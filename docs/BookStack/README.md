# BookStack (staff procedures / SOPs) — plan / implement track

Procedures (SOPs) work runs here, separate from the Shop mode cycles in
`docs/planImp/` and the voucher work in `docs/vouchers/`. Same protocol, same
file roles: see [`planimp.md`](./planimp.md) (a copy of `docs/planImp/planimp.md`
with paths pointing here).

## The decision this track rests on (owner, 2026-09-30)

- SOPs are **written in BookStack** by the owner and the managers. The app does
  not get its own editor or hand-written HTML pages.
- The app **reads** BookStack through its API and shows a procedure **inside our
  own layouts** (Shop mode on the tablets and phones, office layout on the PCs)
  rather than linking out. Reasons: PIN users have no BookStack login, the idle
  lock only runs on our Shop pages, and BookStack is plain HTTP on
  `bookstack.internal`, a name the tablets may not resolve.
- A page is linked to a screen by a BookStack tag `screen` = the route name
  (for example `shop.deliveries.scan`). No mapping table in the app.
- Any signed-in user may read a tagged page; no new permission.

Feature doc (written in cycle 1): `docs/features/sops-bookstack.md`.

## Where things stand (2026-09-30)

| | |
|---|---|
| Current cycle | **Cycle 1** — help button on every screen + in-app reader. Revision 1 implemented (Opus) and reviewed (Fable). `plan.md` is **READY at Revision 2**: three small fix-up steps (R1–R3) for the Implementer; `implemented.md` is the Revision 1 report, status DONE |
| Tests | Revision 1: full suite 15 failed / 921 passed. The 15 are the pre-existing baseline (CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2, TestScraperController ×1, UdeaScrapingService ×7), not this work |
| Committed | Nothing from this track is committed yet. The working tree also carries another track's uncommitted files (`docs/features/invoice-parser-integration.md`, `scripts/invoice-parser/…`) |
| Deployed | No |

### Revision 2, what the Implementer still has to do

1. **R1** — after a failed BookStack fetch, keep the fallback index for 60 s
   (today every page view retries during an outage and logs a warning).
2. **R2** — the image proxy must also refuse a path containing `%`, `\`, `?`,
   `#` or control characters (a double-encoded `%252e%252e` slips past the
   `..` check today).
3. **R3** — two tests for the office sidebar row and the "Procedures
   (BookStack)" link.

Details, tests and checks are in `plan.md` under "Revision 2 steps".

### Open items for the owner

1. **Create a BookStack API token** and put `BOOKSTACK_URL=http://bookstack.internal`,
   `BOOKSTACK_TOKEN_ID` and `BOOKSTACK_TOKEN_SECRET` in the dev `.env`. Neither
   session has been able to run the live check; the API response shapes are
   still unverified against this instance (v25.05.1). The plan's client handles
   search results with or without `tags`.
2. **Look at it in a browser** once the token is in: tag one page
   `screen` = `shop.stock-scan`, open `/shop/stock-scan`, press the help button.
   Watch for: images and tables reading well; an image on its own line not
   stretched to full width; the topbar still on one line on a phone. Then the
   same from the office layout (sidebar row "How to: this page"). Tell the
   Planner whether the index came back populated.
3. **BookStack setup before production** (also in the feature doc): a shelf for
   shop SOPs with a book per area; a role that can view only that shelf and has
   "Access system API", a user with that role, and its token in production
   `.env`; check the production machine resolves `bookstack.internal`; manager
   editor accounts, and their PCs resolving `bookstack.internal`.
4. **Deploy** needs `composer install` (new package `symfony/html-sanitizer`)
   and `npm run build`.

### Ideas for cycle 2 (not planned yet)

- A "Procedures" tile on Shop Home that lists and searches the whole SOP shelf
  in-app, reusing the reader.
- Links between BookStack pages opening in our reader (today they open
  BookStack in a new tab, which on a PIN tablet hits its login).

## Kickoff prompts

Implementer (Revision 2):
```
Read docs/BookStack/planimp.md. You are the Implementer. Implement the Revision 2 steps in docs/BookStack/plan.md.
```

Planner review of Revision 2:
```
Read docs/BookStack/planimp.md. You are the Planner. Review docs/BookStack/implemented.md (Revision 2 section).
```

On acceptance: `mkdir -p docs/BookStack/archive/2026-09-30-cycle-1-help-button`
and move `plan.md` and `implemented.md` there; the next task starts with empty files.

## Folder layout

- `planimp.md` — the protocol
- `plan.md` — Planner-owned
- `implemented.md` — Implementer-owned
- `archive/` — accepted cycles (created on first acceptance)
- `findings/` — things found after implementation (fold into the next plan, then move to the cycle's archive folder)
