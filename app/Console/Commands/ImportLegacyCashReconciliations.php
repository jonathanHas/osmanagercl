<?php

namespace App\Console\Commands;

use App\Models\CashReconciliation;
use App\Models\POS\Payment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyCashReconciliations extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cash:import-legacy-reconciliations
                          {--dry-run : Run without making changes to show what would be imported}
                          {--from= : Start date for import (Y-m-d format)}
                          {--to= : End date for import (Y-m-d format)}
                          {--chunk=100 : Number of records to process at a time}';

    /**
     * The console command description.
     */
    protected $description = 'Import legacy cash reconciliation data from POS money table into cash_reconciliations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        $chunkSize = (int) $this->option('chunk');

        $this->info('Starting legacy cash reconciliation import...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No data will be created');
        }

        // Build the till_id map from CLOSEDCASH HOST values (same positional index used elsewhere)
        $tillMap = $this->buildTillMap();
        $this->info('Till mapping: '.collect($tillMap)->map(fn ($id, $host) => "{$host} => {$id}")->implode(', '));

        // Query POS: money + CLOSEDCASH + lodgeCnt (only records with lodgement data)
        $query = DB::connection('pos')
            ->table('money as m')
            ->join('CLOSEDCASH as cc', 'm.ID', '=', 'cc.MONEY')
            ->join('lodgeCnt as l', 'm.ID', '=', 'l.moneyID')
            ->select([
                'm.*',
                'cc.MONEY',
                'cc.HOST',
                'cc.DATEEND',
                'cc.DATESTART',
            ]);

        if ($fromDate) {
            $query->where('cc.DATEEND', '>=', $fromDate);
            $this->info("Filtering from: {$fromDate}");
        }
        if ($toDate) {
            $query->where('cc.DATEEND', '<=', $toDate.' 23:59:59');
            $this->info("Filtering to: {$toDate}");
        }

        $query->orderBy('cc.DATEEND', 'asc');

        $totalCount = (clone $query)->count();
        $this->info("Found {$totalCount} records to process");

        if ($dryRun) {
            // Check how many already exist
            $existingCount = CashReconciliation::whereIn(
                'closed_cash_id',
                (clone $query)->pluck('cc.MONEY')
            )->count();

            $this->line("  Would import: ".($totalCount - $existingCount)." new reconciliations");
            $this->line("  Already exist: {$existingCount} (would be skipped)");

            return 0;
        }

        $records = $query->get();
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        $this->withProgressBar($records, function ($record) use (
            $tillMap, &$imported, &$skipped, &$failed
        ) {
            try {
                // Skip if already exists
                $existing = CashReconciliation::where('closed_cash_id', $record->MONEY)->first();
                if ($existing) {
                    $skipped++;

                    return;
                }

                // Resolve till_id
                $tillId = $tillMap[$record->HOST] ?? null;
                if ($tillId === null) {
                    $this->newLine();
                    $this->warn("  Unknown till HOST: {$record->HOST} for money ID {$record->MONEY}");
                    $failed++;

                    return;
                }

                // Calculate POS payment totals
                $posTotals = $this->calculatePosTotals($record->MONEY);

                // Convert denomination totals to counts
                $reconciliation = new CashReconciliation([
                    'closed_cash_id' => $record->MONEY,
                    'date' => Carbon::parse($record->DATEEND)->toDateString(),
                    'till_name' => $record->HOST,
                    'till_id' => $tillId,
                    'cash_50' => $record->cash50 ? intval($record->cash50 / 50) : 0,
                    'cash_20' => $record->cash20 ? intval($record->cash20 / 20) : 0,
                    'cash_10' => $record->cash10 ? intval($record->cash10 / 10) : 0,
                    'cash_5' => $record->cash5 ? intval($record->cash5 / 5) : 0,
                    'cash_2' => $record->cash2 ? intval($record->cash2 / 2) : 0,
                    'cash_1' => $record->cash1 ? intval($record->cash1 / 1) : 0,
                    'cash_50c' => $record->cash50c ? intval($record->cash50c / 0.5) : 0,
                    'cash_20c' => $record->cash20c ? intval($record->cash20c / 0.2) : 0,
                    'cash_10c' => $record->cash10c ? intval($record->cash10c / 0.1) : 0,
                    'note_float' => $record->noteFloat ?? 0,
                    'coin_float' => $record->coinFloat ?? 0,
                    'card' => $record->card ?? 0,
                    'cash_back' => $record->cashBack ?? 0,
                    'cheque' => $record->cheque ?? 0,
                    'debt' => $record->debt ?? 0,
                    'debt_paid_cash' => $record->debtPaidCash ?? 0,
                    'debt_paid_cheque' => $record->debtPaidCheque ?? 0,
                    'debt_paid_card' => $record->debtPaidCard ?? 0,
                    'free' => $record->free ?? 0,
                    'voucher_used' => $record->voucherUsed ?? 0,
                    'money_added' => $record->moneyAdded ?? 0,
                    'pos_cash_total' => $posTotals['cash'],
                    'pos_card_total' => $posTotals['card'],
                    'created_by' => 1, // System user
                ]);

                // Calculate total cash counted
                $reconciliation->total_cash_counted = $reconciliation->calculateTotalCash();

                // Calculate variance (cash takings vs POS cash total)
                // Use note_float + coin_float from this record as the float
                $daysCashTakings = $reconciliation->total_cash_counted
                    + ($record->cashBack ?? 0)
                    - ($record->noteFloat ?? 0)
                    - ($record->coinFloat ?? 0)
                    - ($record->moneyAdded ?? 0);

                $reconciliation->variance = $daysCashTakings - $posTotals['cash'];

                $reconciliation->save();

                // Import legacy supplier payments
                $this->importLegacyPayments($reconciliation, $record->MONEY);

                // Import legacy notes
                $this->importLegacyNotes($reconciliation, $record->MONEY);

                $imported++;

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("  Failed for money ID {$record->MONEY}: ".$e->getMessage());
                $failed++;
            }
        });

        $this->newLine(2);

        $this->table(['Metric', 'Count'], [
            ['Total Processed', number_format($imported + $skipped + $failed)],
            ['Imported', number_format($imported)],
            ['Skipped (already exist)', number_format($skipped)],
            ['Failed', number_format($failed)],
        ]);

        $this->info("IMPORT COMPLETE: {$imported} reconciliations imported");

        return 0;
    }

    /**
     * Build HOST -> till_id mapping (same positional index as getAvailableTills)
     */
    private function buildTillMap(): array
    {
        $hosts = DB::connection('pos')
            ->table('CLOSEDCASH')
            ->select('HOST')
            ->distinct()
            ->orderBy('HOST')
            ->pluck('HOST');

        $map = [];
        foreach ($hosts as $index => $host) {
            $map[$host] = $index + 1;
        }

        return $map;
    }

    /**
     * Calculate POS totals from payments (same logic as CashReconciliationRepository)
     */
    private function calculatePosTotals(string $moneyId): array
    {
        $payments = Payment::select('PAYMENT', DB::raw('SUM(TOTAL) as total'))
            ->join('RECEIPTS', 'PAYMENTS.RECEIPT', '=', 'RECEIPTS.ID')
            ->where('RECEIPTS.MONEY', $moneyId)
            ->groupBy('PAYMENT')
            ->get();

        $totals = [
            'cash' => 0,
            'card' => 0,
            'debt' => 0,
            'free' => 0,
            'cheque' => 0,
        ];

        foreach ($payments as $payment) {
            switch ($payment->PAYMENT) {
                case 'cash':
                    $totals['cash'] = $payment->total;
                    break;
                case 'magcard':
                    $totals['card'] = $payment->total;
                    break;
                case 'debt':
                    $totals['debt'] = $payment->total;
                    break;
                case 'free':
                    $totals['free'] = $payment->total;
                    break;
                case 'cheque':
                    $totals['cheque'] = $payment->total;
                    break;
            }
        }

        return $totals;
    }

    /**
     * Import legacy supplier payments from payeePayments table
     */
    private function importLegacyPayments(CashReconciliation $reconciliation, string $closedCashId): void
    {
        try {
            $legacyPayments = DB::connection('pos')->table('payeePayments')
                ->where('closedCashID', $closedCashId)
                ->orderBy('sequence')
                ->get();

            foreach ($legacyPayments as $payment) {
                if ($reconciliation->payments()->where('sequence', $payment->sequence)->exists()) {
                    continue;
                }

                $reconciliation->payments()->create([
                    'supplier_id' => $payment->payeeID ?? null,
                    'payee_name' => null,
                    'amount' => $payment->amount ?? 0,
                    'sequence' => $payment->sequence ?? 0,
                    'description' => null,
                ]);
            }
        } catch (\Exception $e) {
            // payeePayments table may not exist or have no data — not critical
            if (! str_contains($e->getMessage(), 'payeePayments')) {
                throw $e;
            }
        }
    }

    /**
     * Import legacy notes from dayNotes table
     */
    private function importLegacyNotes(CashReconciliation $reconciliation, string $closedCashId): void
    {
        try {
            $legacyNote = DB::connection('pos')->table('dayNotes')
                ->where('closedCashID', $closedCashId)
                ->first();

            if ($legacyNote && ! $reconciliation->notes()->exists()) {
                $reconciliation->notes()->create([
                    'message' => $legacyNote->message,
                    'created_by' => 1, // System user
                ]);
            }
        } catch (\Exception $e) {
            // dayNotes table may not exist — not critical
            if (! str_contains($e->getMessage(), 'dayNotes')) {
                throw $e;
            }
        }
    }
}
