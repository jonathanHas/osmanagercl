# Shop mode cycle 32 — Hand-scanner keystrokes always reach the scan field, plus camera loose ends — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-28

## Baseline
HEAD: 3fbfcc44

```
R  docs/planImp/implemented.md -> docs/planImp/archive/2026-09-28-shop-mode-cycle-31/implemented.md
RM docs/planImp/plan.md -> docs/planImp/archive/2026-09-28-shop-mode-cycle-31/plan.md
D  docs/planImp/plan-wedge-focus.md
?? docs/planImp/plan.md
```
(Cycle 31's archive move and the side plan's removal were already staged by the
owner. Not mine, untouched.)

**Test baseline measured: `15 failed, 801 passed (3406 assertions)`** — matching
the plan's Context. Tracked swap files at the start: 3.

## Steps

### 1. Capture stray keystrokes into the scan field — done
Changed: `resources/js/shop/scan-input.js`,
`resources/views/components/shop/scan-input.blade.php`.

Added `burstAt`, `capture(event)` and a small `isTextEntry(el)` helper (pulled
out because the condition is five clauses and reads badly inline). `submit()`
resets `burstAt = 0`, so a lone Enter a moment later is treated as a person's.

Bound as `x-on:keydown.window="capture($event)"` on the component root — the
long form, not `@keydown.enter.window`, which the cycle-14 guard forbids and
which `ShopDeliveryTest` still asserts against. A Blade comment above the
binding says why.

The doc comment records the cause: the till's USB scanner types into whatever
has focus, and `refocus()` deliberately lets a button keep focus so the
quantity stepper works, so a scan right after a mouse click on "+" lost its
digits and let the trailing Enter press "+" again.

Check: Verification 3.

### 2. Server refuses a barcode-sized quantity — done
Changed: `app/Http/Controllers/DeliveryLegacyController.php`.

`max:9999` added to both rules, with a comment. **Note the method names differ
from the plan**: the `delivery-legacy.update-quantity` route is served by
`updateScannedQuantity()` (line 1276), not `updateQuantity()`. Same method,
different name; I checked the route list rather than trusting the name, and
both sites are now capped. `min:0` kept — a correction to nothing is
legitimate.

**Which branch shows the error (the plan asked me to confirm):** `post()` in
`delivery-scan.js` does not check `response.ok`, it just parses the JSON. A
Laravel 422 body has no `success` key, so `! data.success` is true and the
`showToast('bad', 'Not saved, try again')` branch runs — not the `catch`.
Confirmed live in Verification 4.

### 3. Tests for steps 1–2 — done
Changed: `tests/Feature/Shop/ShopDeliveryTest.php`,
`tests/Feature/Shop/ShopStockScanTest.php`.

`ShopDeliveryTest`: `test_a_barcode_sized_quantity_is_refused_by_both_endpoints`
(422 + `assertJsonValidationErrors('quantity')` on both, and no row written)
and `test_a_plausible_quantity_is_still_accepted` (9999 → 200). The existing
`assertDontSee('keydown.enter.window')` is untouched and green.

`ShopStockScanTest`: `test_stray_keystrokes_are_captured_into_the_scan_field`
asserts the rendered component carries the binding and still carries no
`keydown.enter.window`, and pins the burst rule, the modifier and text-entry
guards, and the `burstAt` reset in `submit()`.

Check — `php artisan test --filter='ShopStockScanTest|ShopDeliveryTest|ShopViewContractTest'`:
```
Tests:    63 passed (445 assertions)
```

### 4. A failed pause falls back to a stop — done
Changed: `resources/js/shop/scan-input.js`, `tests/Feature/Shop/ShopStockScanTest.php`.

`pause()` now does `if (! this.paused) { await this.stop(); }` with a comment
saying pause normally, stop if we cannot, never leave it running. The cycle-30
source test gained an order assertion that `pause()` contains `await this.stop();`
*after* the `scanner.pauseScanner()` attempt.

**I had to narrow the cycle-30 assertion that `detected()` contains no
`this.stop()`.** It bounded the span with `strpos($field, 'async stop()')`,
which worked only while nothing sat between `detected()` and `stop()`. `pause()`
now does, and its legitimate `await this.stop();` fell inside the span and
failed the test. The span now ends at `async pause()`, the next method, which
is what the assertion always meant. Comment added so the next person does not
re-widen it.

Check: Verification 5.

### 5. Document the fire-and-forget resume — done
Changed: `resources/views/components/shop/scan-input.blade.php` — a Blade
comment above the `shop-scan-saved` listener. No code change. Contract test
green.

### 6. Untrack the remaining swap files — done
```
$ git rm --cached .delivery-specialist-agent-recommendation.md.kate-swp \
                  JFolder_temp/.questions.txt.kate-swp \
                  docs/jons_docs/.todo.md.kate-swp
rm '.delivery-specialist-agent-recommendation.md.kate-swp'
rm 'JFolder_temp/.questions.txt.kate-swp'
rm 'docs/jons_docs/.todo.md.kate-swp'

$ git ls-files | grep -c "kate-swp"
0

$ git status --short | grep kate-swp
D  .delivery-specialist-agent-recommendation.md.kate-swp
D  JFolder_temp/.questions.txt.kate-swp
D  docs/jons_docs/.todo.md.kate-swp
```
All three still on disk, verified with `ls`. Left staged.

### 7. Version-tied notes in the feature doc — done
Changed: `docs/features/shop-mode.md` — a new `### Camera scanning` subsection
before "Shared devices, PINs and locking". Names html5-qrcode 2.3.8, explains
pause-not-stop and the stop fallback, then lists the three version-tied places
with what specifically would break: the `Html5QrcodeScannerState` guards, the
`:not(#qr-shaded-region)` rule, and cycle 16's pinned-absolute mount rules. It
also covers the hand scanner and the 9999 cap, since that is now part of how
scanning works. One screen.

### 8. Build and tidy — done
```
npm run build   → shop-C6qKyuGh.js 37.57 kB │ gzip 10.45 kB   ✓ built in 6.10s
php artisan view:clear                    → cleared
./vendor/bin/pint <controller + 2 test files>  → PASS, 3 files
```

## Deviations

1. **The cycle-30 test assertion had to be narrowed** — described in step 4. It
   is a change to an existing test, not a new one, so calling it out here as
   well: the assertion's meaning is unchanged, only the span it measures.

2. **`php artisan view:clear` was needed mid-cycle to unblock the suite**, and
   this is worth recording because it will recur. Four `ShopDeliveryTest` view
   tests began failing with
   `file_put_contents(storage/framework/views/…): Permission denied`. Not my
   code: the browser checks in earlier cycles compiled that component as
   `www-data` with mode 644, and `storage/framework/views` is
   `drwxrwsr-x jon www-data`, so files www-data creates are not group-writable
   and the CLI test runner (`jon`) cannot recompile them. Editing any Blade
   file that the web server has already compiled reproduces it. Clearing the
   cache fixed it; see Notes for Planner 1 for the durable fix.

Nothing under **Out of scope** was touched: `refocus()` is unchanged (the
button exception stays), and the office match page was not modified — though
it does benefit from step 2, since it posts the same endpoints.

## Verification

1. **`php artisan test`** → `Tests: 15 failed, 804 passed (3429 assertions)`.
   The same 15 pre-existing failures, unchanged in name and count. Passing went
   801 → 804: three new tests. **No new failures.**

2. **Contract.** `ShopViewContractTest` green; design-block `cmp` prints
   nothing; `git status resources/css/` is empty — no CSS change, as the
   Constraints require.

3. **Wedge simulation**, delivery scan page on dev, signed in as katelyn by
   PIN. Each character dispatched as `KeyboardEvent('keydown', {key, bubbles:true})`
   on `window`, then Enter, within a few ms — a hand scanner's shape.

   **(a) Normal path, scan input focused — unchanged.** Worth stating how this
   was measured: dispatching synthetic window keydowns does *not* type into a
   focused input (synthetic events produce no text), and `capture()` correctly
   declines to act because the scan input is text-entry. So a raw burst here
   does nothing, which is the right behaviour but proves nothing. I drove the
   real path instead — value into the field, Enter on the input — and got
   `{"scanEvents":1,"promptOpen":true,"product":"Ecover Non Bio Laundry Liquid
   concentrated 1.5l lavender","qty":1}`. Unchanged.

   **(b) The bug itself.** Clicked `button[aria-label="One more"]` ("+") with
   the mouse: `{"qty":2,"activeIsThePlusButton":true}`. Then burst a *different*
   real barcode while that button still held focus:
   ```
   {"promptProduct":"Biona White Rigatoni 500g","promptQty":1,"scanFieldValue":""}
   ```
   The new prompt opened for the scanned product, and the first item was
   committed at the quantity the user had set. **Recorded quantity, read back
   from the POS: `5412533401912 qty=2`.** Before this cycle the trailing Enter
   would have pressed "+" again and written 3, and the Biona barcode would have
   been lost entirely.

   **(c) A person's lone Enter on a focused button still works.**
   `{"prevented":false,"scanFieldValue":"","qtyUnchanged":true}` — `capture()`
   does not intercept it, so the browser activates the button; the following
   real `click()` took the quantity 1 → 2.

   **(d) No Shop page has both a scan field and a second text input.** I
   checked all four pages that use the component; the only other `<input>` is a
   `type="hidden"` inside the labels print form, which cannot hold focus. So
   the plan's case does not exist today — but the guard is what stops it
   becoming a bug later, so I exercised it directly by injecting each kind of
   field:

   | focused element | intercepted? | went to scan field? |
   |---|---|---|
   | `input[type=text]` | no | no |
   | `input[type=number]` | no | no |
   | `input[type=search]` | no | no |
   | `textarea` | no | no |
   | `[contenteditable]` | no | no |
   | `input[type=checkbox]` | **yes** | **yes** |

   The checkbox row is the control: a field that cannot use the keystroke
   should hand it to the scanner.

   **(e) Space on a focused button** — `{"prevented":false,"scanFieldValue":""}`.
   Not intercepted, so it still presses the button.

4. **Server.** Both endpoints, live, with bodies:
   ```
   increment: 422 {"message":"The quantity field must not be greater than 9999.",
                   "errors":{"quantity":["The quantity field must not be greater than 9999."]}}
   update:    422 {"message":"The quantity field must not be greater than 9999.",
                   "errors":{"quantity":["The quantity field must not be greater than 9999."]}}
   ```
   And what the user sees: driving `commit()` with a barcode-sized quantity gave
   `{"toast":{"tone":"bad","text":"Not saved, try again"},"promptStillOpen":true}`
   and the toast was visibly in the DOM. The prompt stays open, so the person
   can retry — see Notes for Planner 2.

5. **Step 4's fallback.** Forcing `pauseScanner()` to return false:
   `{"stopCalls":1,"cameraOpen":false,"paused":false}` — the decoder is never
   left live under a prompt. Then `restartCameraIfWanted()`:
   `{"toggleCalls":1,"cameraOpen":true}` — the slow restart path brings it back.

   **Step 6's git output** is quoted in full under step 6.

   Console across the whole run: **no errors or exceptions**.

6. **Dev data** — all restored, see below.

## Files changed

```
 M app/Http/Controllers/DeliveryLegacyController.php
 M docs/features/shop-mode.md
 M resources/js/shop/scan-input.js
 M resources/views/components/shop/scan-input.blade.php
 M tests/Feature/Shop/ShopDeliveryTest.php
 M tests/Feature/Shop/ShopStockScanTest.php
D  .delivery-specialist-agent-recommendation.md.kate-swp   (staged; on disk)
D  JFolder_temp/.questions.txt.kate-swp                    (staged; on disk)
D  docs/jons_docs/.todo.md.kate-swp                        (staged; on disk)
?? docs/planImp/implemented.md                             (this file)
```
Plus `public/build/*` from `npm run build`. Cycle 31's staged archive move was
already there when I started.

No commits, no deploys.

## Dev state

The browser check writes through real endpoints. Cleaned up, verified after:
```
delivery scan rows: 0        (one Ecover qty=2 row from case (b), deleted)
devices remaining: 1         (the pre-existing revoked "Dev browser check")
label queue: 0
stock adjustments today: 0
```
The seeded "Cycle 32 browser check" device was deleted, the `shop_device`
cookie cleared and the tab closed. katelyn (id 3) still has PIN `2580`. No
config file was modified.

## Notes for Planner

1. **`storage/framework/views` has a permissions split that will keep biting.**
   The directory is `drwxrwsr-x jon www-data` (setgid), so a file `jon` creates
   is `rw-rw-r--` and www-data can overwrite it — but a file www-data creates
   is `rw-r--r--` and `jon` **cannot**. Any cycle that (a) does a browser check
   and then (b) edits a Blade file that was compiled during it will see the
   test suite fail with a permission error that looks like a code bug. It cost
   me a few minutes this cycle and it will cost the next person more, because
   the stack trace points at Laravel internals.
   The durable fix is a umask or ACL so www-data writes group-writable:
   `sudo setfacl -Rdm g:www-data:rwx storage/framework/views` (plus the same
   for `bootstrap/cache`). Needs root, so it is an owner action, not a cycle.
   Until then: `php artisan view:clear` is the workaround, and it is worth a
   line in the Quick Start guide's troubleshooting section.

2. **A rejected save leaves the prompt open and the camera paused.** On the
   `! data.success` branch `commit()` returns early without clearing `pending`
   or calling `announceSaved()`. Keeping the prompt is right — the person can
   retry — but the camera stays frozen on "Got it" until they do something.
   With step 2 in place this is now reachable by a real (if unlikely) route: a
   422 from the quantity cap. One line (`this.announceSaved()` before the
   `return`) would resume the camera while leaving the prompt up. I did not add
   it: it is outside the plan and it changes behaviour on *every* failed save,
   not just this one. Worth a decision.

3. **The 1 s burst window and the 3.5 s same-code suppression now interact.**
   Both guard the same failure (an unintended second read), from different
   directions, and neither knows about the other. Nothing is wrong today — I
   verified both still behave — but they are two magic numbers in one file with
   no shared comment. If either is ever tuned, the other should be re-read at
   the same time. A sentence in `scan-input.js`'s header naming both would be
   cheap insurance; I did not add it because the plan did not ask and the
   header is already long.

4. **`capture()` assumes one scan field per page**, as the plan's Risk noted.
   That is true today and the doc comment says so. The failure mode if it ever
   stops being true is silent — both fields would fill — so if a screen ever
   needs two, this needs a guard rather than a comment.
