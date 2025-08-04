<?php

namespace App\Http\Controllers;

use App\Models\SalesDailySummary;
use App\Models\SalesImportLog;
use App\Models\SalesMonthlySummary;
use App\Repositories\OptimizedSalesRepository;
use App\Services\SalesImportService;
use App\Services\SalesValidationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SalesImportController extends Controller
{
    protected $optimizedRepository;

    protected $importService;

    protected $validationService;

    public function __construct(
        OptimizedSalesRepository $optimizedRepository,
        SalesImportService $importService,
        SalesValidationService $validationService
    ) {
        $this->optimizedRepository = $optimizedRepository;
        $this->importService = $importService;
        $this->validationService = $validationService;
    }

    /**
     * Show the sales import dashboard
     */
    public function index()
    {
        // Get recent import logs
        $recentImports = SalesImportLog::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get system statistics
        $dailyRecordCount = SalesDailySummary::count();
        $monthlyRecordCount = SalesMonthlySummary::count();
        $latestImport = SalesImportLog::where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();

        // Get date range of available data
        $dateRange = SalesDailySummary::selectRaw('MIN(sale_date) as earliest, MAX(sale_date) as latest')
            ->first();

        // Performance test data (last 7 days)
        $performanceData = null;
        if ($dailyRecordCount > 0) {
            $startTime = microtime(true);
            $performanceData = $this->optimizedRepository->getFruitVegSalesStats(
                Carbon::now()->subDays(7),
                Carbon::now()
            );
            $performanceData['execution_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        }

        return view('sales-import.index', compact(
            'recentImports',
            'dailyRecordCount',
            'monthlyRecordCount',
            'latestImport',
            'dateRange',
            'performanceData'
        ));
    }

    /**
     * Run daily import via web interface
     */
    public function runDailyImport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $log = $this->importService->importDailySales($startDate, $endDate);

            return response()->json([
                'success' => true,
                'message' => 'Import completed successfully!',
                'data' => [
                    'records_processed' => $log->records_processed,
                    'records_inserted' => $log->records_inserted,
                    'records_updated' => $log->records_updated,
                    'execution_time' => $log->execution_time_seconds,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run monthly summaries generation
     */
    public function runMonthlySummaries(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:'.(date('Y') + 1),
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        try {
            $log = $this->importService->importMonthlySummaries(
                $request->year,
                $request->month
            );

            return response()->json([
                'success' => true,
                'message' => 'Monthly summaries generated successfully!',
                'data' => [
                    'records_processed' => $log->records_processed,
                    'records_inserted' => $log->records_inserted,
                    'records_updated' => $log->records_updated,
                    'execution_time' => $log->execution_time_seconds,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Monthly summaries failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create test data
     */
    public function createTestData(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        try {
            $exitCode = Artisan::call('sales:create-test-data', [
                '--days' => $request->days,
            ]);

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => "Test data created for {$request->days} days!",
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create test data',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test data creation failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run performance test
     */
    public function performanceTest()
    {
        try {
            $tests = [];
            $startDate = Carbon::now()->subDays(7);
            $endDate = Carbon::now();

            // Test 1: Sales Stats
            $startTime = microtime(true);
            $stats = $this->optimizedRepository->getFruitVegSalesStats($startDate, $endDate);
            $tests['sales_stats'] = [
                'name' => 'Sales Statistics',
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'data' => $stats,
            ];

            // Test 2: Daily Sales
            $startTime = microtime(true);
            $dailySales = $this->optimizedRepository->getFruitVegDailySales($startDate, $endDate);
            $tests['daily_sales'] = [
                'name' => 'Daily Sales Chart Data',
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'record_count' => $dailySales->count(),
            ];

            // Test 3: Top Products
            $startTime = microtime(true);
            $topProducts = $this->optimizedRepository->getTopFruitVegProducts($startDate, $endDate, 5);
            $tests['top_products'] = [
                'name' => 'Top 5 Products',
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'data' => $topProducts->toArray(),
            ];

            // Test 4: Category Performance
            $startTime = microtime(true);
            $categoryPerformance = $this->optimizedRepository->getCategoryPerformance($startDate, $endDate);
            $tests['category_performance'] = [
                'name' => 'Category Performance',
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'data' => $categoryPerformance->toArray(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Performance test completed successfully!',
                'tests' => $tests,
                'total_time_ms' => array_sum(array_column($tests, 'execution_time_ms')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Performance test failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get import logs via AJAX
     */
    public function getImportLogs()
    {
        $logs = SalesImportLog::orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'type' => ucfirst($log->import_type),
                    'date_range' => $log->start_date && $log->end_date
                        ? $log->start_date->format('M j').' - '.$log->end_date->format('M j, Y')
                        : 'N/A',
                    'records_processed' => number_format($log->records_processed ?? 0),
                    'records_inserted' => number_format($log->records_inserted ?? 0),
                    'records_updated' => number_format($log->records_updated ?? 0),
                    'execution_time' => $log->execution_time_seconds ? round($log->execution_time_seconds, 2).'s' : 'N/A',
                    'status' => $log->status,
                    'status_class' => match ($log->status) {
                        'completed' => 'bg-green-100 text-green-800',
                        'failed' => 'bg-red-100 text-red-800',
                        'running' => 'bg-yellow-100 text-yellow-800',
                        default => 'bg-gray-100 text-gray-800'
                    },
                    'created_at' => $log->created_at->format('M j, Y g:i A'),
                    'error_message' => $log->error_message,
                ];
            });

        return response()->json($logs);
    }

    /**
     * Clear all imported data (for testing)
     */
    public function clearData()
    {
        try {
            DB::transaction(function () {
                SalesDailySummary::truncate();
                SalesMonthlySummary::truncate();
                SalesImportLog::truncate();
            });

            return response()->json([
                'success' => true,
                'message' => 'All imported data cleared successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show validation interface
     */
    public function validation()
    {
        // Get basic stats for validation page
        $dailyRecordCount = SalesDailySummary::count();
        $dateRange = SalesDailySummary::selectRaw('MIN(sale_date) as earliest, MAX(sale_date) as latest')
            ->first();

        return view('sales-import.validation', compact('dailyRecordCount', 'dateRange'));
    }

    /**
     * Run data validation for a date range
     */
    public function validateData(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $validation = $this->validationService->validateDateRange($startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $validation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get comparison data for detailed view
     */
    public function getComparisonData(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $comparison = $this->validationService->getComparisonData($startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $comparison,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Comparison failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get daily summary comparison
     */
    public function getDailySummary(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $comparison = $this->validationService->getDailySummaryComparison($startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $comparison,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Daily summary failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get category validation
     */
    public function getCategoryValidation(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $comparison = $this->validationService->getCategoryValidation($startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $comparison,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category validation failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
