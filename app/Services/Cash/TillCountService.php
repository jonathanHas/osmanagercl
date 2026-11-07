<?php

namespace App\Services\Cash;

use App\Models\CashReconciliation;
use App\Models\CustomerPayment;
use App\Models\POS\ClosedCash;
use App\Models\POS\Payment;
use App\Models\Supplier;
use App\Models\Till;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The end-of-day till count (cash reconciliation), rewritten 2026-11-07.
 *
 * One till close (`CLOSEDCASH.MONEY`) is one count. The person counting empties the
 * drawer, enters what they found by denomination, says what stays in as float, and
 * the rest goes in the bag. The till's own payments give the expected cash and card,
 * and the previous counted session gives the float that was carried in.
 *
 *   day's cash  = counted + supplier payments − previous float − money added (+ legacy cashback)
 *   expected    = POS cash + customer-invoice cash taken at this till
 *   variance    = day's cash − expected
 *   bag         = counted − float kept
 *
 * Everything that reads or writes the reconciliation tables for the count goes
 * through here; CashReconciliationRepository is kept as a thin facade for the
 * callers that still use it (lodgements, customer payments, export).
 */
class TillCountService
{
    /** Denomination column => face value in euro. */
    public const DENOMINATIONS = [
        'cash_50' => 50.0,
        'cash_20' => 20.0,
        'cash_10' => 10.0,
        'cash_5' => 5.0,
        'cash_2' => 2.0,
        'cash_1' => 1.0,
        'cash_50c' => 0.5,
        'cash_20c' => 0.2,
        'cash_10c' => 0.1,
    ];

    /** How the till's tender types fold into the count. Anything else is "other". */
    private const CASH_TYPES = ['cash', 'cashrefund'];

    private const CARD_TYPES = ['magcard', 'magcardrefund'];

    // ------------------------------------------------------------------ tills

    /**
     * Active tills, with any POS host that closed in the last six months registered.
     * The registration is cached for five minutes; the rows are always read fresh.
     *
     * @return Collection<int, Till>
     */
    public function tills(): Collection
    {
        Cache::remember('tills.synced', 300, function () {
            try {
                $hosts = DB::connection('pos')->table('CLOSEDCASH')
                    ->where('DATEEND', '>=', Carbon::now()->subMonths(6))
                    ->distinct()
                    ->orderBy('HOST')
                    ->pluck('HOST');
            } catch (\Throwable $e) {
                return false; // POS down: fall back to what is registered
            }

            foreach ($hosts as $host) {
                Till::forHost($host);
            }

            return true;
        });

        return Till::active()->get();
    }

    /** `id => name`, the shape the old getAvailableTills() returned. */
    public function tillOptions(): Collection
    {
        return $this->tills()->mapWithKeys(fn (Till $t) => [$t->id => $t->name]);
    }

    public function tillByHost(string $host): Till
    {
        return Till::forHost($host);
    }

    // --------------------------------------------------------------- sessions

    /**
     * Till closes for this till whose close time falls on the date, oldest first.
     *
     * @return Collection<int, ClosedCash>
     */
    public function sessionsOn(Till $till, Carbon $date): Collection
    {
        return ClosedCash::where('HOST', $till->pos_host)
            ->whereDate('DATEEND', $date->toDateString())
            ->orderBy('DATEEND')
            ->get();
    }

    public function session(string $moneyId): ?ClosedCash
    {
        return ClosedCash::find($moneyId);
    }

    public function latestClosed(Till $till): ?ClosedCash
    {
        return ClosedCash::where('HOST', $till->pos_host)
            ->whereNotNull('DATEEND')
            ->orderByDesc('DATEEND')
            ->first();
    }

    public function openSession(Till $till): ?ClosedCash
    {
        return ClosedCash::where('HOST', $till->pos_host)
            ->whereNull('DATEEND')
            ->orderByDesc('DATESTART')
            ->first();
    }

    /**
     * The closest close dates either side of a date for a till.
     *
     * @return array{prev: ?string, next: ?string}
     */
    public function adjacentDates(Till $till, Carbon $date): array
    {
        $base = ClosedCash::where('HOST', $till->pos_host)->whereNotNull('DATEEND');

        $prev = (clone $base)->whereDate('DATEEND', '<', $date->toDateString())->orderByDesc('DATEEND')->value('DATEEND');
        $next = (clone $base)->whereDate('DATEEND', '>', $date->toDateString())->orderBy('DATEEND')->value('DATEEND');

        return [
            'prev' => $prev ? Carbon::parse($prev)->toDateString() : null,
            'next' => $next ? Carbon::parse($next)->toDateString() : null,
        ];
    }

    public function reconciliationFor(ClosedCash $session): ?CashReconciliation
    {
        return CashReconciliation::where('closed_cash_id', $session->MONEY)->first();
    }

    /**
     * Where each till stands on a date: its last close that day, whether it has
     * been counted and by whom, or that it is still open.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tillStatuses(Carbon $date): array
    {
        $out = [];

        foreach ($this->tills() as $till) {
            $session = $this->sessionsOn($till, $date)->last();
            $reconciliation = $session ? $this->reconciliationFor($session) : null;

            $out[] = [
                'id' => $till->id,
                'name' => $till->name,
                'session_id' => $session?->MONEY,
                'closed_at' => $session?->DATEEND?->format('H:i'),
                'open' => $session === null && $this->openSession($till) !== null,
                'counted' => $reconciliation?->counted_at !== null,
                'counted_by' => $reconciliation?->counter?->name,
                'counted_at' => $reconciliation?->counted_at?->format('H:i'),
                'variance' => $reconciliation?->counted_at ? (float) $reconciliation->variance : null,
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------- POS totals

    /**
     * What the till recorded for one close, by how it affects the count.
     *
     * @return array{cash: float, card: float, voucher: float, free: float, debt: float, debtpaid: float, other: array<string, float>, sales: float}
     */
    public function posTotals(string $moneyId): array
    {
        $rows = Payment::select('PAYMENT', DB::raw('SUM(TOTAL) as total'))
            ->join('RECEIPTS', 'PAYMENTS.RECEIPT', '=', 'RECEIPTS.ID')
            ->where('RECEIPTS.MONEY', $moneyId)
            ->groupBy('PAYMENT')
            ->pluck('total', 'PAYMENT')
            ->map(fn ($t) => round((float) $t, 2));

        $sum = fn (array $types) => round($rows->only($types)->sum(), 2);

        $known = [...self::CASH_TYPES, ...self::CARD_TYPES, 'paperin', 'free', 'debt', 'debtpaid'];

        return [
            'cash' => $sum(self::CASH_TYPES),
            'card' => $sum(self::CARD_TYPES),
            'voucher' => $sum(['paperin']),
            'free' => $sum(['free']),
            'debt' => $sum(['debt']),
            'debtpaid' => $sum(['debtpaid']),
            'other' => $rows->except($known)->filter(fn ($t) => abs($t) >= 0.005)->all(),
            'sales' => round($rows->filter(fn ($t, $type) => $type !== 'debtpaid')->sum(), 2),
        ];
    }

    /**
     * Customer-invoice payments taken at this till on a date: real money in the
     * drawer or on the terminal that the POS never saw as a sale.
     *
     * @return array{cash: float, card: float, items: Collection<int, CustomerPayment>}
     */
    public function customerTillPayments(Carbon $date, Till $till): array
    {
        $items = CustomerPayment::query()
            ->whereDate('payment_date', $date->toDateString())
            ->where(function ($q) use ($till) {
                $q->where('till_id', (string) $till->id)->orWhere('till_name', $till->pos_host);
            })
            ->whereIn('method', [CustomerPayment::METHOD_CARD_TILL, CustomerPayment::METHOD_CASH_TILL])
            ->notVoid()
            ->with('customer')
            ->orderBy('id')
            ->get();

        return [
            'cash' => round((float) $items->where('method', CustomerPayment::METHOD_CASH_TILL)->sum('amount'), 2),
            'card' => round((float) $items->where('method', CustomerPayment::METHOD_CARD_TILL)->sum('amount'), 2),
            'items' => $items,
        ];
    }

    // ---------------------------------------------------------- previous float

    /**
     * The float carried into this session: what the previous counted session of
     * the same till left in the drawer. Null when nothing is known, in which case
     * the form asks for it.
     *
     * @return ?array{amount: float, notes: float, coins: float, source: string, date: ?string, reconciliation_id: ?string}
     */
    public function previousFloat(Till $till, ClosedCash $session): ?array
    {
        $end = $session->DATEEND ?? Carbon::now();

        $candidates = CashReconciliation::where('till_id', $till->id)
            ->whereNotNull('counted_at')
            ->where('closed_cash_id', '!=', $session->MONEY)
            ->whereDate('date', '<=', $end->toDateString())
            ->orderByDesc('date')
            ->limit(6)
            ->get();

        if ($candidates->isNotEmpty()) {
            $ends = ClosedCash::whereIn('MONEY', $candidates->pluck('closed_cash_id'))
                ->pluck('DATEEND', 'MONEY');

            $previous = $candidates
                ->filter(function (CashReconciliation $r) use ($ends, $end) {
                    $closed = $ends[$r->closed_cash_id] ?? null;

                    // A row whose POS session is gone keeps its place by date alone.
                    return $closed === null ? $r->date->lt($end->copy()->startOfDay()) : Carbon::parse($closed)->lt($end);
                })
                ->sortByDesc(fn (CashReconciliation $r) => ($ends[$r->closed_cash_id] ?? $r->date->toDateTimeString()))
                ->first();

            if ($previous) {
                return [
                    'amount' => round((float) $previous->note_float + (float) $previous->coin_float, 2),
                    'notes' => (float) $previous->note_float,
                    'coins' => (float) $previous->coin_float,
                    'source' => 'count',
                    'date' => $previous->date->toDateString(),
                    'reconciliation_id' => $previous->id,
                ];
            }
        }

        return $this->legacyPreviousFloat($till, $end);
    }

    private function legacyPreviousFloat(Till $till, Carbon $before): ?array
    {
        if (! $this->hasLegacyTable('money')) {
            return null;
        }

        $row = DB::connection('pos')->table('CLOSEDCASH')
            ->join('money', 'CLOSEDCASH.MONEY', '=', 'money.ID')
            ->where('CLOSEDCASH.HOST', $till->pos_host)
            ->where('CLOSEDCASH.DATEEND', '<', $before)
            ->where(fn ($q) => $q->whereNotNull('money.noteFloat')->orWhereNotNull('money.coinFloat'))
            ->orderByDesc('CLOSEDCASH.DATEEND')
            ->select('money.noteFloat', 'money.coinFloat', 'CLOSEDCASH.DATEEND')
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'amount' => round((float) ($row->noteFloat ?? 0) + (float) ($row->coinFloat ?? 0), 2),
            'notes' => (float) ($row->noteFloat ?? 0),
            'coins' => (float) ($row->coinFloat ?? 0),
            'source' => 'legacy',
            'date' => Carbon::parse($row->DATEEND)->toDateString(),
            'reconciliation_id' => null,
        ];
    }

    // ------------------------------------------------------------- form state

    /**
     * Everything the count form needs for one session, as plain data.
     *
     * @return array<string, mixed>
     */
    public function stateFor(Till $till, ClosedCash $session, ?CashReconciliation $existing = null): array
    {
        $existing ??= $this->reconciliationFor($session);
        $existing?->load(['payments', 'latestNote', 'counter', 'bagVerification']);

        $pos = $this->posTotals($session->MONEY);
        $customer = $this->customerTillPayments($session->DATEEND ?? Carbon::now(), $till);
        $previous = $this->previousFloat($till, $session);

        $counts = [];
        foreach (array_keys(self::DENOMINATIONS) as $column) {
            $counts[$column] = (int) ($existing?->{$column} ?? 0);
        }

        $counted = $existing?->counted_at !== null;

        return [
            'session' => [
                'id' => $session->MONEY,
                'till_id' => $till->id,
                'till' => $till->name,
                'date' => $session->DATEEND?->toDateString(),
                'opened_at' => $session->DATESTART?->format('D j M H:i'),
                'closed_at' => $session->DATEEND?->format('D j M H:i'),
                'receipts' => (int) $session->NOSALES,
            ],
            'pos' => $pos,
            'customer' => [
                'cash' => $customer['cash'],
                'card' => $customer['card'],
                'items' => $customer['items']->map(fn (CustomerPayment $p) => [
                    'customer' => $p->customer?->name,
                    'method' => $p->method === CustomerPayment::METHOD_CASH_TILL ? 'cash' : 'card',
                    'amount' => (float) $p->amount,
                ])->values()->all(),
            ],
            'previous_float' => $previous,
            'counted' => $counted,
            'reconciliation_id' => $existing?->id,
            'counted_by' => $existing?->counter?->name,
            'counted_at' => $existing?->counted_at?->format('D j M H:i'),
            'source' => $existing?->source,
            'bag_verified' => $existing?->bagVerification !== null,
            'counts' => $counts,
            'note_float' => (float) ($existing?->note_float ?? ($previous['notes'] ?? 0)),
            'coin_float' => $existing ? (float) $existing->coin_float : null, // null = "all coins"
            'card' => (float) ($existing?->card ?? 0),
            'money_added' => (float) ($existing?->money_added ?? 0),
            'stored_previous_float' => $existing?->previous_float !== null ? (float) $existing->previous_float : null,
            'payments' => $existing?->payments->map(fn ($p) => [
                'id' => $p->id,
                'supplier_id' => $p->supplier_id,
                'payee_name' => $p->payee_name,
                'amount' => (float) $p->amount,
            ])->values()->all() ?? [],
            'note' => $existing?->latestNote?->message ?? '',
            'legacy' => $existing ? array_filter([
                'cash_back' => (float) $existing->cash_back,
                'cheque' => (float) $existing->cheque,
                'debt' => (float) $existing->debt,
                'debt_paid_cash' => (float) $existing->debt_paid_cash,
                'debt_paid_cheque' => (float) $existing->debt_paid_cheque,
                'debt_paid_card' => (float) $existing->debt_paid_card,
                'free' => (float) $existing->free,
                'voucher_used' => (float) $existing->voucher_used,
            ], fn ($v) => abs($v) >= 0.005) : [],
            'variance' => $counted ? (float) $existing->variance : null,
            'thresholds' => [
                'ok' => (float) config('till_count.variance_ok'),
                'warn' => (float) config('till_count.variance_warn'),
                'note_required_at' => (float) config('till_count.note_required_at'),
            ],
        ];
    }

    /** Suppliers for the payment picker, `SupplierID => Supplier`. */
    public function suppliers(): Collection
    {
        return Supplier::orderBy('Supplier')->get(['SupplierID', 'Supplier'])
            ->mapWithKeys(fn ($s) => [$s->SupplierID => $s->Supplier]);
    }

    // -------------------------------------------------------------------- save

    /**
     * Record a count. $data is validated by StoreTillCountRequest:
     *   cash_50 … cash_10c (int), note_float, coin_float, card, money_added,
     *   previous_float (only honoured when none can be resolved), payments[] of
     *   {id?, supplier_id?, payee_name?, amount}, note.
     */
    public function save(ClosedCash $session, Till $till, User $counter, User $actor, array $data): CashReconciliation
    {
        return DB::transaction(function () use ($session, $till, $counter, $actor, $data) {
            $reconciliation = CashReconciliation::firstOrNew(['closed_cash_id' => $session->MONEY]);

            if (! $reconciliation->exists) {
                $reconciliation->fill([
                    'date' => $session->DATEEND?->toDateString() ?? Carbon::now()->toDateString(),
                    'till_name' => $till->pos_host,
                    'till_id' => $till->id,
                    'created_by' => $actor->id,
                ]);
            } else {
                $reconciliation->updated_by = $actor->id;
                $reconciliation->till_id = $till->id;
            }

            foreach (array_keys(self::DENOMINATIONS) as $column) {
                $reconciliation->{$column} = (int) ($data[$column] ?? 0);
            }

            $reconciliation->total_cash_counted = $reconciliation->calculateTotalCash();
            $reconciliation->note_float = round((float) ($data['note_float'] ?? 0), 2);
            $reconciliation->coin_float = array_key_exists('coin_float', $data) && $data['coin_float'] !== null
                ? round((float) $data['coin_float'], 2)
                : round($reconciliation->calculateTotalCoins(), 2);
            $reconciliation->card = round((float) ($data['card'] ?? 0), 2);
            $reconciliation->money_added = round((float) ($data['money_added'] ?? 0), 2);

            $pos = $this->posTotals($session->MONEY);
            $reconciliation->pos_cash_total = $pos['cash'];
            $reconciliation->pos_card_total = $pos['card'];

            $previous = $this->previousFloat($till, $session);
            if ($previous !== null) {
                $reconciliation->previous_float = $previous['amount'];
                $reconciliation->previous_reconciliation_id = $previous['reconciliation_id'];
            } else {
                $reconciliation->previous_float = round((float) ($data['previous_float'] ?? 0), 2);
                $reconciliation->previous_reconciliation_id = null;
            }

            $reconciliation->counted_by = $counter->id;
            $reconciliation->counted_at = now();
            $reconciliation->source = 'app';

            // Save before the payments so a new row has its id for the relation.
            $reconciliation->variance = 0;
            $reconciliation->save();

            $this->syncPayments($reconciliation, $data['payments'] ?? []);
            $this->syncNote($reconciliation, trim((string) ($data['note'] ?? '')), $actor);

            $reconciliation->variance = $this->variance($reconciliation->fresh(['payments']));
            $reconciliation->save();

            return $reconciliation->fresh(['payments', 'latestNote', 'counter']);
        });
    }

    /**
     * Keep the rows the form sent back by id, add the new ones, drop the rest.
     * Existing rows keep their created_at, which the P&L sums by.
     */
    private function syncPayments(CashReconciliation $reconciliation, array $payments): void
    {
        $keep = [];
        $sequence = 0;

        foreach ($payments as $payment) {
            $amount = round((float) ($payment['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }

            $attributes = [
                'supplier_id' => $payment['supplier_id'] ?? null,
                'payee_name' => $payment['payee_name'] ?? null,
                'amount' => $amount,
                'sequence' => $sequence++,
                'description' => $payment['description'] ?? null,
            ];

            $existing = ! empty($payment['id'])
                ? $reconciliation->payments()->whereKey($payment['id'])->first()
                : null;

            if ($existing) {
                $existing->update($attributes);
                $keep[] = $existing->id;
            } else {
                $keep[] = $reconciliation->payments()->create($attributes)->id;
            }
        }

        $reconciliation->payments()->whereNotIn('id', $keep)->delete();
    }

    /** One note per count, edited in place; an emptied note is removed. */
    private function syncNote(CashReconciliation $reconciliation, string $message, User $actor): void
    {
        $note = $reconciliation->notes()->latest()->first();

        if ($message === '') {
            $note?->delete();

            return;
        }

        if ($note) {
            if ($note->message !== $message) {
                $note->update(['message' => $message]);
            }

            return;
        }

        $reconciliation->notes()->create(['message' => $message, 'created_by' => $actor->id]);
    }

    // --------------------------------------------------------------- variance

    /**
     * The cash variance from the row's own fields. `cash_back` is zero on anything
     * counted here; it stays in the formula so legacy rows recompute as they were.
     */
    public function variance(CashReconciliation $r): float
    {
        $customer = $this->customerTillPayments(
            $r->date instanceof Carbon ? $r->date : Carbon::parse($r->date),
            $this->tillFor($r)
        );

        $daysCash = (float) $r->total_cash_counted
            + (float) $r->cash_back
            + (float) $r->payments->sum('amount')
            - (float) ($r->previous_float ?? 0)
            - (float) $r->money_added;

        return round($daysCash - (float) $r->pos_cash_total - $customer['cash'], 2);
    }

    /**
     * Re-resolve the previous float and recompute the variance of a stored row.
     *
     * @return array{before: float, after: float, previous_before: ?float, previous_after: ?float}
     */
    public function recalculate(CashReconciliation $r, bool $persist = true): array
    {
        $r->loadMissing('payments');
        $till = $this->tillFor($r);
        $session = ClosedCash::find($r->closed_cash_id);

        $before = (float) $r->variance;
        $previousBefore = $r->previous_float !== null ? (float) $r->previous_float : null;

        if ($session) {
            $previous = $this->previousFloat($till, $session);
            if ($previous !== null) {
                $r->previous_float = $previous['amount'];
                $r->previous_reconciliation_id = $previous['reconciliation_id'];
            } elseif ($r->previous_float === null) {
                $r->previous_float = 0;
            }
        } elseif ($r->previous_float === null) {
            $r->previous_float = 0;
        }

        $r->variance = $this->variance($r);

        if ($persist && $r->isDirty()) {
            $r->saveQuietly();
        }

        return [
            'before' => $before,
            'after' => (float) $r->variance,
            'previous_before' => $previousBefore,
            'previous_after' => (float) $r->previous_float,
        ];
    }

    private function tillFor(CashReconciliation $r): Till
    {
        return Till::find($r->till_id) ?? Till::forHost($r->till_name);
    }

    // ----------------------------------------------------------------- legacy

    /**
     * Mirror the old system's count for a close into a reconciliation. Creates
     * nothing when the old system has no `money` row for it. With $overwrite the
     * existing row's figures are replaced (the "Sync from POS" button).
     */
    public function importFromLegacy(ClosedCash $session, Till $till, bool $overwrite = false, ?User $actor = null): ?CashReconciliation
    {
        if (! $this->hasLegacyTable('money')) {
            return null;
        }

        $money = DB::connection('pos')->table('money')->where('ID', $session->MONEY)->first();
        if (! $money) {
            return null;
        }

        $existing = $this->reconciliationFor($session);
        if ($existing && ! $overwrite) {
            return $existing;
        }

        return DB::transaction(function () use ($session, $till, $money, $existing, $actor) {
            $r = $existing ?? new CashReconciliation([
                'closed_cash_id' => $session->MONEY,
                'date' => $session->DATEEND?->toDateString(),
                'till_name' => $till->pos_host,
                'till_id' => $till->id,
                'created_by' => $actor?->id ?? 1,
            ]);

            $r->fill([
                'cash_50' => $money->cash50 ? intval($money->cash50 / 50) : 0,
                'cash_20' => $money->cash20 ? intval($money->cash20 / 20) : 0,
                'cash_10' => $money->cash10 ? intval($money->cash10 / 10) : 0,
                'cash_5' => $money->cash5 ? intval($money->cash5 / 5) : 0,
                'cash_2' => $money->cash2 ? intval($money->cash2 / 2) : 0,
                'cash_1' => $money->cash1 ? intval($money->cash1 / 1) : 0,
                'cash_50c' => $money->cash50c ? (int) round($money->cash50c / 0.5) : 0,
                'cash_20c' => $money->cash20c ? (int) round($money->cash20c / 0.2) : 0,
                'cash_10c' => $money->cash10c ? (int) round($money->cash10c / 0.1) : 0,
                'note_float' => $money->noteFloat ?? 0,
                'coin_float' => $money->coinFloat ?? 0,
                'card' => $money->card ?? 0,
                'cash_back' => $money->cashBack ?? 0,
                'cheque' => $money->cheque ?? 0,
                'debt' => $money->debt ?? 0,
                'debt_paid_cash' => $money->debtPaidCash ?? 0,
                'debt_paid_cheque' => $money->debtPaidCheque ?? 0,
                'debt_paid_card' => $money->debtPaidCard ?? 0,
                'free' => $money->free ?? 0,
                'voucher_used' => $money->voucherUsed ?? 0,
                'money_added' => $money->moneyAdded ?? 0,
            ]);
            $r->total_cash_counted = $r->calculateTotalCash();

            $pos = $this->posTotals($session->MONEY);
            $r->pos_cash_total = $pos['cash'];
            $r->pos_card_total = $pos['card'];

            $r->source = 'legacy';
            $r->counted_at = $session->DATEEND ?? now();
            $r->till_id = $till->id;
            if ($actor && $existing) {
                $r->updated_by = $actor->id;
            }
            $r->variance = 0;
            $r->save();

            $this->importLegacyPayments($r, $session);
            $this->importLegacyNote($r, $session, $actor);

            $previous = $this->previousFloat($till, $session);
            $r->previous_float = $previous['amount'] ?? 0;
            $r->previous_reconciliation_id = $previous['reconciliation_id'] ?? null;
            $r->variance = $this->variance($r->fresh(['payments']));
            $r->save();

            return $r->fresh(['payments', 'latestNote']);
        });
    }

    /** The old system's supplier payments, dated to the till close for the P&L. */
    private function importLegacyPayments(CashReconciliation $r, ClosedCash $session): void
    {
        if (! $this->hasLegacyTable('payeePayments')) {
            return;
        }

        $rows = DB::connection('pos')->table('payeePayments')
            ->where('closedCashID', $session->MONEY)
            ->orderBy('sequence')
            ->get();

        $r->payments()->delete();

        $when = $session->DATEEND ?? now();

        foreach ($rows as $i => $row) {
            $payment = $r->payments()->make([
                'supplier_id' => $row->payeeID ?? null,
                'payee_name' => null,
                'amount' => $row->amount ?? 0,
                'sequence' => $row->sequence ?? $i,
                'description' => null,
            ]);
            // Dated to the till close, not the import: the P&L sums these by created_at.
            $payment->created_at = $when;
            $payment->updated_at = $when;
            $payment->save();
        }
    }

    private function importLegacyNote(CashReconciliation $r, ClosedCash $session, ?User $actor): void
    {
        if (! $this->hasLegacyTable('dayNotes')) {
            return;
        }

        $row = DB::connection('pos')->table('dayNotes')->where('closedCashID', $session->MONEY)->first();
        if (! $row || trim((string) $row->message) === '') {
            return;
        }

        $note = $r->notes()->latest()->first();
        if ($note) {
            $note->update(['message' => $row->message]);
        } else {
            $r->notes()->create(['message' => $row->message, 'created_by' => $actor?->id ?? 1]);
        }
    }

    /** @var array<string, bool> */
    private array $legacyTables = [];

    private function hasLegacyTable(string $table): bool
    {
        if (! array_key_exists($table, $this->legacyTables)) {
            try {
                $this->legacyTables[$table] = Schema::connection('pos')->hasTable($table);
            } catch (\Throwable $e) {
                $this->legacyTables[$table] = false;
            }
        }

        return $this->legacyTables[$table];
    }

    // ---------------------------------------------------------------- history

    /**
     * Recent counts for the office table, newest first.
     *
     * @return Collection<int, CashReconciliation>
     */
    public function history(int $limit = 28, ?int $tillId = null): Collection
    {
        return CashReconciliation::with(['counter', 'bagVerification', 'payments'])
            ->when($tillId, fn ($q) => $q->where('till_id', $tillId))
            ->orderByDesc('date')
            ->orderByDesc('counted_at')
            ->limit($limit)
            ->get();
    }
}
