<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A confirmed stock check from the /stock-review scanner: the quantity the
 * till held and the quantity counted. The check date itself still lives in
 * the POS stockLastChecked table (shared with the old system).
 */
class StockCheck extends Model
{
    protected $fillable = [
        'barcode',
        'product_id',
        'previous_stock',
        'counted_stock',
        'user_id',
    ];

    protected $casts = [
        'previous_stock' => 'decimal:2',
        'counted_stock' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
