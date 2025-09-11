<?php

namespace App\Console\Commands;

use App\Models\CashLodgement;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyCashLodgements extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cash:import-legacy-lodgements 
                          {--dry-run : Run without making changes to show what would be imported}
                          {--from= : Start date for import (Y-m-d format)}
                          {--to= : End date for import (Y-m-d format)}';

    /**
     * The console command description.
     */
    protected $description = 'Import legacy cash lodgement data from POS lodgeCnt and lodgeCntCheques tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $fromDate = $this->option('from');
        $toDate = $this->option('to');

        $this->info('Starting legacy cash lodgement import...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No data will be created');
        }

        // Build date filters
        $whereClause = [];
        if ($fromDate) {
            $whereClause[] = ['lDate', '>=', $fromDate];
            $this->info("Filtering from: {$fromDate}");
        }
        if ($toDate) {
            $whereClause[] = ['lDate', '<=', $toDate];
            $this->info("Filtering to: {$toDate}");
        }

        // Get cash lodgements from lodgeCnt table
        $this->info('Importing cash lodgements...');
        $cashLodgements = $this->importCashLodgements($whereClause, $dryRun);

        // Get cheque lodgements from lodgeCntCheques table (if it exists)
        $chequeLodgements = 0;
        try {
            $this->info('Importing cheque lodgements...');
            $chequeLodgements = $this->importChequeLodgements($whereClause, $dryRun);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'lodgeCntCheques')) {
                $this->warn('  lodgeCntCheques table not found - skipping cheque lodgement import');
            } else {
                throw $e;
            }
        }

        $totalImported = $cashLodgements + $chequeLodgements;

        if ($dryRun) {
            $this->info("DRY RUN COMPLETE: Would have imported {$totalImported} lodgements ({$cashLodgements} cash, {$chequeLodgements} cheques)");
        } else {
            $this->info("IMPORT COMPLETE: Successfully imported {$totalImported} lodgements ({$cashLodgements} cash, {$chequeLodgements} cheques)");
        }

        return 0;
    }

    /**
     * Import cash lodgements from lodgeCnt table
     */
    private function importCashLodgements(array $whereClause, bool $dryRun): int
    {
        $query = DB::connection('pos')
            ->table('lodgeCnt as l')
            ->join('CLOSEDCASH as cc', 'l.moneyID', '=', 'cc.MONEY')
            ->select([
                'l.moneyID',
                'l.lDate',
                'l.cash',
                'cc.HOST as till_name',
                'cc.DATEEND as close_date',
            ])
            ->where($whereClause);

        if ($dryRun) {
            $count = $query->count();
            $this->line("  Would import {$count} cash lodgements from lodgeCnt table");

            return $count;
        }

        $lodgements = $query->get();
        $imported = 0;
        $skipped = 0;

        foreach ($lodgements as $legacy) {
            try {
                // Check if already imported
                $existing = CashLodgement::where('money_id', $legacy->moneyID)
                    ->where('imported_from_legacy', true)
                    ->where('cash_amount', '>', 0)
                    ->first();

                if ($existing) {
                    $skipped++;

                    continue;
                }

                // Create new lodgement record
                $lodgement = new CashLodgement([
                    'money_id' => $legacy->moneyID,
                    'lodgement_date' => Carbon::parse($legacy->lDate)->toDateString(),
                    'cash_amount' => $legacy->cash,
                    'cheque_amount' => 0,
                    'till_name' => $legacy->till_name,
                    'lodgement_type' => 'cash_only',
                    'imported_from_legacy' => true,
                    'original_lodge_date' => $legacy->lDate,
                    'is_matched' => false,
                    'created_by' => 1, // System user
                ]);

                $lodgement->save();
                $imported++;

                if ($imported % 50 == 0) {
                    $this->line("  Imported {$imported} cash lodgements...");
                }

            } catch (\Exception $e) {
                $this->error("  Failed to import cash lodgement for money ID {$legacy->moneyID}: ".$e->getMessage());
            }
        }

        $this->line("  Cash lodgements: {$imported} imported, {$skipped} skipped (already exist)");

        return $imported;
    }

    /**
     * Import cheque lodgements from lodgeCntCheques table
     */
    private function importChequeLodgements(array $whereClause, bool $dryRun): int
    {
        $query = DB::connection('pos')
            ->table('lodgeCntCheques as lc')
            ->join('CLOSEDCASH as cc', 'lc.moneyID', '=', 'cc.MONEY')
            ->select([
                'lc.moneyID',
                'lc.lDate',
                'lc.cheque',
                'cc.HOST as till_name',
                'cc.DATEEND as close_date',
            ])
            ->where($whereClause);

        if ($dryRun) {
            $count = $query->count();
            $this->line("  Would import {$count} cheque lodgements from lodgeCntCheques table");

            return $count;
        }

        $lodgements = $query->get();
        $imported = 0;
        $skipped = 0;
        $merged = 0;

        foreach ($lodgements as $legacy) {
            try {
                // Check if there's already a cash lodgement for this money_id
                $existing = CashLodgement::where('money_id', $legacy->moneyID)
                    ->where('imported_from_legacy', true)
                    ->first();

                if ($existing) {
                    // Merge cheque into existing lodgement
                    $existing->cheque_amount = $legacy->cheque;
                    $existing->save(); // This will auto-recalculate total_amount and lodgement_type
                    $merged++;
                } else {
                    // Create new cheque-only lodgement
                    $lodgement = new CashLodgement([
                        'money_id' => $legacy->moneyID,
                        'lodgement_date' => Carbon::parse($legacy->lDate)->toDateString(),
                        'cash_amount' => 0,
                        'cheque_amount' => $legacy->cheque,
                        'till_name' => $legacy->till_name,
                        'lodgement_type' => 'cheque_only',
                        'imported_from_legacy' => true,
                        'original_lodge_date' => $legacy->lDate,
                        'is_matched' => false,
                        'created_by' => 1, // System user
                    ]);

                    $lodgement->save();
                    $imported++;
                }

                if (($imported + $merged) % 50 == 0) {
                    $this->line('  Processed '.($imported + $merged).' cheque lodgements...');
                }

            } catch (\Exception $e) {
                $this->error("  Failed to import cheque lodgement for money ID {$legacy->moneyID}: ".$e->getMessage());
            }
        }

        $this->line("  Cheque lodgements: {$imported} new, {$merged} merged with existing, {$skipped} skipped");

        return $imported;
    }
}
