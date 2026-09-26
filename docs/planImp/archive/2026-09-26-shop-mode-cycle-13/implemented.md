# Shop mode cycle 13 (Revision 2) — search groups and the more menu — implementation

Status: DONE
Plan revision: 2
Implementer: Opus
Date: 2026-09-26

Scope: steps 9–10 only, both from findings in Revision 1's browser walkthrough.
Steps 1–8 stand as delivered.

## Baseline
HEAD: 59aa8413
Working tree carries cycles 12 and 13 Revision 1, accepted, uncommitted (29
entries). Revision 2 touches four files: `resources/js/shop/requests-board.js`,
`resources/views/shop/partials/requests-staff.blade.php`,
`resources/views/shop/partials/request-row.blade.php`, `resources/css/shop.css`
(one `APP ADDITIONS` rule), plus the test file.

## Steps
### 9. Search hides empty groups and says when nothing matches — done
Changed: `resources/js/shop/requests-board.js`, `resources/views/shop/partials/requests-staff.blade.php`, `tests/Feature/Shop/ShopRequestsTest.php`

`groupMatches(el)` and `anyMatch()` added, each group `<section>` gets
`x-show="groupMatches($el)"`, and a "No matches" `shop-empty` block appears when
the search is non-empty and nothing matches. The heading counts stay the server
counts, per the plan — they describe the view, not the filter.

`groupMatches()` reads `this.q` before touching the DOM, and the comment says why:
Alpine tracks the properties an expression touches, so without that read the
expression would never re-evaluate on a keystroke because everything else in it
is plain DOM.

Check output:
```
q=zzz  anyMatch false | s1 false | s2 false   (expect false false false)
q=oat  anyMatch true  | s1 false | s2 true    (expect true false true)
q=''   anyMatch true  | s1 true  | s2 true    (expect true true true)
```
The third case is mine: an empty query must show every group, which is the state
the board spends most of its life in.

### 10. More menu: open one at a time, and open upward near the bottom — done
Changed: `resources/js/shop/requests-board.js`, `resources/views/shop/partials/request-row.blade.php`, `resources/css/shop.css`

One `APP ADDITIONS` rule (`.shop-more.is-up > .shop-menu`), `menuToggled($el)`
bound to the `<details>` `toggle` event, and `closeMenus()` on
`keydown.escape.window` in the board scope.

Check output:
```
last row       -> is-up true    (menu bottom 900 > viewport 800, fits above)
other menu open -> false        (only one at a time)
room below     -> is-up false
no room above  -> is-up false   (leave it downward; scrolling still reaches it)
after close    -> is-up false
```
The middle two are branches the plan did not ask for. The "no room above" case
matters: a menu taller than the space above it would be worse flipped up than
left down, so it is left alone.

```
$ sed -n '/APP ADDITIONS START/,$p' resources/css/shop.css | grep -c 'is-up'  → 1
$ head -c ... | cmp - docs/design/shop-mode/shop.css                          → DESIGN-BLOCK-IDENTICAL
```

## Deviations

None. Both steps went in as written.

Two additions inside the plan's intent, both in `menuToggled()`:
1. **Closing a menu removes `is-up`.** Without it a menu that once opened upward
   keeps the class, so the next open is positioned from the stale decision.
2. **The "no room above" branch.** The plan's expression already contains
   `r.height < details.getBoundingClientRect().top`; I kept it and added a check
   for it, because a menu taller than the space above it must stay downward — and
   that is exactly the case on a short phone viewport.

## Verification (Revision 2)

1. `php artisan route:list --name=customer-requests` → unchanged; this revision
   touches no routes or controllers.

2. `php artisan test --filter="Shop|CustomerRequest"` → **pass**,
   `Tests: 188 passed (856 assertions)`. Same 188 tests as Revision 1, three more
   assertions.

3. `php artisan test` → **pass**: `Tests: 17 failed, 560 passed (2266 assertions)`.
   Identical pass count to Revision 1 (no new tests) and the same 17 pre-existing
   failures:
```
  3 Tests\Feature\CashReconciliationTest
  2 Tests\Feature\FruitVegLabelPrintingTest
  2 Tests\Feature\ProductTest
  1 Tests\Feature\TestScraperControllerTest
  2 Tests\Feature\WasteLogTest
  7 Tests\Unit\UdeaScrapingServiceTest
```

4. `head -c ... | cmp` → `DESIGN-BLOCK-IDENTICAL`;
   `cmp public/images/shop-icons.svg docs/design/shop-mode/shop-icons.svg` →
   `SPRITES-IDENTICAL`; `grep -rn "<script\|<style" resources/views/shop/` → no
   output; `grep -c "route(\|fetch(" resources/js/shop/requests-board.js` → 0.
   **pass**

5. No controller or service change this revision.

6. `./vendor/bin/pint --test --dirty` → `PASS 10 files`; `npm run build` →
   `✓ built in 9.56s`. **pass**

7. Manual — **RUN, 2026-09-26**, in two parts.

   > Added after this cycle was accepted and archived: the owner signed in and
   > asked me to verify. Nothing in the code changed; this replaces the
   > "partly run, then blocked" note that was here.

   **Signed out (the session had expired).** This closed one of Revision 1's two
   remaining gaps: the guest board is unchanged — "Staff sign in", no phone
   numbers, cycle 12 cards, "Overdue 1d" / "Overdue 10d" pills, put-aside lines
   included. I did not sign in myself; the owner did.

   **Signed in, both fixes confirmed:**

   | Check | Result |
   |---|---|
   | Search matching nothing | "No matches — Nothing matches your search." and **no orphaned headings** (before: two bare headings over an empty board) |
   | Partial match ("beetroot") | "Coming up" disappeared entirely; only "Due today" with its matching row remained |
   | Last row's menu | Opens **upward**, Edit / Undo last step / **Cancel request** all on screen (before: "Cancel request" was below the fold) |
   | Date blocks | The 25 Sep rows now render `is-late` — today is the 26th, so the state tracks the clock |

   The planned behaviour is still visible: a partial match leaves "Due today 3"
   over one row, because the count describes the view, not the filter. It reads
   far better now that empty groups vanish.

   **One thing went wrong, and it is worth recording.** Verifying the
   one-menu-at-a-time behaviour, I clicked what I took to be the ⋯ of the row
   above — but the upward-opening menu was covering exactly that spot, so the
   click landed on its "Undo last step" and moved a real customer's line
   (Martin's Zonnemaire bread) from `put_aside` back to `ordered`. I put it back
   through the UI's own "Put aside" button within 30 seconds. Final state matches
   the start of the session exactly:
```
  collected 1 | pending 2 | put_aside 4      counts: open 2 / aside 4
  Zonnemaire line status: put_aside
  status-log rows written today: 2   (#27 put_aside->ordered, #28 ordered->put_aside)
  customer_requests created today: 0
```
   I left the two audit rows in place. They are an accurate record of what
   happened and who did it; deleting them to tidy away my own mistake would be
   worse than the mistake. The line's `status_changed_at` is consequently today
   rather than 2026-09-25 10:54:57 — that field drives only the Done view's
   30-day window, which does not apply to a put-aside line, so nothing on screen
   differs.

   **That mis-click is itself a finding about step 10** — see the first note below.

## Files changed

Revision 2 touched five files, all already mine:
```
 M resources/css/shop.css                                   (step 10, one app rule)
?? resources/js/shop/requests-board.js                      (steps 9, 10)
?? resources/views/shop/partials/request-row.blade.php      (step 10)
?? resources/views/shop/partials/requests-staff.blade.php   (steps 9, 10)
?? tests/Feature/Shop/ShopRequestsTest.php                  (steps 9, 10: three assertions)
```

## Notes for Planner

- **An upward menu covers the row above it, including that row's ⋯ button** —
  found by making the mistake. With the last row's menu open, the ⋯ of the row
  above sits underneath it, so a tap there activates a menu item instead of
  opening that row's menu. Downward menus have the same property, but they cover
  the row *below*, which on the last row is empty space — so step 10 moved the
  overlap somewhere it can do harm, on rows that all carry a "Cancel request".
  A transparent click-catching backdrop while a menu is open would close it on
  the first tap anywhere else, which is the usual pattern and would also replace
  the one-at-a-time bookkeeping. Small change; I would not put these menus in
  front of staff without it.
- **`menuToggled()` measures on every open**, which is a layout read on tap. That
  is one `getBoundingClientRect()` per menu open — nothing, but it is the first
  place in Shop mode that reads layout in an event handler, so it is worth knowing
  it exists if anyone later wonders why a tap feels different on a slow tablet.
- **Group counts still describe the view, not the filter**, per the plan: "Due
  today 3" stays 3 while the search narrows it. With the group now hiding when
  nothing in it matches, the confusing case (a count over one row) is gone, but a
  partial match still shows the full count over fewer rows. If that reads wrong on
  the shop floor, making the count reactive is a two-line change.
- **Escape now does two things on this screen**: closes the more menus (board
  scope) and closes the sheet (sheet scope). They are separate listeners on
  separate elements, so both fire. With a menu open behind the sheet, one Escape
  closes both. Harmless, but it is a shared key.
- **Nothing committed, pushed or deployed.** Cycles 12 and 13 are stacked in one
  tree.
