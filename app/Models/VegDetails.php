<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VegDetails extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'veg_details';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_code',
        'country_id',
        'class_id',
        'unit_id',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

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
        return $this->setConnection('pos')->belongsTo(Product::class, 'product_code', 'CODE');
    }

    /**
     * Get the country for this veg detail.
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * Get the class for this veg detail.
     */
    public function vegClass()
    {
        return $this->belongsTo(VegClass::class, 'class_id');
    }

    /**
     * Get the unit for this veg detail.
     */
    public function vegUnit()
    {
        return $this->belongsTo(VegUnit::class, 'unit_id');
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
