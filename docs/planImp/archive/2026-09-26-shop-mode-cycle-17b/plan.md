# Shop mode cycle 17b — Harvest: recent picks first, the rest by search

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

The office harvest page shows a short list of recently harvested products and a search to add any other of Jon's products. The Shop harvest screen (cycle 17) has both facts in its data but presents them as one list: recent products first, then every other product Jon supplies, with nothing separating them. With no harvest in the last 30 days, that is 111 tiles and it reads as the whole range. This cycle makes the Shop screen behave like the office one: an empty search shows only the recent picks (last 30 days plus anything logged today); typing searches all of Jon's products; when there are no recent picks the screen says so and points at the search. The restriction to Jon's products is unchanged and already tested.

## Context

- `HarvestController::rows()` (cycle 17) returns `rows` = products harvested in the last 30 days ∪ logged today, restricted to Jon's products (`SupplierLink.SupplierID = config('suppliers.jon')`, `'2'`), and `available` = the rest of Jon's products. Tests: `ShopFruitVegTest::harvest_rows_lists_recent_and_available_jon_products`, `harvest_refuses_a_product_that_is_not_jons`.
- `resources/js/shop/fv-harvest.js`: `get choices()` returns `[...rows, ...available]`, filtered by `query` only when the query is non-empty. `load()` re-reads after a save and re-finds the selection.
- `resources/views/shop/fv-harvest.blade.php`: heading "What did you pick?", `shop-search` (placeholder "Search your produce", `x-model="query"`), `shop-choices` over `choices`, a `shop-empty` "Nothing to log / No produce matches that search." when `! loading && ! choices.length`, the Today list, the pad.
- Office page (`resources/views/fruit-veg/harvest.blade.php`): rows for recent products; an "Add a product" search over `availableProducts` (Jon's range not in the rows); "Nothing harvested recently. Use the search above to add products." when there are no rows.
- Dev data: 111 Jon-linked products, none harvested in the last 30 days, 10 distinct ever.

## Constraints

- Do not commit, push or deploy. No endpoint or controller change. No stylesheet change.
- The Jon restriction stays server-side as it is.

## Out of scope

- Changing the 30-day window (it is the office's).
- Availability and F&V labels screens.

## Steps

### 1. Choices: recent by default, everything by search
Files: `resources/js/shop/fv-harvest.js`
What: `get choices()` → when the trimmed query is empty return `this.rows` only; otherwise filter `[...rows, ...available]` by name. Add `get searching()` → `query.trim() !== ''` and `get hasRecent()` → `rows.length > 0`. Nothing else changes; a product logged from search appears in `rows` on the re-read after saving, so it becomes a recent pick at once.
Check: node exercise: rows `[S1]`, available `[M1, R1]`; empty query → `choices` `[S1]`; `query = 'm'` → `[M1]`; `query = 'x'` → `[]`; rows empty and empty query → `[]` with `hasRecent` false; after a stubbed save of `M1` and re-read where the server now lists it in `rows`, empty query → `[S1, M1]`.

### 2. The screen says what the list is
Files: `resources/views/shop/fv-harvest.blade.php`, `tests/Feature/Shop/ShopFruitVegTest.php`
What: under the heading, a `shop-meta` line: `x-text="searching ? 'All of your produce matching the search' : 'Recent picks · search to add anything else'"`. Replace the single empty state with two: `x-show="! loading && ! searching && ! hasRecent"` → `shop-empty` with the `carrot` icon, title "Nothing harvested recently", text "Search your produce above to log the first pick."; `x-show="! loading && searching && ! choices.length"` → `shop-empty` with the `search` icon, title "No produce matches", text "Try a shorter search.". Search placeholder becomes "Search all your produce". Test: `employee_can_open_both_screens` also asserts "Recent picks" and "Nothing harvested recently" are in the harvest markup.
Check: `php artisan test --filter="ShopFruitVegTest|ShopViewContractTest"` green.

### 3. Docs, build, format
Files: `docs/features/fruit-veg-system.md`, `docs/design/shop-mode/README.md`
What: one sentence in each: the Shop harvest screen lists recent picks (30 days) by default and searches Jon's whole range; the product set is the same as the office page. `npm run build`; `./vendor/bin/pint --test --dirty`.
Check: build succeeds.

## Verification

1. `php artisan test --filter=Shop` → green; `php artisan test` → 15 failed, the identical set; passed unchanged (no new tests).
2. Contract greps; design block `cmp` identical; `grep -c "route(" resources/js/shop/fv-harvest.js` → 0.
3. Manual, dev app: open Harvest → with nothing harvested in 30 days: no tiles, "Nothing harvested recently", the search; type "rose" → Rosemary appears; log 1 kg → the toast, and with the search cleared Rosemary is now the one recent tile with "1 kg today"; type "x" → "No produce matches"; delete the test row afterwards as in cycle 17.

## Risks

- **A fresh shop with no history** sees an empty list until the first search, which is what the office does and what the hint explains.
- None else: no server change.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end and the changed getters, view lines, test assertions and docs. Reran `php artisan test`: 15 failed / 594 passed, the identical set, assertions up by four as expected. Design block identical; no route helper in the script; Shop and contract tests green.

**Steps 1–3: pass.** An empty search shows only the recent picks, a search spans all of Jon's produce, the two empty states discriminate correctly, and a product logged from a search becomes a recent tile as soon as the search is cleared, exercised in node and by hand on the dev app (data put back).

**Deviations:** none. The extra node cases (whitespace-only query; the log-from-search round trip) and the two extra assertions are welcome.

**Notes for Planner.**
1. Harvest accumulating across a unit change: **still the open follow-up candidate** from cycle 17; not lost.
2. Waste lists its whole on-till range by design (that range is the working set): **agreed**, the comment on `choices()` explains the difference.
3. No keyboard path on the two F&V screens: **accepted**; they are tap screens for the tablet and the yard, and the till PC has a mouse.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-17b/`.
