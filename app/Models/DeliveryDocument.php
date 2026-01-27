<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DeliveryDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'original_filename',
        'stored_filename',
        'file_path',
        'mime_type',
        'file_size',
        'file_hash',
        'document_type',
        'is_primary',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'file_size' => 'integer',
        'is_primary' => 'boolean',
    ];

    /**
     * Get the delivery that owns this document.
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /**
     * Get the user who uploaded this document.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Generate a unique stored filename.
     */
    public static function generateStoredFilename(string $originalFilename): string
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);

        return Str::uuid().'.'.strtolower($extension);
    }

    /**
     * Generate the file path for storage.
     */
    public static function generateFilePath(int $deliveryId, string $storedFilename): string
    {
        $year = date('Y');
        $month = date('m');

        return "deliveries/{$year}/{$month}/{$deliveryId}/{$storedFilename}";
    }

    /**
     * Get the full storage path for this document.
     */
    public function getFullStoragePathAttribute(): string
    {
        return Storage::disk('private')->path($this->file_path);
    }

    /**
     * Get the download URL for this document.
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('delivery-documents.download', $this->id);
    }

    /**
     * Get the view URL for this document (for PDFs and images).
     */
    public function getViewUrlAttribute(): string
    {
        return route('delivery-documents.view', $this->id);
    }

    /**
     * Get the embedded viewer URL for this document.
     */
    public function getViewerUrlAttribute(): string
    {
        return route('delivery-documents.viewer', $this->id);
    }

    /**
     * Get the minimal viewer URL for this document (clean fullscreen view).
     */
    public function getViewerMinimalUrlAttribute(): string
    {
        return route('delivery-documents.viewer-minimal', $this->id);
    }

    /**
     * Check if this document is viewable in browser.
     */
    public function isViewable(): bool
    {
        $viewableMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'text/plain',
            'text/csv',
        ];

        return in_array($this->mime_type, $viewableMimes);
    }

    /**
     * Check if this document is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if this document is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Check if this document is a CSV.
     */
    public function isCsv(): bool
    {
        return in_array($this->mime_type, ['text/csv', 'text/plain', 'application/csv']);
    }

    /**
     * Get human-readable file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Get document type label for display.
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        $labels = [
            'invoice_pdf' => 'Supplier Invoice',
            'csv_import' => 'CSV Import',
            'delivery_note' => 'Delivery Note',
            'other' => 'Other',
        ];

        return $labels[$this->document_type] ?? 'Unknown';
    }

    /**
     * Get icon class for file type.
     */
    public function getIconClassAttribute(): string
    {
        if ($this->isPdf()) {
            return 'fa-file-pdf text-red-500';
        } elseif ($this->isCsv()) {
            return 'fa-file-csv text-green-500';
        } elseif ($this->isImage()) {
            return 'fa-file-image text-blue-500';
        } else {
            return 'fa-file text-gray-500';
        }
    }

    /**
     * Check if document file exists on disk.
     */
    public function exists(): bool
    {
        return Storage::disk('private')->exists($this->file_path);
    }

    /**
     * Delete document file from storage.
     */
    public function deleteFile(): bool
    {
        if ($this->exists()) {
            return Storage::disk('private')->delete($this->file_path);
        }

        return true;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // When document is deleted, also delete the file
        static::deleting(function ($document) {
            $document->deleteFile();
        });

        // Set uploaded_at when creating
        static::creating(function ($document) {
            if (! $document->uploaded_at) {
                $document->uploaded_at = now();
            }
        });
    }

    /**
     * Scope to get primary documents.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to get documents by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope to get documents uploaded by user.
     */
    public function scopeUploadedBy($query, int $userId)
    {
        return $query->where('uploaded_by', $userId);
    }
}
