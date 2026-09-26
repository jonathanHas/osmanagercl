# Implemented — delivery scan row wrap (side plan)

Status: DONE
Plan: plan-delivery-row.md Revision 3
Implementer: Opus 5.5
Date: 2026-09-26

## Changes

- `resources/views/shop/delivery-scan.blade.php`: row root is `class="shop-row shop-row--wrap"`;
  aside, `shop-qty` stepper and pencil wrapped in `<div class="shop-row__controls">`, markup
  inside unchanged apart from indentation. `is-latest` / `is-off` bindings untouched.
- `resources/css/shop.css`: the three rules from the plan, with its comment, appended inside
  `APP ADDITIONS` just before the END marker. Design block `cmp` identical.
- `tests/Feature/Shop/ShopDeliveryTest.php`: `test_scan_screen_renders_with_the_endpoint_urls`
  asserts both classes in the markup and `.shop-row--wrap { flex-wrap: wrap; }` in the additions.
- `docs/design/shop-mode/README.md`: one sentence on the delivery bullet.
- `resources/js/shop/delivery-scan.js` not touched.

## Checks

| Check | Result |
|---|---|
| `--filter="ShopDeliveryTest\|ShopViewContractTest"` | 34 passed |
| Flip to `nowrap` | ShopDeliveryTest 1 failed; restored, 18 passed |
| Additions grep count | 3 |
| Design block `cmp` | identical |
| `--filter=Shop` | 179 passed |
| Full suite | 17 failed, 579 passed |
| `npm run build` | succeeds |
| `pint --test --dirty` | pass |

Full-suite failures, all outside Shop: UdeaScrapingServiceTest 7, CashReconciliationTest 3,
FruitVegLabelPrintingTest 2, ProductTest 2, WasteLogTest 2, TestScraperControllerTest 1.

Browser, dev app, Coolfin delivery 9e5c52db (one row, "Unexpected"), editor opened and closed
by tapping the pencil (records nothing), no console errors:

| Viewport | Editor closed | Editor open |
|---|---|---|
| 430 px | two lines, name 366 px | two lines, name 366 px, controls right-aligned |
| 768 px | one line, name 485 px | one line, name 313 px |
| 1280 px | one line, name 294 px | two lines, name 498 px |

## Deviations

None in the code. Two expected results in the plan's Verification did not hold; see
`plan-delivery-row-imp.md`.

## Notes for Planner

See `plan-delivery-row-imp.md`: closed rows wrap on a phone when the pill is wide, and open
rows wrap at 1280 px because the desktop list column is narrow. Owner phone check still pending.

---

# Revision 2 (step 4)

Status: DONE, with one problem for the Planner (Notes 1).

## Changes

- `resources/css/shop.css` additions only: main basis `180px` → `140px`, controls gap
  `--shop-space-3` → `--shop-space-4`, and the comment's "180 px" → "140 px". Nothing else.
- The test was not changed: it pins `flex-wrap: wrap`, which still holds.

## Checks

| Check | Result |
|---|---|
| `--filter=ShopDeliveryTest` | green |
| `--filter=Shop` | 179 passed |
| Design block `cmp` | identical |
| `npm run build` | succeeds |
| `pint --test --dirty` | pass |

Full suite not rerun: the change is two CSS values, and Shop is the only suite that reads them.

Browser, same Coolfin row with the "Unexpected" pill, page reloaded and the 140 px rule confirmed
loaded; 768 and 1280 px measured in iframes. The four cases the plan asked for:

| Case | Expected | Measured |
|---|---|---|
| 430 px closed | one line | one line, name 144 px (wraps to two text lines, reads normally) |
| 430 px open | two lines, name full width | two lines, name 351 px |
| 768 px open | one line | one line, name 305 px |
| 1280 px open | two lines | two lines, name 498 px |

## Deviations

None.

## Notes for Planner

1. **The open controls line is wider than a phone row, so it is clipped.** With the editor open
   and the "Unexpected" pill, the controls line needs 367 px (aside 119 + stepper 160 + pencil 56
   + two 16 px gaps). It does not wrap internally, and `.shop-list` has `overflow: hidden`.

   | Viewport, editor open | Left edge of the quantity/pill | Clipped |
   |---|---|---|
   | 430 px | inside the row padding | 0 px |
   | 400 px | 1 px, list starts at 16 px | 15 px |
   | 360 px | −39 px | 55 px |

   Revision 1 had the same fault, 8 px smaller, because of its 12 px gap. At 400 px the left of
   the "32/—" and the pill are cut off; the stepper and pencil are fine. Possible fixes: let
   `.shop-row__controls` wrap too (`flex-wrap: wrap`, so the stepper and pencil drop under the
   quantity on narrow phones), or go back to the 12 px gap, which only moves the threshold. I
   did not change the plan's CSS.
2. **The scanned count on this delivery changed from 32 to 27 during testing.** My scripts only
   clicked the pencil, whose `edit()` toggles `editing` and sends nothing; I never clicked −, +
   or the scan input. The `delivery_scan_items` row has no timestamps and the Apache log does not
   record app requests, so I cannot tell who changed it. Worth confirming with the owner that it
   was them, for example testing on the phone. I did not change it back.
3. Per the Revision 1 review, notes are in this report. `plan-delivery-row-imp.md` holds only
   the Revision 1 notes.

---

# Revision 3 (step 5)

Status: DONE. Nothing is clipped at any width. The layout on phones differs from the plan's
description; see Notes 1.

## Changes

- `resources/css/shop.css` additions only: `.shop-row__controls` gains `flex-wrap: wrap;
  row-gap: var(--shop-space-2);` at the end, every other declaration kept. `row-gap` comes after
  the `gap` shorthand, so it wins for the vertical gap.
- `tests/Feature/Shop/ShopDeliveryTest.php`: one more assertion, a regex that the
  `.shop-row__controls { … }` rule in the additions contains `flex-wrap: wrap;`.

## Checks

| Check | Result |
|---|---|
| `--filter=ShopDeliveryTest` | 18 passed |
| Flip controls to `nowrap` | 1 failed; restored |
| `--filter=Shop` | 179 passed |
| Design block `cmp` | identical |
| `npm run build` | succeeds |
| `pint --test --dirty` | pass |

Browser, same Coolfin row with the "Unexpected" pill, editor open, measured in same-origin iframes
with the new rule confirmed loaded. The window now refuses to go below 500 px, so no real-window
check under that width.

| Viewport | Row content | Controls layout | Left of controls vs row content | Row height |
|---|---|---|---|---|
| 360 px | 32..328 | quantity+pill and stepper on one line, pencil alone below | 33 ≥ 32 | 207 px |
| 400 px | 32..368 | same | 73 ≥ 32 | 207 px |
| 430 px | 32..398 | same | 103 ≥ 32 | 207 px |
| 768 px | 40..728 | one line with the name | inside | 82 px |

Every control's right edge equals the row content's right edge on its line, so the lines are
right-aligned. No clipping at any width.

## Deviations

None in the code.

## Notes for Planner

1. **Phones get a pencil-only line, not the stepper and pencil under the quantity.** The quantity
   and pill (119 px) plus the stepper (160 px) plus one 16 px gap is 295 px, which fits in 296 px
   even at 360 px. So the first thing to wrap is the pencil, alone, on a third line. The plan's
   "stepper and pencil drop to a further line under the quantity and pill" describes a different
   break. It reads as: name / quantity, pill, −, 27, + / pencil at the right. The pencil being
   the toggle that closes the editor, it now sits alone under the stepper on phones.
2. **430 px open is three lines, not "unchanged (two lines)".** Open controls need 367 px and the
   430 px row has 366 px, so they wrap there too; the row grows from 143 px to 207 px. Revision 2
   "fitted" at 430 px only by spilling 16 px into the row's left padding.
3. If a two-line layout is wanted on phones instead (quantity+pill on one line, stepper+pencil on
   the next), one option is to group the stepper and pencil in their own wrapper so they break
   together. That changes the view's markup, so I did not do it.
4. The Coolfin delivery still shows 27; my Revision 3 checks only toggled the pencil again.
