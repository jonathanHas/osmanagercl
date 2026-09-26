# Shop mode cycle 14c — one thumbnail implementation — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline
HEAD: 9f7e3837
Pre-existing dirty files: cycles 14 and 14b, accepted, still uncommitted (30).

## Steps
### 1. Component: pass attributes through, fix the comment — done
Changed: `resources/views/components/shop/product-thumb.blade.php`
`{{ $attributes }}` sits after the component's own attributes, so a caller cannot
displace `x-show` / `:src` without naming them. The doc comment now says the
scope is *composed with* `productImages()` — it still said "spreads", which cycle
14b made wrong. Covered by a new rendering test (step 2).

### 2. Find product rows use the component — done
Changed: `resources/views/shop/find-product.blade.php`, `tests/Feature/Shop/ShopFindProductTest.php`
The row's img/span pair is now
`<x-shop.product-thumb x-on:mouseenter="peekAt(p, $el)" x-on:mouseleave="unpeek()" />`.
The detail card's large image and the peek panel's image are untouched, per
Out of scope.
```
$ php artisan test --filter="ShopFindProductTest|ShopViewContractTest" → 27 passed
```

### 3. Find product script composes the shared part — done
Changed: `resources/js/shop/find-product.js`
The factory is now a block that builds `const base = productImages()` per
component and returns `mix(base, { … })`, so each screen keeps its own `failed`
map. The screen's `failed: {}` and `hasImage()` are gone; `imageFailed()` remains
as an override that calls `base.imageFailed.call(this, p)` first and then takes
down the enlarged card or the peek panel.
```
hasImage(selected)          -> true   (expect true)
after imageFailed: hasImage -> false | enlarged false
failing the peeked product  -> unpeek called 1
imageFailed(null) threw     -> false
two components share failed -> false
second component failed map -> {}
reset works                 -> true   (the `this.failed = {}` reset on a new search)
```
```
$ grep -c "hasImage(p) {" resources/js/shop/find-product.js → 0
```

### 4. README, build, format — done
```
$ grep -rn "own thumbnail\|own copy" docs/design/shop-mode/README.md → nothing
$ npm run build                    → ✓ built in 8.24s
$ ./vendor/bin/pint --test --dirty → PASS 3 files
```

## Deviations

1. **One existing assertion changed, forced by step 2.** Cycle 7's
   `row_thumbnails_show_a_hover_preview_on_mouse_devices` asserted the literal
   `@mouseleave="unpeek()"`. Moving the handlers onto a component tag requires the
   `x-on:` form — the plan says so in Context — so the assertion now reads
   `x-on:mouseleave="unpeek()"`, with a comment giving the reason. It is the only
   existing assertion touched; `peekAt(p, $el)` and the rest are unchanged because
   they were already asserted without the `@`.

2. **`find-product.js` was re-indented.** Wrapping the object in
   `mix(base, { … })` put every member one level deeper. Worth flagging only
   because it makes the file's diff look larger than the change is: the real edits
   are the two imports, the factory head, the removal of `failed`/`hasImage`, and
   the `base.imageFailed.call(this, p)` line.

## Verification

1. `php artisan test --filter="ShopFindProductTest|ShopRequestsTest|ShopViewContractTest"`
   → **pass**, `39 passed (347 assertions)`.

2. `php artisan test` → **pass**: `Tests: 17 failed, 567 passed (2314 assertions)`.
   566 + 1 for the new rendering test, exactly as the plan predicts. Same 17.

3. ```
$ grep -rn "hasImage(p) {\|imageFailed(p) {" resources/js/shop/
product-images.js:14:    hasImage(p) {
product-images.js:18:    imageFailed(p) {
find-product.js:217:        imageFailed(p) {          <- the documented override

$ grep -rn 'class="shop-thumb" x-show' resources/views/shop/
(nothing — every row thumbnail goes through the component)
```
   **pass.** One `hasImage` in the codebase; one `imageFailed` plus the override.

4. Design block `cmp` identical; no `<script>`/`<style>` in shop views;
   `npm run build` succeeds. **pass**

5. Manual — **RUN, 2026-09-26, signed in, read-only.** Searched "oatly" on Find
   product: four rows with thumbnails. Then exercised the states directly so the
   result did not depend on screenshot timing:
```
resultRows            4
rowThumbPresent       true
peekOpenedOnHover     true     <- x-on:mouseenter arrived via $attributes
peekClosedAfterLeave  true
cardEnlarged          true
cardShrank            true
```
   The hover preview was also seen on screen (the Oatly carton panel beside the
   row). Console across a fresh load, a search, a hover, a select and two taps:
```
[LOG] Alpine.js started for non-Livewire pages
[LOG] Alpine.js started for non-Livewire pages
```
   — nothing else, no Alpine expression errors. Nothing was written.

   One wobble worth recording: a screenshot timed out mid-batch
   ("Page.captureScreenshot timed out … renderer may be frozen") and the page came
   back visually zoomed. It recovered on reload and produced no console errors, so
   I treated it as a capture glitch rather than a page fault — but it is why the
   checks above are JS state probes rather than more screenshots.

## Files changed

```
 M resources/views/components/shop/product-thumb.blade.php   (step 1)
 M resources/views/shop/find-product.blade.php               (step 2)
 M resources/js/shop/find-product.js                         (step 3)
 M tests/Feature/Shop/ShopFindProductTest.php                (steps 1, 2)
 M docs/design/shop-mode/README.md                           (step 4)
```
Everything else dirty is cycles 14 and 14b.

## Notes for Planner

- **The override pattern is worth naming.** `mix(base, { imageFailed(p) { base.imageFailed.call(this, p); … } })`
  is how a screen extends a shared part rather than replacing it, and it depends on
  holding `base` in the factory closure. If a third screen ever needs to extend
  `productImages()` the same way, that is the shape; a `super`-like helper would be
  over-engineering for two callers.
- **The detail card and peek images are still hand-written**, by design (Out of
  scope). Both are genuinely different — one is `shop-thumb--lg` inside a button,
  the other is inside a fixed panel — so neither is a candidate for the component
  as it stands. There is no third copy of the *row* pattern left.
- **`$attributes` on a component that also sets `class`**: the component writes
  `class="shop-thumb"` itself, so a caller passing `class` would merge rather than
  replace (Blade's attribute bag behaviour). Nothing does today; worth knowing
  before someone tries to restyle a thumbnail from a call site.
- **Nothing committed, pushed or deployed.**
