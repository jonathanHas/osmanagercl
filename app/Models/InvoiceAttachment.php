<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoiceAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'original_filename',
        'stored_filename',
        'file_path',
        'mime_type',
        'file_size',
        'file_hash',
        'description',
        'attachment_type',
        'is_primary',
        'uploaded_by',
        'uploaded_at',
        'external_osaccounts_path',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'file_size' => 'integer',
        'is_primary' => 'boolean',
    ];

    /**
     * Get the invoice that owns this attachment.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the user who uploaded this attachment.
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
    public static function generateFilePath(int $invoiceId, string $storedFilename): string
    {
        $year = date('Y');
        $month = date('m');

        return "invoices/{$year}/{$month}/{$invoiceId}/{$storedFilename}";
    }

    /**
     * Get the full storage path for this attachment.
     */
    public function getFullStoragePathAttribute(): string
    {
        return Storage::disk('private')->path($this->file_path);
    }

    /**
     * Get the download URL for this attachment.
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('invoices.attachments.download', $this->id);
    }

    /**
     * Get the view URL for this attachment (for PDFs and images).
     */
    public function getViewUrlAttribute(): string
    {
        return route('invoices.attachments.view', $this->id);
    }

    /**
     * Get the embedded viewer URL for this attachment.
     */
    public function getViewerUrlAttribute(): string
    {
        return route('invoices.attachments.viewer', $this->id);
    }

    /**
     * Check if this attachment is viewable in browser.
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
        ];

        return in_array($this->mime_type, $viewableMimes);
    }

    /**
     * Check if this attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if this attachment is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
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
     * Get attachment type label for display.
     */
    public function getAttachmentTypeLabelAttribute(): string
    {
        $labels = [
            'invoice_scan' => 'Invoice Scan',
            'receipt' => 'Receipt',
            'delivery_note' => 'Delivery Note',
            'other' => 'Other',
        ];

        return $labels[$this->attachment_type] ?? 'Unknown';
    }

    /**
     * Get icon class for file type.
     */
    public function getIconClassAttribute(): string
    {
        if ($this->isPdf()) {
            return 'fa-file-pdf text-red-500';
        } elseif ($this->isImage()) {
            return 'fa-file-image text-blue-500';
        } else {
            return 'fa-file text-gray-500';
        }
    }

    /**
     * Check if attachment file exists on disk.
     */
    public function exists(): bool
    {
        return Storage::disk('private')->exists($this->file_path);
    }

    /**
     * Delete attachment file from storage.
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

        // When attachment is deleted, also delete the file
        static::deleting(function ($attachment) {
            $attachment->deleteFile();
        });

        // Set uploaded_at when creating
        static::creating(function ($attachment) {
            if (! $attachment->uploaded_at) {
                $attachment->uploaded_at = now();
            }
        });
    }

    /**
     * Scope to get primary attachments.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to get attachments by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('attachment_type', $type);
    }

    /**
     * Scope to get attachments uploaded by user.
     */
    public function scopeUploadedBy($query, int $userId)
    {
        return $query->where('uploaded_by', $userId);
    }
}
