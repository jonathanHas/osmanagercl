# Shop mode cycle 12 (Revision 2) — scanner pick and product images — implementation

Status: DONE
Plan revision: 2
Implementer: Opus
Date: 2026-09-25

Scope: steps 9–10 only. Steps 1–8 stand as delivered in Revision 1 and were not
reworked.

## Baseline
HEAD: 59aa8413
Working tree carries cycle 12 Revision 1, accepted, uncommitted (20 entries; the
Revision 1 report lists them). Revision 2 touches three files:
`resources/js/shop/requests.js`,
`resources/views/shop/partials/request-form.blade.php`,
`tests/Feature/Shop/ShopRequestsTest.php`.

## Steps
### 9. A scanned barcode picks its product in one motion — done
Changed: `resources/js/shop/requests.js`

`pickFirst()` is now async and runs the search itself when `results` is empty and
the query is not, instead of silently doing nothing. The docblock records why: a
keyboard-wedge scanner sends Enter well inside the 250 ms debounce, so on the till
PC `results` was essentially always empty at that moment.

Check output — the plan's exercise with a stubbed `fetch`:
```
before pickFirst: results 0 | picked null
after  pickFirst: picked.code 5000000000017 | query "" | results 0
no hit          : picked null
```
Exactly the plan's expectations: the product is picked with `results` still empty
beforehand, `query` is cleared, `results` ends empty, and an unmatched code picks
nothing.

Two branches the plan did not ask for:
```
blank query     : picked null | results 0
```
Enter on an empty or whitespace-only box does not fire a search — without the
`query.trim() !== ''` guard it would have queried the API on every stray Enter.

### 10. Product images in the item search — done
Changed: `resources/views/shop/partials/request-form.blade.php`, `resources/js/shop/requests.js`, `tests/Feature/Shop/ShopRequestsTest.php`

`hasImage()` / `imageFailed()` and a `failed` map ported from `find-product.js`
verbatim in behaviour; the thumbnail and `shop-row__lead` placeholder pair copied
into both the result rows and the picked row. `pick()` carries `image_url` onto
`picked`. No hover peek, per the plan. No stylesheet change — `.shop-thumb` is
already an `APP ADDITIONS` rule from cycle 5.

Check output:
```
hasImage with url   -> true
hasImage after fail -> false
hasImage no url     -> false
picked image_url    -> http://x/a.jpg     (pick() carries it through)

$ php artisan test --filter="ShopRequestsTest|ShopViewContractTest"
  Tests:    17 passed (184 assertions)
```
`staff_board_shows_actions_and_form` now also asserts `class="shop-thumb"` and
`x-on:error="imageFailed(p)"`.

### Build and format — done
```
$ npm run build                    → ✓ built in 8.00s
$ ./vendor/bin/pint --dirty        → PASS 8 files
$ ./vendor/bin/pint --test --dirty → PASS 8 files
```

## Deviations

None. Both steps went in as written.

One addition inside the plan's intent: `pickFirst()` guards on
`query.trim() !== ''` as well as on `results` being empty, so an Enter in an empty
search box is a no-op rather than an API call. The plan's wording implies it ("if
`results` is empty and the trimmed query is not"); recording it because the check
for it is mine.

## Verification (Revision 2)

1. `php artisan route:list --name=customer-requests` → unchanged from Revision 1;
   this revision touches no routes.

2. `php artisan test --filter="Shop|CustomerRequest"` → **pass**,
   `Tests: 184 passed (786 assertions)`. Same 184 tests as Revision 1, two more
   assertions — the two thumbnail assertions added to an existing test.

3. `php artisan test` → **pass**: `Tests: 17 failed, 556 passed (2196 assertions)`.
   Identical pass count to Revision 1 (no new tests this revision) and the same 17
   pre-existing failures:
```
  3 Tests\Feature\CashReconciliationTest
  2 Tests\Feature\FruitVegLabelPrintingTest
  2 Tests\Feature\ProductTest
  1 Tests\Feature\TestScraperControllerTest
  2 Tests\Feature\WasteLogTest
  7 Tests\Unit\UdeaScrapingServiceTest
```

4. `git diff --stat` on the controller and service → `6 ++-` and `48 +++…`,
   **byte-identical to Revision 1**: steps 9–10 are view and JS only.

5. `grep -rn "<script\|<style" resources/views/shop/` → no output;
   `grep -c "route(" resources/js/shop/requests.js` → 0;
   `head -c ... | cmp` → `DESIGN-BLOCK-IDENTICAL`. **pass**

6. `./vendor/bin/pint --test --dirty` → `PASS 8 files`; `npm run build` →
   `✓ built in 8.00s`. **pass**

7. Manual — **not run**, same position as Revision 1. Step 9 is precisely the path
   a test cannot prove: the node exercise shows `pickFirst()` picks when `results`
   is empty, but whether a real keyboard-wedge scanner beats the debounce on the
   till PC can only be seen with the scanner. If you want one walkthrough of this
   cycle, that is the step to spend it on — scan a barcode into the Item box and
   check the product is picked and the form is **not** submitted. I have browser
   access; creating a request writes, so I did not start unasked.

## Files changed

Revision 2 touched three files, all already mine from Revision 1:
```
?? resources/js/shop/requests.js                          (steps 9, 10)
?? resources/views/shop/partials/request-form.blade.php   (step 10)
?? tests/Feature/Shop/ShopRequestsTest.php                (step 10: two assertions)
```
The `??` marks are because Revision 1's files are still uncommitted. Everything
else in `git status --short` is cycle 12 Revision 1.

## Notes for Planner

- **`pickFirst()` awaits a network round trip on the Enter keystroke.** On a slow
  connection the person presses Enter and nothing happens for as long as the
  search takes; there is no busy indicator on that path (`searching` exists but
  the form does not render it). Find product has the same gap. A spinner or a
  disabled state on the search box would be the cheapest fix, and it is more
  noticeable here because Enter is the scanner's own keystroke.
- **The picked row's image is null for a bounced submission.** `seedItems` carries
  `product_code` and `product_name` but not `image_url`, so after a failed submit
  the re-seeded pre-order shows the package placeholder even though the product
  has a photo. Harmless and self-correcting once they re-pick; noting it because
  it is the one place the thumbnail is knowingly absent.
- **Three copies of the thumbnail pattern now exist** (`find-product.blade.php`,
  `request-form.blade.php`, and `hasImage`/`imageFailed` in two JS modules). The
  next screen that needs a product row would make it four. An `x-shop.product-row`
  component and a small shared module would pay for themselves about now.
- **Nothing committed, pushed or deployed.**
