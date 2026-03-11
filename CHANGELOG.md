# Changelog

All notable changes to OSManager CL will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Natural Medicine Company delivery parser** (2026-03-10)
  - New Python PDF parser for The Natural Medicine Company invoices
  - Extracts all product line items: stock code, description, RRP, quantity, trade price, discount, total, VAT rate
  - Handles multi-line product descriptions that wrap across PDF lines
  - Handles multi-page invoices with repeated headers
  - Validates line totals (qty × trade price) and cross-checks against invoice SUB-TOTAL
  - Supports all Irish VAT rates (0%, 13.5%, 23%)
  - Auto-detected from PDF text ("The Natural Medicine Company" / "naturalmedicine.ie")
  - **New File**: `scripts/invoice-parser/parsers/delivery_natural_medicine.py`
  - **Modified**: `scripts/invoice-parser/delivery_parser_laravel.py` (supplier detection + dispatch)

- **Barcode scanner test page with live camera scanning** (2026-03-10)
  - Live camera barcode scanner at `/labels/barcode-scan-test` using `html5-qrcode` library
  - Supports EAN-13, EAN-8, UPC-A, UPC-E, Code-128 barcode formats
  - Real-time camera feed scans barcodes continuously (requires HTTPS)
  - Auto-lookup against POS product database showing name, code, and price
  - Manual barcode input fallback for typed/pasted codes
  - Scan history list tracks all lookups in current session
  - Audio beep feedback on successful barcode detection
  - **New Package**: `html5-qrcode` for browser-based barcode scanning
  - **New Files**: `resources/js/barcode-scanner.js`, `resources/views/labels/barcode-scan-test.blade.php`
  - **HTTPS Setup**: Self-signed certificate configuration for Apache to enable camera API on mobile

- **AI-powered label translation system** (2026-03-09)
  - Snap photos of foreign-language product labels using phone camera (Chrome on Android)
  - Google Gemini 2.5 Flash translates label text to English and generates ZPL II printer code
  - 14 EU allergens highlighted in CAPS for HSE compliance
  - Direct printing to networked Zebra GX430t thermal printer via CUPS/IPP
  - Label size: 50mm x 76mm at 300dpi with product name, ingredients, nutrition, and storage info
  - Uploaded images auto-resized to 1200px before API call to reduce latency
  - Gallery of previously uploaded label images on capture page
  - Printer debug panel with connectivity and configuration tests
  - **New Package**: `google-gemini-php/laravel` for Gemini API integration
  - **Files Modified**: `LabelAreaController.php`, `routes/web.php`, `config/services.php`, `config/gemini.php`
  - **New Views**: `labels/camera-test.blade.php`, `labels/review.blade.php`

- **Category filter for delivery legacy match page** (2026-03-07)
  - "Categories" checkbox next to "Show Details" displays product category names under each item
  - When enabled, a dropdown appears with all unique categories from the delivery
  - Multi-select checkboxes to filter items by category with "All" / "None" quick buttons
  - Filtering applies across all sections (critical, warnings, verified, OOS, pending, extra items)
  - Items with no category are always shown
  - **Files Modified**: `app/Http/Controllers/DeliveryLegacyController.php`, `resources/views/delivery-legacy/match.blade.php`

- **Client-side image compression for invoice bulk upload** (2026-03-06)
  - Images (JPG, PNG) are now automatically compressed in the browser before upload using Canvas API
  - Scales images down to max 2000px on longest side, JPEG quality 0.7
  - Solves issue where large phone photos (3MB+) exceeded PHP's 2MB `upload_max_filesize` limit
  - Shows compression progress ("Compressing...") and result ("3.3MB → 250KB") in the file list
  - Upload button disabled while compression is in progress
  - Non-image files (PDF, DOC, XLS, etc.) pass through unchanged
  - No new dependencies — uses native browser Canvas API
  - **File Modified**: `resources/views/invoices/bulk-upload.blade.php`

### Fixed

- **Ardú Bakery invoice parser date extraction** (2026-03-06)
  - Updated date regex to handle `20 Feb 2026` text-month format (was only matching `dd/mm/yy`)
  - Old `dd/mm/yy` format kept as fallback for backwards compatibility
  - **File Modified**: `scripts/invoice-parser/parsers/ardu.py`

- **Bulk upload preview: invoice date display and edit form pre-population** (2026-03-06)
  - Added parsed invoice date to the summary line alongside total and supplier
  - Fixed date input in edit form: Carbon date now formatted as `Y-m-d` for HTML date picker (was outputting datetime string the browser ignored)
  - Fixed supplier dropdown pre-selection: added JS initializer to ensure dropdown reflects parsed supplier reliably
  - **File Modified**: `resources/views/invoices/bulk-upload-preview.blade.php`

### Added

- **Manual Resolution of Unparsed Delivery Lines** (2026-02-28)
  - When PDF parser can't parse a line, it's now persisted to the delivery (survives page refresh)
  - Interactive inline form on delivery show page to manually create delivery items from unparsed lines
  - Auto-lookup by supplier code: pre-fills description, unit cost, VAT rate, units/case, and barcode from product database
  - Fallback lookup chain: exact supplier match → any supplier → past delivery items
  - Barcode-based product matching prevents false "New Product" flags
  - Manually added items tracked in parsing discrepancy section with updated totals and remaining difference
  - "Dismiss" option to remove unparsed lines without creating items
  - **Files Created**: `add_unparsed_lines_to_deliveries_table` migration, `add_manually_added_total_to_deliveries_table` migration
  - **Files Modified**: `DeliveryController.php`, `DeliveryService.php`, `Delivery.php`, `deliveries/show.blade.php`, `web.php`, `api.php`

### Fixed

- **"Undefined array key filename" on single-file delivery upload** (2026-02-28)
  - Single-file PDF uploads now include `filename` key in unmatched lines session data, matching multi-file behaviour
  - **File Modified**: `DeliveryController.php`

- **Wages Management & P&L Integration** (2026-02-28)
  - New `/management/wages` page to import payroll "Gross to Net Total By Week Number" XLS/XLSX exports
  - Auto-detects column layout across different file format years (2025 vs 2026 column shifts)
  - Upserts weekly entries (year + week number) so re-importing updates existing data
  - Year filter tabs, totals row, individual and bulk year delete
  - P&L page automatically includes wages (Gross Pay + Employer PRSI) in cost breakdown and profit calculation
  - Previous period comparison now includes wages in cost totals
  - **Files Created**: `WageController.php`, `WageImportService.php`, `WageEntry.php`, `wages/index.blade.php`, `create_wage_entries_table` migration
  - **Files Modified**: `ProfitLossController.php`, `profit-loss/index.blade.php`, `web.php`, `admin.blade.php`

- **Delivery Legacy: Merge Sessions & Change Supplier** (2026-02-24)
  - Merge two pending scan sessions: overlapping barcodes have quantities summed, unique items are moved
  - Source session is deleted after merge; target session's supplier is preserved
  - Change supplier on any pending session via inline edit icon with dropdown
  - Checkboxes on pending session rows with "Merge Selected Sessions" button (appears when exactly 2 selected)
  - Modal dialog to choose which session to keep, with different-supplier warning
  - Completed sessions cannot be merged or have supplier changed
  - **Files Modified**: `DeliveryLegacyController.php`, `delivery-legacy/index.blade.php`, `web.php`

- **RTD: Unfreeze Mode for correcting frozen invoices** (2026-02-23)
  - New "Unfreeze Mode" toggle in the RTD settings cog panel
  - Per-row "Unfreeze" button on frozen invoices resets them to "needs_computation" for recomputation
  - "Unfreeze All Visible" bulk button unfreezes all frozen invoices on the current page
  - Safety: blocks unfreezing invoices in submitted RTD submissions
  - **Files Modified**: `RtdController.php`, `rtd/index.blade.php`, `web.php`

- **VAT on Purchases report page** (2026-02-23)
  - New report under Revenue sidebar showing purchase invoice VAT broken down by rate (0%, 9%, 13.5%, 23%)
  - Splits invoices into Retail (T1), Non-Retail (T2), and Unclassified based on supplier RTD classification
  - Summary cards, VAT rate breakdown table, and collapsible invoice detail list
  - Date range selector defaulting to current month
  - **Files Created**: `VatPurchasesController.php`, `management/vat-purchases/index.blade.php`

- **Suppliers: Inline RTD Classification dropdown on index page** (2026-02-22)
  - New color-coded dropdown in the suppliers table for quickly assigning RTD classification (Simple/Parser/Service/N/A)
  - AJAX-powered inline editing matching the existing VAT Treatment pattern
  - **Files Modified**: `index.blade.php`, `AccountingSuppliersController.php`, `web.php`

### Changed

- **Suppliers: Removed redundant "Default Purchase Use" field** (2026-02-22)
  - The `default_purchase_use` field (resale/overhead/mixed) was never used in any business logic — RTD Classification fully supersedes it
  - Removed from supplier create, edit, and index pages, controller validation, and model
  - Dropped the column and its index via migration
  - **Files Modified**: `AccountingSupplier.php`, `AccountingSuppliersController.php`, `edit.blade.php`, `create.blade.php`, `index.blade.php`

### Fixed

- **Delivery Parser: "Nett" skip term matching "Nettle" product names** (2026-02-19)
  - IIH parser skipped all products containing "Nettle" (e.g., Heath and Heather Nettle, Urtekram Nettle Shampoo) because the skip term `"Nett"` matched as a substring of `"Nettle"`
  - Caused €23.91 discrepancy on Invoice(54).pdf (2 items × €18.05 + €5.86 silently dropped)
  - Fix: replaced plain substring match with regex word-boundary `\bNett\b` so footer "Nett" lines are still skipped but "Nettle" product names are not
  - Added post-parse cross-validation: compares item codes found in raw PDF text against parsed output, warns on any missing codes
  - **File Modified**: `scripts/invoice-parser/parsers/delivery_independent.py`

- **Delivery Discrepancy: Per-document feedback for multi-PDF deliveries** (2026-02-19)
  - When multiple PDFs are uploaded, the discrepancy warning now shows which specific document has the mismatch
  - Stores per-file parsing metadata (stated vs calculated totals, match status) in `DeliveryDocument.parsing_metadata`
  - Single-document deliveries show the document filename; multi-document deliveries show a per-document breakdown with checkmark/X indicators
  - **Files Modified**: `app/Http/Controllers/DeliveryController.php`, `resources/views/deliveries/show.blade.php`

- **VAT Return: Paperin adjustment not applied to 0% rate row in Sales VAT Breakdown** (2026-02-19)
  - The 0.0% row showed unadjusted net/gross figures (e.g., €138,399.44 instead of €136,322.34) while the TOTAL row was correct
  - Root cause: SQLite returns `decimal(8,4)` values as strings like `"0.0000"`, but `keyBy` checked for key `"0"` — the key mismatch silently skipped the paperin deduction for the 0% row
  - Fix: normalize `keyBy` with `(string) (float)` cast to ensure consistent keys; also broadened `show()` recalculation to detect and fix already-stored returns with stale by_rate data
  - **File Modified**: `app/Http/Controllers/Management/VatReturnController.php`

- **RTD Submission: Paperin deduction missing from Tier 1 sales figures** (2026-02-17)
  - `VatReturn.sales_vat_data['by_rate']` stores 0% net **before** paperin deduction — the `paperin_adjustment` key records the amount but `by_rate` is never adjusted
  - Tier 1 (persisted VAT data) was trusting `by_rate` figures blindly, inflating 0% by the paperin gross (e.g., €2,077.10)
  - Now deducts paperin from 0% for all Tier 1 returns: uses stored `paperin_adjustment` value when present, queries `sales_accounting_daily` for older returns without it
  - Debug page also updated to show paperin source (stored vs queried) for each Tier 1 VAT return
  - **Files Modified**: `app/Models/RtdSubmission.php`, `app/Http/Controllers/RtdSubmissionController.php`

- **RTD Submission: Paperin adjustment missing from Tier 2/3 sales figures** (2026-02-16)
  - Paperin (gift voucher redemption) gross amount was not being deducted from 0% sales in Tier 2 (sales_accounting_daily fallback) and Tier 3 (no VAT returns) code paths
  - Caused D1 (0% Home) to be overstated by the paperin gross total (e.g., €921.61)
  - Now correctly deducts paperin gross from 0% net in all code paths, matching `VatReturnController::getSalesVatData()` logic
  - **File Modified**: `app/Models/RtdSubmission.php`

### Added

- **RTD Section 1 (Sales) Populated from VAT3 Returns** (2026-02-13)
  - Section 1 "Goods and/or Services" now shows actual net sales by VAT rate, aggregated from VAT3 returns for the submission period
  - Maps VAT rates to ROS boxes: D1 (0% Home), BC5 (9%), AC5 (13.5%), P1 (Std Rate 23%), Z1 (Total)
  - Primary source: `VatReturn.sales_vat_data` snapshots; fallback to `sales_accounting_daily` for historical returns
  - Sales data persisted in `totals_snapshot` for audit consistency
  - CSV export includes real sales figures
  - **Files Modified**: `RtdSubmission` model, `RtdSubmissionController`, `report.blade.php`

### Changed

- **RTD Submission Report: Aligned to ROS Layout** (2026-02-13)
  - Restructured report to mirror Revenue Online Service (ROS) RTD form exactly — same section order, headings, box codes, and VAT rate sequence
  - 4 ROS sections: Section 1 (Sales), Section 2 (Acquisitions from EU/Non-EU), Section 3 (Goods for Resale), Section 4 (Other Deductible)
  - Added placeholder rows for untracked VAT rates (Exempt, 4.8%, FlatFarm) shown as 0.00
  - Combined EU and Non-EU acquisitions into single Section 2 with correct ROS box codes (E4, D2, C6, BC6, AC6, B6, P2, Z2)
  - Fixed T2 total box code from Z4 to Z5 per ROS specification
  - Added Postponed Accounting rows (PA2 in Section 2, PA4 in Section 4)
  - CSV export updated to match new structure
  - **Files Modified**: `report.blade.php`, `RtdSubmissionController`

### Added

- **RTD Submission: Import Newly Frozen Invoices** (2026-02-13)
  - Draft submissions can now import invoices frozen after the submission was created
  - Collapsible panel on submission detail page shows count of available frozen invoices
  - Table with checkboxes, Select All, and "Add Selected" button for batch import
  - AJAX-powered import with automatic totals recalculation
  - **Files Modified**: `RtdSubmissionController` (new `addInvoices()` method, updated `show()`), `routes/web.php`, `submissions/show.blade.php`

- **RTD Submission Tracking** (2026-02-10)
  - New submission management page to track which invoices were filed with Revenue as part of RTD submissions
  - Create submissions with flexible date ranges, selecting from frozen invoices not yet in any submission
  - VAT breakdown snapshot (T1 goods by rate, T2 service by rate, excluded totals) saved at submission time
  - Mark submissions as filed with Revenue date and reference number
  - Remove invoices from draft submissions; next submission automatically shows previously missed invoices
  - **Files Created**: `RtdSubmission` model, `RtdSubmissionController`, 3 Blade views, 2 migrations

### Fixed

- **Sales Accounting Import - POS Query Performance** (2026-02-16)
  - **Bug**: Importing accounting data took ~36 seconds per day (~72s for 2 days) due to full table scans
  - **Root Cause**: `DATE_FORMAT(DATENEW, '%Y %m %d') = ?` wraps the indexed column in a function, preventing MySQL from using the index on `DATENEW`
  - **Fix**: Replaced with range comparisons `DATENEW >= ? AND DATENEW < ?` using day start/end timestamps in both `importMainSalesData()` and `importStockTransferData()`
  - **Expected Result**: Import time drops from ~36s to <2s per day
  - **File Modified**: `app/Services/SalesAccountingImportService.php`

- **Udea Parser - Credit Note / Negative Total Support** (2026-02-12)
  - **Bug**: Udea credit notes (e.g., returned crates) have negative totals like `Total including vat EUR -3873,20`, but the parser regex `[\d.,]+` didn't match the negative sign, causing total to be `None` and all amounts to be 0
  - **Fix**: Changed total regex to `-?[\d.,]+` to allow negative amounts; detect credit notes from negative total and set `is_credit_note: true`
  - **File Modified**: `scripts/invoice-parser/parsers/udea.py`

- **Invoice Deletion - VAT Return Protection** (2026-02-12)
  - **Bug**: Deleting an invoice assigned to a finalized/submitted VAT return silently succeeded, leaving VAT return totals stale and incorrect
  - **Fix**: Added three-tier protection:
    - **Block**: Invoices with non-zero VAT on finalized/submitted/paid returns cannot be deleted (button disabled with explanation)
    - **Warn**: Invoices with zero VAT on finalized returns show confirmation warning, user can override
    - **Draft**: Invoices on draft returns are unlinked and totals recalculated automatically
  - **Files Modified**: `app/Http/Controllers/InvoiceController.php` (`destroy()` method), `resources/views/invoices/show.blade.php` (delete button)

- **IIH Parser - Non-Standard VAT Rate Handling** (2026-02-10)
  - **Bug**: IIH invoice #9588 showed 164.74 reconciliation difference — 164.75 in taxable goods missing from RTD
  - **Root Cause**: IIH VAT summary regex only matched exact rates (`0.00|9.00|13.50|23.00`); invoice used rate `22.50` which was silently skipped
  - **Fix**: Changed regex to accept any numeric rate with 2 decimal places, anchored to line start; bucket mapping uses wider tolerance (rates >=20% → 23% bucket)
  - **File Modified**: `scripts/invoice-parser/parsers/invoice_iih_rtd.py` (`_extract_vat_summary()` method)

- **VAT Returns - Sales VAT Breakdown Missing Rates** (2026-02-10)
  - **Bug**: Sales VAT Breakdown on VAT return create page only showed one rate (0.0%) instead of all 4 rates (0%, 9%, 13.5%, 23%)
  - **Root Cause**: PHP truncates float array keys to integers — `keyBy('vat_rate')` caused rates 0, 0.09, 0.135, 0.23 to all collapse to integer key `0`, with each overwriting the last
  - **Impact**: Only the 23% rate data survived but displayed as "0.0%"; totals were correct but per-rate breakdown was wrong
  - **Fix**: Cast vat_rate to string before using as key: `keyBy(fn ($item) => (string) $item->vat_rate)`
  - **File Modified**: `app/Http/Controllers/Management/VatReturnController.php` (`getSalesVatData()` method, both optimized and real-time paths)

- **Invoice Total Parsing - Number Format Detection** (2026-02-05)
  - **Bug**: Invoice stated total was parsing `1,978.38` as `1.98` instead of `1978.38`
  - **Root Cause**: `_clean_number_string()` in Independent parser assumed European format when both comma and period present
  - **Fix**: Now detects format by checking which separator comes last (period last = UK/US, comma last = European)
  - **File Modified**: `scripts/invoice-parser/parsers/delivery_independent.py` (lines 158-184)

### Added

- **✅ RTD Non-Retail Classification for Fallback Entries** (2026-02-10)
  - **Non-Retail Flag**: New `is_non_retail` boolean on RTD VAT fallback entries (default: false)
  - **Correct VAT Routing**: Non-retail items (cleaning supplies, office equipment) go to `excluded.service_overhead` instead of `goods_for_resale`, preventing T1 inflation
  - **Minimal UX Impact**: Checkbox in bulk assign bar (unchecked by default — zero extra clicks for the common case)
  - **Fallback Index**: Non-retail entries show yellow "Non-retail" badge; edit modal includes checkbox
  - **RTD Display**: Excluded section shows "Non-retail" line (yellow) for parser-based suppliers, "Service/Overhead" for service suppliers
  - **JS Detail Row**: Recompute AJAX response now correctly includes `service_overhead` in excluded total and renders Non-retail line
  - **Files Created**:
    - `database/migrations/2026_02_10_120000_add_is_non_retail_to_rtd_vat_fallbacks_table.php`
  - **Files Modified**:
    - `app/Models/RtdVatFallback.php` - Added `findFallback()` returning `[vat_rate, is_non_retail]`, `findVatRate()` kept as wrapper
    - `app/Services/RtdResolutionService.php` - Non-retail routing to `excluded.service_overhead`, integrity check includes non-retail total
    - `app/Http/Controllers/RtdFallbackController.php` - `is_non_retail` validation in `bulkAssign()` and `update()`
    - `resources/views/rtd-fallbacks/unresolved.blade.php` - Non-retail checkbox, yellow badge for non-retail assigned items
    - `resources/views/rtd-fallbacks/index.blade.php` - Non-retail badge, edit modal checkbox
    - `resources/views/rtd/index.blade.php` - JS `excludedTotal` includes `service_overhead`, Non-retail line in Excluded section

- **✅ RTD Force Reparse Mode** (2026-02-04)
  - **Force Reparse Toggle**: Settings dropdown with toggle to enable force reparse mode
  - **Reparse Any Invoice**: When enabled, shows "Reparse" button on all invoices with PDFs
  - **Bypass Checks**: Force reparse bypasses `canReparseForRtd()` validation
  - **Fresh Parse**: Deletes existing parsed data and re-parses with latest RTD parser
  - **Visual Indicator**: Orange banner shows when force reparse mode is active
  - **State Persistence**: Mode setting saved to localStorage across sessions
  - **Files Modified**:
    - `app/Http/Controllers/RtdController.php` - Added `forceParse()` method
    - `routes/web.php` - Added force-parse route
    - `resources/views/rtd/index.blade.php` - Added settings dropdown, toggle, reparse buttons

- **✅ Independent Irish Health Foods (IIH) RTD Support** (2026-02-04)
  - **New IIH Parser**: `invoice_iih_rtd.py` extracts VAT summary and DRS totals from IIH invoices
  - **DRS Exclusion**: Deposit Return Scheme amounts automatically excluded from 0% goods for resale
  - **VAT Summary Approach**: Uses invoice's built-in VAT categorization (0%, 13.5%, 23%) instead of article code resolution
  - **Three-Supplier RTD**: Dashboard now supports Udea, Dynamis, and Independent suppliers
  - **DRS in Excluded Section**: DRS amounts shown alongside Freight and Deposits in reconciliation
  - **Files Created**:
    - `scripts/invoice-parser/parsers/invoice_iih_rtd.py` - IIH RTD parser with VAT summary extraction
  - **Files Modified**:
    - `app/Models/Invoice.php` - Added `isIndependentSupplier()`, updated `hasRtdParser()`, `canReparseForRtd()`
    - `app/Services/RtdResolutionService.php` - Added `computeRtdFromVatSummary()` for IIH, DRS support
    - `app/Http/Controllers/RtdController.php` - Added Independent supplier detection in all queries
    - `resources/views/rtd/index.blade.php` - Added DRS row in excluded section
    - `resources/views/rtd/year-report.blade.php` - Added DRS to excluded totals display

- **✅ RTD Detail Row Reconciliation Layout** (2026-02-04)
  - **4-Column Layout**: Reorganized expandable detail row into Goods for Resale, Excluded, Unresolved, and Reconciliation columns
  - **Reconciliation Summary**: New column showing how totals add up to match invoice total
    - Shows `+ Goods`, `+ Excluded`, `+ Unresolved` = `Calculated Total`
    - Compares against Invoice Total with balance indicator
  - **Visual Balance Indicator**: Green border when balanced (difference < €0.50), yellow border when discrepancy exists
  - **Improved Styling**: Each column in a card with colored headers and icons
  - **Files Modified**:
    - `app/Http/Controllers/RtdController.php` - Added `invoice_total` to JSON response
    - `resources/views/rtd/index.blade.php` - New 4-column layout with reconciliation, updated JS

- **✅ RTD Year Report Color Improvements** (2026-02-04)
  - **Improved Readability**: "Not Frozen" warning section now uses orange theme with better contrast
  - **Better Text Colors**: Changed from hard-to-read yellow-200 to gray-200/gray-300
  - **Status Badges**: Proper pill styling with background colors for Computed/Pending status
  - **Table Styling**: Added row separators and darker background for table area
  - **Files Modified**:
    - `resources/views/rtd/year-report.blade.php` - Updated color scheme for warning section

- **✅ RTD Page UX Improvements** (2026-02-04)
  - **AJAX Actions**: Parse, Compute, Freeze actions now use AJAX instead of page redirects
  - **Scroll Preservation**: Page scroll position maintained during all RTD operations
  - **Loading Indicators**: Per-row loading spinners show processing status with action text
  - **In-Place Updates**: Row status, issues count, and action buttons update without page reload
  - **Detail Row Updates**: Expandable detail section updates automatically after actions
  - **PDF View Button**: New "PDF" button opens invoice attachment in popup viewer
  - **Flash Messages**: Success/error messages appear inline and auto-dismiss after 5 seconds
  - **Files Modified**:
    - `app/Http/Controllers/RtdController.php` - Added JSON responses via `rtdResponse()` helper
    - `resources/views/rtd/index.blade.php` - AJAX buttons, loading states, JS update functions

- **✅ Invoice Missing File Detection & Upload** (2026-02-04)
  - **Missing File Indicator**: Red badge on `/invoices` list showing count of missing attachments
  - **Missing Files Modal**: Popup showing missing filenames with upload inputs per file
  - **Copy Filename Button**: Quick copy-to-clipboard with visual feedback (checkmark confirmation)
  - **Clipboard Fallback**: Works on non-HTTPS environments using execCommand fallback
  - **File Upload Validation**: Validates replacement files against invoice data using parser
    - Compares total_amount, invoice_number, supplier_name, invoice_date
    - Shows validation mismatches with option to confirm or cancel
  - **AJAX Upload**: Files upload without page reload, modal updates on success
  - **Files Modified**:
    - `app/Models/Invoice.php` - Added `hasMissingAttachments()`, `getMissingAttachmentCountAttribute()`
    - `app/Http/Controllers/InvoiceController.php` - Eager load attachments
    - `app/Http/Controllers/InvoiceAttachmentController.php` - Added `getMissing()`, `replace()` methods
    - `resources/views/invoices/index.blade.php` - Missing badge, modal, JS functions
    - `routes/web.php` - Added `missing` and `replace` routes

- **✅ RTD Missing PDF Detection** (2026-02-04)
  - **New Status**: Added `pdf_missing` status for invoices with orphaned attachment records
  - **Visual Indicator**: Gray "PDF Missing" badge on RTD dashboard for affected invoices
  - **Smart Detection**: System now checks actual file existence on disk, not just database records
  - **Stat Card**: Conditional stat card appears when missing PDFs are detected
  - **Files Modified**:
    - `app/Models/Invoice.php` - Added `hasPdfOnDisk()` method, updated `canReparseForRtd()`
    - `app/Http/Controllers/RtdController.php` - Added `pdf_missing` status detection
    - `resources/views/rtd/index.blade.php` - Added badge, action text, and stat card

- **✅ Dynamis RTD Invoice Parser** (2026-02-04)
  - **New Parser**: `invoice_dynamis_rtd.py` for line-item extraction from Dynamis invoices
  - **Two Invoice Types**: Automatically detects F&V (RUNGIS) vs Grocery (MAG) from `Ent:` field
  - **EAN Barcode Resolution**: Grocery invoices use 13-digit EAN codes for direct product lookup
  - **Generated Article Codes**: F&V invoices generate `DYN-PRODUCT-COUNTRY` codes for fallback resolution
  - **Multi-Supplier RTD**: Dashboard now supports both Udea and Dynamis suppliers
  - **Files Created**:
    - `scripts/invoice-parser/parsers/invoice_dynamis_rtd.py` - Dynamis RTD parser
  - **Files Modified**:
    - `app/Http/Controllers/RtdController.php` - Added Dynamis supplier detection and routing
    - `app/Services/RtdResolutionService.php` - Added EAN barcode lookup, Dynamis file support
    - `app/Models/Invoice.php` - Added `isDynamisSupplier()`, `hasRtdParser()` methods

- **✅ Delivery Parsing Totals Verification** (2026-01-29)
  - **Invoice Total Extraction**: Python parsers now extract stated totals from PDF footers
    - UDEA: Extracts "Total to deliver", "Total barrels delivered", "Total including/excluding vat"
    - Independent: Extracts "Gross Total", "Subtotal", "Nett" totals
  - **Totals Comparison**: Compares calculated sum of parsed items against PDF-stated totals
    - €0.50 tolerance for rounding differences
    - Flags mismatches with detailed warnings
  - **Preview UI Enhancement**: New "Totals Verification" section in delivery upload preview
    - Shows PDF-stated vs parsed values in comparison table
    - Green checkmarks for matches, red X for mismatches
    - Warning message highlighting potential missing items
  - **Persistent Discrepancy Tracking**: Database storage for audit trail
    - New fields: `invoice_stated_total`, `calculated_total`, `total_discrepancy`, `has_discrepancy`
    - `parsing_metadata` JSON field on `delivery_documents` table
  - **Show Page Warning Banner**: Red warning banner displays on delivery detail page when discrepancy detected
  - **Files Modified**:
    - `scripts/invoice-parser/parsers/delivery_udea.py` - Added `_extract_invoice_totals()` method
    - `scripts/invoice-parser/parsers/delivery_independent.py` - Added `_extract_invoice_totals()` method
    - `app/Services/DeliveryParsingService.php` - Passes through totals verification data
    - `app/Services/DeliveryService.php` - Stores discrepancy data in database
    - `app/Http/Controllers/DeliveryController.php` - Passes totals to service
    - `app/Models/Delivery.php` - Added discrepancy fields to fillable/casts
    - `app/Models/DeliveryDocument.php` - Added `parsing_metadata` field
    - `resources/views/deliveries/create.blade.php` - Totals verification UI section
    - `resources/views/deliveries/show.blade.php` - Discrepancy warning banner
  - **Files Created**:
    - `database/migrations/2026_01_29_160146_add_totals_verification_to_deliveries_table.php`

- **📊 Udea Invoice Parser** (2026-01-28)
  - **Invoice Header Extraction**: Parses invoice number, date, total excl VAT, VAT amount, zero-VAT confirmation
  - **Product Line Parsing**: Extracts article codes, descriptions, quantities, unit prices, and line totals
  - **Line Classification**: Automatic categorization by Gb.rek account code:
    - 30302 = AGF (Fruit & Vegetables)
    - 30322 = DKW (Dry goods)
    - 30342 = Drogmetica
    - 30362 = Non-food
    - 30862 = Transport/Freight
    - 34120 = Barrels/Deposits
  - **Barrel/Deposit Extraction**: Detects "Barrels delivered" section with codes, quantities, values
  - **Freight/Costs Detection**: Extracts transport charges from "Costs" section
  - **Validation**: Reconciles sum of line totals against invoice total (€0.50 tolerance)
  - **Problem Line Reporting**: Reports unparseable lines with reasons (no Gb.rek, truncated, etc.)
  - **PDF Corruption Handling**: Fixes common text extraction issues:
    - `Bio-Dynamis3c0h302` → `Bio-Dynamisch 30302`
    - `1kilogramOnions` → `1kilogram Onions`
    - Scrambled Gb.rek codes recovered from corrupted text
  - **~96% Accuracy**: Captures 248 of ~263 product lines on sample invoices
  - **Web Interface**: "Parse Udea" button on `/invoices/bulk-upload/preview` page
  - **Debug Modal**: Shows header, validation, lines, barrels, costs, warnings, and problem lines
  - **Files Created**:
    - `scripts/invoice-parser/parsers/invoice_udea.py` - Python parser with pdfplumber
    - `docs/features/udea-invoice-parser.md` - Feature documentation
  - **Files Modified**:
    - `app/Http/Controllers/InvoiceBulkUploadController.php` - Added `parseUdeaInvoice()` method
    - `routes/web.php` - Added `parse-udea` route
    - `resources/views/invoices/bulk-upload-preview.blade.php` - Added Parse Udea button and results modal

- **🏷️ Supplier VAT Classification Fields** (2026-01-28)
  - **New Fields**: `vat_treatment` and `default_purchase_use` enums on AccountingSupplier model
  - **VAT Treatment Options**: irish_vat, eu_goods_zero_rated, eu_reverse_charge_services, postponed_import, outside_scope_or_exempt
  - **Purchase Use Options**: resale, overhead, mixed
  - **Auto-Default Logic**: VAT treatment auto-set based on country_code (IE→irish_vat, EU→eu_goods_zero_rated, etc.)
  - **Inline Editing**: Update VAT fields directly on `/suppliers` index page via AJAX
  - **VAT Filter**: Filter suppliers by VAT treatment on index page
  - **Statistics Display**: Shows counts of suppliers by VAT classification
  - **Files Created**:
    - `database/migrations/2026_01_28_135442_add_vat_classification_to_accounting_suppliers_table.php`
  - **Files Modified**:
    - `app/Models/AccountingSupplier.php` - Added fields, constants, helper methods, boot() auto-default
    - `app/Http/Controllers/AccountingSuppliersController.php` - Added updateVatClassification(), validation
    - `routes/web.php` - Added `suppliers.update-vat-classification` route
    - `resources/views/suppliers/index.blade.php` - VAT column, inline dropdowns, AJAX, filter, stats
    - `resources/views/suppliers/edit.blade.php` - VAT Classification section
    - `resources/views/suppliers/create.blade.php` - VAT Classification section

- **📄 Delivery Document Storage & Viewing** (2026-01-27)
  - **Permanent Document Storage**: PDF and CSV files uploaded during delivery creation are now stored permanently
  - **Document Viewer**: View delivery documents at `/delivery-documents/{id}/viewer` with clean minimal interface
  - **Popup Window**: Documents open in separate popup window (900x700) without navigation elements
  - **Collapsible Section**: Documents section on delivery detail page is collapsible (collapsed by default)
  - **Legacy Integration**: Document links synced to legacy delivery pages via cache mechanism
  - **Multiple Documents**: Support for multiple documents per delivery (PDF invoices, CSV imports)
  - **File Management**: Automatic cleanup when delivery/document is deleted
  - **Files Created**:
    - `database/migrations/2026_01_27_143710_create_delivery_documents_table.php`
    - `app/Models/DeliveryDocument.php`
    - `app/Http/Controllers/DeliveryDocumentController.php`
    - `resources/views/deliveries/document-viewer.blade.php`
    - `resources/views/deliveries/document-viewer-minimal.blade.php`
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryController.php` - Document saving in store/storePdf
    - `app/Models/Delivery.php` - Added documents() relationship
    - `resources/views/deliveries/show.blade.php` - Collapsible documents section
    - `resources/views/delivery-legacy/index.blade.php` - Synced documents display
    - `resources/views/delivery-legacy/match.blade.php` - Invoice documents section

- **🔄 Create Legacy Scan Session** (2026-01-27)
  - **New Session Creation**: Create new delivery scan sessions directly from `/delivery-legacy` page
  - **Supplier Selection**: Select supplier when creating new session
  - **UUID-Based IDs**: Sessions created with UUID identifiers
  - **Direct Redirect**: After creation, redirects to match page for the new session
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryLegacyController.php` - Added createSession() method
    - `routes/web.php` - Added `delivery-legacy.create-session` route
    - `resources/views/delivery-legacy/index.blade.php` - Added create session form

### Changed

- **📊 Stock Input Precision Enhancement** (2026-01-27)
  - **2 Decimal Places**: Stock input fields now accept 2 decimal places (was 1)
  - **Arrow Key Behavior**: Up/down arrow keys increment by 1 (not 0.01) for quick adjustments
  - **Display Update**: Stock values display with 2 decimal places for consistency
  - **Pages Updated**: Products index (`/products`) and product detail (`/products/{uuid}`)
  - **Use Case**: Allows precise stock entries like 12.75 units for weighted/measured items
  - **Files Modified**:
    - `resources/views/products/index.blade.php` - Inline stock editing
    - `resources/views/products/show.blade.php` - Stock edit form

### Added

- **⚖️ Weight-Based Product Support for Udea Deliveries** (2026-01-27)
  - **Automatic Detection**: Parser detects weight-based products (e.g., meat sold by kg) from PDF invoices
  - **Weight Fields**: New database fields `is_weight_based`, `weight_per_unit`, `weight_unit`, `total_weight`
  - **Correct Price Validation**: Weight-based products validate as `total_weight × price` (not qty × price)
  - **INVOICED Column**: Shows total weight (e.g., 0.921 kg) instead of quantity for weight-based items
  - **Delivery Legacy Sync**: Syncs `total_weight` to legacy `myOrder` field for weight-based products
  - **Visual Indicators**: Purple badges show weight breakdown (e.g., "0.307 kg × 3 = 0.921 kg")
  - **Decimal Input**: All scanned quantity fields accept decimal values (step="0.001")
  - **Files Created**:
    - `database/migrations/2026_01_27_101241_add_weight_fields_to_delivery_items_table.php`
  - **Files Modified**:
    - `app/Models/DeliveryItem.php` - Added weight fields to fillable and casts
    - `scripts/invoice-parser/parsers/delivery_udea.py` - Weight detection and extraction
    - `app/Services/DeliveryParsingService.php` - Pass weight data through conversion
    - `app/Services/DeliveryService.php` - Store weight fields on import
    - `app/Http/Controllers/DeliveryController.php` - Sync weight to legacy
    - `resources/views/deliveries/show.blade.php` - Display weight badges
    - `resources/views/delivery-legacy/match.blade.php` - Weight display and decimal inputs

- **🖼️ Product Images in Delivery Legacy Pages** (2026-01-26)
  - **Image Thumbnails**: Product images now display in the left column of all tables on `/delivery-legacy/match`
  - **Hover Preview**: Large image preview on hover using fixed positioning (displays over table headers/footers)
  - **Smart Positioning**: Preview automatically appears below thumbnail, or above if near viewport bottom
  - **Alpine.js Teleport**: Uses `x-teleport="body"` to render preview outside overflow containers
  - **Barcode Column**: Added always-visible barcode column to "Pending - Not Yet Scanned" section
  - **Supplier Integration**: Uses existing `SupplierService` for external image URLs (UDEA CDN)
  - **Tables Updated**: Critical Issues, Warnings, Verified, OOS, Pending, Extra Items, Missing Items
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryLegacyController.php` - Injected SupplierService
    - `resources/views/delivery-legacy/match.blade.php` - Added image columns to all tables
    - `resources/views/components/product-image.blade.php` - Enhanced hover with fixed positioning

- **✅ Barcode Exists Highlighting in Deliveries** (2026-01-26)
  - **Auto-Detection**: When refreshing a barcode from supplier website, system checks if barcode already exists in POS products
  - **Green Highlighting**: Existing barcodes display with green background, checkmark icon in separate circle
  - **Product Link**: Clickable link opens existing product page in new tab for verification
  - **Persistent Display**: Highlighting persists through auto-refresh polling (every 10 seconds)
  - **Tooltip**: Hover shows product name for quick identification
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryController.php` - Added Product lookup in `refreshBarcode()` and AJAX response
    - `resources/views/deliveries/show.blade.php` - Updated `refreshBarcode()` and `updateBarcodeCell()` JS functions

- **📦 Barrel Deposit Tracking System** (2026-01-26)
  - **Automatic Extraction**: Barrel deposits (crates, bottles, pallets) parsed from Udea delivery PDFs
  - **Reference Database**: `barrel_codes` table auto-populated from imports with supplier linkage
  - **Per-Delivery Tracking**: `delivery_barrels` table records each barrel item per delivery
  - **Custom Naming**: Add your own names to barrel codes for easier identification
  - **Image Support**: Upload photos (100x100 resized) for visual identification
  - **Collapsible Display**: Barrel section on delivery show page collapsed by default with Show/Hide toggle
  - **Management Page**: `/barrel-codes` - browse, filter by supplier/status, search, and edit barrel codes
  - **Parser Boundary Fix**: Barrels section correctly stops at "Costs" to exclude freight charges
  - **Files Created**:
    - `app/Models/BarrelCode.php` - Barrel code reference model
    - `app/Models/DeliveryBarrel.php` - Delivery barrel line item model
    - `app/Http/Controllers/BarrelCodeController.php` - CRUD with image handling
    - `resources/views/barrel-codes/index.blade.php` - List page with filters
    - `resources/views/barrel-codes/edit.blade.php` - Edit form with image upload
    - `docs/features/barrel-deposit-tracking.md` - Feature documentation
  - **Files Modified**:
    - `scripts/invoice-parser/parsers/delivery_udea.py` - Barrel section extraction
    - `scripts/invoice-parser/delivery_parser_laravel.py` - Include barrels in response
    - `app/Services/DeliveryService.php` - storeBarrelItems() method
    - `app/Http/Controllers/DeliveryController.php` - storePdf() integration
    - `resources/views/deliveries/show.blade.php` - Collapsible barrel display
    - `resources/views/deliveries/index.blade.php` - Barrel Codes button

- **📦 Delivery Legacy - Out of Stock (OOS) Handling** (2026-01-24)
  - **OOS Items at Bottom**: OOS items (ordered but not delivered) now sync to legacy at the bottom of the list
  - **Expected: 0 for OOS**: OOS items display "Expected: 0" instead of their ordered quantity for clearer verification
  - **Correct Case Units**: OOS items retain correct invoice case units (not hardcoded to 1)
  - **Separate OOS Section**: New "Out of Stock" section on delivery-legacy match page with orange styling
  - **OOS Excluded from Missing**: OOS items no longer appear in "Missing Items" section (HAVING clause filter)
  - **OOS Excluded from Verified**: OOS items no longer incorrectly appear in "Verified Items"
  - **Visual Progress**: OOS count displayed in progress bar area
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryController.php` - Two-pass sync with OOS detection
    - `app/Http/Controllers/DeliveryLegacyController.php` - HAVING clause to exclude OOS from Missing Items
    - `resources/views/delivery-legacy/match.blade.php` - OOS section and filter updates

- **🔍 Auto Supplier Detection on PDF Upload** (2026-01-24)
  - **Automatic Detection**: System identifies supplier from PDF content when uploading deliveries
  - **Supported Suppliers**: Independent Irish Health Foods, UDEA, Mossfield
  - **Visual Feedback**: Shows "Detecting supplier..." status while parsing
  - **Smart Mapping**: Converts detected supplier name to correct supplier ID
  - **Graceful Fallback**: If supplier cannot be detected, user can select manually
  - **Route**: `POST /deliveries/detect-supplier`
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryController.php` - Added `detectSupplier()` and `mapSupplierNameToId()`
    - `resources/views/deliveries/create.blade.php` - Added auto-detection UI and JavaScript
    - `routes/web.php` - Added `deliveries.detect-supplier` route

- **🔘 Clickable Case Unit Mismatch Badges** (2026-01-24)
  - **Quick Update**: Case mismatch badges (e.g., "Case: 1 → 12") are now clickable buttons
  - **One-Click Fix**: Clicking updates DB case units to match invoice case units
  - **Auto Reload**: Page reloads after update to reflect corrected expected quantities
  - **Hover Feedback**: Title tooltip shows what value will be applied
  - **Available In**: Critical Issues and Warnings sections
  - **Files Modified**:
    - `resources/views/delivery-legacy/match.blade.php` - Converted case badges to clickable buttons

### Fixed

- **🧾 Tax Rate Calculation for PDF Imports** (2026-01-24)
  - **Root Cause**: `importFromPdfData()` saved tax_amount but didn't calculate tax_rate or normalized_tax_rate
  - **Fix**: Added tax rate calculation: `(tax / lineTotal) * 100` with Irish VAT normalization
  - **Impact**: Delivery items now have correct tax_rate and normalized_tax_rate values
  - **Files Modified**:
    - `app/Services/DeliveryService.php` - Added tax rate calculation in `importFromPdfData()`

- **⌨️ Label Scanner Keyboard Toggle** (2026-01-24)
  - **Keyboard Toggle Button**: Added toggle button beside barcode input in scan-to-label modal
  - **Mobile Keyboard Control**: Click to show/hide virtual keyboard for manual barcode entry
  - **Visual State Feedback**: Blue styling when enabled, gray when disabled (matches stocking page pattern)
  - **Dark Mode Support**: Full dark mode styling for the toggle button
  - **Focus Preservation**: Automatically refocuses input after toggling keyboard state
  - **Files Modified**:
    - `resources/views/labels/index.blade.php` - Added keyboard toggle button and `keyboardEnabled` Alpine state

- **📄 UDEA PDF Delivery Parsing** (2026-01-23)
  - **Automatic Supplier Detection**: Parses "UDEA B.V." or "WWW.UDEA.NL" from PDF text
  - **European Number Formatting**: Converts 1.234,56 → 1234.56 automatically
  - **Three-Tier Regex Matching**: NORMAL → QUANTITY_SKU → FALLBACK patterns for robust parsing
  - **Weight-Based Products**: Handles kilogram, gram, and SKU-based quantities
  - **Price Validation**: Qty × Price × SKU verification with configurable tolerance
  - **High Confidence**: Achieves 99-100% confidence on standard UDEA invoices
  - **Files Created**:
    - `scripts/invoice-parser/parsers/delivery_udea.py` - UDEA-specific parser
  - **Files Modified**:
    - `scripts/invoice-parser/delivery_parser_laravel.py` - Added UDEA detection and import
    - `resources/views/deliveries/create.blade.php` - Added UDEA to supported suppliers list
  - **Test Results**: 3 UDEA PDFs with 257 combined items, €3,938.47 total

- **📦 Multi-PDF Delivery Upload** (2026-01-23)
  - **Multiple File Selection**: Upload multiple PDFs at once to create single delivery
  - **Per-File Status**: Preview shows success/failure and item count for each file
  - **Item Merging**: All items from all PDFs combined into single delivery
  - **Total Aggregation**: Values summed across all files with combined statistics
  - **Confidence Scoring**: Weighted average confidence across parsed files
  - **Files Processed Summary**: Visual breakdown of each file's contribution
  - **Backward Compatible**: Single file uploads continue to work as before
  - **Files Modified**:
    - `app/Services/DeliveryParsingService.php` - Added `parseMultipleDeliveryPdfs()` method
    - `app/Http/Controllers/DeliveryController.php` - Updated `parsePdf()` and `storePdf()` for multi-file
    - `resources/views/deliveries/create.blade.php` - Multi-file UI with `multiple` attribute
  - **Documentation**: See [Delivery System Documentation](./docs/features/delivery-system.md#multi-pdf-upload-support)

- **⚡ Product Detail Page Performance Optimization** (2026-01-22)
  - **Lazy-Loaded Sales Data**: Sales history section now loads via AJAX after page render
  - **Optimized Database Queries**: Combined 4 separate queries into 1 using SQL CASE statements
    - Before: 1 EXISTS check + 3 SUM queries = 4 database round trips
    - After: Single query with CASE statements = 1 database round trip (75% reduction)
  - **Detailed Sales History Modal**: Added "Detailed Sales History" button that opens the full interactive sales chart modal (same as products listing)
    - Weekly view with expand/contract date range
    - Click on week to drill down to daily view
    - Click on day to see individual transactions
  - **Instant Page Load**: Product detail pages now render immediately without waiting for sales data
  - **Files Modified**:
    - `app/Repositories/SalesRepository.php` - Optimized `getProductSalesStatistics()` method
    - `app/Http/Controllers/ProductController.php` - Removed synchronous sales loading from `show()`
    - `resources/views/products/show.blade.php` - Added lazy loading and sales chart modal
  - **Performance Pattern**: Follows the proven optimization pattern from [Sales Data Import Plan](./docs/features/sales-data-import-plan.md)

- **📦 Stocking Scanner** (2026-01-22)
  - **Mobile-First Store Room Scanner**: Dedicated page at `/stocking` for checking stock levels in the store room
  - **Stock Level Display**: Scan product barcode to see current stock count prominently displayed
  - **Stock Adjustment**: Adjust stock levels directly from the scanner with +/- buttons
  - **Add to Label Queue**: Quick button to add scanned products to the label print queue
  - **Audit Trail**: All stock adjustments logged with user, timestamp, old/new values
  - **Keyboard Toggle**: Button to show/hide mobile keyboard (scanner mode vs manual entry)
  - **Scan History**: Recent scans stored in localStorage for quick reference
  - **Admin Stock Logs Page**: View all stock adjustments at `/stocking/logs` (admin only)
    - Filter by barcode, user, and date range
    - Color-coded changes (green for increases, red for decreases)
    - Links to product detail pages
  - **Sidebar Navigation**: Links under "Stock" section (Stocking, Labels & Printing)
  - **Database Schema**: New `stock_adjustments` table for audit trail
  - **Files Created**:
    - `app/Http/Controllers/StockingController.php`
    - `app/Models/StockAdjustment.php`
    - `database/migrations/2026_01_22_105722_create_stock_adjustments_table.php`
    - `resources/views/stocking/index.blade.php`
    - `resources/views/stocking/logs.blade.php`
  - **Files Modified**:
    - `routes/web.php` - Added stocking routes
    - `resources/views/layouts/admin.blade.php` - Added Stock section and Stock Logs link
  - **Documentation**: See [Stocking Documentation](./docs/features/stocking.md)

- **🍳 Kitchen Products Management System** (2026-01-21)
  - **Kitchen Products List**: Dedicated page at `/kitchen/products` for managing products that regularly go to the kitchen
  - **Quick Flag from Orders**: "Kitchen" toggle button on Orders review page (`/orders/`) to quickly add/remove products from kitchen list
  - **Product Search & Add**: Search and add products directly from kitchen products page
    - Search by product name, barcode (CODE), or supplier code
    - Results show product name, barcode, supplier code, and supplier name
    - One-click add with success feedback and page reload
  - **Supplier Code Quick Copy**: Click supplier code to copy to clipboard with visual feedback
    - Fallback method for non-HTTPS environments using execCommand
    - "Copied!" confirmation with checkmark icon
  - **Shop Stock Display**: Real-time stock levels from POS `STOCKCURRENT` table
    - Color-coded: green for in-stock, red for negative stock
  - **Kitchen Stock Placeholder**: Column ready for future kitchen inventory tracking
  - **Ingredient Profile Integration**: Direct links to create or edit ingredient profiles for costing
  - **Filtering Options**:
    - Supplier dropdown filter
    - Group by category toggle with collapsible category sections
    - Text search for product name/code
  - **Statistics Dashboard**: Cards showing total kitchen products and profile coverage
  - **Sidebar Navigation**: Quick access link in admin sidebar
  - **Database Schema**: New `kitchen_products` table with `product_id` (UUID) and notes field
  - **Files Created**:
    - `database/migrations/2026_01_20_114028_create_kitchen_products_table.php`
    - `app/Models/KitchenProduct.php`
    - `app/Http/Controllers/KitchenProductController.php`
    - `resources/views/kitchen/products/index.blade.php`
    - `resources/views/kitchen/products/partials/product-row.blade.php`
  - **Files Modified**:
    - `routes/web.php` - Added kitchen products routes
    - `resources/views/orders/partials/review-table.blade.php` - Added Kitchen toggle button
    - `resources/views/layouts/admin.blade.php` - Added sidebar link
  - **Documentation**: See [Kitchen Products Documentation](./docs/features/kitchen-products.md)

- **📊 Delivery Legacy - Stock Update Verification System** (2026-01-21)
  - **Stock Update Preview**: Blue card shows what will happen before clicking complete:
    - Products to update count
    - Total units to add
    - Current stock total (for affected products only)
    - Expected stock after update
  - **Update Results Banner**: Enhanced completion banner shows actual results:
    - Products actually updated
    - Units actually added
    - Products skipped (no STOCKCURRENT record)
  - **Extra Items Stock Column**: Added Stock column to Extra Items section showing current stock
  - **Extra Items Processing**: Fixed bug where Extra section items weren't included in stock updates
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryLegacyController.php` - Added `calculateStockPreview()`, result tracking, STOCKCURRENT join for Extra Items
    - `resources/views/delivery-legacy/match.blade.php` - Added preview card, enhanced completion banner, Stock column in Extra Items

- **✅ Delivery Legacy - Update Stock & Completion** (2026-01-20)
  - **Update Stock Button**: "Update Stock & Complete" button in header to finalize delivery verification
  - **Stock Updates**: Increments `STOCKCURRENT.UNITS` for all products with scanned quantities
  - **Completion Status**: Sets `deliveriesScan.status` to 1 (integer) to mark delivery as finalized
  - **Read-Only Mode**: After completion, all edit functionality is disabled to preserve record
  - **Completion Banner**: Green banner shows "Delivery Complete - Stock has been updated"
  - **Visual Indicators**: Pencil icons and arrow buttons hidden when completed
  - **Transaction Safety**: Stock updates and status change wrapped in database transaction
  - **Confirmation Dialog**: Warns user before completing (action cannot be undone)
  - **Files Modified**:
    - `routes/web.php` - Added `delivery-legacy.complete` route
    - `app/Http/Controllers/DeliveryLegacyController.php` - Added `completeDelivery()`, `isCompleted` check
    - `resources/views/delivery-legacy/match.blade.php` - Added button, banner, `canEdit` pattern for read-only

- **📝 Delivery Legacy - Delivered Column for Pending Items** (2026-01-20)
  - **Delivered Column**: New column in "Pending - Not Yet Scanned" section for entering quantities
  - **Arrow Auto-Fill**: Click → button to copy expected quantity to delivered field instantly
  - **Manual Entry**: Click delivered field directly for custom quantity entry
  - **Decimal Support**: Accepts up to 3 decimal places for weight-based items (step="0.001")
  - **Workflow Integration**: Saved items move to Verified or Critical sections automatically
  - **Use Case**: Allows quantity entry for non-scannable items (no barcode, bulk items, etc.)
  - **Files Modified**:
    - `resources/views/delivery-legacy/match.blade.php` - Added Delivered column with arrow button and editable input

- **🔧 Delivery Legacy - Case Quantity Mismatch Improvements** (2026-01-20)
  - **Issue Column in Critical Issues**: Now shows "Case: X → Y" badge when case units mismatch alongside quantity issues
  - **Inline-Editable DB Case**: Click DB Case column to edit `supplier_link.CaseUnits` directly
  - **Consistent Editing Pattern**: Same UX as scanned quantity editing (click, edit, save/cancel)
  - **Auto Recalculation**: Page reloads after case update to reflect new expected quantities
  - **Available Everywhere**: Case editing works in Critical Issues, Warnings, and Verified sections
  - **Files Created/Modified**:
    - `routes/web.php` - Added `delivery-legacy.update-case-units` route
    - `app/Http/Controllers/DeliveryLegacyController.php` - Added `updateCaseUnits()` method
    - `resources/views/delivery-legacy/match.blade.php` - Added Issue column, editable DB Case, JS handler

- **📦 Category Products Stock Display & Editing** (2026-01-20)
  - **Show Stock Toggle**: New checkbox in filters to display/hide stock column
  - **Editable Stock Values**: Click-to-edit inline stock editing on category products page
  - **Adaptive Decimal Display**: Smart formatting shows decimals for liquid products, whole numbers for regular items
  - **Real-time Updates**: Uses existing `/products/{id}/update-stock` endpoint for instant saves
  - **Color-Coded Display**: Green for in-stock (>0), gray for out-of-stock (0)
  - **Global Component Reference**: Follows delivery-legacy pattern for reliable nested component access
  - **Files Modified**:
    - `app/Http/Controllers/CategoriesController.php` - Added `stockCurrent` eager loading and `current_stock` to responses
    - `resources/views/categories/products.blade.php` - Added stock toggle, editable column, and `updateStock()` method

### Fixed

- **🔗 Delivery Legacy Match Page - Product Link Fixes** (2026-01-19)
  - **Correct Product URLs**: Product links now use UUID (`productID`) instead of barcode, fixing broken links
  - **Edit Page Navigation**: Links now go to `/products/{uuid}/edit` instead of show page for direct editing
  - **New Tab Opening**: All product links open in new tabs (`target="_blank"`) so users can easily return to delivery
  - **Case Unit Values Display**: "Case units changed" badge now shows actual values (e.g., "Case: 6 → 5")
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryLegacyController.php` - Added `PRODUCTS.ID as productID` to SQL queries
    - `resources/views/delivery-legacy/match.blade.php` - Updated links to use productID with target="_blank"

### Added

- **🔄 Delivery Sync to Legacy Feature** (2026-01-19)
  - **Sync Button**: New "Sync to Legacy" button on delivery detail pages
  - **One-Click Sync**: Copies delivery items from Laravel to POS `delivery` table for legacy comparison
  - **Invoice Matching**: Enables comparison with scanned items via `/delivery-legacy/match`
  - **Confirmation Dialog**: Warns user that existing legacy data will be replaced
  - **Transaction Safety**: Uses database transaction for safe data transfer
  - **Files Created**:
    - `app/Models/LegacyDelivery.php` - Eloquent model for POS delivery table
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryController.php` - Added `syncToLegacy()` method
    - `routes/web.php` - Added `deliveries.sync-legacy` route
    - `resources/views/deliveries/show.blade.php` - Added sync button

- **☕ Internal Customer Sales Tracking on Order Charts** (2026-01-19)
  - **Coffee & Kitchen Tracking**: Order review charts now display internal department transfers (Coffee, Kitchen) alongside regular sales
  - **Visual Distinction**: Purple solid line for Coffee (☕), orange dashed line for Kitchen (🍳), blue for total sales
  - **Interactive Tooltips**: Hover shows contextual info with emoji indicators
  - **Optimized Performance**: Single database query fetches both Coffee and Kitchen data via `getBulkInternalCustomerWeeklySales()`
  - **Modal Support**: Expanded sales history modal also shows Coffee/Kitchen lines
  - **Universal**: Works for all suppliers (Udea, Independent, Mossfield, etc.)
  - **Files Modified**:
    - `app/Repositories/SalesRepository.php` - Added `getBulkInternalCustomerWeeklySales()` method
    - `app/Services/OrderService.php` - Pre-fetches coffee/kitchen data, adds to context_data
    - `app/Http/Controllers/ProductController.php` - Updated API to include coffee/kitchen
    - `resources/views/orders/partials/review-table.blade.php` - Added chart datasets and tooltips

- **📦 Delivery Legacy Page Redesign** (2026-01-18)
  - **Financial Dashboard**: 6-card overview showing Invoice Total, Scanned Total, Discrepancy, Missing Value, Extra Value, and Margin Alerts
  - **Progress Bar**: Visual verification progress with verified/total item counts
  - **Quick Filters**: Alpine.js-powered buttons for All Items, Problems Only, and Verified Only views
  - **Issues-First Layout**: Collapsible sections prioritized by severity (Critical, Warnings, Verified, Pending, Extra, Missing)
  - **Simplified Tables**: Default view shows Product, Expected, Scanned, Diff, Stock columns
  - **Detailed View Toggle**: "Show Details" checkbox reveals VAT, Barcode, Cost, Sell, Margin columns
  - **Stock Column Always Visible**: Moved from details toggle to always-on for easier verification
  - **Admin Layout Integration**: Added sidebar navigation matching rest of application
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryLegacyController.php` - Added `calculateFinancials()` method
    - `resources/views/delivery-legacy/match.blade.php` - Complete redesign with Alpine.js interactivity
    - `resources/views/delivery-legacy/index.blade.php` - Changed to admin layout

- **👁️ Category Visibility Management** (2026-01-14)
  - **Products Page Toggle**: New "Show hidden categories" checkbox to include all categories in the dropdown filter
  - **Hidden Category Indicator**: Categories marked as hidden show "(hidden)" suffix in the dropdown
  - **Categories Page Visibility Stats**: Header now displays visible/hidden category counts
  - **Visual Visibility Toggle**: Eye icon button on each category card to toggle visibility
  - **Instant AJAX Updates**: Toggle visibility without page reload using Alpine.js
  - **POS Integration**: Changes `CATSHOWNAME` field in POS database to control dropdown visibility
  - **Files Modified**:
    - `app/Repositories/ProductRepository.php` - Added `showHidden` parameter to `getAllCategoriesWithProducts()`
    - `app/Http/Controllers/ProductController.php` - Added `showHiddenCategories` handling
    - `app/Http/Controllers/CategoriesController.php` - Added `toggleCategoryVisibility()` method
    - `resources/views/products/index.blade.php` - Added checkbox filter and hidden indicator
    - `resources/views/categories/index.blade.php` - Added visibility stats and toggle buttons
    - `routes/web.php` - Added `categories.category-visibility.toggle` route

### Fixed

- **🖼️ Product Image Quality Improvement** (2026-01-13)
  - **Increased Resolution**: Product image uploads now resize to 128x128 pixels (was 64x64)
  - **Sharper Display**: Images now match the UI display size exactly, eliminating blurriness
  - **Files Modified**:
    - `app/Http/Controllers/ProductController.php` - Updated resize dimensions
    - `tests/Feature/FruitVegProductImageTest.php` - Updated test assertions

### Added

- **💰 Supplier Payments Report** (2026-01-12)
  - **Payments Listing**: View all supplier invoice payments within a date range
  - **Sorting Options**: Sort by date (newest/oldest) or supplier name (A-Z/Z-A)
  - **Group by Supplier**: Collapsible sections with Expand All/Collapse All toggle
  - **Summary Statistics**: Total payments, count, and breakdown by payment method
  - **Date Format**: UK format (dd/mm/yyyy) for payment and invoice dates
  - **Invoice Date Column**: Shows invoice date alongside payment date for reference
  - **CSV Export**: Download filtered payments with current sort order
  - **Navigation**: Green "Payments" button added to suppliers index
  - **Routes**: `/suppliers/payments` and `/suppliers/payments/export`
  - **Files Created/Modified**:
    - `app/Http/Controllers/SupplierPaymentsController.php` - New controller
    - `resources/views/suppliers/payments.blade.php` - New view with grouped/flat modes
    - `resources/views/suppliers/index.blade.php` - Added navigation link

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