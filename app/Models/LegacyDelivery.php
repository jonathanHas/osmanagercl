<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyDelivery extends Model
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
    protected $table = 'delivery';

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
        'prodName',
        'supCode',
        'cost',
        'caseUnits',
        'myOrder',
        'rrPrice',
        'orderNumber',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'cost' => 'decimal:4',
        'rrPrice' => 'decimal:4',
        'caseUnits' => 'integer',
        'myOrder' => 'integer',
    ];

    /**
     * Get the supplier link for this delivery item.
     */
    public function supplierLink()
    {
        return $this->belongsTo(SupplierLink::class, 'supCode', 'SupplierCode');
    }
}
