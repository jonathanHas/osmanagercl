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
        'outer_code',
        'quantity_type',
        'description',
        'units_per_case',
        'supplier_case_units',
        'unit_cost',
        'sale_price',
        'tax_amount',
        'tax_rate',
        'normalized_tax_rate',
        'line_value_ex_vat',
        'unit_cost_including_tax',
        'ordered_quantity',
        'received_quantity',
        'case_ordered_quantity',
        'case_received_quantity',
        'unit_ordered_quantity',
        'unit_received_quantity',
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

    /**
     * Get the effective case units for conversions
     * Prefers supplier_case_units from SupplierLink, falls back to units_per_case from CSV
     */
    public function getEffectiveCaseUnits(): int
    {
        return $this->supplier_case_units ?? $this->units_per_case ?? 1;
    }

    /**
     * Convert case quantity to unit quantity using effective case units
     */
    public function casesToUnits(int $caseQuantity): int
    {
        return $caseQuantity * $this->getEffectiveCaseUnits();
    }

    /**
     * Convert unit quantity to case quantity using effective case units
     */
    public function unitsToCases(int $unitQuantity): int
    {
        $caseUnits = $this->getEffectiveCaseUnits();

        return $caseUnits > 0 ? intval($unitQuantity / $caseUnits) : 0;
    }

    /**
     * Get total ordered quantity in units (handles mixed case/unit orders)
     */
    public function getTotalOrderedUnitsAttribute(): int
    {
        $caseUnits = $this->casesToUnits($this->case_ordered_quantity ?? 0);
        $individualUnits = $this->unit_ordered_quantity ?? 0;

        // Fallback to legacy ordered_quantity converted to units if new fields are empty
        if ($caseUnits === 0 && $individualUnits === 0 && $this->ordered_quantity) {
            return $this->quantity_type === 'case'
                ? $this->casesToUnits($this->ordered_quantity)
                : $this->ordered_quantity;
        }

        return $caseUnits + $individualUnits;
    }

    /**
     * Get total received quantity in units (handles mixed case/unit scanning)
     */
    public function getTotalReceivedUnitsAttribute(): int
    {
        $caseUnits = $this->casesToUnits($this->case_received_quantity ?? 0);
        $individualUnits = $this->unit_received_quantity ?? 0;

        // Fallback to legacy received_quantity if new fields are empty
        if ($caseUnits === 0 && $individualUnits === 0 && $this->received_quantity) {
            return $this->quantity_type === 'case'
                ? $this->casesToUnits($this->received_quantity)
                : $this->received_quantity;
        }

        return $caseUnits + $individualUnits;
    }

    /**
     * Get quantity difference in units
     */
    public function getQuantityDifferenceInUnitsAttribute(): int
    {
        return $this->total_received_units - $this->total_ordered_units;
    }

    /**
     * Update legacy quantity fields for backward compatibility
     */
    public function updateLegacyQuantities(): void
    {
        // Update ordered_quantity based on primary quantity type
        if ($this->quantity_type === 'case') {
            $this->ordered_quantity = $this->case_ordered_quantity ?? 0;
            $this->received_quantity = $this->case_received_quantity ?? 0;
        } else {
            $this->ordered_quantity = $this->total_ordered_units;
            $this->received_quantity = $this->total_received_units;
        }
    }

    /**
     * Add a case scan to this item
     */
    public function addCaseScan(int $caseQuantity = 1, ?string $scannedBy = null): void
    {
        $this->increment('case_received_quantity', $caseQuantity);
        $this->updateLegacyQuantities();
        $this->updateStatus();

        // Add to scan history
        $scanHistory = $this->scan_history ?? [];
        $scanHistory[] = [
            'type' => 'case',
            'quantity' => $caseQuantity,
            'units_equivalent' => $this->casesToUnits($caseQuantity),
            'scanned_by' => $scannedBy,
            'scanned_at' => now()->toISOString(),
        ];

        $this->update(['scan_history' => $scanHistory]);
    }

    /**
     * Add a unit scan to this item
     */
    public function addUnitScan(int $unitQuantity = 1, ?string $scannedBy = null): void
    {
        $this->increment('unit_received_quantity', $unitQuantity);
        $this->updateLegacyQuantities();
        $this->updateStatus();

        // Add to scan history
        $scanHistory = $this->scan_history ?? [];
        $scanHistory[] = [
            'type' => 'unit',
            'quantity' => $unitQuantity,
            'units_equivalent' => $unitQuantity,
            'scanned_by' => $scannedBy,
            'scanned_at' => now()->toISOString(),
        ];

        $this->update(['scan_history' => $scanHistory]);
    }

    /**
     * Update status based on unit quantities for accurate comparison
     */
    public function updateStatus(): void
    {
        $orderedUnits = $this->total_ordered_units;
        $receivedUnits = $this->total_received_units;

        $status = match (true) {
            $receivedUnits == 0 => 'pending',
            $receivedUnits < $orderedUnits => 'partial',
            $receivedUnits == $orderedUnits => 'complete',
            $receivedUnits > $orderedUnits => 'excess',
            default => 'pending'
        };

        $this->update(['status' => $status]);
    }

    /**
     * Check if this item has case barcode for case scanning
     */
    public function hasCaseBarcode(): bool
    {
        return ! empty($this->outer_code);
    }

    /**
     * Get formatted quantity display based on quantity type
     */
    public function getFormattedOrderedQuantityAttribute(): string
    {
        if ($this->quantity_type === 'case') {
            $cases = $this->case_ordered_quantity ?? 0;
            $units = $this->casesToUnits($cases);

            return "{$cases} cases ({$units} units)";
        } elseif ($this->quantity_type === 'mixed') {
            $cases = $this->case_ordered_quantity ?? 0;
            $units = $this->unit_ordered_quantity ?? 0;
            $totalUnits = $this->casesToUnits($cases) + $units;

            return "{$cases} cases + {$units} units = {$totalUnits} units";
        } else {
            return "{$this->unit_ordered_quantity} units";
        }
    }

    /**
     * Get formatted received quantity display
     */
    public function getFormattedReceivedQuantityAttribute(): string
    {
        $caseQty = $this->case_received_quantity ?? 0;
        $unitQty = $this->unit_received_quantity ?? 0;

        if ($caseQty > 0 && $unitQty > 0) {
            $totalUnits = $this->total_received_units;

            return "{$caseQty} cases + {$unitQty} units = {$totalUnits} units";
        } elseif ($caseQty > 0) {
            $units = $this->casesToUnits($caseQty);

            return "{$caseQty} cases ({$units} units)";
        } elseif ($unitQty > 0) {
            return "{$unitQty} units";
        } else {
            return '0';
        }
    }
}
