# Plan: Shop mode cycle 32 — Hand-scanner keystrokes always reach the scan field, plus camera loose ends

**Status:** ACCEPTED
**Planner:** Fable 5.1
**Date:** 2026-09-28

(Replaces the side plan `plan-wedge-focus.md`, now that cycle 31 is accepted. Steps 1–4 are that plan; steps 5–7 are what cycle 31's review left open.)

## Goal

On a desktop PC with a USB hand scanner, a scan on the Shop delivery page can end up changing the quantity instead of being read as a barcode. The office scanner page did not have this problem. Make a hand-scanner burst reach the scan field no matter what is focused, and stop the server accepting a barcode-sized number as a quantity. Then close three small things from cycles 30 and 31: a failed pause must not leave the decoder live during the prompt; the fire-and-forget resume listener gets its reason written down; and the three other editor swap files still tracked in git are untracked.

## What is happening (verified 2026-09-28)

- A keyboard-wedge scanner types the digits and then Enter into whatever has focus.
- `resources/js/shop/scan-input.js` `refocus(event)` deliberately does **not** pull focus back when it moves to a `button` ("a tap on the number pad must work, and the page hands focus back itself once the action is done"). So after the user clicks a stepper button with the mouse — "+" on the quantity prompt, or on the correction card — focus stays on that button until the page finishes the action.
- A scan at that moment: the digits go nowhere (a button ignores them) and the trailing Enter **activates the focused button**, so "+" fires once per scan. The barcode is lost and the quantity creeps up. That is the "scanned as a quantity" the owner sees. On a touch device this cannot happen (no keyboard).
- The page's own Enter handling is only on the scan input (`@keydown.enter.prevent="submit()"` in `resources/views/components/shop/scan-input.blade.php` line 20). `ShopDeliveryTest` asserts the page contains no `keydown.enter.window`, a cycle-14 guard against a duplicate window handler that double-committed.
- Server: `DeliveryLegacyController::incrementScanQuantity()` validates `quantity` as `nullable|numeric|min:0` with **no upper bound**; check `updateQuantity()` (~line 1270) for the same. A 13-digit "quantity" would be accepted and written to `deliveriesScanItems`.
- Other window key handlers in the Shop: the PIN page (`pin.blade.php`, no scan field on that page), the requests staff page (`Escape` only). Text inputs elsewhere (Find product search, request form typeahead) must keep receiving their own keystrokes.
- Cycle 30's scan field: `detected()` calls `this.pause()`; `pause()` sets `this.paused = scanner.pauseScanner()` and does nothing else when that returns false, so the decoder stays live while the prompt is open. Cycle 29's 3.5 s same-code suppression is then the only thing preventing a double add. `restartCameraIfWanted()` is `async` and the Blade listener (`@shop-scan-saved.window="restartCameraIfWanted()"`) does not await it, which is fine and deliberate but undocumented.
- Cycle 31 untracked `docs/planImp/.needed.txt.kate-swp` and added `*.kate-swp` / `.*.swp` to `.gitignore`. `git ls-files | grep kate-swp` still lists three tracked files: `.delivery-specialist-agent-recommendation.md.kate-swp`, `JFolder_temp/.questions.txt.kate-swp`, `docs/jons_docs/.todo.md.kate-swp`. An ignore rule does nothing for a tracked file.
- `docs/features/shop-mode.md` does not mention html5-qrcode or the version-tied points (the `:not(#qr-shaded-region)` CSS rule and the `Html5QrcodeScannerState` constants).
- Baseline: measure before starting (15 failed / 801 passed at cycle 31's acceptance).

## Constraints

- No commits, no deploys. No CSS change. The `keydown.enter.window` string must not appear in the delivery view (the test stays).
- A human pressing Enter or Space on a focused button must still activate it: only a scanner-shaped burst is captured.
- Touch behaviour and the on-screen keyboard toggle are unchanged.
- `git rm --cached` is staging only; leave the deletions staged and the files on disk.

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
**Check:** the cases in Verification 3.

### 2. Server refuses a barcode-sized quantity
`DeliveryLegacyController`: `quantity` rules in `incrementScanQuantity()` and `updateQuantity()` gain `max:9999` (a delivery line never approaches it; a barcode is 8–14 digits). The office page posts the same endpoints, so it is protected too. A 422 reaches the Shop as "Not saved, try again" (the existing `! data.success`/catch path — confirm which branch handles a 422 JSON body and that the toast shows).
**Check:** `ShopDeliveryTest`: POST scan-increment with `quantity` `5412533401912` → 422 and no `deliveriesScanItems` row; same for update-quantity; a quantity of `9999` still succeeds.

### 3. Tests for steps 1–2
- `ShopStockScanTest` (the field's home): the rendered component carries `capture($event)` on a `keydown.window` binding; source assertions that `capture(event)` checks `burstAt` and that `submit()` resets it.
- `ShopDeliveryTest`: the existing `assertDontSee('keydown.enter.window')` stays green.
**Check:** `php artisan test --filter='ShopStockScanTest|ShopDeliveryTest|ShopViewContractTest'`.

### 4. A failed pause falls back to a stop
`scan-input.js` `pause()`: when `pauseScanner()` returns false (the scanner was not in the SCANNING state — mid-teardown, or the stream already gone), `await this.stop()` so the decoder is never live while a page is showing a prompt. `restartCameraIfWanted()` already handles a closed camera by restarting it (slow path, but correct and rare). Update `pause()`'s comment: pause normally, stop if we cannot, never leave it running. Extend the cycle-30 source test in `ShopStockScanTest` to assert `pause()` contains `this.stop()` (only after the `pauseScanner()` check — a `strpos` order assertion).
**Check:** browser spy: force `pauseScanner` to return false, detect a code → `stop` called once, `cameraOpen` false; then `shop-scan-saved` → `toggleCamera` called (restart path).

### 5. Document the fire-and-forget resume
`scan-input.blade.php` line 15: a Blade comment above the listener: the handler is async and deliberately not awaited — nothing downstream depends on the resume finishing, and awaiting it would hold the event handler open across a camera restart. No code change.
**Check:** contract test green (Blade comments are fine).

### 6. Untrack the remaining swap files
`git rm --cached .delivery-specialist-agent-recommendation.md.kate-swp JFolder_temp/.questions.txt.kate-swp docs/jons_docs/.todo.md.kate-swp`. Files stay on disk. Leave staged.
**Check:** `git ls-files | grep -c "kate-swp"` → 0; `git status --short | grep kate-swp` shows three `D` lines.

### 7. Version-tied notes in the feature doc
`docs/features/shop-mode.md`: a short "Camera scanning" subsection (or extend the existing one if present): html5-qrcode 2.3.8; the three places tied to that version — `pauseScanner`/`resumeScanner` state constants in `resources/js/barcode-scanner.js`, the `.shop-scan__mount > div:not(#qr-shaded-region)` rule in `resources/css/shop.css`, and the mount pinned-absolute rules from cycle 16 — and that a dependency bump must re-check all three on a real phone.
**Check:** the section reads in one screen; links/paths correct.

### 8. Build and tidy
`npm run build`; `php artisan view:clear`; Pint on the controller and the test files you touched.

## Verification (report every item with what you saw)

1. `php artisan test` summary against your measured baseline; no new failures.
2. Contract: `ShopViewContractTest` green; design-block `cmp` prints nothing.
3. Browser, delivery scan page on dev, keyboard-wedge simulation by dispatching `KeyboardEvent('keydown', { key, bubbles: true })` on `window` for each character and then Enter, within a few ms:
   - (a) Scan input focused (normal): burst → one `scan` dispatched, prompt opens. (Unchanged path.)
   - (b) Click "+" on the prompt with the mouse (focus on the button), then burst a *different* real barcode: the prompt for the first item is committed with the quantity as it was (the existing "scan while a prompt is open confirms it" rule), the new prompt opens, and **"+" was not pressed** (qty of the first commit unchanged by the scan). Report the recorded quantity.
   - (c) Click "+" with the mouse and press Enter alone (no burst): "+" increments — a human's Enter still works.
   - (d) With a non-scan text input focused on a page that has both (check whether any Shop page does; if none, say so), typed digits go to that input, not the scan field.
   - (e) Space on a focused button still activates it.
4. Server: the 422 checks in step 2, shown with the response body.
5. Step 4's spy check; step 6's git output.
6. Dev data: put back anything the checks recorded, as in cycles 29–31.

## Risks

- **Burst window of 1 s**: a slow typist typing digits by hand on a physical keyboard with a button focused, then Enter within a second, would submit as a scan. That is the desired behaviour on the till PC (typing a code by hand is a scan). A person pressing Enter on a button more than a second after their last keystroke gets the button.
- **Alpine `keydown.window` on the component root**: no Shop page has two scan fields; note it in the comment.
- **`updateQuantity()` legitimately writes 0** (a correction to nothing) — keep `min:0`.
- **Step 4's stop path** closes the camera block; the user sees it close and reopen on the next item instead of a frozen frame. Rare (only when the library refused to pause) and better than a live decoder under a prompt.
