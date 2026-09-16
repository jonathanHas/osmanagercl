<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a kitchen order. supplier_code, product_name and case_units are
 * snapshots taken from POS data at confirm time; quantity is whole cases.
 */
class KitchenOrderItem extends Model
{
    protected $fillable = [
        'kitchen_order_id',
        'product_id',
        'supplier_code',
        'product_name',
        'case_units',
        'quantity',
    ];

    protected $casts = [
        'case_units' => 'integer',
        'quantity' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(KitchenOrder::class, 'kitchen_order_id');
    }

    /**
     * The POS product this line was snapshotted from (cross-connection).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'ID');
    }
}
