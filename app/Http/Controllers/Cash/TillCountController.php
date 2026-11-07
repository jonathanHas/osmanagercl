<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTillCountRequest;
use App\Models\Till;
use App\Models\User;
use App\Services\Cash\CountIdentity;
use App\Services\Cash\TillCountService;
use App\Services\Shop\IdentityVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The till count's JSON endpoints, shared by the Shop screen and the office page.
 *
 *   POST till-count/identify  who is counting: user_id + pin | password
 *   POST till-count/forget    "not you?"
 *   POST till-count/store     the count itself; needs a live identity
 */
class TillCountController extends Controller
{
    public function __construct(
        private readonly TillCountService $service,
        private readonly IdentityVerifier $verifier,
        private readonly CountIdentity $identity,
    ) {}

    public function identify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:App\Models\User,id'],
            'pin' => ['nullable', 'string', 'digits_between:4,6'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::findOrFail($data['user_id']);

        if (! $user->hasPermission('cash_reconciliation.create')) {
            return response()->json(['ok' => false, 'error' => 'That person may not count the till.'], 422);
        }

        if (! empty($data['pin'])) {
            $result = $this->verifier->verifyPin($user, $data['pin'], $request);
            $via = 'pin';
        } elseif (! empty($data['password'])) {
            $result = $this->verifier->verifyPassword($user, $data['password'], $request);
            $via = 'password';
        } else {
            return response()->json(['ok' => false, 'error' => 'Enter your PIN or password.'], 422);
        }

        if (! $result['ok']) {
            return response()->json($result, $result['blocked'] ?? false ? 429 : 422);
        }

        $identity = $this->identity->put($user, $via);

        return response()->json([
            'ok' => true,
            'user' => ['id' => $user->id, 'name' => $user->name, 'first_name' => IdentityVerifier::firstName($user)],
            'expires_at' => $identity['expires_at'],
        ]);
    }

    public function forget(): JsonResponse
    {
        $this->identity->forget();

        return response()->json(['ok' => true]);
    }

    public function store(StoreTillCountRequest $request): JsonResponse
    {
        $counter = $this->identity->current();
        if (! $counter) {
            return response()->json(['ok' => false, 'error' => 'Identify yourself first.', 'identify' => true], 403);
        }

        $data = $request->validated();

        $till = Till::findOrFail($data['till_id']);
        $session = $this->service->session($data['session_id']);

        if (! $session || $session->HOST !== $till->pos_host) {
            return response()->json(['ok' => false, 'error' => 'That till close no longer exists.'], 422);
        }

        if ($session->DATEEND === null) {
            return response()->json(['ok' => false, 'error' => 'The till is still open. Close it first.'], 422);
        }

        $reconciliation = $this->service->save($session, $till, $counter, $request->user(), $data);

        $required = (float) config('till_count.note_required_at');
        if (abs((float) $reconciliation->variance) >= $required && trim((string) ($data['note'] ?? '')) === '') {
            // Saved all the same (the cash is counted either way), but say so.
            $noteMissing = true;
        }

        $this->identity->forget();

        return response()->json([
            'ok' => true,
            'reconciliation_id' => $reconciliation->id,
            'variance' => (float) $reconciliation->variance,
            'bag' => $reconciliation->calculateAvailableToLodge(),
            'counted_by' => $counter->name,
            'note_missing' => $noteMissing ?? false,
            'state' => $this->service->stateFor($till, $session, $reconciliation),
        ]);
    }
}
