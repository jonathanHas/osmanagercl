# Side plan: Shop mode cycle 32 — Hand-scanner keystrokes always reach the scan field

**Status:** READY
**Planner:** Fable 5.1
**Date:** 2026-09-28

Side plan (`plan-<slug>.md`): cycle 31 owns `plan.md` and is in progress. This one touches `scan-input.js`, its Blade component, `DeliveryLegacyController` and tests, none of which cycle 31 touches (31 changes `shop.css`, `vouchers.js`, `.gitignore`), so it can run after 31 or alongside it by a second agent. Report to `implemented-wedge-focus.md`.

## Goal

On a desktop PC with a USB hand scanner, a scan on the Shop delivery page can end up changing the quantity instead of being read as a barcode. The office scanner page did not have this problem. Make a hand-scanner burst reach the scan field no matter what is focused, and stop the server accepting a barcode-sized number as a quantity.

## What is happening (verified 2026-09-28)

- A keyboard-wedge scanner types the digits and then Enter into whatever has focus.
- `resources/js/shop/scan-input.js` `refocus(event)` deliberately does **not** pull focus back when it moves to a `button` ("a tap on the number pad must work, and the page hands focus back itself once the action is done"). So after the user clicks a stepper button with the mouse — "+" on the quantity prompt, or on the correction card — focus stays on that button until the page finishes the action.
- A scan at that moment: the digits go nowhere (a button ignores them) and the trailing Enter **activates the focused button**, so "+" fires once per scan. The barcode is lost and the quantity creeps up. That is the "scanned as a quantity" the owner sees. On a touch device this cannot happen (no keyboard).
- The page's own Enter handling is only on the scan input (`@keydown.enter.prevent="submit()"` in `resources/views/components/shop/scan-input.blade.php` line 20). `ShopDeliveryTest` asserts the page contains no `keydown.enter.window`, a cycle-14 guard against a duplicate window handler that double-committed.
- Server: `DeliveryLegacyController::incrementScanQuantity()` validates `quantity` as `nullable|numeric|min:0` with **no upper bound**; check `updateQuantity()` (~line 1270) for the same. A 13-digit "quantity" would be accepted and written to `deliveriesScanItems`.
- Other window key handlers in the Shop: the PIN page (`pin.blade.php`, no scan field on that page), the requests staff page (`Escape` only). Text inputs elsewhere (Find product search, request form typeahead) must keep receiving their own keystrokes.
- Baseline: measure before starting (15 failed / 800 passed at cycle 30's acceptance).

## Constraints

- No commits, no deploys. No CSS change. The `keydown.enter.window` string must not appear in the delivery view (the test stays).
- A human pressing Enter or Space on a focused button must still activate it: only a scanner-shaped burst is captured.
- Touch behaviour and the on-screen keyboard toggle are unchanged.

## Out of scope

- Reworking `refocus()`; the button exception stays.
- The office match page.

## Steps

### 1. Capture stray keystrokes into the scan field
`scan-input.js`:
- Add `burstAt: 0`.
- Add `capture(event)`, bound on the component root as `x-on:keydown.window="capture($event)"` (in the Blade component; do not use the `keydown.enter.window` form). Logic:
  - Return if `event.defaultPrevented`, or `ctrlKey/altKey/metaKey`, or the active element is text-entry: `textarea`, `select`, `[contenteditable]`, or an `input` whose type is not one of `button, submit, reset, checkbox, radio, hidden, range, file` (this includes our own input, whose own bindings handle it).
  - Printable single character other than space (`event.key.length === 1 && event.key !== ' '`): `preventDefault()`, `this.value += event.key`, `this.burstAt = Date.now()`, `this.focus()`. (Appending directly, not relying on the browser inserting into a newly focused input, so the behaviour is the same in every browser and in tests.)
  - `Enter`: if `this.value !== ''` and `Date.now() - this.burstAt < 1000` → `preventDefault()`, `this.submit()`. Otherwise do nothing, so a person's Enter on a focused button still presses it.
  - Anything else: ignore.
- Doc comment: why (hand scanner on the till PC types into whatever is focused; the stepper button exception in `refocus()`), and the burst rule.
- `submit()` already clears `value`; also reset `burstAt = 0` there.
**Check:** the four cases in Verification 3.

### 2. Server refuses a barcode-sized quantity
`DeliveryLegacyController`: `quantity` rules in `incrementScanQuantity()` and `updateQuantity()` gain `max:9999` (a delivery line never approaches it; a barcode is 8–14 digits). The office page posts the same endpoints, so it is protected too. A 422 reaches the Shop as "Not saved, try again" (the existing `! data.success`/catch path — confirm which branch handles a 422 JSON body and that the toast shows).
**Check:** `ShopDeliveryTest`: POST scan-increment with `quantity` `5412533401912` → 422 and no `deliveriesScanItems` row; same for update-quantity; a quantity of `9999` still succeeds.

### 3. Tests
- `ShopStockScanTest` (the field's home): the rendered component carries `capture($event)` on a `keydown.window` binding; source assertions that `capture(event)` checks `burstAt` and that `submit()` resets it.
- `ShopDeliveryTest`: the existing `assertDontSee('keydown.enter.window')` stays green.
**Check:** `php artisan test --filter='ShopStockScanTest|ShopDeliveryTest|ShopViewContractTest'`.

### 4. Build and tidy
`npm run build`; `php artisan view:clear`; Pint on the controller and the two test files.

## Verification (report every item with what you saw)

1. `php artisan test` summary against your measured baseline; no new failures.
2. Contract: `ShopViewContractTest` green; design-block `cmp` prints nothing.
3. Browser, delivery scan page on dev, keyboard-wedge simulation by dispatching `KeyboardEvent('keydown', { key, bubbles: true })` on `window` for each character and then Enter, within a few ms:
   - (a) Scan input focused (normal): burst → one `scan` dispatched, prompt opens. (Unchanged path.)
   - (b) Click "+" on the prompt with the mouse (focus on the button), then burst a *different* real barcode: the prompt for the first item is committed with the quantity as it was (the existing "scan while a prompt is open confirms it" rule), the new prompt opens, and **"+" was not pressed** (qty of the first commit unchanged by the scan). Report the recorded quantity.
   - (c) Click "+" with the mouse and press Enter alone (no burst): "+" increments — a human's Enter still works.
   - (d) On Find product, with the search box focused, type digits: they go into the search box, not the scan field (there is no scan field on that page, so confirm on the labels or stock scan page with a focused non-scan text input if one exists; otherwise state that no Shop page has both).
   - (e) Space on a focused button still activates it.
4. Server: the two 422 checks in step 2, shown with the response body.
5. Dev data: put back anything the checks recorded, as in cycles 29 and 30.

## Risks

- **Burst window of 1 s**: a slow typist typing digits by hand on a physical keyboard with a button focused, then Enter within a second, would submit as a scan. That is the desired behaviour on the till PC (typing a code by hand is a scan). A person pressing Enter on a button more than a second after their last keystroke gets the button.
- **Alpine `keydown.window` on the component root**: fine on pages with two scan fields? No Shop page has two; note it in the comment.
- **`updateQuantity()` legitimately writes 0** (a correction to nothing) — keep `min:0`.
