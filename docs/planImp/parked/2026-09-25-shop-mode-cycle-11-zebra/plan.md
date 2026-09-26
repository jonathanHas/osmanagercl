# Shop mode cycle 11 — Zebra labels: print a saved label from the counter

Status: PARKED (2026-09-25) — not yet implemented. To resume: copy this file back to docs/planImp/plan.md, set Status: READY, re-check the Context section against the current code (LabelAreaController::zebra(), ZebraLabelController::print(), routes, ShopLabelsTest fixture) and bump Revision if anything moved.
Revision: 1
Planner: Fable 5.1
Date: 2026-09-25

## Goal

Give staff the Zebra half of "Print labels" inside Shop mode: find a saved Zebra label by scanning the product or by typing, see whether the label's price or country still matches the till, choose how many copies, and send it to the counter printer. When the label is out of date, staff can print it with the till's price or country without editing the saved label (the office print endpoint already supports per-print field overrides). Managers can also clear the printer spool. The "Zebra labels" tile on the Shop print-labels screen then stays inside Shop mode instead of dropping into the office page.

## Context

Baseline: cycle 10b accepted and archived (`docs/planImp/archive/2026-09-25-shop-mode-cycle-10b/`); 9b, 10 and 10b may still be uncommitted. Record `git status --short` first and separate pre-existing dirt by filename.

**Data.** `App\Models\ZebraLabel` (default DB, `zebra_labels`; fillable `name`, `product_code`, `product_id`, `description`, `zpl_content`, `original_filename`, `label_width_mm`, `label_height_mm`, `default_copies`, `is_active`, `created_by`; `scopeActive`; `product()` belongs to POS `Product` by `CODE`). Static helpers: `extractTextFields($zpl)` (ordered `^FD` texts of fields with a font, skipping `^XG`/`^BC` segments), `findPriceField($fields)` (`[index, value]` for a field matching `\15<number>`), `findCountryField($fields, $countryNames)`, `setZplQuantity($zpl, $copies)`, `replaceTextFields($zpl, [index => text])`. Dev DB: 17 active labels (14 on products, 3 standalone), sizes 112.6 × 75.1 and 115.2 × 78.3 mm, `default_copies` null for all but one; ZPL averages 25 kB (so the list JSON must not include `zpl_content`).

**Office page** `LabelAreaController::zebra()` (`labels.zebra`, `labels.print`), view `resources/views/labels/zebra.blade.php` (657 lines). For the non-translations view it builds three arrays of `['id','name','product_name','product_code','width_mm','height_mm','default_copies','mismatches','fields']`: `$zebraLabels` (product visible on the till: `TillVisibilityService::isVisibleOnTill($product->ID)` = a row in POS `PRODUCTS_CAT` with `PRODUCT` = id), `$otherLabels` (product not on the till), `$standAloneLabels` (no product). `mismatches` is null or `['price' => ['field_index','label_value','db_value','new_field'], 'country' => [...]]`, comparing the ZPL's `\15` price with `$product->getGrossPrice()` (PRICESELL × (1 + VAT), VAT via `TAXCATEGORIES`→`TAXES` as in cycle 10's fixture) and the country field with `$product->vegDetails->country->name` (POS `vegDetails.countryCode` → default-DB `countries.id`; `Country::pluck('name')` supplies the candidate names). Measured: 46 ms for the 14 product labels. The office print modal shows the mismatch, a "Fix" button (`zebra-labels.update-fields`, **labels.manage**) and a copies input, then POSTs `zebra-labels.print`.

**Print endpoint** `ZebraLabelController::print(Request, ZebraLabel)` (`POST labels/zebra/manage/{zebraLabel}/print`, `zebra-labels.print`, **labels.print**): `copies` clamped 1–99; optional `fields` (array index → text) applied with `replaceTextFields` for this print only; `setZplQuantity`; `ZebraPrintService::sendRaw($zpl)` (shells out to `lp -h host -d printer -o raw`, 15 s timeout). Returns `{ success, message, output }`, HTTP 200 or 500; message is "Print job sent (N copies)" / "Couldn't confirm the print job — the printer may not have responded." / "Print failed".

**Printer spool.** `labels.printer-queue` (GET, labels.print) → `{ success, host, printer, jobs[], count, output }` (runs `lpstat -h host -o`, up to 15 s, so call it from the browser after render, never during it). `labels.printer-cancel` (POST, **labels.manage**) with `{ all: true }` or `{ job_id }` → `{ success, message }`.

**Faking the printer in tests.** `tests/Feature/DeliveryTranslatedLabelPrintingTest.php` lines 89–100: `$this->app->bind(ZebraPrintService::class, fn () => (new ZebraPrintService('printer.test', '631', 'TEST-PRINTER', 1))->usingRunner(function (string $command) use (&$captured) { … return 'request id TEST-PRINTER-1 (1 file(s))'; }))`. `sendRaw` writes the ZPL to a temp file named in the command; the runner can read that file to capture the ZPL (the existing test shows how). Both controllers take the service by constructor injection, so the bind applies.

**Shop pieces to reuse.** `x-shop.scan-input` (events `scan {code}`, `scan-empty`; listens `shop-scan-done`, `shop-scan-error`, `shop-scan-saved`), the delivery-scan prompt card pattern (`resources/views/shop/delivery-scan.blade.php` lines 21–66: `shop-card` with `shop-between` header + ghost × button, `shop-facts`, `shop-stepper` with `shop-iconbtn--lg` ± and `shop-stepper__value`, block primary button), find-product's clickable rows (`<button class="shop-row" type="button" @click=…>`, line 85), `shop-search` (`<div class="shop-search"><x-shop.icon name="search" /><input class="shop-input" …></div>`), `shop-pill--ok|--warn|--muted`, `shop-switch` (`<label class="shop-switch"><input type="checkbox"><span class="shop-switch__track"></span><span class="shop-switch__text"></span></label>`; the text reads On/Off from CSS), `shop-empty`, the toast block from `shop/labels.blade.php`. No design screen exists for Zebra labels; this screen is composed from those components only. Sprite ids in `docs/design/shop-mode/README.md` (`barcode`, `printer`, `search`, `x`, `check`, `alert`, `minus`, `plus`, `list-checks`).

**Tests.** `tests/Feature/Shop/ShopLabelsTest.php` is the fixture to copy (POS `PRODUCTS`, `TAXES`, `TAXCATEGORIES`; `userWith()`, `employee()`, `manager()`). Suite baseline after 10b: 17 failed / 546 passed, the same 17.

## Constraints

- Do not commit, push or deploy.
- Office Zebra page behaviour is unchanged; the only edit to `LabelAreaController::zebra()` is delegating its three-array computation to the new service (same arrays, same keys, same order). `ZebraLabelController` is untouched.
- Employees never edit a saved label: overrides are per print, through the existing `fields` parameter. "Fix" (persisting) stays on the office page.
- Cancelling spool jobs is `labels.manage` only, server-side already; the Shop button is shown only to those users.
- Shop view contract, design-block rule, no Alpine `@` Blade-directive shorthands, URLs via `data-*` or the list JSON (never `route()` in JS).

## Out of scope

- Translated labels (the office page's second tab, `labels.translate`) and auto-print.
- Creating, editing, deleting labels, `update-copies`, `update-fields`.
- Per-job cancel (only "Cancel all").
- Printer status beyond the job count.

## Steps

### 1. `ZebraLabelListService`
Files: `app/Services/ZebraLabelListService.php (new)`, `app/Http/Controllers/LabelAreaController.php`
What: `public function grouped(?string $search = null): array` returning `['onTill' => [...], 'other' => [...], 'standalone' => [...]]`, built by moving the non-translations branch of `zebra()` (from `$zebraLabelsQuery = …` to the end of the `foreach`) verbatim, including the `$countryNames` lookup and the `TillVisibilityService` call (inject it in the service constructor). `zebra()` becomes: in the `else` branch, `$groups = $this->zebraList->grouped($search); $zebraLabels = $groups['onTill']; $otherLabels = $groups['other']; $standAloneLabels = $groups['standalone'];`. Inject `ZebraLabelListService $zebraList` in the controller constructor.
Check: `GET /labels/zebra` as admin through the kernel → 200 before and after, and `grep -c "zebraList->" app/Http/Controllers/LabelAreaController.php` → 2 (step 2 adds the second).

### 2. JSON list for Shop mode
Files: `app/Http/Controllers/LabelAreaController.php`, `routes/web.php`
What: `zebraList()` → `{ labels: [...] }`, one flat array in the order onTill, other, standalone, each item `{ id, name, product_name, product_code, width_mm, height_mm, default_copies (int, null → 1), group: 'till'|'other'|'standalone', mismatches (as computed, or null), print_url: route('zebra-labels.print', $id) }`. Drop `fields` from the JSON. Route `Route::get('/labels/zebra/list', [LabelAreaController::class, 'zebraList'])->name('labels.zebra-list')->middleware('permission:labels.print');` next to `labels.zebra`.
Check: `php artisan route:list --name=labels.zebra-list` → labels.print; step 7 pins the shape.

### 3. Shop route and controller
Files: `app/Http/Controllers/Shop/ZebraController.php (new)`, `routes/web.php`, `resources/views/shop/labels.blade.php`
What: `ZebraController@index` → `view('shop.zebra', ['canCancel' => $request->user()->hasPermission('labels.manage')])`. Route in the `shop` group: `GET /zebra` → `shop.zebra`, `permission:labels.print`. On the print-labels screen the "Zebra labels" tile's `href` becomes `route('shop.zebra')` and the hint "Saved labels, counter printer"; `Shop\LabelsController` no longer needs `zebraUrl` (remove it and the variable).
Check: `php artisan route:list --name=shop.zebra` present; `ShopLabelsTest::employee_can_open_the_labels_screen` updated to expect `route('shop.zebra')`.

### 4. Behaviour: `zebra.js`
Files: `resources/js/shop/zebra.js (new)`, `resources/js/shop.js`
What: `Alpine.data('shopZebra')` reading `listUrl`, `queueUrl`, `cancelUrl` (empty when not allowed) from `data-*`; csrf from the meta tag. State: `labels = []`, `query = ''`, `selected = null`, `copies = 1`, `useTillPrice = true`, `useTillCountry = true`, `busy = false`, `loading = true`, `error = null`, `queue = null` (`{ ok, count }`), `toast = null`.
- `init()`: `load()` then `loadQueue()` (not awaited together; the queue call may take up to 15 s and must not block the list).
- `load()`: GET `listUrl` → `labels`; error → `error = 'Could not load labels'`.
- `matches(label)`: `query` trimmed, lower-cased; true when empty or contained in `name`, `product_name` or `product_code` (null-safe).
- Getters `onTill`, `other`, `standalone`: `labels.filter(l => l.group === … && matches(l))`; `shown` = sum of the three lengths.
- `onScan(code)`: exact `product_code === code` among `labels` (first match) → `open(label)`, dispatch `shop-scan-done`; none → `shop-scan-error` with "No Zebra label for this product".
- `open(label)`: `selected = label`, `copies = label.default_copies || 1`, `useTillPrice = useTillCountry = true`.
- `close()`: `selected = null`.
- `bump(n)`: `copies = Math.min(99, Math.max(1, copies + n))`.
- `overrides()`: object; if `selected.mismatches?.price && useTillPrice` → `[price.field_index] = price.new_field`; same for country. Empty object when nothing applies.
- `print()`: `busy = true`; POST `selected.print_url` with `{ copies, fields: overrides() }` (omit `fields` when empty); toast ok with `data.message` on 2xx, toast bad with `data.message || 'Print failed'` otherwise; on success `close()` and `loadQueue()`; `busy = false`.
- `loadQueue()`: GET `queueUrl` → `queue = { ok: data.success, count: data.count }`; error → `queue = { ok: false, count: 0 }`.
- `cancelAll()`: only when `cancelUrl`; POST `{ all: true }` → toast with `data.message`, then `loadQueue()`.
- `queueText`: getter: `queue === null` → 'Checking printer…'; `!queue.ok` → 'Printer not reachable'; `count === 0` → 'Printer queue empty'; else `count + (count === 1 ? ' job' : ' jobs') + ' waiting'`.
- `statusPill(label)`: `{ tone: 'muted', text: 'No product' }` for standalone; `{ tone: 'warn', text: 'Price differs' | 'Country differs' | 'Price + country differ' }` when mismatches; else `{ tone: 'ok', text: 'Matches till' }`.
- `printLabel` getter: `'Print ' + copies + (copies === 1 ? ' label' : ' labels')`.
- `showToast(tone, text)`, `announceDone()`, `announceError(msg)` as in `labels.js`.
Register `Alpine.data('shopZebra', zebra)` in `shop.js`.
Check: `node --check`; a node exercise with stubbed `$root.dataset` and `fetch` prints: three labels → `onTill 1 / other 1 / standalone 1`; `query = 'oat'` → `shown 1`; `open(priceMismatchLabel)` then `overrides()` → `{ "3": "\\152.43" }`; `useTillPrice = false` → `{}`; `bump(-5)` → `copies 1`; `bump(200)` → 99; `queueText` for `{ok:true,count:2}` → '2 jobs waiting'; `grep -c "route(" resources/js/shop/zebra.js` → 0.

### 5. The screen
Files: `resources/views/shop/zebra.blade.php (new)`
What: `<x-shop-layout title="Zebra labels" :back="route('shop.labels')">`, `<main class="shop-page" x-data="shopZebra()" data-list-url="{{ route('labels.zebra-list') }}" data-queue-url="{{ route('labels.printer-queue') }}" data-cancel-url="{{ $canCancel ? route('labels.printer-cancel') : '' }}" @scan="onScan($event.detail.code)">`:
- `<x-shop.scan-input placeholder="Scan a product to print its label" hint="Ready — scan a product, or search below" />`.
- Printer line: `shop-between` with `<span class="shop-meta" x-text="queueText">` and, when `$canCancel`, `<button class="shop-btn shop-btn--ghost" type="button" x-show="queue && queue.ok && queue.count > 0" x-cloak :disabled="busy" @click="cancelAll()">Cancel all</button>`.
- Print card, `<section class="shop-card" x-show="selected" x-cloak>`: header `shop-between` with `shop-subtitle` = `selected?.product_name || selected?.name`, meta `[selected?.name, size, selected?.product_code]` joined with ' · ' where size is `width × height mm` (skip nulls), ghost × `@click="close()"`. Then, when `selected?.mismatches?.price`: `<div class="shop-card shop-card--flat">` with text `'Label says €' + label_value + ', till says €' + db_value` and a `shop-switch` bound `x-model="useTillPrice"` whose visible label is "Print with till price". Same block for country ("Label says X, till says Y", "Print with till country", `useTillCountry`). Copies: `shop-label` "Copies" + `shop-stepper` (± buttons `bump(-1)`/`bump(1)`, value `copies`). Block primary button `:disabled="busy"` `@click="print()"` with `printer` icon and `x-text="printLabel"`.
- Search: `shop-search` with `search` icon and `<input class="shop-input" type="search" placeholder="Search labels" x-model="query" autocomplete="off">`.
- Three sections, each `x-show` when its group is non-empty: `shop-subtitle` "On the till" / "Other products" / "Standalone labels", then `shop-list` of `<template x-for="label in onTill" :key="label.id"><button class="shop-row" type="button" :class="{ 'is-latest': selected && selected.id === label.id }" @click="open(label)">` with `shop-row__main` (title `label.product_name || label.name`; meta = `[label.product_name ? label.name : null, size, label.product_code].filter(Boolean).join(' · ')`), `shop-row__aside` with `<span class="shop-pill" :class="'shop-pill--' + statusPill(label).tone" x-text="statusPill(label).text">`, and `shop-row__chev` chevron icon if the design has one (else omit).
- `shop-empty` "No labels" (`barcode` icon, "No saved Zebra labels match.") when `! loading && shown === 0 && ! error`; error block as on the labels screen.
- Toast region as on the labels screen.
Check: `php artisan test --filter=ShopViewContractTest` → eight screens, green.

### 6. Home badge / tiles: none
No Home change. The Print labels tile keeps its badge; Zebra is reached through it.

### 7. Tests
Files: `tests/Feature/Shop/ShopZebraTest.php (new)`, `tests/Feature/Shop/ShopLabelsTest.php`
Fixture: copy `ShopLabelsTest::setUp()` (POS `PRODUCTS`, `TAXES`, `TAXCATEGORIES`, two products; Oat drink PRICESELL 1.9756 with 23 % VAT → gross 2.43), add POS `PRODUCTS_CAT` (`PRODUCT` string, `CATORDER` nullable int) and `vegDetails` (`ID` string pk, `product`, `countryCode` nullable int, `classId`, `unitId` nullable) tables. Bind the fake `ZebraPrintService` exactly as `DeliveryTranslatedLabelPrintingTest` does, capturing the ZPL from the temp file named in the command. Labels (all `is_active` true, `created_by` a user id):
- "Oat front" on `5000000000017`, `zpl_content` = `^XA^FT30,60^A0N,28,28^FDOat drink 1 L^FS^FT30,120^A0N,28,28^FD\152.20^FS^PQ1^XZ`, `label_width_mm` 112.6, `label_height_mm` 75.1, `default_copies` 2; `PRODUCTS_CAT` row for `p1` (on the till).
- "Leeks" on `5000000000024`, ZPL with price `\151.50` (product PRICESELL 1.2195 → gross 1.50, so no mismatch), no `PRODUCTS_CAT` row.
- "Shelf talker" with no product, ZPL `^XA^FT30,60^A0N,28,28^FDFresh today^FS^PQ1^XZ`.
Tests:
- `employee_can_open_the_zebra_screen`: 200, `data-shell="shop"`, `data-list-url` = `route('labels.zebra-list')`, the scan input, "Copies", no "Cancel all", `data-cancel-url=""`.
- `manager_sees_cancel_all`: contains "Cancel all" and `data-cancel-url="` + `route('labels.printer-cancel')`.
- `barista_is_forbidden`: 403 on `shop.zebra` and on `labels.zebra-list`.
- `list_groups_labels_and_reports_mismatches`: GET `labels.zebra-list` → three items; Oat front `group` 'till', `default_copies` 2, `mismatches.price.label_value` '2.20', `db_value` '2.43', `new_field` '\152.43', `field_index` 1, `print_url` = `route('zebra-labels.print', $oat)`; Leeks `group` 'other', `mismatches` null; Shelf talker `group` 'standalone', `product_code` null; no item has a `zpl_content` key.
- `office_page_and_list_agree`: manager GET `route('labels.zebra')` → 200 and the page contains "Oat front" and "Shelf talker" (the service feeds both).
- `print_with_till_price_overrides_the_field_for_this_print_only`: employee POST `zebra-labels.print` for Oat front with `{ copies: 2, fields: { 1: '\152.43' } }` → 200 `success` true; captured ZPL contains `\152.43` and `^PQ2` and not `\152.20`; `ZebraLabel::find($oat->id)->zpl_content` still contains `\152.20`.
- `print_without_override_sends_the_saved_label`: POST `{ copies: 1 }` → captured ZPL contains `\152.20` and `^PQ1`.
- `labels_tile_links_to_the_shop_zebra_screen`: in `ShopLabelsTest`, update `employee_can_open_the_labels_screen` to expect `href="` + `route('shop.zebra')`.
Check: `php artisan test --filter="ShopZebraTest|ShopLabelsTest"` green (7 new tests).

### 8. README, format, build
Files: `docs/design/shop-mode/README.md`, all touched
What: README bullet: Zebra labels renders from `labels.zebra-list` (three groups, mismatches, per-label `print_url`), prints via the office `zebra-labels.print` with per-print `fields` overrides (never edits the saved label), reads the spool via `labels.printer-queue` after render, and offers "Cancel all" only to `labels.manage`. `./vendor/bin/pint --dirty`; `npm run build`.
Check: `./vendor/bin/pint --test --dirty` clean; build succeeds.

## Verification

1. `php artisan route:list --name=shop.zebra`, `--name=labels.zebra-list` → present, `labels.print`.
2. `php artisan test --filter=Shop` → green; the contract test lists eight screens.
3. `php artisan test` → 17 failed, the identical set; passed = 546 + 7.
4. `git diff app/Http/Controllers/LabelAreaController.php`: constructor injection, the `else` branch of `zebra()` reduced to the delegation, `zebraList()` added; `git diff --stat app/Http/Controllers/ZebraLabelController.php resources/views/labels/zebra.blade.php` → empty.
5. Contract greps, `grep -c "route(" resources/js/shop/zebra.js` → 0, design-block `cmp` → identical.
6. `./vendor/bin/pint --test --dirty` clean; `npm run build` succeeds.
7. Manual, on the till PC with the printer on, as an employee: Print labels → Zebra labels; the list shows the same three groups as the office page with the same status pills; scan a product that has a label → its card opens with copies preset; a label with a price mismatch shows "Label says … till says …" with the switch on; print 1 copy → the printer produces it with the till price, the toast says "Print job sent (1 copy)", and the office label is unchanged; switch off → the saved price prints; "Printer queue empty" appears once the job clears; as a manager, queue two jobs with the printer paused, "Cancel all" clears them. On the tablet the search box filters as you type and the on-screen keyboard is not suppressed for it.

## Risks

- **Printer calls block the PHP worker** for up to 15 s each (`lp`, `lpstat`). The list is a separate request, so the screen renders even when the printer host is down; only the status line waits. Two people printing at once queue behind each other for seconds, which is what the office page does today.
- **Country mismatch with no `vegDetails` row** is skipped by the existing code (`$dbCountry` null); the fixture has no rows, so only price mismatches are tested. Fine: the logic is untouched, only moved.
- **`replaceTextFields` indexes** are positional over fields with a font; the JSON carries the index the service computed from the same ZPL, so the override lands on the right field as long as the saved ZPL is unchanged between list and print (a manager editing the label mid-shift is the only way it drifts; the worst case is the wrong field text on one print).
- **`default_copies` null** for almost every label: coerced to 1 in the JSON, matching the office (`?? 1`).
