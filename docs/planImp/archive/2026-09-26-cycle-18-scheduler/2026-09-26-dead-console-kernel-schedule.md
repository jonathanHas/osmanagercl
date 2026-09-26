# Finding — five scheduled jobs declared in `app/Console/Kernel.php` never run

Raised by: Opus (Implementer, cycle 17g), verified by Fable (Planner)
Date: 2026-09-26
Severity: outside Shop mode; potentially affects sales reporting and the KDS. Needs the owner's decision.

## What was found

`bootstrap/app.php` uses the Laravel 11/12 bootstrap and registers only `routes/console.php` for console routing and scheduling. It binds no console kernel, so `App\Console\Kernel::schedule()` is never called. The jobs it declares are therefore not scheduled:

| Declared in `app/Console/Kernel.php` | When | Status |
|---|---|---|
| `sales:import-daily --yesterday` | daily 06:00 | not scheduled |
| `sales:import-daily --last-week` | weekly, Sunday 05:00 | not scheduled |
| `sales-accounting:import --days=7` | daily 06:10 | not scheduled |
| `pos:populate-daily-summaries --last-days=7` | daily 06:15 | not scheduled |
| `kds:monitor` | every ten seconds | not scheduled |

What `php artisan schedule:list` actually shows (from `routes/console.php`): `sales:import-daily --today` (20:00), `suppliers:send-daily-sales` (20:15), `customers:send-statements` (monthly), and, from cycle 17g, `fruit-veg:prune-thumbnails` (Sunday 05:30).

## What it may mean

- Same-day sales do import (the `--today` job runs). The `--yesterday` catch-up and the weekly `--last-week` backfill do not, so a missed evening leaves a hole nobody fills.
- `sales-accounting:import` and `pos:populate-daily-summaries` do not run at all unless something outside this repository runs them.
- `kds:monitor` is described in the kernel as ensuring orders are detected when nobody has the KDS page open; if that is still wanted it is not happening via the scheduler. It may be running under a separate process manager; check on the server before assuming.

## Options

1. Move all five entries to `routes/console.php` as declared (times unchanged) and delete the dead `schedule()` method, with a comment in `routes/console.php` that it is the only schedule file. Simplest, restores the declared intent.
2. Move some: the owner decides per job whether it is still wanted (e.g. the KDS monitor may be obsolete or handled elsewhere; the accounting import may be superseded).
3. Leave as is, documented.

Recommendation: option 2, decided by the owner, then a short cycle; in any case add "`php artisan schedule:list` must show the expected jobs" to the deploy checklist so a schedule can never silently vanish again.

## What this does not affect

Cycle 17g's prune is scheduled in the live file and shows in `schedule:list`. Nothing in Shop mode depends on the dead entries.

---

**Fixed in cycle 18.** The four data jobs were moved to `routes/console.php` with
their declarations unchanged, `app/Console/Kernel.php` was deleted,
`tests/Feature/ScheduleTest.php` pins the schedule, and both deployment documents
gained a `php artisan schedule:list` post-deploy check. `kds:monitor` was
deliberately left unscheduled. See `docs/development/known-issues.md`, "Scheduled
Jobs Declared in `app/Console/Kernel.php` Never Ran", including the backfill
commands for the period the jobs were off.
