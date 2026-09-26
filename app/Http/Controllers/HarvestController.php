<?php

namespace App\Http\Controllers;

use App\Models\Harvest;
use App\Models\HarvestProductUnit;
use App\Models\Product;
use App\Models\SupplierLink;
use App\Models\User;
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

        $data = $this->dataFor($selectedDate);

        return view('fruit-veg.harvest', [
            'selectedDate' => $selectedDate,
            'rows' => $data['rows'],
            'availableProducts' => $data['available'],
        ]);
    }

    /**
     * The same rows as the page, as JSON, for the Shop mode harvest screen.
     *
     * Zebra label payloads are dropped: the Shop screen does not print.
     */
    public function rows(Request $request)
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
        ]);

        $date = Carbon::parse($validated['date'] ?? now()->toDateString())->toDateString();
        $data = $this->dataFor($date);

        $logged = Harvest::whereDate('harvest_date', $date)->get()->keyBy('product_code');

        // Not ->with('creator'): Harvest is pinned to 'mysql' and User is not, so
        // the relation would inherit 'mysql' from its parent. That is the same
        // database in production, where 'mysql' is the default connection, but not
        // under test, where the default is sqlite and 'mysql' is repointed.
        // Querying User on its own connection is right on both.
        $names = User::whereIn('id', $logged->pluck('created_by')->filter()->unique()->all())
            ->pluck('name', 'id');

        return response()->json([
            'date' => $date,
            'rows' => collect($data['rows'])->map(function ($row) use ($logged, $names) {
                $entry = $logged->get($row['code']);

                return [
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'unit' => $row['unit'],
                    'image_url' => $row['image_url'],
                    // The Zebra label, so the Shop screen can offer a print after a
                    // log and a reprint from a Today row, as the office page does.
                    'label' => $row['label'],
                    'logged' => $row['logged'],
                    // The unit today's row is actually in, which can differ from the
                    // product's remembered preference; null when nothing is logged.
                    'logged_unit' => $entry?->unit,
                    'updated_at' => $entry?->updated_at?->toIso8601String(),
                    'by' => $entry?->created_by ? $names->get($entry->created_by) : null,
                ];
            })->values(),
            'available' => collect($data['available'])->map(fn ($p) => [
                'code' => $p['code'],
                'name' => $p['name'],
                'unit' => $p['unit'],
                'image_url' => $p['image_url'],
                'label' => $p['label'],
            ])->values(),
        ]);
    }

    /**
     * Rows to show for a date (recently harvested ∪ already logged that day) and
     * the rest of Jon's range, shared by the office page and the Shop screen.
     *
     * @return array{rows: \Illuminate\Support\Collection, available: \Illuminate\Support\Collection}
     */
    private function dataFor(string $selectedDate): array
    {
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
                'image_url' => $p->IMAGE !== null ? $this->imageUrl($p) : null,
                'label' => $this->labelPayload($labels->get($p->CODE)),
            ],
        ]);

        // Quantities already logged for the selected date (prefill).
        $existingForDate = Harvest::whereDate('harvest_date', $selectedDate)
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
            'image_url' => $productLookup[$code]['image_url'],
            'logged' => (float) (optional($existingForDate->get($code))->quantity ?? 0),
            'label' => $productLookup[$code]['label'],
        ])->values();

        // Remaining Jon products available to add via the search dropdown.
        $availableProducts = $productLookup
            ->reject(fn ($p, $code) => $rowCodes->contains($code))
            ->values();

        return ['rows' => $rows, 'available' => $availableProducts];
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

        // Not firstOrNew: its attribute array becomes `where harvest_date = '<Y-m-d>'`,
        // which a date column only matches on MySQL (see WasteController::entry).
        $harvest = Harvest::whereDate('harvest_date', $date)
            ->where('product_code', $code)
            ->first() ?? new Harvest(['harvest_date' => $date, 'product_code' => $code]);

        // One unit per product per day. Saves accumulate, and there is a single
        // quantity column, so adding kilograms to a count produced nonsense: 5.2 kg
        // then 2 units used to read "7.2 unit". Refuse instead, and say what is
        // there and how to change it.
        if ($harvest->exists && $harvest->unit !== $unit) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    "Already logged %s %s of %s today. Log in %s, or remove today's entry on the office harvest page to change the unit.",
                    $this->trimZeros((float) $harvest->quantity),
                    $this->unitLabel($harvest->unit),
                    $product->NAME,
                    $this->unitLabel($harvest->unit)
                ),
                'logged' => (float) $harvest->quantity,
                'unit' => $harvest->unit,
            ], 422);
        }

        // Remember the per-product unit for future harvest logs. After the check,
        // so a refused entry does not quietly change the preference.
        HarvestProductUnit::updateOrCreate(
            ['product_code' => $code],
            ['unit' => $unit]
        );

        // Accumulate onto any existing line for the date (single-user store, so
        // a plain read-add-save is safe).
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
    /**
     * A thumbnail URL for a product that has a photo. See the matching helper on
     * WasteController: `v` is derived from the blob, so the URL changes when the
     * photo does and the route can cache it for a week.
     */
    private function imageUrl(Product $product): string
    {
        return route('fruit-veg.product-image', [
            'code' => $product->CODE,
            'w' => 112,
            'v' => substr(md5($product->IMAGE), 0, 8),
        ]);
    }

    /**
     * "5.2" rather than "5.20", and "2" rather than "2.00" — the message is read
     * aloud on a shop floor, not parsed.
     */
    private function trimZeros(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    private function unitLabel(?string $unit): string
    {
        return $unit === 'kg' ? 'kg' : 'units';
    }

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
