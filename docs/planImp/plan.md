# Plan: Shop mode cycle 33 — Delivery scan: prompt without scrolling, Summary out of the way

**Status:** ACCEPTED
**Planner:** Fable 5.1
**Date:** 2026-09-28

## Goal

On a phone, after scanning an item the quantity prompt (name, stepper, "Add") opens **below the camera block**, so the person has to scroll to reach "Add" on every item. The prompt must be fully visible without scrolling. And the sticky "Summary" bar at the bottom takes a strip of every screen although it is only needed at the end of the delivery; move it out of the way.

Two small things from cycle 32's review ride along: a troubleshooting note for the compiled-view permission trap, and one header sentence in the scan field naming its two timing constants.

## Context (verified 2026-09-28)

- `resources/views/shop/delivery-scan.blade.php`: left `shop-stack` order is scan field (line 27, `x-shop.scan-input inline`, whose camera block `div.shop-scan__camera` is `aspect-ratio 4/3; max-height 320px` when open) → prompt card (line 30, `section.shop-card x-show="pending" x-ref="prompt"`) → notice/status (76–92) → correction card (94, `x-ref="correct"`) → error state. Right `shop-stack`: Items header `div.shop-between` (`h2.shop-group-title` + the ghost sort button, ~line 128–136) → `shop-list` → empty state. Then `div.shop-actions` (line 169, sticky bottom, design `.shop-actions { position: sticky; bottom: 0; … }`) holding the single Summary link with `span.shop-btn__count` for `progress.issues`.
- On a 390 px phone: topbar 72 px, scan field 64 px, camera ≈ 268 px, so the prompt starts ≈ 450 px down and its "Add" button lands ≈ 850 px — below the fold of an 844 px screen. On ≥ 1024 px the `shop-split` is two columns and the prompt is beside the list.
- `resources/js/shop/delivery-scan.js`: opening the prompt (`this.pending = {…}` ~line 211, then `announceDone()` line 223) does **not** scroll; the correction card does (`edit()` line ~340: `$nextTick(() => this.$refs.correct?.scrollIntoView({ block: 'nearest' }))`).
- Design classes available: `.shop-between` (flex, wrap, space-between), `.shop-inline` (flex, wrap, centre), `.shop-btn--ghost`, `.shop-btn__count`, `.shop-actions--static` (non-sticky variant). The design's Screen 05 v2 shows the sticky Summary bar; this cycle deviates from it at the owner's request.
- Tests (`tests/Feature/Shop/ShopDeliveryTest.php`): line 478–479 assert the summary href and the word "Summary" on the scan page; nothing pins `shop-actions` on it. The contract test allows only `shop-*` classes and `x-shop.*` components.
- Cycle 32 notes: `storage/framework/views` is `drwxrwsr-x jon www-data`; files compiled by `www-data` are not group-writable, so after a browser check the CLI suite can fail with "Permission denied" on a Blade edit until `php artisan view:clear`. `docs/development/quick-start-guide.md` has `## Debugging and Troubleshooting` → `### Common Issues` (line 381–383). And `scan-input.js` now has two timing constants that guard the same failure — the 3.5 s same-code suppression after a resume and the 1 s burst window — with no shared comment.
- A rejected save (`! data.success` in `commit()`) leaves the prompt open and the camera paused. **Decision: keep it so.** Resuming the camera under an open prompt is the re-detect hazard cycle 29 closed; the person taps Add again or Cancel, and Cancel resumes.
- Baseline: measure before starting (15 failed / 804 passed at cycle 32's acceptance).

## Constraints

- No commits, no deploys. No CSS change; design block byte-identical; contract test green.
- The two-step prompt, the correction card and the sort toggle behave exactly as now; only their placement changes.
- Summary must still be reachable from the scan page with its issue count, and the test's href/"Summary" assertions must keep passing.

## Out of scope

- Hiding the camera block while the prompt is open (the frozen "Got it" frame stays).
- Changes to the summary page or the design copy.

## Steps

### 1. Prompt above the scan field
`delivery-scan.blade.php`: move the prompt `section` (lines 30–74, unchanged inside) to be the **first** child of the left `shop-stack`, before the `@if ($session['completed'])` / scan-input block. When it opens, it now sits directly under the top bar and the camera block is below it. Update the comment on the card: it sits above the scan field so "Add" is on screen on a phone.
`delivery-scan.js`: after `this.pending = {…}` and before `announceDone()` in `onScan()`, `this.$nextTick(() => this.$refs.prompt?.scrollIntoView({ block: 'nearest' }))` — the same call the correction card makes — so a person who had scrolled down the list still sees the prompt.
**Check:** at 390 px in DevTools with the camera block forced visible (`cameraOpen = true` on the scan field's Alpine data), type a code + Enter: the whole prompt including "Add" is within the first 844 px with no scrolling; measure `$refs.prompt.getBoundingClientRect().bottom` and report it. At ≥ 1024 px the two-column layout still shows the prompt in the left column.

### 2. Summary out of the sticky bar
`delivery-scan.blade.php`: delete the `div.shop-actions` block. In the Items header `div.shop-between`, wrap the sort button and a new Summary link in a `div.shop-inline`:
```html
<a class="shop-btn shop-btn--ghost" href="{{ route('shop.deliveries.summary', ['delID' => $session['id'], 'supplierID' => $session['supplierId']]) }}">
    <x-shop.icon name="list-checks" size="sm" />Summary<span class="shop-btn__count" x-show="progress.issues > 0" x-cloak x-text="progress.issues"></span>
</a>
```
(`list-checks` exists in the sprite; use `check` if it reads better.) The header wraps on a narrow phone, which is acceptable; report what 390 px looks like.
Also add the same Summary link as a static bar after the list — `div.shop-actions.shop-actions--static` with the primary-styled button — so at the end of the delivery, having scrolled through the list, the person lands on it. Two links to the same place, one always reachable in the header, one where the list ends. If you find the header alone is enough at 390 px and the bottom one duplicates awkwardly, keep only the header one and say so.
**Check:** `ShopDeliveryTest` lines 478–479 still pass; no `position: sticky` bar remains on the scan page (`assertDontSee('class="shop-actions"', false)` on the scan page, added to the test — the static variant carries a different class string `shop-actions shop-actions--static`).

### 3. Correction card placement
Leave it where it is (after the status block); it already scrolls into view. Confirm at 390 px that tapping a row brings the whole card, including "Done", on screen; if the camera block pushes "Done" below the fold, move the correction card above the scan field too (directly after the prompt) and say so.

### 4. Troubleshooting note
`docs/development/quick-start-guide.md` under `### Common Issues`: a short entry "Tests fail with `file_put_contents(storage/framework/views/…): Permission denied`" — cause (views compiled by the web server are not group-writable), the workaround (`php artisan view:clear`), and the durable fix for the owner to run once: `sudo setfacl -Rm g:www-data:rwx storage/framework/views bootstrap/cache && sudo setfacl -Rdm g:www-data:rwx storage/framework/views bootstrap/cache` (needs `acl` installed).
**Check:** the entry renders in the doc's list.

### 5. One sentence in `scan-input.js`
Header comment: name the two timing constants — the 1 s burst window in `capture()` and the 1500 ms `lastAt` push in `restartCameraIfWanted()` (3.5 s same-code suppression with `detected()`'s 2 s) — and say they guard the same failure (an unintended second read) from two directions, so tune them together. No code change.

### 6. Tests, build, tidy
- `ShopDeliveryTest`: the prompt precedes the scan field in the rendered HTML (`strpos` of `x-ref="prompt"` < `strpos` of `shop-scan__input`); the header contains the Summary link with `shop-btn__count`; no sticky `shop-actions` (step 2's assertion); the `scrollIntoView` for the prompt is present in the JS (source assertion beside the existing camera ones).
- `npm run build`; `php artisan view:clear`; Pint on the test file.

## Verification (report every item with what you saw)

1. `php artisan test` summary against your measured baseline; no new failures.
2. Contract: `ShopViewContractTest` green; design-block `cmp` prints nothing.
3. Browser at 390 × 844 (DevTools device toolbar) on the delivery scan page with the camera block forced visible: scan → prompt fully on screen, "Add" bottom edge < 844 px (report the number); scroll to the bottom of the list, scan again → the page scrolls so the prompt is visible; tap a row → correction card with "Done" visible (report); the Items header with Summary and the count at 390 px (describe the wrap); the bottom static bar if kept.
4. Browser at 1280 px: two columns, prompt left, list right, nothing overlapping.
5. Dev data restored, as in cycles 29–32.

## Risks

- **Header crowding at 390 px**: "Items 164", "New first" and "Summary 2" may wrap to two lines. `shop-between` wraps by design; if it looks wrong, shorten the sort label to an icon-only ghost button with `aria-label` and say so.
- **Prompt above the field changes the eye-line** for keyboard-wedge users on the till PC: the field is now second. The field keeps focus regardless (cycle 32), so scanning is unaffected.

## Review

Reviewed 2026-09-29 against `implemented.md` (read to the end, four notes) and the working-tree diff. Cycle 32's uncommitted changes share the tree (controller `max:9999`, `capture()` in `scan-input.js`, its tests); they were accepted with cycle 32 and are not re-reviewed here.

**Criteria**
- Step 1 prompt above the scan field — pass. The prompt `section` is the first child of the left `shop-stack`, contents unchanged; `onScan()` calls `$refs.prompt?.scrollIntoView({ block: 'nearest' })` before `announceDone()`. Implementer measured "Add" bottom at 693 px of 844 (was 1038 px, 194 px below the fold).
- Step 2 Summary out of the sticky bar — pass with Deviation 1. No `<div class="shop-actions">` remains; the static variant follows the list; the header carries a Summary link with the issue count.
- Step 3 correction card — pass, left in place; "Done" at 827 px of 844 (implementer's measurement).
- Step 4 troubleshooting note — pass; entry present under Common Issues with the workaround and the one-time `setfacl` fix.
- Step 5 header sentence in `scan-input.js` — pass; comment only.
- Step 6 tests/build — pass; two new tests, both assert structure rather than wording.

**Verification (rerun by the Planner)**
1. `php artisan test` → 15 failed, 806 passed (3441 assertions); the 15 are the baseline classes. No new failures.
2. `php artisan test --filter='ShopDeliveryTest|ShopViewContractTest|ShopStockScanTest|ShopVouchersTest'` → 76 passed. `git diff --stat resources/css/shop.css` is empty, so the design block is untouched.
3. `list-checks` exists in `public/images/shop-icons.svg`; `shop-sr-only`, `shop-inline` and `shop-actions--static` exist in `shop.css`.
4. Browser measurements at 390 px and 1280 px are the implementer's (iframe method, real media queries). Not repeated by the Planner; the owner should try one delivery on a real phone.

**Deviations**
1. Header Summary link is icon + count with the word in `shop-sr-only` — accepted. The plan's markup measured 160 px on three lines, worse than the bar it removed; the chosen variant costs 92 px and keeps the sort label an earlier cycle chose on purpose. The labelled Summary button still ends the list.
2. Both Summary links kept — accepted; the plan allowed either.

**Notes for Planner**
1. No "hide on narrow" utility — deferred. A one-line `.shop-wide-only` under APP ADDITIONS would bring the word "Summary" back on the till PC. Do it only if the owner misses the word there.
2. `resize_window` does not change the page viewport on this machine; a same-origin iframe sized 390 × 844 does — accepted and adopted: future plans that ask for a phone-width check will name the iframe method.
3. The idle lock fired during a long measurement — accepted as cycle 26 working. Future plans with long browser checks will say to use an untrusted browser session for measuring.
4. 17 px clearance under the correction card's "Done" — deferred; safe while the card stays 332 px. Re-measure if a field is ever added to that card.

**Found in review (not in the report)**
- `tests/Feature/Shop/ShopDeliveryTest.php`: the two cycle 33 tests were inserted between cycle 32's docblock ("A USB hand scanner types its digits…") and the test it describes, `test_a_barcode_sized_quantity_is_refused_by_both_endpoints`. Two docblocks now stack above the cycle 33 test and the barcode test has none. Harmless to the suite; move the docblock back in the next cycle that touches this file.

**Owner actions**
- Try one delivery on a real phone: scan, confirm "Add" is on screen without scrolling, and that Summary is findable in the Items header and at the end of the list.
- Optional, once: the `setfacl` fix from the new troubleshooting note.

**Archive**: `mkdir -p docs/planImp/archive/2026-09-29-shop-mode-cycle-33` and move `plan.md` and `implemented.md` there.
