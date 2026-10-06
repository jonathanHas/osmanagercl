# Cycle 2 — Identify deposit products and mark them on the till (app side) — implementation

Status: DONE
Plan revision: 3
Implementer: Opus
Date: 2026-10-03

## Baseline
HEAD: 36207bbf
Pre-existing dirty files (not mine):
```
 M docs/features/invoice-parser-integration.md
 M scripts/invoice-parser/invoice_parser_laravel.py
?? docs/deposit/
?? scripts/invoice-parser/parsers/glennon.py
?? scripts/invoice-parser/tests/fixtures/glennon/
?? scripts/invoice-parser/tests/test_glennon.py
```

## Steps
### 1. Baseline — done
`php artisan test`: `Tests: 15 failed, 942 passed (4173 assertions)`. Baseline failures:
```
Tests\Unit\UdeaScrapingServiceTest (7): get product data returns cached result, successful product scraping,
  failed authentication returns null, network error returns null, no product data found returns null,
  test connection success, parse product data
Tests\Feature\CashReconciliationTest (3): admin / manager / employee … cash reconciliation page
Tests\Feature\FruitVegLabelPrintingTest (2): print labels clears queue and records batch, restore last printed batch requeues products
Tests\Feature\ProductTest (2): shows 404 for non existent product, product statistics are displayed
Tests\Feature\TestScraperControllerTest (1): guzzle test page displays with data
```
Python: `scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests/ -q` → `174 passed`.

### 2. Delivery parser captures the barrel code — done
Changed: `scripts/invoice-parser/parsers/delivery_udea.py` (module pattern
`BARREL_CODE_SUFFIX_REGEX`; static `split_barrel_code()` and
`reconcile_barrel_codes()`; `parse_invoice` splits the description before
`product_name`, adds `barrel_code` to every item, sets
`barrels.line_units`, appends reconciliation warnings),
`scripts/invoice-parser/tests/test_delivery_udea.py` (new; did not exist).
Check output:
```
test_delivery_udea.py: 18 passed
delivery 132 PDF: success True, items 186, every item has the barrel_code key
92504 313 12 | eapple, Your OrganBicio Nlogaitsucrhe DE
92953 315 6  | asta tricolore, Wisselwaar Biologisch NL
94741 313 6  | eet-juice, Luna e Terra Bio-Dynamisch DE
94761 313 12 | ango-juice, Luna e TerraBio-Dynamisch DE
94774 313 12 | h sea salt, Luna Bei oT-Deyrrnaamisch DE
94815 315 6  | ram Mixed-Nuts, Wisselwaar Biologisch NL
5015172 313 12 | ter, Icelandic Glacial Niet-agrarisch NL
5018597 9936 6 | arb-Spritzer, Fritz-spritz Biologisch NL
6000800 10046 12 | n fruit elderflowBeior,lo Lgoisucther NL
6001614 9936 24 | pplespritzer, Fritz-spritz Biologisch NL
line_units {'313': 54, '315': 12, '9936': 30, '10046': 12}
section    [('7',1),('44',2),('69',3),('71',1),('313',54),('315',12),('9936',30),('10046',12),('10186',3)]
warnings   ['Price mismatch for 88927: 1×9.79×1.0=9.79, actual=4.88']   (pre-existing, unrelated; no deposit warning)
```

### 3. Accounting invoice parser emits the barrel code — done
Changed: `scripts/invoice-parser/parsers/invoice_udea.py` (`'barrel_code': extra`
in the `extended_pattern` branch, `'barrel_code': None` in the six other
`line_item` dicts of `_extract_product_lines`),
`scripts/invoice-parser/tests/test_invoice_udea.py` (new; did not exist).
Entry point: `invoice_parser_laravel.py` maps Udea to `parsers/udea.py`, which
only reads header totals; the line items come from `invoice_udea.py`
(`InvoiceParsingService`, `InvoiceBulkUploadController`, `RtdController` call
it directly), so the test calls `InvoiceUdeaParser()._extract_product_lines()`.
The test lines are the real pdfplumber lines from `UdeaFactuur1148558.pdf`
(date merged with the article code), not the plan's spaced version, which no
pattern matches.
Check output:
```
test_invoice_udea.py: 3 passed
full suite: 195 passed (174 baseline + 18 + 3)
UdeaFactuur1148558.pdf: 241 lines, every line has the key; with a code:
94742 313 | 94761 313 | 33649 315 | 45762 313 | 97289 313
```
(`5018612 … lemon,B Lioalongdispcahrk3 B03io2-2q NL 313` has a garbled Gb.rek,
falls through to a fallback branch and gets `None`; see Notes.)

### 4. Laravel schema and models — done
Changed: four migrations `database/migrations/2026_10_03_00000{1,2,3,4}_*.php`
(new, names as in the plan), `app/Models/DeliveryItem.php` (`barrel_code`
fillable), `app/Models/BarrelCode.php` (fillable, `charge_customer` cast,
`productDeposits()`), `app/Models/DepositSighting.php` (new, plain; constant
`SOURCE_DELIVERY_ITEM`), `app/Models/ProductDeposit.php` (new; status/source
constants, `barrelCode()`, `product()`, `confirmedBy()`, `suggested()` /
`confirmed()` scopes, `isManual()`).
`delivery_items.barrel_code` placed after `description`; the sightings unique
index is named `deposit_sightings_source_unique` (the default name is over
MySQL's 64-char limit).
Check output:
```
2026_10_03_000001_add_barrel_code_to_delivery_items .......... DONE
2026_10_03_000002_add_customer_deposit_columns_to_barrel_codes  DONE
2026_10_03_000003_create_deposit_sightings_table ............. DONE
2026_10_03_000004_create_product_deposits_table .............. DONE
tinker schema check → ok
```

### 5. Import paths store the code — done
Changed: `app/Services/DeliveryParsingService.php` (`'barrel_code' => $item['barrel_code'] ?? null`
in `convertToDeliveryItems()`), `app/Services/DeliveryService.php`
(`'barrel_code' => $record['barrel_code'] ?? null` on the `DeliveryItem::create`
in `importFromPdfData()`).
Check output:
```
No syntax errors detected in app/Services/DeliveryParsingService.php
No syntax errors detected in app/Services/DeliveryService.php
```

### 6. Attributes helper — done
Changed: `app/Support/PosProductAttributes.php` (new; did not exist),
`tests/Unit/PosProductAttributesTest.php` (new). Parsing uses
`libxml_use_internal_errors` so garbage never warns; the DOCTYPE is not
fetched (libxml does not load external DTDs by default).
Check output:
```
php artisan test --filter=PosProductAttributesTest → Tests: 10 passed (18 assertions)
extra: serialize(parse(<spike blob on 8711521947614>)) === the blob → "spike blob reproduces byte-for-byte"
```

### 7. Evidence and suggestions service — done
Changed: `app/Services/Deposits/DepositEvidenceService.php` (new),
`tests/Concerns/CreatesDepositPosTables.php` (new),
`tests/Feature/DepositEvidenceServiceTest.php` (new).
- `splitBarrelCode()` (static, the PHP twin), `recordDelivery()`,
  `backfillDeliveryItems(bool $dryRun)` (chunked over Udea lines with
  `barrel_code` null; returns `rows`, `deliveries`, `codes`, `sightings`),
  `refreshSuggestions()` (returns `created`, `updated`, `unchanged`,
  `unmatched`, `tier_off`), plus public helpers `resolveProduct()`,
  `tierFor()`, `udeaSupplierIds()`.
- Evidence is merged **per resolved product**, not per supplier code: two Udea
  codes that resolve to one till product would otherwise overwrite each
  other's counts on the single `product_deposits` row (`product_id` is
  unique). Tie-break for the dominant code: most units, then most lines.
- `last_seen_on` is the latest sighting of the product under any code.
- `recordDelivery()` returns sightings created or changed, so a re-run is 0.
Check output:
```
php artisan test --filter=DepositEvidenceServiceTest → Tests: 7 passed (54 assertions)
```

### 8. POS sync service — done
Changed: `app/Services/Deposits/DepositPosService.php` (new),
`tests/Feature/DepositPosServiceTest.php` (new).
- `ensureTierProducts()`, `syncProduct(row, dryRun)`, `syncAll(dryRun)`
  (returns `totals` and a `changes` list for the command), `check()`, and
  public `price()` / `chargeCode()`.
- `syncProduct` compares only the three deposit entries, so a product whose
  deposit is already right is `unchanged` and is not rewritten. Clearing a
  product with no deposit keys is `unchanged` (a foreign blob is never
  rewritten). Outcomes: `written | unchanged | cleared | refused | missing`.
- `syncAll` treats as stray only till products with deposit properties and no
  `product_deposits` row at all (rows of any status were just handled by
  `syncProduct`); `check()` treats as stray anything with deposit properties
  and no **confirmed** row, as the plan says.
Check output:
```
php artisan test --filter=DepositPosServiceTest → Tests: 9 passed (65 assertions)
```

### 9. Hook into the delivery import — done (live check run after step 13)
Changed: `app/Services/Deposits/DepositEvidenceService.php`
(`afterDeliveryImport()`: `recordDelivery` → `refreshSuggestions` →
`DepositPosService::syncAll()` inside `try/catch (\Throwable)`, warning
`Deposit evidence hook failed`; an info log line on success; `isUdeaSupplier()`),
`app/Http/Controllers/DeliveryController.php` (constructor-injected
`DepositEvidenceService`; called in `storePdf()` right after the
`storeBarrelItems` block when the supplier is a Udea id).
`InvoiceParsingService` untouched.
Check output:
```
No syntax errors detected in app/Http/Controllers/DeliveryController.php
No syntax errors detected in app/Services/Deposits/DepositEvidenceService.php
test_after_delivery_import_never_throws (POS supplier_link table dropped → warning logged, no exception) ✓
```
The tinker run on delivery 132 is in step 13 (see Deviations: running it
here would have run a real `syncAll` before anything was confirmed).

### 10. Commands — done
Changed: `app/Console/Commands/DepositsBackfillDeliveryItems.php`,
`DepositsRefreshSuggestions.php`, `DepositsSyncPos.php` (all new). Not scheduled.
`deposits:sync-pos` exits non-zero when something was refused or is missing
from the till; `--check` exits non-zero on drift, strays or a tier without
till products.
Check output (dev, in order):
```
1. deposits:backfill-delivery-items --dry-run
   313: 456 | 315: 117 | 9936: 11 | 10046: 71
   655 line(s) over 57 deliveries would be updated (dry run, nothing written).
   deposits:backfill-delivery-items → 655 line(s) over 57 deliveries updated, 655 sighting(s) written.
   second run → 0 line(s) over 0 deliveries updated, 0 sighting(s) written.
   94761 latest: "200 millilitre Apple-mango-juice, Luna e TerraBio-Dynamisch DE" | 313
2. BarrelCode 313/315/9936 (supplier 5) charge_customer = true → 3 rows
3. deposits:refresh-suggestions → suggested: 52
   52 created, 0 updated, 0 unchanged, 16 product(s) on a tier not charged to customers.
   (after the units fix below: 0 created, 39 updated, 13 unchanged; then a re-run 52 unchanged)
   6000800 absent (10046 is off). 36214 (waffles) absent: it never was a deposit product, see Notes.
4. deposits:sync-pos --dry-run
   8714728001004 Ommelanden Buttermilk 1l             would be cleared
   8711521947614 Luna e Terra Apple-mango juice 200ml would be cleared
   Dry run, nothing written: 0 written, 2 cleared, 0 stray cleared, 50 unchanged, 0 refused, 0 missing from the till.
```
The plan expected about 620 lines and 53 products. The differences:
- 313 is 456, not 490. The plan's REGEXP matched the code anywhere in the
  description. The `(^|[^0-9])313([^0-9]|$)` hits that the split misses are 36
  lines on 5 supplier codes. 35 have a garbled country code before the number
  (`… NatuDreE 313`, `… Bio-quNeLlle 313`; codes 97433, 92491, 5018611,
  5018612). One is `36214` "315 gram Wheat-waffles": its 315 is the pack size,
  not a deposit.
- 10046 is 71 lines over 16 products, not just 6000800. They are all 0.15
  "Flesje/Blik" drinks; the tier is off, so they make no suggestions.
- 52 suggestions instead of 53, because the waffles drop out.

Units fix (Deviation 2): the first refresh showed buttermilk at 24 units over
38 lines. `invoice_delivered_quantity` is 0 on every line imported before
about 2026-02 (the column defaults to 0). Sightings now use
`invoice_delivered_quantity ?: ordered_quantity`. I re-recorded every
delivery with a code (280 sightings changed), and buttermilk is now 49 units
over 38 lines.

### 11. Import path test — done
Changed: `tests/Feature/DeliveryImportBarrelCodeTest.php` (new). Real
`convertToDeliveryItems()` → `importFromPdfData()`, `Queue::fake()` for the
barcode job of the unlinked item.
Check output:
```
php artisan test --filter=DeliveryImportBarrelCodeTest → Tests: 1 passed (7 assertions)
```

### 12. The screen — done
Changed: `app/Http/Controllers/DepositController.php` (new),
`app/Http/Requests/StoreProductDepositRequest.php` (new),
`app/Http/Requests/UpdateProductDepositRequest.php` (new),
`resources/views/deposits/index.blade.php` (new), `routes/web.php` (the eight
routes inside the `permission:deliveries.manage` group, after the barrel-code
routes; `use App\Http\Controllers\DepositController`),
`resources/views/barrel-codes/index.blade.php` (one link in the header),
`resources/views/deliveries/index.blade.php` (one "Bottle Deposits" button
beside "Barrel Codes"), `tests/Feature/DepositScreenTest.php` (new).
Supporting additions: `ProductDeposit::decide($status, $user)` (sets
`confirmed_by` / `confirmed_at` on confirm, clears them otherwise) and
`DepositEvidenceService::evidenceDetails($rows)` (the Udea code per row and
the "also seen as 315 (6 units)" breakdown), so the controller stays thin.
- Tiers card: every Udea barrel code with `unit_price > 0`, sorted
  numerically by code.
- The tier toggle submits a hidden `0` and the checkbox `1`.
- Switching a tier **on** runs `ensureTierProducts`, then
  `refreshSuggestions()` and `syncAll()`. The last two are beyond the plan
  (Deviation 4), so its products appear without a second click.
- Manual add accepts only tiers with `charge_customer` (`Rule::exists(BarrelCode::class)->where(...)`).
  On a product that already has a row, it confirms that row in place and
  keeps its `source`.
- Remove sets the row to rejected, syncs (clears the till), then deletes it.
  The Remove button shows on manual rows only.
- `x-on:change="$el.form.submit()"` with `x-data` on each small form; no
  Blade-colliding `@` shorthands.
Check output:
```
php artisan test --filter=DepositScreenTest → Tests: 10 passed (59 assertions)
php artisan route:list --name=deposits → 8 routes
```
Browser (after the owner signed in): `/deposits` renders all three cards.
- Tiers: 23 Udea codes with a price; 313 / 315 / 9936 are On.
- Products: 52 suggestions with their evidence, e.g. "Ommelanden Buttermilk
  1l 8714728001004 45751 · 315 €0.70 · 49 units in 38 lines, last 2026-08-17".
- "Confirm all suggested (52)"; the Add card has the product picker and the
  three charged tiers.
- Clicking Confirm on the juice row reloads with the flash
  "8711521947614: confirmed (unchanged on the till)." The row moves to
  `confirmed` with "synced 0 seconds ago".
- No console errors.
- I then changed the evidence text to pluralise ("1 unit in 1 line").

### 13. Confirm on dev and sync the test till — done (owner's scan pending)
On `/deposits` I clicked Confirm on **only** `8711521947614` (juice) and
`8714728001004` (buttermilk); the other 50 stay `suggested`. Both rows have
`confirmed_by` 5 and `pos_synced_at` set, with no error. The juice was
"unchanged on the till", because the spike's blob already had the same values.
Check output:
```
$ php artisan deposits:sync-pos
0 written, 0 cleared, 0 stray cleared, 52 unchanged, 0 refused, 0 missing from the till.
$ php artisan deposits:sync-pos --check          (first run)
| 8714728001004 | Ommelanden Buttermilk 1l             | efc616db-… | efc616db-… | 0.70 | 0.70 | yes |
| 8711521947614 | Luna e Terra Apple-mango juice 200ml | 80184d44-… | 80184d44-… | 0.25 | 0.25 | yes |
| 9936 | 0.10 | — | — | NO |
2 in sync, 0 drifted, 0 stray(s), 1 tier(s) without till products.   exit 1
```
Tier 9936 was switched on with tinker (step 10.2), and till products were
only created for a tier when a confirmed row needed them. `syncAll()` now
first makes sure every tier with `charge_customer` has its products
(Deviation 5; new test `test_sync_all_gives_every_charged_tier_its_till_products`).
After that:
```
$ php artisan deposits:sync-pos --check
| 313  | 0.25 | 80184d44-d2ca-4a05-b243-3e5166612849 | 38fe8fc8-3c2d-4ca1-a75e-02c07cf81a71 | yes |
| 315  | 0.70 | efc616db-9852-448b-992e-744482c48d40 | d2ce3418-0a49-43ff-a70f-7de3f8b6fd22 | yes |
| 9936 | 0.10 | c6f9016e-1a76-4977-ab75-1ac230126f78 | d3cb7179-dbc8-4771-b3ab-cdc0eea7b524 | yes |
2 in sync, 0 drifted, 0 stray(s), 0 tier(s) without till products.   exit 0
juice ATTRIBUTES → {"deposit.id":"80184d44-d2ca-4a05-b243-3e5166612849","deposit.name":"Bottle deposit 0.25","deposit.price":"0.25"}
Product::where('CODE','like','DEP-%')->count() → 6     (all six spike products adopted by CODE, none new)
PRODUCTS with ATTRIBUTES LIKE '%deposit.id%' → 2
PRODUCTS_CAT rows for DEP-* → 3 (the refund buttons)
```
Step 9's check, run here: `afterDeliveryImport(Delivery::find(132))` →
sightings before 655, after 655. Log line:
`Deposit evidence recorded for delivery {"delivery_id":132,"sightings":0,"suggestions_created":0,"pos":{"written":0,"unchanged":52,…}}`.

**Owner: scan the juice (`8711521947614`) once on the test till.** No till
restart is needed. Expected: the deposit line as in cycle 1.

### 14. Docs and formatting — done
Changed: `docs/features/barrel-deposit-tracking.md` (new "Customer Bottle
Deposits (2026-10)" section: data, services, commands, screen, till
behaviour, link to `docs/deposit/`; Future Enhancements: removed "Freight
Extraction", because deliveries already store `freight_charge` from the
parser's costs section, and added the customer deposit float to the
reconciliation item), `docs/FEATURES_INDEX.md` (one bullet under Barrel
Deposit Tracking). `docs/deposit/README.md` untouched.
Before running pint I checked that every pre-existing PHP file I changed was
pint-clean at HEAD, so pint touched only my lines.
Check output:
```
./vendor/bin/pint <27 changed PHP files> → FIXED 27 files, 1 style issue fixed (routes/web.php ordered_imports)
./vendor/bin/pint --test <same files> → PASS 27 files
```

## Deviations

1. **Step 3 test lines.** The plan's sample line (date, a space, then the
   article code) matches none of the parser's patterns. Real pdfplumber output
   merges the date with the article code (`24.09.2694761 …`), so the test uses
   two real lines from `UdeaFactuur1148558.pdf`. It also calls
   `InvoiceUdeaParser()._extract_product_lines()` directly: the Laravel
   wrapper's Udea entry point (`parsers/udea.py`) reads only header totals.
2. **Sighting units.** The plan says `invoice_delivered_quantity` if set, else
   `ordered_quantity`. That column is non-null with a default of 0, and it is 0
   on every line imported before about 2026-02. Sightings now use
   `invoice_delivered_quantity ?: ordered_quantity`
   (`test_units_fall_back_to_ordered_when_the_delivered_quantity_is_zero`).
3. **Step 9's live check ran in step 13.** Running `afterDeliveryImport` before
   anything was confirmed would have done a real `syncAll`, which clears the
   two spike products and conflicts with step 10.4's "do not run it for real
   yet".
4. **Switching a tier on also runs `refreshSuggestions()` and `syncAll()`**,
   besides `ensureTierProducts`. Otherwise its suggestions would only appear
   after a second click on "Recompute suggestions".
5. **`syncAll()` makes sure every charged tier has its till products** before
   syncing rows. Without this, a tier switched on by any route other than the
   screen (as in step 10.2) had no `DEP-*` products, and Verification 5
   ("three tiers with till products") failed.
6. **Evidence is merged per resolved till product**, not per supplier code
   (step 7). This keeps two Udea codes for one product from overwriting each
   other's counts on its single `product_deposits` row.
7. **`syncProduct` compares only the three deposit entries.** It does not
   rewrite a blob whose deposit is already right, and it never re-serialises a
   foreign blob that has no deposit keys.
8. **Tier price change → new till products.** `DEP-<cents>` comes from the
   price, so a new tier price makes `ensureTierProducts` adopt or create
   `DEP-<new cents>` and repoint the tier. It does not rename the old products.
   The old `DEP-*-RET` button stays, so bottles sold at the old price can be
   refunded at it. "Updates the price" in the plan's test is implemented as
   correcting a `DEP-*` product whose `NAME` or `PRICESELL` drifted from its
   code's price.

## Verification

1. `scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests/ -v`
   → `195 passed` (baseline 174 + `test_delivery_udea.py` 18 + `test_invoice_udea.py` 3).
2. `php artisan test --filter="PosProductAttributes|DepositEvidenceService|DepositPosService|DeliveryImportBarrelCode|DepositScreen"`
   → `Tests: 40 passed (210 assertions)`.
3. `php artisan test` → `Tests: 15 failed, 982 passed (4383 assertions)`. The
   15 failures are exactly the step 1 baseline list (UdeaScrapingServiceTest 7,
   CashReconciliationTest 3, FruitVegLabelPrintingTest 2, ProductTest 2,
   TestScraperControllerTest 1). No new failures.
4. `deposits:backfill-delivery-items` → `0 line(s) over 0 deliveries updated, 0 sighting(s) written.`
   `deposits:refresh-suggestions`, run twice → `0 created, 0 updated, 52 unchanged, 16 product(s) on a tier not charged to customers.`
   (52 rows, not about 53: the waffles were a false positive; see step 10.)
5. `deposits:sync-pos --check` → 2 confirmed rows in sync, no strays, three
   tiers with till products, exit 0 (output in step 13).
6. Dev POS: `DEP-%` count = 6; `ATTRIBUTES LIKE '%deposit.id%'` = 2.
7. Owner scan of the juice on the test till → **pending**.
8. `./vendor/bin/pint --test` on the 27 changed PHP files → `PASS 27 files`.
   `git status --short` below.

## Files changed
```
 M app/Http/Controllers/DeliveryController.php
 M app/Models/BarrelCode.php
 M app/Models/DeliveryItem.php
 M app/Services/DeliveryParsingService.php
 M app/Services/DeliveryService.php
 M docs/FEATURES_INDEX.md
 M docs/features/barrel-deposit-tracking.md
 M docs/features/invoice-parser-integration.md          (pre-existing, not mine)
 M resources/views/barrel-codes/index.blade.php
 M resources/views/deliveries/index.blade.php
 M routes/web.php
 M scripts/invoice-parser/invoice_parser_laravel.py     (pre-existing, not mine)
 M scripts/invoice-parser/parsers/delivery_udea.py
 M scripts/invoice-parser/parsers/invoice_udea.py
?? app/Console/Commands/DepositsBackfillDeliveryItems.php
?? app/Console/Commands/DepositsRefreshSuggestions.php
?? app/Console/Commands/DepositsSyncPos.php
?? app/Http/Controllers/DepositController.php
?? app/Http/Requests/StoreProductDepositRequest.php
?? app/Http/Requests/UpdateProductDepositRequest.php
?? app/Models/DepositSighting.php
?? app/Models/ProductDeposit.php
?? app/Services/Deposits/                               (DepositEvidenceService.php, DepositPosService.php)
?? app/Support/PosProductAttributes.php
?? database/migrations/2026_10_03_000001_add_barrel_code_to_delivery_items.php
?? database/migrations/2026_10_03_000002_add_customer_deposit_columns_to_barrel_codes.php
?? database/migrations/2026_10_03_000003_create_deposit_sightings_table.php
?? database/migrations/2026_10_03_000004_create_product_deposits_table.php
?? docs/deposit/                                        (pre-existing folder; mine: implemented.md)
?? resources/views/deposits/
?? scripts/invoice-parser/parsers/glennon.py            (pre-existing, not mine)
?? scripts/invoice-parser/tests/fixtures/glennon/       (pre-existing, not mine)
?? scripts/invoice-parser/tests/test_delivery_udea.py
?? scripts/invoice-parser/tests/test_glennon.py         (pre-existing, not mine)
?? scripts/invoice-parser/tests/test_invoice_udea.py
?? tests/Concerns/CreatesDepositPosTables.php
?? tests/Feature/DeliveryImportBarrelCodeTest.php
?? tests/Feature/DepositEvidenceServiceTest.php
?? tests/Feature/DepositPosServiceTest.php
?? tests/Feature/DepositScreenTest.php
?? tests/Unit/PosProductAttributesTest.php
```
Dev data changed: migrations run on the Laravel database; 655
`delivery_items` updated (`barrel_code` set, description trimmed); 655
`deposit_sightings`; `charge_customer` on for 313 / 315 / 9936 (supplier 5)
with their till product ids; 52 `product_deposits` (2 confirmed). On the dev
POS: no new rows (all six `DEP-*`, the category and the three catalogue rows
were adopted from the spike); the two spike `ATTRIBUTES` are unchanged.

## Notes for Planner

1. **Garbled country codes hide deposit products.** 35 Udea lines on 4 supplier
   codes have pdfplumber-interleaved country letters before the deposit code:
   `97433` (… NatuDreE 313), `92491` (… NatDurEe 313), `5018611` and `5018612`
   (Landpark Bio-quelle waters, … Bio-quNeLlle 313 / … Bio-quDeElle 313). The
   plan's pattern (and the Python twin) needs `\b[A-Z]{2}` right before the
   digits, so neither the backfill nor future imports capture these. A
   fallback anchored on the known per-unit codes, e.g. `\s(313|315|9936|10046)$`
   on Udea lines, would catch them. Until then the owner can add these products
   by hand on `/deposits`. The accounting parser misses the same kind of line
   (`5018612` on `UdeaFactuur1148558.pdf` falls through to a fallback branch).
2. **36214 (Billys Farm waffles) is not a deposit product.** The investigation's
   "315 on 2 of 3 lines" came from the pack size "315 gram". README's facts
   section can drop it.
3. **10046 (€0.15 "Flesje/Blik") is on 16 products**, 71 lines: Whole Earth
   sodas, Charlie's waters, Gutsy kombucha, Kéfir Eau, Billy bliss beer, Why
   Not soda, and others. The tier is off, so none are suggested. The owner may
   want to switch it on; the screen does that in one click, adopting or
   creating `DEP-015` / `DEP-015-RET`.
4. **Some suggestions look like jars or non-drinks**, which the codes
   support: Wisselwaar pasta, nuts, quinoa, rice and millet (315), green tea
   (315), Beutelsbacher vinegar (313). All are evidence-backed, so they are
   left for the owner's review rather than filtered.
5. **Deposit on a rejected product stays cleared on import.** Every Udea import
   runs `syncAll`, so a `DEP` property added by hand on the till (outside the
   app) to a product without a confirmed row is cleared as a stray. That is
   intended, but worth saying in the cycle 3 rollout notes.
6. **Old till lines on a tier price change.** See Deviation 8. If Udea
   changes 313's price, a new `DEP-0xx` pair appears and the old pair remains.
   Cycle 3's reconciliation should group by tier, not by till product.
7. **The plan's "nine real descriptions"** were really ten on the delivery 132
   PDF (five 313, two 315, two 9936, one 10046). The Laravel copy of delivery
   132 has 13 lines with a code, because it holds lines from a different
   import than the stored PDF. Not investigated further.

---

# Revision 3 (steps 15–17) — garbled country codes

Baseline for revision 3: HEAD `36207bbf` (unchanged); working tree = the
revision 2 files above plus the same pre-existing files (not mine).

### 15. Fallback for garbled country codes (parser) — done
Changed: `scripts/invoice-parser/parsers/delivery_udea.py`:
- new module pattern `BARE_BARREL_CODE_SUFFIX_REGEX` (`\s(?P<code>\d{1,5})$`);
- `split_barrel_code(description, known_codes=None)`: the country form is
  tried first, then the bare code, accepted only if it is in `known_codes`;
- `_parse_barrels_section(text)` now runs before the item loop, and its codes
  (`{b['code'] for b in barrels['items']}`) are passed to every split;
- `Set` added to the typing import.

`scripts/invoice-parser/tests/test_delivery_udea.py`: +10 tests.
- the four real garbled descriptions with `{'313','315','9936'}` → `313`;
- the same four with `None` / `set()` / `{'315'}` → unchanged, `None`;
- the country form wins over `known_codes={'313'}`;
- the waffles with `{'315'}` → `None`.

Check output:
```
test_delivery_udea.py → 28 passed
storage/app/private/deliveries/2026/08/139/e25c7637-4d82-4b56-9458-57458e8d7dab.pdf (delivery 139):
  success True items 182
  5018612 313 12 |  acid, lemoBnio,l oLgaisncdhpark Bio-quNelLle
  line_units {'313': 42, '10046': 24, '9936': 6}
  section    [('2',1),('18',2),('27',1),('34',6),('44',7),('69',1),('71',2),('309',1),('313',42),('9936',6),('10046',24),('10154',2),('10186',10)]
  deposit warnings []
```
Extra check: I ran the parser over all 221 stored delivery PDFs (122 parse
as Udea dockets with items) and logged every split that came from the
fallback. It fired on these four products only, never on a crate code (the
barrels sections also list 2, 7, 18, 34, 44, 69, …):
```
fallback 313 x3  | …,ie Lt-aangrdapriascrkh Bio-queNllLe 313   (5018611)
fallback 313 x2  | …moBnio,l oLgaisncdhpark Bio-quNeLlle 313   (5018612)
fallback 313 x17 | …moBnio,l oLgaisncdhpark Bio-quNelLle 313   (5018612)
fallback 313 x2  | …ngBoi,o Ylooguisrc hOrganic NatDurEe 313    (92491)
fallback 313 x1  | …tieB, iYolooguirs cOhrganic NatuDreE 313    (97433)
```
Three dockets still don't reconcile on 313 (deliveries 95: 102 vs 114, 115:
54 vs 59, 136: 36 vs 48). On each, every product line containing "313" is
captured and nothing is unmatched, so these are unit differences, not missed
lines. See Notes.

### 16. The same fallback in the backfill — done
Changed: `app/Services/Deposits/DepositEvidenceService.php`:
- `BARE_BARREL_CODE_SUFFIX`;
- `splitBarrelCode(string $description, array $knownCodes = [])` mirrors step 15;
- `backfillDeliveryItems()` loads each delivery's own `DeliveryBarrel`
  `supplier_code`s, once per delivery and per chunk, and passes only those.

`tests/Feature/DepositEvidenceServiceTest.php`: +2 tests.
- The split cases: garbled + known codes, without codes, country form wins,
  waffles.
- A backfill test: a garbled line splits on a delivery with a 313 barrel row
  and stays untouched on a delivery whose barrels are only `69`; the waffles
  stay untouched on both; a second run gives 0.

Check output:
```
php artisan test --filter=DepositEvidenceServiceTest → Tests: 11 passed (70 assertions)
deposits:backfill-delivery-items --dry-run
  | 313 | 24 |   (per supplier code: 5018611: 3, 5018612: 18, 92491: 2, 97433: 1; nothing else)
  24 line(s) over 21 deliveries would be updated (dry run, nothing written).
deposits:backfill-delivery-items → 24 line(s) over 21 deliveries updated, 24 sighting(s) written.
second run → 0 line(s) over 0 deliveries updated, 0 sighting(s) written.
DeliveryItem::whereNull('barrel_code') (Udea) … REGEXP '(^|[^0-9])(313|315|9936|10046)$' → 10 remain:
  d6  2025-08-05 97433   barrels_rows=0 | …NatuDreE 313
  d13 2025-08-18 5018611 barrels_rows=0 | …Bio-queDllEe 313
  d31 2025-10-20 5018612 barrels_rows=0 | …Bio-quDeElle 313
  d38 2025-11-10 5018612 barrels_rows=0 | …Bio-quDeElle 313
  d43 2025-11-24 5018612 barrels_rows=0 | …Bio-quDeElle 313
  d47 2025-12-08 5018611 barrels_rows=0 | …Bio-queDllEe 313
  d49 2025-12-15 5018612 barrels_rows=0 | …Bio-quDeElle 313
  d54 2026-01-06 5018612 barrels_rows=0 | …Bio-quNeLlle 313
  d56 2026-01-13 5018612 barrels_rows=0 | …Bio-quNeLlle 313
  d58 2026-01-19 5018612 barrels_rows=0 | …Bio-quNeLlle 313
```
Why they remain: all ten are on deliveries imported before barrel tracking
began (2026-01-26), so they have no `delivery_barrels` rows. Under the
"same docket only" rule nothing vouches for the bare 313, and they stay as
they are. This is why the count is 24, not about 34. All four products are
still captured from later deliveries. Their evidence counts are lower than
the full history, but no suggestion is missing.

### 17. Suggestions and docs after the fallback — done
Changed: `docs/features/barrel-deposit-tracking.md` (one sentence in the
`delivery_items.barrel_code` row of the Data table).
Check output:
```
$ php artisan deposits:refresh-suggestions
| confirmed | 4  |
| suggested | 52 |
4 created, 0 updated, 52 unchanged, 16 product(s) on a tier not charged to customers.
re-run → 0 created, 0 updated, 56 unchanged, 16 product(s) on a tier not charged to customers.

ProductDeposit::whereIn('product_code', [...four...]):
  4017943110051 suggested 313 units 348 lines 18  Landpark Mineral-water Carbonated Lemon 750 ml   (5018612)
  4017943110013 suggested 313 units 36  lines 3   Landpark Mineral water slightly sparkling 750ml (5018611)
  8711521924912 suggested 313 units 12  lines 2   Your Organic Nature Fruit-juice apple pineapple mango 700 ml (92491)
  8711521974337 suggested 313 units 6   lines 1   Your Organic Nature Pink grapefruit juice 700ml (97433)
total=56 confirmed=4
```
All four are left `suggested` for the owner.

Confirmed is 4, not the plan's 3: the owner confirmed Hellenaris Mineral
water carbonated 1500 ml (`8717056180205`) on the screen at 15:40, after
Montcalm at 15:39. `deposits:sync-pos --check` → `4 in sync, 0 drifted, 0
stray(s), 0 tier(s) without till products`.

## Deviations (revision 3)

None in behaviour. The plan expected about 34 backfilled lines; there are
24, for the reason in step 16 (10 lines on deliveries that have no barrels
rows).

## Verification (revision 3, full re-run)

1. Python suite → `205 passed` (174 baseline + 28 + 3).
2. Deposit filter → `Tests: 42 passed (223 assertions)`.
3. `php artisan test` → `Tests: 15 failed, 984 passed (4396 assertions)`. The
   15 are the step 1 baseline list exactly; no new failures.
4. Backfill second run → 0 rows; `refresh-suggestions` twice → 56 unchanged.
5. `deposits:sync-pos --check` → 4 confirmed rows in sync (the owner added
   two), no strays, three tiers with till products, exit 0.
6. Dev POS: `DEP-%` = 6; `ATTRIBUTES LIKE '%deposit.id%'` = 4 (one per
   confirmed product).
7. Owner's juice scan on the test till → still **pending**.
8. `./vendor/bin/pint --test` on the 27 PHP files → `PASS 27 files`.
   `git status --short`: no new paths since revision 2; the same files
   changed (`delivery_udea.py`, `test_delivery_udea.py`,
   `DepositEvidenceService.php`, `DepositEvidenceServiceTest.php`,
   `barrel-deposit-tracking.md`).
9. Python suite green with the step 15 tests; backfill second run 0; 10 Udea
   lines still end in a known code, all on pre-2026-01-26 deliveries with no
   barrels rows (listed in step 16); 56 `product_deposits` rows.

## Notes for Planner (revision 3)

1. **Pre-barrel-tracking lines stay uncoded.** 10 garbled lines on deliveries
   before 2026-01-26 have no `delivery_barrels` rows, so the rule leaves them.
   Harmless for suggestions; they only lower those products' evidence counts.
   Widening the rule (e.g. "any code seen on that supplier code's other
   lines") would cover them, but it is your call, not needed.
2. **Three dockets still don't reconcile on 313** (deliveries 95, 115, 136,
   by 12 / 5 / 12 units). No uncaptured product line mentions 313 on them, so
   the gap is a units difference, for example an under-delivery, or a pack
   whose case count differs from its bottle count. Worth a look in cycle 3's
   reconciliation, not a parser problem.
3. **The reconciliation warning only shows in the import preview.** After an
   import, `storePdf` keeps only the warning count in the flash
   ("N warnings - check items"), and the text is lost. If deposit mismatches
   should stay visible, the delivery page would need to keep them. The owner
   asked about this in chat on 2026-10-03.
