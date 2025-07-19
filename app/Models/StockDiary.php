<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockDiary extends Model
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
    protected $table = 'STOCKDIARY';

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
        'DATENEW' => 'datetime',
        'UNITS' => 'decimal:2',
        'PRICE' => 'decimal:2',
        'REASON' => 'integer',
    ];

    /**
     * Reason codes for stock movements.
     */
    const REASON_SALE = -1;
    const REASON_PURCHASE = 1;
    const REASON_MOVEMENT = 2;
    const REASON_ADJUSTMENT = 3;
    const REASON_RETURN = 4;

    /**
     * Get the product associated with this stock movement.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'PRODUCT', 'ID');
    }

    /**
     * Scope for sales transactions only.
     */
    public function scopeSales($query)
    {
        return $query->where('REASON', self::REASON_SALE);
    }

    /**
     * Scope for a specific date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('DATENEW', [$startDate, $endDate]);
    }

    /**
     * Get the formatted month and year for grouping.
     */
    public function getMonthYearAttribute()
    {
        return $this->DATENEW ? $this->DATENEW->format('Y-m') : null;
    }

    /**
     * Get the formatted month name.
     */
    public function getMonthNameAttribute()
    {
        return $this->DATENEW ? $this->DATENEW->format('F Y') : null;
    }
}