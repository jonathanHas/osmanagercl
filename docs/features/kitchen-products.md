# Kitchen Products Management

The Kitchen Products Management system helps track products that regularly go to the kitchen, streamlining ingredient ordering and speeding up the creation of ingredient profiles for recipe costing.

## Overview

This system enables you to:
- Flag products as "kitchen products" for easy tracking
- View all kitchen products in one place with stock levels
- Quickly copy supplier codes for ordering
- Create ingredient profiles directly from the kitchen products list
- Filter and organize products by supplier or category

## Features

### Kitchen Products List

Access at `/kitchen/products` to view all flagged kitchen products.

**Displayed Information:**
| Column | Description |
|--------|-------------|
| Product | Product name and barcode |
| Supplier | Supplier name from POS |
| Supplier Code | Click to copy for ordering |
| Shop Stock | Current POS stock level |
| Kitchen Stock | Placeholder for future kitchen inventory |
| Profile | Link to create or edit ingredient profile |
| Actions | Remove from kitchen list |

### Quick Flag from Orders Page

On the Orders review page (`/orders/`), each product row has a "Kitchen" toggle button:
- **Orange button**: Product is flagged as a kitchen product
- **Gray button**: Product is not flagged
- Click to toggle status with instant AJAX update

### Product Search & Add

Search and add products directly from the kitchen products page:

**Search Capabilities:**
- Product name (partial match)
- Barcode (CODE field)
- Supplier code (via `supplier_link` table)

**Search Results Show:**
- Product name
- Barcode
- Supplier code
- Supplier name

Click "Add" to instantly add a product to the kitchen list.

### Supplier Code Quick Copy

Click any supplier code to copy it to the clipboard:
- Visual feedback with checkmark and "Copied!" message
- Works on HTTPS and HTTP (fallback method)
- Entire code area is clickable for easy copying

### Filtering Options

**Supplier Filter:**
- Dropdown showing all suppliers with kitchen products
- Filter to show only products from selected supplier

**Group by Category:**
- Toggle checkbox to organize products by POS category
- Collapsible category sections with product counts

**Text Search:**
- Search existing kitchen products by name or code

### Statistics Dashboard

Overview cards at the top of the page:
- **Kitchen Products**: Total count of flagged products
- **With Profiles**: Products that have ingredient profiles
- **Need Profiles**: Products without ingredient profiles

### Ingredient Profile Integration

Each product row links directly to ingredient profiles:
- **"Has Profile"** (green): Links to edit existing profile
- **"Create Profile"** (amber): Links to create new profile with product pre-filled

## Database Schema

### kitchen_products

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| product_id | varchar(255) | POS product UUID (unique) |
| notes | text | Optional notes (nullable) |
| created_at | timestamp | When added to kitchen list |
| updated_at | timestamp | Last updated |

**Indexes:**
- Primary key on `id`
- Unique index on `product_id`

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/kitchen/products` | List all kitchen products |
| GET | `/kitchen/products/search?q=` | Search products to add |
| POST | `/kitchen/products/toggle` | Add/remove product from list |
| DELETE | `/kitchen/products/{id}` | Remove from kitchen list |

### Toggle Endpoint

**Request:**
```json
{
  "product_id": "uuid-string"
}
```

**Response:**
```json
{
  "success": true,
  "is_kitchen": true,
  "message": "Product added to kitchen list"
}
```

### Search Endpoint

**Request:**
```
GET /kitchen/products/search?q=milk
```

**Response:**
```json
[
  {
    "id": "uuid-string",
    "name": "Organic Milk 1L",
    "code": "1234567890123",
    "supplier_code": "MLK001",
    "supplier": "Udea"
  }
]
```

## Files

### Models
- `app/Models/KitchenProduct.php` - Kitchen product model with Product relationship

### Controllers
- `app/Http/Controllers/KitchenProductController.php` - All CRUD and search operations

### Views
- `resources/views/kitchen/products/index.blade.php` - Main listing page
- `resources/views/kitchen/products/partials/product-row.blade.php` - Reusable row partial

### Routes
Located in `routes/web.php` under the kitchen prefix:
```php
Route::prefix('products')->name('products.')->group(function () {
    Route::get('/', [KitchenProductController::class, 'index'])->name('index');
    Route::get('/search', [KitchenProductController::class, 'search'])->name('search');
    Route::post('/toggle', [KitchenProductController::class, 'toggle'])->name('toggle');
    Route::delete('/{kitchenProduct}', [KitchenProductController::class, 'destroy'])->name('destroy');
});
```

## Usage Workflow

### Adding Products to Kitchen List

**Method 1: From Orders Page**
1. Navigate to `/orders/`
2. Generate or view an order
3. Click the "Kitchen" button on any product row
4. Button turns orange to indicate the product is now flagged

**Method 2: From Kitchen Products Page**
1. Navigate to `/kitchen/products`
2. Use the search box at the top
3. Enter product name, barcode, or supplier code
4. Click "Add" on the desired product

### Creating Ingredient Profiles

1. Navigate to `/kitchen/products`
2. Find a product showing "Create Profile" (amber badge)
3. Click to open the ingredient profile form with product pre-filled
4. Complete the profile with purchase unit, recipe unit, and conversion factors

### Quick Ordering Workflow

1. Navigate to `/kitchen/products`
2. Filter by supplier if needed
3. Click supplier codes to copy them to clipboard
4. Paste codes into supplier's ordering system

## Related Documentation

- [Kitchen Recipe Costing](./kitchen-recipe-costing.md) - Recipe management and costing
- [Order Generation](./order-management/order-generation.md) - Order system with Kitchen toggle
- [Product Management](./product-management.md) - POS product details
