<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KdsProduct extends Model
{
    protected $fillable = [
        'product_id',
        'product_name',
        'category_id',
        'category_name',
        'is_active',
        'trigger_mode',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('trigger_mode', 'primary');
    }

    public function scopeCompanion(Builder $query): Builder
    {
        return $query->where('trigger_mode', 'companion');
    }

    public function scopeExcluder(Builder $query): Builder
    {
        return $query->where('trigger_mode', 'excluder');
    }
}
