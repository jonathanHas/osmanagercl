<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOrderSetting extends Model
{
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
        'min_order_quantity',
        'max_order_quantity',
        'shelf_life_days',
        'notes',
        'last_updated',
    ];
}
