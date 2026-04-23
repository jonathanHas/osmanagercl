<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamisProductLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'dynamis_code',
        'dynamis_description',
        'pos_product_id',
        'pos_product_code',
        'matched_by',
        'confidence',
        'ai_model',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'confidence' => 'float',
        'verified_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'pos_product_id', 'ID');
    }
}
