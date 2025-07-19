<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'pos';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'TAXES';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'RATE' => 'decimal:4',
        'RATECASCADE' => 'decimal:4',
        'RATEORDER' => 'integer',
    ];

    /**
     * Get the tax category for this tax.
     */
    public function taxCategory()
    {
        return $this->belongsTo(TaxCategory::class, 'CATEGORY', 'ID');
    }

    /**
     * Get the products that use this tax.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'TAXCAT', 'CATEGORY');
    }

    /**
     * Get the formatted rate as a percentage.
     */
    public function getFormattedRateAttribute(): string
    {
        return number_format($this->RATE * 100, 1).'%';
    }

    /**
     * Get the rate as a percentage (0-100).
     */
    public function getRatePercentageAttribute(): float
    {
        return $this->RATE * 100;
    }

    /**
     * Calculate VAT amount for a given price.
     */
    public function calculateVatAmount(float $price): float
    {
        return $price * $this->RATE;
    }

    /**
     * Calculate gross price (price + VAT) for a given net price.
     */
    public function calculateGrossPrice(float $netPrice): float
    {
        return $netPrice * (1 + $this->RATE);
    }
}
