<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;

class LineRemoved extends Model
{
    protected $connection = 'pos';

    protected $table = 'LINEREMOVED';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [];

    protected $casts = [
        'REMOVEDDATE' => 'datetime',
        'UNITS' => 'decimal:2',
    ];
}
