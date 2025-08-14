# Label Template Customization Technical Reference

## Overview

This document provides technical details for developers working with the enhanced label template system, specifically the Grid 4x9 Custom template improvements.

## Architecture

### Template Detection System

The system uses template name matching to determine which rendering logic to apply:

```php
// In both a4-print.blade.php and a4-preview.blade.php
$isCustomGrid = $template->name === 'Grid 4x9 Custom (47x31mm)';

if ($isCustomGrid) {
    // Enhanced logic
    $lengthClass = calculateCustomLength($product->NAME);
    $priceLengthClass = calculateCustomPriceLength($priceText);
} else {
    // Legacy logic
    $lengthClass = calculateLegacyLength($product->NAME);
    $priceLengthClass = calculateLegacyPriceLength($priceText);
}
```

### Dynamic Font Sizing Algorithm

#### Name Sizing Logic
```php
$nameLength = mb_strlen($product->NAME);
$wordCount = str_word_count($product->NAME);

if ($nameLength <= 10) {
    return 'custom-tiny';      // 22pt, optimized for very short names
} elseif ($nameLength <= 20) {
    return 'custom-extra-short'; // 18pt, good for product codes/simple names
} elseif ($nameLength <= 30) {
    return 'custom-short';      // 15pt, balanced size for medium names
} elseif ($nameLength <= 45 && $wordCount >= 4) {
    // Multi-word products get better treatment
    return 'custom-medium';     // 13pt, optimized for descriptive names
} elseif ($nameLength <= 60) {
    return 'custom-long';       // 11pt, handles most long product names
} else {
    return 'custom-extra-long'; // 9pt, fallback for very long names
}
```

#### Price Sizing Logic
```php
$priceLength = mb_strlen($priceText); // Accurate Unicode character count

if ($priceLength <= 5) {
    return 'custom-normal';     // 24pt, €9.99 format
} elseif ($priceLength <= 6) {
    return 'custom-long';       // 22pt, €32.95 format  
} else {
    return 'custom-extra-long'; // 20pt, €100.99+ format
}
```

## CSS Implementation

### Responsive Font Sizing

The CSS uses attribute selectors to apply different font sizes based on calculated classes:

```css
/* Name sizing - larger fonts for better readability */
.label-name-4x9[data-length="custom-tiny"] {
    font-size: 22pt;
    -webkit-line-clamp: 2;
    line-height: 1.1;
}

.label-name-4x9[data-length="custom-extra-short"] {
    font-size: 18pt;
    -webkit-line-clamp: 2;
    line-height: 1.15;
}

.label-name-4x9[data-length="custom-short"] {
    font-size: 15pt;
    -webkit-line-clamp: 3;
    line-height: 1.15;
}

.label-name-4x9[data-length="custom-medium"] {
    font-size: 13pt;
    -webkit-line-clamp: 3;
    line-height: 1.15;
}

.label-name-4x9[data-length="custom-long"] {
    font-size: 11pt;
    -webkit-line-clamp: 4;
    line-height: 1.15;
}

.label-name-4x9[data-length="custom-extra-long"] {
    font-size: 9pt;
    -webkit-line-clamp: 5;
    line-height: 1.12;
}
```

### Layout Optimization

```css
/* Optimized barcode/price space allocation */
.label-4x9 .label-bottom-row-4x9 .label-barcode-4x9 {
    flex: 0 0 35%; /* Reduced from 40% */
}

.label-4x9 .label-bottom-row-4x9 .label-price-4x9[data-price-length^="custom"] {
    flex: 0 0 65%; /* Increased from 60% */
    overflow: visible !important; /* Prevent cropping */
    white-space: nowrap;
    min-width: 0;
}
```

### Price Anti-Cropping System

```css
/* Price sizing with overflow protection */
.label-price-4x9[data-price-length="custom-normal"] {
    font-size: 24pt !important;
}

.label-price-4x9[data-price-length="custom-long"] {
    font-size: 22pt !important;
}

.label-price-4x9[data-price-length="custom-extra-long"] {
    font-size: 20pt !important;
}
```

## Database Integration

### Template Management

```php
// Get default template (now Grid 4x9 Custom)
$defaultTemplate = LabelTemplate::getDefault();

// Check if template uses custom logic
$usesCustomLogic = $template->name === 'Grid 4x9 Custom (47x31mm)';

// Template configuration
$template = [
    'id' => 6,
    'name' => 'Grid 4x9 Custom (47x31mm)',
    'description' => 'Custom version of 4x8 grid layout (32 labels per A4 sheet)',
    'width_mm' => 47,
    'height_mm' => 31,
    'margin_mm' => 2,
    'font_size_name' => 12,      // Base size (dynamically overridden)
    'font_size_barcode' => 7,
    'font_size_price' => 26,     // Base size (dynamically overridden)  
    'barcode_height' => 15,
    'layout_config' => [
        'type' => 'grid_4x9',
        'barcode_position' => 'bottom_left',
        'price_position' => 'bottom_right',
        'name_position' => 'top_full_width'
    ],
    'is_default' => true,
    'is_active' => true
];
```

## File Modifications

### Modified Files
1. **app/Http/Controllers/LabelAreaController.php**
   - No changes (uses existing template system)

2. **resources/views/labels/a4-print.blade.php**
   - Added custom template detection logic
   - Enhanced name sizing algorithm  
   - Improved price classification
   - Added custom CSS classes

3. **resources/views/labels/a4-preview.blade.php**
   - Added custom template detection logic
   - Enhanced name sizing algorithm
   - Improved price classification  
   - Added custom CSS classes

### Code Locations

#### Template Detection
```php
// Line ~276 in a4-print.blade.php, ~526 in a4-preview.blade.php
$isCustomGrid = $template->name === 'Grid 4x9 Custom (47x31mm)';
```

#### Enhanced Name Logic  
```php
// Lines ~278-307 in a4-print.blade.php, ~528-547 in a4-preview.blade.php
if ($isCustomGrid) {
    // Improved sizing algorithm
    $nameLength = mb_strlen($product->NAME);
    $wordCount = str_word_count($product->NAME);
    // ... sizing logic
}
```

#### Enhanced Price Logic
```php  
// Lines ~352-360 in a4-print.blade.php, ~603-613 in a4-preview.blade.php  
if ($isCustomGrid) {
    $priceLength = mb_strlen($priceText);
    $priceLengthClass = $priceLength <= 5 ? 'custom-normal' : 
                       ($priceLength <= 6 ? 'custom-long' : 'custom-extra-long');
}
```

## Testing

### Name Sizing Tests
```php
// Test cases for name sizing
$testCases = [
    ['name' => 'Milk Alt OAT', 'expected' => 'custom-extra-short', 'font' => '18pt'],
    ['name' => 'NHP Sleep Support (60cps)', 'expected' => 'custom-short', 'font' => '15pt'],
    ['name' => 'Het Dichtste Bij Spelt tagliatelle 500g', 'expected' => 'custom-medium', 'font' => '13pt'],
    ['name' => '3 Little Goats Goat cheese spread natural 150g', 'expected' => 'custom-long', 'font' => '11pt'],
];
```

### Price Sizing Tests  
```php
// Test cases for price sizing
$priceTests = [
    ['price' => '€9.99', 'expected' => 'custom-normal', 'font' => '24pt'],
    ['price' => '€32.95', 'expected' => 'custom-long', 'font' => '22pt'], 
    ['price' => '€100.99', 'expected' => 'custom-extra-long', 'font' => '20pt'],
];
```

## Performance Considerations

### Algorithmic Complexity
- **Name Classification**: O(1) - constant time operations
- **Character Counting**: O(n) where n = string length (minimal impact)
- **Word Counting**: O(n) where n = string length (minimal impact)
- **Template Detection**: O(1) - single string comparison

### Memory Usage
- **Additional CSS**: ~2KB per template view
- **Logic Overhead**: Negligible (few variables per label)
- **Database Impact**: None (uses existing template system)

### Caching Strategy
The system leverages Laravel's existing view caching:
```bash
php artisan view:clear  # Clears template cache after changes
```

## Extending the System

### Adding New Templates

1. **Create Template Record**
```php
LabelTemplate::create([
    'name' => 'My Custom Template (50x35mm)',
    'description' => 'Custom template with specific sizing',
    // ... configuration
]);
```

2. **Add Detection Logic**
```php
$isMyCustom = $template->name === 'My Custom Template (50x35mm)';
```

3. **Implement Sizing Algorithm**
```php
if ($isMyCustom) {
    // Custom sizing logic here
    $lengthClass = calculateMyCustomLength($product->NAME);
}
```

4. **Add CSS Classes**
```css
.label-name-custom[data-length="my-class"] {
    font-size: 16pt;
    /* Custom styling */
}
```

### Customizing Existing Templates

To modify the Grid 4x9 Custom algorithm:

1. **Adjust Thresholds**
```php
// Change character length thresholds
if ($nameLength <= 8) {          // Was 10
    $lengthClass = 'custom-tiny';
} elseif ($nameLength <= 18) {   // Was 20  
    $lengthClass = 'custom-extra-short';
}
```

2. **Modify Font Sizes**
```css  
.label-name-4x9[data-length="custom-tiny"] {
    font-size: 24pt; /* Was 22pt */
}
```

3. **Add New Size Classes**
```php
// Add new classification
elseif ($nameLength <= 12) {
    $lengthClass = 'custom-mini'; // New class
}
```

```css
.label-name-4x9[data-length="custom-mini"] {
    font-size: 20pt;
    -webkit-line-clamp: 2;
    line-height: 1.1;
}
```

## Troubleshooting

### Common Issues

1. **Templates Not Updating**
```bash
php artisan view:clear
```

2. **CSS Not Applying**  
- Check template name matching exactly
- Verify CSS selector specificity
- Ensure `data-length` attribute is set correctly

3. **Font Sizes Not Changing**
- Confirm `$isCustomGrid` condition is met
- Check that template name matches exactly
- Verify CSS classes are loaded

### Debugging Tools

```php
// Add to template for debugging
@if($isCustomGrid)
    <!-- DEBUG: Using custom grid logic -->
    <!-- Name: {{ $product->NAME }} ({{ mb_strlen($product->NAME) }} chars, {{ str_word_count($product->NAME) }} words) -->
    <!-- Class: {{ $lengthClass }} -->
@endif
```

## Migration Notes

### From Legacy System
- Original Grid 4x9 template preserved for backward compatibility
- Default switched automatically - no user action required
- Templates can be switched back via admin interface if needed

### Database Changes
```sql
-- Set Grid 4x9 Custom as default
UPDATE label_templates SET is_default = 0;
UPDATE label_templates SET is_default = 1 WHERE name = 'Grid 4x9 Custom (47x31mm)';
```

## Security Considerations

### Input Validation
- Product names: Already validated by Product model
- Template selection: Validated through LabelTemplate model
- No user input directly affects sizing logic

### XSS Prevention  
- All output properly escaped with Blade `{{ }}` syntax
- CSS classes generated server-side (no client input)
- Template names stored in database with validation

## Maintenance

### Regular Tasks
1. **Monitor Performance**: Check template rendering times
2. **User Feedback**: Collect feedback on readability improvements  
3. **Template Usage**: Track which templates are most popular
4. **Font Optimization**: Adjust thresholds based on real-world usage

### Update Process
1. Test changes on development environment
2. Clear view cache: `php artisan view:clear`
3. Deploy template updates
4. Monitor for rendering issues
5. Rollback template default if necessary