<?php

namespace Tests\Feature;

use App\Models\KitchenOrder;
use App\Models\User;
use App\Services\KitchenOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\TestCase;

/**
 * Last-3 history and the history page read only the Laravel-side order
 * tables, so no POS tables are needed. The index page's supplier filter
 * calls supplierOptions(), which reads KitchenProduct (pinned to 'mysql')
 * and only touches POS when kitchen products exist — none here.
 */
class KitchenOrderHistoryTest extends TestCase
{
    use AliasesMysqlConnection, RefreshDatabase;

    private const PRODUCT_A = 'prod-a';

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed on this machine.');
        }

        parent::setUp();

        $this->aliasMysqlConnectionToTestDatabase();

        $this->actingAs(User::factory()->create(['name' => 'Kitchen Kate']));
    }

    private function makeOrder(string $supplierId, string $supplierName, int $qty, string $createdAt, ?string $notes = null): KitchenOrder
    {
        $order = KitchenOrder::create([
            'supplier_id' => $supplierId,
            'supplier_name' => $supplierName,
            'notes' => $notes,
            'total_cases' => $qty,
            'line_count' => 1,
        ]);
        $order->items()->create([
            'product_id' => self::PRODUCT_A,
            'supplier_code' => 'U-A',
            'product_name' => 'Apple Juice 1L',
            'case_units' => 6,
            'quantity' => $qty,
        ]);
        // created_at is the order moment; set it explicitly for a stable order.
        KitchenOrder::where('id', $order->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);

        return $order->fresh();
    }

    public function test_last_orders_returns_the_three_newest_for_that_supplier_only(): void
    {
        $this->makeOrder('5', 'Udea', 1, '2026-09-01 10:00:00');
        $this->makeOrder('5', 'Udea', 2, '2026-09-08 10:00:00');
        $this->makeOrder('5', 'Udea', 3, '2026-09-15 10:00:00');
        $this->makeOrder('5', 'Udea', 4, '2026-09-16 10:00:00');
        $this->makeOrder('37', 'Independent', 99, '2026-09-16 12:00:00');

        $history = app(KitchenOrderService::class)->lastOrdersByProduct('5', [self::PRODUCT_A]);

        $this->assertArrayHasKey(self::PRODUCT_A, $history);
        $entries = $history[self::PRODUCT_A];

        $this->assertCount(3, $entries);
        $this->assertSame([4, 3, 2], array_column($entries, 'quantity'));
        $this->assertSame('2026-09-16', $entries[0]['date']->format('Y-m-d'));
        $this->assertSame('2026-09-15', $entries[1]['date']->format('Y-m-d'));
        $this->assertSame('2026-09-08', $entries[2]['date']->format('Y-m-d'));
        $this->assertNotContains(99, array_column($entries, 'quantity'), 'Supplier 37 must be excluded');
    }

    public function test_last_orders_is_empty_for_unknown_products(): void
    {
        $this->assertSame([], app(KitchenOrderService::class)->lastOrdersByProduct('5', []));
        $this->assertSame([], app(KitchenOrderService::class)->lastOrdersByProduct('5', ['nope']));
    }

    public function test_the_history_page_filters_by_supplier(): void
    {
        $this->makeOrder('5', 'Udea', 1, '2026-09-01 10:00:00', 'first udea');
        $this->makeOrder('5', 'Udea', 2, '2026-09-08 10:00:00', 'second udea');
        $this->makeOrder('5', 'Udea', 3, '2026-09-15 10:00:00', 'third udea');
        $this->makeOrder('5', 'Udea', 4, '2026-09-16 10:00:00', 'fourth udea');
        $this->makeOrder('37', 'Independent', 9, '2026-09-16 12:00:00', 'the independent one');

        $this->get(route('kitchen.orders.index', ['supplier' => '5']))
            ->assertOk()
            ->assertSee('first udea')
            ->assertSee('second udea')
            ->assertSee('third udea')
            ->assertSee('fourth udea')
            ->assertDontSee('the independent one');

        $this->get(route('kitchen.orders.index'))
            ->assertOk()
            ->assertSee('the independent one')
            ->assertSee('Independent');
    }

    public function test_the_history_page_has_an_empty_state(): void
    {
        $this->get(route('kitchen.orders.index'))
            ->assertOk()
            ->assertSee('No kitchen orders yet')
            ->assertSee(route('kitchen.orders.create'), false);
    }
}
