<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'supplier_code',
        'sku',
        'barcode',
        'description',
        'units_per_case',
        'unit_cost',
        'sale_price',
        'tax_amount',
        'tax_rate',
        'normalized_tax_rate',
        'line_value_ex_vat',
        'unit_cost_including_tax',
        'ordered_quantity',
        'received_quantity',
        'total_cost',
        'status',
        'product_id',
        'is_new_product',
        'scan_history',
        'barcode_retrieval_failed',
        'barcode_retrieval_error',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'normalized_tax_rate' => 'decimal:2',
        'line_value_ex_vat' => 'decimal:2',
        'unit_cost_including_tax' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'is_new_product' => 'boolean',
        'barcode_retrieval_failed' => 'boolean',
        'scan_history' => 'array',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'ID');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(DeliveryScan::class);
    }

    public function getQuantityDifferenceAttribute(): int
    {
        return $this->received_quantity - $this->ordered_quantity;
    }

    public function getValueDifferenceAttribute(): float
    {
        return $this->quantity_difference * $this->unit_cost;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'bg-red-100 text-red-800',
            'partial' => 'bg-yellow-100 text-yellow-800',
            'complete' => 'bg-green-100 text-green-800',
            'missing' => 'bg-red-100 text-red-800',
            'excess' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getCompletionPercentageAttribute(): float
    {
        if ($this->ordered_quantity == 0) {
            return $this->received_quantity > 0 ? 100 : 0;
        }

        return min(100, ($this->received_quantity / $this->ordered_quantity) * 100);
    }

    public function updateStatus(): void
    {
        $status = match (true) {
            $this->received_quantity == 0 => 'pending',
            $this->received_quantity < $this->ordered_quantity => 'partial',
            $this->received_quantity == $this->ordered_quantity => 'complete',
            $this->received_quantity > $this->ordered_quantity => 'excess',
            default => 'pending'
        };

        $this->update(['status' => $status]);
    }

    public function addScan(int $quantity, ?string $scannedBy = null): void
    {
        $this->increment('received_quantity', $quantity);

        $scanHistory = $this->scan_history ?? [];
        $scanHistory[] = [
            'quantity' => $quantity,
            'scanned_by' => $scannedBy,
            'scanned_at' => now()->toISOString(),
        ];

        $this->update(['scan_history' => $scanHistory]);
        $this->updateStatus();
    }

    public function scopeForBarcode($query, string $barcode)
    {
        return $query->where('barcode', $barcode);
    }

    public function scopeForSupplierCode($query, string $code)
    {
        return $query->where('supplier_code', $code);
    }

    public function scopeWithDiscrepancies($query)
    {
        return $query->whereIn('status', ['partial', 'missing', 'excess']);
    }

    public function scopeNewProducts($query)
    {
        return $query->where('is_new_product', true);
    }

    /**
     * Get formatted tax rate for display
     */
    public function getFormattedTaxRateAttribute(): ?string
    {
        if (is_null($this->tax_rate)) {
            return null;
        }

        return number_format($this->tax_rate, 2).'%';
    }

    /**
     * Get formatted normalized tax rate for display
     */
    public function getFormattedNormalizedTaxRateAttribute(): ?string
    {
        if (is_null($this->normalized_tax_rate)) {
            return null;
        }

        return number_format($this->normalized_tax_rate, 1).'%';
    }

    /**
     * Get the tax rate that should be used for product creation
     */
    public function getRecommendedTaxRateAttribute(): ?float
    {
        return $this->normalized_tax_rate ?? $this->tax_rate;
    }

    /**
     * Get per-unit tax amount
     */
    public function getUnitTaxAmountAttribute(): ?float
    {
        if (is_null($this->tax_amount) || $this->ordered_quantity <= 0) {
            return null;
        }

        return $this->tax_amount / $this->ordered_quantity;
    }

    /**
     * Check if this item has Independent pricing data
     */
    public function hasIndependentPricingData(): bool
    {
        return ! is_null($this->sale_price) || ! is_null($this->tax_rate);
    }

    /**
     * Check if tax rate indicates this might be a deposit scheme item
     */
    public function isPotentialDepositScheme(): bool
    {
        return ! is_null($this->tax_rate) && $this->tax_rate > 50;
    }
}
