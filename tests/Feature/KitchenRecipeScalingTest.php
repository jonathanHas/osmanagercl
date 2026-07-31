<?php

namespace Tests\Feature;

use App\Models\KitchenRecipe;
use App\Models\KitchenRecipeCostHistory;
use App\Models\User;
use App\Services\KitchenCostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KitchenRecipeScalingTest extends TestCase
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

        config([
            'kitchen.labour_rate' => 15.00,
            'kitchen.electricity_rate' => 0.25,
            'kitchen.avg_cooking_power' => 2.0,
            'kitchen.cook_supervision_factor' => 0.10,
        ]);
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            Schema::connection('pos')->dropIfExists('PRODUCTS');
        }

        parent::tearDown();
    }

    private function makeRecipe(): KitchenRecipe
    {
        return KitchenRecipe::create([
            'name' => 'Mincemeat',
            'prep_time' => 30,
            'cook_time' => 30,
            'portions_produced' => 4,
            'is_active' => true,
            'packaging_cost_per_portion' => 0.50,
        ]);
    }

    private function scale(KitchenRecipe $recipe, array $overrides = []): array
    {
        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('kitchen.scale', $recipe), array_merge([
                'name' => 'Mincemeat (3x batch)',
                'recipe_multiplier' => 3,
                'labour_factor' => 2.0,
                'electricity_factor' => 1.5,
            ], $overrides));

        $response->assertOk()->assertJson(['success' => true]);

        return $response->json();
    }

    public function test_scaled_recipe_persists_the_scaled_times(): void
    {
        $recipe = $this->makeRecipe();

        $scaled = KitchenRecipe::find($this->scale($recipe)['recipe_id']);

        // prep 30 × 2.0 labour factor, cook 30 × 1.5 electricity factor
        $this->assertSame(60, $scaled->prep_time);
        $this->assertSame(45, $scaled->cook_time);
        $this->assertSame(12, $scaled->portions_produced);
    }

    public function test_saved_scaled_recipe_matches_what_the_preview_showed(): void
    {
        $recipe = $this->makeRecipe();
        $service = app(KitchenCostingService::class);

        $scaled = KitchenRecipe::find($this->scale($recipe)['recipe_id']);

        // The preview computes from scaled times, so the saved recipe must agree.
        // Labour: 60 prep + 10% of 45 cook = 64.5 min @ EUR15/hr = EUR16.125
        $this->assertEqualsWithDelta(64.5, $scaled->labour_minutes, 0.001);
        $this->assertEqualsWithDelta(16.125, $service->calculateLabourCost($scaled), 0.001);

        // Electricity: 45 min @ 2.0 kW, EUR0.25/kWh = EUR0.375
        $this->assertEqualsWithDelta(0.375, $service->calculateElectricityCost($scaled), 0.001);
    }

    public function test_factor_of_one_leaves_the_times_untouched(): void
    {
        $recipe = $this->makeRecipe();

        $scaled = KitchenRecipe::find($this->scale($recipe, [
            'labour_factor' => 1.0,
            'electricity_factor' => 1.0,
        ])['recipe_id']);

        $this->assertSame(30, $scaled->prep_time);
        $this->assertSame(30, $scaled->cook_time);
    }

    public function test_scaled_recipe_records_the_factors_in_its_notes(): void
    {
        $recipe = $this->makeRecipe();

        $scaled = KitchenRecipe::find($this->scale($recipe)['recipe_id']);

        $this->assertStringContainsString('30 → 60 min', $scaled->notes);
        $this->assertStringContainsString('30 → 45 min', $scaled->notes);
    }

    public function test_rate_overrides_are_carried_across_to_the_scaled_recipe(): void
    {
        $recipe = $this->makeRecipe();
        $recipe->update(['labour_rate_override' => 20.00, 'cooking_power_override' => 3.0]);

        $scaled = KitchenRecipe::find($this->scale($recipe)['recipe_id']);

        $this->assertEqualsWithDelta(20.00, $scaled->getLabourRate(), 0.001);
        $this->assertEqualsWithDelta(3.0, $scaled->getCookingPower(), 0.001);
        $this->assertEqualsWithDelta(0.50, (float) $scaled->packaging_cost_per_portion, 0.001);
    }

    public function test_cost_history_records_the_component_breakdown(): void
    {
        $recipe = $this->makeRecipe();

        $history = app(KitchenCostingService::class)->recordCostHistory($recipe);

        $this->assertTrue($history->hasBreakdown());
        // 30 prep + 10% of 30 cook = 33 min @ EUR15/hr = EUR8.25
        $this->assertEqualsWithDelta(8.25, (float) $history->labour_cost, 0.01);
        $this->assertEqualsWithDelta(33.0, (float) $history->labour_minutes, 0.01);
        $this->assertEqualsWithDelta(0.25, (float) $history->electricity_cost, 0.01);
        $this->assertEqualsWithDelta(2.00, (float) $history->packaging_cost, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $history->ingredient_cost, 0.01);

        // The components must reconcile to the stored total.
        $this->assertEqualsWithDelta(
            (float) $history->ingredient_cost + (float) $history->labour_cost
                + (float) $history->electricity_cost + (float) $history->packaging_cost,
            (float) $history->total_cost,
            0.01
        );

        $this->assertEqualsWithDelta(8.50, (float) $history->overhead_cost, 0.01);
    }

    public function test_legacy_history_rows_without_a_breakdown_are_flagged(): void
    {
        $recipe = $this->makeRecipe();

        $legacy = KitchenRecipeCostHistory::create([
            'recipe_id' => $recipe->id,
            'total_cost' => 10.00,
            'cost_per_portion' => 2.50,
            'recorded_at' => now(),
        ]);

        $this->assertFalse($legacy->hasBreakdown());
        $this->assertNull($legacy->overhead_cost);
    }
}
