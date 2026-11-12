<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Country;
use App\Models\Delivery;
use App\Models\DynamisPricingLine;
use App\Models\DynamisProductLink;
use App\Models\PosUnit;
use App\Models\Product;
use App\Models\ProductsCat;
use App\Models\VegDetails;
use App\Models\VegPrintQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Dynamis invoice pricing: turns a parsed invoice into a delivery docket with
 * one pricing line per invoice line, and applies the prices a manager settles
 * on to the till. See docs/features/dynamis-pricing.md.
 *
 * The guide price is the owner's spreadsheet formula:
 *
 *     guide RRP per invoice unit = net unit price × transport × markup
 *
 * A box is worth `guide × quantity ÷ packages`, and a shelf price is that box
 * divided by what is in it: kilos (per kg) or a count (each).
 */
class DynamisPricingService
{
    public const SUPPLIER_ID = 56;

    public const DEFAULT_TRANSPORT = 1.2;

    public const DEFAULT_MARKUP = 1.5;

    /** A name guess at or above this decides per kg / each before it is confirmed. */
    private const TRUSTED_GUESS = 0.5;

    /** A name match this close may be suggested even when the product is hidden from the till. */
    private const EXACT_GUESS = 0.9;

    private const SETTING_TRANSPORT = 'dynamis.transport_multiplier';

    private const SETTING_MARKUP = 'dynamis.markup';

    private ?Collection $countriesByCode = null;

    public function __construct(
        private DynamisInvoicePdfParserService $parser,
        private DynamisMatcherService $matcher,
        private DeliveryService $deliveryService,
        private FruitVegPriceService $priceService,
    ) {}

    // ---------------------------------------------------------------------
    // The formula
    // ---------------------------------------------------------------------

    /** Guide RRP per invoice unit (kg, box or piece), VAT included when the product carries any. */
    public static function guidePrice(float $netUnitPrice, float $transport, float $markup, float $vatRate = 0.0): float
    {
        return $netUnitPrice * $transport * $markup * (1 + $vatRate);
    }

    /**
     * Suggested shelf price for a line in the given mode, or null when the
     * figure it has to be divided by (count or kilos in a box) is not known.
     */
    public static function suggestedPrice(
        string $mode,
        float $netUnitPrice,
        float $quantity,
        int $packages,
        ?float $unitsPerBox,
        ?float $kgPerBox,
        float $transport,
        float $markup,
        float $vatRate = 0.0,
    ): ?float {
        $divisor = match ($mode) {
            DynamisPricingLine::MODE_PER_KG => $kgPerBox,
            DynamisPricingLine::MODE_EACH => $unitsPerBox,
            default => null,
        };

        if (! $divisor || $divisor <= 0 || $packages <= 0) {
            return null;
        }

        $boxPrice = self::guidePrice($netUnitPrice, $transport, $markup, $vatRate) * $quantity / $packages;

        return round($boxPrice / $divisor, 2);
    }

    /**
     * What the description says is in a box, for box-priced (COL) lines:
     * "12 PIECES" is a count, "SIZE 22" may be one, "1KG" / "ABOUT 4.5KG" is a weight.
     *
     * @return array{units_per_box: ?float, kg_per_box: ?float, count_source: ?string}
     */
    public static function readBoxContents(string $description): array
    {
        $units = null;
        $source = null;

        if (preg_match('/\b(\d{1,3})\s*(?:PIECES?|PCS?|BUNCH(?:ES)?|TRAYS?|HEADS?)\b/i', $description, $m)) {
            $units = (float) $m[1];
            $source = 'pieces';
        } elseif (preg_match('/\bSIZE\s+(\d{1,3})(?![\d\/+-])\b/i', $description, $m)) {
            $units = (float) $m[1];
            $source = 'size';
        }

        $kg = preg_match('/\b(\d+(?:[.,]\d+)?)\s*KG\b/i', $description, $m)
            ? (float) str_replace(',', '.', $m[1])
            : null;

        return ['units_per_box' => $units, 'kg_per_box' => $kg, 'count_source' => $source];
    }

    // ---------------------------------------------------------------------
    // Settings
    // ---------------------------------------------------------------------

    /** @return array{transport: float, markup: float} */
    public function defaults(): array
    {
        return [
            'transport' => (float) AppSetting::get(self::SETTING_TRANSPORT, self::DEFAULT_TRANSPORT),
            'markup' => (float) AppSetting::get(self::SETTING_MARKUP, self::DEFAULT_MARKUP),
        ];
    }

    /** The multipliers in force for one invoice. */
    public function settings(Delivery $delivery): array
    {
        $stored = $delivery->import_data['dynamis'] ?? [];
        $defaults = $this->defaults();

        return [
            'transport' => (float) ($stored['transport'] ?? $defaults['transport']),
            'markup' => (float) ($stored['markup'] ?? $defaults['markup']),
        ];
    }

    public function saveSettings(Delivery $delivery, float $transport, float $markup, bool $asDefault = false): void
    {
        $data = $delivery->import_data ?? [];
        $data['dynamis'] = array_merge($data['dynamis'] ?? [], ['transport' => $transport, 'markup' => $markup]);
        $delivery->update(['import_data' => $data]);

        if ($asDefault) {
            AppSetting::set(self::SETTING_TRANSPORT, $transport);
            AppSetting::set(self::SETTING_MARKUP, $markup);
        }
    }

    // ---------------------------------------------------------------------
    // Invoice → docket
    // ---------------------------------------------------------------------

    public function findByInvoiceNumber(?string $invoiceNumber): ?Delivery
    {
        if (! $invoiceNumber) {
            return null;
        }

        return Delivery::where('supplier_id', self::SUPPLIER_ID)
            ->where('import_data->dynamis->invoice_number', $invoiceNumber)
            ->first();
    }

    /**
     * Create the docket and its pricing lines from DynamisInvoicePdfParserService output.
     *
     * Only certain matches reach the docket (a saved link, or an EAN that is a
     * till barcode). A name-based guess is kept on the pricing line as a
     * suggestion until a manager confirms it.
     */
    public function createDocket(array $parsed, string $filename): Delivery
    {
        $items = $parsed['data']['items'];
        $meta = $parsed['data']['metadata'];
        $totals = $parsed['data']['totals'];

        $links = DynamisProductLink::whereIn('dynamis_code', array_column($items, 'code'))->get()->keyBy('dynamis_code');
        $certain = $this->certainMatches($items, $links);
        $guesses = $this->nameGuesses($items, $certain);

        $rows = $this->parser->convertToDeliveryItems($parsed);
        foreach ($rows as $i => $row) {
            if (isset($certain[$i])) {
                $rows[$i]['product_id'] = $certain[$i];
            }
        }

        $shipping = (float) ($totals['shipping'] ?? 0);
        $gross = $totals['gross'];

        return DB::transaction(function () use ($rows, $items, $meta, $totals, $filename, $links, $guesses, $shipping, $gross) {
            $delivery = $this->deliveryService->importFromPdfData(
                $rows,
                self::SUPPLIER_ID,
                $meta['invoice_date'],
                $filename,
                [
                    'grand_stated' => $gross !== null ? round($gross + $shipping, 2) : null,
                    'grand_calculated' => round($totals['lines_sum'] + $shipping, 2),
                    'discrepancy' => $gross !== null ? round($totals['lines_sum'] - $gross, 2) : null,
                    'totals_match' => $totals['totals_match'],
                    'costs_total' => $shipping,
                ]
            );

            $data = $delivery->import_data ?? [];
            $data['dynamis'] = [
                'invoice_number' => $meta['invoice_number'],
                'invoice_date' => $meta['invoice_date'],
                'gross' => $gross,
                'shipping' => $shipping,
                'lines_sum' => $totals['lines_sum'],
            ] + $this->defaults();
            $delivery->update(['import_data' => $data]);

            $deliveryItems = $delivery->items()->orderBy('id')->get()->values();

            foreach ($items as $i => $item) {
                $barcoded = ! empty($item['ean']);
                $link = $links->get($item['code']);
                $packages = max(1, (int) $item['packages']);
                $perBox = $item['quantity'] / $packages;
                $contents = self::readBoxContents($item['product']);
                if ($item['unit'] === 'KG' && $contents['count_source'] === 'size') {
                    // On a per-kg line "SIZE 33" is a grade, not how many are in the box.
                    $contents['units_per_box'] = $contents['count_source'] = null;
                }

                // What the invoice itself fixes: kilos in a box on a per-kg line, pieces in a box on a per-piece line.
                $kgPerBox = $item['unit'] === 'KG' ? $perBox : ($link?->kg_per_box ?? $contents['kg_per_box']);
                $unitsPerBox = $item['unit'] === 'PCE' ? $perBox : ($link?->units_per_box ?? $contents['units_per_box']);
                $countSource = match (true) {
                    $item['unit'] === 'PCE' => null,
                    $link?->units_per_box !== null => 'saved',
                    default => $contents['count_source'],
                };

                DynamisPricingLine::create([
                    'delivery_id' => $delivery->id,
                    'delivery_item_id' => $deliveryItems[$i]->id ?? null,
                    'line_no' => $i + 1,
                    'dynamis_code' => $item['code'],
                    'description' => $item['product'],
                    'origin' => $item['origin'],
                    'unit' => $item['unit'],
                    'packages' => $packages,
                    'quantity' => $item['quantity'],
                    'net_unit_price' => $item['net_unit_price'],
                    'line_total' => $item['line_total'],
                    'ean' => $item['ean'],
                    'is_barcoded' => $barcoded,
                    'suggested_product_id' => $guesses[$i]['product_id'] ?? null,
                    'suggested_confidence' => $guesses[$i]['confidence'] ?? null,
                    'pricing_mode' => $barcoded ? null : $link?->pricing_mode,
                    'units_per_box' => $unitsPerBox,
                    'kg_per_box' => $kgPerBox,
                    'count_source' => $countSource,
                ]);
            }

            return $delivery;
        });
    }

    /**
     * @return array<int, string> item index => POS product ID
     */
    private function certainMatches(array $items, Collection $links): array
    {
        $eans = array_values(array_filter(array_column($items, 'ean')));
        $byBarcode = $eans ? Product::whereIn('CODE', $eans)->pluck('ID', 'CODE') : collect();
        $linkedIds = $links->pluck('pos_product_id')->all();
        $existing = $linkedIds ? Product::whereIn('ID', $linkedIds)->pluck('ID')->flip() : collect();

        $matches = [];
        foreach ($items as $i => $item) {
            $link = $links->get($item['code']);
            if ($link && $existing->has($link->pos_product_id)) {
                $matches[$i] = (string) $link->pos_product_id;
            } elseif (! empty($item['ean']) && $byBarcode->has($item['ean'])) {
                $matches[$i] = (string) $byBarcode[$item['ean']];
            }
        }

        return $matches;
    }

    /**
     * Best name-based guess for each unmatched produce line.
     *
     * @return array<int, array{product_id: string, confidence: float}>
     */
    private function nameGuesses(array $items, array $certain): array
    {
        $open = [];
        foreach ($items as $i => $item) {
            if (! isset($certain[$i]) && empty($item['ean'])) {
                $open[$i] = ['code' => '', 'product' => self::productWords($item['product'])];
            }
        }

        $best = function (array $match): ?array {
            $top = $match['product_id'] ? $match : ($match['suggestions'][0] ?? null);

            return $top ? [
                'product_id' => (string) $top['product_id'],
                'confidence' => (float) ($top['confidence'] ?? $top['score'] ?? 0),
            ] : null;
        };

        // Products showing on the till are preferred. One that is hidden is
        // only suggested when nothing on the till matches at all, or when its
        // name is a near-exact match and the till's best is not.
        $onTill = $this->matcher->matchItems(array_values($open));
        $anywhere = $this->matcher->matchItems(array_values($open), false);

        $guesses = [];
        foreach (array_keys($open) as $n => $i) {
            $visible = $best($onTill[$n]);
            $any = $best($anywhere[$n]);

            $useHidden = $any && (
                ! $visible
                || ($any['confidence'] >= self::EXACT_GUESS && $visible['confidence'] < self::EXACT_GUESS)
            );

            if ($guess = $useHidden ? $any : $visible) {
                $guesses[$i] = $guess;
            }
        }

        return $guesses;
    }

    /**
     * Guess again for the lines nobody has tied to a product yet, so a docket
     * imported earlier follows what is on the till now.
     */
    public function refreshGuesses(Delivery $delivery): void
    {
        $lines = DynamisPricingLine::where('delivery_id', $delivery->id)
            ->where('is_barcoded', false)
            ->with('deliveryItem')
            ->orderBy('line_no')
            ->get()
            ->filter(fn ($line) => ! $line->deliveryItem?->product_id)
            ->values();

        $items = $lines->map(fn ($line) => ['product' => $line->description, 'ean' => null])->all();
        $guesses = $this->nameGuesses($items, []);

        foreach ($lines as $n => $line) {
            $line->update([
                'suggested_product_id' => $guesses[$n]['product_id'] ?? null,
                'suggested_confidence' => $guesses[$n]['confidence'] ?? null,
            ]);
        }
    }

    /**
     * The description without what never appears in a till name: class, size,
     * pack, weight, certification and country of origin.
     */
    public static function productWords(string $description): string
    {
        $noise = [
            '/\bCLASS\s+I{1,3}\b/i',
            '/\bSIZE\s+\S+/i',
            '/\b\d+\s*\+/',
            '/\b(?:ABOUT\s+)?\d+(?:[.,]\d+)?\s*(?:KG|G|CL|ML|L)\b/i',
            '/\b\d+\s*(?:PIECES?|PCS?|BUNCH(?:ES)?|TRAYS?|HEADS?)\b/i',
            '/\b(?:DEMETER|FAIR\s*TRADE|TRAY|ON THE VINE)\b/i',
            '/\b(?:SPAIN|ITALY|FRANCE|NETHERLANDS|ARGENTIN[AE]|DOMINICAN REPUBLIC|MOROCCO|PERU|EGYPT|GREECE|GERMANY|BELGIUM|PORTUGAL|ISRAEL|TURKEY|SOUTH AFRICA|NEW ZEALAND|CHILE|ECUADOR|COLOMBIA|MEXICO|BRAZIL|KENYA|TUNISIA)\b/i',
        ];

        return trim(preg_replace('/\s+/', ' ', preg_replace($noise, ' ', $description)));
    }

    // ---------------------------------------------------------------------
    // The pricing page
    // ---------------------------------------------------------------------

    public function hasPricing(Delivery $delivery): bool
    {
        return DynamisPricingLine::where('delivery_id', $delivery->id)->exists();
    }

    /**
     * Every pricing line of a docket, ready for the page.
     *
     * @return array{produce: array, barcoded: array}
     */
    public function lines(Delivery $delivery): array
    {
        $lines = DynamisPricingLine::where('delivery_id', $delivery->id)->with('deliveryItem')->orderBy('line_no')->get();

        $productIds = $lines->map(fn ($l) => $l->deliveryItem?->product_id)
            ->merge($lines->pluck('suggested_product_id'))
            ->filter()->unique()->values();
        $products = $this->describeProducts($productIds->all());

        $out = ['produce' => [], 'barcoded' => []];
        foreach ($lines as $line) {
            $out[$line->is_barcoded ? 'barcoded' : 'produce'][] = $this->present($line, $products);
        }

        return $out;
    }

    /** One line, as the page holds it. */
    public function presentLine(DynamisPricingLine $line): array
    {
        $line->load('deliveryItem');
        $ids = array_filter([$line->deliveryItem?->product_id, $line->suggested_product_id]);

        return $this->present($line, $this->describeProducts($ids));
    }

    private function present(DynamisPricingLine $line, array $products): array
    {
        $productId = $line->deliveryItem?->product_id;
        $product = $productId ? ($products[$productId] ?? null) : null;
        $suggestion = ! $product && $line->suggested_product_id ? ($products[$line->suggested_product_id] ?? null) : null;

        return [
            'id' => $line->id,
            'code' => $line->dynamis_code,
            'description' => $line->description,
            'origin' => $line->origin,
            'origin_country' => $this->originCountry($line->origin),
            'unit' => $line->unit,
            'packages' => $line->packages,
            'quantity' => $line->quantity,
            'net_unit_price' => $line->net_unit_price,
            'line_total' => $line->line_total,
            'ean' => $line->ean,
            'product' => $product,
            'suggestion' => $suggestion ? $suggestion + ['confidence' => $line->suggested_confidence] : null,
            'mode' => in_array($line->pricing_mode, [DynamisPricingLine::MODE_PER_KG, DynamisPricingLine::MODE_EACH], true)
                ? $line->pricing_mode
                : $this->defaultMode($line, $product ?? ($line->suggested_confidence >= self::TRUSTED_GUESS ? $suggestion : null)),
            'units_per_box' => $line->units_per_box,
            'kg_per_box' => $line->kg_per_box,
            'count_source' => $line->count_source,
            'applied_price' => $line->applied_price,
            'applied_at' => $line->applied_at?->format('j M H:i'),
            'skipped' => $line->pricing_mode === DynamisPricingLine::MODE_SKIP,
        ];
    }

    /** How the till sells it wins; otherwise follow the invoice. */
    private function defaultMode(DynamisPricingLine $line, ?array $product): string
    {
        if (($product['till_unit'] ?? null) === 'kg') {
            return DynamisPricingLine::MODE_PER_KG;
        }
        if (($product['till_unit'] ?? null) !== null) {
            return DynamisPricingLine::MODE_EACH;
        }
        if ($line->unit === 'KG' || ($line->unit === 'COL' && ! $line->units_per_box && $line->kg_per_box)) {
            return DynamisPricingLine::MODE_PER_KG;
        }

        return DynamisPricingLine::MODE_EACH;
    }

    /**
     * @param  string[]  $ids  POS product IDs
     * @return array<string, array> keyed by product ID
     */
    private function describeProducts(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $products = Product::with('tax')->whereIn('ID', $ids)->get();
        $details = VegDetails::whereIn('product', $products->pluck('CODE'))->get(['product', 'unitId', 'countryCode'])->keyBy('product');
        $countries = Country::pluck('name', 'id');
        $unitNames = PosUnit::pluck('units', 'ID');
        $onTill = ProductsCat::whereIn('PRODUCT', $products->pluck('ID'))->pluck('PRODUCT')->flip();

        $out = [];
        foreach ($products as $product) {
            $detail = $details[$product->CODE] ?? null;
            $unitId = $detail?->unitId;
            $countryId = $detail?->countryCode !== null ? (int) $detail->countryCode : null;
            $unit = $unitId !== null ? strtolower((string) ($unitNames[$unitId] ?? '')) : null;

            $out[(string) $product->ID] = [
                'id' => (string) $product->ID,
                'code' => (string) $product->CODE,
                'name' => (string) $product->NAME,
                'till_price' => round($product->getGrossPrice(), 2),
                'till_unit' => $unit ?: null,
                'on_till' => $onTill->has($product->ID),
                'country_id' => $countryId,
                'country_name' => $countryId !== null ? ($countries[$countryId] ?? null) : null,
                'vat_rate' => (float) $product->getVatRate(),
            ];
        }

        return $out;
    }

    // ---------------------------------------------------------------------
    // Actions
    // ---------------------------------------------------------------------

    /**
     * Tie a line to a till product (or untie it with null) and remember the
     * choice for this Dynamis code.
     */
    public function linkProduct(DynamisPricingLine $line, ?string $productId, ?int $userId): void
    {
        $product = $productId ? Product::findOrFail($productId) : null;
        $confirmedGuess = $product && (string) $product->ID === (string) $line->suggested_product_id;

        DB::transaction(function () use ($line, $product, $confirmedGuess, $userId) {
            $line->deliveryItem?->update([
                'product_id' => $product?->ID,
                'barcode' => $product?->CODE,
                'is_new_product' => ! $product,
            ]);

            if (! $product) {
                DynamisProductLink::where('dynamis_code', $line->dynamis_code)->delete();

                return;
            }

            DynamisProductLink::updateOrCreate(
                ['dynamis_code' => $line->dynamis_code],
                [
                    'dynamis_description' => $line->description,
                    'pos_product_id' => (string) $product->ID,
                    'pos_product_code' => (string) $product->CODE,
                    'matched_by' => $confirmedGuess ? 'fuzzy' : 'manual',
                    'confidence' => $confirmedGuess ? $line->suggested_confidence : null,
                    'verified_at' => now(),
                    'verified_by' => $userId,
                ]
            );
        });
    }

    /**
     * Write the settled price to the till (price history and label queue
     * included) and remember how this Dynamis code is priced.
     *
     * @return bool whether the till price changed
     */
    public function apply(
        DynamisPricingLine $line,
        string $mode,
        ?float $unitsPerBox,
        ?float $kgPerBox,
        float $price,
        ?int $userId,
    ): bool {
        if (! in_array($mode, [DynamisPricingLine::MODE_PER_KG, DynamisPricingLine::MODE_EACH], true)) {
            throw new InvalidArgumentException('Choose per kg or each before applying a price.');
        }

        $product = $line->deliveryItem?->product_id ? Product::with('tax')->find($line->deliveryItem->product_id) : null;
        if (! $product) {
            throw new InvalidArgumentException('Choose the till product for this line first.');
        }

        $changed = $this->priceService->setPrice($product, $price, $userId);

        $this->remember($line, $mode, $unitsPerBox, $kgPerBox);
        $line->update([
            'applied_price' => round($price, 2),
            'applied_at' => now(),
            'applied_by' => $userId,
        ]);

        return $changed;
    }

    /** Leave the till price alone for this line. */
    public function skip(DynamisPricingLine $line, bool $skip = true): void
    {
        $line->update(['pricing_mode' => $skip ? DynamisPricingLine::MODE_SKIP : null]);
    }

    /**
     * Make the line's linked product a button on the till (PRODUCTS_CAT),
     * the same switch as Fruit & Veg > Manage. Already visible is a no-op.
     */
    public function showOnTill(DynamisPricingLine $line): void
    {
        $productId = $line->deliveryItem?->product_id;
        if (! $productId || ! Product::whereKey($productId)->exists()) {
            throw new InvalidArgumentException('Choose the till product for this line first.');
        }

        app(TillVisibilityService::class)->setVisibility((string) $productId, true, 'fruit_veg');
    }

    /**
     * Set the linked product's country of origin to the one on the invoice
     * line and queue its label, as Fruit & Veg > Manage does. Only ever
     * called from a click; applying a price never changes the country.
     */
    public function useInvoiceCountry(DynamisPricingLine $line): void
    {
        $productId = $line->deliveryItem?->product_id;
        $product = $productId ? Product::find($productId) : null;
        if (! $product) {
            throw new InvalidArgumentException('Choose the till product for this line first.');
        }

        $country = $this->originCountry($line->origin);
        if (! $country) {
            throw new InvalidArgumentException("The invoice origin \"{$line->origin}\" is not in the country list.");
        }

        VegDetails::upsertForProduct((string) $product->CODE, ['countryCode' => $country['id']]);
        VegPrintQueue::addToQueue((string) $product->CODE, 'country_updated');
    }

    /**
     * The shop's country for an invoice origin code ("FR"), or null when the
     * code is missing or not listed.
     *
     * @return array{id: int, name: string}|null
     */
    private function originCountry(?string $origin): ?array
    {
        $this->countriesByCode ??= Country::whereNotNull('code')->get(['id', 'name', 'code'])
            ->keyBy(fn (Country $c) => strtoupper($c->code));

        $country = $this->countriesByCode->get(strtoupper(trim((string) $origin)));

        return $country ? ['id' => (int) $country->id, 'name' => (string) $country->name] : null;
    }

    private function remember(DynamisPricingLine $line, string $mode, ?float $unitsPerBox, ?float $kgPerBox): void
    {
        // The invoice fixes these two; a typed figure only counts for the others.
        $unitsPerBox = $line->unit === 'PCE' ? $line->units_per_box : $unitsPerBox;
        $kgPerBox = $line->unit === 'KG' ? $line->kg_per_box : $kgPerBox;

        $line->update([
            'pricing_mode' => $mode,
            'units_per_box' => $unitsPerBox,
            'kg_per_box' => $kgPerBox,
        ]);

        DynamisProductLink::where('dynamis_code', $line->dynamis_code)->update([
            'pricing_mode' => $mode,
            'units_per_box' => $line->unit === 'PCE' ? null : $unitsPerBox,
            'kg_per_box' => $line->unit === 'KG' ? null : $kgPerBox,
        ]);
    }
}
