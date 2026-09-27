# Plan: Shop mode cycle 24 — Pictures on delivery rows

**Status:** ACCEPTED
**Planner:** Fable 5.1
**Date:** 2026-09-26

## Goal

Show a product picture on the Shop delivery screens: each row on the scan page, the scan prompt (the two-step "Add N" card), the correction card, and the discrepancy rows on the summary page. Pictures come from the same resolver the other Shop screens use (POS photo first, supplier CDN second, placeholder otherwise), with POS photos served as small cached thumbnails through a new authenticated Shop route rather than the full-size blob.

## Context (verified 2026-09-26)

- Rows on both delivery pages come from `delivery-legacy.items` JSON (`DeliveryLegacyController::items()`, ~line 880): fields `barcode, code (supplier code, may be empty), name, expected, scanned, stockable, stock, status`. The scan prompt uses `delivery-legacy.scan-increment` (`scanIncrement()`, response `product{name, barcode, supplierCode, categoryName, currentStock}` at ~line 1431; the product row is fetched by raw SQL at ~1330–1370, `$resolvedBarcode` is the unit barcode).
- Shared resolver: `ProductSearchService::imageUrlsByCode(array $codes, ?callable $posPhotoUrl = null): array` (line 489) — keyed by CODE; a product with a POS photo goes to the callback (or `products.image`, the full blob, when none is given); otherwise supplier CDN template / cache; else null. Selects `SELECT_COLUMNS` (`ID, CODE, REFERENCE, NAME, DISPLAY, CATEGORY, TAXCAT, PRICESELL, PRICEBUY, ISSERVICE`) + `has_image`, eager-loads `supplierLink`.
- `CustomerRequestService::imageUrlsForRequestLines()` (line 303) is the model: it calls the resolver with a callback to `customer-requests.photo?code&v=` and a private `photoVersions(array $codes)` (line 328: first 8 hex of md5(IMAGE), `MD5()` in SQL on MySQL, PHP fallback elsewhere). `CustomerRequestController::photo()` (line 106) serves the thumbnail: `ProductThumbnailService::jpeg($code, $blob, 112)`, `Cache-Control: public, max-age=604800, immutable` when `v` matches, otherwise `max-age=0, must-revalidate`; ETag/304; 404 when the encoder cannot read the blob. That route is public and gated to current request lines.
- `ProductThumbnailService` (`SIZES = [112, 224]`, `jpeg()`, `prune()`, static `currentPhotoProducts()` = F&V categories + Jon's produce). The weekly prune deletes every cached thumbnail not in that set, so delivery thumbnails are regenerated after each Sunday prune (one encode per product per week). Accepted for this cycle; see Risks.
- Front end: `resources/js/shop/product-images.js` (`key(p) = p?.id ?? p?.code`, `hasImage`, `imageFailed`), composed with `mix()` (`resources/js/shop/mix.js`, keeps getters). `x-shop.product-thumb` (`resources/views/components/shop/product-thumb.blade.php`, props `expr` (default `p`), `placeholder`; renders `img.shop-thumb` + `span.shop-row__lead` fallback). `delivery-scan.js` and `delivery-summary.js` are plain objects (not composed with `mix`).
- Views: `resources/views/shop/delivery-scan.blade.php` — rows are `button.shop-row.shop-item` (grid `minmax(0,1fr) auto` from the design, `.shop-item` at design css line 436); prompt card header is a `shop-between` with a `shop-stack--tight` (title + code) and the cancel button; correction card has a `shop-stack--tight` with name/code/stock. `resources/views/shop/delivery-summary.blade.php` lines 52–60: `div.shop-row` with `shop-row__main` / `shop-row__aside`, `x-for="row in discrepancies"`.
- Routes: Shop group `Route::prefix('shop')->name('shop.')` at `routes/web.php:77` inside the auth group; deliveries sub-group under `permission:deliveries.process` (lines 97–101). `customer-requests.photo` at line 64 (public, throttled, `where('code', '[A-Za-z0-9_-]+')`).
- Tests: `tests/Feature/Shop/ShopDeliveryTest.php` uses `tests/Concerns/CreatesLegacyDeliveryPosTables.php` (only user of the trait). Its `PRODUCTS` table has `ID, NAME, CODE, REFERENCE, CATEGORY, TAXCAT, PRICEBUY, PRICESELL` — **no `DISPLAY`, `ISSERVICE`, `IMAGE`**, so anything reaching `imageUrlsByCode` fails with "no such column" until they are added. `tests/Feature/ProductSearchImageUrlsTest.php` shows the resolver's fixtures (binary `IMAGE`, `supplier_link`). `tests/Feature/Shop/ShopRequestsTest.php` covers `customer-requests.photo` and `imageUrlsForRequestLines`.
- Baseline: 15 failed / 664 passed (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2, TestScraper ×1).

## Constraints

- No commits, no deploys. Do not edit `docs/design/shop-mode/**`.
- Shop view contract (components, `shop-*` classes, JS in `resources/js/shop`, design block of `resources/css/shop.css` byte-identical to the design file, app rules under APP ADDITIONS only).
- The full-size photo must never be served by the new route; `products.image` stays where it is.
- `customer-requests.photo` keeps its behaviour and its gate; only its duplicated code moves.
- Office pages using `delivery-legacy.items` / `scan-increment` only gain a field; nothing else in the JSON changes.

## Out of scope

- Pictures on the Deliveries list, labels queue, order review.
- Widening `currentPhotoProducts()` / changing the prune.
- Any layout change beyond adding the lead picture.

## Steps

### 1. Share the thumbnail plumbing
- `ProductThumbnailService`: add `public function versions(array $codes): array` — move the body of `CustomerRequestService::photoVersions()` here unchanged (SQL `MD5()` on MySQL, PHP fallback; keyed by CODE, first 8 hex). `CustomerRequestService::imageUrlsForRequestLines()` calls `$this->thumbnails->versions($codes)` (inject `ProductThumbnailService`); delete the private method.
- New trait `app/Http/Controllers/Concerns/ServesProductThumbnails.php` with `protected function thumbnailResponse(Product $product, int $size, Request $request, ProductThumbnailService $thumbnails): Response` — the body of `CustomerRequestController::photo()` from the `jpeg()` call to the return (encoder-null 404, versioned cache-control, ETag/304). `CustomerRequestController::photo()` keeps its gate and product lookup and calls the trait.
**Check:** `php artisan test --filter=ShopRequestsTest` green with no assertion changed.

### 2. Shop photo route
- `routes/web.php`, inside the Shop group (not under a permission sub-group — any signed-in staff member may see a product picture): `Route::get('/products/{code}/photo', [Shop\ProductPhotoController::class, 'show'])->where('code', '[A-Za-z0-9_-]+')->name('product-photo');`
- `app/Http/Controllers/Shop/ProductPhotoController.php` (new): `show(string $code, Request $request, ProductThumbnailService $thumbnails)`: `Product::where('CODE', $code)->first(['ID', 'CODE', 'IMAGE'])`, 404 when missing or blob empty, then `thumbnailResponse($product, 112, …)`.
- New `app/Services/Shop/ProductImageUrls.php` with `byCode(array $codes): array` = `productSearch->imageUrlsByCode($codes, fn (Product $p) => route('shop.product-photo', array_filter(['code' => $p->CODE, 'v' => $versions[$p->CODE] ?? null])))` where `$versions = $thumbnails->versions($codes)`. This is the one place that knows the Shop photo route.
**Check:** signed-in employee `GET /shop/products/{code}/photo?v=<8 hex>` → 200 `image/jpeg`, `Cache-Control` immutable; wrong `v` → `max-age=0`; guest → redirect to login; unknown code → 404. Confirm with `curl -sI` or the test in step 6.

### 3. `image_url` in the legacy JSON
- `DeliveryLegacyController::items()`: after both row loops, `$urls = $images->byCode(array_column($rows, 'barcode'))` once, then `$row['image_url'] = $urls[$row['barcode']] ?? null` for every row. Inject `App\Services\Shop\ProductImageUrls` (constructor or method injection).
- `scanIncrement()`: add `'image_url' => $images->byCode([$resolvedBarcode])[$resolvedBarcode] ?? null` to the `product` array (only when `$product` is set).
**Check:** in tinker or the browser network tab, `items` for a session shows `image_url` on every row (string or null); `scan-increment` for a product with a POS photo returns the Shop photo URL with `v=`.

### 4. Front end
- `product-images.js`: `key(p)` → `p?.id ?? p?.barcode ?? p?.code ?? null` (delivery rows carry `barcode`; their `code` is the supplier code and can be empty or shared). Update the doc comment. Search-API products have no `barcode` key, so nothing else changes key.
- `delivery-scan.js` and `delivery-summary.js`: wrap the object in `mix(productImages(), { … })` (imports as in `fv-harvest.js`). Getters survive because `mix` copies descriptors.
- `delivery-scan.blade.php`:
  - rows: `class="shop-row shop-item shop-item--pic"` and `<x-shop.product-thumb expr="row" />` as the first child of the button (before `shop-row__main`);
  - prompt card header: `<x-shop.product-thumb expr="pending?.product" />` as the first child of the `shop-between`, before the title stack (the `expr` is used inside `hasImage(…)`, `:src="…?.image_url"` and `imageFailed(…)`, so an optional chain is valid there);
  - correction card: `<x-shop.product-thumb expr="editingRow" />` beside the name/code stack — wrap the existing `shop-stack--tight` and the thumb in a `div.shop-inline` (align-items center; check `shop-inline` in the design css does that, otherwise use `shop-between` with the thumb first).
- `delivery-summary.blade.php`: `<x-shop.product-thumb expr="row" />` as the first child of each `div.shop-row` (flex row: the lead sits at the left as on other lists).
- `resources/css/shop.css`, APP ADDITIONS, with a one-line comment: `.shop-item--pic { grid-template-columns: auto minmax(0, 1fr) auto; }` — the design's item grid has two columns; the picture needs a third.
**Check:** `npm run build` clean; contract test green; the `cmp` design-block check prints nothing.

### 5. Test fixture
`tests/Concerns/CreatesLegacyDeliveryPosTables.php`: add to `PRODUCTS` `->string('DISPLAY')->nullable()`, `->boolean('ISSERVICE')->default(false)`, `->binary('IMAGE')->nullable()`. (Only `ShopDeliveryTest` uses the trait.) Seed one product with a real JPEG blob the way `FruitVegProductImageTest` / `ProductSearchImageUrlsTest` do (GD-generated), so the items test has a product with a photo.
**Check:** `php artisan test --filter=ShopDeliveryTest` green after step 3.

### 6. Tests
In `ShopDeliveryTest`:
- `test_items_endpoint_classifies_the_session` (or a new `test_items_carry_picture_urls`): the seeded-photo row's `image_url` is `route('shop.product-photo', ['code' => …, 'v' => substr(md5($blob), 0, 8)])`; a row without a photo and without a supplier integration has `image_url` null; every row has the key.
- `test_scan_increment_returns_the_picture`: for the photo product, `product.image_url` is the Shop photo URL.
- `test_product_photo_route_serves_a_thumbnail`: employee → 200, `image/jpeg`, immutable when `v` matches, `must-revalidate` otherwise; second request with `If-None-Match` → 304.
- `test_product_photo_route_needs_a_login`: guest → redirect to login; `test_product_photo_route_404s_without_a_photo`.
- Scan page render: `assertSee('class="shop-row shop-item shop-item--pic"', false)`, `assertSee('hasImage(row)', false)`; summary page render: `assertSee('hasImage(row)', false)`.
- `tests/Feature/Shop/ShopViewContractTest` and `ShopRequestsTest` unchanged and green.
**Check:** `php artisan test` → the 15 baseline failures only.

### 7. Format and tidy
`./vendor/bin/pint --dirty`; `npm run build`; `php artisan view:clear`.

## Verification (report every item with what you saw)

1. `php artisan test` summary line; the same 15 pre-existing failures.
2. Design-block `cmp` prints nothing; contract test green.
3. **Timing** (this matters): `items` for the 164-line session (`supplierID=37` on dev) before and after step 3, measured from the browser network tab or `curl -w '%{time_total}'` three times each. Report both figures.
4. Browser, scan page for that session: rows with a POS photo show the picture (network tab: `/shop/products/<code>/photo?v=…`, 200 then cached / 304); rows with a supplier CDN picture show it; a CDN miss falls back to the placeholder without a broken image; rows without either show the `package` placeholder. Scan a photo product: the prompt shows its picture; tap a row: the correction card shows it. At 500 px (or narrower if you can) the three-column row does not squeeze the quantity.
5. Summary page for the same session: discrepancy rows carry the picture or placeholder.
6. Customer requests board and staff view: pictures still show (the moved `versions()` and trait); the public photo route still 404s for a code not on a current request.
7. Stock scan / Find product / F&V pages: unchanged pictures (the `key()` change).

## Risks

- **Hashing cost.** `versions()` runs `MD5(IMAGE)` over every photo product in the list, on every `items()` call, which is after every scan. On the requests board that is a handful of products; a 164-line delivery could be 100+ MB hashed per call. If the measured delta in Verification 3 exceeds ~250 ms, cache the versions map in `ProductImageUrls::byCode` for 10 minutes (`Cache::remember('shop-photo-versions:'.md5(implode(',', $sortedCodes)), 600, …)`), say so in the report, and note that a photo changed in the office can take up to 10 minutes to refresh on the scan page. Do not drop `v`: without it every thumbnail is a conditional request that reads the blob.
- **Weekly prune** removes delivery thumbnails (not in `currentPhotoProducts()`); they are re-encoded on the next view. Acceptable now; widening the prune set is a later cycle because it loads every blob into PHP.
- **`button` rows with an `<img>` inside**: `img`/`span` are not interactive, so the row click is unaffected; `loading="lazy"` inside a button is fine.
- **Grid third column**: `.shop-thumb` and `.shop-row__lead` are `flex: none` sized 48 px; in the grid the `auto` column takes that width. Only one of the two is displayed at a time (`x-show` sets `display:none`, which removes the grid item).
- **`expr="pending?.product"`** yields expressions such as `hasImage(pending?.product)` and `pending?.product?.image_url` — both valid. If Alpine complains about `imageFailed(pending?.product)` in the `x-on:error` handler, fall back to a `pendingProduct` getter (`this.pending?.product ?? null`) and `expr="pendingProduct"`.
