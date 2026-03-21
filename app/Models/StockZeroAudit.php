<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockZeroAudit extends Model
{
    protected $fillable = [
        'category_id',
        'category_name',
        'reference_date',
        'products_zeroed',
        'total_stock_value_zeroed',
        'product_details',
        'user_id',
    ];

    protected $casts = [
        'reference_date' => 'date',
        'products_zeroed' => 'integer',
        'total_stock_value_zeroed' => 'decimal:2',
        'product_details' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
