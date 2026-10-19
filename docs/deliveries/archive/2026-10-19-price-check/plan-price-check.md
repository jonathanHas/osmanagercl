# Side plan — price check: repair glued case sizes, keep price mismatches visible

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-10-19

Side plan (runs beside cycle 3, which is DONE and awaiting review). Report in
`docs/deliveries/implemented-price-check.md`. Kickoff:
`Read docs/deliveries/planimp.md. You are the Implementer. Implement docs/deliveries/plan-price-check.md.`

## Goal

On dev delivery 166 (`DEL-20261019-145724`, Udea orders 4804899 / 4804901 /
4804902 / 4804914, 279 items, draft) the Vivani milk minibar line (supplier code
`5004102`, barcode `40843070`, item id 34400) was imported as **401 units at
€0.0489** when the docket says **40 × 12,50 gram at €0.49, line total €19.60**.
The parser *did* fail the price check (`1×0.49×401=196.49, actual=19.60`) but the
warning only ever appeared in the yellow list on the import preview; the item was
stored with the wrong quantity and a unit cost back-calculated from the total, and
nothing on the delivery page says so.

After this plan:

1. The Udea parser repairs a line whose case size and content were glued together
   by pdfplumber, when the line total identifies the case size unambiguously
   (`40 × 0.49 = 19.60`). The line imports as 40 units at €0.49 with a note.
2. A line that still fails the price check is marked on the delivery item
   (`price_valid = false` plus the arithmetic as a note), shown as a badge in the
   Invoiced cell, and listed in a "Parser warnings" block on the delivery page.
   All parser warnings are kept on the delivery (`import_data.parser_warnings`),
   not just the deposit ones.

## Context

### The line and how it was parsed

Raw pdfplumber text, page 2 of `~/Downloads/Order_4804899.pdf`:

```
5004102 1 1 4012,50gram Chocolate-bar milk, Vivani Biologisch DE 0,49 0,89 5 40% 19,60
```

Columns are Code, Ordered, Qty, **SKU (case size)**, Content, Description, Price,
Sale, VAT, margin, Total. pdfplumber dropped the space between SKU `40` and
content `12,50gram`. In `scripts/invoice-parser/parsers/delivery_udea.py`:

- `_preprocess_line()` only unglues a *single-digit* SKU from a *comma-thousands*
  content (`41,200 millilitre` → `4 1,200 millilitre`). A two-digit SKU glued to a
  decimal content (`4012,50`) is not covered.
- `NORMAL_REGEX` and `QUANTITY_SKU_REGEX` fail (`4012,50` is not a `NUMBER` token
  followed by whitespace). `FALLBACK_REGEX` matches with `raw_sku_content =
  "4012,50"`; `_parse_fallback_sku_content()` takes the greedy `NUMBER` prefix
  `401` as the SKU and leaves `2,50` as content.
- `parse_invoice()` then runs `_validate_price(1, 0.49, 401, 19.60)` → false,
  appends the warning, sets `price_valid: False`, and computes
  `unit_cost = total / (qty × sku) = 19.60 / 401 = 0.0489`.
- Item as emitted: `case_size 401, total_delivered_units 401, unit_cost 0.0489,
  product "2,50 gram Chocolate-bar milk, Vivani Biologisch DE", price_valid false`.

Stored row (`delivery_items` 34400): `units_per_case 401, sku "401",
ordered_quantity 401, invoice_delivered_quantity 401, unit_cost 0.0489,
total_cost 19.60, description "2,50 gram ...", supplier_case_units 40` (the
supplier link `supplier_link` id 1243 knows the case is 40).

### Where the warning went

- `delivery_parser_laravel.py` passes `result['warnings']` and the items through
  unchanged. Confidence is `price_validations_passed / parsed_lines` → 99.3 %,
  shown as a green badge (≥ 90) on the preview.
- `DeliveryParsingService::parseMultipleDeliveryPdfs()` (lines 208–210) prefixes
  each warning with the filename into `$allWarnings` and returns them as
  `warnings`. `convertToDeliveryItems()` (line 362) maps `price_valid` to
  `'Price_Valid' => '✓' | '✗'`, a legacy CSV marker.
- `DeliveryController::parsePdf()` (preview JSON) returns `warnings`; the create
  page (`resources/views/deliveries/create.blade.php` lines 727–745) lists them in
  `#pdf-warnings`. That is the only place the owner could have seen it. The
  preview table (lines 748–756) does not mark the failing row.
- `DeliveryController::storePdf()`: `DeliveryService::importFromPdfData()` never
  reads `Price_Valid`; `recordDepositReconciliation()` keeps only warnings that
  start with `Deposit code`; the success flash says `(2 warnings - check items)`;
  the `import_warnings` flash (line 656) only holds unmatched lines and **no view
  reads it** (dead code, leave it). Only `unparsed_lines` is persisted and shown.
- `resources/views/deliveries/show.blade.php`: deposit warnings block at
  lines 541–600 (pattern to copy); Unparsed Lines block at 279–389; desktop
  Invoiced cell at 1324–1349; mobile "Inv:" at 1041–1045.

### Evidence from every stored Udea docket (read-only sweep, 2026-10-19)

The Planner ran the current parser's line parse plus a prototype repair over all
147 Udea PDFs under `storage/app` (12,309 non-weight product lines):

| | |
|---|---|
| Lines failing the price check | 31 |
| … glued two-digit SKU + decimal content, repairable by the rule below | **10** (`5004101` / `5004102` Vivani `4012,50gram` → 40 × 12,50 gram; `5012204` Naturfrisk gingerbeer `2027,50centilitres` → 20 × 27,50 centilitre) |
| … genuine discount / odd total (keep as a failed check) | 19 (`5007341` / `5007337` Marcel's `1 1 6 … 3,99 … 12,00`; `36489` / `36491` ice cream `6 × 6,39` vs `19,02`; `88927` `2 × 7,34` vs `7,76`) |
| … variable-weight line garbled (`530144 1 1 122,70kilogramCashews …`, SKU read as 122.70) | 2, out of scope |
| Repair rule firing on a line that already passed | **0** of 12,278 |

So the repair is safe to apply blind, and the genuine mismatches are frequent
enough that showing them on the delivery page matters: they too were imported
silently, with `unit_cost = total / units` (which for a discounted line is the
cost actually charged, so the cost itself is right; only the visibility is
missing).

### Repair rule (prototyped, see table above)

For a parsed, non-weight line that fails `_validate_price`:

```
n = round(total / (qty × price))
repair when:
  qty > 0, price > 0, total > 0
  n >= 1 and |qty × price × n − total| <= calculation_tolerance (0.015)
  parsed SKU is all digits, != str(n), and startswith(str(n))
then:
  leftover = SKU[len(str(n)):]          # "401" → "1"
  SKU = str(n)                           # "40"
  Content = leftover + Content           # "1" + "2,50" → "12,50"
```

Product name becomes `"12,50 gram Chocolate-bar milk, Vivani Biologisch DE"`
(`Content` + `Description`, as today). The repaired line counts as a passed
validation and gets `price_valid: True`.

### Owner decisions recorded here

- A discounted line keeps `unit_cost = total / units` (what Udea charged); it is
  flagged, not corrected.
- Repaired lines are not errors, but the owner wants to see that a repair
  happened: they are listed with the other parser warnings.
- Dev delivery 166 is a draft the owner imported today to test the venv fix; it
  is deleted and re-imported as the end-to-end check (step 9). Production is not
  touched.

## Constraints

- Shop rules do not apply (office pages only), but rule 5 of `README.md` holds:
  no POS writes. Nothing here touches `supplier_link`.
- `delivery_items.unit_cost` semantics unchanged: per unit, from the line total.
- The CSV import paths (`importFromCsv`, Independent, Natural Medicine) must not
  change behaviour; the new columns stay `NULL` for them.
- `recordDepositReconciliation()` and its `import_data.deposit_reconciliation`
  shape stay as they are (deposit track reads them).
- Dev PHP is 8.5, production 8.3: no 8.4+ syntax.
- Do not commit, push or deploy. The owner commits.
- Files this side plan touches (so the two implementer sessions cannot collide):
  `scripts/invoice-parser/parsers/delivery_udea.py`,
  `scripts/invoice-parser/tests/test_delivery_udea.py`,
  `app/Services/DeliveryParsingService.php`, `app/Services/DeliveryService.php`,
  `app/Http/Controllers/DeliveryController.php` (`storePdf` only),
  `app/Models/DeliveryItem.php`, one new migration,
  `resources/views/deliveries/show.blade.php`,
  `resources/views/deliveries/create.blade.php`,
  `tests/Feature/DeliveryPriceCheckTest.php` (new),
  `docs/features/delivery-system.md` (one paragraph).

## Out of scope

- The variable-weight glue (`122,70kilogram` read as SKU 122.70) — separate
  parser fix, noted in `README.md` open items.
- The deposit-code count on order 4804899 (313: 24 on lines vs 36 in the
  section) — deposit track, `findings/2026-10-19-udea-order-4804899-parser-warnings.md` item 2.
- Comparing the parsed case size with `supplier_link.CaseUnits` at import (a
  second independent signal). Deferred: `CaseUnits` is 0 or 1 for many links
  (Mossfield) and would be noisy; revisit if the price-guided repair misses a case.
- Editing an item's invoiced quantity on the delivery page. Not planned.
- Removing the dead `import_warnings` flash.
- Installing `@alpinejs/collapse`. Step 7 removes the unused directive instead.

## Steps

### 1. Parser: repair a glued case size
Files: `scripts/invoice-parser/parsers/delivery_udea.py`
What: add `_repair_glued_case_size(self, parsed: Dict[str, str]) -> Optional[str]`
implementing the rule above on the dict `_parse_line()` returns (keys `Qty`,
`Price`, `Total`, `SKU`, `Content`, already cleaned to `.` decimals; note
`Content` for the fallback path is `"2,50"` *without* the unit — keep the string
as is and prepend the leftover digits). It mutates `parsed["SKU"]` and
`parsed["Content"]` and returns a note like
`"case size read as 401; 1×0.49×40=19.60 matches the line total, using 40"`, or
`None` when the rule does not apply. In `parse_invoice()`, in the non-weight
branch (currently lines 750–758): run `_validate_price` first; if it fails, call
the repair; if it returns a note, recompute `sku`, `is_valid`, `calculated_total`
and treat the line as passed (`price_validations_passed += 1`), append
`f"Case size repaired for {code}: {note}"` to `result["warnings"]`; if it
returns `None`, keep today's mismatch warning. Add to every item:
`"price_note": note_or_mismatch_text_or_None` where the mismatch text is
`f"{qty}×{price}×{sku}={calculated_total:.2f}, line total {total:.2f}"` and
`"case_size_repaired": bool`. Weight-based items get `price_note` for a failed
check too (`f"{total_weight:.3f}kg×{price}={calculated_total:.2f}, line total {total:.2f}"`), `case_size_repaired: False`.
Check: `scripts/invoice-parser/venv/bin/python -I scripts/invoice-parser/delivery_parser_laravel.py --file ~/Downloads/Order_4804899.pdf --supplier udea`
→ item `5004102` has `case_size 40, total_delivered_units 40, unit_cost 0.49,
price_valid true, case_size_repaired true`, product starts `12,50 gram`;
`warnings` contains one `Case size repaired for 5004102: …` and the existing
`Deposit code 313` warning, and no `Price mismatch`; `confidence` is 100.0;
`totals.products_total` still 3238.27-ish as before (unchanged: the repair does
not alter the line total).

### 2. Parser tests
Files: `scripts/invoice-parser/tests/test_delivery_udea.py`
What: add tests (module docstring: extend, this file is about Udea line parsing):
- the Vivani line above, through `_preprocess_line` → `_parse_line` →
  `_repair_glued_case_size`: SKU `"40"`, Content `"12,50"`, note not None;
- the gingerbeer line
  `5012204 1 1 2027,50centilitresGingerbeer, Naturfrisk Biologisch NL 1,32 2,39 5 40% 26,40`
  → SKU `"20"`, Content starts `"27,50"`;
- the Marcel's line
  `5007341 1 1 6 500millilitre All-purpose-cleaner-spray lavender, Marcel's NL 3,99 7,29 5 45% 12,00`
  → repair returns `None`, SKU stays `"6"` (a genuine discount);
- a passing NORMAL line (reuse the pineapple juice line from
  `test_full_line_parse_then_split`) → repair returns `None` and leaves the dict
  unchanged;
- an end-to-end `parse_invoice` test is not possible without a PDF fixture; instead
  test the warning text by calling the repair and asserting the note contains
  `"1×0.49×40=19.60"`.
Check: `scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests -q`
→ all pass (208 before this plan; record the new count).

### 3. Carry the flags into the Laravel item rows
Files: `app/Services/DeliveryParsingService.php`
What: in `convertToDeliveryItems()` add, next to `'Price_Valid'`:
`'price_valid' => (bool) ($item['price_valid'] ?? true)`,
`'price_note' => $item['price_note'] ?? null`. Keep `Price_Valid`. Parsers that
do not emit `price_valid` (none today, all three delivery parsers do) default to
true.
Check: `php artisan test tests/Feature/DeliveryImportBarrelCodeTest.php` still
passes (it goes through this method).

### 4. Store them on the delivery item
Files: `database/migrations/2026_10_19_000001_add_price_check_to_delivery_items.php` (new),
`app/Models/DeliveryItem.php`, `app/Services/DeliveryService.php`
What: migration adds to `delivery_items`, after `total_cost`:
`price_valid` nullable boolean, `price_note` nullable string(255). Model:
add both to `$fillable`, `'price_valid' => 'boolean'` to `$casts`. In
`importFromPdfData()` add to the `DeliveryItem::create([...])` array:
`'price_valid' => array_key_exists('price_valid', $record) ? (bool) $record['price_valid'] : null`,
`'price_note' => $record['price_note'] ?? null`. `importFromCsv()` is not
changed, so CSV rows stay `NULL`.
Check: `php artisan migrate` runs clean; in tinker
`Schema::hasColumn('delivery_items','price_valid') && Schema::hasColumn('delivery_items','price_note')` → true.

### 5. Keep every parser warning on the delivery
Files: `app/Services/DeliveryService.php`, `app/Http/Controllers/DeliveryController.php`
What: new `DeliveryService::recordParserWarnings(Delivery $delivery, array $warnings): void`
— filter to strings, `array_values`, and when non-empty merge
`['parser_warnings' => $warnings]` into `import_data` exactly the way
`recordDepositReconciliation()` merges (`array_merge($delivery->import_data ?? [], …)`, then `save()`). When the list is empty, do nothing.
In `storePdf()` call it right after the `recordDepositReconciliation` try/catch
with `$this->deliveryParsingService->getWarnings($result)`, wrapped in its own
try/catch logging `Parser warnings not recorded` (never fails the import).
Change the success message suffix from `(N warnings - check items)` to
`(N parser warnings, listed on this page)`.
Check: covered by step 8's tests; also `grep -n "parser_warnings" app/` shows
the service and nothing else.

### 6. Show the warnings and mark the items on the delivery page
Files: `resources/views/deliveries/show.blade.php`
What:
a) New block directly after the Unparsed Lines block (after its `@endif`, before
   the comment that starts the next section), shown when
   `$delivery->import_data['parser_warnings']` has entries that do **not** start
   with `Deposit code` (those are already shown in the Barrels/Deposits block).
   Amber box like the deposit one, heading `Parser warnings`, then a one-line
   summary built from the items: `N lines failed the price check` (count of
   `price_valid === false`) and `M case sizes repaired` (count of
   `price_valid === true && price_note !== null`), each only when > 0, then the
   warnings as a `<ul>`. Static Blade, no Alpine needed.
b) Desktop Invoiced cell (the `<td>` at line 1324): inside the non-weight branch
   after the `Ordered:` span, add
   `@if($item->price_valid === false)` a small red badge `Price check` with
   `title="{{ $item->price_note }}"`; `@elseif($item->price_note)` a small
   blue badge `Case size repaired` with the same title. Weight-based branch:
   same badge after the unit span for `price_valid === false` only.
c) Mobile "Inv:" cell (line 1041–1045): the same two badges after the number,
   text-xs.
d) The three `x-collapse` attributes (lines 239, 320, 589) are removed; the
   plugin is not installed (`package.json` has only `alpinejs`) so the directive
   does nothing except log a console warning, and each element already has
   `x-show`. (From `findings/2026-10-19-udea-order-4804899-parser-warnings.md` item 3.)
Check: rendering test in step 8; `grep -c "x-collapse" resources/views/deliveries/show.blade.php` → 0.

### 7. Mark the failing row on the import preview
Files: `resources/views/deliveries/create.blade.php`
What: in the preview row template (lines 748–756) give the Units cell
`text-red-700 dark:text-red-400 font-semibold` and `title="${item.price_note}"`
when `item.price_valid === false`, and append a small `repaired` text in blue
when `item.price_valid !== false && item.price_note`. The warnings list above
the table already shows the texts.
Check: upload `Order_4804899.pdf` on `/deliveries/create` (dev, Udea supplier
5) → preview shows the `Case size repaired for 5004102` line in the yellow list,
confidence 100 %, row `5004102` reads Units 40 with the `repaired` mark; no red
cell. Browser console clean. Do not submit yet (step 9 does).

### 8. Feature tests
Files: `tests/Feature/DeliveryPriceCheckTest.php` (new — check it does not exist first)
What: copy the setup of `tests/Feature/DeliveryDepositReconciliationTest.php`
(`AliasesMysqlConnection`, `CreatesDepositPosTables`, `RefreshDatabase`, the
`delivery()` and `manager()` helpers) and add:
- `test_price_check_result_is_stored_on_the_item`: build a parsed array like
  `DeliveryImportBarrelCodeTest` with one item `price_valid => false,
  price_note => '1×3.99×6=23.94, line total 12.00'`, one `price_valid => true,
  price_note => 'case size read as 401; …'`, one with neither key; run
  `convertToDeliveryItems` + `importFromPdfData`; assert the three rows have
  `price_valid` false / true / null and the notes.
- `test_record_parser_warnings_merges_into_import_data`: delivery with
  `['filename' => 'Order_1.pdf', 'deposit_reconciliation' => ['x' => 1]]`; call
  `recordParserWarnings` with two strings; assert both keys survive and
  `parser_warnings` equals the list; calling it with `[]` leaves `import_data`
  untouched.
- `test_delivery_page_lists_parser_warnings_and_marks_items`: delivery with
  `parser_warnings => ['[Order_1.pdf] Case size repaired for 5004102: …',
  '[Order_1.pdf] Price mismatch for 5007341: …', '[Order_1.pdf] Deposit code 313: …']`
  and two items created with `DeliveryItem::create` (one failed with a note, one
  repaired); `GET deliveries.show` as manager → `assertSee('Parser warnings')`,
  the two non-deposit texts, `assertSee('1 line failed the price check')`,
  `assertSee('1 case size repaired')`, `assertSee('Price check')`,
  `assertSee('Case size repaired')`, and `assertDontSee` of the deposit text
  *inside the Parser warnings block* is not practical — instead assert the
  deposit text appears exactly once in the response body (`substr_count`), since
  the deposit block does not render without `deposit_reconciliation`.
- `test_delivery_page_without_parser_warnings_has_no_block`: `assertDontSee('Parser warnings')`.
Check: `php artisan test tests/Feature/DeliveryPriceCheckTest.php` → all pass;
`php artisan test tests/Feature/DeliveryImportBarrelCodeTest.php tests/Feature/DeliveryDepositReconciliationTest.php` → still pass (they show `!` deprecation marks on PHP 8.5; that is the baseline).

### 9. Re-import delivery 166 on dev
Files: none (dev data)
What: dev only. Delete delivery 166 (`DEL-20261019-145724`, draft; owner
decision recorded in Context) with the page's Delete action
(`DeliveryController::destroy`, which deletes scans and items, then the
delivery). It does not touch `delivery_barrels` or `delivery_documents`
explicitly: afterwards check in tinker for rows with `delivery_id = 166` in both
tables and delete any left over (the documents' files under `storage/app` may be
left; say so in the report). Then on `/deliveries/create` pick Udea (supplier 5), delivery date
2026-10-19, upload the four PDFs from `~/Downloads/Order_48048*.pdf` and
`Order_48049*.pdf`, preview, submit.
Check: on the new delivery's page — the success message ends
`(2 parser warnings, listed on this page)`; the Parser warnings block shows
`1 case size repaired` and the `Case size repaired for 5004102` line (the
deposit 313 line stays in the Barrels/Deposits block, which still reports
`1 deposit mismatch`); the `5004102` row reads Invoiced `40`, `1 × 40`, unit cost
`€0.49`, description `12,50 gram Chocolate-bar milk, Vivani Biologisch DE`, blue
`Case size repaired` badge; in tinker the row has `units_per_case 40,
ordered_quantity 40, invoice_delivered_quantity 40, unit_cost 0.4900,
price_valid 1`. Browser console has no `x-collapse` warning. Record the new
delivery id under "Dev state" in the report. 279 items as before.

### 10. Docs
Files: `docs/features/delivery-system.md`
What: in the Udea parser section (around line 457–470, "Qty × Price × SKU price
validation") add a short paragraph: the glued-case-size repair and its rule, the
`price_valid` / `price_note` columns, the `import_data.parser_warnings` key and
the Parser warnings block.
Check: the paragraph names the real column and key names.

## Verification

1. `scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests -q` → all pass (≥ 212).
2. `scripts/invoice-parser/venv/bin/python -I scripts/invoice-parser/delivery_parser_laravel.py --file ~/Downloads/Order_4804899.pdf --supplier udea | python3 -c 'import json,sys; r=json.load(sys.stdin); i=[x for x in r["data"]["items"] if x["code"]=="5004102"][0]; print(i["case_size"], i["total_delivered_units"], i["unit_cost"], i["price_valid"], i["product"][:12]); print(r["confidence"]); print(*r["warnings"], sep="\n")'`
   → `40 40 0.49 True 12,50 gram C`, `100.0`, one `Case size repaired` line and
   one `Deposit code 313` line.
3. The same for `Order_4804901.pdf`, `Order_4804902.pdf`, `Order_4804914.pdf` →
   no warnings, confidence 100 (unchanged).
4. `php artisan test tests/Feature/DeliveryPriceCheckTest.php tests/Feature/DeliveryImportBarrelCodeTest.php tests/Feature/DeliveryDepositReconciliationTest.php` → all pass.
5. `php artisan test` → the same failure set as the baseline you recorded
   (README: 15 unrelated failures on 2026-10-08, 16 on 2026-10-17), nothing new.
6. `./vendor/bin/pint --test` on the changed PHP files → clean.
7. Step 9's browser check, with the console open.

## Risks

- The repair is price-guided, so a line with a glued SKU *and* a discount is not
  repaired and stays a failed check (correct: a human has to look). A line
  where the leftover digits are not the start of the content (e.g. SKU `12`
  glued to `0,250` read as `120,250`) is caught by `_preprocess_line`'s existing
  rules first; the sweep found none that the new rule would mis-repair.
- `price_note` is 255 chars; parser notes are ~70. Truncate with `Str::limit`
  in `importFromPdfData` to be safe.
- The preview JSON item keys are the ones from `convertToDeliveryItems`
  (`price_valid`, `price_note`), not the Python ones; step 7 reads those.
- `DeliveryItem::create` rows made by `createDeliveryItem()` (manual add) and
  the Dynamis XLSX path keep `price_valid NULL`; the badge code must treat
  `NULL` as "no check", not as failed (`=== false`).
- The migration adds two nullable columns to `delivery_items` (31,737 rows on
  dev, production similar); MySQL 5.7 does this in place.

## Review

Planner, 2026-10-19 (evening). Read `implemented-price-check.md` to the end,
the full `git diff` of the eleven files, and reran every verification command.

### Criteria

| Criterion | Result |
|---|---|
| 1 Parser repair | **Pass.** `_repair_glued_case_size()` as specified; called right after `_parse_line()` (Deviation 1, accepted below). `Order_4804899.pdf` → `5004102` case 40, 40 units, €0.49, product `12,50 gram …`, `price_valid` true, `case_size_repaired` true, confidence 100.0, warnings = one `Case size repaired` + the `Deposit code 313` line, `total_value` 3238.27 unchanged |
| 2 Parser tests | **Pass.** `pytest -q` → 212 passed (208 before). The four lines parse and repair exactly as the Planner prototyped |
| 3 `convertToDeliveryItems` | **Pass.** `price_valid` (bool, default true) and `price_note` beside `Price_Valid` |
| 4 Columns + model + import | **Pass.** Migration adds nullable `price_valid` / `price_note(255)` after `total_cost`; fillable + boolean cast; `importFromPdfData()` writes null when the key is absent, note capped with `Str::limit(…, 250)`. Dev: every item before delivery 168 has `price_valid` NULL, all 279 of 168 are true |
| 5 `recordParserWarnings` | **Pass.** Merges like the deposit method, no-op on empty; called in `storePdf()` in its own try/catch; success suffix changed |
| 6 Delivery page | **Pass.** Parser warnings block with the two counts; red `Price check` / blue `Case size repaired` badges in the desktop Invoiced cell (weight branch red only) and the mobile `Inv:` cell; `x-collapse` count 0 |
| 7 Preview | **Pass.** Red Units cell with `title` on a failed check, blue `repaired` mark otherwise; `npm run build` run; headless-Chrome preview of the four PDFs showed 100 %, 279 items, the repaired row, no red cells, console clean |
| 8 Feature tests | **Pass.** `tests/Feature/DeliveryPriceCheckTest.php`, five tests; with `DeliveryImportBarrelCodeTest` and `DeliveryDepositReconciliationTest` → 10 tests, 53 assertions, no failures (`!` = the PHP 8.5 PDO deprecation, baseline) |
| 9 Re-import on dev | **Pass.** 166 and the intermediate 167 gone, no orphan items / barrels / document rows; **168** `DEL-20261019-153708` has 279 items, the Vivani row `units_per_case 40, sku 40, supplier_case_units 40, ordered 40, invoiced 40, unit_cost 0.4900, total 19.60, price_valid 1`, `import_data.parser_warnings` holds both warnings |
| 10 Docs | **Pass.** Paragraph in `docs/features/delivery-system.md` names the real function, columns and key |
| Verification 5 full suite | **Pass.** 16 failed, the 2026-10-17 set exactly (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2, `TestScraperControllerTest` ×1, `BackupConfigurationTest` ×1), nothing new |
| Verification 6 pint | **Pass.** 6 files |

Planner's extra check: the new parser run over every Udea PDF under
`storage/app` (155 files now, 13,129 items): 12 lines repaired (the same
`5004101` / `5004102` / `5012204` pattern, the extra two are the re-imported
copies of order 4804899), 21 still failed (the known discounts, `88927`,
`6000681`, and the two `530144` weight glues), no line repaired that had
passed before. Same picture as the pre-plan sweep.

### Deviations

1. Repair called before SKU / Content are read — **accepted**, the plan was
   wrong about the order: the product name is built from `Content` before the
   validation branch, so the plan's placement would have left `2,50 gram` in
   the name. Behaviour is as the plan describes.
2. Deposit filter accepts the `[file] ` prefix — **accepted**, correct for
   multi-PDF imports.
3. Third test item stored true, not null — **accepted**; the plan conflated the
   parser item (defaults true in step 3) with the converted record (null when the
   key is absent). The test now covers both.
4. `:g` formatting in `price_note` — **accepted**, matches the plan's own example.
5. Deposit warnings shown in the Parser warnings block when the deposit block
   has none — **accepted**. Without it the 313 warning would have been visible
   nowhere on a multi-PDF import. Root cause is in the deposit track (Notes 1).
6. Extra delete / re-import round — **accepted**, dev data only, state recorded.

### Notes for Planner

1. Deposit reconciliation empty on multi-PDF imports — **deferred to the deposit
   track**: finding filed as
   `docs/deposit/findings/2026-10-19-multi-pdf-deposit-reconciliation.md`
   (`parseMultipleDeliveryPdfs()` drops `barrels.line_units`;
   `recordDepositReconciliation()` filters with `str_starts_with('Deposit code')`
   which the prefixed warnings never match). Until fixed, Deviation 5 keeps the
   warning visible.
2. Orphaned PDF files under `storage/app/private/deliveries/2026/10/166/` and
   `167/` — **deferred**, noted in `README.md` open items (`destroy()` removes
   rows, not files; pre-existing).
3. Weak `Case size repaired` assertion — **accepted as is**; the counts line is
   the discriminating assertion, as the Implementer says.
4. Parser test count 212 — **done**, `README.md` updated.

Planner's own follow-up, not a revision: the repair prepends the leftover
digits to the content. A leftover that starts with `0` cannot be the start of a
content number except a weight (`0,250 kilogram`), so rejecting it would
harden the rule against a false repair at no cost. No such line exists in the
13,129 checked; listed in `README.md` open items.

### Decision

**ACCEPTED.** Owner to archive:
`mkdir -p docs/deliveries/archive/2026-10-19-price-check && git mv docs/deliveries/plan-price-check.md docs/deliveries/implemented-price-check.md docs/deliveries/findings/2026-10-19-udea-order-4804899-parser-warnings.md docs/deliveries/archive/2026-10-19-price-check/`
(the files are untracked, so plain `mv` also works; the finding file goes
with this plan because its items 1 and 3 are done here and item 2 is now in
the deposit finding). Then commit the eleven code files with the migration;
production needs `php artisan migrate` on deploy.
