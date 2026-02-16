<?php

namespace App\Console\Commands;

use App\Services\SalesAccountingImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportSalesAccountingData extends Command
{
    protected $signature = 'sales-accounting:import
                            {--start-date= : Start date (YYYY-MM-DD)}
                            {--end-date= : End date (YYYY-MM-DD)}
                            {--days=7 : Number of days to import (default: 7)}
                            {--force : Force re-import existing data}';

    protected $description = 'Import sales accounting data from POS database for optimized reporting';

    public function handle(SalesAccountingImportService $service)
    {
        $this->info('Starting sales accounting data import...');

        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        $force = $this->option('force');

        $this->info("Importing data from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $this->output->progressStart($totalDays);

        $currentDate = $startDate->copy();
        $processedDays = 0;
        $totalRecordsInserted = 0;
        $totalRecordsUpdated = 0;

        while ($currentDate->lte($endDate)) {
            $this->output->progressAdvance();

            try {
                if ($force) {
                    $result = $service->forceImportDay($currentDate);
                } else {
                    // Check if data already exists
                    $exists = DB::table('sales_accounting_daily')
                        ->where('sale_date', $currentDate->format('Y-m-d'))
                        ->exists();

                    if ($exists) {
                        $currentDate->addDay();

                        continue;
                    }

                    $result = $service->importDay($currentDate);
                }

                $processedDays++;
                $totalRecordsInserted += $result['inserted'];
                $totalRecordsUpdated += $result['updated'];

                if ($result['inserted'] > 0 || $result['updated'] > 0) {
                    $this->line("\n  {$currentDate->format('Y-m-d')}: {$result['inserted']} inserted, {$result['updated']} updated");
                }
            } catch (\Exception $e) {
                $this->error("\nError processing {$currentDate->format('Y-m-d')}: ".$e->getMessage());
            }

            $currentDate->addDay();
        }

        $this->output->progressFinish();

        $this->info("\nImport completed successfully!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Days Processed', $processedDays],
                ['Records Inserted', $totalRecordsInserted],
                ['Records Updated', $totalRecordsUpdated],
                ['Total Records', $totalRecordsInserted + $totalRecordsUpdated],
            ]
        );

        return self::SUCCESS;
    }

    private function getStartDate(): Carbon
    {
        if ($this->option('start-date')) {
            return Carbon::parse($this->option('start-date'));
        }

        return Carbon::now()->subDays((int) $this->option('days') - 1);
    }

    private function getEndDate(): Carbon
    {
        if ($this->option('end-date')) {
            return Carbon::parse($this->option('end-date'));
        }

        return Carbon::now();
    }
}
