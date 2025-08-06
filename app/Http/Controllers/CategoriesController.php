<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductsCat;
use App\Repositories\OptimizedSalesRepository;
use App\Services\TillVisibilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoriesController extends Controller
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
     * Create a new controller instance.
     */
    public function __construct(OptimizedSalesRepository $optimizedSalesRepository, TillVisibilityService $tillVisibilityService)
    {
        $this->optimizedSalesRepository = $optimizedSalesRepository;
        $this->tillVisibilityService = $tillVisibilityService;
    }

    /**
     * Display a listing of all categories.
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $showEmpty = $request->get('show_empty', false);
        $period = $request->get('period', 'month'); // 'week' or 'month'
        $sortBy = $request->get('sort', 'revenue'); // 'name' or 'revenue'
        
        $query = Category::query()
            ->withCount('products')
            ->orderBy('NAME');
        
        if ($search) {
            $query->where('NAME', 'like', "%{$search}%");
        }
        
        if (!$showEmpty) {
            $query->having('products_count', '>', 0);
        }
        
        $categories = $query->get();
        
        // Set date range based on period
        $endDate = Carbon::now();
        $startDate = $period === 'week' 
            ? Carbon::now()->subWeek() 
            : Carbon::now()->subMonth();
        
        // Get revenue data for each category
        $categoryRevenues = collect();
        $totalRevenue = 0;
        
        foreach ($categories as $category) {
            $categoryIds = $this->getCategoryIdsWithChildren($category);
            $stats = $this->optimizedSalesRepository->getCategorySalesStats($categoryIds, $startDate, $endDate);
            
            $revenue = (float) $stats['total_revenue'];
            $categoryRevenues->push([
                'id' => $category->ID,
                'revenue' => $revenue
            ]);
            $totalRevenue += $revenue;
        }
        
        // Add revenue stats to each category
        $categories->transform(function ($category) use ($categoryRevenues, $totalRevenue) {
            $categoryRevenue = $categoryRevenues->firstWhere('id', $category->ID);
            $revenue = $categoryRevenue['revenue'] ?? 0;
            
            $category->revenue = $revenue;
            $category->revenue_percentage = $totalRevenue > 0 
                ? round(($revenue / $totalRevenue) * 100, 1)
                : 0;
            
            return $category;
        });
        
        // Apply sorting based on user selection
        if ($sortBy === 'name') {
            $categories = $categories->sortBy('NAME')->values();
        } else {
            // Sort by revenue (highest first) - default
            $categories = $categories->sortByDesc('revenue')->values();
        }
        
        // Get overall stats
        $totalCategories = $categories->count();
        $totalProducts = Product::count();
        $categoriesWithRevenue = $categories->where('revenue', '>', 0)->count();
        
        return view('categories.index', compact(
            'categories', 
            'search', 
            'showEmpty',
            'period',
            'sortBy',
            'totalCategories', 
            'totalProducts',
            'totalRevenue',
            'categoriesWithRevenue',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Display the specified category dashboard.
     */
    public function show(Category $category)
    {
        // Get basic category statistics
        $totalProducts = $category->products()->count();
        $visibleProducts = $this->getVisibleProductCount($category->ID);
        
        // Get featured products (recently added to till)
        $featuredProducts = $this->getFeaturedCategoryProducts($category->ID);
        
        // Get latest products (most recently added)
        $latestProducts = $category->products()
            ->orderByRaw('LENGTH(ID) DESC, ID DESC')  // Assuming newer products have longer/higher IDs
            ->limit(10)
            ->get();
        
        // Get top 10 sellers for last 30 days
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();
        $topSellers = $this->optimizedSalesRepository->getTopCategoryProducts(
            [$category->ID], 
            $startDate, 
            $endDate, 
            10
        );
        
        // Get subcategories if any
        $subcategories = $category->children()->withCount('products')->get();
        
        return view('categories.show', compact(
            'category', 
            'totalProducts', 
            'visibleProducts', 
            'featuredProducts',
            'latestProducts',
            'topSellers',
            'subcategories'
        ));
    }

    /**
     * Display category products list with till visibility management.
     */
    public function products(Request $request, Category $category)
    {
        $search = $request->get('search', '');
        $availability = $request->get('availability', 'all');
        
        $query = $category->products()->with(['category']);
        
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('NAME', 'like', "%{$search}%")
                    ->orWhere('CODE', 'like', "%{$search}%");
            });
        }
        
        // Apply availability filter
        if ($availability === 'available') {
            $visibleIds = ProductsCat::pluck('PRODUCT')->toArray();
            $query->whereIn('ID', $visibleIds);
        } elseif ($availability === 'unavailable') {
            $visibleIds = ProductsCat::pluck('PRODUCT')->toArray();
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
                'products' => $products->map(function ($product) {
                    $product->is_available = $product->is_visible;
                    $product->current_price = $product->PRICESELL * (1 + $product->getVatRate());
                    return $product;
                }),
            ]);
        }
        
        return view('categories.products', compact('category', 'products', 'search', 'availability'));
    }

    /**
     * Display category sales analytics dashboard.
     */
    public function sales(Category $category)
    {
        // Set smart date defaults - last 30 days
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(29);
        
        return view('categories.sales', compact('category', 'startDate', 'endDate'));
    }

    /**
     * Get category sales data for AJAX requests.
     */
    public function getSalesData(Request $request, Category $category)
    {
        $startDate = $request->get('start_date')
            ? Carbon::parse($request->get('start_date'))->startOfDay()
            : Carbon::now()->subDays(29)->startOfDay();
        
        $endDate = $request->get('end_date')
            ? Carbon::parse($request->get('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();
        
        try {
            // Get all category IDs (including subcategories if needed)
            $categoryIds = $this->getCategoryIdsWithChildren($category);
            
            // Get all products with sales data for this category
            $sales = $this->optimizedSalesRepository->getAllCategoryProductsSales($categoryIds, $startDate, $endDate);
            $stats = $this->optimizedSalesRepository->getCategorySalesStats($categoryIds, $startDate, $endDate);
            $dailySales = $this->optimizedSalesRepository->getCategoryDailySales($categoryIds, $startDate, $endDate);
            
            // Add category names for frontend compatibility
            $sales = $sales->map(function ($product) use ($category) {
                $product->category_name = $category->NAME;
                $product->category = $product->category_id;
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
                    'data_source' => 'optimized_pre_aggregated',
                ],
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error loading category sales data', [
                'category_id' => $category->ID,
                'error' => $e->getMessage(),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]);
            
            return response()->json([
                'error' => 'Failed to load sales data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle product visibility on till.
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
            'category'
        );
        
        return response()->json(['success' => $success]);
    }

    /**
     * Serve product image from POS database.
     */
    public function productImage($code)
    {
        $product = Product::where('CODE', $code)->first();
        if (!$product || !$product->IMAGE) {
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
     * Get daily sales breakdown for a specific product.
     */
    public function getProductDailySales(Request $request, Category $category, string $code)
    {
        $product = Product::where('CODE', $code)
            ->where('CATEGORY', $category->ID)
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
            \Log::error('Error getting product daily sales', [
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
     * Get count of products visible on till for a category.
     */
    private function getVisibleProductCount(string $categoryId): int
    {
        return Product::where('CATEGORY', $categoryId)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('PRODUCTS_CAT')
                    ->whereColumn('PRODUCTS_CAT.PRODUCT', 'PRODUCTS.ID');
            })
            ->count();
    }

    /**
     * Get featured products for category dashboard.
     */
    private function getFeaturedCategoryProducts(string $categoryId)
    {
        return Product::where('CATEGORY', $categoryId)
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

    /**
     * Get category IDs including all children.
     */
    private function getCategoryIdsWithChildren(Category $category): array
    {
        $ids = [$category->ID];
        
        // Add all descendant category IDs
        foreach ($category->descendants() as $child) {
            $ids[] = $child->ID;
        }
        
        return $ids;
    }
}