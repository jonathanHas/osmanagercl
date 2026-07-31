<?php

namespace App\Services;

use App\Models\KitchenRecipe;
use App\Models\KitchenRecipeCostHistory;
use Carbon\Carbon;

class KitchenCostingService
{
    /**
     * Margin thresholds for categorization.
     */
    public const MARGIN_EXCELLENT = 40;

    public const MARGIN_GOOD = 20;

    public const MARGIN_LOW = 10;

    /**
     * Calculate all costs for a recipe.
     */
    public function calculateRecipeCost(KitchenRecipe $recipe): array
    {
        // Ensure ingredients are loaded with products, supplier links and profiles
        $recipe->loadMissing(['ingredients.product.supplierLink', 'ingredients.profile.product.supplierLink', 'product']);

        $ingredientCost = 0;
        $ingredientCosts = [];
        $profiledCount = 0;
        $legacyCount = 0;

        foreach ($recipe->ingredients as $ingredient) {
            $unitCost = $ingredient->getUnitCost();
            $lineCost = $ingredient->getLineCost();
            $hasProfile = $ingredient->hasProfile();

            if ($hasProfile) {
                $profiledCount++;
            } else {
                $legacyCount++;
            }

            $ingredientCosts[] = [
                'id' => $ingredient->id,
                'product_id' => $ingredient->pos_product_id,
                'product_name' => $ingredient->product_name,
                'quantity' => $ingredient->quantity,
                'unit_type' => $ingredient->unit_type,
                'waste_factor' => $ingredient->waste_factor,
                'unit_cost' => $unitCost,
                'line_cost' => $lineCost,
                'has_profile' => $hasProfile,
                'cost_method' => $ingredient->cost_method,
            ];

            $ingredientCost += $lineCost;
        }

        // Calculate overhead costs (labour + electricity)
        $labourCost = $this->calculateLabourCost($recipe);
        $electricityCost = $this->calculateElectricityCost($recipe);
        $overheadCost = $labourCost + $electricityCost;

        // Calculate packaging cost (per portion × portions)
        $packagingCostPerPortion = $recipe->getPackagingCost();
        $packagingCost = $packagingCostPerPortion * $recipe->portions_produced;

        // Total cost includes ingredients + overhead + packaging
        $totalCost = $ingredientCost + $overheadCost + $packagingCost;

        $costPerPortion = $recipe->portions_produced > 0
            ? $totalCost / $recipe->portions_produced
            : $totalCost;

        $sellPrice = $recipe->product?->PRICESELL ?? 0;

        $margin = $sellPrice > 0
            ? (($sellPrice - $costPerPortion) / $sellPrice) * 100
            : null;

        $profit = $sellPrice > 0 ? $sellPrice - $costPerPortion : null;

        // Determine cost accuracy based on profile coverage
        $totalIngredients = $profiledCount + $legacyCount;
        $costAccuracy = $totalIngredients > 0
            ? ($profiledCount / $totalIngredients) * 100
            : 100;

        return [
            'ingredient_cost' => round($ingredientCost, 2),
            'labour_cost' => round($labourCost, 2),
            'labour_minutes' => round($recipe->labour_minutes, 1),
            'electricity_cost' => round($electricityCost, 2),
            'overhead_cost' => round($overheadCost, 2),
            'packaging_cost' => round($packagingCost, 2),
            'packaging_cost_per_portion' => round($packagingCostPerPortion, 2),
            'total_cost' => round($totalCost, 2),
            'cost_per_portion' => round($costPerPortion, 2),
            'sell_price' => round($sellPrice, 2),
            'margin_percentage' => $margin !== null ? round($margin, 1) : null,
            'margin_status' => $this->getMarginStatus($margin),
            'profit_per_portion' => $profit !== null ? round($profit, 2) : null,
            'portions_produced' => $recipe->portions_produced,
            // Times and effective rates, so the batch scaling calculator can
            // recompute costs from scaled times instead of scaling the cost
            // figures - the saved recipe stores times, so the preview has to
            // model them to match what it will actually produce.
            'prep_time' => $recipe->prep_time ?? 0,
            'cook_time' => $recipe->cook_time ?? 0,
            'labour_rate' => $recipe->getLabourRate(),
            'electricity_rate' => $recipe->getElectricityRate(),
            'cooking_power' => $recipe->getCookingPower(),
            'cook_supervision_factor' => $recipe->getCookSupervisionFactor(),
            'has_linked_product' => $recipe->hasLinkedProduct(),
            'ingredient_costs' => $ingredientCosts,
            'profiled_ingredients' => $profiledCount,
            'legacy_ingredients' => $legacyCount,
            'cost_accuracy' => round($costAccuracy, 0),
            'is_fully_profiled' => $legacyCount === 0,
        ];
    }

    /**
     * Calculate labour cost based on prep time plus supervised cook time.
     *
     * Cooking is largely unattended, so only the supervision factor of the
     * cook time is charged as direct labour. The full cook time is still
     * charged as electricity in calculateElectricityCost().
     */
    public function calculateLabourCost(KitchenRecipe $recipe): float
    {
        $labourMinutes = $recipe->labour_minutes;

        if ($labourMinutes <= 0) {
            return 0;
        }

        $hourlyRate = $recipe->getLabourRate();

        return ($labourMinutes / 60) * $hourlyRate;
    }

    /**
     * Calculate electricity cost based on cook time.
     */
    public function calculateElectricityCost(KitchenRecipe $recipe): float
    {
        $cookMinutes = $recipe->cook_time ?? 0;

        if ($cookMinutes <= 0) {
            return 0;
        }

        $powerKw = $recipe->getCookingPower();
        $ratePerKwh = $recipe->getElectricityRate();

        return ($cookMinutes / 60) * $powerKw * $ratePerKwh;
    }

    /**
     * Get margin status label.
     */
    public function getMarginStatus(?float $margin): string
    {
        if ($margin === null) {
            return 'unknown';
        }

        if ($margin >= self::MARGIN_EXCELLENT) {
            return 'excellent';
        }
        if ($margin >= self::MARGIN_GOOD) {
            return 'good';
        }
        if ($margin >= self::MARGIN_LOW) {
            return 'low';
        }

        return 'critical';
    }

    /**
     * Get CSS class for margin status.
     */
    public function getMarginStatusClass(?float $margin): string
    {
        return match ($this->getMarginStatus($margin)) {
            'excellent' => 'text-green-600 bg-green-100',
            'good' => 'text-yellow-600 bg-yellow-100',
            'low' => 'text-orange-600 bg-orange-100',
            'critical' => 'text-red-600 bg-red-100',
            default => 'text-gray-600 bg-gray-100',
        };
    }

    /**
     * Record cost history snapshot.
     */
    public function recordCostHistory(KitchenRecipe $recipe): KitchenRecipeCostHistory
    {
        $costs = $this->calculateRecipeCost($recipe);

        return KitchenRecipeCostHistory::create([
            'recipe_id' => $recipe->id,
            'ingredient_cost' => $costs['ingredient_cost'],
            'labour_cost' => $costs['labour_cost'],
            'labour_minutes' => $costs['labour_minutes'],
            'electricity_cost' => $costs['electricity_cost'],
            'packaging_cost' => $costs['packaging_cost'],
            'total_cost' => $costs['total_cost'],
            'cost_per_portion' => $costs['cost_per_portion'],
            'sell_price' => $costs['sell_price'],
            'margin_percentage' => $costs['margin_percentage'],
            'recorded_at' => Carbon::now(),
        ]);
    }

    /**
     * Bulk recalculate and record history for all recipes.
     */
    public function bulkRecordCostHistory(): int
    {
        $recipes = KitchenRecipe::with(['ingredients.product.supplierLink', 'ingredients.profile.product.supplierLink', 'product'])->get();
        $count = 0;

        foreach ($recipes as $recipe) {
            $this->recordCostHistory($recipe);
            $count++;
        }

        return $count;
    }

    /**
     * Get cost trends for a recipe.
     */
    public function getCostTrends(KitchenRecipe $recipe, int $days = 30): array
    {
        $history = $recipe->costHistory()
            ->where('recorded_at', '>=', Carbon::now()->subDays($days))
            ->orderBy('recorded_at')
            ->get();

        return [
            'dates' => $history->pluck('recorded_at')->map(fn ($d) => $d->format('Y-m-d'))->toArray(),
            'total_costs' => $history->pluck('total_cost')->toArray(),
            'cost_per_portions' => $history->pluck('cost_per_portion')->toArray(),
            'margins' => $history->pluck('margin_percentage')->toArray(),
            // Component series are null for snapshots taken before the
            // breakdown columns existed - chart them as gaps, not zeroes.
            'ingredient_costs' => $history->pluck('ingredient_cost')->toArray(),
            'labour_costs' => $history->pluck('labour_cost')->toArray(),
            'electricity_costs' => $history->pluck('electricity_cost')->toArray(),
            'packaging_costs' => $history->pluck('packaging_cost')->toArray(),
        ];
    }

    /**
     * Get summary statistics across all recipes.
     */
    public function getOverallStatistics(): array
    {
        $recipes = KitchenRecipe::with(['ingredients.product.supplierLink', 'ingredients.profile.product.supplierLink', 'product'])->get();

        $stats = [
            'total_recipes' => $recipes->count(),
            'excellent_margin' => 0,
            'good_margin' => 0,
            'low_margin' => 0,
            'critical_margin' => 0,
            'unknown_margin' => 0,
            'average_margin' => null,
            'fully_profiled_recipes' => 0,
            'partially_profiled_recipes' => 0,
            'unprofiled_recipes' => 0,
        ];

        $margins = [];

        foreach ($recipes as $recipe) {
            $costs = $this->calculateRecipeCost($recipe);
            $status = $costs['margin_status'];

            $stats[$status.'_margin']++;

            if ($costs['margin_percentage'] !== null) {
                $margins[] = $costs['margin_percentage'];
            }

            // Track profile coverage
            if ($costs['is_fully_profiled']) {
                $stats['fully_profiled_recipes']++;
            } elseif ($costs['profiled_ingredients'] > 0) {
                $stats['partially_profiled_recipes']++;
            } else {
                $stats['unprofiled_recipes']++;
            }
        }

        if (count($margins) > 0) {
            $stats['average_margin'] = round(array_sum($margins) / count($margins), 1);
        }

        return $stats;
    }
}
