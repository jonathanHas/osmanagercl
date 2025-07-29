<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stocking extends Model
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
    protected $table = 'stocking';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'Barcode';

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
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Barcode',
    ];

    /**
     * Get the product for this stocking record.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'Barcode', 'CODE');
    }
}
