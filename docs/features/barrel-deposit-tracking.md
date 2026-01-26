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

## Future Enhancements

- **Returns Tracking**: Track when barrels are returned to supplier
- **Reconciliation Report**: Show barrels received vs returned per period
- **Freight Extraction**: Separately extract and track freight/delivery charges
- **Multi-Supplier Support**: Extend barrel parsing to other suppliers beyond Udea
