# Voucher activity screen (vouchers cycle 2) — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-29

## Baseline
HEAD: 3fbfcc44
Pre-existing dirty files (Shop mode track and planning files, not mine):
```
D  .delivery-specialist-agent-recommendation.md.kate-swp
D  JFolder_temp/.questions.txt.kate-swp
 M app/Http/Controllers/DeliveryLegacyController.php
 M docs/development/quick-start-guide.md
 M docs/features/shop-mode.md
D  docs/jons_docs/.todo.md.kate-swp
R  docs/planImp/implemented.md -> docs/planImp/archive/2026-09-28-shop-mode-cycle-31/implemented.md
RM docs/planImp/plan.md -> docs/planImp/archive/2026-09-28-shop-mode-cycle-31/plan.md
 M docs/planImp/needed.txt
D  docs/planImp/plan-wedge-focus.md
 M docs/vouchers/README.md
 D docs/vouchers/implemented.md
 M docs/vouchers/plan.md
 M resources/js/shop/delivery-scan.js
 M resources/js/shop/scan-input.js
 M resources/views/components/shop/scan-input.blade.php
 M resources/views/shop/delivery-scan.blade.php
 M tests/Feature/Shop/ShopDeliveryTest.php
 M tests/Feature/Shop/ShopStockScanTest.php
?? docs/planImp/archive/2026-09-28-shop-mode-cycle-32/
?? docs/planImp/implemented.md
?? docs/planImp/plan.md
?? docs/vouchers/archive/2026-09-27-cycle-28-till-voucher-redemption/
?? docs/vouchers/findings/2026-09-29-production-backfill-not-run.md
```
`php artisan test` before any change:
```
   FAIL  Tests\Unit\UdeaScrapingServiceTest
   FAIL  Tests\Feature\CashReconciliationTest
   FAIL  Tests\Feature\FruitVegLabelPrintingTest
   FAIL  Tests\Feature\ProductTest
   FAIL  Tests\Feature\TestScraperControllerTest
  Tests:    15 failed, 806 passed (3441 assertions)
```

## Steps

### 0. Baseline — done
Recorded above; matches the plan's expected `15 failed, 806 passed`.

### 1. Config — done
Changed: `config/vouchers.php` (`activity` block after `sync`)
Check output:
```
$ php artisan tinker --execute="echo config('vouchers.activity.poll_seconds');"
5
```

### 2. Heartbeat — done
Changed: `app/Services/VoucherSyncHeartbeat.php` (new; did not exist)
Key names are class constants (`KEY_LAST`, `KEY_LAST_OK`, `KEY_LAST_SCHEDULED`) so the tests use the same strings.
Check: covered by step 8's `VoucherSyncHeartbeatTest`.

### 3. Sync records the heartbeat — done
Changed: `app/Services/VoucherTillSyncService.php`
The old body of `sync()` is now `private run()`; `sync($since, $source)` wraps it, records ok/failed and rethrows on failure. `errors` added last in `COUNT_KEYS`, incremented in the per-ticket catch. `syncIfDue($source = lookup)` passes its source through.
Check output (the plan says 27 tests at cycle 1; the file has 18):
```
$ php artisan test --filter='VoucherTillSyncServiceTest|ScheduleTest'
  Tests:    23 passed (141 assertions)
```

### 4. Command flag and schedule — done
Changed: `app/Console/Commands/SyncVoucherTillRedemptions.php`, `routes/console.php`, `tests/Feature/ScheduleTest.php`
Check output:
```
$ php artisan schedule:list | grep voucher
  *  *  * * *  php artisan vouchers:sync-till --scheduled  Next Due: 2 seconds from now
$ php artisan vouchers:sync-till
| tickets | applied | partial | no_tender | inactive | unknown | refund | skipped | errors |
| 2       | 0       | 0       | 0         | 0        | 0       | 0      | 2       | 0      |
```
(ScheduleTest passes inside the 23 above.)

### 5. Activity service — done
Changed: `app/Services/VoucherActivityService.php` (new; did not exist)
- Merge order: sorted by `created_at` timestamp desc, then by an internal order (`tx` id×2, `red` id×2+1) instead of the literal `key` string, because string-sorting `tx-9` against `tx-10` puts them the wrong way round. The internal fields are stripped before returning.
- `totals()` sums deducts grouped by `source` in one query.
- Status-change messages format times as `j M H:i`.
Check (dev data, tinker `payload(7, null)`):
```
health: state warn, messages ["The scheduler has never run the till check. This page checks the till itself while it is open."],
        last_check_source command, scheduler_stale 1, last_errors 0, vouchers_without_till_product 0
totals: redeemed_today 0, issued_today 0, outstanding_balance 29, active_vouchers 2, unreviewed_exceptions 1
5 events; newest: tx-14 redeem_till "Redeemed at till" GVVUF2SUACUM -6 → 9, who "Till #430816", status applied
```

### 6. Controller and routes — done
Changed: `app/Http/Controllers/VoucherActivityController.php` (new; did not exist), `routes/web.php` (import + two routes directly above `vouchers/{voucher}/transactions`)
Check output:
```
$ php artisan route:list --name=vouchers.activity
  GET|HEAD       vouchers/activity vouchers.activity › VoucherActivityControl…
  GET|HEAD       vouchers/activity/feed vouchers.activity.feed › VoucherActiv…
routes/web.php:1219 vouchers/activity, :1220 vouchers/activity/feed, :1221 vouchers/{voucher}/transactions
```
The route-order test is in step 8.

### 7. View, navigation — done
Changed: `resources/views/vouchers/activity.blade.php` (new; did not exist), `resources/views/layouts/admin.blade.php` ("Voucher activity" link after "Till exceptions"), `resources/views/vouchers/index.blade.php` ("Activity" before "Generate"), `resources/views/vouchers/exceptions.blade.php` ("Activity" before "All vouchers")
- All Alpine event bindings in the new view use `x-on:`; nullable parts (health messages, `sold_at`, `shortfall`, `voucher_url`) are behind `x-if` templates or `||` defaults.
- A filter change while a fetch is in flight sets `pending` and re-fetches once the first returns (the in-flight guard would otherwise drop the filter change until the next tick).
- Changing the period clears the "seen" set so a different period's rows are not highlighted as new.
- `npm run build` ran clean (the view uses Tailwind classes not used elsewhere, e.g. `bg-purple-800/50`, `bg-blue-900/40`).
Check output (script written to the scratchpad, not `/tmp`):
```
$ php -r '…preg_match_all("/<script>(.*?)<\/script>/s",…)' && node --check …/voucher-activity.js && echo NODE_OK
NODE_OK
```

### 8. Tests — done
Changed: `tests/Feature/VoucherSyncHeartbeatTest.php` (new; did not exist), `tests/Feature/VoucherActivityTest.php` (new; did not exist), `tests/Feature/VoucherTillSyncServiceTest.php` (5 tests added, none changed)
- Heartbeat "broken cache": `Cache::shouldReceive('forever'|'get')->andThrow(...)` (the facade mock), with `Log::spy()` asserting two warnings.
- Per-ticket failure: the POS `TAXES` table is dropped after the sale is created; the ticket-total query joins it, so the ticket fails inside the loop after the ticket list was read (`tickets 1, errors 1`, heartbeat `ok = true`).
- Route-order test asserts the matched route name is `vouchers.activity`.
- Health "stale" test checks both messages: never ran, and `…since 29 Sep 12:00…` after 10 minutes.
Check output:
```
$ php artisan test --filter='VoucherSyncHeartbeatTest|VoucherTillSyncServiceTest'
  Tests:    29 passed (128 assertions)
$ php artisan test --filter=VoucherActivityTest
  Tests:    18 passed (94 assertions)
$ php artisan test --filter='Voucher|Schedule'
  Tests:    82 passed (398 assertions)
```

### 9. Docs — done
Changed: `docs/features/voucher-management.md` (new `## Activity screen (/vouchers/activity)` after the till section, with a health-state table saying what to do for each; `### The sync` mentions `--scheduled`, the heartbeat and `errors`; routes and key files), `docs/FEATURES_INDEX.md` ("Live Activity Screen" bullet), `CLAUDE.md` (voucher line), `docs/deployment/production-guide.md` (nine commands, the `vouchers:sync-till --scheduled` row, the `/etc/cron.d/osmanager` paragraph with how to confirm it and "exactly one trigger"), `docs/deployment/production-deployment-guide.md` (user-crontab instruction replaced with the `/etc/cron.d` form, the same checks).
Check output:
```
$ grep -n "nine\|cron.d/osmanager" docs/deployment/*.md
production-deployment-guide.md:320, 325, 326, 329, 331 (… nine commands, including vouchers:sync-till --scheduled)
production-guide.md:168 must list **nine** commands … 183 **`/etc/cron.d/osmanager`** … 188 cat /etc/cron.d/osmanager
```

## Deviations

1. **Event order tie-break.** The plan says sort by `at` desc, then `key` desc. `key` is a string (`tx-9`, `tx-10`), so a string sort puts same-second rows in the wrong order. The service sorts by the `created_at` timestamp, then by an internal integer (`tx` id×2, `red` id×2+1), stripped before returning. Same intent, same output shape.
2. **Queued re-fetch.** Step 7 says skip a tick while a fetch is in flight. A *filter change* during a fetch would then be dropped until the next tick (up to 5 s), so the view sets `pending` and re-fetches once the current request returns. Polling ticks are still skipped, not queued.
3. **`npm run build`** was run (not listed in the plan) because the new view uses Tailwind classes no other view used. No CSS file changed.
4. **The `node --check` script** was written to the session scratchpad instead of `/tmp/voucher-activity.js`. Same command otherwise.
5. **Plan fact corrected:** step 3 says `VoucherTillSyncServiceTest` had 27 tests at cycle 1; it had 18. All 18 still pass unchanged; 5 were added (23).

## Verification

1. `./vendor/bin/pint --test` on the 12 PHP files I changed or created → `PASS … 12 files`.
2. `php artisan test --filter='Voucher|Schedule|ConfinePinSession'` → `Tests:    90 passed (479 assertions)`.
3. `php artisan test` → `Tests:    15 failed, 835 passed (3577 assertions)`. Failing classes: UdeaScrapingServiceTest, CashReconciliationTest, FruitVegLabelPrintingTest, ProductTest, TestScraperControllerTest — the same 15 as the step 0 baseline; 806 + 29 new tests = 835.
4. `php artisan schedule:list | grep voucher` → `*  *  * * *  php artisan vouchers:sync-till --scheduled  Next Due: 53 seconds from now`.
5. `php artisan route:list --name=vouchers` → `vouchers/activity`, `vouchers/activity/feed`, then `vouchers/{voucher}/transactions`; in `routes/web.php` they are lines 1219, 1220, 1221.
6. `node --check` of the extracted inline script → no output (`NODE_OK` echoed after it).
7. Dev heartbeat:
   ```
   $ php artisan vouchers:sync-till --scheduled; tinker read()
   source=schedule ok=true last_scheduled_at=2026-09-29T13:31:12+01:00 stale=false
   after clearing last-scheduled: stale=true
   ```
   (Cleared the `last-scheduled` key rather than waiting 3 minutes. Dev has no cron, so from here on the dev page will say the scheduler has never run, which is correct on dev.)
8. Simulated till sale on dev: `VOUCHER=GVLH4AU7ASAT TENDER=3 TICKET=999903 … simsale.php` → ticket id `5f459fe5-c7c9-4a93-852a-ea560fbae1c0`; `vouchers:sync-till` → `tickets 3 | applied 1 | skipped 2 | errors 0`; `payload(7, null)` newest event: `tx-15 | Redeemed at till | GVLH4AU7ASAT | -3 -> 17 | Till #999903 | applied`.
   Cleanup: POS rows for that ticket deleted (PAYMENTS 1, TICKETLINES 2, TICKETS 1, RECEIPTS 1). `reset_voucher.php` put GVLH4AU7ASAT back to active €20 (it adds an `activate` audit row with note "Dev reset for till testing"; it does not delete history). The local redemption row (id 7) and its transaction (tx 15) stay in the dev database and in the activity log; they now point at a deleted POS ticket, which is harmless on dev.
9. **Browser check: for the owner** (the Chrome session is a Shop PIN session and office pages go to `/confirm-password`). Checklist is in the plan's Verification 9. Things to look at in particular: the amber "scheduler has never run" banner is expected on dev (no cron); rows from the real till tests show a second line "till HH:mm" that is an hour behind the "When" time (the dev till's UTC clock, finding 3 of `findings/2026-09-28-till-testing.md`).

## Files changed

Mine (vouchers cycle 2):
```
 M CLAUDE.md
 M app/Console/Commands/SyncVoucherTillRedemptions.php
 M app/Services/VoucherTillSyncService.php
 M config/vouchers.php
 M docs/FEATURES_INDEX.md
 M docs/deployment/production-deployment-guide.md
 M docs/deployment/production-guide.md
 M docs/features/voucher-management.md
 M docs/vouchers/implemented.md        (was " D": the committed cycle 1 report had been archived; this file is the new report)
 M resources/views/layouts/admin.blade.php
 M resources/views/vouchers/exceptions.blade.php
 M resources/views/vouchers/index.blade.php
 M routes/console.php
 M routes/web.php
 M tests/Feature/ScheduleTest.php
 M tests/Feature/VoucherTillSyncServiceTest.php
?? app/Http/Controllers/VoucherActivityController.php
?? app/Services/VoucherActivityService.php
?? app/Services/VoucherSyncHeartbeat.php
?? resources/views/vouchers/activity.blade.php
?? tests/Feature/VoucherActivityTest.php
?? tests/Feature/VoucherSyncHeartbeatTest.php
```
Everything in the Baseline list is untouched (Shop mode track and planning files). Not committed.

## Notes for Planner

1. **Transaction timestamps vs till time.** The log's "When" is when the app *recorded* the event (`created_at`); for till rows that is up to a minute after the sale (the scheduler interval). The till's own time is the second line. On production both should be Irish local time; on the dev VirtualBox till the second line is an hour behind (dev artefact).
2. **The seen-set is per period and search.** Changing the period or the search does not highlight the new result set; only rows that arrive on later polls are highlighted. Changing the search does not reset `seen`, so rows that come back into view after clearing a search are highlighted. Harmless; a stricter rule could also reset `seen` on a search change.
3. **The events limit applies per source before merging.** Each of the two queries takes the newest `limit` rows, then the merge takes `limit` again. The result is exact (the newest `limit` across both), but a 30-day window with more than 100 events shows only the newest 100 with no "more" hint. A "showing newest 100" line might help if managers use the 30-day view.
4. **Unreviewed `partial` rows** show as `partial` in red on the log; once reviewed the pill reads "partial · reviewed". Exceptions without a transaction behave the same way. Nothing on this page marks them reviewed (out of scope, as planned).
5. **Dev data left:** redemption id 7 / tx 15 (simulated ticket 999903, POS rows deleted) and two "Dev reset for till testing" `activate` rows on GVLH4AU7ASAT (this cycle) and GVVUF2SUACUM (2026-09-28). All dev-only.
