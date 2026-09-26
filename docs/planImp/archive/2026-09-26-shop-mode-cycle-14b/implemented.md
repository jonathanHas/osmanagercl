# Shop mode cycle 14b — compose shared Alpine modules without the getter trap — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline
HEAD: 9f7e3837
Pre-existing dirty files: cycle 14, accepted, still uncommitted (27 entries).

## Steps
### 1. `mix()` — done
Changed: `resources/js/shop/mix.js` (new), verbatim from the plan.
```
a stayed an accessor                  -> true   (expect true)
o.a resolves via this                 -> 1      (expect 1)
o.c()                                 -> 2      (expect 2)
mix() with a throwing getter threw    -> false  (expect false: lazy)
reading it threw                      -> true   (expect true)
later part overrides                  -> 2      (expect 2)
```

### 2. Use it, and restore the getter — done
Changed: `resources/js/shop/product-typeahead.js`, `resources/js/shop/requests.js`, `resources/js/shop/request-edit.js`
```
$ grep -c "\.\.\.product" resources/js/shop/*.js   → 0 in every file
```
Both screen modules are now `mix(productImages(), productTypeahead(), { … })`
with their own members, getters included, unchanged.

The exercises construct the component **first** and attach `$root` afterwards —
the exact shape that broke in cycle 14:
```
searchUrl is still an accessor -> true
after pickFirst: code 5000000000017 | id p1 | query ""
searchUrl resolves on the live component -> "/api/products/search"
edit searchUrl accessor -> true
canRemove ordered/pending -> false true
merge: lines 3 | last qty 2
dup of ordered: lines 4 | ordered qty 1
hasImage url/after-fail/null -> true false false
failed maps are per-component -> true
```
Every figure matches cycle 14. The last line checks the plan's Risk: descriptor
copying still gives each component its own `failed` map.

### 3. Edit screen placeholder icon — done
Changed: `resources/views/shop/request-edit.blade.php`, `tests/Feature/Shop/ShopRequestsTest.php`
A seeded stocked line (which has `product_code` but no image) now shows `package`;
only a free-text line shows `sprout`. `edit_page_seeds_lines_and_posts_to_update`
asserts both `#package` and `#sprout` are on the page.

### 4. README, build, format — done
```
$ grep -n "getter" docs/design/shop-mode/README.md
67: ... compose them with `mix()` ... so shared parts may use getters like any
    other Alpine data; never spread a shared part into a data object.
$ npm run build                    → ✓ built in 8.73s
$ ./vendor/bin/pint --test --dirty → PASS 2 files
```
I also corrected the earlier half of that same sentence, which still said the
component was for "any Alpine scope that spreads `productImages()`" — it would
have contradicted the new rule in the same line.

## Deviations

1. **Fixed a console-error regression I introduced in cycle 14.** Not in the
   plan's steps; found by this cycle's own manual check. The `product-thumb`
   component I wrote last cycle dropped the optional chaining the markup it
   replaced had:
```
Alpine Expression Error: Cannot read properties of null (reading 'image_url')
Expression: "picked.image_url"   img.shop-thumb
TypeError: Cannot read properties of null (reading 'id')
    at Proxy.imageFailed ...
```
   `x-show="hasImage(picked)"` is null-safe and correctly hides the element, but
   `x-show` only toggles display — `:src`, `:alt` and `x-on:error` still evaluate,
   and `picked` is null until something is picked. Confirmed against git that the
   pre-cycle-14 markup read `:src="picked?.image_url"`:
```
$ git show HEAD:resources/views/shop/partials/request-form.blade.php | grep picked
<img class="shop-thumb" x-show="picked && hasImage(picked)" :src="picked?.image_url" ...
```
   The component now uses `{{ $expr }}?.image_url` / `?.name`, and
   `imageFailed()` returns early on a null product. Three errors and three
   exceptions per board load, gone. I judged this in scope because it is a defect
   in the very component this cycle is tidying, it removes errors rather than
   changing behaviour, and leaving it while looking straight at it would have been
   worse.

2. **One extra sentence corrected in the README** (step 4, above).

## Verification

1. `node --check` clean on `mix.js`, `product-typeahead.js`, `requests.js`,
   `request-edit.js`; both exercises pass (outputs under steps 1–2). **pass**

2. `php artisan test --filter="Shop|CustomerRequest"` → **pass**, `194 passed
   (901 assertions)`. No test changed except the step 3 assertion.

3. `php artisan test` → **pass**: `Tests: 17 failed, 566 passed (2311 assertions)`.
   Pass count unchanged at 566 exactly as the plan predicts. Same 17.

4. `grep -c "route(" resources/js/shop/mix.js resources/js/shop/product-typeahead.js`
   → 0 and 0; design block `cmp` identical; no `<script>`/`<style>` in shop views.
   **pass**

5. `npm run build` → `✓ built in 8.73s`. **pass**

6. Manual — **RUN, 2026-09-26, signed in.** Opened the board, tapped "New
   request", typed "oatly" in Item: **four results with their thumbnails**. That
   is the check that matters — the typeahead can only fetch if `searchUrl`
   resolved lazily against the live component, which is the whole point of
   `mix()`. Console after a fresh load with the sheet opened:
```
$ read_console_messages (Error|TypeError|Alpine Expression)
No console messages found for this tab.
```
   Clean, where the same page produced six errors before Deviation 1's fix.
   Nothing was written: I only opened the sheet and typed. Not observed: the edit
   screen's package/sprout icons in the browser (asserted in the test instead).

## Files changed

```
?? resources/js/shop/mix.js                                 (step 1)
 M resources/js/shop/product-typeahead.js                   (step 2)
 M resources/js/shop/requests.js                            (step 2)
 M resources/js/shop/request-edit.js                        (step 2)
 M resources/js/shop/product-images.js                      (Deviation 1)
 M resources/views/components/shop/product-thumb.blade.php  (Deviation 1)
 M resources/views/shop/request-edit.blade.php              (step 3)
 M tests/Feature/Shop/ShopRequestsTest.php                  (step 3)
 M docs/design/shop-mode/README.md                          (step 4)
```
Everything else dirty is cycle 14.

## Notes for Planner

- **`x-show` does not stop the other bindings evaluating.** That is what made
  Deviation 1 possible and it is worth remembering wherever a Shop view guards an
  element whose attributes dereference something nullable — the guard hides it,
  it does not skip it. `x-if` on a `<template>` does skip, which is why the edit
  screen's thumbnail (wrapped in `<template x-if="item.product">`) never had the
  problem.
- **Only two modules compose anything today**, so `mix()` has two callers. It
  earns its place by removing a footgun rather than by saving lines; if a third
  screen ever needs `productImages()` it costs nothing.
- **The `x-data` attribute is not live state.** While debugging I read
  `{ open: false }` off the sheet's `x-data` and briefly took the sheet to be
  closed when it was open — that string is the initial literal Blade rendered.
  Checking computed style or the Alpine proxy is the reliable way; noting it in
  case a later cycle's manual check does the same.
- **Nothing committed, pushed or deployed.**
