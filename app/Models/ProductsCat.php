<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductsCat extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'pos';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'PRODUCTS_CAT';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'PRODUCT';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'PRODUCT',
        'CATORDER',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // CATORDER left as nullable for future manual ordering
    ];

    /**
     * Get the product associated with this till visibility entry.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'PRODUCT', 'ID');
    }

    /**
     * Scope to order by product name alphabetically.
     * This replaces CATORDER-based sorting to rely on alphabetical order.
     */
    public function scopeOrdered($query)
    {
        return $query->join('PRODUCTS', 'PRODUCTS_CAT.PRODUCT', '=', 'PRODUCTS.ID')
            ->orderBy('PRODUCTS.NAME')
            ->select('PRODUCTS_CAT.*');
    }

    /**
     * Check if a product is visible on till.
     */
    public static function isProductVisible(string $productId): bool
    {
        return self::where('PRODUCT', $productId)->exists();
    }

    /**
     * Add a product to till visibility.
     * CATORDER is left as NULL to rely on alphabetical sorting.
     */
    public static function addProduct(string $productId, ?int $order = null): self
    {
        return self::create([
            'PRODUCT' => $productId,
            'CATORDER' => $order, // Only set if explicitly provided, otherwise NULL
        ]);
    }

    /**
     * Remove a product from till visibility.
     */
    public static function removeProduct(string $productId): bool
    {
        return self::where('PRODUCT', $productId)->delete() > 0;
    }

    /**
     * Toggle product visibility on till.
     */
    public static function toggleProduct(string $productId): bool
    {
        if (self::isProductVisible($productId)) {
            self::removeProduct($productId);

            return false;
        } else {
            self::addProduct($productId);

            return true;
        }
    }

    /**
     * Get visible products for specific categories.
     */
    public static function getVisibleProductsForCategories(array $categoryIds)
    {
        return self::whereHas('product', function ($query) use ($categoryIds) {
            $query->whereIn('CATEGORY', $categoryIds);
        })->ordered()->with('product');
    }

    /**
     * Bulk update visibility for multiple products.
     * CATORDER is left as NULL to rely on alphabetical sorting.
     */
    public static function bulkUpdateVisibility(array $productIds, bool $visible): void
    {
        if ($visible) {
            // Add products that aren't already visible
            $existingProducts = self::whereIn('PRODUCT', $productIds)->pluck('PRODUCT')->toArray();
            $productsToAdd = array_diff($productIds, $existingProducts);

            if (! empty($productsToAdd)) {
                $data = [];

                foreach ($productsToAdd as $productId) {
                    $data[] = [
                        'PRODUCT' => $productId,
                        'CATORDER' => null, // Leave as NULL for alphabetical sorting
                    ];
                }

                self::insert($data);
            }
        } else {
            // Remove products
            self::whereIn('PRODUCT', $productIds)->delete();
        }
    }

    /**
     * Reorder products by updating CATORDER.
     * This method is preserved for future manual ordering functionality.
     * Pass NULL as order value to reset to alphabetical sorting.
     */
    public static function reorderProducts(array $productIdToOrder): void
    {
        foreach ($productIdToOrder as $productId => $order) {
            // Allow NULL values to reset to alphabetical sorting
            self::where('PRODUCT', $productId)->update(['CATORDER' => $order]);
        }
    }
}
