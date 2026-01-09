# Features Index

This document provides a comprehensive overview of all features in the OSManager CL application, organized by category.

**Quick Navigation:**
- [Product Management](#product-management)
- [Kitchen Management](#kitchen-management)
- [Supplier Management](#supplier-management)
- [Order Management](#order-management)
- [Financial Systems](#financial-systems)
- [Analytics & Reporting](#analytics--reporting)
- [POS Integration](#pos-integration)
- [User Management](#user-management)

---

## Product Management

### Auto-Barcode Suggestion System
Comprehensive, configuration-driven barcode suggestion system for streamlined product creation.
- **Multi-Category Support**: Coffee Fresh, Fruit, Vegetables, Bakery, Zero Waste Food, and Lunches
- **Smart Numbering Logic**: Fill gaps or increment based on category-specific rules
- **Global Uniqueness**: Prevents barcode conflicts across all categories
- **Category-Specific URLs**: Direct links for each category (e.g., `/products/create?category=081`)
- **User-Friendly Interface**: Visual indicators, descriptions, and override capability
- **Configuration-Driven**: Easy to add new categories via `config/barcode_patterns.php`

📖 [Auto-Barcode Suggestion System Documentation](./features/barcode-suggestion-system.md)

### Barcode Editing
Safe barcode modification for correcting scanner errors.
- **Safety Warnings**: Clear indication of affected records before changes
- **Transaction Safety**: All updates wrapped in database transaction
- **Comprehensive Updates**: Automatically updates supplier links, stocking, labels, and metadata
- **Audit Trail**: Tracks changes in label_logs with old/new values
- **Validation**: Ensures new barcode is unique across products

📖 [Product Management Documentation](./features/product-management.md#barcode-editing-feature-2025-08-07)

### Categories Management System
Universal category management interface for all product categories.
- **Universal Interface**: Manage any category with consistent tools
- **Sales Analytics**: Pre-aggregated data for instant performance metrics
- **Till Visibility Control**: Toggle products on/off POS per category
- **Product Management**: Inline editing of prices and display names
- **Subcategory Support**: Navigate category hierarchies
- **Search & Filter**: Find products and categories quickly

📖 [Categories Management Documentation](./features/categories-management.md)

### Product Management
Comprehensive product catalog management with inline editing capabilities and real-time duplicate detection.
- **Real-time Barcode Validation** (NEW! 2025-11-01): Instant duplicate detection when creating products with direct links to edit existing products
- **Supplier Link Duplicate Prevention** (NEW! 2025-11-01): Real-time warning and override system for duplicate supplier codes with full audit trail
- **Inline Editing**: Edit product names, tax categories, prices, and costs directly from product detail pages
- **Stocking Management**: Toggle products in/out of stock management operations with visual indicators
- **Delivery Integration**: Create products directly from delivery items with pre-populated data
- **Smart Navigation**: Context-aware navigation maintaining delivery workflow state
- **Validation & Error Handling**: Robust form validation with user-friendly error messages

📖 [Product Management Documentation](./features/product-management.md)

### Label System with Barcode Scanner
Complete label printing system with integrated barcode scanning for quick product queue management.
- **Scan to Label Modal**: Instant barcode scanning interface with auto-focus and real-time feedback
- **Scanner-Optimized Layout**: Prominent scan button in page header, compact stats, streamlined interface
- **Queue Management**: Live counter showing products in labels queue with session tracking
- **Touch-Free Workflow**: Virtual keyboard suppression and automatic focus management for continuous scanning
- **Filter by Add Method**: Select labels by how they were added (New Products, Price Updates, Scanned/Re-queued) with visual indicators and real-time counts

📖 [Label System Documentation](./features/label-system.md)

### Pricing Management
Advanced pricing with VAT calculations and supplier comparison.
- **Enhanced Price Editor Modal**: Inline cost price editing with real-time margin updates
- **Supplier-Specific UI**: UDEA suppliers get additional pricing cards and quick actions
- **Quick Cost Updates**: Arrow buttons in deliveries for instant cost synchronization

📖 [Pricing System Documentation](./features/pricing-system.md)

### Coffee Module
Comprehensive Coffee Fresh product management with till visibility control.
- **Till Visibility**: Toggle products on/off POS till using PRODUCTS_CAT table
- **Inline Editing**: Click-to-edit pricing and display names
- **Sales Analytics**: Optimized performance with pre-aggregated data
- **Context Navigation**: Smart back button routing from product detail pages

📖 [Coffee Module Documentation](./features/coffee-module.md)

### F&V Price Sync Management System (NEW! 2025-08-28)
Cross-database price synchronization management with web-based interface.
- **Discrepancy Detection**: Identifies price mismatches between POS and Laravel databases
- **Bidirectional Sync**: Choose sync direction (History→POS or POS→History)
- **Bulk Operations**: Select and sync multiple products simultaneously
- **Statistics Dashboard**: Real-time sync status overview with detailed reporting
- **Transaction Safety**: Proper cross-database transaction management
- **Production Interface**: Web-based tool eliminates need for terminal access
- **Audit Preservation**: Maintains complete price change history during sync
- **Access**: Available at `/fruit-veg/price-sync` from F&V dashboard

**Critical Fix**: Resolved cross-database transaction issue where Laravel `DB::transaction()` only applied to default connection, causing POS updates to not commit properly. Now uses separate transaction management for each database connection.

📖 [F&V System Documentation](./features/fruit-veg-system.md#price-sync-management-system)

---

## Kitchen Management

### Kitchen Recipe Costing System (NEW! 2025-12-08)
Comprehensive recipe costing system with ingredient profiles, overhead calculations, margin analysis, and batch scaling tools.
- **Recipe Management**: Create, edit, and manage recipes with ingredients linked to POS products
- **Ingredient Profiles**: Define ingredient costing with purchase units, recipe units, and density conversions
- **Labour Cost Calculation**: Automatic labour cost from `(prep_time + cook_time)` × hourly rate
- **Electricity Cost Calculation**: Automatic electricity cost from `cook_time` × power (kW) × rate (€/kWh)
- **Packaging Cost** (NEW! 2025-12-10): Per-portion packaging costs for containers, lids, labels
- **Per-Recipe Overrides**: Override global labour rate, electricity rate, cooking power, and packaging per recipe
- **Cost Breakdown Display**: Detailed breakdown showing ingredients, labour, electricity, packaging, and total costs
- **Margin Analysis**: Profit margin calculation with color-coded status indicators:
  - Excellent (40%+) - Green
  - Good (20-40%) - Yellow
  - Low (10-20%) - Orange
  - Critical (<10%) - Red
- **Batch Scaling Calculator** (NEW! 2025-12-10): Analyze cost efficiencies when scaling recipes
  - Independent scaling for ingredients, labour, and electricity
  - Smart default factors based on batch size
  - Real-time cost comparison and savings percentage
  - Save scaled version as new recipe
- **Cost History Tracking**: Record cost snapshots over time for trend analysis
- **Delivery Markup Support**: Apply delivery markup percentage to imported products (Udea, Dynamis suppliers)
- **Unit Conversions**: Smart weight↔volume conversions using density factors (e.g., flour density 0.593)
- **Linked Products**: Connect recipes to POS products for automatic sell price and margin calculation

**Configuration** (`config/kitchen.php`):
- `KITCHEN_LABOUR_RATE` - Default labour rate per hour (€15.00)
- `KITCHEN_ELECTRICITY_RATE` - Electricity cost per kWh (€0.25)
- `KITCHEN_AVG_COOKING_POWER` - Average cooking power in kW (2.0)

📖 [Kitchen Recipe Costing Documentation](./features/kitchen-recipe-costing.md)

---

## Supplier Management

### Supplier Integration
External supplier connectivity for images, pricing, and product data.

📖 [Supplier Integration Documentation](./features/supplier-integration.md)

### Delivery Verification
Comprehensive delivery processing with barcode scanning.

📖 [Delivery System Documentation](./features/delivery-system.md)

### Unified Supplier Management System (NEW! 2025-09-11)
Complete supplier management with seamless POS integration and auto-code generation.
- **Auto-Generated Codes**: Automatic supplier codes (SUP-0001, SUP-0002) with manual override option
- **POS Integration**: Optional checkbox to create suppliers in both Laravel and POS databases simultaneously
- **Cross-Database Sync**: Creates entries in both accounting (port 3306) and POS (port 3307) databases
- **Smart Validation**: Default payment terms (30 days) and comprehensive error handling
- **User Feedback**: Loading states, success/error messages, and detailed validation errors
- **Edit Integration**: Link existing suppliers to POS or update POS names when changed
- **Transaction Safety**: All operations wrapped in database transactions for data integrity
- **Role-based Access**: Protected routes with appropriate permissions

📖 [Supplier Management Documentation](./features/supplier-management.md)

---

## Order Management

### Order Generation System
Intelligent order suggestion system with sales history analysis and coverage planning.
- **Sales-Driven Suggestions**: Automatic quantity calculations based on weekly sales averages
- **Coverage Window**: Configure target coverage period (e.g., 2 weeks, 3 weeks)
- **Category Overrides**: Different coverage periods for different product categories (Cheese, Refrigerated, etc.)
- **Safety Stock Factors**: Per-product safety multipliers for high-demand items
- **Current Stock Integration**: Automatically accounts for existing inventory
- **Case Product Support**: Smart rounding for case-based ordering (6-packs, 12-packs, etc.)
- **Priority Classification**: Products flagged as "Review", "Standard", or "Safe" based on analysis
- **Min Stock Override**: User-defined minimum stock levels with absolute unit control

📖 [Order Generation Documentation](./features/order-management/order-generation.md)

### Christmas Comparison Feature (NEW! 2025-12-01)
Seasonal order planning with historical Christmas sales comparison.
- **Dual-Window Analysis**: Compare recent sales (8-week) vs historical Christmas periods
- **Flexible Date Range**: Custom start/end dates (e.g., Dec 10-26) for comparison window
- **Multi-Year Comparison**: Select 1-2 previous years (2024, 2023) for analysis
- **Max Mode Calculation**: Automatically uses higher of regular or Christmas-based suggestions
- **Side-by-Side Display**: Visual comparison panels showing both recommendations
- **Enhanced Timeline Charts**: Extended Chart.js graphs (640x220px) with multiple datasets
  - Blue line: Recent sales trend
  - Purple line: Christmas 2024 historical data
  - Pink line: Christmas 2023 historical data
  - Interactive legend and hover tooltips
- **December Banner**: Auto-suggestion when creating orders with December delivery dates
- **Delta Indicators**: Highlights significant differences (>5 units) between suggestions
- **Opt-In Design**: Feature enabled via toggle, doesn't affect normal ordering workflow
- **Zero-Risk Deployment**: Separate Christmas review pages, original order system untouched

📖 [Christmas Comparison Documentation](./features/order-management/christmas-comparison.md)

### Order Review & Adjustment
Interactive review interface with inline editing and approval workflow.
- **Product-Level Charts**: Chart.js graphs showing sales trends, stock levels, and projections
- **Sales Chart Modal** (NEW! 2025-12-09): Click any chart to open expandable sales history popup
  - Navigate with +/- 1 month and +/- 2 months controls (4 weeks to 2 years)
  - Statistics bar: total sales, peak week, average, and active weeks
  - Available on both regular and Christmas review pages
- **Supplier Website Links** (NEW! 2025-12-09): Direct links to view products on supplier websites
  - "View →" link next to supplier code for Udea and Independent Health Foods products
  - Opens supplier search page in new tab
- **Destock/Restock Toggle** (NEW! 2025-12-09): Quick stock management from order review
  - Red "Destock" button removes product from stock management (won't appear in future orders)
  - Green "Restock" button adds product back to stock management
  - Confirmation dialog before action with clear messaging
- **Inline Quantity Editing**: Adjust suggested quantities with +/- buttons or direct input
- **Priority Filtering**: Filter by Review/Standard/Safe classification
- **Approval Workflow**: Complete orders when ready, mark items for adjustment
- **Export to CSV**: Download order for external processing
- **Multiple Layout Options**: A2, A2 Dense, Grid View for different preferences

---

## Financial Systems

### Invoice Bulk Upload System
Modern multi-file invoice upload system with drag-and-drop interface.
- **Drag-and-Drop Interface**: Upload up to 50 files simultaneously
- **Multi-Format Support**: PDF, JPG, PNG, TIFF, **DOC, DOCX, XLS, XLSX** documents
- **Automatic PDF Repair**: Detects and fixes corrupted PDFs (e.g., Klee Paper invoices) during upload
- **Document Processing**: Microsoft Office formats supported with LibreOffice conversion
- **Real-Time Progress**: Individual file upload progress tracking
- **Batch Management**: Unique batch IDs for tracking uploads
- **File Preview**: Review uploaded files before processing with file-type-specific icons
- **Retry Functionality**: Re-process failed files after parser improvements
- **Configurable Limits**: Customizable file count and size limits
- **Recent History**: View and manage recent upload batches
- **Python Parser Ready**: Foundation for automated data extraction (Phase 2)

📖 [Invoice Bulk Upload Documentation](./features/invoice-bulk-upload-system.md)
📖 [Invoice Parser Integration Guide](./features/invoice-parser-integration.md) (Phase 2)

### Invoice Document Viewing System (NEW! 2025-09-03)
On-the-fly document conversion system for viewing DOC/XLS invoice attachments directly in browser.
- **Universal Document Viewing**: DOC, DOCX, XLS, XLSX files display directly in browser without download
- **On-Demand PDF Conversion**: LibreOffice headless conversion to PDF for browser compatibility
- **Seamless User Experience**: Click document icon to view any supported file type
- **Intelligent Caching**: Converted PDFs cached for improved performance on subsequent views
- **Permission-Safe Operations**: Temporary directory strategy avoids web server permission issues
- **Automatic Cleanup**: Converted files cleaned up when original attachments are deleted
- **Visual Indicators**: File type icons and conversion status messages for user clarity
- **Fallback Support**: Graceful fallback to download if conversion fails

**System Requirements**: LibreOffice must be installed on server (`sudo apt-get install libreoffice`)
**Performance**: First view triggers conversion (2-3 seconds), subsequent views instant

### Invoice Payment Management System
Comprehensive supplier payment management with bulk processing and status synchronization.
- **Enhanced Payment Date Sorting**: Smart toggle between payment status and payment date sorting - click "Status/Paid On" column to sort by payment date (most recent first)
- **Outstanding Report Bulk Payments**: NEW! Mark invoices as paid directly from outstanding report with collapsible supplier sections, alphabetical ordering, and sticky bulk actions bar
- **Comprehensive CSV Export**: Export current view with statistics cards (Total Unpaid, Overdue, etc.) and complete invoice table data, respecting all active filters and sorting
- **Unified Unpaid Filter**: Combined view of pending, overdue, and partial invoices
- **Bulk Payment Processing**: Multi-invoice selection with real-time total calculations
- **Supplier Grouping**: Automatic payment breakdown by supplier in selection interface
- **Payment Status Toggle**: Mark invoices paid/unpaid from detail pages with audit trail
- **OSAccounts Sync**: Automated payment status synchronization from legacy system
- **Transaction Safety**: All bulk operations use database transactions for data integrity
- **Payment Methods Support**: Bank transfer, cash, cheque, credit card options with reference tracking

📖 [Invoice Payment Management Documentation](./features/invoice-payment-management.md)

### Bank Reconciliation System (NEW! 2025-09-08)
Comprehensive bank transaction reconciliation with AI-powered bulk auto-reconciliation and intelligent pattern learning.
- **Bulk Auto-Reconciliation**: AI-powered system that learns payment patterns and processes multiple transactions simultaneously
- **Intelligent Pattern Recognition**: Machine learning system that identifies recurring payments (wages, fees, supplier payments) with confidence scoring
- **Bulk Credit & Debit Categorization**: Mass categorization for both credit transactions (Card/Cash Lodgements, Rent) and debit transactions (Wages, Utilities, Stock, Rent, etc.)
- **Visual Prediction Interface**: Smart prediction badges showing confidence levels and expense categories with intuitive icons
- **Comprehensive Search & Filtering**: Advanced filtering by text, status, date range, amounts, and transaction types
- **Preview & Confirmation**: Preview modal shows exactly what will be processed before bulk operations
- **Learning System**: Automatically improves accuracy from successful matches, building confidence scores over time
- **Non-Supplier Expense Handling**: Support for wages, taxes, bank fees, insurance without creating fake suppliers
- **Multi-Invoice Allocation**: Single transactions can be allocated across multiple invoices with detailed tracking
- **Real-time Synchronization**: Livewire-powered interface with instant checkbox and selection updates
- **Audit Trail**: Complete reconciliation history with user tracking and status changes

📖 [Bank Reconciliation System Documentation](./features/bank-reconciliation-system.md)

### Card Transaction Reconciliation System (NEW! 2026-01-07)
Comprehensive card transaction reconciliation with myPOS integration and intelligent matching.
- **myPOS XLS Import**: Upload card transaction exports for reconciliation against POS records
- **Intelligent Matching**: Confidence-based matching using amount, time, and card type scoring
- **Discrepancy Detection**: Automatically identifies declined, mismatched, and orphan transactions
- **Auto-Match Orphans**: Batch matching with configurable criteria and preview mode
  - Adjustable time window (15 min to 2 hours)
  - Minimum confidence threshold (70-90%)
  - Exact amount only option
  - Card/cash payment filtering
- **Preview Before Matching**: Review all proposed matches with payment method details before confirming
- **Manual Matching**: Find nearby POS payments for unmatched transactions
- **Configurable Settings**: User-defined time windows and auto-match thresholds
- **Batch Management**: Upload history, reprocess, delete, and export batches
- **CSV Export**: Download reconciliation results with full transaction details

📖 [Card Transaction Reconciliation Documentation](./features/card-reconciliation.md)

### VAT Returns Management System
Complete Irish Revenue Online Service (ROS) VAT returns with automated calculations.
- **ROS Compliance**: All required fields (T1, T2, T3, T4, E1, E2) automatically calculated
- **Bi-Monthly Periods**: Supports Irish VAT periods (Jan-Feb, Mar-Apr, May-Jun, etc.)
- **Complete VAT Integration**: Sales VAT from POS + Purchase VAT from invoices
- **EU Trade Tracking**: Automatic INTRASTAT reporting for EU suppliers (Dynamis, Udea)
- **Auto-Selection UX**: All period invoices selected by default with smart controls
- **Comprehensive Exports**: Automatic CSV download with all ROS data and breakdowns
- **Dual Performance**: Uses optimized data (100x+ faster) with real-time fallback
- **Role-based Access**: Admin and Manager only with full audit trail

📖 [VAT Returns Management Documentation](./features/vat-returns.md)

### VAT Dashboard System
Comprehensive VAT return management dashboard with proactive deadline alerts.
- **Outstanding Periods Alert**: Automatic detection of overdue VAT periods with direct links
- **Current Period Tracking**: Real-time display of current period status and progress
- **Next Deadline Tracker**: Visual countdown with color-coded urgency indicators
- **Unsubmitted Invoices Summary**: Monthly breakdown with totals and trends
- **Recent Submissions**: Quick view of latest VAT returns with status tracking
- **Complete History View**: Paginated archive with year and status filtering
- **Role-based Access**: Protected for Admin and Manager roles only

📖 [VAT Dashboard Documentation](./features/vat-dashboard.md)

### OSAccounts Integration System
Complete invoice and supplier data migration from legacy OSAccounts system.
- **Supplier Sync Command**: Automatic mapping of POS IDs to OSAccounts IDs
- **Full Invoice Import**: Import with correct supplier names and relationships
- **VAT Line Migration**: Detailed VAT breakdown with Irish tax rate support
- **VAT Returns Recovery**: Reconstruct historical VAT returns from invoice assignments
- **Attachment Import**: File migration with proper web server permissions
- **Production-Ready Workflow**: Tested and optimized import process
- **Data Integrity**: Transaction-safe imports with validation
- **Cross-Database Support**: Handles EXPENSES_JOINED supplier table

📖 [OSAccounts Integration Documentation](./features/osaccounts-integration.md)

---

## Analytics & Reporting

### Sales Accounting Report System
VAT-compliant sales analysis with proper revenue/transfer separation and comprehensive export capabilities.
- **Accurate Revenue Calculation**: Excludes voucher sales to provide true customer revenue figures
- **Dynamic VAT Columns**: Only displays VAT rate columns with actual data for cleaner interface
- **Stock Transfer Separation**: Internal movements excluded from revenue with collapsible display
- **Gift Voucher Handling**: Paperin/paperin adjust system prevents double-counting
- **Comprehensive CSV Export**: Structured export with date range, VAT breakdown, and summary metrics
- **Dual Performance Mode**: Uses pre-aggregated data (100x+ faster) with real-time fallback
- **Professional Formatting**: Tables match website layout for easy accounting review
- **Role-based Access**: Admin and Manager access only for financial data security

📖 [Sales Accounting Report Documentation](./features/sales-accounting-report.md)

### Bank Statement Analysis System (NEW! 2025-09-09)
Comprehensive POS vs Bank reconciliation system for accurate financial tracking and variance identification.
- **Daily Reconciliation Grid**: Side-by-side comparison of POS sales against bank lodgements
- **Automatic Pattern Detection**: Smart matching for exact amounts, weekend combining, card settlements
- **Manual Matching Interface**: Link specific POS days to bank transactions with audit trail
- **Variance Analysis**: Real-time calculation of discrepancies with significance indicators
- **Performance Caching**: Pre-aggregated POS summaries for instant analysis
- **Export Functionality**: Professional CSV reports for accounting reconciliation

📖 [Bank Statement Analysis Documentation](./features/bank-statement-analysis.md)

### Cash Reconciliation System
Comprehensive end-of-day cash management with physical counting and variance tracking.
- **Physical Cash Counting**: Count by denomination (€50 to 10c) with real-time totals
- **Legacy Data Import**: Seamlessly imports from PHP system (converts totals to counts)
- **Variance Tracking**: Automatic calculation against POS with visual indicators
- **Supplier Payments**: Track cash payments made from till
- **Float Management**: Automatic carry-over between days
- **Multi-Till Support**: Manage all terminals from one interface
- **Export to CSV**: Generate reconciliation reports
- **Audit Trail**: Complete tracking with user timestamps

📖 [Cash Reconciliation Documentation](./features/cash-reconciliation.md)

---

## POS Integration

### Receipts Management System
Complete till review and transaction analysis for POS data with modern interface.
- **Transaction Review**: View all POS transactions including receipts, drawer opens, and voided items
- **Color-coded Interface**: Visual highlighting by payment type (Cash: Green, Card: Purple, Free: Orange, Debt: Yellow)
- **Interactive Filtering**: Clickable summary cards for instant payment type filtering
- **Advanced Search**: Filter by date, time, terminal, cashier, transaction type, and amounts
- **Real-time Analytics**: Dynamic summary calculations with optimized caching layer
- **Export Capabilities**: CSV export functionality with comprehensive transaction data
- **Audit Trail**: Complete audit logging for compliance and security monitoring

📖 [Receipts Management Documentation](../management/receipts.md)

### Coffee KDS (Kitchen Display System)
Real-time coffee order tracking system for baristas with optimized performance.
- **Fast Order Detection**: 2-3 second detection time using direct database polling
- **Real-time Updates**: Server-sent events (SSE) for instant display updates
- **Audio Notifications**: Sound alerts for new coffee orders
- **Order Management**: Simple one-click completion with restore capability
- **Complete All Orders**: Mark all active orders as completed (prevents re-import issues)
- **System Monitoring**: Live connection status and response time display
- **Completed Orders**: Track recently completed orders with quick restore
- **Mobile Optimized**: Responsive design for tablets and phones
- **No Queue Dependencies**: Direct polling eliminates queue worker requirements

📖 [KDS Documentation](./features/kds-coffee-system.md)

---

## User Management

### User Roles & Permissions System
Role-based access control (RBAC) with granular permissions.
- **Three-tier Role System**: Admin, Manager, and Employee roles with hierarchical permissions
- **Granular Permissions**: 30+ specific permissions organized by modules
- **Middleware Protection**: Route-level protection using role and permission middleware
- **Flexible Authorization**: Check permissions in controllers, views, and middleware
- **User Management**: Assign roles, manage permissions, audit access

📖 [User Roles & Permissions Documentation](./features/user-roles-permissions.md)

---

## Performance Optimization

🔥 **For comprehensive performance optimization guidance**, see:
- **[Sales Data Import Plan](./features/sales-data-import-plan.md)** - Proven 100x+ performance improvements pattern
- **[Performance Optimization Guide](./development/performance-optimization-guide.md)** - Complete optimization strategies
- **[Architecture Overview](./architecture/overview.md)** - System design and optimization patterns
