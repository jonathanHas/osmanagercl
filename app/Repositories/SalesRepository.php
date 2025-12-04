<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\SalesDailySummary;
use App\Models\StockDiary;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesRepository
{
    /**
     * Get sales history for a product with improved month names.
     * Limited to last 4 months for better performance.
     *
     * @param  int  $monthsBack  Number of months to retrieve (default 4)
     * @return array
     */
    public function getProductSalesHistory(string $productId, int $monthsBack = 4)
    {
        $salesData = [];
        $currentDate = Carbon::now();

        // Generate month data for the last N months
        for ($i = 0; $i < $monthsBack; $i++) {
            $monthDate = $currentDate->copy()->subMonths($i);
            $monthKey = $monthDate->format('Y-m');
            $monthLabel = $monthDate->format('F Y');

            $salesData[$monthKey] = [
                'month' => $monthLabel,
                'units' => 0,
                'month_short' => $monthDate->format('M'),
                'year' => $monthDate->format('Y'),
            ];
        }

        // Get sales data from STOCKDIARY
        $sales = StockDiary::where('PRODUCT', $productId)
            ->sales() // Use the sales scope (REASON = -1)
            ->where('DATENEW', '>=', $currentDate->copy()->subMonths($monthsBack)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(DATENEW, '%Y-%m') as month_key"),
                DB::raw('SUM(ABS(UNITS)) as total_units') // ABS to convert negative sales to positive
            )
            ->groupBy('month_key')
            ->get();

        // Fill in the actual sales data
        foreach ($sales as $sale) {
            if (isset($salesData[$sale->month_key])) {
                $salesData[$sale->month_key]['units'] = (float) $sale->total_units;
            }
        }

        // Return array in chronological order (oldest first)
        return array_reverse($salesData);
    }

    /**
     * Get weekly sales for a product for the last N weeks (oldest first).
     *
     * @param  int  $weeksBack  Number of weeks to retrieve (default 8)
     * @param  bool  $forceLiveData  Force using live POS data instead of summaries
     * @return array<int, array<string, mixed>>
     */
    public function getProductWeeklySales(string $productId, int $weeksBack = 8, bool $forceLiveData = false): array
    {
        $weeksBack = max(1, $weeksBack);

        $endOfCurrentWeek = Carbon::now()->endOfWeek();
        $startRange = $endOfCurrentWeek->copy()->subWeeks($weeksBack - 1)->startOfWeek();

        // Prepare default structure for each week (oldest first)
        $weeklyBuckets = [];
        $cursor = $startRange->copy();

        for ($i = 0; $i < $weeksBack; $i++) {
            $weekStart = $cursor->copy()->startOfWeek();
            $weekEnd = $cursor->copy()->endOfWeek();
            $key = $weekStart->format('Y-m-d');

            $weeklyBuckets[$key] = [
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'label' => $weekStart->format('d M'),
                'units' => 0.0,
            ];

            $cursor->addWeek();
        }

        // Try to use imported daily summaries first (fast path) unless forced to use live data
        $summaryWeekly = [];
        if (! $forceLiveData) {
            $summaryWeekly = SalesDailySummary::where('product_id', $productId)
                ->whereBetween('sale_date', [$startRange, $endOfCurrentWeek])
                ->selectRaw("DATE_FORMAT(DATE_SUB(sale_date, INTERVAL WEEKDAY(sale_date) DAY), '%Y-%m-%d') as week_start")
                ->selectRaw('SUM(total_units) as total_units')
                ->groupBy('week_start')
                ->pluck('total_units', 'week_start')
                ->toArray();
        }

        if (! empty($summaryWeekly)) {
            foreach ($summaryWeekly as $weekStart => $units) {
                if (isset($weeklyBuckets[$weekStart])) {
                    $weeklyBuckets[$weekStart]['units'] = (float) $units;
                }
            }
        } else {
            // Fallback to live POS data if summaries are missing or forced
            $weeklySales = StockDiary::where('PRODUCT', $productId)
                ->sales()
                ->where('DATENEW', '>=', $startRange)
                ->select(
                    DB::raw("DATE_FORMAT(DATE_SUB(DATENEW, INTERVAL WEEKDAY(DATENEW) DAY), '%Y-%m-%d') as week_start"),
                    DB::raw('SUM(ABS(UNITS)) as total_units')
                )
                ->groupBy('week_start')
                ->get();

            foreach ($weeklySales as $sale) {
                if (isset($weeklyBuckets[$sale->week_start])) {
                    $weeklyBuckets[$sale->week_start]['units'] = (float) $sale->total_units;
                }
            }
        }

        return array_values($weeklyBuckets);
    }

    /**
     * Get sales statistics for a product.
     *
     * @return array
     */
    public function getProductSalesStatistics(string $productId)
    {
        $currentDate = Carbon::now();
        $lastYear = $currentDate->copy()->subYear();

        $summaryQuery = SalesDailySummary::where('product_id', $productId);
        $hasSummaryData = $summaryQuery->exists();

        if ($hasSummaryData) {
            // Total sales last 12 months
            $totalSales = (float) SalesDailySummary::where('product_id', $productId)
                ->whereBetween('sale_date', [$lastYear, $currentDate])
                ->sum('total_units');

            $avgMonthlySales = $totalSales / 12;

            // Sales this month
            $thisMonthSales = (float) SalesDailySummary::where('product_id', $productId)
                ->whereYear('sale_date', $currentDate->year)
                ->whereMonth('sale_date', $currentDate->month)
                ->sum('total_units');

            // Sales last month
            $lastMonth = $currentDate->copy()->subMonth();
            $lastMonthSales = (float) SalesDailySummary::where('product_id', $productId)
                ->whereYear('sale_date', $lastMonth->year)
                ->whereMonth('sale_date', $lastMonth->month)
                ->sum('total_units');
        } else {
            // Fallback to live POS queries
            $totalSales = StockDiary::where('PRODUCT', $productId)
                ->sales()
                ->where('DATENEW', '>=', $lastYear)
                ->sum(DB::raw('ABS(UNITS)'));

            $avgMonthlySales = $totalSales / 12;

            $thisMonthSales = StockDiary::where('PRODUCT', $productId)
                ->sales()
                ->whereYear('DATENEW', $currentDate->year)
                ->whereMonth('DATENEW', $currentDate->month)
                ->sum(DB::raw('ABS(UNITS)'));

            $lastMonth = $currentDate->copy()->subMonth();
            $lastMonthSales = StockDiary::where('PRODUCT', $productId)
                ->sales()
                ->whereYear('DATENEW', $lastMonth->year)
                ->whereMonth('DATENEW', $lastMonth->month)
                ->sum(DB::raw('ABS(UNITS)'));
        }

        // Calculate trend
        $trend = 'stable';
        if ($lastMonthSales > 0) {
            $percentChange = (($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100;
            if ($percentChange > 10) {
                $trend = 'up';
            } elseif ($percentChange < -10) {
                $trend = 'down';
            }
        }

        return [
            'total_sales_12m' => (float) $totalSales,
            'avg_monthly_sales' => round($avgMonthlySales, 1),
            'this_month_sales' => (float) $thisMonthSales,
            'last_month_sales' => (float) $lastMonthSales,
            'trend' => $trend,
        ];
    }

    /**
     * Get top selling products for a given period.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getTopSellingProducts(Carbon $startDate, Carbon $endDate, int $limit = 10)
    {
        return StockDiary::with('product')
            ->sales()
            ->whereBetween('DATENEW', [$startDate, $endDate])
            ->select('PRODUCT', DB::raw('SUM(ABS(UNITS)) as total_sold'))
            ->groupBy('PRODUCT')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if a product has any sales history.
     */
    public function hasProductSales(string $productId): bool
    {
        return StockDiary::where('PRODUCT', $productId)
            ->sales()
            ->exists();
    }

    /**
     * Get sales data for F&V products within a date range.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getFruitVegSalesByDateRange(Carbon $startDate, Carbon $endDate)
    {
        // Use efficient JOIN instead of whereHas() for much better performance
        return DB::connection('pos')
            ->table('STOCKDIARY as s')
            ->join('PRODUCTS as p', 's.PRODUCT', '=', 'p.ID')
            ->where('s.REASON', StockDiary::REASON_SALE)
            ->whereBetween('s.DATENEW', [$startDate, $endDate])
            ->whereIn('p.CATEGORY', ['SUB1', 'SUB2', 'SUB3'])
            ->select(
                's.PRODUCT',
                'p.NAME as product_name',
                'p.CODE as product_code',
                'p.CATEGORY as product_category',
                DB::raw('SUM(ABS(s.UNITS)) as total_units'),
                DB::raw('SUM(ABS(s.UNITS) * s.PRICE) as total_revenue'),
                DB::raw('DATE(s.DATENEW) as sale_date')
            )
            ->groupBy('s.PRODUCT', 'p.NAME', 'p.CODE', 'p.CATEGORY', 'sale_date')
            ->orderBy('sale_date', 'asc') // Changed to asc for chronological order
            ->orderBy('total_units', 'desc')
            ->get();
    }

    /**
     * Get aggregated F&V sales statistics for a date range.
     *
     * @return array
     */
    public function getFruitVegSalesStats(Carbon $startDate, Carbon $endDate)
    {
        // Single optimized query with JOIN for all statistics
        $sales = DB::connection('pos')
            ->table('STOCKDIARY as s')
            ->join('PRODUCTS as p', 's.PRODUCT', '=', 'p.ID')
            ->where('s.REASON', StockDiary::REASON_SALE)
            ->whereBetween('s.DATENEW', [$startDate, $endDate])
            ->whereIn('p.CATEGORY', ['SUB1', 'SUB2', 'SUB3'])
            ->selectRaw('
                SUM(ABS(s.UNITS)) as total_units,
                SUM(ABS(s.UNITS) * s.PRICE) as total_revenue,
                COUNT(DISTINCT s.PRODUCT) as unique_products,
                COUNT(*) as total_transactions
            ')
            ->first();

        // Get category breakdown in single query
        $categoryBreakdown = DB::connection('pos')
            ->table('STOCKDIARY as s')
            ->join('PRODUCTS as p', 's.PRODUCT', '=', 'p.ID')
            ->where('s.REASON', StockDiary::REASON_SALE)
            ->whereBetween('s.DATENEW', [$startDate, $endDate])
            ->whereIn('p.CATEGORY', ['SUB1', 'SUB2', 'SUB3'])
            ->selectRaw('
                p.CATEGORY,
                SUM(ABS(s.UNITS)) as category_units,
                SUM(ABS(s.UNITS) * s.PRICE) as category_revenue
            ')
            ->groupBy('p.CATEGORY')
            ->get()
            ->mapWithKeys(function ($item) {
                $categoryName = match ($item->CATEGORY) {
                    'SUB1' => 'Fruits',
                    'SUB2' => 'Vegetables',
                    'SUB3' => 'Veg Barcoded',
                    default => 'Other'
                };

                return [$categoryName => [
                    'units' => (float) $item->category_units,
                    'revenue' => (float) $item->category_revenue,
                ]];
            });

        return [
            'total_units' => (float) ($sales->total_units ?? 0),
            'total_revenue' => (float) ($sales->total_revenue ?? 0),
            'unique_products' => (int) ($sales->unique_products ?? 0),
            'total_transactions' => (int) ($sales->total_transactions ?? 0),
            'category_breakdown' => $categoryBreakdown,
        ];
    }

    /**
     * Get top selling F&V products for a date range.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getTopFruitVegProducts(Carbon $startDate, Carbon $endDate, int $limit = 10)
    {
        // Optimized JOIN query - much faster than whereHas()
        return DB::connection('pos')
            ->table('STOCKDIARY as s')
            ->join('PRODUCTS as p', 's.PRODUCT', '=', 'p.ID')
            ->where('s.REASON', StockDiary::REASON_SALE)
            ->whereBetween('s.DATENEW', [$startDate, $endDate])
            ->whereIn('p.CATEGORY', ['SUB1', 'SUB2', 'SUB3'])
            ->select(
                's.PRODUCT',
                'p.NAME as product_name',
                'p.CODE as product_code',
                'p.CATEGORY as product_category',
                DB::raw('SUM(ABS(s.UNITS)) as total_units'),
                DB::raw('SUM(ABS(s.UNITS) * s.PRICE) as total_revenue'),
                DB::raw('AVG(s.PRICE) as avg_price')
            )
            ->groupBy('s.PRODUCT', 'p.NAME', 'p.CODE', 'p.CATEGORY')
            ->orderByDesc('total_units')
            ->limit($limit)
            ->get();
    }

    /**
     * Get daily sales breakdown for F&V products.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getFruitVegDailySales(Carbon $startDate, Carbon $endDate)
    {
        // Optimized JOIN query for daily sales
        return DB::connection('pos')
            ->table('STOCKDIARY as s')
            ->join('PRODUCTS as p', 's.PRODUCT', '=', 'p.ID')
            ->where('s.REASON', StockDiary::REASON_SALE)
            ->whereBetween('s.DATENEW', [$startDate, $endDate])
            ->whereIn('p.CATEGORY', ['SUB1', 'SUB2', 'SUB3'])
            ->selectRaw('
                DATE(s.DATENEW) as sale_date,
                SUM(ABS(s.UNITS)) as daily_units,
                SUM(ABS(s.UNITS) * s.PRICE) as daily_revenue,
                COUNT(DISTINCT s.PRODUCT) as products_sold
            ')
            ->groupBy('sale_date')
            ->orderBy('sale_date', 'asc') // Changed to asc for chronological order
            ->get()
            ->map(function ($item) {
                $item->sale_date = Carbon::parse($item->sale_date);

                return $item;
            });
    }

    /**
     * Get Christmas window comparison for a product across multiple years.
     *
     * @param  string  $productId  Product ID
     * @param  array  $years  Years to compare (e.g., [2024, 2023])
     * @param  Carbon  $startDate  Christmas period start (e.g., Dec 10)
     * @param  Carbon  $endDate  Christmas period end (e.g., Dec 26)
     */
    public function getChristmasWindowComparison(string $productId, array $years, Carbon $startDate, Carbon $endDate): array
    {
        $windows = [];

        foreach ($years as $year) {
            // Adjust dates to the specified year
            $periodStart = $startDate->copy()->setYear($year);
            $periodEnd = $endDate->copy()->setYear($year);

            // Get daily sales for this period from sales_daily_summary (fast path)
            $dailySales = SalesDailySummary::where('product_id', $productId)
                ->whereBetween('sale_date', [$periodStart, $periodEnd])
                ->orderBy('sale_date')
                ->get();

            // If no data in daily summary, try STOCKDIARY (fallback)
            if ($dailySales->isEmpty()) {
                $stockDiarySales = StockDiary::where('PRODUCT', $productId)
                    ->sales()
                    ->whereBetween('DATENEW', [$periodStart, $periodEnd])
                    ->selectRaw('DATE(DATENEW) as sale_date')
                    ->selectRaw('SUM(ABS(UNITS)) as total_units')
                    ->groupBy('sale_date')
                    ->orderBy('sale_date')
                    ->get();

                // Convert to daily sales format
                $dailySales = $stockDiarySales->map(function ($item) use ($productId) {
                    return (object) [
                        'product_id' => $productId,
                        'sale_date' => Carbon::parse($item->sale_date),
                        'total_units' => (float) $item->total_units,
                    ];
                });
            }

            // Group into weeks for visualization
            $weeklyBreakdown = $this->groupIntoWeeks($dailySales, $periodStart, $periodEnd);

            $totalUnits = $dailySales->sum('total_units');
            $days = $periodStart->diffInDays($periodEnd) + 1;
            $weeksEquivalent = $days / 7;

            $windows[] = [
                'year' => $year,
                'date_range' => $periodStart->format('M d').' - '.$periodEnd->format('M d'),
                'weekly_breakdown' => $weeklyBreakdown,
                'total_units' => (float) $totalUnits,
                'weekly_average' => $weeksEquivalent > 0 ? $totalUnits / $weeksEquivalent : 0,
                'days' => $days,
            ];
        }

        return $windows;
    }

    /**
     * Helper method to group daily sales into weeks.
     *
     * @param  \Illuminate\Support\Collection  $dailySales  Collection of daily sales
     * @param  Carbon  $startDate  Period start date
     * @param  Carbon  $endDate  Period end date
     */
    protected function groupIntoWeeks($dailySales, Carbon $startDate, Carbon $endDate): array
    {
        $weeklyBreakdown = [];
        $currentWeekStart = $startDate->copy()->startOfWeek();
        $weekNum = 1;

        while ($currentWeekStart <= $endDate) {
            $currentWeekEnd = $currentWeekStart->copy()->endOfWeek();

            // Sum sales for this week
            $weekSales = $dailySales->filter(function ($sale) use ($currentWeekStart, $currentWeekEnd) {
                $saleDate = $sale->sale_date instanceof Carbon ? $sale->sale_date : Carbon::parse($sale->sale_date);

                return $saleDate->between($currentWeekStart, $currentWeekEnd);
            })->sum('total_units');

            $weeklyBreakdown[] = [
                'label' => 'W'.$weekNum.' ('.$currentWeekStart->format('M d').')',
                'units' => (float) $weekSales,
            ];

            $currentWeekStart->addWeek();
            $weekNum++;
        }

        return $weeklyBreakdown;
    }

    // ============================================================================
    // BULK METHODS FOR ORDER GENERATION OPTIMIZATION
    // These methods fetch data for multiple products in a single query
    // ============================================================================

    /**
     * Get sales statistics for multiple products in bulk.
     *
     * @param  array  $productIds  Array of product IDs
     * @return \Illuminate\Support\Collection Keyed by product_id
     */
    public function getBulkProductSalesStatistics(array $productIds): \Illuminate\Support\Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        $currentDate = Carbon::now();
        $lastYear = $currentDate->copy()->subYear();
        $lastMonth = $currentDate->copy()->subMonth();

        // Check if we have data in sales_daily_summary
        $hasSummaryData = SalesDailySummary::whereIn('product_id', $productIds)->exists();

        if ($hasSummaryData) {
            // Get all stats in a single query from pre-aggregated table
            $stats = SalesDailySummary::whereIn('product_id', $productIds)
                ->whereBetween('sale_date', [$lastYear, $currentDate])
                ->selectRaw('product_id')
                ->selectRaw('SUM(total_units) as total_sales_12m')
                ->selectRaw('SUM(CASE WHEN YEAR(sale_date) = ? AND MONTH(sale_date) = ? THEN total_units ELSE 0 END) as this_month_sales', [
                    $currentDate->year,
                    $currentDate->month,
                ])
                ->selectRaw('SUM(CASE WHEN YEAR(sale_date) = ? AND MONTH(sale_date) = ? THEN total_units ELSE 0 END) as last_month_sales', [
                    $lastMonth->year,
                    $lastMonth->month,
                ])
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id')
                ->map(function ($item) {
                    $totalSales = (float) ($item->total_sales_12m ?? 0);
                    $avgMonthlySales = $totalSales / 12;
                    $thisMonth = (float) ($item->this_month_sales ?? 0);
                    $lastMonth = (float) ($item->last_month_sales ?? 0);

                    $trend = 'stable';
                    if ($lastMonth > 0) {
                        $percentChange = (($thisMonth - $lastMonth) / $lastMonth) * 100;
                        if ($percentChange > 10) {
                            $trend = 'up';
                        } elseif ($percentChange < -10) {
                            $trend = 'down';
                        }
                    }

                    return [
                        'total_sales_12m' => $totalSales,
                        'avg_monthly_sales' => round($avgMonthlySales, 1),
                        'this_month_sales' => $thisMonth,
                        'last_month_sales' => $lastMonth,
                        'trend' => $trend,
                    ];
                });
        } else {
            // Fallback to live POS queries
            $stats = StockDiary::whereIn('PRODUCT', $productIds)
                ->sales()
                ->where('DATENEW', '>=', $lastYear)
                ->selectRaw('PRODUCT as product_id')
                ->selectRaw('SUM(ABS(UNITS)) as total_sales_12m')
                ->selectRaw('SUM(CASE WHEN YEAR(DATENEW) = ? AND MONTH(DATENEW) = ? THEN ABS(UNITS) ELSE 0 END) as this_month_sales', [
                    $currentDate->year,
                    $currentDate->month,
                ])
                ->selectRaw('SUM(CASE WHEN YEAR(DATENEW) = ? AND MONTH(DATENEW) = ? THEN ABS(UNITS) ELSE 0 END) as last_month_sales', [
                    $lastMonth->year,
                    $lastMonth->month,
                ])
                ->groupBy('PRODUCT')
                ->get()
                ->keyBy('product_id')
                ->map(function ($item) {
                    $totalSales = (float) ($item->total_sales_12m ?? 0);
                    $avgMonthlySales = $totalSales / 12;
                    $thisMonth = (float) ($item->this_month_sales ?? 0);
                    $lastMonth = (float) ($item->last_month_sales ?? 0);

                    $trend = 'stable';
                    if ($lastMonth > 0) {
                        $percentChange = (($thisMonth - $lastMonth) / $lastMonth) * 100;
                        if ($percentChange > 10) {
                            $trend = 'up';
                        } elseif ($percentChange < -10) {
                            $trend = 'down';
                        }
                    }

                    return [
                        'total_sales_12m' => $totalSales,
                        'avg_monthly_sales' => round($avgMonthlySales, 1),
                        'this_month_sales' => $thisMonth,
                        'last_month_sales' => $lastMonth,
                        'trend' => $trend,
                    ];
                });
        }

        // Fill in missing products with zero stats
        $result = collect();
        foreach ($productIds as $productId) {
            $result[$productId] = $stats[$productId] ?? [
                'total_sales_12m' => 0.0,
                'avg_monthly_sales' => 0.0,
                'this_month_sales' => 0.0,
                'last_month_sales' => 0.0,
                'trend' => 'stable',
            ];
        }

        return $result;
    }

    /**
     * Get weekly sales for multiple products in bulk.
     *
     * @param  array  $productIds  Array of product IDs
     * @param  int  $weeksBack  Number of weeks to retrieve
     * @return \Illuminate\Support\Collection Keyed by product_id, each containing array of weekly data
     */
    public function getBulkProductWeeklySales(array $productIds, int $weeksBack = 8): \Illuminate\Support\Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        $weeksBack = max(1, $weeksBack);
        $endOfCurrentWeek = Carbon::now()->endOfWeek();
        $startRange = $endOfCurrentWeek->copy()->subWeeks($weeksBack - 1)->startOfWeek();

        // Build week buckets template
        $weekBuckets = [];
        $cursor = $startRange->copy();
        for ($i = 0; $i < $weeksBack; $i++) {
            $weekStart = $cursor->copy()->startOfWeek();
            $weekEnd = $cursor->copy()->endOfWeek();
            $key = $weekStart->format('Y-m-d');
            $weekBuckets[$key] = [
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'label' => $weekStart->format('d M'),
                'units' => 0.0,
            ];
            $cursor->addWeek();
        }

        // Try to use imported daily summaries first (fast path)
        $t1 = microtime(true);
        $summaryData = SalesDailySummary::whereIn('product_id', $productIds)
            ->whereBetween('sale_date', [$startRange, $endOfCurrentWeek])
            ->selectRaw('product_id')
            ->selectRaw("DATE_FORMAT(DATE_SUB(sale_date, INTERVAL WEEKDAY(sale_date) DAY), '%Y-%m-%d') as week_start")
            ->selectRaw('SUM(total_units) as total_units')
            ->groupBy('product_id', 'week_start')
            ->get();

        $usesSummary = $summaryData->isNotEmpty();
        \Log::debug('getBulkProductWeeklySales: SalesDailySummary query took '.round((microtime(true) - $t1) * 1000).'ms, found '.$summaryData->count().' rows, usesSummary='.$usesSummary);

        if (! $usesSummary) {
            // Fallback to live POS data
            $t2 = microtime(true);
            $summaryData = StockDiary::whereIn('PRODUCT', $productIds)
                ->sales()
                ->where('DATENEW', '>=', $startRange)
                ->selectRaw('PRODUCT as product_id')
                ->selectRaw("DATE_FORMAT(DATE_SUB(DATENEW, INTERVAL WEEKDAY(DATENEW) DAY), '%Y-%m-%d') as week_start")
                ->selectRaw('SUM(ABS(UNITS)) as total_units')
                ->groupBy('PRODUCT', 'week_start')
                ->get();
            \Log::debug('getBulkProductWeeklySales: STOCKDIARY fallback took '.round((microtime(true) - $t2) * 1000).'ms');
        }

        // Group data by product_id ONCE upfront (O(n) instead of O(n*m))
        $groupedData = $summaryData->groupBy('product_id');

        // Build result structure
        $result = collect();
        foreach ($productIds as $productId) {
            $productWeeks = $weekBuckets;

            // Direct access O(1) instead of filtering O(n)
            $productData = $groupedData->get($productId, collect());
            foreach ($productData as $weekData) {
                if (isset($productWeeks[$weekData->week_start])) {
                    $productWeeks[$weekData->week_start]['units'] = (float) $weekData->total_units;
                }
            }

            $result[$productId] = array_values($productWeeks);
        }

        return $result;
    }

    /**
     * Get Christmas window comparison for multiple products in bulk.
     * Includes weekly breakdown data needed for chart visualization.
     *
     * @param  array  $productIds  Array of product IDs
     * @param  array  $years  Years to compare (e.g., [2024, 2023])
     * @param  Carbon  $startDate  Christmas period start (e.g., Dec 10)
     * @param  Carbon  $endDate  Christmas period end (e.g., Dec 26)
     * @return \Illuminate\Support\Collection Keyed by product_id
     */
    public function getBulkChristmasWindowComparison(array $productIds, array $years, Carbon $startDate, Carbon $endDate): \Illuminate\Support\Collection
    {
        if (empty($productIds) || empty($years)) {
            return collect();
        }

        $result = collect();
        $days = $startDate->diffInDays($endDate) + 1;
        $weeksEquivalent = $days / 7;

        // Fetch all data for all years in bulk - including weekly breakdown
        $allYearlyTotals = collect();
        $allWeeklyData = collect();

        foreach ($years as $year) {
            $periodStart = $startDate->copy()->setYear($year);
            $periodEnd = $endDate->copy()->setYear($year);

            // Get totals per product
            $yearTotals = SalesDailySummary::whereIn('product_id', $productIds)
                ->whereBetween('sale_date', [$periodStart, $periodEnd])
                ->selectRaw('product_id')
                ->selectRaw('SUM(total_units) as total_units')
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');

            // Fallback to STOCKDIARY if needed
            if ($yearTotals->isEmpty()) {
                $yearTotals = StockDiary::whereIn('PRODUCT', $productIds)
                    ->sales()
                    ->whereBetween('DATENEW', [$periodStart, $periodEnd])
                    ->selectRaw('PRODUCT as product_id')
                    ->selectRaw('SUM(ABS(UNITS)) as total_units')
                    ->groupBy('PRODUCT')
                    ->get()
                    ->keyBy('product_id');
            }

            $allYearlyTotals[$year] = $yearTotals;

            // Get weekly breakdown data per product
            $weeklyData = SalesDailySummary::whereIn('product_id', $productIds)
                ->whereBetween('sale_date', [$periodStart, $periodEnd])
                ->selectRaw('product_id')
                ->selectRaw("DATE_FORMAT(DATE_SUB(sale_date, INTERVAL WEEKDAY(sale_date) DAY), '%Y-%m-%d') as week_start")
                ->selectRaw('SUM(total_units) as total_units')
                ->groupBy('product_id', 'week_start')
                ->get();

            // Fallback to STOCKDIARY if needed
            if ($weeklyData->isEmpty()) {
                $weeklyData = StockDiary::whereIn('PRODUCT', $productIds)
                    ->sales()
                    ->whereBetween('DATENEW', [$periodStart, $periodEnd])
                    ->selectRaw('PRODUCT as product_id')
                    ->selectRaw("DATE_FORMAT(DATE_SUB(DATENEW, INTERVAL WEEKDAY(DATENEW) DAY), '%Y-%m-%d') as week_start")
                    ->selectRaw('SUM(ABS(UNITS)) as total_units')
                    ->groupBy('PRODUCT', 'week_start')
                    ->get();
            }

            $allWeeklyData[$year] = $weeklyData->groupBy('product_id');
        }

        // Build result for each product
        foreach ($productIds as $productId) {
            $windows = [];
            foreach ($years as $year) {
                $periodStart = $startDate->copy()->setYear($year);
                $periodEnd = $endDate->copy()->setYear($year);
                $yearData = $allYearlyTotals[$year][$productId] ?? null;
                $totalUnits = (float) ($yearData->total_units ?? 0);

                // Build weekly breakdown for this product/year
                $weeklyBreakdown = $this->buildWeeklyBreakdownFromData(
                    $allWeeklyData[$year][$productId] ?? collect(),
                    $periodStart,
                    $periodEnd
                );

                $windows[] = [
                    'year' => $year,
                    'date_range' => $periodStart->format('M d').' - '.$periodEnd->format('M d'),
                    'weekly_breakdown' => $weeklyBreakdown,
                    'total_units' => $totalUnits,
                    'weekly_average' => $weeksEquivalent > 0 ? $totalUnits / $weeksEquivalent : 0,
                    'days' => $days,
                ];
            }
            $result[$productId] = $windows;
        }

        return $result;
    }

    /**
     * Build weekly breakdown array from pre-fetched data.
     */
    protected function buildWeeklyBreakdownFromData($weeklyData, Carbon $startDate, Carbon $endDate): array
    {
        $weeklyBreakdown = [];
        $currentWeekStart = $startDate->copy()->startOfWeek();
        $weekNum = 1;

        while ($currentWeekStart <= $endDate) {
            $weekKey = $currentWeekStart->format('Y-m-d');

            // Find matching week data
            $weekSales = 0.0;
            foreach ($weeklyData as $row) {
                if ($row->week_start === $weekKey) {
                    $weekSales = (float) $row->total_units;
                    break;
                }
            }

            $weeklyBreakdown[] = [
                'label' => 'W'.$weekNum.' ('.$currentWeekStart->format('M d').')',
                'units' => $weekSales,
            ];

            $currentWeekStart->addWeek();
            $weekNum++;
        }

        return $weeklyBreakdown;
    }

    /**
     * Get recent purchase prices for multiple products in bulk.
     * Uses a derived table approach for better performance.
     *
     * @param  array  $productIds  Array of product IDs
     * @return \Illuminate\Support\Collection Keyed by product_id, value is float price or null
     */
    public function getBulkRecentPurchasePrices(array $productIds): \Illuminate\Support\Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        // First get the max date per product (fast GROUP BY)
        $maxDates = DB::connection('pos')
            ->table('STOCKDIARY')
            ->whereIn('PRODUCT', $productIds)
            ->where('REASON', '>', 0)
            ->where('PRICE', '>', 0)
            ->selectRaw('PRODUCT, MAX(DATENEW) as max_date')
            ->groupBy('PRODUCT')
            ->get()
            ->keyBy('PRODUCT');

        if ($maxDates->isEmpty()) {
            $result = collect();
            foreach ($productIds as $productId) {
                $result[$productId] = null;
            }

            return $result;
        }

        // Build conditions for fetching the actual prices
        $conditions = [];
        foreach ($maxDates as $productId => $row) {
            $conditions[] = "(PRODUCT = '{$productId}' AND DATENEW = '{$row->max_date}')";
        }

        // Fetch the prices for those specific records
        $latestPrices = DB::connection('pos')
            ->table('STOCKDIARY')
            ->where('REASON', '>', 0)
            ->where('PRICE', '>', 0)
            ->whereRaw('('.implode(' OR ', $conditions).')')
            ->select('PRODUCT as product_id', 'PRICE as price')
            ->get()
            ->keyBy('product_id');

        // Build result with null for products without purchase history
        $result = collect();
        foreach ($productIds as $productId) {
            $result[$productId] = isset($latestPrices[$productId])
                ? (float) $latestPrices[$productId]->price
                : null;
        }

        return $result;
    }

    /**
     * Get sales history for multiple products in bulk.
     *
     * @param  array  $productIds  Array of product IDs
     * @param  int  $monthsBack  Number of months to retrieve
     * @return \Illuminate\Support\Collection Keyed by product_id, each containing array of monthly data
     */
    public function getBulkProductSalesHistory(array $productIds, int $monthsBack = 6): \Illuminate\Support\Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        $currentDate = Carbon::now();
        $startDate = $currentDate->copy()->subMonths($monthsBack)->startOfMonth();

        // Build month template
        $monthTemplate = [];
        for ($i = 0; $i < $monthsBack; $i++) {
            $monthDate = $currentDate->copy()->subMonths($i);
            $monthKey = $monthDate->format('Y-m');
            $monthTemplate[$monthKey] = [
                'month' => $monthDate->format('F Y'),
                'units' => 0,
                'month_short' => $monthDate->format('M'),
                'year' => $monthDate->format('Y'),
            ];
        }

        // Get sales data from STOCKDIARY in bulk
        $salesData = StockDiary::whereIn('PRODUCT', $productIds)
            ->sales()
            ->where('DATENEW', '>=', $startDate)
            ->selectRaw('PRODUCT as product_id')
            ->selectRaw("DATE_FORMAT(DATENEW, '%Y-%m') as month_key")
            ->selectRaw('SUM(ABS(UNITS)) as total_units')
            ->groupBy('PRODUCT', 'month_key')
            ->get();

        // Group data by product_id ONCE upfront (O(n) instead of O(n*m))
        $groupedData = $salesData->groupBy('product_id');

        // Build result for each product
        $result = collect();
        foreach ($productIds as $productId) {
            $productMonths = $monthTemplate;

            // Direct access O(1) instead of filtering O(n)
            $productData = $groupedData->get($productId, collect());
            foreach ($productData as $monthData) {
                if (isset($productMonths[$monthData->month_key])) {
                    $productMonths[$monthData->month_key]['units'] = (float) $monthData->total_units;
                }
            }

            // Return in chronological order (oldest first)
            $result[$productId] = array_reverse($productMonths);
        }

        return $result;
    }

    /**
     * Get last sale date for multiple products in bulk.
     * Optimized to use GROUP BY instead of correlated subquery.
     *
     * @param  array  $productIds  Array of product IDs
     * @return \Illuminate\Support\Collection Keyed by product_id, value is string date or null
     */
    public function getBulkLastSaleDates(array $productIds): \Illuminate\Support\Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        // Use sales_daily_summary which is pre-aggregated and indexed - MUCH faster than STOCKDIARY
        $lastSales = SalesDailySummary::whereIn('product_id', $productIds)
            ->selectRaw('product_id, MAX(sale_date) as last_sale')
            ->groupBy('product_id')
            ->pluck('last_sale', 'product_id');

        $result = collect();
        foreach ($productIds as $productId) {
            $result[$productId] = $lastSales[$productId] ?? null;
        }

        return $result;
    }
}
