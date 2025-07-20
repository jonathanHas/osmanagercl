<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Repositories\SalesRepository;
use App\Services\SupplierService;
use App\Services\UdeaScrapingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * The product repository instance.
     */
    protected ProductRepository $productRepository;

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
     * Create a new controller instance.
     */
    public function __construct(
        ProductRepository $productRepository, 
        SalesRepository $salesRepository,
        SupplierService $supplierService,
        UdeaScrapingService $udeaScrapingService
    ) {
        $this->productRepository = $productRepository;
        $this->salesRepository = $salesRepository;
        $this->supplierService = $supplierService;
        $this->udeaScrapingService = $udeaScrapingService;
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
        $showSuppliers = $request->boolean('show_suppliers');
        $perPage = $request->get('per_page', 20);

        // Get suppliers for dropdown if needed, filtered by current search criteria
        $suppliers = $showSuppliers ? $this->productRepository->getAllSuppliersWithProducts(
            stockedOnly: $stockedOnly,
            inStockOnly: $inStockOnly,
            activeOnly: $activeOnly
        ) : collect();

        if ($search || $activeOnly || $stockedOnly || $inStockOnly || $supplierId) {
            $products = $this->productRepository->searchProducts(
                search: $search,
                activeOnly: $activeOnly,
                stockedOnly: $stockedOnly,
                inStockOnly: $inStockOnly,
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
            'showSuppliers' => $showSuppliers,
            'suppliers' => $suppliers,
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
                    'error' => $e->getMessage()
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
        $months = match($period) {
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
            'net_price' => 'required|numeric|min:0|max:999999.99',
        ]);

        $product = $this->productRepository->findById($id);

        if (! $product) {
            abort(404, 'Product not found');
        }

        // Update the product's net price (PRICESELL is stored without VAT)
        $product->update([
            'PRICESELL' => $request->net_price,
        ]);

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

        if (!$product->supplierLink?->SupplierCode || 
            !$product->supplier || 
            !$this->supplierService->hasExternalIntegration($product->supplier->SupplierID)) {
            return response()->json(['error' => 'Product does not have Udea supplier integration'], 400);
        }

        try {
            // Clear cache for this product to force fresh data
            $this->udeaScrapingService->clearCache($product->supplierLink->SupplierCode);
            
            // Fetch fresh pricing data
            $udeaPricing = $this->udeaScrapingService->getProductData($product->supplierLink->SupplierCode);

            if (!$udeaPricing) {
                return response()->json(['error' => 'Unable to fetch Udea pricing'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $udeaPricing,
                'product' => [
                    'current_price' => $product->PRICESELL,
                    'current_price_with_vat' => $product->PRICESELL * (1 + $product->getVatRate()),
                    'supplier_code' => $product->supplierLink->SupplierCode,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to refresh Udea pricing', [
                'product_id' => $id,
                'supplier_code' => $product->supplierLink->SupplierCode,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to fetch Udea pricing: ' . $e->getMessage()], 500);
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
}
