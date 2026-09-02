<?php

namespace App\Http\Controllers;

use App\Models\KitchenIngredientProfile;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KitchenIngredientProfileController extends Controller
{
    /**
     * Display profiles listing.
     */
    public function index(Request $request): View
    {
        $search = $request->get('search');

        $query = KitchenIngredientProfile::with('product.supplierLink');

        if ($search) {
            $query->search($search);
        }

        $profiles = $query->orderBy('name')->paginate(20);

        return view('kitchen.profiles.index', [
            'profiles' => $profiles,
            'search' => $search,
        ]);
    }

    /**
     * Show create profile form.
     */
    public function create(Request $request): View
    {
        $prefillProduct = null;
        if ($productId = $request->get('product_id')) {
            $prefillProduct = Product::find($productId);
        }

        return view('kitchen.profiles.create', [
            'unitTypes' => KitchenIngredientProfile::UNIT_CONVERSIONS,
            'unitCategories' => KitchenIngredientProfile::UNIT_CATEGORIES,
            'prefillProduct' => $prefillProduct,
        ]);
    }

    /**
     * Store a new profile.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'pos_product_id' => 'nullable|string|unique:kitchen_ingredient_profiles,pos_product_id',
            'manual_cost' => 'nullable|numeric|min:0',
            'name' => 'required|string|max:255',
            'purchase_quantity' => 'required|numeric|min:0.0001',
            'purchase_unit' => 'required|string|in:kg,g,L,ml,tbsp,tsp,unit,dozen,pack',
            'density' => 'nullable|numeric|min:0|max:10',
            'organic_status' => 'required|in:organic,non_organic',
            'unit_weight_grams' => 'nullable|numeric|min:0',
            'apply_delivery_markup' => 'nullable|boolean',
            'delivery_markup_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        // Convert checkbox value
        $validated['apply_delivery_markup'] = $request->boolean('apply_delivery_markup');

        // Require either a linked product OR manual cost
        if (empty($validated['pos_product_id']) && empty($validated['manual_cost'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Either a POS product or manual cost is required.',
                    'errors' => ['manual_cost' => ['Either link a POS product or enter a manual cost.']],
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['manual_cost' => 'Either link a POS product or enter a manual cost.']);
        }

        // Set base unit based on purchase unit
        $validated['base_unit'] = KitchenIngredientProfile::getBaseUnit($validated['purchase_unit']);
        $validated['cost_per_base_unit'] = 0; // Will be calculated

        $profile = KitchenIngredientProfile::create($validated);

        // Calculate and save cost per base unit
        $profile->recalculateCost();

        // Return JSON for AJAX requests
        if ($request->expectsJson()) {
            return response()->json([
                'id' => $profile->id,
                'name' => $profile->name,
                'pos_product_id' => $profile->pos_product_id,
                'purchase_size' => $profile->formatted_purchase_size,
                'cost_per_base_unit' => $profile->cost_per_base_unit,
                'base_unit' => $profile->base_unit,
                'supplier_cost' => $profile->supplier_cost,
                'cost_source' => $profile->cost_source,
            ]);
        }

        return redirect()
            ->route('kitchen.profiles.index')
            ->with('success', 'Ingredient profile created. Cost per '.$profile->base_unit.': €'.number_format($profile->cost_per_base_unit, 6));
    }

    /**
     * Show edit profile form.
     */
    public function edit(KitchenIngredientProfile $profile): View
    {
        $profile->load('product.supplierLink');

        return view('kitchen.profiles.edit', [
            'profile' => $profile,
            'unitTypes' => KitchenIngredientProfile::UNIT_CONVERSIONS,
            'unitCategories' => KitchenIngredientProfile::UNIT_CATEGORIES,
        ]);
    }

    /**
     * Update a profile.
     */
    public function update(Request $request, KitchenIngredientProfile $profile): RedirectResponse
    {
        // Build validation rules - pos_product_id unique check should ignore current profile
        $rules = [
            'pos_product_id' => 'nullable|string|unique:kitchen_ingredient_profiles,pos_product_id,'.$profile->id,
            'manual_cost' => 'nullable|numeric|min:0',
            'name' => 'required|string|max:255',
            'purchase_quantity' => 'required|numeric|min:0.0001',
            'purchase_unit' => 'required|string|in:kg,g,L,ml,tbsp,tsp,unit,dozen,pack',
            'density' => 'nullable|numeric|min:0|max:10',
            'organic_status' => 'required|in:organic,non_organic',
            'unit_weight_grams' => 'nullable|numeric|min:0',
            'apply_delivery_markup' => 'nullable|boolean',
            'delivery_markup_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ];

        $validated = $request->validate($rules);

        // Convert checkbox value
        $validated['apply_delivery_markup'] = $request->boolean('apply_delivery_markup');

        // Require either a linked product OR manual cost
        if (empty($validated['pos_product_id']) && empty($validated['manual_cost'])) {
            return back()
                ->withInput()
                ->withErrors(['manual_cost' => 'Either link a POS product or enter a manual cost.']);
        }

        // Set base unit based on purchase unit
        $validated['base_unit'] = KitchenIngredientProfile::getBaseUnit($validated['purchase_unit']);

        $profile->update($validated);

        // Reload the product relationship and recalculate cost
        $profile->load('product');
        $profile->recalculateCost();

        return redirect()
            ->route('kitchen.profiles.index')
            ->with('success', 'Profile updated. Cost per '.$profile->base_unit.': €'.number_format($profile->cost_per_base_unit, 6));
    }

    /**
     * Delete a profile.
     */
    public function destroy(KitchenIngredientProfile $profile): RedirectResponse
    {
        $name = $profile->name;
        $profile->delete();

        return redirect()
            ->route('kitchen.profiles.index')
            ->with('success', "Profile '{$name}' deleted.");
    }

    /**
     * Recalculate cost for a profile.
     */
    public function recalculate(KitchenIngredientProfile $profile): RedirectResponse
    {
        $profile->load('product.supplierLink');
        $oldCost = $profile->cost_per_base_unit;
        $profile->recalculateCost();

        $change = $profile->cost_per_base_unit - $oldCost;
        $changeText = $change >= 0 ? '+'.number_format($change, 6) : number_format($change, 6);

        return redirect()
            ->route('kitchen.profiles.index')
            ->with('success', "Recalculated '{$profile->name}': €".number_format($profile->cost_per_base_unit, 6)."/{$profile->base_unit} ({$changeText})");
    }

    /**
     * Recalculate costs for every product-linked profile.
     */
    public function recalculateAll(): RedirectResponse
    {
        $profiles = KitchenIngredientProfile::whereNotNull('pos_product_id')
            ->with('product.supplierLink')
            ->get();

        $updated = 0;

        foreach ($profiles as $profile) {
            $old = (float) $profile->cost_per_base_unit;
            $new = $profile->calculateCostPerBaseUnit();

            // decimal:6 cast - anything below that rounds away to no visible change
            if (round($old, 6) === round($new, 6)) {
                continue;
            }

            $profile->recalculateCost();
            $updated++;
        }

        $message = $updated === 0
            ? 'All '.$profiles->count().' product-linked profiles were already up to date.'
            : "Recalculated {$updated} of {$profiles->count()} product-linked profiles.";

        return redirect()
            ->route('kitchen.profiles.index')
            ->with('success', $message);
    }

    /**
     * Search profiles (AJAX).
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->get('q', '');

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $profiles = KitchenIngredientProfile::with('product')
            ->search($search)
            ->limit(20)
            ->get()
            ->map(function ($profile) {
                return [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'pos_product_id' => $profile->pos_product_id,
                    'purchase_size' => $profile->formatted_purchase_size,
                    'cost_per_base_unit' => $profile->cost_per_base_unit,
                    'base_unit' => $profile->base_unit,
                    'supplier_cost' => $profile->supplier_cost,
                    'cost_source' => $profile->cost_source,
                    'has_linked_product' => $profile->hasLinkedProduct(),
                ];
            });

        return response()->json($profiles);
    }

    /**
     * Search products for profile creation (AJAX).
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $search = $request->get('q', '');

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        // Exclude products that already have profiles
        $existingProductIds = KitchenIngredientProfile::pluck('pos_product_id')->toArray();

        $products = Product::active()
            ->with('supplierLink')
            ->search($search)
            ->whereNotIn('ID', $existingProductIds)
            ->limit(20)
            ->get()
            ->map(function ($product) {
                $supplierId = $product->supplierLink?->SupplierID;
                $isImportedSupplier = $supplierId && in_array((int) $supplierId, KitchenIngredientProfile::IMPORTED_SUPPLIER_IDS);

                return [
                    'id' => $product->ID,
                    'name' => $product->NAME,
                    'code' => $product->CODE,
                    'cost' => $product->PRICEBUY ?? 0,
                    'supplier_id' => $supplierId,
                    'is_imported_supplier' => $isImportedSupplier,
                ];
            });

        return response()->json($products);
    }
}
