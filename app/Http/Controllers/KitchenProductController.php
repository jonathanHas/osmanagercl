<?php

namespace App\Http\Controllers;

use App\Models\KitchenIngredientProfile;
use App\Models\KitchenProduct;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitchenProductController extends Controller
{
    /**
     * Display a listing of kitchen products.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        // Get all kitchen products with their POS product data
        $kitchenProductsQuery = KitchenProduct::query()
            ->with('product')
            ->orderBy('created_at', 'desc');

        // Get all kitchen products first
        $kitchenProducts = $kitchenProductsQuery->get();
        $productIds = $kitchenProducts->pluck('product_id')->toArray();

        // Filter by search if provided
        if ($search && $productIds) {
            $matchingProductIds = Product::whereIn('ID', $productIds)
                ->where(function ($q) use ($search) {
                    $q->where('NAME', 'like', "%{$search}%")
                        ->orWhere('CODE', 'like', "%{$search}%")
                        ->orWhere('REFERENCE', 'like', "%{$search}%");
                })
                ->pluck('ID')
                ->toArray();

            $kitchenProducts = $kitchenProducts->filter(function ($kp) use ($matchingProductIds) {
                return in_array($kp->product_id, $matchingProductIds);
            });
            $productIds = $matchingProductIds;
        }

        // Get kitchen sales data (last 8 weeks)
        $kitchenSales = [];
        if (! empty($productIds)) {
            $kitchenSales = $this->getKitchenSalesData($productIds);
        }

        // Get ingredient profile status for each product
        $profiledProductIds = KitchenIngredientProfile::whereIn('pos_product_id', $productIds)
            ->pluck('pos_product_id', 'id')
            ->flip()
            ->toArray();

        // Get supplier info for products
        $supplierInfo = [];
        if (! empty($productIds)) {
            $supplierInfo = $this->getSupplierInfo($productIds);
        }

        // Calculate totals
        $totalKitchenProducts = $kitchenProducts->count();
        $totalWithProfiles = count(array_intersect($productIds, array_keys($profiledProductIds)));

        return view('kitchen.products.index', [
            'kitchenProducts' => $kitchenProducts,
            'kitchenSales' => $kitchenSales,
            'profiledProductIds' => $profiledProductIds,
            'supplierInfo' => $supplierInfo,
            'search' => $search,
            'totalKitchenProducts' => $totalKitchenProducts,
            'totalWithProfiles' => $totalWithProfiles,
        ]);
    }

    /**
     * Toggle a product's kitchen status (add/remove).
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'product_id' => 'required|string',
        ]);

        $productId = $request->input('product_id');

        // Check if product exists
        $exists = KitchenProduct::where('product_id', $productId)->first();

        if ($exists) {
            // Remove from kitchen products
            $exists->delete();

            return response()->json([
                'success' => true,
                'is_kitchen' => false,
                'message' => 'Product removed from kitchen list',
            ]);
        } else {
            // Add to kitchen products
            KitchenProduct::create([
                'product_id' => $productId,
            ]);

            return response()->json([
                'success' => true,
                'is_kitchen' => true,
                'message' => 'Product added to kitchen list',
            ]);
        }
    }

    /**
     * Remove a product from the kitchen list.
     */
    public function destroy(KitchenProduct $kitchenProduct)
    {
        $kitchenProduct->delete();

        return redirect()->route('kitchen.products.index')
            ->with('success', 'Product removed from kitchen list.');
    }

    /**
     * Get kitchen sales data for the given product IDs.
     */
    private function getKitchenSalesData(array $productIds): array
    {
        $weeksBack = 8;
        $endOfCurrentWeek = Carbon::now()->endOfWeek();
        $startRange = Carbon::now()->startOfWeek()->subWeeks($weeksBack - 1);

        // Query kitchen customer sales
        $kitchenData = DB::connection('pos')
            ->table('TICKETLINES')
            ->join('TICKETS', 'TICKETLINES.TICKET', '=', 'TICKETS.ID')
            ->join('RECEIPTS', 'TICKETS.ID', '=', 'RECEIPTS.ID')
            ->join('CUSTOMERS', 'TICKETS.CUSTOMER', '=', 'CUSTOMERS.ID')
            ->whereIn('TICKETLINES.PRODUCT', $productIds)
            ->where('CUSTOMERS.NAME', 'Kitchen')
            ->where('RECEIPTS.DATENEW', '>=', $startRange)
            ->where('RECEIPTS.DATENEW', '<=', $endOfCurrentWeek)
            ->selectRaw('TICKETLINES.PRODUCT as product_id')
            ->selectRaw('SUM(ABS(TICKETLINES.UNITS)) as total_units')
            ->groupBy('TICKETLINES.PRODUCT')
            ->get()
            ->keyBy('product_id');

        // Also get 6-month totals for each product
        $sixMonthsAgo = Carbon::now()->subMonths(6)->startOfDay();
        $sixMonthTotals = DB::connection('pos')
            ->table('TICKETLINES')
            ->join('TICKETS', 'TICKETLINES.TICKET', '=', 'TICKETS.ID')
            ->join('RECEIPTS', 'TICKETS.ID', '=', 'RECEIPTS.ID')
            ->join('CUSTOMERS', 'TICKETS.CUSTOMER', '=', 'CUSTOMERS.ID')
            ->whereIn('TICKETLINES.PRODUCT', $productIds)
            ->where('CUSTOMERS.NAME', 'Kitchen')
            ->where('RECEIPTS.DATENEW', '>=', $sixMonthsAgo)
            ->selectRaw('TICKETLINES.PRODUCT as product_id')
            ->selectRaw('SUM(ABS(TICKETLINES.UNITS)) as total_units')
            ->groupBy('TICKETLINES.PRODUCT')
            ->get()
            ->keyBy('product_id');

        $result = [];
        foreach ($productIds as $productId) {
            $weeklyData = $kitchenData->get($productId);
            $sixMonthData = $sixMonthTotals->get($productId);

            $totalUnits = $weeklyData ? (float) $weeklyData->total_units : 0;
            $avgWeekly = $totalUnits / $weeksBack;
            $total6Months = $sixMonthData ? (float) $sixMonthData->total_units : 0;

            $result[$productId] = [
                'total_8_weeks' => $totalUnits,
                'avg_weekly' => $avgWeekly,
                'total_6_months' => $total6Months,
            ];
        }

        return $result;
    }

    /**
     * Get supplier info for products.
     */
    private function getSupplierInfo(array $productIds): array
    {
        // Get supplier from products table
        $products = Product::whereIn('ID', $productIds)
            ->select('ID', 'SUPPLIER')
            ->get()
            ->keyBy('ID');

        // Get supplier names
        $supplierIds = $products->pluck('SUPPLIER')->filter()->unique()->toArray();
        $suppliers = [];
        if (! empty($supplierIds)) {
            $suppliers = DB::connection('pos')
                ->table('SUPPLIERS')
                ->whereIn('ID', $supplierIds)
                ->pluck('NAME', 'ID')
                ->toArray();
        }

        $result = [];
        foreach ($productIds as $productId) {
            $product = $products->get($productId);
            $supplierId = $product?->SUPPLIER;
            $result[$productId] = $supplierId ? ($suppliers[$supplierId] ?? 'Unknown') : null;
        }

        return $result;
    }
}
