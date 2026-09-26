# Shop mode cycle 17c — Pictures on the fruit & veg screens — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: 030e3e14. 31 dirty paths: cycles 15, 17 and 17b (mine, archived but
uncommitted) and the parallel delivery-row session's files. Every file this cycle
touches is already dirty from an earlier cycle, so `git diff` does not separate
them; Files changed at the end is exact.

Test baseline, from 17b: 15 failed / 594 passed (2468 assertions).

Design block:
```
$ head -529 resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo IDENTICAL
IDENTICAL
```

## Pre-flight

The plan's facts check out on the real database:
```
$ php artisan tinker --execute='...'
F&V products: 694   with image: 406
sample: 2289 "Apples Canada Grise" bytes=1670
```
`Product` lists `IMAGE` in both `$fillable` and `$hidden`, and the attribute is
readable after a plain `get()`, so `IMAGE !== null` needs no extra query — as the
plan says. `FruitVegController::productImage()` sniffs the magic bytes for the
content type and falls back to a 1×1 transparent PNG when a product has no blob.

## Steps

### 1. Rows carry an image URL when the product has a picture — done

Changed: `app/Http/Controllers/WasteController.php` (`buildRows()`),
`app/Http/Controllers/HarvestController.php` (`dataFor()`'s `productLookup`, its
`rows`, and both arrays in `rows()`).

Check output — both endpoints hit in the browser as the signed-in employee, against
the real database:
```
/fruit-veg/waste/rows
  98 products, every one has an image_url key
  95 with a URL, 3 null
  sample: 1108 "Apples Akane" → http://osmanager.local/fruit-veg/product-image/1108

  fetching that URL: 200  image/png  118068 bytes

/fruit-veg/harvest/rows
  rows 0, available 111, every one has the key, 64 with a URL
  sample: 000000000224 "Spinach - Mossfield 300g"
```

Two things the check turned up that the plan did not say:

- **The pictures are much bigger than the plan's estimate.** It says "average
  50 kB"; the first one I fetched is **118 kB**, and 95 of the 98 waste tiles have
  one. So the worst case if everything loaded is megabytes, not ~5 MB. `loading="lazy"`
  is doing real work here rather than being a nicety — I measure what actually
  loads in the manual step.
- **The fallback is indistinguishable from a real image over HTTP.** A product with
  no blob still gets `200 image/png`, 70 bytes — the 1×1 transparent PNG. So a
  client cannot tell "missing" from "present" by requesting it; `image_url: null`
  is what keeps the placeholder correct, and it also means those products are never
  requested at all. Confirmed against `000000000052` "Carrots 700g".

### 2. Shared parts accept `code` as the key and a produce placeholder — done

Changed: `resources/js/shop/product-images.js` (a `key(p)` helper returning
`p?.id ?? p?.code ?? null`, used by both `hasImage` and `imageFailed`),
`resources/views/components/shop/product-thumb.blade.php` (`placeholder` prop,
default `package`).

Check output:
```
$ node scratchpad/images17c.mjs
ok   id product has image: true          ok   id product after failure: false
ok   failed keyed by id: ["7"]
ok   code product has image: true        ok   code product after failure: false
ok   failed keyed by code: ["1108"]
ok   a different code is unaffected: true
ok   id wins over code: ["42"]
ok   null product: false                 ok   undefined product: false
ok   no image_url: false
ok   failing a keyless product records nothing: []

$ Blade::render on the component
carrot prop contains #carrot:  true
default contains #package:     true
carrot prop contains #package: false     ← the placeholder is replaced, not added

$ php artisan test --filter="ShopRequestsTest|ShopFindProductTest"
Tests:    24 passed (168 assertions)
```

`imageFailed` guarded on `! p?.id` before; it now guards on the resolved key being
null, so a product with only a `code` records its failure instead of being silently
ignored — which was the actual bug that would have shown up as "a broken F&V
picture never falls back to the placeholder". The old id-keyed behaviour is
unchanged, asserted above.

### 3. Picture tiles — done

Changed: `resources/css/shop.css` (`APP ADDITIONS`),
`resources/views/shop/fv-waste.blade.php`,
`resources/views/shop/fv-harvest.blade.php`, `resources/js/shop/fv-waste.js`,
`resources/js/shop/fv-harvest.js` (both now `mix(productImages(), {…})`).

**One rule beyond the plan's three, because without it the feature is unusable.**
The plan's CSS puts a 56 px thumbnail into `.shop-choice`, but `.shop-choices` is
`repeat(auto-fill, minmax(148px, 1fr))` — sized by the design for text-only tiles.
56 px thumbnail + 10 px left padding + 12 px gap + the design's 44 px check-mark
reserve leaves **33 px for the product name**, which overflows. Measured in the
browser before touching the file:
```
minmax   tile   text   overflowing   tile heights
148px    159    33     yes           86,103,108,124,129,146
200px    201    75     yes           86,108,129
220px    271   115     no            80,86,108
240px    271   115     no            80,86,108
```
220 px is where overflow stops and the tiles stop being ragged, so that is the
number, scoped with `:has()` so text-only choice grids keep the design's width:
```css
.shop-choices:has(.shop-choice--pic) { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
```
Recorded under Deviations.

Check output:
```
$ sed -n '/APP ADDITIONS START/,$p' resources/css/shop.css | grep -c "shop-choice"
4                                        (the plan says 3; the fourth is the rule above)
$ design block cmp                        → IDENTICAL
$ php artisan test --filter="ShopFruitVegTest|ShopViewContractTest"
Tests:    29 passed
```

### 4. Tests — done

Changed: `tests/Feature/Shop/ShopFruitVegTest.php` — a nullable `IMAGE` column on
the POS fixture, a `withImage` flag on the two product helpers, and assertions that
`image_url` is the route URL for a product with a blob and null for one without, on
both the waste rows and both harvest arrays. The two screen tests assert
`shop-choice shop-choice--pic`, `class="shop-thumb"`, `#carrot` and — on the waste
screen — `assertDontSee('#package')`, so the placeholder is proved replaced rather
than merely present.

```
$ php artisan test --filter=ShopFruitVegTest
Tests:    10 passed (83 assertions)
```

One failure on the way, and it was the test's fault: I keyed the `available`
collection by `code` to look a row up, and `Collection::keyBy` turns the numeric
string `'2003'` into the integer `2003`, so `assertSame(['2003'], …)` failed on the
key type rather than on anything real. The same `Collection` behaviour bit cycle 10.
Replaced with `pluck`/`firstWhere` and left a comment.

### 5. Docs, build, format — done

Changed: `docs/features/fruit-veg-system.md`, `docs/design/shop-mode/README.md`.

```
$ npm run build   → ✓ built  (shop-DgQqHTcZ.css, shop-BJQr28RP.js)
$ ./vendor/bin/pint --test --dirty → PASS 10 files
```

## Verification

**1. Tests**
```
$ php artisan test --filter=Shop
Tests:    192 passed (857 assertions)

$ php artisan test
Tests:    15 failed, 594 passed (2480 assertions)
```
15 failed, the identical set (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting
×2, Product ×2, TestScraper ×1). **Passed unchanged at 594**, as the plan predicted
— assertions only (2468 → 2480).

**2. Contract**
```
$ grep -c "route(" on fv-waste.js, fv-harvest.js, product-images.js  → 0, 0, 0
$ grep -rn "<script\|<style" resources/views/shop/                    → 0
$ design block cmp                                                    → IDENTICAL
```

**3. `npm run build` succeeds.** Above.

**4. Manual, dev app — done, with real taps, and measured rather than eyeballed.**

*Waste log.* 98 tiles, 95 with a picture and 3 with the carrot placeholder, which
matches the payload exactly (`placeholdersShown: 3`, `withUrl: 95`). After the grid
fix: tile 271 px, text 115 px, **no overflow**, heights settled at 80/86/108 px.
Selecting a tile keeps the design's check mark in its 44 px on the right, over the
accent tint — zoomed in to confirm.

*Today rows.* Logged Apricots (has a picture) and Carrots 700g (does not). Both rows
render as intended: the apricot photo at 48 px on one, the carrot placeholder circle
on the other, "€4.80 · 0.5 kg" and "€0.98 · 0.5 kg".

*Harvest.* Searched "spinach": four tiles, one with a photo and three with
placeholders, matching `hasUrl` on each row. Logged the one with a picture → the
Today row shows the same photo beside "13:52 · jonathanE · 1 unit".

*Console:* one message across the whole walkthrough, Alpine's startup log. No
image error events fired (`failed` stayed empty), so nothing fell back wrongly.

*Bandwidth — the plan's estimate is wrong and the risk is real.* It says "~50 kB
per picture" and that `loading="lazy"` "limits it to what scrolls into view".
Measured on a cold load:
```
87 image requests, 3,299,002 bytes = 3.15 MB
largest 144 kB / 134 kB / 132 kB, median 33 kB
grid height 2445 px against a 1209 px viewport
```
The grid is only about two screens tall, so lazy loading defers almost nothing —
87 of the 95 images load on opening the screen. **3.15 MB on first open.**

The saving grace, which I also measured: the image route sends
`Cache-Control: public, max-age=86400`, so a **second load makes zero image
requests** — not even conditional ones. So it is roughly 3 MB per device per day,
not per page view. Still worth the Planner's attention (note 1).

## Deviations

**One.** A fourth app CSS rule, `.shop-choices:has(.shop-choice--pic)` at
`minmax(220px, 1fr)`. The plan's three rules put a 56 px picture into a 148 px grid
column, which leaves 33 px for the name and overflows; the screen is unreadable
without this. The number is measured, not chosen (table in step 3), and `:has()`
scopes it so no other choice grid changes. The plan's check of "3" for the
`shop-choice` grep is therefore 4, and I have said so at that step.

Everything else is as written.

## Files changed

Mine, this cycle:
```
app/Http/Controllers/WasteController.php           image_url in buildRows()
app/Http/Controllers/HarvestController.php         image_url through dataFor() and rows()
resources/js/shop/product-images.js                key(p) = id ?? code
resources/views/components/shop/product-thumb.blade.php   placeholder prop
resources/css/shop.css                             4 rules under APP ADDITIONS
resources/js/shop/fv-waste.js                      mix(productImages(), …)
resources/js/shop/fv-harvest.js                    mix(productImages(), …)
resources/views/shop/fv-waste.blade.php            picture tiles + Today thumbs
resources/views/shop/fv-harvest.blade.php          picture tiles + Today thumbs
tests/Feature/Shop/ShopFruitVegTest.php            IMAGE fixture + assertions
docs/features/fruit-veg-system.md                  one paragraph
docs/design/shop-mode/README.md                    one sentence
```
Every one was already dirty or untracked from cycles 15/17/17b, so `git status`
does not separate the cycles; this list is exact. `resources/css/shop.css` is also
dirty from the parallel delivery-row session — their three `.shop-row--wrap` rules
sit above my four.

`public/build` is gitignored; the deploy must run the build.

**Not committed, not pushed, not deployed.**

## What I changed on dev, and put back

Two waste rows (Apricots, Carrots 700g) and one harvest row (Spinach - Mossfield
300g), all created by the walkthrough, all deleted. `WasteLog::count()` 0,
`Harvest::count()` 197 with none today.

**One thing I got wrong and corrected**: I deleted the `harvest_product_units` row
for Spinach - Mossfield 300g as if it were mine. It was not — the tile read "units"
*before* I logged anything, and the code defaults to `kg` when no row exists, so a
preference of `unit` was already stored and my save merely rewrote the same value.
I recreated it with `unit`, and the table is back at 10 rows. Recording it because
the count going 10 → 9 is the only reason I noticed.

## Notes for Planner

1. **3.15 MB on first open of the waste screen.** The plan's risk section assumes
   lazy loading contains this; measured, it does not, because the grid is only two
   screens tall. Per-device-per-day rather than per-view thanks to the route's
   24-hour cache header, but a tablet on shop wifi will feel the first open of each
   morning, and the picture store is full-size product photos (up to 144 kB) being
   drawn at 56 px. A thumbnail variant on `fruit-veg.product-image` — a `?size=`
   parameter with a cached resize — would cut it by an order of magnitude and would
   help the office pages too. The plan puts this out of scope, so I have not done
   it; it is the single biggest thing this cycle leaves behind.

2. **The blob still crosses the wire twice.** `rowsFor()` and `dataFor()` load every
   F&V product with its `IMAGE` blob from the POS database just to ask whether it is
   null — the plan notes this as pre-existing and out of scope, and it is, but this
   cycle is the first code to *depend* on it. If someone later adds
   `->select([...])` without `IMAGE` for performance, `image_url` silently becomes
   null everywhere and every tile shows a placeholder. A `whereNotNull('IMAGE')`
   existence pluck would decouple the two; worth doing when the query is optimised
   rather than after it breaks.

3. **The waste screen's grid is now 220 px minimum with 98 tiles.** That is roughly
   three columns on the counter tablet and a lot of scrolling. The harvest screen is
   fine because cycle 17b cut it to recent picks. If the waste screen gets the same
   treatment one day, the obvious cut is "on till and logged recently" — but its
   whole range genuinely is the working set for waste, so I would not do it without
   asking the owner.

4. **Cycle 17's harvest-accumulates-across-units defect is still open** (5.2 kg then
   2 units gives "7.2 unit"). Third cycle carrying this note.
