<?php

namespace Tests\Feature;

use App\Models\KitchenIngredientProfile;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KitchenProfileCostSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed on this machine.');
        }

        parent::setUp();

        // PRODUCTS lives in the external uniCenta database, so there is no migration for it.
        Schema::connection('pos')->create('PRODUCTS', function ($table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->nullable();
            $table->decimal('PRICEBUY', 10, 4)->default(0);
            $table->decimal('PRICESELL', 10, 4)->default(0);
        });
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            Schema::connection('pos')->dropIfExists('PRODUCTS');
        }

        parent::tearDown();
    }

    private function makeProduct(float $priceBuy): Product
    {
        $product = new Product;
        $product->ID = 'test-product-id';
        $product->NAME = 'Garlic';
        $product->CODE = '2013';
        $product->PRICEBUY = $priceBuy;
        $product->PRICESELL = 0.8;
        $product->save();

        return $product;
    }

    private function makeProfile(string $productId): KitchenIngredientProfile
    {
        // 1kg bag - base unit is grams, so cost_per_base_unit is PRICEBUY / 1000
        return KitchenIngredientProfile::create([
            'pos_product_id' => $productId,
            'name' => 'Garlic',
            'purchase_quantity' => 1,
            'purchase_unit' => 'kg',
            'base_unit' => 'g',
            'cost_per_base_unit' => 0,
        ])->recalculateCost();
    }

    public function test_changing_product_cost_price_recalculates_linked_profile(): void
    {
        $product = $this->makeProduct(2.00);
        $profile = $this->makeProfile($product->ID);

        $this->assertEqualsWithDelta(0.002, (float) $profile->cost_per_base_unit, 0.000001);

        $product->update(['PRICEBUY' => 4.00]);

        $this->assertEqualsWithDelta(
            0.004,
            (float) $profile->fresh()->cost_per_base_unit,
            0.000001,
            'Profile cost should follow the product cost price.'
        );
    }

    public function test_changing_retail_price_leaves_profile_cost_untouched(): void
    {
        $product = $this->makeProduct(2.00);
        $profile = $this->makeProfile($product->ID);

        $product->update(['PRICESELL' => 9.99]);

        $this->assertEqualsWithDelta(
            0.002,
            (float) $profile->fresh()->cost_per_base_unit,
            0.000001,
            'Kitchen costing is based on PRICEBUY only.'
        );
    }

    public function test_unlinked_profile_is_not_affected_by_product_cost_changes(): void
    {
        $product = $this->makeProduct(2.00);

        $profile = KitchenIngredientProfile::create([
            'pos_product_id' => null,
            'name' => 'Bulk dates',
            'manual_cost' => 15.00,
            'purchase_quantity' => 2.5,
            'purchase_unit' => 'kg',
            'base_unit' => 'g',
            'cost_per_base_unit' => 0,
        ])->recalculateCost();

        $before = (float) $profile->cost_per_base_unit;

        $product->update(['PRICEBUY' => 99.00]);

        $this->assertEqualsWithDelta($before, (float) $profile->fresh()->cost_per_base_unit, 0.000001);
    }
}
