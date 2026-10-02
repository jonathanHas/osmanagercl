# Delivery scan: follow-ups to find-by-name and typed quantities — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-10-02

## Baseline
HEAD: 9247c79e
Pre-existing dirty files:
```
 D docs/planImp/findings/2026-09-30-summary-units-float.md
 M docs/shop_new/README.md
 D docs/shop_new/implemented.md
 M docs/shop_new/plan.md
?? docs/shop_new/archive/
```
(All are the archiving of the last cycle and this plan; this file is the new
`implemented.md`.)

## Steps

### 1. A plain confirmation when there is no invoice — done
Changed: `resources/js/shop/delivery-scan.js`. `reportRow()` starts with
`if (! this.hasInvoice) { this.showToast('ok', row ? \`Added · ${this.stockText(row.scanned)} so far\` : 'Added'); return; }`
before both "Not on this invoice" warnings; the doc comment says why. New
test `test_adding_without_an_invoice_confirms_rather_than_warns`.

### 2. Say when a picked item was dropped — done (one deviation)
Changed: `delivery-scan.js`. `lookup()` now opens with
```js
let dropped = null;
if (this.pending && this.qtyValue === null) {
    dropped = this.pending.product?.name ?? 'Item';
} else if (this.pending) {
    await this.commit();
}
```
and calls `this.reportDropped(dropped)` right after `this.pending = { … }`.
`reportDropped(name)` is a small new method that shows
`warn` / `${name} not added, no amount entered` when `name` is set. The doc
comment on `lookup` has one extra sentence. **Not** called in the not-found
branch (see Deviations). `this.announceSaved();` count still 3; nothing named
`announceSaved` between `this.pending = {` and the next `this.announceDone();`.
New test `test_a_scan_over_an_unfilled_prompt_says_the_item_was_not_added`.
```
php artisan test tests/Feature/Shop/ShopDeliveryTest.php → Tests: 42 passed (before the new tests; pinned tests unchanged)
```

### 3. Correction card: one prominent button, a failed save stays open — done
Changed: `delivery-scan.js`: `saveQuantity()` returns `true` after the save
and reload, `false` on `! data.success` and in `catch`. `setCorrection()`:
`if (await this.saveQuantity(this.editingRow, value)) { this.editTyped = null; }`.
`adjust()` unchanged. `delivery-scan.blade.php`: "Done" is
`class="shop-btn shop-btn--block" :class="editTyped !== null ? 'shop-btn--ghost' : 'shop-btn--primary'"`,
same click handler. New test
`test_the_correction_card_has_one_primary_button_while_typing`, which asserts
the bound expression, the guarded clear in `setCorrection`, and exactly one
`this.editTyped = null` there.

### 4. "Not stocked" in the meta line — done
Changed: `delivery-scan.blade.php`. The pill is removed and the meta
expression ends `+ (! p.is_stocked ? ' · Not stocked' : '')`. New test
`test_not_stocked_is_part_of_the_result_meta_line`.
```
php artisan test tests/Feature/Shop/ShopDeliveryTest.php → Tests: 46 passed (234 assertions)
```

### 5. A float-tail test that fails without the rounding — done
Changed: `tests/Feature/Shop/ShopDeliveryTest.php`. Name kept; the docblock
now explains the 0.1 + 0.2 tail and why the row starts empty. The body
deletes the seeded `5000000000024` row on `d-1`, posts `0.1` then `0.2`, and
asserts `newQuantity` and the single stored row are exactly `0.3`.
Proof:
```
(round removed from incrementScanQuantity)
  ⨯ weights accumulate without a floating point tail
  Failed asserting that 0.30000000000000004 is identical to 0.3.
  Tests:    1 failed (3 assertions)
(restored with git checkout -- app/Http/Controllers/DeliveryLegacyController.php; app/ was clean at baseline)
  ✓ weights accumulate without a floating point tail
  Tests:    1 passed (5 assertions)
git diff --stat app/  → (empty)
```

### 6. Documentation — done
Changed: `docs/features/shop-mode.md`, "Deliveries: items without a
barcode". Added: with no invoice loaded, each add is confirmed as
"Added · N so far"; a scan that arrives while a picked item has no amount
typed replaces that prompt and a warning says the item was not added.

## Deviations

- **Step 2, not-found branch: no "not added" toast.** The plan said to show
  it after the new prompt is built *or* in the not-found branch. In the
  browser (6a, first attempt), an unknown code arriving over an unfilled
  picked prompt does **not** replace that prompt. The not-found branch returns
  before `this.pending` is overwritten, so the picked item stays open with its
  empty field. A "Mossfield Mature not added" toast there would describe
  something that has not happened, and it would sit next to the still-open
  Mature prompt. Only the replaced-prompt path reports the drop; a comment in
  the not-found branch records why. The "Product not found for …" error under
  the scan field already covers that case.
- Verification 6 run as the signed-in admin account (`jonathanE`), as in the
  last cycle. On dev every role has both permissions.
- Verification 6, focus: in this automation tab `document.visibilityState` is
  `hidden`, and `requestAnimationFrame` never fires (measured: "raf did not
  fire in 1s"). So `focusField()` (and `scan-input.js`'s own focusing)
  does not run here. I clicked into the quantity and scan fields before typing.
  Nothing this plan changes depends on that focus. In the last cycle the tab
  was visible and the auto-focus was checked then.

## Verification

1. `./vendor/bin/pint --dirty` → `PASS … 1 file`.
2. `npm run build` → `✓ built in 7.23s` (and again after the step 2 deviation:
   `✓ built in 7.25s`).
3. Design-block `cmp` → no output (exit 0).
4. `php artisan test tests/Feature/Shop` → `Tests:    288 passed (1387 assertions)`.
   After the step-2 deviation (JS only): `ShopDeliveryTest` → `46 passed`.
5. `php artisan test` → `Tests:    15 failed, 937 passed (4147 assertions)`.
   The same 15 as baseline (UdeaScrapingServiceTest ×7, CashReconciliationTest
   ×3, FruitVegLabelPrintingTest ×2, ProductTest ×2,
   TestScraperControllerTest ×1); 933 + 4 new = 937.
6. Browser, dev, session `8fc498b0-e353-4f33-b06d-a20d4c306f69` (Mossfield).
   Toasts were read from the DOM (`.shop-toasts` display + text + tone class)
   straight after each action, because screenshots of the hidden tab lagged
   behind the DOM and the toast lasts 3 s. Console: no errors.
   a. Mature `4.35` → toast `ok` "Added · 4.35 so far" (also visible in the
      screenshot as the green check). `3.2` → `{"text":"Added · 7.55 so far","tone":"ok"}`. ✓
   b. Picked Garlic, typed nothing, scanned `5391521170057` in the scan field:
      milk stepper prompt open, toast `shop-toast--warn` "Mossfield Garlic not
      added, no amount entered", Garlic not in rows. Added the milk:
      `shop-toast--ok` "Added · 1 so far". ✓
   c. Picked Garlic, typed `2`, scanned the milk: rows
      `Garlic=2, Mature=7.55, Milk=1`, milk prompt open, toast "Added · 2 so
      far" (Garlic's confirmation), no "not added". ✓
   d. Mature row → card: Done `shop-btn--primary`. Tapped the number: Set
      `shop-btn--primary`, Done `shop-btn--ghost`. `5`, Set → row 5,
      `editTyped: null`, Done `shop-btn--primary`. ✓
   e. `window.fetch` patched to reject `PATCH`. Tapped the number, typed `6`,
      Set → toast `shop-toast--bad` "Could not save", `editTyped: "6"`, field
      visible with `6`. Reloaded: Mature still 5. ✓
   f. `cheddar` → 0 supplier results → Search all products → 11 results. The
      two not-stocked rows' meta lines read
      `5060034202028 · Stock 0 · Not stocked` and
      `5420005733218 · Stock 0 · Not stocked`, 0 pills. ✓
   Phone (same-origin iframe 390 × 844):
   b. Same as desktop b: warning toast rendered at 16–359 px of 390 (two
      lines, fits; visible in the screenshot), Garlic unchanged; milk add →
      "Added · 2 so far" `ok`. ✓
   d. Card: Set primary, Done ghost while typing (screenshot), Set at
      bottom 540 / Done 608 of 844; after Set, Done primary. ✓
   f. Not-stocked names get the same width as stocked ones (215 px of a
      311 px row). "Vegan Deli Cheddar 160g" is on 1 line (3 lines last
      cycle with the pill); no horizontal overflow. ✓
   g. No supplier with invoice lines was reachable on dev (Mossfield never has
      them; the last handover found none on the list's sessions). Not
      exercised; the guard's position is covered by the step 1 source test.
7. Dev state: removed session `8fc498b0-e353-4f33-b06d-a20d4c306f69`:
```
items: [{"barcode":"5391521170057","quantity":"2.00"},{"barcode":"5016","quantity":"5.00"},{"barcode":"5009","quantity":"2.00"}]
deleted items=3 session=1
left: 0 / 0
```
   (Items through `DeliveryScanItem`; `deliveriesScan` has no model, so it was
   deleted with the POS query builder, guarded on `status = 0`.) Only one
   session was created: the first "Start scanning" click did not submit, and
   a listing of today's Mossfield sessions showed just this one and
   `b98fced8` (08:41, not this task's, now "In progress" with 1 item; left
   alone). Nothing completed; stock untouched.

## Files changed

This task:
```
 M docs/features/shop-mode.md
 M docs/shop_new/implemented.md
 M resources/js/shop/delivery-scan.js
 M resources/views/shop/delivery-scan.blade.php
 M tests/Feature/Shop/ShopDeliveryTest.php
```
Pre-existing (baseline, untouched): the `docs/planImp/findings/…` deletion,
`docs/shop_new/README.md`, `docs/shop_new/plan.md`, `docs/shop_new/archive/`.
`app/` has no diff.

## Notes for Planner

- **Focus depends on `requestAnimationFrame`.** `focusField()` (last cycle)
  and `scan-input.js`'s `focus()` both wait for a frame, and a hidden tab
  never gets one. That is harmless on a shop tablet, where the page is in
  front. But a device whose screen dims or whose browser treats the page as
  backgrounded would not focus the field until it repaints. If that is ever
  reported, `setTimeout(…, 0)` after `$nextTick` would also work: `x-show`'s
  reveal did not need a frame here (the prompt showed with rAF paused).
- Once, straight after navigating, opening the list showed it empty
  (`results: 0`, no search request logged in that attempt's instrumentation).
  It didn't reproduce across four later opens, including after reloads. Most
  likely the click landed while the page was still initialising. Worth a
  look if staff ever report an empty list on first open.
- The not-found case (step 2 deviation) leaves the picked prompt open and
  focus in the scan field. That is reasonable, but the person has to tap back
  into the quantity field to type the amount.
