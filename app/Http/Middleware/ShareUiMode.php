<?php

namespace App\Http\Middleware;

use App\Services\Shop\ShopDeviceService;
use App\Support\UiMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the resolved UI mode available to every Blade view as $uiMode, so
 * shared chrome can link to the right home screen without each controller
 * passing it down.
 *
 * It also shares the trusted Shop device as $shopDevice (cycle 26). The topbar
 * menu and the Shop layout both need it, and this is the one place it is
 * resolved — ShopDeviceService memoises, and skips the query entirely when the
 * device cookie is absent.
 */
class ShareUiMode
{
    public function handle(Request $request, Closure $next): Response
    {
        View::share('uiMode', app(UiMode::class));
        View::share('shopDevice', app(ShopDeviceService::class)->current($request));

        return $next($request);
    }
}
