# Shop labels — structure brief for Claude Design

Status: DONE — design delivered and accepted 2026-10-21 (`docs/design/shop-mode/screen-07-shelf-labels.html`, `screen-07b-zebra-labels.html`); the build is `plan.md`
Author: Planner (Fable 5.1)
Date: 2026-10-21
Design project: Shop mode `9402165e-9955-461d-8ff4-3e934897b600` (screen 07 is the one to replace)

## Instructions for Claude Design

This brief goes into the existing **Shop mode** project, the one that holds
screens 01–20, `shop.css` and `shop-icons.svg`. Work inside that project's
design system, not a new one.

- Produce **two screens** that replace screen 07 "Print labels": **07 Shelf
  labels** and **07b Zebra labels**. Number and name them so they slot into the
  existing set; link them to each other through the tab nav and back to
  screen 01 Home. Screen 15 "F&V labels" is not part of this job; leave it.
- Compose only from the existing `shop-*` classes and the sprite icons already
  in the project (listed under "Design-system notes" below). Do not restyle or
  edit existing rules. If a genuinely new rule is unavoidable, put it in a
  clearly marked block of additions at the end of `shop.css` so it can be
  carried over as an app addition.
- Draw every state listed under "States Claude Design should draw", including
  the phone-width ones, as the other screens in the project do.
- Use the sample content given below; it reflects real data.
- Deliver the screens as HTML files in the same form as the existing
  `screen-NN-*.html` files, plus any CSS additions, so they can be copied into
  the app's `docs/design/shop-mode/` folder unchanged.

## What is wrong today

`/shop/labels` is half a screen. It shows the A4 shelf-label queue (scan to add,
remove, Print, Clear queue) and two tiles, "Zebra labels" and "Printed recently",
that both leave Shop mode for the office layout. Design screen 07 intended three
tiles (Shelf labels / Zebra labels / Scan to label) and a queue that mixed shelf
and Zebra rows; only the shelf half was built (cycle 9, 2026-09-25) and the Zebra
half was parked (`docs/planImp/parked/2026-09-25-shop-mode-cycle-11-zebra/plan.md`).

The office pages the tiles land on are desktop pages built up over a year:

| Office page | What it holds | Size |
|---|---|---|
| `/labels` hub | three cards (Zebra, Translate, Shelf) + a 4-tab nav | small |
| `/labels/zebra` | printer spool with per-job cancel; saved Zebra labels in three groups (on till / other / standalone) with price- and country-mismatch pills; print modal with copies and a persisting "Fix"; sub-tab of translated labels by category with auto-print toggles; link to the manage CRUD | 657 lines |
| `/labels/shelf-labels` | template picker (6 templates), add-method filters, the needing-labels table, 7 days of prints grouped in 5-minute batches with Restore All and per-row re-queue, a scanner modal, and header links to camera-test v1 / v2 and a disk-file ZPL history | 1,290 lines |
| `/labels/translate` | 5-step wizard: Scan → Product → Photos → Review → Print (Gemini translation, ZPL preview, font scale, copies) | 954 lines |
| `/labels/zebra/manage/*` | upload a ZPL file, link a product, edit fields, default copies, delete | CRUD |
| test pages | camera-test, camera-test2, barcode-scan-test, zpl-debug, translation-history (ZPL files on disk) | `labels.manage` only |

Three label systems sit behind this, each with its own queue and printer:

1. **Shelf labels** — A4 sheets (24 per page on the default template) printed
   through the browser's print dialog. The queue is derived from `LabelLog`:
   a product needs a label when its latest new-product / price-change /
   barcode-change / re-queue event is newer than its last print. Dev: 10 in the
   queue, 253 prints in the last 30 days.
2. **Zebra labels** — ZPL files uploaded by the office, linked to a product,
   printed on the counter Zebra (GX430t) over `lp`. Each is checked against the
   till at list time: price in the ZPL vs the till's gross price, country vs the
   product's country. Dev: 17 active, 3 standalone (no product).
3. **Translated labels** — made from a photo with Gemini in the office wizard,
   printed on the same Zebra, and **auto-printed when the product is scanned in
   on a delivery** (that already works from the Shop delivery screen, because it
   uses the legacy delivery endpoints). Dev: 301 saved, 199 with auto-print on.

A fourth, F&V labels (`/fruit-veg/labels`, its own `VegPrintQueue`), belongs under
the Fruit & veg tabs as design screen 15 and is not part of this brief.

Permissions: `labels.print` is every shop-floor user (20 on dev);
`labels.manage` is four people (Admin, Jessika, Jonathan, test). PIN-signed-in
users are confined to an allow-list of routes (`config/shop.php`), so every
endpoint a Shop screen calls has to be on it; today `labels.requeue`,
`labels.restore-batch`, `labels.printer-queue` and `labels.printer-cancel` are
not.

## The split: what belongs on the shop floor, what stays in the office

Shop floor (tablet or till PC, touch, often a PIN session) — **print what is
needed now**:

- scan a product to queue its shelf label, print the sheet, take one off
- print a saved Zebra label for a product, with the till's price if the label is stale
- reprint a translated label
- see what was just printed and put a wrong one back in the queue
- see whether the counter printer is stuck (and, as a manager, clear it)

Office (desktop, `labels.manage` for most of it) — **make and maintain labels**:

- upload / edit / delete Zebra labels, fix a stale price or country in the file
- translate a new label (photo, AI, review), switch auto-print on or off
- label templates, 7-day history with batch restore, printer spool per job
- the test and debug pages

Nothing in the office set is done at the counter, so none of it needs a Shop
screen. The two tiles that currently exit to the office go away.

## Proposed Shop structure

One section, **Labels**, reached from the Home tile "Print labels" (badge =
shelf queue count, unchanged). Two tabs in a `shop-seg` nav directly under the
top bar, exactly as Fruit & veg does with Waste log / Harvest
(`resources/views/shop/partials/fv-nav.blade.php`):

```
[ Shelf labels ]  [ Zebra labels ]
```

Both tabs share the Shop top bar (back to Home, user chip) and the help button.

### Tab 1 — Shelf labels (`/shop/labels`, exists; refine)

Zones, top to bottom:

1. **Scan input** — "Scan a product to add a label" (camera + hand scanner,
   the existing `x-shop.scan-input`).
2. **Queue** — `shop-list` of rows: product name; meta "Price changed · €2.43"
   (reason · till price); ×1 and a ghost remove button. Header "Print queue"
   with meta "10 labels · 1 A4 sheet". Empty state "All caught up".
3. **Printed recently** — NEW, replaces the office exit. A collapsed section
   under the queue, header "Printed today · 3 batches" (`shop-row` groups,
   expand in flow, never a popup). Each batch: time ("14:05 · 12 labels") and
   its products; each product row has one action, **Back to queue**
   (`labels.requeue`). No batch restore on the shop (owner decision below).
   Only today (and yesterday if today is empty); the office keeps the 7-day view.
4. **Sticky action bar** — "Clear queue" (managers only) and "Print 10 labels".
   Print opens the A4 sheet in a new tab, which calls the browser print dialog;
   the A4 printer is on the shop floor and every device prints to it.

Not shown on the shop: the template picker (the default template is used; the
sheet count in the header is enough) and the add-method filters.

### Tab 2 — Zebra labels (`/shop/labels/zebra`, new; parked cycle 11 plan is the spec)

Zones:

1. **Printer line** — one `shop-meta` line: "Checking printer…" → "Printer
   queue empty" / "2 jobs waiting" / "Printer not reachable", with a ghost
   **Cancel all** for managers when jobs are waiting. Loaded after render (the
   spool call can take seconds).
2. **Scan input** — "Scan a product to print its label". A scan opens the print
   card for that product. If the product has no Zebra label and no translated
   label: scan error "No Zebra label for this product".
3. **Search** — `shop-search` "Search labels", filters the list as you type.
4. **Print card** (`shop-card`, shown when a label is chosen, × to close):
   - title: product name; meta "Oat front · 112.6 × 75.1 mm · 5000000000017"
   - if the label's price differs from the till: a flat inner card
     "Label says €2.20, till says €2.43" with a `shop-switch` **Print with till
     price** (on by default). Same for country. This prints with the till value
     for this print only; it never edits the saved label (office job).
   - for a translated label: meta shows "Translated · 58 × 40 mm" and a muted
     pill "Auto-prints on delivery" when that is on; no mismatch block
   - "Check the printer has 112 × 75 mm labels loaded" (as the Harvest screen does)
   - Copies `shop-stepper` (1–99, preset from the label's default)
   - block primary button "Print 2 labels"; result as a toast
5. **List** — grouped sections, each a `shop-subtitle` + `shop-list` of
   tappable rows (title product name; meta label name · size · code; right-hand
   `shop-pill`: "Matches till" ok / "Price differs" warn / "No product" muted):
   - **On the till** (saved Zebra labels whose product is on the till)
   - **Other products**
   - **Standalone labels** (uploaded without a linked product, so a scan never
     finds them; on dev "parsnip", "Beetroot Chutney 180g" and "360g". Found by
     search, pill "No product")
   - **Translated labels** — 301 of them, so this group shows only its count
     and a prompt ("Scan or search to find one") until there is a search term
     or a scan. The three Zebra groups (17 rows) are always listed.
6. Empty state "No labels match" (barcode icon); error state as on Shelf labels;
   toasts as on Shelf labels.

### What the home tile and nav say

- Home tile: "Print labels" / "Shelf and Zebra labels" (unchanged), badge = shelf queue.
- `shop-seg` tabs: "Shelf labels", "Zebra labels". A third tab is not planned;
  F&V labels go under Fruit & veg (screen 15).

## States Claude Design should draw

1. Shelf tab, queue of 10 with mixed reasons, Printed today collapsed.
2. Shelf tab, Printed today expanded, one batch open, "Back to queue" on a row.
3. Shelf tab, empty queue, Printed today with one batch (the common end-of-task view).
4. Zebra tab, idle: printer line, scan, search, three groups, Translated group collapsed with count.
5. Zebra tab, print card for a label with a price mismatch, switch on.
6. Zebra tab, print card for a translated label (auto-print pill).
7. Zebra tab, scan of a product with no label (scan-input error state).
8. Zebra tab, printer not reachable (grey line; printing still allowed).
9. Both tabs as an employee (no Clear queue, no Cancel all) and as a manager.
10. Phone width (390 px) for 1, 4 and 5; the sticky bar at the bottom of the
    screen, not the end of the page.

Sample content (real shapes from dev): queue rows "Oat drink, barista 1 L ·
New product · €2.43", "Tahini, light 300 g · Price changed · €4.95", "Sourdough
rye 750 g · Barcode changed · €5.20". Zebra labels 112.6 × 75.1 mm and
115.2 × 78.3 mm; translated labels 58 × 40 mm. Prints come in batches of 1–43 labels
(median 30), on about one day in four.

## Design-system notes

- Everything is composed from existing `shop-*` parts: `shop-seg`, `shop-tile`
  is no longer needed here, `shop-row` / `shop-list`, `shop-card` and
  `shop-card--flat`, `shop-pill--ok|warn|muted`, `shop-stepper`, `shop-switch`,
  `shop-search` + `shop-input`, `shop-empty`, `shop-actions`, `shop-toasts`.
  Icons from the sprite: `printer`, `barcode`, `search`, `x`, `check`, `alert`,
  `minus`, `plus`, `list-checks`.
- Lists of identical rows expand in flow; no floating popups over neighbouring rows.
- Sticky action bar is a child of `<main>` (or a `shop-contents` wrapper).
- Light theme only, `is-touch` root, 44 px targets, one scan field per page.

## Owner decisions (2026-10-21)

1. **A4 printing**: the A4 printer is on the shop floor and any device prints
   to it, so the shelf tab's Print button is complete as drawn.
2. **Printed today**: per-row "Back to queue" only. No Restore batch on the shop.
3. **Translated labels**: accessible on the Zebra tab but not the first thing
   seen, so the collapsed group (count + "scan or search") stands. A scan of a
   product that has only a translated label still opens its print card.
4. **Standalone labels**: kept on the shop tab as search-only rows (they are
   ordinary product labels that were never linked to a till product; the owner
   asked what the question meant, so the Planner decided).

## Office tidy-up (separate, smaller job; not for Claude Design)

- Take the test and debug links (camera-test v1 / v2, barcode-scan-test,
  zpl-debug, translation-history of disk files) out of the Shelf Labels header
  and put them behind one "Developer tools" entry for `labels.manage`.
- The hub's third card and nav tab both say "Shelf Labels" but the page title
  is "Shelf Labels" with a 7-day history; rename the history block "Printed in
  the last 7 days" so it matches the shop's "Printed today".
- Two history pages exist (`labels.translation-history` for ZPL files on disk,
  `labels.translate.history` for saved translations); the first belongs with
  the developer tools.

## When this becomes a plan

The Planner turns the agreed design into `docs/shop_new/plan.md`, reusing the
parked cycle 11 plan for the Zebra tab (list service, JSON list endpoint,
`shop.zebra` route, `zebra.js`, tests with the faked printer) and adding: the
`labels.requeue` / `labels.printer-queue` / `labels.printer-cancel` routes
to the PIN allow-list, a "printed today" JSON
endpoint (today's `label_print` events grouped in 5-minute windows, as the
office does), a translated-labels group in the list JSON (name, product, size,
auto-print, `labels.translate.print` URL), and the `shop-seg` nav partial.
