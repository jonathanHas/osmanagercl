<?php

namespace App\Http\Controllers;

use App\Models\Harvest;
use App\Models\HarvestProductUnit;
use App\Models\Product;
use App\Models\SupplierLink;
use App\Models\ZebraLabel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HarvestController extends Controller
{
    /**
     * Combo harvest entry page for a chosen date.
     *
     * Recently harvested products (and anything already logged for the date)
     * are shown with quantity inputs ready to fill/edit; the remainder of Jon's
     * products can be added via the search dropdown.
     */
    public function index(Request $request)
    {
        $selectedDate = Carbon::parse($request->input('date', now()->toDateString()))->toDateString();

        // Saved per-product unit preference (kg|unit); defaults to kg when unset.
        $unitPrefs = HarvestProductUnit::pluck('unit', 'product_code');

        $jonProducts = $this->jonProducts();

        // Active Zebra labels for Jon's products, keyed by product code, so each
        // row/available product can offer inline label printing after saving.
        $labels = ZebraLabel::active()
            ->whereNotNull('product_code')
            ->whereIn('product_code', $jonProducts->pluck('CODE')->all())
            ->get()
            ->keyBy('product_code');

        // Lookup of Jon's products keyed by CODE.
        $productLookup = $jonProducts->mapWithKeys(fn ($p) => [
            $p->CODE => [
                'code' => $p->CODE,
                'name' => $p->NAME,
                'category' => $p->CATEGORY,
                'unit' => $unitPrefs->get($p->CODE, 'kg'),
                'label' => $this->labelPayload($labels->get($p->CODE)),
            ],
        ]);

        // Quantities already logged for the selected date (prefill).
        $existingForDate = Harvest::where('harvest_date', $selectedDate)
            ->get()
            ->keyBy('product_code');

        // Distinct products harvested in the last 30 days, most recent first.
        $recentCodes = Harvest::where('harvest_date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('harvest_date')
            ->pluck('product_code')
            ->unique();

        // Rows to render: recent ∪ already-entered-for-date, still belonging to Jon.
        $rowCodes = $recentCodes
            ->merge($existingForDate->keys())
            ->unique()
            ->filter(fn ($code) => $productLookup->has($code))
            ->values();

        $rows = $rowCodes->map(fn ($code) => [
            'code' => $code,
            'name' => $productLookup[$code]['name'],
            'unit' => $productLookup[$code]['unit'],
            'logged' => (float) (optional($existingForDate->get($code))->quantity ?? 0),
            'label' => $productLookup[$code]['label'],
        ])->values();

        // Remaining Jon products available to add via the search dropdown.
        $availableProducts = $productLookup
            ->reject(fn ($p, $code) => $rowCodes->contains($code))
            ->values();

        return view('fruit-veg.harvest', [
            'selectedDate' => $selectedDate,
            'rows' => $rows,
            'availableProducts' => $availableProducts,
        ]);
    }

    /**
     * Add a single entered amount to a product's running total for the date.
     *
     * Saves accumulate: the submitted amount is added to whatever is already
     * logged for that (date, product) rather than replacing it. Returns the new
     * running total so the row's "Logged" column can update inline. Corrections
     * to an over-logged amount are made by deleting the day's line in history.
     */
    public function saveRow(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'code' => 'required|string',
            'amount' => 'required|numeric|min:0.01|max:99999.99',
            'unit' => 'required|in:'.implode(',', HarvestProductUnit::UNITS),
            'notes' => 'nullable|string|max:500',
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $code = $validated['code'];
        $amount = (float) $validated['amount'];
        $unit = $validated['unit'];

        // Restrict to Jon's products and snapshot the name at save time.
        $product = $this->jonProducts()->firstWhere('CODE', $code);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product does not belong to Jon.',
            ], 422);
        }

        // Remember the per-product unit for future harvest logs.
        HarvestProductUnit::updateOrCreate(
            ['product_code' => $code],
            ['unit' => $unit]
        );

        // Accumulate onto any existing line for the date (single-user store, so
        // a plain read-add-save is safe).
        $harvest = Harvest::firstOrNew([
            'harvest_date' => $date,
            'product_code' => $code,
        ]);

        $harvest->fill([
            'product_name' => $product->NAME,
            'quantity' => (float) ($harvest->quantity ?? 0) + $amount,
            'unit' => $unit,
            'notes' => $validated['notes'] ?? $harvest->notes,
            'created_by' => Auth::id(),
        ])->save();

        return response()->json([
            'success' => true,
            'logged' => (float) $harvest->quantity,
            'unit' => $unit,
            'saved_amount' => $amount,
        ]);
    }

    /**
     * Past harvests grouped by date, newest first.
     */
    public function history()
    {
        $dates = Harvest::query()
            ->selectRaw('harvest_date, COUNT(*) as line_count, SUM(quantity) as total_qty')
            ->groupBy('harvest_date')
            ->orderByDesc('harvest_date')
            ->paginate(20);

        $dateKeys = collect($dates->items())->map(fn ($row) => Carbon::parse($row->harvest_date)->toDateString());

        $rowsByDate = Harvest::whereIn('harvest_date', $dateKeys->all())
            ->orderBy('product_name')
            ->get()
            ->groupBy(fn ($h) => $h->harvest_date->toDateString());

        return view('fruit-veg.harvest-history', compact('dates', 'rowsByDate'));
    }

    /**
     * Delete a single logged harvest line.
     */
    public function destroy(Harvest $harvest)
    {
        $harvest->delete();

        return back()->with('success', 'Harvest entry removed.');
    }

    /**
     * Jon's POS products, de-duplicated on CODE (supplier_link can have dupes).
     *
     * Resolves the barcodes from supplier_link first and uses whereIn rather
     * than a whereHas() correlated subquery against the large PRODUCTS table —
     * the latter takes ~27s, this takes ~30ms.
     */
    private function jonProducts()
    {
        $codes = SupplierLink::where('SupplierID', (string) config('suppliers.jon'))
            ->pluck('Barcode')
            ->unique()
            ->all();

        return Product::whereIn('CODE', $codes)
            ->get()
            ->unique('CODE')
            ->values();
    }

    /**
     * Compact label payload for the view (or null when no label). Dimensions are
     * read from the ZPL, falling back to the stored width/height (same fallback
     * the Zebra labels page uses).
     */
    private function labelPayload(?ZebraLabel $label): ?array
    {
        if (! $label) {
            return null;
        }

        $dims = ZebraLabel::extractDimensions($label->zpl_content);

        return [
            'id' => $label->id,
            'name' => $label->name,
            'width_mm' => $dims['width_mm'] ?? $label->label_width_mm,
            'height_mm' => $dims['height_mm'] ?? $label->label_height_mm,
        ];
    }
}
