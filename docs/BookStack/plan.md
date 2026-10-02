# Procedures (SOPs) cycle 1 — a help button on every screen, read from BookStack

Status: READY
Revision: 2
Planner: Fable 5.1
Date: 2026-09-30

> **Revision 2 (2026-09-30).** Revision 1 is implemented and reviewed; its
> steps 1–12 stand and are not to be redone. The Implementer does only the
> three steps under "Revision 2 steps" in the Review section at the end.

## Goal

Staff get a "How to do this" button on the screen they are using. It opens the
written procedure (SOP) for that screen inside our own site, in Shop mode on
the tablets and phones and in the office layout on the PCs. The procedures
themselves are written and edited in BookStack by the owner and the managers;
our app only reads them. Managers also get a sidebar link to BookStack.

A manager links a procedure to a screen without a developer: in BookStack they
add a tag named `screen` to the page, with the screen's route name as the value
(for example `shop.deliveries.scan`). The help button tells them which value to
use.

## Context

### Decisions already made by the owner (2026-09-30)

- BookStack is the place SOPs are written. We do not build our own editor or
  hand-written HTML pages.
- Readers are on shared Shop tablets (PIN sign-in), office PCs and their own
  phones. Authors are the owner plus managers. BookStack is mostly empty today.
- Wanted: help on specific screens, and a menu link to BookStack.

### Why the SOP is shown inside our site rather than linked

- A PIN user has no BookStack account; BookStack redirects guests to `/login`
  (checked today: public access is off).
- The idle lock only runs on Shop pages (`resources/js/shop/idle-lock.js`, wired
  in `resources/views/layouts/shop.blade.php`). A tablet that navigates to
  another site never locks.
- BookStack's own address is `http://bookstack.internal` (plain HTTP). The dev
  box resolves that name through `/etc/hosts`; shop tablets and phones may not
  resolve it at all. Our app is HTTPS, so images loaded straight from BookStack
  would also be blocked as mixed content. Hence the image proxy in step 6.

### BookStack facts (checked read-only from the dev box today)

- Version **v25.05.1**. `http://lilthink2/` (port 80) redirects to
  `http://bookstack.internal/login`. It runs on the production machine; there
  is no BookStack on the dev box, but the dev box can reach it over the LAN.
- The API needs a token: `GET http://bookstack.internal/api/search` without one
  returns `401 {"error":{"message":"No authorization token found on the request","code":401}}`.
  Auth header: `Authorization: Token <token_id>:<token_secret>`.
- I could not read `/api/docs.json` (401 without a token), so the shapes below
  are from BookStack's published API documentation for v25, not from this
  instance. Step 3 makes the client tolerate the one variation that matters.
  - `GET /api/search?query=…&page=N&count=100` →
    `{"data":[{"id":12,"name":"Receiving a delivery","type":"page","url":"http://bookstack.internal/books/deliveries/page/receiving-a-delivery","tags":[{"name":"screen","value":"shop.deliveries.scan","order":0}], …}],"total":1}`.
    Search syntax: `[screen]` matches entities carrying a tag named `screen`;
    `{type:page}` restricts to pages.
  - `GET /api/pages/{id}` → `{"id":12,"name":"…","html":"<p>…</p>","updated_at":"2026-09-30T10:00:00.000000Z","tags":[…], …}`.
    `html` is the rendered page content.
  - Images in page HTML are absolute URLs on BookStack's own host, path
    `/uploads/images/<type>/<yyyy-mm>/<file>`. BookStack usually wraps a scaled
    image in a link to the full-size file, so both `img[src]` and `a[href]` can
    point under `/uploads/images/`.
- The owner has not yet created an API token. Everything in this plan is built
  and tested with `Http::fake()`. The live check in Verification runs only if
  the owner has put a token in the dev `.env`.

### Code facts

- There is no help, SOP, manual or wiki feature in the app, no markdown
  rendering of user content and no rich-text editor. Nothing in the repo
  mentions BookStack.
- No HTML sanitiser is installed. `symfony/http-foundation` is 7.3.1, PHP 8.3,
  `ext-dom` present, `masterminds/html5` 2.10 and `league/uri` 7.5 are already
  in the lock file, so `symfony/html-sanitizer` ^7.3 installs cleanly.
- Third-party service config lives in `config/services.php` (see `udea`,
  `zebra`). HTTP calls use the `Http` facade with a timeout
  (`app/Services/SupplierService.php:333`).
- `App\Support\UiMode` (scoped in `app/Providers/AppServiceProvider.php`)
  decides Shop or office for a request: `isShop()`, `homeRoute()`. A request to
  a `help.*` route is Shop when the session is a PIN session, else by the
  `ui_mode` cookie, else by role (employee and barista → Shop).
- Shop shell: `<x-shop-layout title="…" :back="…">` (`app/View/Components/ShopLayout.php`,
  `resources/views/layouts/shop.blade.php`). Screens live in
  `resources/views/shop/` and render one `<main class="shop-page">`. The layout
  already turns `session('success')` / `session('error')` into a toast. Useful
  existing classes: `shop-page`, `shop-page--narrow`, `shop-card`, `shop-list`,
  `shop-row`, `shop-row__main`, `shop-row__title`, `shop-row__chev`, `shop-empty`,
  `shop-empty__icon`, `shop-empty__title`, `shop-empty__text`, `shop-notice`,
  `shop-meta`, `shop-code`, `shop-btn`, `shop-btn--secondary`, `shop-iconbtn`.
- Shop view contract (`tests/Feature/Shop/ShopViewContractTest.php`): a file
  under `resources/views/shop/` has no `<style>`, no `<script>` and only
  `shop-*` classes. Components under `resources/views/components/shop/` are not
  scanned, but keep to `shop-*` classes there too.
- `resources/css/shop.css`: the block above `/* === APP ADDITIONS START ===`
  (line 555) is a byte-identical copy of the design file and must not change.
  App styles go between that marker and `/* === APP ADDITIONS END === */`.
- PIN confinement: `App\Http\Middleware\ConfinePinSession` lets a PIN session
  reach only route names matching `config('shop.pin_session_routes')`
  (`config/shop.php:58`). `ConfinePinSessionTest::test_every_route_a_shop_view_names_is_on_the_allow_list`
  greps `resources/views/shop`, `resources/views/components/shop`,
  `layouts/shop.blade.php` and `resources/js/shop` for `route('…')` and fails
  if a name is not on the list.
- Shop topbar: `resources/views/components/shop/topbar.blade.php`. The title or
  brand block is `flex: 1`, so anything placed after it sits on the right, next
  to the user chip (`<details class="shop-usermenu">`, inside `@auth`).
- Shop icons: `<x-shop.icon name="…" />` reads `public/images/shop-icons.svg`.
  App-added symbols (`more`, `phone`, `info`) sit at the end of the sprite.
  There is no question-mark symbol yet.
- Office layout: `resources/views/layouts/admin.blade.php` (one sidebar, used
  by about 195 views through `<x-admin-layout>`). `</nav>` is at line 805 and
  the `<!-- User menu -->` block starts at line 807. The Administration section
  has the "Shop devices" link at lines 773–781, guarded by
  `isManager() || isAdmin()`. Office views open with
  `<x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">…`
  (see `resources/views/shop-devices/index.blade.php`).
- The office top bar (line 876) is a `justify-between` flex row and 140 views
  put differently shaped content in its `header` slot. Do not add anything to
  that row; it would shift those headers. The office help link goes in the
  sidebar instead (step 9).
- `User::isManager()`, `isAdmin()`, `isEmployee()` come from
  `app/Traits/HasPermissions.php`. Test helpers to copy: `userWith()` in
  `tests/Feature/Shop/ShopStockScanTest.php:27` and `pinUser()` in
  `tests/Feature/Shop/ConfinePinSessionTest.php:27` (a real PIN sign-in).
- Tests run with `CACHE_STORE=array` (`phpunit.xml`). There is no
  `.env.testing`, so tests read the dev `.env` for anything `phpunit.xml` does
  not override. That is why step 1 blanks `BOOKSTACK_URL` there.
- Suite baseline taken today: **15 failed, 901 passed** (CashReconciliation ×3,
  FruitVegLabelPrinting ×2, Product ×2, TestScraperController ×1,
  UdeaScrapingService ×7). These are old failures and not part of this work.
- The working tree already has unrelated changes from another track
  (`docs/features/invoice-parser-integration.md`, `scripts/invoice-parser/…`).
  Leave them alone.

## Constraints

- **Read-only towards BookStack.** The app sends only `GET` requests to it.
  Nothing in this cycle creates, edits or tags BookStack content, and neither
  session changes BookStack settings or the production server.
- **Only tagged pages are readable.** The reader serves a BookStack page only
  if its id is in the screen index (it carries a `screen` tag). An arbitrary
  page id must give 404. BookStack may hold pages that are not for shop-floor
  staff, and the API token may be able to see them.
- **BookStack being down, slow or unconfigured never breaks a page.** Every
  call has a timeout and is wrapped; a layout still renders, just without the
  button. With `BOOKSTACK_URL` blank the feature is off and makes no HTTP call.
- **The token never reaches the browser or the log.** Do not put it in a view,
  a URL, an exception message or a `Log::` context.
- **BookStack HTML is sanitised before it is cached or rendered.** It is shown
  inside our origin, where the staff session lives.
- **PIN confinement stays intact.** Help routes are allow-listed by name;
  nothing else is added to the list.
- **Shop view contract** holds for the new Shop screen. The design block of
  `resources/css/shop.css` stays byte-identical.
- No new permission, no migration, no new database table. Any signed-in user
  may read a tagged procedure.
- No JavaScript is needed for this cycle. Do not add any.
- Tests must never touch the network: call `Http::preventStrayRequests()` in
  the new test class.
- Do not commit, push or deploy. Do not edit `.env` (if a token is there, the
  owner put it there; never print it).

## Out of scope

- A "Procedures" tile on Shop Home, or any browse / search of all SOPs in our
  site. That is the next cycle and will reuse this reader.
- Making links between BookStack pages open in our reader. In this cycle they
  open BookStack in a new tab (see step 4).
- Writing to BookStack, single sign-on, syncing users or roles, webhooks.
- Showing different SOPs to different roles.
- A scheduled job. The cache in step 5 is enough; do not touch
  `routes/console.php`.
- The office top bar, the legacy `resources/views/layouts/navigation.blade.php`
  and `<x-app-layout>` views, guest pages, the KDS.
- `docs/design/shop-mode/shop.css` and `docs/design/shop-mode/shop-icons.svg`
  (design copies).
- The 15 baseline failures and the other track's uncommitted files.

## Steps

### 1. Configuration

Files: `config/services.php`, `.env.example`, `phpunit.xml`

What: add a `bookstack` entry to `config/services.php`:

```php
'bookstack' => [
    // Blank switches the whole procedures feature off (no button, no HTTP).
    'url' => env('BOOKSTACK_URL'),
    'token_id' => env('BOOKSTACK_TOKEN_ID'),
    'token_secret' => env('BOOKSTACK_TOKEN_SECRET'),
    'timeout' => env('BOOKSTACK_TIMEOUT', 3),
    // Name of the BookStack page tag whose value is a screen's route name.
    'screen_tag' => env('BOOKSTACK_SCREEN_TAG', 'screen'),
],
```

Add the five keys to `.env.example` as commented lines in the style of the
`UDEA_*` block (line 86), with `# BOOKSTACK_URL=http://bookstack.internal`.
Add `<env name="BOOKSTACK_URL" value=""/>` to `phpunit.xml` so a token in the
dev `.env` can never make the suite call the real BookStack.

Check: `php artisan tinker --execute="dump(config('services.bookstack.screen_tag'));"`
prints `"screen"`. `grep -n BOOKSTACK phpunit.xml .env.example` shows the new lines.

### 2. Add the sanitiser

Files: `composer.json`, `composer.lock`

What: `composer require symfony/html-sanitizer:^7.3`. No other package may
change version; if Composer wants to update anything else, stop and set
`BLOCKED`.

Check: `composer show symfony/html-sanitizer` lists a 7.3.x version;
`git diff --stat composer.lock` shows only the additions for that package.

### 3. `BookStackClient`: the only class that talks HTTP to BookStack

Files: `app/Services/BookStack/BookStackClient.php (new)`

What: a small class reading `config('services.bookstack')`.

- `isConfigured(): bool` — true only when `url`, `token_id` and `token_secret`
  are all non-empty.
- `baseUrl(): string` — `url` without a trailing slash.
- `taggedPages(): array` — calls `GET {url}/api/search` with
  `query` = `[<screen_tag>] {type:page}`, `count` = 100, `page` = 1, 2, … until
  a response has fewer than 100 results (hard stop after 10 pages). Returns a
  flat list of `['id' => int, 'title' => string, 'url' => string, 'screens' => string[]]`,
  where `screens` are the trimmed, non-empty values of the hit's tags whose
  name equals `screen_tag` (compare names case-insensitively). Skip hits whose
  `type` is not `page` and hits with no such value.
  **If a hit has no `tags` key at all**, read that one page with
  `GET /api/pages/{id}` and take `tags` from there. This covers an instance
  whose search results do not carry tags.
- `page(int $id): array` — `GET {url}/api/pages/{id}`; returns
  `['id', 'title' (from `name`), 'html', 'updated_at']`.
- `image(string $path): ?array` — `GET {url}/{path}` with no `Authorization`
  header; returns `['body' => string, 'type' => string]` or null when the
  response is not successful.
- All requests: `Http::connectTimeout(2)->timeout(config timeout)`, header
  `Authorization: Token {id}:{secret}` on the two `/api/` calls,
  `Accept: application/json`. A failed or non-2xx API response throws (use
  `->throw()`); callers catch. Never log the token.

Check: covered by the tests in step 10 (`Http::assertSent` on the header and
the query string; the no-`tags` variation).

### 4. `SopHtml`: make BookStack HTML safe and self-contained

Files: `app/Services/BookStack/SopHtml.php (new)`

What: one public method `clean(string $html): string` that

1. sanitises with `symfony/html-sanitizer`:
   `allowSafeElements()`, `allowAttribute('class', '*')` (BookStack callouts
   are `<p class="callout info">`), link schemes `http`, `https`, `mailto`,
   `tel`, media schemes `http`, `https`, relative links and media allowed,
   `forceAttribute('a', 'target', '_blank')`,
   `forceAttribute('a', 'rel', 'noopener noreferrer')`, and
   `withMaxInputLength(1_000_000)` (the default of 20,000 silently truncates a
   long procedure). `id` attributes are deliberately not allowed, so in-page
   anchor links will not jump; that is accepted for this cycle.
2. then rewrites URLs:
   - `img[src]` and `a[href]` whose path starts with `/uploads/images/` and
     whose host is empty or equals the host of `baseUrl()` become
     `route('help.image', ['path' => <path without the leading slash>], absolute: false)`.
     Relative, because the HTML is cached and the app is reached by more than
     one host name.
   - any other root-relative `a[href]` or `img[src]` (starts with `/`) gets
     `baseUrl()` put in front, so it does not resolve against our app.
   - everything else is left as the sanitiser returned it.

Check: step 10 tests: `<script>` and `onclick` are gone, `class="callout info"`
survives, an image at `http://bookstack.internal/uploads/images/gallery/2026-09/a.png`
comes out as `/help/image/uploads/images/gallery/2026-09/a.png`, a link to
`/books/x/page/y` comes out as `http://bookstack.internal/books/x/page/y`, and
a 30,000-character page is not truncated.

### 5. `ScreenHelp`: the index, the cache and "what help does this screen have"

Files: `app/Services/BookStack/ScreenHelp.php (new)`, `app/Providers/AppServiceProvider.php`

What: register `ScreenHelp` as `scoped` in `AppServiceProvider::register()`
next to `UiMode`. Methods:

- `index(): array` — `['screens' => [routeName => [['id','title','url'], …]], 'ids' => [id => true]]`,
  each screen's pages sorted by title. Returns an empty index with **no HTTP
  call** when the client is not configured. Otherwise
  `Cache::remember('bookstack.screen-index', 300, …)` around
  `BookStackClient::taggedPages()`. On success also
  `Cache::forever('bookstack.screen-index.last-good', $index)`. On any
  exception: `Log::warning` (message and exception class only) and return the
  last-good value, or the empty index if there is none. Memoise the result on
  the instance so one request reads the cache once.
- `pagesFor(string $screen): array`.
- `knows(int $id): bool`.
- `page(int $id): ?array` — null when `knows($id)` is false. Otherwise
  `Cache::remember("bookstack.page.$id", 600, …)` of the client's page with
  `html` passed through `SopHtml::clean()`, plus `url` from the index entry.
  Throws on a failed fetch with nothing cached (do not cache a failure).
- `current(): ?array` — for the help buttons. Null when: not configured; no
  signed-in user; the current route has no name; the name starts with `help.`.
  Otherwise `['screen' => name, 'pages' => pagesFor(name)]`, but null when
  there are no pages and the user is neither manager nor admin.
- `refresh(): void` — forget the index key and every `bookstack.page.{id}` for
  the ids in the current and last-good index. Keep the last-good key.

Check: step 10 tests (index built, last-good survives a failed refetch,
unconfigured sends nothing).

### 6. Routes, controller, allow-list

Files: `routes/web.php`, `app/Http/Controllers/HelpController.php (new)`, `config/shop.php`

What: inside the existing `Route::middleware('auth')->group(…)`, directly after
the `ui-mode.set` route (line 140), add:

```php
// Procedures (SOPs) read from BookStack. See docs/features/sops-bookstack.md.
Route::prefix('help')->name('help.')->group(function () {
    Route::get('/image/{path}', [HelpController::class, 'image'])->where('path', 'uploads/images/.+')->name('image');
    Route::get('/page/{id}', [HelpController::class, 'page'])->whereNumber('id')->name('page');
    Route::post('/refresh', [HelpController::class, 'refresh'])->middleware('role:manager,admin')->name('refresh');
    Route::get('/{screen}', [HelpController::class, 'show'])->where('screen', '[A-Za-z0-9._-]+')->name('show');
});
```

Controller actions (thin; the work is in `ScreenHelp`):

- `show($screen)`: 404 when not configured. No pages → the reader view in its
  empty state. One page → same output as `page()` for it. Several → the reader
  view in its list state, each title linking to `help.page` with
  `back` = the current request URI.
- `page($id)`: 404 when not configured or `ScreenHelp::page()` returns null. If
  the fetch throws, render the reader view in its "could not load" state
  (status 200, message "This procedure could not be loaded just now. Try again
  in a minute.").
- `image($path)`: 404 when not configured, when any path segment is `..`, when
  the client returns null, or when the returned content type (ignoring any
  `; charset` part) is not one of `image/png`, `image/jpeg`, `image/gif`,
  `image/webp`. Otherwise the body with that `Content-Type`,
  `Cache-Control: private, max-age=604800` and `X-Content-Type-Options: nosniff`.
  SVG is refused on purpose: opened directly it would run script in our origin.
- `refresh()`: `ScreenHelp::refresh()`, then `back()->with('success', 'Procedures refreshed.')`.
- The back link for every reader state: the `back` query parameter if it is a
  local path (starts with one `/`, second character is not `/` or `\`),
  otherwise `route(app(UiMode::class)->homeRoute())`.
- Each action picks the view by mode: `app(UiMode::class)->isShop()` →
  `shop.help`, else `help.show`. Both get the same data: `state`
  (`page` | `list` | `empty` | `unavailable`), `screen`, `page`, `pages`, `back`,
  `bookstackUrl`, and `canManage` (`isManager() || isAdmin()`).

In `config/shop.php` add `'help.*'` to `pin_session_routes` with a comment:
the reader, its images and the refresh form are named by Shop views;
`help.refresh` keeps its own `role:manager,admin` gate and managers have no PIN.

Check: `php artisan route:list --name=help` lists the four routes with `auth`
(and `role:manager,admin` on refresh). `curl -s -o /dev/null -w '%{http_code}' http://osmanager.local/help/shop.home`
as a guest gives 302 to login (or use the step 10 guest test).

### 7. Shop reader screen

Files: `resources/views/shop/help.blade.php (new)`, `resources/css/shop.css`

What: `<x-shop-layout :title="…" :back="$back">` with one
`<main class="shop-page shop-page--narrow">`. Title is the page title, or
"How to" for the list, empty and unavailable states.

- `page`: `<article class="shop-sop">{!! $page['html'] !!}</article>`, then a
  `shop-meta` line "Updated <date>" (format `j M Y`). If `$canManage`, a
  `shop-btn shop-btn--secondary` link "Edit in BookStack" to `$page['url']`
  (`target="_blank" rel="noopener"`).
- `list`: a `shop-list` of `shop-row` links, one per page title.
- `empty`: a `shop-empty` block. Everyone sees "There is no written procedure
  for this screen yet." If `$canManage`, also: "To add one, open the page in
  BookStack and add a tag named `screen` with the value" followed by the route
  name in a `shop-code` span, then a plain `<form method="POST">` to
  `help.refresh` with `@csrf` and a "Refresh" button, and an "Open BookStack"
  link.
- `unavailable`: a `shop-notice` with the message from step 6.

In `resources/css/shop.css`, between the APP ADDITIONS markers, add `.shop-sop`
rules using existing `--shop-*` tokens only: spacing and sizes for `h1`–`h4`,
`p`, `ul`, `ol`, `li`, `blockquote`, `hr`, `pre`, `code`; `img` and `video`
`max-width: 100%; height: auto`; `table` `display: block; overflow-x: auto`
with bordered cells; `a` underlined in the accent colour; `.callout` as a
padded box with a left border, and `.callout.info`, `.success`, `.warning`,
`.danger` variants.

Check: `php artisan test --filter=ShopViewContractTest` passes (it now scans
`shop/help.blade.php`). `head -c $(grep -b -o 'APP ADDITIONS START' resources/css/shop.css | cut -d: -f1) resources/css/shop.css | cmp - <(head -c $(grep -b -o 'APP ADDITIONS START' resources/css/shop.css | cut -d: -f1) <(git show HEAD:resources/css/shop.css))`
prints nothing (design block unchanged).

### 8. Office reader page

Files: `resources/views/help/show.blade.php (new)`, `resources/css/app.css`

What: `<x-admin-layout>` with the usual `header` slot (`h2`, same classes as
`resources/views/shop-devices/index.blade.php`) and the standard
`py-12 / max-w-4xl / bg-white shadow-sm sm:rounded-lg / p-6` card. Same four
states and the same wording as step 7, in Tailwind. A "Back" link to `$back`
above the card. The body is `<div class="sop-body">{!! $page['html'] !!}</div>`.
Add `.sop-body` rules to `resources/css/app.css` covering the same elements as
`.shop-sop` (the Tailwind typography plugin is not installed; do not add it).
Use `<x-alert type="success" :message="session('success')" />` for the refresh
flash, as other office views do.

Check: step 10 test "office mode uses the admin layout".

### 9. The buttons and the sidebar link

Files: `resources/views/components/shop/help-button.blade.php (new)`,
`resources/views/components/shop/topbar.blade.php`,
`resources/views/components/help-button.blade.php (new)`,
`resources/views/layouts/admin.blade.php`, `public/images/shop-icons.svg`

What:

- Append a `help` symbol to the end of `public/images/shop-icons.svg`, in the
  same form as the existing `info` symbol (Lucide "circle-help"):
  `<symbol id="help" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><path d="M12 17h.01"></path></symbol>`.
- `components/shop/help-button.blade.php`: asks
  `app(\App\Services\BookStack\ScreenHelp::class)->current()`. When it is not
  null, renders
  `<a class="shop-iconbtn" href="{{ route('help.show', ['screen' => $help['screen'], 'back' => request()->getRequestUri()]) }}" aria-label="How to do this" title="How to do this"><x-shop.icon name="help" /></a>`.
  Renders nothing otherwise.
- In `topbar.blade.php`, put `<x-shop.help-button />` as the first thing inside
  `@auth`, before `<details class="shop-usermenu">`. Nothing for guests.
- `components/help-button.blade.php` (office): same `current()` call; when not
  null, a full-width sidebar row linking to the same URL, text
  "How to: this page", with a question-mark icon, styled like the sidebar's
  other links (`text-gray-300 hover:bg-gray-700 hover:text-white`) and a top
  border `border-t border-gray-800`.
- In `layouts/admin.blade.php`: put `<x-help-button />` between `</nav>`
  (line 805) and `<!-- User menu -->` (line 807), so it stays visible while the
  nav scrolls. In the Administration section, after the "Shop devices" block
  (ends line 781), add a link "Procedures (BookStack)" to
  `config('services.bookstack.url')` with `target="_blank" rel="noopener"`,
  guarded by `(isManager() || isAdmin()) && config('services.bookstack.url')`,
  using the same classes as the neighbouring links (no active state).

Check: `php artisan test --filter=ConfinePinSessionTest` passes (the drift test
now sees `route('help.show')`). With BookStack unconfigured, which is the test
default, neither component renders anything, so no existing test's markup
changes: `php artisan test --filter=Shop` has the same pass count as before
this step plus any new tests.

### 10. Tests

Files: `tests/Feature/Help/HelpTest.php (new)`

What: `RefreshDatabase`, `Http::preventStrayRequests()` and `Cache::flush()` in
`setUp()`. A helper sets the three `services.bookstack.*` config values
(`url` = `http://bookstack.test`); a helper fakes the search and page
responses. Users via the `userWith()` pattern; the PIN case via the `pinUser()`
pattern. One test per line:

1. Unconfigured: an employee's `shop.home` is 200, `Http::assertNothingSent()`,
   the response has no `/help/` link, and `help.show` is 404.
2. The index is built from search results that carry tags; the request had
   `Authorization: Token id:secret` and a `query` containing `[screen]`.
3. Search results without a `tags` key: each page is read and the index is the
   same.
4. An employee sees the button on a screen with a procedure and not on a
   screen without one.
5. A manager sees the button on a screen without a procedure, and `help.show`
   for it shows the tag name, the route name and the Refresh form; an employee
   on the same URL sees neither.
6. One tagged page: `help.show` renders its sanitised HTML (`<script>` and
   `onclick` absent, `callout info` present).
7. Image and link rewriting, and no truncation of a 30,000-character page
   (step 4's check).
8. `help.page` with an id that is not in the index is 404, and no page request
   was sent for it.
9. Two pages tagged for one screen: `help.show` lists both titles, linking to
   `help.page`.
10. BookStack failing (500, and a thrown `ConnectionException`): `shop.home` is
    still 200 with no button; after a good index was cached and the index key
    is forgotten, a failing refetch still serves the last-good index.
11. `help.page` when the page fetch fails shows the "could not be loaded"
    message with status 200, and the failure is not cached (a later good
    response renders).
12. Image proxy: a PNG comes back with the three headers; an
    `image/svg+xml` response is 404; `/help/image/uploads/images/../../.env`
    is 404; `/help/image/books/x` does not match the route (404).
13. A PIN session reaches `help.show` with 200 (no redirect to
    `password.confirm`) and the response contains `data-shell="shop"`.
14. `help.refresh`: 403 for an employee; for a manager it redirects back with
    the flash and the next `help.show` refetches (assert the search was sent
    twice).
15. Office mode (a manager, no `ui_mode` cookie) gets the admin layout: the
    response does not contain `data-shell="shop"` and contains `sop-body`.
16. `back=https://evil.test/` and `back=//evil.test` are ignored: the rendered
    back link is the mode's home route.
17. A guest is redirected to login for `help.show`, `help.page` and
    `help.image`.

Check: `php artisan test --filter=HelpTest` → all pass.

### 11. Documentation

Files: `docs/features/sops-bookstack.md (new)`, `docs/FEATURES_INDEX.md`,
`CLAUDE.md`, `docs/features/shop-mode.md`, `docs/design/shop-mode/README.md`

What:

- `docs/features/sops-bookstack.md`: what the feature is; the split (BookStack
  writes, the app reads); how to link a page to a screen (the `screen` tag,
  where to find the value, Refresh, the 5-minute index cache and 10-minute page
  cache); the four routes; the `.env` keys; failure behaviour; the security
  rules from Constraints (tagged pages only, sanitised HTML, image proxy
  limits); and an **Owner setup in BookStack** section:
  1. create a shelf for shop SOPs with a book per area;
  2. create a role that can view only that shelf and has "Access system API",
     a user with that role, and an API token for that user; put the token id
     and secret in production `.env` with `BOOKSTACK_URL=http://bookstack.internal`;
  3. confirm the production machine itself resolves `bookstack.internal`;
  4. give each manager a BookStack editor account, and make sure their PCs
     resolve `bookstack.internal` for the sidebar link;
  5. deploy needs `composer install` (new package) and `npm run build`.
  Also a short "Not yet" list taken from Out of scope.
- `docs/FEATURES_INDEX.md`: an entry linking to the new doc.
- `CLAUDE.md`: one line under Features Overview (a "Staff Procedures" item) and
  one under "Where to Find Information", in the style of the existing lines.
- `docs/features/shop-mode.md`: note the topbar help button and the `help.*`
  allow-list entry; add `help` to the icon list if one is given there.
- `docs/design/shop-mode/README.md` line 12: add `help` (and the already
  present `info`) to the "app additions" list of sprite ids.

Check: `grep -n "sops-bookstack" docs/FEATURES_INDEX.md CLAUDE.md` finds both.

### 12. Format and build

What: `./vendor/bin/pint --dirty`, then `npm run build`.

Check: Pint reports no remaining changes on a second run; the Vite build ends
without errors.

## Verification

Run in order and paste the trimmed output.

1. `php artisan test --filter=HelpTest` → all pass.
2. `php artisan test --filter=Shop` → no failures (contract and PIN allow-list
   tests included).
3. `php artisan test` → **15 failed** (the same 15 as the baseline above) and
   901 + the number of new tests passed. Any other failure is yours.
4. `php artisan route:list --name=help` → four routes.
5. Design block check from step 7 prints nothing.
6. With `BOOKSTACK_URL` blank or absent in `.env`: open `/shop` and
   `/dashboard` in the browser; both render as before, with no help button and
   no "Procedures (BookStack)" link, and nothing new in `storage/logs/laravel.log`.
7. **Live check, only if `BOOKSTACK_URL`, `BOOKSTACK_TOKEN_ID` and
   `BOOKSTACK_TOKEN_SECRET` are set in the dev `.env`** (check with
   `grep -c '^BOOKSTACK_TOKEN_ID=.' .env`; never print the values). If they are
   not set, write "not run: no token in dev .env" and carry on; that is not a
   blocker.
   - `php artisan tinker --execute="dump(app(App\Services\BookStack\ScreenHelp::class)->index());"`
     → an index (possibly empty) and no exception. Record whether the search
     results carried `tags` or the per-page fallback was used.
   - If the owner has tagged a page: sign in, open that screen, press the help
     button, and confirm the procedure renders with its images, in Shop mode
     and in the office layout. Press the back button and confirm it returns to
     the screen. At 390 px wide the topbar still fits on one line.
   - As a manager on an untagged screen: the button shows, the empty state
     names the route, Refresh returns with the toast.

## Risks

- **API shape.** The search and page shapes are from the published docs, not
  this instance. Step 3's fallback covers missing `tags`. Anything else that
  differs in the live check goes in Notes for Planner with the real response
  (token removed).
- **Secured images.** If BookStack is set to `STORAGE_TYPE=local_secure` (or
  the restricted variant), `/uploads/images/…` needs a BookStack session and
  the proxy will get a redirect or 403, so images show as broken. Report it;
  do not work around it in this cycle.
- **Name resolution.** `bookstack.internal` resolves on the dev box through
  `/etc/hosts`. The production machine and the managers' PCs need the same, and
  that is the owner's to check.
- **Every page now reads one cache key** (`bookstack.screen-index`) when the
  feature is configured. Production uses the database cache store, so that is
  one small query per page, plus one HTTP call to BookStack every five minutes
  from whichever request finds the cache cold (3 s at worst if BookStack
  hangs).
- **Sanitiser strictness.** `allowSafeElements()` drops `style` attributes and
  iframes, so coloured text, fixed image widths and embedded video do not carry
  over. Note anything else it drops that a normal BookStack page uses.
- **Existing markup assertions.** Some Shop tests assert topbar fragments. The
  button renders nothing when unconfigured, so they should be untouched; if one
  fails on whitespace, report it rather than editing that test.

## Review

### Revision 1 review (Planner, 2026-09-30)

Read `implemented.md` to the end and every new or changed file in the diff.

#### Verified by the Planner

- `php artisan test` → **15 failed, 921 passed (4080 assertions)**; the 15 are
  the baseline set (CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2,
  TestScraperController ×1, UdeaScrapingService ×7). 921 = 901 + 19 + 1.
- `./vendor/bin/pint --test --dirty` → PASS, 9 files.
- Design block of `resources/css/shop.css` → `cmp` prints nothing.
- Every `--shop-*` token and every `shop-*` class the new Shop view and the
  `.shop-sop` rules use is defined in `shop.css`.
- `composer.lock`: one package added (`symfony/html-sanitizer` v7.4.20); the
  only other changes are `content-hash` and `plugin-api-version`.
- `Blade::render('<x-help-button />')` compiles and renders nothing when
  unconfigured.
- `.env` still has no `BOOKSTACK_*` keys, so the live check could not be run by
  either session.

#### Criteria

| Step | Result |
|---|---|
| 1 Configuration | Pass |
| 2 Sanitiser | Pass (7.4.20, see Deviations) |
| 3 `BookStackClient` | Pass |
| 4 `SopHtml` | Pass |
| 5 `ScreenHelp` | Pass as written; the plan itself was wrong about outages (defect 1) |
| 6 Routes, controller, allow-list | Pass, with one gap in the image proxy (defect 2) |
| 7 Shop reader | Pass on tests and tokens; not yet seen in a browser |
| 8 Office reader | Pass on tests; not yet seen in a browser |
| 9 Buttons and sidebar link | Pass, but the office sidebar row and the BookStack link have no test (defect 3) |
| 10 Tests | Pass, 19 tests |
| 11 Documentation | Pass |
| 12 Format and build | Pass |

#### Deviations

1. Sanitiser 7.4.20 instead of 7.3.x — **accepted**. It satisfies `^7.3` and
   moved no other package.
2. `Cache::forever(last-good)` only on a fresh build — **accepted**; better
   than the plan.
3. `screen` passed by `help.show` and null from `help.page` — **accepted**.
4. Test 16 assertion narrowed — **accepted**. The `href` assertions test the
   right thing.
5. Verification 6 done through the HTTP kernel, not a browser — **accepted**
   for the unconfigured case, where nothing visible changes. The by-eye check
   of the reader itself stays owed (see "Still owed").
6. `.env.example` placeholder values — **accepted**.

#### Notes for Planner

1. **An outage costs every page a timeout** — **fixed now** (Revision 2,
   step R1). The note is correct and the fault is the plan's: Risks said "one
   HTTP call every five minutes", but step 5 as written caches nothing on
   failure, so every page view retries the search and writes a log line.
2. **Stale-session script echoes the request URI** — **rejected, no change.**
   The value is escaped by `{{ }}`, and the login controller only honours a
   `redirect` that starts with `/` and not `//`
   (`AuthenticatedSessionController.php:24`). A `/\host` value is not an open
   redirect there either: `redirect()->intended()` builds an absolute URL on
   our own host from it.
3. **Scoped instances in tests** — **noted** for the next cycle's tests; no
   change.
4. **Tag name read from config in the empty state** — **accepted**.
5. **Live check and by-eye check owed** — **deferred** to the owner, who holds
   the BookStack login and the browser session. Listed under "Still owed".
6. **`plugin-api-version` in `composer.lock`** — **accepted**; metadata only.

#### Defects (fix in revision 2)

1. **Outage behaviour** (note 1 above). With BookStack down, every page view
   by every signed-in user makes a search request, waits for it to fail (up to
   2–3 s if BookStack hangs) and logs a warning.
2. **The image proxy can be steered outside `uploads/images/`.** Laravel
   decodes the route parameter once, so a request for
   `/help/image/uploads/images/%252e%252e/%252e%252e/x.png` reaches the
   controller as `uploads/images/%2e%2e/%2e%2e/x.png`. There is no `..`
   segment to refuse, and the client forwards it as written; a web server
   decodes `%2e%2e` to `..` and serves `/x.png`. Reproduced against a faked
   BookStack: the proxy returned 200 and the request sent was
   `http://bookstack.test/uploads/images/%2e%2e/%2e%2e/secret.png`. The same
   holds for `..%252f`. Impact is small (no token is sent, and only the four
   image types pass), but the plan's limit is "path must start
   `uploads/images/`" and this breaks it.
3. **No test covers the office sidebar.** Nothing asserts the "How to: this
   page" row or the "Procedures (BookStack)" link renders, or that both are
   absent when unconfigured.

### Revision 2 steps

Only these three. Report them in `implemented.md` under a new `## Revision 2`
heading, set `Plan revision: 2`, and keep the revision 1 report as it is.

#### R1. A short negative cache for the index

Files: `app/Services/BookStack/ScreenHelp.php`, `tests/Feature/Help/HelpTest.php`,
`docs/features/sops-bookstack.md`

What: in the `catch` of `ScreenHelp::index()`, after working out the fallback
(last-good, or the empty index), also store it:
`Cache::put(self::INDEX_KEY, $index, self::FAILURE_TTL)` with a new constant
`FAILURE_TTL = 60`. Nothing else changes: `LAST_GOOD_KEY` is still written only
on a successful build, `refresh()` still forgets `INDEX_KEY` (so a manager's
Refresh retries at once), and the page cache still never stores a failure.

Tests (new, in `HelpTest`):

- During an outage, two page views send one search request, not two: fake a
  failing BookStack, `GET shop.home` as an employee, `forgetScopedInstances()`,
  `GET shop.home` again, assert `searchCount()` is 1.
- The retry comes back after the minute: `$this->travel(61)->seconds()`,
  `forgetScopedInstances()`, fake a healthy BookStack with a page tagged
  `shop.home`, `GET shop.home` shows the button.
- A manager's Refresh retries at once: after a failed fetch, `POST help.refresh`,
  then the next request sends a search request (`searchCount()` goes up) without
  any time travel.
- The existing "a failing refetch serves the last good index" test must still
  pass unchanged.

In `docs/features/sops-bookstack.md`, section "When BookStack is down, slow or
unconfigured", say that after a failed refresh the fallback index is kept for
60 seconds, so an outage costs at most one slow request a minute, and that
Refresh retries immediately.

Check: `php artisan test --filter=HelpTest` → all pass, including the three new
tests.

#### R2. Close the encoded-path gap in the image proxy

Files: `app/Http/Controllers/HelpController.php`, `tests/Feature/Help/HelpTest.php`,
`docs/features/sops-bookstack.md`

What: in `HelpController::image()`, next to the `..` check, also return 404
when the (already decoded) `$path` contains any of `%`, `\`, `?`, `#`, or a
control character (`preg_match('/[%\\\\?#\x00-\x1f\x7f]/', $path)`). Do this
before any request is sent. Spaces stay allowed. `SopHtml` needs no change: it
decodes the path once before building the proxy URL, so an ordinary file name
never reaches the controller with a `%` in it.

Tests (extend "the image proxy passes images only" or add one): with a fake
that would answer 200 `image/png` to anything,

- `/help/image/uploads/images/%252e%252e/%252e%252e/secret.png` → 404
- `/help/image/uploads/images/..%252f..%252fsecret.png` → 404
- `/help/image/uploads/images/x.png%3Fdownload=1` → 404
- after those three, no request was sent to BookStack
  (`Http::assertNothingSent()` before the legitimate PNG request, or count
  the recorded requests).
- `/help/image/uploads/images/gallery/2026-09/a b.png` (a space) → still 200.

Add the rule to the "Image proxy limits" bullet in the feature doc.

Check: `php artisan test --filter=HelpTest` → all pass.

#### R3. Test the office sidebar

Files: `tests/Feature/Help/HelpTest.php`

What: two tests on `route('shop-devices.index')`, which a manager can open in
the test suite (`tests/Feature/ShopDeviceAdminTest.php:43`):

- Configured, with a page tagged `shop-devices.index`: a manager sees
  `How to: this page`, a link containing `/help/shop-devices.index`,
  `Procedures (BookStack)` and `href="http://bookstack.test"`.
- Unconfigured: the same page shows neither `How to: this page` nor
  `Procedures (BookStack)`, and `Http::assertNothingSent()`.

No application code should need to change for this step. If a test fails,
that is a finding: report it under Notes for Planner and set `BLOCKED` rather
than changing the layout.

Check: `php artisan test --filter=HelpTest` → all pass.

#### Revision 2 verification

1. `php artisan test --filter=HelpTest` → all pass (19 + the new tests).
2. `php artisan test` → **15 failed** (the same 15) and 921 + the number of new
   tests passed.
3. `./vendor/bin/pint --dirty` → nothing left to fix on a second run.
4. No `npm run build` is needed (no CSS, JS or Blade changes in this revision).

### Still owed, by the owner (not blocking Revision 2)

These need a BookStack API token in the dev `.env` and a signed-in browser,
which neither session has. They are Verification 7 of revision 1.

1. Create the token and set `BOOKSTACK_URL`, `BOOKSTACK_TOKEN_ID`,
   `BOOKSTACK_TOKEN_SECRET` in the dev `.env`.
2. Tag one BookStack page `screen` = `shop.stock-scan`, open `/shop/stock-scan`,
   press the help button. Look for: the procedure text and its images; lists
   and tables reading well; an image placed on its own line not being stretched
   wider than its natural size (the `.shop-sop` article is a flex column, and a
   top-level image would stretch); the topbar still on one line on a phone.
3. The same from the office layout (sidebar row "How to: this page").
4. Tell the Planner whether the index came back populated. That is the only
   evidence that this instance's API matches the shapes the plan assumed.
