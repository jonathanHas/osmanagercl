<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AmazonInvoicePending extends Model
{
    use HasFactory;

    protected $table = 'amazon_invoices_pending';

    protected $fillable = [
        'invoice_upload_file_id',
        'batch_id',
        'user_id',
        'invoice_date',
        'invoice_number',
        'gbp_amount',
        'parsed_data',
        'actual_payment_eur',
        'payment_entered_by',
        'payment_entered_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'gbp_amount' => 'decimal:2',
        'actual_payment_eur' => 'decimal:2',
        'parsed_data' => 'array',
        'payment_entered_at' => 'datetime',
    ];

    /**
     * Get the upload file that this pending invoice belongs to
     */
    public function uploadFile(): BelongsTo
    {
        return $this->belongsTo(InvoiceUploadFile::class, 'invoice_upload_file_id');
    }

    /**
     * Get the user who uploaded this invoice
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who entered the payment information
     */
    public function paymentEnteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_entered_by');
    }

    /**
     * Get the bulk upload batch this invoice belongs to
     */
    public function bulkUpload(): BelongsTo
    {
        return $this->belongsTo(InvoiceBulkUpload::class, 'batch_id', 'batch_id');
    }

    /**
     * Scope to get only pending invoices
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get invoices for a specific user
     */
    public function scopeForUser($query, $userId = null)
    {
        $userId = $userId ?? Auth::id();

        return $query->where('user_id', $userId);
    }

    /**
     * Check if payment has been entered
     */
    public function hasPaymentEntered(): bool
    {
        return ! is_null($this->actual_payment_eur) && $this->actual_payment_eur > 0;
    }

    /**
     * Check if this invoice can be processed
     */
    public function canBeProcessed(): bool
    {
        return $this->status === 'pending' && $this->hasPaymentEntered();
    }

    /**
     * Mark payment as entered
     */
    public function markPaymentEntered(float $eurAmount, ?string $notes = null): void
    {
        $this->update([
            'actual_payment_eur' => $eurAmount,
            'payment_entered_by' => Auth::id(),
            'payment_entered_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Mark as processing (when invoice creation starts)
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Mark as completed (when invoice is successfully created)
     */
    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Mark as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Get status label for display
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Awaiting Payment',
            'processing' => 'Creating Invoice',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => 'Unknown',
        };
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'processing' => 'blue',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get formatted GBP amount
     */
    public function getFormattedGbpAmountAttribute(): string
    {
        return $this->gbp_amount ? '£'.number_format($this->gbp_amount, 2) : 'N/A';
    }

    /**
     * Get formatted EUR amount
     */
    public function getFormattedEurAmountAttribute(): string
    {
        return $this->actual_payment_eur ? '€'.number_format($this->actual_payment_eur, 2) : 'Not entered';
    }

    /**
     * Calculate exchange rate if both amounts are available
     */
    public function getExchangeRateAttribute(): ?float
    {
        if (! $this->gbp_amount || ! $this->actual_payment_eur) {
            return null;
        }

        return round($this->actual_payment_eur / $this->gbp_amount, 4);
    }

    /**
     * Get days since creation
     */
    public function getDaysPendingAttribute(): int
    {
        return (int) $this->created_at->diffInDays(now());
    }
}
