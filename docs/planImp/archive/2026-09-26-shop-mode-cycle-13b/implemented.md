# Shop mode cycle 13b (Revision 2) — the row's actions open inside the card — implementation

Status: DONE
Plan revision: 2
Implementer: Opus
Date: 2026-09-26

Scope: steps 4–6. Steps 1–3 (the backdrop) stand only as history; this revision
removes their effect, as the plan directs.

## Baseline
HEAD: 59aa8413
Pre-existing dirty files: cycles 12, 13 and 13b Revision 1, uncommitted.

## Pre-flight

`.shop-req` is a named-area grid at three breakpoints (lines 420, 509, 521):
```
base    "date main" / "steps steps" / "act act"          2 columns
≥768    "date main act" / "date steps steps"             3 columns
≥1280   "date main steps act"                            4 columns
```
An element with `grid-column: 1 / -1` and no named area is auto-placed into a new
implicit row spanning every column, at all three — which is what makes the panel
push the card open rather than overlap anything.

## Steps
### 4. The "more" actions open inside the row — done
Changed: `resources/views/shop/partials/request-row.blade.php`, `resources/css/shop.css`

The `<details>` became a plain `<button>` with `:aria-expanded` and
`:aria-pressed`, and the same items moved into a
`.shop-menu.shop-menu--static.shop-req__more` panel as the article's last child,
with the `role="menuitem"` attributes dropped (it is a group of buttons and a
link now, not a menu). Three app rules replace the two dead ones.
```
$ grep -c "is-up\|shop-menu-backdrop" resources/css/shop.css   → 0
$ head -c ... | cmp - docs/design/shop-mode/shop.css            → DESIGN-BLOCK-IDENTICAL
$ php artisan test --filter=ShopViewContractTest                → 13 passed
```

### 5. Remove the popup machinery — done
Changed: `resources/js/shop/requests-board.js`, `resources/views/shop/partials/requests-staff.blade.php`, `tests/Feature/Shop/ShopRequestsTest.php`

The module is back to `q`, `matches`, `groupMatches`, `anyMatch`, `rowsIn`; the
backdrop `<div>` and the board scope's Escape handler are gone (the sheet keeps
its own).
```
$ grep -c "menuToggled\|closeMenus\|menuOpen\|is-up" resources/js/shop/requests-board.js  → 0
  ... and 0 in every partial under resources/views/shop/partials/
$ php artisan test --filter="ShopRequestsTest|CustomerRequestTest|ShopViewContractTest" → 39 passed
```

### 6. The finding's closing line — done
Changed: `docs/planImp/findings/2026-09-26-request-menu-overlap.md`, which now ends:
> Cycle 13b Revision 1 added a backdrop (option 1), which the browser check showed
> cannot cover the hazard: the menu itself sits over the next row's ⋯. Revision 2
> removed the floating menu: the actions now expand inside the row
> (`.shop-menu--static`), so no control is ever under another. Closed.

## Deviations

None.

## Verification

1. `php artisan test --filter="ShopRequestsTest|ShopViewContractTest|CustomerRequestTest"`
   → **pass**, `Tests: 39 passed (392 assertions)`.

2. `php artisan test` → **pass**: `Tests: 17 failed, 560 passed (2269 assertions)`.
   Pass count unchanged at 560, exactly as the plan predicts. Same 17.

3. Design block `cmp` identical; `grep -rn "<script\|<style" resources/views/shop/`
   → no output; `grep -c "route(\|fetch(" resources/js/shop/requests-board.js` → 0.
   **pass**

4. `npm run build` → `✓ built in 13.38s`; `pint --test --dirty` → `PASS 10 files`.
   **pass**

5. Manual — **RUN, 2026-09-26, and the finding is closed.** Measured with
   `document.elementFromPoint` rather than clicking, so nothing was written.

   With the last row's actions open:
```
panelVisible:            true
panelIsInsideTheCard:    true      (panel rect within the article's rect)
moreButtonPressed:       "true"
floatingMachineryGone:   { detailsElements: 0, backdrops: 0 }
anyProbeResolvedToAnAction: false
```
   Probing the centre of every row's ⋯ button, with the page scrolled so none is
   hidden: rows 0–2 each resolve to **their own button**; row 3 was below the fold
   in that pass. **No probe on any row resolved to an action item** — which is the
   exact measurement that failed in Revision 1, where the same point resolved to
   "Undo last step".

   One reading needed care: in the first pass row 0's button resolved to
   `shop-chip__name`. That is the sticky top bar's user chip, not an action — the
   button was at y=10 with the top bar ending at y=81, so that row had simply
   scrolled up behind the header. Re-probing after scrolling to the top confirmed
   it hits its own button.

   Seen on screen: the panel is a beige inset strip inside the card, Edit and
   "Undo last step" on one line with "Cancel request" on its own below (the
   separator behaves as the intended line break), the ⋯ carries a pressed ring,
   and the card grows downward. At 420 px the actions wrap the same way inside the
   card and nothing overlaps.

   Data unchanged: `collected 1 / pending 2 / put_aside 4`; still only the two
   status-log rows from the earlier accident, none written today by this check.

## Files changed

```
 M resources/css/shop.css                                   (step 4; steps 1's rules removed)
?? resources/js/shop/requests-board.js                      (step 5)
?? resources/views/shop/partials/request-row.blade.php      (step 4)
?? resources/views/shop/partials/requests-staff.blade.php   (step 5)
?? tests/Feature/Shop/ShopRequestsTest.php                  (step 5)
 M docs/planImp/findings/2026-09-26-request-menu-overlap.md  (step 6)
```

## Notes for Planner

- **The inline panel removed more than it added.** Gone: the upward-flip
  measurement (cycle 13 step 10), the one-at-a-time bookkeeping, the backdrop and
  its state, and a board-level Escape handler. The row now owns a single boolean.
  That is a better trade than the one I recommended in the finding.
- **Two panels can be open at once**, by design now — they push their own cards
  open and cannot overlap, so there is nothing to arbitrate. Worth knowing it is
  deliberate rather than an oversight.
- **Escape no longer closes the actions.** The board-level handler went with the
  popup machinery, and a panel in flow is not modal, so there is less reason for
  it — but a keyboard user now closes it only by tapping ⋯ again. Cheap to add
  back per row (`x-on:keydown.escape.window="more = false"`) if you want it.
- **`role="menuitem"` was dropped** with the menu semantics. The panel is a
  labelled `role="group"` of ordinary buttons and one link, which is what it now
  is; screen-reader users get normal button semantics rather than a menu that
  does not behave like one.
- **Nothing committed, pushed or deployed.**
