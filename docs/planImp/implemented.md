# Product search: partial barcode and supplier-code matching — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-19

## Baseline
HEAD: 2005a3cb
Pre-existing dirty files:
```
 D docs/planImp/implemented.md      (archived by the user after the previous task)
 M docs/planImp/plan.md             (Planner's new plan)
?? docs/planImp/archive/2026-09-16-product-search/
```

## Steps

### 1. Classify tokens and drop the separate barcode mode — done
Changed: `app/Services/ProductSearch/ProductSearchService.php` — `asBarcode()`
and `applyBarcodeMatch()` deleted; `search()` always tokenises and
`runQuery()` lost its `?string $barcode` parameter; new `isCodeToken()`
(all-digit, 4+ chars → constant `CODE_TOKEN_LENGTH`) and `isDigitToken()`
(all-digit, 3+ chars, used for ranking only); `applyTokenMatch()` omits the
`NAME` clause for code tokens. Typo fallback condition untouched.
Check output:
```
$ php artisan tinker --execute='...foreach(["594341","4341","8721325594341","100"]...'
594341 total=1 first=8721325594341
4341 total=1 first=8721325594341
8721325594341 total=1 first=8721325594341
100 total=1113 first=7610313131495
```
Matches the plan (`100` ≥ 1000, so 3-digit tokens still match names).
Behaviour worth flagging: `100`'s *first* row changed from "100 Eco Toilet
Paper" to a code ending in 100, because Step 2 ranks digit suffixes above a
NAME prefix. That follows the plan's recommendation; see Notes for Planner.

### 2. Rank code fragments — done (see Deviations #3: suffix tier split in two)
Changed: `app/Services/ProductSearch/ProductSearchService.php`
(`applyRanking()` rebuilt, SQL and bindings appended together; new
`suffixGroup()` helper). Seven tiers:
```
0  CODE = phrase OR REFERENCE = phrase
1  code, reference or supplier code ends with a 4+ digit token
2  NAME LIKE 'phrase%'
3  NAME LIKE '%phrase%'
4  every token at a word start
5  code, reference or supplier code ends with a 3-digit token
6  everything else
```
The plan put every 3+ digit token in tier 1. Built that way first; it worked
for barcode fragments but wrecked size queries (`100`, `250`, `500`), so on the
user's decision the 3-digit case was demoted to tier 5. Full reasoning and
evidence in Deviations #3.
Check output (the plan's Step 2 check, live POS, `stocked: false`):
```
341      total=164  rank=5  med 58.3 ms  first=5032722315341  Biona Borlotti Beans 400g   (code ends 341)
6001397  total=1    rank=1  med 23.7 ms  first=8721325594341  Chocolatemakers forest fruit…
1397     total=4    rank=1  med 27.1 ms  first=8721325594341  Chocolatemakers forest fruit…
```
The plan expects `341` at `match_rank` 1; it is 5 by the split above, with the
same rows in the same order (no product name contains "341", so tiers 2–4 are
empty and the suffix rows still lead). `6001397` and `1397` are 4+ digit
tokens and rank 1 as the plan specifies.

Timings (live POS, warm, 5 runs, median / max; box load average 3.3–4.3).
"before" = same query on the committed code at HEAD 2005a3cb:
```
query                  before    med     max
594341                    0.0*   26.0    32.5   (*returned nothing before)
4341                     45.0    23.8    26.5
341                      76.2    58.3    59.0
1397                     31.2    27.1    30.9
6001397                   8.4    23.7    25.3
8721325594341             7.7    23.8    29.2
100                      62.9    62.7    64.4
250                         -    57.3    61.9
500                         -    51.3    54.2
chocolatemakers fruit    39.4    30.6    31.5
choc 100                 56.5    59.0    66.3
```
All < 100 ms. See Deviations #1 (one supplier-code query instead of two).

### 3. Tests — done
Changed: `tests/Concerns/CreatesProductSearchPosTables.php` (P6 "Kettle Model
4341" code `1000100`, P7 "Bulk Oats" code `1143419999999`, both unstocked with
no supplier link; docblock table and the returned id map updated. P6's code
ends in `100` so it doubles as the coincidental-barcode case for the tier-5
test — the plan suggested `1000011`),
`tests/Feature/ProductSearchApiTest.php` (three new tests; `milk` ranks 1/2 → 2/3
in `test_ranking_prefers_exact_code_then_prefix`; pagination totals 5 → 7,
`last_page` 3 → 4, page 3 → page 4; category-filter assertion widened, see
Deviations #2). `test_supplier_code_matches` needed no change — it only
asserts ids.
Check output:
```
PASS  Tests\Feature\ProductSearchApiTest
  ✓ words match in any order
  ✓ ranking prefers exact code then prefix
  ✓ stocked is default and toggle includes unstocked
  ✓ supplier code matches
  ✓ partial barcode matches code suffix first
  ✓ three digit sizes rank names above coincidental code suffixes
  ✓ supplier code ranks like a barcode
  ✓ typo correction reports corrected query
  ✓ no correction when results exist
  ✓ image url prefers blob then udea cdn
  ✓ response carries supplier price and stock details
  ✓ exclude and supplier filter
  ✓ pagination meta is accurate
  ✓ per page is capped at 50 and requires auth
Tests: 14 passed (97 assertions)
```

### 4. Docs — done
Changed: `docs/features/product-search.md`, "Matching and ranking" section
rewritten: the "Barcode mode" item is replaced by "Tokenising" (no separate
barcode mode) and "Token classification" (all-digit 4+ tokens match CODE /
REFERENCE / supplier codes only, 3-digit tokens also match names), the ranking
list is now a 0–6 table with tier 1 = "ends with a 4+ digit token" and tier 5 =
"ends with a 3-digit token", a paragraph explains why the suffix rule is split
at 4 digits, and a line records that `match_rank` values above 0 changed on
2026-09-19. Item 4 also notes that digit
tokens are never typo-corrected.
Check output:
```
$ sed -n '135,158p' docs/features/product-search.md  → tiers 0–6 listed
$ grep -n "CODE LIKE 'q%'" docs/features/product-search.md
none
```
Risk check from the plan: `grep -rn "match_rank" resources/ app/` outside
`ProductSearchService.php` → no hits, so nothing else reads specific values.

## Deviations
1. **Steps 1–2, one supplier-code lookup instead of two.** The plan describes
   `:suffixLinked_t` as "a second, tiny pluck per digit token". Implemented as
   written first and measured: the extra query cost ~4.5 ms and pushed `341`
   from 76 ms to ~67–159 ms depending on box load. Since the suffix set is a
   subset of the contains set, `supplierCodeMatches($token)` now runs one query
   selecting `Barcode, SupplierCode` and derives both lists (`str_ends_with`
   in PHP). `runQuery()` computes it once per token and passes it to both
   `applyTokenMatch()` and `applyRanking()`, so no per-instance state. Result
   sets are identical (verified: `341`, `1397`, `6001397`, `100` return the
   same rows and ranks); one query fewer per token (9 instead of 10 for `341`).
   One semantic difference: the `SUPPLIER_CODE_LIMIT` cap now drops the
   contains *and* suffix lists together, where the plan capped them
   separately. Worst realistic token is `100` at 128 supplier codes, so the
   500 cap is not reached in practice.
2. **Step 3, one more test assertion than the plan listed.** P6 and P7 are in
   `cat-drinks`, so `test_exclude_and_supplier_filter`'s category assertion
   (`[P4, P5]`) also had to gain the two new ids. The plan anticipated the
   pagination knock-on but not this one. The alternative — putting the new
   products in `cat-choc` — would have made the chocolate fixture nonsense
   ("Kettle Model 4341"), so the assertion was widened instead.
3. **Step 2, the suffix tier is split at 4 digits instead of 3.** The plan
   made tier 1 "any all-digit token of 3+ chars" (its recommended option) and
   its Step 2 check expects `341` at `match_rank` 1. Built exactly that, then
   measured what it did to size queries on live data:

   | query | product names containing the digits (page 1 of 50) | unrelated barcodes *ending* in them |
   |---|---|---|
   | `341` | 0 | 6 |
   | `100` | 34 | 17 |
   | `500` | 43 | 7 |
   | `250` | 41 | 10 |
   | `150` | 40 | 10 |

   So `/products?q=100` led with "Absolute Aromas Lavender **10ml**"
   (barcode `800783023100`), "Johnny Cashew **125g**" (`8720865728100`) and 15
   more coincidences; the first genuine "100 gram" product was row 18. The
   plan's own Verification #3 expected `choc 100` to be unchanged, which this
   broke. Raised it with the user, who chose to demote the 3-digit case to a
   tier *below* the name matches (tier 5).

   This needs no "is it a size?" heuristic and no extra query: when the digits
   appear in product names (`100`) the name tiers fill up first; when they do
   not (`341`, no product is named "341") those tiers are empty and the suffix
   rows surface at the top regardless. Verified both ways live and in tests.
   4+ digit tokens are untouched at tier 1, which is the actual feature.

   Consequence for the plan's text: the Step 2 check for `341` now yields
   `match_rank` 5 rather than 1 (same rows, same order), and the tier list in
   Step 2 has seven entries instead of six.


## Verification

1. `./vendor/bin/pint --test app/Services/ProductSearch/ProductSearchService.php tests/Feature/ProductSearchApiTest.php tests/Concerns/CreatesProductSearchPosTables.php` →
   ```
   PASS ........................................................... 3 files
   ```
2. `php artisan test --filter='ProductSearchApiTest|ProductTest|CustomerRequestTest'` →
   ```
   PASS  Tests\Feature\CustomerRequestTest
   PASS  Tests\Feature\ProductSearchApiTest      (14 tests)
   FAIL  Tests\Feature\ProductTest
     ⨯ shows 404 for non existent product        (pre-existing)
     ⨯ product statistics are displayed          (pre-existing)
   Tests: 2 failed, 38 passed (256 assertions)
   ```
   Whole suite as a regression check: `Tests: 17 failed, 387 passed (1555
   assertions), 28.44s` — the same 17 the Planner verified fail on a clean
   checkout during the previous task. No new failures.
3. Live tinker loop, `stocked: false`, 5 warm runs each (box load average 3.3):
   ```
   query                  total  rank  med ms  max ms  first row
   594341                     1     1    26.0    32.5  Chocolatemakers forest fruit milk chocolate 100 gram
   4341                       1     1    23.8    26.5  Chocolatemakers forest fruit…
   341                      164     5    58.3    59.0  Biona Borlotti Beans 400g          (code ends 341)
   1397                       4     1    27.1    30.9  Chocolatemakers forest fruit…
   6001397                    1     1    23.7    25.3  Chocolatemakers forest fruit…
   8721325594341              1     0    23.8    29.2  Chocolatemakers forest fruit…
   100                     1113     2    62.7    64.4  100 Eco Toilet Paper 4pc
   250                      841     3    57.3    61.9  A. Vogel Herbamare 250g b
   500                     1099     3    51.3    54.2  Het Dichtste Bij Spelt tagliatelle 500g
   chocolatemakers fruit      2     4    30.6    31.5  Chocolatemakers forest fruit…
   choc 100                  85     4    59.0    66.3  Belvas Chocolate Sensations 100g
   ```
   Every `took_ms` < 100. `chocolatemakers fruit`, `choc 100` and `100` all
   return the same first row as before this task, as the plan's Verification #3
   requires (that was *not* true before Deviation #3 — `choc 100` and `100` led
   with coincidental barcode rows). `341` is `match_rank` 5 rather than the
   plan's 1, with identical rows in identical order.
   Spot-checked that tier-1/5 rows are genuine suffix matches and not a
   binding-order bug: `8711521971008` has supplier code `97100`,
   `7610313131495` has `03100`, `5032722315341`'s barcode ends in `341`.
4. Browser, logged in:
   - `/customer-requests/create` picker (stocked only), `341` → top rows
     `8721325594341`, `8719189416060` (Udea **5007341**), `5412971160341`,
     `4025089071341`, `5701058012797` (Natural Medicine **33341**) — all code
     or supplier-code suffix matches, unchanged by the tier split because no
     stocked product is named "341".
   - Same picker, `4341` → one row, "Chocolatemakers forest fruit milk
     chocolate 100 gram" (`8721325594341 · Udea 6001397`).
   - Same picker, `594341` → same single row.
   - `/products?q=100` → first rows are "100 gram Chorizo, St. Hendrick",
     "100 gram Dessert mini moment raspberry", "A Vogel Bambu Coffee Instant
     Jar 100g", "A Vogel Echinaforce Oral Drops 100ml", "Absolute Aromas
     Orange Blossom 100ml". The size products lead, as before this task.

## Files changed

`git status --short` at the end. `[pre-existing]` = dirty before I started.

```
 M app/Services/ProductSearch/ProductSearchService.php
 M docs/features/product-search.md
 M docs/planImp/implemented.md
 M docs/planImp/plan.md                                      [pre-existing / Planner's]
 M tests/Concerns/CreatesProductSearchPosTables.php
 M tests/Feature/ProductSearchApiTest.php
?? docs/planImp/archive/2026-09-16-product-search/           [pre-existing]
```

Nothing committed, pushed or deployed. No writes to the POS database.

## Notes for Planner

- **Deviation #3 changes a rule the plan chose deliberately**, so it needs your
  sign-off rather than just a note: 3-digit digit tokens now rank their
  code-suffix matches *below* the name matches (tier 5), not above (tier 1).
  The plan's Step 2 check for `341` therefore reads `match_rank` 5, not 1 —
  same rows, same order. The user picked this after seeing what tier 1 did to
  `q=100` on `/products` (17 coincidental barcodes ahead of the first "100
  gram" product). Evidence table is in Deviations #3.
- **`REFERENCE` is still matched and ranked alongside `CODE`.** In this data
  the two columns are nearly always equal, so the extra LIKE buys little. Left
  alone because the plan's tier definitions name both.
- **`341` remains the slowest query** at ~58 ms, dominated by the main select
  (20 ms) and the pagination COUNT (17 ms) over its 164 matches. Unchanged in
  character by this task.
- **Timing noise:** this box ran at load average 3.3–8.8 during the session.
  An early measurement round showed `341` at a 159 ms median while an
  unaffected word query sat at 34 ms; re-profiling showed identical SQL, so
  single high readings are load, not regression. Figures reported above are
  medians of 5–7 runs taken at load ≈ 3–4.
- **Possible follow-up:** `docs/features/product-search.md` now documents the
  4-digit split as the rule for the whole search. If a later page wants "find
  me anything with these digits anywhere", it would need a flag on the
  criteria rather than a change to this ranking.
