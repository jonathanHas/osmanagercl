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

### Process Barcode Scan

Process a barcode scan and update delivery item quantities.

```http
POST /deliveries/{delivery}/scan
Content-Type: application/json
```

**Request Body:**
```json
{
  "barcode": "8711521021925",
  "quantity": 1,
  "scanned_by": "User Name"
}
```

**Success Response:**
```json
{
  "success": true,
  "item": {
    "id": 156,
    "supplier_code": "19990B",
    "description": "Suma Hemp Oil & Vitamin E Soap 12x90g",
    "received_quantity": 1,
    "ordered_quantity": 1,
    "status": "complete"
  },
  "message": "Scanned: Suma Hemp Oil & Vitamin E Soap 12x90g (Total: 1/1)"
}
```

**Unknown Product Response:**
```json
{
  "success": false,
  "message": "Unknown product: 8711521021925",
  "barcode": "8711521021925"
}
```

### Adjust Item Quantity

Manually adjust received quantity for a delivery item.

```http
PATCH /deliveries/{delivery}/items/{item}/quantity
Content-Type: application/json
```

**Request Body:**
```json
{
  "quantity": 5,
  "action": "set"  // "set", "add", or "subtract"
}
```

**Response:**
```json
{
  "success": true,
  "item": {
    "id": 156,
    "received_quantity": 5,
    "status": "excess"
  }
}
```

### Get Delivery Statistics

Get real-time delivery progress statistics.

```http
GET /deliveries/{delivery}/stats
```

**Response:**
```json
{
  "total_items": 45,
  "complete_items": 38,
  "partial_items": 3,
  "missing_items": 4,
  "excess_items": 2,
  "completion_percentage": 85.5,
  "total_expected_value": 245.67,
  "total_received_value": 210.43
}
```

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

## Completion Operations

### Get Delivery Summary

Get comprehensive delivery summary with discrepancies.

```http
GET /deliveries/{delivery}/summary
```

**Response:**
```json
{
  "summary": {
    "total_items": 45,
    "complete_items": 38,
    "partial_items": 3,
    "missing_items": 4,
    "excess_items": 2,
    "unmatched_scans": 1,
    "total_expected_value": 245.67,
    "total_received_value": 210.43,
    "discrepancies": [
      {
        "code": "ABC123",
        "description": "Product Name",
        "ordered": 5,
        "received": 3,
        "difference": -2,
        "value_difference": -15.50
      }
    ]
  }
}
```

### Complete Delivery

Mark delivery as completed and update POS stock levels.

```http
POST /deliveries/{delivery}/complete
```

**Response:**
```json
{
  "success": true,
  "message": "Delivery completed successfully",
  "processed_items": 38,
  "skipped_new_products": 7
}
```

### Export Discrepancies

Export delivery discrepancies for supplier reconciliation.

```http
GET /deliveries/{delivery}/export-discrepancies
```

**Response:**
```json
{
  "delivery_number": "DEL-20250804-142301",
  "supplier": "Independent Irish Health Foods",
  "export_date": "2025-08-04T15:30:00Z",
  "discrepancies": [
    {
      "supplier_code": "ABC123",
      "description": "Product Name",
      "ordered_quantity": 5,
      "received_quantity": 3,
      "difference": -2,
      "unit_cost": 7.75,
      "value_impact": -15.50,
      "status": "partial"
    }
  ],
  "summary": {
    "total_discrepancies": 7,
    "total_value_impact": -45.75
  }
}
```

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

**Last Updated**: 2025-08-04  
**API Version**: v1.0  
**Framework**: Laravel 12