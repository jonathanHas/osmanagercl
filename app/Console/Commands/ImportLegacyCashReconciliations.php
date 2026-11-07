<?php

namespace App\Console\Commands;

use App\Models\CashReconciliation;
use App\Models\POS\ClosedCash;
use App\Services\Cash\TillCountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill of the old system's end-of-day counts (`money`, `payeePayments`,
 * `dayNotes` on the POS database) into cash_reconciliations.
 *
 * Rewritten 2026-11-07 to go through TillCountService::importFromLegacy(), so the
 * imported rows use the same POS totals, previous-float resolution and variance
 * formula as the new count, and their supplier payments are dated to the till
 * close for the P&L. Rows that already exist are skipped. Closes are processed
 * oldest first so each row's previous float is the row imported just before it.
 */
class ImportLegacyCashReconciliations extends Command
{
    protected $signature = 'cash:import-legacy-reconciliations
                          {--dry-run : Show what would be imported without writing}
                          {--from= : Start date (Y-m-d)}
                          {--to= : End date (Y-m-d)}';

    protected $description = 'Import the old system\'s cash counts from the POS money table into cash_reconciliations';

    public function handle(TillCountService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = DB::connection('pos')
            ->table('money as m')
            ->join('CLOSEDCASH as cc', 'm.ID', '=', 'cc.MONEY')
            ->whereNotNull('cc.DATEEND')
            ->select(['cc.MONEY', 'cc.HOST', 'cc.DATEEND'])
            ->orderBy('cc.DATEEND');

        if ($from = $this->option('from')) {
            $query->where('cc.DATEEND', '>=', $from);
        }
        if ($to = $this->option('to')) {
            $query->where('cc.DATEEND', '<=', $to.' 23:59:59');
        }

        $records = $query->get();
        $existing = CashReconciliation::whereIn('closed_cash_id', $records->pluck('MONEY'))->pluck('closed_cash_id')->flip();

        $toImport = $records->reject(fn ($r) => $existing->has($r->MONEY));

        $this->info("Found {$records->count()} legacy counts; {$existing->count()} already exist; {$toImport->count()} to import.");

        if ($dryRun) {
            $this->warn('DRY RUN: nothing written.');

            return self::SUCCESS;
        }

        $imported = 0;
        $failed = 0;

        $this->withProgressBar($toImport, function ($record) use ($service, &$imported, &$failed) {
            try {
                $session = ClosedCash::find($record->MONEY);
                if (! $session) {
                    $failed++;

                    return;
                }

                $result = $service->importFromLegacy($session, $service->tillByHost($record->HOST));
                $result ? $imported++ : $failed++;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("  Failed for money ID {$record->MONEY}: ".$e->getMessage());
                $failed++;
            }
        });

        $this->newLine(2);
        $this->table(['Metric', 'Count'], [
            ['Imported', number_format($imported)],
            ['Skipped (already exist)', number_format($existing->count())],
            ['Failed', number_format($failed)],
        ]);

        return self::SUCCESS;
    }
}
