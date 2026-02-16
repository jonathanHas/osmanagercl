# RTD (Return of Trading Details) System

The RTD system processes supplier invoices (Udea and Dynamis) to extract and categorize goods for resale by Irish VAT rate. It provides tools to resolve unmatched article codes and freeze final RTD data for VAT reporting.

## Overview

**Navigation:** Sidebar → Revenue → RTD Management

**Purpose:** Automatically categorize supplier invoice line items by VAT rate (0%, 9%, 13.5%, 23%) for Irish VAT return preparation.

**Supported Suppliers:**
- **Udea** - Dutch organic wholesaler (article code → SupplierLink resolution)
- **Dynamis** - French organic produce supplier (EAN barcode → Product resolution)
- **Independent Irish Health Foods (IIH)** - Irish health food distributor (VAT summary → direct categorization, DRS excluded)

**Key Features:**
- Dedicated RTD Management page with summary statistics and filtering
- Parses supplier invoice PDFs to extract line items with article codes
- Resolves article codes to VAT rates via product links, EAN barcodes, or manual fallbacks
- Tracks unresolved items for manual assignment
- Detects missing PDF files and flags them with "PDF Missing" status
- Freezes RTD data to create immutable audit snapshots
- Inline expandable rows to view VAT breakdown without navigation
- Year Report page for aggregated RTD totals by date range
- Issues Queue for managing invoices with unresolved items

**Workflow Summary:**
```
Upload PDF → Parse Lines → Resolve Article Codes → Categorize by VAT → Review → Freeze → Submit to Revenue
```

**Complete Workflow:**
1. Upload supplier invoice PDF (bulk upload or manual attachment)
2. Parse the PDF to extract line items (`/rtd` → Parse)
3. Compute RTD to resolve article codes (`/rtd` → Compute)
4. If unresolved items exist:
   - Use Issues Queue (`/rtd/issues`) to identify problematic invoices
   - Create fallbacks (`/rtd-fallbacks`) for unrecognized codes
   - Recompute affected invoices
5. Freeze completed invoices (`/rtd` → Freeze)
6. Generate Year Report (`/rtd/year-report`) for VAT returns
7. Create Submission (`/rtd/submissions/create`) — select frozen invoices for a period and link them to a Revenue filing
8. Mark Submission as filed with Revenue date and reference number

## Submission Tracking

Submissions track which frozen invoices were included in each RTD filing with Revenue.

**Navigation:** RTD Management → Submissions

**Key Concepts:**
- Only frozen invoices can be added to a submission
- Each invoice can only belong to one submission
- The next submission automatically shows invoices not yet linked to any previous submission
- Submissions store a snapshot of VAT breakdown totals (T1 goods, T2 service, excluded) at creation time
- Draft submissions allow adding and removing invoices; submitted ones are read-only
- **Paperin Adjustment** (FIX 2026-02-16): Sales figures deduct paperin (gift voucher redemption) gross from 0% net to prevent double-counting revenue. Applied consistently across all three data source tiers (persisted VAT data, `sales_accounting_daily` fallback, and direct fallback)

**Adding Invoices to Draft Submissions (NEW! 2026-02-13):**

When invoices are frozen after a draft submission is created, they can be imported directly from the submission detail page:
- A blue banner shows the count of available frozen invoices (unsubmitted, with RTD snapshot)
- Expand to see a table with checkboxes listing invoice #, supplier, date, and amount
- "Select All" checkbox and "Add Selected" button for batch import
- AJAX-powered with automatic totals recalculation after import

**Routes:**
- `GET /rtd/submissions` — list all submissions
- `GET /rtd/submissions/create` — create form with invoice selection
- `GET /rtd/submissions/{id}` — view submission details and totals
- `POST /rtd/submissions/{id}/add-invoices` — add frozen invoices to a draft submission
- `POST /rtd/submissions/{id}/submit` — mark as filed with Revenue

## RTD Status Types

| Status | Badge Color | Description |
|--------|-------------|-------------|
| `frozen` | Green | RTD accepted and immutable |
| `computed` | Blue | RTD calculated, ready to freeze |
| `has_issues` | Yellow | Has unresolved items needing attention |
| `needs_computation` | Orange | Parsed data available, needs compute |
| `needs_parsing` | Red | PDF available, needs parsing |
| `pdf_missing` | Gray | Attachment record exists but PDF file missing from disk |

## Database Schema

### Invoice RTD Fields

| Field | Type | Purpose |
|-------|------|---------|
| `rtd_breakdown` | JSON | Computed VAT breakdown by rate |
| `rtd_status` | String | `pending`, `computed`, or `frozen` |
| `rtd_resolution_issues` | JSON | Array of unresolved items |
| `rtd_snapshot` | JSON | Immutable snapshot when frozen |
| `rtd_computed_at` | Timestamp | When RTD was last computed |
| `rtd_accepted_at` | Timestamp | When RTD was frozen |
| `rtd_accepted_by` | Foreign Key | User who froze the RTD |

### RTD Breakdown Structure

```json
{
  "goods_for_resale": {
    "0": 500.00,
    "9": 200.00,
    "13.5": 150.00,
    "23": 100.00
  },
  "excluded": {
    "freight": 25.00,
    "deposits": 50.00,
    "drs": 4.35,
    "vat": 50.00,
    "service_overhead": 12.84
  },
  "unresolved": {
    "count": 3,
    "net_total": 75.00
  },
  "stats": {
    "total_lines": 50,
    "resolved_lines": 47,
    "excluded_lines": 5
  }
}
```

**Notes:**
- The `drs` field (Deposit Return Scheme) is populated for IIH invoices only.
- The `service_overhead` field holds non-retail fallback items (cleaning supplies, office equipment) that should not inflate T1 goods for resale. Also used for service/overhead classified suppliers.
- The `vat` field holds the VAT amount from the invoice (excluded from goods for resale net totals).

### RtdVatFallback Table

Manual VAT rate assignments for unresolved article codes.

| Field | Type | Purpose |
|-------|------|---------|
| `article_code` | String(50) | Supplier article code |
| `supplier_id` | Foreign Key | Accounting supplier ID |
| `vat_rate` | Decimal | VAT rate: 0, 9, 13.5, or 23 |
| `is_non_retail` | Boolean | If true, routes to `excluded.service_overhead` instead of `goods_for_resale` (default: false) |
| `description` | String(100) | Product description |
| `notes` | Text | Additional context |
| `created_by` | Foreign Key | User who created entry |
| `updated_by` | Foreign Key | User who last updated |

**Non-Retail Classification:** When `is_non_retail` is true, the item's value goes into `excluded.service_overhead` instead of `goods_for_resale`. This prevents non-resale items (cleaning supplies, office equipment) from inflating T1 totals. The checkbox defaults to unchecked, so the normal workflow requires no extra interaction.

## RTD Workflow

### 1. Upload Invoice

Upload a Udea invoice PDF through:
- Invoice bulk upload system
- Direct attachment to existing invoice

### 2. Parse Invoice

The system automatically detects supported suppliers and parses them using the appropriate parser:

**Udea** (`invoice_udea.py`):
- Extracts header info (invoice number, date, totals)
- Supports credit notes with negative totals (e.g., returned crates/barrels)
- Extracts line items with article codes
- Identifies barrels (deposits) and costs (freight)
- Classifies items by Gb.rek account codes

**Dynamis** (`invoice_dynamis_rtd.py`):
- Detects invoice type from `Ent:` field (RUNGIS = F&V, MAG = Grocery)
- Extracts EAN barcodes from grocery invoices for direct product lookup
- Generates article codes (DYN-PRODUCT-COUNTRY) for F&V items without EAN
- Extracts transport/freight charges

**Independent Irish Health Foods (IIH)** (`invoice_iih_rtd.py`):
- Extracts VAT summary table directly from invoice (0%, 13.5%, 23%)
- Flexible VAT rate matching — handles non-standard rates (e.g., 22.50% → 23% bucket)
- No article code resolution needed - invoice already has VAT categorization
- Extracts DRS (Deposit Return Scheme) totals from footer
- Subtracts DRS from 0% goods for resale (DRS is excluded)

### 3. Compute RTD

On the RTD Management page (`/rtd`), click **"Compute"** on any invoice to:
1. Resolve each article code to a VAT rate
2. Categorize resolved items into VAT buckets
3. Flag unresolved items as issues
4. Calculate excluded amounts (freight, deposits)

### 4. Resolve Issues

For unresolved article codes:
1. Navigate to **RTD Fallbacks > Unresolved Items**
2. Select items and assign VAT rates
3. Click **"Recompute All Affected"** to update invoices

### 5. Accept & Freeze

When satisfied with the breakdown:
1. Click **"Freeze"** on the RTD Management page
2. System creates immutable snapshot
3. RTD cannot be modified after freezing

## Article Code Resolution

The system resolves article codes in priority order:

### 1. EAN Barcode Lookup (Dynamis Grocery)

```
EAN Barcode (13 digits) → Product (by CODE) → Tax Category → VAT Rate
```

For Dynamis grocery invoices, the article code IS the EAN barcode, enabling direct product lookup.

### 2. SupplierLink + Product (Udea Primary)

```
Article Code → SupplierLink → Product Barcode → Product → Tax Category → VAT Rate
```

This is the preferred method for Udea when products are linked to supplier codes.

### 3. RtdVatFallback (Manual Assignment)

```
Article Code + Supplier → RtdVatFallback → VAT Rate + Non-Retail flag
  → is_non_retail=false → goods_for_resale[rate]
  → is_non_retail=true  → excluded.service_overhead
```

Manual fallback for codes without product links. Useful for:
- Dynamis F&V items (no EAN codes)
- Udea items not in SupplierLink table
- Non-retail items (cleaning supplies, office equipment) — mark as non-retail to exclude from T1

### 4. IIH VAT Summary (Direct)

```
Invoice VAT Summary → goods_for_resale (minus DRS for 0% rate)
```

For IIH invoices, the VAT categorization is already done on the invoice. The system:
1. Extracts the VAT summary table (Tax Code, Rate, Taxable amount)
2. Maps rates to goods_for_resale buckets (0%, 13.5%, 23%)
3. Extracts DRS totals from the invoice footer
4. Subtracts DRS from the 0% goods (DRS is excluded from goods for resale)

### 5. Unresolved

If no method succeeds, the item is marked as unresolved with one of:
- `no_product_match` - No EAN match, SupplierLink, or fallback exists
- `no_tax_category` - Product exists but has no VAT rate
- `invalid_vat_rate` - VAT rate not in valid set (0, 9, 13.5, 23)

## Line Type Classification

Udea invoices classify items by Gb.rek account codes:

| Gb.rek Codes | Category | Treatment |
|--------------|----------|-----------|
| 30252-30382, 30700 | Products | Included in RTD by VAT rate |
| 30862 | Freight/Transport | Excluded (service) |
| 34120 | Barrels/Deposits | Excluded (returnable) |

## User Interface

### RTD Management Page (`/rtd`)

The main RTD dashboard displays:
- **Summary cards**: Total invoices, Needs Parsing, Needs Compute, Has Issues, Frozen
- **Filter options**: Click cards to filter by status
- **Invoice table**: All Udea invoices with RTD status
- **Expandable rows**: Click to see VAT breakdown, excluded items, unresolved items
- **Action buttons**: Parse, Compute, Freeze per invoice
- **Bulk action**: Recompute All with Issues (uses full page reload)

### AJAX-Powered Actions (NEW! 2026-02)

All RTD actions (Parse, Compute, Freeze) are performed via AJAX for improved user experience:

**Features:**
- **Scroll Preservation**: Page position maintained during all operations
- **Per-Row Loading**: Spinner appears on the specific invoice being processed
- **In-Place Updates**: Status badge, issues count, and buttons update without refresh
- **Detail Row Sync**: Expandable breakdown section updates after compute/freeze
- **Flash Messages**: Success/error messages appear at top of page, auto-dismiss after 5 seconds

**PDF Quick View:**
- Purple "PDF" button opens invoice attachment in popup viewer
- Uses minimal viewer (900×1000 window) for quick document reference
- Only appears when PDF exists on disk

**JavaScript Functions:**

| Function | Purpose |
|----------|---------|
| `rtdAction(id, action, text)` | Submits AJAX request, shows loading |
| `updateRowStatus(id, data)` | Updates row after successful action |
| `updateDetailRow(id, data)` | Rebuilds detail section content |
| `showFlashMessage(msg, type)` | Displays success/error notification |
| `viewInvoicePdf(id)` | Opens PDF in popup viewer |
| `toggleForceReparseMode()` | Toggle force reparse mode on/off |
| `updateForceReparseUI()` | Show/hide force reparse buttons |

### Force Reparse Mode (NEW! 2026-02)

Allows re-parsing any invoice with the latest RTD parser, bypassing normal validation checks.

**Purpose:**
- Re-parse invoices that were parsed with legacy parsers (e.g., missing DRS data)
- Fix parsing issues by re-running with updated parser code
- Override the `canReparseForRtd()` check when needed

**How to Use:**
1. Click the **gear icon** in the filter/actions bar
2. Enable **"Force Reparse Mode"** toggle
3. Orange banner appears: "Force Reparse Mode Active"
4. **"Reparse"** buttons appear on all invoices with PDFs (except frozen)
5. Click **"Reparse"** on any invoice to force re-parse
6. Click **"Compute"** to recalculate RTD with new parsed data

**Behavior:**
- Bypasses `canReparseForRtd()` validation
- Deletes existing parsed data for the supplier
- Creates fresh upload file and parses PDF with latest RTD parser
- Clears existing RTD breakdown (requires Compute to recalculate)
- State persisted in localStorage across browser sessions

**Routes:**

| Method | Route | Action |
|--------|-------|--------|
| POST | `/rtd/{invoice}/force-parse` | Force parse invoice PDF |

### Detail Row Reconciliation Layout (NEW! 2026-02)

The expandable detail row uses a 4-column layout showing how amounts reconcile:

| Column | Description | Color Theme |
|--------|-------------|-------------|
| **Goods for Resale** | VAT rates (0%, 9%, 13.5%, 23%) with subtotal | Green |
| **Excluded** | Freight, deposits, DRS, VAT, and non-retail items with subtotal | Blue |
| **Unresolved** | Items needing resolution with subtotal | Red (or gray if none) |
| **Reconciliation** | Shows how totals add up to invoice total | Green/Yellow border |

**Reconciliation Column Shows:**
```
+ Goods:       1,234.56
+ Excluded:       45.00
+ Unresolved:     67.89
─────────────────────────
= Calculated:  1,347.45
Invoice Total: 1,347.45
         ✓ Balanced
```

**Balance Indicator:**
- **Green border**: Calculated total matches invoice total (within €0.50 tolerance)
- **Yellow border**: Discrepancy detected, shows difference amount

### Invoice Show Page

For Udea invoices, shows a simple link to RTD Management with current status badge.

### RTD Year Report Page (`/rtd/year-report`)

Aggregated RTD totals from frozen invoices for VAT reporting.

**Features:**
- Date range selection with year quick-select dropdown
- Aggregated goods for resale totals by VAT rate (0%, 9%, 13.5%, 23%)
- Grand total of all goods for resale
- Warning panel listing non-frozen invoices in the period (not included in totals)
- Detailed table of all frozen invoices with per-invoice RTD breakdown
- Excluded amounts summary (freight, deposits)

**Non-Frozen Invoices Warning (Updated 2026-02):**
- Orange-themed warning box with high-contrast text for readability
- Lists all invoices in the period that haven't been frozen yet
- Status badges (Computed/Pending) with proper styling
- Direct "View in RTD" links for quick access to resolve

**Use Cases:**
- Preparing VAT return data for a specific period
- Verifying all invoices in a period are frozen before reporting
- Auditing RTD totals by VAT rate

### RTD Issues Queue (`/rtd/issues`)

Work queue for invoices with unresolved RTD items.

**Features:**
- Lists all non-frozen invoices with unresolved items
- Summary stats: total invoices with issues, total unresolved count, total unresolved value
- Per-invoice details: unresolved count, unresolved value, top issue article codes
- Quick actions: Recompute, View Invoice
- Links to Manage Fallbacks for bulk resolution

**Workflow Tip:**
To efficiently resolve issues:
1. Navigate to "Manage Fallbacks" to assign VAT rates to unrecognized codes
2. Return to Issues Queue and click "Recompute" on affected invoices
3. Once resolved, go to RTD Management and "Freeze" the invoice

### RTD Fallbacks Page

**Index** (`/rtd-fallbacks`):
- Lists all manual VAT rate assignments
- Yellow "Non-retail" badge shown on entries marked as non-retail
- Edit modal with VAT rate, non-retail checkbox, description, and notes
- Delete individual entries

**Unresolved Items** (`/rtd-fallbacks/unresolved`):
- Shows all unresolved article codes across invoices
- Bulk assignment of VAT rates with optional "Non-retail" checkbox (unchecked by default)
- Already-assigned non-retail items show yellow badge (e.g., "23% non-retail")
- Recompute affected invoices

## Routes

| Method | Route | Action |
|--------|-------|--------|
| GET | `/rtd` | RTD Management dashboard |
| GET | `/rtd/year-report` | Year Report with aggregated totals |
| GET | `/rtd/issues` | Issues Queue - invoices needing attention |
| POST | `/rtd/{invoice}/parse` | Parse invoice PDF |
| POST | `/rtd/{invoice}/force-parse` | Force parse invoice PDF (bypasses checks) |
| POST | `/rtd/{invoice}/compute` | Compute invoice RTD |
| POST | `/rtd/{invoice}/accept` | Freeze invoice RTD |
| POST | `/rtd/recompute-all` | Recompute all with issues |
| GET | `/rtd-fallbacks` | List all fallbacks |
| GET | `/rtd-fallbacks/unresolved` | Show unresolved items |
| POST | `/rtd-fallbacks/bulk-assign` | Bulk create/update fallbacks |
| POST | `/rtd-fallbacks/recompute-affected` | Recompute all affected invoices |
| PUT | `/rtd-fallbacks/{id}` | Update single fallback |
| DELETE | `/rtd-fallbacks/{id}` | Delete fallback |

## Key Service Methods

### RtdResolutionService

| Method | Purpose |
|--------|---------|
| `computeRtd($invoice)` | Calculate full RTD breakdown |
| `resolveArticleCode($code, $supplier)` | Resolve single article code (returns `is_non_retail` for fallback source) |
| `freezeRtd($invoice, $userId)` | Create immutable snapshot |
| `getSourceUploadFile($invoice)` | Get parsed data file |
| `parseMonetaryValue($value)` | Convert EU/US number formats |

### RtdVatFallback Model

| Method | Purpose |
|--------|---------|
| `findFallback($code, $supplierId)` | Returns `['vat_rate' => float, 'is_non_retail' => bool]` or `null` |
| `findVatRate($code, $supplierId)` | Thin wrapper — returns just the VAT rate float or `null` |

### Invoice Model Methods

| Method | Purpose |
|--------|---------|
| `hasRtdData()` | Check if RTD breakdown exists |
| `canComputeRtd()` | Check if parsing data available |
| `canReparseForRtd()` | Check if PDF exists on disk and can be re-parsed |
| `canModifyRtd()` | Check if not frozen |
| `isRtdComplete()` | Check if no unresolved items |
| `getRtdTotal()` | Sum of all goods for resale |
| `isUdeaSupplier()` | Check if supplier is Udea |
| `isDynamisSupplier()` | Check if supplier is Dynamis |
| `isIndependentSupplier()` | Check if supplier is IIH |
| `hasRtdParser()` | Check if Udea, Dynamis, or Independent (has RTD parser) |
| `hasPdfOnDisk()` | Check if PDF file actually exists on disk |

## Troubleshooting

### RTD Section Not Appearing

**Cause:** Invoice doesn't meet display conditions.

**Check:**
1. Is this a supported supplier? (`hasRtdParser()` - Udea or Dynamis)
2. Is there a PDF attachment on disk? (`hasPdfOnDisk()`)
3. Has the PDF been parsed? (`canComputeRtd()`)

**Solution:** Use "Parse for RTD" button if available, or upload a PDF attachment.

### "PDF Missing" Status Displayed

**Cause:** Invoice has an attachment record in the database but the actual PDF file is missing from disk.

**Solution:**
1. Re-upload the PDF via the invoice detail page
2. Or delete the orphaned attachment record

### "Parse for RTD" Button Not Appearing

**Cause:** Conditions for re-parsing not met.

**Check:**
1. Supplier name contains "Udea" or "Dynamis"
2. PDF file exists on disk (not just database record)
3. No existing parsed line data

### Many Unresolved Items

**Cause:** Article codes not linked to products or fallbacks.

**Solutions:**
1. Create SupplierLinks for frequently used codes
2. Use RTD Fallbacks page for bulk VAT assignment
3. After adding fallbacks, click "Recompute All Affected"

### RTD Total Doesn't Match Invoice

**Cause:** Some items excluded or unresolved.

**Check:**
- Excluded section shows freight/deposits
- Unresolved section shows items needing resolution

### Cannot Modify RTD

**Cause:** RTD has been frozen.

**Solution:** Frozen RTD cannot be changed. This is by design for audit compliance.

## File Locations

| Component | Path |
|-----------|------|
| RTD Controller | `app/Http/Controllers/RtdController.php` |
| RTD Submission Model | `app/Models/RtdSubmission.php` |
| RTD Submission Controller | `app/Http/Controllers/RtdSubmissionController.php` |
| RTD Service | `app/Services/RtdResolutionService.php` |
| Fallback Controller | `app/Http/Controllers/RtdFallbackController.php` |
| Fallback Model | `app/Models/RtdVatFallback.php` |
| Invoice Model | `app/Models/Invoice.php` |
| Udea Parser | `scripts/invoice-parser/parsers/invoice_udea.py` |
| Dynamis RTD Parser | `scripts/invoice-parser/parsers/invoice_dynamis_rtd.py` |
| IIH RTD Parser | `scripts/invoice-parser/parsers/invoice_iih_rtd.py` |
| RTD Dashboard View | `resources/views/rtd/index.blade.php` |
| RTD Year Report View | `resources/views/rtd/year-report.blade.php` |
| RTD Issues View | `resources/views/rtd/issues.blade.php` |
| Fallback Views | `resources/views/rtd-fallbacks/` |
| Migrations | `database/migrations/2026_01_31_*.php` |

## Related Documentation

- [Invoice Parser Integration](./invoice-parser-integration.md)
- [Udea Invoice Parser](./udea-invoice-parser.md)
- [VAT Returns](./vat-returns.md)
