<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenIngredientProfile extends Model
{
    protected $table = 'kitchen_ingredient_profiles';

    protected $fillable = [
        'pos_product_id',
        'manual_cost',
        'name',
        'purchase_quantity',
        'purchase_unit',
        'cost_per_base_unit',
        'base_unit',
        'density',
        'apply_delivery_markup',
        'delivery_markup_percent',
        'notes',
    ];

    protected $casts = [
        'purchase_quantity' => 'decimal:4',
        'cost_per_base_unit' => 'decimal:6',
        'manual_cost' => 'decimal:2',
        'density' => 'decimal:4',
        'apply_delivery_markup' => 'boolean',
        'delivery_markup_percent' => 'decimal:2',
    ];

    /**
     * Supplier IDs that require delivery markup (imported products).
     * Udea: 5, 44, 85 | Dynamis: 56
     */
    public const IMPORTED_SUPPLIER_IDS = [5, 44, 85, 56];

    /**
     * Unit conversion constants.
     * Maps unit names to their base unit and multiplier.
     */
    public const UNIT_CONVERSIONS = [
        // Weight units → base: gram (g)
        'kg' => ['base' => 'g', 'multiplier' => 1000],
        'g' => ['base' => 'g', 'multiplier' => 1],

        // Volume units → base: millilitre (ml)
        'L' => ['base' => 'ml', 'multiplier' => 1000],
        'ml' => ['base' => 'ml', 'multiplier' => 1],
        'tbsp' => ['base' => 'ml', 'multiplier' => 15],
        'tsp' => ['base' => 'ml', 'multiplier' => 5],

        // Count units → base: unit
        'unit' => ['base' => 'unit', 'multiplier' => 1],
        'dozen' => ['base' => 'unit', 'multiplier' => 12],
        'pack' => ['base' => 'unit', 'multiplier' => 1],
    ];

    /**
     * Unit type categories for validation and UI.
     */
    public const UNIT_CATEGORIES = [
        'weight' => ['kg', 'g'],
        'volume' => ['L', 'ml', 'tbsp', 'tsp'],
        'count' => ['unit', 'dozen', 'pack'],
    ];

    /**
     * Get the POS product this profile is for.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'pos_product_id', 'ID');
    }

    /**
     * Check if this profile has a linked POS product.
     */
    public function hasLinkedProduct(): bool
    {
        return ! empty($this->pos_product_id);
    }

    /**
     * Check if this profile has a density value for weight↔volume conversions.
     */
    public function hasDensity(): bool
    {
        return $this->density !== null && $this->density > 0;
    }

    /**
     * Get the effective cost (from linked product or manual entry).
     * Includes delivery markup if enabled.
     */
    public function getEffectiveCost(): float
    {
        $baseCost = 0;

        // If linked to a product, use PRICEBUY
        if ($this->hasLinkedProduct() && $this->product) {
            $baseCost = $this->product->PRICEBUY ?? 0;
        } else {
            // Otherwise use manual cost
            $baseCost = $this->manual_cost ?? 0;
        }

        // Apply delivery markup if enabled
        if ($this->apply_delivery_markup && $this->delivery_markup_percent > 0) {
            $baseCost *= (1 + ($this->delivery_markup_percent / 100));
        }

        return $baseCost;
    }

    /**
     * Get the base cost without delivery markup (for display purposes).
     */
    public function getBaseCost(): float
    {
        if ($this->hasLinkedProduct() && $this->product) {
            return $this->product->PRICEBUY ?? 0;
        }

        return $this->manual_cost ?? 0;
    }

    /**
     * Check if the linked product is from an imported supplier (Udea/Dynamis).
     */
    public function isFromImportedSupplier(): bool
    {
        if (! $this->hasLinkedProduct()) {
            return false;
        }

        $product = $this->product()->with('supplier')->first();

        if (! $product || ! $product->supplier) {
            return false;
        }

        $supplierId = (int) $product->supplier->SupplierID;

        return in_array($supplierId, self::IMPORTED_SUPPLIER_IDS);
    }

    /**
     * Get the supplier name for the linked product.
     */
    public function getSupplierName(): ?string
    {
        if (! $this->hasLinkedProduct()) {
            return null;
        }

        $product = $this->product()->with('supplier')->first();

        return $product?->supplier?->Supplier;
    }

    /**
     * Get recipe ingredients using this profile.
     */
    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(KitchenRecipeIngredient::class, 'ingredient_profile_id');
    }

    /**
     * Get the base unit for a given unit.
     */
    public static function getBaseUnit(string $unit): string
    {
        return self::UNIT_CONVERSIONS[$unit]['base'] ?? 'unit';
    }

    /**
     * Get the multiplier to convert to base units.
     */
    public static function getMultiplier(string $unit): float
    {
        return self::UNIT_CONVERSIONS[$unit]['multiplier'] ?? 1;
    }

    /**
     * Get the unit category (weight, volume, count).
     */
    public static function getUnitCategory(string $unit): ?string
    {
        foreach (self::UNIT_CATEGORIES as $category => $units) {
            if (in_array($unit, $units)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Check if two units are compatible (same category).
     */
    public static function unitsAreCompatible(string $unit1, string $unit2): bool
    {
        $category1 = self::getUnitCategory($unit1);
        $category2 = self::getUnitCategory($unit2);

        return $category1 !== null && $category1 === $category2;
    }

    /**
     * Calculate the cost per base unit from effective cost (product or manual).
     */
    public function calculateCostPerBaseUnit(): float
    {
        $supplierCost = $this->getEffectiveCost();

        if ($supplierCost <= 0 || $this->purchase_quantity <= 0) {
            return 0;
        }

        $multiplier = self::getMultiplier($this->purchase_unit);
        $baseUnits = $this->purchase_quantity * $multiplier;

        return $baseUnits > 0 ? $supplierCost / $baseUnits : 0;
    }

    /**
     * Recalculate and save the cost per base unit.
     */
    public function recalculateCost(): self
    {
        $this->cost_per_base_unit = $this->calculateCostPerBaseUnit();
        $this->base_unit = self::getBaseUnit($this->purchase_unit);
        $this->save();

        return $this;
    }

    /**
     * Convert a quantity from one unit to base units.
     */
    public function convertToBaseUnits(float $quantity, string $fromUnit): float
    {
        $multiplier = self::getMultiplier($fromUnit);

        return $quantity * $multiplier;
    }

    /**
     * Calculate the cost for a given quantity and unit.
     * Supports cross-category conversion (weight↔volume) when density is set.
     */
    public function calculateCost(float $quantity, string $unit): float
    {
        $profileCategory = self::getUnitCategory($this->purchase_unit);
        $recipeCategory = self::getUnitCategory($unit);

        // Same category - direct conversion
        if ($profileCategory === $recipeCategory) {
            $baseUnits = $this->convertToBaseUnits($quantity, $unit);

            return $baseUnits * $this->cost_per_base_unit;
        }

        // Different categories - need density for weight↔volume conversion
        if (! $this->hasDensity()) {
            return 0; // Can't convert without density
        }

        // Only weight↔volume conversions are supported with density
        if (! $this->canConvertWithDensity($profileCategory, $recipeCategory)) {
            return 0;
        }

        // Convert recipe quantity to profile's base unit using density
        $quantityInProfileBase = $this->convertWithDensity($quantity, $unit);

        return $quantityInProfileBase * $this->cost_per_base_unit;
    }

    /**
     * Check if density conversion is possible between two categories.
     */
    private function canConvertWithDensity(?string $cat1, ?string $cat2): bool
    {
        if ($cat1 === null || $cat2 === null) {
            return false;
        }

        // Only weight↔volume conversions are supported
        return ($cat1 === 'weight' && $cat2 === 'volume') ||
               ($cat1 === 'volume' && $cat2 === 'weight');
    }

    /**
     * Convert quantity using density (g/ml).
     * Handles both volume→weight and weight→volume conversions.
     */
    private function convertWithDensity(float $quantity, string $fromUnit): float
    {
        $fromCategory = self::getUnitCategory($fromUnit);
        $toCategory = self::getUnitCategory($this->purchase_unit);

        // Convert input to base unit first (ml or g)
        $inBaseUnit = $quantity * self::getMultiplier($fromUnit);

        // Apply density conversion
        if ($fromCategory === 'volume' && $toCategory === 'weight') {
            // ml → g: multiply by density (g/ml)
            return $inBaseUnit * $this->density;
        } elseif ($fromCategory === 'weight' && $toCategory === 'volume') {
            // g → ml: divide by density
            return $inBaseUnit / $this->density;
        }

        return 0;
    }

    /**
     * Get formatted purchase size string.
     */
    public function getFormattedPurchaseSizeAttribute(): string
    {
        return number_format($this->purchase_quantity, 2).' '.$this->purchase_unit;
    }

    /**
     * Get formatted cost per base unit.
     */
    public function getFormattedCostPerBaseUnitAttribute(): string
    {
        return '€'.number_format($this->cost_per_base_unit, 6).'/'.$this->base_unit;
    }

    /**
     * Get the current supplier cost (from product PRICEBUY or manual cost).
     */
    public function getSupplierCostAttribute(): float
    {
        return $this->getEffectiveCost();
    }

    /**
     * Get the cost source indicator.
     */
    public function getCostSourceAttribute(): string
    {
        return $this->hasLinkedProduct() ? 'POS' : 'Manual';
    }

    /**
     * Scope to search profiles by name.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('name', 'like', '%'.$search.'%');
    }
}
