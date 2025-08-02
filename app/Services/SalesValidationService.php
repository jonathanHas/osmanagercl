<?php

namespace App\Services;

use App\Models\SalesDailySummary;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class SalesValidationService
{
    /**
     * Validate imported data against POS database for a date range
     */
    public function validateDateRange(Carbon $startDate, Carbon $endDate): array
    {
        $startTime = microtime(true);
        
        // Get imported data
        $importedData = $this->getImportedSalesData($startDate, $endDate);
        
        // Get POS data using same query as import
        $posData = $this->getPOSSalesDataForValidation($startDate, $endDate);
        
        // Compare the datasets
        $comparison = $this->compareDatasets($importedData, $posData);
        
        $executionTime = microtime(true) - $startTime;
        
        return [
            'validation_date' => now(),
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'days' => $startDate->diffInDays($endDate) + 1
            ],
            'execution_time_seconds' => round($executionTime, 3),
            'summary' => [
                'imported_records' => $importedData->count(),
                'pos_records' => $posData->count(),
                'matches' => $comparison['matches'],
                'discrepancies' => $comparison['discrepancies'],
                'missing_in_imported' => $comparison['missing_in_imported'],
                'extra_in_imported' => $comparison['extra_in_imported'],
                'accuracy_percentage' => $comparison['accuracy_percentage']
            ],
            'totals_comparison' => $comparison['totals_comparison'],
            'detailed_discrepancies' => $comparison['detailed_discrepancies'],
            'status' => $comparison['accuracy_percentage'] >= 99.9 ? 'excellent' : 
                       ($comparison['accuracy_percentage'] >= 95 ? 'good' : 'needs_attention')
        ];
    }
    
    /**
     * Get side-by-side comparison data for display
     */
    public function getComparisonData(Carbon $startDate, Carbon $endDate): array
    {
        $importedData = $this->getImportedSalesData($startDate, $endDate);
        $posData = $this->getPOSSalesDataForValidation($startDate, $endDate);
        
        // Group both datasets by product+date for comparison
        $importedByKey = $importedData->keyBy(function ($item) {
            return $item->product_id . '-' . $item->sale_date->format('Y-m-d');
        });
        
        $posByKey = $posData->keyBy(function ($item) {
            return $item->product_id . '-' . $item->sale_date;
        });
        
        $comparison = [];
        $allKeys = $importedByKey->keys()->merge($posByKey->keys())->unique();
        
        foreach ($allKeys as $key) {
            $imported = $importedByKey->get($key);
            $pos = $posByKey->get($key);
            
            $comparison[] = [
                'key' => $key,
                'product_id' => $imported->product_id ?? $pos->product_id,
                'product_code' => $imported->product_code ?? $pos->product_code,
                'product_name' => $imported->product_name ?? $pos->product_name,
                'sale_date' => $imported->sale_date ?? $pos->sale_date,
                'imported' => $imported ? [
                    'total_units' => (float) $imported->total_units,
                    'total_revenue' => (float) $imported->total_revenue,
                    'transaction_count' => (int) $imported->transaction_count,
                    'avg_price' => (float) $imported->avg_price
                ] : null,
                'pos' => $pos ? [
                    'total_units' => (float) $pos->total_units,
                    'total_revenue' => (float) $pos->total_revenue,
                    'transaction_count' => (int) $pos->transaction_count,
                    'avg_price' => (float) $pos->avg_price
                ] : null,
                'status' => $this->getComparisonStatus($imported, $pos),
                'variances' => $this->calculateVariances($imported, $pos)
            ];
        }
        
        return $comparison;
    }
    
    /**
     * Get daily summary comparison
     */
    public function getDailySummaryComparison(Carbon $startDate, Carbon $endDate): array
    {
        // Imported data by date
        $importedDaily = SalesDailySummary::whereBetween('sale_date', [$startDate, $endDate])
            ->selectRaw('
                DATE(sale_date) as sale_date,
                SUM(total_units) as daily_units,
                SUM(total_revenue) as daily_revenue,
                COUNT(*) as product_count,
                SUM(transaction_count) as daily_transactions
            ')
            ->groupByRaw('DATE(sale_date)')
            ->orderByRaw('DATE(sale_date)')
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->sale_date)->format('Y-m-d');
            });
        
        // POS data by date
        $posDaily = DB::connection('pos')
            ->table('STOCKDIARY as s')
            ->join('PRODUCTS as p', 's.PRODUCT', '=', 'p.ID')
            ->where('s.REASON', -1)
            ->whereBetween('s.DATENEW', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            // Validate all product categories (removed F&V filter for full store validation)
            ->selectRaw('
                DATE(s.DATENEW) as sale_date,
                SUM(ABS(s.UNITS)) as daily_units,
                SUM(ABS(s.UNITS) * s.PRICE) as daily_revenue,
                COUNT(DISTINCT s.PRODUCT) as product_count,
                COUNT(*) as daily_transactions
            ')
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get()
            ->keyBy('sale_date');
        
        $comparison = [];
        $period = collect();
        $current = $startDate->copy();
        
        while ($current->lte($endDate)) {
            $dateStr = $current->toDateString();
            $imported = $importedDaily->get($dateStr);
            $pos = $posDaily->get($dateStr);
            
            $comparison[] = [
                'date' => $dateStr,
                'imported' => $imported ? [
                    'units' => (float) $imported->daily_units,
                    'revenue' => (float) $imported->daily_revenue,
                    'products' => (int) $imported->product_count,
                    'transactions' => (int) $imported->daily_transactions
                ] : null,
                'pos' => $pos ? [
                    'units' => (float) $pos->daily_units,
                    'revenue' => (float) $pos->daily_revenue,
                    'products' => (int) $pos->product_count,
                    'transactions' => (int) $pos->daily_transactions
                ] : null,
                'status' => $this->getDailyComparisonStatus($imported, $pos),
                'variances' => $this->calculateDailyVariances($imported, $pos)
            ];
            
            $current->addDay();
        }
        
        return $comparison;
    }
    
    /**
     * Get category-level validation
     */
    public function getCategoryValidation(Carbon $startDate, Carbon $endDate): array
    {
        // Imported category totals
        $importedCategories = SalesDailySummary::whereBetween('sale_date', [$startDate, $endDate])
            ->selectRaw('
                category_id,
                SUM(total_units) as total_units,
                SUM(total_revenue) as total_revenue,
                COUNT(DISTINCT product_id) as unique_products,
                SUM(transaction_count) as total_transactions
            ')
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');
        
        // POS category totals
        $posCategories = DB::connection('pos')
            ->table('STOCKDIARY as s')
            ->join('PRODUCTS as p', 's.PRODUCT', '=', 'p.ID')
            ->where('s.REASON', -1)
            ->whereBetween('s.DATENEW', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            // Validate all product categories (removed F&V filter for full store validation)
            ->selectRaw('
                p.CATEGORY as category_id,
                SUM(ABS(s.UNITS)) as total_units,
                SUM(ABS(s.UNITS) * s.PRICE) as total_revenue,
                COUNT(DISTINCT s.PRODUCT) as unique_products,
                COUNT(*) as total_transactions
            ')
            ->groupBy('p.CATEGORY')
            ->get()
            ->keyBy('category_id');
        
        // Get all categories that appear in either imported or POS data
        $allCategories = $importedCategories->keys()->merge($posCategories->keys())->unique()->sort();
        $comparison = [];
        
        foreach ($allCategories as $categoryId) {
            $imported = $importedCategories->get($categoryId);
            $pos = $posCategories->get($categoryId);
            
            // Get a friendly category name (you can expand this mapping as needed)
            $categoryName = match($categoryId) {
                'SUB1' => 'Fruits',
                'SUB2' => 'Vegetables', 
                'SUB3' => 'Veg Barcoded',
                default => 'Category ' . $categoryId
            };
            
            $comparison[] = [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'imported' => $imported ? [
                    'units' => (float) $imported->total_units,
                    'revenue' => (float) $imported->total_revenue,
                    'products' => (int) $imported->unique_products,
                    'transactions' => (int) $imported->total_transactions
                ] : null,
                'pos' => $pos ? [
                    'units' => (float) $pos->total_units,
                    'revenue' => (float) $pos->total_revenue,
                    'products' => (int) $pos->unique_products,
                    'transactions' => (int) $pos->total_transactions
                ] : null,
                'status' => $this->getCategoryComparisonStatus($imported, $pos),
                'variances' => $this->calculateCategoryVariances($imported, $pos)
            ];
        }
        
        return $comparison;
    }
    
    /**
     * Get imported sales data for validation
     */
    private function getImportedSalesData(Carbon $startDate, Carbon $endDate): Collection
    {
        return SalesDailySummary::whereBetween('sale_date', [$startDate, $endDate])
            ->orderBy('sale_date')
            ->orderBy('product_id')
            ->get();
    }
    
    /**
     * Get POS sales data using the same query as import
     */
    private function getPOSSalesDataForValidation(Carbon $startDate, Carbon $endDate): Collection
    {
        return DB::connection('pos')
            ->table('STOCKDIARY as s')
            ->join('PRODUCTS as p', 's.PRODUCT', '=', 'p.ID')
            ->where('s.REASON', -1)
            ->whereBetween('s.DATENEW', [$startDate->startOfDay(), $endDate->endOfDay()])
            // Validate all product categories (removed F&V filter for full store validation)
            ->select(
                's.PRODUCT as product_id',
                'p.CODE as product_code',
                'p.NAME as product_name',
                'p.CATEGORY as category_id',
                DB::raw('DATE(s.DATENEW) as sale_date'),
                DB::raw('SUM(ABS(s.UNITS)) as total_units'),
                DB::raw('SUM(ABS(s.UNITS) * s.PRICE) as total_revenue'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('CASE WHEN SUM(ABS(s.UNITS)) > 0 THEN SUM(ABS(s.UNITS) * s.PRICE) / SUM(ABS(s.UNITS)) ELSE 0 END as avg_price')
            )
            ->groupBy('s.PRODUCT', 'p.CODE', 'p.NAME', 'p.CATEGORY', 'sale_date')
            ->orderBy('sale_date')
            ->orderBy('s.PRODUCT')
            ->get();
    }
    
    /**
     * Compare two datasets and identify discrepancies
     */
    private function compareDatasets(Collection $imported, Collection $pos): array
    {
        $importedByKey = $imported->keyBy(function ($item) {
            return $item->product_id . '-' . $item->sale_date->format('Y-m-d');
        });
        
        $posByKey = $pos->keyBy(function ($item) {
            return $item->product_id . '-' . $item->sale_date;
        });
        
        $matches = 0;
        $discrepancies = [];
        $tolerance = 0.01; // 1 cent tolerance for revenue differences
        
        foreach ($posByKey as $key => $posRecord) {
            $importedRecord = $importedByKey->get($key);
            
            if (!$importedRecord) {
                $discrepancies[] = [
                    'type' => 'missing_in_imported',
                    'key' => $key,
                    'pos_data' => $posRecord
                ];
                continue;
            }
            
            // Check for discrepancies
            $revenueMatch = abs($importedRecord->total_revenue - $posRecord->total_revenue) <= $tolerance;
            $unitsMatch = abs($importedRecord->total_units - $posRecord->total_units) <= 0.01;
            $transactionsMatch = $importedRecord->transaction_count == $posRecord->transaction_count;
            
            if ($revenueMatch && $unitsMatch && $transactionsMatch) {
                $matches++;
            } else {
                $discrepancies[] = [
                    'type' => 'data_mismatch',
                    'key' => $key,
                    'imported_data' => $importedRecord,
                    'pos_data' => $posRecord,
                    'differences' => [
                        'revenue' => !$revenueMatch,
                        'units' => !$unitsMatch,
                        'transactions' => !$transactionsMatch
                    ]
                ];
            }
        }
        
        // Check for extra records in imported
        $extraInImported = $importedByKey->keys()->diff($posByKey->keys());
        
        foreach ($extraInImported as $key) {
            $discrepancies[] = [
                'type' => 'extra_in_imported',
                'key' => $key,
                'imported_data' => $importedByKey->get($key)
            ];
        }
        
        // Calculate totals comparison
        $importedTotals = [
            'revenue' => $imported->sum('total_revenue'),
            'units' => $imported->sum('total_units'),
            'transactions' => $imported->sum('transaction_count')
        ];
        
        $posTotals = [
            'revenue' => $pos->sum('total_revenue'),
            'units' => $pos->sum('total_units'),
            'transactions' => $pos->sum('transaction_count')
        ];
        
        $totalRecords = max($imported->count(), $pos->count());
        $accuracyPercentage = $totalRecords > 0 ? ($matches / $totalRecords) * 100 : 0;
        
        return [
            'matches' => $matches,
            'discrepancies' => count($discrepancies),
            'missing_in_imported' => $posByKey->count() - $importedByKey->count(),
            'extra_in_imported' => $extraInImported->count(),
            'accuracy_percentage' => round($accuracyPercentage, 2),
            'detailed_discrepancies' => $discrepancies,
            'totals_comparison' => [
                'imported' => $importedTotals,
                'pos' => $posTotals,
                'variance' => [
                    'revenue' => $importedTotals['revenue'] - $posTotals['revenue'],
                    'units' => $importedTotals['units'] - $posTotals['units'],
                    'transactions' => $importedTotals['transactions'] - $posTotals['transactions']
                ]
            ]
        ];
    }
    
    /**
     * Get comparison status for two records
     */
    private function getComparisonStatus($imported, $pos): string
    {
        if (!$imported && !$pos) return 'both_missing';
        if (!$imported) return 'missing_imported';
        if (!$pos) return 'extra_imported';
        
        $tolerance = 0.01;
        $revenueMatch = abs($imported->total_revenue - $pos->total_revenue) <= $tolerance;
        $unitsMatch = abs($imported->total_units - $pos->total_units) <= 0.01;
        $transactionsMatch = $imported->transaction_count == $pos->transaction_count;
        
        if ($revenueMatch && $unitsMatch && $transactionsMatch) {
            return 'perfect_match';
        } elseif ($revenueMatch && $unitsMatch) {
            return 'minor_variance';
        } else {
            return 'significant_variance';
        }
    }
    
    /**
     * Calculate variances between imported and POS data
     */
    private function calculateVariances($imported, $pos): array
    {
        if (!$imported || !$pos) {
            return ['revenue' => null, 'units' => null, 'transactions' => null, 'avg_price' => null];
        }
        
        return [
            'revenue' => round($imported->total_revenue - $pos->total_revenue, 2),
            'units' => round($imported->total_units - $pos->total_units, 2),
            'transactions' => $imported->transaction_count - $pos->transaction_count,
            'avg_price' => round($imported->avg_price - $pos->avg_price, 2)
        ];
    }
    
    /**
     * Get daily comparison status
     */
    private function getDailyComparisonStatus($imported, $pos): string
    {
        if (!$imported && !$pos) return 'no_data';
        if (!$imported) return 'missing_imported';
        if (!$pos) return 'extra_imported';
        
        $tolerance = 1.00; // $1 tolerance for daily totals
        $revenueMatch = abs($imported->daily_revenue - $pos->daily_revenue) <= $tolerance;
        
        return $revenueMatch ? 'match' : 'variance';
    }
    
    /**
     * Calculate daily variances
     */
    private function calculateDailyVariances($imported, $pos): array
    {
        if (!$imported || !$pos) {
            return ['units' => null, 'revenue' => null, 'products' => null, 'transactions' => null];
        }
        
        return [
            'units' => round($imported->daily_units - $pos->daily_units, 2),
            'revenue' => round($imported->daily_revenue - $pos->daily_revenue, 2),
            'products' => $imported->product_count - $pos->product_count,
            'transactions' => $imported->daily_transactions - $pos->daily_transactions
        ];
    }
    
    /**
     * Get category comparison status
     */
    private function getCategoryComparisonStatus($imported, $pos): string
    {
        if (!$imported && !$pos) return 'no_data';
        if (!$imported) return 'missing_imported';
        if (!$pos) return 'extra_imported';
        
        $tolerance = 5.00; // $5 tolerance for category totals
        $revenueMatch = abs($imported->total_revenue - $pos->total_revenue) <= $tolerance;
        
        return $revenueMatch ? 'match' : 'variance';
    }
    
    /**
     * Calculate category variances
     */
    private function calculateCategoryVariances($imported, $pos): array
    {
        if (!$imported || !$pos) {
            return ['units' => null, 'revenue' => null, 'products' => null, 'transactions' => null];
        }
        
        return [
            'units' => round($imported->total_units - $pos->total_units, 2),
            'revenue' => round($imported->total_revenue - $pos->total_revenue, 2),
            'products' => $imported->unique_products - $pos->unique_products,
            'transactions' => $imported->total_transactions - $pos->total_transactions
        ];
    }
}