# Plan: Shop mode cycle 30 — Pause the camera instead of stopping it

**Status:** READY
**Planner:** Fable 5.1
**Date:** 2026-09-28

## Goal

On production the camera takes too long to come back after "Add" for the next item to be scanned fluently. Cycle 29 made it come back at all; the delay is that every detection **stops** the camera (tears down the stream) and every restart re-enumerates cameras, asks for the stream again and re-initialises the decoder, which is one to three seconds on a phone. Pause the decoder on detection and resume it on the restart event instead. Resume is immediate: the stream never closes. This applies to every screen that uses the shared scan field (delivery scan, stock scan, labels, vouchers).

## Context (verified 2026-09-28)

- `resources/js/barcode-scanner.js`: wraps html5-qrcode 2.3.8; exports `startScanner(elementId, onSuccess, onError)` (`new Html5Qrcode(elementId)`, `start({facingMode:'environment'}, {fps:10, qrbox:{300×150}, formatsToSupport})`), `stopScanner()`, `isRunning()`, `scanFile()`. The library's `Html5Qrcode` has `pause(shouldPauseVideo?: boolean): void`, `resume(): void` and `getState(): Html5QrcodeScannerState` (`SCANNING = 2`, `PAUSED = 3`) — `node_modules/html5-qrcode/esm/html5-qrcode.d.ts` lines 41–42. `pause()` throws if the scanner is not scanning; `resume()` throws if it is not paused.
- `resources/js/shop/scan-input.js`: `detected(text)` beeps/vibrates, de-duplicates (`lastCode`/`lastAt`, 2 s), then `this.stop()` (which awaits `stopScanner()` and sets `cameraOpen = false`), sets `value` and `submit()`s. `toggleCamera()` opens (`cameraOpen = cameraWanted = true`, `startScanner(...)`) or closes (`cameraWanted = false; stop()`). `restartCameraIfWanted()` (cycle 29: pushes `lastAt` forward 1500 ms, then `toggleCamera()` when `cameraWanted && ! cameraOpen`). `done()` clears the error and refocuses; `fail()` sets the error text.
- `resources/views/components/shop/scan-input.blade.php`: root `:class="{ 'is-camera': cameraOpen, 'is-error': error !== null }"`, listeners for `shop-scan-done`, `shop-scan-error`, `shop-scan-saved`; camera block `div.shop-scan__camera` with the mount, the reticle and `span.shop-scan__camlabel` "Point at the barcode" (static text).
- Pages dispatching `shop-scan-saved`: stock-scan (after save), delivery-scan (after Add, Cancel, not-found — cycle 29), labels (after add). Vouchers dispatches only `shop-scan-done`, so after a camera scan there the camera currently stays stopped; with pause/resume it would stay **paused** showing the frozen frame until the user taps the camera button to close it — acceptable and noted below.
- The camera runs only in a secure context; dev is plain HTTP, production is HTTPS. The camera-related tests are source assertions (`ShopDeliveryTest`, `ShopLabelsTest`, cycle 29) plus the view assertions on the component's handlers.
- Tree: cycle 27 and 29 may be uncommitted, and the voucher track's files are in the tree; revert nothing, and run Pint only on the files you change. **Measure the baseline yourself** before starting (it was 15 failed / 799 passed at cycle 29's acceptance; the voucher track may have moved it).

## Constraints

- No commits, no deploys. No CSS change; the design block stays byte-identical. Component markup may change only as named in step 3.
- The user's explicit camera toggle still fully stops the stream (privacy: tapping the camera button off must turn the camera off).
- Keyboard-wedge/typed scans unaffected.
- The dedupe/suppression rule from cycle 29 stays: the same code is ignored for ~3.5 s after a resume; a different code is accepted at once.

## Out of scope

- Reopening the camera while the delivery prompt is open (cycle 29's ordering rule stands; the frozen frame is what shows during the prompt).
- Changing `fps`/`qrbox` or the decoder formats.

## Steps

### 1. Pause/resume in the wrapper
`barcode-scanner.js`: add and export `pauseScanner()` (when `scanner` exists and `scanner.getState() === Html5QrcodeScannerState.SCANNING` → `scanner.pause(true)`, freezing the video on the frame that was read) and `resumeScanner()` (when state is `PAUSED` → `scanner.resume()`); both wrapped in try/catch and returning a boolean saying whether they acted. Import `Html5QrcodeScannerState` from `html5-qrcode`. `isRunning()` reads `scanner.isScanning`, which the library leaves true while paused, so it needs no change — confirm that in the report. Add the two functions to the `window.BarcodeScanner` object at the bottom of the file, beside the existing four. `Html5QrcodeScannerState` is exported from the package index (`node_modules/html5-qrcode/esm/index.d.ts` line 4). Doc comment: why pause beats stop (restart latency on phones).
**Check:** `grep -n "export" resources/js/barcode-scanner.js` lists the two new functions; `npm run build` clean.

### 2. The scan field pauses on detection and resumes on the restart event
`scan-input.js`:
- Add `paused: false`.
- `detected(text)`: replace `this.stop()` with `this.pause()` — a new method: `import('../barcode-scanner')` then `pauseScanner()`; `this.paused = true`; `cameraOpen` stays true so the camera block and the frozen frame remain on screen.
- `restartCameraIfWanted()`: keep the `lastAt` push; then if `this.cameraOpen && this.paused` → `resume()` (new method: `resumeScanner()`, `paused = false`); else if `cameraWanted && ! cameraOpen` → `toggleCamera()` (the existing path, kept for a camera that failed or was never started).
- `stop()`: also `paused = false`. `toggleCamera()` closing branch unchanged (full stop).
- If `resumeScanner()` returns false (state was not PAUSED, e.g. the stream died in the background), fall back to `stop()` then `toggleCamera()` so the user still gets a camera.
- Update the header comment: detection pauses; the page's `shop-scan-saved` resumes; the camera button stops.
**Check:** read the three methods together and describe the state machine (`cameraOpen`, `paused`, `cameraWanted`) in the report in four lines.

### 3. Say "captured" on the frozen frame
`scan-input.blade.php`: the camera label becomes `<span class="shop-scan__camlabel" x-text="paused ? 'Got it' : 'Point at the barcode'">Point at the barcode</span>` so the frozen frame reads as a capture, not a hang. No new class, no CSS.
**Check:** `ShopViewContractTest` green; the pages' tests that assert component strings still pass (grep the tests for `Point at the barcode` first — if any pins the literal, keep it as the fallback text as shown).

### 4. Tests
Source assertions in the cycle-29 style, in `tests/Feature/Shop/ShopStockScanTest.php` (the field's home page): `barcode-scanner.js` exports `pauseScanner` and `resumeScanner`; `scan-input.js` calls `pauseScanner` from `detected` (a `strpos` order check: `pauseScanner` appears after `detected(text)` and before `async stop()`), and `resumeScanner` from `restartCameraIfWanted`; and `detected(` no longer contains `this.stop()`. One test, documented.
**Check:** `php artisan test --filter='ShopStockScanTest|ShopDeliveryTest|ShopLabelsTest|ShopVouchersTest|ShopViewContractTest'` green.

### 5. Build and tidy
`npm run build`; `php artisan view:clear`; `./vendor/bin/pint tests/Feature/Shop/ShopStockScanTest.php`.

## Verification (report every item with what you saw)

1. `php artisan test` summary against the baseline you measured; no new failures.
2. Contract: `ShopViewContractTest` green; design-block `cmp` prints nothing.
3. **Wiring without a camera** (dev is HTTP): in the browser on the delivery scan page, stub the wrapper's `pauseScanner`/`resumeScanner` with spies on the imported module (or stub `scanner` state) and set `cameraOpen = true`; then type a code + Enter → `pauseScanner` called once, `paused` true, `cameraOpen` still true, the label reads "Got it"; Add → `resumeScanner` called once, `paused` false, label back to "Point at the barcode"; tap the camera button → `stopScanner` called, `cameraOpen` false. Report each.
4. **Real camera** on production after deploy, by the owner: scan → frozen frame with "Got it" → Add → live again immediately → next item reads. State this as owner-verified.

## Risks

- **Library state names.** If `Html5QrcodeScannerState` is not exported from the package's entry in this version, read it from `scanner.getState()` numerically with a named constant and a comment (SCANNING = 2, PAUSED = 3 in 2.3.8).
- **Backgrounded tab.** Phones drop the stream when the tab sleeps; `resume()` then throws or does nothing. The fallback in step 2 (stop + toggle) covers it; report if you can see the code path but cannot exercise it.
- **Vouchers screen** never dispatches `shop-scan-saved`, so after a camera scan there the frozen frame stays until the user closes the camera. Same as before in effect (before: a dark camera block), now with "Got it" showing. If that reads wrong, a later one-line dispatch after the lookup fixes it; out of scope here.
