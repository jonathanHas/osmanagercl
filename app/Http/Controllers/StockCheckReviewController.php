<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\StockZeroAudit;
use App\Services\StockCheckReviewService;
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
