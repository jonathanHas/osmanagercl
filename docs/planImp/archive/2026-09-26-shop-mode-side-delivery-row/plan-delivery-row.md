# Shop mode side fix — Delivery scan rows squeeze the product name to one character per line on a phone

Status: ACCEPTED
Revision: 3
Planner: Fable 5.1
Date: 2026-09-26

This plan runs alongside the vouchers cycle. Implementer: write your report to `docs/planImp/implemented-delivery-row.md` (not `implemented.md`, which belongs to the vouchers cycle). Everything else in `planimp.md` applies.

## Goal

On a phone-width screen, a delivery row whose quantity editor is open shows the product name as a vertical column of single letters ("C / o / o / l / f / i / n …", see the owner's screenshot of 2026-09-26). The row's right-hand controls (quantity with the status pill, the −/+ stepper, and the pencil) are all fixed-width and together are wider than the row leaves for the name, and the design's `overflow-wrap: anywhere` on the title then breaks it at every character. Fix: let the controls wrap onto their own line, right-aligned, whenever the name would otherwise get less than a readable minimum width. Desktop and tablet layouts are unchanged.

## Context

- Row markup: `resources/views/shop/delivery-scan.blade.php` lines 109–128: `<div class="shop-row" …>` containing `shop-row__main` (code + title), `shop-row__aside` (`shop-row__qty` + `shop-pill`), a `shop-qty` stepper shown only while `editing === row.barcode`, and the pencil `shop-iconbtn shop-iconbtn--ghost` ("Correct quantity"). The stepper and pencil were added in cycle 8 Revision 2; the design's row (`docs/design/shop-mode/screen-05-delivery-scan.html`) has only main + aside, which is why the design rules never had to handle this width.
- Design rules (`resources/css/shop.css`, must stay byte-identical): `.shop-row { display: flex; align-items: center; gap: var(--shop-space-4); min-height: 76px; padding: var(--shop-space-3) var(--shop-space-4); }`, `.shop-row__main { flex: 1; min-width: 0; … }`, `.shop-row__title { … overflow-wrap: anywhere; }`, `.shop-row__aside { flex: none; … align-items: flex-end; text-align: right; }`, `.shop-qty { display: flex; align-items: center; gap: var(--shop-space-2); flex: none; }`, `.shop-iconbtn` 56 px.
- Arithmetic on a 400 px viewport: page padding 16 + 16, row padding 16 + 16 → 336 px inside the row; controls when editing ≈ aside 70 + stepper (56 + ~40 + 56 + 16) + pencil 56 + three gaps 48 ≈ 340 px, so the name gets nothing. Without the editor ≈ 70 + 56 + 32 = 158 px, leaving ~178 px for the name, which is why the fault appears only while editing.
- No test asserts the row's markup (`grep -n "shop-row" tests/Feature/Shop/ShopDeliveryTest.php` → nothing). `ShopViewContractTest` accepts any `shop-*` class in the view.
- Other Shop rows do not have this problem: stock scan's last-scans and find product's rows carry a small aside only; the request board uses the `shop-req` grid. No other screen combines a stepper and a button on a `shop-row`.

## Constraints

- Do not commit, push or deploy.
- Design block byte-identical; the fix is one wrapper element in the view and rules under `APP ADDITIONS`.
- No behaviour change: the stepper, pencil, `is-latest`/`is-off` classes and the sort order stay as they are.
- Do not touch `resources/js/shop/delivery-scan.js`.

## Out of scope

- The prompt card above the list (it uses `shop-stepper`, which is a full-width grid and already fine).
- Other screens' rows.

## Steps

### 1. Group the controls and let the row wrap
Files: `resources/views/shop/delivery-scan.blade.php`, `resources/css/shop.css`
What: the row root gets a second class: `<div class="shop-row shop-row--wrap" …>`. Wrap the aside, the stepper and the pencil (everything after `shop-row__main`) in `<div class="shop-row__controls">…</div>`, leaving their markup unchanged inside. App rules:
```css
/* Delivery rows carry a stepper and a pencil beside the quantity (cycle 8), more
   than the design's row expects on a phone. Let the controls wrap to their own
   line, right-aligned, once the name would get less than 180 px. */
.shop-row--wrap { flex-wrap: wrap; }
.shop-row--wrap > .shop-row__main { flex: 1 1 180px; }
.shop-row__controls { display: flex; align-items: center; justify-content: flex-end; gap: var(--shop-space-3); flex: 0 1 auto; margin-left: auto; min-width: 0; }
```
How it behaves: while the main's 180 px basis and the controls fit on one line (tablet, desktop, and phone with the editor closed) nothing moves; when the controls no longer fit beside 180 px (phone with the editor open) they wrap to a second line and `margin-left: auto` keeps them right-aligned. Vertical rhythm: `.shop-row` has `gap` on both axes via `flex-wrap`, so the second line sits `--shop-space-4` below.
Check: `php artisan test --filter="ShopDeliveryTest|ShopViewContractTest"` green; design block `cmp` identical; `sed -n '/APP ADDITIONS START/,$p' resources/css/shop.css | grep -c "shop-row--wrap\|shop-row__controls"` → 3.

### 2. Pin it
Files: `tests/Feature/Shop/ShopDeliveryTest.php`
What: in the existing scan-page rendering test (the one that asserts the page shell), add assertions that the markup contains `class="shop-row shop-row--wrap"` and `class="shop-row__controls"`, and that the stylesheet's `APP ADDITIONS` section contains `.shop-row--wrap { flex-wrap: wrap; }` (same shape as cycle 16's stylesheet assertion, same reason: the fault is geometry).
Check: `php artisan test --filter=ShopDeliveryTest` green; flip the rule to `flex-wrap: nowrap` and confirm the test fails, then restore.

### 3. Build, format, note
Files: `docs/design/shop-mode/README.md`, all touched
What: README: one sentence under the delivery bullet that the row's controls wrap on phones via `.shop-row--wrap` / `.shop-row__controls`. `npm run build`; `./vendor/bin/pint --test --dirty`.
Check: build succeeds.

### 4. (Rev 2) One line whenever it fits
Files: `resources/css/shop.css`, `tests/Feature/Shop/ShopDeliveryTest.php`
Why: with the widest pill ("Unexpected") a closed row's controls are 187 px, so a 180 px name basis wraps closed rows on a 430 px phone that used to fit on one line. The implementer's measurements (see `plan-delivery-row-imp.md`) show 163 px is available there.
What: `.shop-row--wrap > .shop-row__main { flex: 1 1 140px; }` and `.shop-row__controls { … gap: var(--shop-space-4); … }` (the row's own spacing, as before the fix). Nothing else changes. Expected: 430 px closed → one line (163 ≥ 140); 430 px open → two lines, name full width; 768 px open → one line; 1280 px open → two lines (122 < 140), which reads better than a 122 px name.
Check: `php artisan test --filter=ShopDeliveryTest` green; re-measure the four cases above in the browser as in Revision 1 (iframes are fine) and record them.

### 5. (Rev 3) The controls line must never be wider than the row
Files: `resources/css/shop.css`, `tests/Feature/Shop/ShopDeliveryTest.php`
Why: with the editor open and a wide pill the controls line needs 367 px, and `.shop-list` clips overflow, so on a 400 px phone the quantity and pill are cut off at the left (55 px lost at 360 px). The measurements are in Revision 2's report.
What: `.shop-row__controls { … flex-wrap: wrap; row-gap: var(--shop-space-2); }` (keep every other declaration). With `justify-content: flex-end` the stepper and pencil drop to a further line under the quantity and pill on narrow phones, right-aligned; on anything wider nothing changes because the line fits. Add `flex-wrap: wrap` for `.shop-row__controls` to the stylesheet assertion in the existing test.
Check: `php artisan test --filter=ShopDeliveryTest` green; measure at 360 px and 400 px with the editor open (iframes are fine): nothing clipped, the quantity/pill line and the stepper/pencil line both fully inside the row; 430 px open unchanged (two lines); 768 px open still one line.

## Verification

1. `php artisan test --filter=Shop` → green; `php artisan test` → 17 failed, the identical set.
2. Design block `cmp` identical; contract greps clean.
3. `npm run build` succeeds.
4. Manual, dev app (the implementer may drive the browser read-only): open a delivery scan page with rows at a 400 px wide window: names read normally; tap a row's pencil: the stepper appears and the controls drop to a second line under the name, right-aligned, and the name keeps its width; at 768 px and 1280 px the row is a single line as before; `is-latest` highlighting unchanged. Then the owner on the phone: the same rows as the screenshot.

## Risks

- **Rows grow taller on phones while editing**: two lines instead of one; intended.
- **`overflow-wrap: anywhere` stays** (design rule): a single very long word can still break, but only at 180 px, which is normal wrapping rather than one letter per line.
- **Vouchers cycle in flight**: this touches only `delivery-scan.blade.php`, `shop.css` (additions), one test and the README; the vouchers cycle touches none of those except `shop.css` (it expects no additions) and its own files, so the two implementer sessions cannot conflict. If both add to `shop.css`, append; the design block is above both.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented-delivery-row.md` and `plan-delivery-row-imp.md` to the end, and the diffs of the view, the stylesheet additions, the test and the README. Suite: 17 failed / 579 passed, the identical set. Design block identical; the three rules are the plan's; the test proves it can fail.

**Steps 1–3: pass**, and the reported fault (one letter per line with the editor open on a phone) is fixed: the name keeps its full width and the controls sit on their own line.

**Notes (from `plan-delivery-row-imp.md`).**
1. Closed rows now wrap on a phone when the pill is wide: **fixed in Revision 2** by lowering the name's basis to 140 px, which the implementer's own numbers show keeps closed rows on one line at 430 px.
2. Open rows wrap at 1280 px because the desktop list column is narrower than the tablet's: **accepted**; two lines beat a 122 px name, and the plan's expectation was wrong.
3. Controls gap 12 px vs the row's 16 px: **restored to 16 px in Revision 2**.
4. Window could not be resized, iframes used instead: fine. Suite set not diffed exactly because of the shared tree: acceptable; I reran it here.
5. Notes went into a separate `-imp.md` file rather than the report's own `## Deviations` / `## Notes for Planner`: keep them in the report next time (the protocol template has the sections); the file is archived with the rest.

**Verdict:** READY, Revision 2. Step 4 only; steps 1–3 stand.

### Revision 2 (2026-09-26, Planner)

Read the Revision 2 section of `implemented-delivery-row.md` to the end. Reran `php artisan test` with the whole tree: 17 failed / 579 passed, the identical set. Stylesheet: basis 140 px, gap 16 px, design block identical.

**Step 4: pass.** Closed rows are one line again at 430 px; open rows wrap with the name at full width; 768 px open is one line; 1280 px open wraps by choice.

**Notes.**
1. Open controls line clipped on phones narrower than about 415 px: **fixed in Revision 3**, step 5 (the controls wrap internally). Revision 1 had the same fault a little smaller; the measurement is what found it.
2. The Coolfin delivery's scanned count changed from 32 to 27 during the check, and the implementer only toggled the pencil: **owner to confirm** it was their own phone test on that delivery (they were testing it at the time). Nothing to do unless it was not.
3. Notes now inside the report: good.

**Verdict:** READY, Revision 3. Step 5 only.

### Revision 3 (2026-09-26, Planner)

Read the Revision 3 section of `implemented-delivery-row.md` to the end. Stylesheet: the controls wrap with an 8 px row gap, every other declaration kept; design block identical; the regex assertion pins the wrap and was shown to fail when flipped. `php artisan test --filter="ShopDeliveryTest|ShopViewContractTest"` → 37 passed; the change is two declarations, so the full suite from Revision 2 (17 failed / 579 passed) stands.

**Step 5: pass.** Nothing is clipped at 360, 400 or 430 px; every line is right-aligned inside the row; 768 px is one line.

**Notes.**
1. On phones the break falls after the stepper, so the pencil sits alone on a third line rather than the stepper and pencil dropping together: **accepted**. Quantity, pill and stepper on one line is a good reading order for a correction, and the pencil at the right underneath is where a thumb expects the close control. Not worth another revision or a markup change.
2. 430 px open is three lines rather than two: **accepted**, follows from the same break and Revision 2 only fitted by spilling into the padding.
3. Grouping the stepper and pencil to break together: **not needed** given 1.
4. The Coolfin count change: **the owner confirmed it was their own phone test.** Closed.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-side-delivery-row/` with both report files.
