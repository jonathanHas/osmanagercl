<?php

namespace App\Support;

use App\Models\User;

/**
 * Resolves which of the two interfaces the current request belongs to.
 *
 * Shop mode is the simplified shop-floor UI (`/shop`); office mode is the
 * existing sidebar admin UI. Employees and baristas land in shop mode, other
 * roles in office mode, and either can be overridden per device by the
 * `ui_mode` cookie set from the user menu / sidebar switch.
 */
class UiMode
{
    public const SHOP = 'shop';

    public const OFFICE = 'office';

    public const COOKIE = 'ui_mode';

    private ?string $current = null;

    /**
     * The mode for the current request.
     */
    public function current(): string
    {
        if ($this->current !== null) {
            return $this->current;
        }

        // A request to a shop route is shop mode whatever the cookie says.
        if (request()->routeIs('shop.*')) {
            return $this->current = self::SHOP;
        }

        // A PIN sign-in is always a shop-floor sign-in (future PIN cycle).
        if (session('auth_via') === 'pin') {
            return $this->current = self::SHOP;
        }

        $cookie = request()->cookie(self::COOKIE);
        if ($cookie === self::SHOP || $cookie === self::OFFICE) {
            return $this->current = $cookie;
        }

        return $this->current = $this->forRole(auth()->user());
    }

    public function isShop(): bool
    {
        return $this->current() === self::SHOP;
    }

    public function isOffice(): bool
    {
        return $this->current() === self::OFFICE;
    }

    /**
     * The route name of the home screen for the current mode.
     */
    public function homeRoute(): string
    {
        return $this->isShop() ? 'shop.home' : 'dashboard';
    }

    /**
     * Where the given user should land after signing in.
     *
     * Same precedence as current(), but resolved against the user passed in
     * rather than the authenticated one, because the session guard is not
     * always populated yet at redirect time.
     */
    public function landingUrl(?User $user): string
    {
        $mode = self::OFFICE;

        if (session('auth_via') === 'pin') {
            $mode = self::SHOP;
        } else {
            $cookie = request()->cookie(self::COOKIE);
            $mode = ($cookie === self::SHOP || $cookie === self::OFFICE)
                ? $cookie
                : $this->forRole($user);
        }

        return $mode === self::SHOP
            ? route('shop.home', absolute: false)
            : route('dashboard', absolute: false);
    }

    /**
     * Default mode for a user with no explicit preference.
     */
    private function forRole(?User $user): string
    {
        if (! $user) {
            return self::SHOP;
        }

        return ($user->isEmployee() || $user->isBarista()) ? self::SHOP : self::OFFICE;
    }
}
