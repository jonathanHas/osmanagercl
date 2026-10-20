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

## Where things stand (2026-10-20)

| | |
|---|---|
| Side plan | **`plan-glued-price.md` READY 2026-10-20** (Implementer next; report in `implemented-glued-price.md`): pdfplumber runs a long description into the Purchase column, so the price glues to the last word (`Jersey4,63 8,29 5 18,52`) or its digits interleave with it (`F3a,1c7tory 6,39 5 46% 19,02`). Three outcomes today: the line is dropped with no Unparsed entry (`6003103` on order 4798553, production `DEL-20261020-120934`, only the €18.52 total mismatch showed), listed as unparsed for hand entry (`36488`), or parsed shifted so the sale price is read as cost and the row gets a false red `Price check` (`36489`, `36491`; the stored `unit_cost` is right because it is `total / units`). Plan: un-glue rule at the end of `_preprocess_line()`, `_repair_shifted_price()` (integer Sale + the derived price's digits in the description + the total), `Purchase price repaired` warning and `Price repaired` badge, and a structural guard so a `code ordered qty … money` line is always listed as unparsed. Sweep of 157 stored dockets + the 2 new ones, 12,845 lines: 12 un-glues (0 regressions), 28 shift repairs (0 rejected), silent drops 1 → 0, remaining price-check failures 2 (`530144`, open item) |
| Accepted and committed | **`price-check`** (ACCEPTED 2026-10-19, archived in `archive/2026-10-19-price-check/` with the 4804899 finding, committed and deployed in `82b33968`; production has `delivery_items.price_valid` / `price_note`): glued case size repair (`4012,50gram` → 40 × 12,50 g), per-item price-check result, all parser warnings kept in `import_data.parser_warnings` and shown on the delivery page. Parser tests 212 |
| Earlier side plan | **`plan-dev-parser-venv.md` ACCEPTED 2026-10-19** (dev machine only, committed in `82b33968` but still to archive to `archive/2026-10-19-dev-parser-venv/`): `scripts/invoice-parser/venv` now exists on dev, built from a uv-managed CPython 3.12.15 under `/var/www/html/uv-python`, pins matching production; the PDF import on `/deliveries/create` works again (delivery 165 is the proof run). Follow-up side plan worth writing: "parser environment checks" (make `invoice:test-parser` / `checkConfiguration()` test the venv interpreter, pin `pypdfium2==4.30.0` in `requirements.txt`) |
| Current task | **Cycle 3** (`implemented.md` DONE 2026-10-08, committed in `e79531b7`, not yet reviewed by the Planner) — correct quantities from the delivery summary, with the correction card shared between the scan and summary screens (`plan.md`, READY 2026-10-08; owner's request). Accepted and committed: **cycle 1** (outer barcode link with confirmation, `90000850`, `archive/2026-10-08-link-outer-barcode/`), **cycle 2** (instant correction card, Shop PATCH without financials, `getScannedNotOnInvoice()` rewritten around the POS collation trap, `4d023aa8`, `archive/2026-10-08-correction-card-speed/`) |
| HEAD | `82b33968` on `feature/modularization-phase1` (price-check side plan, dev venv plan, deposit cycle 4 and the browser MCP work all swept into that one commit by the owner) |
| Working tree | clean apart from this folder (2026-10-20). The Implementer records the baseline `git status --short` all the same |
| Test baseline | `php artisan test` → **15 failed, 1042 passed** (2026-10-08, after cycle 2). The 15 are the known unrelated set: `UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2, `TestScraperControllerTest` ×1. `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` → 59 passed |

### Production timings (2026-10-08, read-only SSH, session `6717664c-…`, supplier 37, 172 invoice lines, 163 scans)

| Query / step | ms |
|---|---|
| `getMatchedItems()` | 28 |
| `getScannedNotOnInvoice()` as committed (utf8 vs latin1 join, full scan of 9,905 supplier links per scanned row) | 409 |
| the same comparison made in the link table's collation (proof of cause; not the fix shipped) | 25 |
| `getOnInvoiceNotScanned()` (no index on `delivery.supCode`) | 95 |
| `ProductImageUrls::byCode()` for 181 codes (thumbnail versions 43 + URLs 77) | 105 |
| One correction tap before cycle 2 (PATCH + reload, sequential) | ≈ 1,070 |
| **After cycle 2 (deployed, re-timed 2026-10-08):** `getScannedNotOnInvoice()` on the same session | **20** |
| … on the largest session (598 scans, 457 extras; was ~2,170 on dev) | 89 |

Column collations on the POS: `deliveriesScanItems.barcode` and `PRODUCTS.CODE` are `utf8_general_ci`; `supplier_link.Barcode` / `SupplierCode` / `SupplierID`, `delivery.supCode`, `deliveriesScan.supID` are `latin1_swedish_ci`. A comparison across that line cannot use an index. Production MySQL is 5.7.33.

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
  For a longer script, send it over stdin and run it with
  `php artisan tinker --execute="include '/tmp/x.php';"` under `timeout`:
  `php artisan tinker /tmp/x.php` runs the file and then waits on the REPL
  forever.
- Chrome on this machine cannot open `https://lilthink2/` in the
  Claude-in-Chrome tab group (certificate page), so production is checked
  by the owner in their own tab or measured over SSH.

## Open items

- Udea parser: a variable-weight line with the quantity glued to the weight
  (`530144 1 1 122,70kilogramCashews …`, two dockets) parses with SKU 122.70 and
  fails the price check; `_preprocess_line`'s weight rules do not cover it.
  Found in the 2026-10-19 sweep, not in `plan-price-check.md`.
- The `import_warnings` session flash set in `DeliveryController::storePdf()` is
  read by no view (dead code; `unparsed_lines` is persisted instead).
- Hardening for `_repair_glued_case_size()`: reject a repair whose leftover
  digits start with `0` (cannot be the start of a content number except a
  weight). No such line in the 13,129 checked on 2026-10-19; one-line change
  plus a test when the parser is next touched.
- `DeliveryController::destroy()` deletes the delivery's rows but not its
  document files: `storage/app/private/deliveries/2026/10/166/` and `167/`
  hold four orphaned PDFs each from the price-check re-imports on dev.
- Deposit reconciliation is empty on every multi-PDF import (the owner's normal
  Udea case): see `docs/deposit/findings/2026-10-19-multi-pdf-deposit-reconciliation.md`.
- Production `DEL-20261020-120934` (id 168) is missing `6003103` (4 × €4.63 =
  €18.52); the owner adds it by hand. Its `36489` / `36491` rows carry a false
  `Price check` badge (cost €3.17 is right); clears for future imports once
  `plan-glued-price.md` ships.
- The descriptions pdfplumber garbles on long lines (`cashewN,L`,
  `F3 a,1 c7 tory`) are stored as read. A column-based (x-position) read of
  the line tail would fix the glue / interleave family at the source; not
  planned while the text rules cover every line we have.

- Correction card edge cases left by cycle 2: a flush deferred behind a scan
  in flight reads the card's current value, so a row switch plus a tap
  inside ~400 ms can lose the earlier row's taps (a per-row pending map
  would close it); a `pagehide` keepalive PATCH that fails is not retried if
  the page comes back from the back/forward cache.
- `getOnInvoiceNotScanned()` is 95 ms (no index on `delivery.supCode`);
  only the office financials use it now.
- The thumbnail version lookup (`ProductThumbnailService::versions()`) MD5s
  every product photo blob on every `items` call (43 ms for 172 codes on
  production). A cached version per product would take it to nothing; not
  planned.
- Camera after Dismiss during an in-flight unit lookup on the outer-barcode
  card stays paused until the camera button is tapped (cycle 1 rev 2 note,
  rare, recoverable). A one-line widening of the resume condition if the
  owner meets it.
- No JS test runner in the project; the Implementer checked the scan flow
  with a throwaway Node harness in cycle 1. Adding Vitest is an owner
  decision.
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
