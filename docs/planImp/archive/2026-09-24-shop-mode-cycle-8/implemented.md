# Shop mode cycle 8 (Revision 2) — the quantity prompt — implementation

Status: DONE
Plan revision: 2
Implementer: Opus
Date: 2026-09-24

Scope: steps 9–11 only. Steps 1–8 were accepted in Revision 1 and their code is
untouched except for `onScan()`'s +1 behaviour, which step 9 replaces.

## Baseline
HEAD: 31c0bc18
Working tree carries Revision 1's accepted work, uncommitted:
```
 M app/Http/Controllers/DeliveryLegacyController.php
 M app/Http/Controllers/Shop/ShopHomeController.php
 M config/shop.php
 M docs/design/shop-mode/README.md
 M docs/planImp/plan.md
 M resources/js/shop.js
 M routes/web.php
 M tests/Feature/Shop/ShopHomeTest.php
?? app/Http/Controllers/Shop/DeliveryController.php
?? docs/planImp/implemented.md
?? resources/js/shop/delivery-scan.js
?? resources/views/shop/deliveries.blade.php
?? resources/views/shop/delivery-scan.blade.php
?? tests/Concerns/CreatesLegacyDeliveryPosTables.php
?? tests/Feature/Shop/ShopDeliveryTest.php
```
All of that is mine from Revision 1. This revision touches three of those files:
`resources/js/shop/delivery-scan.js`, `resources/views/shop/delivery-scan.blade.php`,
`tests/Feature/Shop/ShopDeliveryTest.php`, plus `docs/design/shop-mode/README.md`.

## Pre-flight: a double-commit hazard in step 9's Enter handler

Step 9 ends with `@keydown.enter.window="pending && ! $event.target.value && commit()"`
so an empty Enter confirms the prompt. Reading `resources/js/shop/scan-input.js`
first, `submit()` (line 57) does this:

```js
submit() {
    const code = this.parseBarcode(this.value);
    if (! code) { return; }          // empty Enter does nothing and bubbles — as the plan says
    this.error = null;
    this.value = '';                 // cleared BEFORE the event is dispatched
    this.$dispatch('scan', { code });
},
```

The value is cleared *before* `scan` is dispatched. So for a **typed** scan while a
prompt is already open, one keydown does both of these, in this order:

1. the input's own handler runs `submit()`, which dispatches `scan` synchronously;
   `onScan()` starts and, because `pending` is set, calls `commit()`;
2. the same keydown carries on bubbling to `window`, where
   `pending && ! $event.target.value` is now **true** (pending is not yet cleared,
   the value has just been emptied) — so `commit()` is called a second time.

That would add the previous item twice. `commit()` therefore guards on `busy` as
well as `pending`: the first call sets `busy = true` synchronously before its
first `await`, so the second call returns immediately. Recorded as Deviation 1 and
covered by a check in step 9.

## Steps
### 9. Two-step scan: look up, then add a quantity — done
Changed: `resources/js/shop/delivery-scan.js` (`pending` state; `onScan()` rewritten
as a lookup; new `bump()`, `commit()`, `cancelPending()`, `reportRow()`; new
`unitsToAdd` / `addLabel` getters; `reportScan()` removed)
Check output — the plan's stubbed-fetch exercise, plus five branches it did not ask for:
```
after onScan: pending.qty = 1 | requests = 1 | quantity sent = 0 | events = shop-scan-done
after bump(1)+commit: requests = 2 | 2nd body quantity = 2 | pending = null
scan while pending: requests = 3 | bodies = B1:0 B1:1 B2:0 | pending code = B2
unknown barcode: pending = null | events = shop-scan-error | write requests = 0
case scan: addLabel = "Add 1 case · 4 units" | unitsToAdd = 4
case scan +1: addLabel = "Add 2 cases · 8 units" | unitsToAdd = 8
unit label qty1 = "Add 1 unit"
unit label qty2 = "Add 2 units"
bump(-5) floors at = 1
double commit: write requests = 1 (expect 1)
toast from reloaded row: {"tone":"warn","text":"Short: 3 of 12"}
```
All four of the plan's expectations hold: the lookup sends `quantity: 0` and no
write; `bump(1)` then `commit()` sends `quantity: 2`; a scan while pending produces
three requests with the middle one committing the first item (`B1:0 B1:1 B2:0`);
and a `product: null` lookup leaves `pending` null, dispatches `shop-scan-error`
and writes nothing.

```
$ grep -c "quantity: 0" resources/js/shop/delivery-scan.js  → 1
$ grep -c "route(" resources/js/shop/delivery-scan.js       → 0
```
The grep read 2 at first — the second hit was my own header comment describing the
behaviour, the same false positive shape as cycle 5's `edit_url`. Comment reworded
to "a zero quantity", so the count is now the one real occurrence.

### 10. The prompt card — done
Changed: `resources/views/shop/delivery-scan.blade.php` (prompt `shop-card` under
the scan input; `@keydown.enter.window` on `<main>`)
Check output:
```
$ grep -c "Quantity to add" resources/views/shop/delivery-scan.blade.php  → 1

$ php artisan test --filter=ShopViewContractTest
  Tests:    6 passed (88 assertions)
```
Still five screens; the template renders (proved by the step 11 tests).

### 11. Tests and README for the prompt — done
Changed: `tests/Feature/Shop/ShopDeliveryTest.php` (two tests), `docs/design/shop-mode/README.md`
Check output:
```
$ php artisan test --filter=ShopDeliveryTest
  ✓ employee can open the delivery list
  ✓ barista is forbidden
  ✓ items endpoint classifies the session
  ✓ scan screen renders with the endpoint urls
  ✓ completed session hides the scan input
  ✓ starting a delivery from shop lands on the shop scan screen
  ✓ scan screen renders the quantity prompt
  ✓ lookup with zero quantity records nothing
  ✓ home tile links to the shop delivery list

  Tests:    9 passed (56 assertions)

$ grep -c "Quantity to add\|Add N" docs/design/shop-mode/README.md → 1
```
`lookup_with_zero_quantity_records_nothing` is the one that matters: it hits the
real endpoint and asserts the stored total is still 12 after a zero-quantity
lookup, then 14 after `quantity: 2`. The two-step flow depends entirely on that
being true, and now it is pinned rather than assumed.

## Deviations

1. **`commit()` guards on `busy` as well as `pending`.** Not a preference — without
   it the plan's own Enter handler double-commits. The reasoning is in Pre-flight
   above; the short version is that `scan-input.js` empties the input *before*
   dispatching `scan`, so one keydown from a typed scan reaches `commit()` twice
   (once through `onScan`, once through `@keydown.enter.window`, whose
   `! $event.target.value` test is true because the value has just been cleared).
   `busy = true` is set synchronously before the first `await`, so the second call
   returns. The check "double commit: write requests = 1" covers it.

2. **`reportScan(data)` replaced by `reportRow(barcode)`.** The plan says to toast
   "from the **reloaded row** for `latest`", which the old signature could not do —
   it read `data.newQuantity` / `data.expectedQty` from the increment response.
   The new one looks the row up in `this.rows` after `load()`. This is what
   resolves the two-formulas note from Revision 1: the toast and the row beside it
   now read from one source. A row that is missing after reload (the barcode is not
   on this invoice) falls back to "Not on this invoice".

3. **`unexpected` toasts `warn`, not `bad`.** The plan's step 9 list says
   `unexpected` → warn "Not on this invoice", which differs from Revision 1's
   `bad` for the same situation. I followed Revision 2. Worth knowing it is a
   deliberate softening: a genuinely unknown barcode is now an input *error*
   (nothing recorded), so "not on this invoice" only ever means a real product
   that the invoice does not list — a warning, not a failure.

4. **The "Case of N" pill sits in a fourth `shop-fact`** with a "Outer" label,
   rather than replacing a fact outright as the plan's wording suggested
   ("in place of a fourth fact"). `shop-facts--2` is a two-column grid, so an
   unlabelled pill in a fact slot looked orphaned next to three labelled ones. The
   pill, its text and the `x-show` condition are exactly as specified.

## Verification (Revision 2)

1. `php artisan route:list --name=shop.` → **pass**, five routes, unchanged from
   Revision 1. `delivery-legacy.items` unchanged.

2. `php artisan test --filter=Shop` → **pass**, `Tests: 115 passed (351 assertions)`
   — exactly the 115 the plan predicts. Contract test still lists five screens.

3. `php artisan test` → **pass**: `Tests: 17 failed, 515 passed (1971 assertions)`,
   exactly the plan's prediction. The 17 are the identical pre-existing set
   (Udea ×7, CashReconciliation ×3, WasteLog ×2, Product ×2, FruitVegLabel ×2,
   TestScraper ×1). The 2 extra passes over Revision 1's 513 are the two new tests.

4. `git diff app/Http/Controllers/DeliveryLegacyController.php` → **pass**,
   unchanged from Revision 1: the only removed line is still the inlined flash
   message in `createSession()`. Revision 2 touched no PHP outside the test file.
   `git diff --stat resources/views/delivery-legacy/ app/Http/Controllers/DeliveryController.php resources/views/deliveries/`
   → empty.

5. `grep -rn "<script\|<style" resources/views/shop/` → no output;
   `grep -c "route(" resources/js/shop/delivery-scan.js` → 0. **pass**

6. `head -c ... | cmp - docs/design/shop-mode/shop.css` → `DESIGN-BLOCK-IDENTICAL`.
   **pass** — no stylesheet change in either revision; every class the prompt card
   uses (`shop-stepper`, `shop-stepper__value`, `shop-btn--lg`, `shop-btn--block`,
   `shop-iconbtn--lg`, `shop-facts--2`, `shop-pill--sage`) is in the design block.

7. `./vendor/bin/pint --test --dirty` → `PASS 8 files`; `npm run build` →
   `✓ built in 11.69s`. **pass**

8. Manual walkthrough — **not run by me.** No browser session. The Revision 2
   surface is smaller than Revision 1's but it is all pointer- and scanner-driven,
   and three things in particular are unobserved and cannot be proved from here:
   - **The Enter-to-confirm handler.** The double-commit hazard is guarded and the
     guard is tested in isolation, but the interaction between `scan-input`'s
     keydown handler and the window handler has only been reasoned about, not
     watched. Worth testing both ways: Enter on an empty input (should add), and a
     typed barcode + Enter while a prompt is open (should add the first **once**
     and open the second).
   - **A real case/outer scan.** The plan verified the endpoint resolves outers in
     dev; what I cannot see is the prompt reading "Case of 4" and the button "Add 1
     case · 4 units" for a real one.
   - **The camera path on the tablet**, which reaches `onScan` through the same
     event but from `barcode-scanner` rather than a keystroke.

## Files changed

Revision 2 touched four files, all of them already mine from Revision 1:
```
 M docs/design/shop-mode/README.md                     (the scan-flow sentence rewritten)
 M docs/planImp/implemented.md                         (this file, restarted for Revision 2)
?? resources/js/shop/delivery-scan.js                  (step 9)
?? resources/views/shop/delivery-scan.blade.php        (step 10)
?? tests/Feature/Shop/ShopDeliveryTest.php             (step 11: two tests)
```
The `??` marks are because Revision 1's files are still uncommitted, not because
they are new this revision. The full cycle-8 file list is unchanged from the
Revision 1 report; nothing was added or removed, and no PHP outside the test file
was touched.

## Notes for Planner

- **`reportScan()` is gone, not deprecated.** If cycle 9 wants a toast straight
  from an increment response (for example to avoid the `load()` round trip), it
  will need to reinstate something like it — and would reintroduce the two-formulas
  divergence for weighed goods that Deviation 2 just closed. Flagging so the
  trade-off is made deliberately rather than by accident.
- **The Enter handler is broad.** `@keydown.enter.window` fires for Enter
  anywhere on the page, and `! $event.target.value` is true for any target without
  a `value` property — including buttons. Pressing Enter while the "Add N units"
  button has focus therefore triggers both the button's click and the window
  handler; the `busy` guard absorbs it, so the behaviour is correct, but it is
  correct by a guard rather than by construction. A tighter condition
  (`$event.target === $refs.prompt` is no good; checking the target is the scan
  input would be) would make it structural. Your call whether that is worth a step.
- **`cancelPending()` dispatches `shop-scan-done`**, which is what re-focuses the
  scan input. That is per the plan and is right, but it means cancelling is
  indistinguishable from completing as far as the input component is concerned. If
  a future cycle wants a "cancelled" signal (say, to leave the camera off), it
  needs a new event.
- **Nothing committed, pushed or deployed**, per the plan's Constraints.
- One process note: Revision 1's work is still uncommitted, so the cycle-8 diff now
  contains two revisions' changes mixed together. Committing at revision boundaries
  would let a reviewer see a revision in isolation, which is the same point I
  raised before cycle 8 and which committing `31c0bc18` largely solved.
