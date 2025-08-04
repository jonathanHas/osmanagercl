<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SalesDailySummary extends Model
{
    protected $table = 'sales_daily_summary';

    protected $fillable = [
        'product_id', 'product_code', 'product_name', 'category_id',
        'sale_date', 'total_units', 'total_revenue', 'transaction_count', 'avg_price',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'total_units' => 'decimal:2',
        'total_revenue' => 'decimal:2',
        'avg_price' => 'decimal:2',
    ];

    // Scopes for common queries
    public function scopeForDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('sale_date', [$startDate, $endDate]);
    }

    public function scopeFruitVeg($query)
    {
        return $query->whereIn('category_id', ['SUB1', 'SUB2', 'SUB3']);
    }

    public function scopeForCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }
}
