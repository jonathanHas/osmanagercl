<?php

namespace App\Http\Controllers;

use App\Models\CoffeeProductMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoffeeMetadataController extends Controller
{
    public function index()
    {
        // Get all metadata, sorted by type then name
        $allMetadata = CoffeeProductMetadata::orderBy('type')->orderBy('product_name')->get();
        $coffeeTypes = $allMetadata->where('type', 'coffee');
        $optionsGrouped = $allMetadata->where('type', 'option')->groupBy('group_name');

        // Get any products that don't have metadata yet
        $allCoffeeProducts = DB::connection('pos')
            ->table('PRODUCTS')
            ->where('CATEGORY', '081')
            ->select('ID', 'NAME')
            ->get();

        $missingMetadata = $allCoffeeProducts->filter(function ($product) {
            return ! CoffeeProductMetadata::where('product_id', $product->ID)->exists();
        });

        return view('coffee.metadata', compact('coffeeTypes', 'optionsGrouped', 'missingMetadata'));
    }

    public function update(Request $request, CoffeeProductMetadata $metadata)
    {
        $request->validate([
            'short_name' => 'required|string|max:20',
            'type' => 'required|in:coffee,option',
            'group_name' => 'nullable|string|max:50',
            'display_order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $metadata->update($request->only([
            'short_name',
            'type',
            'group_name',
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
            'display_order' => 'required|integer|min:0',
        ]);

        CoffeeProductMetadata::create($request->all());

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
