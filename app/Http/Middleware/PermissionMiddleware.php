<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (! $request->user()) {
            abort(403, 'Unauthorized - No user authenticated');
        }

        if (! $request->user()->hasAnyPermission($permissions)) {
            abort(403, 'Unauthorized - You do not have the required permission');
        }

        return $next($request);
    }
}
