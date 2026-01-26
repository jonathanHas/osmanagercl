<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryBarrel extends Model
{
    protected $fillable = [
        'delivery_id',
        'barrel_code_id',
        'supplier_code',
        'description',
        'quantity',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function barrelCode(): BelongsTo
    {
        return $this->belongsTo(BarrelCode::class);
    }
}
