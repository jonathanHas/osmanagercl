# Shop mode cycle 2 — access hardening

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-23

## Goal

Make the role model real. Today almost every manager page only needs a login: an employee (15 of the 20 users) or the barista can open orders, invoices, kitchen recipes, RTD, product editing or the sales importer by typing a URL, because the sidebar merely hides links. This cycle puts `permission:`/`role:` middleware on every route group that is still `auth`-only, adds the handful of finer permissions the shop-floor tasks need, removes the sidebar links that would now 403, and gives the existing tests a role so they keep passing. When it is done, `php artisan route:list` shows no authenticated page outside the shell/profile/auth set without a permission or role gate, and an employee typing `/orders` gets 403. No page is restyled and no Shop mode screen is built.

## Context

Cycle 1 (archived at `docs/planImp/archive/2026-09-23-shop-mode-cycle-1/`) added `/shop`, `UiMode`, the Shop/Office switch and the barista KDS link. Nothing from it is committed yet; the working tree is the baseline.

**RBAC as it stands.** `app/Traits/HasPermissions.php` on `User`: `hasAnyPermission()` returns true for admin without looking at rows; every other role needs a `role_permissions` row. `app/Http/Middleware/PermissionMiddleware.php` calls `hasAnyPermission($permissions)`; `RoleMiddleware` calls `hasAnyRole($roles)`; both abort 403 (a guest is stopped earlier by `auth`). Aliases `permission` and `role` are registered in `bootstrap/app.php`. **Blade gotcha:** the project never uses `@can`. Blade's `@can` goes through the Gate, which knows nothing about this table, so it would be false for everyone but admin. Use `@if(auth()->user()->can('x'))` (the `User::can()` override) as the rest of `admin.blade.php` does.

**Seeder.** `database/seeders/RolesAndPermissionsSeeder.php` defines every permission with `firstOrCreate` and grants via `givePermissionTo` (which `firstOrFail`s the name, so a grant to a permission the seeder does not create throws). Employee grants at lines 292–304, manager at 311–324 (`array_merge` of the employee list plus more), barista `kds.access` only. `tests/Feature/CashBagVerificationEditTest.php:23` runs this seeder in tests, so it must stay valid. Current live counts: admin 3, manager 1, employee 15, barista 1, no-role 0.

**Permissions that already exist** (seeder + `2025_08_11_232011_add_cash_reconciliation_permissions.php`): `products.view/create/edit/delete/manage_pricing/manage_barcodes`, `sales.view_reports/view_analytics/export_data/import_data`, `deliveries.view/create/process/manage`, `labels.view/print/manage`, `categories.view/manage`, `fruit_veg.manage`, `coffee.manage`, `kds.access`, `users.*`, `customer-invoices.manage`, `customer-requests.manage`, `vouchers.redeem/manage`, `settings.view/manage`, `system.backup/maintenance`, `cash_reconciliation.view/create/export`, `till_review.view/export`. Employee holds: `products.view`, `deliveries.view`, `deliveries.process`, `labels.view`, `labels.print`, `categories.view`, `fruit_veg.manage`, `coffee.manage`, `kds.access`, `vouchers.redeem`, `customer-requests.manage`, `till_review.view`. Manager holds those plus `products.edit`, `products.manage_pricing`, `sales.view_reports/view_analytics/export_data`, `deliveries.create`, `deliveries.manage`, `labels.manage`, `categories.manage`, `users.view`, `customer-invoices.manage`, `vouchers.manage`, `cash_reconciliation.*`, `till_review.export`. Note manager does **not** hold `products.create`, `settings.view` or `sales.import_data`.

**Migration template:** `database/migrations/2025_08_11_232011_add_cash_reconciliation_permissions.php`. Copy its shape (`firstOrCreate` per permission, `syncWithoutDetaching` per role guarded by `if ($role)`, full `down()`), but drop the `guard_name` key: the `permissions` table has only `name, display_name, description, module` and `Permission::$fillable` does not include it.

**`routes/web.php` today (1118 lines).** Section comment anchors: Product routes 92, Stocking 139, Stock Check Review 145, Destock 155, Supplier Code Lookup 159, Invoice Management 163, Invoice Attachments 169, Bulk Upload 186, Amazon Pending 215, RTD 231, RTD Fallback 263, Supplier Management 281, Order Manager 304, Label area 313, Fruit & Veg 386, Coffee 441, Kitchen 461, Categories 526, Delivery management 540 (`Route::resource('deliveries')` at 541), Delivery Documents 569, Barrel Codes 577, Delivery Legacy 585, Order mockups 610, Order Management 623 (`Route::resource('orders')` at 628, `products.update-priority` at 646), User Management 648 (already gated), Settings 673, KDS 678 (already `kds.access`), Sales Import 699, Udea scraping tests 723, Debug closures 749–835, Till Review 837, Cash Reconciliation 848 (gated), Financial `management.*` 864 (gated `role:admin,manager`), Customer Invoicing 1008 (gated), Vouchers 1077/1084 (gated), Customer Requests writes 1100 (gated), `require auth.php` 1118. Already-gated single routes: `stocking.logs` (`role:admin`), `stock-review.toggle-category` (`role:admin,manager`), `tools.ai-diagnostics*` and `tools.udea-pallet-volumes*` (`role:admin`), `labels.printer-cancel` and the two `delivery-legacy.undo-*` (`permission:deliveries.manage`).

**Deliveries: two systems.** Owner statement (2026-09-23): employees scan deliveries in with the **legacy** flow only (`delivery-legacy.*`, `resources/views/delivery-legacy/`). The newer `deliveries.*` system (`DeliveryController`, `resources/views/deliveries/`, delivery documents, barrel codes) is manager/office work and is to be kept off employee views entirely. So `delivery-legacy.*` → `deliveries.process` (employees hold it) and everything under the "Delivery management routes" comment → `deliveries.manage` (managers hold it, together with `deliveries.create`). The legacy views reference only `delivery-legacy.*` routes, `customer-requests.index`, and fourteen `products.edit` pop-out links in `resources/views/delivery-legacy/match.blade.php` at lines 861, 974, 1099, 1234, 1400, 1527, 1689, 1769, 1907, 1961, 2020, 2108, 2215, 2300 (each an `<a href="{{ route('products.edit', $item->productID) }}" target="_blank" ...>…</a>`); they do not link to `products.create`.

**Views with links that will 403 after gating** (all use `auth()->user()->can(...)` style already):
- `resources/views/layouts/admin.blade.php`: Order Management section 385–422 wrapped in `@unless(hasRole('barista'))` (386/422) holding three links: `orders.index` (398), `deliveries.index` (406), `delivery-legacy.index` (414); Stock section 623–678 wrapped the same way (624/678) with the Stock Valuation link already inside `hasAnyRole(['admin','manager'])` at 660; System Tools 680–718 (`@unless` at 681/718) holding the Sales Import link at 693–699 and admin-only tools from 701; Administration 720+ with the Settings link at 744 inside an `@unless(barista)` at 743–752, Users link at 734 (already `can('users.view')`), Stocking Logs at 755 (already admin).
- `resources/views/labels/index.blade.php`: "Clear All" button at line 159 (`onclick="clearAllLabels()"`), "Restore All" buttons at 327–332 (`onclick="...restoreBatch(...)"`).
- `resources/views/fruit-veg/index.blade.php`: F&V order generation link at line 135 (`route('fruit-veg.orders')`).
- `resources/views/dashboard.blade.php`: quick links to `invoices.bulk-upload.index` and `suppliers.outstanding-report` (grep for them).
- `resources/views/delivery-legacy/match.blade.php`: the fourteen `products.edit` links listed above.
- `resources/views/deliveries/**` is not touched: the whole new delivery system becomes manager-only at the route level, so no in-page gates are needed there.

**Tests.** PHPUnit, sqlite in-memory for `default` and `pos`. `database/factories/UserFactory.php` has no role state. Fifteen feature tests log in with a role-less `User::factory()->create()` and hit routes this cycle gates: `KitchenIngredientProfileEditReturnTest`, `KitchenIngredientProfileStoreTest`, `KitchenOrderCreatePageTest`, `KitchenOrderCsvTest`, `KitchenOrderHistoryTest`, `KitchenOrderStoreTest`, `KitchenOrganicRegistrationTest`, `KitchenRecipeScalingTest`, `KitchenStandingOrderTest`, `FruitVegLabelPrintingTest`, `FruitVegProductImageTest`, `WasteLogTest`, `LabelTranslationSaveTest`, `OrderDifferenceCreationTest`, `TestScraperControllerTest`. `ProductTest` and `ProductSearchApiTest` only hit product GETs and the search API, which stay open to `products.view` holders; with a role-less user they would 403, so they are on the list too (17 files). `ProfileTest` and `tests/Feature/Auth/*` hit ungated routes and stay untouched. Tests that already build roles (`CustomerRequest*`, `Kds*`, `DeliveryTranslatedLabelPrintingTest`, `UdeaPalletVolumeSyncTest`, `CashBagVerificationEditTest`, `Customer*`, `InvoiceBulkUploadChunkedTest`) use admin or a role with the right permission and need no change.

**Baseline failures.** The full suite currently has 17 failures that predate this work, in `UdeaScrapingServiceTest` (7), `CashReconciliationTest` (3), `WasteLogTest` (2: a `fv_waste_logs` unique-constraint clash and a count), `ProductTest` (2: a 302 instead of 404, and dashboard text), `FruitVegLabelPrintingTest` (2: `no such table: CATEGORIES` on the POS connection, and queue state), `TestScraperControllerTest` (1: page content). Four of those classes are on the edit list above. The acceptance rule for them is "fails the same way as before", not "goes green".

## Constraints

- Do not commit, push or deploy.
- Employees must not lose a task they use on the floor. The gating below therefore grants employees the permissions their existing pages need. The pages employees **do** lose are listed in Out of scope so the owner can see them; those are manager tasks by the seeder's own definition.
- Every Blade gate uses `auth()->user()->can('...')`, never `@can` (see Context).
- `admin.blade.php` changes are limited to the wrapper conditions named in step 9. No markup is restyled or moved; `grep -rl "x-admin-layout" resources/views | wc -l` stays 192; `git diff resources/views/kds/` stays empty.
- Route names must not change. Replacing `Route::resource('deliveries')` must keep `deliveries.index/create/store/show/edit/update/destroy`.
- Keep route order inside each group exactly as it is (several comments say "must be before resource route"); only wrap, never reorder.
- The seeder must remain runnable in tests (`CashBagVerificationEditTest` seeds it): every permission it grants must be one it creates.
- Do not rewrite or delete existing tests to make them pass. The only permitted test edit is swapping the user factory call as described in step 11.

## Out of scope

- Any Shop mode screen, `resources/views/shop/**`, `config/shop.php` tile changes (cycle 3 onward).
- PIN quick-switch, `shop.devices.manage` (cycle 10).
- KDS routes and views: `kds.*` stays exactly as it is (`permission:kds.access` on the whole group, including the allow-list pages).
- Splitting `deliveries/show.blade.php` into partials; only the `can()` gates named in step 10.
- `management.*`, `cash-reconciliation.*`, `customer-*`, `vouchers.*`, `users.*`: already gated, untouched.
- Making the sidebar config-driven.
- Fixing the 17 pre-existing test failures.
- Granting `settings.view` or `sales.import_data` to managers. Both stay admin-only as the seeder intends; the one manager loses `/settings` (cache clear, system info) and `/sales-import`. Recorded so the owner can grant them later with one seeder line.
- **What employees lose after this cycle** (URL access they never held a permission for): product creation and editing pages and inline product mutators; the entire new delivery system (`/deliveries` list, show, scan, create, documents, barrel codes); orders, order manager, F&V order generation; kitchen recipes and kitchen orders; supplier invoices, bulk upload, Amazon pending, RTD, VAT rates, supplier admin; category visibility toggles; label queue clear-all/restore and Zebra template management; sales import; settings; test/debug pages. They keep: product viewing and search, stock scanning, stock check review, **legacy delivery scanning (`/delivery-legacy`, match, complete, print translations)**, labels hub/print/scan/translate/Zebra printing, F&V availability/prices/labels/waste/harvest/manage, coffee, categories view, customer requests, vouchers redeem, till review, KDS.

## Steps

### 1. New permissions and grants: migration
Files: `database/migrations/2026_09_23_150000_add_shop_mode_permissions.php (new)`
What: modelled on `2025_08_11_232011_add_cash_reconciliation_permissions.php` without `guard_name`. Create with `Permission::firstOrCreate(['name' => ...], [display_name, description, module])`:

| name | display_name | module | employee | manager |
|---|---|---|---|---|
| `stocking.scan` | Scan and adjust stock | Stock | yes | yes |
| `fruit_veg.operate` | Fruit & veg daily tasks | Category Management | yes | yes |
| `orders.manage` | Manage supplier orders | Ordering | no | yes |
| `invoices.manage` | Manage supplier invoices and RTD | Accounting | no | yes |
| `kitchen.manage` | Manage kitchen recipes and orders | Kitchen | no | yes |

Grants, each guarded by `if ($role)` and using `syncWithoutDetaching(Permission::whereIn('name', [...])->pluck('id'))`: admin → all five; manager → all five **plus the existing** `products.create` (managers add products while working the new delivery system, whose pages link to `products.create`); employee → `stocking.scan`, `fruit_veg.operate` only. `down()`: detach the five new names from every role, delete those five permissions; do not delete `products.create` (pre-existing), but detach it from manager.
Check: `php artisan migrate` runs clean; `php artisan tinker --execute="echo \App\Models\Role::where('name','employee')->first()->hasPermission('stocking.scan') ? 'yes' : 'no';"` prints `yes`; `...where('name','manager')...hasPermission('products.create')` prints `yes`; `...where('name','employee')...hasPermission('products.create')` prints `no`.

### 2. Seeder in step with the migration
Files: `database/seeders/RolesAndPermissionsSeeder.php`
What: add the five permission definitions to the `$permissions` array (same names, display names and modules as step 1). Add `'stocking.scan'` and `'fruit_veg.operate'` to `$employeePermissions`. Add `'orders.manage'`, `'invoices.manage'`, `'kitchen.manage'`, `'products.create'` to the manager-only list (manager already inherits the employee list). Barista unchanged.
Check: `php artisan db:seed --class=RolesAndPermissionsSeeder` completes on the dev DB without exception (it is idempotent); `php artisan test --filter=CashBagVerificationEditTest` still passes.

### 3. Products and product API
Files: `routes/web.php` (lines 92–137)
What: wrap in groups, preserving order:
- `permission:products.view`: `products.index`, `products.suppliers`, `products.show`, `products.sales-data`, `products.weekly-sales`, `products.daily-sales`, `products.transaction-details`, `products.image`, `products.udea-pricing`, `products.print-label`, and the four `api.products.*` routes (132–137).
- `permission:products.create`: `products.create` (93) and `products.store` (97).
- `permission:products.edit`: `products.edit`, `products.update`, `products.update-image`, `products.refresh-udea-pricing`, and every `PATCH`/`POST` mutator from `update-name` to `toggle-till-visibility` (110–122).
- `role:admin`: `products.independent-test` (95), `products.search-test` (96), `tools.udea-debug`, `tools.udea-case-test`, `tools.udea-case-test.scrape` (124–126).
Because `products/create`, `products/suppliers` and `products/udea-pricing` are literal paths that must stay above `products/{id}`, keep the literal routes first inside their groups and put the `{id}` routes after; Laravel matches in registration order, so registering the `products.view` group (with `{id}` routes) *after* the `products.create` group is the safe ordering. Write the groups in this order: view-literals (`index`, `suppliers`, `udea-pricing`), create, edit-literals (none), then view-`{id}` routes, then edit-`{id}` routes, then admin test routes. Simplest correct layout: two `products.view` groups is fine.
Check: `php artisan route:list --path=products --json | python3 -c "import json,sys; [print(r['method'].split('|')[0], r['uri'], [m for m in r['middleware'] if m.startswith(('permission','role'))]) for r in json.load(sys.stdin)]"` shows a `permission:`/`role:` entry on every row; `php artisan route:list --name=products.create` still lists `products/create` above `products/{id}`.

### 4. Stock, lookup, till review, settings, sales import, tests, debug
Files: `routes/web.php`
What:
- `permission:stocking.scan`: `stocking.index`, `stocking.lookup`, `stocking.update-stock` (139–142; `stocking.logs` keeps `role:admin`), the whole Stock Check Review block 145–152 (keep `toggle-category`'s existing `role:admin,manager`), Destock Review 155–157.
- `permission:products.view`: Supplier Code Lookup 159–161.
- `permission:till_review.view`: Till Review group 837–846 (add the middleware to the existing `Route::prefix('till-review')` chain).
- `permission:settings.view`: Settings 673–676.
- `permission:sales.import_data`: Sales Import group 699–721.
- `role:admin`: the `tests` prefix group 723–747, the three `/debug/*` closures 749–835.
Check: `php artisan route:list --name=stocking.index` shows `permission:stocking.scan`; `--path=debug` rows show `role:admin`; `--path=settings` rows show `permission:settings.view`.

### 5. Accounting: invoices, RTD, suppliers
Files: `routes/web.php` (163–302)
What: one `Route::middleware('permission:invoices.manage')->group(...)` around everything from the Invoice Management comment (163) through `Route::resource('suppliers', ...)` (302): invoice specifics, attachments (both prefixes), bulk upload, notes/bulk-mark-paid/mark-unpaid, Amazon pending, `invoices.export`, `Route::resource('invoices')`, VAT rates, RTD, RTD fallbacks, all `suppliers.*` and `Route::resource('suppliers')`. Keep the internal order untouched (specific routes before resources).
Check: `php artisan route:list --path=invoices --json` and `--path=rtd`, `--path=suppliers`, `--path=vat-rates` all show `permission:invoices.manage` on every row; `php artisan test --filter=InvoiceBulkUploadChunkedTest` passes (admin user).

### 6. Ordering: order manager, orders, order items, F&V orders, mockups
Files: `routes/web.php`
What:
- `permission:orders.manage`: Order Manager group 304–311 (add to its prefix chain); the Order Management block 623–646 (`orders.generate-stream`, `orders.compare`, `orders.compare.difference`, `Route::resource('orders')`, the `orders/{order}/*` routes, the `order-items.*` routes, `orders.bulk-update`, `orders.auto-approve-safe`, `products.update-priority`); the three `fruit-veg.orders*` routes inside the F&V group (423–425) get an inner `Route::middleware('permission:orders.manage')->group`.
- `role:admin`: the mockup routes 610–621 (`orders.mockups`, `orders.mockup.*`, `orders.mockup.vico-live`). Keep them above `Route::resource('orders')` as now.
Check: `php artisan route:list --path=orders --json` shows `permission:orders.manage` or `role:admin` on every row; `--path=order-manager` and `--name=fruit-veg.orders` likewise.

### 7. Kitchen, coffee, categories
Files: `routes/web.php`
What:
- `permission:kitchen.manage`: add to the `Route::prefix('kitchen')` chain (461).
- `permission:coffee.manage`: add to the `Route::prefix('coffee')` chain (441).
- Categories (526–538): GET `index`, `show`, `products`, `sales`, `sales.data`, `sales.product.daily`, `dashboard.data`, `product-image` → `permission:categories.view`; the two POST toggles → `permission:categories.manage`.
Check: `php artisan route:list --path=kitchen --json` all rows `permission:kitchen.manage`; `--path=coffee` all `permission:coffee.manage`; `--path=categories` rows split as above.

### 8. Labels and fruit & veg
Files: `routes/web.php`
What, labels (313–384):
- `permission:labels.print`: `labels.index`, `labels.zebra`, `labels.shelf-labels`, `labels.print-a4`, `labels.preview-a4`, `labels.preview`, `labels.requeue`, `labels.lookup-barcode`, `labels.scan`, `labels.test-print`, `labels.save-zpl`, `labels.print-zpl`, `labels.printer-queue`, `labels.translation-history`, `labels.edit-label`, all `labels.translate*` (364–371), and inside the `zebra-labels` prefix: `index`, `lookup-product`, `show`, `print`.
- `permission:labels.manage`: `labels.clear-all`, `labels.restore-batch`, `labels.zpl-debug`, `labels.barcode-scan-test`, `labels.camera-test`, `labels.camera-upload`, `labels.camera-test2`, `labels.camera-upload2`, `labels.camera-upload2-redirect`, `labels.regenerate-zpl`, and `zebra-labels.create/store/destroy/update-copies/update-fields`. Change `labels.printer-cancel` (357) from `permission:deliveries.manage` to `permission:labels.manage`.
Fruit & veg (386–439): keep the prefix group; inside it, three inner groups: `permission:fruit_veg.operate` for `index`, `availability*`, `labels*`, `countries`, `units`, `classes`, `search`, `quick-search`, `product-image`, `harvest*`, `waste*`; `permission:fruit_veg.manage` for `prices*`, `manage`, `display.update`, `country.update`, `unit.update`, `class.update`, `product.edit`, `product.update-image`, `sales*`, `price-sync*`; `permission:orders.manage` for `orders*` (step 6). Employees hold both `operate` and `manage`, so this is a split for later, not a loss now.
Check: `php artisan route:list --path=labels --json` every row has `permission:labels.print` or `permission:labels.manage`; `--path=fruit-veg` every row has one of the three; `php artisan test --filter="LabelTranslationSaveTest|DeliveryTranslatedLabelPrintingTest"` result unchanged from baseline (see Verification 5).

### 9. Deliveries: new system to managers, legacy to employees
Files: `routes/web.php` (540–608)
What: two wraps, no reordering and no change to `Route::resource('deliveries')`:
- `Route::middleware('permission:deliveries.manage')->group(...)` around everything from line 541 (`Route::resource('deliveries', ...)`) to line 583 (the last `barrel-codes` route): the resource, all `deliveries.*` extras, `delivery-items.refresh-barcode`, `deliveries.documents.index`, all `delivery-documents.*`, all `barrel-codes.*`. Managers hold `deliveries.manage`; `deliveries.create` is not used as a route gate (managers hold it anyway).
- Add `->middleware('permission:deliveries.process')` to the `Route::prefix('delivery-legacy')` chain at 586. Its two `undo-*` routes keep their existing `permission:deliveries.manage`, which now stacks on top (both must pass; a manager passes both).
Check: `php artisan route:list --path=deliveries --json`, `--path=delivery-documents`, `--path=barrel-codes`, `--path=delivery-items` show `permission:deliveries.manage` on every row; `--path=delivery-legacy` shows `permission:deliveries.process` on every row (and `deliveries.manage` as well on the two undo routes); `php artisan route:list --name=deliveries.` still lists index, create, store, show, edit, update, destroy; `php artisan test --filter="CustomerRequestDeliveryFlagTest|DeliveryTranslatedLabelPrintingTest"` result unchanged from baseline.

### 9b. Shop home tile points at the legacy flow
Files: `config/shop.php`, `app/Http/Controllers/Shop/ShopHomeController.php`, `tests/Feature/Shop/ShopHomeTest.php`
What: the cycle-1 "Receive delivery" tile links to `deliveries.index`, which employees can no longer open. Change that tile to `'route' => 'delivery-legacy.index'`, `'hint' => 'Scan a delivery in'` (unchanged), `'badge' => null`, `'permissions' => ['deliveries.process']` (unchanged). Remove the `'deliveries'` branch from `ShopHomeController::badgeCount()` and the now-unused `Delivery` import. Replace `test_deliveries_tile_shows_a_badge_for_active_deliveries` in `ShopHomeTest` with `test_deliveries_tile_links_to_the_legacy_delivery_screen`: an employee with `deliveries.process` sees a tile whose `href` is `route('delivery-legacy.index')` and the response does not contain `aria-label="` followed by `waiting"` for that tile (assert `assertDontSee('shop-tile__badge', false)` is enough while no other tile carries a badge in the test). This is the one permitted test rewrite beyond step 11; it follows a behaviour change the owner asked for.
Check: `php artisan test --filter=ShopHomeTest` green; `grep -n "deliveries.index" config/shop.php` prints nothing.

### 10. Views: hide what would 403
Files: `resources/views/layouts/admin.blade.php`, `resources/views/labels/index.blade.php`, `resources/views/fruit-veg/index.blade.php`, `resources/views/dashboard.blade.php`, `resources/views/deliveries/show.blade.php`
What (wrapper conditions only; no markup moves):
- `admin.blade.php`: Order Management section: change the `@unless(auth()->user()->hasRole('barista'))` at 386 (and its `@endunless` at 422) to `@if(auth()->user()->can('orders.manage') || auth()->user()->can('deliveries.manage') || auth()->user()->can('deliveries.process'))` … `@endif`, and wrap the three links individually: `orders.index` (398) in `can('orders.manage')`, `deliveries.index` (406) in `can('deliveries.manage')`, `delivery-legacy.index` (414) in `can('deliveries.process')`. An employee then sees the section with only the legacy delivery link. Stock section: 624/678 → `@if(auth()->user()->can('stocking.scan'))` … `@endif`. System Tools: 681/718 → `@if(auth()->user()->can('sales.import_data') || auth()->user()->isAdmin())` … `@endif`, and wrap the Sales Import link (693–699) in `@if(auth()->user()->can('sales.import_data'))`. Administration: wrap the Settings link (743–752 block) with `@if(auth()->user()->can('settings.view'))` instead of `@unless(barista)`, and change the section's opening condition so the header is not shown empty: `@if(auth()->user()->can('users.view') || auth()->user()->can('settings.view') || auth()->user()->isAdmin())`. Operations, Customer, Voucher, Kitchen, Stock Monitoring, Financial, Revenue: unchanged.
- `labels/index.blade.php`: wrap the Clear All button (159 and its closing tag) and each Restore All button (327–332) in `@if(auth()->user()->can('labels.manage'))`. Leave the JS functions in place.
- `fruit-veg/index.blade.php`: wrap the order-generation card/link at 135 in `@if(auth()->user()->can('orders.manage'))`.
- `dashboard.blade.php`: wrap the Invoice Bulk Upload and Supplier Outstanding Report quick links, and the Amazon pending banner, in `@if(auth()->user()->can('invoices.manage'))`.
- `delivery-legacy/match.blade.php`: wrap each of the fourteen `<a href="{{ route('products.edit', $item->productID) }}" …>…</a>` elements (lines 861, 974, 1099, 1234, 1400, 1527, 1689, 1769, 1907, 1961, 2020, 2108, 2215, 2300; each anchor spans a few lines to its `</a>`) in `@if(auth()->user()->can('products.edit'))` … `@endif`. Touch nothing else in that file.
Check: `php artisan view:cache && php artisan view:clear` succeeds; `grep -c "@can" resources/views/layouts/admin.blade.php resources/views/labels/index.blade.php resources/views/fruit-veg/index.blade.php resources/views/dashboard.blade.php resources/views/delivery-legacy/match.blade.php` prints `0` for each (no Gate-based `@can`); `grep -c "can('products.edit')" resources/views/delivery-legacy/match.blade.php` → 14; `grep -rl "x-admin-layout" resources/views | wc -l` → 192; `git diff --stat resources/views/deliveries/` → empty.

### 11. Tests keep a role: factory state and 17 files
Files: `database/factories/UserFactory.php`, the 17 test files listed in Context
What: add to `UserFactory`:
```php
public function withRole(string $name): static
{
    return $this->state(fn () => [
        'role_id' => \App\Models\Role::firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)])->id,
    ]);
}
```
Then, in each of the 17 files, change `User::factory()->create(` to `User::factory()->withRole('admin')->create(` **only where that user is then used with `actingAs` on a route this cycle gates**. Leave untouched any factory call used for a "requires authentication" guest test or for a user that is only stored as data. Read each file; do not sed blindly. Admin is chosen because `hasAnyPermission()` short-circuits for admin, so no permission rows are needed. `ProductTest`'s "products page requires authentication" and `WasteLogTest`'s "entry requires authentication" must keep asserting the guest redirect.
Check: `php artisan test --filter="Kitchen|FruitVeg|WasteLog|LabelTranslation|OrderDifference|TestScraper|ProductTest|ProductSearchApi"` shows exactly the same failing test names as the baseline run in Verification 5 and no new ones; in particular every test that failed with a 403 during your work must be green or back to its baseline failure.

### 12. Tests: the permission matrix and the seeder/migration
Files: `tests/Feature/Shop/RoutePermissionsTest.php (new)`, `tests/Feature/Shop/RolePermissionGrantsTest.php (new)`
What:
- `RoutePermissionsTest` (`RefreshDatabase`): build users with `User::factory()->withRole('employee')`, `withRole('barista')`, `withRole('manager')`, `withRole('admin')`; before acting, seed the roles' permissions by calling `$this->seed(RolesAndPermissionsSeeder::class)` **once in `setUp`** so employee/manager carry their real grants (the seeder is idempotent and `firstOrCreate`s the roles the factory state will reuse). A data-provider matrix of `[role, method, uri, expectation]` where expectation is `403` or `open`. For `open`, assert `$response->status() !== 403` (many pages need POS tables and would 500; a 500 here is not this cycle's concern, a 403 is). Include at least: employee → 403 on `/orders`, `/order-manager`, `/invoices`, `/rtd`, `/kitchen`, `/settings`, `/sales-import`, `/products/create`, `/products/1/edit`, `/deliveries`, `/deliveries/create`, `/barrel-codes`, `/categories/visibility/toggle` (POST), `/labels/clear-all` (POST), `/fruit-veg/orders`, `/tests/hub`, `/debug/stock`; employee → open on `/products`, `/stocking`, `/stock-review`, `/delivery-legacy`, `/delivery-legacy/match`, `/labels`, `/labels/translate`, `/fruit-veg/availability`, `/fruit-veg/prices`, `/till-review`, `/coffee`, `/categories`; barista → 403 on `/products`, `/stocking`, `/delivery-legacy`, `/labels`, `/fruit-veg/availability`, `/till-review`, `/coffee`, `/categories`, and open on `/kds`; manager → open on `/orders`, `/invoices`, `/rtd`, `/kitchen`, `/deliveries`, `/deliveries/create`, `/delivery-legacy`, `/products/create`, `/products/1/edit`, `/labels/clear-all` (POST), and 403 on `/settings`, `/sales-import`, `/tests/hub`, `/debug/stock`; admin → open on `/settings`, `/sales-import`, `/tests/hub`, `/debug/stock`. Include a guest case: `/orders` → redirect to `/login`.
- `RolePermissionGrantsTest` (`RefreshDatabase`): (a) seed `RolesAndPermissionsSeeder`; assert employee has `stocking.scan`, `fruit_veg.operate`, lacks `products.create`, `orders.manage`, `invoices.manage`, `kitchen.manage`; manager has all six; barista has only `kds.access`. (b) Migration path: seed the roles only (create the four `Role` rows), then `$migration = require base_path('database/migrations/2026_09_23_150000_add_shop_mode_permissions.php'); $migration->up();` and assert the same grants; call `->up()` a second time and assert no duplicate `role_permissions` rows (`syncWithoutDetaching` makes it idempotent); call `->down()` and assert the five permissions are gone and `products.create` still exists.
Check: `php artisan test --filter="RoutePermissionsTest|RolePermissionGrantsTest"` all green.

### 13. Format
Files: all PHP touched
What: `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean.

## Verification

Run in order and paste real output:

1. **No ungated authenticated route remains.** Save this as a one-off script (do not commit it) and run it:
```bash
php artisan route:list --json | python3 -c "
import json,sys
rows=json.load(sys.stdin)
open_=[r['name'] or r['uri'] for r in rows
  if 'Illuminate\\\\Auth\\\\Middleware\\\\Authenticate' in ' '.join(r['middleware'])
  and not any(m.startswith(('permission:','role:')) for m in r['middleware'])]
print('\n'.join(sorted(set(open_))))"
```
Expected output is exactly this set (order aside): `dashboard`, `logout`, `password.confirm`, `password.update`, `profile.destroy`, `profile.edit`, `profile.update`, `roles.test`, `shop.home`, `ui-mode.set`, `verification.notice`, `verification.send`, `verification.verify`. Anything else listed is a route this cycle missed; gate it and rerun. (`kds.*` do not appear because they carry `permission:kds.access`; `customer-requests.index`, `/`, `auth.check` and the login routes are not behind `auth`.)
2. `php artisan migrate` on the dev DB → the new migration runs once; `php artisan tinker --execute="echo \App\Models\Permission::count();"` is the previous count + 5.
3. `php artisan test --filter="Shop|Auth|CashBagVerificationEditTest|CustomerRequest|Kds|InvoiceBulkUpload|UdeaPalletVolumeSync"` → all green.
4. `php artisan test` → **17 failed, N passed**, and the 17 failing test names are identical to the baseline list in Context (7 `UdeaScrapingServiceTest`, 3 `CashReconciliationTest`, 2 `WasteLogTest`, 2 `ProductTest`, 2 `FruitVegLabelPrintingTest`, 1 `TestScraperControllerTest`), with the same failure messages (constraint clash, 302, `no such table: CATEGORIES`, content). A new failure or a changed message means a gate broke something; a 403 in any message is yours to fix.
5. Baseline capture for step 11 and item 4: before touching anything, run `php artisan test --filter="Kitchen|FruitVeg|WasteLog|LabelTranslation|OrderDifference|TestScraper|ProductTest|ProductSearchApi|Delivery|CustomerRequestDeliveryFlag" 2>&1 | grep -E "✓|⨯"` and paste it in `implemented.md` under Baseline; rerun at the end and diff.
6. `grep -rl "x-admin-layout" resources/views | wc -l` → 192; `git diff --stat resources/views/kds/` → empty; `grep -c "@can" resources/views/layouts/admin.blade.php` → 0.
7. `./vendor/bin/pint --test --dirty` → clean.
8. Manual (browser, dev server): sign in as an employee, open Office; the sidebar shows no Stock Monitoring, Financial, Revenue, Kitchen or System Tools headers, the Order Management section shows only "Delivery Legacy", and the Stock section is present; type `/orders` → 403 page; `/deliveries` → 403; `/stocking` → works; `/delivery-legacy/match` → works and its product-edit pop-out links are absent. On `/shop` the "Receive delivery" tile opens `/delivery-legacy`. Sign in as the barista: `/products` → 403, `/kds` → works, `/shop` shows only Coffee orders. Sign in as admin: everything as before, including `/settings` and `/sales-import`.

## Risks

- **Route order.** Literal paths (`products/create`, `products/suppliers`, `products/udea-pricing`, `orders/compare`, `orders/mockup*`, `invoices/*` specifics) must stay registered before their `{param}` siblings. Wrapping in groups changes nothing as long as the wrapped lines keep their relative order; step 3 (products) is the only place where lines are regrouped by hand.
- **`@can` is a trap.** Blade `@can` consults the Gate, which has no definitions here; only `auth()->user()->can()` reaches the custom table. The step 10 check greps for it.
- **Seeder/migration drift.** A permission granted in the seeder but not created there throws `ModelNotFoundException` in `CashBagVerificationEditTest`. Step 2's check catches it.
- **Employee workflows.** Owner-confirmed: employees receive deliveries through the legacy flow only, so the whole new delivery system goes to managers and employees do not get `products.create`. The legacy match screen's product-edit pop-outs are hidden for employees rather than granted; if staff turn out to need them, granting `products.edit` to employees is a one-line seeder/migration change.
- **Cycle 1's Home tiles** still use existing permission names (`products.view`, `fruit_veg.manage`); they keep working. Switching the stock-scan tile to `stocking.scan` is cycle 3's job.
- **Tests that seed the real seeder** now grant more; nothing asserts the old grant set.
- **Production rollout.** `php artisan migrate` on the server applies the grants to the live roles. Until it runs, employees on the live site would hit 403 on stocking and F&V pages, so deploy the migration together with the route changes, never the routes alone.

## Review

Reviewed 2026-09-23 by the Planner against `implemented.md`, the diff of every changed file, the new tests, and a rerun of the verification commands.

Criteria:
1. Migration — PASS. Five permissions, grants guarded per role, `products.create` to manager only, `down()` removes only what it created; verified on the dev DB (`Permission::count()` 40 → 45) and by `RolePermissionGrantsTest` (up, up twice, down).
2. Seeder — PASS. Definitions and grants match the migration; `CashBagVerificationEditTest`, which seeds it, passes.
3–9. Route gating — PASS. Route names in `web.php` are identical to HEAD apart from cycle 1's two additions; `Route::` line count 625 → 627 for the same reason, so nothing was lost. Every group wrapper the plan named is present; literal product paths still precede `products/{id}`; `deliveries.*` including documents and barrel codes carry `deliveries.manage`, `delivery-legacy.*` carries `deliveries.process` with the two undo routes stacking `deliveries.manage`; `labels.printer-cancel` moved to `labels.manage`.
9b. Home tile — PASS. Links to `delivery-legacy.index`, badge removed, controller branch and import removed, test rewritten as specified.
10. Views — PASS. All gates use `auth()->user()->can()`; zero `@can` in the five files; 14 `products.edit` gates in the legacy match screen; `x-admin-layout` count 192; `resources/views/kds/` and `resources/views/deliveries/` untouched.
11. Tests keep a role — PASS. `withRole()` state added; 42 factory calls in 17 files switched, guest tests untouched; the affected classes fail with exactly the baseline set (7) and no 403 anywhere.
12. Matrix and grants tests — PASS. 63 tests, 92 assertions, green on rerun.
13. Pint — PASS (39 files).

Verification rerun by the Planner: full suite 17 failed / 483 passed, the same 17 tests as before cycle 1 and 2, none mentioning 403; open-route audit with the class-name matcher agrees with the report (the plan's `permission:` string matcher was wrong, Deviation 1 accepted); screenshots rendered as an employee through the app show the sidebar reduced to Operations, Customer, Vouchers, Order Management (Delivery Legacy only) and Stock, the dashboard quick links reduced to Fruit & Veg, the labels hub intact, the legacy delivery list working, and `/orders` returning the framework's plain 403 page.

Deviations 1–5: all accepted. Deviation 2 (`routes/api.php` gated to match the same controllers' decisions) and Deviation 5 (`till_review.view` added to the seeder to fix a real fresh-install drift) are improvements the plan should have contained. Deviation 4 (plain-text fallback instead of deleting product names) is the correct reading of the constraint.

Decisions on Notes for Planner:
- `api/user` stays ungated: it returns only the caller's own record.
- `api/test-scraper/debug-search-raw` has no authentication at all. Not this cycle's scope; carried forward as a follow-up to gate with `role:admin` or delete, owner to say which.
- Remaining seeder/migration drift for `till_review.export` and `cash_reconciliation.*`: follow-up, one seeder block; it only affects fresh installs.
- Manager loses `/settings` and `/sales-import`: intended; two seeder lines if the owner wants them back.
- `withRole()` creating a missing role: accepted; typo risk is small and the matrix test seeds the real roles.
- The framework's default 403 page is bare. A friendlier page inside the shop shell is a candidate for a later cycle, not a blocker.
- Cycle 1's stock-scan tile still gates on `products.view`; cycle 3 switches it to `stocking.scan`.

Deploy note for the owner: `php artisan migrate` must ship together with these route changes; the routes alone would lock employees out of stocking and fruit & veg.

### Addendum review (after the owner's two decisions and the dead-endpoint deletion)

Verified by the Planner on the diff and by rerunning the checks:
- `routes/api.php`: the unauthenticated `test-scraper` group is gone; `debug-search-raw` now sits in the `['auth:web', 'role:admin']` group with a comment explaining why (it signs in to the supplier portal with the shop's credentials). Every one of the 30 `api/*` routes now carries an authentication middleware.
- `GET /api/user` left ungated by the owner's decision; it returns only the caller's own record.
- Deleted `debugSearch()` and `queueScraping()` from `TestScraperController` (42 lines) and their two routes. `grep -rnw` finds no remaining reference in `app`, `resources`, `routes` or `tests`. All seven `fetch('/api/test-scraper/…')` targets in `resources/views/tests/` still resolve to a route.
- `pint --test --dirty` clean; full suite 17 failed / 483 passed, the same 17 as every run since before cycle 1; `TestScraperControllerTest` unchanged (its one failure is the pre-existing content assertion).
- Accepted. The implementer's self-correction (three of the four "probably dead" endpoints were live) is exactly the caution the protocol asks for.

Orphaned code the implementer stopped at, now a decision for the owner: `UdeaScrapingService::queueProductScraping()` (line 1162), `IndependentScrapingService::queueProductScraping()` (line 390) and `app/Jobs/ScrapeProductDataJob.php` (130 lines) have no callers left. Planner recommendation: delete all three in a small housekeeping task rather than folding it into a Shop mode cycle; no test references them.

Result: ACCEPTED (unchanged by the addendum). Owner: `mkdir -p docs/planImp/archive/2026-09-23-shop-mode-cycle-2 && mv docs/planImp/plan.md docs/planImp/implemented.md docs/planImp/archive/2026-09-23-shop-mode-cycle-2/`. Next cycle: Stock scan (screen 02).
