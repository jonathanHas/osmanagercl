<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A shop-wide key/value setting (table app_settings). Values are stored as
 * strings; callers cast. Older callers still use the query builder on the
 * same table.
 */
class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()->whereKey($key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
    }
}
