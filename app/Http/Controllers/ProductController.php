<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateBarcodeRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\LabelLog;
use App\Models\LabelTemplate;
use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Models\Product;
use App\Models\ProductMetadata;
use App\Models\ProductOrderSetting;
use App\Models\Stocking;
use App\Models\Supplier;
use App\Models\SupplierLink;
use App\Models\Tax;
use App\Models\TaxCategory;
use App\Models\VegDetails;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SalesRepository;
use App\Services\LabelService;
use App\Services\OrderService;
use App\Services\SupplierService;
use App\Services\TillVisibilityService;
use App\Services\UdeaScrapingService;
use App\Support\SpecialOrderCategories;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

class ProductController extends Controller
{
    /**
     * The product repository instance.
     */
    protected ProductRepository $productRepository;

    /**
     * The category repository instance.
     */
    protected CategoryRepository $categoryRepository;

    /**
     * The sales repository instance.
     */
    protected SalesRepository $salesRepository;

    /**
     * The supplier service instance.
     */
    protected SupplierService $supplierService;

    /**
     * The Udea scraping service instance.
     */
    protected UdeaScrapingService $udeaScrapingService;

    /**
     * The label service instance.
     */
    protected LabelService $labelService;

    /**
     * The till visibility service instance.
     */
    protected TillVisibilityService $tillVisibilityService;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        SalesRepository $salesRepository,
        SupplierService $supplierService,
        UdeaScrapingService $udeaScrapingService,
        LabelService $labelService,
        TillVisibilityService $tillVisibilityService
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->salesRepository = $salesRepository;
        $this->supplierService = $supplierService;
        $this->udeaScrapingService = $udeaScrapingService;
        $this->labelService = $labelService;
        $this->tillVisibilityService = $tillVisibilityService;
    }

    /**
     * Display a listing of products.
     */
    public function index(Request $request): View
    {
        $search = $request->get('search');
        $activeOnly = $request->boolean('active_only');
        $stockedOnly = $request->boolean('stocked_only');
        $inStockOnly = $request->boolean('in_stock_only');
        $showStats = $request->boolean('show_stats');
        $supplierId = $request->get('supplier_id');
        $categoryId = $request->get('category_id');
        $showSuppliers = $request->boolean('show_suppliers');
        $perPage = $request->get('per_page', 20);

        // Get suppliers for dropdown (always load for immediate availability when checkbox is toggled)
        $suppliers = $this->productRepository->getAllSuppliersWithProducts(
            stockedOnly: $stockedOnly,
            inStockOnly: $inStockOnly,
            activeOnly: $activeOnly
        );

        // Get categories for dropdown - shows ALL categories regardless of till visibility
        // CATSHOWNAME controls POS till display, not product management
        $categories = $this->productRepository->getAllCategoriesWithProducts(
            activeOnly: $activeOnly,
            stockedOnly: $stockedOnly,
            inStockOnly: $inStockOnly
        );

        if ($search || $activeOnly || $stockedOnly || $inStockOnly || $supplierId || $categoryId) {
            $products = $this->productRepository->searchProducts(
                search: $search,
                activeOnly: $activeOnly,
                stockedOnly: $stockedOnly,
                inStockOnly: $inStockOnly,
                categoryId: $categoryId,
                supplierId: $supplierId,
                perPage: $perPage,
                withSuppliers: $showSuppliers
            );
        } else {
            $products = $this->productRepository->getAllProducts($perPage, $showSuppliers);
        }

        // Only calculate statistics when requested
        $statistics = $showStats ? $this->productRepository->getStatistics() : null;

        return view('products.index', [
            'products' => $products,
            'statistics' => $statistics,
            'search' => $search,
            'activeOnly' => $activeOnly,
            'stockedOnly' => $stockedOnly,
            'inStockOnly' => $inStockOnly,
            'showStats' => $showStats,
            'supplierId' => $supplierId,
            'categoryId' => $categoryId,
            'showSuppliers' => $showSuppliers,
            'suppliers' => $suppliers,
            'categories' => $categories,
            'supplierService' => $this->supplierService,
        ]);
    }

    /**
     * Display the specified product.
     */
    public function show(string $id, Request $request): View
    {
        $product = $this->productRepository->findById($id);

        if (! $product) {
            abort(404, 'Product not found');
        }

        // Check for delivery context
        $fromDelivery = $request->query('from_delivery');

        // Check for referrer context
        $from = $request->query('from');

        $taxCategories = $this->productRepository->getAllTaxCategories();

        // Load sales data for the product
        $salesHistory = $this->salesRepository->getProductSalesHistory($id, 4); // Last 4 months
        $salesStats = $this->salesRepository->getProductSalesStatistics($id);

        // Fetch Udea pricing if product has supplier code and is Udea supplier
        $udeaPricing = null;
        if ($product->supplierLink?->SupplierCode &&
            $product->supplier &&
            $this->supplierService->hasExternalIntegration($product->supplier->SupplierID)) {

            try {
                $udeaPricing = $this->udeaScrapingService->getProductData($product->supplierLink->SupplierCode);
            } catch (\Exception $e) {
                // Log error but don't break the page
                \Log::warning('Failed to fetch Udea pricing for product', [
                    'product_id' => $id,
                    'supplier_code' => $product->supplierLink->SupplierCode,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Check if product is visible on till
        $isVisibleOnTill = $this->tillVisibilityService->isVisibleOnTill($id);

        // Get all categories for the category selector
        $allCategories = $this->getAllCategoriesForDropdown();

        // Get order settings for this product (if any)
        $orderSettings = ProductOrderSetting::where('product_id', $id)->first();

        return view('products.show', [
            'product' => $product,
            'taxCategories' => $taxCategories,
            'salesHistory' => $salesHistory,
            'salesStats' => $salesStats,
            'supplierService' => $this->supplierService,
            'udeaPricing' => $udeaPricing,
            'fromDelivery' => $fromDelivery,
            'from' => $from,
            'isVisibleOnTill' => $isVisibleOnTill,
            'allCategories' => $allCategories,
            'orderSettings' => $orderSettings,
        ]);
    }

    /**
     * Update the name for a product.
     */
    public function updateName(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'product_name' => 'required|string|max:255',
        ]);

        $product = $this->productRepository->findById($id);

        if (! $product) {
            abort(404, 'Product not found');
        }

        // Update the product's name in the POS database
        $product->update([
            'NAME' => trim($request->product_name),
        ]);

        return redirect()
            ->route('products.show', $id)
            ->with('success', 'Product name updated successfully.');
    }

    /**
     * Update the tax category for a product.
     */
    public function updateTax(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'tax_category' => 'required|string|exists:pos.TAXCATEGORIES,ID',
        ]);

        $product = $this->productRepository->findById($id);

        if (! $product) {
            abort(404, 'Product not found');
        }

        // Update the product's tax category in the POS database
        $product->update([
            'TAXCAT' => $request->tax_category,
        ]);

        return redirect()
            ->route('products.show', $id)
            ->with('success', 'Tax category updated successfully.');
    }

    /**
     * Get sales data for AJAX requests.
     */
    public function salesData(Request $request, string $id)
    {
        $product = $this->productRepository->findById($id);

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $period = $request->get('period', '4');

        // Determine the number of months based on period
        $months = match ($period) {
            'ytd' => (int) date('n'), // Current month number
            default => (int) $period
        };

        // Get sales history and statistics
        $salesHistory = $this->salesRepository->getProductSalesHistory($id, $months);
        $salesStats = $this->salesRepository->getProductSalesStatistics($id);

        return response()->json([
            'salesHistory' => array_values($salesHistory),
            'salesStats' => $salesStats,
        ]);
    }

    /**
     * Get weekly sales data for a product (used by sales chart popup).
     */
    public function weeklySalesData(Request $request, string $id)
    {
        $product = $this->productRepository->findById($id);

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $weeks = (int) $request->get('weeks', 8);
        $weeks = max(4, min(104, $weeks)); // Clamp between 4 and 104 weeks (2 years)

        $weeklySales = $this->salesRepository->getProductWeeklySales($id, $weeks, forceLiveData: true);

        // Get coffee and kitchen customer sales in ONE query
        $internalSales = $this->salesRepository->getBulkInternalCustomerWeeklySales([$id], $weeks);
        $coffeeWeeklySales = $internalSales['coffee'][$id] ?? [];
        $kitchenWeeklySales = $internalSales['kitchen'][$id] ?? [];

        // Calculate statistics
        $salesValues = array_map(fn ($w) => (float) ($w['units'] ?? 0), $weeklySales);
        $nonZeroSales = array_filter($salesValues, fn ($v) => $v > 0);
        $totalSales = array_sum($salesValues);
        $peakSales = ! empty($salesValues) ? max($salesValues) : 0;
        $avgSales = count($nonZeroSales) > 0 ? $totalSales / count($nonZeroSales) : 0;

        // Calculate coffee and kitchen statistics
        $coffeeValues = array_map(fn ($w) => (float) ($w['units'] ?? 0), $coffeeWeeklySales);
        $totalCoffeeSales = array_sum($coffeeValues);
        $kitchenValues = array_map(fn ($w) => (float) ($w['units'] ?? 0), $kitchenWeeklySales);
        $totalKitchenSales = array_sum($kitchenValues);

        return response()->json([
            'weeks' => $weeks,
            'weeklySales' => $weeklySales,
            'coffeeWeeklySales' => $coffeeWeeklySales,
            'kitchenWeeklySales' => $kitchenWeeklySales,
            'productName' => $product->NAME,
            'stats' => [
                'total' => round($totalSales, 1),
                'peak' => round($peakSales, 1),
                'average' => round($avgSales, 1),
                'weeksWithSales' => count($nonZeroSales),
                'totalCoffeeSales' => round($totalCoffeeSales, 1),
                'totalKitchenSales' => round($totalKitchenSales, 1),
            ],
        ]);
    }

    /**
     * Get daily sales data for a product within a specific week (used by sales chart popup drill-down).
     */
    public function dailySalesData(Request $request, string $id)
    {
        $product = $this->productRepository->findById($id);

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $weekStart = $request->get('week_start');
        if (! $weekStart) {
            return response()->json(['error' => 'week_start parameter is required'], 400);
        }

        // Validate date format
        try {
            $startDate = \Carbon\Carbon::parse($weekStart)->startOfWeek();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid week_start date format'], 400);
        }

        $endDate = $startDate->copy()->endOfWeek();

        $dailySales = $this->salesRepository->getProductDailySales($id, $weekStart, forceLiveData: true);

        // Calculate statistics
        $salesValues = array_map(fn ($d) => (float) ($d['units'] ?? 0), $dailySales);
        $nonZeroSales = array_filter($salesValues, fn ($v) => $v > 0);
        $totalSales = array_sum($salesValues);
        $peakSales = ! empty($salesValues) ? max($salesValues) : 0;
        $avgSales = count($nonZeroSales) > 0 ? $totalSales / count($nonZeroSales) : 0;

        // Find peak day name
        $peakDay = '-';
        foreach ($dailySales as $day) {
            if ((float) $day['units'] === $peakSales && $peakSales > 0) {
                $peakDay = $day['dayName'];
                break;
            }
        }

        return response()->json([
            'weekStart' => $startDate->format('Y-m-d'),
            'weekEnd' => $endDate->format('Y-m-d'),
            'weekLabel' => 'Week of '.$startDate->format('d M'),
            'dailySales' => $dailySales,
            'productName' => $product->NAME,
            'stats' => [
                'total' => round($totalSales, 1),
                'peak' => round($peakSales, 1),
                'peakDay' => $peakDay,
                'average' => round($avgSales, 1),
                'daysWithSales' => count($nonZeroSales),
            ],
        ]);
    }

    /**
     * Get individual transaction details for a product on a specific date (used by sales chart popup drill-down).
     */
    public function transactionDetailsData(Request $request, string $id)
    {
        $product = $this->productRepository->findById($id);

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $date = $request->get('date');
        if (! $date) {
            return response()->json(['error' => 'date parameter is required'], 400);
        }

        // Validate date format
        try {
            $parsedDate = \Carbon\Carbon::parse($date);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid date format'], 400);
        }

        $transactions = $this->salesRepository->getProductTransactionDetails($id, $date);

        // Calculate summary stats
        $totalUnits = array_sum(array_column($transactions, 'units'));
        $totalValue = array_sum(array_column($transactions, 'total'));
        $avgPerTransaction = count($transactions) > 0 ? $totalUnits / count($transactions) : 0;

        return response()->json([
            'date' => $parsedDate->format('Y-m-d'),
            'dayName' => $parsedDate->format('l'),
            'dateLabel' => $parsedDate->format('D d M Y'),
            'transactions' => $transactions,
            'productName' => $product->NAME,
            'stats' => [
                'transactionCount' => count($transactions),
                'totalUnits' => round($totalUnits, 2),
                'totalValue' => round($totalValue, 2),
                'avgPerTransaction' => round($avgPerTransaction, 2),
            ],
        ]);
    }

    /**
     * Serve product image from database.
     * Returns binary image data with caching headers for browser caching.
     */
    public function image(string $id)
    {
        $product = Product::select('IMAGE')->find($id);

        if (! $product || ! $product->IMAGE) {
            abort(404);
        }

        // Return image with proper headers and caching (24 hours)
        return response($product->IMAGE)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Update product image.
     */
    public function updateProductImage(Request $request, string $id)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $product = Product::findOrFail($id);

        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');

            $imageManager = new ImageManager(new GdDriver);
            $image = $imageManager->read($imageFile->getRealPath());

            $image->resize(128, 128, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            $encodedImage = $image->encodeByExtension($this->determineImageExtension($imageFile));
            $imageData = $encodedImage->toString();
            $mimeType = $encodedImage->mediaType();
            $resizedWidth = $image->width();
            $resizedHeight = $image->height();

            // Start transaction on POS database connection
            DB::connection('pos')->beginTransaction();

            try {
                // Update the image in POS database
                DB::connection('pos')->table('PRODUCTS')
                    ->where('ID', $product->ID)
                    ->update(['IMAGE' => $imageData]);

                DB::connection('pos')->commit();

                \Log::info('Product image updated successfully', [
                    'product_id' => $product->ID,
                    'product_code' => $product->CODE,
                    'image_size_bytes' => strlen($imageData),
                    'mime_type' => $mimeType,
                    'width' => $resizedWidth,
                    'height' => $resizedHeight,
                ]);

                return response()->json([
                    'success' => true,
                    'timestamp' => time(),
                ]);

            } catch (\Exception $e) {
                DB::connection('pos')->rollBack();

                \Log::error('Failed to update product image', [
                    'product_id' => $product->ID,
                    'product_code' => $product->CODE,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Failed to update image',
                ], 500);
            }
        }

        return response()->json(['success' => false, 'error' => 'No image file provided']);
    }

    /**
     * Determine the image extension for encoding.
     */
    private function determineImageExtension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');

        $normalizedExtension = match ($extension) {
            'jpeg', 'jpg' => 'jpg',
            'png' => 'png',
            'gif' => 'gif',
            default => null,
        };

        if ($normalizedExtension) {
            return $normalizedExtension;
        }

        return match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            default => 'png',
        };
    }

    /**
     * Update the price for a product.
     */
    public function updatePrice(Request $request, string $id): RedirectResponse
    {
        // Validate based on input mode
        $rules = [
            'price_input_mode' => 'required|in:gross,net',
        ];

        if ($request->price_input_mode === 'gross') {
            $rules['gross_price'] = 'required|numeric|min:0|max:999999.9999';
        } else {
            $rules['net_price'] = 'required|numeric|min:0|max:999999.9999';
        }

        // Also accept final_net_price as fallback (for JavaScript-calculated values)
        if ($request->has('final_net_price')) {
            $rules['final_net_price'] = 'required|numeric|min:0|max:999999.9999';
        }

        $request->validate($rules);

        $product = $this->productRepository->findById($id);

        if (! $product) {
            abort(404, 'Product not found');
        }

        // Calculate net price based on input mode
        if ($request->price_input_mode === 'gross') {
            // User entered gross price, convert to net
            $taxCategory = TaxCategory::with('primaryTax')->find($product->TAXCAT);
            $vatRate = $taxCategory?->primaryTax?->RATE ?? 0.0;
            $netPrice = $vatRate > 0 ? $request->gross_price / (1 + $vatRate) : $request->gross_price;
        } elseif ($request->has('final_net_price')) {
            // Use the JavaScript-calculated net price (most accurate)
            $netPrice = $request->final_net_price;
        } else {
            // User entered net price directly
            $netPrice = $request->net_price;
        }

        // Update the product's net price (PRICESELL is stored without VAT)
        $product->update([
            'PRICESELL' => $netPrice,
        ]);

        // Log the price update event with additional context
        LabelLog::logPriceUpdate($product->CODE);

        $inputMode = $request->price_input_mode === 'gross' ? 'gross price' : 'net price';

        return redirect()
            ->route('products.show', $id)
            ->with('success', "Price updated successfully from {$inputMode}.");
    }

    /**
     * Update the cost for a product.
     */
    public function updateCost(Request $request, string $id)
    {

        $request->validate([
            'cost_price' => 'required|numeric|min:0|max:999999.99',
        ]);

        // Ensure cost_price is a float
        $costPrice = (float) $request->cost_price;

        // Try finding the product directly first
        $product = Product::find($id);

        // If not found, try the repository
        if (! $product) {
            $product = $this->productRepository->findById($id);
        }

        if (! $product) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Product not found'], 404);
            }
            abort(404, 'Product not found');
        }

        // Update the product's cost price
        try {
            // Simple direct update
            $product->PRICEBUY = $costPrice;
            $result = $product->save();

        } catch (\Exception $e) {

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Database update failed: '.$e->getMessage()], 500);
            }
            throw $e;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Cost updated successfully.',
                'cost' => $costPrice,
                'product_id' => $product->ID,
                'update_result' => $result ?? false,
            ]);
        }

        return redirect()
            ->route('products.show', $id)
            ->with('success', 'Cost updated successfully.');
    }

    /**
     * Update the barcode for a product.
     * WARNING: This updates all related records that reference the barcode.
     */
    public function updateBarcode(UpdateBarcodeRequest $request, string $id): RedirectResponse
    {
        $product = $this->productRepository->findById($id);

        if (! $product) {
            abort(404, 'Product not found');
        }

        $oldBarcode = $product->CODE;
        $newBarcode = $request->barcode;

        // If barcode hasn't changed, just redirect back
        if ($oldBarcode === $newBarcode) {
            return redirect()
                ->route('products.show', $id)
                ->with('info', 'Barcode unchanged.');
        }

        try {
            // Use a transaction to ensure all updates succeed or none do
            DB::transaction(function () use ($product, $oldBarcode, $newBarcode) {
                // 1. Update the product's CODE
                $product->update([
                    'CODE' => $newBarcode,
                ]);

                // 2. Update supplier_link records
                SupplierLink::where('Barcode', $oldBarcode)
                    ->update(['Barcode' => $newBarcode]);

                // 3. Handle stocking table (primary key is Barcode)
                $stockingRecord = Stocking::find($oldBarcode);
                if ($stockingRecord) {
                    // Get the data
                    $stockingData = $stockingRecord->toArray();
                    // Delete old record
                    $stockingRecord->delete();
                    // Create new record with new barcode
                    $stockingData['Barcode'] = $newBarcode;
                    Stocking::create($stockingData);
                }

                // 4. Update label_logs records
                LabelLog::where('barcode', $oldBarcode)
                    ->update(['barcode' => $newBarcode]);

                // 5. Update product_metadata records
                ProductMetadata::where('product_code', $oldBarcode)
                    ->update(['product_code' => $newBarcode]);

                // 6. Update veg_details records if they exist
                try {
                    VegDetails::where('product_code', $oldBarcode)
                        ->update(['product_code' => $newBarcode]);
                } catch (\Exception $e) {
                    // Table might not exist or have no records, that's okay
                }

                // 7. Log this barcode change as a special event
                LabelLog::create([
                    'barcode' => $newBarcode,
                    'event_type' => 'barcode_change',
                    'user_id' => auth()->id(),
                    'metadata' => json_encode([
                        'old_barcode' => $oldBarcode,
                        'new_barcode' => $newBarcode,
                    ]),
                ]);
            });

            return redirect()
                ->route('products.show', $id)
                ->with('success', "Barcode successfully changed from {$oldBarcode} to {$newBarcode}. All related records have been updated.");

        } catch (\Exception $e) {
            \Log::error('Failed to update barcode', [
                'product_id' => $id,
                'old_barcode' => $oldBarcode,
                'new_barcode' => $newBarcode,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('products.show', $id)
                ->with('error', 'Failed to update barcode. Please check the logs for details.');
        }
    }

    /**
     * Create a copy of a product with a new barcode.
     * Used when a product comes in with a new barcode (e.g., supplier changed packaging).
     */
    public function createAlternateBarcode(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'new_barcode' => ['required', 'string', 'max:255', 'unique:pos.PRODUCTS,CODE'],
        ]);

        $originalProduct = $this->productRepository->findById($id);

        if (! $originalProduct) {
            abort(404, 'Product not found');
        }

        $newBarcode = $request->new_barcode;

        try {
            $newProductId = (string) Str::uuid();
            $hadSupplierLink = (bool) $originalProduct->supplierLink;

            DB::connection('pos')->transaction(function () use ($originalProduct, $newBarcode, $newProductId) {
                // Generate unique name - append [alt] suffix since NAME has unique index
                // User can rename the product after creation
                $newName = $originalProduct->NAME.' [alt]';

                // If that name also exists, append barcode to make it truly unique
                if (Product::where('NAME', $newName)->exists()) {
                    $newName = $originalProduct->NAME.' ['.$newBarcode.']';
                }

                // Create the new product as a copy with the new barcode
                Product::create([
                    'ID' => $newProductId,
                    'NAME' => $newName,
                    'CODE' => $newBarcode,
                    'REFERENCE' => $newBarcode,
                    'CATEGORY' => $originalProduct->CATEGORY,
                    'PRICEBUY' => $originalProduct->PRICEBUY,
                    'PRICESELL' => $originalProduct->PRICESELL,
                    'TAXCAT' => $originalProduct->TAXCAT,
                    'DISPLAY' => $originalProduct->DISPLAY,
                ]);

                // MOVE supplier link from original product to new product
                // The supplier code stays the same - only the barcode reference changes
                if ($originalProduct->supplierLink) {
                    $originalProduct->supplierLink->update([
                        'Barcode' => $newBarcode,
                    ]);
                }

                // Initialize STOCKCURRENT entry
                try {
                    \App\Models\StockCurrent::create([
                        'LOCATION' => '0',
                        'PRODUCT' => $newProductId,
                        'ATTRIBUTESETINSTANCE_ID' => null,
                        'UNITS' => 0.0,
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Failed to initialize STOCKCURRENT for alternate barcode product', [
                        'product_id' => $newProductId,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Add to stocking table if original was stocked
                $originalStocking = Stocking::find($originalProduct->CODE);
                if ($originalStocking) {
                    try {
                        Stocking::create(['Barcode' => $newBarcode]);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to add alternate barcode product to stocking', [
                            'barcode' => $newBarcode,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Copy till visibility from original
                $originalVisibility = $this->tillVisibilityService->isVisibleOnTill($originalProduct->ID);
                if ($originalVisibility) {
                    $this->tillVisibilityService->setVisibility($newProductId, true, 'category');
                }

                // Create product metadata
                ProductMetadata::createForProduct(
                    $newProductId,
                    $newBarcode,
                    Auth::id(),
                    [
                        'source' => 'alternate_barcode',
                        'original_product_id' => $originalProduct->ID,
                        'original_barcode' => $originalProduct->CODE,
                    ]
                );

                // Log the new product event
                LabelLog::logNewProduct($newBarcode);
            });

            $message = "Product created with barcode {$newBarcode}. The name has '[alt]' appended - you may want to rename it.";
            if ($hadSupplierLink) {
                $message .= ' Supplier link has been transferred from the original product.';
            }

            return redirect()
                ->route('products.edit', $newProductId)
                ->with('success', $message);

        } catch (\Exception $e) {
            \Log::error('Failed to create alternate barcode product', [
                'original_product_id' => $id,
                'new_barcode' => $newBarcode,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('products.edit', $id)
                ->with('error', 'Failed to create product with alternate barcode: '.$e->getMessage());
        }
    }

    /**
     * Refresh Udea pricing for a specific product via AJAX.
     */
    public function refreshUdeaPricing(string $id)
    {
        $product = $this->productRepository->findById($id);

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        if (! $product->supplierLink?->SupplierCode ||
            ! $product->supplier ||
            ! $this->supplierService->hasExternalIntegration($product->supplier->SupplierID)) {
            return response()->json(['error' => 'Product does not have Udea supplier integration'], 400);
        }

        try {
            // Clear cache for this product to force fresh data
            $this->udeaScrapingService->clearCache($product->supplierLink->SupplierCode);

            // Fetch fresh pricing data
            $udeaPricing = $this->udeaScrapingService->getProductData($product->supplierLink->SupplierCode);

            if (! $udeaPricing) {
                return response()->json(['error' => 'Unable to fetch Udea pricing'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $udeaPricing,
                'product' => [
                    'current_price' => $product->PRICESELL,
                    'current_price_with_vat' => $product->PRICESELL * (1 + $product->getVatRate()),
                    'supplier_code' => $product->supplierLink->SupplierCode,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to refresh Udea pricing', [
                'product_id' => $id,
                'supplier_code' => $product->supplierLink->SupplierCode,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to fetch Udea pricing: '.$e->getMessage()], 500);
        }
    }

    /**
     * Display products with supplier information.
     */
    public function suppliersIndex(Request $request): View
    {
        $products = Product::with(['supplierLink', 'supplier', 'stocking', 'stockCurrent'])
            ->select(['ID', 'CODE', 'NAME', 'PRICESELL'])
            ->paginate(25);

        return view('products.supplier-test', compact('products'));
    }

    /**
     * Get the next available barcode for a specific category.
     * Uses configuration-driven approach to support multiple categories with different patterns.
     * Checks ALL products across ALL categories since barcodes are globally unique.
     */
    private function getNextAvailableBarcodeForCategory(string $categoryId): ?string
    {
        $config = config('barcode_patterns.categories.'.$categoryId);

        // Return null if category is not configured for barcode suggestions
        if (! $config) {
            return null;
        }

        $settings = config('barcode_patterns.settings');

        // Get existing codes for this category within internal code range
        $categoryCodes = Product::where('CATEGORY', $categoryId)
            ->pluck('CODE')
            ->filter(fn ($code) => is_numeric($code) && (int) $code <= $settings['max_internal_code'])
            ->map(fn ($code) => (int) $code)
            ->sort()
            ->values()
            ->toArray();

        // If no codes exist, start at the beginning of the first range
        if (empty($categoryCodes)) {
            $firstRange = $config['ranges'][0];

            return (string) $firstRange[0];
        }

        $min = min($categoryCodes);
        $max = max($categoryCodes);

        if ($config['priority'] === 'fill_gaps') {
            // Fill gaps in existing range first
            for ($i = $min; $i <= $max; $i++) {
                if (! in_array($i, $categoryCodes) && $this->isCodeAvailableInRange($i, $config['ranges']) && ! Product::where('CODE', (string) $i)->exists()) {
                    return (string) $i;
                }
            }
        }

        // No gaps found or priority is increment - find next available after highest
        $searchStart = $max + 1;
        $searchLimit = $searchStart + $settings['max_search_range'];

        for ($i = $searchStart; $i <= $searchLimit; $i++) {
            if ($this->isCodeAvailableInRange($i, $config['ranges']) && ! Product::where('CODE', (string) $i)->exists()) {
                return (string) $i;
            }
        }

        // Fallback: return next increment (might be outside configured ranges)
        return (string) ($max + 1);
    }

    /**
     * Check if a code falls within any of the configured ranges for a category.
     */
    private function isCodeAvailableInRange(int $code, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($code >= $range[0] && $code <= $range[1]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(Request $request): View
    {
        // Get necessary data for the form
        $taxCategories = TaxCategory::orderBy('NAME')->get();
        $categories = Category::orderBy('NAME')->get();
        $suppliers = Supplier::orderBy('Supplier')->get();

        // Get tax rates for JavaScript pricing calculations
        $taxRates = Tax::pluck('RATE', 'CATEGORY')->toArray();

        // Get UDEA supplier IDs from config
        $udeaSupplierIds = config('suppliers.external_links.udea.supplier_ids', [5, 44, 85]);

        // Check if we're creating from a delivery item
        $deliveryItemId = $request->query('delivery_item');
        $categoryId = $request->query('category'); // For category-specific creation (e.g., Coffee Fresh)
        $prefillData = null;

        // Auto-suggest barcode for configured categories
        $suggestedBarcode = null;
        $categoryConfig = null;
        if ($categoryId) {
            $suggestedBarcode = $this->getNextAvailableBarcodeForCategory($categoryId);
            $categoryConfig = config('barcode_patterns.categories.'.$categoryId);
        }

        if ($deliveryItemId) {
            $deliveryItem = \App\Models\DeliveryItem::findOrFail($deliveryItemId);
            $prefillData = [
                'name' => $deliveryItem->description,
                'code' => $deliveryItem->barcode ?: '',
                'price_buy' => $deliveryItem->unit_cost,
                'supplier_id' => $deliveryItem->delivery->supplier_id,
                'supplier_code' => $deliveryItem->supplier_code,
                'units_per_case' => $deliveryItem->units_per_case,
                'initial_stock' => $deliveryItem->received_quantity ?: $deliveryItem->ordered_quantity,
            ];

            // Check if this is a UDEA delivery item and try to get scraped customer price
            $udeaSupplierIds = config('suppliers.external_links.udea.supplier_ids', [5, 44, 85]);
            if (in_array($deliveryItem->delivery->supplier_id, $udeaSupplierIds)) {
                try {
                    $scrapedData = $this->udeaScrapingService->getProductDataForDeliveryItem($deliveryItem);
                    if ($scrapedData && isset($scrapedData['customer_price'])) {
                        // Use scraped customer price, converting from European format (comma) to float
                        $customerPrice = floatval(str_replace(',', '.', $scrapedData['customer_price']));
                        $prefillData['price_sell_suggested'] = $customerPrice;
                        $prefillData['price_source'] = 'udea_scraped';
                        $prefillData['scraped_data'] = $scrapedData;

                        // Include scraped product name if available and different from delivery description
                        if (isset($scrapedData['description']) && ! empty($scrapedData['description'])) {
                            $scrapedName = trim($scrapedData['description']);
                            $deliveryName = trim($deliveryItem->description);

                            // Only include if scraped name is different and not empty
                            if ($scrapedName !== $deliveryName && strlen($scrapedName) > 0) {
                                $prefillData['scraped_name'] = $scrapedName;
                                $prefillData['delivery_name'] = $deliveryName;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to fetch UDEA scraped pricing for delivery item', [
                        'delivery_item_id' => $deliveryItemId,
                        'supplier_code' => $deliveryItem->supplier_code,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // If no scraped price available, use 30% markup as fallback
            if (! isset($prefillData['price_sell_suggested'])) {
                $prefillData['price_sell_suggested'] = $deliveryItem->unit_cost * 1.3;
                $prefillData['price_source'] = 'calculated';
            }

            // Auto-select tax category based on delivery item's normalized tax rate
            if ($deliveryItem->hasIndependentPricingData() && $deliveryItem->recommended_tax_rate !== null) {
                $prefillData['tax_category'] = $this->mapTaxRateToCategory($deliveryItem->recommended_tax_rate);
            }

            // Use RSP as suggested selling price if available and better than calculated
            if ($deliveryItem->sale_price && $deliveryItem->sale_price > 0) {
                $prefillData['price_sell_suggested'] = $deliveryItem->sale_price;
                $prefillData['price_source'] = 'independent_rsp';
            }
        }

        return view('products.create', compact('taxCategories', 'categories', 'suppliers', 'prefillData', 'deliveryItemId', 'categoryId', 'taxRates', 'udeaSupplierIds', 'suggestedBarcode', 'categoryConfig'));
    }

    /**
     * Map a tax rate percentage to the corresponding POS tax category ID
     */
    private function mapTaxRateToCategory(float $taxRate): ?string
    {
        // Map normalized tax rates to POS tax category IDs
        return match ($taxRate) {
            0.0 => '000',    // Tax Zero
            9.0 => '003',    // Tax Second Reduced (9%)
            13.5 => '001',   // Tax Reduced (13.5%)
            23.0 => '002',   // Tax Standard (23%)
            default => null, // Unknown rate, let user select
        };
    }

    /**
     * Show the form for editing the product.
     */
    public function edit(Request $request, string $id): View
    {
        // Find the product
        $product = $this->productRepository->findById($id);
        if (! $product) {
            abort(404, 'Product not found');
        }

        // Get necessary data for the form (same as create method)
        $taxCategories = TaxCategory::orderBy('NAME')->get();
        $categories = Category::orderBy('NAME')->get();
        $suppliers = Supplier::orderBy('Supplier')->get();

        // Get tax rates for JavaScript pricing calculations
        $taxRates = Tax::pluck('RATE', 'CATEGORY')->toArray();

        // Get UDEA supplier IDs from config
        $udeaSupplierIds = config('suppliers.external_links.udea.supplier_ids', [5, 44, 85]);

        // Get supplier information if product has a supplier link
        $supplierLink = $product->supplierLinks->first();

        // Calculate gross price (VAT inclusive) for form display
        // This ensures consistency with the create form where users input gross prices
        $taxCategory = TaxCategory::with('primaryTax')->find($product->TAXCAT);
        $vatRate = $taxCategory?->primaryTax?->RATE ?? 0.0;
        $grossPrice = $vatRate > 0 ? $product->PRICESELL * (1 + $vatRate) : $product->PRICESELL;
        $grossPrice = number_format((float) $grossPrice, 2, '.', '');

        // Prepare data for form population
        $prefillData = [
            'name' => $product->NAME,
            'code' => $product->CODE,
            'reference' => $product->REFERENCE,
            'price_buy' => $product->PRICEBUY,
            'price_sell' => $grossPrice, // Show VAT-inclusive price for consistency
            'display_name' => $product->DISPLAY,
            'supplier_id' => $supplierLink?->SupplierID,
            'supplier_code' => $supplierLink?->SupplierCode,
            'units_per_case' => $supplierLink?->CaseUnits ?? 1,
        ];

        // Check if product is in stocking management
        $includeInStocking = $product->stocking !== null;

        // Check if product is visible on till
        $showOnTill = $this->tillVisibilityService->isVisibleOnTill($product->ID);

        // Get product order settings (for short-dated flag and shelf life)
        $orderSettings = ProductOrderSetting::where('product_id', $product->ID)->first();

        // Context information for navigation
        $fromDelivery = $request->query('from_delivery');
        $fromContext = $request->query('from');

        return view('products.edit', compact(
            'product',
            'taxCategories',
            'categories',
            'suppliers',
            'prefillData',
            'taxRates',
            'udeaSupplierIds',
            'includeInStocking',
            'showOnTill',
            'orderSettings',
            'fromDelivery',
            'fromContext'
        ));
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        try {
            // Generate unique product ID using UUID
            $productId = (string) Str::uuid();

            DB::connection('pos')->transaction(function () use ($request, $productId) {

                // Get VAT rate for the selected tax category to convert inclusive price to exclusive
                $taxCategory = TaxCategory::with('primaryTax')->find($request->tax_category);
                $vatRate = $taxCategory?->primaryTax?->RATE ?? 0.0;

                // Convert VAT-inclusive price to VAT-exclusive price for storage
                // PRICESELL should be stored ex-VAT as it's used in getGrossPrice() calculation
                $priceExVat = $vatRate > 0 ? $request->price_sell / (1 + $vatRate) : $request->price_sell;

                // Create the product with essential fields only
                $product = Product::create([
                    'ID' => $productId,
                    'NAME' => $request->name,
                    'CODE' => $request->code,
                    'REFERENCE' => $request->code, // Set reference same as barcode (CODE)
                    'CATEGORY' => $request->category,
                    'PRICEBUY' => $request->price_buy,
                    'PRICESELL' => $priceExVat, // Store ex-VAT price
                    'TAXCAT' => $request->tax_category,
                    'DISPLAY' => $request->display_name, // Optional display name for till buttons
                ]);

                // Create supplier link if supplier information provided
                if ($request->supplier_id && $request->supplier_code) {
                    try {
                        SupplierLink::create([
                            'Barcode' => $request->code,
                            'SupplierID' => $request->supplier_id,
                            'SupplierCode' => $request->supplier_code,
                            'CaseUnits' => $request->units_per_case ?? 1,
                            'Cost' => $request->price_buy,
                            'stocked' => true,
                        ]);
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Check if it's a duplicate entry error
                        if (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'link_index')) {

                            // Check if user confirmed override
                            if ($request->boolean('force_override', false)) {
                                // Find and delete the conflicting supplier link
                                $conflictingLink = SupplierLink::where('SupplierCode', $request->supplier_code)
                                    ->where('SupplierID', $request->supplier_id)
                                    ->first();

                                if ($conflictingLink) {
                                    \Log::info('Removing conflicting supplier link during product creation', [
                                        'old_product_barcode' => $conflictingLink->Barcode,
                                        'new_product_code' => $request->code,
                                        'supplier_code' => $request->supplier_code,
                                        'supplier_id' => $request->supplier_id,
                                        'user_id' => auth()->id(),
                                    ]);

                                    $conflictingLink->delete();
                                }

                                // Now create the supplier link for the new product
                                SupplierLink::create([
                                    'Barcode' => $request->code,
                                    'SupplierID' => $request->supplier_id,
                                    'SupplierCode' => $request->supplier_code,
                                    'CaseUnits' => $request->units_per_case ?? 1,
                                    'Cost' => $request->price_buy,
                                    'stocked' => true,
                                ]);
                            } else {
                                // Rollback the transaction and return error
                                DB::connection('pos')->rollBack();

                                // Find the conflicting product to show helpful error
                                $conflictingLink = SupplierLink::where('SupplierCode', $request->supplier_code)
                                    ->where('SupplierID', $request->supplier_id)
                                    ->with(['product', 'supplier'])
                                    ->first();

                                $errorMessage = 'This supplier code is already linked to another product';
                                if ($conflictingLink) {
                                    $errorMessage .= ': "'.($conflictingLink->product?->NAME ?? 'Unknown').'" ('.$conflictingLink->Barcode.')';
                                }

                                return back()
                                    ->withInput()
                                    ->withErrors([
                                        'supplier_code' => $errorMessage,
                                        'duplicate_conflict' => json_encode([
                                            'product_id' => $conflictingLink->product?->ID,
                                            'product_name' => $conflictingLink->product?->NAME,
                                            'product_barcode' => $conflictingLink->Barcode,
                                            'supplier_name' => $conflictingLink->supplier?->Supplier,
                                        ]),
                                    ]);
                            }
                        } else {
                            // Re-throw if it's a different database error
                            throw $e;
                        }
                    }
                }

                // Log the new product event
                LabelLog::logNewProduct($request->code);

                // Set till visibility if requested
                if ($request->boolean('show_on_till', true)) {
                    $this->tillVisibilityService->setVisibility($product->ID, true, 'category');
                }

                // Create product metadata for creation tracking
                ProductMetadata::createForProduct(
                    $product->ID,
                    $product->CODE,
                    Auth::id(),
                    [
                        'source' => 'manual_creation',
                        'delivery_item_id' => $request->delivery_item_id,
                        'has_display_name' => ! empty($request->display_name),
                        'initial_till_visibility' => $request->boolean('show_on_till', true),
                    ]
                );

                // If this product was created from a delivery item, link them
                if ($request->delivery_item_id) {
                    $deliveryItem = \App\Models\DeliveryItem::findOrFail($request->delivery_item_id);
                    $deliveryItem->update([
                        'product_id' => $product->ID,
                        'is_new_product' => false,
                        'barcode' => $product->CODE,
                    ]);
                }

                // Initialize STOCKCURRENT entry with 0 units (required for POS integration)
                try {
                    \App\Models\StockCurrent::create([
                        'LOCATION' => '0',  // Default location
                        'PRODUCT' => $product->ID,
                        'ATTRIBUTESETINSTANCE_ID' => null,
                        'UNITS' => 0.0,  // Initialize with 0 stock
                    ]);
                } catch (\Exception $e) {
                    // Log the error but don't fail the product creation
                    \Log::warning('Failed to initialize STOCKCURRENT for new product', [
                        'product_id' => $product->ID,
                        'product_code' => $product->CODE,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Add to stocking table if requested
                if ($request->boolean('include_in_stocking', true)) {
                    try {
                        \App\Models\Stocking::create([
                            'Barcode' => $product->CODE,
                        ]);
                    } catch (\Exception $e) {
                        // Log the error but don't fail the product creation
                        \Log::warning('Failed to add product to stocking table', [
                            'product_code' => $product->CODE,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            // Determine redirect route with context
            if ($request->delivery_item_id) {
                $deliveryItem = \App\Models\DeliveryItem::findOrFail($request->delivery_item_id);
                $redirectUrl = route('products.show', $productId).'?from_delivery='.$deliveryItem->delivery_id;

                return redirect($redirectUrl)
                    ->with('success', 'Product created successfully!');
            }

            return redirect()
                ->route('products.show', $productId)
                ->with('success', 'Product created successfully!');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create product: '.$e->getMessage()]);
        }
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, string $id): RedirectResponse
    {
        try {
            // Find the product
            $product = $this->productRepository->findById($id);
            if (! $product) {
                abort(404, 'Product not found');
            }

            // Get VAT rate for the selected tax category to convert inclusive price to exclusive
            $taxCategory = TaxCategory::with('primaryTax')->find($request->tax_category);
            $vatRate = $taxCategory?->primaryTax?->RATE ?? 0.0;

            // Convert VAT-inclusive price to VAT-exclusive price for storage
            // PRICESELL should be stored ex-VAT as it's used in getGrossPrice() calculation
            $priceExVat = $vatRate > 0 ? $request->price_sell / (1 + $vatRate) : $request->price_sell;

            // Update the product's basic information
            $productData = [
                'NAME' => $request->name,
                'REFERENCE' => $request->reference,
                'CATEGORY' => $request->category,
                'TAXCAT' => $request->tax_category,
                'PRICESELL' => $priceExVat, // Store ex-VAT price
                'PRICEBUY' => $request->price_buy,
                'DISPLAY' => $request->display_name,
            ];

            // Update the product
            $product->update($productData);

            // Update supplier link if provided
            if ($request->supplier_id) {
                try {
                    $supplierLink = $product->supplierLinks->first();

                    if ($supplierLink) {
                        // Update existing supplier link
                        $supplierLink->update([
                            'SupplierID' => $request->supplier_id,
                            'SupplierCode' => $request->supplier_code,
                            'CaseUnits' => $request->units_per_case ?? 1,
                            'Cost' => $request->price_buy,
                        ]);
                    } else {
                        // Create new supplier link
                        \App\Models\SupplierLink::create([
                            'Barcode' => $product->CODE,
                            'SupplierID' => $request->supplier_id,
                            'SupplierCode' => $request->supplier_code,
                            'CaseUnits' => $request->units_per_case ?? 1,
                            'Cost' => $request->price_buy,
                            'stocked' => true,
                        ]);
                    }
                } catch (\Illuminate\Database\QueryException $e) {
                    // Check if it's a duplicate entry error
                    if (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'link_index')) {

                        // Check if user confirmed override
                        if ($request->boolean('force_override', false)) {
                            // Find and delete the conflicting supplier link
                            $conflictingLink = \App\Models\SupplierLink::where('SupplierCode', $request->supplier_code)
                                ->where('SupplierID', $request->supplier_id)
                                ->first();

                            if ($conflictingLink) {
                                \Log::info('Removing conflicting supplier link due to override', [
                                    'old_product_barcode' => $conflictingLink->Barcode,
                                    'new_product_id' => $product->ID,
                                    'supplier_code' => $request->supplier_code,
                                    'supplier_id' => $request->supplier_id,
                                    'user_id' => auth()->id(),
                                ]);

                                $conflictingLink->delete();
                            }

                            // Now create/update the supplier link for this product
                            if ($supplierLink) {
                                $supplierLink->update([
                                    'SupplierID' => $request->supplier_id,
                                    'SupplierCode' => $request->supplier_code,
                                    'CaseUnits' => $request->units_per_case ?? 1,
                                    'Cost' => $request->price_buy,
                                ]);
                            } else {
                                \App\Models\SupplierLink::create([
                                    'Barcode' => $product->CODE,
                                    'SupplierID' => $request->supplier_id,
                                    'SupplierCode' => $request->supplier_code,
                                    'CaseUnits' => $request->units_per_case ?? 1,
                                    'Cost' => $request->price_buy,
                                    'stocked' => true,
                                ]);
                            }
                        } else {
                            // Find the conflicting product to show helpful error
                            $conflictingLink = \App\Models\SupplierLink::where('SupplierCode', $request->supplier_code)
                                ->where('SupplierID', $request->supplier_id)
                                ->with(['product', 'supplier'])
                                ->first();

                            $errorMessage = 'This supplier code is already linked to another product';
                            if ($conflictingLink) {
                                $errorMessage .= ': "'.($conflictingLink->product?->NAME ?? 'Unknown').'" ('.$conflictingLink->Barcode.')';
                            }

                            return back()
                                ->withInput()
                                ->withErrors([
                                    'supplier_code' => $errorMessage,
                                    'duplicate_conflict' => json_encode([
                                        'product_id' => $conflictingLink->product?->ID,
                                        'product_name' => $conflictingLink->product?->NAME,
                                        'product_barcode' => $conflictingLink->Barcode,
                                        'supplier_name' => $conflictingLink->supplier?->Supplier,
                                    ]),
                                ]);
                        }
                    } else {
                        // Re-throw if it's a different database error
                        throw $e;
                    }
                }
            }

            // Update stocking status
            if ($request->boolean('include_in_stocking', false)) {
                // Ensure stocking record exists
                if (! $product->stocking) {
                    \App\Models\Stocking::create([
                        'PRODUCT' => $product->CODE,
                        'STOCKSECURITY' => 0,
                        'STOCKMAXIMUM' => 0,
                        'UNITS' => 0,
                    ]);
                }
            } else {
                // Remove from stocking if it exists
                $product->stocking?->delete();
            }

            // Update till visibility
            $this->tillVisibilityService->setVisibility(
                $product->ID,
                $request->boolean('show_on_till', true)
            );

            // Update short-dated product settings (Admin/Manager only)
            if (auth()->user()->hasAnyRole(['admin', 'manager'])) {
                $orderSettings = ProductOrderSetting::firstOrCreate(
                    ['product_id' => $product->ID],
                    [
                        'review_priority' => 'standard',
                        'auto_approve' => false,
                        'safety_stock_factor' => 1.5,
                        'last_updated' => now(),
                    ]
                );

                $orderSettings->is_short_dated = $request->boolean('is_short_dated', false);
                $orderSettings->shelf_life_days = $request->shelf_life_days !== null
                    ? (int) $request->shelf_life_days
                    : null;
                $orderSettings->last_updated = now();
                $orderSettings->save();
            }

            // Ensure STOCKCURRENT exists (create if missing)
            if (! $product->stockCurrent) {
                try {
                    \App\Models\StockCurrent::create([
                        'LOCATION' => '0',  // Default location
                        'PRODUCT' => $product->ID,
                        'ATTRIBUTESETINSTANCE_ID' => null,
                        'UNITS' => 0.0,  // Initialize with 0 stock
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Failed to create missing STOCKCURRENT during product update', [
                        'product_id' => $product->ID,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Log the update
            \Log::info('Product updated via edit form', [
                'product_id' => $product->ID,
                'product_name' => $product->NAME,
                'updated_by' => auth()->id(),
                'updated_fields' => array_keys($productData),
            ]);

            // Determine redirect route with context
            $fromDelivery = $request->query('from_delivery');
            $fromContext = $request->query('from');

            $redirectUrl = route('products.show', $product->ID);

            if ($fromDelivery) {
                $redirectUrl .= '?from_delivery='.$fromDelivery;
            } elseif ($fromContext) {
                $redirectUrl .= '?from='.$fromContext;
            }

            return redirect($redirectUrl)
                ->with('success', 'Product updated successfully!');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update product: '.$e->getMessage()]);
        }
    }

    /**
     * Get UDEA pricing data for a given supplier code via AJAX.
     */
    public function getUdeaPricing(Request $request)
    {
        $request->validate([
            'supplier_code' => 'required|string',
        ]);

        try {
            $supplierCode = $request->input('supplier_code');
            $scrapedData = $this->udeaScrapingService->getProductData($supplierCode);

            if (! $scrapedData || ! isset($scrapedData['customer_price'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No UDEA pricing data found for this supplier code',
                ], 404);
            }

            // Convert European format (comma) to float for calculations
            $customerPrice = floatval(str_replace(',', '.', $scrapedData['customer_price']));

            return response()->json([
                'success' => true,
                'data' => [
                    'customer_price' => $customerPrice,
                    'customer_price_formatted' => $scrapedData['customer_price'],
                    'case_price' => $scrapedData['case_price'] ?? null,
                    'description' => $scrapedData['description'] ?? null,
                    'units_per_case' => $scrapedData['units_per_case'] ?? null,
                    'scraped_at' => $scrapedData['scraped_at'] ?? null,
                    'source' => 'udea_scraped',
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch UDEA pricing via AJAX', [
                'supplier_code' => $request->input('supplier_code'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch UDEA pricing: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check for duplicate supplier link (AJAX endpoint for real-time validation).
     */
    public function checkSupplierLinkDuplicate(Request $request)
    {
        $request->validate([
            'supplier_code' => 'required|string',
            'supplier_id' => 'required|integer',
            'current_product_id' => 'nullable|string', // UUID of product being edited (null for new products)
        ]);

        try {
            $supplierCode = $request->input('supplier_code');
            $supplierId = $request->input('supplier_id');
            $currentProductId = $request->input('current_product_id');

            // Find existing supplier link with this combination
            $existingLink = \App\Models\SupplierLink::where('SupplierCode', $supplierCode)
                ->where('SupplierID', $supplierId)
                ->with(['product', 'supplier'])
                ->first();

            // No duplicate found
            if (! $existingLink) {
                return response()->json([
                    'duplicate' => false,
                ]);
            }

            // Check if it's the same product (allowed when editing)
            if ($currentProductId && $existingLink->Barcode === $existingLink->product?->CODE) {
                $currentProduct = Product::find($currentProductId);
                if ($currentProduct && $currentProduct->CODE === $existingLink->Barcode) {
                    return response()->json([
                        'duplicate' => false,
                    ]);
                }
            }

            // Duplicate found - return conflict details
            return response()->json([
                'duplicate' => true,
                'conflict' => [
                    'product_id' => $existingLink->product?->ID,
                    'product_name' => $existingLink->product?->NAME,
                    'product_barcode' => $existingLink->Barcode,
                    'supplier_name' => $existingLink->supplier?->Supplier,
                    'supplier_code' => $existingLink->SupplierCode,
                    'supplier_id' => $existingLink->SupplierID,
                    'edit_url' => $existingLink->product ? route('products.edit', $existingLink->product->ID) : null,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to check supplier link duplicate', [
                'supplier_code' => $request->input('supplier_code'),
                'supplier_id' => $request->input('supplier_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check for duplicates: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a barcode already exists (AJAX endpoint for real-time validation).
     */
    public function checkBarcodeDuplicate(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
        ]);

        try {
            $barcode = $request->input('barcode');

            // Find existing product with this barcode
            $existingProduct = Product::where('CODE', $barcode)
                ->with(['category', 'supplierLinks.supplier'])
                ->first();

            // No duplicate found
            if (! $existingProduct) {
                return response()->json([
                    'exists' => false,
                ]);
            }

            // Get primary supplier link
            $supplierLink = $existingProduct->supplierLinks->first();

            // Product exists - return details
            return response()->json([
                'exists' => true,
                'product' => [
                    'id' => $existingProduct->ID,
                    'name' => $existingProduct->NAME,
                    'barcode' => $existingProduct->CODE,
                    'category_name' => $existingProduct->category?->NAME,
                    'supplier_name' => $supplierLink?->supplier?->Supplier,
                    'supplier_code' => $supplierLink?->SupplierCode,
                    'selling_price' => $existingProduct->getGrossPrice(),
                    'cost_price' => $existingProduct->PRICEBUY,
                    'edit_url' => route('products.edit', $existingProduct->ID),
                    'view_url' => route('products.show', $existingProduct->ID),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to check barcode duplicate', [
                'barcode' => $request->input('barcode'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check barcode: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate and print a label for a product.
     */
    public function printLabel(string $id, Request $request)
    {
        $product = $this->productRepository->findById($id);

        if (! $product) {
            abort(404, 'Product not found');
        }

        // Get the selected template or use default
        $templateId = $request->input('template_id');
        $template = null;
        if ($templateId) {
            $template = LabelTemplate::active()->find($templateId);
        }
        $template = $template ?? LabelTemplate::getDefault();

        // Log the label print event
        LabelLog::logLabelPrint($product->CODE);

        // Generate the label HTML
        $labelHtml = $this->labelService->generateLabelHtml($product, $template);

        // Return the label HTML with appropriate headers for printing
        return response($labelHtml)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Update product display field.
     */
    public function updateDisplay(Request $request, string $id)
    {
        $request->validate([
            'display' => 'nullable|string|max:255',
        ]);

        $product = $this->productRepository->findById($id);

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $product->update(['DISPLAY' => $request->display]);

        return response()->json(['success' => true]);
    }

    /**
     * Toggle stocking status for a product via AJAX.
     */
    public function toggleStocking(string $id, Request $request)
    {
        $request->validate([
            'include_in_stocking' => 'required|boolean',
        ]);

        $product = $this->productRepository->findById($id);

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $shouldInclude = $request->boolean('include_in_stocking');

        try {
            if ($shouldInclude) {
                // Add to stocking table if not already present
                \App\Models\Stocking::firstOrCreate(['Barcode' => $product->CODE]);
                $message = 'Product added to stock management';
            } else {
                // Remove from stocking table
                \App\Models\Stocking::where('Barcode', $product->CODE)->delete();
                $message = 'Product removed from stock management';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'is_stocked' => $shouldInclude,
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to toggle stocking status', [
                'product_id' => $id,
                'product_code' => $product->CODE,
                'action' => $shouldInclude ? 'add' : 'remove',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update stocking status: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle till visibility for a product via AJAX.
     */
    public function toggleTillVisibility(string $id, Request $request)
    {
        $request->validate([
            'visible' => 'required|boolean',
        ]);

        $product = $this->productRepository->findById($id);

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $makeVisible = $request->boolean('visible');

        try {
            // Set visibility using the TillVisibilityService
            $success = $this->tillVisibilityService->setVisibility($id, $makeVisible);

            // Get the current visibility status
            $isVisible = $this->tillVisibilityService->isVisibleOnTill($id);

            return response()->json([
                'success' => $success,
                'is_visible' => $isVisible,
                'message' => $isVisible ? 'Product is now visible on till' : 'Product is now hidden from till',
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to toggle till visibility', [
                'product_id' => $id,
                'product_code' => $product->CODE,
                'action' => $makeVisible ? 'show' : 'hide',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update till visibility: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the category for a product.
     */
    public function updateCategory(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'category_id' => 'nullable|string|exists:pos.CATEGORIES,ID',
        ]);

        $product = $this->productRepository->findById($id);

        if (! $product) {
            abort(404, 'Product not found');
        }

        // Update the product's category in the POS database
        $product->update([
            'CATEGORY' => $request->category_id ?: null,
        ]);

        return redirect()
            ->route('products.show', $id)
            ->with('success', 'Product category updated successfully.');
    }

    /**
     * Update stock for a product via AJAX.
     */
    public function updateStock(Request $request, string $id)
    {
        $request->validate([
            'stock_units' => 'required|numeric|min:0|max:9999.99',
        ]);

        $product = $this->productRepository->findById($id);

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Check if it's a service item (service items don't have stock)
        if ($product->isService()) {
            return response()->json(['error' => 'Cannot update stock for service items'], 422);
        }

        $stockUnits = (float) $request->stock_units;

        try {
            // Update or create stock record in STOCKCURRENT table
            $stockRecord = \App\Models\StockCurrent::where('PRODUCT', $product->ID)->first();

            if ($stockRecord) {
                // Update existing record
                $stockRecord->update(['UNITS' => $stockUnits]);
            } else {
                // Create new stock record
                \App\Models\StockCurrent::create([
                    'LOCATION' => '0',  // Default location
                    'PRODUCT' => $product->ID,
                    'ATTRIBUTESETINSTANCE_ID' => null,
                    'UNITS' => $stockUnits,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully',
                'stock_units' => $stockUnits,
                'product_id' => $product->ID,
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to update stock', [
                'product_id' => $id,
                'product_code' => $product->CODE,
                'stock_units' => $stockUnits,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update stock: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all categories formatted for dropdown.
     */
    private function getAllCategoriesForDropdown()
    {
        // Get all categories with their parent relationships
        $categories = Category::with('parent')
            ->orderBy('NAME')
            ->get()
            ->map(function ($category) {
                // Build the category path for hierarchical display
                $path = $category->NAME;
                $parent = $category->parent;

                while ($parent) {
                    $path = $parent->NAME.' > '.$path;
                    $parent = $parent->parent;
                }

                $category->category_path = $path;

                return $category;
            })
            ->sortBy('category_path');

        return $categories;
    }

    /**
     * Update the minimum stock override for a product.
     * Only Admin and Manager users can set this override.
     */
    public function updateMinStockOverride(Request $request, string $id, OrderService $orderService)
    {
        \Log::info('=== MIN STOCK UPDATE START ===', [
            'product_id' => $id,
            'value' => $request->min_stock_override,
            'is_json' => $request->expectsJson(),
        ]);

        // Authorization check: Admin and Manager only
        if (! auth()->user()->hasAnyRole(['admin', 'manager'])) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized. Only Admin and Manager users can set minimum stock overrides.'], 403);
            }
            abort(403, 'Unauthorized. Only Admin and Manager users can set minimum stock overrides.');
        }

        $request->validate([
            'min_stock_override' => 'nullable|numeric|min:0|max:999999.99',
            'order_session_id' => 'nullable|integer|exists:order_sessions,id',
        ]);

        // Get the product
        $product = Product::find($id);

        if (! $product) {
            $product = $this->productRepository->findById($id);
        }

        if (! $product) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Product not found'], 404);
            }
            abort(404, 'Product not found');
        }

        try {
            // Get or create product order settings
            $settings = ProductOrderSetting::firstOrCreate(
                ['product_id' => $product->ID],
                [
                    'review_priority' => 'standard',
                    'auto_approve' => false,
                    'safety_stock_factor' => 1.5,
                    'last_updated' => now(),
                ]
            );

            // Update min stock override (null to remove override)
            $minStockValue = $request->min_stock_override !== null
                ? (float) $request->min_stock_override
                : null;

            $settings->min_stock_override = $minStockValue;
            $settings->last_updated = now();
            $settings->save();

            \Log::info('=== MIN STOCK SAVED ===', ['value' => $minStockValue]);

            // Recalculate order quantity with new minimum stock override
            $recalculatedData = null;
            if ($request->expectsJson()) {
                \Log::info('=== STARTING RECALCULATION ===', ['product_type' => get_class($product)]);

                try {
                    $orderItem = null;
                    $recalculationOptions = [];

                    if ($request->order_session_id) {
                        $orderSession = OrderSession::with('supplier')->find($request->order_session_id);

                        if ($orderSession) {
                            $orderItem = OrderItem::where('order_session_id', $orderSession->id)
                                ->where('product_id', $product->ID)
                                ->first();

                            $coverageDays = max(1, (int) ($orderSession->coverage_days ?? 7));
                            $coverageWeeks = max(0.1, (float) ($coverageDays / 7));
                            $coverageEndsOn = $orderSession->coverage_ends_on;
                            $salesHistoryWeeks = max(
                                1,
                                min(26, (int) ($orderSession->sales_history_weeks ?? 8))
                            );

                            $recalculationOptions = [
                                'coverage_days' => $coverageDays,
                                'coverage_weeks' => $coverageWeeks,
                                'sales_history_weeks' => $salesHistoryWeeks,
                                'coverage_ends_on' => $coverageEndsOn,
                            ];

                            $categoryGroups = SpecialOrderCategories::forSupplier(
                                (string) $orderSession->supplier_id,
                                optional($orderSession->supplier)->Supplier ?? null
                            );
                            $coverageOverrides = $orderSession->coverage_overrides ?? [];

                            $groupKey = null;
                            if (! empty($categoryGroups)) {
                                $productCategory = $product->CATEGORY ?? null;
                                if ($productCategory !== null) {
                                    foreach ($categoryGroups as $key => $definition) {
                                        if (in_array($productCategory, $definition['category_codes'] ?? [], true)) {
                                            $groupKey = $key;
                                            break;
                                        }
                                    }
                                }

                                if ($groupKey === null && $orderItem && is_array($orderItem->context_data)) {
                                    $groupKey = $orderItem->context_data['category_group_key'] ?? null;
                                }
                            }

                            if ($groupKey) {
                                $recalculationOptions['category_group_key'] = $groupKey;
                                $recalculationOptions['category_group_label'] = SpecialOrderCategories::labelForGroup(
                                    $groupKey,
                                    $categoryGroups
                                );

                                $groupOverride = $coverageOverrides[$groupKey] ?? null;
                                if ($groupOverride) {
                                    $recalculationOptions['coverage_override'] = $groupOverride;

                                    if (! empty($groupOverride['coverage_days'])) {
                                        $overrideDays = max(1, (int) $groupOverride['coverage_days']);
                                        $recalculationOptions['coverage_days'] = $overrideDays;
                                        $recalculationOptions['coverage_weeks'] = max(
                                            0.1,
                                            (float) ($overrideDays / 7)
                                        );
                                    }

                                    if (! empty($groupOverride['coverage_ends_on'])) {
                                        try {
                                            $recalculationOptions['coverage_ends_on'] = Carbon::parse($groupOverride['coverage_ends_on']);
                                        } catch (\Throwable $parseException) {
                                            $recalculationOptions['coverage_ends_on'] = $groupOverride['coverage_ends_on'];
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $suggestion = $orderService->calculateProductSuggestion($product, $recalculationOptions);
                    \Log::info('=== RECALCULATION SUCCESS ===');

                    if ($suggestion) {
                        $currentStock = $suggestion['context_data']['current_stock'] ?? 0;
                        $suggestedQuantity = $suggestion['suggested_quantity'] ?? 0;
                        $afterOrderStock = $currentStock + $suggestedQuantity;

                        $recalculatedData = [
                            'suggested_quantity' => $suggestedQuantity,
                            'after_order_stock' => $afterOrderStock,
                            'context_data' => $suggestion['context_data'],
                        ];

                        if ($orderItem) {
                            $orderItem->suggested_quantity = $suggestedQuantity;
                            $orderItem->final_quantity = $suggestedQuantity;
                            $orderItem->suggested_cases = $suggestion['suggested_cases'] ?? 0;
                            $orderItem->final_cases = $suggestion['suggested_cases'] ?? 0;
                            $orderItem->total_cost = $suggestedQuantity * $orderItem->unit_cost;
                            $orderItem->context_data = $suggestion['context_data'];
                            $orderItem->save();
                            $orderItem->refresh();
                            \Log::info('=== ORDER ITEM UPDATED ===', ['item_id' => $orderItem->id]);
                        }
                    }
                } catch (\Exception $e) {
                    // If recalculation fails, still return success for the min stock update
                    // Just don't include recalculated data
                    \Log::warning('=== RECALCULATION FAILED ===', [
                        'product_id' => $product->ID,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            \Log::error('=== MIN STOCK UPDATE EXCEPTION ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Failed to update minimum stock override: '.$e->getMessage()], 500);
            }
            throw $e;
        }

        if ($request->expectsJson()) {
            $response = [
                'message' => $minStockValue !== null
                    ? 'Minimum stock override set successfully.'
                    : 'Minimum stock override removed successfully.',
                'min_stock_override' => $minStockValue,
                'product_id' => $product->ID,
            ];

            // Add recalculated order data if available
            if ($recalculatedData) {
                $response['recalculated'] = $recalculatedData;
            }

            \Log::info('=== MIN STOCK UPDATE SUCCESS ===', $response);

            return response()->json($response);
        }

        return redirect()
            ->route('products.show', $id)
            ->with('success', $minStockValue !== null
                ? 'Minimum stock override set successfully.'
                : 'Minimum stock override removed successfully.');
    }

    /**
     * Update short-dated product settings.
     * Only Admin and Manager users can modify these settings.
     */
    public function updateShortDatedSettings(Request $request, string $id)
    {
        // Authorization check: Admin and Manager only
        if (! auth()->user()->hasAnyRole(['admin', 'manager'])) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized. Only Admin and Manager users can modify short-dated settings.'], 403);
            }
            abort(403, 'Unauthorized. Only Admin and Manager users can modify short-dated settings.');
        }

        $request->validate([
            'is_short_dated' => 'required|boolean',
            'shelf_life_days' => 'nullable|integer|min:0|max:999',
        ]);

        // Get the product
        $product = Product::find($id);

        if (! $product) {
            $product = $this->productRepository->findById($id);
        }

        if (! $product) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Product not found'], 404);
            }
            abort(404, 'Product not found');
        }

        try {
            // Get or create product order settings
            $settings = ProductOrderSetting::firstOrCreate(
                ['product_id' => $product->ID],
                [
                    'review_priority' => 'standard',
                    'auto_approve' => false,
                    'safety_stock_factor' => 1.5,
                    'last_updated' => now(),
                ]
            );

            // Update short-dated settings
            $settings->is_short_dated = $request->boolean('is_short_dated');
            $settings->shelf_life_days = $request->shelf_life_days !== null
                ? (int) $request->shelf_life_days
                : null;
            $settings->last_updated = now();
            $settings->save();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Short-dated settings updated successfully.',
                    'is_short_dated' => $settings->is_short_dated,
                    'shelf_life_days' => $settings->shelf_life_days,
                    'product_id' => $product->ID,
                ]);
            }

            return redirect()
                ->route('products.show', $id)
                ->with('success', 'Short-dated settings updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to update short-dated settings', [
                'product_id' => $id,
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Failed to update short-dated settings.'], 500);
            }

            return redirect()
                ->route('products.show', $id)
                ->with('error', 'Failed to update short-dated settings.');
        }
    }
}
