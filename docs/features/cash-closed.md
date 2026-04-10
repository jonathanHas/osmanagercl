# Cash Closed (End-of-Day Cash Management)

## Overview

The Cash Closed system is the Laravel replacement for the legacy `sales_closed_cash.php` page. It handles the complete end-of-day cash workflow: counting the physical cash in the till, recording floats, tracking supplier payments, and reconciling against POS totals.

This feature is implemented as the **Cash Reconciliation** module and integrates with the **Cash Lodgements** module to provide a complete cash-to-bank pipeline.

## Data Flow

```
POS CLOSEDCASH (till close)
    |
    v
Cash Reconciliation (count cash, record float, supplier payments)
    |
    v  "Available to Lodge" = Total Cash - Float - Supplier Payments
    |
Cash Lodgement (record what was deposited at the bank)
    |
    v
Bank Statement Match (verify the deposit appeared on the statement)
```

## Related Documentation

- **[Cash Reconciliation](./cash-reconciliation.md)** -- Full technical documentation for the reconciliation system (models, repository, controller, API)
- **[POS Integration](./pos-integration.md)** -- How the POS database connection works

## What the System Does

1. **Select till and date** -- Load the POS closed-cash record for that session
2. **View POS summary** -- See what the POS recorded (cash, card, debt, free totals)
3. **Count denominations** -- Enter counts for each note (EUR50-EUR5) and coin (EUR2-10c)
4. **Record float** -- Enter the note float and coin float left in the till
5. **Card & cashback** -- Record card payments and cashback amounts
6. **Other payments** -- Cheques, debt, debt repayments, free items, vouchers, money added
7. **Supplier payments** -- Record any cash payments made to suppliers from the till
8. **Notes** -- Add daily comments (who was paid, what was bought, etc.)
9. **Review variance** -- Compare counted cash vs POS figures
10. **Available to Lodge** -- See how much cash should go to the bank

## Key Integration: Reconciliation + Lodgements

When a user is preparing a bank lodgement, the lodgement views show a **side-by-side comparison** with the reconciliation data. This helps investigate discrepancies -- if the bank deposit doesn't match the available-to-lodge figure, the user can see exactly where the difference is (float, supplier payments, counting error, etc.).

### Comparison Panel Shows:
- Total cash counted (with notes/coins breakdown)
- Float retained (note + coin)
- Supplier payments deducted
- Available to lodge (calculated)
- Actual lodgement amount
- Variance with colour coding (green < EUR1, amber < EUR20, red > EUR20)

## Legacy Migration

### Table Mapping

| Legacy Table (POS) | Legacy Field | Laravel Table | Laravel Field | Notes |
|---|---|---|---|---|
| `money` | `cash50` (total EUR) | `cash_reconciliations` | `cash_50` (count) | Divided by 50 |
| `money` | `cash20` (total EUR) | `cash_reconciliations` | `cash_20` (count) | Divided by 20 |
| `money` | `noteFloat` | `cash_reconciliations` | `note_float` | Direct |
| `money` | `coinFloat` | `cash_reconciliations` | `coin_float` | Direct |
| `payeePayments` | `payeeID`, `amount` | `cash_reconciliation_payments` | `supplier_id`, `amount` | Direct |
| `dayNotes` | `message` | `cash_reconciliation_notes` | `message` | Direct |
| `lodgeCnt` | `lodgeAmount` | `cash_lodgements` | `cash_amount` | Direct |

### Key Difference

The legacy system stored **total values** in the money table (e.g., `cash50 = 400` meaning EUR400 in fifty-euro notes). The Laravel system stores **counts** (e.g., `cash_50 = 8` meaning 8 fifty-euro notes). The import command handles this conversion.

### Import Commands

```bash
# Import historical reconciliation data
php artisan cash:import-legacy-reconciliations --from=2024-01-01 --to=2025-12-31

# Import historical lodgement data
php artisan cash:import-legacy-lodgements --from=2024-01-01 --to=2025-12-31

# Auto-match lodgements to reconciliations
php artisan cash:auto-match-money-ids
```

## Routes

| Route | Controller | Description |
|---|---|---|
| `GET /cash-reconciliation` | CashReconciliationController@index | Main reconciliation form |
| `POST /cash-reconciliation/store` | CashReconciliationController@store | Save reconciliation |
| `GET /management/cash-lodgements` | CashLodgementController@index | List lodgements with recon variance |
| `GET /management/cash-lodgements/{id}` | CashLodgementController@show | Lodgement detail with comparison panel |

## Permissions

- `cash_reconciliation.view` -- View the reconciliation form
- `cash_reconciliation.create` -- Create/edit reconciliations
- `cash_reconciliation.export` -- Export data to CSV
