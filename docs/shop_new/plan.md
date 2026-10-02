# Delivery scan: add items without a barcode, and type a quantity or weight

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-10-02

## Goal

Some deliveries cannot be scanned. The weekly Mossfield cheese delivery is the
case this is built for: the wheels have no barcode, and what is received is a
weight per wheel (4.35 kg), not a count. Today the Shop delivery screen can do
neither: a product can only be reached by scanning it, and the quantity is a
stepper that moves by 1. Staff have been using the office side instead, in
more than one way.

After this task the Shop delivery scan screen has one small "No barcode? Find
by name" button. It opens a list of that supplier's products (with a filter,
and a fallback search of every product). Picking one opens the same quantity
card a scan opens, with a field to type the amount, decimals allowed. The list
stays open between items, so a delivery that is entirely typed (the cheese
day) is pick, type, Add, pick, type, Add. Scanning is untouched and still
works in the same delivery, so nobody has to choose a "mode" up front and
nobody can choose the wrong one.

Typed quantities also become available where a quantity already exists: the
scan prompt (tap the number) and the correction card (tap the number). And
quantities are displayed and stored without floating-point tails, which closes
`docs/planImp/findings/2026-09-30-summary-units-float.md`.

Completing a delivery also stops losing stock for a product that has no stock
record yet: the record is created with the delivered amount. This applies to
the office page as well, because both screens complete through the same code.

## Context

Owner decisions (2026-10-02):

- One delivery, no scan/manual choice at the start. A small action on the scan
  screen.
- The list shows the supplier's linked products by default, with a "search all
  products" fallback for a product not linked to the supplier.
- The cheese is stocked in kg: typing 4.35 must add 4.35 to stock. (Dev agrees:
  `Mossfield Mature`, code 5016, stock 4.68, price 28.50.)
- On the cheese day everything is typed, nothing is scanned. Milk and yoghurt
  come another day and are barcoded.
- A delivered product with no stock record gets one at completion, holding the
  delivered amount, on the office page as well as in the Shop. Undoing the
  completion brings that record back to 0 (it is not deleted).

What the code already does:

- A delivery session is rows of `(delID, barcode, quantity)` in the POS table
  `deliveriesScanItems`; `quantity` is a `double`. `barcode` is `PRODUCTS.CODE`.
  Nothing distinguishes a scanned row from a typed one, and nothing needs to.
- `DeliveryLegacyController::incrementScanQuantity()` (`delivery-legacy.scan-increment`)
  looks a product up by `PRODUCTS.CODE` (LEFT JOIN to `supplier_link`, so a
  product not linked to the supplier still resolves), validates `quantity` as
  `nullable|numeric|min:0|max:9999`, and adds it to the row's total. A zero
  quantity is a lookup that writes nothing. So a product picked by name is
  recorded by posting its `code` exactly as a scan does. No new endpoint.
- It computes `$newQuantity = $currentTotal + $effectiveIncrement` on PHP
  floats and stores the result, so repeated weights can store a tail
  (1.94 + 3.74 = 5.680000000000001).
- `updateScannedQuantity()` (`delivery-legacy.update-quantity`) sets an
  absolute quantity; 0 deletes the row.
- `completeDelivery()` increments `STOCKCURRENT.UNITS` per row by that row's
  quantity, in two loops (matched items, then extra items). When the
  increment affects no row, because the product has no `STOCKCURRENT` row at
  all, it counts the product in `productsSkipped` and the delivered amount is
  lost. (A row holding 0 is fine; this is about a missing row.) On dev 11 of
  10,653 products have no row; 7 of them are stocked, none are Mossfield.
  The office page shows "N (no stock record)" from `productsSkipped`
  (`resources/views/delivery-legacy/match.blade.php` line 546).
- Elsewhere a missing row is created, not skipped:
  `StockingController` uses `StockCurrent::updateOrCreate(['PRODUCT' => $id], ['UNITS' => …, 'LOCATION' => '0', 'ATTRIBUTESETINSTANCE_ID' => null])`.
  In the POS, `STOCKCURRENT.LOCATION` is NOT NULL and every row is at
  location `'0'`.
- `undoComplete()` decrements the same rows by the same amounts and needs no
  change: once completion has created the row, undo takes it back to 0.
- The test schema for `STOCKCURRENT` in
  `tests/Concerns/CreatesLegacyDeliveryPosTables.php` has only `PRODUCT` and
  `UNITS`.
- `GET /api/products/search` (`api.products.search`, permission
  `products.view`, already in `config('shop.pin_session_routes')`) accepts
  `q`, `stocked`, `supplier_id`, `per_page` (max 50). Measured on dev:
  `supplier_id=28&stocked=1` with an empty `q` returns Mossfield's 10 stocked
  products in about 60 ms; `stocked=0` returns all 20 linked. A catalogue-wide
  `stocked=0` search for "cheddar" takes about 66 ms. Each result has `id`,
  `code`, `name`, `stock_units`, `is_stocked`, `has_stock_record`,
  `image_url`. `meta.total` is the full count.
- `resources/js/shop/product-typeahead.js` is the shared search-as-you-type
  part (members: `query`, `results`, `searching`, `searchUrl`, `search()`,
  `pickResult(p)`, `pickFirst()`; the caller supplies `onPick(p)`). It reads
  the endpoint from `data-search-url` on the page root, sends
  `{ q, limit: 8 }`, and returns early on an empty query. (`limit` is not a
  parameter the endpoint knows, so callers get the default 20. Not this task's
  problem; see Out of scope.)
- `resources/js/shop/delivery-scan.js` is `mix(productImages(), {…})`. None of
  the typeahead's member names are used in it. `mix()` copies property
  descriptors and later parts override earlier ones.
- `resources/views/shop/delivery-scan.blade.php`: the prompt card
  (`x-ref="prompt"`) is first in the left stack, then `<x-shop.scan-input inline>`
  (not rendered when the session is completed), then the notice/progress, then
  the correction card (`x-ref="correct"`).
- `scan-input.js` `capture()` leaves keystrokes alone when a text-entry
  element has focus (`isTextEntry()`), so a normal `<input>` on this page
  receives its own typing. Its `done()` (fired by the page's
  `shop-scan-done` event) calls `focus()` on the scan field inside a
  `requestAnimationFrame`, which would take focus away from a field the page
  has just focused.
- `stockText(value)` in `delivery-scan.js` already formats a number to at most
  3 dp with trailing zeros trimmed. `delivery-summary.js` has no such helper
  and renders `unitsToAdd`, `label()` and `meta()` from raw doubles.
- Roles on dev: employee, manager and admin all hold both `products.view` and
  `deliveries.process`.
- Mossfield on dev: supplier id `28`, 20 linked products, none flagged
  `ISSCALE`, and no invoice lines in the `delivery` table, so a Mossfield
  session shows the "No invoice lines" notice and rows have no expected figure.
  Because nothing marks a product as weighed, typed entry is offered for every
  product rather than detected.

Existing tests that pin this screen's shape (`tests/Feature/Shop/ShopDeliveryTest.php`),
which the steps below are written to keep passing unchanged:

- `test_the_delivery_scan_page_reopens_the_camera_but_not_while_the_prompt_is_open`:
  exactly three occurrences of `this.announceSaved();` in `delivery-scan.js`;
  and between the first `this.pending = {` and the next `this.announceDone();`
  there is no `announceSaved`.
- `test_the_quantity_prompt_comes_before_the_scan_field`: `x-ref="prompt"`
  precedes `shop-scan__input`, and the JS contains
  `this.$refs.prompt?.scrollIntoView({ block: 'nearest' })`.
- `test_scan_screen_renders_the_quantity_prompt`: the page contains
  `Quantity to add`, `commit()`, `cancelPending()`, `bump(1)`, `bump(-1)`.
- `test_scan_screen_links_to_the_summary_and_has_no_window_enter_handler`.

Test baseline taken 2026-10-02 with `php artisan test`: **15 failed, 921
passed**. The 15 are pre-existing (`UdeaScrapingServiceTest` ×7,
`CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2,
`TestScraperControllerTest` ×1).

## Constraints

- Read `docs/shop_new/README.md` first. Its rules apply: Shop views use only
  `x-shop.*` components and `shop-*` classes; the design block of
  `resources/css/shop.css` stays byte-identical to
  `docs/design/shop-mode/shop.css` and new rules go only between the
  `APP ADDITIONS` markers; no Alpine `@` shorthand that is a Blade directive;
  nullable data behind `x-show` needs `?.`; shared parts are composed with
  `mix()`.
- Scanning must behave exactly as it does now: the camera pauses on detection
  and resumes on `shop-scan-saved`, never while a prompt is open; a failed
  save keeps the camera paused. Do not change `scan-input.js` or the
  `scan-input` component.
- No new route and no new search endpoint. Product search is
  `GET /api/products/search` through `product-typeahead.js`.
- A typed quantity is a positive number of at most 4 whole digits and 3
  decimals, so a barcode typed or wedge-scanned into the quantity field can
  never be saved as a quantity. The server cap of 9999 stays.
- Quantities are stored rounded to 3 decimals.
- Do not rewrite or delete existing tests. If one of the four listed above
  fails, the change is wrong, not the test; set `BLOCKED` if it cannot be
  kept.
- The working tree is already dirty with other tracks' work (BookStack help,
  Sonett parser): `resources/css/shop.css`, `config/shop.php`,
  `resources/views/components/shop/topbar.blade.php`, `routes/web.php` and
  others are modified by them. Record the baseline and do not revert or
  reformat those changes.
- Do not commit, push or deploy.
- Every response ends with `mpg123 /home/jon/Music/notification.mp3`.

## Out of scope

- A scan/manual choice on the Deliveries list, or any change to
  `resources/views/shop/deliveries.blade.php`.
- A "weighed" flag, a units/kg split on the summary, or any use of
  `PRODUCTS.ISSCALE` (the cheese is not flagged, so it could not be relied on).
- Typed quantities on a case/outer-barcode prompt (a case count stays a whole
  number on the stepper).
- Remembering that the by-name list was open across page loads.
- The office page `delivery-legacy/match` and its views.
- `scan-input.js`, `barcode-scanner.js`, the camera timing constants.
- The typeahead's `limit` parameter (the endpoint ignores it); the New request
  page's search, including destocked products there.
- Any change to `undoComplete()` or `calculateUndoPreview()`.
- Creating stock records anywhere except delivery completion.
- Anything in `docs/planImp/`.

## Steps

### 1. Store quantities rounded to 3 decimals

Files: `app/Http/Controllers/DeliveryLegacyController.php`,
`tests/Feature/Shop/ShopDeliveryTest.php`

What:
- In `incrementScanQuantity()`, `$newQuantity = round($currentTotal + $effectiveIncrement, 3);`.
- In `updateScannedQuantity()`, round the validated `quantity` to 3 decimals
  before it is stored and returned.
- Add `test_weights_accumulate_without_a_floating_point_tail`: as the
  employee, post `scan-increment` for barcode `5000000000024` (seeded at 6 on
  session `d-1`, supplier `999`) with quantities `1.94`, then `3.74`, then
  `0.111`. Assert the last response's `newQuantity` is exactly `11.791` and
  the single stored row's `quantity` is exactly `11.791` (`assertSame` on a
  float cast, not `assertEquals` with a delta).
- Add `test_a_corrected_quantity_is_rounded_to_three_decimals`: PATCH
  `update-quantity` with `quantity` `2.34567`; the stored row is `2.346`.

Check: `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` passes,
including the two new tests. Before making the controller change, run the
first new test once and record that it fails (it proves the tail is real on
this PHP build; if it passes without the change, say so in Deviations and keep
the rounding anyway).

### 2. Shared quantity helpers

Files: `resources/js/shop/quantity.js` (new; look first),
`resources/js/shop/delivery-scan.js`, `resources/js/shop/delivery-summary.js`

What:
- `quantity.js` exports two plain functions:
  - `quantityText(value)`: the body of today's `stockText()` (null/undefined
    as 0; integers plain; otherwise at most 3 dp, trailing zeros trimmed).
  - `parseQuantity(text, { allowZero = false } = {})`: trim; replace a single
    `,` with `.` (staff may type a decimal comma); accept only
    `/^\d{1,4}(\.\d{1,3})?$/`; return the `Number`, or `null` when the text
    does not match, or when it is 0 and `allowZero` is false.
- `delivery-scan.js`: `stockText(value)` stays as a method (the view calls
  it) and returns `quantityText(value)`; move its doc comment's substance to
  `quantity.js`.
- `delivery-summary.js`: add a `quantityText(value)` method delegating to the
  helper; `label(row)` and `meta(row)` format their figures with it
  (`Short ${quantityText(row.expected - row.scanned)}` and so on).

Check: `npm run build` completes with no error. `grep -n "quantityText\|parseQuantity" resources/js/shop/*.js`
shows the definitions in `quantity.js` and uses in both delivery modules.

### 3. Format every quantity shown on the scan and summary screens

Files: `resources/views/shop/delivery-scan.blade.php`,
`resources/views/shop/delivery-summary.blade.php`,
`resources/js/shop/delivery-scan.js`

What:
- Scan screen: "Scanned so far" (`pending?.scannedSoFar`), the row quantity
  (`row.scanned ?? 0`), the correction card's value
  (`editingRow?.scanned ?? 0`) and the `Short:` / `Over:` toasts in
  `reportRow()` all go through `stockText()`. Keep `?.` on anything behind an
  `x-show`.
- Summary screen: "Units to add" renders `quantityText(unitsToAdd)`.
- Rename nothing the existing tests look for.

Check: add to `ShopDeliveryTest` `test_quantities_on_the_delivery_screens_are_formatted`:
the scan page HTML contains `stockText(row.scanned)` and
`stockText(pending.scannedSoFar)` (or the exact expressions you wrote; assert
on what is there), and the summary page HTML contains
`quantityText(unitsToAdd)`. `php artisan test tests/Feature/Shop/ShopDeliveryTest.php`
passes.

### 4. Typed quantity on the scan prompt

Files: `resources/js/shop/delivery-scan.js`,
`resources/views/shop/delivery-scan.blade.php`, `resources/css/shop.css`

What:
- `pending` gains `typed`: `null` means the stepper is in charge; a string
  means the person is typing and the string is what they have typed.
- New getter `qtyValue`: `pending.typed === null ? pending.qty : parseQuantity(pending.typed)`
  (so `null` when the typed text is not a valid positive quantity).
- `unitsToAdd` and `addLabel` use `qtyValue`. `addLabel`: `'Add'` when
  `qtyValue` is null; unchanged wording for a whole number
  (`Add 3 units`, `Add 2 cases · 12 units`); `Add ${quantityText(n)}` for a
  fraction (no "units": it is a weight).
- `commit()`: return early when `qtyValue` is null; post `quantity: this.qtyValue`.
  The `busy` guard and everything after the post stay as they are.
- New method `typeQuantity()`: only when `pending.scanType !== 'case'`; sets
  `pending.typed = String(pending.qty)`, then on `$nextTick` focuses and
  selects `$refs.qty`.
- View, inside the prompt card, replacing only the middle of the stepper:
  - when `pending?.typed == null`: the stepper as today, but the value is a
    `<button type="button" class="shop-stepper__value" aria-label="Type the quantity">`
    calling `typeQuantity()` (keep an `<output>` for a case prompt, where
    typing is not offered). `bump(1)` and `bump(-1)` stay on their buttons.
  - when `pending?.typed != null`: one field,
    `<input class="shop-input" type="text" inputmode="decimal" autocomplete="off" enterkeyhint="done" x-ref="qty" x-model="pending.typed">`
    with `x-on:keydown.enter.prevent="commit()"`, in a `shop-field` whose
    label reads "Quantity or weight". Because `pending` can be null while the
    card is hidden, bind with a guarded getter/setter pair or an `x-if`
    template rather than `x-model="pending.typed"` on a hidden element; pick
    whichever renders without a console error when the page loads with no
    prompt open.
  - The Add button is `:disabled="busy || qtyValue === null"`.
- CSS, under APP ADDITIONS only: whatever `button.shop-stepper__value` needs
  to look the same as the `<output>` did (no border, full width, inherits font
  and colour, pointer cursor, the shop focus ring). Nothing else.

Check:
- `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` passes (the four
  pinned tests in Context, unchanged).
- New test `test_the_scan_prompt_offers_a_typed_quantity`: page contains
  `typeQuantity()`, `inputmode="decimal"` and `qtyValue === null`.
- The design-block check from `README.md` prints nothing:
  `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css`.

### 5. Typed quantity on the correction card

Files: `resources/js/shop/delivery-scan.js`,
`resources/views/shop/delivery-scan.blade.php`

What:
- New state `editTyped` (`null` or a string), reset to `null` whenever
  `editing` changes or the card closes.
- Extract the PATCH in `adjust()` into `saveQuantity(row, target)`; `adjust()`
  calls it with `Math.max(0, Number(((row.scanned ?? 0) + delta).toFixed(3)))`.
- `typeCorrection()`: `editTyped = stockText(editingRow.scanned ?? 0)`, focus
  and select `$refs.editQty` on `$nextTick`.
- `setCorrection()`: `parseQuantity(editTyped, { allowZero: true })`; null
  does nothing; otherwise `saveQuantity(editingRow, value)` and
  `editTyped = null`. (0 deletes an unexpected row, as the stepper already
  can; the existing "card closes when its row vanishes" logic in `load()`
  covers it.)
- View: the correction card's value becomes a button calling
  `typeCorrection()`; while `editTyped !== null` the stepper is replaced by
  the same kind of field as step 4 (`x-ref="editQty"`, Enter calls
  `setCorrection()`) and a primary "Set" button; "Done" stays.

Check: new test `test_the_correction_card_offers_a_typed_quantity`: page
contains `typeCorrection()` and `setCorrection()`. Suite for the file passes.

### 6. Let the typeahead take extra parameters and search on an empty query

Files: `resources/js/shop/product-typeahead.js`

What: two overridable members, with defaults that leave every current caller
exactly as it is.
- `searchParams(q)`, default `return { q, limit: LIMIT };`. `search()` builds
  its `URLSearchParams` from it.
- `searchWhenEmpty: false`. `search()` returns early on an empty query only
  when this is false.
- `search()` also stores `this.total = data.meta?.total ?? this.results.length`
  (add `total: 0` to the state; reset it to 0 wherever `results` is emptied).
- Update the file's header comment to describe the two hooks.

Check: `php artisan test tests/Feature/Shop` passes (requests and find-product
tests read this file's behaviour through their pages). In the browser, the New
request sheet on `/customer-requests` and `/shop/find` still search as before
(type three letters, results appear, pick one, no console error).

### 7. "No barcode? Find by name" on the scan screen

Files: `app/Http/Controllers/Shop/DeliveryController.php`,
`resources/views/shop/delivery-scan.blade.php`,
`resources/js/shop/delivery-scan.js`,
`tests/Feature/Shop/ShopDeliveryTest.php`

What, controller:
- `scan()` passes `canSearch` = `$request->user()->hasPermission('products.view')`.

What, JS (`delivery-scan.js` becomes
`mix(productImages(), productTypeahead(), {…})`):
- State: `manual: false` (the by-name list is open), `everywhere: false`
  (searching all products rather than the supplier's).
- `searchWhenEmpty: true` and
  ```js
  searchParams(q) {
      return this.everywhere
          ? { q, stocked: 0, per_page: 20 }
          : { q, supplier_id: this.supplierId, stocked: 1, per_page: 50 };
  },
  ```
- `openManual()`: `manual = true; everywhere = false; query = '';`, close the
  correction card, `search()`, scroll `$refs.manual` into view on `$nextTick`.
  Do not focus the filter on a touch device (the keyboard would cover a list
  that for Mossfield needs no filtering); on a non-touch device focus it.
- `closeManual()`: `manual = false; everywhere = false; query = ''; results = [];`
  then `announceDone()` so the scan field takes focus back.
- `filterManual()`: called by the filter input (debounced 250 ms); if the
  query is now empty set `everywhere = false`; then `search()`.
- `searchEverywhere()`: `everywhere = true; search()`.
- Override `pickResult(p)` (the typeahead's Enter-picks-first calls it) so it
  does **not** clear `query` or `results`: it calls `this.lookup(p.code, true)`.
  Provide `onPick` only if the typeahead requires it to exist.
- Split `onScan(code)`: keep `onScan(code)` as the scan entry point calling
  `this.lookup(code, false)`; `lookup(code, manual)` is today's body with
  three differences when `manual` is true:
  1. `pending.typed` is `''` (the person must type the amount) and
     `pending.manual` is `true`; for a scan `typed` is `null`.
  2. `this.announceDone()` after the prompt is built is skipped
     (`if (! manual) { this.announceDone(); }`), because it would move focus
     to the scan field; instead focus `$refs.qty` on `$nextTick`, after the
     existing `scrollIntoView`.
  3. The not-found branch is unchanged (it cannot normally happen for a
     picked product).
  Keep the statement order `this.pending = { … }` … `this.announceDone();`
  with no `announceSaved` between them, and keep the total number of
  `this.announceSaved();` statements in the file at three.
- After a successful `commit()` or a `cancelPending()` of a prompt that was
  `manual`: `query = ''; everywhere = false; search();` so the supplier list
  is back for the next item. (Read `item.manual` before `pending` is cleared.)
  The list stays open until the person closes it.

What, view:
- Root `<main>` gains `data-search-url="{{ route('api.products.search') }}"`.
- Inside the not-completed branch, directly after `<x-shop.scan-input …>`,
  and only `@if ($canSearch)`:
  - a ghost button, shown while `! manual`: search icon,
    "No barcode? Find by name", calling `openManual()`;
  - a card `x-ref="manual"`, shown while `manual && ! pending`:
    - header "Find by name" with an `x` icon button (`aria-label="Close"`)
      calling `closeManual()`;
    - a `shop-search` filter input (`type="search"`, `x-model="query"`,
      `x-on:input.debounce.250ms="filterManual()"`,
      `x-on:keydown.enter.prevent="pickFirst()"`, `autocomplete="off"`,
      placeholder "Filter {{ supplier name }} products", or
      "Search all products" while `everywhere`);
    - a `shop-list` of results, each a `<button class="shop-row" type="button">`
      calling `pickResult(p)`, with `<x-shop.product-thumb />`, the name, a
      meta line with the code and `'Stock ' + stockText(p.stock_units)` when
      `p.stock_units !== null`, and a muted pill "Not stocked" when
      `! p.is_stocked`. Copy the row markup from
      `resources/views/shop/partials/request-form.blade.php` lines 46–56;
    - a `shop-meta` line "Showing the first N, type to narrow" when
      `total > results.length`;
    - a `shop-meta` line "No products match" when `! searching`, the query is
      not empty and there are no results;
    - a ghost button "Search all products", shown when the query is not empty
      and `! everywhere`, calling `searchEverywhere()`.
  The card is in the page flow (not a popup), per README rule 6.

Tests (add to `ShopDeliveryTest`):
- `test_scan_screen_offers_find_by_name`: the employee (give the test user
  `products.view` as well as `deliveries.process`) sees
  `No barcode? Find by name`, `openManual()`, and a `data-search-url`
  attribute equal to `route('api.products.search')`.
- `test_find_by_name_is_hidden_without_products_view`: a user with only
  `deliveries.process` gets the page (200) without `openManual()`.
- `test_find_by_name_is_hidden_on_a_completed_session`: with the session's
  `status` set to 1, no `openManual()`.
- `test_a_product_picked_by_name_is_recorded_by_its_code_with_a_weight`: post
  `scan-increment` with `barcode` `5000000000024`, `quantity` `4.35`; the
  response `newQuantity` is `10.35`; then `delivery-legacy.items` returns the
  row with `scanned` `10.35`. (This is the request the picked product makes.)

Check: `php artisan test tests/Feature/Shop` passes, including
`ConfinePinSessionTest` (the view now names `api.products.search`, which is
already allow-listed) and `ShopViewContractTest`.

### 8. Completion creates a missing stock record

Files: `app/Http/Controllers/DeliveryLegacyController.php`,
`tests/Concerns/CreatesLegacyDeliveryPosTables.php`,
`tests/Feature/Shop/ShopDeliveryTest.php`

What:
- In `completeDelivery()`, both loops have the same body. Move it into one
  private method (for example `addDeliveredStock(object $item, array &$results)`)
  called from both, so the rule exists once.
- In that method, when the increment affects no row, create the record
  through the model, inside the existing transaction:
  `StockCurrent::create(['PRODUCT' => $item->productID, 'UNITS' => $item->scanned, 'LOCATION' => '0', 'ATTRIBUTESETINSTANCE_ID' => null])`,
  then count the product in `productsUpdated` and its amount in `unitsAdded`.
  Add a `productsCreated` counter to `$updateResults` (incremented here) so
  the office page could report it later; do not change the office view.
  `productsSkipped` stays in the array (the office view reads it) and is now
  only incremented if the create itself throws, in which case log a warning
  with the product id and carry on with the other products, as
  `ProductController` does for the same failure.
- The condition for being stocked at all is unchanged: `scanned > 0` and a
  `productID` (a barcode that matches no product still cannot be stocked).
- Do not touch `undoComplete()`.
- Test schema: add nullable `LOCATION` and `ATTRIBUTESETINSTANCE_ID` string
  columns to the trait's `STOCKCURRENT` table. Existing seeds insert only
  `PRODUCT` and `UNITS` and keep working.
- Tests (seed the extra product inside each test, not in the trait, so no
  other test's figures move): a product `p3`, code `5000000000031`, with **no**
  `STOCKCURRENT` row, and a scan item of `2.5` for it on `d-1`.
  - `test_completion_creates_a_stock_record_for_a_product_without_one`:
    complete as the employee with `return=shop`; `STOCKCURRENT` now has one
    row for `p3` with `UNITS` 2.5 and `LOCATION` `'0'`; p1 still goes from 3
    to 15 (the existing completion test's figure).
  - `test_undo_takes_a_created_stock_record_back_to_zero`: complete, then
    post `delivery-legacy.undo-complete` as a user who also holds
    `deliveries.manage`; the `p3` row still exists with `UNITS` 0 and the
    session's `status` is 0.
  - `test_completing_twice_does_not_create_or_add_twice`: complete twice; the
    `p3` row holds 2.5, not 5.

Check: `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` passes,
including the existing `test_completing_from_shop_updates_stock_and_returns_to_the_list`
and `test_completing_twice_does_not_add_stock_twice` unchanged. Then
`php artisan test --filter=Delivery` to catch any other test using the trait.

### 9. Documentation

Files: `docs/features/shop-mode.md`

What: in the delivery section, a short "Items without a barcode" subsection:
the button, supplier list first and "Search all products" fallback, typed
quantities (prompt, correction card), the 4-digit / 3-decimal rule and why,
and that the list stays open between items. One line under the existing
quantity-cap note that quantities are stored rounded to 3 decimals, and one
that completing a delivery creates a stock record for a product that had none
(office page included; undo returns it to 0).

Check: the section reads correctly against the screen as built.

## Verification

Run in order and record the real output.

1. `./vendor/bin/pint --dirty` → no errors (it may reformat only files this
   task touched; if it touches another track's file, revert that file's
   formatting change and say so).
2. `npm run build` → completes.
3. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css`
   → no output.
4. `php artisan test tests/Feature/Shop` → all pass.
5. `php artisan test` → the same 15 failures as the baseline and nothing else;
   passed count is 921 plus the new tests (13 if written exactly as above).
6. Browser on dev (`http://osmanager.local`, plain HTTP, so no live camera),
   signed in as an employee. Watch the console throughout; it must stay clean.
   Take the desktop pass first, then repeat a to d inside a same-origin iframe
   sized 390 × 844 (`resize_window` does not change the viewport here).
   a. `/shop/deliveries` → choose Mossfield → Start scanning. The page shows
      "No barcode? Find by name". Tap it: ten Mossfield products are listed.
   b. Tap "Mossfield Mature": the prompt opens above the scan field with an
      empty "Quantity or weight" field focused and Add disabled. Type `4.35`,
      Enter: the row "Mossfield Mature" shows 4.35 and the list is back.
      Pick it again, type `3,2` (comma), Add: the row shows 7.55.
      Type `5391521170057` into the field: Add stays disabled.
   c. Type `slieve` in the filter: one result. Clear it, type `cheddar`: "No
      products match" and "Search all products"; tap it: results from other
      suppliers appear. Pick one, add `1`, then correct it to 0 on the
      correction card (tap the number, type `0`, Set): the row disappears and
      the card closes.
   d. Close the list. Type `5391521170057` in the scan field, Enter: the
      prompt opens with the stepper as before; `+` makes it 2; tap the number,
      type `12`, Enter: the row shows 12. Tap the row, tap the number, type
      `2.5`, Set: the row shows 2.5.
   e. Summary: "Units to add" is a clean number (7.55 + 2.5 = 10.05). Do
      **not** complete the delivery.
   f. `/customer-requests` New request and `/shop/find`: search still works.
   g. At 390 × 844, in b the Add button is visible without scrolling while the
      quantity field has focus (measure its bounding box against 844).
7. Dev state: delete the session created in 6 (its `deliveriesScanItems` rows
   and its `deliveriesScan` row, by the session id, through the Eloquent
   models or `php artisan tinker`) and list what was removed under "Dev state"
   in the report. Stock is untouched because nothing was completed.

## Risks

- **Focus.** The scan field likes to take focus back. The typed-quantity
  field must hold focus after a manual pick and after tapping the number; if
  it loses it, look for a `shop-scan-done` dispatch in that path before
  touching `scan-input.js` (which is out of scope).
- **Wedge scanner into the quantity field.** With the field focused on the
  till PC, a scan types its digits there and sends Enter. The 4-digit pattern
  makes that a no-op rather than a quantity; verification 6b checks it.
- **Alpine and a null `pending`.** `x-model` on a path through `pending`
  throws when `pending` is null even if the element is hidden. Step 4 names
  the two safe forms.
- **Pinned tests.** The `announceSaved` count and the prompt/`announceDone`
  ordering are asserted on the source text. Splitting `onScan` into `lookup`
  must not duplicate either statement.
- **Suppliers with thousands of products** (Udea has 4,481 links): the list
  shows the first 50 by name with the "type to narrow" line. Acceptable; the
  feature is for the few unbarcoded items.
- **A supplier with an invoice loaded**: a typed weight is compared with the
  invoice's case arithmetic and may show Short/Over. Mossfield has no invoice
  lines, so this does not arise for the case being built; note it if seen.
- **Completion is shared with the office page.** Step 8 changes what the
  office "Complete" button does for a product with no stock record (it now
  gains stock instead of being skipped). That is the owner's decision; the
  risk is only in getting the refactor of the two loops wrong, which the
  existing completion tests guard.
- **`STOCKCURRENT` key.** The POS table's key includes `LOCATION` and
  `ATTRIBUTESETINSTANCE_ID`; the create must set `LOCATION` to `'0'` or MySQL
  refuses the insert (SQLite in the tests will not). If you can, confirm the
  insert once against the dev POS in `php artisan tinker` inside a transaction
  that is rolled back, and record the output.

## Review

Reviewed 2026-10-02 by the Planner: `implemented.md` read to the end, every
diff read, suite rerun.

Reran: `php artisan test` → 15 failed, 933 passed, the same 15 baseline
failures and nothing else. Design-block `cmp` → identical. `pint --test` on
the four PHP files → pass. `this.announceSaved();` count in
`delivery-scan.js` → 3. The browser pass was not repeated by the Planner; the
report's pass is detailed (measured focus, figures, 390 px positions) and found
and fixed two real faults, so it is taken as evidence. The owner's own try on
a phone is the remaining check.

Steps:
1. Rounding to 3 dp in both endpoints: pass.
2. `quantity.js` with `quantityText` / `parseQuantity`: pass. The comma rule
   is a whole-string match, so only a single decimal comma is accepted.
3. Quantities formatted on scan and summary screens: pass. Closes
   `docs/planImp/findings/2026-09-30-summary-units-float.md` (rounding only;
   no units/kg split, as planned).
4. Typed quantity on the prompt: pass.
5. Typed quantity on the correction card: pass.
6. Typeahead hooks, defaults unchanged for existing callers: pass.
7. Find by name: pass. Hidden without `products.view` and on a completed
   session; a pick posts the product code to the existing endpoint.
8. Completion creates a missing stock record; `undoComplete()` untouched and
   returns it to 0; second completion still refused: pass. Insert verified
   against the dev POS in a rolled-back transaction.
9. Documentation: pass.

Deviations:
- Float-tail test passes without the fix on the plan's sequence: accepted.
  The plan's sequence was a poor choice (starting from 6 hides the tail); the
  rounding is still right. A sharper test is a follow-up.
- `resetManual()` helper: accepted.
- Placeholder via `Js::from`: accepted.
- Docs placement (no delivery section existed): accepted.
- `x-show` + null-safe `typedQty` accessor instead of `x-if`: accepted. This
  was a plan fault: the plan offered `x-if` as equally safe and it is not.
- Focus waits a frame (`focusField`): accepted, same reason.
- Browser pass as admin rather than an employee: accepted; the screen does
  not branch on role beyond `products.view`, which is tested.
- 12 new tests, not 13: accepted; the plan's count was an arithmetic slip.

Notes for Planner:
- Alpine traps (`x-if` handler loses `$root`; `x-show` reveals after
  `$nextTick`): fixed now, added to `README.md` rule 5.
- "Not on this invoice" warning toast after every item when the supplier has
  no invoice lines: deferred to a follow-up, recommended. It is existing
  behaviour for scans too, but on the cheese day it is a warning on every
  single item. Proposed: with no invoice, an ok-toned "Added" toast.
- Two primary buttons ("Set", "Done") while typing a correction: deferred to
  the same follow-up (make "Done" ghost, or hide it, while typing).
- A scan arriving while a prompt holds an empty or invalid typed amount drops
  that prompt silently: deferred to the same follow-up (say so in a toast).
- Phone: the open list pushes Items below it: deferred; acceptable for a
  ten-product supplier, revisit if the owner finds it awkward.
- "Not stocked" pill squeezes the name at 390 px: deferred, cosmetic, same
  follow-up if one is run.
- Supplier code in the Items list vs product code in the by-name list:
  deferred; existing behaviour, owner to say whether it confuses.
- Previous results visible during the 250 ms debounce: rejected as a change;
  cosmetic and the same on every typeahead screen.
- `requests.js` `unpick()` not zeroing `total`: rejected; nothing reads it.
- `expectedLabel` / "Invoice" figure not through `stockText()`: deferred; the
  server rounds them.
- `1,234` reads as 1.234: accepted as designed.
- Float-tail test: deferred to the follow-up (use a barcode starting from 0).

Planner's own findings from the diff:
- `setCorrection()` clears `editTyped` even when the save failed, so a failed
  save closes the typed field (the toast still says "Could not save").
  Follow-up.

Nothing to redo. Uncommitted; the owner commits.
