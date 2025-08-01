<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_session_id',
        'product_id',
        'suggested_quantity',
        'final_quantity',
        'unit_cost',
        'total_cost',
        'case_units',
        'suggested_cases',
        'final_cases',
        'review_priority',
        'adjustment_reason',
        'auto_approved',
        'context_data',
    ];

    protected $casts = [
        'suggested_quantity' => 'decimal:3',
        'final_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'case_units' => 'integer',
        'suggested_cases' => 'decimal:3',
        'final_cases' => 'decimal:3',
        'auto_approved' => 'boolean',
        'context_data' => 'array',
    ];

    /**
     * Get the order session this item belongs to.
     */
    public function orderSession(): BelongsTo
    {
        return $this->belongsTo(OrderSession::class);
    }

    /**
     * Get the product for this order item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'ID');
    }

    /**
     * Calculate total cost based on final quantity and unit cost.
     */
    public function calculateTotalCost(): float
    {
        return $this->final_quantity * $this->unit_cost;
    }

    /**
     * Update total cost.
     */
    public function updateTotalCost(): void
    {
        $this->update(['total_cost' => $this->calculateTotalCost()]);
    }

    /**
     * Check if quantity was adjusted from suggestion.
     */
    public function wasAdjusted(): bool
    {
        return abs($this->final_quantity - $this->suggested_quantity) > 0.001;
    }

    /**
     * Get adjustment percentage.
     */
    public function getAdjustmentPercentage(): float
    {
        if ($this->suggested_quantity == 0) {
            return 0;
        }

        return (($this->final_quantity - $this->suggested_quantity) / $this->suggested_quantity) * 100;
    }

    /**
     * Get adjustment factor for learning.
     */
    public function getAdjustmentFactor(): float
    {
        if ($this->suggested_quantity == 0) {
            return 1.0;
        }

        return $this->final_quantity / $this->suggested_quantity;
    }

    /**
     * Scope for items requiring review.
     */
    public function scopeRequiresReview($query)
    {
        return $query->where('review_priority', 'review');
    }

    /**
     * Scope for safe items.
     */
    public function scopeSafe($query)
    {
        return $query->where('review_priority', 'safe');
    }

    /**
     * Scope for standard items.
     */
    public function scopeStandard($query)
    {
        return $query->where('review_priority', 'standard');
    }

    /**
     * Scope for auto-approved items.
     */
    public function scopeAutoApproved($query)
    {
        return $query->where('auto_approved', true);
    }

    /**
     * Scope for adjusted items.
     */
    public function scopeAdjusted($query)
    {
        return $query->whereRaw('ABS(final_quantity - suggested_quantity) > 0.001');
    }

    /**
     * Check if this product is ordered by cases (case_units > 1).
     */
    public function isOrderedByCases(): bool
    {
        return $this->case_units > 1;
    }

    /**
     * Get the display string for quantity (cases or units).
     */
    public function getQuantityDisplayString(): string
    {
        if ($this->isOrderedByCases()) {
            return "{$this->final_cases} cases ({$this->final_quantity} units)";
        }

        return "{$this->final_quantity} units";
    }

    /**
     * Get the suggested quantity display string.
     */
    public function getSuggestedQuantityDisplayString(): string
    {
        if ($this->isOrderedByCases()) {
            return "{$this->suggested_cases} cases ({$this->suggested_quantity} units)";
        }

        return "{$this->suggested_quantity} units";
    }

    /**
     * Update final cases and recalculate final quantity.
     */
    public function updateFinalCases(float $cases): void
    {
        $this->final_cases = $cases;
        $this->final_quantity = $cases * $this->case_units;
        $this->total_cost = $this->final_quantity * $this->unit_cost;
        $this->save();
    }
}
