# Coffee Module Documentation

## Overview

The Coffee module provides comprehensive management for Coffee Fresh products (category 081), including till visibility management, sales analytics, and inline product editing capabilities.

## Key Features

### 1. Product Management

- **Auto-Barcode Suggestions**: Automatic barcode suggestions when creating new Coffee Fresh products
  - Access via `/products/create?category=081`
  - Suggests next available barcode in 4000s sequence
  - Fills gaps first, then increments from highest
  - Global uniqueness checking prevents conflicts
- **Till Visibility Control**: Toggle products on/off the POS till using the PRODUCTS_CAT table
- **Alphabetical Ordering**: Products on the till are automatically ordered alphabetically by name
  - New products added to till have CATORDER set to NULL for consistent alphabetical sorting
  - Manual ordering functionality preserved for future implementation
- **Inline Price Editing**: Click-to-edit pricing with immediate updates to the POS database
- **Display Name Management**: Set custom display names for till buttons
- **Context-Aware Navigation**: Smart back navigation when accessing products from Coffee module

### 2. Sales Analytics

- **Optimized Performance**: Uses pre-aggregated sales data for sub-second response times
- **Daily Sales Charts**: Interactive charts showing sales trends
- **Product Performance**: Top-selling products with revenue and quantity metrics
- **Date Range Filtering**: Flexible date selection for analysis

### 3. Dashboard

- **Quick Actions**: Fast access to key functions
- **Featured Products**: Shows products currently visible on till
- **Real-time Statistics**: Total products and visible products count

## Technical Implementation

### Routes

```php
Route::prefix('coffee')->name('coffee.')->group(function () {
    Route::get('/', [CoffeeController::class, 'index'])->name('index');
    Route::get('/products', [CoffeeController::class, 'products'])->name('products');
    Route::post('/visibility/toggle', [CoffeeController::class, 'toggleVisibility'])->name('visibility.toggle');
    Route::get('/sales', [CoffeeController::class, 'sales'])->name('sales');
    Route::get('/sales/data', [CoffeeController::class, 'getSalesData'])->name('sales.data');
    Route::get('/sales/product/{code}/daily', [CoffeeController::class, 'getProductDailySales'])->name('sales.product.daily');
    Route::get('/product-image/{code}', [CoffeeController::class, 'productImage'])->name('product-image');
});
```

### Category Configuration

```php
// In TillVisibilityService
const CATEGORY_MAPPINGS = [
    'coffee' => ['081'], // Coffee Fresh category
    // ... other categories
];
```

### Database Integration

The module interacts with both databases:
- **POS Database**: Products, categories, till visibility (PRODUCTS_CAT)
- **Laravel Database**: Sales summaries, activity logs

## UI Components

### Product Management Table

The products table uses Alpine.js for reactive updates:

```javascript
// Alpine.js component structure
coffeeManagementSystem() {
    return {
        products: [], // Loaded via AJAX
        searchTerm: '',
        availabilityFilter: 'all',
        
        async toggleProductAvailability(productCode, isAvailable) {
            // Updates PRODUCTS_CAT table
        },
        
        async updatePrice(productId, newPrice) {
            // Updates product price in POS
        },
        
        async updateDisplay(productId, newDisplay) {
            // Updates display name in POS
        }
    }
}
```

### Toggle Switches

Uses the same pattern as Fruit & Veg for consistency:
- Green background when available on till
- Gray background when not available
- White toggle button with shadow for visibility

## Common Issues & Solutions

### Alpine.js Directive Conflicts

When using Alpine.js directives in Blade templates, escape with double `@@`:

```blade
<!-- Wrong -->
@error="handleError"

<!-- Correct -->
@@error="handleError"
```

### Invisible Toggle Switches

Ensure using self-closing span tags and proper Alpine.js bindings:

```html
<span :class="product.is_available ? 'translate-x-6' : 'translate-x-1'"
      class="inline-block h-4 w-4 transform rounded-full bg-white shadow-lg" />
```

## Performance

The Coffee module leverages the optimized sales repository pattern:
- Pre-aggregated daily and monthly summaries
- Indexed queries for fast filtering
- Minimal cross-database joins

## Related Documentation

- [Sales Data Import Plan](./sales-data-import-plan.md) - Performance optimization patterns
- [Till Visibility Service](../api/till-visibility.md) - Managing product visibility
- [Fruit & Veg System](./fruit-veg-system.md) - Similar implementation patterns