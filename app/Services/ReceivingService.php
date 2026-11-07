<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\LegacyDelivery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The seam between Delivery Dockets (/deliveries, app database) and Receiving
 * (/delivery-legacy and the Shop screens, POS database).
 *
 * A docket is received through one scan session (`deliveriesScan.ID`, kept on
 * `deliveries.scan_session_id`). Sending a docket writes its lines into the
 * POS `delivery` table tagged with that session (`delID`), so several dockets
 * can be in Receiving at once. When Receiving completes the session, the
 * scanned quantities come back onto the docket's lines and the docket becomes
 * `completed`; undo puts it back to `draft`.
 */
class ReceivingService
{
    /**
     * Start a new pending scan session for a supplier and return its id.
     */
    public function createSession(string|int $supplierId): string
    {
        $sessionId = (string) Str::uuid();

        DB::connection('pos')->table('deliveriesScan')->insert([
            'ID' => $sessionId,
            'supID' => (string) $supplierId,
            'dateUpload' => now(),
            'status' => 0,
        ]);

        return $sessionId;
    }

    /**
     * The scan session row, or null.
     */
    public function session(string $sessionId): ?object
    {
        return DB::connection('pos')->table('deliveriesScan')
            ->select('deliveriesScan.ID', 'deliveriesScan.supID', 'deliveriesScan.dateUpload', 'deliveriesScan.status', 'suppliers.Supplier')
            ->leftJoin('suppliers', 'deliveriesScan.supID', '=', 'suppliers.SupplierID')
            ->where('deliveriesScan.ID', $sessionId)
            ->first();
    }

    /**
     * Pending sessions for a supplier that have no docket yet, newest first,
     * each with its scanned item count. These are what a docket can be sent to
     * (besides a new session).
     *
     * @return Collection<int, object>
     */
    public function openSessionsWithoutDocket(int $supplierId): Collection
    {
        $sessions = DB::connection('pos')->table('deliveriesScan')
            ->where('supID', (string) $supplierId)
            ->where('status', 0)
            ->orderByDesc('dateUpload')
            ->get(['ID', 'supID', 'dateUpload', 'status']);

        if ($sessions->isEmpty()) {
            return $sessions;
        }

        $taken = Delivery::whereIn('scan_session_id', $sessions->pluck('ID'))->pluck('scan_session_id')->all();
        $counts = $this->scanCounts($sessions->pluck('ID')->all());

        return $sessions
            ->reject(fn ($session) => in_array($session->ID, $taken, true))
            ->each(fn ($session) => $session->item_count = $counts[$session->ID] ?? 0)
            ->values();
    }

    /**
     * Scanned-row counts per session.
     *
     * @param  array<int, string>  $sessionIds
     * @return array<string, int>
     */
    public function scanCounts(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        return DB::connection('pos')->table('deliveriesScanItems')
            ->select('delID', DB::raw('COUNT(*) as item_count'))
            ->whereIn('delID', $sessionIds)
            ->groupBy('delID')
            ->pluck('item_count', 'delID')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * The docket linked to a session, with its documents, or null.
     */
    public function linkedDocket(string $sessionId): ?Delivery
    {
        return Delivery::with(['documents', 'supplier'])->where('scan_session_id', $sessionId)->first();
    }

    /**
     * Dockets for several sessions, keyed by session id.
     *
     * @param  array<int, string>  $sessionIds
     * @return Collection<string, Delivery>
     */
    public function docketsForSessions(array $sessionIds): Collection
    {
        if ($sessionIds === []) {
            return collect();
        }

        return Delivery::whereIn('scan_session_id', $sessionIds)->get()->keyBy('scan_session_id');
    }

    /**
     * Send a docket's lines to a scan session: the session's previous lines are
     * replaced, lines left on the docket's former session (if it moved) are
     * removed, and the docket remembers the session. Returns the line count.
     *
     * The POS quantity is the DELIVERED amount (invoice "Amount" column), kept
     * as cases when the delivered units divide evenly into full cases and as
     * loose units otherwise; weight-based lines carry their total weight with
     * one "unit" per case. Lines the supplier did not deliver (ordered, zero
     * delivered) go last with myOrder 0 so the match page can list them as OOS.
     *
     * @throws \InvalidArgumentException when the session is missing or completed
     */
    public function sendDocket(Delivery $delivery, string $sessionId): int
    {
        $session = $this->session($sessionId);

        if (! $session) {
            throw new \InvalidArgumentException('That scan session no longer exists.');
        }
        if ((int) $session->status === 1) {
            throw new \InvalidArgumentException('That scan session is already completed. Undo it in Receiving first, or start a new session.');
        }

        $delivery->loadMissing('items');
        $items = $delivery->items;

        if ($items->isEmpty()) {
            throw new \InvalidArgumentException('Cannot send an empty docket to Receiving.');
        }

        $previousSession = $delivery->scan_session_id;

        DB::connection('pos')->transaction(function () use ($items, $sessionId, $previousSession) {
            LegacyDelivery::where('delID', $sessionId)->delete();
            if ($previousSession && $previousSession !== $sessionId) {
                LegacyDelivery::where('delID', $previousSession)->delete();
            }

            $isOutOfStock = fn (DeliveryItem $item) => $item->ordered_quantity > 0 && (int) $item->invoice_delivered_quantity === 0;

            foreach ($items->reject($isOutOfStock) as $item) {
                LegacyDelivery::create($this->legacyRow($item, $sessionId, false));
            }
            foreach ($items->filter($isOutOfStock) as $item) {
                LegacyDelivery::create($this->legacyRow($item, $sessionId, true));
            }
        });

        $delivery->forceFill(['scan_session_id' => $sessionId])->save();

        return $items->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyRow(DeliveryItem $item, string $sessionId, bool $isOutOfStock): array
    {
        if ($item->is_weight_based && $item->total_weight) {
            $caseUnits = 1;
            $myOrder = $item->total_weight;
        } else {
            $unitsPerCase = $item->units_per_case ?? 1;
            $deliveredUnits = (int) ($item->invoice_delivered_quantity ?? 0);

            if ($unitsPerCase > 1 && $deliveredUnits > 0 && $deliveredUnits % $unitsPerCase === 0) {
                $caseUnits = $unitsPerCase;
                $myOrder = intdiv($deliveredUnits, $unitsPerCase);
            } else {
                $caseUnits = 1;
                $myOrder = $deliveredUnits;
            }
        }

        return [
            'delID' => $sessionId,
            'prodName' => $item->description,
            'supCode' => $item->supplier_code,
            'cost' => $item->unit_cost,
            'caseUnits' => $caseUnits,
            'myOrder' => $isOutOfStock ? 0 : $myOrder,
            'rrPrice' => $item->sale_price ?? 0,
            'orderNumber' => $item->order_number,
        ];
    }

    /**
     * Remove a session's invoice lines and detach its docket (the docket itself
     * stays, back in `draft`). Used when a session is deleted.
     */
    public function detachSession(string $sessionId): void
    {
        LegacyDelivery::where('delID', $sessionId)->delete();

        Delivery::where('scan_session_id', $sessionId)->update(['scan_session_id' => null]);
    }

    /**
     * When two sessions merge, the surviving session takes the lines and the
     * docket of the one being removed, unless it already has its own docket.
     * Returns false when both sessions carried a docket (the source's docket is
     * detached rather than merged: one session, one docket).
     */
    public function moveDocket(string $fromSessionId, string $toSessionId): bool
    {
        $target = Delivery::where('scan_session_id', $toSessionId)->exists();

        if ($target) {
            $this->detachSession($fromSessionId);

            return false;
        }

        LegacyDelivery::where('delID', $fromSessionId)->update(['delID' => $toSessionId]);
        Delivery::where('scan_session_id', $fromSessionId)->update(['scan_session_id' => $toSessionId]);

        return true;
    }

    /**
     * After Receiving has added a session's scans to stock: write the scanned
     * units onto the linked docket's lines and mark the docket completed.
     *
     * $matchedItems are DeliveryLegacyController::getMatchedItems() rows, one
     * per invoice supplier code, with `scanned` in units (or kg for weighed
     * goods). A docket can hold several lines for one supplier code (two
     * invoices in one import); the scanned total fills them in order.
     *
     * @param  array<int, object>  $matchedItems
     */
    public function applyCompletion(string $sessionId, array $matchedItems): ?Delivery
    {
        $delivery = Delivery::with('items')->where('scan_session_id', $sessionId)->first();

        if (! $delivery) {
            return null;
        }

        $scannedByCode = [];
        foreach ($matchedItems as $item) {
            $code = (string) ($item->supCode ?? '');
            $scannedByCode[$code] = ($scannedByCode[$code] ?? 0) + (float) ($item->scanned ?? 0);
        }

        DB::transaction(function () use ($delivery, $scannedByCode) {
            $byCode = $delivery->items->groupBy(fn (DeliveryItem $item) => (string) $item->supplier_code);

            foreach ($byCode as $code => $lines) {
                $remaining = $scannedByCode[$code] ?? 0;
                $last = $lines->count() - 1;

                foreach ($lines->values() as $index => $line) {
                    $expected = (float) ($line->invoice_delivered_quantity ?? $line->ordered_quantity ?? 0);
                    $take = $index === $last ? $remaining : min($remaining, $expected);
                    $remaining -= $take;

                    $this->writeReceived($line, $take);
                }
            }

            $delivery->refresh();
            $delivery->forceFill([
                'status' => 'completed',
                'received_at' => now(),
                'total_received' => $delivery->items->sum(
                    fn (DeliveryItem $item) => (float) $item->unit_cost * (float) $item->total_received_units
                ),
            ])->save();
        });

        return $delivery;
    }

    /**
     * Undo of a Receiving completion: the docket goes back to `draft` with
     * nothing received.
     */
    public function revertCompletion(string $sessionId): ?Delivery
    {
        $delivery = Delivery::with('items')->where('scan_session_id', $sessionId)->first();

        if (! $delivery) {
            return null;
        }

        DB::transaction(function () use ($delivery) {
            foreach ($delivery->items as $line) {
                $this->writeReceived($line, 0);
            }

            $delivery->forceFill([
                'status' => 'draft',
                'received_at' => null,
                'total_received' => null,
            ])->save();
        });

        return $delivery;
    }

    /**
     * Store a received amount in units on a docket line, keeping the
     * case/unit split, the legacy `received_quantity` and the status in step.
     */
    private function writeReceived(DeliveryItem $line, float $units): void
    {
        $units = (int) max(0, round($units));

        // Case-type lines keep the legacy `received_quantity` in cases, so split
        // the units; everything else is plain units.
        // Set the legacy column directly: total_received_units falls back to it
        // when the split is all zeros, which would keep an old value alive.
        $caseUnits = $line->getEffectiveCaseUnits();
        if ($line->quantity_type === 'case' && $caseUnits > 1) {
            $line->case_received_quantity = intdiv($units, $caseUnits);
            $line->unit_received_quantity = $units % $caseUnits;
            $line->received_quantity = $line->case_received_quantity;
        } else {
            $line->case_received_quantity = 0;
            $line->unit_received_quantity = $units;
            $line->received_quantity = $units;
        }
        $line->save();
        $line->updateStatus();
    }

    /**
     * Log and swallow: a docket bookkeeping failure must never undo a stock
     * write that has already committed on the POS.
     */
    public function safely(callable $work, string $what): mixed
    {
        try {
            return $work();
        } catch (\Throwable $e) {
            Log::error("Receiving: {$what} failed", ['error' => $e->getMessage()]);

            return null;
        }
    }
}
