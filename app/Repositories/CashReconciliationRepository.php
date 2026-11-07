<?php

namespace App\Repositories;

use App\Models\CashReconciliation;
use App\Models\Till;
use App\Services\Cash\TillCountService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Compatibility facade over TillCountService (till count rewrite, 2026-11-07).
 *
 * The count itself lives in the service. What remains here is the handful of
 * methods other features still call: the till picker on customer payments, the
 * legacy import the lodgements page runs for days before the cut-over, the
 * customer-payment totals, and the CSV export.
 */
class CashReconciliationRepository
{
    public function __construct(private readonly TillCountService $service) {}

    /**
     * Tills as `id => name`. The ids are the stable `tills.id` values, not the
     * positional numbers the old implementation returned.
     */
    public function getAvailableTills(): Collection
    {
        return $this->service->tillOptions();
    }

    /**
     * The reconciliation for a till's close on a date, importing the old system's
     * count when it exists. Returns null when the till did not close that day or
     * nothing has been counted anywhere: visiting a page no longer creates rows.
     */
    public function findOrImportLegacy(Carbon $date, Till $till): ?CashReconciliation
    {
        $session = $this->service->sessionsOn($till, $date)->last();
        if (! $session) {
            return null;
        }

        return $this->service->reconciliationFor($session)
            ?? $this->service->importFromLegacy($session, $till);
    }

    /**
     * Kept for the lodgements index. Same contract as findOrImportLegacy but
     * addressed by the till id, as that caller has it.
     */
    public function getOrCreateReconciliation(Carbon $date, int $tillId, ?string $tillName = null): ?CashReconciliation
    {
        $till = Till::find($tillId) ?? ($tillName ? Till::forHost($tillName) : null);

        return $till ? $this->findOrImportLegacy($date, $till) : null;
    }

    /**
     * Cash and card totals for non-void customer-invoice payments taken through a
     * till on a date.
     *
     * @return array{cash: float, card: float}
     */
    public function getCustomerTillPaymentTotals($date, $tillId, ?string $tillName = null): array
    {
        $till = Till::find($tillId) ?? ($tillName ? Till::forHost($tillName) : null);
        if (! $till) {
            return ['cash' => 0.0, 'card' => 0.0];
        }

        $totals = $this->service->customerTillPayments(Carbon::parse($date), $till);

        return ['cash' => $totals['cash'], 'card' => $totals['card']];
    }

    public function getSuppliers(): Collection
    {
        return $this->service->suppliers();
    }

    /**
     * Export counted reconciliations to CSV.
     */
    public function exportToCsv(Carbon $startDate, Carbon $endDate, ?int $tillId = null): string
    {
        $query = CashReconciliation::with(['payments.supplier', 'latestNote', 'creator', 'counter'])
            ->counted()
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->orderBy('till_name');

        if ($tillId) {
            $query->where('till_id', $tillId);
        }

        $csv = "Date,Till,Counted By,Total Cash,Float,To Bag,Previous Float,Supplier Payments,Money Added,POS Cash,Variance,Card,POS Card,Notes\n";

        foreach ($query->get() as $rec) {
            $payments = $rec->payments->map(fn ($p) => $p->payee_display_name.': €'.number_format((float) $p->amount, 2))->implode('; ');
            $note = str_replace(["\r", "\n", '"'], [' ', ' ', "'"], (string) ($rec->latestNote?->message ?? ''));

            $csv .= sprintf(
                "%s,%s,%s,%.2f,%.2f,%.2f,%.2f,\"%s\",%.2f,%.2f,%.2f,%.2f,%.2f,\"%s\"\n",
                $rec->date->format('Y-m-d'),
                $rec->till_name,
                $rec->counter?->name ?? $rec->creator?->name ?? '',
                $rec->total_cash_counted,
                $rec->total_float,
                $rec->calculateAvailableToLodge(),
                $rec->previous_float ?? 0,
                $payments,
                $rec->money_added,
                $rec->pos_cash_total,
                $rec->variance,
                $rec->card,
                $rec->pos_card_total,
                $note
            );
        }

        return $csv;
    }
}
