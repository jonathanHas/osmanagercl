<?php

namespace App\Services;

use App\Mail\CustomerStatementMail;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Support\DocumentBranding;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Builds a customer statement: a chronological invoice/payment ledger with a
 * running balance, plus aged-debt buckets and an open-item list.
 *
 * One context array feeds every surface — screen, print, PDF and email — so the
 * figures can never drift between what a customer sees on paper and in the app.
 */
class CustomerStatementService
{
    /**
     * Aged bucket keys, oldest last. Also the column order on the debtors report.
     */
    public const BUCKETS = ['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus'];

    public const BUCKET_LABELS = [
        'current' => 'Current',
        'd1_30' => '1–30 days',
        'd31_60' => '31–60 days',
        'd61_90' => '61–90 days',
        'd90_plus' => '90+ days',
    ];

    public function __construct(
        private readonly CustomerPaymentService $payments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Customer $customer, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $to = $to ?? Carbon::today();

        // Opening balance is everything strictly before $from. With no $from set,
        // the statement covers all activity from the start, so opening = 0.
        $openingBalance = 0.0;
        if ($from) {
            $openingInvoices = $customer->invoices()
                ->where('status', '!=', CustomerInvoice::STATUS_VOID)
                ->whereDate('issue_date', '<', $from)
                ->sum('total');
            $openingPayments = $customer->payments()
                ->whereDate('payment_date', '<', $from)
                ->sum('amount');
            $openingBalance = round((float) $openingInvoices - (float) $openingPayments, 2);
        }

        // In-range events.
        $invoices = $customer->invoices()
            ->where('status', '!=', CustomerInvoice::STATUS_VOID)
            ->when($from, fn ($q) => $q->whereDate('issue_date', '>=', $from))
            ->whereDate('issue_date', '<=', $to)
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get();

        $payments = $customer->payments()
            ->with('allocations.invoice')
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->whereDate('payment_date', '<=', $to)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        // Merge into a chronological event list.
        $events = collect();
        foreach ($invoices as $inv) {
            $events->push([
                'date' => $inv->issue_date,
                'sort_key' => $inv->issue_date->format('Ymd').'_inv_'.str_pad((string) $inv->id, 8, '0', STR_PAD_LEFT),
                'kind' => 'invoice',
                'description' => 'Invoice '.($inv->invoice_number ?? '(draft)'),
                'reference' => $inv->invoice_number,
                'debit' => (float) $inv->total,
                'credit' => 0.0,
                'invoice' => $inv,
                // Allocation keys carried by every event kind, so no view needs
                // to guard on 'kind' or defend against a missing key.
                'allocations' => [],
                'allocated' => 0.0,
                'unapplied' => 0.0,
                'allocation_summary' => '',
            ]);
        }
        foreach ($payments as $pay) {
            $allocRows = $pay->allocations->map(fn ($a) => [
                'invoice_number' => $a->invoice?->invoice_number ?? '(no invoice)',
                'amount' => round((float) $a->amount, 2),
                'invoice' => $a->invoice,   // for screen links; may be null
            ])->values()->all();

            $allocated = round(array_sum(array_column($allocRows, 'amount')), 2);
            $tillBit = $pay->isTillPayment() && $pay->till_name ? ' via '.$pay->till_name : '';

            $events->push([
                'date' => $pay->payment_date,
                'sort_key' => $pay->payment_date->format('Ymd').'_pay_'.str_pad((string) $pay->id, 8, '0', STR_PAD_LEFT),
                'kind' => 'payment',
                // Description is about the payment; where it went is data below,
                // so each surface can lay the split out as it sees fit.
                'description' => 'Payment — '.$pay->methodLabel().$tillBit,
                'reference' => $pay->reference,
                'debit' => 0.0,
                'credit' => (float) $pay->amount,
                'payment' => $pay,
                'allocations' => $allocRows,
                'allocated' => $allocated,
                // Positive = still on account. Negative means legacy
                // over-allocation, from before assertAllocationsValid() existed.
                'unapplied' => round((float) $pay->amount - $allocated, 2),
                'allocation_summary' => $allocRows === [] ? '' : 'Applied to '.implode(', ', array_map(
                    fn ($r) => $r['invoice_number'].' €'.number_format($r['amount'], 2),
                    $allocRows,
                )),
            ]);
        }
        $events = $events->sortBy('sort_key')->values()->all(); // plain array so we can mutate by index

        // Running balance.
        $balance = $openingBalance;
        $rangeInvoiced = 0.0;
        $rangePaid = 0.0;
        foreach ($events as $i => $e) {
            $balance = round($balance + $e['debit'] - $e['credit'], 2);
            $events[$i]['balance'] = $balance;
            $rangeInvoiced += $e['debit'];
            $rangePaid += $e['credit'];
        }

        $open = $this->openInvoices($customer, $to);

        // Which payment paid what, for the open-item rows. Loaded here rather
        // than in openInvoices() because debtors() calls that once per customer
        // and doesn't need it — one extra query for a statement, none for the
        // report.
        (new EloquentCollection(array_column($open, 'invoice')))->loadMissing('allocations.payment');

        foreach ($open as $i => $row) {
            $open[$i]['payments'] = $row['invoice']->allocations
                ->map(fn ($a) => [
                    'date' => $a->payment?->payment_date,
                    'amount' => round((float) $a->amount, 2),
                    'reference' => $a->payment?->reference,
                    'payment' => $a->payment,
                ])
                ->sortBy('date')
                ->values()
                ->all();
        }

        // Payments that aren't applied to a specific invoice don't reduce any
        // invoice's outstanding figure, so without this the open-item total
        // would overstate the debt and disagree with the closing balance.
        // By construction: aged total - unallocated credit == closing balance.
        $creditCandidates = $customer->payments()
            ->whereDate('payment_date', '<=', $to)
            ->with('allocations')
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        // Summed over every payment, including any legacy over-allocated
        // (negative) one, so the identity above holds on old data too. The rows
        // listed for the statement are the positive ones.
        $unallocatedCredit = round($creditCandidates->sum(fn ($p) => $p->unallocated_amount), 2);

        // Listed separately from the ledger because unallocated credit spans all
        // payments up to $to, while the ledger only covers [$from, $to] — on a
        // ranged statement the credit would otherwise be a figure with no
        // supporting row anywhere on the document.
        $creditPayments = $creditCandidates
            ->filter(fn ($p) => $p->unallocated_amount > 0.005)
            ->map(fn ($p) => [
                'payment' => $p,
                'date' => $p->payment_date,
                'method' => $p->methodLabel(),
                'reference' => $p->reference,
                'amount' => (float) $p->amount,
                'unapplied' => $p->unallocated_amount,
            ])
            ->values()
            ->all();

        return array_merge(DocumentBranding::context(), [
            'customer' => $customer,
            'events' => $events,
            'opening_balance' => $openingBalance,
            'closing_balance' => $balance,
            'range_invoiced' => round($rangeInvoiced, 2),
            'range_paid' => round($rangePaid, 2),
            'from' => $from,
            'to' => $to,
            'open_invoices' => $open,
            'aging' => $this->agingFromOpenInvoices($open),
            'unallocated_credit' => $unallocatedCredit,
            'credit_payments' => $creditPayments,
            'bucket_labels' => self::BUCKET_LABELS,
        ]);
    }

    /**
     * Unpaid/part-paid non-void invoices issued on or before $asOf, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function openInvoices(Customer $customer, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? Carbon::today();

        // outstanding_amount and total_paid sum loaded collections, so eager
        // load allocations here or every row costs an extra query.
        $invoices = $customer->invoices()
            ->where('status', '!=', CustomerInvoice::STATUS_VOID)
            ->whereDate('issue_date', '<=', $asOf)
            ->with('allocations')
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($invoices as $inv) {
            $outstanding = $inv->outstanding_amount;
            if ($outstanding <= 0.005) {
                continue;
            }
            $inv->setRelation('customer', $customer); // avoid re-querying in effective_due_date
            $rows[] = [
                'invoice' => $inv,
                'invoice_number' => $inv->invoice_number ?? '(draft)',
                'issue_date' => $inv->issue_date,
                'due_date' => $inv->effective_due_date,
                'due_date_is_derived' => $inv->due_date === null,
                'total' => (float) $inv->total,
                'paid' => $inv->total_paid,
                'outstanding' => $outstanding,
                'days_overdue' => $inv->daysOverdue($asOf),
                // Only ever 'unpaid' or 'partial' here — outstanding_amount
                // clamps at 0 and the loop above skips settled invoices.
                // Rendered as the "Part-paid" tag on the statement screen.
                'status' => $inv->paymentStatus(),
            ];
        }

        return $rows;
    }

    /**
     * Bucket open invoices by how overdue they are.
     *
     * @param  array<int, array<string, mixed>>  $openInvoices
     * @return array<string, float>
     */
    public function agingFromOpenInvoices(array $openInvoices): array
    {
        $buckets = array_fill_keys(self::BUCKETS, 0.0);

        foreach ($openInvoices as $row) {
            $buckets[$this->bucketFor($row['days_overdue'])] += $row['outstanding'];
        }

        foreach ($buckets as $k => $v) {
            $buckets[$k] = round($v, 2);
        }
        $buckets['total'] = round(array_sum($buckets), 2);

        return $buckets;
    }

    public function bucketFor(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 0 => 'current',
            $daysOverdue <= 30 => 'd1_30',
            $daysOverdue <= 60 => 'd31_60',
            $daysOverdue <= 90 => 'd61_90',
            default => 'd90_plus',
        };
    }

    /**
     * Every customer carrying a balance, with aged buckets.
     *
     * The balance itself comes from two withSum aggregates rather than the
     * Customer::$balance accessor — the accessor fires two queries per customer,
     * which is what made the old outstanding-balances widget O(n) in queries.
     *
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function debtors(?Carbon $asOf = null, bool $includeCredits = false): array
    {
        $asOf = $asOf ?? Carbon::today();

        $customers = Customer::query()
            ->withSum(
                ['invoices as invoiced_total' => fn ($q) => $q->where('status', '!=', CustomerInvoice::STATUS_VOID)],
                'total'
            )
            ->withSum('payments as paid_total', 'amount')
            ->orderBy('name')
            ->get();

        // One grouped query for the whole page rather than per row. This is also
        // what explains a row whose aged total exceeds its balance: money received
        // but not yet matched to any invoice.
        $credits = $this->payments->unappliedCreditTotals($customers->pluck('id')->all());

        $rows = [];
        $totals = array_fill_keys(self::BUCKETS, 0.0);
        $totals['total'] = 0.0;
        $totals['balance'] = 0.0;
        $totals['credit'] = 0.0;

        foreach ($customers as $customer) {
            $balance = round((float) ($customer->invoiced_total ?? 0) - (float) ($customer->paid_total ?? 0), 2);

            if ($balance <= 0.005 && ! ($includeCredits && $balance < -0.005)) {
                continue;
            }

            $open = $this->openInvoices($customer, $asOf);
            $aging = $this->agingFromOpenInvoices($open);

            $credit = round((float) ($credits[$customer->id] ?? 0), 2);

            $rows[] = [
                'customer' => $customer,
                'balance' => $balance,
                'aging' => $aging,
                'unapplied_credit' => $credit,
                'open_count' => count($open),
                'oldest_days' => collect($open)->max('days_overdue') ?? 0,
            ];

            foreach (self::BUCKETS as $bucket) {
                $totals[$bucket] += $aging[$bucket];
            }
            $totals['total'] += $aging['total'];
            $totals['balance'] += $balance;
            $totals['credit'] += $credit;
        }

        usort($rows, fn ($a, $b) => $b['balance'] <=> $a['balance']);

        foreach ($totals as $k => $v) {
            $totals[$k] = round($v, 2);
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Email a statement to every opted-in customer carrying a balance.
     *
     * Single code path shared by the artisan command, the scheduled monthly run
     * and the debtors-page button.
     *
     * @return array{sent: int, skipped: int, failed: int, details: array<int, string>}
     */
    public function sendStatements(?Carbon $asOf = null, bool $dryRun = false, ?int $onlyCustomerId = null): array
    {
        $asOf = $asOf ?? Carbon::today();

        $query = Customer::query()->receivesStatements();
        if ($onlyCustomerId) {
            $query->where('id', $onlyCustomerId);
        }

        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'details' => []];

        foreach ($query->orderBy('name')->cursor() as $customer) {
            $ctx = $this->build($customer, null, $asOf);

            if ($ctx['closing_balance'] <= 0.005) {
                $result['skipped']++;
                $result['details'][] = "skipped {$customer->name}: nothing outstanding";

                continue;
            }

            if ($dryRun) {
                $result['sent']++;
                $result['details'][] = sprintf(
                    'would email %s <%s>: EUR %s outstanding',
                    $customer->name,
                    $customer->email,
                    number_format($ctx['closing_balance'], 2)
                );

                continue;
            }

            try {
                Mail::to($customer->email)->queue(new CustomerStatementMail($ctx));
                $customer->forceFill(['statement_last_sent_at' => now()])->save();
                $result['sent']++;
                $result['details'][] = sprintf(
                    'queued %s <%s>: EUR %s',
                    $customer->name,
                    $customer->email,
                    number_format($ctx['closing_balance'], 2)
                );
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['details'][] = "failed {$customer->name}: ".$e->getMessage();
                Log::error('Customer statement send failed', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Filename used for both the PDF download and the email attachment.
     */
    public function filename(Customer $customer, Carbon $to): string
    {
        return sprintf(
            'statement-%s-%s.pdf',
            preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($customer->name)),
            $to->format('Ymd'),
        );
    }
}
