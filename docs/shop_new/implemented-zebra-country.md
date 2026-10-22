# Zebra tab: show and set the country of origin — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-10-22

## Baseline
HEAD: 813040f9 (the plan says `58842899` with the Labels cycle uncommitted; the
owner has since committed it as `813040f9` "pallet % info & shop print
improvement", so the Labels cycle files are clean at HEAD)
Pre-existing dirty files:
```
 M docs/shop_new/README.md
?? docs/shop_new/findings/
?? docs/shop_new/plan-zebra-country.md
```

## Steps
### 1. Service: country per label, and `setCountry()` — done
Changed: `app/Services/ZebraLabelListService.php`
- `grouped()` loop body moved into private `labelData(ZebraLabel, ?Product, Collection $countries)`;
  standalone labels go through it too (`$product` null → no mismatches, `till` null).
- One `Country::orderBy('name')->get(['id','name'])` per call (private `countries()`).
- New public `setCountry(ZebraLabel, Country)`, plus `groupOf(ZebraLabel)` and
  `shopItem(array, string $group)` (the Shop JSON mapping, used by step 2).

Check output (dev, tinker; `grouped()` with `country` stripped compared byte-for-byte
with a snapshot taken before the change):
```
keys: ["label","label_id","field_index","till","till_id"]
French Beans 200g => {"label":"Spain","label_id":4,"field_index":3,"till":null,"till_id":null}
Rocket imported 100g => {"label":null,"label_id":null,"field_index":null,"till":null,"till_id":null}
Kale Curly 300g => {"label":null,"label_id":null,"field_index":null,"till":null,"till_id":null}
parsnip => {"label":"France","label_id":2,"field_index":5,"till":null,"till_id":null}
Spinach_Mossfield => {"label":null,...,"till":"Ireland","till_id":1}
cmp grouped-before.json grouped-after-nocountry.json → IDENTICAL
```
`setCountry()` not called on dev data.

### 2. JSON and endpoint — done
Changed: `app/Http/Controllers/LabelAreaController.php` (`zebraList()` maps Zebra items
through `ZebraLabelListService::shopItem()`; translated items get `country`/`country_url`
null), `app/Http/Controllers/ZebraLabelController.php` (`setCountry()`), `routes/web.php`,
`config/shop.php`.

The shared per-item mapping lives on the service as `shopItem($item, $group)`; the
endpoint gets the group from `groupOf($label)` (same rule as `grouped()`).

Check output:
```
PATCH  labels/zebra/manage/{zebraLabel}/country  zebra-labels.set-country › ZebraLabelController@setCountry
       ⇂ App\Http\Middleware\PermissionMiddleware:labels.print

GET /labels/zebra/list as `test` through the kernel:
status 200
zebra 17 all with 5 country keys: yes, all country_url: yes
translated 297 all null country & country_url: yes
{"kind":"zebra","group":"other","id":5,"name":"Green Beans 200g","label":"French Beans 200g",...,
 "country":{"label":"Spain","label_id":4,"field_index":3,"till":null,"till_id":null},
 "print_url":".../labels/zebra/manage/5/print","country_url":".../labels/zebra/manage/5/country"}
```

### 3. Shop controller and view — done
Changed: `app/Http/Controllers/Shop/ZebraController.php` (`countries`),
`resources/views/shop/zebra.blade.php` (Country differs card removed; Country of origin
field after the price card; row meta gets the `help` icon + `shop-sr-only` text).
Both the card notice and the row icon use `noCountryText(...)`.

Check output:
```
php artisan test --filter=ShopViewContractTest → Tests: 30 deprecated (338 assertions)  (all pass)
grep -c "shop-select" zebra.blade.php → 1
grep -c "useTillCountry\|Print with till country" zebra.blade.php → 0
```

### 4. Shop module — done
Changed: `resources/js/shop/zebra.js` — `useTillCountry`, `countryText` and the country
branch of `overrides()` removed; header comment updated; `countryId`, `noCountryText(r)`,
`countryHint`, `setCountry()` added (plus a small `countryFailed(s)` helper for the two
failure paths).

Check output (`node --check` OK; node exercise with stubbed `$root.dataset`, `fetch`,
`window`, `document`, run on a copy of the module in the scratchpad):
```
countryId "2" hint "Label says Spain · till says Ireland" noCountryText false
overrides {"2":"\\152.95"}            ← price only, no country index
b: noCountryText true hint "" countryId ""
field_index 0: noCountryText false translated: false
fetch /c/5 PATCH {"country_id":5}
row replaced Netherlands true selected Netherlands toast Country of origin: Netherlands busy false
failure toast Could not save the country countryId reset "5"   ← reset from the unchanged selection
grep -c "route(" resources/js/shop/zebra.js → 0
```

### 5. CSS — done
Changed: `resources/css/shop.css` (two rules, end of APP ADDITIONS, with a comment).
Check output:
```
head -n 608 resources/css/shop.css | cmp - docs/design/shop-mode/shop.css → design block IDENTICAL
cmp public/images/shop-icons.svg docs/design/shop-mode/shop-icons.svg → sprite IDENTICAL
npm run build → ✓ built in 3.88s
```

### 6. Tests — done
Changed: `tests/Feature/Shop/ShopZebraTest.php` (extended; existing assertions untouched).
- `setUp()`: Ireland/Spain/France/Netherlands ids via `Country::firstOrCreate` (see
  Deviations: the table is seeded by its migration, not empty); parsnip's ZPL gains a
  `France` text field (`PARSNIP_ZPL`).
- French Beans fixture in a helper `addBeans()` instead of `setUp()` (see Deviations):
  product `p4` Green Beans 200g `000000000285`, on the till, `vegDetails` Ireland, label
  fields `Organic Store`, `Green Beans`, `\152.95`, `Spain`, `Origin :`; PRICESELL 2.3984
  (× 1.23 = 2.95, no price mismatch — asserted).
- New: `test_list_carries_the_country_of_origin`, `test_set_country_rewrites_the_label_and_the_till_record`,
  `test_set_country_on_a_label_without_country_text_saves_the_till_only`,
  `test_set_country_on_a_standalone_label_changes_only_the_label`,
  `test_set_country_validates_and_is_for_printers` (422 unknown id, 403 barista, nothing changed).
- `test_employee_can_open_the_zebra_screen`: also asserts `id="zebra-country"`, the four
  `<option value="id">Name</option>` tags, and no `Print with till country`.

Check output:
```
php artisan test --filter=ShopZebraTest → 15 tests, all pass (shown as "!" = PHP 8.5
  PDO::MYSQL_ATTR_SSL_CA deprecation from config, as every test in the suite)
php artisan test --filter="ShopZebraTest|ShopLabelsTest|ShopViewContractTest|ConfinePinSessionTest"
  → Tests: 78 deprecated (685 assertions), 0 failed
```

### 7. Docs, format, build — done
Changed: `docs/features/shop-mode.md` (Zebra labels bullet + code line),
`docs/design/shop-mode/README.md` (07b clause), `docs/shop_new/README.md` ("Screens today"
clause; this file was already dirty at baseline with the Planner's "Current task" row —
my change is only the Screens today clause).

Check output:
```
./vendor/bin/pint --test --dirty → PASS 7 files
npm run build → ✓ built in 4.40s
```

## Deviations
1. **French Beans fixture in a helper, not `setUp()`.** Adding a fifth label in `setUp()`
   would have forced a rewrite of the existing
   `test_list_groups_labels_reports_mismatches_and_includes_translations`
   (`assertCount(4)` and positional destructuring). `addBeans()` is called by the three
   tests that need it, and the existing tests are untouched (only additive asserts in
   `test_employee_can_open_the_zebra_screen`, as the plan asks). The new list test looks
   items up by `kind:id` instead.
2. **Countries via `firstOrCreate`.** The plan says `countries` is empty in tests; it is
   not: `2025_07_26_151328_create_countries_table` inserts 37 rows, so `Country::create`
   hit the unique index. The test reads the four ids with `firstOrCreate`.
3. **Shared Shop mapping on the service.** The plan left the place to me:
   `ZebraLabelListService::shopItem($item, $group)` (used by `zebraList()` and the new
   endpoint) and `groupOf($label)` (so the endpoint returns the right `group`). The
   service's `setCountry()` returns the `grouped()`-shape array as the plan says; the
   controller maps it with `shopItem()`.
4. **`x-cloak` on the country field** in the card, like the other `x-show` blocks there.
   Cosmetic (no flash before Alpine starts).
5. A small `countryFailed(s)` helper in `zebra.js` shares the failure toast + reset between
   the non-OK and the thrown paths.
6. **Baseline HEAD** is `813040f9`, not `58842899`: the Labels cycle was committed by the
   owner in between. Nothing in the plan depended on it.

## Verification
1. `php artisan route:list --name=zebra-labels.set-country` →
   `PATCH labels/zebra/manage/{zebraLabel}/country zebra-labels.set-country › ZebraLabelController@setCountry`,
   middleware `PermissionMiddleware:labels.print`. ✔
2. `php artisan test tests/Feature/Shop` → `Tests: 357 deprecated (1924 assertions)`, 0 failed. ✔
3. `php artisan test` → `Tests: 1081 deprecated, 16 failed, 43 passed (5321 assertions)`;
   the 16 are the identical baseline set: `UdeaScrapingServiceTest` ×7,
   `CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2,
   `TestScraperControllerTest` ×1, `BackupConfigurationTest` ×1. ✔
4. `git diff --stat resources/views/labels/ app/Http/Controllers/FruitVegController.php` → empty. ✔
   `GET /labels/zebra` as `test` through the kernel: status 200, 17 active labels, 0 missing
   from the page. Stronger check: rendered the page with my five server files stashed
   (HEAD code) and again with them; after normalising the CSRF token and removing the
   `,"country":{…}` objects the two HTML files are **identical**. ✔
5. `grep -c "route(" resources/js/shop/zebra.js` → 0; design block `cmp` identical; sprite
   `cmp` identical. ✔
6. `./vendor/bin/pint --test --dirty` → PASS 7 files; `npm run build` → ✓ built. ✔
7. Browser on dev (Playwright MCP, session minted for katelyn, user 3, employee):
   - Rows with the help icon: Spinach_Mossfield, Mixed Salad, Rocket_Mossfield, Kale Curly
     300g, slieve Bloom Kale, Rocket imported 100g, SB Spinach 280g, SB Chard 270g, both
     chutney back labels, and the two standalone chutney labels. Without: Carrots imported,
     lambs lettuce, Spinach Imported, French Beans 200g, parsnip. ✔ (matches plan list)
   - Tap French Beans → select preset to Spain (value 4, 38 options incl. "Choose…"),
     no notice, hint "Not on the till". Screenshot `/tmp/claude-playwright/zebra-country-beans.png`.
   - Pick Italy → toast "Country of origin: Italy", select stays Italy, hint hidden,
     select enabled again. Close + reopen the row → still Italy. Tinker: ZPL has
     `^FDItaly^FS`, no `^FDSpain^FS`; `vegDetails` for `000000000285` countryCode 8 = Italy. ✔
   - Put back: picked Spain in the UI → toast "Country of origin: Spain".
   - Tap Rocket imported → select "Choose…" (blank), notice "Not printed on this label.
     Fix the label in the office." Pick Netherlands → toast "Country of origin:
     Netherlands", notice still shown.
   - Console: 0 errors, 0 warnings.
   - Dev data restored: both labels' ZPL compared with copies taken before the check —
     French Beans (id 5) and Rocket imported (id 13) **byte-identical** to before (Rocket
     still says Holland); the two `vegDetails` rows the check created (285, 212) deleted,
     0 left.
8. Owner production check: not mine to run.

## Files changed
```
 M app/Http/Controllers/LabelAreaController.php
 M app/Http/Controllers/Shop/ZebraController.php
 M app/Http/Controllers/ZebraLabelController.php
 M app/Services/ZebraLabelListService.php
 M config/shop.php
 M docs/design/shop-mode/README.md
 M docs/features/shop-mode.md
 M docs/shop_new/README.md            (pre-existing dirty; I added the Screens today clause)
 M resources/css/shop.css
 M resources/js/shop/zebra.js
 M resources/views/shop/zebra.blade.php
 M routes/web.php
 M tests/Feature/Shop/ShopZebraTest.php
?? docs/shop_new/findings/            (pre-existing)
?? docs/shop_new/implemented-zebra-country.md
?? docs/shop_new/plan-zebra-country.md (pre-existing)
```
Not committed. No migration.

## Notes for Planner
1. `countries` is seeded by its create migration (37 rows), in tests too — worth correcting
   in the plan's Context habit for future cycles.
2. `VegDetails::country()` does `$this->setConnection('mysql')->belongsTo(...)`, which
   switches *that VegDetails instance* to the `mysql` connection as a side effect. Harmless
   here (the write goes through the static `upsertForProduct()`), but a later
   `$vegDetails->save()` on an instance whose country was loaded would go to the wrong
   database. Not touched.
3. Two labels share product `000000000102` (Kale Curly 300g and slieve Bloom Kale); a scan
   opens whichever comes first. Setting the country on one rewrites the till record for
   both, but only that one label's text (both are graphics-only on dev, so nothing differs
   today). Not touched.
4. A label whose `product_code` points at a product that no longer exists is skipped by
   `grouped()` (so it is not in the Shop list), but if `set-country` were called on it,
   it is treated as standalone (label text only). Not reachable from the UI.
5. The toast names `country.till ?? country.label` from the response, which is always the
   chosen country (till is set whenever there is a product; label for standalone).
6. The finding file `docs/shop_new/findings/2026-10-22-zebra-country.md` is still in
   `findings/` for you to move into the archive on acceptance; the design README now links
   to that path.
