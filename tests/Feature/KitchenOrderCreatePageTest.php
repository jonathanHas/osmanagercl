<?php

namespace Tests\Feature;

use App\Models\KitchenStandingOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AliasesMysqlConnection;
use Tests\Concerns\CreatesKitchenOrderPosTables;
use Tests\TestCase;

class KitchenOrderCreatePageTest extends TestCase
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

        // KitchenProduct pins the 'mysql' connection.
        $this->aliasMysqlConnectionToTestDatabase();
        $this->createPosTables();
        $this->ids = $this->seedKitchenProducts();

        $this->actingAs(User::factory()->withRole('admin')->create());
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            $this->dropPosTables();
        }

        parent::tearDown();
    }

    public function test_the_create_page_lists_the_suppliers_products_with_standing_prefill(): void
    {
        KitchenStandingOrderItem::create(['product_id' => $this->ids['A'], 'quantity' => 4]);

        $response = $this->get(route('kitchen.orders.create', ['supplier' => '5']));

        $response->assertOk()
            ->assertSee('Apple Juice 1L')
            ->assertSee('Barley Flakes')
            ->assertDontSee('Cashew Butter')
            ->assertSee('name="qty[prod-a]"', false)
            ->assertSee('value="4"', false)
            ->assertSee('standing')
            ->assertSee('6 units/case')
            ->assertSee('single')
            ->assertSee('no supplier code')
            ->assertSee('U-A');
    }

    public function test_the_create_page_defaults_to_the_supplier_with_most_kitchen_products(): void
    {
        $response = $this->get(route('kitchen.orders.create'));

        $response->assertOk()
            ->assertSee('Udea (2)')
            ->assertSee('Independent (1)')
            ->assertSee('Apple Juice 1L')
            ->assertDontSee('Cashew Butter');
    }

    public function test_an_unknown_supplier_redirects_to_the_default(): void
    {
        $this->get(route('kitchen.orders.create', ['supplier' => '999']))
            ->assertRedirect(route('kitchen.orders.create', ['supplier' => '5']))
            ->assertSessionHas('error');
    }

    public function test_switching_supplier_shows_that_suppliers_products(): void
    {
        $this->get(route('kitchen.orders.create', ['supplier' => '37']))
            ->assertOk()
            ->assertSee('Cashew Butter')
            ->assertSee('12 units/case')
            ->assertDontSee('Apple Juice 1L');
    }
}
