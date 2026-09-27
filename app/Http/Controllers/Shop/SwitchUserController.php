<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Shop\ShopDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
    public function __construct(private readonly ShopDeviceService $devices) {}

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

        $key = $this->limiterKey($user);
        $attempts = (int) config('shop.pin_attempts', 5);
        $minutes = (int) config('shop.pin_lockout_minutes', 15);

        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            $this->devices->log($this->devices->current($request), $user, 'switch_blocked', $request);

            return back()->with('pinError', "Too many tries — wait {$minutes} minutes.");
        }

        $request->validate(['pin' => ['required', 'digits_between:4,6']]);

        $device = $this->devices->current($request);

        if (! $user->checkPin($request->input('pin'))) {
            RateLimiter::hit($key, $minutes * 60);
            $this->devices->log($device, $user, 'switch_failed', $request);

            return back()->with('pinError', 'Wrong PIN, try again.');
        }

        RateLimiter::clear($key);

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
     * Keyed per user, not per device: one person fumbling must not lock the
     * tablet for everybody else on the shift.
     *
     * The limiting is done here rather than with `throttle:pin` on the route
     * because the named-limiter middleware stores its counter under
     * md5($limiterName.$key), which no public API can clear — so a correct PIN
     * could never reset it, and a legitimate run of sign-ins would lock the
     * person out. The limiter registered in AppServiceProvider stays as the
     * single source of the numbers.
     */
    private function limiterKey(User $user): string
    {
        return 'pin:'.$user->id;
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
