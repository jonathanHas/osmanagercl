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

    /** Shortest token that is looked up against supplier codes. */
    private const SUPPLIER_CODE_MIN_LENGTH = 3;

    /** All-digit tokens at least this long are treated as code fragments, never words. */
    private const CODE_TOKEN_LENGTH = 4;

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

        $tokens = $this->tokenise($criteria->q);
        $correctedQuery = null;

        $paginator = $this->runQuery($criteria, $tokens);

        // Typo fallback: only when the exact tokenised search found nothing.
        if ($paginator->total() === 0 && $tokens !== []) {
            $corrected = array_map(fn (string $t) => $this->vocabulary->correct($t) ?? $t, $tokens);

            if ($corrected !== $tokens) {
                $paginator = $this->runQuery($criteria, $corrected);
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
     * A run of 4+ digits is a code fragment, not a word: only 80 of 10,649
     * product names contain a 4-digit run, so such a token skips NAME and
     * matches CODE, REFERENCE and supplier codes only. Three-digit tokens
     * ("100", "500") are usually sizes and still match names.
     */
    private function isCodeToken(string $token): bool
    {
        return ctype_digit($token) && strlen($token) >= self::CODE_TOKEN_LENGTH;
    }

    /**
     * Any all-digit token of 3+ chars is ranked by "code ends with these
     * digits" (tier 1), whether or not it also matches names.
     */
    private function isDigitToken(string $token): bool
    {
        return ctype_digit($token) && strlen($token) >= self::SUPPLIER_CODE_MIN_LENGTH;
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
    private function runQuery(ProductSearchCriteria $criteria, array $tokens): LengthAwarePaginator
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

        if ($tokens !== []) {
            // One supplier-code lookup per token, shared by matching and ranking.
            $links = [];
            foreach ($tokens as $token) {
                $links[$token] = $this->supplierCodeMatches($token);
                $this->applyTokenMatch($query, $token, $links[$token]['contains']);
            }
            $this->applyRanking($query, implode(' ', $tokens), $tokens, $links);
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

    /**
     * @param  string[]  $linked  barcodes whose supplier code contains the token
     */
    private function applyTokenMatch(Builder $query, string $token, array $linked): void
    {
        $pattern = '%'.$this->likeValue($token).'%';
        $isCode = $this->isCodeToken($token);

        $query->where(function (Builder $q) use ($pattern, $linked, $isCode) {
            // A digit run of 4+ is a code fragment: matching it against NAME
            // would drag in every "1000iu"-style product name.
            if (! $isCode) {
                $q->whereRaw($this->like('PRODUCTS.NAME'), [$pattern]);
            }

            $q->{$isCode ? 'whereRaw' : 'orWhereRaw'}($this->like('PRODUCTS.CODE'), [$pattern])
                ->orWhereRaw($this->like('PRODUCTS.REFERENCE'), [$pattern]);

            if ($linked !== []) {
                $q->orWhereIn('PRODUCTS.CODE', $linked);
            }
        });
    }

    /**
     * Barcodes whose supplier code contains the token, and the subset whose
     * supplier code *ends* with it (tier 1). Both come from one query.
     *
     * Two-phase by design: `supplier_link.Barcode` has a different collation
     * from `PRODUCTS.CODE`, so it is plucked and used with `whereIn` rather
     * than joined. Returns empty lists for short tokens and for tokens so
     * generic that they hit the cap (the worst real token, "100", matches 128).
     *
     * @return array{contains: string[], suffix: string[]}
     */
    private function supplierCodeMatches(string $token): array
    {
        $empty = ['contains' => [], 'suffix' => []];

        if (strlen($token) < self::SUPPLIER_CODE_MIN_LENGTH) {
            return $empty;
        }

        $rows = DB::connection('pos')->table('supplier_link')
            ->select('Barcode', 'SupplierCode')
            ->whereRaw($this->like('SupplierCode'), ['%'.$this->likeValue($token).'%'])
            ->limit(self::SUPPLIER_CODE_LIMIT)
            ->get();

        if ($rows->count() >= self::SUPPLIER_CODE_LIMIT) {
            return $empty;
        }

        return [
            'contains' => $rows->pluck('Barcode')->all(),
            'suffix' => $rows->filter(fn ($row) => str_ends_with((string) $row->SupplierCode, $token))
                ->pluck('Barcode')
                ->all(),
        ];
    }

    /**
     * Rank tiers: 0 exact code/reference, 1 code or supplier code ends with a
     * 4+ digit token, 2 phrase prefix of NAME, 3 phrase anywhere in NAME,
     * 4 every token at a word start, 5 code or supplier code ends with a
     * 3-digit token, 6 everything else. Selected as `match_rank` and used as
     * the primary sort.
     *
     * The suffix rule is split in two on purpose. A 4+ digit run is only ever
     * a barcode fragment, so it belongs above the name tiers. A 3-digit run is
     * usually a size ("100", "250", "500") that plenty of 13-digit barcodes
     * also happen to end with, so those coincidences sit *below* the name
     * matches — and when nothing is named after the digits (e.g. "341") the
     * name tiers are empty and the suffix matches surface anyway.
     *
     * Bindings are appended in the same order as the SQL fragments.
     *
     * @param  string[]  $tokens
     * @param  array<string, array{contains: string[], suffix: string[]}>  $links
     */
    private function applyRanking(Builder $query, string $phrase, array $tokens, array $links): void
    {
        $sql = '(CASE WHEN PRODUCTS.CODE = ? OR PRODUCTS.REFERENCE = ? THEN 0';
        $bindings = [$phrase, $phrase];

        // Staff type the last digits of a barcode: a code (or supplier code)
        // ending in those digits beats the many that merely contain them.
        $codeSuffixes = [];
        $sizeSuffixes = [];
        foreach ($tokens as $token) {
            if (! $this->isDigitToken($token)) {
                continue;
            }

            $group = $this->suffixGroup($token, $links[$token]['suffix'] ?? []);

            if ($this->isCodeToken($token)) {
                $codeSuffixes[] = $group;
            } else {
                $sizeSuffixes[] = $group;
            }
        }

        if ($codeSuffixes !== []) {
            $sql .= ' WHEN '.implode(' OR ', array_column($codeSuffixes, 0)).' THEN 1';
            $bindings = array_merge($bindings, ...array_column($codeSuffixes, 1));
        }

        $sql .= ' WHEN '.$this->like('PRODUCTS.NAME').' THEN 2';
        $bindings[] = $this->likeValue($phrase).'%';

        $sql .= ' WHEN '.$this->like('PRODUCTS.NAME').' THEN 3';
        $bindings[] = '%'.$this->likeValue($phrase).'%';

        $wordStarts = [];
        foreach ($tokens as $token) {
            $wordStarts[] = '('.$this->like('PRODUCTS.NAME').' OR '.$this->like('PRODUCTS.NAME').')';
            $bindings[] = $this->likeValue($token).'%';
            $bindings[] = '% '.$this->likeValue($token).'%';
        }

        $sql .= ' WHEN '.implode(' AND ', $wordStarts).' THEN 4';

        if ($sizeSuffixes !== []) {
            $sql .= ' WHEN '.implode(' OR ', array_column($sizeSuffixes, 0)).' THEN 5';
            $bindings = array_merge($bindings, ...array_column($sizeSuffixes, 1));
        }

        $sql .= ' ELSE 6 END)';

        $query->selectRaw($sql.' as match_rank', $bindings)
            ->orderBy('match_rank')
            ->orderBy('PRODUCTS.NAME');
    }

    /**
     * "Code, reference or supplier code ends with this token", as a SQL
     * fragment and its bindings.
     *
     * @param  string[]  $linked  barcodes whose supplier code ends with the token
     * @return array{0: string, 1: array<int, string>}
     */
    private function suffixGroup(string $token, array $linked): array
    {
        $pattern = '%'.$this->likeValue($token);
        $sql = $this->like('PRODUCTS.CODE').' OR '.$this->like('PRODUCTS.REFERENCE');
        $bindings = [$pattern, $pattern];

        if ($linked !== []) {
            $sql .= ' OR PRODUCTS.CODE IN ('.implode(', ', array_fill(0, count($linked), '?')).')';
            $bindings = array_merge($bindings, $linked);
        }

        return ['('.$sql.')', $bindings];
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
