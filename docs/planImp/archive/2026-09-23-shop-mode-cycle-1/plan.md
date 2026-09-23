# Shop mode cycle 1 — shell, tokens and Home

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-23

## Goal

Give shop-floor staff a separate, simple interface ("Shop mode") without restyling the manager/admin UI. This cycle delivers the shell only: the design tokens and component CSS, a new sidebar-free layout, the Home screen with permission-filtered task tiles, and the plumbing that sends employees and baristas to `/shop` after login while managers keep the existing dashboard. Every later cycle adds one page inside this shell. When this cycle is done, an employee logs in, lands on a warm-cream Home page with large tiles, and each tile opens the existing admin page for that task (later cycles replace those targets one by one).

## Context

Read `docs/design/shop-mode/README.md` first. It holds the approved design (`shop.css`, `shop-icons.svg`, 17 static screens) and the porting rules. Open `docs/design/shop-mode/screen-01-home.html` in a browser: that is what `/shop` must look like.

What exists today:

- **Roles.** Custom RBAC, not Spatie. `app/Traits/HasPermissions.php` on `User` gives `hasRole`, `hasAnyPermission`, `isAdmin/isManager/isEmployee/isBarista`; admin passes every permission check. Roles: admin, manager, employee, barista (`database/seeders/RolesAndPermissionsSeeder.php`). Employees hold `products.view`, `deliveries.view`, `deliveries.process`, `labels.view`, `labels.print`, `categories.view`, `fruit_veg.manage`, `coffee.manage`, `kds.access`, `vouchers.redeem`, `customer-requests.manage`, `till_review.view`. Baristas hold only `kds.access`.
- **Layouts.** `app/View/Components/AdminLayout.php` + `resources/views/layouts/admin.blade.php` (893 lines, used by 192 views, `<body class="font-sans antialiased">` at line 20). `app/View/Components/BoardLayout.php` + `resources/views/layouts/board.blade.php` is a 78-line sidebar-free shell used by the 3 customer-requests views; copy its structure (title prop, `$actions` slot, guest branch with "Staff sign in" linking `route('login', ['redirect' => request()->getRequestUri()])`, and the `visibilitychange` bounce that calls `route('auth.check')`) when building the shop layout. Do not modify `board.blade.php` in this cycle.
- **Login landing.** Three places currently special-case baristas to `kds.index`: `app/Http/Controllers/Auth/AuthenticatedSessionController.php` `store()` (lines 33–43; `create()` at 17–24 honours a same-site `?redirect=` by calling `redirect()->setIntendedUrl()`), `app/Providers/AppServiceProvider.php` `boot()` (`RedirectIfAuthenticated::redirectUsing`, lines 28–31) and `app/Http/Controllers/Auth/RegisteredUserController.php` line 48. `tests/Feature/Auth/AuthenticationTest.php` asserts a role-less factory user is redirected to `route('dashboard', absolute: false)` (lines 30, 60) and that `?redirect=/customer-requests` wins (line 45). Those must keep passing.
- **Dashboard.** `/dashboard` is a closure in `routes/web.php` lines 59–70 using `ProductRepository::getStatistics()`, `AmazonInvoicePending::pending()->count()` and `CustomerRequestService::dashboardCounts()` (returns `['due' => int, 'put_aside' => int]`, `app/Services/CustomerRequestService.php:200`). View `resources/views/dashboard.blade.php`.
- **Sidebar.** Hardcoded in `admin.blade.php`. The "System Tools" section (lines 658–706) is wrapped in `@unless(auth()->user()->hasRole('barista'))`; the only KDS link (lines 679–687, gated by `can('kds.access')`) sits inside it, so baristas have no link to the one page they may use.
- **Middleware.** `role` and `permission` aliases registered in `bootstrap/app.php` (`$middleware->alias([...])`, line 16). No `web` group appends yet.
- **Assets.** `vite.config.js` inputs: `resources/css/app.css`, `resources/css/customer-invoice.css`, `resources/js/app.js`, `resources/js/zpl-preview.js`, `resources/js/barcode-scanner.js`. `resources/js/app.js` imports Alpine and calls `Alpine.start()` synchronously at module evaluation, so any `Alpine.data(...)` registration must be made in an `alpine:init` listener from a module loaded **before** `app.js`. Tailwind 3 with `content` covering `resources/views/**/*.blade.php`; `shop.css` is plain CSS with no `@tailwind` directives and passes through PostCSS untouched. The admin layout loads Figtree 400–600 from bunny.net; the design needs 500–800.
- **Delivery counts.** `App\Models\Delivery::scopeActive()` (line 102) = `whereIn('status', ['draft','receiving'])`.
- **Test conventions.** PHPUnit, sqlite in-memory for both `default` and `pos` connections (`phpunit.xml`). Role users are built as in `tests/Feature/CustomerRequestTest.php:60-85`: `Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee'])`, `Permission::firstOrCreate([...])`, `$role->givePermissionTo($permission)`, `User::factory()->create(['role_id' => $role->id])`. `UserFactory` has no role state.
- **Existing helpers to reuse:** `x-application-logo` is not used by the shop shell (the design uses the `leaf` sprite icon as the brand mark). `auth.check` route exists (`routes/web.php:51`). Breeze `logout` route is `POST /logout` named `logout`.

## Constraints

- Do not commit, push or deploy.
- Do not restyle or restructure anything under the admin layout. The only admin-side edits are the three named in step 10 (body attribute, one new link, moving the KDS link block). `grep -rl "x-admin-layout" resources/views | wc -l` must be the same before and after (192).
- `resources/css/shop.css` must be byte-identical to `docs/design/shop-mode/shop.css`. Do not "improve" it. Retheming later means editing tokens only.
- The design/content contract (this is what keeps the layout easy to change later):
  - `resources/css/shop.css`: tokens and component CSS. Not edited in this cycle.
  - `resources/views/components/shop/*.blade.php` and `resources/js/shop.js`: the only place `shop-*` classes are composed into markup and the only place Alpine behaviour lives.
  - `resources/views/shop/**`: screens. May contain only `x-shop.*` components, `shop-*` classes, Blade control flow and data. **No `<style>`, no `<script>`, no Tailwind utility classes.** Step 12 adds a test that enforces this.
  - `config/shop.php`: navigation as data.
  - `resources/views/layouts/shop.blade.php` is the one file allowed a tiny inline script (touch detection before paint, and the session bounce copied from the board layout).
- Light theme only. Do not add `dark:` variants anywhere in shop views.
- Employees keep every permission they have today. This cycle adds no permissions and gates no routes (that is cycle 2).
- KDS is out of scope: `resources/views/kds/**` is not touched. The Home tile for KDS links to the existing `kds.index`.
- Barista landing changes from `/kds` to `/shop` (owner decision; Home shows them the KDS tile and the user menu). No existing test asserts the old barista redirect.

## Out of scope

- Any page other than Home. Stock scan, price check, deliveries, labels, requests, vouchers, F&V, PIN switch each get their own later cycle. Tiles for them link to existing admin pages for now (see `config/shop.php` in step 3). Do **not** add a Price check tile: there is no existing page for it.
- Permission changes, route gating, `UserFactory` role state (cycle 2).
- PIN quick-switch, "Switch user", "Lock", idle lock, trusted devices (cycle 10). The user menu in this cycle has only Office and Log out.
- The `x-shop.scan-input` component and `barcode-scanner.js` integration (cycle 3).
- Retiring `BoardLayout` (cycle 7).
- A labels badge on the Home tile. `LabelAreaController::getLabelCountsByEventType()` is private; extracting it is cycle 6's job.
- Making the admin sidebar config-driven.
- Tailwind 4, removing inline JS from existing views, anything under `management/*`.

## Steps

### 1. Design assets into the build
Files: `resources/css/shop.css (new)`, `public/images/shop-icons.svg (new)`, `resources/js/shop.js (new)`, `vite.config.js`
What: copy `docs/design/shop-mode/shop.css` to `resources/css/shop.css` unchanged; copy `docs/design/shop-mode/shop-icons.svg` to `public/images/shop-icons.svg` unchanged. Create `resources/js/shop.js` containing only a comment header and `document.addEventListener('alpine:init', () => { /* shop components register here in later cycles */ });`. Add `'resources/css/shop.css'` and `'resources/js/shop.js'` to the `input` array in `vite.config.js`.
Check: `cmp resources/css/shop.css docs/design/shop-mode/shop.css && cmp public/images/shop-icons.svg docs/design/shop-mode/shop-icons.svg` prints nothing; `npm run build` succeeds and `grep -c "shop" public/build/manifest.json` is ≥ 2.

### 2. `UiMode` resolver
Files: `app/Support/UiMode.php (new)`
What: a plain class with constants `SHOP = 'shop'`, `OFFICE = 'office'`, `COOKIE = 'ui_mode'`. Public methods:
- `current(): string`, memoised per instance, resolved in this order: (1) `request()->routeIs('shop.*')` → shop; (2) `session('auth_via') === 'pin'` → shop (future PIN cycle; harmless now); (3) cookie `ui_mode` if it is exactly `shop` or `office`; (4) by role: no user → shop; `isEmployee() || isBarista()` → shop; otherwise office.
- `isShop(): bool`, `isOffice(): bool`.
- `homeRoute(): string` → `'shop.home'` when shop, else `'dashboard'`.
- `landingUrl(?User $user): string` → same rules as `current()` but using the given user instead of `auth()->user()`; returns `route('shop.home', absolute: false)` or `route('dashboard', absolute: false)`.
Register it as a scoped singleton in `AppServiceProvider::register()` (`$this->app->scoped(UiMode::class)`).
Check: `php artisan tinker --execute="echo app(\App\Support\UiMode::class)->current();"` prints `shop` (no user).

### 3. Navigation as data
Files: `config/shop.php (new)`
What:
```php
return [
    'tiles' => [
        ['key' => 'stock-scan',  'label' => 'Stock scan',        'hint' => 'Count and adjust',            'icon' => 'package',  'route' => 'stocking.index',           'permissions' => ['products.view'],            'badge' => null],
        ['key' => 'deliveries',  'label' => 'Receive delivery',  'hint' => 'Scan a delivery in',           'icon' => 'truck',    'route' => 'deliveries.index',         'permissions' => ['deliveries.process'],       'badge' => 'deliveries'],
        ['key' => 'labels',      'label' => 'Print labels',      'hint' => 'Shelf and Zebra labels',       'icon' => 'printer',  'route' => 'labels.index',             'permissions' => ['labels.print'],             'badge' => null],
        ['key' => 'kds',         'label' => 'Coffee orders',     'hint' => 'Kitchen display',              'icon' => 'coffee',   'route' => 'kds.index',                'permissions' => ['kds.access'],               'badge' => null],
        ['key' => 'requests',    'label' => 'Customer requests', 'hint' => 'Pre-orders and sourcing',      'icon' => 'requests', 'route' => 'customer-requests.index',  'permissions' => ['customer-requests.manage'], 'badge' => 'requests'],
        ['key' => 'vouchers',    'label' => 'Vouchers',          'hint' => 'Balance and redeem',           'icon' => 'gift',     'route' => 'vouchers.index',           'permissions' => ['vouchers.redeem'],          'badge' => null],
        ['key' => 'fruit-veg',   'label' => 'Fruit & veg',       'hint' => 'Availability, waste, harvest', 'icon' => 'carrot',   'route' => 'fruit-veg.availability',   'permissions' => ['fruit_veg.manage'],         'badge' => null, 'tone' => 'sage'],
    ],
];
```
`badge` is a string key the controller resolves (config files cannot hold closures). `tone => 'sage'` selects the `shop-tile__icon--sage` variant.
Check: `php artisan tinker --execute="echo count(config('shop.tiles'));"` prints `7`.

### 4. Share the mode with Blade
Files: `app/Http/Middleware/ShareUiMode.php (new)`, `bootstrap/app.php`
What: middleware whose `handle` does `View::share('uiMode', app(UiMode::class))` then `$next($request)`. Append it to the `web` group in `bootstrap/app.php` inside `withMiddleware`: `$middleware->web(append: [\App\Http\Middleware\ShareUiMode::class]);` (keep the existing `alias([...])` call).
Check: `php artisan route:list --path=dashboard -vv 2>/dev/null | grep -c ShareUiMode` prints `1` (or inspect `php artisan route:list --json` for the middleware list on `dashboard`).

### 5. The shop layout
Files: `app/View/Components/ShopLayout.php (new)`, `resources/views/layouts/shop.blade.php (new)`
What: `ShopLayout` constructor props: `string $title = 'Shop'`, `?string $back = null` (URL; when `null` the top bar shows the brand mark and word "Shop" as on Home; pass `route('shop.home')` on every other page), `bool $guestSafe = false`, `bool $bare = false` (no top bar; for the future Locked screen). `render()` returns `view('layouts.shop')`.
`layouts/shop.blade.php`:
- `<!DOCTYPE html><html lang="en">`, `<head>` with charset, viewport, csrf meta, `<meta name="robots" content="noindex, nofollow">`, `<title>{{ $title }} · Shop</title>`, `<link rel="preconnect" href="https://fonts.googleapis.com">`, `<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@500;600;700;800&display=swap">`, then `@vite(['resources/css/app.css', 'resources/css/shop.css', 'resources/js/shop.js', 'resources/js/app.js'])` in exactly that order, then `<style>body{margin:0;background:#f5ead8}[x-cloak]{display:none!important}</style>`.
- `<body data-shell="shop">` then `<div class="shop" id="shop-root">` and immediately after it the touch-detect script: `<script>if (window.matchMedia('(pointer: coarse)').matches) document.getElementById('shop-root').classList.add('is-touch');</script>`.
- Unless `$bare`: `<x-shop.topbar :title="$title" :back="$back" :guest-safe="$guestSafe" />`.
- `{{ $slot }}` (screens render their own `<main class="shop-page">`).
- `</div>`, `@stack('scripts')`, and, for authenticated users only, the `visibilitychange` bounce copied from `layouts/board.blade.php` lines 57–76 (fetch `route('auth.check')`, redirect to `route('login', ['redirect' => request()->getRequestUri()])` when not authenticated).
- If `$guestSafe` is false and there is no authenticated user, the layout must not be reachable anyway (routes are behind `auth`), so no extra guard.
Check: `php artisan view:cache` succeeds (compiles the new layout); revert with `php artisan view:clear`.

### 6. Shop Blade components
Files: `resources/views/components/shop/icon.blade.php (new)`, `topbar.blade.php (new)`, `tile.blade.php (new)`
What:
- `icon`: props `name`, `size = null` (`sm|lg|xl`), `class = ''`. Renders `<svg class="shop-ico{{ $size ? ' shop-ico--'.$size : '' }} {{ $class }}" aria-hidden="true"><use href="{{ asset('images/shop-icons.svg') }}#{{ $name }}"></use></svg>`. Symbol ids are listed in `docs/design/shop-mode/README.md`.
- `topbar`: props `title`, `back`, `guestSafe`. Markup from `screen-01-home.html` (brand variant) and `screen-02-stock-scan.html` (back + title variant): `<header class="shop-topbar">`; when `$back` is null render `<div class="shop-topbar__brand"><span class="shop-topbar__mark"><x-shop.icon name="leaf"/></span><span>Shop</span></div>`, else `<a class="shop-iconbtn" href="{{ $back }}" aria-label="Back"><x-shop.icon name="back"/></a><h1 class="shop-topbar__title">{{ $title }}</h1>`. Then the user area: if authenticated, the `<details class="shop-usermenu">` block from the design with `<summary class="shop-chip">` (avatar = initials, first name) and a `<div class="shop-menu" role="menu">` containing the head (initials, full name, role display name), then **only these items**: an Office item (a `<form method="POST" action="{{ route('ui-mode.set', 'office') }}">@csrf<button type="submit" class="shop-menu__item" role="menuitem"><x-shop.icon name="office"/>Office</button></form>`, hidden for baristas), `<div class="shop-menu__sep"></div>`, and Log out (`<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="shop-menu__item shop-menu__item--danger" role="menuitem"><x-shop.icon name="logout"/>Log out</button></form>`). If not authenticated and `$guestSafe`: `<a class="shop-btn shop-btn--secondary" href="{{ route('login', ['redirect' => request()->getRequestUri()]) }}"><x-shop.icon name="login"/>Staff sign in</a>`. Initials: first letters of the first two words of `name`, uppercased; first name: first word.
- `tile`: props `href`, `label`, `hint = null`, `icon`, `badge = null` (int|null; render `<span class="shop-tile__badge" aria-label="{{ $badge }} waiting">{{ $badge }}</span>` only when `$badge > 0`), `tone = null` (`sage` → add `shop-tile__icon--sage`). Markup exactly as one `<a class="shop-tile">` in `screen-01-home.html`, using `<x-shop.icon :name="$icon" size="lg"/>`.
Check: `php artisan view:cache` succeeds; `grep -L "shop-" resources/views/components/shop/*.blade.php` prints nothing.

### 7. Home controller, view and route
Files: `app/Http/Controllers/Shop/ShopHomeController.php (new)`, `resources/views/shop/home.blade.php (new)`, `routes/web.php`
What: controller `index(Request $request)`: filter `config('shop.tiles')` to those where `$request->user()->hasAnyPermission($tile['permissions'])`; resolve badges: `'deliveries'` → `Delivery::active()->count()`, `'requests'` → `app(CustomerRequestService::class)->dashboardCounts()['due']`, anything else → null; greeting: "Good morning" before 12:00, "Good afternoon" before 17:00, else "Good evening", followed by the user's first name; `$today = now()->format('l j F')`. Return `view('shop.home', compact('tiles', 'greeting', 'today'))`.
View: `<x-shop-layout title="Shop">` → `<main class="shop-page">` → the greeting block and `<nav class="shop-tiles" aria-label="Tasks">` with `@foreach($tiles as $tile) <x-shop.tile :href="route($tile['route'])" ... /> @endforeach`, copied from `screen-01-home.html`. If `$tiles` is empty render `<div class="shop-empty"><div class="shop-empty__icon"><x-shop.icon name="inbox" size="xl"/></div><p class="shop-empty__title">Nothing to do here yet</p><p class="shop-empty__text">Ask a manager to give you access to a task.</p></div>`.
Route, inside the existing `Route::middleware('auth')->group` in `routes/web.php` (put it right after the profile routes near line 75):
```php
Route::prefix('shop')->name('shop.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Shop\ShopHomeController::class, 'index'])->name('home');
});
```
Check: `php artisan route:list --name=shop.home` shows `GET shop`. With `php artisan serve`, an employee at `/shop` sees the tiles; a barista sees only "Coffee orders".

### 8. Dashboard closure becomes a controller
Files: `app/Http/Controllers/DashboardController.php (new)`, `routes/web.php`
What: move the body of the closure at `routes/web.php:59-70` into `DashboardController@index` unchanged (same three data sources, same `view('dashboard', ...)`), and replace the closure with `Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');`.
Check: `php artisan route:list --name=dashboard` shows `DashboardController@index`; `php artisan test --filter=AuthenticationTest` passes.

### 9. Landing redirects and the mode switch
Files: `app/Http/Controllers/UiModeController.php (new)`, `routes/web.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Controllers/Auth/RegisteredUserController.php`
What:
- `UiModeController@set(Request $request, string $mode)`: abort 404 unless `$mode` is `shop` or `office`; queue `cookie(UiMode::COOKIE, $mode, 60 * 24 * 365)` (Laravel encrypts it); redirect to `route('shop.home')` for shop, `route('dashboard')` for office. Route inside the auth group: `Route::post('/ui-mode/{mode}', [UiModeController::class, 'set'])->whereIn('mode', ['shop', 'office'])->name('ui-mode.set');`
- `AuthenticatedSessionController@store`: delete the barista branch (lines 38–41) and return `redirect()->intended(app(UiMode::class)->landingUrl($request->user()));`. Keep `create()` untouched so `?redirect=` still wins.
- `AppServiceProvider::boot`: replace the closure body with `return app(UiMode::class)->landingUrl($request->user());`.
- `RegisteredUserController@store` line 48: `return redirect(app(UiMode::class)->landingUrl($request->user()));` (the request variable name there is `$request`; verify).
Check: `php artisan test --filter=AuthenticationTest` passes (role-less user still lands on `/dashboard`); a manual login as a barista lands on `/shop`.

### 10. Admin sidebar: Shop mode link, barista KDS link, shell marker
Files: `resources/views/layouts/admin.blade.php`
What, three edits only:
1. Line 20: `<body class="font-sans antialiased">` → `<body class="font-sans antialiased" data-shell="admin">`.
2. Cut the KDS link block, lines 679–687 (`@if(auth()->user()->can('kds.access')) … @endif`), out of the System Tools section and paste it, unchanged, into a new block placed immediately before the `<!-- OPERATIONS SECTION -->` comment (line 137), wrapped as: `<div class="px-2 pb-2">` + the KDS block + a "Shop mode" link `<form method="POST" action="{{ route('ui-mode.set', 'shop') }}">@csrf<button type="submit" class="group flex w-full items-center px-2 py-2 text-sm font-medium rounded-md text-gray-300 hover:bg-gray-700 hover:text-white">` with the same 24×24 svg pattern the neighbouring links use (any simple icon) and the text `Shop mode` + `</button></form>` + `</div>`. This block is outside every `@unless(barista)` wrapper so baristas see both links.
3. Nothing else.
Check: `grep -c "kds.index" resources/views/layouts/admin.blade.php` is `1` (the block moved, not duplicated); `grep -n "ui-mode.set" resources/views/layouts/admin.blade.php` shows one hit; logged in as a barista, the sidebar shows "Coffee KDS" and "Shop mode".

### 11. Tests: landing, home, mode
Files: `tests/Feature/Shop/LandingRedirectTest.php (new)`, `tests/Feature/Shop/ShopHomeTest.php (new)`, `tests/Feature/Shop/UiModeTest.php (new)`
What: use `RefreshDatabase`. Build roles/permissions inline as in `CustomerRequestTest.php:60-85` (a small private helper per test class is fine; do not touch `UserFactory`, that is cycle 2). `ShopHomeTest` renders `/shop` only, which does not query the POS connection, so no in-memory `pos` tables are needed.
- `LandingRedirectTest`: employee `POST /login` → `assertRedirect('/shop')`; barista → `/shop`; manager → `/dashboard`; role-less user → `/dashboard`; manager with cookie `ui_mode=shop` (use `$this->withCookie('ui_mode', 'shop')` — note Laravel's `EncryptCookies` means the test helper `withUnencryptedCookie` is the one that bypasses encryption; use whichever makes `request()->cookie('ui_mode')` return `shop`, and assert it does) → `/shop`; `GET /login?redirect=/customer-requests` then `POST /login` → `/customer-requests`.
- `ShopHomeTest`: employee (with `products.view`, `deliveries.process`, `customer-requests.manage`) sees "Stock scan", "Receive delivery", "Customer requests" and does not see "Coffee orders"; barista (with `kds.access`) sees only "Coffee orders" and does not see "Office"; manager sees "Office"; a user with no permissions sees "Nothing to do here yet"; response contains `data-shell="shop"`; with two `Delivery::factory()`-free rows inserted directly (`Delivery::create([...])` with `status => 'receiving'`, minimal required columns from the deliveries migration) the deliveries tile shows the badge `2`; guest `GET /shop` → redirect to login.
- `UiModeTest`: `POST /ui-mode/shop` as manager → cookie `ui_mode` set and redirect `/shop`; `POST /ui-mode/office` → redirect `/dashboard`; `POST /ui-mode/other` → 404; `GET /dashboard` as manager contains `data-shell="admin"`.
Check: `php artisan test --filter=Shop` → all pass.

### 12. Test: the design/content contract
Files: `tests/Feature/Shop/ShopViewContractTest.php (new)`
What: iterate every `*.blade.php` under `resources/views/shop/` recursively. Fail with the file name if the content contains `<style` or `<script`, or if any `class="…"` attribute contains a token that does not start with `shop` and matches `/^(bg-|text-|p-|px-|py-|pt-|pb-|pl-|pr-|m-|mx-|my-|mt-|mb-|ml-|mr-|w-|h-|min-|max-|rounded|border|shadow|gap-|flex|grid|items-|justify-|font-|space-|hidden|block|inline|dark:|sm:|md:|lg:|xl:|hover:|focus:)/`. Components and layouts are not scanned. Include one positive assertion that the scan found at least one file (so an empty folder cannot pass silently).
Check: `php artisan test --filter=ShopViewContractTest` passes; temporarily adding `class="p-4"` to `shop/home.blade.php` makes it fail, then revert.

### 13. Format
Files: all PHP touched
What: `./vendor/bin/pint --dirty` (or `./vendor/bin/pint` on the new files and the edited controllers/providers).
Check: `./vendor/bin/pint --test` reports no issues on the touched files.

## Verification

Run in order and paste real output:

1. `cmp resources/css/shop.css docs/design/shop-mode/shop.css && echo SAME` → `SAME`.
2. `npm run build` → completes; `public/build/manifest.json` lists `resources/css/shop.css` and `resources/js/shop.js`.
3. `php artisan route:list --name=shop` → `shop.home`; `php artisan route:list --name=ui-mode.set` → `POST ui-mode/{mode}`.
4. `php artisan test` → all green (the full suite, not only `--filter=Shop`).
5. `grep -rl "x-admin-layout" resources/views | wc -l` → `192` (unchanged).
6. `git diff --stat resources/views/layouts/admin.blade.php` → a small diff (roughly +15 / −9 lines); `git diff resources/views/kds/` → empty.
7. `./vendor/bin/pint --test` → clean on touched files.
8. Manual, with `php artisan serve` and the Vite dev server or a built manifest: log in as an employee → `/shop` renders like `docs/design/shop-mode/screen-01-home.html` (cream background, tiles, user chip); open the chip → Office and Log out only; Office → `/dashboard` with the sidebar showing a "Shop mode" entry; Shop mode → back to `/shop`. On a phone or the tablet the page uses the 2-column tile grid; on the desktop 4 columns.

## Risks

- **Vite entry order.** `resources/js/shop.js` must appear before `resources/js/app.js` in the shop layout's `@vite([...])`, and must register via `alpine:init`, because `app.js` starts Alpine on evaluation. Nothing registers yet, but get the order right now.
- **Cookie in tests.** Laravel encrypts cookies; a test that sets `ui_mode` must use the helper that produces a cookie the `EncryptCookies` middleware accepts (`withUnencryptedCookie` is the usual choice on Laravel 11/12, but confirm by asserting the landing URL). If it proves awkward, assert the cookie path via `POST /ui-mode/shop` followed by `GET /dashboard` in the same test session instead.
- **`redirect()->intended()` precedence.** After login, an `intended` URL set by `?redirect=` must still win over `landingUrl()`. `intended($default)` does exactly that; do not replace it with a plain `redirect()`.
- **Sprite `<use>` and caching.** `asset('images/shop-icons.svg')` is a public file; browsers cache it. If icons do not appear during development, hard-refresh. If the app is ever served from a different origin than its assets, `<use href>` cross-origin is blocked; not the case here.
- **Figtree weights.** The shop layout loads Google Fonts; if the shop has no internet on the tablet, the stack falls back to system-ui by design. Do not copy the admin layout's bunny.net link (it lacks 700/800).
- **`hasAnyPermission` with an empty user role.** A user with `role_id = null` has no permissions and no role; `landingUrl()` sends them to office and Home would show the empty state. Both are covered by tests.
- **Do not let the contract test scan `layouts/shop.blade.php`.** The layout legitimately holds two small scripts.

## Review

Reviewed 2026-09-23 by the Planner against `implemented.md`, `git diff`, every new file, and a rerun of the verification commands.

Criteria:
1. Assets — PASS. `cmp` identical for both files; manifest lists `shop.css` and `shop.js`.
2. `UiMode` — PASS. Precedence route → PIN session → cookie → role, as specified; `landingUrl()` uses relative URLs; scoped binding registered.
3. `config/shop.php` — PASS. Seven tiles, permissions and badge keys as specified, no Price check tile.
4. `ShareUiMode` — PASS. Appended to the `web` group; the `7` count in the check is the check's fault (`--path=dashboard` matches seven routes), not the code's.
5. Shop layout — PASS. `@vite` order correct, Figtree 500–800, touch-detect script before paint, `data-shell="shop"`, session bounce copied from the board layout.
6. Components — PASS. `icon`, `topbar`, `tile` compose only `shop-*` classes; user menu shows Office (hidden for barista) and Log out only.
7. Home — PASS. Permission filter, greeting, date, badges for deliveries and requests, empty state.
8. Dashboard controller — PASS. Closure body moved unchanged.
9. Landing redirects — PASS. `intended()` retained; `AuthenticationTest` (6 tests) still green.
10. Admin sidebar — PASS. Three edits only; `kds.index` appears once; `x-admin-layout` count unchanged at 192; `resources/views/kds/` untouched.
11. Tests — PASS. 26 tests across `Shop/*` and `AuthenticationTest` green on rerun (62 assertions).
12. Contract test — PASS, including the recorded negative check.
13. Pint — PASS on 16 files.

Full suite: 17 failed / 420 passed on rerun, the same six classes the Implementer identified as pre-existing (`UdeaScrapingServiceTest`, `CashReconciliationTest`, `WasteLogTest`, `ProductTest`, `FruitVegLabelPrintingTest`, `TestScraperControllerTest`). The stash comparison in `implemented.md` is accepted as proof they predate this cycle.

Visual check (done by the Planner with headless Chrome on the page rendered as an employee, served from the app origin): cream ground, brand mark, chip with initials and caret, greeting and date, tiles 4-up at 1280px, 2-up at 768px and 390px, deliveries badge, sage icon on Fruit & veg, all icons resolved from the sprite. Matches `docs/design/shop-mode/screen-01-home.html` minus the intentionally omitted Price check tile.

Deviations 1–6: all accepted. Deviation 2 (use `withCookie`, not `withUnencryptedCookie`) corrects the plan's own guess and is documented in the test.

Carry forward to later cycles:
- Cycle 2 must start from the knowledge that `WasteLogTest`, `ProductTest`, `FruitVegLabelPrintingTest` and `TestScraperControllerTest` already fail for environment reasons; its role-state work on those files cannot be verified by "goes green", only by "fails the same way as before".
- Notes for Planner 4 and 5 (memoisation, duplicated ladder in `UiMode`) are accepted as-is; collapse into one private method when cycle 10 adds the PIN branch.
- Note 2 (dashboard needs POS tables in tests): lift `createPosTables()` into a shared trait the first time a second test class needs it.
- Icon sprite via `<use>` is same-origin only. If assets are ever moved to a CDN or a different host, switch `x-shop.icon` to inline symbols.

Result: ACCEPTED. Owner: archive with `mkdir -p docs/planImp/archive/2026-09-23-shop-mode-cycle-1 && git mv docs/planImp/plan.md docs/planImp/implemented.md docs/planImp/archive/2026-09-23-shop-mode-cycle-1/` (or plain `mv`; both files are untracked so far). Next cycle: access hardening.
