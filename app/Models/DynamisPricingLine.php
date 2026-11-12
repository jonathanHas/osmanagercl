<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a Dynamis invoice, with how it was priced for the till.
 * See docs/features/dynamis-pricing.md.
 */
class DynamisPricingLine extends Model
{
    public const MODE_PER_KG = 'per_kg';

    public const MODE_EACH = 'each';

    public const MODE_SKIP = 'skip';

    public const MODES = [self::MODE_PER_KG, self::MODE_EACH, self::MODE_SKIP];

    protected $fillable = [
        'delivery_id',
        'delivery_item_id',
        'line_no',
        'dynamis_code',
        'description',
        'origin',
        'unit',
        'packages',
        'quantity',
        'net_unit_price',
        'line_total',
        'ean',
        'is_barcoded',
        'suggested_product_id',
        'suggested_confidence',
        'pricing_mode',
        'units_per_box',
        'kg_per_box',
        'count_source',
        'applied_price',
        'applied_at',
        'applied_by',
    ];

    protected $casts = [
        'packages' => 'integer',
        'quantity' => 'float',
        'net_unit_price' => 'float',
        'line_total' => 'float',
        'is_barcoded' => 'boolean',
        'suggested_confidence' => 'float',
        'units_per_box' => 'float',
        'kg_per_box' => 'float',
        'applied_price' => 'float',
        'applied_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function deliveryItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryItem::class);
    }
}
