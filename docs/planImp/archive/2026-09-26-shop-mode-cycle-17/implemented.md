# Shop mode cycle 17 — Fruit & veg: waste log and harvest log — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: 030e3e14

Pre-existing dirty files:
```
 M app/Http/Controllers/VoucherController.php     ┐
 M config/shop.php                                │
 M docs/design/shop-mode/README.md                │ cycle 15 (vouchers), mine,
 M docs/features/voucher-management.md            │ accepted and archived but
 M resources/js/shop.js                           │ still uncommitted
 M routes/web.php                                 │
?? app/Http/Controllers/Shop/VouchersController.php
?? resources/js/shop/vouchers.js                  │
?? resources/views/shop/vouchers.blade.php        │
?? tests/Feature/Shop/ShopVouchersTest.php        ┘

 M docs/planImp/planimp.md                        ┐
 M resources/css/shop.css                         │ the parallel "delivery row"
 M resources/views/shop/delivery-scan.blade.php   │ session, NOT mine
 M tests/Feature/Shop/ShopDeliveryTest.php        │
?? docs/planImp/implemented-delivery-row.md       │
?? docs/planImp/plan-delivery-row*.md             ┘

 D docs/planImp/parked/2026-09-26-shop-mode-cycle-15-vouchers/plan.md
?? docs/planImp/archive/2026-09-26-shop-mode-cycle-15/
?? docs/planImp/plan.md
```
**Three cycles' work is live in this tree at once.** Four of the files this cycle
touches are already dirty from cycle 15 — `config/shop.php`, `routes/web.php`,
`resources/js/shop.js` — and `resources/css/shop.css` is dirty from the delivery-row
session. I list my additions to each explicitly in Files changed so the reviewer can
separate them.

Design block baseline (with the delivery-row session's rules already in the
additions block):
```
$ head -529 resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo IDENTICAL
IDENTICAL
```

## Pre-flight: the plan's claims, checked

**The date defect is real and is exactly the plan's diagnosis.** Both controllers
compare a `date`-cast column against a `Y-m-d` string:
```
WasteController.php:34,82,95,129   where('waste_date', …)
HarvestController.php:52,131       where('harvest_date', …) / firstOrNew([...])
```
and `WasteLogTest` currently fails on precisely the two tests the plan names:
```
$ php artisan test --filter=WasteLogTest
Tests:    2 failed, 5 passed (20 assertions)
  ⨯ entry updates the same day row instead of duplicating
  ⨯ zero quantity deletes the entry
```

Every design class the two screens need already exists in `shop.css`
(`shop-seg`, `shop-seg--block`, `shop-choices`, `shop-choice`, `shop-stepper`,
`shop-stepper__value`, `shop-search`, `shop-subtitle`, `shop-iconbtn--lg`,
`shop-list`, `shop-row__qty`, `shop-empty`), as do the icons `search`, `check`,
`minus`, `plus`, `x`, `carrot`. No `APP ADDITIONS` expected.

Route permissions are as described: every `fruit-veg.waste.*` and
`fruit-veg.harvest.*` route carries `permission:fruit_veg.operate`.

One fixture detail the plan's step 7 does not spell out, which would have cost a
debugging round: **`PRODUCTS_CAT.PRODUCT` holds `PRODUCTS.ID`, not `CODE`**
(`TillVisibilityService` line 167 does `whereIn('ID', ProductsCat::pluck('PRODUCT'))`).
The fixture inserts the product ID there.

## Steps

### 1. Date predicates that work on both databases — done

Changed: `app/Http/Controllers/WasteController.php` (4 sites),
`app/Http/Controllers/HarvestController.php` (2 sites).

Check output:
```
$ php artisan test --filter=WasteLogTest
   PASS  Tests\Feature\WasteLogTest
  ✓ entry updates the same day row instead of duplicating     ← was failing
  ✓ zero quantity deletes the entry                           ← was failing
Tests:    7 passed (22 assertions)

$ grep -n "where('waste_date'\|where('harvest_date'" <both controllers>
HarvestController.php:57:  Harvest::where('harvest_date', '>=', now()->subDays(30)->toDateString())
```
The one remaining hit is the 30-day range predicate the plan says to leave alone.
I checked it is genuinely safe rather than assuming: on SQLite the comparison is
`'2026-08-27 00:00:00' >= '2026-08-27'`, which is true because the stored value
shares the prefix and is longer. Only the equality predicates were broken.

`updateOrCreate` and `firstOrNew` could not simply take a `whereDate`, because the
broken comparison lives inside their attribute array. Both became an explicit
`whereDate(...)->where('product_code', ...)->first() ?? new Model([...])` followed
by `fill()->save()`, with a comment at each site saying why, so the next person does
not tidy them back into `updateOrCreate`.

**This moves the suite baseline.** Two of the "identical 17" pre-existing failures
are now green, so from here the baseline is **15**, not 17. Both were failing for
this exact reason.

### 2. JSON reads for the Shop screens — done

Changed: `app/Http/Controllers/WasteController.php` (`index()`'s body extracted to
`rowsFor(string $date): Collection`, new `rows()`),
`app/Http/Controllers/HarvestController.php` (`index()`'s body extracted to
`dataFor(string $date): array{rows, available}`, new `rows()`), `routes/web.php`.

Check output:
```
$ php artisan route:list --name=fruit-veg.waste.rows -v
GET|HEAD  fruit-veg/waste/rows  fruit-veg.waste.rows › WasteController@rows
    ⇂ App\Http\Middleware\PermissionMiddleware:fruit_veg.operate

$ php artisan route:list --name=fruit-veg.harvest.rows -v
GET|HEAD  fruit-veg/harvest/rows  fruit-veg.harvest.rows › HarvestController@rows
    ⇂ App\Http\Middleware\PermissionMiddleware:fruit_veg.operate
```

Rather than only rendering the office pages through the kernel, I hit both new
endpoints and both office pages in the browser against the **real dev data**, which
a fixture cannot tell me:
```
/fruit-veg/waste     200, table renders, 100 rows
/fruit-veg/harvest   200, renders

/fruit-veg/waste/rows    200  date 2026-09-26, 98 products
  first: { code "1108", name "Apples Akane", category "Fruit", origin "France",
           class "II", on_till true, current_price 4.2, priced_unit "kg",
           quantity null, unit "kg", value null }

/fruit-veg/harvest/rows  200  date 2026-09-26, rows 0, available 111
  first available: { code "000000000040", name "Rosemary", unit "kg" }
```
The waste row shape matches `search()`'s exactly, which is what the Shop screen
needs so one client-side row renderer serves both. `rows: 0` for harvest is correct
— nothing has been harvested in the last 30 days on dev.

`HarvestController::rows()` needs one query the office page does not: `dataFor()`
returns `logged` but not who logged it or when, so `rows()` re-reads the day's
`Harvest` rows with `creator` to fill `updated_at` and `by`. Label payloads are
dropped, as the plan says.

### 3. Shop routes, controller, tile — done

Changed: `app/Http/Controllers/Shop/FruitVegController.php (new)`, `routes/web.php`,
`config/shop.php`.

The tile is now `'hint' => 'Waste and harvest logs'`, `'route' => 'shop.fv.waste'`,
`'permissions' => ['fruit_veg.operate']`; tone unchanged.

Check output:
```
$ php artisan route:list --name=shop.fv
GET|HEAD  shop/fv/harvest  shop.fv.harvest › Shop\FruitVegController@harvest
GET|HEAD  shop/fv/waste    shop.fv.waste   › Shop\FruitVegController@waste
Showing [2] routes
```
`ShopHomeTest` needed no change — no assertion pins the F&V tile's route or
permission. The new `home_tile_opens_the_waste_log_for_operate_only_users` covers
the repoint from both sides.

### 4 & 5. Behaviour: `fv-waste.js` and `fv-harvest.js` — done

Changed: `resources/js/shop/fv-waste.js (new)`,
`resources/js/shop/fv-harvest.js (new)`, `resources/js/shop.js`.

```
$ node --check on both                          → parse
$ grep -c "route(" resources/js/shop/fv-*.js    → 0, 0
```

The two modules deliberately share no code even though their pads look alike,
because their endpoints are opposites: **waste replaces** the day's row, so the
client must send today's total plus the new amount; **harvest accumulates**, so the
client sends only the new amount and then re-reads, because the server also stamps
the time and the user.

Node exercise, both modules, real code with `fetch` stubbed:
```
--- waste ---
ok   loaded products: 3            ok   today list: ["B1"]
ok   unit after select: "kg"       ok   amount after select: 0.5
ok   amount after bump(2): 1.5     ok   amountText: "1.5 kg"
ok   logLabel: "Log 1.5 kg Bananas"
ok   posted total (2 + 1.5): {"product_code":"B1","quantity":"3.50","unit":"kg"}
     toast: "Logged 1.5 kg Bananas · 3.5 kg today"
ok   row updated: 3.5              ok   amount reset to step: 0.5
ok   amount after setUnit: 1       ok   todayTotal in other unit: 0
ok   unitClash now: true           ok   amountText units: "1 unit" / "2 units"
ok   search results: ["K1"]        ok   cleared search shows range: 3
ok   client filter: ["C1"]
ok   remove posts zero: {"product_code":"B1","quantity":0,"unit":"kg"}
ok   row cleared: null             ok   today list empty: 0
     422 toast: "Unknown product"

--- harvest ---
ok   choices order (recent first): ["S1","M1","R1"]
ok   today: ["S1"]                 ok   when(): "08:10"
ok   unit from pref: "kg"          ok   typed: "4.2"
ok   amountText (no forced decimals): "4.2 kg"
ok   logLabel: "Log 4.2 kg Salad mix"
ok   second decimal allowed: "4.29"   ok   third decimal ignored: "4.29"
ok   second point ignored: "4.29"     ok   backspace twice: "4."
ok   posted: {"code":"M1","amount":"4.20","unit":"kg"}
     toast: "Logged 4.2 kg Salad mix · 4.2 kg today"
ok   re-read after save: 1         ok   typed cleared: ""
ok   today now two: ["S1","M1"]    ok   selection kept across reload: "M1"
ok   whole kg reads plainly: "3 kg"
ok   singular / plural units: "1 unit" / "12 units"
ok   leading point becomes 0.: "0."
     422 toast: "Product does not belong to Jon."
```

Two things worth recording:

- **My first run of the harvest exercise "failed", and the module was right.** I
  had asserted that typing `9` after `4.2` would be rejected as a third decimal;
  it is the second, so it is allowed. I corrected the exercise to test the real
  boundary (`4.29` accepted, a further digit rejected) rather than "fixing" correct
  code to match a wrong expectation.
- **`load()` re-finds the selected product after a save.** Harvest re-reads the
  whole list, which replaces every object, so without this the picked product
  would silently detach and the pad would keep writing to a stale row. Asserted:
  `selection kept across reload` and `selection now carries logged`.

### 6. The two screens — done

Changed: `resources/views/shop/fv-waste.blade.php (new)`,
`resources/views/shop/fv-harvest.blade.php (new)`,
`resources/views/shop/partials/fv-nav.blade.php (new)`.

Check output:
```
$ php artisan test --filter=ShopViewContractTest
Tests:    19 passed (214 assertions)     (15 screens before, 18 now)
```

The nav partial needed one fix that a test caught rather than my eye: written the
obvious way, `@if ($active === 'waste') aria-current="page" @endif` renders as
`href="…"  aria-current="page" >` — two spaces and a trailing one. The page was
fine; the assertion on the attribute pair was not. The directives now sit tight
against the quotes and the tag renders clean, with a comment saying why.

### 7. Tests — done

Changed: `tests/Feature/Shop/ShopFruitVegTest.php (new)` — 10 tests.

Check output:
```
$ php artisan test --filter=ShopFruitVegTest
   PASS  Tests\Feature\Shop\ShopFruitVegTest
  ✓ employee can open both screens             ✓ barista is forbidden
  ✓ guest is sent to login                     ✓ home tile opens the waste log for operate only users
  ✓ waste rows lists on till products with todays entry
  ✓ waste entry replaces and zero deletes
  ✓ harvest rows lists recent and available jon products
  ✓ harvest save row accumulates               ✓ harvest refuses a product that is not jons
  ✓ office pages still render
Tests:    10 passed (65 assertions)
```

**The fixture found a real cross-connection bug in my own step 2.** The first run
of `harvest_rows_…` returned 500:
```
PDOException: SQLSTATE[HY000]: General error: 1 no such table: users
```
`Harvest` is pinned to the `mysql` connection and `User` declares none, so
`->with('creator')` inherits `mysql` from its parent. In production that is the
same database — `config/database.php` has `'default' => env('DB_CONNECTION', 'mysql')`
and `.env` sets `mysql` — so it would have worked in the shop and failed nowhere I
would have looked. Under test the default is sqlite and `mysql` is repointed, so
they are different databases and the relation has no `users` table to read.

`rows()` now queries `User` directly by id instead, which resolves on User's own
connection. Identical in production, correct in both. The comment at the site says
so, because `->with('creator')` is the obvious thing to write back.

This is worth the Planner's attention beyond this cycle: **any relation from a
`mysql`-pinned model to an unpinned one has this property**, and it is invisible
until something tests it.

### 8. Docs, README, format, build — done

Changed: `docs/features/fruit-veg-system.md` (a Harvest Log section — there was
none at all — and a "Shop mode" section with the endpoint table and the
replace-vs-accumulate rule), `docs/design/shop-mode/README.md` (Fruit & veg
bullet), `docs/development/known-issues.md` (two entries: the date-comparison
defect, and the cross-connection relation trap from step 7).

Check output:
```
$ ./vendor/bin/pint --test --dirty
PASS   10 files

$ npm run build
public/build/assets/shop-BEH_8Yw2.css   43.88 kB
public/build/assets/shop-WCIOaWIv.js    32.82 kB
✓ built in 9.15s
```

## Verification

**1. Routes**
```
$ php artisan route:list --name=shop.fv
GET|HEAD  shop/fv/harvest  shop.fv.harvest › Shop\FruitVegController@harvest
GET|HEAD  shop/fv/waste    shop.fv.waste   › Shop\FruitVegController@waste
```
Both under `permission:fruit_veg.operate`, as are `fruit-veg.waste.rows` and
`fruit-veg.harvest.rows` (middleware listings in steps 2 and 3).

**2. `php artisan test --filter="Shop|WasteLog"`**
```
Tests:    199 passed (861 assertions)
```

**3. `php artisan test`**
```
Tests:    15 failed, 594 passed (2464 assertions)
```
**15, exactly as the plan predicted.** The remaining set is the old 17 minus the
two waste tests: UdeaScrapingServiceTest ×7, CashReconciliationTest ×3,
FruitVegLabelPrintingTest ×2, ProductTest ×2, TestScraperControllerTest ×1.

Passed is 594 = 579 + 2 (the waste tests that now pass) + 10 (this cycle's tests)
+ 3 (contract-test data sets for two screens and the nav partial).

**4. The office controllers changed only as intended**
```
$ git diff --stat app/Http/Controllers/{Waste,Harvest}Controller.php
 HarvestController.php | 81 ++++++++++++++++++++++++++----
 WasteController.php   | 69 +++++++++++++++++--------
 2 files changed, 119 insertions(+), 31 deletions(-)

$ git diff --stat resources/views/fruit-veg/
(empty)
```
I read every deleted line rather than trusting the shape of the diff: all 31 are
either lines moved into the extracted private methods or the replaced date
predicates. Nothing else was removed.

**5. Contract**
```
$ grep -c "route(" resources/js/shop/fv-waste.js     → 0
$ grep -c "route(" resources/js/shop/fv-harvest.js   → 0
$ grep -rn "<script\|<style" resources/views/shop/   → 0
$ design block cmp                                    → IDENTICAL
```
No `APP ADDITIONS` were needed.

**6. `pint --test --dirty` clean; `npm run build` succeeds.** Both above.

**7. Manual walkthrough — done, in the browser, on the dev app.**

I logged and then removed everything I created, and the dev data is back as I found
it (see "What I changed on dev" below). Every action was a real tap.

**Waste log**
- Opens with the 98 on-till F&V products as choices, the nav, and the search box.
- Typing "banana" filters to Bananas **client-side** (the on-till range is already
  loaded); typing "jonagold" brings back three off-till "Apples Jonagold" variants
  **from the server search** — the two paths the plan describes, both working.
- Tapped Bananas → "2 · How much" appeared with kg selected and 0.5 kg.
- Tapped + twice → "1.5 kg", button "Log 1.5 kg Bananas".
- Logged → the tile read "per kg · 1.5 kg today", a Today list appeared with
  "Bananas · €4.28 · 1.5 kg" and an ×, and the pad reset to 0.5 kg.
- Tapped + once and logged again → toast **"Logged 1.0 kg Bananas · 2.5 kg today"**,
  Today row 2.5 kg / €7.13. That is the replace-semantics addition working against
  the real endpoint.
- Switched to units → stepper "1 unit", button "Log 1 unit Bananas", and the hint
  **"Logging in a different unit replaces today's entry."** appeared.
- Tapped × on the Today row → the row and the list disappeared, and the hint with
  them. `WasteLog::count()` back to 0.

**Harvest**
- Opens with 111 of Jon's products, Harvest marked current in the nav.
- Tapped Rosemary → pad appeared, display "Quantity (kg)".
- Tapped 4 · . · 2 → "4.2", button "Log 4.2 kg Rosemary".
- Logged → toast **"Logged 4.2 kg Rosemary · 4.2 kg today"**; the re-read returned
  the row with `updated_at` and `by: "jonathanE"`, and the Today list rendered
  **"Rosemary · 13:09 · jonathanE · 4.2 kg"**. This is the step-7 connection fix
  working against the real MySQL database, not just the sqlite fixture.
- The selection survived the reload and now carried `logged: 4.2`.
- Tapped 1 and logged → **"Logged 1 kg Rosemary · 5.2 kg today"**, tile
  "Rosemary per kg · 5.2 kg today". Accumulation, against the real endpoint.
- Switched to units, tapped 2, logged → display "Quantity (units)", and the unit
  preference came back from the server as `unit`, so it is remembered per product.
- The office `/fruit-veg/harvest` page showed the same row.
- Console across the whole walkthrough: two messages, both Alpine's startup log.

**What I did not check by hand.** The plan asks for an employee holding only
`fruit_veg.operate`. Every employee on dev also holds `fruit_veg.manage`, and the
only way to make one would be to revoke a permission from a shared role while a
second session is working in this tree — not worth it. The signed-in dev user is a
genuine employee, so everything above is the employee path;
`home_tile_opens_the_waste_log_for_operate_only_users` covers the permission
question from both sides with a user who holds `operate` and nothing else.

**What I changed on dev, and put back**
- One waste entry for Bananas today: created, added to, then removed through the
  screen's own × button. `WasteLog::count()` is 0, as it was.
- One harvest row for Rosemary today: created by the walkthrough, deleted
  afterwards. `Harvest::count()` is 197, all of them pre-existing and none today.
- One `harvest_product_units` row for Rosemary. I deleted it. If one already
  existed it held `kg`, which is the default the code falls back to, so its absence
  changes nothing — but I am recording it rather than assuming.

## Deviations

**One, in production code, and it is a fix rather than a shortcut.**
`HarvestController::rows()` does not use `->with('creator')` as step 2's shape
implies. `Harvest` is pinned to the `mysql` connection and `User` declares none, so
the relation inherits `mysql` — the same database in production, a different one
under test. It returned a 500 (`no such table: users`) the first time the new test
ran. `rows()` queries `User` directly by id instead, which is identical in
production and correct in both. Recorded in step 7 and in known-issues.

Everything else is as written. Two smaller shape choices are noted in their steps:
the nav partial's `@if` sits tight against the attribute quotes so the tag renders
without stray whitespace, and `ShopFruitVegTest` has 10 tests rather than 8.

## Files changed

Mine, this cycle:
```
 M app/Http/Controllers/HarvestController.php
 M app/Http/Controllers/WasteController.php
 M config/shop.php                    (+ the fruit-veg tile; cycle 15 also edits this file)
 M docs/design/shop-mode/README.md    (+ the Fruit & veg bullet; cycle 15 also edits this file)
 M docs/development/known-issues.md
 M docs/features/fruit-veg-system.md
 M resources/js/shop.js               (+ two registrations; cycle 15 also edits this file)
 M routes/web.php                     (+ four routes; cycle 15 also edits this file)
?? app/Http/Controllers/Shop/FruitVegController.php
?? resources/js/shop/fv-harvest.js
?? resources/js/shop/fv-waste.js
?? resources/views/shop/fv-harvest.blade.php
?? resources/views/shop/fv-waste.blade.php
?? resources/views/shop/partials/fv-nav.blade.php
?? tests/Feature/Shop/ShopFruitVegTest.php
```

Still in the tree and **not mine**: cycle 15's vouchers files, and the parallel
delivery-row session's `resources/css/shop.css`,
`resources/views/shop/delivery-scan.blade.php`,
`tests/Feature/Shop/ShopDeliveryTest.php` and `docs/planImp/planimp.md`. This cycle
made **no CSS change at all**, so any `shop.css` diff is theirs.

`public/build` is gitignored; the deploy must run the build.

**Not committed, not pushed, not deployed.**

## Notes for Planner

1. **Harvest accumulates across a unit change, and the result is meaningless.**
   Found in the walkthrough, and it is the office endpoint's behaviour, not
   something this cycle introduced: `saveRow()` adds the amount to the day's
   running total and overwrites the unit. Logging 5.2 kg of Rosemary and then 2
   units gives a single row reading **"7.2 unit"** — kilograms added to counts. The
   waste screen warns about a unit clash because waste *replaces*; the harvest
   screen cannot warn the same way because accumulating is the intended behaviour,
   and it is only the cross-unit case that is wrong. The office harvest page has
   the same hole via its per-row unit dropdown. I did not touch it: `saveRow()` is
   explicitly out of scope, and the fix is a decision (refuse the change? keep a
   row per unit? convert?) rather than an implementation. Worth a short cycle.

2. **The two "always failing" waste tests were describing a real defect.** They sat
   in the known-17 for months. Any test that has always failed is worth reading
   before it is written off as environmental. The baseline is now **15**.

3. **Any relation from a `mysql`-pinned model to an unpinned one is untestable as
   written.** `Harvest`, `WasteLog` and anything else with
   `protected $connection = 'mysql'` hands that connection to its relations, which
   is the same database in production and a different one under test. This is now
   in known-issues, but it is worth a grep: other controllers may be using such a
   relation in code that has no test, and it will keep working in the shop while
   being impossible to cover.

4. **The Home tile's permission was the whole of the owner's complaint.** It asked
   for `fruit_veg.manage`, which `RolePermissionGrantsTest` pins *off* for
   employees even though the seeder grants it. So on a fresh seed the tile was
   invisible and neither log was reachable — exactly what was reported. Worth
   checking the other tiles in `config/shop.php` against
   `RolePermissionGrantsTest` for the same mismatch; I did not, as it is outside
   this cycle.

5. **`x-shop-layout title="Fruit &amp; veg"` renders as `Fruit &amp;amp; veg`.**
   I wrote it that way and the browser showed it; the prop is escaped by Blade, so
   it must carry a literal `&`. Caught by looking at the screen, not by any test —
   there is now an assertion for it. Any future screen with punctuation in its
   title has the same trap.
