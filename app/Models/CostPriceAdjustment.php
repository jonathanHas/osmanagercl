<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records a change to a POS product's cost price (PRODUCTS.PRICEBUY).
 *
 * The POS database keeps no history of its own, so this is the only record of
 * what a cost price was before it was adjusted.
 */
class CostPriceAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_code',
        'product_name',
        'old_cost',
        'new_cost',
        'sell_price',
        'divisor',
        'source',
        'user_id',
    ];

    protected $casts = [
        'old_cost' => 'decimal:4',
        'new_cost' => 'decimal:4',
        'sell_price' => 'decimal:4',
        'divisor' => 'decimal:4',
    ];

    /**
     * The user who made the adjustment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The POS product this adjustment applies to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'ID');
    }

    /**
     * Scope to adjustments made from the stock valuation anomaly panel.
     */
    public function scopeFromValuationAnomaly($query)
    {
        return $query->where('source', 'valuation_anomaly');
    }
}
