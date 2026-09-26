# Shop mode cycle 17d — Thumbnail size on the fruit & veg image route

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

Cycle 17c's measurement: opening the Shop waste log loads 87 full-size product photos, 3.15 MB, to draw them at 56 px. Add an optional size to the existing fruit-and-veg image route that returns a small, cached JPEG, and have the two Shop rows endpoints ask for it. Office pages keep requesting the full image exactly as they do now. Expected effect: the first open drops from megabytes to a few hundred kilobytes; repeat opens stay at zero requests thanks to the existing cache header.

## Context

- `FruitVegController::productImage($code, Request)` (`fruit-veg.product-image`, `permission:fruit_veg.operate`, `routes/web.php:467`): reads `PRODUCTS.IMAGE`, sniffs the content type from magic bytes (JPEG/PNG/GIF/WebP, default JPEG), returns a 1 × 1 transparent PNG for a product without an image, sets `ETag` = md5 of the blob, honours `If-None-Match` with 304, `Cache-Control: public, max-age=86400` on the placeholder; on real images `max-age=0, must-revalidate` unless `?t` is present (then 300). (Cycle 17c's report saw `max-age=86400` on the images it fetched; check which branch applies and keep the existing behaviour for the full-size response.)
- Libraries: `intervention/image` ^3.11 is in `composer.json`; PHP has `gd` and `exif`. Intervention v3 API: `use Intervention\Image\ImageManager;` `$manager = ImageManager::gd();` `$image = $manager->read($blob);` `$image->cover(112, 112)` (crop-to-fill) or `$image->scaleDown(width: 112)` (keep aspect); `$image->toJpeg(quality: 80)->toString()`. Product photos are square-ish packshots; `cover` gives uniform tiles.
- Storage: default disk `local` at `storage/app/private`; `Storage::disk('local')->put/exists/get/path`.
- Consumers: `WasteController::buildRows()` and `HarvestController::dataFor()` build `image_url` with `route('fruit-veg.product-image', $code)` (cycle 17c). `x-shop.product-thumb` renders 48 px in rows and 56 px in the picture tiles (`.shop-choice--pic`); device pixel ratio 2 on the tablet means 112 px source pixels are enough.
- Other `product-image` routes (coffee, categories) are separate controllers and untouched.
- Tests: `ShopFruitVegTest` fixture has a nullable `IMAGE` column with a fake JPEG header string (not a decodable image). No test covers `productImage()` today.

## Constraints

- Do not commit, push or deploy.
- Without the size parameter the route's response is byte-for-byte what it is today (office pages unaffected).
- Only whitelisted sizes are honoured (`112`, `224`); anything else is ignored, not an error.
- The cache key includes the blob's md5 so a replaced photo never serves a stale thumbnail; the cache lives on the `local` disk under `fv-thumbs/` and is safe to delete at any time.
- A blob the library cannot decode falls back to the full image (never a 500).

## Out of scope

- Changing the office pages to use thumbnails (they can later by adding `?w=`).
- A cache-clearing command (deleting the folder is enough).
- The blob-loading cost in the product query (housekeeping candidate stands).

## Steps

### 1. Thumbnail service
Files: `app/Services/ProductThumbnailService.php (new)`
What: `public function jpeg(string $code, string $blob, int $size): ?string` — `$key = "fv-thumbs/{$code}-{$size}-".substr(md5($blob), 0, 12).'.jpg'`; if the `local` disk has it, return its contents; else try `ImageManager::gd()->read($blob)->cover($size, $size)->toJpeg(quality: 80)->toString()`, store it, return it; on any `\Throwable` return null. `public const SIZES = [112, 224];`.
Check: unit test `tests/Unit/ProductThumbnailServiceTest.php`: build a 300 × 200 PNG with GD in the test (`imagecreatetruecolor`, `imagepng` to a buffer), call `jpeg('T1', $png, 112)` → non-null, `getimagesizefromstring` → 112 × 112 and `image/jpeg`; the file exists under `fv-thumbs/`; call again → same bytes and the file's mtime unchanged (served from cache; use `Storage::fake('local')` and compare `lastModified`); a garbage blob → null, nothing stored.

### 2. The route takes `?w=`
Files: `app/Http/Controllers/FruitVegController.php`
What: in `productImage()`, after the no-image branch and before the content-type sniff: `$w = (int) $request->query('w'); if (in_array($w, ProductThumbnailService::SIZES, true)) { $thumb = $this->thumbnails->jpeg($code, $imageData, $w); if ($thumb !== null) { $imageData = $thumb; $contentType = 'image/jpeg'; } }` (inject the service in the constructor, or `app()` it). The ETag then hashes the bytes actually served, so full and thumbnail responses have different tags; keep every header as it is. The sniff runs only when no thumbnail was substituted.
Check: feature test `tests/Feature/FruitVegProductImageTest.php (new)` with the POS `PRODUCTS` fixture and a real GD-generated PNG blob: `GET route('fruit-veg.product-image', ['code' => 'P1'])` → 200, `image/png`, body equals the blob (unchanged path); `?w=112` → 200, `image/jpeg`, 112 × 112; `?w=999` → the full PNG; a product without a blob and `?w=112` → the transparent PNG as today; `If-None-Match` with the thumbnail's ETag → 304; a barista → 403.

### 3. Shop rows ask for thumbnails
Files: `app/Http/Controllers/WasteController.php`, `app/Http/Controllers/HarvestController.php`, `tests/Feature/Shop/ShopFruitVegTest.php`
What: the two `image_url` builders become `route('fruit-veg.product-image', ['code' => $code, 'w' => 112])`. Update the three `image_url` assertions in `ShopFruitVegTest` to the new URL.
Check: `php artisan test --filter=ShopFruitVegTest` green.

### 4. Docs, format, build
Files: `docs/features/fruit-veg-system.md`, `docs/design/shop-mode/README.md`, all touched
What: feature doc: the `?w=` sizes, the cache folder and key, that deleting the folder is safe, and that the office pages can adopt `?w=` later. README: the Shop rows request 112 px thumbnails. `./vendor/bin/pint --dirty`; `npm run build` (no asset change expected; the manifest check is cheap).
Check: `./vendor/bin/pint --test --dirty` clean.

## Verification

1. `php artisan test --filter="Shop|FruitVegProductImage|ProductThumbnail"` → green.
2. `php artisan test` → 15 failed, the identical set; passed = 594 + the new tests.
3. `git diff app/Http/Controllers/FruitVegController.php` → only `productImage()` plus the injection; the no-size path is unchanged (the feature test's first case proves the bytes).
4. Contract untouched (no Shop view or script change); design block `cmp` identical.
5. Manual, dev app: open the Shop waste log on a cold cache (a private window): the network tab shows ~90 image requests **each a few kB** (report the total; expect under 400 kB against 3.15 MB before); tiles look the same; `storage/app/private/fv-thumbs/` holds one file per image served; reload → zero image requests; the office `/fruit-veg/availability` still shows full images with no `?w=`. Delete the folder → next open recreates it.

## Risks

- **First open after deploy encodes ~95 thumbnails** on the fly, a few hundred ms of CPU spread over the requests; each later request is a file read.
- **Disk**: 112 px JPEGs are 3–6 kB; a few thousand products would be ~20 MB at most.
- **GD availability on production**: `php -m` lists `gd` on dev; the deploy host should match (the app already lists Intervention Image as a dependency). If GD is missing the service's catch returns null and the full image is served, so the screen still works, only heavier.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end and the service, the route diff, the row URLs and both test files. Reran `php artisan test`: 15 failed / 611 passed, the identical set. The `?w=` path is whitelisted, cached under a hashed key, and falls back to the full image on any decode failure; the no-size path is pinned byte-for-byte by a test, so the office pages are unchanged. Design block untouched. Measured result: the first open of the waste log fell from 3.15 MB to 329 kB for all 95 pictures.

**Steps 1–4: pass.**

**Deviations.** Hashing the product code into the cache path instead of interpolating it: **accepted, and right**; a POS code is free text.

**The overwritten test.** The plan named `tests/Feature/FruitVegProductImageTest.php` as new; it existed with one test, which the implementer's first write destroyed. The implementer noticed from the suite count, recovered it from `HEAD`, and I confirmed the recovered function is byte-identical to `HEAD`'s. My error in the plan, well caught. Lesson for `planimp.md`: a file marked `(new)` is still checked before writing.

**Notes for Planner.**
1. Warm opens cost 95 conditional requests (28 kB), not zero, because real images are served `max-age=0, must-revalidate`; cycle 17c's "zero requests" was a hidden-tab artefact, now corrected. The implementer's suggestion to give thumbnails a long `max-age` is right in principle but **the blob hash is only in the cache filename, not in the URL** (`?w=112` carries no version), so a long lifetime would serve a stale thumbnail after a photo is replaced. **Fixed next, cycle 17e**: the rows add a short version derived from the blob to the URL, and the route sends a long `max-age` only when a version is present and matches.
2. Most stored photos bypass the office upload's 128 px cap (282 of 406 larger, 271 PNGs, one at 1 MB, 19.6 MB in total): **recorded for the owner**; the other write path is worth finding, but it is outside Shop mode.
3. The cache folder is owned by the web server and needs `sudo` to clear by hand: **accepted**; nothing needs clearing in normal use because the key includes the blob hash. A small admin action can come if ever wanted.
4. ~1,000 duplicate-key warnings on the office availability page: pre-existing, office; **noted**.
5. Harvest across-unit accumulation: still open; carried.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-17d/`.
