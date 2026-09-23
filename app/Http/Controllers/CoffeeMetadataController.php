<?php

namespace App\Http\Controllers;

use App\Models\CoffeeProductMetadata;
use App\Models\KdsOrderItem;
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

        return view('coffee.metadata', compact('coffeeTypes', 'optionsGrouped', 'missingMetadata', 'groupNames', 'sampleDrinkName', 'badgeKinds'));
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
