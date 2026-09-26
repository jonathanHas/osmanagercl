# Shop mode cycle 17f — Fruit & veg housekeeping — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: 9a41cc3d — cycles 15 through 17e are now committed, so for the first time in
several cycles the working tree is essentially clean and `git diff` shows only this
cycle's work.

Pre-existing dirty files:
```
?? docs/planImp/plan.md
(and this file)
```

Test baseline: 15 failed / 617 passed (2603 assertions).

## Pre-flight

`saveRow()` is as the plan describes, and the ordering point it makes is real:
`HarvestProductUnit::updateOrCreate` runs **before** the row is loaded, so a
refusal added after it would still have changed the remembered preference. The
check has to go in front of it.

## Steps

### 1. One unit per day in `saveRow()` — done

Changed: `app/Http/Controllers/HarvestController.php` — the mismatch check, two
small private helpers (`trimZeros`, `unitLabel`), and `logged_unit` on each row in
`rows()`.

The check sits **before** `HarvestProductUnit::updateOrCreate`, which meant moving
the row lookup above it: otherwise a refused entry would still have changed the
product's remembered unit. The plan calls this out and it is real.

### 2. The Shop screen locks the unit to the day's row — done

Changed: `resources/js/shop/fv-harvest.js` (`lockedUnit` getter, `select()`,
`setUnit()`, and a re-read on refusal), `resources/views/shop/fv-harvest.blade.php`
(`:disabled` on both radios, and the hint line).

```
$ node scratchpad/harvest17f.mjs
ok   day's unit wins over the preference: "kg"
ok   lockedUnit: "kg"
ok   switching away is ignored: "kg"
ok   switching to the locked unit is fine: "kg"
ok   available product: no lock: null
ok   available product: uses its preference: "unit"
ok   unlocked switch works: "kg"
ok   locked even when preference agrees: "kg"
ok   nothing logged today: no lock: null
ok   nothing logged today: switch free: "unit"
     422 toast: "Already logged 5.2 kg of Salad mix today. Log in kg, or remove
                 today's entry on the office harvest page to change the unit."
ok   posted anyway (stale screen): 1
ok   re-reads so the lock catches up: 1
ok   lock now known: "kg"
ok   unit corrected: "kg"
```

The last four are a gap I had left: my first version toasted the refusal but did not
re-read, so a stale screen would keep being refused. The plan asks for the re-read
and it earns its place — after it, the second attempt succeeds without the user
doing anything.

The first case is the one that matters most and is easy to miss: a product whose
**remembered preference differs from today's row**. The lock has to follow the row,
not the preference, or the screen would offer exactly the combination the server
refuses.

### 3. Thumbnail cache: replace, do not accumulate — done

Changed: `app/Services/ProductThumbnailService.php` — a `prefix($code, $size)`
helper now shared by `path()` and a delete sweep on the cache-miss path.

```
$ php artisan test --filter=ProductThumbnailServiceTest
✓ a replaced photo gets a new cache key and the old file goes
✓ replacing a photo leaves other sizes and other products alone
  ... plus the 6 from 17d
Tests:    8 passed (25 assertions)
```
One existing test asserted the old behaviour — that two blobs leave two files — so
it is rewritten to the new intent rather than deleted: the key still changes, and
now the old file goes with it. The second test is the guard that matters: the sweep
must not touch the same product's other size or another product's files.

### 4. Remove the dead `?t=` branch — done

Changed: `app/Http/Controllers/FruitVegController.php` — one line.
```
$ sed -n '/public function productImage/,/^    }/p' … | grep "'t'"
none
$ php artisan test --filter=FruitVegProductImageTest
Tests:    17 passed (114 assertions)
```

### 5 & 6. Tests, docs, format, build — done

Changed: `tests/Feature/Shop/ShopFruitVegTest.php`,
`tests/Unit/ProductThumbnailServiceTest.php`,
`docs/features/fruit-veg-system.md`, `docs/design/shop-mode/README.md`.

```
$ php artisan test --filter=ShopFruitVegTest
✓ harvest save row accumulates               ✓ harvest refuses a second unit on the same day
✓ harvest refusal message reads without trailing zeros
  ... plus 9 others
Tests:    12 passed (105 assertions)

$ ./vendor/bin/pint --test --dirty  → PASS 5 files
$ npm run build                     → ✓ built in 7.92s
```
The extra test beyond the plan's list checks the message reads "3 units" rather
than "3.00 unit" — it is read aloud on a shop floor, and `number_format` would
otherwise have produced the latter.

## Verification

**1. Tests**
```
$ php artisan test --filter="Shop|FruitVeg|ProductThumbnail"
Tests:    2 failed, 219 passed (1020 assertions)

$ php artisan test
Tests:    15 failed, 620 passed (2632 assertions)
```
The 2 in the filtered run are `FruitVegLabelPrintingTest`, which my `FruitVeg`
filter catches and which are part of the known 15 — not new. The full suite is the
identical set (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2,
TestScraper ×1). 620 = 617 + the 3 new tests.

**2. Diff scope**
```
 app/Http/Controllers/FruitVegController.php |  2 +-      ← one line, the header
 app/Http/Controllers/HarvestController.php  | 51 +++--   ← saveRow(), rows(), 2 helpers
 app/Services/ProductThumbnailService.php    | 29 +++--
 resources/js/shop/fv-harvest.js             | 21 +++-
 resources/views/shop/fv-harvest.blade.php   |  8 ++-

$ git diff --stat resources/views/fruit-veg/   → empty (office views untouched)
```

**3. Contract**
```
design block cmp            → IDENTICAL   (no stylesheet change this cycle)
<script|<style in shop views → 0
route( in fv-harvest.js      → 0
```

**4. Manual, dev app — done, with real taps, and the office half through the
endpoint rather than its UI** (the plan warns its `alert()` blocks automation, and
the browser guidance says the same, so I did not trigger it).

*The unit lock, on Rosemary.*
```
before logging   lockedUnit null    radios [enabled, enabled]   hint hidden
after 1 kg       lockedUnit "kg"    radios [enabled, DISABLED]  hint shown:
                 "Logged in kg today · to change, remove today's entry on the office page"
tapping "units"  unit stays "kg", kg still checked, display still "Quantity (kg)"
```
Zoomed in on the switch to read it: the units half is visibly inert and the line
beneath explains why.

*The server rule, against real data through the real endpoint.*
```
POST {code: Rosemary, amount: 2, unit: 'unit'}  → 422
  "Already logged 1 kg of Rosemary today. Log in kg, or remove today's entry on
   the office harvest page to change the unit."
  logged: 1, unit: "kg"

POST {code: Rosemary, amount: 1, unit: 'kg'}    → 200  logged: 2
```
That is the text the office page's alert will show. Checked afterwards that the
refusal left **both** the row (2.00 kg) and the remembered preference (`kg`)
untouched — the preference is the one a misplaced check would have silently
corrupted.

*The thumbnail orphans, on real data.* Product 1030 already had **two** files at
size 112 — `a9875577` and `da7e3245` — the orphan cycle 17e's photo-swap
walkthrough left behind, which is this defect visible in production-shaped data. I
replaced the photo once more and reopened the waste log:
```
apricots v → e5dcd27f
95 tiles: 65 from cache, exactly 1 fetched
files matching <md5(1030)>-112-  : 1   (was 2)
total thumbnails                 : 100 (was 101)
```
One write removed two old files, so the fix also cleaned up the existing orphan.
Photo restored afterwards and verified by md5
(`a9875577ca93a2577b699e70f42d148b`).

*Dev data put back.* One harvest row and one `harvest_product_units` row for
Rosemary, both created by this walkthrough, both deleted — and unlike cycle 17c I
checked first that Rosemary had no preference before I started, so deleting it is
correct. Final state: `Harvest::count()` 197 with none today, prefs 10, waste 0,
Apricots' blob identical to its backup.

## Deviations

**None in behaviour.** Two notes on shape, both recorded in their steps: the row
lookup in `saveRow()` moved above `HarvestProductUnit::updateOrCreate` so the check
can precede it, and `ShopFruitVegTest` gained three tests rather than the plan's
implied one.

## Files changed

```
 M app/Http/Controllers/FruitVegController.php
 M app/Http/Controllers/HarvestController.php
 M app/Services/ProductThumbnailService.php
 M docs/design/shop-mode/README.md
 M docs/features/fruit-veg-system.md
 M resources/js/shop/fv-harvest.js
 M resources/views/shop/fv-harvest.blade.php
 M tests/Feature/Shop/ShopFruitVegTest.php
 M tests/Unit/ProductThumbnailServiceTest.php
```
The tree was clean at the start of this cycle, so this is the whole of it.
`public/build` and `storage/app/private/fv-thumbs/` are gitignored.

**Not committed, not pushed, not deployed.**

## Notes for Planner

1. **The harvest unit defect is closed.** It was first reported in cycle 17's notes
   and carried through 17b, 17c, 17d and 17e. Nothing is left open from it.

2. **The office harvest page can still reach the refusal, and shows it as an
   `alert()`.** That is its existing behaviour and the view was out of scope, so it
   is correct but blunt — a manager changing a row's radio gets a browser dialog
   rather than an inline message. If the office F&V pages are ever revisited, the
   alert calls are the thing to replace; there are several.

3. **Existing orphans elsewhere are not swept.** The delete sweep only runs when a
   thumbnail is written, so an orphan is cleared the next time that product's photo
   changes — which is what cleaned up 17e's. A product whose photo never changes
   again keeps any orphan it already has. There were exactly two on dev and both are
   gone; I mention it only so nobody expects the folder to self-clean on its own.

4. **`logged_unit` is now in the rows JSON but not in `search`-style payloads
   elsewhere.** The waste side has no equivalent because waste replaces rather than
   accumulates, so there is nothing to lock. If a future screen accumulates into a
   shared row, it needs the same treatment — the pattern is: expose the unit the
   *row* is in, not the unit the *product* prefers.
