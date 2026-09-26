# Shop mode cycle 17e — Versioned thumbnail URLs so warm opens cost nothing

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

After cycle 17d a cold open of the waste log costs 329 kB; a warm open still costs 95 conditional requests (304s) because every image is served `max-age=0, must-revalidate`. Give the thumbnail URLs a short version derived from the photo itself, and let the route send a long cache lifetime only when that version is present and matches the stored photo. A replaced photo changes the version, so the browser never shows a stale picture, and an unchanged photo is never asked for again for a week. The full-size URLs the office pages use carry no version and keep their headers exactly as they are.

## Context

- `FruitVegController::productImage($code, Request)` (cycle 17d): `?w=` in `ProductThumbnailService::SIZES` → cached square JPEG; headers on real images: `Cache-Control: public, max-age={0|300}, must-revalidate`, `ETag` = md5 of the served bytes, `Last-Modified` now, 304 on `If-None-Match`; the placeholder PNG gets `max-age=86400`.
- Row builders: `WasteController::buildRows()` and `HarvestController::dataFor()` produce `image_url` = `route('fruit-veg.product-image', ['code' => …, 'w' => 112])` when `IMAGE !== null`; the blob is already loaded on the product at that point.
- Measured warm open (17d report): 95 requests, 28 kB, all 304.
- Tests: `tests/Feature/FruitVegProductImageTest.php` (11 tests incl. the pre-existing upload test; **existing file, extend it**), `tests/Unit/ProductThumbnailServiceTest.php`, `tests/Feature/Shop/ShopFruitVegTest.php` (three `image_url` assertions).

## Constraints

- Do not commit, push or deploy.
- No change to any response that has no `v` parameter. Office pages unaffected.
- The version is derived from the blob (first 8 hex characters of its md5), never a timestamp, so it is stable across servers and deploys.

## Out of scope

- Adopting `?w=`/`?v=` on the office pages.
- Any change to the thumbnail service or its cache layout.

## Steps

### 1. Rows carry the version
Files: `app/Http/Controllers/WasteController.php`, `app/Http/Controllers/HarvestController.php`
What: the two `image_url` expressions add `'v' => substr(md5($product->IMAGE), 0, 8)` (harvest: `$p->IMAGE`). md5 of a blob up to 1 MB is about a millisecond; 98 products per request is fine (and the blobs are already in memory).
Check: the `ShopFruitVegTest` `image_url` assertions updated to `route('fruit-veg.product-image', ['code' => …, 'w' => 112, 'v' => substr(md5($blob), 0, 8)])`; green.

### 2. The route honours a matching version
Files: `app/Http/Controllers/FruitVegController.php`
What: in `productImage()`, after the thumbnail substitution: `$version = (string) $request->query('v'); $versioned = $thumbnail !== null && $version !== '' && hash_equals(substr(md5($originalBlob), 0, 8), $version);` where `$originalBlob` is the blob before substitution. When `$versioned`, the response headers become `Cache-Control: public, max-age=604800, immutable` (a week; `immutable` stops revalidation on reload in browsers that support it) with the same `ETag`; otherwise headers are exactly as today. A wrong version (`v` present but not matching) is treated as absent: the current thumbnail is served with today's short-lived headers, so a stale link can never pin a stale picture for a week.
Check: extend `FruitVegProductImageTest`: `?w=112&v=<correct>` → 200 `image/jpeg` with `max-age=604800` and `immutable`; `?w=112&v=wrong` → 200 with `max-age=0, must-revalidate`; `?w=112` alone → unchanged headers; `?v=<correct>` without `w` → the full image with unchanged headers (versioning applies to thumbnails only); a product without an image ignores `v`.

### 3. Docs, format
Files: `docs/features/fruit-veg-system.md`, all touched
What: two sentences on `v` and the week-long lifetime for versioned thumbnails. `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean.

## Verification

1. `php artisan test --filter="Shop|FruitVegProductImage|ProductThumbnail"` → green; `php artisan test` → 15 failed, the identical set; passed = 611 + new tests.
2. `git diff app/Http/Controllers/FruitVegController.php` → `productImage()` only.
3. Manual, dev app, tab in the foreground (the 17c lesson): cold open of the waste log → ~95 image requests, ~330 kB, responses carry `max-age=604800, immutable`; reload → **zero** image requests (check the network tab with "disable cache" off); replace one product's photo on the office manage page → that tile's URL changes (`v` differs) and only that image is requested; the office availability page still serves full images with the old headers.

## Risks

- **A week is long**: it is safe only because the URL changes with the photo; the `v`-must-match rule keeps a guessed or stale `v` from pinning anything.
- **`immutable`** is advisory; browsers that ignore it still honour `max-age`.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end and the route diff, both row helpers and both test files. Reran `php artisan test`: 15 failed / 617 passed, the identical set (611 + 6 new tests). Design block untouched. The only change to the image route is inside `productImage()`: the version check against the original blob, and one cache header shared by the 200 and the 304.

**Steps 1–3: pass.** Measured on dev: a warm re-open of the waste log now makes zero image requests (all 95 served from the browser cache, no 304s); a replaced photo changes its version and exactly one image is fetched; the office pages still serve full images with their old headers.

**Deviations.** Applying the long lifetime to the 304 as well as the 200: **accepted, and necessary**; a client that revalidated once would otherwise fall back to asking every time.

**Notes for Planner.**
1. Full-size URLs still revalidate on the office pages: **by design**; if wanted later, give them `?v=` too rather than lengthening the unversioned lifetime.
2. The route's old `?t=` parameter is dead weight from before: **housekeeping candidate**, removable once a grep beyond `resources/` confirms nothing sends it.
3. The thumbnail cache never shrinks (one orphan per photo revision, ~3 kB each): **accepted**; a web-server-side clear can come if it ever matters.
4. Harvest across-unit accumulation: still the open candidate; carried.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-17e/`.
