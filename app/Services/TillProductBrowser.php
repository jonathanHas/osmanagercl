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
     * Search products by barcode or name.
     *
     * Default scope is ALL active products — invoicing sometimes needs items
     * that aren't on the till menu (back-stock, wholesale-only, etc.).
     * Set $tillOnly = true to restrict to till-visible items.
     *
     * Result order: exact code match → exact name → prefix matches → contains;
     * within those buckets, till-visible items are surfaced before non-till.
     * Each Product has a transient `is_till_visible` flag set on it.
     */
    public function searchTillVisible(string $term, int $limit = 50, bool $tillOnly = false): EloquentCollection
    {
        $term = trim($term);
        if ($term === '') {
            return new EloquentCollection;
        }

        $visibleIds = ProductsCat::pluck('PRODUCT')->all();
        $like = '%'.$term.'%';
        $prefix = $term.'%';

        // SQLite doesn't support boolean expressions in ORDER BY directly,
        // so we hand-roll a CASE that prioritises till-visible items.
        $tillCase = empty($visibleIds)
            ? 'NULL'
            : 'CASE WHEN PRODUCTS.ID IN ('.implode(',', array_map(fn ($id) => "'".addslashes($id)."'", $visibleIds)).') THEN 0 ELSE 1 END';

        $query = Product::with('tax')
            ->active()
            ->where(function ($q) use ($like) {
                $q->where('NAME', 'like', $like)
                    ->orWhere('CODE', 'like', $like)
                    ->orWhere('REFERENCE', 'like', $like);
            });

        if ($tillOnly) {
            if (empty($visibleIds)) {
                return new EloquentCollection;
            }
            $query->whereIn('ID', $visibleIds);
        }

        $products = $query
            ->orderByRaw('CASE WHEN CODE = ? THEN 0 WHEN NAME = ? THEN 1 WHEN CODE LIKE ? THEN 2 WHEN NAME LIKE ? THEN 3 ELSE 4 END', [$term, $term, $prefix, $prefix])
            ->orderByRaw($tillCase)
            ->orderBy('NAME')
            ->limit($limit)
            ->get();

        // Stamp till-visibility on each result for the UI badge.
        $visibleSet = array_flip($visibleIds);
        $products->each(function ($p) use ($visibleSet) {
            $p->is_till_visible = isset($visibleSet[$p->ID]);
        });

        return $products;
    }
}
