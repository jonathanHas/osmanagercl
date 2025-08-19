<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InvoiceUploadFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'bulk_upload_id',
        'original_filename',
        'stored_filename',
        'temp_path',
        'mime_type',
        'file_size',
        'file_hash',
        'status',
        'parsed_data',
        'parsing_errors',
        'parsing_confidence',
        'supplier_detected',
        'parsed_vat_data',
        'anomaly_warnings',
        'parsed_invoice_number',
        'parsed_invoice_date',
        'parsed_total_amount',
        'is_tax_free',
        'is_credit_note',
        'invoice_id',
        'error_message',
        'upload_progress',
        'uploaded_at',
        'parsed_at',
        'processed_at',
    ];

    protected $casts = [
        'parsed_data' => 'array',
        'parsing_errors' => 'array',
        'parsing_confidence' => 'float',
        'parsed_vat_data' => 'array',
        'anomaly_warnings' => 'array',
        'parsed_total_amount' => 'decimal:2',
        'is_tax_free' => 'boolean',
        'is_credit_note' => 'boolean',
        'file_size' => 'integer',
        'upload_progress' => 'integer',
        'uploaded_at' => 'datetime',
        'parsed_at' => 'datetime',
        'processed_at' => 'datetime',
        'parsed_invoice_date' => 'date',
    ];

    /**
     * Get the bulk upload batch this file belongs to.
     */
    public function bulkUpload(): BelongsTo
    {
        return $this->belongsTo(InvoiceBulkUpload::class, 'bulk_upload_id');
    }

    /**
     * Get the invoice created from this file.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Check if file is viewable in browser.
     */
    public function isViewable(): bool
    {
        $viewableMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/tiff',
        ];

        return in_array($this->mime_type, $viewableMimes);
    }

    /**
     * Check if file is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if file is a PDF.
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
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Get file extension.
     */
    public function getExtensionAttribute(): string
    {
        return strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION));
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'gray',
            'uploading' => 'blue',
            'uploaded', 'parsing' => 'yellow',
            'parsed', 'review' => 'purple',
            'completed' => 'green',
            'failed', 'rejected' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get status label for display.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'uploading' => 'Uploading...',
            'uploaded' => 'Uploaded',
            'parsing' => 'Parsing...',
            'parsed' => 'Parsed',
            'review' => 'Ready for Review',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'rejected' => 'Rejected',
            default => 'Unknown',
        };
    }

    /**
     * Check if file exists in temporary storage.
     */
    public function tempFileExists(): bool
    {
        if (! $this->temp_path) {
            return false;
        }

        return Storage::disk('local')->exists($this->temp_path);
    }

    /**
     * Get full path to temporary file.
     */
    public function getTempFilePathAttribute(): ?string
    {
        if (! $this->temp_path) {
            return null;
        }

        return Storage::disk('local')->path($this->temp_path);
    }

    /**
     * Delete temporary file.
     */
    public function deleteTempFile(): bool
    {
        if ($this->tempFileExists()) {
            return Storage::disk('local')->delete($this->temp_path);
        }

        return true;
    }

    /**
     * Mark as uploaded.
     */
    public function markAsUploaded(string $tempPath, string $hash): void
    {
        $this->update([
            'status' => 'uploaded',
            'temp_path' => $tempPath,
            'file_hash' => $hash,
            'upload_progress' => 100,
            'uploaded_at' => now(),
        ]);
    }

    /**
     * Mark as parsing.
     */
    public function markAsParsing(): void
    {
        $this->update([
            'status' => 'parsing',
        ]);
    }

    /**
     * Mark as parsed.
     */
    public function markAsParsed(array $data, ?float $confidence = null): void
    {
        // Extract specific fields from parsed data
        $updateData = [
            'status' => 'parsed',
            'parsed_data' => $data,
            'parsing_confidence' => $confidence,
            'parsed_at' => now(),
        ];

        // Store supplier information
        if (isset($data['supplier_name'])) {
            $updateData['supplier_detected'] = $data['supplier_name'];
        }

        // Store VAT breakdown
        if (isset($data['vat_breakdown'])) {
            $updateData['parsed_vat_data'] = $data['vat_breakdown'];
        }

        // Store anomaly warnings
        if (isset($data['warnings']) && ! empty($data['warnings'])) {
            $updateData['anomaly_warnings'] = $data['warnings'];
            // If there are warnings, set status to review instead of parsed
            $updateData['status'] = 'review';
        }

        // Store invoice metadata
        if (isset($data['invoice_number'])) {
            $updateData['parsed_invoice_number'] = $data['invoice_number'];
        }

        if (isset($data['invoice_date'])) {
            $updateData['parsed_invoice_date'] = $data['invoice_date'];
        }

        if (isset($data['total_amount'])) {
            $updateData['parsed_total_amount'] = $data['total_amount'];
        }

        if (isset($data['is_tax_free'])) {
            $updateData['is_tax_free'] = $data['is_tax_free'];
        }

        if (isset($data['is_credit_note'])) {
            $updateData['is_credit_note'] = $data['is_credit_note'];
        }

        $this->update($updateData);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    /**
     * Mark as completed with invoice.
     */
    public function markAsCompleted(int $invoiceId): void
    {
        $this->update([
            'status' => 'completed',
            'invoice_id' => $invoiceId,
            'processed_at' => now(),
        ]);
    }
}
