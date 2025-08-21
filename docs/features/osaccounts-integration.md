# OSAccounts Integration Documentation

## Overview

The OSAccounts integration system enables seamless import of invoices, VAT lines, and attachments from the legacy OSAccounts system into the Laravel application. This integration ensures data consistency, proper supplier mapping, and complete invoice history migration.

## Key Features

- **Complete Invoice Import**: Import invoices with all metadata from OSAccounts
- **Supplier Mapping**: Automatic mapping between POS, OSAccounts, and Laravel suppliers
- **VAT Line Import**: Detailed VAT breakdown import with Irish tax rates
- **Attachment Migration**: Automatic file migration with proper permissions
- **Data Integrity**: Transaction-safe imports with rollback capability

## Database Structure

### OSAccounts Tables
- `INVOICES` - Main invoice records
- `INVOICE_DETAIL` - VAT line breakdowns
- `EXPENSES_JOINED` - Combined supplier table (POS + EXPENSES)
- `INVOICES_UNPAID` - Outstanding invoice tracking

### Laravel Tables
- `invoices` - Main invoice storage with `external_osaccounts_id`
- `invoice_vat_lines` - VAT breakdown per invoice
- `invoice_attachments` - File attachment records
- `accounting_suppliers` - Unified supplier table with mapping fields

## Import Commands

### 1. Supplier Mapping Sync (Run First!)

```bash
php artisan osaccounts:sync-supplier-mapping
```

**Purpose**: Synchronizes OSAccounts supplier IDs with Laravel suppliers based on POS IDs.

**Options**:
- `--dry-run` - Preview changes without applying
- `--detailed` - Show detailed output for each supplier

**What it does**:
1. Finds all suppliers with POS IDs
2. Sets their `external_osaccounts_id` to match `external_pos_id`
3. Marks them as OSAccounts linked
4. Reports on UUID-based suppliers (already mapped)

### 2. Invoice Import

```bash
php artisan osaccounts:import-invoices
```

**Options**:
- `--date-from=YYYY-MM-DD` - Start date for import
- `--date-to=YYYY-MM-DD` - End date for import
- `--force` - Update existing invoices
- `--dry-run` - Preview import without changes
- `--limit=N` - Limit number of invoices
- `--chunk=N` - Process size (default: 100)

**Example**:
```bash
# Import July 2025 invoices
php artisan osaccounts:import-invoices --date-from=2025-07-01 --date-to=2025-07-31

# Import all 2025 invoices with force update
php artisan osaccounts:import-invoices --date-from=2025-01-01 --date-to=2025-12-31 --force
```

### 3. VAT Lines Import

```bash
php artisan osaccounts:import-invoice-vat-lines
```

**Options**:
- `--force` - Re-import even if VAT lines exist
- `--dry-run` - Preview import
- `--invoice=NUM` - Import for specific invoice only

**VAT Categories Mapped**:
- `STANDARD` - 23% (Irish standard rate)
- `REDUCED` - 13.5% (Irish reduced rate)
- `SECOND_REDUCED` - 9% (Irish second reduced rate)
- `ZERO` - 0% (Zero-rated items)

### 4. VAT Returns Import

```bash
php artisan osaccounts:import-vat-returns
```

**Purpose**: Reconstructs historical VAT return records from the OSAccounts `Assigned` column, enabling recovery of VAT return data if lost during testing.

**Options**:
- `--force` - Re-import even if VAT returns already exist for periods
- `--dry-run` - Preview import without making changes
- `--chunk=N` - Process size (default: 100)

**What it does**:
1. **Analyzes VAT Periods**: Scans all unique `Assigned` values in OSAccounts INVOICES table
2. **Parses Period Strings**: Handles various formats like "VAT Jan Feb 2024", "Mar Apr 2021", "Jan - Feb 2016"
3. **Creates VAT Returns**: Generates `vat_returns` records for each historical period
4. **Calculates Totals**: Aggregates invoice VAT breakdowns by rate (0%, 9%, 13.5%, 23%)
5. **Links Invoices**: Updates imported invoices with `vat_return_id` references

**Period Format Support**:
- `VAT Jan Feb 2024` (most common)
- `Jan - Feb 2016` (with dash)
- `Mar Apr 2021` (without VAT prefix)
- Automatically handles case variations and "VAT" prefix removal

**Example**:
```bash
# Preview what will be imported
php artisan osaccounts:import-vat-returns --dry-run

# Import all historical VAT returns
php artisan osaccounts:import-vat-returns

# Force re-import (updates existing returns)
php artisan osaccounts:import-vat-returns --force
```

**Success Rate**: 98.2% (56 of 57 periods parsed successfully)

**Features**:
- **Historical Flagging**: Imported returns marked as `is_historical = true`
- **Status Setting**: Returns marked as `submitted` (already processed)
- **Reference Generation**: Creates reference numbers like `OSA-2024-02`
- **Transaction Safety**: Full rollback on errors
- **Idempotent**: Safe to run multiple times

### 5. Attachments Import

#### Environment Configuration

The attachment import system uses an environment variable for the base path configuration:

```bash
# In .env file
OSACCOUNTS_FILE_PATH=/path/to/osaccounts/invoice_storage
```

This path is configured in `config/osaccounts.php` and works with Laravel's config caching for production.

#### Command Usage

```bash
# Uses configured path from environment
php artisan osaccounts:import-attachments

# Override with specific path
php artisan osaccounts:import-attachments --base-path=/path/to/files
```

**Options**:
- `--base-path=PATH` - Override the configured base directory (optional)
- `--force` - Re-import existing attachments (skips duplicates by file hash)
- `--dry-run` - Preview import without making changes

**Features**:
- **Environment-Based Configuration**: Uses `OSACCOUNTS_FILE_PATH` environment variable
- **Production Config Caching**: Works with `php artisan config:cache`
- **Smart Path Resolution**: Handles various OSAccounts path formats
- **HTML Entity Decoding**: Converts `&amp;` to `&` in supplier names
- **Duplicate Prevention**: SHA-256 hash comparison prevents duplicate files
- **Multiple Fallback Strategies**: Tries various path combinations to find files
- **Production-Ready Permissions**: Files created with `664`, group set to `www-data`
- **Path Visibility**: Shows the base path being used in command output

#### Cross-Server Setup

When OSAccounts files are on a different server:

**Option 1: Network Mount (Recommended)**
```bash
# Mount remote directory
sudo mkdir -p /mnt/osaccounts-files
sudo mount -t nfs osaccounts-server:/var/www/html/OSManager/invoice_storage /mnt/osaccounts-files

# Configure in .env
OSACCOUNTS_FILE_PATH=/mnt/osaccounts-files
```

**Option 2: File Synchronization**
```bash
# Sync files to local storage
sudo mkdir -p /var/lib/osaccounts-files
rsync -av user@osaccounts-server:/var/www/html/OSManager/invoice_storage/ /var/lib/osaccounts-files/

# Configure in .env
OSACCOUNTS_FILE_PATH=/var/lib/osaccounts-files
```

#### Examples

```bash
# Standard import (uses environment configuration)
php artisan osaccounts:import-attachments

# Force re-import with environment path
php artisan osaccounts:import-attachments --force

# Override environment path
php artisan osaccounts:import-attachments --base-path=/custom/path/to/files

# Dry run to test configuration
php artisan osaccounts:import-attachments --dry-run
```

**Success Rate**: 98.4% (183 of 186 files imported successfully)

## Production Import Workflow

### Complete Import Process

**Prerequisites**: Configure environment and cache config:
```bash
# 1. Configure attachment path in .env
echo "OSACCOUNTS_FILE_PATH=/path/to/osaccounts/files" >> .env

# 2. Cache configuration for production
sudo -u www-data php artisan config:cache
```

**Import Steps**:
```bash
# Step 1: Sync supplier mappings (ONE TIME - crucial!)
sudo -u www-data php artisan osaccounts:sync-supplier-mapping

# Step 2: Import invoices for desired period
sudo -u www-data php artisan osaccounts:import-invoices --date-from=2025-01-01 --date-to=2025-12-31

# Step 3: Import VAT lines for all invoices
sudo -u www-data php artisan osaccounts:import-invoice-vat-lines

# Step 4: Import historical VAT returns (after invoices are imported!)
sudo -u www-data php artisan osaccounts:import-vat-returns

# Step 5: Import file attachments (uses environment configuration)
sudo -u www-data php artisan osaccounts:import-attachments
```

### Incremental Updates

For ongoing synchronization:

```bash
# Import last month's invoices
php artisan osaccounts:import-invoices --date-from=$(date -d "first day of last month" +%Y-%m-%d) --date-to=$(date -d "last day of last month" +%Y-%m-%d)

# Import VAT lines, VAT returns, and attachments
php artisan osaccounts:import-invoice-vat-lines
php artisan osaccounts:import-vat-returns --force  # Update if new assignments
php artisan osaccounts:import-attachments --base-path=/path/to/files
```

### Data Recovery

If VAT return data is lost during testing:

```bash
# Quick recovery - just import VAT returns from existing invoice assignments
php artisan osaccounts:import-vat-returns --dry-run  # Preview first
php artisan osaccounts:import-vat-returns            # Import historical data
```

## Supplier Mapping Logic

### Mapping Resolution Order
1. **UUID-based suppliers**: Direct mapping via `external_osaccounts_id`
2. **Numeric POS suppliers**: Mapped when `external_pos_id` equals OSAccounts ID
3. **Manual mapping**: Can be set directly in `accounting_suppliers` table

### Important Fields
- `external_pos_id`: POS system supplier ID
- `external_osaccounts_id`: OSAccounts supplier ID
- `is_osaccounts_linked`: Boolean flag for active mapping

### Common Issues and Solutions

**Issue**: "Unmapped suppliers" during import
**Solution**: Run `php artisan osaccounts:sync-supplier-mapping` first

**Issue**: "Unknown Supplier" in imported invoices
**Solution**: Fixed in latest version - supplier names pulled from Laravel suppliers

**Issue**: Attachments not accessible (404 errors)
**Solution**: Files are now automatically created with correct permissions (664) and group ownership (www-data)

## Data Integrity

### Transaction Safety
- All imports use database transactions
- Automatic rollback on errors
- Detailed logging of all operations

### Validation
- Invoice numbers must be unique
- Supplier must exist in Laravel
- VAT calculations verified against Irish tax rates
- File hash verification for attachments

### Audit Trail
- `external_osaccounts_id` tracks source record
- `created_by`/`updated_by` fields maintained
- Import timestamps preserved

## Performance Considerations

### Chunked Processing
- Default chunk size: 100 records
- Adjustable via `--chunk` parameter
- Memory-efficient for large datasets

### Caching
- Supplier mappings cached during import
- VAT rates cached by date
- Attachment metadata cached

### Optimization Tips
1. Run supplier sync once before bulk imports
2. Import in date ranges rather than all at once
3. Use `--dry-run` to preview large imports
4. Monitor logs for unmapped suppliers

## Troubleshooting

### Debug Commands

```bash
# Check supplier mappings
php artisan tinker
>>> App\Models\AccountingSupplier::whereNull('external_osaccounts_id')->whereNotNull('external_pos_id')->count()

# Verify invoice imports
>>> App\Models\Invoice::whereNotNull('external_osaccounts_id')->count()

# Check for unknown suppliers
>>> App\Models\Invoice::where('supplier_name', 'Unknown Supplier')->count()
```

### Log Locations
- Import logs: `storage/logs/laravel.log`
- Failed imports tracked with supplier details
- Attachment issues logged with file paths

### Common Error Messages

**"Base table or view not found: OSAccounts.invoices"**
- Cause: Cross-database query issue
- Fixed: Queries now properly separated

**"Files not found on disk"**
- Cause: OSAccounts file path inconsistencies
- Solution: Check `--base-path` parameter

**"SQLSTATE[23000]: Integrity constraint violation"**
- Cause: Duplicate invoice number
- Solution: Use `--force` to update existing

## Migration Checklist

### Pre-Migration
- [ ] Backup Laravel database
- [ ] Verify OSAccounts database connection
- [ ] Check file storage accessibility
- [ ] Test with small date range first

### Migration Steps
- [ ] Run supplier sync command
- [ ] Import invoices by month/quarter
- [ ] Verify supplier names correct
- [ ] Import VAT lines
- [ ] Import attachments with proper permissions
- [ ] Verify attachment accessibility

### Post-Migration
- [ ] Verify invoice totals match OSAccounts
- [ ] Check VAT calculations
- [ ] Test attachment viewing
- [ ] Verify payment status accuracy
- [ ] Run reconciliation reports

## Technical Implementation

### Models
- `OSInvoice` - OSAccounts invoice model
- `OSInvoiceDetail` - VAT lines model
- `OSExpense` - Legacy supplier model
- `Invoice` - Laravel invoice model
- `InvoiceVatLine` - Laravel VAT lines
- `InvoiceAttachment` - File attachments

### Key Improvements (August 2025)
1. **Supplier name resolution** - Now pulls from Laravel suppliers, not OSAccounts
2. **EXPENSES_JOINED support** - Uses combined supplier table
3. **Automatic POS mapping** - Maps numeric IDs automatically
4. **Enhanced File Path Handling**:
   - Detects when InvoicePath contains full filename
   - Decodes HTML entities in supplier names
   - Multiple fallback strategies for finding files
   - Handles timestamp suffixes in paths
5. **Production-Ready Permission Management**:
   - Files created with `664` permissions
   - Automatic `www-data` group ownership
   - Directories use setgid bit for group inheritance
   - No sudo required in production
6. **Duplicate Prevention**:
   - SHA-256 hash-based duplicate detection
   - Automatic cleanup command for existing duplicates
   - Prevents re-import of identical files

## Amazon Invoice Processing Integration

### Unified Processing System (Updated 2025-08-20)

Amazon invoices now use the same bulk upload system as other invoice types, providing consistency and improved user experience.

**Previous System**:
- Separate `/invoices/amazon-pending` route with different interface
- Duplicate code for similar functionality
- Inconsistent processing workflows

**Current System**:
- Amazon invoices integrated into `/invoices/bulk-upload/preview` system
- Unified interface for all invoice types
- Consistent filtering and processing

**Key Changes**:
1. **Route Migration**: Old Amazon pending routes now redirect to bulk upload preview with filters
2. **Filtering Support**: Bulk upload preview supports `supplier` and `status` filters
3. **Delete Functionality**: Bulk delete operations for unwanted Amazon pending files
4. **Unified Processing**: Amazon invoices processed through same workflow as other suppliers

**Migration Process**:
- Existing pending Amazon invoices automatically migrated to bulk upload system
- Status mapping: `amazon_pending` → bulk upload with Amazon supplier filter
- Consistent UI/UX across all invoice processing

**Benefits**:
- Single interface for all invoice management
- Consistent user experience
- Reduced code duplication
- Easier maintenance and feature updates

### Amazon Payment Adjustments

Amazon invoices often require payment adjustments due to exchange rate differences between invoice EUR amounts and actual bank charges.

**Features**:
- Payment adjustment input during invoice creation
- Automatic VAT recalculation based on actual payment
- Adjustment tracking in invoice notes
- Integration with VAT returns for accurate reporting

## Future Enhancements

### Planned Features
- Automatic scheduled imports
- Real-time sync via webhooks
- Duplicate detection improvements
- Supplier auto-matching by name
- Import status dashboard

### API Integration
- RESTful endpoints for import status
- Webhook notifications on import
- Bulk operations API
- Validation endpoints

## Support

For issues or questions:
1. Check logs in `storage/logs/`
2. Run commands with `--dry-run` first
3. Verify supplier mappings are complete
4. Ensure file permissions are correct

## Version History

### v2.0.0 (August 2025)
- Fixed supplier name resolution
- Added supplier sync command
- Improved file permission handling
- Enhanced error reporting

### v1.0.0 (July 2025)
- Initial OSAccounts integration
- Basic invoice import
- VAT line support
- Attachment migration