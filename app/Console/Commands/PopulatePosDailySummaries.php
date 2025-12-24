<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulatePosDailySummaries extends Command
{
    protected $signature = 'pos:populate-daily-summaries
                          {--start-date= : Start date (YYYY-MM-DD)}
                          {--end-date= : End date (YYYY-MM-DD)}
                          {--last-days=365 : Number of days to import (default 365)}
                          {--force : Force re-import of existing dates}';

    protected $description = 'Populate pos_daily_summaries table with payment method breakdowns from POS database';

    public function handle(): int
    {
        $endDate = $this->option('end-date')
            ? Carbon::parse($this->option('end-date'))
            : Carbon::yesterday();

        $startDate = $this->option('start-date')
            ? Carbon::parse($this->option('start-date'))
            : $endDate->copy()->subDays((int) $this->option('last-days'));

        $force = $this->option('force');

        $this->info("Populating POS daily summaries from {$startDate->toDateString()} to {$endDate->toDateString()}");

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $bar = $this->output->createProgressBar($totalDays);
        $bar->start();

        $imported = 0;
        $skipped = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dateStr = $current->format('Y-m-d');

            // Check if already exists
            if (! $force && DB::table('pos_daily_summaries')->where('sale_date', $dateStr)->exists()) {
                $skipped++;
                $bar->advance();
                $current->addDay();

                continue;
            }

            // Fetch and aggregate from POS database
            $summary = $this->fetchDaySummary($current);

            if ($summary) {
                DB::table('pos_daily_summaries')->updateOrInsert(
                    ['sale_date' => $dateStr],
                    [
                        'cash_sales' => $summary['cash_sales'],
                        'cash_refunds' => $summary['cash_refunds'],
                        'card_sales' => $summary['card_sales'],
                        'card_refunds' => $summary['card_refunds'],
                        'debt_sales' => $summary['debt_sales'],
                        'free_sales' => $summary['free_sales'],
                        'total_transactions' => $summary['total_transactions'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $imported++;
            }

            $bar->advance();
            $current->addDay();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Import completed!');
        $this->table(['Metric', 'Value'], [
            ['Days Imported', $imported],
            ['Days Skipped (already exist)', $skipped],
            ['Total Days Processed', $totalDays],
        ]);

        return self::SUCCESS;
    }

    private function fetchDaySummary(Carbon $date): ?array
    {
        try {
            $result = DB::connection('pos')
                ->table('RECEIPTS as r')
                ->join('PAYMENTS as p', 'r.ID', '=', 'p.RECEIPT')
                ->whereDate('r.DATENEW', $date)
                ->selectRaw('
                    COUNT(DISTINCT r.ID) as total_transactions,
                    SUM(CASE WHEN p.PAYMENT = "cash" AND p.TOTAL > 0 THEN p.TOTAL ELSE 0 END) as cash_sales,
                    SUM(CASE WHEN p.PAYMENT = "cashrefund" THEN ABS(p.TOTAL) ELSE 0 END) as cash_refunds,
                    SUM(CASE WHEN p.PAYMENT = "magcard" AND p.TOTAL > 0 THEN p.TOTAL ELSE 0 END) as card_sales,
                    SUM(CASE WHEN p.PAYMENT = "magcardrefund" THEN ABS(p.TOTAL) ELSE 0 END) as card_refunds,
                    SUM(CASE WHEN p.PAYMENT = "debt" THEN p.TOTAL ELSE 0 END) as debt_sales,
                    SUM(CASE WHEN p.PAYMENT = "free" THEN p.TOTAL ELSE 0 END) as free_sales
                ')
                ->first();

            return [
                'cash_sales' => (float) ($result->cash_sales ?? 0),
                'cash_refunds' => (float) ($result->cash_refunds ?? 0),
                'card_sales' => (float) ($result->card_sales ?? 0),
                'card_refunds' => (float) ($result->card_refunds ?? 0),
                'debt_sales' => (float) ($result->debt_sales ?? 0),
                'free_sales' => (float) ($result->free_sales ?? 0),
                'total_transactions' => (int) ($result->total_transactions ?? 0),
            ];
        } catch (\Exception $e) {
            $this->error("Failed to fetch data for {$date->toDateString()}: ".$e->getMessage());

            return null;
        }
    }
}
