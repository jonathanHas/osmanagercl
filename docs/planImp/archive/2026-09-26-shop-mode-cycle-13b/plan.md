# Shop mode cycle 13b — Requests board: an open "more" menu must not sit over live controls

Status: ACCEPTED
Revision: 2
Planner: Fable 5.1
Date: 2026-09-26

## Goal

Fix the finding in `docs/planImp/findings/2026-09-26-request-menu-overlap.md`: with a row's ⋯ menu open, a tap aimed at another row's ⋯ button (or anything else under the menu) lands on the menu's own items, which include "Undo last step", "Not available" and "Cancel request". While any menu is open, a transparent full-screen backdrop sits under the menu and above the page; a tap anywhere outside the menu closes it and is swallowed. This is option 1 from the finding, and it applies to downward menus too.

## Context

- Cycle 13 accepted and archived (`docs/planImp/archive/2026-09-26-shop-mode-cycle-13/`); its files and cycle 12's may still be uncommitted.
- `resources/js/shop/requests-board.js`: `q`, `matches()`, `groupMatches()`, `anyMatch()`, `rowsIn()`, `menuToggled(details)` (closes the others, measures, toggles `is-up`), `closeMenus(except)`. The board scope is `<div class="shop-stack" x-data="shopRequestsBoard()" x-on:keydown.escape.window="closeMenus()">` in `resources/views/shop/partials/requests-staff.blade.php`; each row's `<details class="shop-more" x-on:toggle="menuToggled($el)">` is in `resources/views/shop/partials/request-row.blade.php`.
- Stacking, from the design stylesheet: `.shop-menu` is `position: absolute; z-index: 40` inside `.shop-more { position: relative }`; `.shop-topbar` sticky `z-index: 30`; `.shop-actions` sticky `z-index: 20`; `.shop-toasts` 50; `.shop-sheet-backdrop` 60; app rule `.shop-peek` 45. No ancestor of a row creates a stacking context, so a `position: fixed` backdrop with `z-index: 35` sits above rows, the bottom bar and the top bar, and below the open menu, the toasts and the sheet.
- The `<summary>` (the ⋯ button) of the open menu is under the backdrop too, so tapping it again hits the backdrop, which closes the menu: same result as before.
- Tests: `tests/Feature/Shop/ShopRequestsTest.php` (8), `staff_board_shows_actions_and_form` asserts the menu markup.

## Constraints

- Do not commit, push or deploy.
- Keep the upward flip (it is what put "Cancel request" on screen). Keep native `<details>`.
- No change to routes, controllers, services or the guest board.
- One app rule in `APP ADDITIONS`; the design block stays byte-identical.
- Leave the two audit rows the finding mentions alone.

## Out of scope

- Confirmation dialogs on menu items; the undo model stands.
- The sheet's backdrop (already swallows clicks via `@click.self`).

## Steps

### 1. Backdrop rule
Files: `resources/css/shop.css`
What: under `APP ADDITIONS`, after the `is-up` rule: `.shop-menu-backdrop { position: fixed; inset: 0; z-index: 35; background: transparent; }`.
Check: design block `cmp` identical; `grep -c "shop-menu-backdrop" resources/css/shop.css` → 1.

### 2. Backdrop state and element
Files: `resources/js/shop/requests-board.js`, `resources/views/shop/partials/requests-staff.blade.php`
What: add `menuOpen: false` to the state. In `menuToggled(details)`: after the existing early return for a closed details, set `this.menuOpen = this.rowsIn(this.$root).length > 0 && !! this.$root.querySelector('details.shop-more[open]')` at the end of both branches (simplest: compute `this.menuOpen = !! this.$root?.querySelector('details.shop-more[open]')` as the last line of `menuToggled()` and of `closeMenus()`). In the staff partial, inside the board scope (as the first child of the `shop-stack`, before the filter row): `<div class="shop-menu-backdrop" x-show="menuOpen" x-cloak @click="closeMenus()" aria-hidden="true"></div>`. Because `closeMenus()` sets `open = false` on each details, the `toggle` event fires and `menuToggled()` runs again with `details.open === false`, which is harmless.
Check: node exercise with a stubbed `$root` holding two `details.shop-more`: open the first via `menuToggled` → `menuOpen` true; `closeMenus()` → both closed, `menuOpen` false; open one, then `menuToggled` on the second with `open = true` → the first is closed, the second open, `menuOpen` true. `staff_board_shows_actions_and_form` asserts `class="shop-menu-backdrop"` and `x-show="menuOpen"`.

### 3. Format, build, tidy the finding
Files: all touched; `docs/planImp/findings/2026-09-26-request-menu-overlap.md`
What: `./vendor/bin/pint --dirty` (no PHP touched; runs clean); `npm run build`. Append a line to the finding: "Fixed in cycle 13b (backdrop under the open menu)." The Planner moves the finding into this cycle's archive folder on acceptance.
Check: `./vendor/bin/pint --test --dirty` clean; build succeeds.

### 4. (Rev 2) The "more" actions open inside the row, not over the rows around it
Files: `resources/views/shop/partials/request-row.blade.php`, `resources/css/shop.css`
Why: Revision 1's backdrop protects what the menu does not cover; the finding's hazard is what it does cover, and that is another row's ⋯ button. A floating menu over a list of identical rows will always overlap a neighbour's controls somewhere. The design stylesheet already provides `.shop-menu--static { position: static; }` for exactly this: the same menu, laid out in flow.
What: replace the `<details class="shop-more">` with an inline panel that expands the card.
- Row root: `<article class="shop-req …" x-data="{ more: false }" …>` (the existing `x-show="matches($el.dataset.text)"` stays on the article; note `x-data` and `x-show` on the same element is fine).
- In `shop-req__actions`, in place of the `<details>`: `<button class="shop-iconbtn" type="button" :aria-expanded="more" aria-label="More actions" @click="more = ! more"><x-shop.icon name="more" /></button>`.
- As the **last child of the article** (after `shop-req__actions`): `<div class="shop-menu shop-menu--static shop-req__more" role="group" aria-label="More actions" x-show="more" x-cloak>` containing exactly the items the `<details>` held (Edit link, Put aside now, Undo last step, Not available, sep, Cancel request), unchanged markup, `role="menuitem"` → drop the roles (it is no longer a menu; plain buttons and a link in a group).
- App rules, replacing the `is-up` and `shop-menu-backdrop` rules: `.shop-req__more { grid-column: 1 / -1; flex-direction: row; flex-wrap: wrap; gap: var(--shop-space-2); padding: var(--shop-space-2); background: var(--shop-surface-2); box-shadow: none; }`, `.shop-req__more .shop-menu__item { width: auto; padding: 0 18px; background: var(--shop-surface); }`, `.shop-req__more .shop-menu__sep { width: 100%; height: 0; margin: 0; }` (the separator becomes a line break so "Cancel request" sits on its own row; if that reads oddly in the browser, drop the sep rule and let it flow). The panel takes a new implicit grid row spanning every column at every breakpoint, so the card grows downward and nothing overlaps anything.
- The `.shop-iconbtn[aria-pressed="true"]` design rule gives a toggled look for pressed buttons; use `:aria-pressed="more"` as well as `aria-expanded` so the ⋯ shows its open state.
Check: `php artisan test --filter=ShopViewContractTest` green; the design block `cmp` identical; `grep -c "is-up\|shop-menu-backdrop" resources/css/shop.css` → 0.

### 5. (Rev 2) Remove the popup machinery
Files: `resources/js/shop/requests-board.js`, `resources/views/shop/partials/requests-staff.blade.php`, `tests/Feature/Shop/ShopRequestsTest.php`
What: delete `menuToggled()`, `closeMenus()`, `syncMenuOpen()`, `menuOpen` and the backdrop `<div>`; drop `x-on:keydown.escape.window="closeMenus()"` from the board scope (the sheet keeps its own Escape). The module is back to `q`, `matches`, `groupMatches`, `anyMatch`, `rowsIn`. Tests: replace the assertions on `x-on:toggle="menuToggled($el)"`, `class="shop-menu-backdrop"` and `x-show="menuOpen"` with `x-data="{ more: false }"`, `:aria-expanded="more"`, `shop-menu--static shop-req__more` and `x-show="more"`; keep `#more`.
Check: `grep -c "menuToggled\|closeMenus\|menuOpen\|is-up" resources/js/shop/requests-board.js resources/views/shop/partials/*.blade.php` → 0; `php artisan test --filter="ShopRequestsTest|CustomerRequestTest"` green.

### 6. (Rev 2) The finding's closing line
Files: `docs/planImp/findings/2026-09-26-request-menu-overlap.md`
What: replace "Fixed in cycle 13b (backdrop under the open menu — option 1 above)." with: "Cycle 13b Revision 1 added a backdrop (option 1), which the browser check showed cannot cover the hazard: the menu itself sits over the next row's ⋯. Revision 2 removed the floating menu: the actions now expand inside the row (`.shop-menu--static`), so no control is ever under another. Closed."
Check: the file ends with that paragraph.

## Verification

1. `php artisan test --filter="ShopRequestsTest|ShopViewContractTest|CustomerRequestTest"` → green.
2. `php artisan test` → 17 failed, the identical set; passed count unchanged (560).
3. Design block `cmp` identical; `grep -rn "<script\|<style" resources/views/shop/` → nothing; `grep -c "route(\|fetch(" resources/js/shop/requests-board.js` → 0.
4. `npm run build` succeeds.
5. Manual (Rev 2), signed in on the dev app; the implementer may drive the browser but must not act on real rows' items — measure with `document.elementFromPoint` as in Revision 1 rather than clicking: open the last row's ⋯: the card grows and the actions appear inside it below the strip; at every row the point at the centre of the row above's ⋯ button resolves to **that button**, never to an action; open a second row's ⋯: both stay open, nothing overlaps; the ⋯ shows a pressed look while open; at phone width the actions wrap onto two lines inside the card; "New request" and the sheet unaffected.

## Risks

- **The backdrop covers the sticky bottom bar and the top bar while a menu is open.** Intended: the first tap closes the menu, the second does the thing. Same as every dropdown with a scrim.
- **iOS Safari** needs the backdrop to be a real element with a click handler to receive taps; it is.
- **Reactivity**: `menuOpen` is set imperatively from the `toggle` handler, so the backdrop shows on the same tick the menu opens.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end. The three steps were done as written, tests green, design block identical; but the browser measurement in Verification 5 shows the plan's approach cannot close the finding: the backdrop sits below the menu, and the menu itself covers the next row's ⋯ button. The implementer's element-at-point probe resolved that button's centre to "Undo last step". That was the accident's exact path, so the finding stays open.

**Where the plan went wrong:** I endorsed the finding's option 1 without checking what region it protects. A scrim protects everything a popup does not cover; this hazard is the region it does cover. No z-index arrangement fixes that while the menu floats over the list.

**Decision:** Revision 2 stops floating the menu. The design stylesheet's `.shop-menu--static` lays the same menu out in flow, so the actions expand inside the card and push the rows below down. Nothing is ever under anything. This also removes the upward-flip logic (cycle 13 step 10) and the backdrop (this cycle's steps 1–2), which become dead code. Confirmation dialogs (the implementer's option 1) are not needed once no control is under another; the undo model stands.

**Deviations:** none. **Notes:** the "Fixed in 13b" line is corrected by step 6; the other options are superseded by the inline panel. Steps 1–3 stand only as history; steps 4–6 replace their effect.

**Verdict:** READY, Revision 2.

### Revision 2 (2026-09-26, Planner)

Read `implemented.md` to the end and the diff of the row partial, the staff partial, the board script, the stylesheet additions and the test. Reran `php artisan test`: 17 failed / 560 passed, the identical set. Design block identical; `is-up` and the backdrop are gone from the stylesheet and the script.

**Steps 4–6: pass.** The actions are a static menu inside the card, spanning the grid at every breakpoint; the ⋯ is a plain button with `aria-expanded`/`aria-pressed`; the board script is back to search only; the finding's closing paragraph is accurate.

**Deviations:** none.

**Notes for Planner.** Two panels open at once: fine, nothing can overlap. Escape no longer closes a panel: **accepted**; a panel in flow is not modal, and ⋯ toggles it. `role="menuitem"` dropped: correct.

**Manual check:** run by measurement, no clicks on real rows. With a panel open, every ⋯ button's centre resolves to that button and never to an action item, which is precisely the probe that failed in Revision 1. Data unchanged.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-13b/`, together with the finding.
