# Shop mode cycle 17g — Inline refusal on the office harvest page; a thumbnail prune that can actually run

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

Two leftovers from 17f. First, the office harvest page reports a failed save (including the new one-unit-per-day refusal) with a browser `alert()`; it gets an inline message under the row instead, in the style the page already uses for its print modal. Second, thumbnail orphans are only removed when a product's photo changes, so a product whose photo never changes again keeps any orphan it has; a prune walks the cache and deletes every file that does not match a current product photo. Because the cache folder is created by the web server and is not writable by other users, the prune is exposed two ways: an artisan command scheduled weekly (for hosts where the scheduler runs as the web user) and a button on the office fruit-and-veg manage page (which always runs as the web user).

## Context

- `resources/views/fruit-veg/harvest.blade.php`: `saveRow(row)` (lines ~236–258) posts and on `data.success === false` calls `alert(data.message || 'Save failed.')`; the `catch` calls `alert('Save failed: ' + err.message)`. Row state has `logged`, `input`, `unit`, `notes`, `added`, `saving`, `label`. The print modal already uses inline `message`/`success` state rendered with `text-red-600` / `text-green-600` (line ~187), which is the pattern to copy. The page is an office (Tailwind) view; the Shop contract does not apply.
- `ProductThumbnailService` (17d/17f): `SIZES = [112, 224]`, `DISK = 'local'`, `FOLDER = 'fv-thumbs'`, `path()` = `fv-thumbs/<md5(code)12>-<size>-<md5(blob)12>.jpg`, `prefix($code, $size)`; the miss path deletes older files with the same prefix.
- Products with photos: F&V products are `Product::whereIn('CATEGORY', TillVisibilityService::CATEGORY_MAPPINGS['fruit_veg'])`; the harvest side uses Jon's products (`SupplierLink` where `SupplierID = config('suppliers.jon')`), which may include non-F&V categories. The cache can hold thumbnails for either set, so the prune's "current photos" set is the union: every product that is F&V **or** Jon's, with `IMAGE` not null. Loading those blobs once a week is fine (≈20 MB on dev).
- Scheduler: `app/Console/Kernel.php::schedule()` holds `dailyAt`/`weekly` entries; `routes/console.php` holds `Schedule::command(...)` entries too. Cron runs `php artisan schedule:run` (deployment docs); the user it runs as is not fixed by the repo.
- Cache folder on dev: `drwxr-sr-x www-data www-data`; the implementer (user `jon`) could not delete files in it (17d report). Any prune run outside the web server may be unable to delete; the command must report that plainly rather than fail.
- Office F&V manage page: `resources/views/fruit-veg/manage.blade.php` (`fruit-veg.manage`, `permission:fruit_veg.manage`), `FruitVegController::manage()`.
- Tests: `tests/Unit/ProductThumbnailServiceTest.php` (8), `tests/Feature/FruitVegProductImageTest.php` (17). Suite baseline 15 failed / 620 passed.

## Constraints

- Do not commit, push or deploy.
- The office harvest page's behaviour on success is unchanged; only the two failure paths change.
- The prune deletes only files under `fv-thumbs/` whose name is not in the current expected set; it never touches product data.
- Permission failures are reported, never thrown.

## Out of scope

- Other `alert()` calls elsewhere in the office (there are none on the harvest or waste pages besides these two).
- Making the cache folder group-writable (a server change; the button route makes it unnecessary).

## Steps

### 1. Inline failure message on the office harvest page
Files: `resources/views/fruit-veg/harvest.blade.php`
What: each row gets `error: ''` in its initial state (where rows are seeded and where `addRow` builds one). In `saveRow(row)`: set `row.error = ''` at the start; on `success === false` set `row.error = data.message || 'Save failed.'`; in the `catch`, `row.error = 'Save failed: ' + err.message`. Under the row's input area render `<p x-show="row.error" x-cloak class="mt-1 text-xs text-red-600" x-text="row.error"></p>`. Clear `row.error` when the amount input or the unit radio changes (`@input`/`@change` on those controls set `row.error = ''`). Remove both `alert(` calls.
Check: `grep -c "alert(" resources/views/fruit-veg/harvest.blade.php` → 0; the page renders through the kernel as admin (200) and contains `x-text="row.error"`; a browser check that a refused save (unit change on a row already logged today) shows the red line under the row and no dialog, and that the line clears when the unit is switched back.

### 2. `prune()` on the thumbnail service
Files: `app/Services/ProductThumbnailService.php`
What: `public function prune(iterable $productsWithPhotos): array` taking `Product` models (with `IMAGE` loaded); builds the expected set of basenames (`for each product, for each size: prefix . substr(md5(IMAGE), 0, 12) . '.jpg'`); lists `FOLDER`; for every file whose basename is not in the set, `delete()` inside a try/catch that also checks the return value; returns `['kept' => n, 'deleted' => n, 'failed' => [paths]]`. Add a static `public static function currentPhotoProducts(): Collection` (or put it on the command) returning the union described in Context, selecting only `CODE` and `IMAGE`.
Check: unit test `prune removes orphans and keeps current thumbnails`: seed three cached files: current photo of A at 112, an old hash of A at 112, and a file for product Z that is not in the set → after `prune([A])`: A's current kept, A's old and Z's file deleted, counts `kept 1, deleted 2, failed []`. Test `prune reports a file it cannot delete` by using `Storage::fake` and a mocked disk delete returning false → path listed under `failed`, no exception.

### 3. Artisan command, scheduled weekly
Files: `app/Console/Commands/PruneFruitVegThumbnails.php (new)`, `routes/console.php` (or `app/Console/Kernel.php`, whichever already holds the F&V-style schedules; follow the file the other weekly job uses)
What: `fruit-veg:prune-thumbnails` → calls `prune(currentPhotoProducts())` and prints `Kept N, deleted N.`; when `failed` is non-empty prints `Could not delete N files (permission?): run this as the web server user, e.g. sudo -u www-data php artisan fruit-veg:prune-thumbnails, or use the Tidy thumbnails button on the F&V manage page.` and exits 1. Schedule it `->weekly()->sundays()->at('05:30')` next to the other weekly job.
Check: `php artisan fruit-veg:prune-thumbnails` on dev (as `jon`) → either deletes nothing because there is nothing to delete and prints `Kept 100, deleted 0.`, or lists the permission failure clearly; `php artisan schedule:list` shows the command.

### 4. The manage-page button
Files: `routes/web.php`, `app/Http/Controllers/FruitVegController.php`, `resources/views/fruit-veg/manage.blade.php`
What: `POST /fruit-veg/thumbnails/prune` → `fruit-veg.thumbnails.prune`, `permission:fruit_veg.manage`, `FruitVegController::pruneThumbnails()` → runs the same prune, redirects back with `success` "Tidied thumbnails: kept N, deleted N." or `error` "… N could not be deleted." A small form on the manage page near the existing page actions: a secondary button "Tidy thumbnail cache" with a one-line note "Removes cached pictures that no longer match a product photo."
Check: feature test in `FruitVegProductImageTest`: a manager POSTs the route → redirect with the success flash; an employee (`operate` only) → 403; the prune actually removed a seeded orphan (`Storage::fake('local')`).

### 5. Docs, format
Files: `docs/features/fruit-veg-system.md`, all touched
What: replace the "orphans are removed when a photo changes" sentence with the full story: sweep on write, weekly prune, the manage-page button, and the note that a shell run needs the web server user. `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean.

## Verification

1. `php artisan test --filter="FruitVeg|ProductThumbnail|Shop"` → green; `php artisan test` → 15 failed, the identical set; passed = 620 + new tests.
2. `git diff --stat resources/views/fruit-veg/` → `harvest.blade.php` and `manage.blade.php` only; `grep -c "alert(" resources/views/fruit-veg/harvest.blade.php` → 0.
3. `php artisan schedule:list` includes `fruit-veg:prune-thumbnails`.
4. Manual, dev app: on the office harvest page, log a row in kg, switch its radio to units, save → the red line under the row, no dialog; switch back → the line clears, save adds. On the manage page, click "Tidy thumbnail cache" → the flash with counts (deleted 0 on a clean cache); seed an orphan first via tinker (copy one cached file to a name with a wrong hash, as `www-data` is not needed since `Storage::copy` runs in the web process only if done through a request: simplest is `sudo -u www-data php artisan tinker`), click again → deleted 1.

## Risks

- **Loading every F&V and Jon photo for the prune** is ≈20 MB on dev once a week, or on demand from the button; acceptable. If it ever matters, select `CODE` plus `MD5(IMAGE)` in SQL instead of the blob.
- **Cron user**: if the scheduler runs as a user that cannot delete, the weekly job prints the permission message every week and exits 1; the button is the reliable path and the message says so.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end and the diffs of the harvest and manage views, the service, the command, the schedule entry, the route, the controller action and both test files. Reran `php artisan test`: 15 failed / 629 passed, the identical set (620 + 9 new tests). `alert(` count on the harvest page is 0; `app/Console/Kernel.php` is unchanged; `schedule:list` shows the prune.

**Steps 1–5: pass.** The inline error is per row and clears on edit; `prune()` reports rather than throws on both a false return and an exception; the command exits 1 with a plain message when it cannot delete; the manage-page button runs the same prune as the web user and, on dev, removed the exact orphan the shell run could not. The office harvest page was driven by automation for the first time, which is itself proof the dialog is gone.

**Deviations.** The schedule entry lives in `routes/console.php`, not `app/Console/Kernel.php`: **accepted, and correct**. I verified the finding myself: `bootstrap/app.php` registers only `routes/console.php`, so the five jobs declared in `Kernel.php::schedule()` are never scheduled (`schedule:list` shows four jobs, none of them from the kernel). My plan pointed at the wrong file; the implementer found out by checking the list rather than trusting the edit.

**Notes for Planner.**
1. Five declared jobs not running: **the headline, and outside Shop mode.** Filed as `docs/planImp/findings/2026-09-26-dead-console-kernel-schedule.md` for the owner's decision on which should run; the fix itself is a move of entries, plus a `schedule:list` check in the deploy notes.
2. Unrequested 224 px size in `SIZES`: harmless; **accepted**.
3. The manage page now has a flash area that will surface any other flash sent to it: **accepted**, an improvement.
4. Other office dialogs elsewhere: **out of scope**, the harvest page is the worked example.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-17g/`.
