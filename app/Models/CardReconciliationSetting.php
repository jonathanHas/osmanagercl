<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardReconciliationSetting extends Model
{
    protected $fillable = [
        'user_id',
        'time_window_minutes',
        'auto_match_threshold',
    ];

    protected $casts = [
        'time_window_minutes' => 'integer',
        'auto_match_threshold' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getForUser(?int $userId = null): self
    {
        $userId = $userId ?? auth()->id();

        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'time_window_minutes' => 5,
                'auto_match_threshold' => 80,
            ]
        );
    }
}
