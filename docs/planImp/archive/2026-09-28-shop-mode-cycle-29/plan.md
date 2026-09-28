# Plan: Shop mode cycle 29 — Keep the camera open between delivery scans

**Status:** ACCEPTED
**Planner:** Fable 5.1
**Date:** 2026-09-28

(Cycle 28 is the voucher till-redemption track, planned separately under `docs/vouchers/`; the numbering continues from it.)

## Goal

On the delivery scan screen, a camera scan closes the camera and it never comes back, so staff press the camera button before every item. After the quantity is recorded (or the prompt is cancelled, or the code is not found) the camera should reopen on its own, as it already does on Stock scan. Same one-line fix on Print labels, which has the same scan-and-next rhythm.

## Context (verified 2026-09-28)

- `resources/js/shop/scan-input.js`: `detected(text)` beeps, de-duplicates the same code within 2 s (`lastCode`/`lastAt`), then `stop()`s the camera (`cameraOpen = false`, `cameraWanted` stays true) and submits. `restartCameraIfWanted()` (line ~195) calls `toggleCamera()` when `cameraWanted && ! cameraOpen`. The component listens for it: `resources/views/components/shop/scan-input.blade.php` line 15 `@shop-scan-saved.window="restartCameraIfWanted()"`.
- `resources/js/shop/stock-scan.js` lines 160–162 dispatch `shop-scan-saved` after a successful save ("Detecting a code stops the camera; bring it back for the next item"). That is why Stock scan behaves.
- `resources/js/shop/delivery-scan.js` dispatches only `shop-scan-done` (`announceDone()`, line ~425): after the lookup opens the prompt (line ~220), after a successful `commit()` (line ~276) and in `cancelPending()` (line ~286). A not-found code calls `announceError(...)` in `onScan()`. Nothing dispatches `shop-scan-saved`, so the camera stays closed.
- `resources/js/shop/labels.js` line ~101: after a successful queue add, `announceDone()` only.
- `resources/js/shop/vouchers.js`: one voucher per visit; not part of this cycle.
- The two-step prompt: a scan arriving while a prompt is open commits it first (`onScan()` line ~181). If the camera were reopened while the prompt is showing, the same barcode still in frame after the 2 s window would re-fire, commit one unit and open the prompt again — a silent extra unit. So the camera must come back only when the prompt has closed.
- Camera needs a secure context; the dev host is plain HTTP, so live camera runs only on production (`https://lilthink2/`). Cycle 16 established that.
- Tests: `tests/Feature/Shop/ShopDeliveryTest.php` pins view strings and, from cycle 14, some handler names; `ShopViewContractTest` scans views only. Baseline: 15 failed / 760 passed.

## Constraints

- No commits, no deploys. No view or CSS change expected; the contract test must stay green.
- Do not reopen the camera while the delivery prompt is open (see Context).
- Keyboard-wedge and typed scans are unaffected: `cameraWanted` is false unless the user opened the camera, so the event is a no-op for them.

## Out of scope

- Vouchers screen; Find product (no camera on its typeahead).
- Any change to the design or the scan-input markup.

## Steps

### 1. Delivery scan dispatches the restart
`resources/js/shop/delivery-scan.js`: add `announceSaved()` (`window.dispatchEvent(new CustomEvent('shop-scan-saved'))`, beside `announceDone()`, with a comment: detecting a code stops the camera; bring it back once the prompt has closed). Call it in three places: after `announceDone()` at the end of a successful `commit()`; in `cancelPending()`; and in `onScan()` on the not-found branch after `announceError(...)`. **Not** after the lookup that opens the prompt. Update the file header's description of the scan flow (one sentence).
**Check:** `grep -n "announceSaved" resources/js/shop/delivery-scan.js` shows the definition and three calls; none between the `this.pending = {` assignment and its `announceDone()`.

### 2. Ignore the same code briefly after a restart
`scan-input.js`: in `restartCameraIfWanted()`, before `toggleCamera()`, set `this.lastAt = Date.now() + 1500` (or a separate `suppressUntil`, your choice — one mechanism, commented): the item just recorded is usually still under the lens when the camera comes back about a second later, and the 2 s window measured from the *original* detection has already expired by then. With this, the same code is ignored for roughly 3.5 s after the restart; a different code is accepted immediately. Update the `detected()` comment to say so.
**Check:** read `detected()` and `restartCameraIfWanted()` together and state the resulting suppression window in the report.

### 3. Print labels, same rhythm
`labels.js` line ~101: dispatch `shop-scan-saved` after the successful add, after `announceDone()`, with the same comment as stock-scan.
**Check:** grep as in step 1.

### 4. Tests
- `ShopDeliveryTest`: a source assertion in the cycle-14 style that `resources/js/shop/delivery-scan.js` contains `shop-scan-saved` and that the string does not appear before the `this.pending = {` block's `announceDone()` (a simple `strpos` order check is enough; say what it pins).
- `ShopLabelsTest`: the same containment check for `labels.js`.
**Check:** `php artisan test --filter='ShopDeliveryTest|ShopLabelsTest|ShopStockScanTest'` green.

### 5. Build and tidy
`npm run build`; `php artisan view:clear`. No PHP changes expected beyond tests, so `pint --dirty` on the test files.

## Verification (report every item with what you saw)

1. `php artisan test` summary; the same 15 pre-existing failures.
2. Contract: `ShopViewContractTest` green; design-block `cmp` prints nothing.
3. **Event wiring without a camera** (dev is HTTP, so the camera itself cannot start here): on the delivery scan page in the browser, get the scan-input component's Alpine data (`Alpine.$data(document.querySelector('.shop-scan'))`), set `cameraWanted = true`, replace `toggleCamera` with a spy that records calls and sets `cameraOpen = true`, then: (a) type a code + Enter → prompt opens → spy **not** called; (b) Add → spy called once; (c) type a code, Cancel → spy called; (d) type an unknown code → spy called. Report the four results. Do the same once on Print labels after a successful add, and confirm Stock scan is unchanged.
4. **Real camera** is verified by the owner on production after deploy: scan, Add, and the camera returns on its own; the same item left under the lens is not re-added. State this in the report as owner-verified.

## Risks

- **Camera restart timing** differs by phone; if the suppression window in step 2 proves too short (the same item re-prompts) or too long (a fast second scan of the *same* product is ignored), it is one number in `scan-input.js`. Re-scanning the same product on purpose within 3.5 s is rare on a delivery; scanning a case of six is one scan of the outer code.
- **Cancel with the camera off**: `cameraWanted` false → no-op, as intended.
