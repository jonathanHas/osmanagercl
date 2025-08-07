# Product Pricing System

The application features a comprehensive pricing management system with supplier integration, live price comparison, and precise VAT calculations.

## Overview

The pricing system has been redesigned to provide a consolidated interface for managing product prices with real-time supplier data integration. It addresses common pricing challenges including VAT calculation precision and competitive pricing analysis.

## Key Features

### 1. Consolidated Pricing Interface
- **Single-section design**: All pricing information consolidated in one organized section
- **3-column layout**: Your Pricing | Supplier Pricing | Quick Actions
- **Visual indicators**: Color-coded status badges for competitive analysis
- **Collapsible advanced analysis**: Detailed calculations hidden by default

### 2. VAT-Inclusive Pricing Input
- **User-friendly input**: Enter selling prices including VAT for easier management
- **Real-time calculation**: Net price calculated and displayed as you type
- **Precision preservation**: 4-decimal storage ensures VAT calculations are exact
- **Confirmation dialogs**: Shows both gross and net prices before updating

### 3. Live Supplier Integration
- **Real-time price data**: Fetches current pricing from Udea supplier
- **Transport cost calculation**: Automatic 15% transport cost addition
- **Customer price extraction**: Retrieves retail prices from supplier product pages
- **Discount detection**: Identifies and tracks temporary supplier discounts
- **Delivery Integration**: Automatic pricing for new products from UDEA deliveries

### 4. High-Precision Calculations
- **4-decimal precision**: PRICESELL field stores up to 4 decimal places
- **Exact VAT preservation**: Prevents rounding errors in VAT calculations
- **Example**: €7.30 (23% VAT) → €5.9350 (net) → €7.30 (display)

## Architecture

### Components

#### Product Pricing Section (`resources/views/components/product-pricing-section.blade.php`)
The main pricing interface component featuring:
- Consolidated 3-column pricing display
- Visual status indicators and badges
- Quick action buttons for price updates
- Collapsible advanced analysis section
- VAT-inclusive price input with real-time calculation

#### Supplier Info Card (`resources/views/components/supplier-info-card.blade.php`)
Clean supplier information display:
- Simplified layout with essential supplier details
- Product image display (32x32 optimized)
- Direct links to supplier websites
- Case unit information and supplier codes

#### UdeaScrapingService (`app/Services/UdeaScrapingService.php`)
Enhanced supplier data integration:
- Customer price extraction from product detail pages
- **Improved product name extraction using detail page structure**
- **Full product name format**: "Brand ProductName Size" (e.g., "Ice Cream Factory Almond Choc 120 ml")
- Improved price parsing with largest-price logic
- Dutch/English language support
- 1-hour caching with rate limiting

### Models & Precision

#### Product Model Changes
```php
protected $casts = [
    'PRICESELL' => 'decimal:4',  // Increased from decimal:2
    // ... other casts
];
```

#### Controller Validation
```php
$request->validate([
    'net_price' => 'required|numeric|min:0|max:999999.9999',  // 4 decimal support
]);
```

## Price Update Methods

### 1. Manual Cost Update
- Direct cost price entry
- Maintains existing functionality
- Used for non-supplier products

### 2. Delivery-Based Price Management
**Enhanced Feature**: Complete price management during delivery verification.

**Key Capabilities**:
- **Bulk Cost Updates**: Update multiple product costs based on delivery pricing with configurable thresholds
- **Individual Price Editing**: Quick price adjustments with real-time margin feedback
- **VAT-Exclusive Margin Analysis**: Accurate profitability calculations excluding VAT
- **Safety Controls**: Zero-cost item detection and protection during bulk updates
- **Label Integration**: Automatic addition to label queue for immediate shelf price updates

**Usage Context**:
- Triggered during delivery verification when price differences are detected
- Provides immediate cost adjustment capabilities without leaving delivery workflow
- Supports both emergency price fixes and planned repricing during stock receipt

### Delivery-Based Pricing (UDEA Integration)
- **Automatic Detection**: Recognizes UDEA suppliers during product creation from deliveries
- **Scraped Customer Prices**: Uses real UDEA retail prices instead of calculated markup
- **Enhanced Product Names**: Extracts complete product names in "Brand ProductName Size" format
- **Smart Fallback**: Falls back to 30% markup if scraping fails or unavailable
- **Visual Indicators**: Green badges for scraped prices, blue for calculated prices
- **User Control**: Prices can be manually adjusted before saving product
- **Real-time Updates**: AJAX endpoint available for dynamic price fetching

#### Product Name Extraction
The system now uses an improved extraction method that:
- **Fetches detail pages** from UDEA for complete product information
- **Extracts components separately**:
  - Product name from `<h2 class="prod-title">Almond Choc</h2>`
  - Brand from `<div class="detail-subtitle"><strong>Ice Cream Factory</strong></div>`
  - Size from `<span class="prod-qty">120 ml</span>`
- **Constructs full names** in consistent format: "Ice Cream Factory Almond Choc 120 ml"
- **Replaces complex patterns** with simple, reliable HTML parsing
- **Provides comprehensive logging** for debugging and monitoring

#### Enhanced Product Creation Interface
When creating products from UDEA delivery items, the system now provides:
- **Delivery vs Scraped Name Comparison**: Shows both the delivery description and enhanced scraped name
- **Visual Name Enhancement**: Green info box highlighting the improved product name format
- **One-Click Name Update**: "Use This Name" button to replace delivery description with scraped name
- **User Choice Preservation**: Users can choose to keep delivery description or use enhanced name
- **Visual Feedback**: Button animation and field highlighting when name is updated
- **Non-Disruptive**: Only appears when scraped name differs from delivery description

### 3. VAT-Inclusive Selling Price
- **Input**: Final selling price including VAT
- **Calculation**: Automatic net price calculation using product's VAT rate
- **Storage**: Full precision net price stored
- **Display**: Shows calculated net price below input

### 4. Supplier-Based Updates
Quick action buttons for supplier-driven pricing:
- **Update Cost**: Match Udea unit cost
- **Match Customer**: Set price to Udea's retail price
- **Competitive (+10%)**: Udea price + 10% margin
- **Optimal Price**: Cost + transport + 35% margin

### 4. Delivery-Based Quick Price Editing
**New Feature**: Integrated price management directly from delivery verification interface.

**Features**:
- **Modal Price Editor**: Clean interface for instant price adjustments during delivery processing
- **Dual Input Modes**: Support for both gross price (VAT-inclusive) and net price entry
- **Real-time Margin Calculation**: Live updates showing margin impact as prices are adjusted
- **Automatic Label Queue Integration**: Price updates automatically added to label printing queue
- **VAT-Aware Calculations**: Uses product's tax category for accurate gross/net conversions
- **Clickable Price Interface**: Current sell prices are clickable with hover edit indicators

**Implementation**:
- Route: `PATCH /deliveries/{delivery}/items/{item}/price`
- Controller: `DeliveryController@updateItemPrice()`
- Integration: Automatic `LabelLog::logPriceUpdate()` for shelf price updates
- Validation: Supports both gross and net price inputs with proper tax calculations

### 5. Advanced Pricing Options
Collapsible section with additional strategies:
- Industry average pricing (30% margin)
- Break-even price calculations
- Margin analysis vs supplier costs
- Custom margin calculations

## VAT Calculation Precision

### Problem Solved
Previous system had rounding issues:
- Input: €7.30 (VAT inclusive)
- Old: €7.30 ÷ 1.23 = 5.93 (rounded) → 5.93 × 1.23 = €7.29 ❌
- New: €7.30 ÷ 1.23 = 5.9350 (4 decimals) → 5.9350 × 1.23 = €7.30 ✓

### Implementation
1. **JavaScript**: Sends full precision to server (no rounding)
2. **Validation**: Accepts up to 4 decimal places
3. **Model**: Stores with `decimal:4` precision
4. **Display**: Maintains exact VAT-inclusive price

## User Interface Improvements

### Layout Reorganization
- **Removed**: Price from quick stats bar (consolidated in pricing section)
- **Simplified**: Quick stats to Stock, VAT Rate, Category only
- **Enhanced**: Product details with better grouping and visual indicators
- **Mobile optimized**: Responsive design with proper stacking

### Visual Indicators
- **Green**: Competitive pricing, positive margins
- **Orange**: Price review needed, warnings
- **Blue**: Informational, neutral status
- **Purple**: Customer/retail pricing
- **Icons**: SVG icons for quick recognition

### Collapsible Sections
- **Advanced Analysis**: Hidden by default, expandable
- **More Pricing Options**: Secondary actions in collapsible menu
- **Supplier Details**: Full supplier information when needed

## Configuration

### Precision Settings
```php
// Model casting for high precision
'PRICESELL' => 'decimal:4'

// Validation for 4 decimal places
'net_price' => 'required|numeric|min:0|max:999999.9999'
```

### VAT Rate Integration
The system automatically uses the product's configured VAT rate for all calculations, supporting any VAT percentage.

### Supplier Integration
Currently configured for Udea supplier (IDs: 5, 44, 85) with:
- Base URI: https://www.udea.nl
- Authentication: Form-based login
- Caching: 1-hour TTL
- Rate limiting: 2-second delays

## Future Enhancements

### Planned Features
1. **Price History Tracking**: Log all price changes with timestamps
2. **Automated Repricing**: Scheduled updates based on supplier prices
3. **Margin Alerts**: Notifications when margins fall below thresholds
4. **Bulk Price Updates**: Update multiple products simultaneously
5. **Price Rules Engine**: Automated pricing based on configurable rules

### Additional Suppliers
The system is designed to support multiple suppliers with similar integration patterns.

## Troubleshooting

### Common Issues

#### VAT Calculation Not Exact
- **Cause**: JavaScript rounding before server submission
- **Solution**: Ensure full precision is sent to server (no `.toFixed()` on submission)

#### Supplier Prices Not Loading
- **Check**: Network connectivity to supplier website
- **Verify**: Authentication credentials in configuration
- **Clear**: Cache using `/products/{id}/refresh-udea-pricing`

#### Price Updates Not Saving
- **Verify**: Validation rules allow sufficient precision
- **Check**: Model casting configuration
- **Ensure**: Database column supports decimal precision

### Debug Tools
- **Customer Price Debug**: `/tests/customer-price/{productCode}`
- **Connection Test**: UdeaScrapingService test methods
- **Cache Management**: Built-in cache clearing functionality