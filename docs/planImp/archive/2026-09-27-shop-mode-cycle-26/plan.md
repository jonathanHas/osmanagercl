# Plan: Shop mode cycle 26 — Switch user, PIN and Locked (screens 16–18)

**Status:** ACCEPTED
**Planner:** Fable 5.1
**Date:** 2026-09-27

## Goal

Let staff swap who is signed in on a shared Shop device with a PIN instead of a password, and lock the device after five idle minutes. Three design screens: **Switch user** (a grid of staff), **Enter PIN** (numpad), **Locked** (clock, tap to unlock). The device itself must first be trusted by a manager; a PIN session is confined to the Shop; managers and admins never use PINs and sign in with a password as now.

Owner decisions (2026-09-27): idle lock 5 minutes, try it and adjust; managers need a password; the Locked screen shows only the clock and "Tap to unlock" (no customer-requests shortcut). Employees will then use only their PIN on trusted devices; a personal phone still needs a password unless a manager trusts it.

Deferred to cycle 27: the device list/revoke page, self-service PIN on the profile page, a switch log viewer.

## Context (verified 2026-09-27)

- `users` columns: `id, name, username, email, email_verified_at, password, role_id, remember_token, timestamps`. `User` uses `HasPermissions` (`isAdmin/isManager/isEmployee/isBarista` by role name; roles present: admin, barista, employee, manager; 20 users). `$hidden` at `app/Models/User.php:35`, `casts()` at 45. `UserFactory` has `withRole()`.
- Admin user forms: `UserManagementController` (`store()` line 52 validates `password required|min:8|confirmed`; `update()` line 84 validates `password nullable|…`, hashes at 131, `$user->update($data)` at 134), views `resources/views/users/{index,create,edit,show}.blade.php` (office Tailwind views, `x-input-label`/`x-text-input`). Routes `users.*` at `routes/web.php:734–756` under `permission:users.*`.
- Login/landing: `AuthenticatedSessionController::store()` regenerates and redirects to `UiMode::landingUrl()`; `UiMode::current()` and `landingUrl()` already treat `session('auth_via') === 'pin'` as Shop (`app/Support/UiMode.php:40, 87`). `ShareUiMode` is appended to the `web` group in `bootstrap/app.php:21`; middleware aliases at line 16 (`permission`). `ConfirmablePasswordController::store()` (line 25) validates the password, sets `auth.password_confirmed_at`, `redirect()->intended(route('dashboard'))`. `routes/auth.php`: `password.confirm` GET/POST at line 50–53, `logout` POST **and GET** at 57–60. `PermissionMiddleware` aborts 403 for guests.
- No rate limiters are defined yet (`RateLimiter::for` absent from providers). No `auth.check`-style JSON beyond `routes/web.php:53` (`auth.check`, returns `{authenticated}`).
- Shop layout `resources/views/layouts/shop.blade.php`: `@vite` line 21, `#shop-root` line 25 (`is-touch` script 27), topbar at 30 (`ShopLayout` props `title, back, subtitle, guestSafe, bare, guestRefresh` — `guestRefresh` adds a `<meta http-equiv="refresh">` for signed-out viewers, line 9–10), stale-page detector script lines 51–69 (on `visibilitychange`, fetches `auth.check` and sends to `login?redirect=`). Topbar `resources/views/components/shop/topbar.blade.php`: `details.shop-usermenu` with menu head, Office (`@unless isBarista`, POST `ui-mode.set`), separator, Log out. `resources/js/shop.js` registers Alpine data inside `alpine:init`.
- Shop routes: `routes/web.php:77` `Route::prefix('shop')->name('shop.')` inside the `auth` group. Office endpoints the Shop views call (must stay reachable from a PIN session): `api.products.search`, `customer-requests.{index,show,store,update,edit,cancel,items.status,photo}`, `delivery-legacy.{items,scan-increment,update-quantity,complete,create-session}`, `fruit-veg.{harvest.rows,harvest.save-row,waste.entry,waste.rows,waste.search,product-image}`, `labels.{dismiss,dismiss-all,print-a4,queue,scan,shelf-labels}`, `stocking.{lookup,update-stock}`, `vouchers.{activate,deduct,lookup}`, `zebra-labels.print`, `ui-mode.set`, `login`, `logout`, `auth.check`.
- `config/shop.php` holds `tiles` only.
- Design: `docs/design/shop-mode/screen-16-switch-user.html` (`shop-page--narrow`, `h2.shop-title` "Who is working?", `div.shop-people` of `a.shop-person` = `span.shop-avatar.shop-avatar--lg[--accent|--warm]` + first name + optional `span.shop-pill.shop-pill--ok` "Signed in"); `screen-17-pin.html` (`shop-page--center`, `div.shop-pin` → `div.shop-pin__who` (`shop-avatar--xl`, `h2.shop-title` "Hi Tom", `p.shop-meta` "Enter your 4–6 digit PIN"), `div.shop-pin__dots` of `span.shop-pin__dot[.is-filled][.is-optional]`, `div.shop-pin__pad` of `button.shop-pin__key` 1–9, `a.shop-pin__key.shop-pin__key--fn` "Not you?", `0`, delete key with `backspace` icon; `.shop-pin.is-error` + `.shop-pin__msg` for the error); `screen-18-locked.html` (no topbar: `main.shop-lock` with `span.shop-topbar__mark` leaf, `div.shop-lock__time` "10:42", `p.shop-lock__date` "Tuesday 23 September", `p.shop-meta` "Locked after 5 minutes without activity", `div.shop-inline` with the primary "Tap to unlock"). All classes exist in `shop.css` (lines 298–312, 501–508).
- POS `deliveriesScanItems` has no "scanned by" column (`ID, delID, barcode, quantity, dateScan`), so there is nothing to backfill there; Laravel-side audit fields (waste, harvest, labels, requests) already record `auth()->id()` and become correct once the right user is signed in.
- Tests: `tests/Feature/Shop/{ShopHomeTest,UiModeTest,LandingRedirectTest,RoutePermissionsTest,ShopViewContractTest}.php`, `tests/Feature/Auth/{AuthenticationTest,PasswordConfirmationTest}.php`. `ShopHomeTest::userWith(role, permissions)` is the fixture pattern.
- Baseline: 15 failed / 672 passed (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2, TestScraper ×1). Cycles 24–25 may still be uncommitted; revert nothing.

## Constraints

- No commits, no deploys. Do not edit `docs/design/shop-mode/**`. No change to the design block of `resources/css/shop.css`; app rules (if any) under APP ADDITIONS.
- Shop view contract as before (components, `shop-*` classes, JS in `resources/js/shop`, URLs via `data-*`, no `@`-shorthands that are Blade directives).
- PINs are hashed with `Hash::make`; never logged, never in URLs. The PIN POST is rate limited. The device token is random, stored hashed, and never shown after creation.
- A PIN session must never reach a non-Shop page without a password. Enforce in middleware, not in links.
- Managers and admins cannot have a PIN and are not listed on Switch user.
- Existing password login, `?redirect=`, landing rules and the barista KDS landing keep working (`AuthenticationTest`, `LandingRedirectTest`, `UiModeTest` unchanged and green).

## Out of scope

- Device list / revoke page; self-service PIN on the profile page; a log viewer (cycle 27).
- Baristas (the KDS is outside the Shop; they keep password login).
- Any change to the office layout beyond the user forms' PIN field.

## Steps

### 1. Schema and models
Migrations (dated 2026_09_27):
- `add_pin_to_users`: `pin_hash` string nullable, `pin_length` unsignedTinyInteger nullable, `pin_set_at` timestamp nullable.
- `create_shop_devices_table`: `id`, `name`, `token_hash` (string 64, unique), `registered_by` (fk users, nullOnDelete), `last_user_id` (fk users, nullable, nullOnDelete), `last_used_at` nullable, `revoked_at` nullable, timestamps.
- `create_shop_switch_logs_table`: `id`, `shop_device_id` (fk, nullable, nullOnDelete), `user_id` (fk, nullable, nullOnDelete), `event` string 20 (`switch`, `switch_failed`, `lock`, `trust`), `ip` string 45 nullable, `created_at`.

`User`: add `pin_hash` to `$hidden`; keep it and `pin_length`/`pin_set_at` out of `$fillable`; `setPin(string $pin): void` (hash, length, set_at), `clearPin()`, `hasPin(): bool`, `checkPin(string $pin): bool` (`Hash::check`; false when no PIN). `canUsePin(): bool` = `isEmployee()` (one place to widen later). Models `ShopDevice` (`isActive()`, `static findByToken(?string)` hashing with `hash('sha256', …)`), `ShopSwitchLog`.
`UserFactory::withPin(string $pin = '2580')` state.
**Check:** `php artisan migrate` on the dev SQLite and on MySQL (production is MySQL — write the migrations for both); `User::factory()->withRole('employee')->withPin()->create()->checkPin('2580')` true.

### 2. Rules and config
- `app/Rules/NotTrivialPin.php`: rejects all-identical digits (`1111`), straight ascending/descending runs (`1234`, `654321`, `0123`), and `0000`–style repeats of a 2-digit pair (`1212`). Message: "That PIN is too easy to guess."
- `config/shop.php`: add `'idle_lock_minutes' => 5`, `'pin_roles' => ['employee']` (used by `canUsePin()`), `'device_cookie' => 'shop_device'`, `'pin_session_routes' => [...]` — the allow-list: `shop.*`, plus every route in Context's list, plus `password.confirm`, `verification.*`. Use `Str::is()` patterns.
- Rate limiter in `AppServiceProvider::boot()`: `RateLimiter::for('pin', fn (Request $r) => Limit::perMinutes(15, 5)->by('pin:'.($r->route('user')?->id ?? $r->ip())))` — five wrong PINs for one user lock that user's PIN for 15 minutes. (A correct PIN clears it: call `RateLimiter::clear()` on success.)
**Check:** unit test for the rule with a table of accepted/rejected PINs.

### 3. Device trust
- `app/Services/Shop/ShopDeviceService.php`: `current(Request): ?ShopDevice` (cookie → `findByToken` → active only, memoised per request), `trust(Request, User $by, string $name): array{device, cookie}` (creates the row, returns a `Cookie` for 365 days, httpOnly, `secure` when the request is HTTPS, SameSite Lax), `touch(ShopDevice, ?User)` (last_used_at/last_user_id).
- Routes (Shop group, auth): `GET /shop/devices/trust` (`shop.devices.trust`, view with a name field defaulting to "Counter tablet") and `POST` (`shop.devices.trust.store`), both `middleware('role:manager,admin')` — if no `role` middleware alias exists, gate in the controller with `abort_unless($user->isManager() || $user->isAdmin(), 403)`. On POST: create, log `trust`, flash "This device can now use PIN sign-in", redirect to `shop.home` with the cookie attached.
- The topbar menu: "Trust this device" for managers/admins when `current()` is null; "Switch user" (link to `shop.switch`) when the device is trusted; "Lock" (link to `shop.lock`) always for signed-in users; then Office, separator, Log out — the design's order. Share the device with views via `ShareUiMode` (`View::share('shopDevice', …)`) or a `ShopLayout` property; one mechanism, named in the report.
**Check:** feature tests: manager POST creates a device and the response has the cookie; employee GET/POST → 403; the menu shows Switch user only with a valid cookie.

### 4. Switch user and PIN (public routes, outside the `auth` group)
Routes in `routes/web.php` outside the auth group, `Route::prefix('shop')->name('shop.')`:
- `GET /shop/switch` → `Shop\SwitchUserController@index` (`shop.switch`).
- `GET /shop/switch/{user}` → `@pin` (`shop.switch.pin`), `POST /shop/switch/{user}` → `@authenticate` (`shop.switch.authenticate`, `middleware('throttle:pin')`).
- `GET /shop/lock` → `@lock` (`shop.lock`) — GET, like the existing GET logout, so a stale CSRF token cannot turn a lock into a 419.
- `GET /shop/locked` → `@locked` (`shop.locked`).

Controller behaviour:
- `index`: if the device is not trusted, render `shop/switch-untrusted.blade.php` (title "Switch user", `shop-empty` with `lock` icon: "This device isn't set up for PIN sign-in", text "A manager can trust it from the Shop menu after signing in with a password.", and a `shop-btn--primary` "Sign in with password" → `route('login', ['redirect' => route('shop.home', absolute: false)])`). Otherwise render `shop/switch-user.blade.php`: users where `canUsePin()` and `hasPin()`, ordered by name; the signed-in one carries the "Signed in" pill; avatar tone alternates `--accent`/`--warm`/none by position as the design does. Below the grid a `shop-meta` line "Managers sign in with a password" and a `shop-btn--secondary` "Sign in with password" (same login link). Layout: `guest-safe`, `:back` = `route('shop.home')` when signed in else null.
- `pin`: 404 unless the device is trusted and `$user->canUsePin() && $user->hasPin()`. Render `shop/pin.blade.php` with `x-data="shopPinPad()"` and `data-length="{{ $user->pin_length }}"`, `data-action="{{ route('shop.switch.authenticate', $user) }}`; dots: exactly `pin_length` dots (the design's dashed "optional" dots are for an unknown length; ours is known); the pad; "Not you?" links to `shop.switch`; a hidden `<form method="POST">` with `@csrf` and `<input type="hidden" name="pin" x-ref="pin">`. Error flash from the server renders `.shop-pin.is-error` and `p.shop-pin__msg` ("Wrong PIN, try again" / "Too many tries — wait 15 minutes" from the throttle's 429, caught and rendered as a flash rather than a 429 page: handle `ThrottleRequestsException` for this route in the controller by checking `RateLimiter::tooManyAttempts` first and flashing).
- `authenticate`: validate `pin` `required|digits_between:4,6`; 404/403 as in `pin`; wrong PIN → `RateLimiter::hit`, log `switch_failed`, redirect back with the error flash; correct PIN → `RateLimiter::clear`, `Auth::logout()` if anyone is signed in, `session()->invalidate()`, `session()->regenerateToken()`, `Auth::login($user)` (no remember), `session(['auth_via' => 'pin'])`, `touch()` the device, log `switch`, redirect to `route('shop.home')`.
- `lock`: `Auth::logout()`, invalidate, regenerate token, log `lock` (with the user id captured before logout), redirect to `shop.locked`. Works for guests too (just redirects).
- `locked`: render `shop/locked.blade.php` with `bare` and `guest-refresh="60"` (the clock is server-rendered `H:i` and `l j F`; the meta refresh keeps it right without JS): `main.shop-lock`, leaf mark, time, date, `p.shop-meta` "Locked after {{ config('shop.idle_lock_minutes') }} minutes without activity", and one `shop-btn--primary shop-btn--lg` "Tap to unlock" → `shop.switch`. If a user is somehow still signed in when this renders, redirect to `shop.home` instead.

JS `resources/js/shop/pin-pad.js` (`Alpine.data('shopPinPad')`): `digits: ''`, `length` from `$root.dataset.length`, `busy`, `press(d)` (ignore when busy or full), `backspace()`, `submit()` when `digits.length === length` → sets the hidden input and `this.$refs.form.submit()`; `@keydown.window` accepts `0–9`, Backspace, and Enter (submit if at least 4) for the till PC's keyboard; `filled(i)` for the dots.
**Check:** feature tests in `tests/Feature/Shop/ShopPinSwitchTest.php` (helper `trusted()` that creates a device and returns the cookie for `withCookie`): untrusted → the "not set up" page; trusted → grid lists only employees with a PIN, marks the signed-in one, excludes a manager who somehow has a hash; PIN page 404 for a manager; correct PIN → authenticated as that user, `auth_via` is `pin`, redirect `shop.home`, a `switch` log row; wrong PIN → error flash and a `switch_failed` row; six wrong → the "too many tries" message and a correct PIN is refused until cleared; lock → guest, redirect `shop.locked`, a `lock` row; `shop.locked` renders for a guest with `data-shell="shop"` and no topbar; contract test green.

### 5. Confine PIN sessions
- `app/Http/Middleware/ConfinePinSession.php`, appended to the `web` group after `ShareUiMode`: when `session('auth_via') === 'pin'` and the current route name does not match any pattern in `config('shop.pin_session_routes')`: JSON/`expectsJson()` requests get 403 `{message: 'Confirm your password to use the office'}`; others are redirected to `route('password.confirm')` with `redirect()->setIntendedUrl($request->fullUrl())`. Unnamed routes count as not allowed.
- `ConfirmablePasswordController::store()`: after the check passes, `session()->forget('auth_via')` and log nothing (the password confirm is Breeze's own audit). `redirect()->intended(...)` unchanged.
**Check:** tests: PIN session GET `/dashboard` → redirect to `/confirm-password`; GET `shop.home`, `delivery-legacy.items`, `api.products.search`, `customer-requests.index` → 200; POST `labels.scan`-style JSON off-list route (pick any unlisted JSON route, e.g. `products.image`) → 403 JSON; after posting the correct password to `/confirm-password`, `/dashboard` → 200 and `auth_via` is gone. `PasswordConfirmationTest` unchanged and green.

### 6. Idle lock
- `resources/js/shop/idle-lock.js`: `export default function startIdleLock(seconds, url)` — listens for `pointerdown`, `keydown`, `touchstart`, `wheel`, `scroll` on `window` (passive), resets a timer, and on expiry sets `window.location.href = url`. Started from `resources/js/shop.js` **outside** `alpine:init` when `#shop-root` carries `data-idle-lock-seconds` and `data-idle-lock-url`.
- Layout: set those two attributes on `#shop-root` only when a user is signed in **and** the device is trusted (a personal phone is not locked), and never on the locked/switch pages. Seconds = `config('shop.idle_lock_minutes') * 60`; url = `route('shop.lock')`.
- Stale-page detector (layout lines 51–69): on a trusted device redirect to `route('shop.switch')` instead of `login?redirect=`, so a tab that wakes after a lock lands on the grid.
**Check:** tests: the attributes are present for a signed-in user with the cookie, absent without it and absent on `shop.locked`. Browser: with `idle_lock_minutes` temporarily set to 0.2 in a `.env`-free way (e.g. `config(['shop.idle_lock_minutes' => 0.2])` in a tinker-free manner — simplest: pass seconds via `data-idle-lock-seconds` and verify by editing the attribute in DevTools to `5`), the page goes to Locked after the interval with no input, and a tap resets it.

### 7. Setting PINs (admin forms)
- `users/create` and `users/edit` (office views): a "Shop PIN" block shown only when the selected role is employee (Alpine `x-show` on the role select; server validates regardless): `pin` (`nullable|digits_between:4,6|confirmed`, `NotTrivialPin`), `pin_confirmation`, and on edit a "Clear PIN" checkbox. `UserManagementController` store/update: `setPin`/`clearPin`; **refuse** (validation error) a PIN for a user whose role cannot use one. `users/show` and `users/index`: a small "PIN set" badge with `pin_set_at` (no hash, no length shown beyond "set").
**Check:** feature tests: admin sets a PIN for an employee (hash stored, length 4, set_at now); trivial PIN rejected; PIN for a manager rejected; clear works; the edit form shows the block for an employee.

### 8. Docs, deploy notes, tidy
- There is no Shop mode feature doc yet (checked: `docs/features/` mentions Shop mode only inside customer-requests, fruit-veg-system and voucher-management). Create `docs/features/shop-mode.md` with a short overview (the `/shop` shell, the view contract, the design copy under `docs/design/shop-mode/`, the cycle archive) and a "Shared devices, PINs and locking" section; add it to `docs/FEATURES_INDEX.md` and the CLAUDE.md "Where to Find Information" list. The section covers: trusting a device, who can use a PIN, confinement, idle lock, where the switch log lives, how to revoke (cycle 27, for now: set `revoked_at` in the DB).
- Deploy notes in the report: `php artisan migrate`; set PINs on the user forms; on each shared device a manager signs in with a password and picks "Trust this device"; HTTPS is required for the `secure` cookie (production is HTTPS).
- `./vendor/bin/pint --dirty`; `npm run build`; `php artisan view:clear`; `php artisan route:list --name=shop.` in the report.

## Verification (report every item with what you saw)

1. `php artisan test` summary; the same 15 pre-existing failures.
2. Contract: design-block `cmp` prints nothing; `ShopViewContractTest` green.
3. Browser, on dev, as a manager: Trust this device → flash, cookie present (DevTools → Application → Cookies, `HttpOnly`, `SameSite=Lax`); menu now shows Switch user and Lock.
4. Set a PIN for an employee on the user edit form (dev user only; say which). Switch user → grid shows that employee; tap → PIN page; wrong PIN → error; correct PIN → Shop Home as that employee, chip shows their name; `session('auth_via')` is `pin` (tinker or a debug read of `/auth/check` is not enough — add the `auth_via` flag to the `auth.check` JSON only if you also add a test for it).
5. As the PIN user: open `/dashboard` → password confirm page; enter that user's password → dashboard loads; Shop pages still work. Back in the Shop: Lock → Locked screen with the clock; Tap to unlock → grid.
6. Idle: leave the page untouched for the interval → Locked. Tapping within the interval resets it (check via the DevTools attribute trick or a short config value).
7. Rate limit: six wrong PINs → the "too many tries" message; a correct PIN is refused until the window passes (or clear it in tinker afterwards so dev is left usable).
8. Password login for a manager still lands on `/dashboard`; barista still lands on the KDS; `?redirect=` still honoured.
9. Personal phone (or DevTools with the cookie deleted): Switch user shows the "not set up" page; no idle lock attributes; password login works as before.

## Risks

- **Two rate-limit paths.** The route throttle returns a bare 429 page; the controller must check `tooManyAttempts` first so the tablet sees the PIN page with a message. Keep the route throttle as the backstop.
- **Session fixation.** Invalidate and regenerate on every switch and lock; test that the session id changes.
- **Cookie on HTTP dev.** `secure` must follow `$request->isSecure()` or the dev tablet on plain HTTP never receives the cookie; production is HTTPS.
- **Allow-list drift.** A future Shop page that calls a new office endpoint will 403 for PIN users. Put the list in one config array with a comment pointing at this cycle, and have `RoutePermissionsTest` (or a new test) assert every route named in `resources/views/shop/**` is in it — a grep-based test like the contract test.
- **Idle lock on the till PC** mid-transaction: keyboard-wedge scans are `keydown` events and reset the timer; a 5-minute silence locks it, which is the owner's chosen trial value.
- **`GET /shop/lock`** can be triggered by any link; it only logs out, which is the same exposure the existing `GET logout` already has.
