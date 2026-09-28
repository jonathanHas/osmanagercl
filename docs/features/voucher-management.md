# Voucher Management

Gift-voucher system for the shop. Customers buy vouchers and redeem them against goods. Every voucher carries a unique, randomised, non-sequential barcode so it can be scanned, verified, and safely drawn down at the till — preventing forgery and double-spending.

## Overview

**Purpose**: Track gift vouchers with a server-side balance and a full audit trail, redeemable by scanning at the till.

**Lifecycle**:
1. A manager **generates** a batch of vouchers — each gets a random code and starts `inactive` (printed but not sold).
2. Codes are **printed** as CODE-128 barcode labels on the Zebra printer.
3. When sold, a manager **activates** a voucher with a starting balance (status → `active`).
4. Staff **redeem** it at the uniCenta till: scan the voucher label, pay with the till's **Voucher** tender, and the app deducts the amount within a minute (see [Till redemption](#till-redemption-unicenta)). A manual deduct on `/vouchers` or `/shop/vouchers` is the fallback. At €0 the status → `exhausted`.
5. An admin can **deactivate** a voucher (e.g. reported lost/stolen) and later **reactivate** it; the balance is preserved.

## Statuses

| Status | Meaning | Redeemable? |
|---|---|---|
| `inactive` | Generated/printed, not yet sold | No — must be activated first |
| `active` | Issued with a balance | Yes |
| `exhausted` | Balance fully spent (€0) | No |
| `deactivated` | Admin-disabled (balance preserved) | No — until reactivated |

## Data model

- **`vouchers`** — `code` (unique), `initial_value`, `current_balance`, `status`, `created_by`, timestamps, soft deletes. Model: `app/Models/Voucher.php`.
- **`vouchers.pos_product_id`** — the uniCenta `PRODUCTS.ID` of the voucher's hidden redemption product (nullable, unique; cycle 28).
- **`voucher_transactions`** — full audit log: `voucher_id`, `type` (`issue` / `deduct` / `deactivate` / `activate`), `source` (`manual` / `till`, default `manual`), `amount`, `balance_after`, `note` (optional admin reason; `Till #N` for till redemptions), `user_id` (null for till rows), timestamps. Model: `app/Models/VoucherTransaction.php`.
- **`voucher_till_redemptions`** — one row per voucher line seen on a till ticket: `pos_ticket_id` + `pos_product_id` (unique pair, the idempotency backstop), `voucher_code` snapshot, `ticket_number`, `ticket_type`, `sold_at`, `voucher_tender`, `ticket_total`, `amount_deducted`, `shortfall`, `status`, `voucher_id`, `voucher_transaction_id`, `note`, `reviewed_at`, `reviewed_by`. Model: `app/Models/VoucherTillRedemption.php`.

Every value-changing action (issue, deduct) and every admin status change (deactivate/reactivate) writes a transaction row, viewable per voucher at `/vouchers/{voucher}/transactions`.

## Code generation

- **Symbology**: CODE-128 (alphanumeric, in the scanner's supported-format list; auto-selected by `LabelService::generateBarcode()` for non-numeric strings).
- **Scheme**: `GV` + 10 characters from an unambiguous uppercase set (`23456789ABCDEFGHJKLMNPQRSTUVWXYZ`, excludes `0/O/1/I`). Example: `GV7KQFM2RA9T`.
- `Voucher::generateUniqueCode()` uses a CSPRNG (`random_int`), checks for collisions and retries; the `code` unique index is the hard backstop. Codes are fixed length, so printed barcodes are a constant width.

## Till redemption (uniCenta)

Added in cycle 28 (2026-09-27). The cashier redeems a voucher inside the normal till sale, with no second screen.

### Cashier steps

1. Ring up the goods as normal.
2. **Scan the voucher label.** A €0.00 line appears, named e.g. `Gift Voucher GV7KQFM2RA9T [bal €42.50]`. Read the balance from that line.
3. **Pay → Voucher tab** → enter the amount to take from the voucher (at most the balance shown) → take any remainder by cash/card as usual → finish the sale.
4. The app deducts that amount from the voucher within a minute (or immediately on the next voucher scan in the app), records `Till #<ticket>` in its history, and renames the till product to the new balance.

Wording on the till line (and on the printed receipt, which shows the balance *before* the sale) comes from `config/vouchers.php` (`pos_name_format`, `pos_balance_text`): `[bal €42.50]`, `[€0.00 used up]`, `[not active]`, `[deactivated]`. The owner can change it without code.

### Why a product line

uniCenta's Voucher tender is recorded as `PAYMENTS.PAYMENT = 'paperin'` with the amount in `TOTAL` (`TENDERED` is 0). `TRANSID` is a random 12-digit id the till generates for every payment type, so nothing on the payment says *which* voucher was used. Each voucher therefore has a hidden POS product whose barcode is the voucher code; its line on the ticket identifies the voucher.

### The POS product

`App\Services\VoucherPosProductService`:
- One product per voucher: `ID` uuid, `CODE` = `REFERENCE` = voucher code, `PRICESELL 0`, `PRICEBUY 0`, `TAXCAT '000'`, `ISSERVICE 1` (no stock movement), `NAME` carrying the balance.
- Category **"Gift Voucher Redemption"** (child of `033` Seasonal, `CATSHOWNAME 0`), created on first use. Configurable via `VOUCHER_POS_CATEGORY` / `VOUCHER_POS_CATEGORY_PARENT`.
- **Never** a `PRODUCTS_CAT` row (so no till button), no `STOCKCURRENT` or metadata rows.
- Created when vouchers are generated or an unknown code is activated; renamed after every activate / deduct / deactivate / reactivate and every till redemption. uniCenta reads a scanned product from the database on each scan, so a rename shows on the next scan.
- Every POS write is wrapped: if the POS is down, generation/activation/deduction still succeed, a warning is logged, and generation flashes "N voucher products could not be created on the till; run vouchers:sync-pos-products".
- If `pos_product_id` is missing but a product with the voucher's `CODE` exists, it is linked rather than duplicated.

Backfill / repair: `php artisan vouchers:sync-pos-products [--dry-run] [--all]` — by default every voucher without a product (all statuses: a printed-but-unsold label must scan, or the till says "product not found"); `--all` re-syncs every voucher's name to its current balance.

### The sync

`App\Services\VoucherTillSyncService`, run by `php artisan vouchers:sync-till [--since=ISO]` **every minute** (`routes/console.php`; production must run `schedule:run`) and on every voucher lookup (throttled to once per 5 s; off switch `vouchers.sync.on_lookup`). A POS outage during lookup is logged and ignored.

- Reads tickets (sales and refunds) after a watermark (latest recorded `sold_at` minus 15 min overlap, floored at 24 h back) that carry a product in the voucher category, 50 per run.
- Per ticket: voucher lines in `LINE` order (a double scan collapses to one), `voucher_tender` = sum of the receipt's `paperin` payments, `ticket_total` = Σ price × units × (1 + tax rate).
- Owner rules (2026-09-27): the tender is deducted from the voucher(s) in line order; tender above the balance deducts to zero and is flagged `partial` with the shortfall; a voucher line without a Voucher tender deducts nothing (`no_tender`); refunds are recorded, never reversed.
- Money rules are the manual deduct's: 2 dp, never below zero, `exhausted` at exactly 0, one `voucher_transactions` row (`source = till`, note `Till #N`) per deduction, under `lockForUpdate()`.
- Idempotent: an already-recorded (ticket, product) pair is skipped; the unique index catches a race.

### Exception statuses (`/vouchers/exceptions`)

| Status | Meaning | Balance |
|---|---|---|
| `applied` | Tender deducted in full (not an exception) | reduced |
| `partial` | Tender exceeded the balance; deducted to zero. Check the shortfall (and `ticket_total` — a barcode typed into the amount field shows as a huge tender) | €0, exhausted |
| `no_tender` | Voucher scanned but paid by cash/card | untouched |
| `inactive` | Voucher was inactive, deactivated or exhausted | untouched |
| `unknown` | A product in the voucher category with no voucher in the app | — |
| `refund` | Refund ticket carrying a voucher line; re-credit by hand if needed | untouched |

Managers (`vouchers.manage`) see unreviewed exceptions at **Vouchers → Till exceptions** (also a "Till exceptions (N)" link in the `/vouchers` header), fix anything on the voucher itself, and **Mark reviewed** with an optional note. "Show reviewed" lists everything.

### Manual deduct is the fallback

Both `/vouchers` and `/shop/vouchers` now lead with the balance and history (till rows read "Redeemed at till · Till #N"); the deduct input / number pad sits behind a **Manual deduct** button for when the till could not take the voucher.

### Follow-ups (not done)

- Auto-filling cash reconciliation `voucher_used` from `paperin`.
- `TillTransactionRepository::formatReceipt` reads only the first payment of a receipt.
- Auto-activating vouchers at the till (a "Voucher 50 Euro" sale line plus a blank GV label).
- GV products show in the app's product search and as €0 lines in `SalesImportService`; exclude the voucher category if noisy.

## Till screen (`/vouchers`)

A minimal, tablet/phone-friendly screen (`resources/views/vouchers/index.blade.php`) that reuses the shared camera/barcode scanner (`resources/js/barcode-scanner.js`, `window.BarcodeScanner`) — the same one used by `/stocking`. It supports live camera scanning (HTTPS), a photo-decode fallback (works over HTTP), and manual entry.

On scan, the screen looks up the code and shows one of:
- **Activate** (unknown or `inactive`) — enter a starting balance *(managers/admins only)*.
- **Balance + history** (`active`) — the balance, the voucher's history, and a **Manual deduct** button that reveals the deduct field (and "use full balance").
- **Deactivated** / **Exhausted** — an informational message (exhausted also shows the history, so staff can see the till redemption that emptied it).

## Shop mode screen (`/shop/vouchers`)

The shop-floor version of the same job, for staff on the counter tablet
(`resources/views/shop/vouchers.blade.php`, `resources/js/shop/vouchers.js`,
`App\Http\Controllers\Shop\VouchersController`). It calls the **same three
endpoints** — nothing about redemption is duplicated — and adds no rules of its own.

What it shows: the balance as a big number with a status pill, an "Issued … · code"
line, the voucher's recent history, and — behind a **Manual deduct** button — a number
pad for the amount with a running "Remaining after". "Use full balance" fills the pad. A manager who scans an unknown
or inactive code gets the pad labelled "Starting balance €" and an Activate button;
an employee gets "Voucher not active. Please ask a manager." and no pad, because the
view is rendered without an activate URL and without the activate markup. The route
requires `vouchers.redeem`; `vouchers.activate` still enforces `vouchers.manage`
server side regardless of what the page offers.

After a successful deduct or activate the screen re-runs the lookup, so the balance,
status and history on screen are what the server recorded rather than what the client
predicted — and a 422 (another till spent the voucher first) replaces the balance with
the true one and disables the button without a rescan.

`vouchers.lookup` gained two keys for this screen, **additively**, so the office till
screen is unaffected:
- `issued_at` — ISO timestamp of the earliest `issue` transaction, or null.
- `history` — up to 20 transactions, newest first, each `{ type, label, amount,
  balance_after, user, at }`. `amount` is signed for display (issue positive, deduct
  negative, status changes zero) and `user` is the staff name or "Office".
- Cycle 28 added `source` (`manual` / `till`) and `ticket_number` to each history row;
  till rows have label "Redeemed at till" and `user` "Till #N".

### Double-spend protection

`VoucherController::deduct()` (and `activate()`) wrap the read-modify-write in a database transaction with `lockForUpdate()` row locking. The balance is re-read under the lock and the deduct is rejected if it exceeds the remaining balance — so two concurrent tills cannot each spend the last of a voucher. At €0 the status flips to `exhausted`. Deactivated/inactive/exhausted vouchers are rejected.

## Zebra label printing

Vouchers print on the shop's Zebra label printer (the same one used by `/labels`) on the **small label (56×30mm / 673×366 dots)**.

- `Voucher::toZplLabel()` builds one `^XA…^XZ` ZPL block: a "GIFT VOUCHER" title, a CODE-128 barcode (`^BC`, with the human-readable code printed under the bars).
- After generating, the user lands on a **preview + print** page (`resources/views/vouchers/print.blade.php`) that renders each label via the in-browser `ZplPreview` emulator (`resources/js/zpl-preview.js`) and has a **Print to Zebra** button.
- Printing goes through `ZebraPrintService::sendRaw()` (`app/Services/ZebraPrintService.php`) — ZPL → temp file → a `timeout`-guarded `lp … -o raw` (CUPS raw print over IPP), with the printer from `config('services.zebra.*')`. The same service backs every other Zebra print path in the app; do not shell out to `lp` directly. 📖 [Label Translation System Documentation](./label-translation-system.md)
- Voucher ZPL carries no `^PQ` (each code is unique, so there is nothing to repeat), which means `ZebraLabel::setZplQuantity()` is not involved — one `^XA…^XZ` block per voucher is concatenated into a single job.
- If labels do not appear, check the spool **on the printer host**, not the local machine: the **Printer Queue** card on `/labels/zebra`, or `lpstat -h {host}:631 -o`.

## Admin: deactivate / reactivate

On the **All Vouchers** page (`/vouchers/list`), an admin-only **Edit** button (shown for `active`/`deactivated` rows) opens a modal to deactivate or reactivate the voucher with an optional reason/note. The action is logged to the voucher's transaction history (with the note). Deactivating preserves the balance and blocks redemption; reactivating restores it to `active`.

## Access control

Three tiers, enforced by route middleware **and** in the UI:

| Capability | Permission / role | Employee | Manager | Admin |
|---|---|:--:|:--:|:--:|
| Scan + view balance + **redeem** (deduct) | `vouchers.redeem` | ✅ | ✅ | ✅ |
| **Activate**, **generate**, **print**, list, transactions | `vouchers.manage` | ❌ | ✅ | ✅ |
| **Deactivate** / **reactivate** | `vouchers.manage` + `role:admin` | ❌ | ❌ | ✅ |

Employees get a cut-down till screen: they can ring up a voucher payment but cannot create value (activate), generate, or manage. Scanning an unknown/inactive voucher as an employee shows "Voucher not active — please ask a manager". Permissions are created and granted by migration `2026_09_26_120000_add_customer_requests_and_voucher_permissions.php`, so `php artisan migrate --force` on deploy is all that is needed; they are also listed in `database/seeders/RolesAndPermissionsSeeder.php` (module *Voucher Management*), which is for **fresh installs only** and must not be run against the live database. Before that migration existed neither `vouchers.redeem` nor `vouchers.manage` was present on production and the Vouchers tile never appeared (2026-09-26). In Blade they are checked with `auth()->user()->can(...)` (not `@can`).

## Routes

| Route | Verb / path | Access |
|---|---|---|
| `vouchers.index` | GET `/vouchers` | redeem |
| `shop.vouchers` | GET `/shop/vouchers` | redeem |
| `vouchers.lookup` | POST `/vouchers/lookup` | redeem |
| `vouchers.deduct` | POST `/vouchers/deduct` | redeem |
| `vouchers.activate` | POST `/vouchers/activate` | manage |
| `vouchers.generate` / `.generate.store` | GET/POST `/vouchers/generate` | manage |
| `vouchers.print` / `.print.send` | GET/POST `/vouchers/print` | manage |
| `vouchers.list` | GET `/vouchers/list` | manage |
| `vouchers.transactions` | GET `/vouchers/{voucher}/transactions` | manage |
| `vouchers.exceptions` | GET `/vouchers/exceptions` (`?all=1` includes reviewed) | manage |
| `vouchers.exceptions.reviewed` | POST `/vouchers/exceptions/{redemption}/reviewed` | manage |
| `vouchers.deactivate` / `.reactivate` | POST `/vouchers/{voucher}/...` | admin |

## Key files

- **Controller**: `app/Http/Controllers/VoucherController.php`, `app/Http/Controllers/Shop/VouchersController.php`
- **Models**: `app/Models/Voucher.php`, `app/Models/VoucherTransaction.php`, `app/Models/VoucherTillRedemption.php`
- **Till redemption**: `app/Services/VoucherPosProductService.php`, `app/Services/VoucherTillSyncService.php`, `app/Console/Commands/SyncVoucherPosProducts.php`, `app/Console/Commands/SyncVoucherTillRedemptions.php`, `config/vouchers.php`
- **Migrations**: `database/migrations/2026_06_24_120000_create_vouchers_table.php`, `..._120001_create_voucher_transactions_table.php`, `2026_06_25_120000_add_note_to_voucher_transactions_table.php`, `2026_09_28_100000_create_voucher_till_redemptions_table.php`, `..._100001_add_source_to_voucher_transactions_table.php`, `..._100002_add_pos_product_id_to_vouchers_table.php`
- **Views**: `resources/views/vouchers/{index,list,generate,print,transactions,exceptions}.blade.php`
- **Permissions/nav**: `database/seeders/RolesAndPermissionsSeeder.php`, `resources/views/layouts/admin.blade.php`
- **Reused**: `resources/js/barcode-scanner.js`, `resources/js/zpl-preview.js`, `app/Services/LabelService.php`, `config/services.php` (`zebra`)
