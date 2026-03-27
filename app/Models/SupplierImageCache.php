<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierImageCache extends Model
{
    protected $table = 'supplier_image_cache';

    protected $fillable = [
        'supplier_code',
        'supplier_id',
        'image_url',
        'not_found',
    ];

    protected $casts = [
        'not_found' => 'boolean',
    ];
}
