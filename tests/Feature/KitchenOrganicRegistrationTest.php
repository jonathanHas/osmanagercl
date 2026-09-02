<?php

namespace Tests\Feature;

use App\Models\KitchenIngredientProfile;
use App\Models\KitchenRecipe;
use App\Models\KitchenRecipeIngredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KitchenOrganicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed on this machine.');
        }

        parent::setUp();

        // PRODUCTS and supplier_link live in the external uniCenta database.
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

    private function makeRecipe(int $ingredientCount): KitchenRecipe
    {
        $recipe = KitchenRecipe::create([
            'name' => 'Sausage Rolls',
            'portions_produced' => 4,
            'is_active' => true,
        ]);

        for ($i = 1; $i <= $ingredientCount; $i++) {
            DB::connection('pos')->table('PRODUCTS')->insert([
                'ID' => "prod-{$i}", 'NAME' => "Ingredient {$i}", 'CODE' => "BC-{$i}",
                'PRICEBUY' => 1, 'PRICESELL' => 2,
            ]);

            $profile = KitchenIngredientProfile::create([
                'pos_product_id' => "prod-{$i}",
                'name' => "Ingredient {$i}",
                'purchase_quantity' => 1,
                'purchase_unit' => 'kg',
                'base_unit' => 'g',
                'cost_per_base_unit' => 0.001,
            ]);

            KitchenRecipeIngredient::create([
                'recipe_id' => $recipe->id,
                'ingredient_profile_id' => $profile->id,
                'pos_product_id' => $profile->pos_product_id,
                'quantity' => $i * 100,
                'unit_type' => 'g',
            ]);
        }

        return $recipe;
    }

    /**
     * Count page objects, without matching the /Type /Pages page-tree node.
     */
    private function countPages(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    public function test_the_form_requires_authentication(): void
    {
        $recipe = $this->makeRecipe(1);

        $this->get(route('kitchen.organic-form', $recipe))->assertRedirect(route('login'));
    }

    public function test_it_downloads_a_pdf_named_after_the_recipe(): void
    {
        $recipe = $this->makeRecipe(3);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('kitchen.organic-form', $recipe));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename="organic-registration-sausage-rolls.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_a_recipe_within_one_page_produces_the_form_plus_the_signature_page(): void
    {
        $recipe = $this->makeRecipe(12);

        $content = $this->actingAs(User::factory()->create())
            ->get(route('kitchen.organic-form', $recipe))
            ->getContent();

        // One copy of page 1 for the 12 rows, plus page 2 for the checklist and signatures.
        $this->assertSame(2, $this->countPages($content));
    }

    public function test_more_than_twelve_ingredients_spill_onto_another_page(): void
    {
        $recipe = $this->makeRecipe(13);

        $content = $this->actingAs(User::factory()->create())
            ->get(route('kitchen.organic-form', $recipe))
            ->getContent();

        $this->assertSame(3, $this->countPages($content));
    }

    public function test_the_listing_hides_the_download_behind_a_toggle(): void
    {
        $recipe = $this->makeRecipe(1);

        $this->actingAs(User::factory()->create())
            ->get(route('kitchen.index'))
            ->assertOk()
            ->assertSee('Organic registration forms')
            ->assertSee('showOrganicForms', false)
            ->assertSee(route('kitchen.organic-form', $recipe), false);
    }

    public function test_the_listing_flags_recipes_whose_percentages_are_incomplete(): void
    {
        // One weighable ingredient plus one counted ingredient with no weight per unit.
        $recipe = $this->makeRecipe(1);

        DB::connection('pos')->table('PRODUCTS')->insert([
            'ID' => 'prod-garlic', 'NAME' => 'Garlic', 'CODE' => 'BC-garlic', 'PRICEBUY' => 1, 'PRICESELL' => 2,
        ]);

        $profile = KitchenIngredientProfile::create([
            'pos_product_id' => 'prod-garlic',
            'name' => 'Garlic',
            'purchase_quantity' => 1,
            'purchase_unit' => 'unit',
            'base_unit' => 'unit',
            'cost_per_base_unit' => 0.5,
        ]);

        KitchenRecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_profile_id' => $profile->id,
            'pos_product_id' => $profile->pos_product_id,
            'quantity' => 2,
            'unit_type' => 'unit',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('kitchen.index'))
            ->assertOk()
            ->assertSee('1 of 2 ingredients have no % weight worked out', false);
    }

    public function test_the_listing_does_not_flag_a_fully_weighed_recipe(): void
    {
        $this->makeRecipe(2);

        $this->actingAs(User::factory()->create())
            ->get(route('kitchen.index'))
            ->assertOk()
            ->assertDontSee('have no % weight worked out', false);
    }

    public function test_the_recipe_page_lists_data_gaps(): void
    {
        $recipe = $this->makeRecipe(1);

        $this->actingAs(User::factory()->create())
            ->get(route('kitchen.show', $recipe))
            ->assertOk()
            ->assertSee('Organic registration form is incomplete')
            ->assertSee('has no organic certification body', false);
    }
}
