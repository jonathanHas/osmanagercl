<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create POS database table for testing
        $this->createProductsTable();

        // Seed test products
        $this->seedTestProducts();
    }

    protected function createProductsTable(): void
    {
        Schema::connection('pos')->create('PRODUCTS', function ($table) {
            $table->string('ID', 255)->primary();
            $table->string('REFERENCE', 255)->unique();
            $table->string('CODE', 255)->unique();
            $table->string('CODETYPE', 255)->nullable();
            $table->string('NAME', 255);
            $table->double('PRICEBUY')->default(0);
            $table->double('PRICESELL')->default(0);
            $table->string('CATEGORY', 255);
            $table->string('TAXCAT', 255);
            $table->string('ATTRIBUTESET_ID', 255)->nullable();
            $table->double('STOCKCOST')->default(0);
            $table->double('STOCKVOLUME')->default(0);
            $table->binary('IMAGE')->nullable();
            $table->boolean('ISCOM')->default(false);
            $table->boolean('ISSCALE')->default(false);
            $table->boolean('ISKITCHEN')->default(false);
            $table->boolean('PRINTKB')->default(false);
            $table->boolean('SENDSTATUS')->default(false);
            $table->boolean('ISSERVICE')->default(false);
            $table->binary('ATTRIBUTES')->nullable();
            $table->string('DISPLAY', 255)->nullable();
            $table->smallInteger('ISVPRICE')->default(0);
            $table->smallInteger('ISVERPATRIB')->default(0);
            $table->string('TEXTTIP', 255)->nullable();
            $table->smallInteger('WARRANTY')->default(0);
            $table->double('STOCKUNITS')->default(0);
        });
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
    }

    public function test_products_page_requires_authentication()
    {
        $response = $this->get('/products');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_products_list()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/products');

        $response->assertStatus(200);
        $response->assertViewIs('products.index');
        $response->assertSee('Test Product 1');
        $response->assertSee('Test Service Product');
        $response->assertSee('Kitchen Item');
    }

    public function test_can_search_products_by_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/products?search=Kitchen');

        $response->assertStatus(200);
        $response->assertSee('Kitchen Item');
        $response->assertDontSee('Test Product 1');
    }

    public function test_can_filter_active_products_only()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/products?active_only=1');

        $response->assertStatus(200);
        $response->assertSee('Test Product 1');
        $response->assertSee('Kitchen Item');
        $response->assertDontSee('Test Service Product');
    }

    public function test_can_view_product_details()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/products/prod001');

        $response->assertStatus(200);
        $response->assertViewIs('products.show');
        $response->assertSee('Test Product 1');
        $response->assertSee('REF001');
        $response->assertSee('CODE001');
        $response->assertSee('$15.00');
    }

    public function test_shows_404_for_non_existent_product()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/products/non-existent');

        $response->assertStatus(404);
    }

    public function test_product_statistics_are_displayed()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/products');

        $response->assertStatus(200);
        $response->assertSee('Total Products');
        $response->assertSee('3'); // Total products count
        $response->assertSee('Active Products');
        $response->assertSee('2'); // Active products count
    }

    protected function tearDown(): void
    {
        // Drop the test products table
        Schema::connection('pos')->dropIfExists('PRODUCTS');

        parent::tearDown();
    }
}
