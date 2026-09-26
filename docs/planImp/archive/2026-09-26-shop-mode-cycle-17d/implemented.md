# Shop mode cycle 17d — Thumbnail size on the fruit & veg image route — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: 030e3e14, 34 dirty paths — cycles 15, 17, 17b, 17c (mine, archived but
uncommitted) and the parallel delivery-row session. Files changed at the end is
exact, because `git diff` cannot separate the cycles.

Test baseline from 17c: 15 failed / 594 passed (2480 assertions).

## Pre-flight

**The libraries are there and the v3 API is as the plan describes.** Rather than
trust `composer.json`, I ran the exact call chain:
```
$ composer show intervention/image     → 3.11.3
$ php -m | grep -E '^(gd|exif)$'       → exif, gd
$ ImageManager::gd()->read($png)->cover(112,112)->toJpeg(quality: 80)->toString()
in: 659 bytes png -> out: 886 bytes  image/jpeg  112x112
```

**A correction to cycle 17c's report, which the plan was right to query.** 17c
said the image route sends `Cache-Control: public, max-age=86400` and that a repeat
open therefore makes zero requests. That is the **placeholder** branch. Real images
take the other branch. Measured, not read:
```
has image    image/png | max-age=0, must-revalidate, public | bytes=79177
no image     image/png | max-age=86400, public             | bytes=70
```
So a warm open does not cost nothing: it costs ~87 conditional round-trips that
each come back 304. I reported "zero image requests on reload" in 17c from a
`performance.getEntriesByType('resource')` count, and that measurement did not mean
what I said it meant — I have re-measured it properly in the manual step below.

This makes the cycle **more** worthwhile than the plan claims, not less: without
thumbnails every open of the waste log costs either 3.15 MB cold or 87 revalidation
round-trips warm.

## Steps

### 1. Thumbnail service — done

Changed: `app/Services/ProductThumbnailService.php (new)`,
`tests/Unit/ProductThumbnailServiceTest.php (new)`.

One departure from the plan's key format, for safety rather than taste: the code is
hashed into the path rather than interpolated (`md5(code)-size-md5(blob)`). A POS
`CODE` is free text and would otherwise be a path segment. There is a test for it.

```
$ php artisan test --filter=ProductThumbnailServiceTest
✓ it makes a square jpeg of the requested size
✓ it writes one file per code size and blob
✓ a second call is served from the cache
✓ a replaced photo gets a new cache key
✓ an undecodable blob returns null and stores nothing
✓ an empty blob returns null
✓ a code with path characters cannot escape the folder
Tests:    7 passed (18 assertions)
```
The cache test is stronger than the plan's mtime comparison: it overwrites the
cached file with `not-really-a-jpeg` and asserts that string comes back, which
proves the file is read rather than the image re-encoded, with no timing involved.

### 2. The route takes `?w=` — done

Changed: `app/Http/Controllers/FruitVegController.php` (`productImage()` only),
`tests/Feature/FruitVegProductImageTest.php`.

The magic-byte sniff is now guarded by `$thumbnail === null`, so a thumbnail — a
JPEG this code just produced — is not re-sniffed, and the ETag naturally covers the
bytes actually served.

```
$ php artisan test --filter=FruitVegProductImageTest
✓ without a size it serves the stored blob unchanged
✓ a whitelisted size returns a square jpeg      ✓ the second whitelisted size works too
✓ an unknown size is ignored not refused        ✓ a product without an image is unaffected by the size
✓ the etag covers the bytes actually served     ✓ an undecodable blob falls back to the full image
✓ a barista is forbidden                        ✓ a guest is sent to login
✓ upload resizes image to maximum 128 pixels    ✓ the whitelist is what the service publishes
Tests:    11 passed (66 assertions)
```

**I damaged this file and recovered it — recording it plainly.** The plan says
`tests/Feature/FruitVegProductImageTest.php (new)`. It was not new. I wrote it with
`cat >` without looking first, which destroyed the one test it held,
`test_upload_resizes_image_to_maximum_128_pixels`. I noticed because the suite came
out at 610 rather than the 611 the new tests implied, and `git diff --stat` showed
the file as modified rather than untracked. Recovered from `git show HEAD:` and
merged back verbatim, marked as pre-existing. Nothing was lost, but the plan naming
a file `(new)` is not a reason to skip looking at it.

That recovered test is also **useful evidence** (see Notes 2): the office upload
path already caps stored images at 128 px, yet most stored photos are far bigger.

### 3. Shop rows ask for thumbnails — done

Changed: `WasteController::buildRows()`, `HarvestController::dataFor()`, and the
three `image_url` assertions in `ShopFruitVegTest`.

```
$ php artisan test --filter=ShopFruitVegTest
Tests:    10 passed (83 assertions)
```

### 4. Docs, format, build — done

Changed: `docs/features/fruit-veg-system.md`, `docs/design/shop-mode/README.md`.
```
$ ./vendor/bin/pint --test --dirty  → PASS 14 files
$ npm run build                     → ✓ built in 8.15s
```

## Verification

**1 & 2. Tests**
```
$ php artisan test --filter="Shop|FruitVegProductImage|ProductThumbnail"
Tests:    209 passed (932 assertions)

$ php artisan test
Tests:    15 failed, 610 passed (2546 assertions)
```
The 610 above was taken **before** I noticed the overwritten file, and it is the
evidence that led me to look: 594 + 7 + 10 should have been 611, and one test was
missing. After recovering it the final run is:
```
$ php artisan test
Tests:    15 failed, 611 passed (2555 assertions)
```
15 failed, the identical set (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting
×2, Product ×2, TestScraper ×1). 611 = 594 + 7 (`ProductThumbnailServiceTest`) + 10
(the new tests in `FruitVegProductImageTest`); its eleventh test was already in the
594.

**3. `git diff app/Http/Controllers/FruitVegController.php`** — `productImage()`
only, plus one `use` line. The no-size path is unchanged, and
`test_without_a_size_it_serves_the_stored_blob_unchanged` asserts the response body
is byte-identical to the stored blob, so the office pages are pinned by a test
rather than by inspection.

**4. Contract untouched.** No Shop view or script changed this cycle; design block
`cmp` identical.

**5. Manual, dev app — measured.**

*The headline.* Opening the waste log and scrolling to the bottom so every tile
loads:
```
                     requests   transferred   largest   median
before (cycle 17c)      87        3.15 MB      144 kB    33 kB
after  (this cycle)     95        329 kB       5.9 kB    3.5 kB
```
329 kB for **all 95** images, against 3.15 MB for 87 of them. Roughly a tenfold
reduction, inside the plan's "under 400 kB" expectation. Every image arrives
112 × 112.

*Warm load.* Second open, cache warm: 95 requests, **28 kB** — all 95 are 304s of a
few hundred bytes. This is the corrected picture: `must-revalidate` means a warm
open still costs 95 conditional round-trips, it just no longer costs megabytes.

*Why cycle 17c reported "zero requests".* The measurement was taken with the tab
backgrounded. `document.visibilityState` was `hidden`, and Chrome does not load
`loading="lazy"` images in a hidden tab — so nothing loaded and no resource entries
existed, which I read as "served from cache". I reproduced the artifact here
(`imgsLoadedOk: 0`, `imgEntries: 0`) and then took a screenshot to force a render,
after which the images loaded normally. Every measurement above was taken with the
tab rendering.

*The cache.* `storage/app/private/fv-thumbs/` did not exist before this walkthrough
and now holds **95 files, 440 kB on disk, 3,271 bytes average** — one per image
served, exactly as designed. It is covered by `storage/app/private/.gitignore`, so
nothing about it enters the diff. First encode measured at 133 ms; every later
request is a file read.

*Office pages.* `/fruit-veg/availability`: 50 product images, **none** requesting a
size, and their natural dimensions are the originals (313×161, 500×516, 235×214,
100×100, and the 1×1 placeholder). Unchanged.

*Console.* The Shop waste screen is clean. `/fruit-veg/availability` emits ~1000
`Alpine Warning: Duplicate key on x-for template` — that is the **office** page and
is pre-existing, not from this cycle; the Shop screen has 98 products with 98
unique codes and produces none. Noted, not touched.

*Rebuild after deletion — not run as specified, and the reason is a finding.* The
plan says to delete the folder and watch it rebuild. I cannot: the web server
creates it, so it is `www-data:www-data` mode 755 while I am `jon`, and `rm -rf`
fails with Permission denied on every file. The rebuild itself is nevertheless
proven — the folder did not exist when this walkthrough began and built itself to
95 files — and the unit tests cover the cold path directly. I corrected the sentence
I had written in the feature doc, which claimed the folder "can be deleted at any
time"; it now says the deletion needs `sudo`. See Notes 3.

## Deviations

**One, small.** The cache path hashes the product code
(`fv-thumbs/<md5(code)>-<size>-<md5(blob)>.jpg`) instead of interpolating it as the
plan writes. A POS `CODE` is free text and the plan's format would put it straight
into a filesystem path. Behaviour is otherwise identical, and
`test_a_code_with_path_characters_cannot_escape_the_folder` covers it.

## Files changed

Mine, this cycle:
```
app/Services/ProductThumbnailService.php            (new)
tests/Unit/ProductThumbnailServiceTest.php          (new)
tests/Feature/FruitVegProductImageTest.php          (existing — extended, see step 2)
app/Http/Controllers/FruitVegController.php         productImage() + one use line
app/Http/Controllers/WasteController.php            image_url now ?w=112
app/Http/Controllers/HarvestController.php          image_url now ?w=112
tests/Feature/Shop/ShopFruitVegTest.php             three image_url assertions
docs/features/fruit-veg-system.md                   one paragraph
docs/design/shop-mode/README.md                     one sentence
```
`storage/app/private/fv-thumbs/` now holds 95 generated files on dev; it is
gitignored and is not part of the diff.

Still in the tree and not mine: cycles 15, 17, 17b, 17c, and the parallel
delivery-row session.

**Not committed, not pushed, not deployed.**

## Notes for Planner

1. **The warm path is 95 conditional requests, not zero.** My cycle 17c report said
   otherwise and it was wrong — the header I quoted belongs to the no-image branch,
   and the "zero requests" measurement was an artifact of a hidden tab. The images
   now cost 28 kB warm instead of megabytes, so this is no longer urgent, but if the
   shop wifi is poor then 95 round-trips on every open is still the remaining cost.
   Giving real images the same `max-age` the placeholder already has would remove
   them; the blob hash is in the URL for thumbnails, so a long max-age is safe
   *for the `?w=` responses* — a replaced photo produces a new URL. It is not safe
   for the full-size URL, which has no hash in it. That asymmetry is the whole
   design decision and it belongs to you, not to me.

2. **The stored photos should never have been this big, and something upstream is
   not resizing.** The office upload path caps what it stores at 128 px on the
   longest side — that is what the test I recovered asserts. Yet of the 406 F&V
   photos on dev:
   ```
   over 128 px:      282 of 406        max side up to 840 px
   bytes:            min 1.2 kB   median 38 kB   max 1,036,081 (1 MB)
   formats:          271 PNG, 135 JPEG
   total:            19.6 MB in PRODUCTS.IMAGE
   ```
   So most photos arrived by some other path — a sync, the POS itself, or direct
   database writes — and 271 of them are PNGs, which is the wrong format for
   photographs and explains the 1 MB outlier. Thumbnails hide this from the Shop
   screens, but the blobs still cross from the POS database into PHP on every rows
   request. Worth finding the other write path.

3. **"Just delete the folder" is not something the owner can do.** The cache is
   created by the web server and owned by `www-data`, so clearing it needs `sudo`.
   The plan explicitly left out a cache-clearing command, and normally none is
   needed because the blob hash keys the file. But if clearing is ever wanted
   operationally — a bad encode, a disk-space scare — it has to be something the web
   server executes, not a shell instruction in a document. A small admin action
   would do it.

4. **~1000 `Duplicate key on x-for template` warnings on `/fruit-veg/availability`.**
   Pre-existing, on the office page, unrelated to this cycle; the Shop screens are
   clean. Flagging it because it is loud enough to bury a real error on that page.

5. **Cycle 17's harvest-accumulates-across-units defect is still open.** Fourth
   cycle carrying this.
