# Shop mode cycle 16 — Camera scanning in the Shop scan input (production fault)

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

The camera button on the Shop scan input opens a dark box with the reticle and nothing else: no video, no detection. The office `/stocking` camera works on the same device over the same HTTPS. Root cause, confirmed in the library source: `html5-qrcode` sizes its `<video>` from the mount element's `clientWidth` as an **inline** style, and the Shop mounts it in an empty `<div>` inside a grid with `place-items: center`, so the mount is zero pixels wide, the video is created at `width: 0px` (inline beats the stylesheet's `width: 100%`), and the scan region is truncated to zero, so nothing ever decodes. Fix the mount's geometry, and while there make the component report the real failure text so the next fault is diagnosable from the screen. Every Shop screen with a scan input (stock scan, delivery scan, print labels, and later vouchers) is fixed at once.

## Context

- Component: `resources/views/components/shop/scan-input.blade.php` renders `<div class="shop-scan__camera"><div :id="cameraId"></div><div class="shop-scan__reticle"></div><span class="shop-scan__camlabel">Point at the barcode</span></div>`. Design rules (`resources/css/shop.css`, design block): `.shop-scan__camera { grid-column: 1 / -1; position: relative; display: none; place-items: center; aspect-ratio: 4 / 3; max-height: 320px; overflow: hidden; … }`, `.shop-scan__camera video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }`, `.shop-scan.is-camera .shop-scan__camera { display: grid; }`. The reticle is the one in-flow grid item the design centres; the label is absolute.
- Behaviour: `resources/js/shop/scan-input.js` `toggleCamera()`: sets `cameraOpen`, `await import('../barcode-scanner')`, `startScanner(this.cameraId, …)`; on any exception shows the fixed text "Camera could not start. Live scanning needs HTTPS." `detected()`, `stop()`, `restartCameraIfWanted()` as in cycle 3.
- Library (`node_modules/html5-qrcode`, 2.3.8): `RenderedCameraImpl` constructs the video with `createVideoElement(parentElement.clientWidth)` → `videoElement.style.width = width + "px"`; on `playing` it reports `surface.clientWidth/clientHeight` to `setupUi`, whose `validateQrboxSize` truncates the qrbox width to the viewfinder width (0). `Html5Qrcode.start()` also sets the mount's inline `style.position = "relative"`, so a stylesheet `position: absolute` on the mount is overridden unless `!important`. The library inserts its own shaded overlay with id `qr-shaded-region`.
- Office comparison: `resources/views/stocking/index.blade.php` mounts on `<div id="stocking-scanner" class="… bg-gray-900" style="min-height: 220px;">`, a full-width block.
- Production checks done read-only from the dev machine: `https://lilthink2/` serves the app with a self-signed cert (the owner's devices trust it: the office camera works); `build/manifest.json` and `assets/barcode-scanner-C5jdikgW.js` are deployed, `text/javascript`, and the deployed `shop-*.js` imports the scanner chunk by the right path. Port 80 on that host is a different application.
- Cycle 3's manual check ran over HTTP on dev, where the camera cannot start, so this path was never exercised live until now.
- Tests: `tests/Feature/Shop/ShopStockScanTest.php` renders the stock-scan page (contains the scan input); `ShopViewContractTest` reads `resources/views/shop/**` (components excluded).

## Constraints

- Do not commit, push or deploy (the owner deploys; this is the fix they are waiting for, say so in `implemented.md`).
- Design block byte-identical; the fix lives under `APP ADDITIONS`, the component blade and the script.
- No change to `resources/js/barcode-scanner.js` or the office pages.

## Out of scope

- A photo-capture fallback for insecure origins (the owner's devices are on HTTPS with a trusted cert).
- Changing the qrbox size or formats.

## Steps

### 1. Give the mount its size
Files: `resources/views/components/shop/scan-input.blade.php`, `resources/css/shop.css`
What: the mount becomes `<div class="shop-scan__mount" :id="cameraId"></div>`. App rules:
```css
/* html5-qrcode sizes its <video> from the mount's clientWidth (inline px) and sets the
   mount to position: relative inline; a centred, empty grid child is 0 px wide, so the
   video came out 0 px wide. Pin the mount to the whole camera box. */
.shop-scan__mount { position: absolute !important; inset: 0; width: 100%; height: 100%; }
.shop-scan__mount video { width: 100% !important; height: 100% !important; object-fit: cover; }
.shop-scan__mount #qr-shaded-region { display: none; }   /* the design draws its own reticle */
```
The `!important` on the video's width is needed because the library sets `width: <px>` inline from the measured mount; with the mount pinned it measures the box width, so the two agree, but the override keeps it right through orientation changes. Hiding the library's shading keeps the design's reticle as the only frame; the library's decode region is its `qrbox` (300 × 150, truncated to the box width), centred like the reticle.
Check: rendered stock-scan page contains `class="shop-scan__mount"`; `sed -n '/APP ADDITIONS START/,$p' resources/css/shop.css | grep -c "shop-scan__mount"` → 3; design block `cmp` identical.

### 2. Report the real error
Files: `resources/js/shop/scan-input.js`
What: in `toggleCamera()`'s catch: `console.error('Shop scan: camera failed', e);` then `this.fail(this.cameraFailureText(e))` where `cameraFailureText(e)` returns: `'Live scanning needs HTTPS'` when `! window.isSecureContext`; `'Camera permission was refused'` when `e?.name === 'NotAllowedError'`; `'No camera found'` for `NotFoundError`; `'Camera is in use by another app'` for `NotReadableError`; otherwise `'Camera could not start: ' + (e?.message ?? String(e))` (the library throws plain strings). Keep the existing behaviour otherwise.
Check: node exercise of `cameraFailureText` with each input (stub `window.isSecureContext`) → the five texts; `node --check`.

### 3. Test
Files: `tests/Feature/Shop/ShopStockScanTest.php`
What: add `scan_input_mounts_the_camera_in_a_sized_element`: the stock-scan page contains `<div class="shop-scan__mount" :id="cameraId"></div>` and the stylesheet (`file_get_contents(resource_path('css/shop.css'))`) contains `.shop-scan__mount { position: absolute !important;` after the `APP ADDITIONS START` marker. A stylesheet assertion in a feature test is unusual; it is here because the whole fault was geometry that no markup test could see, and this pins the rule that fixes it.
Check: `php artisan test --filter="ShopStockScanTest|ShopViewContractTest"` green.

### 4. Docs, build, format
Files: `docs/design/shop-mode/README.md`, `docs/development/known-issues.md`, all touched
What: README: under the scan-input bullet, one sentence on the mount (`.shop-scan__mount` pinned to the box because the library measures it). Known issues: a short entry "Shop scan camera showed no video (2026-09-26)" with the cause and fix, and the note that the design's `place-items: center` box needs a pinned mount for any library that measures its container. `npm run build`; `./vendor/bin/pint --test --dirty` (no PHP beyond the test).
Check: build succeeds; `grep -c "shop-scan__mount" docs/design/shop-mode/README.md` → ≥ 1.

## Verification

1. `php artisan test --filter=Shop` → green; `php artisan test` → 17 failed, the identical set; passed = 567 + 1.
2. Design block `cmp` identical; contract greps clean; `grep -c "route(" resources/js/shop/scan-input.js` → 0.
3. `npm run build` succeeds (the scanner chunk hash may change only if its imports change; it should not).
4. Manual, **after the owner deploys**, on the phone over `https://`: Shop → Stock scan → camera button: live video fills the box with the design reticle over it and no second dark overlay; a product barcode is read within a second or two, the beep sounds, the product loads and the camera closes; the camera reopens after a save when it had been open; the same on Receive delivery → scan and on Print labels; rotate the phone: the video still fills the box. Then, to check step 2, deny camera permission once in the browser's site settings and tap the camera: the message reads "Camera permission was refused".

## Risks

- **`!important`** is used three times, all scoped under `.shop-scan__mount`, because the library writes inline styles; there is no cleaner hook in 2.3.8.
- **The library's decode region** (qrbox 300 × 150) is larger than the design reticle on narrow phones; a barcode held inside the reticle is inside the qrbox, so nothing is lost. If detection feels slow on a wide tablet, widening the qrbox is a one-line change in `barcode-scanner.js` shared with the office; not touched here.
- **Untested on dev**: dev is HTTP, so the camera cannot be exercised locally; the geometry can be checked in dev tools by opening the camera (it will fail to start, but the mount's computed size is visible), and the real check is the owner's phone after deploy.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end and the diffs of the component, the stylesheet additions, the script, the test and both docs. Reran `php artisan test`: 17 failed / 568 passed, the identical pre-existing set. Design block byte-identical; the three app rules are exactly the plan's; the minified output keeps them; the scanner chunk hash is unchanged so the deploy carries only the new `shop-*` assets.

**Steps 1–4: pass.** The implementer re-verified every claim about the library against its source before touching geometry, measured the fault and the fix in the page (mount 0×0 before, 427×320 after, video width 0 px → 427 px), and proved the new stylesheet test can fail. The reticle is now truly centred, a side benefit of taking the mount out of flow.

**Deviations:** none.

**Notes for Planner.**
1. The camera can be exercised on dev after all (the browser treats the dev origin as secure): **noted for the vouchers cycle**; the end-to-end check stopped at the permission prompt, correctly.
2. The library's sampled region is a superset of the reticle with `object-fit: cover`; advisory only: **accepted**, nothing to do now.
3. Catch blocks that report a guess instead of the error: **agreed**; look for the shape when next in those modules.
4. `findings/` held an unreviewed post-14c fix: **reviewed here and accepted.** The New request sheet's result rows still called `pick(p)` after cycle 14 renamed it to `pickResult(p)` in the shared typeahead, so clicking a result threw; the one-line fix and the regression test on the handler names are correct. My cycle 14 review checked the modules and missed the view's call site. Two follow-ups recorded: a test that resolves every Alpine handler named in `resources/views/shop/**` against its module, and the rule that a manual check exercises the action, not just the render (added to `planimp.md`).

**Manual check:** geometry measured on dev; live decoding is for the owner's phone after deploy. New assets: `shop-BwUm_mmq.js`, `shop-BYs9Z6KG.css`; `barcode-scanner-C5jdikgW.js` unchanged.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-16/`, with the pick-handler finding.
