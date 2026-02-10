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
        'is_non_retail',
        'description',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'vat_rate' => 'decimal:1',
        'is_non_retail' => 'boolean',
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
     * Find fallback entry for an article code by supplier ID.
     * Returns vat_rate and is_non_retail, or null if no match.
     *
     * @return array{vat_rate: float, is_non_retail: bool}|null
     */
    public static function findFallback(string $articleCode, int $supplierId): ?array
    {
        $fallback = self::where('article_code', $articleCode)
            ->where('supplier_id', $supplierId)
            ->first();

        // If no exact match, try XX wildcard for DYN codes (country-agnostic fallback)
        if (! $fallback && preg_match('/^(DYN-.+)-[A-Z]{2}$/', $articleCode, $m)) {
            $fallback = self::where('article_code', $m[1].'-XX')
                ->where('supplier_id', $supplierId)
                ->first();
        }

        if (! $fallback) {
            return null;
        }

        return [
            'vat_rate' => (float) $fallback->vat_rate,
            'is_non_retail' => (bool) $fallback->is_non_retail,
        ];
    }

    /**
     * Find VAT rate for an article code by supplier ID.
     * Thin wrapper around findFallback() for backwards compatibility.
     */
    public static function findVatRate(string $articleCode, int $supplierId): ?float
    {
        $fallback = self::findFallback($articleCode, $supplierId);

        return $fallback ? $fallback['vat_rate'] : null;
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
