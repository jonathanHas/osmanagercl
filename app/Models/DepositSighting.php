<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One supplier document line that showed a deposit (barrel) code for a
 * product: the evidence behind a ProductDeposit suggestion.
 */
class DepositSighting extends Model
{
    public const SOURCE_DELIVERY_ITEM = 'delivery_item';

    protected $fillable = [
        'supplier_id',
        'supplier_code',
        'barrel_code',
        'units',
        'source_type',
        'source_id',
        'seen_on',
    ];

    protected $casts = [
        'supplier_id' => 'integer',
        'units' => 'integer',
        'source_id' => 'integer',
        'seen_on' => 'date',
    ];
}
