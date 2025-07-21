<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_number',
        'supplier_id',
        'delivery_date',
        'status',
        'total_expected',
        'total_received',
        'import_data',
        'notes',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'total_expected' => 'decimal:2',
        'total_received' => 'decimal:2',
        'import_data' => 'array',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'SupplierID');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(DeliveryScan::class);
    }

    public function getCompletionPercentageAttribute(): float
    {
        if ($this->items->isEmpty()) {
            return 0;
        }

        $completeItems = $this->items->where('status', 'complete')->count();

        return ($completeItems / $this->items->count()) * 100;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'receiving' => 'bg-blue-100 text-blue-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'receiving']);
    }

    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }
}
