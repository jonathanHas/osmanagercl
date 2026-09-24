# Shop mode cycle 6 — tap to see the full product image

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-23

## Goal

On the Find product detail card, tapping the product photo shows it at full size so a customer can recognise the item; tapping again (or the close button) returns it to the small size. It works the same with a finger on the tablet and a mouse on the till PC, needs no overlay, scrim or hover, and never covers the price or stock. Nothing else changes.

## Context

Baseline: cycle 5 accepted and archived (`docs/planImp/archive/2026-09-23-shop-mode-cycle-5/`); cycles 3–5 are uncommitted in the working tree. Record `git status --short` at the start.

**Owner request (2026-09-23, relayed by the implementer in cycle 5):** "a nice feature on the original search images is a full size image of the product on hover — can you add this? … when the user clicks on a product it might be good to also show the full image." Hover is a mouse-only idea; Shop mode is touch-first, so the tap is the primary gesture and the mouse gets the same tap.

**What exists.** `resources/views/shop/find-product.blade.php`, detail card section (around lines 30–52): a `shop-inline` holding `<img class="shop-thumb shop-thumb--lg" x-show="selected && hasImage(selected)" :src="selected?.image_url" :alt="selected?.name" decoding="async" x-on:error="selected && imageFailed(selected)">` followed by the name/price/pills stack. `resources/js/shop/find-product.js` (`Alpine.data('shopFindProduct')`): `select(p)` sets `selected` and nudges the card into view; `close()` clears it and refocuses the input; `hasImage()` / `imageFailed()` from cycle 5. Stylesheet `resources/css/shop.css` ends with the `APP ADDITIONS` section: `.shop-thumb` (48 px, cover) and `.shop-thumb--lg` (128 px, `object-fit: contain`, surface background). The design block above the marker must stay byte-identical to `docs/design/shop-mode/shop.css` (check in Verification 4).

**Why in place, not an overlay.** The design system has no lightbox pattern (the `--shop-scrim` token exists but nothing uses it); `x-teleport="body"` would move markup outside `.shop` where no shop style applies; and `ShopViewContractTest` forbids utility classes, so the office component's Tailwind overlay cannot be ported. Growing the image inside the card avoids all three and keeps the price visible beside or below it.

**Blade gotcha carried from cycle 5:** never use an Alpine `@` shorthand whose name is a Blade directive (`@error`, `@class`, `@checked`, `@disabled`, `@selected`, `@style`, `@props`). `@click` and `@keydown` are safe. Use a rendering feature test as the check for markup steps; `php artisan view:cache` does not execute templates.

## Constraints

- Do not commit, push or deploy.
- Only `resources/css/shop.css` (append inside the `APP ADDITIONS` section), the Find product view and JS, `docs/design/shop-mode/README.md` and the test file change.
- No overlay, teleport, scrim or hover-only behaviour. The enlarged image stays inside the card and inside `.shop`.
- Design block of `shop.css` stays byte-identical; the check proves it.
- Contract test rules apply: only `shop-*`/`is-*` names in `:class` bindings; no `<script>`/`<style>` in views.

## Out of scope

- Enlarging row thumbnails in the list (a tap on a row selects the product; the card is the place to look closer).
- Pinch-zoom, swipe, or multiple images.
- Images on the Stock scan screen.

## Steps

### 1. Stylesheet: the full-size state
Files: `resources/css/shop.css`
What: inside the `APP ADDITIONS` section, after `.shop-thumb--lg`, add:
```css
.shop-thumb-btn { display: block; flex: none; padding: 0; border: 0; background: none; border-radius: var(--shop-radius-md); cursor: zoom-in; }
.shop-thumb-btn.is-open { cursor: zoom-out; width: 100%; }
.shop-thumb-btn.is-open .shop-thumb--lg { width: 100%; height: auto; max-height: 60vh; }
```
The button wraps the image so the tap target is at least the 128 px image and keyboard users can reach it. When open, the button takes the card's full width and the image grows to fit, keeping its aspect ratio (`contain` is already set on `--lg`), capped at 60 % of the viewport height so the price and facts remain reachable with one scroll.
Check: `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL` prints it; `grep -c "shop-thumb-btn" resources/css/shop.css` → 3.

### 2. Behaviour: `enlarged` state
Files: `resources/js/shop/find-product.js`
What: add state `enlarged: false`; method `toggleImage()` → `this.enlarged = ! this.enlarged`; set `enlarged = false` at the start of `select(p)` and in `close()`; also in `imageFailed(p)` when `p.id === this.selected?.id` (an image that fails while enlarged must not leave an empty full-width button). Nothing else changes.
Check: `node -e "import('./resources/js/shop/find-product.js').then(m => { const d = m.default(); d.\$refs = {}; d.\$nextTick = f => f(); d.toggleImage(); console.log(d.enlarged); d.select({id:'a'}); console.log(d.enlarged); d.toggleImage(); d.close(); console.log(d.enlarged) })"` prints `true`, `false`, `false`.

### 3. View: wrap the card image in a button
Files: `resources/views/shop/find-product.blade.php`
What: replace the card's `<img class="shop-thumb shop-thumb--lg" …>` with:
```blade
<button class="shop-thumb-btn" type="button" x-show="selected && hasImage(selected)" :class="{ 'is-open': enlarged }" :aria-pressed="enlarged" :aria-label="enlarged ? 'Shrink image' : 'Show full image'" @click="toggleImage()">
    <img class="shop-thumb shop-thumb--lg" :src="selected?.image_url" :alt="selected?.name" decoding="async" x-on:error="selected && imageFailed(selected)">
</button>
```
Keep the surrounding `shop-inline` and the name/price stack exactly as they are; when the button is open it is 100 % wide, so `shop-inline`'s wrap puts the name and price below the image.
Check: `php artisan test --filter="ShopFindProductTest|ShopViewContractTest"` green (the rendering test in step 4 proves the template compiles).

### 4. Tests
Files: `tests/Feature/Shop/ShopFindProductTest.php`
What: add `card_image_is_a_toggle_button`: employee with `products.view` → page contains `class="shop-thumb-btn"`, `toggleImage()`, `'is-open': enlarged` and `aria-pressed`. Adjust `screen_renders_row_thumbnails_and_the_card_image` only if its `shop-thumb--lg` assertion needs the new nesting (it should still find the class).
Check: `php artisan test --filter=ShopFindProductTest` → 10 passed.

### 5. README
Files: `docs/design/shop-mode/README.md`
What: extend the `.shop-thumb` line under "Component API in the app": the card image sits in a `.shop-thumb-btn`; `.is-open` grows it to the card width (max 60 vh), toggled by tap; no overlay by design.
Check: `grep -c "shop-thumb-btn" docs/design/shop-mode/README.md` → 1.

### 6. Build and format
Files: all touched
What: `npm run build`; `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean; `grep -l "shop-thumb-btn" public/build/assets/shop-*.css` finds the stylesheet.

## Verification

1. `php artisan test --filter=Shop` → all green (10 in `ShopFindProductTest`).
2. `php artisan test` → 17 failed / N passed, the identical 17 (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `WasteLogTest` ×2, `ProductTest` ×2, `FruitVegLabelPrintingTest` ×2, `TestScraperControllerTest` ×1).
3. `grep -rn "<script\|<style\|x-teleport" resources/views/shop/` → nothing.
4. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL` → prints it.
5. `git diff --stat app/ resources/views/products/ resources/views/shop/stock-scan.blade.php resources/views/shop/home.blade.php` → empty.
6. `./vendor/bin/pint --test --dirty` → clean; `npm run build` → success.
7. Manual, signed in as an employee on `/shop/find`: search "oat", open a product with a photo; the 128 px image shows a zoom-in cursor on the PC; tap it → it fills the card width with the name and price below, no crop, and the page is still scrollable; tap again → back to 128 px beside the name; open a different product → it starts small; close the card and reopen → small. On the tablet the same with a finger. A product without a photo shows no button.

## Risks

- **Very tall images.** `max-height: 60vh` with `contain` prevents a portrait photo from pushing the price off screen; the card still scrolls with the page.
- **Nested interactive elements.** The button sits inside the card, not inside a row button, so there is no nested-button problem. Do not add this to the list rows.
- **`aria-pressed` on a toggle** is the right semantics; keep `type="button"` so Enter does not submit anything.
- **Design block drift.** Only the `APP ADDITIONS` section changes; Verification 4 proves the rest is untouched.

## Review

Reviewed 2026-09-24 by the Planner against `implemented.md`, the diffs and a rerun of the checks.

Criteria 1–6: all PASS. Three rules appended inside `APP ADDITIONS` with the design block still byte-identical; `enlarged` state reset on select, close and the selected product's own image failure; the card image wrapped in a `type="button"` toggle with `aria-pressed`; one new test (10 in the class) plus an extra assertion pinning that only one such button exists on the page; README line; build and format clean.

Verification rerun by the Planner: `--filter=Shop` 103 passed; full suite 17 failed / 503 passed, the identical 17; no `<script>`, `<style>` or `x-teleport` in the shop views; built stylesheet carries `.shop-thumb-btn`.

Deviations: none. The two additions (a check for the failure reset, an assertion that the row thumbnails are not buttons) are welcome.

Result: ACCEPTED. Archived by the Planner to `docs/planImp/archive/2026-09-24-shop-mode-cycle-6/`. Next: cycle 7, hover preview of row thumbnails on mouse devices (owner question 2026-09-24).
