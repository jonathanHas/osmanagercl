# Shop mode cycle 17g — Inline refusal on the office harvest page; a thumbnail prune that can run — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: 9a41cc3d. Cycle 17f's work is accepted and archived but **still
uncommitted**, so this cycle builds on a dirty tree; its files overlap mine
(`FruitVegController`, `ProductThumbnailService`,
`docs/features/fruit-veg-system.md`). Files changed at the end separates them.

```
 M app/Http/Controllers/FruitVegController.php    ┐
 M app/Http/Controllers/HarvestController.php     │
 M app/Services/ProductThumbnailService.php       │ cycle 17f, mine,
 M docs/design/shop-mode/README.md                │ archived but uncommitted
 M docs/features/fruit-veg-system.md              │
 M resources/js/shop/fv-harvest.js                │
 M resources/views/shop/fv-harvest.blade.php      │
 M tests/Feature/Shop/ShopFruitVegTest.php        │
 M tests/Unit/ProductThumbnailServiceTest.php     ┘
```

Test baseline: 15 failed / 620 passed (2632 assertions).

## Pre-flight

- **Schedules live in two places.** `routes/console.php` holds three
  `Schedule::command(...)` entries; `app/Console/Kernel.php::schedule()` holds five
  more, including the weekly one the plan points at
  (`sales:import-daily --last-week`, `->weekly()->sundays()->at('05:00')
  ->onOneServer()->withoutOverlapping(60)`). The plan says to follow the file the
  other weekly job uses, so the new entry goes in `Kernel.php` beside it.
- The office harvest page's two `alert(` calls are at lines 252 and 255, and the
  print modal's inline `message`/`success` pattern is at line ~187 as described.
  Rows are seeded at `rows.map(...)` and built in `addRow(p)` at line 221 — both
  need the new field.

## Steps

### 1. Inline failure message on the office harvest page — done

Changed: `resources/views/fruit-veg/harvest.blade.php` — `error: ''` on both row
constructors, cleared at the start of `saveRow()` and by `@input`/`@change` on the
amount and unit controls, set on both failure paths, rendered as a red line under
the row's controls. Both `alert(` calls gone.

```
$ grep -c "alert(" resources/views/fruit-veg/harvest.blade.php
0
$ the page rendered through the kernel as admin → 200, contains x-text="row.error"
```
I first wrote the explanatory comment using the word `alert(`, which made the plan's
grep check pass 1 instead of 0 — the check is the point, so the comment is reworded.

### 2. `prune()` on the thumbnail service — done

Changed: `app/Services/ProductThumbnailService.php` — `prune(iterable)` and a static
`currentPhotoProducts()` (the union of F&V products and Jon's, with a photo).

```
$ php artisan test --filter=ProductThumbnailServiceTest
✓ prune removes orphans and keeps current thumbnails
✓ prune skips a product whose photo has gone
✓ prune on an empty or missing folder does nothing
✓ prune reports a file it cannot delete
✓ prune survives a disk that throws on delete
  ... plus the 8 from 17d/17f
Tests:    13 passed (37 assertions)
```
Two beyond the plan's list, both about not failing: a `delete()` that returns false
**and** one that throws are each reported under `failed` rather than propagating —
this runs in a weekly cron, and the whole reason it exists is a permission problem.
The unit tests use a small anonymous `Product` stub, because `prune()` reads only
`CODE` and `IMAGE` and a real model would drag in the POS connection.

### 3. Artisan command, scheduled weekly — done

Changed: `app/Console/Commands/PruneFruitVegThumbnails.php (new)`,
**`routes/console.php`** — not `app/Console/Kernel.php`. See Deviations; this is the
important finding of the cycle.

```
$ php artisan schedule:list | grep prune
30 5 * * 0  php artisan fruit-veg:prune-thumbnails  Next Due: 14 hours from now

$ php artisan fruit-veg:prune-thumbnails          (run as `jon`)
Kept 99, deleted 0.
Could not delete 1 files (permission?): run this as the web server user, e.g.
sudo -u www-data php artisan fruit-veg:prune-thumbnails, or use the Tidy thumbnail
cache button on the F&V manage page.
exit=1
```
That is not a contrived demonstration: dev really did have one orphan that no
current photo matches, and `jon` really cannot delete it. Both halves of the design
— finding the orphan, and reporting honestly that it could not be removed — were
exercised by the first run.

### 4. The manage-page button — done

Changed: `routes/web.php`, `app/Http/Controllers/FruitVegController.php`
(`pruneThumbnails()`), `resources/views/fruit-veg/manage.blade.php` (the button and
a flash area — the page had none).

```
$ php artisan route:list --name=fruit-veg.thumbnails.prune -v
POST fruit-veg/thumbnails/prune … ⇂ PermissionMiddleware:fruit_veg.manage
$ php artisan test --filter=FruitVegProductImageTest
Tests:    21 passed (128 assertions)
```

### 5. Docs, format — done

`docs/features/fruit-veg-system.md` now carries the whole story: sweep on write,
weekly prune, the button, and that a shell run needs the web server user.
`./vendor/bin/pint --test --dirty` → PASS.

## Verification

**1. Tests**
```
$ php artisan test --filter="FruitVeg|ProductThumbnail|Shop"
Tests:    2 failed, 228 passed (1046 assertions)     ← the 2 are FruitVegLabelPrintingTest,
                                                        part of the known 15
$ php artisan test
Tests:    15 failed, 629 passed (2658 assertions)
```
The identical set. 629 = 620 + 9 new tests (5 on `prune`, 4 on the button).

**2. View diffs**
```
 resources/views/fruit-veg/harvest.blade.php | 19 ++++---
 resources/views/fruit-veg/manage.blade.php  | 19 +++++
alert( in harvest.blade.php: 0
```

**3. `php artisan schedule:list`** includes `fruit-veg:prune-thumbnails` — see
Deviations for why that took moving the entry to a different file.

**4. Manual, dev app — done, and the office harvest page could be driven by
automation for the first time, which is itself the proof that step 1 worked.**

*The inline refusal.* Added Rosemary through the page's own search, typed 1, saved →
logged 1 kg. Switched the radio to **units**, typed 2, saved:
```
no dialog; the browser session continued normally
row.error = "Already logged 1 kg of Rosemary today. Log in kg, or remove today's
             entry on the office harvest page to change the unit."
rendered: visible, rgb(220, 38, 38), under the row's controls
```
Zoomed in to read it on screen. Then switched the radio back to **kg**:
```
error cleared (state '' and the element hidden) without a save
```
and saving 2 more kg took the row to 3. The clear-on-change is what makes the
message feel like guidance rather than a stuck error.

*The prune button.* The manage page's new button read back:
```
"Tidied thumbnails: kept 99, deleted 1."   (green flash)
```
It deleted **the exact orphan the shell command had just reported it could not**,
which is the entire justification for having both paths. On disk: 100 files → 99,
and a re-run of the command now says `Kept 99, deleted 0.` and exits 0.

*Console* on both pages: clean.

*Dev data put back.* One harvest row and one `harvest_product_units` row for
Rosemary, both created by this walkthrough, both removed — and as in 17f I checked
first that Rosemary had no stored preference beforehand. Final: 197 harvests with
none today, 10 preferences, 0 waste rows, 99 thumbnails.

## Deviations

**One, and it matters more than the cycle it came from.**

The plan says to schedule the command in "whichever file already holds the weekly
job", which is `app/Console/Kernel.php`. I did that, and `php artisan schedule:list`
did not show it. The reason:

```
bootstrap/app.php → Application::configure()->withRouting(commands: routes/console.php)
                    and binds no console kernel
```

This is a Laravel 11/12 style bootstrap, so **`App\Console\Kernel::schedule()` is
never called**. Verified by listing both:
```
declared in app/Console/Kernel.php        actually scheduled
  sales:import-daily --yesterday            sales:import-daily --today
  sales:import-daily --last-week            suppliers:send-daily-sales
  sales-accounting:import --days=7          customers:send-statements
  pos:populate-daily-summaries --last-days=7
  kds:monitor
```
Five pre-existing jobs are declared and none of them run. I moved my entry to
`routes/console.php`, reverted `Kernel.php` to exactly as I found it, and left a
comment at the new entry saying why it is there. See Notes 1 — this is much bigger
than a thumbnail prune.

## Files changed

Mine, this cycle:
```
 M app/Http/Controllers/FruitVegController.php     pruneThumbnails() + one import
 M app/Services/ProductThumbnailService.php        prune(), currentPhotoProducts()
 M docs/features/fruit-veg-system.md               the prune paragraph
 M resources/views/fruit-veg/harvest.blade.php     inline error, no dialogs
 M resources/views/fruit-veg/manage.blade.php      button + flash area
 M routes/console.php                              the weekly schedule
 M routes/web.php                                  the prune route
 M tests/Feature/FruitVegProductImageTest.php      4 tests + POS fixture tables
 M tests/Unit/ProductThumbnailServiceTest.php      5 tests
?? app/Console/Commands/PruneFruitVegThumbnails.php
```
`app/Console/Kernel.php` is **unchanged** — I edited it and reverted it.

Also still dirty, and **not this cycle**: cycle 17f's files
(`HarvestController.php`, `resources/js/shop/fv-harvest.js`,
`resources/views/shop/fv-harvest.blade.php`, `ShopFruitVegTest.php`,
`docs/design/shop-mode/README.md`), accepted and archived but uncommitted.
`FruitVegController.php`, `ProductThumbnailService.php` and
`docs/features/fruit-veg-system.md` carry both cycles' changes.

**Not committed, not pushed, not deployed.**

## Notes for Planner

1. **Five scheduled jobs have not been running, and this is the headline.**
   `app/Console/Kernel.php::schedule()` is dead code under the current
   `bootstrap/app.php`. The jobs in it — `sales:import-daily --yesterday` (daily
   06:00), `sales:import-daily --last-week` (weekly catch-up),
   `sales-accounting:import --days=7`, `pos:populate-daily-summaries --last-days=7`
   and `kds:monitor` (every 10s, commented as "ensures orders are detected even
   when no one has the KDS page open") — are declared and never scheduled. Some of
   this may be intentional or covered elsewhere: `sales:import-daily --today` does
   run from `routes/console.php`, so same-day sales do arrive. But the weekly
   catch-up, the accounting import, the daily summaries and the KDS monitor look
   like they are simply off. I did not move them: whether each should run, and at
   what time, is a decision about the business, not a mechanical fix. It wants its
   own short cycle, and probably a `schedule:list` check in the deploy notes so
   this cannot happen silently again.

2. **The prune's orphan definition is "not a current photo", which includes sizes
   nobody asks for.** `SIZES` is `[112, 224]` and only 112 is ever requested, so
   every 224 thumbnail is expected-but-never-created. Harmless today. If `SIZES`
   ever shrinks, the prune will delete the dropped size's files on its next run,
   which is correct but worth knowing.

3. **The manage page had no flash area at all before this cycle**, so any
   `with('success')` from elsewhere that redirects there was being swallowed. I
   added one for the prune; it will now also surface anything else that flashes to
   that page. I did not go looking for what else might.

4. **The office harvest page's inline error is per row and clears on edit**, which
   is the pattern the other office F&V pages do not have — several still use
   dialogs elsewhere in the office. Not in scope here; the harvest page is now the
   worked example if anyone wants to convert them.
