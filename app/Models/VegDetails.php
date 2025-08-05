<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VegDetails extends Model
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
    protected $table = 'vegDetails';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ID',
        'product',
        'countryCode',
        'classId',
        'unitId',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'class_name',
        'unit_name',
    ];

    /**
     * Get the product that owns the veg details.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product', 'CODE');
    }

    /**
     * Get the country for this veg detail.
     */
    public function country()
    {
        // Cross-database relationship: POS vegDetails.countryCode -> main DB countries.id
        return $this->setConnection('mysql')->belongsTo(Country::class, 'countryCode', 'id');
    }

    /**
     * Get the class for this veg detail.
     */
    public function vegClass()
    {
        return $this->belongsTo(VegClass::class, 'classId', 'ID');
    }

    /**
     * Get the unit for this veg detail.
     */
    public function vegUnit()
    {
        // Cross-database relationship: POS vegDetails.unitId -> main DB veg_units.id
        return $this->setConnection('mysql')->belongsTo(VegUnit::class, 'unitId', 'id');
    }

    /**
     * Get the class name attribute for backward compatibility.
     */
    public function getClassNameAttribute()
    {
        return $this->vegClass ? $this->vegClass->name : '';
    }

    /**
     * Get the unit name attribute for backward compatibility.
     */
    public function getUnitNameAttribute()
    {
        return $this->vegUnit ? $this->vegUnit->abbreviation : 'kg';
    }
}
