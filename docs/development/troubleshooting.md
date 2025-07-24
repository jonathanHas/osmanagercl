# Troubleshooting Guide

This guide covers common issues and their solutions when developing OSManager CL.

## 🚨 Common Errors

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

## 📞 Getting Help

- Check Laravel Blade documentation
- Search Laravel forums and Stack Overflow
- Review similar templates in the codebase
- Create minimal reproduction case for debugging