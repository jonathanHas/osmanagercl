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

### 4. Attachments Import

```bash
php artisan osaccounts:import-attachments --base-path=/path/to/files
```

**Options**:
- `--base-path=PATH` - Base directory where OSAccounts files are stored (required)
- `--force` - Re-import existing attachments
- `--dry-run` - Preview import

**Example**:
```bash
# Import with proper permissions
umask 002 && php artisan osaccounts:import-attachments --base-path=/var/www/html/OSManager/invoice_storage
```

## Production Import Workflow

### Complete Import Process

```bash
# Step 1: Sync supplier mappings (ONE TIME - crucial!)
php artisan osaccounts:sync-supplier-mapping

# Step 2: Import invoices for desired period
php artisan osaccounts:import-invoices --date-from=2025-01-01 --date-to=2025-12-31

# Step 3: Import VAT lines for all invoices
php artisan osaccounts:import-invoice-vat-lines

# Step 4: Import file attachments
umask 002 && php artisan osaccounts:import-attachments --base-path=/path/to/invoice/storage
```

### Incremental Updates

For ongoing synchronization:

```bash
# Import last month's invoices
php artisan osaccounts:import-invoices --date-from=$(date -d "first day of last month" +%Y-%m-%d) --date-to=$(date -d "last day of last month" +%Y-%m-%d)

# Import VAT lines and attachments
php artisan osaccounts:import-invoice-vat-lines
php artisan osaccounts:import-attachments --base-path=/path/to/files
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
**Solution**: Use `umask 002` when importing to ensure group-readable files

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
4. **File path handling** - Handles OSAccounts path inconsistencies
5. **Permission management** - Ensures web server file access

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