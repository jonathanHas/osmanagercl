# Cash Reconciliation (Till Count)

The end-of-day till count. Whoever closes a till counts the drawer, says what stays
in as float, and the rest goes in a sealed bag for the manager to verify and lodge
(see [Cash Closed](./cash-closed.md) for the bag-to-bank side). Rewritten
2026-11-07; the Laravel page before that only mirrored the old PHP system's figures.

## Where it is

| Surface | Route | Who |
|---|---|---|
| Shop tablet | `/shop/till-count` (`shop.till-count`, Home tile **Till count**) | anyone with `cash_reconciliation.create` (employees, managers, admins) |
| Office | `/cash-reconciliation` (`cash-reconciliation.index`) | `cash_reconciliation.view`; same form plus history, legacy re-import and CSV export |

Both render the same Blade partial (`resources/views/shop/partials/till-count.blade.php`)
driven by one Alpine module (`resources/js/shop/till-count.js`, registered as
`shopTillCount`). The office page embeds it inside `<div class="shop shop--embed">` and
pushes the Shop bundle into the admin layout's `@stack('head')`, which sits before the
layout's own `@vite` so `shop.js` registers its Alpine data before `app.js` starts Alpine.

## The flow

1. **Till and close.** Chips show each till's state on the date: *Not counted*,
   *Counted*, *Open* (close it on the POS first) or *No close*. The page opens on the
   first till closed that day without a count. A till closed twice on one day lists
   both closes; each close is its own count (`closed_cash_id` = `CLOSEDCASH.MONEY`).
2. **Who is counting.** A people grid; the person taps their name and enters their PIN
   (employees with a PIN, on a trusted device) or password (everyone else). This does
   **not** sign anyone in or out: the identity is held in the session for
   `till_count.identity_minutes` (30) or until the count is saved, and recorded as
   `counted_by`. `created_by` stays the signed-in user (often the shared tablet).
   `App\Services\Shop\IdentityVerifier` does the check with the same per-user rate
   limit as switch-user; `App\Services\Cash\CountIdentity` holds the session token.
3. **Count the drawer.** Nine denominations, a stepper and a numpad each.
4. **Float.** Notes kept (defaults to last time), *Keep all the coins* on by default.
   The float carried in comes from the previous counted close of the same till
   (fallback: the old system's `money` row; failing that the counter types it).
5. **Card terminal total**, compared with the till's card tenders.
6. **Paid out / money added / note.** Supplier payments from the drawer (feed the P&L),
   change brought in, a note. A note is required when |cash variance| ≥ €20
   (`till_count.note_required_at`).
7. **Save.** The server recomputes everything and answers with the bag amount and the
   variance; the screen shows them with "seal the bag" and offers the other till.

## The arithmetic (`App\Services\Cash\TillCountService`)

```
counted      = Σ count × face value
bag          = counted − (note float + coin float)
day's cash   = counted + supplier payments − previous float − money added (+ legacy cashback)
expected     = POS cash (cash + cashrefund) + customer-invoice cash taken at this till
cash variance = day's cash − expected
card variance = terminal total − (POS card (magcard + magcardrefund) + customer-invoice card)
```

Tones: green under €5, amber under €20, red from €20 (`config/till_count.php`).
Voucher (`paperin`), `free`, `debt`, `debtpaid` and any other tender are shown as
information only: none of them is cash or card in the drawer.

## Data

`cash_reconciliations` (one row per till close, unique `closed_cash_id`):

- `till_id` → `tills.id` (**stable**; `App\Models\Till`, one row per POS `HOST`,
  registered on first sight). Before 2026-11-07 it was a position in an alphabetical
  list and drifted.
- `counted_by`, `counted_at`: who counted and when. `counted_at IS NULL` means no
  human count (scope `counted()`).
- `source`: `app` or `legacy` (imported from the old system).
- `previous_float`, `previous_reconciliation_id`: the float carried in, as used in
  the stored variance.
- The nine `cash_*` counts, `note_float`, `coin_float`, `card`, `money_added`,
  `total_cash_counted`, `pos_cash_total`, `pos_card_total`, `variance`.
- Legacy-only columns (`cash_back`, `cheque`, `debt*`, `free`, `voucher_used`) stay
  for imported rows and are shown read-only.

`cash_reconciliation_payments` rows are kept by id on re-save (their `created_at` is
what the P&L sums by; imported ones are dated to the till close).
`cash_reconciliation_notes`: one note per count, edited in place.

## Legacy import and repair

- `php artisan cash:import-legacy-reconciliations [--from --to --dry-run]`: backfill
  the old system's `money` / `payeePayments` / `dayNotes` through the service, oldest
  first. Existing rows are skipped.
- **Import from old system** button (office, managers): re-import one close,
  overwriting the row. For days before the cut-over only.
- `php artisan cash:recalculate-variances [--from --till --dry-run]`: re-resolve every
  counted row's previous float and recompute its variance with the one formula. Run
  once after the 2026-11-07 migrations (on dev it changed 162 of 350 rows and brought
  the median |variance| from €30.02 to €12.77).
- The lodgements page imports the old system's count for any close in the last
  `till_count.needs_count_days` (60) that has no row; it never creates empty rows.

## Permissions

`cash_reconciliation.view` and `.create` for admin, manager and employee (migration
`2026_11_07_000003`); `.export` for admin and manager. The Shop endpoints
(`till-count.identify`, `.forget`, `.store`) are on `config('shop.pin_session_routes')`.

## Tests

`tests/Unit/Cash/TillCountServiceTest`, `tests/Feature/Cash/*`,
`tests/Feature/Shop/ShopTillCountTest`, `tests/Feature/CashReconciliationTest`.
The POS fixture is `tests/Concerns/BuildsPosTillFixture`.
