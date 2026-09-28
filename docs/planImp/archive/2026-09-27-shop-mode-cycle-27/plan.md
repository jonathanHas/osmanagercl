# Plan: Shop mode cycle 27 — PIN follow-ups: allow-list fix, devices page, own PIN, switch log

**Status:** ACCEPTED
**Planner:** Fable 5.1
**Date:** 2026-09-27

## Goal

Finish what cycle 26 deferred and fix its one gap:

1. PIN sessions lose POS-photo thumbnails wherever the product search API supplies the picture (Find product, the request form typeahead), because `products.image` is not on the allow-list. Allow it.
2. Managers need a page to see trusted devices and revoke one, and to read the switch log. Today revoking means editing a database row.
3. Employees should be able to set or change their own PIN on the profile page, with their password.
4. The `users.show` route points at a controller method that does not exist and 500s.

Office pages for items 2 and 4 use the admin layout; item 3 is a profile partial; item 1 is config plus one test.

## Context (verified 2026-09-27)

- `config/shop.php` `pin_session_routes` (cycle 26) lists Shop routes and the office endpoints Shop screens call; `App\Http\Middleware\ConfinePinSession` matches by route name with `Str::is`; JSON requests off-list get 403. `tests/Feature/Shop/ConfinePinSessionTest.php` line 72 uses `route('products.image', 1)` as the off-list JSON example. `ProductSearchService::imageUrl()` returns `route('products.image', …)` for any product with a POS blob; `products.image` is under `permission:products.view` (`routes/web.php:148`), which is its own gate.
- Devices: `App\Models\ShopDevice` (`name, token_hash (hidden), registered_by, last_user_id, last_used_at, revoked_at`, `registeredBy()`, `lastUser()`, `isActive()`), `App\Models\ShopSwitchLog` (`shop_device_id, user_id, event (trust|switch|switch_failed|switch_blocked|lock), ip, created_at`), `App\Services\Shop\ShopDeviceService` (`current`, `trust`, `touch`, `log`). `database/factories/ShopDeviceFactory.php` exists. The `role` middleware alias exists (`role:manager,admin`).
- Profile: `routes/web.php:90–92` (`profile.edit/update/destroy`, `ProfileController`), `resources/views/profile/edit.blade.php` includes partials `role-information`, `update-profile-information-form`, `update-password-form`, `delete-user-form`. The password form posts `PUT password.update` to `Auth\PasswordController::update()` using `validateWithBag('updatePassword', ['current_password' => ['required','current_password'], …])` and `back()->with('status', 'password-updated')`; the partial renders errors from `$errors->updatePassword` and uses `x-form-group`. `User::setPin/clearPin/hasPin/canUsePin` and `App\Rules\NotTrivialPin` exist (cycle 26). The admin forms' PIN block in `resources/views/users/{create,edit}.blade.php` is the wording to match.
- `routes/web.php:769` `GET /users/{user}` → `UserManagementController@show` (`users.show`, `permission:users.view`); no `show()` method and no `users/show.blade.php`. Office views use `<x-admin-layout>` with a `header` slot (`resources/views/users/index.blade.php`); the admin sidebar links to `users.index` at `resources/views/layouts/admin.blade.php:742` inside the admin/manager section.
- Docs: `docs/features/shop-mode.md` (cycle 26) has a "Shared devices, PINs and locking" section that says revoking means setting `revoked_at`.
- Dev data: katelyn (id 3) has PIN `2580`; device row 1 is revoked.
- Baseline: 15 failed / 741 passed (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2, TestScraper ×1). Cycles 24–26 may be uncommitted; revert nothing.

## Constraints

- No commits, no deploys. Do not edit `docs/design/shop-mode/**`. No Shop view changes are expected (the contract test must stay green regardless).
- PINs: hashed, never logged, never in URLs; the profile form requires the current password; `NotTrivialPin` applies everywhere a PIN is set.
- Revoking a device must take effect on the device's next request (the cookie lookup already filters `revoked_at`); no session is invalidated by a revoke — the current user stays signed in until lock or idle.
- Managers/admins only for the devices page (`role:manager,admin`), matching the trust action.

## Out of scope

- Re-trusting from the office (trust stays a Shop-menu action on the device itself).
- Log retention/pruning (note the table grows by a few rows a day; a later cycle can add a monthly prune).
- Any Shop screen change.

## Steps

### 1. Allow-list `products.image`
`config/shop.php`: add `'products.image'` to `pin_session_routes` with a one-line comment (the search API's POS-photo URL; the route keeps its own `products.view` gate). `ConfinePinSessionTest`: the off-list JSON example becomes `route('settings.index')` (JSON GET → 403 with the message); add `test_a_pin_session_may_load_a_product_picture` asserting `products.image` is allowed (a 200 needs a product blob — asserting the response is **not** the confinement redirect/403 is enough, as cycle 26 did for `/dashboard`).
**Check:** test green; in the browser as the PIN user (katelyn), Find product shows POS-photo thumbnails for a product that has one.

### 2. Devices page (office)
- Routes in `routes/web.php`, inside the auth group near `users.*`, `Route::middleware('role:manager,admin')`: `GET /shop-devices` → `ShopDeviceAdminController@index` (`shop-devices.index`); `POST /shop-devices/{device}/revoke` → `@revoke` (`shop-devices.revoke`).
- `app/Http/Controllers/ShopDeviceAdminController.php` (new, office namespace): `index()` loads devices with `registeredBy`, `lastUser`, newest first, and the last 100 `ShopSwitchLog` rows with `user` and `device` (add those two `belongsTo` relations to `ShopSwitchLog` if missing). `revoke()`: `abort_if` already revoked → back with a notice; else set `revoked_at`, `log('revoke', …)` via the service (add `revoke` to the documented event list; the column is `string(20)`), back with success.
- View `resources/views/shop-devices/index.blade.php` (`x-admin-layout`, header "Shop devices"): a short intro ("A device is trusted from the Shop menu on the device itself, by a manager. Revoking it here stops PIN sign-in on that device from its next request."), a table of devices (name, trusted by, last user, last used, status Active/Revoked with date, Revoke button as a small POST form — no `confirm()` dialog; revoking is cheap to undo by re-trusting), and below it "Recent switch activity": time, event (human labels: Trusted, Switched, Wrong PIN, Locked out, Locked, Revoked), person, device, IP. Empty states for both.
- Sidebar: a "Shop devices" link beside Users in `layouts/admin.blade.php` (same `@if` that guards Users, or `role` check manager/admin — say which in the report).
**Check:** feature tests (`tests/Feature/ShopDeviceAdminTest.php`): manager sees the page with a device and a log row; employee → 403; revoke sets `revoked_at`, writes a `revoke` log row, and a subsequent request with that device's cookie is untrusted (`ShopDeviceService::current()` null → the Switch user page shows "not set up"); revoking twice is a no-op with a notice.

### 3. Own PIN on the profile page
- Route `PUT /profile/pin` (`profile.pin.update`) in the profile group → `ProfileController@updatePin` (or `Auth\PinController@update` — one class, say which). Validation with `validateWithBag('updatePin', …)`: `current_password` `required|current_password`; `pin` `nullable|digits_between:4,6|confirmed` + `NotTrivialPin`; `clear_pin` boolean. Refuse (validation error on `pin`) when `! $user->canUsePin()`. Clear wins over set, as on the admin form. `back()->with('status', 'pin-updated')` / `'pin-cleared'`.
- Partial `resources/views/profile/partials/update-pin-form.blade.php`, included from `profile/edit.blade.php` **only when** `auth()->user()->canUsePin()`, after the password form: header "Shop PIN", text "4–6 digits for signing in on the shop tablet. Set on {{ pin_set_at }}" or "No PIN set", fields current password, new PIN, confirm PIN, a "Remove my PIN" checkbox when one is set, errors from `$errors->updatePin`, the status flash like the password form.
**Check:** feature tests (`tests/Feature/ProfilePinTest.php`): employee sets a PIN with the right password (hash stored, `checkPin` true, `pin_length`); wrong current password → error in the `updatePin` bag and no change; trivial PIN rejected; manager → the partial is absent and the route rejects; clear works; the profile page for an employee shows the section. Browser: as katelyn (password login on dev), change the PIN and sign in with the new one on the Switch user grid; then set it back to `2580` so dev stays as documented.

### 4. `users.show`
`grep -rn "users.show" resources app` first. If nothing references it, delete the route (line 769) and add a regression test that `GET /users/{id}` is 404 for an admin. If something does, add `show()` as a redirect to `users.edit` and test that. Say which in the report.

### 5. Docs and tidy
- `docs/features/shop-mode.md`: revoking now happens on the Shop devices page; own PIN on the profile page; the `revoke` event; the `products.image` allow-list note.
- `./vendor/bin/pint --dirty`; `npm run build` only if any JS changed (none expected); `php artisan view:clear`.

## Verification (report every item with what you saw)

1. `php artisan test` summary; the same 15 pre-existing failures.
2. `ShopViewContractTest` green; design-block `cmp` prints nothing (no CSS change).
3. Browser: Find product as the PIN user shows POS-photo thumbnails (step 1).
4. Browser, office as a manager (or by test if you will not enter a password on this host, as in cycle 26 — say so): Shop devices page lists the revoked dev device and the switch log from cycle 26's run; trust a new device from the Shop menu (or seed one), revoke it on the page, then load the Switch user page with that cookie → "not set up".
5. Browser: profile page as katelyn shows the Shop PIN section; as a manager it does not.
6. `GET /users/3` no longer 500s (404 or redirect, per step 4).

## Risks

- **Revoke while someone is signed in** on that device: they keep their session until lock/idle; the page's intro says so. If the owner wants an immediate sign-out, that is a session store lookup and a later cycle.
- **`current_password` rule** validates against the `web` guard's current user; a PIN session cannot reach `/profile` without confirming the password first (off-list), which is the intended path.
- **Sidebar placement**: keep to one link; do not restructure the admin sidebar.
