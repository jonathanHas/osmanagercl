# Shop mode cycle 17c — Pictures on the fruit & veg screens

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

The waste and harvest screens show a product picture on every choice tile and Today row that has one, and a produce placeholder where there is none, so staff recognise produce at a glance. The pictures are the ones the office fruit-and-veg pages already show, served by the existing per-product image route. No new storage, no uploads: products without a picture keep the placeholder until someone adds one on the office manage page.

## Context

- **Where the pictures are.** POS `PRODUCTS.IMAGE` is a blob (JPEG/PNG), hidden on the `Product` model but loaded with every product query (`getHidden()` = `IMAGE`, `ATTRIBUTES`; the attribute is present after `get()`). `FruitVegController::productImage($code)` (`fruit-veg.product-image`, `permission:fruit_veg.operate`) streams it with an ETag, or a 1 × 1 transparent PNG when the product has none. Office pages render `<img :src="'/fruit-veg/product-image/' + code">` with an error fallback. Dev: 406 of 694 F&V products and 64 of Jon's 111 have an image; average 50 kB.
- **Rows the Shop screens read.** `WasteController::buildRows()` (rows for `waste.rows` and `waste.search`): `{ code, name, category, origin, class, on_till, current_price, priced_unit, quantity, unit, value }`. `HarvestController::dataFor()` → `productLookup` per product `{ code, name, category, unit, label }` → `rows` `{ code, name, unit, logged, label }` and `available`; `rows()` maps them to `{ code, name, unit, logged, updated_at, by }` and `{ code, name, unit }`.
- **Shared thumbnail pieces** (cycles 14–14c): `x-shop.product-thumb` (`expr` prop; renders `<img class="shop-thumb" x-show="hasImage(expr)" :src="expr?.image_url" … x-on:error="imageFailed(expr)">` plus `<span class="shop-row__lead"><x-shop.icon name="package" /></span>`; extra attributes go on the img), `resources/js/shop/product-images.js` (`failed` keyed by `p.id`; `hasImage(p)`, `imageFailed(p)`), `mix()` for composing. The F&V rows have `code`, not `id`.
- **Design.** `shop-choice` is a text tile (`display: flex; flex-direction: column; … padding: 10px 44px 10px 16px`, the check mark absolutely at the right). The design has no picture tile; an app addition is needed. `.shop-thumb` (48 px) and `.shop-thumb--lg` exist under `APP ADDITIONS`.
- Screens: `resources/views/shop/fv-waste.blade.php` (choices over `shown`; Today list), `fv-harvest.blade.php` (choices over `choices`; Today list); scripts `fv-waste.js`, `fv-harvest.js` (neither composes `productImages()` yet). Tests `ShopFruitVegTest` (POS fixture `PRODUCTS` has no `IMAGE` column yet).

## Constraints

- Do not commit, push or deploy. No office view changes; the image route unchanged.
- The rows JSON must not carry the blob, only a URL when an image exists; the blob is already loaded by the product query, so `IMAGE !== null` is free. Do not add a query per product.
- Design block byte-identical; the picture tile is an app addition.

## Out of scope

- Uploading or editing pictures from Shop mode.
- Reducing the blob load in the product query (a pre-existing cost on the office pages; a `select` without `IMAGE` plus a `whereNotNull` existence pluck would fix it; candidate for housekeeping).
- Pictures on other Shop screens (Find product and the request screens already have them).

## Steps

### 1. Rows carry an image URL when the product has a picture
Files: `app/Http/Controllers/WasteController.php`, `app/Http/Controllers/HarvestController.php`
What: in `buildRows()` add `'image_url' => $product->IMAGE !== null ? route('fruit-veg.product-image', $product->CODE) : null`. In `HarvestController::dataFor()` add the same key to each `productLookup` entry and carry it into `rows` (alongside `logged`) and `available`; `rows()` includes `image_url` in both arrays. Nothing else changes.
Check: tinker `app(App\Http\Controllers\WasteController::class)` is not needed; hit `/fruit-veg/waste/rows` and `/fruit-veg/harvest/rows` as admin through the kernel: every item has an `image_url` key, a URL for products with a blob and null otherwise; `curl` one URL → `image/jpeg` or `image/png` 200.

### 2. Shared parts accept `code` as the key and a produce placeholder
Files: `resources/js/shop/product-images.js`, `resources/views/components/shop/product-thumb.blade.php`
What: `productImages()` keys `failed` by `p.id ?? p.code` in both `hasImage` and `imageFailed`. The component gains `@props(['expr' => 'p', 'placeholder' => 'package'])` and renders `<x-shop.icon :name="$placeholder" />` in the lead span.
Check: the cycle 14c node exercise still passes; `Blade::render('<x-shop.product-thumb placeholder="carrot" />')` contains `#carrot`; existing request and find-product tests green (`php artisan test --filter="ShopRequestsTest|ShopFindProductTest"`).

### 3. Picture tiles
Files: `resources/css/shop.css` (`APP ADDITIONS`), `resources/views/shop/fv-waste.blade.php`, `resources/views/shop/fv-harvest.blade.php`, `resources/js/shop/fv-waste.js`, `resources/js/shop/fv-harvest.js`
What: app rules:
```css
/* Choice tiles with a picture (F&V): thumbnail at the left, text beside it; the
   design's check mark keeps the right-hand 44 px. */
.shop-choice--pic { flex-direction: row; align-items: center; gap: var(--shop-space-3); padding-left: 10px; }
.shop-choice--pic .shop-thumb, .shop-choice--pic .shop-row__lead { width: 56px; height: 56px; border-radius: var(--shop-radius-sm); }
.shop-choice__text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
```
Both scripts compose `productImages()` with `mix()` (as `requests.js` does). Both views: each choice becomes `<label class="shop-choice shop-choice--pic"><input …><x-shop.product-thumb placeholder="carrot" /><span class="shop-choice__text"><span x-text="p.name"></span><small x-text="…"></small></span></label>`; each Today row gets `<x-shop.product-thumb expr="p" placeholder="carrot" />` (harvest: `expr="r"`) before `shop-row__main`. The harvest `today` rows come from `rows`, which now carry `image_url`; the waste `today` rows are the same objects as the tiles.
Check: `php artisan test --filter="ShopFruitVegTest|ShopViewContractTest"` green; design block `cmp` identical; `sed -n '/APP ADDITIONS START/,$p' resources/css/shop.css | grep -c "shop-choice"` → 3.

### 4. Tests
Files: `tests/Feature/Shop/ShopFruitVegTest.php`
What: add a nullable binary `IMAGE` column to the POS `PRODUCTS` fixture; give one product a small blob (any bytes) and leave another null. Extend `waste_rows_lists_on_till_products_with_todays_entry` and `harvest_rows_lists_recent_and_available_jon_products` to assert `image_url` equals `route('fruit-veg.product-image', $code)` for the product with a blob and is null for the other. Extend `employee_can_open_both_screens` to assert `shop-choice shop-choice--pic`, `class="shop-thumb"` and `#carrot` on both screens.
Check: `php artisan test --filter=ShopFruitVegTest` green.

### 5. Docs, build, format
Files: `docs/features/fruit-veg-system.md`, `docs/design/shop-mode/README.md`
What: one sentence each: pictures come from `PRODUCTS.IMAGE` via `fruit-veg.product-image`; rows carry `image_url` or null; the `.shop-choice--pic` app addition; the placeholder prop. `npm run build`; `./vendor/bin/pint --test --dirty`.
Check: build succeeds; pint clean.

## Verification

1. `php artisan test --filter=Shop` → green; `php artisan test` → 15 failed, the identical set; passed unchanged (no new tests, more assertions).
2. Design block `cmp` identical; contract greps; `grep -c "route(" resources/js/shop/fv-*.js resources/js/shop/product-images.js` → 0.
3. `npm run build` succeeds.
4. Manual, dev app: Waste log shows pictures on most tiles (406 of 694 F&V products have one) and a carrot placeholder on the rest; the picked tile keeps its check mark on the right; Harvest tiles the same (64 of Jon's 111 have pictures); log a product with a picture → its Today row shows the same picture; a product without → placeholder; the network tab shows images loading lazily, only for visible tiles, with 304s on a reload.

## Risks

- **Bandwidth**: ~50 kB per picture, up to 98 tiles on the waste screen. `loading="lazy"` on the component limits it to what scrolls into view, and the route's ETag makes reloads cheap. If the tablet feels slow, a thumbnail-sized variant is a later change on the image route, not on these screens.
- **The product query already moves every blob from the POS database into PHP** on every rows request (a pre-existing cost shared with the office waste page); noted as a housekeeping candidate above, not touched here.
- **`failed` keyed by `code`**: `id` wins when present so the other screens are unaffected.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end and the diffs of both controllers, the shared image module and component, the four stylesheet rules, both views, both scripts and the test. Reran `php artisan test`: 15 failed / 594 passed, the identical set, twelve more assertions. Affected Shop suites (F&V, contract, requests, find product) 53 passed. Design block byte-identical.

**Steps 1–5: pass.** Rows carry `image_url` or null without an extra query; the shared part keys failures by `id ?? code` and the component takes a placeholder; the tiles and Today rows show the pictures; the fixture has the blob column and the assertions on both endpoints and both screens.

**Deviations.** The fourth stylesheet rule widening picture-tile grids to 220 px: **accepted, and necessary**: the design's 148 px column left 33 px for a name beside a 56 px picture; the number was measured, and `:has()` scopes it to picture grids only.

**Notes for Planner.**
1. 3.15 MB on the first open of the waste screen, because the pictures are full-size photos and the grid is only two screens tall so lazy loading defers little: **real, and the plan's estimate was wrong. Fixed next, cycle 17d**: a thumbnail size on the image route, cached on disk, an order of magnitude smaller. The 24-hour cache header already makes it per device per day, so the screen is usable meanwhile.
2. The rows depend on the product query loading the blob: **agreed**; recorded with the housekeeping candidate so an existence pluck goes in with any `select` optimisation.
3. The waste grid is long at 220 px minimum: **accepted for now**; the whole on-till range is the working set for waste, and a cut is the owner's call.
4. Harvest accumulating across a unit change: still the open candidate; carried.
5. The `harvest_product_units` row the implementer deleted and restored: the count is back at 10; noted, well handled.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-17c/`.
