# Udea parser: prices run into the description — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-10-20

## Baseline
HEAD: 82b33968
Pre-existing dirty files:
```
 M docs/deliveries/README.md
?? docs/deliveries/plan-glued-price.md
```

## Steps

### 1. Un-glue a money token from the word before it — done
Changed: `scripts/invoice-parser/parsers/delivery_udea.py` (`MONEY`, `GLUED_PRICE_REGEX` before
`LINE_STARTS_WITH_CODE_REGEX`; last `re.sub` in `_preprocess_line()`), pattern exactly as in the plan.
Check output (scratchpad script, `_preprocess_line` + `_parse_line` + `_validate_price`):
```
6003103 1 1 4 900 millilitre Icecream ... HappDyE Mrs. Jersey 4,63 8,29 5 18,52
{'SKU': '4', 'Content': '900 millilitre', 'Price': '4.63', 'Sale': '8.29', 'Total': '18.52'}  (True, 18.52)
5007337 ... MarceNl'Ls Green Soap 2,00 3,99 4 39% 12,00
{'SKU': '6', 'Price': '2.00', 'Sale': '3.99', 'Total': '12.00'}  (True, 12.0)
36488 ... cashNeLw, Ice Cream3, 1F7 actory 6,39 5 46% 19,02
{'SKU': '6', 'Price': '6.39', 'Sale': '5', 'Total': '19.02'}  (False, 38.34)
```

### 2. Repair a shifted tail from the line total — done
Changed: `delivery_udea.py`: `_repair_shifted_price()` after `_repair_glued_case_size()`;
`price_repair_note` computed right after `repair_note` in `parse_invoice()`; `elif price_repair_note:`
branch in the validation block; `"price_repaired"` in the item dict. Comment above the repair call
reworded to cover both repairs.
Check output:
```
36489 purchase price 3.17 ran into the description; 6.39 (the sale price) was read in its place, 19.02÷(1×6)=3.17 | Price 3.17 Sale 6.39 | _validate_price(1,3.17,6,19.02) = (True, 19.02)
discount (5007341)  -> None, parsed unchanged: True
no digits in desc   -> None, parsed unchanged: True
```

### 3. Never drop a product-shaped line silently — done
Changed: `delivery_udea.py`: heuristic moved into `_is_likely_product_line(line)` with the
structural clause; the unmatched branch now calls it with `preprocessed_line`; the unmatched entry
still records the raw `line[:100]`.
Check output:
```
'6003103 ... Jersey4,63 8,29 18,52' (VAT removed) -> preprocessed '... 900 millilitre ... Jersey4,63 8,29 18,52', _parse_line None, likely True
'6003103 1 1 4 Icecream Jersey 4,63 8,29 18,52'  -> _parse_line None, likely True (structure + money tail only)
'6001624 8 1 330ml Water Kefir Jasmine & Peach, Kult' -> False
'3703 1 8 450gram Green-beans, ... leverancier, geen' -> False
```

### 4. Parser tests — done
Changed: `scripts/invoice-parser/tests/test_delivery_udea.py`: the 8 tests named in the plan (plus a
`_passes()` helper and four line constants); module docstring gains a paragraph on the glued /
shifted price rules and the unparsed guard.
Check output:
```
$ scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests -q
220 passed in 0.61s
```

### 5. Both dockets parse clean through the wrapper — done
Check output (trimmed JSON):
```
Order_4795456.pdf: success true, confidence 100.0, errors [], 62 items
warnings:
 "Purchase price repaired for 36488: purchase price 3.17 ran into the description; 6.39 (the sale price) was read in its place, 19.02÷(1×6)=3.17"
 "Purchase price repaired for 36489: ... 19.02÷(1×6)=3.17"
 "Purchase price repaired for 36491: ... 38.04÷(2×6)=3.17"
{"code":"36488","case_size":6,"total_delivered_units":6,"unit_cost":3.17,"rsp":6.39,"line_total":19.02,"price_valid":true,"price_repaired":true,"case_size_repaired":false}
{"code":"36489","case_size":6,"total_delivered_units":6,"unit_cost":3.17,"rsp":6.39,"line_total":19.02,"price_valid":true,"price_repaired":true,"case_size_repaired":false}
{"code":"36491","case_size":6,"total_delivered_units":12,"unit_cost":3.17,"rsp":6.39,"line_total":38.04,"price_valid":true,"price_repaired":true,"case_size_repaired":false}

Order_4798553.pdf: success true, confidence 100.0, errors [], warnings [], 5 items
{"code":"6003103","case_size":4,"total_delivered_units":4,"unit_cost":4.63,"rsp":8.29,"line_total":18.52,"price_valid":true,"price_note":null,"price_repaired":false}
```

### 6. Delivery page labels for the second repair kind — done
Changed: `resources/views/deliveries/show.blade.php`: `$caseSizesRepaired` now counts valid items
whose note starts `case size`, new `$pricesRepaired` counts those starting `purchase price`; a second
blue span "N purchase price(s) repaired"; both blue badges read
`Str::startsWith($item->price_note, 'purchase price') ? 'Price repaired' : 'Case size repaired'`
inline (no `@php` helper needed). `tests/Feature/DeliveryPriceCheckTest.php`: new
`test_delivery_page_labels_a_repaired_purchase_price` (asserts the warning, `1 purchase price
repaired`, `Price repaired`, and neither `Case size repaired` nor `case size repaired`). The existing
test was not touched; it still passes because its note starts `case size`. `create.blade.php`
untouched.
Check output:
```
$ php artisan test --filter=DeliveryPriceCheckTest
  ! delivery page labels a repaired purchase price → Constant PDO::MYSQ… 0.03s
  Tests:    6 deprecated (36 assertions)        # "deprecated" = PHP 8.5 PDO::MYSQL_ATTR_* notice, no failures
$ ./vendor/bin/pint --test tests/Feature/DeliveryPriceCheckTest.php
  PASS ... 1 file
```

### 7. Feature doc — done
Changed: `docs/features/delivery-system.md`: paragraph **"Prices run into the description
(2026-10-20)"** directly after the price-check paragraph (un-glue rule, shifted-tail repair and its
locks, warning / badge / count, `unit_cost` already right, `_is_likely_product_line()` guard).
Check: `git diff --stat docs/` shows only that file (+2 lines) besides the pre-existing README change.

### 8. End-to-end import on dev — done
Driven in headless Chrome with a minted session for user 1 (scratchpad scripts
`/tmp/claude-1000/-var-www-html-osmanagercl/1515da52-9113-4f70-88bb-8456f200ef54/scratchpad/browser/import.mjs`
and `show.mjs`, built on `docs/deposit/scripts/browser/lib.mjs`): both PDFs set on `#pdf_file`,
supplier auto-detected, Parse, then Create.
Check output:
```
supplier selected: 5 Udea   (detect msg: Detected: Udea)
preview items: 67   total €3494.23
preview warnings: the three [Order_4795456.pdf] Purchase price repaired for 36488/36489/36491 lines
url: http://osmanager.local/deliveries/169       console errors: []

/deliveries/169 page text:
Parser warnings
3 purchase prices repaired
[Order_4795456.pdf] Purchase price repaired for 36488: purchase price 3.17 ran into the description; 6.39 (the sale price) was read in its place, 19.02÷(1×6)=3.17
[Order_4795456.pdf] Purchase price repaired for 36489: ... 19.02÷(1×6)=3.17
[Order_4795456.pdf] Purchase price repaired for 36491: ... 38.04÷(2×6)=3.17
Total Items 67   Status Draft
"Products total mismatch" → 0   "Unparsed lines" → 0   "Price check" → 0   "Case size repaired" → 0
badges: Price repaired on 36488, 36489, 36491 (each twice in the DOM: the mobile card and the desktop row)
console errors: []

DB (tinker):
DEL-20261020-131040 draft supplier 5 items 67
36488   units_per_case 6, invoice_delivered_quantity 6,  unit_cost 3.1700, total 19.02, price_valid true, note "purchase price 3.17 ran into ..."
36489   units_per_case 6, invoice_delivered_quantity 6,  unit_cost 3.1700, total 19.02, price_valid true, note "purchase price ..."
36491   units_per_case 6, invoice_delivered_quantity 12, unit_cost 3.1700, total 38.04, price_valid true, note "purchase price ..."
6003103 units_per_case 4, invoice_delivered_quantity 4,  unit_cost 4.6300, total 18.52, price_valid true, note null (no badge)
```
36488 is a parsed item (created by the import, not added by hand).

Dev state: delivery **169** `DEL-20261020-131040` (supplier 5 Udea, Draft, 67 items) kept. Delivery
168 `DEL-20261019-153708` untouched.

## Deviations
- Step 8: the plan says "supplier Udea, as the owner does". The page's auto-detection picked
  supplier 5 "Udea" and I kept it. Production's `DEL-20261020-120934` is supplier 85 "Udea Frozen";
  the parser output does not depend on which Udea supplier is chosen.
- Step 4: "Update the module docstring's last paragraph" — read as the test file's docstring (the
  step's only file); I added a new final paragraph rather than rewriting the case-size one.

## Verification
1. `scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests -q` → `220 passed in 0.53s`.
2. Step 5 wrapper runs → as recorded under step 5: 4795456 has 62 items, confidence 100, exactly 3
   `Purchase price repaired` warnings; 4798553 has 5 items and 0 warnings; item facts as listed.
3. `php artisan test --filter=DeliveryPriceCheckTest` → 6 tests, 0 failed (36 assertions).
4. `php artisan test tests/Feature/DeliveryPriceCheckTest.php tests/Feature/DeliveryDepositReconciliationTest.php tests/Feature/DeliveryImportBarrelCodeTest.php`
   → `Tests: 11 deprecated (59 assertions)`, 0 failed.
5. `./vendor/bin/pint --test tests/Feature/DeliveryPriceCheckTest.php resources/views/deliveries/show.blade.php` → PASS (2 files).
6. `php artisan test` → `16 failed, 1055 deprecated, 43 passed (5099 assertions)`; the 16 are exactly
   the baseline set: UdeaScrapingServiceTest ×7, CashReconciliationTest ×3, FruitVegLabelPrintingTest ×2,
   ProductTest ×2, TestScraperControllerTest ×1, BackupConfigurationTest ×1.
7. Step 8's dev import → as recorded under step 8.

## Files changed
```
 M docs/deliveries/README.md                         (pre-existing, not mine)
 M docs/features/delivery-system.md
 M resources/views/deliveries/show.blade.php
 M scripts/invoice-parser/parsers/delivery_udea.py
 M scripts/invoice-parser/tests/test_delivery_udea.py
 M tests/Feature/DeliveryPriceCheckTest.php
?? docs/deliveries/implemented-glued-price.md
?? docs/deliveries/plan-glued-price.md              (pre-existing, not mine)
```
Nothing committed.

## Notes for Planner
- The Constraints say existing tests pass unchanged "except for the one assertion step 6 names", but
  step 6 names no change to an existing assertion. None was needed:
  `test_delivery_page_lists_parser_warnings_and_marks_items` passes as is.
- `_repair_shifted_price()` also runs on lines the case-size repair declined (it only skips when that
  repair fired), as the plan's snippet specifies.
- The step 3 check line with the VAT token removed is listed as unparsed because of the split unit
  `900 millilitre`, not the structural clause; the unit-less line is the one that exercises the new
  clause (both are in `test_likely_product_line_guard`).
- Owner action from the plan still stands: add `6003103` to production `DEL-20261020-120934` by hand.
