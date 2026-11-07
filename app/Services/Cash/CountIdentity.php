<?php

namespace App\Services\Cash;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Session\Session;

/**
 * "Counting as X": who has just proved who they are on this device.
 *
 * Held in the session for a short while so the count can be filled in and saved
 * without typing the PIN twice, and forgotten the moment a count is saved. It is
 * not a login: the signed-in user is untouched and nothing outside the till count
 * reads it.
 */
class CountIdentity
{
    private const KEY = 'till_count.identity';

    public function __construct(private readonly Session $session) {}

    public function put(User $user, string $via): array
    {
        $identity = [
            'user_id' => $user->id,
            'via' => $via,
            'expires_at' => Carbon::now()->addMinutes((int) config('till_count.identity_minutes', 30))->toIso8601String(),
        ];

        $this->session->put(self::KEY, $identity);

        return $identity;
    }

    /** The identified person, or null when nobody has identified or it has lapsed. */
    public function current(): ?User
    {
        $identity = $this->session->get(self::KEY);

        if (! is_array($identity) || Carbon::parse($identity['expires_at'])->isPast()) {
            $this->forget();

            return null;
        }

        return User::find($identity['user_id']);
    }

    public function expiresAt(): ?Carbon
    {
        $identity = $this->session->get(self::KEY);

        return is_array($identity) ? Carbon::parse($identity['expires_at']) : null;
    }

    public function forget(): void
    {
        $this->session->forget(self::KEY);
    }
}
