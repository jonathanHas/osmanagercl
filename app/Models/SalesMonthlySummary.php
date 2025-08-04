<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesMonthlySummary extends Model
{
    protected $table = 'sales_monthly_summary';

    protected $fillable = [
        'product_id', 'product_code', 'product_name', 'category_id',
        'year', 'month', 'total_units', 'total_revenue', 'transaction_count',
        'avg_price', 'days_with_sales',
    ];

    protected $casts = [
        'total_units' => 'decimal:2',
        'total_revenue' => 'decimal:2',
        'avg_price' => 'decimal:2',
    ];

    // Scopes for common queries
    public function scopeForYearMonth($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    public function scopeFruitVeg($query)
    {
        return $query->whereIn('category_id', ['SUB1', 'SUB2', 'SUB3']);
    }
}
