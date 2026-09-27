<?php

namespace App\Services\Shop;

use App\Models\Product;
use App\Services\ProductSearch\ProductSearchService;
use App\Services\ProductThumbnailService;

/**
 * Picture URLs for Shop screens.
 *
 * The rules are the product search's — till photo first, supplier CDN second,
 * nothing otherwise — with one substitution: a till photo resolves to the Shop's
 * own thumbnail route rather than `products.image`, so a row loads a few kilobytes
 * instead of the stored blob.
 *
 * This is the only place that knows the Shop photo route exists.
 */
class ProductImageUrls
{
    public function __construct(
        private ProductSearchService $productSearch,
        private ProductThumbnailService $thumbnails,
    ) {}

    /**
     * @param  array<int, string|null>  $codes
     * @return array<string, string|null> CODE => url or null
     */
    public function byCode(array $codes): array
    {
        $versions = $this->thumbnails->versions($codes);

        return $this->productSearch->imageUrlsByCode(
            $codes,
            fn (Product $product) => route('shop.product-photo', array_filter([
                'code' => $product->CODE,
                'v' => $versions[$product->CODE] ?? null,
            ]))
        );
    }
}
