# Shop mode cycle 7 — hover preview of product photos (mouse devices)

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-24

## Goal

On the till PC, resting the mouse on a result row's thumbnail shows a larger photo beside it, like the office search page does, so staff can check a product without opening it. On touch devices nothing changes: there is no hover, and the tap-to-enlarge on the detail card (cycle 6) already covers them. The preview floats next to the thumbnail, never covers the row being read, disappears on mouse-out, scroll or selection, and is built from the shop design tokens rather than the office component's Tailwind overlay.

## Context

Baseline: cycle 6 accepted and archived (`docs/planImp/archive/2026-09-24-shop-mode-cycle-6/`); cycles 3–6 are uncommitted. Record `git status --short` at the start.

**Owner (2026-09-24):** "we still don't have an on hover larger image view like on the old page, is this difficult to implement?" It is not; this plan is the answer.

**What exists.** `resources/views/shop/find-product.blade.php` rows (lines ~84–96): `<button class="shop-row" …>` containing `<img class="shop-thumb" x-show="hasImage(p)" :src="p.image_url" …>`, a placeholder `<span class="shop-row__lead">`, the text, the aside and a chevron. `resources/js/shop/find-product.js` (`Alpine.data('shopFindProduct')`) has `results`, `selected`, `hasImage(p)`, `select(p)`, `close()`, `enlarged` / `toggleImage()`. `resources/css/shop.css` ends with the `APP ADDITIONS` section (`.shop-thumb`, `.shop-thumb--lg`, `.shop-thumb-btn` rules); the design block above the marker must stay byte-identical to `docs/design/shop-mode/shop.css`.

**How the office page does it.** `resources/views/components/product-search/thumb.blade.php`: on `mouseenter` of the thumbnail it shows a teleported, `position: fixed`, 256 px wide panel (`w-64`, image `max-h-80 object-contain`, a dark caption with the name) at coordinates computed from the thumbnail's bounding box; on `mouseleave` it hides; a tap mode exists for touch. Shop mode cannot copy that markup (utility classes are banned in shop views, and `x-teleport="body"` would leave `.shop` where no shop style applies), but the behaviour is the same and `position: fixed` works without teleport here: no ancestor of `.shop-list` sets a `transform`, so a fixed element inside the page positions against the viewport.

**Tokens to use:** `--shop-surface`, `--shop-line`, `--shop-radius-lg`, `--shop-shadow-lg`, `--shop-space-2`, `--shop-fs-sm`, `--shop-fw-bold`, `--shop-ink`. Media query gate: `@media (hover: hover) and (pointer: fine)` so the preview never renders on touch screens.

**Blade gotcha:** `@click`, `@mouseenter`, `@mouseleave`, `@scroll` are safe Alpine shorthands; never use `@error`/`@class`/`@checked`/`@disabled`/`@selected`/`@style`/`@props` (Blade directives). Use a rendering feature test as the check for markup steps.

## Constraints

- Do not commit, push or deploy.
- Only `resources/css/shop.css` (inside `APP ADDITIONS`), the Find product view and JS, the README and the test file change.
- Mouse devices only: the panel is hidden by the media query on touch, and the JavaScript also checks `matchMedia('(hover: hover) and (pointer: fine)')` so no work is done on tablets.
- The preview is non-interactive (`pointer-events: none`), never captures the mouse, and a tap/click on the row still selects the product exactly as now.
- No teleport, no scrim, no change to the detail card's tap-to-enlarge.
- Design block of `shop.css` stays byte-identical.

## Out of scope

- Hover preview on the detail card image (it already enlarges on tap/click).
- A long-press preview on touch devices.
- Keyboard-triggered preview (focus on a row could show it; not asked for).
- Any other screen.

## Steps

### 1. Stylesheet: the floating preview
Files: `resources/css/shop.css`
What: append inside `APP ADDITIONS`, after the `.shop-thumb-btn` rules:
```css
.shop-peek { display: none; position: fixed; z-index: 45; width: 272px; padding: var(--shop-space-2); border-radius: var(--shop-radius-lg); background: var(--shop-surface); border: 1px solid var(--shop-line); box-shadow: var(--shop-shadow-lg); pointer-events: none; }
.shop-peek img { display: block; width: 256px; height: 256px; object-fit: contain; border-radius: var(--shop-radius-md); background: var(--shop-surface); }
.shop-peek__name { margin-top: var(--shop-space-2); font-size: var(--shop-fs-sm); font-weight: var(--shop-fw-bold); color: var(--shop-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
@media (hover: hover) and (pointer: fine) { .shop-peek.is-open { display: block; } }
```
Only `.is-open` under a hover-capable, fine-pointer device shows it; touch devices never see it even if the state flips.
Check: `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL` prints it; `grep -c "shop-peek" resources/css/shop.css` → 4.

### 2. Behaviour: `peek` state and positioning
Files: `resources/js/shop/find-product.js`
What: add state `peek: null` (`{ product, x, y }` when open) and a getter `canHover` → `window.matchMedia?.('(hover: hover) and (pointer: fine)').matches ?? false`. Methods:
- `peekAt(p, el)`: if `! this.canHover || ! this.hasImage(p)` return; `const r = el.getBoundingClientRect()`; panel width 272, height estimate 300 (256 image + caption + padding); `x = r.right + 12`; if `x + 272 > window.innerWidth - 8` then `x = r.left - 12 - 272`; if `x < 8` then `x = 8`; `y = Math.min(Math.max(8, r.top - 8), window.innerHeight - 300 - 8)`; `this.peek = { product: p, x, y }`.
- `unpeek()`: `this.peek = null`.
- In `select(p)` and `close()` call `unpeek()`; in `imageFailed(p)` if `this.peek?.product.id === p.id` call `unpeek()`; at the start of a non-append `search()` call `unpeek()`.
- `init()`: additionally `window.addEventListener('scroll', () => this.unpeek(), { passive: true })`.
Check: `node -e "import('./resources/js/shop/find-product.js').then(m => { const d = m.default(); d.failed = {}; global.window = { innerWidth: 1280, innerHeight: 800, matchMedia: () => ({ matches: true }) }; const el = { getBoundingClientRect: () => ({ left: 100, right: 148, top: 700 }) }; d.peekAt({ id: 'a', image_url: 'u' }, el); console.log(d.peek.x, d.peek.y); d.unpeek(); console.log(d.peek) })"` prints `160 492` then `null` (y is clamped so the 300 px panel stays above the 800 px viewport bottom).

### 3. View: hover handlers on the row thumbnail and one panel
Files: `resources/views/shop/find-product.blade.php`
What: on the row's `<img class="shop-thumb" …>` add `@mouseenter="peekAt(p, $el)" @mouseleave="unpeek()"`. Add one panel as the last child of `<main class="shop-page …">` (inside `.shop`, outside the list):
```blade
<div class="shop-peek" :class="{ 'is-open': peek }" :style="peek ? 'left:' + peek.x + 'px; top:' + peek.y + 'px' : ''" aria-hidden="true">
    <img :src="peek?.product.image_url" :alt="peek?.product.name || ''" decoding="async">
    <div class="shop-peek__name" x-text="peek?.product.name"></div>
</div>
```
`:style` is a computed position, not a style block; the contract test checks `<style` tags and `class=""` tokens, neither of which this touches. Do not use `x-show` on the panel: the media query in step 1 is what decides visibility, so `is-open` must be a class.
Check: `php artisan test --filter="ShopFindProductTest|ShopViewContractTest"` green (rendering test proves the template compiles).

### 4. Tests
Files: `tests/Feature/Shop/ShopFindProductTest.php`
What: add `row_thumbnails_show_a_hover_preview_on_mouse_devices`: employee with `products.view` → page contains `class="shop-peek"`, `peekAt(p, $el)`, `@mouseleave="unpeek()"` (assert the literal attribute text, which Blade leaves untouched) and exactly one `shop-peek` panel (`substr_count($content, 'class="shop-peek"') === 1`).
Check: `php artisan test --filter=ShopFindProductTest` → 11 passed.

### 5. README
Files: `docs/design/shop-mode/README.md`
What: one line under "Component API in the app": `.shop-peek` is the hover preview for row photos, shown only under `(hover: hover) and (pointer: fine)`, positioned fixed beside the thumbnail, non-interactive.
Check: `grep -c "shop-peek" docs/design/shop-mode/README.md` → 1.

### 6. Build and format
Files: all touched
What: `npm run build`; `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean; `grep -l "shop-peek" public/build/assets/shop-*.css` finds the stylesheet.

## Verification

1. `php artisan test --filter=Shop` → all green (11 in `ShopFindProductTest`).
2. `php artisan test` → 17 failed / N passed, the identical 17 (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `WasteLogTest` ×2, `ProductTest` ×2, `FruitVegLabelPrintingTest` ×2, `TestScraperControllerTest` ×1).
3. `grep -rn "<script\|<style\|x-teleport" resources/views/shop/` → nothing.
4. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL` → prints it.
5. `git diff --stat app/ resources/views/products/ resources/views/components/product-search* resources/views/shop/stock-scan.blade.php resources/views/shop/home.blade.php` → empty.
6. `./vendor/bin/pint --test --dirty` → clean; `npm run build` → success.
7. Manual on the till PC (mouse), signed in as an employee on `/shop/find`: search "oat"; rest the pointer on a row's thumbnail → a 256 px photo with the product name appears to the right of it (to the left when near the right edge), and never below the bottom of the window; move off → it disappears; scroll with the wheel → it disappears; click the row → the card opens and no preview lingers. Rows without a photo show nothing on hover. On the tablet: no preview appears at any point, and tapping rows behaves as before.

## Risks

- **Fixed positioning and transforms.** A `transform` on any ancestor turns `position: fixed` into a containing-block trap. No ancestor of the list has one today (`.shop-tile:active` does, but tiles are not on this screen). If a future design change adds one to `.shop-page`, the preview will mis-position; the README line records the dependency.
- **Lazy row images.** The panel loads the same URL as the thumbnail, which the browser already has cached once the row is visible, so the preview appears instantly. A CDN miss on the row has already hidden the thumbnail, so there is nothing to hover.
- **Hover on hybrid devices.** A laptop with a touchscreen reports `hover: hover` and `pointer: fine` for its mouse; the preview shows on mouse hover and not on touch, which is the intended split.
- **Sticky top bar.** The panel's `z-index: 45` sits above the top bar (30) and below the toasts (50), so it is never hidden under the header when a row is near the top.

## Review

Reviewed 2026-09-24 by the Planner against `implemented.md`, the diffs and a rerun of the checks.

Criteria 1–6: all PASS. Four rules inside `APP ADDITIONS` with the design block byte-identical; `peek` state, `canHover` gate, `peekAt()` with right-edge flip and vertical clamp, `unpeek()` on select, close, own-image failure, fresh search and scroll; handlers on the row thumbnail only; one shared panel as the last child of `<main>`; new test (11 in the class) pinning a single panel; README line including the transform dependency; build and format clean, and the media-query gate survives minification.

Verification rerun by the Planner: `--filter=Shop` 104 passed; full suite 17 failed / 504 passed, the identical 17; no `<script>`, `<style>` or `x-teleport` in shop views; nothing changed under `app/` or the office product views. A live hover in the browser could not be driven from this session (keystrokes again did not reach the page); the implementer's branch checks (edge flip, touch gate, no-photo row, failed image, clamp at both ends) stand as evidence, and the owner's own try on the till PC is the final word.

Deviations: none. Named constants and the extra branch checks are welcome.

Result: ACCEPTED. Archived by the Planner to `docs/planImp/archive/2026-09-24-shop-mode-cycle-7/`. Find product is now complete for v1: search, stock, detail card, images, tap-to-enlarge, hover preview. Next planned cycle: delivery receiving on the legacy flow (design screens 04–06 rebuilt on `delivery-legacy.*`).
