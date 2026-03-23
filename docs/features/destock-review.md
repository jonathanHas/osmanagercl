# Destock Review & Audit

The Destock Review system provides an audit trail for all destock/restock actions and an intelligent suggestions page that identifies destocked products with ongoing sales activity.

## Overview

**Problem**: Products could be destocked (removed from stock management) with no record of who did it, when, or why. Some products were incorrectly destocked and continued to sell, but there was no way to identify them.

**Solution**: Two-part system:
1. **Audit logging** on every destock/restock action
2. **Restock Suggestions** page that cross-references destocked products against sales data

## Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/destock-review` | GET | Audit log of destock/restock actions |
| `/destock-review/suggestions` | GET | Restock suggestions based on sales data |

## Features

### Audit Log (`/destock-review`)

Every destock or restock action is recorded in the `destock_audits` table with:
- **Barcode** and **product name**
- **Action**: `destock` or `restock`
- **User**: Who performed the action
- **Source**: Where the action was triggered from (Order Review, Product Page, Destock Review, etc.)
- **Timestamp**

Filters: date range, action type, user.

### Restock Suggestions (`/destock-review/suggestions`)

Identifies products that are NOT in the `stocking` table (destocked) but have recorded sales in the `sales_daily_summary` table over a configurable period.

**Filters & Controls:**
- **Search**: Filter by product name or barcode
- **Sales Period**: 7 days to 1 year (default: 30 days)
- **Min Units Sold**: Minimum sales threshold (default: 1)
- **Supplier**: Filter to a specific supplier
- **Exclude F&V**: Hides fruit & vegetable categories (SUB1/SUB2/SUB3) since their orders don't use stocking
- **Sort By**: Units sold, revenue, days with sales, or last sale date

**Per-Product Actions:**
- **Sales Chart**: Opens the sales history modal (weekly/daily chart)
- **View Product**: Links to the product detail page
- **Supplier Link**: For Udea and Independent products, the supplier name links to the product on the supplier's website
- **Restock Button**: One-click to add back to stock management (calls `POST /products/{id}/toggle-stocking`)
- **Product Image**: Thumbnail from supplier CDN with hover preview

## Data Flow

### Audit Logging
1. User clicks Destock/Restock button (on order review, product page, or destock review)
2. `ProductController::toggleStocking()` adds/removes from POS `stocking` table
3. Creates `DestockAudit` record with barcode, product name, action, user, and source

### Restock Suggestions
1. Fetches all currently stocked barcodes from POS `stocking` table
2. Queries `sales_daily_summary` for products NOT in stocked list, within date range
3. Applies filters (F&V exclusion, supplier, search, min units)
4. Groups by product, aggregates units/revenue/days
5. Loads Product models with supplier relationships for images and links

## Technical Details

### Database

**Table: `destock_audits`** (Laravel database)
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| barcode | string | Product barcode |
| product_name | string | Product name at time of action |
| action | string | `destock` or `restock` |
| user_id | foreignId | User who performed the action |
| source | string(50), nullable | Context: `order_review`, `order_review_christmas`, `product_show`, `destock_review` |
| created_at | timestamp | When the action occurred |
| updated_at | timestamp | |

Indexes: `barcode`, `user_id`, `action`, `created_at`

### Key Files

| File | Purpose |
|------|---------|
| `app/Models/DestockAudit.php` | Audit record model |
| `app/Http/Controllers/DestockReviewController.php` | Index (audit log) and suggestions methods |
| `resources/views/destock-review/index.blade.php` | Audit log view |
| `resources/views/destock-review/suggestions.blade.php` | Restock suggestions view |
| `database/migrations/2026_03_22_*_create_destock_audits_table.php` | Migration |

### Modified Files

| File | Change |
|------|--------|
| `app/Http/Controllers/ProductController.php` | Added `DestockAudit::create()` in `toggleStocking()` |
| `resources/views/orders/partials/review-table.blade.php` | Added `source: 'order_review'` to fetch body |
| `resources/views/orders/partials/review-table-christmas.blade.php` | Added `source: 'order_review_christmas'` to fetch body |
| `resources/views/products/show.blade.php` | Added `source: 'product_show'` to fetch body |
| `routes/web.php` | Added destock-review routes |
| `resources/views/layouts/admin.blade.php` | Added sidebar link under STOCK |

### Dependencies

- `sales_daily_summary` table (pre-aggregated sales data)
- POS `stocking` table (current stocking status)
- POS `supplier_link` table (supplier filtering)
- `SupplierService` (supplier website links and product images)
- `<x-sales-chart-modal />` component (sales history charts)
- `<x-product-image />` component (product thumbnails)
