<?php

namespace Tests\Feature;

use App\Models\KitchenRecipe;
use App\Models\Product;
use App\Services\KitchenWholesaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KitchenWholesalePricingTest extends TestCase
{
    use RefreshDatabase;

    private KitchenWholesaleService $service;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed on this machine.');
        }

        parent::setUp();

        // These tables live in the external uniCenta database, so there are no
        // migrations for them.
        Schema::connection('pos')->create('PRODUCTS', function ($table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->nullable();
            $table->string('REFERENCE')->nullable();
            $table->string('CATEGORY')->nullable();
            $table->string('TAXCAT')->nullable();
            $table->decimal('PRICEBUY', 10, 4)->default(0);
            $table->decimal('PRICESELL', 10, 4)->default(0);
            $table->string('DISPLAY')->nullable();
        });

        Schema::connection('pos')->create('TAXCATEGORIES', function ($table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
        });

        Schema::connection('pos')->create('TAXES', function ($table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CATEGORY')->nullable();
            $table->decimal('RATE', 10, 4)->default(0);
            $table->integer('RATEORDER')->nullable();
        });

        Schema::connection('pos')->create('PRODUCTS_CAT', function ($table) {
            $table->string('PRODUCT')->primary();
            $table->integer('CATORDER')->nullable();
        });

        Schema::connection('pos')->create('STOCKCURRENT', function ($table) {
            $table->string('LOCATION')->nullable();
            $table->string('PRODUCT')->nullable();
            $table->string('ATTRIBUTESETINSTANCE_ID')->nullable();
            $table->decimal('UNITS', 10, 4)->default(0);
        });

        // ProductMetadata is pinned to the 'mysql' connection; point it at an
        // in-memory sqlite DB and create its table there (same approach as
        // WasteLogTest).
        \Illuminate\Support\Facades\Config::set('database.connections.mysql', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        \Illuminate\Support\Facades\DB::purge('mysql');

        \Illuminate\Support\Facades\DB::connection('mysql')->getSchemaBuilder()
            ->create('product_metadata', function ($table) {
                $table->id();
                $table->string('product_id');
                $table->string('product_code')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });

        $this->seedTaxCategory('002', '23%', 0.23);
        $this->seedTaxCategory('001', '13.5%', 0.135);

        $this->service = app(KitchenWholesaleService::class);
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            foreach (['PRODUCTS', 'TAXCATEGORIES', 'TAXES', 'PRODUCTS_CAT', 'STOCKCURRENT'] as $table) {
                Schema::connection('pos')->dropIfExists($table);
            }
        }

        parent::tearDown();
    }

    private function seedTaxCategory(string $id, string $name, float $rate): void
    {
        $category = new \App\Models\TaxCategory;
        $category->ID = $id;
        $category->NAME = $name;
        $category->save();

        $tax = new \App\Models\Tax;
        $tax->ID = 'tax-'.$id;
        $tax->NAME = $name;
        $tax->CATEGORY = $id;
        $tax->RATE = $rate;
        $tax->RATEORDER = 1;
        $tax->save();
    }

    private function makeRetailProduct(string $category = '082', string $taxcat = '002'): Product
    {
        $product = new Product;
        $product->ID = 'retail-product-id';
        $product->NAME = 'Lentil Soup';
        $product->CODE = '4001';
        $product->REFERENCE = '4001';
        $product->CATEGORY = $category;
        $product->TAXCAT = $taxcat;
        $product->PRICEBUY = 1.00;
        $product->PRICESELL = 3.00;
        $product->save();

        return $product;
    }

    /**
     * A recipe with no ingredients: the batch cost is pure labour, which keeps
     * the expected figure exact and independent of ingredient profiles.
     *
     * 60 min prep at €15/hr = €15.00 labour, no cook time, over 10 portions.
     */
    private function makeRecipe(?string $posProductId = null): KitchenRecipe
    {
        config([
            'kitchen.labour_rate' => 15.00,
            'kitchen.cook_supervision_factor' => 0.10,
        ]);

        return KitchenRecipe::create([
            'name' => 'Lentil Soup',
            'prep_time' => 60,
            'cook_time' => 0,
            'portions_produced' => 10,
            'pos_product_id' => $posProductId,
            'is_active' => true,
        ]);
    }

    private function wholesaleProducts(): \Illuminate\Support\Collection
    {
        return Product::where('NAME', 'like', '%Wholesale%')->get();
    }

    public function test_setting_a_price_creates_a_wholesale_product_named_after_the_recipe(): void
    {
        $retail = $this->makeRetailProduct();
        $recipe = $this->makeRecipe($retail->ID);

        $this->service->setPrice($recipe, 100.00);

        $product = $recipe->fresh()->wholesaleProduct;

        $this->assertNotNull($product);
        $this->assertSame('Lentil Soup Wholesale', $product->NAME);
        $this->assertSame($product->CODE, $product->REFERENCE);
    }

    public function test_price_buy_is_the_full_batch_cost_not_the_cost_per_portion(): void
    {
        $retail = $this->makeRetailProduct();
        $recipe = $this->makeRecipe($retail->ID);

        $this->service->setPrice($recipe, 100.00);

        // 60 min prep at €15/hr = €15.00 for the whole batch. Per portion it
        // would be €1.50 - storing that would undercost the batch tenfold.
        $this->assertEqualsWithDelta(15.00, (float) $recipe->fresh()->wholesaleProduct->PRICEBUY, 0.01);
    }

    public function test_price_sell_is_stored_ex_vat(): void
    {
        $retail = $this->makeRetailProduct('082', '002');   // 23%
        $recipe = $this->makeRecipe($retail->ID);

        $this->service->setPrice($recipe, 100.00);

        // 100.00 inc VAT at 23% is 81.3008 ex VAT.
        $this->assertEqualsWithDelta(81.3008, (float) $recipe->fresh()->wholesaleProduct->PRICESELL, 0.0001);
    }

    public function test_category_and_vat_are_inherited_from_the_linked_retail_product(): void
    {
        $retail = $this->makeRetailProduct('082', '001');
        $recipe = $this->makeRecipe($retail->ID);

        $this->service->setPrice($recipe, 100.00);

        $product = $recipe->fresh()->wholesaleProduct;

        $this->assertSame('082', $product->CATEGORY);
        $this->assertSame('001', $product->TAXCAT);
    }

    public function test_the_recipe_records_the_link_target_and_timestamp(): void
    {
        $retail = $this->makeRetailProduct();
        $recipe = $this->makeRecipe($retail->ID);

        $this->service->setPrice($recipe, 100.00, null, null, 35.0);

        $recipe = $recipe->fresh();

        $this->assertNotNull($recipe->wholesale_pos_product_id);
        $this->assertEqualsWithDelta(35.0, (float) $recipe->wholesale_target_margin, 0.01);
        $this->assertNotNull($recipe->wholesale_priced_at);
    }

    public function test_pricing_twice_updates_the_same_product_instead_of_duplicating(): void
    {
        $retail = $this->makeRetailProduct();
        $recipe = $this->makeRecipe($retail->ID);

        $this->service->setPrice($recipe, 100.00);
        $firstId = $recipe->fresh()->wholesale_pos_product_id;

        $result = $this->service->setPrice($recipe->fresh(), 150.00);

        $this->assertFalse($result['created']);
        $this->assertCount(1, $this->wholesaleProducts());

        $product = $recipe->fresh()->wholesaleProduct;

        $this->assertSame($firstId, $product->ID);
        $this->assertEqualsWithDelta(121.9512, (float) $product->PRICESELL, 0.0001);
    }

    public function test_an_unlinked_recipe_needs_an_explicit_category_and_vat(): void
    {
        $recipe = $this->makeRecipe();

        $this->expectException(\RuntimeException::class);

        $this->service->setPrice($recipe, 100.00);
    }

    public function test_an_unlinked_recipe_uses_the_supplied_category_and_vat(): void
    {
        $recipe = $this->makeRecipe();

        $this->service->setPrice($recipe, 100.00, '083', '001');

        $product = $recipe->fresh()->wholesaleProduct;

        $this->assertSame('083', $product->CATEGORY);
        $this->assertSame('001', $product->TAXCAT);
    }

    public function test_a_clashing_product_name_gets_the_code_suffix(): void
    {
        // PRODUCTS.NAME is unique in the POS, so the plain name is unavailable.
        $squatter = new Product;
        $squatter->ID = 'squatter-id';
        $squatter->NAME = 'Lentil Soup Wholesale';
        $squatter->CODE = '7777';
        $squatter->save();

        $retail = $this->makeRetailProduct();
        $recipe = $this->makeRecipe($retail->ID);

        // The name lookup would otherwise adopt the squatter, so point the
        // recipe at a product that definitely is not it.
        $this->service->setPrice($recipe, 100.00);

        $product = $recipe->fresh()->wholesaleProduct;

        // The squatter is adopted by name rather than duplicated - the suffix
        // path only triggers when a *different* product owns the name.
        $this->assertSame('squatter-id', $product->ID);
        $this->assertSame('Lentil Soup Wholesale', $product->NAME);
    }

    public function test_a_price_below_cost_saves_with_a_warning_rather_than_failing(): void
    {
        $retail = $this->makeRetailProduct();
        $recipe = $this->makeRecipe($retail->ID);

        // Batch cost is €15.00; €10 inc VAT is €8.13 ex VAT.
        $result = $this->service->setPrice($recipe, 10.00);

        $this->assertContains('price_below_cost', $result['warnings']);
        $this->assertNotNull($recipe->fresh()->wholesaleProduct);
    }

    public function test_a_margin_under_the_target_is_warned_about(): void
    {
        $retail = $this->makeRetailProduct();
        $recipe = $this->makeRecipe($retail->ID);

        // €20 inc VAT is €16.26 ex VAT on a €15.00 batch - about 7.7% margin.
        $result = $this->service->setPrice($recipe, 20.00, null, null, 35.0);

        $this->assertContains('margin_below_target', $result['warnings']);
    }

    public function test_a_dangling_product_link_self_heals_instead_of_throwing(): void
    {
        $retail = $this->makeRetailProduct();
        $recipe = $this->makeRecipe($retail->ID);

        $recipe->update(['wholesale_pos_product_id' => 'deleted-in-unicenta']);

        $result = $this->service->setPrice($recipe, 100.00);

        $this->assertTrue($result['created']);
        $this->assertNotSame('deleted-in-unicenta', $recipe->fresh()->wholesale_pos_product_id);
        $this->assertCount(1, $this->wholesaleProducts());
    }

    public function test_a_stock_record_is_created_for_a_new_wholesale_product(): void
    {
        $retail = $this->makeRetailProduct();
        $recipe = $this->makeRecipe($retail->ID);

        $this->service->setPrice($recipe, 100.00);

        $this->assertSame(1, \App\Models\StockCurrent::where('PRODUCT', $recipe->fresh()->wholesale_pos_product_id)->count());
    }

    public function test_the_row_reports_the_price_back_as_inc_vat(): void
    {
        $retail = $this->makeRetailProduct('082', '002');
        $recipe = $this->makeRecipe($retail->ID);

        $this->service->setPrice($recipe, 100.00);

        $row = $this->service->buildRow($recipe->fresh(), ['002' => 0.23, '001' => 0.135]);

        // The price is never stored - it is rebuilt from PRICESELL and TAXCAT.
        $this->assertEqualsWithDelta(100.00, $row['incVat'], 0.01);
        $this->assertTrue($row['isPriced']);
        $this->assertFalse($row['needsClassification']);
        $this->assertEqualsWithDelta(15.00, $row['batchCost'], 0.01);
        $this->assertSame(10, $row['portions']);
    }
}
