# Label Template Layout Issues Troubleshooting

This document covers how to diagnose and fix layout issues with the Grid 4x9 Custom label template system.

## 🔍 Issue: Grid 4x9 Custom Template Not Rendering Correctly

### Problem Description
- Grid 4x9 Custom template shows barcode and price stacked vertically instead of side-by-side
- Production works differently than development environment
- Template exists in database but layout is incorrect

### Root Cause Analysis

The issue occurs when the `layout_config['type']` in the database doesn't match what the blade template expects.

**Blade Template Check** (in `resources/views/labels/a4-preview.blade.php`):
```php
$isGrid4x9 = isset($template->layout_config['type']) && $template->layout_config['type'] === 'grid_4x9';
```

**Common Mistake**: Setting `layout_config['type']` to `'grid_4x9_custom'` instead of `'grid_4x9'`.

### 🚨 Symptoms
- Template name shows correctly in interface: "Grid 4x9 Custom (47x31mm)"
- Template exists in `label_templates` table
- But layout renders as standard template (barcode and price on separate lines)
- Debug output shows "Found middle rows: 0" (should be > 0)

### 🔧 Diagnosis Steps

#### Step 1: Check Database Layout Config
```sql
SELECT name, layout_config 
FROM label_templates 
WHERE name = 'Grid 4x9 Custom (47x31mm)';
```

Look for: `{"type": "grid_4x9"}` (correct) vs `{"type": "grid_4x9_custom"}` (incorrect)

#### Step 2: Add Temporary Debug Code
Add this to `a4-preview.blade.php` after line 15:
```javascript
console.log('🔍 Debug Info:', {
    templateName: '{{ $template->name }}',
    isGrid4x9: {{ $isGrid4x9 ? 'true' : 'false' }},
    layoutConfig: @json($template->layout_config ?? {}),
    middleRowCount: document.querySelectorAll('.label-middle-row-4x9').length
});
```

#### Step 3: Check Blade Template Logic
Verify this line in `resources/views/labels/a4-preview.blade.php`:
```php
$isGrid4x9 = isset($template->layout_config['type']) && $template->layout_config['type'] === 'grid_4x9';
```

### ✅ Solution

#### Fix 1: Update Database Seeder
In `database/seeders/LabelTemplateSeeder.php`, ensure Grid 4x9 Custom uses:
```php
'layout_config' => [
    'type' => 'grid_4x9',  // Must match blade template check
    'barcode_position' => 'bottom_left',
    'price_position' => 'bottom_right',
    'name_position' => 'top_full_width',
    'custom_sizing' => true,
    'labels_per_a4' => 32,
],
```

#### Fix 2: Sync Templates
Run the sync command:
```bash
php artisan label:sync-templates
```

#### Fix 3: Clear Caches
```bash
php artisan view:clear
php artisan config:clear
php artisan cache:clear
```

### 🔄 Deployment Considerations

When deploying fixes:

1. **Seeder vs Production**: Changes to seeders only affect new installations. Existing production databases need explicit updates.

2. **View Caching**: Production environments with `view:cache` enabled may serve stale templates.

3. **rsync Deployment**: Ensure `resources/views/` directory is being synced properly.

### 📋 Post-Fix Verification

After applying the fix, verify:

1. **Template Detection**: Debug shows correct template name and `isGrid4x9: true`
2. **Middle Rows**: Debug shows "Found middle rows: [count > 0]"  
3. **Layout**: Barcode visual and price appear side-by-side
4. **Barcode Numbers**: Appear on separate line below barcode/price

### 🛠️ Prevention

1. **Consistent Naming**: Use consistent type names between blade templates and database
2. **Template Testing**: Test templates on production-like environment before deployment  
3. **Documentation**: Document template structure expectations clearly

### 🔗 Related Files

- **Blade Template**: `resources/views/labels/a4-preview.blade.php`
- **Print Template**: `resources/views/labels/a4-print.blade.php`  
- **Seeder**: `database/seeders/LabelTemplateSeeder.php`
- **Model**: `app/Models/LabelTemplate.php`
- **Sync Command**: `app/Console/Commands/SyncLabelTemplates.php`

### 🏷️ Template Structure Reference

**Correct Grid 4x9 Custom Layout**:
```
┌─────────────────────────────────┐
│ Product Name                    │ ← Top row
│ [Barcode Visual] │ €Price       │ ← Middle row (side-by-side)
│      12345678                   │ ← Bottom row (barcode number)
└─────────────────────────────────┘
```

**Incorrect Layout** (when `$isGrid4x9 = false`):
```
┌─────────────────────────────────┐
│ Product Name                    │
│ [Barcode Visual]                │
│      12345678                   │
│ €Price                          │ ← Price on separate line
└─────────────────────────────────┘
```

---

**Last Updated**: August 2025  
**Issue Resolution Date**: 2025-08-14