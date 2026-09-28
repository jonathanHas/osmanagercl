# Shop Mode

The simplified, touch-first interface for shop-floor staff, at `/shop`. It is a
second front end over the same application, not a separate app: the same
Eloquent models, services and office endpoints, with a different shell.

## The shell

- **Layout**: `resources/views/layouts/shop.blade.php` via the
  `<x-shop-layout>` component (`app/View/Components/ShopLayout.php`). Props:
  `title`, `back`, `subtitle`, `guestSafe`, `bare`, `guestRefresh`.
- **Screens**: `resources/views/shop/*.blade.php`. One `<main class="shop-page">`
  per screen inside the slot.
- **Components**: `resources/views/components/shop/` — `topbar`, `icon`, `tile`,
  `scan-input`, `photo`, `product-thumb`.
- **Styling**: `resources/css/shop.css`. The top of the file is a byte-identical
  copy of the design bundle (`docs/design/shop-mode/shop.css`); anything the app
  needs beyond it goes below the `APP ADDITIONS START` marker. Check with:
  `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css`
- **Behaviour**: `resources/js/shop/*.js`, registered as Alpine data in
  `resources/js/shop.js` inside its `alpine:init` listener (app.js starts Alpine
  on evaluation, so a registration at module scope would be too late).
- **Icons**: `public/images/shop-icons.svg`, one `<symbol>` per id; used as
  `<x-shop.icon name="…" />`.

### The view contract

`tests/Feature/Shop/ShopViewContractTest.php` enforces it: a screen under
`resources/views/shop/` carries no `<style>`, no `<script>`, and no Tailwind
utility classes — only `shop-*`. Styling lives in `shop.css`, behaviour in
`resources/js/shop/`, and URLs reach JavaScript through `data-*` attributes.

Two Alpine traps worth remembering:

- Never use an Alpine `@` shorthand that is also a Blade directive (`@error`,
  `@class`). Write `x-on:error`.
- `x-show` hides an element but still evaluates its other bindings, so anything
  nullable behind an `x-show` guard needs `?.` in `:src`, `:alt` and handlers,
  or an `x-if` template instead.

### Which interface a request belongs to

`App\Support\UiMode` resolves it: a `shop.*` route is always Shop; a PIN
sign-in is always Shop; otherwise the `ui_mode` cookie, then the role
(employees and baristas default to Shop). `UiMode::landingUrl()` decides where
a sign-in lands — baristas go to the KDS, not Shop Home.

## Shared devices, PINs and locking

Typing a password on a greasy 10-inch touchscreen, several times a shift, is
what this replaces. A shop-floor device can be *trusted*, and on a trusted
device staff sign in with a 4–6 digit PIN.

### Trusting a device

A manager or admin signs in with their password and picks **Trust this device**
from the Shop user menu (`GET|POST /shop/devices/trust`, behind
`role:manager,admin`). That creates a `shop_devices` row and sets a one-year
`shop_device` cookie: HttpOnly, SameSite Lax, and `secure` when the request is
HTTPS — production is HTTPS; the plain-HTTP dev tablet would never receive a
`secure` cookie, which is why it follows the request.

Only the SHA-256 of the token is stored (`token_hash`), so a copy of the
database is not a copy of the device. The raw token is never shown again.

A device that is not trusted shows "This device isn't set up for PIN sign-in"
and a password login button. A personal phone therefore behaves exactly as it
did before this feature existed.

### Who can have a PIN

`config('shop.pin_roles')` — employees only. Managers and admins use a
password, because a PIN session cannot reach the office anyway (below), so a
manager's PIN would be a credential that opens nothing. `User::canUsePin()` is
the single check; the staff form refuses a PIN for any other role, and moving
someone off the shop floor clears their PIN.

A PIN is set by an admin on the staff form (`/users/create`, `/users/{user}/edit`)
in the **Shop PIN** block, which appears only for a shop-floor role. It is
stored as `pin_hash` (bcrypt), with `pin_length` so the pad can draw the right
number of dots and `pin_set_at` for the "PIN set" badge. `pin_hash` is in the
model's `$hidden`. `App\Rules\NotTrivialPin` rejects `1111`, `1234`, `0123`,
`1212` and the like.

Staff can also set, change or remove their own PIN on `/profile` under **Shop
PIN** (`PUT /profile/pin`, `ProfileController::updatePin`). It requires their
current password — a PIN is the weaker credential, so it is minted by the
stronger one — and the section only renders for a role that can use a PIN.

### Signing in

- `GET /shop/switch` — the people grid ("Who is working?"): everyone with a
  shop-floor role and a PIN, the current user marked "Signed in".
- `GET /shop/switch/{user}` — the PIN pad. 404 unless the device is trusted and
  that user may use a PIN and has one, so the page never confirms that a given
  user id exists.
- `POST /shop/switch/{user}` — checks the PIN, then `Auth::logout()`,
  `session()->invalidate()`, `regenerateToken()`, `Auth::login()` and
  `session(['auth_via' => 'pin'])`. A new person gets a genuinely new session.

These routes sit **outside** the `auth` group: they are what a signed-out
shared tablet shows. The device cookie is the gate, not a session.

Five wrong PINs lock that one person's PIN for fifteen minutes
(`shop.pin_attempts`, `shop.pin_lockout_minutes`). The counter is keyed per
user, not per device, so one person fumbling cannot lock the tablet for the
rest of the shift, and a correct PIN clears it. The limiting is done in
`SwitchUserController` rather than with `throttle:` route middleware, because
the named-limiter middleware stores its counter under a hashed key that no
public API can clear.

### Confinement

`App\Http\Middleware\ConfinePinSession` (appended to the `web` group) holds the
rule that a PIN is not a password: while `session('auth_via') === 'pin'`, only
route names matching `config('shop.pin_session_routes')` are reachable.
Anything else redirects to `/confirm-password` (or, for a JSON request, 403s).
Confirming the password calls `session()->forget('auth_via')`, and from then on
it is an ordinary session.

The allow-list is the Shop itself plus every office endpoint a Shop screen
calls. `products.image` is on it (cycle 27): `ProductSearchService::imageUrl()`
hands Shop screens that URL as a product's picture whenever the POS holds a
blob, and leaving it off cost PIN users their thumbnails on Find product and
the request typeahead. The route keeps its own `products.view` gate. `ConfinePinSessionTest::test_every_route_a_shop_view_names_is_on_the_allow_list`
greps `resources/views/shop/**`, the Shop components, the Shop layout and
`resources/js/shop/**` for `route('…')` and fails the suite if one is missing —
so a new Shop screen that calls a new endpoint breaks a test rather than 403-ing
on the shop floor.

### Idle lock

A trusted device with somebody signed in carries `data-idle-lock-seconds` and
`data-idle-lock-url` on `#shop-root`; `resources/js/shop/idle-lock.js` (plain
DOM, deliberately not Alpine, so it survives Alpine failing to boot) navigates
to `GET /shop/lock` after that long with no `pointerdown`, `keydown`,
`touchstart`, `wheel` or `scroll`. The till PC's keyboard-wedge scanner types,
so scanning counts as activity.

`config('shop.idle_lock_minutes')` is 5 — the owner's trial value, meant to be
adjusted in use. An untrusted device never locks: a staff member's own phone is
theirs to leave open.

`/shop/lock` is a GET, like the existing GET logout, so a tab open past its CSRF
token still locks instead of throwing a 419. It logs out and redirects to
`/shop/locked`, which shows the clock and "Tap to unlock" with no top bar. The
clock is server-rendered and kept honest by the layout's guest meta refresh, so
the Locked screen needs no JavaScript.

### The audit trail

`shop_switch_logs` records one row per change of hands: `trust`, `switch`,
`switch_failed`, `switch_blocked`, `lock`, `revoke`, with the device, the user
and the IP. The last 100 rows are shown on the Shop devices page.
The Laravel-side audit fields elsewhere (waste, harvest, labels, requests)
record `auth()->id()` and become correct once the right person is signed in.
The POS `deliveriesScanItems` table has no "scanned by" column, so there is
nothing to attribute there.

### The Shop devices page

`/shop-devices` (`role:manager,admin`, in the admin sidebar under
Administration) lists every trusted device — name, who trusted it, who used it
last and when, active or revoked — and, below that, the last 100 switch-log
rows with human labels (Trusted, Switched, Wrong PIN, Locked out, Locked,
Revoked).

**Revoking** is a POST from that page. It sets `revoked_at`, writes a `revoke`
log row, and the device stops being trusted on its *next* request
(`ShopDevice::findByToken()` filters on `revoked_at`). It does **not** sign out
whoever is currently on the device — they keep their session until the screen
locks or the idle timer fires. There is no undo button because there does not
need to be one: a manager trusts the device again from the Shop menu on the
device itself. Trusting is deliberately not an office action — the point is to
mark *this* hardware.

### Deploying this

1. `php artisan migrate`
2. Set PINs for shop-floor staff on the staff form.
3. On each shared device, a manager signs in with their password and picks
   **Trust this device** from the Shop menu.

HTTPS is required for the device cookie to carry the `secure` flag; production
is HTTPS.

## Where the rest lives

- **Design**: `docs/design/shop-mode/` — the approved static screens
  (`screen-NN-*.html`), the design `shop.css` and `shop-icons.svg`. Ported, not
  edited.
- **Cycle history**: `docs/planImp/archive/YYYY-MM-DD-shop-mode-cycle-N/` — the
  plan and the implementation report for each cycle.
- **Screens with their own docs**: [Customer Requests](./customer-requests.md),
  [Fruit & Veg](./fruit-veg-system.md), [Vouchers](./voucher-management.md).
