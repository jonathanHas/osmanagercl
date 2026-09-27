<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line per change of hands on a shared device: a manager trusting it, a
 * PIN sign-in (or a failed one), or a lock.
 */
class ShopSwitchLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'shop_device_id',
        'user_id',
        'event',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ShopDevice::class, 'shop_device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
