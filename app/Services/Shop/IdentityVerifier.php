<?php

namespace App\Services\Shop;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "Who are you?" without signing anyone in or out.
 *
 * The Shop's switch-user flow hands the whole session to the person who typed
 * the PIN. The till count needs something weaker and more precise: prove that a
 * particular person is standing at the device right now, record them as the
 * counter, and leave the signed-in session alone. The same per-user rate limit
 * as switch-user applies, so a guessed PIN is no easier here than there.
 */
class IdentityVerifier
{
    public function __construct(private readonly ShopDeviceService $devices) {}

    /**
     * Staff who can identify themselves on this request: employees with a PIN
     * (only on a trusted device), and everyone with a password.
     *
     * @return array<int, array{id: int, name: string, first_name: string, initials: string, pin: bool, pin_length: ?int, role: ?string}>
     */
    public function people(Request $request): array
    {
        $trusted = $this->devices->current($request) !== null;

        return User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['employee', 'manager', 'admin']))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => self::firstName($user),
                'initials' => self::initials($user),
                'pin' => $trusted && $user->canUsePin() && $user->hasPin(),
                'pin_length' => $user->pin_length ? (int) $user->pin_length : null,
                'role' => $user->role?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Check a PIN for a person. A PIN counts only on a trusted device, for a role
     * that may use one, for someone who has one set; anything else is refused the
     * same way as a wrong PIN, so the reply never confirms who has a PIN.
     *
     * $context names the log lines: 'identify' for the till count (logs the
     * success too), 'switch' for switch-user (which logs its own 'switch' after
     * signing in).
     *
     * @return array{ok: bool, error?: string, blocked?: bool}
     */
    public function verifyPin(User $user, string $pin, Request $request, string $context = 'identify'): array
    {
        $device = $this->devices->current($request);

        if (! $device || ! $user->canUsePin() || ! $user->hasPin()) {
            return ['ok' => false, 'error' => 'PIN sign-in is not available here. Use a password.'];
        }

        $key = self::pinKey($user);
        $attempts = (int) config('shop.pin_attempts', 5);
        $minutes = (int) config('shop.pin_lockout_minutes', 15);

        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            $this->devices->log($device, $user, $context.'_blocked', $request);

            return ['ok' => false, 'blocked' => true, 'error' => "Too many tries — wait {$minutes} minutes."];
        }

        if (! $user->checkPin($pin)) {
            RateLimiter::hit($key, $minutes * 60);
            $this->devices->log($device, $user, $context.'_failed', $request);

            return ['ok' => false, 'error' => 'Wrong PIN, try again.'];
        }

        RateLimiter::clear($key);

        if ($context === 'identify') {
            $this->devices->log($device, $user, 'identify', $request);
        }

        return ['ok' => true];
    }

    /**
     * Check a password for a person. Works anywhere, for anyone with a password.
     *
     * @return array{ok: bool, error?: string, blocked?: bool}
     */
    public function verifyPassword(User $user, string $password, Request $request): array
    {
        $key = self::passwordKey($user);
        $attempts = (int) config('till_count.password_attempts', 5);
        $minutes = (int) config('till_count.password_lockout_minutes', 15);

        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            $this->devices->log($this->devices->current($request), $user, 'identify_blocked', $request);

            return ['ok' => false, 'blocked' => true, 'error' => "Too many tries — wait {$minutes} minutes."];
        }

        if ($user->password === null || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, $minutes * 60);
            $this->devices->log($this->devices->current($request), $user, 'identify_failed', $request);

            return ['ok' => false, 'error' => 'Wrong password, try again.'];
        }

        RateLimiter::clear($key);
        $this->devices->log($this->devices->current($request), $user, 'identify', $request);

        return ['ok' => true];
    }

    /**
     * The per-user PIN limiter key, shared with SwitchUserController so a run of
     * wrong PINs on either screen counts once.
     */
    public static function pinKey(User $user): string
    {
        return 'pin:'.$user->id;
    }

    public static function passwordKey(User $user): string
    {
        return 'pwd:'.$user->id;
    }

    /** @return array<int, string> */
    public static function nameWords(User $user): array
    {
        return preg_split('/\s+/', trim($user->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public static function firstName(User $user): string
    {
        return self::nameWords($user)[0] ?? $user->name;
    }

    /** Same two-letter rule as the topbar chip. */
    public static function initials(User $user): string
    {
        $words = array_slice(self::nameWords($user), 0, 2);

        return strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), $words)));
    }
}
