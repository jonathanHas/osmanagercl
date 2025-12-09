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
        'total_cost',
        'cost_per_portion',
        'sell_price',
        'margin_percentage',
        'recorded_at',
    ];

    protected $casts = [
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
