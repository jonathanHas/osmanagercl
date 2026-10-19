# Cycle 4 — Evidence on this machine, and a price review on `/deposits` — implementation

Status: DONE
Plan revision: 5
Implementer: Opus
Date: 2026-10-17

## Baseline
HEAD: 5775fbef
Pre-existing dirty files:
```
 M docs/deposit/README.md
R  docs/deposit/implemented.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/implemented.md
R  docs/deposit/plan.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/plan.md
?? docs/deposit/plan.md
```

## Steps

### 1. Populate the evidence on this machine — done
Changed: data only (dev `osmanager` + dev POS `unicenta2016`).

1.1 `deposits:backfill-delivery-items --dry-run`, then for real:
```
| 313   | 537 |
| 315   | 125 |
| 322   | 6   |
| 9936  | 12  |
| 10046 | 76  |
756 line(s) over 64 deliveries would be updated (dry run, nothing written).
...
756 line(s) over 64 deliveries updated, 756 sighting(s) written.
```
1.2 `deposits:refresh-suggestions` (all tiers off):
```
0 created, 0 updated, 0 unchanged, 73 product(s) on a tier not charged to customers.
Udea codes with no till product (add by hand on /deposits if needed): 48719
```
1.3 Tiers switched on: 313 (`barrel_codes.id` 5), 315 (12), 9936 (18). No
browser is available to this session, so each PATCH was made by calling
`DepositController::updateTier()` with a `PATCH` request carrying
`charge_customer=1` (same code path minus the HTTP middleware; see Deviations):
```
313: Deposit 313 is now charged to customers; till products ready.
  charge=1 pos=8cf484cf-9362-4668-8de9-7680945edd76 ret=fb4044b7-0bcc-4cb0-8a93-c7d7f53e1247
315: Deposit 315 is now charged to customers; till products ready.
  charge=1 pos=8e770644-a920-49bc-a6c8-99ed7a46e909 ret=37b63aaf-2783-410b-bbfc-2480ebb5bf16
9936: Deposit 9936 is now charged to customers; till products ready.
  charge=1 pos=8d4f93ac-dad7-4c20-a86b-f8fe05a9371d ret=cf17842a-3795-4338-997f-1583341e43f6
```
1.4 `deposits:refresh-suggestions` again:
```
| suggested | 57 |
0 created, 0 updated, 57 unchanged, 16 product(s) on a tier not charged to customers.
Udea codes with no till product (add by hand on /deposits if needed): 48719
```
(0 created because each `updateTier` call already reran the refresh.)

Check:
```
suggested=57 confirmed=0 sightings=765
DEP products: DEP-010, DEP-010-RET, DEP-025, DEP-025-RET, DEP-070, DEP-070-RET
```
`GET /deposits` rendered through the HTTP kernel as user 1 (Admin): status 200,
the tiers card shows `DEP-025 / DEP-025-RET`, `DEP-070 / DEP-070-RET`,
`DEP-010 / DEP-010-RET`; the lime juice row is listed.

### 2. Migration: price review columns — done
Changed: `database/migrations/2026_10_17_000001_add_price_review_to_product_deposits.php` (new; did not exist)
Check output:
```
2026_10_17_000001_add_price_review_to_product_deposits ........ 90.33ms DONE
bool(true)                       <- Schema::hasColumns(...)
Rolling back ... 2026_10_17_000001_add_price_review_to_product_deposits ... DONE
bool(false)
Running migrations ... 2026_10_17_000001_add_price_review_to_product_deposits ... DONE
bool(true)
```

### 3. Model — done
Changed: `app/Models/ProductDeposit.php` (constants, fillable, casts,
`priceReviewedBy()`, `reviewPrice()`, `isPriceReviewed()`)
Check output:
```
$ php artisan tinker --execute='echo (new App\Models\ProductDeposit)->reviewPrice("kept", null)->price_review;'
kept
```

### 4. `DepositPriceReviewService` — done
Changed: `app/Services/Deposits/DepositPriceReviewService.php` (new; did not exist)
`forRows()` runs two queries (Udea lines joined to deliveries; daily summary)
and groups in PHP. Step detection compares whole cents (`round(avg × 100)`),
so 0.05 / 0.02 thresholds have no float edge cases. `lastLastingStep()` is
public so the tests can feed it rows directly.

Real-data check on dev (57 rows, 0.19 s):
```
Your Organic Nature Lime juice 200ml {"net":2.7,"gross":2.7,"vat_rate":0,"cost":1.36,"cost_on":"2026-10-05","cost_source":"docket","markup_pct":98.53,"udea_rrp":2.59,"step":{"on":"2025-12-27","from":1.99,"to":2.7},"cost_before_step":1.31}
Luna e Terra Pear juice 700ml {"net":3.2114,"gross":3.95,"vat_rate":0.23,"cost":1.9,"cost_on":"2026-10-12",...,"step":{"on":"2025-12-04","from":3.45,"to":3.21},"cost_before_step":1.84}
with step: 29
```
Test check: see step 8.

### 5. Controller and route — done
Changed: `app/Http/Controllers/DepositController.php`, `routes/web.php`
- `index()`: products loaded `->with('tax')->get(['ID','NAME','CODE','PRICESELL','PRICEBUY','TAXCAT'])`;
  passes `prices`, `sort`, `reviewedCount`. `sort` in `status|markup|change`
  (default `status`); markup / change sort descending with nulls last through a
  small private `sortRowsBy()` (partition, `sortByDesc`, concat).
- `priceReview()`: validates `action` in `keep,lower,reset`. Keep flashes
  `"<code>: price kept at €x.xx."` with the gross from `getGrossPrice()`
  rounded to 2 dp; Lower → service, error under the `price_review` key;
  Reset flashes `"<code>: review cleared (price unchanged)."`.
- Route added after `deposits.products.destroy`.

Check output:
```
  PATCH      deposits/products/{productDeposit}/price deposits.products.price…
  ...
                                                            Showing [9] routes
```

### 6. View — done
Changed: `resources/views/deposits/index.blade.php`
Sort select in the same GET form as the status select (both kept across
submits), counter line under the heading, three columns after Evidence, empty
row colspan 8 → 11. The Lower confirm text is built with `@js(sprintf(...))`
so product names with quotes are safe. Lower is disabled with a title when
gross − deposit ≤ 0 or the price is unknown.

Check (rendered through the HTTP kernel as user 1, no browser; see Deviations):
```
status=200
Your Organic Nature Lime juice 200ml 8711521925056 92505 313 €0.25 ... 38 units in 5 lines, last 2026-10-05
€2.70 net 2.7000 · cost 1.36 (docket 2026-10-05) · markup 99% Udea advice €2.59
1.99 → 2.70 on 2025-12-27 cost then 1.31 Keep Lower by €0.25 suggested ...
Price review: 0 of 57 rows decided
confirm: if (! confirm('Lower Your Organic Nature Lime juice 200ml from €2.70 to €2.45 on the till?')) $event.preventDefault()
```
Sort checks:
```
?sort=markup  -> markup 118% 113% 113% 109% 108% 106% 102% 99% ... last: 50% 48% 48%
?sort=change  -> first rows: Wisselwaar Quinoa Dutch 400g (→ 4.75 on 2026-06-17),
                 Luna e Terra Tomato-juice (→ 1.35 on 2026-06-11), Luna e Terra apple juice 1l (2026-06-05) ...
default       -> Montcalm Natural Mineral Water 5L first (status, product_code order, unchanged)
selected option follows ?sort (status / markup / change)
```
Console: not checked (no browser).

### 7. Test helpers — done
Changed: `tests/Concerns/CreatesDepositPosTables.php` (`TAXCATEGORIES`,
`TAXES`, `posTaxRate()`)
Check output (`php artisan test --filter=Deposit`, before new tests):
```
Tests:    46 deprecated, 5 passed (294 assertions)
```
(51 tests, 0 failed; DEPR = passed with the PHP 8.5 `PDO::MYSQL_ATTR_*` deprecation.)

### 8. Tests — done
Changed: `tests/Feature/DepositPriceReviewServiceTest.php` (new; checked
first, did not exist), `tests/Feature/DepositScreenTest.php` (5 tests added).

Service test (11): latest docket cost ignoring 0-cost and non-Udea lines;
PRICEBUY fallback with `cost_source = till`; markup null without cost; Udea
advice = latest positive `sale_price`; step 1.99 → 2.70 on the first 2.70 day
with `cost_before_step` 1.31 (latest cost 1.36); one-day dip → no step; change
on the last two days → no step; lower 23% (2.0325 → 2.25/1.23 to 4 dp,
`label_logs` price_update, `price_review_from` `2.5000`, reviewer); lower 0%
2.70 → 2.45; refused when gross − deposit ≤ 0 and when the product is missing
(no write, no label log).
Screen tests (5): columns + counter + sort selected; keep (price and status
untouched); lower → pill and counter → reset (four columns null, lowered price
stays); refused lower → session error; unknown action → 422 (`patchJson`).
Check output:
```
php artisan test --filter=DepositPriceReviewServiceTest
  Tests:    11 deprecated (33 assertions)
php artisan test --filter=Deposit
  Tests:    62 deprecated, 5 passed (359 assertions)      <- 67 tests, 0 failed
```

### 9. Docs — done
Changed: `docs/features/barrel-deposit-tracking.md`
- Data: row for the four `price_review*` columns.
- Services: `DepositPriceReviewService` with the step rule.
- Screen: the three columns, Keep / Lower / Reset, what Lower writes
  (PRICESELL by the product-page recipe, `LabelLog` price_update → "Price
  changed"), the counter and the Order select.
- Routes: the top-level "Routes" block now lists the nine `deposits.*` routes
  including `deposits.products.price` (see Deviations).
- Going live: step **3b. Review prices** after step 3.

## Deviations

1. **No browser in this session.** Step 1.3 (the three tier PATCHes) called
   `DepositController::updateTier()` directly with a PATCH request. The step 6
   check and Verification 5 rendered `GET /deposits` through the HTTP kernel
   as user 1 (Admin), and ran Keep / Reset / Lower / product-page restore by
   calling `DepositController::priceReview()` and
   `ProductController::updatePrice()` with an authenticated request. Same
   controller and service code; CSRF and route middleware were not exercised
   for the PATCHes, and **the browser console was not checked**. The owner
   should click through Verification 5 once in a browser.
2. **Docs "Routes".** The feature doc has no Routes sub-section under
   "Customer Bottle Deposits"; its only Routes block (top level) listed
   barrel-codes routes. I added the nine `deposits.*` routes there,
   `deposits.products.price` included, rather than one route on its own.
3. **422 test uses `patchJson`.** A form PATCH with a bad `action` redirects
   back with errors (302); the JSON variant gives the 422 the plan names.
4. **Gross rounded to cents before subtracting.** `lowerByDeposit` and the
   Price column use `round(net × (1 + rate), 2)` as the shelf price, so net
   2.0325 at 23% is €2.50 (not 2.499975). `price_review_from` stores that
   value (`2.5000`), which is what the plan's test expects.
5. **Baseline timing.** The baseline full run started after the migration,
   model and service files existed (steps 2–4). None of them was used by
   existing code then; the controller, view, helper and test changes all came
   after the baseline finished.

## Verification

1. `php artisan test --filter=Deposit`
   ```
   Tests:    62 deprecated, 5 passed (359 assertions)
   ```
   67 tests (51 existing + 11 service + 5 screen), 0 failed.
2. `php artisan test`
   ```
   baseline: Tests:    1007 deprecated, 16 failed, 43 passed (4866 assertions)
   after:    Tests:    1023 deprecated, 16 failed, 43 passed (4931 assertions)
   ```
   The 16 failures are the same tests in both runs (`diff` of the FAILED lines
   is empty): UdeaScrapingServiceTest (7), BackupConfigurationTest (1),
   CashReconciliationTest (3), FruitVegLabelPrintingTest (2), ProductTest (2),
   TestScraperControllerTest (1). No new failures.
3. `./vendor/bin/pint --test` on the 8 changed PHP files → `PASS ... 8 files`.
4. `php artisan route:list --name=deposits` → `Showing [9] routes`, including
   `PATCH deposits/products/{productDeposit}/price deposits.products.price`.
5. `/deposits` on dev (Deviation 1: no browser):
   a. 57 suggested rows, 0 confirmed; tiers 313 / 315 / 9936 On with
      `DEP-025 / DEP-025-RET`, `DEP-070 / DEP-070-RET`, `DEP-010 / DEP-010-RET`.
   b. Lime juice row ("Your Organic Nature Lime juice 200ml", barcode
      `8711521925056` on this POS): `€2.70`, cost 1.36 (docket 2026-10-05),
      markup 99%, Udea advice €2.59; `1.99 → 2.70 on 2025-12-27`, cost then 1.31.
   c. Highest markup: 118% first, descending to 48%. Latest price change:
      Wisselwaar Quinoa (2026-06-17) first, then 2026-06-11, 2026-06-05 …
   d. Keep on row `8711521929382` → `price kept at €3.95.`, reviewed count 0 → 1
      (`kept`); Reset → `review cleared (price unchanged).`, count back to 0.
   e. Lower on lime juice →
      ```
      Your Organic Nature Lime juice 200ml: €2.70 → €2.45 on the till (net 2.7000 → 2.4500); label queued.
      PRICESELL now 2.4500; label_logs +1; review=lowered from=2.7000 by=1
      product page -> Price updated successfully from gross price.; PRICESELL 2.7000
      reset -> 8711521925056: review cleared (price unchanged).
      final: PRICESELL 2.7000 review=NULL reviewed rows=0
      ```
      Dev ends as it started, apart from two `label_logs` price_update rows for
      `8711521925056` (one from the Lower, one from the product-page restore).
      The `lowered from €2.70` pill is covered by
      `DepositScreenTest::test_lower_then_reset_keeps_the_lowered_price`, not
      seen in a browser. Console: not checked.
6. `git status --short`: see below.

## Files changed
```
 M app/Http/Controllers/DepositController.php
 M app/Models/ProductDeposit.php
 M docs/deposit/README.md                                   (pre-existing)
R  docs/deposit/implemented.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/implemented.md   (pre-existing)
R  docs/deposit/plan.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/plan.md                 (pre-existing)
 M docs/features/barrel-deposit-tracking.md
 M resources/views/deposits/index.blade.php
 M routes/web.php
 M tests/Concerns/CreatesDepositPosTables.php
 M tests/Feature/DepositScreenTest.php
?? app/Services/Deposits/DepositPriceReviewService.php
?? database/migrations/2026_10_17_000001_add_price_review_to_product_deposits.php
?? docs/deposit/implemented.md                              (this report)
?? docs/deposit/plan.md                                     (pre-existing)
?? tests/Feature/DepositPriceReviewServiceTest.php
```
Data changed on dev only: step 1 (backfill, sightings, 57 suggestions, three
tiers on, six `DEP-*` till products and the category on the dev POS), the
migration, and the two `label_logs` rows above. Nothing committed.

## Notes for Planner

1. **Step count differs from the plan's figure.** The plan says 38 of 57
   products show at least one lasting step since 2025-01; the implemented rule
   finds a last step on 29 of 57 (history from 2024-01-01). I did not tune the
   rule (Risks says not to loosen it). Some steps are decreases (Luna e Terra
   Pear juice 700ml `3.45 → 3.21 on 2025-12-04`), and several Landpark waters
   show a step in 2024-12. Worth a look at a few rows to confirm the rule
   matches what you measured.
2. **Barcode in Verification 5b.** `8711521947614` is "Luna e Terra
   Apple-mango juice 200ml" on this POS (it is also the test fixture barcode).
   The lime juice is `8711521925056`.
3. **Going-live step 1** in `docs/features/barrel-deposit-tracking.md` still
   says "the four deposit migrations"; with this cycle there are five. Not
   changed (outside the four places step 9 names).
4. **Newest lasting step is 2026-06-17.** Under "latest price change" nothing
   is newer than June, although sales run to 2026-10-14. That may be true (no
   recent changes) or a sign that slow sellers rarely reach three steady
   selling days. Not investigated.
5. **Dev label area.** The lime juice now has two `price_update` rows in
   `label_logs`, so it shows under "Price changed" on dev's label screen.
   Harmless; clear it from the label area if it gets in the way.
6. **Keep does not check the till.** Keep records `kept` even when the product
   is missing from the till (the flash then says `€?`). Lower refuses in that
   case. Left as is; the plan did not ask for a guard on Keep.

## Revision 3

Plan revision 3 (steps 10, 12, 13, 14, 15, 16, 11 in that order). Revision 2
was not implemented on its own; step 10 is done here as part of Revision 3.

### Baseline (Revision 3)
HEAD: 5775fbef
Dirty at the start (Revision 1 work plus the pre-existing files listed above):
```
 M app/Http/Controllers/DepositController.php
 M app/Models/ProductDeposit.php
 M docs/deposit/README.md
R  docs/deposit/implemented.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/implemented.md
R  docs/deposit/plan.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/plan.md
 M docs/features/barrel-deposit-tracking.md
 M resources/views/deposits/index.blade.php
 M routes/web.php
 M tests/Concerns/CreatesDepositPosTables.php
 M tests/Feature/DepositScreenTest.php
?? app/Services/Deposits/DepositPriceReviewService.php
?? database/migrations/2026_10_17_000001_add_price_review_to_product_deposits.php
?? docs/deposit/implemented.md
?? docs/deposit/plan.md
?? tests/Feature/DepositPriceReviewServiceTest.php
```

### 10. Price history from the till's own lines — done
Changed: `app/Services/Deposits/DepositPriceReviewService.php` (new private
`fullPriceDays()` on the `pos` connection replaces the `SalesDailySummary`
query; `last_sold`, `changed_since_sale` facts),
`tests/Concerns/CreatesDepositPosTables.php` (`RECEIPTS`, `TICKETS`,
`TICKETLINES`, `posSale()`), `tests/Feature/DepositPriceReviewServiceTest.php`
(three step tests now on `posSale()`, five new tests).
The view part is superseded by step 15 (Revision 3 says so); the docs part is
folded into step 16's doc rewrite.

The plan's query alias `lines` is a MySQL reserved word (see Deviations R3-1):
```
ERR: SQLSTATE[42000]: Syntax error ... near 'lines, SU
```
With `line_count` on dev:
```
ok rows=4902 t=0.21s disc=616 lines=6157
```
Check:
```
php artisan test --filter=DepositPriceReviewServiceTest   -> 16 tests, all DEPR (pass), 0 failed
forRows() on dev: rows=57 time=0.35s with_step=25 changed_since_sale=2
Your Organic Nature Lime juice 200ml: step {"on":"2025-12-27","from":1.99,"to":2.7}, last_sold {"on":"2026-10-12","net":2.7}
Landpark Mineral-water Carbonated Lemon 750 ml: step null, last_sold {"on":"2026-09-02","net":1.42}
Wisselwaar Basmati-rice white 850g: step null
Ommelanden Buttermilk 1l: now 2.5, last_sold {"on":"2026-07-11","net":2.8}, changed_since_sale true
Ommelanden Low fat yoghurt 1L: now 3.95, last_sold {"on":"2026-05-11","net":3.3}, changed_since_sale true   (second flagged row; see Notes R3)
```

### 12. Delivery charge setting — done
Changed: `app/Models/AppSetting.php` (new; checked first, did not exist),
`app/Http/Controllers/DepositController.php` (`DELIVERY_PCT_KEY` /
`DELIVERY_PCT_DEFAULT` constants, `deliveryPct()`, `updateSettings()`,
`deliveryPct` passed to the view), `routes/web.php` (`deposits.settings`).
Check output:
```
AppSetting::set('deposits.delivery_pct', 16); AppSetting::get('deposits.delivery_pct')  -> string(2) "16"
(row deleted again so dev runs on the default)  AppSetting::get(..., 15)                   -> int(15)
php artisan route:list --name=deposits -> PATCH deposits/settings deposits.settings ... Showing [10] routes
```

### 13. Price choice on confirm, custom price, undo — done
Changed: `app/Models/ProductDeposit.php` (`PRICE_SET`, in `PRICE_REVIEWS`),
`app/Services/Deposits/DepositPriceReviewService.php` (private `writeGross()`
used by `lowerByDeposit()`, new `setGrossPrice()` and `undo()`; the service now
also takes `DepositPosService` for undo's till sync),
`app/Http/Requests/UpdateProductDepositRequest.php` (`price_action`,
`new_price`), `app/Http/Controllers/DepositController.php` (`updateProduct()`
applies the price choice after the confirm and the till sync, only when the
row ends `confirmed`; refused writes flash under `price_review` beside the
success flash; `priceReview()` accepts `undo`; `confirmAll()` takes optional
`barrel_code_id` / `brand`, never touches prices; static `brandOf()` = first
word of the till name).
Check (tests for the new behaviour come in step 16):
```
php artisan test --filter=Deposit  ->  Tests:    67 deprecated, 5 passed (371 assertions)   (72 tests, 0 failed)
```

### 14. Groups, sort, filter, sales facts — done
Changed: `app/Services/Deposits/DepositPriceReviewService.php` (the ticket-line
query is now `tillLineGroups()` and also sums `UNITS`; `fullPriceDays()`
derives the series from it; new facts `sold_12m`, `last_sale_on`, `landed`,
`profit`, `vat_ratio`; `markup_pct` against landed; `forRows()` takes
`$deliveryPct`), `app/Http/Controllers/DepositController.php` (`sort` in
`name|markup|sales`, `group` in `tier|brand|none`; private `groupRows()` and
`sortRows()` replace `sortRowsBy()`; view gets `groups`, `group`, `counts`,
`decidedCount`, `lastSyncedAt`, `deliveryPct`).
Check (view data through the HTTP kernel as user 1, before the view rewrite):
```
/deposits?group=brand&sort=markup  status=200
[Bald] 1 product open=1 confirm={"brand":"Bald"}            Bald Brewer Waterkefir ... markup=29
[Beutelsbacher] 2 products                                    ... markup=59, then 75
[Hellenaris] 2 products                                       ... markup=82, then 89
/deposits?group=none&sort=sales  -> one group, show=0, open=57:
   Pineapple juice Your Organic N 200ml sold=309; Ommelanden Cream 250ml a sold=290; Landpark ... Lemon sold=200; ...
/deposits  -> [Tier 313] €0.25 deposit · 36 products open=36 confirm={"barrel_code_id":5}
              [Tier 315] €0.70 deposit · 17 products; [Tier 9936] €0.10 deposit · 4 products
```

### 15. View rewrite to 2a — done
Changed: `resources/views/deposits/index.blade.php` (rewritten). Header slot with
"Till synced …", Sync till now, Recompute suggestions; deposit-codes strip
(5 cells); review band (counters, delivery charge −/number/+, Status and Group
segmented radios, Sort select, Confirm all suggested); eight-column grid with
group headers and "Confirm n" / "All decided"; one `depositRow` Alpine
component per row (registered on `alpine:init` in an inline script, as
`zebra-labels/create` does), server-rendered Keep values; footer formula line;
Add a product card unchanged. `npm run build` run locally so the new arbitrary
Tailwind classes exist in `public/build` (gitignored; production builds on
deploy).

Check, kernel render as user 1: `status=200` for `/deposits`,
`?group=brand&sort=markup`, `?group=none&sort=sales`, `?status=rejected`,
`?status=suggested,confirmed,rejected`. Lime juice row text:
```
Your Organic Nature Lime juice 200ml 8711521925056 · Udea 92505 Deposit tier 313 €0.25 ...
sold 42 in 12 mo last sale 2026-10-12 Udea 38 units · 5 dockets, last 2026-10-05
€2.70 1.99 → 2.70 on 2025-12-27 · cost then 1.31  Markup 73% landed 1.56 · net 2.70  €2.59
Keep −€0.25 €  73% no change €1.14 profit / unit  ✓ ✗
```
Buttermilk row: `now 2.50, sold at 2.80 until 2026-07-11 (no sale since)`.

**Browser check (headless Google Chrome over the DevTools protocol**, session
minted for user 1, window 1600×1000; scripts in the session scratchpad):
```
rows=57, Alpine running
Keep:      73% no change €1.14 profit / unit
−€0.25:    57% −16 pts €0.89 profit / unit      hidden: price_action=lower, new_price=
typed 2.49: 59% −14 pts €0.93 profit / unit     cell tinted: true; hidden: price_action=custom, new_price=2.49
back to Keep: 73% no change; Confirm with Keep asks nothing
confirm() texts: "Confirm Your Organic Nature Lime juice 200ml and change its till price from €2.70 to €2.45?"
                 "... from €2.70 to €2.49?"
grid columns: 190px 120px 90px 150px 80px 250px 150px 110px
console errors/warnings: []
```
Real submit (confirm() accepted, CSRF and middleware exercised):
```
after confirm flash: 8711521925056: confirmed (written on the till). Your Organic Nature Lime juice 200ml: €2.70 → €2.45 on the till (net 2.7000 → 2.4500); label queued.
row: €2.45 ... lowered from €2.70 2026-10-17 · Admin ... Confirmed Undo   (opacity 0.55)
undo confirm text: Undo also restores the price to €2.70. Continue?
after undo flash: 8711521925056: back to suggested; price restored to €2.70.
console errors/warnings: []
DB after: PRICESELL=2.7000 status=suggested review=NULL confirmed_by=NULL attributes_has_deposit=false
          label_logs price_update for the lime juice: 4 (2 from Revision 1, 2 from this run); suggested=57 confirmed=0
```

### 16. Tests and docs for Revision 3 — done
Changed: `tests/Feature/DepositScreenTest.php`,
`tests/Feature/DepositPriceReviewServiceTest.php`,
`docs/features/barrel-deposit-tracking.md`. `tests/Concerns/CreatesDepositPosTables.php`
needed nothing for `app_settings`: it is a normal migration, so it exists
under `RefreshDatabase` (the settings test proves it).

Screen test, assertions updated as the plan lists: `'Deposit codes'`,
`'54 units · 12 dockets, last 2026-07-15'`, `'0 of 1 decided'`, `'−€0.25'`;
`'Last price change'` and the `<option value="markup" selected>` assertions
dropped. Three further changes the plan did not list (Deviation R3-5):
`'Products'` → `'Price review'`; `assertSeeInOrder(['cost 1.31', '(till)'])` →
`'landed 1.51 · net 2.70'`; in the lower-then-reset test
`'Price review: 1 of 1 rows decided'` → `'1 price reviewed'`.
Screen tests added (11): tier / brand / none headers; sort by markup lowest
first (and name order by default); confirm with `lower`; confirm with
`custom` 2.49; confirm with `keep` (no write, no label log) and `custom`
without a price (422-style validation error, row stays suggested); reject
ignores `price_action`; a refused write keeps the confirmation and flashes
`price_review`; confirm-all by `barrel_code_id`; confirm-all by `brand`
(case-insensitive); undo restores 2.70, clears the four columns and the till
attributes, two label-log rows, page shows the Undo confirm text;
`deposits.settings` 20 → landed 1.20, 150 → validation error.
Service tests added (9): landed / markup / profit at 15 % (1.564, 72.63 %);
`vat_ratio`; `sold_12m` counts discounted lines and skips a sale 400 days old;
`last_sale_on` may be a discounted day while `last_sold` is the full-price
one; no sales → nulls; `setGrossPrice` at 23 %; refusals; `undo` after a
lowering; `undo` of a kept row.

While adding the settings test I found that my step 14 rewrite of `index()`
had also removed `updateSettings()` (it sat between `index()` and
`updateTier()`); restored. Step 12's route check had passed before that.

Docs: Routes block (`group`/`sort` params, confirm-all filters,
`price_action`, `undo`, `deposits.settings`); Data (`kept | lowered | set`,
`app_settings` key `deposits.delivery_pct`); Services (till ticket lines with
discounted lines ignored, landed markup, changed since last sale, sold in
12 months; this is step 10's doc part too); Screen rewritten for 2a (strip,
review band, grid, one-step confirm, Undo, bulk confirms never change prices,
delivery setting, landed-markup note); runbook 3b.

Check:
```
php artisan test --filter=Deposit  ->  Tests:    87 deprecated, 5 passed (478 assertions)   (92 tests, 0 failed)
./vendor/bin/pint --test <10 changed PHP files>  ->  PASS ... 10 files
```

### 11. Runbook count — done
Changed: `docs/features/barrel-deposit-tracking.md`
```
$ grep -n "deposit migrations" docs/features/barrel-deposit-tracking.md
331:   `php artisan migrate --force` (the five deposit migrations).
```

## Deviations (Revision 3)

1. **`lines` alias renamed `line_count`** (step 10). `LINES` is a reserved
   word in MySQL; the plan's query fails on the dev POS with a 1064 syntax
   error. Same query otherwise; same 616 / 6,157 split.
2. **Status filter has five segments, not four.** "Suggested + confirmed"
   (the existing default, so decided rows show dimmed next to open ones) plus
   Suggested n / Confirmed n / Rejected n / All. Without it the default view
   would have no segment selected.
3. **Delivery charge is three small forms** (−, number, +), not one. In one
   form the submit button's `delivery_pct` and the number input's
   `delivery_pct` are both sent and PHP keeps the last, so − would have sent
   the unchanged value. Same route and behaviour.
4. **Page container widened** from `max-w-7xl` to `max-w-[1600px]` and the
   grid's minimum from 1200px to 1300px. Inside `max-w-7xl` with the admin
   sidebar, the Product column measured 106px in Chrome at 1600px wide; it is
   now 190px. Below that the grid scrolls, as planned.
5. **Three test assertions changed beyond the plan's list** (step 16, above):
   the new page has no "Products" heading, no cost-source text (the Markup
   cell shows landed and net), and the counter wording changed. Each was
   replaced by the equivalent fact on the new page; none was dropped.
6. **Undo confirm assertion** checks the `@js` output (`€2.70`), since
   `@js` JSON-escapes the euro sign; the browser shows "€2.70".
7. **Kept from the old view, not named in step 15**: the Remove button for
   manual rows (Decision cell), the "manual" marker, the "cost then" figure on
   the step line, and the explanatory paragraph (moved under the strip
   heading). New price shows the recorded review (`kept` / `lowered from` /
   `set from`) on any reviewed row, suggested ones included.
8. **`npm run build` run locally** so the new arbitrary Tailwind classes are in
   `public/build` (gitignored). Production builds on deploy
   (`deploy-streamlined3.sh` runs `npm run build`).

## Verification (Revision 3)

1. `php artisan test --filter=Deposit`
   ```
   Tests:    87 deprecated, 5 passed (478 assertions)
   ```
   92 tests (67 before + 5 step 10 + 11 screen + 9 service), 0 failed.
2. `php artisan test`
   ```
   Tests:    1048 deprecated, 16 failed, 43 passed (5050 assertions)
   UdeaScrapingServiceTest 7, CashReconciliationTest 3, FruitVegLabelPrintingTest 2,
   ProductTest 2, BackupConfigurationTest 1, TestScraperControllerTest 1
   ```
   The same 16 as the Revision 1 baseline. No new failures.
3. `./vendor/bin/pint --test` on the 10 changed PHP files → `PASS ... 10 files`.
4. `php artisan route:list --name=deposits` → `Showing [10] routes`, including
   `PATCH deposits/settings deposits.settings`.
5. Step 10 dev figures: lime juice step `1.99 → 2.70 on 2025-12-27`; Landpark
   Carbonated Lemon no step, `last_sold.net` 1.42; Ommelanden Buttermilk
   changed since sale (now 2.50, sold at 2.80 until 2026-07-11); Wisselwaar
   Basmati no step; 25 rows with a step; `forRows()` 0.35 s for 57 rows.
   Lime juice `sold_12m` = 42 (last sale 2026-10-12).
6. `/deposits` as a manager, in headless Chrome (see step 15): groups by tier
   (313: 36, 315: 17, 9936: 4), Delivery charge 15 %, strip with 5 codes [corrected in Revision 4: 25 codes, see Notes R4-1] and 3
   on; `?group=brand&sort=markup` (Bald, Beutelsbacher, Craft …) and
   `?group=none&sort=sales` render with 57 rows; one real confirm with
   −€0.25 on the lime juice then Undo, ending with `PRICESELL` 2.7000 and the
   row suggested; delivery charge + then − (flashes "set to 16%." / "set to
   15%."; the row was then deleted so dev runs on the default). Console
   errors and warnings: none on any page.
7. `git status --short`: below. Only files named in steps 2–16, plus the
   pre-existing ones.

## Files changed (end of Revision 3)
```
 M app/Http/Controllers/DepositController.php
 M app/Http/Requests/UpdateProductDepositRequest.php                 (new in R3)
 M app/Models/ProductDeposit.php
 M docs/deposit/README.md                                            (pre-existing, Planner)
R  docs/deposit/implemented.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/implemented.md   (pre-existing)
R  docs/deposit/plan.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/plan.md                 (pre-existing)
 M docs/features/barrel-deposit-tracking.md
 M resources/views/deposits/index.blade.php
 M routes/web.php
 M tests/Concerns/CreatesDepositPosTables.php
 M tests/Feature/DepositScreenTest.php
?? app/Models/AppSetting.php                                         (new in R3)
?? app/Services/Deposits/DepositPriceReviewService.php
?? database/migrations/2026_10_17_000001_add_price_review_to_product_deposits.php
?? docs/deposit/implemented.md                                       (this report)
?? docs/deposit/plan.md                                              (Planner)
?? tests/Feature/DepositPriceReviewServiceTest.php
```
Dev data changed in Revision 3: two `label_logs` price_update rows for the
lime juice (the browser confirm and its Undo); the lime juice's till
attributes were written by the confirm and cleared by the Undo. `app_settings`
has no `deposits.delivery_pct` row (default 15). `public/build` rebuilt.
Nothing committed.

## Notes for Planner (Revision 3)

1. **A second "changed since last sale" row**: Ommelanden Low fat yoghurt 1L
   is on the till at net 3.95 while its last full-price sale (2026-05-11) was
   at 3.30, a rise of 0.65. Probably a deliberate rise with no sale since, but
   it is the opposite direction from the Buttermilk; worth the owner's eye.
2. **Undo after a till sync error.** Undo always clears the till attributes
   through `syncProduct()`; if the till write fails the row still goes back to
   suggested and the error shows only after the next render. Fine for now.
3. **Brand confirm sends the brand as displayed.** `confirmAll` re-derives the
   brand from the till name and compares case-insensitively, so a product
   renamed between render and click falls out of the group. Harmless.
4. **`Reset` is still on the backend** (`action=reset`), not shown, as the plan
   says. A row reviewed under Revision 1's flow while still suggested shows
   its pill and the choice controls together; Confirm then records the new
   choice over it, and with Keep that drops the old `price_review_from`, so a
   later Undo cannot restore the Revision 1 lowering. No such row exists on dev
   (all were reset) or on production (never reviewed), so I left it.
5. **Headless Chrome is available on this machine** (`/usr/bin/google-chrome`),
   and a logged-in session can be minted for user 1 without a password
   (scripts `boot.php`, `mint.php`, `cdp.mjs`, `flow.mjs`, `pct.mjs` in this
   session's scratchpad). Later plans can ask for real browser checks instead
   of leaving them to the owner. The owner may still want one look at the
   layout at their usual screen width.

## Revision 4

Plan revision 4 (steps 19, 17, 18 in that order).

### Baseline (Revision 4)
HEAD: 5775fbef. Working tree as in "Files changed (end of Revision 3)" above,
unchanged since; no new files.

### 19. The Modernist look on `/deposits` — done
Changed: `resources/css/modernist.css` (new; checked first, did not exist),
`vite.config.js` (input added), `resources/views/layouts/admin.blade.php`
(`@stack('styles')` after the `@vite([...])` line),
`resources/views/deposits/index.blade.php` (rewritten in the system's
classes), `docs/features/barrel-deposit-tracking.md` (Screen: the design
system, scoped, light ground; the bar replaces the header buttons).

- **Stylesheet** read through the design MCP (project "Incomplete request",
  `_ds/modernist-5f4ad5c4-…/styles.css`, 10,555 bytes) and the 2a mock-up
  (`Bottle Deposits Review.dc.html`) for the inline styles. Every rule is
  prefixed `.ds-modernist`; the body rules sit on `.ds-modernist` itself
  (plus `color-scheme: light`); `:focus`, `:focus-visible`, `::selection` and
  the `box-sizing` reset are scoped; `:root` tokens and the Archivo `@import`
  stay global. Page section `.dr-*` at the end: `dr-strip`, `dr-band`,
  `dr-kicker`, `dr-stepper`, `dr-cols`, `dr-head`, `dr-group`, `dr-row`
  (`.is-decided` 0.55), `dr-price`, `dr-bar > i`, `dr-meter`, `dr-choice`,
  `dr-euro`, `dr-custom`, `dr-decision`, `dr-muted`, `dr-accent`, `dr-amber`,
  `dr-small`, `dr-nowrap`, `dr-footer`. Two small overrides of the Tailwind
  forms plugin: `.dr-check` restores the native checkbox so `accent-color`
  applies, and `select.input` keeps the plugin's chevron with room for it.
- **View**: `.nav` bar (brand, Till synced, Sync / Recompute as
  `btn-secondary`); layout header keeps the title and the Barrel codes link;
  flashes as `tag-neutral` / `tag-outline` blocks; strip; band with `.field`
  labels, the −/number/+ stepper, `.seg` Status and Group by, `.input` Sort,
  `btn-primary` Confirm all; grid with `dr-group` headers (`btn-secondary`
  Confirm n); rows with `.seg` Keep / −€0.25, `.input` € price, ✓ `btn-primary
  btn-icon` and ✗ `btn-secondary btn-icon` with the Lucide paths from the
  mock, `tag-accent` Confirmed, `tag-neutral` Rejected / kept, `tag-outline`
  till error, `btn-ghost` Undo / Remove. Add a product stays Tailwind (light,
  `dark:` classes dropped) with a 2px top rule. Alpine bindings unchanged.
- `npm run build` → `public/build/assets/modernist-CMkFyny1.css 14.66 kB`.

Check (headless Chrome 1600×1000, logged in as user 1):
```
.dr-price font-family: "Archivo, system-ui, sans-serif"; document.fonts.check('800 20px Archivo'): true
Confirm all: background rgb(236, 48, 19), border-radius 0px
sidebar link font-family: "Figtree, ui-sans-serif, system-ui, sans-serif, ..."   (unchanged)
.ds-modernist background: rgb(243, 242, 242)
console errors/warnings: []
```
Behaviour re-run on the new markup (same scripts as Revision 3):
```
Keep 73% no change €1.14 / −€0.25 57% −16 pts €0.89 / typed 2.49 59% −14 pts, cell .dr-custom: true
confirm() texts: "... from €2.70 to €2.45?", "... from €2.70 to €2.49?"; Keep asks nothing
real confirm: 8711521925056: confirmed (written on the till). Your Organic Nature Lime juice 200ml: €2.70 → €2.45 on the till (net 2.7000 → 2.4500); label queued.
row dimmed 0.55; undo confirm text: Undo also restores the price to €2.70. Continue?
after undo: 8711521925056: back to suggested; price restored to €2.70.
DB: PRICESELL=2.7000 status=suggested review=NULL attributes_has_deposit=false; suggested=57 confirmed=0
console errors/warnings: []
```
(The confirm/Undo ran twice: the first run's flash read came back empty
because the script still looked for the Tailwind flash class. Lime juice
`price_update` label-log rows on dev: 8 in all.)
Screenshot of the bar, strip, band and first tier group:
`/tmp/claude-1000/-var-www-html-osmanagercl/642cfe52-65a1-4e22-b3f0-fdac33ba9059/scratchpad/r4-first-tier-group.png`
(1344 × 3433).

### 17. Row density — done
Changed: `resources/css/modernist.css` (`.dr-cols`, `.dr-row` padding,
`.dr-name`, `.dr-sub`, `.dr-sales1`, row select), `resources/views/deposits/index.blade.php`,
`tests/Feature/DepositScreenTest.php`.
- Sales: line 1 `sold n in 12 mo · last <date>` (or `no till sales`), line 2
  `Udea n units · n dockets` with `title="last docket <date>"` (or `added by
  hand`), line 3 the alias; all `white-space: nowrap`.
- Price: the gross, one line `3.78 → 4.02 · 2025-11-29`, the rule text and
  "Udea cost then x" in the cell's `title`, then `now 2.50, was 2.80 (no sale
  since 2026-07-11)` only when it applies.
- Product: the same three facts on two lines (see Deviation R4-2).
- Screen test: `'54 units · 12 dockets'` plus `'last docket 2026-07-15'`; the
  price-columns test seeds one `posSale()` 3 days ago and asserts
  `sold 4 in 12 mo · last <date>`.

Check, headless Chrome 1600×1000 on `/deposits` (57 rows), as first built
to the plan's widths and after the changes in Deviation R4-1 and R4-2:
```
plan widths:  cols 319px 160px 125px 150px 70px 240px 150px 100px
              row height max 105, min 87; 78 one-line texts clipped (e.g. "sold 29 in 12 mo · last 2026-09-26", "3.20 → 3.85 · 2026-04-21")
              text needs: Sales line 1 202px, price step 131px; the Product cell (name, code, 24px select) alone is ~86px
final:        cols 292px 215px 160px 140px 60px 225px 130px 92px
              max 76 (Ommelanden Buttermilk 1l and Low fat yoghurt 1L: step/changed lines), min 69
              plain rows max 69; rows with a step, alias or changed-since-sale line max 76
              lime juice row 69; clipped one-line texts 0; code line + tier select fits on every row (257 of 259px)
console errors/warnings: []
```
Screenshot: the one named under step 19 (same page, final widths).
```
php artisan test --filter=Deposit  ->  Tests:    87 deprecated, 5 passed (480 assertions)   (92 tests, 0 failed)
```

### 18. Keep the browser-check scripts — done
Changed: `docs/deposit/scripts/browser/` (new): `boot.php`, `mint.php`,
`cdp.mjs`, `flow.mjs`, `pct.mjs`, `README.md` as listed, plus `lib.mjs` and
`measure.mjs` (Deviation R4-4). Paths are relative to the scripts
(`dirname(__DIR__, 4)` for the repo); the `.mjs` scripts run `mint.php`
themselves and take the page path as an argument; Chrome profiles go to a
throwaway dir under the system temp dir and are removed afterwards; Chrome
path from `CHROME`, default `/usr/bin/google-chrome`; the base URL is
`APP_URL` (printed by `mint.php`). `boot.php` exits unless `APP_ENV=local`.

Check, from the repo root:
```
$ node docs/deposit/scripts/browser/cdp.mjs /deposits
{ "url": "/deposits", "title": "OS Manager - Admin", "alpine": true, "rows": 57 }
console errors/warnings: []

$ APP_ENV=production php docs/deposit/scripts/browser/mint.php
Refusing to run: APP_ENV is production, not local.        (exit 1)
```
The other scripts, same run:
```
cdp.mjs /deposits "Your Organic Nature Lime juice 200ml": keep 73% no change · lower 57% −16 pts · custom 59% −14 pts (tinted) · two confirm texts (€2.45, €2.49) · console []
measure.mjs: Archivo / true / rgb(236, 48, 19) / 0px / sidebar Figtree; columns 292 215 160 140 60 225 130 92; heights 69–76, plain max 69, with extra lines max 76; clipped 0; console []
pct.mjs: brand groups Bald, Beutelsbacher, Craft (57 rows); flat 57 rows; + → "Delivery charge set to 16%." · input 16; − → "set to 15%." · input 15; console []
flow.mjs: confirmed with €2.70 → €2.45; row decided, opacity 0.55; undo asked "Undo also restores the price to €2.70. Continue?"; back to suggested, price restored to €2.70; console []
DB after: PRICESELL=2.7000 status=suggested review=NULL; suggested=57 confirmed=0; app_settings row deleted (default 15)
no osm-browser-check-* profile left in /tmp
```

## Deviations (Revision 4)

1. **Column widths sized to the text.** Step 17's template could not hold
   its own one-line rule in Archivo: Sales line 1 needs 202px of text (136px
   available) and the price step line 131px (101px available); 78 lines were
   clipped. Measured and set to
   `minmax(0,1fr) 215px 160px 140px 60px 225px 130px 92px`: Sales and Price
   wider, Sales line 1 at 12px, the room taken from the flexible Product
   column and from Markup now, Udea, New markup and Decision, which had
   slack. No gap between columns: 2a has none and uses 1px cell dividers,
   which a gap would break.
2. **Product cell on two lines, not three.** With the name, the code line and
   a 24px tier select stacked, the cell alone is ~86px, over both budgets.
   The same three facts now sit on two lines: the name (one line, ellipsis,
   full name in the `title`), then `barcode · Udea code` with the tier select
   beside it (it wraps under when the column is narrower, so nothing is
   lost below 1600px). The "Deposit tier" label became the select's
   `title` / `aria-label`, and the options read `313 · €0.25`.
3. **Cell padding 10px × 12px** (2a's own New-price cell padding), not
   "14px": the mock uses 12px, and 14px would add 8px to every row.
4. **Two extra files in `docs/deposit/scripts/browser/`**: `lib.mjs` (the
   Chrome / session plumbing the four `.mjs` scripts would otherwise each
   copy) and `measure.mjs` (step 17's height check, so the next layout change
   can be measured the same way).
5. **The flash blocks** use `tag-neutral` (success) and `tag-outline` (error)
   at block size; the system has no alert component.

## Verification (Revision 4)

1. `php artisan test --filter=Deposit` →
   `Tests:    87 deprecated, 5 passed (480 assertions)`; 92 tests, 0 failed.
2. `./vendor/bin/pint --test` on `tests/Feature/DepositScreenTest.php`,
   `boot.php`, `mint.php` → `PASS ... 3 files`. (No other PHP changed in
   Revision 4.)
3. Step 17: max 76px (rows with a changed-since-sale line), min 69px, plain
   rows max 69px, lime juice 69px; budgets ≤ 84 and ≤ 72 met. Screenshot
   `/tmp/claude-1000/-var-www-html-osmanagercl/642cfe52-65a1-4e22-b3f0-fdac33ba9059/scratchpad/r4-first-tier-group.png`;
   the same view from the repo script:
   `/tmp/claude-1000/-var-www-html-osmanagercl/642cfe52-65a1-4e22-b3f0-fdac33ba9059/scratchpad/r4-final.png`.
4. Step 18: `node docs/deposit/scripts/browser/cdp.mjs /deposits` →
   `console errors/warnings: []` (above).
5. `git status --short`: below; Revision 3's list plus exactly
   `docs/deposit/scripts/browser/`, `resources/css/modernist.css`,
   `vite.config.js`, `resources/views/layouts/admin.blade.php`.
6. Step 19: `.dr-price` Archivo, `fonts.check('800 20px Archivo')` true,
   Confirm all `rgb(236, 48, 19)` / `0px`, sidebar link still Figtree, console
   clean; screenshot as in 3.

## Files changed (end of Revision 4)
```
 M app/Http/Controllers/DepositController.php
 M app/Http/Requests/UpdateProductDepositRequest.php
 M app/Models/ProductDeposit.php
 M docs/deposit/README.md                                            (pre-existing, Planner)
R  docs/deposit/implemented.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/implemented.md   (pre-existing)
R  docs/deposit/plan.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/plan.md                 (pre-existing)
 M docs/features/barrel-deposit-tracking.md
 M resources/views/deposits/index.blade.php
 M resources/views/layouts/admin.blade.php                           (new in R4)
 M routes/web.php
 M tests/Concerns/CreatesDepositPosTables.php
 M tests/Feature/DepositScreenTest.php
 M vite.config.js                                                    (new in R4)
?? app/Models/AppSetting.php
?? app/Services/Deposits/DepositPriceReviewService.php
?? database/migrations/2026_10_17_000001_add_price_review_to_product_deposits.php
?? docs/deposit/implemented.md                                       (this report)
?? docs/deposit/plan.md                                              (Planner)
?? docs/deposit/scripts/browser/                                     (new in R4)
?? resources/css/modernist.css                                       (new in R4)
?? tests/Feature/DepositPriceReviewServiceTest.php
```
Dev data changed in Revision 4: lime juice `price_update` label-log rows now
10 (three confirm-then-Undo runs); price, status and till attributes back
where they started; `app_settings` has no `deposits.delivery_pct` row.
`public/build` rebuilt. Nothing committed.

## Notes for Planner (Revision 4)

1. **The strip shows 25 codes, not 5, and my Revision 3 report was wrong to
   say "5 codes".** `index()` lists every Udea barrel code with
   `unit_price > 0`, so crates and pallets (Epsk €0.90, Europallet €15.00,
   the EPS GROEN crates …) sit beside the five bottle deposits. That query has
   not changed since cycle 2; the old table showed the same 25 rows. In 2a's
   five-column strip it takes five rows and ~550px above the price review.
   Suggest a filter (codes seen on product lines, or a flag) in a later cycle.
2. **`@stack('styles')` switches on a dead push.** `fruit-veg/index.blade.php`
   has pushed a `slideIn` keyframe and a `.product-card-container` transition
   to `styles` all along, with no stack to receive it. Both now apply on
   `/fruit-veg` (an entry animation and a 0.3 s transition on its cards).
   Probably what was intended; worth a glance.
3. **Confirm all wraps** onto its own line under the band's controls at
   1600px (the five-segment Status filter is wide). Harmless; dropping
   "Suggested + confirmed" to "Open + done" or moving Confirm all into the
   bar would put it back on one line.
4. **Product names over ~45 characters are cut** with an ellipsis at 1600px
   (full name in the tooltip and on the product page). On wider screens the
   column grows.
5. **Tailwind forms plugin vs the system.** The plugin restyles checkboxes
   and selects; two small overrides (`.dr-check`, `select.input`) keep the
   system's look. Any further Modernist page will meet the same two.

## Revision 4 — fix after DONE (owner report, 2026-10-17)

The owner, trying `/deposits` on dev in Chrome: "strange behavior when I
press the price change buttons". Claude in Chrome was not connected to this
session, so it was reproduced in headless Chrome with **real pointer
events** (`Input.dispatchMouseEvent`); every earlier check had used
`element.click()` from a script, which does not move focus and so missed it.

**Cause.** The system's `.seg-opt input` (and `.radio input`) is
`position: absolute`. Nothing above it was positioned, so each hidden radio
was placed against the viewport, outside the admin shell's scrolling
`<main>`. The 57 rows' radios stretched the window to 5,026px (viewport
913px), and a click focused the radio, which scrolled the **window** and
pushed the whole shell off screen. The click then landed on nothing: in the
reproduction a click on −€0.25 left the choice at Keep, and
`elementFromPoint` at every control returned `<html>`. The 2a mock-up has no
app shell, which is why it never showed.

**Fix.** `resources/css/modernist.css`: `.ds-modernist, .ds-modernist
.seg-opt, .ds-modernist .radio { position: relative; }`, with a comment.
Also in `resources/views/deposits/index.blade.php`: picking Keep or
−deposit clears a typed custom price (`x-on:change="custom = ''"`).
Without that, the € box kept showing 2.49 while Keep was selected and
would be submitted; nothing wrong was submitted, but the screen
contradicted itself. New `docs/deposit/scripts/browser/clicks.mjs` (and a
README row) runs the real-pointer test. `npm run build` run.

Check:
```
before: window 1600×913, document 5026 tall; click −€0.25 → choice keep (unchanged); controls hit <html>
after (clicks.mjs):
start          windowScrollY 0, documentHeight 913, choice keep, 75% no change
click −deposit windowScrollY 0, choice lower, 64% −11 pts, submits price_action=lower
click Keep     choice keep, 75% no change
type 2.49      choice custom, 13% −62 pts, submits price_action=custom new_price=2.49
click Keep     choice keep, custom "" (cleared), submits price_action=keep new_price=
console errors/warnings: []
measure.mjs: heights 69–76, clipped 0, Confirm all rgb(236, 48, 19) (Product column 307px: the window's stray scrollbar is gone)
flow.mjs: confirmed €2.70 → €2.45, Undo restored €2.70; console []
php artisan test --filter=Deposit -> Tests: 87 deprecated, 5 passed (480 assertions)
```
Note for Planner: the Revision 3 Tailwind view had the same pattern (`sr-only`
radios are `position: absolute` too), so it likely showed the same jump; it
is gone with that view. Any page inside the admin shell that hides inputs
with absolute positioning should give them a positioned parent. Lime juice
`price_update` label-log rows on dev: 12.

## Revision 5

Plan revision 5 (steps 20 and 21).

### Baseline (Revision 5)
HEAD: 5775fbef. Working tree as in "Files changed (end of Revision 4)" plus
the fix after DONE (`docs/deposit/scripts/browser/clicks.mjs` inside the
already-new folder); 21 entries in `git status --short`.

### 20. Deposit codes strip shows the codes that matter — done
Changed: `app/Http/Controllers/DepositController.php` (`index()`: `$codesAll`
from `?codes=all`, `$seenCodes` = distinct `DepositSighting::barrel_code` as
strings, `$stripTiers` = `$tiers` filtered to `charge_customer` or a seen
code; passes `stripTiers`, `codesAll`, `tierCount`; `$tiers` unchanged),
`resources/views/deposits/index.blade.php` (strip loops `$stripTiers`; link
"Show all n codes" via `request()->fullUrlWithQuery(['codes' => 'all'])`, or
"Show deposit codes only" via `request()->fullUrlWithoutQuery(['codes'])`),
`tests/Feature/DepositScreenTest.php`, `docs/features/barrel-deposit-tracking.md`
(one sentence under Screen, strip bullet).

Test: new `test_strip_shows_charged_and_seen_codes_unless_all_are_asked_for`
(Europallet €15.00, off, no sighting: absent by default, present with
`codes=all`; 10046 off with one sighting: present; the link keeps
`group=brand`; "Show deposit codes only" on the all view). New helper
`sighting()`. `test_page_renders_the_three_cards` **did** need a change, the
opposite of the plan's expectation: its "Statiegeld 7" tier is created with
`charge: false`, so it now gets one `sighting('7')` and the assertion stays.

Check, headless Chrome on dev:
```
/deposits                         cells 313, 315, 322, 9936, 10046 · one strip row · link "Show all 25 codes" -> /deposits?codes=all
/deposits?codes=all&group=brand   25 cells (2, 5, 7, 18, 27, 34, … 10603) · 5 strip rows · link "Show deposit codes only" -> /deposits?group=brand
console errors/warnings: []
php artisan test --filter=Deposit -> Tests: 88 deprecated, 5 passed (491 assertions)   (93 tests, 0 failed)
```

### 21. Confirm all moves into the bar — done
Changed: `resources/views/deposits/index.blade.php` (the Confirm all form
moved from the band to the `.nav` bar after Recompute suggestions, still
`btn-primary`, the bar's only primary), `resources/css/modernist.css`
(`.dr-band` top padding 20px → 18px, Deviation R5-1),
`docs/deposit/scripts/browser/measure.mjs` (its Confirm-all checks now look
in `.nav`, Deviation R5-2), `docs/features/barrel-deposit-tracking.md` (Bar
and band bullets).

Check:
```
first build:  band height 98px, controls on one line, Confirm all in .nav, none in .dr-band
after R5-1:   band height 96px, one line of controls
screenshot (viewport, no clip, after .dr-strip scrollIntoView()):
  /tmp/claude-1000/-var-www-html-osmanagercl/642cfe52-65a1-4e22-b3f0-fdac33ba9059/scratchpad/r5-viewport.png
  shows the strip (5 cells), the band on one line, the grid header, Tier 313 and its first eight rows
console errors/warnings: []
```

## Deviations (Revision 5)

1. **Band top padding 18px, not 2a's 20px.** With Confirm all gone the
   controls sit on one line, but the band measured 98px against the
   ≤ 96px check: the kicker / "Price review" / counter block is ~60px and
   2a's padding is 20 + 14 with 2px rules. 2px off the top gives 96px.
2. **`measure.mjs` updated** (an existing Revision 4 file): it read the
   Confirm all colour and radius from `.dr-band .btn-primary`, which no
   longer exists; it now reads `.nav .btn-primary`.

## Verification (Revision 5)

1. `php artisan test --filter=Deposit` →
   `Tests:    88 deprecated, 5 passed (491 assertions)`; 93 tests, 0 failed.
2. `./vendor/bin/pint --test app/Http/Controllers/DepositController.php tests/Feature/DepositScreenTest.php`
   → `PASS ... 2 files`.
3. `cdp.mjs /deposits` → `console errors/warnings: []`. `clicks.mjs` →
   unchanged: window never scrolls (document 913 = viewport), −deposit 64 %
   −11 pts, Keep 75 % no change, typed 2.49 → custom 13 % −62 pts, Keep clears
   it; console clean. `measure.mjs`: Confirm all `rgb(236, 48, 19)` / `0px`,
   rows 69–76px, clipped 0.
4. Strip 5 cells by default, 25 with `?codes=all`; band 96px, one line;
   screenshot path above.
5. `git status --short`: the same 21 entries as at the baseline, no new files.

## Files changed (Revision 5)
Within the existing list: `app/Http/Controllers/DepositController.php`,
`resources/views/deposits/index.blade.php`, `resources/css/modernist.css`,
`tests/Feature/DepositScreenTest.php`, `docs/features/barrel-deposit-tracking.md`,
`docs/deposit/scripts/browser/measure.mjs`. `public/build` rebuilt. No dev
data changed in Revision 5 (no submits). Nothing committed.

## Notes for Planner (Revision 5)

None new. The plan's guess that "Statiegeld 7" was charged was the one
surprise (step 20 above).
