<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesProductSearchPosTables;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use CreatesProductSearchPosTables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // /products now renders through ProductSearchService, which reads
        // PRODUCTS, stocking, supplier_link, suppliers, STOCKCURRENT,
        // CATEGORIES, TAXCATEGORIES and TAXES from the POS connection.
        $this->createProductSearchPosTables();

        // Seed test products
        $this->seedTestProducts();
    }

    protected function seedTestProducts(): void
    {
        DB::connection('pos')->table('PRODUCTS')->insert([
            [
                'ID' => 'prod001',
                'REFERENCE' => 'REF001',
                'CODE' => 'CODE001',
                'NAME' => 'Test Product 1',
                'PRICEBUY' => 10.00,
                'PRICESELL' => 15.00,
                'CATEGORY' => 'cat001',
                'TAXCAT' => 'tax001',
                'STOCKUNITS' => 100,
                'ISSERVICE' => false,
                'ISSCALE' => false,
                'ISKITCHEN' => false,
            ],
            [
                'ID' => 'prod002',
                'REFERENCE' => 'REF002',
                'CODE' => 'CODE002',
                'NAME' => 'Test Service Product',
                'PRICEBUY' => 0,
                'PRICESELL' => 25.00,
                'CATEGORY' => 'cat002',
                'TAXCAT' => 'tax001',
                'STOCKUNITS' => 0,
                'ISSERVICE' => true,
                'ISSCALE' => false,
                'ISKITCHEN' => false,
            ],
            [
                'ID' => 'prod003',
                'REFERENCE' => 'REF003',
                'CODE' => 'CODE003',
                'NAME' => 'Kitchen Item',
                'PRICEBUY' => 5.00,
                'PRICESELL' => 12.50,
                'CATEGORY' => 'cat001',
                'TAXCAT' => 'tax001',
                'STOCKUNITS' => 50,
                'ISSERVICE' => false,
                'ISSCALE' => false,
                'ISKITCHEN' => true,
            ],
        ]);

        // Test Product 1 and Kitchen Item are stocked; Test Service Product is not.
        DB::connection('pos')->table('stocking')->insert([
            ['Barcode' => 'CODE001'],
            ['Barcode' => 'CODE003'],
        ]);
    }

    public function test_products_page_requires_authentication()
    {
        $response = $this->get('/products');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_products_list()
    {
        $user = User::factory()->withRole('admin')->create();

        $response = $this->actingAs($user)->get('/products');

        $response->assertStatus(200);
        $response->assertViewIs('products.index');
        $response->assertSee('Test Product 1');
        $response->assertSee('Kitchen Item');
        // Stocked products by default: the unstocked service product is behind the "Include unstocked" toggle.
        $response->assertDontSee('Test Service Product');
    }

    public function test_can_search_products_by_name()
    {
        $user = User::factory()->withRole('admin')->create();

        $response = $this->actingAs($user)->get('/products?q=Kitchen');

        $response->assertStatus(200);
        $response->assertSee('Kitchen Item');
        $response->assertDontSee('Test Product 1');
    }

    public function test_legacy_search_param_still_works()
    {
        $user = User::factory()->withRole('admin')->create();

        $response = $this->actingAs($user)->get('/products?search=Kitchen');

        $response->assertStatus(200);
        $response->assertSee('Kitchen Item');
        $response->assertDontSee('Test Product 1');
    }

    public function test_unstocked_products_hidden_by_default_and_shown_with_toggle()
    {
        $user = User::factory()->withRole('admin')->create();

        // "Test Service Product" has no stocking row, so the default (stocked) view hides it.
        $response = $this->actingAs($user)->get('/products?q=Test');
        $response->assertStatus(200);
        $response->assertSee('Test Product 1');
        $response->assertDontSee('Test Service Product');

        $response = $this->actingAs($user)->get('/products?q=Test&stocked=0');
        $response->assertStatus(200);
        $response->assertSee('Test Product 1');
        $response->assertSee('Test Service Product');
    }

    public function test_can_view_product_details()
    {
        $user = User::factory()->withRole('admin')->create();

        $response = $this->actingAs($user)->get('/products/prod001');

        // Show route redirects to edit page
        $response->assertRedirect('/products/prod001/edit');
    }

    public function test_shows_404_for_non_existent_product()
    {
        $user = User::factory()->withRole('admin')->create();

        $response = $this->actingAs($user)->get('/products/non-existent');

        $response->assertStatus(404);
    }

    public function test_product_statistics_are_displayed()
    {
        $user = User::factory()->withRole('admin')->create();

        $response = $this->actingAs($user)->get('/products');

        $response->assertStatus(200);
        $response->assertSee('Total Products');
        $response->assertSee('3'); // Total products count
        $response->assertSee('Active Products');
        $response->assertSee('2'); // Active products count
    }

    protected function tearDown(): void
    {
        $this->dropProductSearchPosTables();

        parent::tearDown();
    }
}
