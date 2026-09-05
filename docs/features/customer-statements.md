# Customer Statements

Statements of account for customers: an on-screen ledger, a printable A4 page, a
branded PDF, an emailed PDF, and an aged-debtors report.

## Surfaces

| Surface | Route | View |
|---|---|---|
| Screen ledger | `customers.statement` | `customer-statements/show.blade.php` |
| Print (A4, light) | `customers.statement.print` | `customer-statements/print.blade.php` |
| PDF download | `customers.statement.pdf` | `customer-statements/_pdf.blade.php` |
| Email (PDF attached) | `customers.statement.email` (POST) | `emails/customer-statement.blade.php` |
| Aged debtors | `customers.debtors` | `customers/debtors.blade.php` |
| Debtors CSV | `customers.debtors.export` | — |
| Bulk send | `customers.debtors.send` (POST) / `customers:send-statements` | — |

All are inside the existing `permission:customer-invoices.manage` route group — no new permission.

## Where the numbers come from

`App\Services\CustomerStatementService::build()` returns one context array used by
**every** surface, so screen, paper, PDF and email can never disagree.

Source data is Laravel-side only: `customer_invoices` (non-void) and
`customer_payments` (non-void). **POS/uniCenta on-account debt is deliberately
not included** — `CUSTOMERS.CURDEBT`/`MAXDEBT` are unused by this app and there is
no link column between `customers.id` and POS `CUSTOMERS.ID`.

Key context keys: `events` (chronological ledger with running balance),
`opening_balance`, `closing_balance`, `open_invoices`, `aging`, `unallocated_credit`.

`customer-statements/_body.blade.php` is shared by the print and PDF views; each
parent supplies its own CSS for the same class names.

## Aging

Buckets: `current`, `d1_30`, `d31_60`, `d61_90`, `d90_plus` (see
`CustomerStatementService::BUCKETS`).

Aging keys off `CustomerInvoice::$effective_due_date`, **not** the raw `due_date`
column:

```
effective_due_date = due_date ?? issue_date + customer.payment_terms_days (default 30)
```

`due_date` is optional on the invoice form and was historically left blank on every
invoice, so without this fallback everything aged into one bucket. Derived dates are
marked with `*` on screen so nobody mistakes them for agreed terms.

`CustomerInvoiceService::createDraft()` now fills `due_date` from the customer's terms
when the form leaves it blank, so new invoices carry real dates and the fallback
gradually stops mattering. Set per-customer terms on the customer edit form.

### Unallocated credit

A payment with no allocation doesn't reduce any invoice's outstanding figure, so the
aged total alone can overstate the debt. `unallocated_credit` is reported separately
and the invariant is:

```
aging['total'] - unallocated_credit == closing_balance
```

This is asserted in the test suite.

## Balances without N+1

`Customer::$balance` runs two queries per customer, so list pages use aggregates instead:

```php
Customer::withSum(['invoices as invoiced_total' => fn ($q) => $q->where('status', '!=', 'void')], 'total')
    ->withSum('payments as paid_total', 'amount')
```

For *filtering* by balance use the `withBalanceOver()` scope on `Customer`. It uses
Eloquent-built correlated subqueries in `WHERE` rather than `HAVING` on a `withSum`
alias, because neither alias form is portable:

- MySQL (`ONLY_FULL_GROUP_BY`) rejects `GROUP BY customers.id` + `HAVING` here.
- SQLite rejects `HAVING` without `GROUP BY`.

The numeric threshold is inlined with `%F` rather than bound: **SQLite binds a PHP
float as text, and text compares greater than every number**, so a bound `> ?`
silently matched nothing.

## Emailing

Opt-in per customer via `customers.send_statements` (default false) plus a non-empty
email — see `Customer::scopeReceivesStatements()`. Sends stamp `statement_last_sent_at`.

`CustomerStatementMail` attaches the PDF via `Attachment::fromData()`. Sending uses
`Mail::queue()` (not `sendNow()`) so a failed SMTP call retries rather than aborting a
bulk run.

```bash
php artisan customers:send-statements --dry-run      # list who would be emailed
php artisan customers:send-statements --customer=12  # single customer
```

Scheduled monthly on the 1st at 07:00 (`routes/console.php`). The debtors-page button
dispatches `SendCustomerStatementsJob`, which calls the same service method.

> ⚠️ With `MAIL_MAILER=log` nothing is delivered — messages go to
> `storage/logs/laravel.log`. The UI and the command both warn about this.

## Branding

`App\Support\DocumentBranding::context()` supplies `$business`, `$certData`,
`$footerAddress`, `$contactLine`, `$businessLine2`, `$contactParts` from
`config('app.business')`, and is merged into the statement context.

It is a PHP class rather than a Blade partial because `@include` has its own variable
scope — values assigned inside an included partial are not visible to the including
view, so the duplicated `@php` preamble could not simply be extracted.

The organic cert image is base64-inlined because Dompdf cannot fetch remote assets.

## Tests

`tests/Feature/CustomerStatementTest.php` — ledger totals, void exclusion, opening
balance boundary, the terms fallback and its override, bucket sums, overpayment as
credit, unallocated-credit reconciliation, debtors totals, CSV export, page/PDF
rendering, the owing filter, and the bulk run's skip rules.
