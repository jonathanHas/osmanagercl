# Shop mode cycle 16 — Camera scanning in the Shop scan input — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: a02d6edd

Pre-existing dirty files:
```
 M resources/views/shop/partials/request-form.blade.php
 M tests/Feature/Shop/ShopRequestsTest.php
?? docs/jons_docs/
?? docs/planImp/implemented.md
?? docs/planImp/parked/2026-09-26-shop-mode-cycle-15-vouchers/
?? docs/planImp/plan.md
```
The two modified files are the post-cycle-14c fix for the New request sheet's
`pick(p)` → `pickResult(p)` handler; they are not part of this cycle. That fix's
write-up had been appended to the end of `implemented.md`, which was the wrong
file — the protocol puts a post-acceptance finding in `findings/`. Moved to
`docs/planImp/findings/2026-09-26-request-pick-handler-rename.md` before starting
here, so this cycle's report starts clean and the Planner does not lose it.

Design block baseline, for the after-comparison:
```
$ head -529 resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo IDENTICAL
IDENTICAL
```

## Pre-flight: the plan's root cause, checked against the library

Before changing geometry on the strength of a diagnosis, I read
`node_modules/html5-qrcode` (2.3.8 confirmed) and every claim holds:

`esm/camera/core-impl.js:148,151-153` — the video is sized from the mount and the
width is **inline**, so a stylesheet `width: 100%` cannot win:
```js
this.surface = this.createVideoElement(this.parentElement.clientWidth);
...
videoElement.style.width = "".concat(width, "px");
```
`esm/html5-qrcode.js:140-142` — the inline `position: relative` the plan warns about:
```js
var rootElementWidth = element.clientWidth
    ? element.clientWidth : Constants.DEFAULT_WIDTH;
element.style.position = "relative";
```
Note the `DEFAULT_WIDTH` fallback applies only to `rootElementWidth`; the video's
width has **no** fallback, so a 0-px mount really does produce `width: 0px`.

`esm/html5-qrcode.js:37` — `SHADED_REGION_ELEMENT_ID = "qr-shaded-region"`, so the
selector the plan hides is the right one.

`esm/camera/core-impl.js:169-170` — `onRenderSurfaceReady` reports
`surface.clientWidth/clientHeight`, i.e. 0 for a 0-px video, which is what
truncates the qrbox.

And the mount is 0 px wide because `.shop-scan__camera` is `display: grid` with
`place-items: center`: `justify-items: center` makes a grid item shrink-to-fit
instead of stretch, and the mount is empty. Confirmed at
`resources/css/shop.css:267`.

One timing question the plan does not raise, which I checked because it would make
the fix useless: is the box still `display: none` when the library measures it?
`toggleCamera()` sets `cameraOpen` and then `await import(...)`, which yields, so
Alpine's `:class` effect has flushed before `startScanner` runs — and the mount is
measured later still, after `getUserMedia` resolves. So the box is displayed and
laid out at measure time, and pinning the mount will give a real width.

## Steps

### 1. Give the mount its size — done

Changed: `resources/views/components/shop/scan-input.blade.php`,
`resources/css/shop.css` (APP ADDITIONS only).

The mount is now `<div class="shop-scan__mount" :id="cameraId"></div>` and three
rules were added under `APP ADDITIONS`, as the plan specifies.

Check output:
```
$ php artisan tinker --execute='render components.shop.scan-input'
...class="shop-scan__camera"><div class="shop-scan__mount" :id="cameraId"></div><div
class="shop-scan__reticle"></div><span class="shop-scan__camlabel">Point at the
barcode</span></div>

$ sed -n '/APP ADDITIONS START/,$p' resources/css/shop.css | grep -c "shop-scan__mount"
3

$ head -529 resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo IDENTICAL
IDENTICAL

$ php artisan test --filter=ShopStockScanTest
Tests:    8 passed (22 assertions)
```

A second effect of this step, which the plan implies but is worth recording: the
mount was until now an in-flow grid item, so `.shop-scan__camera`'s grid held *two*
in-flow children and put the reticle in a second row rather than centred. Taking the
mount out of flow leaves the reticle as the only in-flow item, so `place-items:
center` now centres it as the design intends. Paint order is still correct: mount and
reticle are both positioned with `z-index: auto`, and the mount comes first in the
DOM, so the reticle and the label draw over the video.

### 2. Report the real error — done

Changed: `resources/js/shop/scan-input.js` — `toggleCamera()`'s catch now logs the
exception and calls `this.fail(this.cameraFailureText(e))`; `cameraFailureText(e)`
added.

Check output:
```
$ node --check resources/js/shop/scan-input.js
(no output — parses)

$ node scratchpad/camfail.mjs      # stubs window.isSecureContext, calls the real factory
insecure context     : Live scanning needs HTTPS
NotAllowedError      : Camera permission was refused
NotFoundError        : No camera found
NotReadableError     : Camera is in use by another app
other Error          : Camera could not start: Camera streaming not supported
library plain string : Camera could not start: some library string
undefined            : Camera could not start: undefined
```
The last two lines are beyond the plan's list and are there because the library
throws plain strings rather than Errors: `String(e)` handles that, and a thrown
`undefined` degrades to readable text rather than throwing inside the handler.

### 3. Test — done

Changed: `tests/Feature/Shop/ShopStockScanTest.php` — added
`test_scan_input_mounts_the_camera_in_a_sized_element`, asserting both halves of the
fix: the rendered mount div and the stylesheet rule that gives it a size.

Check output:
```
$ php artisan test --filter="ShopStockScanTest|ShopViewContractTest"
  ✓ scan input mounts the camera in a sized element
Tests:    24 passed (207 assertions)
```

I also checked the new test can actually fail, because an assertion on a stylesheet
is easy to write in a way that passes on anything. Changing the rule to
`position: static` and rerunning:
```
   FAILED  Tests\Feature\Shop\ShopStockScanTest > scan input mounts the came…
  Tests:    1 failed (3 assertions)
```
Then restored, and the design block `cmp` is identical again.

### 4. Docs, build, format — done

Changed: `docs/design/shop-mode/README.md` (two bullets under the scan-input API:
the mount and why, and the new failure messages), `docs/development/known-issues.md`
(entry "Shop Scan Camera Showed No Video", 2026-09-26, under Frontend Issues).

Check output:
```
$ grep -c "shop-scan__mount" docs/design/shop-mode/README.md
1

$ grep -n "Shop Scan Camera Showed No Video" docs/development/known-issues.md
120:### Shop Scan Camera Showed No Video

$ npm run build
public/build/assets/shop-BwUm_mmq.js              22.58 kB │ gzip:   7.15 kB
public/build/assets/barcode-scanner-C5jdikgW.js  335.57 kB │ gzip: 100.41 kB
✓ built in 9.58s

$ ./vendor/bin/pint --test --dirty
PASS   2 files
```

The barcode-scanner chunk kept its hash (`C5jdikgW`), which is the one already
deployed — so the deploy carries only the new `shop-*.js` and `shop-*.css`.

The built stylesheet is `shop-BYs9Z6KG.css` (shop.css is its own Vite entry, not
part of app.css), and the rules survived minification intact:
```
.shop-scan__mount{position:absolute!important;top:0;right:0;bottom:0;left:0;width:100%;height:100%}
.shop-scan__mount video{width:100%!important;height:100%!important;-o-object-fit:cover;object-fit:cover}
.shop-scan__mount #qr-shaded-region{display:none}
```

## Verification

**1. `php artisan test --filter=Shop`**
```
Tests:    168 passed (699 assertions)
```

**`php artisan test`**
```
Tests:    17 failed, 568 passed (2320 assertions)
```
The 17 are the identical known set — UdeaScrapingServiceTest ×7,
CashReconciliationTest ×3, WasteLogTest ×2, ProductTest ×2,
FruitVegLabelPrintingTest ×2, TestScraperControllerTest ×1 — and passed is
567 + 1, the one test this cycle adds, as the plan predicted.

**2. Design block and contract greps**
```
$ head -529 resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo IDENTICAL
IDENTICAL
$ grep -c "route(" resources/js/shop/scan-input.js
0
$ grep -rn "<script\|<style" resources/views/shop/ | wc -l
0
```

**3. `npm run build`** — succeeds; the barcode-scanner chunk hash is unchanged.

**4. Manual.** The plan defers this to the owner's phone after deploy, because dev
is HTTP and the camera cannot start. I could do more than that here, and the part
that matters is done.

The fault was geometry, so geometry is what I measured. On
`http://osmanager.local/shop/stock-scan`, with the camera box opened and the
library's own measurement reproduced verbatim
(`createVideoElement(parentElement.clientWidth)`), then with the pin removed to
reproduce the old state:
```
fault reproduced (mount back in flow):
  box 427x320   mount   0x0    position: static     video would be created at width: 0px
with the fix:
  box 427x320   mount 427x320  position: absolute   video is created at width: 427px
```
That is the whole bug and the whole fix, measured rather than argued.

The reticle is now exactly centred, which it was not before — the mount used to be
a second in-flow grid item:
```
reticle offset from box centre: x 0, y 0
```

I also confirmed the deployed bundle carries step 2: the live Alpine component on
the page exposes `cameraFailureText` among its methods.

**What I did not do.** This machine turns out to have a camera, and Chrome treats
`http://osmanager.local` as a secure context here, so `getUserMedia` is actually
reachable on dev — the plan's assumption that it is not is wrong (see Notes).
Starting it puts up Chrome's camera permission prompt, and granting a site
permission in the owner's browser is their decision, not mine, so I stopped at the
prompt and reloaded the page to dismiss it. Nothing was granted and no site
permission changed. The end-to-end check — live video in the box, a real barcode
decoding, the beep, the camera closing, rotation, and the "Camera permission was
refused" text — still needs either the owner's say-so here or their phone after
deploy.

## Deviations

None. Every step was done as written.

## Files changed

```
 M docs/development/known-issues.md
 M docs/design/shop-mode/README.md
 M resources/css/shop.css
 M resources/js/shop/scan-input.js
 M resources/views/components/shop/scan-input.blade.php
 M tests/Feature/Shop/ShopStockScanTest.php
?? docs/planImp/findings/2026-09-26-request-pick-handler-rename.md

pre-existing, not this cycle:
 M resources/views/shop/partials/request-form.blade.php
 M tests/Feature/Shop/ShopRequestsTest.php
?? docs/jons_docs/
?? docs/planImp/parked/2026-09-26-shop-mode-cycle-15-vouchers/
```
`public/build` is gitignored (`.gitignore:17`), so the built assets do not appear
above and the deploy must run the build itself.

**Not committed, not pushed, not deployed**, per Constraints. The owner deploys.
This is the fix they are waiting for: it needs `resources/css/shop.css` and
`resources/js/shop/scan-input.js` built and shipped — the new assets are
`shop-BwUm_mmq.js` and `shop-BYs9Z6KG.css`, and `barcode-scanner-C5jdikgW.js` is
unchanged from what is already live.

## Notes for Planner

1. **The camera can be tested on dev after all.** The plan's Risks say "dev is
   HTTP, so the camera cannot be exercised locally". On this machine
   `window.isSecureContext` is `true` on `http://osmanager.local` and
   `enumerateDevices()` reports one `videoinput`, so `getUserMedia` gets as far as
   the permission prompt. If the owner is willing to allow camera access for that
   origin once, the entire manual list — live video, a real decode, the beep, the
   reopen-after-save, rotation — can be checked here before a deploy rather than
   after one. Worth knowing for the vouchers cycle, which adds another scan input.

2. **The decode region does not match the reticle, and cannot.** `foreverScan()`
   computes `widthRatio = videoWidth / clientWidth` and `heightRatio = videoHeight /
   clientHeight` independently, which assumes the video is stretched to fill its
   box. With `object-fit: cover` it is cropped instead, so the region the library
   samples is not the region the user sees inside the reticle. I worked it through
   for a 1280x720 stream in a 427x320 box: the sampled region is a superset of the
   reticle on both axes, so nothing is lost and the plan's risk note holds — but the
   reticle is advisory, not exact, and a future change to the box's aspect ratio
   could break that containment without any test noticing. Not touched.

3. **The old catch block cost a cycle.** One hardcoded cause ("Live scanning needs
   HTTPS") for every failure meant the screen actively misled whoever looked at it,
   and the exception was never logged. Step 2 fixes this one component. The same
   shape — a catch that reports a guess rather than the error — is worth looking for
   elsewhere in the Shop modules when a cycle is next in that code.

4. **`findings/` had been emptied.** The post-cycle-14c fix for the New request
   sheet's `pick(p)` handler was written to the end of `implemented.md`, which this
   cycle overwrites; I moved it to
   `docs/planImp/findings/2026-09-26-request-pick-handler-rename.md` first. It is
   fixed in the working tree but has never been reviewed, and its two suggestions
   (a test that checks Alpine handler names resolve against their module; browser
   checks that exercise the action rather than the render) are still open.
