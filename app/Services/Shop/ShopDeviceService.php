<?php

namespace App\Services\Shop;

use App\Models\ShopDevice;
use App\Models\ShopSwitchLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Trusted shop-floor devices (cycle 26).
 *
 * PIN sign-in is only offered on a device a manager has explicitly trusted, so
 * a lost phone with the Shop URL in its history is no better off than before.
 * The device proves itself with a long-lived random cookie; the database keeps
 * only the SHA-256 of that token.
 */
class ShopDeviceService
{
    /** One year: the tablet should not need re-trusting every term. */
    private const COOKIE_MINUTES = 60 * 24 * 365;

    /**
     * Resolved once per request — the middleware, the layout and the topbar all
     * ask. Memoised against the Request object rather than just "have I looked
     * yet", because the container is scoped per request in production but is
     * reused across requests in the test suite.
     */
    private ?Request $resolvedFor = null;

    private ?ShopDevice $resolved = null;

    /**
     * The trusted, unrevoked device this request came from, if any.
     */
    public function current(Request $request): ?ShopDevice
    {
        if ($this->resolvedFor === $request) {
            return $this->resolved;
        }

        $this->resolvedFor = $request;

        return $this->resolved = ShopDevice::findByToken($request->cookie(config('shop.device_cookie')));
    }

    /**
     * Trust this device. Returns the row and the cookie to attach to the
     * response — the caller decides where to redirect.
     *
     * @return array{device: ShopDevice, cookie: SymfonyCookie}
     */
    public function trust(Request $request, User $by, string $name): array
    {
        $token = Str::random(40);

        $device = ShopDevice::create([
            'name' => $name,
            'token_hash' => ShopDevice::hashToken($token),
            'registered_by' => $by->id,
            'last_user_id' => $by->id,
            'last_used_at' => now(),
        ]);

        $this->resolvedFor = $request;
        $this->resolved = $device;

        $this->log($device, $by, 'trust', $request);

        // `secure` follows the request: the dev tablet is on plain HTTP and
        // would otherwise never receive the cookie. Production is HTTPS.
        $cookie = Cookie::make(
            name: config('shop.device_cookie'),
            value: $token,
            minutes: self::COOKIE_MINUTES,
            path: '/',
            domain: null,
            secure: $request->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );

        return ['device' => $device, 'cookie' => $cookie];
    }

    /**
     * Record that someone is using this device now.
     */
    public function touch(ShopDevice $device, ?User $user): void
    {
        $device->forceFill([
            'last_used_at' => now(),
            'last_user_id' => $user?->id,
        ])->save();
    }

    /**
     * One line in the switch log. Events: trust, switch, switch_failed, lock.
     */
    public function log(?ShopDevice $device, ?User $user, string $event, Request $request): void
    {
        ShopSwitchLog::create([
            'shop_device_id' => $device?->id,
            'user_id' => $user?->id,
            'event' => $event,
            'ip' => $request->ip(),
        ]);
    }
}
