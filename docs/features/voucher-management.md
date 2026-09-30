# Voucher Management

Gift-voucher system for the shop. Customers buy vouchers and redeem them against goods. Every voucher carries a unique, randomised, non-sequential barcode so it can be scanned, verified, and safely drawn down at the till — preventing forgery and double-spending.

## Overview

**Purpose**: Track gift vouchers with a server-side balance and a full audit trail, redeemable by scanning at the till.

**Lifecycle**:
1. A manager **generates** a batch of vouchers **with a value** (e.g. 10 × €20). Each gets a random code, starts `inactive` (unsold) with that `face_value`, and its hidden till product is **priced at the value** (`[for sale €20.00]`).
2. Codes are **printed** as CODE-128 barcode labels on the Zebra printer (the label does not show the value).
3. **Sold at the till**: the cashier scans the label as an item, the till charges its value, and the next till check (within a minute) **activates** the voucher with that value (status → `active`) and drops the till price to €0.00 (see [Selling a voucher at the till](#selling-a-voucher-at-the-till)). Anything unusual activates nothing and is flagged. A manager can still activate by hand on `/vouchers` (the fallback, and the only way for vouchers generated before 2026-09-30, which have no value).
4. Staff **redeem** it at the uniCenta till: scan the voucher label, pay with the till's **Voucher** tender, and the app deducts the amount within a minute (see [Till redemption](#till-redemption-unicenta)). A manual deduct on `/vouchers` or `/shop/vouchers` is the fallback. At €0 the status → `exhausted`.
5. An admin can **deactivate** a voucher (e.g. reported lost/stolen) and later **reactivate** it; the balance is preserved.

## Statuses

| Status | Meaning | Redeemable? |
|---|---|---|
| `inactive` | Generated/printed, not yet sold. With a `face_value` it is **for sale**: its till product charges that value and the sale activates it | No — must be sold (or activated by a manager) first |
| `active` | Issued with a balance | Yes |
| `exhausted` | Balance fully spent (€0) | No |
| `deactivated` | Admin-disabled (balance preserved) | No — until reactivated |
| *deleted* | Not a status: an admin soft-deleted the voucher ([Admin changeover tools](#admin-changeover-tools)). Its status and balance are kept but it is hidden from the list, the activity log and the totals, cannot be looked up, activated or redeemed, and its code stays reserved. Restorable | No |

## Data model

- **`vouchers`** — `code` (unique), `initial_value`, `current_balance`, `status`, `created_by`, timestamps, soft deletes. Model: `app/Models/Voucher.php`.
- **`vouchers.pos_product_id`** — the uniCenta `PRODUCTS.ID` of the voucher's hidden redemption product (nullable, unique; cycle 28).
- **`vouchers.face_value`** — the value an unsold voucher is sold for at the till (nullable; vouchers cycle 3). Set by generation; NULL for vouchers generated before it. `initial_value` stays NULL until the voucher is activated.
- **`voucher_transactions`** — full audit log: `voucher_id`, `type` (`issue` / `deduct` / `deactivate` / `activate`), `source` (`manual` / `till`, default `manual`), `amount`, `balance_after`, `note` (optional admin reason; `Till #N` for till redemptions), `user_id` (null for till rows), timestamps. Model: `app/Models/VoucherTransaction.php`.
- **`voucher_till_redemptions`** — one row per voucher line seen on a till ticket: `pos_ticket_id` + `pos_product_id` (unique pair, the idempotency backstop), `voucher_code` snapshot, `ticket_number`, `ticket_type`, `sold_at`, `voucher_tender`, `ticket_total`, `sale_amount` (what the till charged for the voucher's own line on a sale; NULL on redemptions; vouchers cycle 3), `amount_deducted`, `shortfall`, `status`, `voucher_id`, `voucher_transaction_id`, `note`, `reviewed_at`, `reviewed_by`. Model: `app/Models/VoucherTillRedemption.php`.

Every value-changing action (issue, deduct) and every admin status change (deactivate/reactivate) writes a transaction row, viewable per voucher at `/vouchers/{voucher}/transactions`.

## Code generation

- **Symbology**: CODE-128 (alphanumeric, in the scanner's supported-format list; auto-selected by `LabelService::generateBarcode()` for non-numeric strings).
- **Scheme**: `GV` + 10 characters from an unambiguous uppercase set (`23456789ABCDEFGHJKLMNPQRSTUVWXYZ`, excludes `0/O/1/I`). Example: `GV7KQFM2RA9T`.
- `Voucher::generateUniqueCode()` uses a CSPRNG (`random_int`), checks for collisions and retries; the `code` unique index is the hard backstop. Codes are fixed length, so printed barcodes are a constant width.

## Selling a voucher at the till

Added in vouchers cycle 3 (2026-09-30). A voucher generated with a value is sold like any product, and the sale activates it. No manager step and no separate "Voucher 20 Euro" product.

### Cashier steps

1. Take a label from the batch of the value the customer wants.
2. **Scan the label as an item.** The till shows a line `Gift Voucher GV… [for sale €20.00]` charging €20.00.
3. **Scan each voucher itself. Never use the quantity key**: three €20 vouchers are three scans of three labels, not one label × 3.
4. Take payment as normal (cash, card, or even another voucher's Voucher tender).
5. Within a minute the app activates the voucher with €20.00, and the same label then scans as a €0.00 line `[bal €20.00]` for redemption.

Labels look the same whatever their value (the value is not printed), so **keep batches of different values apart**. The price on the till line at scan is the only check.

### What activates and what is flagged

The till check (`vouchers:sync-till`) treats a voucher-category line that carries a price as a **sale**; a €0.00 line is a redemption as before. A sale activates the voucher (`activated`: `initial_value` = balance = `face_value`, an `issue` transaction with `source = till` and note `Till #N`, shown as "Sold at till") only when everything matches. Otherwise nothing is activated and the line goes to `/vouchers/exceptions`:

| Status | When | What to do |
|---|---|---|
| `sale_flagged` | The voucher was already active, exhausted or deactivated ("Charged €X but the voucher was already … Nothing was added.") | Sold twice, or scanned for sale instead of redemption; refund or correct by hand |
| `sale_flagged` | The receipt was paid with the **Free** tender | Activate by hand if it was a deliberate gift |
| `sale_flagged` | **Quantity** other than 1 (a quantity key, or the same label scanned twice) | Each extra voucher is unactivated; activate the right labels by hand |
| `sale_flagged` | The amount charged differs from the voucher's value (a price edited at the till), or the voucher has no value | Activate by hand with the right amount |
| `refund` | A refund ticket carrying a voucher sale ("Refund of a voucher sale (€X)…") | **Deactivate** the voucher by hand if it was handed back |
| `unknown` | A priced product in the voucher category with no voucher in the app | Investigate |

Each row carries `sale_amount`, shown in the "Sale" column on the exceptions page and as "charged €X" on the activity screen.

**The one-minute window**: until the next till check the sold voucher's product still carries its price. A redemption attempted in that minute charges the value again and is flagged (`already active`). On production the scheduler checks every minute; on dev (no cron) run `php artisan vouchers:sync-till`.

**Manual activation** stays for managers and admins (`vouchers.manage`): scanning an unsold voucher on `/vouchers` or `/shop/vouchers` shows "Value €20.00. Normally sold at the till…" with the starting balance pre-filled. Employees see "Not sold yet (€20.00). Sell it at the till: scan the label as an item." A manual activation also drops the till price to €0.00.

**Products `6012-6014`** ("Voucher 10/20/50 Euro") still exist and still work the old way (sell the product, a manager activates a label by hand) during the changeover. A voucher sold that way is never scanned, so it stays unsold until activated by hand.

**Finance** is unchanged: the voucher product is `TAXCAT 000`, so its sale counts as 0% VAT revenue exactly as a "Voucher 20 Euro" sale does, and redemption is still `paperin`. Sales reports by POS category show these sales under "Gift Voucher Redemption" (misleading name; renaming it is a follow-up).

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
- **Price**: `PRICESELL` = the face value while the voucher is for sale (inactive with a value), €0.00 otherwise. `sync()` corrects a drifted price as well as the name; `vouchers:sync-pos-products --all` does it for every voucher.
- Created when vouchers are generated or an unknown code is activated; renamed (and repriced) after every activate / deduct / deactivate / reactivate and every till redemption or sale. uniCenta reads a scanned product from the database on each scan, so a rename shows on the next scan.
- Every POS write is wrapped: if the POS is down, generation/activation/deduction still succeed, a warning is logged, and generation flashes "N voucher products could not be created on the till; run vouchers:sync-pos-products".
- If `pos_product_id` is missing but a product with the voucher's `CODE` exists, it is linked rather than duplicated.

Backfill / repair: `php artisan vouchers:sync-pos-products [--dry-run] [--all]` — by default every voucher without a product (all statuses: a printed-but-unsold label must scan, or the till says "product not found"); `--all` re-syncs every voucher's name to its current balance.

### The sync

`App\Services\VoucherTillSyncService`, run by `php artisan vouchers:sync-till [--since=ISO] [--scheduled]` **every minute** (`routes/console.php` schedules it with `--scheduled`; production runs `schedule:run` from `/etc/cron.d/osmanager`), on every voucher lookup and by the activity screen while it is open (both throttled together to once per 5 s; off switch `vouchers.sync.on_lookup`). A POS outage during lookup is logged and ignored.

- Every run records a **heartbeat** (`App\Services\VoucherSyncHeartbeat`, see [Activity screen](#activity-screen-vouchersactivity)): when, which source (`schedule`, `command`, `lookup`, `activity`), ok or failed, and the counts.
- The counts table has an **`errors`** column: tickets that failed inside the run (logged with `Log::error`, left unrecorded, retried on the next run inside the overlap window). A run with errors still counts as ok (the till was read).

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
| `refund` | Refund ticket carrying a voucher line; re-credit by hand if needed (for a refunded *sale*, deactivate the voucher) | untouched |
| `activated` | A till sale activated the voucher (not an exception) | set to the face value |
| `sale_flagged` | A voucher sale that activated nothing; see [What activates and what is flagged](#what-activates-and-what-is-flagged) | untouched |

Managers (`vouchers.manage`) see unreviewed exceptions at **Vouchers → Till exceptions** (also a "Till exceptions (N)" link in the `/vouchers` header), fix anything on the voucher itself, and **Mark reviewed** with an optional note. "Show reviewed" lists everything.

### Manual deduct is the fallback

Both `/vouchers` and `/shop/vouchers` now lead with the balance and history (till rows read "Redeemed at till · Till #N"); the deduct input / number pad sits behind a **Manual deduct** button for when the till could not take the voucher.

### Follow-ups (not done)

- Auto-filling cash reconciliation `voucher_used` from `paperin`.
- `TillTransactionRepository::formatReceipt` reads only the first payment of a receipt.
- Auto-activating vouchers at the till (a "Voucher 50 Euro" sale line plus a blank GV label).
- GV products show in the app's product search and as €0 lines in `SalesImportService`; exclude the voucher category if noisy.

## Activity screen (`/vouchers/activity`)

Added in vouchers cycle 2 (2026-09-29). One office page showing what is happening to gift vouchers as it happens, and whether the till check is healthy. **Managers and admins** (`vouchers.manage`); linked from the sidebar ("Voucher activity"), the `/vouchers` header and the exceptions page. It watches only: corrections stay on the voucher's own pages and on `/vouchers/exceptions`.

**Live updates**: the page polls `GET /vouchers/activity/feed` every `vouchers.activity.poll_seconds` (5 s), like the coffee display (no websockets). It skips a tick while paused, while the browser tab is hidden, or while a request is still out, and fetches at once when the tab becomes visible again. New rows are highlighted for 10 s. "Live" / "Paused" / "Offline" in the title: Offline means the page's own request to the server failed; it clears on the next success and keeps the rows it had.

**The page runs the till check itself** while open: each feed request calls `syncIfDue('activity')`, sharing the lookup screens' 5-second throttle, so several managers watching still read the till at most once per 5 s. The first page render does not, so a slow till database never delays the page.

**Health banner**:

| State | Shown when | What to do |
|---|---|---|
| green "Till checked N s ago" | the last check succeeded, the scheduler ran recently, no errors, every voucher can be scanned | nothing |
| red | the last till check failed: "The till database could not be read: …" | check the POS database/host is up; the error is the database's |
| amber, "The scheduler has not run the till check since …" / "…has never run…" | no `--scheduled` run for `vouchers.activity.scheduler_stale_seconds` (180 s) | on production check `/etc/cron.d/osmanager` and the cron journal (see the production guide). While the page is open it checks the till itself, so balances still update; when it is closed, only lookups do. On a dev box with no cron this is always shown |
| amber, "N till ticket(s) could not be applied on the last check" | the last run had `errors > 0` | read the application log (`Voucher till sync failed for ticket`) |
| amber, "N voucher(s) have no till product…" | active or inactive vouchers with no `pos_product_id` | run `php artisan vouchers:sync-pos-products` |

Under the banner: when the last check ran and from where, and when the scheduler last ran it. The scheduler time comes only from runs with `--scheduled`, so the page's own checks can never hide a dead scheduler.

**Tiles**: Redeemed today (split till / manual), Issued today, Outstanding balance (sum over active vouchers, with the count), Unreviewed exceptions (links to `/vouchers/exceptions`, red above zero).

**Filters**: Today / 7 days (default) / 30 days, and a code search (substring, 300 ms debounce).

**The log** (newest first, at most `vouchers.activity.limit` = 100): every `voucher_transactions` row, plus the till redemptions that never made a transaction (`no_tender`, `inactive`, `unknown`, `refund`). `applied` and `partial` redemptions always have a transaction, so they appear once. Columns: When (for till rows a second line "till HH:mm", the till's own time), Event (label + pill: till purple, manual blue, issue green, status change grey, exception amber, `partial` red with "short €x"), Voucher (linked unless the voucher was deleted), Amount (signed), Balance after, By (`Till #N`, the staff name, or "Office"), Note.

**Heartbeat keys** (default cache store, `Cache::forever`, one key per fact): `vouchers:till-sync:last` (at, ok, source, counts, error cut to 300 chars), `vouchers:till-sync:last-ok`, `vouchers:till-sync:last-scheduled` (written by every `--scheduled` run, successful or not). Recording or reading them never fails a sync, a lookup or the feed.

Code: `app/Http/Controllers/VoucherActivityController.php`, `app/Services/VoucherActivityService.php`, `app/Services/VoucherSyncHeartbeat.php`, `resources/views/vouchers/activity.blade.php` (inline Alpine), config `vouchers.activity.*`.

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
- The generate form (`/vouchers/generate`) takes a **count and one value** for the whole batch (value €0.01 to `vouchers.max_face_value`, default €1000, 2 dp). The print page shows the value under each code on screen; the printed label does not.
- After generating, the user lands on a **preview + print** page (`resources/views/vouchers/print.blade.php`) that renders each label via the in-browser `ZplPreview` emulator (`resources/js/zpl-preview.js`) and has a **Print to Zebra** button.
- Printing goes through `ZebraPrintService::sendRaw()` (`app/Services/ZebraPrintService.php`) — ZPL → temp file → a `timeout`-guarded `lp … -o raw` (CUPS raw print over IPP), with the printer from `config('services.zebra.*')`. The same service backs every other Zebra print path in the app; do not shell out to `lp` directly. 📖 [Label Translation System Documentation](./label-translation-system.md)
- Voucher ZPL carries no `^PQ` (each code is unique, so there is nothing to repeat), which means `ZebraLabel::setZplQuantity()` is not involved — one `^XA…^XZ` block per voucher is concatenated into a single job.
- If labels do not appear, check the spool **on the printer host**, not the local machine: the **Printer Queue** card on `/labels/zebra`, or `lpstat -h {host}:631 -o`.

## Admin: deactivate / reactivate

(For many vouchers at once, and for delete / restore / make for sale, see [Admin changeover tools](#admin-changeover-tools).)

On the **All Vouchers** page (`/vouchers/list`), an admin-only **Edit** button (shown for `active`/`deactivated` rows) opens a modal to deactivate or reactivate the voucher with an optional reason/note. The action is logged to the voucher's transaction history (with the note). Deactivating preserves the balance and blocks redemption; reactivating restores it to `active`.

## Admin changeover tools

Added in vouchers cycle 4 (2026-09-29). Tools for bringing the vouchers that predate the sell-at-the-till process (activated by hand, 2026-06 to 2026-09) into it, and for clearing out test vouchers. **Admins only**, and all behind one switch, **`VOUCHER_ADMIN_TOOLS`** (`config('vouchers.admin_tools')`, default `true`): when false the five routes answer 404, the list page shows none of the controls, and the retire command refuses to run.

### Bulk actions on `/vouchers/list`

For an admin the list gets a checkbox per voucher, "select all on this page", a **per page** choice (25/50/100/200) and a **Deleted** status filter. The bar above the table shows how many are selected and their total balance, a note field, and the actions. Each action asks for confirmation in an in-page dialog, handles every voucher separately (one that does not qualify is skipped and named in the result banner, e.g. "Deactivated 36 vouchers. Skipped 1: GV… (not active).") and writes one `voucher_transactions` row per voucher with the admin as user and the note.

| Action | Applies to | Effect |
|---|---|---|
| **Make for sale** | `active` or `deactivated` vouchers **nobody has used**: balance > 0, equal to the initial value, and no `deduct` row ever | Back to unsold: `face_value` = the balance, balance 0, `initial_value` cleared, status `inactive`. Transaction `for_sale` (shown as "Made for sale", a negative amount). The till product becomes `[for sale €X]` at price X (created if missing), so the label sells through the till and activates like a new voucher. Skipped with a reason otherwise: `deleted`, `not active or deactivated`, `no balance`, `has been spent from`, `balance differs from its initial value` |
| **Deactivate selected** | `active` | → `deactivated` (balance kept). Transaction `deactivate`. Till product `[deactivated]` (created if missing) |
| **Reactivate selected** | `deactivated` | → `active`. Transaction `activate` |
| **Delete selected** | `inactive`, `deactivated`, `exhausted` (**not `active`**: deactivate it first, two deliberate steps before a balance disappears). **A reason is required** | Soft delete. Transaction `delete`. The voucher leaves the list, the activity log and the totals; its unreviewed till exceptions are marked reviewed; its till product stays (it may be on past tickets) renamed `[deleted]` at price 0; its code stays reserved (lookup says "This voucher was deleted. It cannot be used."; activation refused). A deleted label scanned at the till deducts nothing and appears on the exceptions page. **Destroy the physical labels** |
| **Restore selected** (Deleted view) | deleted vouchers | Back with the status and balance they had. Transaction `restore`. Till product renamed back (a for-sale voucher is priced again) |

**Undoing make for sale**: a manager scans the voucher on `/vouchers` and activates it by hand (the value is pre-filled). There is no undo button.

"Activate the one voucher that was sold" needs no tool: leave it unticked, or reactivate it after a bulk deactivation.

Code: `app/Services/VoucherAdminService.php`, `app/Http/Controllers/VoucherAdminToolsController.php`, `resources/views/vouchers/partials/admin-tools.blade.php` (the bar, the dialog and its `voucherBulk()` Alpine component), plus the `$adminTools` blocks in `resources/views/vouchers/list.blade.php` and `VoucherController::list()`.

### Retiring the three fixed voucher products

`php artisan vouchers:retire-fixed-products [--dry-run] [--restore]` works on `config('vouchers.legacy_product_codes')` = `6012` "Voucher 50 Euro", `6013` "Voucher 10 Euro", `6014` "Voucher 20 Euro". Retiring removes each one's till button (`PRODUCTS_CAT`), adds ` (retired)` to its name and changes its barcode to `RET6012` etc., so keying the old code on the till finds nothing. The product rows stay because past sales refer to them (reports show the new name). `--dry-run` prints the table and writes nothing; `--restore` puts code, name and button back. **Tills load their buttons at start-up: restart uniCenta on each till** afterwards.

### Changeover on production (owner checklist)

1. Find the one hand-activated voucher that was really sold and keep its code to hand.
2. `/vouchers/list`, status **Active**, **200 per page**: tick all, **untick the sold one**, **Make for sale**. The banner should report 36 done.
3. Filter to the test vouchers (**Deactivated**, then **Inactive**): **Delete selected**, with a reason. Destroy the physical test labels.
4. On the server, as the web user: `php artisan vouchers:retire-fixed-products --dry-run`, then without `--dry-run`. Restart uniCenta on each till.
5. `/vouchers/activity`: the health banner should be green, and "Outstanding balance" should be the sold voucher's balance only.

### Removing the tools later

Delete `app/Http/Controllers/VoucherAdminToolsController.php`, `app/Services/VoucherAdminService.php`, `resources/views/vouchers/partials/admin-tools.blade.php`, the five `vouchers.bulk.*` routes, `app/Console/Commands/RetireFixedVoucherProducts.php` and their tests; remove the `$adminTools` / `$showDeleted` / `per_page` parts of `VoucherController::list()` and the `@if ($adminTools)` blocks in `list.blade.php`; remove `admin_tools` and `legacy_product_codes` from `config/vouchers.php`. **Keep**, if any voucher has ever been deleted: the `withTrashed()` handling in `VoucherTillSyncService` (`resolveVoucher()`, `applyLine()`, `applySale()`), `VoucherController::lookup()` / `activate()`, the `[deleted]` name in `VoucherPosProductService`, the `whereHas('voucher')` filters in `VoucherActivityService`, and the transaction types and labels (`for_sale`, `delete`, `restore`), which stay in the history.

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
| `vouchers.activity` | GET `/vouchers/activity` | manage |
| `vouchers.activity.feed` | GET `/vouchers/activity/feed?days=1\|7\|30&q=` (JSON) | manage |
| `vouchers.deactivate` / `.reactivate` | POST `/vouchers/{voucher}/...` | admin |
| `vouchers.bulk.for-sale` / `.deactivate` / `.reactivate` / `.delete` / `.restore` | POST `/vouchers/bulk/{action}` (`ids[]`, `note`) | admin, and `VOUCHER_ADMIN_TOOLS` on |
| `vouchers.list` extras | GET `/vouchers/list?per_page=25\|50\|100\|200&status=deleted` | `status=deleted` admin only |

## Key files

- **Controller**: `app/Http/Controllers/VoucherController.php`, `app/Http/Controllers/VoucherActivityController.php`, `app/Http/Controllers/Shop/VouchersController.php`
- **Models**: `app/Models/Voucher.php`, `app/Models/VoucherTransaction.php`, `app/Models/VoucherTillRedemption.php`
- **Till redemption**: `app/Services/VoucherPosProductService.php`, `app/Services/VoucherTillSyncService.php`, `app/Console/Commands/SyncVoucherPosProducts.php`, `app/Console/Commands/SyncVoucherTillRedemptions.php`, `config/vouchers.php`
- **Activity screen**: `app/Services/VoucherActivityService.php`, `app/Services/VoucherSyncHeartbeat.php`
- **Admin changeover tools**: `app/Services/VoucherAdminService.php`, `app/Http/Controllers/VoucherAdminToolsController.php`, `resources/views/vouchers/partials/admin-tools.blade.php`, `app/Console/Commands/RetireFixedVoucherProducts.php`
- **Migrations**: `database/migrations/2026_06_24_120000_create_vouchers_table.php`, `..._120001_create_voucher_transactions_table.php`, `2026_06_25_120000_add_note_to_voucher_transactions_table.php`, `2026_09_28_100000_create_voucher_till_redemptions_table.php`, `..._100001_add_source_to_voucher_transactions_table.php`, `..._100002_add_pos_product_id_to_vouchers_table.php`, `2026_09_30_100000_add_face_value_to_vouchers_table.php`, `..._100001_add_sale_amount_to_voucher_till_redemptions_table.php`
- **Views**: `resources/views/vouchers/{index,list,generate,print,transactions,exceptions,activity}.blade.php`
- **Permissions/nav**: `database/seeders/RolesAndPermissionsSeeder.php`, `resources/views/layouts/admin.blade.php`
- **Dev helpers** (never against production): `docs/vouchers/scripts/` — `simsale.php` (fake till sale; `SALE=1` sells a voucher as an item), `numeric_barcode.php`, `reset_voucher.php`
- **Reused**: `resources/js/barcode-scanner.js`, `resources/js/zpl-preview.js`, `app/Services/LabelService.php`, `config/services.php` (`zebra`)
