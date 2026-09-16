<?php

namespace App\Services\ProductSearch;

use App\Models\Product;
use App\Models\Stocking;
use App\Models\SupplierImageCache;
use App\Services\SupplierService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * The one product search behind `/api/products/search` and `<x-product-search>`.
 *
 * Performance rules (measured on the live POS DB, see docs/development/known-issues.md):
 *  - `PRODUCTS.CODE` and `stocking.Barcode` / `supplier_link.Barcode` have
 *    different collations, so a correlated `WHERE EXISTS` between them cannot
 *    use an index (20 s). The stocked filter is a plain `JOIN stocking`
 *    (~40 ms); supplier filtering and supplier-code matching pre-pluck barcodes
 *    and use `WHERE IN`.
 *  - `supplier_link` is never joined into the search query. Suppliers, stock,
 *    tax and category are eager-loaded on the page of results only.
 *  - Never `SELECT PRODUCTS.*` — `IMAGE` is a mediumblob. `has_image` is a
 *    CASE expression.
 */
class ProductSearchService
{
    /** LIKE escape character; portable across MySQL and SQLite (backslash is not). */
    private const LIKE_ESCAPE = '!';

    private const MAX_TOKENS = 6;

    /** Skip the supplier-code OR clause when a token matches this many links (too generic). */
    private const SUPPLIER_CODE_LIMIT = 500;

    private const SELECT_COLUMNS = [
        'PRODUCTS.ID', 'PRODUCTS.CODE', 'PRODUCTS.REFERENCE', 'PRODUCTS.NAME', 'PRODUCTS.DISPLAY',
        'PRODUCTS.CATEGORY', 'PRODUCTS.TAXCAT', 'PRODUCTS.PRICESELL', 'PRODUCTS.PRICEBUY', 'PRODUCTS.ISSERVICE',
    ];

    public function __construct(
        private SupplierService $supplierService,
        private ProductSearchVocabulary $vocabulary,
    ) {}

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function search(ProductSearchCriteria $criteria): array
    {
        $started = microtime(true);

        $barcode = $this->asBarcode($criteria->q);
        $tokens = $barcode === null ? $this->tokenise($criteria->q) : [];
        $correctedQuery = null;

        $paginator = $this->runQuery($criteria, $tokens, $barcode);

        // Typo fallback: only when the exact tokenised search found nothing.
        if ($paginator->total() === 0 && $tokens !== []) {
            $corrected = array_map(fn (string $t) => $this->vocabulary->correct($t) ?? $t, $tokens);

            if ($corrected !== $tokens) {
                $paginator = $this->runQuery($criteria, $corrected, null);
                $correctedQuery = implode(' ', $corrected);
            }
        }

        $data = $this->hydrate($paginator->items(), $criteria);

        return [
            'data' => $data,
            'meta' => [
                'query' => $criteria->q,
                'corrected_query' => $correctedQuery,
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'stocked' => $criteria->stocked,
                'took_ms' => round((microtime(true) - $started) * 1000, 1),
            ],
        ];
    }

    /**
     * Whole query is a barcode when it is all digits and at least 6 long.
     */
    private function asBarcode(string $q): ?string
    {
        return strlen($q) >= 6 && ctype_digit($q) ? $q : null;
    }

    /**
     * @return string[]
     */
    private function tokenise(string $q): array
    {
        $q = strtolower(trim(preg_replace('/\s+/', ' ', $q)));

        if ($q === '') {
            return [];
        }

        $tokens = [];
        foreach (explode(' ', $q) as $token) {
            $token = preg_replace('/[^a-z0-9%.\-]/', '', $token);
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return array_slice(array_values(array_unique($tokens)), 0, self::MAX_TOKENS);
    }

    private function likeValue(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE.self::LIKE_ESCAPE, self::LIKE_ESCAPE.'%', self::LIKE_ESCAPE.'_'],
            $value
        );
    }

    private function like(string $column): string
    {
        return "{$column} LIKE ? ESCAPE '".self::LIKE_ESCAPE."'";
    }

    /**
     * @param  string[]  $tokens
     */
    private function runQuery(ProductSearchCriteria $criteria, array $tokens, ?string $barcode): LengthAwarePaginator
    {
        $query = Product::query()
            ->select(self::SELECT_COLUMNS)
            ->addSelect(DB::raw('(CASE WHEN PRODUCTS.IMAGE IS NOT NULL AND LENGTH(PRODUCTS.IMAGE) > 0 THEN 1 ELSE 0 END) as has_image'));

        if ($criteria->stocked) {
            // Plain JOIN: the only stocked filter that can use an index (see class docblock).
            $query->join('stocking', 'stocking.Barcode', '=', 'PRODUCTS.CODE');
        }

        if ($criteria->categoryId !== null && $criteria->categoryId !== '') {
            $query->where('PRODUCTS.CATEGORY', $criteria->categoryId);
        }

        if ($criteria->supplierId !== null && $criteria->supplierId !== '') {
            $barcodes = DB::connection('pos')->table('supplier_link')
                ->where('SupplierID', $criteria->supplierId)
                ->pluck('Barcode')
                ->all();
            $query->whereIn('PRODUCTS.CODE', $barcodes ?: ['']);
        }

        if ($criteria->excludeIds !== []) {
            $query->whereNotIn('PRODUCTS.ID', $criteria->excludeIds);
        }

        if ($barcode !== null) {
            $this->applyBarcodeMatch($query, $barcode);
            $this->applyRanking($query, $barcode, [$barcode]);
        } elseif ($tokens !== []) {
            foreach ($tokens as $token) {
                $this->applyTokenMatch($query, $token);
            }
            $this->applyRanking($query, implode(' ', $tokens), $tokens);
        } else {
            $query->orderBy('PRODUCTS.NAME');
        }

        // Page first, count second: when the first page comes back short the
        // total is already known, which saves a ~30 ms COUNT on most searches
        // (and on the empty first pass of the typo fallback).
        $rows = (clone $query)->forPage($criteria->page, $criteria->perPage)->get();

        $total = ($criteria->page === 1 && $rows->count() < $criteria->perPage)
            ? $rows->count()
            : $query->toBase()->getCountForPagination();

        $rows->load(['tax', 'category', 'stockCurrent', 'supplierLink.supplier']);

        return new LengthAwarePaginator($rows, $total, $criteria->perPage, $criteria->page);
    }

    private function applyBarcodeMatch(Builder $query, string $barcode): void
    {
        $linked = DB::connection('pos')->table('supplier_link')
            ->where('SupplierCode', $barcode)
            ->limit(self::SUPPLIER_CODE_LIMIT)
            ->pluck('Barcode')
            ->all();

        $query->where(function (Builder $q) use ($barcode, $linked) {
            $q->where('PRODUCTS.CODE', $barcode)
                ->orWhere('PRODUCTS.REFERENCE', $barcode)
                ->orWhereRaw($this->like('PRODUCTS.CODE'), [$this->likeValue($barcode).'%']);

            if ($linked !== []) {
                $q->orWhereIn('PRODUCTS.CODE', $linked);
            }
        });
    }

    private function applyTokenMatch(Builder $query, string $token): void
    {
        $pattern = '%'.$this->likeValue($token).'%';
        $linked = [];

        if (strlen($token) >= 3) {
            $linked = DB::connection('pos')->table('supplier_link')
                ->whereRaw($this->like('SupplierCode'), [$pattern])
                ->limit(self::SUPPLIER_CODE_LIMIT)
                ->pluck('Barcode')
                ->all();

            if (count($linked) >= self::SUPPLIER_CODE_LIMIT) {
                $linked = [];
            }
        }

        $query->where(function (Builder $q) use ($pattern, $linked) {
            $q->whereRaw($this->like('PRODUCTS.NAME'), [$pattern])
                ->orWhereRaw($this->like('PRODUCTS.CODE'), [$pattern])
                ->orWhereRaw($this->like('PRODUCTS.REFERENCE'), [$pattern]);

            if ($linked !== []) {
                $q->orWhereIn('PRODUCTS.CODE', $linked);
            }
        });
    }

    /**
     * Rank tiers: 0 exact code/reference, 1 phrase prefix of NAME, 2 phrase
     * anywhere in NAME, 3 every token at a word start, 4 everything else.
     * Selected as `match_rank` and used as the primary sort.
     *
     * @param  string[]  $tokens
     */
    private function applyRanking(Builder $query, string $phrase, array $tokens): void
    {
        $bindings = [$phrase, $phrase, $this->likeValue($phrase).'%', '%'.$this->likeValue($phrase).'%'];

        $wordStarts = [];
        foreach ($tokens as $token) {
            $wordStarts[] = '('.$this->like('PRODUCTS.NAME').' OR '.$this->like('PRODUCTS.NAME').')';
            $bindings[] = $this->likeValue($token).'%';
            $bindings[] = '% '.$this->likeValue($token).'%';
        }

        $sql = '(CASE'
            .' WHEN PRODUCTS.CODE = ? OR PRODUCTS.REFERENCE = ? THEN 0'
            .' WHEN '.$this->like('PRODUCTS.NAME').' THEN 1'
            .' WHEN '.$this->like('PRODUCTS.NAME').' THEN 2'
            .' WHEN '.implode(' AND ', $wordStarts).' THEN 3'
            .' ELSE 4 END)';

        $query->selectRaw($sql.' as match_rank', $bindings)
            ->orderBy('match_rank')
            ->orderBy('PRODUCTS.NAME');
    }

    /**
     * Turn the page of models into the canonical JSON items. All lookups are
     * batched per page; nothing here runs a query per row.
     *
     * @param  Product[]  $products
     * @return array<int, array<string, mixed>>
     */
    private function hydrate(array $products, ProductSearchCriteria $criteria): array
    {
        if ($products === []) {
            return [];
        }

        $codes = array_values(array_unique(array_filter(array_map(fn (Product $p) => $p->CODE, $products))));

        $stockedCodes = $criteria->stocked
            ? array_fill_keys($codes, true)
            : array_fill_keys(Stocking::query()->whereIn('Barcode', $codes)->pluck('Barcode')->all(), true);

        $imageCache = $this->loadSupplierImageCache($products);

        $items = [];
        foreach ($products as $product) {
            $link = $product->supplierLink;
            $supplier = $link?->supplier;
            if ($supplier) {
                // getSupplierWebsiteLink() reads $product->supplier; avoid a lazy hasOneThrough query.
                $product->setRelation('supplier', $supplier);
            }

            $items[] = [
                'id' => $product->ID,
                'code' => $product->CODE,
                'reference' => $product->REFERENCE,
                'name' => $product->NAME,
                'display' => $product->DISPLAY,
                'category_id' => $product->CATEGORY,
                'category_name' => $product->category?->NAME,
                'price_sell' => (float) $product->PRICESELL,
                'price_with_vat' => round($product->getGrossPrice(), 2),
                'vat_rate' => $product->getVatRate(),
                'vat_label' => $product->formatted_vat_rate,
                'vat_badge_class' => $product->tax_category_badge_class,
                'stock_units' => $product->stockCurrent ? (float) $product->stockCurrent->UNITS : 0.0,
                'stock_location' => $this->stockLocation($product),
                'has_stock_record' => $product->stockCurrent !== null,
                'is_stocked' => isset($stockedCodes[$product->CODE]),
                'is_service' => (bool) $product->ISSERVICE,
                'has_image' => (bool) $product->has_image,
                'image_url' => $this->imageUrl($product, $imageCache),
                'supplier' => $supplier ? [
                    'id' => (string) $supplier->SupplierID,
                    'name' => $supplier->Supplier,
                    'code' => $link->SupplierCode,
                    'website_url' => $this->supplierService->getSupplierWebsiteLink($product),
                ] : null,
                'edit_url' => route('products.edit', $product->ID, false),
                'match_rank' => isset($product->match_rank) ? (int) $product->match_rank : null,
            ];
        }

        return $items;
    }

    private function stockLocation(Product $product): ?string
    {
        $location = $product->stockCurrent?->LOCATION;

        return $location !== null && $location !== '' && $location !== '0' ? $location : null;
    }

    /**
     * One query for every supplier-code-keyed image (Independent) on the page,
     * keyed "supplierId|supplierCode" => SupplierImageCache.
     *
     * @param  Product[]  $products
     * @return array<string, SupplierImageCache>
     */
    private function loadSupplierImageCache(array $products): array
    {
        $wanted = [];
        foreach ($products as $product) {
            $link = $product->supplierLink;
            $supplierId = $this->externalSupplierId($product);
            if ($supplierId !== null && $link?->SupplierCode && $this->supplierService->usesSupplierCodeImages($supplierId)) {
                $wanted[$supplierId][] = $link->SupplierCode;
            }
        }

        $cache = [];
        foreach ($wanted as $supplierId => $codes) {
            SupplierImageCache::query()
                ->where('supplier_id', $supplierId)
                ->whereIn('supplier_code', array_unique($codes))
                ->get()
                ->each(function (SupplierImageCache $row) use (&$cache) {
                    $cache[$row->supplier_id.'|'.$row->supplier_code] = $row;
                });
        }

        return $cache;
    }

    /**
     * POS blob first, supplier CDN second, else null.
     *
     * @param  array<string, SupplierImageCache>  $imageCache
     */
    private function imageUrl(Product $product, array $imageCache): ?string
    {
        if ($product->has_image) {
            return route('products.image', $product->ID);
        }

        $supplierId = $this->externalSupplierId($product);
        if ($supplierId === null) {
            return null;
        }

        if (! $this->supplierService->usesSupplierCodeImages($supplierId)) {
            return $this->supplierService->getExternalImageUrlByBarcode($supplierId, $product->CODE);
        }

        $supplierCode = $product->supplierLink?->SupplierCode;
        if (! $supplierCode) {
            return null;
        }

        $cached = $imageCache[$supplierId.'|'.$supplierCode] ?? null;
        if ($cached) {
            return $cached->not_found ? null : $cached->image_url;
        }

        // No cache row: template URL, the browser's onerror hides a miss (matches other pages).
        $template = $this->imageTemplate($supplierId);
        if (! $template) {
            return null;
        }

        return str_replace('{SUPPLIER_CODE}', preg_replace('/[^a-zA-Z0-9_-]/', '', $supplierCode), $template);
    }

    /**
     * Supplier ID of the product's link when that supplier has an external image integration.
     */
    private function externalSupplierId(Product $product): ?int
    {
        $supplierId = $product->supplierLink?->SupplierID;

        return $supplierId !== null && $this->supplierService->hasExternalIntegration($supplierId)
            ? (int) $supplierId
            : null;
    }

    private function imageTemplate(int $supplierId): ?string
    {
        foreach (config('suppliers.external_links', []) as $settings) {
            if (in_array($supplierId, $settings['supplier_ids'] ?? [], true)) {
                return ($settings['enabled'] ?? false) ? ($settings['image_url'] ?? null) : null;
            }
        }

        return null;
    }
}
