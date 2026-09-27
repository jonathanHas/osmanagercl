# Shop mode cycle 25 — Delivery tidy-ups — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-27

## Baseline

HEAD: ea9db126; cycle 24 accepted but uncommitted, so the tree carries it and
`git diff` is cumulative with it. Test baseline: 15 failed / 670 passed.

## Steps

### 1. Stock formatting on the prompt — done

`stockText(row)` → `stockText(value)`, taking the figure rather than the row,
because the prompt has it on `pending.product.currentStock` and rows have it on
`row.stock`. All three call sites updated.

Checked in the browser on the product the cycle-24 report named:
```
raw pending.product.currentStock: 1.5200000000000011
the prompt's facts:  ["Scanned so far 0", "Invoice 1", "In stock 1.52", "Outer Case of 1"]
stockText(1.52000…)=1.52   stockText(8)=8   stockText(0)=0   stockText(null)=0
```

### 2. Close the correction card when its row is gone — done

Four lines in `load()`, no extra round trip.

Exercised on the Coolfin session against a real unexpected row:
```
before:  3 rows, "200 gram Cottage drops" scanned 1, card open on it
press −: rows 3 → 2, the row gone from the JSON
         editing null, editingRow null, card hidden
```
Before this change the card sat there with blank content.

**Dev data restored**, as the plan requires: I rescanned `7640166798880` and added
1, and the session is back to 3 rows at 1 / 1 / 27 — the figures it had.

### 3. Doc comment placement — done

```
    get pendingProduct() { … }
    /** The row the correction card is editing, or null. */
    get editingRow() { … }
    /**
     * "New first" puts the items just scanned at the top …
     */
    get sorted() {
```

### 4. Indentation — done

The `shop-stack--tight` block inside `shop-inline` is re-indented one level.
`git diff -w` on that block shows nothing — the non-whitespace lines in the file's
diff are cycle 24's, which is uncommitted underneath.

### 5. Prune keep-set and the shared window — done

`CustomerRequestService::RECENT_DAYS = 30` now serves all four places: the Done
view, its counts, the public photo route's gate (`PHOTO_WINDOW_DAYS` deleted) and
the prune's keep-set. `currentPhotoProducts()` gains request-line codes and
recent-delivery barcodes, each skipped when empty, with `dateUpload` compared as a
formatted string as the plan's risk notes. The command's description and class
comment no longer say "F&V" — its name is kept because `ScheduleTest` pins it.

**The measurement the plan asked for**, on dev:
```
products kept: 406 in 196.6 ms
peak memory:  48.5 MB → 86.5 MB (delta 38 MB)
blob bytes held: 19.6 MB
```

Tests: two new ones in `FruitVegProductImageTest`, using products deliberately
outside the old keep-set (`CATEGORY = 'GROCERY'`, no Jon link) — the fixture's own
`product()` helper hard-codes `SUB1`, which *is* an F&V category, so using it would
have made the tests pass for the wrong reason.

**Both were proved to fail on the old behaviour**: reverting the keep-set to
`[$jonCodes]` alone gives
```
⨯ the prune keeps thumbnails for recent deliveries and request lines
    A product scanned on a recent delivery is in use.
⨯ the prune does not delete the files it keeps
    The thumbnail for a recently scanned product must survive the prune.
```
and restoring it gives 2 passed.

### 6. Format and tidy — done

`pint --test --dirty` PASS; `npm run build` ✓; `view:clear`.

## Verification

**1. `php artisan test`**
```
Tests:    15 failed, 672 passed (2885 assertions)
```
The identical 15 (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2,
Product ×2, TestScraper ×1). 672 = 670 + 2.

**2. Design block `cmp`** prints nothing; no CSS change was needed.

**3. Browser:** items 1 and 2 above. Rows and the correction card still read
`Stock N` — the Coolfin rows show `Stock 3`, `Stock 3`, `Stock 53`. Console clean.

**4. The prune on dev.** 121 cached files; the keep-set covers 120. I worked out
exactly which file it wants to delete rather than reading a count:
```
orphans the prune would delete: 1
  86a284e58fb5-112-9de1a90a381a.jpg -> 5412533420791
2015 (a current request line) kept: true
```

**The plan's item 4 cannot pass as written, and the plan's example is the error.**
It asks me to confirm the thumbnail for `5412533420791` is **kept**. It is not, and
should not be: that product's only delivery session is dated **30 July**, well
outside the 30-day window. Its thumbnail exists because cycle 24's browser check
opened that old session, so it is a genuine orphan. The dev sessions inside the
window are:
```
4149c0a2 sup=5  2026-09-26 20:05:28
da16f9e0 sup=5  2026-09-24 16:30:36
93cb99e5 sup=48 2026-09-23 22:24:57
c3c6b841 sup=37 2026-08-13 09:42:19   ← outside
97ed8ced sup=28 2026-08-10 16:45:03   ← outside
```

I could not run the prune to completion as `jon` (the folder is `www-data`, cycle
17g), and the manage-page button did not submit on the click I gave it — the file
count was unchanged afterwards. The accounting above is from computing the expected
filenames directly, which answers the same question without needing the delete to
succeed.

**5. `php artisan schedule:list`** still shows
`30 5 * * 0  php artisan fruit-veg:prune-thumbnails`.

## Deviations

None.

## Files changed

```
 M app/Console/Commands/PruneFruitVegThumbnails.php      wording only
 M app/Http/Controllers/CustomerRequestController.php    PHOTO_WINDOW_DAYS → RECENT_DAYS
 M app/Services/CustomerRequestService.php               RECENT_DAYS, two literals replaced
 M app/Services/ProductThumbnailService.php              keep-set widened
 M resources/js/shop/delivery-scan.js                    items 1, 2, 3
 M resources/views/shop/delivery-scan.blade.php          items 1, 4
 M tests/Feature/FruitVegProductImageTest.php            delivery tables + 2 tests
```
Cycle 24's files are also dirty in this tree, uncommitted; I reverted nothing.

**Not committed, not pushed, not deployed.**

## Notes for Planner

1. **The widened keep-set changes nothing on the current dev data, and the tests are
   the only proof it works.** The one product on a request line that has a photo is
   `2015`, Ginger — category `SUB2`, so it was already kept as fruit & veg. The
   count is 406 before and after. The fix is right; dev simply has no non-F&V
   product that is both photographed and in use. Worth knowing before judging it by
   the numbers.

2. **The keep-set covers products *scanned* on a recent delivery, not products on
   its *invoice*.** The scan page draws a picture for every row it shows, and most
   rows on a delivery with invoice lines have never been scanned — 163 of the 164 on
   the Mossfield session. So on a *recent* session with invoice lines, the Sunday
   prune still throws away those thumbnails. Dev cannot show this: the three sessions
   inside the window belong to suppliers 5 and 48, which have **no** invoice lines at
   all, so the query returns 0 barcodes there. Extending it means joining `delivery`
   to `supplier_link` by the supplier of a recent session, since `delivery` carries no
   session id. I did not, because it is a different query from the one the plan
   specifies and would have gone in unmeasured.

3. **196 ms and +38 MB for `currentPhotoProducts()`** — the plan asked for these.
   Both are dominated by the 19.6 MB of blobs the prune needs to compute expected
   filenames, not by the two sets I added (5 request codes, 4 delivery barcodes on
   dev). It runs weekly and on a button, so this is fine, but it is the figure to
   watch if the keep-set ever widens much further: the method loads every kept
   product's photo into PHP.

4. **The manage-page prune button did not submit** when I clicked it this time,
   where it worked in cycle 17g. The file count was unchanged and no flash appeared.
   I did not chase it, because the prune's behaviour was verifiable another way and
   the button is not this cycle's subject — but it is worth a look, since it is the
   only way the owner can clear the cache.
