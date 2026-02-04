<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RtdVatFallback extends Model
{
    protected $fillable = [
        'article_code',
        'supplier_id',
        'vat_rate',
        'description',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'vat_rate' => 'decimal:1',
    ];

    /**
     * Get the accounting supplier this fallback belongs to.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(AccountingSupplier::class);
    }

    /**
     * Get the user who created this fallback entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this fallback entry.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Find VAT rate for an article code by supplier ID.
     */
    public static function findVatRate(string $articleCode, int $supplierId): ?float
    {
        $fallback = self::where('article_code', $articleCode)
            ->where('supplier_id', $supplierId)
            ->first();

        return $fallback?->vat_rate;
    }

    /**
     * Scope to filter by supplier ID.
     */
    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Get the VAT rate as a formatted percentage string.
     */
    public function getVatRateFormattedAttribute(): string
    {
        return number_format($this->vat_rate, 1).'%';
    }
}
