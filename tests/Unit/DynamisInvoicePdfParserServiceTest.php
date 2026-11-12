<?php

namespace Tests\Unit;

use App\Services\DynamisInvoicePdfParserService;
use PHPUnit\Framework\TestCase;

class DynamisInvoicePdfParserServiceTest extends TestCase
{
    private function parse(?callable $alter = null): array
    {
        $text = file_get_contents(__DIR__.'/../Fixtures/dynamis_invoice_FC10006631.txt');

        return (new DynamisInvoicePdfParserService)->parseText($alter ? $alter($text) : $text);
    }

    private function item(array $parsed, string $code): array
    {
        return collect($parsed['data']['items'])->firstWhere('code', $code);
    }

    public function test_reads_every_line_and_the_lines_add_up_to_the_goods_total(): void
    {
        $parsed = $this->parse();

        $this->assertTrue($parsed['success']);
        $this->assertSame([], $parsed['warnings']);
        $this->assertCount(41, $parsed['data']['items']);
        $this->assertSame(1022.44, $parsed['data']['totals']['gross']);
        $this->assertSame(1022.44, $parsed['data']['totals']['lines_sum']);
        $this->assertTrue($parsed['data']['totals']['totals_match']);
        $this->assertSame(0.0, $parsed['data']['totals']['shipping']);
        $this->assertSame(['invoice_number' => 'FC10006631', 'invoice_date' => '2026-11-09'], $parsed['data']['metadata']);
    }

    public function test_reads_per_kg_box_and_piece_lines(): void
    {
        $parsed = $this->parse();

        $banana = $this->item($parsed, 'BAN004');
        $this->assertSame('KG', $banana['unit']);
        $this->assertSame(18.5, $banana['quantity']);
        $this->assertSame(1.55, $banana['net_unit_price']);
        $this->assertSame('DO', $banana['origin']);

        $hass = $this->item($parsed, 'AVO012');
        $this->assertSame('AVOCADO HASS SIZE 22 SPAIN CLASS II', $hass['product']);
        $this->assertSame('COL', $hass['unit']);
        $this->assertSame(3, $hass['packages']);
        $this->assertSame(22.07, $hass['net_unit_price']);
        $this->assertSame(66.21, $hass['line_total']);

        $chives = $this->item($parsed, 'CIB001');
        $this->assertSame('PCE', $chives['unit']);
        $this->assertSame(10.0, $chives['quantity']);
    }

    public function test_joins_a_wrapped_description(): void
    {
        $this->assertSame(
            'TOMATO CHERRY ON THE VINE DEMETER 3KG SPAIN CLASS II',
            $this->item($this->parse(), 'TOM010')['product']
        );
    }

    public function test_barcoded_lines_carry_their_ean_and_produce_lines_do_not(): void
    {
        $parsed = $this->parse();

        $this->assertSame('3520836120354', $this->item($parsed, '26184')['ean']);
        $this->assertSame('GRANULATED BLOND CANE SUGAR (5KG) PHILIA', $this->item($parsed, '42489')['product']);
        $this->assertSame('3520831630230', $this->item($parsed, '42489')['ean']);
        $this->assertNull($this->item($parsed, 'POM033')['ean']);
        $this->assertCount(2, array_filter(array_column($parsed['data']['items'], 'ean')));
    }

    public function test_reads_the_shipping_fee_from_under_its_heading(): void
    {
        $parsed = $this->parse(function (string $text) {
            $lines = explode("\n", $text);
            foreach ($lines as $i => $line) {
                if (str_contains($line, 'Gross Total Excl.') && str_contains($line, 'Shipping Fees')) {
                    $at = mb_strpos($line, 'Shipping Fees');
                    $n = $i + 1;
                    while (trim($lines[$n]) === '') {
                        $n++;
                    }
                    $lines[$n] = mb_substr($lines[$n], 0, $at).'  135,00 €'.mb_substr($lines[$n], $at + 10);
                }
            }

            return implode("\n", $lines);
        });

        $this->assertSame(135.0, $parsed['data']['totals']['shipping']);
        $this->assertSame(1022.44, $parsed['data']['totals']['gross']);
    }

    public function test_warns_when_a_line_is_missing(): void
    {
        $parsed = $this->parse(fn (string $text) => preg_replace('/^.*POI009.*$/m', '', $text));

        $this->assertTrue($parsed['success']);
        $this->assertCount(40, $parsed['data']['items']);
        $this->assertFalse($parsed['data']['totals']['totals_match']);
        $this->assertStringContainsString('€992.67', $parsed['warnings'][0]);
    }

    public function test_fails_on_text_that_is_not_a_dynamis_invoice(): void
    {
        $parsed = (new DynamisInvoicePdfParserService)->parseText("Order confirmation\nNothing to see here\n");

        $this->assertFalse($parsed['success']);
        $this->assertNotEmpty($parsed['errors']);
    }

    public function test_docket_rows_count_kg_lines_in_boxes_and_the_rest_in_their_own_unit(): void
    {
        $service = new DynamisInvoicePdfParserService;
        $rows = collect($service->convertToDeliveryItems($this->parse()))->keyBy('Code');

        $this->assertSame(4, $rows['PAT003']['Total_Delivered_Units']);
        $this->assertTrue($rows['PAT003']['is_weight_based']);
        $this->assertSame(6.0, $rows['PAT003']['weight_per_unit']);
        $this->assertSame(24.0, $rows['PAT003']['total_weight']);

        $this->assertSame(3, $rows['AVO012']['Total_Delivered_Units']);
        $this->assertFalse($rows['AVO012']['is_weight_based']);
        $this->assertSame(12, $rows['26184']['Total_Delivered_Units']);
        $this->assertSame('BL10010660', $rows['AVO012']['order_number']);
    }
}
