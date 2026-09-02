<?php

namespace App\Http\Controllers;

use App\Models\KitchenRecipe;
use App\Models\KitchenRecipeIngredient;
use App\Repositories\KitchenRepository;
use App\Services\KitchenCostingService;
use App\Services\KitchenOrganicRegistrationService;
use App\Services\OrganicRegistrationPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KitchenController extends Controller
{
    public function __construct(
        protected KitchenRepository $repository,
        protected KitchenCostingService $costingService
    ) {}

    /**
     * Display recipes listing.
     */
    public function index(Request $request, KitchenOrganicRegistrationService $registrationService): View
    {
        $search = $request->get('search');
        $recipes = $this->repository->getPaginatedRecipes(15, $search);

        // Calculate costs for each recipe
        $recipesWithCosts = $recipes->through(function ($recipe) {
            $recipe->calculated_costs = $this->costingService->calculateRecipeCost($recipe);

            return $recipe;
        });

        $stats = $this->costingService->getOverallStatistics();

        return view('kitchen.index', [
            'recipes' => $recipesWithCosts,
            'search' => $search,
            'stats' => $stats,
            'organicReadiness' => $registrationService->summarise($recipesWithCosts->getCollection()),
        ]);
    }

    /**
     * Show create recipe form.
     */
    public function create(): View
    {
        return view('kitchen.create', [
            'unitTypes' => KitchenRecipeIngredient::UNIT_TYPES,
        ]);
    }

    /**
     * Store a new recipe.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prep_time' => 'nullable|integer|min:0',
            'cook_time' => 'nullable|integer|min:0',
            'portions_produced' => 'required|integer|min:1',
            'pos_product_id' => 'nullable|string',
            'notes' => 'nullable|string',
            'labour_rate_override' => 'nullable|numeric|min:0',
            'electricity_rate_override' => 'nullable|numeric|min:0',
            'cooking_power_override' => 'nullable|numeric|min:0',
            'packaging_cost_per_portion' => 'nullable|numeric|min:0',
        ]);

        $recipe = $this->repository->create($validated);

        return redirect()
            ->route('kitchen.edit', $recipe)
            ->with('success', 'Recipe created. Now add ingredients.');
    }

    /**
     * Display a recipe.
     */
    public function show(KitchenRecipe $recipe, KitchenOrganicRegistrationService $registrationService): View
    {
        $recipe = $this->repository->findById($recipe->id);
        $costs = $this->costingService->calculateRecipeCost($recipe);
        $trends = $this->costingService->getCostTrends($recipe);

        return view('kitchen.show', [
            'recipe' => $recipe,
            'costs' => $costs,
            'trends' => $trends,
            'organicWarnings' => $registrationService->build($recipe)['warnings'],
        ]);
    }

    /**
     * Download the Organic Trust "Multi-Ingredient Product Registration Form" for a recipe,
     * filled in from the recipe's ingredients and their suppliers' certification details.
     */
    public function organicRegistrationForm(
        KitchenRecipe $recipe,
        KitchenOrganicRegistrationService $registrationService,
        OrganicRegistrationPdfService $pdfService
    ) {
        $form = $registrationService->build($recipe);

        $pdf = $pdfService->generate($form['product_name'], $form['rows']);

        $filename = 'organic-registration-'.Str::slug($recipe->name).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Show edit recipe form.
     */
    public function edit(KitchenRecipe $recipe): View
    {
        $recipe = $this->repository->findById($recipe->id);
        $costs = $this->costingService->calculateRecipeCost($recipe);

        return view('kitchen.edit', [
            'recipe' => $recipe,
            'costs' => $costs,
            'unitTypes' => KitchenRecipeIngredient::UNIT_TYPES,
        ]);
    }

    /**
     * Update a recipe.
     */
    public function update(Request $request, KitchenRecipe $recipe): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prep_time' => 'nullable|integer|min:0',
            'cook_time' => 'nullable|integer|min:0',
            'portions_produced' => 'required|integer|min:1',
            'pos_product_id' => 'nullable|string',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
            'labour_rate_override' => 'nullable|numeric|min:0',
            'electricity_rate_override' => 'nullable|numeric|min:0',
            'cooking_power_override' => 'nullable|numeric|min:0',
            'packaging_cost_per_portion' => 'nullable|numeric|min:0',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // Convert empty strings to null for override fields
        foreach (['labour_rate_override', 'electricity_rate_override', 'cooking_power_override', 'packaging_cost_per_portion'] as $field) {
            if (isset($validated[$field]) && $validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        $this->repository->update($recipe, $validated);

        return redirect()
            ->route('kitchen.edit', $recipe)
            ->with('success', 'Recipe updated successfully.');
    }

    /**
     * Delete a recipe.
     */
    public function destroy(KitchenRecipe $recipe): RedirectResponse
    {
        $this->repository->delete($recipe);

        return redirect()
            ->route('kitchen.index')
            ->with('success', 'Recipe deleted successfully.');
    }

    /**
     * Add an ingredient to a recipe.
     */
    public function addIngredient(Request $request, KitchenRecipe $recipe): RedirectResponse
    {
        $validated = $request->validate([
            'pos_product_id' => 'required_without:ingredient_profile_id|nullable|string',
            'ingredient_profile_id' => 'nullable|exists:kitchen_ingredient_profiles,id',
            'quantity' => 'required|numeric|min:0.0001',
            'unit_type' => 'required|string',
            'waste_factor' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $validated['waste_factor'] = $validated['waste_factor'] ?? 0;

        // If profile is selected, get the product ID from the profile
        if (! empty($validated['ingredient_profile_id'])) {
            $profile = \App\Models\KitchenIngredientProfile::find($validated['ingredient_profile_id']);
            if ($profile) {
                $validated['pos_product_id'] = $profile->pos_product_id;
            }
        }

        $this->repository->addIngredient($recipe, $validated);

        return redirect()
            ->route('kitchen.edit', $recipe)
            ->with('success', 'Ingredient added.');
    }

    /**
     * Update an ingredient.
     */
    public function updateIngredient(Request $request, KitchenRecipeIngredient $ingredient): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
            'unit_type' => 'required|string',
            'waste_factor' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $validated['waste_factor'] = $validated['waste_factor'] ?? 0;

        $this->repository->updateIngredient($ingredient, $validated);

        return redirect()
            ->route('kitchen.edit', $ingredient->recipe_id)
            ->with('success', 'Ingredient updated.');
    }

    /**
     * Remove an ingredient.
     */
    public function removeIngredient(KitchenRecipeIngredient $ingredient): RedirectResponse
    {
        $recipeId = $ingredient->recipe_id;
        $this->repository->removeIngredient($ingredient);

        return redirect()
            ->route('kitchen.edit', $recipeId)
            ->with('success', 'Ingredient removed.');
    }

    /**
     * Search products for ingredient selection (AJAX).
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $search = $request->get('q', '');

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $products = $this->repository->searchProducts($search);

        return response()->json($products);
    }

    /**
     * Get recipe costs (AJAX).
     */
    public function getRecipeCosts(KitchenRecipe $recipe): JsonResponse
    {
        $recipe = $this->repository->findById($recipe->id);
        $costs = $this->costingService->calculateRecipeCost($recipe);

        return response()->json($costs);
    }

    /**
     * Recalculate and save cost history.
     */
    public function recalculateCosts(KitchenRecipe $recipe): JsonResponse
    {
        $recipe = $this->repository->findById($recipe->id);
        $history = $this->costingService->recordCostHistory($recipe);
        $costs = $this->costingService->calculateRecipeCost($recipe);

        return response()->json([
            'success' => true,
            'history_id' => $history->id,
            'costs' => $costs,
        ]);
    }

    /**
     * Save a scaled version of a recipe as a new recipe.
     */
    public function saveScaledRecipe(Request $request, KitchenRecipe $recipe): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'recipe_multiplier' => 'required|numeric|min:0.1|max:100',
            'labour_factor' => 'required|numeric|min:0.1|max:100',
            'electricity_factor' => 'required|numeric|min:0.1|max:100',
        ]);

        // Load the original recipe with all relationships
        $recipe = $this->repository->findById($recipe->id);

        $multiplier = $validated['recipe_multiplier'];
        $labourFactor = $validated['labour_factor'];
        $electricityFactor = $validated['electricity_factor'];

        // Apply the factors to the times, which is what actually drives cost:
        // prep time is pure labour, cook time drives electricity (and a slice
        // of labour via the supervision factor). Copying the times unchanged
        // would make the saved recipe recompute at 1x and contradict the
        // preview the user based their decision on.
        $scaledPrepTime = (int) round(($recipe->prep_time ?? 0) * $labourFactor);
        $scaledCookTime = (int) round(($recipe->cook_time ?? 0) * $electricityFactor);

        // Build notes with scaling info
        $notes = "Scaled from '{$recipe->name}' ({$multiplier}x batch). "
            ."Prep {$recipe->prep_time} → {$scaledPrepTime} min (labour factor {$labourFactor}x), "
            ."cook {$recipe->cook_time} → {$scaledCookTime} min (electricity factor {$electricityFactor}x).";

        // Create the new scaled recipe
        $newRecipe = $this->repository->create([
            'name' => $validated['name'],
            'description' => $recipe->description,
            'prep_time' => $scaledPrepTime,
            'cook_time' => $scaledCookTime,
            'portions_produced' => (int) round($recipe->portions_produced * $multiplier),
            'pos_product_id' => $recipe->pos_product_id,
            'is_active' => true,
            'notes' => $notes,
            'labour_rate_override' => $recipe->labour_rate_override,
            'electricity_rate_override' => $recipe->electricity_rate_override,
            'cooking_power_override' => $recipe->cooking_power_override,
            'packaging_cost_per_portion' => $recipe->packaging_cost_per_portion,
        ]);

        // Copy ingredients with scaled quantities
        foreach ($recipe->ingredients as $ingredient) {
            $this->repository->addIngredient($newRecipe, [
                'pos_product_id' => $ingredient->pos_product_id,
                'ingredient_profile_id' => $ingredient->ingredient_profile_id,
                'quantity' => $ingredient->quantity * $multiplier,
                'unit_type' => $ingredient->unit_type,
                'waste_factor' => $ingredient->waste_factor,
                'notes' => $ingredient->notes,
            ]);
        }

        return response()->json([
            'success' => true,
            'recipe_id' => $newRecipe->id,
            'redirect_url' => route('kitchen.edit', $newRecipe),
        ]);
    }
}
