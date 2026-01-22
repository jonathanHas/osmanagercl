<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockCurrent;
use Illuminate\Http\Request;

class StockingController extends Controller
{
    /**
     * Display the stocking scanner page.
     */
    public function index()
    {
        return view('stocking.index');
    }

    /**
     * Lookup a product by barcode and return stock information.
     */
    public function lookup(Request $request)
    {
        $request->validate(['barcode' => 'required|string']);

        $product = Product::with('stockCurrent', 'category')
            ->where('CODE', $request->barcode)
            ->first();

        if (! $product) {
            return response()->json([
                'found' => false,
                'message' => 'Product not found',
            ]);
        }

        return response()->json([
            'found' => true,
            'product' => [
                'name' => $product->NAME,
                'code' => $product->CODE,
                'category' => $product->category?->NAME ?? 'Uncategorized',
            ],
            'stock' => $product->getCurrentStock(),
        ]);
    }

    /**
     * Update stock for a product and log the adjustment.
     */
    public function updateStock(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
            'new_stock' => 'required|numeric|min:0|max:9999.99',
        ]);

        $product = Product::with('stockCurrent')
            ->where('CODE', $request->barcode)
            ->first();

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $oldStock = $product->getCurrentStock();
        $newStock = (float) $request->new_stock;

        // Skip if no change
        if ($oldStock == $newStock) {
            return response()->json([
                'success' => true,
                'message' => 'No change needed',
                'stock' => $newStock,
            ]);
        }

        try {
            // Update or create stock record in STOCKCURRENT table
            StockCurrent::updateOrCreate(
                ['PRODUCT' => $product->ID],
                ['UNITS' => $newStock, 'LOCATION' => '0', 'ATTRIBUTESETINSTANCE_ID' => null]
            );

            // Log the adjustment for audit trail
            StockAdjustment::create([
                'barcode' => $product->CODE,
                'product_id' => $product->ID,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'adjustment' => $newStock - $oldStock,
                'user_id' => auth()->id(),
                'source' => 'stocking',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock updated',
                'old_stock' => $oldStock,
                'stock' => $newStock,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update stock from stocking page', [
                'barcode' => $request->barcode,
                'new_stock' => $newStock,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock',
            ], 500);
        }
    }
}
