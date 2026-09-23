<?php

namespace Tests\Feature;

use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\Concerns\CreatesKitchenOrderPosTables;
use Tests\TestCase;

class KitchenOrderStoreTest extends TestCase
{
    use AliasesMysqlConnection, CreatesKitchenOrderPosTables, RefreshDatabase;

    /** @var array{A: string, B: string, C: string} */
    private array $ids;

    private User $user;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed on this machine.');
        }

        parent::setUp();

        $this->aliasMysqlConnectionToTestDatabase();
        $this->createPosTables();
        $this->ids = $this->seedKitchenProducts();

        $this->user = User::factory()->withRole('admin')->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            $this->dropPosTables();
        }

        parent::tearDown();
    }

    public function test_confirming_stores_a_snapshot_of_only_the_lines_with_a_quantity(): void
    {
        $response = $this->post(route('kitchen.orders.store'), [
            'supplier_id' => '5',
            'notes' => 'Thursday delivery',
            'qty' => [
                $this->ids['A'] => 3,
                $this->ids['B'] => 0,
                // C belongs to Independent, not Udea: must be ignored.
                $this->ids['C'] => 2,
            ],
        ]);

        $order = KitchenOrder::sole();

        $response->assertRedirect(route('kitchen.orders.show', $order))
            ->assertSessionHas('success');

        $this->assertSame('5', $order->supplier_id);
        $this->assertSame('Udea', $order->supplier_name);
        $this->assertSame(3, $order->total_cases);
        $this->assertSame(1, $order->line_count);
        $this->assertSame($this->user->id, $order->user_id);
        $this->assertSame('Thursday delivery', $order->notes);

        $item = KitchenOrderItem::sole();
        $this->assertSame($this->ids['A'], $item->product_id);
        $this->assertSame('U-A', $item->supplier_code);
        $this->assertSame('Apple Juice 1L', $item->product_name);
        $this->assertSame(6, $item->case_units);
        $this->assertSame(3, $item->quantity);
    }

    public function test_a_product_without_case_units_or_code_is_stored_as_a_single(): void
    {
        $this->post(route('kitchen.orders.store'), [
            'supplier_id' => '5',
            'qty' => [$this->ids['B'] => 2],
        ]);

        $item = KitchenOrderItem::sole();
        $this->assertNull($item->supplier_code);
        $this->assertSame(1, $item->case_units);
        $this->assertSame(2, $item->quantity);
    }

    public function test_an_all_zero_order_is_rejected_and_nothing_is_stored(): void
    {
        $this->post(route('kitchen.orders.store'), [
            'supplier_id' => '5',
            'qty' => [$this->ids['A'] => 0, $this->ids['B'] => 0],
        ])
            ->assertRedirect(route('kitchen.orders.create', ['supplier' => '5']))
            ->assertSessionHas('error');

        $this->assertSame(0, KitchenOrder::count());
        $this->assertSame(0, KitchenOrderItem::count());
    }

    public function test_a_negative_quantity_fails_validation(): void
    {
        $this->from(route('kitchen.orders.create', ['supplier' => '5']))
            ->post(route('kitchen.orders.store'), [
                'supplier_id' => '5',
                'qty' => [$this->ids['A'] => -1],
            ])
            ->assertSessionHasErrors('qty.'.$this->ids['A']);

        $this->assertSame(0, KitchenOrder::count());
    }

    public function test_the_show_page_renders_the_confirmed_order(): void
    {
        $this->post(route('kitchen.orders.store'), [
            'supplier_id' => '5',
            'qty' => [$this->ids['A'] => 3],
        ]);

        $order = KitchenOrder::sole();

        $this->get(route('kitchen.orders.show', $order))
            ->assertOk()
            ->assertSee('Kitchen Order #'.$order->id)
            ->assertSee('Udea')
            ->assertSee('Download CSV')
            ->assertSee('Apple Juice 1L')
            ->assertSee('U-A');
    }
}
