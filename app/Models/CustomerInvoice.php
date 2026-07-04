<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerInvoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'customer_name',
        'customer_address',
        'customer_vat_number',
        'customer_email',
        'issue_date',
        'due_date',
        'subtotal',
        'vat_total',
        'total',
        'discount_percent',
        'standard_net',
        'standard_vat',
        'reduced_net',
        'reduced_vat',
        'second_reduced_net',
        'second_reduced_vat',
        'zero_net',
        'zero_vat',
        'status',
        'notes',
        'created_by',
        'voided_at',
        'voided_by',
        'last_edited_at',
        'last_edited_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'voided_at' => 'datetime',
        'last_edited_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'vat_total' => 'decimal:2',
        'total' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'standard_net' => 'decimal:2',
        'standard_vat' => 'decimal:2',
        'reduced_net' => 'decimal:2',
        'reduced_vat' => 'decimal:2',
        'second_reduced_net' => 'decimal:2',
        'second_reduced_vat' => 'decimal:2',
        'zero_net' => 'decimal:2',
        'zero_vat' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerInvoiceItem::class)->orderBy('position');
    }

    /**
     * Allocations from non-void payments only — voided payments are excluded
     * from outstanding-balance maths via the join condition.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class)
            ->whereHas('payment', fn ($q) => $q->whereNull('voided_at'));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    /**
     * Drafts can be edited by anyone with manage permission.
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Admins can edit any non-void invoice — used to fix typos/mistakes after an
     * invoice has been issued. Edits are recorded in last_edited_at/by.
     * Voided invoices are immutable for audit reasons.
     */
    public function isAdminEditable(): bool
    {
        return $this->status !== self::STATUS_VOID;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    /**
     * Recalculate totals from line items, including per-VAT-band breakdown.
     * Bands match the supplier Invoice model so the PDF/UI can share the same shape.
     *
     * If discount_percent is set, the band columns and subtotal/vat_total/total
     * store POST-discount amounts (the customer-paid figures). Line-item amounts
     * remain at their listed prices — the discount is reconstructable as
     * getPreDiscountNet() - subtotal.
     */
    public function calculateTotals(): void
    {
        $items = $this->items()->get();
        $factor = max(0.0, 1.0 - ((float) $this->discount_percent / 100));

        // Group line nets by VAT band first (pre-discount).
        $bands = [
            'standard' => ['rate' => 0.23, 'net' => 0.0],
            'reduced' => ['rate' => 0.135, 'net' => 0.0],
            'second_reduced' => ['rate' => 0.09, 'net' => 0.0],
            'zero' => ['rate' => 0.0, 'net' => 0.0],
        ];

        foreach ($items as $item) {
            $rate = (string) (float) $item->vat_rate; // avoid float-key/decimal-string gotcha
            $key = match ($rate) {
                '0.23' => 'standard',
                '0.135' => 'reduced',
                '0.09' => 'second_reduced',
                '0', '0.0' => 'zero',
                default => 'standard',
            };
            $bands[$key]['net'] += (float) $item->net_amount;
        }

        // Apply discount factor per-band, then recompute VAT on the discounted net.
        $subtotal = 0.0;
        $vatTotal = 0.0;
        foreach ($bands as $key => $b) {
            $postNet = round($b['net'] * $factor, 2);
            $postVat = round($postNet * $b['rate'], 2);
            $this->{$key.'_net'} = $postNet;
            $this->{$key.'_vat'} = $postVat;
            $subtotal += $postNet;
            $vatTotal += $postVat;
        }

        $this->subtotal = round($subtotal, 2);
        $this->vat_total = round($vatTotal, 2);
        $this->total = round($subtotal + $vatTotal, 2);

        $this->save();
    }

    /**
     * Pre-discount net (sum of line net amounts).
     */
    public function getPreDiscountNet(): float
    {
        return round((float) $this->items->sum('net_amount'), 2);
    }

    /**
     * Discount amount on net (positive number; 0 when no discount applied).
     */
    public function getDiscountAmount(): float
    {
        return round($this->getPreDiscountNet() - (float) $this->subtotal, 2);
    }

    public function hasDiscount(): bool
    {
        return (float) $this->discount_percent > 0;
    }

    /**
     * Sum of allocations from non-void payments applied to this invoice.
     */
    public function getTotalPaidAttribute(): float
    {
        return round((float) $this->allocations->sum('amount'), 2);
    }

    /**
     * Date of the most recent non-void payment applied to this invoice, or null
     * if unpaid. Relies on the void-filtered `allocations` relationship; eager
     * load `allocations.payment` to avoid N+1 when using this in a list.
     */
    public function getLastPaymentDateAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->allocations
            ->map(fn ($a) => $a->payment?->payment_date)
            ->filter()
            ->max();
    }

    /**
     * Outstanding (positive). Clamped at 0 — overpayments don't make this negative;
     * the overpayment surfaces on the customer's account-credit balance instead.
     */
    public function getOutstandingAmountAttribute(): float
    {
        return round(max(0, (float) $this->total - $this->total_paid), 2);
    }

    /**
     * Derived payment status. void invoices report 'void' regardless of allocations.
     */
    public function paymentStatus(): string
    {
        if ($this->status === self::STATUS_VOID) {
            return 'void';
        }
        $paid = $this->total_paid;
        $total = (float) $this->total;
        if (abs($paid - $total) < 0.01) {
            return 'paid';
        }
        if ($paid > $total) {
            return 'overpaid';
        }
        if ($paid > 0) {
            return 'partial';
        }

        return 'unpaid';
    }

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

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeIssued($query)
    {
        return $query->where('status', self::STATUS_ISSUED);
    }

    public function scopeNotVoid($query)
    {
        return $query->where('status', '!=', self::STATUS_VOID);
    }
}
