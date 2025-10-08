<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAdjustment extends Model
{
    // Disable updated_at as the table only has created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'user_id',
        'original_quantity',
        'adjusted_quantity',
        'adjustment_factor',
        'context_data',
        'order_date',
        'reason',
    ];

    protected $casts = [
        'order_date' => 'date',
        'original_quantity' => 'float',
        'adjusted_quantity' => 'float',
        'adjustment_factor' => 'float',
        'context_data' => 'json',
    ];
}
