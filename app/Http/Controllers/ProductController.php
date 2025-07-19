<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Repositories\SalesRepository;
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
     * Create a new controller instance.
     */
    public function __construct(ProductRepository $productRepository, SalesRepository $salesRepository)
    {
        $this->productRepository = $productRepository;
        $this->salesRepository = $salesRepository;
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
        $perPage = $request->get('per_page', 20);

        if ($search || $activeOnly || $stockedOnly || $inStockOnly) {
            $products = $this->productRepository->searchProducts(
                search: $search,
                activeOnly: $activeOnly,
                stockedOnly: $stockedOnly,
                inStockOnly: $inStockOnly,
                perPage: $perPage
            );
        } else {
            $products = $this->productRepository->getAllProducts($perPage);
        }

        // Only calculate statistics when requested
        $statistics = $showStats ? $this->productRepository->getStatistics() : null;

        return view('products.index', compact('products', 'statistics', 'search', 'activeOnly', 'stockedOnly', 'inStockOnly', 'showStats'));
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

        return view('products.show', compact('product', 'taxCategories', 'salesHistory', 'salesStats'));
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
