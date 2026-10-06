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
`/deliveries`): switch tiers on or off, confirm, reject or re-tier suggested
products (with their evidence), add a product by hand, confirm all, recompute,
sync the till.

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
   `php artisan migrate --force` (the four deposit migrations).
2. **Evidence.** `php artisan deposits:backfill-delivery-items --dry-run`,
   then the same without `--dry-run`; then
   `php artisan deposits:refresh-suggestions`.
3. **Decide.** On `/deposits`: switch on tiers 313, 315 and 9936 (10046 is
   optional); confirm the products (per row, or "Confirm all suggested"),
   reject any that are wrong.
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
