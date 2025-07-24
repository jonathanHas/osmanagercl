# Fruit & Vegetables Management System

## Overview

The Fruit & Vegetables (F&V) system is a specialized module designed for organic produce management. It provides dedicated functionality for handling organic certification requirements, weekly availability management, pricing updates, and label printing for fresh produce.

## Features

### 1. Availability Management
- **Weekly Product Selection**: Manage which F&V products are available for sale each week
- **Bulk Operations**: Mark multiple products as available/unavailable simultaneously
- **Real-time Search**: AJAX-powered search across all F&V products (664 total)
- **Advanced Filtering**: Filter by category (Fruits/Vegetables) and availability status
- **Performance Optimized**: Pagination with "Load More" functionality for handling large datasets
- **Reliable Interface**: Alpine.js integration with proper Blade template compatibility

### 2. Pricing Management
- **Dynamic Pricing**: Update prices for available F&V products
- **Price History**: Complete audit trail of all price changes
- **Automatic Label Queue**: Products automatically added to print queue when prices change
- **VAT Calculations**: Integrated with existing tax system

### 3. Label Printing System
- **Organic Certification Labels**: Specialized templates for organic produce compliance
- **Country of Origin**: Required labeling for organic certification
- **Print Queue Management**: Tracks products needing new labels
- **Batch Printing**: Print labels for multiple products simultaneously

### 4. Product Information Management
- **Display Name Editing**: Set custom display names for products with live HTML preview
- **Country of Origin**: Select and update product origin countries
- **Unit Information**: Display unit types (kg, pieces, etc.)
- **Product Images**: Full image management with upload, preview, and binary storage
- **Comprehensive Edit Interface**: Dedicated product edit pages with tabbed layout

### 5. Featured Products Dashboard
- **Available This Week Section**: Prominently displays currently available products on main dashboard
- **Clickable Product Cards**: Direct navigation to product edit pages from featured section
- **Visual Product Grid**: Responsive grid layout with product images and pricing
- **Real-time Availability**: Cards reflect current availability status and pricing

## Technical Implementation

### Database Structure

#### F&V Specific Tables (Laravel Database)
```sql
-- Tracks which products are available for sale
CREATE TABLE veg_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(255) NOT NULL UNIQUE,
    is_available BOOLEAN DEFAULT FALSE,
    current_price DECIMAL(10,4),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Audit trail for price changes
CREATE TABLE veg_price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(255) NOT NULL,
    old_price DECIMAL(10,4),
    new_price DECIMAL(10,4),
    changed_by INT,
    changed_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Print queue for labels needing to be printed
CREATE TABLE veg_print_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(255) NOT NULL UNIQUE,
    reason VARCHAR(255), -- 'price_change', 'marked_available', etc.
    added_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### POS Database Tables (Read-Only)
- **PRODUCTS**: Main product data (name, code, price, category, images with BLOB IMAGE field)
- **vegDetails**: Product details (country, class, unit information)
- **CATEGORIES**: Product categories (SUB1=Fruits, SUB2=Vegetables, SUB3=Veg Barcoded)
- **countries**: Country master data for origin labeling

### Models

#### VegPrintQueue
```php
// Static helper methods for queue management
VegPrintQueue::addToQueue($productCode, $reason);
VegPrintQueue::removeFromQueue($productCode);
VegPrintQueue::getQueuedProductCodes();
VegPrintQueue::clearQueue();
```

#### Product (Enhanced)
```php
// Image handling methods
$product->hasImage();
$product->getImageThumbnailAttribute();
$product->getGrossPrice(); // Price including VAT
```

#### VegDetails
```php
// Relationships to countries and product data
$vegDetails->country;
$vegDetails->getUnitNameAttribute();
$vegDetails->getClassNameAttribute();
```

### Controller Methods

#### FruitVegController Key Methods

**Dashboard & Statistics**
- `index()` - Main F&V dashboard with statistics and featured products
- `getAvailableCount()` - Helper for availability counts
- `getFeaturedAvailableProducts()` - Get featured products for dashboard display

**Availability Management**
- `availability()` - Main availability page with pagination
- `toggleAvailability()` - Toggle single product availability
- `bulkAvailability()` - Bulk update multiple products
- `searchProducts()` - AJAX search endpoint

**Price Management**
- `prices()` - Price management interface
- `updatePrice()` - Update individual product prices

**Label System**
- `labels()` - Label printing interface
- `previewLabels()` - Label preview functionality
- `markLabelsPrinted()` - Clear items from print queue

**Product Data Management**
- `editProduct()` - Comprehensive product edit interface with tabbed layout
- `updateProductImage()` - Handle image uploads with binary storage
- `updateDisplay()` - Update product display names
- `updateCountry()` - Update country of origin
- `getCountries()` - Country dropdown data
- `productImage()` - Serve product images from database with cache headers

### Routes

All F&V routes are grouped under `/fruit-veg` prefix:

```php
Route::prefix('fruit-veg')->name('fruit-veg.')->group(function () {
    Route::get('/', [FruitVegController::class, 'index'])->name('index');
    Route::get('/availability', [FruitVegController::class, 'availability'])->name('availability');
    Route::post('/availability/toggle', [FruitVegController::class, 'toggleAvailability'])->name('availability.toggle');
    Route::post('/availability/bulk', [FruitVegController::class, 'bulkAvailability'])->name('availability.bulk');
    Route::get('/prices', [FruitVegController::class, 'prices'])->name('prices');
    Route::post('/prices/update', [FruitVegController::class, 'updatePrice'])->name('prices.update');
    Route::get('/labels', [FruitVegController::class, 'labels'])->name('labels');
    Route::get('/labels/preview', [FruitVegController::class, 'previewLabels'])->name('labels.preview');
    Route::post('/labels/printed', [FruitVegController::class, 'markLabelsPrinted'])->name('labels.printed');
    Route::post('/display/update', [FruitVegController::class, 'updateDisplay'])->name('display.update');
    Route::post('/country/update', [FruitVegController::class, 'updateCountry'])->name('country.update');
    Route::get('/countries', [FruitVegController::class, 'getCountries'])->name('countries');
    Route::get('/search', [FruitVegController::class, 'searchProducts'])->name('search');
    Route::get('/product-image/{code}', [FruitVegController::class, 'productImage'])->name('product-image');
    Route::get('/product/{code}', [FruitVegController::class, 'editProduct'])->name('product.edit');
    Route::post('/product/{code}/update-image', [FruitVegController::class, 'updateProductImage'])->name('product.update-image');
});
```

## User Interface

### Availability Management Page
- **Compact 6-column layout** optimized for mobile and desktop
- **Real-time search** with 500ms debounce
- **Advanced filters** for category and availability status
- **Bulk selection** with checkbox controls
- **Inline editing** for display names and country of origin
- **Product thumbnails** with fallback placeholders
- **Pagination** with "Load More" functionality (50 products per page)

### UI Components
- **Alpine.js** for reactive interface components
- **AJAX-powered** search and updates without page refreshes
- **Visual feedback** with success/error notifications
- **Mobile-responsive** design with Tailwind CSS
- **Tabbed Interface** for product edit pages (with known compatibility issues)
- **Live HTML Preview** for display name editing with entity conversion
- **Image Upload** with drag-and-drop and real-time preview

## Performance Optimizations

### Database Queries
- **Eager loading** relationships to prevent N+1 queries
- **Indexed searches** on product codes and names
- **Pagination** to limit initial load to 50 products
- **EXISTS queries** for efficient filtering

### Frontend Performance
- **Debounced search** to prevent excessive API calls
- **Progressive loading** with "Load More" functionality
- **Image caching** with appropriate cache headers
- **Optimized JavaScript** with minimal DOM manipulation

## Workflow Examples

### Weekly Availability Update
1. Navigate to F&V Availability page
2. Use search/filters to find products for the week
3. Select multiple products using checkboxes
4. Click "Mark Available" for bulk update
5. Products automatically added to label print queue

### Price Updates
1. Go to F&V Prices page (shows only available products)
2. Update prices directly in the interface
3. Changes logged to price history
4. Products automatically added to print queue
5. Print new labels with updated prices

### Label Printing
1. Visit F&V Labels page
2. Review products needing labels
3. Preview labels before printing
4. Mark labels as printed to clear queue

## Organic Certification Compliance

### Required Label Information
- **Product name** and display name
- **Price per unit** with VAT
- **Country of origin** (mandatory for organic certification)
- **Unit type** (kg, pieces, bunches, etc.)
- **Organic certification badge** (visual indicator)

### Data Validation
- Country of origin required for all F&V products in print queue
- Price validation ensures positive values
- Display name optional but recommended for customer clarity

## Integration Points

### POS System Integration
- **Read-only access** to POS product data
- **Real-time price updates** reflected in availability management
- **Category mapping** for F&V product identification
- **Image serving** from POS database BLOB fields

### Laravel Application Integration
- **Authentication required** for all F&V operations
- **Audit logging** for price changes and availability updates
- **Print queue integration** with existing label system
- **Admin layout consistency** with rest of application

## Future Enhancements

### Planned Features
- **Seasonal availability templates** for recurring weekly patterns
- **Supplier price integration** for cost tracking
- **Sales performance analytics** for F&V products
- **Mobile scanning app** for quick availability updates
- **Batch import/export** for availability data

### Technical Improvements
- ✅ **Fixed Alpine.js/Blade conflicts** - Resolved ParseError issues with event handler escaping
- **Enhanced error handling** - Better debugging and troubleshooting documentation
- **Improved template reliability** - Systematic approach to template literal conflicts

### Recent Bug Fixes (2024)
- **ParseError Resolution**: Fixed "unexpected end of file" errors caused by Alpine.js `@error` directive conflicts with Blade compilation
- **Template Compatibility**: Resolved JavaScript template literal issues with Blade route generation
- **Compilation Stability**: Enhanced Blade template parsing for complex Alpine.js integrations
- **HTML Display Rendering**: Fixed display name rendering to properly show `<br>` tags and other HTML entities
- **SQL Query Optimization**: Fixed ordering errors when querying POS database tables
- **Image Upload Integration**: Implemented comprehensive image management with binary storage
- **Component Compatibility**: Identified and documented tab component slot access issues with working workarounds

### Recent Feature Additions (2024)

#### Featured Products Dashboard Section
- Added "Available This Week" section to main fruit-veg page
- Displays 12 featured available products in responsive grid
- Clickable cards navigate directly to product edit pages
- Real-time pricing and availability status display
- Proper HTML rendering for product display names
- Removed "View All Products" button from quick actions for cleaner interface

#### Comprehensive Product Edit Interface
- Full product management page with tabbed layout
- Image upload functionality with binary database storage
- Live HTML preview for display name editing
- Price management with history tracking
- Country of origin selection with validation
- **Sales History Tab**: Full sales analytics matching general products functionality
  - Interactive time period selection (4m, 6m, 12m, YTD)
  - Dynamic sales charts with Chart.js integration
  - Sales statistics cards (total, average, current month, trend)
  - Monthly sales table with trend indicators
  - AJAX-powered data loading without page refresh
- Real-time AJAX form submissions without page refresh

#### Enhanced Image Management
- Direct image upload to POS database IMAGE field
- Real-time image preview before upload
- Cache-optimized image serving with proper headers
- Fallback transparent PNG for products without images
- Image update triggers automatic addition to print queue

### Planned Enhancements
- **Real-time updates** using WebSockets for multi-user environments
- **Advanced image processing** for better thumbnail generation
- **API endpoints** for mobile app integration
- **Caching layer** for frequently accessed product data
- **Tab component fix**: Resolve Laravel slot system compatibility for proper tab functionality

## Technical Issues

### 🚨 Known Component Compatibility Issues

#### Tab Component Slot Access Problems
**Status**: Unresolved - Workaround Implemented

**Symptoms**:
- Product edit page tabs display navigation but show "No content provided for [tab name] tab"
- Content properly defined in `<x-slot name="tabname">` sections is not rendering
- Issue affects both Products show page and Fruit-Veg product edit page

**Root Cause**: 
Laravel's slot system compatibility issue with the `<x-tab-group>` component's slot access pattern. The component cannot access named slots using dynamic slot names.

**Affected Code Pattern**:
```blade
<!-- This doesn't work in current Laravel version -->
@if(isset($slots[$tab['id']]))
    {{ $slots[$tab['id']] }}
@endif
```

**Attempted Solutions**:
1. Modified slot access pattern to `@if($slot = $slots[$tab['id']] ?? null)`
2. Verified exact slot name matching between tab definitions and slot names
3. Tested minimal component implementation

**Working Workaround**: 
Direct Alpine.js implementation bypassing Laravel's slot system:
```blade
<div x-data="{ activeTab: 0 }" class="w-full">
    <!-- Tab Navigation -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <button @click="activeTab = 0" 
                    :class="activeTab === 0 ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500'">
                Overview
            </button>
        </nav>
    </div>
    <!-- Tab Content -->
    <div class="mt-4">
        <div x-show="activeTab === 0" x-transition>
            Content here
        </div>
    </div>
</div>
```

**Current Status**: 
- Product edit page implemented with working Alpine.js solution
- Products show page still uses broken tab component
- Need system-wide solution for tab component compatibility

### 🔧 Template Rendering Issues

#### HTML Entity Display Problems
**Status**: Resolved

**Issue**: Product display names with HTML entities (like `<br>` tags) not rendering correctly

**Solution**: 
```blade
<!-- ❌ Before - showed raw HTML -->
{{ strip_tags(html_entity_decode($product->DISPLAY)) }}

<!-- ✅ After - renders HTML properly -->
{!! nl2br(html_entity_decode($product->DISPLAY)) !!}
```

#### SQL Ordering Errors
**Status**: Resolved

**Issue**: `Unknown column 'updated_at' in 'ORDER BY'` when ordering POS database results

**Solution**: Changed ordering from non-existent `updated_at` column to `NAME` column

## Troubleshooting

### Common Issues

**Products Not Appearing in Search**
- Verify product belongs to F&V categories (SUB1, SUB2, SUB3)
- Check database connection to POS system
- Confirm product has valid CODE field

**Images Not Loading**
- Check product has IMAGE data in POS database
- Verify image serving route is accessible
- Clear browser cache if images appear corrupted

**Price Updates Not Saving**
- Ensure product is marked as available first
- Check numeric validation (positive values only)
- Verify user has proper authentication

**Labels Not Generating**
- Confirm country of origin is set for organic products
- Check print queue contains the product
- Verify VegDetails relationship exists

### Database Maintenance
```sql
-- Clean up old price history (optional)
DELETE FROM veg_price_history WHERE changed_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- Reset availability for new season
UPDATE veg_availability SET is_available = FALSE WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 MONTH);

-- Clear print queue
TRUNCATE TABLE veg_print_queue;
```

## Security Considerations

### Access Control
- All F&V routes require authentication
- Price change logging includes user identification
- CSRF protection on all POST requests

### Data Validation
- Input sanitization on all user inputs
- Numeric validation for prices and IDs
- SQL injection prevention through Eloquent ORM

### Image Serving
- Content-Type validation for served images
- Cache control headers to prevent abuse
- Fallback handling for missing images