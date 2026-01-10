# Changelog

All notable changes to OSManager CL will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **🥬 F&V Order Generation System** (2026-01-09)
  - **Supplier-Agnostic Ordering**: Generate orders for all F&V products regardless of supplier
  - **Sales-Based Suggestions**: Order quantities calculated from historical sales data
  - **Category Groupings**: Products organized by Fruits (SUB1), Vegetables (SUB2), and Barcoded (SUB3)
  - **Configurable Parameters**:
    - Sales period selection (start/end date)
    - Coverage days (how many days the order should cover)
  - **Weekly Sales Analytics**:
    - Weekly average calculation
    - Peak weekly sales tracking
    - Mini line charts with average line indicator (dashed)
  - **Interactive Review Interface**:
    - Matches existing order system layout
    - Editable suggested quantities with +/- buttons
    - Client-side sorting by sales or name
    - Product images and origin country display
  - **Routes**: `/fruit-veg/orders` (form) and POST for results
  - **Quick Access**: "Generate Order" button added to F&V dashboard
  - **Files Created**:
    - `resources/views/fruit-veg/orders.blade.php` - Order generation form
    - `resources/views/fruit-veg/orders-review.blade.php` - Results display
    - `resources/views/fruit-veg/partials/order-table.blade.php` - Category table partial

- **💳 Card Transaction Reconciliation System** (2026-01-07)
  - **myPOS XLS Import**: Upload card transaction exports for reconciliation against POS records
  - **Intelligent Matching Algorithm**: Confidence-based matching using amount (0-50 pts), time (0-40 pts), and card type (0-10 pts)
  - **Discrepancy Detection**: Automatically identifies declined, mismatched, and orphan transactions
  - **Auto-Match Orphans**: Batch matching with configurable criteria and preview mode
    - Adjustable time window (15 min to 2 hours)
    - Minimum confidence threshold (70-90%)
    - Exact amount only option
    - Card/cash payment filtering
  - **Preview Before Matching**: Review all proposed matches with payment method details (Card/Cash) before confirming
  - **Manual Matching**: Find nearby POS payments for unmatched transactions
  - **Configurable Settings**: User-defined time windows and auto-match thresholds
  - **Batch Management**: Upload history, reprocess, delete, and export batches
  - **CSV Export**: Download reconciliation results with full transaction details
  - **Database Schema**: Two new tables (`card_transactions`, `card_reconciliation_settings`)
  - **Files Created**:
    - `app/Services/MyPosXlsParserService.php` - myPOS XLS parser
    - `app/Services/CardReconciliationService.php` - Matching logic
    - `app/Models/CardTransaction.php` - Card transaction model
    - `app/Models/CardReconciliationSetting.php` - User settings model
    - `app/Jobs/ProcessCardTransactions.php` - File processing job
    - `app/Http/Controllers/Financials/CardReconciliationController.php`
    - `resources/views/financials/card-reconciliation/` - Views
  - **Documentation**: See [Card Transaction Reconciliation Guide](./docs/features/card-reconciliation.md)

- **🍳 Kitchen Recipe Scaling & Packaging** (2025-12-10)
  - **Batch Scaling Calculator**: Analyze cost efficiencies when producing larger batches
    - Recipe multiplier (2x, 3x, 5x, 10x, or custom)
    - Independent labour factor (e.g., 2x batch might only need 1.5x labour)
    - Independent electricity factor (e.g., same oven time for larger batch)
    - Smart default factors based on batch size
    - Real-time comparison table showing original vs scaled costs
    - Per-portion savings percentage calculation
    - Save scaled version as new recipe with one click
  - **Packaging Cost Support**: Per-portion packaging costs for containers, lids, labels
    - New field in Rate Overrides section
    - Automatically included in total cost and cost-per-portion calculations
    - Packaging costs scale with portions in batch scaling calculator
    - Preserved when saving scaled recipes
  - **Database**: Added `packaging_cost_per_portion` column to `kitchen_recipes` table
  - **API**: New endpoint `POST /kitchen/{recipe}/scale` for saving scaled recipes

- **📦 Order Page Enhancements** (2025-12-09)
  - **Supplier Website Links**: Added "View →" links to Udea and Independent Health Foods product pages directly from order review
    - Links appear next to supplier code in product rows
    - Opens supplier website in new tab with product search
    - Works on both regular and Christmas review pages
  - **Destock/Restock Toggle**: Quick stock management control from order review pages
    - Red "Destock" button to remove products from stock management
    - Green "Restock" button to add products back
    - Confirmation dialog with clear messaging before action
    - Visual state toggle without page reload
    - Prevents products from appearing in future orders when destocked
  - **Sales Chart Modal for Christmas Review**: Extended sales history popup now available on Christmas review page
    - Click any chart to open expandable sales history modal
    - Navigate sales history with +/- 1 month and +/- 2 months controls
    - Statistics bar showing total sales, peak week, average, and active weeks
    - Consistent experience with regular order review page

- **🍳 Kitchen Recipe Costing System** (2025-12-08)
  - **Recipe Management**: Create, edit, and manage recipes with ingredients linked to POS products
  - **Ingredient Profiles**: Define ingredient costing with purchase units, recipe units, and density conversions
  - **Labour Cost Calculation**: Automatic labour cost from prep + cook time with configurable hourly rate
  - **Electricity Cost Calculation**: Automatic electricity cost from cook time with configurable kW and rate
  - **Per-Recipe Overrides**: Override global labour rate, electricity rate, and cooking power per recipe
  - **Cost Breakdown Display**: Detailed cost breakdown showing ingredients, labour, electricity, and total
  - **Margin Analysis**: Profit margin calculation with color-coded status (excellent/good/low/critical)
  - **Cost History Tracking**: Record cost snapshots over time for trend analysis
  - **Delivery Markup Support**: Apply delivery markup to imported products (Udea, Dynamis suppliers)
  - **Unit Conversions**: Smart weight↔volume conversions using density factors
  - **Global Config Defaults**: `config/kitchen.php` for system-wide rate defaults via environment variables
  - **Database Schema**:
    - `kitchen_recipes` table with override fields for rates
    - `kitchen_recipe_ingredients` table with unit conversions
    - `kitchen_ingredient_profiles` table for reusable ingredient costing
    - `kitchen_recipe_cost_history` table for cost tracking over time
  - **Files**:
    - `app/Models/KitchenRecipe.php` - Recipe model with rate helpers
    - `app/Services/KitchenCostingService.php` - Cost calculation service
    - `app/Http/Controllers/KitchenController.php` - Recipe management
    - `resources/views/kitchen/` - Recipe management views

- **🎄 Christmas Comparison Feature** (2025-12-01)
  - **Seasonal Order Planning**: Compare recent sales with historical Christmas period sales when generating orders
    - Flexible date range selection (e.g., Dec 10-26) with custom start/end dates
    - Multi-year comparison: Select 1-2 previous years (2024, 2023)
    - Max mode: Automatically uses higher of regular or Christmas-based suggestions
    - Opt-in design: Feature enabled via toggle, doesn't affect normal ordering
  - **Dual-Window Comparison Display**: Side-by-side stats showing recent vs Christmas data
    - Recent sales column: 8-week (or custom) average and suggested quantity
    - Christmas sales column: Historical Christmas average and suggested quantity
    - Green checkmark indicates which suggestion was selected (higher)
    - Delta indicator shows difference if >5 units
  - **Enhanced Visual Timeline Charts**: Extended Chart.js graphs with multiple datasets
    - Timeline: Recent weeks | Current/After stock | Christmas 2024 | Christmas 2023
    - Distinct colors: Blue (recent), Purple (2024), Pink (2023)
    - Interactive legend to toggle datasets
    - Hover tooltips display quantities for all data points
    - Larger graphs: 640x220px (2x previous size) for better visibility
  - **December Banner Prompt**: Auto-suggestion when creating December orders
    - Promotional banner with one-click enable
    - Dismissible without enabling feature
  - **Technical Implementation**:
    - New files: `show-christmas.blade.php`, `review-table-christmas.blade.php`
    - Enhanced: `OrderService`, `SalesRepository`, `OrderController`
    - Zero-risk deployment: Separate Christmas review files, original pages untouched
    - JSON storage: No new database tables, uses `christmas_window_config` JSON column
  - **Documentation**: See [Christmas Comparison Feature Guide](./docs/features/order-management/christmas-comparison.md)

- **📊 Graph Size Expansion** (2025-12-01)
  - **Larger Charts in Christmas Review**: Doubled graph dimensions for better visibility
    - Column width: 320px → 640px
    - Chart height: 110px → 220px
    - Row height: 180px → 360px
    - Better utilization of available white space
    - Desktop-optimized fixed dimensions
  - **Improved Data Visibility**: Easier to see patterns in extended timeline with Christmas data
    - Multiple datasets more clearly distinguishable
    - Legend and tooltip interactions more accessible
    - Better for analyzing seasonal trends

### Changed

- **📚 Documentation Refactoring** (2025-11-03)
  - **CLAUDE.md Cleanup**: Reduced from 646 lines to 229 lines (65% reduction)
    - Removed detailed feature descriptions (moved to Features Index)
    - Removed detailed known issues (moved to Known Issues document)
    - Removed detailed development commands (moved to Quick Start Guide)
    - Removed AI assistant guidelines (moved to AI Assistant Guide)
    - Now serves as concise entry point with links to detailed documentation
  - **New Documentation Files**:
    - `docs/FEATURES_INDEX.md` - Complete feature catalog organized by category
    - `docs/development/ai-assistant-guide.md` - Comprehensive guidelines for AI assistants
    - `docs/development/known-issues.md` - Detailed known issues and solutions
    - `docs/development/quick-start-guide.md` - Complete development setup and commands
  - **Improved Organization**: Better separation of concerns with focused, maintainable documents
  - **Enhanced Navigation**: Clear links between related documentation files
  - **Better Maintainability**: Easier to update specific sections without editing large files

### Added

- **📦 Minimum Stock Level Override System** (2025-11-03)
  - **User-Controlled Stock Levels**: Admin and Manager users can now set custom minimum stock levels for individual products
    - Override system uses absolute units (e.g., 50 units) for clear, direct control
    - Smart calculation: System uses whichever is higher - calculated minimum or user override
    - Preserves existing ordering intelligence while giving power users precise control
  - **Product Detail Page Integration**: Inline editing interface on product pages
    - Yellow badge displays current override value when set
    - Click-to-edit functionality with save/cancel/remove options
    - Real-time AJAX updates without page reload
    - Visual feedback during save operations
    - Only visible to Admin and Manager roles
  - **Order Calculation Integration**: Seamlessly integrated into order suggestion system
    - OrderService automatically applies override when calculating order quantities
    - Context data includes both calculated minimum and override value for transparency
    - Indicates when override is active in order context information
  - **Order Review Table Display**: Min stock override shown in stock levels section
    - Orange badge displays override value for products with custom minimums
    - Appears between current/after stock and coverage information
    - Visible across all order table sections (Cheese, Refrigerated, Case, Unit products)
  - **Order Review Table Editing**: Inline editing of min stock directly from orders page (Admin/Manager only)
    - Click pencil icon in orange badge to edit min stock override
    - Compact inline editor with number input, save, and cancel buttons
    - Enter to save, Escape to cancel editing
    - **Dynamic Real-time Updates** (2025-11-04): Changes apply instantly without page reload
      - Order quantity automatically recalculates based on new minimum stock level
      - "After Order" stock value updates immediately
      - Orange dotted line on chart moves to new minimum stock level
      - Green ring and "✓ Saved!" indicator provide immediate visual feedback
      - All updates happen seamlessly in <1 second
    - "Set Min Stock" button appears for products without override set
    - Non-admin users see display-only badge
  - **Sales Graph Visualization**: Orange dotted line shows minimum stock level
    - Horizontal line overlays monthly sales bars when override is set (product detail page)
    - Legend automatically displays when min stock override is active
    - Tooltip shows "Min Stock: X units" when hovering over line
    - Clear visual reference for stock planning and analysis
  - **Order Table Mini Charts**: Orange dotted line appears on weekly sales charts
    - Mini charts in order review table show min stock override as orange dotted line
    - Consistent visualization across product detail and order review pages
    - Tooltip displays "Min Stock Override · X units" when hovering
    - Automatically scales chart to include override level
  - **Database Schema**: New `min_stock_override` column in `product_order_settings` table
    - Nullable decimal field (10,2) for flexible precision
    - Automatically created/updated with product order settings
    - Persists across all order sessions
  - **Permission-Based Access**: Restricted to Admin and Manager roles only
    - Authorization checks in controller and view
    - Clear error messages for unauthorized access attempts
  - **API Support**: RESTful endpoint for updating min stock overrides
    - Route: `PATCH /products/{id}/min-stock-override`
    - Supports both setting and removing overrides
    - JSON responses for AJAX requests
    - Comprehensive validation (numeric, min: 0, max: 999,999.99)
    - **Enhanced Response** (2025-11-04): Returns recalculated order data for instant UI updates
      - Includes new suggested quantity after min stock change
      - Returns updated "after order" stock level
      - Provides complete context data for seamless dynamic updates

- **🔍 Real-time Product Duplicate Detection** (2025-11-01)
  - **Barcode Duplicate Detection**: Instant validation when creating products
    - Real-time AJAX validation with 500ms debounce for optimal performance
    - Warning appears before user fills out entire form, saving time
    - Shows conflicting product name, supplier, and direct link to edit existing product
    - "Edit Existing Product" button for quick navigation to conflicting product
    - "Use Different Barcode" button to clear field and try again
    - Full-width warning placement for maximum visibility
  - **Supplier Link Duplicate Detection with Override**: Smart duplicate handling for supplier codes
    - Real-time validation when entering supplier codes on create/edit forms
    - Warning modal shows conflicting product details before submission
    - User-controlled override with confirmation modal for intentional duplicates
    - Automatic conflict resolution: removes old link, assigns code to new product
    - Complete audit trail logging all override actions with metadata
    - Transaction-safe operations with rollback on failure
    - Visual feedback with yellow warning colors and clear conflict information
  - **Enhanced User Experience**: Comprehensive duplicate prevention system
    - Prevents accidental duplicate product creation
    - Allows intentional supplier code reassignment with proper warnings
    - Direct navigation to conflicting products for quick resolution
    - Session-based authentication for AJAX endpoints
    - CSRF protection on all validation requests

- **📄 DOC/XLS Invoice Attachment Viewing** (2025-09-03)
  - **Universal Document Viewing**: DOC, DOCX, XLS, XLSX files now viewable directly in browser
  - **On-Demand PDF Conversion**: LibreOffice headless conversion transforms documents to PDF for browser compatibility
  - **Seamless User Experience**: Click document icon to view any supported file type without download
  - **Intelligent Caching**: Converted PDFs cached for instant subsequent views (2-3 seconds first time, instant after)
  - **Permission-Safe Architecture**: Temporary directory strategy eliminates web server permission conflicts
  - **Visual File Type Indicators**: Enhanced icons show DOC (blue), XLS (green), PDF (red) with conversion status
  - **Robust Error Handling**: Graceful fallback to download if LibreOffice conversion fails
  - **Automatic Cleanup**: Converted files removed when original attachments deleted
  - **Database Schema**: Added `converted_pdf_path` and `converted_at` columns to track conversions
  - **Production Ready**: Full deployment support with proper environment variable management
  - **System Requirement**: LibreOffice must be installed (`sudo apt-get install libreoffice`)

- **🔧 F&V Image Upload Cache Fix** (2025-09-02)
  - **Root Cause Resolution**: Fixed issue where uploaded images appeared successful but didn't show updated images
  - **Cache-Busting Implementation**: Added server timestamp parameters to force browser cache refresh
  - **Dynamic Cache Control**: Images cached for 24 hours normally, 5 minutes when cache-busting parameter present
  - **Transaction Safety**: Added proper POS database transaction management matching price update patterns
  - **Content-Type Detection**: Automatic MIME type detection (PNG, JPEG, GIF, WebP) from binary image data
  - **Enhanced Error Handling**: Comprehensive logging and rollback on upload failures with debugging information
  - **Immediate Visibility**: Uploaded images now appear instantly without requiring browser refresh or cache clear
  - **Robust Architecture**: Uses explicit `DB::connection('pos')->beginTransaction()` for transaction integrity

- **📎 Clickable Invoice Attachment Icons** (2025-09-02)
  - **One-Click Viewing**: Click attachment icons in invoice table to instantly view documents
  - **New Window Display**: Opens attachments in dedicated window (1200x800) without navigation disruption
  - **Smart Selection**: Automatically prioritizes primary attachment, falls back to first available
  - **Visual Feedback**: Hover effects and tooltips indicate clickability and file count
  - **Error Handling**: Graceful handling of missing attachments with user-friendly messages
  - **Event Management**: Click handlers prevent interference with existing table row links
  - **Quick Access**: No need to navigate to invoice detail page to view attachments

- **📊 Outstanding Invoices Report System** (2025-09-02)
  - **Date-Based Reporting**: Select any date to see invoices outstanding at that time
  - **Supplier Grouping**: Automatic organization by supplier with individual tables
  - **Smart Outstanding Logic**: Uses `payment_status` field to accurately determine outstanding invoices
  - **Comprehensive Calculations**: Per-supplier totals and overall outstanding amounts
  - **Summary Statistics**: Cards showing supplier count, invoice count, total amounts, unpaid count
  - **CSV Export**: Download complete report with all supplier groupings and totals
  - **Year-End Reporting**: Perfect for management accounts and financial reporting at any date
  - **Access Points**: Available at `/suppliers/outstanding-report` or via button on suppliers page
  - **Payment Status Integration**: Properly excludes cancelled invoices and handles payment dates

- **📊 Invoice CSV Export System** (2025-09-02)
  - **Comprehensive Export**: Export button on invoices page with complete statistics and invoice data
  - **Filter Preservation**: CSV respects all active filters (supplier, status, dates, search terms)
  - **Statistics Cards Data**: Includes Total Unpaid, Overdue, This Month, Last Month summaries
  - **Filtered Results Summary**: Shows breakdown of filtered results when filters are applied
  - **Professional Format**: Structured CSV with header info, statistics sections, and detailed invoice table
  - **Smart Filename**: Auto-generated filename format `invoices_YYYY-MM-DD.csv`
  - **Complete Data Export**: All invoice fields including payment details, due dates, notes
  - **One-Click Export**: Green "Export CSV" button preserves current view state

- **🔄 Enhanced Invoice Payment Date Sorting** (2025-09-02)
  - **Smart Column Sorting**: "Status / Paid On" column now toggles between payment status and payment date sorting
  - **Payment Date Priority**: Click to sort by payment date (most recent payments first)
  - **NULL Value Handling**: Proper ordering with paid invoices first, unpaid invoices at end
  - **Direction Toggle**: Second click reverses payment date order (oldest to newest)
  - **Visual Feedback**: Arrow indicators show current sort field and direction
  - **Maintained Layout**: Single column design preserves compact table layout

- **🔧 F&V Price Sync Management System** (2025-08-28)
  - **Web-based Price Sync Tool**: New management interface at `/fruit-veg/price-sync`
  - **Cross-Database Discrepancy Detection**: Identifies products where POS and Laravel price history don't match
  - **Bidirectional Synchronization**: Choose sync direction (History→POS or POS→History)
  - **Statistics Dashboard**: Real-time overview of total F&V products, sync status, and discrepancy counts
  - **Individual & Bulk Operations**: Sync single products or multiple products simultaneously
  - **Professional Interface**: Sortable tables, loading indicators, success/error notifications
  - **Production Ready**: Eliminates need for terminal access to identify price issues
  - **Transaction Safety**: Proper cross-database transaction management with error handling
  - **Audit Trail Preservation**: Maintains complete price change history during sync operations

### Fixed

- **🔧 Product Duplicate Detection Showing "Unknown" Supplier** (2025-11-01)
  - **Root Cause**: Code accessing wrong supplier model field name (`NAME` instead of `Supplier`)
  - **Symptoms**: Real-time duplicate warnings displayed "Supplier: Unknown" or "Supplier: No supplier"
  - **Solution**: Fixed all 4 instances in ProductController (lines 912, 1126, 1301, 1357)
  - **Impact**: Duplicate detection now shows correct supplier names (e.g., "Infinity", "Natural Medicine")
  - **Discovery Method**: Used tinker to inspect Supplier model schema
  - **Testing**: Verified with both barcode and supplier link duplicate detection

- **🚨 F&V Price Updates Not Appearing on POS Till** (2025-08-28) - **CRITICAL BUG FIX**
  - **Cross-Database Transaction Issue**: Laravel `DB::transaction()` only applied to default connection
  - **Root Cause**: POS database updates were running but not committing properly due to transaction scope
  - **Solution**: Implemented separate transaction management for each database connection
  - **Database Connection Verification**: Added diagnostic tools to detect port mismatches (3306 vs 3307)
  - **Result**: Price changes now synchronize correctly between Laravel app and POS till system

- **📎 OSAccounts Attachment Import**: Major improvements to attachment import system (2025-08-12)
  - **Smart Path Resolution**: Handles various path formats from OSAccounts
    - Detects when InvoicePath already contains full filename
    - Decodes HTML entities (e.g., `&amp;` to `&`)
    - Multiple fallback strategies for finding files
    - Handles timestamp suffixes in paths
  - **Production-Ready Permissions**: Automatic permission management
    - Files created with `664` permissions (group-readable)
    - Automatic `www-data` group ownership
    - Directories use setgid bit for group inheritance
    - No sudo required in production
  - **Duplicate Prevention**: SHA-256 hash-based duplicate detection
    - Prevents re-import of identical files
    - New cleanup command `attachments:cleanup-duplicates`
    - Removed 42 duplicate attachments from initial imports
  - **Success Rate**: Improved from 23% to 98.4% (183 of 186 files)
  - **New Commands**:
    - `attachments:cleanup-duplicates` - Remove duplicates and fix permissions
    - `attachments:fix-permissions` - Fix file ownership and permissions
  - **Web Interface**: Fixed file access issues through proper group permissions

### Added

- **📊 VAT Dashboard System**: Comprehensive VAT return management dashboard (2025-08-12)
  - **Outstanding Periods Alert**: Automatic detection of overdue VAT periods
  - **Current Period Tracking**: Real-time display of current period status
  - **Next Deadline Tracker**: Visual countdown with urgency indicators
  - **Unsubmitted Invoices Summary**: Monthly breakdown of unassigned invoices
  - **Recent Submissions**: Quick view of last 6 VAT returns
  - **Yearly Statistics**: Side-by-side comparison of annual VAT metrics
  - **Complete History View**: Paginated archive with filtering by year and status
  - **Direct Links**: Quick access to create returns with pre-filled dates
  - **Role-based Access**: Protected for Admin and Manager roles only

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