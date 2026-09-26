# Shop mode cycle 22 — Guests see product pictures on the public requests board

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

On the public customer-requests board, products whose only picture is the till's own photo show a placeholder to a signed-out tablet, because the product image route needs a login. The owner wants guests to see those pictures. Rather than opening the product image route, the board gets its own small public thumbnail: a 112 px JPEG of the till photo, served only for products that are on a current customer request, cached on disk by the existing thumbnail service and versioned like the fruit-and-veg thumbnails so it can be cached for a week. The board's pictures for POS-photo products point at this route for guests and staff alike; supplier pictures are unchanged.

## Context

- Cycle 21: `ProductSearchService::imageUrlsByCode(array $codes)` returns `route('products.image', $ID)` for a product with a POS photo (`has_image`), else a supplier URL, else null. `products.image` is behind `auth` + `permission:products.view`. `CustomerRequestService::rowsFrom()` enriches board rows with `image_url`; `CustomerRequestController::show()` and `seedItems()` call the same method.
- Thumbnails: `App\Services\ProductThumbnailService::jpeg(string $code, string $blob, int $size): ?string` (sizes `[112, 224]`, cache `fv-thumbs/<md5(code)12>-<size>-<md5(blob)12>.jpg` on the `local` disk, sweeps older files for the same code and size). `FruitVegController::productImage()` (cycle 17e) shows the response shape: `ETag` of the served bytes, `If-None-Match` → 304, `Cache-Control: public, max-age=604800, immutable` when `?v=` matches `substr(md5($blob), 0, 8)`, else `public, max-age=0, must-revalidate`.
- Public board route: `routes/web.php:59`, `Route::get('/customer-requests', …)->name('customer-requests.index')`, outside the auth group.
- Request lines: `CustomerRequestItem` (`product_code`, `request()`), `CustomerRequest` (`closed_at`; the board shows open requests and, for staff, ones closed in the last 30 days).
- `Product` loads `IMAGE` by default; select only `ID`, `CODE`, `IMAGE` for the one product the route serves.
- Tests: `ShopRequestsTest` (14; fixture now has `IMAGE`), `ProductSearchImageUrlsTest` (11), `FruitVegProductImageTest` (the versioned-header assertions to mirror). Suite baseline: 15 failed / 653 passed.

## Constraints

- Do not commit, push or deploy.
- The new route is public but narrow: it serves a thumbnail only for a product code that appears on a request line whose request is open or was closed within the last 30 days; anything else is 404. It never serves the full-size photo. Throttled.
- `products.image` and its middleware are unchanged. `imageUrlsByCode()`'s default behaviour is unchanged (the search API keeps returning `products.image`).
- Design block byte-identical; no new stylesheet rules.

## Out of scope

- Supplier-CDN pictures (already visible to guests).
- Guest access to any other product image.

## Steps

### 1. A public thumbnail route for request lines
Files: `app/Http/Controllers/CustomerRequestController.php`, `routes/web.php`
What: `photo(string $code, Request $request)`: 404 unless a `CustomerRequestItem` with that `product_code` exists whose request is open (`closed_at` null) or closed within 30 days; load `Product::where('CODE', $code)->first(['ID', 'CODE', 'IMAGE'])`; 404 when missing or `IMAGE` empty; `$jpeg = app(ProductThumbnailService::class)->jpeg($code, $product->IMAGE, 112)`; 404 when null (undecodable); then the same headers as `FruitVegController::productImage()` for a thumbnail: `ETag` = `"` . md5($jpeg) . `"`, 304 on a matching `If-None-Match`, `Cache-Control` long-and-immutable when `?v=` equals `substr(md5($product->IMAGE), 0, 8)` (use `hash_equals`), else `public, max-age=0, must-revalidate`; `Content-Type: image/jpeg`. Route next to the board's: `Route::get('/customer-requests/photo/{code}', [CustomerRequestController::class, 'photo'])->name('customer-requests.photo')->middleware('throttle:120,1');` (outside the auth group; `{code}` constrained to `[A-Za-z0-9_-]+`).
Check: feature tests in step 4.

### 2. The board resolves POS photos to the public route
Files: `app/Services/ProductSearch/ProductSearchService.php`, `app/Services/CustomerRequestService.php`, `app/Http/Controllers/CustomerRequestController.php`
What: `imageUrlsByCode(array $codes, ?callable $posPhotoUrl = null)`: when `$posPhotoUrl` is given, a product with `has_image` resolves to `$posPhotoUrl($product)` instead of `route('products.image', …)`; supplier resolution unchanged. The `has_image` products' version needs the blob's md5, which the search select does not load; so the callable receives the `Product` (with `ID`, `CODE`) and the request-board caller computes the version itself with one extra query: `Product::whereIn('CODE', $codes)->whereNotNull('IMAGE')->get(['CODE', DB::raw('MD5(IMAGE) as image_md5')])` (MySQL and SQLite both have `md5()`? SQLite does **not**; use `->pluck('IMAGE', 'CODE')` and `md5()` in PHP for portability, the blobs are few on a board). Add a private `boardPhotoUrls(array $codes): array` in `CustomerRequestService` that does this and passes `fn ($product) => route('customer-requests.photo', ['code' => $product->CODE, 'v' => $versions[$product->CODE]])` into `imageUrlsByCode()`; `rowsFrom()`, `show()` and `seedItems()` all use it (expose it as a public `imageUrlsForRequestLines(array $codes)` on the request service so the controller shares it).
Check: `ProductSearchImageUrlsTest` gains `a_pos_photo_uses_the_callback_when_given` (the callback's URL is returned, the supplier path untouched); `ShopRequestsTest` assertions move from `route('products.image', 'p1')` to `route('customer-requests.photo', ['code' => '5000000000017', 'v' => substr(md5($blob), 0, 8)])` on the staff board, the guest card, the detail page and the edit seed.

### 3. Docs
Files: `docs/features/customer-requests.md`, `docs/design/shop-mode/README.md`, `docs/features/product-search.md`
What: customer requests: the public thumbnail route, its scope (only current request lines), the version and week-long cache, and that supplier pictures are unchanged; remove the "guests see placeholders for POS photos" limitation. README: the board photo URL now works for guests. Product search: the optional callback.
Check: `grep -n "placeholder" docs/features/customer-requests.md` no longer describes a guest limitation.

### 4. Tests
Files: `tests/Feature/Shop/ShopRequestsTest.php`
What: with the fixture's photo product (a real small GD-generated PNG blob, as `FruitVegProductImageTest` does, so the thumbnail service can decode it):
- `guest_can_load_a_request_line_photo`: a pending request line for `5000000000017` → GET the route with no session → 200 `image/jpeg`, `getimagesizefromstring` 112 × 112; with the right `v` → `max-age=604800`; with a wrong `v` → `max-age=0`.
- `photo_is_refused_for_products_not_on_a_current_request`: a product with a photo but no request line → 404; a line on a request closed 40 days ago → 404; closed 5 days ago → 200.
- `photo_is_refused_without_a_photo`: a line for the product with `IMAGE` null → 404.
- `guest_board_is_shop_styled_and_read_only`: the card's `src` is the new route with `v`.
- Existing staff/detail/edit assertions updated as in step 2.
Check: `php artisan test --filter="ShopRequestsTest|ProductSearchImageUrlsTest|CustomerRequestTest"` green.

### 5. Format, build
What: `./vendor/bin/pint --dirty`; `npm run build` (no asset change expected).
Check: `./vendor/bin/pint --test --dirty` clean.

## Verification

1. `php artisan route:list --name=customer-requests.photo` → public (no auth), `throttle:120,1`.
2. `php artisan test --filter="Shop|CustomerRequest|ProductSearch"` → green; `php artisan test` → 15 failed, the identical set; passed = 653 + new tests.
3. `git diff app/Http/Controllers/CustomerRequestController.php` → `photo()` plus the resolver switch; `git diff app/Services/ProductSearch/ProductSearchService.php` → the optional parameter only.
4. Manual: fetch the public board with no cookies (curl or a private window): every pre-order picture loads, including the two till photos that were placeholders on dev; the network tab shows them as 112 px JPEGs with the week-long cache header; a second load makes no image requests; a direct GET of the route for a product not on any request → 404; the office product pages still serve full images through `products.image`.

## Risks

- **Public exposure** is limited to 112 px thumbnails of products a customer has asked for, on a board that is already public and shows the product names; the 404 rule keeps it from becoming a general product-image endpoint, and the throttle caps scraping.
- **Two lookups per board render** for versions (one `whereIn` for photo blobs of the board's codes): a handful of rows; the blobs are those products' photos only.
- **Cache folder sharing** with the F&V thumbnails is fine: keys hash code and blob, and the sweep is per code and size.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end and the route, `photo()`, the search service's optional callback, the request service's resolver and version helper, and both test files. Reran `php artisan test`: 15 failed / 662 passed, the identical set (653 + 9). Design block untouched. The route is public, throttled, constrained, and refuses anything not on a current request line; it never serves the full photo and 404s on an undecodable blob rather than falling back, which is the right call for a public route.

**Steps 1–5: pass.** The implementer probed the boundary as a true guest with `curl`: a requested product's thumbnail loads with the week-long header, an unrequested product, an unknown code and a path-traversal attempt are all 404, and the office image route still redirects to login.

**Deviations.** Hashing the photo in SQL on MySQL with a PHP fallback for SQLite: **accepted, and better than the plan**; pulling every board product's photo into PHP on a self-refreshing public page would have been the wrong cost.

**Notes for Planner.**
1. What the route exposes: a small thumbnail of a product already named publicly on the board, never who asked; **recorded**, with the one-method lever if it is ever reconsidered.
2. The 30-day window written twice: **tidy candidate**, a shared constant.
3. Request-line products outside the F&V and Jon sets churn against the prune: **tidy candidate**, add them to `currentPhotoProducts()`.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-22/`.
