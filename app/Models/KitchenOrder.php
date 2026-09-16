<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One confirmed kitchen order to one supplier. Lives on the default (Laravel)
 * connection; supplier_id points at the POS suppliers table across databases.
 */
class KitchenOrder extends Model
{
    protected $fillable = [
        'user_id',
        'supplier_id',
        'supplier_name',
        'notes',
        'total_cases',
        'line_count',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'total_cases' => 'integer',
        'line_count' => 'integer',
    ];

    /**
     * The user who confirmed the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The POS supplier (cross-connection, same as OrderSession::supplier()).
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'SupplierID');
    }

    /**
     * The snapshotted order lines.
     */
    public function items(): HasMany
    {
        return $this->hasMany(KitchenOrderItem::class);
    }

    /**
     * File name for the CSV download, e.g. kitchen-order-udea-2026-09-16-12.csv
     */
    public function csvFilename(): string
    {
        $date = $this->created_at ? $this->created_at->format('Y-m-d') : now()->format('Y-m-d');

        return sprintf(
            'kitchen-order-%s-%s-%d.csv',
            Str::slug($this->supplier_name) ?: 'supplier',
            $date,
            $this->id
        );
    }
}
