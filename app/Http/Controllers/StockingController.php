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

    /**
     * Display stock adjustment logs (admin only).
     */
    public function logs(Request $request)
    {
        $query = StockAdjustment::with('user')
            ->orderBy('created_at', 'desc');

        // Filter by barcode/product
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('barcode', 'like', "%{$search}%");
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $adjustments = $query->paginate(50)->withQueryString();

        // Get users for filter dropdown
        $users = \App\Models\User::orderBy('name')->get();

        return view('stocking.logs', compact('adjustments', 'users'));
    }
}
