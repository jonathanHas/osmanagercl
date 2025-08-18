# Bulk Upload Development Guide

## Quick Start for Developers

This guide helps developers understand and extend the bulk upload system.

## Project Structure

```
app/
├── Http/Controllers/
│   └── InvoiceBulkUploadController.php   # Main controller
├── Models/
│   ├── InvoiceBulkUpload.php            # Batch model
│   └── InvoiceUploadFile.php            # File model
├── Services/
│   └── InvoiceParsingService.php        # (Phase 2) Parser service
└── Jobs/
    └── ParseInvoiceFile.php             # (Phase 2) Queue job

resources/views/invoices/
├── bulk-upload.blade.php                 # Upload interface
└── bulk-upload-preview.blade.php         # Preview page

config/
└── invoices.php                          # Configuration

database/migrations/
├── *_create_invoice_bulk_uploads_tables.php
└── *_add_uploaded_status_to_invoice_bulk_uploads_table.php
```

## Adding New File Types

### 1. Update Configuration

Edit `config/invoices.php`:

```php
'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'tif', 'docx'], // Add new
'allowed_mime_types' => [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/tiff',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // Add new
],
```

### 2. Update Frontend Validation

Edit `resources/views/invoices/bulk-upload.blade.php`:

```javascript
// In the bulkUpload() Alpine.js component
allowedExtensions: @json($allowedExtensions), // Already dynamic

// Update file input accept attribute
accept=".pdf,.jpg,.jpeg,.png,.tiff,.tif,.docx"
```

### 3. Update Parser (Phase 2)

Add support in Python parser for new file types.

## Customizing Upload Limits

### Via Environment Variables

```env
# .env file
INVOICE_MAX_FILES_PER_BATCH=100
INVOICE_MAX_FILE_SIZE_MB=50
INVOICE_MAX_TOTAL_SIZE_MB=1000
```

### Dynamic Per-User Limits

```php
// In controller
$maxFiles = auth()->user()->hasRole('admin') ? 100 : 50;
```

## Adding Custom Validation

### Server-side Validation

```php
// In InvoiceBulkUploadController@upload
$request->validate([
    'files.*' => [
        'required',
        'file',
        function ($attribute, $value, $fail) {
            // Custom validation logic
            if ($this->isDuplicateInvoice($value)) {
                $fail('This invoice has already been uploaded.');
            }
        },
    ],
]);
```

### Client-side Validation

```javascript
// In bulk-upload.blade.php
addFiles(newFiles) {
    newFiles.forEach(file => {
        // Add custom validation
        if (this.isInvoiceAlreadyUploaded(file)) {
            this.errors.push(`${file.name}: Invoice already exists in system.`);
            return;
        }
    });
}
```

## Implementing Progress Updates

### Real-time Upload Progress

```javascript
// Enhanced upload with XMLHttpRequest for progress
async uploadFilesWithProgress() {
    const formData = new FormData();
    this.files.forEach((fileObj, index) => {
        formData.append('files[]', fileObj.file);
    });
    
    const xhr = new XMLHttpRequest();
    
    // Track upload progress
    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            this.updateProgress(percentComplete);
        }
    });
    
    xhr.open('POST', '{{ route("invoices.bulk-upload.upload") }}');
    xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
    xhr.send(formData);
}
```

### Polling for Processing Status

```javascript
// Poll for batch status updates
pollStatus() {
    const interval = setInterval(async () => {
        const response = await fetch(`/invoices/bulk-upload/status/${this.batchId}`);
        const data = await response.json();
        
        this.updateFileStatuses(data.files);
        
        if (data.status === 'completed' || data.status === 'failed') {
            clearInterval(interval);
        }
    }, 2000); // Poll every 2 seconds
}
```

## Adding Chunked Upload Support

### Frontend Implementation

```javascript
// Upload large files in chunks
async uploadChunked(file, chunkSize = 2 * 1024 * 1024) { // 2MB chunks
    const chunks = Math.ceil(file.size / chunkSize);
    
    for (let i = 0; i < chunks; i++) {
        const start = i * chunkSize;
        const end = Math.min(start + chunkSize, file.size);
        const chunk = file.slice(start, end);
        
        const formData = new FormData();
        formData.append('chunk', chunk);
        formData.append('chunk_index', i);
        formData.append('total_chunks', chunks);
        formData.append('file_name', file.name);
        
        await this.uploadChunk(formData);
    }
}
```

### Backend Implementation

```php
// Handle chunked uploads
public function uploadChunk(Request $request)
{
    $chunk = $request->file('chunk');
    $index = $request->input('chunk_index');
    $total = $request->input('total_chunks');
    $fileName = $request->input('file_name');
    
    $tempPath = "temp/chunks/{$fileName}.part{$index}";
    Storage::disk('local')->put($tempPath, $chunk);
    
    // If all chunks received, combine them
    if ($this->allChunksReceived($fileName, $total)) {
        $this->combineChunks($fileName, $total);
    }
    
    return response()->json(['success' => true]);
}
```

## Error Recovery

### Resumable Uploads

```php
// Check for existing partial uploads
public function checkExistingUpload(Request $request)
{
    $fileHash = $request->input('file_hash');
    
    $existingFile = InvoiceUploadFile::where('file_hash', $fileHash)
        ->where('status', 'uploading')
        ->first();
    
    if ($existingFile) {
        return response()->json([
            'exists' => true,
            'uploaded_bytes' => Storage::size($existingFile->temp_path),
            'file_id' => $existingFile->id
        ]);
    }
    
    return response()->json(['exists' => false]);
}
```

## Adding File Preview

### PDF Preview

```javascript
// Show PDF in modal
previewPDF(fileId) {
    const modal = document.getElementById('preview-modal');
    const viewer = document.getElementById('pdf-viewer');
    
    viewer.src = `/invoices/bulk-upload/file/${fileId}/preview`;
    modal.style.display = 'block';
}
```

### Image Thumbnail Generation

```php
// Generate thumbnails for images
use Intervention\Image\Facades\Image;

public function generateThumbnail(InvoiceUploadFile $file)
{
    if (!$file->isImage()) {
        return null;
    }
    
    $image = Image::make($file->temp_file_path);
    $image->resize(200, 200, function ($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    });
    
    $thumbnailPath = "temp/thumbnails/{$file->id}.jpg";
    Storage::disk('local')->put($thumbnailPath, $image->encode('jpg'));
    
    return $thumbnailPath;
}
```

## Batch Operations

### Select All/None

```javascript
// Add to Alpine component
selectAll() {
    this.selectedFiles = this.files.map(f => f.id);
},

selectNone() {
    this.selectedFiles = [];
},

removeSelected() {
    this.files = this.files.filter(f => !this.selectedFiles.includes(f.id));
    this.selectedFiles = [];
}
```

### Bulk Actions

```php
// Bulk delete files
public function bulkDelete(Request $request, $batchId)
{
    $batch = InvoiceBulkUpload::where('batch_id', $batchId)
        ->where('user_id', auth()->id())
        ->firstOrFail();
    
    $fileIds = $request->input('file_ids', []);
    
    InvoiceUploadFile::whereIn('id', $fileIds)
        ->where('bulk_upload_id', $batch->id)
        ->each(function ($file) {
            $file->deleteTempFile();
            $file->delete();
        });
    
    $batch->updateStatistics();
    
    return response()->json(['success' => true]);
}
```

## Testing

### Feature Test Example

```php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BulkUploadTest extends TestCase
{
    public function test_user_can_upload_multiple_files()
    {
        Storage::fake('local');
        
        $user = User::factory()->create();
        
        $files = [
            UploadedFile::fake()->create('invoice1.pdf', 1000),
            UploadedFile::fake()->create('invoice2.pdf', 2000),
        ];
        
        $response = $this->actingAs($user)
            ->post('/invoices/bulk-upload/upload', [
                'files' => $files
            ]);
        
        $response->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('invoice_bulk_uploads', [
            'user_id' => $user->id,
            'total_files' => 2
        ]);
        
        // Assert files were stored
        foreach ($files as $file) {
            Storage::disk('local')->assertExists('temp/invoices');
        }
    }
    
    public function test_upload_validates_file_types()
    {
        $user = User::factory()->create();
        
        $file = UploadedFile::fake()->create('document.exe', 1000);
        
        $response = $this->actingAs($user)
            ->post('/invoices/bulk-upload/upload', [
                'files' => [$file]
            ]);
        
        $response->assertSessionHasErrors('files.0');
    }
}
```

## Performance Optimization

### Database Indexes

```php
// Add indexes for common queries
Schema::table('invoice_upload_files', function (Blueprint $table) {
    $table->index(['bulk_upload_id', 'status']);
    $table->index('file_hash');
});
```

### Eager Loading

```php
// Optimize queries with eager loading
$batch = InvoiceBulkUpload::with([
    'files' => function ($query) {
        $query->select('id', 'bulk_upload_id', 'status', 'original_filename');
    },
    'user:id,name'
])->find($id);
```

### Caching

```php
// Cache recent uploads
$recentUploads = Cache::remember("user.{$userId}.recent_uploads", 300, function () use ($userId) {
    return InvoiceBulkUpload::where('user_id', $userId)
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get();
});
```

## Security Enhancements

### Virus Scanning

```php
// Integrate with ClamAV
use Sunspikes\ClamavValidator\ClamavValidator;

$rules = [
    'files.*' => ['required', 'file', 'clamav'],
];
```

### File Type Verification

```php
// Verify file type beyond extension
public function verifyFileType(UploadedFile $file): bool
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file->getPathname());
    finfo_close($finfo);
    
    return in_array($mimeType, config('invoices.bulk_upload.allowed_mime_types'));
}
```

## Debugging Tips

### Enable Debug Logging

```php
// In controller
Log::channel('bulk_upload')->info('Upload started', [
    'batch_id' => $batch->batch_id,
    'files' => $request->file('files'),
    'user' => auth()->id()
]);
```

### Laravel Telescope

Install Laravel Telescope for debugging:
```bash
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

### Common Issues

1. **Memory Limit**: Increase PHP memory limit for large files
2. **Timeout**: Increase max_execution_time
3. **Permissions**: Ensure storage/app/temp is writable
4. **Queue Workers**: Ensure queue workers are running

## Deployment Checklist

- [ ] Run migrations on production
- [ ] Set environment variables
- [ ] Configure queue workers
- [ ] Set up storage permissions
- [ ] Test file upload limits
- [ ] Configure monitoring
- [ ] Set up cleanup cron job
- [ ] Review security settings