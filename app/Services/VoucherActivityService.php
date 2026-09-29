<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\VoucherTillRedemption;
use App\Models\VoucherTransaction;
use Illuminate\Support\Carbon;

/**
 * Everything the voucher activity screen (/vouchers/activity) shows: one
 * newest-first log of voucher events, today's totals, and the health of the
 * till check. Read-only.
 *
 * The log is `voucher_transactions` plus the till redemptions that never made
 * a transaction (no_tender, inactive, unknown, refund). `applied` and
 * `partial` redemptions always have a transaction, so they appear once, as
 * that transaction.
 */
class VoucherActivityService
{
    public function __construct(
        protected VoucherSyncHeartbeat $heartbeat,
    ) {}

    /**
     * @return array{server_now: string, health: array<string, mixed>, totals: array<string, mixed>, events: array<int, array<string, mixed>>}
     */
    public function payload(int $days, ?string $q): array
    {
        return [
            'server_now' => now()->toIso8601String(),
            'health' => $this->health(),
            'totals' => $this->totals(),
            'events' => $this->events($days, $q, (int) config('vouchers.activity.limit')),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function events(int $days, ?string $q, int $limit): array
    {
        $since = $days > 1 ? now()->subDays($days)->startOfDay() : today();
        $q = $q !== null && trim($q) !== '' ? trim($q) : null;
        $withVoucher = ['voucher' => fn ($r) => $r->withTrashed()];

        $transactions = VoucherTransaction::with($withVoucher + ['user', 'tillRedemption'])
            ->where('created_at', '>=', $since)
            ->when($q, fn ($query) => $query->whereHas('voucher', fn ($r) => $r->withTrashed()->where('code', 'like', '%'.$q.'%')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (VoucherTransaction $t) => $this->fromTransaction($t));

        $tillOnly = VoucherTillRedemption::with($withVoucher)
            ->whereNull('voucher_transaction_id')
            ->where('created_at', '>=', $since)
            ->when($q, fn ($query) => $query->where('voucher_code', 'like', '%'.$q.'%'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (VoucherTillRedemption $r) => $this->fromRedemption($r));

        return $transactions->concat($tillOnly)
            ->sortBy([['_ts', 'desc'], ['_order', 'desc']])
            ->take($limit)
            ->map(function (array $e) {
                unset($e['_ts'], $e['_order']);

                return $e;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function fromTransaction(VoucherTransaction $t): array
    {
        $till = $t->source === VoucherTransaction::SOURCE_TILL;
        $redemption = $t->tillRedemption;

        [$kind, $label] = match ($t->type) {
            VoucherTransaction::TYPE_ISSUE => ['issue', 'Issued'],
            VoucherTransaction::TYPE_DEDUCT => $till ? ['redeem_till', 'Redeemed at till'] : ['redeem_manual', 'Redeemed'],
            VoucherTransaction::TYPE_DEACTIVATE => ['deactivate', 'Deactivated'],
            VoucherTransaction::TYPE_ACTIVATE => ['reactivate', 'Reactivated'],
            default => [$t->type, ucfirst($t->type)],
        };

        $shortfall = $redemption ? (float) $redemption->shortfall : 0.0;

        return [
            'key' => 'tx-'.$t->id,
            'at' => $t->created_at?->toIso8601String(),
            'kind' => $kind,
            'label' => $label,
            'code' => $t->voucher?->code,
            'voucher_url' => $this->voucherUrl($t->voucher),
            'amount' => match ($t->type) {
                VoucherTransaction::TYPE_ISSUE => round((float) $t->amount, 2),
                VoucherTransaction::TYPE_DEDUCT => -round((float) $t->amount, 2),
                default => 0.0,
            },
            'balance_after' => round((float) $t->balance_after, 2),
            'who' => $till
                ? 'Till #'.($redemption?->ticket_number ?? '?')
                : ($t->user?->name ?? 'Office'),
            'status' => $till ? $redemption?->status : null,
            'shortfall' => $shortfall > 0 ? $shortfall : null,
            'sold_at' => $till ? $redemption?->sold_at?->toIso8601String() : null,
            'note' => $t->note,
            'reviewed' => $redemption?->status === VoucherTillRedemption::STATUS_PARTIAL
                ? $redemption->reviewed_at !== null
                : null,
            '_ts' => $t->created_at?->getTimestamp() ?? 0,
            '_order' => $t->id * 2,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromRedemption(VoucherTillRedemption $r): array
    {
        return [
            'key' => 'red-'.$r->id,
            'at' => $r->created_at?->toIso8601String(),
            'kind' => 'exception',
            'label' => match ($r->status) {
                VoucherTillRedemption::STATUS_NO_TENDER => 'No voucher tender',
                VoucherTillRedemption::STATUS_INACTIVE => 'Voucher not active',
                VoucherTillRedemption::STATUS_UNKNOWN => 'Unknown voucher',
                VoucherTillRedemption::STATUS_REFUND => 'Refund',
                default => ucfirst(str_replace('_', ' ', $r->status)),
            },
            'code' => $r->voucher?->code ?? $r->voucher_code,
            'voucher_url' => $this->voucherUrl($r->voucher),
            'amount' => 0.0,
            'balance_after' => null,
            'who' => 'Till #'.$r->ticket_number,
            'status' => $r->status,
            'shortfall' => null,
            'sold_at' => $r->sold_at?->toIso8601String(),
            'note' => $r->note,
            'reviewed' => $r->reviewed_at !== null,
            '_ts' => $r->created_at?->getTimestamp() ?? 0,
            '_order' => $r->id * 2 + 1,
        ];
    }

    private function voucherUrl(?Voucher $voucher): ?string
    {
        return $voucher && ! $voucher->trashed() ? route('vouchers.transactions', $voucher) : null;
    }

    /**
     * @return array<string, float|int>
     */
    public function totals(): array
    {
        $today = today();

        $deducts = VoucherTransaction::where('type', VoucherTransaction::TYPE_DEDUCT)
            ->where('created_at', '>=', $today)
            ->selectRaw('source, SUM(amount) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        $till = round((float) ($deducts[VoucherTransaction::SOURCE_TILL] ?? 0), 2);
        $manual = round((float) ($deducts[VoucherTransaction::SOURCE_MANUAL] ?? 0), 2);

        return [
            'redeemed_today' => round($till + $manual, 2),
            'redeemed_today_till' => $till,
            'redeemed_today_manual' => $manual,
            'issued_today' => round((float) VoucherTransaction::where('type', VoucherTransaction::TYPE_ISSUE)
                ->where('created_at', '>=', $today)->sum('amount'), 2),
            'outstanding_balance' => round((float) Voucher::where('status', Voucher::STATUS_ACTIVE)->sum('current_balance'), 2),
            'active_vouchers' => Voucher::where('status', Voucher::STATUS_ACTIVE)->count(),
            'unreviewed_exceptions' => VoucherTillRedemption::exceptions()->unreviewed()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        $beat = $this->heartbeat->read();
        $last = $beat['last'];
        $lastErrors = (int) ($last['counts']['errors'] ?? 0);
        $withoutProduct = Voucher::whereNull('pos_product_id')
            ->whereIn('status', [Voucher::STATUS_ACTIVE, Voucher::STATUS_INACTIVE])
            ->count();

        $messages = [];
        $state = 'ok';

        if ($last !== null && ! $last['ok']) {
            $state = 'error';
            $messages[] = 'The till database could not be read: '.($last['error'] ?? 'unknown error');
        } else {
            if ($beat['scheduler_stale']) {
                $messages[] = ($beat['last_scheduled_at']
                    ? 'The scheduler has not run the till check since '.Carbon::parse($beat['last_scheduled_at'])->format('j M H:i').'.'
                    : 'The scheduler has never run the till check.')
                    .' This page checks the till itself while it is open.';
            }
            if ($lastErrors > 0) {
                $messages[] = "{$lastErrors} till ticket(s) could not be applied on the last check. See the application log.";
            }
            if ($withoutProduct > 0) {
                $messages[] = "{$withoutProduct} voucher(s) have no till product and cannot be scanned at the till. Run vouchers:sync-pos-products.";
            }
            if ($messages) {
                $state = 'warn';
            }
        }

        return [
            'state' => $state,
            'messages' => $messages,
            'last_check_at' => $last['at'] ?? null,
            'last_check_ok' => $last['ok'] ?? null,
            'last_check_source' => $last['source'] ?? null,
            'last_error' => $last['error'] ?? null,
            'last_ok_at' => $beat['last_ok_at'],
            'scheduler_last_at' => $beat['last_scheduled_at'],
            'scheduler_stale' => $beat['scheduler_stale'],
            'last_errors' => $lastErrors,
            'vouchers_without_till_product' => $withoutProduct,
        ];
    }
}
