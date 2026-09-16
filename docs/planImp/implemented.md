# Kitchen supplier orders + standing order — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-16

## Baseline
HEAD: 341d74fa (branch `feature/modularization-phase1`)
Pre-existing dirty files:
```
?? docs/planImp/
```

## Steps
### 1. Migrations — done
Changed: `database/migrations/2026_09_16_100000_create_kitchen_orders_table.php` (new),
`database/migrations/2026_09_16_100001_create_kitchen_order_items_table.php` (new),
`database/migrations/2026_09_16_100002_create_kitchen_standing_order_items_table.php` (new)
Check output:
```
$ php artisan migrate
  2026_09_16_100000_create_kitchen_orders_table ................ 302.52ms DONE
  2026_09_16_100001_create_kitchen_order_items_table ........... 284.13ms DONE
  2026_09_16_100002_create_kitchen_standing_order_items_table .. 115.44ms DONE
$ tinker: Schema::hasTable(...) && ... && ...
bool(true)
$ php artisan migrate:rollback --step=3
  2026_09_16_100002_... DONE / 100001 DONE / 100000 DONE
$ php artisan migrate
  all three DONE again
```


### 2. Models — done
Changed: `app/Models/KitchenOrder.php` (new), `app/Models/KitchenOrderItem.php` (new),
`app/Models/KitchenStandingOrderItem.php` (new). None declares `$connection` (grep count 0 in each).
Check output:
```
$ tinker: KitchenOrder::create([...]) ->items()->create([...])
order id: 1 item id: 1
csv: kitchen-order-udea-2026-09-16-1.csv
$ KitchenOrder::find($id)->delete()
after delete items: 0
orders left: 0
```

### 3. Service — done
Changed: `app/Services/KitchenOrderService.php` (new)
Check output:
```
$ tinker
options: [{"id":"5","name":"Udea","count":74},{"id":"37","name":"Independent","count":19},{"id":"44","name":"Udea Veg","count":4}]
default: 5
rows: 74
pos queries: 4          (suppliers, PRODUCTS, supplier_link, STOCKCURRENT)
min case_units: 1
cdn rows: 55            (image_url starts https://cdn.ekoplaza.nl/)
blob rows: 19           (image_url is route products.image)
no image rows: 0
first: AGF tas TGTG paper bag big
$ allProductsGroupedBySupplier()
Independent 19 / Misc 1 / Mossfield 1 / Udea 74 / Udea Veg 4 / No supplier link 2 / Missing POS product 1 = 102
```

### 4. Form requests + controller — done
Changed: `app/Http/Requests/StoreKitchenOrderRequest.php` (new),
`app/Http/Requests/UpdateKitchenStandingOrderRequest.php` (new),
`app/Http/Controllers/KitchenOrderController.php` (new)
Check output (after step 5):
```
$ php artisan route:list --path=kitchen/orders
  GET|HEAD   kitchen/orders                     kitchen.orders.index
  POST       kitchen/orders                     kitchen.orders.store
  GET|HEAD   kitchen/orders/create              kitchen.orders.create
  GET|HEAD   kitchen/orders/{kitchenOrder}      kitchen.orders.show
  GET|HEAD   kitchen/orders/{kitchenOrder}/csv  kitchen.orders.csv
$ php artisan route:list --path=kitchen/standing-order
  GET|HEAD  kitchen/standing-order  kitchen.standing-order.edit
  PUT       kitchen/standing-order  kitchen.standing-order.update
```

### 5. Routes — done
Changed: `routes/web.php` (import added after KitchenIngredientProfileController; block added
immediately after the `wholesale` prefix group, before the AJAX endpoints)
Check output:
```
$ tinker: Route::getRoutes()->match(Request::create($url,'GET'))->getName()
/kitchen/orders => kitchen.orders.index
/kitchen/orders/create => kitchen.orders.create
/kitchen/standing-order => kitchen.standing-order.edit
/kitchen/orders/1/csv => kitchen.orders.csv
```
(`route:list` sorts alphabetically so it cannot show registration order; the router match above
proves the new routes win over `kitchen/{recipe}`. Also covered by KitchenOrderRoutesTest in step 8.)

### 6. Views — done
Changed (new): `resources/views/kitchen/orders/{create,show,index,standing}.blade.php`,
`resources/views/kitchen/orders/partials/{product-row,qty-scripts}.blade.php`.
Changed (existing): `resources/views/kitchen/products/index.blade.php` (header: Create Order button +
Order History link), `resources/views/layouts/admin.blade.php` (sidebar: Kitchen Orders + Standing Order
after Kitchen Products).

The Chrome extension was not connected in this session, so the browser check was run instead by
driving the pages through the HTTP kernel as user #1 (admin@osmanager.local) against the live dev
databases (scratch script, not committed). Smoke data was deleted afterwards. JS behaviour (totals
updating, Confirm enabling on input, Clear all) is **not** verified in a browser — only `node --check`
on the script. See Deviations.

Check output:
```
GET /kitchen/products => 200 create-order link: yes
GET /kitchen/orders/create => 200 udea selected: yes qty inputs: 74 images(img src): 222 confirm disabled: yes history dashes: 74
GET create?supplier=37 => 200 qty inputs: 19
GET create?supplier=999 => 302 location: http://localhost/kitchen/orders/create?supplier=5
PUT standing qty[A]=3 => 302 location: http://localhost/kitchen/standing-order rows: 1
GET /kitchen/standing-order => 200 value=3 for A: yes groups: 7
GET create (after standing) => prefilled 3: yes standing badge: yes row highlighted: yes
POST /kitchen/orders => 302 location: http://localhost/kitchen/orders/4
  order #4 Udea lines=2 cases=5 user=1
GET show => 200 has Download CSV: yes
GET csv => 200 text/csv; charset=UTF-8 | attachment; filename="kitchen-order-udea-2026-09-16-4.csv"
  CSV line1: Quantity,Supplier Code,Product Name,Case Size
  CSV line2: 3,6000562,AGF tas TGTG paper bag big,1
POST all-zero => 302 location: http://localhost/kitchen/orders/create?supplier=5 error flash: Enter a quantity for at least one product.
GET /kitchen/orders => 200 lists #2: yes
GET create (after 2 orders) => history chips: 3 (expect 3: A has 2, B has 1)
cleanup: orders=0 standing=0
$ node --check qty-scripts.js
JS syntax OK
```
(222 img tags = 74 rows × 3: thumbnail + hover preview + tap overlay from the product-image component.)

### 7. Documentation — done
Changed: `docs/features/kitchen-orders.md` (new), `docs/features/kitchen-products.md` (Create Order
feature bullet + Related Documentation link), `docs/FEATURES_INDEX.md` (`### Kitchen Supplier Orders
(NEW! 2026-09-16)` above Wholesale), `CHANGELOG.md` (first entry under `## [Unreleased]` → `### Added`).
Check output:
```
$ for f in <every path in the changelog Modified list>; do [ -f "$f" ] || echo MISSING; done
all changelog paths checked
$ ls database/migrations/2026_09_16_* | wc -l
3
```

### 8. Tests — done
Changed (new): `tests/Concerns/CreatesKitchenOrderPosTables.php`,
`tests/Feature/{KitchenOrderRoutesTest,KitchenOrderCreatePageTest,KitchenOrderStoreTest,KitchenOrderCsvTest,KitchenStandingOrderTest,KitchenOrderHistoryTest}.php`
Check output:
```
$ php artisan test --filter='KitchenOrder|KitchenStandingOrder'
  PASS  KitchenOrderCreatePageTest (4)  PASS  KitchenOrderCsvTest (3)  PASS  KitchenOrderHistoryTest (4)
  PASS  KitchenOrderRoutesTest (3)      PASS  KitchenOrderStoreTest (5)  PASS  KitchenStandingOrderTest (3)
  Tests:    22 passed (117 assertions)
$ php artisan test --filter=Kitchen
  Tests:    94 passed (298 assertions)
```
First run had 2 failures in KitchenOrderHistoryTest: "Access denied for user 'osmanager'@'localhost'
to database ':memory:' (Connection: mysql ... kitchen_products)". The index page's supplier filter
calls `supplierOptions()` → `KitchenProduct` (mysql-pinned), so the history test also needs
`AliasesMysqlConnection` even though it creates no POS tables. Added; the plan's table said
"Service-level, no POS tables" which is still true — it is the mysql alias that was missing.

### 9. Format — done
Check output:
```
$ ./vendor/bin/pint <18 new/changed PHP files>
  PASS .... 18 files
$ ./vendor/bin/pint --test <all changed .php from git status>
  PASS .... 20 files          (second run, no changes)
```

## Deviations

1. **CSV writer (step 3 / constraint "header row must be exactly …")**. The plan said to use
   `fputcsv` and that it "only quotes when needed". PHP's `fputcsv` quotes any field containing a
   space, so the header came out as `Quantity,"Supplier Code","Product Name","Case Size"` in the
   smoke run, violating the exact-header constraint. Replaced with a private `csvLine()` that quotes
   only fields containing `,` `"` CR or LF (RFC 4180) and escapes `"` as `""`. Header now byte-exact;
   comma-containing names are still quoted (KitchenOrderCsvTest covers both).
2. **Browser check (step 6 / verification 6)**. The Claude-in-Chrome extension was not connected, so
   the manual browser check was replaced by a scratch script driving the real HTTP kernel as the
   admin user against the live dev DBs (all pages, standing save → pre-fill, confirm, CSV, all-zero
   rejection, history, last-3 chips). Output is under step 6. **Not verified**: the JS (totals
   updating on input, Confirm enabling, Clear all). Only `node --check` was run on it.
3. **`create()` when no supplier exists at all**. The plan says an unknown `?supplier=` redirects to
   the default. If there is no default (no kitchen products with a link) that would loop, so in that
   case the page renders an empty state instead of redirecting. Only reachable on an empty database.
4. **`KitchenOrderHistoryTest` uses `AliasesMysqlConnection`** (see step 8). Not a behaviour change.

## Verification

1. `php artisan migrate:status`:
```
  2026_09_16_100000_create_kitchen_orders_table ..................... [90] Ran
  2026_09_16_100001_create_kitchen_order_items_table ................ [90] Ran
  2026_09_16_100002_create_kitchen_standing_order_items_table ....... [90] Ran
```
2. `php artisan route:list --path=kitchen | grep -c 'kitchen.orders.|kitchen.standing-order.'` → `7`.
   `route:list` sorts alphabetically so it cannot show registration order; router matching (step 5)
   and KitchenOrderRoutesTest prove precedence over `kitchen/{recipe}`.
3. `php artisan test --filter=Kitchen` → `Tests: 94 passed (298 assertions)`.
4. `php artisan test` → `Tests: 21 failed, 363 passed (1423 assertions)`. **No new failures.** The 21
   are in AuthenticationTest (1), CashReconciliationTest (3), FruitVegLabelPrintingTest (2),
   ProductTest (5), TestScraperControllerTest (1), WasteLogTest (2), UdeaScrapingServiceTest (7).
   Proven pre-existing: the same seven classes run in a clean `git worktree` of HEAD 341d74fa give
   `21 failed, 33 passed` (worktree removed afterwards). This matches the "21 pre-existing failures"
   noted in the two most recent CHANGELOG entries.
5. `./vendor/bin/pint --test` on all changed PHP files → PASS (20 files). The repo-wide
   `pint --test` reports 1 pre-existing issue in `JFolder_temp/old_system/organic_trust_supplier_handler.php`,
   untouched by this work.
6. Browser check → replaced by the kernel-driven run (Deviation 2). First two lines of the downloaded CSV:
```
Quantity,Supplier Code,Product Name,Case Size
3,6000562,AGF tas TGTG paper bag big,1
```
7. Query count: `productsForSupplier('5')` → `pos queries: 4`.


## Verification
(pending)

## Files changed
`git status --short` at the end (`?? docs/planImp/` was the only pre-existing dirty entry):
```
 M CHANGELOG.md
 M docs/FEATURES_INDEX.md
 M docs/features/kitchen-products.md
 M resources/views/kitchen/products/index.blade.php
 M resources/views/layouts/admin.blade.php
 M routes/web.php
?? app/Http/Controllers/KitchenOrderController.php
?? app/Http/Requests/StoreKitchenOrderRequest.php
?? app/Http/Requests/UpdateKitchenStandingOrderRequest.php
?? app/Models/KitchenOrder.php
?? app/Models/KitchenOrderItem.php
?? app/Models/KitchenStandingOrderItem.php
?? app/Services/KitchenOrderService.php
?? database/migrations/2026_09_16_100000_create_kitchen_orders_table.php
?? database/migrations/2026_09_16_100001_create_kitchen_order_items_table.php
?? database/migrations/2026_09_16_100002_create_kitchen_standing_order_items_table.php
?? docs/features/kitchen-orders.md
?? docs/planImp/                      (pre-existing; now also holds this file)
?? resources/views/kitchen/orders/    (create, show, index, standing, partials/product-row, partials/qty-scripts)
?? tests/Concerns/CreatesKitchenOrderPosTables.php
?? tests/Feature/KitchenOrderCreatePageTest.php
?? tests/Feature/KitchenOrderCsvTest.php
?? tests/Feature/KitchenOrderHistoryTest.php
?? tests/Feature/KitchenOrderRoutesTest.php
?? tests/Feature/KitchenOrderStoreTest.php
?? tests/Feature/KitchenStandingOrderTest.php
```
Nothing committed. The three migrations have been run on the dev MySQL database (tables exist,
empty — all smoke rows were deleted).

## Notes for Planner

- **JS is the one unverified piece.** Please open `/kitchen/orders/create` in a browser and check:
  totals update as you type / press +/−, Confirm enables above 0 cases, Clear all resets, and
  pre-filled standing rows show non-zero totals on load. Also that the sticky footer behaves
  with the hover image preview.
- **`PRODUCTS.*` still loads the IMAGE blob.** The plan's "cheap has_image flag without loading
  blobs" idiom from `ProductRepository` selects `PRODUCTS.*` *and* the flag, so the blob column is
  transferred for all 74 rows (the flag just avoids the accessor). It is fast enough on dev (page
  renders in well under a second) but a follow-up could select explicit columns. Not changed
  because the plan named that idiom.
- **Live data counts differ slightly from the plan's Context**: I see 2 kitchen products with no
  supplier link + 1 with a missing POS row (plan said 3 + 1); 74+19+4+1+1+2+1 = 102 matches
  `KitchenProduct::count()`. The plan's "3" likely counted the missing-POS product as also unlinked.
- **Independent images cost one Laravel-DB query per product** via `SupplierImageCache` inside
  `SupplierService::getExternalImageUrl()` (19 rows on live data). This is on the default
  connection, so the ≤4 POS-query check still holds, and `SupplierService` is out of scope to change.
- The order-again link on the show page and the `?supplier=` redirect both use the stored
  `supplier_id`; if a supplier later loses all kitchen products the create page redirects to the
  default with an error flash, as specified.
- `KitchenStandingOrderItem` got an extra `updatedBy()` relation (not in the plan, harmless, unused).
- Scratch files (kernel smoke script, worktree) live only in the session scratchpad; nothing was
  added to the repo outside the file list above.
