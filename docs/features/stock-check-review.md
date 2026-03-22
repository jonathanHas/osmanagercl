# Stock Check Review

The Stock Check Review is a tool for reviewing and reconciling physical stock checks by category. It highlights products that haven't been checked since a reference date, allows bulk zeroing of stale stock, and includes an integrated barcode scanner for performing stock checks.

## Overview

**Purpose**: After physically counting stock in a category, review which products were checked, identify items with stock that weren't counted, and zero out stale inventory.

**Example Workflow**:
1. User selects a category (e.g., "Nut Butter") and a reference date
2. System shows all products, grouped by status — items needing attention first
3. User opens the scanner modal to scan products as they count them
4. After scanning, the review page shows which items are now verified
5. User reviews unchecked items with stock (highlighted red) to decide if stock is accurate
6. User presses "Set to Zero" to zero out all unchecked products

## Features

### Category Review Table
- **Status Grouping**: Products grouped by attention needed — danger (unchecked with stock) and warning (negative stock) first, then verified/clear items
- **Color Coding**: Red = unchecked with positive stock, Yellow = negative stock, Green = zero stock (safe), White = verified (checked since reference date)
- **Product Images**: Thumbnails from database, Udea CDN, or Independent CDN with click-to-enlarge lightbox
- **Expandable Details** (mobile): Tap a product card to see barcode, supplier, cost, and stock value
- **Desktop Table**: Full 9-column table with hover image previews

### Summary Dashboard
- **Total Products**: Count of products in selected category
- **Checked Count**: Products verified since reference date, with progress bar
- **Needs Check**: Count of unchecked products with non-zero stock
- **Scan Button**: Quick-access button to open the scanner modal

### Stock Check Scanner
- **Fullscreen Modal**: Dark-themed scanner that opens over the review page
- **Barcode Input**: Physical scanner input or manual keyboard entry
- **Camera Scanning**: Phone camera barcode detection via `html5-qrcode` (requires HTTPS)
- **Product Display**: Shows product image, name, category, supplier, and current stock
- **Stock Update**: Optional — enter actual count to update stock in the same flow
- **Scan History**: Last 20 scans with timestamps
- **Audio Feedback**: Beep on successful camera detection

### Set to Zero
- **Bulk Operation**: Zeros `STOCKCURRENT.UNITS` for all products NOT checked since the reference date
- **Confirmation Modal**: Shows count of affected products, total value, and product list before executing
- **Audit Trail**: Records to both POS `catSetZero` table (backward compatibility) and Laravel `stock_zero_audits` table
- **Individual Logging**: Each zeroed product gets a `StockAdjustment` record with source `stock_review_zero`

### Set to Zero History
- **Combined Timeline**: Shows records from both old system (`catSetZero`) and new Laravel audits
- **AJAX Modal**: Opens in a modal overlay, loads on demand
- **Expandable Details**: New system records show individual products that were zeroed with old stock values

### Controls
- **Category Selection**: Dropdown with product counts, auto-submits on change
- **Reference Date**: Date picker (defaults to today)
- **Filter**: All products or stocked-only
- **Sort**: By product name, last checked (newest/oldest)
- **Collapsible on Mobile**: Controls collapse to a compact bar showing selected category

## Access

- **URL**: `/stock-review`
- **Sidebar**: Stock > Stock Review
- **Access**: All authenticated users (except barista role)
- **History**: Available via "History" button in page header
- **Audit Log**: `/stock-review/audit-log` (full-page view)

## Technical Details

### Database

**POS Tables Used** (read/write):
- `STOCKCURRENT` — Current stock levels (updated by set-to-zero and scanner stock updates)
- `stockLastChecked` — Last check date per product barcode (updated by scanner)
- `catSetZero` — Audit log of set-to-zero operations (legacy compatibility)
- `PRODUCTS`, `CATEGORIES`, `stocking`, `supplier_link`, `suppliers` — Read-only

**Laravel Tables**:

**`stock_zero_audits`** (created by migration):

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| category_id | string | POS category UUID |
| category_name | string | Category name snapshot |
| reference_date | date | Date threshold used |
| products_zeroed | integer | Count of products affected |
| total_stock_value_zeroed | decimal(10,2) | Total cost value zeroed |
| product_details | json | Array of {barcode, name, old_stock, cost_value} |
| user_id | bigint | User who performed the action |
| created_at | timestamp | When operation was performed |

**`stock_adjustments`** — Individual product adjustments (source: `stock_review_zero` or `stock_check`)

### Models

- `app/Models/StockLastChecked.php` — POS `stockLastChecked` table
- `app/Models/StockZeroAudit.php` — Laravel audit table

### Files

**Controller**: `app/Http/Controllers/StockCheckReviewController.php`
- `index()` — Main review page with category selection and product table
- `stockCheck()` — AJAX: scan barcode, mark as checked, optionally update stock
- `setToZero()` — POST: bulk zero unchecked products
- `salesData()` — AJAX: last 5 months sales data per product
- `history()` — AJAX: combined old + new set-to-zero history
- `auditLog()` — Full-page audit log view

**Service**: `app/Services/StockCheckReviewService.php`
- `getReviewData()` — Fetch and enrich products with status, grouping, and sorting
- `determineStatus()` — Classify product as verified/danger/warning/ok
- `computeSummary()` — Calculate dashboard statistics
- `setUncheckedToZero()` — Execute bulk zero with audit trail
- `getSalesHistory()` — Query STOCKDIARY for category sales

**Repository**: `app/Repositories/ProductRepository.php`
- `getProductsForStockReview()` — Efficient query with eager loading

**Views**:
- `resources/views/stock-review/index.blade.php` — Main review page
- `resources/views/stock-review/audit-log.blade.php` — Full audit log page

**Routes**:
```php
Route::get('/stock-review', ...)->name('stock-review.index');
Route::post('/stock-review/stock-check', ...)->name('stock-review.stock-check');
Route::post('/stock-review/set-to-zero', ...)->name('stock-review.set-to-zero');
Route::get('/stock-review/sales-data', ...)->name('stock-review.sales-data');
Route::get('/stock-review/history', ...)->name('stock-review.history');
Route::get('/stock-review/audit-log', ...)->name('stock-review.audit-log');
```

### Integration Points

- **POS Database**: Reads from and writes to `STOCKCURRENT`, `stockLastChecked`, `catSetZero`
- **Supplier Image Service**: Uses `SupplierService` for Udea/Independent product images
- **Barcode Scanner**: Uses `html5-qrcode` via `resources/js/barcode-scanner.js`
- **Stock Adjustments**: Reuses `StockAdjustment` model for individual audit trail
- **User System**: Laravel authentication for user tracking

## Related Documentation

- [Stocking Scanner](./stocking.md)
- [Product Management](./product-management.md)
- [POS Integration](./pos-integration.md)
