<?php

namespace App\Repositories;

use App\Models\Product;
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
     * Get sales statistics for a product.
     *
     * @return array
     */
    public function getProductSalesStatistics(string $productId)
    {
        $currentDate = Carbon::now();
        $lastYear = $currentDate->copy()->subYear();

        // Total sales last 12 months
        $totalSales = StockDiary::where('PRODUCT', $productId)
            ->sales()
            ->where('DATENEW', '>=', $lastYear)
            ->sum(DB::raw('ABS(UNITS)'));

        // Average monthly sales
        $avgMonthlySales = $totalSales / 12;

        // Sales this month
        $thisMonthSales = StockDiary::where('PRODUCT', $productId)
            ->sales()
            ->whereYear('DATENEW', $currentDate->year)
            ->whereMonth('DATENEW', $currentDate->month)
            ->sum(DB::raw('ABS(UNITS)'));

        // Sales last month
        $lastMonth = $currentDate->copy()->subMonth();
        $lastMonthSales = StockDiary::where('PRODUCT', $productId)
            ->sales()
            ->whereYear('DATENEW', $lastMonth->year)
            ->whereMonth('DATENEW', $lastMonth->month)
            ->sum(DB::raw('ABS(UNITS)'));

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
}
