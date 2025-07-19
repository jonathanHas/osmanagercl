<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxCategory extends Model
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
    protected $table = 'TAXCATEGORIES';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the taxes for this category.
     */
    public function taxes()
    {
        return $this->hasMany(Tax::class, 'CATEGORY', 'ID');
    }

    /**
     * Get the products that use this tax category.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'TAXCAT', 'ID');
    }

    /**
     * Get the primary tax rate for this category.
     */
    public function primaryTax()
    {
        return $this->hasOne(Tax::class, 'CATEGORY', 'ID')
            ->orderBy('RATEORDER')
            ->orderBy('ID');
    }
}
