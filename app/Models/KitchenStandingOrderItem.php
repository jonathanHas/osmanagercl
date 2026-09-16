<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Standing weekly case quantity for one POS product. Pre-fill only: it is
 * read when the create-order page opens and never sent or logged by itself.
 */
class KitchenStandingOrderItem extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * The POS product (cross-connection).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'ID');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
