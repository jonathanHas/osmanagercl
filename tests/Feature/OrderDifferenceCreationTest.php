<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "order the difference" action on the comparison page.
 *
 * The write path never touches the POS connection, and the response is a
 * redirect, so no PRODUCTS scaffolding is needed here - following the redirect
 * would pull in the whole product/supplier eager-load chain, so we assert on the
 * redirect itself.
 */
class OrderDifferenceCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * @param  array<int, array{string, float, int}>  $items  [product_id, final_quantity, case_units]
     */
    private function orderSession(string $supplierId, array $items): OrderSession
    {
        $session = OrderSession::create([
            'user_id' => $this->user->id,
            'supplier_id' => $supplierId,
            'order_date' => '2026-09-15',
            'coverage_days' => 7,
            'coverage_ends_on' => '2026-09-21',
            'sales_history_weeks' => 8,
            'status' => 'draft',
        ]);

        foreach ($items as [$productId, $quantity, $caseUnits]) {
            OrderItem::create([
                'order_session_id' => $session->id,
                'product_id' => $productId,
                'suggested_quantity' => $quantity,
                'final_quantity' => $quantity,
                'final_cases' => $caseUnits > 1 ? $quantity / $caseUnits : $quantity,
                'case_units' => $caseUnits,
                'unit_cost' => 2.00,
                'total_cost' => $quantity * 2.00,
                'review_priority' => 'standard',
                'context_data' => ['current_stock' => 3],
            ]);
        }

        return $session->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function postDifference(OrderSession $from, OrderSession $to, array $overrides = [])
    {
        return $this->actingAs($this->user)->post(route('orders.compare.difference'), array_merge([
            'from' => $from->id,
            'to' => $to->id,
            'order_date' => '2026-09-22',
        ], $overrides));
    }

    public function test_it_creates_a_draft_order_from_the_shortfall(): void
    {
        $from = $this->orderSession('5', [['P1', 12, 1], ['P2', 24, 12]]);
        $to = $this->orderSession('5', [['P1', 8, 1]]);

        $response = $this->postDifference($from, $to);

        $new = OrderSession::latest('id')->first();

        $response->assertRedirect(route('orders.show', $new));
        $response->assertSessionHas('success');

        $this->assertSame('draft', $new->status);
        $this->assertSame('5', $new->supplier_id);
        $this->assertSame($this->user->id, $new->user_id);
        $this->assertSame('2026-09-22', $new->order_date->toDateString());
        // Coverage is re-anchored to the new delivery date, not copied.
        $this->assertSame('2026-09-28', $new->coverage_ends_on->toDateString());
        $this->assertFalse($new->christmas_comparison_enabled);
        $this->assertStringContainsString("order #{$from->id}", $new->notes);
        $this->assertStringContainsString("order #{$to->id}", $new->notes);

        $this->assertSame(2, $new->total_items);
        $this->assertEquals(56.00, (float) $new->total_value);   // (4 + 24) units at 2.00

        $this->assertDatabaseHas('order_items', [
            'order_session_id' => $new->id,
            'product_id' => 'P1',
            'final_quantity' => 4.000,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_session_id' => $new->id,
            'product_id' => 'P2',
            'final_quantity' => 24.000,
            'final_cases' => 2.000,
        ]);
    }

    public function test_candidate_rows_at_zero_quantity_are_not_orders(): void
    {
        // The rule the whole comparison rests on: a session holds a row for every
        // candidate product, so a zero row on either side must not register as
        // ordered, nor mask a product as already covered.
        $from = $this->orderSession('5', [['P1', 12, 1], ['P2', 0, 1]]);
        $to = $this->orderSession('5', [['P1', 0, 1]]);

        $this->postDifference($from, $to);

        $new = OrderSession::latest('id')->first();

        $this->assertSame(1, $new->total_items);
        $this->assertDatabaseHas('order_items', [
            'order_session_id' => $new->id,
            'product_id' => 'P1',
            'final_quantity' => 12.000,
        ]);
    }

    public function test_it_refuses_orders_from_different_suppliers(): void
    {
        $from = $this->orderSession('5', [['P1', 12, 1]]);
        $to = $this->orderSession('44', [['P1', 8, 1]]);

        $response = $this->postDifference($from, $to);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(2, OrderSession::count());
    }

    public function test_it_creates_nothing_when_there_is_no_shortfall(): void
    {
        $from = $this->orderSession('5', [['P1', 12, 1]]);
        $to = $this->orderSession('5', [['P1', 20, 1]]);

        $response = $this->postDifference($from, $to);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(2, OrderSession::count());
    }

    public function test_an_order_cannot_be_differenced_against_itself(): void
    {
        $from = $this->orderSession('5', [['P1', 12, 1]]);

        $this->postDifference($from, $from)->assertSessionHasErrors('from');

        $this->assertSame(1, OrderSession::count());
    }

    public function test_guests_cannot_create_difference_orders(): void
    {
        $from = $this->orderSession('5', [['P1', 12, 1]]);
        $to = $this->orderSession('5', [['P1', 8, 1]]);

        $response = $this->post(route('orders.compare.difference'), [
            'from' => $from->id,
            'to' => $to->id,
            'order_date' => '2026-09-22',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(2, OrderSession::count());
    }
}
