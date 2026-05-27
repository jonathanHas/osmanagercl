<?php

namespace App\Http\Controllers;

use App\Models\Harvest;
use App\Models\HarvestProductUnit;
use App\Models\Product;
use App\Models\SupplierLink;
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

        // Lookup of Jon's products keyed by CODE.
        $productLookup = $this->jonProducts()->mapWithKeys(fn ($p) => [
            $p->CODE => [
                'code' => $p->CODE,
                'name' => $p->NAME,
                'category' => $p->CATEGORY,
                'unit' => $unitPrefs->get($p->CODE, 'kg'),
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

        $recentRows = $rowCodes->map(fn ($code) => [
            'code' => $code,
            'name' => $productLookup[$code]['name'],
            'unit' => $productLookup[$code]['unit'],
            'quantity' => optional($existingForDate->get($code))->quantity,
        ]);

        // Remaining Jon products available to add via the search dropdown.
        $availableProducts = $productLookup
            ->reject(fn ($p, $code) => $rowCodes->contains($code))
            ->values();

        return view('fruit-veg.harvest', [
            'selectedDate' => $selectedDate,
            'recentRows' => $recentRows,
            'availableProducts' => $availableProducts,
        ]);
    }

    /**
     * Upsert the submitted rows for the date. A cleared/zero quantity removes
     * that date's row.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'items' => 'array',
            'items.*' => 'nullable|numeric|min:0|max:99999.99',
            'units' => 'array',
            'units.*' => 'in:'.implode(',', HarvestProductUnit::UNITS),
            'notes' => 'array',
            'notes.*' => 'nullable|string|max:500',
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $items = $validated['items'] ?? [];
        $units = $request->input('units', []);
        $notes = $request->input('notes', []);

        // Restrict to Jon's products and snapshot name/unit at save time.
        $jon = $this->jonProducts()->keyBy('CODE');

        foreach ($items as $code => $qty) {
            if (! $jon->has($code)) {
                continue; // ignore anything not belonging to Jon
            }

            $unit = in_array($units[$code] ?? null, HarvestProductUnit::UNITS, true)
                ? $units[$code]
                : 'kg';

            // Remember the per-product unit for future harvest logs.
            HarvestProductUnit::updateOrCreate(
                ['product_code' => $code],
                ['unit' => $unit]
            );

            $qty = ($qty === null || $qty === '') ? 0.0 : (float) $qty;

            if ($qty <= 0) {
                Harvest::where('harvest_date', $date)
                    ->where('product_code', $code)
                    ->delete();

                continue;
            }

            $product = $jon->get($code);

            Harvest::updateOrCreate(
                ['harvest_date' => $date, 'product_code' => $code],
                [
                    'product_name' => $product->NAME,
                    'quantity' => $qty,
                    'unit' => $unit,
                    'notes' => $notes[$code] ?? null,
                    'created_by' => Auth::id(),
                ]
            );
        }

        return redirect()
            ->route('fruit-veg.harvest', ['date' => $date])
            ->with('success', 'Harvest log saved for '.Carbon::parse($date)->format('D j M Y').'.');
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
}
