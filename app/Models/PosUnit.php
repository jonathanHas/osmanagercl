<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosUnit extends Model
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
    protected $table = 'units';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

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
        'units',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['id', 'name', 'abbreviation'];

    /**
     * Get the id attribute (for template compatibility).
     */
    public function getIdAttribute()
    {
        return $this->attributes['ID'] ?? null;
    }

    /**
     * Get the name attribute.
     */
    public function getNameAttribute()
    {
        // Expand abbreviations to full names
        $names = [
            'kg' => 'kilogram',
            'each' => 'each',
        ];

        return $names[$this->units] ?? $this->units;
    }

    /**
     * Get the abbreviation attribute.
     */
    public function getAbbreviationAttribute()
    {
        return $this->units;
    }

    /**
     * Get the veg details for this unit.
     */
    public function vegDetails()
    {
        return $this->hasMany(VegDetails::class, 'unitId', 'ID');
    }
}
