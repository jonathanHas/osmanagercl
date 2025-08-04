# Till Visibility Service API

## Overview

The Till Visibility Service manages which products appear as buttons on the POS till interface. It provides a unified API for controlling product visibility across different categories (Fruit & Veg, Coffee, Lunch, Cakes).

## Service Methods

### `isVisibleOnTill(string $productId): bool`

Check if a product is currently visible on the till.

**Parameters:**
- `$productId` - The product UUID from the PRODUCTS table

**Returns:**
- `bool` - True if product is visible on till, false otherwise

**Example:**
```php
$isVisible = $tillVisibilityService->isVisibleOnTill('c79611e3-042a-11ed-a063-10c37b4d894e');
```

### `setVisibility(string $productId, bool $visible, ?string $categoryType = null): bool`

Set the visibility of a product on the till.

**Parameters:**
- `$productId` - The product UUID
- `$visible` - Whether to show (true) or hide (false) the product
- `$categoryType` - Optional category type for activity logging ('fruit_veg', 'coffee', etc.)

**Returns:**
- `bool` - Success status

**Example:**
```php
$success = $tillVisibilityService->setVisibility($productId, true, 'coffee');
```

### `toggleVisibility(string $productId): bool`

Toggle a product's visibility status.

**Parameters:**
- `$productId` - The product UUID

**Returns:**
- `bool` - New visibility status (true = visible, false = hidden)

### `getProductsWithVisibility(string $categoryType, array $filters = [], ?int $limit = null, int $offset = 0): Collection`

Get products for a category with their visibility status.

**Parameters:**
- `$categoryType` - Category type ('fruit_veg', 'coffee', 'lunch', 'cakes')
- `$filters` - Optional filters array:
  - `search` - Search term for product name/code
  - `visibility` - Filter by visibility ('all', 'visible', 'hidden')
  - `category` - Specific category within type
- `$limit` - Optional pagination limit
- `$offset` - Pagination offset

**Returns:**
- `Collection` - Products with `is_visible_on_till` property added

**Example:**
```php
$products = $tillVisibilityService->getProductsWithVisibility('coffee', [
    'search' => 'Americano',
    'visibility' => 'visible'
], 50, 0);
```

## Category Mappings

The service uses predefined category mappings:

```php
const CATEGORY_MAPPINGS = [
    'fruit_veg' => ['SUB1', 'SUB2', 'SUB3'], // Fruits, Vegetables, Veg Barcoded
    'coffee' => ['081'],                      // Coffee Fresh
    'lunch' => ['SANDWICHES', 'SALADS'],      // To be defined
    'cakes' => ['CAKES', 'PASTRIES'],         // To be defined
];
```

## Database Structure

### PRODUCTS_CAT Table (POS Database)

The service manages the `PRODUCTS_CAT` table in the POS database:

| Column | Type | Description |
|--------|------|-------------|
| PRODUCT | VARCHAR(255) | Product UUID (Primary Key) |
| CATORDER | INT | Display order on till |

## HTTP Endpoints

### Toggle Product Visibility

**Endpoint:** `POST /{module}/visibility/toggle`

**Request Body:**
```json
{
    "product_id": "c79611e3-042a-11ed-a063-10c37b4d894e",
    "visible": true
}
```

**Response:**
```json
{
    "success": true
}
```

**Available Routes:**
- `/fruit-veg/availability/toggle`
- `/coffee/visibility/toggle`

## Activity Logging

All visibility changes are logged to the `product_activity_logs` table with:
- Product ID and code
- Activity type (added_to_till, removed_from_till)
- Category context
- User who made the change
- Timestamp

## Usage in Controllers

```php
class CoffeeController extends Controller
{
    protected TillVisibilityService $tillVisibilityService;
    
    public function toggleVisibility(Request $request)
    {
        $request->validate([
            'product_id' => 'required|string',
            'visible' => 'required|boolean',
        ]);

        $success = $this->tillVisibilityService->setVisibility(
            $request->product_id,
            $request->visible,
            'coffee'
        );

        return response()->json(['success' => $success]);
    }
}
```

## Frontend Integration

The service is typically used with Alpine.js components:

```javascript
async toggleProductAvailability(productCode, isAvailable) {
    const product = this.products.find(p => p.CODE === productCode);
    if (!product) return;
    
    const response = await fetch('/coffee/visibility/toggle', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            product_id: product.ID,
            visible: isAvailable
        })
    });
    
    if (response.ok) {
        product.is_available = isAvailable;
    }
}
```