# Changelog

All notable changes to OSManager CL will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **☕ KDS Clear All Orders Fix**: Improved reliability of clearing all orders (2025-08-11)
  - Changed from deleting orders to marking them as completed
  - Prevents orders from reappearing after clearing
  - Simplified implementation without complex tracking
  - Orders remain in database for audit trail
  - Automatic cleanup after 24 hours
  - Updated UI button text to "Complete All Orders"

### Added

- **💰 Cash Reconciliation System**: Comprehensive end-of-day cash management (2025-08-11)
  - **Physical Cash Counting**: Count by denomination (€50 notes to 10c coins)
  - **Legacy Data Import**: Seamlessly imports existing data from PHP system
    - Converts stored totals to denomination counts (€400 → 8 × €50 notes)
    - Imports supplier payments from `payeePayments` table
    - Imports daily notes from `dayNotes` table
  - **Variance Tracking**: Automatic calculation against POS totals
  - **Float Management**: Automatic carry-over from previous day
  - **Supplier Payments**: Track up to 4 cash payments to suppliers
  - **Multi-Till Support**: Manage all terminals from one interface
  - **Real-time Calculations**: Dynamic totals with Alpine.js
  - **Export to CSV**: Generate reports for accounting
  - **Audit Trail**: Complete tracking of who created/modified reconciliations
  - **Role-Based Access**: Manager and Admin only permissions
  - **Database Structure**: 3 new tables for reconciliations, payments, and notes
  - **Repository Pattern**: Clean separation of business logic
  - **Modern UI**: Responsive design with color-coded variance indicators

- **🔐 User Roles & Permissions System**: Complete RBAC implementation (2025-08-08)
  - **Three-tier Role System**: Admin, Manager, and Employee roles
  - **30+ Granular Permissions**: Organized by modules (Products, Sales, Delivery, etc.)
  - **Database Structure**: Four new tables for roles, permissions, and relationships
  - **Middleware Protection**: `role` and `permission` middleware for route protection
  - **HasPermissions Trait**: Comprehensive permission checking methods
  - **Flexible Authorization**: Works in controllers, views, and middleware
  - **Default Permissions**:
    - Admin: Full system access
    - Manager: Sales reports, analytics, product management
    - Employee: Basic operational tasks
  - **Test Interface**: Role testing page at `/roles-test`
  - **Seeder System**: Automated setup of roles and permissions
  - **User Management Integration**: 
    - Role selection in user create/edit forms
    - Security warnings for role changes
    - Prevention of self-demotion
    - Protection of last admin user
    - Role column in user list with badges
  - **Profile Role Display**:
    - Comprehensive role information section in user profile
    - Role badges with color coding and icons
    - Permission count and access summary
    - Key permissions display
    - Help text for requesting additional access
  - **Blade Integration**: Permission checks in views
  - **Security Features**: Admin override, role hierarchy, audit support
  - **Specialized Agent**: Custom Claude Code agent for role system development

- **📝 Barcode Editing Feature**: Ability to edit product barcodes with comprehensive safety measures (2025-08-07)
  - **Edit Interface**: Inline barcode editing directly from product detail page
  - **Safety Warnings**: Clear warnings about affected records before changes
  - **Confirmation Required**: Checkbox confirmation to prevent accidental changes
  - **Transaction Safety**: All updates wrapped in database transaction
  - **Automatic Updates**: Updates all dependent records:
    - Supplier link records
    - Stocking records (handles primary key change)
    - Label logs with audit trail
    - Product metadata
    - Veg details
  - **Audit Trail**: Creates special 'barcode_change' event in label_logs
  - **Validation**: Ensures new barcode is unique across products
  - **Error Handling**: Comprehensive error messages and rollback on failure
  - **Visual Design**: Yellow warning colors for high visibility
  - **Metadata Storage**: Stores old and new barcode in JSON metadata field

- **🛠️ Product Creation Form Improvements**: Enhanced functionality and fixes (2025-08-07)
  - **UDEA Button Fix**: "View on UDEA Website" button now only shows for UDEA suppliers (IDs: 5, 44, 85)
  - **Independent Support**: Added Independent supplier website links and image preview
  - **Pricing Breakdown Fix**: Initial pricing breakdown now displays correctly on page load
  - **Tax Rates Integration**: Proper tax rates loaded from database for accurate calculations
  - **Till Visibility Default**: "Show on Till" checkbox now unchecked by default (most products don't need till visibility)
  - **Dynamic Supplier Links**: Links update based on selected supplier type
  - **Improved Validation**: Better handling of supplier-specific features

- **🖼️ Independent Health Foods Product Images**: Full integration with Independent supplier (2025-08-07)
  - **Automatic Image Display**: Product images appear when supplier code is entered
  - **Smart Path Detection**: Automatically tries multiple CDN paths (`/cdn/shop/files/` and `/cdn/shop/products/`)
  - **Format Flexibility**: Supports both `.webp` and `.jpg` image formats
  - **Click-to-View Modal**: Full-size image viewer with zoom capabilities
  - **Test Page**: Dedicated testing interface at `/products/independent-test`
  - **Dynamic Loading**: Images update in real-time as supplier codes change
  - **Visual Feedback**: Hover effects and "click to view" indicators
  - **Fallback System**: Gracefully handles missing images
  - **Website Integration**: Direct links to Independent's product search
  - **Error Handling**: Console logging for debugging image load issues

- **🚨 Product Health Dashboard**: Auto-loading dashboard with critical product insights (2025-01-06)
  - **Good Sellers Gone Silent**: Identifies high performers with no recent sales
  - **Slow Movers**: Products with lowest sales velocity over 60 days
  - **Stagnant Stock**: Products with zero sales in last 30 days
  - **Inventory Alerts**: High-velocity products needing stock attention
  - **Auto-Loading**: Dashboard loads immediately on page view
  - **Stock Levels**: Current stock displayed for all dashboard products
  - **Product Links**: Click any product name to navigate to edit page
  - **Parallel Loading**: All tabs fetch data simultaneously for speed
  - **Visual Design**: Color-coded cards by severity (red, orange, yellow, blue)
  - **Empty States**: Positive feedback when no issues found
  - **Performance**: Sub-second load times with pre-aggregated data

- **📊 Categories Sales Analytics Enhancements**: Major improvements to sales analytics interface (2025-01-06)
  - **Fixed Daily Sales Chart**: Resolved chart initialization preventing graph display
  - **Enhanced Tooltips**: Added day of week to chart tooltips (e.g., "Monday, 1 Mar 2025")
  - **Expandable Product Details**: Dropdown arrows show individual product daily sales
  - **Product Mini Charts**: Each expanded product shows revenue/units trend chart
  - **Column Sorting**: Click headers to sort by Product, Units, Revenue, or Avg Price
  - **Sort Indicators**: Visual arrows show current sort column and direction
  - **Table Structure Fix**: Corrected alignment issues with expandable rows
  - **Data Type Handling**: Fixed formatCurrency() errors with proper float parsing
  - **Loading States**: Separate states for loading, empty, and data display
  - **Performance**: Lazy loading of expanded product data for efficiency

- **📂 Universal Categories Management System**: Complete category management for all product types (2025-08-05)
  - **Universal Interface**: Single system works with any product category
  - **Category Index**: Grid view with product counts, visibility stats, and progress bars
  - **Category Dashboard**: Quick actions, featured products, subcategory navigation
  - **Product Management**: Inline editing of prices and display names per category
  - **Sales Analytics**: Pre-aggregated data with charts and top products per category
  - **Till Visibility**: Toggle products on/off POS per category
  - **Search & Filter**: Find categories and products quickly
  - **Breadcrumb Navigation**: Clear path through category hierarchies
  - **Performance Optimized**: Sub-second response times using OptimizedSalesRepository
  - **Generic Repository Methods**: New category-agnostic methods for any category analysis
  - **Backward Compatible**: Existing Coffee and F&V modules continue to work
  - **Routes**: Complete `/categories` routing structure with all CRUD operations
  - **Navigation**: New "Categories" menu item in sidebar

- **🏷️ Product Display Name Management**: Universal display name editing across all products (2025-08-05)
  - **Inline Editing**: Click-to-edit display names on all product detail pages
  - **HTML Support**: Support for `<br>` tags and HTML formatting in display names
  - **Consistent UX**: Same editing pattern as fruit-veg module for unified experience
  - **AJAX Updates**: Real-time saving with loading states and success feedback
  - **Label Integration**: Display names automatically used in label generation
  - **Cross-Module**: Works for all product categories, not just F&V products
  - **API Endpoint**: New `PATCH /products/{id}/display` endpoint with JSON responses

- **☕ Coffee Module Enhancements**: Advanced product management features (2025-08-04)
  - **Inline Price Editing**: Click-to-edit pricing with VAT calculations
  - **Display Name Management**: Set custom display names for till buttons
  - **Clickable Product Names**: Navigate to product detail pages with context
  - **Context-Aware Navigation**: Smart back button text based on referrer
  - **Till Visibility Toggle**: Fixed invisible toggle switches using Alpine.js patterns
  - **Alpine.js Directive Fix**: Resolved Blade/Alpine.js `@error` directive conflicts

- **☕ Coffee Fresh Module**: New category-specific sales analytics module (2025-08-04)
  - **Complete Implementation**: Full coffee sales tracking and analytics dashboard
  - **Category Support**: Covers both "Coffee Hot" (080) and "Coffee Cold" (081) categories
  - **Sales Analytics**: Comprehensive sales dashboard with charts and individual product breakdowns
  - **Product Management**: Till visibility toggles and product listing
  - **Individual Product Charts**: Expandable rows with Chart.js visualizations per product
  - **Pattern Template**: Establishes simplified pattern for future category modules (Lunch, Cakes, etc.)
  - **Performance**: Uses OptimizedSalesRepository for instant sub-20ms queries

- **🎯 Enhanced F&V Sales Dashboard Navigation**: Advanced date range controls for sales analytics
  - **Week/Month Navigation**: Dedicated arrow buttons for intuitive week and month increments
  - **Quick Period Selector**: Pre-configured periods (Today, This Week, Last Month, Latest Data)
  - **Smart Date Defaults**: Automatically detects latest sales data period (June 18 - July 17, 2025)
  - **Manual Date Inputs**: Compact date selectors for precise range control
  - **Period Information Display**: Shows current range with duration (1 week, 30 days, etc.)
  - **Mobile Responsive Design**: Compact controls optimized for all screen sizes

- **📊 Daily Sales Chart Integration**: Interactive Chart.js visualization for F&V sales trends
  - **Dual-axis display**: Revenue (€) on left axis, Units Sold on right axis
  - **Real-time chart updates**: Chart correctly updates when navigating date ranges
  - **Smooth animations**: Professional chart transitions with data changes
  - **Currency formatting**: Proper Euro (€) display in tooltips and axis labels
  - **Loading states**: Visual indicators during data fetching
  - **Empty data handling**: Graceful "No Data" placeholders
  - **Error recovery**: Automatic chart recreation on update failures

- **🔧 Enhanced Sales Data API**: Improved backend support for sales analytics
  - **Smart date detection**: getSalesData() automatically uses most recent 30-day period with data
  - **Daily sales endpoint**: New getProductDailySales() method for individual product breakdowns
  - **Optimized data flow**: Proper integration with OptimizedSalesRepository
  - **Enhanced logging**: Comprehensive debugging information for troubleshooting

### Fixed

- **🔧 Alpine.js Template Tag Error in Coffee Sales**: Fixed "can't access property 'after', A is undefined" error (2025-08-04)
  - **Root Cause**: Invalid `x-show` directive on `<template>` tags causing Alpine.js DOM manipulation failure
  - **Solution**: Removed `<template x-show="...">` wrapper - template tags cannot use runtime directives
  - **Impact**: Coffee sales table now displays product data correctly with pagination and search
  - **Documentation**: Added troubleshooting guide entry and updated CLAUDE.md with prevention tips

- **🔧 Critical F&V Sales Table Rendering**: Fixed Product Sales Details table not displaying data
  - **Alpine.js template structure**: Resolved nested template issues preventing x-for loop rendering
  - **Table initialization**: Added missing x-init directive to trigger data loading on page load
  - **Data flow debugging**: Enhanced logging to track API responses and data processing

- **📊 Chart Recursion Error Resolution**: Fixed "too much recursion" error in daily sales chart
  - **Non-reactive chart storage**: Moved Chart.js instance outside Alpine.js reactive scope
  - **Update optimization**: Prevented infinite loops caused by Alpine reactivity watching chart internals
  - **Error recovery**: Improved chart recreation logic for failed updates

- **Label Preview Layout Improvements**: Enhanced 4x9 grid label display for better readability
  - Fixed € symbol clipping by restructuring layout from 2 rows to 3 rows
  - Moved barcode number to dedicated bottom row for improved legibility (7pt from 5.5pt)
  - Increased barcode and price horizontal space allocation (48% each from 42%/52%)
  - Larger barcode visual height (18px from 10px) for better scanning
- **Product Name Display Optimization**: Smarter text sizing for better space utilization  
  - Implemented 5-tier responsive font sizing (extra-short to extra-long)
  - Fixed character counting with mb_strlen() for proper UTF-8 support
  - Changed hyphenation from auto to manual to prevent awkward breaks
  - Added letter-spacing adjustments for long text
  - Increased line-clamp for extra-long text (5 lines) to show more content

### Added

- **🚀 Full Store Sales Data Import System**: Revolutionary performance improvement for complete store analytics
  - **Lightning-fast queries**: 100x+ performance improvement (sub-20ms vs 30+ second queries)
  - **Pre-aggregated sales tables**: `sales_daily_summary` and `sales_monthly_summary` with optimized indexes
  - **Complete store coverage**: Imports ALL product categories (not just F&V) with UUID category support
  - **Automated data synchronization**: Daily imports from POS database with scheduling
  - **Historical data processing**: Chunked imports for large datasets with progress tracking
  - **Console commands**: Complete CLI suite for sales data management
    - `sales:import-daily` - Daily sales import with flexible date options
    - `sales:import-historical` - Bulk historical data processing
    - `sales:import-monthly` - Monthly summary generation
    - `sales:test-repository` - Performance testing utilities
  - **OptimizedSalesRepository**: New repository with sub-second analytics queries for full store
    - Full store sales statistics in 17ms (vs 5-10 seconds previously)
    - Daily sales charts in 1.2ms (vs 15+ seconds previously)
    - Top products analysis across all categories in 1.3ms (vs 10+ seconds previously)
    - Category performance for all 60+ categories in 1.3ms (vs 20+ seconds previously)
    - Backward-compatible F&V methods maintained for existing integrations
  - **Import logging and monitoring**: Complete audit trail with `sales_import_log` table
  - **Memory-efficient processing**: Chunked processing for large datasets
  - **Automated scheduling**: Production-ready cron scheduling with overlap protection
  - **Extended database schema**: VARCHAR(50) category_id support for UUID-based categories
  - **🔍 Full Store Data Validation & Comparison System**: Comprehensive validation interface for data integrity
    - **Real-time validation**: Compare imported data against original POS database for all categories
    - **100% accuracy detection**: Identify perfect matches, variances, and discrepancies across full store
    - **Multi-view analysis**: Overview, daily, category, and detailed product-level comparisons for all categories
    - **Performance metrics**: Sub-second validation of entire months of full store data
    - **Interactive web interface**: Tabbed validation dashboard with real-time results for all categories
    - **CSV export**: Export detailed validation results for analysis
    - **Status indicators**: Excellent/Good/Needs Attention classification system
    - **63+ category validation**: Validates all product categories including F&V, beverages, dairy, and more
- **🚀 Fruit & Veg Sales Analytics Optimization**: Revolutionary performance improvement for F&V sales dashboard
  - **Integrated OptimizedSalesRepository**: Replaced slow cross-database queries with blazing-fast pre-aggregated data
  - **Unprecedented Speed Gains**: 100x+ performance improvement across all F&V sales operations
    - F&V Sales Stats: 5-10 seconds → **14ms** (357x faster)
    - Daily Sales Charts: 15+ seconds → **1ms** (13,513x faster) 
    - Top Products Analysis: 10+ seconds → **1ms** (7,117x faster)
    - Full Sales Data: 30+ seconds → **2ms** (18,071x faster)
  - **Sub-Second Response Times**: Complete F&V analytics dashboard loads in under 30ms
  - **Enhanced User Experience**: From unusable timeouts to instant, responsive analytics
  - **100% Data Accuracy**: Leverages validated pre-aggregated sales data
  - **Smart Search**: Ultra-fast product search across F&V sales data
  - **Performance Monitoring**: Real-time performance metrics in API responses
  - **Backward Compatibility**: All existing F&V functionality maintained while dramatically faster
- **Enhanced Label Printing System**: Comprehensive improvements to label design and functionality
  - **New 4x9 Grid Label Template**: Efficient 36 labels per A4 sheet (47.5×30.8mm each)
    - Optimized layout with product name, barcode, and price positioning
    - Intelligent price font sizing (26pt) for clear readability
    - Fixed CSS syntax errors that prevented proper font size rendering
    - Enhanced CSS specificity to override parent constraints
    - Automatic text sizing for product names within available space
    - Improved barcode positioning and sizing for better scanner recognition
  - **Enhanced Label Template System**: Multiple templates with configurable dimensions
  - **Improved Print Templates**: Consistent styling between preview and print modes
  - **Debug Features**: Comprehensive CSS debugging and troubleshooting capabilities
  - **Layout Optimization**: Flexible height management and overflow handling
  - Removed label borders for cleaner appearance when cutting
  - Enhanced padding (4mm) and margins (2mm) for easier label cutting
  - Smart unit display: shows "each" instead of "per ea" for per-unit items
  - Left-aligned product names for better readability
  - Print-optimized CSS to hide navigation buttons during printing
  - Professional borderless design for retail use
- **Enhanced Product Price Editor**: Complete redesign of product price editing interface
  - Dual input modes: gross price (inc VAT) and net price (ex VAT) with toggle switching
  - Real-time pricing breakdown showing cost, net price, VAT amount, gross price, and profit margins
  - Color-coded margin analysis (red <10%, yellow 10-20%, green >20%)  
  - Modal dialog interface replacing inline form for better UX
  - Price change preview before submission
  - Visual consistency with product creation form
  - Improved validation and error handling
  - Automatic VAT conversion using tax category rates
- **Enhanced Product Search & Filtering**: Improved supplier filtering on products page
  - Dynamic supplier dropdown that appears instantly when "Show suppliers" is checked
  - No form submission required to populate dropdown options
  - Suppliers always loaded for immediate availability
  - Automatic dropdown reset when checkbox is unchecked
  - Better performance with efficient loading strategy
- **VAT Handling Improvements**: Fixed product creation and editing to properly handle VAT calculations
  - Product creation now correctly converts VAT-inclusive prices to VAT-exclusive for database storage
  - Enhanced price update methods support both gross and net price inputs
  - Consistent VAT calculation throughout product management workflows
- **Product Detail Management System**: Complete unit and class editing functionality for fruit-veg products
  - Unit editing with inline dropdown (kilogram, each, bunch, punnet, bag)
  - Quality class assignment (Extra, I, II, III) with inline editing  
  - Self-contained database migrations for countries, units, and classes
  - Normalized veg_details table with proper foreign key relationships
  - API endpoints for unit/class CRUD operations (/fruit-veg/units, /fruit-veg/classes)
  - Alpine.js event dispatch system for clean component communication
- Combined management interface (/fruit-veg/manage) unifying availability and price management
- Activity tracking system with product_activity_logs table for audit trail without modifying POS database
- "Recently Added to Till" section on main dashboard with real-time updates
- Progressive loading with "Load More" functionality for better performance
- Database-level filtering for availability status to improve query efficiency
- Real-time dashboard updates when products are added/removed via search
- Till visibility management system replacing legacy veg_availability approach
- Integration with POS database PRODUCTS_CAT table for real-time till synchronization
- TillVisibilityService for centralized till management across product categories
- ProductsCat model for POS database integration
- Quick search component (till-visibility-search) for rapid product visibility updates
- Till visibility search bar on F&V main dashboard for instant access
- Reusable Blade components for consistent till visibility UI
- Migration script to populate PRODUCTS_CAT from veg_availability data
- Foundation for extending till visibility to Coffee, Lunch, and Cakes categories

### Added
- Comprehensive documentation restructuring with new organization system
- CONTRIBUTING.md with coding standards and development guidelines
- Project-focused README.md replacing Laravel boilerplate
- Label system documentation with complete feature overview
- Enhanced label re-queuing functionality with "Add Back to Products Needing Labels"
- Dynamic print/preview forms that use current product state instead of cached data
- Real-time label queue management without requiring full page navigation
- Featured "Available This Week" section on fruit-veg main page with clickable product cards
- Comprehensive fruit-veg product edit interface with tabbed layout (Alpine.js workaround)
- Image upload functionality for fruit-veg products with binary database storage
- Live HTML preview for display name editing with proper entity conversion
- Price history tracking and display in fruit-veg product edit interface
- Sales statistics placeholder interface for future POS integration
- Enhanced fruit-veg product image serving with cache optimization and fallback handling

### Fixed
- Price update functionality in manage screen failing due to Alpine.js `$root` scope issues (now uses self-contained savePrice method)
- Price editing UX improved with explicit save/cancel buttons instead of auto-save on blur
- Price update restrictions preventing updates to hidden products in manage screen (now allows all updates in manage, restricts only in prices page)
- N+1 query performance issues in manage screen by implementing batch loading of price records
- Availability filter not working in manage screen due to post-pagination filtering (now applied at database level)
- Delivery scanning syntax errors in Blade templates
- Division by zero in progress bar calculations
- Null date handling in delivery views
- API data consistency between scan and quantity endpoints
- Label system caching issues where re-queued products didn't appear in print/preview until navigation
- Products not disappearing from "Products Needing Labels" after printing due to incorrect requeue vs print event logic
- JavaScript errors when "Products Needing Labels" section is empty (null reference exceptions)
- Label layout order changed from price-name-barcode to name-price-barcode as requested
- ParseError in fruit-veg/availability.blade.php caused by Alpine.js @error directive conflicting with Blade compilation
- **Daily Sales Overview Chart Issues**: Fixed major Chart.js errors and date range synchronization problems
  - Chart.js "can't access property 'save', t is null" error resolved with smart chart recreation logic
  - Daily Sales Overview now properly responds to date range changes (June data shows when June selected)
  - Implemented intelligent chart destruction/recreation only when data actually changes
  - Added 100ms delay between chart destroy and create operations to prevent Canvas context issues
  - Comprehensive Chart.js error handling with user-friendly error messages
  - Fixed currency display to show Euro (€) throughout all chart labels and statistics
  - Added fallback system using live POS queries when aggregated sales data unavailable
  - Enhanced quick date buttons to use data-aware date calculations (show periods with actual sales)
  - Improved debugging with comprehensive console logging for troubleshooting chart issues
- Template literal and route generation issues in JavaScript sections of Blade templates
- Blade compilation errors due to unescaped Alpine.js event handlers
- HTML entity display issues in fruit-veg product names (display names now render <br> tags properly)
- SQL ordering errors when querying POS database tables without 'updated_at' column
- Tab component slot access compatibility issues with Laravel's slot system (documented with Alpine.js workaround)
- Products removed from till reappearing in "Recently Added" section after page refresh
- **Sales Data Validation System Issues**: Fixed multiple validation accuracy and interface problems
  - **Key matching bug**: Fixed Carbon date formatting in validation service causing 0% accuracy
  - **Daily summary grouping**: Corrected DATE() function usage and keyBy operations for proper aggregation
  - **Tab loading restrictions**: Removed dependency on overview validation for other tabs to function
  - **AJAX endpoint failures**: Fixed Daily, Category, and Detailed comparison tabs not loading data
  - **Test data cleanup**: Removed 120 synthetic test records (€12,186.17) leaving only real POS data
  - **Data integrity verification**: Achieved 100% validation accuracy with clean imported data

### Changed
- Optimized TillVisibilityService to apply filters at database query level instead of post-processing
- Enhanced manage screen performance with progressive loading and optimized queries
- Replaced "Currently Visible on Till" section with dynamic "Recently Added to Till" on main dashboard
- Refactored DeliveryController to use consistent data formatting
- Moved complex PHP logic from Blade templates to controllers
- Replaced session-based print queue with event-based re-queuing system
- Improved getProductsNeedingLabels() algorithm to properly handle timestamp-based event comparison
- Enhanced JavaScript form handling to collect current product IDs dynamically
- Updated label system UI terminology from "Add to Queue" to "Add Back to Products Needing Labels"
- Strengthened notification requirements in CLAUDE.md to ensure consistent user alerts
- Enhanced fruit-veg product display to use regular product names in headers instead of display names
- Updated all F&V views to use "till visibility" terminology instead of "availability"
- Modified FruitVegController to use TillVisibilityService instead of direct DB queries
- Replaced veg_availability table references with PRODUCTS_CAT integration
- Enhanced pricing system to track history independently of till visibility
- Improved statistics to show "visible on till" counts instead of "available" counts
- Improved fruit-veg controller methods with new routes for product editing and image management
- Updated fruit-veg main page to feature available products with responsive grid layout

### Technical Improvements
- Added EVENT_REQUEUE_LABEL to LabelLog model with database migration
- Implemented proper null checks and conditional initialization in JavaScript
- Optimized label event tracking with timestamp-aware logic
- Enhanced error handling and user feedback in label operations
- Implemented binary image storage for fruit-veg products in POS database IMAGE field
- Enhanced troubleshooting documentation with comprehensive tab component slot access analysis
- Added working Alpine.js alternatives for problematic Laravel Blade components
- Improved fruit-veg image serving with proper cache headers and transparent PNG fallbacks
- Enhanced AJAX form submissions for real-time fruit-veg product updates without page refresh

## [0.3.0] - 2024-01-20

### Added
- Delivery verification system with CSV import and barcode scanning
- Real-time mobile-optimized scanning interface
- Discrepancy tracking and reporting for deliveries
- Product creation from unmatched delivery items
- Supplier image integration with hover previews
- Export functionality for delivery discrepancies

### Changed
- Enhanced product image support for new products without existing models
- Improved barcode extraction with multiple pattern support

## [0.2.0] - 2024-01-15

### Added
- Advanced pricing system with VAT-inclusive calculations
- 4-decimal precision storage for accurate VAT preservation
- Live supplier price comparison with Udea
- Quick action buttons for competitive pricing strategies
- Transport cost analysis (15% calculation)
- Customer price extraction from supplier pages

### Changed
- Consolidated pricing interface in product management
- Enhanced supplier integration with live data

## [0.1.0] - 2024-01-10

### Added
- Initial Laravel 12 application setup
- uniCenta POS database integration
- Product catalog with real-time stock levels
- Supplier management and cost tracking
- Admin dashboard with sidebar navigation
- Username/email authentication with Laravel Breeze
- Product search and filtering capabilities
- Supplier external integration for images and links

### Security
- Secure authentication system with email verification
- Role-based access control foundation

## Development Guidelines

When making changes:
1. Update this changelog in the Unreleased section
2. Follow the categories: Added, Changed, Deprecated, Removed, Fixed, Security
3. Reference issue numbers where applicable
4. Move Unreleased items to a new version section when releasing

[Unreleased]: https://github.com/yourusername/osmanagercl/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/yourusername/osmanagercl/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/yourusername/osmanagercl/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/yourusername/osmanagercl/releases/tag/v0.1.0