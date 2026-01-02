<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockValuationCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'snapshot_id',
        'category_id',
        'category_name',
        'product_count',
        'calculated_value',
        'override_value',
        'override_reason',
    ];

    protected $casts = [
        'product_count' => 'integer',
        'calculated_value' => 'decimal:2',
        'override_value' => 'decimal:2',
    ];

    /**
     * Get the snapshot for this category.
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(StockValuationSnapshot::class, 'snapshot_id');
    }

    /**
     * Get the items for this category.
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockValuationItem::class, 'category_record_id');
    }

    /**
     * Get the final value (override if set, otherwise calculated).
     */
    public function getFinalValueAttribute(): float
    {
        return $this->override_value ?? $this->calculated_value;
    }

    /**
     * Check if this category has an override.
     */
    public function getHasOverrideAttribute(): bool
    {
        return $this->override_value !== null;
    }

    /**
     * Set an override value.
     */
    public function setOverride(float $value, ?string $reason = null): void
    {
        if (! $this->snapshot->canBeModified()) {
            throw new \Exception('Cannot modify a finalized snapshot.');
        }

        $this->update([
            'override_value' => $value,
            'override_reason' => $reason,
        ]);

        $this->snapshot->calculateTotals();
    }

    /**
     * Clear the override value.
     */
    public function clearOverride(): void
    {
        if (! $this->snapshot->canBeModified()) {
            throw new \Exception('Cannot modify a finalized snapshot.');
        }

        $this->update([
            'override_value' => null,
            'override_reason' => null,
        ]);

        $this->snapshot->calculateTotals();
    }
}
