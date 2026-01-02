<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockValuationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'snapshot_id',
        'category_record_id',
        'product_id',
        'product_code',
        'product_name',
        'unit_cost',
        'stock_units',
        'line_value',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:4',
        'stock_units' => 'decimal:2',
        'line_value' => 'decimal:2',
    ];

    /**
     * Get the snapshot for this item.
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(StockValuationSnapshot::class, 'snapshot_id');
    }

    /**
     * Get the category record for this item.
     */
    public function categoryRecord(): BelongsTo
    {
        return $this->belongsTo(StockValuationCategory::class, 'category_record_id');
    }
}
