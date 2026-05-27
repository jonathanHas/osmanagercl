<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarvestProductUnit extends Model
{
    /**
     * The connection name for the model (Laravel primary DB).
     *
     * @var string
     */
    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_code',
        'unit',
    ];

    /**
     * Allowed measurement units for a harvested product.
     *
     * @var array<int, string>
     */
    public const UNITS = ['kg', 'unit'];
}
