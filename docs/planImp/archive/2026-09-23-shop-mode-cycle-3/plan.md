# Shop mode cycle 3 — Stock scan (screen 02)

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-23

## Goal

Give shop-floor staff the first real task screen in Shop mode: scan a product, see its stock in very large digits, adjust it with a stepper or a number pad, save, and see the last few scans. It is a new page at `/shop/stock-scan` built from the approved design (`docs/design/shop-mode/screen-02-stock-scan.html`), reusing the existing stocking JSON endpoints unchanged. The Home tile "Stock scan" starts pointing at it. The existing office page at `/stocking` is not touched and keeps serving managers. This cycle also introduces the reusable scan-input component and the pattern for page behaviour living in `resources/js/shop/`, which every later screen builds on.

## Context

Baseline: HEAD `47ee616f` ("1st deploy of shop layout"), tree clean. Cycles 1 and 2 and the scraping cleanup are in that commit and archived under `docs/planImp/archive/`.

**Design.** Open `docs/design/shop-mode/screen-02-stock-scan.html` in a browser: top bar with Back + "Stock scan" + user chip; a two-column `shop-split` (one column under 1024px); left: the scan input (`shop-scan`) then a `shop-card` with label "Product", the product name as `shop-subtitle`, "code · category" as `shop-row__meta shop-code`, an "In stock" `shop-pill--ok`, the big number `shop-bignum` with `<small>units</small>`, then "Adjust by" with a `shop-stepper` (two `shop-iconbtn--lg`, an `<output class="shop-stepper__value is-plus">+6</output>`) and "New stock will be **30 units**"; right: a `shop-card` holding the `shop-numpad` (display "Or type amount" + value, keys 1–9, `±`, 0, backspace) and a "Last scans" `shop-list` of `shop-row`s (title, meta like "10:38 · set to 8", qty at right, `is-latest` on the current product); bottom: `shop-actions` with "Cancel" (`shop-btn--secondary shop-btn--lg`) and "Save · 30" (`shop-btn--primary shop-btn--lg`, check icon). All classes exist in `resources/css/shop.css`; states: `.shop-scan.is-focused/.is-camera/.is-error`, `.shop-stepper__value.is-plus/.is-minus`, `.shop-row.is-latest`, `.shop-toast--ok/--bad` inside `.shop-toasts`. Handoff rules are in `docs/design/shop-mode/README.md` (scan input behaviour section applies verbatim).

**What exists from cycle 1.** `App\View\Components\ShopLayout` (props `title`, `back`, `guestSafe`, `bare`) and `resources/views/layouts/shop.blade.php` (loads `resources/js/shop.js` before `app.js`; sets `is-touch` on `#shop-root` for coarse pointers; csrf meta tag present). Components `resources/views/components/shop/{icon,topbar,tile}.blade.php` (`<x-shop.icon name="scan" size="lg"/>` renders the sprite; ids include `scan`, `camera`, `keyboard`, `plus`, `minus`, `backspace`, `check`, `alert`, `x`). `resources/js/shop.js` is a stub with one `alpine:init` listener. `config/shop.php` tile `stock-scan` currently has `'route' => 'stocking.index', 'permissions' => ['products.view']`. `resources/views/shop/home.blade.php` is the only screen so far and shows the composition style. `tests/Feature/Shop/ShopViewContractTest.php` scans every file under `resources/views/shop/` and fails on `<style`, `<script`, or any class token matching `/^(bg-|text-|p-|px-|…|hidden|block|inline|dark:|sm:|md:|lg:|xl:|hover:|focus:)/` that does not start with `shop`. It reads every `class="…"` attribute including the content of Alpine `:class="…"` bindings, so bindings must only toggle `shop-*` or `is-*` names; use `x-show` rather than a `hidden` class.

**Endpoints to reuse, unchanged** (`app/Http/Controllers/StockingController.php`, both behind `permission:stocking.scan` since cycle 2):
- `POST /stocking/lookup` (`stocking.lookup`), body `{ barcode }` → `{ found: false, message }` or `{ found: true, product: { name, code, category }, stock: float }`.
- `POST /stocking/update-stock` (`stocking.update-stock`), body `{ barcode, new_stock }` (numeric, 0–9999.99) → `{ success: true, message, stock }` (also `old_stock` when changed), or `{ success: false, message }` with 404/500. It writes `STOCKCURRENT` on the POS connection and logs a `StockAdjustment` row with `user_id = auth()->id()` and `source = 'stocking'`.
- `Product::getCurrentStock()` returns a float; the office page shows `Math.floor(stock)`.

**Behaviour to carry over from the office page** (`resources/views/stocking/index.blade.php`, script at lines 138–405, Alpine `stockingScanner()`):
- GS1 prefix handling in `parseBarcode()`: strip leading `]C1`, `]d2`, `]e0`; replace group separators with `|`; if `01(\d{14})` present use that GTIN-14.
- Camera: `window.BarcodeScanner.startScanner(elementId, onDetected, onError)` / `stopScanner()` / `isRunning()` from `resources/js/barcode-scanner.js` (an ES module wrapping `html5-qrcode`; live camera needs HTTPS). On detect: short beep via `AudioContext`, `navigator.vibrate(100)`, ignore the same code within 2 s, stop the camera, look the code up. Restart the camera after a save if it was active before.
- Scan history in `localStorage` under `stockingScanHistory`: array of `{ name, code, stock, time }`, newest first, max 10; stock updated in place after a save. Keep the same key so history carries over between the office page and the shop page.
- After every lookup and save, refocus the barcode input.
- The office page also has "Add to Labels" (`labels.scan`). The design does not; the Price check screen (cycle 4) carries "Print shelf label". Not included here.

**Vite.** `vite.config.js` `build.rollupOptions.output.format = 'es'` (comment: needed for dynamic import). `resources/js/barcode-scanner.js` is its own entry used by office pages; do not add it to the shop layout. Load it on demand with a dynamic `import('../barcode-scanner')` so Home does not pay for `html5-qrcode`.

**Tests.** `RefreshDatabase` + sqlite in-memory for `default` and `pos`. Role users: `User::factory()->withRole('employee')` (cycle 2 factory state; creates the role but no permissions) plus `Permission::firstOrCreate` + `$role->givePermissionTo()` as in `tests/Feature/Shop/ShopHomeTest.php::userWith()`. POS tables for the lookup endpoint: create in-memory `PRODUCTS` (`ID`, `NAME`, `CODE`, `CATEGORY`, `PRICESELL`, `TAXCAT`), `STOCKCURRENT` (`PRODUCT`, `UNITS`, `LOCATION`, `ATTRIBUTESETINSTANCE_ID`), `CATEGORIES` (`ID`, `NAME`) following the pattern in `tests/Feature/CustomerRequestTest.php:25-58` (`Config::set('database.connections.pos', [...sqlite :memory:...])`, `DB::purge('pos')`, schema builder). `stock_adjustments` lives on the default connection (migration `2026_01_22_105722`). Full-suite baseline: 17 failed / 483 passed, always the same 17 (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `WasteLogTest` ×2, `ProductTest` ×2, `FruitVegLabelPrintingTest` ×2, `TestScraperControllerTest` ×1).

## Constraints

- Do not commit, push or deploy.
- Design/content contract (enforced by `ShopViewContractTest`): `resources/views/shop/**` holds only `x-shop.*` components, `shop-*` classes, Blade control flow, Alpine directives and data. No `<style>`, no `<script>`, no utility classes anywhere in a view, including inside `:class` bindings. All behaviour lives in `resources/js/shop/*.js`, registered from `resources/js/shop.js` inside `alpine:init`. URLs the JavaScript needs are passed as `data-*` attributes on the page root, never interpolated into JS.
- `resources/css/shop.css` is not edited. If a needed style is missing, add a class-free wrapper element instead (the camera mount point below is the one such case).
- `StockingController`, its routes, `resources/views/stocking/**` and `resources/js/barcode-scanner.js` are not modified.
- Markup and copy follow the design screen; do not add controls the design does not show (no "Add to Labels" here).
- Numbers: the endpoint accepts decimals, but this screen works in whole units like the office page does (`Math.floor` on display, integer adjustments). New stock can never go below 0.

## Out of scope

- Price check (cycle 4), every other screen, PIN switch.
- Photo-capture fallback for HTTP (`scanFile`); the office page has none either. On camera failure the component shows the error text in `.shop-scan__msg`.
- A generic toast component; this screen renders its own toast markup from the design. Promote to `x-shop.toast` when a second screen needs it.
- Decimal stock adjustments (weighed goods).
- Removing or restyling `/stocking`.
- Touching `RetrieveIndependentBarcodeJob` or any other leftover.

## Steps

### 1. Route and controller
Files: `app/Http/Controllers/Shop/StockScanController.php (new)`, `routes/web.php`
What: `StockScanController@index` returns `view('shop.stock-scan')` with nothing else. Inside the existing `Route::prefix('shop')->name('shop.')` group add `Route::get('/stock-scan', [\App\Http\Controllers\Shop\StockScanController::class, 'index'])->middleware('permission:stocking.scan')->name('stock-scan');`.
Check: `php artisan route:list --name=shop.stock-scan` shows `GET shop/stock-scan` with `permission:stocking.scan` (the middleware column shows the class name `PermissionMiddleware:stocking.scan`).

### 2. Home tile points at the new page
Files: `config/shop.php`
What: the `stock-scan` tile → `'route' => 'shop.stock-scan'`, `'permissions' => ['stocking.scan']`. Nothing else in the file changes.
Check: `php artisan test --filter=ShopHomeTest` green (its employee fixture grants `products.view`; give that test user `stocking.scan` as well if "Stock scan" stops appearing, and say so in `implemented.md`).

### 3. JavaScript layout: `shop.js` becomes an index
Files: `resources/js/shop.js`, `resources/js/shop/scan-input.js (new)`, `resources/js/shop/stock-scan.js (new)`
What: `shop.js` imports the two modules and registers them: 
```js
import scanInput from './shop/scan-input';
import stockScan from './shop/stock-scan';
document.addEventListener('alpine:init', () => {
    Alpine.data('shopScanInput', scanInput);
    Alpine.data('shopStockScan', stockScan);
});
```
Each module exports a function returning an Alpine data object. Keep `alpine:init` as the only registration point (the cycle 1 comment explains why).
Check: `npm run build` succeeds; `public/build/manifest.json` still lists `resources/js/shop.js`; after step 4, a chunk for `barcode-scanner` appears in `public/build/assets/` only when the dynamic import is present (`ls public/build/assets | grep -i barcode`).

### 4. The scan-input component
Files: `resources/views/components/shop/scan-input.blade.php (new)`, `resources/js/shop/scan-input.js`
What, Blade: props `placeholder = 'Scan or type a barcode'`, `hint = 'Ready — scanner listening'`, `camera = true`. Markup from the design's `.shop-scan` block:
```blade
<div class="shop-scan" x-data="shopScanInput()" :class="{ 'is-camera': cameraOpen, 'is-error': error !== null }"
     @shop-scan-done.window="done()" @shop-scan-error.window="fail($event.detail)">
    <label class="shop-scan__field">
        <x-shop.icon name="scan" size="lg" />
        <span class="shop-sr-only">Barcode</span>
        <input class="shop-scan__input" x-ref="input" x-model="value" :inputmode="inputMode" autocomplete="off" enterkeyhint="go"
               placeholder="{{ $placeholder }}" @keydown.enter.prevent="submit()" @focusout="refocus($event)">
    </label>
    @if ($camera)
    <div class="shop-scan__tools">
        <button class="shop-iconbtn shop-touch-only" type="button" :aria-pressed="cameraOpen" aria-label="Camera" @click="toggleCamera()"><x-shop.icon name="camera" /></button>
        <button class="shop-iconbtn shop-touch-only" type="button" :aria-pressed="keyboard" aria-label="Show keyboard" @click="toggleKeyboard()"><x-shop.icon name="keyboard" /></button>
    </div>
    @endif
    <p class="shop-scan__hint">{{ $hint }}</p>
    <p class="shop-scan__msg"><x-shop.icon name="alert" size="sm" /><span x-text="error"></span></p>
    <div class="shop-scan__camera"><div :id="cameraId"></div><div class="shop-scan__reticle"></div><span class="shop-scan__camlabel">Point at the barcode</span></div>
</div>
```
(The inner `<div :id="cameraId">` is the mount point for `html5-qrcode`; it has no class and needs no CSS because `.shop-scan__camera video` already positions the video.)
What, `scan-input.js` (data object): state `value`, `keyboard = false`, `cameraOpen = false`, `error = null`, `cameraId` (unique, e.g. `'shop-camera-' + Math.random().toString(36).slice(2)`), `lastCode`, `lastAt`. `init()`: focus the input; `inputMode` getter: `'text'` when the keyboard is on or the root is not touch (`!document.getElementById('shop-root')?.classList.contains('is-touch')`), else `'none'`. `submit()`: parse with the GS1 rules copied from the office page, ignore empty, `this.error = null`, dispatch `this.$dispatch('scan', { code })` from the root element, clear `value`. `refocus(e)`: if `e.relatedTarget` is an `input`, `textarea`, `select` or `button` do nothing, else `requestAnimationFrame(() => this.$refs.input.focus())` (the USB scanner on the till PC must always find the input focused; buttons are excluded so taps on the numpad still work because the page re-focuses after each action via `done()`). `done()`: clear `error`, focus. `fail(message)`: set `error`, focus. `toggleKeyboard()`: flip `keyboard`, focus. `toggleCamera()`: if open → `stop()`; else `cameraOpen = true`, `const m = await import('../barcode-scanner')`, `await m.startScanner(this.cameraId, (text) => this.detected(text), () => {})`, on failure `fail('Camera could not start. Live scanning needs HTTPS.')` and `cameraOpen = false`. `detected(text)`: beep + vibrate as the office page does, 2 s duplicate guard, `stop()`, `value = text`, `submit()`. `stop()`: `import('../barcode-scanner').then(m => m.isRunning() && m.stopScanner())`, `cameraOpen = false`. Expose `restartCameraIfWanted()` that the page may call after a save (remember `cameraWanted` when the user opened it).
Check: `php artisan view:cache` succeeds; `grep -c "shop-" resources/views/components/shop/scan-input.blade.php` > 0; in the built bundle `grep -l "shop-camera-" public/build/assets/shop-*.js` finds the chunk.

### 5. Page behaviour: `stock-scan.js`
Files: `resources/js/shop/stock-scan.js`
What: `Alpine.data('shopStockScan')` reading `this.$root.dataset.lookupUrl`, `updateUrl` and the csrf token from `document.querySelector('meta[name="csrf-token"]').content`. State: `product = null` (`{ name, code, category }`), `stock = 0` (integer), `delta = 0`, `typed = ''` (numpad buffer), `busy = false`, `history` (from `localStorage.stockingScanHistory`, same shape as the office page, newest first, max 10), `toast = null` (`{ tone: 'ok'|'bad', text }`), `cameraWanted = false`. Getters: `newStock = Math.max(0, stock + delta)`, `deltaLabel` (`'+6'`, `'−2'` using a real minus sign, `'0'`), `deltaTone` (`'is-plus'`/`'is-minus'`/`''`). Methods:
- `lookup(code)`: `busy = true`; POST JSON to `lookupUrl` with `X-CSRF-TOKEN`; on `found` set `product`, `stock = Math.floor(data.stock)`, `delta = 0`, `typed = ''`, push/refresh history `{ name, code, stock, time: ISO, note: 'scanned' }`, `$dispatch('shop-scan-done')` on `window`; on not found: `product = null`, dispatch `shop-scan-error` with `No product for ${code}`; on network error dispatch `shop-scan-error` with "Lookup failed"; finally `busy = false`.
- `step(n)`: `delta += n`, clamp so `newStock >= 0`, `typed = ''`.
- `key(k)`: digits append to `typed` (max 4 digits) and set `delta = sign * Number(typed)`; `'sign'` flips the sign of `delta` (and of `typed` interpretation); `'backspace'` drops the last digit; clamp as in `step`.
- `cancel()`: `delta = 0`, `typed = ''`, focus scan input via `window.dispatchEvent(new CustomEvent('shop-scan-done'))`.
- `save()`: if no product or `delta === 0` return; `busy = true`; POST `{ barcode: product.code, new_stock: newStock }` to `updateUrl`; on success `stock = Math.floor(data.stock)`, `delta = 0`, `typed = ''`, update the history row (`stock`, `note: 'set to N'`, time), `showToast('ok', 'Stock updated')`, dispatch `shop-scan-done`; on failure `showToast('bad', data.message || 'Could not save')`; finally `busy = false`.
- `showToast(tone, text)`: set and clear after 3 s.
- `timeOf(iso)`: `HH:MM` for the row meta.
History rows render meta as `${timeOf(time)} · ${note}`; the current product's row gets `is-latest` and note "editing" while `delta !== 0`.
Check: `node -e "import('./resources/js/shop/stock-scan.js')"` is not meaningful (Alpine globals); rely on the build and the manual check. `npm run build` succeeds with no warnings about the module.

### 6. The screen
Files: `resources/views/shop/stock-scan.blade.php (new)`
What: `<x-shop-layout title="Stock scan" :back="route('shop.home')">` → `<main class="shop-page" x-data="shopStockScan()" data-lookup-url="{{ route('stocking.lookup') }}" data-update-url="{{ route('stocking.update-stock') }}" @scan="lookup($event.detail.code)">`. Compose exactly the design's structure:
- `<div class="shop-split">` with left `<div class="shop-stack">`: `<x-shop.scan-input />`; then a `<section class="shop-card" x-show="product" x-cloak>` holding the product header (`shop-between` → `shop-stack shop-stack--tight` with `shop-label` "Product", `<h2 class="shop-subtitle" x-text="product?.name">`, `<span class="shop-row__meta shop-code" x-text="product ? product.code + ' · ' + product.category : ''">`; and `<span class="shop-pill" :class="stock > 0 ? 'shop-pill--ok' : 'shop-pill--bad'" x-text="stock > 0 ? 'In stock' : 'Out of stock'">`), the big number (`<span class="shop-bignum"><span x-text="stock"></span><small>units</small></span>`), and the "Adjust by" stepper (`shop-stepper`: `<button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="Minus one" @click="step(-1)"><x-shop.icon name="minus" size="lg"/></button>`, `<output class="shop-stepper__value" :class="deltaTone" x-text="deltaLabel">`, plus button) and `<p class="shop-meta">New stock will be <strong x-text="newStock + ' units'"></strong></p>`. When `product` is null show instead `<section class="shop-empty">` with the `package` icon (xl), title "Scan a product", text "Point the scanner at a barcode, or type it and press Enter."
- Right `<div class="shop-stack">`: `<section class="shop-card"><div class="shop-numpad">` with the display (`shop-label` "Or type amount", `<span x-text="typed || '0'">`), nine digit `<button class="shop-key" type="button" @click="key('5')">`, then `<button class="shop-key shop-key--fn" @click="key('sign')">±</button>`, `0`, and the backspace fn key with `<x-shop.icon name="backspace" size="lg"/>` and `aria-label="Delete"`; then the "Last scans" section (`shop-label` heading, `<div class="shop-list">` with `<template x-for="row in history" :key="row.code">` → `<div class="shop-row" :class="{ 'is-latest': product && row.code === product.code }">` … title, meta, `shop-row__aside` qty). Show a `shop-empty` variant ("No scans yet") when history is empty.
- Bottom: `<div class="shop-actions"><button class="shop-btn shop-btn--secondary shop-btn--lg" type="button" @click="cancel()" :disabled="delta === 0">Cancel</button><button class="shop-btn shop-btn--primary shop-btn--lg" type="button" @click="save()" :disabled="!product || delta === 0 || busy"><x-shop.icon name="check"/><span x-text="'Save · ' + newStock"></span></button></div>`.
- Toast: `<div class="shop-toasts" role="status" x-show="toast" x-cloak><div class="shop-toast" :class="toast && 'shop-toast--' + toast.tone"><span class="shop-toast__icon"><x-shop.icon name="check" size="sm"/></span><span class="shop-toast__text" x-text="toast?.text"></span></div></div>`.
Check: `php artisan test --filter=ShopViewContractTest` green (the new file is picked up automatically; the test must now report two screens). `php artisan view:cache` succeeds.

### 7. Tests
Files: `tests/Feature/Shop/ShopStockScanTest.php (new)`
What (`RefreshDatabase`): helper building an employee with `stocking.scan` as in `ShopHomeTest::userWith()`; helper creating the in-memory POS tables listed in Context and inserting one product `CODE 5000000000017`, `NAME 'Oat drink, barista 1 L'`, `CATEGORY 'c1'` with `CATEGORIES` row `('c1','Dairy alternatives')` and `STOCKCURRENT` `UNITS 24`.
- `employee_can_open_the_stock_scan_screen`: GET `/shop/stock-scan` → 200, contains `data-shell="shop"`, `shop-scan__input`, `data-lookup-url="` + `route('stocking.lookup')`, and a Back link to `route('shop.home')`.
- `barista_is_forbidden`: barista with `kds.access` only → 403.
- `guest_is_sent_to_login`: → redirect `/login`.
- `home_tile_links_to_the_stock_scan_screen`: employee with `stocking.scan` sees `href="` + `route('shop.stock-scan')` on `/shop`, and an employee with only `products.view` does **not** see "Stock scan".
- `lookup_returns_the_product_and_whole_stock` (POS stub): POST `stocking.lookup` `{ barcode: '5000000000017' }` → `found true`, `product.name`, `product.category 'Dairy alternatives'`, `stock 24`.
- `lookup_reports_an_unknown_barcode`: → `found false`.
- `update_stock_writes_the_pos_row_and_logs_the_adjustment`: POST `stocking.update-stock` `{ barcode, new_stock: 30 }` → `success true`, `stock 30`; `STOCKCURRENT.UNITS` is 30; `stock_adjustments` has a row with `old_stock 24`, `new_stock 30`, `adjustment 6`, `user_id` = the employee, `source 'stocking'`.
These last three cover the endpoints the screen depends on, which had no tests before.
Check: `php artisan test --filter="ShopStockScanTest|ShopHomeTest|ShopViewContractTest"` green.

### 8. Build and format
Files: all touched
What: `npm run build`; `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean; `public/build/manifest.json` lists `resources/js/shop.js`.

## Verification

1. `php artisan route:list --name=shop.` → `shop.home`, `shop.stock-scan`; the latter carries the `stocking.scan` permission middleware.
2. `php artisan test --filter=Shop` → all green (cycle 1 and 2 Shop tests plus the new class; the contract test lists two screens).
3. `php artisan test` → 17 failed / N passed, the identical 17 named in Context, nothing new.
4. `grep -rn "<script\|<style" resources/views/shop/` → no output. `grep -c "route(" resources/js/shop/*.js` → 0 (no Blade in JS).
5. `git diff --stat resources/views/stocking/ app/Http/Controllers/StockingController.php resources/js/barcode-scanner.js resources/css/shop.css` → empty.
6. `npm run build` → success; `ls public/build/assets | grep -ci barcode` ≥ 1 (the on-demand chunk).
7. `./vendor/bin/pint --test --dirty` → clean.
8. Manual, signed in as an employee, on the real devices:
   - Till PC with the USB scanner: open `/shop/stock-scan`, scan a product without touching the page: the product card fills, the big number shows stock, the input keeps focus; press `+` three times, "Save · N" reflects it; click Save: toast "Stock updated", the number updates, the "Last scans" row shows "set to N"; scan again straight away (focus is back on the input).
   - Tablet: the camera and keyboard buttons are visible; the on-screen keyboard does not open when the input is focused; tapping the keyboard button opens it; the camera button opens the viewfinder over HTTPS (or shows the HTTPS message over HTTP); typing on the number pad sets the adjustment; `±` flips it; Cancel clears it.
   - Phone: single-column layout, number pad keys at least 56px, actions bar full-width at the bottom.
   - Scanning an unknown code shows the red error state with "No product for …" and clears when the next code is scanned.
   - The office page `/stocking` still works as before and shares the same "Last scans" history.

## Risks

- **Contract test vs Alpine bindings.** It reads `:class` contents too. Only toggle `shop-*`/`is-*` names; never `hidden`, `block`, `flex`. Use `x-show` and `x-cloak` (the layout already has the `[x-cloak]` rule).
- **Focus on the till PC.** A keyboard-wedge scanner types into whatever has focus. The number pad and stepper are buttons, and the component's `refocus()` ignores focus moving to buttons, so after each tap the page must call back to the input: `step()`, `key()`, `cancel()` and `save()` all end by dispatching `shop-scan-done`, which the component handles by focusing. Test this with a real scanner; if focus is ever lost, the fix belongs in `refocus()`, not in the view.
- **`html5-qrcode` mount point.** It replaces the children of the element it is given, so the mount element must be the empty inner `<div :id="cameraId">`, not `.shop-scan__camera` itself, or the reticle and label disappear.
- **Dynamic import path.** From `resources/js/shop/scan-input.js` the module is `../barcode-scanner`. Vite must emit it as a separate chunk; if the build inlines it into `shop.js` instead, Home gets heavier but nothing breaks.
- **HTTPS.** Live camera scanning needs a secure origin; staff phones on the LAN over HTTP will see the error message. Same as the office page today.
- **History shape.** Adding `note` to the localStorage rows is additive; the office page ignores unknown keys. Rows written by the office page lack `note`; render meta as time only when it is missing.
- **Decimal stock.** Weighed items can have fractional stock; the screen floors for display and saves whole numbers, as the office page does. If a product's stock is 2.5 and the user saves +1, it becomes 3, not 3.5. Same behaviour as today; noted, not changed.

## Review

Reviewed 2026-09-23 by the Planner against `implemented.md`, every new file, the small diffs, a rerun of the tests, and screenshots of the screen rendered as an employee.

Criteria:
1. Route and controller — PASS. `shop.stock-scan` behind `stocking.scan`; the controller only returns the view.
2. Home tile — PASS. Route and permission switched; the one-line fixture change in `ShopHomeTest` is the option the plan offered.
3. JS index — PASS. `shop.js` imports two modules and registers both inside the single `alpine:init` listener.
4. Scan-input component — PASS. Design markup, class-free mount point for the camera, GS1 parsing, refocus rules, on-demand `import('../barcode-scanner')` (built as a separate 335 kB chunk; `shop.js` is 6.75 kB and contains no `html5-qrcode`).
5. Page behaviour — PASS. URLs from data attributes, csrf from the meta tag, history shared under the office key with one row per product, clamp at zero, explicit `sign` state.
6. Screen — PASS. Composed only from `shop-*` classes and `x-shop.*` components; contract test now scans two screens.
7. Tests — PASS. 8 tests including the first coverage of both stocking endpoints (POS row written, audit row with the employee's id).
8. Build and format — PASS.

Verification rerun by the Planner: `--filter=Shop` 92 passed; full suite 17 failed / 492 passed, the same 17; formatter clean; `StockingController`, `resources/views/stocking/`, `barcode-scanner.js` and `shop.css` untouched. Screenshots at 1280 px and 390 px: hero scan input with the listening dot, empty state, number pad, "Last scans", sticky action bar; single column on the phone. Hardware behaviour (USB scanner focus, on-screen keyboard suppression, camera) still needs a hand on the real devices, as the report says.

Deviations 1–3: accepted. Deviation 1 (explicit sign state, because `-0 < 0` is false) fixed a bug the plan's wording would have caused; the node exercise that caught it is the right kind of check. Deviation 2 (camera restart via a window event) fits the component's event API better than the direct call the plan suggested.

Notes for Planner: 1 (no upper clamp; the endpoint's 422 message becomes a red toast) accepted. 2 (one history row per product) accepted as a deliberate display difference from the office page. 3 (three window events are the component's API: `shop-scan-done`, `shop-scan-error`, `shop-scan-saved`) noted; the next screen that reuses the component records them in `docs/design/shop-mode/README.md`. 4 (camera reopens after save) accepted; watch battery on the tablet. 5 (number pad is an absolute magnitude) matches the design's "Or type amount". 6 (floor of decimal stock) unchanged from the office page.

Result: ACCEPTED. Archived by the Planner to `docs/planImp/archive/2026-09-23-shop-mode-cycle-3/`. Next cycle: Find product (search and stock check), which absorbs the design's Price check screen as its detail view.
