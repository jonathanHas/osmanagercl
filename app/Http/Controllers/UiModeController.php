<?php

namespace App\Http\Controllers;

use App\Support\UiMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches this device between the shop-floor and office interfaces.
 *
 * The choice is a year-long cookie, so a tablet parked on the shop floor stays
 * in shop mode and a manager's desktop stays in the office, whoever signs in.
 */
class UiModeController extends Controller
{
    public function set(Request $request, string $mode): RedirectResponse
    {
        abort_unless(in_array($mode, [UiMode::SHOP, UiMode::OFFICE], true), 404);

        $target = $mode === UiMode::SHOP ? route('shop.home') : route('dashboard');

        return redirect($target)->withCookie(cookie(UiMode::COOKIE, $mode, 60 * 24 * 365));
    }
}
