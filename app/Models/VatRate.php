<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VatRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'rate',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    /**
     * Get the current VAT rates.
     */
    public function scopeCurrent($query)
    {
        return $query->whereNull('effective_to')
            ->orWhere('effective_to', '>=', now());
    }

    /**
     * Get VAT rate for a specific date.
     */
    public function scopeEffectiveOn($query, Carbon $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }

    /**
     * Get formatted rate as percentage.
     */
    public function getFormattedRateAttribute(): string
    {
        return number_format($this->rate * 100, 1).'%';
    }

    /**
     * Get the rate as a percentage (0-100).
     */
    public function getRatePercentageAttribute(): float
    {
        return $this->rate * 100;
    }

    /**
     * Calculate VAT amount for a given net amount.
     */
    public function calculateVat(float $netAmount): float
    {
        return round($netAmount * $this->rate, 2);
    }

    /**
     * Calculate gross amount from net amount.
     */
    public function calculateGross(float $netAmount): float
    {
        return round($netAmount * (1 + $this->rate), 2);
    }

    /**
     * Calculate net amount from gross amount.
     */
    public function calculateNet(float $grossAmount): float
    {
        return round($grossAmount / (1 + $this->rate), 2);
    }

    /**
     * Get VAT rate by code for a specific date.
     */
    public static function getRateByCode(string $code, ?Carbon $date = null): ?self
    {
        $date = $date ?? now();

        return self::where('code', $code)
            ->effectiveOn($date)
            ->first();
    }

    /**
     * Get all available VAT codes.
     */
    public static function getAvailableCodes(?Carbon $date = null): array
    {
        $date = $date ?? now();

        return self::effectiveOn($date)
            ->pluck('name', 'code')
            ->toArray();
    }
}
