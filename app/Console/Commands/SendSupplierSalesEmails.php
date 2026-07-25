<?php

namespace App\Console\Commands;

use App\Mail\SupplierDailySalesMail;
use App\Models\AccountingSupplier;
use App\Services\SalesImportService;
use App\Services\SupplierSalesReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSupplierSalesEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:send-daily-sales
                          {--date= : Report date (YYYY-MM-DD), defaults to today}
                          {--supplier= : Limit to a single AccountingSupplier id (for testing)}
                          {--dry-run : Build reports and log them without sending email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Email opted-in suppliers a same-day report of their products\' sales';

    public function handle(
        SupplierSalesReportService $reportService,
        SalesImportService $importService
    ): int {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        $dryRun = (bool) $this->option('dry-run');

        // Ensure the report date's sales are imported (idempotent upsert).
        // Belt-and-braces even though the 20:00 sales:import-daily --today run should have covered it.
        $this->info("Refreshing sales data for {$date->toDateString()}...");
        try {
            $importService->importDailySales($date->copy(), $date->copy());
        } catch (\Throwable $e) {
            $this->warn('Sales refresh failed (continuing with existing data): '.$e->getMessage());
            Log::warning('Supplier daily email: sales refresh failed', [
                'date' => $date->toDateString(),
                'error' => $e->getMessage(),
            ]);
        }

        $query = AccountingSupplier::receivesDailySalesEmail();
        if ($supplierId = $this->option('supplier')) {
            $query->where('id', $supplierId);
        }
        $suppliers = $query->get();

        if ($suppliers->isEmpty()) {
            $this->info('No suppliers opted in for the daily sales email.');

            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($suppliers as $supplier) {
            $report = $reportService->buildReport($supplier, $date);

            if ($report === null) {
                $skipped++;
                $this->line("  - {$supplier->name}: no sales on {$date->toDateString()}, skipped");

                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '  - %s <%s>: %d products, %s units, €%s (DRY RUN)',
                    $supplier->name,
                    $supplier->email,
                    $report['totals']['lines'],
                    rtrim(rtrim(number_format($report['totals']['units'], 2), '0'), '.'),
                    number_format($report['totals']['revenue'], 2)
                ));
                $sent++;

                continue;
            }

            try {
                Mail::to($supplier->email)->queue(new SupplierDailySalesMail($report));
                $sent++;
                $this->line("  - {$supplier->name} <{$supplier->email}>: queued");
            } catch (\Throwable $e) {
                $this->error("  - {$supplier->name}: failed to queue — ".$e->getMessage());
                Log::error('Supplier daily email: queue failed', [
                    'supplier_id' => $supplier->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf(
            '%s %d supplier email(s); skipped %d with no sales.',
            $dryRun ? 'Would send' : 'Queued',
            $sent,
            $skipped
        ));

        return self::SUCCESS;
    }
}
