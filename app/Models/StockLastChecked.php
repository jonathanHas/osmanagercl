<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLastChecked extends Model
{
    protected $connection = 'pos';

    protected $table = 'stockLastChecked';

    protected $primaryKey = 'Barcode';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'Barcode',
        'Date',
    ];

    protected $casts = [
        'Date' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'Barcode', 'CODE');
    }
}
