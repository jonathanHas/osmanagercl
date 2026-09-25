# Shop mode cycle 10 — Print labels: the shelf-label queue (screen 07)

Status: ACCEPTED
Revision: 2
Planner: Fable 5.1
Date: 2026-09-24

## Goal

Give staff the shelf-label workflow in Shop mode: see which products need a new shelf label and why (new product, price change, re-queued), scan a product to add it to the queue, take one off the queue, and print the whole queue on A4 exactly as the office page does. The Zebra label tile links to the office Zebra page for now; a Shop version of it is a later cycle. First, one shared-component fix carried from cycle 9b: `x-shop.icon` must pass attributes through.

## Context

Baseline: cycle 9b accepted and archived (`docs/planImp/archive/2026-09-24-shop-mode-cycle-9b/`); HEAD `f1ae53ad` plus 9b's uncommitted files unless the owner has committed. Record `git status --short` at the start and separate pre-existing dirt.

**How the shelf-label queue works today** (`app/Http/Controllers/LabelAreaController.php`): the queue is not a table but a derivation over `App\Models\LabelLog` (default DB; events `new_product`, `price_update`, `requeue_label`, `label_print`; static loggers `logNewProduct`, `logPriceUpdate`, `logLabelPrint($barcode, ?$userId)`, `logRequeueLabel($barcode, ?$userId)`; scopes `eventType`, `forBarcode`). A product "needs a label" when its most recent queue event in the last 30 days is newer than its most recent `label_print` in the same window. Two private methods compute it: `getProductsNeedingLabels(array $filters)` (returns POS `Product` models ordered by name with `label_event_type` and `label_event_date` attached) and `getLabelCountsByEventType()` (per-event counts plus `total`). The office page `shelfLabels()` (`labels.index`, `resources/views/labels/index.blade.php`, 1,270 lines) shows the queue with template choice, "Preview All", "Print All", per-event filters, recent print sessions, "Clear All"/"Restore All" (manager-only since cycle 2) and a scan-to-label modal.
- Printing: `POST labels.print-a4` with `products[]` (POS product ids) and optional `template_id`; logs `label_print` for each product (which removes it from the queue) and returns `resources/views/labels/a4-print.blade.php`, a standalone HTML page the office opens with `target="_blank"` for the browser's print dialog. `LabelTemplate::getDefault()` and `->labels_per_a4` give the sheet size.
- Adding: `POST labels.scan` `{ barcode }` → looks the product up, `LabelLog::logRequeueLabel($product->CODE)`, returns `{ success, message, product: { code, name, … } }`; unknown barcode → `{ success: false, message }`. `POST labels.lookup-barcode` returns price fields without queuing.
- There is no single-product "take off the queue" endpoint; the office clears everything (`labels.clear-all`, manager-only). A per-row dismiss is new in this cycle and is just `logLabelPrint` for one barcode.
- Route gating since cycle 2: `labels.index`, `labels.zebra`, `labels.shelf-labels`, `labels.print-a4`, `labels.preview-a4`, `labels.requeue`, `labels.lookup-barcode`, `labels.scan`, `labels.printer-queue` → `permission:labels.print` (employees hold it); `labels.clear-all`, `labels.restore-batch` → `labels.manage`.
- Home tile `labels` in `config/shop.php` currently links to `labels.index` with `badge => null`; `ShopHomeController::badgeCount()` has branches for `requests` and `deliveries`.

**Design.** `docs/design/shop-mode/screen-07-print-labels.html`: `shop-page`; a `shop-tiles shop-tiles--hub` of three `shop-tile shop-tile--row` (icon, label, hint): Shelf labels / Zebra labels / Scan to label; then a "Print queue" section (`shop-between` with `shop-subtitle` and `shop-meta` "7 labels · Counter Zebra") whose `shop-list` rows have title (product), meta ("Shelf label · price changed"), and a `shop-qty` with `shop-qty__value` "×1" and a ghost `x` remove button; `shop-actions` with "Clear queue" (secondary) and "Print 7 labels" (primary, printer icon). Sprite ids: `printer`, `tag`, `barcode`, `scan`, `x`, `check`, `alert`, `list-checks`.

**Shop pieces to reuse.** `ShopLayout`, `x-shop.icon`, `x-shop.scan-input` (events `scan {code}`, `scan-empty`; listens `shop-scan-done`, `shop-scan-error`, `shop-scan-saved`), `shop.js` registration inside `alpine:init`, data-attribute URLs, client toast pattern (icon toggled by tone, cycle 9b), server flash toasts in the layout (cycle 9). Contract and traps as in cycles 3–9b.

**Tests.** No test covers `LabelAreaController` today (`FruitVegLabelPrintingTest` is a different queue and already fails for environment reasons). POS fixture: `PRODUCTS` (`ID`, `NAME`, `CODE`, `CATEGORY`, `PRICESELL`, `TAXCAT`) and `TAXES` (`ID`, `CATEGORY`, `RATE`) for the price accessor, as in `tests/Feature/Shop/ShopStockScanTest::createPosProduct()` plus a `TAXES` table; `LabelLog` and `LabelTemplate` live on the default DB (migrations run under `RefreshDatabase`; seed one active default template with `labels_per_a4 = 24` if `LabelTemplate::getDefault()` returns null in tests). Baseline suite: 17 failed / 528 passed, the same 17 (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `WasteLogTest` ×2, `ProductTest` ×2, `FruitVegLabelPrintingTest` ×2, `TestScraperControllerTest` ×1).

## Constraints

- Do not commit, push or deploy.
- The office label pages are not modified. `LabelAreaController` changes only by: moving the two private queue methods into a new service (the controller keeps one-line private wrappers so nothing else in it changes), one new JSON action `queue()`, and one new action `dismiss()`. `printA4()`, `processBarcodeScan()`, `lookupBarcode()` are untouched.
- Printing reuses `labels.print-a4` and the existing A4 page in a new tab; no new print rendering.
- "Clear queue" is shown only to holders of `labels.manage` (employees do not see it); per-row dismiss is available to `labels.print` holders because it is the same act as printing one label.
- Design/content contract, stylesheet rule (`APP ADDITIONS` only, and probably none needed), rendering tests as markup checks, no Alpine `@` shorthand that is a Blade directive.

## Out of scope

- A Shop version of Zebra labels (saved ZPL labels, copies, the printer spool): the tile links to the office `labels.zebra` page. Next cycle.
- Template choice (the default template is used), per-event filters, "Preview All", recent print sessions, restore batch.
- Label translation and camera translation pages.
- Changing how the queue is derived (the 30-day windows stay as they are).

## Steps

### 1. `x-shop.icon` passes attributes through
Files: `resources/views/components/shop/icon.blade.php`, `tests/Feature/Shop/ShopHomeTest.php`
What: render `{{ $attributes }}` on the `<svg>` (declared props `name`, `size`, `class` are excluded from `$attributes` automatically): `<svg class="shop-ico{{ … }} {{ $class }}" aria-hidden="true" {{ $attributes }}>`. No call site changes; cycle 9b's toast spans keep their directives on the span.
Test: in `ShopHomeTest`, `icon_component_passes_attributes_through`: `Blade::render('<x-shop.icon name="check" x-show="open" data-test="1" />')` contains `x-show="open"` and `data-test="1"` and still contains `class="shop-ico`.
Check: `php artisan test --filter="ShopHomeTest|ShopViewContractTest"` green.

### 2. `LabelQueueService`
Files: `app/Services/LabelQueueService.php (new)`, `app/Http/Controllers/LabelAreaController.php`
What: move the bodies of `getProductsNeedingLabels(array $filters = [])` and `getLabelCountsByEventType()` into the service as public `needingLabels(array $filters = [])` and `countsByEventType()`, byte-for-byte apart from `$this->` references. In the controller, inject the service in the constructor (it already injects `LabelService`, `ZplGeneratorService`, `TillVisibilityService`; add `LabelQueueService $labelQueue`) and turn the two private methods into one-line delegations so every existing caller (`hub()`, `shelfLabels()`, `clearAllLabels()` and any other) is unchanged.
Check: `grep -c "labelQueue->" app/Http/Controllers/LabelAreaController.php` → 2; `php artisan route:list --name=labels.index` still resolves; rendering `/labels` as an admin through the kernel returns 200 (the implementer can do this with `Auth::login` as in earlier cycles); the diff of the controller shows the two method bodies removed and two delegations added, nothing else besides steps 3–4.

### 3. JSON: the queue, and dismiss one product
Files: `app/Http/Controllers/LabelAreaController.php`, `routes/web.php`
What:
- `queue()` → `response()->json(['rows' => …, 'counts' => $this->labelQueue->countsByEventType(), 'labels_per_sheet' => LabelTemplate::getDefault()?->labels_per_a4 ?? 24])` where each row is `{ id: Product ID, code: CODE, name: NAME, price: getFormattedPriceWithVatAttribute(), event: label_event_type, since: label_event_date (ISO), reason: 'New product'|'Price changed'|'Re-queued' }` from `needingLabels()`.
- `dismiss(Request $request)` validating `barcode` (required string, max 255): find the product by `CODE` (404 JSON if missing), `LabelLog::logLabelPrint($product->CODE, auth()->id())`, return `{ success: true, code }`. This is the same record the office "Clear All" writes, for one product.
- Routes next to the other label routes, in the `labels.print` group: `Route::get('/labels/queue', [LabelAreaController::class, 'queue'])->name('labels.queue');` and `Route::post('/labels/dismiss', [LabelAreaController::class, 'dismiss'])->name('labels.dismiss');`.
Check: `php artisan route:list --name=labels.queue` and `--name=labels.dismiss` show the `labels.print` permission; the tests in step 8 pin the shapes.

### 4. Home tile and badge
Files: `config/shop.php`, `app/Http/Controllers/Shop/ShopHomeController.php`
What: the `labels` tile → `'route' => 'shop.labels'`, `'badge' => 'labels'`; in `badgeCount()` add `'labels' => $this->labelQueueCount()` using `app(LabelQueueService::class)->countsByEventType()['total'] ?: null` wrapped in the same try/catch style as the deliveries badge (the POS connection is needed only if the derivation touches it; it does, via `Product::whereIn`).
Check: `php artisan test --filter=ShopHomeTest` green (adjust the fixture-only expectations as in cycle 8 if a tile assertion changes; say so).

### 5. Route and controller
Files: `app/Http/Controllers/Shop/LabelsController.php (new)`, `routes/web.php`
What: `LabelsController@index` returns `view('shop.labels', ['zebraUrl' => route('labels.zebra'), 'canClear' => auth()->user()->can('labels.manage')])`. Route in the `shop` group: `GET /labels` → `index`, name `shop.labels`, `permission:labels.print`.
Check: `php artisan route:list --name=shop.labels` → present.

### 6. Behaviour: `labels.js`
Files: `resources/js/shop/labels.js (new)`, `resources/js/shop.js`
What: `Alpine.data('shopLabels')` reading `queueUrl`, `scanUrl`, `dismissUrl`, `clearUrl` (empty string when not allowed) from `data-*`; csrf from the meta tag. State: `rows = []`, `counts = null`, `perSheet = 24`, `loading = true`, `busy = false`, `toast = null`, `error = null`. `init()` → `load()`. Getters: `total` (`rows.length`), `sheets` (`Math.ceil(total / perSheet)`), `ids` (`rows.map(r => r.id)`). Methods:
- `load()`: GET `queueUrl` → `rows`, `counts`, `perSheet`.
- `onScan(code)`: `busy = true`; POST `scanUrl` `{ barcode: code }`; on `success` → `await load()`, toast ok `Added: ${data.product.name}`, dispatch `shop-scan-done`; on `success === false` → dispatch `shop-scan-error` with `data.message`; network error → `shop-scan-error` "Could not add"; `busy = false`.
- `dismiss(row)`: POST `dismissUrl` `{ barcode: row.code }` → on success remove the row locally and toast ok `Removed: ${row.name}`; on failure toast bad.
- `clear()`: only when `clearUrl`; POST it with an empty body → `await load()`, toast ok with `data.message`.
- `showToast(tone, text)` as elsewhere; `reasonLabel(row)` → `Shelf label · ${row.reason}`.
Printing is a plain form (step 7), not JS.
Check: `node --check`; a node exercise with a stubbed fetch prints `total 3`, `sheets 1` for three rows at 24 per sheet, then after `dismiss(rows[0])` `total 2`; `grep -c "route(" resources/js/shop/labels.js` → 0.

### 7. The screen
Files: `resources/views/shop/labels.blade.php (new)`
What: `<x-shop-layout title="Print labels" :back="route('shop.home')">` → `<main class="shop-page" x-data="shopLabels()" data-queue-url="{{ route('labels.queue') }}" data-scan-url="{{ route('labels.scan') }}" data-dismiss-url="{{ route('labels.dismiss') }}" data-clear-url="{{ $canClear ? route('labels.clear-all') : '' }}" @scan="onScan($event.detail.code)">`:
- `<x-shop.scan-input placeholder="Scan a product to add a label" hint="Ready — scan to add to the queue" />`.
- Hub tiles, `shop-tiles shop-tiles--hub`: "Zebra labels" (`shop-tile shop-tile--row`, `barcode` icon, hint "Saved labels and the counter printer", `href="{{ $zebraUrl }}"`), and "Printed recently" (`list-checks` icon, hint "Office page: history and re-queue", `href="{{ route('labels.shelf-labels') }}"`). (The design's third tile, "Scan to label", is the scan input above, so it is not repeated as a tile.)
- Queue section: `shop-between` with `<h2 class="shop-subtitle">Print queue</h2>` and `<span class="shop-meta" x-text="total ? total + ' labels · ' + sheets + ' A4 sheet' + (sheets === 1 ? '' : 's') : ''">`; `shop-list` with `<template x-for="row in rows" :key="row.id">` → `<div class="shop-row">` (`shop-row__main`: title `row.name`, meta `reasonLabel(row) + ' · ' + row.price`), `<div class="shop-qty"><span class="shop-qty__value">×1</span><button class="shop-iconbtn shop-iconbtn--ghost" type="button" :aria-label="'Remove ' + row.name" @click="dismiss(row)"><x-shop.icon name="x" /></button></div>`; `shop-empty` "All caught up" (`check` icon, text "Nothing needs a label. Scan a product to add one.") when `! loading && total === 0`.
- Print form: `<form method="POST" action="{{ route('labels.print-a4') }}" target="_blank" x-show="total > 0" x-cloak>` with `@csrf`, `<template x-for="id in ids" :key="id"><input type="hidden" name="products[]" :value="id"></template>`, and the actions bar inside it: `shop-actions` with (when `$canClear`) `<button type="button" class="shop-btn shop-btn--secondary shop-btn--lg" @click="clear()">Clear queue</button>` and `<button type="submit" class="shop-btn shop-btn--primary shop-btn--lg"><x-shop.icon name="printer" /><span x-text="'Print ' + total + ' label' + (total === 1 ? '' : 's')"></span></button>`. After submitting, the A4 page opens in a new tab and the queue on this tab is stale: add `@submit="setTimeout(() => load(), 1500)"` on the form so the list refreshes once the prints are logged.
- Toast region as on the other screens (icon by tone).
Check: `php artisan test --filter=ShopViewContractTest` → seven screens, green; the rendering tests in step 8 pass.

### 8. Tests
Files: `tests/Feature/Shop/ShopLabelsTest.php (new)`
What (`RefreshDatabase`; POS `PRODUCTS` + `TAXES` in-memory with two products `5000000000017` "Oat drink 1 L" and `5000000000024` "Leeks"; a default `LabelTemplate` if none; employee with `labels.print`, manager with `labels.print` + `labels.manage`):
- `employee_can_open_the_labels_screen`: 200, `data-shell="shop"`, `data-queue-url`, the scan input, "Print queue", a Zebra tile linking to `route('labels.zebra')`, no "Clear queue".
- `manager_sees_clear_queue`: contains "Clear queue" and `data-clear-url="` + `route('labels.clear-all')`.
- `barista_is_forbidden`: 403 on `shop.labels` and on `labels.queue`.
- `queue_lists_products_whose_latest_event_is_newer_than_their_last_print`: `LabelLog::logPriceUpdate('5000000000017')`; `LabelLog::logNewProduct('5000000000024')` then `LabelLog::logLabelPrint('5000000000024')` (printed after queuing) → GET `labels.queue` → one row, code `5000000000017`, `reason` "Price changed", `counts.total` 1, `labels_per_sheet` 24.
- `dismiss_takes_a_product_off_the_queue`: queue both, POST `labels.dismiss` `{ barcode: '5000000000017' }` → success; the queue now has one row (`5000000000024`); a `label_print` row exists for the dismissed barcode with the employee's user id.
- `scan_adds_a_product_to_the_queue`: POST `labels.scan` `{ barcode: '5000000000024' }` → success with `product.name` "Leeks"; queue has it with reason "Re-queued".
- `home_tile_links_to_the_labels_screen_with_a_badge`: queue one product → `/shop` shows `href="` + `route('shop.labels')` and the badge `1`.
- `service_and_controller_agree`: `app(LabelQueueService::class)->countsByEventType()['total']` equals the count of rows from `labels.queue`.
Check: `php artisan test --filter="ShopLabelsTest|ShopHomeTest"` green (8 new tests).

### 9. README, build, format
Files: `docs/design/shop-mode/README.md`, all touched
What: README: one line that Print labels renders from `labels.queue`, adds via `labels.scan`, removes via `labels.dismiss`, prints via the office `labels.print-a4` page in a new tab, and that Zebra labels are still the office page. `npm run build`; `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean; `npm run build` succeeds.

### 10. (Rev 2) Derive the queue in a fixed number of queries
Files: `app/Services/LabelQueueService.php`, `tests/Feature/Shop/ShopLabelsTest.php`
Why: the service issues one `label_print` lookup per candidate barcode. Measured on the dev copy of production data (read-only replay in tinker, `now()->subDays(400)` window): 3,324 barcodes took 33.4 s, about 10 ms per barcode. Real 30-day windows in this table run 180 to 1,430 distinct barcodes (July 2026: 1,429), so the badge alone could add 2 to 14 s to every Shop Home load, and the office hub and shelf-labels pages have been paying the same cost (the shelf-labels page runs the derivation twice).
What: add a private `candidates(): Collection` that does the whole derivation with two queries and no loop queries, and make both public methods use it:
1. Candidate events as now (`whereIn` the three queue event types, 30-day window, ordered `created_at desc`, `get()`), grouped by barcode, most recent per barcode.
2. One query for the last print per barcode: `LabelLog::where('event_type', LabelLog::EVENT_LABEL_PRINT)->where('created_at', '>=', now()->subDays(30))->whereIn('barcode', $barcodes)->groupBy('barcode')->selectRaw('barcode, MAX(created_at) as last_print')->pluck('last_print', 'barcode')`.
3. For each barcode, keep it when there is no last print or `$mostRecentEvent->created_at > Carbon::parse($lastPrint)`. This is the same rule as today (`first()` on `created_at desc` is `MAX(created_at)`); the comparison stays strict.
`candidates()` returns a collection of `['barcode', 'event_type', 'created_at']`. `needingLabels($filters)` applies the filter and fetches the products as now; `countsByEventType()` counts the candidates by `event_type` and sets `total`. Chunk the `whereIn` at 1,000 barcodes (`$barcodes->chunk(1000)`) so a bad month cannot exceed a bind limit.
Tests (in `ShopLabelsTest`):
- `queue_derivation_uses_a_fixed_number_of_queries`: seed five barcodes with `logPriceUpdate` (add products `p3..p5` to the POS fixture, or log barcodes that have no product; the query count is what matters), `DB::connection()->enableQueryLog()`, call `needingLabels()`, assert `count(DB::getQueryLog()) <= 3`; flush, call `countsByEventType()`, assert `<= 2`.
- `queue_ignores_events_and_prints_outside_the_window`: an event logged then its `created_at` set to 31 days ago (`LabelLog::where(...)->update(['created_at' => now()->subDays(31)])`) is not listed; a product with an event 5 days ago and a print 40 days ago is listed (print outside the window is ignored, as today); a product with an event 5 days ago and a print 2 days ago is not listed.
Check: `php artisan test --filter=ShopLabelsTest` green (11 tests); in tinker on the dev DB, `app(App\Services\LabelQueueService::class)->countsByEventType()` with `DB::enableQueryLog()` shows 2 queries; rendering `/labels` and `/labels/shelf-labels` as admin through the kernel still returns 200.

### 11. (Rev 2) Empty state only when there is no error
Files: `resources/views/shop/labels.blade.php`
What: the "All caught up" block's `x-show` becomes `! loading && ! error && total === 0`, so a failed load shows only "Something went wrong". Add an assertion to `employee_can_open_the_labels_screen` that the markup contains `! loading && ! error && total === 0`.
Check: `php artisan test --filter="ShopLabelsTest|ShopViewContractTest"` green.

### 12. (Rev 2) Correct the two misleading migration comments
Files: `database/migrations/2025_07_22_155113_add_requeue_label_event_type_to_label_logs_table.php`, `database/migrations/2025_08_07_164348_add_barcode_change_support_to_label_logs.php`
What: comment-only change. Replace the sentence claiming SQLite "stores these columns as text, so there is nothing to do" with: "SQLite: Laravel's enum() adds a CHECK constraint, so this widening is needed there too; it is done by 2026_09_24_220000_widen_label_logs_event_type_on_non_mysql." No code lines change.
Check: `git diff database/migrations/2025_*label_logs*` shows only comment lines changed; `php artisan migrate:fresh --env=testing` is not required (the test suite already rebuilds).

## Verification

1. `php artisan route:list --name=shop.labels` → present; `--name=labels.queue`, `--name=labels.dismiss` → present with `labels.print`.
2. `php artisan test --filter=Shop` → all green; the contract test lists seven screens.
3. `php artisan test` → 17 failed / N passed, the identical 17 (Rev 1 ended at 17 / 539; Rev 2 adds three tests).
4. `git diff app/Http/Controllers/LabelAreaController.php` shows: constructor injection, two private methods reduced to delegations, `queue()` and `dismiss()` added, nothing else; `git diff --stat resources/views/labels/` → empty.
5. `grep -rn "<script\|<style" resources/views/shop/` → nothing; `grep -c "route(" resources/js/shop/labels.js` → 0; `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL` → prints it.
6. `./vendor/bin/pint --test --dirty` → clean; `npm run build` → success.
7. Manual, signed in as an employee on the till PC: Home → "Print labels" (badge = queue size) → the queue lists the same products as the office Shelf Labels page with the same reasons; scan a product → it appears with "Re-queued" and a toast; tap × on a row → it disappears and reappears on neither page; "Print N labels" opens the A4 page in a new tab, the browser print dialog appears, and after closing it the Shop queue is empty and the office page's "Recent Label Prints" shows the batch; the Zebra tile opens the office Zebra page; as a manager the "Clear queue" button is present and empties the queue; on the tablet the scan input suppresses the keyboard and the camera works.

## Risks

- **Moving the two queue methods into a service** touches `hub()`, `shelfLabels()` and `clearAllLabels()` indirectly. The delegations keep names and signatures, and rendering `/labels` and `/labels/shelf-labels` as an admin proves nothing broke; there were no tests for them before, so the new `service_and_controller_agree` test is the first.
- **Print in a new tab.** Pop-up blockers may block `target="_blank"` on some tablets; the office page has the same behaviour. The 1.5 s refresh is a heuristic; the queue is also correct on the next load.
- **Dismiss is a `label_print` record.** It will show in the office "Recent Label Prints" as a print by that user, which is honest (the label was deliberately taken off), and it means "Restore All" can bring it back.
- **Badge cost.** The queue derivation runs one query per candidate barcode (pre-existing shape); with a 30-day window it is tens of rows. If Home slows, cache the count for a minute; not needed yet.

## Review

### Revision 1 (2026-09-24, Planner)

Read `implemented.md` to the end and the diff of every file listed. Reran: `php artisan test` (17 failed / 539 passed, same pre-existing set), `route:list` (`shop.labels`, `labels.queue`, `labels.dismiss` all under `labels.print`), `pint --test --dirty` (PASS 11 files), design-block `cmp` (identical), the contract greps (clean), and the print-form contract (`printA4()` reads `products[]` as POS `ID`s, which is what `queue()` returns as `id`).

**Criteria.** Steps 1–9: pass. Verification 1–6: pass, reproduced. Verification 7 (manual): not run, reasonable; every check on this screen writes to the live queue. It moves to the owner, on the till, after Revision 2.

**Deviations.**
1. Migration widening `event_type` on non-MySQL: **accepted.** Genuine latent defect, no-op on MySQL by construction, and it is what makes `labels.scan` testable at all. Its two misleading predecessors get a comment fix in step 12.
2. `TAXCATEGORIES` fixture: **accepted**, the plan was wrong about the accessor's path.
3. `hasPermission()` instead of `can()`: **accepted**, the plan named the wrong helper; the trait's own method is right.
4. Extra 404 test: **accepted.**

**Notes for Planner.**
- Migration comments: **fixed now**, step 12.
- Dismiss recorded as a `label_print`: **deferred.** A distinct event type needs another MySQL enum widening plus a change to the derivation and the office history page. Recorded as a candidate for a housekeeping cycle; for now the office "Recent Label Prints" will show a dismissal as a print by that user.
- Badge cost: **measured, and it is why this is Revision 2.** The implementer could not measure it because the dev queue is empty; a read-only replay over the historical data shows ~10 ms per candidate barcode and real 30-day windows of 180–1,430 barcodes. Step 10 makes the derivation two queries regardless of size. This also speeds up the office hub and shelf-labels pages, which share the service now.
- Clear-queue guard: acknowledged; `labels.clear-all` is gated server-side by `labels.manage`, nothing to do.
- Zebra tile leaves Shop mode visually: as planned; next cycle.
- Nothing committed: correct. The owner commits 9b and 10 together or separately; the files are disjoint.

**Also found in the diff** (not raised by the implementer): with a failed queue load both "Something went wrong" and "All caught up" render. Step 11.

**Verdict:** READY, Revision 2. Steps 10–12 only; steps 1–9 stand and must not be reworked.

### Revision 2 (2026-09-24, Planner)

Read `implemented.md` to the end and the diff of `LabelQueueService`, the view, the two migration comments and the test additions. Reran `php artisan test`: 17 failed / 541 passed, the identical pre-existing set. Controller diff unchanged from Revision 1.

**Steps 10–12: pass.** The derivation is now two queries plus one per 1,000-barcode chunk; the rule is the same (`MAX(created_at)` equals `first()` on a descending list, comparison still strict, both windows still 30 days). The replay the Planner timed at 33.4 s now takes 1.22 s. The empty-state guard and the migration comments are as specified.

**Deviations.**
1. Accumulating chunk results in a plain array instead of `Collection::merge()`: **accepted, and the important catch of this cycle.** Numeric barcodes become integer keys, which `merge()` renumbers, so every last-print lookup would have missed and the queue would never have emptied. The plan did not say how to combine chunks; the implementer found the bug through the dismiss test and added a direct test for the rule.
2. Window test reads `candidates()` by reflection: **accepted.** The alternative was three more POS products to test the same rule indirectly.
3. Second migration's comment wording: **accepted.**

**Notes for Planner.**
- `merge()` bug: covered above; the new window test now guards the rule directly.
- `barcode_change` events neither queue nor count a label, on any page, before and after this cycle: **deferred to the owner.** A shelf label carries the barcode, so a changed barcode probably should queue a label; that is a product decision and a one-line addition to the candidate event list once made.
- Chunk size 1,000 against a worst real window of ~1,430: fine.
- Office pages faster for free: noted; nothing to do.
- Dismissal shows as a print in the office history (now observed, not just reasoned): **still deferred**, recorded as a housekeeping candidate with the enum change it needs. Told to the owner.
- "1 labels" in the queue meta: **fixed in the next cycle as its first step** (one ternary in `labels.blade.php`), the same way the icon fix rode on this cycle.
- A4 page auto-prints and pop-up blockers: known risk, as planned.
- Nothing committed: correct.

**Manual walkthrough:** run by the implementer as the real employee account on the dev app, queue restored to empty afterwards. Everything on the checklist observed except the manager Clear queue button (covered by a test) and the tablet camera. The owner should still try the print dialog on the till PC and the camera on the tablet.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-24-shop-mode-cycle-10/`.
