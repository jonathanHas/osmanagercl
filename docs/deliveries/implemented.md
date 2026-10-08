# Cycle 3 — Correct quantities from the delivery summary — implementation

Status: DONE
Plan revision: 1
Implementer: Fable 5.1 (this session was started as the Implementer; the protocol names Opus)
Date: 2026-10-08

**Where it stands in one line:** all seven steps done, including the browser
check (the Chrome extension was connected and the owner had signed the tab in
as `katelyn`, an employee). 63 delivery tests, 333 Shop tests, full suite at
the baseline 15 failed / 1047 passed, build clean, pint clean. The card is one
JS part and one Blade partial used by both screens; the scan page lost 264
lines and gained an import. Dev data put back (honey row at 27).

## Baseline
HEAD: 4d023aa8
Pre-existing dirty files:
```
 M docs/deliveries/README.md
R  docs/deliveries/implemented.md -> docs/deliveries/archive/2026-10-08-correction-card-speed/implemented.md
RM docs/deliveries/plan.md -> docs/deliveries/archive/2026-10-08-correction-card-speed/plan.md
?? docs/deliveries/plan.md
```
(the cycle 2 archive move, staged by the owner; this file is new). The three
product-form files another session had open during cycle 2 were no longer
dirty. `resources/js/shop/delivery-correction.js` and
`resources/views/shop/partials/delivery-correction.blade.php` did not exist.

## Steps

### 1. The shared card logic — done
Changed: `resources/js/shop/delivery-correction.js` (new, 286 lines),
`resources/js/shop/delivery-scan.js` (−264 lines)
- The part was built by cutting the blocks out of the scan JS with a script
  and writing them verbatim into the new file: state `editing`, `editTyped`,
  `editValue`, `flushTimer`, `flushPending`, `busy`, `toast`, `toastTimer`;
  `TOAST_MS`; getters `updateUrl`, `delId`, `supplierId`, `csrf`,
  `editingRow`; `focusField()` (with its docblock), `edit()`, `adjust()`,
  `flushNow()`, `typeCorrection()`, `setCorrection()`, `quantityBody()`,
  `saveQuantity()`, `requestInit()`, `post()`, `stockText()`, `showToast()`.
  New: `initCorrection()` (the `editing` watch and the `pagehide` listener,
  the exact text from the scan page's `init()`), `closeCardIfRowGone()` (the
  two lines from `load()`). Header comment states what the page must provide
  and why the part exists. `export default () => ({...})`.
- Scan page: `import deliveryCorrection`, composed as
  `mix(productImages(), productTypeahead(), deliveryCorrection(), {...})`
  before the page object; the moved members deleted (none commented out);
  `init()` is `this.initCorrection(); this.load();`; `load()` calls
  `this.closeCardIfRowGone()`; `lookup()` keeps `await this.flushNow()`;
  `parseQuantity` / `quantityText` imports stay (used by the prompt).
  Header comment: the card is the shared part.
Check output:
```
$ npm run build                                   ✓ built in 9.77s
$ grep -c "async flushNow(" resources/js/shop/delivery-scan.js         0
$ grep -c "async flushNow(" resources/js/shop/delivery-correction.js   1
$ grep -n 'editing: null\|busy: false\|toast: null\|get delId\|get csrf\|showToast(tone\|focusField(ref)\|TOAST_MS' resources/js/shop/delivery-scan.js
(no matches: nothing left behind)
```
The delivery tests were run after steps 1–3 together with the step 5
retargeting already applied (one script), so no intermediate failure list;
see step 5.

### 2. The shared card markup — done
Changed: `resources/views/shop/partials/delivery-correction.blade.php` (new,
47 lines), `resources/views/shop/delivery-scan.blade.php`
- The `x-ref="correct"` section with its leading comment moved verbatim
  (re-indented to the partial's top level), `x-show="{{ $show ?? 'editing' }}"`,
  input id `delivery-edit-qty` kept; a header comment on the partial.
- Scan view line 221:
  `@include('shop.partials.delivery-correction', ['show' => 'editing && ! pending'])`.
Check output: in the 99-test run below, the scan-page rendering tests
(`Correct quantity`, `adjust(editingRow, 1)`, `stockText(editValue)`, the Done
button's `:class`) all pass.

### 3. The summary opens the card — done
Changed: `resources/views/shop/delivery-summary.blade.php`, `resources/js/shop/delivery-summary.js`
- JS: import, `mix(productImages(), deliveryCorrection(), {...})`, `init()`
  = `initCorrection(); load();`, `load()` calls `closeCardIfRowGone()` after
  `rows`; header sentence. Nothing else (its own `delId` / `supplierId`
  getters stay; see Notes).
- View: `data-update-url` on `<main>`; after the Discrepancies `<h2>`, the
  include inside `@unless ($session['completed'])` with the comment; inside
  the `x-for` template an `@if ($session['completed'])` keeping today's
  `<div class="shop-row">` and an `@else` with
  `<button class="shop-row shop-item shop-item--pic" type="button" :aria-pressed="editing === row.barcode" @click="edit(row)">`
  and `<span>` children; the `.shop-toasts` block before `</main>`.
Check output:
```
$ php artisan test tests/Feature/Shop/ShopDeliveryTest.php tests/Feature/Shop/ShopViewContractTest.php tests/Feature/Shop/ConfinePinSessionTest.php
  Tests:    99 passed (749 assertions)
$ npm run build                                   ✓ built in 9.77s
```

### 4. Tests for the summary — done
Changed: `tests/Feature/Shop/ShopDeliveryTest.php` — the four tests under
`// --- deliveries cycle 3: corrections from the summary ---`, as named and
specified (the card-before-list `strpos`, the completed page's `assertDontSee`s,
the shared-files assertions reading both JS files, both views and the
partial, and the PATCH-then-items totals check).
Check output: `ShopDeliveryTest` → **63 passed** (347 assertions).

### 5. Retarget the moved source assertions — done
Changed: `tests/Feature/Shop/ShopDeliveryTest.php` — `correctionJs()` helper
after `scanJs()`; the `setCorrection()` body test, the `financials: false`
count and the `flushNow` / 400 ms / `keepalive` / `adjust()` body assertions
now read `correctionJs()`; the steps-locally test also asserts the scan JS
does not contain `setTimeout(() => this.flushNow(), 400)`. The scan-flush,
camera and prompt-order tests stay on `scanJs()`.
Check output: 63 passed (above).

### 6. Docs — done
Changed: `docs/features/shop-mode.md` (a third paragraph in "Deliveries:
correcting a quantity"), `docs/shop_new/README.md` ("Where the code is",
Alpine modules row).
Check output:
```
$ grep -c "delivery-correction" docs/features/shop-mode.md docs/shop_new/README.md
docs/features/shop-mode.md:2
docs/shop_new/README.md:1
```

### 7. Format, build, browser check — done
- `./vendor/bin/pint --test $(git diff --name-only -- '*.php')` → PASS (3
  files: the test file plus two unrelated PHP files the owner's README /
  archive diff does not include — all PASS). `npm run build` clean.
- Browser, dev host, Chrome extension connected, tab signed in as `katelyn`
  (employee, `deliveries.process`), in a tab of my own so the owner's open
  scan-screen tab was not disturbed. Session
  `9e5c52db-9b1b-4641-95e3-4e5b33cd8a24` (Coolfin, supplier 13, open, no
  invoice lines; three unexpected rows). Honey row **27 before** (screen and
  tinker).
  1. Tap the Coolfin Raw Honey row → the card opened above the list showing
     **27**, "Stock 93"; JS probe: one `button[aria-pressed="true"]`, its
     title "Coolfin Raw Honey 340g". Console: no errors.
  2. Tap + three times quickly → the number read **30** in a screenshot
     taken immediately after the taps, before any request; after 2 s the
     network log showed exactly **one PATCH `scan-item` (200) and one GET
     `items` (200)**; the row's meta read "scanned 30". Totals stayed 3 / 3
     unexpected (no invoice, so the status cannot change; the pill is
     "Unexpected" either way).
  3. Tap the number → the "Quantity or weight" field opened with 30. My
     first attempt to type into it did not land (see Notes: the extension's
     keystrokes went to the button, `document.activeElement` was `BUTTON`);
     a click into the field, select-all, type `27`, Enter → JS probe
     `editTyped "27"`, then **one PATCH and one GET**, `editTyped null`,
     `editValue 27`, row "scanned 27".
  4. Tap Done → JS probe `editing null`, card `display: none`, no pressed
     rows. Console clean.
  5. Summary of the completed session `685c8d19-…` (Udea, 457 rows) → JS
     probe: `button.shop-row` 0, `div.shop-row` 457, `[x-ref="correct"]` 0,
     "This delivery is completed" shown.
- **Dev state:** honey row back at **27** (typed in step 3; tinker:
  `honey qty: 27`, session rows 3). Nothing else written.

## Deviations
1. **Steps 1–5 were applied in one script**, so the plan's "run the
   delivery tests after step 1, note which fail, fix in step 5" produced no
   intermediate failure list. The retargeting in step 5 is exactly the four
   assertions the plan named; with them the file passes at 63.
2. **The partial is re-indented** to top level (the scan view had it four
   levels deep); the markup is otherwise the moved text. The `$show`
   expression is the one difference the plan asked for.
3. **`delivery-summary.js` keeps its own `delId` / `supplierId` getters**,
   which now duplicate the part's (identical bodies; the page object comes
   later in `mix()` and wins). The plan said nothing else changes in that
   file, so they were left; the Planner may want them removed.
4. The owner's checklist was not needed: the browser check ran.

## Verification
1. `./vendor/bin/pint --test $(git diff --name-only -- '*.php')` → PASS (3 files).
2. `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` → **63 passed**.
3. `php artisan test tests/Feature/Shop` → **333 passed** (1710 assertions).
4. `php artisan test` → **15 failed, 1047 passed** (4831 assertions). The 15 by
   class: `Tests\Unit\UdeaScrapingServiceTest` ×7,
   `Tests\Feature\CashReconciliationTest` ×3,
   `Tests\Feature\FruitVegLabelPrintingTest` ×2, `Tests\Feature\ProductTest` ×2,
   `Tests\Feature\TestScraperControllerTest` ×1 — the baseline set, nothing
   else. (Passed is 1047, one more than the plan's 1046: 1042 + 4 new = 1046,
   so one test elsewhere was added since the README baseline, presumably
   with the product-form commit; not from this cycle.)
5. `npm run build` → `✓ built in 9.77s`.
6. Browser check: done, step 7 above.

## Files changed
Mine:
```
 M docs/features/shop-mode.md
 M docs/shop_new/README.md
 M resources/js/shop/delivery-scan.js
 M resources/js/shop/delivery-summary.js
 M resources/views/shop/delivery-scan.blade.php
 M resources/views/shop/delivery-summary.blade.php
 M tests/Feature/Shop/ShopDeliveryTest.php
?? resources/js/shop/delivery-correction.js
?? resources/views/shop/partials/delivery-correction.blade.php
?? docs/deliveries/implemented.md
```
Pre-existing (owner): `docs/deliveries/README.md`, the staged archive
renames, `?? docs/deliveries/plan.md`.
`git diff --stat` (tracked files): 9 files, 213 insertions, 317 deletions;
plus the two new untracked files (286 + 47 lines). Nothing committed. The
two new files must be `git add`ed with the rest.

## Notes for Planner
- **Focus after "tap the number" on the typed field.** On the summary (and
  so, same code, on the scan page) `focusField('editQty')` ran but the
  extension's next keystrokes went to the button and
  `document.activeElement` was `BUTTON`, even though the field showed a focus
  ring. It may be the automation (synthetic clicks do not always move focus
  the way a finger does) rather than the page; a real tablet check of "tap
  the number, type, Set" on either screen would settle it. If real, the fix
  is in the shared part, once.
- **The summary's totals do not change for a no-invoice session** (every row
  is "unexpected" whatever its quantity). The owner's check in step 7 of the
  plan expected "the totals and the row's pill update"; on a session with
  invoice lines a short row corrected to its expected figure does change
  both (`test_a_summary_correction_changes_the_totals` pins it server-side).
- **Duplicate getters** in `delivery-summary.js` (Deviation 3): removing
  `delId` / `supplierId` there would leave one definition; trivial follow-up.
- **The correction card on the summary has no "scanned so far / invoice"
  facts** the scan prompt shows; the row's meta line ("Expected · scanned")
  is below it. Fine for discrepancies; mentioned in case the owner wants the
  expected figure on the card itself.
- **Chrome tab**: I closed the tab I opened; the owner's own tab (on a scan
  screen for supplier 37) was left as it was.
