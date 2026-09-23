# Shop mode cycle 2 — access hardening — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-23

## Baseline

HEAD: `fc5820dc` (cycle 1 is still uncommitted; its files are the baseline, as the plan says)

Pre-existing dirty files at start (cycle 1's work plus the archive move — **not** this cycle's):
```
 M app/Http/Controllers/Auth/AuthenticatedSessionController.php
 M app/Http/Controllers/Auth/RegisteredUserController.php
 M app/Providers/AppServiceProvider.php
 M bootstrap/app.php
R  docs/planImp/implemented.md -> docs/planImp/archive/2026-09-23-kds-modifier-badges/implemented.md
RM docs/planImp/plan.md -> docs/planImp/archive/2026-09-23-kds-modifier-badges/plan.md
 M resources/views/layouts/admin.blade.php
 M routes/web.php
 M vite.config.js
?? app/Http/Controllers/DashboardController.php
?? app/Http/Controllers/Shop/
?? app/Http/Controllers/UiModeController.php
?? app/Http/Middleware/ShareUiMode.php
?? app/Support/UiMode.php
?? app/View/Components/ShopLayout.php
?? config/shop.php
?? docs/design/
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-1/
?? docs/planImp/plan.md
?? public/images/shop-icons.svg
?? resources/css/shop.css
?? resources/js/shop.js
?? resources/views/components/shop/
?? resources/views/layouts/shop.blade.php
?? resources/views/shop/
?? tests/Feature/Shop/
```

`Permission::count()` before: **40**. `grep -rl "x-admin-layout" resources/views | wc -l`: **192**.

### Ungated authenticated routes, before

**The plan's Verification 1 script does not work as written** — see Deviation 1. `route:list --json`
resolves middleware *aliases* to class names, so a gate reads as
`App\Http\Middleware\PermissionMiddleware:x`, never `permission:x`. The plan's matcher therefore
reports every route as ungated, including ones the plan itself calls already-gated
(`cash-reconciliation.*`, `stocking.logs`). With the matcher corrected, the real baseline is:

```
$ git stash push -- routes/web.php     # back to HEAD's routes for a clean measurement
$ php artisan route:list --json | python3 open-routes.py | wc -l
489
$ git stash pop
```

**489** authenticated-but-ungated routes at `fc5820dc`. (Cycle 1 adds two more, `shop.home` and
`ui-mode.set`, both in the expected-open set.)

### Test baseline for step 11 / Verification 5

`php artisan test --filter="Kitchen|FruitVeg|WasteLog|LabelTranslation|OrderDifference|TestScraper|ProductTest|ProductSearchApi|Delivery|CustomerRequestDeliveryFlag"`
→ **7 failed, 167 passed (645 assertions)**. The 7:
```
⨯ print labels clears queue and records batch          (FruitVegLabelPrintingTest)
⨯ restore last printed batch requeues products         (FruitVegLabelPrintingTest)
⨯ shows 404 for non existent product                   (ProductTest)
⨯ product statistics are displayed                     (ProductTest)
⨯ guzzle test page displays with data                  (TestScraperControllerTest)
⨯ entry updates the same day row instead of duplicating (WasteLogTest)
⨯ zero quantity deletes the entry                      (WasteLogTest)
```
Full-suite baseline (from cycle 1's verification, unchanged): **17 failed, 420 passed**.

## Steps

### 1. New permissions and grants: migration — done
Changed: `database/migrations/2026_09_23_150000_add_shop_mode_permissions.php` (new)
Check output:
```
$ php artisan migrate
  2026_09_23_150000_add_shop_mode_permissions .................. 178.43ms DONE

$ php artisan tinker   (one call, all six permissions)
stocking.scan        employee=yes manager=yes admin=yes
fruit_veg.operate    employee=yes manager=yes admin=yes
orders.manage        employee=no  manager=yes admin=yes
invoices.manage      employee=no  manager=yes admin=yes
kitchen.manage       employee=no  manager=yes admin=yes
products.create      employee=no  manager=yes admin=yes
Permission::count() = 45
```

### 2. Seeder in step with the migration — done
Changed: `database/seeders/RolesAndPermissionsSeeder.php` (5 definitions, 2 employee grants, 4 manager grants)
Check output:
```
$ php artisan db:seed --class=RolesAndPermissionsSeeder
   INFO  Seeding database.
Roles and permissions created successfully!

$ php artisan test --filter=CashBagVerificationEditTest
  Tests:    5 passed (20 assertions)
```

### 3. Products and product API — done
Changed: `routes/web.php`
Groups written in the order the plan prescribes so every literal `/products/*` path stays above
`/products/{id}`: view-literals (`index`, `suppliers`, `udea-pricing`) → create → admin literals
(`independent-test`, `search-test`) → view-`{id}` → edit-`{id}` → admin tools. The four
`api.products.*` are in their own `products.view` group.
Check output:
```
$ php artisan route:list --path=products   (ordering preserved)
  GET|HEAD  products/create ....... products.create
  GET|HEAD  products/independent-test ...
  GET|HEAD  products/search-test ...
  GET|HEAD  products/suppliers ...
  GET|HEAD  products/udea-pricing ...
  GET|HEAD  products/{id} ............. products.show
  PUT       products/{id} ......... products.update

$ (gates, alias-corrected matcher)
GET     products                     ['permission:products.view']
POST    products                     ['permission:products.create']
GET     products/create              ['permission:products.create']
GET     products/{id}                ['permission:products.view']
GET     products/{id}/edit           ['permission:products.edit']
GET     api/products/search          ['permission:products.view']
```

### 4. Stock, lookup, till review, settings, sales import, tests, debug — done
Changed: `routes/web.php`
Check output:
```
GET     stocking                     ['permission:stocking.scan']
GET     stocking/logs                ['permission:stocking.scan', 'role:admin']   (stacked, as intended)
POST    stocking/lookup              ['permission:stocking.scan']
GET     destock-review               ['permission:stocking.scan']
GET     supplier-code-lookup         ['permission:products.view']
GET     debug/stock                  ['role:admin']
GET     debug/suppliers              ['role:admin']
GET     debug/product-suppliers      ['role:admin']
GET     settings                     ['permission:settings.view']
GET     sales-import                 ['permission:sales.import_data']
GET     tests/client                 ['role:admin']
GET     till-review                  ['permission:till_review.view']
```

### 5. Accounting: invoices, RTD, suppliers — done
Changed: `routes/web.php` (one `permission:invoices.manage` group, 140 lines, internal order untouched)
Check output — rows carrying `invoices.manage`, and no UNGATED row in any of these paths:
```
invoices: 72   rtd: 32   suppliers: 28   vat-rates: 4   amazon-pending: 12
UNGATED in those paths: none
```

### 6. Ordering — done
Changed: `routes/web.php`
Check output:
```
GET     fruit-veg/orders             ['permission:orders.manage']
POST    fruit-veg/orders             ['permission:orders.manage']
POST    fruit-veg/orders/generate-stream ['permission:orders.manage']
GET     orders/mockup/1-charts       ['role:admin']
UNGATED rows matching "order": only kitchen/* and deliveries/* (steps 7 and 9)
```

### 7. Kitchen, coffee, categories — done
Changed: `routes/web.php`
Check output:
```
UNGATED under kitchen: none (all permission:kitchen.manage)
UNGATED under coffee:  none (all permission:coffee.manage)
GET     categories                              ['permission:categories.view']
GET     categories/{category}/sales/data        ['permission:categories.view']
GET     categories/product-image/{code}         ['permission:categories.view']
POST    categories/visibility/toggle            ['permission:categories.manage']
POST    categories/category-visibility/toggle   ['permission:categories.manage']
```

### 8. Labels and fruit & veg — done
Changed: `routes/web.php` (43 label routes, 42 fruit-veg routes, gated per route so the
interleaved print/manage and operate/manage splits need no reordering)
`labels.printer-cancel` moved from `permission:deliveries.manage` to `permission:labels.manage`.
Check output:
```
labels rows not carrying labels.print/labels.manage: none
fruit-veg rows not carrying fruit_veg.operate / fruit_veg.manage / orders.manage: none  (45 rows total)
```

### 9. Deliveries: new system to managers, legacy to employees — done
Changed: `routes/web.php`
Check output:
```
UNGATED under "deliver"/"barrel" in web.php: none
GET     delivery-legacy                  ['permission:deliveries.process']
GET     delivery-legacy/match            ['permission:deliveries.process']
POST    delivery-legacy/undo-complete    ['permission:deliveries.process', 'permission:deliveries.manage']
POST    delivery-legacy/undo-last-print  ['permission:deliveries.process', 'permission:deliveries.manage']

$ resource names intact:
deliveries.index deliveries.create deliveries.store deliveries.show
deliveries.edit  deliveries.update deliveries.destroy
```

### 9c. `routes/api.php` — gated (NOT in the plan's steps; see Deviation 2) — done
Changed: `routes/api.php`
The plan's steps never mention `routes/api.php`, but its Verification 1 expects those routes gone
from the open list and says "Anything else listed is a route this cycle missed; gate it and rerun".
Gated to match the plan's own decisions for the same controllers:
`api/test-scraper/*` → `role:admin` (as step 4 does for the `tests` prefix / TestScraperController),
`api/deliveries/*` → `permission:deliveries.manage` (as step 9 does for the new delivery system).
```
POST    api/deliveries/{delivery}/scan     ['permission:deliveries.manage']
GET     api/test-scraper/test-api          ['role:admin']
```
`api/user` (sanctum whoami) left alone — see Deviation 3.

### 9b. Shop home tile points at the legacy flow — done
Changed: `config/shop.php`, `app/Http/Controllers/Shop/ShopHomeController.php`, `tests/Feature/Shop/ShopHomeTest.php`
Check output:
```
$ grep -n "deliveries.index" config/shop.php
(no output)

$ php artisan test --filter=ShopHomeTest
  ✓ deliveries tile links to the legacy delivery screen
  Tests:    7 passed (22 assertions)
```

### 10. Views: hide what would 403 — in progress
### 10. Views: hide what would 403 — done
Changed: `resources/views/layouts/admin.blade.php`, `labels/index.blade.php`, `fruit-veg/index.blade.php`,
`dashboard.blade.php`, `delivery-legacy/match.blade.php`
- `admin.blade.php`: Order Management section condition → `can('orders.manage') || can('deliveries.manage') || can('deliveries.process')`, and the three links wrapped individually (`orders.manage` / `deliveries.manage` / `deliveries.process`); Stock → `can('stocking.scan')`; System Tools → `can('sales.import_data') || isAdmin()` with the Sales Import link wrapped in `can('sales.import_data')`; Administration header → `can('users.view') || can('settings.view') || isAdmin()` with the Settings link wrapped in `can('settings.view')`.
- `labels/index.blade.php`: Clear All button and the Restore All button wrapped in `can('labels.manage')`; the JS functions left alone.
- `fruit-veg/index.blade.php`: the Generate Order card wrapped in `can('orders.manage')`.
- `dashboard.blade.php`: the Amazon pending banner condition and the two quick links wrapped in `can('invoices.manage')` (3 gates).
- `delivery-legacy/match.blade.php`: all 14 `products.edit` pop-outs gated — **with a plain-text fallback, see Deviation 4**.
Check output:
```
$ php artisan view:cache
   INFO  Blade templates cached successfully.

$ grep -c "@can" <each of the five files>
0 resources/views/layouts/admin.blade.php
0 resources/views/labels/index.blade.php
0 resources/views/fruit-veg/index.blade.php
0 resources/views/dashboard.blade.php
0 resources/views/delivery-legacy/match.blade.php

$ grep -c "can('products.edit')" resources/views/delivery-legacy/match.blade.php
14
$ grep -rl "x-admin-layout" resources/views | wc -l
192
$ git diff --stat resources/views/deliveries/     → empty
$ git diff --stat resources/views/kds/            → empty
```

### 11. Tests keep a role: factory state and 17 files — done
Changed: `database/factories/UserFactory.php` (`withRole()`), plus the 17 test files.
Every factory call in those files was read first: all 42 are passed straight to `actingAs` on a route
this cycle gates, and the "requires authentication" tests create no user at all, so none of them
changed meaning.
```
 6  tests/Feature/KitchenIngredientProfileEditReturnTest.php
 3  tests/Feature/KitchenIngredientProfileStoreTest.php
 1  tests/Feature/KitchenOrderCreatePageTest.php
 1  tests/Feature/KitchenOrderCsvTest.php
 1  tests/Feature/KitchenOrderHistoryTest.php
 1  tests/Feature/KitchenOrderStoreTest.php
 7  tests/Feature/KitchenOrganicRegistrationTest.php
 1  tests/Feature/KitchenRecipeScalingTest.php
 1  tests/Feature/KitchenStandingOrderTest.php
 2  tests/Feature/FruitVegLabelPrintingTest.php
 1  tests/Feature/FruitVegProductImageTest.php
 6  tests/Feature/WasteLogTest.php
 1  tests/Feature/LabelTranslationSaveTest.php
 1  tests/Feature/OrderDifferenceCreationTest.php
 1  tests/Feature/TestScraperControllerTest.php
 7  tests/Feature/ProductTest.php
 1  tests/Feature/ProductSearchApiTest.php
total replaced: 42 in 17 files
```
Check output — the failing set is byte-identical to the baseline, and nothing anywhere failed on a 403:
```
$ php artisan test --filter="Kitchen|FruitVeg|WasteLog|LabelTranslation|OrderDifference|TestScraper|ProductTest|ProductSearchApi|Delivery|CustomerRequestDeliveryFlag"
  ⨯ print labels clears queue and records batch
  ⨯ restore last printed batch requeues products
  ⨯ shows 404 for non existent product
  ⨯ product statistics are displayed
  ⨯ guzzle test page displays with data
  ⨯ entry updates the same day row instead of duplicating
  ⨯ zero quantity deletes the entry
  Tests:    7 failed, 168 passed (649 assertions)      (baseline: 7 failed, 167 passed)

$ diff <(baseline failures) <(current failures)
(no differences)  →  IDENTICAL failure set

$ grep -ciE "403|forbidden" on that run's output   →  0
```

### 12. Tests: the permission matrix and the seeder/migration — done
Changed: `tests/Feature/Shop/RoutePermissionsTest.php` (new), `tests/Feature/Shop/RolePermissionGrantsTest.php` (new)
`RoutePermissionsTest` seeds `RolesAndPermissionsSeeder` in `setUp` and runs a named data-provider
matrix of 57 cases plus a guest case. `RolePermissionGrantsTest` covers the seeder grants and the
migration path (`up()`, `up()` twice for idempotence, `down()`).
Check output:
```
$ php artisan test --filter="RoutePermissionsTest|RolePermissionGrantsTest"
  Tests:    63 passed (92 assertions)
```
The matrix caught a real pre-existing defect on its first run — see Deviation 5.

### 13. Format — done
```
$ ./vendor/bin/pint --dirty
  PASS   39 files
$ ./vendor/bin/pint --test --dirty
  PASS   39 files
```

## Deviations

**1. The plan's Verification 1 script (and every step's `permission:`/`role:` grep) does not work as written.**
`php artisan route:list --json` resolves middleware *aliases* to class names, so a gate appears as
`App\Http\Middleware\PermissionMiddleware:products.view`, never `permission:products.view`. With the
plan's matcher, every route in the app reads as ungated — including ones the plan itself calls
already-gated (`cash-reconciliation.*`, `stocking.logs`, `management.*`). I corrected the matcher to
test for the two middleware class names and used that throughout. Nothing about the *work* changed;
only the check. The plan's own baseline figure would have been 680; the true baseline is **489**.

**2. `routes/api.php` was not in any step, but Verification 1 requires it.**
14 routes there carry `auth:web`/`auth:sanctum` and no gate, so they would all have shown up in
Verification 1's output. The plan's steps never mention the file, but Verification 1 says
"Anything else listed is a route this cycle missed; gate it and rerun", so I gated them to match the
plan's own decisions for the same controllers: `api/test-scraper/*` → `role:admin` (step 4 puts the
`tests` prefix and TestScraperController behind `role:admin`), `api/deliveries/*` →
`permission:deliveries.manage` (step 9 puts the new delivery system there). Recorded as step 9c above.
`api/test-scraper/debug-search-raw` had no auth at all, so it never appears in that list; the owner
decided it after the fact — see Addendum below.

**3. `api/user` left ungated, so Verification 1's output has 16 names, not 13.**
The three extra names are `GET api/user`, `POST confirm-password` and `logout.get`.
`confirm-password` (POST) and `logout.get` are Breeze routes in `routes/auth.php` — squarely the
"shell/profile/auth set" the Goal exempts; the plan's expected list just omitted them (it lists
`password.confirm`/`password.update`, the GET and PUT, but the POST is unnamed so it prints as a URI).
`api/user` is the sanctum whoami endpoint: it returns only the caller's own record and grants access
to nothing, and the plan made no decision about it, so putting a business permission on it would be a
behaviour change beyond the plan. **Flagging rather than deciding — say if you want it gated.**

**4. The 14 `products.edit` pop-outs keep their text for employees.**
Wrapping each anchor in a bare `@if(can('products.edit')) … @endif` as written would delete the
product name (and in one case the barcode) from the legacy match screen for employees, because the
anchor's *text* is the product name. Nine of the fourteen also sit inside an existing
`@if($item->productID) … @else … @endif`, so the cell would simply render empty. That collides with
the plan's own Constraint ("Employees must not lose a task they use on the floor") — the legacy match
screen is the employee's main tool. Smallest fix: each anchor is now
`@if(can('products.edit')) <a …>NAME</a> @else <span class="…">NAME</span> @endif`, with the
indigo link classes stripped from the span. Verification 8's requirement ("its product-edit pop-out
links are absent") still holds — the link is gone, the text stays.

**5. Added `till_review.view` to the seeder (one definition + one employee grant).**
`RoutePermissionsTest` failed on `employee get /till-review => open` the first time it ran, and the
cause is a pre-existing seeder/migration drift, not this cycle: `till_review.view` is created and
granted only by `2025_08_11_232011_add_cash_reconciliation_permissions`, whose grants are each guarded
by `if ($role)`. On a **fresh** database that migration runs before any role exists, so the grants
silently no-op and the seeder never makes up the difference. Confirmed both ways:
```
live employee till_review.view: yes      (migration ran when roles existed)
seeder defines till_review.view: no
```
So a freshly seeded production box would 403 employees out of till review — a page the plan's own
Context lists among employee permissions and whose Out-of-scope list says employees keep. I added the
permission definition *and* the employee grant to the seeder (manager inherits it via the existing
`array_merge`), which keeps the plan's Constraint that the seeder only grants what it creates. Two
lines of drift remain unaddressed — Notes 2.

## Verification

**1. No ungated authenticated route remains** (with the corrected matcher, Deviation 1):
```
GET api/user            ← Deviation 3
POST confirm-password   ← routes/auth.php, unnamed; Deviation 3
dashboard
logout
logout.get              ← routes/auth.php; Deviation 3
password.confirm
password.update
profile.destroy
profile.edit
profile.update
roles.test
shop.home
ui-mode.set
verification.notice
verification.send
verification.verify
```
**489 → 16.** All 13 of the plan's expected names are present and nothing else except the three in
Deviation 3, each of which belongs to the auth/shell set the Goal exempts.

**2. Migration and permission count:**
```
$ php artisan migrate
  2026_09_23_150000_add_shop_mode_permissions .................. 178.43ms DONE
$ php artisan tinker --execute="echo \App\Models\Permission::count();"
45
```
Baseline was 40, +5 new = 45. (`till_review.view` from Deviation 5 already existed as a row; only the
seeder definition and the employee grant were added, so the count is unaffected.)

**3.** `php artisan test --filter="Shop|Auth|CashBagVerificationEditTest|CustomerRequest|Kds|InvoiceBulkUpload|UdeaPalletVolumeSync"`
```
  ⨯ failed authentication returns null
   FAILED  Tests\Unit\UdeaScrapingServiceTest > fail…  BadMethodCallException
  Tests:    1 failed, 176 passed (607 assertions)
```
Not green, but the single failure is **not new**: it is one of the 17 pre-existing
`UdeaScrapingServiceTest` failures, pulled in because the filter string `Auth` matches the test name
`failed authentication returns null`. Everything the plan meant by this filter passes.

**4.** `php artisan test` → **17 failed, 483 passed (1763 assertions)**, 55.47s.
```
   FAILED  Tests\Unit\UdeaScrapingServiceTest        (7)   BadMethodCallException / InvalidCountException
   FAILED  Tests\Feature\CashReconciliationTest      (3)   BadMethodCallException
   FAILED  Tests\Feature\FruitVegLabelPrintingTest   (2)
   FAILED  Tests\Feature\ProductTest                 (2)
   FAILED  Tests\Feature\TestScraperControllerTest   (1)
   FAILED  Tests\Feature\WasteLogTest                (2)
```
Identical to the baseline list, test for test. Passing count rose 420 → 483 (the 63 new tests).
No failure anywhere in the suite mentions 403 or "forbidden".

**5.** Baseline vs. final for the affected classes: recorded under step 11 — same 7 failures, one more
test passing (168 vs 167) because `ShopHomeTest` gained a case.

**6.**
```
$ grep -rl "x-admin-layout" resources/views | wc -l   → 192
$ git diff --stat resources/views/kds/                → empty
$ grep -c "@can" resources/views/layouts/admin.blade.php → 0
```

**7.** `./vendor/bin/pint --test --dirty` → `PASS  39 files`.

**8. Manual walkthrough — driven through the real HTTP kernel, not a browser.**
No browser extension is connected to this session and I don't enter anyone's password, so I logged in
the four live dev users with `Auth::login()` (no credentials typed) and pushed real requests through
the kernel. Status per role (`403` = forbidden, everything else is the page's own response):

| path | employee | barista | manager | admin |
|---|---|---|---|---|
| `/dashboard` | 200 | 200 | 200 | 200 |
| `/shop` | 200 | 200 | 200 | 200 |
| `/orders` | **403** | **403** | 200 | 200 |
| `/deliveries` | **403** | **403** | 200 | 200 |
| `/delivery-legacy` | 200 | **403** | 200 | 200 |
| `/delivery-legacy/match` | 302 | **403** | 302 | 302 |
| `/stocking` | 200 | **403** | 200 | 200 |
| `/labels` | 200 | **403** | 200 | 200 |
| `/fruit-veg/availability` | 200 | **403** | 200 | 200 |
| `/fruit-veg/orders` | **403** | **403** | 200 | 200 |
| `/kitchen` | **403** | **403** | 200 | 200 |
| `/invoices` | **403** | **403** | 200 | 200 |
| `/settings` | **403** | **403** | **403** | 200 |
| `/sales-import` | **403** | **403** | **403** | 200 |
| `/kds` | 200 | 200 | 200 | 200 |
| `/products` | 200 | **403** | 200 | 200 |
| `/products/create` | **403** | **403** | 200 | 200 |
| `/till-review` | 200 | **403** | 200 | 200 |

The `302` on `/delivery-legacy/match` is the screen's own redirect with no active session —
`Location: http://osmanager.local/delivery-legacy`, not `/login` — so the gate passes.

Sidebar sections rendered on `/dashboard`:

| section | employee | barista | manager | admin |
|---|---|---|---|---|
| Operations | yes | – | yes | yes |
| Order Management | yes | – | yes | yes |
| — Orders | – | – | yes | yes |
| — Deliveries | – | – | yes | yes |
| — Delivery Legacy | **yes** | – | yes | yes |
| Stock | yes | – | yes | yes |
| Stock Monitoring | – | – | – | yes |
| System Tools | – | – | – | yes |
| — Sales Import | – | – | – | yes |
| Financial | – | – | yes | yes |
| Revenue | – | – | yes | yes |
| Kitchen | – | – | yes | yes |
| Administration | – | – | yes | yes |
| — Settings | – | – | – | yes |
| Shop mode | yes | yes | yes | yes |
| Coffee KDS | yes | yes | yes | yes |

That is exactly what Verification 8 asks for: the employee sees no Stock Monitoring, Financial,
Revenue, Kitchen or System Tools header, the Order Management section carries only Delivery Legacy,
the Stock section is present, and `/orders` and `/deliveries` are 403 while `/stocking` works.

**Still needs a human with a browser:** that the 403 page itself looks right; that the legacy match
screen renders correctly for an employee *with an active delivery session* (the product names showing
as plain text rather than links — the 14 gates were verified structurally, but the screen redirects
without a session so it could not be rendered here); and the F&V and labels pages with their buttons
hidden.

## Files changed

Mine, this cycle:
```
 M database/factories/UserFactory.php
 M database/seeders/RolesAndPermissionsSeeder.php
 M resources/views/dashboard.blade.php
 M resources/views/delivery-legacy/match.blade.php
 M resources/views/fruit-veg/index.blade.php
 M resources/views/labels/index.blade.php
 M resources/views/layouts/admin.blade.php
 M routes/api.php
 M routes/web.php
 M tests/Feature/FruitVegLabelPrintingTest.php
 M tests/Feature/FruitVegProductImageTest.php
 M tests/Feature/KitchenIngredientProfileEditReturnTest.php
 M tests/Feature/KitchenIngredientProfileStoreTest.php
 M tests/Feature/KitchenOrderCreatePageTest.php
 M tests/Feature/KitchenOrderCsvTest.php
 M tests/Feature/KitchenOrderHistoryTest.php
 M tests/Feature/KitchenOrderStoreTest.php
 M tests/Feature/KitchenOrganicRegistrationTest.php
 M tests/Feature/KitchenRecipeScalingTest.php
 M tests/Feature/KitchenStandingOrderTest.php
 M tests/Feature/LabelTranslationSaveTest.php
 M tests/Feature/OrderDifferenceCreationTest.php
 M tests/Feature/ProductSearchApiTest.php
 M tests/Feature/ProductTest.php
 M tests/Feature/TestScraperControllerTest.php
 M tests/Feature/WasteLogTest.php
?? database/migrations/2026_09_23_150000_add_shop_mode_permissions.php
?? tests/Feature/Shop/RoutePermissionsTest.php
?? tests/Feature/Shop/RolePermissionGrantsTest.php
   (also modified, from cycle 1's files: config/shop.php,
    app/Http/Controllers/Shop/ShopHomeController.php, tests/Feature/Shop/ShopHomeTest.php — step 9b)
```

Cycle 1's files, still uncommitted and untouched by me except the three named above:
`AuthenticatedSessionController`, `RegisteredUserController`, `AppServiceProvider`, `bootstrap/app.php`,
`resources/views/layouts/shop.blade.php`, `resources/css/shop.css`, `resources/js/shop.js`,
`public/images/shop-icons.svg`, `app/Support/UiMode.php`, `app/Http/Middleware/ShareUiMode.php`,
`app/View/Components/ShopLayout.php`, `app/Http/Controllers/{DashboardController,UiModeController}.php`,
`resources/views/components/shop/`, `resources/views/shop/`, `vite.config.js`.

Pre-existing, not mine: the `docs/planImp/` archive moves and `docs/design/`.

Nothing committed, pushed or deployed, per Constraints.

## Notes for Planner

1. **`api/test-scraper/debug-search-raw` — resolved by the owner, now `auth:web` + `role:admin`.**
   See the Addendum. Worth knowing for the protocol: Verification 1 only inspects *authenticated*
   routes, so a route with no auth middleware at all is invisible to this cycle's acceptance criterion.
   A future audit step could check "every `api/*` route requires a login" as well.

2. **Remaining seeder/migration drift, beyond the one line I fixed (Deviation 5).** The same
   `if ($role)` no-op affects `till_review.export` and `cash_reconciliation.view/create/export`: the
   seeder neither defines nor grants them, so on a fresh database managers do not get them, while on
   the live database they do. Nothing in this cycle depends on it (those routes are already gated and
   `CashReconciliationTest` fails for an unrelated pre-existing reason), but a fresh install will
   behave differently from production. One seeder block would settle it.

3. **Manager cannot reach `/settings` or `/sales-import`** — as the plan's Out of scope intends, now
   observable in the Verification 8 table. The one manager loses cache-clear, system info and the sales
   importer. Two seeder lines (`settings.view`, `sales.import_data` in the manager list) if the owner
   wants them back.

4. **`stocking.logs` and the two `delivery-legacy` undo routes now carry two gates** (e.g.
   `['permission:stocking.scan', 'role:admin']`). Both must pass, which is what you described, and
   admins/managers satisfy both. Just noting it is visible in `route:list` and is intentional.

5. **Cycle 1's stock-scan tile still gates on `products.view`, not `stocking.scan`.** Out of scope for
   this cycle per your note, but worth remembering: an employee holds both, so nothing is broken, yet a
   future role with `products.view` and not `stocking.scan` would see a tile leading to a 403.

6. **The new `withRole()` factory state creates the role if missing.** That makes it safe in tests that
   do not seed, but it also means a typo (`withRole('manger')`) silently creates a permissionless role
   rather than failing. If you would rather it `firstOrFail`, say so — it would need the 17 files to
   seed roles first.

7. **`RoutePermissionsTest`'s "open" assertion is deliberately weak** (`status !== 403`), as the plan
   specified, because many of these pages need POS tables. Several currently return 200 against the dev
   database but would 500 in CI. If the POS stubs ever land in `Tests\TestCase`, this matrix could
   tighten to `assertOk()` and become much more valuable.

8. **Deploy order matters.** As your Risks section says: `php artisan migrate` must go out with the
   route changes, never after them, or employees hit 403 on stocking and fruit & veg. The seeder change
   from Deviation 5 is only needed for fresh installs; the live database already has that grant.


## Addendum — two owner decisions taken after Status: DONE

The owner reviewed the two open questions from Deviation 3 / Notes 1 and decided both. Everything
above still holds; this section records what changed afterwards.

### 1. `GET /api/user` — stays ungated (owner's decision, no code change)

Owner: "I think that this shouldn't be gated — there is no good reason to?" Agreed and unchanged.
It is the stock Laravel closure `fn (Request $request) => $request->user()` behind `auth:sanctum`,
returning the caller's own record. Verification 1 therefore still lists 16 names rather than 13; the
three extra (`api/user`, `POST confirm-password`, `logout.get`) are all deliberate — the latter two
being unnamed/sibling Breeze routes in `routes/auth.php` that the plan's expected list did not
enumerate.

### 2. `GET /api/test-scraper/debug-search-raw` — now `auth:web` + `role:admin`

Owner: "to me it should not be accessible to employees."

**Why this mattered more than first described.** I had called it merely ungated. Reading
`TestScraperController::debugSearchRaw()` (line 266) shows it does more than that: it builds its own
Guzzle client, **signs in to `https://www.udea.nl` with `config('services.udea.username')` and
`config('services.udea.password')`**, runs a search and returns the raw page plus regex matches. With
no auth middleware, any anonymous request — from anywhere — triggered a login to the supplier portal
with the shop's credentials and got scraped output back. That is worth more than "employees shouldn't
see it", and I should have said so in the first report rather than filing it as a tidy-up.

**Caller check before changing anything** — exactly one, and it is already admin-only:
```
$ grep -rn "debug-search-raw\|debugSearchRaw" app resources routes tests
app/Http/Controllers/TestScraperController.php:266:    public function debugSearchRaw(): JsonResponse
resources/views/tests/dashboard.blade.php:170:   <button @click="debugSearchRaw()" ...
resources/views/tests/dashboard.blade.php:335:   async debugSearchRaw() {
resources/views/tests/dashboard.blade.php:341:       const response = await fetch('/api/test-scraper/debug-search-raw', {
routes/api.php:14:    Route::get('/debug-search-raw', [TestScraperController::class, 'debugSearchRaw']);
```
`resources/views/tests/dashboard.blade.php` is `tests.dashboard`, which step 4 put behind `role:admin`.
So moving the endpoint into the sibling `['auth:web', 'role:admin']` group matches its only caller
exactly and cannot break it.

Changed: `routes/api.php` — the separate unauthenticated `test-scraper` group is gone; the route now
sits at the top of the existing admin group with a comment saying why.

Check output:
```
$ php artisan route:list  (all ten api/test-scraper routes)
GET     api/test-scraper/debug-search-raw      ['role:admin']
  ...  the other nine unchanged, all ['role:admin']

$ every api/* route with no Authenticate middleware:
(none)          ← before this change, debug-search-raw was the one exception

$ live probe through the HTTP kernel, Accept: application/json
anonymous              401
employee               403
barista                403
manager                403
```
Admin was deliberately **not** fired: a successful call logs in to udea.nl with the shop's supplier
credentials and scrapes it, which is not something to trigger for a test. That admin passes
`role:admin` is covered by `RoutePermissionsTest` (`admin get /tests/hub => open`, same middleware).

Regression check after the change:
```
$ ./vendor/bin/pint --test --dirty     PASS  39 files
$ php artisan test                     17 failed, 483 passed (1763 assertions)
```
Identical to the run recorded under Verification 4 — the same 17 pre-existing failures, same pass count.

### Follow-up left for the Planner

`TestScraperController` has nine further debug/scrape endpoints in that group and a matching
`tests.*` page set, all now `role:admin`. Several appear to be one-off diagnostics from earlier Udea
work (`find-login-url`, `debug-login-page`, `debug-search`, `debug-search-raw`). Deleting the dead ones
would shrink the surface further, but that is a separate decision and I have not touched them.

### 3. Dead debug endpoints deleted (owner asked for it after the Addendum above)

Owner: "delete those dead debug endpoints." I audited all ten routes in the `api/test-scraper` group by
controller method **and** by URI before removing anything, because two of the names I had loosely
called "one-off diagnostics" in my follow-up note turned out to be live.

| endpoint | method | referenced by | verdict |
|---|---|---|---|
| `GET /debug-search-raw` | `debugSearchRaw` | `tests/dashboard.blade.php` button + fetch | keep |
| `POST /product-data` | `proxyProductData` | `tests/client.blade.php` fetch, 5× in `TestScraperControllerTest` | keep |
| `GET /connection-test` | `testConnection` | 3× in `TestScraperControllerTest` | keep |
| `POST /clear-cache` | `clearCache` | `tests/dashboard.blade.php` + 3× in `TestScraperControllerTest` | keep |
| `GET /test-api` | `testApiRoute` | `tests/dashboard.blade.php` | keep |
| `GET /test-udea-connection` | `testUdeaConnection` | `tests/dashboard.blade.php` | keep |
| `GET /find-login-url` | `findLoginUrl` | `tests/dashboard.blade.php` | keep |
| `GET /debug-login-page` | `debugLoginPage` | `tests/dashboard.blade.php` | keep |
| `GET /debug-search` | `debugSearch` | **nothing** | **deleted** |
| `POST /queue-scraping` | `queueScraping` | **nothing** | **deleted** |

Two corrections to my own earlier note, which named four endpoints as probably dead:
`find-login-url`, `debug-login-page` and `debug-search-raw` are all wired to buttons on
`tests/dashboard.blade.php`, so only two were actually dead. The earlier greps for `debugSearch` were
matching `debugSearchRaw` as a substring; `grep -w` separates them.

Changed: `routes/api.php` (2 route lines), `app/Http/Controllers/TestScraperController.php`
(`debugSearch()` and `queueScraping()`, both self-contained).

Check output:
```
$ grep -rnw "debugSearch\|queueScraping" app resources routes tests
(no output)

$ remaining api/test-scraper routes — 8, all role:admin
POST    api/test-scraper/clear-cache            ['role:admin']
GET     api/test-scraper/connection-test        ['role:admin']
GET     api/test-scraper/debug-login-page       ['role:admin']
GET     api/test-scraper/debug-search-raw       ['role:admin']
GET     api/test-scraper/find-login-url         ['role:admin']
POST    api/test-scraper/product-data           ['role:admin']
GET     api/test-scraper/test-api               ['role:admin']
GET     api/test-scraper/test-udea-connection   ['role:admin']

$ every fetch('/api/test-scraper/...') in resources/views/tests/ checked against the route table:
  OK   /api/test-scraper/clear-cache
  OK   /api/test-scraper/debug-login-page
  OK   /api/test-scraper/debug-search-raw
  OK   /api/test-scraper/find-login-url
  OK   /api/test-scraper/product-data
  OK   /api/test-scraper/test-api
  OK   /api/test-scraper/test-udea-connection
(no DEAD rows — nothing in a view points at a deleted route)

$ ./vendor/bin/pint --test --dirty     PASS  40 files
$ php artisan test                     17 failed, 483 passed (1763 assertions)
```
Same 17 pre-existing failures, same pass count as Verification 4. No test covered either deleted
endpoint, so nothing was removed or rewritten to make the suite pass.

**Now-orphaned code I did NOT delete** (not endpoints; flagging for a decision):
deleting `queueScraping()` leaves `UdeaScrapingService::queueProductScraping()` (line 1162) and
`IndependentScrapingService::queueProductScraping()` (line 390) with no callers, and the first is the
only thing that dispatches `App\Jobs\ScrapeProductDataJob` — so that job class is now unreachable too.
That is three more files' worth of dead code in the scraping layer rather than a debug endpoint, so I
stopped at the boundary you named. Say the word and it goes.
