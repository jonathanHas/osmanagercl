<?php

namespace App\Repositories;

use App\Models\KitchenRecipe;
use App\Models\KitchenRecipeIngredient;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class KitchenRepository
{
    /**
     * Get all recipes with eager loading.
     */
    public function getAllRecipes(): Collection
    {
        return KitchenRecipe::with(['ingredients.product.supplierLink', 'ingredients.profile.product.supplierLink', 'product'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get active recipes.
     */
    public function getActiveRecipes(): Collection
    {
        return KitchenRecipe::active()
            ->with(['ingredients.product.supplierLink', 'ingredients.profile.product.supplierLink', 'product'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get paginated recipes.
     */
    public function getPaginatedRecipes(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        $query = KitchenRecipe::with(['ingredients.product.supplierLink', 'ingredients.profile.product.supplierLink', 'product']);

        if ($search) {
            $query->search($search);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Find a recipe by ID with all relationships.
     */
    public function findById(int $id): ?KitchenRecipe
    {
        return KitchenRecipe::with(['ingredients.product.supplierLink', 'ingredients.profile.product.supplierLink', 'product', 'costHistory'])
            ->find($id);
    }

    /**
     * Create a new recipe.
     */
    public function create(array $data): KitchenRecipe
    {
        return KitchenRecipe::create($data);
    }

    /**
     * Update a recipe.
     */
    public function update(KitchenRecipe $recipe, array $data): bool
    {
        return $recipe->update($data);
    }

    /**
     * Delete a recipe.
     */
    public function delete(KitchenRecipe $recipe): bool
    {
        return $recipe->delete();
    }

    /**
     * Add an ingredient to a recipe.
     */
    public function addIngredient(KitchenRecipe $recipe, array $ingredientData): KitchenRecipeIngredient
    {
        return $recipe->ingredients()->create($ingredientData);
    }

    /**
     * Update an ingredient.
     */
    public function updateIngredient(KitchenRecipeIngredient $ingredient, array $data): bool
    {
        return $ingredient->update($data);
    }

    /**
     * Remove an ingredient from a recipe.
     */
    public function removeIngredient(KitchenRecipeIngredient $ingredient): bool
    {
        return $ingredient->delete();
    }

    /**
     * Search POS products for ingredient selection.
     * Prioritizes exact code matches, then exact name matches, then partial matches.
     */
    public function searchProducts(string $search, int $limit = 50): Collection
    {
        return Product::active()
            ->search($search)
            ->orderByRaw("CASE
                WHEN CODE = ? THEN 0
                WHEN NAME = ? THEN 1
                WHEN NAME LIKE ? THEN 2
                ELSE 3
            END", [$search, $search, $search.'%'])
            ->orderBy('NAME')
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->ID,
                    'name' => $product->NAME,
                    'code' => $product->CODE,
                    'cost' => $product->PRICEBUY ?? 0,
                    'sell_price' => $product->PRICESELL,
                ];
            });
    }

    /**
     * Get recipes with low margins.
     */
    public function getRecipesWithLowMargins(float $threshold = 20): Collection
    {
        return $this->getAllRecipes()->filter(function ($recipe) use ($threshold) {
            // Calculate margin using the costing service logic
            $totalCost = $recipe->ingredients->sum(function ($ingredient) {
                return $ingredient->getLineCost();
            });

            if ($recipe->portions_produced > 0) {
                $costPerPortion = $totalCost / $recipe->portions_produced;
            } else {
                $costPerPortion = $totalCost;
            }

            $sellPrice = $recipe->product?->PRICESELL ?? 0;

            if ($sellPrice > 0) {
                $margin = (($sellPrice - $costPerPortion) / $sellPrice) * 100;

                return $margin < $threshold;
            }

            return false;
        });
    }

    /**
     * Get statistics for dashboard.
     */
    public function getStatistics(): array
    {
        $recipes = $this->getAllRecipes();

        $totalRecipes = $recipes->count();
        $activeRecipes = $recipes->where('is_active', true)->count();
        $linkedRecipes = $recipes->whereNotNull('pos_product_id')->count();

        return [
            'total_recipes' => $totalRecipes,
            'active_recipes' => $activeRecipes,
            'linked_recipes' => $linkedRecipes,
            'unlinked_recipes' => $totalRecipes - $linkedRecipes,
        ];
    }
}
