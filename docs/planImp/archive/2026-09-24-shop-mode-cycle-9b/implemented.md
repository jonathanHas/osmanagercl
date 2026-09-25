# Shop mode cycle 9b — delivery list completeness and two cosmetic fixes — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-24

## Baseline
HEAD: f1ae53ad
Pre-existing dirty files: **clean**

```
$ git status --short
(no output)
```
The owner has committed cycles 8 and 9, so the tree is clean again and everything
below is mine. All three items in this cycle came out of cycle 9's live browser
walkthrough.

## Pre-flight

**The icon component does not merge attributes** — the risk the plan flagged in
step 3 is real:
```
$ grep -c 'attributes' resources/views/components/shop/icon.blade.php
0
```
`x-shop.icon` builds its own `class` from `$props` and never renders
`{{ $attributes }}`, so an `x-show` written on the component tag would be silently
dropped and both icons would always be visible. The plan's fallback applies. I am
toggling the two `.shop-toast__icon` spans themselves rather than wrapping the
icons in extra spans: `.shop-toast__icon` is `display: grid; place-items: center`
(line 399), which `x-show` restores correctly when it clears its inline
`display: none`, and it avoids a nested element inside a 40px circle.

## Steps
### 1. Open sessions are never windowed — done
Changed: `app/Http/Controllers/Shop/DeliveryController.php` (two queries via a new
private `sessions()` builder; `$counts` scoped with `whereIn`),
`tests/Feature/Shop/ShopDeliveryTest.php` (one regression test)

Check output:
```
$ php artisan test --filter=ShopDeliveryTest
  Tests:    18 passed (103 assertions)
```

**The regression test was verified to catch the original bug**, not just to pass.
I restored the committed (windowed) controller from HEAD and re-ran it:
```
--- against the OLD controller ---
  ⨯ old open sessions are listed
  To contain: href="http://osmanager.local/shop/deliveries/scan?delID=d-old&amp;supplierID=999"
  Tests:    1 failed (2 assertions)
--- restored ---
  ✓ old open sessions are listed
  Tests:    1 passed (5 assertions)
```

And against the real dev database, badge and list now agree:
```
badge (unwindowed open count): 5
list rows with the new query:  5
AGREE
  cef92f82  Coolfin    2026-08-08 08:03:51
  778fb73c  Mossfield  2026-07-30 12:55:37
  9e5c52db  Coolfin    2026-06-20 10:23:41
  6a6ddf0b  Coolfin    2026-06-20 10:21:40
  97b3c357  Imbibe     2026-05-20 09:25:19
```
`97b3c357` is the May Imbibe session that was invisible before. The count is 5
rather than the 6 in the cycle 9 report because I completed the Udea session
during that walkthrough.

### 2. Honest wording when no invoice lines are loaded — done
Changed: `resources/js/shop/delivery-scan.js`, `resources/js/shop/delivery-summary.js`
(a `hasInvoice` getter each), `resources/views/shop/delivery-scan.blade.php`,
`resources/views/shop/delivery-summary.blade.php`

Check output:
```
scan     total=0  -> hasInvoice false   total=42 -> hasInvoice true
summary  total=0  -> hasInvoice false   total=42 -> hasInvoice true
summary  progress=null -> hasInvoice false
```
The `progress = null` case matters on the summary, whose `progress` starts null
before `load()` returns; the getter's `!!` guard covers it.
```
$ grep -c "No invoice lines loaded" resources/views/shop/delivery-scan.blade.php → 1
$ php artisan test --filter=ShopViewContractTest → 7 passed (97 assertions)
```
The scan screen now reads "No invoice lines loaded · N issues" instead of
"0 of 0 items · N issues", hides the progress bar in that state, and carries a
`shop-meta` line above the list explaining that the rows below were scanned but
cannot be checked. Unexpected rows stay listed. The summary header reads
"no invoice lines loaded" in place of "0 items expected".

### 3. Client toast icon follows the tone — done
Changed: `resources/views/shop/stock-scan.blade.php`, `resources/views/shop/delivery-scan.blade.php`

Check output:
```
$ grep -c 'name="alert"' resources/views/shop/stock-scan.blade.php     → 1
$ grep -c 'name="alert"' resources/views/shop/delivery-scan.blade.php  → 2
$ php artisan test --filter="ShopViewContractTest|ShopStockScanTest|ShopDeliveryTest"
  Tests:    33 passed (222 assertions)
```
The delivery-scan count is 2, not the plan's 1. Both are accounted for:
```
82:  <div class="shop-empty__icon"><x-shop.icon name="alert" size="xl" /></div>   (cycle 8's error state)
153: <span class="shop-toast__icon" x-show="toast && toast.tone !== 'ok'" ...>     (mine)
```
The plan's expectation overlooked the pre-existing error empty state. Stock scan
has no such state, so it reads 1 as predicted.

### 4. Build and format — done
```
$ npm run build                    → ✓ built in 10.26s
$ ./vendor/bin/pint --dirty        → PASS 2 files
$ ./vendor/bin/pint --test --dirty → PASS 2 files
```

## Deviations

1. **A private `sessions()` query builder instead of two inline queries.** The plan
   said "replace the single windowed query with two". Written out twice, the
   five-column select and the supplier join would be duplicated verbatim; both
   callers now share one builder and differ only by status and limit. Behaviour is
   exactly as specified.

2. **The toast directive goes on the `.shop-toast__icon` span, not on a new
   wrapper.** The plan offered two shapes and said to confirm which was needed.
   It is needed: `x-shop.icon` never renders `{{ $attributes }}`, so an `x-show`
   on the component tag would have been dropped silently and both icons would
   always have shown. Rather than the plan's fallback of wrapping each icon in an
   extra `<span>` inside the 40px circle, I toggle the two styled spans
   themselves — `.shop-toast__icon` is `display: grid; place-items: center`, which
   `x-show` restores correctly, and it avoids nesting inside a fixed-size circle.

## Verification

1. `php artisan test --filter=Shop` → **pass**, `Tests: 128 passed (416 assertions)`
   — exactly the 128 the plan predicts.

2. `php artisan test` → **pass**: `Tests: 17 failed, 528 passed (2036 assertions)`,
   exactly the plan's prediction. Cycle 9 ended at 17 failed / 527 passed; the one
   extra pass is the new regression test. The 17 are the identical pre-existing
   set, confirmed by class:
```
  3 Tests\Feature\CashReconciliationTest
  2 Tests\Feature\FruitVegLabelPrintingTest
  2 Tests\Feature\ProductTest
  1 Tests\Feature\TestScraperControllerTest
  2 Tests\Feature\WasteLogTest
  7 Tests\Unit\UdeaScrapingServiceTest
```

3. `git diff --stat app/Http/Controllers/DeliveryLegacyController.php resources/views/delivery-legacy/`
   → empty. **pass** — no legacy controller or office view change.

4. `grep -rn "<script\|<style" resources/views/shop/` → no output;
   `head -c ... | cmp - docs/design/shop-mode/shop.css` → `DESIGN-BLOCK-IDENTICAL`.
   **pass** — no `APP ADDITIONS` rule was needed.

5. `./vendor/bin/pint --test --dirty` → `PASS 2 files`; `npm run build` →
   `✓ built in 10.26s`. **pass**

6. Manual — **partly run.** The database half is verified directly (step 1 above:
   badge 5, list 5, AGREE, with the May Imbibe session present). The three
   browser-visible halves are **not** observed: the delivery list rendering the
   Imbibe row, the "No invoice lines loaded · N issues" wording on a real
   unsynced session, and the alert-vs-tick toast icons. I did not reopen the
   browser for this because the previous walkthrough is finished and two of the
   three checks would need me to provoke a failing save on Stock scan, which
   writes. They are quick to confirm by eye and I can do them on request.

## Files changed

The tree was clean at baseline, so everything here is mine.

```
 M app/Http/Controllers/Shop/DeliveryController.php    (step 1)
 M resources/js/shop/delivery-scan.js                  (step 2: hasInvoice)
 M resources/js/shop/delivery-summary.js               (step 2: hasInvoice)
 M resources/views/shop/delivery-scan.blade.php        (steps 2 and 3)
 M resources/views/shop/delivery-summary.blade.php     (step 2)
 M resources/views/shop/stock-scan.blade.php           (step 3)
 M tests/Feature/Shop/ShopDeliveryTest.php             (step 1: one test)
?? docs/planImp/implemented.md                         (this file)
```

`public/build/` is gitignored, so the rebuilt assets do not appear.

## Notes for Planner

- **The open list is now unbounded, and on the dev database that is five rows.**
  The plan's Risk holds: if a real backlog builds up, the list grows. Two of the
  five open sessions have zero items scanned and are from June and July, which
  suggests sessions do get abandoned rather than completed. Worth the owner
  knowing that the Shop list will now show that backlog honestly where it used to
  hide anything older than the window.
- **`$counts` is now scoped with `whereIn`** rather than grouping the whole
  `deliveriesScanItems` table. On the dev database that table backs 1,201
  sessions, so this is also a small performance improvement — the old query
  grouped every row to use at most 60 of them.
- **The "no invoice lines" state is common, not exceptional.** Of the five open
  sessions, any whose supplier is not the one currently synced will show it,
  because the scratch table holds one delivery at a time. The new wording is
  honest, but the underlying situation — staff scanning against an invoice that
  is not loaded — may be worth a cycle of its own: today the only way to load one
  is for a manager to sync on the office page.
- **`x-shop.icon` swallows attributes.** Worth knowing generally, not just here:
  any directive or aria attribute written on that component tag is dropped
  without error. Adding `{{ $attributes }}` to the `<svg>` would fix it for
  everyone, but it would change a shared component used on every shop screen, so
  I left it. A one-line step in a housekeeping cycle.
- **Nothing committed, pushed or deployed.** The tree was clean at the start of
  this cycle, so this diff is cycle 9b alone — the first time that has been true
  since cycle 7b.
