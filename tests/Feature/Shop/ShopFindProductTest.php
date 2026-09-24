<?php

namespace Tests\Feature\Shop;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductSearch\ProductSearchVocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesProductSearchPosTables;
use Tests\TestCase;

/**
 * Shop mode find product: the screen, who may open it, and the canonical
 * search endpoint it leans on. The screen is read-only, so the contract
 * includes what must *not* be on it — no create, edit or stock affordance.
 */
class ShopFindProductTest extends TestCase
{
    use CreatesProductSearchPosTables;
    use RefreshDatabase;

    /** @var array{P1: string, P2: string, P3: string, P4: string, P5: string, P6: string, P7: string} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createProductSearchPosTables();
        $this->ids = $this->seedProductSearchFixture();
        Cache::forget(ProductSearchVocabulary::CACHE_KEY);
    }

    protected function tearDown(): void
    {
        $this->dropProductSearchPosTables();

        parent::tearDown();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(string $roleName, array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => $name, 'module' => 'Shop']
            );
            $role->givePermissionTo($permission);
        }

        return User::factory()->create(['role_id' => $role->id, 'name' => 'Maya Jensen']);
    }

    public function test_employee_can_open_the_find_product_screen(): void
    {
        $response = $this->actingAs($this->userWith('employee', ['products.view']))
            ->get(route('shop.find-product'))
            ->assertOk();

        $response->assertSee('data-shell="shop"', false);
        $response->assertSee('data-search-url="'.route('api.products.search').'"', false);
        $response->assertSee('type="search"', false);
        $response->assertSee('Our range');
        $response->assertSee('All products');
        $response->assertSee('href="'.route('shop.home').'"', false);

        // Read-only: nothing that creates, edits or links into the office product pages.
        $response->assertDontSee('products.create');
        $response->assertDontSee('products/create');
        $response->assertDontSee('edit_url');
        $response->assertDontSee('Edit');
    }

    public function test_barista_is_forbidden(): void
    {
        $this->actingAs($this->userWith('barista', ['kds.access']))
            ->get(route('shop.find-product'))
            ->assertForbidden();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('shop.find-product'))->assertRedirect('/login');
    }

    public function test_home_shows_the_find_product_tile_to_product_viewers(): void
    {
        $this->actingAs($this->userWith('employee', ['products.view']))
            ->get(route('shop.home'))
            ->assertOk()
            ->assertSee('Find product')
            ->assertSee('href="'.route('shop.find-product').'"', false);

        $this->actingAs($this->userWith('barista', ['kds.access']))
            ->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('Find product');
    }

    public function test_employee_can_search_the_product_api(): void
    {
        $response = $this->actingAs($this->userWith('employee', ['products.view']))
            ->getJson(route('api.products.search', ['q' => 'chocolatemakers fruit']))
            ->assertOk();

        $this->assertSame($this->ids['P1'], $response->json('data.0.id'));
        $this->assertNotNull($response->json('data.0.stock_units'));
        $this->assertTrue($response->json('meta.stocked'));
    }

    public function test_employee_without_products_view_cannot_search(): void
    {
        $this->actingAs($this->userWith('stock-only', ['stocking.scan']))
            ->getJson(route('api.products.search', ['q' => 'chocolatemakers fruit']))
            ->assertForbidden();
    }

    public function test_screen_renders_row_thumbnails_and_the_card_image(): void
    {
        $response = $this->actingAs($this->userWith('employee', ['products.view']))
            ->get(route('shop.find-product'))
            ->assertOk();

        $response->assertSee('class="shop-thumb"', false);
        $response->assertSee('shop-thumb--lg', false);
        $response->assertSee('loading="lazy"', false);

        // A candidate image URL may 404; the row must fall back rather than
        // show the browser's broken-image icon.
        $response->assertSee('imageFailed(', false);
    }

    public function test_search_rows_carry_an_image_url_field(): void
    {
        $row = $this->actingAs($this->userWith('employee', ['products.view']))
            ->getJson(route('api.products.search', ['q' => 'chocolatemakers fruit']))
            ->assertOk()
            ->json('data.0');

        $this->assertArrayHasKey('image_url', $row);
        $this->assertArrayHasKey('has_image', $row);
        $this->assertIsBool($row['has_image']);
    }

    public function test_card_image_is_a_toggle_button(): void
    {
        $response = $this->actingAs($this->userWith('employee', ['products.view']))
            ->get(route('shop.find-product'))
            ->assertOk();

        $response->assertSee('class="shop-thumb-btn"', false);
        $response->assertSee('toggleImage()', false);
        $response->assertSee("'is-open': enlarged", false);
        $response->assertSee('aria-pressed', false);

        // In the card, not in the list rows: a row is itself a button.
        $this->assertSame(1, substr_count($response->getContent(), 'shop-thumb-btn'));
    }

    public function test_row_thumbnails_show_a_hover_preview_on_mouse_devices(): void
    {
        $response = $this->actingAs($this->userWith('employee', ['products.view']))
            ->get(route('shop.find-product'))
            ->assertOk();

        $response->assertSee('class="shop-peek"', false);
        $response->assertSee('peekAt(p, $el)', false);
        $response->assertSee('@mouseleave="unpeek()"', false);

        // One shared panel for the whole list, not one per row.
        $this->assertSame(1, substr_count($response->getContent(), 'class="shop-peek"'));
    }

    public function test_barista_cannot_fetch_product_images(): void
    {
        $this->actingAs($this->userWith('barista', ['kds.access']))
            ->get(route('products.image', 'p-none'))
            ->assertForbidden();

        // An unknown id 404s, but the permission gate lets the employee through.
        $this->actingAs($this->userWith('employee', ['products.view']))
            ->get(route('products.image', 'p-none'))
            ->assertNotFound();
    }
}
