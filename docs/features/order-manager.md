# Order Manager

## Overview

The Order Manager is a decision-support tool designed to help monitor stock levels for smaller or non-routine suppliers that are easy to overlook. Unlike frequently ordered suppliers, these occasional suppliers can run out of stock before anyone notices. Order Manager provides visibility into stock levels and highlights when orders may be needed.

**Key Principle**: This is a support tool only—it does not place orders automatically. It highlights potential ordering needs based on stock levels and supplier selection, allowing users to make informed ordering decisions.

**Added**: January 2026

## Features

### Core Functionality

1. **Managed Supplier Selection**
   - Mark specific POS-linked suppliers as "managed" by Order Manager
   - Toggle switch for easy add/remove from monitoring
   - Only POS-linked suppliers can be managed (requires product data)

2. **Per-Supplier Stock Thresholds**
   - Set a custom stock threshold for each managed supplier (default: 10 units)
   - Products below this threshold are flagged as "low stock"
   - Products at zero are flagged as "out of stock"

3. **Expandable Product View**
   - Click expand button to view all products for a supplier
   - See current stock levels with color-coded status indicators
   - Product details: name, barcode, supplier code, case units
   - Summary stats: total products, low stock count, out of stock count

4. **Stock Check Report**
   - User-initiated action via "Run Stock Check" button
   - Groups results by supplier
   - Shows only suppliers with products needing attention
   - Separate section for suppliers with all stock OK

## Architecture

### Components

| Component | File | Description |
|-----------|------|-------------|
| Model | `app/Models/AccountingSupplier.php` | Extended with `is_order_managed` and `order_manager_threshold` fields |
| Service | `app/Services/OrderManagerService.php` | Business logic for stock checks and product queries |
| Controller | `app/Http/Controllers/OrderManagerController.php` | HTTP endpoints for dashboard and AJAX actions |
| Views | `resources/views/order-manager/` | Dashboard and results pages |

### Database Schema

```sql
-- Added to accounting_suppliers table
ALTER TABLE accounting_suppliers ADD COLUMN is_order_managed BOOLEAN DEFAULT FALSE;
ALTER TABLE accounting_suppliers ADD COLUMN order_manager_threshold INTEGER DEFAULT 10;
```

**Migration**: `2026_01_20_132450_add_order_manager_fields_to_accounting_suppliers_table.php`

### Data Flow

The Order Manager queries across two databases:

```
AccountingSupplier (Laravel DB)
    ↓ external_pos_id
SupplierLink (POS DB)
    ↓ SupplierID → Barcode
stocking (POS DB)
    ↓ Barcode must exist (filters out discontinued products)
Product (POS DB)
    ↓ CODE matches Barcode
StockCurrent (POS DB)
    → UNITS (current stock level)
```

**Important**: Only products that exist in the `stocking` table (actively stocked in store) are included. This matches the filtering used by the main order generation system and excludes discontinued products.

### Key Service Methods

```php
class OrderManagerService
{
    // Run stock check for all managed suppliers
    public function runStockCheck(): array

    // Get low-stock products for a supplier
    public function getLowStockProductsForSupplier(string $posSupplierID, int $threshold): Collection

    // Get ALL products for a supplier (for expandable view)
    public function getAllProductsForSupplier(string $posSupplierID, int $threshold): Collection

    // Get summary statistics
    public function getStats(): array

    // Get POS-linked suppliers (candidates for management)
    public function getPosLinkedSuppliers(): Collection

    // Get low-stock count for a single supplier
    public function getLowStockCountForSupplier(AccountingSupplier $supplier): int
}
```

## Routes

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/order-manager` | `order-manager.index` | Dashboard with supplier list |
| GET | `/order-manager/check` | `order-manager.check` | Run stock check, show results |
| POST | `/order-manager/{supplier}/toggle` | `order-manager.toggle` | Toggle managed status (AJAX) |
| PATCH | `/order-manager/{supplier}/threshold` | `order-manager.threshold` | Update threshold (AJAX) |
| GET | `/order-manager/{supplier}/products` | `order-manager.products` | Get products for supplier (AJAX) |

## Usage

### Setting Up Managed Suppliers

1. Navigate to `/order-manager`
2. Find the supplier you want to monitor in the table
3. Click the toggle switch to enable monitoring (turns blue)
4. Adjust the threshold if needed (default is 10 units)

### Viewing Stock Levels

1. Click the chevron (▶) next to any supplier to expand
2. View all stocked products with current levels
3. Products are sorted by stock level (lowest first)
4. Status badges show: Out of Stock (red), Low Stock (yellow), OK (green)

### Running a Stock Check

1. Click "Run Stock Check" button
2. View results grouped by supplier
3. Suppliers needing attention appear first with product details
4. Suppliers with adequate stock are listed separately

### Adjusting Thresholds

1. Change the threshold value in the input field
2. Value saves automatically on change
3. If the supplier is expanded, the view refreshes to reflect new status
4. Products may change from "Low Stock" to "OK" (or vice versa) based on new threshold

## User Interface

### Dashboard (`/order-manager`)

```
┌─────────────────────────────────────────────────────────────┐
│ Order Manager                    [Run Stock Check] [Back]   │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                  │
│  │    3     │  │    12    │  │    2     │                  │
│  │ Managed  │  │ Low Stock│  │ Out of   │                  │
│  │ Suppliers│  │ Products │  │ Stock    │                  │
│  └──────────┘  └──────────┘  └──────────┘                  │
├─────────────────────────────────────────────────────────────┤
│ How to Use:                                                 │
│ ✓ Toggle suppliers to add them to monitoring               │
│ ↔ Set stock threshold for each supplier                    │
│ ↗ Click expand to view all products                        │
│ ☑ Click "Run Stock Check" for summary                      │
├─────────────────────────────────────────────────────────────┤
│ POS-Linked Suppliers                                        │
│ ┌───┬─────────┬──────────────┬────────┬───────────┬───────┐│
│ │ ▶ │ Managed │ Supplier     │ POS ID │ Threshold │ Low   ││
│ ├───┼─────────┼──────────────┼────────┼───────────┼───────┤│
│ │ ▶ │ [====]  │ Supplier A   │ SUP001 │ [10] units│ 5 items│
│ │ ▼ │ [====]  │ Supplier B   │ SUP002 │ [15] units│ All OK │
│ │   │ ├────────────────────────────────────────────────┤  │
│ │   │ │ Product Name    │ Barcode │ Stock │ Status    │  │
│ │   │ ├────────────────────────────────────────────────┤  │
│ │   │ │ Widget A        │ 123456  │  8.0  │ OK        │  │
│ │   │ │ Widget B        │ 123457  │  3.0  │ Low Stock │  │
│ │   │ └────────────────────────────────────────────────┤  │
│ │ ▶ │ [    ]  │ Supplier C   │ SUP003 │ [10] units│ -     ││
│ └───┴─────────┴──────────────┴────────┴───────────┴───────┘│
└─────────────────────────────────────────────────────────────┘
```

### Stock Check Results (`/order-manager/check`)

- **Suppliers Needing Attention**: Expanded view with full product tables
- **Suppliers OK**: Collapsed list confirming stock is adequate
- **All Clear Message**: Shown when no products need attention

## Model Changes

### AccountingSupplier Model

Added fields to `$fillable`:
- `is_order_managed`
- `order_manager_threshold`

Added casts:
- `'is_order_managed' => 'boolean'`
- `'order_manager_threshold' => 'integer'`

Added scope:
```php
public function scopeOrderManaged($query)
{
    return $query->where('is_order_managed', true)
                 ->where('is_pos_linked', true);
}
```

## Testing

### Manual Testing Checklist

- [ ] Navigate to `/order-manager` as authenticated user
- [ ] Verify POS-linked suppliers are displayed
- [ ] Toggle a supplier to "managed" status
- [ ] Verify threshold input becomes active
- [ ] Change threshold value
- [ ] Expand a supplier to view products
- [ ] Verify stock levels and status badges
- [ ] Run stock check and verify results
- [ ] Toggle supplier off and verify it's removed from check

### Verification Commands

```bash
# Check if a supplier is managed
php artisan tinker
>>> App\Models\AccountingSupplier::orderManaged()->get(['id', 'name', 'order_manager_threshold']);

# Test the service
>>> $service = app(App\Services\OrderManagerService::class);
>>> $service->getStats();
>>> $service->runStockCheck();
```

## Troubleshooting

### Issue: No suppliers appearing in the list

**Cause**: No suppliers are linked to the POS system.

**Solution**: Link suppliers to POS via the supplier edit page, or ensure `is_pos_linked = true` and `external_pos_id` is set.

### Issue: Products not showing when expanded

**Cause**: No products in `supplier_link` table for that supplier, `stocked` flag is false, OR products are not in the `stocking` table.

**Solution**: Verify the supplier has products in the POS database that are also actively stocked:
```sql
-- Check supplier_link entries
SELECT sl.Barcode, p.NAME, sl.stocked
FROM supplier_link sl
JOIN PRODUCTS p ON sl.Barcode = p.CODE
WHERE sl.SupplierID = 'SUP001234';

-- Check which are in the stocking table (actively stocked)
SELECT sl.Barcode, p.NAME
FROM supplier_link sl
JOIN stocking s ON sl.Barcode = s.Barcode
JOIN PRODUCTS p ON sl.Barcode = p.CODE
WHERE sl.SupplierID = 'SUP001234' AND sl.stocked = 1;
```

### Issue: Discontinued products appearing

**Cause**: Products in `supplier_link` with `stocked=true` but not in the `stocking` table.

**Solution**: This was fixed in January 2026 by adding a join to the `stocking` table. Discontinued products (not in `stocking` table) are now automatically excluded.

### Issue: Stock levels not updating

**Cause**: Stock levels come from POS `STOCKCURRENT` table which is read-only from Laravel.

**Solution**: Stock updates must come from POS system sales/adjustments. Refresh the page to see latest values.

## Future Enhancements

This feature is under active development. Planned improvements include:

### Short Term
- [ ] CSV export of stock check results
- [ ] Filter products by status (show only low stock)
- [ ] Bulk threshold update for multiple suppliers
- [ ] Remember expanded state across page loads

### Medium Term
- [ ] Sales-based threshold calculation (based on weekly sales velocity)
- [ ] Historical tracking of stock check results
- [ ] Email notifications when products fall below threshold
- [ ] Integration with existing Order Management system

### Long Term
- [ ] Predictive ordering suggestions based on sales trends
- [ ] Automatic threshold adjustment based on seasonality
- [ ] Supplier lead time consideration
- [ ] Direct order creation from stock check results

## Related Documentation

- [Supplier Management](./supplier-management.md) - Core supplier system
- [POS Integration](./pos-integration.md) - How Laravel connects to POS database
- [Order Management](./order-management/) - Full ordering system (different from Order Manager)
- [Product Management](./product-management.md) - Product and stock management
