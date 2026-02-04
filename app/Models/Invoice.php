<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'supplier_invoice_reference',
        'supplier_id',
        'supplier_name',
        'invoice_date',
        'due_date',
        'subtotal',
        'vat_amount',
        'total_amount',
        'standard_net',
        'standard_vat',
        'reduced_net',
        'reduced_vat',
        'second_reduced_net',
        'second_reduced_vat',
        'zero_net',
        'zero_vat',
        'payment_status',
        'payment_date',
        'payment_method',
        'payment_reference',
        'expense_category',
        'cost_center',
        'notes',
        'attachment_path',
        'created_by',
        'updated_by',
        'external_osaccounts_id',
        'vat_return_id',
        // RTD fields
        'rtd_breakdown',
        'rtd_status',
        'rtd_resolution_issues',
        'rtd_snapshot',
        'rtd_computed_at',
        'rtd_accepted_at',
        'rtd_accepted_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'payment_date' => 'date',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'standard_net' => 'decimal:2',
        'standard_vat' => 'decimal:2',
        'reduced_net' => 'decimal:2',
        'reduced_vat' => 'decimal:2',
        'second_reduced_net' => 'decimal:2',
        'second_reduced_vat' => 'decimal:2',
        'zero_net' => 'decimal:2',
        'zero_vat' => 'decimal:2',
        // RTD casts
        'rtd_breakdown' => 'array',
        'rtd_resolution_issues' => 'array',
        'rtd_snapshot' => 'array',
        'rtd_computed_at' => 'datetime',
        'rtd_accepted_at' => 'datetime',
    ];

    /**
     * Get the VAT lines for this invoice.
     */
    public function vatLines(): HasMany
    {
        return $this->hasMany(InvoiceVatLine::class)->ordered();
    }

    /**
     * Get the attachments for this invoice.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(InvoiceAttachment::class);
    }

    /**
     * Get the upload files for this invoice.
     */
    public function uploadFiles(): HasMany
    {
        return $this->hasMany(InvoiceUploadFile::class, 'invoice_id');
    }

    /**
     * Get all bank transactions linked through allocations
     */
    public function bankTransactions()
    {
        return $this->belongsToMany(BankTransaction::class, 'bank_transaction_allocations')
            ->withPivot('allocated_amount', 'allocation_type', 'notes', 'created_by')
            ->withTimestamps();
    }

    /**
     * Get all bank transaction allocations for this invoice
     */
    public function bankAllocations()
    {
        return $this->hasMany(BankTransactionAllocation::class);
    }

    /**
     * Get the total amount of payments received through bank transactions
     */
    public function getTotalPaymentsReceivedAttribute()
    {
        return $this->bankAllocations->sum('allocated_amount');
    }

    /**
     * Get the outstanding amount after all bank payments
     */
    public function getOutstandingAmountAttribute()
    {
        return $this->total_amount - $this->total_payments_received;
    }

    /**
     * Check if invoice has been partially paid through bank allocations
     */
    public function hasPartialPayments()
    {
        $totalPayments = $this->total_payments_received;

        return $totalPayments > 0 && $totalPayments < $this->total_amount;
    }

    /**
     * Check if invoice is fully paid through bank allocations
     */
    public function isFullyPaidByBank()
    {
        return abs($this->outstanding_amount) < 0.01;
    }

    /**
     * Get the supplier for this invoice.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(AccountingSupplier::class, 'supplier_id');
    }

    /**
     * Get the VAT return this invoice is assigned to.
     */
    public function vatReturn(): BelongsTo
    {
        return $this->belongsTo(VatReturn::class);
    }

    /**
     * Get the user who created this invoice.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this invoice.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Calculate and update totals from VAT lines.
     */
    public function calculateTotals(): void
    {
        // Calculate main totals
        $this->subtotal = $this->vatLines->sum('net_amount');
        $this->vat_amount = $this->vatLines->sum('vat_amount');
        $this->total_amount = $this->vatLines->sum('gross_amount');

        // Reset VAT breakdown fields
        $this->standard_net = 0;
        $this->standard_vat = 0;
        $this->reduced_net = 0;
        $this->reduced_vat = 0;
        $this->second_reduced_net = 0;
        $this->second_reduced_vat = 0;
        $this->zero_net = 0;
        $this->zero_vat = 0;

        // Calculate VAT breakdown from VAT lines
        foreach ($this->vatLines as $line) {
            switch ($line->vat_category) {
                case 'STANDARD':
                    $this->standard_net += $line->net_amount;
                    $this->standard_vat += $line->vat_amount;
                    break;
                case 'REDUCED':
                    $this->reduced_net += $line->net_amount;
                    $this->reduced_vat += $line->vat_amount;
                    break;
                case 'SECOND_REDUCED':
                    $this->second_reduced_net += $line->net_amount;
                    $this->second_reduced_vat += $line->vat_amount;
                    break;
                case 'ZERO':
                    $this->zero_net += $line->net_amount;
                    $this->zero_vat += $line->vat_amount;
                    break;
            }
        }

        $this->save();
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->payment_status === 'paid' || $this->payment_status === 'cancelled') {
            return false;
        }

        return $this->due_date && $this->due_date->isPast();
    }

    /**
     * Update payment status based on due date.
     */
    public function updatePaymentStatus(): void
    {
        if ($this->payment_status === 'pending' && $this->isOverdue()) {
            $this->payment_status = 'overdue';
            $this->save();
        }
    }

    /**
     * Get VAT breakdown by rate.
     */
    public function getVatBreakdown(): array
    {
        $breakdown = [];

        if ($this->standard_net > 0 || $this->standard_vat > 0) {
            $breakdown[] = [
                'code' => 'STANDARD',
                'rate' => 0.23,
                'net_amount' => $this->standard_net,
                'vat_amount' => $this->standard_vat,
                'gross_amount' => $this->standard_net + $this->standard_vat,
            ];
        }

        if ($this->reduced_net > 0 || $this->reduced_vat > 0) {
            $breakdown[] = [
                'code' => 'REDUCED',
                'rate' => 0.135,
                'net_amount' => $this->reduced_net,
                'vat_amount' => $this->reduced_vat,
                'gross_amount' => $this->reduced_net + $this->reduced_vat,
            ];
        }

        if ($this->second_reduced_net > 0 || $this->second_reduced_vat > 0) {
            $breakdown[] = [
                'code' => 'SECOND_REDUCED',
                'rate' => 0.09,
                'net_amount' => $this->second_reduced_net,
                'vat_amount' => $this->second_reduced_vat,
                'gross_amount' => $this->second_reduced_net + $this->second_reduced_vat,
            ];
        }

        if ($this->zero_net > 0) {
            $breakdown[] = [
                'code' => 'ZERO',
                'rate' => 0.00,
                'net_amount' => $this->zero_net,
                'vat_amount' => $this->zero_vat,
                'gross_amount' => $this->zero_net + $this->zero_vat,
            ];
        }

        return $breakdown;
    }

    /**
     * Scope for unpaid invoices.
     */
    public function scopeUnpaid($query)
    {
        return $query->whereIn('payment_status', ['pending', 'overdue', 'partial']);
    }

    /**
     * Scope for paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope for date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('invoice_date', [$startDate, $endDate]);
    }

    /**
     * Scope for unassigned invoices (not assigned to any VAT return).
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('vat_return_id');
    }

    /**
     * Scope for invoices up to a certain date.
     */
    public function scopeUpToDate($query, $date)
    {
        return $query->where('invoice_date', '<=', $date);
    }

    /**
     * Check if invoice can be assigned to a VAT return.
     */
    public function canBeAssignedToVatReturn(): bool
    {
        return is_null($this->vat_return_id) &&
               in_array($this->payment_status, ['paid', 'pending', 'overdue']);
    }

    /**
     * Get the primary attachment for this invoice.
     */
    public function primaryAttachment()
    {
        return $this->attachments()->where('is_primary', true)->first();
    }

    /**
     * Get attachments by type.
     */
    public function getAttachmentsByType(string $type)
    {
        return $this->attachments()->where('attachment_type', $type)->get();
    }

    /**
     * Check if invoice has any attachments.
     */
    public function hasAttachments(): bool
    {
        return $this->attachments()->exists();
    }

    /**
     * Get attachment count.
     */
    public function getAttachmentCountAttribute(): int
    {
        return $this->attachments()->count();
    }

    /**
     * Check if any attachment files are missing from disk.
     * Returns true if attachments exist in DB but at least one file is missing.
     */
    public function hasMissingAttachments(): bool
    {
        // If attachments are already loaded, use them
        if ($this->relationLoaded('attachments')) {
            foreach ($this->attachments as $attachment) {
                if (! $attachment->exists()) {
                    return true;
                }
            }

            return false;
        }

        // Otherwise query and check each attachment
        foreach ($this->attachments()->get() as $attachment) {
            if (! $attachment->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get count of missing attachment files.
     */
    public function getMissingAttachmentCountAttribute(): int
    {
        $count = 0;

        // If attachments are already loaded, use them
        if ($this->relationLoaded('attachments')) {
            foreach ($this->attachments as $attachment) {
                if (! $attachment->exists()) {
                    $count++;
                }
            }

            return $count;
        }

        // Otherwise query and check each attachment
        foreach ($this->attachments()->get() as $attachment) {
            if (! $attachment->exists()) {
                $count++;
            }
        }

        return $count;
    }

    // ==================== RTD Methods ====================

    /**
     * Check if invoice has RTD data.
     */
    public function hasRtdData(): bool
    {
        return ! empty($this->rtd_breakdown);
    }

    /**
     * Get total RTD amount (goods for resale).
     */
    public function getRtdTotal(): float
    {
        if (! $this->rtd_breakdown) {
            return 0;
        }
        $gfr = $this->rtd_breakdown['goods_for_resale'] ?? [];

        return array_sum($gfr);
    }

    /**
     * Check if all RTD lines are resolved (no unresolved issues).
     */
    public function isRtdComplete(): bool
    {
        if (! $this->rtd_breakdown) {
            return false;
        }

        return ($this->rtd_breakdown['unresolved']['count'] ?? 0) === 0;
    }

    /**
     * Check if RTD can be modified (not frozen).
     */
    public function canModifyRtd(): bool
    {
        return $this->rtd_status !== 'frozen';
    }

    /**
     * Check if RTD can be computed (has valid Udea or Dynamis upload file with line data).
     */
    public function canComputeRtd(): bool
    {
        $rtdService = app(\App\Services\RtdResolutionService::class);

        return $rtdService->getSourceUploadFile($this) !== null;
    }

    /**
     * Check if invoice can be re-parsed with RTD line parser.
     * True if: has RTD-supported upload file (Udea/Dynamis/Independent), has PDF on disk, but no line data yet.
     */
    public function canReparseForRtd(): bool
    {
        // Must have a PDF file that actually exists on disk
        if (! $this->hasPdfOnDisk()) {
            return false;
        }

        // Check for existing RTD-supported upload file (Udea, Dynamis, or Independent)
        $uploadFile = $this->uploadFiles()
            ->where(function ($q) {
                $q->where('supplier_detected', 'Udea')
                    ->orWhere('supplier_detected', 'Dynamis')
                    ->orWhere('supplier_detected', 'Independent');
            })
            ->whereNotNull('parsed_data')
            ->first();

        // Case 1: Has upload file without line data or VAT summary yet
        if ($uploadFile) {
            $hasLines = isset($uploadFile->parsed_data['lines']);
            $hasVatSummary = isset($uploadFile->parsed_data['vat_summary']);
            if (! $hasLines && ! $hasVatSummary) {
                return true;
            }

            // Case 1b: Independent invoices can be re-parsed if parsed by legacy parser
            // (legacy parser uses vat_breakdown, new RTD parser uses vat_summary with drs)
            if ($uploadFile->supplier_detected === 'Independent') {
                $hasVatSummary = isset($uploadFile->parsed_data['vat_summary']);
                $hasDrs = isset($uploadFile->parsed_data['drs']['total']);
                // Allow re-parse if missing vat_summary (legacy format) or missing DRS
                if (! $hasVatSummary || ! $hasDrs) {
                    return true;
                }
            }
        }

        // Case 2: No upload file but has RTD parser available (allows initial parsing)
        if (! $uploadFile && $this->hasRtdParser()) {
            return true;
        }

        return false;
    }

    /**
     * Check if this invoice is from a Udea supplier.
     */
    public function isUdeaSupplier(): bool
    {
        // Check linked supplier relationship
        if ($this->supplier && stripos($this->supplier->name, 'udea') !== false) {
            return true;
        }

        // Fall back to supplier_name field
        return stripos($this->supplier_name ?? '', 'udea') !== false;
    }

    /**
     * Check if this invoice is from a Dynamis supplier.
     */
    public function isDynamisSupplier(): bool
    {
        // Check linked supplier relationship
        if ($this->supplier && stripos($this->supplier->name, 'dynamis') !== false) {
            return true;
        }

        // Fall back to supplier_name field
        return stripos($this->supplier_name ?? '', 'dynamis') !== false;
    }

    /**
     * Check if this invoice is from an Independent Irish Health Foods supplier.
     */
    public function isIndependentSupplier(): bool
    {
        // Check linked supplier relationship
        if ($this->supplier && stripos($this->supplier->name, 'independent') !== false) {
            return true;
        }

        // Fall back to supplier_name field
        $name = strtolower($this->supplier_name ?? '');

        return str_contains($name, 'independent') || str_contains($name, 'iih');
    }

    /**
     * Check if this invoice has an RTD parser available.
     */
    public function hasRtdParser(): bool
    {
        return $this->isUdeaSupplier() || $this->isDynamisSupplier() || $this->isIndependentSupplier();
    }

    /**
     * Check if this invoice has a PDF file that actually exists on disk.
     */
    public function hasPdfOnDisk(): bool
    {
        $attachment = $this->attachments()->where('mime_type', 'application/pdf')->first();

        return $attachment && $attachment->exists();
    }

    /**
     * Get the user who accepted the RTD.
     */
    public function rtdAcceptedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rtd_accepted_by');
    }

    /**
     * Scope for invoices with pending RTD status.
     */
    public function scopeRtdPending($query)
    {
        return $query->where('rtd_status', 'pending');
    }

    /**
     * Scope for invoices with computed RTD status.
     */
    public function scopeRtdComputed($query)
    {
        return $query->where('rtd_status', 'computed');
    }

    /**
     * Scope for invoices with frozen RTD status.
     */
    public function scopeRtdFrozen($query)
    {
        return $query->where('rtd_status', 'frozen');
    }

    /**
     * Scope for invoices with RTD resolution issues.
     */
    public function scopeRtdWithIssues($query)
    {
        return $query->whereNotNull('rtd_resolution_issues')
            ->whereJsonLength('rtd_resolution_issues', '>', 0);
    }
}
