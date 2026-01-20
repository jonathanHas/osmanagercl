<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenProduct extends Model
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
        'notes',
    ];

    /**
     * Get the POS product associated with this kitchen product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'ID');
    }

    /**
     * Check if a product is marked as a kitchen product.
     */
    public static function isKitchenProduct(string $productId): bool
    {
        return self::where('product_id', $productId)->exists();
    }

    /**
     * Get all kitchen product IDs.
     */
    public static function getKitchenProductIds(): array
    {
        return self::pluck('product_id')->toArray();
    }
}
