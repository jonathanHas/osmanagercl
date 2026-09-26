# Cycle 18 — Scheduler: restore the four data jobs that stopped running, retire the dead kernel

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

`app/Console/Kernel.php::schedule()` is dead code under this application's bootstrap, so the five jobs it declares have not been scheduled (finding `docs/planImp/findings/2026-09-26-dead-console-kernel-schedule.md`). Four of them are data imports that should run and come back exactly as declared, in the one file the bootstrap reads. The fifth, the every-ten-seconds KDS monitor, stays off: the KDS page detects orders itself and the job is documented as legacy; the last time it ran unattended it left 3.9 million stale queue rows. The dead kernel file is deleted, a test pins the schedule so it cannot silently drift again, and the deploy notes gain a one-line check.

## Context

- `bootstrap/app.php` → `Application::configure()->withRouting(... commands: __DIR__.'/../routes/console.php')`; no console kernel is bound, so `App\Console\Kernel` is never instantiated. Nothing references the class (`grep -rn "Console\\Kernel" app/ bootstrap/ config/ tests/ routes/` → nothing).
- `app/Console/Kernel.php::schedule()` declares, verbatim:
  - `sales:import-daily --yesterday` `->dailyAt('06:00')->onOneServer()->withoutOverlapping(30)` ("Import yesterday's sales data every morning at 6 AM")
  - `sales:import-daily --last-week` `->weekly()->sundays()->at('05:00')->onOneServer()->withoutOverlapping(60)` ("Import last 7 days every Sunday to catch any missed data")
  - `sales-accounting:import --days=7` `->dailyAt('06:10')->onOneServer()->withoutOverlapping(30)`
  - `pos:populate-daily-summaries --last-days=7` `->dailyAt('06:15')->onOneServer()->withoutOverlapping(30)` ("runs after sales import")
  - `kds:monitor` `->everyTenSeconds()->onOneServer()->withoutOverlapping()` — **not restored** (see Goal). `kds:monitor` only dispatches `MonitorCoffeeOrdersJob` to the `default` queue; `docs/features/kds-coffee-system.md` lists that job as "Legacy queue job (kept for backward compatibility)"; the KDS page uses SSE plus `KdsRealtimeController` polling every 2 s; `docs/development/known-issues.md` (~line 899) records the 3.9M stale rows.
- All four commands exist with those options: `sales:import-daily {--start-date=} {--end-date=} {--today} {--yesterday} {--last-week}`, `sales-accounting:import {--start-date=} {--end-date=} {--days=7} {--force}`, `pos:populate-daily-summaries {--start-date=} {--end-date=} {--last-days=365} {--force}`.
- `routes/console.php` today: `sales:import-daily --today` 20:00, `suppliers:send-daily-sales` 20:15, `fruit-veg:prune-thumbnails` Sunday 05:30 (cycle 17g, with a comment explaining why schedules live here), `customers:send-statements` monthly on the 1st 07:00. All use `->onOneServer()->withoutOverlapping(n)`.
- Cron on production runs `php artisan schedule:run` every minute (deployment docs). `php artisan schedule:list` prints what is scheduled.
- Deploy notes: `DEPLOYMENT_SCRIPTS_README.md` (has a post-deploy verification section with `php artisan migrate --force` etc.), `docs/deployment/production-guide.md`.
- Suite baseline: 15 failed / 629 passed.

## Constraints

- Do not commit, push or deploy.
- The four restored entries keep their commands, options, times and overlap settings exactly as declared in the kernel; only the file changes.
- `kds:monitor` is not scheduled anywhere. The command and the job stay in the codebase (manual use, backward compatibility).
- No command's behaviour changes.

## Out of scope

- Whether the KDS should have a server-side monitor again (a product decision; the page-side detection is what runs today).
- Backfilling data the jobs missed while unscheduled (`sales:import-daily --start-date … --end-date …`, `sales-accounting:import --force`, `pos:populate-daily-summaries --last-days=N` can be run by hand once this ships; noted for the owner, not part of this cycle).

## Steps

### 1. Move the four data jobs into `routes/console.php`
Files: `routes/console.php`
What: add, with the kernel's comments carried over and the same chaining, in time order (05:00 weekly backfill, 06:00, 06:10, 06:15) above the 20:00 entries:
```php
Schedule::command('sales:import-daily --last-week')->weekly()->sundays()->at('05:00')->onOneServer()->withoutOverlapping(60);
Schedule::command('sales:import-daily --yesterday')->dailyAt('06:00')->onOneServer()->withoutOverlapping(30);
Schedule::command('sales-accounting:import --days=7')->dailyAt('06:10')->onOneServer()->withoutOverlapping(30);
Schedule::command('pos:populate-daily-summaries --last-days=7')->dailyAt('06:15')->onOneServer()->withoutOverlapping(30);
```
Add a comment above them: "Moved from app/Console/Kernel.php (cycle 18): that kernel is not bound by bootstrap/app.php, so its schedule() never ran. `kds:monitor` was deliberately not moved — the KDS page detects orders itself and the job is legacy." Keep the 17g comment about this being the only schedule file.
Check: `php artisan schedule:list` shows eight entries: the four above plus the existing four, each with the right cron expression (`0 5 * * 0`, `0 6 * * *`, `10 6 * * *`, `15 6 * * *`).

### 2. Delete the dead kernel
Files: `app/Console/Kernel.php` (delete)
What: remove the file. Nothing references it; Laravel 11/12 applications do not have one.
Check: `grep -rn "Console\\\\Kernel" app/ bootstrap/ config/ routes/ tests/` → nothing; `php artisan list` still works; `composer dump-autoload` locally so the classmap is fresh (the deploy runs `composer install --optimize-autoloader`, cycle 14's check).

### 3. Pin the schedule
Files: `tests/Feature/ScheduleTest.php (new)`
What: resolve `Illuminate\Console\Scheduling\Schedule` from the container (the console routes are loaded by the test kernel), collect `->events()` and map each to `[$event->command basename after 'artisan ', $event->expression]`. Assert the eight expected pairs are present, and assert no event's command contains `kds:monitor`. (Strip the PHP binary and `artisan` path from `$event->command` with a regex; compare the tail.)
Check: `php artisan test --filter=ScheduleTest` green; remove one entry temporarily and confirm the test fails, then restore.

### 4. Deploy notes and the finding
Files: `DEPLOYMENT_SCRIPTS_README.md`, `docs/deployment/production-guide.md`, `docs/development/known-issues.md`, `docs/planImp/findings/2026-09-26-dead-console-kernel-schedule.md`
What: in both deploy documents' post-deploy checks add: `php artisan schedule:list` must show the eight scheduled commands (list them); if any is missing, the schedule file was not deployed. Known issues: an entry "Scheduled jobs declared in app/Console/Kernel.php never ran (2026-09-26)" with cause, the four restored, the one retired, and the backfill commands the owner can run by hand for the gap. Append "Fixed in cycle 18" to the finding (the Planner archives it).
Check: `grep -n "schedule:list" DEPLOYMENT_SCRIPTS_README.md docs/deployment/production-guide.md` → both hit.

### 5. Format
What: `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean.

## Verification

1. `php artisan schedule:list` → eight entries, `kds:monitor` absent.
2. `php artisan test --filter=ScheduleTest` → green; `php artisan test` → 15 failed, the identical set; passed = 629 + 1 (or + the number of tests in the new file).
3. `git status --short` → `routes/console.php` modified, `app/Console/Kernel.php` deleted, the new test, the docs; nothing else beyond cycles 17f/17g's uncommitted files.
4. Manual, dev: `php artisan sales:import-daily --yesterday` runs to completion by hand (it is the job that will fire first at 06:00 after deploy; running it once proves the command still works against the current POS schema), and `php artisan schedule:test` offers the new entries.

## Risks

- **First mornings after deploy**: the 06:00 job imports yesterday, the 06:10 job seven days of accounting data, the 06:15 job seven days of summaries. They were designed to run daily and are idempotent for existing rows unless `--force`. Anything older than a week stays missing until the owner backfills by hand (Out of scope).
- **Load at 06:00–06:15** on the POS database: the same three jobs the kernel intended, on a single server; overlap protection carried over.
- **The weekly `--last-week` at 05:00 Sunday and the prune at 05:30 Sunday** do not overlap in purpose or tables.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end; ran `php artisan schedule:list` (eight entries, the four restored with the exact cron expressions, `kds:monitor` absent), confirmed the kernel file is gone and unreferenced, the five schedule tests pass and were shown to fail, both deploy documents carry the check. Reran `php artisan test`: 15 failed / 634 passed, the identical set (629 + 5).

**Steps 1–5: pass.** The implementer also proved the first morning job works against the current POS schema with real data (462 rows, idempotent on a second run) and used `schedule:test --name=kds:monitor` to show the monitor is truly unscheduled.

**Deviations:** none.

**Dating the gap (Planner, from git):** `bootstrap/app.php` has used the Laravel 11 layout since the repository's first commit (2025-07-16). `app/Console/Kernel.php` was added on 2025-08-12 and last edited 2026-02-16. So its `schedule()` was **never** read: the four data jobs have never run on a schedule since they were declared, and only `sales:import-daily --today` (in `routes/console.php`) has been keeping same-day sales current. Any report built on the yesterday/weekly imports, the accounting import or the POS daily summaries has depended on runs by hand.

**Notes for Planner.**
1. Backfill still owed: **told to the owner** with the dated window; commands in known-issues.
2. `--today` at 20:00 and `--yesterday` at 06:00 are a coherent pair: **agreed**, not a duplicate.
3. `schedule:test --name` for proving one entry: **noted**.
4. No other pre-11 leftovers: **good**; the kernel was the only survivor.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-cycle-18-scheduler/` with the finding.
