# Cycle 3 — Go live: till install command, runbook, warnings on the delivery page, staff procedure — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-10-04

## Baseline
HEAD: 36207bbf
Pre-existing dirty files (cycles 1–2, uncommitted, plus the unrelated
glennon parser / `invoice_parser_laravel.py` / `invoice-parser-integration.md`):
```
 M app/Http/Controllers/DeliveryController.php
 M app/Models/BarrelCode.php
 M app/Models/DeliveryItem.php
 M app/Services/DeliveryParsingService.php
 M app/Services/DeliveryService.php
 M docs/FEATURES_INDEX.md
 M docs/features/barrel-deposit-tracking.md
 M docs/features/invoice-parser-integration.md
 M resources/views/barrel-codes/index.blade.php
 M resources/views/deliveries/index.blade.php
 M routes/web.php
 M scripts/invoice-parser/invoice_parser_laravel.py
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
?? app/Services/Deposits/
?? app/Support/PosProductAttributes.php
?? database/migrations/2026_10_03_00000{1,2,3,4}_*.php
?? docs/deposit/
?? resources/views/deposits/
?? scripts/invoice-parser/parsers/glennon.py
?? scripts/invoice-parser/tests/fixtures/glennon/
?? scripts/invoice-parser/tests/test_delivery_udea.py
?? scripts/invoice-parser/tests/test_glennon.py
?? scripts/invoice-parser/tests/test_invoice_udea.py
?? tests/Concerns/CreatesDepositPosTables.php
?? tests/Feature/DeliveryImportBarrelCodeTest.php
?? tests/Feature/DepositEvidenceServiceTest.php
?? tests/Feature/DepositPosServiceTest.php
?? tests/Feature/DepositScreenTest.php
?? tests/Unit/PosProductAttributesTest.php
```

## Steps
### 1. Baseline — done
`php artisan test` → `Tests: 15 failed, 984 passed (4396 assertions)`. The 15
are the known list: UdeaScrapingServiceTest 7, CashReconciliationTest 3,
FruitVegLabelPrintingTest 2, ProductTest 2, TestScraperControllerTest 1.
Python: `scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests/ -q` → `205 passed`.

### 2. Deployable copies of the till scripts — done
Changed: `resources/pos/deposit/script.Deposit.AddLine.bsh` (new, copied),
`resources/pos/deposit/script.Deposit.Change.bsh` (new, copied),
`resources/pos/deposit/README.md` (new: what the files are, install with
`deposits:install-till`, restart the tills, history and test matrix in
`docs/deposit/`), `docs/deposit/scripts/deposit-spike-setup.php` (part 5 now
reads `resource_path("pos/deposit/$name.bsh")`; `$dir` stays for the XML
files it also writes; header comment updated).
Check output:
```
a4dea667ba39d37025f6a9b5d03330b6  resources/pos/deposit/script.Deposit.AddLine.bsh
0706d00f4aff6b8b6275a3a80eb550ba  resources/pos/deposit/script.Deposit.Change.bsh
No syntax errors detected in docs/deposit/scripts/deposit-spike-setup.php
```

### 3. `deposits:install-till` command — done
Changed: `app/Services/Deposits/DepositTillInstaller.php` (new),
`app/Console/Commands/DepositsInstallTill.php` (new),
`tests/Concerns/CreatesDepositPosTables.php` (`RESOURCES` table),
`tests/Feature/DepositTillInstallerTest.php` (new, 10 tests).
- `target()`, `status()`, `install()`, `rollback()` as specified.
- `status()['scripts'][name]` = `present`, `matches_file`; plus `wired`,
  `anchor_count`, `backup_present`, `installed`.
- `install()` checks every refusal **before** any write:
  - only one of the two event lines present → "partially wired";
  - not wired and the anchor count ≠ 1;
  - wired and no backup row.
  So a refused install writes nothing, not even the backup.
- The backup file goes to `Storage::disk('local')`
  (`storage/app/private/deposit/Ticket.Buttons.<Ymd-His>.pre-deposit.xml`).
- Script rows: insert when no row of that `NAME` exists, update when the
  bytes differ, otherwise `unchanged`.
- `rollback()` keeps the backup row; it reports "already the backup" when
  `Ticket.Buttons` already equals it.
- The command prints the target first. On a non-dev POS without `--force` it
  asks: "Install the deposit till scripts on this POS?" or "Roll back the
  deposit till scripts on this POS?". `--check` never asks. Errors are caught
  and printed with `error()`, exit 1. After install or rollback it prints the
  status table and "Restart uniCenta on every till for this to take effect."
Check output:
```
php artisan test --filter=DepositTillInstallerTest → Tests: 10 passed (64 assertions)
```
Dev (installed by the spike), before:
```
Ticket.Buttons             e202b0e0878fda86aaf847f221626409
Ticket.Buttons.pre-deposit 6310ac80cf46cbcf7f5e2c90e2f87625
script.Deposit.AddLine     a4dea667ba39d37025f6a9b5d03330b6
script.Deposit.Change      0706d00f4aff6b8b6275a3a80eb550ba
$ php artisan deposits:install-till --check
POS: 127.0.0.1:3307/unicenta2016 (dev)
| script.Deposit.AddLine | yes | yes |
| script.Deposit.Change  | yes | yes |
| Ticket.Buttons wired | yes | anchor count | 1 | backup row present | yes | installed | yes |
Installed.                                   exit 0
$ php artisan deposits:install-till
  backup: already present (kept)
  script.Deposit.AddLine: unchanged
  script.Deposit.Change: unchanged
  Ticket.Buttons: already wired
Installed.                                   exit 0
```
Round trip:
```
$ php artisan deposits:install-till --rollback        (dev: no prompt)
  Ticket.Buttons: restored from Ticket.Buttons.pre-deposit
  script.Deposit.AddLine: deleted
  script.Deposit.Change: deleted
  backup: kept
Not installed.                               exit 0
  → Ticket.Buttons 6310ac80…, scripts absent
$ php artisan deposits:install-till --check  → wired no, backup present yes, installed no; "Not installed."  exit 1
$ php artisan deposits:install-till
  backup: already present (kept)
  script.Deposit.AddLine: created
  script.Deposit.Change: created
  Ticket.Buttons: wired
Installed.                                   exit 0
$ php artisan deposits:install-till --check  → Installed.  exit 0
Ticket.Buttons             e202b0e0878fda86aaf847f221626409   (the cycle 1 wired content again)
Ticket.Buttons.pre-deposit 6310ac80cf46cbcf7f5e2c90e2f87625
script.Deposit.AddLine     a4dea667ba39d37025f6a9b5d03330b6
script.Deposit.Change      0706d00f4aff6b8b6275a3a80eb550ba
```
**Owner:** the script rows on dev were deleted and re-created during this
round trip; the content is byte-identical, but please **restart the dev till
once** in the VM and scan the juice (`8711521947614`) once more to confirm
the deposit line still appears.

### 4. Deposit reconciliation kept on the delivery — done
Changed:
- `app/Services/DeliveryService.php`: `recordDepositReconciliation()`, which
  merges `deposit_reconciliation` (`line_units`, `section` summed per code,
  only the `Deposit code …` warnings, `checked_at`) into `import_data`. It
  skips when both arrays are empty.
- `app/Http/Controllers/DeliveryController.php`: `storePdf()` calls it
  right after the deposit-evidence hook (which follows `storeBarrelItems`),
  inside `try/catch (\Throwable)` + `Log::warning('Deposit reconciliation not
  recorded', …)`; `use Illuminate\Support\Facades\Log`. It runs for every
  supplier; the method skips anything without deposit data.
- `resources/views/deliveries/show.blade.php`: the Barrels/Deposits block
  now also renders when there are no barrels but a reconciliation exists.
  - Header: a red "n deposit mismatch(es)" when there are warnings, a green
    "deposit codes match" otherwise.
  - The warnings appear as a red list **under the header, visible without
    expanding** (Deviation 2).
  - Inside the collapsible: "Deposit codes on product lines" (code, units on
    lines, units in barrels section, status `matches` / `differs by n` /
    `not in section`), above the existing barrels table.
  - The block's `@click` became `x-on:click`.
- `tests/Feature/DeliveryDepositReconciliationTest.php` (new, 4 tests): it
  uses the existing `AliasesMysqlConnection` trait, because the delivery page
  loads `KitchenProduct`, which pins the `mysql` connection.

Check output:
```
php artisan test --filter=DeliveryDepositReconciliationTest → Tests: 4 passed (16 assertions)
dev, delivery 132, recorded from the stored PDF's parse:
  import_data keys before ["filename","imported_at","format","source"]
                   after  [..., "deposit_reconciliation"]
  {"line_units":{"313":54,"315":12,"9936":30,"10046":12},
   "section":{"7":1,"44":2,"69":3,"71":1,"313":54,"315":12,"9936":30,"10046":12,"10186":3},
   "warnings":[],"checked_at":"2026-10-04 11:02:18"}
```
Browser, `/deliveries/132`:
- The header reads "Barrels/Deposits: €176.75 (17 items) deposit codes match".
- Show expands to "Deposit codes on product lines": 313 54/54, 315 12/12,
  9936 30/30, 10046 12/12, all `matches`.
- No console errors.

The section comes from the parse's own barrels section, not from the
`DeliveryBarrel` rows (Deviation 1).

### 5. The add-a-product card shows the pick — done
Changed: `resources/views/deposits/index.blade.php`. The form has
`x-data="{ picked: null }"`, `x-on:product-search:selected`, and
`x-on:product-search:cleared`, plus:
- "Selected: <name> (<code>)" (`x-show="picked" x-cloak`, `?.` in the
  `x-text`s);
- the hint "Scan or type a barcode, or type part of the name, then choose a
  result; Enter selects the top match." (`x-show="!picked"`);
- Add has `x-bind:disabled="!picked"`;
- the placeholder became "Scan or type a barcode, or part of the name…";
- `:autofocus="$rows->isEmpty()"`.

`tests/Feature/DepositScreenTest.php`: +1 test (hint and markup strings).
Check output:
```
php artisan test --filter=DepositScreenTest → Tests: 11 passed (66 assertions)
```
Browser (dev, signed in as admin):
- **On load:** the hint is visible and Add is greyed out (disabled).
- **Type `8711521947614`:** the dropdown shows "Luna e Terra Apple-mango
  juice 200ml · 8711521947614 · Udea 94761 · €1.85".
- **Enter:** the box clears, "Selected: Luna e Terra Apple-mango juice 200ml
  (8711521947614)" appears, and Add becomes active.
- **Type `8714728001004` and click the dropdown result (no Enter):**
  "Selected: Ommelanden Buttermilk 1l (8714728001004)". So that works too.
- I didn't click Add for the buttermilk: the tier select was on 313, and Add
  would have re-tiered it from 315.
- **Re-picked the juice (Enter), tier 313, clicked Add:** the flash reads
  "Luna e Terra Apple-mango juice 200ml added (unchanged on the till)." The
  row stays `confirmed`, `invoice`, tier 313, with a new `confirmed_at`
  (2026-10-04 11:04:14). `deposits:sync-pos --check` → 4 in sync.
- No console errors.

### 6. Runbook and staff procedure — done
Changed: `docs/features/barrel-deposit-tracking.md`:
- the `deposits:install-till` line in Commands;
- "At the till" now describes the install command;
- a new "Deposit codes on the delivery page" subsection;
- a new "Going live on production" section: the nine steps in the plan's
  order, each saying who and where, step 5's "stop if the anchor count is
  not 1", and the three notes (`syncAll` clears hand-added properties; sales
  analytics show zero-VAT lines in "Bottle Deposits"; a tier price change
  creates a new `DEP-<cents>` pair).

`docs/deposit/sop-bottle-deposits.md` (new): what the deposit is, with a
table of the three amounts and typical items; selling (nothing to do; remove
the product line, not the deposit); returns (Bottle Deposits → the refund
button once per bottle; a refund-only ticket is paid out in cash); what to
accept; who to ask. The owner should check the last two (marked in the
draft's first line).
Check output:
```
wc -w docs/deposit/sop-bottle-deposits.md → 293 words (well under a page)
```

### 7. Format and tidy — done
```
./vendor/bin/pint on the 8 cycle-3 PHP files (DeliveryService, DeliveryController,
  DepositsInstallTill, DepositTillInstaller, CreatesDepositPosTables,
  DepositTillInstallerTest, DeliveryDepositReconciliationTest, DepositScreenTest) → PASS 8 files
./vendor/bin/pint --test (same) → PASS 8 files
./vendor/bin/pint --test docs/deposit/scripts/deposit-spike-setup.php → PASS 1 file
```

## Deviations

1. **Step 4's dev check took the section from the parse, not from
   `DeliveryBarrel`.** Delivery 132's `DeliveryBarrel` rows come from a
   different import of the same order (313: 13 + 54, 315: 3 + 12, two copies
   of several crate codes; 17 rows), so mixing them with the PDF's
   `line_units` would show false mismatches. Recording from the stored PDF's
   own parse is what `storePdf()` does on a real import.
2. **The reconciliation warnings show without expanding.** They sit under
   the block header (with a red count in the header). The table stays inside
   the collapsible. The point of the feature is that mismatches are noticed.
3. **`install()` checks all its refusal conditions before the first write**,
   including a new "partially wired" refusal (only one of the two event
   lines present). The plan's order (a) backup → (b) scripts → (c) wire
   would have left a backup row and the scripts behind on an anchor failure.
4. **`recordDepositReconciliation()` is called for every supplier**, not
   only inside the Udea branch. It skips anything without deposit data, so
   non-Udea imports are unaffected.

## Verification

1. `php artisan test --filter="DepositTillInstaller|DeliveryDepositReconciliation|DepositScreen"`
   → `Tests: 25 passed (146 assertions)`. The other cycle-2 deposit tests:
   `32 passed`.
2. `php artisan test` → `Tests: 15 failed, 999 passed (4483 assertions)`. The
   15 are the step 1 baseline list exactly.
3. Python suite → `205 passed` (untouched).
4. Dev round trip of `deposits:install-till`: in step 3 (md5s
   `e202b0e0…` / `6310ac80…` / `a4dea667…` / `0706d00f…` restored).
   `--check` now → `Installed.`, exit 0. **Owner: restart the dev till and
   scan the juice once.** Pending.
5. `/deliveries/132` shows the reconciliation table, every row `matches` (step 4).
6. `/deposits` add card: browser check in step 5, with the exact text seen.
7. `pint --test` clean (step 7). `git status --short`: compared with the
   baseline, the new paths are `app/Console/Commands/DepositsInstallTill.php`,
   `app/Services/Deposits/DepositTillInstaller.php` (inside the already
   untracked `app/Services/Deposits/`), `resources/pos/`,
   `tests/Feature/DeliveryDepositReconciliationTest.php`,
   `tests/Feature/DepositTillInstallerTest.php`, and
   ` M resources/views/deliveries/show.blade.php`. The rest are the cycle 1–2
   files and the known unrelated ones.

## Files changed (cycle 3)
```
 M app/Http/Controllers/DeliveryController.php        (already modified by cycle 2; + reconciliation call, Log import)
 M app/Services/DeliveryService.php                   (already modified by cycle 2; + recordDepositReconciliation)
 M docs/features/barrel-deposit-tracking.md           (already modified by cycle 2; + runbook etc.)
 M resources/views/deliveries/show.blade.php          (new in the diff)
 M resources/views/deposits/index.blade.php           (untracked since cycle 2; add card)
?? app/Console/Commands/DepositsInstallTill.php
?? app/Services/Deposits/DepositTillInstaller.php
?? resources/pos/deposit/{README.md, script.Deposit.AddLine.bsh, script.Deposit.Change.bsh}
?? tests/Feature/DeliveryDepositReconciliationTest.php
?? tests/Feature/DepositTillInstallerTest.php
   tests/Concerns/CreatesDepositPosTables.php          (untracked since cycle 2; + RESOURCES)
   tests/Feature/DepositScreenTest.php                 (untracked since cycle 2; + 1 test)
   docs/deposit/scripts/deposit-spike-setup.php        (reads the .bsh from resources/pos/deposit)
   docs/deposit/sop-bottle-deposits.md                 (new)
   docs/deposit/implemented.md
```
Dev data:
- **Delivery 132:** `import_data.deposit_reconciliation` added.
- **Dev POS:** `RESOURCES` round-tripped back to identical bytes.
- **Juice row:** re-confirmed through the add card (new `confirmed_at`).

## Notes for Planner

1. **Delivery 132 has its barrels stored twice** (17 `delivery_barrels`
   rows, 313 as 13 + 54). This is pre-existing data, from two imports of the
   same order, and it inflates its "Barrels/Deposits" total (€176.75).
   `storeBarrelItems` doesn't de-duplicate when an order is imported twice.
   Worth a look before cycle 4 sums barrels per period.
2. **The picker keeps the last pick while you type a new search.** After
   picking the juice and typing another barcode, "Selected: juice" stays and
   Add stays active until the new result is chosen. Clicking Add mid-search
   would add the previous product. The hidden `product_id` belongs to the
   component, so a clean fix (clear on input) belongs in
   `<x-product-search>`, not in this card.
3. **The tier select doesn't follow the product.** Adding an already-mapped
   315 product with the select left at 313 re-tiers it silently. A cheap
   follow-up: preselect the row's current tier when the pick is already in
   the Products table, or warn before re-tiering.
4. **Deliveries imported before this cycle have no reconciliation.** A
   one-off backfill would need re-parsing every stored PDF (about 120 Udea
   dockets, roughly a minute). Not needed for go-live.
