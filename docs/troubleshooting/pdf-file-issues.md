# PDF & File Upload Issues

This guide covers issues related to PDF uploads, file attachments, and document conversion.

---

## PDF Upload Validation Failures - Corrupted Headers

**Symptoms:**
- PDF files fail to upload with "Only PDF, JPG, PNG, TIFF files are allowed" error
- Browser console shows 422 Unprocessable Content on upload
- Files appear to be valid PDFs when opened manually
- Issue occurs with specific suppliers (e.g., Klee Paper)

**Root Cause:**
Some suppliers generate PDFs with corrupted headers containing PostScript commands before the PDF signature:
- **Normal PDF**: `%PDF-1.7...`
- **Corrupted PDF**: `0.566929 w 0 J 0 j [] 0.000000 d\n%PDF-1.7...`

**Diagnosis:**
```bash
# Check file header (should show %PDF- at start)
xxd -l 50 suspicious.pdf | head -1

# Check what system detects as file type
file suspicious.pdf

# Use our repair service test
php artisan test:pdf-repair /path/to/suspicious.pdf
```

**Solution:**
The system now includes automatic PDF repair. If issues persist:

1. **Check PDF repair is enabled:**
   ```php
   // config/invoices.php
   'pdf_repair' => [
       'enabled' => env('INVOICE_PDF_REPAIR_ENABLED', true),
   ],
   ```

2. **Check repair logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i "pdf repair"
   ```

3. **Manual repair for testing:**
   ```bash
   php artisan test:pdf-repair /path/to/file.pdf
   ```

**Technical Details:**
- **Service**: `App\Services\PdfRepairService`
- **Validation Rule**: `App\Rules\RepairablePdf`
- **Detection**: Scans first 1KB for PDF signature location
- **Repair**: Strips data before `%PDF-` header
- **Logging**: All attempts logged for debugging

---

## Image Uploads Not Appearing (Fixed September 2025)

**Problem:** Image uploads appear successful but changes don't show on the page.

**Symptoms:**
- Image file uploads without errors
- Success message displayed to user
- Upload function returns 200 status
- New image doesn't appear, shows old image instead

**Root Cause:**
Images are successfully saved to the database, but browser caching prevents updated images from being displayed. The image serving endpoint sets 24-hour cache headers.

**Solution (Implemented):**
1. **Cache-Busting**: Added timestamp parameter to image URLs after upload
2. **Dynamic Cache Control**: Reduced cache time to 5 minutes when cache-busting parameter present
3. **Transaction Safety**: Added proper database transaction handling for image updates
4. **Content-Type Detection**: Automatic MIME type detection from image binary data

**For Users (Temporary Workaround):**
If you encounter this issue, force refresh the image:
- Press Ctrl+F5 to hard refresh the page
- Or clear browser cache for the site

**Technical Details:**
```javascript
// Fixed: Image URL now includes timestamp for cache busting
document.getElementById('current-image').src =
    '/fruit-veg/product-image/' + productCode + '?t=' + timestamp;
```

**Controller Changes:**
```php
// Added proper transaction management
DB::connection('pos')->beginTransaction();
try {
    DB::connection('pos')->table('PRODUCTS')
        ->where('ID', $product->ID)
        ->update(['IMAGE' => $imageData]);
    DB::connection('pos')->commit();
} catch (\Exception $e) {
    DB::connection('pos')->rollBack();
    // Error handling...
}
```

---

## Invoice Bulk Upload Attachments Not Saved (Fixed 2025-08-19)

**Symptoms:**
- Invoice data and amounts are created successfully in bulk upload
- Invoice records appear in the system with correct totals
- Attachment files are missing from invoice detail pages
- Error logs contain "Unable to create directory" errors

**Root Cause:**
Directory permission mismatch between web server process and queue worker process:

1. **Web Server Process**: Runs as `www-data` user
2. **Queue Worker Process**: Runs as `jon` user (or different system user)
3. **Permission Conflict**: `/storage/app/private/invoices/attachments/` owned by `www-data:www-data` with restrictive permissions (700)
4. **Result**: Queue worker cannot create invoice-specific subdirectories

**Diagnostic Steps:**
```bash
# 1. Check directory permissions
ls -la /var/www/html/osmanagercl/storage/app/private/invoices/

# 2. Check running processes
ps aux | grep php | grep -v grep

# 3. Check recent error logs
tail -50 /var/www/html/osmanagercl/storage/logs/laravel.log | grep -i "unable to create"

# 4. Test attachment creation manually
php artisan tinker
$file = App\Models\InvoiceUploadFile::where('status', 'review')->first();
$service = new App\Services\InvoiceCreationService();
$invoice = $service->createFromParsedFile($file, true);
```

**Solution Implemented:**
Updated `InvoiceCreationService.php` to use year/month directory structure that avoids permission conflicts:

- **Old Path**: `invoices/attachments/[invoice_id]/[filename]`
- **New Path**: `invoices/[year]/[month]/[invoice_id]/[filename]`

**Key Changes:**
- Uses existing `invoices/2025/` directory with proper group permissions
- Creates new directories with 775 permissions (group writable)
- Sets proper file permissions (664) for created attachments
- Maintains logical organization by invoice ID

**Prevention:**
- Ensure consistent user/group ownership for storage directories
- Use group-writable permissions (775) for directories
- Monitor logs for permission-related errors
- Test with both web interface and queue worker processes

**Verification:**
```bash
# Check that new invoices have attachments
# Visit invoice detail page and verify files are visible

# Verify file storage location
ls -la /var/www/html/osmanagercl/storage/app/private/invoices/2025/08/[invoice_id]/

# Test file permissions
stat /var/www/html/osmanagercl/storage/app/private/invoices/2025/08/[invoice_id]/[filename]
```

---

## Invoice Attachment Path Mismatch (Fixed 2025-08-20)

**Symptoms:**
- Files upload successfully to bulk upload system
- Invoice data and amounts are correctly displayed in bulk upload preview
- When creating invoices from review, attachments are not created
- Database records show correct file paths but `tempFileExists()` returns false
- No errors in logs but attachment creation is silently skipped

**Root Cause:**
Path mismatch between what's stored in database and what Laravel's Storage facade expects when checking file existence:

1. **File Storage**: Laravel's `Storage::disk('local')->storeAs()` puts files in `storage/app/private/temp/invoices/...`
2. **Database Storage**: `InvoiceBulkUploadController` was storing only relative path `temp/invoices/...`
3. **File Check**: `tempFileExists()` uses `Storage::disk('local')->exists()` which expects the full path including 'private/'
4. **Result**: File exists on disk but `tempFileExists()` returns false, preventing attachment creation

**Technical Details:**
```php
// The problem was in InvoiceBulkUploadController.php line 126:
'temp_path' => $filePath,  // Only stored 'temp/invoices/batch/file.pdf'

// But storeAs() actually returned:
$storedPath = 'private/temp/invoices/batch/file.pdf'  // Full path with 'private/' prefix

// When tempFileExists() checked:
Storage::disk('local')->exists($this->temp_path);  // Looked for 'temp/invoices/...' but file was at 'private/temp/invoices/...'
```

**Solution Implemented:**
Fixed `InvoiceBulkUploadController.php` to store the correct path returned by Laravel's `storeAs()` method:

```php
// ❌ WRONG (before fix):
'temp_path' => $filePath,  // Manual path construction

// ✅ CORRECT (after fix):
'temp_path' => $storedPath,  // Use path returned by storeAs()
```

**Key Lesson:**
Always use the path returned by Laravel's Storage methods rather than constructing paths manually. The Storage facade knows the correct disk structure and returns the exact path needed for future operations.

**Verification:**
```bash
# 1. Upload new Amazon invoice through bulk upload interface
# 2. Verify invoice appears in preview with correct parsing
# 3. Create invoice from review - confirm attachment appears on detail page
# 4. Test attachment download/view functionality

# Debug if issues persist:
php artisan tinker --execute="
\$file = App\Models\InvoiceUploadFile::latest()->first();
echo 'Temp Path: ' . \$file->temp_path . PHP_EOL;
echo 'File Exists: ' . (\$file->tempFileExists() ? 'YES' : 'NO') . PHP_EOL;
echo 'Full Path: ' . \$file->temp_file_path . PHP_EOL;
echo 'Disk Path: ' . Storage::disk('local')->path(\$file->temp_path) . PHP_EOL;
"
```

**Prevention:**
- Always use Storage facade returned paths instead of manual path construction
- Test `tempFileExists()` method when making file storage changes
- Ensure database stores exactly what Storage facade expects for consistency

---

## Document Conversion Issues (DOC/XLS to PDF Viewing)

### DOC/XLS Attachment Viewer Shows Blank Page

**Symptoms:**
- Document icon appears and is clickable
- Viewer opens but shows blank content
- Browser shows "Viewing converted PDF version of DOC document" message
- No PDF content displays in the viewer area

**Root Cause:**
Document conversion files created via CLI commands (user `jon`) have incorrect permissions for web server access (`www-data`).

**Diagnosis Steps:**
1. Check if conversion files exist:
   ```bash
   ls -la storage/app/private/temp/conversions/
   ```
2. Check file ownership - problematic files will show `jon:jon` ownership
3. Check Laravel logs for conversion errors:
   ```bash
   tail -f storage/logs/laravel.log | grep -i "conversion\|libreoffice"
   ```

**Solution:**
1. Clear existing conversion files:
   ```bash
   rm -rf storage/app/private/temp/conversions/
   ```
2. Clear database entries:
   ```bash
   php artisan tinker --execute="
   App\Models\InvoiceAttachment::where('converted_pdf_path', '!=', null)
       ->update(['converted_pdf_path' => null, 'converted_at' => null]);
   "
   ```
3. Access document through web browser (not CLI) to create proper conversion

**Prevention:**
- Always test document viewing through web browser, not CLI commands
- Conversions created by web server will have correct `www-data` ownership

### LibreOffice Conversion Fails

**Symptoms:**
- Error: "LibreOffice 24.2 - Fatal Error: The application cannot be started"
- Error: "User installation could not be completed"
- Error: "Unable to create directory '/var/www/.cache/dconf': Permission denied"

**Root Cause:**
LibreOffice requires proper HOME directory and environment variables when running headless.

**Solution:**
The DocumentConversionService automatically handles this by:
- Creating temporary HOME directories with proper permissions
- Setting XDG environment variables for LibreOffice configuration
- Using `--accept` parameter to avoid profile conflicts

**Manual Check:**
Verify LibreOffice is installed and working:
```bash
soffice --version
```

If not installed:
```bash
sudo apt-get install libreoffice
```

### Conversion Performance Issues

**Symptoms:**
- First-time document viewing takes longer than expected (>5 seconds)
- Server high CPU usage during conversion

**Optimization:**
- Conversions are cached - first view is slow, subsequent views instant
- LibreOffice timeout set to 60 seconds maximum
- Temporary files cleaned up automatically
- Consider pre-converting frequently accessed documents

**Monitoring:**
Check conversion performance:
```bash
grep "Document converted successfully" storage/logs/laravel.log
```

---

## Related Documentation

- [Invoice Bulk Upload System](../features/invoice-bulk-upload-system.md)
- [Invoice Parser Integration](../features/invoice-parser-integration.md)
- [Back to Troubleshooting Index](./index.md)
