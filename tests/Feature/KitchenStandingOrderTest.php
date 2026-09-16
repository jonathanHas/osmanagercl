<?php

namespace Tests\Feature;

use App\Models\KitchenStandingOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\Concerns\CreatesKitchenOrderPosTables;
use Tests\TestCase;

class KitchenStandingOrderTest extends TestCase
{
    use AliasesMysqlConnection, CreatesKitchenOrderPosTables, RefreshDatabase;

    /** @var array{A: string, B: string, C: string} */
    private array $ids;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed on this machine.');
        }

        parent::setUp();

        $this->aliasMysqlConnectionToTestDatabase();
        $this->createPosTables();
        $this->ids = $this->seedKitchenProducts();

        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            $this->dropPosTables();
        }

        parent::tearDown();
    }

    public function test_saving_stores_positive_quantities_and_zero_deletes(): void
    {
        $this->put(route('kitchen.standing-order.update'), [
            'qty' => [$this->ids['A'] => 4, $this->ids['B'] => 2],
        ])
            ->assertRedirect(route('kitchen.standing-order.edit'))
            ->assertSessionHas('success');

        $this->assertSame(2, KitchenStandingOrderItem::count());
        $this->assertSame(4, KitchenStandingOrderItem::where('product_id', $this->ids['A'])->sole()->quantity);

        // C is set separately and must be untouched by a save that does not post it.
        KitchenStandingOrderItem::create(['product_id' => $this->ids['C'], 'quantity' => 9]);

        $this->put(route('kitchen.standing-order.update'), [
            'qty' => [$this->ids['A'] => 6, $this->ids['B'] => 0],
        ])->assertRedirect(route('kitchen.standing-order.edit'));

        $this->assertSame(6, KitchenStandingOrderItem::where('product_id', $this->ids['A'])->sole()->quantity);
        $this->assertSame(0, KitchenStandingOrderItem::where('product_id', $this->ids['B'])->count());
        $this->assertSame(9, KitchenStandingOrderItem::where('product_id', $this->ids['C'])->sole()->quantity);
    }

    public function test_the_standing_page_groups_by_supplier_and_shows_saved_quantities(): void
    {
        KitchenStandingOrderItem::create(['product_id' => $this->ids['A'], 'quantity' => 6]);

        $response = $this->get(route('kitchen.standing-order.edit'));

        $response->assertOk()
            ->assertSee('Udea')
            ->assertSee('Independent')
            ->assertSee('Apple Juice 1L')
            ->assertSee('Cashew Butter')
            ->assertSee('name="qty[prod-a]"', false)
            ->assertSee('value="6"', false)
            ->assertSee('Save Standing Order');
    }

    public function test_a_negative_standing_quantity_fails_validation(): void
    {
        $this->from(route('kitchen.standing-order.edit'))
            ->put(route('kitchen.standing-order.update'), ['qty' => [$this->ids['A'] => -3]])
            ->assertSessionHasErrors('qty.'.$this->ids['A']);

        $this->assertSame(0, KitchenStandingOrderItem::count());
    }
}
