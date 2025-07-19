<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCurrent extends Model
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
    protected $table = 'STOCKCURRENT';

    /**
     * The primary key for the model.
     * Since this table has a composite key, we'll use PRODUCT as primary for relationships.
     *
     * @var string
     */
    protected $primaryKey = 'PRODUCT';

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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'UNITS' => 'decimal:2',
    ];

    /**
     * Get the product for this stock record.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'PRODUCT', 'ID');
    }
}
