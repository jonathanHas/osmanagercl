<?php

namespace App\Console\Commands;

use App\Repositories\OptimizedSalesRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TestOptimizedSalesRepository extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:test-repository';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the optimized sales repository performance';

    /**
     * Execute the console command.
     */
    public function handle(OptimizedSalesRepository $repository)
    {
        $this->info('Testing OptimizedSalesRepository...');

        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        $this->info("Testing date range: {$startDate->toDateString()} to {$endDate->toDateString()}");

        // Test 1: Get sales stats
        $this->info("\n1. Testing getFruitVegSalesStats...");
        $startTime = microtime(true);

        $stats = $repository->getFruitVegSalesStats($startDate, $endDate);
        $executionTime = microtime(true) - $startTime;

        $this->info('Execution time: '.round($executionTime * 1000, 2).'ms');
        $this->table(['Metric', 'Value'], [
            ['Total Units', number_format($stats['total_units'], 2)],
            ['Total Revenue', '€'.number_format($stats['total_revenue'], 2)],
            ['Unique Products', $stats['unique_products']],
            ['Total Transactions', $stats['total_transactions']],
        ]);

        // Test 2: Get daily sales
        $this->info("\n2. Testing getFruitVegDailySales...");
        $startTime = microtime(true);

        $dailySales = $repository->getFruitVegDailySales($startDate, $endDate);
        $executionTime = microtime(true) - $startTime;

        $this->info('Execution time: '.round($executionTime * 1000, 2).'ms');
        $this->info("Found {$dailySales->count()} daily records");

        // Test 3: Get top products
        $this->info("\n3. Testing getTopFruitVegProducts...");
        $startTime = microtime(true);

        $topProducts = $repository->getTopFruitVegProducts($startDate, $endDate, 5);
        $executionTime = microtime(true) - $startTime;

        $this->info('Execution time: '.round($executionTime * 1000, 2).'ms');

        if ($topProducts->count() > 0) {
            $this->table(['Product', 'Units', 'Revenue'],
                $topProducts->map(function ($product) {
                    return [
                        $product->product_name,
                        number_format($product->total_units, 2),
                        '€'.number_format($product->total_revenue, 2),
                    ];
                })->toArray()
            );
        }

        // Test 4: Get category performance
        $this->info("\n4. Testing getCategoryPerformance...");
        $startTime = microtime(true);

        $categoryPerformance = $repository->getCategoryPerformance($startDate, $endDate);
        $executionTime = microtime(true) - $startTime;

        $this->info('Execution time: '.round($executionTime * 1000, 2).'ms');

        if ($categoryPerformance->count() > 0) {
            $this->table(['Category', 'Units', 'Revenue', 'Products'],
                $categoryPerformance->map(function ($category) {
                    return [
                        $category->category_name,
                        number_format($category->total_units, 2),
                        '€'.number_format($category->total_revenue, 2),
                        $category->unique_products,
                    ];
                })->toArray()
            );
        }

        // Test 5: Get sales summary
        $this->info("\n5. Testing getSalesSummary...");
        $startTime = microtime(true);

        $summary = $repository->getSalesSummary($startDate, $endDate);
        $executionTime = microtime(true) - $startTime;

        $this->info('Execution time: '.round($executionTime * 1000, 2).'ms');
        $this->table(['Period Info', 'Value'], [
            ['Date Range', $summary['period']['start_date'].' to '.$summary['period']['end_date']],
            ['Total Days', $summary['period']['days']],
            ['Active Days', $summary['period']['active_days']],
        ]);

        $this->table(['Totals', 'Value'], [
            ['Units', number_format($summary['totals']['units'], 2)],
            ['Revenue', '€'.number_format($summary['totals']['revenue'], 2)],
            ['Transactions', number_format($summary['totals']['transactions'])],
            ['Unique Products', $summary['totals']['unique_products']],
            ['Avg Price', '€'.number_format($summary['totals']['avg_price'], 2)],
        ]);

        $this->table(['Daily Averages', 'Value'], [
            ['Units', number_format($summary['daily_averages']['units'], 2)],
            ['Revenue', '€'.number_format($summary['daily_averages']['revenue'], 2)],
            ['Transactions', number_format($summary['daily_averages']['transactions'], 2)],
        ]);

        $this->info("\n✅ All tests completed successfully!");

        return 0;
    }
}
