<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row of a bill's meter table: a previous and a present reading. */
class ElectricityMeterReading extends Model
{
    protected $fillable = [
        'electricity_bill_id', 'electricity_meter_id', 'band', 'period_start', 'period_end',
        'previous_reading', 'previous_type', 'present_reading', 'present_type', 'multiplier',
        'kwh', 'is_reversal',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'previous_reading' => 'float',
        'present_reading' => 'float',
        'multiplier' => 'float',
        'kwh' => 'float',
        'is_reversal' => 'boolean',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(ElectricityBill::class, 'electricity_bill_id');
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(ElectricityMeter::class, 'electricity_meter_id');
    }
}
