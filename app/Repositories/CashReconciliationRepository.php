<?php

namespace App\Repositories;

use App\Models\CashReconciliation;
use App\Models\POS\ClosedCash;
use App\Models\POS\Payment;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CashReconciliationRepository
{
    /**
     * Get or create reconciliation for a specific date and till
     */
    public function getOrCreateReconciliation(Carbon $date, int $tillId, string $tillName): CashReconciliation
    {
        // Find the closed cash record for this date and till
        $closedCash = ClosedCash::where('HOST', $tillName)
            ->whereDate('DATEEND', $date)
            ->first();

        if (! $closedCash) {
            throw new \Exception("No closed cash record found for till {$tillName} on {$date->format('Y-m-d')}");
        }

        // Find or create reconciliation
        $reconciliation = CashReconciliation::firstOrNew([
            'closed_cash_id' => $closedCash->MONEY,
        ]);

        if (! $reconciliation->exists) {
            // Calculate POS totals
            $posTotals = $this->calculatePosTotals($closedCash->MONEY);

            // Get previous day's float
            $previousFloat = $this->getPreviousDayFloat($date, $tillId);

            // Check for existing money record in legacy system
            $legacyMoney = DB::connection('pos')->table('money')
                ->where('ID', $closedCash->MONEY)
                ->first();

            $reconciliation->fill([
                'date' => $date,
                'till_name' => $tillName,
                'till_id' => $tillId,
                'pos_cash_total' => $posTotals['cash'],
                'pos_card_total' => $posTotals['card'],
                'note_float' => $legacyMoney->noteFloat ?? $previousFloat['notes'],
                'coin_float' => $legacyMoney->coinFloat ?? $previousFloat['coins'],
                'created_by' => auth()->id() ?? 1,
            ]);

            // Import cash counts from legacy money table if they exist
            // Legacy system stores TOTALS, not counts, so we need to convert
            if ($legacyMoney) {
                $reconciliation->fill([
                    'cash_50' => $legacyMoney->cash50 ? intval($legacyMoney->cash50 / 50) : 0,
                    'cash_20' => $legacyMoney->cash20 ? intval($legacyMoney->cash20 / 20) : 0,
                    'cash_10' => $legacyMoney->cash10 ? intval($legacyMoney->cash10 / 10) : 0,
                    'cash_5' => $legacyMoney->cash5 ? intval($legacyMoney->cash5 / 5) : 0,
                    'cash_2' => $legacyMoney->cash2 ? intval($legacyMoney->cash2 / 2) : 0,
                    'cash_1' => $legacyMoney->cash1 ? intval($legacyMoney->cash1 / 1) : 0,
                    'cash_50c' => $legacyMoney->cash50c ? intval($legacyMoney->cash50c / 0.5) : 0,
                    'cash_20c' => $legacyMoney->cash20c ? intval($legacyMoney->cash20c / 0.2) : 0,
                    'cash_10c' => $legacyMoney->cash10c ? intval($legacyMoney->cash10c / 0.1) : 0,
                    'card' => $legacyMoney->card ?? 0,
                    'cash_back' => $legacyMoney->cashBack ?? 0,
                    'cheque' => $legacyMoney->cheque ?? 0,
                    'debt' => $legacyMoney->debt ?? 0,
                    'debt_paid_cash' => $legacyMoney->debtPaidCash ?? 0,
                    'debt_paid_cheque' => $legacyMoney->debtPaidCheque ?? 0,
                    'debt_paid_card' => $legacyMoney->debtPaidCard ?? 0,
                    'free' => $legacyMoney->free ?? 0,
                    'voucher_used' => $legacyMoney->voucherUsed ?? 0,
                    'money_added' => $legacyMoney->moneyAdded ?? 0,
                ]);

                // Calculate total cash counted
                $reconciliation->total_cash_counted = $reconciliation->calculateTotalCash();

                // Calculate variance
                $daysCashTakings = $reconciliation->total_cash_counted + ($legacyMoney->cashBack ?? 0) -
                                  ($previousFloat['notes'] + $previousFloat['coins']) -
                                  ($legacyMoney->moneyAdded ?? 0);

                $reconciliation->variance = $daysCashTakings - $reconciliation->pos_cash_total;
            }

            $reconciliation->save();

            // Import legacy supplier payments if they exist
            if ($legacyMoney) {
                $this->importLegacyPayments($reconciliation, $closedCash->MONEY);
                $this->importLegacyNotes($reconciliation, $closedCash->MONEY);
            }
        }

        return $reconciliation->load(['payments', 'latestNote']);
    }

    /**
     * Calculate POS totals from payments
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
     * Get previous day's float
     */
    private function getPreviousDayFloat(Carbon $date, int $tillId): array
    {
        $previousReconciliation = CashReconciliation::where('till_id', $tillId)
            ->where('date', '<', $date)
            ->orderBy('date', 'desc')
            ->first();

        if ($previousReconciliation) {
            return [
                'notes' => $previousReconciliation->note_float,
                'coins' => $previousReconciliation->coin_float,
            ];
        }

        return ['notes' => 0, 'coins' => 0];
    }

    /**
     * Save reconciliation data
     */
    public function saveReconciliation(CashReconciliation $reconciliation, array $data): CashReconciliation
    {
        DB::transaction(function () use ($reconciliation, $data) {
            // Update cash counts
            $reconciliation->fill([
                'cash_50' => $data['cash_50'] ?? 0,
                'cash_20' => $data['cash_20'] ?? 0,
                'cash_10' => $data['cash_10'] ?? 0,
                'cash_5' => $data['cash_5'] ?? 0,
                'cash_2' => $data['cash_2'] ?? 0,
                'cash_1' => $data['cash_1'] ?? 0,
                'cash_50c' => $data['cash_50c'] ?? 0,
                'cash_20c' => $data['cash_20c'] ?? 0,
                'cash_10c' => $data['cash_10c'] ?? 0,
                'note_float' => $data['note_float'] ?? 0,
                'coin_float' => $data['coin_float'] ?? 0,
                'card' => $data['card'] ?? 0,
                'cash_back' => $data['cash_back'] ?? 0,
                'cheque' => $data['cheque'] ?? 0,
                'debt' => $data['debt'] ?? 0,
                'debt_paid_cash' => $data['debt_paid_cash'] ?? 0,
                'debt_paid_cheque' => $data['debt_paid_cheque'] ?? 0,
                'debt_paid_card' => $data['debt_paid_card'] ?? 0,
                'free' => $data['free'] ?? 0,
                'voucher_used' => $data['voucher_used'] ?? 0,
                'money_added' => $data['money_added'] ?? 0,
                'updated_by' => auth()->id(),
            ]);

            // Calculate totals
            $totalCash = $reconciliation->calculateTotalCash();
            $reconciliation->total_cash_counted = $totalCash;

            // Calculate variance
            $previousFloat = $this->getPreviousDayFloat($reconciliation->date, $reconciliation->till_id);
            $daysCashTakings = $totalCash + ($data['cash_back'] ?? 0) -
                              ($previousFloat['notes'] + $previousFloat['coins']) -
                              ($data['money_added'] ?? 0);

            $reconciliation->variance = $daysCashTakings - $reconciliation->pos_cash_total;

            $reconciliation->save();

            // Handle payments
            if (isset($data['payments'])) {
                $this->savePayments($reconciliation, $data['payments']);
            }

            // Handle notes
            if (! empty($data['notes'])) {
                $reconciliation->notes()->create([
                    'message' => $data['notes'],
                    'created_by' => auth()->id(),
                ]);
            }
        });

        return $reconciliation->fresh(['payments', 'latestNote']);
    }

    /**
     * Save payment records
     */
    private function savePayments(CashReconciliation $reconciliation, array $payments): void
    {
        // Delete existing payments
        $reconciliation->payments()->delete();

        // Create new payments
        foreach ($payments as $index => $payment) {
            if (empty($payment['amount']) || $payment['amount'] == 0) {
                continue;
            }

            $reconciliation->payments()->create([
                'supplier_id' => $payment['supplier_id'] ?? null,
                'payee_name' => $payment['payee_name'] ?? null,
                'amount' => $payment['amount'],
                'sequence' => $index,
                'description' => $payment['description'] ?? null,
            ]);
        }
    }

    /**
     * Get available tills
     */
    public function getAvailableTills(): Collection
    {
        return Cache::remember('available_tills', 3600, function () {
            return DB::connection('pos')
                ->table('CLOSEDCASH')
                ->select('HOST')
                ->distinct()
                ->orderBy('HOST')
                ->pluck('HOST')
                ->mapWithKeys(function ($host, $index) {
                    return [$index + 1 => $host];
                });
        });
    }

    /**
     * Get suppliers for dropdown
     */
    public function getSuppliers(): Collection
    {
        return Supplier::orderBy('Supplier')
            ->get(['SupplierID', 'Supplier'])
            ->mapWithKeys(function ($supplier) {
                return [$supplier->SupplierID => $supplier->Supplier];
            });
    }

    /**
     * Get reconciliation history
     */
    public function getHistory(?int $tillId = null, int $limit = 30): Collection
    {
        $query = CashReconciliation::with(['creator', 'latestNote'])
            ->orderBy('date', 'desc');

        if ($tillId) {
            $query->where('till_id', $tillId);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Export reconciliation data
     */
    public function exportToCsv(Carbon $startDate, Carbon $endDate, ?int $tillId = null): string
    {
        $query = CashReconciliation::with(['payments.supplier', 'latestNote'])
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date');

        if ($tillId) {
            $query->where('till_id', $tillId);
        }

        $reconciliations = $query->get();

        $csv = "Date,Till,Total Cash,POS Cash,Variance,Card,Notes,Payments,Created By\n";

        foreach ($reconciliations as $rec) {
            $payments = $rec->payments->map(function ($p) {
                return $p->payee_display_name.': €'.number_format($p->amount, 2);
            })->implode('; ');

            $csv .= sprintf(
                "%s,%s,%.2f,%.2f,%.2f,%.2f,%s,%s,%s\n",
                $rec->date->format('Y-m-d'),
                $rec->till_name,
                $rec->total_cash_counted,
                $rec->pos_cash_total,
                $rec->variance,
                $rec->card,
                $rec->latestNote?->message ?? '',
                $payments,
                $rec->creator->name
            );
        }

        return $csv;
    }

    /**
     * Import legacy supplier payments
     */
    private function importLegacyPayments(CashReconciliation $reconciliation, string $closedCashId): void
    {
        $legacyPayments = DB::connection('pos')->table('payeePayments')
            ->where('closedCashID', $closedCashId)
            ->orderBy('sequence')
            ->get();

        foreach ($legacyPayments as $payment) {
            // Skip if payment already exists
            if ($reconciliation->payments()->where('sequence', $payment->sequence)->exists()) {
                continue;
            }

            $reconciliation->payments()->create([
                'supplier_id' => $payment->payeeID ?? null,
                'payee_name' => null, // Will use supplier name from relationship
                'amount' => $payment->amount ?? 0,
                'sequence' => $payment->sequence ?? 0,
                'description' => null,
            ]);
        }
    }

    /**
     * Import legacy notes
     */
    private function importLegacyNotes(CashReconciliation $reconciliation, string $closedCashId): void
    {
        $legacyNote = DB::connection('pos')->table('dayNotes')
            ->where('closedCashID', $closedCashId)
            ->first();

        if ($legacyNote && ! $reconciliation->notes()->exists()) {
            $reconciliation->notes()->create([
                'message' => $legacyNote->message,
                'created_by' => auth()->id() ?? 1,
            ]);
        }
    }
}
