# Shop mode cycle 26 — Switch user, PIN and Locked (screens 16–18) — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-27

## Baseline
HEAD: ea9db126

Pre-existing dirty files (cycles 24–25, not mine — nothing reverted):
```
 M app/Console/Commands/PruneFruitVegThumbnails.php
 M app/Http/Controllers/CustomerRequestController.php
 M app/Http/Controllers/DeliveryLegacyController.php
 M app/Services/CustomerRequestService.php
 M app/Services/ProductThumbnailService.php
R  docs/planImp/implemented.md -> docs/planImp/archive/2026-09-26-shop-mode-cycle-23/implemented.md
RM docs/planImp/plan.md -> docs/planImp/archive/2026-09-26-shop-mode-cycle-23/plan.md
 M resources/css/shop.css
 M resources/js/shop/delivery-scan.js
 M resources/js/shop/delivery-summary.js
 M resources/js/shop/product-images.js
 M resources/views/shop/delivery-scan.blade.php
 M resources/views/shop/delivery-summary.blade.php
 M routes/web.php
 M tests/Concerns/CreatesLegacyDeliveryPosTables.php
 M tests/Feature/CustomerRequestDeliveryFlagTest.php
 M tests/Feature/FruitVegProductImageTest.php
 M tests/Feature/Shop/ShopDeliveryTest.php
?? app/Http/Controllers/Concerns/
?? app/Http/Controllers/Shop/ProductPhotoController.php
?? app/Services/Shop/          (ProductImageUrls.php only; I added ShopDeviceService.php)
?? docs/planImp/archive/2026-09-27-shop-mode-cycle-24/
?? docs/planImp/archive/2026-09-27-shop-mode-cycle-25/
```

Test baseline, confirmed before starting: **15 failed / 672 passed**.

## Steps

### 1. Schema and models — done
Changed: `database/migrations/2026_09_27_100000_add_pin_to_users.php` (new),
`2026_09_27_100001_create_shop_devices_table.php` (new),
`2026_09_27_100002_create_shop_switch_logs_table.php` (new),
`app/Models/User.php`, `app/Models/ShopDevice.php` (new),
`app/Models/ShopSwitchLog.php` (new), `database/factories/UserFactory.php`,
`database/factories/ShopDeviceFactory.php` (new).

`User`: `pin_hash` added to `$hidden`, `pin_set_at` cast to datetime, none of the
three columns in `$fillable` (`setPin`/`clearPin` use `forceFill`). Added
`canUsePin()`, `hasPin()`, `setPin()`, `clearPin()`, `checkPin()`.

Check — migrate on the dev **MySQL** database:
```
   INFO  Running migrations.
  2026_09_27_100000_add_pin_to_users ........................... 218.16ms DONE
  2026_09_27_100001_create_shop_devices_table .................. 275.71ms DONE
  2026_09_27_100002_create_shop_switch_logs_table .............. 201.55ms DONE
```
SQLite is covered by the suite (`phpunit.xml` uses `sqlite/:memory:` with
`RefreshDatabase`, so every migration runs on SQLite on each test class).

Check — model behaviour:
```
checkPin(2580): true
checkPin(1234): false
hasPin: true
canUsePin(employee): true
pin_length: 4
canUsePin(manager): false
hidden: password,pin_hash,remember_token
```

### 2. Rules and config — done
Changed: `app/Rules/NotTrivialPin.php` (new), `config/shop.php`,
`tests/Unit/Rules/NotTrivialPinTest.php` (new).

`NotTrivialPin` rejects all-identical digits, straight runs up or down
(including wraparound: `9012`, `1098`) and a two-digit pair repeated to fill the
PIN. Config gained `idle_lock_minutes`, `pin_roles`, `pin_attempts`,
`pin_lockout_minutes`, `device_cookie`, `pin_session_routes`.

Check:
```
Tests:    19 passed (19 assertions)
```
(12 rejected PINs, 6 accepted, plus non-numeric input left to the other rules.)

### 3. Device trust — done
Changed: `app/Services/Shop/ShopDeviceService.php` (new),
`app/Http/Controllers/Shop/ShopDeviceController.php` (new),
`resources/views/shop/device-trust.blade.php` (new), `routes/web.php`,
`app/Http/Middleware/ShareUiMode.php`, `app/Providers/AppServiceProvider.php`,
`resources/views/components/shop/topbar.blade.php`.

The `role` middleware alias **does** exist (`bootstrap/app.php:17`), so the
routes use `middleware('role:manager,admin')` as the plan's first choice.

**Sharing mechanism (the plan asked me to name it):** `ShareUiMode` does
`View::share('shopDevice', app(ShopDeviceService::class)->current($request))`.
One place, available to the layout and the topbar. The service is registered
`scoped` beside `UiMode` and skips the query entirely when the cookie is absent.

Menu order, per the design: Switch user (trusted) *or* Trust this device
(manager/admin, untrusted) → Lock → Office → separator → Log out.

Check (`ShopPinSwitchTest`): manager POST creates the device and the response
carries the cookie; employee GET and POST both 403 and no row is created; the
menu shows `shop.switch` only with a valid cookie, and `shop.devices.trust` to a
manager without one. All green.

### 4. Switch user and PIN — done
Changed: `app/Http/Controllers/Shop/SwitchUserController.php` (new),
`resources/views/shop/switch-user.blade.php` (new),
`switch-untrusted.blade.php` (new), `pin.blade.php` (new),
`locked.blade.php` (new), `resources/js/shop/pin-pad.js` (new),
`resources/js/shop.js`, `routes/web.php`.

Routes are outside the `auth` group as specified. `lock` is a GET. The PIN page
draws exactly `pin_length` dots (the design's dashed "optional" dots are for an
unknown length; ours is known). The pad submits itself on the last digit; the
keyboard (`x-on:keydown.window` → `onKey`) accepts 0–9, Backspace and Enter for
the till PC and its wedge scanner.

Check (`tests/Feature/Shop/ShopPinSwitchTest.php`, 27 tests):
```
Tests:    27 passed (96 assertions)
```
Covering: untrusted → the "not set up" page; the grid lists only shop-floor
staff with a PIN and excludes a manager who has a hash; the signed-in one is
marked and a guest sees no mark; PIN page 404 for a manager, for an employee
with no PIN and without a device; correct PIN → authenticated, `auth_via` is
`pin`, redirect `shop.home`, device touched, a `switch` row; wrong PIN → error
flash and a `switch_failed` row; six wrong → "too many tries" and the correct
PIN refused until cleared; a correct PIN clears the counter; lock → guest,
`shop.locked`, a `lock` row; guest lock is a no-op redirect; `shop.locked`
renders for a guest with `data-shell="shop"` and no top bar and sends a
signed-in user home; a revoked device is not trusted.

### 5. Confine PIN sessions — done
Changed: `app/Http/Middleware/ConfinePinSession.php` (new), `bootstrap/app.php`,
`app/Http/Controllers/Auth/ConfirmablePasswordController.php`, `routes/auth.php`.

Appended to the `web` group after `ShareUiMode`. Unnamed routes count as not
allowed. `ConfirmablePasswordController::store()` now does
`session()->forget('auth_via')`; `redirect()->intended(...)` unchanged.

Check (`tests/Feature/Shop/ConfinePinSessionTest.php`, 7 tests):
```
Tests:    7 passed (77 assertions)
```
The fixture signs in through the real PIN endpoint rather than poking
`auth_via` into the session. Covering: `/dashboard` → `/confirm-password`;
`shop.home`, `shop.stock-scan`, `customer-requests.index`, `auth.check` all
200; `products.image` as JSON → 403 with the message; an unnamed route →
`/confirm-password`; posting the right password ends the confinement and clears
`auth_via`; an ordinary password session is never confined; and the
allow-list drift check (below).

`PasswordConfirmationTest`, `AuthenticationTest`, `LandingRedirectTest`,
`UiModeTest`, `RoutePermissionsTest`, `ShopViewContractTest` all unchanged:
```
Tests:    101 passed (340 assertions)
```

**Allow-list drift** (the plan's Risks asked for this): 
`ConfinePinSessionTest::test_every_route_a_shop_view_names_is_on_the_allow_list`
greps `resources/views/shop/**`, `resources/views/components/shop/**`,
`resources/views/layouts/shop.blade.php` and `resources/js/shop/**` for
`route('…')` and asserts each name matches `config('shop.pin_session_routes')`,
naming the offending file in the failure message.

### 6. Idle lock — done
Changed: `resources/js/shop/idle-lock.js` (new), `resources/js/shop.js`,
`resources/views/layouts/shop.blade.php`.

Started outside `alpine:init` (plain DOM, so it survives Alpine failing to
boot). The layout sets `data-idle-lock-seconds` / `data-idle-lock-url` on
`#shop-root` only when signed in **and** the device is trusted, and never on
`shop.switch`, `shop.switch.*`, `shop.lock` or `shop.locked`. The stale-page
detector now sends a trusted device to `shop.switch` instead of
`login?redirect=`.

Check (tests): attributes present for a signed-in user on a trusted device
(`data-idle-lock-seconds="300"`), absent without the cookie, absent for a guest
and absent on the switch/locked screens. Browser evidence under Verification 6.

### 7. Setting PINs (admin forms) — done
Changed: `app/Http/Controllers/UserManagementController.php`,
`resources/views/users/create.blade.php`, `edit.blade.php`, `index.blade.php`,
`tests/Feature/UserPinManagementTest.php` (new).

The Shop PIN block is wrapped in Alpine `x-data` on the form with `x-model` on
the role select and `x-show="pinRoles.includes(Number(roleId))"`; the role ids
come from the controller (`pinRoleIds()`) so no role name is hard-coded in
Blade. The server validates regardless and `guardPinRole()` throws a validation
error for a PIN on a non-shop-floor role. On edit, "Clear PIN" wins over
setting, and moving someone off the shop floor clears their PIN.

Check:
```
Tests:    11 passed (39 assertions)
```
Covering: admin sets a PIN for an employee (hash stored and not the plaintext,
length 4, `set_at` now); PIN set at creation with length 6; trivial PIN
rejected; mismatched confirmation rejected; PIN for a manager refused; clear
works; an unrelated edit keeps the PIN; a role change off the shop floor takes
it; the edit form shows the block, the "Clear this PIN" box and the role ids;
the index badges a user with a PIN; `pin_hash` is never serialised.

### 8. Docs, deploy notes, tidy — done
Changed: `docs/features/shop-mode.md` (new, 178 lines), `docs/FEATURES_INDEX.md`,
`CLAUDE.md`.

`docs/features/shop-mode.md` covers the `/shop` shell, the view contract, the
two Alpine traps, `UiMode`, then "Shared devices, PINs and locking": trusting a
device, who can have a PIN, signing in, the rate limit, confinement and the
drift test, the idle lock, the audit trail, revoking (set `revoked_at`) and the
deploy steps. Added to `docs/FEATURES_INDEX.md` under User Management and to
both lists in `CLAUDE.md`.

Tidy:
```
./vendor/bin/pint --dirty   → PASS, 37 files
npm run build               → ✓ built in 6.65s  (shop-Ccvt6IXa.js 36.08 kB)
php artisan view:clear      → INFO  Compiled views cleared successfully.
```

`php artisan route:list --name=shop.` — 18 routes, the six new ones being:
```
GET|HEAD   shop/devices/trust        shop.devices.trust        ShopDeviceController@create
POST       shop/devices/trust        shop.devices.trust.store  ShopDeviceController@store
GET|HEAD   shop/lock                 shop.lock                 SwitchUserController@lock
GET|HEAD   shop/locked               shop.locked               SwitchUserController@locked
GET|HEAD   shop/switch               shop.switch               SwitchUserController@index
GET|HEAD   shop/switch/{user}        shop.switch.pin           SwitchUserController@pin
POST       shop/switch/{user}        shop.switch.authenticate  SwitchUserController@authenticate
```

**Deploy notes**
1. `php artisan migrate` (three migrations; additive only).
2. `npm run build` — `resources/js/shop.js` changed.
3. Set PINs for shop-floor staff on the staff form.
4. On each shared device, a manager signs in with a password and picks
   **Trust this device** from the Shop menu.
5. HTTPS is required for the device cookie to carry `secure`; the flag follows
   `$request->isSecure()`, so a plain-HTTP dev tablet still works.

## Deviations

1. **`POST /confirm-password` was unnamed, which would have deadlocked the
   confinement.** The plan says "unnamed routes count as not allowed", and
   `routes/auth.php:53` registered that POST with no name — so a PIN session
   redirected to the password form could never submit it: the POST would be
   blocked and bounce back to the form for ever. I named it
   `password.confirm.store` and allow-listed it. Naming a route that nothing
   could previously reference by name changes no behaviour.

2. **Rate limiting moved from `throttle:pin` route middleware into the
   controller.** The plan wanted both, with the controller checking
   `tooManyAttempts` first. Two problems made that impossible as written:
   - the route middleware runs *before* the controller, so the controller could
     never check first; and, decisively,
   - `ThrottleRequests` stores a named limiter's counter under
     `md5($limiterName.$key)`, so `RateLimiter::clear('pin:'.$id)` from the
     controller clears a different key. A correct PIN could never reset the
     counter, and after five sign-ins in fifteen minutes a legitimate user would
     be locked out. (My first pass had exactly this bug and a test caught it:
     the "clear on success" test was passing vacuously against the unused key.)

   The controller now checks `tooManyAttempts`, hits on a wrong PIN and clears
   on a correct one, all on `pin:{id}`. This gives the plan's *stated* semantics
   more exactly than the middleware would — five **wrong** PINs, not five
   requests. `RateLimiter::for('pin', …)` in `AppServiceProvider` and the
   `ThrottleRequestsException` renderable I had added became dead code and were
   removed; `config('shop.pin_attempts')` and `pin_lockout_minutes` are now the
   single source of the numbers. Verified end to end in the browser
   (Verification 7).

3. **A fifth log event, `switch_blocked`.** The plan lists four. A lockout that
   leaves no trace is an audit gap precisely when someone is guessing, so a
   blocked attempt is logged. Additive; the column is `string(20)`.

4. **`customer-requests.*` as one pattern** rather than the plan's enumeration.
   The only route the wildcard adds over the plan's list is
   `customer-requests.create` (the GET form whose POST, `store`, the plan
   already allows), so the enumeration looked like an oversight rather than an
   intent.

5. **There is no `resources/views/users/show.blade.php`.** The plan asked for a
   "PIN set" badge on `users/show` and `users/index`. Only `index` exists, so
   the badge went there; the edit form's PIN block also states whether a PIN is
   set and when. See Notes for Planner — the `users.show` *route* exists but its
   controller method does not.

6. **`ShopDeviceService` memoises against the `Request` object**, not a simple
   "have I looked yet" flag. The container is scoped per request in production
   but is reused across requests inside a single test method, and the plain flag
   made the second request in a test see the first request's answer. Caught by a
   failing test, not by reasoning.

7. **Two small test-shape changes** where the plan's wording did not fit: the
   suite's users route is `PATCH`, not `PUT`; and assertions that would have
   rendered `/dashboard` were reduced to "the confinement redirect is gone",
   because the dashboard needs the POS `PRODUCTS` table, which these test
   classes do not create.

Nothing under **Out of scope** was touched: no device list/revoke page, no
profile-page PIN, no log viewer, no barista change, no office-layout change
beyond the user forms.

## Verification

1. **`php artisan test`** → `Tests: 15 failed, 741 passed (3141 assertions)`.
   The same 15 pre-existing failures as the baseline, unchanged in name and
   count (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2,
   TestScraper ×1). Passing went 672 → 741; the 69 new tests are all mine.

2. **Contract.**
   `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css`
   → prints nothing (`DESIGN-BLOCK-IDENTICAL`). No CSS change was needed: every
   class the three screens use already exists. `ShopViewContractTest` green (23
   screens scanned, including the five new ones).

3. **Trust this device.** Verified by test rather than by browser: I did not
   sign in as a manager in the browser because this dev host is
   `osmanager.local`, and I don't enter passwords into a host outside the
   local-development set. `test_a_manager_can_trust_a_device_and_gets_the_cookie`
   asserts the row, the flash, the cookie on the response and that the cookie
   value is not what is stored; `test_an_employee_cannot_trust_a_device` asserts
   403 on both verbs. For the browser run I created the device row in tinker and
   set the correctly-encrypted cookie, which exercises the same server path
   (EncryptCookies → `ShopDevice::findByToken`). With it present, the menu read
   `["Switch user","Lock","Office","Log out"]` and `#shop-root` carried
   `data-idle-lock-seconds="300"`; without it, `["Lock","Office","Log out"]` and
   no idle attributes. **The cookie's own flags (`HttpOnly`, `SameSite=Lax`) are
   therefore asserted in the test, not read off DevTools.**

4. **PIN sign-in, in the browser.** Dev user: **katelyn (id 3, employee)**, PIN
   set to `2580` — set via `setPin()` in tinker, not through the form (the form
   path needs an admin password login; it is covered by `UserPinManagementTest`).
   Switch user showed the grid with katelyn only (the sole employee with a PIN);
   tapping her opened the PIN pad with four dots and "Hi katelyn"; `1357` came
   back with the red `is-error` state and "Wrong PIN, try again."; `2580` landed
   on Shop Home reading "Good afternoon, katelyn" with the chip showing her name.
   Keyboard entry (typing `2580`) auto-submitted on the fourth digit too.
   `shop_switch_logs` after the run:
   ```
   1   switch_failed   user=katelyn  device=1 ip=127.0.0.1 2026-09-27 16:06:48
   2   switch          user=katelyn  device=1 ip=127.0.0.1 2026-09-27 16:07:11
   ```
   `auth_via` is asserted as `pin` in `test_the_right_pin_signs_that_person_in`;
   I did **not** add it to the `auth.check` JSON.

5. **Confinement, in the browser, as the PIN user.** `fetch('/dashboard')`
   followed to `http://osmanager.local/confirm-password`; an off-list JSON
   request returned `403 {"message":"Confirm your password to use the office."}`.
   Shop pages kept working throughout. Lock → the Locked screen with the clock
   (`16:07`, "Sunday 27 September", "Locked after 5 minutes without activity",
   no top bar); "Tap to unlock" → the grid, now as a guest ("Staff sign in" in
   the top bar, no "Signed in" pill). The password-confirm half of this item is
   covered by `test_confirming_the_password_ends_the_confinement` — I did not
   type the password in the browser, for the reason in item 3.

6. **Idle lock, in the browser**, with `idle_lock_minutes` temporarily set to
   `0.5` (30 s) and restored to `5` afterwards (confirmed: `'idle_lock_minutes' => 5,`):
   - No activity → the tab navigated to `/shop/locked` on its own. (At 6 s the
     window was shorter than my tool round-trip, which is why I widened it.)
   - A `pointerdown` every 8 s for 40 s — well past the 30 s window — left the
     tab on `/shop`: `{"idleWindow":30,"elapsed":40,"start":"/shop","stillOn":"/shop"}`.
   Console during the whole browser run: two `Alpine.js started` logs, **no
   errors or exceptions**.

7. **Rate limit, in the browser.** Six POSTs with the wrong PIN, then the
   correct one:
   ```
   attempt 1: Wrong PIN, try again.
   attempt 2: Wrong PIN, try again.
   attempt 3: Wrong PIN, try again.
   attempt 4: Wrong PIN, try again.
   attempt 5: Wrong PIN, try again.
   attempt 6: Too many tries — wait 15 minutes.
   correct PIN while locked out: Too many tries — wait 15 minutes.
   ```
   After `RateLimiter::clear('pin:3')` the correct PIN landed on
   `http://osmanager.local/shop`. **Dev is left usable:** the counter is cleared.

8. **Existing sign-in unchanged.** `AuthenticationTest`,
   `PasswordConfirmationTest`, `LandingRedirectTest`, `UiModeTest`,
   `RoutePermissionsTest`, `ShopViewContractTest` → `101 passed (340 assertions)`,
   none of them modified. Manager → `/dashboard`, barista → KDS and `?redirect=`
   are those tests' subject matter.

9. **Personal phone.** With the device cookie deleted in the browser:
   ```
   {"switchShowsNotSetUp":true,"switchShowsGrid":false,
    "pinPageStatus":404,"idleAttrOnSwitch":false}
   ```
   Password login is untouched (item 8).

**Dev machine left in this state:** katelyn (id 3) has PIN `2580`; the
`Dev browser check` device row (id 1) is **revoked** (`revoked_at` set), which
also exercised the documented revoke path; the rate-limit counter is cleared;
`config/shop.php` is back to 5 minutes.

## Files changed

Mine (new):
```
app/Http/Controllers/Shop/ShopDeviceController.php
app/Http/Controllers/Shop/SwitchUserController.php
app/Http/Middleware/ConfinePinSession.php
app/Models/ShopDevice.php
app/Models/ShopSwitchLog.php
app/Rules/NotTrivialPin.php
app/Services/Shop/ShopDeviceService.php
database/factories/ShopDeviceFactory.php
database/migrations/2026_09_27_100000_add_pin_to_users.php
database/migrations/2026_09_27_100001_create_shop_devices_table.php
database/migrations/2026_09_27_100002_create_shop_switch_logs_table.php
docs/features/shop-mode.md
resources/js/shop/idle-lock.js
resources/js/shop/pin-pad.js
resources/views/shop/device-trust.blade.php
resources/views/shop/locked.blade.php
resources/views/shop/pin.blade.php
resources/views/shop/switch-untrusted.blade.php
resources/views/shop/switch-user.blade.php
tests/Feature/Shop/ConfinePinSessionTest.php
tests/Feature/Shop/ShopPinSwitchTest.php
tests/Feature/UserPinManagementTest.php
tests/Unit/Rules/NotTrivialPinTest.php
```

Mine (modified):
```
CLAUDE.md
app/Http/Controllers/Auth/ConfirmablePasswordController.php
app/Http/Controllers/UserManagementController.php
app/Http/Middleware/ShareUiMode.php
app/Models/User.php
app/Providers/AppServiceProvider.php
bootstrap/app.php
config/shop.php
database/factories/UserFactory.php
docs/FEATURES_INDEX.md
resources/js/shop.js
resources/views/components/shop/topbar.blade.php
resources/views/layouts/shop.blade.php
resources/views/users/create.blade.php
resources/views/users/edit.blade.php
resources/views/users/index.blade.php
routes/auth.php
routes/web.php
```

Pre-existing dirty, **not mine** (cycles 24–25):
```
 M app/Console/Commands/PruneFruitVegThumbnails.php
 M app/Http/Controllers/CustomerRequestController.php
 M app/Http/Controllers/DeliveryLegacyController.php
 M app/Services/CustomerRequestService.php
 M app/Services/ProductThumbnailService.php
 M resources/css/shop.css
 M resources/js/shop/delivery-scan.js
 M resources/js/shop/delivery-summary.js
 M resources/js/shop/product-images.js
 M resources/views/shop/delivery-scan.blade.php
 M resources/views/shop/delivery-summary.blade.php
 M tests/Concerns/CreatesLegacyDeliveryPosTables.php
 M tests/Feature/CustomerRequestDeliveryFlagTest.php
 M tests/Feature/FruitVegProductImageTest.php
 M tests/Feature/Shop/ShopDeliveryTest.php
?? app/Http/Controllers/Concerns/
?? app/Http/Controllers/Shop/ProductPhotoController.php
?? app/Services/Shop/ProductImageUrls.php
```
(`routes/web.php` and `resources/js/shop.js` were already dirty from cycles
24–25 **and** carry my changes.)

No commits, no deploys, as the Constraints required.

## Notes for Planner

1. **`products.image` is off the allow-list, and that costs PIN users their
   product thumbnails.** The plan names it as the example of an off-list JSON
   route for step 5's test, so I implemented it that way and the test uses it —
   but `ProductSearchService::imageUrl()` (line 525) returns
   `route('products.image', …)` for any product with a POS blob, and that URL is
   what `image_url` carries into Find product, delivery scan, F&V and the
   requests board. For a PIN session those `<img>` requests now 302 to
   `/confirm-password`, the browser fires `onerror`, and the row falls back to
   its placeholder icon. It degrades gracefully and nothing throws, but a PIN
   user sees grey icons where a password user sees pictures. `shop.product-photo`
   (covered by `shop.*`) is the auth'd Shop equivalent and does not have this
   problem — it is just not what the search API returns. Options: (a) add
   `products.image` to `pin_session_routes` (it is a read of a picture for a
   product the Shop already shows by name, and the route keeps its own
   `products.view` permission check), and pick another off-list route for the
   403 test — `settings.index` would do; (b) make `ProductSearchService` return
   `shop.product-photo` when `UiMode` is shop; (c) accept the placeholders.
   I'd suggest (a) now and (b) as the tidier fix later. **I did not change this**
   — it is your call, and the plan was explicit.

2. **`users.show` routes to a controller method that does not exist.**
   `routes/web.php:769` registers `GET /users/{user}` → `UserManagementController@show`,
   and there is no `show()` on that controller (and no `resources/views/users/show.blade.php`).
   Pre-existing, unrelated to this cycle, and it will 500 for anyone who reaches
   it. Worth a one-line follow-up: either write the method or drop the route.

3. **`Route::get('logout')` (`logout.get`) is allow-listed.** It had to be, since
   the topbar and the stale-page detector can reach it, but it means a PIN
   session can be ended by any link on any page. That is the same exposure the
   plan already noted for `GET /shop/lock`, so I have not treated it as new —
   flagging it only because the allow-list makes it explicit in config now.

4. **The idle lock and the stale-page detector both navigate on their own.**
   They cannot currently fight (the detector only fires on `visibilitychange`
   when the session is already gone, by which point the lock has done its work),
   but a future change to either should check the other. Both live in
   `resources/views/layouts/shop.blade.php`.

5. **Five minutes may prove short on the delivery scan screen.** Scanning is
   `keydown`, so an active scan resets the timer — but a person reading a
   delivery note for six minutes without touching the tablet will be locked out
   mid-session and will have to re-enter a PIN. The owner asked to try five and
   adjust; if it bites, the cheapest fix is a per-route override in
   `config/shop.php` rather than raising it everywhere.

6. **`public/images/shop-icons.svg` differs from
   `docs/design/shop-mode/shop-icons.svg`** (byte 7458). Pre-existing — both
   files are committed and untouched by this cycle — but the cycle-1 check
   `cmp public/images/shop-icons.svg docs/design/shop-mode/shop-icons.svg` no
   longer passes, so if any future plan reuses that check it will need the
   `head -c` treatment the CSS check already has, or the design copy needs
   updating.

7. **`UserFactory::withPin()` writes `pin_hash` through the factory**, which
   works because factories bypass `$fillable`. `setPin()` is the only other way
   in and uses `forceFill`. Worth knowing if anyone later tries
   `User::create(['pin' => …])` and is surprised that nothing happens.

8. **Nothing seeds PINs.** Every existing employee has `pin_hash = null`, so on
   the day this ships the Switch user grid will be empty until someone sets
   PINs, and the empty state ("Nobody has a PIN yet — a manager can set one on
   the staff page in the office") is what staff will see. That is deliberate and
   safe, but it is worth telling the owner before the first shift rather than
   after.
