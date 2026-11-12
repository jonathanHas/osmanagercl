<?php

namespace Tests\Unit;

use App\Services\DynamisPricingService as Pricing;
use PHPUnit\Framework\TestCase;

/**
 * The spreadsheet formula and what the description says is in a box.
 */
class DynamisPricingFormulaTest extends TestCase
{
    public function test_guide_is_cost_times_transport_times_markup(): void
    {
        $this->assertEqualsWithDelta(4.122, Pricing::guidePrice(2.29, 1.2, 1.5), 0.0001);
        $this->assertEqualsWithDelta(11.124 * 1.23, Pricing::guidePrice(6.18, 1.2, 1.5, 0.23), 0.0001);
    }

    public function test_per_kg_line_sold_per_kg_is_the_guide(): void
    {
        // Pear Conference: 13 kg at 2.29/kg
        $this->assertSame(4.12, Pricing::suggestedPrice('per_kg', 2.29, 13, 1, null, 13, 1.2, 1.5));
    }

    public function test_box_line_sold_each_divides_the_box_by_the_count(): void
    {
        // Avocado Hass: 3 boxes at 22.07 a box, 22 in a box
        $this->assertSame(1.81, Pricing::suggestedPrice('each', 22.07, 3, 3, 22, null, 1.2, 1.5));
    }

    public function test_per_kg_line_sold_each_divides_the_box_weight_by_the_count(): void
    {
        // Kiwi: one 10 kg box at 4.19/kg, counted as 90 fruit
        $this->assertSame(0.84, Pricing::suggestedPrice('each', 4.19, 10, 1, 90, 10, 1.2, 1.5));
        // Two 6 kg boxes: the count is still per box
        $this->assertSame(0.77, Pricing::suggestedPrice('each', 3.85, 12, 2, 54, 6, 1.2, 1.5));
    }

    public function test_box_line_sold_per_kg_divides_by_the_box_weight(): void
    {
        // Persimmon: 13.72 a box of about 4.5 kg
        $this->assertSame(5.49, Pricing::suggestedPrice('per_kg', 13.72, 2, 2, null, 4.5, 1.2, 1.5));
    }

    public function test_piece_line_sold_each_is_the_guide(): void
    {
        // Chives: 10 bunches at 1.21
        $this->assertSame(2.18, Pricing::suggestedPrice('each', 1.21, 10, 1, 10, null, 1.2, 1.5));
    }

    public function test_no_suggestion_without_the_divisor(): void
    {
        $this->assertNull(Pricing::suggestedPrice('each', 11.68, 1, 1, null, null, 1.2, 1.5));
        $this->assertNull(Pricing::suggestedPrice('per_kg', 11.68, 1, 1, 14, null, 1.2, 1.5));
        $this->assertNull(Pricing::suggestedPrice('skip', 11.68, 1, 1, 14, 4, 1.2, 1.5));
    }

    public function test_reads_what_is_in_a_box_from_the_description(): void
    {
        $this->assertSame(
            ['units_per_box' => 12.0, 'kg_per_box' => null, 'count_source' => 'pieces'],
            Pricing::readBoxContents('LONG CUCUMBER 12 PIECES SPAIN CLASS II')
        );
        $this->assertSame(
            ['units_per_box' => 22.0, 'kg_per_box' => null, 'count_source' => 'size'],
            Pricing::readBoxContents('AVOCADO HASS SIZE 22 SPAIN CLASS II')
        );
        $this->assertSame(
            ['units_per_box' => null, 'kg_per_box' => 4.5, 'count_source' => null],
            Pricing::readBoxContents('ROJO BRILLANTE PERSIMON ABOUT 4.5KG SPAIN CLASS II')
        );
        $this->assertSame(
            ['units_per_box' => null, 'kg_per_box' => null, 'count_source' => null],
            Pricing::readBoxContents('AVOCADO BACON SPAIN CLASS II')
        );
    }

    public function test_a_size_range_is_not_a_count(): void
    {
        $this->assertNull(Pricing::readBoxContents('ORANGE SIZE 3/4 10KG SPAIN CLASS II')['units_per_box']);
        $this->assertNull(Pricing::readBoxContents('GREEN COURGETTE SIZE 14-21 6KG ITALY CLASS II')['units_per_box']);
        $this->assertNull(Pricing::readBoxContents('APPLE JULIET TRAY SIZE 150/180 8KG CLASS II')['units_per_box']);
    }

    public function test_product_words_drop_pack_class_and_origin(): void
    {
        $this->assertSame('WHITE GARLIC', Pricing::productWords('WHITE GARLIC SIZE 55/65 5KG SPAIN CLASS II'));
        $this->assertSame('BLUEBERRY', Pricing::productWords('BLUEBERRY 8 TRAYS 125G ARGENTINE CLASS II'));
        $this->assertSame('BANANA CAVENDISH', Pricing::productWords('BANANA CAVENDISH FAIR TRADE DOMINICAN REPUBLIC CLASS II'));
    }
}
