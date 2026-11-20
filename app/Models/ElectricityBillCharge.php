<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectricityBillCharge extends Model
{
    protected $fillable = [
        'electricity_bill_id', 'category', 'band', 'description', 'quantity', 'unit', 'rate',
        'period_start', 'period_end', 'net_amount', 'vat_rate',
    ];

    protected $casts = [
        'quantity' => 'float',
        'rate' => 'float',
        'net_amount' => 'float',
        'vat_rate' => 'float',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(ElectricityBill::class, 'electricity_bill_id');
    }
}
