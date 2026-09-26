# Problems found implementing plan-delivery-row.md (Revision 1)

Implementer: Opus 5.5 · 2026-09-26 · full report in `implemented-delivery-row.md`

The fix works for the reported fault: with the editor open on a phone the name keeps its full
width and the controls sit right-aligned on a second line. Two of the plan's expected results
did not hold in the browser. Neither is a regression, but both need a Planner decision.

## 1. On a phone the controls wrap even with the editor closed

The plan assumed the aside is ~70 px wide. With an "Unexpected" pill it is 119 px, so the closed
controls are 187 px (aside 119 + gap 12 + pencil 56).

| Viewport 430 px, editor closed | Value |
|---|---|
| Space inside the row | 366 px |
| Needed for one line (180 + 16 + 187) | 383 px |
| Result | wraps, row 143 px tall |

Before the fix the name had 163 px here and read fine on one line; now the row is two lines
whenever the pill is wide ("Unexpected", probably "Not scanned"). Readable, but taller than the
plan intended. Options: lower the main's basis (about 140 px keeps closed rows on one line at
430 px with the widest pill), or accept two-line rows on phones.

## 2. At 1280 px the controls wrap when the editor is open

At 1280 px the list sits in a narrower column than at 768 px (row content ~497 px vs ~688 px).

| Editor open | Main width | Controls line |
|---|---|---|
| 768 px | 313 px | same line |
| 1280 px | 498 px | second line, row 143 px |

Open controls are 359 px, so 180 + 16 + 359 = 555 px exceeds the 497 px available. Before the
fix the name would have had about 122 px at 1280 px, so the original squeeze existed on desktop
too, just less severely. The plan's "at 1280 px the row is a single line as before" is true only
with the editor closed. I left this as is: wrapping reads better than a 122 px name.

## 3. Smaller notes

- The controls' internal gap is `--shop-space-3` (12 px) per the plan, where the row used 16 px
  between aside, stepper and pencil. It shifts those three 4 px closer on every width.
- The browser window could not be resized (window manager ignored it), so 768 and 1280 px were
  measured in same-origin iframes of the live page. No screenshot exists at those widths.
- The full suite's 17 failures match cycle 16's count and are all outside Shop (list in the
  report). I did not stash the working tree to diff the set exactly, because the vouchers
  session's uncommitted work shares it.
- `docs/design/shop-mode/README.md` also carries a vouchers line from the other session; the
  plan's risk note expected the vouchers cycle not to touch that file. No conflict resulted.
