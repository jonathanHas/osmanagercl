# Shop mode cycle 27 — PIN follow-ups — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-27

## Baseline
HEAD: 4e7373ea (cycles 24–26 committed; the tree was clean)
Pre-existing dirty files:
```
?? docs/planImp/plan.md
```
Test baseline confirmed before starting: **15 failed, 741 passed (3141 assertions)**.

## Steps

### 1. Allow-list `products.image` — done
Changed: `config/shop.php`, `tests/Feature/Shop/ConfinePinSessionTest.php`.

`'products.image'` added to `pin_session_routes` with a comment noting it is the
search API's POS-photo URL and that the route keeps its own `products.view`
gate. The off-list JSON example became `route('settings.index')` (with the
`settings.view` permission on the fixture so the *only* thing refusing it is the
confinement). Added `test_a_pin_session_may_load_a_product_picture`, which
asserts the response is neither 403 nor the confinement redirect.

Check:
```
Tests:    8 passed (81 assertions)
```

Browser (item 3 below) is the real proof: a 47 KB JPEG now loads.

### 2. Devices page (office) — done
Changed: `app/Http/Controllers/ShopDeviceAdminController.php` (new),
`resources/views/shop-devices/index.blade.php` (new), `routes/web.php`,
`resources/views/layouts/admin.blade.php`,
`tests/Feature/ShopDeviceAdminTest.php` (new).

Both `belongsTo` relations the plan asked me to add if missing
(`ShopSwitchLog::user()`, `::device()`) **already existed** from cycle 26, so
nothing was added there.

Routes sit inside the auth group just above `users.*`, wrapped in
`Route::middleware('role:manager,admin')` — the same gate as trusting.
`revoke()` returns `back()` with an `error` flash when the device is already
revoked (no log row, no timestamp change) and otherwise sets `revoked_at`, logs
`revoke` through `ShopDeviceService::log()`, and flashes `success`.

The page: intro paragraph (including the "anyone currently signed in stays
signed in until the screen locks" caveat the plan's Risks called for), the
device table (name, trusted by, last user, last used, Active/Revoked with date,
Revoke as a small POST form with no `confirm()` dialog), then "Recent switch
activity" — the last 100 log rows with human labels (Trusted, Switched, Wrong
PIN, Locked out, Locked, Revoked). Empty states for both tables.

**Sidebar guard (the plan asked me to say which):** I used a role check,
`isManager() || isAdmin()`, to match `role:manager,admin` on the routes rather
than inventing a permission. That exposed a second problem — see Deviations 1.

Check:
```
Tests:    7 passed (32 assertions)
```
Covering: a manager sees the device, its Active badge, the log section, the
human label and the IP; the `token_hash` and the raw token are never rendered;
an employee gets 403 on both the page and the revoke POST and the device stays
active; revoking sets `revoked_at`, writes a `revoke` row, and a subsequent
request carrying that device's cookie is untrusted (the Switch user page flips
to "not set up"); revoking twice is a no-op with a notice and writes no second
row; both empty states render; the sidebar link is present for a manager.

### 3. Own PIN on the profile page — done
Changed: `app/Http/Controllers/ProfileController.php`,
`resources/views/profile/partials/update-pin-form.blade.php` (new),
`resources/views/profile/edit.blade.php`, `routes/web.php`,
`tests/Feature/ProfilePinTest.php` (new).

**Which class (the plan asked):** `ProfileController::updatePin`, beside
`destroy()`, which already uses the `validateWithBag` pattern. No new
controller.

`PUT /profile/pin` → `profile.pin.update`. Validation in the `updatePin` bag:
`current_password` `required|current_password`, `pin`
`nullable|digits_between:4,6|confirmed` + `NotTrivialPin`, `clear_pin` boolean.
A user whose role cannot use a PIN is refused before validation runs. Clearing
wins over setting. The partial renders only when `canUsePin()`, after the
password form, and shows "PIN set <date>" or "No PIN set", with the "Remove my
PIN" checkbox only when one exists.

Check:
```
Tests:    10 passed (36 assertions)
```
Covering: an employee sets a PIN with the right password (hash, length,
`set_at`); a wrong current password changes nothing and errors in the
`updatePin` bag; trivial PIN rejected; mismatched confirmation rejected; an
empty submission says so rather than silently doing nothing (Deviation 2);
clear works; clearing wins over setting; a manager is refused by the route; the
section shows for an employee and not for a manager; the "Remove my PIN" box
appears only when a PIN is set.

### 4. `users.show` — done
Changed: `routes/web.php`, `tests/Feature/UserPinManagementTest.php`.

`grep -rn "users.show" resources app tests` → **no matches** (exit 1). Nothing
referenced it, so per the plan's first branch I **deleted the route**. Added
`test_there_is_no_user_show_page`.

Check: `GET /users/3` over HTTP is now `405`, not a 500:
```
GET /users/3 (was a 500):    HTTP 405
GET /users/3/edit:           HTTP 302   (unchanged, redirects to login)
```

### 5. Docs and tidy — done
Changed: `docs/features/shop-mode.md`, `docs/FEATURES_INDEX.md`.

`shop-mode.md`: the "Self-service PIN … not built yet" line replaced with the
profile-page flow; `revoke` added to the event list and a note that the last 100
rows appear on the page; "Revoking a device / Not yet a page" replaced with a
new "The Shop devices page" section covering the listing, the revoke semantics
(next request, not an immediate sign-out) and why there is no undo button; a
`products.image` note added to the confinement section explaining why it is on
the allow-list. `FEATURES_INDEX.md`: `revoke` added to the audited bullet, plus
new **Managed** and **Self-service** bullets.

Tidy:
```
./vendor/bin/pint --dirty   → PASS, 8 files
php artisan view:clear      → INFO  Compiled views cleared successfully.
```
`npm run build` **not run** — no JS or CSS changed this cycle, as the plan
expected (`git diff --stat resources/css resources/js` is empty).

## Deviations

1. **The Administration sidebar section itself had to admit managers.** The
   plan said to add one link beside Users and not to restructure the sidebar. I
   added the link, and my own sidebar test failed: the whole Administration
   block is wrapped in
   `@if(can('users.view') || can('settings.view') || isAdmin())`, so a manager
   holding neither permission would never see the section and therefore never
   see the new link. I added `|| auth()->user()->isManager()` to that one
   condition, with a comment. It is one boolean on an existing line, not a
   restructure, and without it step 2's deliverable is unreachable for exactly
   the audience it is for. (Managers on this dev database do hold those
   permissions, so a manual check would probably not have caught it.)

2. **An empty PIN submission is a validation error, not a silent no-op.** The
   plan's validation makes `pin` `nullable`, and with `clear_pin` unticked that
   would mean submitting the form does nothing and still says "Saved." I made
   that case an error on the `pin` field: "Enter a new PIN, or tick 'Remove my
   PIN'." The admin form does not need this because it is a general-purpose
   user-edit form where a blank PIN legitimately means "leave it alone"; this
   form's only job is the PIN.

3. **`GET /users/{id}` returns 405, not the 404 the plan predicted.** The URI
   still exists for PATCH and DELETE, so Laravel rejects the verb rather than
   the path. The test asserts 405 and says why. The point — it is no longer a
   500 — holds.

4. **Two relations the plan offered to have me add already existed.**
   `ShopSwitchLog::user()` and `::device()` were written in cycle 26. No change.

Nothing under **Out of scope** was touched: no office re-trusting, no log
pruning, no Shop screen change.

## Verification

1. **`php artisan test`** → `Tests: 15 failed, 760 passed (3214 assertions)`.
   The same 15 pre-existing failures, unchanged in name and count
   (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2,
   TestScraper ×1). Passing went 741 → 760; the 19 new tests are all mine.

2. **Contract.** `ShopViewContractTest` green;
   `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css`
   prints nothing (`DESIGN-BLOCK-IDENTICAL`). No CSS change, and no Shop view
   changed this cycle.

3. **Find product as the PIN user shows POS-photo thumbnails.** Signed in as
   katelyn by PIN on a seeded trusted device, and confirmed it really was a PIN
   session first (`fetch('/dashboard')` still lands on `/confirm-password`).
   Searching "cabbage":
   - The search API returns `products.image` URLs, e.g.
     `http://osmanager.local/products/004743a0-…/image`.
   - Fetching one directly: `{"status":200,"contentType":"image/jpeg","bytes":47240,"redirectedToConfirm":false}`.
   - In the rendered list, **7 of the 9 loaded thumbnails are `products.image`
     URLs**, all with real `naturalWidth`/`naturalHeight` (100×167, 225×225,
     259×194, …). The other two are supplier-CDN URLs. Before this cycle every
     one of those seven would have been a placeholder.
   - The one `naturalWidth === 0` image is the Alpine placeholder `<img>` with an
     empty `:src` (its `src` resolves to the page URL) — pre-existing markup,
     hidden by `x-show`, not a broken picture.
   - Console: **no errors or exceptions**.

4. **Shop devices page.** **By test, not in the browser** — as in cycle 26 I did
   not sign in as a manager, because this dev host is `osmanager.local` and I
   don't type passwords into a host outside the local-development set. The plan
   explicitly allowed this and asked me to say so. `ShopDeviceAdminTest` covers
   the listing, the log, the 403s, the revoke POST, the double-revoke notice,
   the empty states and the sidebar link (7 tests, all green).

   What I *did* confirm in the browser is the half that does not need a manager
   password — the **effect** of a revoke on a live device cookie. With the
   seeded device trusted, `/shop/switch` showed the people grid; after setting
   `revoked_at` (the same DB effect the page's POST has), the very next request
   rendered "This device isn't set up for PIN sign-in", **and katelyn stayed
   signed in** — exactly what the page's intro promises. Screenshot-confirmed.

   Also confirmed in the real app: a PIN session asking for `/shop-devices` gets
   `403 {"message":"Confirm your password to use the office."}` — the
   confinement catches it before the role gate, which is right for an office
   page.

5. **Profile page.** **By test**, for the same reason as item 4 — and there is a
   second reason worth recording: `/profile` is deliberately off the PIN
   allow-list, so even signed in by PIN I could not reach it without a password.
   That is the plan's own intended path (Risks, third bullet), now confirmed by
   observation rather than by reading. `ProfilePinTest::test_the_section_shows_for_an_employee_and_not_for_a_manager`
   asserts the employee sees "Shop PIN" and "No PIN set." and the manager sees
   neither.

   Because I could not drive the profile form in the browser, I did **not** do
   the plan's "change the PIN and sign in with the new one, then set it back"
   round trip. katelyn's PIN is therefore untouched at `2580`, as documented —
   which is the state the plan wanted her left in anyway.

6. **`GET /users/3` no longer 500s** → `HTTP 405` (see step 4).

**Dev machine left in this state:** katelyn (id 3) still has PIN `2580`; the
only `shop_devices` row is id 1 "Dev browser check", revoked (the cycle-27
browser-check device was deleted after use); 19 switch-log rows; no device
cookie in the browser; no config left modified.

## Files changed

Mine (new):
```
app/Http/Controllers/ShopDeviceAdminController.php
resources/views/profile/partials/update-pin-form.blade.php
resources/views/shop-devices/index.blade.php
tests/Feature/ProfilePinTest.php
tests/Feature/ShopDeviceAdminTest.php
```

Mine (modified):
```
app/Http/Controllers/ProfileController.php
config/shop.php
docs/FEATURES_INDEX.md
docs/features/shop-mode.md
resources/views/layouts/admin.blade.php
resources/views/profile/edit.blade.php
routes/web.php
tests/Feature/Shop/ConfinePinSessionTest.php
tests/Feature/UserPinManagementTest.php
```

Nothing else was dirty at the start, so everything above is mine.
No commits, no deploys, as the Constraints required.

## Notes for Planner

1. **`shop-devices.index` is not on the PIN allow-list, and should stay off.**
   Worth stating explicitly because it is the first office page added since the
   allow-list existed: a manager on a PIN session (impossible today, since
   managers cannot have PINs) would be asked for their password. That is
   correct. Flagging only so a future "let managers have PINs" cycle does not
   trip over it.

2. **The switch log has no pagination and no pruning.** The page caps the
   *display* at 100 rows, so the page itself will not get slow, but the table
   grows unbounded — roughly a row per sign-in, lock and idle-lock, so on a
   busy shared tablet plausibly 50–100 rows a day. The plan put pruning out of
   scope; at that rate it is perhaps 30k rows a year, which is nothing for
   MySQL, so I'd suggest leaving it until someone actually wants older history
   searchable, and then adding filtering and pagination together rather than a
   prune alone.

3. **A revoked device cannot be re-trusted from the office, by design — but the
   old row stays revoked forever and a new row is created each time.** Trusting
   the same physical tablet three times leaves three rows, two revoked, all
   named "Counter tablet". The list will get confusing on a device that is
   re-trusted regularly (e.g. after a browser-data wipe). Options for a later
   cycle: match on name and reuse the row, or add a "hide revoked" toggle. Not
   urgent — I mention it because the dev database already has this shape after
   two cycles of testing.

4. **`ShopDeviceAdminController` sits in the root `App\Http\Controllers`
   namespace**, matching `UserManagementController` and the rest of the office
   controllers, rather than under `Shop\` (which is the *Shop mode* namespace,
   not "things about shop devices"). Say if you'd rather it moved.

5. **The admin sidebar's Administration guard is now four conditions long**
   (`users.view || settings.view || isAdmin || isManager`) and mixes
   permissions with roles. It works, but it is the kind of thing that acquires a
   fifth condition next cycle. If another role-gated admin page appears, it may
   be worth a single `can('admin.section')` permission or a small
   `@canSeeAdminSection` helper. Not worth doing for one link.

6. **`ProfilePinTest` sets `'password' => 'password'` through the factory**,
   relying on the `hashed` cast. That works, but note `UserFactory::definition()`
   also sets a shared static password — if a future change makes the factory's
   password non-deterministic, these tests break in a confusing way. Cheap to
   make explicit later if it bites.
