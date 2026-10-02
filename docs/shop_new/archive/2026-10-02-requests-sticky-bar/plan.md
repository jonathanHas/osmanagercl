# Customer requests: keep "New request" on screen

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-10-02

## Goal

The owner's note (`docs/shop_new/ToDo.txt`): when there are a lot of existing
requests, staff have to scroll to the bottom of the Customer requests page to
reach "New request". Taking a request at the counter should start with one
tap.

The design already intends that: the "New request" bar is meant to stay fixed
at the bottom of the screen. It does not, because of how the page is nested
(see Context). After this task the bar stays at the bottom of the screen
however long the list is. The Print labels screen has the same fault with its
"Print" bar and is fixed the same way.

Two small search gaps on the request screens are closed while those files are
open: neither the New request form nor the request edit screen says "No
products match" when a product search succeeds and finds nothing.

## Context

Cause, measured by the Planner on dev (2026-10-02):

- `.shop-actions` (design block of `resources/css/shop.css`, line 228) is
  `position: sticky; bottom: 0` with `margin: auto -16px -16px`. In the design
  file (`docs/design/shop-mode/screen-10-requests-staff-v2.html`, line 74) it
  is a direct child of `<main class="shop-page">`, a flex column as tall as
  the page, so it sticks to the bottom of the viewport while the list scrolls.
- In `resources/views/shop/partials/requests-staff.blade.php` (line 78) the
  bar sits inside `<div x-data="{ open: @js($openNew) }" …>`, a wrapper that
  holds only the bar and the sheet backdrop (which is `position: fixed`). A
  sticky element cannot leave its parent's box, and this parent is exactly as
  tall as the bar: measured parent height 81 px. So the bar sits wherever the
  page ends. At 390 × 844 with only four requests the document is 1,216 px
  tall and the bar's top is at 1,119 px, below the screen.
- `resources/views/shop/labels.blade.php` (line 63) has the same shape: the
  bar is inside a `<form … x-show="total > 0" x-cloak>` that holds only hidden
  inputs and the bar (measured parent height 81 px).
- Every other `.shop-actions` in `resources/views/shop/**` is a direct child
  of `<main>` or of a form that wraps the whole page
  (`request-edit.blade.php`), and `delivery-scan.blade.php` uses the
  deliberate `shop-actions--static` variant. Those are correct.

The fix that keeps the markup and the tests: give the wrapper
`display: contents`, so it generates no box and the bar becomes, for layout,
a direct child of `<main>`. Alpine scopes, the form submission and the
`x-data="{ open: … }"` literal are unaffected. `x-show` sets an inline
`display: none` and removes it again, so a class providing
`display: contents` coexists with `x-show`; `[x-cloak]` in the layout is
`display: none !important` and wins while cloaked.

Tests that pin this markup and must keep passing unchanged:
`tests/Feature/CustomerRequestTest.php` lines 268, 280, 284 and
`tests/Feature/Shop/ShopRequestsTest.php` line 670 assert the exact text
`x-data="{ open: true }"` / `x-data="{ open: false }"`. A `class` attribute
placed before `x-data` on the wrapper does not disturb them.

Search gaps:

- `resources/views/shop/partials/request-form.blade.php` (New request) and
  `resources/views/shop/request-edit.blade.php` have no "No products match"
  line. A search that succeeds with zero hits shows nothing.
- `resources/views/shop/delivery-scan.blade.php` has one, shown when
  `! searching && query.trim() !== '' && ! results.length && ! searchError`.
  During the 250 ms input debounce `searching` is still false and `results`
  may be empty, so it can show for a moment before the search has run.
- `resources/js/shop/product-typeahead.js` (shared by all three) knows when
  an answer has arrived but does not record which query it answered.

Test baseline: **15 failed, 939 passed** (2026-10-02; the 15 are
`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3,
`FruitVegLabelPrintingTest` ×2, `ProductTest` ×2,
`TestScraperControllerTest` ×1).

Working tree at planning time: two accepted tasks are uncommitted
(`delivery-scan.js`, `product-typeahead.js`, `delivery-scan.blade.php`,
`request-form.blade.php`, `request-edit.blade.php`, `ShopDeliveryTest.php`,
`ShopRequestsTest.php`, `docs/features/shop-mode.md`, `docs/shop_new/`),
unless the owner has committed since. Record the baseline and build on what
is there.

## Constraints

- `docs/shop_new/README.md` rules apply. The design block of `shop.css` stays
  byte-identical; the one new rule goes between the APP ADDITIONS markers.
  Views use only `shop-*` classes.
- Do not move the bar or the sheet out of their Alpine scope, and do not
  change `x-data="{ open: @js($openNew) }"`.
- The public (guest) board is unchanged: it has no bar.
- A successful search with results behaves exactly as now on all three
  screens.
- No PHP application code changes.
- Do not rewrite or delete existing tests.
- Do not commit, push or deploy.
- Every response ends with `mpg123 /home/jon/Music/notification.mp3`.

## Out of scope

- Any other way of starting a request (a button in the header, a Home tile
  shortcut, a keyboard shortcut). The sticky bar is the design's answer; if
  the owner still finds it slow after this, that is a new task.
- The New request form's fields, validation or layout.
- Destocked products in the New request search (a separate open item).
- The order or grouping of requests on the board.
- Other screens' action bars (they are correct).

## Steps

### 1. A `shop-contents` utility

Files: `resources/css/shop.css`

What: between the APP ADDITIONS markers, one rule with a comment saying what
it is for:

```css
/* A wrapper that exists only for an Alpine scope or a form must not be a box,
   or a sticky .shop-actions inside it cannot stick (it cannot leave a parent
   that is only as tall as itself). */
.shop .shop-contents { display: contents; }
```

Check: design-block `cmp` from the README prints nothing.

### 2. Customer requests: the bar sticks

Files: `resources/views/shop/partials/requests-staff.blade.php`

What: add `class="shop-contents"` to the wrapper, before its `x-data`
attribute: `<div class="shop-contents" x-data="{ open: @js($openNew) }" …>`.
Update the partial's header comment: the wrapper is `display: contents` so
the bar is laid out as a child of `<main>` and sticks. Nothing else moves.

Check: new test in `ShopRequestsTest`,
`test_the_new_request_bar_is_laid_out_to_stick`: the staff board contains
`<div class="shop-contents" x-data="{ open: false }"`.
`php artisan test tests/Feature/CustomerRequestTest.php tests/Feature/Shop/ShopRequestsTest.php`
passes, including the four pinned `x-data` assertions.

### 3. Print labels: the bar sticks

Files: `resources/views/shop/labels.blade.php`

What: add `class="shop-contents"` to the print form (line 63). Keep its
`x-show="total > 0"`, `x-cloak`, `target`, `@submit` and contents as they
are. Add a one-line comment above it saying why the form is
`display: contents`.

Check: new test in `ShopLabelsTest`,
`test_the_print_bar_is_laid_out_to_stick`: the labels page contains a
`<form` tag carrying `class="shop-contents"` and the `labels.print-a4`
action. `php artisan test tests/Feature/Shop/ShopLabelsTest.php` passes.

### 4. The typeahead records which query was answered

Files: `resources/js/shop/product-typeahead.js`

What:
- New state `answered: null`: the trimmed query the current `results` belong
  to, or null when no search has been answered (or it failed).
- Set it to `q` where a good answer writes `results`. Set it to `null` in the
  empty-query early return, in both failure paths, and in `pickResult()`.
  Stale calls (wrong `seq`) do not touch it.
- New getter `noMatches`:
  `this.answered !== null && this.answered === this.query.trim() && this.query.trim() !== '' && ! this.results.length && ! this.searchError`.
- Add both to the header comment.

Check: `npm run build` completes. Extend the existing
`test_the_typeahead_reports_a_failed_search` or add
`test_the_typeahead_knows_when_a_search_found_nothing` asserting the source
contains `answered` and `get noMatches()`.

### 5. "No products match" on the three screens

Files: `resources/views/shop/partials/request-form.blade.php`,
`resources/views/shop/request-edit.blade.php`,
`resources/views/shop/delivery-scan.blade.php`,
`resources/js/shop/delivery-scan.js`, `resources/js/shop/requests.js`

What:
- New request form: after the "Try again" button and before the results
  list, `<p class="shop-meta" x-show="! picked && noMatches" x-cloak>No products match</p>`.
- Request edit: the same line with `x-show="noMatches"`.
- Delivery scan: the existing line's condition becomes `x-show="noMatches"`.
  The existing test asserts the text
  `&& ! searchError" x-cloak>No products match`; that assertion describes the
  old condition, which now lives in the getter. This is the one existing
  assertion the plan changes: update that single `assertSee` to
  `x-show="noMatches" x-cloak>No products match` and leave the rest of the
  test alone.
- `closeManual()` in `delivery-scan.js` and `unpick()` in `requests.js` both
  empty `results` by hand: set `answered = null` there too.

Check: tests assert `noMatches` appears on `/customer-requests` (staff), on a
request's edit page and on the delivery scan page.
`php artisan test tests/Feature/Shop` passes.

### 6. Documentation

Files: `docs/features/shop-mode.md`

What: in the view-contract or layout part of the document, a short note: a
sticky `.shop-actions` must be laid out as a child of the page's tall
container (`<main>`, or a form that wraps the whole page); a wrapper that
exists only for an Alpine scope or a form takes `shop-contents`. Name the two
screens fixed. One sentence where the typeahead is described: `noMatches`
shows "No products match" only once the search for the text on screen has
answered.

Check: reads correctly against the code.

## Verification

1. `./vendor/bin/pint --dirty` → no errors.
2. `npm run build` → completes.
3. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css`
   → no output.
4. `php artisan test tests/Feature/Shop tests/Feature/CustomerRequestTest.php`
   → all pass.
5. `php artisan test` → the same 15 failures and nothing else; 939 plus the
   new tests passed.
6. Browser on dev (`http://osmanager.local`), a fresh tab, console clean.
   Measure, do not judge by eye: for each case record the bar's
   `getBoundingClientRect()` top and bottom, the viewport height and the
   document's `scrollHeight`, in a same-origin iframe of the stated size.
   a. `/customer-requests` at 390 × 844, scrolled to the top: the "New
      request" bar's bottom equals the viewport height (844, give or take the
      safe-area padding) although the document is taller. Before this task
      the bar's top was at 1,119 with four requests.
   b. Scroll to the bottom: the bar is still at the bottom of the viewport
      and the last request row is fully visible above it (its bottom is at or
      above the bar's top).
   c. 1280 × 800: the bar is at the bottom of the viewport, button
      right-aligned as the design's wide layout has it.
   d. Tap "New request": the sheet opens; Escape and Cancel close it. Type
      `zzzqqq` in the item search: "No products match" appears after the
      search answers, and not while typing (read `noMatches` straight after
      the input event and again after 1 s). Type `che`: results, no "No
      products match". Do not submit.
   e. Signed out (a private window, or fetch the page without cookies): the
      public board renders with no bar and no layout change.
   f. A request's edit page: `zzzqqq` in its item search → "No products
      match". Its own Save/Cancel bar is still at the bottom of the viewport.
      Do not save.
   g. `/shop/labels` at 390 × 844 with at least one label queued (dev had one
      at planning time; if the queue is empty, say so and check the layout by
      temporarily setting the page's Alpine `total` to 1 and `rows` as it
      has them): the Print bar's bottom is at the bottom of the viewport. With
      the queue empty (`total` 0) the bar is not shown. Do not print or
      clear.
   h. Delivery scan, "Find by name": `zzzqqq` → "No products match" and
      "Search all products", as before.
7. Dev state: nothing is written in this pass. Say so, or list anything that
   was.

## Risks

- **`display: contents` and `x-show`.** On the labels form `x-show` toggles
  an inline `display: none`; when Alpine clears it the class applies. If the
  form shows when it should be hidden, or the reverse, check 6g catches it.
- **The bar covering the last row.** A sticky bar overlays the end of the
  list while scrolling; at the very end the list must clear it (6b). The
  design relies on the bar's negative margins inside `<main>`'s padding for
  that; if the last row is covered, report the measurements rather than
  inventing spacing.
- **`margin-top: auto`.** As a flex child of `<main>` the bar's `auto` top
  margin pushes it to the bottom on a short page. That is the design's
  behaviour; confirm at 1280 × 800 with few requests (6c) that it looks
  intended, bar at the bottom of the screen.
- **Pinned `x-data` assertions** in two test files: the class goes before
  `x-data`, and the attribute's text is unchanged.

## Review

Reviewed 2026-10-02 by the Planner: `implemented.md` read to the end, the
whole diff read, suite rerun.

Reran: `php artisan test` → 15 failed, 942 passed (the baseline 15, nothing
else). Design-block `cmp` → identical. `app/` has no diff. The browser pass
was not repeated by the Planner; the report gives measured positions for each
case, which line up with the Planner's own before-measurements (bar top 1,119
before, 747 with bottom 844 after, at 390 × 844).

Steps:
1. `.shop .shop-contents { display: contents; }` under APP ADDITIONS: pass.
2. Customer requests wrapper takes the class; the four pinned `x-data`
   assertions pass unchanged; bar measured at the viewport bottom at the top
   and the end of the scroll, last row clear of it: pass.
3. Print labels form takes the class; hidden when the queue is empty, bar at
   the viewport bottom with a long queue: pass.
4. `answered` and `noMatches` in the typeahead; stale calls do not touch
   them: pass.
5. "No products match" on New request, request edit and delivery scan, shown
   only after the search has answered; the one planned assertion change made:
   pass.
6. Documentation: pass.

Deviations:
- 6g faked `rows` rather than `total` (a getter): accepted; the plan's
  wording was wrong.

Notes for Planner:
- 8 px gap under the bar on wide screens when the page does not scroll:
  deferred to the owner. It comes from the design block (32 px page padding
  against a 24 px bar margin at wide widths), applies to every screen's bar,
  and is not new. An APP ADDITIONS rule could close it if wanted.
- Labels `total` is a getter: noted in `README.md`.
- `find-product.js` does not use the shared typeahead: accepted, out of scope.

Nothing to redo. Uncommitted; the owner commits.
