# Shop order review: week readout, product pictures, chilled groups

Status: ACCEPTED
Revision: 2
Planner: Fable 5.1
Date: 2026-10-06 (Revision 1 was the week readout alone; Revision 2 adds pictures and chilled groups before kickoff)

## Goal

Three additions to the Shop order review (`/shop/orders/{order}`, cycle 1),
asked for by the owner on 2026-10-06:

1. **Week readout on the sales chart.** Pointing at a bar shows that week's
   sales ("Week of 3 Aug · 25 sold"); on the tablet a tap pins it until the
   next tap. The projected bars show their stock estimate the same way.
2. **Product pictures.** Each row shows the product's thumbnail (till photo
   or supplier picture, as the delivery and Find product screens do), and
   hovering it shows the larger picture in the same floating panel Find
   product uses. On the tablet a tap on the thumbnail pins that panel.
3. **Chilled products grouped.** Refrigerated products (and cheese, which
   the office review also separates) sit in their own groups at the top of
   the list, ahead of Case products and Single units, so the person
   reviewing an order sees the chilled lines together.

Nothing else on the screen changes.

## Context

- Cycle 1 is accepted and archived at
  `archive/2026-10-06-cycle-1-shop-order-review/` (plan with both reviews,
  and the report). The facts below are what this cycle needs.

### The chart (addition 1)

- `resources/views/shop/order-review.blade.php` lines ~56–66.
  `.shop-spark__plot` (design CSS: `position: relative`, `height: 72px`)
  holds `.shop-spark__past` and `.shop-spark__future` (both
  `position: relative; display: flex; align-items: flex-end; gap: 4px`).
  Since cycle 1 Revision 2 the bars are **not** Alpine-bound elements: each
  side is one `x-html` fed by `sparkPast(it)` / `sparkFuture(it)` in
  `resources/js/shop/order-review.js` (section `--- chart`), returning
  `<span class="shop-spark__bar …" style="height:…%">` strings, the past
  side ending with the `.shop-spark__avg` line (design CSS gives it
  `pointer-events: none`). Projection bars carry a native `title` from
  `projTitle(v, w)`. That was done for speed on 1,461-item Udea orders;
  keep it, so per-bar Alpine handlers are out. Event delegation on the plot
  is the way in.
- Row data (`App\Services\Shop\OrderReviewService::row()`): `weekly_sales`
  is a plain list of floats, oldest first, from
  `context_data.weekly_sales[*].units`. Each stored week also has
  `week_start` (`Y-m-d`, a Monday), `week_end` and `label` (`d M`, e.g.
  "03 Aug"); the service drops them today. All 30 newest dev drafts have
  `week_start` on every week; older sessions may hold a bare number per
  week (the row builder tolerates that).
- Items get client-side fields in `load()`: `{ ...it, busy, savedCases, timer }`.
  Rows are reactive objects, so per-item `hot` / `pinned` fields are the
  natural place for readout state.
- `.shop-ord` has `content-visibility: auto` (APP ADDITIONS). Paint
  containment clips anything drawn outside the row's box, so the readout
  must sit inside the row: just above the plot, over the head line, is
  inside. (The picture panel is `position: fixed` on `<main>`, outside any
  row, so it is not affected.)
- Touch: the layout adds `is-touch` to the root on coarse pointers. On
  touch, `pointerleave` fires as soon as the finger lifts, so a hover-only
  readout would vanish before it is read; hence "pinned on tap".

### Pictures (addition 2)

- Thumbnail component: `resources/views/components/shop/product-thumb.blade.php`
  (`<x-shop.product-thumb expr="it" … />`, extra attributes go on the
  `<img>`; it needs the scope composed with `productImages()` from
  `resources/js/shop/product-images.js`, whose `key(p)` is
  `p.id ?? p.barcode ?? p.code`; the order item `id` is unique, so nothing
  to add). The `<img>` has `loading="lazy"`, so only rows on screen fetch.
- URLs: `App\Services\Shop\ProductImageUrls::byCode(array $codes)` →
  `CODE => url|null`; a till photo resolves to `shop.product-photo` with a
  `?v=` cache key, otherwise the supplier CDN picture, otherwise null. The
  delivery screen does exactly this in one batch
  (`DeliveryLegacyController.php:950-958`). Measured today: 1,461 codes
  (session 20) → **164 ms**, 1,454 URLs (37 till photos, 1,417 CDN); 30
  codes (306) → 10 ms. Acceptable on the items JSON.
- Hover panel: Find product's `.shop-peek` (`resources/views/shop/find-product.blade.php:122-125`),
  `position: fixed`, 272 px wide, shown only under
  `@media (hover: hover) and (pointer: fine)` when `.is-open`
  (`resources/css/shop.css` APP ADDITIONS ~618–621). Its logic lives in
  `resources/js/shop/find-product.js`: constants `PEEK_W/H/GAP/EDGE`
  (:23–26), `peek` state, `canHover`, `peekAt(p, el)` (places the panel
  beside the thumbnail, flipping to the left near the right edge),
  `unpeek()`, and a window scroll listener added in `init()` and removed
  in `destroy()` (:53–60). Cycle 14c made one thumbnail implementation for
  the Shop; this cycle does the same for the peek by moving it into a
  shared part that both screens compose.
- Row layout: `.shop-ord__product` is `display: flex; flex-direction: column`
  (design CSS :453). The thumbnail goes to its left, so the block becomes
  a two-column grid under an app class; `.shop-item--pic` (APP ADDITIONS
  :657) is the precedent for "the design row plus a picture column".
- Dev data: 306 has one item with a till photo (the rest get CDN pictures);
  session 20 has 37 till photos.

### Chilled groups (addition 3)

- The office review separates Cheese (POS `CATEGORY` `032`) and
  Refrigerated (`002`) into their own tables ahead of Case and Unit
  products (`review-table.blade.php:89-99`), driven by
  `App\Support\SpecialOrderCategories` (`app/Support/SpecialOrderCategories.php`):
  per-supplier definitions, Udea (`5`, `44`, `85`) has both groups,
  Independent (`37`) has Refrigerated, with labels and
  `category_codes`; `forSupplier()` returns the groups for one supplier.
  The per-supplier split exists for the office's coverage overrides; for
  *display* the owner wants chilled lines together whenever they are in an
  order, so this cycle groups by category code regardless of supplier
  (decision 3 below).
- `Product::CATEGORY` is the POS category id; the test POS fixture
  (`tests/Concerns/CreatesProductSearchPosTables.php`) has the column.
  Session 20 has 153 items in `002`; 306 has none (it is `001`/`082`).
- Windowing (cycle 1 Rev 2): `windows: { case: PAGE, unit: PAGE }`,
  `shown(g)`, `remaining(g)`, `more(g)`, `resetWindows()`; the `groups`
  getter returns `[['case', 'Case products'], ['unit', 'Single units']]`
  filtered to non-empty. The group list must become data-driven.

### Tests and tooling

- `tests/Feature/Shop/ShopOrderReviewTest.php`. Its `item()` fixture builds
  `week_start` as `"2026-08-0{$i}"` for `i` 0–7, which gives the invalid
  date `2026-08-00` for the first week. Step 1 fixes that fixture (real
  Mondays, 7 days apart) because the labels now matter. The fixture's
  `PRODUCTS` rows have no `IMAGE` or `CATEGORY`; steps 5 and 8 add them.
- Timing method in the automation browser (cycle 1 Rev 2 and
  `docs/shop_new/README.md`): the tab is hidden, so `requestAnimationFrame`
  and `x-show` wait on throttled timers; measure with microtask turns plus
  a forced layout, and read component state rather than waiting for
  `x-show`.
- Dev state: draft 306 has item 177715 at 13 cases (the owner's own taps on
  2026-10-06, `order_adjustments` 9766–9773); not this cycle's to touch.

### Decisions recorded (the owner may overturn any of these before kickoff)

1. The week readout is a small dark pill above the bar, hover on desktop,
   tap-to-pin on touch; the projection bars keep their native `title` too.
2. Pictures use the existing thumbnail component and URL rules; the hover
   panel is the Find product one, moved into a shared part. On touch the
   thumbnail tap pins the same panel (new, small) rather than an inline
   enlarge.
3. Chilled groups are by category code for every supplier: **Cheese
   (`032`) then Refrigerated (`002`)**, taken from the union of
   `SpecialOrderCategories::definitions()` so the codes and labels have one
   home; then Case products, then Single units. A product in a chilled
   group leaves Case/Single. Groups with no rows are not shown.
4. Chilled group rows keep their own case/unit stepper wording; the group
   is about where the line sits, not how it is ordered.

## Constraints

- All cycle 1 constraints hold (Shop rules in `docs/shop_new/README.md`:
  view contract, verbatim design block with app rules only under APP
  ADDITIONS, `shop.*` routes only, Alpine traps, no inline `style="…"` in
  views other than `:style` geometry bindings, `mix()` for shared parts).
- No new endpoint: labels, picture URLs and categories ride on the
  existing items JSON.
- Keep the rendering cost where cycle 1 left it: the readout adds one
  element with three bindings per row and three listeners on the plot; the
  thumbnail adds the component's two elements (four bindings) per row;
  grouping adds nothing per row. First render of session 20 must stay
  within about 25% of the 564 ms in cycle 1's report (images load lazily
  and are not part of that number).
- `ProductImageUrls::byCode()` is called **once** per items request with
  every code, never per row.
- Writes, permissions, the stepper, export: untouched. Office pages
  untouched; `SpecialOrderCategories` gains one read-only static method and
  nothing else changes there.
- Do not commit, push or deploy.

## Out of scope

- Any change to the office review page, its Chart.js modal or its
  per-supplier coverage groups.
- Keyboard access to the readout (the chart keeps its `aria-label`
  sentence listing every week's sales).
- An inline "tap to enlarge" picture inside the row (the pinned panel
  covers the tablet).
- Pictures on the Orders list page.
- Changing what the bars show or how the average and peak are computed.
- A design-project update; these are app additions consistent with the
  tokens, recorded in the Shop feature doc.

## Steps

### Part A — week readout

### 1. Week labels in the row data

Files: `app/Services/Shop/OrderReviewService.php`, `tests/Feature/Shop/ShopOrderReviewTest.php`

What: in `row()`, alongside `weekly_sales`, add `weekly_labels`: one string
per week, same order and length. For a week with a parseable `week_start`
use `Carbon::parse($week['week_start'])->format('j M')` ("3 Aug"); if that
throws or the key is missing, fall back to `(string) ($week['label'] ?? '')`;
a bare-number week gives `''`. Keep the list the same length as
`weekly_sales` whatever happens. Fix the test fixture so weeks are real
Mondays: `week_start = Carbon::parse('2026-08-03')->addWeeks($i)`,
`week_end` six days later, `label` as `d M`. Tests: the P1 row's
`weekly_labels` is `['3 Aug', '10 Aug', '17 Aug', '24 Aug', '31 Aug', '7 Sep', '14 Sep', '21 Sep']`;
the "product gone / old context" test asserts `weekly_labels` is `[]`; a
new small case with one week carrying `week_start => 'not-a-date'` and
`label => 'W1'` yields `['W1']`.

Check: `php artisan test --filter=ShopOrderReviewTest` passes; tinker on
session 306 shows `weekly_labels` starting `"3 Aug", "10 Aug"`.

### 2. Readout state and handlers in the module

Files: `resources/js/shop/order-review.js`

What:

- In `load()`, add `hot: null, pinned: false` to each item.
- `sparkPast(it)`: give each past bar `data-w="${i}"`. `sparkFuture(it)`:
  give each projection bar `data-p="${w}"`. Nothing else in those strings
  changes (no user text enters them).
- `tipFor(bar)`: `bar` is a `.shop-spark__bar` element or null. Returns
  `{ kind: 'w', i, x }` for a past bar, `{ kind: 'p', i, x }` for a
  projection bar, null otherwise. `x` is the bar's horizontal centre in
  plot coordinates: `bar.offsetLeft + bar.offsetWidth / 2 + bar.parentElement.offsetLeft`
  (the sides are `position: relative`, so a bar's `offsetLeft` is relative
  to its side; the plot is the side's offset parent).
- `tipText(it)`: for `kind 'w'`:
  `` `Week of ${label} · ${fmt(units)} sold` `` with `label = it.weekly_labels[i]`,
  or `` `Week ${i + 1} · … sold` `` when the label is empty; for `'p'`:
  `projTitle(projection(it)[i], i)`.
- Handlers, taking `(it, event)` or `(it, plotEl)`:
  - `peek…` names are taken by the picture panel (step 6), so call these
    `hover(it, e)`: if `it.pinned` return; else set `it.hot` from
    `e.target.closest('.shop-spark__bar')`.
  - `unhover(it, plot)`: if not pinned, `it.hot = null`.
  - `pinBar(it, e)`: set `it.hot` from the target bar; `it.pinned = !! it.hot`.
    Tapping the bar that is already pinned clears both.
  - `unpinBar(it, plot)`: `it.hot = null; it.pinned = false`.
  - After any change to `it.hot`, mark the bar imperatively: remove
    `is-hot` from every `.is-hot` under the plot, add it to the chosen bar.
    This is deliberate: the bars are `x-html` output and a later re-render
    simply drops the class, which is fine because `it.hot` is cleared on
    leave anyway. Pass the plot element from the view (`$el`); do not
    store elements on the item (Alpine would proxy them).

Check: `npm run build` clean; the view (step 3) has no new `x-for` or
per-bar binding; behaviour in step 11.

### 3. The readout in the view

Files: `resources/views/shop/order-review.blade.php`

What: on the `.shop-spark__plot` div add
`@pointerover="hover(it, $event)" @pointerleave="unhover(it, $el)" @pointerdown="pinBar(it, $event)" @click.outside="unpinBar(it, $el)"`.
Inside the plot, after the future div, add
`<span class="shop-spark__tip" x-show="it.hot" :style="'left:' + (it.hot?.x ?? 0) + 'px'" x-text="it.hot ? tipText(it) : ''"></span>`.
The `?.` and the `it.hot ?` guard are required: `x-show` still evaluates
the other bindings (Shop rule 5). Keep `role="img"` and `:aria-label`.

Check: `ShopViewContractTest` passes; `grep -c '[^:]style="' resources/views/shop/order-review.blade.php` → 0.

### 4. Readout styling

Files: `resources/css/shop.css` (APP ADDITIONS only)

What, under a comment naming this cycle:

```css
.shop-spark__plot .shop-spark__bar { cursor: pointer; }
.shop-spark__bar.is-hot { outline: 2px solid var(--shop-ink-2); outline-offset: 1px; }
.shop-spark__tip { position: absolute; bottom: calc(100% + 6px); transform: translateX(-50%); padding: 4px 10px; border-radius: var(--shop-radius-pill); background: var(--shop-ink); color: var(--shop-surface); font-size: var(--shop-fs-xs); font-weight: var(--shop-fw-heavy); line-height: 1.3; white-space: nowrap; pointer-events: none; z-index: 2; }
```

If the tip at the first or last bar reaches outside the row card on a
phone, clamp `x` in `tipFor()` to `[60, plot.clientWidth - 60]` and say so
in the report.

Check: `css-ok` (design block untouched); build clean.

### Part B — product pictures

### 5. Picture URLs in the row data

Files: `app/Services/Shop/OrderReviewService.php`, `tests/Feature/Shop/ShopOrderReviewTest.php`

What: inject `ProductImageUrls` into the service (constructor, next to
`OrderService`). In `rows()`, after loading the items, call
`byCode()` once with every item's `product?->CODE` (nulls filtered) and
pass the map into `row()`; each row gets `image_url` (`string|null`). In
`update()` (single row) call `byCode([$code])` for that one item so the
PATCH response row is complete. Test: give the fixture's `P1` an `IMAGE`
blob (the F&V test's `"\xFF\xD8\xFFfake-jpeg"` stand-in) and assert its row's
`image_url` starts with `route('shop.product-photo', ['code' => '4007547307025'])`
(ignore the `v`); `P2` has no blob and no supplier CDN in the test, so
`image_url` is null; the "product gone" row has `image_url` null. If the
CDN resolution needs the supplier-cache table that the fixture lacks,
look at how `ShopDeliveryTest` sets it up and copy that, and say so.

Check: `php artisan test --filter=ShopOrderReviewTest` passes;
tinker on 306 shows one `shop/products/…/photo?v=` URL and 29 CDN URLs;
`rows()` on session 20 (timed in tinker) is within 200 ms of its previous
time, and `byCode()` appears once in the code path.

### 6. A shared picture-peek part

Files: `resources/js/shop/product-peek.js (new)`, `resources/js/shop/find-product.js`,
`resources/js/shop/order-review.js`

What: move the hover panel logic out of `find-product.js` into a part
composed with `mix()`, as `product-images.js` is:

```js
// product-peek.js — the floating larger picture beside a hovered thumbnail.
export default () => ({
    peek: null,          // { product, x, y } or null
    peekPinned: false,   // touch: a tapped thumbnail keeps the panel until the next tap
    onPeekScroll: null,
    get canHover() { … as find-product has it … },
    watchPeekScroll() { … adds the passive window scroll listener that calls unpeek() … },
    unwatchPeekScroll() { … removes it … },
    peekAt(p, el) { … as find-product.js, unchanged … },
    pinPeek(p, el) { … on touch only (!canHover): place like peekAt() ignoring the canHover guard, set peekPinned = true; a second tap on the same product clears … },
    unpeek() { if (! this.peekPinned) this.peek = null; },
    unpinPeek() { this.peek = null; this.peekPinned = false; },
});
```

with the four `PEEK_*` constants moved along. `find-product.js` composes
`mix(productImages(), productPeek(), {…})`, drops its own copies, and its
`init()`/`destroy()` call `watchPeekScroll()`/`unwatchPeekScroll()`; its
behaviour is unchanged (the existing Find product tests and a quick
browser hover prove it). `order-review.js` composes the same two parts;
its `init()` calls `watchPeekScroll()`, and add a `destroy()` that calls
`unwatchPeekScroll()`. The part must not define `init` or `destroy`
itself (`mix()` would let the host's override it silently).

Check: `npm run build` clean; `php artisan test tests/Feature/Shop/ShopFindProductTest.php`
passes; in the browser `/shop/find` still shows the panel on hover (step 11).

### 7. The thumbnail and the panel in the review view

Files: `resources/views/shop/order-review.blade.php`, `resources/css/shop.css` (APP ADDITIONS)

What: the product block becomes

```blade
<div class="shop-ord__product shop-ord__product--pic">
    <x-shop.product-thumb expr="it" x-on:mouseenter="peekAt(it, $el)" x-on:mouseleave="unpeek()" x-on:click="pinPeek(it, $el)" />
    <div class="shop-ord__text">
        … the existing h3, meta line and tags, unchanged …
    </div>
</div>
```

and, as the last child of `<main>` (after the toasts), the panel copied
from `find-product.blade.php:122-125` verbatim, with `is-pinned` added:
`:class="{ 'is-open': peek, 'is-pinned': peekPinned }"`. On `<main>` add
`@click.window="maybeUnpin($event)"`, where `maybeUnpin` clears the pin
unless `$event.target.closest('.shop-thumb')` (so a tap on a thumbnail
does not immediately clear what it just pinned); put `maybeUnpin` in the
part so Find product can use it later.

CSS, APP ADDITIONS:

```css
.shop-ord__product--pic { display: grid; grid-template-columns: auto minmax(0, 1fr); column-gap: var(--shop-space-3); align-items: start; }
.shop-ord__text { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.shop-peek.is-pinned { display: block; }
```

(`.shop-ord__product` itself keeps the design's flex rules; the modifier
overrides `display`.)

Check: `ShopViewContractTest` passes; on 306 the one till-photo row shows
its photo and the rest show CDN pictures or the package placeholder; a
broken CDN URL falls back to the placeholder (the component's error
handler); hover and tap behaviour in step 11.

### Part C — chilled groups

### 8. Category and group definitions in the data

Files: `app/Support/SpecialOrderCategories.php`, `app/Services/Shop/OrderReviewService.php`,
`tests/Feature/Shop/ShopOrderReviewTest.php`, `tests/Unit/SpecialOrderCategoriesTest.php`

What:

- `SpecialOrderCategories::displayGroups(): array` (new static, read-only):
  the union of every group in `definitions()`, keyed by group key, in
  first-seen order, each `['label' => …, 'category_codes' => […]]`. With
  today's definitions that is `cheese` (Cheese, `['032']`) then
  `refrigerated` (Refrigerated, `['002']`). Docblock: used by the Shop
  review to group chilled lines regardless of supplier.
- `header()` gains `groups`: an ordered list for the client,
  `[{key:'cheese', title:'Cheese', codes:['032']}, {key:'refrigerated', title:'Refrigerated', codes:['002']}, {key:'case', title:'Case products', codes:[]}, {key:'unit', title:'Single units', codes:[]}]`.
- `row()` gains `category` (`$product?->CATEGORY`, string or null).
- Tests: `order.groups` has those four entries in that order; add a
  fixture product `P3` with `CATEGORY` `'002'` (single unit, ordered) and
  assert its row's `category` is `'002'`, P1's is null (or whatever the
  fixture sets). A unit test for `displayGroups()` in the existing
  `SpecialOrderCategoriesTest`: keys in order, codes as above.

Check: `php artisan test --filter="ShopOrderReviewTest|SpecialOrderCategoriesTest"` passes.

### 9. Grouping in the module and the view

Files: `resources/js/shop/order-review.js`, `resources/views/shop/order-review.blade.php`

What:

- `groupKey(it)`: the first entry of `order.groups` with a non-empty
  `codes` list containing `it.category`; otherwise `it.group`
  (`'case'`/`'unit'`).
- The `groups` getter builds from `order.groups` in order:
  `{ key, title, items: list.filter(it => groupKey(it) === key) }`, empty
  groups dropped, as now.
- `windows` becomes an object keyed on demand: `shown(g)` and
  `remaining(g)` read `this.windows[g.key] ?? PAGE`; `more(g)` writes
  `this.windows = { ...this.windows, [g.key]: (this.windows[g.key] ?? PAGE) + PAGE }`
  (reassigned so Alpine sees it); `resetWindows()` sets `{}`.
- The view's group loop needs no change beyond reading `g.title`; confirm
  the `:key="g.key"` still holds.

Check: on session 20, groups read "Cheese N", "Refrigerated 153"
(with not-ordered shown; fewer when hidden), then "Case products",
"Single units"; a `002` product is absent from Case/Single; the "Show 50
more" buttons work per group; on 306 only Case products appears, as
before.

### Part D — docs, formatting, browser

### 10. Docs and formatting

Files: `docs/features/shop-mode.md`, `docs/features/order-management/order-generation.md`

What: in "Orders: list and order review" (`shop-mode.md`): pointing at a
bar shows that week's sales, a tap pins it on the tablet; rows carry the
product picture with the Find product hover panel (tap pins it on touch);
chilled products (Cheese `032`, Refrigerated `002`, from
`SpecialOrderCategories::displayGroups()`) are grouped first for every
supplier. One line in `order-generation.md`'s "2026-10 Shop mode review"
entry about the grouping differing from the office's per-supplier rule.
Then `./vendor/bin/pint --test` on the service, the support class and the
test files.

Check: `grep -n "Week of\|displayGroups" docs/features/shop-mode.md` finds
both; pint clean.

### 11. Browser check

What: `/shop/orders/306` as `katelyn` in the automation Chrome (a
signed-in session should still be there; if not, ask the owner).

- Readout: `hover` (computer tool) on a past bar of the first row; read
  `Alpine.$data(main).items.find(...).hot` and the tip text: "Week of
  3 Aug · N sold" with N equal to that row's `weekly_sales[0]`; the last
  past bar changes the text; a projection bar reads "Delivery: about N in
  stock"; off the plot `hot` is null. Click a bar: `pinned` true; click
  elsewhere: both cleared. `is-hot` lands on the hovered bar and leaves it.
- Pictures: rows show thumbnails (count visible `img.shop-thumb` vs
  placeholders); hover the till-photo row's thumbnail → `peek` set, panel
  `is-open` with that product's name; move off → null. Simulate touch:
  with `canHover` forced false in state, `pinPeek` on a thumbnail sets
  `peekPinned` and the panel has `is-pinned`; a click elsewhere clears it.
  `/shop/find`: hover a result thumbnail still opens the panel.
- Groups: session 20 group titles and counts as in step 9; `/shop/orders/306`
  unchanged apart from pictures.
- Phone (390 × 844 iframe, 306): the thumbnail column does not push the
  title off; the tip's box stays inside the row card; no horizontal
  scroll.
- Timing on session 20 (JSON in hand, cycle 1 Rev 2 method): first render
  within about 25% of 564 ms; items JSON time recorded (was 508 ms; now
  carries URLs and categories); the number of image requests on first
  load.
- Console: no errors. Nothing is saved by any of this; say so under Dev
  state.

## Verification

1. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo css-ok`
2. `npm run build 2>&1 | tail -3` → clean.
3. `php artisan test tests/Feature/Shop tests/Unit/SpecialOrderCategoriesTest.php` → all pass.
4. `php artisan test` → 15 failed, the same five classes as the baseline
   (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3,
   `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2,
   `TestScraperControllerTest` ×1); baseline after cycle 1: 15 failed /
   1020 passed.
5. `./vendor/bin/pint --test app/Services/Shop/OrderReviewService.php app/Support/SpecialOrderCategories.php tests/Feature/Shop/ShopOrderReviewTest.php tests/Unit/SpecialOrderCategoriesTest.php` → clean.
6. `grep -c '[^:]style="' resources/views/shop/order-review.blade.php` → 0.
7. `grep -c "PEEK_W" resources/js/shop/find-product.js` → 0 (the
   constants and logic live in `product-peek.js` only).
8. The browser check of step 11, written up with the readings.

## Risks

- **Paint containment**: the tip is clipped if it is ever drawn above the
  row's top edge; it sits above the plot but below the row's top padding.
  Check on the phone, where the head line wraps. The picture panel is
  outside the rows and unaffected.
- **`x-html` re-render drops `is-hot`**: a stepper tap while hovering a
  bar re-renders that row's bars, losing the class until the next pointer
  move. Cosmetic; leave it.
- **Native titles on projection bars** also appear after a second, next
  to the readout. Accept; the design put them there.
- **Touch and `click.outside` / `click.window`**: both fire on taps. A tap
  on one row's bar may unpin another row's readout and pin this one; that
  is intended. Make sure the thumbnail tap does not clear its own pin
  (step 7).
- **Many image requests**: only rendered rows fetch (lazy, windowed at 50
  per group), but with four groups up to 200 rows can be on the page after
  "Show more" taps; the thumbnail route caches for a week and CDN
  pictures are small. Report the count on first load of session 20.
- **Supplier pictures that 404**: the component hides the image on error
  and shows the placeholder; nothing to do, but expect some in the network
  tab.
- **`mix()` name clashes**: the peek part must not define `init`/`destroy`;
  the host calls `watchPeekScroll()` itself.
- **Group order on big orders**: Cheese and Refrigerated at the top push
  Case products down; with 153 refrigerated rows the first "Show 50 more"
  is the chilled group's. That is the point of the request.

## Review (2026-10-06) — ACCEPTED

Read `implemented.md` to the end, the diffs of all eleven touched files and
the new `product-peek.js`; reran `css-ok`, the inline-style and `PEEK_W`
greps, pint, the Shop suite plus the unit test (319 passed) and the full
suite: **15 failed / 1023 passed**, the same five classes as the baseline.
Spot-checked in the browser as `katelyn`: on 306 a dispatched pointer move
over the first bar gives "Week of 3 Aug · 25 sold" with the bar marked;
the thumbnail's mouseenter opens the panel with the muffin's CDN picture and
caption, mouseleave closes it; with `matchMedia` stubbed to touch, a click
on the thumbnail pins it (`is-open is-pinned`) and a click on the page
clears it. On session 20 the groups read Cheese 9, Refrigerated 105, Case
products 174, Single units 41; every Refrigerated row is category `002`
(1 case product, 104 units) and none sits in Case or Single. The
computer tool's real `hover` did not reach the page in this hidden tab
(no `pointermove` arrived), so the pointer path rests on the Implementer's
real-hover readings plus my dispatched events.

### Criteria

| Step | Result |
|---|---|
| 1 Week labels | pass; the two-week fallback test is better than the one I asked for |
| 2–4 Readout | pass; see Deviations 1 and 2 |
| 5 Picture URLs | pass; `byCode()` once in `rows()`, once for the single row in `update()`; PATCH response row carries the URL |
| 6 Shared peek part | pass; Find product composes it, loses its own copy, behaves the same (its tests and the browser) |
| 7 Thumb and panel | pass; `maybeUnpin` lives in the part as suggested |
| 8–9 Groups | pass; `displayGroups()` is a pure union, unit-tested; the client buckets in one pass; windows keyed on demand |
| 10 Docs | pass |
| 11 Browser | pass, with the render budget missed (below) |
| Verification | all eight as reported; my own reruns agree |

### The render budget

First render of session 20 with the JSON in hand: the Implementer
measured about 870 ms (I measured 1.2 s in the same hidden tab, which is
noisier than its 564 ms counterpart was); cycle 1's figure was 564 ms. The
per-row cost barely moved (5.6 → 5.8 ms); what grew is the row count: four
groups each open a 50-row window, so the Udea order opens with 150 rows
instead of 100. **Accepted as is.** The constraint assumed cycle 1's row
count and did not account for the groups the owner asked for. The number
to watch is the tablet's, not this tab's; the owner is already due to try
an Udea draft there (cycle 1 review). If it drags, `PAGE` = 25 is one
constant, and that is the first thing to try.

### Deviations

1. **Column-based bar pick on `pointermove`** — accepted and welcome: a
   zero-sales week is a 3 px stub, and the whole point was to read those
   weeks. The cost is fourteen `getBoundingClientRect` calls per move only
   when the pointer is not on a bar.
2. **Edge-anchored pill** — accepted; the plan's clamp was wrong for a
   170–200 px pill. One extra binding on the tip.
3. **Scroll clears a pinned panel** — accepted; a pinned panel is as
   stranded by a scroll as a hovered one.
4. Two-week fallback test — accepted.
5. `x-on:` instead of `@` — accepted; either is fine for pointer events,
   and the long form reads consistently with the thumbnail's handlers.
6. P3 in its own draft — accepted.

### Notes for Planner

- **Render over budget** — decided above.
- **Items JSON 917 KB** — accepted; 1,454 CDN URLs is what it is. If it
  ever matters, the server could send a short CDN key and the client could
  build the URL, but not now.
- **Sorts slower with 171 rows** — same cause, same decision.
- Touch pin behaviour across rows — as intended.
- Native titles on projection bars — accepted in the Risks.

Noticed by me, **not fixed, a tidy for later**: `rows()` builds the code
list with `->filter()` and no callback, which also drops a product whose
code is the string `"0"` (none on the two dev orders checked). The exact
filter is `fn ($c) => $c !== null && $c !== ''`.

Dev state: nothing written by the cycle or the review; item 177715 is still
at the owner's 13 cases.

Archived to `archive/2026-10-06-cycle-2-readout-pictures-groups/`.
