<?php

namespace App\Services;

use App\Models\CardReconciliationSetting;
use App\Models\CardTransaction;
use App\Models\POS\Payment;
use App\Models\TerminalTillMapping;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CardReconciliationService
{
    private int $timeWindowMinutes = 5;

    private int $autoMatchThreshold = 80;

    public function __construct(?int $timeWindowMinutes = null, ?int $autoMatchThreshold = null)
    {
        // Only load user settings if we have an authenticated user
        if (auth()->check()) {
            $settings = CardReconciliationSetting::getForUser();
            $this->timeWindowMinutes = $settings->time_window_minutes;
            $this->autoMatchThreshold = $settings->auto_match_threshold;
        }

        // Allow overriding via constructor
        if ($timeWindowMinutes !== null) {
            $this->timeWindowMinutes = $timeWindowMinutes;
        }
        if ($autoMatchThreshold !== null) {
            $this->autoMatchThreshold = $autoMatchThreshold;
        }
    }

    public function reconcileBatch(string $batchId): array
    {
        $transactions = CardTransaction::forBatch($batchId)
            ->whereIn('reconciliation_status', ['pending'])
            ->get();

        $stats = [
            'total' => $transactions->count(),
            'matched' => 0,
            'mismatches' => 0,
            'declined' => 0,
            'orphans' => 0,
        ];

        foreach ($transactions as $transaction) {
            $result = $this->reconcileTransaction($transaction);
            $stats[$result]++;
        }

        return $stats;
    }

    public function reconcileTransaction(CardTransaction $transaction): string
    {
        // Handle declined transactions - they should not match any POS record
        if ($transaction->isDeclined()) {
            $transaction->update([
                'reconciliation_status' => 'declined',
            ]);

            return 'declined';
        }

        // Get mapped till for this terminal (strict matching)
        $posHost = null;
        if ($transaction->terminal_id) {
            $posHost = TerminalTillMapping::getPosHostForTerminal($transaction->terminal_id);
        }

        // Find matching POS payment (filtered by till if mapping exists)
        $match = $this->findBestMatch($transaction, null, $posHost);

        if ($match) {
            $variance = abs((float) $transaction->amount - (float) $match['payment']->TOTAL);
            $status = $variance < 0.01 ? 'matched' : 'mismatch';

            $transaction->update([
                'reconciliation_status' => $status,
                'pos_payment_id' => $match['payment']->ID,
                'confidence_score' => $match['confidence'],
                'variance_amount' => $variance > 0.01 ? $variance : null,
            ]);

            return $status === 'matched' ? 'matched' : 'mismatches';
        }

        // No match found
        $transaction->update([
            'reconciliation_status' => 'orphan',
        ]);

        return 'orphans';
    }

    public function findBestMatch(CardTransaction $transaction, ?int $timeWindowMinutes = null, ?string $posHost = null): ?array
    {
        $window = $timeWindowMinutes ?? $this->timeWindowMinutes;
        $candidates = $this->findPosPayments(
            $transaction->transaction_datetime,
            $transaction->amount,
            $window,
            true, // cardOnly
            $posHost
        );

        if ($candidates->isEmpty()) {
            return null;
        }

        $bestMatch = null;
        $bestConfidence = 0;

        foreach ($candidates as $candidate) {
            $confidence = $this->calculateConfidence($transaction, $candidate);

            if ($confidence > $bestConfidence && $confidence >= $this->autoMatchThreshold) {
                $bestMatch = $candidate;
                $bestConfidence = $confidence;
            }
        }

        if ($bestMatch) {
            return [
                'payment' => $bestMatch,
                'confidence' => $bestConfidence,
            ];
        }

        return null;
    }

    public function findPosPayments(Carbon $datetime, float $amount, int $windowMinutes, bool $cardOnly = true, ?string $posHost = null): Collection
    {
        $startTime = $datetime->copy()->subMinutes($windowMinutes);
        $endTime = $datetime->copy()->addMinutes($windowMinutes);

        // Query POS database for payments within time window
        // Join CLOSEDCASH to get the till HOST for terminal-to-till filtering
        $query = Payment::query()
            ->join('RECEIPTS', 'PAYMENTS.RECEIPT', '=', 'RECEIPTS.ID')
            ->join('CLOSEDCASH', 'RECEIPTS.MONEY', '=', 'CLOSEDCASH.MONEY')
            ->where('PAYMENTS.TOTAL', '>', 0)
            ->whereBetween('RECEIPTS.DATENEW', [$startTime, $endTime])
            ->select('PAYMENTS.*', 'RECEIPTS.DATENEW as receipt_datetime', 'CLOSEDCASH.HOST as till_name');

        if ($cardOnly) {
            $query->where('PAYMENTS.PAYMENT', 'magcard');
        }

        // Filter by specific till if terminal mapping exists
        if ($posHost) {
            $query->where('CLOSEDCASH.HOST', $posHost);
        }

        return $query->get();
    }

    public function calculateConfidence(CardTransaction $cardTx, Payment $posPayment): int
    {
        $confidence = 0;

        // Amount scoring (0-50 points)
        $confidence += $this->calculateAmountScore($cardTx->amount, (float) $posPayment->TOTAL);

        // Time scoring (0-40 points)
        $confidence += $this->calculateTimeScore($cardTx->transaction_datetime, $posPayment->receipt_datetime);

        // Card type bonus (0-10 points)
        $confidence += $this->calculateCardTypeScore($cardTx->processor, $posPayment->CARDNAME);

        return min(100, $confidence);
    }

    private function calculateAmountScore(float $cardAmount, float $posAmount): int
    {
        $diff = abs($cardAmount - $posAmount);

        if ($diff < 0.01) {
            return 50;  // Exact match
        }
        if ($diff <= 0.01) {
            return 45;  // Within 1 cent
        }
        if ($diff <= $cardAmount * 0.01) {
            return 30;  // Within 1%
        }
        if ($diff <= $cardAmount * 0.05) {
            return 15;  // Within 5%
        }
        if ($diff <= $cardAmount * 0.10) {
            return 5;   // Within 10%
        }

        return 0;
    }

    private function calculateTimeScore(Carbon $cardTime, $posTime): int
    {
        $posDateTime = $posTime instanceof Carbon ? $posTime : Carbon::parse($posTime);
        $diffSeconds = abs($cardTime->diffInSeconds($posDateTime));

        if ($diffSeconds <= 30) {
            return 40;  // Within 30 seconds
        }
        if ($diffSeconds <= 60) {
            return 30;  // Within 1 minute
        }
        if ($diffSeconds <= 180) {
            return 20;  // Within 3 minutes
        }
        if ($diffSeconds <= 300) {
            return 15;  // Within 5 minutes
        }
        if ($diffSeconds <= 600) {
            return 5;   // Within 10 minutes
        }

        return 0;
    }

    private function calculateCardTypeScore(?string $cardProcessor, ?string $posCardName): int
    {
        if (empty($cardProcessor) || empty($posCardName)) {
            return 0;
        }

        $cardProcessor = strtolower($cardProcessor);
        $posCardName = strtolower($posCardName);

        // Check for Visa
        if (str_contains($cardProcessor, 'visa') && str_contains($posCardName, 'visa')) {
            return 10;
        }

        // Check for Mastercard
        if ((str_contains($cardProcessor, 'master') || str_contains($cardProcessor, 'mc')) &&
            (str_contains($posCardName, 'master') || str_contains($posCardName, 'mc'))) {
            return 10;
        }

        return 0;
    }

    public function manualMatch(CardTransaction $transaction, string $posPaymentId): bool
    {
        $payment = Payment::find($posPaymentId);
        if (! $payment) {
            return false;
        }

        $variance = abs((float) $transaction->amount - (float) $payment->TOTAL);
        $status = $variance < 0.01 ? 'matched' : 'mismatch';

        $transaction->update([
            'reconciliation_status' => $status,
            'pos_payment_id' => $posPaymentId,
            'confidence_score' => 100, // Manual match
            'variance_amount' => $variance > 0.01 ? $variance : null,
            'notes' => ($transaction->notes ? $transaction->notes."\n" : '').'Manually matched by user.',
        ]);

        return true;
    }

    public function unmatch(CardTransaction $transaction): bool
    {
        $transaction->update([
            'reconciliation_status' => $transaction->isDeclined() ? 'declined' : 'pending',
            'pos_payment_id' => null,
            'confidence_score' => null,
            'variance_amount' => null,
            'notes' => ($transaction->notes ? $transaction->notes."\n" : '').'Match removed by user.',
        ]);

        return true;
    }

    public function getBatchStats(string $batchId): array
    {
        return [
            'total' => CardTransaction::forBatch($batchId)->count(),
            'matched' => CardTransaction::forBatch($batchId)->matched()->count(),
            'mismatches' => CardTransaction::forBatch($batchId)->mismatches()->count(),
            'declined' => CardTransaction::forBatch($batchId)->declined()->count(),
            'orphans' => CardTransaction::forBatch($batchId)->orphans()->count(),
            'pending' => CardTransaction::forBatch($batchId)->pending()->count(),
        ];
    }

    public function findNearbyPosPayments(CardTransaction $transaction, int $extendedWindowMinutes = 30, bool $cardOnly = true, ?string $posHost = null, bool $showAllTills = false): Collection
    {
        // If not showing all tills and no posHost specified, try to get from terminal mapping
        $effectivePosHost = $posHost;
        if (! $showAllTills && ! $posHost && $transaction->terminal_id) {
            $effectivePosHost = TerminalTillMapping::getPosHostForTerminal($transaction->terminal_id);
        }

        return $this->findPosPayments(
            $transaction->transaction_datetime,
            $transaction->amount,
            $extendedWindowMinutes,
            $cardOnly,
            $showAllTills ? null : $effectivePosHost
        )->map(function ($payment) use ($transaction) {
            return [
                'payment' => $payment,
                'confidence' => $this->calculateConfidence($transaction, $payment),
                'time_diff_seconds' => abs($transaction->transaction_datetime->diffInSeconds($payment->receipt_datetime)),
                'amount_diff' => abs((float) $transaction->amount - (float) $payment->TOTAL),
                'till_name' => $payment->till_name ?? null,
            ];
        })->sortByDesc('confidence');
    }

    public function previewAutoMatch(string $batchId, array $options): array
    {
        $windowMinutes = $options['window_minutes'] ?? 30;
        $minConfidence = $options['min_confidence'] ?? 80;
        $exactAmountOnly = $options['exact_amount_only'] ?? false;
        $cardOnly = $options['card_only'] ?? true;
        $respectTillMapping = $options['respect_till_mapping'] ?? true;

        $orphans = CardTransaction::forBatch($batchId)->orphans()->get();
        $previews = [];

        foreach ($orphans as $tx) {
            // Get mapped till for this terminal (if mapping exists and respected)
            $posHost = null;
            if ($respectTillMapping && $tx->terminal_id) {
                $posHost = TerminalTillMapping::getPosHostForTerminal($tx->terminal_id);
            }

            $candidates = $this->findPosPayments(
                $tx->transaction_datetime,
                $tx->amount,
                $windowMinutes,
                $cardOnly,
                $posHost
            );

            $bestMatch = null;
            $bestConfidence = 0;

            foreach ($candidates as $candidate) {
                $confidence = $this->calculateConfidence($tx, $candidate);
                $variance = abs((float) $tx->amount - (float) $candidate->TOTAL);

                if ($confidence >= $minConfidence && $confidence > $bestConfidence) {
                    if ($exactAmountOnly && $variance >= 0.01) {
                        continue;
                    }
                    $bestMatch = $candidate;
                    $bestConfidence = $confidence;
                }
            }

            if ($bestMatch) {
                $variance = abs((float) $tx->amount - (float) $bestMatch->TOTAL);
                $previews[] = [
                    'card_tx' => $tx,
                    'pos_payment' => $bestMatch,
                    'confidence' => $bestConfidence,
                    'variance' => $variance,
                    'payment_method' => $bestMatch->PAYMENT,
                    'till_name' => $bestMatch->till_name ?? null,
                    'terminal_id' => $tx->terminal_id,
                    'terminal_name' => $tx->terminal_name,
                    'has_mapping' => $posHost !== null,
                ];
            }
        }

        return [
            'total_orphans' => $orphans->count(),
            'will_match' => count($previews),
            'previews' => $previews,
        ];
    }

    public function autoMatchOrphans(string $batchId, array $options): array
    {
        $windowMinutes = $options['window_minutes'] ?? 30;
        $minConfidence = $options['min_confidence'] ?? 80;
        $exactAmountOnly = $options['exact_amount_only'] ?? false;
        $cardOnly = $options['card_only'] ?? true;
        $respectTillMapping = $options['respect_till_mapping'] ?? true;

        $orphans = CardTransaction::forBatch($batchId)->orphans()->get();
        $stats = ['processed' => 0, 'matched' => 0, 'mismatches' => 0, 'still_orphans' => 0];

        foreach ($orphans as $tx) {
            $stats['processed']++;

            // Get mapped till for this terminal (if mapping exists and respected)
            $posHost = null;
            if ($respectTillMapping && $tx->terminal_id) {
                $posHost = TerminalTillMapping::getPosHostForTerminal($tx->terminal_id);
            }

            $candidates = $this->findPosPayments($tx->transaction_datetime, $tx->amount, $windowMinutes, $cardOnly, $posHost);

            $bestMatch = null;
            $bestConfidence = 0;

            foreach ($candidates as $candidate) {
                $confidence = $this->calculateConfidence($tx, $candidate);
                $variance = abs((float) $tx->amount - (float) $candidate->TOTAL);

                if ($confidence >= $minConfidence && $confidence > $bestConfidence) {
                    if ($exactAmountOnly && $variance >= 0.01) {
                        continue;
                    }
                    $bestMatch = $candidate;
                    $bestConfidence = $confidence;
                }
            }

            if ($bestMatch) {
                $variance = abs((float) $tx->amount - (float) $bestMatch->TOTAL);
                $status = $variance < 0.01 ? 'matched' : 'mismatch';

                $tx->update([
                    'reconciliation_status' => $status,
                    'pos_payment_id' => $bestMatch->ID,
                    'confidence_score' => $bestConfidence,
                    'variance_amount' => $variance > 0.01 ? $variance : null,
                    'notes' => ($tx->notes ? $tx->notes."\n" : '').'Auto-matched with extended window.',
                ]);

                $stats[$status === 'matched' ? 'matched' : 'mismatches']++;
            } else {
                $stats['still_orphans']++;
            }
        }

        return $stats;
    }
}
