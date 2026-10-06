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
| Current task | **Cycle 1: Shop order review** (design screen 20). Revision 1 implemented and reviewed 2026-10-06 (all criteria pass; full suite 15 failed / 1019 passed, same five classes). `plan.md` is READY at **Revision 2**: window the rows (a 1,461-item Udea order took 18 s to show all rows), tag pills keep their dot, read-only rows say "Suggested N", `exportToCsv()` skips a product gone from the POS. Kickoff: `Read docs/order_clean/planimp.md. You are the Implementer. Implement docs/order_clean/plan.md (Revision 2, steps 11–14).` |
| HEAD | `36207bbf` on `feature/modularization-phase1` |
| Working tree | cycle 1 uncommitted (see `implemented.md` Files changed) on top of the deposit track's uncommitted cycles 1–3 (parsers, migrations, models, services, `/deposits`, tests) and the glennon parser; none of it touches order code. The Implementer records the baseline `git status --short` so the reviewer can tell the two apart |
| Test baseline | 15 failed / 999 passed, 65 s (2026-10-06). The 15 are the known unrelated set: `UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2, `TestScraperControllerTest` ×1 |

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
| Shop screen (cycle 1) | `app/Http/Controllers/Shop/OrderReviewController.php`, `app/Services/Shop/OrderReviewService.php`, `resources/views/shop/orders.blade.php`, `order-review.blade.php`, `resources/js/shop/order-review.js`, routes `shop.orders*`, permission `orders.review` |
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
