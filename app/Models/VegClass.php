<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VegClass extends Model
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
    protected $table = 'class';

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
        'classNum',
        'class',
    ];

    /**
     * Get the class name attribute (for backward compatibility).
     */
    public function getNameAttribute()
    {
        return $this->class;
    }

    /**
     * Get the sort order attribute (using classNum).
     */
    public function getSortOrderAttribute()
    {
        return $this->classNum;
    }

    /**
     * Get the veg details for this class.
     */
    public function vegDetails()
    {
        return $this->hasMany(VegDetails::class, 'classId', 'ID');
    }
}
