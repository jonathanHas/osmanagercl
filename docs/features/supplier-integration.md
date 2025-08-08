# Supplier Integration

This document covers the external supplier integration system, including product images, website links, live pricing, and delivery verification.

## Overview

The supplier integration system provides seamless connectivity with external suppliers, enabling real-time access to product images, pricing data, and automated delivery processing. Currently supporting Udea (Ekoplaza) with an extensible architecture for additional suppliers.

## Current Features

### External Product Images
- **CDN Integration**: Direct image loading from supplier CDNs
- **Automatic URL Generation**: Based on product barcode/CODE
- **Lazy Loading**: Performance-optimized with `loading="lazy"`
- **Error Handling**: Graceful fallback for missing images
- **Hover Previews**: Large 192x192px overlays on hover

### Supplier Website Links
- **Direct Product Search**: Links to supplier product pages
- **Supplier Code Integration**: Uses internal supplier codes for searches
- **Multi-Supplier Support**: Configurable per supplier

### Live Price Comparison
- **Real-Time Scraping**: Current prices from supplier websites
- **Cost Analysis**: Purchase price vs. selling price comparison
- **Margin Calculations**: Including 15% transport costs
- **Customer Price Extraction**: Retail price data for competitive analysis
- **Delivery Integration**: Automatic pricing for new products from deliveries

### Barcode Extraction
- **Multiple Pattern Support**: Handles various HTML formats
- **Table-Based Extraction**: Supports `<td>EAN</td><td>barcode</td>`
- **Class-Based Extraction**: `<td class="wt-semi">EAN</td>`
- **Colon-Separated Format**: `EAN: barcode`
- **EAN-13 Validation**: Validates 13-digit codes starting with '87'

## Configuration

### Supplier Configuration (`config/suppliers.php`)

```php
return [
    'udea' => [
        'supplier_ids' => [5, 44, 85],
        'image_url' => 'https://cdn.ekoplaza.nl/ekoplaza/producten/small/{CODE}.jpg',
        'website_search' => 'https://www.udea.nl/search/?qry={SUPPLIER_CODE}',
        'display_name' => 'Udea',
        'enabled' => true,
        'scraping' => [
            'base_url' => 'https://www.udea.nl',
            'search_path' => '/search/?qry={code}',
            'auth_required' => true,
            'selectors' => [
                'price' => '.product-price',
                'customer_price' => '.customer-price',
                'barcode' => ['td:contains("EAN")', '.wt-semi']
            ]
        ]
    ],
    'independent' => [
        'supplier_ids' => [37], // Independent supplier ID
        'image_url' => 'https://iihealthfoods.com/cdn/shop/files/{SUPPLIER_CODE}_1.webp?width=533',
        'website_search' => 'https://iihealthfoods.com/search?q={SUPPLIER_CODE}',
        'display_name' => 'Independent Health Foods',
        'enabled' => true,
        'csv_format' => [
            'headers' => ['Code', 'Product', 'Ordered', 'Qty', 'RSP', 'Price', 'Tax', 'Value'],
            'price_type' => 'case', // Price field is per case, not per unit
            'supports_vat' => true,
            'quantity_notation' => 'ordered/received' // Supports "6/5" format
        ]
    ]
];
```

### Service Architecture

#### SupplierService (`app/Services/SupplierService.php`)
- **`hasExternalIntegration($supplierId)`**: Check if supplier has external features
- **`getExternalImageUrl($product)`**: Generate image URL for product
- **`getExternalImageUrlByBarcode($supplierId, $barcode)`**: Direct barcode-based URLs
- **`getSupplierSearchUrl($product)`**: Generate supplier search links
- **`getSupplierDisplayName($supplierId)`**: Get human-readable supplier name

#### UdeaScrapingService (`app/Services/UdeaScrapingService.php`)
- **`getProductData($productCode)`**: Get comprehensive product data with caching
- **`getProductDataForDeliveryItem($item)`**: Wrapper for delivery item integration
- **`extractCustomerPrice($html)`**: Extract customer pricing from product pages
- **`extractBarcodeFromDetail($html)`**: Extract product barcode from detail page
- **Session Management**: Handles authentication and cookies
- **Error Handling**: Graceful degradation on scraping failures

## Supported Suppliers

### Udea (Ekoplaza) - Full Integration
- **Image CDN**: High-quality product images
- **Website Scraping**: Live pricing and barcode extraction  
- **Delivery Integration**: Automatic product matching and pricing
- **Status**: ✅ Fully Operational

### Independent Health Foods - Full Integration
- **CSV Import**: Specialized format with VAT calculations
- **Tax Integration**: Automatic Irish VAT rate detection and normalization
- **Product Creation**: Auto-populated tax categories based on VAT rates
- **Case-to-Unit Conversion**: Handles case pricing with automatic unit cost calculation
- **RSP Support**: Recommended selling price integration
- **Product Images**: Direct CDN access with automatic format detection
- **Website Integration**: Direct product search links
- **Status**: ✅ Fully Operational

**Key Features**:
- **VAT Rate Calculation**: Formula (Tax ÷ Value) × 100 with normalization to Irish rates
- **Standard Irish VAT Rates**: 0%, 9%, 13.5%, 23% with automatic POS tax category mapping
- **Quantity Notation**: "ordered/received" format support (e.g., "6/5", "1/0")
- **Smart Unit Pricing**: Extracts units per case from product names (e.g., "12x90g" = 12 units)
- **Tax Category Auto-Selection**: Maps normalized VAT rates to POS tax categories:
  - 0% → Tax Zero (ID: 000)
  - 9% → Tax Second Reduced (ID: 003)  
  - 13.5% → Tax Reduced (ID: 001)
  - 23% → Tax Standard (ID: 002)
- **Image CDN Integration**: Automatic detection of image paths and formats:
  - Tries multiple CDN paths: `/cdn/shop/files/` and `/cdn/shop/products/`
  - Supports both `.webp` and `.jpg` formats
  - Smart fallback system for maximum compatibility
- **Product Image Display**: Shows product images on creation page with:
  - Click-to-view full size modal
  - Automatic image loading when supplier code is entered
  - Visual feedback for clickable images
- **Test Page**: Dedicated testing interface at `/products/independent-test` for:
  - Verifying image availability
  - Testing product data extraction
  - Debugging supplier code lookups

## Implementation Details

### Product List Integration
When "Show suppliers" is enabled:
- Thumbnail images (40x40px) in product listings
- Direct links to supplier product pages
- Visual indicators for external integration availability

### Product Detail Integration
Dedicated supplier information section showing:
- Large product image (128x128px)
- Supplier name and product link
- Live price comparison data
- Transport cost calculations
- Margin analysis tools

### Delivery System Integration
- **CSV Import**: Process Udea delivery files
- **Barcode Retrieval**: Automatic extraction for new products
- **Image Display**: Shows images during scanning workflow
- **Hover Previews**: Large overlays with product information
- **New Product Support**: Images work immediately after barcode retrieval
- **Automatic Pricing**: UDEA customer prices used when creating products from deliveries

### Pricing System Integration
- **VAT-Inclusive Display**: Shows prices with VAT included
- **Quick Actions**: Update prices based on supplier data
- **Competitive Pricing**: +10% over supplier cost
- **Optimal Margin**: 35% margin calculations
- **Customer Price Match**: Match retail prices

### UDEA Delivery Pricing Integration
- **Smart Price Detection**: Automatically detects UDEA suppliers (IDs: 5, 44, 85)
- **Scraped Customer Prices**: Uses real UDEA retail prices instead of markup
- **Visual Indicators**: Green badges show scraped prices, blue badges show calculated
- **Fallback Logic**: Falls back to 30% markup when scraping unavailable
- **Real-time Updates**: AJAX endpoint for dynamic price fetching
- **User Override**: Prices can still be manually adjusted before saving

## Adding New Suppliers

### Step 1: Configuration
Add supplier configuration to `config/suppliers.php`:

```php
'new_supplier' => [
    'supplier_ids' => [/* supplier IDs from database */],
    'image_url' => 'https://example.com/products/{CODE}.jpg',
    'website_search' => 'https://example.com/search?q={SUPPLIER_CODE}',
    'display_name' => 'New Supplier',
    'enabled' => true,
    'scraping' => [
        'base_url' => 'https://example.com',
        'auth_required' => false,
        // ... additional scraping config
    ]
],
```

### Step 2: Create Scraping Service (Optional)
If live data is needed:

```php
namespace App\Services;

class NewSupplierScrapingService extends BaseScrapingService
{
    public function fetchPriceBySupplierCode(string $code): ?array
    {
        // Implementation specific to supplier's website
    }
}
```

### Step 3: Register Service
Update `SupplierService` to use the new scraping service when needed.

## UI Components

### Supplier External Info Component
`resources/views/components/supplier-external-info.blade.php`

Usage:
```blade
<x-supplier-external-info 
    :product="$product" 
    :show-image="true"
    :show-link="true"
    size="large" 
/>
```

Parameters:
- `product`: Product model instance
- `show-image`: Display product image (default: true)
- `show-link`: Display supplier link (default: true)
- `size`: Image size - 'small' (40px), 'medium' (80px), 'large' (128px)

### Pricing Section Component
`resources/views/components/product-pricing-section.blade.php`

Integrated supplier pricing display with:
- Live supplier cost comparison
- VAT calculations
- Margin analysis
- Quick update actions

## Performance Considerations

### Image Loading
- **Lazy Loading**: All images use `loading="lazy"`
- **CDN Usage**: Direct loading from supplier CDNs
- **Error Handling**: Failed images hidden via JavaScript
- **Caching**: Browser caching leveraged via CDN headers

### Scraping Optimization
- **Session Reuse**: Maintains authenticated sessions
- **Rate Limiting**: Respects supplier website limits
- **Caching**: 15-minute cache for pricing data
- **Queue Processing**: Barcode extraction via job queue

## Security Considerations

- **URL Sanitization**: All dynamic URLs are properly escaped
- **External Content**: Images loaded from trusted CDNs only
- **Scraping Authentication**: Credentials stored securely in .env
- **CORS Headers**: Proper handling of cross-origin requests

## Troubleshooting

### Images Not Displaying
1. Check product has valid barcode/CODE
2. Verify supplier configuration is enabled
3. Check browser console for CORS errors
4. Confirm CDN URL pattern is correct

### Scraping Failures
1. Verify scraping credentials in .env
2. Check supplier website hasn't changed structure
3. Review logs for authentication errors
4. Confirm network connectivity to supplier

### Price Mismatches
1. Check transport cost percentage (default 15%)
2. Verify VAT calculations are correct
3. Confirm scraping selectors are up-to-date
4. Review margin calculation formulas

## Future Enhancements

### Planned Features
- EDI integration for automated data exchange
- Multiple image sizes and galleries
- Historical price tracking
- Automated price update notifications
- Supplier API integrations
- Product specification sync

### Supplier Expansion
- Independent Health Foods integration
- Additional organic/specialty suppliers
- Direct manufacturer connections
- Wholesale marketplace integration