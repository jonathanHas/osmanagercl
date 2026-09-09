<?php

namespace App\Services;

use App\Models\UdeaProductCard;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Reads Udea's per-product pallet volume figures out of the logged-in basket page
 * and stores them against udea_product_cards.
 *
 * Udea exposes this data nowhere else: product detail pages, search listings and the
 * site JS all lack it, and there is no API. Each basket row carries a hidden span:
 *
 *     <span class="hidden product-pallet-data" data-sve="10" data-volume="0.65" ...>
 *
 * The figures are per-product constants, independent of the ordered quantity, so a
 * single pass over a basket containing the full range captures them permanently.
 *
 * This is a deliberate sibling of UdeaScrapingService rather than an extension of it:
 * that class is large, reachable from live order pages, and covered only by ageing
 * tests. This one does exactly one job and only ever issues GETs.
 */
class UdeaPalletVolumeService
{
    /**
     * A basket holding the full Udea range is a very large page (~7.4KB per line, so
     * ~15MB for 2,000 products). The configured Udea timeout is tuned for small search
     * requests and is nowhere near enough.
     */
    private const CART_TIMEOUT = 300;

    private Client $client;

    private array $config;

    private bool $authenticated = false;

    public function __construct()
    {
        $this->config = [
            'base_uri' => config('services.udea.base_uri', 'https://www.udea.nl'),
            'username' => config('services.udea.username'),
            'password' => config('services.udea.password'),
        ];

        $this->client = new Client([
            'base_uri' => $this->config['base_uri'],
            'timeout' => self::CART_TIMEOUT,
            'cookies' => true,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
        ]);
    }

    /**
     * Fetch, parse and persist. Returns a summary for the caller to report.
     *
     * @return array{found:int,created:int,updated:int,skipped:int,errors:array<int,string>,capacities:array{euro:float|null,block:float|null},capacity_warning:string|null,total_volume:float}
     */
    public function sync(bool $dryRun = false): array
    {
        $parsed = $this->parseCart($this->fetchCartHtml());

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($parsed['rows'] as $row) {
            if ($row['supplier_code'] === null) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                UdeaProductCard::where('supplier_code', $row['supplier_code'])->exists()
                    ? $updated++
                    : $created++;

                continue;
            }

            try {
                $card = UdeaProductCard::firstOrNew(['supplier_code' => $row['supplier_code']]);
                $existed = $card->exists;

                // Only the pallet columns are written. scraped_at is deliberately left
                // alone: it drives the 30-day price-tier staleness check elsewhere, and
                // touching it here would suppress legitimate price re-scrapes.
                $card->pallet_sve = $row['sve'];
                $card->pallet_unit_volume = $row['unit_volume'];
                $card->pallet_scraped_at = now();
                $card->save();

                $existed ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $errors[] = "{$row['supplier_code']}: {$e->getMessage()}";
            }
        }

        return [
            'found' => count($parsed['rows']),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'capacities' => $parsed['capacities'],
            'capacity_warning' => $parsed['capacity_warning'],
            'total_volume' => $parsed['total_volume'],
        ];
    }

    /**
     * Log in and GET the basket page.
     *
     * @throws RuntimeException when authentication or the fetch fails
     */
    public function fetchCartHtml(): string
    {
        $this->authenticate();

        try {
            $response = $this->client->get('/orders/cart');
        } catch (GuzzleException $e) {
            throw new RuntimeException('Could not reach the Udea basket: '.$e->getMessage(), 0, $e);
        }

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Udea basket returned HTTP '.$response->getStatusCode().'.');
        }

        $html = (string) $response->getBody();

        // A logged-out response is still a 200, so check we actually got the account page.
        if (! str_contains($html, 'Uitloggen') && ! str_contains($html, 'Mijn account')) {
            throw new RuntimeException('Udea basket page did not come back logged in - check UDEA_USERNAME / UDEA_PASSWORD.');
        }

        return $html;
    }

    /**
     * Extract the pallet figures from a basket page.
     *
     * @return array{rows:array<int,array{supplier_code:string|null,sve:float,unit_volume:float}>,capacities:array{euro:float|null,block:float|null},capacity_warning:string|null,total_volume:float}
     */
    public function parseCart(string $html): array
    {
        $capacities = $this->extractCapacities($html);
        $rows = [];
        $totalVolume = 0.0;

        // Split on row boundaries rather than matching whole <tr>...</tr> blocks with a
        // non-greedy pattern: on a full basket this string is ~15MB, and a pattern able
        // to span rows backtracks badly. Splitting also keeps each row's fields together
        // - the "Productnummer" label sits *before* the pallet span within the row, so
        // anchoring on the span alone pairs each row with the next row's code.
        foreach (preg_split('/<tr[\s>]/', $html) as $row) {
            if (! str_contains($row, 'product-pallet-data')) {
                continue;
            }
            if (! preg_match('/data-sve="([0-9.]+)"/', $row, $sveMatch)) {
                continue;
            }
            if (! preg_match('/data-volume="([0-9.]+)"/', $row, $volMatch)) {
                continue;
            }

            $sve = (float) $sveMatch[1];
            $unitVolume = (float) $volMatch[1];

            $rows[] = [
                'supplier_code' => $this->extractSupplierCode($row),
                'sve' => $sve,
                'unit_volume' => $unitVolume,
            ];

            $totalVolume += $sve * $unitVolume * $this->extractQuantity($row);
        }

        return [
            'rows' => $rows,
            'capacities' => $capacities['values'],
            'capacity_warning' => $capacities['warning'],
            'total_volume' => round($totalVolume, 4),
        ];
    }

    /**
     * Pallet volume for a given product code and quantity, or null if not captured yet.
     * This is the long-term read API the harvest exists to enable.
     */
    public function palletFractionFor(string $supplierCode, float $qty): ?float
    {
        return UdeaProductCard::where('supplier_code', $supplierCode)
            ->first()
            ?->palletVolumeFor($qty);
    }

    /**
     * Pallet volume per ORDER UNIT (sve * volume), keyed by supplier code.
     *
     * An order unit is what Udea counts in the basket: a case for case-bought products,
     * a single otherwise. Multiplying by the ordered quantity gives the line's volume,
     * which is exactly how the Udea basket calculates it.
     *
     * @param  iterable<int, string|null>  $supplierCodes
     * @return \Illuminate\Support\Collection<string, float>
     */
    public function volumesForCodes(iterable $supplierCodes): Collection
    {
        $codes = collect($supplierCodes)
            ->filter()
            ->map(fn ($code) => trim((string) $code))
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return collect();
        }

        return UdeaProductCard::whereIn('supplier_code', $codes->all())
            ->whereNotNull('pallet_sve')
            ->whereNotNull('pallet_unit_volume')
            ->get()
            ->mapWithKeys(fn (UdeaProductCard $card) => [
                (string) $card->supplier_code => round((float) $card->pallet_sve * (float) $card->pallet_unit_volume, 6),
            ]);
    }

    /**
     * Total pallet volume and fill percentage for a set of order lines.
     *
     * Udea rounds each line's percentage to 2dp before summing (order.js:1556); summing the
     * raw volumes and rounding once at the end gives a slightly different figure to the one
     * their page shows. The JS in the pallet-summary component mirrors this exactly.
     *
     * @param  iterable<int, array{volume_per_unit: float, quantity: float}>  $lines
     * @return array{volume:float,percent:float,capacity:float,counted:int}
     */
    public function summarise(iterable $lines, float $capacity): array
    {
        $volume = 0.0;
        $percent = 0.0;
        $counted = 0;

        foreach ($lines as $line) {
            $qty = (float) ($line['quantity'] ?? 0);
            $perUnit = (float) ($line['volume_per_unit'] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $lineVolume = $perUnit * $qty;
            $volume += $lineVolume;
            $counted++;

            if ($capacity > 0) {
                $percent += round($lineVolume / $capacity * 100, 2);
            }
        }

        return [
            'volume' => round($volume, 4),
            'percent' => round($percent, 2),
            'capacity' => $capacity,
            'counted' => $counted,
        ];
    }

    /**
     * Total capacity for a pallet selection, per Udea's own calculation.
     */
    public function capacityFor(int $euroPallets, int $blockPallets): float
    {
        $pallet = config('suppliers.external_links.udea.pallet', ['euro' => 250, 'block' => 360]);

        return $euroPallets * (float) $pallet['euro'] + $blockPallets * (float) $pallet['block'];
    }

    /**
     * Mirrors UdeaScrapingService::ensureAuthenticated(): GET the login page, then POST
     * credentials. Udea's form carries no CSRF token and answers a good login with a 302.
     */
    private function authenticate(): void
    {
        if ($this->authenticated) {
            return;
        }

        if (blank($this->config['username']) || blank($this->config['password'])) {
            throw new RuntimeException('Udea credentials are not configured (UDEA_USERNAME / UDEA_PASSWORD).');
        }

        try {
            $this->client->get('/users');

            $response = $this->client->post('/users/login', [
                'form_params' => [
                    'email' => $this->config['username'],
                    'password' => $this->config['password'],
                    'remember-me' => '1',
                ],
                'allow_redirects' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Could not reach the Udea login page: '.$e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();

        if ($status !== 302 && $status !== 200) {
            Log::warning('Udea pallet sync login failed', ['status' => $status]);

            throw new RuntimeException("Udea login failed (HTTP {$status}).");
        }

        $this->authenticated = true;
    }

    /**
     * Read the pallet capacities off the page and compare them with config. The attributes
     * are split across newlines in the markup, hence the /s and the tolerance of ordering.
     *
     * @return array{values:array{euro:float|null,block:float|null},warning:string|null}
     */
    private function extractCapacities(string $html): array
    {
        $euro = preg_match('/data-volume-euro-pallet="([0-9.]+)"/s', $html, $m) ? (float) $m[1] : null;
        $block = preg_match('/data-volume-block-pallet="([0-9.]+)"/s', $html, $m) ? (float) $m[1] : null;

        $configured = config('suppliers.external_links.udea.pallet', ['euro' => 250, 'block' => 360]);
        $warning = null;

        if ($euro !== null && (float) $configured['euro'] !== $euro) {
            $warning = "Europallet capacity on the site is {$euro}, config says {$configured['euro']}.";
        }
        if ($block !== null && (float) $configured['block'] !== $block) {
            $warning = trim(($warning ?? '')." Blockpallet capacity on the site is {$block}, config says {$configured['block']}.");
        }

        return ['values' => ['euro' => $euro, 'block' => $block], 'warning' => $warning];
    }

    /**
     * The visible "Productnummer 5004482" label is the supplier code we key on.
     */
    private function extractSupplierCode(string $segment): ?string
    {
        if (preg_match('/Productnummer[^0-9]{0,40}([0-9]{2,20})/s', $segment, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Ordered quantity for the row, used only for the reconciliation total.
     */
    private function extractQuantity(string $segment): float
    {
        if (preg_match('/class="amount_\d+[^"]*"\s+value="([0-9.]+)"/s', $segment, $m)) {
            return (float) $m[1];
        }

        return 0.0;
    }
}
