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
        $supplierFilter = $request->input('supplier');
        $groupByCategory = $request->boolean('group_by_category', false);

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

        // Get unique suppliers for dropdown (before filtering)
        $availableSuppliers = collect($supplierInfo)->filter()->unique()->sort()->values();

        // Get category info for each product
        $categoryInfo = [];
        if (! empty($productIds)) {
            $categoryInfo = $this->getCategoryInfo($productIds);
        }

        // Filter by supplier if selected
        if ($supplierFilter) {
            $kitchenProducts = $kitchenProducts->filter(function ($kp) use ($supplierInfo, $supplierFilter) {
                return ($supplierInfo[$kp->product_id] ?? null) === $supplierFilter;
            });
        }

        // Calculate totals (after all filters)
        $filteredProductIds = $kitchenProducts->pluck('product_id')->toArray();
        $totalKitchenProducts = $kitchenProducts->count();
        $totalWithProfiles = count(array_intersect($filteredProductIds, array_keys($profiledProductIds)));

        return view('kitchen.products.index', [
            'kitchenProducts' => $kitchenProducts,
            'kitchenSales' => $kitchenSales,
            'profiledProductIds' => $profiledProductIds,
            'supplierInfo' => $supplierInfo,
            'search' => $search,
            'totalKitchenProducts' => $totalKitchenProducts,
            'totalWithProfiles' => $totalWithProfiles,
            'availableSuppliers' => $availableSuppliers,
            'selectedSupplier' => $supplierFilter,
            'categoryInfo' => $categoryInfo,
            'groupByCategory' => $groupByCategory,
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
     * Search for products to add to kitchen list.
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        // Get existing kitchen product IDs to exclude
        $existingIds = KitchenProduct::pluck('product_id')->toArray();

        // Search by supplier code first (from supplier_link table)
        $supplierCodeMatches = DB::connection('pos')
            ->table('supplier_link')
            ->where('SupplierCode', 'like', "%{$query}%")
            ->pluck('Barcode')
            ->toArray();

        // Search products by name, code (barcode), or matching supplier codes
        $products = Product::where(function ($q) use ($query, $supplierCodeMatches) {
            $q->where('NAME', 'like', "%{$query}%")
                ->orWhere('CODE', 'like', "%{$query}%")
                ->orWhere('REFERENCE', 'like', "%{$query}%");

            if (! empty($supplierCodeMatches)) {
                $q->orWhereIn('CODE', $supplierCodeMatches);
            }
        })
            ->whereNotIn('ID', $existingIds)
            ->with('supplierLink')
            ->limit(15)
            ->get();

        // Get supplier names
        $supplierIds = $products->map(fn ($p) => $p->supplierLink?->SupplierID)->filter()->unique()->values()->toArray();
        $suppliers = [];
        if (! empty($supplierIds)) {
            $suppliers = DB::connection('pos')
                ->table('suppliers')
                ->whereIn('SupplierID', $supplierIds)
                ->pluck('Supplier', 'SupplierID')
                ->toArray();
        }

        $results = $products->map(function ($product) use ($suppliers) {
            $supplierId = $product->supplierLink?->SupplierID;
            $supplierCode = $product->supplierLink?->SupplierCode;

            return [
                'id' => $product->ID,
                'name' => $product->NAME,
                'code' => $product->CODE,
                'supplier_code' => $supplierCode,
                'supplier' => $supplierId ? ($suppliers[$supplierId] ?? null) : null,
            ];
        });

        return response()->json($results);
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
        // Get products with their supplier links
        $products = Product::whereIn('ID', $productIds)
            ->with('supplierLink')
            ->get()
            ->keyBy('ID');

        // Get all supplier IDs
        $supplierIds = $products->map(fn ($p) => $p->supplierLink?->SupplierID)->filter()->unique()->values()->toArray();

        // Fetch supplier names in one query (column is 'Supplier', not 'NAME')
        $suppliers = [];
        if (! empty($supplierIds)) {
            $suppliers = DB::connection('pos')
                ->table('suppliers')
                ->whereIn('SupplierID', $supplierIds)
                ->pluck('Supplier', 'SupplierID')
                ->toArray();
        }

        $result = [];
        foreach ($productIds as $productId) {
            $product = $products->get($productId);
            $supplierId = $product?->supplierLink?->SupplierID;
            $result[$productId] = $supplierId ? ($suppliers[$supplierId] ?? null) : null;
        }

        return $result;
    }

    /**
     * Get category info for products.
     */
    private function getCategoryInfo(array $productIds): array
    {
        $products = Product::whereIn('ID', $productIds)
            ->with('category')
            ->get()
            ->keyBy('ID');

        $result = [];
        foreach ($productIds as $productId) {
            $product = $products->get($productId);
            $result[$productId] = [
                'id' => $product?->CATEGORY,
                'name' => $product?->category?->NAME ?? 'Uncategorized',
            ];
        }

        return $result;
    }
}
