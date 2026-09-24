# Shop mode cycle 4 — Find product (search and stock check) — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-23

## Baseline
HEAD: 47ee616f
Pre-existing dirty files (cycle 3's accepted work, archived but uncommitted — not mine):
```
 M config/shop.php
 D docs/planImp/implemented.md
 M docs/planImp/plan.md
 M resources/js/shop.js
 M routes/web.php
 M tests/Feature/Shop/ShopHomeTest.php
?? app/Http/Controllers/Shop/StockScanController.php
?? docs/planImp/archive/2026-09-23-scraping-queue-cleanup/
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-3/
?? resources/js/shop/
?? resources/views/components/shop/scan-input.blade.php
?? resources/views/shop/stock-scan.blade.php
?? tests/Feature/Shop/ShopStockScanTest.php
```

## Steps
### 1. Route and controller — done
Changed: `app/Http/Controllers/Shop/FindProductController.php` (new), `routes/web.php`
Check output:
```
$ php artisan route:list --name=shop.find-product
  GET|HEAD       shop/find shop.find-product › Shop\FindProductController@ind…
                                                            Showing [1] routes

$ php artisan route:list --name=shop.find-product --json  (middleware)
shop/find shop.find-product ['web', 'Illuminate\Auth\Middleware\Authenticate',
 'App\Http\Middleware\PermissionMiddleware:products.view']
```

### 2. Home tile — done
Changed: `config/shop.php` (new `find-product` entry directly after `stock-scan`)
Check output:
```
$ php artisan tinker --execute="echo count(config('shop.tiles'));"
8

$ php artisan test --filter=ShopHomeTest
  Tests:    7 passed (22 assertions)

$ php artisan test --filter=ShopStockScanTest
  Tests:    8 passed (22 assertions)
```
Both existing Shop suites stay green with the extra tile present.

### 3. Behaviour: find-product.js — done
Changed: `resources/js/shop/find-product.js` (new), `resources/js/shop.js` (registers `shopFindProduct`)
Check output:
```
$ node --check resources/js/shop/find-product.js
syntax ok

$ grep -c "route(" resources/js/shop/find-product.js
0

$ node -e "import('./resources/js/shop/find-product.js').then(m => { const d = m.default(); console.log(Object.keys(d).length > 10, d.price({price_with_vat: 3.4}), d.stockLabel({stock_units: 7.5}), d.stockLabel({stock_units: 0})) })"
true €3.40 7 in stock Out of stock

$ npm run build
public/build/assets/shop-CAKOImm3.js                   8.89 kB │ gzip:  3.63 kB
✓ built in 14.00s
```

### 4. The screen — done
Changed: `resources/views/shop/find-product.blade.php` (new)
Check output:
```
$ php artisan view:cache
   INFO  Blade templates cached successfully.

$ php artisan test --filter=ShopViewContractTest
  ✓ there are screens to check
  ✓ screen carries no styling or behaviour with data set #0
  ✓ screen carries no styling or behaviour with data set #1
  ✓ screen carries no styling or behaviour with data set #2
  Tests:    4 passed (38 assertions)
```
Three screens scanned (home, stock-scan, find-product), as the plan expects.

### 5. Tests — done
Changed: `tests/Feature/Shop/ShopFindProductTest.php` (new)
Check output:
```
$ php artisan test --filter="ShopFindProductTest|ShopHomeTest|ShopStockScanTest|ShopViewContractTest"
   PASS  Tests\Feature\Shop\ShopFindProductTest
  ✓ employee can open the find product screen
  ✓ barista is forbidden
  ✓ guest is sent to login
  ✓ home shows the find product tile to product viewers
  ✓ employee can search the product api
  ✓ employee without products view cannot search
   PASS  Tests\Feature\Shop\ShopHomeTest            (7 passed)
   PASS  Tests\Feature\Shop\ShopStockScanTest       (8 passed)
   PASS  Tests\Feature\Shop\ShopViewContractTest    (4 passed)

  Tests:    25 passed (106 assertions)
  Duration: 2.05s
```

### 6. Record the scan-input component API — done
Changed: `docs/design/shop-mode/README.md` (appended "Component API in the app", 6 lines)
Check output:
```
$ grep -c "shop-scan-done" docs/design/shop-mode/README.md
1
```

### 7. Build and format — done
Changed: all touched files
Check output:
```
$ ./vendor/bin/pint --dirty
  PASS   7 files

$ npm run build
✓ built in 12.16s

$ ./vendor/bin/pint --test --dirty
  PASS   7 files

$ python3 -c "manifest lookup for resources/js/shop.js"
True assets/shop-CAKOImm3.js
```

## Deviations

Five, all small; none changes behaviour the plan specifies.

1. **`error` added to the state list** (step 3). The plan's method descriptions set
   and clear `error`, but the state list did not declare it. Added `error: null`
   alongside the rest so Alpine makes it reactive.
2. **`onEnter()` returns early below the 2-character minimum** (step 3). As
   written it would fire a request for an empty or 1-character box, which
   `onInput()` deliberately avoids. It now applies the same minimum.
3. **The stale-response guard also covers the failure path** (step 3). The plan
   put `mine !== this.seq` on the success branch only, so a slow *failed*
   request could still blank a newer successful result. The `catch` and the
   `finally` both check it now, which is what the constraint ("a slow earlier
   response must never overwrite a newer one") asks for.
4. **Detail card grouping** (step 4). The name, big price and pill row sit inside
   a `shop-stack shop-stack--tight` wrapper, as they do in
   `docs/design/shop-mode/screen-03-price-check.html`; the plan listed the
   elements without saying how they are grouped. `x-cloak` added to the "Not in
   our range" pill so it does not flash before Alpine evaluates `x-show`.
5. **Comment wording in `find-product.js`.** The header comment originally said
   "`edit_url` from the API is ignored", which made the plan's own verification
   grep (item 4) report a hit on a comment. Reworded to "the API's edit link is
   ignored" so the grep is genuinely clean.

## Verification

1. `php artisan route:list --name=shop.` → **pass**
```
  GET|HEAD       shop .............. shop.home › Shop\ShopHomeController@index
  GET|HEAD       shop/find shop.find-product › Shop\FindProductController@ind…
  GET|HEAD       shop/stock-scan shop.stock-scan › Shop\StockScanController@i…
                                                            Showing [3] routes
```

2. `php artisan test --filter=Shop` → **pass**, `Tests: 99 passed (224 assertions)`.
   The contract test lists three screens (data sets #0–#2).

3. `php artisan test` → **pass against baseline**: `Tests: 17 failed, 499 passed (1844 assertions)`.
   Baseline was 17 failed / 492 passed; the 7 extra passes are the 6 new
   `ShopFindProductTest` cases plus the contract test's third data set.
   The 17 failures are the identical pre-existing set named in the plan:
```
UdeaScrapingServiceTest      ×7  (BadMethodCallException ×6, InvalidCountException ×1)
CashReconciliationTest       ×3  (BadMethodCallException)
WasteLogTest                 ×2
ProductTest                  ×2
FruitVegLabelPrintingTest    ×2
TestScraperControllerTest    ×1
```

4. `grep -rn "<script\|<style" resources/views/shop/` → no output. **pass**
   `grep -rn "edit_url\|products.create\|products.edit\|labels.scan" resources/views/shop/find-product.blade.php resources/js/shop/find-product.js`
   → no output. **pass** (see Deviation 5)

5. `git diff --stat app/Http/Controllers/Api/ app/Services/ProductSearch/ resources/views/products/ resources/views/components/product-search.blade.php resources/css/shop.css`
   → empty. **pass** — the search stack, the office component and the stylesheet are untouched.

6. `./vendor/bin/pint --test --dirty` → `PASS 7 files`; `npm run build` → `✓ built in 12.16s`. **pass**

7. Manual checks (till PC, tablet/phone, manager, misspelling) — **not run by me.**
   I have no browser session and cannot sign in as an employee from here. The
   list stands as written in the plan for the owner to walk through. Nothing in
   steps 1–7 depends on the outcome; the automated checks above cover routing,
   permissions, markup contract and the endpoint.

## Files changed

`git status --short` at the end. `[pre]` marks files already dirty at baseline
(cycle 3's accepted work, not mine); everything else is this cycle.

```
 M config/shop.php                                            (mine — new tile; also [pre])
 M docs/design/shop-mode/README.md                            mine
 M docs/planImp/implemented.md                                mine
 M docs/planImp/plan.md                                       [pre]
 M resources/js/shop.js                                       (mine — registration; also [pre])
 M routes/web.php                                             (mine — new route; also [pre])
 M tests/Feature/Shop/ShopHomeTest.php                        [pre]
?? app/Http/Controllers/Shop/FindProductController.php        mine (new)
?? app/Http/Controllers/Shop/StockScanController.php          [pre]
?? docs/planImp/archive/2026-09-23-scraping-queue-cleanup/    [pre]
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-3/         [pre]
?? resources/js/shop/                                         (mine — find-product.js; also [pre])
?? resources/views/components/shop/scan-input.blade.php       [pre]
?? resources/views/shop/find-product.blade.php                mine (new)
?? resources/views/shop/stock-scan.blade.php                  [pre]
?? tests/Feature/Shop/ShopFindProductTest.php                 mine (new)
?? tests/Feature/Shop/ShopStockScanTest.php                   [pre]
```

Three of the modified files (`config/shop.php`, `resources/js/shop.js`,
`routes/web.php`) and the untracked `resources/js/shop/` directory carry both
cycle 3's changes and mine, because cycle 3 was archived but never committed.
`public/build/` is gitignored, so the rebuilt assets do not appear.

My files, precisely:
- new: `app/Http/Controllers/Shop/FindProductController.php`,
  `resources/js/shop/find-product.js`,
  `resources/views/shop/find-product.blade.php`,
  `tests/Feature/Shop/ShopFindProductTest.php`
- edited: `config/shop.php` (one tile), `routes/web.php` (one route),
  `resources/js/shop.js` (one import + one registration),
  `docs/design/shop-mode/README.md` (appended section)

## Notes for Planner

- **No loading indicator.** `loading` is tracked and disables "Show more", but
  nothing on screen says a search is running. On the till PC at ~130 ms nobody
  will notice; on a slow tablet connection the list simply sits still for a
  moment. The plan did not ask for one and I did not add one. A `shop-spinner`
  or a dimmed list would be a one-line follow-up if you want it.
- **`shop-facts--2` holds five facts**, so the last cell (VAT) sits alone on its
  row. That is what the plan's step 4 lists. If the odd cell looks wrong in the
  real screen, dropping VAT or adding a sixth fact would even it up — but VAT is
  the one staff get asked about, so I left it.
- **Verification grep vs. code comments.** Item 4 greps for `edit_url` across the
  new files, which flags an honest comment explaining that the field is ignored
  (Deviation 5). Worth scoping that grep to non-comment lines in future plans,
  or the comment has to be written around it.
- **`ShopHomeTest` needed no change.** Its employee fixture holds
  `stocking.scan`, `deliveries.process` and `customer-requests.manage` but not
  `products.view`, so "Find product" correctly does not appear there and the
  existing assertions still hold. Same for
  `ShopStockScanTest::home_tile_is_hidden_without_the_stocking_permission`.
- **Out-of-stock rows** get `is-off`, which only greys the title; the red
  "Out of stock" pill still reads at full strength. That looked right against
  the design, but it is a visual call you may want to confirm on the real screen.
- **Nothing committed, pushed or deployed**, per the plan's Constraints.
