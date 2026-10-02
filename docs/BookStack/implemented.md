# Procedures (SOPs) cycle 1 — a help button on every screen, read from BookStack — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-30

## Baseline
HEAD: 9f796b28
Pre-existing dirty files:
```
 M docs/features/invoice-parser-integration.md
 M scripts/invoice-parser/parsers/sonett.py
 M scripts/invoice-parser/tests/test_sonett.py
?? docs/BookStack/
?? scripts/invoice-parser/tests/fixtures/sonett/2026-08-24_S26-0824-2606.txt
```

## Steps

### 1. Configuration — done
Changed: `config/services.php` (new `bookstack` entry after `zebra`), `.env.example` (commented `BOOKSTACK_*` block after the `UDEA_*` block), `phpunit.xml` (`<env name="BOOKSTACK_URL" value=""/>`)
Check output:
```
$ php artisan tinker --execute="dump(config('services.bookstack.screen_tag'));"
"screen"
$ grep -n BOOKSTACK phpunit.xml .env.example
phpunit.xml:35:        <env name="BOOKSTACK_URL" value=""/>
.env.example:94:# BOOKSTACK_URL=http://bookstack.internal
.env.example:95:# BOOKSTACK_TOKEN_ID=your_token_id
.env.example:96:# BOOKSTACK_TOKEN_SECRET=your_token_secret
.env.example:97:# BOOKSTACK_TIMEOUT=3
.env.example:98:# BOOKSTACK_SCREEN_TAG=screen
$ grep -c '^BOOKSTACK' .env
0
```

### 2. Add the sanitiser — done
Changed: `composer.json`, `composer.lock`
Check output:
```
$ composer require symfony/html-sanitizer:^7.3
Lock file operations: 1 install, 0 updates, 0 removals
  - Locking symfony/html-sanitizer (v7.4.20)
Package operations: 1 install, 0 updates, 0 removals
$ composer show symfony/html-sanitizer
versions : * v7.4.20
$ git diff --stat composer.json composer.lock
 composer.json |  3 ++-
 composer.lock | 78 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++--
```
The only non-addition lines in the lock diff are `content-hash` and
`plugin-api-version` (2.6.0 → 2.9.0, the local Composer binary's metadata, not a
package). No other package changed version. See Deviations for 7.4 vs 7.3.

### 3. `BookStackClient` — done
Changed: `app/Services/BookStack/BookStackClient.php` (new; did not exist)
`isConfigured()`, `baseUrl()`, `taggedPages()` (paged search, hard stop at 10
pages, per-page `GET /api/pages/{id}` fallback when a hit has no `tags` key,
tag names compared case-insensitively, values trimmed and de-duplicated),
`page()`, `image()` (no `Authorization` header). `connectTimeout(2)`,
`timeout(config)`, `acceptJson()`, `->throw()` on API calls. The token only
appears in the header.
Check output: covered by HelpTest 2, 3 and 12 (see step 10).

### 4. `SopHtml` — done
Changed: `app/Services/BookStack/SopHtml.php` (new)
Sanitiser config as specified; the URL rewrite parses the sanitised output
with `Masterminds\HTML5` (already installed; a dependency of the sanitiser).
The first run of test 7 failed: XPath on a `DOMDocumentFragment` does not match
the fragment's own top-level nodes, so only the `<img>` nested inside an `<a>`
was rewritten. Fixed by moving the fragment into a container `<div>` and
serialising the container's children.
Check output: HelpTest 6 and 7 pass (see step 10).

### 5. `ScreenHelp` — done
Changed: `app/Services/BookStack/ScreenHelp.php` (new), `app/Providers/AppServiceProvider.php` (`scoped(ScreenHelp::class)` next to `UiMode`)
As specified. One detail: `Cache::forever(last-good)` is written inside the
`Cache::remember` closure, so only when the index is freshly built. That way a
warm cache costs one cache read per request, not a read plus a write.
Check output: HelpTest 2, 10, 10b and 1 pass.

### 6. Routes, controller, allow-list — done
Changed: `routes/web.php` (`use HelpController`; the `help.` group right after `ui-mode.set`), `app/Http/Controllers/HelpController.php` (new), `config/shop.php` (`'help.*'` with comment)
Check output:
```
$ php artisan route:list --name=help -v
  GET|HEAD   help/image/{path} ............. help.image › HelpController@image
             ⇂ web
             ⇂ Illuminate\Auth\Middleware\Authenticate
  GET|HEAD   help/page/{id} .................. help.page › HelpController@page
             ⇂ web
             ⇂ Illuminate\Auth\Middleware\Authenticate
  POST       help/refresh .............. help.refresh › HelpController@refresh
             ⇂ web
             ⇂ Illuminate\Auth\Middleware\Authenticate
             ⇂ App\Http\Middleware\RoleMiddleware:manager,admin
  GET|HEAD   help/{screen} ................... help.show › HelpController@show
             ⇂ web
             ⇂ Illuminate\Auth\Middleware\Authenticate
                                                            Showing [4] routes
```
Guest redirect: HelpTest 17 (all three GET routes redirect to login, nothing sent).

### 7. Shop reader screen — done
Changed: `resources/views/shop/help.blade.php` (new), `resources/css/shop.css` (`.shop-sop` rules between the APP ADDITIONS markers)
Check output:
```
$ php artisan test --filter='ShopViewContractTest|ConfinePinSessionTest'
  Tests:    33 passed (325 assertions)
$ head -c … resources/css/shop.css | cmp - <(… git show HEAD:resources/css/shop.css); echo "cmp exit $?"
cmp exit 0
```
(No output from `cmp`; the design block is unchanged.)

### 8. Office reader page — done
Changed: `resources/views/help/show.blade.php` (new), `resources/css/app.css` (`.sop-body` rules, Tailwind `@apply`)
Check output: HelpTest 15 "office mode uses the admin layout" passes.

### 9. The buttons and the sidebar link — done
Changed: `public/images/shop-icons.svg` (`help` symbol appended after `info`), `resources/views/components/shop/help-button.blade.php` (new), `resources/views/components/shop/topbar.blade.php` (first thing inside `@auth`), `resources/views/components/help-button.blade.php` (new), `resources/views/layouts/admin.blade.php` (`<x-help-button />` between `</nav>` and `<!-- User menu -->`; "Procedures (BookStack)" link after "Shop devices")
Check output:
```
$ php artisan test --filter=ConfinePinSessionTest   → passes (in the 33 above)
$ php artisan test --filter=Shop                    (this tree)
  Tests:    280 passed (1355 assertions)
$ php artisan test --filter=Shop                    (clean HEAD in a temporary git worktree, since removed)
  Tests:    279 passed (1353 assertions)
```
The +1 is `ShopViewContractTest` scanning the new `shop/help.blade.php`.
HelpTest does not match `--filter=Shop`.

### 10. Tests — done
Changed: `tests/Feature/Help/HelpTest.php` (new; the directory did not exist)
One test per plan line, except line 4 and line 10, which are two tests each:
19 tests.
Check output:
```
$ php artisan test --filter=HelpTest
   PASS  Tests\Feature\Help\HelpTest
  ✓ unconfigured feature sends nothing and shows no button
  ✓ the index is built from tagged search results
  ✓ search results without tags fall back to reading each page
  ✓ an employee sees the button on a screen with a procedure
  ✓ an employee sees no button on a screen without a procedure
  ✓ a manager sees the button everywhere and the tag to add
  ✓ a single tagged page renders its sanitised html
  ✓ images and links are rewritten and long pages are not truncated
  ✓ a page that is not in the index is not found
  ✓ two pages for one screen are listed
  ✓ bookstack failing never breaks a page
  ✓ a failing refetch serves the last good index
  ✓ a page that fails to load says so and is not cached
  ✓ the image proxy passes images only
  ✓ a pin session reaches the reader
  ✓ refresh is for managers and refetches the index
  ✓ office mode uses the admin layout
  ✓ an off site back link is ignored
  ✓ guests are sent to login
  Tests:    19 passed (110 assertions)
```
Notes on the tests:
- `ScreenHelp` is `scoped`, and the test app does not reset scoped instances
  between `$this->get()` calls, so tests that make a second request after
  changing the fakes call `app()->forgetScopedInstances()` first (as a new
  request would in production).
- Test 16: my first version asserted the page did not contain `evil.test` at
  all, and failed. The only occurrence is the existing stale-session script in
  `layouts/shop.blade.php:76`, which puts the current request URI into
  `route('login', ['redirect' => …])`. The back link itself was correct. The
  assertion now checks that no `href` is the off-site value and that the Back
  button points at `shop.home`. `/\evil.test` is also covered.

### 11. Documentation — done
Changed: `docs/features/sops-bookstack.md` (new; did not exist), `docs/FEATURES_INDEX.md` (new "Staff Procedures" section + nav link, before User Management), `CLAUDE.md` (a "Staff Procedures" item under Features Overview; a line under "Where to Find Information"), `docs/features/shop-mode.md` (help button bullet, icon list, `help.*` in the Confinement paragraph), `docs/design/shop-mode/README.md` (sprite ids: `info`, `help` added to the app additions)
Check output:
```
$ grep -n "sops-bookstack" docs/FEATURES_INDEX.md CLAUDE.md
CLAUDE.md:260:- **Staff Procedures**: [Staff Procedures (SOPs) from BookStack](./docs/features/sops-bookstack.md) - …
docs/FEATURES_INDEX.md:964:📖 [Staff Procedures Documentation](./features/sops-bookstack.md)
```

### 12. Format and build — done
Check output:
```
$ ./vendor/bin/pint --dirty      → PASS 9 files
$ ./vendor/bin/pint --dirty      → PASS 9 files (second run, nothing to fix)
$ npm run build                  → ✓ built in 10.37s
  (the only warning is the old >500 kB chunk warning for zpl-preview)
$ grep -c sop-body public/build/assets/app-D3sFkqPL.css   → 1
$ grep -c shop-sop public/build/assets/shop-DxLf57p6.css  → 1
```

## Deviations

1. **Sanitiser version 7.4.20, not 7.3.x.** `composer require symfony/html-sanitizer:^7.3`
   resolved to v7.4.20, which satisfies `^7.3`; the constraint in `composer.json`
   is `^7.3` as planned. It installed with 0 updates and 0 removals, so no other
   package moved. I did not pin to 7.3 because the plan's command was the
   constraint and nothing else changed.
2. **`Cache::forever(last-good)` only on a fresh build** (inside the
   `remember` closure) rather than on every successful `index()` call. Same
   behaviour, but a warm cache is one read per request instead of a read plus a
   write (production cache store is the database).
3. **`help.show` with one page passes `screen` to the view**; `help.page`
   passes `screen = null`. The rendered output is otherwise identical. Nothing
   in either reader shows `screen` in the page state.
4. **Test 16 assertion narrowed** (see step 10 notes): the page does contain
   `evil.test`, but only in the existing stale-session script of
   `layouts/shop.blade.php`, not in the back link.
5. **Verification 6 was done server-side, not in the browser.** Chrome was not
   signed in to `osmanager.local`, and I may not type a password into it. I
   rendered the pages through the HTTP kernel as real users from the dev
   database instead (see Verification 6).
6. `.env.example` placeholder values for token id/secret are `your_token_id` /
   `your_token_secret`, following the `UDEA_*` block's `your_username` style.

## Verification

1. `php artisan test --filter=HelpTest` → **19 passed (110 assertions)**.
2. `php artisan test --filter=Shop` → **280 passed (1355 assertions)**, no failures
   (clean HEAD in a temporary worktree: 279 passed; +1 is the contract scan of
   `shop/help.blade.php`).
3. `php artisan test` → **15 failed, 921 passed (4080 assertions)**. The 15 are
   exactly the baseline: UdeaScrapingServiceTest ×7, CashReconciliationTest ×3,
   FruitVegLabelPrintingTest ×2, ProductTest ×2, TestScraperControllerTest ×1.
   921 = 901 + 19 (HelpTest) + 1 (new contract dataset).
4. `php artisan route:list --name=help` → four routes (`help.image`, `help.page`,
   `help.refresh` with `RoleMiddleware:manager,admin`, `help.show`), all `auth`.
5. Design block check → `cmp` prints nothing (exit 0).
6. `BOOKSTACK_URL` is absent from the dev `.env` (`grep -c '^BOOKSTACK' .env` → 0).
   - Live dev site: `curl http://osmanager.local/help/shop.home` as a guest →
     `302 http://osmanager.local/login`.
   - Browser: Chrome was not signed in (redirected to `/login`); I did not sign
     in. Server-side through the HTTP kernel, real users from the dev DB:
     ```
     manager /dashboard -> 200 shell=admin help=0 procedures=0
     employee /shop -> 200 shell=shop help=0 procedures=0
     manager /shop -> 200 shell=shop help=0 procedures=0
     log lines before=959475 after=959475
     ```
     (`help` = count of `/help/` in the HTML, `procedures` = count of
     "Procedures (BookStack)".) No new lines in `storage/logs/laravel.log`.
     **A manual look in a signed-in browser is still owed.**
7. Live check: **not run: no token in dev .env** (`grep -c '^BOOKSTACK_TOKEN_ID=.' .env` → 0).

## Files changed

`git status --short` at the end (pre-existing dirty files marked *):
```
 M .env.example
 M CLAUDE.md
 M app/Providers/AppServiceProvider.php
 M composer.json
 M composer.lock
 M config/services.php
 M config/shop.php
 M docs/FEATURES_INDEX.md
 M docs/design/shop-mode/README.md
 M docs/features/invoice-parser-integration.md          *
 M docs/features/shop-mode.md
 M phpunit.xml
 M public/images/shop-icons.svg
 M resources/css/app.css
 M resources/css/shop.css
 M resources/views/components/shop/topbar.blade.php
 M resources/views/layouts/admin.blade.php
 M routes/web.php
 M scripts/invoice-parser/parsers/sonett.py              *
 M scripts/invoice-parser/tests/test_sonett.py           *
?? app/Http/Controllers/HelpController.php
?? app/Services/BookStack/          (BookStackClient, SopHtml, ScreenHelp)
?? docs/BookStack/                  * (this track's folder; implemented.md is mine)
?? docs/features/sops-bookstack.md
?? resources/views/components/help-button.blade.php
?? resources/views/components/shop/help-button.blade.php
?? resources/views/help/            (show.blade.php)
?? resources/views/shop/help.blade.php
?? scripts/invoice-parser/tests/fixtures/sonett/2026-08-24_S26-0824-2606.txt  *
?? tests/Feature/Help/              (HelpTest.php)
```
`public/build/` was rebuilt by `npm run build`; it is not tracked. Nothing committed.

## Notes for Planner

1. **An outage costs every page a connect timeout.** When BookStack is down,
   `index()` catches the failure and serves last-good, but caches nothing new,
   so *every* request (every page view, because the buttons call `current()`)
   retries the search and waits up to the 2 s connect timeout (3 s if BookStack
   accepts the connection and hangs). The page still renders, as the plan
   requires, but slowly for as long as the outage lasts. Suggested follow-up:
   after a failure, also `Cache::put(INDEX_KEY, lastGoodOrEmpty, 60)` (a short
   negative cache) so there is at most one slow request a minute. I did not do
   it because the plan specifies "return the last-good value" and "do not cache
   a failure" (the latter is written for pages, but it seemed close enough to
   leave the decision to you).
2. **Stale-session script echoes the request URI.** `layouts/shop.blade.php:76`
   puts `request()->getRequestUri()` into `route('login', ['redirect' => …])`
   inside a JS string. It is escaped by `{{ }}` and pre-existing, and the login
   redirect is presumably validated elsewhere; I only noticed it because test 16
   tripped on it. Not touched.
3. **Scoped instances in tests.** `ScreenHelp` memoises per request via
   `scoped`, but Laravel's test client does not reset scoped instances between
   `$this->get()` calls in one test. Tests that need a second request to re-read
   the cache call `app()->forgetScopedInstances()`. In production each request
   gets a fresh instance, so behaviour is as planned. Worth knowing for the next
   cycle's tests.
4. **Tag name in the empty state** is read from `config('services.bookstack.screen_tag')`
   rather than hard-coded `screen`, so a changed `BOOKSTACK_SCREEN_TAG` shows the
   right instruction.
5. **The live check and the signed-in browser check (Verification 6 by eye, 7)
   are still owed.** They need a signed-in browser session and, for 7, an API
   token in the dev `.env`. The API response shapes are still unverified against
   this BookStack instance.
6. `composer.lock` `plugin-api-version` moved 2.6.0 → 2.9.0 because of the local
   Composer binary's version; harmless, but it will show in the diff.
