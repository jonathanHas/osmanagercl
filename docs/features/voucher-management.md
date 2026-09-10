# Voucher Management

Gift-voucher system for the shop. Customers buy vouchers and redeem them against goods. Every voucher carries a unique, randomised, non-sequential barcode so it can be scanned, verified, and safely drawn down at the till — preventing forgery and double-spending.

## Overview

**Purpose**: Track gift vouchers with a server-side balance and a full audit trail, redeemable by scanning at the till.

**Lifecycle**:
1. A manager **generates** a batch of vouchers — each gets a random code and starts `inactive` (printed but not sold).
2. Codes are **printed** as CODE-128 barcode labels on the Zebra printer.
3. When sold, a manager **activates** a voucher with a starting balance (status → `active`).
4. Staff **redeem** (deduct) against the balance at the till until it reaches €0 (status → `exhausted`).
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
- **`voucher_transactions`** — full audit log: `voucher_id`, `type` (`issue` / `deduct` / `deactivate` / `activate`), `amount`, `balance_after`, `note` (optional admin reason), `user_id`, timestamps. Model: `app/Models/VoucherTransaction.php`.

Every value-changing action (issue, deduct) and every admin status change (deactivate/reactivate) writes a transaction row, viewable per voucher at `/vouchers/{voucher}/transactions`.

## Code generation

- **Symbology**: CODE-128 (alphanumeric, in the scanner's supported-format list; auto-selected by `LabelService::generateBarcode()` for non-numeric strings).
- **Scheme**: `GV` + 10 characters from an unambiguous uppercase set (`23456789ABCDEFGHJKLMNPQRSTUVWXYZ`, excludes `0/O/1/I`). Example: `GV7KQFM2RA9T`.
- `Voucher::generateUniqueCode()` uses a CSPRNG (`random_int`), checks for collisions and retries; the `code` unique index is the hard backstop. Codes are fixed length, so printed barcodes are a constant width.

## Till screen (`/vouchers`)

A minimal, tablet/phone-friendly screen (`resources/views/vouchers/index.blade.php`) that reuses the shared camera/barcode scanner (`resources/js/barcode-scanner.js`, `window.BarcodeScanner`) — the same one used by `/stocking`. It supports live camera scanning (HTTPS), a photo-decode fallback (works over HTTP), and manual entry.

On scan, the screen looks up the code and shows one of:
- **Activate** (unknown or `inactive`) — enter a starting balance *(managers/admins only)*.
- **Balance + Deduct** (`active`) — shows the balance with a deduct field (and "use full balance").
- **Deactivated** / **Exhausted** — an informational message.

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

Employees get a cut-down till screen: they can ring up a voucher payment but cannot create value (activate), generate, or manage. Scanning an unknown/inactive voucher as an employee shows "Voucher not active — please ask a manager". Permissions are defined in `database/seeders/RolesAndPermissionsSeeder.php` (module *Voucher Management*); in Blade they are checked with `auth()->user()->can(...)` (not `@can`).

## Routes

| Route | Verb / path | Access |
|---|---|---|
| `vouchers.index` | GET `/vouchers` | redeem |
| `vouchers.lookup` | POST `/vouchers/lookup` | redeem |
| `vouchers.deduct` | POST `/vouchers/deduct` | redeem |
| `vouchers.activate` | POST `/vouchers/activate` | manage |
| `vouchers.generate` / `.generate.store` | GET/POST `/vouchers/generate` | manage |
| `vouchers.print` / `.print.send` | GET/POST `/vouchers/print` | manage |
| `vouchers.list` | GET `/vouchers/list` | manage |
| `vouchers.transactions` | GET `/vouchers/{voucher}/transactions` | manage |
| `vouchers.deactivate` / `.reactivate` | POST `/vouchers/{voucher}/...` | admin |

## Key files

- **Controller**: `app/Http/Controllers/VoucherController.php`
- **Models**: `app/Models/Voucher.php`, `app/Models/VoucherTransaction.php`
- **Migrations**: `database/migrations/2026_06_24_120000_create_vouchers_table.php`, `..._120001_create_voucher_transactions_table.php`, `2026_06_25_120000_add_note_to_voucher_transactions_table.php`
- **Views**: `resources/views/vouchers/{index,list,generate,print,transactions}.blade.php`
- **Permissions/nav**: `database/seeders/RolesAndPermissionsSeeder.php`, `resources/views/layouts/admin.blade.php`
- **Reused**: `resources/js/barcode-scanner.js`, `resources/js/zpl-preview.js`, `app/Services/LabelService.php`, `config/services.php` (`zebra`)
