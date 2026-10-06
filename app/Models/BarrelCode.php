<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarrelCode extends Model
{
    protected $fillable = [
        'supplier_code',
        'supplier_id',
        'description',
        'name',
        'unit_price',
        'is_active',
        'charge_customer',
        'pos_product_id',
        'pos_refund_product_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'is_active' => 'boolean',
        'charge_customer' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'SupplierID');
    }

    public function deliveryBarrels(): HasMany
    {
        return $this->hasMany(DeliveryBarrel::class);
    }

    public function productDeposits(): HasMany
    {
        return $this->hasMany(ProductDeposit::class);
    }
}
