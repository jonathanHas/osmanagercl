# Labels in Shop mode — Shelf labels and Zebra labels tabs — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-10-21

## Baseline
HEAD: 58842899
Pre-existing dirty files:
```
 M docs/deliveries/README.md
 D docs/deliveries/implemented-glued-price.md
 D docs/deliveries/plan-glued-price.md
 M docs/design/shop-mode/README.md
 M docs/shop_new/README.md
?? docs/deliveries/archive/2026-10-20-glued-price/
?? docs/design/shop-mode/screen-07-shelf-labels.html
?? docs/design/shop-mode/screen-07b-zebra-labels.html
?? docs/shop_new/design-brief-labels.md
?? docs/shop_new/plan.md
```

## Steps

### 1. Labels tab nav partial — done
Changed: `resources/views/shop/partials/labels-nav.blade.php` (new; checked first, did not exist)
Copied from `fv-nav.blade.php`; `shop-seg shop-seg--block`, `tag` / `barcode` icons, `aria-current` tight against the quote, comment kept.
Check: see step 9 (`aria-current="page"` asserted on each tab by the tests).

### 2. Shelf tab: view and controller — done
Changed: `resources/views/shop/labels.blade.php`, `app/Http/Controllers/Shop/LabelsController.php`
`shop-page--narrow`, three new `data-*` URLs, nav first, tile nav and `$zebraUrl` removed, new empty-state text,
Printed today section, `@submit` reloads both, Undo button in the toast. Chevrons use
`<x-shop.icon name="chevron-right" class="shop-row__chev" x-bind:class="…" />`
(`x-bind:` rather than `:` so Blade passes it through instead of evaluating PHP). See Deviations for the
`shop-contents` wrappers.
Check output:
```
$ grep -c "labels.zebra\b" resources/views/shop/labels.blade.php
0
$ grep -c "labels-nav" resources/views/shop/labels.blade.php
1
$ php artisan test --filter="ShopViewContractTest|ConfinePinSessionTest|ShopLabelsTest"
  Tests:    55 deprecated, 1 failed (501 assertions)
  (the one failure: ShopLabelsTest::employee_can_open_the_labels_screen still asserting
   href="…/labels/zebra" — the tile this step removes; updated in step 9)
```

### 3. Shelf tab: module — done
Changed: `resources/js/shop/labels.js`
Check output (node exercise, script in scratchpad `labels-ex.mjs`, stubbed `$root.dataset` and `fetch`):
```
$ node --check resources/js/shop/labels.js   → OK
historyMeta 2 batches · 5 labels
inQueue p1 true inQueue p9 false
requeue post ["/requeue",{"product_id":"p9"}] reloaded 1 toast Feta is back in the queue
dismiss post ["/d",{"barcode":"111"}] toast has undo function
undo post ["/requeue",{"product_id":"p1"}] toast null
clear post ["/clear",{}] toast ok function
undo-clear post /restore ["timestamp","product_ids"] ["p1","p2"]
employee-like clear undo null          (restoreUrl blank → no Undo offered)
yesterday meta Yesterday · 2 batches · 5 labels
$ grep -c "route(" resources/js/shop/labels.js
0
```

### 4. Printed-today JSON — done
Changed: `app/Services/LabelQueueService.php` (`recentPrints()`, `groupPrintsByWindow()` body verbatim),
`app/Http/Controllers/LabelAreaController.php` (`shelfLabels()` delegates, private method deleted, `printedToday()` added),
`routes/web.php`.
Check output:
```
$ php artisan route:list --name=labels.printed-today -v
  GET|HEAD  labels/printed-today labels.printed-today › LabelAreaController@printedToday
            ⇂ web ⇂ Authenticate ⇂ PermissionMiddleware:labels.print
$ grep -c "groupLabelPrintsByTime" app/Http/Controllers/LabelAreaController.php
0
```
GET `/labels/shelf-labels` as `test` through the kernel, at HEAD (a `git worktree` of `58842899` in the
scratchpad with its own `App\` autoloader; confirmed HEAD code by `/labels/printed-today` → 404 there) and now:
both 200, 201,285 bytes each, `diff` (CSRF token and 40-hex hashes masked) → **no differences**.
Dev JSON today: `{"day":"today","batches":[]}` (no prints today or yesterday on dev).

### 5. Zebra list service and JSON — done
Changed: `app/Services/ZebraLabelListService.php` (new; did not exist), `app/Http/Controllers/LabelAreaController.php`
(constructor injection, `zebra()` else-branch delegates, `zebraList()` added), `routes/web.php` (route before `labels/zebra/manage/…`).
Check output:
```
$ php artisan route:list --name=labels.zebra-list -v
  GET|HEAD  labels/zebra/list labels.zebra-list › LabelAreaController@zebraList
            ⇂ web ⇂ Authenticate ⇂ PermissionMiddleware:labels.print
GET /labels/zebra as test, HEAD vs now: both 200, 117,324 bytes each, diff → no differences
  (so the office page still lists the same 17 labels in the same order)
GET /labels/zebra/list (dev): 314 items — till 3, other 11, standalone 3 (= 17 Zebra), translated 297
  keys containing "zpl": []
  first: {"kind":"zebra","group":"till","id":20,"name":"Spinach - Mossfield 300g","label":"Spinach_Mossfield",
          "product_code":"000000000224","width_mm":112.6,"height_mm":75.1,"default_copies":1,"mismatches":null,
          "print_url":"http://localhost/labels/zebra/manage/20/print"}
```
(297 translated, not 301: the newest row per product code, minus those whose newest row has no ZPL.)

### 6. Zebra tab: route, controller, view — done
Changed: `app/Http/Controllers/Shop/ZebraController.php` (new), `resources/views/shop/zebra.blade.php` (new), `routes/web.php`.
Both checked first; neither existed.
Check output:
```
$ php artisan route:list --name=shop.zebra -v
  GET|HEAD  shop/labels/zebra shop.zebra › Shop\ZebraController@index  ⇂ PermissionMiddleware:labels.print
$ grep -c "labels-nav" resources/views/shop/zebra.blade.php
1
ShopViewContractTest: green (run above; the new screen is in the provider)
```

### 7. Zebra tab: module — done
Changed: `resources/js/shop/zebra.js` (new), `resources/js/shop.js` (import + `Alpine.data('shopZebra', zebra)`).
No shared toast / post part exists in `resources/js/shop/`, so those are copied from `labels.js` as the plan allows.
Check output (node exercise, scratchpad `zebra-ex.mjs`):
```
$ node --check resources/js/shop/zebra.js   → OK
groups till 1 / other 1 / standalone 1 translated group present false
query hall → translated 1 of 1 shown 1
scan kale → Curly Kale 200g copies 2 events shop-scan-done
meta Kale Curly 300g · 112.6 × 75.1 mm · 000000000102 | pill {"tone":"warn","text":"Price differs"} | Label says €2.49, till says €2.79 | Check the printer has 113 × 75 mm labels loaded
overrides {"1":"\\152.79"}
useTillPrice=false {}
translated print ["/t/9/print",{"copies":1}] selected after null toast Print job sent (1 copy)
kale print ["/z/1/print",{"copies":2,"fields":{"1":"\\152.79"}}]
bump(-5) 1
bump(200) 99
Counter Zebra · 2 jobs waiting is-busy
Counter Zebra · printer not reachable is-off
Counter Zebra · checking printer… ""
no label → null shop-scan-error
rowMeta standalone Label only · 112.6 × 75.1 mm | translated Translated · 75 × 50 mm · 5201111222999 | pills {"tone":"muted","text":"No product"} {"tone":"ok","text":"Matches till"} {"tone":"muted","text":"Auto-print"}
$ grep -c "route(" resources/js/shop/zebra.js
0
```

### 8. CSS additions and the PIN allow-list — done
Changed: `resources/css/shop.css`, `config/shop.php`
The additions block is not nested, so each new selector is prefixed `.shop ` as the plan says (the `@media`
one too); `.shop-switch__label` replaced by the design's rule (also prefixed). One extra rule, see Deviations.
Seven allow-list entries added after the `labels.*` block with the one-line comment.
Check output:
```
$ head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo identical
identical
$ npm run build   → ✓ built in 4.22s
ConfinePinSessionTest: green (run above)
```

### 9. Tests — done
Changed: `tests/Feature/Shop/ShopLabelsTest.php` (extended), `tests/Feature/Shop/ShopZebraTest.php` (new; checked first, did not exist)
- `ShopLabelsTest`: `employee_can_open_the_labels_screen` now asserts both nav links (Shelf with
  `aria-current="page"`, Zebra without), `data-printed-url`, `data-requeue-url`, `data-restore-url=""`, and no
  `route('labels.zebra')`; `manager_sees_clear_queue` asserts the restore URL. New:
  `printed_today_groups_prints_in_five_minute_windows`, `printed_today_falls_back_to_yesterday`,
  `printed_today_is_empty_when_neither_day_has_prints` (extra), `requeue_puts_a_printed_product_back_on_the_queue`,
  `restore_batch_is_for_managers`, `printed_today_is_forbidden_for_baristas`,
  `undo_in_the_same_second_as_remove_puts_the_product_back` (extra; see Deviations 3).
- `ShopZebraTest`: fixture as the plan (kale on the till with `\152.49` vs till 2.79, `default_copies` 2;
  Rocket Mossfield off the till, matching; standalone "parsnip"; Halloumi translation `large`, auto-print).
  Tests: `employee_can_open_the_zebra_screen`, `manager_sees_cancel_all`, `barista_is_forbidden`,
  `list_groups_labels_reports_mismatches_and_includes_translations`,
  `list_takes_the_newest_translation_per_product` (extra: newest per code wins; a newest row with no ZPL is
  skipped, not replaced by an older one), `office_page_and_list_agree`,
  `print_with_till_price_overrides_this_print_only`, `print_without_override_sends_the_saved_label`,
  `translated_print_sends_the_translation_with_copies`, `office_shelf_page_still_renders`.
Check output:
```
$ php artisan test --filter="ShopLabelsTest|ShopZebraTest|ShopViewContractTest|ConfinePinSessionTest"
  Tests:    73 deprecated (632 assertions)        (0 failed; "deprecated" = PHP 8.5 PDO::MYSQL_ATTR_SSL_CA notice, every test)
```

### 10. Docs, format, build — done
Changed: `docs/design/shop-mode/README.md` (new bullet after the existing Print labels bullet; the file was
already dirty from the Planner), `docs/features/shop-mode.md` (new "Print labels: Shelf labels and Zebra labels"
subsection before "Orders: list and order review", i.e. after the Deliveries ones), `docs/shop_new/README.md`
("Screens today": "Print labels (Shelf labels and Zebra labels tabs)"; nothing else).
Check output:
```
$ ./vendor/bin/pint --test --dirty
  PASS ... 9 files
$ npm run build
  ✓ built in 3.86s
```
Pint removed the now-unused `use App\Models\Country;` from `LabelAreaController` (it moved to the service).

### Browser check (step 7 of Verification) — done, two bugs found and fixed
Playwright MCP, headless Chrome, sessions minted with `mint.php` for `katelyn` (id 3) and `test` (id 20).
Real input (Playwright clicks / fill / Enter) unless marked.
- Shelf, katelyn: nav Shelf current; queue 10 rows; tile nav gone; new empty text present. Faked a print
  (POST `labels.print-a4` for the first row, from page JS) → queue 9, Printed today `1 batch · 1 label`.
  Clicked header → batch `16:20` → **Back to queue** → queue 10, item shows "In queue", button hidden; both
  chevrons `is-open` (rotate 90°); batch row has its top border.
- Remove → Undo: **failed twice, fixed** (Deviations 1–3). After the fixes, real clicks: queue 5 → Remove
  (toast "Removed: Dapp Peanut sauce 280 gram", Undo visible) → Undo → queue 5 again, same product back.
- As `test`: Clear queue → toast "Cleared 5 products from labels queue" → Undo → all 5 back.
- 390 × 844 iframe: sticky bar at 747–844 (bottom of the screen), `scrollWidth` 390 (no sideways scroll).
  Screenshot `/tmp/claude-playwright/shelf-390.png`.
- Zebra, `test`: list 314 (till 3 / other 11 / standalone 3 / 297 translated); printer line settled to
  "Counter Zebra · printer queue empty" (dev's `127.0.0.1` answers `lpstat`, so not the "not reachable" the plan
  expected); Cancel all hidden because the count is 0. Search "zzzzqq" → "No labels match"; translated
  collapsed section hidden while searching; search "Organic" → `translated 95 of 297`. Tapped a translated row
  → card open, row `is-latest`, meta "Translated · 56 × 31 mm · 8721225230868", "56 × 31 mm labels loaded".
  One fewer ×3 → 1 (clamped); One more ×2 → 3, "Print 3 labels". Print → POST `{"copies":3}` → 500
  `lp: Error - The printer or class does not exist.` → toast "Print failed" (bad), card stays open.
- Scan field: typed `000000000102` + Enter → kale card ("Kale Curly 300g · 112.6 × 75.1 mm · 000000000102",
  "Matches till", copies 1 because dev `default_copies` is null). Typed `8712345678906` + Enter → scan field
  `is-error` "No Zebra label for this product", card closed.
- katelyn, 390 px iframe: no Cancel all button in the DOM; injected a price mismatch into the kale item from
  page JS (dev has no mismatching labels) → "Label says €2.49, till says €2.79", Price differs pill, switch On
  "Print with till price", both notices, `overrides()` `{"1":"\\152.79"}`. Screenshot
  `/tmp/claude-playwright/zebra-card-390.png`.
- Console: clean on both tabs apart from the browser's own "Failed to load resource: 500" line for the
  deliberate failed print.

Dev state: the check wrote `label_logs` ids 16669–16689 (one print, dismissals, requeues; users 3 and 20).
All 21 deleted; queue back to 10 (`new_product` 8, `price_update` 1, `requeue_label` 1), max id 16668 as before.
No print reached a printer.

## Deviations

1. **Undo button `x-show="!! toast?.undo"`, not `x-show="toast?.undo"`** (step 2). Alpine calls a function an
   expression returns, so the plan's binding ran the undo callback the moment the toast rendered (browser:
   "Could not undo" appeared 600 ms after Remove, no Undo button shown). `!!` makes it a boolean; a Blade
   comment beside it says why.
2. **Undo callbacks take the component as an argument** (step 3). `showToast(…, (c) => c.restore([row]))` and
   `(c) => c.restoreBatch(ids)`; `undo()` calls `callback(this)`. The plan's `() => this.restore([row])` closed
   over the Remove button's x-for scope; Remove deletes that row, so the scope has no `$root` and every URL
   getter threw (README rule 5's x-if trap applies to x-for rows too). The Undo button's own `this` is live.
   The node exercise could not catch 1 or 2; the browser did.
3. **`LabelQueueService::candidates()` breaks same-second ties by row id** (that method was not in the plan).
   `created_at` is to the second and the rule was `event <= last resolve → resolved`, so an Undo tapped within
   a second of Remove (Playwright did it in ~100 ms; a person correcting a mis-tap will too) posted a
   successful `requeue_label` that the queue then ignored — the product stayed off the queue. Now an event in
   the *same second* as the last print/dismissal counts as newer when its id is higher; strictly earlier or
   later timestamps behave exactly as before. The resolve query adds `MAX(id)` (still two queries;
   `test_queue_derivation_uses_a_fixed_number_of_queries` passes) and the event query adds
   `orderBy('id','desc')` so `first()` is deterministic on ties. The office queue changes only for exact-second
   ties, where the later row now wins (e.g. a price update logged in the same second as a print stays queued).
   New test `test_undo_in_the_same_second_as_remove_puts_the_product_back` (`freezeSecond()`); I removed the
   `travel(1)->seconds()` I had first put in the requeue / restore tests, so they exercise the tie too.
   **Planner: please accept or reject explicitly** — without it, quick Undo after Remove or Clear does nothing.
4. **`display: contents` wrappers in Printed today, and one extra CSS rule** (steps 2, 8). Alpine `x-if` and
   `x-for` templates need one root element, but the design's batch row and item rows are siblings. The `x-if`
   content and each batch get a `<div class="shop-contents">` (existing class); item rows carry
   `x-show="openBatch === b.key"`. Rows inside wrappers are no longer DOM siblings, so `.shop-row + .shop-row`
   stops drawing the separator; the additions block gets one more (commented) rule:
   `.shop .shop-list > .shop-contents .shop-row { border-top: 1px solid var(--shop-line); }`. Browser: batch row
   has its 1 px top border.
5. **Chevron class binding** is `x-bind:class` on `<x-shop.icon>` instead of a wrapping span: Blade evaluates
   `:class` on a component as PHP, `x-bind:` passes through to `$attributes`. Rotation lands on the svg.
6. `stockText` uses `Math.round` as planned, so 112.6 × 75.1 reads "113 × 75 mm" (the design sample showed
   "112 × 75 mm").
7. Translated list sorted by name case-insensitively. When a code's newest translation has no ZPL the code is
   skipped (newest first, then filter), as `ProductTranslation::latestPerProductCode()` requires.
8. `printedToday()` with nothing today reads `recentPrints(today()->subDay())`, which also spans today; correct
   only because today is empty at that point (commented).

## Verification

1. Routes:
   ```
   shop/labels/zebra    shop.zebra › Shop\ZebraController@index               ⇂ PermissionMiddleware:labels.print
   labels/zebra/list    labels.zebra-list › LabelAreaController@zebraList     ⇂ PermissionMiddleware:labels.print
   labels/printed-today labels.printed-today › LabelAreaController@printedToday ⇂ PermissionMiddleware:labels.print
   ```
2. `php artisan test tests/Feature/Shop` → `Tests: 352 deprecated (1871 assertions)`, 0 failed. Contract-test
   data sets: 29, against 27 view files at HEAD (the new screen **and** the new partial; partials are scanned).
3. `php artisan test` → `Tests: 1074 deprecated, 16 failed, 43 passed (5260 assertions)`. The 16:
   `UdeaScrapingServiceTest` ×7, `BackupConfigurationTest` ×1, `CashReconciliationTest` ×3,
   `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2, `TestScraperControllerTest` ×1 — the identical baseline set.
4. `git diff --stat` on `ZebraLabelController.php`, `LabelTranslationController.php`, `resources/views/labels/`
   → empty. `LabelAreaController` hunks: `Country` import removed (pint), `ZebraLabelListService` import,
   property and constructor parameter, `zebra()` else-branch delegation, `zebraList()`, `shelfLabels()`
   delegation, private `groupLabelPrintsByTime()` deleted, `printedToday()`. Office pages through the kernel at
   HEAD vs after steps 4–5: byte-identical (taken before Deviation 3, which only matters on same-second ties).
5. `grep -c "route("` → `labels.js:0`, `zebra.js:0`; design block `cmp` → identical;
   `cmp public/images/shop-icons.svg docs/design/shop-mode/shop-icons.svg` → identical.
6. `./vendor/bin/pint --test --dirty` → PASS (9 files); `npm run build` → built.
7. Browser on dev: done, see "Browser check" above.
8. Owner on production with the printer on: **not done here** (needs the shop printer).

## Files changed
`git status --short` at the end (pre-existing dirt marked *):
```
 M app/Http/Controllers/LabelAreaController.php
 M app/Http/Controllers/Shop/LabelsController.php
 M app/Services/LabelQueueService.php
 M config/shop.php
 M docs/deliveries/README.md                         *
 D docs/deliveries/implemented-glued-price.md        *
 D docs/deliveries/plan-glued-price.md               *
 M docs/design/shop-mode/README.md                   * (already modified; this cycle added one bullet)
 M docs/features/shop-mode.md
 M docs/shop_new/README.md                           * (already modified; this cycle changed one line)
 M resources/css/shop.css
 M resources/js/shop.js
 M resources/js/shop/labels.js
 M resources/views/shop/labels.blade.php
 M routes/web.php
 M tests/Feature/Shop/ShopLabelsTest.php
?? app/Http/Controllers/Shop/ZebraController.php
?? app/Services/ZebraLabelListService.php
?? docs/deliveries/archive/2026-10-20-glued-price/   *
?? docs/design/shop-mode/screen-07-shelf-labels.html *
?? docs/design/shop-mode/screen-07b-zebra-labels.html *
?? docs/shop_new/design-brief-labels.md             *
?? docs/shop_new/implemented.md
?? docs/shop_new/plan.md                            *
?? resources/js/shop/zebra.js
?? resources/views/shop/partials/labels-nav.blade.php
?? resources/views/shop/zebra.blade.php
?? tests/Feature/Shop/ShopZebraTest.php
```
Nothing committed. No migration.

## Notes for Planner

- **Deviation 3 needs a decision** (same-second tie-break in the shared queue derivation).
- **Two Alpine facts worth adding to `planimp.md` / README rule 5** (Deviations 1, 2): an expression that
  evaluates to a function is *called* by Alpine (`x-show="toast?.undo"`); and the "loses `$root`" trap also hits
  a closure created in an `x-for` row that its own action removes. A node exercise cannot see either.
- `.shop-switch__label` now uses the design's rule (small, ink colour). That also restyles the "Show not
  ordered" label on Order review (`shop-meta shop-switch__label`), which was muted. Not checked in a browser.
- Dev `127.0.0.1` answers `lpstat` (line says "printer queue empty") but `lp` fails with "The printer or class
  does not exist", so a dev print returns 500 "Print failed". README's "expect not reachable on dev" does not
  hold on this machine.
- Dev has no Zebra label with a price or country mismatch, so the mismatch card was checked with injected
  state; Verification 8 on production is the real check.
- Translated labels on dev: 297 listed from 301 rows (newest per code, minus newest-without-ZPL).
- A Printed today item for an unknown product printed once has `meta` `''`, so its meta line is blank.
- Rows put back by Back to queue / Undo show reason "Re-queued" (existing `queue()` wording; the design sample
  says "Back in queue"). Not changed.
