# Cycle 2 — Identify deposit products and mark them on the till (app side)

Status: ACCEPTED
Revision: 3
Planner: Fable 5.1
Date: 2026-10-03

Revision 3 (2026-10-03, after review): steps 15–17 added for the deposit
lines whose country code pdfplumber garbled (34 lines, four products never
suggested). Everything else is accepted; see Review.

Revision 2 (2026-10-03): the owner uses both Udea documents but wants the
**delivery docket** to drive the deposit mapping (invoice uploads involve no
price review). The accounting-invoice hook is dropped; the invoice parser
still emits `barrel_code` so the data is there if ever wanted.

## Goal

The app learns which Udea products carry a bottle or jar deposit, the owner
confirms them on one office screen, and the app writes the deposit properties
to those products on the till so the cycle 1 scripts add the deposit line
automatically. Three parts: (1) the parsers capture the per-line barrel code
instead of leaving it as a stray token in the description, and the import
paths record it; (2) a product-to-tier mapping with suggestions from the
evidence, a backfill from the existing delivery lines (about 620 lines, 53
products today), and a screen to confirm, reject, change or add; (3) a POS
sync service that owns the deposit till products and the `ATTRIBUTES` of
every mapped product, with a drift check. Cycle 1 proved the till side; this
cycle never touches `RESOURCES` or `Ticket.Buttons`.

## Context

### Where the barrel code sits in the documents

- **Delivery notes** (`Order_<n>.pdf`, imported at `/deliveries` → PDF
  upload → `DeliveryController::storePdf`): each product line ends
  `… <Country> <Brl>`, e.g. `94761 1 1 12 200millilitre Apple-mango-juice,
  Luna e TerraBio-Dynamisch DE 313 0,89 1,79 5 46% 10,68`. pdfplumber
  sometimes garbles the description (`OrganBicio Nlogaitsucrhe`) and once
  dropped the space (`… NL10046`), but the country code and the barrel code
  are the last two tokens before the prices every time. There is no Gb.rek
  group code on delivery notes. The parser's `NORMAL_REGEX` description group
  swallows `<Country> <Brl>`, which is why `delivery_items.description` today
  ends in `DE 313`.
- Reconciliation holds: on delivery 132 the per-line units sum to exactly the
  "Barrels delivered" quantities (313: 54, 315: 12, 9936: 30), with one line
  (`6000800 … NL10046`) carrying the 0.15 code. Product lines never carry
  crate or pallet codes; those appear only in the barrels section.
- **Accounting invoices** (`UdeaFactuur<n>.pdf`, uploaded through the invoice
  bulk upload) have a different layout (date first, Gb.rek group, country,
  then the Brl column). `scripts/invoice-parser/parsers/invoice_udea.py`
  already matches those lines with `extended_pattern` (the `extra` group) but
  throws the value away. `InvoiceParsingService` (around lines 494–517)
  merges the parser's `lines` and `barrels` into
  `InvoiceUploadFile.parsed_data` (cast `array`); the file row has
  `supplier_detected` but no supplier id. The delivery parser cannot read a
  Factuur (it returned `NO_PRODUCTS_FOUND` on the owner's sample).
- **Owner (2026-10-03): both documents are brought in, but the delivery
  docket is the one to drive the mapping** (it is reviewed line by line;
  invoice uploads are not). So the only import hook is on the delivery PDF
  import; the accounting parser just emits the code. The hook may never fail
  an import.

### The code paths

- `scripts/invoice-parser/parsers/delivery_udea.py`: `DeliveryUdeaParser`,
  `_preprocess_line`, `_parse_line` (three regexes), `parse_invoice` builds
  `item = {code, product, case_size, total_ordered_units,
  total_delivered_units, unit_cost, rsp, line_total, price_valid,
  is_weight_based, weight_per_unit, weight_unit, total_weight}` with
  `product_name = f"{content} {description}"`; `_parse_barrels_section`
  returns `{'items': [{code, qty, description, price, total}], 'total'}`.
  Run with the project venv:
  `scripts/invoice-parser/venv/bin/python scripts/invoice-parser/delivery_parser_laravel.py --file <pdf> --supplier udea`.
  `delivery_parser_laravel.py` passes `items` through untouched.
- `app/Services/DeliveryParsingService.php::convertToDeliveryItems()`
  whitelists keys into the `Code / Product / … / order_number` shape;
  `app/Services/DeliveryService.php::importFromPdfData()` (lines ~95–200)
  creates `DeliveryItem` rows from that shape (`'description' =>
  $productName`, `'product_id' => $product?->ID`, `'barcode' =>
  $product?->CODE`), resolving the product with
  `findProductBySupplierCode($code, $supplierId)` (SupplierLink on the POS).
  `DeliveryController::storePdf()` then calls `storeBarrelItems()` for
  `$result['data']['barrels']['items']`.
- `App\Models\DeliveryItem` (fillable includes `supplier_code`, `barcode`,
  `description`, `product_id`; `product()` → POS `Product` by `ID`).
- `App\Models\BarrelCode` (`barrel_codes`: `supplier_code`, `supplier_id`,
  `description`, `name`, `image`, `unit_price`, `is_active`; unique
  `(supplier_id, supplier_code)`; `storeBarrelItems()` updates `unit_price`
  when an invoice shows a new price). Udea supplier ids:
  `config('suppliers.external_links.udea.supplier_ids')` = `[5, 44, 85]`.
  Codes 313 / 315 / 9936 / 10046 exist under supplier 5.
- POS: `App\Models\Product` (connection `pos`, table `PRODUCTS`, string `ID`,
  fillable `ID NAME CODE REFERENCE CATEGORY TAXCAT PRICESELL PRICEBUY DISPLAY
  IMAGE`; `ATTRIBUTES` is `$hidden`, not fillable: write it with
  `Product::whereKey($id)->update(['ATTRIBUTES' => $xml])`; casts `ISSCALE`
  boolean, `ISVPRICE` integer). `App\Models\SupplierLink` (pos
  `supplier_link`: `Barcode`, `SupplierCode`, `SupplierID`). The write
  pattern to copy is `app/Services/VoucherPosProductService.php::sync()`
  (`Product::create([...])` then `forceFill(['ISSERVICE' => 1])->save()`).
- `PRODUCTS.ATTRIBUTES` format (Java `Properties.storeToXML`), exactly:

  ```xml
  <?xml version="1.0" encoding="UTF-8" standalone="no"?>
  <!DOCTYPE properties SYSTEM "http://java.sun.com/dtd/properties.dtd">
  <properties>
  <comment>osmanager deposit</comment>
  <entry key="deposit.id">80184d44-d2ca-4a05-b243-3e5166612849</entry>
  <entry key="deposit.name">Bottle deposit 0.25</entry>
  <entry key="deposit.price">0.25</entry>
  </properties>
  ```

  LF line endings, UTF-8, no BOM, XML-escape entry values. Other keys must be
  preserved when present (no product has any today, but the till's own editor
  can add some). Reading: `simplexml_load_string` copes with the DOCTYPE.
- The till (cycle 1) reads `deposit.id` (POS id of the deposit product),
  `deposit.name`, `deposit.price` from the scanned product's properties.
  Deposit till products already exist on dev from the spike, created by code:
  `DEP-025` `80184d44-d2ca-4a05-b243-3e5166612849`, `DEP-070`
  `efc616db-9852-448b-992e-744482c48d40`, `DEP-010`
  `c6f9016e-1a76-4977-ab75-1ac230126f78`, refunds `DEP-025-RET`, `DEP-070-RET`,
  `DEP-010-RET`; category `Bottle Deposits` `c8936170-3320-47e9-af5e-bf059c43b93a`.
  The service in this cycle must find them by `CODE` and adopt them, not
  create duplicates (`CODE`, `NAME`, `REFERENCE` are unique on the till).
  Attributes already set by the spike on `8711521947614` and `8714728001004`
  are to be overwritten by the sync (same values).
- Office screens: `/barrel-codes` (`BarrelCodeController`, views
  `resources/views/barrel-codes/*.blade.php`, `<x-admin-layout>`), inside the
  `Route::middleware('permission:deliveries.manage')` group in
  `routes/web.php` (around line 694). Product picker: `<x-product-search
  mode="picker" name="product_id">` emits `product-search:selected` with
  `$event.detail` (`id`, `name`, `code`…), see `docs/features/product-search.md`.
- Tests: `phpunit.xml` points the `pos` connection at sqlite `:memory:`.
  `tests/Concerns/CreatesVoucherPosTables.php` shows how a test builds the POS
  tables it needs (`Config::set('database.connections.pos', …)`, `DB::purge`,
  schema builder). Python tests: `scripts/invoice-parser/tests/test_*.py`,
  run with `scripts/invoice-parser/venv/bin/python -m pytest
  scripts/invoice-parser/tests/ -v` (fixtures are real extracted text; this
  cycle needs none, string-level tests suffice).
- Commands live in `app/Console/Commands/` (`SyncVoucherPosProducts.php` is
  the shape: `--dry-run`, a table, totals).
- Alpine in Blade: never an `@` shorthand that is also a Blade directive
  (write `x-on:…`); anything nullable behind `x-show` needs `?.`.

### Facts from the data (dev, 2026-10-03)

- 16,527 Udea delivery lines; 490 end in `313`, 117 in `315`, 11 in `9936`
  (plus the merged `NL10046` kind). 53 distinct products, all with
  `product_id`; none is `ISSCALE` or `ISVPRICE`. One product (`36214`, Billys
  Farm waffles) shows the code on 2 of 3 lines; the screen must show such
  counts rather than hide them.
- Tier prices: 313 = 0.25, 315 = 0.70, 9936 = 0.10, 10046 = 0.15.

## Constraints

- **Owner decisions (2026-10-03):** automatic till line; deposit at cost
  (tier price = `barrel_codes.unit_price`); zero-rated (`TAXCAT 000`).
- **Deposit codes come from the documents and are confirmed by the owner.**
  The app never infers a deposit from a product's name, size or category.
  A suggestion exists only for a product seen with a barrel code whose tier
  the owner has switched on ("charge customers").
- **No weighed or variable-price products.** The sync refuses (and records
  why) when the POS product has `ISSCALE = 1` or `ISVPRICE != 0`; the screen
  shows the error.
- **POS writes go through one service** (`DepositPosService`) and touch only:
  `PRODUCTS` (new deposit/refund rows; `ATTRIBUTES`, `PRICESELL` and `NAME`
  of deposit rows), `CATEGORIES` (the one category), `PRODUCTS_CAT` (refund
  buttons). Nothing else on the POS, ever; no `RESOURCES`.
- `ATTRIBUTES` writes merge: other keys are preserved; clearing removes only
  `deposit.id`, `deposit.name`, `deposit.price` and writes `NULL` when nothing
  is left.
- The delivery import hook is wrapped so any deposit-side exception is
  logged and the import still succeeds. There is no hook on the accounting
  invoice upload (owner decision, revision 2).
- Follow `CLAUDE.md`: Eloquent models for every Laravel table (new models for
  the new tables); the query builder on `pos` only for `CATEGORIES` and
  `PRODUCTS_CAT`, which have no model; validation `exists:App\Models\…`.
- Run `./vendor/bin/pint` on changed PHP. Do not commit, deploy, or touch
  production.
- Dev POS writes are fine (it is the test till's database); the deposit
  products from the spike are adopted by `CODE`.

## Out of scope

- Production rollout (installing the scripts and `Ticket.Buttons` on the
  production POS, restarting tills, running the sync there): cycle 3.
- Refund accounting, deposit float reconciliation, reports: cycle 3.
- The Shop view (`/shop`), shelf labels, receipt wording.
- The legacy delivery flow (`/delivery-legacy`, POS `delivery` table).
- Repairing pdfplumber garbles in descriptions, or the merged `NL10046` form
  in the **accounting** invoice parser (handle it in the delivery parser only).
- Other suppliers' deposits.
- Recording evidence from accounting invoice uploads (`InvoiceParsingService`
  is not touched).
- Changing cycle 1's till scripts or anything under `docs/deposit/scripts/`.

## Steps

### 1. Baseline
Files: none.
What: record `git rev-parse --short HEAD`, `git status --short`; run
`php artisan test` once and record the failing test names (there is a known
baseline of pre-existing failures; this cycle must not add to it). Run the
Python suite once and record the result.
Check: both outputs in `implemented.md`.

### 2. Delivery parser captures the barrel code
Files: `scripts/invoice-parser/parsers/delivery_udea.py`,
`scripts/invoice-parser/tests/test_delivery_udea.py` (new; check first).
What:
- Add a module-level pattern and a method
  `DeliveryUdeaParser.split_barrel_code(description) -> (description, code|None)`:
  the description ends in a two-letter uppercase country code, optional
  whitespace, then 1–5 digits at end of string
  (`re.compile(r'^(?P<desc>.*?\b[A-Z]{2})\s*(?P<code>\d{1,5})$')`). On a match
  return the description up to and including the country code (trailing
  whitespace stripped) and the code; otherwise the input unchanged and `None`.
- In `parse_invoice`, apply it to `description` before `product_name` is
  built, and add `"barrel_code": barrel_code` to `item`.
- After the barrels section is parsed, reconcile: sum `total_delivered_units`
  per `barrel_code` over the items and compare with the section's `qty` per
  code. Put the per-code line sums in `result["barrels"]["line_units"]`
  (`{code: units}`) and append a warning per mismatch
  (`Deposit code 313: product lines total 54 units, barrels section says 60`)
  and per code seen on lines but absent from the section. A mismatch is a
  warning, never an error.
- Tests (string level, no PDF): `split_barrel_code` on the nine real
  descriptions from delivery 132 (Context) including the merged
  `'… elderflowBeior,lo Lgoisucther NL10046'` → code `10046`; negatives:
  `'… Biologisch NL'`, `'… I NL'`, `'… . NL'`, `'Omega 3'`,
  `'Vitamin D3 1000 IU'` → unchanged, `None`. A full-line test: run
  `_preprocess_line` + `_parse_line` on
  `'92504 1 1 12 200millilitre Fruit-juice pineapple, Your OrganBicio Nlogaitsucrhe DE 313 1,18 2,39 5 46% 14,16'`
  and assert the split yields `313` and the description ends `DE`. A
  reconciliation test on a small synthetic `items` + `barrels` pair through
  whatever helper you extract for it.
Check: `scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests/test_delivery_udea.py -v`
passes; then the real document:
`scripts/invoice-parser/venv/bin/python scripts/invoice-parser/delivery_parser_laravel.py --file storage/app/private/deliveries/2026/07/132/978ab3fd-1eb4-4030-930b-8391c59a34d8.pdf --supplier udea`
→ `success` true, 186 items, the nine deposit items carry `barrel_code`
(`313` ×5, `315` ×2, `9936` ×2) plus `6000800` → `10046`, their `product`
ends with the country code, `barrels.line_units` is
`{"313": 54, "315": 12, "9936": 30, "10046": 12}` and there is no
reconciliation warning. Paste the summary.

### 3. Accounting invoice parser emits the barrel code
Files: `scripts/invoice-parser/parsers/invoice_udea.py`,
`scripts/invoice-parser/tests/test_invoice_udea.py` (new; check first).
What: in the `extended_pattern` branch set `'barrel_code': extra` on
`line_item`; set `'barrel_code': None` in the other line branches so the key
is always present. Test with the owner's sample line
`'24.09.26 94761      1   12   200 millilitre Apple-mango-juice, Luna e Terra Bio-Dynamisch     30322 DE   313    0,89    1,79   1   46%    10,68'`
(collapse runs of spaces if the parser's preprocess does so) → one line with
`article_code 94761`, `barrel_code '313'`, `country 'DE'`; and a line without
the column → `barrel_code None`. Use the same entry point the Laravel wrapper
uses (`invoice_parser_laravel.py` imports `udea`/`invoice_udea`; follow how
`test_independent.py` calls its parser).
Check: the new test file passes; `python -m pytest scripts/invoice-parser/tests/ -v` has no new failures.

### 4. Laravel schema and models
Files: `database/migrations/2026_10_03_000001_add_barrel_code_to_delivery_items.php` (new),
`database/migrations/2026_10_03_000002_add_customer_deposit_columns_to_barrel_codes.php` (new),
`database/migrations/2026_10_03_000003_create_deposit_sightings_table.php` (new),
`database/migrations/2026_10_03_000004_create_product_deposits_table.php` (new),
`app/Models/DeliveryItem.php`, `app/Models/BarrelCode.php`,
`app/Models/DepositSighting.php` (new), `app/Models/ProductDeposit.php` (new).
What:
- `delivery_items`: `barrel_code` string(20) nullable, indexed; add to
  `DeliveryItem::$fillable`.
- `barrel_codes`: `charge_customer` boolean default false;
  `pos_product_id` string(36) nullable; `pos_refund_product_id` string(36)
  nullable. Fillable + casts. Relation `productDeposits()` hasMany.
- `deposit_sightings`: `id`; `supplier_id` unsignedBigInteger;
  `supplier_code` string(20); `barrel_code` string(20); `units`
  unsignedInteger; `source_type` string(32) (only `delivery_item` for now;
  kept so another source can be added later); `source_id` unsignedBigInteger
  (the `delivery_items.id`); `seen_on` date nullable;
  timestamps; unique `(source_type, source_id, supplier_code, barrel_code)`;
  index `supplier_code`. Model with fillable/casts and
  `barrelCode()` (belongsTo `BarrelCode` by `supplier_code`… no: resolve in
  the service; keep the model plain).
- `product_deposits`: `id`; `product_id` string(36) unique (POS `PRODUCTS.ID`);
  `product_code` string(32) nullable (barcode snapshot); `barrel_code_id`
  FK → `barrel_codes` (restrict on delete); `status` string(16) default
  `suggested` (`suggested` | `confirmed` | `rejected`); `source` string(16)
  (`invoice` | `manual`); `sightings_units` unsignedInteger default 0;
  `sightings_count` unsignedInteger default 0; `conflicting_units`
  unsignedInteger default 0; `last_seen_on` date nullable; `confirmed_by`
  FK → `users` nullable (nullOnDelete); `confirmed_at` timestamp nullable;
  `pos_synced_at` timestamp nullable; `pos_sync_error` string nullable;
  `notes` string nullable; timestamps. Model: fillable, casts, constants for
  statuses/sources, `barrelCode()`, `product()` (belongsTo POS `Product`,
  `product_id`, `ID`), `confirmedBy()`, scopes `suggested()`, `confirmed()`.
Check: `php artisan migrate` clean on dev; `php artisan tinker --execute="echo Schema::hasColumn('delivery_items','barrel_code') && Schema::hasTable('product_deposits') && Schema::hasTable('deposit_sightings') && Schema::hasColumn('barrel_codes','charge_customer') ? 'ok' : 'missing';"` → `ok`.

### 5. Import paths store the code
Files: `app/Services/DeliveryParsingService.php`, `app/Services/DeliveryService.php`.
What: `convertToDeliveryItems()` adds `'barrel_code' => $item['barrel_code'] ?? null`;
`importFromPdfData()` stores `'barrel_code' => $record['barrel_code'] ?? null`
on the `DeliveryItem`. Nothing else in these methods changes.
Check: covered by the test in step 11; for now `php -l` both files.

### 6. Attributes helper
Files: `app/Support/PosProductAttributes.php` (new; check first),
`tests/Unit/PosProductAttributesTest.php` (new).
What: a small final class with static methods: `parse(?string $xml): array`
(key → value; empty array for null/blank/unparseable), `serialize(array
$entries, string $comment = 'osmanager deposit'): ?string` (null for an empty
array; otherwise byte-exact format from Context, entries in insertion order,
values and keys XML-escaped), `withDeposit(?string $xml, string $id, string
$name, string $price): string`, `withoutDeposit(?string $xml): ?string`,
`hasDeposit(?string $xml): bool`, `depositId(?string $xml): ?string`.
Tests: serialize of the three deposit entries equals the Context XML
byte-for-byte; parse(serialize(x)) round trip; withDeposit keeps a foreign
key (`foo`); withoutDeposit on deposit-only XML returns null, on mixed XML
keeps `foo`; escaping of `&`, `<`, `"`; parse of garbage returns `[]`.
Check: `php artisan test --filter=PosProductAttributesTest` green.

### 7. Evidence and suggestions service
Files: `app/Services/Deposits/DepositEvidenceService.php` (new),
`tests/Concerns/CreatesDepositPosTables.php` (new),
`tests/Feature/DepositEvidenceServiceTest.php` (new).
What (service):
- `recordDelivery(Delivery $delivery): int` — for each of the delivery's
  items with `barrel_code` and `supplier_code`, `updateOrCreate` a sighting
  (`source_type 'delivery_item'`, `source_id` = item id, `supplier_id` =
  delivery's, `units` = the item's delivered units (`invoice_delivered_quantity`
  if set else `ordered_quantity`), `seen_on` = delivery date). Returns rows
  written.
- `backfillDeliveryItems(bool $dryRun = false): array` — for `DeliveryItem`
  rows of Udea deliveries with `barrel_code` null whose description matches
  the PHP twin of the Python pattern (`/^(?<desc>.*?\b[A-Z]{2})\s*(?<code>\d{1,5})$/`):
  set `barrel_code`, trim the description to the country code, then
  `recordDelivery` per affected delivery. Returns counts (rows, deliveries,
  codes). Idempotent.
- `refreshSuggestions(): array` — group sightings by `supplier_code`; resolve
  the product: `SupplierLink` with `SupplierID` in the Udea ids and
  `SupplierCode` = code → `Product` by `CODE` = `Barcode`; fall back to the
  latest `DeliveryItem.product_id` for that `supplier_code`. Dominant code =
  most units. Tier = `BarrelCode` with that `supplier_code` and a Udea
  `supplier_id` (prefer 5). Only when `tier->charge_customer`: upsert
  `ProductDeposit` by `product_id`: create as `suggested`/`invoice` with that
  tier; an existing `suggested` row follows the dominant tier; `confirmed`
  and `rejected` rows keep status and tier. Always refresh
  `sightings_units`, `sightings_count` (distinct source rows for the row's
  tier code), `conflicting_units` (units under other codes), `last_seen_on`,
  `product_code`. Returns counts (created, updated, unmatched codes).
- `evidenceFor(ProductDeposit)` is not needed; the row carries the counts.
What (test trait): `CreatesDepositPosTables::createDepositPosTables()` builds
sqlite `pos` tables exactly as the voucher trait does, for: `PRODUCTS`
(`ID`, `NAME` unique, `CODE` unique, `REFERENCE` unique, `CATEGORY`,
`TAXCAT`, `PRICEBUY`, `PRICESELL`, `ISSERVICE` default 0, `ISSCALE`
default 0, `ISVPRICE` default 0, `ATTRIBUTES` text nullable, `DISPLAY`
nullable, `IMAGE` nullable), `CATEGORIES` (`ID`, `NAME` unique, `PARENTID`
nullable, `CATSHOWNAME`), `PRODUCTS_CAT` (`PRODUCT`, `CATORDER`),
`supplier_link` (`ID` autoincrement, `Barcode`, `SupplierCode`,
`SupplierID`, `CaseUnits` nullable, `stocked` default 1, `OuterCode`
nullable, `Cost` nullable), `suppliers` (`SupplierID`, `Supplier`). Helpers
`posProduct(code, name, extra = [])` and `supplierLink(barcode, code,
supplierId = 5)`.
Tests: backfill splits `… DE 313` and the merged `… NL10046`, leaves
`… Biologisch NL` alone, creates sightings, is idempotent; suggestions
appear only for `charge_customer` tiers; dominant code wins when a product
has two codes and `conflicting_units` is right; a confirmed row keeps its
tier when the evidence changes; unmatched supplier code is counted, not
created.
Check: `php artisan test --filter=DepositEvidenceServiceTest` green.

### 8. POS sync service
Files: `app/Services/Deposits/DepositPosService.php` (new),
`tests/Feature/DepositPosServiceTest.php` (new).
What:
- `ensureTierProducts(BarrelCode $tier): BarrelCode` — category `Bottle
  Deposits` by `NAME` (create with uuid, `PARENTID` null, `CATSHOWNAME` 1 if
  missing); cents = `round(unit_price * 100)`; charge product `CODE`
  `DEP-<cents padded to 3>` (`DEP-025`), `NAME` `Bottle deposit <0.25>`,
  `REFERENCE` = `CODE`, `TAXCAT '000'`, `PRICESELL` = unit_price,
  `PRICEBUY` 0, `ISSERVICE` 1; refund product `DEP-<cents>-RET`, `NAME`
  `Bottle deposit refund <0.25>`, `PRICESELL` = −unit_price, plus a
  `PRODUCTS_CAT` row (`CATORDER` = next free). Find by `CODE` first and
  adopt; update `NAME`/`PRICESELL` when the price changed. Store both ids on
  the tier (`pos_product_id`, `pos_refund_product_id`).
- `syncProduct(ProductDeposit $row): string` — loads the POS product; if
  missing → `pos_sync_error 'product not on till'`. If `status === confirmed`
  and `tier->charge_customer`: refuse when `ISSCALE` or `ISVPRICE != 0`
  (`pos_sync_error`, no write, return `refused`); else
  `ensureTierProducts`, then `ATTRIBUTES` = `withDeposit(current, tier
  pos_product_id, 'Bottle deposit <price>', '<price>')` where `<price>` is
  `number_format(unit_price, 2, '.', '')`; return `written`/`unchanged`.
  Otherwise `ATTRIBUTES` = `withoutDeposit(current)`; return
  `cleared`/`unchanged`. Set `pos_synced_at`, clear `pos_sync_error` on
  success. Writes via `Product::whereKey($id)->update([...])`.
- `syncAll(): array` — every `ProductDeposit` through `syncProduct`; then
  strays: POS products whose `ATTRIBUTES` contain `deposit.id` (`LIKE
  '%deposit.id%'`) with no `confirmed` row → `withoutDeposit`. Totals per
  outcome.
- `check(): array` — read-only report: per confirmed row expected vs actual
  (`deposit.id`, `deposit.price`), strays, tiers without till products.
Tests: `ensureTierProducts` creates category, charge and refund products and
the catalogue row, then adopts them by `CODE` on a second call (no
duplicates) and updates the price; `syncProduct` writes the exact XML,
preserves a foreign key, clears on `rejected`, refuses `ISSCALE`; `syncAll`
clears a stray; `check` reports a drifted price.
Check: `php artisan test --filter=DepositPosServiceTest` green.

### 9. Hook into the delivery import
Files: `app/Http/Controllers/DeliveryController.php`,
`app/Services/Deposits/DepositEvidenceService.php`.
What: add `afterDeliveryImport(Delivery $delivery): void` to the evidence
service: `recordDelivery()` → `refreshSuggestions()` →
`DepositPosService::syncAll()`, the whole body inside `try/catch (\Throwable)`
with `Log::warning('Deposit evidence hook failed', [...])`. Call it from
`storePdf()` right after the `storeBarrelItems` block, only when the supplier
is a Udea id. `InvoiceParsingService` is not touched.
Check: `php -l`; a Feature test is impractical (Python subprocess), so prove
it with the dev document: re-import is destructive, so instead run
`php artisan tinker --execute="app(App\Services\Deposits\DepositEvidenceService::class)->afterDeliveryImport(App\Models\Delivery::find(132));"`
after step 10's backfill and confirm it is idempotent (sightings count
unchanged).

### 10. Commands
Files: `app/Console/Commands/DepositsBackfillDeliveryItems.php` (new),
`app/Console/Commands/DepositsRefreshSuggestions.php` (new),
`app/Console/Commands/DepositsSyncPos.php` (new).
What: `deposits:backfill-delivery-items {--dry-run}`,
`deposits:refresh-suggestions`, `deposits:sync-pos {--dry-run} {--check}`
(`--check` prints `check()`; `--dry-run` lists what `syncAll` would do without
writing — implement by passing a flag into the service). Tables and totals
like `vouchers:sync-pos-products`. Do not schedule anything.
Check (on dev, in this order, outputs pasted):
1. `php artisan deposits:backfill-delivery-items --dry-run` → about 620 rows
   over the 313 / 315 / 9936 / 10046 codes; then without `--dry-run`; a
   second run reports 0.
   `DeliveryItem::where('supplier_code','94761')->latest('id')->value('description')`
   now ends `DE`, and `barrel_code` is `313`.
2. `php artisan tinker --execute="App\Models\BarrelCode::whereIn('supplier_code',['313','315','9936'])->where('supplier_id',5)->update(['charge_customer'=>true]);"`
   (the owner can change this on the screen later).
3. `php artisan deposits:refresh-suggestions` → about 53 `suggested` rows
   (`36214` with `conflicting_units` > 0 or a lower `sightings_count` than its
   lines); `6000800` is absent because 10046 is not switched on.
4. `php artisan deposits:sync-pos --dry-run` → nothing to write (no row is
   confirmed yet) except that the two spike products would be cleared;
   do **not** run it for real yet (step 12 confirms first).

### 11. Import path test
Files: `tests/Feature/DeliveryImportBarrelCodeTest.php` (new).
What: using `CreatesDepositPosTables`, build a `pos` product + supplier link
for code `94761`, call `DeliveryParsingService::convertToDeliveryItems()` on
a parsed-shape array with one item carrying `barrel_code '313'` and one
without, then `DeliveryService::importFromPdfData()` with those items and
assert the two `DeliveryItem` rows have `barrel_code` `313` / null and the
description untouched. Mock nothing else; if `importFromPdfData` needs a
queue for barcode retrieval, use `Queue::fake()`.
Check: `php artisan test --filter=DeliveryImportBarrelCodeTest` green.

### 12. The screen
Files: `app/Http/Controllers/DepositController.php` (new),
`app/Http/Requests/StoreProductDepositRequest.php` (new),
`app/Http/Requests/UpdateProductDepositRequest.php` (new),
`resources/views/deposits/index.blade.php` (new), `routes/web.php`,
`resources/views/barrel-codes/index.blade.php` (one link),
`resources/views/deliveries/index.blade.php` (one link beside "Barrel Codes"),
`tests/Feature/DepositScreenTest.php` (new).
What: inside the existing `permission:deliveries.manage` group:

| Method | URI | Name | Does |
|---|---|---|---|
| GET | `/deposits` | `deposits.index` | the page |
| PATCH | `/deposits/tiers/{barrelCode}` | `deposits.tiers.update` | toggle `charge_customer`; when switched on → `ensureTierProducts`; when switched off → `syncAll()` (its products' attributes get cleared) |
| POST | `/deposits/products` | `deposits.products.store` | manual add: `product_id` (`exists:App\Models\Product,ID`), `barrel_code_id` (`exists:App\Models\BarrelCode,id`); creates `confirmed`/`manual` and syncs |
| PATCH | `/deposits/products/{productDeposit}` | `deposits.products.update` | `status` in confirmed/rejected/suggested and/or `barrel_code_id`; sets `confirmed_by`/`confirmed_at`; syncs |
| DELETE | `/deposits/products/{productDeposit}` | `deposits.products.destroy` | clears attributes then deletes the row |
| POST | `/deposits/products/confirm-all` | `deposits.products.confirm-all` | every `suggested` → `confirmed`, then `syncAll()` |
| POST | `/deposits/refresh` | `deposits.refresh` | `refreshSuggestions()` |
| POST | `/deposits/sync` | `deposits.sync` | `syncAll()`; flash totals |

Page (`<x-admin-layout>`, title "Bottle deposits", back link to Barrel
Codes), three cards:
1. **Deposit tiers**: every Udea barrel code with `unit_price > 0`, sorted by
   code: code, description/name, price, "Charge customers" checkbox (submits
   the PATCH on change via a small form, `x-on:change="$el.form.submit()"`),
   till products (`DEP-…` codes or "not created yet"), count of confirmed
   products.
2. **Products**: status filter (`?status=` default `suggested,confirmed`),
   table: product name (link to the product edit page if one exists, else
   plain), barcode, Udea code, tier (select; changing submits the PATCH),
   evidence ("54 units in 12 lines, last 2026-07-15"; a red "also seen as
   315 (6 units)" when `conflicting_units > 0`), status pill, till column
   (`synced <time>` / `error: …` / `pending`), actions: Confirm, Reject,
   Remove (manual rows only). Buttons above the table: "Confirm all
   suggested (n)", "Recompute suggestions", "Sync till now".
3. **Add a product**: `<x-product-search mode="picker" name="product_id">`,
   tier select (charge_customer tiers only), Add.
Flash messages for every action. Keep the controller thin: validation in
the two Form Requests, logic in the services.
Tests: 403 without `deliveries.manage`; 200 with it and the three cards
render; toggling a tier on creates till products; confirm → attributes
written on the sqlite POS; reject → cleared; manual add → confirmed and
written; remove → cleared and gone; confirm-all; refresh and sync return
redirects with a flash.
Check: `php artisan test --filter=DepositScreenTest` green; open
`/deposits` on dev as an admin, see the 53 suggestions and the tiers.

### 13. Confirm on dev and sync the test till
Files: none (dev data).
What: on `/deposits`, the Implementer confirms **only** the two spike
products (`8711521947614`, `8714728001004`) and leaves the rest `suggested`
(the owner confirms the list); then `php artisan deposits:sync-pos` and
`php artisan deposits:sync-pos --check`.
Check: `--check` reports the two confirmed rows in sync and no strays; the
`ATTRIBUTES` of `8711521947614` parse to the same three values the spike
wrote (`deposit.id` `80184d44-…`, `Bottle deposit 0.25`, `0.25`); the six
`DEP-*` products are now linked on the tiers (`pos_product_id` set, no new
`DEP-` rows: `Product::where('CODE','like','DEP-%')->count()` is still 6).
Then the owner scans the juice on the test till once (no till restart
needed; attributes are read per scan): deposit line as in cycle 1.

### 14. Docs and formatting
Files: `docs/features/barrel-deposit-tracking.md`, `docs/FEATURES_INDEX.md`,
`docs/deposit/README.md` (Planner-owned: do not edit; the Planner updates it).
What: a "Customer bottle deposits" section in the feature doc (tables,
models, services, commands, screen, the till behaviour in two sentences with
a pointer to `docs/deposit/`), and the "Future Enhancements" list trimmed of
what now exists; a bullet under the Barrel Deposit Tracking entry in the
index. `./vendor/bin/pint` on all changed PHP.
Check: pint reports no changes on a second run.

### 15. Fallback for garbled country codes (parser)
Files: `scripts/invoice-parser/parsers/delivery_udea.py`,
`scripts/invoice-parser/tests/test_delivery_udea.py`.
What: pdfplumber interleaves the quality word with the country code on some
lines, so the description ends `… Bio-quNeLlle 313` or `… NatuDreE 313` and
the `\b[A-Z]{2}` anchor never matches. Add an optional `known_codes`
argument to `split_barrel_code(description, known_codes=None)`: when the
country-code form does not match, and `known_codes` is given, and the
description ends in whitespace followed by one of those codes
(`re.search(r'\s(\d{1,5})$', …)` with the digits in `known_codes`), split
there (strip the code and the whitespace; leave the garbled letters). The
known codes are the codes of the **same document's** "Barrels delivered"
section, so a bare trailing number can only become a deposit code when the
docket itself lists that code. Move the `_parse_barrels_section(text)` call
above the item loop (it reads the whole text and does not depend on the
items) and pass `{b['code'] for b in barrels['items']}`. Tests: the four
real garbled descriptions below with `known_codes={'313','315','9936'}`
→ `313` and the description ending at the garbled word; the same four with
`known_codes=None` → unchanged, `None`; `'… Biologisch NL 315'` with
`known_codes={'313'}` still splits (country form wins); `'315 gram
Wheat-waffles, Billy's farm Biologisch NL'` with `{'315'}` → `None`.

```
cenNt,ie Lt-aangrdapriascrkh Bio-queDllEe 313        (5018611)
d, lemoBnio,l oLgaisncdhpark Bio-quNeLlle 313        (5018612)
le mangBoi,o Ylooguisrc hOrganic NatDurEe 313        (92491)
 sweetieB, iYolooguirs cOhrganic NatuDreE 313        (97433)
```

Check: the Python tests pass; find a stored docket that has one of these
products (`DeliveryItem::where('supplier_code','5018612')->pluck('delivery_id')`,
then `DeliveryDocument::where('delivery_id', …)->value('file_path')` under
`storage/app/private/`), run the parser on it and show that line now carries
`barrel_code 313` and that `barrels.line_units` reconciles with no warning.

### 16. The same fallback in the backfill
Files: `app/Services/Deposits/DepositEvidenceService.php`,
`tests/Feature/DepositEvidenceServiceTest.php`.
What: `splitBarrelCode(string $description, array $knownCodes = [])` mirrors
step 15. `backfillDeliveryItems()` passes, per delivery, the codes of that
delivery's own `delivery_barrels` rows (`DeliveryBarrel::where('delivery_id',
…)->pluck('supplier_code')`), nothing wider. Test: a garbled description
splits when the delivery has a `313` barrel row and stays untouched when it
has none; the waffles line stays untouched either way.
Check: `php artisan test --filter=DepositEvidenceServiceTest` green;
`php artisan deposits:backfill-delivery-items --dry-run` → about 34 lines on
`5018611`, `5018612`, `92491`, `97433` (and nothing else); run it for real;
a second run → 0. `DeliveryItem::whereNull('barrel_code')->whereRaw("description REGEXP '(^|[^0-9])(313|315|9936|10046)$'")->count()`
→ 0 (or list what remains and why).

### 17. Suggestions and docs after the fallback
Files: `docs/features/barrel-deposit-tracking.md`.
What: `php artisan deposits:refresh-suggestions` → the four products appear
as `suggested` (expect 56 rows in total, 3 confirmed). Add one sentence to
the feature doc's Data table or parser note: when the country code is
garbled, the parser and the backfill accept a trailing code only if the
docket's own barrels section lists it. Leave the four rows `suggested` for
the owner.
Check: paste the refresh output and
`ProductDeposit::whereIn('product_code', ['4017943110013','4017943110051','8711521924912','8711521974337'])->get(['product_code','status','sightings_units'])`.

## Verification

1. `scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests/ -v` → the two new files pass, no new failures.
2. `php artisan test --filter="PosProductAttributes|DepositEvidenceService|DepositPosService|DeliveryImportBarrelCode|DepositScreen"` → all green.
3. `php artisan test` → no failures beyond the step 1 baseline list (name any new one).
4. `php artisan deposits:backfill-delivery-items` second run → 0 rows; `deposits:refresh-suggestions` → about 53 rows, counts unchanged on a re-run.
5. `php artisan deposits:sync-pos --check` → two confirmed rows in sync, no strays, three tiers with till products.
6. Dev POS: `Product::where('CODE','like','DEP-%')->count()` = 6; `PRODUCTS` with `ATTRIBUTES LIKE '%deposit.id%'` = 2.
7. Owner: scan the juice on the test till → deposit line appears (as cycle 1).
8. `./vendor/bin/pint --test` clean on changed files; `git status --short` lists only this cycle's files plus the untracked `docs/deposit/`.
9. (rev 3) Python suite green with the step 15 tests; backfill second run 0; no Udea line left whose description ends in a known deposit code; 56 `product_deposits` rows.

## Risks

- **Description regex over-matching.** A product whose description ends in
  two capitals and digits (`… XL 2`) would lose the `2`. The country-code
  anchor makes this rare; the reconciliation warning and the screen's
  evidence counts make it visible. The backfill touches only Udea lines.
- **Product resolution.** A product with no `SupplierLink` and no
  `delivery_items.product_id` cannot be suggested; the command reports those
  codes so the owner can add the product by hand.
- **Price changes.** `storeBarrelItems` updates `unit_price` on import; the
  delivery hook's `syncAll()` then rewrites `deposit.price` and `PRICESELL`.
- **Write scope on the POS.** The service is the only writer; tests run on
  sqlite, dev runs against the test till's database. Production is untouched
  until cycle 3.
- **Scale of `syncAll`.** ~55 rows plus one `LIKE` scan over 10k products:
  well under a second; no queue needed.

## Review

Planner, 2026-10-03, revision 2 report. Read to the end; re-ran the suites;
read both services, the attributes helper, the controller, the requests, the
migrations, the parser diffs and the docs diff; checked the dev databases.

### Criteria

| Criterion | Result |
|---|---|
| Parser emits `barrel_code`, cleans the description, reconciles | Pass. Delivery 132: ten lines, `line_units` `{313: 54, 315: 12, 9936: 30, 10046: 12}`, no warning. `split_barrel_code` / `reconcile_barrel_codes` are static and tested (18 tests) |
| Accounting parser emits `barrel_code` | Pass (3 tests on real Factuur lines; `extra` kept, `None` elsewhere) |
| Schema and models | Pass. Four migrations as specified; named unique index; `restrictOnDelete` on the tier FK; `nullOnDelete` on `confirmed_by` |
| Import path stores the code | Pass. One-line changes in `convertToDeliveryItems` and `importFromPdfData`; `DeliveryImportBarrelCodeTest` green |
| Attributes helper | Pass. Byte-exact format, foreign keys preserved, `libxml` errors silenced; spike blob round-trips |
| Evidence service | Pass. Per-product merge, dominant code by units then lines, confirmed/rejected keep their tier, `tier_off` and `unmatched` counted; backfill idempotent (second run 0) |
| POS service | Pass. Adopts the six spike products by `CODE` (still 6 `DEP-*` rows), writes only the three keys, refuses scale/vprice, strays cleared, `check()` exits non-zero on drift; category and catalogue rows reused |
| Hook | Pass. After `storeBarrelItems`, Udea only, `try/catch (\Throwable)`; re-run on delivery 132 changed nothing |
| Commands | Pass. All three re-run by the Planner: backfill 0, refresh 52 unchanged, sync dry-run nothing, `--check` 3 in sync / 0 drift / 0 strays / 3 tiers on the till, exit 0 |
| Screen | Pass. Eight routes inside `permission:deliveries.manage`; Form Requests with `exists:App\Models\…`; Alpine uses `x-on:` only; no `x-show`; 10 tests. The owner confirmed a third product (Montcalm water 5 l) from the screen after the report, which the sync picked up: that is the screen working as intended |
| Tests | Pass. 40 PHP, 21 Python; full suite 15 failed / 982 passed, the 15 being the step 1 baseline exactly |
| Docs, pint | Pass. Feature doc section and index bullet; `pint --test` clean |
| Owner's till scan (Verification 7) | **Pending**: the owner has not yet reported scanning the juice on the test till |

### Deviations

All eight **accepted**. In particular: 2 (units fall back to
`ordered_quantity` when the delivered quantity is the default 0) is a real
data fact the plan missed; 4 and 5 (tier switch-on refreshes and syncs;
`syncAll` gives every charged tier its products) make the screen honest;
6 (merge per resolved till product) is more correct than the plan; 8 (a tier
price change yields a new `DEP-<cents>` pair and leaves the old refund
button) is the right call for refunds of bottles sold at the old price, and
cycle 3's reconciliation must group by tier, not by till product.

### Notes for Planner

1. Garbled country codes hide four products (34 lines): **fixed now**, steps
   15–17 in this revision, with the fallback scoped to the codes in the same
   docket's barrels section so a bare trailing number cannot become a
   deposit by accident.
2. `36214` waffles was a false positive of the investigation's REGEXP
   ("315 gram"): **accepted**; the README's facts are corrected.
3. Tier 10046 (0.15 "Flesje/Blik") is on 16 products: **owner decision**,
   one click on `/deposits`; nothing to build.
4. Jars and non-drinks among the suggestions: **accepted**; evidence-backed,
   the owner reviews them on the screen.
5. A deposit property added by hand on the till is cleared as a stray on the
   next Udea import: **deferred to cycle 3** (rollout notes).
6. Old `DEP-*` pairs after a tier price change: **deferred to cycle 3**
   (reconciliation groups by tier).
7. Ten, not nine, deposit lines on the delivery 132 PDF, and 13 in the
   Laravel copy: **accepted**, the plan's count was from a quick look; the
   Laravel rows come from a different import of the same order, no action.

### For the owner

- Scan the juice (`8711521947614`) once on the test till and confirm the
  deposit line appears; that closes Verification 7.
- The screen is live on dev at `/deposits`: 49 suggestions wait for review
  (plus the four garbled ones after step 17). "Confirm all suggested" is
  there if the list looks right; the tier 10046 switch is yours too.

### Revision 3 review (Planner, 2026-10-04)

Steps 15–17 verified against the code and the data, not just the report.

| Criterion | Result |
|---|---|
| Parser fallback scoped to the docket's barrels section | Pass. `split_barrel_code(description, known_codes)`; barrels parsed before the item loop; 28 Python tests; delivery 139's garbled Landpark line → `313`, `line_units` reconcile, no deposit warning |
| Backfill fallback scoped to the delivery's own `delivery_barrels` | Pass. Same split in PHP (`PREG_OFFSET_CAPTURE`, codes compared as strings); 11 evidence tests; dry run 0 on re-run |
| Nothing false-positive | Pass. The Implementer's sweep over 122 Udea dockets shows the fallback fired only on the four known products; the waffles have no code |
| Suggestions | Pass. 56 rows: 4 confirmed (the owner's), 52 suggested, the four garbled products among them with their evidence |
| Suites | Pass. Python 205; deposit filter 42; full suite still the 15 baseline failures; pint clean |
| Till | Pass. `--check`: 4 in sync, 0 drift, 0 strays, 3 tiers on the till; 6 `DEP-*` rows, 4 attribute blobs |
| Owner's juice scan (Verification 7) | **Still to be reported by the owner**; the attributes on the juice are byte-identical to the spike's that passed cycle 1 test a, so nothing in this cycle changed what the till reads |

Deviations (rev 3): none. The ten lines left uncoded are on deliveries from
before barrel tracking existed (first barrels row 2026-01-27) and have nothing
to vouch for them: **accepted** as is.

Notes (rev 3):
1. Widening the rule for the ten pre-tracking lines: **rejected**. They only
   lower four products' evidence counts; no suggestion is missing, and the
   docket-scoped rule is the one that cannot misfire.
2. Three dockets off by 12 / 5 / 12 units on 313 with every line captured:
   **deferred to cycle 3** (reconciliation; likely under-delivery or
   case-versus-bottle counts).
3. Reconciliation warnings survive only as a count in the import flash:
   **deferred to cycle 3**, which should keep deposit mismatches visible on the
   delivery page (the owner asked about this).

**ACCEPTED.** Archive: `mkdir -p docs/deposit/archive/2026-10-04-cycle-2-app-side`
and move `plan.md` and `implemented.md` there. `docs/deposit/scripts/` stays.
