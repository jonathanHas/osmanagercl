# Kitchen Supplier Orders & Standing Order

Build, confirm and export a kitchen ingredient order to one supplier at a time, keep a history of what was ordered, and hold a standing weekly quantity per product that pre-fills each new order.

## Overview

This system enables you to:
- Create an order for one supplier from the kitchen products list, with product images, supplier code, case size and the last three orders beside each product
- Enter quantities in **cases** (the supplier's case size comes from `supplier_link.CaseUnits`; single-unit items are case size 1)
- Confirm the order, which logs it, then download a CSV to send to the supplier
- Re-download any past order's CSV from the order history
- Keep a **standing weekly order**: case quantities per product that are pre-filled every time the create page opens

Nothing is sent to a supplier, emailed, or logged automatically. The standing order is a pre-fill only; every order is reviewed and confirmed by a person.

## Features

### Create Order

Access at `/kitchen/orders/create`, or via the **Create Order** button on `/kitchen/products`.

- **Supplier picker**: lists every supplier that has kitchen products, with the count, e.g. "Udea (74)". The supplier with the most kitchen products is selected by default. Changing the select reloads the page.
- **One row per kitchen product** linked to the selected supplier, sorted by name (a flat list, so the browser's find works across all rows):

| Column | Description |
|--------|-------------|
| Image | POS product image if one exists, otherwise the supplier CDN image (Udea by barcode, Independent by supplier code); hover/tap for a large preview |
| Product | Name, barcode, current shop stock; an amber "no supplier code" badge when the link has no code |
| Supplier code | From `supplier_link.SupplierCode` |
| Case size | "N units/case", or "single" when the case size is 1 |
| Last 3 orders | Up to three chips "qty × date", newest first, for **this supplier only** |
| Qty (cases) | −/+ buttons and a number input, 0–999. Pre-filled from the standing order, with a "standing" badge |

- **Sticky footer** shows "N products · M cases" live, a **Clear all** button, and **Confirm Order**, which is disabled while the total is 0.
- **Notes** (optional, up to 2000 characters) are stored on the order.

Products that are not on the kitchen list, or have no supplier link, cannot be ordered here.

### Confirm & Download

Confirming creates a `kitchen_orders` row and one `kitchen_order_items` row per product with a quantity above 0. Each line is a **server-side snapshot** of the POS data at that moment: supplier code, product name and case size are copied from the database, never from the form, so a CSV re-downloaded months later is identical even if POS data has changed.

You land on `/kitchen/orders/{id}` with a **Download CSV** button, the order metadata, and the lines in the same columns as the CSV. There is no CSV of an unconfirmed draft.

### CSV format

File name: `kitchen-order-{supplier-slug}-{YYYY-MM-DD}-{id}.csv`

Header row, exactly:

```
Quantity,Supplier Code,Product Name,Case Size
```

One row per line, sorted by product name. Fields are quoted only when they contain a comma, a quote or a line break. Lines with quantity 0 are never written. Example:

```
Quantity,Supplier Code,Product Name,Case Size
3,6000562,AGF tas TGTG paper bag big,1
2,110243,"Olives, green, pitted 1kg",6
```

### Order History

`/kitchen/orders` lists every confirmed order, newest first, 25 per page, with a supplier filter. Each row shows the date, supplier, line count, total cases, who confirmed it and a truncated note, with **View** and **CSV** links.

### Standing Weekly Order

`/kitchen/standing-order` lists **every** kitchen product grouped by supplier, with a weekly case quantity input per product. Saving stores quantities above 0 and removes rows set to 0. Products with no supplier link or whose POS product row is missing are shown greyed at the bottom and cannot be given a quantity.

Standing quantities are keyed by the POS product id, not `kitchen_products.id`, so they survive a product being removed from and re-added to the kitchen list.

### Data edge cases

| Situation | Behaviour |
|-----------|-----------|
| `CaseUnits` empty or 0 | Treated as 1 (normalised in the service, never in the view) |
| `SupplierCode` empty | Orderable; badge on the row; blank in the CSV |
| No supplier link | Never orderable; greyed on the standing page |
| POS product row missing | Skipped on the create page; greyed on the standing page |
| Barcode with several `supplier_link` rows | The link with the selected `SupplierID` is used |

## Database Schema

All three tables live in the Laravel database. Nothing is written to the POS database.

### kitchen_orders

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint nullable | Who confirmed the order (no FK, so history survives user deletion) |
| supplier_id | varchar(20) | POS `suppliers.SupplierID` (a string) |
| supplier_name | varchar(100) | Snapshot of the supplier name |
| notes | text nullable | Free text |
| total_cases | int unsigned | Sum of line quantities |
| line_count | int unsigned | Number of lines |
| created_at / updated_at | timestamp | `created_at` is the order moment |

Indexes: `user_id`, `supplier_id`, `(supplier_id, created_at)`.

### kitchen_order_items

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| kitchen_order_id | bigint | FK → `kitchen_orders.id`, cascade on delete |
| product_id | varchar(36) | POS `PRODUCTS.ID` (UUID) |
| supplier_code | varchar(50) nullable | Snapshot |
| product_name | varchar(255) | Snapshot |
| case_units | int unsigned | Snapshot, ≥ 1 |
| quantity | int unsigned | Cases |
| created_at / updated_at | timestamp | |

Unique: `(kitchen_order_id, product_id)`.

### kitchen_standing_order_items

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| product_id | varchar(36) | POS `PRODUCTS.ID`, unique |
| quantity | int unsigned | Weekly cases |
| updated_by | bigint nullable | Last user to save |
| created_at / updated_at | timestamp | |

No `supplier_id`: the supplier is derived from the product's current link when the create page opens.

## API Endpoints

All routes are inside the `auth` middleware group under the `kitchen` prefix, registered before the `/{recipe}` wildcard.

| Method | Endpoint | Route name | Description |
|--------|----------|------------|-------------|
| GET | `/kitchen/orders` | `kitchen.orders.index` | Order history, `?supplier=` filter |
| GET | `/kitchen/orders/create` | `kitchen.orders.create` | Create page, `?supplier=` (default: most kitchen products) |
| POST | `/kitchen/orders` | `kitchen.orders.store` | Confirm. Body: `supplier_id`, `notes`, `qty[product_id]` |
| GET | `/kitchen/orders/{kitchenOrder}` | `kitchen.orders.show` | Confirmed order |
| GET | `/kitchen/orders/{kitchenOrder}/csv` | `kitchen.orders.csv` | CSV download |
| GET | `/kitchen/standing-order` | `kitchen.standing-order.edit` | Standing order page |
| PUT | `/kitchen/standing-order` | `kitchen.standing-order.update` | Save. Body: `qty[product_id]` |

Validation: `qty.*` must be an integer 0–999; `notes` up to 2000 characters. Posted product ids that are not kitchen products of the chosen supplier are ignored. Confirming with no quantity above 0 redirects back with an error.

## Files

### Models
- `app/Models/KitchenOrder.php` — order header; `items()`, `user()`, `supplier()`, `csvFilename()`
- `app/Models/KitchenOrderItem.php` — snapshotted line
- `app/Models/KitchenStandingOrderItem.php` — standing weekly quantity

### Services
- `app/Services/KitchenOrderService.php` — supplier options, products for a supplier (with pre-resolved image URLs), last-3 history, order creation, CSV, standing order

### Controllers & Requests
- `app/Http/Controllers/KitchenOrderController.php`
- `app/Http/Requests/StoreKitchenOrderRequest.php`
- `app/Http/Requests/UpdateKitchenStandingOrderRequest.php`

### Views
- `resources/views/kitchen/orders/create.blade.php`
- `resources/views/kitchen/orders/show.blade.php`
- `resources/views/kitchen/orders/index.blade.php`
- `resources/views/kitchen/orders/standing.blade.php`
- `resources/views/kitchen/orders/partials/product-row.blade.php` — shared row (create + standing)
- `resources/views/kitchen/orders/partials/qty-scripts.blade.php` — shared +/−, totals and Clear all JS

### Migrations
- `database/migrations/2026_09_16_100000_create_kitchen_orders_table.php`
- `database/migrations/2026_09_16_100001_create_kitchen_order_items_table.php`
- `database/migrations/2026_09_16_100002_create_kitchen_standing_order_items_table.php`

### Tests
- `tests/Feature/KitchenOrderRoutesTest.php`
- `tests/Feature/KitchenOrderCreatePageTest.php`
- `tests/Feature/KitchenOrderStoreTest.php`
- `tests/Feature/KitchenOrderCsvTest.php`
- `tests/Feature/KitchenStandingOrderTest.php`
- `tests/Feature/KitchenOrderHistoryTest.php`
- `tests/Concerns/CreatesKitchenOrderPosTables.php` — builds the POS tables in sqlite

## Usage Workflow

### Weekly order

1. Go to `/kitchen/products` and click **Create Order** (or the Kitchen Orders / Standing Order sidebar links)
2. Pick the supplier
3. Adjust the pre-filled standing quantities and add anything extra; the footer totals update as you type
4. Add a note if useful and press **Confirm Order**
5. On the order page press **Download CSV** and send the file to the supplier
6. Repeat for the next supplier

### Setting up the standing order

1. Go to `/kitchen/standing-order`
2. Enter the weekly case quantity beside each product you order every week; leave the rest at 0
3. Press **Save Standing Order**
4. The next time you open the create page those quantities are already filled in and marked "standing"

## Related Documentation

- [Kitchen Products](./kitchen-products.md) — the list of products these orders are built from
- [Kitchen Recipe Costing](./kitchen-recipe-costing.md) — recipe management and costing
- [Order Generation](./order-management/order-generation.md) — the shop's sales-driven ordering system (separate from kitchen orders)
