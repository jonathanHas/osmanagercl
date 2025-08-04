<?php

namespace App\Console\Commands;

use App\Services\SalesImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportMonthlySummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:import-monthly 
                          {--year= : Year to import (default: current year)}
                          {--month= : Specific month to import (1-12)}
                          {--last-month : Import last month}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import monthly sales summaries from daily data';

    /**
     * Execute the console command.
     */
    public function handle(SalesImportService $importService)
    {
        if ($this->option('last-month')) {
            $date = Carbon::now()->subMonth();
            $year = $date->year;
            $month = $date->month;
        } else {
            $year = (int) ($this->option('year') ?? Carbon::now()->year);
            $month = $this->option('month') ? (int) $this->option('month') : null;
        }

        if ($month) {
            $this->info("Importing monthly summaries for {$year}-{$month}...");
        } else {
            $this->info("Importing monthly summaries for all months in {$year}...");
        }

        try {
            $log = $importService->importMonthlySummaries($year, $month);

            $this->info('Import completed successfully!');
            $this->table(['Metric', 'Value'], [
                ['Records Processed', number_format($log->records_processed)],
                ['Records Inserted', number_format($log->records_inserted)],
                ['Records Updated', number_format($log->records_updated)],
                ['Execution Time', $log->execution_time_seconds.' seconds'],
            ]);

        } catch (\Exception $e) {
            $this->error('Import failed: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
