<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A grid connection, identified by its MPRN whoever the supplier is. */
class ElectricitySupplyPoint extends Model
{
    protected $fillable = ['mprn', 'name', 'mic_kva', 'dg_group'];

    public function meters(): HasMany
    {
        return $this->hasMany(ElectricityMeter::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(ElectricityBill::class);
    }
}
