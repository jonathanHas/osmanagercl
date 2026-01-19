# Backend & System Issues

This guide covers database connections, imports, recovery, and system-level issues in OSManager CL.

---

## POS Database Connection Mismatch - Price Updates Not Appearing on Till

**Symptoms:**
- Price updates in Laravel F&V interface don't reflect on the POS till
- Laravel shows one price, direct MySQL/MariaDB queries show different price
- Price sync tool shows products as synchronized but till prices remain unchanged

**Root Cause:**
Multiple database instances running on different ports. Laravel may be connecting to a different database than the actual POS system.

**Common Configuration:**
- Port 3306: MariaDB (actual POS database used by till)
- Port 3307: MySQL (test/development database)

**Diagnosis Steps:**
1. **Check Laravel's POS connection:**
```bash
php artisan tinker --execute="
\$config = config('database.connections.pos');
echo 'Laravel connects to: ' . \$config['host'] . ':' . (\$config['port'] ?? '3306') . '/' . \$config['database'] . PHP_EOL;
\$dbInfo = DB::connection('pos')->select('SELECT DATABASE() as db, @@hostname as host, @@port as port, VERSION() as version');
echo 'Actual connection: ' . \$dbInfo[0]->host . ':' . \$dbInfo[0]->port . '/' . \$dbInfo[0]->db . ' (' . \$dbInfo[0]->version . ')' . PHP_EOL;
"
```

2. **Check what database your till system uses:**
```bash
# Connect to your actual POS database (usually port 3306)
mysql -u username -p -h 127.0.0.1 -P 3306 unicenta2016
SELECT PRICESELL FROM PRODUCTS WHERE CODE = 'your_test_product_code';
```

**Solution:**
Update the `.env` file to use the correct port:
```env
# Change from the wrong port
POS_DB_PORT=3307

# To the correct port (usually 3306 for MariaDB)
POS_DB_PORT=3306
```

Then clear configuration cache:
```bash
php artisan config:clear
php artisan config:cache
```

**Prevention:**
- Always verify the POS database connection after setup changes
- Use the Price Sync Management tool at `/fruit-veg/price-sync` to identify discrepancies
- Test price changes on a sample product before making bulk updates

---

## Delivery System Issues

### Independent Irish Health Foods CSV Format Problems

**Symptoms:**
- Unit costs showing as case prices (e.g., €21.44 instead of €1.79)
- Tax calculations appearing incorrect
- Product creation form showing wrong pricing
- "Add to POS" button not auto-selecting tax category

**Root Cause Analysis:**

**Case vs Unit Pricing Issue (Fixed 2025-08-04)**:
Independent CSV format uses **case pricing** in the Price field, not unit pricing like Udea format.

```csv
# Independent format - Price is per CASE
Code,Product,Price,Qty
19990B,Suma Hemp Oil & Vitamin E Soap 12x90g,21.44,1
# 12 units per case → €21.44 ÷ 12 = €1.79 per unit
```

**Solutions Applied:**

1. **Automatic Format Detection**:
```php
// DeliveryService detects format by headers and supplier ID
private function detectIndependentCsvFormat(array $headers, int $supplierId): bool
{
    $independentConfig = config('suppliers.external_links.independent');
    return in_array($supplierId, $independentConfig['supplier_ids'] ?? []);
}
```

2. **Case-to-Unit Conversion**:
```php
// Extract units per case from product name
$unitsPerCase = $this->extractUnitsFromProductName($productName); // "12x90g" = 12
$unitCost = $unitsPerCase > 0 ? $caseCost / $unitsPerCase : $caseCost;
```

3. **VAT Rate Calculation & Normalization**:
```php
// Calculate Irish VAT rate: (Tax ÷ Value) × 100
$taxRate = ($taxAmount / $lineValueExVat) * 100;
$normalizedRate = $this->normalizeIrishVatRate($taxRate); // Maps to 0%, 9%, 13.5%, 23%
```

4. **Automatic Tax Category Selection**:
```php
// Map VAT rates to POS tax category IDs
private function mapTaxRateToCategory(float $taxRate): ?string
{
    return match ($taxRate) {
        0.0 => '000',    // Tax Zero
        9.0 => '003',    // Tax Second Reduced
        13.5 => '001',   // Tax Reduced
        23.0 => '002',   // Tax Standard
        default => null
    };
}
```

**Debugging Steps:**
```bash
# Test CSV parsing for specific product
php artisan tinker
$testRow = [
    'Code' => '19990B',
    'Product' => 'Suma Hemp Oil & Vitamin E Soap 12x90g',
    'Price' => '21.44', 'Tax' => '4.93', 'Value' => '21.44'
];
$service = new App\Services\DeliveryService(app(App\Services\UdeaScrapingService::class));
$result = $service->parseIndependentCsv($testRow);
// Expected: unit_cost = 1.79, tax_rate = 23.02, normalized_tax_rate = 23.0
```

**Verification Checklist:**
- Unit costs are calculated correctly (case price ÷ units per case)
- VAT rates are calculated using (Tax ÷ Value) × 100
- Tax categories are auto-selected in product creation form
- Green styling appears on auto-selected tax category field
- Delivery view shows correct unit costs with "per unit" label

### CSV Import Failures

**Symptoms:**
- "Failed to import CSV" error messages
- No delivery items created
- Format detection not working

**Debug Process:**
```bash
# 1. Check CSV file structure
head -5 /path/to/file.csv

# 2. Test format detection
php artisan tinker
$headers = ['Code', 'Product', 'RSP', 'Price', 'Tax', 'Value'];
$supplierId = 1;
# Should return true for Independent format

# 3. Check supplier configuration
config('suppliers.external_links.independent.supplier_ids')

# 4. Verify file upload limits
php -i | grep upload_max_filesize
php -i | grep post_max_size
```

**Common Fixes:**
- Ensure CSV has proper headers (first row)
- Check file encoding (UTF-8 recommended)
- Verify supplier ID exists in database
- Increase PHP upload limits if needed

---

## Sales Data Import System Issues

### Import Commands Failing

**Symptoms:**
- `php artisan sales:import-daily` fails with errors
- Import logs show failed status
- Data not importing from POS database

**Common Causes & Solutions:**

#### 1. POS Database Connection Issues
```bash
# Test POS database connection
php artisan tinker --execute="DB::connection('pos')->table('STOCKDIARY')->count()"
```

**Solutions:**
- Verify POS_DB_* environment variables in `.env`
- Check POS database server is accessible
- Ensure credentials have read access to POS database

#### 2. Memory Exhaustion During Import
**Error:** `Fatal error: Allowed memory size exhausted`

**Solutions:**
```bash
# Use smaller chunk sizes
php artisan sales:import-historical --chunk-days=7

# Increase PHP memory limit temporarily
php -d memory_limit=512M artisan sales:import-historical
```

#### 3. Import Taking Too Long
**Solutions:**
- Process during off-peak hours
- Use smaller date ranges
- Check database indexes are created properly

### Performance Issues

#### Slow Analytics Queries
**Symptoms:**
- Sales repository queries still taking seconds
- Not seeing expected performance improvements

**Debugging Steps:**
```bash
# Test repository performance
php artisan sales:test-repository

# Check if import data exists
php artisan tinker --execute="App\Models\SalesDailySummary::count()"

# Verify indexes are created
php artisan migrate:status
```

**Solutions:**
- Ensure migrations have run: `php artisan migrate`
- Import historical data: `php artisan sales:import-historical`
- Check if using OptimizedSalesRepository instead of legacy SalesRepository

#### Scheduled Imports Not Running
**Symptoms:**
- No new data appearing in sales_daily_summary
- Import logs showing no recent activity

**Debugging:**
```bash
# Check scheduler configuration
php artisan schedule:list

# Test individual commands
php artisan sales:import-daily --yesterday

# Check cron is configured
crontab -l | grep artisan
```

**Solutions:**
- Ensure Laravel scheduler is configured in cron:
  ```bash
  * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
  ```
- Check server timezone matches application timezone
- Verify overlap protection isn't blocking imports

### Data Integrity Issues

#### Data Validation Failures
**Symptoms:**
- Import reports processed records but totals don't match POS
- Missing data for certain products or dates

**Debugging:**
```bash
# Compare imported vs POS totals for a specific date
php artisan tinker --execute="
\$date = '2025-07-30';
\$imported = App\Models\SalesDailySummary::where('sale_date', \$date)->sum('total_revenue');
\$pos = DB::connection('pos')->table('STOCKDIARY as s')
    ->join('PRODUCTS as p', 's.PRODUCT', '=', 'p.ID')
    ->where('s.REASON', -1)
    ->whereDate('s.DATENEW', \$date)
    ->whereIn('p.CATEGORY', ['SUB1', 'SUB2', 'SUB3'])
    ->selectRaw('SUM(ABS(s.UNITS) * s.PRICE) as total')
    ->value('total');
echo \"Imported: {\$imported}, POS: {\$pos}\";
"
```

**Solutions:**
- Re-import specific date ranges: `php artisan sales:import-daily --start-date=2025-07-30 --end-date=2025-07-30`
- Check POS database data quality
- Verify category filters (SUB1, SUB2, SUB3) are correct

### Sales Data Validation System Issues

#### Validation Interface Tabs Not Loading Data

**Symptoms:**
- Overview tab works but Daily, Category, Detailed tabs show no data
- Console errors in browser developer tools
- AJAX requests failing or returning empty results

**Common Causes & Solutions:**

##### 1. Key Matching Issues (Fixed in v1.0)
**Problem:** Carbon date formatting causing validation service to return 0% accuracy

**Solution:** Fixed in SalesValidationService.php with proper date formatting

##### 2. Daily Summary Aggregation Problems (Fixed in v1.0)
**Problem:** MySQL DATE() function and GROUP BY issues causing missing daily data

**Solution:** Fixed with proper raw queries and groupByRaw

##### 3. Tab Loading Dependencies (Fixed in v1.0)
**Problem:** Other tabs couldn't load without running overview validation first

**Solution:** Made tabs independent with their own AJAX calls

---

## Console Command Issues

### Commands Not Found
**Error:** `Command "sales:import-daily" is not defined`

**Solutions:**
```bash
# Clear command cache
php artisan optimize:clear

# Register commands manually
php artisan optimize

# Check command is registered
php artisan list sales
```

### Permission Errors
**Error:** Permission denied when running imports

**Solutions:**
- Check file permissions on storage directories
- Ensure web server user can write to log files
- Verify database connection permissions

---

## Delivery System Authentication Issues

### Barcode Editing Issues

**Symptoms:**
- Barcode edit form doesn't appear when clicking edit button
- Barcode update fails with constraint errors
- Related records not updating after barcode change

**Common Causes & Solutions:**

1. **JavaScript Function Not Found**
   - Ensure `toggleBarcodeEdit()` function is defined in the page
   - Check browser console for JavaScript errors
   - Function should be in the main script section, not inside DOMContentLoaded

2. **Unique Constraint Violation**
   - New barcode already exists in another product
   - Check with: `Product::where('CODE', $newBarcode)->exists()`
   - UpdateBarcodeRequest validation should catch this

3. **Stocking Table Primary Key Error**
   ```php
   // Stocking uses Barcode as primary key - must recreate record
   $stockingData = $stockingRecord->toArray();
   $stockingRecord->delete();
   $stockingData['Barcode'] = $newBarcode;
   Stocking::create($stockingData);
   ```

4. **Transaction Rollback**
   - Check Laravel logs for specific error messages
   - Common issue: Missing veg_details table (safe to ignore)
   - Ensure all models are imported in controller

5. **Label Logs Enum Error**
   - Migration must include 'barcode_change' in event_type enum
   - Check existing enum values before migration

**Debug Steps:**
1. Check browser console for JavaScript errors
2. Verify route exists: `php artisan route:list | grep update-barcode`
3. Check Laravel log: `tail -f storage/logs/laravel.log`
4. Test validation: Try updating to an existing barcode
5. Verify transaction: Check if partial updates occurred

### "Unauthenticated" Error During Barcode Scanning

**Symptoms:**
- Users get "unauthenticated" error when scanning barcodes in delivery scan interface
- Scanning interface loads correctly but scan operations fail
- Error occurs on routes like `/deliveries/3/scan`
- JavaScript console shows 401 Unauthorized responses

**Root Cause:**
JavaScript code was calling API routes (`/api/deliveries/{delivery}/scan`) which expect token-based authentication (Sanctum), while users only have session-based authentication from web login.

**Solution (Fixed 2025-08-04):**
Updated the scan.blade.php file to use web routes instead of API routes:

```javascript
// ❌ WRONG - API routes require token auth
const response = await fetch(`/api/deliveries/${this.deliveryId}/scan`, {...});

// ✅ CORRECT - Web routes use session auth
const response = await fetch(`/deliveries/${this.deliveryId}/scan`, {...});
```

**Prevention:**
- Use web routes for frontend JavaScript that relies on session authentication
- Reserve API routes for external integrations that provide proper tokens
- When adding new AJAX functionality, prefer web routes over API routes for consistency

---

## System Recovery Issues

### Database Data Loss - Users and Roles Cleared (2025-08-16)

**Symptoms:**
- Unable to log in with any account
- Users table shows 0 records
- Roles and permissions tables are empty
- Authentication system completely non-functional

**Root Cause:**
Database seeding tables were cleared during failed test runs or migrations with `--fresh` flag.

**Recovery Process:**

1. **Verify the Damage:**
   ```bash
   php artisan tinker --execute="
   echo 'Users: ' . \App\Models\User::count() . PHP_EOL;
   echo 'Roles: ' . \App\Models\Role::count() . PHP_EOL;
   echo 'Permissions: ' . \App\Models\Permission::count() . PHP_EOL;
   "
   ```

2. **Restore Roles and Permissions:**
   ```bash
   php artisan db:seed --class=RolesAndPermissionsSeeder
   ```
   This creates:
   - 4 roles (admin, manager, employee, barista)
   - 36+ permissions across all modules
   - Proper role-permission assignments

3. **Restore Admin User:**
   ```bash
   php artisan db:seed --class=AdminUserSeeder
   ```
   Creates default admin:
   - Username: `admin`
   - Email: `admin@osmanager.local`
   - Password: `admin123`
   - Role: Administrator

4. **Verify Recovery:**
   ```bash
   php artisan tinker --execute="
   \$admin = \App\Models\User::where('username', 'admin')->first();
   echo 'Admin found: ' . (\$admin ? 'YES' : 'NO') . PHP_EOL;
   echo 'Can access KDS: ' . (\$admin->hasPermission('kds.access') ? 'YES' : 'NO') . PHP_EOL;
   echo 'Password check: ' . (\Illuminate\Support\Facades\Hash::check('admin123', \$admin->password) ? 'PASS' : 'FAIL') . PHP_EOL;
   "
   ```

5. **Restore Coffee Metadata (if affected):**
   ```bash
   php artisan db:seed --class=CoffeeProductMetadataSeeder
   ```

**Prevention:**
- Never use `migrate:fresh` in production
- Always backup before running migrations
- Use specific seeders instead of `db:seed` without class parameter
- Test migrations and seeders in development first

### Coffee Metadata Cleared

**Symptoms:**
- KDS mobile grouping not working
- Coffee orders display as individual items instead of grouped
- Metadata management page shows no data
- Order grouping falls back to basic detection

**Recovery:**
```bash
# Check if metadata exists
php artisan tinker --execute="echo \App\Models\CoffeeProductMetadata::count() . ' metadata records';"

# Restore if needed
php artisan db:seed --class=CoffeeProductMetadataSeeder
```

**Expected Result:**
- 22+ coffee products with metadata
- 14 coffee types (main drinks)
- 8+ options (modifiers)
- Proper short names for mobile display

### Complete System Recovery Checklist

When multiple systems are affected:

1. **Database Tables Exist**
   ```bash
   php artisan migrate:status
   ```

2. **Roles & Permissions Restored**
   ```bash
   php artisan db:seed --class=RolesAndPermissionsSeeder
   ```

3. **Admin User Created**
   ```bash
   php artisan db:seed --class=AdminUserSeeder
   ```

4. **Coffee Metadata Restored**
   ```bash
   php artisan db:seed --class=CoffeeProductMetadataSeeder
   ```

5. **Test Authentication**
   - Login with admin/admin123
   - Verify role permissions work
   - Check KDS access functions

6. **Clear Caches**
   ```bash
   php artisan optimize:clear
   ```

**Recovery Time:** 2-3 minutes for complete system restoration

---

## Related Documentation

- [Delivery System](../features/delivery-system.md) - Full delivery workflow documentation
- [Sales Data Import Plan](../features/sales-data-import-plan.md) - Performance optimization
- [POS Integration](../features/pos-integration.md) - Database integration details
- [Back to Troubleshooting Index](./index.md)
