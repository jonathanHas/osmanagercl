<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\WasteLog;
use App\Services\TillVisibilityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WasteController extends Controller
{
    public function __construct(
        protected TillVisibilityService $tillVisibilityService
    ) {}

    /**
     * Waste log entry page for a chosen date.
     *
     * Lists the till-visible F&V range (plus anything already logged for the
     * date) with inline amount inputs that save instantly. The search bar
     * covers the full F&V range via the search() endpoint.
     */
    public function index(Request $request)
    {
        $selectedDate = Carbon::parse($request->input('date', now()->toDateString()))->toDateString();

        $onTill = $this->tillVisibilityService->getProductsWithVisibility('fruit_veg', ['visibility' => 'visible']);

        $existing = WasteLog::where('waste_date', $selectedDate)->get()->keyBy('product_code');

        // Off-till products already logged for this date stay visible/editable.
        $onTillCodes = $onTill->pluck('CODE')->all();
        $missingCodes = $existing->keys()->reject(fn ($code) => in_array($code, $onTillCodes))->values();
        $offTill = $missingCodes->isNotEmpty()
            ? Product::whereIn('CODE', $missingCodes->all())->get()
                ->unique('CODE')->values()
                ->each(fn ($p) => $p->is_visible_on_till = false)
            : collect();

        $rows = $this->buildRows($onTill->concat($offTill), $existing);

        return view('fruit-veg.waste', [
            'selectedDate' => $selectedDate,
            'rows' => $rows,
        ]);
    }

    /**
     * Instant-save a single waste entry (AJAX). Upserts the (date, product)
     * row; a cleared/zero quantity removes it.
     */
    public function entry(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'product_code' => 'required|string',
            'quantity' => 'nullable|numeric|min:0|max:99999.99',
            'unit' => 'in:'.implode(',', WasteLog::UNITS),
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $code = $validated['product_code'];

        // Only accept products from the F&V range.
        $product = Product::whereIn('CATEGORY', TillVisibilityService::CATEGORY_MAPPINGS['fruit_veg'])
            ->where('CODE', $code)
            ->first();

        if (! $product) {
            return response()->json(['error' => 'Unknown product'], 422);
        }

        $qty = isset($validated['quantity']) ? (float) $validated['quantity'] : 0.0;
        $unit = $validated['unit'] ?? 'kg';

        if ($qty <= 0) {
            WasteLog::where('waste_date', $date)
                ->where('product_code', $code)
                ->delete();

            return response()->json(['deleted' => true]);
        }

        // Snapshot price/value at save time so historical totals stay stable.
        $price = $this->currentPrices([$code])->get($code) ?? $product->getGrossPrice();
        $pricedUnit = ($product->vegDetails?->unit_name ?: 'kg') === 'kg' ? 'kg' : 'unit';
        $value = $unit === $pricedUnit ? round($qty * $price, 2) : null;

        $entry = WasteLog::updateOrCreate(
            ['waste_date' => $date, 'product_code' => $code],
            [
                'product_name' => $product->NAME,
                'quantity' => $qty,
                'unit' => $unit,
                'unit_price' => round($price, 2),
                'value' => $value,
                'created_by' => Auth::id(),
            ]
        );

        return response()->json([
            'saved' => true,
            'quantity' => (float) $entry->quantity,
            'unit' => $entry->unit,
            'value' => $entry->value !== null ? (float) $entry->value : null,
        ]);
    }

    /**
     * Search the full F&V range (on-till and beyond) for the waste page (AJAX).
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:100',
            'date' => 'nullable|date',
        ]);

        $products = $this->tillVisibilityService
            ->searchAllProductsWithVisibility('fruit_veg', $validated['q'])
            ->take(50);

        $date = Carbon::parse($validated['date'] ?? now()->toDateString())->toDateString();
        $existing = WasteLog::where('waste_date', $date)
            ->whereIn('product_code', $products->pluck('CODE'))
            ->get()
            ->keyBy('product_code');

        return response()->json([
            'products' => $this->buildRows($products, $existing)->values(),
        ]);
    }

    /**
     * Past waste grouped by date, newest first.
     */
    public function history()
    {
        $dates = WasteLog::query()
            ->selectRaw('waste_date, COUNT(*) as line_count, SUM(quantity) as total_qty, SUM(value) as total_value')
            ->groupBy('waste_date')
            ->orderByDesc('waste_date')
            ->paginate(20);

        $dateKeys = collect($dates->items())->map(fn ($row) => Carbon::parse($row->waste_date)->toDateString());

        $rowsByDate = WasteLog::whereIn('waste_date', $dateKeys->all())
            ->orderBy('product_name')
            ->get()
            ->groupBy(fn ($w) => $w->waste_date->toDateString());

        return view('fruit-veg.waste-history', compact('dates', 'rowsByDate'));
    }

    /**
     * Delete a single logged waste line.
     */
    public function destroy(WasteLog $wasteLog)
    {
        $wasteLog->delete();

        return back()->with('success', 'Waste entry removed.');
    }

    /**
     * Map POS products + any existing entries to the flat row shape the
     * Alpine component consumes. Prices and veg details are batch-loaded
     * to avoid N+1 cross-database queries.
     */
    private function buildRows(EloquentCollection|Collection $products, Collection $existing): Collection
    {
        $products = EloquentCollection::make($products->all());
        $products->load('category', 'vegDetails.country', 'vegDetails.vegUnit', 'vegDetails.vegClass');

        $codes = $products->pluck('CODE')->all();
        $historyPrices = $this->currentPrices($codes);

        // Unit last used for each product on any prior day (single batched query):
        // default chain is today's entry -> last-used unit -> the priced unit.
        $lastUnits = WasteLog::whereIn('product_code', $codes)
            ->orderByDesc('waste_date')
            ->get(['product_code', 'unit'])
            ->unique('product_code')
            ->pluck('unit', 'product_code');

        return $products->map(function ($product) use ($historyPrices, $existing, $lastUnits) {
            $pricedUnit = ($product->vegDetails?->unit_name ?: 'kg') === 'kg' ? 'kg' : 'unit';
            $entry = $existing->get($product->CODE);

            return [
                'code' => $product->CODE,
                'name' => $product->NAME,
                'category' => $product->category?->NAME ?? $product->CATEGORY,
                'origin' => $product->vegDetails?->country?->name,
                'class' => $product->vegDetails?->class_name ?: null,
                'on_till' => (bool) ($product->is_visible_on_till ?? false),
                'current_price' => round((float) ($historyPrices->get($product->CODE) ?? $product->getGrossPrice()), 2),
                'priced_unit' => $pricedUnit,
                'quantity' => $entry ? (float) $entry->quantity : null,
                'unit' => $entry->unit ?? $lastUnits->get($product->CODE, $pricedUnit),
                'value' => $entry && $entry->value !== null ? (float) $entry->value : null,
            ];
        })->values();
    }

    /**
     * Latest veg_price_history price per product code (single batched query).
     */
    private function currentPrices(array $codes): Collection
    {
        if (empty($codes)) {
            return collect();
        }

        return DB::table('veg_price_history')
            ->whereIn('product_code', $codes)
            ->select('product_code', 'new_price', 'changed_at')
            ->get()
            ->groupBy('product_code')
            ->map(fn ($records) => (float) $records->sortByDesc('changed_at')->first()->new_price);
    }
}
