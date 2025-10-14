<?php

namespace App\Services;

use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Models\Product;
use App\Models\ProductOrderSetting;
use App\Models\StockCurrent;
use App\Repositories\SalesRepository;
use App\Support\SpecialOrderCategories;
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
    public function generateOrderSuggestions(string $supplierId, Carbon $orderDate, array $options = []): OrderSession
    {
        $coverageDays = max(1, (int) ($options['coverage_days'] ?? 7));
        $coverageWeeks = ($options['coverage_weeks'] ?? null) !== null
            ? max(0.1, (float) $options['coverage_weeks'])
            : $coverageDays / 7;
        $salesHistoryWeeks = max(1, min(26, (int) ($options['sales_history_weeks'] ?? 8)));
        $coverageEndsOn = $this->normaliseCoverageEnd($options['coverage_ends_on'] ?? null, $orderDate, $coverageDays);

        $categoryGroups = $options['category_groups'] ?? SpecialOrderCategories::forSupplier($supplierId);
        $categoryOverrides = $options['category_overrides'] ?? [];

        $orderSession = OrderSession::create([
            'user_id' => Auth::id(),
            'supplier_id' => $supplierId,
            'order_date' => $orderDate,
            'coverage_days' => $coverageDays,
            'coverage_ends_on' => $coverageEndsOn,
            'coverage_overrides' => $categoryOverrides,
            'sales_history_weeks' => $salesHistoryWeeks,
            'status' => 'draft',
        ]);

        return $this->buildOrderItemsForSession($orderSession, [
            'coverage_days' => $coverageDays,
            'coverage_weeks' => $coverageWeeks,
            'coverage_ends_on' => $coverageEndsOn,
            'sales_history_weeks' => $salesHistoryWeeks,
            'category_groups' => $categoryGroups,
            'category_overrides' => $categoryOverrides,
        ]);
    }

    /**
     * Recalculate an existing order session with updated configuration.
     */
    public function regenerateOrderSession(OrderSession $orderSession, array $options = []): OrderSession
    {
        if (! $orderSession->isEditable()) {
            throw new \RuntimeException('Only draft order sessions can be regenerated.');
        }

        $orderDate = $options['order_date'] ?? $orderSession->order_date ?? Carbon::now();
        if (! $orderDate instanceof Carbon) {
            $orderDate = Carbon::parse($orderDate);
        }

        $coverageDays = max(1, (int) ($options['coverage_days'] ?? $orderSession->coverage_days ?? 7));
        $coverageWeeks = ($options['coverage_weeks'] ?? null) !== null
            ? max(0.1, (float) $options['coverage_weeks'])
            : $coverageDays / 7;
        $salesHistoryWeeks = max(
            1,
            min(26, (int) ($options['sales_history_weeks'] ?? $orderSession->sales_history_weeks ?? 8))
        );
        $coverageEndsOn = $this->normaliseCoverageEnd(
            $options['coverage_ends_on'] ?? $orderSession->coverage_ends_on,
            $orderDate,
            $coverageDays
        );
        $categoryGroups = $options['category_groups'] ?? SpecialOrderCategories::forSupplier((string) $orderSession->supplier_id);
        $categoryOverrides = $options['category_overrides'] ?? ($orderSession->coverage_overrides ?? []);
        $rebuildGroupsOption = $options['rebuild_groups'] ?? null;
        $rebuildGroups = null;

        if (is_array($rebuildGroupsOption)) {
            $rebuildGroups = [];
            foreach ($rebuildGroupsOption as $groupKey) {
                if (is_string($groupKey) && array_key_exists($groupKey, $categoryGroups)) {
                    $rebuildGroups[] = $groupKey;
                }
            }
            $rebuildGroups = array_values(array_unique($rebuildGroups));
            if (empty($rebuildGroups)) {
                $rebuildGroups = null;
            }
        }

        if ($rebuildGroups === null) {
            $orderSession->items()->delete();
        } else {
            $itemsToRebuild = $orderSession->items()
                ->with('product')
                ->get()
                ->filter(function ($item) use ($categoryGroups, $rebuildGroups) {
                    $productCategory = $item->product->CATEGORY ?? null;
                    if ($productCategory === null) {
                        $contextGroup = $item->context_data['category_group_key'] ?? null;

                        return $contextGroup !== null && in_array($contextGroup, $rebuildGroups, true);
                    }

                    foreach ($rebuildGroups as $groupKey) {
                        $codes = $categoryGroups[$groupKey]['category_codes'] ?? [];
                        if (in_array($productCategory, $codes, true)) {
                            return true;
                        }
                    }

                    return false;
                })
                ->pluck('id');

            if ($itemsToRebuild->isNotEmpty()) {
                OrderItem::whereIn('id', $itemsToRebuild)->delete();
            }
        }

        $orderSession->update([
            'order_date' => $orderDate,
            'coverage_days' => $coverageDays,
            'coverage_ends_on' => $coverageEndsOn,
            'coverage_overrides' => $categoryOverrides,
            'sales_history_weeks' => $salesHistoryWeeks,
        ]);

        return $this->buildOrderItemsForSession($orderSession->fresh(), [
            'coverage_days' => $coverageDays,
            'coverage_weeks' => $coverageWeeks,
            'coverage_ends_on' => $coverageEndsOn,
            'sales_history_weeks' => $salesHistoryWeeks,
            'category_groups' => $categoryGroups,
            'category_overrides' => $categoryOverrides,
            'rebuild_groups' => $rebuildGroups,
        ]);
    }

    /**
     * Build order items for a session (initial generation or regeneration).
     *
     * @param  array<string, mixed>  $options
     */
    protected function buildOrderItemsForSession(OrderSession $orderSession, array $options): OrderSession
    {
        $coverageDays = (int) ($options['coverage_days'] ?? 7);
        $coverageWeeks = (float) ($options['coverage_weeks'] ?? ($coverageDays / 7));
        $coverageEndsOn = $options['coverage_ends_on'] ?? null;
        $salesHistoryWeeks = (int) ($options['sales_history_weeks'] ?? 8);
        $categoryGroups = $options['category_groups'] ?? [];
        $categoryOverrides = $options['category_overrides'] ?? [];
        $rebuildGroups = $options['rebuild_groups'] ?? null;
        if (is_array($rebuildGroups)) {
            $rebuildGroups = array_values(array_unique(array_filter($rebuildGroups, static fn ($value) => $value !== null)));
            if (empty($rebuildGroups)) {
                $rebuildGroups = null;
            }
        } else {
            $rebuildGroups = null;
        }

        $categoryMap = [];
        foreach ($categoryGroups as $groupKey => $definition) {
            foreach ($definition['category_codes'] ?? [] as $code) {
                $categoryMap[(string) $code] = $groupKey;
            }
        }

        $products = $this->getSupplierProducts($orderSession->supplier_id);
        $orderItems = [];

        foreach ($products as $product) {
            try {
                $groupKey = $categoryMap[$product->CATEGORY ?? ''] ?? null;

                if ($rebuildGroups !== null && (! $groupKey || ! in_array($groupKey, $rebuildGroups, true))) {
                    continue;
                }

                $override = $groupKey ? ($categoryOverrides[$groupKey] ?? null) : null;

                $productCoverageDays = max(1, (int) ($override['coverage_days'] ?? $coverageDays));
                $productCoverageWeeks = ($override['coverage_days'] ?? null) !== null
                    ? max(0.1, (float) ($productCoverageDays / 7))
                    : $coverageWeeks;
                $productCoverageEnd = $override['coverage_ends_on'] ?? $coverageEndsOn;
                if ($productCoverageEnd !== null && ! $productCoverageEnd instanceof Carbon) {
                    $productCoverageEnd = Carbon::parse($productCoverageEnd);
                }

                $suggestion = $this->calculateProductSuggestion($product, [
                    'coverage_days' => $productCoverageDays,
                    'coverage_weeks' => $productCoverageWeeks,
                    'sales_history_weeks' => $salesHistoryWeeks,
                    'category_group_key' => $groupKey,
                    'category_group_label' => $groupKey
                        ? ($categoryGroups[$groupKey]['label'] ?? ucfirst($groupKey))
                        : null,
                    'coverage_override' => $override,
                    'coverage_ends_on' => $productCoverageEnd,
                ]);

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
                \Log::warning("Failed to calculate suggestion for product {$product->ID}: ".$e->getMessage());
                continue;
            }

            if (count($orderItems) >= 100) {
                OrderItem::insert($orderItems);
                $orderItems = [];
            }
        }

        if (! empty($orderItems)) {
            OrderItem::insert($orderItems);
        }

        $orderSession->updateTotals();

        $orderSession = $orderSession->fresh(['items.product']);
        $sortedItems = $orderSession->items->sortByDesc(function ($item) {
            $salesTotal = $item->context_data['total_sales_6m'] ?? 0;

            return ($item->suggested_quantity * 10000) + $salesTotal;
        });

        $orderSession->setRelation('items', $sortedItems);

        return $orderSession;
    }

    /**
     * Normalise coverage end input into a Carbon instance.
     */
    protected function normaliseCoverageEnd(mixed $coverageEndsOn, Carbon $orderDate, int $coverageDays): Carbon
    {
        if ($coverageEndsOn instanceof Carbon) {
            return $coverageEndsOn->copy();
        }

        if ($coverageEndsOn instanceof \DateTimeInterface) {
            return Carbon::instance($coverageEndsOn);
        }

        if (is_string($coverageEndsOn)) {
            return Carbon::parse($coverageEndsOn);
        }

        return $orderDate->copy()->addDays(max(1, $coverageDays) - 1);
    }

    /**
     * Calculate suggestion for a single product.
     */
    public function calculateProductSuggestion(Product $product, array $options = []): array
    {
        // Get product settings
        $settings = ProductOrderSetting::where('product_id', $product->ID)->first();
        $safetyFactor = $settings?->safety_stock_factor ?? 1.5;
        $coverageDays = max(1, (int) ($options['coverage_days'] ?? 7));
        $coverageWeeks = ($options['coverage_weeks'] ?? null) !== null
            ? max(0.1, (float) $options['coverage_weeks'])
            : $coverageDays / 7;
        $salesHistoryWeeks = max(1, min(26, (int) ($options['sales_history_weeks'] ?? 8)));
        $targetWeeks = max($coverageWeeks, $safetyFactor);
        $categoryGroupKey = $options['category_group_key'] ?? null;
        $categoryGroupLabel = $options['category_group_label'] ?? null;
        $coverageOverride = $options['coverage_override'] ?? null;
        $coverageEndsOnOption = $options['coverage_ends_on'] ?? null;
        if ($coverageEndsOnOption !== null && ! $coverageEndsOnOption instanceof Carbon) {
            $coverageEndsOnOption = Carbon::parse($coverageEndsOnOption);
        }

        // Get sales data (8-week history & derived averages)
        $salesStats = $this->salesRepository->getProductSalesStatistics($product->ID);
        $weeklySales = $this->salesRepository->getProductWeeklySales($product->ID, $salesHistoryWeeks);
        $weeklyUnits = array_map(static fn ($week) => (float) ($week['units'] ?? 0), $weeklySales);
        $weeksWindow = count($weeklyUnits);
        $avgWeeklySalesFromHistory = $weeksWindow > 0 ? array_sum($weeklyUnits) / $weeksWindow : null;
        $avgWeeklySales = $avgWeeklySalesFromHistory ?? ($salesStats['avg_monthly_sales'] / 4.33);
        $peakWeeklySales = ! empty($weeklyUnits) ? max($weeklyUnits) : 0;
        if ($peakWeeklySales <= 0 && $avgWeeklySales > 0) {
            $peakWeeklySales = $avgWeeklySales;
        }

        // Get current stock
        $currentStock = $this->getCurrentStock($product->ID);

        // Base calculation: (Weekly Average × Target Weeks) - Current Stock
        $desiredUnits = $avgWeeklySales * $targetWeeks;
        $baseQuantity = max(0, $desiredUnits - $currentStock);

        // Apply learned adjustments
        $adjustedQuantity = $this->applyLearningAdjustments($product->ID, $baseQuantity);

        // Get supplier link for this product to access CaseUnits
        $supplierLink = $product->supplierLinks->first();
        $caseUnits = $supplierLink?->CaseUnits ?? 1;

        // Calculate case quantities
        if ($caseUnits > 1) {
            $suggestedCases = ceil($adjustedQuantity / $caseUnits);
            $finalUnitsAfterCaseRounding = $suggestedCases * $caseUnits;
        } else {
            $finalUnitsAfterCaseRounding = (int) ceil($adjustedQuantity);
            $suggestedCases = $finalUnitsAfterCaseRounding;
        }

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
        $weeklySalesTotal = array_sum($weeklyUnits);

        $suggestedQuantity = $caseUnits > 1
            ? round($finalUnitsAfterCaseRounding, 3)
            : (int) $finalUnitsAfterCaseRounding;
        $suggestedCasesValue = $caseUnits > 1
            ? round($suggestedCases, 3)
            : (int) $suggestedCases;

        return [
            'suggested_quantity' => $suggestedQuantity,
            'suggested_cases' => $suggestedCasesValue,
            'case_units' => $caseUnits,
            'unit_cost' => $unitCost,
            'review_priority' => $reviewPriority,
            'auto_approved' => $settings?->auto_approve ?? false,
            'force_include' => $baseQuantity > 0 || $avgWeeklySales > 0,
            'context_data' => [
                'avg_weekly_sales' => round($avgWeeklySales, 2),
                'current_stock' => $currentStock,
                'safety_factor' => $safetyFactor,
                'coverage_days' => $coverageDays,
            'coverage_weeks' => round($coverageWeeks, 2),
            'target_weeks' => round($targetWeeks, 2),
            'coverage_ends_on' => $coverageEndsOnOption?->toDateString(),
            'base_calculation' => round($baseQuantity, 3),
            'adjusted_calculation' => round($adjustedQuantity, 3),
            'case_units' => $caseUnits,
            'is_case_product' => $caseUnits > 1,
            'sales_trend' => $salesStats['trend'],
                'last_month_sales' => $salesStats['last_month_sales'],
                'total_sales_6m' => $totalSales6m,
                'sales_history' => $salesHistory,
                'weekly_sales' => $weeklySales,
                'peak_weekly_sales' => round($peakWeeklySales, 2),
                'weekly_sales_total' => round($weeklySalesTotal, 2),
                'weekly_sales_window_weeks' => $weeksWindow,
                'sales_history_weeks' => $salesHistoryWeeks,
                'avg_weekly_sales_source' => $avgWeeklySalesFromHistory !== null ? 'weekly_history' : 'monthly_average',
                'last_sale_date' => $lastSaleDate,
                'stock_days_remaining' => $avgWeeklySales > 0 ? round(($currentStock / $avgWeeklySales) * 7, 1) : 999,
                'cost_source' => $this->getCostSource($supplierLink, $product, $unitCost),
                'cost_per_ordering_unit' => $unitCost,
            'units_per_case' => $caseUnits,
            'has_cost_data' => $unitCost > 0,
            'category_group_key' => $categoryGroupKey,
            'category_group_label' => $categoryGroupLabel,
            'coverage_override_applied' => ! empty($coverageOverride),
            'coverage_override' => $coverageOverride,
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
