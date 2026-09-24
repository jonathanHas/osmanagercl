# Shop mode cycle 9 — Delivery summary and completion (screen 06)

Status: ACCEPTED
Revision: 3
Planner: Fable 5.1
Date: 2026-09-24

## Goal

Let staff finish a delivery without leaving Shop mode: a summary screen that totals what was scanned (OK, Short, Over, Unexpected, Not scanned), lists every discrepancy, and completes the delivery after one clear confirmation, writing stock exactly as the office "Update Stock & Complete" button does. The scan screen gets its "Summary" action, and the Enter-to-confirm on the scan prompt is made correct by construction rather than by a guard. Manager tools (undo, financials, reports) stay on the office page.

## Context

Baseline: cycle 8 accepted at Revision 2 and archived (`docs/planImp/archive/2026-09-24-shop-mode-cycle-8/`); the owner may or may not have committed since `31c0bc18`. Record `git status --short` at the start and separate any pre-existing dirt as before.

**What exists from cycle 8.** `DeliveryLegacyController::items()` (JSON: `session { id, supplier, date, completed }`, `rows[] { barcode, code, name, expected, scanned, status ∈ ok|short|over|unexpected|not_scanned }`, `progress { total, checked, issues }`), route `delivery-legacy.items`. `Shop\DeliveryController@index` (`shop.deliveries`) and `@scan` (`shop.deliveries.scan`, query `delID`, `supplierID`). Views `resources/views/shop/deliveries.blade.php` and `delivery-scan.blade.php`; the scan view's `<main>` carries `@keydown.enter.window="pending && ! $event.target.value && commit()"` and has no `shop-actions` bar yet. `resources/js/shop/delivery-scan.js` (`shopDeliveryScan`: `pending`, `onScan()`, `bump()`, `commit()` guarded by `busy`, `cancelPending()`, `load()`, `reportRow()`). `resources/js/shop/scan-input.js`: `submit()` returns early on an empty value (line ~57) and otherwise clears the value then dispatches `scan`. Test trait `tests/Concerns/CreatesLegacyDeliveryPosTables.php` with `seedLegacyDelivery()` (session `d-1`, supplier `999`, products `5000000000017` expected 12 scanned 12 and `5000000000024` expected 8 scanned 6, unknown `4260009912200` scanned 3; `STOCKCURRENT` rows for `p1`/`p2`).

**Completion on the office page.** `DeliveryLegacyController::completeDelivery()` (find with `grep -n "public function completeDelivery"`): validates `delID`, `supplierID`; inside a POS transaction increments `STOCKCURRENT.UNITS` by `scanned` for every matched and extra item with `scanned > 0` and a `productID`, counting `productsUpdated` and `unitsAdded`; sets `deliveriesScan.status = 1`; redirects to the office match page with a success flash and `updateResults`. The office button is a plain POST form with a browser `confirm()` ("This will update stock levels for all scanned items and mark this delivery as complete. This action cannot be undone."). Undo (`undoComplete`) is manager-only (`deliveries.manage`).

**Design.** `docs/design/shop-mode/screen-06-delivery-summary.html`: `shop-page--narrow`; header `shop-subtitle` "Supplier · reference" with `shop-meta` "Delivered … · N items expected"; a `shop-totals` grid of `shop-total` cards (`shop-label` + `shop-total__value`): Scanned, OK (`--ok`), Short (`--warn`), Extra, Unexpected (`--bad`); a "Discrepancies" `shop-list` of `shop-row`s (title, meta "Expected 8 · scanned 6", aside pill "Short 2" `--warn` / "Extra 1" `--muted` / "Unexpected" `--bad`); a note `textarea` (no backend exists, so it is dropped); `shop-actions` with "Keep scanning" (secondary) and "Complete delivery" (primary, check icon). Classes exist in the design block; the confirmation card uses `shop-card`, `shop-facts`, `shop-btn--danger`/`--secondary`.

**Contract and traps** as in cycles 3–8: no `<script>`/`<style>`/utilities in views; `shop-*`/`is-*` only in `:class`; never an Alpine `@` shorthand that is a Blade directive; rendering feature tests as the markup check; `APP ADDITIONS` only in the stylesheet; URLs via `data-*`; read `implemented.md` to the end on review.

**Baseline suite:** 17 failed / 515 passed, the same 17 (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `WasteLogTest` ×2, `ProductTest` ×2, `FruitVegLabelPrintingTest` ×2, `TestScraperControllerTest` ×1).

## Constraints

- Do not commit, push or deploy.
- `completeDelivery()` gains only a guarded `return=shop` redirect branch, exactly like `createSession()` did in cycle 8; its validation, transaction and stock writes are untouched. No other legacy controller or office view change.
- Completing requires an explicit second tap on a confirmation that states the stock effect; no browser `confirm()`.
- A completed session's summary is read-only (no Complete button) and the scan screen already refuses scans for it.
- Design/content contract, stylesheet rule and test conventions as before.

## Out of scope

- Undo (manager-only, office page). Financials, deviation report, goods-return sheet, translated labels.
- The note textarea from the design (no backend).
- Editing quantities on the summary (use the scan screen's row stepper).
- Extracting the legacy queries into a service (still deferred).

## Steps

### 1. Completion lands in Shop mode
Files: `app/Http/Controllers/DeliveryLegacyController.php`
What: in `completeDelivery()`, keep everything up to and including the transaction; before the existing `return redirect()->route('delivery-legacy.match', …)`, add: if `$request->input('return') === 'shop'`, `return redirect()->route('shop.deliveries')->with('success', "Delivery completed. {$updateResults['unitsAdded']} units added to stock for {$updateResults['productsUpdated']} products.")`. The office form sends no `return`, so its behaviour is unchanged.
Check: `git diff app/Http/Controllers/DeliveryLegacyController.php` shows only the added branch (about 8 lines, no removals); the test in step 6 covers both branches.

### 2. Summary route, controller and JSON reuse
Files: `app/Http/Controllers/Shop/DeliveryController.php`, `routes/web.php`
What: add `summary(Request $request)` mirroring `scan()` (validate `delID`/`supplierID`, load the session joined to `suppliers`, 404 if missing) returning `view('shop.delivery-summary', compact('session'))`. Route in the existing `deliveries.process` sub-group: `GET /deliveries/summary` → `summary`, name `deliveries.summary`. The page reads its rows from `delivery-legacy.items` client-side, like the scan screen.
Check: `php artisan route:list --name=shop.deliveries.summary` → present with `deliveries.process`.

### 3. Behaviour: `delivery-summary.js`
Files: `resources/js/shop/delivery-summary.js (new)`, `resources/js/shop.js`
What: `Alpine.data('shopDeliverySummary')`, reading `itemsUrl`, `delId`, `supplierId` from `data-*`. State: `session = null`, `rows = []`, `progress = null`, `loading = true`, `error = null`, `confirming = false`. `init()` → `load()` (GET `itemsUrl?delID&supplierID`). Getters:
- `totals` → `{ scanned: rows with scanned > 0, ok, short, over, unexpected, missing: rows with status not_scanned }` (counts of rows).
- `unitsToAdd` → sum of `scanned` over rows with `scanned > 0` (this is what completion adds to stock; it counts unexpected items too, as `completeDelivery()` does).
- `productsToUpdate` → count of rows with `scanned > 0`.
- `discrepancies` → rows with status `short`, `over`, `unexpected` or `not_scanned`, in that order, each with `label(row)`: short → `Short ${expected - scanned}`, over → `Over ${scanned - expected}`, unexpected → `Unexpected`, not_scanned → `Not scanned`; `tone(row)`: short/over → `shop-pill--warn`, unexpected → `shop-pill--bad`, not_scanned → `shop-pill--muted`; `meta(row)`: `Expected ${expected ?? '—'} · scanned ${scanned ?? 0}`.
- `canComplete` → `session && ! session.completed && totals.scanned > 0`.
Methods: `askToComplete()` → `confirming = true`; `cancelComplete()` → `confirming = false`. Register in `shop.js` inside `alpine:init`.
Check: a node exercise with a fixture of six rows (two ok, one short, one over, one unexpected, one not_scanned; scanned units 12, 6, 10, 3, and 0/null) prints `totals {scanned:4, ok:2, short:1, over:1, unexpected:1, missing:1}`, `unitsToAdd 31`, `productsToUpdate 4`, four discrepancies in the order short, over, unexpected, not_scanned with the labels above; `grep -c "route(" resources/js/shop/delivery-summary.js` → 0.

### 4. The summary view
Files: `resources/views/shop/delivery-summary.blade.php (new)`
What: `<x-shop-layout title="Delivery summary" :back="route('shop.deliveries.scan', ['delID' => $session['id'], 'supplierID' => $session['supplierId']])">` → `<main class="shop-page shop-page--narrow" x-data="shopDeliverySummary()" data-items-url="{{ route('delivery-legacy.items') }}" data-del-id="{{ $session['id'] }}" data-supplier-id="{{ $session['supplierId'] }}">`:
- Header `shop-stack shop-stack--tight`: `<h2 class="shop-subtitle">{{ $session['supplier'] }} · {{ substr($session['id'], 0, 8) }}</h2>`, `<p class="shop-meta">` with the session date and `<span x-text="progress ? progress.total + ' items expected' : ''">`.
- `shop-card shop-card--flat` notice `x-show="session && session.completed" x-cloak`: "This delivery is completed." with a `shop-pill shop-pill--ok` "Completed".
- `shop-totals`: five `shop-total`s bound to `totals.scanned`, `totals.ok` (`--ok`), `totals.short` (`--warn`), `totals.over` (label "Over"), `totals.unexpected` (`--bad`); plus a sixth "Not scanned" (`shop-total--warn` only when `totals.missing > 0`, via `:class`).
- "Discrepancies" section: `shop-label` heading; `shop-list` with `<template x-for="row in discrepancies" :key="row.barcode">` → `<div class="shop-row">` (title `row.name`, meta `meta(row)`, aside pill `:class="tone(row)"` `x-text="label(row)"`); `shop-empty` "Everything matches the invoice" (check icon) when `discrepancies.length === 0 && ! loading`.
- Confirmation card `x-show="confirming" x-cloak x-ref="confirm"`: `shop-card` with `shop-subtitle` "Complete this delivery?", `shop-facts shop-facts--2` (Units to add → `unitsToAdd`, Products → `productsToUpdate`), `shop-meta` "Stock is updated now and the session is closed. Undo is only available to a manager on the office page.", and a `<form method="POST" action="{{ route('delivery-legacy.complete') }}">` with `@csrf`, hidden `delID`, `supplierID`, `return=shop`, and `shop-inline` of `<button type="button" class="shop-btn shop-btn--secondary shop-btn--lg" @click="cancelComplete()">Not yet</button>` and `<button type="submit" class="shop-btn shop-btn--danger shop-btn--lg"><x-shop.icon name="check" />Yes, complete</button>`.
- `shop-actions`: `<a class="shop-btn shop-btn--secondary shop-btn--lg" href="{{ route('shop.deliveries.scan', [...]) }}">Keep scanning</a>` and `<button type="button" class="shop-btn shop-btn--primary shop-btn--lg" x-show="canComplete" x-cloak @click="askToComplete(); $nextTick(() => $refs.confirm.scrollIntoView({ block: 'nearest' }))"><x-shop.icon name="check" />Complete delivery</button>`.
- Error `shop-empty` on `error`, as on the other screens.
Check: `php artisan test --filter=ShopViewContractTest` → six screens, green; the rendering test in step 6 passes.

### 5. Scan screen: Summary action and Enter by construction
Files: `resources/views/shop/delivery-scan.blade.php`, `resources/js/shop/scan-input.js`, `resources/js/shop/delivery-scan.js`, `resources/views/components/shop/scan-input.blade.php` (only if needed), `docs/design/shop-mode/README.md`
What:
- Add a `shop-actions` bar at the end of `<main>` on the scan screen: secondary `<a … href="{{ route('shop.deliveries') }}">Deliveries</a>`, primary `<a class="shop-btn shop-btn--primary shop-btn--lg" href="{{ route('shop.deliveries.summary', ['delID' => $session['id'], 'supplierID' => $session['supplierId']]) }}">Summary<span class="shop-btn__count" x-show="progress.issues > 0" x-text="progress.issues"></span></a>`.
- Enter by construction: in `scan-input.js` `submit()`, when the parsed value is empty, `this.$dispatch('scan-empty')` and return (instead of a silent return). In `delivery-scan.blade.php` replace `@keydown.enter.window="pending && ! $event.target.value && commit()"` on `<main>` with `@scan-empty="pending && commit()"`. Now a typed barcode reaches `commit()` exactly once (through `onScan`), and an empty Enter exactly once (through `scan-empty`); the `busy` guard in `commit()` stays as belt-and-braces. Stock scan is unaffected (it does not listen for `scan-empty`). README: add `scan-empty` to the scan-input event list and the note that Enter on an empty input confirms an open delivery prompt.
Check: node exercise of `scan-input.js` `submit()` with `value = ''` records one `scan-empty` dispatch and no `scan`; with `value = '5000000000017'` records one `scan` and no `scan-empty`. `grep -c "keydown.enter.window" resources/views/shop/delivery-scan.blade.php` → 0; `grep -c "scan-empty" resources/views/shop/delivery-scan.blade.php` → 1.

### 6. Tests
Files: `tests/Feature/Shop/ShopDeliveryTest.php`
What: add, using the trait's fixture:
- `summary_screen_renders`: employee → GET `shop.deliveries.summary` for `d-1`/`999` → 200, contains `data-shell="shop"`, `data-items-url`, "Discrepancies", "Complete delivery", "Keep scanning", a Back link to the scan URL, and the hidden `return` input with value `shop`.
- `summary_is_forbidden_for_a_barista`: 403.
- `scan_screen_links_to_the_summary_and_has_no_window_enter_handler`: the scan page contains `href="` + the summary URL, "Summary", and `@scan-empty="pending && commit()"`, and does not contain `keydown.enter.window`.
- `completing_from_shop_updates_stock_and_returns_to_the_list`: POST `delivery-legacy.complete` `{ delID: 'd-1', supplierID: '999', return: 'shop' }` → redirect to `route('shop.deliveries')` with a `success` flash containing "18 units" (12 + 6; the unknown barcode has no product so adds nothing) and "2 products"; `deliveriesScan.status` for `d-1` is 1; `STOCKCURRENT.UNITS` for `p1` rose by 12 and for `p2` by 6 (read the seeded values first and assert the deltas). Without `return`: redirect to `route('delivery-legacy.match', [...])`.
- `completed_summary_has_no_complete_button`: after setting `d-1` to status 1, the summary page's Complete button is present in markup but gated by `x-show="canComplete"` — assert instead that the notice text "This delivery is completed" is present (client-side gating cannot be asserted server-side; say so in the test docblock).
Check: `php artisan test --filter=ShopDeliveryTest` → 14 passed.

### 7. Build and format
Files: all touched
What: `npm run build`; `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean; `npm run build` succeeds.

## Verification

1. `php artisan route:list --name=shop.deliveries` → `deliveries`, `deliveries.scan`, `deliveries.summary`, all with `deliveries.process`.
2. `php artisan test --filter=Shop` → all green; the contract test lists six screens.
3. `php artisan test` → 17 failed / N passed, the identical 17.
4. `git diff app/Http/Controllers/DeliveryLegacyController.php` (against the cycle 8 state if uncommitted, or HEAD if committed) shows only the `return=shop` branch in `completeDelivery()`; `git diff --stat resources/views/delivery-legacy/` → empty.
5. `grep -rn "<script\|<style\|keydown.enter.window" resources/views/shop/` → nothing; `grep -c "route(" resources/js/shop/delivery-summary.js` → 0.
6. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL` → prints it.
7. `./vendor/bin/pint --test --dirty` → clean; `npm run build` → success.
8. Manual, signed in as an employee, on a test session with a synced invoice: scan a few items on the Shop scan screen → tap "Summary" (badge shows the issue count) → totals and discrepancy rows match the office match page for the same session → "Keep scanning" returns to the scan screen → "Complete delivery" shows the confirmation with units and product counts → "Not yet" hides it → "Yes, complete" returns to the delivery list with the green flash, the session now under "Recently completed", and the office page shows the same session as completed with stock increased; reopening its summary shows the completed notice and no Complete button. On the scan screen: Enter on the empty input confirms an open prompt once; a typed barcode plus Enter while a prompt is open adds the first item once and opens the second.

## Risks

- **Completion is irreversible for staff.** The confirmation states units and products and that undo is manager-only; the danger-styled button is the design's convention for irreversible actions.
- **Totals from the client.** The summary's unit count is computed from `items()` rows, which `completeDelivery()` also derives from the same queries; the test asserts the flash figures match the seeded deltas so the two cannot silently disagree.
- **`scan-empty` is a new event on a shared component.** Stock scan ignores it; Find product does not use the component. README records it.
- **Session id in the header** is shown as its first 8 characters, as on the list.

## Review (Revision 1 → 2)

Reviewed 2026-09-24 by the Planner against the full `implemented.md` (to the end), the diffs and a rerun of the checks.

Steps 1–7: all PASS as written. The completion branch is a pure 8-line addition before the office redirect; three `shop.deliveries*` routes behind `deliveries.process`; the summary JS reproduces the plan's fixture figures and the extra branches; the summary view and the scan screen's actions bar render; `scan-empty` is dispatched on an empty or whitespace-only value and the window Enter handler is gone; six new tests (15 in the class); build and format clean; no stylesheet change needed.

Verification rerun by the Planner: three routes present; `--filter=Shop` 122 passed; full suite 17 failed / 522 passed, the identical 17; no `<script>`, `<style>` or `keydown.enter.window` in the shop views; office delivery views untouched; design block byte-identical.

Deviations 1–4: accepted. Deviation 2 matters: the plan's single completion test would have completed the same session twice, and the implementer proved that adds the stock twice (3 → 15 → 27). Splitting the test was right, and it exposed the defect handled below.

Notes for Planner, each decided:
- **`completeDelivery()` is not idempotent**, and Shop mode now puts the button in front of 15 employees; a back-button resubmit, a double tap, or two devices on one session would double the stock. The Revision 1 constraint forbade touching the method beyond the redirect; that constraint was wrong given this finding. **Fixed now: step 8.**
- **`unitsToAdd` can exceed what completion writes** (rows without a `productID` are summed on screen but skipped on write). **Fixed now: step 9.**
- `scan-empty` fires on every screen using the component: **accepted**, documented.
- The design's note box is still absent: **deferred to the owner**; nothing stores it today.
- Cycles 8 and 9 mixed in one dirty tree: **for the owner**; commit recommended.

Result: **READY, Revision 2.** The Implementer starts a fresh `implemented.md` for Revision 2 covering steps 8–9 only; steps 1–7 are accepted as delivered.

### 8. Completion refuses to run twice
Files: `app/Http/Controllers/DeliveryLegacyController.php`, `tests/Feature/Shop/ShopDeliveryTest.php`
What: at the top of `completeDelivery()`, after validation and before any stock write, load the session (`deliveriesScan` where `ID = $delID`) and, if it is missing or `(int) $session->status === 1`, return without touching stock: `return redirect()->back()->with('error', 'This delivery is already completed.')` (for `return=shop` the back URL is the Shop summary; for the office page it is the match page, so `back()` serves both). Everything below the guard is unchanged. This closes the double-write on the office page as well.
Test `completing_twice_does_not_add_stock_twice`: complete `d-1` with `return=shop` once (stock deltas as in the existing test), then POST the same completion again → redirect back with the `error` flash, `STOCKCURRENT.UNITS` unchanged after the second call, `deliveriesScan.status` still 1. Also assert that `completing_from_shop_updates_stock_and_returns_to_the_list` still passes (the guard must not trip on a fresh session).
Check: `php artisan test --filter=ShopDeliveryTest` → 16 passed; `git diff app/Http/Controllers/DeliveryLegacyController.php` shows the guard block added inside `completeDelivery()` and nothing removed.

### 9. The summary counts only what completion will write
Files: `app/Http/Controllers/DeliveryLegacyController.php` (`items()`), `resources/js/shop/delivery-summary.js`, `tests/Feature/Shop/ShopDeliveryTest.php`
What: in `items()`, add `'stockable' => ! empty($item->productID)` to both row shapes (matched rows always have one when the supplier link resolved to a product; unmatched extra rows may not). In `delivery-summary.js`, `unitsToAdd` and `productsToUpdate` sum and count only rows with `scanned > 0 && stockable`; `totals` and `discrepancies` are unchanged (an unexpected, unstockable scan is still a discrepancy). In the confirmation card, when any scanned row is not stockable, show a `shop-meta` line: "N scanned items are not in the product list and will not be added to stock." (`unstockableCount` getter).
Tests: extend `items_endpoint_classifies_the_session` to assert `stockable` true for `5000000000017` and false for `4260009912200`; a node exercise of the summary getters with the fixture from step 3 plus `stockable` flags (unknown row false) prints `unitsToAdd 28`, `productsToUpdate 3`, `unstockableCount 1`.
Check: `php artisan test --filter=ShopDeliveryTest` green; the node output above; `grep -c "stockable" resources/js/shop/delivery-summary.js` ≥ 2.

## Review (Revision 2 → 3)

Reviewed 2026-09-24 by the Planner against the full Revision 2 `implemented.md`, the diffs and a rerun of the checks.

Steps 8–9: both PASS. The guard sits after validation and before any query that feeds a write; the double-completion test asserts the sequence 3 → 15 → 15 against the after-first figures, which is the only version of that assertion that means anything; `stockable` is emitted by `items()` and drives `unitsToAdd`, `productsToUpdate` and the new `unstockableCount` line; 16 tests in the class; `--filter=Shop` 123 passed; full suite 17 failed / 523 passed, the identical 17; formatter clean; design block byte-identical.

Deviations: none. The two choices recorded (sequence assertion, shared `stockableScanned` getter) are improvements.

Notes for Planner, each decided:
- Guard reopens after `undoComplete()`: **accepted**, correct by design; recorded here so nobody later "fixes" it into a permanent lock.
- **Shop mode renders no flash messages, and the office match page renders `success` but not `error`.** Verified by the Planner (`grep -rn "session('" resources/views/shop/ resources/views/layouts/shop.blade.php` → nothing; `match.blade.php` has `session('success')` at line 434 and no `session('error')`). So the completion confirmation, the "session created" note and the new refusal are all invisible in Shop mode, and the refusal is invisible on the office match page. This is the decision the owner asked about. **Decided: fixed now, steps 10–12.** Reasoning: a silent bounce after an irreversible action is worse than no guard message at all, the design already provides the toast pattern, and the change is one block in one layout plus one banner on one office page. It retroactively completes cycles 8 and 9's acceptance criteria that mentioned a green or red message.
- `redirect()->back()` has no fallback: **fixed now, step 12.**
- Commit: **for the owner**, recommended after this revision is accepted.

Result: **READY, Revision 3.** The Implementer starts a fresh `implemented.md` covering steps 10–12 only; steps 1–9 are accepted as delivered.

### 10. Server flash messages in the Shop layout
Files: `resources/views/layouts/shop.blade.php`, `tests/Feature/Shop/ShopHomeTest.php`
What: inside `<div class="shop" id="shop-root">`, directly after the `@unless($bare) … @endunless` top bar block and before `{{ $slot }}`, add:
```blade
@if (session('success') || session('error'))
    <div class="shop-toasts" role="status" x-data="{ open: true }" x-show="open" x-init="setTimeout(() => open = false, 8000)">
        <div class="shop-toast {{ session('error') ? 'shop-toast--bad' : 'shop-toast--ok' }}">
            <span class="shop-toast__icon"><x-shop.icon :name="session('error') ? 'alert' : 'check'" size="sm" /></span>
            <span class="shop-toast__text">{{ session('error') ?? session('success') }}</span>
            <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Dismiss" @click="open = false"><x-shop.icon name="x" /></button>
        </div>
    </div>
@endif
```
The layout is not scanned by the contract test, so the inline `x-data` is allowed there (as the touch-detect script already is). `.shop-toasts` is fixed at the bottom of the viewport in the design block; `.shop-toast` has `pointer-events: auto`, so the dismiss button works. When a page also has its own client-side `shop-toasts` (Stock scan, Delivery scan), the two can only overlap during the first eight seconds after a redirect, before any user action; acceptable.
Test in `ShopHomeTest`: `flash_success_is_shown_as_a_toast`: `$this->actingAs($manager)->withSession(['success' => 'Delivery completed. 18 units added.'])->get('/shop')` → sees the text and `shop-toast--ok`; `flash_error_is_shown_as_a_bad_toast`: `withSession(['error' => 'This delivery is already completed.'])` → sees the text and `shop-toast--bad`; and `/shop` without a flash does not contain `shop-toasts` (Home has no client toast region, so the absence is assertable there).
Check: `php artisan test --filter=ShopHomeTest` → 9 passed; `grep -c "session('error')" resources/views/layouts/shop.blade.php` → 2 or 3 (the condition and the ternaries).

### 11. The office match page shows the refusal
Files: `resources/views/delivery-legacy/match.blade.php`
What: immediately before the existing `@if(session('success'))` block (line ~434), add an `@if(session('error'))` block with the same markup and the red classes the office index already uses (`resources/views/delivery-legacy/index.blade.php` lines 5–9: `bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded`, with a `mb-4`). Nothing else on the page changes. This is the one office-view edit of the cycle, allowed because the guard from step 8 now produces a message that page must be able to show.
Check: `git diff --stat resources/views/delivery-legacy/` shows only `match.blade.php` with a handful of added lines and no removals; `grep -c "session('error')" resources/views/delivery-legacy/match.blade.php` → 1.

### 12. Deliberate fallback for the refusal's redirect
Files: `app/Http/Controllers/DeliveryLegacyController.php`, `tests/Feature/Shop/ShopDeliveryTest.php`
What: in the guard added by step 8, replace `redirect()->back()` with `redirect()->back(302, [], $fallback)` where `$fallback` is `route('shop.deliveries.summary', ['delID' => $delID, 'supplierID' => $supplierID])` when `$request->input('return') === 'shop'`, else `route('delivery-legacy.match', ['delID' => $delID, 'supplierID' => $supplierID])`. With a referer present `back()` still uses it; without one the person lands on the right page instead of `/`.
Test: extend `completing_twice_does_not_add_stock_twice` (or add a sibling) so the second POST is made without a referer header and asserts `assertRedirect(route('shop.deliveries.summary', [...]))` for `return=shop`, and a further POST without the flag asserts the office match URL.
Check: `php artisan test --filter=ShopDeliveryTest` → 16 or 17 passed, all green.

## Review (Revision 3)

Reviewed 2026-09-24 by the Planner against the full Revision 3 `implemented.md`, the five changed files and a rerun of the checks.

Steps 10–12: all PASS. Flash block in the layout between the top bar and the slot, dismissable, auto-hiding, with three layout tests; a six-line pure addition on the office match page; the guard's redirect falls back to the Shop summary or the office match page and a referer-less test pins both. `--filter=Shop` 127 passed; full suite 17 failed / 527 passed, the identical 17; formatter clean; no `<script>`/`<style>` in shop views; design block byte-identical.

Deviations: none. The two `grep -c` expectations in the plan undercounted the plan's own snippets; the implementer was right to say so rather than trim code to match.

Live walkthrough (implementer, in the owner's browser, with the owner's authorisation, on session `da16f9e0`): case scan showed "Case of 4"; empty Enter committed once; unknown barcode refused; row stepper writes; confirmation figures 23 units / 2 products; completion wrote +19 and +4 and closed the session; the browser-back resubmit (page restored from cache with a live button) was refused with the red toast and stock did not move; office page agrees. This is the first full hardware-and-browser confirmation since cycle 4 and it covered the one irreversible path.

Notes for Planner, each decided:
- Flash keys now load-bearing across two shells: **accepted**.
- Toast dismissed by time or tap only, no queue: **accepted**.
- Overlapping toast regions (server flash plus a page's client toast within 8 s): **deferred**; fix with a `shop-toasts--flash` offset in `APP ADDITIONS` only if it looks wrong on a real screen.
- `ShopHomeTest` doubles as the layout test: **accepted**, noted for whoever gives Home a client toast later.
- **Defect in cycle 8's delivery list**: the query takes the 50 most recent sessions and then filters to open, so with 1,201 sessions an open session from May is counted by the Home badge but never listed. **Fixed in the next plan** (`docs/planImp/plan.md` after archiving), not here: it is cycle 8 code and outside Revision 3's steps.
- Cosmetic: "0 of 0 items · 2 issues" when no invoice lines are loaded, and the client toast always showing a tick even for warnings: **fixed in the same follow-up plan**.
- Commit: **for the owner, now**. Three revisions of two cycles sit in one tree.

Result: ACCEPTED. Archived by the Planner to `docs/planImp/archive/2026-09-24-shop-mode-cycle-9/`.

### Verification for Revision 3
Items 1–7 of the original Verification unchanged (expect `--filter=Shop` green with 126 or 127 tests; full suite 17 failed / the matching count). Item 8: complete a test session from Shop mode → the green toast with the unit and product counts appears on the delivery list and can be dismissed; start a delivery → the "session created" toast appears on the scan screen; press back and resubmit a completion → the red "already completed" toast appears on the summary and stock has not moved; do the same on the office match page → a red banner appears above the page. `git diff --stat resources/views/delivery-legacy/` shows only the match page banner.

### Verification for Revision 2
Items 1–7 of the original Verification unchanged (expect `--filter=Shop` green with 123 tests; full suite 17 failed / 523 passed). Item 8 additionally: on a test session, complete it from Shop mode, then press the browser's back button and resubmit the confirmation form → the red "already completed" message appears and stock has not moved again (check the product on the office page or in Find product); and a session that includes a scan of a barcode unknown to POS shows the "will not be added to stock" line on the confirmation with a unit total that excludes it, matching the green flash after completion.
