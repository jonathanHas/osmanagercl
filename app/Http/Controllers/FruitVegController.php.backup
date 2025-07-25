<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Country;
use App\Models\Product;
use App\Models\VegDetails;
use App\Models\VegPrintQueue;
use App\Repositories\SalesRepository;
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
     * Create a new controller instance.
     */
    public function __construct(SalesRepository $salesRepository)
    {
        $this->salesRepository = $salesRepository;
    }

    /**
     * Display the main F&V dashboard.
     */
    public function index()
    {
        // Get F&V categories
        $fruitCategory = Category::where('ID', 'SUB1')->first();
        $vegCategories = Category::whereIn('ID', ['SUB2', 'SUB3'])->pluck('ID');

        // Get statistics
        $stats = [
            'total_fruits' => Product::where('CATEGORY', $fruitCategory->ID ?? null)->count(),
            'total_vegetables' => Product::whereIn('CATEGORY', $vegCategories)->count(),
            'available_fruits' => $this->getAvailableCount($fruitCategory->ID ?? null),
            'available_vegetables' => $this->getAvailableCountMultiple($vegCategories),
            'needs_labels' => VegPrintQueue::count(),
            'recent_price_changes' => DB::table('veg_price_history')
                ->where('changed_at', '>=', now()->subDays(7))
                ->count(),
        ];

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

        // Get featured available products
        $featuredAvailable = $this->getFeaturedAvailableProducts();

        return view('fruit-veg.index', compact('stats', 'recentPriceChanges', 'featuredAvailable'));
    }

    /**
     * Display availability management page.
     */
    public function availability(Request $request)
    {
        // Get F&V categories
        $fruitCategory = Category::where('ID', 'SUB1')->first();
        $vegCategories = Category::whereIn('ID', ['SUB2', 'SUB3'])->pluck('ID');

        // Build the query
        $query = Product::whereIn('CATEGORY', array_merge(
            [$fruitCategory->ID ?? 0],
            $vegCategories->toArray()
        ))->with(['category', 'vegDetails.country']);

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('NAME', 'like', '%'.$search.'%')
                    ->orWhere('CODE', 'like', '%'.$search.'%')
                    ->orWhere('DISPLAY', 'like', '%'.$search.'%');
            });
        }

        // Apply category filter
        if ($request->filled('category') && $request->category !== 'all') {
            if ($request->category === 'fruit') {
                $query->where('CATEGORY', $fruitCategory->ID ?? 0);
            } elseif ($request->category === 'vegetables') {
                $query->whereIn('CATEGORY', $vegCategories);
            }
        }

        // Get paginated results
        $perPage = $request->get('per_page', 50);
        $products = $query->orderBy('CATEGORY')
            ->orderBy('NAME')
            ->take($perPage)
            ->get()
            ->map(function ($product) {
                // Get availability status from our table
                $availability = DB::table('veg_availability')
                    ->where('product_code', $product->CODE)
                    ->first();

                $product->is_available = $availability->is_available ?? false;
                $product->current_price = $availability->current_price ?? $product->getGrossPrice();

                return $product;
            });

        // For AJAX requests, return JSON
        if ($request->ajax()) {
            return response()->json([
                'products' => $products,
                'hasMore' => $products->count() >= $perPage,
            ]);
        }

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

        DB::table('veg_availability')->updateOrInsert(
            ['product_code' => $request->product_code],
            [
                'is_available' => $request->is_available,
                'current_price' => $product->getGrossPrice(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // If making available, add to print queue
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

        foreach ($request->product_codes as $code) {
            $product = Product::where('CODE', $code)->first();
            if ($product) {
                DB::table('veg_availability')->updateOrInsert(
                    ['product_code' => $code],
                    [
                        'is_available' => $request->is_available,
                        'current_price' => $product->getGrossPrice(),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                // If making available, add to print queue
                if ($request->is_available) {
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
        // Get available F&V products
        $availableProducts = DB::table('veg_availability')
            ->where('is_available', true)
            ->pluck('current_price', 'product_code');

        $products = Product::whereIn('CODE', array_keys($availableProducts->toArray()))
            ->with(['category', 'vegDetails.country'])
            ->orderBy('CATEGORY')
            ->orderBy('NAME')
            ->get()
            ->map(function ($product) use ($availableProducts) {
                $product->current_price = $availableProducts[$product->CODE] ?? $product->getGrossPrice();

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
        $availability = DB::table('veg_availability')
            ->where('product_code', $request->product_code)
            ->first();

        if (! $availability) {
            return response()->json(['error' => 'Product not in availability list'], 422);
        }

        $oldPrice = $availability->current_price;
        $newPrice = $request->new_price;

        // Only proceed if price actually changed
        if ($oldPrice != $newPrice) {
            // Update availability table
            DB::table('veg_availability')
                ->where('product_code', $request->product_code)
                ->update([
                    'current_price' => $newPrice,
                    'updated_at' => now(),
                ]);

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
     * Display label printing page.
     */
    public function labels()
    {
        // Get products needing labels
        $printQueue = VegPrintQueue::getQueuedProductCodes();
        $availabilityData = DB::table('veg_availability')
            ->whereIn('product_code', $printQueue)
            ->pluck('current_price', 'product_code');

        $productsNeedingLabels = Product::whereIn('CODE', $printQueue)
            ->with(['category', 'vegDetails.country'])
            ->get()
            ->map(function ($product) use ($availabilityData) {
                $product->current_price = $availabilityData[$product->CODE] ?? $product->getGrossPrice();

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

        $availabilityData = DB::table('veg_availability')
            ->whereIn('product_code', $productCodes)
            ->pluck('current_price', 'product_code');

        $products = Product::whereIn('CODE', $productCodes)
            ->with(['category', 'vegDetails.country'])
            ->get()
            ->map(function ($product) use ($availabilityData) {
                $product->current_price = $availabilityData[$product->CODE] ?? $product->getGrossPrice();

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
            'country_id' => 'required|integer|exists:App\Models\Country,ID',
        ]);

        // Update or create vegDetails record
        VegDetails::updateOrCreate(
            ['product' => $request->product_code],
            ['countryCode' => $request->country_id]
        );

        // Add to print queue since origin changed
        VegPrintQueue::addToQueue($request->product_code, 'country_updated');

        return response()->json(['success' => true]);
    }

    /**
     * Get all countries for dropdown.
     */
    public function getCountries()
    {
        $countries = Country::orderBy('country')->get();

        return response()->json($countries);
    }

    /**
     * Search products for AJAX requests.
     */
    public function searchProducts(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:2|max:100',
            'category' => 'nullable|string|in:all,fruit,vegetables',
            'availability' => 'nullable|string|in:all,available,unavailable',
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        // Get F&V categories
        $fruitCategory = Category::where('ID', 'SUB1')->first();
        $vegCategories = Category::whereIn('ID', ['SUB2', 'SUB3'])->pluck('ID');

        $query = Product::whereIn('CATEGORY', array_merge(
            [$fruitCategory->ID ?? 0],
            $vegCategories->toArray()
        ))->with(['category', 'vegDetails.country']);

        // Apply search
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('NAME', 'like', '%'.$search.'%')
                ->orWhere('CODE', 'like', '%'.$search.'%')
                ->orWhere('DISPLAY', 'like', '%'.$search.'%');
        });

        // Apply category filter
        if ($request->filled('category') && $request->category !== 'all') {
            if ($request->category === 'fruit') {
                $query->where('CATEGORY', $fruitCategory->ID ?? 0);
            } elseif ($request->category === 'vegetables') {
                $query->whereIn('CATEGORY', $vegCategories);
            }
        }

        // Get results with pagination
        $offset = $request->get('offset', 0);
        $limit = $request->get('limit', 50);

        $products = $query->orderBy('CATEGORY')
            ->orderBy('NAME')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($product) {
                $availability = DB::table('veg_availability')
                    ->where('product_code', $product->CODE)
                    ->first();

                $product->is_available = $availability->is_available ?? false;
                $product->current_price = $availability->current_price ?? $product->getGrossPrice();

                return $product;
            });

        // Apply availability filter after loading from DB (since it's in a separate table)
        if ($request->filled('availability') && $request->availability !== 'all') {
            $products = $products->filter(function ($product) use ($request) {
                return $request->availability === 'available' ? $product->is_available : ! $product->is_available;
            });
        }

        return response()->json([
            'products' => $products->values(),
            'hasMore' => $products->count() >= $limit,
            'total' => $query->count(),
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

        $productCodes = Product::where('CATEGORY', $categoryId)->pluck('CODE');

        return DB::table('veg_availability')
            ->whereIn('product_code', $productCodes)
            ->where('is_available', true)
            ->count();
    }

    /**
     * Helper method to get available count for multiple categories.
     */
    private function getAvailableCountMultiple($categoryIds)
    {
        $productCodes = Product::whereIn('CATEGORY', $categoryIds)->pluck('CODE');

        return DB::table('veg_availability')
            ->whereIn('product_code', $productCodes)
            ->where('is_available', true)
            ->count();
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
        
        if (!in_array($product->CATEGORY, $validCategories)) {
            abort(404, 'Product is not a fruit or vegetable.');
        }

        // Load relationships
        $product->load(['category', 'vegDetails.country']);

        // Get availability data
        $availability = DB::table('veg_availability')
            ->where('product_code', $code)
            ->first();

        $product->is_available = $availability->is_available ?? false;
        $product->current_price = $availability->current_price ?? $product->getGrossPrice();

        // Get all countries for dropdown
        $countries = Country::orderBy('country')->get();

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
        // Get F&V categories
        $fruitCategory = Category::where('ID', 'SUB1')->first();
        $vegCategories = Category::whereIn('ID', ['SUB2', 'SUB3'])->pluck('ID');

        // Get available product codes
        $availableProductCodes = DB::table('veg_availability')
            ->where('is_available', true)
            ->pluck('product_code');

        // Get a selection of available products with full details
        return Product::whereIn('CODE', $availableProductCodes)
            ->whereIn('CATEGORY', array_merge(
                [$fruitCategory->ID ?? 0],
                $vegCategories->toArray()
            ))
            ->with(['category', 'vegDetails.country'])
            ->orderBy('NAME')
            ->limit(12)
            ->get()
            ->map(function ($product) {
                // Get current price from availability table
                $availability = DB::table('veg_availability')
                    ->where('product_code', $product->CODE)
                    ->first();

                $product->current_price = $availability->current_price ?? $product->getGrossPrice();
                $product->is_available = true; // We know these are available

                return $product;
            });
    }
}
