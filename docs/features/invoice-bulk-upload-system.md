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

### Supported File Types

- PDF documents
- Images: JPG, JPEG, PNG
- Scanned documents: TIFF, TIF

### File Limits

- **Files per batch**: 50 (configurable via `INVOICE_MAX_FILES_PER_BATCH`)
- **Max file size**: 25MB per file (configurable via `INVOICE_MAX_FILE_SIZE_MB`)
- **Total batch size**: 500MB (configurable via `INVOICE_MAX_TOTAL_SIZE_MB`)

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
   - Start processing (Python parser - Phase 2)
   - Or cancel batch if needed

## Configuration

Configuration is stored in `config/invoices.php`:

```php
'bulk_upload' => [
    'max_files_per_batch' => env('INVOICE_MAX_FILES_PER_BATCH', 50),
    'max_file_size_mb' => env('INVOICE_MAX_FILE_SIZE_MB', 25),
    'max_total_size_mb' => env('INVOICE_MAX_TOTAL_SIZE_MB', 500),
    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'tif'],
    'allowed_mime_types' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/tiff',
    ],
    'temp_path' => 'temp/invoices',
    'temp_file_lifetime' => 24, // hours
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

## File Storage

Files are temporarily stored in:
- **Location**: `storage/app/temp/invoices/{batch_id}/`
- **Naming**: UUID-based filenames to prevent conflicts
- **Cleanup**: Automatic cleanup after 24 hours (configurable)
- **Security**: Private disk, not web-accessible

## Security Considerations

1. **Authentication**: All endpoints require authenticated user
2. **File Validation**: 
   - MIME type checking
   - Extension validation
   - File size limits
3. **User Isolation**: Users can only access their own batches
4. **Hash Verification**: SHA256 hashing for integrity
5. **Temporary Storage**: Files cleaned up automatically
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
   - Check `php.ini` settings: `upload_max_filesize`, `post_max_size`
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