# Cash Closed (End-of-Day Cash Management)

## Overview

The Cash Closed system is the Laravel replacement for the legacy `sales_closed_cash.php` page. It handles the complete end-of-day cash workflow: counting the physical cash in the till, recording floats, tracking supplier payments, and reconciling against POS totals.

This feature is implemented as the **Cash Reconciliation** module and integrates with the **Cash Lodgements** module to provide a complete cash-to-bank pipeline.

## Related Documentation

- **[Cash Reconciliation (Till Count)](./cash-reconciliation.md)** -- The count: screens, identity, arithmetic, data, commands
- **[POS Integration](./pos-integration.md)** -- How the POS database connection works

## Data Flow

```
POS CLOSEDCASH (till close)
    |
    v
Cash Reconciliation (count cash, record float, supplier payments)
    |
    v  "Available to Lodge" = Total Cash - Float
    |  (supplier payments are already paid out before the cash count)
    |
Cash Lodgement (record what was deposited at the bank)
    |
    v
Bank Statement Match (verify the deposit appeared on the statement)
```

> **Note**: Supplier payments are deducted from the till during the day,
> so the end-of-day denomination count already reflects those payments.
> The available-to-lodge calculation only subtracts the float.

## The count

The count itself (screens, who-is-counting, arithmetic, data columns, tills) is
documented in [Cash Reconciliation (Till Count)](./cash-reconciliation.md). Since
2026-11-07 it is one form rendered on the Shop tablet (`/shop/till-count`) and in the
office (`/cash-reconciliation`), with the figures computed by
`App\Services\Cash\TillCountService`.

## Key Files

| File | Purpose |
|---|---|
| `resources/views/shop/partials/till-count.blade.php` | The count form (Shop and office) |
| `resources/views/shop/till-count.blade.php` | Shop screen |
| `resources/views/management/cash-reconciliation/index.blade.php` | Office page: the form, history, legacy re-import, export |
| `resources/js/shop/till-count.js` | Alpine component (`shopTillCount`) |
| `app/Services/Cash/TillCountService.php` | POS totals, previous float, save, variance, legacy import |
| `app/Services/Cash/TillCountPage.php`, `CountIdentity.php` | Page data; the "counting as" session token |
| `app/Services/Shop/IdentityVerifier.php` | PIN / password check without signing in |
| `app/Http/Controllers/Cash/TillCountController.php` | identify / forget / store endpoints |
| `app/Repositories/CashReconciliationRepository.php` | Compatibility facade (till picker, legacy import, export) |
| `app/Models/Till.php`, `CashReconciliation.php`, `CashReconciliationPayment.php`, `CashReconciliationNote.php` | Models |

## Legacy Migration

### Table Mapping

| Legacy Table (POS) | Legacy Field | Laravel Table | Laravel Field | Notes |
|---|---|---|---|---|
| `money` | `cash50` (total EUR) | `cash_reconciliations` | `cash_50` (count) | Divided by 50 |
| `money` | `cash20` (total EUR) | `cash_reconciliations` | `cash_20` (count) | Divided by 20 |
| `money` | `cash10c` (total EUR) | `cash_reconciliations` | `cash_10c` (count) | `(int) round(val / 0.1)` |
| `money` | `noteFloat` | `cash_reconciliations` | `note_float` | Direct |
| `money` | `coinFloat` | `cash_reconciliations` | `coin_float` | Direct |
| `payeePayments` | `payeeID`, `amount` | `cash_reconciliation_payments` | `supplier_id`, `amount` | Direct |
| `dayNotes` | `message` | `cash_reconciliation_notes` | `message` | Direct |
| `lodgeCnt` | `lodgeAmount` | `cash_lodgements` | `cash_amount` | Direct |

### Key Difference

The legacy system stored **total values** in the money table (e.g., `cash50 = 400` meaning EUR400 in fifty-euro notes). The Laravel system stores **counts** (e.g., `cash_50 = 8` meaning 8 fifty-euro notes). The import uses `(int) round()` for coin denominations to avoid floating point truncation (e.g., `5.10 / 0.1 = 50.999...` truncated to 50 with `intval()`).

### Import Commands

```bash
# Backfill the old system's counts (skips rows that already exist; oldest first)
php artisan cash:import-legacy-reconciliations --from=2024-01-01 --to=2025-12-31 --dry-run

# Import historical lodgement data
php artisan cash:import-legacy-lodgements --from=2024-01-01 --to=2025-12-31

# Auto-match lodgements to reconciliations
php artisan cash:auto-match-money-ids

# Re-resolve previous floats and recompute every stored variance (once, after 2026-11-07)
php artisan cash:recalculate-variances --dry-run
```

Do not delete and re-import reconciliations: `cash_bag_verifications` and
`cash_lodgement_matches` cascade on delete. Visiting a page no longer creates rows;
the lodgements page imports the old system's count for a recent close that has none,
and otherwise lists the close as needing a count.

## Bag Verification & Lodgement Workflow

### The Process
1. **Employee** closes the till daily, stores cash (minus float) in a sealed bag — one bag per till per day
2. **Manager** visits `/management/cash-lodgements` every few days
3. **Legacy import**: On page load, any till close in the last 60 days without a reconciliation is imported from the old system's `money` table if it has a row there; nothing is created otherwise.
4. **Needs Cash Count** section shows closes with no counted reconciliation. Each row links to the office count page for that date and till.
5. **Pending Bags** section shows reconciliations with cash available (denominations counted) but no bag verification yet. Each row includes a **"View Recon"** link (opens in new tab) to help investigate discrepancies.
6. Manager clicks **"Count Bag"** — an inline denomination counting form expands. Empty fields are treated as 0.
7. Manager counts notes and coins, system calculates total and compares against expected (available-to-lodge from reconciliation)
8. Manager clicks **"Confirm Bag Count"** — creates a `CashBagVerification` record
9. Verified bags appear in **"Verified Bags — Ready to Lodge"** section with select-all checkbox support
10. Manager selects bags and clicks **"Create Lodgement"** — creates a `CashLodgement` record with the combined total

### Database: `cash_bag_verifications` table
- `id` (uuid) — primary key
- `cash_reconciliation_id` (uuid, unique) — one verification per reconciliation
- `cash_50..cash_10c` (integer) — denomination counts
- `counted_total` (decimal) — calculated from denominations
- `expected_total` (decimal) — snapshot of available-to-lodge at verification time
- `variance` (decimal) — counted minus expected
- `cash_lodgement_id` (uuid, nullable) — linked once included in a lodgement
- `verified_by` (FK users), `verified_at` (timestamp)

### Key Files
| File | Purpose |
|---|---|
| `app/Models/CashBagVerification.php` | Model with calculateTotal(), relationships |
| `database/migrations/2026_04_10_153939_create_cash_bag_verifications_table.php` | Migration |

### Routes
| Route | Method | Description |
|---|---|---|
| `POST /management/cash-lodgements/verify-bag` | verifyBag | Create bag verification |
| `POST /management/cash-lodgements/create-lodgement` | createLodgement | Create lodgement from verified bags |

## Key Integration: Reconciliation + Lodgements

When a user is preparing a bank lodgement, the lodgement views show a **side-by-side comparison** with the reconciliation data. This helps investigate discrepancies.

### Comparison Partial
`resources/views/management/cash-lodgements/partials/reconciliation-comparison.blade.php`

Shows:
- **Reconciliation side**: Total cash counted, notes/coins breakdown, float retained, available to lodge
- **Lodgement side**: Cash lodged, cheque lodged, total lodged
- **Variance row**: Difference with colour coding (green < EUR1, amber < EUR20, red > EUR20)
- Link to edit the reconciliation

Used in the lodgement show view (`cash-lodgements/show.blade.php`).

The lodgement index view (`cash-lodgements/index.blade.php`) shows columns for **Lodgement Date**, **Till Closed** date (from POS `CLOSEDCASH.DATEEND`), **Till**, **Amount**, **Source**, and **Actions**. Results are sorted by till closed date descending.

## Routes

| Route | Controller | Description |
|---|---|---|
| `GET /cash-reconciliation` | CashReconciliationController@index | Main reconciliation form |
| `POST /cash-reconciliation/store` | CashReconciliationController@store | Save reconciliation |
| `GET /cash-reconciliation/previous-float` | CashReconciliationController@getPreviousFloat | AJAX: get previous day's float |
| `GET /cash-reconciliation/reconciliation` | CashReconciliationController@getReconciliation | AJAX: load reconciliation data |
| `GET /cash-reconciliation/export` | CashReconciliationController@export | Export to CSV |
| `GET /management/cash-lodgements` | CashLodgementController@index | List lodgements with recon variance |
| `GET /management/cash-lodgements/{id}` | CashLodgementController@show | Lodgement detail with comparison panel |

## Permissions

- `cash_reconciliation.view` -- View the reconciliation form (employees)
- `cash_reconciliation.create` -- Create/edit reconciliations (employees)
- `cash_reconciliation.export` -- Export data to CSV (managers)
- Lodgements button: visible only to `admin` and `manager` roles

## Known Issues & Fixes Applied

- **Floating point truncation on coin import**: `intval(5.10 / 0.1)` = 50 not 51. Fixed by using `(int) round()` instead
- **Previous float not found**: Was only checking Laravel records. Now falls back to POS `CLOSEDCASH JOIN money` table
- **Supplier payments excluded from variance**: Day's Cash Taking formula was missing supplier payments. Now matches legacy formula
- **Alpine.js scoping bug**: Supplier payments had a nested `x-data` scope that prevented `totalPayments` from reaching the summary. Moved to parent scope
