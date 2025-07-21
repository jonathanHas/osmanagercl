<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'delivery_item_id',
        'barcode',
        'quantity',
        'matched',
        'scanned_by',
        'metadata',
    ];

    protected $casts = [
        'matched' => 'boolean',
        'metadata' => 'array',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function deliveryItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryItem::class);
    }

    public function getStatusIconAttribute(): string
    {
        return $this->matched ? '✓' : '✗';
    }

    public function getStatusColorAttribute(): string
    {
        return $this->matched ? 'text-green-600' : 'text-red-600';
    }

    public function scopeMatched($query)
    {
        return $query->where('matched', true);
    }

    public function scopeUnmatched($query)
    {
        return $query->where('matched', false);
    }

    public function scopeForDelivery($query, int $deliveryId)
    {
        return $query->where('delivery_id', $deliveryId);
    }

    public function scopeForBarcode($query, string $barcode)
    {
        return $query->where('barcode', $barcode);
    }

    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }
}
