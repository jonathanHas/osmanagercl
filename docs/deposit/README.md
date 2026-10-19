# Deposit — plan / implement track

Deposit work runs here, separate from the Shop view updates in `docs/shop_new/`
and the vouchers track in `docs/vouchers/`. Same protocol, same file roles:
see [`planimp.md`](./planimp.md) (a copy of `docs/planImp/planimp.md` with
paths pointing here). The Planner keeps this file current; a fresh session
reads it before touching deposit code.

## Where things stand (2026-10-17)

| | |
|---|---|
| Current task | **Side plan `plan-browser-mcp.md` READY** (the Implementer tests through the Playwright MCP; `mint.php --playwright`, README, first run of the tools). **Cycle 4 ACCEPTED 2026-10-17** after five revisions; to archive to `archive/2026-10-17-cycle-4-price-review/` and **commit**. `/deposits` is the owner's layout 2a in the Modernist design system with the one-step Confirm (Keep / −deposit / typed price), Undo, landed-cost markup, till-line price history, 93 deposit tests. Next: cycle 5, the float report |
| Cycle 3 archive | `archive/2026-10-05-cycle-3-go-live/` (moved 2026-10-17). Open owner items from its review: SOP shelf-label sentence; dev till restart + juice scan (moot: the dev POS was rebuilt from production and has no till install) |
| Cycle 3 | `deposits:install-till [--check|--rollback|--force]` (`DepositTillInstaller`, writes only `RESOURCES`); deployable scripts `resources/pos/deposit/`; `import_data.deposit_reconciliation` shown on the delivery page; `/deposits` add card shows the pick; runbook + SOP draft `docs/deposit/sop-bottle-deposits.md` |
| Cycle 2 | Parsers emit `barrel_code` (country-code rule plus a fallback scoped to the docket's barrels section); `delivery_items.barrel_code`; `deposit_sightings`, `product_deposits`; `/deposits` screen; `DepositEvidenceService`, `DepositPosService`, `PosProductAttributes`; `deposits:backfill-delivery-items`, `deposits:refresh-suggestions`, `deposits:sync-pos [--dry-run|--check]`; delivery import hook. Dev state: 679 sightings, 56 mapped products (4 confirmed by the owner), tiers 313 / 315 / 9936 on, 4 till products carrying attributes |
| Cycle 1 | Automatic deposit line on the test till: ACCEPTED 2026-10-03, uncommitted. Six `DEP-*` products (ids in the archived `implemented.md`), category `Bottle Deposits` `c8936170-…`, `script.Deposit.AddLine` / `script.Deposit.Change`, `Ticket.Buttons` wired; all left installed on the dev POS database |
| HEAD | `5775fbef` on `feature/modularization-phase1`; cycles 1–3 committed (commit `e8d79571`) |
| Working tree | clean at the start of cycle 4 (plus the cycle 3 archive move) |
| Production | deposit migrations applied; evidence commands, tier switches, `deposits:sync-pos` and `deposits:install-till` **not** run as of the 2026-10-15 backup. Runbook: `docs/features/barrel-deposit-tracking.md`, "Going live on production" |

## What already exists under the word "deposit"

The codebase uses "deposit" for two unrelated things. Check which one a task
means before planning.

1. **Returnable packaging charged on supplier deliveries** (crates, bottles,
   pallets; Udea calls them "barrels"). Feature doc:
   `docs/features/barrel-deposit-tracking.md`.
   - Models `App\Models\BarrelCode` (table `barrel_codes`, reference data per
     supplier, custom name and image) and `App\Models\DeliveryBarrel` (table
     `delivery_barrels`, line items per delivery). `Delivery::barrels()`.
   - Parsed from Udea delivery PDFs by
     `scripts/invoice-parser/parsers/delivery_udea.py` and stored by
     `DeliveryService::storeBarrelItems()` during PDF import.
   - Office screens: `BarrelCodeController`, routes `barrel-codes.*` in
     `routes/web.php` (index, edit, image upload/remove); the barrel section
     on `resources/views/deliveries/show.blade.php`.
   - Migrations `2026_01_26_121414_create_barrel_storage_tables.php` and
     `2026_01_26_123319_add_name_and_image_to_barrel_codes_table.php`.
   - There is no return / credit side yet: deliveries record what was charged,
     nothing records what went back or what the supplier credited.
2. **Bank deposits / cash lodgements** (cash reconciliation, bank statement
   analysis, RTD). Docs: `docs/features/cash-reconciliation.md`,
   `docs/features/cash-closed.md`, `docs/features/bank-statement-analysis.md`.
   Not packaging deposits.

## The goal (owner, 2026-10-03)

Udea charges the shop a returnable deposit ("statiegeld") on glass bottles and
jars, as well as on crates and pallets. The shop does not pass the bottle
deposit on to customers. The owner wants to charge customers the deposit when
they buy such a product and refund it when the bottle comes back, with the
simplest system possible. First step: identify which products carry the
charge. Sample invoice: `JFolder_temp/UdeaFactuur1148558.pdf` (code 313).

## What the investigation found (2026-10-03)

**Where the code is on the invoice.** Each Udea product line carries the
barrel code in its own column, after the Gb.rek group and country
(`30322 DE 313`). Only bottle/jar deposits appear there; crates and pallets
appear only in the "Barrels delivered" section. On the sample invoice the
per-line codes reconcile exactly with that section (five 313 lines totalling
42 units = 30 + 12 in the section; one 315 line = 1).

**Per-unit deposit codes seen so far** (from `barrel_codes`, populated by
imports): `313` = €0.25 glass (bottles 200 ml to 1.5 l, 500 g yogurt jars,
5 l water), `315` = €0.70 glass (1 l dairy bottles and Wisselwaar jars of
pasta, rice, nuts), `9936` = €0.10 "Bierflesje", `10046` = €0.15
"Flesje/Blik" (in the catalogue, not yet seen on a product line).

**The app already has the data.** The Udea delivery parser does not capture
the column, but its description regex swallows it, so
`delivery_items.description` ends in e.g. `... DE 313`. Over 16,527 Udea
delivery lines, 490 end in `313`, 117 in `315`, 11 in `9936`. Grouping by
supplier code gives 53 distinct products, every one matched to a till product
(`product_id` set). Correction after cycle 2: the quick REGEXP over-counted.
`36214` Billys Farm waffles is **not** a deposit product (its "315" is the pack
size "315 gram"), and four real deposit products were invisible to the
country-code rule because pdfplumber garbled the letters before the code
(`5018611`, `5018612` Landpark waters; `92491`, `97433` Your Organic juices;
34 lines). Cycle 2 rev 3 adds a fallback scoped to the docket's own barrels
section. Code 10046 (0.15 "Flesje/Blik") sits on 16 drinks products (71
lines), not one. Query used:
`DeliveryItem` where `description REGEXP '(^|[^0-9])(313|315|9936)([^0-9]|$)'`,
deliveries with `supplier_id` in (5 Udea, 44 Udea Veg, 85 Udea Frozen).

**Volumes (last 90 days).** Udea charged 520 × €0.25 and 50 × €0.70
(≈ €165 a quarter not recovered); the 53 products sold 384 units on 269
tickets (about four deposit items a day). All-time charged: €491.25 (313) and
€156.10 (315).

**The till (uniCenta oPOS 3.91.3).** No deposit products exist. The shop
already runs a manual deposit for reusable cups: product `4076` "2GoCup Cup"
(+€2.00) and `4078` "Cup Discount" (−€0.40, TAXCAT 000, category 081), the
latter used 143 times in 90 days, so negative-price return lines are proven
on this till. `PRODUCTS_COM` (auxiliary products) is empty and auxiliaries
need a catalogue tap, so they do not help for scanned items. `Ticket.Buttons`
supports `<event key="ticket.addline" .../>` (present, commented out), the
mechanism an automatic deposit line would use; product `ATTRIBUTES`
(Java Properties XML) are copied onto the ticket line and readable from such a
script. Test tills exist (`vm_Till3`, `Till 2` host properties).

**Credit notes.** `scripts/invoice-parser/parsers/invoice_udea.py` already
parses "Barrels returned" on Udea credit notes (Gb.rek 34010), which is the
supplier side of the refund loop.

## Owner decisions (2026-10-03)

- **Automatic** deposit line at the till (so staff need not know which items
  carry a deposit), not the manual cup-style tap.
- Deposit passed on **at cost** (0.25 / 0.70 / 0.10).
- Deposit lines **zero-rated** (TAXCAT `000`), as Udea charges them. Still
  worth a word with the accountant.

## The test till (facts, 2026-10-03)

- VirtualBox VM "Arch Shop" (guest hostname `archlinux`, NAT only). Runs
  uniCenta oPOS **3.91.3** from the shared folder
  `/home/jon/VirtualBox VMs/vb_arch_share/unicenta/` (jar, `lib/bsh-2.0b5.jar`,
  `start.sh`). The Planner decompiled that jar (CFR) to read the event API;
  the facts are in `plan.md` Context for cycle 1.
- It uses the **dev POS database** `unicenta2016` in the `mysql57` Docker
  container on `127.0.0.1:3307` (same as the app's `pos` connection; the VM
  sees it as `10.0.2.2:3307`). Production snapshot of 2026-08-14 plus test
  tickets. Restart the till after any change to `RESOURCES`.
- Event scripts: `Ticket.Buttons` resource declares
  `<event key="ticket.addline" code="…"/>`; the script gets `line`, `ticket`,
  `sales`, `taxeslogic`; returning non-null from `ticket.addline` means
  "handled". `ticket.change` after a plain add is **not** repainted, so the
  deposit line is added inside `ticket.addline`. `PRODUCTS.ATTRIBUTES` is a
  Java `Properties` XML blob and is copied onto the ticket line (and saved in
  `TICKETLINES.ATTRIBUTES`).

## What the till spike proved (cycle 1, 2026-10-03)

- A `ticket.addline` script that adds the product line and its deposit line
  itself and returns non-null works on 3.91.3: deposit directly under the
  product, totals right, no dialogs. Repeated scans make repeated pairs.
- `ticket.change` after + / − or a deletion repaints, so the Change script
  keeps pairs consistent: deleting the product removes its deposit, deleting
  the deposit brings it back, quantity edits follow. The till's own
  `ticket.change` after a plain scan is skipped when AddLine returns
  non-null, so nothing wired to `ticket.change` sees scans.
- Deposit lines save with `PRODUCT` = the `DEP-*` id, TAXID `000`, and
  `deposit.for` = the product's id in `TICKETLINES.ATTRIBUTES`; the product
  line keeps `deposit.id`. No `STOCKDIARY` row for deposit products
  (`product.service` = true on the line).
- A ticket of refund lines alone closes with a negative cash payment.
- Receipt printing needed no template change.
- Till scripts, setup and rollback: `docs/deposit/scripts/`.

## Recommendation (Planner, 2026-10-03)

Three cycles, each useful on its own.

1. **Identify and record** (no till change). Capture the per-line barrel code
   explicitly in the Udea delivery and invoice parsers and store it on
   `delivery_items` (new nullable `barrel_code` column) instead of leaving it
   as a trailing token in the description. Add a product-to-deposit mapping in
   the Laravel database (product barcode → `barrel_codes.id`, with source
   `invoice` or `manual`, and a confirmed flag), a backfill command that seeds
   it from the 53 products above, and an office screen next to `/barrel-codes`
   to confirm, change or remove the tier. Every future import that sees a
   code on a product line adds or re-confirms the mapping and flags new ones.
2. **Charge and refund at the till.** Superseded by the owner's decision for
   the automatic approach (now cycle 1, the spike; the app side follows as
   cycle 2). Original text kept for the reasoning: Recommended: the proven cup pattern.
   One deposit product per tier ("Bottle deposit €0.25", "€0.70", "€0.10";
   TAXCAT 000, ISSERVICE 1, own category) and one return product per tier at
   the negative price. Created by the app through the existing POS product
   write path (`VoucherPosProductService` is the pattern). The cashier adds
   the deposit line per bottle. The app audits this daily: deposit-mapped
   units sold vs deposit lines sold, so misses are visible. The automatic
   alternative (a `ticket.addline` BeanShell script reading a `deposit`
   property the app writes into `ATTRIBUTES`) is the upgrade if the audit
   shows too many misses; it needs a test-till session first. Baking the
   deposit into the sell price is rejected: it would be taxed at the product's
   VAT rate, skew margins and sales reports, and still need per-product
   refund lines.
3. **Reconcile.** A report of the deposit float: charged by Udea
   (`delivery_barrels`) vs charged to customers (till lines on the deposit
   products) vs refunded (return products) vs credited by Udea (credit-note
   "Barrels returned"). The existing "Future Enhancements" in
   `docs/features/barrel-deposit-tracking.md` (returns tracking, reconciliation
   report) land here.

Owner decisions needed before cycle 2's plan: pass the deposit on at cost
(0.25 / 0.70 / 0.10)? Refund a returned bottle regardless of where it was
bought? VAT treatment of the deposit lines (proposed TAXCAT 000 as Udea
charges them, to be confirmed with the accountant). Whether a standalone
return ticket (negative total, cash out) is acceptable on this till is a
till-test item.

## Cycle 4 inputs (owner, 2026-10-17)

- `/deposits` shows nothing under suggested or confirmed on this machine: the
  database is production's and production never ran the evidence steps. The
  Implementer runs them on dev (plan step 1); the owner runs them on production.
- The owner is not sure which bottled products already had their shelf price
  raised to cover unreturned bottles. Before the till adds the deposit on top,
  each deposit product's price is reviewed on `/deposits`: current gross and
  net, latest Udea docket cost and markup, the last lasting price step from
  `sales_daily_summary` with the cost before it, Udea's advice price
  (`delivery_items.sale_price`, dockets from 2026-04-16). Decision per row:
  Keep, or Lower by the deposit (writes `PRICESELL` through the product-page
  recipe and logs a `LabelLog` price update). Recorded on `product_deposits`.
- Example found while planning: Your Organic Nature Lime juice 200ml, net
  1.99 from 2024-01 to 2025-11, 2.70 since 2025-12-27 (+0.71) while Udea's
  cost moved 1.31 → 1.36.
- The redesign (2026-10-17): Claude Design project "Incomplete request", file
  `Bottle Deposits Review.dc.html`, option **2a**, read through the design MCP
  (`claude mcp add --scope user --transport http claude-design https://api.anthropic.com/v1/design/mcp`,
  then `/design-login` and `/design-consent` in a new session). Planner
  decisions on it are in `plan.md`, "Revision 3": Tailwind not the Modernist
  stylesheet, default choice Keep, bulk confirms never change prices, Undo
  restores the price, Last-price-change kept under the Price cell, status
  filter kept, delivery % in `app_settings` (`deposits.delivery_pct`).
- The owner prefers the Modernist look (Archivo, red accent #ec3013, zero
  radius, 2px rules) to the app's Tailwind styling and may want it elsewhere
  later; `/deposits` is the first page on it. Dark mode is not supported by
  the system: the page keeps a light ground.
- Hidden inputs positioned `absolute` (the system's `.seg-opt input`, Tailwind's
  `sr-only`) need a positioned ancestor inside the admin shell: `<main>` scrolls,
  not the document, so an unpositioned hidden radio sits against the viewport,
  stretches the window, and focusing it on click scrolls the shell away.
  `.ds-modernist` is `position: relative` for this reason. Scripted
  `element.click()` does not move focus and will not show it; `clicks.mjs`
  sends real pointer events.
- Headless Google Chrome is at `/usr/bin/google-chrome` on this machine and
  the Implementer can mint a logged-in session for user 1 and drive the real
  page over the DevTools protocol (scripts kept from Revision 4 in
  `docs/deposit/scripts/browser/`). Plans can now ask for real browser checks
  with console output instead of leaving them to the owner.
- Discounts at the till: a discounted line carries `product.name` ending
  ` - 5%` / ` - 10%` in `TICKETLINES.ATTRIBUTES` and no `product.barcode`;
  `TICKETLINES.PRICE` is the discounted net price. `sales_daily_summary.avg_price`
  therefore mixes discounted and full-price sales; use the lines for price
  history (Revision 2). Some products sell discounted almost every time
  (Landpark Carbonated Lemon).
- Ommelanden Buttermilk 1l is already on the till at net 2.50 while every
  sale since June was at 2.80: lowering has started by hand somewhere.
- New Udea deposit code since cycle 3: `322` "Fles groot statiegeld NL"
  €0.25 (6 lines). Shown on the tiers card; left off until the owner decides.

## Cycle 5 inputs (collected 2026-10-05; the float report)

- The deposit float report: charged at the till (lines on `DEP-*` products,
  grouped by **tier**, not till product) vs refunded (`DEP-*-RET`) vs Udea's
  charges (`delivery_barrels`) and credits (credit-note "Barrels returned",
  parsed by `invoice_udea.py` but not stored anywhere yet).
- `delivery_barrels` can hold an order twice (delivery 132: 17 rows, 313 as
  13 + 54): de-duplicate per order before summing, and make `storeBarrelItems`
  idempotent per order.
- Three dockets off on 313 (deliveries 95, 115, 136) with every line captured.
- `<x-product-search>`: clear `selectedId` when the user types a new search
  (the add card otherwise keeps the previous pick).
- `/deposits` add card: preselect the current tier for an already-mapped
  product, or warn before re-tiering.
- Owner edits before publishing the SOP: the shelf-label sentence (labels do
  not show deposits).

## Cycle 3 inputs (collected 2026-10-04; folded into cycle 3's plan, except the float report → cycle 4)

- `todo.txt` (owner, 2026-10-04 09:50): the add-product search on `/deposits`
  "doesn't take barcodes". Finding: it is the shared picker and its endpoint
  does match barcodes; the card just never shows what was picked (the picker
  clears its box on select). Cycle 3 step 5 fixes the card; the Implementer
  reproduces in the browser.

- Production rollout: install `script.Deposit.AddLine` / `script.Deposit.Change`
  and the two `Ticket.Buttons` events on the production POS (same files as
  dev, `docs/deposit/scripts/`), run the migrations, `deposits:backfill-delivery-items`,
  `deposits:refresh-suggestions`, the owner confirms on `/deposits`,
  `deposits:sync-pos`, restart tills. Say plainly in the notes that a `deposit.*`
  property added by hand on the till is cleared on the next Udea import.
- Reconciliation groups by **tier**, not till product (a tier price change
  creates a new `DEP-<cents>` pair and keeps the old refund button).
- Three dockets (deliveries 95, 115, 136) are off by 12 / 5 / 12 units on 313
  with every line captured: under-delivery or case-vs-bottle counts; the float
  report should surface such gaps.
- The parser's deposit reconciliation warnings survive only as a count in the
  import flash; keep them visible on the delivery page.
- Ten pre-2026-01-27 garbled lines stay uncoded (no barrels rows to vouch for
  them); accepted, no action.
- Owner confirmed 2026-10-04: juice and buttermilk both still add their deposit
  line on the test till after cycle 2.

## Owner decision on documents (2026-10-03)

Both Udea documents are brought in (delivery dockets `Order_<n>.pdf` through
the PDF import on `/deliveries`; accounting invoices `UdeaFactuur<n>.pdf`
through the invoice bulk upload). **The delivery docket drives the deposit
mapping**: it is reviewed line by line on import, the invoice upload is not.
Cycle 2 therefore hooks only the delivery import; the accounting parser
emits the barrel code but nothing records it.

## Rules every change must respect

To be filled by the Planner once the first task is described. Standing rules
from the project:

1. The POS (uniCenta) database is read-only except through the existing stock
   paths. Deposit data lives in the Laravel database.
2. Eloquent models only, business logic in services, tests for every change
   (`CLAUDE.md`).
3. If a task touches the Shop view (`/shop`), the rules in
   `docs/shop_new/README.md` apply too (view contract, verbatim design block,
   PIN route allow-list).
4. Deposit codes come from the invoice and are confirmed by the owner; the app
   never guesses a deposit from a product's name or size.
5. Till products the app creates for deposits are written through a service
   (the voucher POS product service is the pattern), never by hand in the POS
   database, so the mapping between tier and till product stays in the app.

## Browser checks

Two ways, both dev only:

1. **Playwright MCP** (preferred for an Implementer session): `.mcp.json` at the
   repo root registers `playwright` (headless Chrome at `/usr/bin/google-chrome`,
   isolated profile, logged in through the storage-state file
   `~/.cache/osmanagercl/playwright-state.json` that `mint.php --playwright`
   writes). A session in this repo approves the server once and gets the
   `mcp__playwright__browser_*` tools. Screenshots: absolute paths under the
   session scratchpad. Login page instead of the app → re-mint, `browser_close`.
2. **DevTools scripts** in `docs/deposit/scripts/browser/` for measured checks
   (row heights, computed styles, real-pointer clicks, flows).

Plans should ask for the browser check explicitly (what to click, what to
read back, console clean) and expect the tool output pasted into the report.

## Kickoff prompts

Planner:
```
Read docs/deposit/planimp.md. You are the Planner. <describe the task>
```

Implementer:
```
Read docs/deposit/planimp.md. You are the Implementer. Implement docs/deposit/plan.md.
```

Planner review:
```
Read docs/deposit/planimp.md. You are the Planner. Review docs/deposit/implemented.md.
```

## Folder layout

- `planimp.md` — the protocol
- `README.md` — this file, Planner-owned working notes
- `plan.md` — Planner-owned
- `plan-<slug>.md` / `implemented-<slug>.md` — side plans (currently `plan-browser-mcp.md`)
- `implemented.md` — Implementer-owned
- `archive/YYYY-MM-DD-<slug>/` — accepted cycles (created on first acceptance)
- `findings/` — things found after a cycle is accepted (created when needed)
- `parked/` — plans put aside before implementation (created when needed)
