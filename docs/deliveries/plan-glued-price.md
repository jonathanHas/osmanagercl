# Udea parser: prices run into the description

Status: READY
Revision: 1
Planner: Fable 5.1
Date: 2026-10-20

Side plan (runs beside cycle 3's `plan.md` / `implemented.md`, which it does
not touch). Report in `docs/deliveries/implemented-glued-price.md`. Kickoff:
`Read docs/deliveries/planimp.md. You are the Implementer. Implement docs/deliveries/plan-glued-price.md.`

## Goal

A Udea docket line whose purchase price pdfplumber ran into the description
is imported with the right cost, sale price and case size, instead of being
dropped without a trace (ice cream `6003103` on order 4798553, imported on
production today as `DEL-20261020-120934` with 66 items and a €18.52 total
mismatch as the only clue), flagged red although its cost is right
(`36489`, `36491`), or left for the owner to type in from the Unparsed lines
block (`36488`). And no line shaped like a product line can ever again leave
the parser without being listed under Unparsed lines.

## Context

### The three shapes, from the two dockets in `JFolder_temp/` (git-ignored, dev only)

pdfplumber reads the page as text runs. When a long description reaches the
Purchase column (x ≈ 428–442), the price either glues to the last word or its
characters interleave with the word's. The tail of the line is then short by
one token, and the regexes shift every column one place to the left.

1. **Glued, one price.** `Order_4798553.pdf`, the line the owner reported:
   ```
   6003103 1 1 4 900millilitre Icecream raspberry & carameliBzeiodlo gailsmchonds, HappDyE Mrs. Jersey4,63 8,29 5 18,52
   ```
   The Prf column (`39%`) is blank in the PDF itself for this line (the
   other three ice creams have it); `VAT_PROFIT_TOKEN` already tolerates
   that. What kills the line is `Jersey4,63`: all three regexes fail. The
   unmatched-line heuristic then needs a unit token or a `%` token; the unit
   is glued (`900millilitre`) and the `%` is missing, so the line is not
   even listed as unparsed. Only the products-total mismatch warning shows
   that €18.52 is gone. **Silent drop.**

2. **Glued, both prices.** `Order_4795456.pdf` line 46 (and the same product
   in 9 earlier stored dockets):
   ```
   36488 1 1 6 500millilitre Vegan-ice cream banana walnuBti otloofgfiesceh swirl, cashNeLw, Ice Cream3, 1F7actory6,39 5 46% 19,02
   ```
   The purchase price `3,17` is interleaved into `Factory` and the sale
   price `6,39` is glued to it. Fails every regex; listed as unparsed (it has
   `%`); the owner added it by hand on production (`price_valid` NULL).

3. **Interleaved purchase price.** Same docket, `36489` and `36491` (and
   `5007341` Marcel's Green Soap in 6 earlier dockets, `88927` in 4,
   `6000681` in 2):
   ```
   36489 1 1 6 500millilitre Vegan-ice cream vanilla doughB ciohloogcisoclhate, cashewN,L Ice Cream F3a,1c7tory 6,39 5 46% 19,02
   ```
   Parses, but shifted: Price = 6,39 (really the sale price), Sale = `5`
   (really the VAT code), the `46%` token is swallowed by `VAT_PROFIT_TOKEN`.
   The price check fails (1 × 6.39 × 6 = 38.34 ≠ 19.02), the row gets the red
   `Price check` badge and the cost shown in the note is wrong. The **stored
   cost is right** all the same, because `unit_cost = total / (qty × sku)`
   (`_calculate_unit_cost()`), so the damage is a false alarm plus an RSP of
   5.00 instead of 6.39. `_repair_glued_case_size()` correctly declines these
   (n = 3, SKU `6` does not start with `3`).

### What the two new rules do, measured on every docket we have

Planner's sweep, 2026-10-20: all 157 stored Udea dockets under
`storage/app/` plus the two new ones, 12,845 product lines (lines in the
captured sections starting `code ordered qty`). Scripts (session-only):
`/tmp/claude-1000/-var-www-html-osmanagercl/70de1951-2625-4a31-8eb6-37d2c5c27b1d/scratchpad/sweep_tail.py`
and `proto_tail2.py`.

| | Today | After steps 1–2 |
|---|---|---|
| Parse and pass the price check | 12,805 | 12,836 |
| Fail the price check (after the case-size repair) | 23 = 21 shifted price + 2 variable-weight glue (`530144`, open item) | 2 (`530144`) |
| Unparsed and listed | 9 (all `36488`) | 0 |
| Unparsed and **silent** | 1 (`6003103`) | 0 |
| Unparsed, no money tail, rightly silent ("Processed" section lines such as `6001624 8 1 330ml Water Kefir Jasmine & Peach, Kult`) | 7 | 7 |
| Un-glue rule (step 1) fires | – | 12 lines; 0 of them a line that parses correctly today |
| Shifted-price repair (step 2) | – | 28 candidates (21 + the 9 `36488` lines, minus the 2 `Soap2,00` lines that the un-glue rule alone fixes), 28 repaired, 0 rejected |
| Lines that pass the price check today with an integer Sale token | 0 of 12,805 | – |

That last row is the safety argument for step 2: Udea prints every sale
price with two decimals, so a Sale token without a decimal separator is a
shifted tail, never a real sale price. The digit check in step 2 is the
second lock.

### Where the code is

- `scripts/invoice-parser/parsers/delivery_udea.py`
  - `NUMBER` :35, `VAT_PROFIT_TOKEN` :37, `NORMAL_REGEX` / `QUANTITY_SKU_REGEX` / `FALLBACK_REGEX` :41–85.
  - `_preprocess_line()` :152–204, a chain of `re.sub` fixes; the first one
    (`(\d)([a-z])` → `\1 \2`) is why `F3a,1c7tory` arrives at `_parse_line()`
    as `F3 a,1 c7 tory` and why the digit check in step 2 must strip
    letters and spaces, not look at the last word.
  - `_parse_line()` :230–347 returns the dict `Code, Ordered, Qty, SKU,
    Content, Description, Price, Sale, Total, is_weight_based, …`; numbers
    are already through `_clean_number_string()` (`6,39` → `6.39`, `5` stays
    `5`).
  - `_validate_price()` :349, tolerance `self.calculation_tolerance` = 0.015.
  - `_repair_glued_case_size()` :364–402, the model for step 2 (same
    signature, same "return None when the check passes" shape).
  - `parse_invoice()`: `repair_note = self._repair_glued_case_size(parsed)`
    at :747 runs **before** `ordered/qty/sku/price/sale/total` are read from
    `parsed` (:748–753), so a repair that rewrites `parsed["Price"]` and
    `parsed["Sale"]` is picked up by everything below. Validation block
    :781–800 (writes `price_note`, appends `Price mismatch for …` /
    `Case size repaired for …` warnings), item dict :828–845
    (`price_valid`, `price_note`, `case_size_repaired`). Unmatched branch
    :857–875 (`is_likely_product` heuristic on the **raw** `line`).
- `scripts/invoice-parser/tests/test_delivery_udea.py`: `_parse(parser, raw)`
  helper :143 runs `_preprocess_line` + `_parse_line`; the four repair tests
  :149–190 are the pattern to copy. 212 tests pass today.
- Laravel side needs no data change: `DeliveryParsingService::convertToDeliveryItems()`
  :357–364 already passes `RSP`, `price_valid`, `price_note`;
  `DeliveryService::importFromPdfData()` stores them. Only the labels:
  `resources/views/deliveries/show.blade.php` :552 (`$caseSizesRepaired`
  counts every valid item with a note), :562 ("N case sizes repaired"),
  :1077 and :1375 (blue badge text "Case size repaired");
  `resources/views/deliveries/create.blade.php` :755 (generic "repaired"
  mark, fine as is).
- `tests/Feature/DeliveryPriceCheckTest.php` (5 tests):
  `test_delivery_page_lists_parser_warnings_and_marks_items` :131 asserts
  the badge and the count text.
- `docs/features/delivery-system.md` :467, the price-check paragraph.

### Production state (read-only, 2026-10-20)

Production has the price-check deploy (parser repair present, `price_valid`
column exists). Delivery id 168 `DEL-20261020-120934`, supplier 85, 66 items,
`import_data.filename` = `Order_4795456.pdf, Order_4798553.pdf`:
`6003103` missing; `36488` added by hand (6 units at €3.17, `price_valid`
NULL); `36489` and `36491` `price_valid` false with notes `1×6.39×6=38.34,
line total 19.02` / `2×6.39×6=76.68, line total 38.04` but `unit_cost` 3.17
(right). Nothing in this plan touches production data; see "Owner action"
under Verification.

## Constraints

- The Planner never edits application code; the owner commits. No commit,
  push or deploy in this plan.
- Production PHP is 8.3.6: no PHP 8.4+ syntax. The parser venv is CPython
  3.12: nothing newer than 3.12 syntax in Python.
- A repair must only ever fire on a line that **fails** the price check, and
  must leave the line passing it. Never change `Price`, `Sale` or `SKU` on a
  line that already passes.
- Every repair is visible: a `… repaired for <code>: …` warning, the note on
  the item, the blue badge. A silent correction is as bad as a silent drop.
- Udea totals come from the docket; `unit_cost = total / (qty × sku)` stays
  the stored cost (unchanged by this plan).
- Existing parser tests (212) and `tests/Feature/DeliveryPriceCheckTest.php`
  keep passing unchanged, except for the one assertion step 6 names.

## Out of scope

- The variable-weight glue `530144 1 1 122,70kilogramCashews …` (2 dockets,
  README open item).
- Cleaning the garbled descriptions pdfplumber produces (`cashewN,L`,
  `F3 a,1 c7 tory`); they are stored as today.
- A column-based (x-position) extraction of the tail. It would fix all of
  this at the source, but it is a rewrite of how the parser reads a page;
  the text rules below cover every case in 12,845 lines.
- The multi-PDF deposit reconciliation finding (deposit track), the dead
  `import_warnings` flash, orphaned PDFs of deleted deliveries, the
  zero-leading leftover guard in `_repair_glued_case_size()`.
- Any change to production data. The owner adds `6003103` to
  `DEL-20261020-120934` by hand.

## Steps

### 1. Un-glue a money token from the word before it

Files: `scripts/invoice-parser/parsers/delivery_udea.py`

What: a module-level pattern next to the other regexes and one more `re.sub`
at the **end** of `_preprocess_line()` (after every existing rule, so the
digit/letter splits have already run):

```python
# European money amount as Udea prints it: "2,27", "12,00", "1.234,56"
MONEY: Final[str] = r"\d{1,3}(?:\.\d{3})*,\d{2}"

# A price pdfplumber glued to the last word of the description, e.g.
# "... Mrs. Jersey4,63 8,29 5 18,52" or "... 1F7actory6,39 5 46% 19,02".
# Only fires when what follows is a complete money tail
# (optional sale, VAT code, optional profit %, total), so a code glued to a
# country ("NL10046") or a quantity inside a description is left alone.
GLUED_PRICE_REGEX: Final[Pattern[str]] = re.compile(
    rf"([^\s\d])({MONEY})(?=\s+(?:{MONEY}\s+)?\d+\s+(?:\d+%\s+)?{MONEY}$)"
)
```

and in `_preprocess_line()`, last line before `return line`:

```python
line = GLUED_PRICE_REGEX.sub(r'\1 \2', line)
```

Keep the thousands separator as `\.` and the decimal as `,` exactly as
above; the 0-regression figure in Context was measured with this pattern.

Check: `_parse(parser, <6003103 line from Context>)` returns `Price` `4.63`,
`Sale` `8.29`, `Total` `18.52`, `SKU` `4`, and `_validate_price(1, 4.63, 4,
18.52)` is `(True, 18.52)`. The `5007337` line
`5007337 1 1 6 750millilitre All-purpose-cleaner sandalwood N&o cna-frodoadmom, MarceNl'Ls Green Soap2,00 3,99 4 39% 12,00`
parses with `Price` `2.00`, `Sale` `3.99` and passes. The `36488` line
parses (Price `6.39`, Sale `5`, Total `19.02`) and fails the check; step 2
takes it from there.

### 2. Repair a shifted tail from the line total

Files: `scripts/invoice-parser/parsers/delivery_udea.py`

What: a new method after `_repair_glued_case_size()`:

```python
def _repair_shifted_price(self, parsed: Dict[str, str]) -> Optional[str]:
    """Repair a purchase price that pdfplumber ran into the description.

    "... Ice Cream F3a,1c7tory 6,39 5 46% 19,02": the purchase price 3,17 is
    interleaved with "Factory", so the sale price is read as Price and the
    VAT code as Sale. Udea prints every sale price with two decimals, so an
    integer Sale is a shifted tail. The total gives the real price; its
    digits must also be present in the description. Mutates parsed["Price"]
    and parsed["Sale"] and returns a note, or returns None.
    """
```

Rule, in this order, returning `None` as soon as one fails:

1. `parsed.get("is_weight_based")` is false.
2. `sale_str = (parsed.get("Sale") or "").strip()` is non-empty and contains
   no `.` (already cleaned, so `6,39` would be `6.39`; `5` stays `5`).
3. `qty`, `price`, `total` parse as floats (as in `_repair_glued_case_size`),
   `sku = float(parsed["SKU"]) if parsed["SKU"] else 1.0`; the line fails
   `self._validate_price(qty, price, sku, total)`; `qty > 0`, `sku > 0`,
   `total > 0`.
4. `derived = round(total / (qty * sku), 2)`;
   `abs(qty * sku * derived - total) <= self.calculation_tolerance`;
   `derived < price` (the sale price is always above the purchase price).
5. `digits = re.sub(r"[^\d,]", "", parsed.get("Description") or "")` ends
   with `f"{derived:.2f}".replace(".", ",")`. (`Ice Cream F3 a,1 c7 tory` →
   `3,17`; `Green Soa2,p00` → `2,00`; `Levi3 e,46` → `3,46`.)
6. Then `parsed["Sale"] = parsed["Price"]`, `parsed["Price"] = f"{derived:.2f}"`,
   and return
   `f"purchase price {derived:.2f} ran into the description; {price:g} (the sale price) was read in its place, {total:.2f}÷({qty:g}×{sku:g})={derived:.2f}"`.

In `parse_invoice()`, directly after `repair_note = self._repair_glued_case_size(parsed)`:

```python
price_repair_note = None if repair_note else self._repair_shifted_price(parsed)
```

so both run before `price`/`sale` are read from `parsed`. In the validation
block, where `if repair_note:` sets `price_note` and appends
`Case size repaired for {code}: …`, add the second kind:

```python
elif price_repair_note:
    price_note = price_repair_note
    result["warnings"].append(f"Purchase price repaired for {code}: {price_repair_note}")
```

and in the item dict add `"price_repaired": bool(price_repair_note) and is_valid,`
next to `case_size_repaired`. `rsp` (= `sale`) now carries the real sale
price on these lines.

Check: `parsed = _parse(parser, <36489 line>)`; `parser._repair_shifted_price(parsed)`
returns a note containing `19.02÷(1×6)=3.17`; `parsed["Price"] == "3.17"`,
`parsed["Sale"] == "6.39"`; `_validate_price(1, 3.17, 6, 19.02)[0]` is True.
The Marcel's discount line from `test_repair_leaves_a_genuine_discount_alone`
returns `None` (its Sale has decimals). A line with an integer Sale but no
price digits in the description, e.g.
`36489 1 1 6 500millilitre Vegan-ice cream vanilla, Ice Cream Factory 6,39 5 46% 19,02`,
returns `None` and is left unchanged.

### 3. Never drop a product-shaped line silently

Files: `scripts/invoice-parser/parsers/delivery_udea.py`

What: pull the `is_likely_product` heuristic (:858–865) into a method
`_is_likely_product_line(self, line: str) -> bool` and call it with the
**preprocessed** line (`preprocessed_line`, not `line`), so a glued unit
(`900millilitre`) has already been split. Add a structural test to it:

```python
has_structure = re.match(r'^\d+\s+\d+\s+\d+\s+\S', line) is not None
has_money_tail = re.search(r'\d[.,]\d{2}$', line) is not None
return has_price_pattern and (has_unit_keywords or has_percentage or (has_structure and has_money_tail))
```

The unmatched entry keeps recording the raw `line[:100]` as today (the
owner wants to see what the PDF said).

Check: `parser._is_likely_product_line(parser._preprocess_line(<6003103 line with "4,63 8,29 18,52" as its tail, i.e. VAT token removed so it still fails every regex>))`
is True (the split unit `900 millilitre` now counts); the unit-less
`6003103 1 1 4 Icecream Jersey 4,63 8,29 18,52` is True only because of the
new structural clause; `parser._is_likely_product_line("6001624 8 1 330ml Water Kefir Jasmine & Peach, Kult")`
is False; `parser._is_likely_product_line("3703 1 8 450gram Green-beans, Natural Cool NV / 28-10-2026 / Niet geleverd leverancier, geen")`
is False.

### 4. Parser tests

Files: `scripts/invoice-parser/tests/test_delivery_udea.py`

What: add, using the `_parse` helper and the real lines from Context:

- `test_unglue_price_from_description_jersey` (6003103: parses, Price `4.63`,
  Sale `8.29`, SKU `4`, Content `900 millilitre`, passes the check).
- `test_unglue_price_marcels_soap` (5007337: Price `2.00`, Sale `3.99`,
  passes; `_repair_shifted_price` then returns `None`).
- `test_unglue_both_prices_then_repair_shift` (36488: parses; repair returns
  a note; Price `3.17`, Sale `6.39`; passes).
- `test_repair_shifted_price_interleaved` (36489: as in step 2's check).
- `test_repair_shifted_price_leaves_discount_alone` (the Marcel's discount
  line already in the file → `None`, Price/Sale unchanged).
- `test_repair_shifted_price_needs_digits_in_description` (step 2's last
  example → `None`, unchanged).
- `test_repair_shifted_price_leaves_passing_line_alone` (the pineapple line
  from `test_repair_leaves_a_passing_line_unchanged` → `None`).
- `test_likely_product_line_guard` with the four step 3 inputs.

Update the module docstring's last paragraph to mention the glued / shifted
price rules.

Check: `scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests -q`
→ 220 passed (212 + 8), 0 failed.

### 5. Both dockets parse clean through the wrapper

Files: none (check only)

What: run the Laravel wrapper on both files:

```
scripts/invoice-parser/venv/bin/python scripts/invoice-parser/delivery_parser_laravel.py --file JFolder_temp/Order_4795456.pdf
scripts/invoice-parser/venv/bin/python scripts/invoice-parser/delivery_parser_laravel.py --file JFolder_temp/Order_4798553.pdf
```

Check: `Order_4795456.pdf` → 62 items (61 today), confidence 100, warnings
are exactly three `Purchase price repaired for 36488/36489/36491: …` lines
and nothing else (no `Price mismatch`, no `likely product lines`, no
`total mismatch`); items `36488`, `36489` have `unit_cost` 3.17, `rsp` 6.39,
`price_valid` true, `price_repaired` true. `Order_4798553.pdf` → 5 items
(4 today), no warnings at all, `6003103` with `case_size` 4,
`total_delivered_units` 4, `unit_cost` 4.63, `line_total` 18.52,
`price_valid` true, `price_note` null. Paste the trimmed JSON for those
items into the report.

### 6. Delivery page labels for the second repair kind

Files: `resources/views/deliveries/show.blade.php`, `tests/Feature/DeliveryPriceCheckTest.php`

What: the blue badge and the count currently assume every note on a valid
item is a case-size repair. Make them read the note:

- :552: split the count into `$caseSizesRepaired` (valid item whose note
  starts with `case size`) and `$pricesRepaired` (valid item whose note
  starts with `purchase price`); :562 keeps "N case sizes repaired" and adds,
  when `$pricesRepaired > 0`, a second blue span
  `{{ $pricesRepaired }} purchase {{ Str::plural('price', $pricesRepaired) }} repaired`.
- :1077 and :1375: badge text `Str::startsWith($item->price_note, 'purchase price') ? 'Price repaired' : 'Case size repaired'`
  (same classes, same `title`). A `@php` helper variable or a small
  `@php` closure at the top of the block is fine; no new Blade component.
- `create.blade.php` :755 stays ("repaired" with the note as title).
- Test: in `DeliveryPriceCheckTest`, add
  `test_delivery_page_labels_a_repaired_purchase_price`: a delivery with
  `parser_warnings` `['Purchase price repaired for 36489: purchase price 3.17 ran into the description; 6.39 (the sale price) was read in its place, 19.02÷(1×6)=3.17']`
  and one item `price_valid` true, `price_note` starting `purchase price` →
  page shows `Price repaired`, `1 purchase price repaired`, the warning text,
  and not `Case size repaired`.

Check: `php artisan test --filter=DeliveryPriceCheckTest` → 6 passed;
`./vendor/bin/pint --test` on the changed PHP files passes (`pint` on
Blade is not needed).

### 7. Feature doc

Files: `docs/features/delivery-system.md`

What: after the price-check paragraph at :467, one paragraph **"Prices run
into the description (2026-10-20)"**: the glued-price un-glue rule in
`_preprocess_line()`, the shifted-tail repair (`_repair_shifted_price()`,
integer Sale + digits in the description + total), the `Purchase price
repaired for …` warning and `Price repaired` badge, the fact that
`unit_cost` was already right on these lines and only the flag and RSP were
wrong, and the `_is_likely_product_line()` guard (a line with the
`code ordered qty …` shape and a money tail is always listed as unparsed).

Check: the paragraph renders in the file; no other doc changed.

### 8. End-to-end import on dev

Files: none (dev data only)

What: on dev, import `JFolder_temp/Order_4795456.pdf` and
`JFolder_temp/Order_4798553.pdf` together through `/deliveries/create`
(supplier Udea, as the owner does; the headless-Chrome scripts in
`docs/deposit/scripts/browser/` give a logged-in session, or use the
browser). This becomes dev delivery 169. Keep it as Draft and name it in the
report ("Dev state"); do not delete 168 (`DEL-20261019-153708`, last cycle's
keeper).

Check: 67 items. `6003103` present: 4 units, unit cost €4.63, total €18.52,
no badge. `36488` present as a parsed item (not manual): 6 units at €3.17,
blue `Price repaired` badge. `36489`, `36491`: blue `Price repaired`, no red
`Price check`. The Parser warnings block lists the three
`[Order_4795456.pdf] Purchase price repaired for …` warnings and says
`3 purchase prices repaired`; no `Products total mismatch` line, no Unparsed
lines block. Browser console clean. Screenshot or trimmed DOM text in the
report.

## Verification

In order, at the end:

1. `scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests -q` → 220 passed.
2. Step 5's two wrapper runs → the item facts listed there, 0 warnings for
   4798553, exactly 3 `Purchase price repaired` warnings for 4795456.
3. `php artisan test --filter=DeliveryPriceCheckTest` → 6 passed.
4. `php artisan test tests/Feature/DeliveryPriceCheckTest.php tests/Feature/DeliveryDepositReconciliationTest.php tests/Feature/DeliveryImportBarrelCodeTest.php` → all pass.
5. `./vendor/bin/pint --test` → PASS on the touched PHP files.
6. `php artisan test` → the known baseline only (16 failures:
   `UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3,
   `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2,
   `TestScraperControllerTest` ×1, `BackupConfigurationTest` ×1).
7. Step 8's dev import, with the facts recorded.

Owner action (not the Implementer's): on production, add `6003103` to
`DEL-20261020-120934` by hand (4 units, €4.63, €18.52 total) now; the red
`Price check` badges on `36489` / `36491` there can be ignored (cost
€3.17 is right). After the next deploy nothing needs re-importing.

## Risks

- **Un-glue rule touching a legitimate token.** It only fires when a
  non-digit character is immediately followed by a money amount and a
  complete money tail. Measured: 12 hits in 12,845 lines, every one a real
  glue, 0 on a line that parses correctly today. A thousands-separated
  price (`1.234,56`) glued to a word is covered by `MONEY`.
- **Shift repair on a genuine discount line.** A discount line has a full
  tail, so its Sale has decimals and rule 2 stops it; and its description
  does not end in the derived price's digits. 0 of 12,805 valid lines have
  an integer Sale.
- **The derived price is a rounding of total / units**, like `unit_cost`
  already is. The note shows the arithmetic so the owner can see it.
- **The structural guard listing non-product lines.** It also requires a
  money tail; the "Not available" and "Processed" section lines have none,
  and those sections are outside the captured range anyway. Measured: 0 new
  unparsed entries beyond the one silent drop.
- **Badge text keyed on the note's first words.** Both notes start with a
  fixed phrase written by the parser (`case size read as …`, `purchase price
  … ran into …`); an item from a CSV / XLSX import has no note.

## Review

(Planner fills this in after reading implemented-glued-price.md and the diff.)
