<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Rules\NotTrivialPin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $user->load('role');

        return view('profile.edit', [
            'user' => $user,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Set, change or remove the user's own Shop PIN (cycle 27).
     *
     * The current password is required: a PIN is the weaker credential, so it
     * must be minted by the stronger one. A PIN session cannot reach /profile
     * without confirming the password first (ConfinePinSession), which is the
     * same rule arriving by a different road.
     */
    public function updatePin(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->canUsePin()) {
            throw ValidationException::withMessages([
                'pin' => 'Your role does not use a Shop PIN.',
            ])->errorBag('updatePin');
        }

        $validated = $request->validateWithBag('updatePin', [
            'current_password' => ['required', 'current_password'],
            'pin' => ['nullable', 'digits_between:4,6', 'confirmed', new NotTrivialPin],
            'clear_pin' => ['boolean'],
        ]);

        // Clearing wins over setting, as on the admin form: a half-filled form
        // with the box ticked must not leave the old PIN in place.
        if ($request->boolean('clear_pin')) {
            $user->clearPin();

            return back()->with('status', 'pin-cleared');
        }

        if (($validated['pin'] ?? null) === null || $validated['pin'] === '') {
            throw ValidationException::withMessages([
                'pin' => 'Enter a new PIN, or tick "Remove my PIN".',
            ])->errorBag('updatePin');
        }

        $user->setPin($validated['pin']);

        return back()->with('status', 'pin-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
