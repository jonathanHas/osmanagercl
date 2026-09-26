# Finding — an open "more" menu covers the ⋯ button of the row above it

Raised by: Opus (Implementer)
Date: 2026-09-26
Cycle: 13 (Revision 2, step 10) — **found after the cycle was accepted and archived**
Severity: worth fixing before staff use the board. Not a defect in the code as
written; a consequence of the design the step asked for.

## What happened

While verifying step 10 in the browser I opened the last row's ⋯ menu, which
correctly flipped upward. I then clicked what I believed was the ⋯ button of the
row above, to check that opening a second menu closes the first.

The click did not hit that button. The open menu was covering it, so the click
landed on the menu's own **"Undo last step"** and moved a real customer's line
(Martin's Zonnemaire bread) from `put_aside` back to `ordered`.

I restored it through the UI within 30 seconds. The data is exactly as it was:
`collected 1 / pending 2 / put_aside 4`, counts `open 2 / aside 4`. Two rows
remain in `customer_request_status_logs` (#27 `put_aside -> ordered`, #28
`ordered -> put_aside`, 30 seconds apart, user 15). I left them: they are an
accurate audit record, and deleting them to tidy away my own mistake would be
worse than the mistake.

## Why it happens

`.shop-menu` is `position: absolute` with `z-index: 40`, anchored to the
`.shop-more` wrapper (`position: relative`, line 434). Step 10 added:

```css
.shop-more.is-up > .shop-menu { top: auto; bottom: calc(100% + 8px); }
```

So an upward menu occupies the space **directly above the ⋯ button** — which, in
a list of stacked rows, is the ⋯ button of the previous row. The menu is roughly
three rows tall, so it can cover two rows' buttons at once.

Downward menus have the identical property; they cover the row *below*. That was
harmless, because the only row whose menu flips upward is one near the bottom of
the viewport, and for a downward menu at the end of a list the overlapped area is
empty page. Step 10 did not create the overlap — it moved it from empty space
onto live controls.

## Why it matters

Every row's menu contains **"Cancel request"** and, where the status allows,
**"Not available"** and **"Undo last step"**. All three are one tap, with no
confirmation, by design ("a mis-tap is undone with Reopen / Undo last step").
That reasoning holds for a mis-tap on the row you are looking at. It holds less
well when the tap lands on a *different row's* menu than the one you aimed at,
because the feedback is a toast naming a product you were not touching — as it
did for me.

The exposure is small (a menu must already be open) but the target is the one
part of the screen with irreversible-feeling actions on it, and shop-floor taps
are fast and imprecise.

## Options

1. **A transparent click-catching backdrop while any menu is open.** A full-viewport
   element below the menu's z-index that closes the menu on any click and swallows
   that click. This is the conventional pattern, it fixes the overlap for upward
   *and* downward menus, and it would replace the one-at-a-time bookkeeping in
   `menuToggled()` — the backdrop closes the open menu before the next one opens.
   Needs one `APP ADDITIONS` rule and a few lines of Alpine. **My recommendation.**
2. **Close menus on any click outside**, via a `click.outside` listener per
   `<details>`. Similar effect, no new markup, but it does not swallow the
   offending click: the first tap both closes the menu and activates whatever is
   underneath, which is the behaviour that caused this.
3. **Leave it.** Defensible — the overlap existed downward before step 10 and
   nobody hit it — but it now sits over live destructive controls rather than over
   blank page.

## What this does not affect

- The upward flip itself is correct and worth keeping: "Cancel request" was below
  the fold before step 10 and is on screen after it.
- Step 9 (search groups) is unrelated and verified working.
- No test changes: `ShopRequestsTest` asserts the markup, not the geometry, and
  all 188 Shop/CustomerRequest tests pass.

---

Cycle 13b Revision 1 added a backdrop (option 1), which the browser check showed
cannot cover the hazard: the menu itself sits over the next row's ⋯. Revision 2
removed the floating menu: the actions now expand inside the row
(`.shop-menu--static`), so no control is ever under another. Closed.
