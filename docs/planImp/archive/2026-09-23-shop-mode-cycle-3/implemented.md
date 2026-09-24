# Shop mode cycle 3 — Stock scan (screen 02) — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-23

## Baseline

HEAD: `47ee616f` ("1st deploy of shop layout") — matches the plan.

`git status --short` at start (only planImp bookkeeping; no application code dirty):
```
 D docs/planImp/implemented.md
 M docs/planImp/plan.md
?? docs/planImp/archive/2026-09-23-scraping-queue-cleanup/
```

Premises checked against the tree before starting:
- `resources/js/shop.js` is the cycle-1 stub with a single `alpine:init` listener. ✓
- `config/shop.php:13` `stock-scan` tile → `'route' => 'stocking.index', 'permissions' => ['products.view']`. ✓
- `routes/web.php:69-71` holds the `Route::prefix('shop')->name('shop.')` group with only `home`. ✓
- `StockingController::lookup()` returns `{found, product{name,code,category}, stock}`;
  `updateStock()` validates `barcode` + `new_stock` (numeric 0–9999.99), writes `STOCKCURRENT`
  via `updateOrCreate` and logs `StockAdjustment` with `source => 'stocking'`. ✓
- `resources/js/barcode-scanner.js` exports `scanFile`, `startScanner`, `stopScanner`, `isRunning`. ✓
- Office `parseBarcode()` / beep / 2 s duplicate guard / `stockingScanHistory` shape
  (`{name, code, stock, time}`, newest first, max 10) read from
  `resources/views/stocking/index.blade.php` lines 205–245 and 368–400 for faithful reuse. ✓

## Steps

### 1. Route and controller — done
Changed: `app/Http/Controllers/Shop/StockScanController.php` (new), `routes/web.php`
Check output:
```
$ php artisan route:list --name=shop.stock-scan
  GET|HEAD       shop/stock-scan shop.stock-scan › Shop\StockScanController@index

$ middleware on that route:
['Illuminate\Auth\Middleware\Authenticate', 'App\Http\Middleware\PermissionMiddleware:stocking.scan']
```

### 2. Home tile points at the new page — done
Changed: `config/shop.php`, `tests/Feature/Shop/ShopHomeTest.php`
```
['key' => 'stock-scan', ... 'route' => 'shop.stock-scan', 'permissions' => ['stocking.scan'], 'badge' => null],
```
`ShopHomeTest` did fail on the first run, exactly as the plan anticipated, and I took the option it
offered: its employee fixture granted `products.view`, which no longer reveals the tile, so
`test_employee_sees_only_the_tiles_they_may_use` now grants `stocking.scan` in place of
`products.view`. One line; no assertion changed.
```
$ php artisan test --filter=ShopHomeTest     (before)   1 failed, 6 passed
$ php artisan test --filter=ShopHomeTest     (after)    7 passed (22 assertions)
```

### 3. JavaScript layout: `shop.js` becomes an index — done
Changed: `resources/js/shop.js`, `resources/js/shop/scan-input.js` (new), `resources/js/shop/stock-scan.js` (new)
`alpine:init` remains the only registration point. Build evidence is under step 8.

### 4. The scan-input component — done
Changed: `resources/views/components/shop/scan-input.blade.php` (new), `resources/js/shop/scan-input.js`
Markup follows the design's `.shop-scan` block; the camera mounts on the inner class-free
`<div :id="cameraId">` so the reticle and label survive `html5-qrcode` replacing its children.
GS1 handling copied from the office page and exercised directly:
```
$ node … import('./resources/js/shop/scan-input.js')
scan-input keys: value, keyboard, cameraOpen, cameraWanted, error, cameraId, lastCode, lastAt,
                 init, inputMode, isTouch, focus, parseBarcode, submit, refocus, done, fail,
                 toggleKeyboard, toggleCamera, detected, stop, restartCameraIfWanted
parseBarcode("]C1010759876543210812345") -> 07598765432108      ← GTIN-14 extracted
parseBarcode("5000000000017")            -> 5000000000017       ← plain code passes through
```
```
$ php artisan view:cache      INFO  Blade templates cached successfully.
$ grep -c "shop-" resources/views/components/shop/scan-input.blade.php
11
$ grep -l "shop-camera-" public/build/assets/shop-*.js
public/build/assets/shop-b7H6LJ6E.js
```
Every class the component uses exists in `resources/css/shop.css` (checked `shop-sr-only`,
`shop-touch-only`, `shop-scan__reticle`, `shop-scan__camlabel`); the file was not edited.

### 5. Page behaviour: `stock-scan.js` — done
Changed: `resources/js/shop/stock-scan.js`
URLs come from `this.$root.dataset`; the csrf token from the meta tag. No Blade in the file
(Verification 4). The pure logic was exercised in node with the browser globals stubbed:
```
± then 99, stock 24:  delta -24 | label −24 | newStock 0    (clamped at zero)
± then 6:             delta -6  | label −6  | newStock 18
± again (back to +6): delta 6   | label +6  | newStock 30
+++:                  delta 3   | label +3  | tone is-plus | newStock 27
then type 2:          delta 2   | newStock 26
step -1 then type 5:  delta -5  | label −5  | newStock 19
deltaLabel at 0:      "0" | tone ""
rowMeta no note:      "10:38"                 ← office-written rows have no note
rowMeta with note:    "10:38 · set to 8"
```
**A bug in my first version, found by that exercise and fixed** — see Deviation 1.

### 6. The screen — done
Changed: `resources/views/shop/stock-scan.blade.php` (new)
Composed from the design: `shop-split`, scan input, product card (`x-show="product"`), empty state,
number pad, "Last scans", actions bar, toast region. The nine digit keys come from a Blade
`@foreach (range(1, 9))` rather than nine copied lines.
Check output:
```
$ php artisan test --filter=ShopViewContractTest
  ✓ there are screens to check
  ✓ screen carries no styling or behaviour with data set #0
  ✓ screen carries no styling or behaviour with data set #1     ← two screens now
  Tests:    3 passed (26 assertions)

$ php artisan view:cache      INFO  Blade templates cached successfully.
```

### 7. Tests — done
Changed: `tests/Feature/Shop/ShopStockScanTest.php` (new)
```
$ php artisan test --filter=ShopStockScanTest
  ✓ employee can open the stock scan screen
  ✓ barista is forbidden
  ✓ guest is sent to login
  ✓ home tile links to the stock scan screen
  ✓ home tile is hidden without the stocking permission
  ✓ lookup returns the product and whole stock
  ✓ lookup reports an unknown barcode
  ✓ update stock writes the pos row and logs the adjustment
  Tests:    8 passed (22 assertions)
```
The last three are the first coverage the two stocking endpoints have had. The update test asserts
both sides: `STOCKCURRENT.UNITS` becomes 30, and `stock_adjustments` gains a row with
`old_stock 24`, `new_stock 30`, `adjustment 6`, the employee's `user_id` and `source 'stocking'`.
The plan listed one tile test; I split it into two (`links_to` and `is_hidden_without`) so a failure
names which half broke.

### 8. Build and format — done
```
$ npm run build
public/build/assets/shop--SBCLd7J.css     37.26 kB │ gzip:  6.70 kB
public/build/assets/shop-czleMNzq.js       6.75 kB │ gzip:  2.87 kB
public/build/assets/barcode-scanner-*.js  335.57 kB │ gzip: 100.41 kB
✓ built in 15.27s

$ manifest still lists the entries:
"resources/css/shop.css"
"resources/js/shop.js"

$ is barcode-scanner a separate chunk, not inlined?
grep -o 'import("[^"]*barcode[^"]*")' public/build/assets/shop-*.js
import("./barcode-scanner-C5jdikgW.js")
grep -c "html5-qrcode\|Html5Qrcode" public/build/assets/shop-*.js
0
```
So Home does not pay for `html5-qrcode`: it loads only when the camera button is tapped.
```
$ ./vendor/bin/pint --test --dirty     PASS  5 files
```

## Deviations

**1. Fixed a sign bug in my own `stock-scan.js` before finishing step 5.**
The plan says `'sign'` "flips the sign of `delta` (and of `typed` interpretation)". Deriving the sign
from `delta`, as I first did, breaks at zero: `key('sign')` on `delta = 0` produces `-0`, and `-0 < 0`
is `false` in JavaScript, so `applyTyped()` read the sign as positive. Pressing `±` then typing on a
freshly scanned product therefore *added* stock instead of removing it — the most likely sequence on
the floor, and silently wrong. Fixed by holding an explicit `sign` state (1 or −1) that `±` flips and
`applyTyped()` reads, reset by `reset()` on lookup, save and cancel; `step()` re-derives it when the
delta is non-zero so stepping negative then typing stays negative. Evidence above under step 5.

**2. Wired the camera restart through an event rather than a direct call.**
Step 4 says to expose `restartCameraIfWanted()` "that the page may call after a save". Alpine gives a
parent no handle on a child component's methods without a ref, and the plan's own architecture uses
window events for exactly this traffic, so `save()` dispatches `shop-scan-saved` and the component
listens for it. Behaviour is what the plan's Context asks for ("restart the camera after a save if it
was active before"): the camera comes back only after a successful save, not after every lookup, and
only when the user had opened it (`cameraWanted`).

**3. Split one planned test in two** (`home_tile_links_to_the_stock_scan_screen` and
`home_tile_is_hidden_without_the_stocking_permission`) so a regression names which half failed. Same
assertions as the plan describes.

Nothing else. No step was impossible, `resources/css/shop.css` was not edited, and the office page,
`StockingController` and `barcode-scanner.js` were not touched.

## Verification

**1.** `php artisan route:list --name=shop.`
```
  GET|HEAD       shop .............. shop.home › Shop\ShopHomeController@index
  GET|HEAD       shop/stock-scan shop.stock-scan › Shop\StockScanController@index
                                                            Showing [2] routes
```
`shop.stock-scan` carries `PermissionMiddleware:stocking.scan` (step 1).

**2.** `php artisan test --filter=Shop` → **92 passed (188 assertions)**, no failures.
(Cycle 1 and 2 Shop tests, `RoutePermissionsTest`, `RolePermissionGrantsTest`, the contract test now
listing two screens, and the new `ShopStockScanTest`.)

**3.** `php artisan test` → **17 failed, 492 passed (1808 assertions)**, 60.30s.
```
   FAILED  Tests\Unit\UdeaScrapingServiceTest        (7)
   FAILED  Tests\Feature\CashReconciliationTest      (3)
   FAILED  Tests\Feature\FruitVegLabelPrintingTest   (2)
   FAILED  Tests\Feature\ProductTest                 (2)
   FAILED  Tests\Feature\TestScraperControllerTest   (1)
   FAILED  Tests\Feature\WasteLogTest                (2)
```
The identical 17 named in Context. Passing count 483 → 492 (the 8 new tests plus the extra tile test).

**4.**
```
$ grep -rn "<script\|<style" resources/views/shop/
(no output)
$ grep -c "route(" resources/js/shop/*.js
resources/js/shop/scan-input.js:0
resources/js/shop/stock-scan.js:0
```

**5.** `git diff --stat resources/views/stocking/ app/Http/Controllers/StockingController.php resources/js/barcode-scanner.js resources/css/shop.css`
```
(empty)
```

**6.** `npm run build` → success. `ls public/build/assets | grep -ci barcode` → `1`.

**7.** `./vendor/bin/pint --test --dirty` → `PASS  5 files`.

**8. Manual — rendered through the real HTTP kernel, not a browser.**
No browser extension is connected to this session and I do not enter anyone's password, so the four
live dev users were signed in with `Auth::login()` and real requests pushed through the kernel.

Access:
```
employee   /shop/stock-scan -> 200
barista    /shop/stock-scan -> 403
manager    /shop/stock-scan -> 200
admin      /shop/stock-scan -> 200
```
The employee's rendered page, checked element by element:
```
  OK   body data-shell="shop"              OK   shop-split two columns
  OK   topbar Back link to /shop           OK   bignum
  OK   title "Stock scan"                  OK   stepper
  OK   scan input                          OK   numpad with 12 keys
  OK   camera + keyboard buttons (2)       OK   last scans list
  OK   data-lookup-url  → /stocking/lookup        OK   actions bar
  OK   data-update-url  → /stocking/update-stock  OK   toast region
  OK   shop.js loaded before app.js
sprite icons resolved: alert, back, backspace, camera, check, chevron-down, keyboard,
                       logout, minus, office, package, plus, scan
```

**Still needs a human on the real devices** — everything in the plan's Verification 8 that depends on
input hardware or a camera, which I cannot exercise here:
- Till PC with the USB scanner: scanning without touching the page, and above all that focus returns
  to the input after tapping the stepper, the number pad, Cancel and Save. The `refocus()` /
  `shop-scan-done` design is in place and unit-reasoned, but only a real keyboard-wedge scanner
  proves it.
- Tablet: that `inputmode="none"` really suppresses the on-screen keyboard, that the keyboard toggle
  restores it, and that the camera opens over HTTPS (or shows the HTTPS message over HTTP).
- Phone: single-column layout, key sizes, bottom actions bar.
- An unknown code showing the red error state, and the toast appearing on save.
- That `/stocking` still shares the same "Last scans" history.

## Files changed

This cycle:
```
 M config/shop.php                                       (tile → shop.stock-scan / stocking.scan)
 M resources/js/shop.js                                  (stub → module index)
 M routes/web.php                                        (+3, the new route)
 M tests/Feature/Shop/ShopHomeTest.php                   (1 line, fixture permission)
?? app/Http/Controllers/Shop/StockScanController.php
?? resources/js/shop/scan-input.js
?? resources/js/shop/stock-scan.js
?? resources/views/components/shop/scan-input.blade.php
?? resources/views/shop/stock-scan.blade.php
?? tests/Feature/Shop/ShopStockScanTest.php
```
Also in `git status`, not mine: `docs/planImp/{plan,implemented}.md` and the
`archive/2026-09-23-scraping-queue-cleanup/` move.

Nothing committed, pushed or deployed, per Constraints.

## Notes for Planner

1. **Upper bound on `new_stock` is unhandled by design, and degrades acceptably.** The endpoint
   validates `max:9999.99`; the number pad allows four digits, so on a product with stock 24 a typed
   `9999` asks for 10023 and the endpoint answers 422. `save()` reads `data.success` as falsy and shows
   `data.message`, which Laravel fills with "The new stock field must not be greater than 9999.99." So
   the user gets a sensible red toast rather than a silent failure. The plan specified only the ≥ 0
   clamp, so I did not add an upper clamp; say if you want one.

2. **History rows are now keyed by product, not appended.** Re-scanning a product moves its row to the
   top instead of adding a duplicate, which the design's "Last scans" list implies (one row per
   product, `is-latest` on the current one). The office page appends and can show the same product
   twice. Both read the same key safely — this is a display difference, not a data one — but it is a
   behaviour difference between the two pages worth knowing.

3. **`shop-scan-saved` is a third window event** alongside `shop-scan-done` / `shop-scan-error`. If
   cycle 4's Price check screen reuses `x-shop.scan-input`, these three are the contract; they may be
   worth writing down in the design doc as the component's API.

4. **`restartCameraIfWanted()` reopens the camera after a save.** On a tablet that means the viewfinder
   returns for the next item, which is right for counting a shelf, but it also means the camera stays
   powered between items. If staff find it draining, the fix is to drop the `shop-scan-saved` listener
   from the component.

5. **The number pad sets an absolute magnitude, not a running total.** Typing `1` then `2` gives 12,
   not 3, matching the design's "Or type amount" display. `±` applies to whatever is typed. Worth
   confirming with staff that this is what they expect, since the stepper is additive and the pad is
   not.

6. **Decimal stock is still floored**, as the plan says. A product at 2.5 units saved with +1 becomes 3.
   Unchanged from the office page, but now on a screen designed for repeated use, so it may surface
   more often for weighed goods.
