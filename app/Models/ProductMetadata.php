<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMetadata extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'product_code',
        'created_by',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created this product.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the associated product from the POS database.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'ID');
    }

    /**
     * Get product metadata by product ID.
     */
    public static function findByProductId(string $productId): ?ProductMetadata
    {
        return static::where('product_id', $productId)->first();
    }

    /**
     * Get product metadata by product code.
     */
    public static function findByProductCode(string $productCode): ?ProductMetadata
    {
        return static::where('product_code', $productCode)->first();
    }

    /**
     * Create metadata for a product.
     */
    public static function createForProduct(
        string $productId,
        string $productCode,
        ?int $createdBy = null,
        ?array $additionalMetadata = null
    ): ProductMetadata {
        return static::create([
            'product_id' => $productId,
            'product_code' => $productCode,
            'created_by' => $createdBy,
            'metadata' => $additionalMetadata,
        ]);
    }
}
