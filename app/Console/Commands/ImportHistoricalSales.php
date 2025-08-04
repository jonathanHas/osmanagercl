<?php

namespace App\Console\Commands;

use App\Services\SalesImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportHistoricalSales extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:import-historical 
                          {--start-date= : Start date (YYYY-MM-DD)}
                          {--chunk-days=30 : Days per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import historical sales data in chunks';

    /**
     * Execute the console command.
     */
    public function handle(SalesImportService $importService)
    {
        $startDate = $this->option('start-date')
            ? Carbon::parse($this->option('start-date'))
            : Carbon::now()->subYears(2); // Default to 2 years ago

        $chunkDays = (int) $this->option('chunk-days');
        $endDate = Carbon::now();

        $this->info("Importing historical sales data from {$startDate->toDateString()} to {$endDate->toDateString()}");
        $this->info("Processing in {$chunkDays}-day chunks...");

        $current = $startDate->copy();
        $totalLogs = [];

        while ($current->lessThan($endDate)) {
            $chunkEnd = $current->copy()->addDays($chunkDays - 1);
            if ($chunkEnd->greaterThan($endDate)) {
                $chunkEnd = $endDate;
            }

            $this->info("Processing chunk: {$current->toDateString()} to {$chunkEnd->toDateString()}");

            try {
                $log = $importService->importDailySales($current, $chunkEnd);
                $totalLogs[] = $log;

                $this->info("Chunk completed: {$log->records_processed} records in {$log->execution_time_seconds}s");
            } catch (\Exception $e) {
                $this->error('Chunk failed: '.$e->getMessage());

                return 1;
            }

            $current->addDays($chunkDays);
        }

        // Summary
        $totalProcessed = collect($totalLogs)->sum('records_processed');
        $totalInserted = collect($totalLogs)->sum('records_inserted');
        $totalTime = collect($totalLogs)->sum('execution_time_seconds');

        $this->info("\nHistorical import completed!");
        $this->table(['Metric', 'Value'], [
            ['Total Records Processed', number_format($totalProcessed)],
            ['Total Records Inserted', number_format($totalInserted)],
            ['Total Execution Time', round($totalTime, 2).' seconds'],
            ['Average Records/Second', $totalTime > 0 ? round($totalProcessed / $totalTime, 2) : 0],
        ]);

        return 0;
    }
}
