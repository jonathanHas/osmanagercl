<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockValuationSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'valuation_date',
        'status',
        'calculated_total',
        'adjusted_total',
        'diagnostics',
        'notes',
        'created_by',
        'finalized_by',
        'finalized_at',
    ];

    protected $casts = [
        'valuation_date' => 'date',
        'calculated_total' => 'decimal:2',
        'adjusted_total' => 'decimal:2',
        'diagnostics' => 'array',
        'finalized_at' => 'datetime',
    ];

    /**
     * Get the categories for this snapshot.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(StockValuationCategory::class, 'snapshot_id');
    }

    /**
     * Get the items for this snapshot.
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockValuationItem::class, 'snapshot_id');
    }

    /**
     * Get the user who created this snapshot.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who finalized this snapshot.
     */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /**
     * Calculate and update totals from categories.
     */
    public function calculateTotals(): void
    {
        $calculatedTotal = $this->categories()->sum('calculated_value');

        // Adjusted total accounts for overrides
        $adjustedTotal = $this->categories()
            ->selectRaw('SUM(COALESCE(override_value, calculated_value)) as total')
            ->value('total') ?? 0;

        $this->update([
            'calculated_total' => $calculatedTotal,
            'adjusted_total' => $adjustedTotal != $calculatedTotal ? $adjustedTotal : null,
        ]);
    }

    /**
     * Get the final total (adjusted if set, otherwise calculated).
     */
    public function getFinalTotalAttribute(): float
    {
        return $this->adjusted_total ?? $this->calculated_total;
    }

    /**
     * Check if any categories have overrides.
     */
    public function getHasOverridesAttribute(): bool
    {
        return $this->categories()->whereNotNull('override_value')->exists();
    }

    /**
     * Check if snapshot can be modified.
     */
    public function canBeModified(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Finalize the snapshot.
     */
    public function finalize(?int $userId = null): void
    {
        if (! $this->canBeModified()) {
            throw new \Exception('Snapshot cannot be modified once finalized.');
        }

        $this->calculateTotals();
        $this->update([
            'status' => 'finalized',
            'finalized_by' => $userId ?? auth()->id(),
            'finalized_at' => now(),
        ]);
    }

    /**
     * Scope for draft snapshots.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope for finalized snapshots.
     */
    public function scopeFinalized($query)
    {
        return $query->where('status', 'finalized');
    }
}
