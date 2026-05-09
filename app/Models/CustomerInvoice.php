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
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'voided_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'vat_total' => 'decimal:2',
        'total' => 'decimal:2',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
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
     */
    public function calculateTotals(): void
    {
        $items = $this->items()->get();

        $this->subtotal = round($items->sum('net_amount'), 2);
        $this->vat_total = round($items->sum('vat_amount'), 2);
        $this->total = round($items->sum('gross_amount'), 2);

        $this->standard_net = 0;
        $this->standard_vat = 0;
        $this->reduced_net = 0;
        $this->reduced_vat = 0;
        $this->second_reduced_net = 0;
        $this->second_reduced_vat = 0;
        $this->zero_net = 0;
        $this->zero_vat = 0;

        foreach ($items as $item) {
            $rate = (string) (float) $item->vat_rate; // avoid float-key/decimal-string gotcha

            switch ($rate) {
                case '0.23':
                    $this->standard_net += $item->net_amount;
                    $this->standard_vat += $item->vat_amount;
                    break;
                case '0.135':
                    $this->reduced_net += $item->net_amount;
                    $this->reduced_vat += $item->vat_amount;
                    break;
                case '0.09':
                    $this->second_reduced_net += $item->net_amount;
                    $this->second_reduced_vat += $item->vat_amount;
                    break;
                case '0':
                case '0.0':
                    $this->zero_net += $item->net_amount;
                    $this->zero_vat += $item->vat_amount;
                    break;
                default:
                    // Non-standard rates roll into standard bucket
                    $this->standard_net += $item->net_amount;
                    $this->standard_vat += $item->vat_amount;
                    break;
            }
        }

        $this->save();
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
