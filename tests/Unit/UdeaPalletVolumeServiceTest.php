<?php

namespace Tests\Unit;

use App\Models\UdeaProductCard;
use App\Services\UdeaPalletVolumeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UdeaPalletVolumeServiceTest extends TestCase
{
    use RefreshDatabase;

    private UdeaPalletVolumeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.udea', [
            'base_uri' => 'https://test.udea.nl',
            'username' => 'test@example.com',
            'password' => 'secret',
            'timeout' => 30,
            'rate_limit_delay' => 0,
            'cache_ttl' => 3600,
        ]);

        $this->service = new UdeaPalletVolumeService;
    }

    /**
     * Builds a basket row in the same shape Udea emits: the "Productnummer" label sits
     * before the pallet span, which is what makes naive marker-anchored parsing pair a
     * row with the next row's code.
     */
    private function row(string $code, string $sve, string $volume, string $qty = '1'): string
    {
        return <<<HTML
        <tr class="cart-row">
            <td><h3>Some product</h3><span class="item-meta">Productnummer <b>{$code}</b></span></td>
            <td><input type="text" class="amount_{$code} qty-cart-input product-quantity"
                       value="{$qty}"></td>
            <td class="valign-middle palet-col">
                <span class="hidden product-pallet-data" data-sve="{$sve}" data-volume="{$volume}" data-volume-percent="0"></span>
            </td>
        </tr>
        HTML;
    }

    private function cart(string $rows, ?string $palletData = null): string
    {
        $palletData ??= '<span class="hidden" id="pallet-data"
                               data-volume-euro-pallet="250"
                               data-volume-block-pallet="360"></span>';

        return '<html><body>Mijn account <table>'.$rows.'</table>'.$palletData.'</body></html>';
    }

    public function test_parses_sve_and_volume_from_a_basket_row(): void
    {
        $result = $this->service->parseCart($this->cart($this->row('5004482', '10', '0.65')));

        $this->assertCount(1, $result['rows']);
        $this->assertSame('5004482', $result['rows'][0]['supplier_code']);
        $this->assertSame(10.0, $result['rows'][0]['sve']);
        $this->assertSame(0.65, $result['rows'][0]['unit_volume']);
    }

    /**
     * Weight-priced goods carry fractional sve values. An integer column or int cast
     * would silently turn 0.22 into 0 and quietly wreck every calculation downstream.
     */
    public function test_fractional_sve_values_survive_parsing(): void
    {
        $result = $this->service->parseCart($this->cart(
            $this->row('45006', '0.22', '0.13').
            $this->row('4471', '4.5', '2.5').
            $this->row('32369', '1.44', '0.28')
        ));

        $this->assertSame(0.22, $result['rows'][0]['sve']);
        $this->assertSame(4.5, $result['rows'][1]['sve']);
        $this->assertSame(1.44, $result['rows'][2]['sve']);
    }

    public function test_fractional_values_survive_the_database_round_trip(): void
    {
        UdeaProductCard::create([
            'supplier_code' => '45006',
            'pallet_sve' => 0.22,
            'pallet_unit_volume' => 0.13,
            'pallet_scraped_at' => now(),
        ]);

        $card = UdeaProductCard::where('supplier_code', '45006')->first();

        $this->assertSame(0.22, $card->pallet_sve);
        $this->assertSame(0.13, $card->pallet_unit_volume);
        $this->assertIsFloat($card->pallet_sve, 'A decimal: cast would return a string and break arithmetic.');
        $this->assertEqualsWithDelta(0.858, $card->palletVolumeFor(30), 0.0001);
    }

    /**
     * Each row must keep its own code. The label precedes the pallet span in the markup,
     * so an off-by-one here pairs every product with its neighbour's figures.
     */
    public function test_each_row_keeps_its_own_supplier_code(): void
    {
        $result = $this->service->parseCart($this->cart(
            $this->row('1111', '10', '0.65').
            $this->row('2222', '12', '0.33').
            $this->row('3333', '6', '0.5')
        ));

        $this->assertSame(['1111', '2222', '3333'], array_column($result['rows'], 'supplier_code'));
        $this->assertSame([10.0, 12.0, 6.0], array_column($result['rows'], 'sve'));
    }

    public function test_parses_capacities_when_attributes_span_lines_and_are_reordered(): void
    {
        $result = $this->service->parseCart($this->cart(
            $this->row('1111', '1', '1'),
            '<span id="pallet-data"
                   data-volume-block-pallet="360"
                   data-volume-euro-pallet="250"
                   class="hidden"></span>'
        ));

        $this->assertSame(250.0, $result['capacities']['euro']);
        $this->assertSame(360.0, $result['capacities']['block']);
        $this->assertNull($result['capacity_warning']);
    }

    public function test_warns_when_site_capacities_drift_from_config(): void
    {
        $result = $this->service->parseCart($this->cart(
            $this->row('1111', '1', '1'),
            '<span id="pallet-data" data-volume-euro-pallet="300" data-volume-block-pallet="360"></span>'
        ));

        $this->assertStringContainsString('300', $result['capacity_warning']);
    }

    /**
     * Golden master: the real 298-line basket captured on 2026-09-09. The site displayed
     * 188.24% of a Europallet and 130.80% of a blockpallet for this basket, and these
     * numbers reproduce that exactly, pinning the per-line rounding semantics.
     */
    public function test_golden_master_reproduces_the_real_basket_totals(): void
    {
        $path = base_path('tests/Fixtures/udea-cart-298-lines.json');

        if (! file_exists($path)) {
            $this->markTestSkipped('Golden-master fixture not present.');
        }

        $rows = json_decode(file_get_contents($path), true);

        $this->assertCount(298, $rows);
        $this->assertSame(719.0, array_sum(array_column($rows, 'quantity')));

        $total = 0.0;
        foreach ($rows as $row) {
            $total += $row['sve'] * $row['unit_volume'] * $row['quantity'];
        }
        $this->assertEqualsWithDelta(470.6612, $total, 0.0001);

        // Udea rounds each line to 2dp before summing (order.js:1556) - summing first and
        // rounding once gives 188.26, not the 188.24 the site shows.
        $euroPct = 0.0;
        $blockPct = 0.0;
        foreach ($rows as $row) {
            $lineVolume = $row['sve'] * $row['unit_volume'] * $row['quantity'];
            $euroPct += round($lineVolume / 250 * 100, 2);
            $blockPct += round($lineVolume / 360 * 100, 2);
        }

        $this->assertEqualsWithDelta(188.24, $euroPct, 0.01);
        $this->assertEqualsWithDelta(130.80, $blockPct, 0.01);
    }

    public function test_sync_does_not_touch_scraped_at_or_existing_price_data(): void
    {
        $card = UdeaProductCard::create([
            'supplier_code' => '5004482',
            'case_price' => '14,10',
            'units_per_case' => 10,
            'scraped_at' => now()->subDays(3),
        ]);
        $originalScrapedAt = $card->scraped_at;

        $service = $this->serviceReturning($this->cart($this->row('5004482', '10', '0.65')));
        $result = $service->sync();

        $card->refresh();

        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(10.0, $card->pallet_sve);
        $this->assertSame(0.65, $card->pallet_unit_volume);
        $this->assertNotNull($card->pallet_scraped_at);
        $this->assertSame('14,10', $card->case_price, 'Price data must survive a pallet sync.');
        $this->assertEquals($originalScrapedAt->timestamp, $card->scraped_at->timestamp,
            'scraped_at drives the price-tier staleness check and must not be touched.');
    }

    public function test_sync_creates_rows_for_unseen_codes(): void
    {
        $service = $this->serviceReturning($this->cart($this->row('9999999', '6', '0.11')));
        $result = $service->sync();

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('udea_product_cards', ['supplier_code' => '9999999']);

        $card = UdeaProductCard::where('supplier_code', '9999999')->first();
        $this->assertNull($card->scraped_at, 'A pallet-only row must not look price-fresh.');
    }

    public function test_dry_run_writes_nothing(): void
    {
        $service = $this->serviceReturning($this->cart($this->row('5004482', '10', '0.65')));
        $result = $service->sync(dryRun: true);

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseCount('udea_product_cards', 0);
    }

    public function test_volumes_for_codes_returns_volume_per_order_unit(): void
    {
        UdeaProductCard::create(['supplier_code' => '5004482', 'pallet_sve' => 10, 'pallet_unit_volume' => 0.65, 'pallet_scraped_at' => now()]);
        UdeaProductCard::create(['supplier_code' => '45006', 'pallet_sve' => 0.22, 'pallet_unit_volume' => 0.13, 'pallet_scraped_at' => now()]);
        // No pallet data - must be absent rather than present as zero.
        UdeaProductCard::create(['supplier_code' => '9999', 'scraped_at' => now()]);

        $volumes = $this->service->volumesForCodes(['5004482', '45006', '9999', null, '  ']);

        $this->assertEqualsWithDelta(6.5, $volumes['5004482'], 0.0001);
        $this->assertEqualsWithDelta(0.0286, $volumes['45006'], 0.0001);
        $this->assertArrayNotHasKey('9999', $volumes->all());
        $this->assertCount(2, $volumes);
    }

    public function test_volumes_for_codes_handles_an_empty_list(): void
    {
        $this->assertTrue($this->service->volumesForCodes([])->isEmpty());
    }

    public function test_capacity_for_matches_udea_pallet_maths(): void
    {
        $this->assertSame(250.0, $this->service->capacityFor(1, 0));
        $this->assertSame(360.0, $this->service->capacityFor(0, 1));
        $this->assertSame(860.0, $this->service->capacityFor(2, 1));
        $this->assertSame(0.0, $this->service->capacityFor(0, 0));
    }

    /**
     * The order page's running total must agree with the Udea basket for the same lines.
     * Same golden master as the parse test, driven through the summarise() API the Blade
     * component's JS mirrors.
     */
    public function test_summarise_reproduces_the_real_basket_fill_percentages(): void
    {
        $path = base_path('tests/Fixtures/udea-cart-298-lines.json');

        if (! file_exists($path)) {
            $this->markTestSkipped('Golden-master fixture not present.');
        }

        $lines = array_map(fn ($row) => [
            'volume_per_unit' => $row['sve'] * $row['unit_volume'],
            'quantity' => $row['quantity'],
        ], json_decode(file_get_contents($path), true));

        $euro = $this->service->summarise($lines, $this->service->capacityFor(1, 0));
        $block = $this->service->summarise($lines, $this->service->capacityFor(0, 1));

        $this->assertEqualsWithDelta(470.6612, $euro['volume'], 0.0001);
        $this->assertEqualsWithDelta(188.24, $euro['percent'], 0.01);
        $this->assertEqualsWithDelta(130.80, $block['percent'], 0.01);
        $this->assertSame(298, $euro['counted']);
    }

    public function test_summarise_ignores_zero_and_negative_quantities(): void
    {
        $result = $this->service->summarise([
            ['volume_per_unit' => 6.5, 'quantity' => 2],
            ['volume_per_unit' => 6.5, 'quantity' => 0],
            ['volume_per_unit' => 6.5, 'quantity' => -3],
        ], 250.0);

        $this->assertSame(1, $result['counted']);
        $this->assertEqualsWithDelta(13.0, $result['volume'], 0.0001);
    }

    public function test_summarise_reports_zero_percent_when_no_pallets_selected(): void
    {
        $result = $this->service->summarise([['volume_per_unit' => 6.5, 'quantity' => 2]], 0.0);

        $this->assertSame(0.0, $result['percent']);
        $this->assertEqualsWithDelta(13.0, $result['volume'], 0.0001, 'Volume is still known without a pallet selection.');
    }

    /**
     * Stubs fetchCartHtml() so sync() can be exercised without HTTP.
     */
    private function serviceReturning(string $html): UdeaPalletVolumeService
    {
        return new class($html) extends UdeaPalletVolumeService
        {
            public function __construct(private string $html)
            {
                parent::__construct();
            }

            public function fetchCartHtml(): string
            {
                return $this->html;
            }
        };
    }
}
