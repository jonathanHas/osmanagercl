# Shop mode cycle 5 — product images on Find product — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-23

## Baseline
HEAD: 47ee616f
Pre-existing dirty files (cycles 3 and 4, accepted and archived but uncommitted — not mine):
```
 M config/shop.php
 M docs/design/shop-mode/README.md
 D docs/planImp/implemented.md
 M docs/planImp/plan.md
 M resources/js/shop.js
 M routes/web.php
 M tests/Feature/Shop/ShopHomeTest.php
?? app/Http/Controllers/Shop/FindProductController.php
?? app/Http/Controllers/Shop/StockScanController.php
?? docs/planImp/archive/2026-09-23-scraping-queue-cleanup/
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-3/
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-4/
?? resources/js/shop/
?? resources/views/components/shop/scan-input.blade.php
?? resources/views/shop/find-product.blade.php
?? resources/views/shop/stock-scan.blade.php
?? tests/Feature/Shop/ShopFindProductTest.php
?? tests/Feature/Shop/ShopStockScanTest.php
```

## Steps
### 1. Stylesheet: app additions section — done
Changed: `resources/css/shop.css` (appended, nothing above the marker touched)
Check output:
```
$ cmp resources/css/shop.css docs/design/shop-mode/shop.css   (before the edit)
IDENTICAL-BEFORE

$ head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL
DESIGN-BLOCK-IDENTICAL

$ grep -c "APP ADDITIONS" resources/css/shop.css
2

$ tail -8 resources/css/shop.css
}

/* === APP ADDITIONS START ===
   Styles the app needs that the design bundle does not define. Keep the design
   block above byte-identical to docs/design/shop-mode/shop.css; add here only. */
.shop-thumb { display: block; flex: none; width: 48px; height: 48px; border-radius: var(--shop-radius-sm); background: var(--shop-surface-2); border: 1px solid var(--shop-line); object-fit: cover; }
.shop-thumb--lg { width: 128px; height: 128px; border-radius: var(--shop-radius-md); object-fit: contain; background: var(--shop-surface); }
/* === APP ADDITIONS END === */
```
(The `--lg` rule gained `object-fit: contain` and its background after the owner
reviewed the screen — see "Post-review correction" below. Everything else in
this section is as first written.)

All five tokens used are defined in the design block: `--shop-radius-sm: 10px`,
`--shop-radius-md: 16px`, `--shop-surface-2: #efe3cf`, `--shop-line: #dcd3c4`,
`--shop-surface: #fdf9f3`.

### 2. Behaviour: failed-image tracking — done
Changed: `resources/js/shop/find-product.js` (state `failed: {}`, methods `hasImage()` / `imageFailed()`, reset on a non-append search)
Check output:
```
$ node --check resources/js/shop/find-product.js
syntax ok

$ node -e "import('./resources/js/shop/find-product.js').then(m => { const d = m.default(); d.failed = {}; const p = {id:'x', image_url:'u'}; console.log(d.hasImage(p)); d.imageFailed(p); console.log(d.hasImage(p), d.hasImage({id:'y', image_url:null})) })"
true
false false
```
Matches the plan's expected `true`, then `false false`.

### 3. Rows: thumbnail or placeholder — done
Changed: `resources/views/shop/find-product.blade.php` (img + `shop-row__lead` placeholder before `shop-row__main`)
Check output:
```
$ grep -c 'class="shop-thumb"' resources/views/shop/find-product.blade.php
1

$ php artisan test --filter=ShopViewContractTest
  ✓ there are screens to check
  ✓ screen carries no styling or behaviour with data set #0
  ✓ screen carries no styling or behaviour with data set #1
  ✓ screen carries no styling or behaviour with data set #2
  Tests:    4 passed (38 assertions)
```

### 4. Detail card: large image beside the name — done
Changed: `resources/views/shop/find-product.blade.php` (name/price/pills block wrapped in `shop-inline`, 128 px image before it)
Check output:
```
$ grep -c "shop-thumb--lg" resources/views/shop/find-product.blade.php
1

$ php artisan view:cache
   INFO  Blade templates cached successfully.
```
`.shop-inline` is `display: flex; flex-wrap: wrap`, so the image sits beside the
name on the till PC and wraps above it on a phone, as the plan describes.

### 5. Tests — done
Changed: `tests/Feature/Shop/ShopFindProductTest.php` (three tests added, six kept)
First run failed, and caught a real bug in the plan's markup — see Deviation 1:
```
syntax error, unexpected end of file, expecting "elseif" or "else" or "endif"
(View: resources/views/shop/find-product.blade.php)
  Tests:    2 failed, 7 passed
```
After switching `@error` to `x-on:error`:
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
  ✓ barista cannot fetch product images

  Tests:    9 passed (35 assertions)
```

### 6. README — done
Changed: `docs/design/shop-mode/README.md` (the `shop.css` table row, plus one line under "Component API in the app")
Check output:
```
$ grep -c "APP ADDITIONS" docs/design/shop-mode/README.md
1

$ tail -1 docs/design/shop-mode/README.md
- `.shop-thumb` / `.shop-thumb--lg` are app additions for product photos (48 px in rows,
  128 px on a detail card); both hide on a load error and the row falls back to the
  `shop-row__lead` circle.
```

### 7. Build and format — done
Changed: all touched files
Check output:
```
$ npm run build
✓ built in 12.37s

$ ./vendor/bin/pint --dirty
  PASS   7 files

$ ./vendor/bin/pint --test --dirty
  PASS   7 files

$ grep -l "shop-thumb" public/build/assets/shop-*.css
public/build/assets/shop-BCSImQZ4.css
```

## Post-review correction (owner, 2026-09-23, after Status: DONE)

The owner looked at the finished screen and reported that the card image shows
"a section of the product" rather than the whole thing. Correct: `.shop-thumb`
sets `object-fit: cover`, which centre-crops, and `.shop-thumb--lg` inherited it.
On a 128 px square that cuts the top and bottom off most product photos, which
works directly against the plan's own Goal — "staff can confirm 'is this the
one?' with the customer at a glance".

I treated this as a defect in cycle 5's delivery rather than new scope, and
changed one rule:

```css
.shop-thumb--lg { ... object-fit: contain; background: var(--shop-surface); }
```

- `contain` on the large variant only. The 48 px row thumbnail keeps `cover`,
  which is right for a dense list and matches the office component exactly:
  `resources/views/components/product-search/thumb.blade.php` uses
  `object-cover` for its 40 px row thumb and `object-contain` for both enlarged
  views.
- `background: var(--shop-surface)` because `contain` letterboxes. `--shop-surface`
  (`#fdf9f3`) is also `.shop-card`'s background, so the bars disappear into the
  card while the inherited `1px solid var(--shop-line)` border keeps the image's
  edge visible.

Re-verified after the change: design block still `DESIGN-BLOCK-IDENTICAL`;
`php artisan test --filter=Shop` → 102 passed; `php artisan test` → 17 failed /
502 passed (unchanged); `pint --test --dirty` clean; the built stylesheet carries
`.shop-thumb--lg{...object-fit:contain;background:var(--shop-surface)}`.

The owner's second request — a full-size image on click, like the office search's
hover preview — is **not** implemented. It is explicitly in this plan's Out of
scope and is written up for the Planner below.

## Deviations

One, and it was forced: the plan's markup does not compile.

1. **`@error` → `x-on:error`** (steps 3 and 4). Blade has its own `@error`
   directive (validation errors; it opens an `if` and expects `@enderror`), so
   `@error="imageFailed(p)"` in a `.blade.php` file is compiled as that
   directive and the view dies with:
   ```
   syntax error, unexpected end of file, expecting "elseif" or "else" or "endif"
   (View: resources/views/shop/find-product.blade.php)
   ```
   Both images now use `x-on:error="..."`, Alpine's long form for exactly the
   same binding. Behaviour is identical; only the attribute spelling changed.
   Note that every other `@`-shorthand already on this screen (`@click`,
   `@input`, `@change`, `@keydown.enter`) is safe — `error` is the one Alpine
   event name that collides with a Blade directive here.

   The plan's step 4 check (`php artisan view:cache` succeeds) did **not** catch
   this: `view:cache` writes the compiled PHP without executing or linting it,
   so it reported success on a template that fatally errors on render. The new
   feature test caught it on the first run. See Notes for Planner.

## Verification

1. `php artisan test --filter=Shop` → **pass**, `Tests: 102 passed (235 assertions)`,
   with 9 in `ShopFindProductTest` as the plan expects.

2. `php artisan test` → **pass against baseline**: `Tests: 17 failed, 502 passed (1855 assertions)`.
   Cycle 4 ended at 17 failed / 499 passed; the 3 extra passes are exactly the
   three new tests. The 17 failures are the identical pre-existing set:
```
UdeaScrapingServiceTest      ×7  (BadMethodCallException ×6, InvalidCountException ×1)
CashReconciliationTest       ×3  (BadMethodCallException)
WasteLogTest                 ×2
ProductTest                  ×2
FruitVegLabelPrintingTest    ×2
TestScraperControllerTest    ×1
```

3. `grep -rn "<script\|<style" resources/views/shop/` → no output. **pass**

4. `git diff --stat app/ resources/views/products/ resources/views/components/product-search* resources/views/shop/stock-scan.blade.php resources/views/shop/home.blade.php`
   → empty. **pass** — the search API, `ProductController@image`, the office
   components and the other two shop screens are untouched.

5. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL`
   → `DESIGN-BLOCK-IDENTICAL`. **pass**
   `tail -8 resources/css/shop.css` shows the additions block (quoted under Step 1).
   For the record, `cmp` of the two whole files was clean *before* the edit, so
   the design block was byte-identical to start with and still is.

6. `./vendor/bin/pint --test --dirty` → `PASS 7 files`; `npm run build` → `✓ built in 12.37s`;
   the built stylesheet `public/build/assets/shop-BCSImQZ4.css` contains `.shop-thumb`. **pass**

7. Manual checks on `/shop/find` — **not run by me.** I have no browser session
   and cannot sign in as an employee, so nothing about real CDN hits, lazy
   loading, row alignment or the phone wrap has been observed in a browser. The
   list stands as written for the owner. What the automated checks do cover:
   the markup is present and renders, the permission gate on `products.image`
   holds both ways, and the API still carries `image_url` / `has_image`.

## Files changed

`git status --short` at the end. `[pre]` marks files already dirty at baseline
(cycles 3 and 4, accepted and archived but never committed).

```
 M config/shop.php                                            [pre]
 M docs/design/shop-mode/README.md                            mine (also [pre])
 M docs/planImp/implemented.md                                mine
 M docs/planImp/plan.md                                       [pre]
 M resources/css/shop.css                                     mine — first edit to this file
 M resources/js/shop.js                                       [pre]
 M routes/web.php                                             [pre]
 M tests/Feature/Shop/ShopHomeTest.php                        [pre]
?? app/Http/Controllers/Shop/FindProductController.php        [pre]
?? app/Http/Controllers/Shop/StockScanController.php          [pre]
?? docs/planImp/archive/2026-09-23-scraping-queue-cleanup/    [pre]
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-3/         [pre]
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-4/         [pre]
?? resources/js/shop/                                         mine (find-product.js; also [pre])
?? resources/views/components/shop/scan-input.blade.php       [pre]
?? resources/views/shop/find-product.blade.php                mine (also [pre])
?? resources/views/shop/stock-scan.blade.php                  [pre]
?? tests/Feature/Shop/ShopFindProductTest.php                 mine (also [pre])
?? tests/Feature/Shop/ShopStockScanTest.php                   [pre]
```

My changes this cycle, precisely — five files, no new files:
- `resources/css/shop.css` — 6 lines appended below the `APP ADDITIONS` marker
- `resources/js/shop/find-product.js` — `failed: {}` state, `hasImage()`,
  `imageFailed()`, reset on a non-append search
- `resources/views/shop/find-product.blade.php` — row image + placeholder, card
  image, name block wrapped in `shop-inline`
- `tests/Feature/Shop/ShopFindProductTest.php` — three tests added
- `docs/design/shop-mode/README.md` — `shop.css` row reworded, one line added

`public/build/` is gitignored, so the rebuilt assets do not appear.

## Notes for Planner

- **`php artisan view:cache` is not a compile check.** It passed on a template
  that fatally errors on render (Deviation 1). For a step whose risk is "does
  this Blade parse", a feature test that actually GETs the page is the check
  that bites; `view:cache` only proves the compiler ran, not that its output is
  valid PHP. Worth using `->get(route(...))->assertOk()` as the `Check:` line
  for markup steps in future plans.
- **Alpine `@` shorthands vs. Blade directives.** Beyond `@error`, Blade also
  owns `@class`, `@checked`, `@disabled`, `@selected`, `@props` and `@style`.
  None are Alpine event names, so `x-on:error` is the only substitution this
  screen needed — but a future screen binding `@click.away` is fine while
  anything spelled `@<blade-directive>` is not. Might be worth a line in the
  design README's component-API section.
- **The card image has no placeholder**, per the plan. When a selected product
  has no photo the name block simply starts at the left edge — correct, but it
  means the card's height changes slightly between products with and without a
  photo. Not a layout jump in the list, just worth seeing on the real screen.
- **`failed` resets only on a fresh search**, not on "Show more". That is what
  the plan says and it is the right call: appending a page should not retry
  images that already 404'd on the rows above.
- **`has_image` is asserted as a bool** in the new API-shape test. If the search
  service ever starts returning it as `0`/`1` that test will fail loudly, which
  is the point — the screen's fallback logic reads `image_url`, but `has_image`
  is the field that distinguishes a local blob from a CDN guess and is worth
  pinning.
- **Owner request for cycle 6: full-size product image on click.** Their words:
  "a nice feature on the original search images is a full size image of the
  product on hover — can you add this? … when the user clicks on a product it
  might be good to also show the full image". The crop half of that is fixed
  above; the enlarge is yours to spec. What I found while checking:
  - The office implementation is `resources/views/components/product-search/thumb.blade.php`
    with `resources/js/.../productSearchThumb()`. It has **two** modes, and the
    shop needs the second one at least: `preview($el)` on `mouseenter` (a
    teleported `w-64` panel positioned at `pos.x/pos.y`), and a `$tap` mode —
    a centred overlay with a `bg-black/50` backdrop, a close button,
    `x-on:click.self` to dismiss and `x-on:keydown.escape.window`. Shop mode is
    touch-first (till PC has a mouse, the tablet does not), so hover alone would
    ship a feature half the devices cannot reach.
  - Three things make this more than a port. (a) The shop design system has no
    overlay, scrim or lightbox pattern — `--shop-scrim: rgb(46 43 37 / 0.45)` is
    defined in the token block but nothing uses it, so it looks like the design
    anticipated one. (b) `ShopViewContractTest` bans utility classes, so the
    office component's Tailwind cannot come across; it needs real `shop-*` rules
    in the `APP ADDITIONS` section. (c) `x-teleport="body"` moves the node
    outside `.shop`, where none of the shop styles apply — either teleport to
    `#shop-root` instead, or scope the new rules so they still bite.
  - The row is already a `<button>` that selects the product, so a tap on the
    thumbnail needs `click.stop` or it will do both.
  - Cheapest version, if you want one step rather than a cycle: make the card's
    128 px image a button that swaps to a full-width `shop-card`-sized image
    in place, no overlay at all. Avoids the scrim, the teleport and the touch/
    hover split entirely.
- **Nothing committed, pushed or deployed**, per the plan's Constraints.
