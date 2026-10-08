# Deliveries — plan / implement track

Supplier delivery work runs here, separate from customer requests in
`docs/requests/`, Shop view updates in `docs/shop_new/`, orders in
`docs/order_clean/`, deposits in `docs/deposit/` and vouchers in
`docs/vouchers/`. Same protocol, same file roles: see
[`planimp.md`](./planimp.md). The Planner keeps this file current; a fresh
session reads it before touching delivery code.

Two delivery interfaces exist and both are live:

- **Shop delivery screens** (`/shop/deliveries`, `/shop/deliveries/scan`,
  `/shop/deliveries/summary`): the touch-first flow employees use on the
  floor. Every screen is a **Shop view** in `<x-shop-layout>`, so the Shop
  rules in `docs/shop_new/README.md` apply to every change here. The screens
  are thin: rows come from, and writes go to, the `delivery-legacy.*` JSON
  endpoints.
- **Office pages**: the legacy match page (`/delivery-legacy/match`, one
  Blade file with inline Alpine, ~3,300 lines) that the Shop screens
  replaced on the floor but which still owns case units, outer barcodes,
  translations, deviation reports and goods-return sheets; and the newer
  verification flow at `/deliveries` (CSV / PDF / XLSX import, documents,
  label printing, `DeliveryService`).

Feature docs: `docs/features/shop-mode.md` (Shop screens, camera scanning,
find by name), `docs/features/delivery-system.md` (the `/deliveries` flow),
`docs/features/delivery-scanning-enhancements-2025-08-05.md`.

## Where things stand (2026-10-08)

| | |
|---|---|
| Current task | **Cycle 1** — link an unknown outer barcode to a product from the Shop scan screen, as the legacy match page allows (from the owner's `todo.txt`). Revision 1 implemented and reviewed 2026-10-08 (uncommitted, six files); **revision 2 READY**: the owner's browser check found that the linking state looks like ordinary scanning, so a stray scan linked silently. Rev 2 adds a "Link to this product?" confirmation and turns the card and scan field amber while linking. Track opened 2026-10-08 |
| HEAD | `f29c5369` on `feature/modularization-phase1` |
| Working tree | clean apart from this folder. The Implementer records the baseline `git status --short` all the same |
| Test baseline | `php artisan test` → **15 failed, 1030 passed** (2026-10-08, about 60 s). The 15 are the known unrelated set: `UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2, `TestScraperControllerTest` ×1. `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` → 47 passed |

## Where the delivery code is

| What | Where |
|---|---|
| Shop controller | `app/Http/Controllers/Shop/DeliveryController.php` (`index`, `scan`, `summary`; only the session header, rows are fetched client-side) |
| Shop views | `resources/views/shop/deliveries.blade.php`, `delivery-scan.blade.php`, `delivery-summary.blade.php` |
| Shop JS | `resources/js/shop/delivery-scan.js` (composed with `mix()` from `product-images.js` + `product-typeahead.js`), `delivery-summary.js`; shared `scan-input.js` (camera + hand scanner, one scan field per page), `quantity.js` |
| Legacy endpoints | `app/Http/Controllers/DeliveryLegacyController.php`: `items()` :877 (classified rows + progress), `incrementScanQuantity()` :1326 (lookup with `quantity: 0`, add with N; resolves unit then outer barcode), `updateScannedQuantity()` :1275, `updateCaseUnits()` :1471, `saveOuterBarcode()` :1493 (+ `parseGS1OuterCode()` :1553), `completeDelivery()` :1579, `undoComplete()` :1708, `createSession()` :1886 |
| Legacy office view | `resources/views/delivery-legacy/match.blade.php` (assign-outer UI :262–311, JS :3083–3192, scan handler :3270) |
| Routes | `routes/web.php`: `delivery-legacy.*` group :727 behind `permission:deliveries.process`; `shop.deliveries*` in the authenticated `shop.*` group |
| PIN allow-list | `config/shop.php` `pin_session_routes` — every route a Shop view names must be listed (drift test in `tests/Feature/Shop/ConfinePinSessionTest.php`) |
| POS tables (read-only except through these endpoints) | `deliveriesScan` (session: `ID`, `supID`, `dateUpload`, `status`), `deliveriesScanItems` (`delID`, `barcode`, `quantity`), `delivery` (invoice lines, per sync not per session), `supplier_link` (`Barcode`, `SupplierCode`, `SupplierID`, `CaseUnits`, `OuterCode`, `stocked`), `PRODUCTS`, `STOCKCURRENT` |
| Models | `App\Models\DeliveryScanItem`, `SupplierLink` (POS connection), `LegacyDelivery`; the `/deliveries` flow has `Delivery`, `DeliveryItem`, `DeliveryScan`, `DeliveryDocument`, `DeliveryBarrel` |
| Tests | `tests/Feature/Shop/ShopDeliveryTest.php` (47 tests, fixture trait `tests/Concerns/CreatesLegacyDeliveryPosTables.php`: supplier 999 "Hof Linde", p1 oat drink `5000000000017` with outer `15000000000014` case 6, p2 leeks `5000000000024` no outer, session `d-1`); `CustomerRequestDeliveryFlagTest`, `DeliveryDepositReconciliationTest`, `DeliveryImportBarrelCodeTest`, `DeliveryTranslatedLabelPrintingTest` |
| Design | `docs/design/shop-mode/screen-04-deliveries.html`, `screen-05-delivery-scan-v2.html`, `screen-06-delivery-summary.html` (no unknown-barcode state is drawn; cycle 1 designs one from the prompt card) |

## Rules every change must respect

1. **Shop rules apply.** View contract (`x-shop.*` components, `shop-*`
   classes only), design CSS block verbatim, PIN allow-list, Alpine traps,
   no popups over rows, `mix()` not spread: all in `docs/shop_new/README.md`.
2. **The Shop screens do not re-implement the office rules.** Expected
   quantities, statuses, case resolution and stock updates are computed by the
   `delivery-legacy.*` endpoints; after every write the Shop page refetches
   `items`. A new Shop capability reuses an existing endpoint or adds one to
   `DeliveryLegacyController`, so the office page gets it too.
3. **Scan handling is two-step.** A code is looked up with `quantity: 0`
   (records nothing), a prompt opens, and only Add records. A camera
   detection pauses the decoder; the page dispatches `shop-scan-saved` only
   once it has finished with the scan and never while a prompt is open
   (the code still in frame would confirm it). The test
   `test_the_delivery_scan_page_reopens_the_camera_but_not_while_the_prompt_is_open`
   counts the `announceSaved()` calls; a change that adds one updates the
   count and the comment that lists them.
4. **One scan field per page.** `scan-input.js` captures hand-scanner bursts
   at window level and assumes a single field. A new "scan something else"
   step reuses the field and routes the `scan` event by page state, rather
   than adding a second input.
5. **POS writes stay narrow.** `supplier_link.OuterCode` and `CaseUnits` are
   written only by `saveOuterBarcode()` / `updateCaseUnits()`; scan rows only
   by the increment / update endpoints; stock only by complete / undo. No
   tinker fixes on production.
6. **GS1-128 codes** are normalised to the GTIN-14 in AI (01) on both sides
   (`parseBarcode()` in `scan-input.js`, `parseGS1OuterCode()` on the
   server). Keep both; a hand scanner on the office page bypasses the Shop
   client.
7. **Quantities** are capped at 9999 by the endpoints (a barcode can never be
   stored as a quantity) and stored rounded to 3 decimals.
8. **No commit, push or deploy** unless the plan says so. The owner commits.
   The Planner never edits application code.
9. Every response ends with `mpg123 /home/jon/Music/notification.mp3`.

## Checking a change

- `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` while working,
  `php artisan test tests/Feature/Shop` before reporting, the full suite for
  the baseline comparison.
- Markup is checked with a rendering test, not `php artisan view:cache`.
- Browser: dev host `osmanager.local` (plain HTTP, **no live camera**); typed
  codes in the scan field exercise the same `scan` event. `public/build` is
  git-ignored and served from a `npm run build`; rebuild after a JS change or
  run `npm run dev`. Dev accounts: `test` (manager), `katelyn` (employee, PIN
  `2580`). Open dev sessions on 2026-10-08: `4149c0a2-ae65-49ec-9c00-8da5a8124cef`
  (supplier 5, 1,532 stocked links without an outer code),
  `b98fced8-4b24-40bc-bf62-7322bffa9146` (supplier 28 Mossfield, case units 0).
  Dev POS data written during a check is put back and listed under "Dev state"
  in the report.
- Production is read-only from here:
  `ssh -n -o BatchMode=yes jon@lilThink2 'cd /var/www/html/osmanager && php artisan tinker --execute=…'`.

## Open items

- `supplier_link.CaseUnits` can be 0 or 1 for a product that really comes in
  cases (Mossfield links are 0). A newly linked outer barcode then opens a
  "Case of 1" prompt. Editing case units from the Shop screen is not planned;
  the office page has `updateCaseUnits()`. Owner to say whether the Shop
  prompt should offer it.
- From `docs/shop_new/README.md`, still open and delivery-related: the
  Deliveries list does not show which sessions have invoice lines loaded;
  the summary "Note for the office" has no backend; the summary could split
  units and kg given a reliable weighed flag; the by-name list choices left
  from the find-by-name task.

## Folder layout

- `planimp.md` — the protocol
- `README.md` — this file; the Planner updates "Where things stand" when a task is accepted
- `todo.txt` — the owner's list; the Planner turns items into cycles
- `plan.md` — Planner-owned
- `implemented.md` — Implementer-owned report for the current task
- `findings/` — things found after a task is accepted
- `archive/YYYY-MM-DD-<slug>/` — accepted tasks
- `parked/` — plans put aside before implementation
