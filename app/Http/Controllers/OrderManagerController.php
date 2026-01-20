<?php

namespace App\Http\Controllers;

use App\Models\AccountingSupplier;
use App\Services\OrderManagerService;
use Illuminate\Http\Request;

class OrderManagerController extends Controller
{
    public function __construct(private OrderManagerService $service) {}

    /**
     * Display the Order Manager dashboard.
     */
    public function index()
    {
        $stats = $this->service->getStats();
        $posLinkedSuppliers = $this->service->getPosLinkedSuppliers();

        // Add low stock count to each supplier for display
        foreach ($posLinkedSuppliers as $supplier) {
            $supplier->low_stock_count = $supplier->is_order_managed
                ? $this->service->getLowStockCountForSupplier($supplier)
                : null;
        }

        return view('order-manager.index', [
            'stats' => $stats,
            'suppliers' => $posLinkedSuppliers,
        ]);
    }

    /**
     * Run the stock check and display results.
     */
    public function check()
    {
        $results = $this->service->runStockCheck();
        $stats = $this->service->getStats();

        return view('order-manager.results', [
            'results' => $results,
            'stats' => $stats,
            'checkTime' => now(),
        ]);
    }

    /**
     * Toggle a supplier's order-managed status.
     */
    public function toggleManaged(AccountingSupplier $supplier)
    {
        if (! $supplier->is_pos_linked) {
            return response()->json([
                'success' => false,
                'message' => 'Only POS-linked suppliers can be managed.',
            ], 422);
        }

        $supplier->is_order_managed = ! $supplier->is_order_managed;
        $supplier->save();

        return response()->json([
            'success' => true,
            'is_order_managed' => $supplier->is_order_managed,
            'message' => $supplier->is_order_managed
                ? 'Supplier added to Order Manager.'
                : 'Supplier removed from Order Manager.',
        ]);
    }

    /**
     * Update a supplier's stock threshold.
     */
    public function updateThreshold(Request $request, AccountingSupplier $supplier)
    {
        $validated = $request->validate([
            'threshold' => 'required|integer|min:0|max:1000',
        ]);

        $supplier->order_manager_threshold = $validated['threshold'];
        $supplier->save();

        return response()->json([
            'success' => true,
            'threshold' => $supplier->order_manager_threshold,
            'message' => 'Threshold updated successfully.',
        ]);
    }

    /**
     * Get all products for a supplier (AJAX endpoint).
     */
    public function products(AccountingSupplier $supplier)
    {
        if (! $supplier->is_pos_linked || ! $supplier->external_pos_id) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier is not linked to POS.',
            ], 422);
        }

        $products = $this->service->getAllProductsForSupplier(
            $supplier->external_pos_id,
            $supplier->order_manager_threshold
        );

        return response()->json([
            'success' => true,
            'products' => $products,
            'threshold' => $supplier->order_manager_threshold,
            'total_count' => $products->count(),
            'low_stock_count' => $products->where('stock_status', 'low_stock')->count(),
            'out_of_stock_count' => $products->where('stock_status', 'out_of_stock')->count(),
        ]);
    }
}
