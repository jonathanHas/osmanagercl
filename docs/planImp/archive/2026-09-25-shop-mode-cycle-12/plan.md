# Shop mode cycle 12 — Customer requests board (screens 09 + 10), and Coffee orders off the Home

Status: ACCEPTED
Revision: 2
Planner: Fable 5.1
Date: 2026-09-25

## Goal

The public customer-requests board at `/customer-requests` becomes a Shop mode screen. Guests (the tablet on the counter, nobody signed in) see the read-only board from design screen 09: one card per requested item, grouped Due today / Coming up, with a "Staff sign in" button. Signed-in staff with `customer-requests.manage` see screen 10: the same cards with the status actions, plus a "New request" card that takes a pre-order (a stocked product, found by typing or by scanning) or a sourcing request (free text). The URL, the route name, the permission model and every write endpoint stay exactly as they are; only the board's rendering changes. Separately, the owner has decided the Coffee orders tile leaves the Shop Home, and baristas go straight to the KDS after login.

## Context

Baseline: cycle 10b accepted; cycle 11 (Zebra) parked under `docs/planImp/parked/`; 9b, 10 and 10b may still be uncommitted. Record `git status --short` first.

**Feature doc**: `docs/features/customer-requests.md` (read it; the data model and lifecycle are there). Key facts:
- `CustomerRequestController::index()` (`customer-requests.index`, **public**, registered at `routes/web.php:59` outside the auth group) passes `due`, `open`, `closed` (collections of `CustomerRequest` with `items.statusChanger` and `creator` loaded), `showClosed`, `canManage`, `openNew` (true on `?new=1` or when a failed submission left `old('customer_name')`), `seedItems` (old input lines or `[]`). `CustomerRequestService::board($includeClosed)` does the grouping: `due` = open requests with `wanted_on` today or earlier, `open` = the rest ordered by `wanted_on` (undated last), `closed` = last 30 days when asked.
- Writes (all `permission:customer-requests.manage`, inside the auth group, `routes/web.php:1165–1178`): `store` (POST, `CustomerRequestRequest`: `customer_name` required ≤120, `customer_phone` ≤40, `wanted_on` date, `notes` ≤2000, `items[]` 1–50 of `{ id, product_code ≤64, product_name ≤255, description (required unless product_code), quantity > 0 ≤ 9999, notes ≤500 }`; a stocked line may carry only `product_code`, the service snapshots the POS name), `updateItemStatus` (PATCH `customer-requests.items.status`, `{ status, note }`; redirects `back()` with `status` flash or `errors.status`; JSON when `Accept: application/json`), `cancel` (POST, whole request), `show`, `edit`, `update`.
- Models: `CustomerRequest` (`isDueToday()`, `isOverdue()`, `isDue()`, `isOpen()`, `wanted_on` Carbon or null, `customer_name`, `customer_phone`, `notes`, `creator`), `CustomerRequestItem` (`STATUS_*`, `LABELS`, `ALLOWED_TRANSITIONS`, `nextStatuses()`, `statusLabel()`, `labelFor()`, `label()` = product name or description, `isLinkedToProduct()`, `isOpen()`, `quantity` decimal, `notes`, `product_code`, `status_changed_at`, `statusChanger`).
- Current views (`resources/views/customer-requests/`): `index` (board, `<x-board-layout>`, a modal "New request" containing `_form`), `partials/request-card`, `partials/status-buttons` (one small PATCH form per allowed next status, labels Ordered / Put aside / Collected / Not available / Cancel / Back to pending), `partials/status-pill`, `_form` (multi-line Alpine form; uses `<x-product-search>`), `show` and `edit` (both `<x-board-layout>`; **stay as they are this cycle**).
- `BoardLayout` (`app/View/Components/BoardLayout.php`, `resources/views/layouts/board.blade.php`): guest meta refresh every 300 s; signed-in stale-session check. It stays this cycle because `show` and `edit` still use it.
- Tests: `tests/Feature/CustomerRequestTest.php` (18 tests) asserts on the board's markup: guest sees "Walk-in Wendy", "Oat milk", "Staff sign in", `route('login', ['redirect' => '/customer-requests'])`, and does **not** see "New request" nor the status route; staff see "New request", the status route for an item, "Show closed"; board shows "Overdue Olly", "Overdue 1d", "Due today", not "Closed Cleo"; `?closed=1` shows closed only to staff; a failed submission re-renders with "Kept Name", "Kept line" and the literal `x-data="{ open: true }"`; `?new=1` renders `x-data="{ open: true }"`, otherwise `x-data="{ open: false }"`. **These stay green by keeping those strings**, listed in step 5. `CustomerRequestDashboardTest` is the office dashboard, untouched.

**Shop shell facts.** `ShopLayout` props `title`, `back`, `guestSafe` (topbar renders "Staff sign in" → `route('login', ['redirect' => request()->getRequestUri()])` for guests), `bare`. The layout's stale-session check is inside `@auth`, so it renders for guests, but it has no guest refresh yet. Server flash: `session('success'|'error')` → layout toasts (cycle 9); the board writes flash key `status` and `errors` bags `status`/`items`, which the shop layout does not read — step 5 handles both in the view. `x-shop.scan-input` is not needed here; `find-product.js` shows how to query `api.products.search` (`this.$root.dataset.searchUrl`, `?q=…&limit=…`, `data.data[]` rows with `code`, `name`, plus a barcode-shaped query auto-selecting a single hit). Design screens: `docs/design/shop-mode/screen-09-requests-board.html`, `screen-10-requests-staff.html`. Classes used there: `shop-page--wide` (guest) / `shop-page` + `shop-split` (staff: board left, "New request" `shop-card` right), `shop-group-title` with `<small>` count, `shop-board` of `shop-request` (`__head` with `shop-stack--tight` of `__item` and `__who`, `__foot` of `shop-pill`s), `shop-seg--block` radio group, `shop-field`/`shop-input`, `shop-facts--2`, `shop-switch`, block primary button. Design deviation, decided: the design's single "Put aside" switch cannot express the six-state lifecycle, so staff cards get one `shop-btn shop-btn--secondary` per allowed next status (same set and labels as the office `status-buttons` partial), and the design's "Put aside when it arrives" switch is dropped because it has no backing field (delivery screens already flag pending/ordered lines).

**Home / KDS.** `config/shop.php` tile `kds` (Coffee orders → `kds.index`, `kds.access`). `UiMode::landingUrl()` sends baristas to `/shop` (`LandingRedirectTest::test_barista_lands_on_shop`), where `ShopHomeTest::test_barista_sees_only_coffee_orders_and_no_office_switch` expects the tile. Removing the tile without changing the landing would strand baristas on an empty Home.

## Constraints

- Do not commit, push or deploy.
- `/customer-requests` stays public, same route name. All writes stay behind `customer-requests.manage`. No controller logic moves; `CustomerRequestController::index()` may only gain view data (step 2), nothing else changes. `show`, `edit`, `_form`, `BoardLayout` untouched.
- Status changes and the new-request form are **plain HTML forms** posting to the existing endpoints (no fetch), so the existing redirect-and-flash contract and the existing tests keep working. The only JavaScript is the product typeahead in the New request card.
- Guests never see phone numbers (design: "Phone (not shown on board)").
- Contract rules as before (views under `resources/views/shop/**`, no `<script>`/`<style>`/Tailwind, no Alpine `@` Blade-directive shorthands, URLs via `data-*`).
- Keep the strings the existing tests assert on (step 5).

## Out of scope

- Porting `show` (status history) and `edit` (multi-line editing) to Shop mode, and retiring `BoardLayout`: **next cycle**. The staff card links to the office `edit` page with the design's pencil button.
- Whole-request cancel from the board (per-line Cancel covers a Shop-created request, which has one line; multi-line requests are cancelled from the office edit flow next cycle).
- Multi-line requests from the Shop form (one item per request; take a second request for a second item).
- Notes on a request or line from the Shop form.
- Changes to the KDS itself.

## Steps

### 1. Coffee orders off the Home; baristas land on the KDS
Files: `config/shop.php`, `app/Support/UiMode.php`, `tests/Feature/Shop/ShopHomeTest.php`, `tests/Feature/Shop/LandingRedirectTest.php`
What: delete the `kds` tile. In `landingUrl()`, before the shop/office decision, return `route('kds.index')` for a user whose `isBarista()` is true. Tests: rename/adjust `test_barista_sees_only_coffee_orders_and_no_office_switch` to `test_barista_sees_no_tiles_and_no_office_switch` (asserts "Nothing to do here yet", not "Coffee orders", not "Office"); `test_barista_lands_on_shop` → `test_barista_lands_on_the_kds` asserting redirect to `route('kds.index')`. Check the KDS route is reachable for a barista (permission `kds.access`) with a GET in the same test if `kds.index` renders without extra fixtures; otherwise assert the redirect only and say so.
Check: `php artisan test --filter="ShopHomeTest|LandingRedirectTest"` green; `grep -c kds config/shop.php` → 0.

### 2. Guest refresh on the Shop layout
Files: `app/View/Components/ShopLayout.php`, `resources/views/layouts/shop.blade.php`, `tests/Feature/Shop/ShopHomeTest.php`
What: new prop `public ?int $guestRefresh = null`; in the layout `<head>`, when `$guestRefresh` and no user: `<meta http-equiv="refresh" content="{{ $guestRefresh }}">`. Nothing for signed-in users (they keep the stale-session check).
Check: a rendering test in `ShopHomeTest`: `Blade::render('<x-shop-layout title="T" :guest-safe="true" :guest-refresh="300">x</x-shop-layout>')` as a guest contains `http-equiv="refresh" content="300"`, and rendered while `actingAs` a user does not.

### 3. Controller view data
Files: `app/Http/Controllers/CustomerRequestController.php`
What: `index()` returns `view('shop.requests', [...])` with the same array plus `'searchUrl' => $canManage ? route('api.products.search') : null`, `'formErrors' => $errors` is unnecessary (the `$errors` bag is shared); `openNew`, `seedItems` unchanged. Delete `resources/views/customer-requests/index.blade.php` and `partials/request-card.blade.php`, `partials/status-buttons.blade.php`, `partials/status-pill.blade.php` **only if** `grep -rn "customer-requests.partials\|customer-requests.index'" resources/views app` shows no other includes (`show.blade.php` includes `status-pill`; keep whatever is still used, delete the rest, and list what was deleted in `implemented.md`).
Check: `php artisan route:list --name=customer-requests.index` unchanged (no middleware).

### 4. The board's data shape for the view
Files: `app/Http/Controllers/CustomerRequestController.php` or a small view-model in `app/Services/CustomerRequestService.php`
What: the view renders one card per **item**, so add `CustomerRequestService::boardCards(bool $includeClosed): array` returning `['due' => [...], 'open' => [...], 'closed' => [...]]`, each a list of `['item' => CustomerRequestItem, 'request' => CustomerRequest]` in the order `board()` gives the requests, then line `position`. Closed groups include all lines; due/open groups include only lines that are still open (`$item->isOpen()`) **plus** lines collected/cancelled in the last 24 h so a mis-tap can be undone from the board (the backwards transitions exist for that). `index()` passes `cards = boardCards($showClosed)` alongside the existing data. Unit-test it in `ShopRequestsTest` (step 7).
Check: tinker `app(App\Services\CustomerRequestService::class)->boardCards(false)` returns the three keys.

### 5. The screen
Files: `resources/views/shop/requests.blade.php (new)`
What: `<x-shop-layout title="Customer requests" :back="auth()->check() ? route('shop.home') : null" :guest-safe="true" :guest-refresh="auth()->check() ? null : 300">`. Guest: `<main class="shop-page shop-page--wide">`; staff: `<main class="shop-page">` with `shop-split` (board left, New request card right). Board content (both audiences):
- Guest lead `shop-meta`: "Pre-orders and items we are sourcing for you. Ask at the counter if yours is ready." Guest banner is **not** the office grey box; the topbar's "Staff sign in" is the design's affordance. Keep the exact strings "Staff sign in" (comes from the topbar) and `route('login', ['redirect' => request()->getRequestUri()])` (also from the topbar).
- Flash: `@if (session('status'))` → a `shop-toasts` block with `shop-toast--ok` (same markup the layout uses; it is server-rendered here because the key is `status`, not `success`); `@if ($errors->has('status') || $errors->has('items'))` → `shop-toast--bad` with the first message, unless `$openNew` (then the form shows it).
- Groups: `Due today` (`$cards['due']`, `shop-group-title` "Due today <small>N</small>"), `Coming up` (`$cards['open']`), and for staff with `$showClosed` `Closed recently` (`$cards['closed']`). Empty group → `shop-empty` "Nothing due today" / "No open requests" (keep "No open requests" text wording free; nothing asserts it).
- Card (`article.shop-request`): `__item` = `$item->label()`; `__who` = customer name · quantity (format the decimal like the office: `rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.')`) and, for staff only, ` · ` phone when present. `__foot` pills: due pill (`shop-pill--bad` "Overdue {n}d" using `$request->wanted_on->diffInDays(today())` — keep this exact "Overdue 1d" format; `shop-pill--warn` "Due today"; `shop-pill--muted` "{D j M}" for a future date; nothing when undated), type pill (`shop-pill--muted` "Pre-order" when `isLinkedToProduct()`, else "Sourcing"), status pill (`shop-pill--ok` "Put aside", `shop-pill--sage` "Ordered", `shop-pill--ok` "Collected", `shop-pill--bad` "Not available", `shop-pill--muted` "Cancelled"; no pill for pending). Staff card head also gets the ghost pencil `<a class="shop-iconbtn shop-iconbtn--ghost" href="{{ route('customer-requests.edit', $request) }}" aria-label="Edit">` and, when `$item->notes` or `$request->notes`, a `shop-meta` line under the who-line.
- Staff actions: under the foot, `<div class="shop-inline">` with one form per `$item->nextStatuses()`: `<form method="POST" action="{{ route('customer-requests.items.status', $item) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="…"><button class="shop-btn shop-btn--secondary" type="submit">{label}</button></form>`, labels as the office partial (Ordered, Put aside, Collected, Not available, Cancel, Back to pending). The "Collected" button is `shop-btn--primary`.
- Staff "New request" card (`section.shop-card`, `x-data="{ open: @js($openNew) }"` **exactly** so the existing assertions match; the content below toggled with `x-show="open"`; when closed, a block `shop-btn shop-btn--primary shop-btn--lg` "New request" `@click="open = true"` — this keeps the "New request" string visible to staff and absent for guests): `<form method="POST" action="{{ route('customer-requests.store') }}" x-data="shopRequestForm()" data-search-url="{{ $searchUrl }}">` with `@csrf`, then, following the design: `shop-seg shop-seg--block` radio `kind` (Pre-order / Sourcing, `x-model="kind"`, default pre-order); Customer (`customer_name`, required, value `old('customer_name')`); Item: for pre-order a `shop-search` input (`x-model="query"`, `@input.debounce.250ms="search()"`, `@keydown.enter.prevent="pickFirst()"`) with a `shop-list` of results as `button.shop-row` (`@click="pick(p)"`), and once picked a `shop-row is-latest` showing the product with a ghost × to unpick; hidden inputs `items[0][product_code]`, `items[0][product_name]`, `items[0][description]` bound to the pick; for sourcing a plain `shop-input` bound to `items[0][description]` (`old('items.0.description')`); Quantity (`items[0][quantity]`, `inputmode="decimal"`, default `old(…, 1)`); Due (`wanted_on`, type date); Phone (`customer_phone`, `inputmode="tel"`, label "Phone (not shown on board)"); block primary "Add request". Field errors: `@error('customer_name')`-style is a Blade directive and is fine in Blade (it is the Alpine `@error` on an element that is the trap); render `<span class="shop-field__error">{{ $message }}</span>` — check the design CSS for an error class; if none exists, use `shop-meta` with `shop-scan__msg`-like tone via `shop-pill--bad`. Nothing in this form uses `x-product-search` (its markup is Tailwind).
- The seed: if `$seedItems` is non-empty (failed submission), the form initialises from `$seedItems[0]` (`x-data="shopRequestForm(@js($seedItems[0] ?? null))"`), so "Kept line" reappears in the description input's `value`.
Check: `php artisan test --filter="CustomerRequestTest|ShopViewContractTest"` green (the contract test must list this screen; if it enumerates files it picks it up automatically).

### 6. Behaviour: `requests.js`
Files: `resources/js/shop/requests.js (new)`, `resources/js/shop.js`
What: `Alpine.data('shopRequestForm', (seed) => ({...}))`: `kind` ('preorder' | 'sourcing'; sourcing when the seed has a description and no product_code), `query`, `results`, `picked` (`{ code, name }` or null from the seed's product_code/product_name), `description` (seed or ''), `searching`. `search()`: GET `${this.$root.dataset.searchUrl}?q=…&limit=8` with `Accept: application/json`, `results = data.data`; empty query clears. `pick(p)`: `picked = { code: p.code, name: p.name }`, `results = []`, `query = ''`. `pickFirst()`: if `results.length` pick the first (so a scanned barcode + Enter picks the product without submitting). `unpick()`. Getters `productCode`, `productName`, `descriptionValue` (picked name for pre-order, `description` for sourcing) for the hidden inputs. Register in `shop.js`.
Check: `node --check`; `grep -c "route(" resources/js/shop/requests.js` → 0.

### 7. Tests
Files: `tests/Feature/Shop/ShopRequestsTest.php (new)`
What (`RefreshDatabase`; POS fixture from `ShopLabelsTest::setUp()` for the product name snapshot; users via `userWith()`):
- `guest_board_is_shop_styled_and_read_only`: seed a due request (customer "Walk-in Wendy", stocked line "Oat milk", `wanted_on` today, phone "087 111"), GET `/customer-requests` → 200, `data-shell="shop"`, `shop-page--wide`, "Walk-in Wendy", "Oat milk", "Due today", "Staff sign in", `http-equiv="refresh" content="300"`, **not** "087 111", not "New request", not the status route, not the pencil `route('customer-requests.edit', …)`.
- `staff_board_shows_actions_and_form`: employee → sees the status route for the pending item with buttons "Ordered", "Put aside", "Not available", "Cancel", the pencil link, "087 111", "New request", `data-search-url="` + `route('api.products.search')`, no `http-equiv="refresh"`.
- `cards_are_per_item_and_grouped`: one request with two lines (one due today) and one undated request → Due today group count 2 (`<small>2</small>` after "Due today"), Coming up count 1.
- `recently_collected_line_stays_on_the_board_for_a_day`: a line collected 2 h ago is shown with "Back to put aside"-equivalent (the `put_aside` button labelled "Put aside"); one collected 2 days ago is not shown.
- `store_from_the_shop_form_creates_a_single_line_request`: POST `customer-requests.store` with `customer_name`, `wanted_on`, `items[0][product_code] = 5000000000017`, `items[0][quantity] = 2` → redirect to the board with the `status` flash; the board then shows "Oat drink 1 L" and the flash text.
- `failed_submission_reopens_the_form_with_old_input`: POST with an empty `customer_name` and `items[0][description] = 'Kept line'` from the board → redirect; GET board → `x-data="{ open: true }"`, `value="Kept line"`.
- `barista_home_has_no_coffee_tile_and_lands_on_kds`: covered in step 1's files; do not duplicate.
Check: `php artisan test --filter="ShopRequestsTest|CustomerRequestTest|ShopHomeTest|LandingRedirectTest"` green.

### 8. Docs, README, format, build
Files: `docs/features/customer-requests.md`, `docs/design/shop-mode/README.md`, all touched
What: feature doc: the board is now `resources/views/shop/requests.blade.php` (Shop mode shell, guests get a 5-minute refresh, one card per line, staff actions are the same PATCH forms, the New request card takes one line and finds products through `api.products.search`); `show`/`edit` still use `BoardLayout` (to be ported). README bullet for Customer requests. Add a line to `config/shop.php`'s comment or README that Coffee orders is no longer a Home tile and baristas land on the KDS. `./vendor/bin/pint --dirty`; `npm run build`.
Check: `./vendor/bin/pint --test --dirty` clean; build succeeds.

### 9. (Rev 2) A scanned barcode picks its product in one motion
Files: `resources/js/shop/requests.js`
Why: a USB scanner types the code and sends Enter within tens of milliseconds, before the 250 ms debounced `search()` has run, so `pickFirst()` finds `results` empty and does nothing. The person then has to tap the hit that appears a moment later.
What: make `pickFirst()` async: if `results` is empty and the trimmed query is not, `await this.search()` first; then pick the first result if there is one. `pick()` already clears `query`, so the debounced search that fires afterwards sees an empty query and clears `results` rather than re-populating them.
Check: node exercise with a stubbed `fetch` resolving `{ data: [{ id: 'p1', code: '5000000000017', name: 'Oat drink 1 L' }] }`: set `query = '5000000000017'`, call `await pickFirst()` with `results` still empty → `picked.code === '5000000000017'`, `query === ''`, `results.length === 0`; with the stub resolving `{ data: [] }` → `picked === null`.

### 10. (Rev 2) Product images in the item search
Files: `resources/views/shop/partials/request-form.blade.php`, `resources/js/shop/requests.js`
What: the search API already returns `image_url` (see `docs/features/product-search.md`), and the find-product screen renders it with the `.shop-thumb` app addition (`resources/views/shop/find-product.blade.php` lines 86–87: `<img class="shop-thumb" x-show="hasImage(p)" :src="p.image_url" :alt="p.name" loading="lazy" decoding="async" x-on:error="imageFailed(p)">` with a `shop-row__lead` icon fallback). Copy that pair into each typeahead result row and into the picked row (`:src="picked?.image_url"`), and port `hasImage(p)` / `imageFailed(p)` with a `failed` map into `requests.js` (no hover peek here: the form column is narrow and the peek panel is find-product's). `pick(p)` keeps `image_url` on `picked`. The stylesheet needs nothing new.
Check: `php artisan test --filter="ShopRequestsTest|ShopViewContractTest"` green; `staff_board_shows_actions_and_form` also asserts the markup contains `class="shop-thumb"` and `x-on:error="imageFailed(p)"`; node exercise: `hasImage({ id: 1, image_url: 'x' })` true, after `imageFailed` false, `hasImage({ id: 2 })` false.

## Verification

1. `php artisan route:list --name=customer-requests` → unchanged list and middleware.
2. `php artisan test --filter="Shop|CustomerRequest"` → green; the contract test now includes `shop/requests.blade.php`.
3. `php artisan test` → 17 failed, the identical set; passed = 546 + new tests − 0 (renamed tests keep their count).
4. `git diff app/Http/Controllers/CustomerRequestController.php` shows only the view name and the added view data in `index()`; `git status --short resources/views/customer-requests/` shows deletions only for files nothing else includes.
5. Contract greps; `grep -c "route(" resources/js/shop/requests.js` → 0; design-block `cmp` identical.
6. `./vendor/bin/pint --test --dirty` clean; `npm run build` succeeds.
7. Manual, dev app, owner-authorised: signed out on the tablet URL `/customer-requests` → shop-styled board, no phone numbers, "Staff sign in" goes to login and back; signed in as an employee → New request: pick "Pre-order", type three letters of a product, tap it, quantity 2, a date, Add request → toast, card appears under Coming up; scan a barcode into the Item box on the till PC + Enter → the product is picked, not submitted; tap "Put aside" → pill turns green, button set changes; tap "Collected" → the card stays with a "Put aside" undo button; "Show closed" lists it after a day; as a barista, login lands on the KDS and `/shop` shows the empty state.

## Risks

- **Existing test strings.** Five `CustomerRequestTest` assertions depend on literal markup (`x-data="{ open: true }"`, "Overdue 1d", "Show closed", "New request", "Staff sign in"). Step 5 keeps each; if one cannot be kept sensibly, update the test and say why in Deviations rather than contorting the view.
- **`$errors` and old input** come back to a public route; a guest can never trigger them (writes need a login), but the view must not assume a user when rendering errors.
- **Per-item cards** change the mental model slightly from the office's per-request cards; the customer name is on every card, and a multi-line request simply shows as adjacent cards.
- **The typeahead** relies on `api.products.search` being inside the auth group (it is, `routes/web.php:172`); guests never render the form.
- **Baristas** lose the Shop Home entirely; if a barista ever gets another Shop permission, Home is still reachable at `/shop` from the KDS's own navigation only if the KDS links there. Not a v1 concern.

## Review

### Revision 1 (2026-09-25, Planner)

Read `implemented.md` to the end and the diffs of the controller, service, `UiMode`, layout component and view, config, both new partials, the board view, `requests.js` and the new test. Reran `php artisan test`: 17 failed / 556 passed, the identical pre-existing set.

**Steps 1–8: pass.** All eighteen `CustomerRequestTest` assertions survive unchanged. The controller diff is the view name plus two view-data keys. Guests get no phone numbers, no edit link, no form, and the 300 s refresh; staff get the PATCH forms, the pencil, the typeahead. Baristas land on the KDS and the tile is gone.

**Deviations.** 1 (three view files): **accepted**, the contract test covers all three. 2 (bounced line via the Alpine seed): **accepted**, correct given the shared hidden field. 3 (seeding `_old_input`): **accepted**, same shape as the existing test. 4 (`shop-pill--bad` for field errors): **accepted**, the plan's fallback. 5 ("Show closed" as a link): **accepted.**

**Notes for Planner.** Barista cannot reach Shop Home: intended; a KDS home link is for a cycle that gives baristas a second task. Office `_form`/`show`/`edit` still Tailwind: next cycle. `boardCards()` re-runs the board queries and the `due`/`open`/`closed` keys are unused: **fixed next cycle** with the show/edit port (drop the keys, call `board()` once). Whole-request cancel unsignposted: **deferred**, the same cycle. Nothing committed: correct.

**Found in the diff, not raised:** with a keyboard-wedge scanner the Enter arrives before the debounced search has run, so `pickFirst()` has nothing to pick. That is the till-PC path the plan promised, so it is Revision 2 step 9. The owner also asked for product images in the item search: step 10, using the thumbnail pattern find-product already has.

**Verdict:** READY, Revision 2. Steps 9–10 only; steps 1–8 stand.

### Revision 2 (2026-09-25, Planner)

Read `implemented.md` to the end and the diff of `requests.js`, the form partial and the test. Reran `php artisan test`: 17 failed / 556 passed, the identical set. Controller and service diffs unchanged from Revision 1.

**Steps 9–10: pass.** `pickFirst()` runs the search itself when Enter arrives before the debounce, and picks the first hit; the empty-query guard is right. Thumbnails render in the result rows and the picked row with the icon fallback, using the existing `.shop-thumb` rule.

**Deviations:** none.

**Notes for Planner.**
- Enter awaits a network round trip with no busy indicator: **deferred**; the same gap exists on Find product. A `searching` state on the search box is a candidate for the shared product-row work below.
- Bounced submission re-seeds the pick without an image: **accepted as harmless.**
- Three copies of the thumbnail pattern: **agreed**; the next cycle that touches a product row extracts an `x-shop.product-row` component and a shared image-fallback module. One small thing to fold into that: `pick()` does not carry the product `id`, so a picked row whose image fails records the failure under an undefined key and a later pick with a good image would still show the placeholder until reload. Cosmetic; fix when the component lands.
- Nothing committed: correct.

**Manual walkthrough:** not run; the scanner-Enter path on the till PC is the one thing worth a real check by the owner.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-25-shop-mode-cycle-12/`.
