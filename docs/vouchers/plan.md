# Voucher activity screen (vouchers cycle 2)

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-29

## Goal

Managers and admins get one office page, `/vouchers/activity`, that shows what is happening to gift vouchers as it happens: every issue, till redemption, manual deduction, status change and till exception, newest first, refreshing itself every few seconds with no reload. The top of the page says whether the till check is healthy: when the till was last read, whether the scheduler is still running it, and whether any vouchers cannot be scanned at the till. Today none of that is visible anywhere: the scheduled command's output is discarded, so a stopped scheduler or an unreachable till database looks exactly like a quiet day.

## Context (verified 2026-09-29)

**What exists (vouchers cycle 1, archived at `docs/vouchers/archive/2026-09-27-cycle-28-till-voucher-redemption/`, committed in 421df081):**
- `app/Services/VoucherTillSyncService.php`: `sync(?Carbon $since = null): array` returns counts keyed by `COUNT_KEYS = ['tickets','applied','partial','no_tender','inactive','unknown','refund','skipped']`; `syncIfDue(): ?array` is gated by `config('vouchers.sync.on_lookup')`, throttled by `Cache::add('vouchers:till-sync', 1, lookup_throttle_seconds)`, and swallows every throwable with `Log::warning`. Inside `sync()`, a failure on one ticket is caught, `Log::error`'d and the ticket skipped; nothing counts it. A failure before the loop (till database unreachable, category lookup) throws out of `sync()`.
- `app/Console/Commands/SyncVoucherTillRedemptions.php`: `vouchers:sync-till {--since=}`; prints the counts table; catches throwables, prints the error, returns `FAILURE`.
- `routes/console.php` (end of file): `Schedule::command('vouchers:sync-till')->everyMinute()->onOneServer()->withoutOverlapping(5);`. `tests/Feature/ScheduleTest.php` pins each entry by its command string and asserts `assertCount(9, …)`.
- `app/Services/VoucherPosProductService.php`: `sync(Voucher)`, `categoryId()`. A voucher with `pos_product_id` NULL has no till product.
- Models: `Voucher` (SoftDeletes; statuses `inactive|active|exhausted|deactivated`; `transactions()` is `latest()`), `VoucherTransaction` (`type` `issue|deduct|deactivate|activate`; `source` `manual|till`; `user()`, `voucher()`, `tillRedemption()` HasOne), `VoucherTillRedemption` (statuses `applied|partial|no_tender|inactive|unknown|refund`; `scopeExceptions()`, `scopeUnreviewed()`; `voucher()`, `transaction()`, `reviewer()`).
- **Which till rows have a transaction:** `applied` and `partial` always do (`voucher_transaction_id` set, a `deduct` row with `source = till`). `no_tender`, `inactive`, `unknown` and `refund` never do (`voucher_transaction_id` NULL). So the full log is `voucher_transactions` plus the `voucher_till_redemptions` rows whose `voucher_transaction_id` is NULL.
- `app/Http/Controllers/VoucherController.php` (about 500 lines): `history()` l.123-155 already maps a transaction to label / signed amount / who; copy its rules, do not call it (it is private and per-voucher).
- Routes `routes/web.php` l.1208-1224, group `permission:vouchers.manage`: `vouchers.list`, `vouchers.exceptions`, `vouchers.exceptions.reviewed`, then `vouchers/{voucher}/transactions`. **A new `vouchers/activity` route must be declared before `vouchers/{voucher}/transactions`**, or `{voucher}` swallows it.
- Office views use `<x-admin-layout>` and inline Alpine scripts (no Vite module): `resources/views/vouchers/index.blade.php` (light card style, `x-slot name="header"`), `resources/views/vouchers/list.blade.php` and `exceptions.blade.php` (dark table style: `bg-gray-800`, `divide-gray-700`, status pills `bg-green-800/50 text-green-300` etc.). Copy the dark style.
- Sidebar: `resources/views/layouts/admin.blade.php` l.302-327, the `vouchers.manage` block ends with the "Till exceptions" link (l.319-325).
- Tests: `tests/Concerns/CreatesVoucherPosTables.php` (`createVoucherPosTables()`, `posGoods()`, `posSale()`); `tests/Feature/VoucherTillExceptionsTest.php` has the `userWith()` / `manager()` / `redemption()` helpers to copy; `tests/Feature/VoucherTillSyncServiceTest.php` has `voucher()` / `sale()`. `phpunit.xml`: `CACHE_STORE=array`, POS connection sqlite in memory. No test asserts the exact counts array, so adding a key to the counts is safe.
- Docs: `docs/features/voucher-management.md` (sections `## Till redemption (uniCenta)` l.40, `### The sync` l.69, `## Routes` l.172, `## Key files` l.189); `docs/deployment/production-guide.md` l.163-180 says the post-deploy `schedule:list` "must list **eight** commands" (it is nine since vouchers cycle 1); `docs/deployment/production-deployment-guide.md` l.317-321 tells the reader to install a `www-data` user crontab.

**Production facts (read-only check, 2026-09-29):**
- The scheduler runs every minute from **`/etc/cron.d/osmanager`** as `www-data` (about 1,440 runs a day in the cron journal). It is *not* a user crontab, so `crontab -l` shows nothing. There must be exactly one trigger; never add a second.
- Cache store is `database` on production and dev.
- 39 vouchers, 37 with no till product (the backfill has not been run). Parked by the owner: `docs/vouchers/findings/2026-09-29-production-backfill-not-run.md`. This cycle only makes it visible.
- The dev box has no cron, so on dev "scheduler last ran" will be stale or never. That is correct behaviour on dev, not a bug.

**Owner decisions (2026-09-29):**
1. Managers and admins only: the existing `vouchers.manage` permission. No new permission.
2. Polling, like the coffee display. No websockets, no server-sent events.
3. The page watches; it does not change vouchers. Corrections stay on the existing pages.

## Constraints

- The page is read-only. The only side effect of the feed is `syncIfDue()`, the same sync the scheduler and the lookup screens already run.
- Voucher money rules, the sync's rules and the JSON of `vouchers.lookup` do not change. `sync()`'s new parameter and the new counts key are additive.
- A failure to record or read the heartbeat must never fail a sync, a lookup or the feed.
- No new permission, no seeder change, no npm dependency, no CSS file change. Inline Alpine in the Blade view, as `vouchers/index.blade.php` does.
- Alpine rules from `planimp.md`: no `@` shorthand that is also a Blade directive (write `x-on:error`), and anything nullable behind an `x-show` needs `?.` or an `x-if`.
- Shared files (`routes/web.php`, `routes/console.php`, `resources/views/layouts/admin.blade.php`, `tests/Feature/ScheduleTest.php`) get additive voucher hunks only. The Shop mode track has uncommitted work in the same tree (cycles 32-33: `resources/views/shop/**`, `resources/js/shop/**`, `tests/Feature/Shop/**`, `DeliveryLegacyController.php`); do not touch those files.
- Do not commit, push or deploy. Do not run anything against production.

## Out of scope

- Fixing the 37 vouchers without a till product: no backfill button, no automatic backfill. Health line only.
- Employee access, a Shop mode version of this screen, CSV export, charts.
- Marking exceptions reviewed from this page (link to `/vouchers/exceptions` instead).
- Auto-activation at the till, cash reconciliation `voucher_used`, `TillTransactionRepository::formatReceipt`.
- The misplaced docblock in `tests/Feature/Shop/ShopDeliveryTest.php` (Shop mode track).

## Steps

### 0. Baseline
What: record `git rev-parse --short HEAD`, `git status --short`, and `php artisan test` summary in `implemented.md` before changing anything. Expected about `15 failed, 806 passed` (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2, TestScraper ×1).
Check: the summary line is pasted.

### 1. Config
Files: `config/vouchers.php`
What: add after `sync`:
```php
'activity' => [
    'poll_seconds' => 5,             // how often the activity page asks for the feed
    'limit' => 100,                  // newest events returned per poll
    'scheduler_stale_seconds' => 180, // no scheduled till check for this long = warning
],
```
Check: `php artisan tinker --execute="echo config('vouchers.activity.poll_seconds');"` prints `5`.

### 2. Heartbeat
Files: `app/Services/VoucherSyncHeartbeat.php` (new)
What: a small class recording when the till check ran and how it went.
- Constants `SOURCE_SCHEDULE = 'schedule'`, `SOURCE_COMMAND = 'command'`, `SOURCE_LOOKUP = 'lookup'`, `SOURCE_ACTIVITY = 'activity'`.
- `record(string $source, bool $ok, ?array $counts = null, ?string $error = null): void` writes with `Cache::forever()`, one key per fact so there is no read-modify-write:
  - `vouchers:till-sync:last` → `['at' => now()->toIso8601String(), 'ok' => $ok, 'source' => $source, 'counts' => $counts, 'error' => $error !== null ? Str::limit($error, 300) : null]`
  - `vouchers:till-sync:last-ok` → ISO string, only when `$ok`
  - `vouchers:till-sync:last-scheduled` → ISO string, only when `$source === SOURCE_SCHEDULE` (written whether or not the run succeeded: it proves the scheduler is alive)
- `read(): array` returns `['last' => array|null, 'last_ok_at' => ?string, 'last_scheduled_at' => ?string, 'scheduler_stale' => bool]`, where `scheduler_stale` is true when `last_scheduled_at` is null or older than `config('vouchers.activity.scheduler_stale_seconds')`.
- Both methods wrap their body in `try/catch (\Throwable)`; `record()` logs a warning and returns; `read()` returns the all-null shape with `scheduler_stale => true`.
Check: covered by step 8's `VoucherSyncHeartbeatTest`.

### 3. Sync records the heartbeat
Files: `app/Services/VoucherTillSyncService.php`
What:
- Constructor also takes `VoucherSyncHeartbeat $heartbeat`.
- `sync(?Carbon $since = null, string $source = VoucherSyncHeartbeat::SOURCE_COMMAND): array`. Move the current body into a private method (or wrap it) so that: on normal return, `record($source, true, $counts)`; on a throwable, `record($source, false, null, $e->getMessage())` and rethrow.
- Add `'errors'` to `COUNT_KEYS` (last). Increment `$counts['errors']` in the existing per-ticket `catch` beside the `Log::error`. A run with `errors > 0` still records `ok = true`: the till was read; the health line reports the errors separately (step 4).
- `syncIfDue(string $source = VoucherSyncHeartbeat::SOURCE_LOOKUP): ?array` passes `$source` to `sync()`. Its gate, throttle and catch are unchanged. `VoucherController::lookup()` keeps calling `syncIfDue()` with no argument.
Check: `php artisan test --filter=VoucherTillSyncServiceTest` still passes unchanged (27 tests at cycle 1).

### 4. Command flag and schedule
Files: `app/Console/Commands/SyncVoucherTillRedemptions.php`, `routes/console.php`, `tests/Feature/ScheduleTest.php`
What:
- Signature gains `{--scheduled : Set by the scheduler, so the activity screen can tell its runs from a manual one}`. `handle()` passes `SOURCE_SCHEDULE` when the option is set, else `SOURCE_COMMAND`.
- `routes/console.php`: `Schedule::command('vouchers:sync-till --scheduled')`, same `everyMinute()->onOneServer()->withoutOverlapping(5)`.
- `ScheduleTest`: the expected key becomes `'vouchers:sync-till --scheduled' => '* * * * *'`. The count stays 9.
Check: `php artisan schedule:list | grep voucher` shows `vouchers:sync-till --scheduled`; `php artisan test --filter=ScheduleTest` passes; `php artisan vouchers:sync-till` prints a table that now has an `errors` column.

### 5. Activity service
Files: `app/Services/VoucherActivityService.php` (new)
What: constructor takes `VoucherSyncHeartbeat`. Three public methods.

`events(int $days, ?string $q, int $limit): array` — newest first, at most `$limit`.
- Since = `now()->subDays($days)->startOfDay()` when `$days > 1`, else `today()`.
- Transactions: `VoucherTransaction::with(['voucher' => fn ($r) => $r->withTrashed(), 'user', 'tillRedemption'])->where('created_at', '>=', $since)`, when `$q`: `whereHas('voucher', fn ($r) => $r->withTrashed()->where('code', 'like', '%'.$q.'%'))`, `orderByDesc('created_at')->orderByDesc('id')->limit($limit)`.
- Till rows with no transaction: `VoucherTillRedemption::with(['voucher' => fn ($r) => $r->withTrashed()])->whereNull('voucher_transaction_id')->where('created_at', '>=', $since)`, when `$q`: `where('voucher_code', 'like', '%'.$q.'%')`, same ordering and limit.
- Map both to one shape, merge, sort by `at` descending then `key` descending, take `$limit`:

| field | transaction row | till row without a transaction |
|---|---|---|
| `key` | `'tx-'.$id` | `'red-'.$id` |
| `at` | `created_at` ISO | `created_at` ISO |
| `kind` | `issue`, `redeem_till` (deduct + source till), `redeem_manual` (deduct + manual), `deactivate`, `reactivate` | `exception` |
| `label` | Issued / Redeemed at till / Redeemed / Deactivated / Reactivated (same words as `VoucherController::history()`) | No voucher tender / Voucher not active / Unknown voucher / Refund |
| `code` | `voucher?->code` | `voucher?->code ?? voucher_code` |
| `voucher_url` | `route('vouchers.transactions', voucher)` when the voucher exists and is not trashed, else null | same rule |
| `amount` | signed float: issue positive, deduct negative, status changes `0.0` | `0.0` |
| `balance_after` | float | null |
| `who` | `'Till #'.ticket_number` for till rows, else user name, else `'Office'` | `'Till #'.ticket_number` |
| `status` | the till redemption's status for till rows (`applied`/`partial`), else null | the row's status |
| `shortfall` | the till redemption's shortfall as float when `> 0`, else null | null |
| `sold_at` | the till redemption's `sold_at` ISO for till rows, else null | `sold_at` ISO |
| `note` | transaction note | redemption note |
| `reviewed` | null, or for `partial` whether the redemption has `reviewed_at` | whether it has `reviewed_at` |

`totals(): array` — `redeemed_today` (sum of `amount` for `deduct` rows created today), `redeemed_today_till` and `redeemed_today_manual` (the same split by `source`), `issued_today` (sum for `issue` rows today), `outstanding_balance` (sum of `current_balance` over `active` vouchers), `active_vouchers` (count), `unreviewed_exceptions` (`VoucherTillRedemption::exceptions()->unreviewed()->count()`). All money as floats rounded to 2 dp.

`health(): array` — from `heartbeat->read()` plus `vouchers_without_till_product` = count of vouchers with `pos_product_id` NULL and status `active` or `inactive`. Returns `last_check_at`, `last_check_ok`, `last_check_source`, `last_error`, `last_ok_at`, `scheduler_last_at`, `scheduler_stale`, `last_errors` (the last counts' `errors`, 0 when absent), `vouchers_without_till_product`, `state` and `messages`:
- `state = 'error'` when the last check failed. Message: `The till database could not be read: <error>`.
- otherwise `state = 'warn'` when any of these holds, one message each:
  - `scheduler_stale`: `The scheduler has not run the till check since <time>.` or, when it never has, `The scheduler has never run the till check.` followed in both cases by ` This page checks the till itself while it is open.`
  - `last_errors > 0`: `<n> till ticket(s) could not be applied on the last check. See the application log.`
  - `vouchers_without_till_product > 0`: `<n> voucher(s) have no till product and cannot be scanned at the till. Run vouchers:sync-pos-products.`
- otherwise `state = 'ok'`, no messages.

`payload(int $days, ?string $q): array` — `['server_now' => now()->toIso8601String(), 'health' => …, 'totals' => …, 'events' => …]`, using `config('vouchers.activity.limit')`.
Check: covered by step 8's `VoucherActivityTest`.

### 6. Controller and routes
Files: `app/Http/Controllers/VoucherActivityController.php` (new), `routes/web.php`
What: a separate controller (keeps `VoucherController` from growing), constructor takes `VoucherActivityService` and `VoucherTillSyncService`.
- `index(): View` returns `vouchers.activity` with `initial` = `payload(7, null)` and `pollSeconds` = config. It does **not** run the sync (a slow till must not delay the page).
- `feed(Request): JsonResponse` validates `days` (`nullable|integer|in:1,7,30`, default 7) and `q` (`nullable|string|max:64`), calls `$this->tillSync->syncIfDue(VoucherSyncHeartbeat::SOURCE_ACTIVITY)`, returns `payload(...)`.
- Routes inside the `permission:vouchers.manage` group, **before** `vouchers/{voucher}/transactions`:
```php
Route::get('vouchers/activity', [VoucherActivityController::class, 'index'])->name('vouchers.activity');
Route::get('vouchers/activity/feed', [VoucherActivityController::class, 'feed'])->name('vouchers.activity.feed');
```
Check: `php artisan route:list --name=vouchers.activity` lists both; as a manager in a test, `GET /vouchers/activity` is 200 and is not handled by `transactions`.

### 7. View, navigation
Files: `resources/views/vouchers/activity.blade.php` (new), `resources/views/layouts/admin.blade.php`, `resources/views/vouchers/index.blade.php`, `resources/views/vouchers/exceptions.blade.php`
What:
- `activity.blade.php`, dark style copied from `exceptions.blade.php`, root `x-data="voucherActivity()"` with `data-feed-url`, `data-poll-seconds`, and the initial payload in a `<script type="application/json" id="voucher-activity-initial">@json($initial)</script>` block read in `init()`.
  - **Title row:** "Voucher activity", a live indicator (green dot + "Live", grey + "Paused", red + "Offline"), a Pause/Resume button, links to "Till exceptions" and "All vouchers".
  - **Health banner:** green "Till checked <n> s ago" when `state === 'ok'`; amber or red box listing `health.messages` otherwise. Under it, in small text: "Last till check <ago> (<source>) · Scheduler last ran <ago or never>".
  - **Four tiles:** Redeemed today (with "till €x · manual €y" beneath), Issued today, Outstanding balance (with "<n> active vouchers"), Unreviewed exceptions (links to `vouchers.exceptions`; red when above zero).
  - **Filter row:** period buttons Today / 7 days / 30 days (default 7 days), a code search input (debounced 300 ms), both applied on the next fetch, which is triggered at once.
  - **Table:** When (date + time; for till rows a second line "till <HH:mm>" from `sold_at`), Event (label + pill: till purple, manual blue, issue green, status change grey, exception amber, `partial` red with "short €x"), Voucher (code, linked when `voucher_url`), Amount (signed, `—` when zero), Balance after, By, Note. Rows are `<template x-for="e in events" :key="e.key">`. Empty state: "No voucher activity in this period."
  - **Behaviour:** `setInterval` every `pollSeconds`; skip a tick while paused, while `document.hidden`, or while a fetch is in flight; fetch once on `visibilitychange` back to visible. A failed fetch or non-200 sets `offline = true` and keeps the rows; the next success clears it. Rows whose `key` was not in the previous set are highlighted for 10 seconds, except on the first load. "Ago" texts tick every second, computed against the server clock: keep `offset = Date.parse(server_now) - Date.now()` from each payload.
  - No `@` shorthand that is a Blade directive; nullable fields behind `?.`.
- `admin.blade.php`: in the `vouchers.manage` block, after the "Till exceptions" link (l.319-325), a "Voucher activity" link to `vouchers.activity`, active on `request()->routeIs('vouchers.activity')`.
- `vouchers/index.blade.php` header (l.8-15, inside `@if ($canManage)`): an "Activity" link before "Generate".
- `vouchers/exceptions.blade.php` title row: an "Activity" link beside "All vouchers".
Check: the rendering tests in step 8 pass; the inline script passes a syntax check:
```bash
php -r '$b=file_get_contents("resources/views/vouchers/activity.blade.php"); preg_match_all("/<script>(.*?)<\/script>/s",$b,$m); $js=preg_replace("/\{\{.*?\}\}/s","\"X\"",implode("\n",$m[1])); file_put_contents("/tmp/voucher-activity.js",$js);' && node --check /tmp/voucher-activity.js
```
(the JSON `<script type="application/json">` block is not matched by `<script>` and is skipped).

### 8. Tests
Files: `tests/Feature/VoucherSyncHeartbeatTest.php` (new), `tests/Feature/VoucherActivityTest.php` (new), `tests/Feature/VoucherTillSyncServiceTest.php`
What (look before creating each new file; extend if it exists):
- `VoucherSyncHeartbeatTest`: `record()` then `read()` round-trips; `last-ok` is not moved by a failed run; `last-scheduled` moves only for the `schedule` source and also on a failed scheduled run; `scheduler_stale` is true when never run, false just after a scheduled run, true again after `Carbon::setTestNow()` moves past the threshold; the error is cut to 300 characters; a cache that throws (swap the `cache` binding for a mock that throws) makes `record()` return quietly and `read()` return the stale shape.
- `VoucherTillSyncServiceTest` (add): a successful `sync()` records `ok = true` with the counts and the given source; a sync with the POS `TICKETS` table dropped records `ok = false` with an error and still throws; a ticket that fails inside the loop increments `errors` and the run still records `ok = true` (make one fail by dropping `TAXES` after creating the sale, or by another means that fails per ticket, and say which); `syncIfDue('activity')` records the `activity` source; `vouchers:sync-till --scheduled` records `schedule`, and without the flag `command`.
- `VoucherActivityTest`, with the `userWith()`/`manager()` helpers and `Config::set('vouchers.sync.on_lookup', false)` in `setUp` unless a test needs the sync:
  - employee → 403 on both routes; guest → login redirect; manager → 200; the page contains the feed URL and "Voucher activity".
  - `GET /vouchers/activity` is not swallowed by `vouchers/{voucher}/transactions`.
  - feed JSON has `server_now`, `health`, `totals`, `events`; an issue, a manual deduct, a till deduct (with its redemption), a `partial` (shortfall shown), a deactivation and a `no_tender` row appear with the right `kind`, `label`, signed `amount`, `who`, `status`; order is newest first; a till exception without a transaction is present exactly once and an `applied` redemption does not produce a second row.
  - `days=1` excludes yesterday's rows; `days=30` includes them; `days=5` → 422; `q` filters by code for both kinds of row.
  - `limit` is respected (set config to 3, create 5).
  - a soft-deleted voucher's rows still appear, with `voucher_url` null.
  - totals: redeemed today split till/manual, issued today, outstanding balance over active vouchers only, unreviewed exceptions.
  - health: `error` when the heartbeat's last run failed; `warn` with the scheduler message when stale; `warn` with the count when a voucher has no `pos_product_id`; exhausted and deactivated vouchers without a product are not counted; `ok` when a scheduled run is fresh and every active voucher has a product.
  - with `on_lookup` true and POS tables created, a feed request applies a pending till sale and the response already contains it; with the POS tables missing the feed still answers 200 and `health.state` is `error`.
  - the sidebar and the `/vouchers` header show the Activity link to a manager and not to an employee.
Check: `php artisan test --filter='Voucher|Schedule'` → all pass.

### 9. Docs
Files: `docs/features/voucher-management.md`, `docs/FEATURES_INDEX.md`, `CLAUDE.md`, `docs/deployment/production-guide.md`, `docs/deployment/production-deployment-guide.md`
What:
- `voucher-management.md`: new `## Activity screen (/vouchers/activity)` after `## Till redemption (uniCenta)`: who can see it, what each column and tile means, the health states and what to do for each, the polling interval and that the page itself runs the till check while open, the heartbeat keys, the `--scheduled` flag. Add the two routes to `## Routes` and the new files to `## Key files`. In `### The sync`, mention the `errors` count and the heartbeat.
- `FEATURES_INDEX.md` Gift Vouchers block and the `CLAUDE.md` voucher line: mention the live activity screen with till-check health.
- `production-guide.md` l.163-180: "must list **nine** commands", add the row `every minute | vouchers:sync-till --scheduled`.
- Both deployment guides: state that production runs the scheduler from `/etc/cron.d/osmanager` as `www-data`, that `crontab -l` therefore shows nothing, how to confirm it (`cat /etc/cron.d/osmanager`; `journalctl -u cron --since "10 min ago" | grep artisan`), and that there must be exactly one trigger. In `production-deployment-guide.md` l.317-321 replace the user-crontab instruction with the `/etc/cron.d` form:
```
* * * * * www-data cd /var/www/html/osmanager && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```
Check: `grep -n "nine\|cron.d/osmanager" docs/deployment/*.md` finds both guides.

## Verification (report every item with what you saw)

1. `./vendor/bin/pint --test` on the PHP files you changed (not `--dirty`: the Shop mode track's files are dirty in the same tree) → PASS.
2. `php artisan test --filter='Voucher|Schedule|ConfinePinSession'` → all pass.
3. `php artisan test` → no new failures against your step 0 baseline. Paste the summary line and the failing class names.
4. `php artisan schedule:list | grep voucher` → `vouchers:sync-till --scheduled`, every minute.
5. `php artisan route:list --name=vouchers` → the two new routes, both above `vouchers/{voucher}/transactions` in `routes/web.php`.
6. The `node --check` from step 7 → no output.
7. On dev, by hand: `php artisan vouchers:sync-till --scheduled`, then `php artisan tinker --execute="print_r(app(App\Services\VoucherSyncHeartbeat::class)->read());"` → `last.source = schedule`, `scheduler_stale = false`. Wait past the threshold or clear the key and read again → `scheduler_stale = true`.
8. On dev, a simulated till sale with `docs/vouchers/scripts/simsale.php` (read its header for usage) against an active voucher, then one authenticated feed request through a feature test or `curl` with a session is **not** required; instead confirm through tinker that `app(App\Services\VoucherActivityService::class)->payload(7, null)` lists the new "Redeemed at till" event after `vouchers:sync-till`. Restore dev data afterwards with `docs/vouchers/scripts/reset_voucher.php` and delete the simulated POS rows; record the ids.
9. **Browser check: owner, not the Implementer.** Office pages send a Shop PIN session to `/confirm-password`, and the Implementer does not type account passwords. The owner, signed in as a manager: open `/vouchers/activity`; the health banner and four tiles show; leave it open and deduct €1 on `/vouchers` in another tab: the row appears within 5 seconds, highlighted, and "Redeemed today" rises; Pause stops updates and Resume catches up; switch period and search by code; stop the dev server for a moment: "Offline" shows and clears on restart; the console stays free of errors.

## Risks

- **The page masks a dead scheduler unless the two timestamps stay separate.** While the page is open it runs the till check itself, so "last till check" is always fresh. The scheduler warning depends only on `last-scheduled`. Do not merge them.
- **A slow or unreachable till database** delays every feed response by the connection timeout, once per throttle window. The page shows "Offline" only for a failed HTTP request; a slow till shows as `health.state = error` on the next answer. If feed requests stack up, the in-flight guard in step 7 prevents overlap.
- **Several managers watching at once** share one throttle (`Cache::add`, 5 s), so the till is read at most once per 5 seconds however many tabs are open.
- **Clock differences**: "ago" is computed against the server clock, not the browser's. Till times (`sold_at`) are the till's local time; on the dev VirtualBox till they are an hour behind (UTC), which is a dev artefact.
- **`ScheduleTest` pins the command string.** Changing it to `--scheduled` without updating the test fails two tests, by design.
- **Route order**: `vouchers/activity` after `vouchers/{voucher}/transactions` would 404 on model binding. The test in step 8 guards it.

## Review

Reviewed 2026-09-29 against `implemented.md` (read to the end, five deviations and five notes) and the working-tree diff. Every command below was rerun by the Planner.

**Criteria**
- Step 0 baseline — pass (15 failed, 806 passed, as expected).
- Step 1 config — pass.
- Step 2 heartbeat — pass. Three separate keys, `last-scheduled` written on failed scheduled runs too, neither method can throw.
- Step 3 sync records the heartbeat — pass. The old body is `run()`; `sync()` wraps it, records, rethrows. `errors` counted in the per-ticket catch. `lookup()` still calls `syncIfDue()` with no argument.
- Step 4 command flag and schedule — pass. `schedule:list` shows `vouchers:sync-till --scheduled` every minute; `ScheduleTest` updated, count still 9.
- Step 5 activity service — pass. Merged log is exact; `applied`/`partial` appear once as their transaction; soft-deleted vouchers keep their rows with no link; health states as specified.
- Step 6 controller and routes — pass. Both routes sit above `vouchers/{voucher}/transactions` (lines 1219-1221); `index()` does not run the sync.
- Step 7 view and navigation — pass by rendering tests and `node --check`. All bindings use `x-on:`; nullable parts are behind `x-if`. `@json` escapes tags, so a note containing `</script>` cannot break the initial payload. Behaviour in a browser is the owner's check.
- Step 8 tests — pass. 29 new tests; none of the existing ones changed.
- Step 9 docs — pass. Both deployment guides now name `/etc/cron.d/osmanager` and nine commands.

**Verification (rerun by the Planner)**
1. `pint --test` on the 12 changed PHP files → PASS.
2. `php artisan test --filter='Voucher|Schedule|ConfinePinSession'` → 90 passed (479 assertions).
3. `php artisan test` → 15 failed, 835 passed (3577 assertions); the 15 are the baseline classes. No new failures.
4. `schedule:list` → `vouchers:sync-till --scheduled`, every minute.
5. Route order confirmed in `routes/web.php`.
6. `node --check` of the extracted inline script → clean.
7-8. Heartbeat and simulated sale: the implementer's evidence, consistent with the code. Not repeated.
9. Browser check: owner.

**Deviations** — all five accepted.
1. Event order tie-break by timestamp then an internal integer: correct; the plan's string sort on `key` would have put `tx-9` after `tx-10`. Planner's error.
2. A filter change during a fetch is re-run once the fetch returns: better than the plan, which would have dropped it for up to 5 seconds.
3. `npm run build` was run: needed, the view uses Tailwind classes no other view used. `public/build` is git-ignored, so the production deploy must build assets as it normally does.
4. Syntax-check script written to the scratchpad instead of `/tmp`: fine.
5. `VoucherTillSyncServiceTest` had 18 tests at cycle 1, not 27: Planner's error in the plan.

**Notes for Planner**
1. "When" is when the app recorded the event, up to a minute after the sale; the till's own time is the second line — accepted as designed and documented.
2. The highlight set is not reset on a search change — deferred; harmless.
3. No "showing newest 100" hint when a period holds more than the limit — deferred; add the line if managers use the 30-day view on a busy month.
4. `partial` pill reads "partial · reviewed" once reviewed — accepted.
5. Dev data left in place (redemption 7 / transaction 15, two "Dev reset" rows) — accepted; dev only.

**Owner actions**
- Browser check as a manager (plan Verification 9): open `/vouchers/activity`, deduct €1 on `/vouchers` in another tab and watch the row arrive within 5 seconds; try Pause, the period buttons and the code search; keep the console open.
- After deploying, the page on production will show the amber line about vouchers without a till product until the parked backfill is run. The scheduler line should go green within a minute of the deploy.

**Archive**: `mkdir -p docs/vouchers/archive/2026-09-29-cycle-2-activity-screen` and move `plan.md` and `implemented.md` there.
