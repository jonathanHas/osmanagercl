<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerPaymentService
{
    /**
     * Rounding tolerance. Amounts are 2dp decimals; anything inside half a cent
     * is treated as equal so float arithmetic never rejects a valid allocation.
     */
    private const EPSILON = 0.005;

    /**
     * Record a customer payment with optional invoice allocations.
     *
     * @param  array  $paymentData  customer_id, payment_date, amount, method, till_id?, till_name?, reference?, notes?
     * @param  array  $allocations  list of ['customer_invoice_id' => int, 'amount' => float]
     */
    public function recordPayment(array $paymentData, array $allocations = []): CustomerPayment
    {
        return DB::transaction(function () use ($paymentData, $allocations) {
            $allocations = $this->normalizeAllocations($allocations);

            $this->assertAllocationsValid(
                (int) $paymentData['customer_id'],
                (float) $paymentData['amount'],
                $allocations,
                lock: true,
            );

            $payment = CustomerPayment::create(array_merge($paymentData, [
                'created_by' => Auth::id(),
            ]));

            foreach ($allocations as $alloc) {
                $payment->allocations()->create([
                    'customer_invoice_id' => $alloc['customer_invoice_id'],
                    'amount' => $alloc['amount'],
                ]);
            }

            return $payment->load('allocations.invoice', 'customer');
        });
    }

    /**
     * Replace a payment's allocation set wholesale.
     *
     * The payment itself (amount, date, method, till) is immutable — only the
     * matching changes, so the money that was banked stays exactly as recorded.
     * Old allocation rows are hard-deleted: they carry no audit value of their
     * own, the payment is the auditable event and last_matched_at/by is the
     * breadcrumb.
     *
     * @param  array  $allocations  list of ['customer_invoice_id' => int, 'amount' => float]
     *
     * @throws \DomainException
     */
    public function reallocate(CustomerPayment $payment, array $allocations): CustomerPayment
    {
        return DB::transaction(function () use ($payment, $allocations) {
            // Re-read under lock: serialises against a concurrent void and against
            // another reallocation of the same payment. Lock order everywhere is
            // payment row first, then invoice rows ascending by id.
            $payment = CustomerPayment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($payment->isVoid()) {
                throw new \DomainException('A voided payment cannot be re-allocated.');
            }

            $allocations = $this->normalizeAllocations($allocations);

            $this->assertAllocationsValid(
                (int) $payment->customer_id,
                (float) $payment->amount,
                $allocations,
                excludePaymentId: (int) $payment->id,
                lock: true,
            );

            // allAllocations(): clear every row, including any against a voided
            // invoice, or a stale one would survive the replace.
            $payment->allAllocations()->delete();

            foreach ($allocations as $alloc) {
                $payment->allocations()->create([
                    'customer_invoice_id' => $alloc['customer_invoice_id'],
                    'amount' => $alloc['amount'],
                ]);
            }

            $payment->forceFill([
                'last_matched_at' => now(),
                'last_matched_by' => Auth::id(),
            ])->save();

            return $payment->load('allocations.invoice', 'customer');
        });
    }

    public function void(CustomerPayment $payment): CustomerPayment
    {
        if ($payment->isVoid()) {
            return $payment;
        }
        $payment->voided_at = now();
        $payment->voided_by = Auth::id();
        $payment->save();

        return $payment;
    }

    /**
     * Allocate $amount across this customer's outstanding invoices, oldest first.
     * Returns an allocation array suitable for recordPayment() or reallocate().
     *
     * Pass $excludePaymentId when re-allocating an existing payment, so its own
     * current rows don't count against the headroom it is allowed to reuse.
     */
    public function autoAllocate(Customer $customer, float $amount, ?int $excludePaymentId = null): array
    {
        $remaining = round($amount, 2);
        $allocations = [];

        foreach ($this->invoiceRowsFor($customer, $excludePaymentId) as $row) {
            if ($remaining <= self::EPSILON) {
                break;
            }
            $apply = min($remaining, $row['outstanding']);
            $allocations[] = [
                'customer_invoice_id' => $row['id'],
                'amount' => round($apply, 2),
            ];
            $remaining = round($remaining - $apply, 2);
        }

        return $allocations;
    }

    /**
     * The customer's allocatable invoices, oldest first, as rendered by the
     * allocation table and consumed by autoAllocate().
     *
     * When $excludePaymentId is given, `outstanding` is headroom ignoring that
     * payment's own rows and `allocated` carries what it currently applies — so
     * the edit form can show, and re-edit, the invoices that payment already pays
     * rather than seeing them capped at zero or dropped from the list entirely.
     *
     * @return array<int, array{id:int, invoice_number:?string, issue_date:?string, total:float, outstanding:float, allocated:float}>
     */
    public function invoiceRowsFor(Customer $customer, ?int $excludePaymentId = null): array
    {
        $invoices = $customer->invoices()
            ->where('status', '!=', CustomerInvoice::STATUS_VOID)
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get(['id', 'invoice_number', 'issue_date', 'total']);

        if ($invoices->isEmpty()) {
            return [];
        }

        $ids = $invoices->pluck('id')->all();
        $headroom = $this->headroomFor($ids, $excludePaymentId);
        $own = $excludePaymentId ? $this->allocatedByPayment($excludePaymentId, $ids) : [];

        return $invoices
            ->map(fn ($inv) => [
                'id' => (int) $inv->id,
                'invoice_number' => $inv->invoice_number,
                'issue_date' => optional($inv->issue_date)->toDateString(),
                'total' => (float) $inv->total,
                'outstanding' => max(0, $headroom[(int) $inv->id] ?? 0),
                'allocated' => (float) ($own[(int) $inv->id] ?? 0),
            ])
            // Keep zero-headroom invoices only when this payment currently pays them.
            ->filter(fn ($row) => $row['outstanding'] > self::EPSILON || $row['allocated'] > self::EPSILON)
            ->values()
            ->all();
    }

    /**
     * This customer's non-void payments still carrying unapplied credit, oldest
     * first — the order applyCreditToInvoice() consumes them in.
     *
     * Each row is the CustomerPayment with an `unapplied` attribute set.
     *
     * @return \Illuminate\Support\Collection<int, CustomerPayment>
     */
    public function unappliedCreditFor(Customer $customer): Collection
    {
        return $customer->payments()
            ->withSum('allocations as allocated_total', 'amount')
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get()
            ->each(fn ($p) => $p->unapplied = round((float) $p->amount - (float) ($p->allocated_total ?? 0), 2))
            ->filter(fn ($p) => $p->unapplied > self::EPSILON)
            ->values();
    }

    /**
     * Customer id => unapplied credit, in one grouped query for a whole report
     * page rather than per row.
     *
     * @param  array<int, int>|null  $customerIds  null for every customer
     * @return array<int, float>
     */
    public function unappliedCreditTotals(?array $customerIds = null): array
    {
        if ($customerIds === []) {
            return [];
        }

        // Joined to invoices and filtered to live ones so this agrees with the
        // allocations() relation: a voided invoice's row is not allocated money.
        $allocated = DB::table('customer_payment_allocations as a')
            ->join('customer_invoices as i', 'i.id', '=', 'a.customer_invoice_id')
            ->where('i.status', '!=', CustomerInvoice::STATUS_VOID)
            ->selectRaw('a.customer_payment_id as customer_payment_id, SUM(a.amount) as allocated')
            ->groupBy('a.customer_payment_id');

        return DB::table('customer_payments as p')
            ->leftJoinSub($allocated, 'a', 'a.customer_payment_id', '=', 'p.id')
            ->whereNull('p.voided_at')
            ->when($customerIds, fn ($q) => $q->whereIn('p.customer_id', $customerIds))
            ->groupBy('p.customer_id')
            ->selectRaw('p.customer_id, SUM(p.amount - COALESCE(a.allocated, 0)) as credit')
            ->pluck('credit', 'customer_id')
            ->map(fn ($v) => round((float) $v, 2))
            ->filter(fn ($v) => $v > self::EPSILON)
            ->all();
    }

    /**
     * Pull unapplied credit onto $invoice, oldest payment first, capped by the
     * invoice's own headroom.
     *
     * @param  array<int, float>|null  $sources  payment id => amount; null allocates automatically
     * @param  float|null  $max  further cap on the total applied
     * @return array{applied: float, rows: array<int, array{payment_id:int, amount:float}>}
     */
    public function applyCreditToInvoice(CustomerInvoice $invoice, ?array $sources = null, ?float $max = null): array
    {
        return DB::transaction(function () use ($invoice, $sources, $max) {
            if ($invoice->status === CustomerInvoice::STATUS_VOID) {
                throw new \DomainException('A void invoice cannot be paid.');
            }

            // Locks the invoice row before summing its allocations, which is what
            // makes this read-then-write safe against a concurrent allocation.
            $headroom = $this->headroomFor([$invoice->id], null, lock: true)[$invoice->id] ?? 0;
            $remaining = max(0, round($headroom, 2));
            if ($max !== null) {
                $remaining = min($remaining, round($max, 2));
            }

            $candidates = $this->unappliedCreditFor($invoice->customer);
            if ($sources !== null) {
                $candidates = $candidates->filter(fn ($p) => array_key_exists($p->id, $sources))->values();
            }

            $rows = [];
            $applied = 0.0;

            // Ascending id keeps the documented lock order: payment rows after the
            // invoice row, among themselves lowest id first.
            foreach ($candidates->sortBy('id') as $candidate) {
                if ($remaining <= self::EPSILON) {
                    break;
                }

                // Re-read under lock — the payment may have been voided or spent
                // between listing the candidates and getting here.
                $payment = CustomerPayment::whereKey($candidate->id)->lockForUpdate()->first();
                if (! $payment || $payment->isVoid()) {
                    continue;
                }

                $available = round((float) $payment->amount - $payment->total_allocated, 2);
                if ($sources !== null) {
                    $available = min($available, round((float) $sources[$payment->id], 2));
                }
                if ($available <= self::EPSILON) {
                    continue;
                }

                $apply = round(min($remaining, $available), 2);

                // Increment an existing row rather than adding a second one for
                // the same pair, so headroom maths stays one row per pair.
                $existing = $payment->allAllocations()
                    ->where('customer_invoice_id', $invoice->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $existing->amount = round((float) $existing->amount + $apply, 2);
                    $existing->save();
                } else {
                    $payment->allocations()->create([
                        'customer_invoice_id' => $invoice->id,
                        'amount' => $apply,
                    ]);
                }

                $payment->forceFill([
                    'last_matched_at' => now(),
                    'last_matched_by' => Auth::id(),
                ])->save();

                $rows[] = ['payment_id' => (int) $payment->id, 'amount' => $apply];
                $applied = round($applied + $apply, 2);
                $remaining = round($remaining - $apply, 2);
            }

            return ['applied' => $applied, 'rows' => $rows];
        });
    }

    /**
     * Invoice id => amount still allocatable, ignoring rows that belong to
     * $excludePaymentId. Mirrors CustomerInvoice::allocations() by excluding
     * voided payments' rows — which is exactly why headroom must come from this
     * join and not from $invoice->allocations.
     *
     * NOT clamped at 0: a negative value means the invoice is already
     * over-allocated (legacy data) and should be reported, not hidden.
     *
     * Two queries regardless of invoice count.
     *
     * @param  array<int, int>  $invoiceIds
     * @return array<int, float>
     */
    public function headroomFor(array $invoiceIds, ?int $excludePaymentId = null, bool $lock = false): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        $invoiceIds = array_values(array_unique(array_map('intval', $invoiceIds)));
        sort($invoiceIds); // deterministic lock order — see reallocate()

        $invoices = CustomerInvoice::query()
            ->whereIn('id', $invoiceIds)
            ->orderBy('id')
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get(['id', 'total']);

        // Select only the grouped column plus the aggregate so MySQL's
        // ONLY_FULL_GROUP_BY is satisfied.
        $allocated = DB::table('customer_payment_allocations as a')
            ->join('customer_payments as p', 'p.id', '=', 'a.customer_payment_id')
            ->whereIn('a.customer_invoice_id', $invoiceIds)
            ->whereNull('p.voided_at')
            ->when($excludePaymentId, fn ($q) => $q->where('a.customer_payment_id', '!=', $excludePaymentId))
            ->groupBy('a.customer_invoice_id')
            ->selectRaw('a.customer_invoice_id as invoice_id, SUM(a.amount) as allocated')
            ->pluck('allocated', 'invoice_id');

        $out = [];
        foreach ($invoices as $inv) {
            $out[(int) $inv->id] = round((float) $inv->total - (float) ($allocated[$inv->id] ?? 0), 2);
        }

        return $out;
    }

    /**
     * Invoice id => amount this one payment currently applies to it.
     *
     * @param  array<int, int>  $invoiceIds
     * @return array<int, float>
     */
    private function allocatedByPayment(int $paymentId, array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        return DB::table('customer_payment_allocations')
            ->where('customer_payment_id', $paymentId)
            ->whereIn('customer_invoice_id', $invoiceIds)
            ->groupBy('customer_invoice_id')
            ->selectRaw('customer_invoice_id, SUM(amount) as allocated')
            ->pluck('allocated', 'customer_invoice_id')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    /**
     * Drop non-positive rows, round to 2dp, and merge duplicate invoice ids —
     * two rows for the same invoice would each pass a per-row check while
     * jointly busting it.
     *
     * @return array<int, array{customer_invoice_id:int, amount:float}>
     */
    private function normalizeAllocations(array $allocations): array
    {
        $merged = [];

        foreach ($allocations as $alloc) {
            $amount = round((float) ($alloc['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $id = (int) ($alloc['customer_invoice_id'] ?? 0);
            $merged[$id] = round(($merged[$id] ?? 0) + $amount, 2);
        }

        $out = [];
        foreach ($merged as $id => $amount) {
            $out[] = ['customer_invoice_id' => $id, 'amount' => $amount];
        }

        return $out;
    }

    /**
     * The single set of invariants every allocation write path must satisfy.
     *
     * Without the per-invoice headroom check, over-allocation is silently
     * swallowed: CustomerInvoice::outstanding_amount clamps at 0, so the excess
     * vanishes from every balance report and breaks the identity the statement
     * relies on (aged total - unallocated credit == closing balance).
     *
     * @param  array  $allocations  already normalized
     *
     * @throws \DomainException
     */
    private function assertAllocationsValid(
        int $customerId,
        float $paymentAmount,
        array $allocations,
        ?int $excludePaymentId = null,
        bool $lock = false,
    ): void {
        if ($allocations === []) {
            return;
        }

        $allocSum = round(array_sum(array_column($allocations, 'amount')), 2);

        if ($allocSum > $paymentAmount + self::EPSILON) {
            throw new \DomainException(sprintf(
                'Allocations (€%.2f) exceed payment amount (€%.2f).',
                $allocSum,
                $paymentAmount,
            ));
        }

        $ids = array_column($allocations, 'customer_invoice_id');
        $invoices = CustomerInvoice::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            if (! $invoices->has($id)) {
                throw new \DomainException("Invoice #{$id} does not exist.");
            }
        }

        foreach ($invoices as $invoice) {
            if ((int) $invoice->customer_id !== $customerId) {
                throw new \DomainException('All allocated invoices must belong to the same customer as the payment.');
            }
            if ($invoice->status === CustomerInvoice::STATUS_VOID) {
                throw new \DomainException(sprintf(
                    'Invoice %s is void and cannot be paid.',
                    $invoice->invoice_number ?? "#{$invoice->id}",
                ));
            }
        }

        $headroom = $this->headroomFor($ids, $excludePaymentId, $lock);

        foreach ($allocations as $alloc) {
            $id = $alloc['customer_invoice_id'];
            $available = max(0, $headroom[$id] ?? 0);

            if ($alloc['amount'] > $available + self::EPSILON) {
                throw new \DomainException(sprintf(
                    'Cannot apply €%.2f to invoice %s — only €%.2f is outstanding on it.',
                    $alloc['amount'],
                    $invoices[$id]->invoice_number ?? "#{$id}",
                    $available,
                ));
            }
        }
    }
}
