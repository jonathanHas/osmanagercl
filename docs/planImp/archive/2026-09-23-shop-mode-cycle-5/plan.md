# Shop mode cycle 5 — product images on Find product

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-23

## Goal

Show a product photo wherever one is available on the Find product screen: a small thumbnail on each result row and a larger one on the detail card. Staff at the counter can then confirm "is this the one?" with the customer at a glance. Where no photo exists, or the photo fails to load, the row shows a neutral placeholder and the card shows nothing, so the layout never jumps. No other screen changes.

## Context

Baseline: cycle 4 accepted and archived (`docs/planImp/archive/2026-09-23-shop-mode-cycle-4/`); cycles 3 and 4 are uncommitted in the working tree. Record `git status --short` at the start as usual.

**Owner request (2026-09-23):** "we do need images where they are available though."

**Where images come from.** Every row of `GET /api/products/search` already carries `image_url` (string or `null`) and `has_image` (bool). `ProductSearchService::imageUrl()` (line 468) returns, in order: the local route `products.image` when the POS record holds an image blob (`has_image`); otherwise a supplier CDN URL derived by barcode or supplier code (with a template fallback when nothing is cached, in which case the URL may 404); otherwise `null`. So a non-null `image_url` is a *candidate*, not a guarantee: the office pages hide the `<img>` on the browser's `error` event (see `resources/views/components/product-search/thumb.blade.php`, `x-on:error="failed = true"`, and `resources/views/components/product-image.blade.php:121`). `products.image` (`ProductController@image`, line 426) is behind `permission:products.view` since cycle 2, serves `image/jpeg` with `Cache-Control: public, max-age=86400`, and 404s when there is no blob.

**The screen today.** `resources/views/shop/find-product.blade.php` renders rows as `<button class="shop-row">` with `shop-row__main` / `shop-row__aside` / chevron, and the detail card as `shop-card` → `shop-between` (label + close) → `shop-stack shop-stack--tight` (name, `shop-bignum`, pills) → `shop-facts`. Behaviour is in `resources/js/shop/find-product.js` (`Alpine.data('shopFindProduct')`), state includes `results`, `selected`.

**The stylesheet has no image styles.** `resources/css/shop.css` is the design bundle verbatim; the design contains no product-image element (the row's `shop-row__lead` is a 48 px circle meant for an icon). Cycle 1's rule was "not edited except to retheme". This cycle adds the first app-specific styles, so the rule becomes: the design block stays verbatim, and app additions live in one clearly marked section appended after the design's last rule (`@media (prefers-reduced-motion: reduce) { … }`, the file's final lines). The docs copy `docs/design/shop-mode/shop.css` stays as the design reference and is not edited, so `cmp` against it will now differ only by the appended section; the README records this.

**Tokens to use** (all defined in the `.shop` block): `--shop-surface-2`, `--shop-line`, `--shop-radius-sm` (10 px), `--shop-radius-md` (16 px). The `package` sprite icon is the placeholder.

**Contract test.** `ShopViewContractTest` allows any class starting with `shop`, so `shop-thumb` passes; `:class` bindings may only toggle `shop-*`/`is-*` names.

## Constraints

- Do not commit, push or deploy.
- Only `resources/css/shop.css` (append-only, below a marker comment), the Find product view and JS, the README, and the test file change. The search API, `ProductController@image`, the office components and every other screen stay untouched.
- Images never break the layout: fixed-size boxes, `object-fit: cover`, placeholder of the same size when there is no image or it fails.
- No hover previews, lightboxes or teleported overlays (the office component's approach); a tap on a row still selects the product.
- The design block of `shop.css` remains byte-identical to `docs/design/shop-mode/shop.css`; the check in Verification 5 proves it.

## Out of scope

- Images on the Stock scan screen (`stocking.lookup` returns none; adding one means touching that endpoint, a separate decision).
- Tap-to-enlarge on the detail card.
- Caching or proxying supplier CDN images.
- The loading indicator noted in cycle 4.

## Steps

### 1. Stylesheet: app additions section
Files: `resources/css/shop.css`
What: append at the very end of the file:
```css

/* === APP ADDITIONS START ===
   Styles the app needs that the design bundle does not define. Keep the design
   block above byte-identical to docs/design/shop-mode/shop.css; add here only. */
.shop-thumb { display: block; flex: none; width: 48px; height: 48px; border-radius: var(--shop-radius-sm); background: var(--shop-surface-2); border: 1px solid var(--shop-line); object-fit: cover; }
.shop-thumb--lg { width: 128px; height: 128px; border-radius: var(--shop-radius-md); }
/* === APP ADDITIONS END === */
```
Check: `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL` prints `DESIGN-BLOCK-IDENTICAL`; `grep -c "APP ADDITIONS" resources/css/shop.css` → 2.

### 2. Behaviour: failed-image tracking
Files: `resources/js/shop/find-product.js`
What: add state `failed: {}` (keyed by product id). Add methods `hasImage(p)` → `!! p.image_url && ! this.failed[p.id]` and `imageFailed(p)` → `this.failed = { ...this.failed, [p.id]: true }` (reassign so Alpine sees the change). Reset `failed = {}` at the start of a non-append `search()` so a transient failure does not stick for the session. Nothing else changes.
Check: `node -e "import('./resources/js/shop/find-product.js').then(m => { const d = m.default(); d.failed = {}; const p = {id:'x', image_url:'u'}; console.log(d.hasImage(p)); d.imageFailed(p); console.log(d.hasImage(p), d.hasImage({id:'y', image_url:null})) })"` prints `true`, then `false false`.

### 3. Rows: thumbnail or placeholder
Files: `resources/views/shop/find-product.blade.php`
What: inside the `<button class="shop-row">`, before `shop-row__main`, add:
```blade
<img class="shop-thumb" x-show="hasImage(p)" :src="p.image_url" :alt="p.name" loading="lazy" decoding="async" @error="imageFailed(p)">
<span class="shop-row__lead" x-show="! hasImage(p)"><x-shop.icon name="package" /></span>
```
(`shop-row__lead` is the design's 48 px circle, so both branches take the same space.)
Check: `php artisan test --filter=ShopViewContractTest` green; `grep -c 'class="shop-thumb"' resources/views/shop/find-product.blade.php` → 1.

### 4. Detail card: large image beside the name
Files: `resources/views/shop/find-product.blade.php`
What: wrap the existing `shop-stack shop-stack--tight` block (name, big price, pills) in `<div class="shop-inline">` and place before it:
```blade
<img class="shop-thumb shop-thumb--lg" x-show="selected && hasImage(selected)" :src="selected?.image_url" :alt="selected?.name" decoding="async" @error="selected && imageFailed(selected)">
```
No placeholder on the card: when there is no image the name block simply starts at the left. `shop-inline` wraps on narrow screens, so on a phone the image sits above the name.
Check: `php artisan view:cache` succeeds; `grep -c "shop-thumb--lg" resources/views/shop/find-product.blade.php` → 1.

### 5. Tests
Files: `tests/Feature/Shop/ShopFindProductTest.php`
What: add three tests, using the class's existing helpers:
- `screen_renders_row_thumbnails_and_the_card_image`: employee with `products.view` → page contains `class="shop-thumb"`, `shop-thumb--lg`, `loading="lazy"` and `@error` handling (assert `imageFailed(` appears).
- `search_rows_carry_an_image_url_field`: the search response for `chocolatemakers fruit` has key `image_url` on `data.0` (present, whatever its value) and `has_image` boolean; this pins the API field the screen depends on.
- `barista_cannot_fetch_product_images`: barista with `kds.access` → `GET route('products.image', 'p-none')` → 403; employee with `products.view` → status is not 403 (it will be 404 for an unknown id).
Check: `php artisan test --filter=ShopFindProductTest` green (9 tests).

### 6. README
Files: `docs/design/shop-mode/README.md`
What: under "What is here", change the `shop.css` line to say: copy to `resources/css/shop.css`; the app appends its own styles below an `APP ADDITIONS` marker at the end of that file, so the design block stays byte-identical and `head -c` against this file compares it. Add one line under "Component API in the app": `.shop-thumb` / `.shop-thumb--lg` are app additions for product photos (48 px in rows, 128 px on a detail card), hidden on load error.
Check: `grep -c "APP ADDITIONS" docs/design/shop-mode/README.md` → 1.

### 7. Build and format
Files: all touched
What: `npm run build`; `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean; the built `shop-*.css` contains `.shop-thumb` (`grep -l "shop-thumb" public/build/assets/shop-*.css`).

## Verification

1. `php artisan test --filter=Shop` → all green (9 in `ShopFindProductTest`).
2. `php artisan test` → 17 failed / N passed, the identical 17 (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `WasteLogTest` ×2, `ProductTest` ×2, `FruitVegLabelPrintingTest` ×2, `TestScraperControllerTest` ×1).
3. `grep -rn "<script\|<style" resources/views/shop/` → nothing.
4. `git diff --stat app/ resources/views/products/ resources/views/components/product-search* resources/views/shop/stock-scan.blade.php resources/views/shop/home.blade.php` → empty.
5. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL` → prints it; `tail -6 resources/css/shop.css` shows the additions block.
6. `./vendor/bin/pint --test --dirty` → clean; `npm run build` → success.
7. Manual, signed in as an employee on `/shop/find`: search "oat"; rows with a photo show a 48 px thumbnail, rows without show the grey circle with the box icon, and all rows stay the same height; tap a row with a photo → the card shows the 128 px image beside the name; tap one without → the card shows no image and no gap; on a phone the image sits above the name. Switch to "All products" and scroll: thumbnails load lazily without the list jumping. Open the browser's network panel once: a CDN miss produces a 404 and the row falls back to the placeholder rather than a broken-image icon.

## Risks

- **CDN misses.** Supplier template URLs can 404; the `error` handler hides the image and shows the placeholder. Nothing else observes the failure.
- **Mixed content.** If the shop is served over HTTPS and a supplier URL is plain HTTP, the browser blocks it and fires `error`; same fallback.
- **Row height.** Both branches are 48 px, so rows with and without photos align; do not add margins to the image.
- **Stylesheet rule change.** The design block is still compared byte-for-byte via `head -c`; only the appended section may differ. Anyone editing `shop.css` above the marker breaks Verification 5 on purpose.

## Review

Reviewed 2026-09-23 by the Planner against `implemented.md`, the diffs, and a rerun of the checks.

Criteria:
1. Stylesheet — PASS. Six lines appended below the marker; `head -c` comparison against `docs/design/shop-mode/shop.css` reports the design block byte-identical.
2. Failed-image tracking — PASS. `failed` map, `hasImage()`, `imageFailed()`, reset on a fresh search only.
3. Row thumbnail or placeholder — PASS. Same 48 px footprint either way.
4. Card image — PASS, including the post-review change to `object-fit: contain` with the card's surface colour behind it, so whole products show rather than a crop.
5. Tests — PASS. Nine in `ShopFindProductTest`, including the permission gate on the image route both ways and the API field pin.
6. README — PASS.
7. Build and format — PASS. Built stylesheet carries `.shop-thumb`.

Verification rerun by the Planner: `--filter=Shop` 102 passed; full suite 17 failed / 502 passed, the identical 17; formatter clean; no change under `app/`, the office product views or the other shop screens. Live browser check was not repeatable this session (keystrokes did not reach the page); the owner inspected the screen directly and requested the crop fix, which is the visual evidence on record.

Deviation 1 (`@error` → `x-on:error`): accepted and important. Blade owns the `@error` directive, so the plan's markup could not compile, and `php artisan view:cache` did not catch it because it compiles without rendering. Two lessons taken into future plans: never use an Alpine `@` shorthand whose name is a Blade directive (`error`, `class`, `checked`, `disabled`, `selected`, `style`, `props`), and use a feature test that renders the page as the check for any markup step.

Post-review correction (contain instead of cover on the large image) accepted as part of this cycle. Notes for Planner acknowledged; the click-to-enlarge request becomes cycle 6.

Result: ACCEPTED. Archived by the Planner to `docs/planImp/archive/2026-09-23-shop-mode-cycle-5/`.
