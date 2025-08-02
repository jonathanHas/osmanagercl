# Label Printing Consistency Guide

This guide ensures consistent label rendering across development and production environments.

## Environment Requirements

### 1. System Fonts
Ensure Arial font is installed on both dev and production:

```bash
# Ubuntu/Debian
sudo apt-get install fonts-liberation fonts-liberation2

# CentOS/RHEL
sudo yum install liberation-fonts

# Verify Arial is available
fc-list | grep -i arial
```

### 2. PHP Configuration
Ensure consistent PHP settings:

```ini
; php.ini settings
mbstring.internal_encoding = UTF-8
mbstring.func_overload = 0
default_charset = "UTF-8"
```

### 3. Browser/Rendering Engine
For headless printing or PDF generation:

```bash
# Install consistent Chrome version
wget -q -O - https://dl-ssl.google.com/linux/linux_signing_key.pub | sudo apt-key add -
sudo sh -c 'echo "deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main" >> /etc/apt/sources.list.d/google.list'
sudo apt-get update
sudo apt-get install google-chrome-stable

# Verify version
google-chrome --version
```

## CSS Consistency Measures

### 1. Explicit Units
All CSS in the label templates uses explicit units:
- Font sizes: `pt` (points) - consistent across screens
- Dimensions: `mm` (millimeters) - physical measurements
- No relative units (em, rem, %) that could vary

### 2. Font Stack
```css
font-family: Arial, Helvetica, sans-serif;
```
Fallback fonts ensure consistency if Arial is missing.

### 3. CSS Reset
The templates include a CSS reset to normalize browser defaults:
```css
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
```

### 4. Print-Specific CSS
```css
@media print {
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
```

## Deployment Checklist

### Pre-Deployment
- [ ] Test labels in production-like environment
- [ ] Verify font installation: `fc-list | grep -i arial`
- [ ] Check PHP version matches: `php -v`
- [ ] Verify mbstring extension: `php -m | grep mbstring`

### Database Sync
- [ ] Run migrations: `php artisan migrate`
- [ ] Verify label templates match:
```sql
-- Compare template data
SELECT name, font_size_price, font_size_name, font_size_barcode 
FROM label_templates 
WHERE name = 'Grid 4x9 (47x31mm)';
```

### Post-Deployment Verification
1. Generate test labels for products with:
   - Short names (< 15 chars)
   - Long names (> 40 chars)
   - 4-digit prices (€15.95)

2. Compare output:
   - Font sizes match expected values
   - No text truncation/ellipsis
   - Barcode/price proportions correct

### Debugging Commands

```bash
# Check computed CSS in production
# Add this temporary route for debugging
Route::get('/label-debug', function() {
    $template = \App\Models\LabelTemplate::where('name', 'Grid 4x9 (47x31mm)')->first();
    return view('labels.debug', compact('template'));
});
```

Create `resources/views/labels/debug.blade.php`:
```blade
<!DOCTYPE html>
<html>
<head>
    <title>Label Debug</title>
</head>
<body>
    <h1>Label Template Debug</h1>
    <pre>{{ json_encode($template->toArray(), JSON_PRETTY_PRINT) }}</pre>
    <h2>CSS Dimensions</h2>
    <pre>{{ json_encode($template->css_dimensions, JSON_PRETTY_PRINT) }}</pre>
    <h2>Environment</h2>
    <pre>
PHP Version: {{ PHP_VERSION }}
MB String: {{ extension_loaded('mbstring') ? 'Loaded' : 'Not Loaded' }}
Charset: {{ ini_get('default_charset') }}
    </pre>
</body>
</html>
```

## Common Issues & Solutions

### Issue: Different font rendering
**Solution**: Install exact same font files on both systems
```bash
# Copy font files from dev to production
scp /usr/share/fonts/truetype/liberation/*.ttf user@production:/tmp/
# Install on production
sudo cp /tmp/*.ttf /usr/share/fonts/truetype/
sudo fc-cache -f -v
```

### Issue: Character encoding differences
**Solution**: Ensure UTF-8 throughout
```bash
# Database
ALTER DATABASE your_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Apache
AddDefaultCharset UTF-8

# Nginx
charset utf-8;
```

### Issue: Different DPI/scaling
**Solution**: Use physical units (mm, pt) not pixels
- Already implemented in current templates

## Testing Script

Create `test-label-consistency.php`:
```php
<?php
// Run on both dev and production
$products = [
    ['name' => 'Short', 'price' => '€9.99'],
    ['name' => 'A. Vogel Herbamare 250g b', 'price' => '€15.95'],
    ['name' => 'Very long product name that should wrap to multiple lines without truncation', 'price' => '€123.45']
];

foreach ($products as $product) {
    $nameLen = strlen($product['name']);
    $priceLen = mb_strlen($product['price']);
    
    echo "Product: {$product['name']}\n";
    echo "Name length: $nameLen chars\n";
    echo "Price length: $priceLen chars\n";
    echo "Name category: " . ($nameLen <= 15 ? 'short' : ($nameLen <= 25 ? 'medium' : ($nameLen <= 40 ? 'long' : 'extra-long'))) . "\n";
    echo "Price category: " . ($priceLen <= 5 ? 'normal' : ($priceLen <= 6 ? 'long' : 'extra-long')) . "\n";
    echo "---\n";
}
```

## Version Control

Track these files for label consistency:
- `/resources/views/labels/a4-preview.blade.php`
- `/resources/views/labels/a4-print.blade.php`
- `/app/Models/LabelTemplate.php`
- `/database/migrations/*label*.php`
- This guide: `/docs/deployment/label-consistency-guide.md`

## Final Notes

1. **Never use JavaScript for layout calculations** - CSS only
2. **Test with actual printer** - Screen != Print
3. **Keep font files in repo** if possible (check licensing)
4. **Document any production-specific tweaks** in this guide