<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTranslation extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'product_id',
        'product_code',
        'label_data',
        'label_size',
        'font_scale',
        'original_photos',
        'zpl_content',
        'auto_print',
        'created_by',
    ];

    protected $casts = [
        'label_data' => 'json',
        'original_photos' => 'json',
        'font_scale' => 'float',
        'auto_print' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_code', 'CODE');
    }

    /**
     * Get the latest translation for a product by barcode.
     */
    public static function latestForProduct(string $productCode): ?self
    {
        return static::where('product_code', $productCode)
            ->latest()
            ->first();
    }

    /**
     * Find all translations for a product code.
     */
    public static function findByProductCode(string $productCode)
    {
        return static::where('product_code', $productCode)
            ->latest()
            ->get();
    }
}
