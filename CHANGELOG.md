# Changelog

All notable changes to OSManager CL will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
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
- Template literal and route generation issues in JavaScript sections of Blade templates
- Blade compilation errors due to unescaped Alpine.js event handlers
- HTML entity display issues in fruit-veg product names (display names now render <br> tags properly)
- SQL ordering errors when querying POS database tables without 'updated_at' column
- Tab component slot access compatibility issues with Laravel's slot system (documented with Alpine.js workaround)
- Products removed from till reappearing in "Recently Added" section after page refresh

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