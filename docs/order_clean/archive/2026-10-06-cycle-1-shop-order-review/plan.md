# Shop order review (design screen 20)

Status: ACCEPTED
Revision: 2
Planner: Fable 5.1
Date: 2026-10-06 (Revision 2 the same day, after review)

## Goal

Shop-floor staff can open a draft supplier order on the shared tablet, see
each product's recent weekly sales, stock and projected cover, adjust the
number of cases with a stepper, and export the CSV. It is the Claude Design
screen **20 Order review** ported into the Shop view (`/shop`), reached from
a new Orders tile through a short list of draft orders. The office review
page keeps everything else (priorities, add-by-search, coverage overrides,
completing the order).

## Context

### Design (reference copies saved 2026-10-06, read these first)

- `docs/design/shop-mode/screen-20-order-review.html` — the screen, markup
  verbatim. It is a dc-runtime template: `{{ … }}` are bindings, `<sc-for>`
  repeats, `<sc-if>` conditionals. The script block at the end is the
  behaviour and the maths; the Alpine module in step 6 ports it.
- `docs/design/shop-mode/screen-19-new-order.html` — the screen before it.
  **Out of scope** this cycle; saved so the flow is clear.
- `docs/design/shop-mode/shop.css` — refreshed to 608 lines. The change is
  purely additive to the 553-line version the app carries: a `.shop-select`
  arrow, `.shop-hint`, `.shop-disclosure`, the `.shop-ord*` review row and the
  `.shop-spark*` sales chart (lines 442–493), plus `.shop-ord` column rules at
  the 768 and 1280 breakpoints and `.shop-facts--4` at 1280 (lines 585,
  598–599). `diff docs/design/shop-mode/shop.css <(head -n 553 …)` shows only
  insertions.
- `docs/design/shop-mode/shop-icons.svg` — now the app sprite plus two new
  design symbols, `download` and `chart`, appended last.
- Two design details to port deliberately, not literally:
  - The row carries `data-unordered="…"` but the stylesheet keys on
    `.shop-ord.is-unordered`. Use the class.
  - Selected filter buttons and several spacings are inline `style="…"` in
    the design. Shop views carry no inline styles (rule 1 below); step 6
    names the APP ADDITIONS classes that replace them.

### Office feature the screen sits on

- `App\Models\OrderSession` (`order_sessions`): `supplier_id` (POS
  `SUPPLIERS.SupplierID`), `order_date` (date cast, the delivery day),
  `coverage_ends_on` (date cast), `coverage_days`, `sales_history_weeks`,
  `status` in draft|submitted|completed|cancelled (plain strings, no
  constants), `total_items`, `total_value`, `christmas_comparison_enabled`.
  `isEditable()` is `status === 'draft'` and is the only lock. Relations
  `user()`, `supplier()` (POS `App\Models\Supplier`, column `Supplier` is the
  name), `items()`. `updateTotals()` recounts both totals.
- `App\Models\OrderItem` (`order_items`): `product_id` (POS `PRODUCTS.ID`),
  `suggested_quantity`/`final_quantity` (units), `suggested_cases`/
  `final_cases` (`decimal:3` casts, so strings until cast to float),
  `case_units` (1 for single units), `unit_cost` (per **unit**; `total_cost`
  = `final_quantity × unit_cost`), `review_priority` in safe|standard|review,
  `added_via_search` (bool), `context_data` (array). "Unordered" in the office
  means `final_quantity = 0`; there is no include/exclude column.
- `context_data` keys the screen needs (set by
  `OrderService::calculateProductSuggestion()`, `app/Services/OrderService.php:451`):
  `weekly_sales` (array of `{week_start, week_end, label, units}` oldest
  first, zero-filled, one per week of `sales_history_weeks`),
  `avg_weekly_sales` (leading zero weeks before the product's first sale are
  excluded, so it is not simply sum ÷ weeks), `peak_weekly_sales`,
  `weekly_sales_total`, `current_stock` (POS `STOCKCURRENT.UNITS` at
  generation time), `target_weeks` (what the suggestion aimed for:
  max(coverage weeks, safety factor)), `sales_history_weeks`,
  `is_case_product`. Items added by search get the same keys
  (`addProductToOrder()` calls the same calculation).
- Writes: `OrderService::updateOrderItemCases(OrderItem, float $cases, ?string $reason)`
  (`OrderService.php:1257`) sets `final_cases`, `final_quantity`,
  `total_cost`, logs an `OrderAdjustment` for the learning system when the
  suggestion was non-zero, and calls `updateTotals()`. For `case_units <= 1`
  it delegates to `updateOrderItemQuantity()` with cases treated as units, so
  **one method covers both groups**. It does not check `isEditable()`; the
  office controller does (403). Export: `OrderService::exportToCsv(OrderSession)`
  returns the CSV string; `OrderController::export()` (`:954`) wraps it with
  the filename `order_{Supplier}_{Y-m-d}.csv`.
- Permissions: everything office-side is behind `permission:orders.manage`
  (`routes/web.php:756`), held by manager and admin only. Employees do not
  have it and must not get it (it also unlocks delete, complete, priorities).
- Tags on the office row (`resources/views/orders/partials/review-table.blade.php:631-650`):
  stocked = the product has a `stocking` row (`Product::stocking()`, POS
  table `stocking` keyed by `Barcode` = `PRODUCTS.CODE`); kitchen = a
  `App\Models\KitchenProduct` row (Laravel DB, column `product_id`, model
  pinned to the `mysql` connection).
- Dev data (2026-10-06): 290 draft sessions, 1 completed, 19 with
  `christmas_comparison_enabled`. Newest draft is #306 (supplier 85, 30 items,
  delivery 2026-10-13, lasts until 2026-11-10, 10 weeks of history, €300.02).
  A typical case item there: `case_units` 8, `unit_cost` 1.56, `current_stock`
  4, `avg_weekly_sales` 0.2, `target_weeks` 4.14, suggested 0.

### Shop patterns to copy

- JSON-fed screen: `resources/views/shop/fv-waste.blade.php` +
  `resources/js/shop/fv-waste.js` (URLs as `data-*` on `<main>`, `get`/`post`
  helpers, toast region, `mix()` from `mix.js`). Register the module in
  `resources/js/shop.js` inside `alpine:init` as `Alpine.data('shopOrderReview', orderReview)`.
- List screen: `resources/views/shop/deliveries.blade.php` (`.shop-list` of
  `.shop-row` links with lead icon, title, meta, pill, chevron; empty state).
- Thin controller: `app/Http/Controllers/Shop/FruitVegController.php`,
  `DeliveryController.php`.
- Topbar: `<x-shop-layout title="…" subtitle="…" :back="…">` renders
  `.shop-topbar__titles` with the subtitle in `shop-code` (component
  `resources/views/components/shop/topbar.blade.php`). Leave the component
  alone; the subtitle here is a short sentence and reads fine in that style.
- Home tiles: `config/shop.php` `tiles`; badges resolved in
  `ShopHomeController::badgeCount()`.
- Permission migration template:
  `database/migrations/2026_09_23_150000_add_shop_mode_permissions.php`
  (firstOrCreate the permission, `syncWithoutDetaching` per role, exact
  reversal in `down()`). Seeder: `database/seeders/RolesAndPermissionsSeeder.php`
  permission list (~:296) and `$employeePermissions` (:333).
- Tests: `tests/Feature/Shop/ShopFruitVegTest.php` (employee holding one
  permission, POS tables rebuilt in SQLite), `tests/Concerns/CreatesProductSearchPosTables.php`
  (`createProductSearchPosTables()` builds `PRODUCTS`, `stocking`,
  `supplier_link`, `suppliers`, `STOCKCURRENT`, …),
  `tests/Concerns/AliasesMysqlConnection.php` (needed because
  `KitchenProduct` and `ProductOrderSetting` pin `mysql`),
  `tests/Feature/OrderDifferenceCreationTest.php:34-60` (building a session
  and items by hand). Permission tests to extend:
  `tests/Feature/Shop/RolePermissionGrantsTest.php`,
  `tests/Feature/Shop/RoutePermissionsTest.php` (matrix rows),
  `tests/Feature/Shop/ShopHomeTest.php` (tiles). The PIN allow-list drift test
  (`ConfinePinSessionTest::test_every_route_a_shop_view_names_is_on_the_allow_list`)
  and the view contract scan pick the new screen up automatically.
- Sprite: `public/images/shop-icons.svg`; `<x-shop.icon name="…" />`.

### Decisions recorded (the owner may overturn any of these before kickoff)

1. **New permission `orders.review`**, granted to employee, manager and admin,
   gates every Shop order route. The design's draft is "by Jessika", a
   shop-floor user, and `orders.manage` is far too broad to hand out. The
   office routes keep `orders.manage` unchanged.
2. **Entry point**: a Home tile "Orders" opens `/shop/orders`, a list of the
   20 newest draft sessions plus the 5 most recently completed, each row
   opening the review. Screen 19 (creating an order from the Shop) is a later
   cycle; the list is what the review needs to be reachable at all.
3. **Completed sessions open read-only** (steppers disabled, no reset, export
   still allowed). Sessions with `christmas_comparison_enabled` open as a
   plain review; the Christmas columns are an office-only view.
4. **Tags**: `Destocked` when the product has no `stocking` row, `Kitchen`
   when it is a kitchen product. The design's sample "Refill" tag is sample
   data, not a rule.
5. **Cover pill when there are no sales**: the design yields "Plenty of
   stock" for an item with zero average sales (cover = 99). Show a muted
   `No recent sales` pill instead; a product that never sells is not
   "plenty".
6. **Stock shown is the generation-time snapshot** (`context_data.current_stock`),
   the same number the suggestion was computed from and the number the office
   row shows. No live POS stock query per row.
7. **Saves are per tap, debounced 400 ms per item**, optimistic, reverted
   with a toast on failure. Reason text sent: none (the office sends none).
8. **No badge on the Orders tile**: dev has 290 drafts and production's count
   is unknown; a big red number on Home would mean nothing.

## Constraints

- Shop rules in `docs/shop_new/README.md` apply in full. In particular:
  1. **View contract**: views under `resources/views/shop/**` use only
     `x-shop.*` components and `shop-*` classes, no `<style>`/`<script>`,
     no inline `style="…"` except Alpine `:style` bindings for computed
     geometry (bar heights, the average line).
  2. **Design block is verbatim**: `resources/css/shop.css` starts with the
     608-line `docs/design/shop-mode/shop.css` byte for byte; app rules only
     between `/* === APP ADDITIONS START === */` and `END`.
  3. **PIN confinement**: every route a Shop view names must match
     `config('shop.pin_session_routes')`. All new routes are `shop.*`, which
     is already listed; do not add office `orders.*` routes to the list.
  4. **New permissions ship as a migration**, plus the seeder for fresh
     installs; never seed over production.
  5. Alpine traps: no `@error`/`@class` shorthands; `?.` behind `x-show`;
     `mix()` not spread.
  7. A sticky `.shop-actions` is a direct child of `<main>`.
- Business rules: writes go through `OrderService::updateOrderItemCases()`
  only (learning log + totals); no direct `OrderItem::update()`. The POS
  database is read-only. Non-draft sessions refuse writes with HTTP 409 and
  `{error: 'Order is not editable'}`.
- Office pages, routes, permissions and the office review JS are not
  changed.
- `CLAUDE.md`: Eloquent models, thin controllers, a service for the
  presentation logic, tests, `./vendor/bin/pint`.
- Do not commit, push or deploy.

## Out of scope

- Screen 19 New order (creating or regenerating a session from the Shop).
- Changing priorities, adding products by search, destock/kitchen toggles,
  the min-stock editor, per-category coverage overrides, auto-approve,
  completing or deleting an order, pallet fill, product photos on rows.
- Any change to the office review page, its partials or its endpoints.
- The Christmas comparison view in Shop mode.
- The topbar component (subtitle styling).

## Steps

### 1. Design assets

Files: `resources/css/shop.css`, `public/images/shop-icons.svg`

What: replace the design block of `resources/css/shop.css` (everything
before `/* === APP ADDITIONS START === */`) with the full
`docs/design/shop-mode/shop.css`; leave the APP ADDITIONS block as it is.
Replace `public/images/shop-icons.svg` with `docs/design/shop-mode/shop-icons.svg`
(identical content plus `download` and `chart` appended; the app additions
`more`, `phone`, `info`, `help` are already in that copy).

Check:
```
head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo css-ok
cmp public/images/shop-icons.svg docs/design/shop-mode/shop-icons.svg && echo sprite-ok
grep -c "APP ADDITIONS" resources/css/shop.css   # 2
npm run build 2>&1 | tail -3                     # builds clean
```

### 2. Permission `orders.review`

Files: `database/migrations/2026_10_06_000001_add_orders_review_permission.php (new)`,
`database/seeders/RolesAndPermissionsSeeder.php`,
`tests/Feature/Shop/RolePermissionGrantsTest.php`

What: a migration in the shape of `2026_09_23_150000_add_shop_mode_permissions.php`
creating one permission
(`name` `orders.review`, `display_name` "Review supplier orders in Shop mode",
`description` "Can open draft supplier orders in Shop mode, adjust quantities
and export the CSV", `module` "Ordering") and granting it to the `admin`,
`manager` and `employee` roles with `syncWithoutDetaching`; `down()` detaches
and deletes exactly that permission. Seeder: add the same entry to the
permission definitions and `'orders.review'` to `$employeePermissions`
(managers inherit it through the merge). Tests: in
`RolePermissionGrantsTest` assert the seeder grants `orders.review` to the
employee and the migration (run the new one the way the test already runs
the 2026-09-23 one) does too, and that `orders.manage` is still withheld
from employees.

Check: `php artisan migrate` runs clean;
`php artisan tinker --execute='echo App\Models\Role::where("name","employee")->first()->hasPermission("orders.review") ? "yes" : "no";'`
prints `yes`; `php artisan test tests/Feature/Shop/RolePermissionGrantsTest.php` passes.

### 3. `OrderReviewService`

Files: `app/Services/Shop/OrderReviewService.php (new)`

What: the presentation logic, so the controller stays thin and the maths is
testable. Public methods:

- `listing(): array{drafts: Collection, completed: Collection}` — sessions
  `with('supplier', 'user')->withCount('items')`, drafts newest first
  (`latest('created_at')`), 20 at most; completed newest first, 5 at most.
- `header(OrderSession $order): array` with keys:
  `id`, `supplier` (name or "Unknown supplier"), `delivery_date`
  (`order_date->format('D j M')`), `created_by` (first word of the user's
  name, or "unknown"), `status` (`ucfirst`), `editable` (`isEditable()`),
  `lasts_until` (`coverage_ends_on` as `D j M`, or `null`),
  `weeks_after_delivery` (days between `order_date` and `coverage_ends_on` ÷ 7,
  one decimal, `null` without an end date), `history_weeks`
  (`sales_history_weeks`), `total_value` (float), `ordered_count` (items
  with `final_quantity > 0`), `item_count`, `export_url`
  (`route('shop.orders.export', $order)`).
- `rows(OrderSession $order): array` — one array per item, items eager
  loaded with `product.stocking`; kitchen ids from
  `KitchenProduct::pluck('product_id')` once. Keys:
  `id`, `url` (`route('shop.orders.item', [$order, $item])`), `name`
  (`product->NAME`, or "Unknown product" when the POS row is gone), `code`
  (`product->CODE`, else `product_id`), `group` (`'case'` when
  `case_units > 1`, else `'unit'`), `case_units` (int), `unit_cost` (float),
  `priority` (`'added'` when `added_via_search`, else `review_priority`),
  `tags` (array: `Destocked` when a product exists without a `stocking` row;
  `Kitchen` when its id is in the kitchen set), `stock` (float
  `context_data.current_stock`, default 0), `suggested_cases` and
  `final_cases` (floats, for unit products these are the unit counts),
  `total_cost` (float), `weekly_sales` (plain list of floats from
  `context_data.weekly_sales[*].units`, oldest first; `[]` if missing),
  `avg_weekly` (`context_data.avg_weekly_sales`, default 0), `peak_weekly`,
  `sold` (`context_data.weekly_sales_total`, else the sum), `target_weeks`
  (`context_data.target_weeks`, else `coverage_days / 7`, else 1).
- `row(OrderSession $order, OrderItem $item, array $kitchenIds): array` —
  the single-row builder `rows()` uses, so `update()` can return one row.
- `update(OrderSession $order, OrderItem $item, int $cases): array` —
  throws `\DomainException('Order is not editable')` unless
  `$order->isEditable()`; otherwise `OrderService::updateOrderItemCases($item, $cases)`
  and returns `['item' => row(...), 'order' => header($order->fresh())]`.
  Inject `OrderService` through the constructor.

Check: a feature test in step 8 covers it; meanwhile
`php artisan tinker --execute='$s=app(App\Services\Shop\OrderReviewService::class); $o=App\Models\OrderSession::find(306); echo json_encode($s->header($o)), PHP_EOL, json_encode($s->rows($o)[0]);'`
prints a header with `supplier`, `lasts_until "Tue 10 Nov"`, `history_weeks 10`
and a row with a 10-element `weekly_sales` list.

### 4. Controller and routes

Files: `app/Http/Controllers/Shop/OrderReviewController.php (new)`, `routes/web.php`

What: in the authenticated `shop.` group (`routes/web.php` ~:101, next to
the F&V group) add

```php
Route::middleware('permission:orders.review')->group(function () {
    Route::get('/orders', [OrderReviewController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [OrderReviewController::class, 'show'])->name('orders.review');
    Route::get('/orders/{order}/items', [OrderReviewController::class, 'items'])->name('orders.items');
    Route::patch('/orders/{order}/items/{item}', [OrderReviewController::class, 'updateItem'])->name('orders.item')->scopeBindings();
    Route::get('/orders/{order}/export', [OrderReviewController::class, 'export'])->name('orders.export');
});
```

`{order}` is an `OrderSession`, `{item}` an `OrderItem`; `scopeBindings()`
resolves the item through `OrderSession::items()`, so an item from another
order is a 404. Controller methods:

- `index()` → `view('shop.orders', $service->listing())`.
- `show(OrderSession $order)` → `view('shop.order-review', ['order' => $order, 'header' => $service->header($order)])`.
- `items(OrderSession $order)` → JSON `['order' => header, 'items' => rows]`.
- `updateItem(Request, OrderSession $order, OrderItem $item)` → validate
  `cases` as `required|integer|min:0|max:9999`; call `update()`; catch
  `\DomainException` → `response()->json(['error' => $e->getMessage()], 409)`;
  success → JSON `['success' => true, 'item' => …, 'order' => …]`.
- `export(OrderSession $order)` → the same response as
  `OrderController::export()` (CSV body from `OrderService::exportToCsv()`,
  `Content-Type: text/csv`, filename `order_{Supplier}_{Y-m-d}.csv`).

Check: `php artisan route:list --name=shop.orders` lists the five routes
under the `permission:orders.review` middleware; as the dev employee
`katelyn` (holds `orders.review` after step 2) `GET /shop/orders/306/items`
returns 200 JSON; the manager `test` gets the same; a user without the
permission gets 403.

### 5. Orders list view

Files: `resources/views/shop/orders.blade.php (new)`

What: `<x-shop-layout title="Orders" :back="route('shop.home')">` with a
`shop-page shop-page--narrow` main. Section "Draft orders" (`shop-label`,
count in `shop-meta`): a `.shop-list` of `<a class="shop-row" href="{{ route('shop.orders.review', $session) }}">`
rows copied from the deliveries list: lead icon `chart`, title = supplier
name, meta line 1 = `Delivery {order_date D j M} · {user first name}`, meta
line 2 = `{items_count} products · €{total_value}`, aside pill
`shop-pill--sage` "Draft", chevron. Empty state with the `inbox` icon:
"No draft orders" / "Orders are generated in the office; drafts appear
here." If there are more drafts than shown, a `shop-meta` line "Showing the
20 most recent". Section "Recently completed": the same rows with class
`shop-row is-off`, pill `shop-pill--ok` "Completed", still links to the
review (read-only).

Check: a rendering test in step 8; `GET /shop/orders` as `katelyn` shows
the newest draft first and the contract scan (`ShopViewContractTest`) passes.

### 6. Review view, Alpine module, CSS additions

Files: `resources/views/shop/order-review.blade.php (new)`,
`resources/js/shop/order-review.js (new)`, `resources/js/shop.js`,
`resources/css/shop.css` (APP ADDITIONS only)

What — view: `<x-shop-layout :title="'Order · '.$header['supplier']" :subtitle="'Delivery '.$header['delivery_date'].' · '.$header['status'].' by '.$header['created_by']" :back="route('shop.orders')">`,
then `<main class="shop-page" x-data="shopOrderReview()" data-items-url="{{ route('shop.orders.items', $order) }}">`
holding, in the design's order:

1. `shop-facts shop-facts--4`: Order value (`x-text="eur(totalValue)"`),
   Products ordered (`orderedCount of allCount`), Lasts until
   (`order.lasts_until ?? '—'`, hint `{weeks_after_delivery} weeks after delivery`
   with `x-show` on the number), Based on (`{history_weeks} weeks`, hint "of sales").
2. Controls block (`shop-stack shop-ord-controls`): a `shop-between` row
   with the `.shop-seg` of five `<button class="shop-seg__opt" type="button" :aria-pressed="filter === f.key" @click="filter = f.key">`
   (label `x-text="f.label + ' ' + f.count"`), and the switch
   `<label class="shop-switch"><input type="checkbox" x-model="showUnordered"><span class="shop-switch__track"></span><span class="shop-meta shop-switch__label">Show not ordered</span></label>`;
   then a `shop-ord-tools` row with the `.shop-search` (icon `search`,
   `<input class="shop-input" type="search" placeholder="Find in this order" x-model="q">`)
   and the `<select class="shop-input shop-select" aria-label="Sort" x-model="sort">`
   with Best sellers / Lowest cover / A–Z.
3. `<template x-for="g in groups" :key="g.key">` → `section.shop-stack.shop-stack--tight`
   with `h2.shop-group-title` (title + `<small>` count) and a `.shop-list`
   of `<article class="shop-ord" :class="{ 'is-unordered': isUnordered(it) }">`
   per item, the four blocks exactly as the design:
   - `shop-ord__product`: `h3.shop-row__title` name, `span.shop-row__meta`
     with `span.shop-code` code · pack label, `shop-ord__tags` with the
     priority pill (`shop-pill shop-pill--{bad|warn|ok|sage}` for
     review|standard|safe|added, words Review|Standard|Safe|Added) and one
     `shop-pill shop-pill--muted shop-pill--plain` per tag.
   - `shop-spark`: head (`<strong>sold</strong> sold`, key `avg {avg}/wk`,
     `peak {peak}`, proj key "stock"), plot with `role="img"` and an
     `:aria-label` sentence, past bars (`<template x-for="(v, i) in it.weekly_sales" :key="i">`
     `span.shop-spark__bar` with `is-peak` when `v === peak && v > 0`,
     `:style="'height:' + barHeight(it, v) + '%'"`), the average line
     (`:style="'bottom:' + avgBottom(it) + '%'"`), future bars from
     `projection(it)` (`is-proj`, `is-low` below the average, `:title`),
     and the axis (`{history_weeks} wk ago` / `last wk` / `after delivery`).
   - `shop-ord__stock`: In stock (`shop-ord__num`, `is-zero` at 0), After
     delivery, the cover pill.
   - `shop-ord__order`: stepper (`shop-iconbtn` minus / `output.shop-ord__qty`
     with `is-case`/`is-unit` and `is-changed`, cases number and
     `<small>` word / `shop-iconbtn` plus), buttons
     `:disabled="! order.editable || it.busy"`, `aria-label` "One case less"
     / "One case more" ("One less"/"One more" for unit products); the line
     with units label · `<strong>` cost, and either the reset button
     (`shop-ord__reset`, icon `history`, "Suggested N", `x-show="isChanged(it) && order.editable"`)
     or the "Suggested" word.
4. Empty card (`shop-card > shop-empty`, icon `search`, "Nothing matches",
   "Try another filter, or turn on “Show not ordered”.") with
   `x-show="! loading && ! groups.length"`, and a `shop-meta` "Loading
   order…" with `x-show="loading"`.
5. `.shop-actions` as a direct child of `<main>`: the Total block
   (`shop-actions__spacer shop-ord-total`: `shop-label` "Total" + the value)
   and `<a class="shop-btn shop-btn--primary shop-btn--lg" href="{{ route('shop.orders.export', $order) }}">`
   with icon `download`, "Export CSV".
6. The toast region as in `fv-waste.blade.php`.

What — module `order-review.js`, default export composed with `mix()` as the
others, ported from the design's script:

- State: `order` (header, starts `{ editable: false }`), `items`, `filter`
  `'all'`, `showUnordered` `false`, `sort` `'sales'`, `q` `''`, `loading`,
  `toast`. `init()` loads `itemsUrl`; each item gets `busy: false`,
  `savedCases: final_cases`, `timer: null`.
- Per-item maths (functions taking `it`): `units = final_cases × case_units`,
  `after = stock + units`, `cover = avg_weekly > 0 ? after / avg_weekly : null`,
  `coverState`: avg ≤ 0 → `['muted', 'No recent sales']`; cover < 1 →
  `['bad', 'Runs out within a week']`; cover < target_weeks × 0.85 →
  `['warn', 'Lasts {fmt} wk · below target']`; else `['ok', cover > 20 ? 'Plenty of stock' : 'Lasts {fmt} wk']`.
  `isChanged = final_cases !== suggested_cases`,
  `isUnordered = suggested_cases === 0 && final_cases === 0`, `packLabel`,
  `caseWord`, `unitsLabel` (`{units} units` for cases, `€{unit_cost} each`
  for units), `cost = units × unit_cost`. Chart: `top = max(peak, after, 1)`,
  `barHeight = max(4, v / top × 100)`, `avgBottom = avg / top × 100`,
  `projection = [0,1,2,3].map(w => max(0, after − avg × w))` with titles
  "Delivery: about N in stock" / "Week w: about N in stock". `fmt` rounds to
  one decimal; `eur` is `'€' + n.toFixed(2)`.
- Derived lists: `visible` (showUnordered or not unordered), then the
  priority filter, then the text filter (name or code contains `q`,
  case-insensitive), sorted by `sales` (sold desc), `cover` (asc, nulls
  last) or `name` (`localeCompare`); `groups` = Case products / Single units,
  empty ones dropped; `filters` = All/Review/Standard/Safe/Added with counts
  taken over `visible`; `totalValue` = Σ cost over all items,
  `orderedCount` = items with `final_cases > 0`, `allCount` = items.
- Actions: `inc(it)`, `dec(it)`, `reset(it)` → `setCases(it, n)`: clamp at
  0, set `it.final_cases = n`, then debounce 400 ms per item and PATCH
  `it.url` with `{cases: n}` (`X-CSRF-TOKEN`, `Accept: application/json`).
  Success: copy `final_cases`, `total_cost`, `savedCases` from the response
  item and `order` from the response header. Failure (non-2xx or network):
  revert `final_cases` to `savedCases` and toast `bad`
  "Could not save {name}" (409 → "This order is no longer editable" and set
  `order.editable = false`).
- Register in `shop.js`: `import orderReview from './shop/order-review';`
  and `Alpine.data('shopOrderReview', orderReview);`.

What — CSS, appended inside APP ADDITIONS with a comment naming this cycle:

```css
/* Order review (order_clean cycle 1): the design's inline styles as classes. */
.shop-seg__opt[aria-pressed="true"] { background: var(--shop-surface); color: var(--shop-ink); box-shadow: var(--shop-shadow-sm); }
.shop-ord-controls { gap: 12px; }
.shop-ord-tools { display: flex; align-items: center; gap: var(--shop-space-3); }
.shop-ord-tools > .shop-search { flex: 1; min-width: 0; }
.shop-ord-tools > .shop-select { width: auto; flex: none; }
.shop-switch__label { font-weight: var(--shop-fw-bold); }
.shop-pill--plain { padding-left: 12px; }
.shop-pill--plain::before { display: none; }
.shop-ord__stock > .shop-stack { gap: 4px; }
.shop-ord-total { gap: 0; flex: 1; min-width: 0; }
.shop-ord-total__value { font-size: var(--shop-fs-xl); font-weight: var(--shop-fw-heavy); line-height: 1.1; font-variant-numeric: tabular-nums; }
.shop-actions > .shop-btn--export { flex: 0 0 auto; }
```

(Adjust names if the Implementer finds a cleaner fit; every rule stays
under the marker and under `.shop`.)

Check: `npm run build` clean; `php artisan test tests/Feature/Shop/ShopViewContractTest.php`
passes; `grep -c 'style="' resources/views/shop/order-review.blade.php` is 0;
`grep -n "@error\|@class" resources/views/shop/order-review.blade.php` is
empty; in the browser as `katelyn`, `/shop/orders/306` loads 30 rows, tapping
"+" on a case product saves (network tab shows PATCH 200, the output and
cost change, the orange `is-changed` ring appears and "Suggested N"
reset button shows), tapping it resets; the three sorts and five filters
change the list; the Total in the bar matches the Order value fact; Export
CSV downloads `order_{Supplier}_2026-10-13.csv`.

### 7. Home tile

Files: `config/shop.php`, `tests/Feature/Shop/ShopHomeTest.php`

What: add after the `labels` tile:
`['key' => 'orders', 'label' => 'Orders', 'hint' => 'Review supplier orders', 'icon' => 'chart', 'route' => 'shop.orders', 'permissions' => ['orders.review'], 'badge' => null]`.
No badge (decision 8). Extend `test_employee_sees_only_the_tiles_they_may_use`
(or add a sibling) so a user holding `orders.review` sees "Orders" and one
without does not.

Check: `php artisan test tests/Feature/Shop/ShopHomeTest.php` passes;
`/shop` as `katelyn` shows the Orders tile with the chart icon.

### 8. Tests

Files: `tests/Feature/Shop/ShopOrderReviewTest.php (new)`,
`tests/Feature/Shop/RoutePermissionsTest.php`

What: `ShopOrderReviewTest` uses `RefreshDatabase`,
`CreatesProductSearchPosTables` (POS tables in SQLite) and
`AliasesMysqlConnection` (for `KitchenProduct`). Fixture: a supplier `S1`,
products `P1` (case product, `supplier_link.CaseUnits` 6, has a `stocking`
row) and `P2` (single unit, no `stocking` row, a `KitchenProduct` row), a
draft session for `S1` (order_date 2026-10-13, coverage_ends_on 2026-11-10,
sales_history_weeks 8) with two items built like
`OrderDifferenceCreationTest::orderSession()` but with a realistic
`context_data` (`weekly_sales` of 8 `{units}` entries, `avg_weekly_sales`,
`peak_weekly_sales`, `weekly_sales_total`, `current_stock`, `target_weeks`),
`P2` with `added_via_search` true; and a completed session. Users: an
employee holding only `orders.review`, one holding nothing. Tests:

- permission: the bare employee gets 403 on the list, the review, items and
  export; the `orders.review` employee gets 200 on all four.
- list: shows the draft's supplier name and "Draft", the completed one under
  "Recently completed".
- items JSON: `order.lasts_until` "Tue 10 Nov", `order.weeks_after_delivery`
  4.0, `order.history_weeks` 8, `order.editable` true; `P1` row has `group`
  `case`, `case_units` 6, `tags` `[]`, `weekly_sales` of 8 floats; `P2` row
  has `group` `unit`, `priority` `added`, `tags` `['Destocked', 'Kitchen']`.
- items JSON for a product deleted from the POS: name "Unknown product",
  code = product_id, no exception.
- PATCH cases on `P1` with `{cases: 3}`: 200, `final_cases` 3,
  `final_quantity` 18, `total_cost` 18 × unit_cost, the session's
  `total_value` updated, an `OrderAdjustment` row written (suggested was
  non-zero); on `P2` `{cases: 2}` → `final_quantity` 2.
- PATCH validation: `{cases: -1}` and `{cases: 'x'}` → 422; `{cases: 10000}`
  → 422.
- PATCH on the completed session → 409 with `error` "Order is not editable"
  and nothing changed.
- PATCH with an item id from another session → 404.
- export: 200, `Content-Type` starts with `text/csv`, disposition filename
  `order_…_2026-10-13.csv`, body starts with the `Code,Cases,Units,` header.
- view contract: nothing to add; the scan covers the two new views.

`RoutePermissionsTest` matrix: add `['employee', 'get', '/shop/orders', 'open']`
(the employee factory role gets `orders.review` from the migration) and
`['barista', 'get', '/shop/orders', '403']` if the matrix has a barista
user; otherwise a plain assertion in `ShopOrderReviewTest`.

Check: `php artisan test tests/Feature/Shop` passes (all files, not only the
new one).

### 9. Docs and formatting

Files: `docs/features/shop-mode.md`, `docs/features/order-management/order-generation.md`,
`docs/FEATURES_INDEX.md`, `config/shop.php` docblock if touched

What: `shop-mode.md`: add the Orders list and Order review to the screens
list and a short "Order review" subsection: what it shows, that stock is
the generation-time snapshot, the `orders.review` permission, what stays
office-only, the 409 on completed orders. `order-generation.md`: a dated
"2026-10 Shop mode review" entry under the enhancements and one line in
Operational Notes naming `/shop/orders`. `FEATURES_INDEX.md`: mention the
Shop review under Order Management. Then `./vendor/bin/pint` on the new
and touched PHP files.

Check: `./vendor/bin/pint --test` clean on the changed files;
`grep -n "orders.review" docs/features/shop-mode.md` finds the section.

### 10. Browser walkthrough

What: as `katelyn` (PIN 2580, trusted device or password session) on
`http://osmanager.local/shop/orders`: open the newest draft; exercise the
actions, not just the render: tap +, −, reset on a case row and a unit row
and watch the network tab and console (no errors); switch each filter, each
sort, the "Show not ordered" switch, type in the search; confirm the empty
card appears for a nonsense search; Export CSV. Then at phone width (a
same-origin iframe sized 390 × 844, per `docs/shop_new/README.md`): the row
stacks into one column, the stepper is reachable, the sticky bar sits at
the bottom. Open a completed session: steppers disabled, no reset button,
export works. Put dev data back: reset any changed item to its suggested
value and list what was touched under "Dev state".

Check: all of the above recorded in `implemented.md` with the real PATCH
responses and any console output.

## Verification

Run in order, record the output:

1. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo css-ok`
2. `cmp public/images/shop-icons.svg docs/design/shop-mode/shop-icons.svg && echo sprite-ok`
3. `npm run build 2>&1 | tail -3` → no errors.
4. `php artisan route:list --name=shop.orders` → five routes, all with
   `permission:orders.review`.
5. `php artisan test tests/Feature/Shop` → all pass.
6. `php artisan test` → **15 failed, the same five classes as the baseline**
   (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3,
   `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2,
   `TestScraperControllerTest` ×1; baseline 2026-10-06: 15 failed / 999
   passed, 65 s). Any other failure is yours.
7. `./vendor/bin/pint --test` on the changed PHP files → no issues.
8. `grep -c 'style="' resources/views/shop/order-review.blade.php resources/views/shop/orders.blade.php` → `0` for both.
9. The browser walkthrough of step 10, written up.

## Risks

- **Decimal casts**: `final_cases`/`suggested_cases` come out of Eloquent
  as strings (`decimal:3`). Cast to float in the service or the JS
  comparison `final_cases !== suggested_cases` is always true and every row
  shows the orange ring.
- **Weekly sales missing**: sessions generated before the weekly series
  existed, or items with `context_data` from an older shape, may have no
  `weekly_sales`. The row must render with an empty chart, not throw
  (`?? []`, bars from an empty list, `avg` 0 → "No recent sales").
- **Product gone from the POS**: `items.product` can be null. Guard every
  product read.
- **Large sessions**: a draft can hold a few hundred items; the Alpine
  template renders them all. If a 300-row session is sluggish on the
  tablet, note it; a later cycle can window the list. Do not paginate now.
- **Scoped bindings**: `scopeBindings()` needs the relation name to match
  the parameter (`items` for `{item}`); a wrong name gives a 404 on every
  PATCH. Test it (step 8).
- **Sticky bar**: the bar must be a direct child of `<main>`; wrapping the
  page in a `div` for Alpine moves it to the end of the page (rule 7).
- **Stale drafts on the list**: 290 drafts in dev; the list shows 20. If the
  owner wants a different window, it is one constant in `listing()`.
- **Rapid taps**: the per-item debounce means the last value wins; two taps
  within 400 ms send one PATCH. A reply arriving after a newer local change
  must not overwrite it: only copy the server's `final_cases` when it equals
  the value that was sent.

## Review (Revision 1, 2026-10-06)

Read `implemented.md` to the end, read every new file and the diff of every
touched one, reran the checks, and walked the screen in the browser under the
employee session (`katelyn`) alongside the Implementer's own walkthrough. The
work is good; one real problem (large orders render slowly) and three small
things go into Revision 2 below. **Status stays READY at Revision 2** for
those; acceptance follows the next report.

### Criteria

| Step | Result |
|---|---|
| 1 Design assets | pass: `css-ok`, `sprite-ok`, two `APP ADDITIONS` markers, build clean |
| 2 Permission | pass: migration is the planned shape and reverses itself; seeder updated; `RolePermissionGrantsTest` covers seeder, migration, idempotence and `down()` |
| 3 Service | pass: all keys as specified, floats everywhere, missing product and old `context_data` guarded; `target_weeks` fallback right |
| 4 Controller, routes | pass: five `shop.orders*` routes under `permission:orders.review`, `scopeBindings()` gives a 404 for a foreign item (tested), 409 on a non-draft (tested), export identical to the office response |
| 5 List | pass: newest draft first, 290-draft count, "Showing the 20 most recent", completed rows link read-only |
| 6 Review screen | pass: markup follows the design block by block; no inline styles beyond the three `:style` geometry bindings; `.is-unordered` class used; APP ADDITIONS as planned |
| 7 Tile | pass, with a test |
| 8 Tests | pass: 12 new tests cover permission, list, JSON shape, missing product, PATCH paths, validation, 409, 404, export; matrix rows added; `tests/Feature/Shop` 313 passed |
| 9 Docs, pint | pass |
| 10 Walkthrough | pass, see below |
| Verification | css-ok, sprite-ok, build clean, routes listed, Shop suite 313 passed, full suite **15 failed / 1019 passed** with the same five classes as the baseline, pint clean, no inline `style=` in either view |

### Planner's own browser check (katelyn, employee, 2560 px and a 390 × 844 iframe)

- Draft 306 loads 30 items (11 shown), 10 bars and 4 projection bars per row,
  no console errors, items JSON 200.
- Real tap on + (B Muffin Apple & Cinnamon): one `PATCH …/items/177715` 200,
  3 → 4 cases, €27.36 → €36.48, Order value and Total both €309.14, orange
  ring and "Suggested 3" reset shown; the DB agreed (final 4 / 32 / 36.48,
  session 309.14, one `order_adjustments` row). Real tap on reset: PATCH 200,
  back to 3 and €300.02, no second adjustment row (final = suggested).
- "Show not ordered" (real click): 30 rows, 19 carry `.is-unordered`; filters
  count 30 / 0 / 27 / 3 / 0; "Safe" (real click) shows the three safe rows
  with `aria-pressed` styling. Sorts: Lowest cover puts the negative-stock
  muffins first (cover −10.6, −3.3), A–Z and Best sellers correct. Search
  "muffin" → 2 rows; nonsense → the "Nothing matches" card.
- Export fetched in page: 200, CSV header and 11 product lines.
- Phone iframe: one-column rows, two-column facts, no horizontal page
  scroll, 56 px stepper buttons, sticky bar flush with the bottom.
- Completed session 20 (Udea, 1,461 items): read-only, every stepper button
  disabled, no reset, export link present.
- **Dev state put back**: item 177715 at 3 cases, session 306 at €300.02;
  I deleted `order_adjustments` 9765 (my + tap; the Implementer had already
  removed its own 9763 and 9764). No adjustment rows by user 3 remain today.

### The problem: big orders are slow to render

Measured on session 20 (1,461 items, the shape of every Udea order):

| | |
|---|---|
| items JSON | 566 KB, 585 ms |
| first render (329 ordered rows) | about 3.3 s including the fetch |
| re-sort of those 329 rows | 0.7 s |
| "Show not ordered" (all 1,461 rows) | **18 s** |
| search re-render with all rows shown | 1.4 s |

That is on a desktop Chrome; the tablet is slower. Udea is the main
supplier, so this is the common case, not an edge. Each row carries about
forty bound expressions plus fourteen bars, and Alpine builds them all.
Step 11 windows the list.

### Deviations

1. `listing()` returns `draft_total` — accepted, the view needs it.
2. "Lasts until" and "Based on" rendered server-side — accepted; they never
   change on the page.
3. "−" disabled at 0 — accepted, better than the plan.
4. `shop-stack` kept on the Total block, `shop-btn--export` on the link —
   accepted; the plan's rule was incomplete without it.
5. `grep 'style="'` → 3 — accepted; those are the `:style` bindings the plan
   itself asks for. The verification line was imprecise; Revision 2 uses
   `grep -c '[^:]style="'`.
6. Two migration tests with a delete-first — accepted, same approach as the
   file's existing helper.

### Notes for Planner

- Office CSV export crashes on a product gone from the POS — **fixed now**,
  step 14. The Shop export shares that path, so it is this cycle's problem
  too. (No such item exists in the 50 newest dev drafts; the test builds one.)
- Tag pills hide their dot — **fixed now**, step 12: the design keeps the
  dot, so drop the `shop-pill--plain` rules.
- `weeks_after_delivery` as `4` not `4.0` in JSON — no action; the view
  formats it server-side and the test uses `assertEquals`.
- Busy flag while a save is in flight — accepted as implemented; it closes
  the race the plan's risk described.
- First-name logic duplicated in the view and the service — **deferred**: a
  `first_name` accessor on `User` is a tidy-up for a later cycle, not worth a
  revision here.

Also noted by me, **deferred for the owner to judge on the device**: at
390 px the sort select (186 px) is wider than the search field (141 px);
if that reads badly on the phone, the select could shrink to an icon or
drop under the search. And the stock snapshot shows negative POS stock as
it is (−39 on a muffin); the office row has a "reset stock to 0" button,
which is out of scope here, but the number is honest.

## Revision 2 — steps 11 to 14

Everything above stands. Four more steps, then rerun the Verification
section (with item 8 read as `grep -c '[^:]style="' … → 0`).

### 11. Window the rows

Files: `resources/js/shop/order-review.js`, `resources/views/shop/order-review.blade.php`,
`resources/css/shop.css` (APP ADDITIONS only, if anything is needed)

What: each group renders at most `PAGE` rows (constant, 50) and, when it
holds more, a `<button class="shop-btn shop-btn--secondary shop-btn--block" type="button">`
under its `.shop-list` reading "Show 50 more · N left" (`x-text`), which
raises that group's window by `PAGE`. The group title keeps the full count.
Any change to `filter`, `showUnordered`, `sort` or `q` resets both windows
to `PAGE` (an `x-effect` or watchers on those four, or reset inside the
setters; pick one and say which). The `groups` getter returns the full
sorted lists; the template iterates `g.items.slice(0, window[g.key])` or a
`shown(g)` helper, so totals, filter counts and the empty state are
unchanged. The empty state must still key on the full list, not the window.

Check: on `/shop/orders/20` read, as the Planner did, with
`Alpine.$data(document.querySelector('main'))` and two
`requestAnimationFrame`s: first render well under 1 s after the JSON
arrives; "Show not ordered" and a re-sort each under 300 ms; tapping "Show
50 more" appends 50 rows and the button's remainder drops by 50; a filter
change resets the window. Record the numbers in the report. On session 306
(30 items) no button appears.

### 12. Tag pills keep their dot

Files: `resources/css/shop.css` (APP ADDITIONS), `resources/views/shop/order-review.blade.php`

What: delete the two `.shop-pill--plain` rules and the `shop-pill--plain`
class on the tag pill; the tag pill is `shop-pill shop-pill--muted` as the
design renders it (its inline `padding-left:12px` is the pill's default).

Check: `grep -c "shop-pill--plain" resources/css/shop.css resources/views/shop/order-review.blade.php` → 0 and 0; `css-ok` still holds.

### 13. Read-only rows still say what was suggested

Files: `resources/views/shop/order-review.blade.php`

What: on a non-editable order a changed row currently shows neither the
reset button nor the word "Suggested". Replace the trailing span with one
that shows when the reset button does not:
`<span x-show="! (isChanged(it) && order.editable)" x-text="isChanged(it) ? 'Suggested ' + fmt(it.suggested_cases) : 'Suggested'"></span>`.
The reset button's `x-show` is unchanged.

Check: `ShopViewContractTest` passes; in the browser on session 20 a row
whose final differs from suggested reads "Suggested N" with no button (if
none exists on 20, set one in the component state and read the line).

### 14. Export survives a product gone from the POS

Files: `app/Services/OrderService.php` (`exportToCsv()` only),
`tests/Feature/Shop/ShopOrderReviewTest.php`

What: the one office-side touch this cycle allows, because the Shop export
runs through it. In `exportToCsv()` skip an item whose `product` relation is
null (`continue`), with a one-line comment saying why. A product that no
longer exists cannot be ordered, and the office row shows nothing for it
either. Add a test: a draft with one real ordered item and one `GONE`
ordered item exports 200, the body contains the real product's supplier
code and not `GONE`.

Check: `php artisan test --filter=ShopOrderReviewTest` passes;
`php artisan test tests/Feature/Shop` passes; pint clean on `OrderService.php`.

## Review (Revision 2, 2026-10-06) — ACCEPTED

Read the Revision 2 report to the end and the diffs of the five files it
touches; reran `css-ok`, `sprite-ok`, the inline-style grep (0 and 0),
pint, `tests/Feature/Shop` (314 passed) and the full suite:
**15 failed / 1020 passed**, the same five classes as the baseline.

### Criteria

| Step | Result |
|---|---|
| 11 Window the rows | pass. 50 per group, "Show N more · M left", full counts kept, windows reset on filter/switch/sort/search via `$watch`. First render of session 20 is 564 ms in a hidden tab (was about 2.7 s for 329 rows plus the fetch; showing every row was 18 s). Sorts are 327–655 ms there, above the 300 ms I asked for; see below |
| 12 Tag pill dot | pass: both rules and the class gone, `css-ok` |
| 13 Read-only "Suggested N" | pass: exactly the planned span, checked in component state on session 20 |
| 14 Export guard | pass: four lines in `exportToCsv()`, a test that fails without them |

### Deviations

7. **Bars as `x-html` strings** — accepted. Only numbers and fixed class
   names enter the string, the markup is the design's, and halving the
   directives per row is what made the windowed page quick. The Blade
   comment explains it to the next reader. Cycle 2 builds on it.
8. **`content-visibility: auto` on `.shop-ord`** — accepted; it is an
   app rule under the marker. Cycle 2 notes the paint-containment
   consequence.
9. **Timing by microtasks plus a forced layout** — accepted; the plan's
   `requestAnimationFrame` advice was wrong for a hidden tab. Added to
   `docs/shop_new/README.md` under "Checking a change".

### Notes for Planner

- **Sorts above 300 ms** — deferred, the Implementer's option (c): the
  numbers come from a background renderer, and the real question is how
  the tablet feels. If it drags, `PAGE` 25 is one constant away. Owner to
  try an Udea draft on the tablet.
- Hidden-tab timing method → recorded in the Shop README (done by the
  Planner).
- "36 units · €61.56" on session 20 — no action, ordinary maths.
- From the Revision 1 notes, still open for the owner: filters scroll
  sideways at 390 px and the sort select squeezes the search field (both
  as the design behaves; a phone tweak if wanted); negative snapshot
  stock shows as it is.
- Dev state: draft 306 has item 177715 at 13 cases with eight
  `order_adjustments` rows (9766–9773) from the owner's own taps; left
  alone. The owner can reset the row from the screen; the eight rows
  feed the learning average for that product until deleted.

Archived to `archive/2026-10-06-cycle-1-shop-order-review/`. Cycle 2 (week
readout on the chart) starts from a fresh `plan.md`.
