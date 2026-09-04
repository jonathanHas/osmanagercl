# Invoice Bulk Upload System

## Overview

The Invoice Bulk Upload System allows users to upload multiple invoice files simultaneously through a drag-and-drop interface, preparing them for automated parsing and data extraction. This system significantly reduces manual data entry by supporting batch processing of up to 50 invoice files at once.

## Features

### Current Implementation (Phase 1)

- **Drag-and-Drop Interface**: Modern, intuitive file upload with visual feedback
- **Multi-File Support**: Upload up to 50 files per batch (configurable)
- **File Validation**: Client and server-side validation for file types and sizes
- **Progress Tracking**: Real-time upload progress for each file
- **Batch Management**: Track and manage upload batches with unique IDs
- **File Preview**: Review uploaded files before processing
- **Recent Uploads History**: View and manage recent batch uploads
- **Temporary Storage**: Secure temporary file storage before processing

### Client-Side Image Compression

Large image files (e.g. phone photos over 2MB) are automatically compressed in the browser before upload:
- **Automatic**: JPG and PNG images are resized and compressed using the Canvas API
- **Max Dimension**: Scaled down to 2000px on the longest side (sufficient for invoice readability)
- **JPEG Quality**: 0.7 (good balance of quality vs size)
- **UI Feedback**: Shows "Compressing..." during processing, then displays original and compressed size (e.g. "3.3MB → 250KB")
- **Smart**: Only replaces the file if compression actually reduces size; non-image files pass through unchanged
- **No Dependencies**: Uses native browser Canvas API only

### Supported File Types

- **PDF documents**: PDF (directly viewable in browser, with automatic repair for corrupted files)
- **Images**: JPG, JPEG, PNG (directly viewable in browser, auto-compressed on upload)
- **Scanned documents**: TIFF, TIF (download only, not compressed — Canvas API limitation)
- **Microsoft Word documents**: DOC, DOCX (viewable via PDF conversion)
- **Microsoft Excel spreadsheets**: XLS, XLSX (viewable via PDF conversion)

### Automatic PDF Repair System ⚡ NEW!

The system now automatically detects and repairs corrupted PDF files during upload, specifically addressing issues with suppliers like **Klee Paper** that generate PDFs with malformed headers.

**Features:**
- **Automatic Detection**: Identifies PDFs with corrupted headers (extra data before `%PDF-` signature)
- **Transparent Repair**: Fixes files during validation without user intervention
- **Supplier Recognition**: Automatically detects problematic suppliers (e.g., "Klee Paper")
- **Comprehensive Logging**: Tracks all repair attempts for debugging
- **Configurable**: Can be enabled/disabled via environment settings

**How it works:**
1. When a PDF is uploaded, the system checks if the PDF signature (`%PDF-`) is at the beginning
2. If corrupted data is found before the PDF header, it's automatically stripped
3. The cleaned PDF is validated and processed normally
4. All repair operations are logged for troubleshooting

**Configuration options:**
- `INVOICE_PDF_REPAIR_ENABLED=true` - Enable/disable automatic repair
- `INVOICE_PDF_REPAIR_MAX_SIZE=50` - Maximum file size (MB) to attempt repair
- `INVOICE_PDF_REPAIR_LOG=true` - Log repair attempts for debugging

### Document Viewing Capabilities

**Direct Browser Viewing**:
- PDF and image files can be viewed directly in the browser by clicking the document icon
- DOC/DOCX and XLS/XLSX files are converted to PDF on-the-fly for browser viewing
- First-time viewing triggers automatic conversion (2-3 seconds)
- Subsequent views use cached PDF for instant display
- Original files remain unchanged and can still be downloaded

### File Limits

- **Files per batch**: 50 (configurable via `INVOICE_MAX_FILES_PER_BATCH`)
- **Max file size**: 25MB per file (configurable via `INVOICE_MAX_FILE_SIZE_MB`)
- **Total batch size**: 500MB (configurable via `INVOICE_MAX_TOTAL_SIZE_MB`)

### System Requirements

**For Document Processing and Viewing**:
- **LibreOffice**: Required for DOC/XLS to PDF conversion and browser viewing
  ```bash
  sudo apt-get install libreoffice
  ```
- **Python Dependencies** (for future parser integration): 
  - `python-docx`: For .docx file processing
  - `xlrd`: For .xls file processing
  - These are included in the invoice parser virtual environment

**Note**: LibreOffice is essential for both the bulk upload document processing and the real-time document viewing system. Without it, DOC/XLS files can only be downloaded, not viewed in browser.

## Database Schema

### invoice_bulk_uploads

Tracks batch upload sessions:

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| batch_id | string | Unique batch identifier |
| user_id | bigint | User who created the batch |
| total_files | integer | Total number of files in batch |
| processed_files | integer | Number of files processed |
| successful_files | integer | Number of successfully parsed files |
| failed_files | integer | Number of failed files |
| status | enum | pending, uploading, uploaded, processing, completed, failed, cancelled |
| metadata | json | Additional batch information |
| started_at | timestamp | When processing started |
| completed_at | timestamp | When batch completed |

### invoice_upload_files

Tracks individual files within a batch:

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| bulk_upload_id | bigint | Foreign key to batch |
| original_filename | string | Original file name |
| stored_filename | string | System-generated filename |
| temp_path | string | Temporary storage location |
| mime_type | string | File MIME type |
| file_size | bigint | File size in bytes |
| file_hash | string | SHA256 hash for deduplication |
| status | enum | pending, uploading, uploaded, parsing, parsed, review, completed, failed, rejected |
| parsed_data | json | Extracted invoice data |
| parsing_errors | json | Any parsing errors |
| parsing_confidence | float | OCR/parsing confidence score |
| invoice_id | bigint | Created invoice ID (if completed) |
| error_message | text | Error details if failed |
| upload_progress | integer | Upload progress 0-100 |

## User Workflow

1. **Access Bulk Upload**
   - Navigate to Invoices → Click "Bulk Upload" button
   - Or directly visit `/invoices/bulk-upload`

2. **Select Files**
   - Drag and drop multiple files onto the upload zone
   - Or click "Browse Files" to select using file dialog
   - Or click "Select Folder" to read straight from a folder on your PC — see
     [Source Folder Sync](#source-folder-sync) below
   - Files are validated immediately

3. **Review Selection**
   - View list of selected files with sizes
   - Remove individual files if needed
   - See total size and file count

4. **Upload Files**
   - Click "Upload Files" to start batch upload
   - Progress bars show upload status for each file
   - Automatic redirect to preview page on completion

5. **Preview & Process**
   - Review uploaded files in preview page
   - Parsed summary shows total amount, supplier name, and invoice date
   - Edit/Enter Data form pre-populates supplier, date, and VAT breakdown from parsed data
   - Start processing (Python parser - Phase 2)
   - Or cancel batch if needed

## Configuration

Configuration is stored in `config/invoices.php`:

```php
'bulk_upload' => [
    'max_files_per_batch' => env('INVOICE_MAX_FILES_PER_BATCH', 50),
    'max_file_size_mb' => env('INVOICE_MAX_FILE_SIZE_MB', 25),
    'max_total_size_mb' => env('INVOICE_MAX_TOTAL_SIZE_MB', 500),
    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'tif', 'doc', 'docx', 'xls', 'xlsx'],
    'allowed_mime_types' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/tiff',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ],
    'temp_path' => 'temp/invoices',
    'temp_file_lifetime' => 24, // hours
],

'pdf_repair' => [
    'enabled' => env('INVOICE_PDF_REPAIR_ENABLED', true),
    'max_file_size_mb' => env('INVOICE_PDF_REPAIR_MAX_SIZE', 50),
    'log_repairs' => env('INVOICE_PDF_REPAIR_LOG', true),
    'problematic_suppliers' => [
        'Klee Paper',
    ],
],
```

## API Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/invoices/bulk-upload` | Bulk upload interface |
| POST | `/invoices/bulk-upload/upload` | Handle file uploads |
| GET | `/invoices/bulk-upload/status/{batchId}` | Get batch status (JSON) |
| GET | `/invoices/bulk-upload/preview/{batchId}` | Preview uploaded files |
| POST | `/invoices/bulk-upload/{batchId}/cancel` | Cancel a batch |
| DELETE | `/invoices/bulk-upload/{batchId}/file/{fileId}` | Remove a file from batch |
| POST | `/invoices/bulk-upload/{batchId}/file/{fileId}/retry` | Re-run the Python parsers on a failed file |
| POST | `/invoices/bulk-upload/{batchId}/file/{fileId}/send-to-ai` | Send a failed / review file to the AI fallback parser (uses `invoice_ai_fallback` provider from `/tools/ai-diagnostics`; PDFs require Mistral OCR). See [AI Integration: Invoice AI Fallback](./ai-integration.md#invoice-ai-fallback-failed-parses) |

## File Storage

Files are temporarily stored in:
- **Location**: `storage/app/private/temp/invoices/{batch_id}/`
- **Naming**: UUID-based filenames to prevent conflicts
- **Cleanup**: **None today.** `config/invoices.php` declares
  `bulk_upload.temp_file_lifetime` (24 hours) but nothing in the codebase reads that
  key — there is no scheduled task, artisan command or job that prunes this directory.
  Temp files are only removed when a batch is cancelled or an individual file is
  deleted from the preview page. Creating an invoice **copies** the file to
  `invoices/{year}/{month}/{invoice_id}/` and leaves the temp original in place, so
  this directory grows without bound.
- **Security**: Private disk, not web-accessible

## Source Folder Sync

Invoices usually arrive as a pile of PDFs in a folder on the user's own PC (scans,
supplier email downloads). Before this feature the user had to move those originals
out by hand after processing, or risk uploading the same invoices again next time.

That folder is **not reachable from the server** — there is no mount or share — so a
watch folder or an artisan command cannot solve it. The only thing that can move a
file there is the browser, via the File System Access API.

### How it works

1. On `/invoices/bulk-upload`, **Select Folder** calls `showDirectoryPicker()` and reads
   the top-level files whose extension is allowed (the `Processed` subfolder is skipped).
   The resulting `File` objects go through the same `addFiles()` validation as
   drag-and-drop, so size limits, dedupe and image compression are unchanged.
2. On successful upload, the `FileSystemDirectoryHandle` is stored in **IndexedDB**
   (database `invoice-folder-sync`, store `handles`) keyed by batch id, alongside the
   list of uploaded filenames. A handle cannot go in `localStorage`; IndexedDB is what
   lets it survive the navigation to the preview page. Entries are pruned after 7 days.
3. On the preview page, if a handle is remembered for this batch, a **Move to Processed
   folder** panel appears. It polls the existing `/status/{batchId}` endpoint and offers
   to move only the files whose status is `completed` or `split_processed`.
4. Clicking the button (a user gesture is *required* — `requestPermission()` is rejected
   without one) moves those files into `Processed/YYYY-MM/` inside the same folder.
   Name collisions become `invoice (2).pdf`. Files in `review`, `parsed`, `failed` or
   `amazon_pending` are deliberately left in the inbox.

The implementation lives in `resources/views/invoices/partials/folder-sync-script.blade.php`
(`window.InvoiceFolderSync`), included by both bulk-upload views. **No server-side code is
involved** — no controller, model, migration or config changes.

### Browser requirements

| Requirement | Detail |
|---|---|
| Browser | Chrome or Edge desktop. Firefox and Safari do not implement the API. |
| Context | Must be a **secure context** (HTTPS or localhost). |

This app is served over plain HTTP (`http://osmanager.local`, HTTP-only Apache vhost),
so **the folder picker will not appear until the origin is allowlisted**:

1. Open `chrome://flags/#unsafely-treat-insecure-origin-as-secure`
2. Set it to **Enabled** and add `http://osmanager.local`
3. Restart the browser

This is a one-time setting per PC. Where it isn't done — or in an unsupported browser —
the upload page detects it, hides the folder button, and shows these instructions inline.
Drag-and-drop continues to work exactly as before; the folder feature is purely additive.

## Security Considerations

1. **Authentication**: All endpoints require authenticated user
2. **File Validation**: 
   - MIME type checking
   - Extension validation
   - File size limits
3. **User Isolation**: Users can only access their own batches
4. **Hash Verification**: SHA256 hashing for integrity
5. **Temporary Storage**: Private disk, not web-accessible (note: not auto-pruned — see [File Storage](#file-storage))
6. **SQL Injection Prevention**: Parameterized queries throughout

## Error Handling

The system handles various error scenarios:

- **File too large**: Clear error message with size limit
- **Invalid file type**: Lists allowed types
- **Upload failure**: Transaction rollback, cleanup
- **Batch limit exceeded**: Prevents adding more files
- **Network interruption**: Can resume from preview page
- **Duplicate files**: Detected and prevented

## Performance Considerations

- **Client-side image compression**: Large images compressed before upload, reducing bandwidth and avoiding PHP upload limits
- **Chunked uploads**: Support for large files (if enabled)
- **Async processing**: Files queued for background parsing
- **Database indexing**: Optimized queries on batch_id, status
- **Client-side validation**: Reduces server load
- **Progress tracking**: Prevents timeout appearance

## Monitoring & Debugging

### Key Metrics to Track

- Average files per batch
- Upload success rate
- Processing time per file
- Storage usage
- Failed file percentage

### Common Issues & Solutions

1. **"Data truncated" error**
   - Ensure migration `add_uploaded_status_to_invoice_bulk_uploads_table` is run
   
2. **Files not uploading**
   - For images over 2MB: client-side compression should handle this automatically
   - If compression fails, check browser console for Canvas API errors
   - For non-image files: check `php.ini` settings: `upload_max_filesize`, `post_max_size`
   - Verify storage permissions on `storage/app/temp/invoices`

3. **Timeout on large batches**
   - Increase `max_execution_time` in PHP
   - Enable chunked uploads in config

## Future Enhancements (Phase 2)

### Python Parser Integration
- Execute Python invoice parser on uploaded files
- Extract key data: invoice number, date, amounts, VAT
- Support for OCR on scanned documents
- Template learning for repeated suppliers

### Review Interface
- Display extracted data for verification
- Inline editing of parsed values
- Side-by-side view with original document
- Bulk approval/rejection actions

### Invoice Creation
- Convert approved data to invoice records
- Auto-match suppliers by name/VAT number
- Suggest expense categories
- Link original files as attachments

### Advanced Features
- Folder monitoring for automatic uploads
- Email invoice ingestion
- API for external systems
- Machine learning for improved parsing
- Duplicate invoice detection

## Testing

### Manual Testing Checklist

- [ ] Upload single PDF file
- [ ] Upload multiple mixed file types
- [ ] Drag and drop functionality
- [ ] File removal before upload
- [ ] Exceed file count limit (>50)
- [ ] Upload oversized file (>25MB)
- [ ] Upload invalid file type
- [ ] Cancel batch upload
- [ ] View recent uploads
- [ ] Preview uploaded files
- [ ] Delete file from preview

### Automated Tests

Create feature tests for:
- File upload validation
- Batch creation and tracking
- Status updates
- User isolation
- Error handling

## Troubleshooting

### Upload Fails Immediately
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify CSRF token is present
3. Check browser console for JavaScript errors

### Files Not Saving
1. Check storage permissions: `chmod -R 775 storage/app/temp`
2. Verify disk space available
3. Check `storage/app/temp/invoices` directory exists

### Database Errors
1. Run migrations: `php artisan migrate`
2. Check MySQL enum values match model
3. Verify foreign key constraints

### Invoice Created But Attachment Files Missing (Fixed 2025-08-19)

**Symptoms:**
- Invoice amounts and data are created successfully
- Invoice shows in the system with correct totals
- No attachment files appear on invoice detail pages
- Error logs show "Unable to create directory" errors

**Cause:**
Directory permission issues where the queue worker (running as user `jon`) cannot create directories in `/storage/app/private/invoices/attachments/` which is owned by `www-data` with restrictive permissions (700).

**Root Cause Analysis:**
1. Web server uploads create directories owned by `www-data:www-data`
2. Queue worker runs as user `jon` (different from web server user)
3. Restrictive permissions on `attachments/` directory prevent queue worker from creating subdirectories
4. Invoice creation succeeds but attachment creation silently fails

**Solution Implemented:**
The `InvoiceCreationService` was updated to use a different directory structure that avoids permission conflicts:

- **Old path**: `invoices/attachments/[invoice_id]/[filename]`
- **New path**: `invoices/[year]/[month]/[invoice_id]/[filename]`

This uses the existing `invoices/2025/` structure with proper group permissions instead of the restricted `attachments/` folder.

### Attachment Path Mismatch (Fixed 2025-08-20)

**Symptoms:**
- Files upload successfully to bulk upload system
- Invoice amounts are correct and shown in bulk upload preview
- When creating invoices, attachments are not created
- `tempFileExists()` returns false preventing attachment creation

**Root Cause:**
Path mismatch between what's stored in database and what Laravel's Storage facade expects:
- Files stored by `Storage::disk('local')->storeAs()` in `storage/app/private/temp/invoices/...`
- Database was storing only relative path `temp/invoices/...` (missing 'private/' prefix)
- `tempFileExists()` checks `Storage::disk('local')->exists()` which expects full path with 'private/'

**Solution Implemented:**
Updated `InvoiceBulkUploadController.php` line 126 to store the full path returned by `storeAs()`:
```php
// Before (incorrect):
'temp_path' => $filePath,  // Only stored 'temp/invoices/batch/file.pdf'

// After (correct):
'temp_path' => $storedPath,  // Stores 'private/temp/invoices/batch/file.pdf'
```

**Prevention:**
- Always use the path returned by Laravel's Storage methods
- Ensure database stores exactly what Storage facade expects
- Test `tempFileExists()` method when making storage changes

**Verification Steps:**
1. Upload new Amazon invoice through bulk upload
2. Verify file shows in preview with correct parsing
3. Create invoice from review and confirm attachment appears on invoice detail page
4. Check that attachment can be viewed/downloaded successfully

### PDF Upload Fails with "File type not allowed" (Fixed 2025-09-04)

**Symptoms:**
- PDF files fail to upload with validation error "Only PDF, JPG, PNG, TIFF files are allowed"
- Browser console shows 422 Unprocessable Content error
- Files appear to be valid PDFs when viewed manually
- Issue occurs specifically with certain supplier PDFs (e.g., Klee Paper)

**Root Cause:**
Some suppliers generate PDFs with corrupted headers containing PostScript commands before the PDF signature:
- Normal PDF starts with: `%PDF-1.7`
- Corrupted PDF starts with: `0.566929 w 0 J 0 j [] 0.000000 d\n%PDF-1.7`
- System's MIME type detection reads the PostScript commands and identifies file as "data" instead of "application/pdf"
- Validation fails before file can be processed

**Solution Implemented:**
Created automatic PDF repair system that:
1. **Custom Validation Rule**: `RepairablePdf` rule handles files with PDF extensions but wrong MIME types
2. **Automatic Detection**: Scans first 1KB of file to find actual PDF signature location
3. **Transparent Repair**: Removes corrupted data before PDF header during validation
4. **Supplier Recognition**: Identifies and logs problematic suppliers for tracking
5. **Seamless Processing**: Repaired files continue through normal upload workflow

**Technical Details:**
- Service: `App\Services\PdfRepairService`
- Validation Rule: `App\Rules\RepairablePdf`
- Configuration: `config/invoices.pdf_repair`
- Logs: All repair attempts logged to `storage/logs/laravel.log`

**Verification:**
1. Upload a Klee Paper PDF through bulk upload interface
2. Check logs for "PDF repair needed" and "PDF repaired successfully" messages
3. Confirm file uploads without validation errors
4. Verify file processes normally through the system

## Related Documentation

- [Invoice Management System](./invoice-management.md)
- [Invoice Attachments System](./invoice-attachments-system.md)
- [Python Parser Integration Guide](./invoice-parser-integration.md) (To be created)
- [VAT Processing](./vat-returns.md)

## Support

For issues or questions:
1. Check this documentation
2. Review error logs
3. Contact system administrator
4. Report bugs in issue tracker