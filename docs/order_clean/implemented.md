# Shop order review (design screen 20) — implementation

Status: DONE
Plan revision: 2 (Revision 1 report below, Revision 2 steps 11–14 at the end)
Implementer: Opus
Date: 2026-10-06

## Baseline
HEAD: 36207bbf
Pre-existing dirty files (all from the deposit track / glennon parser / design refresh; none touch order code):
```
 M app/Http/Controllers/DeliveryController.php
 M app/Models/BarrelCode.php
 M app/Models/DeliveryItem.php
 M app/Services/DeliveryParsingService.php
 M app/Services/DeliveryService.php
 M docs/FEATURES_INDEX.md
 M docs/design/shop-mode/README.md
 M docs/design/shop-mode/shop-icons.svg
 M docs/design/shop-mode/shop.css
 M docs/features/barrel-deposit-tracking.md
 M docs/features/invoice-parser-integration.md
 M resources/views/barrel-codes/index.blade.php
 M resources/views/deliveries/index.blade.php
 M resources/views/deliveries/show.blade.php
 M routes/web.php                 (deposit routes, +11 lines, before this cycle)
 M scripts/invoice-parser/invoice_parser_laravel.py
 M scripts/invoice-parser/parsers/delivery_udea.py
 M scripts/invoice-parser/parsers/invoice_udea.py
?? app/Console/Commands/Deposits*.php (4)
?? app/Http/Controllers/DepositController.php
?? app/Http/Requests/StoreProductDepositRequest.php, UpdateProductDepositRequest.php
?? app/Models/DepositSighting.php, ProductDeposit.php
?? app/Services/Deposits/
?? app/Support/PosProductAttributes.php
?? database/migrations/2026_10_03_00000{1..4}_*.php
?? docs/deposit/
?? docs/design/shop-mode/screen-19-new-order.html, screen-20-order-review.html
?? docs/order_clean/
?? resources/pos/
?? resources/views/deposits/
?? scripts/invoice-parser/parsers/glennon.py, tests/fixtures/glennon/, tests/test_*.py (3)
?? tests/Concerns/CreatesDepositPosTables.php
?? tests/Feature/Deposit*.php, DeliveryDepositReconciliationTest.php, DeliveryImportBarrelCodeTest.php
?? tests/Unit/PosProductAttributesTest.php
```
`docs/FEATURES_INDEX.md` and `routes/web.php` were already dirty; this cycle adds to both.

## Steps

### 1. Design assets — done
Changed: `resources/css/shop.css` (design block replaced by the 608-line copy; APP ADDITIONS untouched), `public/images/shop-icons.svg` (copied from the design)
Check output:
```
css-ok
sprite-ok
2            # grep -c "APP ADDITIONS"
696 resources/css/shop.css
✓ built in 9.16s   (npm run build; only the usual chunk-size warning)
```

### 2. Permission `orders.review` — done
Changed: `database/migrations/2026_10_06_000001_add_orders_review_permission.php` (new), `database/seeders/RolesAndPermissionsSeeder.php` (definition + `$employeePermissions`), `tests/Feature/Shop/RolePermissionGrantsTest.php` (two tests: seeder grants it to employee/manager/admin, not barista, and employee still lacks `orders.manage`; migration on an existing database grants admin/manager/employee, is idempotent, `down()` removes it)
Check output:
```
  2026_10_06_000001_add_orders_review_permission ................ 39.43ms DONE
yes          # employee hasPermission("orders.review")
  Tests:    12 passed (88 assertions)   # RolePermissionGrantsTest
```

### 3. `OrderReviewService` — done
Changed: `app/Services/Shop/OrderReviewService.php` (new)
Check output (tinker, session 306):
```
{"id":306,"supplier":"Udea Frozen","delivery_date":"Tue 13 Oct","created_by":"Jonathan","status":"Draft","editable":true,"lasts_until":"Tue 10 Nov","weeks_after_delivery":4,"history_weeks":10,"total_value":300.02,"ordered_count":11,"item_count":30,"export_url":"http://osmanager.local/shop/orders/306/export"}
{"id":177697,"url":".../shop/orders/306/items/177697","name":"Natural Cool Pea-carrot-mix 450g","code":"4026813000194","group":"case","case_units":8,"unit_cost":1.56,"priority":"standard","tags":[],"stock":4,"suggested_cases":0,"final_cases":0,"total_cost":0,"weekly_sales":[1,1,0,0,0,0,0,0,0,0],"avg_weekly":0.2,"peak_weekly":1,"sold":2,"target_weeks":4.14}
```
`weeks_after_delivery` 4.0 is JSON-encoded as `4` (no `JSON_PRESERVE_ZERO_FRACTION`); the test compares with `assertEquals`.

### 4. Controller and routes — done
Changed: `app/Http/Controllers/Shop/OrderReviewController.php` (new), `routes/web.php` (the group after the F&V group)
Check output (`php artisan route:list --name=shop.orders -v`):
```
  GET|HEAD    shop/orders ..... shop.orders › Shop\OrderReviewController@index
  GET|HEAD    shop/orders/{order} shop.orders.review › ...@show
  GET|HEAD    shop/orders/{order}/export shop.orders.export › ...@export
  GET|HEAD    shop/orders/{order}/items shop.orders.items › ...@items
  PATCH       shop/orders/{order}/items/{item} shop.orders.item › ...@updateItem
  each: web, Authenticate, PermissionMiddleware:orders.review
                                                            Showing [5] routes
```
HTTP checks as katelyn / test / no-permission user: see step 8 (feature tests) and step 10 (browser).

### 5. Orders list view — done
Changed: `resources/views/shop/orders.blade.php` (new)
Rows as planned (lead icon `chart`, supplier, `Delivery D j M · first name`, `N products · €value`, pill, chevron). The "Draft orders" meta shows the total number of drafts, and "Showing the 20 most recent" appears when there are more; for that `listing()` also returns `draft_total` (see Deviations).
Check: rendering test `test_list_shows_drafts_and_recently_completed` (step 8) passes; `ShopViewContractTest` passes (step 6 output). Browser: step 10.

### 6. Review view, Alpine module, CSS additions — done
Changed: `resources/views/shop/order-review.blade.php` (new), `resources/js/shop/order-review.js` (new), `resources/js/shop.js` (import + `Alpine.data('shopOrderReview', orderReview)`), `resources/css/shop.css` (APP ADDITIONS only: the planned rules, plus nothing else)
Check output:
```
✓ built in 8.40s                     # npm run build
css-ok                               # design block still byte-identical
  Tests:    27 passed (303 assertions)   # ShopViewContractTest
grep -c 'style="' order-review.blade.php → 3   (all three are the planned Alpine `:style` bindings: two bar heights, the average line)
grep -c '[^:]style="' order-review.blade.php → 0
grep -c 'style="' orders.blade.php → 0
grep -n "@error\|@class" order-review.blade.php → (empty)
```
Browser: step 10.

### 7. Home tile — done
Changed: `config/shop.php` (Orders tile after `labels`), `tests/Feature/Shop/ShopHomeTest.php` (`test_orders_tile_follows_the_orders_review_permission`: a user holding `orders.review` sees the tile, its route and the `#chart` icon; one holding only `stocking.scan` does not)
Check output:
```
  Tests:    14 passed (48 assertions)    # ShopHomeTest
```

### 8. Tests — done
Changed: `tests/Feature/Shop/ShopOrderReviewTest.php` (new, 12 tests), `tests/Feature/Shop/RoutePermissionsTest.php` (matrix rows: employee `/shop/orders` open, barista 403, manager open)
Covers: 403 on list/review/items/export/PATCH without the permission, 200 with it (and the employee still lacks `orders.manage`); list with draft and "Recently completed"; items JSON header (`lasts_until` "Tue 10 Nov", `weeks_after_delivery` 4.0, `history_weeks` 8, `editable` true) and rows (P1 `case`, 6, `[]` tags, 8 weekly values, numbers not strings; P2 `unit`, `added`, `['Destocked','Kitchen']`); product gone from POS with old `context_data` (Unknown product, code = product_id, empty `weekly_sales`); PATCH case product 3 → final_quantity 18, total_cost 27, session total 39, `order_adjustments` row; PATCH unit product 2 → final_quantity 2; validation −1 / 'x' / 10000 / missing → 422; completed session → 409 `{"error":"Order is not editable"}` and nothing changed; item from another session → 404; completed header `editable` false; export headers, filename `order_Sonett_2026-10-13.csv`, body starts `Code,Cases,Units,`.
Check output:
```
php artisan test tests/Feature/Shop
  Tests:    313 passed (1574 assertions)
  Duration: 26.70s
```
One fix during writing (and a rename: a helper called `session()` clashes with `TestCase::session()`, so it is `orderSession()`): a first assertion `assertIsFloat($p1['final_cases'])` failed because JSON decodes `2.0` as int 2; replaced by `assertIsNotString` + `assertIsNumeric`, which still catches the decimal-string risk.

### 9. Docs and formatting — done
Changed: `docs/features/shop-mode.md` (new subsection "Orders: list and order review" before "Shared devices…", and Order Generation added to "Screens with their own docs"), `docs/features/order-management/order-generation.md` ("2026-10 Shop mode review" entry before "4. Learning System", one Operational Notes line), `docs/FEATURES_INDEX.md` (a "Shop Order Review" bullet under Order Generation System). `config/shop.php` docblock not touched.
Check output:
```
grep -n "orders.review" docs/features/shop-mode.md
149:A Home tile **Orders** (permission `orders.review`) opens `/shop/orders`: the
174:- **`orders.review`** is held by employee, manager and admin (migration
./vendor/bin/pint --test <10 changed PHP files>
    PASS   .......................................................... 10 files
```

### 10. Browser walkthrough — done
The owner signed in as `katelyn` (id 3, employee) in the automation Chrome. Before any change, session 306 (all items, totals, the highest `order_adjustments` id 9762) and session 63 were snapshotted with tinker. "Real" below means a real mouse click; "state" means Alpine state was read or set through `Alpine.$data`.

- **List** `/shop/orders`: 290 drafts in the count. The newest is first: "Udea Frozen · Delivery Tue 13 Oct · Jonathan · 30 products · €300.02", Draft pill. Opening it with a real click went to `/shop/orders/306`.
- **Review 306**: the header reads "Order · Udea Frozen / Delivery Tue 13 Oct · Draft by Jonathan". The facts are €300.02 · 11 of 30 · Tue 10 Nov, 4 weeks after delivery · 10 weeks. The filter shows "All 11" because not-ordered rows are hidden by default. Charts, the average line, projection bars, stock and cover pills all render.
- **+ on a case row (real)**: B Muffin Apple & Cinnamon went 3 → 4 cases, with the orange ring, "Suggested 3" reset button, €36.48, and Order value and Total both €309.14. In the DB: final_cases 4 / quantity 32 / cost 36.48, session 309.14, and `order_adjustments` 9763 (24→32, user 3).
- **Reset (real)**: `PATCH /shop/orders/306/items/177715` → 200. Back to 3 cases, €27.36, "Suggested", €300.02.
- **Unit row, session 63 (Dunany Flour, 4 single-unit items)**: renders as "3 UNITS", "€3.40 each · €10.20". Two quick real taps on − sent **one** `PATCH /shop/orders/63/items/45400` → 200 (the debounce held). The row shows 1 unit, €3.40, the "Lasts 2 wk · below target" pill, and the total is €24.10. In the DB: 1 / 1 / 3.40, session 24.10, adjustment 9764 (3→1). Reset (real) → a second PATCH 200, and state is back to 3/3 on every row and €30.90.
- **Switch, filters, sorts, search (state)**: the "Show not ordered" switch was a real click. With it on, 30 articles show, filters "All 30, Review 0, Standard 27, Safe 3, Added 0", and each filter gives 0/27/3/0/30 articles in the DOM. Sort Lowest cover puts the negative-cover muffins first. A–Z gives "B Croissant, B Muffin Apple…". Best sellers gives sold 46, 18, 8, 8. Search "muffin" gives 2 rows; a nonsense search gives 0 groups and the "Nothing matches" card is shown (`display:flex`). The Total in the bar equals the Order value fact (€300.02).
- **Export (fetched in page JS rather than downloaded)**: `/shop/orders/306/export` → 200, header `Code,Cases,Units,Content,Description,Price,Sale,Total`, 11 lines of products. `/shop/orders/63/export` → 200, 3 lines. The extension masks response headers, so the filename and content type are covered by `test_export_returns_the_office_csv`.
- **Completed session 20** (Udea, 1,461 items): the subtitle is "Delivery Tue 28 Oct · Completed by Jonathan", `order.editable` is false, all 2,922 stepper buttons are disabled, no reset button is visible, and a click on + changes nothing. A direct PATCH `{cases: 99}` → **409** `{"error":"Order is not editable"}`. The export link is present.
- **Phone width** (same-origin iframe 390 × 844 on 306): `.shop-ord` is one column (311 px), the page `scrollWidth` is 375 so there is no horizontal page scroll, the stepper spans 32–343 px, and the sticky bar sits at 747–844, at the bottom of the viewport.
- **Console**: no errors (`read_console_messages`, errors only, on session 63 after the PATCHes).

Not caused by this tab: `order_adjustments` 9765 (another 24→32 on the same 306 product, user 3, 11:02:42) and a reset at 11:03:01 happened while this tab was on session 63. Another client signed in as katelyn was using 306 at the same time, probably the owner's own window. The item ended back at 3 cases.

One mishap: a stray `document.body.innerHTML += ''` in a check script re-parsed the 1,461-row page of session 20 and froze the tab for one call. It is browser-only; no data was involved. The phone check was then run from the light Orders list page.

**Dev state** (put back):
- Session 306: all 30 items match the pre-check snapshot (0 mismatches), total €300.02.
- Session 63: items 45400–45403 at 3/3/0/3, total €30.90.
- `updated_at` on items 177715 and 45400 and on both sessions is now 2026-10-06. `adjustment_reason` was null before and is null now.
- `order_adjustments` 9763 and 9764 (created by this check) were deleted. 9765 is left because it was not this tab's.
- Session 20 is unchanged (the PATCH was refused).

## Deviations
1. `OrderReviewService::listing()` also returns `draft_total` (count of all drafts). The view needs it for "Showing the 20 most recent" and for the count beside "Draft orders" (the plan asks for both). No other effect.
2. The "Lasts until" and "Based on" facts are rendered server-side from `$header` instead of Alpine bindings on `order.*`. They never change while the page is open (a stepper does not move them), so the result is the same. Order value and Products ordered are Alpine bindings, as planned, with the server value as the initial text.
3. The "−" button is also disabled at 0 (`it.final_cases <= 0`); the plan only clamps at 0. A tap at 0 would otherwise send a PATCH that changes nothing.
4. The Total block in the sticky bar carries `shop-stack` as well as `shop-ord-total` (`shop-actions__spacer shop-stack shop-ord-total`). The planned `.shop-ord-total` rule sets `gap: 0` but no `display: flex; flex-direction: column`, so on its own the label and value would sit side by side; `shop-stack` is what the design uses there. The export link gets `shop-btn--export` so the planned `.shop-actions > .shop-btn--export` rule applies.
5. Verification 8 (`grep -c 'style="'` → 0) reports 3 for `order-review.blade.php`. All three are the Alpine `:style` bindings the plan's Constraint 1 and step 6 call for (two bar heights, the average line). `grep -c '[^:]style="'` is 0 for both views, and `orders.blade.php` is 0 either way.
6. Step 2's tests are two new tests (`test_seeder_grants_orders_review_to_employees_but_not_orders_manage`, `test_orders_review_migration_grants_it_on_an_existing_database`). The migration test first deletes `orders.review`, because RefreshDatabase has already run the migration with no roles present. This is the same approach as the file's existing `existingDatabase()` helper.

## Verification
1. css-ok
2. sprite-ok
3. `npm run build` → `✓ built in 8.14s` (only the usual chunk-size warning)
4. `php artisan route:list --name=shop.orders` → 5 routes, each `PermissionMiddleware:orders.review` (the full listing is in step 4)
5. `php artisan test tests/Feature/Shop` → 313 passed (1574 assertions)
6. `php artisan test` → **15 failed, 1019 passed** (67.4 s). These are the same five classes as the baseline: UdeaScrapingServiceTest ×7, CashReconciliationTest ×3, FruitVegLabelPrintingTest ×2, ProductTest ×2, TestScraperControllerTest ×1. There are 20 more passing tests: 12 ShopOrderReviewTest, 2 RolePermissionGrantsTest, 1 ShopHomeTest, 3 matrix rows, plus 2 that come from elsewhere (the baseline was 999 and other tracks are adding tests).
7. `./vendor/bin/pint --test` on the 10 changed PHP files → PASS
8. `grep -c 'style="'` → orders 0, order-review 3 (all `:style` bindings, see Deviation 5)
9. Browser walkthrough → done, see step 10 (PATCH 200s, one PATCH for two quick taps, 409 on completed, no console errors, phone layout OK)

## Files changed
This cycle:
```
 M config/shop.php
 M database/seeders/RolesAndPermissionsSeeder.php
 M docs/FEATURES_INDEX.md                      (already dirty before; added one bullet)
 M docs/features/order-management/order-generation.md
 M docs/features/shop-mode.md
 M public/images/shop-icons.svg
 M resources/css/shop.css
 M resources/js/shop.js
 M routes/web.php                              (already dirty with deposit routes; added the shop.orders group)
 M tests/Feature/Shop/RolePermissionGrantsTest.php
 M tests/Feature/Shop/RoutePermissionsTest.php
 M tests/Feature/Shop/ShopHomeTest.php
?? app/Http/Controllers/Shop/OrderReviewController.php
?? app/Services/Shop/OrderReviewService.php
?? database/migrations/2026_10_06_000001_add_orders_review_permission.php
?? resources/js/shop/order-review.js
?? resources/views/shop/order-review.blade.php
?? resources/views/shop/orders.blade.php
?? tests/Feature/Shop/ShopOrderReviewTest.php
?? docs/order_clean/implemented.md
```
Not this cycle: `resources/views/layouts/admin.blade.php` (+16 lines, modified 10:46 today). It was not in the baseline and this session did not touch it, so another session changed it while this one was running. Everything else in `git status` is the pre-existing baseline listed above.
Dev DB: migration `2026_10_06_000001_add_orders_review_permission` ran (batch 99).

## Notes for Planner
- **The office CSV export crashes when a product has gone from the POS.** `OrderService::exportToCsv()` reads `$product->supplierLinks` without a null check, so an order holding such an item fails to export, from both the office and the Shop. This cycle did not touch it (office code, out of scope). The Shop items JSON handles a missing product.
- **Tag pills hide their dot.** The plan's `.shop-pill--plain::before { display: none }` removes the pill dot from the tag pills. The design's inline `padding-left:12px` is the same as the pill's default padding, so the design shows the grey dot. Implemented as planned; if the dot should match the design, drop that one rule.
- `weeks_after_delivery` comes through JSON as `4`, not `4.0` (no `JSON_PRESERVE_ZERO_FRACTION`). The view formats it server-side, so nothing visible changes.
- Rapid taps: while a save is in flight the row's buttons are disabled (`it.busy`), so a tap cannot race a reply. On success, the server's `final_cases` is copied only when no newer tap is waiting and the value on screen is still the one that was sent.
- The Orders list's first-name logic (`strtok`) is repeated in the Blade view and in `OrderReviewService::firstName()`. It is small, but it could become a `first_name` accessor on `User` in a later cycle.
- **Large sessions (from step 10)**: completed session 20 has 1,461 items. Its items JSON took 543 ms. With every row rendered, changing the sort took about 2.5 s and a search keystroke about 1.2 s (Alpine re-render). Several drafts in the list have 1,000+ products. This is the plan's "Large sessions" risk, confirmed: a later cycle should window or paginate the list. Session 306 (30 items) is instant.
- **Phone width (from step 10)**: the five filters do not fit in 343 px. The design's `.shop-seg` scrolls sideways (`overflow-x: auto`, no scrollbar), so Safe and Added are reachable but not visible. The sort select (186 px) also squeezes the search box to 145 px ("Find ir…"). Both follow the design at that width; a later cycle could stack the tools row or shorten the labels on phones.
- **Negative snapshot stock**: 306's muffins have `current_stock` −39 / −27 (the POS stock was negative at generation time). They show in red as "-39", with after-delivery "-15" and "Runs out within a week", and sort first under Lowest cover. That is faithful to the snapshot, but the owner may want negative stock shown as 0 or flagged.

---

# Revision 2 — steps 11 to 14

Baseline for Revision 2: the Revision 1 working tree above, unchanged since that report (HEAD still `36207bbf`). The Planner's review deleted `order_adjustments` 9765.

### 11. Window the rows — done (target partly met, see the numbers)
Changed: `resources/js/shop/order-review.js`, `resources/views/shop/order-review.blade.php`, `resources/css/shop.css` (APP ADDITIONS)
- `PAGE = 50`. The `windows: { case, unit }` state, `shown(g)` (= `g.items.slice(0, windows[g.key])`), `remaining(g)`, `moreLabel(g)` ("Show N more · M left"), `more(g)` and `resetWindows()` are new. The template iterates `shown(g)`. Under each `.shop-list` sits `<button class="shop-btn shop-btn--secondary shop-btn--block" type="button" x-show="remaining(g) > 0">`. The group title, the filter counts, the totals and the empty state still read the full `groups`.
- **Reset mechanism: watchers.** `init()` calls `this.$watch(key, () => this.resetWindows())` for `filter`, `showUnordered`, `sort` and `q`.
- **Two further changes**, made because windowing alone did not reach the target (see Deviations 7 and 8). The chart bars are rendered from one `x-html` per side (`sparkPast(it)` / `sparkFuture(it)`, which output the same spans, classes, heights and titles), so directives per row went from 78 to 35. And `.shop-ord { content-visibility: auto; contain-intrinsic-size: auto 160px; }` was added in APP ADDITIONS, which brought layout from about 230 ms to about 10 ms for 100 rows.

**Measuring.** The automation tab reports `document.hidden: true` throughout, and a screenshot does not change that. In a hidden tab Chrome throttles timers to about 1 s, and both `Alpine.nextTick` and `requestAnimationFrame` depend on those. My first scripts used `requestAnimationFrame`, as the plan suggests, and they hung for 45 s. The Shop README warns about exactly this. The numbers below therefore time the change, then five microtask turns (Alpine flushes its effects in microtasks), then a forced layout (`document.body.offsetHeight`). The renderer of a background tab is also given lower CPU priority, so a visible tab should be faster than this.

Session 20 (Udea, 1,461 items, 329 ordered, 14 history weeks), with the JSON already in hand:
```
                                windowing only   + x-html bars + content-visibility
first render (100 rows)         —                564 ms
show not ordered (100 rows)     750 ms*          278 ms
sort A–Z                        939 ms*          423 ms
sort lowest cover               1,062 ms*        655 ms
sort best sellers               —                327 ms
search "bio"                    464 ms*          300 ms
clear search                    —                523 ms
show 50 more (+50 rows)         573 ms*          252 ms
filter change (resets window)   148 ms*           87 ms
* includes about 230 ms of layout and some timer throttling; the second column does not.
items JSON: 508 ms (Revision 1 review: 585 ms). Planner's Revision 1 numbers: show not ordered 18 s, re-sort 0.7 s.
```
Of the per-step cost, the helpers take 9 ms for 100 rows, the `groups` getter 7–43 ms (cover sort is the dearest) and `filters` 5 ms. The rest is Alpine building or moving row DOM, about 5 ms per new row in this background renderer.

**Against the plan's targets**: "first render well under 1 s" is met (564 ms). "Show not ordered and a re-sort each under 300 ms" is met for Show not ordered (278 ms) but not for every sort: best sellers 327 ms, A–Z 423 ms, lowest cover 655 ms. A re-sort swaps in up to 100 new rows (50 per group). See Notes for options.

Behaviour (session 20):
- **On load**: 100 rows, buttons "Show 50 more · 131 left" and "Show 50 more · 98 left", titles "Case products 181" and "Single units 148" (full counts).
- **Show not ordered**: still 100 rows; buttons "… 1068 left" and "… 293 left".
- **Show 50 more** (button `.click()` in page JS): 150 rows, "Show 50 more · 1018 left".
- **Filter change**: windows back to `{"case":50,"unit":50}`.
- **Search "bio"** (not-ordered hidden): 9 + 6 rows, `remaining` 0 for both groups, and both buttons `display: none` once x-show had applied. In the hidden tab that takes a throttled timer: the first reading said `flex`, and after 2.5 s it said `none`.
- **Clear search**: buttons "131 left" and "98 left" again.
- **Session 306** (30 items, not-ordered shown): 30 rows and the one button `display: none`.
- **Charts after the x-html change**: session 306's first row has 10 past bars, one `is-peak`, the avg line, and 4 `is-proj` bars with heights and titles ("Delivery: about 65 in stock" … "Week 3: about 51.2 in stock"). A zoomed screenshot looks the same as Revision 1.

### 12. Tag pills keep their dot — done
Changed: `resources/css/shop.css` (both `.shop-pill--plain` rules removed), `resources/views/shop/order-review.blade.php` (the tag pill is now `shop-pill shop-pill--muted`)
Check output:
```
grep -c "shop-pill--plain" → resources/css/shop.css:0, resources/views/shop/order-review.blade.php:0
css-ok
```
In the browser, the tag pill class on session 20 is `shop-pill shop-pill--muted`.

### 13. Read-only rows still say what was suggested — done
Changed: `resources/views/shop/order-review.blade.php` (the trailing span is exactly the plan's: `x-show="! (isChanged(it) && order.editable)"`, with `x-text` choosing "Suggested N" or "Suggested")
Check: `ShopViewContractTest` 27 passed. In the browser on session 20 (read-only), no row differed from its suggestion, so as the plan allows I set the first row's `final_cases` from 4 to 6 in component state. The line then read `36 units · €61.56 [block] | Suggested 4 (reset button) [none] | Suggested 4 [block]`. After restoring it: `24 units · €41.04 | … [none] | Suggested [block]`. Component state only; nothing was saved (the order is read-only).

### 14. Export survives a product gone from the POS — done
Changed: `app/Services/OrderService.php` (`exportToCsv()` only: `if (! $product) { continue; }` with a one-line comment), `tests/Feature/Shop/ShopOrderReviewTest.php` (`test_export_skips_an_ordered_product_gone_from_the_pos`: a draft with ordered P1 and an ordered `GONE` item exports 200, the body contains `SON-1` and not `GONE`)
Check output:
```
php artisan test --filter=ShopOrderReviewTest → Tests: 13 passed (82 assertions)
Without the guard (temporarily removed, then restored from a copy): ErrorException: Attempt to read property "supplierLinks" on null … OrderService.php:1330
With it: Tests: 1 passed (3 assertions)
pint --test app/Services/OrderService.php → PASS
git diff app/Services/OrderService.php → +4 lines (comment, if, continue, brace), nothing else
```

## Deviations (Revision 2)
7. **Chart bars via `x-html`** instead of step 6's per-bar `<template x-for>` with `:class` / `:style` / `:title`. Windowing alone left 78 directives per row, and a re-sort or "Show not ordered" took 0.75–1 s. `sparkPast()` and `sparkFuture()` build the same spans from numbers and fixed class names only (no user text goes in, so nothing needs escaping), and the view now has no `style=` at all (`grep -c 'style="'` → 0). The Blade comment above the two divs says why. If the Planner prefers the template form, revert those two divs and the two helpers; windowing still works without them.
8. **`content-visibility: auto` on `.shop-ord`** (APP ADDITIONS, step 11's "if anything is needed"). Layout of 100 rows dropped from about 230 ms to about 10 ms. The only visible effect is that off-screen rows are laid out when they scroll in; `contain-intrinsic-size: auto 160px` keeps the scrollbar close.
9. Timing method: the plan's "two `requestAnimationFrame`s" hang in the hidden automation tab, so microtask turns plus a forced layout were used instead (see step 11).

## Verification (Revision 2)
1. css-ok
2. sprite-ok
3. `npm run build` → `✓ built in 8.06s`
4. `php artisan route:list --name=shop.orders` → 5 routes
5. `php artisan test tests/Feature/Shop` → **314 passed** (1560 assertions)
6. `php artisan test` → **15 failed, 1020 passed**, the same five classes as the baseline (UdeaScrapingServiceTest, CashReconciliationTest, FruitVegLabelPrintingTest, ProductTest, TestScraperControllerTest)
7. `./vendor/bin/pint --test` on the 11 changed PHP files (now including `OrderService.php`) → PASS
8. `grep -c '[^:]style="'` → 0 for both views (and plain `grep -c 'style="'` is now 0 for both too)
9. Browser → step 11 and step 13 above

## Files changed (Revision 2)
On top of the Revision 1 list: `app/Services/OrderService.php` (new in the list); further edits to `resources/js/shop/order-review.js`, `resources/views/shop/order-review.blade.php`, `resources/css/shop.css` and `tests/Feature/Shop/ShopOrderReviewTest.php`. `resources/views/layouts/admin.blade.php` is still modified by someone else.

**Dev state (Revision 2)**: nothing written. Step 13's change was component state on a read-only order and was restored. No PATCH was sent.

**Not this session**: between 11:37:09 and 11:37:30, user 5 (Jonathan, admin) tapped item 177715 on session 306 up to **13 cases** (`order_adjustments` 9766–9773), so the session total is €391.22. I left it alone because it is not my data. If it was a test, it can go back to 3 cases and those eight rows can be deleted.

## Notes for Planner (Revision 2)
- **Sorts above 300 ms** (327–655 ms in a background tab, A–Z and lowest cover worst). The cost is Alpine building up to 100 new rows of 35 directives each. Options, cheapest first:
  - (a) `PAGE` 25: about half the time, and one constant to change.
  - (b) One window across both groups instead of 50 each.
  - (c) Measure on the tablet before deciding: a foreground tab is not deprioritised, and this machine's numbers include background-renderer slowdown.

  I would do (c) and then (a) if needed.
- Alpine's `x-show` and `requestAnimationFrame` both wait on throttled timers in a hidden tab. Any future timing check from the automation browser should use microtasks plus a forced layout (step 11 explains how), not rAF. It may be worth adding that line to `docs/shop_new/README.md` under "Checking a change".
- A completed Udea order (session 20) shows "36 units · €61.56" for 6 cases of 6. That is ordinary maths and was mentioned only because it came up while checking step 13.
