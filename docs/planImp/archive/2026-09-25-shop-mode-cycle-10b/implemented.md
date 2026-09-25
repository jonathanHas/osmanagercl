# Shop mode cycle 10b — barcode changes queue a label, dismissals are not prints — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-24

## Baseline
HEAD: f1ae53ad
Pre-existing dirty files — cycles 9b and 10, both accepted, still uncommitted (27
entries). Cycle 10b adds to several of them; my own file list is at the end.

**This cycle changes the MySQL schema.** Step 2 adds an enum value to
`label_logs.event_type`. It runs on the next deploy — the owner should expect a
migration, on a ~15k-row table, which is effectively instant.

## Steps
### 1. Model: two constants, a dismiss logger, metadata fillable — done
Changed: `app/Models/LabelLog.php`
```
$ php artisan tinker --execute="echo json_encode(LabelLog::QUEUE_EVENTS); ..."
["new_product","price_update","requeue_label","barcode_change"]
["label_print","label_dismiss"]
```
`metadata` added to `$fillable` with a comment saying why there is deliberately no
`array` cast: `ProductController` json-encodes it itself and a cast would
double-encode.

### 2. Migration: add `label_dismiss` to the MySQL enum — done
Changed: `database/migrations/2026_09_24_230000_add_label_dismiss_event_type_to_label_logs.php` (new)
```
$ php artisan migrate
  2026_09_24_220000_widen_label_logs_event_type_on_non_mysql ..... 5.22ms DONE
  2026_09_24_230000_add_label_dismiss_event_type_to_label_logs .. 79.77ms DONE

$ SHOW COLUMNS FROM label_logs LIKE 'event_type'
enum('new_product','price_update','label_print','requeue_label','barcode_change','label_dismiss')

rows still intact: 15194
empty event_type rows (truncation check): 0
```
Six values, and the truncation check confirms the `MODIFY COLUMN` listed every
existing value — no row was blanked. Note the 220000 migration also ran here: it
had never been applied to the dev MySQL database (it is a no-op there by design).

### 3. Service: barcode changes queue, dismissals resolve — done
Changed: `app/Services/LabelQueueService.php`
Candidates now come from `LabelLog::QUEUE_EVENTS` and are resolved by
`LabelLog::RESOLVE_EVENTS`; `$lastPrints` renamed `$lastResolved` throughout,
`countsByEventType()` gained the `barcode_change` key, docblocks updated.
```
$ grep -n "lastPrints\|EVENT_LABEL_PRINT" app/Services/LabelQueueService.php
(no output — fully renamed, no stray single-event reference)
```

### 4. Controller: dismiss writes a dismissal, dismiss-all, hub count, reason text — done
Changed: `app/Http/Controllers/LabelAreaController.php`, `routes/web.php`
```
labels/dismiss-all  labels.dismiss-all  ['labels.manage']
```
`hub()`'s `array_sum($labelCounts)` became `$labelCounts['total']`, with a comment
recording that the array already carried its own total and was being counted twice.

### 5. Office shelf-labels page: a fourth filter and badge — done
Changed: `resources/views/labels/index.blade.php`
```
$ git diff --stat resources/views/labels/index.blade.php
 resources/views/labels/index.blade.php | 17 +++++++++++++++++
 1 file changed, 17 insertions(+)
```
17 added, **0 removed** — the two map entries slot in before the existing closers,
so nothing else in that 1,270-line file moved.

### 6. Shop screen: clear posts to dismiss-all, plural fix — done
Changed: `resources/views/shop/labels.blade.php`
`data-clear-url` now points at `labels.dismiss-all`, and the queue meta reads
"1 label · 1 A4 sheet" rather than "1 labels · …" — the wording I flagged from the
cycle 10 walkthrough.
```
$ php artisan test --filter=ShopViewContractTest → 8 passed (104 assertions)
```

### 7. Tests — done
Changed: `tests/Feature/Shop/ShopLabelsTest.php`
```
$ php artisan test --filter=ShopLabelsTest
  ✓ barcode change queues a label
  ✓ manager can dismiss the whole queue
  ✓ employee cannot dismiss the whole queue
  ✓ office hub counts the queue once
  ✓ dismissals do not appear as recent prints
  ... 16 tests ...
  Tests:    16 passed (66 assertions)
```
**16, not the plan's 17.** The class held 11 after cycle 10 and the plan lists five
new tests; 11 + 5 = 16. The plan's figure is an arithmetic slip, not a missing test
— every test it names is present.

### 8. README, format, build — done
```
$ ./vendor/bin/pint --dirty        → PASS 15 files
$ ./vendor/bin/pint --test --dirty → PASS 15 files
$ npm run build                    → ✓ built in 10.67s
```

## Deviations

1. **The hub test has to repoint the `mysql` connection.** `office_hub_counts_the_queue_once`
   failed at first with:
```
SQLSTATE[HY000] [1044] Access denied for user 'osmanager'@'localhost' to database ':memory:'
 (Connection: mysql, SQL: select count(*) as aggregate from `product_translations`)
```
   `hub()` counts `ProductTranslation`, which hardcodes `protected $connection = 'mysql'`.
   Under `phpunit.xml` the default connection is sqlite but the `mysql` connection
   still has the real driver with `DB_DATABASE=:memory:`, so any model pinned to it
   is unusable in tests. The test now points that connection at its own in-memory
   sqlite and creates the one table, the same trick the fixture already uses for
   `pos`. Three lines, confined to that test.

2. **Five new tests, not six.** See step 7 — the plan's expected total was one out.

## Verification

1. `php artisan migrate` on dev MySQL → **pass**, both label-log migrations ran;
   `SHOW COLUMNS` lists six enum values; 15,194 rows intact and zero truncated.

2. `php artisan test --filter=Shop` → **pass**, `Tests: 146 passed (493 assertions)`,
   16 of them in `ShopLabelsTest`.

3. `php artisan test` → **pass**: `Tests: 17 failed, 546 passed (2113 assertions)`.
   Cycle 10 ended at 17 / 541; +5 is the five new tests (the plan predicted +6 from
   the same slip as above). The 17 failures are the identical pre-existing set:
```
  3 Tests\Feature\CashReconciliationTest
  2 Tests\Feature\FruitVegLabelPrintingTest
  2 Tests\Feature\ProductTest
  1 Tests\Feature\TestScraperControllerTest
  2 Tests\Feature\WasteLogTest
  7 Tests\Unit\UdeaScrapingServiceTest
```

4. `git diff app/Http/Controllers/LabelAreaController.php` → **pass**, four hunks
   (import, property/constructor from cycle 10, `hub()`, and the
   `queue()`/`dismiss()`/`dismissAll()` block). Confirmed **0 changed lines** each
   for `clearAllLabels`, `restoreBatch`, `shelfLabels`, `printA4`,
   `processBarcodeScan`.

5. `grep -rn "<script\|<style" resources/views/shop/` → no output;
   `head -c ... | cmp` → `DESIGN-BLOCK-IDENTICAL`. **pass**

6. `./vendor/bin/pint --test --dirty` → `PASS 15 files`. **pass**

   Additionally, both office pages still render on the dev MySQL database after the
   `hub()` and view changes:
```
/labels                  -> 200
/labels/shelf-labels     -> 200
   has barcode_change filter: true
```

7. Manual walkthrough — **not run this cycle.** I ran cycle 10's in the browser on
   the owner's authorisation, and this cycle's list needs the same: it writes
   dismissals, and one item (changing a product's barcode on the office product
   page) is a real edit to a real product's data, which is a bigger step than
   queuing and dismissing a label. Say the word and I will; I would want to agree
   which product to rename first, and I would change it back.

   What is already proven without the browser: the enum accepts `label_dismiss` on
   the real database, both office pages render, the derivation treats dismissals
   exactly like prints, `barcode_change` queues and counts, `dismiss-all` is
   manager-only, and a dismissed product appears nowhere on the office page.

## Files changed

Cycle 10b's own changes — two new files, seven edited:
```
?? database/migrations/2026_09_24_230000_add_label_dismiss_event_type_to_label_logs.php
?? docs/planImp/implemented.md
 M app/Models/LabelLog.php                       (step 1)
 M app/Services/LabelQueueService.php            (step 3)   [also cycle 10, uncommitted]
 M app/Http/Controllers/LabelAreaController.php  (step 4)   [also cycle 10, uncommitted]
 M routes/web.php                                (step 4)   [also cycles 8-10]
 M resources/views/labels/index.blade.php        (step 5)
 M resources/views/shop/labels.blade.php         (step 6)   [also cycle 10, uncommitted]
 M tests/Feature/Shop/ShopLabelsTest.php         (step 7)   [also cycle 10, uncommitted]
 M docs/design/shop-mode/README.md               (step 8)   [also cycles 5-10]
```
Everything else in `git status --short` is cycles 9b and 10.

## Notes for Planner

- **The dev MySQL database had never run `2026_09_24_220000`.** It applied cleanly
  alongside the new one, and it is a no-op on MySQL by design, so nothing changed —
  but it means production has not run it either, and will apply both on the next
  deploy. Two migrations, one of them a real `ALTER TABLE` on ~15k rows.
- **`down()` on the new migration is destructive after any dismissal exists.**
  Rolling back truncates `label_dismiss` rows to `''`, and those products would
  quietly reappear in the queue. The migration says so in a docblock; worth the
  owner knowing it is effectively one-way once staff start using ×.
- **Cycle 10's six walkthrough rows are still `label_print`.** Two of them
  (`#15191 4112`, `#15194 4040`) were dismissals recorded under the old behaviour,
  so the office history will show two prints that never happened. The plan called
  this history and left it; noting it because it is in the owner's real data, not
  a fixture.
- **`ProductTranslation` pins itself to the `mysql` connection**, which makes
  `hub()` — and anything else touching that model — unrenderable under the test
  suite without the workaround in Deviation 1. There are probably other models like
  it. A housekeeping cycle could let the test environment configure `mysql` to the
  test database centrally, rather than each test doing it.
- **The office "Clear All" still writes prints**, per the Constraints, so the two
  clear buttons now behave differently: the Shop one dismisses, the office one
  prints-and-can-be-restored. That is deliberate and documented in the README, but
  it is a difference staff and managers could trip over if anyone compares them.
- **Nothing committed, pushed or deployed.** Cycles 9b, 10 and 10b are stacked in
  one tree.
