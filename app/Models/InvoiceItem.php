<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'vat_code',
        'vat_rate',
        'vat_amount',
        'net_amount',
        'gross_amount',
        'expense_category',
        'cost_center',
        'gl_code',
        'department',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'vat_rate' => 'decimal:4',
        'vat_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
    ];

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Calculate amounts before saving
        static::saving(function ($item) {
            $item->calculateAmounts();
        });

        // Update invoice totals after saving
        static::saved(function ($item) {
            if ($item->invoice) {
                $item->invoice->calculateTotals();
            }
        });

        // Update invoice totals after deletion
        static::deleted(function ($item) {
            if ($item->invoice) {
                $item->invoice->calculateTotals();
            }
        });
    }

    /**
     * Get the invoice this item belongs to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the VAT rate details.
     */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class, 'vat_code', 'code');
    }

    /**
     * Calculate line amounts.
     */
    public function calculateAmounts(): void
    {
        // Calculate net amount
        $this->net_amount = round($this->quantity * $this->unit_price, 2);
        
        // Calculate VAT amount
        $this->vat_amount = round($this->net_amount * $this->vat_rate, 2);
        
        // Calculate gross amount
        $this->gross_amount = $this->net_amount + $this->vat_amount;
    }

    /**
     * Get formatted VAT rate percentage.
     */
    public function getFormattedVatRateAttribute(): string
    {
        return number_format($this->vat_rate * 100, 1) . '%';
    }

    /**
     * Set VAT rate from code.
     */
    public function setVatFromCode(string $code, \Carbon\Carbon $date = null): void
    {
        $date = $date ?? now();
        
        $vatRate = VatRate::where('code', $code)
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->first();

        if ($vatRate) {
            $this->vat_code = $vatRate->code;
            $this->vat_rate = $vatRate->rate;
        }
    }
}