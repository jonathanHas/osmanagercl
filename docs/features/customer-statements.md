# Customer Statements & Receivables

Statements of account for customers — an on-screen ledger, a printable A4 page, a branded
PDF, an emailed PDF and an aged-debtors report — plus the payment↔invoice matching that
feeds them.

Related: [Known Issues → Environment & Mail](../development/known-issues.md#environment--mail-issues)
covers the mail-configuration and `.env` traps hit while building this.

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

This is asserted in the test suite. The invariant only holds because no allocation
may exceed its invoice — see *Matching* below.

The statement shows this identity **as a sentence** ("€X outstanding on invoices, less
€Y received on account, leaves a balance of €Z") on screen, print and PDF, so the document
carries its own proof — a future change that breaks the identity produces a visibly wrong
sentence rather than a silently wrong number.

Unallocated credit is surfaced on the payments index (a header stat plus an
**Unmatched only** filter), as a Credit column on the aged debtors report and its
CSV, and on the customer page. `CustomerPaymentService::unappliedCreditTotals()`
computes it for a whole page in one grouped query.

### What each surface shows

Every surface is fed by the one `build()` context, and `CustomerStatementController::context()`
is a pass-through, so a new context key reaches all five at once.

| | screen | print / PDF | email |
|---|---|---|---|
| Per-invoice split under each payment | linked list | prose (`allocation_summary`) | — |
| Unapplied money marked on the payment | yes, + *match →* link | yes | — |
| **Payments on account** section | yes, + *match to invoices →* | yes | — |
| Reconciliation sentence | yes | yes | as balance-box rows |
| Paid per open invoice | yes, + which payments | yes (total only) | yes, when any is part-paid |
| `part-paid` tag | yes | — | — |
| Links into the matching flows | yes | never | never |

Payment events carry `allocations` (renderable rows), `allocated`, `unapplied` and
`allocation_summary` (the one-line form). **Invoice events carry the same four keys as empty
defaults**, so no view needs to branch on `kind` or defend against a missing key. `description`
is deliberately about the payment only — where the money went is data, not prose.

The allocation detail renders as a **sub-line inside the Description cell, not a sixth column**:
a column would take width from the only column that wraps on a 150mm PDF, be blank on every
invoice row, and wrap anyway on a three-way split. An in-cell sub-line is also protected by
`tr { page-break-inside: avoid }`, which a `colspan` detail row is not.

`credit_payments` lists the unapplied payments and exists because `unallocated_credit` spans
**all** payments up to `$to` while the ledger only covers `[$from, $to]` — on a ranged
statement the credit would otherwise be a figure with no supporting row anywhere on the
document. Its rows sum to `unallocated_credit` exactly, and a test asserts that.

Per-invoice payment history is loaded in `build()`, **not** `openInvoices()`, because
`debtors()` calls `openInvoices()` once per customer and would pay a query per debtor row.
A query-count test pins this.

### Voiding an invoice frees its payment

`CustomerInvoiceService::void()` deliberately leaves allocation rows in place so `unvoid()`
is a clean round trip. The payment side therefore excludes them:
`CustomerPayment::allocations()` filters to non-void invoices — **the exact mirror of
`CustomerInvoice::allocations()`**, which filters to non-void payments. Void a fully-paid
€100 invoice and the €100 returns to on-account credit on every surface at once; unvoid it
and the allocation comes back, losslessly.

Use `allAllocations()` where you need every row regardless of the invoice's state: the
payment page (which marks a freed row `invoice voided` and totals it as *Returned to credit
by voided invoices* via the `voided_allocated` accessor, so the table still explains its own
total) and `reallocate()` (which must clear stale rows, not merely stop counting them).

The two raw-SQL paths — `CustomerPayment::scopeWithUnallocatedOver()` and
`CustomerPaymentService::unappliedCreditTotals()` — carry the same filter by hand, since they
bypass the relation. If they ever drift from `allocations()`, the payments-index filter and
the debtors Credit column will disagree with the payment's own figure.

## Matching payments to invoices

`customer_payment_allocations` links a payment to an invoice with an amount. A
payment may be partly or wholly unallocated — that remainder is on-account credit.
Nothing is denormalized: invoice `outstanding_amount`, `paymentStatus()` and the
customer balance are all derived from these rows. **There is no "mark as paid" flag.**

Allocation is editable after the fact, from both directions:

| From | Route | Notes |
|---|---|---|
| A payment | `customer-payments.allocations.edit` / `.update` | Replaces the allocation set wholesale |
| A payment | `customer-payments.allocations.auto` | One-click oldest-first |
| An invoice | `customer-invoices.apply-credit` / `.store` | Draws on the customer's unapplied credit, oldest payment first |

The payment itself (amount, date, method, till) is **immutable** once banked —
deliberately not `customer-payments.edit`/`update`. Only the matching changes, and
it is stamped with `last_matched_at` / `last_matched_by`.

### Allocation invariants

`CustomerPaymentService::assertAllocationsValid()` is the single gate every write
path passes through. Allocations must not exceed the payment, must belong to the
payment's customer, must not target a void invoice, and **must not exceed the
invoice's own headroom**. Duplicate rows for one invoice are merged first, since two
rows would each pass a per-row check while jointly busting it.

Draft invoices *are* allocatable — they count toward `Customer::$total_invoiced`, so
excluding them would unbalance the statement. Only void invoices are blocked.

### Headroom, and why `outstanding_amount` isn't enough

`headroomFor($invoiceIds, $excludePaymentId, $lock)` computes `total - SUM(allocations
from non-void payments)` in one grouped join. Two reasons it can't just read the
`outstanding_amount` accessor:

- **Re-allocation.** An invoice paid in full by payment P reads as €0 outstanding.
  Editing P's own allocations must see the €100 back, or the invoice P already pays
  would be capped at zero and drop out of the form. Hence `$excludePaymentId`.
- **Void payments.** The accessor filters them via `CustomerInvoice::allocations()`'s
  `whereHas`; the join reproduces that with `whereNull('p.voided_at')`.

The result is deliberately **not** clamped at 0 — a negative means legacy
over-allocation that should be visible, not hidden.

### Concurrency

Two transactions each reading €50 of headroom and each writing €50 would put €100 on
a €50 invoice. Nothing portably gap-locks allocation rows, so **the invoice row is
the mutex**: every write locks `customer_invoices` before summing allocations.
Lock order is fixed everywhere — payment row first, then invoice rows ascending by
id (`headroomFor()` sorts the ids for this reason), so the paths can't deadlock.
SQLite compiles `lockForUpdate()` away and serialises writes anyway, so this only
bites on MySQL/InnoDB.

### Voided payments

`void()` keeps the allocation rows for audit; they simply stop counting. A voided
payment cannot be re-allocated — guarded in the controller for a friendly 422 and
again inside the transaction after `lockForUpdate()`, to close the race against a
concurrent void.

**There is no `unvoid` for payments** (unlike invoices). If one is ever added, its
stale allocation rows would come back to life and could now exceed their invoices,
because headroom is only validated at write time. Any future unvoid must re-run
`assertAllocationsValid()` before clearing `voided_at`.

### Selecting an invoice

Each row carries a checkbox: tick to settle that invoice, untick to clear it, so a
payment that matches an invoice can be applied without retyping the figure. A partly
applied row fills to the maximum rather than clearing. An invoice whose outstanding
amount equals the payment exactly is badged **exact match** and tinted.

How much a tick applies depends on `amountIsEditable`, which the two pages set
differently:

- **Edit allocations** — the banked amount is the ceiling, so a tick applies
  `min(outstanding, what's left of the payment)`. With nothing left the checkbox is
  disabled rather than silently doing nothing.
- **Record payment** — the amount is still an input, so a tick that doesn't fit grows
  the amount to cover it. This is the same thing *Auto-allocate* already does.

### Shared UI

The allocation table is one component, `<x-customer-payments.allocation-table>`, used
by both the record-payment and edit-allocations pages. Its Alpine state lives in
`customer-payments/_allocation-script.blade.php` as `customerAllocationCore(config)`,
which each page spreads into its own component. The component's contract (required
properties and methods) is documented at the top of both files. Alpine only calls the
root's `init()`, so the core's initialiser is `initAllocations()` and each page calls
it explicitly.

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

**The context must be unpacked with `Content(with:)`.** A Mailable exposes only its
*public properties* to the view; the single property here is `$ctx`, while the blade
reads `$customer`, `$aging`, `$open_invoices` directly. Without `with:` every send dies
on `Undefined variable $customer` — see [Known Issues](../development/known-issues.md#emailed-statement-fails-with-undefined-variable-customer).

```php
return new Content(view: 'emails.customer-statement', with: $this->ctx);
```

`Mail::fake()` records a queued mailable **without rendering it**, so `assertQueued`
passes over a broken view. Any change to the email blade or the context keys needs the
render test, not just an assertion that something was queued.

Note `->send()` on a `ShouldQueue` mailable *queues* it; use `->sendNow()` to build and
deliver synchronously (e.g. against the `array` transport when debugging).

```bash
php artisan customers:send-statements --dry-run      # list who would be emailed
php artisan customers:send-statements --customer=12  # single customer
```

Scheduled monthly on the 1st at 07:00 (`routes/console.php`). The debtors-page button
dispatches `SendCustomerStatementsJob`, which calls the same service method.

> ⚠️ With `MAIL_MAILER=log` nothing is delivered — messages go to
> `storage/logs/laravel.log`. The UI and the command both warn about this.
>
> **Commenting the variable out does not enable real sending.** `config/mail.php` is
> `env('MAIL_MAILER', 'log')`, so an absent value falls back to `log`. Set
> `MAIL_MAILER=smtp` explicitly, then `php artisan config:clear`.
> (`MAIL_SCHEME=null` with port 465 is fine — Laravel's
> `MailManager::createSmtpTransport()` picks `smtps` automatically for that port.)

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
rendering, the owing filter, the bulk run's skip rules, the credit identity holding
across a re-allocation, and a real render of the Mailable (which `Mail::fake()` would
not exercise).

`tests/Feature/CustomerPaymentAllocationTest.php` — the allocation invariants
(per-invoice over-allocation, void invoices, duplicate rows, wrong customer),
re-allocation including the headroom-excludes-this-payment case, apply-credit across
multiple payments, the unmatched/partial filters, and permission gating.

`database/factories/CustomerInvoiceFactory.php` and `CustomerPaymentFactory.php` back
both files.
