# Barrel Deposit Tracking System

This document covers the barrel deposit tracking system for managing returnable items (crates, bottles, pallets) from supplier deliveries.

## Overview

The barrel deposit tracking system automatically extracts and stores deposit items from supplier invoices (currently Udea). These are returnable items that get charged on delivery and need to be tracked for reconciliation when returned.

## Key Features

- **Automatic Extraction**: Barrel data is automatically parsed from Udea delivery PDFs
- **Reference Database**: Barrel codes are stored as reference data, auto-populated from imports
- **Per-Delivery Tracking**: Each delivery records its barrel line items with quantities and values
- **Custom Naming**: Users can add custom names to barrel codes for easier identification
- **Image Support**: Upload photos for each barrel type for visual identification
- **Collapsible Display**: Barrel section on delivery pages is collapsed by default to save space

## System Architecture

### Core Components

1. **Python Parser** (`scripts/invoice-parser/parsers/delivery_udea.py`)
   - `_parse_barrels_section()` - Extracts barrel items from PDF text
   - Identifies "Barrels delivered with product" section
   - Parses code, quantity, description, price, and total for each barrel item
   - Stops parsing at "Costs" section to exclude freight charges

2. **Models** (`app/Models/`)
   - `BarrelCode` - Reference table of all barrel types per supplier
   - `DeliveryBarrel` - Line items linking barrels to specific deliveries
   - `Delivery` - Extended with `barrels()` relationship

3. **Services** (`app/Services/`)
   - `DeliveryService::storeBarrelItems()` - Creates/updates barrel codes and delivery barrel records
   - Uses `firstOrCreate` pattern to avoid duplicate barrel codes

4. **Controllers** (`app/Http/Controllers/`)
   - `DeliveryController::storePdf()` - Stores barrel items during delivery import
   - `BarrelCodeController` - CRUD for barrel code management with image handling

5. **Views** (`resources/views/`)
   - `deliveries/show.blade.php` - Collapsible barrel section on delivery detail
   - `barrel-codes/index.blade.php` - List all barrel codes with filters
   - `barrel-codes/edit.blade.php` - Edit barrel code with image upload

## Database Schema

### barrel_codes Table
Reference table for barrel types (auto-populated from imports):

```sql
- id (primary key)
- supplier_code (varchar 20) - Udea code: "69", "71", "313"
- supplier_id (unsigned bigint) - Link to supplier
- description (varchar 150) - "Beutelsb klein leeg - Landpark"
- name (varchar 100, nullable) - Custom user-assigned name
- image (binary, nullable) - Barrel photo (100x100 resized)
- unit_price (decimal 8,2) - Current price per unit
- is_active (boolean, default true)
- timestamps

UNIQUE INDEX: (supplier_id, supplier_code)
```

### delivery_barrels Table
Line items per delivery:

```sql
- id (primary key)
- delivery_id (foreign key to deliveries)
- barrel_code_id (foreign key to barrel_codes, nullable)
- supplier_code (varchar 20) - Code stored directly
- description (varchar 150) - Description from invoice
- quantity (integer) - Quantity received
- unit_price (decimal 8,2) - Price at time of delivery
- total (decimal 10,2) - Line total
- timestamps

INDEXES: delivery_id, barrel_code_id, supplier_code
```

## Udea Invoice Format

The parser recognizes this section format from Udea PDFs:

```
Barrels delivered with product
Brl code  Amount Description                    Price VAT  Total
15        1      Wegwerppallet                  0,00  1    0,00
69        1      Beutelsb klein leeg - Landpark 1,50  1    1,50
71        3      Beutelsbacher krat groot leeg  2,00  1    6,00
313       60     Statiegeld fles/glas 0,25      0,25  1    15,00
                 Total barrels delivered              44,28
Costs
Cost code Amount Description                    Price VAT  Total
191       1      Freight 2                      293,55 4   293,55
```

**Important**: The parser stops at "Costs" or "Cost code" to exclude freight charges from barrels.

## User Workflow

### Viewing Barrels on Delivery
1. Navigate to a delivery detail page (`/deliveries/{id}`)
2. If the delivery has barrels, a collapsed amber section shows:
   - "Barrels/Deposits: €44.28 (8 items) [Show]"
3. Click "Show" to expand and see the full breakdown

### Managing Barrel Codes
1. Navigate to Deliveries page
2. Click "Barrel Codes" button (amber colored)
3. Browse all barrel codes with:
   - Search by code, description, or custom name
   - Filter by supplier
   - Filter by active/inactive status
4. Click "Edit" to:
   - Add a custom name for the barrel
   - Update description or unit price
   - Upload a photo for identification
   - Mark as inactive if no longer used

## Routes

```php
// Barrel Codes Management
GET    /barrel-codes                    - List all barrel codes
GET    /barrel-codes/{id}/edit          - Edit barrel code form
PUT    /barrel-codes/{id}               - Update barrel code
GET    /barrel-codes/{id}/image         - Serve barrel image
POST   /barrel-codes/{id}/image         - Upload barrel image
DELETE /barrel-codes/{id}/image         - Remove barrel image

// Customer bottle deposits (permission deliveries.manage), names deposits.*
GET    /deposits                          - Screen (?status=…&group=tier|brand|none&sort=name|markup|sales)
PATCH  /deposits/tiers/{id}               - Switch a tier on/off for customers
POST   /deposits/products                 - Add a product by hand
POST   /deposits/products/confirm-all     - Confirm all suggested (optional barrel_code_id or brand); prices unchanged
PATCH  /deposits/products/{id}            - Confirm / reject / re-tier a row; a confirm may carry
                                            price_action=keep|lower|custom (+ new_price, gross)
PATCH  /deposits/products/{id}/price      - action=keep|lower|reset|undo (deposits.products.price)
PATCH  /deposits/settings                 - Delivery charge % (deposits.settings)
DELETE /deposits/products/{id}            - Remove a manual row
POST   /deposits/refresh                  - Recompute suggestions
POST   /deposits/sync                     - Sync the till
```

## Data Flow

```
PDF Upload
    ↓
delivery_udea.py::_parse_barrels_section()
    ↓ extracts barrel items
delivery_parser_laravel.py
    ↓ includes barrels in response['data']['barrels']
DeliveryParsingService
    ↓ passes barrel data through
DeliveryController::storePdf()
    ↓ calls storeBarrelItems()
DeliveryService::storeBarrelItems()
    ↓
BarrelCode::firstOrCreate() + DeliveryBarrel::create()
    ↓
Database: barrel_codes + delivery_barrels tables
```

## Files Reference

### Python
- `scripts/invoice-parser/parsers/delivery_udea.py` - Barrel extraction
- `scripts/invoice-parser/delivery_parser_laravel.py` - Response formatting

### PHP
- `app/Models/BarrelCode.php` - Barrel code model
- `app/Models/DeliveryBarrel.php` - Delivery barrel model
- `app/Models/Delivery.php` - Barrels relationship
- `app/Services/DeliveryService.php` - storeBarrelItems()
- `app/Http/Controllers/BarrelCodeController.php` - Barrel code CRUD
- `app/Http/Controllers/DeliveryController.php` - storePdf() integration

### Views
- `resources/views/deliveries/show.blade.php` - Collapsible barrel display
- `resources/views/deliveries/index.blade.php` - Barrel Codes button
- `resources/views/barrel-codes/index.blade.php` - Barrel list page
- `resources/views/barrel-codes/edit.blade.php` - Barrel edit form

### Migrations
- `database/migrations/2026_01_26_121414_create_barrel_storage_tables.php`
- `database/migrations/2026_01_26_123319_add_name_and_image_to_barrel_codes_table.php`

## Customer Bottle Deposits (2026-10)

Udea charges a deposit on glass bottles and jars, and prints the deposit code
on each product line of the delivery note (`… Bio-Dynamisch DE 313`). The app
passes that deposit on to customers at the till, at cost and zero-rated.
The plan/implement history is in [`docs/deposit/`](../deposit/README.md).

### Data

| Table | What |
|---|---|
| `delivery_items.barrel_code` | Per-line deposit code from the Udea delivery note (parser `split_barrel_code`). When pdfplumber garbles the country code (`… Bio-quNeLlle 313`), the parser and the backfill accept a bare trailing code only if the same docket's barrels section lists it |
| `barrel_codes.charge_customer` | Tier switched on for customers; `pos_product_id` / `pos_refund_product_id` hold its till products |
| `deposit_sightings` | Evidence: one row per delivery line seen with a code (`source_type` `delivery_item`) |
| `product_deposits` | Till product → tier, `status` `suggested` / `confirmed` / `rejected`, `source` `invoice` / `manual`, evidence counts, till sync state |
| `product_deposits.price_review` … | The owner's price review (cycle 4): `price_review` `kept` / `lowered` / `set` (null = not reviewed), `price_review_from` (gross price before a lowering or a typed price; Undo writes it back), `price_reviewed_at`, `price_reviewed_by` (user) |
| `app_settings` key `deposits.delivery_pct` | Delivery charge on Udea cost in percent, default 15 (`App\Models\AppSetting`) |

Models: `DepositSighting`, `ProductDeposit` (and the new columns on
`DeliveryItem` and `BarrelCode`). `App\Support\PosProductAttributes` reads and
writes uniCenta's `PRODUCTS.ATTRIBUTES` (Java Properties XML), keeping other keys.

### Services

- `App\Services\Deposits\DepositEvidenceService`: records sightings for a
  delivery, backfills codes on old delivery lines, builds suggestions (only for
  tiers with `charge_customer`; the dominant code wins; confirmed and rejected
  rows keep their tier), and runs after every Udea delivery PDF import
  (`afterDeliveryImport`, never fails the import).
- `App\Services\Deposits\DepositPosService`: the only writer on the till. It
  creates or adopts the tier products (`DEP-025` "Bottle deposit 0.25",
  `DEP-025-RET` "Bottle deposit refund 0.25", category "Bottle Deposits",
  refunds as catalogue buttons), writes `deposit.id` / `deposit.name` /
  `deposit.price` to confirmed products, clears them elsewhere, refuses
  weighed and variable-price products, and reports drift (`check()`).
- `App\Services\Deposits\DepositPriceReviewService`: the price facts on
  `/deposits` (two queries for the whole table: Udea delivery lines, and the
  till's own ticket lines, read-only) and the price writes (Lower, a typed
  price, Undo). Cost is the latest Udea docket `unit_cost` (> 0), else the
  till's `PRICEBUY`; the **landed cost** is that cost × (1 + delivery charge),
  and markup and profit are against it. Udea advice is the latest docket
  `sale_price` (> 0; dockets from 2026-04-16 carry it). The last price change
  comes from **full-price till lines since 2024-01-01, discounted lines
  ignored** (a line whose `ATTRIBUTES` `product.name` ends ` - 5%` / ` - 10%`):
  each day's full price is the price with the most full-price lines, ties to
  the higher; a day at least 0.05 from the established price counts as a step
  only when the next three selling days stay within 0.02 of it, so a change in
  the last three selling days does not show yet. "Changed since last sale"
  flags a till price 0.05 or more away from the last full-price sale. Sold in
  12 months counts every line, discounted ones included. It never labels a
  product "deposit included"; the owner decides.

The accounting invoice parser (`invoice_udea.py`) also emits `barrel_code` on
its lines, but nothing reads it: the delivery note drives the mapping.

### Commands

```bash
php artisan deposits:backfill-delivery-items [--dry-run]   # split codes off old delivery lines
php artisan deposits:refresh-suggestions                   # rebuild suggestions from the evidence
php artisan deposits:sync-pos [--dry-run] [--check]        # write / preview / audit the till
php artisan deposits:install-till [--check] [--rollback] [--force]   # the till scripts (below)
```

None is scheduled; the delivery import hook keeps things current.

### Screen

`/deposits` (permission `deliveries.manage`; links from `/barrel-codes` and
`/deliveries`), layout "2a" from the owner's Claude Design mock-up (cycle 4),
top to bottom:

The page uses the Modernist design system from the owner's design project
(Archivo, red accent, square corners, 2px rules), in
`resources/css/modernist.css`, scoped to `.ds-modernist` so nothing reaches
the admin shell, and loaded only by this view through the layout's
`@stack('styles')`. Its ground is always light; it does not follow the
shell's dark theme. The page's own classes are the `.dr-*` section at the end
of that file. Rows are kept to two or three short lines (sales, price step
and evidence each on one line; full product names in the tooltip).


- **Bar** at the top of the page: "Till synced <ago>" (latest
  `pos_synced_at`, or never), **Sync till now**, **Recompute suggestions**,
  **Confirm all suggested (n)**.
- **Deposit codes** strip: one cell per Udea deposit code with its price,
  description, the **Charge customers** checkbox, the till products once
  created (`DEP-025 / DEP-025-RET`, or "not in till yet") and the confirmed
  count. It lists the codes charged to customers or seen on a delivery
  line (`deposit_sightings`); crates, pallets and other unseen codes appear
  only after **Show all n codes** (`?codes=all`).
- **Price review** band: "n of m decided · n prices reviewed"; the
  **Delivery charge** % (−, number, +; shop-wide, stored in `app_settings`);
  **Status** (Suggested + confirmed, the default; Suggested; Confirmed;
  Rejected; All); **Group by** Tier / Brand (first word of the till name) /
  None; **Sort** Brand / name A–Z, Markup lowest first, Sales volume highest
  first.
- **Grid**, under group headers ("Tier 313 · €0.25 deposit · 12 products",
  with **Confirm n** for the group's suggestions):
  Product (name, barcode · Udea code, deposit tier select) · Sales (sold in
  12 months, last sale, Udea delivery evidence, other codes seen) · Price
  (gross; the last lasting price step, with the Udea cost before it in the
  tooltip; "now x, was y (no sale since <date>)" when the till price changed
  after the last sale) · Markup now (against landed cost, bar, landed and net) ·
  Udea (advice price) · New price (Keep / −€deposit / a typed € price; a typed
  price tints the cell) · New markup (recomputed in the browser as the choice
  changes) · Decision (✓ Confirm and ✗ Reject while suggested; then the status,
  any till sync error, and **Undo**). Decided rows are dimmed.
- A footer with the markup formula, then **Add a product** (by hand).

**The price choice and the deposit decision are one step.** A row's ✓ Confirm
confirms the deposit and applies the row's price choice: Keep records `kept`;
−deposit and a typed price write the till (a confirm dialog asks first) and
record `lowered` / `set` with the gross price before. Writes use the product
page's recipe: gross converted to net with the product's VAT rate, written to
`PRODUCTS.PRICESELL`, then a `LabelLog` `price_update`, so the product appears
in the label area under "Price changed". A refused write (price not above the
deposit, product missing) leaves the deposit confirmed and shows an error.
**Undo** takes the row back to suggested, clears its till deposit properties,
and, when its confirm changed the price, writes the old price back (another
label-log row). The bulk confirms (header and group) never change prices.

Markups since this layout are against landed cost (Udea cost × (1 + delivery
charge)), so they read lower than the bare-cost figures of the first price
review.

### At the till

Two BeanShell event scripts on the till (`script.Deposit.AddLine` on
`ticket.addline`, `script.Deposit.Change` on `ticket.change`) read the
`deposit.*` properties of a scanned product and add the deposit line
directly under it, keeping its quantity in step. The deployable copies live
in `resources/pos/deposit/`; `deposits:install-till` installs them into
`RESOURCES` and wires the two events into `Ticket.Buttons` (backup row
`Ticket.Buttons.pre-deposit` first; refuses unless the `ticket.close` anchor
line occurs exactly once; asks before writing to a non-dev POS). Every till
must restart afterwards. History and the till test matrix: `docs/deposit/`.

### Deposit codes on the delivery page

At import, the parser's per-code check (units on product lines vs the
"Barrels delivered" section) is kept in `deliveries.import_data.deposit_reconciliation`.
The delivery page shows it in the Barrels/Deposits block: a red
"n deposit mismatch(es)" with the warnings under the header, and a "Deposit
codes on product lines" table (matches / differs by n / not in section)
when expanded. Deliveries imported before 2026-10-04 have no reconciliation.

## Going live on production

Who: the owner. Where: production (`lilThink2`), artisan as `www-data`
(`sudo -u www-data php artisan …`). Nobody edits the POS database by hand.

1. **Deploy.** Commit and deploy as usual; the deploy runs
   `php artisan migrate --force` (the five deposit migrations).
2. **Evidence.** `php artisan deposits:backfill-delivery-items --dry-run`,
   then the same without `--dry-run`; then
   `php artisan deposits:refresh-suggestions`.
3. **Decide.** On `/deposits`: switch on tiers 313, 315 and 9936 (10046 is
   optional); confirm the products (per row, or "Confirm all suggested"),
   reject any that are wrong.
3b. **Review prices.** On `/deposits`, set the delivery charge, then for each
   row read the Price (last price change), Markup now and Udea columns
   (sorting by markup helps). Pick Keep, −deposit or a price, then Confirm:
   that confirms the deposit and writes the chosen price. A changed price
   lands in the label area under "Price changed", ready for a new shelf label.
   "Confirm all suggested" and a group's "Confirm n" confirm the deposit only
   and keep every price.
4. **Write the product properties.** `php artisan deposits:sync-pos --check`,
   then `php artisan deposits:sync-pos`. The tills ignore the properties
   until the scripts exist, so this is safe to do first.
5. **Install the till scripts.** `php artisan deposits:install-till --check`
   (expect "Not installed."), then `php artisan deposits:install-till`
   (it prints the POS host and asks to confirm), then `--check` again: exit 0,
   "Installed.". If it refuses because the anchor count is not 1, stop and
   send the `--check` output; nothing was written.
6. **Restart uniCenta on both tills** (`OrgStore` and `Till 2`). Without a
   restart nothing changes at the till.
7. **Prove it on one till.** Scan a 313 product: a "Bottle deposit 0.25"
   line appears under it; print the receipt. In the catalogue open
   **Bottle Deposits** and tap "Bottle deposit refund 0.25": a −0.25 line.
   Ring a refund-only ticket and pay it out in cash.
8. **Tell staff.** Publish the staff procedure (`docs/deposit/sop-bottle-deposits.md`)
   in BookStack.
9. **Rollback, any time.** `php artisan deposits:install-till --rollback`,
   then restart both tills. Deposit lines stop; the product properties may
   stay (they do nothing without the scripts).

Notes:

- Every Udea docket import runs `syncAll()`, which clears `deposit.*`
  properties added by hand on the till to products without a confirmed row.
  Make changes on `/deposits`, not on the till.
- Deposit and refund lines appear in sales analytics as zero-VAT sales in
  category "Bottle Deposits" (refunds negative).
- A tier price change creates a new `DEP-<cents>` pair and keeps the old
  refund button, so bottles sold at the old price can still be refunded at it.

## Future Enhancements

- **Returns Tracking**: Track when barrels are returned to supplier
- **Reconciliation Report**: Show barrels received vs returned per period, and the customer deposit float (charged at the till vs refunded vs Udea credits)
- **Multi-Supplier Support**: Extend barrel parsing to other suppliers beyond Udea
