# Cycle 18 — Scheduler: restore the four data jobs, retire the dead kernel — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: 9a41cc3d. Cycles 17f and 17g are accepted and archived but still uncommitted,
so the tree carries their files alongside this cycle's. Files changed at the end
separates them.

Test baseline: 15 failed / 629 passed (2658 assertions).

Schedule before this cycle — four entries:
```
0  20 * * *  sales:import-daily --today
15 20 * * *  suppliers:send-daily-sales
30 5  * * 0  fruit-veg:prune-thumbnails
0  7  1 * *  customers:send-statements
```

## Pre-flight

Every claim the plan rests on, checked rather than taken from my own 17g report:

- **Nothing references the kernel.** `grep -rn "Console\\Kernel" app/ bootstrap/ config/ routes/ tests/` returns exactly one line, and it is `Kernel.php`'s own `use Illuminate\Foundation\Console\Kernel as ConsoleKernel` — the parent class it extends. Nothing outside the file mentions it, so deleting it breaks no reference.
- **All four commands exist** and `php artisan list` shows each one: `sales:import-daily`, `sales-accounting:import`, `pos:populate-daily-summaries`, `kds:monitor`.
- **The KDS evidence behind leaving `kds:monitor` off holds.** `app/Console/Commands/MonitorKdsOrders.php:16` does nothing but `MonitorCoffeeOrdersJob::dispatch()`; `docs/features/kds-coffee-system.md:31` lists that job as "Legacy queue job (kept for backward compatibility)"; `docs/development/known-issues.md:898` records the ~3.9M accumulated rows. This is the Planner's call and the evidence supports it.

## Steps

### 1. Move the four data jobs into `routes/console.php` — done

Changed: `routes/console.php` — the four entries in time order above the 20:00
block, each with its original comment, and a block comment explaining where they
came from and why `kds:monitor` did not come with them.

```
$ php artisan schedule:list
0  5  * * 0  sales:import-daily --last-week
0  6  * * *  sales:import-daily --yesterday
10 6  * * *  sales-accounting:import --days=7
15 6  * * *  pos:populate-daily-summaries --last-days=7
0  20 * * *  sales:import-daily --today
15 20 * * *  suppliers:send-daily-sales
30 5  * * 0  fruit-veg:prune-thumbnails
0  7  1 * *  customers:send-statements
```
Eight entries, cron expressions exactly as the plan specifies.

### 2. Delete the dead kernel — done

Changed: `app/Console/Kernel.php` deleted (via `git rm`), `composer dump-autoload`
run so the local classmap is fresh.

```
$ grep -rn "Console\\Kernel" app/ bootstrap/ config/ routes/ tests/
(none)
$ php artisan list          → Laravel Framework 12.20.0, works
$ php artisan schedule:list → still 8 entries
```

### 3. Pin the schedule — done

Changed: `tests/Feature/ScheduleTest.php (new)` — 5 tests.

```
$ php artisan test --filter=ScheduleTest
✓ the expected jobs are scheduled at the expected times
✓ nothing unexpected is scheduled
✓ the kds monitor is not scheduled
✓ every scheduled command exists
✓ every scheduled command guards against overlapping
Tests:    5 passed (49 assertions)
```

Three beyond the plan's two. `every scheduled command exists` would have caught a
schedule entry naming a command that had been renamed or removed — a second way for
a job to be silently dead. `every scheduled command guards against overlapping`
pins the `onOneServer()`/`withoutOverlapping()` the kernel set on every entry, so a
future addition cannot quietly omit them.

**The test was proved to fail**, in both ways that matter:
```
entry removed:   "sales-accounting:import --days=7 is not scheduled"
                 (and "nothing unexpected is scheduled" fails too, at 7)
time changed:    "sales-accounting:import --days=7 runs at the wrong time"
restored:        Tests: 5 passed (49 assertions)
```

### 4. Deploy notes and the finding — done

Changed: `DEPLOYMENT_SCRIPTS_README.md` (a new "Scheduled Jobs (post-deploy check)"
section), `docs/deployment/production-guide.md` (the same under Post-Deployment
Notes), `docs/development/known-issues.md` (a full entry), and the finding file
marked fixed.

Both deploy documents carry the same table of eight commands and the sentence that
there is deliberately no `app/Console/Kernel.php`, so nobody re-creates one.

```
$ grep -n "schedule:list" DEPLOYMENT_SCRIPTS_README.md docs/deployment/production-guide.md
DEPLOYMENT_SCRIPTS_README.md:231
docs/deployment/production-guide.md:166
```

The known-issues entry includes the three backfill commands with placeholder dates,
since the plan puts the backfill itself out of scope but the owner will need them.

### 5. Format — done

`./vendor/bin/pint --test --dirty` → PASS.

## Verification

**1. `php artisan schedule:list`** — eight entries above; `grep -c kds:monitor` → 0.

**2. Tests**
```
$ php artisan test --filter=ScheduleTest   → 5 passed
$ php artisan test                          → 15 failed, 634 passed (2707 assertions)
```
The identical 15. 634 = 629 + 5.

**3. `git status --short`** — see Files changed.

**4. Manual, dev.**

*The job that fires first after deploy.* `sales:import-daily --yesterday` ran to
completion, but reported `Records Processed 0` — dev's POS data ends in August, so
that proves the command starts, not that it still works against the schema. I ran it
again against a date that has data:
```
$ php artisan sales:import-daily --start-date=2026-08-14 --end-date=2026-08-14
Records Processed 462 | Inserted 0 | Updated 462 | 0.84 seconds
```
462 rows really processed, so the command works against the current POS schema. Run
a second time it reported the identical 462 / 0 / 462, which also demonstrates the
idempotency the plan's Risks section relies on — the morning job re-importing a day
it already has changes nothing.

*`schedule:test`.* It is interactive, so I named the entry:
```
$ php artisan schedule:test --name="sales-accounting:import --days=7"
Running ['artisan' sales-accounting:import --days=7] ......... 376.51ms DONE

$ php artisan schedule:test --name="kds:monitor"
INFO  No matching scheduled command found.
```
The second is the useful one: it is independent confirmation that `kds:monitor` is
genuinely unscheduled rather than merely absent from a listing I filtered.

*What this wrote to dev.* Two real imports ran: `sales:import-daily` for 2026-08-14
(462 existing rows re-written with the same values, twice) and
`sales-accounting:import --days=7` via `schedule:test`. Both are the jobs' own
designed behaviour against data they already owned, and both are idempotent; nothing
was created or removed. No cleanup was needed and none was done.

## Deviations

None. The four entries carry their commands, options, times and overlap settings
unchanged; only the file differs.

## Files changed

Mine, this cycle:
```
 D app/Console/Kernel.php
 M DEPLOYMENT_SCRIPTS_README.md
 M docs/deployment/production-guide.md
 M docs/development/known-issues.md
 M docs/planImp/findings/2026-09-26-dead-console-kernel-schedule.md
 M routes/console.php
?? tests/Feature/ScheduleTest.php
```

Still dirty and **not this cycle**: cycles 17f and 17g, accepted and archived but
uncommitted — `app/Http/Controllers/{FruitVeg,Harvest}Controller.php`,
`app/Services/ProductThumbnailService.php`,
`app/Console/Commands/PruneFruitVegThumbnails.php`, `routes/web.php`,
`resources/js/shop/fv-harvest.js`, `resources/views/fruit-veg/{harvest,manage}.blade.php`,
`resources/views/shop/fv-harvest.blade.php`,
`tests/Feature/{FruitVegProductImageTest,Shop/ShopFruitVegTest}.php`,
`tests/Unit/ProductThumbnailServiceTest.php`, and two docs. `routes/console.php`
carries both 17g's entry and this cycle's four.

**Not committed, not pushed, not deployed.**

## Notes for Planner

1. **The backfill is still owed, and the window matters.** The jobs have been off
   for as long as the kernel has been unbound — which the git history could date,
   and I have not. Until someone runs the three commands in the known-issues entry,
   `daily_sales`, the sales-accounting tables and the POS daily summaries have a
   hole for that period, and the reports built on them are quietly short. The
   restored jobs only reach back seven days on their own. Worth telling the owner
   explicitly rather than leaving it in a document.

2. **`sales:import-daily --today` at 20:00 and `--yesterday` at 06:00 overlap in
   purpose.** The evening job imports a partial day so suppliers can be emailed;
   the morning job re-imports the same day complete. That is coherent, and the
   idempotency I measured means the second is cheap. Recording it only so the pair
   is not mistaken for a duplicate and one of them removed.

3. **`schedule:test` is how to check a single entry** without waiting for its time,
   and it needs `--name` with the full command string including options. Worth a
   line in the deploy notes if anyone ever needs to prove one entry works; I did not
   add it, as the plan's check is `schedule:list`.

4. **I checked for other pre-11 leftovers and there are none.** `Kernel.php` was
   one file that looked live and was not, so the obvious question is whether any
   sibling is in the same state. Checked rather than guessed:
   ```
   app/Http/Kernel.php                       absent
   app/Exceptions/Handler.php                absent
   app/Providers/RouteServiceProvider.php    absent
   app/Providers/BroadcastServiceProvider.php absent
   ```
   and `app/Providers/` holds only `AppServiceProvider`, which
   `bootstrap/providers.php` does register. The console kernel was the single
   survivor of the upgrade. Nothing to follow up.
