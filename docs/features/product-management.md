# Product Management

## Overview

The Product Management system provides comprehensive CRUD operations for managing products in the OSManager CL application. It integrates with the uniCenta POS database to maintain product catalog information, including pricing, categorization, tax settings, and inventory management. The system supports both standalone product management and delivery-integrated product creation workflows.

## Architecture

### Components
- **ProductController**: Handles HTTP requests for product operations
- **ProductRepository**: Data access layer for product queries and statistics
- **Product Model**: Eloquent model representing POS database products
- **VegDetails Model**: Links to POS vegDetails table for fruit/vegetable metadata
- **VegClass Model**: Connects to POS class table for quality classifications (I, II, III)
- **Stocking Model**: Manages which products are included in stock operations
- **StoreProductRequest**: Validates product creation/update requests

### Database Schema

The system primarily works with the uniCenta POS database:

```sql
-- Main product table (POS database)
CREATE TABLE PRODUCTS (
    ID VARCHAR(255) PRIMARY KEY,
    NAME VARCHAR(255) NOT NULL,
    CODE VARCHAR(50) UNIQUE NOT NULL,
    REFERENCE VARCHAR(100),
    CATEGORY VARCHAR(255),
    TAXCAT VARCHAR(255),
    PRICESELL DECIMAL(10,4),
    PRICEBUY DECIMAL(10,2),
    DISPLAY TEXT,
    IMAGE BLOB
);

-- Stocking management table (POS database)
CREATE TABLE stocking (
    Barcode VARCHAR(50) PRIMARY KEY
);

-- Vegetable details table (POS database)
CREATE TABLE vegDetails (
    ID VARCHAR(255) PRIMARY KEY,
    product VARCHAR(50) NOT NULL,
    countryCode INT,
    classId INT,
    unitId INT,
    FOREIGN KEY (product) REFERENCES PRODUCTS(CODE),
    FOREIGN KEY (classId) REFERENCES class(ID)
);

-- Class quality grades table (POS database)
CREATE TABLE class (
    ID INT PRIMARY KEY,
    classNum INT NOT NULL,
    class VARCHAR(10) NOT NULL  -- I, II, III
);
```

### Data Flow

#### Product Creation
1. User accesses create form (`/products/create`)
2. Form validates input via `StoreProductRequest`
3. `ProductController::store()` processes the request:
   - Converts VAT-inclusive price to VAT-exclusive for storage
   - Product created in POS database with UUID
   - Optional stocking entry created
4. User redirected to product detail page

#### Product Price Updates
1. User clicks edit price button on product detail page
2. Enhanced price editor modal opens with dual input modes
3. User can enter either gross price (inc VAT) or net price (ex VAT)
4. Real-time calculations show pricing breakdown
5. `ProductController::updatePrice()` processes the request:
   - Handles both gross and net price inputs
   - Converts to net price for database storage
   - Updates PRICESELL field with VAT-exclusive price

#### Product Updates
1. User clicks edit button on product detail page
2. Inline form appears for specific field (name, tax, price, cost, barcode)
3. PATCH request sent to specific update endpoint
4. Controller validates and updates database
5. User redirected with success message

#### Cost Update System
The system provides multiple ways to update product costs with enhanced delivery integration:

1. **Traditional Cost Update**:
   - Form-based update on product detail page
   - Validation: numeric, min 0, max 999,999.99
   - Returns redirect response with success message

2. **AJAX Cost Update** (Enhanced 2025-08-08):
   - JSON API support for programmatic updates
   - Route: `PATCH /products/{id}/cost`
   - Dual response handling:
     ```php
     if ($request->expectsJson()) {
         return response()->json([
             'message' => 'Cost updated successfully.',
             'cost' => $request->cost_price
         ]);
     }
     return redirect()->route('products.show', $id);
     ```

3. **Delivery Integration Cost Updates**:
   - **Quick Update Arrows**: One-click cost updates from delivery review pages
   - **Visual Indicators**: Arrow buttons appear when delivery cost differs from product cost
   - **Confirmation Dialogs**: Clear cost change confirmations before updates
   - **Real-time Feedback**: Success messages and automatic page refresh
   - **Smart Conditions**: Only available for non-completed deliveries with differences >€0.01

#### Barcode Updates
1. User clicks edit icon next to SKU/barcode on product detail page
2. Warning form appears with:
   - List of affected records that will be updated
   - Confirmation checkbox requirement
   - New barcode input field
3. `ProductController::updateBarcode()` processes the request:
   - Validates new barcode is unique
   - Updates product CODE in transaction
   - Updates all dependent records:
     - supplier_link records
     - stocking records (recreates with new PK)
     - label_logs records
     - product_metadata records
     - veg_details records
   - Creates audit trail in label_logs
4. User redirected with success/error message

### Cross-Database Relationships

The system implements sophisticated cross-database relationships to integrate POS data with application data:

#### VegDetails Model Configuration
```php
// Connects to POS database
protected $connection = 'pos';
protected $table = 'vegDetails';

// Cross-database relationships
public function vegClass()
{
    return $this->belongsTo(VegClass::class, 'classId', 'ID');
}

public function country()
{
    // Cross-database: POS vegDetails -> main DB countries
    return $this->setConnection('mysql')->belongsTo(Country::class, 'countryCode', 'id');
}
```

#### Field Mappings
- **POS Database**: Uses original uniCenta field names (`product`, `classId`, `countryCode`, `unitId`)
- **Application Database**: Uses Laravel conventions (`country_id`, `class_id`, etc.)
- **Accessors**: Bridge the gap for backward compatibility (`class_name`, `unit_name`)

## Configuration

### Environment Variables
```env
# POS Database Connection
POS_DB_CONNECTION=mysql
POS_DB_HOST=localhost
POS_DB_PORT=3306
POS_DB_DATABASE=unicenta
POS_DB_USERNAME=pos_user
POS_DB_PASSWORD=pos_password
```

### Config Files
- `config/database.php` - POS database connection configuration
- `config/suppliers.php` - External supplier integration settings

## Usage

### User Perspective

#### Creating Products
1. Navigate to **Products** → **Create New Product**
2. Fill in basic information (name, code, pricing)
3. Select category and tax settings
4. Configure supplier information (optional)
5. Choose whether to include in stock management
6. Submit form to create product

#### Editing Product Information
1. Navigate to product detail page
2. Click the **edit icon** next to any editable field:
   - **Product Name**: Click pencil icon in header
   - **Display Name**: Click edit icon in Additional Information section
   - **Tax Category**: Use dropdown in Tax Configuration section
   - **Selling Price**: Use Quick Price Update or detailed form
   - **Cost Price**: Use cost update form
3. Make changes and save
4. System provides confirmation feedback

#### Managing Stock Status
1. View stocking status in "Quick Stats Bar"
2. Toggle stock management inclusion using **Add/Remove** button
3. Products included in stocking are considered for automated ordering

### Developer Perspective

#### Key Models and Relationships
```php
// Product model with relationships
class Product extends Model
{
    protected $connection = 'pos';
    protected $table = 'PRODUCTS';
    
    public function taxCategory() { /* Tax relationship */ }
    public function category() { /* Category relationship */ }
    public function supplierLink() { /* Supplier relationship */ }
    public function stocking() { /* Stock management flag */ }
}
```

#### Controller Methods
```php
// Product management endpoints
public function index()     // List products with filtering
public function show($id)   // Product detail page
public function create()    // Create form
public function store()     // Save new product
public function updateName() // Update product name
public function updateDisplay() // Update display name
public function updateTax()  // Update tax category
public function updatePrice() // Update selling price
public function updateCost()  // Update cost price
public function toggleStocking() // Toggle stock management
```

#### API Endpoints
- `GET /products` - List products with search/filtering
- `GET /products/create` - Show create form
- `POST /products` - Create new product
- `GET /products/{id}` - Show product details
- `PATCH /products/{id}/name` - Update product name
- `PATCH /products/{id}/display` - Update display name
- `PATCH /products/{id}/tax` - Update tax category
- `PATCH /products/{id}/price` - Update selling price
- `PATCH /products/{id}/cost` - Update cost price
- `POST /products/{id}/toggle-stocking` - Toggle stock management

### Integration Points

#### Delivery System Integration
- Products can be created directly from delivery items
- Delivery context maintained through navigation
- Pre-populated forms with supplier and cost information
- Automatic barcode assignment from delivery data
- **Automatic Tax Category Selection**: For Independent Irish Health Foods deliveries, tax categories are automatically selected based on calculated VAT rates

#### Label System Integration
- Products can be queued for label printing
- Print labels directly from product detail page
- Re-queue products for label printing when needed
- Support for multiple label templates including new 4x9 grid layout
- Optimized price display sizing for clear readability

#### Supplier Integration
- Automatic price fetching from external suppliers (UDEA)
- Supplier code linking for inventory management
- Cost and pricing recommendations based on supplier data
- **Independent Health Foods Integration**:
  - Product images automatically displayed when supplier code is entered
  - Smart image path detection (supports both `/cdn/shop/files/` and `/cdn/shop/products/`)
  - Multiple format support (.webp and .jpg)
  - Click-to-view full-size modal for product images
  - Direct website search links to Independent's product pages
  - Test page available at `/products/independent-test` for debugging

## Enhanced Features

### Enhanced Price Editor (2025)

The product price editing system has been enhanced with a comprehensive modal interface that provides flexibility and real-time feedback.

#### Key Features

1. **Dual Input Modes**
   - **Gross Price Mode**: Enter price including VAT, system auto-calculates net price
   - **Net Price Mode**: Traditional ex-VAT price entry
   - Toggle between modes with visual indicators

2. **Real-time Pricing Breakdown**
   - Cost price display
   - Net price (ex VAT) calculation
   - VAT amount and rate display
   - Gross price (inc VAT) calculation
   - Profit margin analysis with color coding:
     - Red: < 10% margin (warning)
     - Yellow: 10-20% margin (caution)
     - Green: > 20% margin (good)

3. **Enhanced UX**
   - Modal dialog interface instead of inline form
   - Price change preview before submission
   - Visual consistency with product creation form
   - Improved validation and error handling

#### Technical Implementation

**Frontend Components:**
- `product-price-editor.blade.php` - Modal component with dual input modes
- JavaScript for real-time calculations and mode switching
- Integration with existing pricing section component

**Backend Updates:**
- Enhanced `ProductController::updatePrice()` method
- Support for both gross and net price inputs
- Automatic VAT conversion using tax category rates
- Improved validation rules based on input mode

**VAT Handling:**
```php
// Convert gross price to net for storage
if ($request->price_input_mode === 'gross') {
    $taxCategory = TaxCategory::with('primaryTax')->find($product->TAXCAT);
    $vatRate = $taxCategory?->primaryTax?->RATE ?? 0.0;
    $netPrice = $vatRate > 0 ? $request->gross_price / (1 + $vatRate) : $request->gross_price;
}
```

### Automatic Tax Category Selection

**Overview**: When creating products from Independent Irish Health Foods delivery items, the system automatically pre-selects the appropriate tax category based on the calculated VAT rate from the delivery data.

**Process**:
1. **VAT Rate Calculation**: System calculates VAT rate using formula: `(Tax ÷ Value) × 100`
2. **Irish VAT Normalization**: Calculated rates are normalized to standard Irish VAT rates
3. **Tax Category Mapping**: Normalized rates are mapped to POS tax category IDs
4. **Form Pre-population**: Tax category dropdown is automatically selected
5. **Visual Feedback**: Green styling indicates auto-selected fields

**VAT Rate Mappings**:
```php
// POS Tax Category Mapping
return match ($taxRate) {
    0.0 => '000',    // Tax Zero
    9.0 => '003',    // Tax Second Reduced (9%)
    13.5 => '001',   // Tax Reduced (13.5%)
    23.0 => '002',   // Tax Standard (23%)
    default => null, // Manual selection required
};
```

**Supported Tax Rates**:
- **0%**: Essential foods, books, medicines
- **9%**: Tourism, restaurants, some services
- **13.5%**: Fuel, electricity, building materials
- **23%**: Standard rate for most goods and services

**Visual Indicators**:
- **Green Background**: Tax category field when auto-selected
- **"Auto-selected" Label**: Displayed next to pre-filled dropdown
- **Tooltip Information**: Shows source VAT rate and calculation details

### Barcode Editing Feature (2025-08-07)

#### Overview
The barcode editing feature allows administrators to modify product barcodes after creation, addressing issues from faulty scanners or incorrect data entry. This feature includes comprehensive safety measures to ensure data integrity across all related systems.

#### Key Features

1. **Inline Editing Interface**
   - Edit button next to SKU display on product detail page
   - Warning form with yellow highlighting for visibility
   - Clear indication of current and new barcode values

2. **Safety Measures**
   - Confirmation checkbox required before changes
   - Clear warning about affected records
   - Transaction-based updates for atomicity
   - Rollback on any failure

3. **Comprehensive Updates**
   - All barcode references updated automatically:
     - `supplier_link` table (Barcode field)
     - `stocking` table (Barcode as primary key - recreated)
     - `label_logs` table (barcode field)
     - `product_metadata` table (product_code field)
     - `veg_details` table (product_code field)

4. **Audit Trail**
   - Creates 'barcode_change' event in label_logs
   - Stores old and new barcode in JSON metadata
   - Tracks user who made the change
   - Timestamp for change tracking

#### Technical Implementation

```php
// UpdateBarcodeRequest validation
'barcode' => [
    'required',
    'string',
    'max:255',
    Rule::unique('pos.PRODUCTS', 'CODE')->ignore($productId, 'ID'),
],
'confirm' => 'required|accepted'

// Controller method with transaction
DB::transaction(function () use ($product, $oldBarcode, $newBarcode) {
    // Update product CODE
    $product->update(['CODE' => $newBarcode]);
    
    // Update all dependent records
    SupplierLink::where('Barcode', $oldBarcode)->update(['Barcode' => $newBarcode]);
    
    // Handle stocking table (PK change)
    $stockingRecord = Stocking::find($oldBarcode);
    if ($stockingRecord) {
        $data = $stockingRecord->toArray();
        $stockingRecord->delete();
        $data['Barcode'] = $newBarcode;
        Stocking::create($data);
    }
    
    // Create audit log
    LabelLog::create([
        'barcode' => $newBarcode,
        'event_type' => 'barcode_change',
        'user_id' => auth()->id(),
        'metadata' => json_encode([
            'old_barcode' => $oldBarcode,
            'new_barcode' => $newBarcode,
        ]),
    ]);
});
```

#### Why It's Safe

1. **Sales Unaffected**: TICKETLINES, STOCKDIARY, STOCKCURRENT reference products by ID, not CODE
2. **Transaction Safety**: All updates happen together or rollback
3. **Validation**: Ensures new barcode is unique
4. **Audit Trail**: Changes are logged for accountability

### Display Name Management (2025)

The product management system now supports inline editing of display names for all products, providing consistent functionality across the entire application.

#### Overview

Display names (stored in the `DISPLAY` field) are used on labels and displays as an alternative to the main product name. This feature allows for customized product presentation without changing the core product name used for inventory tracking.

#### Key Features

1. **Inline Editing Interface**
   - Click-to-edit functionality with pencil icon
   - Multi-line textarea with HTML support
   - Save/Cancel buttons for user control
   - Visual feedback on save operations

2. **HTML Support**
   - Supports `<br>` tags for line breaks in display names
   - Automatic HTML entity handling and conversion
   - Real-time preview of formatting changes
   - Safe HTML rendering in display areas

3. **User Experience**
   - Consistent with fruit-veg module editing patterns
   - Auto-focus on textarea when editing begins
   - Loading states and success indicators
   - Error handling with user-friendly messages

#### Technical Implementation

**Frontend Components:**
```html
<!-- Display Name Editor -->
<div>
    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
        Display Name
        <button onclick="toggleDisplayEdit()" class="ml-2 inline-flex items-center p-1">
            <!-- Edit icon -->
        </button>
    </dt>
    <dd class="mt-1">
        <!-- Display value (default view) -->
        <div id="displayValue">
            <!-- Current display name or placeholder -->
        </div>
        
        <!-- Edit form (hidden by default) -->
        <div id="displayEditForm" class="hidden">
            <textarea id="displayInput" rows="3" placeholder="Enter custom display name...">
            </textarea>
            <button onclick="saveDisplayName()">Save</button>
            <button onclick="cancelDisplayEdit()">Cancel</button>
        </div>
    </dd>
</div>
```

**Backend API:**
```php
/**
 * Update product display field.
 */
public function updateDisplay(Request $request, string $id)
{
    $request->validate([
        'display' => 'nullable|string|max:255',
    ]);

    $product = $this->productRepository->findById($id);
    $product->update(['DISPLAY' => $request->display]);

    return response()->json(['success' => true]);
}
```

**JavaScript Functions:**
- `toggleDisplayEdit()` - Shows edit form and focuses textarea
- `cancelDisplayEdit()` - Cancels editing and restores original value  
- `saveDisplayName()` - Saves via AJAX with loading states and feedback

#### Usage Examples

**Setting a Display Name:**
1. Navigate to any product detail page
2. Scroll to "Additional Information" section
3. Click the edit icon next to "Display Name"
4. Enter custom display text (supports `<br>` for line breaks)
5. Click "Save" to persist changes

**HTML Formatting:**
```
Fresh Organic Apples<br>Class I - Ireland
```
Will display as:
```
Fresh Organic Apples
Class I - Ireland
```

#### Integration Benefits

- **Consistent UX**: Same editing pattern as fruit-veg products
- **Label System**: Display names automatically used in label generation
- **Backward Compatible**: Empty display names fall back to product name
- **Cross-Module**: Works for all product categories, not just F&V

### Enhanced Product Search & Filtering (2025)

The product listing page has been improved with better supplier filtering capabilities.

#### Features

1. **Dynamic Supplier Dropdown**
   - Suppliers always loaded for immediate availability
   - Dropdown appears instantly when "Show suppliers" is checked
   - No form submission required to populate dropdown
   - Automatic reset when dropdown is hidden

2. **Improved JavaScript Integration**
   ```javascript
   // Show/hide supplier dropdown on checkbox change
   showSuppliersCheckbox.addEventListener('change', function() {
       if (this.checked) {
           supplierDropdown.classList.remove('hidden');
       } else {
           supplierDropdown.classList.add('hidden');
           supplierSelect.value = ''; // Reset selection
       }
   });
   ```

3. **Better Performance**
   - Suppliers filtered by current search criteria
   - Efficient loading strategy
   - Maintained backward compatibility

## Testing

### Feature Tests
```php
/** @test */
public function user_can_create_product()
{
    $response = $this->post('/products', [
        'name' => 'Test Product',
        'code' => 'TEST001',
        'price_buy' => 10.00,
        'price_sell' => 15.00,
        'tax_category' => 'tax-id'
    ]);
    
    $response->assertRedirect();
    $this->assertDatabaseHas('PRODUCTS', ['CODE' => 'TEST001']);
}

/** @test */
public function user_can_update_product_name()
{
    $product = Product::factory()->create();
    
    $response = $this->patch("/products/{$product->ID}/name", [
        'product_name' => 'Updated Name'
    ]);
    
    $response->assertRedirect();
    $this->assertEquals('Updated Name', $product->fresh()->NAME);
}

/** @test */
public function user_can_update_product_display_name()
{
    $product = Product::factory()->create();
    
    $response = $this->patch("/products/{$product->ID}/display", [
        'display' => 'Custom Display<br>Line 2'
    ]);
    
    $response->assertOk();
    $response->assertJson(['success' => true]);
    $this->assertEquals('Custom Display<br>Line 2', $product->fresh()->DISPLAY);
}
```

### Manual Testing
1. **Product Creation**: Test form validation, database insertion, and redirect
2. **Product Updates**: Test each update endpoint with valid/invalid data
3. **Display Name Editing**: Test inline editing with HTML content and validation
4. **Stocking Toggle**: Verify database changes and UI feedback
5. **Search/Filtering**: Test product listing with various filter combinations
6. **Delivery Integration**: Create products from delivery items and verify context

## Troubleshooting

### Common Issues

#### Issue: Product creation fails with UUID error
**Symptoms**: "Invalid UUID" error during product creation
**Cause**: UUID generation or database constraint issues
**Solution**: Check database connection and UUID field configuration

#### Issue: Stocking toggle not working
**Symptoms**: Button click doesn't update stocking status
**Cause**: JavaScript error or missing CSRF token
**Solution**: Check browser console and verify CSRF token in page meta

#### Issue: Product name editing not saving
**Symptoms**: Edit form appears but changes don't persist
**Cause**: Route not found or validation failure
**Solution**: Verify route registration and check validation rules

### Debug Tips
- Check `storage/logs/laravel.log` for validation errors
- Verify POS database connection in `.env` file
- Use browser developer tools to inspect AJAX requests
- Check that Product model fillable array includes updated fields

### FAQ

**Q: Why do products need UUIDs instead of auto-incrementing IDs?**
A: The POS system requires UUID primary keys for compatibility with the uniCenta POS software.

**Q: What's the difference between CODE and REFERENCE fields?**
A: CODE is the unique barcode/SKU, while REFERENCE is an optional internal reference number.

**Q: How does stocking management work?**
A: Products in the `stocking` table are included in automated ordering calculations. This is a flag to indicate which products should be regularly stocked vs one-time items.

## Security Considerations

- All product operations require authentication
- Input validation prevents SQL injection and XSS
- Product names and codes are sanitized before database storage
- CSRF protection on all form submissions
- Rate limiting on search and listing endpoints

## Performance Optimization

- Product queries use proper database indexing
- Pagination implemented for large product catalogs
- Eager loading of relationships to prevent N+1 queries
- Caching of tax category and supplier dropdowns
- Optimized search queries with database indexes

## Recent Updates

### Auto-Barcode Suggestion System (2025-08-13)

Implemented a comprehensive, configuration-driven barcode suggestion system that automatically suggests the next available barcode for supported product categories.

#### Supported Categories

The system supports automatic barcode suggestions for the following categories:

| Category | ID | Range | Priority | Description |
|----------|-------|--------|----------|-------------|
| **Coffee Fresh** | 081 | 4000-4999 | Fill Gaps | Coffee products use 4000s sequence, fills gaps first |
| **Fruit** | SUB1 | 1000-2999 | Increment | Fresh fruit products use sequential numbering |
| **Vegetables** | SUB2 | 1000-2999 | Increment | Fresh vegetable products use sequential numbering |
| **Bakery** | 082 | 4000-4999 | Fill Gaps | Bakery products use 4000s sequence, fills gaps first |
| **Zero Waste Food** | 083 | 7000-7999 | Increment | Zero waste and lunch products use 7000s sequence |
| **Lunches** | 50918faf... | 4000-4999 | Increment | Lunch products use 4000s sequence |

#### Key Features

1. **Category-Specific Suggestions**
   - Each category has its own numbering pattern and rules
   - Configurable ranges and priorities per category
   - Smart handling of overlapping ranges (Coffee Fresh + Bakery both use 4000s)

2. **Global Barcode Uniqueness**
   - Checks across ALL categories to prevent conflicts
   - Ensures barcodes are unique across the entire system
   - Handles cross-category range overlaps intelligently

3. **Smart Numbering Logic**
   - **Fill Gaps**: Some categories fill sequence gaps first (Coffee Fresh, Bakery)
   - **Incremental**: Others always increment from highest (Fruit, Vegetables, Zero Waste)
   - **Range Validation**: Ensures suggestions stay within configured ranges

4. **Enhanced User Interface**
   - Category-specific badges (e.g., "Coffee Fresh Auto-Suggested")
   - Dynamic descriptions explaining numbering patterns
   - Visual indicators showing suggested barcodes
   - Easy to override suggestions if needed

#### Usage

To create a product with auto-suggested barcode, use category-specific URLs:

- **Coffee Fresh**: `/products/create?category=081`
- **Fruit**: `/products/create?category=SUB1`
- **Vegetables**: `/products/create?category=SUB2`
- **Bakery**: `/products/create?category=082`
- **Zero Waste**: `/products/create?category=083`

#### Configuration

The system is configured via `config/barcode_patterns.php`:

```php
'categories' => [
    '081' => [
        'name' => 'Coffee Fresh',
        'ranges' => [[4000, 4999]],
        'priority' => 'fill_gaps',
        'description' => 'Coffee Fresh products use the 4000s numbering sequence',
    ],
    // ... other categories
],

'settings' => [
    'max_search_range' => 200,
    'max_internal_code' => 99999,
    'default_start' => 1000,
]
```

#### Technical Implementation

**Core Methods:**
- `getNextAvailableBarcodeForCategory($categoryId)` - Main suggestion logic
- `isCodeAvailableInRange($code, $ranges)` - Range validation
- Configuration-driven approach for easy extensibility

**Algorithm:**
1. Load category configuration (ranges, priority, description)
2. Get existing codes for the category within internal code range
3. Apply category-specific logic (fill gaps vs increment)
4. Check global availability across all products
5. Return first available code within configured ranges

**Frontend Integration:**
- Auto-populates barcode field when category is detected
- Shows category-specific messaging and styling
- Maintains all existing functionality for manual barcode entry

#### Benefits

- **Streamlined Workflow**: Automatic barcode suggestions speed up product creation
- **Consistency**: Maintains category-specific numbering patterns
- **Conflict Prevention**: Global uniqueness checking prevents duplicate barcodes
- **Extensible**: Easy to add new categories via configuration
- **User-Friendly**: Clear visual indicators and helpful descriptions

### Cost Price Editing in Modal (2025-08-08)

Enhanced the price editor modal to include inline cost price editing capabilities.

#### Features
- **Inline Cost Editing**: Edit button next to cost price in pricing breakdown
- **Real-time Updates**: Margin calculations update automatically
- **No Page Refresh**: AJAX-based updates for smooth UX
- **UUID Support**: Handles both numeric and UUID product IDs
- **Visual Feedback**: Clear edit/save/cancel workflow

#### Technical Implementation
- Cost price updates via `/products/{id}/cost` endpoint
- Supports POS database write operations
- Validation for numeric values (0-999999.99)
- Error handling with user-friendly messages

### Supplier-Specific UI Components (2025-08-08)

Dynamic display of pricing cards based on supplier type.

#### Features
- **UDEA-Only Components**:
  - Supplier Pricing card with cost comparisons
  - Quick Actions for price updates
  - Advanced pricing analysis section
- **Adaptive Layout**: Grid adjusts from 3 columns (UDEA) to 1 column (others)
- **Smart Pricing Suggestions**: 
  - Optimal pricing (35% margin on total cost)
  - Competitive pricing (+10% above supplier)
- **One-Click Updates**: Apply supplier prices directly

### Delivery Cost Quick Update (2025-08-08)

Added quick cost update functionality to delivery pages.

#### Features
- **Arrow Button**: Update product cost from delivery item
- **Inline UI Updates**: No page refresh required
- **Visual Indicators**: Color-coded cost differences
- **Confirmation Dialog**: Prevents accidental updates
- **Batch Processing**: Handle multiple updates efficiently

## Future Enhancements

- Bulk product import/export functionality
- Advanced pricing rules and discount management
- Product image management and optimization
- Inventory tracking and low-stock alerts
- Product variant support (size, color, etc.)
- Enhanced reporting and analytics
- Multi-language product descriptions

## Related Documentation

- [POS Integration](./pos-integration.md) - Database integration details
- [Delivery System](./delivery-system.md) - Product creation from deliveries
- [Label System](./label-system.md) - Product labeling workflow
- [Supplier Integration](./supplier-integration.md) - External supplier data
- [Pricing System](./pricing-system.md) - VAT calculations and pricing logic