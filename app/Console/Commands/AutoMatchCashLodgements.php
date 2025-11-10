<?php

namespace App\Console\Commands;

use App\Models\CashLodgement;
use App\Models\CashLodgementMatch;
use App\Models\CashReconciliation;
use Illuminate\Console\Command;

class AutoMatchCashLodgements extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cash:auto-match-money-ids
                          {--dry-run : Show what would be matched without creating records}
                          {--from= : Start date for matching (Y-m-d format)}
                          {--to= : End date for matching (Y-m-d format)}
                          {--confidence= : Minimum confidence score (default: 75)}';

    /**
     * The console command description.
     */
    protected $description = 'Automatically match cash lodgements to reconciliations using Money ID';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        $minConfidence = $this->option('confidence', 75);

        $this->info('Starting automatic Money ID matching...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No matches will be created');
        }

        // Build date filters for lodgements
        $query = CashLodgement::query();

        if ($fromDate) {
            $query->where('lodgement_date', '>=', $fromDate);
            $this->info("Filtering from: {$fromDate}");
        }
        if ($toDate) {
            $query->where('lodgement_date', '<=', $toDate);
            $this->info("Filtering to: {$toDate}");
        }

        $this->info("Minimum confidence score: {$minConfidence}%");
        $this->newLine();

        // Get lodgements that might have matching reconciliations
        $lodgements = $query->with(['matches'])->get();

        $totalProcessed = 0;
        $exactMatches = 0;
        $goodMatches = 0;
        $poorMatches = 0;
        $alreadyMatched = 0;
        $noReconciliation = 0;

        $this->withProgressBar($lodgements, function ($lodgement) use (
            $dryRun, $minConfidence,
            &$totalProcessed, &$exactMatches, &$goodMatches, &$poorMatches,
            &$alreadyMatched, &$noReconciliation
        ) {
            $totalProcessed++;

            // Skip if already matched
            if ($lodgement->matches->count() > 0) {
                $alreadyMatched++;

                return;
            }

            // Look for reconciliation with same Money ID
            $reconciliation = CashReconciliation::where('closed_cash_id', $lodgement->money_id)->first();

            if (! $reconciliation) {
                $noReconciliation++;

                return;
            }

            // Calculate available to lodge from reconciliation
            $availableToLodge = $reconciliation->calculateAvailableToLodge();
            $lodgedAmount = $lodgement->cash_amount;

            // Calculate confidence based on amount matching
            $confidence = $this->calculateConfidence($availableToLodge, $lodgedAmount);

            // Determine match type
            $matchType = $this->getMatchType($availableToLodge, $lodgedAmount);

            if ($confidence >= $minConfidence) {
                if (! $dryRun) {
                    // Create the match record
                    CashLodgementMatch::create([
                        'cash_lodgement_id' => $lodgement->id,
                        'cash_reconciliation_id' => $reconciliation->id,
                        'pos_date' => $reconciliation->date,
                        'matched_amount' => $lodgedAmount,
                        'match_type' => $matchType,
                        'confidence_score' => $confidence,
                        'match_notes' => "Auto-matched via Money ID: {$lodgement->money_id}",
                        'cash_taken' => $reconciliation->total_cash_counted,
                        'float_retained' => $reconciliation->total_float,
                        'supplier_payments' => $reconciliation->total_supplier_payments,
                        'available_to_lodge' => $availableToLodge,
                        'matched_by' => 1, // System user
                        'matched_at' => now(),
                        'created_by' => 1,
                    ]);

                    // Update lodgement match status
                    $lodgement->update(['is_matched' => true]);
                }

                // Count by confidence level
                if ($confidence >= 95) {
                    $exactMatches++;
                } elseif ($confidence >= 80) {
                    $goodMatches++;
                } else {
                    $poorMatches++;
                }
            }
        });

        $this->newLine(2);

        // Summary
        $this->table(['Metric', 'Count'], [
            ['Total Processed', number_format($totalProcessed)],
            ['Already Matched', number_format($alreadyMatched)],
            ['No Reconciliation', number_format($noReconciliation)],
            ['Exact Matches (95%+)', number_format($exactMatches)],
            ['Good Matches (80-94%)', number_format($goodMatches)],
            ['Fair Matches (75-79%)', number_format($poorMatches)],
            ['Total New Matches', number_format($exactMatches + $goodMatches + $poorMatches)],
        ]);

        if ($dryRun) {
            $this->info('DRY RUN COMPLETE: No matches were created');
        } else {
            $newMatches = $exactMatches + $goodMatches + $poorMatches;
            $this->info("MATCHING COMPLETE: Created {$newMatches} new matches");

            if ($newMatches > 0) {
                $this->info('Run the diagnostic page to see the matched lodgements!');
            }
        }

        // Show sample of unmatched lodgements if any
        $unmatched = $noReconciliation;
        if ($unmatched > 0) {
            $this->newLine();
            $this->warn("⚠️  {$unmatched} lodgements couldn't be matched (no reconciliation data)");
            $this->info('Consider creating reconciliation records for these POS closes to enable matching.');
        }

        return 0;
    }

    /**
     * Calculate confidence score based on amount matching
     */
    private function calculateConfidence(float $availableToLodge, float $lodgedAmount): int
    {
        if ($availableToLodge == 0) {
            return 0;
        }

        $difference = abs($availableToLodge - $lodgedAmount);
        $percentageOff = ($difference / $availableToLodge) * 100;

        // Perfect match
        if ($difference <= 0.01) {
            return 100;
        }

        // Very close match (within €1 or 1%)
        if ($difference <= 1.00 || $percentageOff <= 1) {
            return 98;
        }

        // Good match (within €5 or 2%)
        if ($difference <= 5.00 || $percentageOff <= 2) {
            return 95;
        }

        // Decent match (within €10 or 5%)
        if ($difference <= 10.00 || $percentageOff <= 5) {
            return 90;
        }

        // Fair match (within €20 or 10%)
        if ($difference <= 20.00 || $percentageOff <= 10) {
            return 80;
        }

        // Poor but possible match (within €50 or 20%)
        if ($difference <= 50.00 || $percentageOff <= 20) {
            return 70;
        }

        // Very poor match
        return 50;
    }

    /**
     * Determine match type based on amounts
     */
    private function getMatchType(float $availableToLodge, float $lodgedAmount): string
    {
        $difference = abs($availableToLodge - $lodgedAmount);

        // Exact match (within 1 cent)
        if ($difference <= 0.01) {
            return 'exact';
        }

        // Close enough to be considered exact (rounding, etc.)
        if ($difference <= 1.00) {
            return 'exact';
        }

        // Partial match (some of the available amount was lodged)
        if ($lodgedAmount < $availableToLodge) {
            return 'partial';
        }

        // Manual verification needed
        return 'manual';
    }
}
