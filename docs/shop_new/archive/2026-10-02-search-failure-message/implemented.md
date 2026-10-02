# Product search: say when it failed, and let staff retry — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-10-02

## Baseline
HEAD: 9247c79e
Pre-existing dirty files (the accepted delivery follow-ups and track docs):
```
 M docs/features/shop-mode.md
 D docs/planImp/findings/2026-09-30-summary-units-float.md
 M docs/shop_new/README.md
 D docs/shop_new/implemented.md
 M docs/shop_new/plan.md
 M resources/js/shop/delivery-scan.js
 M resources/views/shop/delivery-scan.blade.php
 M tests/Feature/Shop/ShopDeliveryTest.php
?? docs/shop_new/archive/
?? docs/shop_new/findings/
```

## Steps

### 1. The typeahead records a failure and ignores stale answers — done
Changed: `resources/js/shop/product-typeahead.js`.
- State `searchError: null`, `searchSeq: 0`; getter `searchMessage` with the
  plan's two sentences.
- `search()` takes `const seq = ++this.searchSeq` first (also for the
  empty-query early return, so an answer still in flight for the previous text
  cannot refill a cleared list). After the `fetch` and again after
  `response.json()`, and in `catch`, it returns without writing if
  `seq !== this.searchSeq`.
- `! response.ok`: 401/419 → `'signed-out'`, any other → `'failed'`;
  `results = []`, `total = 0`, body not parsed. Thrown error → `'failed'`.
  Good answer → `searchError = null`.
- `finally` resets `searching` only when `seq === this.searchSeq`.
- Empty-query return and `pickResult()` set `searchError = null` (the early
  return also sets `searching = false`, since it is now the latest call).
- Header comment explains `searchError`, `searchMessage` and the sequence.
- New test `ShopRequestsTest::test_the_typeahead_reports_a_failed_search`
  (source assertions: `searchError`, `'signed-out'`, `response.ok`,
  `searchSeq`; it also covers step 3's pages).
```
✓ built in 8.56s
```

### 2. Delivery "Find by name": message, retry, cloaked button — done
Changed: `delivery-scan.blade.php`. After the filter's `.shop-search`
wrapper: `<p class="shop-notice" x-show="searchError" x-cloak>` with the
`alert` icon (`sm`) and `<span x-text="searchMessage"></span>`, and
`<button class="shop-btn shop-btn--ghost" type="button" x-show="searchError === 'failed'" x-cloak x-on:click="search()">Try again</button>`.
"No products match" gains `&& ! searchError`. The "No barcode?" button gains
`x-cloak`. `delivery-scan.js` `closeManual()` sets `searchError = null`
(plus two lines, see Deviations).
New test `ShopDeliveryTest::test_find_by_name_says_when_the_search_failed`
(asserts `x-text="searchMessage"`, `Try again`, the `'failed'` x-show, the
"No products match" condition, and the exact cloaked button tag).

### 3. The same message on New request and request edit — done
Changed: `resources/views/shop/partials/request-form.blade.php` (after the
`.shop-search` div: notice `x-show="! picked && searchError"`, button
`x-show="! picked && searchError === 'failed'"`) and
`resources/views/shop/request-edit.blade.php` (same, without the `picked`
rule, since that screen has none). Both buttons are `type="button"` with
`x-on:click="search()"`.
```
php artisan test tests/Feature/Shop → Tests: 290 passed (1403 assertions)
```

### 4. Documentation — done
Changed: `docs/features/shop-mode.md`, "Deliveries: items without a
barcode". One sentence says the button appears once the page is ready
(`x-cloak`). A short paragraph says that a failed search, on all three screens,
shows "Could not load products." with Try again, or the signed-out sentence
for 401/419, and that only the latest search writes results.

## Deviations

- `closeManual()` also does `this.searchSeq++` and `this.searching = false`.
  The plan asked only for `searchError = null`. Without the bump, a search
  still in flight when the list is closed would refill `results` behind the
  closed card. Bumping makes that answer stale, and a stale call no longer
  resets `searching`, so close resets it itself.
- Verification 6 as the signed-in admin (`jonathanE`), as in earlier cycles.
- Verification 6f/6g input: partway through the pass the automation tab's
  renderer stalled (screenshots timed out after 30 s, `visibilityState`
  `hidden`, no rAF), and coordinate clicks and typed keys stopped reaching
  the page. I measured this with listeners: zero `keydown`/`input` events
  for a typed "ch". From that point:
  - 6f: the New request sheet was opened by setting its Alpine `open = true`
    (the "New request" button click did not register). Typing `che` and the
    Enter press **did** still arrive at that point. "Try again" and the result
    pick were DOM `click()`s.
  - 6g: the search value was set and a real `input` event dispatched from
    script (this drives the same `x-model` + 250 ms debounce + `search()`
    path); "Try again" was a DOM `click()`.
  What is under test (the typeahead's state, the bindings, and the buttons'
  handlers and `type`) ran in the page as normal.

## Verification

1. `./vendor/bin/pint --dirty` → fixed `ShopDeliveryTest.php`
   (`single_quote, unary_operator_spaces`), only in lines this track added
   (the new assertion strings, now single-quoted). Both touched test files
   rerun after: `Tests: 68 passed (415 assertions)`.
2. `npm run build` → `✓ built in 7.56s`.
3. Design-block `cmp` → exit 0, no output.
4. `php artisan test tests/Feature/Shop` → `Tests: 290 passed (1403 assertions)`.
5. `php artisan test` → `Tests: 15 failed, 939 passed (4163 assertions)`.
   The same 15 as baseline (UdeaScrapingServiceTest ×7,
   CashReconciliationTest ×3, FruitVegLabelPrintingTest ×2, ProductTest ×2,
   TestScraperControllerTest ×1); 937 + 2 new = 939.
6. Browser, dev, Mossfield session `778fb73c-a674-4533-8160-3fe9187f4bb1`
   (read only). `fetch` was wrapped per check; console had no errors apart
   from the injected failures (none were logged as errors: the typeahead
   catches them).
   a. Opened "Find by name": `results: 10`, `searchError: null`, notice and
      Try again hidden. ✓
   b. Offline (search URL rejected), closed and reopened: `searchError:
      "failed"`, notice "Could not load products.", Try again shown, "No
      products match" hidden, `searching: false` (also in the screenshot).
      Restored, tapped Try again: 10 results, notice gone. ✓
   c. 401 JSON: "You have been signed out. Reload the page to sign in.",
      `searchError: "signed-out"`, no Try again, no "No products match"
      (screenshot). ✓
   d. 500 JSON: "Could not load products." with Try again. ✓
   e. Stale answer. First run: `sl` (delayed 1.5 s) then `slieve`. The final
      list was `["Moss Slieve Bloom"]`, but `sl` returns the same single
      product, so that run could not tell them apart. Second run: `m`
      (delayed 1.5 s; on its own it returns **10** products) then `mature`.
      Requests `["m","mature"]`. At +0.7 s `searching: true` (the mature call
      was in flight). At +2.7 s, after the late `m` answer had arrived,
      `results: ["Mossfield Mature"]`, `searching: false`. ✓
   f. `/customer-requests` New request, offline, typed `che`: notice "Could
      not load products.", Try again shown, `type="button"`. Enter while
      offline (`pickFirst`): `picked: null`, no navigation. Restored, Try
      again: `results: 20`, notice gone, form `submit` not fired. Picked the
      first result ("Cheddar hart Godminster 200g"): notice and Try again
      hidden. With a product picked and `searchError` forced to `'failed'`,
      the notice stays hidden (the `! picked` rule). Not submitted. ✓
   g. `/customer-requests/4/edit`, offline, search `cheddar`:
      `searchError: "failed"`, notice "Could not load products." and Try
      again `display: flex` (Alpine reveals `x-show` via `setTimeout` in a
      hidden tab, which Chrome was throttling, so the reveal came a few
      seconds late). Restored, Try again: 9 results, notice hidden. Not
      saved. ✓
   h. The served scan page has
      `<button class="shop-btn shop-btn--ghost" type="button" x-show="! manual" x-cloak x-on:click="openManual()">`
      and the layout's `[x-cloak]{display:none!important}` rule. After load
      the button is visible (6a: `visible: true`; Alpine removes the
      attribute once it starts). ✓
7. Dev state: nothing written. The Mossfield session `778fb73c` still has 0
   scan rows. The New request sheet was not submitted, and request 4's edit
   page was not saved.

## Files changed

This task:
```
 M docs/features/shop-mode.md                          (also dirty at baseline)
 M docs/shop_new/implemented.md
 M resources/js/shop/delivery-scan.js                  (also dirty at baseline)
 M resources/js/shop/product-typeahead.js
 M resources/views/shop/delivery-scan.blade.php        (also dirty at baseline)
 M resources/views/shop/partials/request-form.blade.php
 M resources/views/shop/request-edit.blade.php
 M tests/Feature/Shop/ShopDeliveryTest.php             (also dirty at baseline)
 M tests/Feature/Shop/ShopRequestsTest.php
```
Untouched baseline entries: the `docs/planImp/findings/…` deletion,
`docs/shop_new/README.md`, `docs/shop_new/plan.md`, `docs/shop_new/archive/`,
`docs/shop_new/findings/`.

## Notes for Planner

- **Browser automation on this machine degrades over a session.** The tab
  reports `visibilityState: hidden` from the start (no rAF). After about
  half an hour, screenshots time out and clicks and keys stop reaching the
  page, and Alpine's `x-show` reveal (a `setTimeout` when hidden) is
  throttled to seconds. Worth a line in README "Checking a change": prefer
  state reads over screenshots, and expect to fall back to DOM events late in
  a pass. A fresh tab helped earlier in the day.
- `request-edit.blade.php`'s search has no "No products match" line, so on
  that screen a search that succeeds with zero hits still shows nothing. Out
  of scope here (the plan only covered failures), but it is the same "looks
  like nothing" problem for a different cause.
- The notice and Try again sit between the search field and its results on
  all three screens. On the New request sheet this pushes the results down
  by one line while an error shows. That is fine, since the results are empty
  at that point anyway.
