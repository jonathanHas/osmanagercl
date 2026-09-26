# Shop mode cycle 17f — Fruit & veg housekeeping: one unit per harvest day, no thumbnail orphans, no dead `?t=`

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

Three small things left over from the fruit-and-veg cycles, done together:
1. **Harvest keeps one unit per product per day.** Today `saveRow()` adds any amount onto the day's row and overwrites its unit, so 5.2 kg then 2 units becomes "7.2 unit". The endpoint now refuses an entry whose unit differs from the row already logged that day, with a message that says what is logged and what to do; the Shop screen locks the unit switch to the day's unit so staff never see the refusal in normal use; the office page shows the message through its existing failed-save path.
2. **The thumbnail cache does not keep orphans.** Writing a new thumbnail for a product and size deletes the older files for the same product and size, so a replaced photo leaves nothing behind.
3. **The dead `?t=` branch leaves the image route.** Nothing sends it; removing it leaves two caching paths (versioned, unversioned) instead of three.

## Context

- `HarvestController::saveRow()` (cycle 17 `whereDate` version): validates `date`, `code`, `amount`, `unit` (kg|unit), `notes`; refuses a non-Jon product with 422 `{ success: false, message }`; `HarvestProductUnit::updateOrCreate` remembers the unit; then `Harvest::whereDate(...)->where('product_code')->first() ?? new Harvest(...)`, `fill([... 'quantity' => existing + amount, 'unit' => $unit ...])->save()`; returns `{ success, logged, unit, saved_amount }`. `rows()` (17c/17e) returns rows `{ code, name, unit (preference), logged, updated_at, by, image_url }` and `available`. The day's row unit is not in the JSON.
- Office harvest page: on `data.success === false` → `alert(data.message || 'Save failed.')`; each row has a kg/unit radio, so the refusal is reachable there too.
- Shop harvest (`resources/js/shop/fv-harvest.js`, `fv-harvest.blade.php`): `select(p)` sets `unit = p.unit`; the unit `shop-seg--block` radios call `setUnit()`; `log()` posts `{ date, code, amount, unit }` and re-reads on success, toasts `data.message` on failure.
- Existing test `ShopFruitVegTest::harvest_save_row_accumulates` logs 4.2 kg, then 1 kg (→ 5.2), **then 2 units onto the same day and asserts success** and that the preference became `unit`. Under the new rule the third save is refused; the preference part of the test moves to a product with no row that day.
- `ProductThumbnailService` (17d): `jpeg()` computes `path()` = `fv-thumbs/<md5(code)12>-<size>-<md5(blob)12>.jpg`, returns the cached file if present, else encodes, `put`s and returns. `Storage::disk('local')`.
- `FruitVegController::productImage()` (17e): `$cacheControl = $versioned ? 'public, max-age=604800, immutable' : 'public, max-age='.($request->has('t') ? 300 : 0).', must-revalidate'`. Repo-wide grep for a sender of `t=` to this route: none (the only hits are the unrelated `x-product-image` component and a `.backup` file).
- Suite baseline: 15 failed / 617 passed.

## Constraints

- Do not commit, push or deploy.
- `saveRow()` keeps accumulating within a unit; only a **different** unit on an existing day row is refused. A product with no row that day accepts either unit as before (and the preference is still remembered).
- Office harvest view untouched (its alert already shows the message).
- No stylesheet change.

## Out of scope

- A "change today's unit" action (the office `destroy` route removes a day's row; that remains the correction path).
- Anything on the waste side (waste replaces, so it has no such hole).

## Steps

### 1. One unit per day in `saveRow()`
Files: `app/Http/Controllers/HarvestController.php`
What: after finding the day's row and before `fill()`: if the row exists (`$harvest->exists`) and `$harvest->unit !== $unit` → `return response()->json(['success' => false, 'message' => "Already logged {$logged} {$existingUnit} of {$name} today. Log in {$existingUnit}, or remove today's entry on the office harvest page to change the unit.", 'logged' => (float) $harvest->quantity, 'unit' => $harvest->unit], 422)` where `$logged` is the existing quantity formatted without trailing zeros and `$existingUnit` reads `kg` or `units`. Do the check **before** `HarvestProductUnit::updateOrCreate` so a refused entry does not change the remembered preference. Also add `'logged_unit' => $entry?->unit` to each row in `rows()` (null when nothing is logged that day).
Check: tests in step 4.

### 2. The Shop screen locks the unit to the day's row
Files: `resources/js/shop/fv-harvest.js`, `resources/views/shop/fv-harvest.blade.php`
What: `select(p)` sets `unit = p.logged > 0 && p.logged_unit ? p.logged_unit : p.unit`. New getter `lockedUnit` → `selected?.logged > 0 ? selected.logged_unit : null`. The two unit radios get `:disabled="lockedUnit && lockedUnit !== 'kg'"` / `!== 'unit'` respectively, and a `shop-meta` line under the switch `x-show="lockedUnit"` reading `x-text="'Logged in ' + (lockedUnit === 'kg' ? 'kg' : 'units') + ' today · to change, remove today's entry on the office page'"`. `setUnit(u)` ignores a change to a unit other than the locked one. Keep the 422 toast path: if the server refuses anyway (two people, or a stale screen), the message shows and `load()` runs so the lock catches up.
Check: node exercise: select a row with `logged 5.2, logged_unit 'kg', unit 'unit'` (preference differs from today's unit) → `unit` 'kg', `lockedUnit` 'kg'; `setUnit('unit')` → still 'kg'; select an available product → `lockedUnit` null and `unit` = its preference; a stubbed 422 on `log()` → toast bad with the message and `load()` called.

### 3. Thumbnail cache: replace, do not accumulate
Files: `app/Services/ProductThumbnailService.php`
What: on the cache-miss path, before `put`, delete every file in `FOLDER` whose name starts with `<md5(code)12>-<size>-` (the prefix `path()` builds without the blob part; extract a `prefix($code, $size)` helper used by both). `$disk->files(self::FOLDER)` is a single directory read, only on a miss, and the folder holds one file per product per size.
Check: unit test `an older thumbnail for the same product and size is removed when the photo changes`: encode blob A at 112 → file A; encode blob B at 112 → file B exists, file A gone; encode blob B at 224 → both sizes present; another product's file untouched.

### 4. Remove the dead `?t=` branch
Files: `app/Http/Controllers/FruitVegController.php`, `docs/features/fruit-veg-system.md`
What: the unversioned header becomes the constant `'public, max-age=0, must-revalidate'`; delete the `$request->has('t')` expression and any comment about `t`. If the feature doc mentions `?t`, remove it.
Check: `grep -n "'t'" app/Http/Controllers/FruitVegController.php` → 0 hits in `productImage()`; `FruitVegProductImageTest` green (its unversioned-header assertions expect `max-age=0`).

### 5. Tests
Files: `tests/Feature/Shop/ShopFruitVegTest.php`, `tests/Unit/ProductThumbnailServiceTest.php`
What:
- Rewrite `harvest_save_row_accumulates`: 4.2 kg then 1 kg → 5.2 (unchanged); then 2 **units** on the same product and day → **422** with `success` false, a message containing "Already logged 5.2 kg", `logged` 5.2, `unit` 'kg'; the row is still 5.2 kg; the preference is still `kg`. Then a second product (no row today) logged in units → 200 and its preference is `unit`; `rows` for it shows `unit` 'unit' and `logged_unit` 'unit'; the first product's row shows `logged_unit` 'kg'.
- `harvest_rows_lists_recent_and_available_jon_products`: assert `logged_unit` is null for the product with no row today and 'kg' for the one logged today.
- Step 3's unit test.
- `employee_can_open_both_screens`: the harvest markup contains `lockedUnit` (the switch is bound) and "remove today's entry on the office page".
Check: `php artisan test --filter="ShopFruitVegTest|ProductThumbnailServiceTest|FruitVegProductImageTest"` green.

### 6. Docs, format, build
Files: `docs/features/fruit-veg-system.md`, `docs/design/shop-mode/README.md`, all touched
What: feature doc: the one-unit-per-day rule and the office correction path; the thumbnail cache no longer keeps orphans; `?t` gone. README: the harvest unit lock. `./vendor/bin/pint --dirty`; `npm run build`.
Check: `./vendor/bin/pint --test --dirty` clean; build succeeds.

## Verification

1. `php artisan test --filter="Shop|FruitVeg|ProductThumbnail"` → green; `php artisan test` → 15 failed, the identical set; passed = 617 + new tests.
2. `git diff app/Http/Controllers/HarvestController.php` → `saveRow()` and `rows()` only; `git diff app/Http/Controllers/FruitVegController.php` → the header expression only; `git diff --stat resources/views/fruit-veg/` → empty.
3. Contract greps; design block `cmp` identical.
4. Manual, dev app, on a throwaway harvest row deleted afterwards: log 1 kg of a product → the unit switch locks to kg with the hint; the units radio is disabled; on the office harvest page, set that row's radio to units and save → the alert reads "Already logged 1 kg of … today …" and the row is unchanged; log 1 more kg from the Shop → 2 kg; a second product logged in units works. Thumbnails: replace one product's photo on the office manage page (or via tinker with a backup, as in 17e), open the Shop waste log → `storage/app/private/fv-thumbs/` has one file for that product at 112 (`ls | grep <md5(code)12>-112-` → 1), restore the photo.

## Risks

- **The office page's alert** is a browser dialog; that is its existing behaviour and it stays (the office view is out of scope). It blocks the implementer's browser automation, so the office half of the manual check is for the owner or via the endpoint directly.
- **Removing `?t`** cannot break a caller because none exists in the codebase; a bookmark with `?t=` simply gets the standard headers.
- **Directory listing on a thumbnail miss** is a few hundred entries at most; misses are rare after the first day.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end and the diffs of the harvest controller, the image route, the thumbnail service, the Shop harvest script and view, and both test files. Reran `php artisan test`: 15 failed / 620 passed, the identical set (617 + 3 new tests). Design block untouched; office views untouched; the image route diff is the one header line.

**Steps 1–6: pass.** The refusal sits before the preference is remembered; the rows carry the day's unit; the Shop switch locks to it and re-reads after a refusal so a stale screen catches up; the thumbnail write sweeps older files for the same product and size and nothing else; `?t` is gone. The implementer's walkthrough exercised the lock by tap, the rule through the real endpoint, and the orphan sweep on real data, where it also removed the orphan cycle 17e had left.

**Deviations:** none in behaviour. The third test on message wording ("3 units", not "3.00 unit") is a good addition: the text is read aloud on a shop floor.

**Notes for Planner.**
1. The harvest unit defect, carried since cycle 17, is closed.
2. The office harvest page shows the refusal as a browser alert: its existing behaviour; **noted** for whenever the office F&V pages are revisited.
3. Orphans on products whose photo never changes again stay: **accepted**; there were two on dev and both are gone.
4. The rule of thumb, expose the unit the row is in rather than the unit the product prefers: **agreed**, recorded in memory for any future accumulating screen.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-17f/`.
