<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A PIN session is a shop-floor session (cycle 26).
 *
 * Signing in with four digits on a shared tablet is deliberately weaker than a
 * password, so it buys access to the Shop and nothing else. Anything outside
 * the allow-list asks for the password first, and confirming it drops the
 * `auth_via` flag — from then on the session is an ordinary one.
 *
 * The rule lives here rather than in the links because a link is a suggestion
 * and a URL bar is not.
 */
class ConfinePinSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('auth_via') !== 'pin') {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Confirm your password to use the office.'], 403);
        }

        // setIntendedUrl rather than redirect()->guest(): the session is alive
        // and ConfirmablePasswordController already redirects to ->intended().
        redirect()->setIntendedUrl($request->fullUrl());

        return redirect()->route('password.confirm');
    }

    /**
     * An unnamed route counts as not allowed: the list is by name, and a route
     * nobody named is not a Shop route anybody meant to expose.
     */
    private function isAllowed(Request $request): bool
    {
        $name = $request->route()?->getName();

        if (! is_string($name) || $name === '') {
            return false;
        }

        return Str::is(config('shop.pin_session_routes', []), $name);
    }
}
