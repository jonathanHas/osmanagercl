# Cash Reconciliation — Sync from POS

## Overview

The **Sync from POS** button on the Cash Reconciliation page (`/cash-reconciliation`) lets a manager
force a re-import of a day's cash figures from the legacy POS (`money`) table when the automatic import
left the reconciliation empty.

This is a companion to the main [Cash Reconciliation System](./cash-reconciliation.md) — see that doc
for the full feature set.

## The problem it solves

When you open a day, `CashReconciliationRepository::getOrCreateReconciliation()` auto-creates the
reconciliation and imports the legacy POS `money` row (denomination counts, card, cashback, floats,
supplier payments, notes). **That import only runs in the create branch** (`if (! $reconciliation->exists)`).

The POS **cash/card totals** shown at the top of the page come from a *separate* live query against the
POS `PAYMENTS` table (`calculatePosTotals()`), so they always appear.

If the page is opened **before the old POS system has written that day's `money` row** (i.e. before the
operator completed the end-of-day cash count in the old system), Laravel persists a reconciliation of
zeros and **never re-pulls** it — even after the old system later records the data. The symptom:

> POS Cash / POS Card totals are populated, but the Cash Count denominations, Card field and floats are
> all zero.

## How the button works

| Step | Behaviour |
|------|-----------|
| 1 | User clicks **Sync from POS** (amber button in the navigation bar) and confirms the dialog. |
| 2 | `POST /cash-reconciliation/sync` → `CashReconciliationController::sync()`. |
| 3 | `CashReconciliationRepository::resyncFromLegacy($date, $tillId, $tillName)` runs. |
| 4 | The reconciliation is located (or created) for that till/date, live POS cash/card totals are refreshed, and the legacy `money` row is re-fetched. |
| 5 | If the `money` row exists → denominations/card/floats/variance are overwritten from it, and supplier payments + notes are re-imported. |
| 6 | Redirects back to the same day with a flash message. |

### Result messages

- **Success** — `"Synced cash figures from the POS system."` (legacy `money` row found and applied)
- **Warning** — `"The POS system has no cash count for this day yet — POS totals were refreshed."`
  (no legacy `money` row; only the live POS totals were refreshed)
- **Error** — `"Failed to sync from POS: …"` (e.g. no `CLOSEDCASH` record for that till/date)

### Why it overwrites

For historical days the legacy POS `money` row is the authoritative source of the denomination counts,
so a sync intentionally overwrites the current values. A JavaScript `confirm()` guard on the button
prevents a manually-entered day being clobbered by an accidental click.

## Implementation

| Layer | Location | Notes |
|-------|----------|-------|
| Route | `routes/web.php` — `POST /cash-reconciliation/sync` (`cash-reconciliation.sync`) | Guarded by `permission:cash_reconciliation.create`. |
| Controller | `App\Http\Controllers\Management\CashReconciliationController::sync()` | Validates `date` + `till_id`, resolves till name, flashes result. |
| Repository | `App\Repositories\CashReconciliationRepository::resyncFromLegacy()` | Force re-import for an existing/missing reconciliation. |
| Repository | `App\Repositories\CashReconciliationRepository::applyLegacyMoney()` | Shared private helper — the legacy `money` → reconciliation fill logic, reused by both `getOrCreateReconciliation()` and `resyncFromLegacy()`. |
| View | `resources/views/management/cash-reconciliation/index.blade.php` | Button in the nav bar; renders in both the loaded-form and empty/error states. Visibility gated with `auth()->user()?->can('cash_reconciliation.create')` (not the `@can` Blade directive, which silently fails for custom permissions). |

## Permissions

Uses the existing `cash_reconciliation.create` permission (same as saving a reconciliation).

## Troubleshooting

### Button reports "no cash count for this day yet" on the dev environment
The development environment runs against a **separate, static POS snapshot** — not the live POS. If that
snapshot doesn't contain the day's `money` row (or has aged out of the 6-month till-activity window used
by `getAvailableTills()`), the sync will legitimately report no data even though it would work on
production. Reproduce sync behaviour on dev only with a POS snapshot that contains the relevant day.

### Empty till list / "No till is available for the selected period"
`getAvailableTills()` only returns tills with `CLOSEDCASH` activity in the last 6 months. If the POS
snapshot's most recent close is older than that, no tills resolve. Both
`getOrCreateReconciliation()` and `resyncFromLegacy()` throw a catchable `Exception` in this case, so the
page shows a friendly error rather than a fatal `TypeError`.

## Related

- [Cash Reconciliation System](./cash-reconciliation.md) — main feature documentation
- [Cash Closed (End-of-Day Overview)](./cash-closed.md) — full cash-to-bank pipeline
