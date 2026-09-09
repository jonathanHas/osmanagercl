<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistent cache of parsed Udea webshop-card data (case / single-unit buy tiers),
 * keyed by supplier_code. Written through on scrape by UdeaScrapingService::debugProductCard().
 */
class UdeaProductCard extends Model
{
    protected $table = 'udea_product_cards';

    protected $fillable = [
        'supplier_code',
        'supplier_id',
        'case_qty',
        'single_unit_available',
        'single_unit_price',
        'per_unit_case_price',
        'case_price',
        'unit_price',
        'units_per_case',
        'description',
        'purchase_tiers',
        'not_found',
        'scraped_at',
        'pallet_sve',
        'pallet_unit_volume',
        'pallet_scraped_at',
    ];

    protected $casts = [
        'case_qty' => 'integer',
        'units_per_case' => 'integer',
        'single_unit_available' => 'boolean',
        'not_found' => 'boolean',
        'purchase_tiers' => 'array',
        'scraped_at' => 'datetime',
        // Cast to float, not decimal:4 - a decimal cast returns strings, which silently
        // breaks the arithmetic in palletVolumeFor().
        'pallet_sve' => 'float',
        'pallet_unit_volume' => 'float',
        'pallet_scraped_at' => 'datetime',
    ];

    /**
     * Return the parsed-card shape used by the test page / scrape endpoint,
     * matching the keys produced by UdeaScrapingService::parseProductData().
     */
    public function toDataArray(): array
    {
        return [
            'case_qty' => $this->case_qty,
            'single_unit_available' => $this->single_unit_available,
            'single_unit_price' => $this->single_unit_price,
            'per_unit_case_price' => $this->per_unit_case_price,
            'case_price' => $this->case_price,
            'unit_price' => $this->unit_price,
            'units_per_case' => $this->units_per_case,
            'description' => $this->description,
            'purchase_tiers' => $this->purchase_tiers ?? [],
        ];
    }

    /**
     * Whether pallet volume figures have been captured for this product.
     */
    public function hasPalletData(): bool
    {
        return $this->pallet_sve !== null && $this->pallet_unit_volume !== null;
    }

    /**
     * Pallet volume consumed by ordering $qty of this product.
     *
     * Mirrors Udea's own calculation (js/orders/order.js:1556):
     *     lineVolume = sve * volume * qty
     *
     * Returns null when the product has no captured pallet data.
     */
    public function palletVolumeFor(float $qty): ?float
    {
        if (! $this->hasPalletData()) {
            return null;
        }

        return $this->pallet_sve * $this->pallet_unit_volume * $qty;
    }
}
