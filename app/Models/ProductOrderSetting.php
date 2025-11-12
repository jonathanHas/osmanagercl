<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOrderSetting extends Model
{
    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'review_priority',
        'auto_approve',
        'safety_stock_factor',
        'min_stock_override',
        'min_order_quantity',
        'max_order_quantity',
        'shelf_life_days',
        'is_short_dated',
        'notes',
        'last_updated',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'safety_stock_factor' => 'decimal:2',
        'min_stock_override' => 'decimal:2',
        'min_order_quantity' => 'decimal:2',
        'max_order_quantity' => 'decimal:2',
        'shelf_life_days' => 'integer',
        'is_short_dated' => 'boolean',
        'auto_approve' => 'boolean',
    ];
}
