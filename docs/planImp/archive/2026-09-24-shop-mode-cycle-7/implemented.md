# Shop mode cycle 7 — hover preview of product photos (mouse devices) — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-24

## Baseline
HEAD: 47ee616f
Pre-existing dirty files (cycles 3–6, accepted and archived but uncommitted — not mine):
```
 M config/shop.php
 M docs/design/shop-mode/README.md
 D docs/planImp/implemented.md
 M docs/planImp/plan.md
 M resources/css/shop.css
 M resources/js/shop.js
 M routes/web.php
 M tests/Feature/Shop/ShopHomeTest.php
?? app/Http/Controllers/Shop/FindProductController.php
?? app/Http/Controllers/Shop/StockScanController.php
?? docs/planImp/archive/2026-09-23-scraping-queue-cleanup/
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-3/
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-4/
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-5/
?? docs/planImp/archive/2026-09-24-shop-mode-cycle-6/
?? resources/js/shop/
?? resources/views/components/shop/scan-input.blade.php
?? resources/views/shop/find-product.blade.php
?? resources/views/shop/stock-scan.blade.php
?? tests/Feature/Shop/ShopFindProductTest.php
?? tests/Feature/Shop/ShopStockScanTest.php
```

## Pre-flight: the plan's two structural claims

Both hold, so `position: fixed` without a teleport is safe here.

```
$ grep -n "z-index" resources/css/shop.css
.shop-actions  z-index: 20
.shop-topbar   z-index: 30   (position: sticky)
.shop-menu     z-index: 40
.shop-toasts   z-index: 50   (position: fixed)
```
`.shop-peek` at 45 therefore sits above the sticky top bar and the user menu and
below the toasts, exactly as the plan's Risks section states.

```
$ grep -n "transform:" resources/css/shop.css | grep -v none
.shop-chip__caret (rotate), .shop-tile:active, .shop-btn:active,
.shop-switch__track::after, .shop-person:active
```
None of these is an ancestor of the panel: the panel is a direct child of
`<main class="shop-page">`, and neither `.shop-page` nor the `.shop` root has a
transform. `.shop-row` has no `:active` transform either (only a background
change), so hovering or pressing a row cannot create a containing block.

## Steps
### 1. Stylesheet: the floating preview — done
Changed: `resources/css/shop.css` (four rules inside `APP ADDITIONS`, after the `.shop-thumb-btn` rules)
Check output:
```
$ grep -c "shop-peek" resources/css/shop.css
4

$ head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL
DESIGN-BLOCK-IDENTICAL
```

### 2. Behaviour: `peek` state and positioning — done
Changed: `resources/js/shop/find-product.js` (`peek` state, `canHover` getter, `peekAt()` / `unpeek()`, `unpeek()` from `select()`, `close()`, `imageFailed()` and a non-append `search()`, scroll listener in `init()`)
Check output — the plan's own check, exact match:
```
$ node -e "... d.peekAt({id:'a', image_url:'u'}, el at {left:100,right:148,top:700}) ..."
160 492
null
```

The plan gave no checks for the interesting branches, so I wrote them:
```
near right edge, flips left  -> 768   (1052 - 12 - 272)
touch device (no hover)      -> null
row with no photo            -> null
row whose image 404'd        -> null
```
And the vertical clamp at both ends, in an 800 px window:
```
thumb top   2 -> panel y 8      (floor)
thumb top  20 -> panel y 12     (tracks the thumbnail)
thumb top 790 -> panel y 492    (ceiling: 800 - 300 - 8)
```
Note for the record: my first guess was that a thumbnail at `top: 20` would clamp
to 8. It does not, and should not — the floor only binds within 16 px of the top.
The code is right; the prediction was wrong.

### 3. View: hover handlers and the panel — done
Changed: `resources/views/shop/find-product.blade.php` (`@mouseenter` / `@mouseleave` on the row thumbnail; one `.shop-peek` panel as the last child of `<main>`)
Check output:
```
$ php artisan test --filter="ShopFindProductTest|ShopViewContractTest"
  Tests:    14 passed (87 assertions)
```
The template renders, so the Blade-directive trap is avoided again: `@mouseenter`
and `@mouseleave` are not Blade directives, and the panel keeps `:class` / `:style`
bindings rather than a `<style>` block.

### 4. Tests — done
Changed: `tests/Feature/Shop/ShopFindProductTest.php` (one test added)
Check output:
```
$ php artisan test --filter=ShopFindProductTest
  ✓ employee can open the find product screen
  ✓ barista is forbidden
  ✓ guest is sent to login
  ✓ home shows the find product tile to product viewers
  ✓ employee can search the product api
  ✓ employee without products view cannot search
  ✓ screen renders row thumbnails and the card image
  ✓ search rows carry an image url field
  ✓ card image is a toggle button
  ✓ row thumbnails show a hover preview on mouse devices
  ✓ barista cannot fetch product images

  Tests:    11 passed (46 assertions)
```
11 passed, as the plan expects.

### 5. README — done
Changed: `docs/design/shop-mode/README.md` (one line under "Component API in the app")
Check output:
```
$ grep -c "shop-peek" docs/design/shop-mode/README.md
1
```
I also recorded the `transform` dependency in that line, since the plan's Risks
section asked for the dependency to be written down somewhere durable.

### 6. Build and format — done
Changed: all touched files
Check output:
```
$ npm run build
✓ built in 6.94s

$ ./vendor/bin/pint --dirty     → PASS 7 files
$ ./vendor/bin/pint --test --dirty → PASS 7 files

$ grep -l "shop-peek" public/build/assets/shop-*.css
public/build/assets/shop-DKn6Ulx2.css
```
All four rules survive minification, and `.is-open` stays inside the gate:
```
@media (hover: hover) and (pointer: fine){.shop-peek.is-open{display:block}
```

## Deviations

None. Every step was implementable as written.

Three additions inside the plan's intent, recorded for transparency:

1. **Named constants instead of inline numbers** in `find-product.js`:
   `PEEK_W = 272`, `PEEK_H = 300`, `PEEK_GAP = 12`, `PEEK_EDGE = 8`. The plan gave
   these as literals in prose; the arithmetic reads better with the panel width
   named, and 272 now appears once rather than twice. Values and behaviour are
   exactly as specified — the plan's own check passes unchanged.
2. **Extra branch checks on step 2** (output above). The plan checked only the
   happy path; I also verified the right-edge flip, the touch gate, a row with no
   photo, a row whose image already failed, and the vertical clamp at both ends.
3. **A fourth assertion in the new test**: `substr_count(..., 'class="shop-peek"') === 1`.
   This was in the plan's step 4 description, so strictly not an addition — noting
   it because it is what pins "one shared panel", not one per row.

## Verification

1. `php artisan test --filter=Shop` → **pass**, `Tests: 104 passed (254 assertions)`,
   with 11 in `ShopFindProductTest` as the plan expects.

2. `php artisan test` → **pass against baseline**: `Tests: 17 failed, 504 passed (1874 assertions)`.
   Cycle 6 ended at 17 failed / 503 passed; the one extra pass is the one new
   test. The 17 failures are the identical pre-existing set:
```
UdeaScrapingServiceTest      ×7  (BadMethodCallException ×6, InvalidCountException ×1)
CashReconciliationTest       ×3  (BadMethodCallException)
WasteLogTest                 ×2
ProductTest                  ×2
FruitVegLabelPrintingTest    ×2
TestScraperControllerTest    ×1
```

3. `grep -rn "<script\|<style\|x-teleport" resources/views/shop/` → no output. **pass**
   No teleport: the panel is a child of `<main class="shop-page">`, inside `.shop`.

4. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL`
   → `DESIGN-BLOCK-IDENTICAL`. **pass**

5. `git diff --stat app/ resources/views/products/ resources/views/components/product-search* resources/views/shop/stock-scan.blade.php resources/views/shop/home.blade.php`
   → empty. **pass** — the office component this borrows its behaviour from is untouched.

6. `./vendor/bin/pint --test --dirty` → `PASS 7 files`; `npm run build` → `✓ built in 6.94s`. **pass**

7. Manual on the till PC and tablet — **not run by me.** No browser session here,
   so none of the actual hover behaviour has been observed: not the panel
   appearing beside the thumbnail, not the flip near the right edge, not the
   dismiss on wheel-scroll, and not the absence of the panel on the tablet. This
   is the cycle where that gap matters most, because every visible effect is
   pointer-driven and the automated tests can only prove the markup, the CSS and
   the positioning arithmetic. **Please walk through the plan's item 7 before
   accepting.**
   What is covered automatically: the panel renders exactly once with its
   bindings, the handlers are on the thumbnail, the four CSS rules reach the
   built file with `.is-open` inside the hover gate, and `peekAt()` returns the
   right coordinates and refuses to open on touch, on a photo-less row, or on a
   row whose image already failed.

## Files changed

`git status --short` at the end. `[pre]` marks files already dirty at baseline
(cycles 3–6, accepted and archived but never committed).

```
 M config/shop.php                                            [pre]
 M docs/design/shop-mode/README.md                            mine (also [pre])
 M docs/planImp/implemented.md                                mine
 M docs/planImp/plan.md                                       [pre]
 M resources/css/shop.css                                     mine (also [pre])
 M resources/js/shop.js                                       [pre]
 M routes/web.php                                             [pre]
 M tests/Feature/Shop/ShopHomeTest.php                        [pre]
?? app/Http/Controllers/Shop/FindProductController.php        [pre]
?? app/Http/Controllers/Shop/StockScanController.php          [pre]
?? docs/planImp/archive/2026-09-23-scraping-queue-cleanup/    [pre]
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-3/         [pre]
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-4/         [pre]
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-5/         [pre]
?? docs/planImp/archive/2026-09-24-shop-mode-cycle-6/         [pre]
?? resources/js/shop/                                         mine (find-product.js; also [pre])
?? resources/views/components/shop/scan-input.blade.php       [pre]
?? resources/views/shop/find-product.blade.php                mine (also [pre])
?? resources/views/shop/stock-scan.blade.php                  [pre]
?? tests/Feature/Shop/ShopFindProductTest.php                 mine (also [pre])
?? tests/Feature/Shop/ShopStockScanTest.php                   [pre]
```

My changes this cycle — five files, no new files:
- `resources/css/shop.css` — 4 rules appended inside `APP ADDITIONS`
- `resources/js/shop/find-product.js` — `peek` state, `canHover`, `peekAt()`,
  `unpeek()`, four `unpeek()` call sites, scroll listener
- `resources/views/shop/find-product.blade.php` — two handlers on the row
  thumbnail, one `.shop-peek` panel
- `tests/Feature/Shop/ShopFindProductTest.php` — one test added
- `docs/design/shop-mode/README.md` — one line added

`public/build/` is gitignored, so the rebuilt assets do not appear.

## Notes for Planner

- **The scroll listener is never removed.** `init()` adds a `scroll` listener on
  `window` and nothing takes it off. On this screen that is harmless — the Alpine
  component lives as long as the page — but it is the first `window` listener
  added by a shop page module, and the pattern will leak if it is copied onto a
  component that is created and destroyed repeatedly. If you want it tidy,
  Alpine's `destroy()` hook is the place.
- **Wheel-scroll dismisses, keyboard-scroll may not.** The listener catches any
  scroll event on `window`, but a scroll inside a nested scrolling element would
  not bubble to it. Nothing on this screen scrolls independently today, so this is
  a note rather than a defect.
- **The panel is `aria-hidden="true"`** and has no keyboard trigger, per Out of
  scope. A keyboard-only user therefore has no way to see a larger photo from the
  list; they can still select the row and use cycle 6's toggle on the card. Worth
  a decision if accessibility is on the roadmap.
- **`PEEK_H = 300` is an estimate**, as the plan says. The real panel is
  256 (image) + 8 (padding top) + 8 (gap) + ~22 (caption) + 8 (padding bottom)
  ≈ 302, plus 2 for the border — so the clamp is about 2–4 px optimistic and a
  panel pinned to the bottom edge could overhang by that much. Harmless, but if
  you want it exact, `320` would be safely conservative.
- **Nothing committed, pushed or deployed**, per the plan's Constraints.
