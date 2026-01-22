<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'barcode',
        'product_id',
        'old_stock',
        'new_stock',
        'adjustment',
        'user_id',
        'source',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_stock' => 'decimal:2',
        'new_stock' => 'decimal:2',
        'adjustment' => 'decimal:2',
    ];

    /**
     * Get the user who made the adjustment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
