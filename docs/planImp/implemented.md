# Shop mode cycle 17e — Versioned thumbnail URLs so warm opens cost nothing — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: 030e3e14, 39 dirty paths — cycles 15, 17, 17b, 17c, 17d (mine, archived but
uncommitted) and the parallel delivery-row session. Files changed at the end is
exact.

Test baseline from 17d: 15 failed / 611 passed (2555 assertions).

Warm-open baseline to beat, measured in 17d with the tab in the foreground:
**95 requests, 28 kB, all 304.**

## Pre-flight

The route is as the plan describes. Two things it does not mention that the change
has to account for, both found by reading `productImage()` rather than assuming:

1. **`$imageData` is reassigned to the thumbnail**, so the version has to be
   computed from a separately held copy of the original blob, not from
   `$imageData` at the point of the check.
2. **There are two places that emit `Cache-Control`** — the 200 and the
   `If-None-Match` 304 — and the plan only describes the 200. If the 304 kept
   `max-age=0, must-revalidate`, a client that revalidated once would be downgraded
   out of the week-long lifetime and go back to asking every time, which is the
   exact cost this cycle exists to remove. I build the header once and use it in
   both. Recorded under Deviations.

## Steps

### 1. Rows carry the version — done

Changed: `app/Http/Controllers/WasteController.php`,
`app/Http/Controllers/HarvestController.php`,
`tests/Feature/Shop/ShopFruitVegTest.php`.

Each controller gained a small private `imageUrl(Product $product)` rather than
growing the expression inline — the row arrays are already dense, and the reason
the version exists needs a comment somewhere the next reader will find it.

The Shop test's fake blob became a named constant with an `expectedImageUrl()`
helper, so the three assertions derive the version the same way the controller does
instead of hard-coding a hash.

```
$ php artisan test --filter=ShopFruitVegTest
Tests:    10 passed (83 assertions)
```

### 2. The route honours a matching version — done

Changed: `app/Http/Controllers/FruitVegController.php` (`productImage()` only),
`tests/Feature/FruitVegProductImageTest.php` (extended — and this time I read it
first).

```
$ php artisan test --filter=FruitVegProductImageTest
✓ a matching version on a thumbnail caches for a week
✓ a wrong version is treated as absent
✓ a version without a size changes nothing
✓ a product without an image ignores the version
✓ a revalidated versioned thumbnail keeps the long lifetime
✓ replacing the photo changes the version and the old one stops matching
  ... plus the 11 from 17d and earlier
Tests:    17 passed (114 assertions)
```

Two of those go beyond the plan's list and are the ones I would not want missing:

- **`a revalidated versioned thumbnail keeps the long lifetime`** covers the 304
  branch. Without it a client that revalidated once would be handed
  `max-age=0, must-revalidate` and go back to asking every time.
- **`a wrong version is treated as absent`** tries four kinds of wrong, including
  the correct hash in **uppercase** — `hash_equals` is case-sensitive, so an
  uppercased link is correctly treated as a miss rather than accidentally matching.

One assertion I wrote was wrong and the code was right. I had asserted that after
replacing a photo, the stale-`v` response and the fresh-`v` response would have
different ETags. They do not, and should not: a wrong version is treated as absent,
so **both** serve a thumbnail of the *current* photo. The test now asserts the thing
that actually matters — the bytes differ from what the browser held before the
swap, and the stale link shows the current picture rather than the old one.

### 3. Docs, format — done

Changed: `docs/features/fruit-veg-system.md` — a paragraph on `v`, the week-long
lifetime, the 304, the must-match rule, and why versioning is thumbnails-only.
```
$ ./vendor/bin/pint --test --dirty → PASS 14 files
```

## Verification

**1. Tests**
```
$ php artisan test --filter="Shop|FruitVegProductImage|ProductThumbnail"
Tests:    216 passed (989 assertions)

$ php artisan test
Tests:    15 failed, 617 passed (2603 assertions)
```
15 failed, the identical set (Udea ×7, CashReconciliation ×3,
FruitVegLabelPrinting ×2, Product ×2, TestScraper ×1). 617 = 611 + the 6 new tests.

**2. `git diff app/Http/Controllers/FruitVegController.php`** — three hunks: the
`use` line, and two inside `productImage()`. Every deleted line is one I replaced;
I read them rather than trusting the hunk headers.

**3. Manual, dev app, tab rendering throughout (the 17c lesson applied — every
measurement below follows a screenshot that forces a render).**

*Cold open*, browser cache empty for these URLs because the `v` parameter makes
them new:
```
95 image requests, 339,285 bytes (331 kB), largest 5.9 kB, median 3.5 kB
Cache-Control: immutable, max-age=604800, public
all 95 loaded, every URL carrying v=<8 hex>
```

*Re-open*, after navigating to Harvest and back — the way staff actually return to
a screen:
```
95 image entries, 0 bytes transferred, all 95 rendered
deliveryType:    { "cache": 95 }
responseStatus:  { "200": 95 }
```
`deliveryType: "cache"` on every one: not a 304, no network at all. This is the
whole point of the cycle, and the progression across three cycles is:
```
17c   cold 3.15 MB / 87 imgs      warm 95 requests, 28 kB (all 304)
17d   cold 329 kB  / 95 imgs      warm 95 requests, 28 kB (all 304)
17e   cold 331 kB  / 95 imgs      warm 0 requests, 0 bytes
```

*Replacing a photo.* I backed up Apricots' blob to a file, replaced it with a
rotated copy (a visibly different picture, so the screen could be judged by eye),
and reopened:
```
apricots v: a9875577 → da7e3245
95 tiles: 65 served from cache, exactly 1 fetched from the network (3,231 bytes)
```
and the tile visibly showed the upside-down apricots. One photo changed, one image
fetched, everything else untouched — which is the property that makes a week-long
lifetime safe. The original blob was restored afterwards and verified by md5
(`a9875577ca93a2577b699e70f42d148b`, 79,177 bytes, identical to the backup).

*Office pages.* `/fruit-veg/availability`: 50 product images, **none** asking for a
size or a version, natural sizes still the originals (313×161, 500×516, 100×100,
1×1). A full-size image with a real photo answers
`max-age=0, must-revalidate, public` — byte-for-byte the behaviour it had before
this cycle.

## Deviations

**One.** The plan describes the header change on the 200 only. I apply the same
`Cache-Control` to the `If-None-Match` 304 as well, because the two are emitted
from the same method and leaving the 304 at `max-age=0, must-revalidate` would drop
any client that revalidates once straight back into asking every time — the exact
cost this cycle removes. Covered by
`test_a_revalidated_versioned_thumbnail_keeps_the_long_lifetime`.

## Files changed

Mine, this cycle:
```
app/Http/Controllers/FruitVegController.php     productImage(): version check, shared header
app/Http/Controllers/WasteController.php        imageUrl() helper, +v
app/Http/Controllers/HarvestController.php      imageUrl() helper, +v
tests/Feature/FruitVegProductImageTest.php      6 new tests (existing file, extended)
tests/Feature/Shop/ShopFruitVegTest.php         BLOB constant + expectedImageUrl()
docs/features/fruit-veg-system.md               one paragraph
```
`storage/app/private/fv-thumbs/` now holds 96 files — the 95 from 17d plus one for
the replaced-photo test; gitignored, not in the diff. Dev data is as I found it:
no waste or harvest rows today, 406 F&V photos, Apricots' blob restored exactly.

Still in the tree and not mine: cycles 15, 17, 17b, 17c, 17d, and the parallel
delivery-row session.

**Not committed, not pushed, not deployed.**

## Notes for Planner

1. **The full-size URLs are now the only ones still revalidating**, and the office
   pages open 50 of them at a time. They cannot take a week-long lifetime as they
   stand, because the URL carries no hash — that is the asymmetry I flagged in 17d
   and this cycle deliberately only fixed the safe half. If the office pages are
   ever worth the same treatment, the move is to give them `?v=` too (the blob is
   already in memory where those views are built), not to lengthen the unversioned
   lifetime.

2. **`?t=` on this route looks like dead weight.** `productImage()` still honours a
   `t` parameter with a 300-second lifetime, from before any of this. Nothing live
   passes it *to this route*: the only grep hit under `resources/` is in
   `fruit-veg/sales.blade.php.backup`, and it is a false positive (`x-text=`
   matching `t=`). The cache-busting `?t=` pattern is real, but it belongs to
   `products.image` in a different controller
   (`resources/views/products/edit.blade.php:1751`). So this route now has three
   caching paths — versioned, `t`, and neither — and the next reader will wonder
   which applies. Worth removing in housekeeping; I have not, because it is outside
   this cycle and a `t` could be arriving from somewhere I cannot grep, such as a
   bookmark.

3. **The thumbnail cache grows by one file per photo revision and never shrinks.**
   Replacing a photo leaves the old thumbnail behind forever — I created one such
   orphan during this walkthrough. At 3 kB each this is not a problem for years,
   but it is the reason a clearing mechanism will eventually be wanted, and note 3
   of 17d still applies: the folder is `www-data`-owned, so clearing it needs to be
   something the web server does.

4. **Cycle 17's harvest-accumulates-across-units defect is still open** (5.2 kg then
   2 units gives "7.2 unit"). Fifth cycle carrying this note.
