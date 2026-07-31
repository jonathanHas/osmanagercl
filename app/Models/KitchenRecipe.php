<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenRecipe extends Model
{
    protected $table = 'kitchen_recipes';

    protected $fillable = [
        'name',
        'description',
        'prep_time',
        'cook_time',
        'portions_produced',
        'pos_product_id',
        'is_active',
        'notes',
        'labour_rate_override',
        'electricity_rate_override',
        'cooking_power_override',
        'packaging_cost_per_portion',
    ];

    protected $casts = [
        'prep_time' => 'integer',
        'cook_time' => 'integer',
        'portions_produced' => 'integer',
        'is_active' => 'boolean',
        'labour_rate_override' => 'decimal:2',
        'electricity_rate_override' => 'decimal:4',
        'cooking_power_override' => 'decimal:2',
        'packaging_cost_per_portion' => 'decimal:2',
    ];

    /**
     * Get the POS product this recipe creates (the finished item).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'pos_product_id', 'ID');
    }

    /**
     * Get the ingredients for this recipe.
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(KitchenRecipeIngredient::class, 'recipe_id');
    }

    /**
     * Get the cost history for this recipe.
     */
    public function costHistory(): HasMany
    {
        return $this->hasMany(KitchenRecipeCostHistory::class, 'recipe_id')->orderByDesc('recorded_at');
    }

    /**
     * Get the total time (prep + cook) in minutes.
     */
    public function getTotalTimeAttribute(): int
    {
        return ($this->prep_time ?? 0) + ($this->cook_time ?? 0);
    }

    /**
     * Get the chargeable labour time in minutes.
     *
     * Prep time is fully attended. Cook time is largely unattended, so only
     * the supervision factor (default 10%) is charged as direct labour - the
     * full cook time is still charged as electricity.
     */
    public function getLabourMinutesAttribute(): float
    {
        return ($this->prep_time ?? 0) + (($this->cook_time ?? 0) * $this->getCookSupervisionFactor());
    }

    /**
     * Get the formatted total time.
     */
    public function getFormattedTotalTimeAttribute(): string
    {
        $total = $this->total_time;

        if ($total < 60) {
            return $total.' min';
        }

        $hours = floor($total / 60);
        $minutes = $total % 60;

        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
    }

    /**
     * Scope to only active recipes.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to search recipes by name.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('name', 'like', '%'.$search.'%');
    }

    /**
     * Check if recipe has a linked POS product.
     */
    public function hasLinkedProduct(): bool
    {
        return ! empty($this->pos_product_id);
    }

    /**
     * Get the effective labour rate (override or config default).
     */
    public function getLabourRate(): float
    {
        return $this->labour_rate_override ?? config('kitchen.labour_rate', 15.00);
    }

    /**
     * Get the effective electricity rate (override or config default).
     */
    public function getElectricityRate(): float
    {
        return $this->electricity_rate_override ?? config('kitchen.electricity_rate', 0.25);
    }

    /**
     * Get the effective cooking power (override or config default).
     */
    public function getCookingPower(): float
    {
        return $this->cooking_power_override ?? config('kitchen.avg_cooking_power', 2.0);
    }

    /**
     * Get the portion of cook time charged as attended labour.
     */
    public function getCookSupervisionFactor(): float
    {
        return (float) config('kitchen.cook_supervision_factor', 0.10);
    }

    /**
     * Check if this recipe has any rate overrides or packaging cost.
     */
    public function hasRateOverrides(): bool
    {
        return $this->labour_rate_override !== null
            || $this->electricity_rate_override !== null
            || $this->cooking_power_override !== null
            || $this->packaging_cost_per_portion !== null;
    }

    /**
     * Get the packaging cost per portion (or 0 if not set).
     */
    public function getPackagingCost(): float
    {
        return (float) ($this->packaging_cost_per_portion ?? 0);
    }
}
