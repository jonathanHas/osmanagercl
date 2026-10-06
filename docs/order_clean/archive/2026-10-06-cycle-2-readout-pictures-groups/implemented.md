# Shop order review: week readout, product pictures, chilled groups — implementation

Status: DONE
Plan revision: 2
Implementer: Opus
Date: 2026-10-06

## Baseline
HEAD: e8d79571
Pre-existing dirty files (all docs, from the Planner/owner):
```
 M docs/order_clean/README.md
 D docs/order_clean/implemented.md      (cycle 1 report, archived)
 M docs/order_clean/plan.md
 M docs/shop_new/README.md
?? docs/order_clean/archive/
```

## Steps

### 1. Week labels in the row data — done
Changed: `app/Services/Shop/OrderReviewService.php` (`row()` builds `weekly_labels` from the same week list as `weekly_sales`, with a private static `weekLabel()`: `Carbon::parse(week_start)->format('j M')`, falling back to `label` on a missing key or an unparseable date, and `''` for a bare-number week), `tests/Feature/Shop/ShopOrderReviewTest.php` (fixture weeks are real Mondays from 3 Aug; P1 labels asserted; "product gone" asserts `[]`; new `test_week_labels_fall_back_to_the_stored_label_and_keep_their_length`)
The new test has two weeks rather than the plan's one: `{week_start: 'not-a-date', label: 'W1'}` and a bare number `5`. That covers both fallbacks: labels `['W1', '']`, sales `[2, 5]`, same length.
Check output:
```
php artisan test --filter=ShopOrderReviewTest → Tests: 14 passed (87 assertions)
tinker 306 row 0: ["3 Aug","10 Aug","17 Aug"] 10/10   (labels / sales length)
```

### 2. Readout state and handlers in the module — done
Changed: `resources/js/shop/order-review.js`
`load()` adds `hot: null, pinned: false`. Past bars carry `data-w`, projection bars `data-p`; nothing else in the strings changed. New section `--- week readout`: `tipFor(bar)`, `tipText(it)`, `hover(it, e)`, `unhover(it, plot)`, `pinBar(it, e)` (a second tap on the pinned bar, recognised by its `is-hot` mark, clears it), `unpinBar(it, plot)`, and a shared `setHot(it, plot, bar)` that sets `it.hot` and moves the `is-hot` mark imperatively. `hover` and `pinBar` take the plot from `e.currentTarget`; nothing stores an element on the item.
Check: `npm run build` → `✓ built in 7.79s`. The view adds no `x-for` and no per-bar binding.

### 3. The readout in the view — done
Changed: `resources/views/shop/order-review.blade.php` (the four handlers on `.shop-spark__plot`, and the `.shop-spark__tip` span after the future div exactly as planned, with `it.hot?.x` and the `it.hot ?` guard)
The handlers are written `x-on:pointerover` / `x-on:pointerleave` / `x-on:pointerdown` / `x-on:click.outside` rather than `@…`. They behave the same, and the long form keeps clear of Blade directives (Shop rule 5).
Check output:
```
ShopViewContractTest → Tests: 27 passed (286 assertions)
grep -c '[^:]style="' order-review.blade.php → 0
```

### 4. Readout styling — done
Changed: `resources/css/shop.css` (APP ADDITIONS: the three planned rules under a comment naming this cycle)
Check: `css-ok`; build clean. Phone clamp: decided in step 11.

### 5. Picture URLs in the row data — done
Changed: `app/Services/Shop/OrderReviewService.php` (`ProductImageUrls` injected; `rows()` calls `byCode()` once with every item's code and passes the map to `row()`, which gains an optional `array $imageUrls = []` and an `image_url` key; `update()` calls `byCode([$code])` for its one row), `tests/Feature/Shop/ShopOrderReviewTest.php` (P1 gets an `IMAGE` stand-in blob and P2 `IMAGE` null; P1's `image_url` starts with the `shop.product-photo` route, P2's is null, the "product gone" row's is null, and the PATCH response row carries P1's photo URL)
No supplier-cache setup was needed: with no supplier picture rules for `S1`, the lookup returns null.
Check output:
```
php artisan test --filter=ShopOrderReviewTest → Tests: 14 passed (91 assertions)
tinker 306: photo 1 cdn 29 null 0   (http://osmanager.local/shop/products/4121/photo?v=584ff186)
grep byCode OrderReviewService.php → line 86 (rows, once) and line 159 (update, one code)
rows() on session 20 (1,461 rows), tinker wall time — this machine is noisy:
  before: 781, 969 ms
  after:  894, 800, 1679; then 5 runs 682, 690, 931, 1473, 2307 (median 931)
  byCode() alone, 1,461 codes: 482 ms, 1,454 URLs (Planner measured 164 ms)
```
The median moved by less than the 200 ms allowance, but the spread is wider than the effect. Step 11 records the JSON time in the browser.

### 6. A shared picture-peek part — done
Changed: `resources/js/shop/product-peek.js` (new), `resources/js/shop/find-product.js`, `resources/js/shop/order-review.js`
`product-peek.js` holds the four `PEEK_*` constants, `peek`, `peekPinned`, `onPeekScroll`, `canHover`, `watchPeekScroll()` / `unwatchPeekScroll()`, `peekAt(p, el)`, `pinPeek(p, el)` (touch only; a second tap on the same product, compared with `key()`, clears it), `maybeUnpin(e)`, `unpeek()`, `unpinPeek()`, and a shared `placePeek(p, el)` (the old `peekAt` body, unchanged, with its `hasImage` guard). It defines no `init` or `destroy`.
`find-product.js` composes `mix(base, productPeek(), {…})`, drops its constants, `peek`, `onScroll`, `canHover`, `peekAt` and `unpeek`, and its `init()` / `destroy()` call `watchPeekScroll()` / `unwatchPeekScroll()`. Its `imageFailed()` still calls `unpeek()` (now the part's).
`order-review.js` composes `mix(productImages(), productPeek(), {…})`; `init()` calls `watchPeekScroll()`, and a new `destroy()` calls `unwatchPeekScroll()`.
Two small differences from the plan's sketch (Deviation 3): the scroll listener calls `unpinPeek()`, not `unpeek()`, because a pinned panel would be just as stranded by a scroll and Find product never pins, so nothing changes there; and `peekAt()` also returns while a panel is pinned.
Check output:
```
npm run build → ✓ built in 10.75s
php artisan test tests/Feature/Shop/ShopFindProductTest.php → Tests: 12 passed (49 assertions)
grep -c "PEEK_W" resources/js/shop/find-product.js → 0
```
Browser: step 11.

### 7. The thumbnail and the panel in the review view — done
Changed: `resources/views/shop/order-review.blade.php` (`shop-ord__product shop-ord__product--pic` with `<x-shop.product-thumb expr="it" …mouseenter / mouseleave / click… />` and a `shop-ord__text` wrapper around the unchanged title, meta and tags; the panel copied from Find product with `'is-pinned': peekPinned`, as the last child of `<main>` after the toasts; `x-on:click.window="maybeUnpin($event)"` on `<main>`), `resources/css/shop.css` (APP ADDITIONS: the three planned rules)
Check output:
```
css-ok; build clean
ShopViewContractTest → Tests: 27 passed (292 assertions)
grep -c '[^:]style="' → 0
```
Browser: step 11.

### 8. Category and group definitions in the data — done
Changed: `app/Support/SpecialOrderCategories.php` (new static `displayGroups()`: the union of every supplier's groups, keyed, in first-seen order, with codes merged and de-duplicated; docblock as planned; nothing else in the class changed), `app/Services/Shop/OrderReviewService.php` (`header()['groups']` from a private `displayGroups()` that adds case and unit last; `row()['category']`), `tests/Feature/Shop/ShopOrderReviewTest.php` (fixture `P3`, Glenisk yogurt, `CATEGORY` `002`; all fixture products now carry `CATEGORY`; new `test_items_json_carries_the_display_groups_and_each_rows_category`; P1's `category` is null), `tests/Unit/SpecialOrderCategoriesTest.php` (`test_display_groups_are_the_union_in_first_seen_order`)
P3's item goes in its own draft, not the shared fixture draft, so the cycle-1 assertions on item counts and totals stay as they were.
Check output:
```
php artisan test --filter="ShopOrderReviewTest|SpecialOrderCategoriesTest" → Tests: 18 passed (107 assertions)
```

### 9. Grouping in the module and the view — done
Changed: `resources/js/shop/order-review.js`
- `groupKey(it)`: the first `order.groups` entry with non-empty `codes` containing `it.category`, else `it.group`.
- `groups` builds from `order.groups` in order. It falls back to a `DEFAULT_GROUPS` constant (case, unit) only until the order header has loaded, buckets the sorted list in one pass, and drops empty groups.
- `windows` starts as `{}`. `shown()` and `remaining()` read `?? PAGE`, `more()` reassigns `{ ...windows, [key]: … }`, and `resetWindows()` sets `{}`.
- The view's `x-for="g in groups" :key="g.key"` is unchanged and reads `g.title`.
Check: in step 11.

### 10. Docs and formatting — done
Changed: `docs/features/shop-mode.md` (three bullets in "Orders: list and order review": week readout, pictures, chilled groups first), `docs/features/order-management/order-generation.md` (one sentence in the "2026-10 Shop mode review" entry on how the Shop grouping differs from the office's per-supplier rule)
Check output:
```
grep -n "Week of\|displayGroups" docs/features/shop-mode.md → 178 (Week of), 188 (displayGroups)
pint (service, support class, both tests) → PASS, no changes needed
```

### 11. Browser check — done
As `katelyn`, in a fresh automation tab. The tab reports `document.hidden: true` throughout, so timings use microtask turns plus a forced layout, and state is read from the components. The viewport is 2,560 px wide; screenshot coordinates are 0.6×. "Real" means a real hover or click from the computer tool.

**Readout (session 306, first row B Muffin Apple & Cinnamon)**
- Real hover on the first past bar: `hot {kind:'w', i:0, x:6.5}`, tip "Week of 3 Aug · 25 sold" (`weekly_sales[0]` 25, label "3 Aug"), `is-hot` on bar 0 only.
- Real hover on the last past bar (5 Oct, 0 sold): first `hot` was **null**. The bar of a zero week is 3 px tall (rect 574–577), so the pointer is never on it. Fixed by `barAt()` (Deviation 1). After the fix: "Week of 5 Oct · 0 sold", `is-hot` on bar 9.
- Real hover on the first projection bar: "Delivery: about 65 in stock", `is-hot` on p0.
- Real hover off the plot: `hot` null, no `is-hot` left.
- Real click on the second bar: `pinned` true, "Week of 10 Aug · 21 sold". Real hover away: still pinned and shown. Real click on empty page: `hot` null, `pinned` false, no `is-hot`.

**Pictures (306, with not-ordered rows shown)**
- 30 rows, 30 thumbnails visible, 0 placeholders, `failed` empty. The till-photo row is B Croissant (`/shop/products/4121/photo?v=584ff186`); the other 29 are CDN pictures.
- Real hover on the croissant thumbnail: `peek` = B Croissant at x 789 / y 651, the panel `shop-peek is-open`, `display: block`, its img src the photo URL. A screenshot shows the large croissant beside the row. Real hover away: `peek` null.
- Touch (`window.matchMedia` stubbed so `(hover: hover)` is false, so `canHover` is false): a real click on the croissant thumbnail gives `peek` B Croissant, `peekPinned` true, the panel `shop-peek is-open is-pinned`, `display: block`. A real click on empty page: `peek` null, `peekPinned` false, panel `shop-peek`. Two `img.click()`s in a row: pinned true, then false (second tap toggles). `matchMedia` restored afterwards (`canHover` true).
- `/shop/find`, search "croissant": real hover on the result thumbnail: `peek` B Croissant, `shop-peek is-open`, `display: block`. Real hover away: null. No console errors.

**Groups (session 20, Udea, 1,461 items)**
- Not-ordered hidden: "Cheese 9", "Refrigerated 105", "Case products 174", "Single units 41". Shown at first: 9, 50, 50 and 41 rows.
- Not-ordered shown: Cheese 21, **Refrigerated 153**, Case products 1,098, Single units 189.
- Every Refrigerated row has `category` `002`; no `002` row is in Case or Single. Refrigerated holds both `case` and `unit` items, which keep their own stepper wording.
- `more(refrigerated)`: remaining 103 → 53, `windows {"refrigerated":100}`, Case remaining 1,048 (untouched). A filter change reset `windows` to `{}`.
- Session 306 shows "Case products" only, as before.

**Phone (390 × 844 iframe, 306)**
- `scrollWidth` 375, so no horizontal scroll. `.shop-ord__product--pic` columns `48px 251px`; the title sits at 92–343 inside the row's 16–359.
- With the plan's centred pill, the readout overflowed the row at both ends: bar 0 tip −46…126 and the last projection 232…429, against the row 16…359. The plan's suggested clamp of `x` to [60, width − 60] is not enough because the pill is 170–200 px wide. Fixed by edge-anchoring (Deviation 2).
- After the fix, every position stays inside: bar 0 `start` 26–198, bar 4 `start` 105–276, bar 9 `mid` 136–298, projection 0 `end` 75–270, projection 3 `end` 147–345, all against the row 16–359. The tip's top (716) is below the row's top (566).

**Timing (session 20, JSON in hand)**
```
items JSON (page load): 782 ms, 917 KB          (cycle 1: 508 ms, 566 KB — now carries image_url, weekly_labels, category)
first render, 3 runs:    829, 902, 891 ms → 150 rows   (cycle 1: 564 ms → 100 rows)
show not ordered:        735 ms → 171 rows
sort A–Z:                1,567 ms;  sort best sellers: 1,039 ms
show 50 more (fridge):   409 ms → 221 rows
filter change (reset):   97 ms
image requests on first load: 1 (0 till photos)
```
The image count is low because the tab is hidden: off-screen rows are skipped by `content-visibility`, and lazy images wait until they are on screen.
**First render is about 870 ms, about 54% over 564 ms, so the plan's "within about 25%" is not met.** See Notes: per row it is 5.8 ms against 5.6 ms in cycle 1; the difference is 150 rendered rows against 100, because four groups each open with a window.

**Console**: no errors on `/shop/find` after the hover checks. **Dev state**: nothing written. All checks were hovers, clicks on charts, thumbnails and empty page, and component state. Item 177715 is still at 13 cases, session 306 at €391.22, and the highest `order_adjustments` id is still 9773 (the owner's, as the plan's Context says).

## Deviations
1. **The readout picks the bar under the pointer's column**, not only the bar element (`barAt(e)`: the element under the pointer if it is a bar, else the bar whose horizontal span ±2 px contains `clientX`). The handler moved from `pointerover` to **`pointermove`** so that moving along a column re-evaluates; `hover()` only changes state when the bar changes. Why: a week with no sales is a 3 px stub, so the plan's `e.target.closest('.shop-spark__bar')` gave no readout for exactly the weeks someone might ask about (found with a real hover on 306's last week). `pinBar()` uses the same `barAt()`, so tapping anywhere in a column pins that week.
2. **Edge-anchored pill instead of the suggested clamp.** `tipFor()` adds `align` (`start` in the left third, `end` in the right third, else `mid`). The tip carries `:class="'is-' + (it.hot?.align ?? 'mid')"`, and two APP ADDITIONS rules (`.shop-spark__tip.is-start` / `.is-end`) change only its `transform`. That is one more binding on the tip than the plan's budget of three. A clamp to [60, w − 60] still overflowed, because the pill is wider than 120 px.
3. In `product-peek.js` the scroll listener calls `unpinPeek()`, and `peekAt()` ignores hovers while a panel is pinned (step 6). Find product's behaviour is unchanged (it never pins).
4. Step 1's fallback test has two weeks (an unparseable `week_start` with a label, and a bare number) to cover both fallbacks; the plan's single week is the first of them.
5. View handlers use `x-on:` throughout (Shop rule 5); the plan wrote `@pointerover` etc.
6. P3's item lives in its own draft so cycle 1's count and total assertions stay as they are (step 8).

## Verification
1. css-ok
2. `npm run build` → `✓ built in 8.83s`
3. `php artisan test tests/Feature/Shop tests/Unit/SpecialOrderCategoriesTest.php` → **319 passed** (1596 assertions)
4. `php artisan test` → **15 failed, 1023 passed**, the same five classes (UdeaScrapingServiceTest, CashReconciliationTest, FruitVegLabelPrintingTest, ProductTest, TestScraperControllerTest). The 3 extra passes are 2 new ShopOrderReviewTest tests and 1 SpecialOrderCategoriesTest test.
5. `./vendor/bin/pint --test` on the four PHP files → PASS
6. `grep -c '[^:]style="' resources/views/shop/order-review.blade.php` → 0
7. `grep -c "PEEK_W" resources/js/shop/find-product.js` → 0
8. Browser → step 11 above. **One target missed: first render about 870 ms, against about 705 ms allowed.**

## Files changed
```
 M app/Services/Shop/OrderReviewService.php
 M app/Support/SpecialOrderCategories.php
 M docs/features/order-management/order-generation.md
 M docs/features/shop-mode.md
 M resources/css/shop.css                      (APP ADDITIONS only)
 M resources/js/shop/find-product.js
 M resources/js/shop/order-review.js
 M resources/views/shop/order-review.blade.php
 M tests/Feature/Shop/ShopOrderReviewTest.php
 M tests/Unit/SpecialOrderCategoriesTest.php
?? resources/js/shop/product-peek.js
 M docs/order_clean/implemented.md            (this report)
```
Pre-existing (baseline): `docs/order_clean/README.md`, `plan.md`, `docs/shop_new/README.md`, `docs/order_clean/archive/`.

## Notes for Planner
- **First render over budget (about 870 ms against 564 ms; 25% allows about 705).** The added per-row cost is small: thumbnail, readout and grouping took it from about 5.6 to about 5.8 ms per row. The overrun is the row count: four groups each open with up to 50 rows, so session 20 opens with 150 rows (9 + 50 + 50 + 41) instead of 100. The plan's Risks saw "up to 200 rows" but its Constraint assumed cycle 1's count. Options:
  - (a) Open each group with 25 rows ("Show 25 more"). About 84 rows, about 500 ms.
  - (b) Give the page one budget of 100 rows filled in group order.
  - (c) Accept it. It is still under 1 s in a background tab.

  I did not change `PAGE` because "Show 50 more" is the plan's wording and behaviour.
- **Items JSON grew to 917 KB / 782 ms** (from 566 KB / 508 ms). Most of that is 1,454 image URLs, the long CDN ones included. `byCode()` alone took 482 ms in tinker on this machine (the Planner measured 164 ms). The tinker timings were noisy (682–2,307 ms per `rows()` call).
- Sorting with all 1,461 rows available is slower than in cycle 1 (A–Z 1.57 s against 0.42 s, best sellers 1.04 s against 0.33 s). Background-tab noise is part of that, but the cause is the same as the first-render overrun: up to 171 rows are rebuilt instead of 100.
- On touch a tap on a bar pins the readout. A tap on a different row's bar unpins the first through its `click.outside`, as the plan's Risks intended.
- The native `title` on projection bars still appears after a second, next to the readout (accepted in the Risks).
