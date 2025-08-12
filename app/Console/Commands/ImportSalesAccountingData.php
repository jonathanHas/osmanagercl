<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportSalesAccountingData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales-accounting:import 
                            {--start-date= : Start date (YYYY-MM-DD)}
                            {--end-date= : End date (YYYY-MM-DD)} 
                            {--days=7 : Number of days to import (default: 7)}
                            {--force : Force re-import existing data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import sales accounting data from POS database for optimized reporting';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting sales accounting data import...');

        // Determine date range
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();

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
                $result = $this->importDayData($currentDate);
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

    /**
     * Get the start date for import
     */
    private function getStartDate(): Carbon
    {
        if ($this->option('start-date')) {
            return Carbon::parse($this->option('start-date'));
        }

        $days = (int) $this->option('days');

        return Carbon::now()->subDays($days - 1);
    }

    /**
     * Get the end date for import
     */
    private function getEndDate(): Carbon
    {
        if ($this->option('end-date')) {
            return Carbon::parse($this->option('end-date'));
        }

        return Carbon::now();
    }

    /**
     * Import data for a specific day
     */
    private function importDayData(Carbon $date): array
    {
        $inserted = 0;
        $updated = 0;

        // Check if data already exists and we're not forcing
        if (! $this->option('force')) {
            $existingSales = DB::table('sales_accounting_daily')
                ->where('sale_date', $date->format('Y-m-d'))
                ->exists();

            $existingTransfers = DB::table('stock_transfer_daily')
                ->where('transfer_date', $date->format('Y-m-d'))
                ->exists();

            if ($existingSales || $existingTransfers) {
                return ['inserted' => 0, 'updated' => 0];
            }
        }

        DB::beginTransaction();

        try {
            // Import main sales data (excluding Kitchen and Coffee customers)
            $salesResult = $this->importMainSalesData($date);
            $inserted += $salesResult['inserted'];
            $updated += $salesResult['updated'];

            // Import stock transfer data (Kitchen and Coffee customers)
            $transferResult = $this->importStockTransferData($date);
            $inserted += $transferResult['inserted'];
            $updated += $transferResult['updated'];

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * Import main sales data for a day
     */
    private function importMainSalesData(Carbon $date): array
    {
        $formattedDate = $date->format('Y m d');

        // Query main sales (excluding Kitchen and Coffee customers)
        $salesData = DB::connection('pos')->select("
            SELECT 
                TAXES.RATE,
                SUM(PRICE * UNITS) AS Net,
                PAYMENTS.PAYMENT,
                COUNT(DISTINCT RECEIPTS.ID) as TransactionCount
            FROM TICKETLINES
            JOIN TICKETS ON TICKETLINES.TICKET = TICKETS.ID
            JOIN RECEIPTS ON TICKETS.ID = RECEIPTS.ID
            JOIN PAYMENTS ON RECEIPTS.ID = PAYMENTS.RECEIPT
            JOIN TAXES ON TICKETLINES.TAXID = TAXES.ID
            LEFT JOIN CUSTOMERS ON TICKETS.CUSTOMER = CUSTOMERS.ID
            WHERE DATE_FORMAT(DATENEW, '%Y %m %d') = ?
            AND (CUSTOMERS.NAME IS NULL OR CUSTOMERS.NAME NOT IN ('Kitchen', 'Coffee'))
            GROUP BY PAYMENTS.PAYMENT, TAXES.RATE
        ", [$formattedDate]);

        $inserted = 0;
        $updated = 0;

        foreach ($salesData as $sale) {
            $netAmount = $sale->Net;
            $vatAmount = $netAmount * $sale->RATE;
            $grossAmount = $netAmount + $vatAmount;

            $data = [
                'sale_date' => $date->format('Y-m-d'),
                'payment_type' => $sale->PAYMENT,
                'vat_rate' => $sale->RATE,
                'net_amount' => $netAmount,
                'vat_amount' => $vatAmount,
                'gross_amount' => $grossAmount,
                'transaction_count' => $sale->TransactionCount,
                'updated_at' => now(),
            ];

            if ($this->option('force')) {
                // Update or insert
                $exists = DB::table('sales_accounting_daily')
                    ->where('sale_date', $date->format('Y-m-d'))
                    ->where('payment_type', $sale->PAYMENT)
                    ->where('vat_rate', $sale->RATE)
                    ->exists();

                if ($exists) {
                    DB::table('sales_accounting_daily')
                        ->where('sale_date', $date->format('Y-m-d'))
                        ->where('payment_type', $sale->PAYMENT)
                        ->where('vat_rate', $sale->RATE)
                        ->update($data);
                    $updated++;
                } else {
                    $data['created_at'] = now();
                    DB::table('sales_accounting_daily')->insert($data);
                    $inserted++;
                }
            } else {
                // Insert only
                $data['created_at'] = now();
                try {
                    DB::table('sales_accounting_daily')->insert($data);
                    $inserted++;
                } catch (\Exception $e) {
                    // Record might already exist, skip
                }
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * Import stock transfer data for a day
     */
    private function importStockTransferData(Carbon $date): array
    {
        $formattedDate = $date->format('Y m d');

        // Query stock transfers (Kitchen and Coffee customers only)
        $transferData = DB::connection('pos')->select("
            SELECT 
                TAXES.RATE,
                SUM(PRICE * UNITS) AS Net,
                SUM(PRICE * RATE * UNITS) AS VATtotals,
                CUSTOMERS.NAME as Department,
                COUNT(DISTINCT RECEIPTS.ID) as TransactionCount
            FROM TICKETLINES
            JOIN TICKETS ON TICKETLINES.TICKET = TICKETS.ID
            JOIN RECEIPTS ON TICKETS.ID = RECEIPTS.ID
            JOIN TAXES ON TICKETLINES.TAXID = TAXES.ID
            JOIN CUSTOMERS ON TICKETS.CUSTOMER = CUSTOMERS.ID
            WHERE DATE_FORMAT(DATENEW, '%Y %m %d') = ?
            AND CUSTOMERS.NAME IN ('Kitchen', 'Coffee')
            GROUP BY CUSTOMERS.NAME, TAXES.RATE
        ", [$formattedDate]);

        $inserted = 0;
        $updated = 0;

        foreach ($transferData as $transfer) {
            $netAmount = $transfer->Net;
            $vatAmount = $transfer->VATtotals;
            $grossAmount = $netAmount + $vatAmount;

            $data = [
                'transfer_date' => $date->format('Y-m-d'),
                'department' => $transfer->Department,
                'vat_rate' => $transfer->RATE,
                'net_amount' => $netAmount,
                'vat_amount' => $vatAmount,
                'gross_amount' => $grossAmount,
                'transaction_count' => $transfer->TransactionCount,
                'updated_at' => now(),
            ];

            if ($this->option('force')) {
                // Update or insert
                $exists = DB::table('stock_transfer_daily')
                    ->where('transfer_date', $date->format('Y-m-d'))
                    ->where('department', $transfer->Department)
                    ->where('vat_rate', $transfer->RATE)
                    ->exists();

                if ($exists) {
                    DB::table('stock_transfer_daily')
                        ->where('transfer_date', $date->format('Y-m-d'))
                        ->where('department', $transfer->Department)
                        ->where('vat_rate', $transfer->RATE)
                        ->update($data);
                    $updated++;
                } else {
                    $data['created_at'] = now();
                    DB::table('stock_transfer_daily')->insert($data);
                    $inserted++;
                }
            } else {
                // Insert only
                $data['created_at'] = now();
                try {
                    DB::table('stock_transfer_daily')->insert($data);
                    $inserted++;
                } catch (\Exception $e) {
                    // Record might already exist, skip
                }
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }
}
