<?php

namespace App\Http\Controllers;

use App\Models\ShopDevice;
use App\Models\ShopSwitchLog;
use App\Services\Shop\ShopDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The office view of trusted shop-floor devices (cycle 27).
 *
 * Trusting stays a Shop-menu action on the device itself — it has to be, since
 * the point is to mark *this* hardware. What the office needs is the other
 * half: seeing which devices are trusted, revoking one that has walked off,
 * and reading who took over which tablet and when.
 */
class ShopDeviceAdminController extends Controller
{
    /** Enough switch history to answer "who was on the counter at 3pm". */
    private const LOG_LIMIT = 100;

    public function __construct(private readonly ShopDeviceService $devices) {}

    public function index(): View
    {
        return view('shop-devices.index', [
            'devices' => ShopDevice::with(['registeredBy', 'lastUser'])
                ->orderByDesc('created_at')
                ->get(),
            'logs' => ShopSwitchLog::with(['user', 'device'])
                ->orderByDesc('id')
                ->limit(self::LOG_LIMIT)
                ->get(),
        ]);
    }

    public function revoke(Request $request, ShopDevice $device): RedirectResponse
    {
        if (! $device->isActive()) {
            return back()->with('error', "'{$device->name}' was already revoked.");
        }

        $device->forceFill(['revoked_at' => now()])->save();

        $this->devices->log($device, $request->user(), 'revoke', $request);

        return back()->with('success', "'{$device->name}' can no longer be used for PIN sign-in.");
    }
}
