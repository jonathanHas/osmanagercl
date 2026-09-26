# Shop mode cycle 17 — Fruit & veg: waste log and harvest log (screens 13 + 14)

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

Staff log fruit-and-veg waste and Jon's harvest from Shop mode. Today the Home tile needs `fruit_veg.manage`, which employees do not hold on a fresh seed, and it lands on the office availability page, so neither log is reachable from the Shop. This cycle adds two Shop screens on the existing endpoints: **Waste log** (pick an on-till product, choose kg or units, set how much, log it; today's entries listed with a way to remove one) and **Harvest** (pick one of Jon's products, tap the quantity on the pad, log it; today's picks listed). The Home tile moves to `fruit_veg.operate` and opens the waste log; the two screens share a segmented nav. Availability and F&V labels (screens 12 and 15) are later cycles. One portability defect in the office controllers is fixed on the way because the Shop tests would hit it too.

## Context

**Access.** Routes under `fruit-veg.` (`routes/web.php` 435–486): waste and harvest routes all carry `permission:fruit_veg.operate`; availability/labels also `operate`; prices, manage, sales, price-sync `fruit_veg.manage`. Seeder: employees get both `fruit_veg.manage` and `fruit_veg.operate` in `RolesAndPermissionsSeeder` (lines 333–348), but `RolePermissionGrantsTest` pins only `operate` for employees, and the owner reports the screens are not available, so treat `operate` as the employee permission. Home tile (`config/shop.php` line 22): `fruit-veg` → `fruit-veg.availability`, permissions `['fruit_veg.manage']`, tone sage.

**Waste** (`app/Http/Controllers/WasteController.php`, model `WasteLog`, table `fv_waste_logs`, unique `(waste_date, product_code)`, `UNITS = ['kg','unit']`, casts `waste_date` date, `quantity`/`unit_price`/`value` decimal:2; no reason column):
- `index(date)` view: on-till F&V products (`TillVisibilityService::getProductsWithVisibility('fruit_veg', ['visibility' => 'visible'])`) plus off-till products already logged that day, through private `buildRows($products, $existing)` → rows `{ code, name, category, origin, class, on_till, current_price, priced_unit ('kg'|'unit'), quantity (today's or null), unit (today's, else last used, else priced), value }`.
- `search(q, date)` JSON `{ products: [rows] }` over all F&V products (`searchAllProductsWithVisibility`), 50 max.
- `entry(date, product_code, quantity, unit)` JSON: replaces the day's row (`updateOrCreate`); `quantity <= 0` deletes it → `{ deleted: true }`; success `{ saved, quantity, unit, value }`; unknown or non-F&V product → 422 `{ error }`. **Replace semantics**: the Shop screen must send today's existing quantity plus the new amount.
- `history` view (office), `destroy`.

**Harvest** (`app/Http/Controllers/HarvestController.php`, models `Harvest` (`harvests`, unique `(harvest_date, product_code)`, `notes`), `HarvestProductUnit` (`UNITS = ['kg','unit']`, per-product unit preference)):
- `index(date)` view: Jon's products = POS `PRODUCTS` whose `CODE` is in `supplier_link` rows with `SupplierID = config('suppliers.jon')`; rows = products harvested in the last 30 days ∪ logged today, each `{ code, name, unit (pref, default kg), logged (today's total), label }`; `availableProducts` = the rest of Jon's products; also Zebra label payloads for printing after saving (**out of scope here**, Zebra is parked).
- `saveRow(date, code, amount, unit, notes?)` JSON: **accumulates** onto the day's row (`firstOrNew` + add), remembers the unit per product; success `{ success, logged, unit, saved_amount }`; not Jon's product → 422.
- `history` view, `destroy`.

**The SQLite date defect.** Both controllers query `where('waste_date', $date)` / `where('harvest_date', $date)` with a `Y-m-d` string while the `date` cast stores `Y-m-d 00:00:00`. MySQL's DATE column coerces, so production is fine; SQLite compares strings, so `updateOrCreate` never finds the row and the unique index throws. That is exactly why `WasteLogTest::entry_updates_the_same_day_row_instead_of_duplicating` and `zero_quantity_deletes_the_entry` have been in the 17 pre-existing failures. `whereDate()` compiles to `date(col)` on MySQL and `strftime('%Y-%m-%d', col)` on SQLite, so it is right on both; the tables are small, so losing the index on that predicate does not matter.

**Design.** `docs/design/shop-mode/screen-13-fv-waste.html`: `shop-page` → `nav.shop-seg` with four `a.shop-seg__opt` (Availability / Waste log / Harvest / Labels, `aria-current="page"` on the active one) → `shop-split`: left `section.shop-stack` "1 · Product" with a `shop-search` and `shop-choices` of `label.shop-choice` (radio, name, `<small>` unit); right `section.shop-card` "2 · How much" with a `shop-stepper`, then "3 · Reason" choices (Spoiled / Damaged / Past date / Other); sticky `shop-actions` "Log 1.5 kg bananas". `screen-14-fv-harvest.html`: the same nav → `shop-split`: left "What did you pick?" `shop-choices` plus a "Today" `shop-list` (`shop-row` title, meta "08:10 · Ben", aside `shop-row__qty` "12 bunches"); right `shop-card` with a `shop-numpad` (display "Quantity (kg)"); sticky action "Log 4.2 kg salad mix". **Design decisions taken:** the nav shows only Waste log and Harvest until screens 12 and 15 exist; the waste "Reason" step is dropped (no column; if the owner wants reasons it is a migration and a later cycle); the waste screen also gets a "Today" list (as harvest has) so a wrong entry can be removed; the harvest numpad gains a kg/units switch (`shop-seg--block`) because the endpoint needs the unit and the preference is per product.

**Shop pieces.** `x-shop.scan-input` is not used (nothing is scanned); choices, stepper, numpad markup exist on earlier screens (`stock-scan.blade.php` numpad and `key()` handler; `delivery-scan.blade.php` stepper); `shop-seg` as links with `aria-current` (cycle 13); `mix()` if a shared part is wanted (not needed). Tests: `WasteLogTest` shows the fixture (POS `PRODUCTS`, `vegDetails`, `TAXCATEGORIES`, `TAXES`; `mysql` connection repointed to in-memory SQLite with `fv_waste_logs` created by hand; `WasteLog` is pinned to `mysql`, and so is `Harvest`, check). `veg_price_history` is read via `DB::table` on the default connection (migrated). Suite baseline: 17 failed / 579 passed.

## Constraints

- Do not commit, push or deploy.
- Endpoint contracts unchanged except the `whereDate` fix and two new read-only JSON actions; the office waste and harvest pages keep working (render them through the kernel as admin as the check).
- Employees see only what `fruit_veg.operate` allows; nothing on these screens needs `manage`.
- Contract rules; design block byte-identical; app rules only if a design class is missing.

## Out of scope

- Availability (screen 12) and F&V labels (screen 15), and the nav entries for them.
- Waste reasons (no column). Notes on harvest rows.
- Printing a Zebra label after a harvest entry (Zebra is parked).
- Editing past days (both screens are today only; the office pages keep the date picker).

## Steps

### 1. Date predicates that work on both databases
Files: `app/Http/Controllers/WasteController.php`, `app/Http/Controllers/HarvestController.php`
What: every `where('waste_date', $x)` / `where('harvest_date', $x)` (including inside `updateOrCreate` / `firstOrNew` attribute arrays, which must become an explicit `whereDate(...)->first()` followed by create-or-fill) becomes a `whereDate`. Range predicates (`>=` for the 30-day window) stay as they are. Do not touch anything else in these controllers yet.
Check: `php artisan test --filter=WasteLogTest` → **7 passed** (the two pre-existing failures turn green); grep `where('waste_date'`/`where('harvest_date'` → 0 hits in the two controllers.

### 2. JSON reads for the Shop screens
Files: `app/Http/Controllers/WasteController.php`, `app/Http/Controllers/HarvestController.php`, `routes/web.php`
What: extract `index()`'s row computation into a private `rowsFor(string $date): Collection` and add `rows(Request)` → `{ date, products: rowsFor(today or ?date) }` (same row shape as `search`); route `GET /waste/rows` name `waste.rows`, `permission:fruit_veg.operate`. Extract `HarvestController::index()`'s product/row computation into private `dataFor(string $date): array{rows, available}` and add `rows(Request)` → `{ date, rows: [{ code, name, unit, logged, updated_at (ISO), by (creator name or null) }], available: [{ code, name, unit }] }` (no label payloads); route `GET /harvest/rows` name `harvest.rows`, `permission:fruit_veg.operate`. `index()` on both keeps returning the same view data via the extracted methods.
Check: `php artisan route:list --name=fruit-veg.waste.rows` and `--name=fruit-veg.harvest.rows` → `fruit_veg.operate`; rendering `/fruit-veg/waste` and `/fruit-veg/harvest` as admin through the kernel → 200 before and after.

### 3. Shop routes, controller, tile
Files: `app/Http/Controllers/Shop/FruitVegController.php (new)`, `routes/web.php`, `config/shop.php`
What: `waste()` → `view('shop.fv-waste')`, `harvest()` → `view('shop.fv-harvest')`; both routes in the `shop` group under `permission:fruit_veg.operate`: `GET /fv/waste` → `shop.fv.waste`, `GET /fv/harvest` → `shop.fv.harvest`. Tile `fruit-veg`: `'hint' => 'Waste and harvest logs'`, `'route' => 'shop.fv.waste'`, `'permissions' => ['fruit_veg.operate']`, tone unchanged.
Check: `php artisan route:list --name=shop.fv` → both; `php artisan test --filter=ShopHomeTest` green (update a tile assertion if one pins the old permission or route, and say so).

### 4. Behaviour: `fv-waste.js`
Files: `resources/js/shop/fv-waste.js (new)`, `resources/js/shop.js`
What: `Alpine.data('shopFvWaste')` reading `rowsUrl`, `searchUrl`, `entryUrl` from `data-*`; csrf from the meta tag. State: `date` (from the rows payload), `products = []`, `results = null` (server search results or null), `query = ''`, `selected = null` (a row), `unit = 'kg'`, `amount = 0`, `busy`, `loading`, `toast`.
- `init()` → `load()`: GET `rowsUrl` → `products`, `date`.
- `shown` getter: `results ?? products.filter(p => matches(p.name))` (client filter over on-till products; server search for the rest).
- `search()` (`@input.debounce.300ms`): empty → `results = null`; else GET `searchUrl?q=…&date=…` → `results = data.products`.
- `select(p)`: `selected = p`, `unit = p.unit`, `amount = step(unit)` (0.5 for kg, 1 for unit); `setUnit(u)`: `unit = u`, `amount = step(u)`.
- `bump(n)`: `amount = Math.max(0, round(amount + n * step(unit), 2))`; `amountText`: kg → `amount.toFixed(1) + ' kg'` (`1.5 kg`), unit → `amount + (amount === 1 ? ' unit' : ' units')`.
- `todayTotal(p)`: `p.quantity ?? 0` when `p.unit === unit` else 0 (a unit change starts a new total; the server has one row per product per day, so logging in a different unit replaces the row: say so in a `shop-meta` hint when `selected.quantity` exists and `selected.unit !== unit`).
- `logLabel`: `'Log ' + amountText + ' ' + selected.name` or `'Log waste'` when nothing selected; `canLog`: `selected && amount > 0`.
- `log()`: POST `entryUrl` `{ date, product_code: selected.code, quantity: (todayTotal(selected) + amount).toFixed(2), unit }` → on `saved`: toast ok `Logged ${amountText} ${name} · ${data.quantity} ${unit} today`, update the product row (`quantity`, `unit`, `value`) in `products`/`results`, `amount = step(unit)`; on 422 → toast bad `data.error`; network → toast bad.
- `remove(p)`: POST `entryUrl` with `quantity: 0` → on `deleted` set `p.quantity = null`, toast ok `Removed ${name}`.
- `today` getter: `products.filter(p => p.quantity > 0)`.
Check: node exercise with stubbed fetch: load 3 products (one with `quantity: 2, unit: 'kg'`); `select(bananas)` → `unit` 'kg', `amount` 0.5; `bump(2)` → 1.5, `amountText` "1.5 kg", `logLabel` "Log 1.5 kg Bananas"; `log()` posts `quantity: "3.50"` (2 + 1.5) and the toast reads "… · 3.5 kg today"; `setUnit('unit')` → amount 1, `todayTotal` 0; `remove(p)` posts `quantity: 0`; `grep -c "route(" …` → 0.

### 5. Behaviour: `fv-harvest.js`
Files: `resources/js/shop/fv-harvest.js (new)`, `resources/js/shop.js`
What: `Alpine.data('shopFvHarvest')` reading `rowsUrl`, `saveUrl`. State: `date`, `rows = []` (recent + today), `available = []`, `query`, `selected`, `unit`, `typed = ''`, `busy`, `loading`, `toast`.
- `load()`: GET `rowsUrl`.
- `choices` getter: `rows` first then `available`, filtered by `query` on name; each choice carries `unit` and `logged` (0 for available).
- `select(p)`: `selected = p`, `unit = p.unit`, `typed = ''`; `setUnit(u)`.
- `key(k)`: digits/point/backspace exactly as the vouchers pad (max 7 chars, 2 decimals, no leading point); `amount` getter.
- `amountText`: kg → `amount + ' kg'` (no forced decimals: "4.2 kg", "3 kg"); units → `n unit(s)`.
- `logLabel`: `'Log ' + amountText + ' ' + selected.name`; `canLog`: `selected && amount > 0`.
- `log()`: POST `saveUrl` `{ date, code, amount: amount.toFixed(2), unit }` → success: toast ok `Logged ${amountText} ${name} · ${data.logged} ${unit} today`, then `load()` (the server accumulates and stamps time/user, so re-read rather than guess), `typed = ''`; 422 → toast bad `data.message`.
- `today` getter: `rows.filter(r => r.logged > 0)`; `when(iso)` → `HH:MM`.
Check: node exercise: load with one recent row (logged 3.2 kg) and two available; `select(available[0])` → unit from pref; keys 4 . 2 → "4.2", `logLabel` "Log 4.2 kg Salad mix"; `log()` posts `{ amount: "4.20", unit: "kg" }`, then `load` ran again; `today` lists the row with `logged > 0`; `grep -c "route(" …` → 0.

### 6. The two screens
Files: `resources/views/shop/fv-waste.blade.php (new)`, `resources/views/shop/fv-harvest.blade.php (new)`, `resources/views/shop/partials/fv-nav.blade.php (new)`
What: nav partial: `<nav class="shop-seg" aria-label="Fruit and veg"><a class="shop-seg__opt" href="{{ route('shop.fv.waste') }}" @if ($active === 'waste') aria-current="page" @endif>Waste log</a><a … harvest …>Harvest</a></nav>` (no Availability/Labels entries yet).
**Waste** (`<x-shop-layout title="Fruit & veg" :back="route('shop.home')">`, `<main class="shop-page" x-data="shopFvWaste()" data-rows-url data-search-url data-entry-url>`): nav (`waste`), `shop-split`:
- left `section.shop-stack`: `h2.shop-subtitle` "1 · Product"; `shop-search` (`search` icon, `x-model="query"`, `@input.debounce.300ms="search()"`, placeholder "Search products"); `shop-choices` with `<template x-for="p in shown" :key="p.code"><label class="shop-choice"><input type="radio" name="p" :value="p.code" :checked="selected && selected.code === p.code" @change="select(p)"><span x-text="p.name"></span><small x-text="(p.priced_unit === 'kg' ? 'per kg' : 'each') + (p.quantity > 0 ? ' · ' + p.quantity + ' ' + p.unit + ' today' : '')"></small></label></template>`; `shop-empty` "No products match" when `shown` is empty and not loading; then `h2.shop-label` "Today" + `shop-list` of `today` rows (`shop-row`: title name, meta `value ? '€' + value.toFixed(2) : ''`, aside `shop-row__qty` `quantity + ' ' + unit`, ghost × `@click="remove(p)"` aria-label "Remove"), shown when `today.length`.
- right `section.shop-card` `x-show="selected"` `x-cloak`: `h2.shop-subtitle` "2 · How much"; unit `shop-seg shop-seg--block` radios kg / units (`x-model="unit"`, `@change="setUnit(unit)"`); `shop-stepper` (`shop-iconbtn--lg` minus/plus, `output.shop-stepper__value` `x-text="amountText"`); `shop-meta` hint when `selected.quantity > 0 && selected.unit !== unit`: "Logging in a different unit replaces today's entry."
- `shop-actions`: `<button class="shop-btn shop-btn--primary shop-btn--lg" type="button" :disabled="! canLog || busy" @click="log()"><x-shop.icon name="check" /><span x-text="logLabel"></span></button>`.
**Harvest** (same shell, `x-data="shopFvHarvest()" data-rows-url data-save-url`): nav (`harvest`), `shop-split`:
- left `div.shop-stack`: `section.shop-stack` with `h2.shop-subtitle` "What did you pick?", `shop-search` (client filter), `shop-choices` over `choices` (small: `unit === 'kg' ? 'per kg' : 'units'` plus `logged > 0 ? ' · ' + logged + ' ' + unit + ' today' : ''`); `section.shop-stack--tight` "Today" `shop-list` of `today` rows (title name, meta `when(updated_at) + (by ? ' · ' + by : '')`, aside `logged + ' ' + unit`).
- right `section.shop-card` `x-show="selected"`: unit `shop-seg--block` kg / units; `shop-numpad` (display `shop-label` `x-text="'Quantity (' + (unit === 'kg' ? 'kg' : 'units') + ')'"`, value `typed || '0'`; keys as vouchers).
- `shop-actions` with the log button as above.
- Toast block on both.
Check: `php artisan test --filter=ShopViewContractTest` green (two new screens and the partial).

### 7. Tests
Files: `tests/Feature/Shop/ShopFruitVegTest.php (new)`
Fixture: as `WasteLogTest::setUp()` (POS `PRODUCTS` with `CATEGORY` in `SUB1`, `vegDetails`, `TAXCATEGORIES`, `TAXES`; the `mysql` connection repointed to in-memory SQLite) plus POS `CATEGORIES` (`ID`, `NAME`; `buildRows` loads `category`), `PRODUCTS_CAT` (`PRODUCT`) for on-till, `supplier_link` (`Barcode`, `SupplierCode`, `SupplierID`) with `Config::set('suppliers.jon', 'J1')`; on the `mysql` connection create `fv_waste_logs`, `harvests`, `harvest_product_units` by hand as the waste test does (copy each column list from its migration); default DB has `veg_price_history` from migrations. Users via `userWith()`: employee with `fruit_veg.operate` only.
- `employee_can_open_both_screens`: 200 on `shop.fv.waste` and `shop.fv.harvest`, `data-shell="shop"`, the nav with both links and `aria-current="page"` on the right one, `data-rows-url`, no `fruit_veg.manage` needed.
- `barista_is_forbidden`: 403 on both.
- `home_tile_opens_the_waste_log_for_operate_only_users`: employee with `fruit_veg.operate` sees the tile linking to `route('shop.fv.waste')`; without it, no tile.
- `waste_rows_lists_on_till_products_with_todays_entry`: two products, one on till; log 2 kg of it today → `waste.rows` → `products` has the on-till one with `quantity` 2, `unit` 'kg', `priced_unit`; the off-till one absent; log the off-till one → present with `on_till` false.
- `waste_entry_replaces_and_zero_deletes` (mirrors the two previously failing office tests, from the Shop's perspective): POST entry 2 then 3.5 → one row, quantity 3.5; POST 0 → deleted.
- `harvest_rows_lists_recent_and_available_jon_products`: three Jon products, one harvested 5 days ago, one today (3.2 kg) → `rows` has those two with `logged` 0 and 3.2, `by` the user's name for today's, `available` has the third; a non-Jon product appears nowhere.
- `harvest_save_row_accumulates`: save 4.2 then 1 → `logged` 5.2; unit preference remembered (`harvest_product_units`).
- `office_pages_still_render`: admin GET `fruit-veg.waste` and `fruit-veg.harvest` → 200 (the extracted methods still feed the views).
Check: `php artisan test --filter="ShopFruitVegTest|WasteLogTest|ShopHomeTest"` green.

### 8. Docs, README, format, build
Files: `docs/features/fruit-veg-system.md`, `docs/design/shop-mode/README.md`, `docs/development/known-issues.md`, all touched
What: feature doc: a "Shop mode" note under Waste Log and a short Harvest note (screens, endpoints, replace vs accumulate semantics, no reasons). README: Fruit & veg bullet (nav shows two of four, why). Known issues: resolve/annotate the SQLite date-comparison entry if one exists, else add a short one ("waste/harvest day lookups use `whereDate`; comparing a date column with a `Y-m-d` string only works on MySQL"). `./vendor/bin/pint --dirty`; `npm run build`.
Check: `./vendor/bin/pint --test --dirty` clean; build succeeds.

## Verification

1. `php artisan route:list --name=shop.fv` → two routes, `fruit_veg.operate`; `--name=fruit-veg.waste.rows`, `--name=fruit-veg.harvest.rows` → `fruit_veg.operate`.
2. `php artisan test --filter="Shop|WasteLog"` → green.
3. `php artisan test` → **15 failed** (the two waste tests now pass), the remaining 15 = the known set minus them; passed = 579 + 2 + new tests.
4. `git diff` of the two office controllers shows only: `whereDate` substitutions, the extracted private methods, and the two new `rows()` actions; `git diff --stat resources/views/fruit-veg/` → empty.
5. Contract greps; `grep -c "route(" resources/js/shop/fv-*.js` → 0 each; design block `cmp` identical.
6. `./vendor/bin/pint --test --dirty` clean; `npm run build` succeeds.
7. Manual, dev app, as an employee holding only `fruit_veg.operate` (create one if none: dev employees hold `manage` too; a throwaway user via tinker is fine), exercising every action: Home → Fruit & veg → the waste log with today's on-till produce as tiles; tap Bananas → the card shows kg with 0.5; + twice → "Log 1.5 kg Bananas"; log → toast and "1.5 kg today" on the tile and the Today list; log another 1 kg → 2.5 kg today; switch to units → the hint appears; type a search for an off-till product → it appears from the server search; × on a Today row → gone; office `/fruit-veg/waste` shows the same day's rows. Harvest: tiles show recent picks first; tap one, 4 . 2 → "Log 4.2 kg Salad mix"; log → Today shows it with the time and your name; log 1 more → 5.2 kg; switch a product to units and log → the office harvest page shows the unit remembered.

## Risks

- **`whereDate` in office controllers** is a behaviour-preserving change on MySQL; on the small waste/harvest tables the lost index use is irrelevant. It flips two tests from red to green, so the "identical 17" baseline becomes 15: say so loudly in `implemented.md`.
- **Replace vs accumulate**: waste replaces, harvest accumulates. The Shop waste screen adds client-side and sends the total, so two people logging the same product at the same moment could overwrite each other; the office page has the same property. Acceptable for a single-till shop.
- **Employees who hold `manage` on dev** will not reproduce the owner's "not available"; the test with an `operate`-only user does.
- **Search in waste** mixes client filtering of on-till products with server results for the rest; results replace the list while a query is present, which matches the office behaviour.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end and the diffs of both office controllers, the routes, the tile, the new Shop controller, the nav partial, both screens, both scripts, the tests and the known-issues entries. Reran `php artisan test`: **15 failed / 594 passed**, exactly as the plan predicted: the two long-standing waste failures are green and the remaining 15 are the known set. Design block byte-identical; no app rules added; the office views untouched; the 31 lines removed from the controllers are moved lines and replaced date predicates only.

**Steps 1–8: pass.** The `whereDate` fix, the two extracted row builders and their JSON actions, the routes and tile under `fruit_veg.operate`, the two screens with the shared nav, replace-versus-accumulate handled correctly in the two scripts, ten tests including an operate-only user, and the docs. The implementer walked both screens against the real dev data with every control tapped and restored the data afterwards.

**Deviations.** `HarvestController::rows()` reads user names with a direct `User` query instead of `->with('creator')`: **accepted, and the important catch**: `Harvest` is pinned to the `mysql` connection, so the relation inherits it, which is the same database in production and a different one under test; the first run of the new test found it. Tight `@if` in the nav partial and ten tests instead of eight: **accepted.**

**Notes for Planner.**
1. Harvest accumulates across a unit change ("5.2 kg" + "2 units" = "7.2 unit"): **real, pre-existing in the office endpoint, deferred to a short follow-up**. The decision is the rule (refuse a unit change while today's row exists in another unit is the simplest honest one; the Shop screen would then lock the unit switch to today's unit). Recorded as a candidate.
2. Always-failing tests can describe a real defect: **agreed**; baseline is now 15.
3. Relations from `mysql`-pinned models are untestable as written: **housekeeping candidate**, a grep across controllers.
4. Other tiles versus the employee grants: **checked by the Planner** after this review: every Home tile's permission is in the seeder's employee list; the fruit-veg tile was the only mismatch.
5. `&amp;` in a layout title prop: noted; the test now pins it.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-17/`.
