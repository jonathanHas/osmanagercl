# Housekeeping — remove the orphaned scraping queue code

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-23

## Goal

Delete three pieces of dead code left behind when cycle 2 removed the `queue-scraping` debug endpoint: the background job that scraped a Udea product and posted the result to a webhook, and the two service methods that dispatched it. Nothing calls them, one of them would crash if anything did, and production fetches supplier data synchronously. No behaviour changes for any user. This is not a Shop mode cycle; it is a small cleanup so the scraping layer stops carrying an unfinished idea.

## Context

- **What is being removed and why it is dead.** `app/Jobs/ScrapeProductDataJob.php` (130 lines) runs `UdeaScrapingService::getProductData()` in the background and, if given a `callbackUrl`, POSTs the result to it with Guzzle. Its only dispatcher is `UdeaScrapingService::queueProductScraping()` (lines 1162–1173, the last method in the class; the class closes at 1174). The sibling `IndependentScrapingService::queueProductScraping()` (lines 390–401, likewise the last method; class closes at 402) dispatches `\App\Jobs\ScrapeIndependentProductDataJob`, **a class that does not exist**, so it would throw on first use. Git history shows the only caller of either method was `TestScraperController::queueScraping()`, deleted in cycle 2 (archived at `docs/planImp/archive/2026-09-23-shop-mode-cycle-2/`). No route, scheduler entry, view, test, doc or config references any of the three today (`grep -rn "queueProductScraping\|ScrapeProductDataJob\|ScrapeIndependentProductDataJob" app routes tests config database resources docs` finds only the three definitions themselves).
- **Production path, untouched.** Supplier data is fetched synchronously by `ProductController` (lines 891 and 1755) and `DeliveryService` (lines 917–919) through `getProductData()`. Those stay.
- **Other jobs, untouched.** `app/Jobs/` also holds `CacheTillTransactions`, `MonitorCoffeeOrdersJob`, `ParseInvoiceCameraImage`, `ParseInvoiceFile`, `ProcessBankStatement`, `ProcessCardTransactions`, `RetrieveBarcodeJob`, `SendCustomerStatementsJob`. All are in use and none touches the code being removed.
- **Tests.** `tests/Unit/UdeaScrapingServiceTest.php` (7 tests) and `tests/Feature/TestScraperControllerTest.php` (1 test) already fail for unrelated, pre-existing reasons (Guzzle/Mockery expectations, page content). Neither references the removed code. The full-suite baseline is **17 failed / 483 passed**, the same 17 tests as every run since before Shop mode cycle 1: 7 `UdeaScrapingServiceTest`, 3 `CashReconciliationTest`, 2 `WasteLogTest`, 2 `ProductTest`, 2 `FruitVegLabelPrintingTest`, 1 `TestScraperControllerTest`.
- **Working tree.** Cycles 1 and 2 are still uncommitted; treat the current tree as the baseline and record `git status --short` at the start as usual.

## Constraints

- Do not commit, push or deploy.
- Touch only the three files named in the Steps. No refactoring of the scraping services beyond removing the one method each.
- Do not rewrite, delete or skip any existing test.

## Out of scope

- `DeliveryService.php` lines 698–701 reference a second missing job class, `RetrieveIndependentBarcodeJob`, behind a `class_exists()` guard. It silently skips rather than crashing. Leave it; it is a separate decision (either write the job or remove the branch).
- The remaining eight `api/test-scraper/*` admin-only diagnostics and the `tests.*` pages.
- Anything under Shop mode.
- Fixing the 17 pre-existing test failures.

## Steps

### 1. Delete the job class
Files: `app/Jobs/ScrapeProductDataJob.php` (delete)
What: `git rm` is not available for an untracked-vs-tracked distinction here (the file is tracked), so `rm app/Jobs/ScrapeProductDataJob.php` and let `git status` show it as deleted.
Check: `ls app/Jobs/ScrapeProductDataJob.php` → no such file; `grep -rn "ScrapeProductDataJob" app routes tests config database resources` → no output.

### 2. Remove `queueProductScraping()` from the Udea service
Files: `app/Services/UdeaScrapingService.php`
What: delete lines 1161–1173 (the blank line before the method through the method's closing brace), leaving the class's closing `}` as the final line. Nothing else in the file changes.
Check: `grep -n "queueProductScraping" app/Services/UdeaScrapingService.php` → no output; `php -l app/Services/UdeaScrapingService.php` → no syntax errors; `tail -3 app/Services/UdeaScrapingService.php` shows the previous method's closing brace, then the class brace.

### 3. Remove `queueProductScraping()` from the Independent service
Files: `app/Services/IndependentScrapingService.php`
What: delete lines 389–401 (blank line plus method), leaving the class's closing `}` as the final line.
Check: `grep -n "queueProductScraping\|ScrapeIndependentProductDataJob" app/Services/IndependentScrapingService.php` → no output; `php -l app/Services/IndependentScrapingService.php` → no syntax errors.

### 4. Refresh the autoloader and caches
Files: none
What: `composer dump-autoload -q && php artisan optimize:clear`, so the classmap no longer lists the deleted job.
Check: `php artisan tinker --execute="var_dump(class_exists(\App\Jobs\ScrapeProductDataJob::class));"` prints `bool(false)`.

### 5. Format
Files: the two services
What: `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` → clean.

## Verification

Run in order and paste real output:

1. `grep -rn "queueProductScraping\|ScrapeProductDataJob\|ScrapeIndependentProductDataJob" app routes tests config database resources docs` → no output (the archived `docs/planImp/archive/**` may match; that is fine, exclude it with `--exclude-dir=archive`).
2. `php -l` on both service files → "No syntax errors detected".
3. `php artisan route:list --path=api | grep -c test-scraper` → `8` (unchanged from the end of cycle 2).
4. `php artisan test --filter="UdeaScrapingServiceTest|TestScraperControllerTest|Delivery"` → the same failures as the baseline (7 `UdeaScrapingServiceTest`, 1 `TestScraperControllerTest`), nothing new.
5. `php artisan test` → **17 failed, 483 passed**, the identical 17 named in Context.
6. `./vendor/bin/pint --test --dirty` → clean.
7. `git status --short | grep -E "Jobs|ScrapingService"` → exactly three lines: `D app/Jobs/ScrapeProductDataJob.php`, `M app/Services/UdeaScrapingService.php`, `M app/Services/IndependentScrapingService.php`.

## Risks

- **Queued jobs already in the database.** `QUEUE_CONNECTION=database`. If a `ScrapeProductDataJob` payload were still sitting in the `jobs` or `failed_jobs` table, a worker would fail to unserialize it after the class is gone. Check once on the dev DB: `php artisan tinker --execute="echo \DB::table('jobs')->where('payload','like','%ScrapeProductDataJob%')->count() . ' ' . \DB::table('failed_jobs')->where('payload','like','%ScrapeProductDataJob%')->count();"` → expected `0 0`. Record the numbers; if non-zero, note it for the Planner rather than deleting rows. The same check belongs in the deploy notes for production.
- **Line numbers.** They are correct for the current tree; if the services have shifted, locate the method by name, not by number.
- Nothing else. The synchronous scrape path has no dependency on the removed code.

## Review

Reviewed 2026-09-23 by the Planner against `implemented.md`, the diff, and a rerun of every verification command.

Criteria:
1. Job class deleted — PASS. `git status` shows `D app/Jobs/ScrapeProductDataJob.php`; `class_exists()` is false after the autoload refresh.
2. Udea service method removed — PASS. Diff is exactly the 13 lines (blank line plus method); `php -l` clean.
3. Independent service method removed — PASS. Same shape; `php -l` clean.
4. Autoloader and caches refreshed — PASS.
5. Format — PASS (42 dirty files).

Verification rerun by the Planner: no reference to any of the three names in `app`, `routes`, `tests`, `config`, `database`, `resources` or `docs` (archive and protocol folders excluded, as they quote the names as evidence); 8 admin-only `api/test-scraper` routes, unchanged; queued-payload check `0 0` on the dev database; full suite 17 failed / 483 passed, the identical 17 pre-existing tests. Net change −156 lines, no additions, no test touched.

Deviations: none. The implementer located the methods by name as the Risks section asked and checked the premises before deleting, which is the right habit.

Notes for Planner: all acknowledged. Note 1 goes into the deploy notes: run the queued-payload count on production before deploying this. Note 3 (the guarded reference to a missing `RetrieveIndependentBarcodeJob` in `DeliveryService`) remains an open owner decision, harmless meanwhile.

Result: ACCEPTED. Owner: `mkdir -p docs/planImp/archive/2026-09-23-scraping-queue-cleanup && mv docs/planImp/plan.md docs/planImp/implemented.md docs/planImp/archive/2026-09-23-scraping-queue-cleanup/`. Next: Shop mode cycle 3, Stock scan (screen 02).
