<?php

namespace Tests\Unit;

use App\Models\KitchenRecipe;
use App\Services\KitchenCostingService;
use Tests\TestCase;

class KitchenCostingServiceTest extends TestCase
{
    private KitchenCostingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new KitchenCostingService;

        config([
            'kitchen.labour_rate' => 15.00,
            'kitchen.electricity_rate' => 0.25,
            'kitchen.avg_cooking_power' => 2.0,
            'kitchen.cook_supervision_factor' => 0.10,
        ]);
    }

    /**
     * Build an unsaved recipe - the labour and electricity calculations are
     * pure functions of the recipe attributes and never touch the database.
     */
    private function makeRecipe(?int $prepTime, ?int $cookTime): KitchenRecipe
    {
        $recipe = new KitchenRecipe;
        $recipe->prep_time = $prepTime;
        $recipe->cook_time = $cookTime;
        $recipe->portions_produced = 1;

        return $recipe;
    }

    public function test_cook_time_is_charged_at_the_supervision_factor_not_the_full_rate(): void
    {
        // 30 min prep + 10% of 45 min cook = 34.5 min @ EUR15/hr = EUR8.625
        $recipe = $this->makeRecipe(30, 45);

        $this->assertEqualsWithDelta(34.5, $recipe->labour_minutes, 0.001);
        $this->assertEqualsWithDelta(8.625, $this->service->calculateLabourCost($recipe), 0.001);
    }

    public function test_unattended_cook_only_recipe_charges_a_tenth_of_the_time(): void
    {
        // 10% of 60 min = 6 min @ EUR15/hr = EUR1.50
        $recipe = $this->makeRecipe(0, 60);

        $this->assertEqualsWithDelta(1.50, $this->service->calculateLabourCost($recipe), 0.001);
    }

    public function test_prep_only_recipe_is_unaffected_by_the_supervision_factor(): void
    {
        // 30 min @ EUR15/hr = EUR7.50
        $recipe = $this->makeRecipe(30, 0);

        $this->assertEqualsWithDelta(7.50, $this->service->calculateLabourCost($recipe), 0.001);
    }

    public function test_electricity_still_charges_the_full_cook_time(): void
    {
        // 45 min @ 2.0 kW, EUR0.25/kWh = EUR0.375 - unchanged by the labour split
        $recipe = $this->makeRecipe(30, 45);

        $this->assertEqualsWithDelta(0.375, $this->service->calculateElectricityCost($recipe), 0.001);
    }

    public function test_supervision_factor_is_configurable(): void
    {
        config(['kitchen.cook_supervision_factor' => 0.50]);

        // 30 min prep + 50% of 40 min cook = 50 min @ EUR15/hr = EUR12.50
        $recipe = $this->makeRecipe(30, 40);

        $this->assertEqualsWithDelta(50.0, $recipe->labour_minutes, 0.001);
        $this->assertEqualsWithDelta(12.50, $this->service->calculateLabourCost($recipe), 0.001);
    }

    public function test_recipe_with_no_times_has_no_labour_or_electricity_cost(): void
    {
        $recipe = $this->makeRecipe(null, null);

        $this->assertSame(0.0, (float) $this->service->calculateLabourCost($recipe));
        $this->assertSame(0.0, (float) $this->service->calculateElectricityCost($recipe));
    }

    public function test_labour_rate_override_applies_to_supervised_cook_time(): void
    {
        $recipe = $this->makeRecipe(30, 45);
        $recipe->labour_rate_override = 20.00;

        // 34.5 min @ EUR20/hr = EUR11.50
        $this->assertEqualsWithDelta(11.50, $this->service->calculateLabourCost($recipe), 0.001);
    }

    public function test_total_time_still_reports_the_full_recipe_duration(): void
    {
        // total_time is "how long this takes to make" and must not change
        $recipe = $this->makeRecipe(30, 45);

        $this->assertSame(75, $recipe->total_time);
        $this->assertSame('1h 15m', $recipe->formatted_total_time);
    }
}
