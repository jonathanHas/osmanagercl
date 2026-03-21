<?php

namespace App\Services;

use App\Models\Category;
use App\Models\StockAdjustment;
use App\Models\StockCurrent;
use App\Models\StockZeroAudit;
use App\Repositories\ProductRepository;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockCheckReviewService
{
    public function __construct(
        protected ProductRepository $productRepository,
        protected SupplierService $supplierService,
    ) {}

    /**
     * Get all products in a category with their review status.
     */
    public function getReviewData(
        string $categoryId,
        string $referenceDate,
        bool $stockedOnly = false,
        string $sortBy = 'name',
    ): array {
        $refDate = Carbon::parse($referenceDate)->startOfDay();
        $products = $this->productRepository->getProductsForStockReview($categoryId, $stockedOnly);

        // Enrich products with status and computed values
        $enriched = $products->map(function ($product) use ($refDate) {
            $stock = $product->getCurrentStock();
            $status = $this->determineStatus($product, $refDate);
            $costValue = (float) $product->PRICEBUY * $stock;

            return (object) [
                'product' => $product,
                'stock' => $stock,
                'status' => $status,
                'cost_value' => $costValue,
                'checked_date' => $product->stockLastChecked?->Date,
                'is_stocked' => $product->stocking !== null,
                'supplier_name' => $product->supplier?->Supplier ?? '',
                'image_url' => $this->getProductImageUrl($product),
            ];
        });

        // Sort — always group needs-attention items first (danger, warning), then ok, then verified
        $statusOrder = ['danger' => 0, 'warning' => 1, 'ok' => 2, 'verified' => 3];

        $enriched = $enriched->sortBy([
            fn ($a, $b) => ($statusOrder[$a->status] ?? 9) <=> ($statusOrder[$b->status] ?? 9),
            fn ($a, $b) => match ($sortBy) {
                'checked_asc' => ($a->checked_date ?? Carbon::createFromTimestamp(0)) <=> ($b->checked_date ?? Carbon::createFromTimestamp(0)),
                'checked_desc' => ($b->checked_date ?? Carbon::createFromTimestamp(0)) <=> ($a->checked_date ?? Carbon::createFromTimestamp(0)),
                default => strcmp($a->product->NAME, $b->product->NAME),
            },
        ]);

        $summary = $this->computeSummary($enriched, $refDate);

        return [
            'products' => $enriched->values(),
            'summary' => $summary,
        ];
    }

    /**
     * Determine the review status of a product.
     */
    public function determineStatus($product, Carbon $referenceDate): string
    {
        $stock = $product->getCurrentStock();
        $lastChecked = $product->stockLastChecked?->Date;

        // If checked on or after reference date, it's verified
        if ($lastChecked && $lastChecked->startOfDay()->gte($referenceDate)) {
            return 'verified';
        }

        // Not checked (or checked before reference date)
        if ($stock > 0) {
            return 'danger';
        }

        if ($stock < 0) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * Compute summary statistics for the review.
     */
    public function computeSummary(Collection $products, Carbon $referenceDate): array
    {
        $total = $products->count();
        $checked = $products->where('status', 'verified')->count();
        $uncheckedWithStock = $products->where('status', 'danger')->count();
        $negativeStock = $products->where('status', 'warning')->count();
        $totalStockValue = $products->sum('cost_value');
        $atRiskValue = $products->whereIn('status', ['danger', 'warning'])->sum('cost_value');

        return [
            'total_products' => $total,
            'checked_count' => $checked,
            'unchecked_count' => $total - $checked,
            'unchecked_with_stock' => $uncheckedWithStock,
            'negative_stock_count' => $negativeStock,
            'total_stock_value' => round($totalStockValue, 2),
            'at_risk_value' => round($atRiskValue, 2),
            'progress_percentage' => $total > 0 ? round(($checked / $total) * 100) : 0,
        ];
    }

    /**
     * Zero out stock for all unchecked products in a category.
     */
    public function setUncheckedToZero(
        string $categoryId,
        string $referenceDate,
        int $userId,
        bool $stockedOnly = false,
    ): StockZeroAudit {
        $refDate = Carbon::parse($referenceDate)->startOfDay();
        $products = $this->productRepository->getProductsForStockReview($categoryId, $stockedOnly);
        $category = Category::find($categoryId);

        $affectedProducts = [];
        $totalValueZeroed = 0;

        foreach ($products as $product) {
            $status = $this->determineStatus($product, $refDate);

            // Only zero products that are not verified (checked since reference date)
            // and have non-zero stock
            if ($status === 'verified') {
                continue;
            }

            $currentStock = $product->getCurrentStock();
            if ($currentStock == 0) {
                continue;
            }

            $costValue = (float) $product->PRICEBUY * $currentStock;

            // Update STOCKCURRENT on POS database
            StockCurrent::where('PRODUCT', $product->ID)
                ->update(['UNITS' => 0]);

            // Create individual stock adjustment audit record
            StockAdjustment::create([
                'barcode' => $product->CODE,
                'product_id' => $product->ID,
                'old_stock' => $currentStock,
                'new_stock' => 0,
                'adjustment' => -$currentStock,
                'user_id' => $userId,
                'source' => 'stock_review_zero',
            ]);

            $affectedProducts[] = [
                'barcode' => $product->CODE,
                'name' => $product->NAME,
                'old_stock' => $currentStock,
                'cost_value' => round($costValue, 2),
            ];

            $totalValueZeroed += $costValue;
        }

        // Write to POS catSetZero table (backward compatibility with old system)
        DB::connection('pos')->table('catSetZero')->insert([
            'ID' => (string) Str::uuid(),
            'catID' => $categoryId,
            'dateUpdated' => now(),
        ]);

        // Create the summary audit record
        return StockZeroAudit::create([
            'category_id' => $categoryId,
            'category_name' => $category?->NAME ?? 'Unknown',
            'reference_date' => $refDate->toDateString(),
            'products_zeroed' => count($affectedProducts),
            'total_stock_value_zeroed' => round($totalValueZeroed, 2),
            'product_details' => $affectedProducts,
            'user_id' => $userId,
        ]);
    }

    /**
     * Get sales history for products in a category (last 5 months).
     */
    public function getSalesHistory(string $categoryId): array
    {
        $now = Carbon::now();
        $months = [];

        for ($i = 0; $i < 5; $i++) {
            $monthDate = $now->copy()->subMonths($i);
            $months[] = [
                'key' => $monthDate->format('Y-m'),
                'label' => $monthDate->format('M Y'),
                'start' => $monthDate->copy()->startOfMonth(),
                'end' => $monthDate->copy()->endOfMonth(),
            ];
        }

        // Get all sales for products in this category for the last 5 months
        $sales = DB::connection('pos')
            ->table('STOCKDIARY')
            ->join('PRODUCTS', 'STOCKDIARY.PRODUCT', '=', 'PRODUCTS.ID')
            ->where('PRODUCTS.CATEGORY', $categoryId)
            ->where('STOCKDIARY.REASON', -1)
            ->where('STOCKDIARY.DATENEW', '>=', $months[4]['start'])
            ->select(
                'PRODUCTS.CODE as barcode',
                DB::raw("DATE_FORMAT(STOCKDIARY.DATENEW, '%Y-%m') as month_key"),
                DB::raw('SUM(ABS(STOCKDIARY.UNITS)) as total_units')
            )
            ->groupBy('barcode', 'month_key')
            ->get();

        // Organize by barcode
        $salesByBarcode = [];
        foreach ($sales as $sale) {
            $salesByBarcode[$sale->barcode][$sale->month_key] = (float) $sale->total_units;
        }

        return [
            'months' => array_reverse($months),
            'sales' => $salesByBarcode,
        ];
    }

    /**
     * Get the image URL for a product (DB image or supplier CDN).
     */
    protected function getProductImageUrl($product): ?string
    {
        if ($product->has_image) {
            return route('products.image', $product->ID);
        }

        return $this->supplierService->getExternalImageUrl($product);
    }
}
