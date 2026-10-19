<?php

namespace App\Services\Deposits;

use App\Models\DeliveryItem;
use App\Models\LabelLog;
use App\Models\Product;
use App\Models\ProductDeposit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Price facts for the owner's review of each deposit product on /deposits:
 * was the shelf price raised in the past to cover the unreturned bottle?
 * The service shows facts (price, Udea cost and advice price, the last lasting
 * price step from the till's full-price lines); it never decides. Lowering writes PRICESELL the same way as
 * the product page (ProductController::updatePrice()).
 */
class DepositPriceReviewService
{
    /** Price history from the till's ticket lines starts here. */
    private const HISTORY_FROM = '2024-01-01';

    /** A product.name ending " - 5%" (or " - 10%") in a line's ATTRIBUTES marks a discounted line. */
    private const DISCOUNT_LIKE = '% - _%!%</entry>%';

    /** Net price this far (cents) from the last full-price sale counts as changed since. */
    private const CHANGED_MIN_CENTS = 5;

    /** A new daily price this far (cents) from the established one is a candidate step. */
    private const STEP_MIN_CENTS = 5;

    /** ...and counts once the next selling days stay within this (cents) of it. */
    private const HOLD_TOLERANCE_CENTS = 2;

    private const HOLD_DAYS = 3;

    public function __construct(
        private DepositEvidenceService $evidence,
        private DepositPosService $pos,
    ) {}

    /**
     * Price facts per product_id for the rows on screen. $products is keyed by
     * ID and carries PRICESELL, PRICEBUY, TAXCAT and an eager-loaded tax.
     * Markup is against the landed cost: Udea cost × (1 + $deliveryPct / 100).
     *
     * @return array<string, array<string, mixed>>
     */
    public function forRows(Collection $rows, Collection $products, float $deliveryPct): array
    {
        $ids = $rows->pluck('product_id')->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $lines = DeliveryItem::query()
            ->join('deliveries', 'deliveries.id', '=', 'delivery_items.delivery_id')
            ->whereIn('deliveries.supplier_id', $this->evidence->udeaSupplierIds())
            ->whereIn('delivery_items.product_id', $ids)
            ->orderByDesc('deliveries.delivery_date')
            ->orderByDesc('delivery_items.id')
            ->toBase()
            ->get([
                'delivery_items.product_id',
                'delivery_items.unit_cost',
                'delivery_items.sale_price',
                'deliveries.delivery_date',
            ])
            ->groupBy('product_id');

        $tillLines = $this->tillLineGroups($ids);
        $soldFrom = now()->subDays(365)->toDateString();

        $facts = [];
        foreach ($ids as $id) {
            $groups = $tillLines->get($id, collect());
            $facts[$id] = $this->facts($products->get($id), $lines->get($id, collect()), $this->fullPriceDays($groups), $deliveryPct) + [
                'sold_12m' => $groups->isEmpty() ? null : (float) $groups->filter(fn ($g) => $this->day($g) >= $soldFrom)->sum('units'),
                'last_sale_on' => $groups->isEmpty() ? null : $this->day($groups->last()),
            ];
        }

        return $facts;
    }

    /**
     * Lower the shelf price by the row's deposit, so price + deposit is what
     * the customer paid before. Same write as the product page: gross to net
     * with the product's VAT rate, PRICESELL, then a label-log price update.
     *
     * @return array{ok: bool, message: string}
     */
    public function lowerByDeposit(ProductDeposit $row, User $by): array
    {
        $product = Product::find($row->product_id);
        if (! $product) {
            return ['ok' => false, 'message' => "{$row->product_code}: product not found on the till."];
        }

        $tier = $row->barrelCode;
        if (! $tier) {
            return ['ok' => false, 'message' => "{$product->NAME}: no deposit tier on this row."];
        }

        $rate = $product->getVatRate();
        $net = (float) $product->PRICESELL;
        $gross = round($net * (1 + $rate), 2);
        $newGross = round($gross - (float) $tier->unit_price, 2);
        if ($newGross <= 0) {
            return ['ok' => false, 'message' => sprintf(
                '%s: €%.2f less the €%.2f deposit leaves nothing; price unchanged.',
                $product->NAME, $gross, $tier->unit_price
            )];
        }

        $newNet = $this->writeGross($product, $newGross);
        $row->reviewPrice(ProductDeposit::PRICE_LOWERED, $by, $gross)->save();

        return ['ok' => true, 'message' => sprintf(
            '%s: €%.2f → €%.2f on the till (net %.4f → %.4f); label queued.',
            $product->NAME, $gross, $newGross, $net, $newNet
        )];
    }

    /**
     * Set the shelf price to a gross price the owner typed, by the same
     * recipe as lowerByDeposit().
     *
     * @return array{ok: bool, message: string}
     */
    public function setGrossPrice(ProductDeposit $row, User $by, float $newGross): array
    {
        $product = Product::find($row->product_id);
        if (! $product) {
            return ['ok' => false, 'message' => "{$row->product_code}: product not found on the till."];
        }

        $newGross = round($newGross, 2);
        if ($newGross <= 0) {
            return ['ok' => false, 'message' => "{$product->NAME}: a price must be above zero; price unchanged."];
        }

        $net = (float) $product->PRICESELL;
        $gross = round($net * (1 + $product->getVatRate()), 2);
        $newNet = $this->writeGross($product, $newGross);
        $row->reviewPrice(ProductDeposit::PRICE_SET, $by, $gross)->save();

        return ['ok' => true, 'message' => sprintf(
            '%s: €%.2f → €%.2f on the till (net %.4f → %.4f); label queued.',
            $product->NAME, $gross, $newGross, $net, $newNet
        )];
    }

    /**
     * Take a decided row back to suggested. When its confirm changed the
     * price (lowered or set), the gross price before is written back by the
     * same recipe (so the label area lists it again). The till's deposit
     * properties are cleared, as Reject does.
     *
     * @return array{ok: bool, message: string}
     */
    public function undo(ProductDeposit $row): array
    {
        $restored = null;
        if (in_array($row->price_review, [ProductDeposit::PRICE_LOWERED, ProductDeposit::PRICE_SET], true)
            && $row->price_review_from !== null
            && ($product = Product::find($row->product_id))) {
            $restored = round((float) $row->price_review_from, 2);
            $this->writeGross($product, $restored);
        }

        $row->reviewPrice(null, null)->decide(ProductDeposit::STATUS_SUGGESTED, null)->save();
        $this->pos->syncProduct($row->fresh('barrelCode'));

        return ['ok' => true, 'message' => $restored !== null
            ? sprintf('%s: back to suggested; price restored to €%.2f.', $row->product_code, $restored)
            : "{$row->product_code}: back to suggested; price unchanged."];
    }

    /**
     * The product page's price write: gross to net with the product's VAT
     * rate (no further rounding), PRICESELL, then a label-log price update.
     *
     * @return float the new net price
     */
    private function writeGross(Product $product, float $newGross): float
    {
        $rate = $product->getVatRate();
        $newNet = $rate > 0 ? $newGross / (1 + $rate) : $newGross;

        Product::whereKey($product->ID)->update(['PRICESELL' => $newNet]);
        LabelLog::logPriceUpdate($product->CODE);

        return $newNet;
    }

    /**
     * @param  Collection<int, object>  $lines  Udea lines, newest first
     * @param  Collection<int, object>  $days  full-price selling days, oldest first
     * @return array<string, mixed>
     */
    private function facts(?Product $product, Collection $lines, Collection $days, float $deliveryPct): array
    {
        $net = $product ? (float) $product->PRICESELL : null;
        $rate = $product ? $product->getVatRate() : null;

        $costLine = $lines->first(fn ($l) => (float) $l->unit_cost > 0);
        $rrpLine = $lines->first(fn ($l) => (float) $l->sale_price > 0);

        if ($costLine) {
            $cost = (float) $costLine->unit_cost;
            $costOn = substr((string) $costLine->delivery_date, 0, 10);
            $costSource = 'docket';
        } else {
            $cost = $product && (float) $product->PRICEBUY > 0 ? (float) $product->PRICEBUY : null;
            $costOn = null;
            $costSource = $cost !== null ? 'till' : null;
        }

        $landed = $cost ? $cost * (1 + $deliveryPct / 100) : null;
        $gross = $net !== null ? round($net * (1 + $rate), 2) : null;

        $step = $this->lastLastingStep($days);
        $last = $days->last();
        $lastSold = $last ? ['on' => $last->sale_date, 'net' => (float) $last->avg_price] : null;
        $costBeforeStep = null;
        if ($step) {
            $before = $lines->first(fn ($l) => (float) $l->unit_cost > 0
                && substr((string) $l->delivery_date, 0, 10) < $step['on']);
            $costBeforeStep = $before ? (float) $before->unit_cost : null;
        }

        return [
            'net' => $net,
            'gross' => $gross,
            'vat_rate' => $rate,
            'cost' => $cost,
            'cost_on' => $costOn,
            'cost_source' => $costSource,
            'landed' => $landed,
            'markup_pct' => $net !== null && $landed ? ($net - $landed) / $landed * 100 : null,
            'profit' => $net !== null && $landed ? $net - $landed : null,
            'vat_ratio' => $net ? $gross / $net : 1.0,
            'udea_rrp' => $rrpLine ? (float) $rrpLine->sale_price : null,
            'step' => $step,
            'cost_before_step' => $costBeforeStep,
            'last_sold' => $lastSold,
            'changed_since_sale' => $lastSold !== null && $net !== null
                && abs((int) round($net * 100) - (int) round($lastSold['net'] * 100)) >= self::CHANGED_MIN_CENTS,
        ];
    }

    /**
     * The till's sale lines since HISTORY_FROM, grouped per product, day and
     * price (oldest first), with the units and how many lines were
     * discounted. Read-only; TICKETLINES, TICKETS and RECEIPTS have no
     * Eloquent model, so the query builder is used.
     *
     * @param  array<int, string>  $ids
     * @return Collection<string, Collection<int, object>>
     */
    private function tillLineGroups(array $ids): Collection
    {
        return DB::connection('pos')->table('TICKETLINES as tl')
            ->join('TICKETS as t', 't.ID', '=', 'tl.TICKET')
            ->join('RECEIPTS as r', 'r.ID', '=', 't.ID')
            ->whereIn('tl.PRODUCT', $ids)
            ->where('r.DATENEW', '>=', self::HISTORY_FROM)
            ->where('tl.UNITS', '>', 0)
            ->selectRaw(
                'tl.PRODUCT as product_id, DATE(r.DATENEW) as sale_date, ROUND(tl.PRICE, 2) as price, '
                .'COUNT(*) as line_count, SUM(tl.UNITS) as units, '
                .'SUM(CASE WHEN tl.ATTRIBUTES LIKE ? ESCAPE ? THEN 1 ELSE 0 END) as discounted',
                [self::DISCOUNT_LIKE, '!']
            )
            ->groupBy('product_id', 'sale_date', 'price')
            ->orderBy('product_id')
            ->orderBy('sale_date')
            ->get()
            ->groupBy('product_id');
    }

    /**
     * The full price per selling day, oldest first: among the day's prices
     * with at least one full-price line, the one with the most full-price
     * lines (ties to the higher price). Days with only discounted lines are
     * skipped.
     *
     * @param  Collection<int, object>  $groups  one product's till-line groups
     * @return Collection<int, object{sale_date: string, avg_price: float}>
     */
    private function fullPriceDays(Collection $groups): Collection
    {
        return $groups
            ->groupBy(fn ($g) => $this->day($g))
            ->map(function (Collection $day, string $date) {
                $best = $day
                    ->filter(fn ($g) => (int) $g->line_count - (int) $g->discounted > 0)
                    ->sortBy([
                        fn ($a, $b) => ((int) $b->line_count - (int) $b->discounted) <=> ((int) $a->line_count - (int) $a->discounted),
                        fn ($a, $b) => (float) $b->price <=> (float) $a->price,
                    ])
                    ->first();

                return $best ? (object) ['sale_date' => $date, 'avg_price' => (float) $best->price] : null;
            })
            ->filter()
            ->sortKeys()
            ->values();
    }

    private function day(object $group): string
    {
        return substr((string) $group->sale_date, 0, 10);
    }

    /**
     * The most recent price step that held: a daily full price at least 5 cents
     * from the established price, followed by three selling days within
     * 2 cents of it. A change in the last three selling days does not show yet.
     *
     * @param  Collection<int, object>  $days
     * @return array{on: string, from: float, to: float}|null
     */
    public function lastLastingStep(Collection $days): ?array
    {
        $points = $days->map(fn ($d) => [
            'on' => substr((string) $d->sale_date, 0, 10),
            'cents' => (int) round((float) $d->avg_price * 100),
        ])->values()->all();

        if ($points === []) {
            return null;
        }

        $established = $points[0]['cents'];
        $step = null;
        $count = count($points);

        for ($i = 1; $i < $count; $i++) {
            $p = $points[$i]['cents'];
            if (abs($p - $established) < self::STEP_MIN_CENTS || $i + self::HOLD_DAYS >= $count) {
                continue;
            }

            $holds = true;
            for ($k = 1; $k <= self::HOLD_DAYS; $k++) {
                if (abs($points[$i + $k]['cents'] - $p) >= self::HOLD_TOLERANCE_CENTS) {
                    $holds = false;
                    break;
                }
            }

            if ($holds) {
                $step = ['on' => $points[$i]['on'], 'from' => $established / 100, 'to' => $p / 100];
                $established = $p;
            }
        }

        return $step;
    }
}
