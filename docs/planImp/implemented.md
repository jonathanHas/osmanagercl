# Reusable product search bar (`x-product-search`) — implementation

Status: DONE
Plan revision: 2
Implementer: Opus
Date: 2026-09-16

## Baseline
HEAD: 76dc597c
Pre-existing dirty files:
```
 M app/Http/Controllers/Auth/AuthenticatedSessionController.php
 M app/Http/Controllers/CustomerRequestController.php
 D docs/planImp/implemented.md
 M docs/planImp/plan.md
 M resources/views/customer-requests/_form.blade.php
D  resources/views/customer-requests/create.blade.php
 M resources/views/customer-requests/edit.blade.php
 M resources/views/customer-requests/index.blade.php
 M resources/views/customer-requests/show.blade.php
 M resources/views/layouts/board.blade.php
 M tests/Feature/Auth/AuthenticationTest.php
 M tests/Feature/CustomerRequestTest.php
?? docs/planImp/archive/
```

## Steps
### 1. `ProductSearchVocabulary` — done
Changed: `app/Services/ProductSearch/ProductSearchVocabulary.php` (new),
`tests/Unit/ProductSearchVocabularyTest.php` (new),
`app/Http/Controllers/ProductController.php` (`use` line + `forget()` after
the success log in `store()` and `update()`, lines 1413 / 1648).
Check output:
```
PASS  Tests\Unit\ProductSearchVocabularyTest
  ✓ builds vocabulary of words three chars or longer
  ✓ corrects typos against vocabulary
  ✓ forget clears the cache
Tests: 3 passed (13 assertions)

live POS (tinker): build: 51.1 ms, 6212 words
chocolatmakers => 'chocolatemakers' (4.32 ms)
friut => 'fruit' (4.36 ms)
choc => NULL  |  8721325594341 => NULL  |  xyzzyqq => NULL
```
See Deviations #1 (transposition distance).


### 2. `ProductSearchService` — done
Changed: `app/Services/ProductSearch/ProductSearchService.php` (new),
`app/Services/ProductSearch/ProductSearchCriteria.php` (new).
Check output (live POS, tinker, second call = warm):
```
"chocolatemakers fruit"  total=1    took= 93.3 ms (warm  46.6) corrected=null                    first=Chocolatemakers forest fruit milk chocolate 100 gram rank=3
"chocolatmakers friut"   total=1    took= 94.1 ms (warm  89.5) corrected="chocolatemakers fruit" first=Chocolatemakers forest fruit milk chocolate 100 gram rank=3
"8721325594341"          total=1    took= 33.6 ms (warm  33.0) corrected=null                    first=Chocolatemakers forest fruit milk chocolate 100 gram rank=0
"6001397"                total=1    took= 32.1 ms (warm  42.5) corrected=null                    first=Chocolatemakers ... rank=4   (supplier code)
"milk"                   total=73   took= 87.7 ms (warm 107.4) corrected=null                    first=Milk Alternative rank=1
"a"                      total=4885 took=103.9 ms (warm 107.9)                                   first=A A Cake Free rank=1
""                       total=5219 took= 77.4 ms (warm  77.2)                                   first= cake rank=null
milk page 2: total=73 page=2 rows=20 took=77.0
```
Query profile for "milk" (77.7 ms wall): supplier_link pluck 3.6 ms, main
select 30.4 ms, COUNT 28.9 ms, five eager loads + image cache ≈ 5 ms. EXPLAIN
confirms the shape the plan wants (stocking index → eq_ref PRODUCTS_INX_1).
The two ~30 ms queries are the floor; "milk"/"a" hover around 80–105 ms
depending on run. See Deviations #2 (COUNT skipped when the page is short).

### 3. JSON endpoint — done
Changed: `app/Http/Controllers/Api/ProductSearchController.php` (new),
`routes/web.php` (one line after `api.products.suggest-barcode`, inside the auth group).
Check output (logged in as admin via curl):
```
$ curl -b cookies '.../api/products/search?q=chocolatemakers%20fruit' | jq '.data[0], .meta'
{"name":"Chocolatemakers forest fruit milk chocolate 100 gram","code":"8721325594341",
 "image_url":"https://cdn.ekoplaza.nl/ekoplaza/producten/small/8721325594341.jpg",
 "supplier":{"id":"5","name":"Udea","code":"6001397","website_url":"https://www.udea.nl/search/?qry=6001397"},
 "category_name":"Chocolate","price_with_vat":6.25,"vat_label":"23.0%",
 "vat_badge_class":"bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100",
 "stock_units":7,"has_stock_record":true,"is_stocked":true,"is_service":false,"has_image":false,
 "match_rank":3,"edit_url":"/products/98f8a46e-.../edit"}
{"query":"chocolatemakers fruit","corrected_query":null,"total":1,"page":1,"per_page":20,"last_page":1,"stocked":true,"took_ms":51}

$ php artisan route:list --path=api/products
GET|HEAD   api/products/search  api.products.search › Api\ProductSearchController

unauthenticated: HTML → 302 /login ; Accept: application/json → 401

took_ms over HTTP, 5–8 samples each (box load average ≈ 8.9 while measuring):
  chocolatemakers fruit : 38 43 50 40 44 49
  chocolatmakers friut  : 95 79 97 85 92 87 93 94   (typo path, two passes)
  8721325594341         : 47 35 35 36 33
  6001397               : 39 35 35 35 39
  milk                  : 83 77 91 77 91
  milk&stocked=0        : 48
  a                     : 86 101 84 85 132
  (empty)               : 80 183 143 142 73   (browse: ORDER BY NAME over the 5.2k join = 38 ms + COUNT 14 ms; outliers coincide with load spikes)
```
`vat_label` is `"23.0%"` (existing `formatted_vat_rate` accessor), not `"23%"`
as in the plan's sample JSON.

### 4. Feature tests for the endpoint — done
Changed: `tests/Feature/ProductSearchApiTest.php` (new),
`tests/Concerns/CreatesProductSearchPosTables.php` (new).
All nine planned tests plus two extra (`response_carries_supplier_price_and_stock_details`,
`pagination_meta_is_accurate` — the latter guards Deviation #2).
Check output:
```
PASS  Tests\Feature\ProductSearchApiTest
  ✓ words match in any order
  ✓ ranking prefers exact code then prefix
  ✓ stocked is default and toggle includes unstocked
  ✓ supplier code matches
  ✓ typo correction reports corrected query
  ✓ no correction when results exist
  ✓ image url prefers blob then udea cdn
  ✓ response carries supplier price and stock details
  ✓ exclude and supplier filter
  ✓ pagination meta is accurate
  ✓ per page is capped at 50 and requires auth
Tests: 11 passed (70 assertions)      (73 after Step 10)
```

### 5. The Blade component — done
Changed: `resources/views/components/product-search.blade.php` (new).
All props from the plan; slots `row-actions` (used as `<x-slot:row-actions>`,
Blade camel-cases it to `$rowActions`) and `empty`. Events
`product-search:selected`, `product-search:cleared`, plus
`product-search:results` (`{data, meta, url}`) which the test page's debug
panel uses. Alpine factory is `window.productSearch(config)` inside
`@once @push('scripts')`; camera pushes `@vite(['resources/js/barcode-scanner.js'])`
under its own `@once` key. Debounce is a manual `setTimeout` (Alpine's
`.debounce.Nms` modifier can't take a prop value).
Check output:
```
$ php artisan view:cache   →   INFO  Blade templates cached successfully.
$ php artisan view:clear   →   INFO  Compiled views cleared successfully.
```
See Deviations #4 (camera runs the search in-page) and #5 ("search instead").

### 6. Test page — done
Changed: `app/Http/Controllers/ProductSearchTestController.php` (new, `__invoke`),
`resources/views/products/search-test.blade.php` (new), `routes/web.php`
(`products.search-test` right after `products.independent-test`, before `/products/{id}`).
Check output (Chrome, logged in, `/products/search-test`; `took_ms` from the
debug panel / component meta):
```
query                   stocked?   total  took_ms  corrected               first result
chocolatemakers fruit   yes        1      44.7                             Chocolatemakers forest fruit milk chocolate 100 gram (Udea CDN thumbnail shown)
chocolatmakers friut    yes        1      84.4     chocolatemakers fruit   same
chocolatmakers          yes        3      95.7     chocolatemakers         same; banner "Showing results for chocolatemakers (search instead for chocolatmakers)"
8721325594341           yes        1      40.4                             same
6001397 (supplier code) yes        1      39.4                             same
milk                    yes        73     73.2                             Milk Alternative
milk                    no         215    62.9                             Milk Alternative
a                       yes        4885   127.9                            A A Cake Free   (< 300 allowed)
(empty)                 yes        5219   68.8                             " cake"
```
Picker: typed "milk choc" → dropdown with thumbnails, ↓↓ Enter selected the
third row; hidden `demo_product` input = product id, input cleared, dropdown
closed, `product-search:selected` payload printed. Barcode-scanner flow: typed
`5412860001205` + Enter with no dropdown open → searched (43 ms) and selected
"Chocolates From Heaven Belgian Milk Chocolate 100g", match_rank 0.
List: Next/Prev → "Showing 41–60 of 73" / "Showing 21–40 of 73". Camera
button opens the teleported modal ("Starting camera…" — no HTTPS on dev, as
on the old page). Console: no errors.

### 7. Migrate `/products` — done
Changed: `app/Http/Controllers/ProductController.php` (`index()` rewritten;
two `use` lines), `resources/views/products/index.blade.php` (statistics
block kept verbatim; `x-filter-form`, hand-written table, supplier-toggle JS
and the page's own scanner modal/JS removed; "More filters" `<details>` with
supplier + category selects that reload via `productsFilterChanged()`;
`<x-product-search mode="list" :initial :camera :supplier-id :category-id>`
with a `row-actions` slot holding the inline stock editor, sales-chart button
and edit link; `updateStock()` / `showToast()` kept; `<x-sales-chart-modal />` kept).
Check output (curl, server-rendered `initial` decoded from the page):
```
/products                                 200 0.157s  total=5219 page=1 stocked=True took=70.4 rows=20 first=" cake"
/products?q=chocolatemakers%20fruit       200 0.126s  total=1    page=1 stocked=True took=44.6 rows=1  first=Chocolatemakers forest fruit…
/products?search=8721325594341  (legacy)  200 0.113s  total=1    page=1 stocked=True took=35.4 rows=1  first=Chocolatemakers forest fruit…
/products?stocked_only=1&search=milk      200 0.175s  total=73   page=1 stocked=True took=92.3 rows=20 first=Milk Alternative
/products?q=milk&stocked=0&page=2         200 0.126s  total=215  page=2 stocked=False took=48.9 rows=20 first=Biona Coconut Milk 200ml
/products?supplier_id=37                  200 0.179s  total=1377 page=1 stocked=True took=98.1 rows=20 supplierId=37
```
Chrome: default page shows 20 stocked products with thumbnails and the
"87 ms" badge; inline stock edit on " cake" (re-saved its current value 0 so
live POS data is unchanged) → toast "Stock updated successfully", editor
closes; sales-chart button opens the modal titled "Het Dichtste Bij Spelt
tagliatelle 500g - Sales History"; camera button opens the modal; simulated
scan of 8721325594341 → in-page search, address bar `?q=8721325594341`;
Next page with "Include unstocked" → `?q=milk&stocked=0&page=2`,
"Showing 21–40 of 215"; typo banner + "search instead" → empty state;
`?show_stats=1&supplier_id=37` shows statistics, "More filters" open with
Independent selected and 1377 results (404 CDN images fall back to the
placeholder, cached ones load). Console: no errors.
See Deviations #6 (dropdown loaders).

### 8. Docs and known-issues — done
Changed: `docs/features/product-search.md` (new), `docs/FEATURES_INDEX.md`
(entry under Product Management), `docs/development/known-issues.md` (new
section "POS Collation Mismatch: Never `WHERE EXISTS` Against `stocking` /
`supplier_link`" with the timings table, under Database & Transaction Issues),
`CLAUDE.md` (bullet under Product Management + a "Where to Find Information" line).
Check output:
```
CLAUDE.md:            ](./docs/features/product-search.md)  → exists
docs/FEATURES_INDEX.md:](./features/product-search.md)      → exists
product-search.md → ../development/known-issues.md         → exists
```

### 9. Update `ProductTest` — done (2 pre-existing failures remain)
Changed: `tests/Feature/ProductTest.php` — `setUp()`/`tearDown()` use
`CreatesProductSearchPosTables` (all eight POS tables), `stocking` rows for
CODE001 and CODE003; `test_can_search_products_by_name` → `?q=Kitchen`;
`test_can_filter_active_products_only` replaced by
`test_unstocked_products_hidden_by_default_and_shown_with_toggle`;
`test_legacy_search_param_still_works` added.
Baseline for this file (before any of my changes): **5 failed, 2 passed** —
every page-rendering test died with `no such table: suppliers`, plus the two below.
Check output:
```
  ✓ products page requires authentication
  ✓ authenticated user can view products list
  ✓ can search products by name
  ✓ legacy search param still works
  ✓ unstocked products hidden by default and shown with toggle
  ✓ can view product details
  ⨯ shows 404 for non existent product          (pre-existing: show() redirects to edit without checking the product exists → 302)
  ⨯ product statistics are displayed            (pre-existing: statistics only render with ?show_stats=1, and the label is "Active (Non-Service)" not "Active Products")
  Tests: 2 failed, 6 passed (24 assertions)
```
Both remaining failures are untouched per "Leave the other tests untouched".
See Deviations #7 for the one assertion I did change.

### 10. Guard the missing website template — done (revision 2)
Changed: `app/Services/SupplierService.php` (`getSupplierWebsiteLink`: early
`return null` when `$config['website_search']` is empty),
`tests/Concerns/CreatesProductSearchPosTables.php` (supplier 65 "Natural
Medicine", link `NM-1` on P5), `tests/Feature/ProductSearchApiTest.php`
(three assertions on P5 in `test_response_carries_supplier_price_and_stock_details`:
supplier name, code, `website_url === null`).
Check output:
```
$ php artisan tinker --execute='app(...ProductSearchService::class)->search(new ...Criteria(q: ""));'
(no output — no DEPRECATED line)

$ php artisan test --filter=ProductSearchApiTest
Tests: 11 passed (73 assertions)
```

### 11. Forget the vocabulary on inline rename — done (revision 2)
Changed: `app/Http/Controllers/ProductController.php` (`updateName`, after the `update()`).
Check output:
```
$ grep -n "ProductSearchVocabulary::class)->forget()" app/Http/Controllers/ProductController.php
206:        app(ProductSearchVocabulary::class)->forget();     (updateName)
1403:            app(ProductSearchVocabulary::class)->forget();  (store)
1638:            app(ProductSearchVocabulary::class)->forget();  (update)
```

### 12. Complete `implemented.md` — done (revision 2)
Changed: this file. Root cause of the gap: my Step 2 edit replaced the
`## Deviations` heading with the step text and did not re-add it, so every
later "insert before `## Deviations`" was a silent no-op (the deviation
entries themselves survived because they were appended after existing text).
Steps 3–9 above are reconstructed from the checks I ran and recorded during
revision 1 (same numbers as reported in the session).
Check output:
```
$ grep -c "^### " docs/planImp/implemented.md   → 12
$ grep -n "^## Deviations" docs/planImp/implemented.md → found
```

## Deviations
1. **Step 1, distance for transpositions.** The plan's rules say "accept
   distance ≤1 for tokens of 4–6 chars", but its check requires
   `correct('friut') === 'fruit'`, and plain `levenshtein('friut','fruit')` is 2
   (the plan's own Context notes d=2). Rather than loosen the threshold for
   short tokens (which would over-correct 4-letter words), an adjacent
   transposition is counted as one edit (`ProductSearchVocabulary::distance()`,
   Damerau-style, using native `levenshtein()` plus a cheap swap check so the
   ~1 ms/token scan is kept). All other rules unchanged.
2. **Step 2f, pagination.** Plan says `->paginate()` "is fine". Measured: the
   COUNT is ~29 ms, so a typo search (empty pass + corrected pass) cost
   ~128 ms warm, over the 100 ms check. `runQuery()` now fetches the page
   first and only runs `getCountForPagination()` when page 1 came back full
   (otherwise total = row count). Same `meta` (total/last_page verified on
   "milk" page 2), same two-phase eager loads. Typo search dropped to ~90 ms.
3. **Step 2h, Independent image URLs.** `getExternalImageUrlBySupplierCode()`
   runs its own `supplier_image_cache` query per call, which would be one
   query per row. The service instead reads the batched cache rows directly
   (`not_found` → null, else `image_url`) and builds the template URL from
   `config('suppliers.external_links')` for codes with no cache row. Same
   output as the SupplierService method, one query per page.
4. **Step 5/7, camera scan result.** Step 7 says "on detection navigate to
   `/products?q=<barcode>`". The component owns the camera now, so a scan sets
   the input to the barcode and runs the search in place; with `syncUrl` the
   address bar becomes `/products?q=<barcode>` without a reload (picker mode
   additionally selects the top match, like the Enter-from-scanner flow). No
   page navigation, same end state.
5. **Step 5, "search instead for *original*".** The endpoint has no
   "don't correct" flag (the plan's param list is canonical), so re-running
   the original would just be corrected again. The link therefore shows the
   original query's real result client-side: the empty "No products found"
   state with the corrected banner cleared. Noted under Notes for Planner as a
   possible `exact=1` follow-up.
6. **Step 7, dropdown loaders.** Plan: "pass `stockedOnly: $stocked` and
   nothing else to the existing repository methods". Measured on live POS:
   `getAllSuppliersWithProducts(stockedOnly: true)` = 133 ms and
   `getAllCategoriesWithProducts(stockedOnly: true)` = 397 ms (both are nested
   `WHERE EXISTS` against `stocking`, the collation trap) versus 51 ms + 2 ms
   unfiltered. That would add ~0.5 s to every `/products` load. The controller
   calls both unfiltered, which is exactly what the old default `/products`
   did (no filters → unfiltered dropdowns). Only effect: the dropdowns list
   60 suppliers / 81 categories instead of 53 / 80.
7. **Step 9, `test_authenticated_user_can_view_products_list`.** It asserted
   `assertSee('Test Service Product')` on the default `/products`, but that
   product is deliberately left unstocked by the plan's seeding instruction
   and the plan's decision is "stocked products by default". The test could
   not pass as written without contradicting the plan, so that one line became
   `assertDontSee('Test Service Product')` with a comment. Please confirm; if
   you would rather the service product be stocked in the fixture, the toggle
   test needs a different unstocked product.


## Verification

### Revision 1
1. `./vendor/bin/pint --test <all changed PHP files>` →
   ```
   PASS .......................................................... 11 files
   ```
2. `php artisan test --filter='ProductSearchVocabularyTest|ProductSearchApiTest|ProductTest'` →
   ```
   PASS  Tests\Unit\ProductSearchVocabularyTest   (3)
   PASS  Tests\Feature\ProductSearchApiTest       (11)
   FAIL  Tests\Feature\ProductTest                (6 pass, 2 fail — both pre-existing, see Step 9)
   Tests: 2 failed, 20 passed (107 assertions)
   ```
3. `php artisan test` → `Tests: 17 failed, 385 passed (1536 assertions), 29.11s`.
   No new failures. The 17 are:
   - `ProductTest` × 2 — pre-existing (Step 9).
   - `Unit\UdeaScrapingServiceTest` × 7 — `BadMethodCallException` / `InvalidCountException` (mocking).
   - `CashReconciliationTest` × 3 — `BadMethodCallException`.
   - `FruitVegLabelPrintingTest` × 2 — its fixture has no `CATEGORIES` table (`no such table: CATEGORIES`), and a count assertion that depends on it.
   - `TestScraperControllerTest` × 1.
   - `WasteLogTest` × 2 — SQLite unique-constraint violation in its own fixture.
   None of these touch the product search code, `/products`, or the POS tables I create.
4. `php artisan route:list --path=products` → lists `api.products.search`
   (`Api\ProductSearchController`) and `products.search-test`
   (`ProductSearchTestController`). `route:list` sorts alphabetically; in
   `routes/web.php` `products.search-test` is registered on line 95, before
   `/products/{id}` on line 97, and `GET /products/search-test` returns 200
   from the test controller (not `products.show`).
5. Test page timings — see Step 6 (all < 100 ms except `a` = 127.9 ms, within the 300 ms allowance).
6. `/products` live checks — see Step 7 (default page, `?q=`, legacy `?search=`,
   Next/Prev, inline stock edit + toast, sales chart modal, camera modal all verified in Chrome).
7. After a typo search over HTTP: `php artisan tinker --execute='dump(Cache::get("product-search:vocab") !== null)'` → `true`.

### Revision 2
1. `./vendor/bin/pint --test app/Services/SupplierService.php app/Http/Controllers/ProductController.php tests/Feature/ProductSearchApiTest.php tests/Concerns/CreatesProductSearchPosTables.php` →
   ```
   PASS ........................................................... 4 files
   ```
2. `php artisan test --filter='ProductSearchVocabularyTest|ProductSearchApiTest|ProductTest'` →
   ```
   ⨯ shows 404 for non existent product        (pre-existing)
   ⨯ product statistics are displayed          (pre-existing)
   Tests: 2 failed, 20 passed (110 assertions)
   ```
3. Tinker command from Step 10 → prints nothing.

## Files changed

`git status --short` at the end. Lines marked `[pre-existing]` were dirty before I started (customer-requests / auth work from another session) and are untouched by me; `docs/planImp/plan.md` is the Planner's.

```
 M CLAUDE.md
 M app/Http/Controllers/Auth/AuthenticatedSessionController.php   [pre-existing]
 M app/Http/Controllers/CustomerRequestController.php             [pre-existing]
 M app/Http/Controllers/ProductController.php
 M app/Services/SupplierService.php                                (rev 2)
 M docs/FEATURES_INDEX.md
 M docs/development/known-issues.md
 M docs/planImp/implemented.md
 M docs/planImp/plan.md                                           [Planner's]
 M resources/views/customer-requests/_form.blade.php              [pre-existing]
D  resources/views/customer-requests/create.blade.php             [pre-existing]
 M resources/views/customer-requests/edit.blade.php               [pre-existing]
 M resources/views/customer-requests/index.blade.php              [pre-existing]
 M resources/views/customer-requests/show.blade.php               [pre-existing]
 M resources/views/layouts/board.blade.php                        [pre-existing]
 M resources/views/products/index.blade.php
 M routes/web.php
 M tests/Feature/Auth/AuthenticationTest.php                      [pre-existing]
 M tests/Feature/CustomerRequestTest.php                          [pre-existing]
 M tests/Feature/ProductTest.php
?? app/Http/Controllers/Api/ProductSearchController.php
?? app/Http/Controllers/ProductSearchTestController.php
?? app/Services/ProductSearch/ProductSearchCriteria.php
?? app/Services/ProductSearch/ProductSearchService.php
?? app/Services/ProductSearch/ProductSearchVocabulary.php
?? docs/features/product-search.md
?? docs/planImp/archive/                                          [pre-existing]
?? resources/views/components/product-search.blade.php
?? resources/views/products/search-test.blade.php
?? tests/Concerns/CreatesProductSearchPosTables.php
?? tests/Feature/ProductSearchApiTest.php
?? tests/Unit/ProductSearchVocabularyTest.php
```

Nothing committed, pushed or deployed. The one live-data side effect: the
inline-stock-edit check re-saved " cake" (CODE 4112) with its existing value
0 via `/products/{id}/update-stock`, which is a no-op for STOCKCURRENT.

## Notes for Planner

- (Resolved in revision 2: `updateName` now calls `forget()`; the
  `website_search` null deprecation is guarded.)
- **"Search instead for *original*"** would be a proper feature with an
  `exact=1` (skip correction) param on the endpoint; currently it just shows
  the honest empty result (Deviation #5).
- **The `stocked=0` search is faster than the stocked one** (48 ms vs 77 ms
  for "milk") because the unfiltered PRODUCTS scan is 11 ms vs the 30 ms
  `JOIN stocking`. STRAIGHT_JOIN and `IN (subquery)` variants were measured and
  are not better (185 ms / 33 ms). A Laravel-side cache of the ~5k stocking
  barcodes would not help either (`whereIn` of 5,264 values was 60 ms).
- **Box load was ~9** during all measurements (the other session and the
  test-suite runs), so the `took_ms` figures have maybe ±20 ms noise; the
  quiet-box numbers are at the low end of each range.
- **`vat_label` is `"23.0%"`** (existing accessor), not `"23%"` as in the
  plan's example JSON.
- **Pre-existing `ProductTest` failures** (404 for a missing product, and the
  statistics test) are real gaps: `ProductController@show` never checks the
  product exists, and the statistics test predates the `show_stats` toggle.
  Both are one-line fixes for a future plan.
- **Old page's `Product::scopeSearch` / `ProductRepository::searchProducts`
  callers**: `/products` no longer calls `searchProducts()` or
  `getAllProducts()`; they remain for the other pages listed in
  `docs/features/product-search.md`.
- **Independent thumbnails**: as on the old page, uncached supplier codes get
  a template URL that often 404s and falls back to the placeholder. A
  follow-up could queue `resolveAndCacheImageUrl()` for codes seen in search
  results (never inline).
