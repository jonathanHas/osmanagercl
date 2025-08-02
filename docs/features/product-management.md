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
2. Inline form appears for specific field (name, tax, price, cost)
3. PATCH request sent to specific update endpoint
4. Controller validates and updates database
5. User redirected with success message

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
```

### Manual Testing
1. **Product Creation**: Test form validation, database insertion, and redirect
2. **Product Updates**: Test each update endpoint with valid/invalid data
3. **Stocking Toggle**: Verify database changes and UI feedback
4. **Search/Filtering**: Test product listing with various filter combinations
5. **Delivery Integration**: Create products from delivery items and verify context

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