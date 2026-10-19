# Side plan — price check: repair glued case sizes, keep price mismatches visible — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-10-19

## Baseline
HEAD: 5775fbef
Pre-existing dirty files (none of them in this plan's file list):
```
A  .mcp.json
M  app/Http/Controllers/DepositController.php
M  app/Http/Requests/UpdateProductDepositRequest.php
A  app/Models/AppSetting.php
M  app/Models/ProductDeposit.php
A  app/Services/Deposits/DepositPriceReviewService.php
A  database/migrations/2026_10_17_000001_add_price_review_to_product_deposits.php
 M docs/deliveries/README.md
M  docs/deposit/README.md
R  docs/deposit/implemented.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/implemented.md
R  docs/deposit/plan.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/plan.md
A  docs/deposit/archive/2026-10-17-cycle-4-price-review/implemented.md
A  docs/deposit/archive/2026-10-17-cycle-4-price-review/plan.md
A  docs/deposit/plan-browser-mcp.md
AM docs/deposit/scripts/browser/README.md
A  docs/deposit/scripts/browser/boot.php
A  docs/deposit/scripts/browser/cdp.mjs
A  docs/deposit/scripts/browser/clicks.mjs
A  docs/deposit/scripts/browser/flow.mjs
A  docs/deposit/scripts/browser/lib.mjs
A  docs/deposit/scripts/browser/measure.mjs
AM docs/deposit/scripts/browser/mint.php
A  docs/deposit/scripts/browser/pct.mjs
 M docs/development/fresh-install-guide.md
M  docs/features/barrel-deposit-tracking.md
A  resources/css/modernist.css
M  resources/views/deposits/index.blade.php
M  resources/views/layouts/admin.blade.php
M  routes/web.php
M  tests/Concerns/CreatesDepositPosTables.php
A  tests/Feature/DepositPriceReviewServiceTest.php
M  tests/Feature/DepositScreenTest.php
M  vite.config.js
?? docs/deliveries/findings/
?? docs/deliveries/implemented-dev-parser-venv.md
?? docs/deliveries/plan-dev-parser-venv.md
?? docs/deliveries/plan-price-check.md
?? docs/deposit/implemented-browser-mcp.md
```

## Steps

### 1. Parser: repair a glued case size — done
Changed: `scripts/invoice-parser/parsers/delivery_udea.py` (new `_repair_glued_case_size()`; `parse_invoice()` calls it right after `_parse_line()`; `price_note` / `case_size_repaired` on every item). See Deviations 1 for where the call sits.
Check output (`delivery_parser_laravel.py --file ~/Downloads/Order_4804899.pdf --supplier udea`):
```
{'code': '5004102', 'product': '12,50 gram Chocolate-bar milk, Vivani Biologisch DE', 'case_size': 40, 'total_ordered_units': 40, 'total_delivered_units': 40, 'unit_cost': 0.49, 'rsp': 0.89, 'line_total': 19.6, 'price_valid': True, 'price_note': 'case size read as 401; 1×0.49×40=19.60 matches the line total, using 40', 'case_size_repaired': True, ...}
100.0
Case size repaired for 5004102: case size read as 401; 1×0.49×40=19.60 matches the line total, using 40
Deposit code 313: product lines total 24 units, barrels section says 36
totals: line_count 147, total_value 3238.27, products_total 2845.15, totals_match True
```
Same totals with the HEAD parser (run against a copy, then restored): `total_value 3238.27, products_total 2845.15`, confidence 99.3. (The plan's "products_total 3238.27-ish" is `total_value`; `products_total` is 2845.15 both before and after.)

### 2. Parser tests — done
Changed: `scripts/invoice-parser/tests/test_delivery_udea.py` (docstring extended; four tests: Vivani, gingerbeer, Marcel's discount, passing pineapple line unchanged; the Vivani one asserts `'1×0.49×40=19.60' in note`).
Check output:
```
212 passed in 0.54s
```
(208 before.)

### 3. Carry the flags into the Laravel item rows — done
Changed: `app/Services/DeliveryParsingService.php` (`price_valid`, `price_note` next to `Price_Valid`).
Check: `php artisan test tests/Feature/DeliveryImportBarrelCodeTest.php` → `! barrel code is stored on the delivery item … Tests: 1 deprecated (7 assertions)` (the PHP 8.5 baseline mark, passes).

### 4. Store them on the delivery item — done
Changed: `database/migrations/2026_10_19_000001_add_price_check_to_delivery_items.php` (new; did not exist), `app/Models/DeliveryItem.php`, `app/Services/DeliveryService.php` (`importFromPdfData()`; note truncated with `Str::limit(…, 250)`, `use Illuminate\Support\Str` added).
Check output:
```
2026_10_19_000001_add_price_check_to_delivery_items ........... 53.34ms DONE
tinker: Schema::hasColumn(... 'price_valid') && Schema::hasColumn(... 'price_note') → true
```

### 5. Keep every parser warning on the delivery — done
Changed: `app/Services/DeliveryService.php` (`recordParserWarnings()`), `app/Http/Controllers/DeliveryController.php` (`storePdf()`: call in its own try/catch after the deposit one; success suffix now `(N parser warnings, listed on this page)`).
Check: `grep -rn "parser_warnings" app/` →
```
app/Services/DeliveryService.php:1020:     * Keep every parser warning on the delivery (import_data.parser_warnings),
app/Services/DeliveryService.php:1032:            'parser_warnings' => $warnings,
```

### 6. Show the warnings and mark the items on the delivery page — done
Changed: `resources/views/deliveries/show.blade.php` — Parser warnings block after the Unparsed Lines `@endif`; red `Price check` / blue `Case size repaired` badges in the desktop Invoiced cell (weight branch: `Price check` only) and the mobile `Inv:` cell; three `x-collapse` removed. Deposit warnings are filtered with `/^(\[[^\]]*\]\s*)?Deposit code/` (Deviations 2).
Check: `grep -c "x-collapse" resources/views/deliveries/show.blade.php` → `0`. Rendering covered by step 8.

### 7. Mark the failing row on the import preview — done
Changed: `resources/views/deliveries/create.blade.php` (Units cell red + `title` for `price_valid === false`; blue `repaired` mark when `price_note` is set otherwise). `npm run build` run.
Check: headless Chrome (lib.mjs from `docs/deposit/scripts/browser/`, my script in the session scratchpad, not in the repo): `/deliveries/create`, supplier 5, date 2026-10-19, the four PDFs set on `#pdf_file`, `parsePdf()`; not submitted.
```
"confidence": "100% confidence", "items": "279",
"warnings": [
  "[Order_4804899.pdf] Case size repaired for 5004102: case size read as 401; 1×0.49×40=19.60 matches the line total, using 40",
  "[Order_4804899.pdf] Deposit code 313: product lines total 24 units, barrels section says 36" ],
row 5004102: "5004102 | 12,50 gram Chocolate-bar milk, Vivani Biologisch DE | 40 repaired | €0.49 | €19.60"
  (Units cell: <td class="... text-gray-900 ...">40 <span class="text-xs text-blue-600 ..." title="case size read as 401; …">repaired</span></td>)
"redCells": 0
console errors/warnings: []
```

### 8. Feature tests — done
Changed: `tests/Feature/DeliveryPriceCheckTest.php` (new; checked absent first). Four tests as planned (Deviations 3, 4).
Check output:
```
php artisan test tests/Feature/DeliveryPriceCheckTest.php tests/Feature/DeliveryImportBarrelCodeTest.php tests/Feature/DeliveryDepositReconciliationTest.php
  ! price check result is stored on the item
  ! record parser warnings merges into import data
  ! delivery page lists parser warnings and marks items
  ! delivery page without parser warnings has no block
  ! barrel code is stored on the delivery item
  ! record merges into import data and keeps other keys
  ! record skips when there is nothing to compare
  ! delivery page shows the table and the warnings
  ! delivery page without reconciliation has no table
  Tests:    9 deprecated (49 assertions)
```
(`!` = the `PDO::MYSQL_ATTR…` deprecation on PHP 8.5, the baseline; none failed.)
After Deviation 5 a fifth test was added (`test_deposit_warnings_stay_in_the_deposit_block_when_it_lists_them`) and the third test's last assertion became "exactly once" as the plan wrote it: `DeliveryPriceCheckTest` → `Tests: 5 deprecated (30 assertions)`, none failed.

### 9. Re-import delivery 166 on dev — done, one expectation differs (Deviation 5)
Delete via the page's Delete Delivery form (`confirm()` answered yes) in headless Chrome:
```
title Delivery DEL-20261019-145724
url http://osmanager.local/deliveries
flash Delivery DEL-20261019-145724 has been deleted successfully.
tinker after: Delivery 166 null, delivery_items 0, delivery_barrels 0, delivery_documents 0
```
`delivery_barrels` and `delivery_documents` rows went with the delivery; nothing to clean up in tinker. The four stored PDFs are left under `storage/app/private/deliveries/2026/10/166/` (as the plan allows).

First re-import became delivery 167. It showed the Barrels/Deposits block without `1 deposit mismatch` (Deviation 5), so after the view change I deleted 167 the same way (its files are left in `.../2026/10/167/`) and re-imported once more to capture the flash. Final run, delivery **168**:
```
url http://osmanager.local/deliveries/168
flash Delivery imported successfully from 4 PDFs. 279 items loaded. Total value: 5,268.66 (2 parser warnings, listed on this page)
Parser warnings block:
  "Parser warnings / 1 case size repaired /
   [Order_4804899.pdf] Case size repaired for 5004102: case size read as 401; 1×0.49×40=19.60 matches the line total, using 40 /
   [Order_4804899.pdf] Deposit code 313: product lines total 24 units, barrels section says 36"
Barrels/Deposits block: "Barrels/Deposits: €172.68 (17 items) Show"   ← no "1 deposit mismatch" (Deviation 5)
desktop row: "12,50 gram Chocolate-bar milk, Vivani Biologisch DE Code: 5004102 Order #4804899 40843070 40 Case size repaired 1 × 40 0 (-40) Pending €0.49 …"
badge title: "case size read as 401; 1×0.49×40=19.60 matches the line total, using 40" (desktop visible; mobile copy present, hidden at 1600px)
x-collapse in DOM: 0
console errors/warnings: []
tinker: {"units_per_case":40,"ordered_quantity":40,"invoice_delivered_quantity":40,"unit_cost":"0.4900","price_valid":true,
         "description":"12,50 gram Chocolate-bar milk, Vivani Biologisch DE"}; raw price_valid 1; 279 items
```

### 10. Docs — done
Changed: `docs/features/delivery-system.md` — one paragraph after the Udea feature list naming `_repair_glued_case_size()`, the rule, `delivery_items.price_valid` / `delivery_items.price_note`, `deliveries.import_data.parser_warnings`, the Parser warnings block and the badges.

## Dev state
- Delivery 166 deleted; 167 (intermediate re-import) deleted; **168** `DEL-20261019-153708` (draft, 279 items, supplier 5, 2026-10-19) is the re-import to keep. Leftover document files: `storage/app/private/deliveries/2026/10/166/` and `.../167/` (four PDFs each).
- Migration `2026_10_19_000001_add_price_check_to_delivery_items` run on dev.
- `npm run build` run (public/build).

## Deviations

1. **Where the repair is called (step 1).** The plan put the repair inside the non-weight validation branch, after `_validate_price` fails. But `product_name` is built from `Content` *before* that branch, so a repair there would leave the name as `2,50 gram …` (step 1's check wants `12,50 gram`). `_repair_glued_case_size()` therefore runs its own `_validate_price` first (returns `None` for a passing line, which also makes the "passing line unchanged" test natural) and is called right after `_parse_line()`, before SKU / Content are read. The validation branch then passes, counts as `price_validations_passed`, and appends the `Case size repaired for …` warning when a note exists. Behaviour is what the plan describes.
2. **Deposit-warning filter (step 6a).** The plan said to drop warnings that *start with* `Deposit code`. On a multi-PDF import `parseMultipleDeliveryPdfs()` prefixes every warning with `[filename] `, so they start with `[Order_…pdf] Deposit code`. The filter matches `/^(\[[^\]]*\]\s*)?Deposit code/` instead.
3. **Step 8, third item "with neither key".** `convertToDeliveryItems()` defaults `price_valid` to true (step 3), so an item lacking the key in the *parser* output is stored as true, not null. The test asserts that (true, note null) and adds a fourth record with the key removed from the *converted* record, which is stored null, the CSV-like case.
4. **Price note format.** The mismatch `price_note` formats price and SKU with `:g` (`1×3.99×6=23.94, line total 12.00`, as in the plan's step 8 example) rather than the raw float (`6.0`). The existing `Price mismatch …` warning text is unchanged.
5. **Deposit warnings and the Barrels/Deposits block (steps 6a, 8, 9).** Step 9 expected the Barrels/Deposits block to show `1 deposit mismatch`. It does not, on the multi-PDF import, and it did not before this plan: for a merged parse `deposit_reconciliation.line_units` is `[]` and `warnings` is `[]`, because `recordDepositReconciliation()` keeps only warnings that `str_starts_with('Deposit code')` and the merged ones carry the `[file] ` prefix (see Notes 1). With the plan's filter the 313 warning would then be shown nowhere on the page. Smallest change: the Parser warnings block leaves deposit-code warnings out **only when `deposit_reconciliation.warnings` is non-empty** (the deposit block lists them itself); otherwise it shows them. This also makes the plan's step 8 assertion "the deposit text appears exactly once" true as written. Added test `test_deposit_warnings_stay_in_the_deposit_block_when_it_lists_them`. `recordDepositReconciliation()` is untouched.
6. **Step 9 extra round trip.** The first re-import (167) was deleted and redone (168) after Deviation 5 so that the flash text could be captured; my first capture selector was too broad. Same steps both times.

## Verification

1. `venv/bin/python -m pytest scripts/invoice-parser/tests -q` → `212 passed in 0.60s`.
2. Order_4804899.pdf → `40 40 0.49 True 12,50 gram C`, `100.0`, `Case size repaired for 5004102: case size read as 401; 1×0.49×40=19.60 matches the line total, using 40`, `Deposit code 313: product lines total 24 units, barrels section says 36`. Pass.
3. Order_4804901 / 4804902 / 4804914 → `100.0`, no warnings each. Pass.
4. `php artisan test tests/Feature/DeliveryPriceCheckTest.php tests/Feature/DeliveryImportBarrelCodeTest.php tests/Feature/DeliveryDepositReconciliationTest.php` → `Tests: 10 deprecated (53 assertions)`, no failures.
5. `php artisan test` → `Tests: 1054 deprecated, 16 failed, 43 passed (5093 assertions)`. The 16: `UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2, `TestScraperControllerTest` ×1, `BackupConfigurationTest` ×1 — the 2026-10-17 baseline set of 16, nothing new.
6. `./vendor/bin/pint --test` on the six changed / new PHP files → `PASS ... 6 files`.
7. Browser check: step 7 and step 9 above; console clean on create (preview), delete and the delivery page; no `x-collapse` in the DOM.

## Files changed
Mine (this side plan):
```
 M app/Http/Controllers/DeliveryController.php
 M app/Models/DeliveryItem.php
 M app/Services/DeliveryParsingService.php
 M app/Services/DeliveryService.php
?? database/migrations/2026_10_19_000001_add_price_check_to_delivery_items.php
 M docs/features/delivery-system.md
 M resources/views/deliveries/create.blade.php
 M resources/views/deliveries/show.blade.php
 M scripts/invoice-parser/parsers/delivery_udea.py
 M scripts/invoice-parser/tests/test_delivery_udea.py
?? tests/Feature/DeliveryPriceCheckTest.php
?? docs/deliveries/implemented-price-check.md
```
Everything else in `git status --short` is the pre-existing baseline above (unchanged). Nothing committed.

## Notes for Planner

1. **Deposit reconciliation is empty for multi-PDF imports** (pre-existing, not touched). Delivery 168 `import_data.deposit_reconciliation` = `{"line_units": [], "section": {…11 codes…}, "warnings": [], …}`. Two causes: the merged result from `parseMultipleDeliveryPdfs()` carries no `barrels.line_units` (`grep line_units app/Services/DeliveryParsingService.php` finds nothing), and `recordDepositReconciliation()` filters with `str_starts_with($w, 'Deposit code')`, which the `[file] `-prefixed merged warnings never match. So the Barrels/Deposits block cannot report a mismatch for any multi-file Udea import (the owner's normal case). Deposit track; worth a small fix (strip the prefix / merge `line_units` per file). Until then Deviation 5 keeps the warning visible in the Parser warnings block.
2. Pre-existing `storage/app/private/deliveries/2026/10/166/` and `167/` hold orphaned PDFs: `DeliveryController::destroy` deletes `delivery_documents` rows (or they cascade) but not the files. Not touched.
3. The `Case size repaired` badge test assertion is weak on its own (the same words are in the warning text); the counts line (`1 case size repaired`) and the `Price check` badge are the discriminating assertions.
4. The pytest suite now has 212 tests; README test baseline for parser can be bumped.
