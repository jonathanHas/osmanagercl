<?php

namespace Tests\Unit;

use App\Services\DynamisXlsxParserService;
use Tests\TestCase;

class DynamisXlsxParserServiceTest extends TestCase
{
    private DynamisXlsxParserService $parser;

    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DynamisXlsxParserService;
        $this->fixturePath = __DIR__.'/../Fixtures/dynamis_historique_sample.xlsx';
    }

    public function test_parses_fixture_successfully(): void
    {
        $result = $this->parser->parse($this->fixturePath);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['data']['items']);
        $this->assertSame('Dynamis', $result['data']['supplier']);
        $this->assertSame('1044839', $result['data']['metadata']['order_number']);
    }

    public function test_separates_transport_row_into_costs(): void
    {
        $result = $this->parser->parse($this->fixturePath);

        $costItems = $result['data']['costs']['items'];
        $this->assertCount(1, $costItems);
        $this->assertSame('DIV0010', $costItems[0]['code']);
        $this->assertGreaterThan(0, $result['data']['costs']['total']);

        foreach ($result['data']['items'] as $item) {
            $this->assertNotSame('DIV0010', $item['code']);
        }
    }

    public function test_kilo_row_is_weight_based_with_correct_per_unit(): void
    {
        $result = $this->parser->parse($this->fixturePath);

        $apple = collect($result['data']['items'])
            ->first(fn ($i) => $i['code'] === 'POM0481');

        $this->assertNotNull($apple, 'Expected POM0481 (APPLE ARIANE BIO) in fixture');
        $this->assertTrue($apple['is_weight_based']);
        $this->assertSame('kg', $apple['weight_unit']);
        $this->assertEqualsWithDelta(14.0, $apple['total_weight'], 0.01);
        $this->assertEqualsWithDelta(14.0, $apple['weight_per_unit'], 0.01);
        $this->assertSame(1, $apple['total_ordered_units']);
        $this->assertSame(1, $apple['total_delivered_units']);
        $this->assertEqualsWithDelta(1.94, $apple['unit_cost'], 0.01);
    }

    public function test_count_row_uses_pieces_column(): void
    {
        $result = $this->parser->parse($this->fixturePath);

        $avocado = collect($result['data']['items'])
            ->first(fn ($i) => $i['code'] === 'AVO0064');

        $this->assertNotNull($avocado, 'Expected AVO0064 (AVOCADO HASS) in fixture');
        $this->assertFalse($avocado['is_weight_based']);
        $this->assertNull($avocado['weight_per_unit']);
        $this->assertGreaterThan(0, $avocado['total_ordered_units']);
    }

    public function test_totals_match_line_sum(): void
    {
        $result = $this->parser->parse($this->fixturePath);

        $itemsSum = array_sum(array_column($result['data']['items'], 'line_total'));
        $costsSum = $result['data']['costs']['total'];

        $this->assertEqualsWithDelta(
            round($itemsSum, 2),
            $result['data']['totals']['products_calculated'],
            0.01
        );

        $this->assertEqualsWithDelta(
            round($itemsSum + $costsSum, 2),
            $result['data']['totals']['grand_calculated'],
            0.01
        );
    }

    public function test_convert_to_delivery_items_has_expected_shape(): void
    {
        $parsed = $this->parser->parse($this->fixturePath);
        $items = $this->parser->convertToDeliveryItems($parsed);

        $this->assertNotEmpty($items);
        $first = $items[0];

        foreach ([
            'Code', 'Product', 'Total_Ordered_Units', 'Total_Delivered_Units',
            'Unit_Cost', 'Price', 'Value', 'Tax', 'Case_Size',
            'is_weight_based', 'weight_per_unit', 'weight_unit', 'total_weight',
            'order_number',
        ] as $key) {
            $this->assertArrayHasKey($key, $first, "Missing key {$key}");
        }

        $this->assertSame('1044839', $first['order_number']);
    }

    public function test_missing_file_returns_failure(): void
    {
        $result = $this->parser->parse('/tmp/does-not-exist-dynamis.xlsx');

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
    }
}
