# Shop mode cycle 10 (Revision 2) — a fixed-query derivation and two fixes — implementation

Status: DONE
Plan revision: 2
Implementer: Opus
Date: 2026-09-24

Scope: steps 10–12 only. Steps 1–9 stand as delivered in Revision 1 and were not
reworked; step 10 rewrites the internals of `LabelQueueService`, whose public
surface is unchanged.

## Baseline
HEAD: f1ae53ad
Working tree carries cycle 9b and cycle 10 Revision 1, both accepted, both
uncommitted (24 entries; the Revision 1 report lists them). Revision 2 touches
five files: `app/Services/LabelQueueService.php`,
`resources/views/shop/labels.blade.php`,
`tests/Feature/Shop/ShopLabelsTest.php`, and the two 2025 label-log migrations.

## Steps

### 10. Derive the queue in a fixed number of queries — done
Changed: `app/Services/LabelQueueService.php`, `tests/Feature/Shop/ShopLabelsTest.php`

A private `candidates()` now does the whole derivation and both public methods
read it. Two queries plus one per 1,000-barcode chunk, instead of one lookup per
barcode. The rule is unchanged — `first()` on a `created_at desc` list is the same
value `MAX(created_at)` returns, and the comparison is still strict.

```
$ php artisan test --filter=ShopLabelsTest
  ✓ queue derivation uses a fixed number of queries
  ✓ queue ignores events and prints outside the window
  ... 11 tests ...
  Tests:    11 passed (43 assertions)
```

On the dev database, with the real 30-day window (currently empty, so this is the
early-return path) and both office pages:
```
counts: {"new_product":0,"price_update":0,"requeue_label":0,"total":0}
queries for countsByEventType(): 1
needingLabels rows: 0 | queries: 1
/labels                  -> 200  (0.07s)
/labels/shelf-labels     -> 200  (0.03s)
```

That proves correctness but not scale, so I replayed the Planner's own
measurement — read-only, 400-day window, the same 3,324 barcodes it timed at
**33.4 s**:
```
NEW shape: 3324 barcodes, 22 queued, 1.22s, 4 chunk queries + 1
```
**33.4 s → 1.22 s**, about 27× faster, and the query count is now bounded by
chunks rather than by barcodes. At the plan's real-world worst case (1,429
barcodes in July 2026) that is two queries, not 1,429.

### 11. Empty state only when there is no error — done
Changed: `resources/views/shop/labels.blade.php`, `tests/Feature/Shop/ShopLabelsTest.php`
```
x-show="! loading && ! error && total === 0"
```
`employee_can_open_the_labels_screen` now asserts that exact expression, so a
future edit that drops the `! error` guard fails the test rather than quietly
showing "All caught up" beside "Something went wrong".

### 12. Correct the two misleading migration comments — done
Changed: `database/migrations/2025_07_22_155113_...` (2 occurrences, `up` and
`down`), `database/migrations/2025_08_07_164348_...` (1)
```
$ git diff -- 'database/migrations/2025_*label_logs*' | grep '^[-+]' | grep -v '^[-+][-+]' | grep -vE '^[-+]\s*//'
(no output — only comment lines changed)

$ git diff --stat -- 'database/migrations/2025_*label_logs*'
 ...155113_add_requeue_label_event_type_to_label_logs_table.php | 10 ++++++----
 ...5_08_07_164348_add_barcode_change_support_to_label_logs.php |  4 +++-
 2 files changed, 9 insertions(+), 5 deletions(-)
```
The second file's wording differed from the plan's quoted sentence ("SQLite stores
the column as text, so it is skipped there"), so I matched the plan's replacement
text to its actual phrasing rather than failing the edit.

### Build and format — done
```
$ npm run build                    → ✓ built in 10.57s
$ ./vendor/bin/pint --dirty        → PASS 13 files
$ ./vendor/bin/pint --test --dirty → PASS 13 files
```

## Deviations

1. **`Collection::merge()` could not be used to accumulate the chunk results, and
   using it was a real bug I had to find.** The first version of `candidates()`
   built the last-print map with `$lastPrints = $lastPrints->merge(...)` per chunk.
   Two existing tests then failed: `dismiss_takes_a_product_off_the_queue` returned
   2 rows instead of 1. Probing showed the derivation keeping a barcode that had a
   newer print:
```
 candidates: ["5000000000024","5000000000017"]
   #1 5000000000017 price_update 22:55:29
   #2 5000000000024 new_product  22:55:29
   #3 5000000000017 label_print  22:55:29     <- newer print, should have dropped it
```
   Cause: barcodes are numeric strings, which PHP stores as **integer** array keys,
   and `Collection::merge()` renumbers integer keys exactly as `array_merge` does.
   So `$lastPrints` came out keyed `0, 1, 2…`, every `get($barcode)` returned null,
   and nothing was ever dropped — the queue would have silently included every
   product that had ever been queued in the window, printed or not.
   Fixed by accumulating into a plain array keyed by barcode, with a comment
   explaining why `merge()` is wrong here. Worth flagging because the plan's step 10
   specifies `pluck('last_print', 'barcode')` per chunk without saying how to
   combine chunks, and `merge()` is the obvious choice.

2. **`candidates()` is reached by reflection in the window test.** The plan's
   `queue_ignores_events_and_prints_outside_the_window` describes assertions on
   what is "listed", but the three fixture barcodes (`OLD`, `STALE-PRINT`,
   `PRINTED`) have no POS products, so `needingLabels()` filters them out and the
   public surface cannot show them. Rather than add three products and test the
   window indirectly, the test reads the derivation itself through a small private
   helper. `candidates()` stays private.

3. **The second migration's comment differed from the plan's quote.** Replaced its
   actual sentence with the plan's intended wording; effect is identical.

## Verification (Revision 2)

1. `php artisan route:list` → **pass**, unchanged from Revision 1: `shop.labels`,
   `labels.queue`, `labels.dismiss`, all under `labels.print`.

2. `php artisan test --filter=Shop` → **pass**, `Tests: 141 passed (471 assertions)`.
   The contract test still lists seven screens.

3. `php artisan test` → **pass**: `Tests: 17 failed, 541 passed (2091 assertions)`.
   Revision 1 ended at 17 / 539; the two extra passes are the two new tests. The
   17 are the identical pre-existing set:
```
  3 Tests\Feature\CashReconciliationTest
  2 Tests\Feature\FruitVegLabelPrintingTest
  2 Tests\Feature\ProductTest
  1 Tests\Feature\TestScraperControllerTest
  2 Tests\Feature\WasteLogTest
  7 Tests\Unit\UdeaScrapingServiceTest
```

4. `git diff --stat app/Http/Controllers/LabelAreaController.php` → `57 insertions,
   92 deletions`, **byte-identical to Revision 1** — step 10 changed the service,
   not the controller. `git diff --stat resources/views/labels/` → empty.

5. `grep -rn "<script\|<style" resources/views/shop/` → no output;
   `grep -c "route(" resources/js/shop/labels.js` → 0;
   `head -c ... | cmp` → `DESIGN-BLOCK-IDENTICAL`. **pass**

6. `./vendor/bin/pint --test --dirty` → `PASS 13 files`; `npm run build` →
   `✓ built in 10.57s`. **pass**

7. Manual walkthrough — **RUN, 2026-09-24, against the live dev app**, driving
   the owner's Chrome, on the owner's authorisation. Signed in as the real
   shop-floor account **jonathanE (id 15, role employee, `labels.manage: false`)**,
   which is the target user for this screen.

   Before: queue total 0, `label_logs` 15,188 rows.

   | Check | Result |
   |---|---|
   | Screen renders | Scan input, both hub tiles, "Print queue", "All caught up" |
   | Scan `4040` | "Apple fruit tray bake · Shelf label · Re-queued · €2.25", ×1 |
   | Scan `4112` | Added; green toast **"Added: cake"** with the tick icon (cycle 9b's tone-aware toast) |
   | Queue meta | "2 labels · 1 A4 sheet"; button "Print 2 labels" |
   | Pluralisation | "Print 1 label" at one row, "Print 2 labels" at two |
   | × on a row | Row gone, toast **"Removed: cake"**, count back to 1 |
   | **Clear queue absent** | Correct — this user is an employee without `labels.manage` |
   | Office agreement | `/labels/shelf-labels` shows "1 Need Labels", the same product, add method "Scanned", €2.25 / €1.98 net, and the filter "Scanned/Re-queued: 1 products" |
   | Print | Queue emptied, `label_print` logged, A4 page 200 / 14,870 bytes |
   | Queue after print | "All caught up" |
   | Home badge | **1** with one product queued, absent at zero; delivery badge 5 alongside |
   | Zebra tile | `href` is `labels/zebra`; the office Zebra page loads |

   **The print click was made server-side, not in the browser, and deliberately.**
   `resources/views/labels/a4-print.blade.php:429` calls `window.print()` on load.
   That opens a native print dialog, which blocks the Chrome extension and would
   have ended the session. I checked for it before clicking anything, then sent the
   identical payload (`products[]` = the ids `queue()` returns) through the kernel
   as jonathanE with a valid CSRF token: `200`, 14,870 bytes, queue emptied,
   `label_print` written. The browser then confirmed "All caught up". So the print
   path is verified end to end except for the native dialog itself, which nobody
   can automate.

   **Not observed:** the manager "Clear queue" button (this account is an employee;
   the test covers the manager case), and the tablet camera.

   After: queue total **0**, as found. Six `label_logs` rows added, all attributed
   to user 15:
```
  #15189 4040 requeue_label   #15192 4040 label_print
  #15190 4112 requeue_label   #15193 4040 requeue_label
  #15191 4112 label_print     #15194 4040 label_print
```

## Files changed

Revision 2 touched five files, all already mine or pre-existing:
```
?? app/Services/LabelQueueService.php                  (step 10, rewritten internals)
?? resources/views/shop/labels.blade.php               (step 11, one x-show)
?? tests/Feature/Shop/ShopLabelsTest.php               (steps 10, 11: +2 tests, +1 assertion)
 M database/migrations/2025_07_22_155113_...php        (step 12, comments)
 M database/migrations/2025_08_07_164348_...php        (step 12, comments)
```
The `??` marks are because cycle 10's Revision 1 files are still uncommitted.
Everything else in `git status --short` is cycle 9b and cycle 10 Revision 1.

## Notes for Planner

- **The `merge()` bug is the interesting one, and it was silent.** Every test that
  existed before Revision 2 still passed with the broken accumulator except the
  two that happened to exercise a print newer than its event. Had the dismiss test
  not existed, this would have shipped as "the queue never empties". The new
  `queue_ignores_events_and_prints_outside_the_window` now covers that rule
  directly rather than incidentally.
- **`countsByEventType()` no longer guards against unknown event types silently.**
  It counts into a fixed three-key array and skips anything else via
  `array_key_exists`. That matches the old behaviour for the three queue types,
  but `barcode_change` — the fourth type the 2025 migration added — is neither
  counted nor queued, by either the old or the new code. If a barcode change is
  meant to trigger a label, it currently does not, on any page. Worth a decision;
  it is out of scope here and pre-existing.
- **The chunk size is 1,000 and the largest real window is ~1,430**, so production
  will do two chunk queries at worst. The chunking only matters if the window is
  ever widened.
- **Office pages got faster for free.** `hub()` runs the counts once and
  `shelfLabels()` runs the derivation twice; both now pay two queries instead of
  one per barcode. Nobody has timed them before and after on a busy month — the
  400-day replay suggests the difference is seconds, not milliseconds.
- **Confirmed in the live UI: a dismissal is indistinguishable from a print.** The
  Revision 1 note was reasoning; it is now observed. After tapping × on "cake", the
  office Shelf Labels page listed **cake under "Recent Label Prints"** and its
  "Printed (7d)" counter had incremented. A manager reading that list will believe
  a label was printed when staff simply took it off the queue. Still deferred, but
  the evidence is no longer hypothetical.
- **"1 labels" reads wrong.** The queue meta is
  `total + ' labels · ' + sheets + ' A4 sheet' + (sheets === 1 ? '' : 's')` — the
  *sheets* are pluralised but the *labels* are not, so a one-row queue reads
  "1 labels · 1 A4 sheet" while the button beside it correctly reads "Print 1
  label". One ternary, cosmetic, not fixed because it is outside steps 10–12.
- **The A4 page auto-prints**, which is right for the office workflow but means the
  Shop flow cannot be fully automated end to end, and that a pop-up blocker on a
  tablet would stop the sheet appearing at all (the plan's Risk). Worth knowing
  that the print dialog is the *only* step in Shop mode that leaves the app's
  control.
- **Nothing committed, pushed or deployed.** Cycle 9b and cycle 10 (both
  revisions) are stacked in one tree, on disjoint files.
