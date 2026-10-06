<?php

namespace App\Services\Deposits;

use App\Models\BarrelCode;
use App\Models\Delivery;
use App\Models\DeliveryBarrel;
use App\Models\DeliveryItem;
use App\Models\DepositSighting;
use App\Models\Product;
use App\Models\ProductDeposit;
use App\Models\SupplierLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Turns the deposit (barrel) codes Udea prints on delivery-note product lines
 * into evidence (deposit_sightings) and suggestions (product_deposits).
 *
 * A suggestion only exists for a product seen with a code whose tier the
 * owner has switched on (barrel_codes.charge_customer). The app never infers
 * a deposit from a product's name, size or category.
 */
class DepositEvidenceService
{
    /** PHP twin of BARREL_CODE_SUFFIX_REGEX in parsers/delivery_udea.py. */
    public const BARREL_CODE_SUFFIX = '/^(?<desc>.*?\b[A-Z]{2})\s*(?<code>\d{1,5})$/';

    /** PHP twin of BARE_BARREL_CODE_SUFFIX_REGEX: a garbled country code, then the code. */
    public const BARE_BARREL_CODE_SUFFIX = '/\s(?<code>\d{1,5})$/';

    /** Udea's own supplier id, preferred when a code exists under several Udea ids. */
    private const UDEA_MAIN_SUPPLIER_ID = 5;

    /**
     * Mirrors split_barrel_code() in parsers/delivery_udea.py. When pdfplumber
     * garbled the country code, a bare trailing code is accepted only if it is
     * one of $knownCodes: the codes on the same delivery's barrels section.
     *
     * @param  array<int, string>  $knownCodes
     * @return array{0: string, 1: string|null} [description, code]
     */
    public static function splitBarrelCode(string $description, array $knownCodes = []): array
    {
        if (preg_match(self::BARREL_CODE_SUFFIX, $description, $m)) {
            return [rtrim($m['desc']), $m['code']];
        }

        if ($knownCodes !== []
            && preg_match(self::BARE_BARREL_CODE_SUFFIX, $description, $m, PREG_OFFSET_CAPTURE)
            && in_array($m['code'][0], array_map('strval', $knownCodes), true)) {
            return [rtrim(substr($description, 0, $m[0][1])), $m['code'][0]];
        }

        return [$description, null];
    }

    /**
     * After a delivery PDF import: record its deposit codes, refresh the
     * suggestions and bring the till in line. Never throws: a failure here is
     * logged and must not fail the import.
     */
    public function afterDeliveryImport(Delivery $delivery): void
    {
        try {
            $sightings = $this->recordDelivery($delivery);
            $suggestions = $this->refreshSuggestions();
            $sync = app(DepositPosService::class)->syncAll();

            Log::info('Deposit evidence recorded for delivery', [
                'delivery_id' => $delivery->id,
                'sightings' => $sightings,
                'suggestions_created' => $suggestions['created'],
                'pos' => $sync['totals'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Deposit evidence hook failed', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function isUdeaSupplier(int $supplierId): bool
    {
        return in_array($supplierId, $this->udeaSupplierIds(), true);
    }

    /**
     * @return array<int, int>
     */
    public function udeaSupplierIds(): array
    {
        return array_map('intval', config('suppliers.external_links.udea.supplier_ids', []));
    }

    /**
     * Record a sighting for every line of the delivery that carries a barrel code.
     *
     * @return int sightings created or changed
     */
    public function recordDelivery(Delivery $delivery): int
    {
        $written = 0;

        $items = $delivery->items()
            ->whereNotNull('barrel_code')
            ->whereNotNull('supplier_code')
            ->get();

        foreach ($items as $item) {
            $sighting = DepositSighting::updateOrCreate(
                [
                    'source_type' => DepositSighting::SOURCE_DELIVERY_ITEM,
                    'source_id' => $item->id,
                    'supplier_code' => $item->supplier_code,
                    'barrel_code' => $item->barrel_code,
                ],
                [
                    'supplier_id' => $delivery->supplier_id,
                    // Lines imported before invoice_delivered_quantity existed hold 0 there.
                    'units' => (int) ($item->invoice_delivered_quantity ?: $item->ordered_quantity),
                    'seen_on' => $delivery->delivery_date,
                ]
            );

            if ($sighting->wasRecentlyCreated || $sighting->wasChanged()) {
                $written++;
            }
        }

        return $written;
    }

    /**
     * Split the barrel code off Udea delivery lines imported before the parser
     * captured it (the code is the trailing token of the description), then
     * record the sightings. Idempotent: lines that already have a code are skipped.
     *
     * @return array{rows: int, deliveries: int, codes: array<string, int>, sightings: int}
     */
    public function backfillDeliveryItems(bool $dryRun = false): array
    {
        $rows = 0;
        $codes = [];
        $deliveryIds = [];
        $deliveryCodes = [];

        DeliveryItem::query()
            ->whereNull('barrel_code')
            ->whereHas('delivery', fn ($q) => $q->whereIn('supplier_id', $this->udeaSupplierIds()))
            ->select(['id', 'delivery_id', 'description'])
            ->chunkById(1000, function (Collection $items) use ($dryRun, &$rows, &$codes, &$deliveryIds, &$deliveryCodes) {
                // Only the delivery's own barrels section may vouch for a bare code.
                $missing = $items->pluck('delivery_id')->unique()->diff(array_keys($deliveryCodes));
                foreach ($missing as $id) {
                    $deliveryCodes[$id] = [];
                }
                DeliveryBarrel::whereIn('delivery_id', $missing)->get(['delivery_id', 'supplier_code'])
                    ->each(function ($barrel) use (&$deliveryCodes) {
                        $deliveryCodes[$barrel->delivery_id][] = (string) $barrel->supplier_code;
                    });

                foreach ($items as $item) {
                    [$description, $code] = self::splitBarrelCode((string) $item->description, $deliveryCodes[$item->delivery_id]);
                    if ($code === null) {
                        continue;
                    }

                    $rows++;
                    $codes[$code] = ($codes[$code] ?? 0) + 1;
                    $deliveryIds[$item->delivery_id] = true;

                    if (! $dryRun) {
                        DeliveryItem::whereKey($item->id)->update([
                            'description' => $description,
                            'barrel_code' => $code,
                        ]);
                    }
                }
            });

        $sightings = 0;
        if (! $dryRun) {
            foreach (Delivery::whereIn('id', array_keys($deliveryIds))->get() as $delivery) {
                $sightings += $this->recordDelivery($delivery);
            }
        }

        ksort($codes);

        return [
            'rows' => $rows,
            'deliveries' => count($deliveryIds),
            'codes' => $codes,
            'sightings' => $sightings,
        ];
    }

    /**
     * Rebuild the suggestions and evidence counts from the sightings.
     *
     * @return array{created: int, updated: int, unchanged: int, unmatched: array<int, string>, tier_off: int}
     */
    public function refreshSuggestions(): array
    {
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'unmatched' => [], 'tier_off' => 0];

        // Several supplier codes can resolve to the same till product, so the
        // evidence is merged per product before anything is written.
        $byProduct = [];
        foreach (DepositSighting::all()->groupBy('supplier_code') as $supplierCode => $sightings) {
            $product = $this->resolveProduct((string) $supplierCode);
            if (! $product) {
                $result['unmatched'][] = (string) $supplierCode;

                continue;
            }

            $byProduct[$product->ID] ??= ['product' => $product, 'sightings' => collect()];
            $byProduct[$product->ID]['sightings'] = $byProduct[$product->ID]['sightings']->concat($sightings);
        }

        foreach ($byProduct as $productId => ['product' => $product, 'sightings' => $sightings]) {
            $perCode = $sightings->groupBy('barrel_code')->map(fn (Collection $s) => [
                'units' => (int) $s->sum('units'),
                'lines' => $s->unique(fn ($x) => $x->source_type.':'.$x->source_id)->count(),
            ]);

            $dominant = $perCode->sortBy([['units', 'desc'], ['lines', 'desc']])->keys()->first();
            $dominantTier = $this->tierFor((string) $dominant);

            $row = ProductDeposit::where('product_id', $productId)->first();

            if (! $row) {
                if (! $dominantTier?->charge_customer) {
                    $result['tier_off']++;

                    continue;
                }
                $row = new ProductDeposit([
                    'product_id' => $productId,
                    'barrel_code_id' => $dominantTier->id,
                    'status' => ProductDeposit::STATUS_SUGGESTED,
                    'source' => ProductDeposit::SOURCE_INVOICE,
                ]);
            } elseif ($row->status === ProductDeposit::STATUS_SUGGESTED && $dominantTier?->charge_customer) {
                // A suggestion follows the evidence; confirmed and rejected rows keep their tier.
                $row->barrel_code_id = $dominantTier->id;
            }

            $tierCode = $dominantTier && (int) $row->barrel_code_id === $dominantTier->id
                ? (string) $dominant
                : (string) BarrelCode::whereKey($row->barrel_code_id)->value('supplier_code');

            $row->fill([
                'product_code' => $product->CODE,
                'sightings_units' => $perCode[$tierCode]['units'] ?? 0,
                'sightings_count' => $perCode[$tierCode]['lines'] ?? 0,
                'conflicting_units' => (int) $perCode->except([$tierCode])->sum('units'),
                'last_seen_on' => $sightings->max('seen_on'),
            ]);

            if (! $row->exists) {
                $row->save();
                $result['created']++;
            } elseif ($row->isDirty()) {
                $row->save();
                $result['updated']++;
            } else {
                $result['unchanged']++;
            }
        }

        sort($result['unmatched']);

        return $result;
    }

    /**
     * For the /deposits screen: each row's Udea code and the other deposit
     * codes its product was seen under ("also seen as 315 (6 units)").
     *
     * @param  Collection<int, ProductDeposit>  $rows  with barrelCode loaded
     * @return array<int, array{udea_code: string|null, others: array<string, int>}> keyed by row id
     */
    public function evidenceDetails(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $productIds = $rows->pluck('product_id')->all();

        // Udea code per product: the latest delivery line with a deposit code,
        // else the supplier link.
        $codes = DeliveryItem::whereIn('product_id', $productIds)
            ->whereNotNull('barrel_code')
            ->orderBy('id')
            ->pluck('supplier_code', 'product_id')
            ->all();

        $missing = $rows->filter(fn ($r) => ! isset($codes[$r->product_id]) && $r->product_code)->pluck('product_id', 'product_code');
        if ($missing->isNotEmpty()) {
            SupplierLink::whereIn('SupplierID', $this->udeaSupplierIds())
                ->whereIn('Barcode', $missing->keys()->all())
                ->get(['Barcode', 'SupplierCode'])
                ->each(function ($link) use ($missing, &$codes) {
                    $codes[$missing[$link->Barcode]] ??= $link->SupplierCode;
                });
        }

        $details = [];
        foreach ($rows as $row) {
            $udeaCode = $codes[$row->product_id] ?? null;
            $others = [];

            if ($row->conflicting_units > 0 && $udeaCode !== null) {
                $others = DepositSighting::where('supplier_code', $udeaCode)
                    ->where('barrel_code', '!=', (string) $row->barrelCode?->supplier_code)
                    ->selectRaw('barrel_code, sum(units) as units')
                    ->groupBy('barrel_code')
                    ->pluck('units', 'barrel_code')
                    ->map(fn ($u) => (int) $u)
                    ->all();
            }

            $details[$row->id] = ['udea_code' => $udeaCode, 'others' => $others];
        }

        return $details;
    }

    /**
     * The till product behind a Udea supplier code: the supplier link first,
     * then the product the latest delivery line was matched to.
     */
    public function resolveProduct(string $supplierCode): ?Product
    {
        $barcode = SupplierLink::whereIn('SupplierID', $this->udeaSupplierIds())
            ->where('SupplierCode', $supplierCode)
            ->value('Barcode');

        if ($barcode !== null && $product = Product::where('CODE', $barcode)->first()) {
            return $product;
        }

        $productId = DeliveryItem::where('supplier_code', $supplierCode)
            ->whereNotNull('product_id')
            ->whereHas('delivery', fn ($q) => $q->whereIn('supplier_id', $this->udeaSupplierIds()))
            ->latest('id')
            ->value('product_id');

        return $productId ? Product::find($productId) : null;
    }

    /**
     * The Udea barrel code row for a code, preferring Udea's main supplier id.
     */
    public function tierFor(string $code): ?BarrelCode
    {
        return BarrelCode::where('supplier_code', $code)
            ->whereIn('supplier_id', $this->udeaSupplierIds())
            ->get()
            ->sortBy(fn (BarrelCode $tier) => $tier->supplier_id === self::UDEA_MAIN_SUPPLIER_ID ? 0 : 1)
            ->first();
    }
}
