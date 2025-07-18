<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
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
    protected $table = 'PRODUCTS';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

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
        'PRICEBUY' => 'decimal:2',
        'PRICESELL' => 'decimal:2',
        'STOCKCOST' => 'decimal:2',
        'STOCKVOLUME' => 'decimal:2',
        'STOCKUNITS' => 'decimal:2',
        'ISCOM' => 'boolean',
        'ISSCALE' => 'boolean',
        'ISKITCHEN' => 'boolean',
        'PRINTKB' => 'boolean',
        'SENDSTATUS' => 'boolean',
        'ISSERVICE' => 'boolean',
        'ISVPRICE' => 'integer',
        'ISVERPATRIB' => 'integer',
        'WARRANTY' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'IMAGE',
        'ATTRIBUTES',
    ];

    /**
     * Get the formatted price for display.
     *
     * @return string
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->PRICESELL, 2);
    }

    /**
     * Check if the product is a service.
     *
     * @return bool
     */
    public function isService(): bool
    {
        return (bool) $this->ISSERVICE;
    }

    /**
     * Check if the product is sold by weight/scale.
     *
     * @return bool
     */
    public function isSoldByWeight(): bool
    {
        return (bool) $this->ISSCALE;
    }

    /**
     * Check if the product is a kitchen item.
     *
     * @return bool
     */
    public function isKitchenItem(): bool
    {
        return (bool) $this->ISKITCHEN;
    }

    /**
     * Scope a query to only include active (non-service) products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('ISSERVICE', 0);
    }

    /**
     * Scope a query to only include products in stock.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInStock($query)
    {
        return $query->where('STOCKUNITS', '>', 0);
    }

    /**
     * Search products by name, code, or reference.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('NAME', 'like', '%' . $search . '%')
              ->orWhere('CODE', 'like', '%' . $search . '%')
              ->orWhere('REFERENCE', 'like', '%' . $search . '%');
        });
    }
}