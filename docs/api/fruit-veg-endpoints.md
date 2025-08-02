# Fruit & Vegetables API Endpoints

## Overview

This document describes the API endpoints for the Fruit & Vegetables management system. All endpoints require authentication and are prefixed with `/fruit-veg`.

## Base URL
```
/fruit-veg
```

## Authentication
All endpoints require user authentication via Laravel's session-based auth middleware.

---

## Dashboard & Statistics

### GET /
**Description**: Main F&V dashboard with statistics and recent activity

**Response**:
```json
{
  "stats": {
    "total_fruits": 245,
    "total_vegetables": 419,
    "available_fruits": 23,
    "available_vegetables": 67,
    "needs_labels": 15,
    "recent_price_changes": 8
  },
  "recent_price_changes": [
    {
      "product_code": "F001",
      "product_name": "Organic Apples",
      "old_price": "2.50",
      "new_price": "2.75",
      "changed_at": "2024-01-15T10:30:00Z",
      "changed_by": 1
    }
  ]
}
```

---

## Availability Management

### GET /availability
**Description**: Display availability management page with pagination

**Parameters**:
- `search` (optional): Search term for product name, code, or display name
- `category` (optional): `all`, `fruit`, `vegetables`
- `availability` (optional): `all`, `available`, `unavailable`
- `per_page` (optional): Number of products per page (default: 50)

**Response**:
```json
{
  "products": [
    {
      "ID": "product-uuid-1",
      "CODE": "F001",
      "NAME": "Organic Apples",
      "DISPLAY": "Premium Red Apples",
      "PRICESELL": "2.50",
      "current_price": "2.75",
      "is_available": true,
      "category": {
        "ID": "SUB1",
        "NAME": "Fruit"
      },
      "veg_details": {
        "country_code": 1,
        "country": {
          "ID": 1,
          "country": "Ireland"
        },
        "unit_name": "kg"
      }
    }
  ],
  "hasMore": true
}
```

### POST /availability/toggle
**Description**: Toggle availability status for a single product

**Request Body**:
```json
{
  "product_code": "F001",
  "is_available": true
}
```

**Response**:
```json
{
  "success": true
}
```

**Side Effects**:
- Updates `veg_availability` table
- Adds product to print queue if marked available
- Sets current price to product's gross price

### POST /availability/bulk
**Description**: Update availability for multiple products simultaneously

**Request Body**:
```json
{
  "product_codes": ["F001", "F002", "V003"],
  "is_available": true
}
```

**Response**:
```json
{
  "success": true
}
```

**Side Effects**:
- Updates `veg_availability` table for all specified products
- Adds products to print queue if marked available

---

## Product Search

### GET /search
**Description**: AJAX endpoint for real-time product search with pagination

**Parameters**:
- `search` (required): Search term (minimum 2 characters)
- `category` (optional): `all`, `fruit`, `vegetables`
- `availability` (optional): `all`, `available`, `unavailable`
- `offset` (optional): Starting offset for pagination (default: 0)
- `limit` (optional): Number of results to return (default: 50, max: 100)

**Validation**:
- `search`: required|string|min:2|max:100
- `category`: nullable|string|in:all,fruit,vegetables
- `availability`: nullable|string|in:all,available,unavailable
- `offset`: nullable|integer|min:0
- `limit`: nullable|integer|min:1|max:100

**Response**:
```json
{
  "products": [
    {
      "ID": "product-uuid",
      "CODE": "F001", 
      "NAME": "Organic Apples",
      "DISPLAY": "Premium Red Apples",
      "current_price": "2.75",
      "is_available": true,
      "category": {
        "ID": "SUB1",
        "NAME": "Fruit"
      },
      "veg_details": {
        "country_code": 1,
        "country": {
          "ID": 1,
          "country": "Ireland"
        },
        "unit_name": "kg"
      }
    }
  ],
  "hasMore": true,
  "total": 156
}
```

---

## Price Management

### GET /prices
**Description**: Display price management interface for available products only

**Response**:
```json
{
  "products": [
    {
      "ID": "product-uuid",
      "CODE": "F001",
      "NAME": "Organic Apples", 
      "current_price": "2.75",
      "category": {
        "NAME": "Fruit"
      },
      "veg_details": {
        "country": {
          "country": "Ireland"
        }
      }
    }
  ]
}
```

### POST /prices/update
**Description**: Update price for an available product

**Request Body**:
```json
{
  "product_code": "F001",
  "new_price": 2.95
}
```

**Validation**:
- `product_code`: required|string
- `new_price`: required|numeric|min:0

**Response**:
```json
{
  "success": true
}
```

**Side Effects**:
- Updates `veg_availability.current_price`
- Logs change to `veg_price_history`
- Adds product to print queue with reason "price_change"

**Error Responses**:
```json
{
  "error": "Product not in availability list"
}
```

---

## Label Management

### GET /labels
**Description**: Display label printing interface with products needing labels

**Response**:
```json
{
  "products": [
    {
      "CODE": "F001",
      "NAME": "Organic Apples",
      "current_price": "2.75",
      "category": {
        "NAME": "Fruit"
      },
      "veg_details": {
        "country": {
          "country": "Ireland"
        }
      }
    }
  ]
}
```

### GET /labels/preview
**Description**: Preview labels before printing

**Parameters**:
- `products[]` (optional): Array of product codes to preview. If empty, previews all queued products.

**Response**: HTML preview of labels ready for printing

### POST /labels/printed
**Description**: Mark labels as printed and remove from print queue

**Request Body**:
```json
{
  "products": ["F001", "F002"]
}
```

**Response**:
```json
{
  "success": true
}
```

**Side Effects**:
- Removes specified products from `veg_print_queue`
- If no products specified, clears entire print queue

---

## Product Data Management

### POST /display/update
**Description**: Update product display name

**Request Body**:
```json
{
  "product_code": "F001",
  "display": "Premium Organic Red Apples"
}
```

**Validation**:
- `product_code`: required|string
- `display`: nullable|string|max:255

**Response**:
```json
{
  "success": true
}
```

**Side Effects**:
- Updates `PRODUCTS.DISPLAY` in POS database
- Adds product to print queue with reason "display_updated"

### POST /country/update
**Description**: Update product country of origin

**Request Body**:
```json
{
  "product_code": "F001",
  "country_id": 1
}
```

**Validation**:
- `product_code`: required|string
- `country_id`: required|integer|exists:App\Models\Country,ID

**Response**:
```json
{
  "success": true
}
```

**Side Effects**:
- Updates or creates `vegDetails` record
- Adds product to print queue with reason "country_updated"

### GET /countries
**Description**: Get list of all countries for dropdown selection

**Response**:
```json
[
  {
    "ID": 1,
    "country": "Ireland"
  },
  {
    "ID": 2,
    "country": "Spain"
  }
]
```

---

## Image Management

### GET /product-image/{code}
**Description**: Serve product image from database

**Parameters**:
- `code` (path): Product code

**Response**: 
- **Content-Type**: `image/jpeg` or `image/png`
- **Cache-Control**: `public, max-age=86400` (24 hours)
- **Body**: Image binary data or 1x1 transparent PNG if no image

**Error Handling**:
- Missing product: Returns transparent placeholder image
- Missing image data: Returns transparent placeholder image

---

## Error Responses

### Standard Error Format
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": [
      "Validation error message"
    ]
  }
}
```

### Common HTTP Status Codes
- **200**: Success
- **400**: Bad Request (validation errors)
- **401**: Unauthorized (not authenticated)
- **404**: Not Found (product doesn't exist)
- **422**: Unprocessable Entity (validation failed)
- **500**: Internal Server Error

---

## Rate Limiting

Currently no specific rate limiting implemented beyond Laravel's default throttling.

## CSRF Protection

All POST requests require CSRF token in header:
```
X-CSRF-TOKEN: {csrf_token}
```

## Database Relationships

### Key Models Used
- **Product**: Main product data from POS database
- **VegDetails**: Product details (country, class, unit)
- **Country**: Country master data
- **Category**: Product categories
- **VegPrintQueue**: Print queue management

### Data Sources
- **POS Database**: Products, categories, images, vegDetails
- **Laravel Database**: Availability, price history, print queue

## Performance Notes

### Optimizations Implemented
- **Pagination**: Default 50 products per request
- **Eager Loading**: Relationships loaded efficiently
- **Image Caching**: 24-hour cache headers
- **Search Debouncing**: Frontend implements 500ms debounce

### Query Efficiency
- Uses `EXISTS` queries for efficient filtering
- Indexed searches on product codes
- Minimal N+1 query issues through proper eager loading

## Integration Examples

### JavaScript/AJAX Usage
```javascript
// Search products
const searchProducts = async (searchTerm) => {
  const params = new URLSearchParams({
    search: searchTerm,
    category: 'all',
    limit: 50
  });
  
  const response = await fetch(`/fruit-veg/search?${params}`, {
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  });
  
  return await response.json();
};

// Toggle availability
const toggleAvailability = async (productCode, isAvailable) => {
  const response = await fetch('/fruit-veg/availability/toggle', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
      product_code: productCode,
      is_available: isAvailable
    })
  });
  
  return await response.json();
};
```

### cURL Examples
```bash
# Search for products
curl -X GET "/fruit-veg/search?search=apple&category=fruit&limit=10" \
  -H "X-Requested-With: XMLHttpRequest" \
  -b "session_cookie=..."

# Update product price
curl -X POST "/fruit-veg/prices/update" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: abc123..." \
  -b "session_cookie=..." \
  -d '{"product_code": "F001", "new_price": 2.95}'

# Get sales analytics data
curl -X GET "/fruit-veg/sales/data?start_date=2025-06-01&end_date=2025-06-30&limit=50" \
  -H "X-Requested-With: XMLHttpRequest" \
  -b "session_cookie=..."
```

---

## Sales Analytics

### GET /sales
**Description**: Display the F&V sales analytics dashboard with Daily Sales Overview chart

**Features**:
- Interactive date range selection with smart defaults
- Real-time Chart.js visualization with dual-axis (revenue/units)
- Euro (€) currency display throughout interface
- Responsive design with mobile support

**View Parameters**:
- `start_date` (optional) - Start date for analytics (defaults to July 1, 2025)
- `end_date` (optional) - End date for analytics (defaults to July 17, 2025)

### GET /sales/data
**Description**: Get F&V sales data for AJAX requests (Powers the Daily Sales Overview chart)

**Query Parameters**:
- `start_date` (optional, string) - Start date in Y-m-d format
- `end_date` (optional, string) - End date in Y-m-d format  
- `search` (optional, string) - Search term for product filtering
- `limit` (optional, int) - Maximum number of products to return (default: 50)
- `format` (optional, string) - Set to 'csv' for CSV export

**Response** (JSON):
```json
{
  "sales": [
    {
      "product_id": "123",
      "product_name": "Organic Apples",
      "product_code": "F001",
      "category": "SUB1",
      "category_name": "Fruits",
      "total_units": 45.5,
      "total_revenue": 89.99,
      "avg_price": 1.98
    }
  ],
  "stats": {
    "total_units": 2942.12,
    "total_revenue": 7439.95,
    "unique_products": 89,
    "total_transactions": 156,
    "category_breakdown": {
      "Fruits": {
        "units": 1245.67,
        "revenue": 3210.45
      },
      "Vegetables": {
        "units": 1696.45,
        "revenue": 4229.50
      }
    }
  },
  "daily_sales": [
    {
      "sale_date": "2025-06-01T00:00:00.000000Z",
      "daily_units": 154.89,
      "daily_revenue": 399.15,
      "products_sold": 48
    }
  ],
  "date_range": {
    "start": "2025-06-01",
    "end": "2025-06-30",
    "days": 30
  },
  "performance_info": {
    "execution_time_ms": 15.2,
    "data_source": "optimized_pre_aggregated"
  }
}
```

**Features**:
- **Blazing Performance**: Sub-20ms response times with pre-aggregated data
- **Smart Fallback**: Uses live POS queries when aggregated data unavailable
- **Chart Integration**: Optimized for Chart.js Daily Sales Overview updates
- **Error Recovery**: Comprehensive error handling for chart rendering issues
- **Export Support**: CSV export functionality for external analysis

**Technical Notes**:
- Uses `OptimizedSalesRepository` for 100x+ performance improvement
- Falls back to live `TICKETLINES`/`RECEIPTS` queries when needed
- Chart recreation logic prevents Chart.js canvas context errors
- Smart date range validation with user-friendly feedback