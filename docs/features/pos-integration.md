# POS Database Integration

This document covers the integration with the uniCenta POS database, including models, relationships, and usage patterns.

## Overview

The application connects to a uniCenta POS database via a secondary database connection called `pos`. This integration provides read-only access to product, supplier, and inventory data.

## Database Connection

### Configuration
The POS connection is configured in `config/database.php`:

```php
'pos' => [
    'driver' => 'mysql',
    'host' => env('POS_DB_HOST', '127.0.0.1'),
    'port' => env('POS_DB_PORT', '3306'),
    'database' => env('POS_DB_DATABASE', 'unicenta'),
    'username' => env('POS_DB_USERNAME'),
    'password' => env('POS_DB_PASSWORD'),
    // ... other config
]
```

### Environment Variables
```env
POS_DB_HOST=127.0.0.1
POS_DB_PORT=3306
POS_DB_DATABASE=unicenta
POS_DB_USERNAME=your_username
POS_DB_PASSWORD=your_password
```

## Models

### Product Model (`app/Models/Product.php`)

**Table:** `PRODUCTS` (uppercase in uniCenta)
**Primary Key:** `ID` (non-incrementing string)
**Connection:** `pos`

#### Key Fields
- `ID` - Unique product identifier
- `CODE` - Barcode/product code (links to other tables)
- `NAME` - Product name
- `PRICESELL` - Selling price
- `PRICEBUY` - Purchase price
- `STOCKUNITS` - Current stock quantity
- `ISSERVICE` - Boolean flag for service items

#### Relationships
```php
// One-to-one with supplier link
public function supplierLink()
{
    return $this->hasOne(SupplierLink::class, 'Barcode', 'CODE');
}

// One-to-one with supplier (through supplier link)
public function supplier()
{
    return $this->hasOneThrough(
        Supplier::class,
        SupplierLink::class,
        'Barcode',         // FK on SupplierLink
        'SupplierID',      // FK on Supplier
        'CODE',            // Local key on Product
        'SupplierID'       // Local key on SupplierLink
    );
}

// One-to-one with stocking record
public function stocking()
{
    return $this->hasOne(Stocking::class, 'Barcode', 'CODE');
}

// One-to-one with current stock
public function stockCurrent()
{
    return $this->hasOne(StockCurrent::class, 'PRODUCT', 'ID');
}
```

#### Scopes
```php
// Active (non-service) products
Product::active()->get();

// Products in stock
Product::inStock()->get();

// Products that are stocked
Product::stocked()->get();

// Products with current stock
Product::inCurrentStock()->get();

// Search by name, code, or reference
Product::search('coffee')->get();
```

#### Helper Methods
```php
// Get current stock quantity
$stockQuantity = $product->getCurrentStock(); // Returns float
```

### Supplier Model (`app/Models/Supplier.php`)

**Table:** `suppliers`
**Primary Key:** `SupplierID` (non-incrementing string)
**Connection:** `pos`

#### Key Fields
- `SupplierID` - Unique supplier identifier
- `Supplier` - Supplier name
- `Phone` - Contact phone
- `Email` - Contact email
- `Address`, `PostCode`, `Country` - Address information
- `Supplier_Type_ID` - Supplier type classification

#### Relationships
```php
// One-to-one with supplier link
public function supplierLink()
{
    return $this->hasOne(SupplierLink::class, 'SupplierID', 'SupplierID');
}

// One-to-one with product (through supplier link)
public function product()
{
    return $this->hasOneThrough(
        Product::class,
        SupplierLink::class,
        'SupplierID',      // FK on SupplierLink
        'CODE',            // FK on Product
        'SupplierID',      // Local key on Supplier
        'Barcode'          // Local key on SupplierLink
    );
}
```

### SupplierLink Model (`app/Models/SupplierLink.php`)

**Table:** `supplier_link`
**Primary Key:** `ID` (auto-incrementing)
**Connection:** `pos`

#### Key Fields
- `ID` - Auto-incrementing primary key
- `Barcode` - Product code (links to products.CODE)
- `SupplierID` - Supplier identifier (links to suppliers.SupplierID)
- `SupplierCode` - Supplier's internal product code
- `Cost` - Product cost from supplier
- `CaseUnits` - Units per case
- `stocked` - Boolean flag
- `OuterCode` - Outer packaging code

#### Fillable Fields
```php
protected $fillable = [
    'Barcode',
    'SupplierCode', 
    'SupplierID',
    'CaseUnits',
    'stocked',
    'OuterCode',
    'Cost'
];
```

#### Relationships
```php
// Belongs to product
public function product()
{
    return $this->belongsTo(Product::class, 'Barcode', 'CODE');
}

// Belongs to supplier
public function supplier()
{
    return $this->belongsTo(Supplier::class, 'SupplierID', 'SupplierID');
}
```

### Stocking Model (`app/Models/Stocking.php`)

**Table:** `stocking`
**Primary Key:** `Barcode` (non-incrementing string)
**Connection:** `pos`

#### Purpose
The stocking table acts as a lookup table indicating which products are actively stocked. If a product's CODE appears in this table, it's considered a stocked item.

#### Fields
- `Barcode` - Product code (matches products.CODE)

#### Relationships
```php
// Belongs to product
public function product()
{
    return $this->belongsTo(Product::class, 'Barcode', 'CODE');
}
```

### StockCurrent Model (`app/Models/StockCurrent.php`)

**Table:** `STOCKCURRENT`
**Primary Key:** `PRODUCT` (for relationships - actual table has composite key)
**Connection:** `pos`

#### Purpose
The STOCKCURRENT table contains actual inventory quantities. This is the real-time stock data showing how many units are currently available.

#### Key Fields
- `LOCATION` - Warehouse/location identifier
- `PRODUCT` - Product ID (links to products.ID)
- `ATTRIBUTESETINSTANCE_ID` - Product variants/attributes (nullable)
- `UNITS` - Current stock quantity (decimal)

#### Relationships
```php
// Belongs to product
public function product()
{
    return $this->belongsTo(Product::class, 'PRODUCT', 'ID');
}
```

#### Usage Notes
- For single-location setups, this provides direct access to current stock levels
- `UNITS` field contains the actual quantity available
- Can be extended for multi-location inventory if needed

## Future Integration Opportunities

### VAT/Tax Integration (Pending Implementation)

The uniCenta database contains comprehensive VAT/tax structures that can be integrated:

#### Available Tables
- **TAXES** - VAT rates and tax definitions
  - `ID`, `NAME`, `CATEGORY`, `RATE`, `RATECASCADE`, `RATEORDER`
- **TAXCATEGORIES** - Tax category definitions
  - `ID`, `NAME`
- **PRODUCTS.TAXCAT** - Links products to tax categories

#### Potential Features
- Display VAT rates alongside product prices
- Calculate inclusive/exclusive pricing
- Filter products by tax category
- VAT compliance reporting
- Supplier cost analysis with VAT considerations

*See `next.md` for detailed implementation planning questions.*

### Additional uniCenta Tables
Other tables available for future integration:
- **CATEGORIES** - Product categorization system
- **ATTRIBUTES** - Product attribute/variant system
- **LOCATIONS** - Multi-location inventory management
- **STOCKDIARY** - Stock movement history and audit trail

## Repository Pattern

### SupplierRepository (`app/Repositories/SupplierRepository.php`)

Provides clean data access methods for supplier-related operations:

```php
$repository = new SupplierRepository();

// Get products with supplier information
$products = $repository->getProductsWithSuppliers(25);

// Get all products for a specific supplier
$products = $repository->getSupplierProducts('supplier-id');

// Update supplier cost for a product
$repository->updateSupplierCost('product-code', 15.99);

// Get products with low margins
$lowMarginProducts = $repository->getLowMarginProducts(20.0);

// Get supplier statistics
$stats = $repository->getSupplierStatistics('supplier-id');

// Find products without suppliers
$orphanProducts = $repository->getProductsWithoutSuppliers();
```

## Usage Examples

### Basic Queries

```php
// Get products with all relationships
$products = Product::with(['supplierLink', 'supplier', 'stocking'])
    ->paginate(25);

// Get only products that have suppliers
$productsWithSuppliers = Product::whereHas('supplierLink')
    ->with(['supplierLink', 'supplier'])
    ->get();

// Get stocked products with suppliers
$stockedWithSuppliers = Product::stocked()
    ->whereHas('supplierLink')
    ->with(['supplierLink', 'supplier', 'stocking'])
    ->get();
```

### Checking Relationships

```php
// Check if product has supplier
if ($product->supplierLink) {
    echo "Supplier: " . $product->supplier->Supplier;
    echo "Cost: $" . $product->supplierLink->Cost;
}

// Check if product is stocked
if ($product->stocking) {
    echo "Product is actively stocked";
}

// Calculate margin
if ($product->supplierLink && $product->supplierLink->Cost > 0) {
    $margin = (($product->PRICESELL - $product->supplierLink->Cost) / $product->PRICESELL) * 100;
    echo "Margin: " . number_format($margin, 1) . "%";
}
```

### Advanced Queries

```php
// Products with high margins
$highMarginProducts = Product::with(['supplierLink'])
    ->whereHas('supplierLink', function ($query) {
        $query->where('Cost', '>', 0);
    })
    ->get()
    ->filter(function ($product) {
        $cost = $product->supplierLink->Cost;
        $price = $product->PRICESELL;
        $margin = (($price - $cost) / $price) * 100;
        return $margin > 50;
    });

// Suppliers with product counts
$suppliersWithCounts = Supplier::withCount('supplierLink')->get();

// Products missing from stocking table
$unstockedProducts = Product::doesntHave('stocking')
    ->active()
    ->get();
```

## Database Schema

### Table Relationships Diagram
```
products (PRODUCTS)
├── ID (PK) ─────┐
├── CODE ────┐   │
├── NAME     │   │
└── PRICESELL│   │
             │   │
             ├── supplier_link
             │   ├── ID (PK)
             │   ├── Barcode (FK → products.CODE)
             │   ├── SupplierID (FK → suppliers.SupplierID)
             │   ├── Cost
             │   └── SupplierCode
             │
             ├── suppliers
             │   ├── SupplierID (PK)
             │   ├── Supplier (name)
             │   ├── Phone
             │   └── Email
             │
             ├── stocking
             │   └── Barcode (PK → products.CODE)
             │
             └── STOCKCURRENT
                 ├── PRODUCT (FK → products.ID)
                 ├── LOCATION
                 ├── UNITS (current stock)
                 └── ATTRIBUTESETINSTANCE_ID
```

### Foreign Key Relationships
- `supplier_link.Barcode` → `products.CODE`
- `supplier_link.SupplierID` → `suppliers.SupplierID`
- `stocking.Barcode` → `products.CODE`
- `STOCKCURRENT.PRODUCT` → `products.ID`

## Views and Controllers

### ProductController Methods

```php
// Display products with supplier information
public function suppliersIndex(Request $request)
{
    $products = Product::with(['supplierLink', 'supplier', 'stocking', 'stockCurrent'])
        ->select(['ID', 'CODE', 'NAME', 'PRICESELL'])
        ->paginate(25);

    return view('products.supplier-test', compact('products'));
}
```

### Sample Blade Views

**Products with Suppliers:** `resources/views/products/supplier-test.blade.php`
- Displays products in a table
- Shows supplier name, cost, margin calculation
- Shows current stock levels
- Indicates stocking status with badges

**Debug Views:** `resources/views/debug/`
- `suppliers.blade.php` - Raw table data and structure
- `product-suppliers.blade.php` - Relationship debugging

## Debug Routes

Useful routes for testing and debugging:

- `/debug/suppliers` - View raw supplier and supplier_link data
- `/debug/product-suppliers` - Check product-supplier relationships
- `/products/suppliers` - Main products with suppliers view

## Performance Considerations

### Eager Loading
Always use eager loading to prevent N+1 queries:
```php
// Good
$products = Product::with(['supplierLink', 'supplier', 'stocking'])->get();

// Bad - will cause N+1 queries
$products = Product::all();
foreach ($products as $product) {
    echo $product->supplier->Supplier; // N+1 query!
}
```

### Indexing
Ensure these fields are indexed in the POS database:
- `supplier_link.Barcode`
- `supplier_link.SupplierID`
- `stocking.Barcode`

### Query Optimization
```php
// Select only needed columns
$products = Product::select(['ID', 'CODE', 'NAME', 'PRICESELL'])
    ->with([
        'supplierLink:Barcode,SupplierID,Cost,SupplierCode',
        'supplier:SupplierID,Supplier'
    ])
    ->get();
```

## Troubleshooting

### Common Issues

1. **No suppliers showing**
   - Check that `supplier_link.Barcode` matches `products.CODE`
   - Verify the field name is `Supplier` not `Name` in suppliers table

2. **Connection errors**
   - Verify POS database credentials in `.env`
   - Check network connectivity to POS database

3. **Missing relationships**
   - Use debug routes to verify data exists
   - Check that foreign key values match exactly

### Debugging Commands

```php
// In tinker
$product = Product::first();
$product->supplierLink; // Should return SupplierLink or null
$product->supplier;     // Should return Supplier or null
$product->stocking;     // Should return Stocking or null

// Check raw data
DB::connection('pos')->table('supplier_link')->first();
DB::connection('pos')->table('suppliers')->first();
```

## Future Enhancements

### Potential Additions
1. **Supplier Management UI** - CRUD operations for suppliers
2. **Cost History Tracking** - Track supplier cost changes over time
3. **Purchase Order Integration** - Link to PO system
4. **Inventory Alerts** - Low stock notifications based on stocking table
5. **Supplier Performance Metrics** - Delivery times, quality scores
6. **Bulk Operations** - Mass update costs, change suppliers

### Additional Tables
Consider integrating other uniCenta tables:
- `categories` - Product categorization
- `taxcategories` - Tax information
- `locations` - Multi-location inventory
- `stockdiary` - Stock movement history