# Stocking Scanner

The Stocking Scanner is a mobile-first tool designed for use in the store room. It allows staff to scan product barcodes, view current stock levels, and make adjustments directly from a handheld device.

## Overview

**Purpose**: Help store room staff decide which products need to go to the shop floor by showing current stock levels.

**Example Workflow**:
1. User scans a product barcode
2. System displays "10 in stock"
3. User counts 9 items in store room
4. User deduces 1 is on the shop floor
5. User decides whether to take more out

## Features

### Stock Level Display
- Scan any product barcode to see its current stock level
- Large, prominent stock number for easy reading
- Product name and category displayed for context

### Stock Adjustment
- Adjust stock levels directly using +/- buttons or number input
- Changes are immediately saved to the POS database (STOCKCURRENT table)
- All adjustments are logged with full audit trail

### Add to Label Queue
- Quick button to add the scanned product to the label print queue
- Uses the existing label system (`LabelLog::logRequeueLabel()`)
- Product will appear in the "Products Needing Labels" section at `/labels`

### Keyboard Toggle
- By default, the mobile keyboard is hidden (for barcode scanner use)
- Tap the keyboard icon to enable manual entry
- Button highlights blue when keyboard mode is enabled

### Scan History
- Last 10 scanned products are saved locally (localStorage)
- Shows product name and stock level
- Persists across page refreshes
- Can be cleared with "Clear" button

## Access

### Stocking Scanner
- **URL**: `/stocking`
- **Sidebar**: Stock > Stocking
- **Access**: All authenticated users (except barista role)

### Stock Adjustment Logs
- **URL**: `/stocking/logs`
- **Sidebar**: Administration > Stock Logs
- **Access**: Admin role only

## Stock Adjustment Logs

The Stock Adjustment Logs page provides a complete audit trail of all stock changes made through the Stocking Scanner.

### Features
- **Date/Time**: When the adjustment was made
- **User**: Who made the adjustment
- **Barcode**: Product barcode (links to product detail page)
- **Old Stock**: Stock level before adjustment
- **New Stock**: Stock level after adjustment
- **Change**: Difference (green for increases, red for decreases)
- **Source**: Where the adjustment was made (e.g., "stocking")

### Filtering
- **Search Barcode**: Find adjustments for a specific product
- **User Filter**: View adjustments by a specific user
- **Date Range**: Filter by from/to dates

## Technical Details

### Database

**Table**: `stock_adjustments`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| barcode | string | Product barcode |
| product_id | string | Product ID (UUID) |
| old_stock | decimal(10,2) | Stock before adjustment |
| new_stock | decimal(10,2) | Stock after adjustment |
| adjustment | decimal(10,2) | Difference (new - old) |
| user_id | bigint | User who made adjustment |
| source | string | Source of adjustment (default: 'stocking') |
| created_at | timestamp | When adjustment was made |
| updated_at | timestamp | Last update time |

### Files

**Controller**: `app/Http/Controllers/StockingController.php`
- `index()` - Display scanner page
- `lookup()` - Look up product by barcode (AJAX)
- `updateStock()` - Update stock level (AJAX)
- `logs()` - Display adjustment logs (admin only)

**Model**: `app/Models/StockAdjustment.php`
- Tracks all stock adjustments with user relationship

**Views**:
- `resources/views/stocking/index.blade.php` - Scanner interface
- `resources/views/stocking/logs.blade.php` - Adjustment logs

**Routes**:
```php
Route::get('/stocking', [StockingController::class, 'index'])->name('stocking.index');
Route::post('/stocking/lookup', [StockingController::class, 'lookup'])->name('stocking.lookup');
Route::post('/stocking/update-stock', [StockingController::class, 'updateStock'])->name('stocking.update-stock');
Route::get('/stocking/logs', [StockingController::class, 'logs'])->name('stocking.logs')->middleware('role:admin');
```

### Integration Points

- **POS Database**: Reads from and writes to `STOCKCURRENT` table
- **Label System**: Integrates with `LabelLog` for adding products to print queue
- **User System**: Uses Laravel authentication for user tracking

## Related Documentation

- [Label System](./label-system.md)
- [Product Management](./product-management.md)
- [Categories Management](./categories-management.md)
