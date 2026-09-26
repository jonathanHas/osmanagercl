# Shop mode cycle 14c — One thumbnail implementation: Find product adopts the shared component

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

Two leftovers from cycles 14 and 14b. The Find product screen still carries its own copy of the thumbnail markup and the `hasImage`/`imageFailed` logic; it switches to `x-shop.product-thumb` and `productImages()` composed with `mix()`, keeping its hover preview and enlarge behaviour exactly as they are. The thumbnail component's doc comment still says "scope that spreads productImages()"; it is corrected. After this there is one thumbnail implementation in Shop mode.

## Context

- `resources/views/components/shop/product-thumb.blade.php` (cycle 14/14b): `@props(['expr' => 'p'])`, renders `<img class="shop-thumb" x-show="hasImage(expr)" :src="expr?.image_url" :alt="expr?.name" loading="lazy" decoding="async" x-on:error="imageFailed(expr)">` + the `shop-row__lead` package icon. It does not render `$attributes`, so a caller cannot add handlers to the `<img>`.
- `resources/js/shop/product-images.js`: `failed`, `hasImage(p)` (null-safe), `imageFailed(p)` (null-guarded, reassigns `failed`). `resources/js/shop/mix.js` composes parts by descriptor; later parts override earlier ones.
- `resources/views/shop/find-product.blade.php`: result rows (line ~86) use the same img/span pair **plus** `@mouseenter="peekAt(p, $el)" @mouseleave="unpeek()"` on the `<img>`; the detail card (line ~36) has a different, larger image inside `.shop-thumb-btn` (`shop-thumb--lg`, no lazy loading, `x-on:error="selected && imageFailed(selected)"`); the peek panel (line ~123) has a plain `<img>`. Only the row pair is the shared pattern.
- `resources/js/shop/find-product.js`: `failed: {}` (line 35), reset to `{}` on a new search (line 132), `hasImage(p)` (211), `imageFailed(p)` (215: reassigns `failed`, then closes the enlarged view if the failed product is the selected one and unpeeks if it is being previewed), `peekAt(p, el)` (uses `hasImage`), `unpeek()`.
- Tests: `tests/Feature/Shop/ShopFindProductTest.php` asserts `class="shop-thumb"`, `shop-thumb--lg`, `imageFailed(`, `class="shop-thumb-btn"` exactly once. `ShopRequestsTest` asserts `x-on:error="imageFailed(p)"` on the board.
- Blade component tags: attributes written as `x-on:mouseenter="…"` pass through `$attributes`; do **not** write the Alpine `@mouseenter` shorthand on a component tag (Blade's component compiler does not preserve `@`-prefixed attributes reliably), which is why the row must switch to the `x-on:` form.

## Constraints

- Do not commit, push or deploy.
- No behaviour change on Find product: hover preview, enlarge/shrink, failed-image fallback, `failed` reset on a new search, all as today.
- Design block untouched; no new stylesheet rules.

## Out of scope

- The detail card's large image and the peek panel image (not the shared pattern).
- Any other screen.

## Steps

### 1. Component: pass attributes through, fix the comment
Files: `resources/views/components/shop/product-thumb.blade.php`
What: the `<img>` gains `{{ $attributes }}` (after `x-on:error="…"`). The doc comment's first sentence becomes: "A product thumbnail with its placeholder, for any Alpine scope composed with `productImages()` (see `mix()`). `expr` names the product in that scope; extra attributes go on the `<img>`, so a caller can add hover handlers."
Check: `Blade::render('<x-shop.product-thumb x-on:mouseenter="peekAt(p, $el)" />')` contains `x-on:mouseenter="peekAt(p, $el)"` and still `x-on:error="imageFailed(p)"` (add this as a rendering test in `ShopFindProductTest`).

### 2. Find product rows use the component
Files: `resources/views/shop/find-product.blade.php`
What: replace the row's `<img class="shop-thumb" …>` + `<span class="shop-row__lead" …>` pair with `<x-shop.product-thumb x-on:mouseenter="peekAt(p, $el)" x-on:mouseleave="unpeek()" />`. The detail card image and the peek image stay as they are.
Check: `php artisan test --filter="ShopFindProductTest|ShopViewContractTest"` green; the rendered page contains `x-on:mouseenter="peekAt(p, $el)"` and exactly one `shop-thumb-btn` (existing assertion).

### 3. Find product script composes the shared part
Files: `resources/js/shop/find-product.js`
What: `import mix from './mix.js'; import productImages from './product-images.js'; const images = productImages();` at module scope is **not** right (a shared `failed` map across components); instead build the base inside the factory: `export default () => { const base = productImages(); return mix(base, { …existing members without failed/hasImage…, imageFailed(p) { base.imageFailed.call(this, p); if (p?.id === this.selected?.id) { this.enlarged = false; } if (this.peek?.product.id === p?.id) { this.unpeek(); } } }); }`. Remove the screen's own `failed: {}` and `hasImage()`; keep the `this.failed = {}` reset on a new search (it now resets the composed property, which is the same property).
Check: `node --check`; node exercise (construct, then set `selected = { id: 'a', image_url: 'x' }`, `enlarged = true`, `peek = { product: { id: 'b', image_url: 'y' } }`, stub `unpeek` to record): `hasImage(selected)` true; `imageFailed(selected)` → `hasImage(selected)` false and `enlarged` false; `imageFailed({ id: 'b' })` → `unpeek` called; `imageFailed(null)` → no throw; two constructed components do not share `failed`. `grep -c "hasImage(p) {" resources/js/shop/find-product.js` → 0.

### 4. README, build, format
Files: `docs/design/shop-mode/README.md`, all touched
What: in the `x-shop.product-thumb` bullet add "extra attributes go on the `<img>` (Find product adds its hover handlers that way)"; remove any sentence saying Find product keeps its own copy, if one exists. `npm run build`; `./vendor/bin/pint --test --dirty`.
Check: build succeeds; `grep -rn "own thumbnail\|own copy" docs/design/shop-mode/README.md` → nothing.

## Verification

1. `php artisan test --filter="ShopFindProductTest|ShopRequestsTest|ShopViewContractTest"` → green.
2. `php artisan test` → 17 failed, the identical set; passed = 566 + 1 (the rendering test).
3. `grep -rn "hasImage(p) {\|imageFailed(p) {" resources/js/shop/` → only `product-images.js`, plus the override in `find-product.js`; `grep -rn 'class="shop-thumb" x-show' resources/views/shop/` → nothing (all rows go through the component).
4. Design block `cmp` identical; contract greps clean; `npm run build` succeeds.
5. Manual, signed in, on Find product with a mouse: search a product with an image → thumbnail in the row; hover → the preview panel appears beside it and disappears on leave; tap the row → the card's large image; tap it → enlarges and shrinks; a product whose image 404s shows the package circle. On the tablet the previews never appear.

## Risks

- **`$attributes` ordering on the `<img>`**: placed after the component's own attributes, so a caller cannot accidentally override `x-show`/`:src` unless it names them.
- **`this` inside the base `imageFailed` call**: `base.imageFailed.call(this, p)` binds the component, so `this.failed` is the composed property; the node exercise proves the reassignment is seen.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end and the diff of the component, the Find product view and script, the test and the README. Reran `php artisan test`: 17 failed / 567 passed (566 + the new rendering test), the identical pre-existing set. Design block identical.

**Steps 1–4: pass.** The component passes attributes to its image after its own, the comment is right, the Find product rows go through the component with their hover handlers in `x-on:` form, the script builds its own `productImages()` base per component and overrides only `imageFailed`, the `failed` reset on a new search still works, and the README says so. Exactly one `hasImage` remains in the codebase.

**Deviations.** 1 (one cycle 7 assertion changed from `@mouseleave` to `x-on:mouseleave`): **accepted**, forced by the component tag, as the plan's Context anticipated. 2 (re-indentation): **accepted**, cosmetic.

**Notes for Planner.** The `base.method.call(this, …)` override shape: **noted** as the way to extend a shared part. The card and peek images stay hand-written: **as planned**. `class` merging on the component: **noted**. Nothing committed: correct.

**Manual check:** run read-only by the implementer with state probes and a visual confirmation of the hover panel; console clean. The one screenshot timeout was a capture glitch, not a page fault.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-14c/`.
