<?php

namespace App\Http\Middleware;

use App\Support\UiMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the resolved UI mode available to every Blade view as $uiMode, so
 * shared chrome can link to the right home screen without each controller
 * passing it down.
 */
class ShareUiMode
{
    public function handle(Request $request, Closure $next): Response
    {
        View::share('uiMode', app(UiMode::class));

        return $next($request);
    }
}
