# Delivery scan: add items without a barcode, and type a quantity or weight — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-10-02

## Baseline
HEAD: 9f796b28
Pre-existing dirty files:
```
 M .env.example
 M CLAUDE.md
 M app/Providers/AppServiceProvider.php
 M composer.json
 M composer.lock
 M config/services.php
 M config/shop.php
 M docs/FEATURES_INDEX.md
 M docs/design/shop-mode/README.md
 M docs/features/invoice-parser-integration.md
 M docs/features/shop-mode.md
R  docs/planImp/implemented.md -> docs/planImp/archive/2026-09-28-shop-mode-cycle-33/implemented.md
R  docs/planImp/plan.md -> docs/planImp/archive/2026-09-28-shop-mode-cycle-33/plan.md
 M docs/vouchers/README.md
 D docs/vouchers/implemented.md
 D docs/vouchers/plan.md
 M docs/vouchers/planimp.md
 M phpunit.xml
 M public/images/shop-icons.svg
 M resources/css/app.css
 M resources/css/shop.css
 M resources/views/components/shop/topbar.blade.php
 M resources/views/layouts/admin.blade.php
 M routes/web.php
 M scripts/invoice-parser/parsers/sonett.py
 M scripts/invoice-parser/tests/test_sonett.py
?? app/Http/Controllers/HelpController.php
?? app/Services/BookStack/
?? docs/BookStack/
?? docs/features/sops-bookstack.md
?? docs/planImp/findings/
?? docs/planImp/handover-2026-09-30.md
?? docs/shop_new/
?? docs/vouchers/archive/2026-09-30-cycle-4-admin-tools/
?? resources/views/components/help-button.blade.php
?? resources/views/components/shop/help-button.blade.php
?? resources/views/help/
?? resources/views/shop/help.blade.php
?? scripts/invoice-parser/tests/fixtures/sonett/2026-08-24_S26-0824-2606.txt
?? tests/Feature/Help/
```
Note: `docs/features/shop-mode.md` and `resources/css/shop.css` were already
modified by the BookStack track; this task adds to them without touching
those changes.

## Steps

### 1. Store quantities rounded to 3 decimals — done
Changed: `app/Http/Controllers/DeliveryLegacyController.php`
(`incrementScanQuantity()` `$newQuantity = round(…, 3)`;
`updateScannedQuantity()` `$quantity = round((float) $validated['quantity'], 3)`),
`tests/Feature/Shop/ShopDeliveryTest.php` (two new tests).

Before the controller change:
```
  ✓ weights accumulate without a floating point tail                     1.23s
  ⨯ a corrected quantity is rounded to three decimals                    0.05s
  Failed asserting that 2.34567 is identical to 2.346.
```
The float-tail test passed without the change (see Deviations). After:
```
  Tests:    32 passed (182 assertions)
```

### 2. Shared quantity helpers — done
Changed: `resources/js/shop/quantity.js` (new; did not exist),
`resources/js/shop/delivery-scan.js` (`stockText()` delegates),
`resources/js/shop/delivery-summary.js` (`quantityText()` method; `label()`
and `meta()` use it).
Check output:
```
✓ built in 8.06s
resources/js/shop/delivery-scan.js:28:import { quantityText } from './quantity.js';
resources/js/shop/delivery-scan.js:398:        return quantityText(value);
resources/js/shop/delivery-summary.js:12:import { quantityText } from './quantity.js';
resources/js/shop/delivery-summary.js:126:    quantityText(value) {
resources/js/shop/delivery-summary.js:133:                return `Short ${quantityText(row.expected - row.scanned)}`;
resources/js/shop/delivery-summary.js:135:                return `Over ${quantityText(row.scanned - row.expected)}`;
resources/js/shop/delivery-summary.js:158:        return `Expected ${expected} · scanned ${quantityText(row.scanned)}`;
resources/js/shop/quantity.js:12:export function quantityText(value) {
resources/js/shop/quantity.js:26:export function parseQuantity(text, { allowZero = false } = {}) {
```
(`parseQuantity` is imported into `delivery-scan.js` in step 4.)

### 3. Format every quantity shown on the scan and summary screens — done
Changed: `delivery-scan.blade.php` ("Scanned so far" →
`pending ? stockText(pending.scannedSoFar) : ''`; row quantity →
`stockText(row.scanned)`), `delivery-scan.js` (`Short:` / `Over:` toasts),
`delivery-summary.blade.php` (`quantityText(unitsToAdd)`). The correction
card's value is formatted in step 5, where it becomes a button
(`stockText(editingRow?.scanned)`).
New test `test_quantities_on_the_delivery_screens_are_formatted`.
```
  Tests:    33 passed (187 assertions)
```

### 4. Typed quantity on the scan prompt — done
Changed: `delivery-scan.js` (`pending.typed`, `qtyValue` getter, `unitsToAdd`
/ `addLabel` on `qtyValue`, `commit()` returns early on a null `qtyValue` and
posts it, `typeQuantity()`), `delivery-scan.blade.php` (stepper value is a
`<button class="shop-stepper__value" aria-label="Type the quantity">` except on
a case prompt, which keeps the `<output>`; the field was first in an
`<template x-if="pending && pending.typed !== null">` and is now `x-show` with a
`typedQty` getter/setter (see Deviations, found in the browser); Add is
`:disabled="busy || qtyValue === null"`), `resources/css/shop.css` (one rule
under APP ADDITIONS: `.shop button.shop-stepper__value { width: 100%; }` — the
design reset already removes border/background and inherits font, colour and
cursor; the global `.shop :focus-visible` gives the focus ring).
New test `test_the_scan_prompt_offers_a_typed_quantity`.
```
  Tests:    34 passed (191 assertions)
cmp exit 0     (design-block check, no output)
```

### 5. Typed quantity on the correction card — done
Changed: `delivery-scan.js` (`editTyped`, reset by `$watch('editing')` in
`init()` — covers the close button, Done and a different row;
`adjust()` → `saveQuantity(row, target)` with the `toFixed(3)` target;
`typeCorrection()`; `setCorrection()`), `delivery-scan.blade.php` (value is a
button `stockText(editingRow?.scanned)`; field `x-ref="editQty"` and a primary
"Set" button shown while `editTyped !== null`; "Done" unchanged).
`editTyped` is a top-level property, so `x-model` on it behind `x-show` is safe.
New test `test_the_correction_card_offers_a_typed_quantity`.
```
  Tests:    35 passed (194 assertions)
✓ built in 6.94s
```

### 6. Typeahead: extra parameters and search on an empty query — done
Changed: `resources/js/shop/product-typeahead.js` (`searchParams(q)` default
`{ q, limit: LIMIT }`; `searchWhenEmpty: false`; `total` state set from
`meta.total`, reset to 0 where the file empties `results`; header comment).
Callers: `requests.js`, `request-edit.js` (neither defines `total`,
`searchParams` or `searchWhenEmpty`). `/shop/find` uses its own search
(`find-product.js`), not this file.
```
✓ built in 7.48s
php artisan test tests/Feature/Shop →  Tests:    277 passed (1340 assertions)
```
Browser part of the check: done in Verification 6f.

### 7. "No barcode? Find by name" on the scan screen — done
Changed:
- `app/Http/Controllers/Shop/DeliveryController.php`: `scan()` passes
  `canSearch` = `hasPermission('products.view')`.
- `delivery-scan.js`: `mix(productImages(), productTypeahead(), {…})`; state
  `manual`, `everywhere`, `searchWhenEmpty: true`; `searchParams(q)` as in the
  plan; `openManual()`, `closeManual()` (also zeroes `total`), `filterManual()`,
  `searchEverywhere()`, `resetManual()` (the "back to the supplier list" step
  after a manual commit/cancel), `pickResult(p)` override →
  `lookup(p.code, true)`. `onScan(code)` → `lookup(code, false)`; `lookup()`
  sets `typed: manual ? '' : null` and `manual`, focuses `$refs.qty` in the
  existing `$nextTick` after `scrollIntoView`, and wraps the prompt's
  `announceDone()` in `if (! manual)`. `commit()` reads `item.manual` (the
  captured `pending`) and `cancelPending()` reads it before clearing.
  `this.announceSaved();` count still 3; no `announceSaved` between
  `this.pending = {` and the next `this.announceDone();`.
- `delivery-scan.blade.php`: root `data-search-url`; inside the not-completed
  branch, after `<x-shop.scan-input>`, `@if ($canSearch)` ghost button and the
  `x-ref="manual"` card (header + close, `shop-search` filter with
  `x-ref="filter"`, result rows copied from the request form plus a meta line
  `code · Stock n` and a muted "Not stocked" pill, "Showing the first N, type
  to narrow", "No products match", "Search all products"). The placeholder is
  `:placeholder="everywhere ? 'Search all products' : {{ Js::from('Filter <supplier> products') }}"`.
- Tests: `test_scan_screen_offers_find_by_name`,
  `test_find_by_name_is_hidden_without_products_view`,
  `test_find_by_name_is_hidden_on_a_completed_session`,
  `test_a_product_picked_by_name_is_recorded_by_its_code_with_a_weight`.
```
✓ built in 7.55s
php artisan test tests/Feature/Shop →  Tests:    281 passed (1352 assertions)
  (includes ConfinePinSessionTest and ShopViewContractTest, both PASS)
```

### 8. Completion creates a missing stock record — done
Changed: `DeliveryLegacyController.php` (`use App\Models\StockCurrent`; the two
loops are now one `foreach ([$matchedItems, $extraItems] …)` calling
`private addDeliveredStock(object $item, array &$results)`; on 0 affected rows
`StockCurrent::create([... 'LOCATION' => '0', 'ATTRIBUTESETINSTANCE_ID' => null])`,
`productsCreated++`, then counted in `productsUpdated`/`unitsAdded`; a throw
logs a warning and counts `productsSkipped`. `undoComplete()` untouched.),
`tests/Concerns/CreatesLegacyDeliveryPosTables.php` (nullable `LOCATION`,
`ATTRIBUTESETINSTANCE_ID`), `ShopDeliveryTest.php` (helper
`seedProductWithoutStockRecord()` + the three tests).
```
php artisan test tests/Feature/Shop/ShopDeliveryTest.php →  Tests:    42 passed (221 assertions)
php artisan test --filter=Delivery                         →  Tests:    62 passed (312 assertions)
```
Dev POS insert check (Risk "STOCKCURRENT key"), in a rolled-back transaction:
```
product without row: e1cc64ab-bbb7-11ef-b648-10c37b4d894e
inserted: [{"LOCATION":"0","PRODUCT":"e1cc64ab-…","ATTRIBUTESETINSTANCE_ID":null,"UNITS":2.5}]
after rollback rows: 0
```

### 9. Documentation — done
Changed: `docs/features/shop-mode.md` (file was already modified by the
BookStack track; those changes are untouched). Under the existing quantity-cap
note: the 3-dp storage line and the created-stock-record line (office page
included, undo → 0). New subsection `### Deliveries: items without a barcode`
directly after it: the button and who sees it, supplier list first and
"Search all products" fallback, the pick → same prompt → typed field flow,
the list staying open, typed amounts on the prompt (not on a case prompt) and
the correction card, and the 4-digit / 3-decimal rule and why.
Check: read against the built markup and JS (button visibility, when "Search
all products" shows, Set/Done). Not yet read against the screen in a browser
(see Verification 6).

## Deviations

- Step 1: `test_weights_accumulate_without_a_floating_point_tail` passes
  without the controller change on this PHP build. Starting from 6:
  `6+1.94 = 7.9399999999999995` (the same double as 7.94),
  `+3.74 = 11.68`, `+0.111 = 11.791` — no tail survives this particular
  sequence. A tail does show for other starts, e.g. `1.94+3.74+0.111 =
  5.7909999999999995`. Test kept as written; rounding kept, per the plan.
- Step 7: the plan said to reset `query`/`everywhere` and `search()` after a
  manual commit or cancel; that sits in a small `resetManual()` method called
  from both, after `announceSaved()` in `commit()` and after it in
  `cancelPending()`, so neither the `announceSaved` count nor the
  prompt/`announceDone` order changes.
- Step 7: the filter placeholder is built in Blade with `Js::from(...)` (the
  supplier name falls back to "supplier" when the session has none) rather
  than adding a `data-supplier-name` attribute.
- Step 9: `shop-mode.md` has no delivery section. The two one-liners went
  under the quantity-cap note (in "Camera scanning", as the plan anchors them)
  and the new subsection follows it as `### Deliveries: items without a barcode`.
- **Step 4, found in the browser pass: the typed field is `x-show` with a
  null-safe getter/setter, not `x-if`.** The plan offered both forms. With
  `x-if`, pressing Enter in the field ran `commit()` from an element that
  `pending = null` then removed. For the rest of that run Alpine's `$root`
  resolved to nothing, so `resetManual()` → `search()` threw on
  `$root.dataset` and its `catch` emptied `results`. After every
  Enter-confirmed manual item the list came back **empty** (tapping Add worked,
  because that button is never removed). Now
  `<div class="shop-field" x-show="pending?.typed != null" x-cloak>` with
  `x-model="typedQty"`, where `get typedQty()` returns `pending?.typed ?? ''`
  and `set typedQty(v)` writes only while `pending` exists. The view comment
  records why.
- **Steps 4, 5 and 7, found in the browser pass: focus waits a frame.**
  Measured with a `focus()` spy: when `$nextTick` runs, the prompt section is
  still `display:none` (`x-show` reveals it later), and `focus()` on a hidden
  element is ignored. It worked once and failed the next time. All three typed
  fields now go through `focusField(ref)` =
  `$nextTick(() => requestAnimationFrame(() => focus + select))`, the same
  frame wait `scan-input.js` uses. After the fix, focus landed on every pick and
  every tap of the number, on desktop and at 390 px.
- Verification 6: run as the signed-in admin account (`jonathanE`), not
  `katelyn`. On dev every role holds both `products.view` and
  `deliveries.process` (plan Context), so the screens are the same; it avoided
  entering a PIN through the automation. Garlic and a second Slieve Bloom
  entry were used for extra Enter-path tests and corrected back to 0, so the 6e
  figures are the plan's.
- Verification 5: the plan expected 13 new tests; as written it lists 12
  (2 + 1 + 1 + 1 + 4 + 3). 12 were added: 921 + 12 = 933.

## Verification

1. `./vendor/bin/pint --dirty`
```
  FIXED   .................................... 13 files, 1 style issue fixed
  ✓ app/Http/Controllers/DeliveryLegacyController.php  unary_operator_spaces,…
```
   Only this task's file was changed; no other track's file was touched.
2. `npm run build` → `✓ built in 7.44s`
3. Design-block `cmp` → no output (exit 0).
4. `php artisan test tests/Feature/Shop` → `Tests:    284 passed (1367 assertions)`
5. `php artisan test` → `Tests:    15 failed, 933 passed (4127 assertions)`.
   The 15 are the baseline set: CashReconciliationTest ×3,
   FruitVegLabelPrintingTest ×2, ProductTest ×2, TestScraperControllerTest ×1,
   UdeaScrapingServiceTest ×7. Nothing else fails.
6. Browser on dev (`http://osmanager.local`), second session after the
   extension connected. The first attempt found the two bugs above; everything
   below is **after** the fixes (rebuilt, page reloaded). The console stayed
   clean throughout (`read_console_messages` onlyErrors: none on the scan
   page, the summary, `/customer-requests` or `/shop/find`).
   a. Started a new Mossfield session (`6a46a648-e246-46b2-a54f-954b8c6f610b`).
      "No barcode? Find by name" is shown; tapping it lists **10** Mossfield
      products (Moss Slieve Bloom … Mossfield Tomato). The filter took focus
      (non-touch). ✓
   b. Mossfield Mature: prompt above the scan field, empty "Quantity or
      weight" field **focused**, Add disabled (`addDisabled: true`, label
      "Add"). Typed `4.35` → button "Add 4.35"; Enter → row 4.35. Picked it
      again, typed `3,2` → "Add 3.2" → row **7.55**. Enter path re-tested after
      the fix (Garlic, `1`, Enter): row added and the supplier list came back
      with 10 results. Typed `5391521170057` into the field, then Enter:
      field focused, `addDisabled: true`, nothing recorded, prompt stays open. ✓
   c. `slieve` → `["Moss Slieve Bloom"]`. `cheddar` → "No products match" and
      "Search all products"; tapped → 11 products from other suppliers, with
      "Not stocked" pills. Picked Lye Cross Farm Cheddar matured 200g, added
      `1`. Correction card: tapped the number (field focused, old value
      selected), typed `0`, Set → row removed, card closed (`editing: null`).
      Garlic removed the same way, with Enter instead of Set. ✓
   d. Closed the list (scan field took focus, button back). Typed
      `5391521170057` + Enter in the scan field: stepper prompt as before;
      `+` → qty 2; tapped the number (field focused, "2" selected), typed
      `12`, Enter → row 12, focus back on the scan field. Tapped the row,
      tapped the number, typed `2.5`, Set → row **2.5**. ✓
   e. Summary: "Units to add" renders **10.05** (`unitsToAdd` 10.05);
      discrepancy meta lines read "Expected — · scanned 7.55" / "… 2.5". Not
      completed. ✓
   f. `/customer-requests` → New request → typed `che` → 20 results
      (`total` 221); picked the first: `picked` set, query and results
      cleared; sheet left unsubmitted. `/shop/find` → `mossfield` → 65 found;
      opened Mossfield Mature's card. No console errors. ✓
   g. Same-origin iframe, inner 388 × 842. a–d repeated: list of 10; pick Moss
      Slieve Bloom → field **focused**, Add button at 521–593 px of 842
      (visible without scrolling); `4.35` Enter → row added, list back;
      `cheddar` → "No products match" → Search all products (11); correction to
      0 with Enter → row removed; closed the list → scan field focused; scan →
      stepper prompt ("Scanned so far 2.5"), tapped the number → field
      focused, Add bottom at 653 of 842; `3` Enter → milk 5.5. No horizontal
      overflow (`scrollWidth <= innerWidth`). ✓
   One oddity, not reproduced: on the first desktop correction to 2.5 the field
   showed `w2.5` (Set correctly refused it). Repeating the same taps showed the
   field focused with `12` selected and typing gave `2.5`. Most likely a stray
   keystroke from the automation; noted in case it is seen again.
7. Dev state: the session created in 6a was deleted by its id:
```
session: [{"ID":"6a46a648-e246-46b2-a54f-954b8c6f610b","supID":"28","dateUpload":"2026-10-02 10:29:30","status":0}]
items: [{"barcode":"5016","quantity":"7.55"},{"barcode":"5391521170057","quantity":"5.50"}]
deleted items=2 session=1
left: 0 / 0
```
   (`DeliveryScanItem` model for the items; `deliveriesScan` has no model, so
   it was deleted with the POS query builder, guarded on `status = 0`.) Nothing
   was completed, so stock is untouched. The earlier Mossfield session
   `b98fcad8` (08:41 today, not created by this task) was left alone. The Step 8
   dev-POS insert check was rolled back (`after rollback rows: 0`). The New
   request sheet on `/customer-requests` was never submitted.
8. After the two fixes: `npm run build` ✓, `php artisan test tests/Feature/Shop`
   → `Tests:    284 passed (1367 assertions)`, design-block `cmp` exit 0,
   `this.announceSaved();` count 3. No PHP changed after the full-suite run
   in item 5.

## Files changed

This task (all new to the working tree except where marked):
```
 M app/Http/Controllers/DeliveryLegacyController.php
 M app/Http/Controllers/Shop/DeliveryController.php
 M docs/features/shop-mode.md            (pre-existing dirty; added to)
 M resources/css/shop.css                (pre-existing dirty; one rule added in APP ADDITIONS)
 M resources/js/shop/delivery-scan.js
 M resources/js/shop/delivery-summary.js
 M resources/js/shop/product-typeahead.js
 M resources/views/shop/delivery-scan.blade.php
 M resources/views/shop/delivery-summary.blade.php
 M tests/Concerns/CreatesLegacyDeliveryPosTables.php
 M tests/Feature/Shop/ShopDeliveryTest.php
?? resources/js/shop/quantity.js
?? docs/shop_new/implemented.md          (inside the already-untracked docs/shop_new/)
```
Every other entry in `git status --short` is in the Baseline list and was not
touched.

## Notes for Planner

- **Worth carrying into the README's Alpine traps:** a handler on an element
  that `x-if` can remove loses `$root` (and with it every `$root.dataset`
  getter) once its own action removes that element, even within the same
  async call. And `x-show` reveals an element *after* `$nextTick`, so focusing
  something just shown needs a frame (`requestAnimationFrame`). Both cost a
  browser round to find (Deviations).
- At phone width the open "Find by name" list pushes the Items list below it,
  so after adding a typed item the row is not in view (the toast still
  confirms it). For a 10-product supplier that is one screen of list; fine for
  the cheese day, but a phone user checking rows will scroll.
- At 390 px a result row with the "Not stocked" pill squeezes the name into a
  narrow column ("Vegan Deli Cheddar 160g" wraps over three lines). Only seen
  in "Search all products" results.
- The items list shows `row.code` (the supplier code) where it has one, e.g.
  Garlic shows `3` and the milk `002`, while the by-name list shows
  `PRODUCTS.CODE` (5009). This was already the case, but the two lists now
  sit side by side.
- The "Not on this invoice" toast fires after every typed item on a supplier
  with no invoice lines (existing `reportRow()` behaviour). On the cheese day
  that is every item; the notice above the list already says nothing can be
  checked.
- During the 250 ms filter debounce the previous results stay on screen, so
  for a moment "cheddar" shows the "slieve" hit. Cosmetic.
- A scan arriving while a by-name prompt is open with an empty or invalid
  field: `commit()` returns early (no valid quantity), and the scan replaces
  the prompt without recording the picked item. The list is not reset in that
  case (the replacing prompt is a scan prompt). Harmless, but the picked item
  is silently dropped; a toast could say so if it matters.
- The correction card shows two primary block buttons while typing ("Set" and
  "Done"), as the plan specified. "Done" could become ghost while
  `editTyped !== null` if that reads badly on the floor.
- `requests.js` `unpick()` empties `results` without zeroing the typeahead's
  new `total`. Nothing on that page reads `total`, so it was left alone (file
  not in this plan).
- `expectedLabel(row)` (`/ 12`) and the prompt's "Invoice" figure are still
  raw; the server rounds fractional expected figures, so they should not carry
  a tail, but they do not go through `stockText()`.
- `parseQuantity` treats `1,234` as `1.234` (single comma = decimal comma),
  as the plan says.
- Step 1's float-tail test does not fail on the plan's sequence (Deviations);
  a sequence starting from 0, e.g. `1.94, 3.74, 0.111` on a new barcode,
  would.
