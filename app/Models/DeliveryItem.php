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
        'ordered_quantity',
        'received_quantity',
        'total_cost',
        'status',
        'product_id',
        'is_new_product',
        'scan_history',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'is_new_product' => 'boolean',
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
}
