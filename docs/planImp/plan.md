# Reusable product search bar (`x-product-search`)

Status: ACCEPTED
Revision: 2
Planner: Fable 5.1
Date: 2026-09-16

## Goal

One Blade component, `<x-product-search>`, backed by one JSON endpoint, that every
page can drop in when it needs to find a product. It must be fast (sub-100 ms
server time on the live POS DB), show product thumbnails, match words in any
order ("chocolatemakers fruit" finds "Chocolatemakers forest fruit milk chocolate
100 gram"), tolerate typos ("chocolatmakers"), default to stocked products with
suppliers shown, and work in two modes: a picker dropdown (select one product,
page reacts) and a full results list (what `/products` needs). Prove it on a new
test page, then switch `/products` over to it.

Decisions already taken with the user (do not reopen):
- Both modes in one component.
- Word-based matching plus typo tolerance.
- Stocked products by default with a single "Include unstocked" toggle. Suppliers
  always shown. "Active only" and "In stock only" checkboxes are removed; stock
  quantity is shown on each row instead.
- Test page first, then migrate `/products` in this task. Other pages migrate in
  later tasks.

## Context

### What exists today (all verified against the code and live DBs)

- `/products` is a full-page GET form: `resources/views/products/index.blade.php`
  lines 75–158 (`x-filter-form`), `ProductController@index` lines 123–180,
  `ProductRepository::searchProducts()` lines 108–174, and the matcher
  `Product::scopeSearch()` at `app/Models/Product.php:163–182` (four
  leading-wildcard LIKEs, single phrase, no ranking, no multi-word support).
- There is no JSON search endpoint for `/products`. Nine other pages have their
  own ad-hoc typeaheads with nine different JSON shapes (orders review, customer
  requests, customer invoices, kitchen ×3, KDS, fruit-veg ×3). None of the picker
  dropdowns show images. The best UI reference to copy is the picker in
  `resources/views/customer-requests/_form.blade.php` lines 85–115 and 188–250
  (debounce, keyboard ↑/↓/Enter/Esc, Enter commits top match for barcode
  scanners). `resources/views/fruit-veg/waste.blade.php:355` has the only
  stale-response guard; copy that idea.
- Images: the canonical resolver is "POS blob first, supplier CDN second", e.g.
  `app/Services/KitchenOrderService.php:468–473`. Blob route is
  `route('products.image', $id)` (`ProductController@image` line 435). Supplier
  CDN URLs come from `app/Services/SupplierService.php`:
  `getExternalImageUrlByBarcode()` (line 162, pure string work, no DB; Udea
  supplier IDs 5/44/85) and `getExternalImageUrlBySupplierCode()` (line 205,
  Independent supplier ID 37, hits `supplier_image_cache`). `getExternalImageUrl(Product)`
  lazy-loads `supplier` and `supplierLink` per product, so it is an N+1 trap
  inside a results loop. `x-product-image` (`resources/views/components/product-image.blade.php`)
  takes a Product model, so it cannot be used inside an Alpine `x-for`; the JSON
  must carry a ready `image_url` and the component renders `<img>` with the same
  `onerror` fallback icon.
- `has_image` must be a CASE expression, never the blob:
  `(CASE WHEN IMAGE IS NOT NULL AND LENGTH(IMAGE) > 0 THEN 1 ELSE 0 END)`
  (`ProductRepository.php:131`). Select explicit columns; `PRODUCTS.*` drags the
  `IMAGE` mediumblob across the wire. `Product::$hidden` already excludes `IMAGE`
  from JSON.
- Data facts (live POS, MySQL 5.7.33, DB `unicenta2016`): 10,649 products,
  5,264 rows in `stocking`, 10,276 in `supplier_link`, 714 products with a blob
  image, average name length 33 chars. Table names are case-sensitive
  (`lower_case_table_names=0`): `PRODUCTS`, `STOCKCURRENT`, `stocking`,
  `supplier_link`, `suppliers`, `CATEGORIES`.
- Laravel DB is MariaDB 10.11 (`osmanager`). `CACHE_STORE=database`.

### The performance trap (measured, must be respected)

`PRODUCTS.CODE` is `utf8_general_ci` but `stocking.Barcode` and
`supplier_link.Barcode` are `latin1_swedish_ci`. Any correlated comparison
between them cannot use an index. Measured on the live POS DB:

| Stocked-filter strategy | Time |
|---|---|
| `JOIN stocking ON stocking.Barcode = PRODUCTS.CODE` (repository line 149) | 30–40 ms |
| `whereIn('CODE', all 5,264 stocking barcodes)` | 60 ms |
| `Product::stocked()` scope, i.e. `WHERE EXISTS (...)` (`Product.php:267`) | **20,381 ms** |
| `LEFT JOIN supplier_link` inside the search query | Block Nested Loop over 10k rows, seconds cold |

Rules that follow: use the plain `JOIN stocking` for the stocked filter; never
`whereExists` against `stocking` or `supplier_link`; never join `supplier_link`
in the search query. Load supplier links, suppliers, stock and tax **after** the
page of results is known, with `whereIn` on the ≤50 result codes/IDs (the
"two-phase" pattern already used at `Product.php:167` and
`ProductRepository.php:192`). A ranked two-word stocked query with `LIMIT 20`
measured 40 ms; the matching `COUNT(*)` for pagination 28 ms.

### Typo tolerance design (measured)

The vocabulary of distinct words (≥3 chars, lowercased, split on non-alphanumerics)
across all product names is 6,212 words, 107 KB serialised. Building it is one
`pluck('NAME')` (30 ms). A pruned `levenshtein()` scan over it takes ~1 ms per
token ("chocolatmakers" → "chocolatemakers" d=1, "friut" → "fruit" d=2). So typo
tolerance is a "did you mean" rewrite: run the exact tokenised search; if it
returns nothing, correct each token against the cached vocabulary and rerun;
report the corrected query in the response so the UI can say "Showing results for
…". No new tables, no sync job, no POS schema change.

### Test environment

`phpunit.xml` runs both the default and `pos` connections as SQLite `:memory:`.
POS tables are hand-built per test; reuse the shape in
`tests/Concerns/CreatesKitchenOrderPosTables.php` (has `PRODUCTS` with
`binary('IMAGE')`, `supplier_link`, `suppliers`, `STOCKCURRENT`) and add
`stocking`, `CATEGORIES`, `TAXCATEGORIES`, `TAXES` as needed. Everything in the
query must therefore be SQLite-compatible: `LIKE`, `CASE`, `JOIN`, `IN`. No
`MATCH AGAINST`, no `SOUNDEX`, no `COLLATE`. There is no `ProductFactory`; seed
with `DB::connection('pos')->table(...)->insert()`.

`tests/Feature/ProductTest.php` seeds only `PRODUCTS` (lines 16–57) and asserts
`GET /products?search=Kitchen` shows "Kitchen Item" (line 125) and
`?active_only=1` (line 136). Step 9 changes these tests deliberately because the
request parameters change; that is a planned change, not a workaround.

## Constraints

- POS database is read-only for this feature: no indexes, no collation changes,
  no new tables on the `pos` connection.
- No new Composer or npm packages (no Scout, Meilisearch, Fuse.js).
- Always Eloquent models for reads (`Product`, `SupplierLink`, `Supplier`,
  `StockCurrent`, `Category`), with explicit `select()` lists. Raw
  `DB::connection('pos')->table('supplier_link')->pluck('Barcode')` is acceptable
  only for the barcode pre-pluck, matching `Product::scopeSearch`.
- Server time for a search request must stay under 100 ms warm on the live POS DB
  (the test page shows the timing; see Verification).
- Existing routes keep working: `products.index` (GET `/products`) still renders
  the products page; `products.image` unchanged. Old query parameters
  `search`, `stocked_only`, `supplier_id`, `category_id` on `/products` must
  still be accepted (map `search` → `q`), because the camera scanner and external
  links use `?search=`.
- The response JSON is the canonical shape for all future product pickers; use
  lowercase snake_case keys (see Step 3). Do not add per-page variations.
- Do not commit, push or deploy. Run `./vendor/bin/pint` on changed PHP files.
- Do not fix `Product::scopeStocked()` or any other page's search in this task
  (see Out of scope); just document the trap.

## Out of scope

- Migrating any page other than `/products` (orders review, customer requests,
  customer invoices, kitchen, KDS, fruit-veg). Follow-up tasks.
- A Laravel-side mirror/index of `PRODUCTS`, FULLTEXT indexes, n-gram search.
- Editing `Product::scopeSearch()` / `scopeStocked()` or `ProductRepository::searchProducts()`;
  other callers still depend on them.
- Changing the product statistics block, the sales chart modal, the stock
  update endpoint, or the product image upload flow.
- Resolving/scraping Independent image URLs that are not already in
  `supplier_image_cache` (never call `resolveAndCacheImageUrl()` in a search).
- Removing `x-filter-form` or `till-visibility-search` components.

## Steps

### 1. `ProductSearchVocabulary` (typo correction)
Files: `app/Services/ProductSearch/ProductSearchVocabulary.php` (new),
`tests/Unit/ProductSearchVocabularyTest.php` (new)

What:
- `words(): array` returns `['word' => frequency]` built from
  `Product::query()->select('NAME')->pluck('NAME')`, lowercased, split on
  `/[^a-z0-9]+/`, words shorter than 3 chars dropped. Wrap in
  `Cache::remember('product-search:vocab', now()->addHour(), ...)`.
- `correct(string $token): ?string` returns the closest vocabulary word or null.
  Rules: skip tokens shorter than 4 chars and all-digit tokens (barcodes are
  never "corrected"); only compare words whose length differs by ≤2; accept
  distance ≤1 for tokens of 4–6 chars, ≤2 for longer; on ties prefer the more
  frequent word; if any vocabulary word already contains the token as a
  substring, return null (the token is not a typo, it is a partial word).
- `forget(): void` clears the cache key. Call it from `ProductController@store`
  and `@update` after a product is created/renamed (one line each; find the
  existing success paths, do not restructure those methods).

Check: unit test seeds a SQLite `PRODUCTS` table with names
"Chocolatemakers forest fruit milk chocolate 100 gram" and "Apple Juice 1L";
asserts `correct('chocolatmakers') === 'chocolatemakers'`,
`correct('friut') === 'fruit'`, `correct('choc') === null` (substring),
`correct('8721325594341') === null`, `correct('xyzzyqq') === null`.
`php artisan test --filter=ProductSearchVocabularyTest` passes.

### 2. `ProductSearchService` (query, ranking, hydration)
Files: `app/Services/ProductSearch/ProductSearchService.php` (new),
`app/Services/ProductSearch/ProductSearchCriteria.php` (new, a small readonly
DTO: `q`, `stocked` bool default true, `supplierId`, `categoryId`,
`excludeIds` array, `page` int default 1, `perPage` int default 20 max 50)

What: `search(ProductSearchCriteria $c): array` returning
`['data' => [...items...], 'meta' => [...]]` (shapes in Step 3). Internals:

a. **Tokenise**: trim, collapse whitespace, lowercase, split on whitespace,
   strip characters other than `[a-z0-9%.-]` from each token, drop empties,
   keep max 6 tokens. If `q` is empty, tokens are empty and the query returns
   the browse listing (stocked filter still applies) ordered by NAME.

b. **Barcode mode**: if the whole trimmed `q` is digits and ≥6 long, add the
   condition `CODE = q OR REFERENCE = q OR CODE LIKE 'q%' OR CODE IN (supplier
   codes equal to q)` instead of tokenising. Exact match ranks first (rank 0).

c. **Per-token condition** (AND across tokens, OR within a token):
   `NAME LIKE '%t%' OR CODE LIKE '%t%' OR REFERENCE LIKE '%t%'`, plus
   `OR CODE IN (:barcodes)` where `:barcodes` =
   `DB::connection('pos')->table('supplier_link')->where('SupplierCode','like',"%t%")->limit(500)->pluck('Barcode')`
   only when the token is ≥3 chars and the pluck returned fewer than 500 rows.
   Escape `%` and `_` in tokens before building LIKE patterns.

d. **Base query**: `Product::query()->select(['PRODUCTS.ID','PRODUCTS.CODE','PRODUCTS.REFERENCE','PRODUCTS.NAME','PRODUCTS.DISPLAY','PRODUCTS.CATEGORY','PRODUCTS.TAXCAT','PRODUCTS.PRICESELL','PRODUCTS.PRICEBUY','PRODUCTS.ISSERVICE'])->addSelect(DB::raw('(CASE WHEN PRODUCTS.IMAGE IS NOT NULL AND LENGTH(PRODUCTS.IMAGE) > 0 THEN 1 ELSE 0 END) as has_image'))`.
   Filters: stocked → `->join('stocking', 'stocking.Barcode', '=', 'PRODUCTS.CODE')`
   (never the `stocked()` scope); category → `where('PRODUCTS.CATEGORY', ...)`;
   supplier → pre-pluck `supplier_link.Barcode where SupplierID = ?` then
   `whereIn('PRODUCTS.CODE', ...)`; excludeIds → `whereNotIn('PRODUCTS.ID', ...)`.

e. **Ranking** via one `orderByRaw` CASE with bound parameters, then `NAME ASC`:
   0 `CODE = :q OR REFERENCE = :q`;
   1 `NAME LIKE ':q%'` (whole phrase prefix);
   2 `NAME LIKE '%:q%'` (whole phrase anywhere);
   3 every token at a word start: AND over tokens of `(NAME LIKE 't%' OR NAME LIKE '% t%')`;
   4 everything else.
   Must run on SQLite and MySQL (plain LIKE/CASE only).

f. **Pagination**: `->paginate($perPage, ['*'], 'page', $page)` is fine
   (COUNT measured 28 ms). Eager-load `with(['tax','category','stockCurrent','supplierLink.supplier'])`
   on the paginated results only (`SupplierLink::supplier()` exists at
   `app/Models/SupplierLink.php:74`). These eager loads are `whereIn` on ≤50
   literal values and are fast; do not move them into the main query.

g. **Typo fallback**: if `tokens` non-empty, not barcode mode, and `total === 0`,
   map each token through `ProductSearchVocabulary::correct()`; if at least one
   token changed, rerun (a)–(f) with the corrected tokens and set
   `meta.corrected_query` to the corrected string. Never correct twice.

h. **Image URL per item** (batch, no per-row queries):
   `has_image` → `route('products.image', $id)`; else if supplier ID ∈ Udea IDs
   (use `SupplierService::hasExternalIntegration()` and
   `usesSupplierCodeImages()` to branch) → `getExternalImageUrlByBarcode()`;
   else if supplier-code-keyed (Independent) → one
   `SupplierImageCache::whereIn('supplier_code', $codes)->where('supplier_id', 37)->get()`
   for the page, then `getExternalImageUrlBySupplierCode()` only for codes with a
   cache hit or, when no cache row exists, the template URL (the browser
   `onerror` hides failures); else `null`.

i. `meta.took_ms` = wall time of the whole `search()` call, rounded to 1 dp.

Check: `php artisan tinker --execute='dump(app(App\Services\ProductSearch\ProductSearchService::class)->search(new App\Services\ProductSearch\ProductSearchCriteria(q: "chocolatemakers fruit"))["data"][0]["name"] ?? null)'`
prints `"Chocolatemakers forest fruit milk chocolate 100 gram"`; the same with
`q: "chocolatmakers friut"` prints the same name and `meta.corrected_query`
is `"chocolatemakers fruit"`; `meta.took_ms` < 100 for both on the live POS DB.
Also `q: "8721325594341"` returns that product first with `match_rank` 0.

### 3. JSON endpoint
Files: `app/Http/Controllers/Api/ProductSearchController.php` (new, `__invoke`),
`routes/web.php` (add one line next to line 132, inside the auth group):
`Route::get('/api/products/search', ProductSearchController::class)->name('api.products.search');`

What: validate `q` (nullable string max 100), `stocked` (boolean, default true;
accept `0/1/true/false`), `supplier_id` (nullable string), `category_id`
(nullable string), `exclude` (nullable, comma-separated IDs, max 200),
`page` (int ≥1), `per_page` (int 1–50, default 20). Build the criteria, call the
service, return JSON:

```json
{
  "data": [
    {
      "id": "…uuid…", "code": "8721325594341", "reference": "8721325594341",
      "name": "Chocolatemakers forest fruit milk chocolate 100 gram", "display": null,
      "category_id": "…", "category_name": "Chocolate",
      "price_sell": 3.25, "price_with_vat": 4.00, "vat_rate": 0.23, "vat_label": "23%",
      "vat_badge_class": "bg-… text-…",
      "stock_units": 12.0, "stock_location": null, "has_stock_record": true,
      "is_stocked": true, "is_service": false, "has_image": false,
      "image_url": "https://cdn.ekoplaza.nl/…/8721325594341.jpg",
      "supplier": { "id": "5", "name": "Udea", "code": "6001397", "website_url": "https://…" },
      "edit_url": "/products/…/edit", "match_rank": 3
    }
  ],
  "meta": { "query": "chocolatemakers fruit", "corrected_query": null,
            "total": 1, "page": 1, "per_page": 20, "last_page": 1,
            "stocked": true, "took_ms": 38.2 }
}
```
`supplier` is `null` when there is no link. `price_with_vat`, `vat_label`,
`vat_badge_class` come from the existing accessors on `Product`
(`getFormattedVatRateAttribute` line 352, `getFormattedPriceWithVatAttribute`
line 378, `getTaxCategoryBadgeClassAttribute` line 396) or their numeric
equivalents; `website_url` from `SupplierService::getSupplierWebsiteLink()`.
Unauthenticated requests get a 401/redirect like the other `api/products/*`
routes.

Check: logged-in `curl -s -b cookies 'http://localhost/api/products/search?q=chocolatemakers%20fruit' | jq '.data[0].name, .meta'`
shows the product and `took_ms`. `php artisan route:list --name=api.products.search`
lists the route.

### 4. Feature tests for the endpoint
Files: `tests/Feature/ProductSearchApiTest.php` (new),
`tests/Concerns/CreatesProductSearchPosTables.php` (new trait, modelled on
`CreatesKitchenOrderPosTables`, adding `stocking` (Barcode PK), `CATEGORIES`
(ID, NAME), `TAXCATEGORIES` (ID, NAME), `TAXES` (ID, NAME, CATEGORY, RATE),
and the `TAXCAT`, `PRICESELL`, `PRICEBUY`, `ISSERVICE`, `DISPLAY` columns on
`PRODUCTS`; check `Product::tax()` at `Product.php:321` for the exact join
columns it needs).

Seed: supplier 5 "Udea", 37 "Independent"; products
P1 "Chocolatemakers forest fruit milk chocolate 100 gram" code 8721325594341 (Udea, code 6001397, stocked, no blob),
P2 "Chocolatemakers Puffed Quinoa and Ginger 80g" code 8719324515672 (Udea, stocked),
P3 "Milk Chocolate Bar" code 5000000000001 (no supplier, NOT stocked),
P4 "Apple Juice 1L" code 1000001 (Independent, code IND-A, stocked, blob image bytes),
P5 "Old Delisted Thing" code 1000009 (not stocked).

Tests (each one assertion group, names as listed):
- `words_match_in_any_order`: `q=chocolatemakers fruit` → exactly P1.
- `ranking_prefers_exact_code_then_prefix`: `q=8721325594341` → P1 first,
  `match_rank` 0; `q=milk` with `stocked=0` → P3 ("Milk…" prefix) before P1.
- `stocked_is_default_and_toggle_includes_unstocked`: `q=milk` → P1 only;
  `q=milk&stocked=0` → P1 and P3.
- `supplier_code_matches`: `q=6001397` → P1; `q=ind-a` → P4.
- `typo_correction_reports_corrected_query`: `q=chocolatmakers friut` → P1,
  `meta.corrected_query === 'chocolatemakers fruit'`.
- `no_correction_when_results_exist`: `q=choc` → `corrected_query` null, ≥2 rows.
- `image_url_prefers_blob_then_udea_cdn`: P4 `image_url` ends with
  `/products/<P4 id>/image`; P1 `image_url` contains `8721325594341.jpg`; P3 null.
- `exclude_and_supplier_filter`: `exclude=<P1 id>&q=chocolatemakers` → P2 only;
  `supplier_id=37&q=&stocked=1` → P4 only.
- `per_page_is_capped_at_50_and_requires_auth`.

Check: `php artisan test --filter=ProductSearchApiTest` → all pass.

### 5. The Blade component
Files: `resources/views/components/product-search.blade.php` (new)

Props:
```
mode        'picker' | 'list'          (default 'picker')
url         string                     (default route('api.products.search'))
stocked     bool                       (default true)
showStockedToggle bool                 (default true)
supplierId, categoryId  ?string        (default null; hidden fixed filters)
excludeIds  array                      (default [])
perPage     int                        (default 10 picker / 20 list)
placeholder string
minLength   int                        (default 1; barcodes and short words are fine at ≤50 rows)
debounce    int ms                     (default 250)
autofocus   bool                       (default false)
camera      bool                       (default false; renders the camera button and
                                        wires window.BarcodeScanner from resources/js/barcode-scanner.js,
                                        pushing @vite(['resources/js/barcode-scanner.js']) @once like
                                        products/index.blade.php lines 475–476)
initial     ?array                     (list mode: server-rendered first response, see Step 7)
syncUrl     bool                       (list mode: mirror q/stocked/page into the address bar
                                        with history.replaceState, default true)
name        ?string                    (picker: hidden input name receiving the selected product id)
```
Slots: `row-actions` (list mode; Blade slot rendered inside the `x-for`
template, so it may use Alpine expressions on `product`, e.g.
`:href="product.edit_url"`), `empty` (optional).

Behaviour (one Alpine factory `productSearch(config)` defined once inside
`@once @push('scripts')`, as the other pages do; plain function on `window`,
no `Alpine.data`, because `app.js` starts Alpine before the stack runs):
- Input with search icon, spinner, clear (×) button, optional camera button.
  `@input.debounce.<ms>` → `run()`; `@keydown.down/up/enter/escape` for
  keyboard navigation; Enter with no open results runs the search and commits
  the top match (barcode scanner flow, copied from customer-requests `_form`).
- Stale-response guard: keep a request counter; ignore responses whose counter
  is not the latest (see `fruit-veg/waste.blade.php:355`).
- Fetch with `Accept: application/json` and `credentials: 'same-origin'`.
- "Include unstocked" checkbox (when `showStockedToggle`), re-runs the search.
- Picker mode: absolutely positioned dropdown, `@click.outside` closes; each
  row: 40 px thumbnail (`<img loading="lazy">` with `onerror` swapping to the
  same grey SVG placeholder used by `x-product-image`), name, code, supplier
  name + code, price incl. VAT, stock units. Selecting a row sets the hidden
  input (when `name` given), `$dispatch('product-search:selected', product)`
  (bubbles), clears the input, closes the dropdown. Also `product-search:cleared`.
- List mode: results table with columns image, product (name + code), category,
  supplier (name, code, website link), price incl. VAT, VAT badge, stock, and
  the `row-actions` slot; footer with "Showing x–y of N", prev/next buttons;
  a one-line notice "Showing results for **corrected** (search instead for
  *original*)" when `meta.corrected_query` is set; "No products found" empty
  state; a small "N ms" badge from `meta.took_ms` (grey, right-aligned, so the
  user can see speed on every page).
- Dark mode classes throughout (the app uses `dark:` variants everywhere).
- No inline stock editing inside the component (that stays a `/products`
  concern, added through the `row-actions` slot or a follow-up).

Check: `php artisan view:cache` compiles without error, then `php artisan view:clear`.

### 6. Test page
Files: `app/Http/Controllers/ProductSearchTestController.php` (new),
`resources/views/products/search-test.blade.php` (new), `routes/web.php`
(add `Route::get('/products/search-test', ...)->name('products.search-test');`
next to the `products.independent-test` line 94, before `/products/{id}`).

What: an `x-admin-layout` page with three sections:
1. Picker demo: `<x-product-search mode="picker" :camera="true" name="demo_product" />`
   plus a panel that listens for `product-search:selected` and pretty-prints
   the JSON of the selected product.
2. List demo: `<x-product-search mode="list" :camera="true">` with a
   `row-actions` slot containing an Edit link.
3. Debug panel: last request URL, `meta` block, and a running log of
   `took_ms` for the last 10 searches.

Check: log in, open `/products/search-test`, type `chocolatemakers fruit`,
`chocolatmakers`, `8721325594341`, `6001397` (supplier code), `milk` with and
without "Include unstocked". Each shows correct results, thumbnails for Udea
products, and `took_ms` < 100. Record the observed timings in `implemented.md`.

### 7. Migrate `/products` to the component
Files: `app/Http/Controllers/ProductController.php` (index only, lines 123–180),
`resources/views/products/index.blade.php`

What:
- `index()` reads `q` (fallback to legacy `search`), `stocked`
  (default true; legacy `stocked_only=1` also means true), `supplier_id`,
  `category_id`, `page`, `per_page`, `show_stats`. It calls
  `ProductSearchService::search()` once and passes the array as `:initial`
  so the first page is server-rendered (fast first paint, shareable URLs,
  and `assertSee` in tests keeps working). Keep the statistics block and its
  `show_stats` toggle unchanged. Keep loading `$suppliers` and `$categories`
  for the two optional dropdowns (pass `stockedOnly: $stocked` and nothing
  else to the existing repository methods).
- Replace `x-filter-form` and the hand-written table with
  `<x-product-search mode="list" :initial="$initial" :camera="true" :supplier-id="$supplierId" :category-id="$categoryId">`.
  Put the supplier and category `<select>`s in a collapsed "More filters"
  disclosure above the component; changing either reloads the page with the
  new query string (keep it simple, these are rarely used).
- `row-actions` slot: the sales-chart button (`showSalesChartModal(product.id, product.name)`),
  the edit link, and the inline stock editor moved from lines 280–311 into
  an Alpine snippet inside the slot using `product.stock_units`,
  `product.has_stock_record`, `product.is_service`, calling the existing
  `updateStock()` JS (keep that function and `showToast()`; the
  `/products/{id}/update-stock` endpoint is unchanged).
- Remove the `active_only`, `in_stock_only`, `show_suppliers` handling from
  the view; suppliers are always shown. Delete the supplier-dropdown toggle JS
  (lines 371–391).
- Camera scanner: on detection navigate to `route('products.index') . '?q=' . barcode`
  (was `?search=`).

Check: `GET /products` renders 20 stocked products with thumbnails; `?q=chocolatemakers%20fruit`
shows the one product; `?search=8721325594341` (legacy param) still works;
statistics toggle still works; inline stock edit still updates and toasts;
sales chart modal still opens.

### 8. Docs and known-issues
Files: `docs/features/product-search.md` (new), `docs/FEATURES_INDEX.md`
(add an entry under Product Management), `docs/development/known-issues.md`
(add a section "POS collation mismatch: never `WHERE EXISTS` against
`stocking`/`supplier_link`" with the timings table from Context),
`CLAUDE.md` (one bullet under Product Management: "Product Search Bar
(`x-product-search`, `/api/products/search`)").

What: `product-search.md` documents the component props, events, JSON shape,
ranking tiers, typo-correction rules, the performance rules, and a
"migrating a page" checklist for the follow-up tasks (list the nine call
sites from Context by file path).

Check: links in `docs/FEATURES_INDEX.md` and `CLAUDE.md` resolve to existing files.

### 9. Update `ProductTest` for the new parameters
Files: `tests/Feature/ProductTest.php`

What (planned change, not a workaround): extend `setUp()` to also create
`stocking`, `supplier_link`, `suppliers`, `STOCKCURRENT`, `CATEGORIES`,
`TAXCATEGORIES`, `TAXES` via the Step 4 trait (or reuse it), and mark
"Kitchen Item" and "Test Product 1" as stocked. Change
`test_can_search_products_by_name` to `GET /products?q=Kitchen`; replace
`test_can_filter_active_products_only` with
`test_unstocked_products_hidden_by_default_and_shown_with_toggle`
(`/products?q=Test` hides an unstocked "Test Product 2"; `?q=Test&stocked=0`
shows it). Add `test_legacy_search_param_still_works` (`?search=Kitchen`).
Leave the other tests untouched.

Check: `php artisan test --filter=ProductTest` → all pass.

## Verification

Run in order:
1. `./vendor/bin/pint --test` → no style errors on changed files.
2. `php artisan test --filter='ProductSearchVocabularyTest|ProductSearchApiTest|ProductTest'` → all pass.
3. `php artisan test` → no new failures (record any pre-existing failures with
   names so the reviewer can compare).
4. `php artisan route:list --path=products` → shows `api.products.search` and
   `products.search-test`; `/products/{id}` still after `/products/search-test`.
5. Live check on the test page (`/products/search-test`), record `took_ms` for:
   `chocolatemakers fruit`, `chocolatmakers friut`, `8721325594341`, `6001397`,
   `milk`, `a` (single character, worst case), and an empty query. All must be
   < 100 ms warm; "a" may be slower but must be < 300 ms.
6. Live check `/products`: default page, `?q=...`, legacy `?search=...`,
   pagination next/prev, inline stock edit, sales chart, camera button opens.
7. `php artisan tinker --execute='dump(Cache::get("product-search:vocab") !== null)'`
   after one typo search → `true`.

## Risks

- **EXISTS/JOIN regression**: any "tidy-up" to use `Product::stocked()` or a
  correlated subquery turns 40 ms into 20 s. The Step 2 checks measure this;
  the known-issues note is there so it does not come back.
- **Blob leakage**: selecting `PRODUCTS.*` anywhere in the search path pulls
  `IMAGE` blobs (714 products). Explicit `select()` lists only.
- **SQLite vs MySQL LIKE**: SQLite's LIKE is ASCII case-insensitive; MySQL's
  is collation-based. Names with accents may rank differently between tests
  and live. Acceptable; do not add `COLLATE` (it breaks SQLite and errors on
  the utf8 column, as measured).
- **Independent images**: without a cache row the template URL may 404 and
  the `onerror` fallback hides it. That is the current behaviour elsewhere.
- **Duplicate supplier links**: two barcodes have more than one `supplier_link`
  row. `supplierLink` is `hasOne`, so the first is used; matches existing
  pages.
- **Legacy links**: anything linking to `/products?search=` keeps working via
  the fallback in Step 7; `active_only`/`in_stock_only` links silently lose
  those filters, which the user has confirmed are never used.
- **Old `ProductTest` fixture** only has `PRODUCTS`; Step 9 must run before
  claiming the suite passes.

## Review

Planner review of revision 1, 2026-09-16. Read `implemented.md`, the full diff
and every new file; reran the checks myself.

### Verified by the Planner
- `pint --test` on the 11 changed PHP files: pass.
- `php artisan test --filter='ProductSearchVocabularyTest|ProductSearchApiTest|ProductTest'`:
  3 + 11 pass; `ProductTest` 6 pass, 2 fail (`shows_404_for_non_existent_product`,
  `product_statistics_are_displayed`). Both fail identically on a clean checkout
  of HEAD `76dc597c` (that class fails 5 of 7 there), so they are pre-existing.
- Full suite: 17 failures. All 17 fail on a clean checkout of HEAD as well
  (verified in a throwaway worktree). No new failures.
- Live POS timings (warm, my run): "chocolatemakers fruit" 44 ms, typo path
  87 ms, barcode 28 ms, supplier code 32 ms, "milk" 73 ms, "a" 79 ms, empty
  66 ms, "choc milk 100" 64 ms, "milk" page 3 80 ms. One search runs 8 POS
  queries (pluck, page select, count, five `whereIn` eager loads) and 1 Laravel
  query. Within the plan's limits.
- HTTP through the kernel as a logged-in user: `/products` 200 with the
  component and stocked toggle; `?q=chocolatemakers fruit` and legacy
  `?search=8721325594341` both render the product server-side; typo query
  shows the corrected banner; `/products/search-test` 200 with both modes;
  `/api/products/search?q=6001397` returns the Udea product; `per_page=51`
  422; unauthenticated 401. `view:cache` compiles.
- Route order: `products/search-test` registered before `products/{id}`.

### Criteria
| Step | Result |
|---|---|
| 1 Vocabulary | Pass. Transposition-as-one-edit (Deviation 1) accepted; it is the right call for `friut`. |
| 2 Service | Pass. Deviation 2 (skip COUNT on a short first page) and 3 (batched Independent image cache) accepted. |
| 3 Endpoint | Pass. `vat_label` is `"23.0%"` from the existing accessor; fine. |
| 4 API tests | Pass, 11 tests, more than asked. |
| 5 Component | Pass. Camera scan stays on the page (Deviation 4) accepted. "Search instead" showing the honest empty state (Deviation 5) accepted; no `exact=1` follow-up needed. |
| 6 Test page | Pass. |
| 7 `/products` | Pass. Unfiltered dropdown loaders (Deviation 6) accepted and correctly documented. |
| 8 Docs | Pass. |
| 9 `ProductTest` | Pass. Deviation 7 (`assertDontSee('Test Service Product')` on the default list) accepted; it follows the stocked-by-default decision. |

### Defects (fix in revision 2)
1. **Deprecation on every default `/products` load.** The browse page includes
   Natural Medicine products (supplier 65). Its config has
   `website_search => null` with `enabled => true`, so
   `SupplierService::getSupplierWebsiteLink()` reaches
   `str_replace('{SUPPLIER_CODE}', $code, null)` and PHP logs
   "Passing null to parameter #3" (`app/Services/SupplierService.php:290`).
   Pre-existing bug, but the new page triggers it on every load and in tinker
   output.
2. **`implemented.md` is incomplete.** It has Step 1 and Step 2 only; Steps
   3–9 have no sections, the deviation list has no `## Deviations` heading, and
   the Verification section points at "see Step 6" / "see Step 7" which do not
   exist. The protocol needs the per-step record.
3. **`ProductController@updateName` also renames a product** (line 189) but
   does not call `forget()`, so an inline rename leaves a stale vocabulary for
   up to an hour. Noted by the Implementer; do it.

### Revision 2 steps

#### 10. Guard the missing website template
Files: `app/Services/SupplierService.php` (`getSupplierWebsiteLink`, lines 272–298)
What: after the existing `$config` check add
`if (empty($config['website_search'])) { return null; }` before the
`str_replace`. No other change to the method.
Check: `php artisan tinker --execute='app(App\Services\ProductSearch\ProductSearchService::class)->search(new App\Services\ProductSearch\ProductSearchCriteria(q: ""));'`
prints no DEPRECATED line. Add one assertion to
`tests/Feature/ProductSearchApiTest.php`: seed a supplier `65` "Natural
Medicine" link on P5 (code `NM-1`) in the trait, and assert
`supplier.website_url` is `null` for P5 in `test_response_carries_supplier_price_and_stock_details`
(or a new small test). `php artisan test --filter=ProductSearchApiTest` passes.

#### 11. Forget the vocabulary on inline rename
Files: `app/Http/Controllers/ProductController.php` (`updateName`, from line 189)
What: `app(ProductSearchVocabulary::class)->forget();` on the success path,
same as `store()`/`update()`.
Check: `grep -n "ProductSearchVocabulary::class)->forget()" app/Http/Controllers/ProductController.php`
shows three call sites.

#### 12. Complete `implemented.md`
Files: `docs/planImp/implemented.md`
What: add `### 3.` to `### 9.` sections (files changed and the real check
output for each, including the test-page timings you observed and the Chrome
checks for `/products`), put the deviation list under a `## Deviations`
heading, add Steps 10–12 with their checks, and set `Plan revision: 2`.
Check: `grep -c "^### " docs/planImp/implemented.md` is at least 12 and
`grep -n "^## Deviations"` finds the heading.

#### Verification for revision 2
1. `./vendor/bin/pint --test app/Services/SupplierService.php app/Http/Controllers/ProductController.php tests/Feature/ProductSearchApiTest.php tests/Concerns/CreatesProductSearchPosTables.php` → pass.
2. `php artisan test --filter='ProductSearchVocabularyTest|ProductSearchApiTest|ProductTest'` → only the two pre-existing `ProductTest` failures remain.
3. The tinker command in Step 10 prints nothing.

### Revision 2 review (Planner, 2026-09-16)
- Step 10: `website_search` guard present at `SupplierService.php:287`; the empty-query search prints no DEPRECATED line. Pass.
- Step 11: `forget()` now called in `updateName`, `store`, `update`. Pass.
- Step 12: `implemented.md` has 14 step sections, a `## Deviations` heading, `Plan revision: 2`. Pass.
- Tests: 20 pass, only the two pre-existing `ProductTest` failures remain. Pint passes on the four touched files.

ACCEPTED. Archive with `mkdir -p docs/planImp/archive/2026-09-16-product-search && git mv`-free move of `plan.md` and `implemented.md` into it.

