# Amazon Invoice Permission Issues - Troubleshooting Guide

This document covers the permission-related issues encountered with Amazon invoices in the bulk upload system and their solutions.

## Overview

Amazon invoices in the bulk upload system face unique permission challenges due to the dual-user execution model:
- **Queue workers** run as the `jon` user (processing uploads and creating invoices)
- **Web server** runs as the `www-data` user (serving attachment files to browsers)

This dual-user model creates permission conflicts when files/directories are created by one user but need to be accessed by the other.

## Problem 1: PDF Splitting Permission Errors

### Issue Description
When attempting to split multi-page PDF invoices (like `invoice(6).pdf`), the system would fail with:
```
Failed to split PDF: undefined
```

### Root Cause
The PDF splitting feature uses Python scripts that need to be executed by the web server (`www-data`), but the scripts and directories were owned by the `jon` user with restrictive permissions.

### Solution: Access Control Lists (ACLs)
Instead of system-wide permission changes that could compromise security, we implemented granular ACLs:

```bash
# Make Python scripts executable by www-data
chmod 755 /var/www/html/osmanagercl/scripts/invoice-parser/pdf_splitter.py
chmod 755 /var/www/html/osmanagercl/scripts/invoice-parser/invoice_parser_laravel.py

# Ensure proper group membership (jon user is already in www-data group)
groups jon  # Confirms: jon adm cdrom sudo dip www-data ...
```

### Key Benefits
- **Granular permissions**: Only affects specific directories/files, not system-wide
- **Security maintained**: No broad permission changes that could affect other system components
- **Leverages existing group membership**: Uses the fact that `jon` is already in the `www-data` group

## Problem 2: Attachment Creation Failures

### Issue Description
Amazon invoices were created successfully but attachments weren't visible. The error logs showed:
```
Failed to create invoice attachment: "Unable to create a directory at /var/www/html/osmanagercl/storage/app/private/invoices/2025/06/9087."
```

### Root Cause Analysis
Directory permission investigation revealed:
```bash
ls -la /var/www/html/osmanagercl/storage/app/private/invoices/2025/
# Results showed mixed permissions:
# drwxr-xr-x jon jon      (2025/07) - www-data can't write
# drwxrwxr-x jon www-data (2025/08) - both users can access
```

The issue was that some directories were created with `jon:jon` ownership instead of `jon:www-data`, making them inaccessible to the web server.

### Solution: Enhanced Permission Management

#### 1. Fixed Existing Directory Permissions
```bash
# Fix directories owned by jon user
find /var/www/html/osmanagercl/storage/app/private/invoices/2025/07 -type d -user jon -exec chmod 775 {} \;
find /var/www/html/osmanagercl/storage/app/private/invoices/2025/07 -type d -user jon -exec chgrp www-data {} \;
find /var/www/html/osmanagercl/storage/app/private/invoices/2025/07 -type f -user jon -exec chmod 664 {} \;
find /var/www/html/osmanagercl/storage/app/private/invoices/2025/07 -type f -user jon -exec chgrp www-data {} \;
```

#### 2. Updated InvoiceCreationService.php
Enhanced the attachment creation process with proper permission handling:

```php
private function createInvoiceAttachment(Invoice $invoice, InvoiceUploadFile $file): void
{
    try {
        // Set umask to ensure proper permissions for new files/directories
        $oldUmask = umask(0002); // This ensures group write permissions
        
        // ... file processing code ...
        
        // Ensure directories exist with proper permissions
        $this->ensureDirectoryExistsWithPermissions($permanentPath);
        
        // Copy file and fix permissions
        Storage::disk('local')->put($permanentPath, $tempFileContent);
        $this->fixAttachmentPermissions($permanentPath);
        
        // Restore original umask
        umask($oldUmask);
        
        // ... create attachment record ...
    }
}
```

#### 3. Added Helper Methods

**Directory Creation with Permissions:**
```php
private function ensureDirectoryExistsWithPermissions(string $filePath): void
{
    $directory = dirname($filePath);
    
    if (!Storage::disk('local')->exists($directory)) {
        Storage::disk('local')->makeDirectory($directory, 0775, true);
        $this->fixDirectoryPermissions(Storage::disk('local')->path($directory));
    }
}
```

**Permission Fixing:**
```php
private function fixAttachmentPermissions(string $filePath): void
{
    try {
        $fullPath = Storage::disk('local')->path($filePath);
        $directory = dirname($fullPath);
        
        // Set file permissions (readable by group)
        if (file_exists($fullPath)) {
            chmod($fullPath, 0664);
            chgrp($fullPath, 'www-data');
        }
        
        // Fix directory permissions recursively
        $this->fixDirectoryPermissions($directory);
    } catch (\Exception $e) {
        Log::warning('Failed to set permissions for invoice attachment', [
            'path' => $filePath,
            'error' => $e->getMessage(),
        ]);
    }
}
```

## Permission Standards

### File Permissions
- **Directories**: `775` (rwxrwxr-x) - Owner and group can read/write/execute, others can read/execute
- **Files**: `664` (rw-rw-r--) - Owner and group can read/write, others can read only

### Ownership Standards
- **Owner**: `jon` (queue worker user)
- **Group**: `www-data` (web server group)
- **Rationale**: Allows both queue workers and web server to access files

### Umask Usage
Set `umask(0002)` during file operations to ensure:
- New directories get 775 permissions (777 - 002 = 775)
- New files get 664 permissions (666 - 002 = 664)
- Group write permissions are preserved

## Verification Steps

### 1. Test PDF Splitting
```bash
cd /var/www/html/osmanagercl/scripts/invoice-parser
./venv/bin/python pdf_splitter.py --help
# Should display help without permission errors
```

### 2. Test Attachment Creation
```bash
php artisan tinker
# Test attachment creation for a sample invoice
$invoice = App\Models\Invoice::find(INVOICE_ID);
$attachment = $invoice->attachments()->first();
$attachment->exists(); // Should return true
```

### 3. Check Directory Permissions
```bash
ls -la /var/www/html/osmanagercl/storage/app/private/invoices/2025/
# Should show: drwxrwxr-x jon www-data for all directories
```

### 4. Test Web Access
Navigate to an Amazon invoice in the web interface and verify:
- Attachments are visible in the attachments section
- PDF files can be viewed inline
- Download links work correctly

## Prevention Measures

### 1. Consistent Umask Setting
Always set proper umask when creating files in queue workers:
```php
$oldUmask = umask(0002);
// ... file operations ...
umask($oldUmask);
```

### 2. Group Ownership Verification
Ensure the executing user has proper group membership:
```bash
groups jon  # Should include www-data
```

### 3. Storage Directory Monitoring
Periodically check for directories with incorrect permissions:
```bash
find /var/www/html/osmanagercl/storage/app/private/invoices/ -type d ! -group www-data -ls
```

## Common Issues and Solutions

### Issue: "Permission denied" when accessing attachments
**Solution**: Check directory permissions and group ownership
```bash
ls -la /var/www/html/osmanagercl/storage/app/private/invoices/YYYY/MM/INVOICE_ID/
chmod 775 directory_name
chgrp www-data directory_name
```

### Issue: PDF splitting fails with "undefined" error
**Solution**: Verify Python script permissions and group access
```bash
ls -la /var/www/html/osmanagercl/scripts/invoice-parser/pdf_splitter.py
chmod 755 pdf_splitter.py
```

### Issue: New attachments created with wrong permissions
**Solution**: Ensure umask is set in the code creating attachments
```php
$oldUmask = umask(0002);
// ... file operations ...
umask($oldUmask);
```

## Architecture Considerations

### Why This Approach Works
1. **Leverages existing group membership**: `jon` user is already in `www-data` group
2. **Minimal system impact**: Only affects invoice storage directories
3. **Maintains security**: Doesn't grant broad system permissions
4. **Future-proof**: New files/directories automatically get correct permissions

### Alternative Approaches Considered
1. **Adding www-data to jon group system-wide**: Rejected due to security concerns
2. **Running queue workers as www-data**: Rejected due to existing cron job configurations
3. **Using sudo for permission changes**: Rejected due to complexity and security risks

## Monitoring and Maintenance

### Log Monitoring
Watch for permission-related errors in Laravel logs:
```bash
tail -f /var/www/html/osmanagercl/storage/logs/laravel.log | grep -i "permission\|unable to create"
```

### Periodic Permission Audits
Run monthly checks for permission inconsistencies:
```bash
# Find directories with wrong group
find /var/www/html/osmanagercl/storage/app/private/invoices/ -type d ! -group www-data

# Find files with restrictive permissions
find /var/www/html/osmanagercl/storage/app/private/invoices/ -type f ! -perm -664
```

This comprehensive approach ensures Amazon invoices work reliably while maintaining system security and proper file access controls.