<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_invoice_id',
        'pos_product_id',
        'pos_product_code',
        'description',
        'quantity',
        'unit_price',
        'vat_rate',
        'net_amount',
        'vat_amount',
        'gross_amount',
        'position',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'vat_rate' => 'decimal:4',
        'net_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->calculateAmounts();
        });

        static::saved(function ($item) {
            $item->invoice?->calculateTotals();
        });

        static::deleted(function ($item) {
            $item->invoice?->calculateTotals();
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    /**
     * Calculate line amounts. unit_price is treated as NET (matches POS PRICESELL convention
     * and InvoiceItem). Same rounding strategy as InvoiceItem::calculateAmounts() — round per
     * line, then sum at the parent.
     */
    public function calculateAmounts(): void
    {
        $this->net_amount = round((float) $this->quantity * (float) $this->unit_price, 2);
        $this->vat_amount = round((float) $this->net_amount * (float) $this->vat_rate, 2);
        $this->gross_amount = round((float) $this->net_amount + (float) $this->vat_amount, 2);
    }

    public function getFormattedVatRateAttribute(): string
    {
        return number_format($this->vat_rate * 100, 1).'%';
    }
}
