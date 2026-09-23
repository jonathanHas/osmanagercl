<?php

namespace App\Http\Controllers;

use App\Models\CoffeeProductMetadata;
use App\Models\KdsOrderItem;
use App\Models\KdsProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CoffeeMetadataController extends Controller
{
    public function index()
    {
        // Get all metadata, sorted by type then name
        $allMetadata = CoffeeProductMetadata::orderBy('type')->orderBy('product_name')->get();
        $coffeeTypes = $allMetadata->where('type', 'coffee');
        $optionsGrouped = $allMetadata->where('type', 'option')->groupBy('group_name');
        $groupNames = $optionsGrouped->keys()->filter()->sort()->values();

        // Flag rows whose product is not on the active KDS allow-list: the
        // importers drop those ticket lines, so the product never reaches /kds.
        $this->flagKdsListing($allMetadata);
        $unlisted = $allMetadata->where('kds_listed', '===', false)->values();

        // Get any products that don't have metadata yet
        $allCoffeeProducts = DB::connection('pos')
            ->table('PRODUCTS')
            ->where('CATEGORY', '081')
            ->select('ID', 'NAME', 'DISPLAY')
            ->get();

        $missingMetadata = $allCoffeeProducts->filter(function ($product) {
            return ! CoffeeProductMetadata::where('product_id', $product->ID)->exists();
        })->map(function ($product) {
            // The /kds card shows the POS display name for drink lines, so surface it for the preview.
            $product->kds_name = KdsOrderItem::cleanPosDisplay($product->DISPLAY) ?? $product->NAME;

            return $product;
        });

        // A real drink to anchor the KDS preview when adding an option/modifier.
        $sampleDrinkName = $coffeeTypes->where('is_active', true)->sortBy('display_order')->first()?->product_name ?? 'Latte';

        // Badge picker options for option rows, grouped by family.
        $badgeKinds = CoffeeProductMetadata::BADGE_KINDS;

        return view('coffee.metadata', compact('coffeeTypes', 'optionsGrouped', 'missingMetadata', 'groupNames', 'sampleDrinkName', 'badgeKinds', 'unlisted'));
    }

    /**
     * Set kds_listed on each row: true when on the active allow-list, false
     * when a real POS product is missing from it, null when the product does
     * not exist in the POS at all (synthetic ids), which cannot be listed.
     */
    private function flagKdsListing($metadata): void
    {
        $productIds = $metadata->pluck('product_id')->unique()->values();

        $listed = KdsProduct::active()->whereIn('product_id', $productIds)->pluck('product_id')->flip();
        $inPos = DB::connection('pos')->table('PRODUCTS')->whereIn('ID', $productIds)->pluck('ID')->flip();

        foreach ($metadata as $row) {
            $row->kds_listed = isset($listed[$row->product_id])
                ? true
                : (isset($inPos[$row->product_id]) ? false : null);
        }
    }

    /**
     * Put one metadata row's product on the KDS allow-list.
     */
    public function listOnKds(CoffeeProductMetadata $metadata)
    {
        $result = KdsProduct::ensureListed($metadata->product_id);

        if ($result['action'] === 'not_in_pos') {
            return response()->json([
                'success' => false,
                'message' => 'This product does not exist in the POS, so it cannot be sent to the KDS.',
            ], 422);
        }

        return response()->json(['success' => true, 'action' => $result['action']]);
    }

    /**
     * Put every metadata row's product on the KDS allow-list (same rule as
     * `php artisan kds:sync-products`).
     */
    public function syncKds()
    {
        $counts = ['created' => 0, 'reactivated' => 0, 'unchanged' => 0, 'not_in_pos' => 0];

        foreach (CoffeeProductMetadata::pluck('product_id') as $productId) {
            $counts[KdsProduct::ensureListed($productId)['action']]++;
        }

        $added = $counts['created'] + $counts['reactivated'];

        return response()->json([
            'success' => true,
            'counts' => $counts,
            'message' => $added > 0
                ? "Added {$added} product(s) to the KDS."
                : 'Every product with metadata is already on the KDS.',
        ]);
    }

    public function update(Request $request, CoffeeProductMetadata $metadata)
    {
        $request->validate([
            'short_name' => 'required|string|max:20',
            'type' => 'required|in:coffee,option',
            'group_name' => 'nullable|string|max:50',
            'badge_kind' => ['nullable', 'string', Rule::in(array_keys(CoffeeProductMetadata::BADGE_KINDS))],
            'display_order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $metadata->update($request->only([
            'short_name',
            'type',
            'group_name',
            'badge_kind',
            'display_order',
            'is_active',
        ]));

        return response()->json(['success' => true]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|string|unique:coffee_product_metadata,product_id',
            'product_name' => 'required|string',
            'short_name' => 'required|string|max:20',
            'type' => 'required|in:coffee,option',
            'group_name' => 'nullable|string|max:50',
            'badge_kind' => ['nullable', 'string', Rule::in(array_keys(CoffeeProductMetadata::BADGE_KINDS))],
            'display_order' => 'required|integer|min:0',
        ]);

        CoffeeProductMetadata::create($request->only([
            'product_id',
            'product_name',
            'short_name',
            'type',
            'group_name',
            'badge_kind',
            'display_order',
        ]));

        return response()->json(['success' => true]);
    }

    public function destroy(CoffeeProductMetadata $metadata)
    {
        try {
            $metadata->delete();

            return response()->json([
                'success' => true,
                'message' => 'Metadata deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete metadata: '.$e->getMessage(),
            ], 500);
        }
    }

    public function addSpecificSyrups()
    {
        // Add your specific syrups
        $syrups = [
            'Vanilla Syrup' => 'Van',
            'Hazelnut Syrup' => 'Haz',
            'Caramel Syrup' => 'Car',
        ];

        $created = 0;
        foreach ($syrups as $name => $shortName) {
            // Check if this syrup already exists
            if (! CoffeeProductMetadata::where('product_name', $name)->exists()) {
                // Create a dummy product ID for manual tracking
                $productId = 'SYRUP_'.strtoupper(str_replace(' ', '_', $name));

                CoffeeProductMetadata::create([
                    'product_id' => $productId,
                    'product_name' => $name,
                    'type' => 'option',
                    'short_name' => $shortName,
                    'group_name' => 'Syrups',
                    'display_order' => $created + 10, // Order after existing syrups
                    'is_active' => true,
                ]);
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Added {$created} new syrup entries",
            'created' => $created,
        ]);
    }
}
