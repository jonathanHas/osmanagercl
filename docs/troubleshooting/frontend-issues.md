# Frontend & JavaScript Issues

This guide covers issues related to Alpine.js, Blade templates, and JavaScript in OSManager CL.

---

## Alpine.js x-for with Table Rows - Expandable Rows Appearing at Bottom

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

---

## Alpine.js Template Tag Errors - "can't access property 'after', A is undefined" (FIXED 2025-08-04)

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

---

## ParseError: "unexpected end of file, expecting 'elseif' or 'else' or 'endif'"

**Symptoms:**
- Internal Server Error on page load
- Laravel log shows ParseError with unexpected end of file
- Blade template compilation fails
- Error points to a specific Blade view file

**Common Causes:**

### 1. Alpine.js Event Handlers Conflicting with Blade Directives

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

### 2. Template Literals with Blade Syntax

**Problem:** JavaScript template literals (backticks) mixed with Blade syntax can cause parsing issues.

```blade
<!-- ❌ WRONG - Template literal with Blade inside -->
<img :src="`{{ route('image', '') }}/${product.CODE}`">

<!-- ✅ CORRECT - String concatenation -->
<img :src="'{{ route('image', '') }}/' + product.CODE">
```

### 3. Unclosed Blade Directives

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

---

## Debugging Blade Template Issues

### 1. Identify the Problematic File
The error message will show the file path:
```
(View: /var/www/html/osmanagercl/resources/views/fruit-veg/availability.blade.php)
```

### 2. Clear All Caches
```bash
php artisan view:clear
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### 3. Test Blade Compilation
```bash
php artisan tinker --execute="
try {
    \$html = view('your.view', [])->render();
    echo 'View compiled successfully';
} catch (\Exception \$e) {
    echo 'Error: ' . \$e->getMessage();
}"
```

### 4. Isolate the Problem Area
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

### 5. Check Compiled View
Find and examine the compiled PHP file:
```bash
# Find compiled view
find storage/framework/views -name "*.php" -exec grep -l "your-view-name" {} \;

# Check PHP syntax
php -l storage/framework/views/compiled-file.php
```

---

## Template Issues

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

---

## Component Issues

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

---

## Chart.js Errors in Daily Sales Overview

### "can't access property 'save', t is null" Error

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
- `Chart needs recreation` - Chart update triggered
- `Creating chart with data` - Chart creation process
- `Chart created successfully` - Successful creation
- `Error creating chart` - Chart creation failed

**Fixed Features:**
- Smart chart recreation only when data changes
- Proper canvas cleanup with Chart.getChart()
- 100ms delay between destroy/create operations
- Comprehensive error handling and recovery
- Chart responds correctly to date range changes
- Euro currency display throughout interface

### Chart Not Updating with Date Range Changes

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

---

## Cache Issues

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

---

## Related Documentation

- [Categories Management](../features/categories-management.md) - Tab implementation examples
- [Coffee Module](../features/coffee-module.md) - Alpine.js patterns
- [Back to Troubleshooting Index](./index.md)
