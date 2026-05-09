<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductsCat;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Browses POS products filtered to those visible on the till (i.e. members of PRODUCTS_CAT).
 *
 * Independent of TillVisibilityService::CATEGORY_MAPPINGS — this service treats the till
 * menu generically: any POS category that contains at least one till-visible product is
 * browsable, mirroring what the till actually displays.
 */
class TillProductBrowser
{
    /**
     * Categories that contain at least one till-visible product.
     */
    public function categories(): EloquentCollection
    {
        $categoryIds = DB::connection('pos')
            ->table('PRODUCTS_CAT')
            ->join('PRODUCTS', 'PRODUCTS_CAT.PRODUCT', '=', 'PRODUCTS.ID')
            ->distinct()
            ->pluck('PRODUCTS.CATEGORY')
            ->filter()
            ->all();

        if (empty($categoryIds)) {
            return new EloquentCollection;
        }

        return Category::whereIn('ID', $categoryIds)
            ->orderBy('NAME')
            ->get();
    }

    /**
     * Till-visible products within a single POS category.
     */
    public function productsInCategory(string $categoryId): EloquentCollection
    {
        $visibleIds = ProductsCat::pluck('PRODUCT')->all();

        if (empty($visibleIds)) {
            return new EloquentCollection;
        }

        return Product::with('tax')
            ->whereIn('ID', $visibleIds)
            ->where('CATEGORY', $categoryId)
            ->orderBy('NAME')
            ->get();
    }

    /**
     * Search till-visible products by barcode or name. Order: exact code match first,
     * then exact name, then prefix matches, then contains.
     */
    public function searchTillVisible(string $term, int $limit = 50): EloquentCollection
    {
        $term = trim($term);
        if ($term === '') {
            return new EloquentCollection;
        }

        $visibleIds = ProductsCat::pluck('PRODUCT')->all();
        if (empty($visibleIds)) {
            return new EloquentCollection;
        }

        $like = '%'.$term.'%';
        $prefix = $term.'%';

        return Product::with('tax')
            ->whereIn('ID', $visibleIds)
            ->where(function ($q) use ($like) {
                $q->where('NAME', 'like', $like)
                    ->orWhere('CODE', 'like', $like)
                    ->orWhere('REFERENCE', 'like', $like);
            })
            ->orderByRaw('CASE WHEN CODE = ? THEN 0 WHEN NAME = ? THEN 1 WHEN CODE LIKE ? THEN 2 WHEN NAME LIKE ? THEN 3 ELSE 4 END', [$term, $term, $prefix, $prefix])
            ->orderBy('NAME')
            ->limit($limit)
            ->get();
    }
}
