<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductActivityLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'product_code',
        'activity_type',
        'category',
        'old_value',
        'new_value',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Activity type constants
     */
    const TYPE_ADDED_TO_TILL = 'added_to_till';

    const TYPE_REMOVED_FROM_TILL = 'removed_from_till';

    const TYPE_PRICE_CHANGED = 'price_changed';

    const TYPE_DISPLAY_CHANGED = 'display_changed';

    const TYPE_COUNTRY_CHANGED = 'country_changed';

    /**
     * Get the product associated with this log entry.
     * Note: This is a cross-database relationship to the POS database.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'ID');
    }

    /**
     * Get the user who performed this action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope for recently added products.
     */
    public function scopeRecentlyAdded($query, $category = null, $days = 7)
    {
        $query->where('activity_type', self::TYPE_ADDED_TO_TILL)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc');

        if ($category) {
            $query->where('category', $category);
        }

        return $query;
    }

    /**
     * Scope for price changes.
     */
    public function scopePriceChanges($query, $category = null)
    {
        $query->where('activity_type', self::TYPE_PRICE_CHANGED);

        if ($category) {
            $query->where('category', $category);
        }

        return $query;
    }
}
