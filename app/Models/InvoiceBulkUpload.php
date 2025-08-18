<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InvoiceBulkUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'user_id',
        'total_files',
        'processed_files',
        'successful_files',
        'failed_files',
        'status',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Generate a unique batch ID.
     */
    public static function generateBatchId(): string
    {
        return 'BATCH-' . strtoupper(Str::random(8)) . '-' . time();
    }

    /**
     * Get the user who created this batch.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the files in this batch.
     */
    public function files(): HasMany
    {
        return $this->hasMany(InvoiceUploadFile::class, 'bulk_upload_id');
    }

    /**
     * Get pending files.
     */
    public function pendingFiles(): HasMany
    {
        return $this->files()->where('status', 'pending');
    }

    /**
     * Get failed files.
     */
    public function failedFiles(): HasMany
    {
        return $this->files()->where('status', 'failed');
    }

    /**
     * Get successful files.
     */
    public function successfulFiles(): HasMany
    {
        return $this->files()->where('status', 'completed');
    }

    /**
     * Calculate progress percentage.
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_files == 0) {
            return 0;
        }

        return (int) (($this->processed_files / $this->total_files) * 100);
    }

    /**
     * Check if batch is complete.
     */
    public function isComplete(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled']);
    }

    /**
     * Check if batch can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'uploading', 'processing']);
    }

    /**
     * Update batch statistics.
     */
    public function updateStatistics(): void
    {
        $this->processed_files = $this->files()
            ->whereNotIn('status', ['pending', 'uploading'])
            ->count();
        
        $this->successful_files = $this->files()
            ->where('status', 'completed')
            ->count();
        
        $this->failed_files = $this->files()
            ->whereIn('status', ['failed', 'rejected'])
            ->count();
        
        // Update status if all files are processed
        if ($this->processed_files >= $this->total_files && $this->total_files > 0) {
            if ($this->failed_files == $this->total_files) {
                $this->status = 'failed';
            } else {
                $this->status = 'completed';
            }
            $this->completed_at = now();
        }
        
        $this->save();
    }

    /**
     * Mark batch as started.
     */
    public function markAsStarted(): void
    {
        $this->status = 'processing';
        $this->started_at = now();
        $this->save();
    }

    /**
     * Cancel the batch.
     */
    public function cancel(): void
    {
        if ($this->canBeCancelled()) {
            $this->status = 'cancelled';
            $this->completed_at = now();
            $this->save();
            
            // Cancel all pending files
            $this->files()
                ->whereIn('status', ['pending', 'uploading', 'parsing'])
                ->update(['status' => 'rejected']);
        }
    }
}