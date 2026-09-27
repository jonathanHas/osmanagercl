<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasPermissions, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pin_hash',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin_set_at' => 'datetime',
        ];
    }

    /**
     * Get the role that owns the user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * May this user sign in on a trusted Shop device with a PIN?
     *
     * Managers and admins never use PINs: a PIN session is confined to the
     * Shop, and their job is the office. One place to widen later, driven by
     * config('shop.pin_roles').
     */
    public function canUsePin(): bool
    {
        return $this->hasAnyRole(config('shop.pin_roles', ['employee']));
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }

    /**
     * Store a PIN. The digits are hashed and never kept; the length is, so the
     * PIN pad can draw the right number of dots.
     */
    public function setPin(string $pin): void
    {
        $this->forceFill([
            'pin_hash' => Hash::make($pin),
            'pin_length' => strlen($pin),
            'pin_set_at' => now(),
        ])->save();
    }

    public function clearPin(): void
    {
        $this->forceFill([
            'pin_hash' => null,
            'pin_length' => null,
            'pin_set_at' => null,
        ])->save();
    }

    public function checkPin(string $pin): bool
    {
        if (! $this->hasPin()) {
            return false;
        }

        return Hash::check($pin, $this->pin_hash);
    }
}
