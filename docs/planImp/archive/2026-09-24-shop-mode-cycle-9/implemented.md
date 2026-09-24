# Shop mode cycle 9 (Revision 3) — flash messages made visible — implementation

Status: DONE
Plan revision: 3
Implementer: Opus
Date: 2026-09-24

Scope: steps 10–12 only. Steps 1–9 are accepted as delivered; step 12 edits the
guard step 8 added, and nothing else from earlier revisions changes.

## Baseline
HEAD: 31c0bc18
Working tree carries cycles 8 and 9 (Revisions 1 and 2), all accepted, all
uncommitted — the same 19 entries the Revision 2 report listed. Revision 3 touches
five files: `resources/views/layouts/shop.blade.php`,
`resources/views/delivery-legacy/match.blade.php`,
`app/Http/Controllers/DeliveryLegacyController.php`,
`tests/Feature/Shop/ShopHomeTest.php`, `tests/Feature/Shop/ShopDeliveryTest.php`.

## Pre-flight

Two of the plan's assumptions checked before relying on them:

**The dismiss button will work.** `.shop-toasts` is `pointer-events: none` (line
397) but `.shop-toast` is `pointer-events: auto` (line 398), so the button inside
the toast receives clicks. The plan states this; it is true.

**The office banner markup exists to copy.** `resources/views/delivery-legacy/index.blade.php`
lines 12–16 carry exactly the `@if(session('error'))` block the plan points at, and
`match.blade.php` has `session('success')` at line 434 with no `error` sibling.

## Steps
### 10. Server flash messages in the Shop layout — done
Changed: `resources/views/layouts/shop.blade.php` (toast block between the top bar
and the slot), `tests/Feature/Shop/ShopHomeTest.php` (three tests)
Check output:
```
$ php artisan test --filter=ShopHomeTest
  ✓ flash success is shown as a toast
  ✓ flash error is shown as a bad toast
  ✓ no toast region without a flash
  ✓ employee sees only the tiles they may use
  ✓ barista sees only coffee orders and no office switch
  ✓ manager sees the office switch
  ✓ user with no permissions sees the empty state
  ✓ page is marked as the shop shell
  ✓ deliveries tile links to the shop delivery list
  ✓ guest is sent to login

  Tests:    10 passed (30 assertions)

$ grep -c "session('error')" resources/views/layouts/shop.blade.php
4
```
10 tests, not the plan's 9 — the class already held 7 and the plan added 3. The
grep reads 4, not the plan's "2 or 3": the plan's own snippet contains four
occurrences (the condition, the class ternary, the icon ternary and the
null-coalesce in the text). Nothing was added beyond the snippet.

The error test also asserts `assertDontSee('shop-toast--ok')`, so the ternaries
cannot both fire.

### 11. The office match page shows the refusal — done
Changed: `resources/views/delivery-legacy/match.blade.php`
Check output:
```
$ git diff --stat resources/views/delivery-legacy/
 resources/views/delivery-legacy/match.blade.php | 6 ++++++
 1 file changed, 6 insertions(+)

$ git diff resources/views/delivery-legacy/ | grep "^-" | grep -v "^---"
(no output — a pure addition)

$ grep -c "session('error')" resources/views/delivery-legacy/match.blade.php
2
```
One file, six added lines, nothing removed. The grep reads 2 rather than the plan's
1, for the same reason as step 10: the block the plan specified contains both the
`@if` and the echo. Markup copied from the office index (lines 12–16) so the two
office pages show errors identically.

### 12. Deliberate fallback for the refusal's redirect — done
Changed: `app/Http/Controllers/DeliveryLegacyController.php` (the step 8 guard),
`tests/Feature/Shop/ShopDeliveryTest.php` (one test)
Check output:
```
$ php artisan test --filter=ShopDeliveryTest
  ... 17 tests ...
  ✓ completing twice does not add stock twice
  ✓ refusing a second completion falls back to the right page
  ✓ completing without the shop flag returns to the office page

  Tests:    17 passed (98 assertions)
```
17, within the plan's "16 or 17". The new test makes both POSTs with no referer
header, which is what forces the fallback to be used rather than `back()`'s
referer: `return=shop` lands on the Shop summary, and without the flag on the
office match page. Both also assert the `error` flash, so the refusal and its
destination are pinned together.

### Build and format — done
```
$ npm run build                    → ✓ built in 12.88s
$ ./vendor/bin/pint --dirty        → PASS 8 files
$ ./vendor/bin/pint --test --dirty → PASS 8 files
```

## Deviations

None. All three steps went in as specified.

Two of the plan's `grep -c` expectations were undercounts of the plan's own
snippets, not signs of extra code: step 10 predicted "2 or 3" and the snippet
contains 4; step 11 predicted 1 and the block contains 2. Recorded so a reviewer
rerunning the checks does not read them as drift.

## Verification (Revision 3)

1. `php artisan route:list --name=shop.deliveries` → **pass**, three routes,
   unchanged from Revision 2.

2. `php artisan test --filter=Shop` → **pass**, `Tests: 127 passed (411 assertions)`
   — the top of the plan's "126 or 127". The contract test still lists six screens.

3. `php artisan test` → **pass**: `Tests: 17 failed, 527 passed (2031 assertions)`.
   Revision 2 ended at 17 failed / 523 passed; the 4 extra passes are the 4 new
   tests (3 in `ShopHomeTest`, 1 in `ShopDeliveryTest`). The 17 failures are the
   identical pre-existing set, confirmed by class:
```
  3 Tests\Feature\CashReconciliationTest
  2 Tests\Feature\FruitVegLabelPrintingTest
  2 Tests\Feature\ProductTest
  1 Tests\Feature\TestScraperControllerTest
  2 Tests\Feature\WasteLogTest
  7 Tests\Unit\UdeaScrapingServiceTest
```

4. `git diff --stat resources/views/delivery-legacy/` → only `match.blade.php`,
   `6 ++++++`, no removals. **pass** — this is the cycle's one office-view edit, as
   step 11 allows. The legacy controller's only Revision 3 change is inside the
   step 8 guard.

5. `grep -rn "<script\|<style\|keydown.enter.window" resources/views/shop/` → no
   output. **pass.** Worth being explicit: the `x-data`/`x-init` the toast block
   uses lives in `resources/views/layouts/shop.blade.php`, which is outside
   `resources/views/shop/` and is deliberately not scanned by
   `ShopViewContractTest` — its docblock says so, and the layout already carries
   the touch-detect `<script>` and the stale-page detector. So the contract is
   intact rather than circumvented.

6. `head -c ... | cmp - docs/design/shop-mode/shop.css` → `DESIGN-BLOCK-IDENTICAL`.
   **pass** — no stylesheet change in any revision of this cycle; the toast reuses
   the design's own `shop-toast` rules.

7. `./vendor/bin/pint --test --dirty` → `PASS 8 files`; `npm run build` →
   `✓ built in 12.88s`. **pass**

8. Manual walkthrough — **RUN, 2026-09-24, against the live dev app and POS
   database**, driving the owner's Chrome. The owner authorised the full
   walkthrough including completing a delivery. This closes the manual gap that
   every report since cycle 4 has carried.

   Session used: `da16f9e0` (Udea, supplier 5). Before-state recorded first:
```
Vivani 50% Cacao 80g   (4044889002560)  UNITS = 23.0   scanned 19 on the session
Sodasan Colour Compact (4019886050203)  UNITS =  0.0
outer 4019886650205 -> unit 4019886050203, CaseUnits 4
```

   | Check | Result |
   |---|---|
   | Case/outer barcode scan | Prompt showed "Case of 4" and "Add 1 case · 4 units" |
   | Prompt stepper | + → "Add 2 cases · 8 units"; − → back to 1 case |
   | **Enter on empty input confirms the prompt** | Committed **once**: the row read **4**, not 8 |
   | Unknown barcode | Input turned red, "Product not found for 9999999999999", nothing recorded |
   | Pencil row stepper | 4 → 3 → 4, written each time |
   | Confirmation figures | "Units to add 23 · Products 2" (19 + 4) |
   | "Not yet" | Hid the card; "Complete delivery" reopened it |
   | **Completion** | Redirected to the list; **green toast "Delivery completed. 23 units added to stock for 2 products."** |
   | Stock written | Vivani 23 → 42 (+19), Sodasan 0 → 4 (+4); session `status` = 1 |
   | **Double-completion guard** | Browser back restored the page from bfcache with "Yes, complete" still live; clicking it gave the **red toast "This delivery is already completed."**, landed on the summary (step 12's fallback), and **stock did not move** (42 / 4 unchanged) |
   | Completed summary | Shows "This delivery is completed." + green Completed pill; the Complete button is gone, only "Keep scanning" remains |
   | Office agreement | The office match page lists the same two extras with the same quantities and Sodasan Stock 4 |

   The bfcache case is worth calling out: it is exactly the scenario step 8 was
   written for, and it happens without any user error — the browser restored a page
   whose Complete button was still live for a session that was already closed.
   Without the guard that click would have added another 23 units.

   **Still not observed:** the overlapping toast regions. The server flash and a
   page's own client toasts are both `.shop-toasts`, `position: fixed` at the same
   offset. The only way to see both at once is to start a delivery (server flash on
   arrival at the scan screen) and scan something within the 8 seconds before it
   auto-dismisses. I did not test it because it would leave a stray open session
   behind. The repro is exact if the Planner wants it covered.

## Files changed

Revision 3 touched five files:
```
 M app/Http/Controllers/DeliveryLegacyController.php   (step 12, inside the step 8 guard)
 M resources/views/delivery-legacy/match.blade.php     (step 11, +6, new this cycle)
 M resources/views/layouts/shop.blade.php              (step 10, new this cycle)
 M tests/Feature/Shop/ShopHomeTest.php                 (step 10, three tests)
?? tests/Feature/Shop/ShopDeliveryTest.php             (step 12, one test)
```
`resources/views/layouts/shop.blade.php` and
`resources/views/delivery-legacy/match.blade.php` are new entries in the cycle's
dirty set — every earlier revision left both untouched. Everything else in
`git status --short` is cycles 8 and 9 as the Revision 2 report described it.

## Notes for Planner

- **Two flash keys are now load-bearing across two shells.** `completeDelivery()`
  and `createSession()` set `success`/`error`, and three views render them: the
  Shop layout (all Shop screens), the office match page and the office index. Any
  future controller that flashes from a Shop-facing action gets the toast for free,
  which is the point — but it also means an unrelated flash set elsewhere in the
  app will now surface on a Shop screen if the user lands there next. Nothing does
  that today.
- **The toast is not dismissed by navigation, only by time or tap.** Laravel clears
  the flash on the next request, so it cannot reappear; the 8 seconds only governs
  how long it sits on the page it landed on. That is the intended behaviour, noted
  because "toast with a timer" often implies a queue and this is deliberately a
  single message.
- **Overlapping toast regions** are the one visual unknown (Verification 8). If it
  does look wrong, the cheap fix is a modifier on the layout's container
  (`shop-toasts--flash`) offset above the client one — which would be the first
  `APP ADDITIONS` rule this cycle needs.
- **`ShopHomeTest` is now the de facto layout test.** The three flash tests are
  about the layout, not Home; Home is simply the only screen with no client toast
  region, which is what makes the negative assertion possible. If a later cycle
  gives Home a client toast region, `no_toast_region_without_a_flash` will start
  failing for a reason that has nothing to do with flashes.
- **DEFECT FOUND IN THE BROWSER (cycle 8, not this revision): the Home badge and
  the delivery list disagree.** The tile reads 6 open, the list header reads 5, and
  one genuinely open session is unreachable in Shop mode:
```
badge query (all open):                6
last 50 by dateUpload, of which open:  5
total sessions:                     1201
INVISIBLE IN SHOP: 97b3c357  Imbibe  2026-05-20 09:25:19
oldest of the visible 50:            2026-06-18 10:30:55
```
  `Shop\DeliveryController::index()` takes the 50 most recent sessions by
  `dateUpload` and *then* filters to `status = 0`. With 1,201 sessions in the real
  database that window only reaches back to 18 June, so an open Imbibe session from
  20 May is counted by the badge (which is unwindowed) but never listed. Staff
  would have to use the office page to find it. No test could have caught this: the
  fixture has one session. The fix is to select open sessions without a limit —
  there are only ever a handful — and apply `limit(50)` to the completed list only.
  I have not fixed it; it is cycle 8's code and outside Revision 3's steps.
- **Cosmetic, same walkthrough:** a session whose supplier has no rows in the
  `delivery` scratch table reads "0 of 0 items · 2 issues" on the scan screen and
  "0 items expected" on the summary. Correct per the documented per-sync behaviour,
  but the zero sits oddly beside a non-zero issue count. Also, the toast markup
  always uses the `check` icon, so a warning toast ("Not on this invoice") shows a
  tick; the layout flash toast picks its icon properly, the client one does not.
- **Nothing committed, pushed or deployed.** Cycles 8 and 9 across three revisions
  are still stacked in one dirty tree; the Planner has recommended a commit to the
  owner after this revision is accepted, and I would second that — this report is
  the third in a row that has had to describe the tree rather than a diff.
