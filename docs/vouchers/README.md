# Vouchers — plan / implement track

Gift-voucher work runs here, separate from the Shop mode cycles in `docs/planImp/`.
Same protocol, same file roles: see [`planimp.md`](./planimp.md) (a copy of
`docs/planImp/planimp.md` with paths pointing here).

## Where things stand (2026-09-29)

| | |
|---|---|
| Current cycle | Vouchers cycle 3 — selling a voucher at the till activates it |
| `plan.md` | `Status: READY`, Revision 1 (Planner: Fable 5.1) |
| `implemented.md` | not started |
| Cycle 2 | Activity screen `/vouchers/activity`: ACCEPTED 2026-09-29, uncommitted at acceptance. `archive/2026-09-29-cycle-2-activity-screen/` |
| Cycle 1 | Till-driven redemption (called cycle 28 at the time): ACCEPTED, committed (421df081), deployed. `archive/2026-09-27-cycle-28-till-voucher-redemption/` |
| Production | Scheduler runs every minute from `/etc/cron.d/osmanager` as `www-data`. `crontab -l` shows nothing, by design |

Open items for the owner:
1. **Parked:** 37 of 39 production vouchers have no till product. See [`findings/2026-09-29-production-backfill-not-run.md`](./findings/2026-09-29-production-backfill-not-run.md).
2. **Later, on request:** retire the till products Voucher 10/20/50 Euro (`6013`, `6014`, `6012`) once cycle 3 is working on production. The owner asked to be reminded then.
3. Browser check of cycle 2 as a manager: `/vouchers/activity` (steps in the archived plan, Verification 9).
4. Scan one printed GV label with the real scanner at the real till (finding 1 in [`findings/2026-09-28-till-testing.md`](./findings/2026-09-28-till-testing.md)).

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
