# Bottle deposit till scripts (uniCenta oPOS 3.91.3)

The two BeanShell event scripts that make the till add a "Bottle deposit" line
automatically under any product whose `PRODUCTS.ATTRIBUTES` carry
`deposit.id`, `deposit.name` and `deposit.price` (written by the app's
`DepositPosService`).

| File | Installed as `RESOURCES.NAME` | Event in `Ticket.Buttons` |
|---|---|---|
| `script.Deposit.AddLine.bsh` | `script.Deposit.AddLine` | `ticket.addline`: adds the deposit line directly under the scanned product |
| `script.Deposit.Change.bsh` | `script.Deposit.Change` | `ticket.change`: keeps deposit quantities in step, removes orphans, restores a deleted deposit |

Install, check or roll back on the POS database the app points at:

```bash
php artisan deposits:install-till --check
php artisan deposits:install-till            # asks to confirm on a non-dev POS
php artisan deposits:install-till --rollback
```

Restart uniCenta on every till afterwards: it reads `Ticket.Buttons` and the
scripts only at start-up.

These files are installed byte-for-byte; do not edit them without re-running
the till test matrix. The history (decompiled event API, the spike, the test
matrix and its results) is in `docs/deposit/`; the runbook is in
`docs/features/barrel-deposit-tracking.md` ("Going live on production").
