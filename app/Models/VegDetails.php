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
    protected $primaryKey = 'product';

    /**
     * The "type" of the primary key ID.
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
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

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
        return $this->belongsTo(Country::class, 'countryCode', 'ID');
    }

    /**
     * Get the class name (Extra, I, II).
     */
    public function getClassNameAttribute()
    {
        $classes = [
            1 => 'Extra',
            2 => 'I',
            3 => 'II',
        ];

        return $classes[$this->classId] ?? '';
    }

    /**
     * Get the unit name (kg, each, bunch, etc).
     */
    public function getUnitNameAttribute()
    {
        $units = [
            1 => 'kg',
            2 => 'each',
            3 => 'bunch',
            4 => 'punnet',
            5 => 'bag',
        ];

        return $units[$this->unitId] ?? 'kg';
    }
}
