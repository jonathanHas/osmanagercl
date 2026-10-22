# Per-line pallet percentage on Udea order review — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-10-21

## Baseline
HEAD: 58842899
Pre-existing dirty files (Shop labels cycle and deliveries archive, not mine):
```
 M app/Http/Controllers/LabelAreaController.php
 M app/Http/Controllers/Shop/LabelsController.php
 M app/Services/LabelQueueService.php
 M config/shop.php
 M docs/deliveries/README.md
 D docs/deliveries/implemented-glued-price.md
 D docs/deliveries/plan-glued-price.md
 M docs/design/shop-mode/README.md
 M docs/features/shop-mode.md
 M docs/shop_new/README.md
 M docs/shop_new/planimp.md
 M resources/css/shop.css
 M resources/js/shop.js
 M resources/js/shop/labels.js
 M resources/views/shop/labels.blade.php
 M routes/web.php
 M tests/Feature/Shop/ShopLabelsTest.php
?? app/Http/Controllers/Shop/ZebraController.php
?? app/Services/ZebraLabelListService.php
?? docs/deliveries/archive/2026-10-20-glued-price/
?? docs/design/shop-mode/screen-07-shelf-labels.html
?? docs/design/shop-mode/screen-07b-zebra-labels.html
?? docs/planImp/plan.md
?? docs/shop_new/design-brief-labels.md
?? docs/shop_new/implemented.md
?? docs/shop_new/plan.md
?? resources/js/shop/zebra.js
?? resources/views/shop/partials/labels-nav.blade.php
?? resources/views/shop/zebra.blade.php
?? tests/Feature/Shop/ShopZebraTest.php
```

## Steps

### 1. Pass the Udea flag into the review-table partials — done
Changed: `resources/views/orders/show.blade.php`, `resources/views/orders/show-christmas.blade.php`
Check output:
```
show-christmas.blade.php:55:        $isUdeaOrder = in_array((string) $order->supplier_id, $udeaSupplierIds, true);
show-christmas.blade.php:126:                'isUdeaOrder' => $isUdeaOrder,
show.blade.php:58:        $isUdeaOrder = in_array((string) $order->supplier_id, $udeaSupplierIds, true);
show.blade.php:141:                'isUdeaOrder' => $isUdeaOrder,
```
`mockup-vico-live.blade.php` untouched.

### 2. Per-line placeholder in `review-table.blade.php` — done
Changed: `resources/views/orders/partials/review-table.blade.php` (`$showPalletLine` at line 87,
block after each of the four "Order:" divs). Inserted by a script that asserted exactly four
matches of the "Order:" div.
Check output:
```
$ grep -cF 'id="pallet-line-{{ $item->id }}"' review-table.blade.php
4
# four quantity cells (65 lines each, re-derived from the qty-decrease lines 767/1270/1685/2100)
767 vs 1270 identical
767 vs 1685 identical
767 vs 2100 identical
```
(The plan's `grep -c` without `-F` prints 0 because `$i` is not literal in BRE; `-F` gives the 4.)

### 3. Same placeholder in `review-table-christmas.blade.php` — done
Changed: `resources/views/orders/partials/review-table-christmas.blade.php` (`$showPalletLine`
at line 68, right after the `$udeaPalletVolumes` map; block before each "Adjusted from").
Check output:
```
$ grep -cF 'id="pallet-line-{{ $item->id }}"' review-table-christmas.blade.php
4
```

### 4. `recalc()` writes each line's figure — done
Changed: `resources/views/components/udea-pallet-summary.blade.php`. Per input: parse `qty` and
`perUnit` first, compute `linePct` once (same expression as before), write the spans for every
input with a finite `perUnit` (qty 0 included), then the unchanged counted/uncounted/pctSum
logic, adding the same `linePct`. Span writing lives in a new component method `writeLine()`
(skips rows without both spans, assigns `textContent` only on change). Header comment and the
listener comment updated.
Check output (`node docs/deposit/scripts/browser/cdp.mjs /orders/349`):
```
 "alpine": true,
console errors/warnings: []
```
Line figures / sum: see Verification 2.

### 5. Programmatic quantity changes tell the bar — done
Changed: `review-table.blade.php` (−, +, case-snap), `review-table-christmas.blade.php` (−, +),
`min-stock-editor.blade.php` (inside `if (qtyInputEl)`). The case-snap handler's `input` can be
null (`if (input) input.value = t;`), so its dispatch is guarded the same way.
Check output:
```
min-stock-editor.blade.php:138:  qtyInputEl.dispatchEvent(new Event('change', { bubbles: true }));
review-table-christmas.blade.php:3305:  input.dispatchEvent(new Event('change', { bubbles: true }));
review-table-christmas.blade.php:3326:  input.dispatchEvent(new Event('change', { bubbles: true }));
review-table.blade.php:3232:  input.dispatchEvent(new Event('change', { bubbles: true }));
review-table.blade.php:3254:  input.dispatchEvent(new Event('change', { bubbles: true }));
review-table.blade.php:3318:  if (input) input.dispatchEvent(new Event('change', { bubbles: true }));
```
Other `change` listeners in these files: only the priority `select` (`review-table:3183`,
`christmas:3258`). Browser: see Verification 3.

### 6. Feature test for the markup — done
Changed: `tests/Feature/OrderReviewPalletLineTest.php` (new; did not exist before). Seeds
suppliers `5` (Udea) and `S1`, products `PA`/`PB` with `supplier_link` + `stocking`, a
`UdeaProductCard` for `5004482` only, items with `context_data.supplier_code`, quantity 6.
No extra POS tables were needed.
Check output:
```
$ php artisan test --filter=OrderReviewPalletLineTest
  ! udea order renders a pallet line per quantity cell → Constant PDO::… 0.70s
  ! non udea order renders no pallet line → Constant PDO::MYSQL_ATTR_SS… 0.04s
  Tests:    2 deprecated (8 assertions)
```
("deprecated" = passed with the PHP 8.5 PDO notice.) Test 1 also asserts item B has no
`pallet-line-pct-` span.

### 7. Documentation — done
Changed: `docs/features/udea-pallet-volumes.md`: per-line paragraph under "On the order review
page", a bullet on the `change` dispatch (and why not `input`) under "Integration points", and
the A2/Grid known-limitations bullet now says they have neither the bar nor per-line figures.
Check: read back; the three passages read correctly.

## Deviations

- Step 4: the span writing is a component method `writeLine(input, perUnit, hasQty, linePct)`
  called from the `forEach`, rather than inline. Same order and rules as the plan (spans are
  written before the qty early-return; `linePct` is computed once and the same value is added
  to `pctSum`). No behaviour change.
- Step 5: the case-snap dispatch is `if (input) input.dispatchEvent(...)` because that handler's
  `input` may be null (it already guards `if (input) input.value = t;`).
- Step 2 check: used `grep -cF`; the plan's `grep -c` without `-F` reports 0 (BRE treats `$i…`
  oddly), with `-F` it reports 4 for each file.

## Verification

1. `./vendor/bin/pint --test` → FAIL on 2 files, both pre-existing and not touched by me:
   `JFolder_temp/old_system/organic_trust_supplier_handler.php`,
   `docs/deposit/scripts/deposit-spike-rollback.php`. `./vendor/bin/pint --test
   tests/Feature/OrderReviewPalletLineTest.php` → PASS.
2. Order 349, script exactly as in the plan:
   ```
   "capacity": 250, "barPct": 19.709999999999997, "sumOfLines": 19.709999999999997,
   "rows": 62, "withLine": 38, "noData": 2, "unitOnly": 22, "mismatches": [],
   sample: {"id":"198627","qty":"1","vol":"1.44","line":"0.58%","unit":" · 0.58%/case"}, …
   console: []
   ```
   All expected values met.
3. Buttons (order 349, item 198627):
   ```
   start  cap 250 pct 19.71 line 0.58% unit " · 0.58%/case"
   euro+  cap 500 pct  9.85 line 0.29% unit " · 0.29%/case"
   euro-  cap 250 pct 19.71 line 0.58%
   qty+   cap 250 pct 20.28 qty 2 line 1.15%   bar delta 0.5700 = line delta 0.5700
   qty-   cap 250 pct 19.71 qty 1 line 0.58%
   console: []
   ```
   DB after the run: item 198627 `final_quantity 8.000`, `final_cases 1.000` (restored).
4. `php artisan test --filter=OrderReviewPalletLineTest` → 2 passed (8 assertions).
5. `php artisan test --filter="UdeaPalletVolume|ShopOrderReview|OrderDifference|OrderReviewPalletLine"`
   → `Tests: 57 deprecated (230 assertions)`, 0 failed.
6. `php artisan test` → `16 failed, 1076 deprecated, 43 passed`. The 16 are the baseline set:
   UdeaScrapingServiceTest ×7, BackupConfigurationTest ×1, CashReconciliationTest ×3,
   FruitVegLabelPrintingTest ×2, ProductTest ×2, TestScraperControllerTest ×1. No new failure.
7. Order 353: `node docs/deposit/scripts/browser/cdp.mjs /orders/353` → console `[]`. In the
   default view (rows with a quantity) the page has 253 inputs, 252 per-line spans, bar 95.77 %;
   `recalc()` timed 2.5 / 1.8 / 1.5 / 1.2 / 1.2 ms, a dispatched `change` 3.2 ms. Well under
   100 ms. With `?show_all=1` (all 1,353 rows) the page returns **500: PHP memory exhausted
   (128 MB) rendering `review-table.blade.php`**. Also 500s with the HEAD versions of all six
   view files swapped in (mine copied aside and restored after), so it is pre-existing; see
   Notes.

## Files changed

Mine (all were clean at baseline):
```
 M docs/features/udea-pallet-volumes.md
 M resources/views/components/udea-pallet-summary.blade.php
 M resources/views/orders/partials/min-stock-editor.blade.php
 M resources/views/orders/partials/review-table-christmas.blade.php
 M resources/views/orders/partials/review-table.blade.php
 M resources/views/orders/show-christmas.blade.php
 M resources/views/orders/show.blade.php
?? docs/planImp/implemented.md
?? tests/Feature/OrderReviewPalletLineTest.php
```
Everything else in `git status --short` is the pre-existing list in Baseline, unchanged.
No `npm run build` needed (only `text-amber-600` is new to these files; it is already compiled).

## Notes for Planner

- **`/orders/353?show_all=1` exhausts PHP's 128 MB memory limit on dev** (HEAD as well as with
  this change). The per-line block adds ~12 lines of markup per row, which makes the page a
  little heavier but did not cause it. Worth a follow-up: production `memory_limit`, or the
  page's size at 1,353 rows. Not touched.
- The plan's per-step `grep -c 'id="pallet-line-{{ $item->id }}"'` needs `-F` to count.
- Per-line spans are empty in the HTML until Alpine runs (by design, out of scope to render
  server-side); with JS off a Udea line shows nothing under "Order:" except "No pallet data".
