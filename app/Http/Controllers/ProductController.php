<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierLink;
use App\Models\TaxCategory;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SalesRepository;
use App\Services\SupplierService;
use App\Services\UdeaScrapingService;
use App\Services\LabelService;
use App\Models\LabelLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Create a new controller instance.
     */
    public function __construct(
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        SalesRepository $salesRepository,
        SupplierService $supplierService,
        UdeaScrapingService $udeaScrapingService,
        LabelService $labelService
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->salesRepository = $salesRepository;
        $this->supplierService = $supplierService;
        $this->udeaScrapingService = $udeaScrapingService;
        $this->labelService = $labelService;
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

        // Get suppliers for dropdown if needed, filtered by current search criteria
        $suppliers = $showSuppliers ? $this->productRepository->getAllSuppliersWithProducts(
            stockedOnly: $stockedOnly,
            inStockOnly: $inStockOnly,
            activeOnly: $activeOnly
        ) : collect();

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
    public function show(string $id): View
    {
        $product = $this->productRepository->findById($id);

        if (! $product) {
            abort(404, 'Product not found');
        }

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

        return view('products.show', [
            'product' => $product,
            'taxCategories' => $taxCategories,
            'salesHistory' => $salesHistory,
            'salesStats' => $salesStats,
            'supplierService' => $this->supplierService,
            'udeaPricing' => $udeaPricing,
        ]);
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
        $request->validate([
            'net_price' => 'required|numeric|min:0|max:999999.9999',
        ]);

        $product = $this->productRepository->findById($id);

        if (! $product) {
            abort(404, 'Product not found');
        }

        // Update the product's net price (PRICESELL is stored without VAT)
        $product->update([
            'PRICESELL' => $request->net_price,
        ]);

        // Log the price update event
        LabelLog::logPriceUpdate($product->CODE);

        return redirect()
            ->route('products.show', $id)
            ->with('success', 'Price updated successfully.');
    }

    /**
     * Update the cost for a product.
     */
    public function updateCost(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'cost_price' => 'required|numeric|min:0|max:999999.99',
        ]);

        $product = $this->productRepository->findById($id);

        if (! $product) {
            abort(404, 'Product not found');
        }

        // Update the product's cost price
        $product->update([
            'PRICEBUY' => $request->cost_price,
        ]);

        return redirect()
            ->route('products.show', $id)
            ->with('success', 'Cost updated successfully.');
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
     * Show the form for creating a new product.
     */
    public function create(Request $request): View
    {
        // Get necessary data for the form
        $taxCategories = TaxCategory::orderBy('NAME')->get();
        $categories = Category::orderBy('NAME')->get();
        $suppliers = Supplier::orderBy('Supplier')->get();

        // Check if we're creating from a delivery item
        $deliveryItemId = $request->query('delivery_item');
        $prefillData = null;

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
                        if (isset($scrapedData['description']) && !empty($scrapedData['description'])) {
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
        }

        return view('products.create', compact('taxCategories', 'categories', 'suppliers', 'prefillData', 'deliveryItemId'));
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

                // Create the product with essential fields only
                $product = Product::create([
                    'ID' => $productId,
                    'NAME' => $request->name,
                    'CODE' => $request->code,
                    'REFERENCE' => $request->code, // Set reference same as barcode (CODE)
                    'CATEGORY' => $request->category,
                    'PRICEBUY' => $request->price_buy,
                    'PRICESELL' => $request->price_sell,
                    'TAXCAT' => $request->tax_category,
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

                // If this product was created from a delivery item, link them
                if ($request->delivery_item_id) {
                    $deliveryItem = \App\Models\DeliveryItem::findOrFail($request->delivery_item_id);
                    $deliveryItem->update([
                        'product_id' => $product->ID,
                        'is_new_product' => false,
                        'barcode' => $product->CODE,
                    ]);
                }
            });

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
    public function printLabel(string $id)
    {
        $product = $this->productRepository->findById($id);

        if (! $product) {
            abort(404, 'Product not found');
        }

        // Log the label print event
        LabelLog::logLabelPrint($product->CODE);

        // Generate the label HTML
        $labelHtml = $this->labelService->generateLabelHtml($product);

        // Return the label HTML with appropriate headers for printing
        return response($labelHtml)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
}
