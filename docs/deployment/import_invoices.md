# Production Invoice Import Guide

This document provides the complete strategy for safely importing invoices from OSAccounts to the production Laravel application.

## Overview

The import process involves transferring invoice data from the legacy OSAccounts system into the new Laravel-based VAT management system. This guide ensures a safe, incremental approach with proper backup and rollback procedures.

## Prerequisites

- Production server access with appropriate permissions
- OSAccounts database credentials (read-only recommended)
- Sufficient disk space (at least 2x current database size)
- Maintenance window planned for import execution

## 🔄 Phase 1: Database Setup & Testing

### 1. Create OSAccounts Connection on Production

Update production `.env` file with OSAccounts database credentials:

```bash
# Add to production .env
OSACCOUNTS_DB_CONNECTION=mysql
OSACCOUNTS_DB_HOST=your-production-host
OSACCOUNTS_DB_PORT=3306
OSACCOUNTS_DB_DATABASE=osaccounts
OSACCOUNTS_DB_USERNAME=readonly_user  # Use read-only user for safety
OSACCOUNTS_DB_PASSWORD=secure_password
```

### 2. Test Connection

Verify the database connection works:

```bash
php artisan tinker
# Test connection:
DB::connection('osaccounts')->select('SELECT COUNT(*) FROM INVOICES');
# Should return invoice count without errors
```

## 🧪 Phase 2: Dry Run & Validation

### 3. Dry Run Import (Safe - No Changes Made)

Test the import process without making any database changes:

```bash
# Test import with small dataset
php artisan osaccounts:import-invoices --dry-run --limit=100

# Check specific date range if needed
php artisan osaccounts:import-invoices --dry-run --date-from=2024-01-01 --limit=500
```

Expected output should show:
- Number of invoices found
- Supplier mapping status
- Any potential issues or unmapped suppliers

### 4. Supplier Mapping Check

Ensure all suppliers are properly mapped before importing invoices:

```bash
# Check supplier mapping first
php artisan osaccounts:sync-suppliers --dry-run

# If needed, run the actual sync
php artisan osaccounts:sync-suppliers
```

## 📦 Phase 3: Incremental Import

### 5. Start with Recent Data (Recommended)

Begin with recent data to test the process with a smaller, more manageable dataset:

```bash
# Import last 3 months first
php artisan osaccounts:import-invoices --date-from=2024-06-01 --chunk=50

# Verify results in VAT dashboard before continuing
# Navigate to /management/vat-dashboard to check data
```

Benefits of starting with recent data:
- Smaller dataset is easier to troubleshoot
- More likely to catch supplier mapping issues
- Quicker to verify in the dashboard
- Can rollback more easily if issues arise

### 6. Historical Data Import

After confirming recent data import works correctly:

```bash
# Import historical data in chunks
php artisan osaccounts:import-invoices --date-from=2024-01-01 --date-to=2024-05-31 --chunk=100

# For very large datasets, consider monthly imports:
php artisan osaccounts:import-invoices --date-from=2024-01-01 --date-to=2024-01-31 --chunk=100
php artisan osaccounts:import-invoices --date-from=2024-02-01 --date-to=2024-02-29 --chunk=100
# Continue for each month...
```

## 🏷️ Phase 4: VAT Returns Import

### 7. Import Historical VAT Assignments

After all invoices are imported, link them to historical VAT returns:

```bash
# Dry run first to see what will be imported
php artisan vat-returns:import-historical --dry-run

# Run the actual import
php artisan vat-returns:import-historical
```

This command will:
- Extract VAT periods from invoice notes
- Create historical VAT returns
- Link invoices to appropriate VAT periods
- Calculate VAT totals for each return

## 🚨 Safety Measures

### Before Starting Import

**1. Backup Production Database**
```bash
# Create timestamped backup
mysqldump osmanager_production > backup_pre_import_$(date +%Y%m%d_%H%M%S).sql

# Verify backup was created successfully
ls -la backup_pre_import_*.sql
```

**2. Enable Maintenance Mode**
```bash
php artisan down --message="Importing invoice data - will be back shortly"
```

**3. Monitor System Resources**
```bash
# Check available disk space
df -h

# Check memory usage
free -h

# Ensure sufficient resources before starting
```

### During Import

**Monitor Progress:**
```bash
# Watch running processes
watch -n 5 'ps aux | grep artisan'

# Monitor disk space usage
watch -n 10 'df -h'

# Monitor system memory
watch -n 10 'free -h'
```

**Check for Errors:**
```bash
# Monitor Laravel logs in real-time
tail -f storage/logs/laravel.log

# Check for any database errors
tail -f /var/log/mysql/error.log  # Adjust path as needed
```

### After Import

**1. Verify Data Integrity**
```bash
php artisan tinker
# Check record counts
Invoice::count()
VatReturn::count()
Invoice::whereNull('vat_return_id')->count()  # Should show unassigned invoices
```

**2. Test Dashboard Functionality**
- Navigate to `/management/vat-dashboard`
- Verify outstanding periods show correctly
- Check recent submissions display properly
- Test VAT return creation with a small test period

**3. Re-enable Site**
```bash
php artisan up
```

## ⚡ Performance Optimization

### For Large Datasets

**Use Appropriate Chunk Sizes:**
```bash
# For systems with limited memory, use smaller chunks
php artisan osaccounts:import-invoices --chunk=25 --date-from=2024-01-01

# For systems with more resources, larger chunks are more efficient
php artisan osaccounts:import-invoices --chunk=200 --date-from=2024-01-01
```

**Long-Running Imports:**
```bash
# Use screen or tmux for long-running imports
screen -S invoice-import
php artisan osaccounts:import-invoices --chunk=50

# Detach from screen: Ctrl+A, then D
# Reattach later: screen -r invoice-import
```

**Optimal Timing:**
- Run during low-traffic hours (typically early morning)
- Avoid peak business hours
- Consider weekend maintenance windows for large imports

## 🔧 Troubleshooting

### Common Issues and Solutions

**1. Import Fails Partway Through**
```bash
# Resume from a specific date using --force
php artisan osaccounts:import-invoices --force --date-from=2024-06-15
```

**2. Check for Data Issues**
```bash
php artisan tinker
# Check for invoices without suppliers
Invoice::whereNull('supplier_id')->count()

# Check for invoices with invalid dates
Invoice::whereNull('invoice_date')->count()
```

**3. Memory Issues**
```bash
# Reduce chunk size
php artisan osaccounts:import-invoices --chunk=10

# Clear Laravel caches
php artisan cache:clear
php artisan config:clear
```

**4. Database Connection Issues**
```bash
# Test OSAccounts connection
php artisan tinker
DB::connection('osaccounts')->getPdo()

# Check Laravel database connection
DB::connection()->getPdo()
```

### Recovery Procedures

**If Import Fails and Needs Complete Reset:**

⚠️ **WARNING: This will delete ALL imported invoice data**

```bash
# Only do this if starting completely over
php artisan down
# Restore from backup
mysql osmanager_production < backup_pre_import_YYYYMMDD_HHMMSS.sql
php artisan up
```

**Partial Rollback:**
```bash
# Remove invoices from a specific date range
Invoice::whereBetween('invoice_date', ['2024-06-01', '2024-06-30'])->delete();
```

## 📋 Production Import Checklist

### Pre-Import Checklist
- [ ] OSAccounts database connection configured and tested
- [ ] Database backup completed and verified
- [ ] Dry run completed successfully with expected results
- [ ] Supplier mapping verified and synchronized
- [ ] Disk space sufficient (minimum 2x current database size)
- [ ] System resources adequate (memory, CPU)
- [ ] Maintenance window scheduled and communicated
- [ ] Rollback procedures documented and understood

### Import Execution Checklist
- [ ] Maintenance mode enabled
- [ ] Recent data import completed (last 3-6 months)
- [ ] Recent data verified in VAT dashboard
- [ ] Historical data import completed
- [ ] VAT returns imported and linked
- [ ] Data integrity checks passed
- [ ] Dashboard functionality verified
- [ ] Performance acceptable

### Post-Import Checklist
- [ ] All import statistics reviewed and acceptable
- [ ] VAT dashboard shows correct outstanding periods
- [ ] Recent submissions display properly
- [ ] Test VAT return creation works
- [ ] User acceptance testing completed
- [ ] Maintenance mode disabled
- [ ] Success confirmation documented
- [ ] Backup of post-import state created

## 🎯 Recommended Timeline

### Week 1: Preparation and Testing
- Set up OSAccounts connection
- Run comprehensive dry runs
- Test with small datasets
- Verify supplier mapping
- Document any issues found

### Week 2: Recent Data Import
- Import last 3-6 months of data
- Verify data accuracy in dashboard
- Test VAT return functionality
- Address any issues found

### Week 3: Historical Data Import
- Import remaining historical data
- Import and link VAT returns
- Complete data integrity checks
- Performance testing

### Week 4: Verification and Go-Live
- Final user acceptance testing
- Documentation updates
- Training for end users
- Production go-live
- Post-go-live monitoring

## Command Reference

### Import Commands

```bash
# Basic invoice import
php artisan osaccounts:import-invoices

# Import with options
php artisan osaccounts:import-invoices --dry-run --chunk=100 --limit=1000

# Date range import
php artisan osaccounts:import-invoices --date-from=2024-01-01 --date-to=2024-12-31

# VAT returns import
php artisan vat-returns:import-historical --dry-run
php artisan vat-returns:import-historical --force

# Supplier synchronization
php artisan osaccounts:sync-suppliers --dry-run
php artisan osaccounts:sync-suppliers
```

### Monitoring Commands

```bash
# Check import progress
php artisan tinker
# Invoice::count()
# Invoice::whereDate('created_at', today())->count()

# System monitoring
df -h                    # Disk space
free -h                  # Memory usage
ps aux | grep artisan   # Running processes
tail -f storage/logs/laravel.log  # Application logs
```

### Maintenance Commands

```bash
# Maintenance mode
php artisan down --message="Maintenance in progress"
php artisan up

# Cache management
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Database backup
mysqldump osmanager_production > backup_$(date +%Y%m%d_%H%M%S).sql
```

## Support and Documentation

### Related Documentation
- [VAT Dashboard](../features/vat-dashboard.md) - Understanding the VAT management system
- [OSAccounts Integration](../features/osaccounts-integration.md) - Technical integration details
- [Cash Reconciliation](../features/cash-reconciliation.md) - Related financial management

### Getting Help
- Check `storage/logs/laravel.log` for detailed error messages
- Use `--dry-run` flag to test commands without making changes
- Start with small datasets and gradually increase scope
- Document any issues encountered for future reference

---

*Last Updated: 2025-08-12*
*Document Version: 1.0*