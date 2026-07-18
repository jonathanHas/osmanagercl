<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockLastChecked;
use App\Models\StockReviewCategorySetting;
use App\Models\StockZeroAudit;
use App\Services\StockCheckReviewService;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $overview = null;
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
        } else {
            // Landing state: show the category overview (oldest checked first).
            $overview = $this->reviewService->getCategoryOverview($categories);
        }

        return view('stock-review.index', [
            'categories' => $categories,
            'reviewData' => $reviewData,
            'overview' => $overview,
            'canToggle' => $request->user()->hasAnyRole(['admin', 'manager']),
            'selectedCategory' => $categoryId,
            'referenceDate' => $referenceDate,
            'filter' => $filter,
            'sortBy' => $sortBy,
        ]);
    }

    /**
     * Toggle whether a category is included in or excluded from the review list.
     * Shared/global state; gated to managers and admins via route middleware.
     */
    public function toggleCategory(Request $request)
    {
        $request->validate([
            'category' => 'required|string',
        ]);

        $setting = StockReviewCategorySetting::firstOrNew(['category_id' => $request->category]);
        $setting->excluded = ! $setting->excluded;
        $setting->save();

        return response()->json([
            'category_id' => $setting->category_id,
            'excluded' => $setting->excluded,
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

    /**
     * Get combined set-to-zero history from old POS + new Laravel systems.
     */
    public function history(Request $request)
    {
        // Old system records from POS catSetZero table
        $oldRecords = DB::connection('pos')
            ->table('catSetZero')
            ->leftJoin('CATEGORIES', 'catSetZero.catID', '=', 'CATEGORIES.ID')
            ->select(
                'catSetZero.ID as id',
                'catSetZero.catID as category_id',
                'CATEGORIES.NAME as category_name',
                'catSetZero.dateUpdated as date'
            )
            ->orderByDesc('catSetZero.dateUpdated')
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'date' => $r->date,
                'category_name' => $r->category_name ?? 'Unknown',
                'source' => 'old',
                'products_zeroed' => null,
                'total_value' => null,
                'user' => null,
                'product_details' => null,
            ]);

        // New system records from Laravel stock_zero_audits table
        $newRecords = StockZeroAudit::with('user')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($a) => [
                'id' => 'new_'.$a->id,
                'date' => $a->created_at->toDateTimeString(),
                'category_name' => $a->category_name,
                'source' => 'new',
                'products_zeroed' => $a->products_zeroed,
                'total_value' => $a->total_stock_value_zeroed,
                'user' => $a->user?->name,
                'product_details' => $a->product_details,
            ]);

        // Merge and sort by date descending
        $combined = $oldRecords->concat($newRecords)
            ->sortByDesc('date')
            ->values()
            ->take(100);

        return response()->json($combined);
    }
}
