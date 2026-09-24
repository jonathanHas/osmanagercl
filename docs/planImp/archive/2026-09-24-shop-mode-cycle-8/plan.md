# Shop mode cycle 8 — Receive delivery: session list and scan screen (screens 04, 05)

Status: ACCEPTED
Revision: 2
Planner: Fable 5.1
Date: 2026-09-24

## Goal

Give staff the delivery-receiving loop in Shop mode, built on the legacy scan sessions they use today: a list of open deliveries, a "start a delivery" control, and a scan screen where each scan adds a unit (or a case) to the running count, every invoice line shows expected versus scanned with a status pill, and a mis-scan can be corrected in place. The office match page keeps everything else (financials, case units, outer barcodes, translated labels, deviation reports, return sheets, undo, merges). Summary and completion (screen 06) follow in cycle 9; until then staff finish a delivery on the office page as they do now.

## Context

Baseline: cycle 7b accepted and archived (`docs/planImp/archive/2026-09-24-shop-mode-cycle-7b/`); cycles 3–7b remain uncommitted (the owner has been asked to commit). Record `git status --short` at the start. `resources/js/shop/` is still untracked, so prove JS changes with `git status --short` plus grep, not `git diff --stat`.

**Owner facts.** Employees receive deliveries through the legacy flow only (`delivery-legacy.*`); the newer `deliveries.*` system is manager-only (cycle 2). The Shop home tile "Receive delivery" currently links to `delivery-legacy.index` (the office session list).

**The legacy flow** (`app/Http/Controllers/DeliveryLegacyController.php`, 1,800 lines, all routes under `delivery-legacy` behind `permission:deliveries.process`, which employees hold):
- Sessions live in the POS table `deliveriesScan` (`ID` string uuid, `supID`, `dateUpload`, `status` 0 open / 1 completed). Scans live in `deliveriesScanItems` (`ID`, `delID`, `barcode`, `quantity`, `dateScan`), one row per barcode per session holding the running total (the increment endpoint deletes and re-creates the row). `App\Models\DeliveryScanItem` is that table on the `pos` connection and fills `dateScan` on create.
- The invoice lines come from the POS scratch table `delivery` (`supCode`, `prodName`, `myOrder`, `caseUnits`, `cost`, `orderNumber`), which the legacy sync truncates and repopulates per delivery; it is not keyed by session. Expected units per line = `caseUnits × myOrder`, except when `myOrder` has a fraction, in which case `myOrder` itself (weighed goods); this exact formula is in `match.blade.php` lines 775–806 and in `incrementScanQuantity()` around line 1300.
- `index()` (line 42) loads suppliers, the last 100 sessions joined to `suppliers.Supplier`, per-session item counts, and pending supplier ids. `match()` (line 99) runs three private queries: `getMatchedItems($deliveryId, $supplierId)` (invoice lines joined to `supplier_link` → `PRODUCTS`, with `scanned` = SUM of scan quantities, fields `prodName`, `supCode`, `Barcode`, `myOrder`, `invoiceCaseUnits`, `CaseUnits`, `scanned`, `productID`, `dbProductName`, `categoryName`, `PRICESELL`, `cost`, `orderNumber`), `getScannedNotOnInvoice()` (`Barcode`, `scanned`, `NAME`, `categoryName`, `productID`, `SupplierCode`) and `getOnInvoiceNotScanned()` (`supCode`, `prodName`, `myOrder`, `caseUnits`, `Barcode`, `dbProductName`, `productID`). The view classifies matched items as pending (`scanned === null && myOrder > 0`), verified (`scanned == expected`), critical (`scanned != expected`), OOS (`myOrder == 0`).
- `POST delivery-legacy.scan-increment` (`incrementScanQuantity`, line 1205): body `{ delID, barcode, quantity?, supplierID }`. Resolves the barcode as a unit code or, failing that, as a supplier outer/case code (`supplier_link.OuterCode`, multiplying by `CaseUnits`); adds `quantity ?? 1` × case units to the running total (quantity `0` is a pure lookup that records nothing); returns `{ success, product: { name, barcode (resolved unit barcode), supplierCode, categoryName, currentStock } | null, expectedQty | null, newQuantity, matchStatus: 'verified'|'partial'|'over'|'extra'|'unknown', scanType: 'unit'|'case', caseUnits, customerRequests: [{ id, request_id, customer_name, customer_phone, quantity, wanted_on, status, status_label }] }`. Unknown barcodes still record a scan (they appear as "not on invoice").
- `POST delivery-legacy.update-quantity` (`updateScannedQuantity`, line 1157): `{ delID, barcode, quantity (absolute, ≥0), supplierID }` → `{ success, quantity, financials }`.
- `POST delivery-legacy.create-session` (line 1715): `{ supplierID }` → inserts a session and redirects to the office match page. `POST delivery-legacy.complete` (line 1455) increments `STOCKCURRENT` and marks the session complete, redirecting to the office match page (cycle 9).
- Office scan UX (`match.blade.php`, Alpine `deliveryMatch()`, from line 3020): scan → quantity-0 lookup → a quantity prompt → submit. The Shop version is one step: each scan adds one unit (or one case when an outer code is scanned), and quantities are corrected with a stepper.

**Shop mode pieces to reuse.** `ShopLayout`, `x-shop.icon` (ids include `truck`, `scan`, `check`, `alert`, `sort`, `list-checks`, `plus`, `minus`, `chevron-right`, `inbox`), `x-shop.scan-input` (emits `scan {code}`; listens for `shop-scan-done`, `shop-scan-error`, `shop-scan-saved`; see `docs/design/shop-mode/README.md`), `resources/js/shop.js` registration inside `alpine:init`, data-attribute URLs, the `APP ADDITIONS` section of `resources/css/shop.css` (design block byte-identical), `ShopViewContractTest` rules (no `<script>`/`<style>`/utilities, only `shop-*`/`is-*` in `:class`), the Blade-directive trap (`x-on:error`, never `@error`), and a rendering feature test as the check for markup.

**Design.** `docs/design/shop-mode/screen-04-deliveries.html`: `shop-page--narrow`, a `shop-between` header ("Open deliveries", "N open"), a `shop-list` of `shop-row` links each with `shop-row__lead` (truck icon), title (supplier), meta (reference · time), a `shop-progress` bar with meta text, an aside `shop-pill` (`--sage` In progress, `--muted` Not started, `--ok` Checked), and a chevron. `screen-05-delivery-scan.html`: title "Supplier · reference"; `shop-split` with the scan input and a progress card (`shop-progress-meta` "18 of 42 items · 3 issues", `shop-progress--lg`) on the left, and an "Items" section on the right with a `shop-seg` sort ("New first" / "Scanned first") and a `shop-list` whose rows show meta (code) above the title, `shop-row__qty` "12 / 12" with a `<small>`, and a pill (`--ok` OK, `--warn` Short, `--muted` Extra / Not scanned, `--bad` Unexpected); `is-latest` on the just-scanned row; a `shop-actions` bar. Screen 15's `shop-qty` (−, value, +) is the pattern for in-row quantity correction.

**Tests.** `tests/Feature/CustomerRequestDeliveryFlagTest.php` lines 25–110 build every POS table the legacy queries touch (`PRODUCTS`, `CATEGORIES`, `TAXES`, `STOCKCURRENT`, `suppliers`, `supplier_link`, `delivery`, `deliveriesScan`, `deliveriesScanItems`) and seed one product; copy that setup into a trait `tests/Concerns/CreatesLegacyDeliveryPosTables.php` for the new test (leave the existing test as it is; the duplication is noted). Role users via `User::factory()->withRole()` plus `Permission::firstOrCreate` / `givePermissionTo`. Full-suite baseline: 17 failed / 504 passed, always the same 17 (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `WasteLogTest` ×2, `ProductTest` ×2, `FruitVegLabelPrintingTest` ×2, `TestScraperControllerTest` ×1).

## Constraints

- Do not commit, push or deploy.
- Reuse the legacy endpoints; do not change their behaviour. The only edits to `DeliveryLegacyController` are: one new JSON action (`items`) that calls the three existing private query methods, and an optional `return` parameter on `createSession()` so the Shop form lands on the Shop scan page. `match()`, `incrementScanQuantity()`, `updateScannedQuantity()` and the office views are not modified.
- New routes carry `permission:deliveries.process`.
- Design/content contract as in cycles 3–7. Stylesheet: `APP ADDITIONS` only.
- Copy in the row pills: OK, Short, Over, Unexpected, Not scanned (colour plus word).
- The scan screen must work with a USB scanner on the till and with the camera on the tablet, exactly like Stock scan (reuse `x-shop.scan-input`).

## Out of scope

- Summary and completion (screen 06), the `return` handling on `complete`, undo (cycle 9).
- Financial figures, cost/margin warnings, case-unit editing, outer-barcode assignment, translated label printing, deviation report, goods-return sheet, merging sessions, changing supplier, image resolution. All stay on the office match page.
- The "Mark put aside" action for customer requests: the Shop scan shows the flag only; the action follows when the Customer requests screen is built.
- The quantity prompt of the office scanner: Shop scans add one unit (or one case) per scan; corrections use the row stepper.
- Progress bars on the list rows: the list shows scan counts and status only (expected counts would need the invoice queries per session).

## Steps

### 1. JSON: classified items for a session
Files: `app/Http/Controllers/DeliveryLegacyController.php`, `routes/web.php`
What: add `public function items(Request $request)` validating `delID` and `supplierID` (both required strings), then: `$matched = $this->getMatchedItems($delID, $supplierID)`, `$extra = $this->getScannedNotOnInvoice(...)`, `$missing = $this->getOnInvoiceNotScanned(...)` (the third list is a subset of the first with `scanned === null`; use the first as the source of truth and ignore the third). Build rows:
- For each matched item with `myOrder > 0`: `expected = fmod(myOrder, 1) != 0 ? round(myOrder, 3) : (invoiceCaseUnits ?? 1) * myOrder`; `scanned = $item->scanned === null ? null : (float) $item->scanned`; `status` = `not_scanned` when `scanned === null`, else `ok` when `scanned == expected`, `short` when `<`, `over` when `>`. Row: `{ barcode: Barcode, code: supCode, name: dbProductName ?? prodName, expected, scanned, status }`. Items with `myOrder == 0` are skipped (out of stock at the supplier; the office page lists them separately).
- For each extra item: `{ barcode: Barcode, code: SupplierCode, name: NAME ?? barcode, expected: null, scanned: (float) scanned, status: 'unexpected' }`.
- `progress`: `total` = matched rows count, `checked` = rows with `scanned !== null`, `issues` = rows with status `short`, `over` or `unexpected`.
- Session header: `session = { id, supplier: suppliers.Supplier, date: dateUpload, completed: status == 1 }` from `deliveriesScan` joined to `suppliers`.
Return `response()->json(compact('session', 'rows', 'progress'))`. Route inside the `delivery-legacy` prefix group: `Route::get('/items', [DeliveryLegacyController::class, 'items'])->name('items');` (the group already carries `permission:deliveries.process`).
Check: `php artisan route:list --name=delivery-legacy.items` → `GET delivery-legacy/items` with the `deliveries.process` permission middleware; the feature test in step 7 asserts the classification.

### 2. Start-a-delivery lands in Shop mode
Files: `app/Http/Controllers/DeliveryLegacyController.php`
What: in `createSession()`, after inserting the session, if `$request->input('return') === 'shop'` redirect to `route('shop.deliveries.scan', ['delID' => $sessionId, 'supplierID' => $supplierId])` (route from step 3) instead of the office match page; keep the flash message. Nothing else changes; the office form does not send `return` so its behaviour is identical.
Check: covered by the feature test in step 7 (`POST delivery-legacy.create-session` with `return=shop` redirects to the Shop scan URL; without it, to the office match URL).

### 3. Routes and controller
Files: `app/Http/Controllers/Shop/DeliveryController.php (new)`, `routes/web.php`
What: in the `shop` prefix group, a sub-group `Route::middleware('permission:deliveries.process')->group(...)` with `GET /deliveries` → `index` (name `deliveries`) and `GET /deliveries/scan` → `scan` (name `deliveries.scan`, query `delID`, `supplierID`).
`index()`: mirror `DeliveryLegacyController::index()`'s data without the synced-delivery block: suppliers (`suppliers` ordered by name), the last 50 sessions joined to `suppliers`, per-session `COUNT(*)` and `SUM(quantity)` from `deliveriesScanItems`; split into `open` (status 0) and `completed` (status 1, last 10 only). Return `view('shop.deliveries', compact('suppliers', 'open', 'completed', 'counts'))`.
`scan(Request $request)`: validate `delID` and `supplierID` present; load the session joined to `suppliers`; 404 if missing; return `view('shop.delivery-scan', compact('session'))` where `session` has `id`, `supplierId`, `supplier`, `date`, `completed`.
Check: `php artisan route:list --name=shop.deliveries` lists both routes with `deliveries.process`.

### 4. Home tile
Files: `config/shop.php`
What: the `deliveries` tile → `'route' => 'shop.deliveries'`, hint "Scan a delivery in" unchanged, `'badge' => 'deliveries'`; in `ShopHomeController::badgeCount()` add `'deliveries' => DB::connection('pos')->table('deliveriesScan')->where('status', 0)->count()` (open sessions), wrapped in a `try/catch` returning `null` if the POS connection is unavailable, so Home never 500s because of a badge.
Check: `php artisan test --filter="ShopHomeTest|ShopStockScanTest"` green (`ShopHomeTest::test_deliveries_tile_links_to_the_legacy_delivery_screen` must be updated to assert the new href `route('shop.deliveries')` and may keep asserting there is no badge only if the test seeds no POS `deliveriesScan` table; simplest is to assert the href and drop the badge assertion, saying so in `implemented.md`).

### 5. Behaviour: `delivery-scan.js`
Files: `resources/js/shop/delivery-scan.js (new)`, `resources/js/shop.js`
What: `Alpine.data('shopDeliveryScan')`. Reads from `this.$root.dataset`: `itemsUrl`, `scanUrl`, `updateUrl`, `delId`, `supplierId`, csrf from the meta tag. State: `session = null`, `rows = []`, `progress = { total: 0, checked: 0, issues: 0 }`, `sort = 'new'`, `recent = []` (barcodes scanned on this page, newest first), `latest = null` (barcode of the last scan), `busy = false`, `editing = null` (barcode whose stepper is open), `toast = null`, `flag = null` (customer-request text for the last scan), `error = null`.
- `init()`: `load()`.
- `load()`: GET `itemsUrl?delID&supplierID`; set `session`, `rows`, `progress`.
- `sorted` getter: `sort === 'scanned'` → rows with `scanned !== null` first (then by name); `sort === 'new'` → rows ordered by position in `recent` (newest first), then unscanned rows, then the rest by name.
- `onScan(code)`: `busy = true`; POST `scanUrl` `{ delID, barcode: code, supplierID }` (no quantity → +1 unit or +1 case); on `success`: `latest = data.product?.barcode ?? code`; unshift into `recent` (dedupe); `flag = data.customerRequests?.length ? 'Put aside for ' + data.customerRequests.map(c => c.customer_name).join(', ') : null`; `await load()` (the server is the source of truth for statuses and progress); `showToast(...)` with `data.matchStatus`: verified → ok "Matches invoice", partial → warn "Short: N of M", over → warn "Over: N of M", extra → bad "Not on this invoice", unknown → bad "Unknown barcode, recorded"; dispatch `shop-scan-done` (or `shop-scan-error` with "Scan failed" on a network error); `busy = false`.
- `edit(row)`: `editing = editing === row.barcode ? null : row.barcode`.
- `adjust(row, delta)`: `target = Math.max(0, (row.scanned ?? 0) + delta)`; POST `updateUrl` `{ delID, barcode: row.barcode, quantity: target, supplierID }`; on success `await load()`; keep `editing` open; toast on failure.
- `showToast(tone, text)` 3 s; `qtyLabel(row)` → `${scanned ?? 0} / ${expected ?? '—'}`; `pill(row)` → `{ text, tone }` mapping `ok`→(OK, ok), `short`→(Short, warn), `over`→(Over, warn), `unexpected`→(Unexpected, bad), `not_scanned`→(Not scanned, muted).
Register `shopDeliveryScan` in `shop.js` inside `alpine:init`.
Check: `node --check resources/js/shop/delivery-scan.js`; a node exercise of `pill()` and `qtyLabel()` and of `sorted` with a three-row fixture prints the expected order (`recent` first, then unscanned, then the rest); `grep -c "route(" resources/js/shop/delivery-scan.js` → 0.

### 6. Views
Files: `resources/views/shop/deliveries.blade.php (new)`, `resources/views/shop/delivery-scan.blade.php (new)`
What, list (screen 04): `<x-shop-layout title="Receive delivery" :back="route('shop.home')">` → `<main class="shop-page shop-page--narrow">`:
- "Start a delivery" `shop-card`: `<form method="POST" action="{{ route('delivery-legacy.create-session') }}">` with `@csrf`, `<input type="hidden" name="return" value="shop">`, a `shop-field` with `<select class="shop-input" name="supplierID" required>` listing suppliers (`SupplierID` → `Supplier`), and a `shop-btn shop-btn--primary shop-btn--block` "Start scanning" with the `scan` icon.
- `shop-between` header "Open deliveries" / "{{ count($open) }} open"; a `shop-list` of `<a class="shop-row" href="{{ route('shop.deliveries.scan', ['delID' => $s->ID, 'supplierID' => $s->supID]) }}">` with `shop-row__lead` (truck icon), title `Supplier`, meta `{{ short id }} · {{ date/time }}` and `{{ items }} items scanned`, aside pill `shop-pill--sage` "In progress" when items > 0 else `shop-pill--muted` "Not started", chevron. `shop-empty` ("No open deliveries", inbox icon) when none.
- "Recently completed" section with the last 10 completed sessions as non-link rows (`shop-row` div) with `shop-pill--ok` "Completed".
What, scan (screen 05): `<x-shop-layout :title="$session['supplier'] . ' · ' . substr($session['id'], 0, 8)" :back="route('shop.deliveries')">` → `<main class="shop-page" x-data="shopDeliveryScan()" data-items-url="{{ route('delivery-legacy.items') }}" data-scan-url="{{ route('delivery-legacy.scan-increment') }}" data-update-url="{{ route('delivery-legacy.update-quantity') }}" data-del-id="{{ $session['id'] }}" data-supplier-id="{{ $session['supplierId'] }}" @scan="onScan($event.detail.code)">`:
- If `$session['completed']`: a `shop-card` notice "This delivery is completed. Corrections are made on the office page." and no scan input; otherwise `<x-shop.scan-input hint="Ready — scan the next item" />`.
- Left column: progress `shop-card` with `shop-progress-meta` (`<strong x-text="progress.checked"></strong> of <span x-text="progress.total"></span> items` and `<span x-text="progress.issues + ' issues'">`), `shop-progress shop-progress--lg` with `:class="{ 'is-done': progress.total && progress.checked === progress.total, 'is-issue': progress.issues > 0 }"` and the bar `:style="'width:' + (progress.total ? Math.round(progress.checked / progress.total * 100) : 0) + '%'"`, `role="progressbar"` with aria values; a `shop-pill shop-pill--sage` flag line `x-show="flag" x-text="flag"`.
- Right column: `shop-between` with `<h2 class="shop-subtitle">Items</h2>` and the sort `shop-seg` (two radio `shop-seg__opt`: "New first" `@change="sort = 'new'"`, "Scanned first" `@change="sort = 'scanned'"`); `shop-list` with `<template x-for="row in sorted" :key="row.barcode">` → `<div class="shop-row" :class="{ 'is-latest': row.barcode === latest, 'is-off': row.status === 'not_scanned' }">` containing `shop-row__main` (meta `shop-code` with `row.code || row.barcode`, title `row.name`), `shop-row__aside` (`shop-row__qty` with `qtyLabel(row)` where the "/ expected" part is a `<small>`, and the pill), and a `shop-iconbtn shop-iconbtn--ghost` edit button (`pencil` icon, `@click="edit(row)"`, `:aria-pressed="editing === row.barcode"`), followed by a `shop-qty` block `x-show="editing === row.barcode"` with `−` (`@click="adjust(row, -1)"`), `<span class="shop-qty__value" x-text="row.scanned ?? 0">`, `+` (`@click="adjust(row, 1)"`). `shop-empty` ("No invoice lines for this supplier are loaded. Scans are still recorded.") when `rows.length === 0`.
- Toast region as on Stock scan. No actions bar in this cycle (Summary arrives in cycle 9).
Check: `php artisan test --filter=ShopViewContractTest` → five screens, green; the rendering tests in step 7 pass.

### 7. Tests
Files: `tests/Concerns/CreatesLegacyDeliveryPosTables.php (new)`, `tests/Feature/Shop/ShopDeliveryTest.php (new)`
What: the trait creates the nine POS tables exactly as `CustomerRequestDeliveryFlagTest::setUp()` does (copy the schema; add `supID` string and `dateUpload` datetime nullable columns to `deliveriesScan`, and `Supplier` to `suppliers` is already there) and offers `seedLegacyDelivery()` that inserts: supplier `999` "Hof Linde"; products `p1` (`5000000000017`, "Oat drink 1 L") and `p2` (`5000000000024`, "Leeks"); `supplier_link` rows for both (`SupplierCode` `S1`/`S2`, `CaseUnits` 6 and 1); `delivery` lines `S1` `myOrder 2` `caseUnits 6` (expected 12) and `S2` `myOrder 8` `caseUnits 1` (expected 8); session `d-1` supID `999` status 0; scan items for `d-1`: `5000000000017` quantity 12, `5000000000024` quantity 6, and an unknown `4260009912200` quantity 3.
Tests (`RefreshDatabase`, employee with `deliveries.process`):
- `employee_can_open_the_delivery_list`: 200, sees "Hof Linde", "Start a delivery", `href` to `route('shop.deliveries.scan', ['delID' => 'd-1', 'supplierID' => '999'])`, `data-shell="shop"`.
- `barista_is_forbidden`: 403 on both Shop routes and on `delivery-legacy.items`.
- `items_endpoint_classifies_the_session`: GET `delivery-legacy.items?delID=d-1&supplierID=999` → 200; rows keyed by barcode: `5000000000017` status `ok` expected 12 scanned 12; `5000000000024` status `short` expected 8 scanned 6; `4260009912200` status `unexpected` expected null scanned 3; `progress` `{ total: 2, checked: 2, issues: 2 }`; `session.supplier` "Hof Linde", `session.completed` false.
- `scan_screen_renders_with_the_endpoint_urls`: GET the scan URL → 200, contains `data-items-url`, `data-scan-url`, `data-update-url`, `data-del-id="d-1"`, the scan input, "New first", and a Back link to `route('shop.deliveries')`.
- `completed_session_hides_the_scan_input`: set `d-1` status 1 → page shows "This delivery is completed" and does not contain `shop-scan__input`.
- `starting_a_delivery_from_shop_lands_on_the_shop_scan_screen`: POST `delivery-legacy.create-session` `{ supplierID: '999', return: 'shop' }` → redirect to a URL starting with `route('shop.deliveries.scan')` containing `supplierID=999`; without `return` → redirect to `route('delivery-legacy.match', [...])`.
- `home_tile_links_to_the_shop_delivery_list`: sees `href="` + `route('shop.deliveries')` and the badge `1` (one open session).
Check: `php artisan test --filter="ShopDeliveryTest|ShopHomeTest|CustomerRequestDeliveryFlagTest"` green.

### 8. README, build, format
Files: `docs/design/shop-mode/README.md`, all touched
What: README: one line under "Component API in the app" noting `delivery-legacy.items` is the JSON the Shop scan screen renders from, and that the Shop flow adds one unit per scan (case codes add a case) with corrections via the row stepper. `npm run build`; `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean; `npm run build` succeeds.

## Verification

1. `php artisan route:list --name=shop.` → `home`, `stock-scan`, `find-product`, `deliveries`, `deliveries.scan`; `php artisan route:list --name=delivery-legacy.items` → present with `deliveries.process`.
2. `php artisan test --filter=Shop` → all green; the contract test lists five screens.
3. `php artisan test` → 17 failed / N passed, the identical 17.
4. `git diff -- app/Http/Controllers/DeliveryLegacyController.php` shows only the new `items()` method and the `return` branch in `createSession()`; `git diff --stat resources/views/delivery-legacy/ app/Http/Controllers/DeliveryController.php resources/views/deliveries/` → empty.
5. `grep -rn "<script\|<style" resources/views/shop/` → nothing; `grep -c "route(" resources/js/shop/delivery-scan.js` → 0.
6. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL` → prints it (this cycle should need no new CSS; if it does, `APP ADDITIONS` only).
7. `./vendor/bin/pint --test --dirty` → clean; `npm run build` → success.
8. Manual, signed in as an employee: Home → "Receive delivery" (badge shows the open count) → list shows open sessions; start a delivery for a supplier with a synced invoice → lands on the Shop scan screen; on the till PC scan a unit barcode → the row jumps to the top with `is-latest`, the count and progress update, a toast says whether it matches; scan a case (outer) barcode → the count rises by the case size; scan an unknown barcode → red toast and an "Unexpected" row; tap the pencil on a row and use − / + → the count changes and the pill follows; switch the sort → order changes; on the tablet the camera works as on Stock scan; open the same session on the office match page → the same quantities are there.

## Risks

- **The invoice scratch table is per sync, not per session.** If a manager syncs a different delivery while staff scan, expected quantities change under them. Unchanged from the office page; the `items` endpoint simply reflects it. Worth a line in the README.
- **Reload after every scan.** `load()` refetches the whole list after each scan so status and progress are always right; with ~50–300 lines the JSON is small and the query is what the office page runs anyway. If it feels slow on the LAN, cycle 9 can patch rows from the increment response instead.
- **`create-session` redirect change** is guarded by an explicit `return=shop` input; the office form is unaffected.
- **Unknown barcodes are recorded** by the endpoint even though they match nothing; that is the legacy behaviour and how "not on invoice" items appear. The red toast makes it visible.
- **`ShopHomeTest` badge**: the Home badge now queries the POS connection; the try/catch keeps Home rendering in tests without the table. Adjust the one test as step 4 says.
- **Session id display**: ids are uuids; show the first 8 characters as the reference, as the office list does.

## Review (Revision 1 → 2)

Reviewed 2026-09-24 by the Planner against the full `implemented.md`, the diffs, and a rerun of the checks. The tree was committed by the owner before this cycle (`31c0bc18`), so the diff is clean for the first time since cycle 3.

Criteria 1–8: all PASS as written. `items()` reproduces the office page's classification and is proved by the endpoint test; `createSession()` gains only the guarded `return=shop` branch (+109/−1 on the controller, with `match()`, `incrementScanQuantity()` and `updateScannedQuantity()` byte-identical); the two Shop routes, the two views, the JS, the trait and 7 tests are in; `--filter=Shop` 113 passed; full suite 17 failed / 513 passed, the identical 17; formatter clean; no stylesheet change was needed.

Deviations 1–4: accepted. Deviation 1 (moving the badge assertion to a test that actually seeds a session) makes the test honest rather than green by accident.

Notes for Planner, each decided:
- Two expected-quantity formulas in the legacy code (fractional `myOrder`): **deferred** as a pre-existing inconsistency; the Shop toast will quote the reloaded row rather than the endpoint's `expectedQty` (step 10 below), so the Shop screen cannot show two figures.
- Duplicated POS table setup in the new trait: **deferred**; a housekeeping cycle can move `CustomerRequestDeliveryFlagTest` onto the trait.
- `items()` on a 1,900-line controller: **accepted for now**; cycle 9 extracts a `LegacyDeliveryQuery` service if it adds summary JSON.
- No number entry on the row stepper: **deferred**; the quantity prompt below covers the common case (adding N at scan time).
- `load()` after every scan: **accepted**; revisit only if it is slow on a real Udea invoice.

**Owner feedback that reopens the cycle.** "On the old screen a user scans a product and is presented with a quick way to adjust the quantity … the new way seems to be scan the item and add 1 to the delivery which is not as practical as the original." Correct, and the plan is at fault: it specified a one-step +1 scan. The office scanner does a quantity-0 lookup, shows the product with "Scanned so far" and "In stock", a stepper for "Quantity to add" defaulting to 1, and an "Add N units" button; only then does it record. Revision 2 adds that flow (steps 9–11). The implementer's work stands; nothing from Revision 1 is undone except the +1 behaviour inside `onScan()`.

**Owner question: do outer (case) barcodes load the case units?** Checked directly against the dev POS database and the endpoint, as an employee, with `quantity: 0` so nothing was recorded. The dev database holds 3,671 supplier links with an outer code. The endpoint resolves them: outer `4019886650205` (supplier 5) returns `scanType: case`, `caseUnits: 4`, resolved to unit barcode `4019886050203`; the Independent outers with leading zeros resolve the same way when scanned exactly as stored. The product in the owner's screenshot (`5010249077300`, FSC Starflower Oil) has **no outer code in this dev copy** (`OuterCode` null, `CaseUnits` 1), so a case scan of it cannot resolve here although it does in production, where that link evidently carries the outer code. So: the mechanism works in dev; that one product's data differs between the two databases. Two caveats carried into step 9: the Shop scan-input strips a GS1 prefix and, for a `01`-prefixed 16-digit string, keeps the GTIN-14, which does not affect the outer codes stored here; and a scanner that drops leading zeros would miss outers stored with them (`00061232200729`), which is the same on the office page.

Result: **READY, Revision 2.** The Implementer starts a fresh `implemented.md` for Revision 2 covering steps 9–11 only; steps 1–8 are accepted as delivered.

### 9. Two-step scan: look up, then add a quantity
Files: `resources/js/shop/delivery-scan.js`
What: replace the immediate increment in `onScan(code)` with the office flow.
- State: add `pending = null`. When set: `{ code, product: { name, barcode, supplierCode, categoryName, currentStock }, expected, scannedSoFar, scanType, caseUnits, qty }`.
- `onScan(code)`: if `pending` is set, first `await commit()` (a new scan confirms the previous item at its current quantity, default 1, so "scan, scan, scan" stays fast; the owner can veto this in favour of cancel-on-new-scan). Then POST `scanUrl` with `{ delID, barcode: code, quantity: 0, supplierID }` (a pure lookup; the endpoint records nothing for quantity 0). If `data.product` is null → dispatch `shop-scan-error` with `Product not found for ${code}` and record nothing (as the office page does; this replaces Revision 1's behaviour of recording unknown barcodes). Otherwise set `pending` with `expected = data.expectedQty`, `scannedSoFar = data.newQuantity` (equals the current total when quantity is 0), `scanType = data.scanType || 'unit'`, `caseUnits = data.caseUnits || 1`, `qty = 1`; dispatch `shop-scan-done` (keeps the input focused for the next scan).
- `bump(delta)`: `pending.qty = Math.max(1, pending.qty + delta)`.
- `commit()`: if no `pending` return; `busy = true`; POST `scanUrl` with `quantity: pending.qty` (cases when `scanType === 'case'`; the endpoint multiplies); on success `latest = data.product?.barcode ?? pending.code`, unshift into `recent`, set `flag` from `data.customerRequests` as before, `pending = null`, `await load()`, then toast from the **reloaded row** for `latest`: `ok` → ok "Matches invoice", `short` → warn `Short: ${scanned} of ${expected}`, `over` → warn `Over: ${scanned} of ${expected}`, `unexpected` → warn "Not on this invoice"; dispatch `shop-scan-done`; on failure toast bad "Not saved, try again" and keep `pending`.
- `cancelPending()`: `pending = null`; dispatch `shop-scan-done`.
- `unitsToAdd` getter: `pending ? pending.qty * (pending.scanType === 'case' ? pending.caseUnits : 1) : 0`; `addLabel` getter: unit scans → `Add ${qty} unit(s)`; case scans → `Add ${qty} case(s) · ${unitsToAdd} units`.
- Enter on the scan input with an empty value should confirm: the scan-input component ignores empty submits, so listen for a keydown Enter on `window` inside the page (`@keydown.enter.window="pending && ! $event.target.value && commit()"` on `<main>`), which only fires when the input is empty and a prompt is open.
Check: a node exercise with a stubbed `fetch` (returning a lookup then an increment payload) prints: after `onScan` → `pending.qty 1`, no increment request sent; after `bump(1)` and `commit()` → the second request body has `quantity: 2`; `onScan` of a second code while pending → three requests, the second being the commit of the first with its quantity; a lookup returning `product: null` → `pending` stays null and a `shop-scan-error` event is dispatched (stub `window.dispatchEvent` and record). `grep -c "quantity: 0" resources/js/shop/delivery-scan.js` → 1.

### 10. The prompt card (matches the office scanner's card)
Files: `resources/views/shop/delivery-scan.blade.php`
What: in the left column, directly under `<x-shop.scan-input>`, add `<section class="shop-card" x-show="pending" x-cloak x-ref="prompt">`:
- `shop-between`: `shop-stack shop-stack--tight` with `<h2 class="shop-subtitle" x-text="pending?.product.name">` and `<span class="shop-row__meta shop-code" x-text="pending ? [pending.product.barcode, pending.product.categoryName].filter(Boolean).join(' · ') : ''">`; a ghost close button (`x` icon, `aria-label="Cancel"`, `@click="cancelPending()"`).
- `shop-facts shop-facts--2`: Scanned so far (`pending?.scannedSoFar`), Invoice (`pending?.expected ?? '—'`), In stock (`pending?.product.currentStock ?? '—'`), and, when `pending?.scanType === 'case'`, a `shop-pill shop-pill--sage` "Case of N" in place of a fourth fact.
- `shop-label` "Quantity to add" then a `shop-stepper`: minus `shop-iconbtn shop-iconbtn--lg` (`@click="bump(-1)"`), `<output class="shop-stepper__value" x-text="pending?.qty">`, plus button.
- `<button class="shop-btn shop-btn--primary shop-btn--lg shop-btn--block" type="button" :disabled="busy" @click="commit()"><x-shop.icon name="check" /><span x-text="addLabel"></span></button>`.
Keep the progress card below it. Nothing else in the view changes.
Check: the rendering test in step 11 passes; `grep -c "Quantity to add" resources/views/shop/delivery-scan.blade.php` → 1.

### 11. Tests and README for the prompt
Files: `tests/Feature/Shop/ShopDeliveryTest.php`, `docs/design/shop-mode/README.md`
What: add `scan_screen_renders_the_quantity_prompt`: the scan page contains "Quantity to add", `commit()`, `cancelPending()`, `bump(1)` and `bump(-1)`. Add `lookup_with_zero_quantity_records_nothing`: POST `delivery-legacy.scan-increment` `{ delID: 'd-1', barcode: '5000000000017', quantity: 0, supplierID: '999' }` → 200 with `product.name` "Oat drink 1 L", `newQuantity` 12 (the seeded total), and `deliveriesScanItems` still holds quantity 12 for that barcode; then the same with `quantity: 2` → `newQuantity` 14 and the table row is 14. README: replace the "adds one unit per scan" sentence with: a scan looks the product up, the prompt shows scanned so far / invoice / in stock, and "Add N" records it; a new scan confirms the open prompt at its current quantity; unknown barcodes are not recorded.
Check: `php artisan test --filter=ShopDeliveryTest` → 9 passed; `grep -c "Quantity to add\|Add N" docs/design/shop-mode/README.md` ≥ 1.

## Review (Revision 2)

Reviewed 2026-09-24 by the Planner against the full Revision 2 `implemented.md`, the three changed files, and a rerun of the checks.

Steps 9–11: all PASS. `onScan()` is a zero-quantity lookup that opens the prompt; `bump()`, `commit()`, `cancelPending()`, `unitsToAdd` and `addLabel` behave as specified, including case scans ("Add 2 cases · 8 units"); a scan while a prompt is open commits it first (request bodies `B1:0 B1:1 B2:0`); unknown barcodes raise the input error and write nothing; the toast is built from the reloaded row. The prompt card renders with the facts, the stepper and the "Add N" button. `lookup_with_zero_quantity_records_nothing` pins the endpoint contract the whole flow rests on. README updated.

Verification rerun by the Planner: `--filter=Shop` 115 passed; full suite 17 failed / 515 passed, the identical 17; formatter clean; the legacy controller diff is still exactly Revision 1's two additions; no stylesheet change.

Deviations 1–4: accepted. Deviation 1 (the `busy` guard on `commit()`) prevents a double add that the plan's own Enter handler would have caused, because the scan-input clears its value before dispatching; well caught and covered by a check. Deviation 2 (`reportRow()` from the reloaded list) is what the plan asked for and closes the two-formulas note. Deviations 3 and 4 are sensible readings of the plan.

Notes for Planner, each decided:
- `reportScan()` removed: **accepted**; cycle 9 keeps toasts sourced from the reloaded rows.
- The window Enter handler is broad and correct only by the `busy` guard: **deferred to cycle 9** as a one-line tightening (fire only when `$event.target` is the scan input), so it becomes correct by construction.
- `cancelPending()` dispatches `shop-scan-done`: **accepted**; a distinct "cancelled" signal is not needed yet.
- Commit at revision boundaries: **for the owner**; the Planner recommends committing cycle 8 now.

Hardware and pointer behaviour (Enter both ways, a real case scan showing "Case of 4", the tablet camera) remain for the owner to try on the devices; the automated evidence is as strong as it can be without a browser.

Result: ACCEPTED. Archived by the Planner to `docs/planImp/archive/2026-09-24-shop-mode-cycle-8/`. Next: cycle 9, delivery summary and completion (screen 06), plus the Enter-handler tightening.

### Verification for Revision 2
Items 1–7 of the original Verification unchanged (expect `--filter=Shop` green with 115 tests; full suite 17 failed / 515 passed). Item 8 replaced: signed in as an employee on the till PC, scan a unit barcode → the prompt card appears with the product, scanned so far, invoice quantity and stock; press + twice and tap "Add 3 units" → the row shows the new count and the toast reports the match; scan a product and immediately scan another → the first is added with quantity 1 and the second prompt opens; scan a case barcode that has an outer code in this database (for example the outer for `4019886050203`, supplier 5) → the prompt says "Case of 4" and the button "Add 1 case · 4 units"; scan a barcode not in POS → red error on the input, nothing recorded; tap × on a prompt → nothing recorded. Also confirm the earlier items (sort, pencil stepper, camera on the tablet) still work.
