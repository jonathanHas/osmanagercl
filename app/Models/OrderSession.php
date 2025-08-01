<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderSession extends Model
{
    protected $fillable = [
        'user_id',
        'supplier_id',
        'order_date',
        'status',
        'total_items',
        'total_value',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'total_value' => 'decimal:2',
    ];

    /**
     * Get the user who created this order session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the supplier for this order session.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'SupplierID');
    }

    /**
     * Get all order items for this session.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get items that require review.
     */
    public function reviewItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)->where('review_priority', 'review');
    }

    /**
     * Get items that are safe to auto-approve.
     */
    public function safeItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)->where('review_priority', 'safe');
    }

    /**
     * Get standard items.
     */
    public function standardItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)->where('review_priority', 'standard');
    }

    /**
     * Calculate total value from items.
     */
    public function calculateTotalValue(): float
    {
        return $this->items()->sum('total_cost');
    }

    /**
     * Update total items and value.
     */
    public function updateTotals(): void
    {
        $this->update([
            'total_items' => $this->items()->count(),
            'total_value' => $this->calculateTotalValue(),
        ]);
    }

    /**
     * Check if order is editable.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft']);
    }

    /**
     * Scope for active orders.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'submitted']);
    }

    /**
     * Scope for completed orders.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
