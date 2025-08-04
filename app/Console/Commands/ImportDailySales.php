<?php

namespace App\Console\Commands;

use App\Services\SalesImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportDailySales extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:import-daily 
                          {--start-date= : Start date (YYYY-MM-DD)}
                          {--end-date= : End date (YYYY-MM-DD)}
                          {--yesterday : Import yesterday\'s data}
                          {--last-week : Import last 7 days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import daily sales data from POS system';

    /**
     * Execute the console command.
     */
    public function handle(SalesImportService $importService)
    {
        if ($this->option('yesterday')) {
            $startDate = $endDate = Carbon::yesterday();
        } elseif ($this->option('last-week')) {
            $startDate = Carbon::now()->subDays(7);
            $endDate = Carbon::now();
        } else {
            $startDate = $this->option('start-date')
                ? Carbon::parse($this->option('start-date'))
                : Carbon::yesterday();
            $endDate = $this->option('end-date')
                ? Carbon::parse($this->option('end-date'))
                : $startDate;
        }

        $this->info("Importing sales data from {$startDate->toDateString()} to {$endDate->toDateString()}");

        try {
            $log = $importService->importDailySales($startDate, $endDate);

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
