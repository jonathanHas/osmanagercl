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
 * Vouchers cycle 3: a voucher line that carries a price is the voucher being
 * *sold* (its product is priced at the face value while it is for sale). That
 * sale activates the voucher (`activated`), or, when anything is unusual,
 * activates nothing and is flagged (`sale_flagged`, `refund`). See applySale().
 *
 * Pattern follows KdsRealtimeController::checkNewOrders(): a watermark from the
 * local table's latest sold_at (with an overlap so a late-committed ticket is
 * not missed), a capped look-back, and a per-row existence check backed by a
 * unique index.
 */
class VoucherTillSyncService
{
    // `errors` counts tickets that failed inside the loop (logged, retried next run).
    private const COUNT_KEYS = ['tickets', 'applied', 'partial', 'no_tender', 'inactive', 'unknown', 'refund', 'activated', 'sale_flagged', 'skipped', 'errors'];

    public function __construct(
        protected VoucherPosProductService $posProducts,
        protected VoucherSyncHeartbeat $heartbeat,
    ) {}

    /**
     * Run the sync from the lookup screens, at most once per throttle window.
     * Never throws: a POS outage must not break a voucher lookup.
     */
    public function syncIfDue(string $source = VoucherSyncHeartbeat::SOURCE_LOOKUP): ?array
    {
        if (! config('vouchers.sync.on_lookup')) {
            return null;
        }

        if (! Cache::add('vouchers:till-sync', 1, (int) config('vouchers.sync.lookup_throttle_seconds'))) {
            return null;
        }

        try {
            return $this->sync(null, $source);
        } catch (\Throwable $e) {
            Log::warning('Voucher till sync on lookup failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Run the till check and record the heartbeat (VoucherSyncHeartbeat). A run
     * with per-ticket `errors` still counts as ok: the till was read. A failure
     * before the ticket loop (till database unreachable) is recorded and rethrown.
     *
     * @return array<string, int>
     */
    public function sync(?Carbon $since = null, string $source = VoucherSyncHeartbeat::SOURCE_COMMAND): array
    {
        try {
            $counts = $this->run($since);
        } catch (\Throwable $e) {
            $this->heartbeat->record($source, false, null, $e->getMessage());

            throw $e;
        }

        $this->heartbeat->record($source, true, $counts);

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function run(?Carbon $since): array
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
                $counts['errors']++;
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

        $groups = $this->voucherGroups($pos, $ticket->ID, $categoryId);

        if ($groups === []) {
            return;
        }

        $tender = round((float) $pos->table('PAYMENTS')
            ->where('RECEIPT', $ticket->ID)
            ->where('PAYMENT', 'paperin')
            ->sum('TOTAL'), 2);

        // Owner decision 4 (2026-09-29): a voucher sale paid with the Free tender activates nothing.
        $paidFree = $pos->table('PAYMENTS')
            ->where('RECEIPT', $ticket->ID)
            ->where('PAYMENT', 'free')
            ->exists();

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

        // `isLast` (which decides `partial`) is counted over redemption lines only,
        // so a voucher sold on the same ticket can neither take nor create a shortfall.
        $redemptionProducts = array_keys(array_filter($groups, fn ($g) => ! $g->isSale));
        $lastRedemption = end($redemptionProducts);

        foreach ($groups as $line) {
            $existing = VoucherTillRedemption::where('pos_ticket_id', $ticket->ID)
                ->where('pos_product_id', $line->PRODUCT)
                ->first();

            if ($existing) {
                // Already recorded (overlap window, or a retry after a partial failure):
                // its deduction still used up part of the shared tender (0 for a sale).
                $pool = round($pool - (float) $existing->amount_deducted, 2);
                $counts['skipped']++;

                continue;
            }

            $voucher = $this->resolveVoucher($line);

            try {
                [$status, $touched, $voucherId] = DB::transaction(
                    function () use ($base, $line, $voucher, &$pool, $lastRedemption, $ticket, $paidFree) {
                        return $line->isSale
                            ? $this->applySale($base, $line, $voucher, $ticket, $paidFree)
                            : $this->applyLine($base, $line, $voucher, $pool, $line->PRODUCT === $lastRedemption, $ticket);
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

            if ($touched > 0 && $voucherId) {
                $changed[$voucherId] = true;
            }
        }

        // Rename (and reprice) each deducted or activated voucher's POS product.
        foreach (array_keys($changed) as $voucherId) {
            if ($voucher = Voucher::find($voucherId)) {
                $this->posProducts->sync($voucher);
            }
        }
    }

    /**
     * The ticket's voucher-category lines, one group per product in order of its
     * first LINE: a double scan collapses to one group, with `units` summed and
     * `sale_total` the charged amount incl. tax. A group with a charge is a sale
     * (the voucher sold as an item); a €0.00 group is a redemption.
     *
     * @return array<string, object>
     */
    private function voucherGroups($pos, string $ticketId, string $categoryId): array
    {
        $lines = $pos->table('TICKETLINES as tl')
            ->join('PRODUCTS as p', 'tl.PRODUCT', '=', 'p.ID')
            ->leftJoin('TAXES as tx', 'tx.ID', '=', 'tl.TAXID')
            ->where('tl.TICKET', $ticketId)
            ->where('p.CATEGORY', $categoryId)
            ->orderBy('tl.LINE')
            ->select('tl.LINE', 'tl.PRODUCT', 'tl.UNITS', 'tl.PRICE', 'p.CODE', 'tx.RATE')
            ->get();

        $groups = [];
        foreach ($lines as $l) {
            $g = $groups[$l->PRODUCT] ??= (object) [
                'LINE' => $l->LINE,
                'PRODUCT' => $l->PRODUCT,
                'CODE' => $l->CODE,
                'units' => 0.0,
                'sale_total' => 0.0,
            ];
            $g->units += (float) $l->UNITS;
            $g->sale_total += (float) $l->UNITS * (float) $l->PRICE * (1 + (float) ($l->RATE ?? 0));
        }

        foreach ($groups as $g) {
            $g->sale_total = round($g->sale_total, 2);
            $g->isSale = abs($g->sale_total) > 0.005;
        }

        return $groups;
    }

    /**
     * Find the voucher behind a POS product, repairing a missing product link.
     */
    private function resolveVoucher(object $line): ?Voucher
    {
        // withTrashed(): a deleted voucher's label still scans; applyLine()/applySale()
        // then refuse it explicitly rather than treating it as unknown.
        $voucher = Voucher::withTrashed()->where('pos_product_id', $line->PRODUCT)->first();

        if ($voucher) {
            return $voucher;
        }

        $voucher = $line->CODE ? Voucher::withTrashed()->where('code', $line->CODE)->first() : null;

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
        $locked = Voucher::withTrashed()->whereKey($voucher->id)->lockForUpdate()->first();

        if ($locked->trashed()) {
            VoucherTillRedemption::create($row + [
                'status' => VoucherTillRedemption::STATUS_INACTIVE,
                'note' => 'Voucher was deleted.',
            ]);

            return [VoucherTillRedemption::STATUS_INACTIVE, 0.0, $locked->id];
        }
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

    /**
     * A voucher sold as an item on this ticket (its line carries a price). The
     * sale activates the voucher with its face value when everything matches;
     * anything unusual activates nothing and is flagged for a manager (owner
     * decisions 1-4, 2026-09-29). Runs inside the caller's transaction; the
     * redemption row is inserted before the voucher changes.
     *
     * @param  array<string, mixed>  $base
     * @return array{0: string, 1: float, 2: ?int} status, amount activated, voucher id
     */
    private function applySale(array $base, object $line, ?Voucher $voucher, object $ticket, bool $paidFree): array
    {
        $charged = round(abs($line->sale_total), 2);
        $chargedText = '€'.number_format($charged, 2);

        $row = $base + [
            'pos_product_id' => $line->PRODUCT,
            'voucher_code' => $line->CODE !== null ? substr((string) $line->CODE, 0, 20) : null,
            'voucher_id' => $voucher?->id,
            'sale_amount' => $charged,
            'amount_deducted' => 0,
            'shortfall' => 0,
        ];

        $flag = function (string $status, string $note) use ($row, $voucher): array {
            VoucherTillRedemption::create($row + ['status' => $status, 'note' => $note]);

            return [$status, 0.0, $voucher?->id];
        };

        if ((int) $ticket->TICKETTYPE === 1) {
            return $flag(VoucherTillRedemption::STATUS_REFUND,
                "Refund of a voucher sale ({$chargedText}). Deactivate the voucher if it was handed back.");
        }

        if (! $voucher) {
            return $flag(VoucherTillRedemption::STATUS_UNKNOWN, 'No voucher with this code in the app.');
        }

        $locked = Voucher::withTrashed()->whereKey($voucher->id)->lockForUpdate()->first();
        $faceValue = round((float) $locked->face_value, 2);

        if ($locked->trashed()) {
            return $flag(VoucherTillRedemption::STATUS_SALE_FLAGGED,
                "Charged {$chargedText} but the voucher was deleted. Nothing was activated.");
        }

        if ($locked->status !== Voucher::STATUS_INACTIVE) {
            return $flag(VoucherTillRedemption::STATUS_SALE_FLAGGED,
                "Charged {$chargedText} but the voucher was already {$locked->status} (balance €"
                .number_format((float) $locked->current_balance, 2).'). Nothing was added.');
        }

        if ($paidFree) {
            return $flag(VoucherTillRedemption::STATUS_SALE_FLAGGED, 'Paid with the Free tender. Not activated.');
        }

        if (abs($line->units - 1) > 0.0001) {
            $units = rtrim(rtrim(number_format($line->units, 3, '.', ''), '0'), '.');

            return $flag(VoucherTillRedemption::STATUS_SALE_FLAGGED,
                "Quantity {$units} on one voucher (charged {$chargedText}). Not activated: each voucher is scanned itself.");
        }

        if ($faceValue <= 0 || abs($faceValue - $charged) > 0.005) {
            return $flag(VoucherTillRedemption::STATUS_SALE_FLAGGED,
                "Charged {$chargedText} but the voucher's value is €".number_format($faceValue, 2).'. Not activated.');
        }

        $redemption = VoucherTillRedemption::create($row + ['status' => VoucherTillRedemption::STATUS_ACTIVATED]);

        $locked->initial_value = $faceValue;
        $locked->current_balance = $faceValue;
        $locked->status = Voucher::STATUS_ACTIVE;
        $locked->save();

        $transaction = $locked->transactions()->create([
            'type' => VoucherTransaction::TYPE_ISSUE,
            'source' => VoucherTransaction::SOURCE_TILL,
            'amount' => $faceValue,
            'balance_after' => $faceValue,
            'note' => 'Till #'.$base['ticket_number'],
            'user_id' => null,
        ]);

        $redemption->update(['voucher_transaction_id' => $transaction->id]);

        return [VoucherTillRedemption::STATUS_ACTIVATED, $faceValue, $locked->id];
    }
}
