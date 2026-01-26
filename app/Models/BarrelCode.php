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
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'SupplierID');
    }

    public function deliveryBarrels(): HasMany
    {
        return $this->hasMany(DeliveryBarrel::class);
    }
}
