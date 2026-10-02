# Customer requests: keep "New request" on screen — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-10-02

## Baseline
HEAD: 9247c79e
Pre-existing dirty files:
```
 M docs/features/shop-mode.md
 D docs/planImp/findings/2026-09-30-summary-units-float.md
 M docs/shop_new/README.md
 D docs/shop_new/implemented.md
 M docs/shop_new/plan.md
 M resources/js/shop/delivery-scan.js
 M resources/js/shop/product-typeahead.js
 M resources/views/shop/delivery-scan.blade.php
 M resources/views/shop/partials/request-form.blade.php
 M resources/views/shop/request-edit.blade.php
 M tests/Feature/Shop/ShopDeliveryTest.php
 M tests/Feature/Shop/ShopRequestsTest.php
?? docs/shop_new/archive/
```

## Steps
### 1. A `shop-contents` utility — done
Changed: `resources/css/shop.css` (one rule + comment, just before `APP ADDITIONS END`)
Check output (design-block cmp):
```
cmp=0   (no output from cmp)
```

### 2. Customer requests: the bar sticks — done
Changed: `resources/views/shop/partials/requests-staff.blade.php` (`class="shop-contents"` before `x-data`, header comment extended),
`tests/Feature/Shop/ShopRequestsTest.php` (new `test_the_new_request_bar_is_laid_out_to_stick`)
Check output:
```
php artisan test tests/Feature/CustomerRequestTest.php tests/Feature/Shop/ShopRequestsTest.php tests/Feature/Shop/ShopLabelsTest.php
Tests: 1 failed, 58 passed
```
The one failure was the step-4 test (written ahead, `answered` not yet in the
source); the four pinned `x-data` assertions and the new bar test passed. All
green after step 5 (see below).

### 3. Print labels: the bar sticks — done
Changed: `resources/views/shop/labels.blade.php` (`class="shop-contents"` on the print form + one-line Blade comment; `x-show`, `x-cloak`, `target`, `@submit` untouched),
`tests/Feature/Shop/ShopLabelsTest.php` (new `test_the_print_bar_is_laid_out_to_stick`, asserts `<form class="shop-contents" method="POST" action="…labels.print-a4…"`)
Check: passed in the run above.

### 4. The typeahead records which query was answered — done
Changed: `resources/js/shop/product-typeahead.js`: `answered: null`; set to `q` on a good answer; `null` on the empty-query return, the `! response.ok` path, the catch path and in `pickResult()`; stale calls return before touching it. Getter `noMatches` as specified. Header comment extended.
`tests/Feature/Shop/ShopRequestsTest.php`: new `test_the_typeahead_knows_when_a_search_found_nothing` (source contains `answered` and `get noMatches()`; also the step-5 view assertions).
Check: `npm run build` → `✓ built in 8.12s`.

### 5. "No products match" on the three screens — done
Changed: `request-form.blade.php` (`x-show="! picked && noMatches"` line after Try again), `request-edit.blade.php` (`x-show="noMatches"` line after Try again), `delivery-scan.blade.php` (condition → `noMatches`), `delivery-scan.js` (`closeManual()` sets `answered = null`), `requests.js` (`unpick()` sets `answered = null`), `ShopDeliveryTest.php` (the single `assertSee` changed as the plan says).
Check output:
```
php artisan test tests/Feature/Shop tests/Feature/CustomerRequestTest.php
Tests:    311 passed (1549 assertions)
```

### 6. Documentation — done
Changed: `docs/features/shop-mode.md`: a paragraph after the Alpine traps in "The view contract" (sticky `.shop-actions` must be a child of `<main>` or a whole-page form; `shop-contents` for scope/form-only wrappers; names the two screens), and one sentence after the search-failure paragraph on `noMatches`.
Check: re-read against the code; class names and getter match.

## Deviations

None in the code. One note on the plan's step 6g wording: on the labels page
`total` is a getter (`rows.length`, `labels.js:55`), so "temporarily setting
`total`" does nothing. I set `rows` instead (client-side only, then restored).

## Verification

1. `./vendor/bin/pint --dirty` → `PASS 3 files`, no changes.
2. `npm run build` → `✓ built in 8.12s`.
3. Design-block `cmp` → no output (exit 0).
4. `php artisan test tests/Feature/Shop tests/Feature/CustomerRequestTest.php` → `Tests: 311 passed (1549 assertions)`.
5. `php artisan test` → `Tests: 15 failed, 942 passed (4173 assertions)`. The failures are the
   baseline 15 exactly: `UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3,
   `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2, `TestScraperControllerTest` ×1.
   942 = 939 + 3 new tests.
6. Browser, dev `http://osmanager.local`, fresh tab, signed in as the owner's
   dev account. Measurements are from a same-origin iframe of the stated size.
   - **a.** `/customer-requests` 390 × 844, top: bar top 747, bottom **844**, viewport 844,
     document `scrollHeight` 1216, wrapper computed `display: contents`. (Before: bar top 1,119.)
   - **b.** Scrolled to the end (`scrollY` 372): bar top 747, bottom 844. The last
     list element (the `.shop-stack` before the wrapper, and its deepest
     visible child) ends at 731, which is at or above the bar top (747).
   - **c.** 1280 × 800: bar top 687, bottom **792**, viewport 800. The document does not scroll
     (`scrollHeight` 800). Button right-aligned (`justify-content: flex-end`,
     button right 1208, bar right 1232). The 8 px gap below the bar comes from the
     design block: `main` padding is 32 px at this width and the bar's margin is
     `… -24px -24px`. See Notes.
   - **d.** Real input: I clicked "New request" and the sheet opened (backdrop `flex`).
     Escape closed it (backdrop `none`). I reopened it by click and Cancel closed it
     (`none`). I reopened it and typed `zzzqqq` (real keys). Read straight after
     typing: `answered: null, noMatches: false`, line hidden. After 1.2 s:
     `answered: "zzzqqq", noMatches: true`, line shown. I selected the text and typed `che`. Straight after:
     `noMatches: false` (answered still `zzzqqq`). After 1.2 s: 20 results,
     `noMatches: false`, line hidden. Not submitted.
   - **e.** `fetch('/customer-requests', {credentials: 'omit'})` → 200, has `shop-page`,
     no `shop-actions`, no `shop-contents`, no "New request".
   - **f.** `/customer-requests/4/edit`: I typed `zzzqqq` in the item search (real input).
     Straight after: `noMatches: false`. After 1.2 s: `true`, "No products match" shown.
     Save/Cancel bar: top 1148, bottom **1253** = viewport 1253. Not saved.
   - **g.** `/shop/labels` 390 × 844 with 1 label queued: form `display: contents`, Print bar
     bottom **844** = viewport. The page itself is not taller than the screen
     (`scrollHeight` 844). So I padded `rows` client-side to 15 copies (`scrollHeight` 2583): bar bottom
     844 at the top and at the end of the scroll. With `rows = []` (`total` 0): form
     inline `display: none`, bar not rendered. After restoring: `total` 1, `display: contents`.
     I did not print or clear.
   - **h.** Delivery scan (Mossfield draft, `b98fced8…`): I clicked "Find by name" and typed `zzzqqq`
     (real input). After 1.5 s: `noMatches: true`, "No products match" shown,
     "Search all products" shown.
   - Console: no errors or warnings. I reloaded `/customer-requests` and `/shop/labels`
     with tracking on; the only log line is "Alpine.js started".
7. Dev state: nothing was written. I submitted, saved, printed, cleared and scanned
   nothing. The only change to the labels `rows` was client-side Alpine state, and I restored it.

## Files changed

`git status --short` at the end. **mine** = changed in this task; (pre) = dirty before I started.
```
 M docs/features/shop-mode.md                          (pre) + mine
 D docs/planImp/findings/2026-09-30-summary-units-float.md   (pre)
 M docs/shop_new/README.md                             (pre)
 M docs/shop_new/implemented.md                        mine (was deleted at baseline; recreated)
 M docs/shop_new/plan.md                               (pre)
 M resources/css/shop.css                              mine
 M resources/js/shop/delivery-scan.js                  (pre) + mine
 M resources/js/shop/product-typeahead.js              (pre) + mine
 M resources/js/shop/requests.js                       mine
 M resources/views/shop/delivery-scan.blade.php        (pre) + mine
 M resources/views/shop/labels.blade.php               mine
 M resources/views/shop/partials/request-form.blade.php (pre) + mine
 M resources/views/shop/partials/requests-staff.blade.php mine
 M resources/views/shop/request-edit.blade.php         (pre) + mine
 M tests/Feature/Shop/ShopDeliveryTest.php             (pre) + mine (one assertSee)
 M tests/Feature/Shop/ShopLabelsTest.php               mine
 M tests/Feature/Shop/ShopRequestsTest.php             (pre) + mine
?? docs/shop_new/archive/                              (pre)
```

## Notes for Planner

- **Wide layout gap (6c).** At 1280 × 800 on a page that does not scroll, the bar's
  bottom edge is 8 px above the viewport bottom. The design block causes this:
  `main` padding is 32 px at wide widths, but the bar's negative margin is 24 px.
  The same would happen in the design file. I did not touch it, because the design
  block is verbatim. If the owner wants the bar flush on wide screens, an
  APP ADDITIONS rule could match the margin to the wide padding.
- **Plan wording, 6g.** `total` on the labels page is a getter, so the check
  has to set `rows`. I did that. Future plans that suggest faking state there should name `rows`.
- `find-product.js` has its own `results` handling and does not use
  `product-typeahead.js`'s `noMatches`. I left it alone (not in the plan).
