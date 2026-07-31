<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenRecipeCostHistory extends Model
{
    protected $table = 'kitchen_recipe_cost_history';

    public $timestamps = false;

    protected $fillable = [
        'recipe_id',
        'ingredient_cost',
        'labour_cost',
        'labour_minutes',
        'electricity_cost',
        'packaging_cost',
        'total_cost',
        'cost_per_portion',
        'sell_price',
        'margin_percentage',
        'recorded_at',
    ];

    protected $casts = [
        'ingredient_cost' => 'decimal:2',
        'labour_cost' => 'decimal:2',
        'labour_minutes' => 'decimal:1',
        'electricity_cost' => 'decimal:2',
        'packaging_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'cost_per_portion' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    /**
     * Get the recipe this history belongs to.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(KitchenRecipe::class, 'recipe_id');
    }

    /**
     * Whether this snapshot captured the per-component cost breakdown.
     *
     * Rows recorded before the breakdown columns existed only have totals.
     */
    public function hasBreakdown(): bool
    {
        return $this->total_cost !== null && $this->ingredient_cost !== null;
    }

    /**
     * Get the combined overhead (labour + electricity) for this snapshot.
     */
    public function getOverheadCostAttribute(): ?float
    {
        if (! $this->hasBreakdown()) {
            return null;
        }

        return (float) $this->labour_cost + (float) $this->electricity_cost;
    }

    /**
     * Get the margin status based on percentage.
     */
    public function getMarginStatusAttribute(): string
    {
        if ($this->margin_percentage === null) {
            return 'unknown';
        }

        if ($this->margin_percentage >= 40) {
            return 'excellent';
        }
        if ($this->margin_percentage >= 20) {
            return 'good';
        }
        if ($this->margin_percentage >= 10) {
            return 'low';
        }

        return 'critical';
    }
}
