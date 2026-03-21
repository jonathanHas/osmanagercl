<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockLastChecked;
use App\Models\StockZeroAudit;
use App\Services\StockCheckReviewService;
use App\Services\SupplierService;
use Illuminate\Http\Request;

class StockCheckReviewController extends Controller
{
    public function __construct(
        protected StockCheckReviewService $reviewService,
    ) {}

    /**
     * Show the stock check review page.
     */
    public function index(Request $request)
    {
        $categories = Category::withCount('products')
            ->has('products')
            ->orderBy('NAME')
            ->get();

        $reviewData = null;
        $categoryId = $request->get('category');
        $referenceDate = $request->get('reference_date', now()->toDateString());
        $filter = $request->get('filter', 'all');
        $sortBy = $request->get('sort', 'name');

        if ($categoryId) {
            $reviewData = $this->reviewService->getReviewData(
                $categoryId,
                $referenceDate,
                $filter === 'stocked',
                $sortBy,
            );
        }

        return view('stock-review.index', [
            'categories' => $categories,
            'reviewData' => $reviewData,
            'selectedCategory' => $categoryId,
            'referenceDate' => $referenceDate,
            'filter' => $filter,
            'sortBy' => $sortBy,
        ]);
    }

    /**
     * Execute the set-to-zero operation.
     */
    public function setToZero(Request $request)
    {
        $request->validate([
            'category' => 'required|string',
            'reference_date' => 'required|date',
            'filter' => 'in:all,stocked',
        ]);

        $audit = $this->reviewService->setUncheckedToZero(
            $request->category,
            $request->reference_date,
            $request->user()->id,
            $request->filter === 'stocked',
        );

        return redirect()
            ->route('stock-review.index', [
                'category' => $request->category,
                'reference_date' => $request->reference_date,
                'filter' => $request->filter,
            ])
            ->with('success', "Stock zeroed for {$audit->products_zeroed} products (value: €".number_format($audit->total_stock_value_zeroed, 2).')');
    }

    /**
     * Get sales history data via AJAX.
     */
    public function salesData(Request $request)
    {
        $request->validate([
            'category' => 'required|string',
        ]);

        $salesData = $this->reviewService->getSalesHistory($request->category);

        return response()->json($salesData);
    }

    /**
     * Stock check a product by barcode - lookup and mark as checked.
     */
    public function stockCheck(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
            'stock_count' => 'nullable|numeric|min:0|max:9999.99',
        ]);

        $product = Product::with(['stockCurrent', 'category', 'supplier'])
            ->where('CODE', $request->barcode)
            ->first();

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $currentStock = $product->getCurrentStock();

        // Update the stockLastChecked record (upsert)
        StockLastChecked::updateOrCreate(
            ['Barcode' => $product->CODE],
            ['Date' => now()]
        );

        // If a stock count was provided, update stock
        $stockUpdated = false;
        if ($request->has('stock_count') && $request->stock_count !== null) {
            $newStock = (float) $request->stock_count;
            if ($newStock != $currentStock) {
                \App\Models\StockCurrent::updateOrCreate(
                    ['PRODUCT' => $product->ID],
                    ['UNITS' => $newStock, 'LOCATION' => '0', 'ATTRIBUTESETINSTANCE_ID' => null]
                );

                \App\Models\StockAdjustment::create([
                    'barcode' => $product->CODE,
                    'product_id' => $product->ID,
                    'old_stock' => $currentStock,
                    'new_stock' => $newStock,
                    'adjustment' => $newStock - $currentStock,
                    'user_id' => auth()->id(),
                    'source' => 'stock_check',
                ]);

                $stockUpdated = true;
                $currentStock = $newStock;
            }
        }

        // Get image URL
        $imageUrl = null;
        if ($product->hasImage()) {
            $imageUrl = route('products.image', $product->ID);
        } else {
            $supplierService = app(SupplierService::class);
            $imageUrl = $supplierService->getExternalImageUrl($product);
        }

        return response()->json([
            'success' => true,
            'message' => $stockUpdated ? 'Stock checked & updated' : 'Stock checked',
            'product' => [
                'name' => $product->NAME,
                'code' => $product->CODE,
                'category' => $product->category?->NAME ?? 'Uncategorized',
                'supplier' => $product->supplier?->Supplier ?? '',
                'image_url' => $imageUrl,
            ],
            'stock' => $currentStock,
            'stock_updated' => $stockUpdated,
        ]);
    }

    /**
     * Show the audit log of set-to-zero operations.
     */
    public function auditLog(Request $request)
    {
        $audits = StockZeroAudit::with('user')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('stock-review.audit-log', [
            'audits' => $audits,
        ]);
    }
}
