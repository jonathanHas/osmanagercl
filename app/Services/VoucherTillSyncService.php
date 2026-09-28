<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\VoucherTillRedemption;
use App\Models\VoucherTransaction;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies gift vouchers redeemed at the uniCenta till.
 *
 * The till's Voucher tender (PAYMENTS.PAYMENT = 'paperin') says how much was
 * paid by voucher but not which voucher: TRANSID is a random id the till makes
 * for every payment. The voucher is identified by its hidden product line
 * (see VoucherPosProductService), so this reads new tickets carrying a product
 * in the voucher category and deducts the receipt's summed paperin tender.
 *
 * Owner decisions (2026-09-27): deduct = sum of paperin on the receipt; tender
 * above the balance deducts to zero and is flagged `partial`; a voucher line
 * with no voucher tender deducts nothing and is flagged `no_tender`; refunds
 * are recorded, never reversed.
 *
 * Pattern follows KdsRealtimeController::checkNewOrders(): a watermark from the
 * local table's latest sold_at (with an overlap so a late-committed ticket is
 * not missed), a capped look-back, and a per-row existence check backed by a
 * unique index.
 */
class VoucherTillSyncService
{
    private const COUNT_KEYS = ['tickets', 'applied', 'partial', 'no_tender', 'inactive', 'unknown', 'refund', 'skipped'];

    public function __construct(
        protected VoucherPosProductService $posProducts,
    ) {}

    /**
     * Run the sync from the lookup screens, at most once per throttle window.
     * Never throws: a POS outage must not break a voucher lookup.
     */
    public function syncIfDue(): ?array
    {
        if (! config('vouchers.sync.on_lookup')) {
            return null;
        }

        if (! Cache::add('vouchers:till-sync', 1, (int) config('vouchers.sync.lookup_throttle_seconds'))) {
            return null;
        }

        try {
            return $this->sync();
        } catch (\Throwable $e) {
            Log::warning('Voucher till sync on lookup failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, int>
     */
    public function sync(?Carbon $since = null): array
    {
        $counts = array_fill_keys(self::COUNT_KEYS, 0);
        $since ??= $this->watermark();
        $categoryId = $this->posProducts->categoryId();

        $tickets = DB::connection('pos')
            ->table('TICKETS as t')
            ->join('RECEIPTS as r', 't.ID', '=', 'r.ID')
            ->join('TICKETLINES as tl', 't.ID', '=', 'tl.TICKET')
            ->join('PRODUCTS as p', 'tl.PRODUCT', '=', 'p.ID')
            ->where('r.DATENEW', '>', $since->format('Y-m-d H:i:s'))
            ->where('p.CATEGORY', $categoryId)
            ->whereIn('t.TICKETTYPE', [0, 1])
            ->select('t.ID', 't.TICKETID', 't.TICKETTYPE', 'r.DATENEW')
            ->distinct()
            ->orderBy('r.DATENEW')
            ->orderBy('t.TICKETID')
            ->limit((int) config('vouchers.sync.batch'))
            ->get();

        foreach ($tickets as $ticket) {
            $counts['tickets']++;

            try {
                $this->syncTicket($ticket, $categoryId, $counts);
            } catch (\Throwable $e) {
                // Left unrecorded, so the next run (inside the overlap window) retries it.
                Log::error('Voucher till sync failed for ticket', [
                    'ticket_id' => $ticket->ID,
                    'ticket_number' => $ticket->TICKETID,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $counts;
    }

    /**
     * Latest recorded sale minus the overlap, but never further back than the look-back.
     */
    private function watermark(): Carbon
    {
        $floor = now()->subHours((int) config('vouchers.sync.lookback_hours'));
        $latest = VoucherTillRedemption::max('sold_at');

        if ($latest === null) {
            return $floor;
        }

        $since = Carbon::parse($latest)->subMinutes((int) config('vouchers.sync.overlap_minutes'));

        return $since->lt($floor) ? $floor : $since;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function syncTicket(object $ticket, string $categoryId, array &$counts): void
    {
        $pos = DB::connection('pos');

        // A double scan of one voucher collapses to its first line; UNITS is ignored.
        $lines = $pos->table('TICKETLINES as tl')
            ->join('PRODUCTS as p', 'tl.PRODUCT', '=', 'p.ID')
            ->where('tl.TICKET', $ticket->ID)
            ->where('p.CATEGORY', $categoryId)
            ->orderBy('tl.LINE')
            ->select('tl.LINE', 'tl.PRODUCT', 'p.CODE')
            ->get()
            ->unique('PRODUCT')
            ->values();

        if ($lines->isEmpty()) {
            return;
        }

        $tender = round((float) $pos->table('PAYMENTS')
            ->where('RECEIPT', $ticket->ID)
            ->where('PAYMENT', 'paperin')
            ->sum('TOTAL'), 2);

        // Same formula as TillTransactionRepository::formatReceipt().
        $ticketTotal = round((float) $pos->table('TICKETLINES as tl')
            ->join('TAXES as tx', 'tx.ID', '=', 'tl.TAXID')
            ->where('tl.TICKET', $ticket->ID)
            ->selectRaw('SUM(tl.PRICE * tl.UNITS * (1 + tx.RATE)) as total')
            ->value('total'), 2);

        $base = [
            'pos_ticket_id' => $ticket->ID,
            'ticket_number' => (int) $ticket->TICKETID,
            'ticket_type' => (int) $ticket->TICKETTYPE,
            'sold_at' => Carbon::parse($ticket->DATENEW),
            'voucher_tender' => $tender,
            'ticket_total' => $ticketTotal,
        ];

        $pool = $tender;
        $changed = [];
        $lastIndex = $lines->count() - 1;

        foreach ($lines as $index => $line) {
            $existing = VoucherTillRedemption::where('pos_ticket_id', $ticket->ID)
                ->where('pos_product_id', $line->PRODUCT)
                ->first();

            if ($existing) {
                // Already recorded (overlap window, or a retry after a partial failure):
                // its deduction still used up part of the shared tender.
                $pool = round($pool - (float) $existing->amount_deducted, 2);
                $counts['skipped']++;

                continue;
            }

            $voucher = $this->resolveVoucher($line);

            try {
                [$status, $deducted, $voucherId] = DB::transaction(
                    function () use ($base, $line, $voucher, &$pool, $index, $lastIndex, $ticket) {
                        return $this->applyLine($base, $line, $voucher, $pool, $index === $lastIndex, $ticket);
                    }
                );
            } catch (QueryException $e) {
                if (($e->errorInfo[0] ?? null) === '23000') {
                    // Another run recorded this line first; the unique index kept it single.
                    $counts['skipped']++;

                    continue;
                }

                throw $e;
            }

            $counts[$status]++;

            if ($deducted > 0 && $voucherId) {
                $changed[$voucherId] = true;
            }
        }

        // Rename each touched voucher's POS product to its new balance.
        foreach (array_keys($changed) as $voucherId) {
            if ($voucher = Voucher::find($voucherId)) {
                $this->posProducts->sync($voucher);
            }
        }
    }

    /**
     * Find the voucher behind a POS product, repairing a missing product link.
     */
    private function resolveVoucher(object $line): ?Voucher
    {
        $voucher = Voucher::where('pos_product_id', $line->PRODUCT)->first();

        if ($voucher) {
            return $voucher;
        }

        $voucher = $line->CODE ? Voucher::where('code', $line->CODE)->first() : null;

        if ($voucher && $voucher->pos_product_id !== $line->PRODUCT) {
            $voucher->forceFill(['pos_product_id' => $line->PRODUCT])->saveQuietly();
        }

        return $voucher;
    }

    /**
     * Record one voucher line and, when due, deduct from the voucher. Runs inside
     * a transaction on the default connection; the redemption row is inserted
     * first so the unique index stops a concurrent run before any money moves.
     *
     * @param  array<string, mixed>  $base
     * @return array{0: string, 1: float, 2: ?int} status, amount deducted, voucher id
     */
    private function applyLine(array $base, object $line, ?Voucher $voucher, float &$pool, bool $isLast, object $ticket): array
    {
        $row = $base + [
            'pos_product_id' => $line->PRODUCT,
            'voucher_code' => $line->CODE !== null ? substr((string) $line->CODE, 0, 20) : null,
            'voucher_id' => $voucher?->id,
        ];

        if ((int) $ticket->TICKETTYPE === 1) {
            VoucherTillRedemption::create($row + ['status' => VoucherTillRedemption::STATUS_REFUND]);

            return [VoucherTillRedemption::STATUS_REFUND, 0.0, $voucher?->id];
        }

        if (! $voucher) {
            VoucherTillRedemption::create($row + [
                'status' => VoucherTillRedemption::STATUS_UNKNOWN,
                'note' => 'No voucher with this code in the app.',
            ]);

            return [VoucherTillRedemption::STATUS_UNKNOWN, 0.0, null];
        }

        // Re-read the balance under the lock.
        $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->first();
        $balance = round((float) $locked->current_balance, 2);

        if ($locked->status !== Voucher::STATUS_ACTIVE || $balance <= 0) {
            VoucherTillRedemption::create($row + [
                'status' => VoucherTillRedemption::STATUS_INACTIVE,
                'note' => 'Voucher was '.$locked->status.' (balance €'.number_format($balance, 2).').',
            ]);

            return [VoucherTillRedemption::STATUS_INACTIVE, 0.0, $locked->id];
        }

        if ($pool <= 0) {
            VoucherTillRedemption::create($row + [
                'status' => VoucherTillRedemption::STATUS_NO_TENDER,
                'note' => 'Voucher scanned but not paid with the Voucher tender.',
            ]);

            return [VoucherTillRedemption::STATUS_NO_TENDER, 0.0, $locked->id];
        }

        $deduct = round(min($balance, $pool), 2);
        $pool = round($pool - $deduct, 2);
        $shortfall = $isLast && $pool > 0 ? $pool : 0.0;
        $status = $shortfall > 0 ? VoucherTillRedemption::STATUS_PARTIAL : VoucherTillRedemption::STATUS_APPLIED;

        $redemption = VoucherTillRedemption::create($row + [
            'status' => $status,
            'amount_deducted' => $deduct,
            'shortfall' => $shortfall,
        ]);

        $locked->current_balance = round($balance - $deduct, 2);
        if ((float) $locked->current_balance <= 0) {
            $locked->status = Voucher::STATUS_EXHAUSTED;
        }
        $locked->save();

        $note = 'Till #'.$base['ticket_number'];
        if ($shortfall > 0) {
            $note .= ' · short €'.number_format($shortfall, 2);
        }

        $transaction = $locked->transactions()->create([
            'type' => VoucherTransaction::TYPE_DEDUCT,
            'source' => VoucherTransaction::SOURCE_TILL,
            'amount' => $deduct,
            'balance_after' => $locked->current_balance,
            'note' => $note,
            'user_id' => null,
        ]);

        $redemption->update(['voucher_transaction_id' => $transaction->id]);

        return [$status, $deduct, $locked->id];
    }
}
