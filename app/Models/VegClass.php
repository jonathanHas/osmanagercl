<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VegClass extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'veg_classes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'sort_order',
    ];

    /**
     * Get the veg details for this class.
     */
    public function vegDetails()
    {
        return $this->hasMany(VegDetails::class, 'class_id');
    }
}
