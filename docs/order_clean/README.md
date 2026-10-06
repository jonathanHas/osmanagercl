# Order clean-up — plan / implement track

Order-management work runs here, separate from the deposit track in
`docs/deposit/`, the Shop view updates in `docs/shop_new/` and the vouchers
track in `docs/vouchers/`. Same protocol, same file roles: see
[`planimp.md`](./planimp.md). The Planner keeps this file current; a fresh
session reads it before touching order code.

The first cycle is a **Shop view screen** (design screen 20, Order review),
so the Shop rules in `docs/shop_new/README.md` apply to it as well as the
order rules below. Later cycles may be office-side clean-up of the order
generation, review and comparison pages.

## Where things stand (2026-10-06)

| | |
|---|---|
| Current task | none. **Cycle 2** (week readout on the chart, product pictures with the shared hover panel `product-peek.js`, chilled groups Cheese/Refrigerated first) ACCEPTED 2026-10-06, archived to `archive/2026-10-06-cycle-2-readout-pictures-groups/`. Cycle 1 (Shop order review, screen 20) ACCEPTED the same day, `archive/2026-10-06-cycle-1-shop-order-review/`. Both uncommitted; the owner commits. Next: whatever the owner brings; the tablet check of an Udea draft decides whether `PAGE` drops to 25 |
| HEAD | `36207bbf` on `feature/modularization-phase1` |
| Working tree | cycle 1 uncommitted (see `implemented.md` Files changed) on top of the deposit track's uncommitted cycles 1–3 (parsers, migrations, models, services, `/deposits`, tests) and the glennon parser; none of it touches order code. The Implementer records the baseline `git status --short` so the reviewer can tell the two apart |
| Test baseline | 15 failed / 1023 passed after cycle 2 (2026-10-06; 999 before cycle 1). The 15 are the known unrelated set: `UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2, `TestScraperControllerTest` ×1 |

## Where the order code is

| What | Where |
|---|---|
| Models | `app/Models/OrderSession.php`, `OrderItem.php`, `OrderAdjustment.php`, `ProductOrderSetting.php` (the last pins the `mysql` connection) |
| Generation, suggestions, quantity updates, CSV | `app/Services/OrderService.php` (`calculateProductSuggestion()` :451 builds `context_data`; `updateOrderItemCases()` :1257 and `updateOrderItemQuantity()` :1207 are the write path; `exportToCsv()` :1314) |
| Low-stock dashboard (separate feature) | `app/Services/OrderManagerService.php`, `OrderManagerController.php`, `/order-manager` |
| Office controller | `app/Http/Controllers/OrderController.php`; routes `orders.*` and `order-items.*` behind `permission:orders.manage` (`routes/web.php:756`) |
| Office review page | `resources/views/orders/show.blade.php` + `partials/review-table.blade.php` (≈4000 lines, inline JS); variants `show-layout-a2*.blade.php`, `grid-view.blade.php`, `show-christmas.blade.php` |
| Feature docs | `docs/features/order-management/order-generation.md`, `order-comparison.md`, `christmas-comparison.md` |
| Existing tests | `tests/Feature/OrderDifferenceCreationTest.php`, `tests/Unit/OrderDifferenceTest.php`, `tests/Unit/SpecialOrderCategoriesTest.php`; nothing yet covers the review endpoints |
| Shop screen (cycles 1–2) | `app/Http/Controllers/Shop/OrderReviewController.php`, `app/Services/Shop/OrderReviewService.php`, `resources/views/shop/orders.blade.php`, `order-review.blade.php`, `resources/js/shop/order-review.js`, shared `product-peek.js` (hover panel, also used by Find product), `SpecialOrderCategories::displayGroups()`, routes `shop.orders*`, permission `orders.review` |
| Design | `docs/design/shop-mode/screen-20-order-review.html` (and `screen-19-new-order.html`, not yet planned), `shop.css` (608 lines since 2026-10-06), `shop-icons.svg` (`download`, `chart` added) |

## Rules every change must respect

1. **Writes go through `OrderService`.** Quantity changes use
   `updateOrderItemCases()` / `updateOrderItemQuantity()` so the learning log
   (`order_adjustments`) and the session totals stay right. Never
   `OrderItem::update()` from a controller.
2. **Only draft sessions are editable** (`OrderSession::isEditable()`).
   Refuse writes on anything else; the Shop returns 409 with
   `{error: 'Order is not editable'}`.
3. **`orders.manage` stays manager-only.** It unlocks delete, complete,
   priorities and generation. Shop-floor access to orders is the narrower
   `orders.review` (cycle 1), shipped as a migration and in the seeder.
4. **Suggestions come from the pre-aggregated sales tables**
   (`sales_daily_summaries` via `SalesRepository`), never live POS sales
   queries; see `docs/features/sales-data-import-plan.md`. The POS database
   is read-only.
5. **Stock on a review row is the generation-time snapshot**
   (`context_data.current_stock`), the number the suggestion was computed
   from. Do not mix live stock into the same row.
6. **`context_data` is the contract between generation and review.** Any
   new key goes into `calculateProductSuggestion()` and gets a default on
   the read side; old sessions keep their old shape.
7. Shop screens: all of `docs/shop_new/README.md` (view contract, verbatim
   design block, PIN allow-list, Alpine traps, sticky bars).
8. Project rules from `CLAUDE.md`: Eloquent models, thin controllers,
   services, tests, `./vendor/bin/pint`.
9. **No commit, push or deploy** unless the plan says so. The owner commits.
   The Planner never edits application code.
10. Every response ends with `mpg123 /home/jon/Music/notification.mp3`.

## Facts that are easy to get wrong

- Shop order review rows: the chart bars are `x-html` strings (`sparkPast` / `sparkFuture` in `order-review.js`), not Alpine-bound elements, and `.shop-ord` has `content-visibility: auto`; groups render 50 rows at a time (`PAGE`). A 1,461-item Udea order is the common case, so keep per-row bindings few.
- Timing in the automation browser: the tab is hidden, so `requestAnimationFrame` and `x-show` wait on throttled timers; measure with microtask turns plus a forced layout and read component state.

- `OrderItem::final_cases` and `suggested_cases` are `decimal:3` casts and
  arrive as strings; `unit_cost` is per **unit** even though the code
  comments wonder whether `PRICEBUY` is per case (`total_cost = final_quantity × unit_cost`).
- `context_data.avg_weekly_sales` excludes the zero weeks before a product's
  first sale, so it is not sum ÷ weeks; use the stored value.
- "Unordered" on the office page is `final_quantity = 0`; there is no
  include/exclude column. Items added from the catalogue search carry
  `added_via_search`.
- `Route::resource('orders')` registers `orders.edit` with no controller
  method; `order-generation.md` says a finished order is "submitted" but the
  code sets `completed`; nothing ever sets `submitted` or `cancelled`.
- Dev data (2026-10-06): 290 draft sessions, 1 completed, 19 with the
  Christmas comparison on. Newest draft #306 (supplier 85, 30 items).

## Open items (owner chooses)

- **Try an Udea draft on the tablet.** First render of a 1,461-item order is about 0.9–1.2 s in the hidden automation tab (four groups × 50-row windows = 150 rows); sorts 0.7–1.5 s there. If it drags on the device, `PAGE` in `order-review.js` to 25 is the first thing to try.
- Phone width (390 px): the five filter pills scroll sideways and the sort select squeezes the search field, both as the design behaves; a stacked tools row would be a small cycle.
- Negative snapshot stock shows as it is (−39 on a muffin); the office has a "reset stock to 0" button, the Shop does not.
- Tidy: `OrderReviewService::rows()` filters product codes with a bare `filter()`, which also drops a code of `"0"`; and the first-name logic is duplicated between the Orders list view and the service.
- Screen 19 New order in Shop mode (creating a session from the tablet).
- The office review partial is ≈4000 lines with inline JS; the clean-up this
  track is named for.
- `order-generation.md` Step 4 and the `submitted` status disagree with the
  code.
- Product photos on Shop order rows (the thumbnail route and `x-shop.product-thumb` exist).

## Folder layout

- `planimp.md` — the protocol
- `README.md` — this file; the Planner updates "Where things stand" when a task is accepted
- `plan.md` — Planner-owned
- `implemented.md` — Implementer-owned report for the current task
- `findings/` — things found after a task is accepted
- `archive/YYYY-MM-DD-<slug>/` — accepted tasks
- `parked/` — plans put aside before implementation
