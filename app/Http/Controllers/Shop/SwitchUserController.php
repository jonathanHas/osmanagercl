<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Shop\IdentityVerifier;
use App\Services\Shop\ShopDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Swapping who is signed in on a trusted shared device, with a PIN (cycle 26).
 *
 * These routes sit outside the `auth` group on purpose: the Locked screen and
 * the people grid are what a signed-out tablet shows, and the whole point is
 * to get from nobody to somebody without a keyboard.
 *
 * What keeps them safe is the device cookie. Without a trusted device there is
 * no grid and no PIN page at all, so the PIN is only ever a second factor on
 * hardware a manager has vouched for with their own password.
 */
class SwitchUserController extends Controller
{
    public function __construct(
        private readonly ShopDeviceService $devices,
        private readonly IdentityVerifier $identity,
    ) {}

    /**
     * The people grid — "Who is working?".
     */
    public function index(Request $request): View
    {
        if (! $this->devices->current($request)) {
            return view('shop.switch-untrusted');
        }

        $currentId = $request->user()?->id;

        // The design cycles the avatar tone plain / accent / warm down the grid
        // so a wall of eight circles is not one flat colour.
        $tones = ['', 'shop-avatar--accent', 'shop-avatar--warm'];

        $people = User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('name', config('shop.pin_roles', ['employee'])))
            ->whereNotNull('pin_hash')
            ->orderBy('name')
            ->get()
            ->values()
            ->map(fn (User $user, int $i) => [
                'id' => $user->id,
                'first_name' => self::firstName($user),
                'initials' => self::initials($user),
                'tone' => $tones[$i % 3],
                'is_current' => $user->id === $currentId,
            ]);

        return view('shop.switch-user', [
            'people' => $people,
            'signedIn' => $currentId !== null,
        ]);
    }

    /**
     * The PIN pad for one person.
     */
    public function pin(Request $request, User $user): View
    {
        $this->abortUnlessPinnable($request, $user);

        return view('shop.pin', [
            'person' => $user,
            'firstName' => self::firstName($user),
            'initials' => self::initials($user),
        ]);
    }

    /**
     * Check the PIN and hand the session to that person.
     */
    public function authenticate(Request $request, User $user): RedirectResponse
    {
        $this->abortUnlessPinnable($request, $user);

        $request->validate(['pin' => ['required', 'digits_between:4,6']]);

        // The check, the per-user rate limit and the log line are shared with the
        // till count's "who is counting?" step (IdentityVerifier).
        $result = $this->identity->verifyPin($user, $request->input('pin'), $request, 'switch');

        if (! $result['ok']) {
            return back()->with('pinError', $result['error']);
        }

        $device = $this->devices->current($request);

        // A new session for a new person: nothing of the previous shift's
        // flashes, intended URLs or CSRF token carries over.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::login($user);
        $request->session()->put('auth_via', 'pin');

        if ($device) {
            $this->devices->touch($device, $user);
        }

        $this->devices->log($device, $user, 'switch', $request);

        return redirect()->route('shop.home');
    }

    /**
     * Lock the device: sign out and show the clock.
     *
     * GET rather than POST, like the existing GET logout, so a tab that has sat
     * open past its CSRF token still locks instead of throwing a 419 — and so
     * the idle timer can simply navigate.
     */
    public function lock(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            $this->devices->log($this->devices->current($request), $user, 'lock', $request);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('shop.locked');
    }

    /**
     * The Locked screen.
     */
    public function locked(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('shop.home');
        }

        return view('shop.locked');
    }

    /**
     * A PIN page exists only on a trusted device, and only for someone whose
     * role may use a PIN and who has one set. Anything else is a 404: the page
     * should not confirm that a given user id exists.
     */
    private function abortUnlessPinnable(Request $request, User $user): void
    {
        abort_unless($this->devices->current($request), 404);
        abort_unless($user->canUsePin() && $user->hasPin(), 404);
    }

    /**
     * @return array<int, string>
     */
    private static function nameWords(User $user): array
    {
        return preg_split('/\s+/', trim($user->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function firstName(User $user): string
    {
        return self::nameWords($user)[0] ?? $user->name;
    }

    /** Same two-letter rule as the topbar chip. */
    private static function initials(User $user): string
    {
        $words = array_slice(self::nameWords($user), 0, 2);

        return strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), $words)));
    }
}
