<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Shop\ShopDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trusting a shared device for PIN sign-in (cycle 26).
 *
 * Only a manager or admin can do it, and only by signing in with a password
 * first — that password is the whole security of the arrangement. The route is
 * behind `role:manager,admin` as well as `auth`.
 *
 * The device list and revoke page are cycle 27; until then a device is revoked
 * by setting `revoked_at` on the row.
 */
class ShopDeviceController extends Controller
{
    public function __construct(private readonly ShopDeviceService $devices) {}

    public function create(Request $request): View
    {
        return view('shop.device-trust', [
            'device' => $this->devices->current($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        ['cookie' => $cookie] = $this->devices->trust($request, $request->user(), $data['name']);

        return redirect()->route('shop.home')
            ->with('success', 'This device can now use PIN sign-in.')
            ->withCookie($cookie);
    }
}
