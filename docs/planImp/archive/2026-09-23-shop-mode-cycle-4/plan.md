# Shop mode cycle 4 — Find product (search and stock check)

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-23

## Goal

Give staff a fast way to answer "do you have X?" at the counter: a Shop mode screen where they type part of a product name (or scan a barcode), see matching products with price and stock at a glance, and tap one for the details. Read-only by design: no create, no edit, no stock change. It reuses the canonical product search endpoint unchanged and takes its detail card from the design's Price check screen (03), so that screen is delivered as part of this one rather than separately. A new "Find product" tile appears on Home for anyone with `products.view`, which every employee holds.

## Context

Baseline: cycle 3 accepted and archived (`docs/planImp/archive/2026-09-23-shop-mode-cycle-3/`); its files are uncommitted in the working tree. Treat the current tree as the baseline and record `git status --short` at the start.

**Owner intent (2026-09-23).** "I would like our employees to use this page more often to search for products if customers ask and they want to see if we have any in stock. No need for the link to create new product for employees, no need to edit details or stock from here." The office page `/products` stays for managers; this screen is the employee's version.

**Endpoint to reuse, unchanged.** `GET /api/products/search` (`api.products.search`, `app/Http/Controllers/Api/ProductSearchController.php`), inside the `auth` group in `routes/web.php` and behind `permission:products.view` since cycle 2, so the browser session authenticates it. Documented in `docs/features/product-search.md` ("Endpoint" section). Params: `q` (≤100 chars), `stocked` (`1` default; `0` includes products outside the stocking range), `page`, `per_page` (max 50). Response `data[]` rows carry `id`, `code`, `name`, `category_name`, `price_with_vat`, `vat_label`, `stock_units` (float), `is_stocked`, `is_service`, `image_url`, `supplier { id, name, code }`, `edit_url`, `match_rank`; `meta` carries `query`, `corrected_query`, `total`, `page`, `per_page`, `last_page`, `stocked`. Matching handles words in any order, code prefix/suffix and supplier codes, and a full barcode is simply a long digit token, so a USB scanner typing a code and Enter into the box finds the product. The service is fast (single character ~130 ms, browse ~70 ms) and the doc's performance rules mean the query must not be modified here.

**Design.** There is no dedicated screen for search; compose it from the system, all classes present in `resources/css/shop.css`:
- `shop-search` (search icon + `shop-input type="search"`, as on screen 12) for the query.
- `shop-seg shop-seg--block` with two radio `shop-seg__opt` for "Our range" / "All products" (the `stocked` flag).
- `shop-list` of `shop-row` (`__main` with `__title`/`__meta`, `__aside` with `__qty` and a `shop-pill`, a `__chev` chevron; `is-off` on out-of-stock rows) for results, as on screens 04/05.
- The detail card from screen 03 (`docs/design/shop-mode/screen-03-price-check.html`): `shop-card` with `shop-label` "Shelf price", `shop-subtitle` name, `shop-bignum` with `shop-bignum__cur` "€", a `shop-inline` of pills, then a second block with `shop-facts shop-facts--2` of `shop-fact` (`shop-label` + `shop-fact__value`). Screen 03's action bar (Add to queue / Print shelf label) is **not** included (owner: read-only).
- `shop-empty` for "no results", `shop-btn shop-btn--secondary shop-btn--block` for "Show more".
Layout: single column, `shop-page shop-page--narrow` (680 px), in this order: search box, range toggle, the detail card when a product is selected (with a ghost close button), results list, Show more. One column everywhere avoids any width logic in JavaScript and keeps the selected product in view on a phone.

**Cycle 1–3 pieces to reuse.** `ShopLayout` (`title`, `back`), `x-shop.icon` (sprite ids include `search`, `x`, `chevron-right`, `tag`, `package`), `resources/js/shop.js` index registering data objects inside `alpine:init`, the data-attribute pattern for URLs, the csrf meta tag in the layout, `config/shop.php` tiles, `ShopViewContractTest` (no `<script>`, `<style>` or utility classes in `resources/views/shop/**`, including inside `:class` bindings; use `x-show`/`x-cloak`). The scan-input component is **not** used here: this box must open the on-screen keyboard because staff type names; a USB scanner still works because it types into whatever input is focused.

**Tests.** `tests/Concerns/CreatesProductSearchPosTables.php` provides `createProductSearchPosTables()`, `seedProductSearchFixture()` (returns ids `P1`…`P7`; `P1` is "Chocolatemakers forest fruit milk chocolate 100 gram", code `8721325594341`, stocked, supplier Udea code `6001397`; `P3` is an unstocked "milk" product) and `dropProductSearchPosTables()`; `tests/Feature/ProductSearchApiTest.php` shows the usage (`setUp` creates and seeds, `tearDown` drops, `Cache::forget(ProductSearchVocabulary::CACHE_KEY)`). Role users: `User::factory()->withRole('employee')` plus `Permission::firstOrCreate` and `givePermissionTo`, as in `tests/Feature/Shop/ShopStockScanTest.php::userWith()`. Full-suite baseline: 17 failed / 492 passed, always the same 17 (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `WasteLogTest` ×2, `ProductTest` ×2, `FruitVegLabelPrintingTest` ×2, `TestScraperControllerTest` ×1).

## Constraints

- Do not commit, push or deploy.
- Read-only screen: no link or button to create, edit, adjust stock, print or queue labels. `edit_url` from the API is ignored.
- Design/content contract as in cycle 3: view = `x-shop.*` + `shop-*` + Blade + Alpine directives + data; behaviour in `resources/js/shop/find-product.js`; URLs via `data-*`; no Blade in JS; `resources/css/shop.css` not edited.
- `ProductSearchController`, `ProductSearchService`, `ProductSearchCriteria`, the `x-product-search` component and `resources/views/products/**` are not modified.
- Copy: prices shown as `€` + two decimals; stock shown in whole units (`Math.floor`) with the word "in stock"; never show "0.5".
- Keep the request-counter guard from the office component: a slow earlier response must never overwrite a newer one.

## Out of scope

- Camera scanning on this screen (staff with the tablet can use the Stock scan screen's camera; a camera button here can be a follow-up reusing the `../barcode-scanner` chunk).
- Product images and thumbnails (`image_url`); the row is text only in v1.
- Print / Add to label queue (screen 03's action bar). If the owner wants it later it is one button posting to `labels.scan`.
- Filtering by category or supplier, sorting, and the office page's hover preview.
- Any change to the office `/products` page.
- Deliveries, labels, requests, vouchers, F&V, PIN (later cycles).

## Steps

### 1. Route and controller
Files: `app/Http/Controllers/Shop/FindProductController.php (new)`, `routes/web.php`
What: `FindProductController@index` returns `view('shop.find-product')`. In the `shop` prefix group add `Route::get('/find', [\App\Http\Controllers\Shop\FindProductController::class, 'index'])->middleware('permission:products.view')->name('find-product');`.
Check: `php artisan route:list --name=shop.find-product` → `GET shop/find` with the `products.view` permission middleware.

### 2. Home tile
Files: `config/shop.php`
What: insert after the `stock-scan` entry: `['key' => 'find-product', 'label' => 'Find product', 'hint' => 'Search and check stock', 'icon' => 'search', 'route' => 'shop.find-product', 'permissions' => ['products.view'], 'badge' => null],`.
Check: `php artisan tinker --execute="echo count(config('shop.tiles'));"` → `8`; `php artisan test --filter=ShopHomeTest` green (the employee fixture there holds `stocking.scan`, `deliveries.process`, `customer-requests.manage`; it must not see "Find product"; if a `products.view`-only assertion in `ShopStockScanTest::home_tile_is_hidden_without_the_stocking_permission` starts seeing "Find product", that is correct behaviour and the assertion about "Stock scan" still holds).

### 3. Behaviour: `find-product.js`
Files: `resources/js/shop/find-product.js (new)`, `resources/js/shop.js`
What: export a data object registered as `Alpine.data('shopFindProduct', …)` from the `alpine:init` listener in `shop.js`. State: `q = ''`, `stocked = true`, `results = []`, `meta = null`, `page = 1`, `loading = false`, `selected = null`, `seq = 0`, `timer = null`. Getters: `searchUrl` (`this.$root.dataset.searchUrl`), `hasMore` (`meta && meta.page < meta.last_page`), `correctedQuery` (`meta?.corrected_query`), `isBarcodeQuery` (`/^\d{8,}$/.test(q.trim())`). Methods:
- `init()`: focus the search input (`this.$refs.input`).
- `onInput()`: `clearTimeout(timer)`; if `q.trim().length < 2` → `results = []; meta = null; selected = null; return`; else `timer = setTimeout(() => this.search(), 300)`.
- `onEnter()`: `clearTimeout(timer)`; `search({ autoSelect: true })`.
- `setStocked(value)`: set, then `search()` if `q` has ≥ 2 chars.
- `search({ append = false, autoSelect = false } = {})`: `const mine = ++this.seq`; `loading = true`; `page = append ? page + 1 : 1`; build the URL with `URLSearchParams({ q, stocked: stocked ? 1 : 0, page, per_page: 20 })`; `fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })`; ignore the response if `mine !== this.seq`; on success `results = append ? [...results, ...data.data] : data.data`, `meta = data.meta`; when not appending, `selected = null`, except: if `autoSelect && isBarcodeQuery && results.length === 1` then `select(results[0])`; on error `results = []`, `meta = null`, `error = 'Search failed'` (clear `error` on the next successful search); finally `loading = false` (only if `mine === this.seq`).
- `more()`: `search({ append: true })`.
- `select(p)`: `selected = p`; `window.scrollTo({ top: 0 })` is not needed because the card renders above the list, but call `this.$refs.card?.scrollIntoView({ block: 'nearest' })` after `$nextTick`.
- `close()`: `selected = null`; focus the input.
- Formatting helpers: `price(p)` → `'€' + Number(p.price_with_vat).toFixed(2)`; `units(p)` → `Math.floor(Number(p.stock_units))`; `stockLabel(p)` → `units(p) > 0 ? units(p) + ' in stock' : 'Out of stock'`; `stockTone(p)` → `units(p) > 0 ? 'shop-pill--ok' : 'shop-pill--bad'`; `rowMeta(p)` → `[p.category_name, p.code].filter(Boolean).join(' · ')`; `supplierLabel(p)` → `p.supplier ? (p.supplier.code ? p.supplier.name + ' · ' + p.supplier.code : p.supplier.name) : '—'`.
Check: `npm run build` succeeds; `grep -c "route(" resources/js/shop/find-product.js` → 0; `node -e "import('./resources/js/shop/find-product.js').then(m => { const d = m.default(); console.log(Object.keys(d).length > 10, d.price({price_with_vat: 3.4}), d.stockLabel({stock_units: 7.5}), d.stockLabel({stock_units: 0})) })"` prints `true €3.40 7 in stock Out of stock`.

### 4. The screen
Files: `resources/views/shop/find-product.blade.php (new)`
What: `<x-shop-layout title="Find product" :back="route('shop.home')">` → `<main class="shop-page shop-page--narrow" x-data="shopFindProduct()" data-search-url="{{ route('api.products.search') }}">` containing, in order:
1. Search: `<div class="shop-search"><x-shop.icon name="search" /><input class="shop-input" type="search" x-ref="input" x-model="q" @input="onInput()" @keydown.enter.prevent="onEnter()" placeholder="Search by name, or scan a barcode" autocomplete="off" enterkeyhint="search" aria-label="Search products"></div>`.
2. Range toggle: `<div class="shop-seg shop-seg--block" role="radiogroup" aria-label="Range"><label class="shop-seg__opt"><input type="radio" name="range" value="1" :checked="stocked" @change="setStocked(true)">Our range</label><label class="shop-seg__opt"><input type="radio" name="range" value="0" :checked="! stocked" @change="setStocked(false)">All products</label></div>`.
3. Corrected query note: `<p class="shop-meta" x-show="correctedQuery" x-cloak>Showing results for <strong x-text="correctedQuery"></strong></p>`.
4. Detail card, `x-show="selected" x-cloak x-ref="card"`, a `<section class="shop-card">`: top `shop-between` with `<span class="shop-label">Shelf price</span>` and `<button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Close" @click="close()"><x-shop.icon name="x" /></button>`; `<h2 class="shop-subtitle" x-text="selected?.name">`; `<span class="shop-bignum"><span class="shop-bignum__cur">€</span><span x-text="selected ? Number(selected.price_with_vat).toFixed(2) : ''"></span></span>`; `<div class="shop-inline"><span class="shop-pill" :class="selected && stockTone(selected)" x-text="selected && stockLabel(selected)"></span><span class="shop-pill shop-pill--muted" x-show="selected && ! selected.is_stocked">Not in our range</span></div>`; then `<div class="shop-facts shop-facts--2">` with four `shop-fact`s: Stock (`units(selected) + ' units'`), Category (`selected?.category_name || '—'`), Supplier (`supplierLabel(selected)`), Barcode (`shop-fact__value shop-code`, `selected?.code`), and VAT (`selected?.vat_label || '—'`).
5. Results: `<section class="shop-stack shop-stack--tight" x-show="results.length" x-cloak>` with `<div class="shop-between"><h2 class="shop-label">Results</h2><span class="shop-meta" x-text="meta ? meta.total + ' found' : ''"></span></div>` and `<div class="shop-list"><template x-for="p in results" :key="p.id"><button class="shop-row" type="button" :class="{ 'is-off': units(p) === 0 }" @click="select(p)"><div class="shop-row__main"><span class="shop-row__title" x-text="p.name"></span><span class="shop-row__meta shop-code" x-text="rowMeta(p)"></span></div><div class="shop-row__aside"><span class="shop-row__qty" x-text="price(p)"></span><span class="shop-pill" :class="stockTone(p)" x-text="stockLabel(p)"></span></div><x-shop.icon name="chevron-right" class="shop-row__chev" /></button></template></div>`, then `<button class="shop-btn shop-btn--secondary shop-btn--block" type="button" x-show="hasMore" x-cloak :disabled="loading" @click="more()">Show more</button>`.
6. Empty states, each `x-cloak`: before typing (`q.trim().length < 2`): `shop-empty` with the `search` icon (xl), title "Find a product", text "Type part of the name, or scan a barcode."; after a search with no results (`! loading && meta && results.length === 0`): `shop-empty` with the `package` icon, title "Nothing matches", text "Try fewer words, or switch to All products."; on `error`: `shop-empty` with the `alert` icon and the message.
`.shop-row` is styled for `button` as well as `a` (`.shop button.shop-row { width:100%; text-align:left }`), so rows are buttons, which also gives keyboard access.
Check: `php artisan test --filter=ShopViewContractTest` → three screens, green; `php artisan view:cache` succeeds.

### 5. Tests
Files: `tests/Feature/Shop/ShopFindProductTest.php (new)`
What (`RefreshDatabase` + `CreatesProductSearchPosTables`, set up and torn down as in `ProductSearchApiTest`):
- `employee_can_open_the_find_product_screen`: employee with `products.view` → 200, contains `data-shell="shop"`, `data-search-url="` + `route('api.products.search')`, `type="search"`, "Our range", "All products", a Back link to `route('shop.home')`, and does **not** contain `products.create`, `products/create`, `edit_url` or the word "Edit".
- `barista_is_forbidden`: → 403. `guest_is_sent_to_login`: → redirect `/login`.
- `home_shows_the_find_product_tile_to_product_viewers`: employee with `products.view` sees "Find product" and `href="` + `route('shop.find-product')`; a barista with `kds.access` does not.
- `employee_can_search_the_product_api`: employee with `products.view` → `GET route('api.products.search', ['q' => 'chocolatemakers fruit'])` → 200, `data.0.id === $ids['P1']`, `data.0.stock_units` present, `meta.stocked === true`.
- `employee_without_products_view_cannot_search`: employee with only `stocking.scan` → 403 on the same request.
Check: `php artisan test --filter="ShopFindProductTest|ShopHomeTest|ShopStockScanTest|ShopViewContractTest"` green.

### 6. Record the scan-input component API (cycle 3 follow-up)
Files: `docs/design/shop-mode/README.md`
What: append a section "Component API in the app" listing `x-shop.scan-input` (props `placeholder`, `hint`, `camera`; emits `scan` `{ code }` on its root; listens on `window` for `shop-scan-done`, `shop-scan-error` (detail = message), `shop-scan-saved`) and the rule that page behaviour lives in `resources/js/shop/*.js` and takes its URLs from `data-*` attributes. Five to ten lines; no other changes to the file.
Check: `grep -c "shop-scan-done" docs/design/shop-mode/README.md` → 1.

### 7. Build and format
Files: all touched
What: `npm run build`; `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean; `public/build/manifest.json` lists `resources/js/shop.js`.

## Verification

1. `php artisan route:list --name=shop.` → `shop.home`, `shop.stock-scan`, `shop.find-product`.
2. `php artisan test --filter=Shop` → all green; the contract test lists three screens.
3. `php artisan test` → 17 failed / N passed, the identical 17 named in Context.
4. `grep -rn "<script\|<style" resources/views/shop/` → nothing; `grep -rn "edit_url\|products.create\|products.edit\|labels.scan" resources/views/shop/find-product.blade.php resources/js/shop/find-product.js` → nothing.
5. `git diff --stat app/Http/Controllers/Api/ app/Services/ProductSearch/ resources/views/products/ resources/views/components/product-search.blade.php resources/css/shop.css` → empty.
6. `./vendor/bin/pint --test --dirty` → clean; `npm run build` → success.
7. Manual, signed in as an employee:
   - Till PC: open Home → "Find product"; type "oat" → results appear after a short pause with price and a green "N in stock" or red "Out of stock" pill; tap a row → the detail card appears above the list with the big price and the facts; the close button hides it. Scan a product with the USB scanner into the box → the single result opens automatically.
   - Tablet/phone: the on-screen keyboard opens when the box is tapped; "All products" reveals unstocked items with the grey "Not in our range" pill on the detail; "Show more" appends the next page; single column throughout.
   - Manager/admin: the screen works the same; the office `/products` page is unchanged.
   - Type a misspelling the vocabulary can correct (for example "chocolat makers") and see "Showing results for …".

## Risks

- **Search-as-you-type load.** 300 ms debounce and a 2-character minimum keep this to a handful of requests per query; the endpoint is measured at ~130 ms worst case. The sequence guard prevents out-of-order responses from overwriting results.
- **The API is the office component's contract.** Do not add fields or parameters; if something is missing, note it for the Planner.
- **Keyboard on touch devices.** This box deliberately opens the keyboard (no `inputmode="none"`). A tablet user who only scans will see the keyboard pop; that is acceptable for a search screen and the reason the scan-input component is not reused.
- **Decimal stock** shows floored ("2 in stock" for 2.5). Weighed goods may look one unit low; unchanged from how the office page rounds.
- **Contract test and bindings.** As in cycle 3: only `shop-*`/`is-*` names inside `:class`; use `x-show`, never a `hidden` class.
- **`x-for` on a `<button>`** requires the `<template>` to have a single root, which it does.

## Review

Reviewed 2026-09-23 by the Planner against `implemented.md`, the new files and diffs, a rerun of the tests, and a live walkthrough in a signed-in browser.

Criteria:
1. Route and controller — PASS (`shop.find-product`, `products.view`).
2. Home tile — PASS (8 tiles; existing Shop tests unchanged and green).
3. Behaviour — PASS. Debounce, 2-character minimum, sequence guard on success and failure paths, barcode auto-select, URLs from data attributes, no Blade in JS.
4. Screen — PASS. Search box, range toggle, detail card from screen 03 without its action bar, results as button rows, three empty states; contract test scans three screens.
5. Tests — PASS. Six tests including the permission-denied search case.
6. README component API — PASS.
7. Build and format — PASS.

Verification rerun by the Planner: `--filter=Shop` 99 passed; full suite 17 failed / 499 passed, the same 17; formatter clean; the search stack, office component and `shop.css` untouched. Live check in Chrome as a signed-in user: typing "oat" returned 95 results with prices and green "N in stock" / red "Out of stock" pills; tapping a row opened the detail card with the big price, stock, category, supplier code, barcode and VAT; the close button works. Read-only confirmed: no create, edit or label control anywhere on the page.

Deviations 1–5: all accepted; 2 and 3 tighten the plan's own wording (Enter below the minimum, stale-guard on the failure path).

Notes for Planner: no loading indicator (accepted for now; ~130 ms on the LAN); five facts leave one odd cell (accepted, VAT stays); verification greps should be scoped to code, not comments (taken for future plans); `is-off` greying only the title (accepted, matches the design).

Result: ACCEPTED. Archived by the Planner to `docs/planImp/archive/2026-09-23-shop-mode-cycle-4/`. Next: cycle 5, product images on this screen (owner request 2026-09-23: "we do need images where they are available").
