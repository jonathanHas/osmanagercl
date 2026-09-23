# Shop mode cycle 1 — shell, tokens and Home — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-23

## Baseline
HEAD: fc5820dc

Pre-existing dirty files (NOT mine):
```
R  docs/planImp/implemented.md -> docs/planImp/archive/2026-09-23-kds-modifier-badges/implemented.md
RM docs/planImp/plan.md -> docs/planImp/archive/2026-09-23-kds-modifier-badges/plan.md
?? docs/design/
?? docs/planImp/plan.md
```

Pre-flight check — all tile target routes exist:
```
stocking.index                  OK
deliveries.index                OK
labels.index                    OK
kds.index                       OK
customer-requests.index         OK
vouchers.index                  OK
fruit-veg.availability          OK
```

Baseline `grep -rl "x-admin-layout" resources/views | wc -l` → `192`

## Steps

### 1. Design assets into the build — done
Changed: `resources/css/shop.css` (new), `public/images/shop-icons.svg` (new), `resources/js/shop.js` (new), `vite.config.js`
Check output:
```
$ cmp resources/css/shop.css docs/design/shop-mode/shop.css && cmp public/images/shop-icons.svg docs/design/shop-mode/shop-icons.svg && echo IDENTICAL
IDENTICAL

$ npm run build
✓ 92 modules transformed.
public/build/assets/shop--SBCLd7J.css                 37.26 kB │ gzip:  6.70 kB
public/build/assets/shop-LCihtINI.js                   0.05 kB │ gzip:  0.07 kB
✓ built in 12.83s

$ grep -o '"resources/[a-z/.]*shop[a-z.]*"' public/build/manifest.json | sort -u
"resources/css/shop.css"
"resources/js/shop.js"
```

### 2. `UiMode` resolver — done
Changed: `app/Support/UiMode.php` (new), `app/Providers/AppServiceProvider.php` (scoped binding)
Check output:
```
$ php artisan tinker --execute="echo app(\App\Support\UiMode::class)->current();"
shop
```

### 3. Navigation as data — done
Changed: `config/shop.php` (new)
Check output:
```
$ php artisan tinker --execute="echo count(config('shop.tiles'));"
7
```

### 4. Share the mode with Blade — done
Changed: `app/Http/Middleware/ShareUiMode.php` (new), `bootstrap/app.php`
Check output:
```
$ php artisan route:list --path=dashboard -vv
  GET|HEAD       dashboard ......................................... dashboard
                 ⇂ Illuminate\Cookie\Middleware\EncryptCookies
                 ⇂ Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse
                 ⇂ Illuminate\Session\Middleware\StartSession
                 ⇂ Illuminate\View\Middleware\ShareErrorsFromSession
                 ⇂ Illuminate\Foundation\Http\Middleware\ValidateCsrfToken
                 ⇂ Illuminate\Auth\Middleware\Authenticate
                 ⇂ Illuminate\Routing\Middleware\SubstituteBindings
                 ⇂ App\Http\Middleware\ShareUiMode
                 ⇂ Illuminate\Auth\Middleware\EnsureEmailIsVerified
```
(`grep -c ShareUiMode` on that command prints `7`, not `1`: `--path=dashboard` matches 7 routes and the middleware is now on all of them. The check's intent — present on `dashboard` — holds.)

### 5. The shop layout — done
Changed: `app/View/Components/ShopLayout.php` (new), `resources/views/layouts/shop.blade.php` (new)
Check output:
```
$ php artisan view:cache
   INFO  Blade templates cached successfully.
$ php artisan view:clear
   INFO  Compiled views cleared successfully.
```

### 6. Shop Blade components — done
Changed: `resources/views/components/shop/icon.blade.php`, `topbar.blade.php`, `tile.blade.php` (all new)
Check output:
```
$ php artisan view:cache   → cached successfully (see step 5)
$ grep -L "shop-" resources/views/components/shop/*.blade.php
(no output)
```

### 7. Home controller, view and route — done
Changed: `app/Http/Controllers/Shop/ShopHomeController.php` (new), `resources/views/shop/home.blade.php` (new), `routes/web.php`
Check output:
```
$ php artisan route:list --name=shop
  GET|HEAD       shop .............. shop.home › Shop\ShopHomeController@index
```

### 8. Dashboard closure becomes a controller — done
Changed: `app/Http/Controllers/DashboardController.php` (new), `routes/web.php`
Check output:
```
$ php artisan route:list --name=dashboard
  GET|HEAD       dashboard ............. dashboard › DashboardController@index
```

### 9. Landing redirects and the mode switch — done
Changed: `app/Http/Controllers/UiModeController.php` (new), `routes/web.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Controllers/Auth/RegisteredUserController.php`
Check output:
```
$ php artisan route:list --name=ui-mode
  POST       ui-mode/{mode} ............... ui-mode.set › UiModeController@set
```
(`--filter=AuthenticationTest` run recorded under Verification.)

### 10. Admin sidebar: Shop mode link, barista KDS link, shell marker — done
Changed: `resources/views/layouts/admin.blade.php` (3 edits, +23/−11)
Check output:
```
$ grep -c 'kds.index' resources/views/layouts/admin.blade.php
1
$ grep -n 'ui-mode.set' resources/views/layouts/admin.blade.php
148:                            <form method="POST" action="{{ route('ui-mode.set', 'shop') }}">
$ grep -n 'data-shell' resources/views/layouts/admin.blade.php
20:    <body class="font-sans antialiased" data-shell="admin">
$ grep -rl "x-admin-layout" resources/views | wc -l
192
```
The moved KDS block plus the Shop mode form sit in a new `<div class="px-2 pb-2">` immediately before `<!-- OPERATIONS SECTION -->`, outside every `@unless(barista)` wrapper, so a barista sees both links.

### 11. Tests: landing, home, mode — done
Changed: `tests/Feature/Shop/LandingRedirectTest.php`, `ShopHomeTest.php`, `UiModeTest.php` (all new)
Check output:
```
$ php artisan test --filter=Shop
  ✓ employee lands on shop
  ✓ barista lands on shop
  ✓ manager lands on dashboard
  ✓ user without a role lands on dashboard
  ✓ shop cookie overrides the role default
  ✓ office cookie overrides the role default
  ✓ redirect query still wins over the landing url
  ✓ employee sees only the tiles they may use
  ✓ barista sees only coffee orders and no office switch
  ✓ manager sees the office switch
  ✓ user with no permissions sees the empty state
  ✓ page is marked as the shop shell
  ✓ deliveries tile shows a badge for active deliveries
  ✓ guest is sent to login
  ✓ there are screens to check
  ✓ screen carries no styling or behaviour with data set #0
  ✓ switching to shop sets the cookie and redirects
  ✓ switching to office redirects to the dashboard
  ✓ an unknown mode is not a route
  ✓ the dashboard is marked as the admin shell
  Tests:    20 passed (49 assertions)
```

### 12. Test: the design/content contract — done
Changed: `tests/Feature/Shop/ShopViewContractTest.php` (new)
Check output — the negative check the plan asked for (temporarily adding `class="shop-page p-4"` to `shop/home.blade.php`, then reverting):
```
$ php artisan test --filter=ShopViewContractTest      # with p-4
  ✓ there are screens to check
  ⨯ screen carries no styling or behaviour with data set #0
  resources/views/shop/home.blade.php uses the utility class "p-4"; shop screens may only use shop-* classes.
  Tests:    1 failed, 1 passed (4 assertions)

$ php artisan test --filter=ShopViewContractTest      # after reverting
  ✓ there are screens to check
  ✓ screen carries no styling or behaviour with data set #0
  Tests:    2 passed (3 assertions)
```

### 13. Format — done
Check output:
```
$ ./vendor/bin/pint --dirty
  PASS   16 files
$ ./vendor/bin/pint --test --dirty
  PASS   16 files
```

## Deviations

1. **Step 11, the login field is `login`, not `email`.** `LoginRequest::rules()` validates `login` (it accepts an email *or* a username). The plan's test sketch implied `email`. All three login posts use `'login' => $user->email`, matching `tests/Feature/Auth/AuthenticationTest.php`.

2. **Step 11, cookie helper: `withCookie`, not `withUnencryptedCookie`.** The plan flagged this as an open question. `withUnencryptedCookie` fails: `ui_mode` is not in `EncryptCookies`' except list, so the middleware cannot decrypt it and discards it — both cookie tests returned the role default. `withCookie` puts the value through `prepareCookiesForRequest`, which encrypts it, and both pass. A comment in the test records why. A manager can only be redirected to `/shop` via the cookie branch of `UiMode`, so the redirect assertion *is* the assertion that `request()->cookie('ui_mode')` was read; no separate assertion was needed.

3. **Step 11, `UiModeTest` needs in-memory POS tables.** `test_the_dashboard_is_marked_as_the_admin_shell` renders `/dashboard`, which calls `ProductRepository::getStatistics()` across the `pos` connection → 500, `no such table: PRODUCTS`. This is pre-existing behaviour of the dashboard (the closure did the same), not something this cycle introduced. Added a small `createPosTables()` helper to that one test class, in the style of `CustomerRequestTest::setUp()`, creating empty `PRODUCTS`, `STOCKCURRENT` and `stocking`. `ShopHomeTest` needs none, as the plan predicted.

4. **Step 12, data providers run before the app boots.** `base_path()` inside `screenProvider()` throws `Call to undefined method Illuminate\Container\Container::basePath()`. Replaced with a private `projectRoot()` returning `dirname(__DIR__, 3)`.

5. **Step 4's check reads `7`, not `1`.** `php artisan route:list --path=dashboard -vv | grep -c ShareUiMode` prints `7` because `--path=dashboard` matches 7 routes and the middleware is correctly on all of them. The intent of the check (present on `dashboard`) is satisfied; the `dashboard` row is pasted under step 4.

6. **Verification 8 (manual browser walkthrough) was done by rendering, not clicking.** No browser extension is connected to this session, and entering a user's password is something I don't do. Instead `/shop` was rendered through the real HTTP kernel as three live dev users (`Auth::login()`, no credentials typed) into temporary files under `public/`, checked, and deleted. What that verified is under Verification below. **The visual check — cream background, tile grid at 2 columns on tablet / 4 on desktop, the chip opening — still needs a human with a browser.**

## Verification

1. `cmp resources/css/shop.css docs/design/shop-mode/shop.css` →
```
SAME
```

2. `npm run build` → completes.
```
public/build/assets/shop--SBCLd7J.css   37.26 kB │ gzip: 6.70 kB
public/build/assets/shop-LCihtINI.js     0.05 kB │ gzip: 0.07 kB
✓ built in 14.89s

$ grep -o '"resources/[a-z/.]*shop[a-z.]*"' public/build/manifest.json | sort -u
"resources/css/shop.css"
"resources/js/shop.js"
```

3. Routes:
```
$ php artisan route:list --name=shop
  GET|HEAD       shop .............. shop.home › Shop\ShopHomeController@index
$ php artisan route:list --name=ui-mode.set
  POST       ui-mode/{mode} ............... ui-mode.set › UiModeController@set
```

4. `php artisan test` → **17 failed, 420 passed (1669 assertions)**, all 17 pre-existing.
```
   FAILED  Tests\Unit\UdeaScrapingServiceTest > (7 tests)     BadMethodCallException / InvalidCountException
   FAILED  Tests\Feature\CashReconciliationTest > (3 tests)   BadMethodCallException
   FAILED  Tests\Feature\FruitVegLabelPrintingTest > (2 tests)
   FAILED  Tests\Feature\ProductTest > (2 tests)
   FAILED  Tests\Feature\TestScraperControllerTest > (1 test)
   FAILED  Tests\Feature\WasteLogTest > (2 tests)
  Tests:    17 failed, 420 passed (1669 assertions)
```
**Proof they are pre-existing, not caused by this work:** I stashed every tracked modification (`AuthenticatedSessionController`, `RegisteredUserController`, `AppServiceProvider`, `bootstrap/app.php`, `admin.blade.php`, `routes/web.php`, `vite.config.js`), cleared caches and reran exactly those classes at `fc5820dc`:
```
$ git stash push -- <the 7 tracked files>
$ php artisan optimize:clear
$ php artisan test --filter="UdeaScrapingServiceTest|CashReconciliationTest|FruitVegLabelPrintingTest|ProductTest|TestScraperControllerTest|WasteLogTest"
  (the same 17 ⨯, none passing)
  Tests:    17 failed, 34 passed (124 assertions)
$ git stash pop
```
The same 17 fail identically with and without this cycle's changes. They are environment failures (`WasteLogTest` runs against `Connection: mysql`; the scraper tests hit Guzzle/Mockery expectations), untouched by this work.

`AuthenticationTest` specifically, which the plan called out:
```
$ php artisan test --filter=AuthenticationTest
  ✓ login screen can be rendered
  ✓ users can authenticate using the login screen
  ✓ login returns user to public page they came from
  ✓ login ignores external redirect targets
  ✓ users can not authenticate with invalid password
  ✓ users can logout
  Tests:    6 passed (13 assertions)
```

5. `grep -rl "x-admin-layout" resources/views | wc -l` →
```
192
```
(unchanged from baseline)

6. Admin layout diff and KDS:
```
$ git diff --stat resources/views/layouts/admin.blade.php
 resources/views/layouts/admin.blade.php | 34 ++++++++++++++++-----------
 1 file changed, 23 insertions(+), 11 deletions(-)
$ git diff resources/views/kds/
(empty)
```

7. `./vendor/bin/pint --test --dirty` →
```
  PASS   16 files
```

8. Manual — rendered, not clicked (see Deviation 6). `/shop` was pushed through the real HTTP kernel as three dev users and the output inspected:

| | employee (katelyn) | barista | manager (test) |
|---|---|---|---|
| status | 200 | 200 | 200 |
| `<body>` | `data-shell="shop"` | same | same |
| tiles | Stock scan, Receive delivery, Print labels, Coffee orders, Customer requests, Vouchers, Fruit & veg | **Coffee orders only** | all 7 (admin-ish perms) |
| user menu | Office, Log out | **Log out only** | Office, Log out |
| greeting | `Good afternoon, katelyn` / `Wednesday 23 September` | same shape | same shape |

Also confirmed in the rendered HTML:
- Asset order is right: `shop-LCihtINI.js` before `app-dclx3xXx.js` (the Alpine ordering risk the plan flagged).
- `<link ...Figtree:wght@500;600;700;800...>` present (not the bunny.net 400–600 link).
- The `pointer: coarse` touch-detect script is inside `<div class="shop" id="shop-root">`.
- Icons resolve to the sprite: `<use href="http://osmanager.local/images/shop-icons.svg#leaf">` etc., 11 distinct symbols, all present in the sprite.
- The deliveries badge rendered `130` from live dev data (`Delivery::active()->count()`).

Both assets serve over HTTP:
```
$ curl -o /dev/null -w "%{http_code} (%{content_type})" http://osmanager.local/images/shop-icons.svg
200 (image/svg+xml)
$ curl -o /dev/null -w "%{http_code}" http://osmanager.local/build/assets/shop--SBCLd7J.css
200
```
The temporary preview files under `public/` were deleted; `git status` below confirms none remain.

**Still needs a human:** the actual look in a browser (cream ground, tile grid 2-up on tablet / 4-up on desktop), opening the user chip, and the Office → Shop mode → Office round trip through the sidebar.

## Files changed

```
 M app/Http/Controllers/Auth/AuthenticatedSessionController.php
 M app/Http/Controllers/Auth/RegisteredUserController.php
 M app/Providers/AppServiceProvider.php
 M bootstrap/app.php
 M resources/views/layouts/admin.blade.php
 M routes/web.php
 M vite.config.js
?? app/Http/Controllers/DashboardController.php
?? app/Http/Controllers/Shop/ShopHomeController.php
?? app/Http/Controllers/UiModeController.php
?? app/Http/Middleware/ShareUiMode.php
?? app/Support/UiMode.php
?? app/View/Components/ShopLayout.php
?? config/shop.php
?? public/images/shop-icons.svg
?? resources/css/shop.css
?? resources/js/shop.js
?? resources/views/components/shop/{icon,topbar,tile}.blade.php
?? resources/views/layouts/shop.blade.php
?? resources/views/shop/home.blade.php
?? tests/Feature/Shop/{LandingRedirectTest,ShopHomeTest,ShopViewContractTest,UiModeTest}.php

  ── pre-existing, NOT mine ──
R  docs/planImp/implemented.md -> docs/planImp/archive/2026-09-23-kds-modifier-badges/implemented.md
RM docs/planImp/plan.md -> docs/planImp/archive/2026-09-23-kds-modifier-badges/plan.md
?? docs/design/
?? docs/planImp/plan.md
```

Nothing committed, pushed or deployed, per Constraints.

## Notes for Planner

1. **`public/build/` is rebuilt.** `npm run build` ran twice, so the built assets on disk are newer than `master`'s. If `public/build` is gitignored this is nothing; if it is tracked, the reviewer will see churn beyond the file list above. (`git status` shows no `public/build` entry, so it is ignored — noted only for completeness.)

2. **The dashboard 500s without POS tables in tests.** Not new — the closure had the same dependency — but it means any future feature test that renders `/dashboard` must stub the `pos` connection. If more than one test class ends up needing `createPosTables()`, it wants lifting into a trait or `Tests\TestCase`. Out of scope here, so I left it local to `UiModeTest`.

3. **`ShopViewContractTest` currently scans exactly one file.** `test_there_are_screens_to_check` guards against a silently vacuous pass, as the plan required, but the contract only starts earning its keep from cycle 2 onward.

4. **`UiMode::current()`'s memoisation and the scoped binding.** `current()` caches per instance and `scoped()` gives one instance per request, so a test that changes the cookie mid-request would see a stale value. Nothing does today. `landingUrl()` deliberately does not read the cache, since it is called at a point where `current()` may already have been resolved for a different user.

5. **Duplicated mode-resolution logic.** `current()` and `landingUrl()` repeat the cookie/PIN/role ladder because one reads `auth()->user()` and the other takes a user argument. I kept them separate rather than inventing an abstraction the plan did not ask for, but if cycle 10's PIN work adds a third caller it is worth collapsing into one private method taking `?User`.

6. **No `Price check` tile**, per Out of scope — `config/shop.php` has 7 tiles, and `screen-01-home.html` shows 8. Worth remembering when cycle 3 lands, so the grid is compared against the design minus that tile.

7. **The labels tile has no badge**, per Out of scope (`getLabelCountsByEventType()` is private). `config/shop.php` has `'badge' => null` for it; cycle 6 just changes that to `'labels'` and adds a branch to `ShopHomeController::badgeCount()`.

8. **`RegisteredUserController` now depends on `UiMode`.** Registration is not gated in this app, and a newly registered user has `role_id = null` → office → `/dashboard`, which is the previous behaviour exactly. Flagging it only because the plan named the file and no test covers registration's landing.
