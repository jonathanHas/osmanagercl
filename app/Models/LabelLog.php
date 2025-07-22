<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabelLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'barcode',
        'event_type',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Event type constants
     */
    const EVENT_NEW_PRODUCT = 'new_product';
    const EVENT_PRICE_UPDATE = 'price_update';
    const EVENT_LABEL_PRINT = 'label_print';

    /**
     * Get the user that created this log entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product associated with this log entry.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'barcode', 'CODE');
    }

    /**
     * Scope to filter by event type.
     */
    public function scopeEventType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope to filter by barcode.
     */
    public function scopeForBarcode($query, string $barcode)
    {
        return $query->where('barcode', $barcode);
    }

    /**
     * Log a new product event.
     */
    public static function logNewProduct(string $barcode): self
    {
        return self::create([
            'barcode' => $barcode,
            'event_type' => self::EVENT_NEW_PRODUCT,
        ]);
    }

    /**
     * Log a price update event.
     */
    public static function logPriceUpdate(string $barcode): self
    {
        return self::create([
            'barcode' => $barcode,
            'event_type' => self::EVENT_PRICE_UPDATE,
        ]);
    }

    /**
     * Log a label print event.
     */
    public static function logLabelPrint(string $barcode, ?int $userId = null): self
    {
        return self::create([
            'barcode' => $barcode,
            'event_type' => self::EVENT_LABEL_PRINT,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }
}
