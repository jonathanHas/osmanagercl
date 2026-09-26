# Shop mode cycle 21 — Product pictures on the customer requests screens

Status: READY
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

A pre-order line is a stocked product, and the product search already shows its picture when staff pick it. Once saved, the board, the detail page and the edit page show only the name. This cycle shows the picture wherever a pre-order line is rendered: the staff board row, the guest card, the detail page's item list, and the edit page's seeded lines. Sourcing lines (free text) keep no picture. The picture is resolved exactly as the product search resolves it (POS photo first, supplier CDN second), through one shared method rather than a second implementation.

## Context

- **Resolution today** lives in `app/Services/ProductSearch/ProductSearchService.php`: the search query adds `has_image` (`CASE WHEN PRODUCTS.IMAGE IS NOT NULL AND LENGTH(PRODUCTS.IMAGE) > 0`) and selects `SELECT_COLUMNS` (never `PRODUCTS.*`, the blob is a mediumblob); `loadSupplierImageCache(array $products)` batches `SupplierImageCache` rows for products whose supplier uses supplier-code images; `imageUrl(Product, $cache)` returns `route('products.image', $product->ID)` when `has_image`, else the supplier CDN URL by barcode or by cached/template supplier code, else null; helpers `externalSupplierId()`, `imageTemplate()`. All private; constructor takes `SupplierService` and `ProductSearchVocabulary`. Products are loaded with `supplierLink`.
- **Where lines render**: `resources/views/shop/partials/request-row.blade.php` (staff row: `shop-req__main` holds `h3.shop-req__title` then `shop-req__meta`), `resources/views/shop/partials/request-card.blade.php` (guest card: `shop-request__head` → `shop-stack--tight` with `shop-request__item` and `__who`), `resources/views/shop/request-show.blade.php` (Items: `shop-row` with `shop-row__main` and an aside pill), `resources/views/shop/request-edit.blade.php` (Alpine lines; the thumbnail already renders when `item.product` is set, cycle 14, and the sprout/package placeholders otherwise, cycle 14b). Data: `CustomerRequestService::boardRows($view)` → `['due' => rows, 'open' => rows, 'done' => rows, 'counts']` with `row = ['item', 'request']`; `CustomerRequestController::show()` loads the request; `seedItems()` builds `{ id, product_code, product_name, description, quantity, notes, status }` from old input or the model.
- **Shop pieces**: `x-shop.product-thumb` is for Alpine scopes (`hasImage(expr)`); the board and detail pages are server-rendered with no product object in scope, so a server-side sibling is needed. `.shop-thumb` (48 px) and the `shop-row__lead` placeholder circle exist. Icons: `package`.
- **Access to the image URL**: `products.image` is an office route; check its middleware. Guests on the public board have no session, so a POS-photo URL will redirect to login and the `<img>` errors; the placeholder must take over cleanly. Supplier CDN URLs work for guests. This is acceptable for v1 and is stated in Out of scope.
- Tests: `tests/Feature/Shop/ShopRequestsTest.php` (POS fixture `PRODUCTS` with `REFERENCE`, `supplier_link`; no `IMAGE` column yet), `ShopFruitVegTest` shows adding a nullable `IMAGE` column. `SupplierImageCache` lives on the default DB (migrated). Suite baseline: 15 failed / 640 passed.

## Constraints

- Do not commit, push or deploy.
- One resolver: the new public method delegates to the existing private ones; `imageUrl()`'s rules do not change, so the search API's results are unaffected.
- No blob in any payload; `has_image` stays a computed column.
- Contract rules; design block byte-identical; app rules only if a new size is needed (none expected: 48 px rows, and the guest card can use 48 px too).

## Out of scope

- Serving POS photos to guests (the public board shows CDN pictures and placeholders for POS-photo products until the image route is made guest-readable, which is a separate decision).
- Thumbnail sizing on `products.image` (the F&V thumbnail work is on a different route).
- Pictures for sourcing lines.

## Steps

### 1. `imageUrlsByCode()` on the search service
Files: `app/Services/ProductSearch/ProductSearchService.php`
What: `public function imageUrlsByCode(array $codes): array` → `[]` for an empty list; else `Product::query()->select(self::SELECT_COLUMNS)->addSelect(<the same has_image raw>)->whereIn('CODE', array_values(array_unique($codes)))->with('supplierLink')->get()`, then `$cache = $this->loadSupplierImageCache($products->all())` and return `[CODE => $this->imageUrl($product, $cache)]` for each (null when no picture). Extract the `has_image` raw expression into a private constant or method so `runQuery()` and this share it.
Check: unit/feature test `tests/Feature/ProductSearchImageUrlsTest.php (new)` with the POS fixture used by `ShopRequestsTest` plus an `IMAGE` column: a product with a blob → `route('products.image', $id)`; a product without a blob and no supplier link → null; a product linked to a supplier with a template image URL configured (`config(['suppliers.external_links' => …])` with `enabled`, `image_url` containing `{SUPPLIER_CODE}`, and `SupplierService::usesSupplierCodeImages` true for it) → the template URL with the code substituted; an unknown code → absent from the array; the empty list → `[]` without a query (assert with `DB::enableQueryLog()`).

### 2. Board rows and the detail page carry the URL
Files: `app/Services/CustomerRequestService.php`, `app/Http/Controllers/CustomerRequestController.php`
What: `boardRows()` collects the distinct `product_code`s of every row it is about to return, calls `imageUrlsByCode()` once, and adds `'image_url' => $urls[$code] ?? null` to each row (null for sourcing lines). Inject `ProductSearchService` into `CustomerRequestService` (constructor). `show()` passes `'images' => $searchService->imageUrlsByCode($customerRequest->items->pluck('product_code')->filter()->all())`. `seedItems()` adds `'image_url'` to each line the same way (one call for the request's codes, or the old-input codes), and `edit()`'s view gets lines whose `product` object the Alpine module expects: in `seedItems()` set `'product' => $code ? ['id' => $code, 'image_url' => $urls[$code] ?? null] : null` so `withKey()` in `request-edit.js` picks it up unchanged (it already does `product: line.product ?? null`).
Check: `ShopRequestsTest` (step 4) pins the rows; `php artisan test --filter=CustomerRequestTest` still green (18).

### 3. A server-side photo component and the three views
Files: `resources/views/components/shop/photo.blade.php (new)`, `resources/views/shop/partials/request-row.blade.php`, `resources/views/shop/partials/request-card.blade.php`, `resources/views/shop/request-show.blade.php`
What: `x-shop.photo` with `@props(['url' => null, 'alt' => '', 'placeholder' => 'package'])`: when `$url` is set, `<span class="shop-photo" x-data="{ ok: true }"><img class="shop-thumb" x-show="ok" src="{{ $url }}" alt="{{ $alt }}" loading="lazy" decoding="async" x-on:error="ok = false"><span class="shop-row__lead" x-show="! ok" x-cloak><x-shop.icon :name="$placeholder" /></span></span>`; when null, just the placeholder span. Then:
- Staff row: inside `shop-req__main`, wrap the title in a `shop-inline`: `<x-shop.photo :url="$row['image_url'] ?? null" :alt="$item->label()" />` before `h3.shop-req__title`, only when `$item->isLinkedToProduct()` (sourcing lines keep no photo and no placeholder, so their layout is unchanged). The row partial receives `image_url` from the board (`@include(..., ['image' => $row['image_url']])`).
- Guest card: the same pair in `shop-request__head` before the text stack, for pre-order lines.
- Detail page items: `<x-shop.photo>` as the row's lead for pre-order lines, using `$images[$item->product_code] ?? null`.
- Edit page: no view change (the seed now carries `product`).
Check: `php artisan test --filter=ShopViewContractTest` green (the component is under `components/`, outside the scan; the views gain only `x-shop.*` and `shop-*`).

### 4. Tests
Files: `tests/Feature/Shop/ShopRequestsTest.php`
What: add a nullable `IMAGE` column to the fixture and a product with a blob (`5000000000017`, Oat drink). Extend `staff_board_shows_actions_and_form`: a pre-order line for that product renders `class="shop-thumb"` with `src="` + `route('products.image', 'p1')` and `x-on:error="ok = false"`; a sourcing line renders no `shop-thumb`. Extend `guest_board_is_shop_styled_and_read_only`: the same `src` on the guest card. Extend `detail_page_is_shop_styled_and_lists_lines_and_history`: the item list shows the photo for the pre-order line. Extend `edit_page_seeds_lines_and_posts_to_update`: the seed JSON contains `"product":{"id":"5000000000017","image_url":"…products.image…"}`.
Check: `php artisan test --filter="ShopRequestsTest|CustomerRequestTest|ProductSearchImageUrlsTest"` green.

### 5. Docs, format, build
Files: `docs/features/customer-requests.md`, `docs/design/shop-mode/README.md`, `docs/features/product-search.md`
What: customer requests doc: pictures on pre-order lines via the shared resolver; guests see CDN pictures only. README: `x-shop.photo` (server-side) beside `x-shop.product-thumb` (Alpine), and when to use which. Product search doc: the new public `imageUrlsByCode()` for pages that hold product codes. `./vendor/bin/pint --dirty`; `npm run build`.
Check: `./vendor/bin/pint --test --dirty` clean; build succeeds.

## Verification

1. `php artisan test --filter="Shop|CustomerRequest|ProductSearch"` → green; `php artisan test` → 15 failed, the identical set; passed = 640 + new tests.
2. `git diff app/Services/ProductSearch/ProductSearchService.php` → the new public method and the shared `has_image` expression only; `git diff app/Http/Controllers/CustomerRequestController.php` → `show()`, `edit()`/`seedItems()` only.
3. Contract greps; design block `cmp` identical.
4. Manual, dev app, on a throwaway request: New request → pick a product that has a picture → save → the board row shows the picture beside the name; a sourcing request shows none; Details shows it in the item list; Edit shows it on the seeded line (this was the cycle 14 gap); sign out and open the public board: a product with a POS photo shows the placeholder (expected, see Out of scope), one with a supplier CDN picture shows it; delete the throwaway request.

## Risks

- **Guests and POS photos**: an authenticated image route behind a public board means placeholders for those products when signed out; stated, and the placeholder path is tested by the component's error handling.
- **One extra query per board**: a single `whereIn` over the board's product codes plus the supplier-cache batch; a handful of rows.
- **`products.image` permission**: if it requires more than employees hold, staff would also see placeholders; the implementer checks the route's middleware in step 3 and records it.
