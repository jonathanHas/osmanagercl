# Delivery Verification System

This document covers the comprehensive delivery verification system for processing supplier deliveries, scanning products, and managing discrepancies.

## Overview

The delivery verification system provides a complete workflow for handling supplier deliveries from initial CSV import through scanning verification to stock updates. It includes real-time scanning interfaces, discrepancy tracking, and supplier image integration.

## System Architecture

### Core Components

1. **DeliveryService** (`app/Services/DeliveryService.php`)
   - CSV import and parsing
   - Barcode scanning logic
   - Delivery completion and stock updates
   - Summary generation and discrepancy reporting

2. **Models** (`app/Models/`)
   - `Delivery` - Main delivery tracking
   - `DeliveryItem` - Individual product items
   - `DeliveryScan` - Scan history and matching

3. **Controllers** (`app/Http/Controllers/DeliveryController.php`)
   - RESTful delivery management
   - Real-time scanning API endpoints
   - Summary and reporting views

4. **Views** (`resources/views/deliveries/`)
   - `index.blade.php` - Delivery overview with progress tracking
   - `create.blade.php` - CSV upload interface
   - `show.blade.php` - Detailed delivery view with item breakdown
   - `scan.blade.php` - Real-time scanning interface (mobile-optimized)
   - `summary.blade.php` - Discrepancy reporting and completion

## Database Schema

### Deliveries Table
```sql
- id (primary key)
- delivery_number (unique, auto-generated)
- supplier_id (foreign key to SUPPLIERS)
- delivery_date
- status (draft, receiving, completed, cancelled)
- total_expected (decimal 8,2)
- total_received (decimal 8,2, nullable)
- import_data (JSON metadata)
- timestamps
```

### Delivery Items Table
```sql
- id (primary key)
- delivery_id (foreign key)
- supplier_code (supplier's internal product code)
- sku, description, units_per_case
- unit_cost, ordered_quantity, received_quantity
- total_cost, status
- product_id (foreign key to PRODUCTS, nullable)
- is_new_product (boolean)
- barcode (nullable, retrieved from scraping)
- timestamps
```

### Delivery Scans Table
```sql
- id (primary key)
- delivery_id (foreign key)
- delivery_item_id (foreign key, nullable)
- barcode (scanned code)
- quantity, matched (boolean)
- scanned_by (user identifier)
- timestamps
```

## Complete Workflow

### 1. Pre-Delivery Setup  
1. **CSV Import**: Upload supplier delivery docket
   - Route: `POST /deliveries` (upload CSV file)
   - **Multi-Format Support**: Automatic detection of CSV format based on headers and supplier
   - **Udea Format**: Code, Ordered, Qty, SKU, Content, Description, Price, Sale, Total
   - **Independent Irish Health Foods Format**: Code, Product, Ordered, Qty, RSP, Price, Tax, Value
   - Creates delivery header and individual items with format-specific parsing

2. **Product Matching**: Automatic product identification
   - Uses existing `SupplierLink` model to match supplier codes
   - Identifies new products not in system
   - **Enhanced Barcode Retrieval**: Multiple extraction patterns for maximum compatibility
     - HTML table format: `<td class="wt-semi">EAN</td><td>8711521021925</td>`
     - Simple table format: `<td>EAN</td><td>8711521021925</td>`
     - Colon separated: `EAN: 8711521021925`
     - Fallback EAN-13 validation for 13-digit codes
   - Queues barcode retrieval for new products via enhanced `UdeaScrapingService`

### 2. Delivery Scanning
1. **Scanning Interface**: Mobile-optimized real-time interface
   - Route: `GET /deliveries/{delivery}/scan`
   - Real-time progress tracking with Alpine.js
   - Barcode input with quantity adjustment
   - Visual status indicators (complete, partial, missing, excess)

2. **Scan Processing**: Real-time barcode verification
   - Route: `POST /deliveries/{delivery}/scan` (uses session authentication)
   - Matches barcodes to expected items
   - Updates received quantities and item status
   - Records all scans (matched and unmatched)

3. **Live Updates**: Dynamic interface updates
   - Progress bars and statistics
   - Item filtering (all, pending, partial, discrepancies)
   - Manual quantity adjustments with +/- buttons

### 3. Product Creation Integration
1. **New Product Identification**: Automatic detection of unmatched items
   - Items without existing product matches are flagged as "new products"
   - Barcode retrieval via `UdeaScrapingService` for product identification
   - Visual indicators in delivery interfaces

2. **Product Creation Workflow**: Direct integration with product creation system
   - **Access**: "Add to POS" buttons appear next to all new product items
   - **Pre-population**: Delivery item data automatically populates the product creation form
   - **Fields**: Name, barcode, cost price, supplier information, units per case
   - **Smart Pricing**: UDEA suppliers automatically use scraped customer prices
   - **Visual Indicators**: Green badges for scraped prices, blue for calculated prices
   - **Integration**: Created products are automatically linked back to delivery items

3. **Creation Process**: Seamless workflow integration
   - Route: `GET /products/create?delivery_item={id}` - Pre-populated form
   - **UUID Generation**: Products get unique UUID identifiers
   - **Intelligent Pricing**: UDEA deliveries use scraped customer prices when available
   - **Fallback Logic**: Falls back to 30% markup if scraping fails
   - **Supplier Linking**: Automatic SupplierLink creation with delivery supplier data
   - **Status Update**: Delivery items updated from "new product" to matched product

### 4. Verification & Completion
1. **Summary Generation**: Comprehensive discrepancy analysis
   - Route: `GET /deliveries/{delivery}/summary`
   - Complete vs partial vs missing items breakdown
   - Unmatched scans (products not in manifest)
   - Value difference calculations

2. **Discrepancy Export**: Structured reporting
   - Route: `GET /deliveries/{delivery}/export-discrepancies`
   - JSON export for supplier reconciliation
   - Includes value impacts and item details

3. **Stock Updates**: Final completion process
   - Route: `POST /deliveries/{delivery}/complete`
   - Updates POS system stock levels
   - Adjusts product costs if different from expected
   - Marks delivery as completed

## Supplier Image Integration

### Implementation
The system integrates with the existing `SupplierService` to display product images throughout the delivery workflow:

1. **Backend Integration**:
   - `DeliveryController` injects `SupplierService`
   - Loads `items.product.supplier` relationships for existing products
   - **New Products**: Uses `getExternalImageUrlByBarcode()` with delivery supplier ID and retrieved barcode
   - Passes `supplierService` to all views

2. **Frontend Display**:
   - 40x40px thumbnails in all tables (existing and new products)
   - 192x192px hover previews with product names and barcode display
   - **Real-time Updates**: Images appear automatically when barcodes are retrieved
   - Lazy loading with error handling
   - Consistent styling with products page

3. **Coverage**:
   - ✅ Delivery items table (show view) - supports new products
   - ✅ Scanning interface (real-time) - dynamic image loading
   - ✅ Discrepancy reports (summary view) - full new product support
   - ✅ Mobile responsive design

### Enhanced Image Features
- **Hover Previews**: Large image overlay on hover with barcode information
- **Loading Animation**: Pulse effect while loading
- **Error Handling**: Graceful fallback to placeholder icon
- **Performance**: Lazy loading with `loading="lazy"`
- **New Product Support**: Images work immediately once barcodes are extracted from supplier
- **Barcode Display**: Shows barcode in image overlay for product identification
- **Integration Check**: Only shows for suppliers with external integration

### Barcode-Driven Images
For new products without existing Product models:
- Uses delivery's `supplier_id` and item's retrieved `barcode`
- Image URL format: `https://cdn.ekoplaza.nl/ekoplaza/producten/small/{BARCODE}.jpg`
- Automatic availability once `UdeaScrapingService` successfully extracts EAN/barcode
- Same visual experience and hover functionality as existing products

## API Endpoints

### Web Routes (`routes/web.php`)
```php
Route::resource('deliveries', DeliveryController::class);
Route::get('/deliveries/{delivery}/scan', [DeliveryController::class, 'scan']);
Route::get('/deliveries/{delivery}/summary', [DeliveryController::class, 'summary']);
Route::post('/deliveries/{delivery}/complete', [DeliveryController::class, 'complete']);
Route::post('/deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel']);
Route::get('/deliveries/{delivery}/export-discrepancies', [DeliveryController::class, 'exportDiscrepancies']);
Route::post('/delivery-items/{item}/refresh-barcode', [DeliveryController::class, 'refreshBarcode']);
```

**Note**: The `Route::resource('deliveries', DeliveryController::class)` includes the `destroy` method for delivery deletion, accessible via `DELETE /deliveries/{delivery}` with safety restrictions.

### API Routes (`routes/api.php`)
```php
Route::middleware('auth')->prefix('deliveries')->group(function () {
    Route::post('/{delivery}/scan', [DeliveryController::class, 'processScan']);
    Route::get('/{delivery}/stats', [DeliveryController::class, 'getStats']);
    Route::patch('/{delivery}/items/{item}/quantity', [DeliveryController::class, 'adjustQuantity']);
});
```

**Authentication Note**: The frontend scanning interface uses web routes (session authentication) rather than API routes (token authentication) to ensure compatibility with the existing login system. API routes are available for external integrations.

## Key Features

### Real-Time Scanning
- **Mobile Optimized**: Touch-friendly interface for warehouse use
- **Live Updates**: Instant feedback on scans and progress
- **Barcode Focus**: Auto-focus on barcode input after each scan
- **Status Tracking**: Visual indicators for all item states

### Product Creation Integration
- **New Product Detection**: Automatic identification of items not in POS system
- **One-Click Creation**: "Add to POS" buttons for instant product creation
- **Pre-populated Forms**: Delivery data automatically fills product creation form
- **UUID-Based Products**: Modern product identification system
- **Supplier Integration**: Automatic supplier linking and barcode retrieval
- **Seamless Workflow**: Created products immediately available for scanning

### UDEA Smart Pricing Integration
- **Automatic Detection**: Recognizes UDEA suppliers (IDs: 5, 44, 85) from delivery data
- **Scraped Customer Prices**: Uses real retail prices from UDEA website instead of markup
- **Visual Feedback**: Green badges indicate scraped prices, blue badges show calculated prices
- **Real-time Data**: Prices are retrieved with timestamps showing data freshness
- **User Control**: Scraped prices can be manually adjusted if needed
- **Fallback Logic**: Gracefully falls back to 30% markup if scraping fails
- **Performance**: Cached scraping results with 1-hour TTL for efficiency

### Independent Irish Health Foods Integration
- **VAT Rate Calculation**: Automatic calculation using formula: (Tax ÷ Value) × 100
- **Irish VAT Normalization**: Maps calculated rates to standard Irish VAT rates (0%, 9%, 13.5%, 23%)
- **Automatic Tax Category Selection**: Pre-selects appropriate POS tax category when creating products
- **Case-to-Unit Conversion**: Converts case prices to unit prices using product name parsing
- **RSP Integration**: Uses Recommended Selling Price for intelligent pricing suggestions
- **Quantity Notation Support**: Handles "ordered/received" format for partial deliveries
- **Visual Tax Indicators**: Green highlighting shows auto-selected tax categories

### Discrepancy Management
- **Comprehensive Tracking**: Missing, partial, excess, and unknown items
- **Value Impact**: Financial implications of discrepancies
- **Export Capability**: Structured data for supplier communication
- **Visual Identification**: Product images for quick recognition

### Progress Monitoring
- **Real-Time Progress**: Live percentage completion
- **Status Badges**: Visual status indicators throughout
- **Summary Cards**: Quick overview of delivery metrics
- **Filtering Options**: View specific item categories

## Dependencies

### Required Packages
- `league/csv` - CSV parsing and processing
- **Alpine.js** - Frontend reactivity (included in Breeze)
- **Tailwind CSS** - Styling framework

### Existing Integrations
- **SupplierService** - External image URLs and supplier integration
- **UdeaScrapingService** - Barcode retrieval for new products
- **SupplierLink Model** - Product-supplier code matching
- **Admin Layout** - Sidebar navigation integration

## Configuration

### CSV Formats

#### Udea Format
Standard Dutch supplier format:
```csv
Code,Ordered,Qty,SKU,Content,Description,Price,Sale,Total
115,1,1,6,"1 kilogram","Broccoli, . Biologisch Klasse I NL",3.17,6.98,19.02
```

#### Independent Irish Health Foods Format  
Irish supplier format with VAT calculations:
```csv
Code,Product,Ordered,Qty,RSP,Price,Tax,Value
49036A,All About KombuchaRaspberry Can (Org)(DRS) 1x330ml,6,6,3.7,2.15,2.97,12.9
19990B,Suma Hemp Oil & Vitamin E Soap 12x90g,1/0,1/0,3.08,21.44,4.93,21.44
```

**Key Differences**:
- **Price Field**: Independent format uses **case price**, Udea uses **unit price**  
- **Unit Cost Calculation**: Independent divides Price by units per case (extracted from product name)
- **Tax Information**: Independent provides separate Tax amount and calculated VAT rates
- **Quantity Notation**: Independent supports "ordered/received" format (e.g., "6/5", "1/0")
- **RSP Field**: Recommended selling price for automatic pricing suggestions

### File Uploads
- **Maximum Size**: 10MB
- **Accepted Types**: .csv, .txt
- **Storage**: Temporary storage with automatic cleanup
- **Validation**: Required supplier selection and delivery date

## Troubleshooting Guide

### Common Issues

#### 1. CSV Import Failures
**Symptoms**: 
- "Failed to import CSV" error messages
- Empty delivery creation

**Potential Causes**:
- Incorrect CSV format or headers
- Missing `league/csv` package
- Invalid supplier ID selection
- File upload size limits

**Solutions**:
```bash
# Install CSV package if missing
composer require league/csv

# Check CSV format matches expected headers
# Verify supplier exists in database
# Check file upload limits in php.ini
```

#### 2. Product Matching Issues
**Symptoms**:
- All items marked as "new products"
- No existing products found during import

**Potential Causes**:
- `SupplierLink` table empty or incorrect
- Supplier ID mismatch between CSV and database
- Missing product relationships

**Debug Steps**:
```bash
php artisan tinker
# Check supplier link data
App\Models\SupplierLink::where('SupplierID', 5)->count();
# Verify supplier codes exist
App\Models\SupplierLink::where('SupplierCode', '115')->first();
```

#### 3. Barcode Scanning Problems
**Symptoms**:
- "Unknown product" for all scans
- Scans not registering in interface
- "Unauthenticated" errors when scanning

**Potential Causes**:
- Missing barcodes in delivery items
- Authentication issues with API routes
- JavaScript errors in browser console
- CSRF token problems

**Solutions**:
```bash
# Check delivery item barcodes
php artisan tinker
$delivery = App\Models\Delivery::find(ID);
$delivery->items->whereNull('barcode')->count();

# Refresh barcodes manually if needed
```

**Authentication Issue Fix (2025-08-04)**:
If you get "unauthenticated" errors when scanning barcodes, this was caused by JavaScript calling API routes (`/api/deliveries/{delivery}/scan`) while using session-based authentication. The fix ensures JavaScript uses web routes instead:
- ✅ Uses `/deliveries/{delivery}/scan` (web route with session auth)
- ✅ Uses `/deliveries/{delivery}/items/{item}/quantity` (web route)
- ❌ Avoid `/api/deliveries/...` routes (require token auth)

#### 4. Image Display Issues
**Symptoms**:
- No product images showing
- Placeholder icons everywhere
- Images not loading on hover

**Potential Causes**:
- `SupplierService` not injected properly
- External CDN connectivity issues
- Missing supplier integrations
- Product-supplier relationships not loaded

**Debug Steps**:
```bash
php artisan tinker
$service = new App\Services\SupplierService();
$product = App\Models\Product::first();
$service->hasExternalIntegration($product->supplier->SupplierID);
$service->getExternalImageUrl($product);
```

#### 5. Unit Cost Display Issues (Independent Format)
**Symptoms**:
- Unit costs showing as case prices (e.g., €21.44 instead of €1.79)
- "Add to POS" form shows incorrect pricing
- Tax calculations appear wrong

**Root Cause**: 
Independent Irish Health Foods CSV format uses **case prices** in the Price field, not unit prices.

**Solution Applied (2025-08-04)**:
- **Automatic Detection**: System detects Independent format by headers and supplier ID
- **Case-to-Unit Conversion**: Extracts units per case from product name (e.g., "12x90g" = 12 units)
- **Correct Calculation**: Unit cost = Case price ÷ Units per case
- **Example**: €21.44 case price ÷ 12 units = €1.79 per unit

**Debug Steps**:
```bash
php artisan tinker
# Test specific product parsing
$row = ['Code' => '19990B', 'Product' => 'Suma Hemp Oil & Vitamin E Soap 12x90g', 'Price' => '21.44'];
$service = new App\Services\DeliveryService(app(App\Services\UdeaScrapingService::class));
# Expected: €1.79 unit cost, not €21.44
```

#### 6. Performance Issues
**Symptoms**:
- Slow page loading on large deliveries
- Timeouts during CSV import
- Unresponsive scanning interface

**Potential Causes**:
- Large delivery files (>500 items)
- Missing database indexes
- Memory limits during import
- Unoptimized queries

**Solutions**:
```bash
# Increase memory limits for large imports
ini_set('memory_limit', '512M');

# Check for missing indexes
php artisan migrate:status

# Monitor query performance
php artisan telescope # if installed
```

### Database Troubleshooting

#### Verify Table Creation
```bash
php artisan migrate:status
# Should show: 2024_01_20_create_delivery_tables ... [Ran]

# If not migrated:
php artisan migrate
```

#### Check Relationships
```bash
php artisan tinker
$delivery = App\Models\Delivery::with(['supplier', 'items.product.supplier'])->first();
$delivery->supplier; // Should return Supplier model
$delivery->items->first()->product; // May be null for new products
```

#### Clean Up Test Data
```bash
# Remove test deliveries if needed
php artisan tinker
App\Models\Delivery::where('delivery_number', 'LIKE', 'DEL-%')->delete();
```

### Frontend Debugging

#### JavaScript Console Errors
- Check browser console for Alpine.js errors
- Verify CSRF token in meta tags
- Confirm API endpoints are accessible

#### CSS/Styling Issues
- Ensure Tailwind CSS is compiled
- Check for conflicting styles
- Verify responsive classes work on mobile

### Production Deployment Notes

#### Performance Considerations
- Index `delivery_items.supplier_code` for faster lookups
- Index `delivery_scans.barcode` for scan performance
- Consider Redis for real-time updates in high-volume environments

#### Security
- File upload validation is in place
- API endpoints require authentication
- CSRF protection on all forms

#### Monitoring
- Monitor CSV import performance
- Track scan success rates
- Alert on unusual discrepancy patterns

## Recent Enhancements (2025)

### Interface Improvements
1. **Prominent Supplier Display**: Enhanced supplier visibility throughout delivery interfaces
   - **Larger Supplier Names**: Increased font size and visual prominence in delivery tables
   - **Supplier Badges**: Color-coded supplier identification for quick recognition
   - **Supplier Headers**: Prominent supplier information in delivery detail views
   - **Visual Hierarchy**: Supplier information prioritized in layout design

2. **Delivery Management Controls**: Complete delivery lifecycle management
   - **Delete Functionality**: Ability to remove draft and cancelled deliveries
   - **Safety Checks**: Confirmation dialogs prevent accidental deletion
   - **Status Restrictions**: Only non-completed deliveries can be removed
   - **Cascade Deletion**: Associated items and scans are properly cleaned up
   - **Audit Trail**: Deletion events are logged for tracking

### Enhanced User Experience
- **Improved Navigation**: Clearer supplier identification reduces errors
- **Better Organization**: Supplier-centric view helps manage multiple suppliers
- **Flexible Management**: Ability to correct mistakes and remove test deliveries
- **Safety Features**: Multiple confirmation steps prevent data loss

## Future Enhancements

### Planned Features
1. **Batch Scanning**: Multiple barcode input at once
2. **Voice Commands**: Hands-free quantity entry
3. **Supplier Notifications**: Automatic discrepancy reporting
4. **Historical Analytics**: Delivery performance trends
5. **Mobile App**: Dedicated scanning application

### Integration Opportunities
1. **Weight Verification**: Integration with scales
2. **Temperature Monitoring**: Cold chain tracking
3. **Photo Documentation**: Damage recording
4. **Supplier APIs**: Direct integration replacing CSV uploads

## Testing

### Manual Testing Workflow
1. **Import Test**: Use `/tests/examples/udea_combined_output.csv`
2. **Scan Test**: Use existing product barcodes from database
3. **Summary Test**: Create discrepancies and verify reporting
4. **Image Test**: Check supplier image display and hover

### Key Test Cases
- CSV import with valid/invalid formats
- Barcode scanning with known/unknown codes
- Quantity adjustments and status updates
- Discrepancy calculation accuracy
- Image loading and error handling

---

**Last Updated**: 2025-01-20  
**System Status**: ✅ Fully Operational  
**Test Coverage**: Manual testing completed  
**Performance**: Tested with 292-item deliveries