# Delivery scan: follow-ups to find-by-name and typed quantities

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-10-02

## Goal

Five small rough edges left by the find-by-name task (archived at
`docs/shop_new/archive/2026-10-02-delivery-find-by-name/`, committed as
`9247c79e`), fixed before the first real cheese delivery is entered with it:

1. When a supplier has no invoice loaded (Mossfield never has), every added
   item shows a warning-coloured "Not on this invoice" toast. On the cheese day
   that is a warning on every item. It becomes a plain confirmation.
2. If someone picks an item by name and a scan arrives before they have typed
   its amount, the picked item's prompt is replaced and nothing says it was
   not recorded. The screen now says so. (Owner approved this fix 2026-10-02.)
3. While typing a correction, "Set" and "Done" are both prominent buttons.
   "Done" steps back while typing.
4. A typed correction that fails to save closes the typed field, as if it had
   worked. It now stays open.
5. On a phone, the "Not stocked" pill squeezes a product's name into a narrow
   column in the search-all results. The words move into the row's meta line.

Plus one test repair: the float-tail test from the last task passes with or
without the rounding it is meant to prove.

## Context

All in `resources/js/shop/delivery-scan.js` and
`resources/views/shop/delivery-scan.blade.php` unless said otherwise. Line
numbers are as of `9247c79e`.

- `reportRow(barcode)` (line 473) picks the toast after an add from the
  reloaded row's `status`. With no invoice lines every row's status is
  `unexpected`, so the `default` branch shows `warn` / "Not on this invoice".
  `hasInvoice` (a getter: `progress.total > 0`) already tells the two cases
  apart and `pill(row)` already uses it to hide the "Unexpected" pill. The
  early `if (! row)` branch (the row is not in the reloaded list at all) shows
  the same warning.
- `lookup(code, manual)` (line 247) begins
  `if (this.pending) { await this.commit(); }`. `commit()` returns early when
  `qtyValue === null`: the prompt holds a typed amount that is empty or not a
  valid quantity. `lookup` then carries on and overwrites `this.pending` with
  the newly scanned product. `this.pending.product.name` is the dropped
  product's name. This can only be reached by a scan (camera, or a hand
  scanner while focus is not in the quantity field), because the by-name list
  is hidden while a prompt is open.
- `setCorrection()` (line 536) awaits `saveQuantity()` and then sets
  `editTyped = null` unconditionally. `saveQuantity()` (line 548) returns
  nothing; it shows "Could not save" on a failed or thrown request.
  `adjust()` also calls `saveQuantity()` and ignores the result.
- The correction card's "Done" button is view line 189:
  `class="shop-btn shop-btn--primary shop-btn--block"`. "Set" above it is
  shown while `editTyped !== null`. `.shop-btn--ghost` exists in the design
  block.
- By-name result rows, view lines 116–123: the meta line is
  `p.code + (p.stock_units !== null ? ' · Stock ' + stockText(p.stock_units) : '')`
  and the pill is
  `<span class="shop-pill shop-pill--muted" x-show="! p.is_stocked">Not stocked</span>`,
  a flex sibling of `.shop-row__main`.
- `test_weights_accumulate_without_a_floating_point_tail` in
  `tests/Feature/Shop/ShopDeliveryTest.php` adds 1.94, 3.74, 0.111 to a row
  seeded at 6. The Implementer showed it passes without the `round(…, 3)` in
  `incrementScanQuantity()`, so it pins nothing. `0.1 + 0.2` is
  `0.30000000000000004` in PHP, which is not the double `0.3`.
- Tests on this screen assert on rendered markup and on the JS source text
  (see the existing tests in that file). Three are pinned and must keep
  passing unchanged: exactly three `this.announceSaved();` in
  `delivery-scan.js`; no `announceSaved` between the first `this.pending = {`
  and the next `this.announceDone();`; `x-ref="prompt"` before
  `shop-scan__input`.
- From the last cycle (now README rule 5): do not put a handler on an element
  an `x-if` can remove; focusing something just revealed by `x-show` needs
  `focusField()`.

Test baseline: **15 failed, 933 passed** (2026-10-02, after `9247c79e`; the
15 are `UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3,
`FruitVegLabelPrintingTest` ×2, `ProductTest` ×2,
`TestScraperControllerTest` ×1). The working tree was clean at planning time
apart from `docs/shop_new/`.

## Constraints

- `docs/shop_new/README.md` rules apply (view contract, design block
  untouched, APP ADDITIONS only, Alpine traps).
- Scanning behaviour is unchanged: a scan arriving over a prompt with a valid
  amount still confirms that prompt first. Only the wording of toasts and the
  no-valid-amount case change.
- With an invoice loaded, toasts are exactly as they are now.
- No PHP application code changes. No CSS is expected; if any is needed it
  goes under APP ADDITIONS and is explained in Deviations.
- Do not rewrite or delete existing tests, with one stated exception in
  step 5.
- Do not commit, push or deploy.
- Every response ends with `mpg123 /home/jon/Music/notification.mp3`.

## Out of scope

- Where the by-name list sits on a phone (it pushes Items below it).
- Supplier code vs product code in the two lists.
- The stale results shown during the filter debounce.
- `expectedLabel` and the prompt's "Invoice" figure.
- The owner's `docs/shop_new/ToDo.txt` (customer requests); it is the next
  task, not this one.
- `scan-input.js`, `product-typeahead.js`, the summary screen, any PHP
  controller.

## Steps

### 1. A plain confirmation when there is no invoice

Files: `resources/js/shop/delivery-scan.js`

What: in `reportRow()`, before the existing logic, when `! this.hasInvoice`:
show an ok-toned toast and return. Text: `Added` followed by the row's running
total when the row is found, in the form `Added · 7.55 so far`
(`this.stockText(row.scanned)`); just `Added` when the row is not in the list.
Everything below that guard, including both "Not on this invoice" warnings,
stays as it is for suppliers with an invoice. Update the method's doc comment.

Check: new test `test_adding_without_an_invoice_confirms_rather_than_warns`
asserts the source of `delivery-scan.js` contains `so far` and that, within
`reportRow`, the `hasInvoice` guard comes before the first
`'Not on this invoice'` (compare `strpos` positions from the start of
`reportRow(`). `php artisan test tests/Feature/Shop/ShopDeliveryTest.php`
passes.

### 2. Say when a picked item was dropped

Files: `resources/js/shop/delivery-scan.js`

What: at the top of `lookup()`, where it handles an already-open prompt:
- if `this.pending` is set and `this.qtyValue === null`, do not call
  `commit()`. Remember the product's name
  (`this.pending.product?.name ?? 'Item'`) in a local variable; the lookup
  then proceeds and replaces the prompt exactly as it does now.
- once the new prompt has been built (after `this.pending = { … }`), or in
  the not-found branch, show
  `this.showToast('warn', \`${name} not added, no amount entered\`)`. Showing
  it after the lookup, not before, keeps it from being overwritten and means
  one toast, not two.
- if `this.pending` is set and `qtyValue` is a number, `await this.commit()`
  as now.
Keep the statement order `this.pending = {` … `this.announceDone();` free of
`announceSaved`, and the count of `this.announceSaved();` at three. Extend
the `lookup` doc comment with one sentence on this case.

Check: new test `test_a_scan_over_an_unfilled_prompt_says_the_item_was_not_added`
asserts the JS source contains `not added, no amount entered`. The three
pinned tests pass unchanged.

### 3. Correction card: one prominent button, and a failed save stays open

Files: `resources/js/shop/delivery-scan.js`,
`resources/views/shop/delivery-scan.blade.php`

What:
- `saveQuantity()` returns `true` after a successful save and reload, `false`
  in both failure paths. `adjust()` may keep ignoring it.
- `setCorrection()` clears `editTyped` only when `saveQuantity()` returned
  `true`.
- The "Done" button takes its tone from the state: ghost while a correction
  is being typed, primary otherwise. Keep the static classes
  `shop-btn shop-btn--block` and bind the tone, for example
  `:class="editTyped !== null ? 'shop-btn--ghost' : 'shop-btn--primary'"`
  (`:class` on a plain element is Alpine's, not a Blade directive; do not use
  `@class`). Its click handler is unchanged.

Check: new test `test_the_correction_card_has_one_primary_button_while_typing`
asserts the scan page contains the bound tone expression and that the JS
source of `setCorrection` only clears `editTyped` under a condition on the
save's result (assert on the text you wrote). Suite for the file passes.

### 4. "Not stocked" in the meta line

Files: `resources/views/shop/delivery-scan.blade.php`

What: remove the "Not stocked" pill from the by-name result row and append
`' · Not stocked'` to the meta line's expression when `! p.is_stocked`, after
the stock figure.

Check: new test `test_not_stocked_is_part_of_the_result_meta_line` asserts the
scan page (user with `products.view`) contains `· Not stocked` inside an
`x-text` expression and does not contain
`shop-pill--muted" x-show="! p.is_stocked"`.

### 5. A float-tail test that fails without the rounding

Files: `tests/Feature/Shop/ShopDeliveryTest.php`

What: this is the one existing test the plan changes, because it does not
test what its name says. Keep its name and docblock intent; change its body
so the row starts from nothing and the sequence is one whose sum is not
representable: delete the seeded scan row for barcode `5000000000024` on
`d-1`, then post `scan-increment` with `0.1` and then `0.2`; assert the last
`newQuantity` and the single stored row are exactly `0.3` (`assertSame` on a
float cast).

Check: prove it. Temporarily remove the `round(…, 3)` in
`DeliveryLegacyController::incrementScanQuantity()`, run
`php artisan test --filter=test_weights_accumulate_without_a_floating_point_tail`,
record the failure output, restore the line, run again, record the pass.
`git diff app/` must be empty afterwards. If the test still passes without
the rounding (the SQLite column or the sum may round for us), try
`1.1` then `2.2` (`3.3000000000000003`); if nothing fails, say so under
Deviations and leave the test in its best form.

### 6. Documentation

Files: `docs/features/shop-mode.md`

What: in "Deliveries: items without a barcode", two sentences: with no invoice
loaded an add is confirmed as "Added · N so far"; a scan that arrives while a
picked item has no amount typed replaces it and says the item was not added.

Check: reads correctly against the screen as built.

## Verification

1. `./vendor/bin/pint --dirty` → no errors.
2. `npm run build` → completes.
3. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css`
   → no output.
4. `php artisan test tests/Feature/Shop` → all pass.
5. `php artisan test` → the same 15 failures and nothing else; 933 plus the
   new tests passed (4 if written as above).
6. Browser on dev (`http://osmanager.local`), console watched throughout and
   clean. Desktop first, then b and d in a same-origin iframe sized
   390 × 844.
   a. `/shop/deliveries` → Mossfield → Start scanning → "No barcode? Find by
      name" → Mossfield Mature → `4.35` → Add. The toast is ok-toned and reads
      "Added · 4.35 so far". Add `3.2` to it: "Added · 7.55 so far".
   b. Pick Mossfield Garlic and type nothing. Click into the scan field, type
      `5391521170057`, Enter. The milk's stepper prompt opens, a warning toast
      reads "Mossfield Garlic not added, no amount entered", and Garlic is not
      in the Items list. Add the milk: "Added · 1 so far".
   c. Pick Mossfield Garlic, type `2`, then scan the milk as in b: Garlic is
      recorded at 2 (existing behaviour: a valid amount is confirmed first)
      and the milk prompt opens, with no "not added" toast.
   d. Tap the Mature row, tap the number: "Set" is the prominent button and
      "Done" is ghost. Type `5`, Set: row shows 5, "Done" is primary again.
   e. Failed save: in the browser console replace `window.fetch` with a
      function that rejects for `PATCH` requests only, tap the number, type
      `6`, Set: "Could not save" shows and the field stays open with `6` in
      it. Restore `fetch` (reload), confirm the row still shows 5.
   f. Type `cheddar`, "Search all products": a not-stocked result shows
      "· Not stocked" in its meta line and no pill; at 390 px its name is not
      squeezed (record the name element's width against the row's).
   g. A supplier with invoice lines, if dev has one reachable (the last
      handover said the list's sessions had none): toasts read as before
      ("Matches invoice", "Short: …"). If none is reachable, say so; the
      guard is covered by the source test in step 1.
7. Dev state: delete the session created in 6 by its id (items, then the
   session row) and list what was removed. Nothing is completed, so stock is
   untouched.

## Risks

- **Toast order in step 2.** `commit()` and `reportRow()` also show toasts;
  the "not added" toast must be the one left on screen after a dropped
  prompt. In that path `commit()` is not called, so nothing should overwrite
  it; check 6b shows it.
- **Pinned source tests.** Step 2 edits the top of `lookup()`, close to the
  statements two tests count and order. Run the file's tests after that step,
  not only at the end.
- **Step 5 may not be provable** if the test database rounds the stored sum.
  The step says what to do then.

## Review

Reviewed 2026-10-02 by the Planner: `implemented.md` read to the end, the
whole diff read, suite rerun.

Reran: `php artisan test` → 15 failed, 937 passed (the baseline 15, nothing
else). Design-block `cmp` → identical. `pint --test` on the test file → pass.
`this.announceSaved();` count → 3. `app/` has no diff. The browser pass was
not repeated by the Planner; the report quotes toast text and tone from the
DOM for each case, at desktop and 390 px.

Steps:
1. Plain "Added · N so far" with no invoice; warnings unchanged with one: pass.
2. "… not added, no amount entered" when a scan replaces an unfilled prompt;
   a valid amount is still confirmed first: pass.
3. "Done" ghost while typing; a failed typed correction keeps the field: pass.
4. "Not stocked" in the meta line, pill removed: pass.
5. Float-tail test now fails without the rounding (shown) and passes with it:
   pass.
6. Documentation: pass.

Deviations:
- No "not added" toast in the not-found branch: accepted, and the plan was
  wrong to ask for it. An unknown code returns before the prompt is replaced,
  so the picked item is still open and nothing was dropped.
- Browser pass as admin: accepted, as last cycle.
- Fields clicked into by hand because the automation tab was hidden and
  `requestAnimationFrame` does not fire there: accepted. Nothing in this plan
  touches focus; it was measured in a visible tab last cycle.
- 6g (a supplier with invoice lines) not exercised, none reachable on dev:
  accepted. The guard is a single early return above code that is otherwise
  unchanged, and its position is pinned by a test. The owner will see the
  with-invoice toasts on any ordinary delivery.

Notes for Planner:
- Focus waits on `requestAnimationFrame`, which a hidden or backgrounded page
  never gets: deferred, recorded in `README.md` under scanning facts. Not a
  fault on a tablet with the page in front; `setTimeout` is the fallback if it
  is ever reported.
- The by-name list opened empty once, straight after navigation, not
  reproduced in four further tries: deferred, recorded in `README.md` as
  something to watch on the first real use.
- After an unknown code the picked prompt stays open but focus is in the scan
  field: accepted as is; one tap back into the field.

Nothing to redo. Uncommitted; the owner commits.
