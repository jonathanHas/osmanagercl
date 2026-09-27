<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shared shop-floor device a manager has trusted for PIN sign-in.
 *
 * The device holds a random token in a long-lived cookie; only the SHA-256 of
 * that token is stored, so the column cannot be replayed as a credential.
 */
class ShopDevice extends Model
{
    /** @use HasFactory<\Database\Factories\ShopDeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'token_hash',
        'registered_by',
        'last_user_id',
        'last_used_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function lastUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * Hash a raw device token the way the column stores it.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The active device for a raw cookie token, if there is one.
     */
    public static function findByToken(?string $token): ?self
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        return static::where('token_hash', static::hashToken($token))
            ->whereNull('revoked_at')
            ->first();
    }
}
