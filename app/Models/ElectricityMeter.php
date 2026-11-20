<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectricityMeter extends Model
{
    protected $fillable = ['electricity_supply_point_id', 'serial', 'name'];

    public function supplyPoint(): BelongsTo
    {
        return $this->belongsTo(ElectricitySupplyPoint::class, 'electricity_supply_point_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(ElectricityMeterReading::class);
    }

    /** The name a manager gave it, or the serial number. */
    public function getLabelAttribute(): string
    {
        return $this->name ?: $this->serial;
    }
}
