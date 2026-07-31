<?php

namespace Tests\Unit;

use App\Services\KitchenWholesaleService;
use Tests\TestCase;

/**
 * The wholesale price arithmetic is pure and never touches the database, so
 * this suite runs everywhere - including machines without pdo_sqlite.
 */
class KitchenWholesaleMarginTest extends TestCase
{
    public function test_ex_vat_strips_the_vat_from_an_inclusive_price(): void
    {
        // 100.00 net at 23% is 123.00 gross.
        $this->assertEqualsWithDelta(100.00, KitchenWholesaleService::exVat(123.00, 0.23), 0.0001);

        // Irish 13.5% and 9% rates.
        $this->assertEqualsWithDelta(100.00, KitchenWholesaleService::exVat(113.50, 0.135), 0.0001);
        $this->assertEqualsWithDelta(100.00, KitchenWholesaleService::exVat(109.00, 0.09), 0.0001);
    }

    public function test_ex_vat_passes_a_zero_rated_price_through_unchanged(): void
    {
        $this->assertSame(50.00, KitchenWholesaleService::exVat(50.00, 0.0));
    }

    public function test_inc_vat_is_the_inverse_of_ex_vat(): void
    {
        $gross = KitchenWholesaleService::incVat(KitchenWholesaleService::exVat(45.00, 0.23), 0.23);

        $this->assertEqualsWithDelta(45.00, $gross, 0.0001);
    }

    public function test_margin_is_measured_against_the_ex_vat_price(): void
    {
        // Matches the products page formula: (sell_ex_vat - cost) / sell_ex_vat.
        $this->assertEqualsWithDelta(40.0, KitchenWholesaleService::marginPercentage(100.00, 60.00), 0.0001);
        $this->assertEqualsWithDelta(0.0, KitchenWholesaleService::marginPercentage(60.00, 60.00), 0.0001);
    }

    public function test_margin_is_negative_when_the_price_is_below_cost(): void
    {
        $this->assertLessThan(0, KitchenWholesaleService::marginPercentage(50.00, 60.00));
    }

    public function test_margin_is_null_when_there_is_no_price_to_measure_against(): void
    {
        $this->assertNull(KitchenWholesaleService::marginPercentage(0.0, 60.00));
        $this->assertNull(KitchenWholesaleService::marginPercentage(-1.0, 60.00));
    }

    public function test_target_margin_price_round_trips_back_to_the_target(): void
    {
        $cost = 12.36;
        $rate = 0.23;

        $incVat = KitchenWholesaleService::priceForTargetMargin($cost, 35, $rate);
        $margin = KitchenWholesaleService::marginPercentage(
            KitchenWholesaleService::exVat($incVat, $rate),
            $cost
        );

        $this->assertEqualsWithDelta(35.0, $margin, 0.0001);
    }

    public function test_target_margin_of_zero_prices_at_cost_plus_vat(): void
    {
        $incVat = KitchenWholesaleService::priceForTargetMargin(10.00, 0, 0.23);

        $this->assertEqualsWithDelta(12.30, $incVat, 0.0001);
    }

    public function test_target_margin_of_one_hundred_percent_does_not_divide_by_zero(): void
    {
        // (1 - target/100) hits zero at 100, so the formula is clamped rather
        // than allowed to blow up or return INF.
        $this->assertSame(0.0, KitchenWholesaleService::priceForTargetMargin(10.00, 100, 0.23));
        $this->assertSame(0.0, KitchenWholesaleService::priceForTargetMargin(10.00, 150, 0.23));
    }

    public function test_target_margin_price_works_on_a_zero_vat_rate(): void
    {
        $incVat = KitchenWholesaleService::priceForTargetMargin(20.00, 50, 0.0);

        $this->assertEqualsWithDelta(40.00, $incVat, 0.0001);
    }
}
