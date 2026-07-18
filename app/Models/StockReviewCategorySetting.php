<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReviewCategorySetting extends Model
{
    protected $fillable = [
        'category_id',
        'excluded',
    ];

    protected $casts = [
        'excluded' => 'boolean',
    ];
}
