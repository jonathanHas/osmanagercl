# Vouchers — plan / implement track

Gift-voucher work runs here, separate from the Shop mode cycles in `docs/planImp/`.
Same protocol, same file roles: see [`planimp.md`](./planimp.md) (a copy of
`docs/planImp/planimp.md` with paths pointing here).

## Where things stand (2026-09-28)

| | |
|---|---|
| Cycle | 28 — till-driven gift voucher redemption |
| `plan.md` | `Status: ACCEPTED`, Revision 1 (Planner: Fable 5.1), reviewed 2026-09-28 |
| `implemented.md` | `Status: DONE` — reviewed and accepted; see `## Review` in `plan.md` |
| Real-till test | Passed (applied + partial). See [`findings/2026-09-28-till-testing.md`](./findings/2026-09-28-till-testing.md) |
| Committed? | No. All cycle 28 changes are uncommitted in the working tree |

Open items for the owner (see `## Review` in `plan.md`):
1. Scan one printed GV label with the real scanner at the real till (finding 1; no code change unless it fails).
2. Browser checks as a manager: office `/vouchers` scan + Manual deduct, and `/vouchers/exceptions` + Mark reviewed on #430814.
3. Archive the cycle: `mkdir -p docs/vouchers/archive/2026-09-27-cycle-28-till-voucher-redemption` and move `plan.md` + `implemented.md` there.

## Kickoff prompts

Planner review:
```
Read docs/vouchers/planimp.md. You are the Planner. Review docs/vouchers/implemented.md and docs/vouchers/findings/.
```

Implementer (after the Planner bumps the revision):
```
Read docs/vouchers/planimp.md. You are the Implementer. Implement docs/vouchers/plan.md.
```

## Folder layout

- `planimp.md` — the protocol
- `plan.md` — Planner-owned
- `implemented.md` — Implementer-owned
- `findings/` — things found after implementation (fold into the next plan, then move to the cycle's archive folder)
- `archive/YYYY-MM-DD-<slug>/` — accepted cycles
- `parked/` — plans put aside before implementation
- `scripts/` — dev/test helpers (never run against production):
  - `numeric_barcode.php` — give a voucher's till product a numeric barcode for the till keypad
  - `reset_voucher.php` — put a voucher back to active with a balance (writes an audit row)
  - `simsale.php` — insert a fake till sale into the dev POS when no till is running

Each script documents its usage at the top, e.g.
`VOUCHER=GVVUF2SUACUM BALANCE=20 php artisan tinker --execute="require 'docs/vouchers/scripts/reset_voucher.php';"`

## Testing at the till (dev)

1. uniCenta (VirtualBox) points at the dev POS MySQL, the same `pos` connection as the app.
2. The till keypad is numeric: key the numeric barcode printed by `numeric_barcode.php` (currently `2990000000019` for `GVVUF2SUACUM`), or use a scanner on a printed label.
3. Ring up goods, key the voucher, Pay → Voucher → amount → finish.
4. No cron on dev: run `php artisan vouchers:sync-till` by hand (or scan the voucher in the app, which syncs on lookup).

Feature documentation: [`docs/features/voucher-management.md`](../features/voucher-management.md).
