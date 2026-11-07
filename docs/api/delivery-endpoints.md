# Delivery API Endpoints

This document covers the REST API endpoints for the delivery verification system, including CSV import, scanning operations, and completion workflows.

## Overview

The Delivery API provides endpoints for managing supplier deliveries from initial CSV import through scanning verification to completion. It supports multi-format CSV processing (Udea and Independent Irish Health Foods formats) with real-time scanning capabilities.

## Authentication

All endpoints require authentication via Laravel's session-based authentication system.

```http
Cookie: laravel_session=...
X-CSRF-TOKEN: {{ csrf_token() }}
```

## Base URL

```
https://your-domain.com/
```

## Delivery Management

### Create Delivery (CSV Import)

Import a delivery from CSV file with automatic format detection.

```http
POST /deliveries
Content-Type: multipart/form-data
```

**Request Body:**
```
supplier_id: integer (required) - Supplier ID from database
delivery_date: date (optional) - Defaults to current date
csv_file: file (required) - CSV file (.csv, .txt, max 10MB)
```

**Supported CSV Formats:**

**Udea Format:**
```csv
Code,Ordered,Qty,SKU,Content,Description,Price,Sale,Total
115,1,1,6,"1 kilogram","Broccoli Biologisch",3.17,6.98,19.02
```

**Independent Irish Health Foods Format:**
```csv
Code,Product,Ordered,Qty,RSP,Price,Tax,Value
49036A,All About KombuchaRaspberry Can (Org)(DRS) 1x330ml,6,6,3.7,2.15,2.97,12.9
19990B,Suma Hemp Oil & Vitamin E Soap 12x90g,1/0,1/0,3.08,21.44,4.93,21.44
```

**Response:**
```json
{
  "success": true,
  "delivery": {
    "id": 17,
    "delivery_number": "DEL-20250804-142301",
    "supplier_id": 1,
    "delivery_date": "2025-08-04",
    "status": "draft",
    "total_expected": 245.67,
    "import_data": {
      "filename": "combined_iih_invoices.csv",
      "format": "independent",
      "imported_at": "2025-08-04T14:23:01.000000Z"
    },
    "created_at": "2025-08-04T14:23:01.000000Z"
  },
  "redirect": "/deliveries/17"
}
```

**Error Response:**
```json
{
  "success": false,
  "errors": {
    "csv_file": ["The csv file field is required."],
    "supplier_id": ["The supplier id field is required."]
  }
}
```

### Get Delivery Details

Retrieve detailed delivery information including items and progress.

```http
GET /deliveries/{delivery}
```

**Response:**
```json
{
  "delivery": {
    "id": 17,
    "delivery_number": "DEL-20250804-142301",
    "supplier": {
      "SupplierID": 1,
      "Supplier": "Independent Irish Health Foods"
    },
    "status": "draft",
    "completion_percentage": 85.5,
    "total_expected": 245.67,
    "items": [
      {
        "id": 156,
        "supplier_code": "19990B",
        "description": "Suma Hemp Oil & Vitamin E Soap 12x90g",
        "unit_cost": 1.79,
        "total_cost": 21.44,
        "ordered_quantity": 1,
        "received_quantity": 0,
        "units_per_case": 12,
        "sale_price": 3.08,
        "tax_rate": 23.02,
        "normalized_tax_rate": 23.00,
        "status": "pending",
        "is_new_product": true,
        "product_id": null,
        "barcode": null
      }
    ]
  }
}
```

## Scanning Operations

Removed 2026-11-07. `/deliveries` no longer has scan, stats, adjust-quantity, summary, complete, cancel or export-discrepancies endpoints (its completion never wrote stock). Receiving uses the `delivery-legacy.*` routes documented in `docs/features/shop-mode.md` and `docs/features/delivery-system.md`.

## Product Creation Integration

### Create Product from Delivery Item

Create a new POS product from a delivery item with automatic tax category selection.

```http
GET /products/create?delivery_item={item_id}
```

**Response:** Returns HTML form with pre-populated data:
- Product name from delivery description
- Unit cost from delivery pricing (with case-to-unit conversion for Independent format)
- Supplier information
- **Automatic tax category selection** for Independent deliveries based on VAT rate
- Units per case extracted from product name

**Form Submission:**
```http
POST /products
Content-Type: application/x-www-form-urlencoded
```

**Pre-filled Independent Item Example:**
```
name: "Suma Hemp Oil & Vitamin E Soap 12x90g"
code: "" (to be filled by user)
price_buy: 1.79  (converted from case price ÷ units per case)
price_sell: 3.08 (from RSP field)
tax_category: "002" (auto-selected based on 23% VAT rate)
supplier_id: 1
supplier_code: "19990B"
units_per_case: 12
delivery_item_id: 156
```

## Docket Operations

### Detect Supplier from PDF

Automatically detect the supplier from uploaded PDF content.

```http
POST /deliveries/detect-supplier
Content-Type: multipart/form-data
```

**Request Body:**
```
pdf_file: file (required) - PDF file to analyze
```

**Success Response:**
```json
{
  "success": true,
  "supplier_id": 1,
  "supplier_name": "Independent Irish Health Foods",
  "detected_name": "INDEPENDENT IRISH HEALTH FOODS"
}
```

**No Match Response:**
```json
{
  "success": true,
  "supplier_id": null,
  "supplier_name": null,
  "detected_name": "Unknown Supplier Name"
}
```

**Error Response:**
```json
{
  "success": false,
  "error": "Failed to parse PDF"
}
```

**Supported Suppliers:**
| Detected Text | Supplier ID | Supplier Name |
|---------------|-------------|---------------|
| `INDEPENDENT IRISH HEALTH FOODS`, `IIHF` | 1 | Independent Irish Health Foods |
| `UDEA B.V.`, `WWW.UDEA.NL`, `UDEA` | 5 | UDEA |
| `MOSSFIELD` | 3 | Mossfield |

**Notes:**
- Detection uses case-insensitive partial matching
- Parses first few pages of PDF for supplier identification
- Returns null supplier_id if no match found (user selects manually)

---

### Send to Receiving (`sync-legacy`)

Since 2026-11-07 the request takes `scan_session_id`: `new` (default) starts a scan session for the docket's supplier; an open session's id joins it. The lines are written to the POS `delivery` table tagged with that session (`delID`) instead of truncating the table, the docket records the session on `deliveries.scan_session_id`, and the response redirects to that session's Receiving match page. Re-posting without a session re-sends to the docket's own session. A completed session is refused (undo it first). The older description below predates this.


Sync delivery items to the POS `delivery` table for invoice comparison with scanned items.

```http
POST /deliveries/{delivery}/sync-legacy
```

**Description:**
Clears the POS `delivery` table and populates it with items from the specified Laravel delivery. This enables comparison with scanned items via the legacy `/delivery-legacy/match` interface.

**Request:** No body required (uses CSRF token from session)

**Success Response:**
```
HTTP/1.1 302 Found
Location: /delivery-legacy
```

With session flash message:
```
"Synced 45 items to legacy. Select a scan session to compare."
```

**Error Response:**
```json
{
  "errors": {
    "sync": ["Cannot sync empty delivery to legacy system."]
  }
}
```

Or:
```json
{
  "errors": {
    "sync": ["Failed to sync to legacy: Database connection error"]
  }
}
```

**Data Mapping:**
| Laravel `delivery_items` | POS `delivery` |
|--------------------------|----------------|
| `description` | `prodName` |
| `supplier_code` | `supCode` |
| `unit_cost` | `cost` |
| `units_per_case` | `caseUnits` |
| `ordered_quantity` | `myOrder` |
| `sale_price` | `rrPrice` |

**Notes:**
- Existing data in POS `delivery` table is cleared before sync
- Operation is wrapped in a database transaction
- Redirects to `/delivery-legacy` for scan session selection

## Format-Specific Features

### Independent Irish Health Foods

**VAT Rate Processing:**
- Automatic VAT rate calculation: `(Tax ÷ Value) × 100`
- Irish VAT normalization to standard rates: 0%, 9%, 13.5%, 23%
- Tax category auto-selection for product creation

**Case-to-Unit Conversion:**
- Price field represents case pricing
- Units per case extracted from product name (e.g., "12x90g" = 12 units)
- Unit cost = Case price ÷ Units per case

**Quantity Notation:**
- Supports "ordered/received" format: "6/5", "1/0"
- Handles partial deliveries and zero receipts

### Udea Format

**Standard Processing:**
- Price field represents unit pricing
- Direct quantity processing
- Standard tax handling

## Error Handling

### Common Error Codes

**400 Bad Request**
```json
{
  "error": "Invalid CSV format",
  "details": "Missing required headers: Code, Product, Price"
}
```

**404 Not Found**
```json
{
  "error": "Delivery not found",
  "delivery_id": 999
}
```

**422 Unprocessable Entity**
```json
{
  "errors": {
    "supplier_id": ["The supplier id field is required."],
    "csv_file": ["The csv file must be a file of type: csv, txt."]
  }
}
```

**500 Internal Server Error**
```json
{
  "error": "CSV import failed",
  "message": "Database connection error"
}
```

## Rate Limiting

API endpoints are subject to Laravel's default rate limiting:
- **Web routes**: 60 requests per minute per IP
- **Authenticated routes**: 1000 requests per minute per user

## Webhook Events

The system does not currently support webhooks, but the following events are logged internally:
- `delivery.created` - New delivery imported
- `delivery.item.scanned` - Item scanned
- `delivery.completed` - Delivery marked as complete
- `product.created_from_delivery` - Product created from delivery item

## Development & Testing

### Test Data

Use the included test CSV files:
- `/tests/fixtures/udea_delivery.csv` - Sample Udea format
- `/tests/fixtures/independent_delivery.csv` - Sample Independent format

### Debugging

Enable Laravel debugging to see detailed error messages:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

### Performance Monitoring

Monitor delivery processing performance:
```bash
# Check import processing time
tail -f storage/logs/laravel.log | grep "Delivery import"

# Monitor queue processing
php artisan queue:work --verbose
```

---

**Last Updated**: 2026-01-24
**API Version**: v1.2
**Framework**: Laravel 12