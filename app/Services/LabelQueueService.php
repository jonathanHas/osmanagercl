<?php

namespace App\Services;

use App\Models\LabelLog;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Which products need a shelf label printed, and how many of each kind.
 *
 * The queue is a derivation, not a table: a product needs a label when its most
 * recent queue event (new product / price update / re-queue / barcode change) in
 * the last 30 days is newer than its most recent print or dismissal in the same
 * window. A barcode change queues a label because the label carries the barcode.
 *
 * Extracted from LabelAreaController in cycle 10 so the Shop mode screen and the
 * office page read the queue through one implementation. The controller keeps
 * private wrappers, so every existing caller is unchanged.
 *
 * The derivation used to issue one print lookup per candidate barcode — about
 * 10 ms each against production-sized data, and real 30-day windows hold 180 to
 * 1,430 distinct barcodes, so the Home badge alone could have cost seconds.
 * candidates() now does the whole thing in two queries regardless of size.
 */
class LabelQueueService
{
    private const WINDOW_DAYS = 30;

    /**
     * How many barcodes to bind per whereIn, so a busy month cannot exceed a
     * driver's placeholder limit.
     */
    private const CHUNK = 1000;

    /**
     * Products needing labels, as POS Product models with the queue event that
     * put them there attached.
     *
     * @param  array<int, string>  $filters  event types to keep; empty means all
     */
    public function needingLabels(array $filters = [])
    {
        $candidates = $this->candidates();

        if (! empty($filters)) {
            $candidates = $candidates->whereIn('event_type', $filters)->values();
        }

        return Product::whereIn('CODE', $candidates->pluck('barcode'))
            ->orderBy('NAME')
            ->get()
            ->map(function ($product) use ($candidates) {
                $eventData = $candidates->firstWhere('barcode', $product->CODE);
                $product->label_event_type = $eventData['event_type'];
                $product->label_event_date = $eventData['created_at'];

                return $product;
            });
    }

    /**
     * Counts of products needing labels by event type, plus a total.
     *
     * @return array<string, int>
     */
    public function countsByEventType(): array
    {
        $counts = [
            LabelLog::EVENT_NEW_PRODUCT => 0,
            LabelLog::EVENT_PRICE_UPDATE => 0,
            LabelLog::EVENT_REQUEUE_LABEL => 0,
            LabelLog::EVENT_BARCODE_CHANGE => 0,
        ];

        foreach ($this->candidates() as $candidate) {
            if (array_key_exists($candidate['event_type'], $counts)) {
                $counts[$candidate['event_type']]++;
            }
        }

        $counts['total'] = array_sum($counts);

        return $counts;
    }

    /**
     * The queue itself: one entry per barcode whose latest queue event in the
     * window is newer than its latest print in the same window.
     *
     * Two queries, whatever the size: the candidate events, then the last print
     * or dismissal per barcode via MAX(created_at) grouped by barcode. `first()` on a
     * created_at-descending list is the same value MAX() returns, so the rule is
     * unchanged from the per-barcode version this replaced.
     *
     * @return Collection<int, array{barcode: string, event_type: string, created_at: Carbon}>
     */
    private function candidates(): Collection
    {
        $since = now()->subDays(self::WINDOW_DAYS);

        $eventsByBarcode = LabelLog::whereIn('event_type', LabelLog::QUEUE_EVENTS)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('barcode');

        if ($eventsByBarcode->isEmpty()) {
            return collect();
        }

        // A plain array, keyed by barcode. Not Collection::merge(): barcodes are
        // numeric strings, which PHP stores as integer keys, and merge()
        // renumbers integer keys the way array_merge does — every lookup would
        // miss and nothing would ever be dropped from the queue.
        $lastResolved = [];

        foreach ($eventsByBarcode->keys()->chunk(self::CHUNK) as $chunk) {
            $found = LabelLog::whereIn('event_type', LabelLog::RESOLVE_EVENTS)
                ->where('created_at', '>=', $since)
                ->whereIn('barcode', $chunk->all())
                ->groupBy('barcode')
                ->selectRaw('barcode, MAX(created_at) as last_resolved')
                ->pluck('last_resolved', 'barcode');

            foreach ($found as $barcode => $resolvedAt) {
                $lastResolved[$barcode] = $resolvedAt;
            }
        }

        return $eventsByBarcode->map(function ($events, $barcode) use ($lastResolved) {
            $mostRecentEvent = $events->first();
            $resolvedAt = $lastResolved[$barcode] ?? null;

            if ($resolvedAt !== null && $mostRecentEvent->created_at <= Carbon::parse($resolvedAt)) {
                return null;
            }

            return [
                'barcode' => (string) $barcode,
                'event_type' => $mostRecentEvent->event_type,
                'created_at' => $mostRecentEvent->created_at,
            ];
        })->filter()->values();
    }
}
