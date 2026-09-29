<?php

namespace App\Http\Controllers;

use App\Services\VoucherActivityService;
use App\Services\VoucherSyncHeartbeat;
use App\Services\VoucherTillSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Live voucher activity for managers (/vouchers/activity). Read-only: the only
 * side effect is the throttled till check the lookup screens already run.
 */
class VoucherActivityController extends Controller
{
    public function __construct(
        protected VoucherActivityService $activity,
        protected VoucherTillSyncService $tillSync,
    ) {}

    /**
     * The page. Deliberately does not run the till check, so a slow till
     * database never delays the first render; the first poll does it.
     */
    public function index(): View
    {
        return view('vouchers.activity', [
            'initial' => $this->activity->payload(7, null),
            'pollSeconds' => (int) config('vouchers.activity.poll_seconds'),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'in:1,7,30'],
            'q' => ['nullable', 'string', 'max:64'],
        ]);

        $this->tillSync->syncIfDue(VoucherSyncHeartbeat::SOURCE_ACTIVITY);

        return response()->json($this->activity->payload((int) ($data['days'] ?? 7), $data['q'] ?? null));
    }
}
