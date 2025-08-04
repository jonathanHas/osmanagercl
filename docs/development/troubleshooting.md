# Troubleshooting Guide

This guide covers common issues and their solutions when developing OSManager CL.

## 🚨 Common Errors

### Alpine.js x-for with Table Rows - Expandable Rows Appearing at Bottom

**Symptoms:**
- Expandable table rows appear at the bottom of the table instead of under their parent row
- All product rows render first, then all expandable rows render after
- Alpine.js x-for loops through sorted data twice with separate templates

**Root Cause:**
HTML tables have strict structure requirements. When using two separate `<template x-for>` loops in a table:
1. Alpine.js completes the first template loop entirely (all product rows)
2. Then processes the second template loop (all expandable rows)
3. The browser places all rows from the second loop after all rows from the first

**Solution:**
Use a single `<template x-for>` that wraps both rows in a `<tbody>` element:

```blade
<!-- ❌ WRONG - Two separate templates -->
<tbody>
    <template x-for="item in items" :key="item.id">
        <tr><!-- Product row --></tr>
    </template>
    <template x-for="item in items" :key="'expand-' + item.id">
        <tr x-show="expanded.includes(item.id)"><!-- Expandable row --></tr>
    </template>
</tbody>

<!-- ✅ CORRECT - Single template with tbody wrapper -->
<tbody>
    <template x-for="item in items" :key="item.id">
        <tbody>
            <tr><!-- Product row --></tr>
            <tr x-show="expanded.includes(item.id)"><!-- Expandable row --></tr>
        </tbody>
    </template>
</tbody>
```

**Key Points:**
- Multiple `<tbody>` elements are valid HTML
- Each product and its expandable row stay together in the DOM
- Alpine.js can properly scope the loop variable to both rows
- Maintains proper table structure and row ordering

### Alpine.js Template Tag Errors - "can't access property 'after', A is undefined" (FIXED 2025-08-04)

**Symptoms:**
- Table displays loading state but never shows data rows
- Console error: `Uncaught TypeError: can't access property "after", A is undefined`
- Data loads successfully (visible in console logs) but table remains empty
- Error occurs in Alpine.js minified code during DOM manipulation

**Root Cause:**
Alpine.js `<template>` tags cannot use runtime directives like `x-show`. Templates are compile-time constructs that get removed from the DOM after processing.

**Example of the Issue (Coffee Sales Implementation):**
```blade
<!-- ❌ WRONG - template tags cannot use x-show -->
<template x-show="!loading && filteredSales.length > 0">
    <template x-for="sale in paginatedSales" :key="sale.product_id">
        <tbody>
            <tr>...</tr>
        </tbody>
    </template>
</template>

<!-- ✅ CORRECT - No x-show on template -->
<template x-for="sale in paginatedSales" :key="sale.product_id">
    <tbody>
        <tr>...</tr>
    </tbody>
</template>
```

**Why This Happened:**
- Developer attempted to control visibility of the template
- Confusion between `<template>` (Alpine.js construct) and regular HTML elements
- Similar patterns work with `<div>` or `<tbody>` but not `<template>`

**Solution:**
1. Remove `x-show` from `<template>` tags
2. Use separate `<tbody>` elements with `x-show` for loading/empty states
3. Let the `x-for` template handle its own visibility based on the data

**Debugging Steps:**
1. Check browser console for Alpine.js errors
2. Look for `<template x-show=...>` patterns in your code
3. Verify data is loading correctly with console.log
4. Ensure proper table structure without nested templates

**Prevention Tips:**
- Never use runtime directives (`x-show`, `x-if`) on `<template>` tags
- Use `<template>` only for `x-for` and `x-if` directives
- For visibility control, wrap content in `<div>` or appropriate HTML elements

### ParseError: "unexpected end of file, expecting 'elseif' or 'else' or 'endif'"

**Symptoms:**
- Internal Server Error on page load
- Laravel log shows ParseError with unexpected end of file
- Blade template compilation fails
- Error points to a specific Blade view file

**Common Causes:**

#### 1. Alpine.js Event Handlers Conflicting with Blade Directives

**Problem:** Alpine.js event handlers like `@error`, `@click`, `@change` can be interpreted by Blade as directives.

```blade
<!-- ❌ WRONG - Blade interprets @error as an error directive -->
<img src="image.jpg" @error="handleError()">

<!-- ✅ CORRECT - Escaped to prevent Blade compilation -->
<img src="image.jpg" @@error="handleError()">
```

**Solution:** Escape Alpine.js event handlers with double `@@`:
- `@error` → `@@error`
- `@click` → `@@click` (if conflicts arise)
- `@change` → `@@change` (if conflicts arise)

#### 2. Template Literals with Blade Syntax

**Problem:** JavaScript template literals (backticks) mixed with Blade syntax can cause parsing issues.

```blade
<!-- ❌ WRONG - Template literal with Blade inside -->
<img :src="`{{ route('image', '') }}/${product.CODE}`">

<!-- ✅ CORRECT - String concatenation -->
<img :src="'{{ route('image', '') }}/' + product.CODE">
```

#### 3. Unclosed Blade Directives

**Problem:** Missing `@endif`, `@endforeach`, `@endwhile`, etc.

```blade
<!-- ❌ WRONG - Missing @endif -->
@if($condition)
    <div>Content</div>
<!-- Missing @endif -->

<!-- ✅ CORRECT -->
@if($condition)
    <div>Content</div>
@endif
```

### Debugging Steps

#### 1. Identify the Problematic File
The error message will show the file path:
```
(View: /var/www/html/osmanagercl/resources/views/fruit-veg/availability.blade.php)
```

#### 2. Clear All Caches
```bash
php artisan view:clear
php artisan config:clear
php artisan route:clear  
php artisan cache:clear
```

#### 3. Test Blade Compilation
```bash
php artisan tinker --execute="
try { 
    \$html = view('your.view', [])->render(); 
    echo 'View compiled successfully'; 
} catch (\Exception \$e) { 
    echo 'Error: ' . \$e->getMessage(); 
}"
```

#### 4. Isolate the Problem Area
Create a truncated version of the file to narrow down the issue:
```bash
# Test first 100 lines + closing tag
head -100 resources/views/problematic-file.blade.php > /tmp/test.blade.php
echo "</x-admin-layout>" >> /tmp/test.blade.php

# Test compilation
php artisan tinker --execute="
try { 
    \$html = \$blade = app('view')->file('/tmp/test.blade.php', [])->render(); 
    echo 'Partial view OK'; 
} catch (\Exception \$e) { 
    echo 'Error: ' . \$e->getMessage(); 
}"
```

#### 5. Check Compiled View
Find and examine the compiled PHP file:
```bash
# Find compiled view
find storage/framework/views -name "*.php" -exec grep -l "your-view-name" {} \;

# Check PHP syntax
php -l storage/framework/views/compiled-file.php
```

## 🔍 Template Issues

### Missing Route Parameters

**Error:** `Missing required parameter for [Route: example] [URI: example/{id}]`

**Cause:** Routes being called during compilation instead of runtime.

**Solution:** Use static URLs or ensure parameters are available:
```blade
<!-- ❌ WRONG - Route called during compilation -->
<img :src="'{{ route('image', '') }}/' + product.CODE">

<!-- ✅ CORRECT - Static URL -->
<img :src="'/images/' + product.CODE">
```

### Variable Not Defined

**Error:** `Undefined variable $variable`

**Solution:** Use null coalescing operator:
```blade
<!-- ❌ WRONG -->
{{ $products }}

<!-- ✅ CORRECT -->
{{ $products ?? [] }}
@json($products ?? [])
```

### Alpine.js Directive Conflicts

**Error:** `ParseError: syntax error, unexpected end of file, expecting 'elseif' or 'else' or 'endif'`

**Cause:** Alpine.js directives starting with `@` are interpreted as Blade directives.

**Solution:** Escape Alpine.js directives with double `@@`:
```blade
<!-- ❌ WRONG - Blade tries to parse @error -->
<img src="image.jpg" @error="handleError()">

<!-- ✅ CORRECT - Outputs @error for Alpine.js -->
<img src="image.jpg" @@error="handleError()">

<!-- Common Alpine.js directives to escape -->
@@click="handler()"
@@change="update()"
@@submit="submit()"
@@keyup="search()"
@@error="fallback()"
```

## 🧹 Cache Issues

### Views Not Updating

**Problem:** Changes to Blade templates not reflected on frontend.

**Solution:**
```bash
# Clear view cache
php artisan view:clear

# For persistent issues, manually delete compiled views
rm -rf storage/framework/views/*.php
```

### Components Not Loading

**Problem:** New Blade components not recognized.

**Solution:**
```bash
# Clear all caches
php artisan optimize:clear

# Restart development server
php artisan serve
```

## 📦 Delivery System Issues

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
- ✅ Unit costs are calculated correctly (case price ÷ units per case)
- ✅ VAT rates are calculated using (Tax ÷ Value) × 100
- ✅ Tax categories are auto-selected in product creation form
- ✅ Green styling appears on auto-selected tax category field
- ✅ Delivery view shows correct unit costs with "per unit" label

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
// Should return true for Independent format

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

## 🔧 Development Tools

### Useful Artisan Commands

```bash
# Clear specific caches
php artisan view:clear      # Clear compiled views
php artisan config:clear    # Clear configuration cache
php artisan route:clear     # Clear route cache
php artisan cache:clear     # Clear application cache

# Clear everything
php artisan optimize:clear

# Debug routes
php artisan route:list

# Debug models and data
php artisan tinker
```

### Testing Blade Templates

```bash
# Test specific view compilation
php artisan tinker
>>> view('your.view.name')->render();

# Test with data
>>> view('your.view.name', ['data' => 'value'])->render();
```

## 📋 Prevention Tips

1. **Always escape Alpine.js events** that match Blade directive names
2. **Avoid template literals** with Blade syntax inside
3. **Use null coalescing** operators for optional variables
4. **Test template compilation** after major changes
5. **Keep backups** of working templates before modifications
6. **Document known issues** in CLAUDE.md for future reference

## 🆘 When All Else Fails

1. **Restore from backup** if available
2. **Compare with working similar templates**
3. **Start with minimal template** and add complexity gradually
4. **Check Laravel documentation** for Blade syntax changes
5. **Review recent git commits** for breaking changes

## 🧩 Component Issues

### Tab Component Slot Access Problems (RESOLVED)

**Symptoms:**
- Tabs display but show "No content provided for [tab name] tab" message
- Content is properly defined in `<x-slot name="tabname">` sections
- Issue affects multiple pages using `<x-tab-group>` component

**Root Cause:**
Laravel's slot system compatibility issue with the `<x-tab-group>` component's slot access pattern. The `$slots` collection wasn't accessible via array notation.

**Solution Applied:**
The issue has been fixed by using variable variables to access named slots:
```blade
@php
    $slotName = $tab['id'];
    $hasSlot = false;
    $slotContent = null;
    
    // Check if slot exists and get its content
    if (isset($$slotName)) {
        $hasSlot = true;
        $slotContent = $$slotName;
    }
@endphp

@if($hasSlot && $slotContent)
    {{ $slotContent }}
@else
    // Show "No content provided" message
@endif
```

**Debugging Steps:**

1. **Verify slot names match exactly**:
   ```blade
   <!-- Tab definition -->
   ['id' => 'overview', 'label' => 'Overview']
   
   <!-- Slot name must match exactly -->
   <x-slot name="overview">
       Content here
   </x-slot>
   ```

2. **Test with minimal component**:
   ```blade
   <x-tab-group :tabs="[['id' => 'test', 'label' => 'Test']]">
       <x-slot name="test">
           <p>Simple test content</p>
       </x-slot>
   </x-tab-group>
   ```

3. **Debug slot contents**:
   ```blade
   <!-- Add to tab-group.blade.php for debugging -->
   @php 
   dump('Available slots:', array_keys($slots->toArray())); 
   dump('Looking for:', $tab['id']);
   @endphp
   ```

**Working Solution - Direct Alpine.js Implementation:**
```blade
<div x-data="{ activeTab: 0 }" class="w-full">
    <!-- Tab Navigation -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <button @click="activeTab = 0"
                    :class="activeTab === 0 ? 
                        'border-indigo-500 text-indigo-600' : 
                        'border-transparent text-gray-500 hover:text-gray-700'"
                    class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                Tab 1
            </button>
            <button @click="activeTab = 1"
                    :class="activeTab === 1 ? 
                        'border-indigo-500 text-indigo-600' : 
                        'border-transparent text-gray-500 hover:text-gray-700'"
                    class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                Tab 2
            </button>
        </nav>
    </div>

    <!-- Tab Content -->
    <div class="mt-4">
        <div x-show="activeTab === 0" x-transition>
            <div class="p-4">Content for tab 1</div>
        </div>
        <div x-show="activeTab === 1" x-transition>
            <div class="p-4">Content for tab 2</div>
        </div>
    </div>
</div>
```

**Previously Affected Pages (Now Fixed):**
- Products show page (`/products/{id}`)
- Fruit-Veg product edit page (`/fruit-veg/product/{code}`)

**If Similar Issues Occur:**
If you encounter similar slot access issues in other components, you can use the variable variable approach shown above or implement direct Alpine.js as a fallback.

## 🚀 Sales Data Import System Issues

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

### 🔍 Sales Data Validation System Issues

#### Validation Interface Tabs Not Loading Data

**Symptoms:**
- Overview tab works but Daily, Category, Detailed tabs show no data
- Console errors in browser developer tools
- AJAX requests failing or returning empty results

**Common Causes & Solutions:**

##### 1. Key Matching Issues (Fixed in v1.0)
**Problem:** Carbon date formatting causing validation service to return 0% accuracy
```javascript
// The issue was in keyBy operations:
importedByKey = data.keyBy(item => item.product_id + '-' + item.sale_date); // ❌ Includes timestamp
```

**Solution:** Fixed in SalesValidationService.php:
```php
// ✅ FIXED: Proper date formatting
$importedByKey = $imported->keyBy(function ($item) {
    return $item->product_id . '-' . $item->sale_date->format('Y-m-d');
});
```

##### 2. Daily Summary Aggregation Problems (Fixed in v1.0)
**Problem:** MySQL DATE() function and GROUP BY issues causing missing daily data
```php
// ❌ WRONG: Basic groupBy didn't work with DATE() function
->groupBy('sale_date')
```

**Solution:** Fixed with proper raw queries:
```php
// ✅ FIXED: Proper DATE() grouping
->selectRaw('DATE(sale_date) as sale_date, SUM(total_revenue) as daily_revenue, ...')
->groupByRaw('DATE(sale_date)')
->keyBy(function ($item) {
    return Carbon::parse($item->sale_date)->format('Y-m-d');
});
```

##### 3. Tab Loading Dependencies (Fixed in v1.0)
**Problem:** Other tabs couldn't load without running overview validation first
```javascript
// ❌ WRONG: All tabs required currentValidationData
async function loadTabData(tab) {
    if (!currentValidationData) return; // Blocked other tabs
}
```

**Solution:** Made tabs independent:
```javascript
// ✅ FIXED: Independent tab loading
async function loadTabData(tab) {
    const formData = new FormData(document.getElementById('validation-form'));
    switch (tab) {
        case 'overview':
            if (currentValidationData) loadOverviewTab(); // Only overview needs this
            break;
        case 'daily':
            await loadDailyTab(formData); // Independent AJAX calls
            break;
        // ... other tabs work independently
    }
}
```

#### Debugging Validation Issues

**Check Browser Console:**
1. Open browser developer tools (F12)
2. Go to Console tab
3. Look for JavaScript errors or AJAX failures
4. Console logs show: "Loading daily tab data...", "Daily tab response:", etc.

**Test Backend Validation:**
```bash
# Test validation service directly
php artisan tinker --execute="
use App\Services\SalesValidationService;
use Carbon\Carbon;
\$service = new SalesValidationService();
\$result = \$service->validateDateRange(Carbon::parse('2025-07-15'), Carbon::parse('2025-07-15'));
echo 'Accuracy: ' . \$result['summary']['accuracy_percentage'] . '%';
"
```

**Verify Routes:**
```bash
# Check validation routes exist
php artisan route:list --name=sales-import
```

#### Test Data Cleanup Issues

**Problem:** Mixed test data causing validation discrepancies

**Solution:** Clean test data:
```bash
# Remove synthetic test records
php artisan tinker --execute="
use App\Models\SalesDailySummary;
\$testProductIds = ['PROD001', 'PROD002', 'PROD003', 'PROD004', 'PROD005'];
\$deleted = SalesDailySummary::whereIn('product_id', \$testProductIds)->delete();
echo 'Deleted ' . \$deleted . ' test records';
"
```

**Verification:** After cleanup, validation should show 100% accuracy with real POS data.

### Development & Testing Issues

#### Test Data Creation Fails
**Error:** `sales:create-test-data` command fails

**Solutions:**
```bash
# Check if tables exist
php artisan migrate:status

# Clear and rebuild
php artisan migrate:fresh
php artisan sales:create-test-data --days=30
```

#### Repository Performance Testing Shows Slow Results
**Symptoms:**
- `php artisan sales:test-repository` shows high execution times
- Expected sub-20ms queries taking longer

**Debugging:**
```bash
# Check index usage
EXPLAIN SELECT * FROM sales_daily_summary 
WHERE sale_date BETWEEN '2025-07-01' AND '2025-07-31' 
AND category_id IN ('SUB1', 'SUB2', 'SUB3');
```

**Solutions:**
- Ensure database has proper indexes
- Check if using development vs production database
- Verify sufficient test data exists

### Console Command Issues

#### Commands Not Found
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

#### Permission Errors
**Error:** Permission denied when running imports

**Solutions:**
- Check file permissions on storage directories
- Ensure web server user can write to log files
- Verify database connection permissions

### Chart.js Errors in Daily Sales Overview

#### "can't access property 'save', t is null" Error

**Symptoms:**
- Daily Sales Overview chart appears blank
- Browser console shows Chart.js error about 'save' property
- Chart creation/update failures
- Error occurs during chart rendering

**Root Causes:**
1. **Canvas Context Issues**: Chart.js losing reference to canvas 2D context
2. **Multiple Chart Instances**: Multiple charts created on same canvas element
3. **Rapid Destroy/Create Cycles**: Chart destroyed and recreated too quickly
4. **Memory Leaks**: Chart instances not properly cleaned up

**Solutions:**

**1. Check Chart Recreation Logic:**
```javascript
// Browser Console debugging
console.log('Chart instance:', this.chart);
console.log('Canvas element:', document.getElementById('dailySalesChart'));
console.log('Canvas context:', document.getElementById('dailySalesChart').getContext('2d'));
```

**2. Verify Canvas Element:**
```html
<!-- Ensure canvas has unique ID and no conflicts -->
<canvas id="dailySalesChart" style="height: 300px;"></canvas>
```

**3. Clear Browser Cache:**
- Hard refresh (Ctrl+F5)
- Clear browser cache and cookies
- Disable browser extensions temporarily

**4. Check Console Logs:**
Look for these debug messages:
- `📊 Chart needs recreation` - Chart update triggered
- `📊 Creating chart with data` - Chart creation process
- `✅ Chart created successfully` - Successful creation
- `💥 Error creating chart` - Chart creation failed

**Fixed Features:**
- ✅ Smart chart recreation only when data changes
- ✅ Proper canvas cleanup with Chart.getChart()
- ✅ 100ms delay between destroy/create operations
- ✅ Comprehensive error handling and recovery
- ✅ Chart responds correctly to date range changes
- ✅ Euro currency display throughout interface

#### Chart Not Updating with Date Range Changes

**Symptoms:**
- Chart shows old data when date range changes
- July data persists when selecting June dates
- Chart doesn't respond to "Update" button clicks

**Diagnosis:**
```javascript
// Check if date range changes are detected
console.log('Current date range:', this.startDate, 'to', this.endDate);
console.log('Daily sales count:', this.dailySales?.length);
console.log('Sample data:', this.dailySales?.[0]);
```

**Solutions:**
1. **Check AJAX Response**: Verify API returns correct data for selected dates
2. **Verify Data Assignment**: Ensure `this.dailySales` updates with new data
3. **Force Chart Recreation**: Chart will recreate automatically when data changes
4. **Check Data Availability**: Some date ranges may have no F&V sales data

## 🚛 Delivery System Authentication Issues

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

**Files Modified:**
- `/resources/views/deliveries/scan.blade.php` - Updated JavaScript fetch URLs

**Prevention:**
- Use web routes for frontend JavaScript that relies on session authentication
- Reserve API routes for external integrations that provide proper tokens
- When adding new AJAX functionality, prefer web routes over API routes for consistency

**Debugging Steps:**
1. Check browser console for 401/403 errors
2. Verify user is logged in to web interface
3. Test if web route is accessible: `curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8000/deliveries/1/scan"`
4. Should return 302 (redirect to login) if not authenticated, not 401

**Related Documentation:**
- See [Delivery System Documentation](../features/delivery-system.md) for full scanning workflow
- Authentication patterns are documented in delivery system API section

## 📞 Getting Help

- Check Laravel Blade documentation
- Search Laravel forums and Stack Overflow
- Review similar templates in the codebase
- Create minimal reproduction case for debugging