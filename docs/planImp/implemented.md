# Housekeeping — remove the orphaned scraping queue code — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-23

## Baseline

HEAD: `fc5820dc` (Shop mode cycles 1 and 2 are still uncommitted; the current tree is the baseline)

`git status --short` at start — 34 modified/deleted tracked files and 18 untracked paths, all from
cycles 1 and 2 plus the archive moves. The three files this cycle touches were **clean** at start:
`app/Jobs/ScrapeProductDataJob.php`, `app/Services/UdeaScrapingService.php`,
`app/Services/IndependentScrapingService.php` do not appear in it. Full listing:
```
 M app/Http/Controllers/Auth/AuthenticatedSessionController.php
 M app/Http/Controllers/Auth/RegisteredUserController.php
 M app/Http/Controllers/TestScraperController.php
 M app/Providers/AppServiceProvider.php
 M bootstrap/app.php
 M database/factories/UserFactory.php
 M database/seeders/RolesAndPermissionsSeeder.php
R  docs/planImp/implemented.md -> docs/planImp/archive/2026-09-23-kds-modifier-badges/implemented.md
RM docs/planImp/plan.md -> docs/planImp/archive/2026-09-23-kds-modifier-badges/plan.md
 M resources/views/dashboard.blade.php
 M resources/views/delivery-legacy/match.blade.php
 M resources/views/fruit-veg/index.blade.php
 M resources/views/labels/index.blade.php
 M resources/views/layouts/admin.blade.php
 M routes/api.php
 M routes/web.php
 M tests/Feature/{FruitVegLabelPrinting,FruitVegProductImage,KitchenIngredientProfileEditReturn,
    KitchenIngredientProfileStore,KitchenOrderCreatePage,KitchenOrderCsv,KitchenOrderHistory,
    KitchenOrderStore,KitchenOrganicRegistration,KitchenRecipeScaling,KitchenStandingOrder,
    LabelTranslationSave,OrderDifferenceCreation,ProductSearchApi,Product,TestScraperController,
    WasteLog}Test.php
 M vite.config.js
?? app/Http/Controllers/{DashboardController,UiModeController}.php, app/Http/Controllers/Shop/
?? app/Http/Middleware/ShareUiMode.php, app/Support/UiMode.php, app/View/Components/ShopLayout.php
?? config/shop.php, database/migrations/2026_09_23_150000_add_shop_mode_permissions.php
?? docs/design/, docs/planImp/archive/2026-09-23-shop-mode-cycle-{1,2}/, docs/planImp/plan.md
?? public/images/shop-icons.svg, resources/css/shop.css, resources/js/shop.js
?? resources/views/components/shop/, resources/views/layouts/shop.blade.php, resources/views/shop/
?? tests/Feature/Shop/
```

### Risk check run before touching anything: queued job payloads

```
$ php artisan tinker --execute="echo \DB::table('jobs')->where('payload','like','%ScrapeProductDataJob%')->count() . ' ' . \DB::table('failed_jobs')->where('payload','like','%ScrapeProductDataJob%')->count();"
0 0
```
`jobs` = 0, `failed_jobs` = 0 on the dev database, as the plan expected. Nothing to strand.
**This check still needs running on production before deploy** — see Notes for Planner 1.

### Premises verified before deleting

Whole-repo grep (excluding `vendor`, `node_modules`, `archive`, `.git`, `storage`, `public`, and
`plan.md`'s own prose) finds only the three definitions — no route, scheduler entry, view, test, doc
or config reference:
```
app/Jobs/ScrapeProductDataJob.php:13:class ScrapeProductDataJob implements ShouldQueue
app/Services/IndependentScrapingService.php:390:    public function queueProductScraping(
app/Services/IndependentScrapingService.php:395:        \App\Jobs\ScrapeIndependentProductDataJob::dispatch(...)
app/Services/UdeaScrapingService.php:1162:    public function queueProductScraping(
app/Services/UdeaScrapingService.php:1167:        \App\Jobs\ScrapeProductDataJob::dispatch(...)
```
And the class the Independent service dispatches really is missing, confirming it would have thrown:
```
$ ls app/Jobs/ScrapeIndependentProductDataJob.php
ls: cannot access 'app/Jobs/ScrapeIndependentProductDataJob.php': No such file or directory
```

## Steps

### 1. Delete the job class — done
Changed: `app/Jobs/ScrapeProductDataJob.php` **deleted** (130 lines)
Check output:
```
$ wc -l app/Jobs/ScrapeProductDataJob.php     (before)
130 app/Jobs/ScrapeProductDataJob.php

$ rm app/Jobs/ScrapeProductDataJob.php
$ ls app/Jobs/ScrapeProductDataJob.php
ls: cannot access 'app/Jobs/ScrapeProductDataJob.php': No such file or directory

$ grep -rn "ScrapeProductDataJob" app routes tests config database resources
app/Services/UdeaScrapingService.php:1167:        \App\Jobs\ScrapeProductDataJob::dispatch($productCode, $callbackUrl, $callbackData);
```
The one remaining hit is the dispatcher removed in step 2; after that step the grep is empty
(Verification 1).

### 2. Remove `queueProductScraping()` from the Udea service — done
Changed: `app/Services/UdeaScrapingService.php` (−13 lines)
Located by method name rather than line number, as the plan's Risks section asks. The script asserted
the removed block contained `ScrapeProductDataJob` and that `queueProductScraping` occurred exactly
once in the file before removal.
Check output:
```
$ grep -n "queueProductScraping" app/Services/UdeaScrapingService.php
(no output)

$ php -l app/Services/UdeaScrapingService.php
No syntax errors detected in app/Services/UdeaScrapingService.php

$ tail -3 app/Services/UdeaScrapingService.php
        return null;
    }
}
```

### 3. Remove `queueProductScraping()` from the Independent service — done
Changed: `app/Services/IndependentScrapingService.php` (−13 lines)
Check output:
```
$ grep -n "queueProductScraping\|ScrapeIndependentProductDataJob" app/Services/IndependentScrapingService.php
(no output)

$ php -l app/Services/IndependentScrapingService.php
No syntax errors detected in app/Services/IndependentScrapingService.php

$ tail -3 app/Services/IndependentScrapingService.php
        return $this->getProductData($item->supplier_code);
    }
}
```

### 4. Refresh the autoloader and caches — done
Check output:
```
$ composer dump-autoload -q && php artisan optimize:clear
$ php artisan tinker --execute="var_dump(class_exists(\App\Jobs\ScrapeProductDataJob::class));"
bool(false)
```

### 5. Format — done
```
$ ./vendor/bin/pint --dirty
  PASS   42 files
$ ./vendor/bin/pint --test --dirty
  PASS   42 files
```

## Deviations

None. Every step was possible as written, and all of the plan's premises held when I checked them
before deleting anything (see Baseline). Two notes on *how* checks were run rather than what was done:

- Step 2/3 located each method by name, not by the line numbers in the plan, which is what the plan's
  Risks section instructs. The numbers were in fact still correct (1162 and 390).
- Verification 1 also matches this report's own prose in `docs/planImp/implemented.md`, because the
  Baseline section quotes the pre-deletion grep output verbatim as evidence. The plan already allows
  `--exclude-dir=archive` for the same reason; excluding `docs/planImp` as well gives empty output.
  The application code carries no reference.

## Verification

**1.** `grep -rn "queueProductScraping|ScrapeProductDataJob|ScrapeIndependentProductDataJob" app routes tests config database resources docs --exclude-dir=archive --exclude-dir=planImp`
```
(no output)
```

**2.** `php -l` on both services:
```
No syntax errors detected in app/Services/UdeaScrapingService.php
No syntax errors detected in app/Services/IndependentScrapingService.php
```

**3.** `php artisan route:list --path=api | grep -c test-scraper`
```
8
```
Unchanged from the end of cycle 2.

**4.** `php artisan test --filter="UdeaScrapingServiceTest|TestScraperControllerTest|Delivery"`
```
  ⨯ get product data returns cached result
  ⨯ successful product scraping
  ⨯ failed authentication returns null
  ⨯ network error returns null
  ⨯ no product data found returns null
  ⨯ test connection success
  ⨯ parse product data
  ⨯ guzzle test page displays with data
  Tests:    8 failed, 38 passed (160 assertions)
```
Exactly the 7 `UdeaScrapingServiceTest` plus 1 `TestScraperControllerTest` the plan predicts. Nothing
new, and every `Delivery*` test passes.

**5.** `php artisan test`
```
   FAILED  Tests\Unit\UdeaScrapingServiceTest        (7)   BadMethodCallException / InvalidCountException
   FAILED  Tests\Feature\CashReconciliationTest      (3)   BadMethodCallException
   FAILED  Tests\Feature\FruitVegLabelPrintingTest   (2)
   FAILED  Tests\Feature\ProductTest                 (2)
   FAILED  Tests\Feature\TestScraperControllerTest   (1)
   FAILED  Tests\Feature\WasteLogTest                (2)
  Tests:    17 failed, 483 passed (1763 assertions)
  Duration: 60.84s
```
**17 failed / 483 passed** — the identical 17 named in the plan's Context, same counts as before this
cycle. No test was rewritten, deleted or skipped.

**6.** `./vendor/bin/pint --test --dirty` → `PASS  42 files`.

**7.** `git status --short | grep -E "Jobs|ScrapingService"`
```
 D app/Jobs/ScrapeProductDataJob.php
 M app/Services/IndependentScrapingService.php
 M app/Services/UdeaScrapingService.php
```
Exactly the three lines the plan specifies, and nothing else under `app/Jobs/`.

## Files changed

This cycle, in full:
```
 D app/Jobs/ScrapeProductDataJob.php            (−130)
 M app/Services/UdeaScrapingService.php         (−13)
 M app/Services/IndependentScrapingService.php  (−13)
```
Net −156 lines, no additions. Everything else in `git status` is cycle 1 / cycle 2 work and the
archive moves, untouched by this cycle.

`app/Jobs/` now holds the eight jobs the plan lists as in use: `CacheTillTransactions`,
`MonitorCoffeeOrdersJob`, `ParseInvoiceCameraImage`, `ParseInvoiceFile`, `ProcessBankStatement`,
`ProcessCardTransactions`, `RetrieveBarcodeJob`, `SendCustomerStatementsJob`.

Nothing committed, pushed or deployed, per Constraints.

## Notes for Planner

1. **The queued-payload check still needs running on production before this deploys.** On dev both
   counts were `0 0`. The production command, for the deploy notes:
   ```
   php artisan tinker --execute="echo \DB::table('jobs')->where('payload','like','%ScrapeProductDataJob%')->count() . ' ' . \DB::table('failed_jobs')->where('payload','like','%ScrapeProductDataJob%')->count();"
   ```
   If either is non-zero, a worker would fail to unserialize the payload once the class is gone. Given
   the only dispatcher was an unreferenced debug endpoint, a stranded row is unlikely, but it costs one
   command to know.

2. **`IndependentScrapingService` no longer has any queue-related code**, and its `getProductData()`
   path is untouched. Worth knowing that the class it used to dispatch
   (`ScrapeIndependentProductDataJob`) never existed, so no independent-supplier background scraping
   has ever worked — if that capability is ever wanted, it starts from scratch rather than from this
   code.

3. **The out-of-scope sibling is still there**, as you intended: `DeliveryService.php` (around lines
   698–701) references `RetrieveIndependentBarcodeJob`, another class that does not exist, behind a
   `class_exists()` guard so it silently skips. Same shape of unfinished idea as the two removed here.
   Whenever you want it decided, the options are to write the job or drop the branch.

4. **`config/queue.php` / `QUEUE_CONNECTION` unchanged.** Nothing about the queue configuration was
   part of this, and the remaining eight jobs still use it.
