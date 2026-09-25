# Shop mode cycle 9b — delivery list completeness and two cosmetic fixes

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-24

## Goal

Close three findings from cycle 9's live walkthrough. One is a real defect from cycle 8: the Shop delivery list can hide an open session that the Home badge counts, so staff could not reach it without the office page. Two are cosmetic: a scan screen with no invoice lines loaded reads "0 of 0 items · 2 issues", and the client-side toasts on the scan screens always show a tick, even for warnings. Small, contained, no new screens.

## Context

Baseline: cycle 9 accepted at Revision 3 and archived (`docs/planImp/archive/2026-09-24-shop-mode-cycle-9/`). Cycles 8 and 9 may still be uncommitted; record `git status --short` at the start and separate pre-existing dirt as before.

**The list defect.** `app/Http/Controllers/Shop/DeliveryController.php::index()` (lines ~30–52) selects the 50 most recent `deliveriesScan` rows by `dateUpload` (`->limit(50)`, line 39) and only then splits them into `$open` (`status 0`) and `$completed` (`status 1`, `take(10)`). On the real database (1,201 sessions) the window reaches back only to mid-June, so an open Imbibe session from 20 May is invisible while `ShopHomeController::openDeliveryCount()` (unwindowed `where status 0`) counts it: badge 6, list 5. There are only ever a handful of open sessions, so they need no limit; the completed list is what needs windowing.

**The zero-items wording.** `resources/views/shop/delivery-scan.blade.php` line 69 renders `<strong>checked</strong> of <total> items` and line 73 colours the bar; `delivery-summary.blade.php` line 11 renders `total + ' items expected'`. When the supplier has no rows in the POS `delivery` scratch table (invoice not synced, or a different supplier synced since), `total` is 0 while scans still produce `issues`, so the text reads "0 of 0 items · 2 issues". The scan screen already shows a `shop-empty` "No invoice lines for this supplier are loaded. Scans are still recorded." when `rows.length === 0`, but not when the only rows are unexpected ones.

**The toast icon.** `stock-scan.blade.php` line 89 and `delivery-scan.blade.php` line 146 hard-code `<x-shop.icon name="check" size="sm" />` inside `.shop-toast__icon`, while the tone class (`shop-toast--ok|--warn|--bad`) is bound to `toast.tone`. The layout's server flash (cycle 9) already picks `alert` for errors. Sprite ids available: `check`, `alert`, `x`.

**Tests.** `tests/Concerns/CreatesLegacyDeliveryPosTables.php` (`seedLegacyDelivery()` seeds one open session `d-1`); `tests/Feature/Shop/ShopDeliveryTest.php` (17 tests). Baseline suite: 17 failed / 527 passed, the same 17 (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `WasteLogTest` ×2, `ProductTest` ×2, `FruitVegLabelPrintingTest` ×2, `TestScraperControllerTest` ×1).

## Constraints

- Do not commit, push or deploy.
- Only `Shop\DeliveryController`, the two scan-side views, the stock-scan view, `ShopDeliveryTest` and (if a class is needed) `APP ADDITIONS` change. No legacy controller or office view change.
- Contract rules as before (no `<script>`/`<style>`/utilities in shop views; `shop-*`/`is-*` only in `:class`; rendering tests as the markup check).

## Out of scope

- Overlapping toast regions (deferred from cycle 9 until seen).
- Any change to how the badge counts (it is correct; the list was wrong).
- Pagination of completed sessions beyond the last 10.

## Steps

### 1. Open sessions are never windowed
Files: `app/Http/Controllers/Shop/DeliveryController.php`, `tests/Feature/Shop/ShopDeliveryTest.php`
What: in `index()`, replace the single windowed query with two: `$open` = all sessions with `status = 0`, joined to `suppliers`, `orderByDesc('dateUpload')`, **no limit**; `$completed` = sessions with `status = 1`, same join and order, `limit(10)`. Compute `$counts` for the union of both sets' ids (the existing per-session `COUNT(*)`/`SUM(quantity)` query, `whereIn('delID', $ids)`). The view is unchanged.
Test `old_open_sessions_are_listed`: after `seedLegacyDelivery()`, insert 60 completed sessions with `dateUpload` in the last week and one open session `d-old` (supplier `999`) with `dateUpload` 120 days ago; GET `shop.deliveries` → sees `d-old`'s scan link (`route('shop.deliveries.scan', ['delID' => 'd-old', 'supplierID' => '999'])`) and shows "2 open"; the "Recently completed" section shows at most 10 rows (`substr_count` of `shop-pill--ok">Completed` ≤ 10).
Check: `php artisan test --filter=ShopDeliveryTest` → 18 passed; on the dev database `php artisan tinker --execute="echo \DB::connection('pos')->table('deliveriesScan')->where('status',0)->count();"` equals the number of rows under "Open deliveries" when the page is loaded (compare by eye; the implementer can render the page through the kernel as an employee and count `shop.deliveries.scan` links).

### 2. Honest wording when no invoice lines are loaded
Files: `resources/views/shop/delivery-scan.blade.php`, `resources/views/shop/delivery-summary.blade.php`, `resources/js/shop/delivery-scan.js`, `resources/js/shop/delivery-summary.js`
What: add a getter `hasInvoice` → `progress && progress.total > 0` to both page data objects. Scan screen: wrap the `<strong>checked</strong> of <total> items` span in `x-show="hasInvoice"` and add a sibling `<span x-show="! hasInvoice" x-cloak>No invoice lines loaded</span>`; keep the issues count; hide the progress bar (`x-show="hasInvoice"`) when there is no invoice. Leave the existing `shop-empty` (shown when `rows.length === 0`) as it is, and add a `shop-meta` line above the list, `x-show="! hasInvoice && rows.length" x-cloak`: "No invoice lines are loaded for this supplier; everything below was scanned but cannot be checked." The unexpected rows must stay listed. Summary: line 11 becomes `x-text="hasInvoice ? progress.total + ' items expected' : 'no invoice lines loaded'"`.
Check: a node exercise sets `progress = { total: 0, checked: 0, issues: 2 }` and prints `hasInvoice false`, then `{ total: 42, … }` → `true`; `php artisan test --filter=ShopDeliveryTest` green (the rendering tests still pass); `grep -c "No invoice lines loaded" resources/views/shop/delivery-scan.blade.php` → 1.

### 3. Client toast icon follows the tone
Files: `resources/views/shop/stock-scan.blade.php`, `resources/views/shop/delivery-scan.blade.php`
What: replace the fixed check icon inside `.shop-toast__icon` with two icons toggled by tone: `<x-shop.icon name="check" size="sm" x-show="! toast || toast.tone === 'ok'" />` and `<x-shop.icon name="alert" size="sm" x-show="toast && toast.tone !== 'ok'" x-cloak />` (the `x-show`/`x-cloak` attributes pass through the icon component's `$attributes` onto the `<svg>`; confirm by rendering, and if the component does not merge attributes, wrap each icon in a `<span>` carrying the directive instead).
Check: rendering tests green; `grep -c 'name="alert"' resources/views/shop/stock-scan.blade.php resources/views/shop/delivery-scan.blade.php` → 1 each (delivery-scan also has an alert in the scan-input component, which lives in components, not in this file).

### 4. Build and format
Files: all touched
What: `npm run build`; `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean; `npm run build` succeeds.

## Verification

1. `php artisan test --filter=Shop` → all green (128 tests).
2. `php artisan test` → 17 failed / 528 passed, the identical 17.
3. `git diff --stat app/Http/Controllers/DeliveryLegacyController.php resources/views/delivery-legacy/` → empty.
4. `grep -rn "<script\|<style" resources/views/shop/` → nothing; `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL` → prints it.
5. `./vendor/bin/pint --test --dirty` → clean; `npm run build` → success.
6. Manual: the Home badge and the "N open" header on the delivery list agree on the dev database (6 and 6 at the time of writing, once the May Imbibe session is listed); open that session → its scan screen renders; a session with no invoice loaded reads "No invoice lines loaded · N issues" rather than "0 of 0 items"; on Stock scan, save a stock change (tick toast) and then try a save that fails validation such as a huge number (alert toast).

## Risks

- **Unbounded open list.** Open sessions are few by nature, but an abandoned backlog would make the list long; that is information staff (and the owner) should see, not a reason to hide it. If the backlog is real, the owner completes or removes sessions on the office page.
- **Attribute passthrough on `x-shop.icon`.** The component builds its own `class`; if it does not merge `$attributes`, the wrapper-span fallback in step 3 applies.

## Review

Reviewed 2026-09-24 by the Planner against the full `implemented.md`, the diff (a clean tree at baseline, HEAD `f1ae53ad`), and a rerun of the checks.

Steps 1–4: all PASS. Open sessions unwindowed and completed limited to 10 through a shared query builder; `$counts` scoped to the listed ids; the regression test fails against the old controller and passes against the new one; on the dev database the badge and the list both read 5 and the May Imbibe session is listed. `hasInvoice` getters on both delivery pages with the honest wording and the progress bar hidden; unexpected rows still listed. Toast icons follow the tone, with the directive on the icon span because `x-shop.icon` drops attributes. `--filter=Shop` 128 passed; full suite 17 failed / 528 passed, the identical 17; formatter clean; no legacy or office change; design block byte-identical.

Deviations 1–2: accepted; both are the better shape.

Notes for Planner, each decided:
- Open list unbounded, and abandoned sessions now show: **accepted**; the owner is told so they can complete or clear them on the office page.
- `$counts` scoped: **accepted**, a small performance gain.
- "No invoice lines" is common because the scratch table holds one synced delivery: **deferred**, recorded as a candidate cycle ("load the invoice from Shop mode") for the owner to weigh.
- `x-shop.icon` swallows attributes: **fixed in the next cycle** as its first step (one line, a rendering check).

Result: ACCEPTED. Archived by the Planner to `docs/planImp/archive/2026-09-24-shop-mode-cycle-9b/`. Next: print labels (screen 07).
