<?php

namespace App\Models;

use App\Services\DocumentConversionService;
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
        'converted_pdf_path',
        'mime_type',
        'file_size',
        'file_hash',
        'description',
        'attachment_type',
        'is_primary',
        'uploaded_by',
        'uploaded_at',
        'converted_at',
        'external_osaccounts_path',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'converted_at' => 'datetime',
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
     * Get the minimal embedded viewer URL for this attachment (for iframe embedding).
     */
    public function getViewerMinimalUrlAttribute(): string
    {
        return route('invoices.attachments.viewer-minimal', $this->id);
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

        // Also viewable if it can be converted to PDF
        if (in_array($this->mime_type, $viewableMimes)) {
            return true;
        }

        return $this->isConvertible();
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

        // When attachment is deleted, also delete the file and converted PDF
        static::deleting(function ($attachment) {
            $attachment->deleteFile();
            $attachment->deleteConvertedPdf();
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

    /**
     * Check if this attachment can be converted to PDF.
     */
    public function isConvertible(): bool
    {
        $conversionService = new DocumentConversionService;

        return $conversionService->canConvert($this->mime_type);
    }

    /**
     * Get or create the converted PDF version of this attachment.
     */
    public function getOrCreateConvertedPdf(): ?string
    {
        // If already a PDF or image, no conversion needed
        if ($this->isPdf() || $this->isImage()) {
            return $this->full_storage_path;
        }

        // If not convertible, return null
        if (! $this->isConvertible()) {
            return null;
        }

        // If already converted and file exists, return existing
        if ($this->converted_pdf_path && $this->convertedPdfExists()) {
            return Storage::disk('private')->path($this->converted_pdf_path);
        }

        // Convert the document
        return $this->convertToPdf();
    }

    /**
     * Convert this attachment to PDF.
     */
    public function convertToPdf(): ?string
    {
        $conversionService = new DocumentConversionService;

        $inputPath = $this->full_storage_path;

        // Use a temporary directory for conversion to avoid permission issues
        $tempOutputDir = sys_get_temp_dir().'/invoice_conversion_'.uniqid();
        if (! mkdir($tempOutputDir, 0755, true)) {
            return null;
        }

        $outputFilename = $conversionService->getConvertedPdfFilename($this->original_filename);

        // Convert to temporary directory first
        $convertedPath = $conversionService->convertToPdf($inputPath, $tempOutputDir, $outputFilename);

        if ($convertedPath && file_exists($convertedPath)) {
            // Use temp directory for converted files to avoid permission issues
            $finalConversionPath = 'temp/conversions/'.$this->invoice_id;
            if (! Storage::disk('private')->exists($finalConversionPath)) {
                Storage::disk('private')->makeDirectory($finalConversionPath, 0755, true);
            }

            // Move the converted file to final location using Laravel's Storage facade
            $relativeFinalPath = $finalConversionPath.'/'.$outputFilename;
            $fileContents = file_get_contents($convertedPath);

            if (Storage::disk('private')->put($relativeFinalPath, $fileContents)) {
                $finalPath = Storage::disk('private')->path($relativeFinalPath);
                // Set proper permissions on the final file
                @chmod($finalPath, 0644);

                // Store the relative path in database
                $this->update([
                    'converted_pdf_path' => $relativeFinalPath,
                    'converted_at' => now(),
                ]);

                // Clean up temp directory
                if (is_dir($tempOutputDir)) {
                    // Remove any remaining files first
                    $files = glob($tempOutputDir.'/*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                    rmdir($tempOutputDir);
                }

                return $finalPath;
            }
        }

        // Clean up temp directory on failure
        if (is_dir($tempOutputDir)) {
            array_map('unlink', glob($tempOutputDir.'/*'));
            rmdir($tempOutputDir);
        }

        return null;
    }

    /**
     * Check if the converted PDF file exists.
     */
    public function convertedPdfExists(): bool
    {
        return $this->converted_pdf_path &&
               Storage::disk('private')->exists($this->converted_pdf_path);
    }

    /**
     * Get the full path to the converted PDF file.
     */
    public function getConvertedPdfPath(): ?string
    {
        if ($this->convertedPdfExists()) {
            return Storage::disk('private')->path($this->converted_pdf_path);
        }

        return null;
    }

    /**
     * Check if this attachment needs conversion for viewing.
     */
    public function needsConversion(): bool
    {
        return $this->isConvertible() && ! $this->isPdf() && ! $this->isImage();
    }

    /**
     * Delete the converted PDF file.
     */
    public function deleteConvertedPdf(): bool
    {
        if ($this->converted_pdf_path && Storage::disk('private')->exists($this->converted_pdf_path)) {
            $deleted = Storage::disk('private')->delete($this->converted_pdf_path);

            if ($deleted) {
                $this->update([
                    'converted_pdf_path' => null,
                    'converted_at' => null,
                ]);
            }

            return $deleted;
        }

        return true;
    }
}
