# Release Notes & Updates

This document contains the release history and feature updates for OSManager CL.

---

## Latest Updates (January 2026)

### Card Transaction Reconciliation System (2026-01-07)
Comprehensive card transaction reconciliation with myPOS integration and intelligent matching:

- **myPOS XLS Import**: Upload card transaction exports for reconciliation against POS records
- **Intelligent Matching**: Confidence-based matching using amount (0-50 pts), time (0-40 pts), and card type (0-10 pts)
- **Discrepancy Detection**: Automatically identifies declined, mismatched, and orphan transactions
- **Auto-Match Orphans**: Batch matching with configurable criteria and preview mode
  - Adjustable time window (15 min to 2 hours)
  - Minimum confidence threshold (70-90%)
  - Exact amount only option
  - Card/cash payment filtering
- **Preview Before Matching**: Review all proposed matches with payment method details (Card/Cash)
- **Manual Matching**: Find nearby POS payments for unmatched transactions
- **Configurable Settings**: User-defined time windows and auto-match thresholds
- **Batch Management**: Upload history, reprocess, delete, and export batches

**Key Benefits**:
- Identify declined card transactions that may need follow-up
- Find amount mismatches between card terminal and POS
- Detect orphan transactions missing from either system
- Intelligent matching with preview before confirming
- Full audit trail of all matching decisions

See [Card Transaction Reconciliation Documentation](./features/card-reconciliation.md) for complete details.

---

## Previous Updates (December 2025)

### Kitchen Recipe Costing System (2025-12-08)
Comprehensive recipe costing system with ingredient profiles, overhead calculations, and margin analysis:

- **Recipe Management**: Create, edit, and manage recipes with ingredients linked to POS products
- **Ingredient Profiles**: Define ingredient costing with purchase units, recipe units, and density conversions
- **Labour Cost Calculation**: Automatic labour cost from `(prep_time + cook_time)` × hourly rate (default €15/hr)
- **Electricity Cost Calculation**: Automatic electricity cost from `cook_time` × power (kW) × rate (€/kWh)
- **Per-Recipe Overrides**: Override global labour rate, electricity rate, and cooking power per recipe
- **Cost Breakdown Display**: Detailed breakdown showing ingredients, labour, electricity, and total costs
- **Margin Analysis**: Profit margin calculation with color-coded status (excellent/good/low/critical)
- **Cost History Tracking**: Record cost snapshots over time for trend analysis
- **Delivery Markup Support**: Apply delivery markup percentage to imported products (Udea, Dynamis suppliers)
- **Unit Conversions**: Smart weight↔volume conversions using density factors

**Configuration** (`config/kitchen.php` or `.env`):
```env
KITCHEN_LABOUR_RATE=15.00      # €/hour
KITCHEN_ELECTRICITY_RATE=0.25  # €/kWh
KITCHEN_AVG_COOKING_POWER=2.0  # kW
```

**Key Benefits**:
- Complete recipe costing including all overhead costs
- Per-recipe rate overrides for specialized equipment or labour
- Real-time margin analysis when linked to POS products
- Cost history for tracking price changes over time
- Intelligent unit conversions with density support

See [Kitchen Recipe Costing Documentation](./features/kitchen-recipe-costing.md) for complete details.

---

## Previous Updates (August-September 2025)

### Unified Supplier Management System (2025-09-11)
Complete supplier management with seamless POS integration and intelligent auto-code generation:

- **Auto-Generated Codes**: Sequential supplier codes (SUP-0001, SUP-0002) with manual override capability
- **POS Integration**: Optional checkbox to create suppliers in both Laravel and POS databases simultaneously
- **Cross-Database Sync**: Creates entries in both accounting (port 3306) and POS (port 3307) databases with proper linking
- **Smart Validation**: Automatic payment terms defaulting (30 days) and comprehensive error handling with specific messages
- **Enhanced User Feedback**: Loading states, success/error messages, and detailed validation errors for better UX
- **Edit Integration**: Link existing suppliers to POS or update POS names when changed in Laravel
- **Transaction Safety**: All operations wrapped in database transactions for complete data integrity
- **Visual Indicators**: Color-coded status displays (purple for POS-linked, blue for linkable suppliers)
- **Form Enhancements**: Clear placeholders, auto-focus, disabled states during processing
- **Error Specificity**: Detailed error messages for duplicate codes, database issues, POS connection problems

**Key Benefits**:
- Single entry point for suppliers available across entire system (accounting + POS)
- Eliminates duplicate data entry and ensures consistency between systems
- Automatic code generation prevents human errors and ensures uniqueness
- Flexible integration - choose which suppliers need POS presence based on type
- Real-time synchronization keeps supplier names in sync across databases

See [Supplier Management System](./features/supplier-management.md) for complete implementation details.

### Bank Statement Analysis System (2025-09-09)
Comprehensive POS vs Bank reconciliation system for accurate financial tracking and variance identification:

- **Daily Reconciliation Grid**: Side-by-side comparison of POS sales against bank lodgements with color-coded status indicators
- **Automatic Pattern Detection**: Smart matching for exact amounts, weekend combining, and card settlement timing
- **Manual Matching Interface**: Link specific POS days to bank transactions with full audit trail and confidence scoring
- **Variance Analysis**: Real-time calculation of discrepancies with significance indicators (€50+ or 5%+ variance)
- **Performance Caching**: Pre-aggregated POS daily summaries for instant analysis and responsive interface
- **Export Functionality**: Professional CSV reports with variance analysis for accounting reconciliation
- **Pattern Insights**: Learns lodgement patterns (same-day, next-day, weekend combinations) for better predictions
- **Unmatched Transaction Tracking**: Clear identification of POS days and bank transactions requiring attention

**Key Benefits**:
- Validates data accuracy before feeding main Financial Overview Dashboard
- Identifies missing lodgements and timing discrepancies instantly
- Provides stepping stone for troubleshooting financial data integrity
- Supports multiple matching scenarios (exact, partial, combined, split deposits)
- Tracks lodgement patterns for improved automatic matching over time

See [Bank Statement Analysis System](./features/bank-statement-analysis.md) for complete details.

### Invoice Management Enhancements (2025-09-02)
Enhanced invoice system with advanced sorting, comprehensive data export, and quick attachment viewing:

- **Clickable Invoice Attachment Icons**: Click attachment icons in invoice table to instantly view documents
  - **One-Click Viewing**: Direct access to attachments without navigating to detail pages
  - **New Window Display**: Opens in dedicated window (1200x800) without disrupting current workflow
  - **Smart Selection**: Automatically prioritizes primary attachment, falls back to first available
  - **Visual Feedback**: Hover effects and tooltips indicate clickability and file count
  - **Error Handling**: Graceful handling of missing attachments with user-friendly messages
- **Smart Payment Date Sorting**: Click "Status / Paid On" column to toggle between payment status and payment date sorting
  - **Payment Date Priority**: Most recent payments appear first when sorting by payment date
  - **NULL Value Handling**: Proper ordering with paid invoices grouped by date, unpaid invoices at end
  - **Direction Toggle**: Second click reverses payment date order (oldest to newest)
  - **Visual Feedback**: Arrow indicators show current sort field and direction
- **Comprehensive CSV Export**: One-click export of complete invoice data including statistics
  - **Statistics Integration**: Includes all summary cards (Total Unpaid, Overdue, This/Last Month)
  - **Filter Preservation**: CSV respects all active filters (supplier, status, dates, search terms)
  - **Complete Data Export**: All invoice fields including payment details, due dates, and notes
  - **Professional Format**: Structured CSV with header info, statistics sections, and detailed table
  - **Smart Filename**: Auto-generated as `invoices_YYYY-MM-DD.csv`

**Key Benefits**:
- Instant attachment access directly from invoice listings
- Streamlined document review workflow with new window display
- Chronological payment tracking with intuitive sorting interface
- Complete data export for accounting and financial analysis
- Professional CSV format ready for external systems
- Enhanced productivity through better payment history visibility

See [Invoice Payment Management](./features/invoice-payment-management.md) and [Invoice Attachments System](./features/invoice-attachments-system.md) for complete details.

### Label System Barcode Scanner Enhancement (2025-08-29)
Scanner-optimized label queue management with instant barcode scanning and advanced filtering:

- **Scan to Label Modal**: Instant barcode scanning interface with auto-focus and real-time feedback
- **Scanner-Optimized Layout**: Prominent scan button in page header, compact stats, streamlined interface for continuous scanning workflow
- **Live Queue Counter**: Prominent circular badge showing current items in labels queue with session tracking
- **Touch-Free Workflow**: Virtual keyboard suppression and automatic focus management for hands-free scanning
- **Real-Time Product Lookup**: Instant product details display with name, code, and price verification
- **Seamless Queue Integration**: Automatic addition to existing label printing workflow via LabelLog system
- **Filter by Add Method**: Select labels by how they were added with visual indicators:
  - **New Products** (Green badge) - Products created through product creation
  - **Price Updates** (Blue badge) - Products with price changes (manual or delivery-based)
  - **Scanned/Re-queued** (Purple badge) - Products manually scanned or re-queued
- **Real-Time Counts**: Live product counts for each filter category with persistent filter state

**Key Benefits**:
- Focus on specific types of label requirements for improved workflow efficiency
- Visual indicators clearly show the source of each label requirement
- Persistent filtering maintains user preferences across page reloads
- Enhanced productivity through targeted label management

See [Label System Documentation](./features/label-system.md) for complete details.

### VAT Returns Management System (2025-08-13)
Complete Irish Revenue Online Service (ROS) VAT returns with automated calculations:

- **ROS Compliance**: All required fields (T1, T2, T3, T4, E1, E2) automatically calculated
- **Bi-Monthly Periods**: Supports Irish VAT periods (Jan-Feb, Mar-Apr, May-Jun, etc.)
- **Complete VAT Integration**: Sales VAT from POS + Purchase VAT from invoices
- **EU Trade Tracking**: Automatic INTRASTAT reporting for EU suppliers (Dynamis, Udea)
- **Auto-Selection UX**: All period invoices selected by default with smart controls
- **Comprehensive Exports**: Automatic CSV download with all ROS data and breakdowns
- **Dual Performance**: Uses optimized data (100x+ faster) with real-time fallback
- **Role-Based Access**: Admin and Manager only with full audit trail

**Key Benefits**:
- Complete ROS VAT3 preparation with all required fields
- Accurate VAT calculations including voucher sales VAT
- EU supplier tracking for INTRASTAT compliance
- Professional CSV exports ready for Revenue submission
- Seamless integration with VAT Dashboard for workflow

See [VAT Returns Management](./features/vat-returns.md) for complete details.

### Sales Accounting Report System (2025-08-12)
VAT-compliant sales analysis with comprehensive export and accurate revenue calculations:

- **Accurate Revenue Calculation**: Excludes voucher sales to provide true customer revenue figures
- **Dynamic VAT Columns**: Only displays VAT rate columns with actual data for cleaner interface
- **Stock Transfer Separation**: Internal movements excluded from revenue with collapsible display
- **Gift Voucher Handling**: Paperin/paperin adjust system prevents double-counting
- **Comprehensive CSV Export**: Structured export with date range, VAT breakdown, and summary metrics
- **Dual Performance Mode**: Uses pre-aggregated data (100x+ faster) with real-time fallback
- **Professional Formatting**: Tables match website layout for easy accounting review
- **Role-based Access**: Admin and Manager access only for financial data security

**Key Benefits**:
- True revenue figures excluding internal voucher transactions
- Complete VAT breakdown for tax return preparation
- Professional CSV export with all accounting details needed
- 100x+ performance using optimized data aggregation patterns

See [Sales Accounting Report](./features/sales-accounting-report.md) for complete details.

### VAT Dashboard System (2025-08-12)
Comprehensive VAT return management dashboard with proactive alerts:

- **Outstanding Periods Alert**: Automatic detection of overdue VAT periods
- **Current Period Tracking**: Real-time display of current period status and progress
- **Next Deadline Tracker**: Visual countdown with color-coded urgency indicators
- **Unsubmitted Invoices Summary**: Monthly breakdown with totals and trends
- **Recent Submissions**: Quick view of latest VAT returns with status tracking
- **Yearly Statistics**: Side-by-side comparison of annual VAT metrics
- **Complete History View**: Paginated archive with year and status filtering
- **Direct Links**: Quick access to create returns with pre-filled dates
- **Role-based Access**: Protected for Admin and Manager roles only

**Key Benefits**:
- Never miss a VAT deadline with proactive alerts
- Complete visibility of VAT obligations and history
- Streamlined workflow from dashboard to return creation
- Comprehensive audit trail and export capabilities

See [VAT Dashboard](./features/vat-dashboard.md) for complete details.

### Bank Reconciliation System (2025-09-08)
AI-powered bank transaction reconciliation with bulk auto-processing and intelligent pattern learning:

- **Bulk Auto-Reconciliation**: AI system learns payment patterns and processes multiple transactions simultaneously
  - **Machine Learning**: Automatically recognizes recurring payments (wages, fees, supplier bills) with confidence scoring
  - **Visual Predictions**: Smart prediction badges showing confidence levels and expense categories with intuitive icons
  - **Preview & Confirm**: Preview modal shows exactly what will be processed before bulk operations
- **Advanced Search & Filtering**: Comprehensive transaction filtering and search capabilities
  - **Text Search**: Search descriptions, filenames, notes, amounts across all transactions
  - **Smart Filters**: Status, date range, amount range, and transaction type filtering
  - **Real-time Results**: Instant search with debounced input for optimal performance
- **Intelligent Learning System**: Self-improving accuracy through successful match learning
  - **Pattern Recognition**: Creates fingerprints from transaction descriptions for consistent matching
  - **Confidence Scoring**: Tracks and improves prediction accuracy over time (50-100% confidence)
  - **Automatic Updates**: Each successful match increases rule confidence and match count
- **Seamless UI Integration**: Livewire-powered interface with real-time updates
  - **Multi-Select Checkboxes**: Proper reactive binding for bulk transaction selection
  - **Visual Status Indicators**: Color-coded prediction badges, selected transaction highlighting
  - **Mobile Optimized**: Responsive design works on tablets and mobile devices

**Key Benefits**:
- Process dozens of similar transactions in seconds instead of individually
- Learn from patterns to improve accuracy over time (e.g., "Jessika Roeske SO" → 85% confidence wages)
- Handle non-supplier expenses (wages, taxes, fees) without creating fake suppliers
- Complete audit trail with user tracking and reconciliation history
- Comprehensive search makes finding specific transaction patterns effortless

See [Bank Reconciliation System](./features/bank-reconciliation-system.md) for complete details.

### Cash Reconciliation System (2025-08-11)
Comprehensive end-of-day cash management with legacy data import:

- **Physical Cash Counting**: Count by denomination (€50 to 10c) with real-time totals
- **Legacy Data Import**: Seamlessly imports existing reconciliations from PHP system
- **Variance Tracking**: Automatic calculation against POS totals with visual indicators
- **Supplier Payments**: Track cash payments made from till to suppliers
- **Multi-Till Support**: Manage reconciliations across all terminals
- **Daily Notes**: Add comments and context to each reconciliation
- **Float Management**: Automatic carry-over of previous day's float
- **Export to CSV**: Generate reports for accounting and analysis
- **Role-Based Access**: Manager and Admin only with full audit trail

**Key Benefits**:
- Preserves all historical cash count data from legacy system
- Converts legacy total values to correct denomination counts
- Modern reactive interface with Alpine.js
- Integrates with existing Till Review system

See [Cash Reconciliation](./features/cash-reconciliation.md) for complete details.

---

## Previous Updates (August 2025)

### OSAccounts Integration System (2025-08-10)
Production-ready invoice and supplier data migration from legacy OSAccounts:

- **Supplier Sync Command**: Automatic mapping of POS IDs to OSAccounts IDs
- **Full Invoice Import**: Import with correct supplier names and relationships
- **VAT Line Migration**: Detailed VAT breakdown with Irish tax rate support (0%, 9%, 13.5%, 23%)
- **Attachment Import**: File migration with proper web server permissions
- **Production-Ready Workflow**: Tested and optimized import process
- **Data Integrity**: Transaction-safe imports with comprehensive validation
- **Cross-Database Support**: Handles EXPENSES_JOINED supplier table correctly

**Key Benefits**:
- Complete migration from OSAccounts with zero data loss
- Automatic supplier mapping prevents "Unknown Supplier" issues
- Preserves all invoice history and attachments
- Production-tested workflow with rollback capability

See [OSAccounts Integration](./features/osaccounts-integration.md) for complete details.

### Invoice Bulk Upload System (2025-08-16)
Modern multi-file invoice upload system with drag-and-drop interface:

- **Drag-and-Drop Upload**: Upload up to 50 invoice files simultaneously
- **Multi-Format Support**: PDF, JPG, PNG, TIFF documents accepted
- **Real-Time Progress**: Individual progress tracking for each file
- **Batch Management**: Track upload batches with unique identifiers
- **File Preview**: Review uploaded files before processing
- **Python Parser Ready**: Prepared for Phase 2 automated data extraction
- **Recent History**: View and manage recent upload batches
- **Secure Storage**: Temporary file storage with automatic cleanup

**Key Benefits**:
- Eliminates tedious one-by-one invoice uploads
- Supports bulk processing workflows for month-end
- Configurable limits (files, sizes) per environment
- Foundation for automated invoice data extraction
- Streamlined UX with visual feedback

See [Invoice Bulk Upload System](./features/invoice-bulk-upload-system.md) for implementation details.
See [Invoice Parser Integration](./features/invoice-parser-integration.md) for Phase 2 Python integration guide.

### Invoice Editing System (2025-08-26)
Comprehensive invoice modification system with VAT line management and automatic total recalculation:

- **Full Invoice Editing**: Modify all invoice details including supplier, dates, and categories
- **Dynamic VAT Line Management**: Add, remove, and modify VAT lines with real-time calculations
- **Negative Amount Support**: Handle credit notes and refunds with negative values
- **Automatic Recalculation**: Invoice totals update automatically when VAT lines change
- **Irish VAT Rates**: Support for all Irish VAT categories (23%, 13.5%, 9%, 0%)
- **Real-time Feedback**: JavaScript calculations with server-side validation
- **Data Integrity**: Transaction-safe updates with relationship refresh
- **VAT Breakdown Display**: Per-category VAT analysis in summary sidebar

**Key Benefits**:
- Fix invoice data errors without recreating invoices
- Handle complex VAT scenarios with multiple rates per invoice
- Support credit notes and adjustments with negative amounts
- Maintain audit trail with user attribution for all changes
- Real-time user interface updates for immediate feedback

See [Invoice Editing System](./features/invoice-editing-system.md) for complete implementation details.

---

## Previous Updates (January 2025)

### Product Health Dashboard (2025-01-06)
Auto-loading dashboard with critical product performance insights:

- **Good Sellers Gone Silent**: Identifies high performers with no recent sales
- **Slow Movers**: Products with lowest sales velocity over 60 days
- **Stagnant Stock**: Products with zero sales in last 30 days
- **Inventory Alerts**: High-velocity products needing stock attention
- **Real-time Stock Levels**: Current stock displayed for all dashboard products
- **Direct Product Links**: Click any product name to navigate to edit page
- **Auto-Loading**: Dashboard starts fetching data immediately on page load
- **Visual Indicators**: Color-coded cards by severity (red, orange, yellow, blue)

### Universal Categories Management System
Complete category management system that generalizes the Coffee Fresh module to work with ALL categories:

- **Universal Interface**: Manage any category with the same powerful tools
- **Sales Analytics**: Pre-aggregated data for instant performance metrics across all categories
- **Till Visibility Control**: Toggle products on/off POS per category
- **Inline Product Management**: Edit prices and display names without page refresh
- **Advanced Search & Filter**: Find products and categories quickly
- **Performance Optimized**: Sub-second response times with optimized repository patterns

**Key Benefits**:
- Consistent management interface across all product types
- No need for separate modules per category
- Scalable to unlimited categories
- Maintains backward compatibility with existing modules

See [Categories Management](./features/categories-management.md) for complete details.

### Independent Health Foods Integration
Complete integration for Independent Health Foods with delivery system, product images, and website links:

- **Irish VAT Support**: Automatic calculation and normalization of Irish VAT rates (0%, 9%, 13.5%, 23%)
- **Auto Tax Categories**: Intelligent tax category selection for POS integration
- **Case-to-Unit Conversion**: Smart pricing conversion from case to unit costs
- **Enhanced CSV Processing**: Multi-format support with automatic format detection
- **Product Images**: Automatic CDN image display with smart path detection
- **Website Integration**: Direct product search links to Independent's website
- **Visual UX**: Green indicators, image previews, and click-to-view modals

**Key Benefits**:
- Eliminates manual tax category selection for Irish products
- Ensures accurate unit pricing from case-based supplier data
- Visual product verification with automatic image loading
- Quick access to supplier website for product details
- Reduces data entry errors with intelligent form pre-population
- Streamlines Irish supplier delivery processing workflow

See [Delivery System](./features/delivery-system.md) and [Supplier Integration](./features/supplier-integration.md) for complete details.

---

*For the complete feature list and documentation structure, see the [Documentation Index](./README.md).*
