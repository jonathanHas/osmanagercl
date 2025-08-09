<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $connection = 'pos';
    protected $table = 'PAYMENTS';
    protected $primaryKey = 'ID';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [];

    protected $casts = [
        'TOTAL' => 'decimal:2',
        'TENDERED' => 'decimal:2',
        'CARDNAME' => 'string',
    ];

    public function receipt()
    {
        return $this->belongsTo(Receipt::class, 'RECEIPT', 'ID');
    }
}