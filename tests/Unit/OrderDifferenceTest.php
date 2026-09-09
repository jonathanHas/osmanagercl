<?php

namespace Tests\Unit;

use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The arithmetic behind "create an order from the difference" on the comparison page.
 *
 * differenceItemsFor() touches no database, so these run against unsaved models.
 * That is deliberate rather than merely convenient: the decimal casts return
 * strings on an unsaved model exactly as they do on a persisted one, so the
 * string-vs-float hazard is exercised for real here.
 */
class OrderDifferenceTest extends TestCase
{
    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrderService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function item(string $productId, float $quantity, int $caseUnits = 1, array $attributes = []): OrderItem
    {
        $cases = $caseUnits > 1 ? $quantity / $caseUnits : $quantity;

        return new OrderItem(array_merge([
            'product_id' => $productId,
            'final_quantity' => $quantity,
            'final_cases' => $cases,
            'case_units' => $caseUnits,
            'unit_cost' => 2.50,
            'review_priority' => 'standard',
            'context_data' => ['current_stock' => 4, 'weekly_sales' => [['label' => 'W1', 'units' => 3]]],
        ], $attributes));
    }

    /**
     * @param  array<int, OrderItem>  $items
     * @return Collection<string, OrderItem>
     */
    private function keyed(array $items): Collection
    {
        return collect($items)->keyBy('product_id');
    }

    public function test_product_the_other_order_does_not_cover_comes_across_in_full(): void
    {
        $diff = $this->service->differenceItemsFor(
            $this->keyed([$this->item('P1', 12)]),
            $this->keyed([$this->item('P2', 8)]),
        );

        $this->assertCount(1, $diff);
        $this->assertSame(12.0, $diff['P1']['final_quantity']);
    }

    public function test_product_in_both_yields_only_the_shortfall(): void
    {
        $diff = $this->service->differenceItemsFor(
            $this->keyed([$this->item('P1', 12)]),
            $this->keyed([$this->item('P1', 8)]),
        );

        $this->assertSame(4.0, $diff['P1']['final_quantity']);
        $this->assertSame(4.0, $diff['P1']['final_cases']);
        $this->assertSame(10.0, $diff['P1']['total_cost']);
    }

    public function test_equal_or_larger_coverage_produces_no_line(): void
    {
        $diff = $this->service->differenceItemsFor(
            $this->keyed([$this->item('P1', 12), $this->item('P2', 6)]),
            $this->keyed([$this->item('P1', 12), $this->item('P2', 20)]),
        );

        $this->assertTrue($diff->isEmpty());
    }

    public function test_shortfall_below_the_rounding_epsilon_produces_no_line(): void
    {
        $diff = $this->service->differenceItemsFor(
            $this->keyed([$this->item('P1', 12.0004)]),
            $this->keyed([$this->item('P1', 12)]),
        );

        $this->assertTrue($diff->isEmpty());
    }

    public function test_case_products_round_the_shortfall_up_to_whole_cases(): void
    {
        // 24 units ordered, 8 covered: a 16-unit gap on a 12-pack is two cases.
        $diff = $this->service->differenceItemsFor(
            $this->keyed([$this->item('P1', 24, 12)]),
            $this->keyed([$this->item('P1', 8, 12)]),
        );

        $this->assertSame(2.0, $diff['P1']['final_cases']);
        $this->assertSame(24.0, $diff['P1']['final_quantity']);
        $this->assertSame(12, $diff['P1']['case_units']);
    }

    public function test_case_rounding_may_exceed_the_shortfall_because_part_cases_cannot_be_bought(): void
    {
        // A source quantity off the case grid is reachable: updateOrderItemCases()
        // accepts a fractional case count. 25 units uncovered is still 3 cases.
        $diff = $this->service->differenceItemsFor(
            $this->keyed([$this->item('P1', 25, 12, ['final_cases' => 2.0833])]),
            $this->keyed([]),
        );

        $this->assertSame(3.0, $diff['P1']['final_cases']);
        $this->assertSame(36.0, $diff['P1']['final_quantity']);
    }

    public function test_case_size_is_taken_from_the_source_order_when_the_two_disagree(): void
    {
        // Units are the source of truth; the case size is a per-session snapshot.
        // 24 units ordered against 10 covered is a 14-unit gap, i.e. two 12-packs.
        $diff = $this->service->differenceItemsFor(
            $this->keyed([$this->item('P1', 24, 12)]),
            $this->keyed([$this->item('P1', 10, 6)]),
        );

        $this->assertSame(12, $diff['P1']['case_units']);
        $this->assertSame(2.0, $diff['P1']['final_cases']);
        $this->assertSame(24.0, $diff['P1']['final_quantity']);
    }

    public function test_missing_case_size_is_treated_as_single_units(): void
    {
        $diff = $this->service->differenceItemsFor(
            $this->keyed([$this->item('P1', 5, 1, ['case_units' => 0])]),
            $this->keyed([]),
        );

        $this->assertSame(5.0, $diff['P1']['final_quantity']);
        $this->assertSame(1, $diff['P1']['case_units']);
    }

    public function test_new_rows_read_as_unadjusted_and_unapproved(): void
    {
        $diff = $this->service->differenceItemsFor(
            $this->keyed([$this->item('P1', 12, 1, ['auto_approved' => true, 'added_via_search' => true])]),
            $this->keyed([]),
        );

        $row = $diff['P1'];

        $this->assertSame($row['final_quantity'], $row['suggested_quantity']);
        $this->assertSame($row['final_cases'], $row['suggested_cases']);
        $this->assertFalse($row['auto_approved']);
        $this->assertFalse($row['added_via_search']);
        $this->assertNull($row['adjustment_reason']);
    }

    public function test_the_source_sales_snapshot_carries_over_with_provenance(): void
    {
        $diff = $this->service->differenceItemsFor(
            $this->keyed([$this->item('P1', 12)]),
            $this->keyed([$this->item('P1', 5)]),
        );

        $context = $diff['P1']['context_data'];

        $this->assertSame(4, $context['current_stock']);
        $this->assertSame([['label' => 'W1', 'units' => 3]], $context['weekly_sales']);
        $this->assertSame('order_difference', $context['derived_from']['type']);
        $this->assertSame(12.0, $context['derived_from']['from_quantity']);
        $this->assertSame(5.0, $context['derived_from']['to_quantity']);
        $this->assertSame(7.0, $context['derived_from']['raw_shortfall']);
    }
}
