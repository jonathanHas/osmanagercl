# Cycle 2 — Correction card + / − respond instantly; the scanned-not-on-invoice query stops scanning every supplier link

Status: READY
Revision: 1
Planner: Fable 5.1
Date: 2026-10-08

## Goal

On the Shop scan screen, tapping a row opens the correction card, and its
+ / − buttons take about a second to register on production, while the
quantity prompt's + / − after a scan is instant. The owner met this on the
2026-10-08 delivery (supplier 37, 172 invoice lines, 163 scanned) on items
that have no barcode and so can only be counted through the card. Each tap
currently waits for a PATCH that recomputes the office page's financials
(three queries) and then a full reload of the list (two queries plus image
resolution), with the buttons disabled meanwhile. One query in both paths,
`getScannedNotOnInvoice()`, takes ~410 ms because it compares a utf8 column
with a latin1 column and so scans all 9,905 supplier links for every scanned
row. After this cycle: a tap updates the number at once and the save is sent
once taps stop; the Shop page's PATCH skips the financials it never reads;
and the slow query is rewritten the way the product-search rules prescribe,
which also speeds up the scan prompt's Add, the summary page, completion and
the office match page.

## Context

Measured on production on 2026-10-08 over the read-only SSH route, session
`6717664c-500a-459e-9969-d1461d74e72c` (supplier 37), two runs each:

| What a correction tap runs today | ms |
|---|---|
| PATCH `update-quantity`: delete + insert, then `getMatchedItems` | 28 |
| … `getScannedNotOnInvoice` | **409** |
| … `getOnInvoiceNotScanned` | 95 |
| then `load()` → GET `items`: `getMatchedItems` | 28 |
| … `getScannedNotOnInvoice` | **409** |
| … `ProductImageUrls::byCode` (181 codes: thumbnail versions 43 + URLs 77) | 105 |
| **Total server time per tap, sequential** | **≈ 1,070** |

Why `getScannedNotOnInvoice()` is slow (`DeliveryLegacyController.php:962`):
`deliveriesScanItems.barcode` is `utf8_general_ci`, `supplier_link.Barcode`
is `latin1_swedish_ci` (also `delivery.supCode`, `supplier_link.SupplierCode`,
`supplier_link.SupplierID`). `EXPLAIN` shows the `LEFT JOIN supplier_link sl
ON sl.Barcode = deliveriesScanItems.barcode` as `type=ALL rows=9905, Block
Nested Loop` (the `idx_barcode` index cannot be used across collations) and
the `NOT IN (SELECT … FROM delivery …)` as a dependent subquery run per row.
With the comparison made in the link table's collation the same query ran in
25 ms with identical rows, so the fix is the comparison, not the data. This is
the documented POS trap: `docs/features/product-search.md` ("POS collation
performance rules") says to pre-pluck barcodes from `supplier_link` and use
`whereIn`, never a cross-collation correlated comparison. There is no
`COLLATE` anywhere in `app/` today and the tests run the POS connection on
SQLite, so the rewrite below is driver-neutral and follows that rule rather
than adding MySQL-only syntax.

`getOnInvoiceNotScanned()` (:1000) is 95 ms for a different reason (the
`delivery` scratch table has no index on `supCode`, 195 rows); it is only
used by the financials, which the Shop page will stop asking for. Not
touched. `getMatchedItems()` (:823) is 28 ms; not touched.

Who uses what:
- `updateScannedQuantity()` (:1275) returns `{success, quantity, financials}`.
  The office page reads `financials` after each correction
  (`match.blade.php:452–689`, PATCH at :3425). The Shop page
  (`resources/js/shop/delivery-scan.js` `saveQuantity()`) reads only
  `success` and then calls `load()`.
- `items()` (:877) is read by `delivery-scan.js` (`load()` after every write)
  and `delivery-summary.js`. It calls `getMatchedItems()`,
  `getScannedNotOnInvoice()` and `ProductImageUrls::byCode()`.
- `getScannedNotOnInvoice()` is called from six places: `match()` :118
  (office page), `items()` :939, `calculateUndoPreview()` :1186,
  `updateScannedQuantity()` :1307, `completeDelivery()` :1608,
  `undoComplete()` :1745. Its return shape must stay: an array of
  `stdClass` with `Barcode`, `scanned`, `NAME`, `PRICESELL`, `RATE`,
  `SupplierCode`, `CaseUnits`, `productID`, `UNITS`, `categoryName`, ordered
  by `NAME` (MySQL puts NULL names first).
- The correction card: `delivery-scan.blade.php` section `x-ref="correct"`;
  JS `edit()`, `adjust()` → `saveQuantity()` (PATCH then `load()`, `busy`
  disables the buttons), `typeCorrection()` / `setCorrection()` (typed value,
  `editTyped`), `$watch('editing')` resets `editTyped`. The prompt's stepper
  (`bump()`) is local state, which is why it is instant.
- Tests: `tests/Feature/Shop/ShopDeliveryTest.php` (55 pass; fixture in
  `tests/Concerns/CreatesLegacyDeliveryPosTables.php`: session `d-1`, scans
  p1 ×12, p2 ×6 and the unknown `4260009912200` ×3, which is the one
  "unexpected" row `test_items_endpoint_classifies_the_session` (:396)
  checks). PATCH tests at :250–342. Shop rules in `docs/shop_new/README.md`,
  delivery rules in this folder's `README.md`.
- Dev POS is MySQL (`unicenta2016`), so a before/after timing can be taken on
  dev with tinker; production only over the read-only SSH route.

## Constraints

- Shop rules (`docs/shop_new/README.md`) and this folder's `README.md`.
  Rule 2 there: the server owns statuses; the card may show a local number
  while a save is pending, but rows and pills come from `items` after the
  save. No new endpoint; no route change (the PIN allow-list is unchanged).
- `getScannedNotOnInvoice()` keeps its signature and return shape; every
  caller must see the same rows it sees today. Completion and undo depend on
  it for stock.
- The office page keeps receiving `financials` from the PATCH by default.
- No `COLLATE` / `CONVERT` in queries (SQLite tests, and the product-search
  rule). Pre-pluck and `whereIn`.
- Do not commit, push or deploy. The owner commits.

## Out of scope

- Indexing or altering POS tables (`delivery.supCode` has no index; the
  table is a 195-row scratch table and is not ours to alter).
- `getOnInvoiceNotScanned()`, `getMatchedItems()`, image resolution speed
  (105 ms per `items` call: thumbnail MD5s over blobs each call; a later
  cycle could cache versions).
- Making rows update locally before the reload (the server owns statuses).
- The office match page's own correction speed beyond what the query
  rewrite gives it.

## Steps

### 1. `getScannedNotOnInvoice()` without the cross-collation join
Files: `app/Http/Controllers/DeliveryLegacyController.php`
What: rewrite the method body (:962–1000) in three indexed steps, same
signature and return shape:
1. Scanned totals for the session:
   `deliveriesScanItems` where `delID`, `select barcode, SUM(quantity) as
   scanned`, `groupBy barcode` → a map `barcode => scanned`. Cast keys with
   `(string)` when iterating: numeric-looking barcodes become integer array
   keys in PHP. Empty → return `[]`.
2. Barcodes the invoice covers for this supplier: `delivery` joined to
   `supplier_link` on `supCode = SupplierCode` with `SupplierID = ?`,
   `whereNotNull('supplier_link.Barcode')`, `distinct()->pluck('supplier_link.Barcode')`
   (both sides latin1; the join is already how `getOnInvoiceNotScanned()`
   does it). The extras are `array_diff` of the scanned barcodes against
   these, as strings.
3. Details for the extras, two `whereIn` queries with literal values (the
   index is used on both): `PRODUCTS` left-joined to `CATEGORIES`, `TAXES`
   and `STOCKCURRENT`, `whereIn('PRODUCTS.CODE', $extras)`, selecting
   `PRODUCTS.CODE`, `PRODUCTS.NAME`, `PRODUCTS.PRICESELL`, `TAXES.RATE`,
   `PRODUCTS.ID`, `STOCKCURRENT.UNITS`, `CATEGORIES.NAME as categoryName`,
   keyed by `CODE` (a product with several `STOCKCURRENT` rows: keep the
   first, as today's grouped query would list one row per location; one row
   per barcode is the better behaviour and the undo preview already sums
   stock separately); and `supplier_link` where `SupplierID = ?` and
   `whereIn('Barcode', $extras)`, `Barcode, SupplierCode, CaseUnits`, keyed
   by `Barcode`.
4. Assemble one `(object) [...]` per extra barcode with exactly the keys
   `Barcode, scanned (float), NAME, PRICESELL, RATE, SupplierCode, CaseUnits,
   productID, UNITS, categoryName` (null where there is no product or link),
   sorted by `NAME` with nulls first (`usort` on `[$a->NAME === null ? 0 : 1, $a->NAME]`),
   and return it as a plain array.
Replace the method's docblock with why: the old SQL compared
`deliveriesScanItems.barcode` (utf8) with `supplier_link.Barcode` (latin1),
which MySQL cannot index, so it scanned every supplier link per scanned row
(409 ms for 163 scans on 2026-10-08); the product-search rules say pre-pluck
and `whereIn`.
Check: `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` still
passes (the unexpected row `4260009912200` with no product, and completion /
undo through it). Then on dev MySQL, before and after the change, with the
dev session that has the most scan rows
(`php artisan tinker --execute="print_r(DB::connection('pos')->table('deliveriesScanItems')->select('delID', DB::raw('count(*) n'))->groupBy('delID')->orderByDesc('n')->limit(3)->get()->all());"`),
time the method through a bound closure, as the Planner did:
```
$c = app(\App\Http\Controllers\DeliveryLegacyController::class);
$f = \Closure::bind(fn () => $this->getScannedNotOnInvoice($delID, $sup), $c, \App\Http\Controllers\DeliveryLegacyController::class);
$t = microtime(true); $r = $f(); printf("%d rows %.0f ms\n", count($r), (microtime(true) - $t) * 1000);
```
Record both timings and that the row count and `Barcode` set are the same
before and after (compare `array_column($r, 'Barcode')` sorted).

### 2. A test with a product-backed extra row
Files: `tests/Feature/Shop/ShopDeliveryTest.php`
What: add `test_an_extra_product_with_a_supplier_link_keeps_its_details`:
insert a product `p3` "Hazelnuts 500 g" code `5000000000031` (category c2,
taxcat 001, `IMAGE` null), a `STOCKCURRENT` row `p3` 4, a `supplier_link`
row `Barcode 5000000000031, SupplierCode 'S3', SupplierID '999', CaseUnits 10`
(no `delivery` line for S3), and a scan `i4` of `5000000000031` × 2 in
`d-1`. GET `delivery-legacy.items` → the row for `5000000000031` has
`status 'unexpected'`, `code 'S3'`, `name 'Hazelnuts 500 g'`, `stock 4`,
`scanned 2`, `stockable true`; the row for `4260009912200` still has
`code null`, `name '4260009912200'`, `stockable false`; `progress.total`
is still 2 (extras are not invoice lines) and `progress.issues` is 3 (short
leeks + two unexpected). Also assert the rows' order among the extras: the
no-product row (null name) before "Hazelnuts" (nulls first, as MySQL
ordered them).
Check: the new test passes; `ShopDeliveryTest` → 56 passed.

### 3. The PATCH skips financials when asked
Files: `app/Http/Controllers/DeliveryLegacyController.php`,
`resources/js/shop/delivery-scan.js`
What: in `updateScannedQuantity()` add `'financials' => 'sometimes|boolean'`
to the validation and compute the three queries and `calculateFinancials()`
only when `$request->boolean('financials', true)`; when false, the JSON is
`{success, quantity}` with no `financials` key. Comment: the Shop page
reloads `items` after every write and never reads the financials; the office
page does. In `saveQuantity()` add `financials: false` to the PATCH body.
Check: new test `test_the_shop_page_can_skip_financials_on_a_correction`:
PATCH with `financials => false` → `assertOk`, `assertJsonMissing(['financials'])`
(or `assertJsonMissingPath('financials')`), the quantity is stored; PATCH
without the flag → `assertJsonPath('financials.totalItems', 2)`. And
`grep -n "financials: false" resources/js/shop/delivery-scan.js` → 1 line.

### 4. The correction card steps locally and saves once taps stop
Files: `resources/js/shop/delivery-scan.js`, `resources/views/shop/delivery-scan.blade.php`
What:
- State: `editValue: null` (the card's number while editing; starts from the
  row's `scanned ?? 0` when the card opens), `flushTimer: null`,
  `flushPending: false`.
- `edit(row)`: when opening, set `editValue = row.scanned ?? 0`; when
  closing (tapping the same row) flush first (see below).
- The `$watch('editing')` also calls `this.flushNow()` before resetting
  `editTyped`, so closing the card by any route (Done, ×, a scan) saves what
  was tapped.
- `adjust(row, delta)`: no request. `editValue = Math.max(0, Number((editValue + delta).toFixed(3)))`,
  `flushPending = true`, `clearTimeout(flushTimer)`,
  `flushTimer = setTimeout(() => this.flushNow(), 400)`. The + / − buttons
  lose `:disabled="busy"` (a tap must never be swallowed); the card shows
  `stockText(editValue)` instead of `stockText(editingRow?.scanned)`.
- `flushNow()`: if `! flushPending || ! editingRow` return; clear the timer,
  `flushPending = false`; `await this.saveQuantity(this.editingRow, this.editValue)`
  (PATCH + `load()`); on failure set `flushPending = true` again so the next
  tap or close retries, and the existing "Could not save" toast shows. Guard
  re-entrancy with `busy` as `saveQuantity()` does: if a flush is in flight
  when another is due, the timer simply fires again after it.
- `typeCorrection()` starts from `editValue`; `setCorrection()` sets
  `editValue` to the typed value and calls `flushNow()` (immediate, as now).
- `lookup()` already sets `editing = null`, which triggers the watch flush.
  Because `saveQuantity()` and `load()` are awaited inside the flush but the
  watch cannot be awaited, a scan that follows a tap within 400 ms could
  reload before the PATCH lands: make `lookup()` `await this.flushNow()`
  before `this.editing = null`.
- Page unload with a pending flush: add `window.addEventListener('pagehide', …)`
  in `init()` that, when `flushPending`, sends the PATCH with
  `fetch(..., { keepalive: true })` (a small JSON body is within the keepalive
  limit) and no reload. Put the body-building in one helper used by both
  `saveQuantity()` and this path so the two cannot drift.
- Header comment of the file: one sentence on the card stepping locally and
  saving 400 ms after the last tap, and why (the owner's 2026-10-08 report:
  about a second per tap on a 163-line delivery).
- `README.md` rule 3 wording in this folder stays true (scan is two-step);
  add a sentence to the Shop rule list if the Implementer finds a place, else
  leave it to the Planner.
Check: `php artisan test tests/Feature/Shop/ShopDeliveryTest.php tests/Feature/Shop/ShopViewContractTest.php`
pass; `npm run build` succeeds; source assertions in step 5.

### 5. Tests for the card
Files: `tests/Feature/Shop/ShopDeliveryTest.php`
What:
- Extend `test_the_correction_card_has_one_primary_button_while_typing` or
  add `test_the_correction_card_steps_locally_and_saves_once`: the view shows
  `stockText(editValue)` on the stepper value and the + / − buttons carry no
  `:disabled="busy"` (assert the `adjust(editingRow, -1)` button markup lacks
  it); the JS contains `flushNow()`, `setTimeout(() => this.flushNow(), 400)`,
  `keepalive: true`, and `adjust(row, delta) {` is followed, before the next
  `},`, by no `saveQuantity(` (the tap itself never posts).
- `test_a_scan_flushes_a_pending_correction_first`: the JS `lookup(` body
  contains `await this.flushNow()` before `this.editing = null`
  (`strpos` order within the function).
Check: `ShopDeliveryTest` → 59 passed (56 + the financials test + these two).

### 6. Docs
Files: `docs/features/shop-mode.md`, `docs/development/known-issues.md`
What: in `shop-mode.md`, after "Deliveries: unknown barcodes and outer
codes", add "### Deliveries: correcting a quantity" (two short paragraphs):
the card steps locally and saves 400 ms after the last tap or when the card
closes, a scan or leaving the page; the Shop PATCH skips the office
financials; and the measured cause (the `getScannedNotOnInvoice` collation
scan, 409 → ~25 ms). In `known-issues.md`, add a short entry under the POS
collation heading (or beside it) naming `deliveriesScanItems.barcode` (utf8)
vs `supplier_link.Barcode` (latin1) as a second instance of the same trap,
fixed 2026-10-08 by pre-plucking.
Check: `grep -c "correcting a quantity" docs/features/shop-mode.md` → 1;
`grep -c "deliveriesScanItems" docs/development/known-issues.md` ≥ 1.

### 7. Format, build, browser check
Files: none new.
What: `./vendor/bin/pint` on changed PHP files; `npm run build`. Browser on
dev as `test`: open the dev session with the most scan rows (from step 1),
tap a row, tap + five times quickly: the number climbs at once, exactly one
PATCH and one `items` GET appear in the network log about 400 ms after the
last tap, the row then shows the new total; tap − five times; type a value
and Set; then put the row's quantity back to what it was (note it first) and
confirm with a tinker read. Tap +, then within 400 ms scan (type) a barcode:
the PATCH lands before the reload. If the Chrome extension is not connected,
write this as the owner's checklist and set BLOCKED on that alone, as in
cycle 1. Dev state: the restored quantity, listed in the report.
Check: observations recorded; console clean.

## Verification

1. `./vendor/bin/pint --test $(git diff --name-only -- '*.php')` → PASS.
2. `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` → 59 passed.
3. `php artisan test tests/Feature/Shop` → all pass.
4. `php artisan test` → 15 failed (the baseline set in `README.md`), 1042 passed, nothing else.
5. `npm run build` succeeds.
6. Dev MySQL timing of `getScannedNotOnInvoice()` before and after (step 1), same rows.
7. The browser check in step 7.

After the owner deploys, the Planner re-times the production session over
the read-only route and records it in `README.md`.

## Risks

- **Return-shape drift in `getScannedNotOnInvoice()`** would silently change
  completion, undo, the deviation report and the office page. The field list
  in step 1 is exhaustive; the `grep` of callers is part of the step. The
  one intended change: a product with several stock locations gives one row,
  not one per location.
- **Order of extras**: callers that display the list expect name order; the
  `usort` keeps it, nulls first.
- **A lost tap** if the page is closed within 400 ms: the `pagehide`
  keepalive PATCH covers it; a backgrounded phone tab fires `pagehide` too.
- **Debounce and a slow server**: a flush in flight while taps continue is
  fine (the timer refires); the final value always wins because the PATCH is
  absolute, not an increment.
- **SQLite tests do not exercise MySQL plans**: the dev timing in step 1 is
  the evidence for the speed-up; the tests are the evidence for the rows.

## Review

(Planner fills this in after reading implemented.md and the diff.)
