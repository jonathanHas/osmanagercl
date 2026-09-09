# Supplier Management System

## Overview
The Supplier Management System provides a unified interface for managing suppliers across both the Laravel accounting system and the POS (Point of Sale) system. Implemented on September 11, 2025, this system ensures seamless integration between expense tracking and product supplier management.

## Key Features

### 1. Auto-Generated Supplier Codes
- Automatic generation of sequential codes (SUP-0001, SUP-0002, etc.)
- Manual override option for custom codes
- Ensures uniqueness across the system
- Clear placeholder text guides users

### 2. POS Integration
- Optional checkbox to create suppliers in both systems simultaneously
- Links accounting suppliers to POS suppliers via `external_pos_id`
- Automatic ID generation for POS entries (SUP + 6-digit ID)
- Bi-directional synchronization capabilities

### 3. Database Architecture

#### Dual Database System
- **Main Database (Port 3306)**: `accounting_suppliers` table
- **POS Database (Port 3307)**: `suppliers` table

#### Key Fields
```sql
-- accounting_suppliers table (Main DB)
id                  -- Primary key
code                -- Unique supplier code (e.g., SUP-0001)
name                -- Supplier name
external_pos_id     -- Links to POS supplier ID
is_pos_linked       -- Boolean flag for POS integration
payment_terms_days  -- Default: 30 days

-- suppliers table (POS DB)
SupplierID          -- Primary key (e.g., SUP001274)
Supplier            -- Supplier name
```

## User Interface

### Create Supplier Form (`/suppliers/create`)

#### Features
- **Auto-Code Generation**: Leave code field blank for automatic generation
- **POS Integration Checkbox**: "Also create in POS system"
- **Smart Defaults**: Payment terms default to 30 days
- **Comprehensive Validation**: All fields validated with helpful error messages
- **Loading States**: Submit button shows spinner during processing
- **Flash Messages**: Success/error feedback displayed prominently

#### Form Fields
- Supplier Code (optional - auto-generated if blank)
- Supplier Name (required)
- Supplier Type (product, service, utility, professional, other)
- Status (active, inactive, suspended, archived)
- Contact Information (phone, email, address, etc.)
- Financial Details (VAT number, payment terms, bank details)
- POS Integration checkbox

### Edit Supplier Form (`/suppliers/{id}/edit`)

#### Features
- **POS Status Display**: Shows if supplier is linked to POS
- **Create in POS Option**: For existing suppliers not yet in POS
- **Name Synchronization**: Updates POS name when changed in Laravel
- **Visual Indicators**: Purple badge for POS-linked suppliers
- **Protected Fields**: Code cannot be changed for POS-linked suppliers

## Implementation Details

### Controller (`AccountingSuppliersController.php`)

#### Key Methods

##### `store()` - Create New Supplier
```php
// Auto-generate code if not provided
if (empty($validated['code'])) {
    $validated['code'] = $this->generateSupplierCode();
}

// Set default payment terms
if (!isset($validated['payment_terms_days'])) {
    $validated['payment_terms_days'] = 30;
}

// Create POS supplier if requested
if ($request->boolean('create_in_pos')) {
    $this->createPosSupplier($supplier);
}
```

##### `generateSupplierCode()` - Auto-Generate Codes
```php
private function generateSupplierCode(): string
{
    $lastSupplier = AccountingSupplier::where('code', 'LIKE', 'SUP-%')
        ->orderByRaw('CAST(SUBSTRING(code, 5) AS UNSIGNED) DESC')
        ->first();
    
    $nextNumber = $lastSupplier 
        ? ((int) substr($lastSupplier->code, 4)) + 1 
        : 1;
    
    return 'SUP-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
}
```

##### `createPosSupplier()` - POS Integration
```php
private function createPosSupplier(AccountingSupplier $supplier): void
{
    $posId = 'SUP' . str_pad($supplier->id, 6, '0', STR_PAD_LEFT);
    
    DB::connection('pos')->table('suppliers')->insert([
        'SupplierID' => $posId,
        'Supplier' => $supplier->name,
    ]);
    
    $supplier->update([
        'external_pos_id' => $posId,
        'is_pos_linked' => true,
    ]);
}
```

### Model (`AccountingSupplier.php`)

#### Helper Methods

##### `canLinkToPos()` - Check POS Eligibility
```php
public function canLinkToPos(): bool
{
    return !$this->is_pos_linked && 
           in_array($this->supplier_type, ['product', 'other']);
}
```

##### `syncNameToPos()` - Update POS Name
```php
public function syncNameToPos(): bool
{
    if (!$this->is_pos_linked) return false;
    
    DB::connection('pos')->table('suppliers')
        ->where('SupplierID', $this->external_pos_id)
        ->update(['Supplier' => $this->name]);
    
    return true;
}
```

##### `getPosStatusAttribute()` - Status Display
```php
public function getPosStatusAttribute(): array
{
    if ($this->is_pos_linked) {
        return [
            'status' => 'linked',
            'message' => 'Linked to POS',
            'pos_id' => $this->external_pos_id,
            'color' => 'purple'
        ];
    }
    // ... additional status logic
}
```

## Error Handling

### Enhanced Error Messages
The system provides specific error messages for common issues:
- Duplicate supplier codes
- Payment terms validation
- Database connection issues
- POS system connection failures

### Transaction Safety
All operations use database transactions to ensure data integrity:
```php
DB::beginTransaction();
try {
    // Create supplier
    // Create POS entry if requested
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    // Provide specific error message
}
```

## User Experience Features

### Visual Feedback
1. **Loading States**: Spinner with "Creating..." text during submission
2. **Success Messages**: Green alert boxes for successful operations
3. **Error Messages**: Red alert boxes with specific error details
4. **Validation Errors**: Itemized list of fields needing correction

### Form Enhancements
- Placeholder text guides users
- Auto-focus on key fields
- Disabled submit button during processing
- Clear cancel/back navigation

## Database Migrations

### Key Migrations
- `2025_08_10_124231_create_accounting_suppliers_table.php`
- `2025_08_10_152620_enhance_accounting_suppliers_table.php`

### Important Fields
```php
$table->string('code', 50)->unique();
$table->string('external_pos_id')->nullable()->index();
$table->boolean('is_pos_linked')->default(false);
$table->integer('payment_terms_days')->default(30);
```

## Testing

### Manual Testing Checklist
- [ ] Create supplier without code (auto-generates)
- [ ] Create supplier with custom code
- [ ] Create supplier with POS integration
- [ ] Edit existing supplier
- [ ] Link existing supplier to POS
- [ ] Update POS-linked supplier name
- [ ] Verify error messages display
- [ ] Test loading states

### Verification Commands
```bash
# Check Laravel supplier
php artisan tinker
>>> App\Models\AccountingSupplier::where('name', 'LIKE', '%SupplierName%')->first();

# Check POS supplier
>>> DB::connection('pos')->table('suppliers')
    ->where('Supplier', 'LIKE', '%SupplierName%')->first();
```

## Common Issues & Solutions

### Issue: Supplier not appearing in POS
**Solution**: Check you're looking at the correct database port (3307 for POS, not 3306)

### Issue: Payment terms error
**Solution**: System now automatically defaults to 30 days if not provided

### Issue: No feedback on form submission
**Solution**: Added comprehensive flash messages and loading states

### Issue: POS connection failure
**Solution**: Verify POS_DB_* environment variables and database connectivity

## Security Considerations

- All operations require authentication
- Role-based access control via middleware
- SQL injection prevention via Eloquent ORM
- XSS protection via Blade templating
- CSRF protection on all forms

## Supplier Payments Report

### Overview
The Supplier Payments Report (`/suppliers/payments`) provides a comprehensive view of all supplier invoice payments within a selected date range. Added on 2026-01-12.

### Features

#### Date Range Selection
- Start and end date pickers with sensible defaults (current month)
- Maximum date limited to today

#### Sorting Options
- **Date (Newest)**: Most recent payments first (default)
- **Date (Oldest)**: Oldest payments first
- **Supplier (A-Z)**: Alphabetical by supplier name
- **Supplier (Z-A)**: Reverse alphabetical

#### Group by Supplier
- Toggle checkbox to enable grouped view
- Collapsible sections per supplier showing:
  - Supplier name
  - Payment count
  - Total amount paid
- **Expand All / Collapse All** toggle for quick navigation
- Individual sections expand to show payment details table

#### Payment Details Table
| Column | Description |
|--------|-------------|
| Payment Date | Date payment was made (dd/mm/yyyy) |
| Supplier | Supplier name (flat view only) |
| Amount | Payment amount in euros |
| Payment Method | Color-coded badge (Bank Transfer, Cash, Cheque, Card) |
| Invoice # | Related invoice number |
| Invoice Date | Original invoice date (dd/mm/yyyy) |
| Reference | Payment reference if provided |

#### Summary Statistics
- **Total Payments**: Count of payments in range
- **Total Amount**: Sum of all payments
- **By Payment Method**: Breakdown showing totals per method

#### CSV Export
- Downloads all filtered payments
- Respects current sort order
- Includes headers: Payment Date, Supplier, Amount, Payment Method, Reference, Invoice #, Invoice Date

### User Interface

#### Navigation
Access via the green **"Payments"** button in the suppliers index page header (between Outstanding Report and View Invoices).

#### URL Parameters
```
/suppliers/payments?start_date=2026-01-01&end_date=2026-01-12&sort=supplier_asc&group_by=supplier
```

### Controller Implementation

```php
// SupplierPaymentsController.php
public function index(Request $request)
{
    $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
    $endDate = $request->get('end_date', now()->format('Y-m-d'));
    $sort = $request->get('sort', 'date_desc');
    $groupBy = $request->get('group_by', 'none');

    // Query paid invoices
    $paidInvoices = Invoice::with('supplier')
        ->where('payment_status', 'paid')
        ->whereNotNull('payment_date')
        ->whereBetween('payment_date', [$startDate, $endDate])
        ->get();

    // Apply sorting and optional grouping
    // ...
}
```

### Routes
```php
Route::get('/suppliers/payments', [SupplierPaymentsController::class, 'index'])
    ->name('suppliers.payments');
Route::get('/suppliers/payments/export', [SupplierPaymentsController::class, 'exportCsv'])
    ->name('suppliers.payments.export');
```

---

## Organic Trust Supplier Report

### Overview
The Organic Trust Supplier Report (`/suppliers/organic-trust-report`) generates the data needed for the annual Organic Trust return form, covering both Field 13 ("Bought In Organic Ingredients/Products") and organic product sales. Added 2026-03-14, enhanced 2026-03-23.

### Features

#### Date Range Selection
- Start and end date pickers, defaulting to the current calendar year
- Shows product-type suppliers with invoice spend or POS sales > 0 in the selected period

#### Organic Toggle
- Each supplier row has an Alpine.js toggle switch to mark/unmark as organic
- The `is_organic` flag is persisted on the `accounting_suppliers` table
- Toggles via AJAX (no page reload) so users can quickly classify multiple suppliers
- Once set, the organic status persists across future reports

#### Organic Certification Fields (2026-03-23)
- **Product Type**: Inline dropdown per supplier (e.g., Fruit & Vegetables, Dried Goods & Grocery, Dairy, Meat)
- **Certification Body**: Inline dropdown per supplier (e.g., Organic Trust, IOFGA, Soil Association)
- Both fields support preset options with "Other..." for custom entries
- Only visible when supplier is marked organic
- Save immediately via AJAX

#### Manage Dropdown Options (2026-03-23)
- Collapsible settings panel to add/remove Product Type and Certification Body options
- Options stored in `app_settings` table, falling back to model constants as defaults
- Changes save immediately and are sorted alphabetically

#### Sales Revenue (2026-03-23)
- Per-supplier sales revenue from POS data (via `supplier_link` → `sales_daily_summary`)
- Suppliers with sales but no invoices (e.g., Coffee) are included in the report
- All amounts shown ex. VAT

#### Organic Sales by Category (2026-03-23)
- Breakdown of organic supplier product sales grouped by POS category
- Shows category name, units sold, and sales revenue (ex. VAT)
- Displayed below the supplier table when organic suppliers have sales data

#### Summary Statistics
- **Total Suppliers**: Count of product suppliers with spend or sales in period
- **Total Spend**: Sum of all supplier invoices (ex. VAT)
- **Total Sales**: Sum of all supplier product sales (ex. VAT)
- **Organic Suppliers**: Count of suppliers marked organic
- **Organic Spend**: Sum of organic-only supplier invoices (ex. VAT)
- **Organic Sales**: Sum of organic-only supplier product sales (ex. VAT)

#### CSV Exports (Organic Trust Submission-Ready)
Two purpose-built exports:
- **Bought In Organic Products**: Supplier Name, Product Type, Certification Body, Total Amount (ex. VAT), with total row
- **Sales of Organic Products**: Category, Units Sold, Sales Revenue (ex. VAT), with total row

Both include date range in the header. Filenames: `Bought In Organic Products YYYY-MM-DD to YYYY-MM-DD.csv` and `Sales of Organic Products YYYY-MM-DD to YYYY-MM-DD.csv`.

### Navigation
Access via the green **"Organic Trust"** button in the suppliers index page header.

### Routes
```php
Route::get('/suppliers/organic-trust-report', [OrganicTrustReportController::class, 'index'])
    ->name('suppliers.organic-trust-report');
Route::get('/suppliers/organic-trust-report/export-bought-in', [OrganicTrustReportController::class, 'exportBoughtIn'])
    ->name('suppliers.organic-trust-report.export-bought-in');
Route::get('/suppliers/organic-trust-report/export-sales', [OrganicTrustReportController::class, 'exportSales'])
    ->name('suppliers.organic-trust-report.export-sales');
Route::post('/suppliers/{supplier}/toggle-organic', [OrganicTrustReportController::class, 'toggleOrganic'])
    ->name('suppliers.toggle-organic');
Route::post('/suppliers/{supplier}/update-organic-fields', [OrganicTrustReportController::class, 'updateOrganicFields'])
    ->name('suppliers.update-organic-fields');
Route::post('/suppliers/organic-trust-report/options', [OrganicTrustReportController::class, 'updateOptions'])
    ->name('suppliers.organic-trust-report.options');
```

### Database
- **Migration**: `2026_03_14_000000_add_is_organic_to_accounting_suppliers_table` — `is_organic` boolean
- **Migration**: `2026_03_23_000000_add_organic_fields_to_accounting_suppliers_table` — `organic_product_type`, `organic_certification_body` (nullable strings)
- **Settings**: `app_settings` table keys `organic_product_types` and `organic_certification_bodies` (JSON arrays)
- **Sales data**: Cross-database query via `supplier_link` (POS) → `sales_daily_summary` (Laravel)

---

## Future Enhancements

1. **Bulk Import**: CSV/Excel import for multiple suppliers
2. **API Endpoints**: RESTful API for external integrations
3. **Audit Trail**: Complete history of supplier changes
4. **Advanced Search**: Filter by multiple criteria
5. **Supplier Portal**: Self-service portal for suppliers
6. **Document Management**: Attach contracts and certificates
7. **Performance Metrics**: Track supplier reliability
8. **Automated Alerts**: Notifications for important events

## Related Documentation

- [Order Manager](./order-manager.md) - Stock monitoring for managed suppliers
- [POS Integration](./pos-integration.md)
- [Invoice Payment Management](./invoice-payment-management.md)
- [OSAccounts Integration](./osaccounts-integration.md)
- [Management Accounting System](./management-accounting-system.md)