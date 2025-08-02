<?php

namespace App\Repositories;

use App\Models\SalesDailySummary;
use App\Models\SalesMonthlySummary;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class OptimizedSalesRepository
{
    /**
     * Get ALL STORE sales data for date range - 100x faster than cross-database queries
     */
    public function getAllSalesByDateRange(Carbon $startDate, Carbon $endDate): Collection
    {
        return SalesDailySummary::forDateRange($startDate, $endDate)
            ->orderBy('sale_date', 'asc')
            ->orderBy('total_revenue', 'desc')
            ->get();
    }
    
    /**
     * Get aggregated ALL STORE sales statistics - instant response
     */
    public function getAllSalesStats(Carbon $startDate, Carbon $endDate): array
    {
        $stats = SalesDailySummary::forDateRange($startDate, $endDate)
            ->selectRaw('
                SUM(total_units) as total_units,
                SUM(total_revenue) as total_revenue,
                AVG(avg_price) as avg_price,
                COUNT(DISTINCT product_id) as unique_products,
                SUM(transaction_count) as total_transactions
            ')
            ->first();

        $categoryBreakdown = SalesDailySummary::forDateRange($startDate, $endDate)
            ->selectRaw('category_id, SUM(total_revenue) as revenue, COUNT(DISTINCT product_id) as products')
            ->groupBy('category_id')
            ->orderBy('revenue', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'category_id' => $item->category_id,
                    'category_name' => $this->getCategoryName($item->category_id),
                    'revenue' => (float) $item->revenue,
                    'products' => (int) $item->products,
                ];
            });

        return [
            'total_units' => (float) $stats->total_units ?? 0,
            'total_revenue' => (float) $stats->total_revenue ?? 0,
            'avg_price' => (float) $stats->avg_price ?? 0,
            'unique_products' => (int) $stats->unique_products ?? 0,
            'total_transactions' => (int) $stats->total_transactions ?? 0,
            'category_breakdown' => $categoryBreakdown,
        ];
    }
    
    /**
     * Get ALL STORE daily sales for charts - instant response
     */
    public function getAllDailySales(Carbon $startDate, Carbon $endDate): Collection
    {
        return SalesDailySummary::forDateRange($startDate, $endDate)
            ->selectRaw('
                sale_date, 
                SUM(total_units) as daily_units,
                SUM(total_revenue) as daily_revenue,
                COUNT(DISTINCT product_id) as daily_products,
                SUM(transaction_count) as daily_transactions
            ')
            ->groupByRaw('DATE(sale_date)')
            ->orderBy('sale_date', 'asc')
            ->get();
    }
    
    /**
     * Get top selling ALL STORE products - instant response
     */
    public function getTopAllProducts(Carbon $startDate, Carbon $endDate, int $limit = 10): Collection
    {
        return SalesDailySummary::forDateRange($startDate, $endDate)
            ->selectRaw('
                product_id,
                product_code,
                product_name,
                category_id,
                SUM(total_units) as total_units,
                SUM(total_revenue) as total_revenue,
                SUM(transaction_count) as total_transactions,
                AVG(avg_price) as avg_price
            ')
            ->groupBy('product_id', 'product_code', 'product_name', 'category_id')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                $product->category_name = $this->getCategoryName($product->category_id);
                return $product;
            });
    }

    /**
     * Get category performance for ALL STORE - instant response
     */
    public function getAllCategoryPerformance(Carbon $startDate, Carbon $endDate): Collection
    {
        return SalesDailySummary::forDateRange($startDate, $endDate)
            ->selectRaw('
                category_id,
                SUM(total_units) as total_units,
                SUM(total_revenue) as total_revenue,
                COUNT(DISTINCT product_id) as unique_products,
                SUM(transaction_count) as total_transactions,
                AVG(avg_price) as avg_price
            ')
            ->groupBy('category_id')
            ->orderBy('total_revenue', 'desc')
            ->get()
            ->map(function ($category) {
                $category->category_name = $this->getCategoryName($category->category_id);
                return $category;
            });
    }
    
    /**
     * Get category name mapping
     */
    private function getCategoryName(string $categoryId): string
    {
        return match($categoryId) {
            'SUB1' => 'Fruits',
            'SUB2' => 'Vegetables', 
            'SUB3' => 'Veg Barcoded',
            default => 'Category ' . $categoryId
        };
    }

    /**
     * Get F&V sales data for date range - 100x faster than cross-database queries
     * @deprecated Use getAllSalesByDateRange for full store data
     */
    public function getFruitVegSalesByDateRange(Carbon $startDate, Carbon $endDate): Collection
    {
        return SalesDailySummary::fruitVeg()
            ->forDateRange($startDate, $endDate)
            ->orderBy('sale_date', 'asc')
            ->orderBy('total_units', 'desc')
            ->get();
    }
    
    /**
     * Get aggregated F&V sales statistics - instant response
     */
    public function getFruitVegSalesStats(Carbon $startDate, Carbon $endDate): array
    {
        $stats = SalesDailySummary::fruitVeg()
            ->forDateRange($startDate, $endDate)
            ->selectRaw('
                SUM(total_units) as total_units,
                SUM(total_revenue) as total_revenue,
                COUNT(DISTINCT product_id) as unique_products,
                SUM(transaction_count) as total_transactions
            ')
            ->first();
            
        $categoryBreakdown = SalesDailySummary::fruitVeg()
            ->forDateRange($startDate, $endDate)
            ->selectRaw('
                category_id,
                SUM(total_units) as category_units,
                SUM(total_revenue) as category_revenue
            ')
            ->groupBy('category_id')
            ->get()
            ->mapWithKeys(function ($item) {
                $categoryName = match ($item->category_id) {
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
            'total_units' => (float) ($stats->total_units ?? 0),
            'total_revenue' => (float) ($stats->total_revenue ?? 0),
            'unique_products' => (int) ($stats->unique_products ?? 0),
            'total_transactions' => (int) ($stats->total_transactions ?? 0),
            'category_breakdown' => $categoryBreakdown,
        ];
    }
    
    /**
     * Get daily sales breakdown - optimized for charts
     */
    public function getFruitVegDailySales(Carbon $startDate, Carbon $endDate): Collection
    {
        return SalesDailySummary::fruitVeg()
            ->forDateRange($startDate, $endDate)
            ->selectRaw('
                sale_date,
                SUM(total_units) as daily_units,
                SUM(total_revenue) as daily_revenue,
                COUNT(DISTINCT product_id) as products_sold
            ')
            ->groupBy('sale_date')
            ->orderBy('sale_date', 'asc')
            ->get();
    }
    
    /**
     * Get top selling F&V products - instant response
     */
    public function getTopFruitVegProducts(Carbon $startDate, Carbon $endDate, int $limit = 10): Collection
    {
        return SalesDailySummary::fruitVeg()
            ->forDateRange($startDate, $endDate)
            ->selectRaw('
                product_id,
                product_code,
                product_name,
                category_id,
                SUM(total_units) as total_units,
                SUM(total_revenue) as total_revenue,
                AVG(avg_price) as avg_price
            ')
            ->groupBy('product_id', 'product_code', 'product_name', 'category_id')
            ->orderByDesc('total_units')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get monthly sales trends - optimized using monthly summaries
     */
    public function getMonthlyFruitVegTrends(int $year): Collection
    {
        return SalesMonthlySummary::fruitVeg()
            ->where('year', $year)
            ->selectRaw('
                month,
                SUM(total_units) as monthly_units,
                SUM(total_revenue) as monthly_revenue,
                COUNT(DISTINCT product_id) as unique_products
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }
    
    /**
     * Get category performance comparison
     */
    public function getCategoryPerformance(Carbon $startDate, Carbon $endDate): Collection
    {
        return SalesDailySummary::fruitVeg()
            ->forDateRange($startDate, $endDate)
            ->selectRaw('
                category_id,
                SUM(total_units) as total_units,
                SUM(total_revenue) as total_revenue,
                AVG(avg_price) as avg_price,
                COUNT(DISTINCT product_id) as unique_products,
                COUNT(DISTINCT sale_date) as active_days
            ')
            ->groupBy('category_id')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function ($item) {
                $item->category_name = match ($item->category_id) {
                    'SUB1' => 'Fruits',
                    'SUB2' => 'Vegetables',
                    'SUB3' => 'Veg Barcoded',
                    default => 'Other'
                };
                return $item;
            });
    }
    
    /**
     * Get product performance over time
     */
    public function getProductTrends(string $productId, Carbon $startDate, Carbon $endDate): Collection
    {
        return SalesDailySummary::where('product_id', $productId)
            ->forDateRange($startDate, $endDate)
            ->select('sale_date', 'total_units', 'total_revenue', 'avg_price', 'transaction_count')
            ->orderBy('sale_date')
            ->get();
    }
    
    /**
     * Get sales summary for date range with totals
     */
    public function getSalesSummary(Carbon $startDate, Carbon $endDate): array
    {
        $summary = SalesDailySummary::fruitVeg()
            ->forDateRange($startDate, $endDate)
            ->selectRaw('
                COUNT(DISTINCT sale_date) as active_days,
                COUNT(DISTINCT product_id) as unique_products,
                SUM(total_units) as total_units,
                SUM(total_revenue) as total_revenue,
                SUM(transaction_count) as total_transactions,
                AVG(avg_price) as overall_avg_price
            ')
            ->first();
            
        $dailyAverage = $summary->active_days > 0 ? [
            'daily_units' => $summary->total_units / $summary->active_days,
            'daily_revenue' => $summary->total_revenue / $summary->active_days,
            'daily_transactions' => $summary->total_transactions / $summary->active_days,
        ] : [
            'daily_units' => 0,
            'daily_revenue' => 0,
            'daily_transactions' => 0,
        ];
        
        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'days' => $startDate->diffInDays($endDate) + 1,
                'active_days' => (int) $summary->active_days,
            ],
            'totals' => [
                'units' => (float) $summary->total_units,
                'revenue' => (float) $summary->total_revenue,
                'transactions' => (int) $summary->total_transactions,
                'unique_products' => (int) $summary->unique_products,
                'avg_price' => (float) $summary->overall_avg_price,
            ],
            'daily_averages' => [
                'units' => round($dailyAverage['daily_units'], 2),
                'revenue' => round($dailyAverage['daily_revenue'], 2),
                'transactions' => round($dailyAverage['daily_transactions'], 2),
            ]
        ];
    }
}