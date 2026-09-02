<?php

namespace Tests\Unit;

use App\Models\AccountingSupplier;
use App\Models\KitchenIngredientProfile;
use App\Models\KitchenRecipe;
use App\Models\KitchenRecipeIngredient;
use App\Services\KitchenOrganicRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KitchenOrganicRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed on this machine.');
        }

        parent::setUp();

        // PRODUCTS and supplier_link live in the external uniCenta database, so there are no
        // migrations for them.
        Schema::connection('pos')->create('PRODUCTS', function ($table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->nullable();
            $table->decimal('PRICEBUY', 10, 4)->default(0);
            $table->decimal('PRICESELL', 10, 4)->default(0);
        });

        Schema::connection('pos')->create('supplier_link', function ($table) {
            $table->increments('ID');
            $table->string('Barcode')->nullable();
            $table->string('SupplierCode')->nullable();
            $table->string('SupplierID')->nullable();
            $table->boolean('stocked')->default(false);
        });
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            Schema::connection('pos')->dropIfExists('supplier_link');
            Schema::connection('pos')->dropIfExists('PRODUCTS');
        }

        parent::tearDown();
    }

    private function service(): KitchenOrganicRegistrationService
    {
        return app(KitchenOrganicRegistrationService::class);
    }

    private function makeRecipe(): KitchenRecipe
    {
        return KitchenRecipe::create([
            'name' => 'Test Loaf',
            'portions_produced' => 4,
            'is_active' => true,
        ]);
    }

    /**
     * Create a profile, its POS product, and the supplier link that carries the certification body.
     */
    private function makeProfile(string $name, array $attributes = [], ?string $certificationBody = 'Organic Trust'): KitchenIngredientProfile
    {
        $productId = 'prod-'.$name;
        $barcode = 'BC-'.$name;

        DB::connection('pos')->table('PRODUCTS')->insert([
            'ID' => $productId, 'NAME' => $name, 'CODE' => $barcode, 'PRICEBUY' => 1, 'PRICESELL' => 2,
        ]);

        if ($certificationBody !== null) {
            $supplierId = 'sup-'.$name;

            DB::connection('pos')->table('supplier_link')->insert([
                'Barcode' => $barcode, 'SupplierID' => $supplierId, 'stocked' => true,
            ]);

            AccountingSupplier::create([
                'code' => 'C-'.$name,
                'name' => 'Supplier '.$name,
                'external_pos_id' => $supplierId,
                'is_pos_linked' => true,
                'is_organic' => true,
                'organic_certification_body' => $certificationBody,
            ]);
        }

        return KitchenIngredientProfile::create(array_merge([
            'pos_product_id' => $productId,
            'name' => $name,
            'purchase_quantity' => 1,
            'purchase_unit' => 'kg',
            'base_unit' => 'g',
            'cost_per_base_unit' => 0.001,
        ], $attributes));
    }

    private function addIngredient(KitchenRecipe $recipe, KitchenIngredientProfile $profile, float $quantity, string $unit): void
    {
        KitchenRecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_profile_id' => $profile->id,
            'pos_product_id' => $profile->pos_product_id,
            'quantity' => $quantity,
            'unit_type' => $unit,
        ]);
    }

    public function test_weight_units_convert_directly_and_percentages_sum_to_100(): void
    {
        $recipe = $this->makeRecipe();
        $this->addIngredient($recipe, $this->makeProfile('Flour'), 1.5, 'kg');
        $this->addIngredient($recipe, $this->makeProfile('Sugar'), 500, 'g');

        $form = $this->service()->build($recipe);

        $this->assertSame(2000.0, $form['total_grams']);
        $this->assertEqualsWithDelta(75.0, $form['rows'][0]['percent'], 0.01);
        $this->assertEqualsWithDelta(25.0, $form['rows'][1]['percent'], 0.01);
        $this->assertSame([], $form['warnings']);
    }

    public function test_volume_units_convert_using_density(): void
    {
        $recipe = $this->makeRecipe();
        $this->addIngredient($recipe, $this->makeProfile('Water'), 1, 'kg');
        // 2 tbsp = 30ml, at 0.92 g/ml = 27.6g
        $this->addIngredient($recipe, $this->makeProfile('Oil', ['purchase_unit' => 'L', 'density' => 0.92]), 2, 'tbsp');

        $form = $this->service()->build($recipe);

        $this->assertEqualsWithDelta(1027.6, $form['total_grams'], 0.01);
        $this->assertSame([], $form['warnings']);
    }

    public function test_volume_without_density_is_left_blank_and_warned(): void
    {
        $recipe = $this->makeRecipe();
        $this->addIngredient($recipe, $this->makeProfile('Flour'), 1, 'kg');
        $this->addIngredient($recipe, $this->makeProfile('Shoyu', ['purchase_unit' => 'L']), 2, 'tbsp');

        $form = $this->service()->build($recipe);

        $shoyu = collect($form['rows'])->firstWhere('ingredient', 'Shoyu');
        $this->assertNull($shoyu['percent']);
        $this->assertSame(1000.0, $form['total_grams']);
        $this->assertStringContainsString('density', $form['warnings'][0]);
    }

    public function test_count_units_convert_using_unit_weight(): void
    {
        $recipe = $this->makeRecipe();
        $this->addIngredient($recipe, $this->makeProfile('Eggs', ['purchase_unit' => 'unit', 'unit_weight_grams' => 60]), 3, 'unit');

        $form = $this->service()->build($recipe);

        $this->assertSame(180.0, $form['total_grams']);
        $this->assertSame([], $form['warnings']);
    }

    public function test_count_units_without_unit_weight_are_left_blank_and_warned(): void
    {
        $recipe = $this->makeRecipe();
        $this->addIngredient($recipe, $this->makeProfile('Flour'), 1, 'kg');
        $this->addIngredient($recipe, $this->makeProfile('Garlic', ['purchase_unit' => 'unit']), 2, 'unit');

        $form = $this->service()->build($recipe);

        $garlic = collect($form['rows'])->firstWhere('ingredient', 'Garlic');
        $this->assertNull($garlic['percent']);
        $this->assertStringContainsString('weight per unit', $form['warnings'][0]);
    }

    public function test_slice_and_portion_units_are_not_silently_weighted(): void
    {
        $recipe = $this->makeRecipe();
        $this->addIngredient($recipe, $this->makeProfile('Flour'), 1, 'kg');
        $this->addIngredient($recipe, $this->makeProfile('Bread'), 4, 'slice');

        $form = $this->service()->build($recipe);

        $this->assertSame(1000.0, $form['total_grams']);
        $bread = collect($form['rows'])->firstWhere('ingredient', 'Bread');
        $this->assertNull($bread['percent']);
        $this->assertStringContainsString('no weight equivalent', $form['warnings'][0]);
    }

    public function test_rows_are_sorted_descending_by_weight_with_unknowns_last(): void
    {
        $recipe = $this->makeRecipe();
        $this->addIngredient($recipe, $this->makeProfile('Small'), 100, 'g');
        $this->addIngredient($recipe, $this->makeProfile('Unknown', ['purchase_unit' => 'unit']), 1, 'unit');
        $this->addIngredient($recipe, $this->makeProfile('Large'), 2, 'kg');
        $this->addIngredient($recipe, $this->makeProfile('Medium'), 500, 'g');

        $form = $this->service()->build($recipe);

        $this->assertSame(
            ['Large', 'Medium', 'Small', 'Unknown'],
            array_column($form['rows'], 'ingredient')
        );
    }

    public function test_certification_body_is_resolved_from_the_supplier(): void
    {
        $recipe = $this->makeRecipe();
        $this->addIngredient($recipe, $this->makeProfile('Flour', [], 'IOFGA'), 1, 'kg');

        $form = $this->service()->build($recipe);

        $this->assertSame('IOFGA', $form['rows'][0]['certification_body']);
        $this->assertTrue($form['rows'][0]['organic']);
    }

    public function test_missing_certification_body_is_warned(): void
    {
        $recipe = $this->makeRecipe();
        $this->addIngredient($recipe, $this->makeProfile('Flour', [], null), 1, 'kg');

        $form = $this->service()->build($recipe);

        $this->assertSame('', $form['rows'][0]['certification_body']);
        $this->assertStringContainsString('certification body', $form['warnings'][0]);
    }

    public function test_non_organic_ingredients_carry_no_certification_body(): void
    {
        $recipe = $this->makeRecipe();
        $this->addIngredient($recipe, $this->makeProfile('Salt', ['organic_status' => 'non_organic']), 50, 'g');

        $form = $this->service()->build($recipe);

        $this->assertFalse($form['rows'][0]['organic']);
        $this->assertSame('', $form['rows'][0]['certification_body']);
        $this->assertSame([], $form['warnings']);
    }
}
