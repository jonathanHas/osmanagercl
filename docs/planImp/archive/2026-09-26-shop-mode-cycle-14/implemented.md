# Shop mode cycle 14 — Request detail and edit in Shop mode; retire the board layout — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline
HEAD: 9f7e3837
Pre-existing dirty files: `?? docs/planImp/plan.md` only — the owner has committed
cycles 12, 13 and 13b, so everything else below is mine.

## Steps
### 1. Shared thumbnail: component and script module — done
Changed: `resources/views/components/shop/product-thumb.blade.php` (new),
`resources/js/shop/product-images.js` (new), `resources/js/shop/requests.js`,
`resources/views/shop/partials/request-form.blade.php`, README
```
$ grep -c "hasImage(p) {" resources/js/shop/requests.js        → 0
$ php artisan test --filter="ShopRequestsTest|ShopViewContractTest" → 21 passed
```
`hasImage()` is null-safe (`!! p?.image_url`), which is what lets the picked-row
guard collapse from `picked && hasImage(picked)` to the component's own
`hasImage(picked)`.

### 2. Shared typeahead module — done
Changed: `resources/js/shop/product-typeahead.js` (new), `resources/js/shop/requests.js`
```
$ node -e "... cycle 12 Rev 2 scanner exercise against requests.js ..."
before pickFirst: results 0 | picked null
after  pickFirst: code 5000000000017 | id p1 | query "" | results 0
no hit          : picked null
hasImage url/after-fail/none/null -> true false false false
$ grep -c "route(" resources/js/shop/product-typeahead.js  → 0
```
Behaviour identical to cycle 12, plus `picked` now carries `id` so the thumbnail
can key its failure map.

### 3. Controller view names, and "Details" on the board — done
```
$ git diff app/Http/Controllers/CustomerRequestController.php
-        return view('customer-requests.show', ...   +        return view('shop.request-show', ...
-        return view('customer-requests.edit', [     +        return view('shop.request-edit', [
```
Two lines, nothing else. The actions panel gained a Details link above Edit.

### 4. Detail screen — done
Changed: `resources/views/shop/request-show.blade.php` (new)
The `$logs` computation is the office page's verbatim (flatMap over `statusLogs`,
sorted by `created_at` then id).
```
$ php artisan test --filter="CustomerRequestTest::test_show_page_lists_status_history|ShopViewContractTest" → 15 passed
```

### 5. Edit behaviour: request-edit.js — done
```
canRemove ordered/pending -> false true
statusLabel ordered       -> "Ordered"
after 1st pick: lines 3 | last qty 1
after 2nd pick: lines 3 | last qty 2      (merged, not duplicated)
dup of ordered: lines 4 | ordered qty still 1
addBlank      : lines 5 | keys unique true
unlink(2)     : code "" | product null
after removes : lines 3
empty submit  : prevented true | tooFew true
$ grep -c "route(\|alert(" resources/js/shop/request-edit.js → 0
```
The "dup of ordered" case is mine, not the plan's: a pick matching a line that
has already moved past pending must **not** merge into it, because that line is a
commitment the edit screen may not alter. `onPick()` therefore matches on
`canRemove(item)` as well as the code.

### 6. Edit screen — done
Changed: `resources/views/shop/request-edit.blade.php` (new)
```
$ php artisan test --filter="CustomerRequestTest|ShopViewContractTest" → 33 passed
```
All 18 office tests pass against the new Shop views, including
`test_update_syncs_lines_without_touching_status` and
`test_update_rejects_a_line_from_another_request`. "Keep me" / "Delete me" match
inside the `@js` seed, so the plan's `value` fallback was not needed.

### 7. Retire the board layout and the office partials — done
Eight files deleted and the `customer-requests/` view folder removed:
```
D app/View/Components/BoardLayout.php
D resources/views/layouts/board.blade.php
D resources/views/customer-requests/{show,edit,_form}.blade.php
D resources/views/customer-requests/partials/{request-card,status-buttons,status-pill}.blade.php
```
Checked before deleting that only those files referenced each other, and after:
```
$ grep -rn "board-layout\|BoardLayout\|layouts\.board\|customer-requests\.partials\|customer-requests\._form" app/ resources/ tests/ config/
(nothing)
$ php artisan view:cache   → Blade templates cached successfully
```
The guest card lost `$canManage`, `$statusTone` and `$actionLabels` and the
branches they gated; the board's guest branch passes `$item`, `$request`, `$qty`.

### 8. Tests — done
```
$ php artisan test --filter=ShopRequestsTest
  ✓ guest board is shop styled and read only
  ✓ staff board shows actions and form
  ✓ board panel links to details
  ✓ detail page is shop styled and lists lines and history
  ✓ edit page seeds lines and posts to update
  ✓ board layout is gone
  ... 12 passed (114 assertions)
```

### 9. Docs, README, format, build — done
```
$ npm run build                    → ✓ built in 9.24s
$ ./vendor/bin/pint --test --dirty → PASS 2 files
$ grep -rn "board-layout\|BoardLayout" docs/ --include=*.md | grep -v planImp
docs/features/customer-requests.md:21: ... `BoardLayout` is gone.
```
The one hit is the new sentence saying it is gone, not a reference to it.

## Deviations

1. **The shared modules import with a `.js` extension.** The project's convention
   is extensionless (`shop.js` does it eight times), but those are all resolved by
   Vite and never imported by Node. Mine are the first module-to-module imports
   under `resources/js/shop/`, and every check in this cycle is a `node -e`
   exercise — which fails on an extensionless relative import
   (`ERR_MODULE_NOT_FOUND`). The extension is valid ESM, Vite resolves it
   unchanged, and it keeps the modules testable. `shop.js` is untouched.

2. **`searchUrl` had to stop being a getter — and finding out was the one real
   bug in this cycle.** The plan has both modules spread into their caller
   (`...productTypeahead()`). **Object spread invokes getters and copies the
   resulting value**, so `get searchUrl()` ran at construction time, before Alpine
   had given the component a `$root`:
```
TypeError: Cannot read properties of undefined (reading 'dataset')
    at get searchUrl (product-typeahead.js:18)
    at Module.default (requests.js:16)
```
   It is now a method, `searchUrl()`, with a comment saying why. Anything else
   spread into an Alpine object has the same constraint; the README now records
   it.

3. **`onPick()` will not merge into a line that has moved past pending.** The plan
   says to merge "when an existing line has the same `product_code` and is new or
   pending"; I read that as the `canRemove()` condition and tested it, because
   adding to an already-ordered line would change a commitment the edit screen is
   not allowed to touch.

4. **`composer dump-autoload` was needed after deleting `BoardLayout`**, and the
   test asserts `class_exists(..., false)`. Without the dump, the stale classmap
   made `class_exists()` fatal on the missing file; without the `false`, the test
   would be asserting the classmap is fresh rather than that the class is gone.

## Verification

1. `php artisan route:list --name=customer-requests` → **unchanged**: index
   `PUBLIC`, every write `auth` + `customer-requests.manage`.

2. `php artisan test --filter="Shop|CustomerRequest"` → **pass**, `190 passed`
   before the new tests, `196` after. The contract test lists 14 screens (the two
   new ones included).

3. `php artisan test` → **pass**: `Tests: 17 failed, 566 passed (2309 assertions)`.
   Cycle 13b ended at 17 / 560; +6 is the six new tests. The 17 are the identical
   pre-existing set:
```
  3 CashReconciliationTest   2 FruitVegLabelPrintingTest   2 ProductTest
  1 TestScraperControllerTest   2 WasteLogTest   7 UdeaScrapingServiceTest
```

4. Controller diff → two view-name lines. `git status --short` → the eight
   deletions. **pass**

5. `grep -rn "<script\|<style" resources/views/shop/` → nothing;
   `grep -c "route(\|fetch(\|alert(" resources/js/shop/request-edit.js` → 0;
   design block `cmp` identical; `view:cache` succeeded then cleared. **pass**

6. `pint --test --dirty` → `PASS 2 files`; `npm run build` → `✓ built in 9.24s`.
   **pass**

7. Manual — **not run.** Chrome is available and I can drive it, but this needs a
   throwaway request created and deleted, and the interesting parts write: adding
   a line, scanning the same barcode twice to see the merge, saving. The
   automated side is well covered (both office update tests pass from the Shop
   page, and the edit test does a real PUT and checks the line was renamed, the
   count, and that the status did not move). Unobserved: the two screens' layout,
   the thumbnail in a seeded line, the scan-to-add merge on a real scanner, and
   the `sprout` placeholder on free-text lines. Say the word.

## Files changed

```
D  app/View/Components/BoardLayout.php                       (step 7)
D  resources/views/layouts/board.blade.php                   (step 7)
D  resources/views/customer-requests/ (6 files, folder gone)  (step 7)
 M app/Http/Controllers/CustomerRequestController.php        (step 3)
 M resources/js/shop.js                                      (step 5)
 M resources/js/shop/requests.js                             (steps 1, 2)
 M resources/views/shop/partials/request-card.blade.php      (step 7)
 M resources/views/shop/partials/request-form.blade.php      (step 1)
 M resources/views/shop/partials/request-row.blade.php       (step 3)
 M resources/views/shop/requests.blade.php                   (step 7)
 M tests/Feature/Shop/ShopRequestsTest.php                   (step 8)
 M docs/{features/customer-requests.md,FEATURES_INDEX.md,design/shop-mode/README.md}
?? resources/js/shop/product-images.js                       (step 1)
?? resources/js/shop/product-typeahead.js                    (step 2)
?? resources/js/shop/request-edit.js                         (step 5)
?? resources/views/components/shop/product-thumb.blade.php   (step 1)
?? resources/views/shop/request-show.blade.php               (step 4)
?? resources/views/shop/request-edit.blade.php               (step 6)
```

## Notes for Planner

- **The getter-in-a-spread trap is worth knowing beyond this cycle.** Any future
  shared module intended for `...spread()` into an Alpine object must expose
  methods, not getters, or it will evaluate against a component that does not
  exist yet. `find-product.js` and the others define their getters directly on
  their own object, so they are fine; the constraint only bites shared modules.
  Recorded in the design README.
- **Find product still has its own thumbnail code**, by choice (Out of scope) —
  two implementations for one more cycle. When it is tidied, the hover peek is
  the only part that does not fit `productImages()`, so the peek would need to
  stay local or become a third module.
- **`composer dump-autoload` is required on deploy**, or the stale classmap will
  fatal on the deleted `BoardLayout`. Most deploy scripts run it; worth checking
  `deploy.sh` before this ships.
- **The edit screen has no confirmation on removing a line.** Removal only takes
  effect on Save, and Cancel goes back to Details, so the escape hatch exists —
  but a removed line is gone from the form with no undo short of leaving the page.
  Consistent with the office form it replaces.
- **Nothing committed, pushed or deployed.**
