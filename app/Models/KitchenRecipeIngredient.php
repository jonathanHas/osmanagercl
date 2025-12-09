<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenRecipeIngredient extends Model
{
    protected $table = 'kitchen_recipe_ingredients';

    protected $fillable = [
        'recipe_id',
        'ingredient_profile_id',
        'pos_product_id',
        'quantity',
        'unit_type',
        'waste_factor',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'waste_factor' => 'decimal:2',
    ];

    /**
     * Common unit types for ingredients.
     */
    public const UNIT_TYPES = [
        'kg' => 'Kilograms',
        'g' => 'Grams',
        'L' => 'Litres',
        'ml' => 'Millilitres',
        'unit' => 'Units',
        'slice' => 'Slices',
        'portion' => 'Portions',
        'tbsp' => 'Tablespoons',
        'tsp' => 'Teaspoons',
    ];

    /**
     * Get the recipe this ingredient belongs to.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(KitchenRecipe::class, 'recipe_id');
    }

    /**
     * Get the ingredient profile (for unit conversion).
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(KitchenIngredientProfile::class, 'ingredient_profile_id');
    }

    /**
     * Get the POS product (the ingredient itself).
     * Uses profile's product if available, otherwise direct product reference.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'pos_product_id', 'ID');
    }

    /**
     * Get the effective product (from profile or direct).
     */
    public function getEffectiveProduct(): ?Product
    {
        return $this->profile?->product ?? $this->product;
    }

    /**
     * Check if this ingredient has a profile for accurate costing.
     */
    public function hasProfile(): bool
    {
        return $this->ingredient_profile_id !== null;
    }

    /**
     * Get the unit cost from PRICEBUY.
     * Returns raw cost (legacy behavior for non-profile ingredients).
     */
    public function getUnitCost(): float
    {
        $product = $this->getEffectiveProduct();

        return $product?->PRICEBUY ?? 0;
    }

    /**
     * Get the line cost with profile-aware calculation.
     * If profile exists, uses proper unit conversion.
     * Otherwise falls back to legacy calculation.
     */
    public function getLineCost(): float
    {
        $wasteFactor = 1 + ($this->waste_factor / 100);

        // If has profile, use profile-based calculation with unit conversion
        if ($this->hasProfile() && $this->profile) {
            $baseCost = $this->profile->calculateCost($this->quantity, $this->unit_type);

            return $baseCost * $wasteFactor;
        }

        // Fallback: legacy calculation (assumes SupplierLink.Cost matches recipe unit)
        $unitCost = $this->getUnitCost();

        return $unitCost * $this->quantity * $wasteFactor;
    }

    /**
     * Get the cost calculation method used.
     */
    public function getCostMethodAttribute(): string
    {
        return $this->hasProfile() ? 'profile' : 'legacy';
    }

    /**
     * Get the product/ingredient name.
     */
    public function getProductNameAttribute(): string
    {
        // If has profile, use profile name (works for both linked and manual profiles)
        if ($this->hasProfile() && $this->profile) {
            return $this->profile->name;
        }

        // Fallback to direct product
        return $this->product?->NAME ?? 'Unknown Product';
    }

    /**
     * Get formatted quantity with unit.
     */
    public function getFormattedQuantityAttribute(): string
    {
        return number_format($this->quantity, 2).' '.$this->unit_type;
    }

    /**
     * Get the unit type label.
     */
    public function getUnitTypeLabelAttribute(): string
    {
        return self::UNIT_TYPES[$this->unit_type] ?? $this->unit_type;
    }
}
