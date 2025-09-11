<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\CashLodgement;
use App\Models\CashReconciliation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankStatementAnalysisService
{
    /**
     * Analyze a period comparing POS data to bank lodgements
     */
    public function analyzePeriod(Carbon $startDate, Carbon $endDate): array
    {
        $analysis = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $posSummary = $this->getPOSSummary($currentDate);
            $bankMatches = $this->findBankMatches($currentDate, $posSummary);

            $analysis[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day_of_week' => $currentDate->format('l'),
                'is_weekend' => $currentDate->isWeekend(),
                'pos' => $posSummary,
                'bank' => $bankMatches,
                'variance' => $this->calculateVariance($posSummary, $bankMatches),
                'status' => $this->determineStatus($posSummary, $bankMatches, $currentDate),
                'suggestions' => $this->generateSuggestions($posSummary, $bankMatches, $currentDate),
            ];

            $currentDate->addDay();
        }

        return $analysis;
    }

    /**
     * Get or calculate POS summary for a specific date
     */
    public function getPOSSummary(Carbon $date): array
    {
        // First check if we have a cached summary
        $cached = DB::table('pos_daily_summaries')
            ->where('sale_date', $date->format('Y-m-d'))
            ->first();

        if ($cached) {
            return [
                'cash_sales' => (float) $cached->cash_sales,
                'cash_refunds' => (float) $cached->cash_refunds,
                'card_sales' => (float) $cached->card_sales,
                'card_refunds' => (float) $cached->card_refunds,
                'debt_sales' => (float) $cached->debt_sales,
                'free_sales' => (float) $cached->free_sales,
                'total_transactions' => $cached->total_transactions,
                'net_cash' => (float) $cached->cash_sales - (float) $cached->cash_refunds,
                'net_card' => (float) $cached->card_sales - (float) $cached->card_refunds,
                'total' => (float) $cached->cash_sales - (float) $cached->cash_refunds +
                          (float) $cached->card_sales - (float) $cached->card_refunds,
            ];
        }

        // Calculate from POS database
        return $this->calculatePOSSummary($date);
    }

    /**
     * Calculate POS summary directly from POS database
     */
    private function calculatePOSSummary(Carbon $date): array
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

            $summary = [
                'cash_sales' => (float) ($result->cash_sales ?? 0),
                'cash_refunds' => (float) ($result->cash_refunds ?? 0),
                'card_sales' => (float) ($result->card_sales ?? 0),
                'card_refunds' => (float) ($result->card_refunds ?? 0),
                'debt_sales' => (float) ($result->debt_sales ?? 0),
                'free_sales' => (float) ($result->free_sales ?? 0),
                'total_transactions' => (int) ($result->total_transactions ?? 0),
                'net_cash' => (float) ($result->cash_sales ?? 0) - (float) ($result->cash_refunds ?? 0),
                'net_card' => (float) ($result->card_sales ?? 0) - (float) ($result->card_refunds ?? 0),
                'total' => 0,
            ];

            $summary['total'] = $summary['net_cash'] + $summary['net_card'];

            // Cache the summary
            $this->cachePOSSummary($date, $summary);

            return $summary;
        } catch (\Exception $e) {
            Log::error('Failed to calculate POS summary', [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            return [
                'cash_sales' => 0,
                'cash_refunds' => 0,
                'card_sales' => 0,
                'card_refunds' => 0,
                'debt_sales' => 0,
                'free_sales' => 0,
                'total_transactions' => 0,
                'net_cash' => 0,
                'net_card' => 0,
                'total' => 0,
            ];
        }
    }

    /**
     * Cache POS summary for faster access
     */
    private function cachePOSSummary(Carbon $date, array $summary): void
    {
        DB::table('pos_daily_summaries')->updateOrInsert(
            ['sale_date' => $date->format('Y-m-d')],
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
    }

    /**
     * Find bank matches for a POS date
     */
    private function findBankMatches(Carbon $posDate, array $posSummary): array
    {
        // Check for existing manual matches
        $manualMatches = DB::table('pos_bank_matches')
            ->where('pos_date', $posDate->format('Y-m-d'))
            ->join('bank_transactions', 'pos_bank_matches.bank_transaction_id', '=', 'bank_transactions.id')
            ->select('bank_transactions.*', 'pos_bank_matches.match_type', 'pos_bank_matches.matched_amount')
            ->get();

        if ($manualMatches->isNotEmpty()) {
            return [
                'transactions' => $manualMatches->toArray(),
                'total_lodged' => $manualMatches->sum('matched_amount'),
                'match_type' => $manualMatches->first()->match_type,
                'is_manual' => true,
            ];
        }

        // Try automatic matching
        return $this->findAutomaticMatches($posDate, $posSummary);
    }

    /**
     * Find automatic matches based on patterns and credit categories
     */
    private function findAutomaticMatches(Carbon $posDate, array $posSummary): array
    {
        $matches = [];
        $totalToMatch = $posSummary['total'];
        $cardTotal = $posSummary['net_card'];
        $cashTotal = $posSummary['net_cash'];

        // Try category-specific matching first
        $categoryMatches = $this->findCategorySpecificMatches($posDate, $posSummary);
        if (! empty($categoryMatches['transactions'])) {
            return $categoryMatches;
        }

        // Fall back to original exact amount matching
        $searchStart = $posDate->copy();
        $searchEnd = $posDate->copy()->addDays(5); // Account for weekends

        // Look for exact amount match (any category)
        $exactMatch = BankTransaction::whereBetween('transaction_date', [$searchStart, $searchEnd])
            ->where('credit_amount', '>', 0)
            ->whereRaw('ABS(credit_amount - ?) < 0.01', [$totalToMatch])
            ->first();

        if ($exactMatch) {
            return [
                'transactions' => [$exactMatch],
                'total_lodged' => $exactMatch->credit_amount,
                'match_type' => 'exact',
                'is_manual' => false,
                'confidence' => 95,
                'category_info' => $exactMatch->credit_category ? [
                    'category' => $exactMatch->credit_category,
                    'display' => $exactMatch->credit_category_display,
                ] : null,
            ];
        }

        // Look for combined weekend deposits
        if ($posDate->isFriday() || $posDate->isSaturday() || $posDate->isSunday()) {
            $weekendTotal = $this->calculateWeekendTotal($posDate);

            $weekendMatch = BankTransaction::whereBetween('transaction_date', [
                $posDate->copy()->next('Monday'),
                $posDate->copy()->next('Tuesday'),
            ])
                ->where('credit_amount', '>', 0)
                ->whereRaw('ABS(credit_amount - ?) < 0.01', [$weekendTotal])
                ->first();

            if ($weekendMatch) {
                return [
                    'transactions' => [$weekendMatch],
                    'total_lodged' => $weekendMatch->credit_amount,
                    'match_type' => 'combined',
                    'is_manual' => false,
                    'confidence' => 85,
                    'note' => 'Weekend combined deposit',
                ];
            }
        }

        // No automatic match found
        return [
            'transactions' => [],
            'total_lodged' => 0,
            'match_type' => 'none',
            'is_manual' => false,
            'confidence' => 0,
        ];
    }

    /**
     * Calculate weekend total for combined deposits
     */
    private function calculateWeekendTotal(Carbon $date): float
    {
        $friday = $date->copy()->previous('Friday');
        $saturday = $friday->copy()->addDay();
        $sunday = $saturday->copy()->addDay();

        $total = 0;
        foreach ([$friday, $saturday, $sunday] as $day) {
            if ($day <= Carbon::now()) {
                $summary = $this->getPOSSummary($day);
                $total += $summary['total'];
            }
        }

        return $total;
    }

    /**
     * Find matches using credit category-specific logic
     */
    private function findCategorySpecificMatches(Carbon $posDate, array $posSummary): array
    {
        $cardTotal = $posSummary['net_card'];
        $cashTotal = $posSummary['net_cash'];

        // Match card lodgements (typically 1-3 days after POS date)
        if ($cardTotal > 0) {
            $cardMatch = $this->findCardLodgementMatch($posDate, $cardTotal);
            if ($cardMatch) {
                $variance = abs($cardMatch->credit_amount - $cardTotal);
                $isExactMatch = $variance < 0.01;

                return [
                    'transactions' => [$cardMatch],
                    'total_lodged' => $cardMatch->credit_amount,
                    'match_type' => $isExactMatch ? 'card_specific' : 'card_specific_flexible',
                    'is_manual' => false,
                    'confidence' => $isExactMatch ? 90 : 75,
                    'category_info' => [
                        'category' => 'card_lodgement',
                        'display' => 'Card Lodgements',
                        'matched_amount' => $cardTotal,
                        'pos_type' => 'card',
                        'variance' => $variance,
                        'match_quality' => $isExactMatch ? 'exact' : 'flexible',
                    ],
                ];
            }
        }

        // Match cash lodgements (typically 3-5 days after POS date, often combined)
        if ($cashTotal > 0) {
            $cashMatch = $this->findCashLodgementMatch($posDate, $cashTotal);
            if ($cashMatch) {
                $variance = abs($cashMatch->credit_amount - $cashTotal);
                $isExactMatch = $variance < 0.01;

                return [
                    'transactions' => [$cashMatch],
                    'total_lodged' => $cashMatch->credit_amount,
                    'match_type' => $isExactMatch ? 'cash_specific' : 'cash_specific_flexible',
                    'is_manual' => false,
                    'confidence' => $isExactMatch ? 85 : 70,
                    'category_info' => [
                        'category' => 'cash_lodgement',
                        'display' => 'Cash Lodgements',
                        'matched_amount' => $cashTotal,
                        'pos_type' => 'cash',
                        'variance' => $variance,
                        'match_quality' => $isExactMatch ? 'exact' : 'flexible',
                    ],
                ];
            }
        }

        return ['transactions' => []];
    }

    /**
     * Find card lodgement matches (1-3 day delay) with flexible matching
     */
    private function findCardLodgementMatch(Carbon $posDate, float $cardAmount): ?BankTransaction
    {
        // First try exact match
        $exactMatch = BankTransaction::whereBetween('transaction_date', [
            $posDate->copy()->addDay(),
            $posDate->copy()->addDays(3),
        ])
            ->where('credit_amount', '>', 0)
            ->where('credit_category', 'card_lodgement')
            ->whereRaw('ABS(credit_amount - ?) < 0.01', [$cardAmount])
            ->first();

        if ($exactMatch) {
            return $exactMatch;
        }

        // Try flexible matching (within 15% variance for amounts over €50)
        if ($cardAmount > 50) {
            $variance = $cardAmount * 0.15; // 15% variance

            return BankTransaction::whereBetween('transaction_date', [
                $posDate->copy()->addDay(),
                $posDate->copy()->addDays(3),
            ])
                ->where('credit_amount', '>', 0)
                ->where('credit_category', 'card_lodgement')
                ->whereRaw('ABS(credit_amount - ?) < ?', [$cardAmount, $variance])
                ->orderByRaw('ABS(credit_amount - ?) ASC', [$cardAmount])
                ->first();
        }

        return null;
    }

    /**
     * Find cash lodgement matches (0-5 day delay, weekend combining) with flexible matching
     */
    private function findCashLodgementMatch(Carbon $posDate, float $cashAmount): ?BankTransaction
    {
        // Check for same-day or next-day cash deposits first (exact then flexible)
        $immediateMatch = $this->findFlexibleCashMatch($posDate->copy(), $posDate->copy()->addDays(2), $cashAmount);
        if ($immediateMatch) {
            return $immediateMatch;
        }

        // Check for weekend combined deposits if it's a weekend
        if ($posDate->isWeekend() || $posDate->isFriday()) {
            $weekendCashTotal = $this->calculateWeekendCashTotal($posDate);
            $weekendMatch = $this->findFlexibleCashMatch(
                $posDate->copy()->next('Monday'),
                $posDate->copy()->next('Tuesday'),
                $weekendCashTotal
            );
            if ($weekendMatch) {
                return $weekendMatch;
            }
        }

        // Check for delayed cash deposits (up to 5 days)
        return $this->findFlexibleCashMatch(
            $posDate->copy()->addDays(3),
            $posDate->copy()->addDays(5),
            $cashAmount
        );
    }

    /**
     * Helper method for flexible cash matching
     */
    private function findFlexibleCashMatch(Carbon $startDate, Carbon $endDate, float $amount): ?BankTransaction
    {
        // First try exact match
        $exactMatch = BankTransaction::whereBetween('transaction_date', [$startDate, $endDate])
            ->where('credit_amount', '>', 0)
            ->where('credit_category', 'cash_lodgement')
            ->whereRaw('ABS(credit_amount - ?) < 0.01', [$amount])
            ->first();

        if ($exactMatch) {
            return $exactMatch;
        }

        // Try flexible matching (within 10% variance for amounts over €100)
        if ($amount > 100) {
            $variance = $amount * 0.10; // 10% variance (stricter than cards)

            return BankTransaction::whereBetween('transaction_date', [$startDate, $endDate])
                ->where('credit_amount', '>', 0)
                ->where('credit_category', 'cash_lodgement')
                ->whereRaw('ABS(credit_amount - ?) < ?', [$amount, $variance])
                ->orderByRaw('ABS(credit_amount - ?) ASC', [$amount])
                ->first();
        }

        return null;
    }

    /**
     * Calculate weekend cash total for combined deposits
     */
    private function calculateWeekendCashTotal(Carbon $date): float
    {
        $friday = $date->copy()->previous('Friday');
        $saturday = $friday->copy()->addDay();
        $sunday = $saturday->copy()->addDay();

        $total = 0;
        foreach ([$friday, $saturday, $sunday] as $day) {
            if ($day <= Carbon::now()) {
                $summary = $this->getPOSSummary($day);
                $total += $summary['net_cash']; // Only cash, not card
            }
        }

        return $total;
    }

    /**
     * Calculate variance between POS and bank
     */
    private function calculateVariance(array $posSummary, array $bankMatches): array
    {
        $posTotal = $posSummary['total'];
        $bankTotal = $bankMatches['total_lodged'] ?? 0;
        $variance = $bankTotal - $posTotal;
        $variancePercent = $posTotal > 0 ? ($variance / $posTotal) * 100 : 0;

        return [
            'amount' => $variance,
            'percentage' => $variancePercent,
            'is_significant' => abs($variance) > 50 || abs($variancePercent) > 5,
        ];
    }

    /**
     * Determine the status of a day's reconciliation
     */
    private function determineStatus(array $posSummary, array $bankMatches, Carbon $date): string
    {
        // No POS sales
        if ($posSummary['total'] == 0) {
            return 'no_sales';
        }

        // Too recent to expect lodgement
        $daysSince = $date->diffInDays(Carbon::now());
        if ($daysSince < 3 && $bankMatches['total_lodged'] == 0) {
            return 'pending';
        }

        // Matched
        if ($bankMatches['total_lodged'] > 0) {
            $variance = abs($bankMatches['total_lodged'] - $posSummary['total']);
            if ($variance < 0.01) {
                return 'matched';
            } elseif ($variance < 50) {
                return 'matched_with_variance';
            } else {
                return 'large_variance';
            }
        }

        // Missing lodgement
        return 'missing';
    }

    /**
     * Generate suggestions for reconciliation
     */
    private function generateSuggestions(array $posSummary, array $bankMatches, Carbon $date): array
    {
        $suggestions = [];

        if ($bankMatches['total_lodged'] == 0 && $posSummary['total'] > 0) {
            $daysSince = $date->diffInDays(Carbon::now());

            if ($daysSince < 3) {
                $suggestions[] = 'Lodgement expected within '.(3 - $daysSince).' business days';
            } else {
                $suggestions[] = 'Check if lodgement was made - should have appeared by now';
            }

            if ($date->isWeekend()) {
                $suggestions[] = 'Weekend sales typically lodged on Monday';
            }
        }

        $variance = $this->calculateVariance($posSummary, $bankMatches);
        if ($variance['is_significant']) {
            if ($variance['amount'] > 0) {
                $suggestions[] = 'Bank lodgement exceeds POS total - check for additional deposits';
            } else {
                $suggestions[] = 'Bank lodgement less than POS total - check for held cash or split deposits';
            }
        }

        return $suggestions;
    }

    /**
     * Get summary statistics for the period
     */
    public function getSummaryStatistics(Carbon $startDate, Carbon $endDate): array
    {
        $posTotals = DB::table('pos_daily_summaries')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->selectRaw('
                SUM(cash_sales - cash_refunds) as total_cash,
                SUM(card_sales - card_refunds) as total_card,
                SUM(cash_sales - cash_refunds + card_sales - card_refunds) as total_sales
            ')
            ->first();

        $bankTotals = BankTransaction::whereBetween('transaction_date', [$startDate, $endDate->copy()->addDays(5)])
            ->where('credit_amount', '>', 0)
            ->sum('credit_amount');

        $unmatchedDays = DB::table('pos_daily_summaries')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('pos_bank_matches')
                    ->whereColumn('pos_bank_matches.pos_date', 'pos_daily_summaries.sale_date');
            })
            ->count();

        return [
            'pos_total' => $posTotals->total_sales ?? 0,
            'bank_total' => $bankTotals,
            'variance' => $bankTotals - ($posTotals->total_sales ?? 0),
            'unmatched_days' => $unmatchedDays,
            'pos_cash' => $posTotals->total_cash ?? 0,
            'pos_card' => $posTotals->total_card ?? 0,
        ];
    }

    /**
     * Get unmatched POS days
     */
    public function getUnmatchedPOSDays(Carbon $startDate, Carbon $endDate): array
    {
        // First ensure all days have summaries
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $this->getPOSSummary($currentDate);
            $currentDate->addDay();
        }

        return DB::table('pos_daily_summaries')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('pos_bank_matches')
                    ->whereColumn('pos_bank_matches.pos_date', 'pos_daily_summaries.sale_date');
            })
            ->where(DB::raw('cash_sales - cash_refunds + card_sales - card_refunds'), '>', 0)
            ->orderBy('sale_date', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get unmatched bank transactions
     */
    public function getUnmatchedBankTransactions(Carbon $startDate, Carbon $endDate): array
    {
        return BankTransaction::whereBetween('transaction_date', [$startDate, $endDate->copy()->addDays(5)])
            ->where('credit_amount', '>', 0)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('pos_bank_matches')
                    ->whereColumn('pos_bank_matches.bank_transaction_id', 'bank_transactions.id');
            })
            ->orderBy('transaction_date', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get pattern insights
     */
    public function getPatternInsights(): array
    {
        $insights = [];

        // Card settlement timing
        $cardTiming = DB::table('pos_bank_matches as m')
            ->join('pos_daily_summaries as p', 'm.pos_date', '=', 'p.sale_date')
            ->join('bank_transactions as b', 'm.bank_transaction_id', '=', 'b.id')
            ->where('p.card_sales', '>', 0)
            ->selectRaw('DATEDIFF(b.transaction_date, m.pos_date) as days_delay, COUNT(*) as count')
            ->groupBy('days_delay')
            ->orderBy('count', 'desc')
            ->first();

        if ($cardTiming) {
            $insights[] = "Card payments typically settle in {$cardTiming->days_delay} business days";
        }

        // Weekend pattern
        $weekendPattern = DB::table('pos_bank_matches')
            ->where('match_type', 'combined')
            ->count();

        if ($weekendPattern > 0) {
            $insights[] = "Weekend sales are often deposited together on Monday ({$weekendPattern} occurrences)";
        }

        return $insights;
    }

    /**
     * Create a manual match
     */
    public function createMatch(string $posDate, string $bankTransactionId, string $matchType, float $amount, ?string $notes = null): array
    {
        $match = DB::table('pos_bank_matches')->insertGetId([
            'pos_date' => $posDate,
            'bank_transaction_id' => $bankTransactionId,
            'matched_amount' => $amount,
            'match_type' => $matchType,
            'confidence_score' => 100, // Manual matches have full confidence
            'notes' => $notes,
            'matched_by' => auth()->id(),
            'matched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('pos_bank_matches')->find($match);
    }

    /**
     * Remove a match
     */
    public function removeMatch(int $matchId): bool
    {
        return DB::table('pos_bank_matches')->delete($matchId) > 0;
    }

    /**
     * Refresh POS summary for a specific date
     */
    public function refreshPOSSummary(string $date): array
    {
        $carbonDate = Carbon::parse($date);

        // Delete cached summary
        DB::table('pos_daily_summaries')
            ->where('sale_date', $date)
            ->delete();

        // Recalculate
        return $this->getPOSSummary($carbonDate);
    }

    /**
     * Export analysis data
     */
    public function exportAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $analysis = $this->analyzePeriod($startDate, $endDate);
        $exportData = [];

        foreach ($analysis as $day) {
            $exportData[] = [
                'date' => $day['date'],
                'pos_cash' => $day['pos']['net_cash'],
                'pos_card' => $day['pos']['net_card'],
                'pos_total' => $day['pos']['total'],
                'bank_lodged' => $day['bank']['total_lodged'] ?? 0,
                'variance' => $day['variance']['amount'],
                'variance_percent' => round($day['variance']['percentage'], 2).'%',
                'status' => $day['status'],
                'notes' => implode('; ', $day['suggestions']),
            ];
        }

        return $exportData;
    }

    /**
     * Suggest automatic matches for a period
     */
    public function suggestMatches(Carbon $startDate, Carbon $endDate): array
    {
        $suggestions = [];
        $unmatchedPOS = $this->getUnmatchedPOSDays($startDate, $endDate);
        $unmatchedBank = $this->getUnmatchedBankTransactions($startDate, $endDate);

        foreach ($unmatchedPOS as $posDay) {
            $posTotal = $posDay->cash_sales - $posDay->cash_refunds + $posDay->card_sales - $posDay->card_refunds;

            foreach ($unmatchedBank as $bankTrans) {
                $variance = abs($bankTrans->credit_amount - $posTotal);
                $variancePercent = $posTotal > 0 ? ($variance / $posTotal) * 100 : 0;

                // Suggest if within 1% or €10
                if ($variance < 10 || $variancePercent < 1) {
                    $suggestions[] = [
                        'pos_date' => $posDay->sale_date,
                        'bank_transaction_id' => $bankTrans->id,
                        'pos_amount' => $posTotal,
                        'bank_amount' => $bankTrans->credit_amount,
                        'variance' => $variance,
                        'confidence' => $variance < 0.01 ? 100 : (100 - round($variancePercent)),
                    ];
                }
            }
        }

        // Sort by confidence
        usort($suggestions, function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return array_slice($suggestions, 0, 10); // Return top 10 suggestions
    }

    /**
     * Get unmatched bank transactions for a period, grouped by credit category
     */
    public function getUnmatchedBankTransactionsByCategory(Carbon $startDate, Carbon $endDate): array
    {
        // Get all matched bank transaction IDs
        $matchedIds = DB::table('pos_bank_matches')
            ->whereBetween('pos_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->pluck('bank_transaction_id');

        // Get unmatched credit transactions, grouped by category
        $unmatched = BankTransaction::whereBetween('transaction_date', [$startDate, $endDate])
            ->where('credit_amount', '>', 0)
            ->whereNotIn('id', $matchedIds)
            ->get()
            ->groupBy('credit_category');

        $result = [];
        foreach ($unmatched as $category => $transactions) {
            $categoryKey = $category ?: 'uncategorized';
            $result[$categoryKey] = [
                'category' => $category,
                'display_name' => $this->getCreditCategoryDisplayName($category),
                'transactions' => $transactions,
                'total_amount' => $transactions->sum('credit_amount'),
                'count' => $transactions->count(),
                'icon' => $this->getCreditCategoryIcon($category),
            ];
        }

        return $result;
    }

    /**
     * Get display name for credit category
     */
    private function getCreditCategoryDisplayName(?string $category): string
    {
        if (! $category) {
            return 'Uncategorized';
        }

        return match ($category) {
            'card_lodgement' => 'Card Lodgements',
            'cash_lodgement' => 'Cash Lodgements',
            'rent' => 'Rent',
            'other_credit' => 'Other Credits',
            default => ucfirst(str_replace('_', ' ', $category))
        };
    }

    /**
     * Get icon for credit category
     */
    private function getCreditCategoryIcon(?string $category): string
    {
        if (! $category) {
            return '❓';
        }

        return match ($category) {
            'card_lodgement' => '💳',
            'cash_lodgement' => '💰',
            'rent' => '🏠',
            'other_credit' => '📈',
            default => '💡'
        };
    }

    /**
     * Get comprehensive cash reconciliation summary for a period using actual lodgement data
     */
    public function getCashReconciliationSummary(Carbon $startDate, Carbon $endDate): array
    {
        // Get total POS cash for period
        $posCashTotal = DB::table('pos_daily_summaries')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->selectRaw('SUM(cash_sales - cash_refunds) as total_cash')
            ->first();

        // Get actual cash lodgements from our new table
        $extendedEndDate = $endDate->copy()->addDays(7);
        $actualLodgements = CashLodgement::whereBetween('lodgement_date', [$startDate, $extendedEndDate])
            ->get();

        // Get cash reconciliations for the period
        $reconciliations = CashReconciliation::whereBetween('date', [$startDate, $endDate])
            ->withLodgements()
            ->get();

        // Calculate available cash vs lodged amounts
        $totalAvailableToLodge = $reconciliations->sum(function ($reconciliation) {
            return $reconciliation->calculateAvailableToLodge();
        });

        $totalActualLodgements = $actualLodgements->sum('cash_amount');
        $unmatchedLodgements = $actualLodgements->where('is_matched', false);
        $unmatchedLodgementsAmount = $unmatchedLodgements->sum('cash_amount');

        // Calculate float impact
        $totalFloat = $reconciliations->sum('total_float');
        $totalSupplierPayments = $reconciliations->sum('total_supplier_payments');

        // Calculate accumulated cash balance considering float and payments
        $accumulatedCash = $this->getEnhancedCashBalance($startDate, $endDate, $reconciliations);

        $variance = $totalActualLodgements - $totalAvailableToLodge;
        $matchRate = $totalAvailableToLodge > 0
            ? (($totalActualLodgements - $unmatchedLodgementsAmount) / $totalAvailableToLodge) * 100
            : 0;

        return [
            'pos_cash_total' => $posCashTotal->total_cash ?? 0,
            'available_to_lodge' => $totalAvailableToLodge,
            'total_float_retained' => $totalFloat,
            'total_supplier_payments' => $totalSupplierPayments,
            'cash_lodgements_total' => $totalActualLodgements,
            'unmatched_cash_lodgements' => $unmatchedLodgementsAmount,
            'accumulated_unmatched_cash' => $accumulatedCash['final_balance'],
            'variance' => $variance,
            'match_rate' => round($matchRate, 1),
            'lodgement_count' => $actualLodgements->count(),
            'unmatched_lodgement_count' => $unmatchedLodgements->count(),
            'unmatched_lodgements' => $unmatchedLodgements->values(),
            'cash_flow_timeline' => $accumulatedCash['timeline'],
            'reconciliation_details' => $reconciliations->map(function ($reconciliation) {
                return [
                    'date' => $reconciliation->date->format('Y-m-d'),
                    'pos_cash' => $reconciliation->pos_cash_total,
                    'total_cash_counted' => $reconciliation->calculateTotalCash(),
                    'float_retained' => $reconciliation->total_float,
                    'supplier_payments' => $reconciliation->total_supplier_payments,
                    'available_to_lodge' => $reconciliation->calculateAvailableToLodge(),
                    'legacy_lodged' => $reconciliation->legacy_lodged_amount,
                    'variance' => $reconciliation->lodgement_variance,
                    'has_lodgement' => $reconciliation->has_lodgement,
                ];
            }),
        ];
    }

    /**
     * Calculate enhanced cash balance using actual cash reconciliation data
     */
    public function getEnhancedCashBalance(Carbon $startDate, Carbon $endDate, $reconciliations = null): array
    {
        if ($reconciliations === null) {
            $reconciliations = CashReconciliation::whereBetween('date', [$startDate, $endDate])
                ->withLodgements()
                ->get()
                ->keyBy(function ($reconciliation) {
                    return $reconciliation->date->format('Y-m-d');
                });
        }

        $timeline = [];
        $runningAvailableBalance = 0; // Available to lodge balance
        $runningCashPosition = 0; // Total cash position
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $reconciliation = $reconciliations->get($dateKey);

            if ($reconciliation) {
                $availableToLodge = $reconciliation->calculateAvailableToLodge();
                $totalCash = $reconciliation->calculateTotalCash();
                $floatRetained = $reconciliation->total_float;
                $supplierPayments = $reconciliation->total_supplier_payments;
                $legacyLodged = $reconciliation->legacy_lodged_amount;

                // Add to available balance, subtract if lodged
                $runningAvailableBalance += $availableToLodge - $legacyLodged;
                $runningCashPosition += $totalCash;

                $timeline[] = [
                    'date' => $dateKey,
                    'pos_cash' => $reconciliation->pos_cash_total,
                    'total_cash_counted' => $totalCash,
                    'float_retained' => $floatRetained,
                    'supplier_payments' => $supplierPayments,
                    'available_to_lodge' => $availableToLodge,
                    'legacy_lodged' => $legacyLodged,
                    'daily_change' => $availableToLodge - $legacyLodged,
                    'running_available_balance' => $runningAvailableBalance,
                    'running_cash_position' => $runningCashPosition,
                    'day_name' => $currentDate->format('l'),
                    'is_weekend' => $currentDate->isWeekend(),
                    'has_lodgement' => $reconciliation->has_lodgement,
                    'lodgement_variance' => $reconciliation->lodgement_variance,
                ];
            } else {
                // No reconciliation data for this date
                $posCash = DB::table('pos_daily_summaries')
                    ->where('sale_date', $dateKey)
                    ->value('cash_sales') ?? 0;

                $timeline[] = [
                    'date' => $dateKey,
                    'pos_cash' => $posCash,
                    'total_cash_counted' => 0,
                    'float_retained' => 0,
                    'supplier_payments' => 0,
                    'available_to_lodge' => 0,
                    'legacy_lodged' => 0,
                    'daily_change' => 0,
                    'running_available_balance' => $runningAvailableBalance,
                    'running_cash_position' => $runningCashPosition,
                    'day_name' => $currentDate->format('l'),
                    'is_weekend' => $currentDate->isWeekend(),
                    'has_lodgement' => false,
                    'lodgement_variance' => 0,
                ];
            }

            $currentDate->addDay();
        }

        return [
            'timeline' => $timeline,
            'final_balance' => $runningAvailableBalance,
            'final_cash_position' => $runningCashPosition,
        ];
    }

    /**
     * Calculate accumulated cash balance day by day, accounting for matched lodgements
     */
    public function getAccumulatedCashBalance(Carbon $startDate, Carbon $endDate): array
    {
        $timeline = [];
        $runningBalance = 0;
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            // Get POS cash for this day
            $posCash = DB::table('pos_daily_summaries')
                ->where('sale_date', $currentDate->format('Y-m-d'))
                ->selectRaw('cash_sales - cash_refunds as net_cash')
                ->value('net_cash') ?? 0;

            // Check if any cash lodgements were matched to this day
            $matchedCashOut = DB::table('pos_bank_matches')
                ->join('bank_transactions', 'pos_bank_matches.bank_transaction_id', '=', 'bank_transactions.id')
                ->where('pos_bank_matches.pos_date', $currentDate->format('Y-m-d'))
                ->where('bank_transactions.credit_category', 'cash_lodgement')
                ->sum('bank_transactions.credit_amount') ?? 0;

            // Add POS cash, subtract any lodgements matched to this day
            $runningBalance += $posCash - $matchedCashOut;

            $timeline[] = [
                'date' => $currentDate->format('Y-m-d'),
                'pos_cash' => $posCash,
                'matched_lodgement' => $matchedCashOut,
                'daily_change' => $posCash - $matchedCashOut,
                'running_balance' => $runningBalance,
                'day_name' => $currentDate->format('l'),
                'is_weekend' => $currentDate->isWeekend(),
            ];

            $currentDate->addDay();
        }

        return [
            'timeline' => $timeline,
            'final_balance' => $runningBalance,
        ];
    }

    /**
     * Suggest potential matches between accumulated cash and unmatched cash lodgements
     */
    public function suggestCashMatches(Carbon $startDate, Carbon $endDate): array
    {
        $reconciliation = $this->getCashReconciliationSummary($startDate, $endDate);
        $suggestions = [];

        foreach ($reconciliation['unmatched_lodgements'] as $lodgement) {
            $amount = $lodgement->cash_amount;
            $lodgementDate = Carbon::parse($lodgement->lodgement_date);

            // Look for periods where accumulated available cash approximately matches the lodgement amount
            $timeline = $reconciliation['cash_flow_timeline'];

            foreach ($timeline as $index => $day) {
                $dayBalance = $day['running_available_balance'];
                $variance = abs($dayBalance - $amount);
                $variancePercent = $dayBalance > 0 ? ($variance / $dayBalance) * 100 : 100;

                // Consider it a potential match if within 20% variance and reasonable timing
                if ($variancePercent <= 20 && $dayBalance > 0) {
                    $daysSinceCash = Carbon::parse($day['date'])->diffInDays($lodgementDate, false);

                    // Prefer matches where lodgement is 0-7 days after the cash accumulation
                    if ($daysSinceCash >= 0 && $daysSinceCash <= 7) {
                        $confidence = max(50, 100 - $variancePercent - ($daysSinceCash * 3));

                        $suggestions[] = [
                            'lodgement_id' => $lodgement->id,
                            'lodgement_date' => $lodgement->lodgement_date->format('Y-m-d'),
                            'lodgement_amount' => $amount,
                            'suggested_pos_date' => $day['date'],
                            'accumulated_cash' => $dayBalance,
                            'variance' => $variance,
                            'variance_percent' => round($variancePercent, 1),
                            'days_delay' => $daysSinceCash,
                            'confidence' => round($confidence),
                            'description' => "Legacy cash lodgement from {$lodgement->till_name}",
                            'float_info' => [
                                'float_retained' => $day['float_retained'],
                                'supplier_payments' => $day['supplier_payments'],
                                'pos_cash' => $day['pos_cash'],
                                'available_vs_pos' => $day['available_to_lodge'] - $day['pos_cash'],
                            ],
                        ];
                    }
                }
            }
        }

        // Sort by confidence descending
        usort($suggestions, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return array_slice($suggestions, 0, 10); // Return top 10 suggestions
    }
}
