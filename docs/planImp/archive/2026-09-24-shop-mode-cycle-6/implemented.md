# Shop mode cycle 6 — tap to see the full product image — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-24

## Baseline
HEAD: 47ee616f
Pre-existing dirty files (cycles 3–5, accepted and archived but uncommitted — not mine):
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
?? resources/js/shop/
?? resources/views/components/shop/scan-input.blade.php
?? resources/views/shop/find-product.blade.php
?? resources/views/shop/stock-scan.blade.php
?? tests/Feature/Shop/ShopFindProductTest.php
?? tests/Feature/Shop/ShopStockScanTest.php
```
Note: the plan is dated 2026-09-23; this session ran on 2026-09-24.

## Steps
### 1. Stylesheet: the full-size state — done
Changed: `resources/css/shop.css` (three rules inside the `APP ADDITIONS` section, after `.shop-thumb--lg`)
Check output:
```
$ grep -c "shop-thumb-btn" resources/css/shop.css
3

$ head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL
DESIGN-BLOCK-IDENTICAL

$ tail -5 resources/css/shop.css
.shop-thumb { display: block; flex: none; width: 48px; height: 48px; border-radius: var(--shop-radius-sm); background: var(--shop-surface-2); border: 1px solid var(--shop-line); object-fit: cover; }
.shop-thumb--lg { width: 128px; height: 128px; border-radius: var(--shop-radius-md); object-fit: contain; background: var(--shop-surface); }
.shop-thumb-btn { display: block; flex: none; padding: 0; border: 0; background: none; border-radius: var(--shop-radius-md); cursor: zoom-in; }
.shop-thumb-btn.is-open { cursor: zoom-out; width: 100%; }
.shop-thumb-btn.is-open .shop-thumb--lg { width: 100%; height: auto; max-height: 60vh; }
/* === APP ADDITIONS END === */
```

### 2. Behaviour: `enlarged` state — done
Changed: `resources/js/shop/find-product.js` (`enlarged: false`, `toggleImage()`, reset in `select()`, `close()` and `imageFailed()`)
Check output:
```
$ node -e "... d.toggleImage(); d.enlarged; d.select({id:'a'}); d.enlarged; d.toggleImage(); d.close(); d.enlarged"
true
false
false
```
Matches the plan's expected `true`, `false`, `false`.

The plan specified the `imageFailed()` reset but gave no check for it, so I added one:
```
$ node -e "... select a, toggleImage, imageFailed(a) / imageFailed(other) ..."
enlarged before failure: true
enlarged after its own image fails: false
enlarged after an unrelated row fails: true
```
Only the selected product's own failure collapses the button; a row further down
the list failing leaves the open card alone, which is the intent.

### 3. View: wrap the card image in a button — done
Changed: `resources/views/shop/find-product.blade.php` (card `<img>` wrapped in `.shop-thumb-btn`; `shop-inline` and the name/price stack untouched)
Check output:
```
$ php artisan test --filter="ShopFindProductTest|ShopViewContractTest"
   PASS  Tests\Feature\Shop\ShopFindProductTest        (9 passed at this point)
   PASS  Tests\Feature\Shop\ShopViewContractTest       (4 passed)
  Tests:    13 passed (77 assertions)
```
The template renders, so the Blade-directive trap from cycle 5 is not repeated
(`@click` is safe; the image keeps `x-on:error`).

### 4. Tests — done
Changed: `tests/Feature/Shop/ShopFindProductTest.php` (one test added)
Check output:
```
$ php artisan test --filter=ShopFindProductTest
   PASS  Tests\Feature\Shop\ShopFindProductTest
  ✓ employee can open the find product screen
  ✓ barista is forbidden
  ✓ guest is sent to login
  ✓ home shows the find product tile to product viewers
  ✓ employee can search the product api
  ✓ employee without products view cannot search
  ✓ screen renders row thumbnails and the card image
  ✓ search rows carry an image url field
  ✓ card image is a toggle button
  ✓ barista cannot fetch product images

  Tests:    10 passed (41 assertions)
```
10 passed, as the plan expects. `screen_renders_row_thumbnails_and_the_card_image`
needed **no** adjustment: its `shop-thumb--lg` assertion still finds the class
through the new nesting.

### 5. README — done
Changed: `docs/design/shop-mode/README.md` (one line added under "Component API in the app")
Check output:
```
$ grep -c "shop-thumb-btn" docs/design/shop-mode/README.md
1
```

### 6. Build and format — done
Changed: all touched files
Check output:
```
$ npm run build
✓ built in 7.03s

$ ./vendor/bin/pint --dirty
  PASS   7 files

$ ./vendor/bin/pint --test --dirty
  PASS   7 files

$ grep -l "shop-thumb-btn" public/build/assets/shop-*.css
public/build/assets/shop-C2h1BVrr.css
```

## Deviations

None. Every step was implementable as written.

Two small additions inside the plan's own intent, recorded for transparency:

1. **An extra check on step 2.** The plan specified the `imageFailed()` reset but
   gave no check for it, so I wrote one (output under Step 2). It confirms that
   only the *selected* product's failure collapses the button — a row further
   down the list failing leaves the open card alone.
2. **A fourth assertion in the new test.** Beyond the plan's four strings I added
   `assertSame(1, substr_count($content, 'shop-thumb-btn'))`, which pins the
   plan's own Risk note ("Do not add this to the list rows") and the Out of scope
   line about row thumbnails. If anyone later wraps the row image in the same
   button, this fails.

## Verification

1. `php artisan test --filter=Shop` → **pass**, `Tests: 103 passed (245 assertions)`,
   with 10 in `ShopFindProductTest` as the plan expects.

2. `php artisan test` → **pass against baseline**: `Tests: 17 failed, 503 passed (1865 assertions)`.
   Cycle 5 ended at 17 failed / 502 passed; the one extra pass is the one new
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
   No overlay, no teleport; the enlarged image stays inside `.shop`.

4. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL`
   → `DESIGN-BLOCK-IDENTICAL`. **pass** — only the `APP ADDITIONS` section grew.

5. `git diff --stat app/ resources/views/products/ resources/views/shop/stock-scan.blade.php resources/views/shop/home.blade.php`
   → empty. **pass**

6. `./vendor/bin/pint --test --dirty` → `PASS 7 files`; `npm run build` → `✓ built in 7.03s`. **pass**

7. Manual checks on `/shop/find` — **not run by me.** No browser session here, so
   the zoom-in/zoom-out cursor, the actual growth to card width, the 60 vh cap on
   a portrait photo, the reflow of name and price below the image, and the finger
   version on the tablet are all unobserved. What the automated checks cover: the
   button renders with its bindings, the class appears exactly once, the state
   machine behaves (`toggleImage` / `select` / `close` / `imageFailed`), and the
   CSS reaches the built stylesheet.

## Files changed

`git status --short` at the end. `[pre]` marks files already dirty at baseline
(cycles 3–5, accepted and archived but never committed).

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
?? resources/js/shop/                                         mine (find-product.js; also [pre])
?? resources/views/components/shop/scan-input.blade.php       [pre]
?? resources/views/shop/find-product.blade.php                mine (also [pre])
?? resources/views/shop/stock-scan.blade.php                  [pre]
?? tests/Feature/Shop/ShopFindProductTest.php                 mine (also [pre])
?? tests/Feature/Shop/ShopStockScanTest.php                   [pre]
```

My changes this cycle — four files, no new files:
- `resources/css/shop.css` — 3 rules appended inside `APP ADDITIONS` (+10 lines)
- `resources/js/shop/find-product.js` — `enlarged` state, `toggleImage()`, resets
  in `select()`, `close()` and `imageFailed()`
- `resources/views/shop/find-product.blade.php` — card image wrapped in `.shop-thumb-btn`
- `tests/Feature/Shop/ShopFindProductTest.php` — one test added
- `docs/design/shop-mode/README.md` — one line added

`public/build/` is gitignored, so the rebuilt assets do not appear.

## Notes for Planner

- **No keyboard dismiss.** The plan did not ask for one and I did not add it, but
  the enlarged image can only be closed by tapping it again or closing the card.
  `x-on:keydown.escape` on the button would only fire while it holds focus, so a
  proper Escape would need a `window` listener — a decision, not an oversight.
- **`60vh` is viewport height, not card height.** On the till PC in landscape a
  wide product photo will usually be limited by the card's width instead, so the
  cap mostly bites on portrait photos and on the tablet. Worth a look on the real
  devices; if portrait photos still feel too tall, `50vh` is a one-token change.
- **The small image has no visible affordance.** `cursor: zoom-in` tells a mouse
  user, and `aria-label` tells a screen reader, but on the tablet nothing on
  screen says the photo is tappable. If staff do not discover it, a small
  `shop-pill`-style hint or a corner icon would be the cheapest fix.
- **Row thumbnails stay unenlargeable**, per Out of scope, and the new test now
  enforces that the button class appears exactly once.
- **Nothing committed, pushed or deployed**, per the plan's Constraints.
