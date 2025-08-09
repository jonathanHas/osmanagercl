<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;

class ClosedCash extends Model
{
    protected $connection = 'pos';
    protected $table = 'CLOSEDCASH';
    protected $primaryKey = 'MONEY';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [];

    protected $casts = [
        'DATESTART' => 'datetime',
        'DATEEND' => 'datetime',
        'NOSALES' => 'integer',
    ];

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'MONEY', 'MONEY');
    }
}