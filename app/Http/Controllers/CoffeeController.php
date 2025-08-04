<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Repositories\OptimizedSalesRepository;
use App\Services\TillVisibilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoffeeController extends Controller
{
    /**
     * The optimized sales repository instance for blazing-fast queries.
     */
    protected OptimizedSalesRepository $optimizedSalesRepository;

    /**
     * The till visibility service instance.
     */
    protected TillVisibilityService $tillVisibilityService;

    /**
     * Coffee category IDs
     */
    const COFFEE_FRESH_CATEGORIES = ['081']; // Only Coffee Fresh, not retail packs

    /**
     * Create a new controller instance.
     */
    public function __construct(OptimizedSalesRepository $optimizedSalesRepository, TillVisibilityService $tillVisibilityService)
    {
        $this->optimizedSalesRepository = $optimizedSalesRepository;
        $this->tillVisibilityService = $tillVisibilityService;
    }

    /**
     * Display the main Coffee dashboard.
     */
    public function index()
    {
        // Get basic coffee statistics
        $totalCoffee = Product::whereIn('CATEGORY', self::COFFEE_FRESH_CATEGORIES)->count();
        $visibleCoffee = $this->getVisibleCoffeeCount();

        // Get featured coffee products (recently added to till)
        $featuredProducts = $this->getFeaturedCoffeeProducts();

        return view('coffee.index', compact('totalCoffee', 'visibleCoffee', 'featuredProducts'));
    }

    /**
     * Display coffee products list with till visibility management.
     */
    public function products(Request $request)
    {
        $search = $request->get('search', '');
        $availability = $request->get('availability', 'all');
        $perPage = 50;

        $query = Product::whereIn('CATEGORY', self::COFFEE_FRESH_CATEGORIES)
            ->with(['category']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('NAME', 'like', "%{$search}%")
                  ->orWhere('CODE', 'like', "%{$search}%");
            });
        }

        // Apply availability filter
        if ($availability === 'available') {
            $visibleIds = \App\Models\ProductsCat::pluck('PRODUCT')->toArray();
            $query->whereIn('ID', $visibleIds);
        } elseif ($availability === 'unavailable') {
            $visibleIds = \App\Models\ProductsCat::pluck('PRODUCT')->toArray();
            $query->whereNotIn('ID', $visibleIds);
        }

        $products = $query->orderBy('NAME')->get();

        // Add visibility status to each product
        $products->transform(function ($product) {
            $product->is_visible = $this->tillVisibilityService->isVisibleOnTill($product->ID);
            return $product;
        });

        // Return JSON for AJAX requests
        if ($request->ajax()) {
            return response()->json([
                'products' => $products->map(function($product) {
                    $product->is_available = $product->is_visible;
                    $product->current_price = $product->PRICESELL * (1 + $product->getVatRate());
                    return $product;
                })
            ]);
        }

        return view('coffee.products', compact('products', 'search'));
    }

    /**
     * Toggle coffee product visibility on till.
     */
    public function toggleVisibility(Request $request)
    {
        $request->validate([
            'product_id' => 'required|string',
            'visible' => 'required|boolean',
        ]);

        $success = $this->tillVisibilityService->setVisibility(
            $request->product_id,
            $request->visible,
            'coffee'
        );

        return response()->json(['success' => $success]);
    }

    /**
     * Display coffee sales analytics dashboard.
     */
    public function sales()
    {
        // Set smart date defaults - last 30 days or available data range
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(29);

        return view('coffee.sales', compact('startDate', 'endDate'));
    }

    /**
     * Get coffee sales data for AJAX requests.
     */
    public function getSalesData(Request $request)
    {
        $startDate = $request->get('start_date') 
            ? Carbon::parse($request->get('start_date'))->startOfDay()
            : Carbon::now()->subDays(29)->startOfDay();

        $endDate = $request->get('end_date')
            ? Carbon::parse($request->get('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        try {
            // Get coffee sales data using grouped products (like F&V)
            $limit = $request->get('limit', 50);
            $sales = $this->optimizedSalesRepository->getTopCoffeeProducts($startDate, $endDate, $limit);
            $stats = $this->optimizedSalesRepository->getCoffeeSalesStats($startDate, $endDate);
            $dailySales = $this->optimizedSalesRepository->getCoffeeDailySales($startDate, $endDate);

            // Add category names from database for frontend compatibility
            $sales = $sales->map(function ($product) {
                // Since we only show Coffee Fresh (081), we can simplify this
                $product->category_name = 'Coffee Fresh';
                $product->category = $product->category_id; // For template compatibility
                return $product;
            });

            // Apply search filter if provided
            $searchTerm = $request->get('search', '');
            if ($searchTerm) {
                $sales = $sales->filter(function ($sale) use ($searchTerm) {
                    return stripos($sale->product_name, $searchTerm) !== false ||
                           stripos($sale->product_code, $searchTerm) !== false;
                });
            }

            return response()->json([
                'sales' => $sales->values(),
                'stats' => $stats,
                'daily_sales' => $dailySales,
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'days' => $startDate->diffInDays($endDate) + 1,
                ],
                'performance_info' => [
                    'execution_time_ms' => round(microtime(true) * 1000 - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)) * 1000, 2),
                    'data_source' => 'optimized_pre_aggregated'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error loading coffee sales data', [
                'error' => $e->getMessage(),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]);

            return response()->json([
                'error' => 'Failed to load coffee sales data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Serve product image from POS database.
     */
    public function productImage($code)
    {
        $product = Product::where('CODE', $code)->first();
        if (! $product || ! $product->IMAGE) {
            // Return a simple 1x1 transparent PNG
            $transparentPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAFBHrE9YAAAAABJRU5ErkJggg==');
            return response($transparentPng, 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        return response($product->IMAGE, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Get daily sales breakdown for a specific coffee product.
     */
    public function getProductDailySales(Request $request, string $code)
    {
        $product = Product::where('CODE', $code)
            ->whereIn('CATEGORY', self::COFFEE_FRESH_CATEGORIES)
            ->firstOrFail();

        // Get date range from request
        $startDate = $request->get('start_date')
            ? Carbon::parse($request->get('start_date'))
            : Carbon::now()->subDays(29)->startOfDay();

        $endDate = $request->get('end_date')
            ? Carbon::parse($request->get('end_date'))
            : Carbon::now()->endOfDay();

        try {
            // Try optimized repository first
            $dailySales = $this->optimizedSalesRepository->getProductDailySales($product->ID, $startDate, $endDate);

            // If no optimized data, use live POS queries
            if ($dailySales->isEmpty()) {
                $dailySales = DB::connection('pos')
                    ->table('TICKETLINES as tl')
                    ->join('RECEIPTS as r', 'tl.TICKET', '=', 'r.ID')
                    ->join('PRODUCTS as p', 'tl.PRODUCT', '=', 'p.ID')
                    ->where('p.CODE', $code)
                    ->whereBetween('r.DATENEW', [$startDate, $endDate])
                    ->selectRaw('
                        DATE(r.DATENEW) as sale_date,
                        SUM(tl.UNITS) as daily_units,
                        SUM(tl.UNITS * tl.PRICE) as daily_revenue,
                        AVG(tl.PRICE) as avg_price,
                        COUNT(DISTINCT r.ID) as transactions
                    ')
                    ->groupBy('sale_date')
                    ->orderBy('sale_date', 'asc')
                    ->get();
            }

            // Calculate totals
            $totalUnits = $dailySales->sum('daily_units');
            $totalRevenue = $dailySales->sum('daily_revenue');

            return response()->json([
                'success' => true,
                'product' => [
                    'code' => $product->CODE,
                    'name' => $product->NAME,
                ],
                'daily_sales' => $dailySales,
                'summary' => [
                    'total_units' => (float) $totalUnits,
                    'total_revenue' => (float) $totalRevenue,
                    'days_with_sales' => $dailySales->count(),
                    'avg_daily_units' => $dailySales->count() > 0 ? $totalUnits / $dailySales->count() : 0,
                ],
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting coffee product daily sales', [
                'product_code' => $code,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load daily sales data',
            ], 500);
        }
    }

    /**
     * Get count of coffee products visible on till.
     */
    private function getVisibleCoffeeCount(): int
    {
        return Product::whereIn('CATEGORY', self::COFFEE_FRESH_CATEGORIES)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('PRODUCTS_CAT')
                    ->whereColumn('PRODUCTS_CAT.PRODUCT', 'PRODUCTS.ID');
            })
            ->count();
    }

    /**
     * Get featured coffee products for dashboard.
     */
    private function getFeaturedCoffeeProducts()
    {
        return Product::whereIn('CATEGORY', self::COFFEE_FRESH_CATEGORIES)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('PRODUCTS_CAT')
                    ->whereColumn('PRODUCTS_CAT.PRODUCT', 'PRODUCTS.ID');
            })
            ->with(['category'])
            ->orderBy('NAME')
            ->limit(8)
            ->get();
    }
}