<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KdsOrderItem extends Model
{
    protected $fillable = [
        'kds_order_id',
        'product_id',
        'product_name',
        'display_name',
        'quantity',
        'modifiers',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'modifiers' => 'array',
    ];

    public function kdsOrder(): BelongsTo
    {
        return $this->belongsTo(KdsOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'ID');
    }

    public function getFormattedQuantityAttribute(): string
    {
        if ($this->quantity == intval($this->quantity)) {
            return intval($this->quantity);
        }

        return number_format($this->quantity, 3, '.', '');
    }

    public function getDisplayNameAttribute($value): string
    {
        return $value ?: $this->product_name;
    }
}
