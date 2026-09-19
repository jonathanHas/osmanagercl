# Product search: partial barcode and supplier-code matching

Status: READY
Revision: 1
Planner: Fable 5.1
Date: 2026-09-19

## Goal

Staff often find a product by typing the last few digits of its barcode. In the
shared search (`ProductSearchService`, used by `/products`, `/customer-requests`
and every future `x-product-search` consumer) that currently either returns
nothing (6+ digits) or buries the right product among a hundred codes that
merely contain the digits (3–5 digits). After this change a digit query ranks
"code ends with these digits" first, six-or-more digits no longer require a
prefix match, and supplier codes rank like barcodes. Word matching is untouched.

## Context

Measured on the live POS DB on 2026-09-19 via
`app(ProductSearchService::class)->search(new ProductSearchCriteria(q: ..., stocked: false))`:

| Query | Result today | Cause |
|---|---|---|
| `594341` | 0 rows | `asBarcode()` (`ProductSearchService.php:90`) treats any all-digit query of 6+ chars as a full barcode and `applyBarcodeMatch()` (line 187) matches `CODE = q`, `REFERENCE = q`, `CODE LIKE 'q%'`, supplier code `= q`. No suffix or contains match. |
| `341` | 164 rows, target not on page 1 | Token mode (`applyTokenMatch()`, line 206) does `CODE LIKE '%341%'` (150 codes) plus supplier codes containing 341 (28). `applyRanking()` (line 241) has no digit tier, so all land in tier 4 and sort by NAME. Only 12 codes actually *end* in 341. |
| `4341` / `94341` | 1 row, correct | Contains-match happens to be unique. |
| `6001397` (Udea supplier code) | found, `match_rank` 4 | Supplier-code matches have no ranking tier. |
| `100` | 1,113 rows | Names contain sizes ("100 gram", "100 Eco Toilet Paper"); this is expected and must keep working. |

Data facts: average barcode length 12.2; only 18 of 10,649 codes contain
letters; only 80 product names contain a run of 4+ digits. So a digit token of
4+ characters is a code fragment, not a word, and can skip `NAME` entirely.
3-digit tokens ("100", "500") are usually sizes and must still match names.

Existing pieces to keep: `likeValue()`/`like()` escaping, the two-phase
supplier-code pluck (`SUPPLIER_CODE_LIMIT`), the stocked `JOIN stocking`, the
typo fallback (`ProductSearchVocabulary::correct()` already returns null for
all-digit tokens, so digit queries are never "corrected"). The ranking CASE is
one `selectRaw(... as match_rank)` with bound parameters and must stay
SQLite-compatible (`LIKE`/`CASE`/`IN` only).

Consumers: `/products` (list mode) and `/customer-requests` form (picker,
`:min-length="2"`, stocked only). The JSON shape is unchanged by this plan;
`match_rank` values shift (documented in Step 4).

## Constraints

- Same performance rules as the accepted plan: no `whereExists` against
  `stocking`/`supplier_link`, no joins to `supplier_link`, explicit `select()`
  list, SQLite-compatible SQL. Server time stays under 100 ms warm.
- JSON field names unchanged. `match_rank` remains an integer, lower is better.
- `ProductSearchVocabulary` unchanged.
- Do not commit, push or deploy. Run `./vendor/bin/pint` on changed PHP files.

## Out of scope

- Any change to the Blade component, the controller, or the pages.
- Fuzzy matching on digits (a mistyped digit stays unmatched).
- Searching `DISPLAY` or other columns.

## Steps

### 1. Classify tokens and drop the separate barcode mode
Files: `app/Services/ProductSearch/ProductSearchService.php`

What:
- Delete `asBarcode()` and `applyBarcodeMatch()`. In `search()` always
  tokenise; `$barcode` and its branch in `runQuery()` go away.
- Add `private function isCodeToken(string $t): bool` = `ctype_digit($t) && strlen($t) >= 4`.
- In `applyTokenMatch()`: when `isCodeToken($token)` the OR group is
  `CODE LIKE '%t%' OR REFERENCE LIKE '%t%' OR CODE IN (:linked)` with no
  `NAME` clause. Otherwise unchanged (NAME, CODE, REFERENCE, linked). The
  supplier-code pluck stays as is (tokens of 3+ chars, capped at 500).
- Typo fallback condition stays `total === 0 && tokens !== []`; because
  digits are never corrected, an all-digit query that finds nothing simply
  returns nothing.

Check: `php artisan tinker --execute='$s=app(App\Services\ProductSearch\ProductSearchService::class); foreach(["594341","4341","8721325594341","100"] as $q){$r=$s->search(new App\Services\ProductSearch\ProductSearchCriteria(q:$q,stocked:false)); echo $q," total=",$r["meta"]["total"]," first=",$r["data"][0]["code"]??"-","\n";}'`
prints `594341 total=1 first=8721325594341`, `4341 total=1`,
`8721325594341 total=1`, and `100 total=` ≥ 1000 (names still match for
3-digit tokens).

### 2. Rank code fragments
Files: `app/Services/ProductSearch/ProductSearchService.php` (`applyRanking()`)

What: new tier order, still one CASE expression with bound parameters:
```
0  PRODUCTS.CODE = :phrase OR PRODUCTS.REFERENCE = :phrase           (unchanged)
1  for any code token t: CODE LIKE '%t' OR REFERENCE LIKE '%t'
   OR CODE IN (:suffixLinked_t)                                     (new: code ends with the digits)
2  NAME LIKE ':phrase%'                                              (was 1)
3  NAME LIKE '%:phrase%'                                             (was 2)
4  every token at a word start                                       (was 3)
5  everything else                                                   (was 4)
```
Tier 1 applies only to tokens where `isCodeToken()` is true, and only to
3-digit tokens if you choose to extend it (recommended: apply tier 1 to any
all-digit token of 3+ chars, so `341` also ranks its 12 suffix matches first
while still matching names). `:suffixLinked_t` is a second, tiny pluck per
digit token: `supplier_link` rows whose `SupplierCode` equals `t` or ends with
`t` (`LIKE '%t'`), limit 500, plucked barcodes; omit the `IN` clause when the
list is empty. Tier 1 therefore also lifts an exact supplier code such as
`6001397` to the top.

Bindings must be appended in the same order as the SQL fragments; keep the
existing pattern of building `$sql` and `$bindings` together.

Check: same tinker loop with `["341","6001397","1397"]` prints
`341` first result code ending in `341` with `match_rank` 1;
`6001397` → `8721325594341` with `match_rank` 1; `1397` → the Chocolatemakers
product first (supplier code ends in 1397), `match_rank` 1. Each `took_ms` < 100.

### 3. Tests
Files: `tests/Concerns/CreatesProductSearchPosTables.php`,
`tests/Feature/ProductSearchApiTest.php`

What: add two fixture products in `seedProductSearchFixture()` (unstocked,
no supplier link, keep the docblock table current):
- P6 "Kettle Model 4341" code `1000011` (name contains the digits, code does not)
- P7 "Bulk Oats" code `1143419999999` (code contains 4341 in the middle)

Then:
- New `test_partial_barcode_matches_code_suffix_first`:
  `q=4341&stocked=0` → ids `[P1, P7]` in that order, `data.0.match_rank === 1`,
  P6 absent (4-digit tokens never match names).
  `q=594341&stocked=0` → `[P1]`.
  `q=100&stocked=0` → P1 present (3-digit token still matches "100 gram").
- New `test_supplier_code_ranks_like_a_barcode`: `q=6001397` → `[P1]` with
  `match_rank` 1; `q=1397` → P1 first.
- Update `test_ranking_prefers_exact_code_then_prefix`: exact barcode still
  `match_rank` 0; the `milk` assertions become ranks 2 and 3.
- Update `test_supplier_code_matches` if its rank assertions change; the id
  assertions stay.
- `test_pagination_meta_is_accurate` counts all fixture rows: total becomes 7
  and `last_page` 4 with `per_page=2`; page 4 has 1 row. Adjust.

Check: `php artisan test --filter=ProductSearchApiTest` → all pass.

### 4. Docs
Files: `docs/features/product-search.md` ("Matching and ranking" section)

What: replace the "Barcode mode" paragraph with the token classification rule
(all-digit tokens of 4+ chars match `CODE`, `REFERENCE` and supplier codes
only; 3-digit tokens also match names) and the new six-tier ranking list,
noting that tier 1 is "code or supplier code ends with the digits" and that
`match_rank` values above 0 shifted by one on 2026-09-19.

Check: the section lists tiers 0–5 and no longer mentions `CODE LIKE 'q%'`.

## Verification

1. `./vendor/bin/pint --test app/Services/ProductSearch/ProductSearchService.php tests/Feature/ProductSearchApiTest.php tests/Concerns/CreatesProductSearchPosTables.php` → pass.
2. `php artisan test --filter='ProductSearchApiTest|ProductTest|CustomerRequestTest'` → all pass except the two pre-existing `ProductTest` failures (`shows_404_for_non_existent_product`, `product_statistics_are_displayed`).
3. Live tinker loop (stocked: false) for `594341`, `4341`, `341`, `1397`, `6001397`, `8721325594341`, `100`, `chocolatemakers fruit`, `choc 100`: expected firsts as in Steps 1–2; `chocolatemakers fruit` and `choc 100` unchanged from before; every `took_ms` < 100 warm.
4. In the browser on `/customer-requests/create`, type `341`: the dropdown's first rows are products whose barcode ends in 341. Type `594341`: the Chocolatemakers bar appears. On `/products`, `q=100` still lists the 100-gram products.

## Risks

- **Names with 4+ digit runs** (80 products, e.g. "Vitamin D3 1000iu") stop
  matching on those digits alone; they still match on their words. Accepted.
- **`CODE LIKE '%t'`** cannot use an index; it is a 10k-row scan like the
  existing contains-match, about 10 ms. Tier 1 adds one LIKE per digit token
  to the CASE, evaluated only on rows already matched.
- **`match_rank` renumbering**: nothing outside the tests reads specific
  values (grep `match_rank` in `resources/` shows only the JSON pass-through),
  but check before assuming.

## Review
(Planner fills this in after reading implemented.md and the diff.)
