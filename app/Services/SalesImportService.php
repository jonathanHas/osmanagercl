<?php

namespace App\Services;

use App\Models\SalesDailySummary;
use App\Models\SalesMonthlySummary;
use App\Models\SalesImportLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesImportService
{
    public function importDailySales(Carbon $startDate, Carbon $endDate): SalesImportLog
    {
        $log = SalesImportLog::create([
            'import_type' => 'daily',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'running'
        ]);
        
        $startTime = microtime(true);
        
        try {
            // Get sales data from POS database with single optimized query
            $salesData = $this->getPOSSalesData($startDate, $endDate);
            
            $processedCount = 0;
            $insertedCount = 0;
            $updatedCount = 0;
            
            // Process in chunks for memory efficiency
            foreach ($salesData->chunk(1000) as $chunk) {
                foreach ($chunk as $sale) {
                    $existing = SalesDailySummary::where('product_id', $sale->product_id)
                        ->where('sale_date', $sale->sale_date)
                        ->first();
                    
                    $data = [
                        'product_id' => $sale->product_id,
                        'product_code' => $sale->product_code,
                        'product_name' => $sale->product_name,
                        'category_id' => $sale->category_id,
                        'sale_date' => $sale->sale_date,
                        'total_units' => $sale->total_units,
                        'total_revenue' => $sale->total_revenue,
                        'transaction_count' => $sale->transaction_count,
                        'avg_price' => $sale->total_units > 0 ? $sale->total_revenue / $sale->total_units : 0,
                    ];
                    
                    if ($existing) {
                        $existing->update($data);
                        $updatedCount++;
                    } else {
                        SalesDailySummary::create($data);
                        $insertedCount++;
                    }
                    
                    $processedCount++;
                }
            }
            
            $executionTime = microtime(true) - $startTime;
            
            $log->update([
                'records_processed' => $processedCount,
                'records_inserted' => $insertedCount,
                'records_updated' => $updatedCount,
                'execution_time_seconds' => $executionTime,
                'status' => 'completed'
            ]);
            
            Log::info("Daily sales import completed", [
                'processed' => $processedCount,
                'inserted' => $insertedCount,
                'updated' => $updatedCount,
                'execution_time' => $executionTime
            ]);
            
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'execution_time_seconds' => microtime(true) - $startTime
            ]);
            
            Log::error("Daily sales import failed", [
                'error' => $e->getMessage(),
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            throw $e;
        }
        
        return $log;
    }
    
    private function getPOSSalesData(Carbon $startDate, Carbon $endDate)
    {
        return DB::connection('pos')
            ->table('STOCKDIARY as s')
            ->join('PRODUCTS as p', 's.PRODUCT', '=', 'p.ID')
            ->where('s.REASON', -1) // Sales only
            ->whereBetween('s.DATENEW', [$startDate->startOfDay(), $endDate->endOfDay()])
            // Import all product categories (removed F&V filter for full store analytics)
            ->select(
                's.PRODUCT as product_id',
                'p.CODE as product_code',
                'p.NAME as product_name',
                'p.CATEGORY as category_id',
                DB::raw('DATE(s.DATENEW) as sale_date'),
                DB::raw('SUM(ABS(s.UNITS)) as total_units'),
                DB::raw('SUM(ABS(s.UNITS) * s.PRICE) as total_revenue'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->groupBy('s.PRODUCT', 'p.CODE', 'p.NAME', 'p.CATEGORY', 'sale_date')
            ->orderBy('sale_date')
            ->get();
    }
    
    public function importMonthlySummaries(int $year, int $month = null): SalesImportLog
    {
        $log = SalesImportLog::create([
            'import_type' => 'monthly',
            'start_date' => Carbon::create($year, $month ?? 1, 1),
            'end_date' => $month ? Carbon::create($year, $month, 1)->endOfMonth() : Carbon::create($year, 12, 31),
            'status' => 'running'
        ]);
        
        $startTime = microtime(true);
        
        try {
            $processedCount = 0;
            $insertedCount = 0;
            $updatedCount = 0;
            
            // If specific month provided, process that month only
            if ($month) {
                $months = [$month];
            } else {
                // Process all months in the year
                $months = range(1, 12);
            }
            
            foreach ($months as $currentMonth) {
                // Aggregate daily data into monthly summaries
                $monthlySummaries = SalesDailySummary::selectRaw('
                    product_id,
                    product_code,
                    product_name,
                    category_id,
                    SUM(total_units) as total_units,
                    SUM(total_revenue) as total_revenue,
                    SUM(transaction_count) as transaction_count,
                    AVG(avg_price) as avg_price,
                    COUNT(DISTINCT sale_date) as days_with_sales
                ')
                ->whereYear('sale_date', $year)
                ->whereMonth('sale_date', $currentMonth)
                ->groupBy('product_id', 'product_code', 'product_name', 'category_id')
                ->get();
                
                foreach ($monthlySummaries as $summary) {
                    $existing = SalesMonthlySummary::where('product_id', $summary->product_id)
                        ->where('year', $year)
                        ->where('month', $currentMonth)
                        ->first();
                    
                    $data = [
                        'product_id' => $summary->product_id,
                        'product_code' => $summary->product_code,
                        'product_name' => $summary->product_name,
                        'category_id' => $summary->category_id,
                        'year' => $year,
                        'month' => $currentMonth,
                        'total_units' => $summary->total_units,
                        'total_revenue' => $summary->total_revenue,
                        'transaction_count' => $summary->transaction_count,
                        'avg_price' => $summary->avg_price,
                        'days_with_sales' => $summary->days_with_sales,
                    ];
                    
                    if ($existing) {
                        $existing->update($data);
                        $updatedCount++;
                    } else {
                        SalesMonthlySummary::create($data);
                        $insertedCount++;
                    }
                    
                    $processedCount++;
                }
            }
            
            $executionTime = microtime(true) - $startTime;
            
            $log->update([
                'records_processed' => $processedCount,
                'records_inserted' => $insertedCount,
                'records_updated' => $updatedCount,
                'execution_time_seconds' => $executionTime,
                'status' => 'completed'
            ]);
            
            Log::info("Monthly sales summaries import completed", [
                'year' => $year,
                'month' => $month,
                'processed' => $processedCount,
                'inserted' => $insertedCount,
                'updated' => $updatedCount,
                'execution_time' => $executionTime
            ]);
            
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'execution_time_seconds' => microtime(true) - $startTime
            ]);
            
            Log::error("Monthly sales summaries import failed", [
                'error' => $e->getMessage(),
                'year' => $year,
                'month' => $month
            ]);
            
            throw $e;
        }
        
        return $log;
    }
    
    public function importHistoricalData(Carbon $startDate, int $chunkDays = 30): array
    {
        $endDate = Carbon::now();
        $current = $startDate->copy();
        $totalLogs = [];
        
        while ($current->lessThan($endDate)) {
            $chunkEnd = $current->copy()->addDays($chunkDays - 1);
            if ($chunkEnd->greaterThan($endDate)) {
                $chunkEnd = $endDate;
            }
            
            $log = $this->importDailySales($current, $chunkEnd);
            $totalLogs[] = $log;
            
            $current->addDays($chunkDays);
        }
        
        return $totalLogs;
    }
}