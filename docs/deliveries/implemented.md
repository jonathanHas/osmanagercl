# Cycle 2 — Correction card + / − respond instantly; the scanned-not-on-invoice query stops scanning every supplier link — implementation

Status: BLOCKED
Plan revision: 1
Implementer: Fable 5.1 (this session was started as the Implementer; the protocol names Opus)
Date: 2026-10-08

**Where it stands in one line:** steps 1–6 are done and every automated check
passes (59 delivery tests, 328 Shop tests, full suite at the baseline 15 failed
/ 1042 passed, pint clean on my PHP files, build clean). On dev MySQL the
rewritten query went from **~2,170 ms to ~27 ms** for the 598-scan session
with the same 457 barcodes and no field differences. The one thing not done
is the **browser check in step 7**: the Chrome extension connected this time,
but the dev host showed the login page (signed out in that Chrome profile),
and signing in is not something this session may do. The seven observations
are written below as the owner's checklist, with an open dev session and a
row picked for it, and the status is BLOCKED on that alone, as step 7 directs.
The same flow was driven through the real `delivery-scan.js` in a Node
harness with real timers (under step 7). No POS data was written.

## Baseline
HEAD: 90000850
Pre-existing dirty files:
```
 M docs/deliveries/README.md
R  docs/deliveries/implemented.md -> docs/deliveries/archive/2026-10-08-link-outer-barcode/implemented.md
RM docs/deliveries/plan.md -> docs/deliveries/archive/2026-10-08-link-outer-barcode/plan.md
?? docs/deliveries/plan.md
```
(the cycle 1 archive move, staged by the owner; this file is new)

**Appeared during the cycle, not mine, untouched** (another session is
editing the product create form in parallel; mtime 12:32 today):
```
 M app/Http/Controllers/ProductController.php      (+12)
 M app/Http/Requests/StoreProductRequest.php        (+2)
 M resources/views/products/create.blade.php        (+14)
```

## Steps

### 1. `getScannedNotOnInvoice()` without the cross-collation join — done
Changed: `app/Http/Controllers/DeliveryLegacyController.php` (the method body
and docblock; nothing else in the file for this step)
- Four steps as the plan: scanned totals keyed by `(string)` barcode; the
  invoice's barcodes via `delivery ⋈ supplier_link` (latin1 both sides),
  `distinct()->pluck()`; `array_diff` as strings; two `whereIn` queries
  (`PRODUCTS` left-joined to `CATEGORIES`/`TAXES`/`STOCKCURRENT`, first row
  per `CODE` wins; `supplier_link` for the supplier) keyed by code; one
  `(object)` per extra with exactly the ten keys; `usort` nulls first then
  name, **case-insensitive** (`strcasecmp`, see Deviation 3). Empty inputs
  return `[]` early.
Check output — tests:
```
$ php artisan test tests/Feature/Shop/ShopDeliveryTest.php
  Tests:    59 passed            (including completion / undo through the extras)
```
Check output — dev MySQL timing, session with the most scan rows, bound
closure as the plan's snippet, warm, three runs each:
```
session 685c8d19-bba4-11ef-b648-10c37b4d894e scans 598 (supplier 5; status 1 = completed, read-only here)
before: run 1: 457 rows 2180 ms | run 2: 457 rows 2177 ms | run 3: 457 rows 2153 ms
after:  run 1: 457 rows   28 ms | run 2: 457 rows   24 ms | run 3: 457 rows   30 ms
same Barcode set as before (sorted): true
field differences over NAME, PRICESELL, RATE, SupplierCode, CaseUnits, productID, UNITS, categoryName, scanned: 0
same name order as before: false — one difference, at index 443 of 457:
  now 'Zonnemaire Baguete white 2pc' before 'ZÃ¼ger Cottage Cheese 200g'
  (a mojibake name: utf8_general_ci folds Ã to A so MySQL sorted it before "Zo…";
   PHP's byte order puts it after. Cosmetic; see Deviation 3.)
keys: Barcode,scanned,NAME,PRICESELL,RATE,SupplierCode,CaseUnits,productID,UNITS,categoryName
```
Dev was only read. The "before" row set was saved to the session scratchpad
before the edit and compared against after it.

### 2. A test with a product-backed extra row — done
Changed: `tests/Feature/Shop/ShopDeliveryTest.php` —
`test_an_extra_product_with_a_supplier_link_keeps_its_details` as specified
(p3 "Hazelnuts 500 g" `5000000000031`, stock 4, link S3 case 10, scan ×2;
asserts status/code/name/stock/scanned/stockable, the no-product row's
fields, `progress` `total 2, checked 2, issues 3`, and the null-name extra
before Hazelnuts).
Check output: passes; the file is at 56 after this step (59 at the end).

### 3. The PATCH skips financials when asked — done
Changed: `app/Http/Controllers/DeliveryLegacyController.php`
(`updateScannedQuantity()`: `'financials' => 'sometimes|boolean'`, the three
queries and `calculateFinancials()` only when
`$request->boolean('financials', true)`, response built as an array),
`resources/js/shop/delivery-scan.js` (`financials: false` in the PATCH body,
in the new `quantityBody()` helper).
Check output:
```
test_the_shop_page_can_skip_financials_on_a_correction: passes
  (financials:false → 200, success true, quantity 7, no `financials` key, 7 stored;
   no flag → financials.totalItems = 2)
$ grep -n "financials: false" resources/js/shop/delivery-scan.js
<one line>  (grep -c → 1)
```

### 4. The correction card steps locally and saves once taps stop — done
Changed: `resources/js/shop/delivery-scan.js`, `resources/views/shop/delivery-scan.blade.php`
- State `editValue`, `flushTimer`, `flushPending` with a comment.
- `edit(row)`: calls `flushNow()` first (leaving a row saves its taps), sets
  `editValue = row.scanned ?? 0` on opening.
- `$watch('editing', (value, old) => { this.flushNow(old); this.editTyped = null; })`
  — the row just left is passed explicitly (Deviation 1).
- `adjust(row, delta)`: local step, `flushPending = true`, timer
  `setTimeout(() => this.flushNow(), 400)`. No request. The two stepper
  buttons lost `:disabled="busy"`; the value shows `stockText(editValue)`.
- `flushNow(barcode = this.editing)`: returns false when nothing is pending;
  when `busy`, re-arms the timer with the same barcode and returns; else
  clears the timer, takes `row` and `value` before the first await, awaits
  `saveQuantity()`; on failure re-arms `flushPending` while the card is still
  on that row ("Could not save" toast comes from `saveQuantity()`).
- `typeCorrection()` starts from `editValue`; `setCorrection()` sets
  `editValue`, `flushPending`, and awaits `flushNow()` (immediate).
- `lookup()`: `await this.flushNow()` before `this.editing = null`.
- `pagehide` listener in `init()`: with a pending tap, `fetch(updateUrl,
  { ...requestInit(quantityBody(editing, editValue), 'PATCH'), keepalive: true })`,
  no reload. `quantityBody()` is shared with `saveQuantity()`; `requestInit()`
  is shared with `post()`.
- Header comment: the sentence on local stepping and the 400 ms save, with
  the owner's 2026-10-08 report as the reason.
- `README.md` rule wording: left to the Planner (the README is Planner-owned).
Check output:
```
$ php artisan test tests/Feature/Shop/ShopDeliveryTest.php tests/Feature/Shop/ShopViewContractTest.php
  Tests:    86 passed (625 assertions)       (59 + 27)
$ npm run build
✓ built in 9.01s
```

### 5. Tests for the card — done
Changed: `tests/Feature/Shop/ShopDeliveryTest.php`
- `test_the_correction_card_steps_locally_and_saves_once` (view shows
  `stockText(editValue)`, both stepper buttons without `:disabled`, JS has
  `flushNow()`, the 400 ms `setTimeout`, `keepalive: true`, and `adjust()`'s
  body has no `saveQuantity(`).
- `test_a_scan_flushes_a_pending_correction_first` (`await this.flushNow();`
  before `this.editing = null;` inside `lookup`).
- `test_the_correction_card_has_one_primary_button_while_typing`: its
  `setCorrection()` assertion now expects `if (await this.flushNow()) {` and
  bounds the body at `quantityBody(` (Deviation 2).
Check output: `ShopDeliveryTest` → **59 passed**.

### 6. Docs — done
Changed: `docs/features/shop-mode.md` ("### Deliveries: correcting a
quantity", two paragraphs, after the outer-codes section),
`docs/development/known-issues.md` (a "Second instance" sub-heading inside the
POS collation section, naming the two columns and the fix).
Check output:
```
$ grep -c "correcting a quantity" docs/features/shop-mode.md
1
$ grep -c "deliveriesScanItems" docs/development/known-issues.md
2
```

### 7. Format, build, browser check — partly done (browser check not run)
- Pint applied to the two PHP files I changed; `--test` on them passes.
  `npm run build` clean.
- **Browser check: not run.** The Chrome extension connected this time
  (unlike cycle 1); the dev host answered with the login page, with
  credentials autofilled in the Chrome profile. Signing in is outside what
  this session may do, so the tab was left on the login page.
- **Node harness** (`scratchpad/harness3.mjs`, not in the repo): real module,
  Alpine's `$watch` emulated with a setter on `editing`, `fetch` scripted
  (`items` returns two rows from a mock server that the PATCH updates), real
  timers. Output, trimmed:
  ```
  1. open the card on A (scanned 6)                       editValue 6, 0 PATCHes
  2. tap + five times quickly                              editValue 11 at once, row still 6, pending, 0 PATCHes
  2b. 150 ms later                                         still 0 PATCHes
  2c. 550 ms after the last tap                            1 PATCH {barcode A, quantity 11, financials false}, 1 reload, row 11
  3. tap − five times, wait                                1 more PATCH (6), row 6
  4. type 9 and Set                                        immediate PATCH (9), editTyped null afterwards
  5. tap +, then × within 400 ms                           the watch flushed: PATCH (10), row 10
  6. tap + then scan B within 400 ms                       order: PATCH /update → GET /items → POST /scan
  7. tap + on A then open B within 400 ms                  A saved (12); card shows B's own figure
  8. slow server (300 ms PATCH), taps during the save      final editValue 15 = server 15 (last value wins; 4 PATCHes)
  9. tap + then pagehide                                   one PATCH with keepalive=true, qty 16, no reload
  ```
  Not covered: the rendered DOM, `scan-input.js` focus handling, real
  network timing.
- **Owner's checklist (step 7, dev host, signed in as `test`).** The session
  used for the timing is completed, so use the open one with the most scans:
  `/shop/deliveries/scan?delID=9e5c52db-9b1b-4641-95e3-4e5b33cd8a24&supplierID=13`
  (3 scan rows). Row to use: **Coolfin Raw Honey 340g**, barcode
  `5391209002625`, **quantity 27 before the check** (note it again on screen).
  1. [ ] Tap the row → the card opens showing 27.
  2. [ ] Tap + five times quickly → the number climbs at once to 32; in the
         network log exactly one PATCH `update-quantity` (body has
         `"financials":false`) and one GET `items` appear about 400 ms after
         the last tap; the row then shows 32.
  3. [ ] Tap − five times → 27 again; one PATCH, one GET.
  4. [ ] Tap the number, type `30`, Set → saved at once; row shows 30.
  5. [ ] Tap + once, then within 400 ms type a barcode into the scan field and
         Enter → in the network log the PATCH lands before the GET `items`,
         and the scan lookup follows; cancel the prompt.
  6. [ ] Put the row back: tap the number, type `27`, Set (or
         `php artisan tinker --execute="…update-quantity…"` is not needed:
         confirm with
         `php artisan tinker --execute="echo DB::connection('pos')->table('deliveriesScanItems')->where('delID','9e5c52db-9b1b-4641-95e3-4e5b33cd8a24')->where('barcode','5391209002625')->sum('quantity');"`
         → `27`).
  7. [ ] Console clean of errors throughout.
- **Dev state:** nothing written this session (all tinker calls were reads;
  the harness mocked `fetch`). The checklist row's quantity is 27 and must
  read 27 again after the owner's check.

## Deviations
1. **`flushNow()` takes the barcode of the row to save.** The plan's
   `if (! flushPending || ! editingRow) return` can never flush on close: by
   the time the `editing` watch runs, `editing` is already null (or the next
   row), so `editingRow` is null. The watch now passes its `old` value, and
   `flushNow()` finds the row in `rows` by that barcode, taking `value` before
   the first await. Same behaviour the plan asks for; the guard is on the
   passed barcode instead.
2. **`setCorrection()` saves through `flushNow()`** rather than
   `saveQuantity()` directly, so Set and the stepper share one path; the
   existing test's assertion was updated to the new shape (the plan allows
   extending that test). Still immediate; still keeps the field open on
   failure.
3. **Name order is case-insensitive** (`strcasecmp`) rather than the plan's
   raw `<=>`, because MySQL's `ORDER BY PRODUCTS.NAME` under utf8_general_ci
   is case-insensitive. It is not accent-folding, hence the one cosmetic order
   difference on dev (a mojibake "ZÃ¼ger" name). Nulls first as specified.
4. **A `requestInit()` helper** was split out of `post()` so the keepalive
   PATCH on `pagehide` uses the same headers and credentials; the plan asked
   only for the body helper (`quantityBody()`), which is also there.
5. **Browser check not run** (signed out on dev); checklist written and
   BLOCKED on that alone, as step 7 directs.
6. **README rule wording** not added: the README is Planner-owned.

## Verification
1. `./vendor/bin/pint --test app/Http/Controllers/DeliveryLegacyController.php tests/Feature/Shop/ShopDeliveryTest.php` → PASS.
   (`git diff --name-only -- '*.php'` now also lists `ProductController.php`
   and `StoreProductRequest.php`, which are another session's and were not
   formatted or checked here.)
2. `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` → **59 passed**.
3. `php artisan test tests/Feature/Shop` → **328 passed** (1675 assertions).
4. `php artisan test` → **15 failed, 1042 passed** (4796 assertions). The 15 by
   class: `Tests\Unit\UdeaScrapingServiceTest` ×7,
   `Tests\Feature\CashReconciliationTest` ×3,
   `Tests\Feature\FruitVegLabelPrintingTest` ×2, `Tests\Feature\ProductTest` ×2,
   `Tests\Feature\TestScraperControllerTest` ×1 — the baseline set, nothing
   else. (Run while the other session's product-form edits were on disk.)
5. `npm run build` → `✓ built in 9.01s`.
6. Dev MySQL timing before / after: **2,170 ms → 27 ms** (step 1), same 457
   barcodes, 0 field differences, one cosmetic order difference.
7. Browser check → **not run**; owner's checklist under step 7.

## Files changed
Mine:
```
 M app/Http/Controllers/DeliveryLegacyController.php
 M docs/development/known-issues.md
 M docs/features/shop-mode.md
 M resources/js/shop/delivery-scan.js
 M resources/views/shop/delivery-scan.blade.php
 M tests/Feature/Shop/ShopDeliveryTest.php
?? docs/deliveries/implemented.md
```
Pre-existing (owner): `docs/deliveries/README.md`, the staged archive
renames, `?? docs/deliveries/plan.md`. Another session's, untouched:
`app/Http/Controllers/ProductController.php`,
`app/Http/Requests/StoreProductRequest.php`,
`resources/views/products/create.blade.php`.
`git diff --stat` over everything: 11 files, 507 insertions, 77 deletions
(28 of the insertions are the other session's).
Nothing committed.

## Notes for Planner
- **Another session is editing in this working tree** (product create form).
  The owner should be aware when committing cycle 2 that `git add -A` would
  sweep those in.
- **`php artisan tinker <file>` hangs** after running the file (it drops into
  the REPL and waits on stdin); `--execute="include '…';"` with a `timeout`
  does not. Worth a line in the README's "Checking a change".
- **The timing session is completed** (`status 1`). The method is read-only,
  so the timing stands; the browser checklist uses an open session instead.
  The scratch timing script's "second session" line re-ran the first (an
  arrow function captured `$delID` by value), so only session `685c8d19-…`
  timings are claimed.
- **Edge left as the plan has it:** a flush deferred because `busy` keeps the
  barcode but reads `editValue` when it finally runs; if the card has moved
  to another row *and* that row was tapped in the meantime, the earlier
  row's taps are lost (the new row's `adjust()` clears the shared timer).
  Needs a scan in flight plus a row switch plus a tap inside ~400 ms. A
  per-row pending map would close it; not done.
- **`pagehide` with bfcache:** `flushPending` is set false before the
  keepalive fetch; if the page is restored from the back/forward cache and
  the fetch had failed, the tap is lost silently. Acceptable for a phone tab
  going to the background; mentioned for completeness.
- **`getOnInvoiceNotScanned()` (95 ms)** is now the slowest query in the
  Shop page's path only through the office financials, which the Shop PATCH
  no longer asks for. Out of scope as the plan says.
- The one order difference in step 1 could be removed by sorting with
  `Collator` (intl) in `en_US` with primary strength, which does fold accents
  like MySQL; not done because the plan specified a plain `usort` and the
  difference is cosmetic.
