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
        $this->subtotal = $this->vatLines->sum('net_amount');
        $this->vat_amount = $this->vatLines->sum('vat_amount');
        $this->total_amount = $this->vatLines->sum('gross_amount');
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
}
