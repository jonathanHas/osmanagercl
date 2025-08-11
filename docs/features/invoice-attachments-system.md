# Invoice Attachments System

## Overview

The Invoice Attachments System provides secure file management capabilities for invoices, allowing users to upload, view, and manage multiple file attachments per invoice. This system is a significant upgrade from the simple path/filename storage used in OSAccounts.

## Features

### ✅ Core Functionality
- **Multiple Attachments**: Support for up to 5 files per invoice
- **Secure Storage**: Files stored outside web root with controlled access
- **File Validation**: Type, size, and security validation
- **In-Browser Viewing**: PDFs and images viewable directly in browser
- **Download Management**: Secure file downloads with proper headers
- **Attachment Types**: Categorized as Invoice Scan, Receipt, Delivery Note, or Other
- **Primary File**: Automatic designation of primary attachment

### ✅ User Interface
- **Modern Upload Modal**: Drag-and-drop interface with progress indicators
- **Visual File Management**: File icons, type recognition, and action buttons
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Long Filename Support**: Truncated display with hover tooltips
- **Attachment Indicators**: Badge system showing attachment count on invoice listings

### ✅ Security & Performance
- **Authentication Required**: All routes protected by Laravel auth middleware
- **Private Storage**: Files stored in Laravel's private disk
- **Access Control**: Only authenticated users can access attachments
- **File Organization**: Structured by year/month/invoice for scalability
- **Duplicate Detection**: SHA-256 hash-based duplicate prevention

## Technical Architecture

### Database Schema

#### `invoice_attachments` Table
```sql
id                     bigint          PRIMARY KEY
invoice_id             bigint          FOREIGN KEY → invoices.id
original_filename      varchar(255)    Original name when uploaded
stored_filename        varchar(255)    UUID-based filename on disk
file_path             varchar(255)    Relative path from storage disk
mime_type             varchar(255)    File MIME type
file_size             bigint          File size in bytes
file_hash             varchar(255)    SHA-256 hash for duplicates
description           varchar(255)    Optional user description
attachment_type       enum            invoice_scan|receipt|delivery_note|other
is_primary            boolean         Primary attachment flag
uploaded_by           bigint          FOREIGN KEY → users.id
uploaded_at           timestamp       Upload timestamp
external_osaccounts_path varchar(255) Original OSAccounts path (migration)
created_at            timestamp
updated_at            timestamp
```

### File Storage Structure
```
storage/app/private/invoices/
├── 2025/
│   └── 08/
│       ├── 1/
│       │   └── uuid-filename.pdf
│       ├── 2/
│       │   ├── uuid-filename.jpg
│       │   └── uuid-filename.txt
│       └── 3/
│           └── uuid-filename.pdf
└── 2025/
    └── 09/
        └── ...
```

### Models

#### `InvoiceAttachment` Model
Located: `app/Models/InvoiceAttachment.php`

**Key Methods:**
- `generateStoredFilename()` - Creates UUID-based filename
- `generateFilePath()` - Creates year/month/invoice directory structure
- `exists()` - Checks if file exists on disk
- `isViewable()` - Determines if file can be viewed in browser
- `deleteFile()` - Removes file from storage
- `getFormattedFileSizeAttribute()` - Human-readable file size

**Relationships:**
- `belongsTo(Invoice::class)`
- `belongsTo(User::class, 'uploaded_by')`

#### `Invoice` Model Extensions
Added methods:
- `attachments()` - HasMany relationship
- `primaryAttachment()` - Get primary attachment
- `hasAttachments()` - Boolean check for attachments
- `getAttachmentCountAttribute()` - Count of attachments

### Controllers

#### `InvoiceAttachmentController`
Located: `app/Http/Controllers/InvoiceAttachmentController.php`

**Endpoints:**
- `POST /invoices/{invoice}/attachments` - Upload files
- `GET /invoices/{invoice}/attachments` - List attachments (JSON)
- `GET /invoice-attachments/{attachment}/view` - View file in browser
- `GET /invoice-attachments/{attachment}/download` - Download file
- `PATCH /invoice-attachments/{attachment}` - Update attachment details
- `DELETE /invoice-attachments/{attachment}` - Delete attachment
- `GET /invoices/{invoice}/attachments/config` - Upload configuration

### Routes
```php
// Invoice Attachments routes
Route::prefix('invoices/{invoice}/attachments')->name('invoices.attachments.')->group(function () {
    Route::get('/', [InvoiceAttachmentController::class, 'index'])->name('index');
    Route::post('/', [InvoiceAttachmentController::class, 'store'])->name('store');
    Route::get('/config', [InvoiceAttachmentController::class, 'getUploadConfig'])->name('config');
});

Route::prefix('invoice-attachments/{attachment}')->name('invoices.attachments.')->group(function () {
    Route::get('/view', [InvoiceAttachmentController::class, 'view'])->name('view');
    Route::get('/download', [InvoiceAttachmentController::class, 'download'])->name('download');
    Route::patch('/', [InvoiceAttachmentController::class, 'update'])->name('update');
    Route::delete('/', [InvoiceAttachmentController::class, 'destroy'])->name('destroy');
});
```

## File Upload Specifications

### Supported File Types
- **Documents**: PDF, DOC, DOCX, XLS, XLSX, TXT
- **Images**: JPG, JPEG, PNG, GIF, WEBP
- **Size Limit**: 10MB per file
- **Quantity Limit**: 5 files per upload

### Validation Rules
```php
'attachments' => 'required|array|min:1|max:5',
'attachments.*' => [
    'required',
    'file',
    'max:10240', // 10MB
    'mimes:pdf,jpg,jpeg,png,gif,webp,txt,doc,docx,xls,xlsx',
],
```

## User Interface Components

### Upload Modal
**Location**: `resources/views/invoices/show.blade.php` (JavaScript)

**Features:**
- File selection with validation
- Attachment type selection
- Optional description
- Progress indicators
- Error handling

### Attachment Display
**Layout**: Responsive card-based design with:
- File type icons
- Filename truncation for long names
- File metadata (size, type, upload date)
- Action buttons (View, Download, Delete)
- Primary attachment badge

### Invoice List Integration
**Feature**: Attachment count badges on invoice listings
- Shows blue badge with count when attachments exist
- Tooltip shows attachment count details

## OSAccounts Migration

### Import Command
**Command**: `php artisan osaccounts:import-attachments`

**Options:**
- `--dry-run` - Preview import without changes
- `--base-path=/path/to/files` - OSAccounts file location
- `--force` - Import even if attachments exist

**Process:**
1. Finds OSAccounts invoices with file attachments
2. Locates corresponding Laravel invoices
3. Copies files to Laravel private storage
4. Creates attachment records with metadata
5. Preserves OSAccounts path references

### Migration Fields
- `external_osaccounts_path` - Tracks original OSAccounts file path
- Description auto-set to "Imported from OSAccounts"
- Attachment type defaults to "invoice_scan"

## API Reference

### Upload Files
```http
POST /invoices/{invoice}/attachments
Content-Type: multipart/form-data

{
    "attachments[]": [File objects],
    "descriptions[]": ["Optional descriptions"],
    "attachment_types[]": ["invoice_scan", "receipt", "delivery_note", "other"]
}
```

### List Attachments
```http
GET /invoices/{invoice}/attachments
Accept: application/json

Response:
{
    "success": true,
    "attachments": [
        {
            "id": 1,
            "original_filename": "invoice.pdf",
            "formatted_file_size": "2.5 MB",
            "attachment_type_label": "Invoice Scan",
            "description": "Main invoice document",
            "is_primary": true,
            "is_viewable": true,
            "view_url": "http://localhost/invoice-attachments/1/view",
            "download_url": "http://localhost/invoice-attachments/1/download",
            "uploaded_by": "Admin",
            "uploaded_at": "10/08/2025 14:30"
        }
    ]
}
```

### View/Download File
```http
GET /invoice-attachments/{attachment}/view
GET /invoice-attachments/{attachment}/download
```

## Configuration

### Storage Configuration
**File**: `config/filesystems.php`

```php
'private' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'serve' => false,
    'visibility' => 'private',
    'throw' => false,
    'report' => false,
],
```

### Route Model Binding
**File**: `app/Providers/AppServiceProvider.php`

```php
Route::bind('attachment', function (string $value) {
    return InvoiceAttachment::findOrFail($value);
});
```

## Troubleshooting

### Common Issues

#### 1. File Upload Fails - Directory Creation Error
**Error**: `Unable to create a directory at /var/www/html/osmanagercl/storage/app/private/invoices/...`

**Solution**: Fix storage permissions
```bash
chmod -R 775 /var/www/html/osmanagercl/storage/app/private/invoices/
chgrp -R www-data /var/www/html/osmanagercl/storage/app/private/invoices/
```

#### 2. File Not Found When Viewing/Downloading
**Error**: `File not found` or 404 error

**Solutions:**
1. Check file permissions: `chmod 644 [file-path]`
2. Verify file exists in private storage
3. Check Laravel logs for detailed errors
4. Ensure user is authenticated

#### 3. Long Filenames Cut Off Action Buttons
**Solution**: UI automatically truncates long filenames and shows full name on hover. Action buttons are always visible.

#### 4. Route Model Binding Issues
**Error**: Route not found or model not bound

**Solution**: Clear route cache
```bash
php artisan route:clear
php artisan config:clear
```

### Log Monitoring
Check logs for attachment-related issues:
```bash
tail -f storage/logs/laravel.log | grep -i attachment
```

## Performance Considerations

### File Organization
- Files organized by year/month/invoice for scalability
- Prevents large directory issues with thousands of files
- Easy to archive/backup by time period

### Database Indexing
```sql
-- Existing indexes
INDEX `invoice_attachments_invoice_id_index` (invoice_id)
INDEX `invoice_attachments_external_osaccounts_path_index` (external_osaccounts_path)
INDEX `invoice_attachments_invoice_id_attachment_type_index` (invoice_id, attachment_type)
INDEX `invoice_attachments_uploaded_by_uploaded_at_index` (uploaded_by, uploaded_at)
```

### Caching Considerations
- File existence checks cached at application level
- Attachment counts cached using Eloquent relationships
- No database queries for file metadata once loaded

## Security Features

### Access Control
- All routes require authentication
- Users can only access attachments for invoices they have permission to view
- File downloads use Laravel's secure response methods

### File Validation
- MIME type validation prevents malicious uploads
- File size limits prevent storage abuse
- Extension whitelist blocks dangerous file types

### Storage Security
- Files stored outside web root
- UUID-based filenames prevent guessing
- Private disk configuration prevents direct access

## Future Enhancements

### Planned Features
- **Cloud Storage Integration**: Easy migration to AWS S3, Google Cloud
- **Image Thumbnails**: Preview generation for image attachments
- **File Versioning**: Support for multiple versions of the same document
- **Bulk Operations**: Download multiple attachments as ZIP
- **Advanced Search**: Search within document contents
- **Approval Workflow**: Attachment approval process
- **Email Integration**: Attach files when emailing invoices

### Integration Possibilities
- **Document Management**: Integration with SharePoint, Google Drive
- **OCR Processing**: Extract text from uploaded images
- **Digital Signatures**: PDF signing capabilities
- **Audit Trail**: Enhanced tracking of file access and modifications

## Comparison with OSAccounts

| Feature | OSAccounts | Laravel System |
|---------|------------|----------------|
| Files per Invoice | 1 only | Multiple (up to 5) |
| File Storage | Simple path + filename | UUID-based secure storage |
| Security | Direct file paths | Private storage with access control |
| File Metadata | None | Size, MIME, hash, upload date, uploader |
| File Validation | None | Type, size, security validation |
| File Organization | Flat storage | Year/month/invoice structure |
| Duplicate Detection | None | SHA-256 hash-based |
| File Types | Any | Controlled whitelist |
| Attachment Types | None | Categorized system |
| Primary File | Manual | Automatic designation |
| File Viewing | External app | In-browser PDF/image viewing |
| File Management | Manual | Web interface with CRUD |
| Audit Trail | None | Full upload/access logging |
| Cloud Ready | No | Easy AWS S3/Google Cloud migration |

The Laravel implementation provides **enterprise-grade file management** with significantly enhanced security, usability, and scalability compared to OSAccounts.