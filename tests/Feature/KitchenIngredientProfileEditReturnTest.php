<?php

namespace Tests\Feature;

use App\Models\KitchenIngredientProfile;
use App\Models\KitchenRecipe;
use App\Models\KitchenRecipeIngredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Editing an ingredient profile from a recipe's ingredient list returns to that recipe.
 */
class KitchenIngredientProfileEditReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed on this machine.');
        }

        parent::setUp();

        // PRODUCTS and supplier_link live in the external uniCenta database; the recipe
        // edit page eager-loads them even when no ingredient is linked to a POS product.
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

    private function makeProfile(): KitchenIngredientProfile
    {
        return KitchenIngredientProfile::create([
            'manual_cost' => 3.00,
            'name' => 'Pear Elliot',
            'purchase_quantity' => 1,
            'purchase_unit' => 'kg',
            'base_unit' => 'g',
            'cost_per_base_unit' => 0.003,
        ]);
    }

    private function makeRecipe(KitchenIngredientProfile $profile): KitchenRecipe
    {
        $recipe = KitchenRecipe::create([
            'name' => 'Pear Tart',
            'portions_produced' => 4,
            'is_active' => true,
        ]);

        KitchenRecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_profile_id' => $profile->id,
            'quantity' => 400,
            'unit_type' => 'g',
        ]);

        return $recipe;
    }

    private function updatePayload(): array
    {
        return [
            'manual_cost' => 3.50,
            'name' => 'Pear Elliot',
            'purchase_quantity' => 1,
            'purchase_unit' => 'kg',
            'organic_status' => 'organic',
        ];
    }

    public function test_recipe_edit_page_links_each_profiled_ingredient_to_its_profile(): void
    {
        $profile = $this->makeProfile();
        $recipe = $this->makeRecipe($profile);

        $this->actingAs(User::factory()->create())
            ->get(route('kitchen.edit', $recipe))
            ->assertOk()
            ->assertSee(route('kitchen.profiles.edit', [$profile, 'recipe' => $recipe->id]), false);
    }

    public function test_profile_edit_page_returns_to_the_recipe_it_was_opened_from(): void
    {
        $profile = $this->makeProfile();
        $recipe = $this->makeRecipe($profile);

        $this->actingAs(User::factory()->create())
            ->get(route('kitchen.profiles.edit', [$profile, 'recipe' => $recipe->id]))
            ->assertOk()
            ->assertSee(route('kitchen.edit', $recipe), false)
            ->assertSee('Back to Pear Tart')
            ->assertSee('name="recipe" value="'.$recipe->id.'"', false);
    }

    public function test_profile_edit_page_defaults_to_the_profiles_index(): void
    {
        $profile = $this->makeProfile();

        $this->actingAs(User::factory()->create())
            ->get(route('kitchen.profiles.edit', $profile))
            ->assertOk()
            ->assertSee(route('kitchen.profiles.index'), false)
            ->assertSee('Back to Profiles')
            ->assertDontSee('name="recipe"', false);
    }

    public function test_saving_redirects_back_to_the_recipe(): void
    {
        $profile = $this->makeProfile();
        $recipe = $this->makeRecipe($profile);

        $this->actingAs(User::factory()->create())
            ->put(route('kitchen.profiles.update', $profile), $this->updatePayload() + ['recipe' => $recipe->id])
            ->assertRedirect(route('kitchen.edit', $recipe))
            ->assertSessionHas('success');

        $this->assertEqualsWithDelta(3.50, $profile->fresh()->manual_cost, 0.001);
    }

    public function test_saving_without_a_recipe_redirects_to_the_profiles_index(): void
    {
        $profile = $this->makeProfile();

        $this->actingAs(User::factory()->create())
            ->put(route('kitchen.profiles.update', $profile), $this->updatePayload())
            ->assertRedirect(route('kitchen.profiles.index'));
    }

    public function test_saving_with_an_unknown_recipe_is_rejected(): void
    {
        $profile = $this->makeProfile();

        $this->actingAs(User::factory()->create())
            ->from(route('kitchen.profiles.edit', $profile))
            ->put(route('kitchen.profiles.update', $profile), $this->updatePayload() + ['recipe' => 999])
            ->assertRedirect(route('kitchen.profiles.edit', $profile))
            ->assertSessionHasErrors('recipe');

        $this->assertEqualsWithDelta(3.00, $profile->fresh()->manual_cost, 0.001);
    }
}
