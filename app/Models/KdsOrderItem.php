<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KdsOrderItem extends Model
{
    protected $fillable = [
        'kds_order_id',
        'product_id',
        'product_name',
        'display_name',
        'kind',
        'quantity',
        'modifiers',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'modifiers' => 'array',
    ];

    public function kdsOrder(): BelongsTo
    {
        return $this->belongsTo(KdsOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'ID');
    }

    public function getFormattedQuantityAttribute(): string
    {
        if ($this->quantity == intval($this->quantity)) {
            return intval($this->quantity);
        }

        return number_format($this->quantity, 3, '.', '');
    }

    public function getDisplayNameAttribute($value): string
    {
        return $value ?: $this->product_name;
    }

    /**
     * Sanitize the POS PRODUCTS.DISPLAY field into a plain string suitable for
     * the KDS. The POS stores till-button labels as HTML fragments like
     * `<html>Pain<br>au<br>Chocolat` — we strip the tags and collapse newlines.
     */
    public static function cleanPosDisplay(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = preg_replace('/<br\s*\/?>/i', ' ', $value);
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5);
        $s = trim(preg_replace('/\s+/', ' ', $s));

        return $s !== '' ? $s : null;
    }
}
