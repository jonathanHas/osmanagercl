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
  `<x-shop.icon name="…" />`. App additions to the design sprite: `more`,
  `phone`, `info`, `help`.
- **Help button**: the topbar shows a "How to do this" icon button
  (`components/shop/help-button.blade.php`) before the user chip when the
  screen has a procedure in BookStack (managers: on every screen). See
  [Staff Procedures](./sops-bookstack.md).

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

A bottom action bar (`.shop-actions`) is `position: sticky`, and a sticky
element cannot leave its parent's box. It must be laid out as a child of the
page's tall container: `<main class="shop-page">`, or a form that wraps the
whole page. A wrapper that exists only for an Alpine scope or a form takes the
`shop-contents` class (`display: contents`, so it generates no box). The
Customer requests "New request" bar and the Print labels "Print" bar use it;
without it they sat at the end of the list instead of on screen.

### Which interface a request belongs to

`App\Support\UiMode` resolves it: a `shop.*` route is always Shop; a PIN
sign-in is always Shop; otherwise the `ui_mode` cookie, then the role
(employees and baristas default to Shop). `UiMode::landingUrl()` decides where
a sign-in lands — baristas go to the KDS, not Shop Home.

### Camera scanning

Live scanning is [html5-qrcode](https://github.com/mebjas/html5-qrcode) **2.3.8**,
wrapped by `resources/js/barcode-scanner.js` and driven by
`resources/js/shop/scan-input.js`. It needs a secure context, so it runs on
production (HTTPS) and not on a plain-HTTP dev host.

Reading a code **pauses** the decoder rather than stopping it, and the page
resumes it by dispatching `shop-scan-saved` once its action is done. Stopping
and restarting costs one to three seconds on a phone — the stream is torn down,
cameras are re-enumerated and the decoder re-initialised — which is too slow to
scan a delivery item by item. A pause costs nothing to undo. If the library
refuses to pause, the field stops the camera instead: a live decoder under an
open quantity prompt would read the item still under the lens and confirm it.

**Three things are tied to that library version. A dependency bump must
re-check all three on a real phone, because none of them can be exercised on
dev:**

1. `pauseScanner()` / `resumeScanner()` in `resources/js/barcode-scanner.js`
   guard on `Html5QrcodeScannerState.SCANNING` and `.PAUSED`. The enum is
   exported from the package entry in 2.3.8; earlier versions may not export
   it.
2. `.shop-scan__mount > div:not(#qr-shaded-region)` in `resources/css/shop.css`
   hides the library's own "Scanner paused" banner, which it appends to the
   mount as an id-less `<div>` with an inline `display:block`. The selector is
   safe only while the video and canvas are not wrapped in a `div`.
3. The mount's pinned-absolute rules (cycle 16), also in `shop.css`: the
   library sizes its `<video>` from the mount's `clientWidth` as an inline
   style, so the mount has to fill the camera box or the scan region collapses.

A USB hand scanner is a keyboard, not a camera: it types the digits and an
Enter into whatever has focus. `capture()` in `scan-input.js` takes those
keystrokes into the scan field when the focus is somewhere that cannot use
them — a stepper button, say — and treats an Enter within a second of the last
character as the end of a scan. A lone Enter still presses a focused button.
As a backstop, the delivery endpoints cap `quantity` at 9999, so a barcode can
never be written as a quantity.
Delivery quantities are stored rounded to 3 decimals, so typed weights add up
without floating-point tails (`1.94 + 3.74` is stored as `5.68`).
Completing a delivery creates a stock record for a delivered product that had
none, holding the delivered amount, instead of skipping it. This is the shared
`delivery-legacy.complete`, so it applies on the office page too; undoing the
completion takes that record back to 0 (it is not deleted).

### Deliveries: items without a barcode

Some deliveries cannot be scanned — the weekly Mossfield cheese has no barcode
and arrives as a weight per wheel. The delivery scan screen has a small **No
barcode? Find by name** button under the scan field (for anyone with
`products.view`; not on a completed delivery). It opens a list, in the page
flow, of the supplier's stocked products, with a filter; once something is
typed, **Search all products** searches the whole catalogue instead, for a
product not linked to the supplier. Both use `GET /api/products/search`
through `product-typeahead.js`. The button appears once the page is ready
(`x-cloak`), so an early tap is never lost.

A product search that fails — here, on New request and on request edit, all
through `product-typeahead.js` — says so rather than showing an empty list:
"Could not load products." with **Try again**, or "You have been signed out.
Reload the page to sign in." for a 401/419. Only the latest search writes its
results, so a slow older answer cannot replace a newer one. A search that
succeeds with nothing shows "No products match" (the typeahead's `noMatches`),
only once the search for the text on screen has answered — never during the
debounce before it has run.

Picking a product opens the same quantity prompt a scan opens, with an empty
**Quantity or weight** field focused and Add disabled until something valid is
typed. Typing `4.35` and Add records 4.35 (the product is stocked in kg, so
4.35 is added to stock at completion). The pick posts the product's code to
`delivery-legacy.scan-increment`, exactly as a scan does, so nothing
distinguishes a typed row from a scanned one. The list stays open between
items — pick, type, Add, pick, type, Add — until it is closed, and scanning
keeps working in the same delivery. With no invoice loaded (Mossfield never
has one) each add is confirmed as "Added · N so far" rather than warned about.
A scan that arrives while a picked item has no amount typed replaces that
prompt, and a warning says the item was not added.

Typed amounts are also available where a quantity already exists: tap the
number on a scan prompt (not on a case prompt, where the count stays whole on
the stepper), or the number on the correction card (type, then **Set**; 0
removes an unexpected row, as the stepper can). A typed quantity is a positive
number of at most 4 whole digits and 3 decimals (a decimal comma is accepted):
a barcode typed or wedge-scanned into the field is 8–14 digits, so it can never
be saved as a quantity — Add simply stays disabled.

### Deliveries: unknown barcodes and outer codes

A scan that no product has (not a unit barcode, not an outer code the supplier
link knows) opens a **Not found** card above the scan field showing the code,
with **Link as outer barcode**. Tapping it turns the card and the scan field
amber and asks for the barcode on one item from the case (or a pick from Find
by name). The product found is shown with its picture and **Yes, link it** /
**Not this one**, and nothing is saved until Yes is tapped: a scan of a code
that is already a case barcode, or that no product has, is refused with a
message and the card keeps waiting. **Cancel linking** or Dismiss leaves
without saving. The link is
saved on `supplier_link.OuterCode` through the office page's own
`delivery-legacy.save-outer-barcode`, so the legacy match page and the Shop
screen share one write path. A GS1-128 code is stored as the GTIN-14 in AI
(01). An outer code already on another product of the same supplier, or a unit
barcode the supplier does not carry, is refused and the server's message shows
on the card, which stays open for another try; Dismiss closes it.

On success the outer code is looked up again and opens a case prompt ("Outer ·
Case of N", "Add 1 case · N units"), completing the scan the person made a
moment ago. Case units are not editable from the Shop screen: a link whose
`CaseUnits` is 0 or 1 gives a "Case of 1" prompt, and the office page's case
units control is the place to fix it.

### Orders: list and order review

A Home tile **Orders** (permission `orders.review`) opens `/shop/orders`: the
20 newest draft supplier orders and the 5 most recently completed, each opening
`/shop/orders/{order}`, the order review (design screen 20). Orders are still
generated in the office; the Shop only reviews them.

The review shows, per product: name, code, pack size, the priority pill
(Review / Standard / Safe, or Added for products added by search) and tags
(`Destocked` when the product has no `stocking` row, `Kitchen` for kitchen
products); a weekly sales chart over the session's history weeks with the
average as a dashed line and four projected stock bars after delivery; stock,
stock after delivery and a cover pill (`No recent sales` when the average is
0); and a case stepper (units for single-unit products). Filters, a "Show not
ordered" switch, a text search and three sorts work in the browser. The
sticky bar shows the total and **Export CSV** (the same file as the office
export).

- **Stock is the generation-time snapshot** (`context_data.current_stock`), the
  number the suggestion was computed from, not live stock. Sales come from
  `context_data.weekly_sales`; there is no POS query per row.
- **Each tap saves** (debounced 400 ms per item, optimistic, reverted with a
  toast on failure) through `PATCH shop.orders.item`, which calls
  `OrderService::updateOrderItemCases()`, so the learning log and session totals
  stay right. Only drafts are editable: any other status answers **409**
  `{error: 'Order is not editable'}` and the screen opens read-only (steppers
  disabled, export still allowed).
- **`orders.review`** is held by employee, manager and admin (migration
  `2026_10_06_000001_add_orders_review_permission`, and the seeder). It is not
  `orders.manage`, which stays manager-only.
- **Week readout**: pointing at a sales bar shows that week's sales in a small
  pill above it ("Week of 3 Aug · 25 sold", from `context_data.weekly_sales[*].week_start`);
  a projected bar shows its stock estimate ("Week 2: about 12 in stock"). On the
  tablet a tap pins the readout until the next tap. The bars are rendered from one
  `x-html` per side for speed, so the plot delegates the pointer events.
- **Pictures**: each row shows the product thumbnail (till photo, else supplier
  picture, else the placeholder; `ProductImageUrls::byCode()` once per order).
  Hovering it shows the larger picture in Find product's panel, now the shared
  part `resources/js/shop/product-peek.js`; on touch a tap on the thumbnail pins
  the panel and a tap anywhere else closes it.
- **Chilled groups first**: rows in the chilled POS categories, Cheese (`032`)
  then Refrigerated (`002`), from `SpecialOrderCategories::displayGroups()`, are
  listed in their own groups ahead of Case products and Single units, **for every
  supplier**. A chilled row keeps its case or unit stepper. The groups come with
  the order's header, and each group shows 50 rows at a time ("Show 50 more").
- **Office-only**: generating or completing an order, priorities, adding
  products by search, destock/kitchen toggles, min-stock, coverage overrides,
  pallet fill, and the Christmas comparison view (a Christmas session opens as
  a plain review).
- Code: `app/Http/Controllers/Shop/OrderReviewController.php`,
  `app/Services/Shop/OrderReviewService.php`, `resources/views/shop/orders.blade.php`,
  `order-review.blade.php`, `resources/js/shop/order-review.js`. See also
  [Order Generation](./order-management/order-generation.md).

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
the request typeahead. The route keeps its own `products.view` gate. `help.*` is on it too (the
procedure reader, its image proxy and the manager Refresh form; see
[Staff Procedures](./sops-bookstack.md)); `help.refresh` keeps its own
`role:manager,admin` gate. `ConfinePinSessionTest::test_every_route_a_shop_view_names_is_on_the_allow_list`
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
  [Fruit & Veg](./fruit-veg-system.md), [Vouchers](./voucher-management.md),
  [Order Generation](./order-management/order-generation.md) (Shop order review).
