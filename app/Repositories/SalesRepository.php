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
}
