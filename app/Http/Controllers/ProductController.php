<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateBarcodeRequest;
use App\Models\Category;
use App\Models\LabelLog;
use App\Models\LabelTemplate;
use App\Models\Product;
use App\Models\ProductMetadata;
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
use App\Services\SupplierService;
use App\Services\TillVisibilityService;
use App\Services\UdeaScrapingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

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

        // Get categories for dropdown, filtered by current search criteria
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
     * Get the next available barcode for Coffee Fresh category (081).
     * Follows the 4000s numbering pattern, fills gaps or increments from highest.
     * Checks ALL products across ALL categories since barcodes are globally unique.
     */
    private function getNextAvailableCoffeeFreshBarcode(): string
    {
        // Get Coffee Fresh codes to understand the numbering pattern
        $coffeeFreshCodes = Product::where('CATEGORY', '081')
            ->pluck('CODE')
            ->filter(fn($code) => is_numeric($code) && (int)$code < 100000)
            ->map(fn($code) => (int)$code)
            ->sort()
            ->values()
            ->toArray();
            
        // If no Coffee Fresh codes exist, start at 4001
        if (empty($coffeeFreshCodes)) {
            return '4001';
        }
        
        // Determine the search range based on Coffee Fresh pattern
        $min = min($coffeeFreshCodes);
        $max = max($coffeeFreshCodes);
        
        // First check for gaps in the Coffee Fresh range, but verify globally
        for ($i = $min; $i <= $max; $i++) {
            if (!in_array($i, $coffeeFreshCodes) && !Product::where('CODE', (string)$i)->exists()) {
                return (string)$i;
            }
        }
        
        // No gaps found, find next available code after highest Coffee Fresh code
        for ($i = $max + 1; $i <= $max + 200; $i++) { // Check next 200 numbers
            if (!Product::where('CODE', (string)$i)->exists()) {
                return (string)$i;
            }
        }
        
        // Fallback: if everything is taken, return next increment
        return (string)($max + 1);
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
        
        // Auto-suggest barcode for Coffee Fresh category (081)
        $suggestedBarcode = null;
        if ($categoryId === '081') {
            $suggestedBarcode = $this->getNextAvailableCoffeeFreshBarcode();
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

        return view('products.create', compact('taxCategories', 'categories', 'suppliers', 'prefillData', 'deliveryItemId', 'categoryId', 'taxRates', 'udeaSupplierIds', 'suggestedBarcode'));
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
                    SupplierLink::create([
                        'Barcode' => $request->code,
                        'SupplierID' => $request->supplier_id,
                        'SupplierCode' => $request->supplier_code,
                        'CaseUnits' => $request->units_per_case ?? 1,
                        'Cost' => $request->supplier_cost ?? $request->price_buy,
                        'stocked' => true,
                    ]);
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
                    'PRODUCT' => $product->ID,
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
}
