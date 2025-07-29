<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VegUnit extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'veg_units';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'abbreviation',
        'plural_name',
        'sort_order',
    ];

    /**
     * Get the veg details for this unit.
     */
    public function vegDetails()
    {
        return $this->hasMany(VegDetails::class, 'unit_id');
    }
}
