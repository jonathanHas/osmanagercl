# Product search: say when it failed, and let staff retry

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-10-02

## Goal

When the product search on a Shop screen fails (the tablet's wifi drops, the
session has expired, the server errors), staff see an empty list and nothing
else, which looks like "there are no products". After this task the screen
says the products could not be loaded and offers "Try again", or says the
person has been signed out. A slow, older search answer can no longer
overwrite a newer one. And the delivery screen's "No barcode? Find by name"
button is hidden until the page is ready, so an early tap cannot be lost.

This comes from `docs/shop_new/findings/2026-10-02-find-by-name-empty-list.md`
(read it first). The owner asked for the fix on 2026-10-02.

## Context

- `resources/js/shop/product-typeahead.js` is the shared search part. Its
  `search()` (line 36 onwards) does `fetch` then `response.json()` inside a
  `try`, and its `catch` sets `results = []` and `total = 0`. It never looks
  at `response.ok`. So:
  - a rejected request (offline) → empty list, silently;
  - a 401 or 419 (signed out; Laravel answers JSON because the request sends
    `Accept: application/json`) → `data.data` is undefined → empty list;
  - a 500 → the same;
  - a login page returned as HTML → `json()` throws → empty list.
- There is no ordering guard: each call writes `results` when it resolves, so
  an older request that answers late replaces a newer one's results.
- Three screens compose it with `mix()`:
  - delivery scan: `resources/js/shop/delivery-scan.js` and the "Find by name"
    card in `resources/views/shop/delivery-scan.blade.php` (the card is
    `x-ref="manual"`; its lines "Showing the first N", "No products match" and
    the "Search all products" button follow the results list);
  - New request: `resources/js/shop/requests.js`, markup in
    `resources/views/shop/partials/request-form.blade.php` (search input line
    41, results list line 46);
  - request edit: `resources/js/shop/request-edit.js`, markup in
    `resources/views/shop/request-edit.blade.php` (input line 128, list
    line 134).
- `.shop-notice` (design block, used on the delivery screen for "No invoice
  lines…") is an icon-and-text line: `<p class="shop-notice"><x-shop.icon name="info" size="sm" />text</p>`.
  The sprite has an `alert` icon.
- The "No barcode? Find by name" button (delivery view line 95) is
  `x-show="! manual"` with no `x-cloak`, so it is visible and clickable before
  Alpine starts; a click then does nothing (measured: Alpine starts 65–130 ms
  into the load on dev). The layout defines `[x-cloak]{display:none!important}`.
- `/shop/find` uses its own search (`find-product.js`), not this part.
- Tests for these screens assert on rendered markup and on JS source text
  (`tests/Feature/Shop/ShopDeliveryTest.php` has a `scanJs()` helper;
  `tests/Feature/Shop/ShopRequestsTest.php` covers the request screens).
  There is no JavaScript test runner.

Test baseline: **15 failed, 937 passed** (2026-10-02; the 15 are
`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3,
`FruitVegLabelPrintingTest` ×2, `ProductTest` ×2,
`TestScraperControllerTest` ×1).

Working tree at planning time: the accepted delivery follow-ups are
uncommitted (`resources/js/shop/delivery-scan.js`,
`resources/views/shop/delivery-scan.blade.php`,
`tests/Feature/Shop/ShopDeliveryTest.php`, `docs/features/shop-mode.md`,
`docs/shop_new/`). This task edits three of those files again; record the
baseline and build on what is there.

## Constraints

- `docs/shop_new/README.md` rules apply (view contract, design block
  untouched, Alpine traps: no `@` shorthand that is a Blade directive, `?.`
  behind `x-show`, no handler on an element an `x-if` can remove).
- A successful search behaves exactly as now on all three screens, including
  Enter-picks-first and the scanner-typed barcode path in `pickFirst()`.
- No PHP application code changes. No new CSS is expected.
- The pinned source tests in `ShopDeliveryTest` keep passing unchanged (three
  `this.announceSaved();`, prompt before scan field, and the rest).
- Do not rewrite or delete existing tests.
- Do not commit, push or deploy.
- Every response ends with `mpg123 /home/jon/Music/notification.mp3`.

## Out of scope

- `/shop/find` and `find-product.js`.
- Failure handling for the delivery items list, scan lookups or saves (they
  already show a message).
- Automatic retries, offline detection, or redirecting to the login page.
- The owner's `docs/shop_new/ToDo.txt` (customer requests scroll).

## Steps

### 1. The typeahead records a failure and ignores stale answers

Files: `resources/js/shop/product-typeahead.js`

What:
- New state `searchError: null`. Values: `null`, `'failed'`, `'signed-out'`.
- New getter `searchMessage`: `''` for null,
  `'Could not load products.'` for `'failed'`,
  `'You have been signed out. Reload the page to sign in.'` for `'signed-out'`.
- New state `searchSeq: 0`. `search()` takes `const seq = ++this.searchSeq`
  before the request; after each `await`, if `seq !== this.searchSeq` it
  returns without touching `results`, `total`, `searchError` or `searching`
  (the newer call owns them).
- After the response: status 401 or 419 → `searchError = 'signed-out'`; any
  other `! response.ok` → `'failed'`; in both cases `results = []`,
  `total = 0`, and do not parse the body. A thrown error (network, or a body
  that is not JSON) → `'failed'`. A good response → `searchError = null`.
- The empty-query early return also sets `searchError = null`; so does
  `pickResult()`.
- `searching` goes back to false only for the latest request (a stale call
  must not switch it off while the newer one is still in flight).
- Update the header comment: what `searchError` and `searchMessage` are for.

Check: `npm run build` completes. New test in `ShopRequestsTest`,
`test_the_typeahead_reports_a_failed_search`, asserts the file's source
contains `searchError`, `'signed-out'`, `response.ok` and `searchSeq`.

### 2. Delivery "Find by name": message, retry, and a cloaked button

Files: `resources/views/shop/delivery-scan.blade.php`,
`resources/js/shop/delivery-scan.js`

What:
- In the `x-ref="manual"` card, directly after the filter input and before
  the results list:
  `<p class="shop-notice" x-show="searchError" x-cloak>` with the `alert`
  icon (size `sm`) and a `<span x-text="searchMessage"></span>`; and a ghost
  button "Try again" (`x-show="searchError === 'failed'"`, `x-cloak`,
  `x-on:click="search()"`).
- "No products match" must not show while `searchError` is set: add
  `&& ! searchError` to its `x-show`.
- `closeManual()` also sets `searchError = null`.
- Add `x-cloak` to the "No barcode? Find by name" button.

Check: new test in `ShopDeliveryTest`,
`test_find_by_name_says_when_the_search_failed`: the scan page (user with
`products.view`) contains `x-text="searchMessage"`, `Try again`,
`x-show="searchError === 'failed'"`, and the "No barcode?" button's tag
contains `x-cloak` (assert on the exact tag text you wrote).
`php artisan test tests/Feature/Shop/ShopDeliveryTest.php` passes.

### 3. The same message on New request and request edit

Files: `resources/views/shop/partials/request-form.blade.php`,
`resources/views/shop/request-edit.blade.php`

What: directly after each screen's search input wrapper and before its
results list, the same notice and "Try again" button as step 2 (same
bindings). On the New request form the notice follows the form's existing
visibility rule for the search (`! picked`), so it does not show once a
product has been picked. Write `x-on:click`, not `@click`, for the new
buttons, and give them `type="button"` (both sit inside forms).

Check: add to `test_the_typeahead_reports_a_failed_search` (or a second test)
that `/customer-requests` (as a user with `customer-requests.manage`) and a
request's edit page each contain `x-text="searchMessage"`; use the fixtures
and helpers `ShopRequestsTest` already has for reaching those pages.
`php artisan test tests/Feature/Shop` passes.

### 4. Documentation

Files: `docs/features/shop-mode.md`

What: two or three sentences where the product typeahead or "Deliveries:
items without a barcode" is described: a failed search shows "Could not load
products." with "Try again", a signed-out session says so, stale answers are
ignored, and the "No barcode?" button appears once the page is ready.

Check: reads correctly against the screens as built.

## Verification

1. `./vendor/bin/pint --dirty` → no errors.
2. `npm run build` → completes.
3. `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css`
   → no output.
4. `php artisan test tests/Feature/Shop` → all pass.
5. `php artisan test` → the same 15 failures and nothing else; 937 plus the
   new tests passed.
6. Browser on dev (`http://osmanager.local`), console watched and clean apart
   from the failures you inject. Use the open Mossfield session
   `778fb73c-a674-4533-8160-3fe9187f4bb1` (supplier 28); opening the list
   writes nothing, so no session needs creating. The automation tab may be
   hidden, in which case `requestAnimationFrame` does not fire and fields are
   not auto-focused (README, scanning facts): click into fields by hand.
   a. Normal: open "Find by name" → 10 products, no notice.
   b. Offline: in the console make `window.fetch` reject for URLs containing
      `/api/products/search`; close and reopen the list → notice "Could not
      load products." and "Try again"; no "No products match". Restore
      `fetch`, tap "Try again" → 10 products, notice gone.
   c. Signed out: make `fetch` resolve with
      `new Response('{"message":"Unauthenticated."}', { status: 401, headers: { 'Content-Type': 'application/json' } })`
      for the search URL; reopen → "You have been signed out. Reload the page
      to sign in." and no "Try again". Restore `fetch`.
   d. Server error: the same with status 500 → "Could not load products."
   e. Stale answer: wrap `fetch` so the first search request is delayed 1.5 s
      and later ones are not; type `sl` then `slieve` quickly (two requests);
      after 2 s the list shows the `slieve` result (one product), not the
      `sl` results. Record `results.length` and the names.
   f. `/customer-requests` → New request: inject the offline failure, type
      `che` → notice and "Try again"; restore, "Try again" → results; pick
      one → notice absent. Do not submit.
   g. A request's edit page: inject the failure, type in its item search →
      notice. Do not save.
   h. Early tap: reload the scan page and confirm from the page source that
      the "No barcode?" button carries `x-cloak`; after load it is visible.
7. Dev state: nothing is written in this pass. Say so, or list anything that
   was.

## Risks

- **`searching` and the sequence guard.** A stale call returning early must
  not leave `searching` stuck true or switch it off under a newer call. The
  "No products match" line depends on `! searching`.
- **`pickFirst()`** awaits `search()` then reads `results`; with a failed
  search it must simply pick nothing. Check 6f covers the screen it matters
  on.
- **Notice inside a form.** The new "Try again" buttons need `type="button"`
  or they submit the request form.

## Review

Reviewed 2026-10-02 by the Planner: `implemented.md` read to the end, the
whole diff read, suite rerun.

Reran: `php artisan test` → 15 failed, 939 passed (the baseline 15, nothing
else). Design-block `cmp` → identical. `this.announceSaved();` count → 3.
`app/` has no diff. The browser pass was not repeated by the Planner; the
report gives component state and visible text for each injected failure.

Steps:
1. `searchError`, `searchMessage`, `searchSeq`; `response.ok` checked;
   401/419 → signed-out; stale calls write nothing and leave `searching` to
   the latest call: pass. Taking the sequence number before the empty-query
   return is a sound addition (a late answer cannot refill a cleared list).
2. Delivery list: notice, "Try again" for `failed` only, "No products match"
   suppressed on error, "No barcode?" button cloaked: pass.
3. New request and request edit: same notice and retry, `type="button"`,
   hidden once a product is picked on the New request form: pass.
4. Documentation: pass.

Deviations:
- `closeManual()` also bumps `searchSeq` and resets `searching`: accepted; it
  closes a gap the plan left (an in-flight answer landing behind a closed
  list).
- Browser pass as admin: accepted, as before.
- 6f/6g driven partly by DOM events after the automation tab stalled:
  accepted. The typeahead state, bindings and button handlers under test ran
  in the page; what was not exercised by a real pointer is a plain click on
  two ghost buttons.
- 6e rerun with `m` / `mature` because `sl` and `slieve` return the same
  product: accepted, and a better check than the plan's.

Notes for Planner:
- Browser automation degrades over a long session on this machine: fixed
  now, added to `README.md` "Checking a change".
- Request edit shows nothing when a search succeeds with zero hits: deferred;
  folded into the customer-requests task from the owner's ToDo, which touches
  the same screens.
- Notice sits between the field and the results: accepted as is.

Nothing to redo. Uncommitted; the owner commits.
