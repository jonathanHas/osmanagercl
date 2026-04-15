# Cash Closed (End-of-Day Cash Management)

## Overview

The Cash Closed system is the Laravel replacement for the legacy `sales_closed_cash.php` page. It handles the complete end-of-day cash workflow: counting the physical cash in the till, recording floats, tracking supplier payments, and reconciling against POS totals.

This feature is implemented as the **Cash Reconciliation** module and integrates with the **Cash Lodgements** module to provide a complete cash-to-bank pipeline.

## Related Documentation

- **[Cash Reconciliation (Technical)](./cash-reconciliation.md)** -- Models, repository, controller, API, database schema
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

## User Interface Layout

The page is designed for employees doing the end-of-day count. It has three columns:

### Navigation Bar
- **Prev/Next arrows** -- Jump to previous/next actual closed cash date for that till (skips days the till wasn't closed)
- **Till selector** -- Auto-submits on change. Only shows tills with activity in the last 6 months (cached 5 min). New tills appear automatically once they have a CLOSEDCASH record
- **Date picker** -- Manual date entry with Go button
- **Current date display** -- Full day name, date, and till name centred below

### Column 1: Cash Count
- **Notes section**: EUR50, EUR20, EUR10, EUR5 -- input count, shows euro total beside each
- **Coins section**: EUR2, EUR1, 50c, 20c, 10c -- same pattern
- **Subtotals**: Notes subtotal, Coins subtotal, Total Cash

### Column 2: Float, Card & Extras
- **Always visible**:
  - Note Float (manual entry, required daily)
  - Coin Float (auto-populated from coin total since all coins usually stay in till; user can override by typing; double-click to reset to auto)
  - Card inc cashback (required daily)
- **Collapsed "Cashback, Cheques, Debt & Other"** -- auto-opens if any field has data:
  - Cashback, Cheque, Money Added, Free, Voucher Used
  - Debt, Debt Paid Cash, Debt Paid Cheque, Debt Paid Card
- **Collapsed "Supplier Payments"** -- starts with 1 row, "Add another" button for more. Shows total on header when > 0
- **Collapsed "Notes"** -- blue dot indicator when a note exists

### Column 3: Summary (sticky on desktop)
- Total Cash Counted
- Previous Float (-EUR)
- Cashback (+EUR, shown only when > 0)
- Supplier Payments (+EUR, shown only when > 0)
- Money Added (-EUR, shown only when > 0)
- **Day's Cash Taking** (bold subtotal)
- POS Cash Total
- **Cash Variance** (coloured: green/red)
- Card Entered (card minus cashback)
- POS Card
- **Card Variance** (coloured: green/red)
- Cash + Card total
- Total Sales (POS)
- **Total Variance** (the bottom-line number, large and coloured)
- **Save button** (full-width)

### Role-Based UI
- **Lodgements button** in header: only visible to admin/manager roles
- The page itself is accessible to employees with `cash_reconciliation.view` permission

## Calculations

### Day's Cash Taking (matches legacy formula)
```
Day's Cash Taking = Total Cash + Cashback + Supplier Payments - Previous Float - Money Added
```

Supplier payments are added because cash was taken out of the till to pay suppliers -- the actual day's takings were higher than what remains in the till.

### Cash Variance
```
Cash Variance = Day's Cash Taking - POS Cash Total
```

### Card Variance
```
Card Variance = (Card Entered - Cashback) - POS Card Total
```

Useful for spotting when a card payment was accidentally entered as cash in the POS, or vice versa.

### Total Variance
```
Total Variance = Cash Variance + Card Variance
```

### Coin Float Auto-Calculation
Coins are usually not removed from the till. The coin float field auto-populates with the total of all coins counted (EUR2 + EUR1 + 50c + 20c + 10c). If the user manually edits the field, auto mode is disabled. Double-clicking the field resets to auto.

## Previous Float Logic

The previous day's float is fetched via AJAX on page load. It checks:
1. **Laravel `cash_reconciliations` table** first (most recent record before the selected date for the same till)
2. **Falls back to POS legacy data** (`CLOSEDCASH JOIN money`) if no Laravel record exists

This ensures correct float values even when records haven't been imported to Laravel yet.

## POS Summary Cards

Three cards at the top show read-only POS figures:
- **POS Cash** -- total cash payments from POS
- **POS Card** -- total card payments from POS (magcard)
- **Total Sales** -- combined

## Key Files

| File | Purpose |
|---|---|
| `resources/views/management/cash-reconciliation/index.blade.php` | Main view (Blade + Alpine.js) |
| `app/Http/Controllers/Management/CashReconciliationController.php` | Controller (index, store, getPreviousFloat, export) |
| `app/Repositories/CashReconciliationRepository.php` | Business logic (POS queries, float lookup, save, import) |
| `app/Models/CashReconciliation.php` | Model with calculation methods |
| `app/Models/CashReconciliationPayment.php` | Supplier payment records |
| `app/Models/CashReconciliationNote.php` | Daily notes |

## Till Management

- Tills are sourced from `CLOSEDCASH.HOST` in the POS database
- Only tills with activity in the last 6 months are shown (cached for 5 minutes)
- New tills appear automatically -- no manual configuration needed
- Navigation arrows skip to the next/previous actual closed date for the selected till
- Active tills as of Sept 2025: **OrgStore**, **Till 2**

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
# Delete existing Laravel records and reimport fresh
php artisan tinker --execute="
    \App\Models\CashReconciliationNote::query()->delete();
    \App\Models\CashReconciliationPayment::query()->delete();
    \App\Models\CashReconciliation::query()->delete();
"

# Import historical reconciliation data
php artisan cash:import-legacy-reconciliations --from=2024-01-01 --to=2025-12-31

# Import historical lodgement data
php artisan cash:import-legacy-lodgements --from=2024-01-01 --to=2025-12-31

# Auto-match lodgements to reconciliations
php artisan cash:auto-match-money-ids
```

Records also re-import automatically on first visit to `/cash-reconciliation` for any date -- the `getOrCreateReconciliation` method pulls fresh from legacy if no Laravel record exists.

## Bag Verification & Lodgement Workflow

### The Process
1. **Employee** closes the till daily, stores cash (minus float) in a sealed bag — one bag per till per day
2. **Manager** visits `/management/cash-lodgements` every few days
3. **Auto-creation**: On page load, the system auto-creates `CashReconciliation` records for any POS till closes that don't have one yet, importing legacy denomination data from the POS `money` table. This ensures all recent days appear without needing to visit the cash-reconciliation page first.
4. **Needs Cash Reconciliation** section shows days where the till was closed but no denomination data exists (no legacy money record and no manual count). Each row links to the cash-reconciliation page for that date.
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
