# Product Management API Endpoints

## Overview

This document describes the HTTP endpoints available for product management operations in OSManager CL. All endpoints require authentication and return responses in HTML format (server-side rendered) unless otherwise specified.

## Authentication

All product endpoints require user authentication via Laravel Breeze session authentication.

## Endpoints

### Product Listing

#### `GET /products`
Lists products with optional search and filtering capabilities.

**Parameters:**
- `search` (string, optional) - Search products by name, code, or reference
- `active_only` (boolean, optional) - Filter to show only active (non-service) products
- `stocked_only` (boolean, optional) - Filter to show only products in stocking management
- `in_stock_only` (boolean, optional) - Filter to show only products with current stock
- `show_stats` (boolean, optional) - Include product statistics in response
- `supplier_id` (string, optional) - Filter by specific supplier
- `category_id` (string, optional) - Filter by product category
- `show_suppliers` (boolean, optional) - Include supplier information
- `per_page` (integer, optional) - Number of products per page (default: 20)

**Response:** HTML page with paginated product listing

**Example:**
```
GET /products?search=apple&active_only=1&per_page=10
```

---

### Product Details

#### `GET /products/{id}`
Display detailed information for a specific product.

**Parameters:**
- `id` (string, required) - Product UUID
- `from_delivery` (string, optional) - Delivery ID for context-aware navigation

**Response:** HTML page with product details, sales history, and management options

**Example:**
```
GET /products/123e4567-e89b-12d3-a456-426614174000?from_delivery=456
```

---

### Product Creation

#### `GET /products/create`
Display the product creation form.

**Parameters:**
- `delivery_item` (integer, optional) - Delivery item ID for pre-population

**Response:** HTML form for creating new products

#### `POST /products`
Create a new product with enhanced VAT handling.

**Request Body:**
```json
{
    "name": "Product Name",
    "code": "PROD001", 
    "reference": "REF001",
    "price_buy": 10.50,
    "price_sell": 15.75,
    "tax_category": "tax-category-id",
    "category": "category-id",
    "supplier_id": "supplier-id",
    "supplier_code": "SUP-CODE-001", 
    "units_per_case": 12,
    "supplier_cost": 10.00,
    "include_in_stocking": true,
    "delivery_item_id": 123
}
```

**Enhanced VAT Handling (2025):**
- **Automatic VAT Conversion**: The `price_sell` field is expected to include VAT
- **Database Storage**: System automatically converts to VAT-exclusive price for storage
- **Tax Integration**: Uses selected `tax_category` to determine VAT rate for conversion
- **Consistent Pricing**: Ensures PRICESELL field always contains net (ex-VAT) prices

**VAT Conversion Logic:**
```php
// System automatically converts VAT-inclusive to VAT-exclusive
$taxCategory = TaxCategory::with('primaryTax')->find($request->tax_category);
$vatRate = $taxCategory?->primaryTax?->RATE ?? 0.0;
$priceExVat = $vatRate > 0 ? $request->price_sell / (1 + $vatRate) : $request->price_sell;
```

**Validation Rules:**
- `name`: required, string, max 255 characters
- `code`: required, string, max 50 characters, unique in POS database
- `price_buy`: required, numeric, min 0, max 999999.99
- `price_sell`: required, numeric, min 0, max 999999.9999
- `tax_category`: required, string, must exist in TAXCATEGORIES table
- `include_in_stocking`: boolean, defaults to true

**Response:** Redirect to product detail page with success message

---

### Product Updates

#### `PATCH /products/{id}/name`
Update a product's name.

**Request Body:**
```json
{
    "product_name": "Updated Product Name"
}
```

**Validation:** Required string, max 255 characters

**Response:** Redirect to product detail page with success message

#### `PATCH /products/{id}/tax`
Update a product's tax category.

**Request Body:**
```json
{
    "tax_category": "new-tax-category-id"
}
```

**Validation:** Required string, must exist in TAXCATEGORIES table

**Response:** Redirect to product detail page with success message

#### `PATCH /products/{id}/price`
Update a product's selling price with enhanced dual input mode support.

**Request Body (Net Price Mode):**
```json
{
    "price_input_mode": "net",
    "net_price": 19.99
}
```

**Request Body (Gross Price Mode):**
```json
{
    "price_input_mode": "gross", 
    "gross_price": 24.59,
    "final_net_price": 19.99
}
```

**Enhanced Features (2025):**
- **Dual Input Modes**: Accept either gross (inc VAT) or net (ex VAT) prices
- **Automatic VAT Conversion**: System converts gross prices to net for storage
- **JavaScript Integration**: Enhanced price editor modal with real-time calculations
- **Validation**: Dynamic validation based on input mode
- **Tax Rate Integration**: Uses product's tax category for accurate VAT calculations

**Validation Rules:**
- `price_input_mode`: Required, must be 'gross' or 'net'
- `net_price`: Required if mode is 'net', numeric, min 0, max 999999.9999
- `gross_price`: Required if mode is 'gross', numeric, min 0, max 999999.9999  
- `final_net_price`: Optional fallback for JavaScript-calculated values

**Response:** Redirect to product detail page with success message indicating input mode used

#### `PATCH /products/{id}/cost`
Update a product's cost price.

**Request Body:**
```json
{
    "cost_price": 12.50
}
```

**Validation:** Required numeric, min 0, max 999999.99

**Response:** Redirect to product detail page with success message

---

### Stocking Management

#### `POST /products/{id}/toggle-stocking`
Toggle a product's inclusion in stocking management operations.

**Request Body:**
```json
{
    "include_in_stocking": true
}
```

**Validation:** Required boolean

**Response:** JSON response
```json
{
    "success": true,
    "message": "Product added to stock management",
    "is_stocked": true
}
```

**Error Response:**
```json
{
    "error": "Product not found"
}
```

---

### Sales Data

#### `GET /products/{id}/sales-data`
Retrieve sales history data for a product (AJAX endpoint).

**Parameters:**
- `period` (string, optional) - Time period: "4", "6", "12", "ytd" (default: "4")

**Response:** JSON response
```json
{
    "salesHistory": [
        {
            "month": "2024-01",
            "quantity": 150,
            "revenue": 2250.00
        }
    ],
    "salesStats": {
        "total_quantity": 500,
        "total_revenue": 7500.00,
        "average_monthly": 125
    }
}
```

---

### Pricing Integration

#### `GET /products/{id}/refresh-udea-pricing`
Refresh UDEA supplier pricing for a product (AJAX endpoint).

**Response:** JSON response
```json
{
    "success": true,
    "data": {
        "customer_price": 15.99,
        "case_price": 191.88,
        "units_per_case": 12
    },
    "product": {
        "current_price": 14.50,
        "current_price_with_vat": 17.83,
        "supplier_code": "UDEA-123"
    }
}
```

#### `GET /products/udea-pricing`
Get UDEA pricing data for a supplier code (AJAX endpoint).

**Parameters:**
- `supplier_code` (string, required) - Supplier product code

**Response:** JSON response with pricing data

---

### Label Operations

#### `GET /products/{id}/print-label`
Generate and return a printable label for the product.

**Parameters:**
- `template_id` (integer, optional) - Label template ID

**Response:** HTML content suitable for printing

---

## Error Handling

### HTTP Status Codes
- `200` - Success
- `302` - Redirect (typical for form submissions)
- `404` - Product not found
- `422` - Validation error
- `500` - Server error

### Error Response Format
For AJAX endpoints:
```json
{
    "error": "Error message description",
    "errors": {
        "field_name": ["Validation error message"]
    }
}
```

For form submissions, errors are returned via Laravel's session flash data and displayed on the redirected page.

## Rate Limiting

Standard Laravel rate limiting applies:
- 60 requests per minute for authenticated users
- Additional throttling on search endpoints to prevent abuse

## Examples

### Creating a Product
```bash
curl -X POST /products \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: csrf-token-here" \
  -d '{
    "name": "Organic Apples",
    "code": "ORG-APPLE-001", 
    "price_buy": 2.50,
    "price_sell": 4.99,
    "tax_category": "standard-rate",
    "include_in_stocking": true
  }'
```

### Updating Product Name
```bash
curl -X PATCH /products/123e4567-e89b-12d3-a456-426614174000/name \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: csrf-token-here" \
  -d '{"product_name": "Premium Organic Apples"}'
```

### Toggle Stocking Status
```bash
curl -X POST /products/123e4567-e89b-12d3-a456-426614174000/toggle-stocking \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: csrf-token-here" \
  -d '{"include_in_stocking": false}'
```

## Related Documentation

- [Product Management Feature Guide](../features/product-management.md)
- [POS Integration](../features/pos-integration.md)
- [Authentication](../features/authentication.md)