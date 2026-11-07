<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A till: one POS host (`CLOSEDCASH.HOST`) with a stable id.
 *
 * `till_id` on reconciliations, lodgements and customer payments is this id. New
 * hosts are registered the first time a till close for them is seen
 * (TillCountService::tills()).
 */
class Till extends Model
{
    protected $fillable = ['pos_host', 'name', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(CashReconciliation::class, 'till_id');
    }

    public static function forHost(string $host): self
    {
        return static::firstOrCreate(
            ['pos_host' => $host],
            ['name' => $host, 'is_active' => true, 'sort_order' => (int) static::max('sort_order') + 1]
        );
    }
}
