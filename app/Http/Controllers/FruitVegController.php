<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Country;
use App\Models\Product;
use App\Models\VegClass;
use App\Models\VegDetails;
use App\Models\VegPrintQueue;
use App\Models\VegUnit;
use App\Repositories\SalesRepository;
use App\Services\TillVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FruitVegController extends Controller
{
    /**
     * The sales repository instance.
     */
    protected SalesRepository $salesRepository;

    /**
     * The till visibility service instance.
     */
    protected TillVisibilityService $tillVisibilityService;

    /**
     * Create a new controller instance.
     */
    public function __construct(SalesRepository $salesRepository, TillVisibilityService $tillVisibilityService)
    {
        $this->salesRepository = $salesRepository;
        $this->tillVisibilityService = $tillVisibilityService;
    }

    /**
     * Display the main F&V dashboard.
     */
    public function index()
    {
        // Get F&V categories
        $fruitCategory = Category::where('ID', 'SUB1')->first();
        $vegCategories = Category::whereIn('ID', ['SUB2', 'SUB3'])->pluck('ID');

        // Get statistics using the new service
        $stats = $this->tillVisibilityService->getCategoryStats('fruit_veg');
        $stats['needs_labels'] = VegPrintQueue::count();
        $stats['recent_price_changes'] = DB::table('veg_price_history')
            ->where('changed_at', '>=', now()->subDays(7))
            ->count();

        // Get recent price changes
        $recentPriceChanges = DB::table('veg_price_history')
            ->orderBy('changed_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($change) {
                $product = Product::where('CODE', $change->product_code)->first();
                $change->product_name = $product ? $product->NAME : 'Unknown Product';

                return $change;
            });

        // Get recently added products (last 7 days)
        $recentlyAdded = $this->tillVisibilityService->getRecentlyAddedProducts('fruit_veg', 7, 10);

        return view('fruit-veg.index', compact('stats', 'recentPriceChanges', 'recentlyAdded'));
    }

    /**
     * Display availability management page.
     */
    public function availability(Request $request)
    {
        // Get products with till visibility status
        $filters = [
            'search' => $request->search,
            'category' => $request->category,
            'visibility' => $request->availability, // Map old parameter name
        ];

        $products = $this->tillVisibilityService->getProductsWithVisibility('fruit_veg', $filters);

        // For AJAX requests, handle pagination differently
        if ($request->ajax()) {
            $perPage = $request->get('per_page', 50);
            $paginatedProducts = $products->take($perPage);

            return response()->json([
                'products' => $paginatedProducts->values(),
                'hasMore' => $products->count() > $perPage,
            ]);
        }

        // For regular requests, just return the first batch
        $products = $products->take(50);

        return view('fruit-veg.availability', compact('products'));
    }

    /**
     * Toggle product availability.
     */
    public function toggleAvailability(Request $request)
    {
        $request->validate([
            'product_code' => 'required|string',
            'is_available' => 'required|boolean',
        ]);

        $product = Product::where('CODE', $request->product_code)->firstOrFail();

        // Update till visibility
        $this->tillVisibilityService->setVisibility($product->ID, $request->is_available, 'fruit_veg');

        // If making visible on till, add to print queue
        if ($request->is_available) {
            VegPrintQueue::addToQueue($request->product_code, 'marked_available');
        }

        return response()->json(['success' => true]);
    }

    /**
     * Bulk update availability.
     */
    public function bulkAvailability(Request $request)
    {
        $request->validate([
            'product_codes' => 'required|array',
            'is_available' => 'required|boolean',
        ]);

        // Get product IDs from codes
        $products = Product::whereIn('CODE', $request->product_codes)
            ->pluck('ID', 'CODE');

        // Update till visibility in bulk
        $this->tillVisibilityService->bulkSetVisibility(
            $products->values()->toArray(),
            $request->is_available
        );

        // If making visible, add to print queue
        if ($request->is_available) {
            foreach ($request->product_codes as $code) {
                if (isset($products[$code])) {
                    VegPrintQueue::addToQueue($code, 'marked_available');
                }
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Display price management page.
     */
    public function prices()
    {
        // Get visible F&V products from till
        $visibleProducts = $this->tillVisibilityService->getVisibleProducts('fruit_veg');

        // Get products with their details
        $productIds = $visibleProducts->pluck('product.ID');
        $products = Product::whereIn('ID', $productIds)
            ->with(['category', 'vegDetails.country', 'vegDetails.vegUnit', 'vegDetails.vegClass'])
            ->orderBy('CATEGORY')
            ->orderBy('NAME')
            ->get()
            ->map(function ($product) {
                $product->current_price = $product->getGrossPrice();

                return $product;
            });

        return view('fruit-veg.prices', compact('products'));
    }

    /**
     * Update product price.
     */
    public function updatePrice(Request $request)
    {
        $request->validate([
            'product_code' => 'required|string',
            'new_price' => 'required|numeric|min:0',
        ]);

        $product = Product::where('CODE', $request->product_code)->firstOrFail();

        // For the dedicated prices page, only allow updates to visible products
        // For the manage page, allow updates to all products
        $referer = $request->headers->get('referer', '');
        $isFromPricesPage = str_contains($referer, '/fruit-veg/prices');

        if ($isFromPricesPage && ! $this->tillVisibilityService->isVisibleOnTill($product->ID)) {
            return response()->json(['error' => 'Product not visible on till'], 422);
        }

        // Get current price from veg_price_history or product
        $lastPriceRecord = DB::table('veg_price_history')
            ->where('product_code', $request->product_code)
            ->orderBy('changed_at', 'desc')
            ->first();

        $oldPrice = $lastPriceRecord ? $lastPriceRecord->new_price : $product->getGrossPrice();
        $newPrice = $request->new_price;

        // Only proceed if price actually changed
        if ($oldPrice != $newPrice) {
            // Log price change
            DB::table('veg_price_history')->insert([
                'product_code' => $request->product_code,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'changed_by' => Auth::id(),
                'changed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Add to print queue
            VegPrintQueue::addToQueue($request->product_code, 'price_change');
        }

        return response()->json(['success' => true]);
    }

    /**
     * Display combined availability and price management page.
     */
    public function manage(Request $request)
    {
        // Get products with till visibility status (original behavior - search within filters)
        $filters = [
            'search' => $request->search,
            'category' => $request->category,
            'visibility' => $request->availability === 'available' ? 'visible' :
                          ($request->availability === 'unavailable' ? 'hidden' : 'all'),
        ];

        // Pagination parameters
        $limit = $request->get('limit', 50); // Default 50 products per page
        $offset = $request->get('offset', 0);

        $products = $this->tillVisibilityService->getProductsWithVisibility('fruit_veg', $filters, $limit, $offset);

        // Batch load all price records to avoid N+1 queries
        $productCodes = $products->pluck('CODE')->toArray();
        $priceRecords = DB::table('veg_price_history')
            ->whereIn('product_code', $productCodes)
            ->select('product_code', 'new_price', 'changed_at')
            ->get()
            ->groupBy('product_code')
            ->map(function ($records) {
                return $records->sortByDesc('changed_at')->first();
            });

        // Add current prices from batch-loaded data
        $products->each(function ($product) use ($priceRecords) {
            $priceRecord = $priceRecords->get($product->CODE);
            $product->current_price = $priceRecord ? $priceRecord->new_price : $product->getGrossPrice();
            $product->is_available = $product->is_visible_on_till; // Maintain compatibility
        });

        // For AJAX requests, return JSON
        if ($request->wantsJson()) {
            // Make sure relationships are loaded for AJAX responses too
            $products->load('vegDetails.country', 'vegDetails.vegUnit', 'vegDetails.vegClass');

            // Check if there are more products by trying to get one more
            $hasMore = $this->tillVisibilityService->getProductsWithVisibility('fruit_veg', $filters, 1, $offset + $limit)->count() > 0;

            return response()->json([
                'products' => $products->values(),
                'hasMore' => $hasMore,
            ]);
        }

        // Make sure relationships are loaded for the view
        $products->load('vegDetails.country', 'vegDetails.vegUnit', 'vegDetails.vegClass');

        return view('fruit-veg.manage', compact('products'));
    }

    /**
     * Display label printing page.
     */
    public function labels()
    {
        // Get products needing labels
        $printQueue = VegPrintQueue::getQueuedProductCodes();

        $productsNeedingLabels = Product::whereIn('CODE', $printQueue)
            ->with(['category', 'vegDetails.country', 'vegDetails.vegUnit', 'vegDetails.vegClass'])
            ->get()
            ->map(function ($product) {
                // Get current price from price history or product
                $lastPriceRecord = DB::table('veg_price_history')
                    ->where('product_code', $product->CODE)
                    ->orderBy('changed_at', 'desc')
                    ->first();

                $product->current_price = $lastPriceRecord ? $lastPriceRecord->new_price : $product->getGrossPrice();

                return $product;
            });

        return view('fruit-veg.labels', compact('productsNeedingLabels'));
    }

    /**
     * Preview F&V labels.
     */
    public function previewLabels(Request $request)
    {
        $productCodes = $request->input('products', []);

        if (empty($productCodes)) {
            // Get all products needing labels
            $productCodes = VegPrintQueue::getQueuedProductCodes();
        }

        $products = Product::whereIn('CODE', $productCodes)
            ->with(['category', 'vegDetails.country', 'vegDetails.vegUnit', 'vegDetails.vegClass'])
            ->get()
            ->map(function ($product) {
                // Get current price from price history or product
                $lastPriceRecord = DB::table('veg_price_history')
                    ->where('product_code', $product->CODE)
                    ->orderBy('changed_at', 'desc')
                    ->first();

                $product->current_price = $lastPriceRecord ? $lastPriceRecord->new_price : $product->getGrossPrice();

                return $product;
            });

        return view('fruit-veg.label-preview', compact('products'));
    }

    /**
     * Mark labels as printed.
     */
    public function markLabelsPrinted(Request $request)
    {
        $productCodes = $request->input('products', []);

        if (empty($productCodes)) {
            // Clear all from print queue
            VegPrintQueue::clearQueue();
        } else {
            // Clear specific products from print queue
            VegPrintQueue::removeMultipleFromQueue($productCodes);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Clear all labels from print queue.
     */
    public function clearAllLabels()
    {
        VegPrintQueue::clearQueue();

        return redirect()->route('fruit-veg.labels')->with('success', 'All labels cleared from print queue.');
    }

    /**
     * Remove a single product from the labels print queue.
     */
    public function removeFromLabels(Request $request)
    {
        $productCode = $request->input('product_code');

        if (! $productCode) {
            return response()->json(['success' => false, 'message' => 'Product code is required.']);
        }

        $removed = VegPrintQueue::removeFromQueue($productCode);

        if ($removed) {
            return response()->json(['success' => true, 'message' => 'Product removed from print queue.']);
        } else {
            return response()->json(['success' => false, 'message' => 'Product not found in print queue.']);
        }
    }

    /**
     * Add a product to the labels print queue.
     */
    public function addToLabels(Request $request)
    {
        $productCode = $request->input('product_code');

        if (! $productCode) {
            return response()->json(['success' => false, 'message' => 'Product code is required.']);
        }

        // Verify product exists
        $product = Product::where('CODE', $productCode)->first();
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found.']);
        }

        VegPrintQueue::addToQueue($productCode, 'manual_add');

        return response()->json(['success' => true, 'message' => 'Product added to print queue.']);
    }

    /**
     * Update product display field.
     */
    public function updateDisplay(Request $request)
    {
        $request->validate([
            'product_code' => 'required|string',
            'display' => 'nullable|string|max:255',
        ]);

        $product = Product::where('CODE', $request->product_code)->firstOrFail();
        $product->update(['DISPLAY' => $request->display]);

        // Add to print queue since display info changed
        VegPrintQueue::addToQueue($request->product_code, 'display_updated');

        return response()->json(['success' => true]);
    }

    /**
     * Update product country of origin.
     */
    public function updateCountry(Request $request)
    {
        $request->validate([
            'product_code' => 'required|string',
            'country_id' => 'required|integer|exists:App\Models\Country,id',
        ]);

        // Update or create vegDetails record
        VegDetails::updateOrCreate(
            ['product_code' => $request->product_code],
            ['country_id' => $request->country_id]
        );

        // Add to print queue since origin changed
        VegPrintQueue::addToQueue($request->product_code, 'country_updated');

        return response()->json(['success' => true]);
    }

    /**
     * Update product unit.
     */
    public function updateUnit(Request $request)
    {
        $request->validate([
            'product_code' => 'required|string',
            'unit_id' => 'required|integer|exists:App\Models\VegUnit,id',
        ]);

        // Update or create vegDetails record
        VegDetails::updateOrCreate(
            ['product_code' => $request->product_code],
            ['unit_id' => $request->unit_id]
        );

        // Add to print queue since unit changed
        VegPrintQueue::addToQueue($request->product_code, 'unit_updated');

        return response()->json(['success' => true]);
    }

    /**
     * Update product class.
     */
    public function updateClass(Request $request)
    {
        $request->validate([
            'product_code' => 'required|string',
            'class_id' => 'required|integer|exists:App\Models\VegClass,id',
        ]);

        // Update or create vegDetails record
        VegDetails::updateOrCreate(
            ['product_code' => $request->product_code],
            ['class_id' => $request->class_id]
        );

        // Add to print queue since class changed
        VegPrintQueue::addToQueue($request->product_code, 'class_updated');

        return response()->json(['success' => true]);
    }

    /**
     * Get all countries for dropdown.
     */
    public function getCountries()
    {
        $countries = Country::orderBy('name')->get();

        return response()->json($countries);
    }

    /**
     * Get all units for dropdown.
     */
    public function getUnits()
    {
        $units = VegUnit::orderBy('sort_order')->get();

        return response()->json($units);
    }

    /**
     * Get all classes for dropdown.
     */
    public function getClasses()
    {
        $classes = VegClass::orderBy('classNum')->get();

        return response()->json($classes);
    }

    /**
     * Search products for AJAX requests.
     */
    public function searchProducts(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|min:2|max:100',
            'category' => 'nullable|string|in:all,fruit,vegetables,veg_barcoded',
            'availability' => 'nullable|string|in:all,available,unavailable',
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        // Use different approach based on whether we're searching or just filtering
        if (! empty($request->search)) {
            // When searching: search across ALL fruit-veg products, ignoring availability filter
            // Only apply category filter to search results (not availability)
            $displayFilters = [
                'category' => $request->category,
                'visibility' => 'all', // Always show all availability states when searching
            ];

            $products = $this->tillVisibilityService->searchAllProductsWithVisibility(
                'fruit_veg',
                $request->search,
                $displayFilters
            );
        } else {
            // When not searching: use existing filtering behavior
            $filters = [
                'search' => null,
                'category' => $request->category,
                'visibility' => $request->availability === 'available' ? 'visible' :
                              ($request->availability === 'unavailable' ? 'hidden' : 'all'),
            ];

            $products = $this->tillVisibilityService->getProductsWithVisibility('fruit_veg', $filters);
        }

        // Load vegDetails relationships for all products
        $productCodes = $products->pluck('CODE');
        $vegDetailsCollection = VegDetails::whereIn('product_code', $productCodes)
            ->with(['country', 'vegUnit', 'vegClass'])
            ->get()
            ->keyBy('product_code');

        // Attach vegDetails to each product
        $products = $products->map(function ($product) use ($vegDetailsCollection) {
            $product->veg_details = $vegDetailsCollection->get($product->CODE);

            return $product;
        });

        // Apply pagination
        $offset = $request->get('offset', 0);
        $limit = $request->get('limit', 50);

        // Get print queue status for all products
        $printQueueCodes = VegPrintQueue::getQueuedProductCodes();

        $products = $products->slice($offset, $limit)
            ->map(function ($product) use ($printQueueCodes) {
                // Maintain compatibility with old field name
                $product->is_available = $product->is_visible_on_till;

                // Get current price from price history or product
                $lastPriceRecord = DB::table('veg_price_history')
                    ->where('product_code', $product->CODE)
                    ->orderBy('changed_at', 'desc')
                    ->first();

                $product->current_price = $lastPriceRecord ? $lastPriceRecord->new_price : $product->getGrossPrice();

                // Add print queue status
                $product->in_print_queue = in_array($product->CODE, $printQueueCodes);

                return $product;
            });

        // Apply availability filter after loading from DB (since it's in a separate table)
        // Only apply this filter when NOT searching (search should ignore availability filter)
        if (empty($request->search) && $request->filled('availability') && $request->availability !== 'all') {
            $products = $products->filter(function ($product) use ($request) {
                return $request->availability === 'available' ? $product->is_available : ! $product->is_available;
            });
        }

        return response()->json([
            'products' => $products->values(),
            'hasMore' => $products->count() >= $limit,
            'total' => $products->count(),
        ]);
    }

    /**
     * Quick search across all fruit-veg products for visibility management.
     * This is used by the quick search widget and always searches across all products.
     */
    public function quickSearch(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:2|max:100',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        // Quick search always searches across ALL products, ignoring filters
        $products = $this->tillVisibilityService->searchAllProductsWithVisibility(
            'fruit_veg',
            $request->search,
            ['category' => 'all', 'visibility' => 'all'] // Always search all
        );

        // Limit results for quick search (smaller limit for performance)
        $limit = $request->get('limit', 10);
        $products = $products->take($limit);

        // Load vegDetails relationships
        $productCodes = $products->pluck('CODE');
        $vegDetailsCollection = VegDetails::whereIn('product_code', $productCodes)
            ->with(['country', 'vegUnit', 'vegClass'])
            ->get()
            ->keyBy('product_code');

        // Attach vegDetails to each product
        $products = $products->map(function ($product) use ($vegDetailsCollection) {
            $product->veg_details = $vegDetailsCollection->get($product->CODE);

            // Add current price
            $lastPriceRecord = DB::table('veg_price_history')
                ->where('product_code', $product->CODE)
                ->orderBy('changed_at', 'desc')
                ->first();

            $product->current_price = $lastPriceRecord ? $lastPriceRecord->new_price : $product->getGrossPrice();
            $product->is_available = $product->is_visible_on_till; // Maintain compatibility

            return $product;
        });

        return response()->json([
            'products' => $products->values(),
            'total' => $products->count(),
        ]);
    }

    /**
     * Serve product image.
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

        // Return the image from the database
        return response($product->IMAGE, 200, [
            'Content-Type' => 'image/jpeg', // Assume JPEG for now
            'Cache-Control' => 'public, max-age=86400', // Cache for 24 hours
        ]);
    }

    /**
     * Helper method to get available count for a category.
     */
    private function getAvailableCount($categoryId)
    {
        if (! $categoryId) {
            return 0;
        }

        return $this->tillVisibilityService->getVisibleCountForCategory($categoryId);
    }

    /**
     * Helper method to get available count for multiple categories.
     */
    private function getAvailableCountMultiple($categoryIds)
    {
        $count = 0;
        foreach ($categoryIds as $categoryId) {
            $count += $this->tillVisibilityService->getVisibleCountForCategory($categoryId);
        }

        return $count;
    }

    /**
     * Display product edit page.
     */
    public function editProduct($code)
    {
        $product = Product::where('CODE', $code)->firstOrFail();

        // Get F&V categories to verify this is a F&V product
        $fruitCategory = Category::where('ID', 'SUB1')->first();
        $vegCategories = Category::whereIn('ID', ['SUB2', 'SUB3'])->pluck('ID');

        $validCategories = array_merge(
            [$fruitCategory->ID ?? 0],
            $vegCategories->toArray()
        );

        if (! in_array($product->CATEGORY, $validCategories)) {
            abort(404, 'Product is not a fruit or vegetable.');
        }

        // Load relationships
        $product->load(['category', 'vegDetails.country', 'vegDetails.vegUnit', 'vegDetails.vegClass']);

        // Get till visibility status
        $product->is_visible_on_till = $this->tillVisibilityService->isVisibleOnTill($product->ID);
        $product->is_available = $product->is_visible_on_till; // Maintain compatibility

        // Get current price from price history or product
        $lastPriceRecord = DB::table('veg_price_history')
            ->where('product_code', $code)
            ->orderBy('changed_at', 'desc')
            ->first();

        $product->current_price = $lastPriceRecord ? $lastPriceRecord->new_price : $product->getGrossPrice();

        // Get all countries for dropdown
        $countries = Country::orderBy('name')->get();

        // Get price history for this product
        $priceHistory = DB::table('veg_price_history')
            ->where('product_code', $code)
            ->orderBy('changed_at', 'desc')
            ->limit(10)
            ->get();

        // Get sales data using the repository
        $salesHistory = $this->salesRepository->getProductSalesHistory($product->ID, 4); // Last 4 months
        $salesStats = $this->salesRepository->getProductSalesStatistics($product->ID);

        return view('fruit-veg.product-edit', compact(
            'product',
            'countries',
            'priceHistory',
            'salesHistory',
            'salesStats'
        ));
    }

    /**
     * Get sales data for AJAX requests.
     */
    public function salesData(Request $request, string $code)
    {
        $product = Product::where('CODE', $code)->firstOrFail();

        $period = $request->get('period', '4');

        // Determine the number of months based on period
        $months = match ($period) {
            'ytd' => (int) date('n'), // Current month number
            default => (int) $period
        };

        // Get sales history and statistics
        $salesHistory = $this->salesRepository->getProductSalesHistory($product->ID, $months);
        $salesStats = $this->salesRepository->getProductSalesStatistics($product->ID);

        return response()->json([
            'salesHistory' => array_values($salesHistory),
            'salesStats' => $salesStats,
        ]);
    }

    /**
     * Update product image.
     */
    public function updateProductImage(Request $request, $code)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $product = Product::where('CODE', $code)->firstOrFail();

        if ($request->hasFile('image')) {
            $imageData = file_get_contents($request->file('image')->path());
            $product->update(['IMAGE' => $imageData]);

            // Add to print queue since image changed
            VegPrintQueue::addToQueue($code, 'image_updated');
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get featured available products for the main dashboard.
     */
    private function getFeaturedAvailableProducts()
    {
        // Use the service to get featured visible products
        return $this->tillVisibilityService->getFeaturedVisibleProducts('fruit_veg', 12)
            ->map(function ($product) {
                // Get current price from price history or product
                $lastPriceRecord = DB::table('veg_price_history')
                    ->where('product_code', $product->CODE)
                    ->orderBy('changed_at', 'desc')
                    ->first();

                $product->current_price = $lastPriceRecord ? $lastPriceRecord->new_price : $product->getGrossPrice();
                $product->is_available = true; // Maintain compatibility

                return $product;
            });
    }
}
