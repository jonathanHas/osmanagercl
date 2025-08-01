<?php

namespace App\Services;

use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Models\Product;
use App\Models\ProductOrderSetting;
use App\Models\StockCurrent;
use App\Repositories\SalesRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class OrderService
{
    protected SalesRepository $salesRepository;

    public function __construct(SalesRepository $salesRepository)
    {
        $this->salesRepository = $salesRepository;
    }

    /**
     * Generate order suggestions for a supplier.
     */
    public function generateOrderSuggestions(string $supplierId, Carbon $orderDate): OrderSession
    {
        // Create new order session
        $orderSession = OrderSession::create([
            'user_id' => Auth::id(),
            'supplier_id' => $supplierId,
            'order_date' => $orderDate,
            'status' => 'draft',
        ]);

        // Get all products for this supplier
        $products = $this->getSupplierProducts($supplierId);

        $orderItems = [];
        $processedCount = 0;

        foreach ($products as $product) {
            try {
                $suggestion = $this->calculateProductSuggestion($product);

                if ($suggestion['suggested_quantity'] > 0 || $suggestion['force_include']) {
                    $orderItems[] = [
                        'order_session_id' => $orderSession->id,
                        'product_id' => $product->ID,
                        'suggested_quantity' => $suggestion['suggested_quantity'],
                        'final_quantity' => $suggestion['suggested_quantity'],
                        'case_units' => $suggestion['case_units'],
                        'suggested_cases' => $suggestion['suggested_cases'],
                        'final_cases' => $suggestion['suggested_cases'],
                        'unit_cost' => $suggestion['unit_cost'],
                        'total_cost' => $suggestion['suggested_quantity'] * $suggestion['unit_cost'],
                        'review_priority' => $suggestion['review_priority'],
                        'auto_approved' => $suggestion['auto_approved'] ? 1 : 0,
                        'context_data' => json_encode($suggestion['context_data']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            } catch (\Exception $e) {
                // Log the error but continue processing other products
                \Log::warning("Failed to calculate suggestion for product {$product->ID}: ".$e->getMessage());

                continue;
            }

            $processedCount++;

            // Process in batches to avoid memory issues
            if (count($orderItems) >= 100) {
                OrderItem::insert($orderItems);
                $orderItems = [];
            }
        }

        // Insert remaining items
        if (! empty($orderItems)) {
            OrderItem::insert($orderItems);
        }

        // Update totals
        $orderSession->updateTotals();

        // Sort order items by suggested quantity (desc), then by total sales (desc)
        $orderSession = $orderSession->fresh(['items.product']);
        $sortedItems = $orderSession->items->sortByDesc(function ($item) {
            $salesTotal = $item->context_data['total_sales_6m'] ?? 0;

            // Primary sort: suggested quantity, Secondary sort: total sales
            return ($item->suggested_quantity * 10000) + $salesTotal;
        });

        // Update the collection
        $orderSession->setRelation('items', $sortedItems);

        return $orderSession;
    }

    /**
     * Calculate suggestion for a single product.
     */
    public function calculateProductSuggestion(Product $product): array
    {
        // Get product settings
        $settings = ProductOrderSetting::where('product_id', $product->ID)->first();
        $safetyFactor = $settings?->safety_stock_factor ?? 1.5;

        // Get sales data (4-week average)
        $salesStats = $this->salesRepository->getProductSalesStatistics($product->ID);
        $avgWeeklySales = $salesStats['avg_monthly_sales'] / 4.33; // Convert monthly to weekly

        // Get current stock
        $currentStock = $this->getCurrentStock($product->ID);

        // Base calculation: (Weekly Average × Safety Factor) - Current Stock
        $baseQuantity = max(0, ($avgWeeklySales * $safetyFactor) - $currentStock);

        // Apply learned adjustments
        $adjustedQuantity = $this->applyLearningAdjustments($product->ID, $baseQuantity);

        // Get supplier link for this product to access CaseUnits
        $supplierLink = $product->supplierLinks->first();
        $caseUnits = $supplierLink?->CaseUnits ?? 1;

        // Calculate case quantities
        $suggestedCases = $caseUnits > 1 ? ceil($adjustedQuantity / $caseUnits) : $adjustedQuantity;
        $finalUnitsAfterCaseRounding = $caseUnits > 1 ? $suggestedCases * $caseUnits : $adjustedQuantity;

        // Determine review priority
        $reviewPriority = $this->determineReviewPriority($product, $settings);

        // Get unit cost from multiple possible sources (prioritize purchase costs for ordering)
        // Note: PRICEBUY appears to be the purchase price per case/ordering unit, not per individual unit
        $unitCost = $product->PRICEBUY  // Primary: Purchase price per ordering unit (case)
                 ?? $supplierLink?->Cost // Secondary: Supplier-specific cost
                 ?? $product->SELLPRICE  // Tertiary: Retail price (least preferred)
                 ?? 0;

        // If cost is still 0, try to estimate from recent purchase data
        if ($unitCost == 0) {
            $recentPurchasePrice = $this->getRecentPurchasePrice($product->ID);
            $unitCost = $recentPurchasePrice ?? 0;
        }

        // Get more detailed sales data
        $salesHistory = $this->salesRepository->getProductSalesHistory($product->ID, 6);
        $totalSales6m = array_sum(array_column($salesHistory, 'units'));
        $lastSaleDate = $this->getLastSaleDate($product->ID);

        return [
            'suggested_quantity' => round($finalUnitsAfterCaseRounding, 3),
            'suggested_cases' => round($suggestedCases, 3),
            'case_units' => $caseUnits,
            'unit_cost' => $unitCost,
            'review_priority' => $reviewPriority,
            'auto_approved' => $settings?->auto_approve ?? false,
            'force_include' => $baseQuantity > 0 || $avgWeeklySales > 0,
            'context_data' => [
                'avg_weekly_sales' => round($avgWeeklySales, 2),
                'current_stock' => $currentStock,
                'safety_factor' => $safetyFactor,
                'base_calculation' => round($baseQuantity, 3),
                'adjusted_calculation' => round($adjustedQuantity, 3),
                'case_units' => $caseUnits,
                'is_case_product' => $caseUnits > 1,
                'sales_trend' => $salesStats['trend'],
                'last_month_sales' => $salesStats['last_month_sales'],
                'total_sales_6m' => $totalSales6m,
                'sales_history' => $salesHistory,
                'last_sale_date' => $lastSaleDate,
                'stock_days_remaining' => $avgWeeklySales > 0 ? round(($currentStock / $avgWeeklySales) * 7, 1) : 999,
                'cost_source' => $this->getCostSource($supplierLink, $product, $unitCost),
                'cost_per_ordering_unit' => $unitCost,
                'units_per_case' => $caseUnits,
                'has_cost_data' => $unitCost > 0,
            ],
        ];
    }

    /**
     * Apply learning adjustments based on historical user modifications.
     */
    protected function applyLearningAdjustments(string $productId, float $baseQuantity): float
    {
        // Get recent adjustments for this product
        $recentAdjustments = OrderAdjustment::where('product_id', $productId)
            ->where('user_id', Auth::id())
            ->where('order_date', '>=', Carbon::now()->subMonths(3))
            ->get();

        if ($recentAdjustments->isEmpty()) {
            return $baseQuantity;
        }

        // Calculate average adjustment factor
        $avgAdjustmentFactor = $recentAdjustments->avg('adjustment_factor');

        // Apply conservative learning (don't adjust too dramatically)
        $learningFactor = 0.7; // Use 70% of learned pattern
        $finalFactor = 1 + (($avgAdjustmentFactor - 1) * $learningFactor);

        return $baseQuantity * $finalFactor;
    }

    /**
     * Determine review priority for a product.
     */
    protected function determineReviewPriority(Product $product, ?ProductOrderSetting $settings): string
    {
        // Use manual setting if available
        if ($settings && $settings->review_priority) {
            return $settings->review_priority;
        }

        // Auto-classify based on product characteristics
        $shelfLifeDays = $settings?->shelf_life_days;
        $isCase = ($product->supplier?->CASEUNITS ?? 1) > 1;

        // High priority items (require careful review)
        if ($shelfLifeDays && $shelfLifeDays < 7) {
            return 'review'; // Short shelf life
        }

        if ($product->SELLPRICE > 50) {
            return 'review'; // High value items
        }

        // Safe items (long shelf life cases with stable sales)
        if ($isCase && (! $shelfLifeDays || $shelfLifeDays > 30)) {
            $salesVariance = $this->calculateSalesVariance($product->ID);
            if ($salesVariance < 0.3) { // Low variance in sales
                return 'safe';
            }
        }

        return 'standard';
    }

    /**
     * Get current stock for a product.
     */
    protected function getCurrentStock(string $productId): float
    {
        $stockRecord = StockCurrent::where('PRODUCT', $productId)->first();

        return $stockRecord?->UNITS ?? 0;
    }

    /**
     * Get products for a supplier.
     */
    protected function getSupplierProducts(string $supplierId): Collection
    {
        $sixMonthsAgo = Carbon::now()->subMonths(6);

        return Product::whereHas('supplierLinks', function ($query) use ($supplierId) {
            $query->where('SupplierID', $supplierId);
        })
            ->whereHas('stocking') // Only include products that are stocked
            ->whereHas('stockDiary', function ($query) use ($sixMonthsAgo) {
                // Only include products with sales in last 6 months
                $query->where('REASON', -1) // Sales transactions
                    ->where('DATENEW', '>=', $sixMonthsAgo);
            })
            ->with([
                'supplierLinks' => function ($query) use ($supplierId) {
                    $query->where('SupplierID', $supplierId);
                },
                'stockCurrent',
                'stocking',
            ])
            ->get();
    }

    /**
     * Calculate sales variance for a product.
     */
    protected function calculateSalesVariance(string $productId): float
    {
        $salesHistory = $this->salesRepository->getProductSalesHistory($productId, 4);

        if (count($salesHistory) < 2) {
            return 1.0; // High variance for insufficient data
        }

        $sales = array_column($salesHistory, 'units');
        $mean = array_sum($sales) / count($sales);

        if ($mean == 0) {
            return 1.0;
        }

        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $sales)) / count($sales);

        return sqrt($variance) / $mean; // Coefficient of variation
    }

    /**
     * Update order item quantity and track adjustment.
     */
    public function updateOrderItemQuantity(OrderItem $orderItem, float $newQuantity, ?string $reason = null): OrderItem
    {
        $originalQuantity = $orderItem->final_quantity;

        // If this is a case product, calculate the case quantity from the unit quantity
        if ($orderItem->case_units > 1) {
            $newCases = ceil($newQuantity / $orderItem->case_units);
            $actualQuantity = $newCases * $orderItem->case_units;

            $orderItem->update([
                'final_cases' => $newCases,
                'final_quantity' => $actualQuantity,
                'total_cost' => $actualQuantity * $orderItem->unit_cost,
                'adjustment_reason' => $reason,
            ]);
        } else {
            // For unit products, update normally
            $orderItem->update([
                'final_cases' => $newQuantity,
                'final_quantity' => $newQuantity,
                'total_cost' => $newQuantity * $orderItem->unit_cost,
                'adjustment_reason' => $reason,
            ]);
        }

        // Track adjustment for learning
        if (abs($orderItem->final_quantity - $orderItem->suggested_quantity) > 0.001) {
            OrderAdjustment::create([
                'product_id' => $orderItem->product_id,
                'user_id' => Auth::id(),
                'original_quantity' => $orderItem->suggested_quantity,
                'adjusted_quantity' => $orderItem->final_quantity,
                'adjustment_factor' => $orderItem->final_quantity / max(0.001, $orderItem->suggested_quantity),
                'context_data' => $orderItem->context_data,
                'order_date' => $orderItem->orderSession->order_date,
                'reason' => $reason,
            ]);
        }

        // Update session totals
        $orderItem->orderSession->updateTotals();

        return $orderItem->fresh();
    }

    /**
     * Update order item by case quantity (for case products).
     */
    public function updateOrderItemCases(OrderItem $orderItem, float $newCases, ?string $reason = null): OrderItem
    {
        if ($orderItem->case_units <= 1) {
            // For unit products, treat cases as units
            return $this->updateOrderItemQuantity($orderItem, $newCases, $reason);
        }

        $newQuantity = $newCases * $orderItem->case_units;

        $orderItem->update([
            'final_cases' => $newCases,
            'final_quantity' => $newQuantity,
            'total_cost' => $newQuantity * $orderItem->unit_cost,
            'adjustment_reason' => $reason,
        ]);

        // Track adjustment for learning
        if (abs($orderItem->final_quantity - $orderItem->suggested_quantity) > 0.001) {
            OrderAdjustment::create([
                'product_id' => $orderItem->product_id,
                'user_id' => Auth::id(),
                'original_quantity' => $orderItem->suggested_quantity,
                'adjusted_quantity' => $orderItem->final_quantity,
                'adjustment_factor' => $orderItem->final_quantity / max(0.001, $orderItem->suggested_quantity),
                'context_data' => $orderItem->context_data,
                'order_date' => $orderItem->orderSession->order_date,
                'reason' => $reason,
            ]);
        }

        // Update session totals
        $orderItem->orderSession->updateTotals();

        return $orderItem->fresh();
    }

    /**
     * Update order item cost and recalculate totals.
     */
    public function updateOrderItemCost(OrderItem $orderItem, float $newCost): OrderItem
    {
        $orderItem->update([
            'unit_cost' => $newCost,
            'total_cost' => $orderItem->final_quantity * $newCost,
        ]);

        // Update session totals
        $orderItem->orderSession->updateTotals();

        return $orderItem->fresh();
    }

    /**
     * Export order session to CSV format.
     */
    public function exportToCsv(OrderSession $orderSession): string
    {
        $items = $orderSession->items()
            ->with('product')
            ->where('final_quantity', '>', 0)
            ->get();

        $csv = "Code,Ordered,Cases,Units,SKU,Content,Description,Price,Sale,Total\n";

        foreach ($items as $item) {
            $product = $item->product;
            $supplierLink = $product->supplierLinks
                ->where('SupplierID', $orderSession->supplier_id)
                ->first();

            $code = $supplierLink?->SupplierCode ?? $product->CODE;
            $description = $product->NAME;

            // For case products, show case quantity; for unit products, show unit quantity
            $orderQuantity = $item->case_units > 1 ? $item->final_cases : $item->final_quantity;
            $caseDisplay = $item->case_units > 1 ? $item->final_cases : '';
            $unitDisplay = $item->final_quantity;

            $unitPrice = $item->unit_cost;
            $total = $item->total_cost;

            // Content description shows case information
            $content = $item->case_units > 1
                ? "Case of {$item->case_units}"
                : ($product->PACKAGE_SIZE ?? '1 unit');

            $csv .= sprintf(
                "%s,%d,%.3f,%.3f,%s,\"%s\",\"%s\",%.2f,%.2f,%.2f\n",
                $code,
                1, // Ordered (always 1 for simplicity)
                $caseDisplay,
                $unitDisplay,
                $product->ID,
                $content,
                $description,
                $unitPrice,
                $unitPrice, // Sale price (same as unit price for now)
                $total
            );
        }

        return $csv;
    }

    /**
     * Complete an order session.
     */
    public function completeOrderSession(OrderSession $orderSession): OrderSession
    {
        $orderSession->update(['status' => 'completed']);

        // Here you could add integration with external systems
        // such as submitting to supplier APIs, updating forecasts, etc.

        return $orderSession;
    }

    /**
     * Get last sale date for a product.
     */
    protected function getLastSaleDate(string $productId): ?string
    {
        $lastSale = \DB::connection('pos')
            ->table('STOCKDIARY')
            ->where('PRODUCT', $productId)
            ->where('REASON', -1) // Sales transactions
            ->orderBy('DATENEW', 'desc')
            ->first();

        return $lastSale ? Carbon::parse($lastSale->DATENEW)->format('Y-m-d') : null;
    }

    /**
     * Update product review priority setting.
     */
    public function updateProductPriority(string $productId, string $priority): ProductOrderSetting
    {
        return ProductOrderSetting::updateOrCreate(
            ['product_id' => $productId],
            ['review_priority' => $priority]
        );
    }

    /**
     * Get recent purchase price from stock diary.
     */
    protected function getRecentPurchasePrice(string $productId): ?float
    {
        // Look for recent stock entries with positive REASON (purchases/deliveries)
        // and get the most recent PRICE
        $recentPurchase = \DB::connection('pos')
            ->table('STOCKDIARY')
            ->where('PRODUCT', $productId)
            ->where('REASON', '>', 0) // Positive reasons are typically purchases/stock increases
            ->where('PRICE', '>', 0) // Only entries with a valid price
            ->orderBy('DATENEW', 'desc')
            ->first();

        return $recentPurchase?->PRICE;
    }

    /**
     * Determine the source of cost data for debugging.
     */
    protected function getCostSource($supplierLink, $product, float $unitCost): string
    {
        if ($product->PRICEBUY > 0) {
            return 'purchase_price'; // Primary: Purchase price per unit
        }

        if ($supplierLink?->Cost > 0) {
            return 'supplier_link'; // Secondary: Supplier-specific cost
        }

        if ($product->SELLPRICE > 0) {
            return 'retail_price'; // Tertiary: Retail price (not ideal for ordering)
        }

        if ($unitCost > 0) {
            return 'recent_purchase'; // Fallback: From purchase history
        }

        return 'no_cost_data';
    }

    /**
     * Get order statistics.
     */
    public function getOrderStatistics(OrderSession $orderSession): array
    {
        $items = $orderSession->items;

        return [
            'total_items' => $items->count(),
            'review_items' => $items->where('review_priority', 'review')->count(),
            'safe_items' => $items->where('review_priority', 'safe')->count(),
            'standard_items' => $items->where('review_priority', 'standard')->count(),
            'auto_approved_items' => $items->where('auto_approved', true)->count(),
            'adjusted_items' => $items->filter(fn ($item) => $item->wasAdjusted())->count(),
            'total_value' => $orderSession->total_value,
            'avg_item_value' => $items->count() > 0 ? $orderSession->total_value / $items->count() : 0,
        ];
    }
}
