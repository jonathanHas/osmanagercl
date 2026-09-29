# Plan: Shop mode cycle 31 — Camera pause loose ends

**Status:** ACCEPTED
**Planner:** Fable 5.1
**Date:** 2026-09-28

## Goal

Three small things left by cycle 30 (pause instead of stop):

1. html5-qrcode draws its own banner when paused: a dark translucent strip across the top of the camera reading "Scanner paused". It will show on production over the frozen frame, beside our "Got it" label. Hide it.
2. The Vouchers screen never announces that a scan is finished, so after a camera scan its frame stays frozen with "Got it" indefinitely. Resume the camera after the lookup, as the other screens do.
3. `docs/planImp/.needed.txt.kate-swp` is an editor swap file committed by accident (and re-committed with cycle 30). Untrack it and ignore swap files.

## Context (verified 2026-09-28)

- `node_modules/html5-qrcode/esm/html5-qrcode.js` lines 523–540: `createScannerPausedUiElement(rootElement)` builds a `<div>` with `innerText "Scanner paused"`, inline `display:none; position:absolute; top:0; z-index:1; background:rgba(9,9,9,0.46); color:#FFECEC; text-align:center; width:100%`, no id or class, appended to the mount element (our `div.shop-scan__mount`). `pause()` sets its `display` to `block` inline; `resume()`/`stop()` set it back to `none`. The mount's other children: the `<video>`, a hidden `<canvas>`, and `div#qr-shaded-region`.
- `resources/css/shop.css` APP ADDITIONS lines 579–581 already style the mount and hide `#qr-shaded-region` ("the design draws its own reticle"). A stylesheet `display:none !important` beats the inline `display:block`.
- `resources/js/shop/vouchers.js` line ~145: after a successful lookup, `announceDone()` only. Other screens dispatch `shop-scan-saved` after their action (stock-scan line ~162, labels line ~103, delivery-scan `announceSaved()`). The scan field resumes on that event (`restartCameraIfWanted()`), pushing the same-code suppression 1500 ms as before.
- `git ls-files docs/planImp` lists `docs/planImp/.needed.txt.kate-swp` (tracked, binary, modified whenever the editor is open). `.gitignore` has no swap-file pattern (check).
- Baseline: 15 failed / 800 passed at cycle 30's acceptance; measure again before starting.

## Constraints

- No commits, no deploys. `git rm --cached` and a `.gitignore` edit are staging changes, not commits; leave them staged for the owner.
- Design block of `resources/css/shop.css` unchanged; the new rule goes under APP ADDITIONS beside the existing mount rules.
- No change to the pause/resume state machine.

## Out of scope

- The stray `docs/planImp/needed.txt` (2 bytes) — the owner's file; leave it.
- Any other camera behaviour.

## Steps

### 1. Hide the library's paused banner
`resources/css/shop.css`, APP ADDITIONS, next to the `#qr-shaded-region` rule: `.shop-scan__mount > div:not(#qr-shaded-region) { display: none !important; }   /* html5-qrcode's own "Scanner paused" strip; the design shows "Got it" instead */`. Do **not** hide `video` or `canvas` (they are not `div`s). If the library nests the banner deeper than a direct child in this version, adjust the selector and say so.
**Check:** `cmp` design block prints nothing; `ShopViewContractTest` green. In the browser on dev, append a `<div>` with the library's inline styles to a `.shop-scan__mount` and confirm it is not displayed while `#qr-shaded-region` handling is unchanged.

### 2. Vouchers resumes the camera
`vouchers.js`: after the successful lookup's `announceDone()`, dispatch `shop-scan-saved` with the same one-line comment as labels. A source assertion in `ShopVouchersTest` in the cycle-29 style.
**Check:** `php artisan test --filter=ShopVouchersTest` green.

### 3. Untrack the swap file
`git rm --cached docs/planImp/.needed.txt.kate-swp`; add to `.gitignore`: `*.kate-swp` and `.*.swp` (Vim) — two lines under a comment "editor swap files". Leave the working-tree file alone.
**Check:** `git status` shows the deletion staged and `.gitignore` modified; `git check-ignore -v docs/planImp/.needed.txt.kate-swp` names the rule.

### 4. Build and tidy
`npm run build`; `php artisan view:clear`; Pint on the one test file.

## Verification (report every item with what you saw)

1. `php artisan test` summary against your measured baseline; no new failures.
2. Contract `cmp` and `ShopViewContractTest`.
3. Step 1's browser check with the injected banner; step 3's `git status` and `check-ignore` output.
4. Owner on production: after a scan the frozen frame shows only "Got it", no dark strip; on Vouchers the camera comes back after the lookup.

## Risks

- **Selector over-reach**: if a future html5-qrcode version wraps the video in a `div`, the rule would hide the picture. Pin it with a comment naming the library version (2.3.8) and the element it targets.
