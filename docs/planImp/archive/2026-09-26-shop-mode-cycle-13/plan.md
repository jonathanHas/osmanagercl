# Shop mode cycle 13 — Customer requests staff board v2 (design "Screen 10 Requests staff v2")

Status: ACCEPTED
Revision: 2
Planner: Fable 5.1
Date: 2026-09-25

## Goal

The owner had Claude Design rework the staff view of the customer-requests board. The new screen replaces cycle 12's staff cards with request rows: a date block on the left (Today / Late / the date), the item and customer in the middle, a three-step lifecycle strip (Ordered → Put aside → Collected) showing where the line is, one primary next-step button, and a "more" menu (Edit, Undo last step, Not available, Cancel request). Above the list, a segmented filter (Open N / Put aside N / Done) and a search box; "New request" lives in the sticky bottom bar and opens the form in a side sheet (a bottom sheet on phones). The guest board from cycle 12 (screen 09) is unchanged. The URL, route name, permissions and write endpoints stay exactly as they are.

## Context

Baseline: cycle 12 accepted and archived; its files may still be uncommitted. Record `git status --short` first.

**Design.** `docs/design/shop-mode/screen-10-requests-staff-v2.html` (saved today from the Claude Design project). New stylesheet rules were added to `docs/design/shop-mode/shop.css` today (46 lines, additions only: "Request rows (staff)", "Lifecycle steps", "Sheet", plus `.shop-req` / `.shop-sheet*` lines in the 768 px and 1024 px media blocks). `resources/css/shop.css` still holds the previous design block, so step 1 refreshes it. The sprite is unchanged and lacks two icons the screen uses: a three-dot "more" and a "phone" glyph; step 1 adds them as app additions to the sprite (the design inlines its SVGs).

Markup, from the design:
- Filter row: `<div class="shop-between">` with `<div class="shop-seg" role="radiogroup">` of three `shop-seg__opt` (design uses radios; port as links with `aria-current="page"` on the active one, which the stylesheet styles identically), and a `shop-search` with `<input class="shop-input" type="search" placeholder="Search customer or item">`. The design gives the search `style="flex:1;max-width:360px;min-width:220px"`; inline `style` on a Shop view is allowed by the contract test only if it does not scan for it (check; if it fails, add a one-line `.shop-search--grow` rule under `APP ADDITIONS` and use that).
- Group: `<section class="shop-stack shop-stack--tight"><h2 class="shop-group-title">Due today <small>1</small></h2><div class="shop-reqs">…</div></section>`.
- Row: `<article class="shop-req">` containing `<div class="shop-req__date [is-today|is-late]"><span>Today</span><strong>25</strong><span>Sep</span></div>`, `<div class="shop-req__main"><h3 class="shop-req__title">…</h3><div class="shop-req__meta"><span>The Count · 1</span><span class="shop-inline">📞 057 …</span><span class="shop-pill shop-pill--muted">Pre-order</span></div></div>`, `<ol class="shop-steps" aria-label="Status"><li class="shop-step [is-done] [is-current]">Ordered</li>…Put aside…Collected</ol>`, `<div class="shop-req__actions"><button class="shop-btn shop-btn--primary|--secondary">Mark ordered</button><details class="shop-more"><summary class="shop-iconbtn" aria-label="More actions">⋯</summary><div class="shop-menu" role="menu">…shop-menu__item…<div class="shop-menu__sep"></div>…shop-menu__item--danger…</div></details></div>`. A finished row adds `is-done` on the article; `shop-steps is-stopped` strikes the steps through.
- Bottom bar: `<div class="shop-actions"><button class="shop-btn shop-btn--primary shop-btn--lg">＋ New request</button></div>`.
- Sheet: `<div class="shop-sheet-backdrop"><section class="shop-sheet" role="dialog" aria-label="New request"><div class="shop-sheet__head"><h2 class="shop-subtitle">New request</h2><button class="shop-iconbtn shop-iconbtn--ghost" aria-label="Close">×</button></div><div class="shop-sheet__body">…form fields…</div><div class="shop-sheet__foot"><button class="shop-btn shop-btn--secondary shop-btn--lg">Cancel</button><button class="shop-btn shop-btn--primary shop-btn--lg">✓ Add request</button></div></section></div>`. The design's field order is Type seg, Item, Customer, Quantity + Due, Phone ("Phone · not shown on the public board").

**Lifecycle mapping** (`CustomerRequestItem`: `pending`, `ordered`, `put_aside`, `collected`, `not_available`, `cancelled`; `ALLOWED_TRANSITIONS` as documented; every status write goes through the existing PATCH `customer-requests.items.status` form; whole-request cancel is POST `customer-requests.cancel`, which cancels every open line via `CustomerRequestService::cancel()`):

| status | steps | primary button (→ status) | more menu (after Edit) |
|---|---|---|---|
| pending | none done | "Mark ordered" (→ ordered) | "Put aside now" (→ put_aside), "Not available", sep, "Cancel request" |
| ordered | Ordered done+current | "Put aside" (→ put_aside) | "Undo last step" (→ pending), "Not available", sep, "Cancel request" |
| put_aside | Ordered done, Put aside done+current | "Collected" (→ collected) | "Undo last step" (→ ordered), sep, "Cancel request" |
| collected | all done, Collected current; article `is-done` | none | "Undo last step" (→ put_aside) |
| not_available | `is-stopped`; article `is-done`; pill "Not available" | "Reopen" secondary (→ pending) | none besides Edit |
| cancelled | `is-stopped`; article `is-done`; pill "Cancelled" | "Reopen" secondary (→ pending) | none besides Edit |

Primary is `shop-btn--primary` when the request is due today or overdue, or when the button is "Collected"; otherwise `shop-btn--secondary` (that is what the design shows). Every button is a plain form (`@csrf @method('PATCH')`, hidden `status`), as in cycle 12; "Cancel request" is a form to `customer-requests.cancel` (POST). No confirm dialog: a mis-tap is undone with "Reopen"/"Undo last step" (the backwards transitions exist for that).

**Views.** Filter `show`: `open` (default; lines pending or ordered), `aside` (lines put_aside), `done` (lines collected, not_available or cancelled whose `status_changed_at` is within 30 days, newest first, one group "Done recently"). `?closed=1` is kept as an alias of `show=done` because `CustomerRequestTest::test_closed_requests_are_only_shown_to_staff_who_ask` uses it. Within `open` and `aside`, the groups are "Due today" (request due today or overdue) and "Coming up", as now. Guests always get `open` and `aside` lines together on the cycle 12 board (no filter, no search; unchanged). Counts for the seg labels: open lines, put-aside lines (done shows no count).

**Existing code.** `CustomerRequestController::index()` (cycle 12: passes `due`/`open`/`closed` from `board()`, `cards` from `boardCards($showClosed)`, `showClosed`, `canManage`, `openNew`, `seedItems`, `searchUrl`). `CustomerRequestService::board()` (keep: `CustomerRequestTest` calls it) and `boardCards()` (cycle 12; replaced here). Views: `resources/views/shop/requests.blade.php`, `partials/request-card.blade.php` (cycle 12 card: keep for guests), `partials/request-form.blade.php` (keep, moves into the sheet). JS: `resources/js/shop/requests.js` (form typeahead; keep). Tests: `tests/Feature/Shop/ShopRequestsTest.php` (6), `tests/Feature/CustomerRequestTest.php` (18, asserts on board strings: guest sees "Staff sign in" and no "New request"; staff see "New request", the status route, **"Show closed"**; "Overdue Olly", **"Overdue 1d"**, "Due today"; `?closed=1`; `x-data="{ open: true }"` / `{ open: false }`). Decisions on those strings: "New request", "Due today", the status route, `x-data="{ open: … }"` are kept naturally; "Overdue 1d" is kept as a `shop-sr-only` span inside the Late date block (screen readers get the full phrase); **"Show closed" is replaced by the seg's "Done" and that one assertion is updated** (say so in Deviations).

**Shop shell.** Layout, `x-shop.icon`, sprite at `public/images/shop-icons.svg` (ids listed in `docs/design/shop-mode/README.md`), contract test, `shop.js` registration inside `alpine:init`, URLs via `data-*`/Blade only.

## Constraints

- Do not commit, push or deploy.
- Route, permissions, controller actions and the service's write methods unchanged. `index()` changes only its view data. Guest rendering is byte-for-byte the cycle 12 guest board except for the stylesheet refresh.
- Status changes stay plain forms; the only JavaScript is the typeahead (existing) and the board's search filter + sheet open/close (new, tiny).
- The design block of `resources/css/shop.css` is replaced by the new `docs/design/shop-mode/shop.css` verbatim; app rules stay below the `APP ADDITIONS` marker.
- Contract rules as before.

## Out of scope

- Porting `show`/`edit` and retiring `BoardLayout` (the "Edit" menu item still links to the office edit page). Next cycle.
- Multi-line requests from the sheet; notes.
- Guest board changes.

## Steps

### 1. Stylesheet refresh and two sprite icons
Files: `resources/css/shop.css`, `public/images/shop-icons.svg`, `docs/design/shop-mode/shop-icons.svg`, `docs/design/shop-mode/README.md`
What: replace everything above `/* === APP ADDITIONS START ===` in `resources/css/shop.css` with the full content of `docs/design/shop-mode/shop.css` (then the marker and the existing app rules). Add two symbols to both sprite copies, after `leaf`: `<symbol id="more" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></symbol>` and `<symbol id="phone" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></symbol>`. Add `more`, `phone` to the README's id list with "(app additions)".
Check: `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo DESIGN-BLOCK-IDENTICAL`; `grep -c 'id="more"\|id="phone"' public/images/shop-icons.svg` → 2; `cmp public/images/shop-icons.svg docs/design/shop-mode/shop-icons.svg`; `php artisan test --filter=Shop` still green (nothing uses the new rules yet).

### 2. Service: rows by view
Files: `app/Services/CustomerRequestService.php`, `app/Models/CustomerRequestItem.php`
What: add to the model `public const DONE_STATUSES = [STATUS_COLLECTED, STATUS_NOT_AVAILABLE, STATUS_CANCELLED]`. Replace `boardCards(bool)` with `boardRows(string $view = 'open'): array` returning `['due' => [...], 'open' => [...], 'done' => [...], 'counts' => ['open' => int, 'aside' => int]]` where each row is `['item' => CustomerRequestItem, 'request' => CustomerRequest]`:
- `open`: items with status in `[pending, ordered]` whose request is open, eager-loaded `request.creator`, `statusChanger`; grouped `due` (request `isDue()`) / `open` (rest), ordered by request `wanted_on` (nulls last), request `created_at`, item `position` (the same order `board()` uses).
- `aside`: same with status `put_aside`.
- `done`: items with status in `DONE_STATUSES` and `status_changed_at >= now()->subDays(30)`, ordered `status_changed_at` desc, all in the `done` key; `due`/`open` empty.
- `counts`: open = count of `[pending, ordered]` items on open requests; aside = count of `put_aside` items on open requests; computed regardless of `$view`.
Remove the cycle 12 "recently changed stays a day" rule and `changedRecently()`: the Done view is where undo happens now. Keep `board()`.
Check: tinker `app(App\Services\CustomerRequestService::class)->boardRows('done')` returns the four keys; step 7 tests pin the semantics.

### 3. Controller view data
Files: `app/Http/Controllers/CustomerRequestController.php`
What: `index()`: `$view = $request->boolean('closed') ? 'done' : $request->query('show', 'open'); $view = in_array($view, ['open','aside','done'], true) ? $view : 'open';` guests are forced to `'open'`, and the guest board still needs put-aside lines, so for guests call `boardRows('open')` and `boardRows('aside')` and merge their `due`/`open` lists (keeping order: due first within each) — or add a `'board'` view to the service that returns `[pending, ordered, put_aside]` together; **do the latter** (`boardRows('board')`), it is one more `whereIn`. Pass `rows`, `view`, `canManage`, `openNew`, `seedItems`, `searchUrl`. Drop `due`/`open`/`closed`/`showClosed`/`cards` from the view data (nothing renders them; this also stops the board queries running twice, cycle 12's note). `board()` is no longer called by the controller.
Check: `git diff app/Http/Controllers/CustomerRequestController.php` shows only `index()`; `php artisan route:list --name=customer-requests` unchanged.

### 4. Behaviour: `requests-board.js`
Files: `resources/js/shop/requests-board.js (new)`, `resources/js/shop.js`
What: `Alpine.data('shopRequestsBoard', (startOpen) => ({ open: startOpen, q: '', matches(text) { const n = this.q.trim().toLowerCase(); return n === '' || text.toLowerCase().includes(n); }, show() { this.open = true; this.$nextTick(() => this.$refs.first?.focus()); }, close() { this.open = false; } }))`. Rows carry `data-text="{{ customer }} {{ item label }}"` and `x-show="matches($el.dataset.text)"`. No fetch. Register in `shop.js`.
Check: `node --check`; `grep -c "route(\|fetch(" resources/js/shop/requests-board.js` → 0.

### 5. The staff board
Files: `resources/views/shop/requests.blade.php`, `resources/views/shop/partials/request-row.blade.php (new)`, `resources/views/shop/partials/request-card.blade.php` (guest card: unchanged), `resources/views/shop/partials/request-form.blade.php`
What: in `requests.blade.php`, the staff branch (`$canManage`) becomes:
- `<main class="shop-page" x-data="shopRequestsBoard(@js($openNew))" x-on:keydown.escape.window="close()">`. **Keep the literal `x-data="{ open: @js($openNew) }"`?** No: `CustomerRequestTest` asserts `x-data="{ open: true }"` / `{ open: false }` verbatim on three tests. Keep those assertions green by putting the sheet in its own scope: `<div x-data="{ open: @js($openNew) }" x-on:open-sheet.window="open = true" x-on:keydown.escape.window="open = false">` wrapping the bottom bar and the sheet; the board's search scope is a separate `x-data="shopRequestsBoard()"` on the list container (drop `open`/`show`/`close` from step 4's module; it only needs `q` and `matches`). The bottom-bar button does `@click="open = true"` inside that scope.
- Filter row: `shop-between` with the seg as three links: `route('customer-requests.index')` "Open <small>N</small>", `route('customer-requests.index', ['show' => 'aside'])` "Put aside <small>N</small>", `route('customer-requests.index', ['show' => 'done'])` "Done"; `aria-current="page"` on the active one (use `class="shop-seg__opt"` on `<a>`; the count in a `shop-btn__count`-style span is not in the design; render the number inline as text "Open 4"). The search `shop-search` with `x-model="q"`, placeholder "Search customer or item".
- Groups: for `open`/`aside`: "Due today" then "Coming up" (each only when non-empty; when both empty one `shop-empty` "Nothing here" with text per view: "No open requests" / "Nothing put aside" ); for `done`: one group "Done recently" (empty → "Nothing finished in the last 30 days").
- Row partial `request-row.blade.php` (expects `$item`, `$request`): date block: overdue → `is-late`, `<span>Late</span><strong>{{ d }}</strong><span>{{ M }}</span>` plus `<span class="shop-sr-only">Overdue {{ diffInDays }}d</span>`; due today → `is-today`, `<span>Today</span>…`; future → `<span>{{ D }}</span><strong>{{ j }}</strong><span>{{ M }}</span>`; undated → `<span>No</span><strong>–</strong><span>date</span>`. Main: title `$item->label()`; meta: `customer · qty` (cycle 12 formatter), phone with `<x-shop.icon name="phone" size="sm" />` when present, type pill (Pre-order / Sourcing), and for `not_available`/`cancelled` a `shop-pill--bad`/`--muted` status pill; `$item->notes ?: $request->notes` as a `shop-meta` line when present. Steps and actions exactly per the lifecycle table; the "more" `<details class="shop-more">` uses `<x-shop.icon name="more" />` in the summary and menu items as forms (`<form …><button class="shop-menu__item" type="submit" role="menuitem"><x-shop.icon name="history" />Undo last step</button></form>`), "Edit" is `<a class="shop-menu__item" href="{{ route('customer-requests.edit', $request) }}"><x-shop.icon name="pencil" />Edit</a>`, "Not available" uses `alert`, "Cancel request" uses `x` and `shop-menu__item--danger`. Row gets `data-text` and `x-show="matches($el.dataset.text)"`.
- Bottom bar `shop-actions` with the "New request" button (`<x-shop.icon name="plus" />`).
- Sheet: `<div class="shop-sheet-backdrop" x-show="open" x-cloak @click.self="open = false"><section class="shop-sheet" role="dialog" aria-modal="true" aria-label="New request">` head (title + ghost × `@click="open = false"`), body = `@include('shop.partials.request-form', …)` with the form given `id="new-request-form"` and its own submit button removed, foot = `<button class="shop-btn shop-btn--secondary shop-btn--lg" type="button" @click="open = false">Cancel</button><button class="shop-btn shop-btn--primary shop-btn--lg" type="submit" form="new-request-form"><x-shop.icon name="check" />Add request</button>`. Reorder the form's fields to the design: Type seg, Item (search / picked row), Customer, Quantity + Due, Phone (label "Phone · not shown on the public board"). Error pills stay. Also `x-effect="document.body.classList.toggle('shop-no-scroll', open)"`-style body locking is **not** available without a stylesheet rule; skip it (the backdrop is `position: fixed` and covers the page; scrolling behind is tolerable).
- Toast blocks (session `status`, errors) as in cycle 12. Guest branch untouched.
Check: `php artisan test --filter="ShopViewContractTest|CustomerRequestTest|ShopRequestsTest"`: the contract test green; `CustomerRequestTest` green except `test_staff_see_controls_on_the_board`'s "Show closed", handled in step 7.

### 6. Guest board
Files: none
What: nothing changes for guests; confirm by test that the guest markup still uses `shop-request` cards and has no `shop-req` rows, no search, no seg.
Check: covered by step 7.

### 7. Tests
Files: `tests/Feature/Shop/ShopRequestsTest.php`, `tests/Feature/CustomerRequestTest.php`
What:
- `CustomerRequestTest::test_staff_see_controls_on_the_board`: replace `assertSee('Show closed')` with `assertSee('Done')` and `assertSee(route('customer-requests.index', ['show' => 'aside']), false)`. Record in Deviations that this is the one existing assertion changed and why (the design replaced the link with a segmented filter).
- `ShopRequestsTest`: keep `guest_board_is_shop_styled_and_read_only` (add `assertDontSee('shop-req ', false)` and no `shop-search`); rewrite `staff_board_shows_actions_and_form` for v2: sees `shop-req`, `shop-steps`, "Mark ordered" as the pending row's primary, the status route, the `more` icon reference (`#more`), "Undo last step" absent for pending, "Put aside now" present, `route('customer-requests.cancel', $request)`, `route('customer-requests.edit', $request)`, the seg with "Open 1", `data-search-url`, `id="new-request-form"`, `form="new-request-form"`; replace `cards_are_per_item_and_grouped` with `rows_are_grouped_and_counted` (two open lines due today + one put-aside line + one undated pending → Open view: "Due today <small>2</small>", "Coming up <small>1</small>", seg "Open 3" and "Put aside 1"); replace `recently_collected_line_stays_on_the_board_for_a_day` with `done_view_lists_finished_lines_from_the_last_30_days` (collected 2 h ago shown with "Undo last step"; cancelled 2 days ago shown with "Reopen"; not_available 40 days ago absent; `?closed=1` gives the same page as `?show=done`); `put_aside_view_shows_collected_as_primary` (a put_aside line → "Collected" button carries `shop-btn--primary`; its menu has "Undo last step" and "Cancel request"); `overdue_row_uses_the_late_block_and_keeps_the_sr_text` (wanted_on yesterday → `shop-req__date is-late`, "Late", `Overdue 1d`); keep the store and failed-submission tests (the sheet must open: `x-data="{ open: true }"` still asserted).
Check: `php artisan test --filter="ShopRequestsTest|CustomerRequestTest|ShopHomeTest"` green.

### 8. Docs, README, format, build
Files: `docs/features/customer-requests.md`, `docs/design/shop-mode/README.md`, all touched
What: feature doc: staff board v2 (views open/aside/done with `?show=`, `?closed=1` alias, lifecycle strip, primary next step, more menu, sheet; guests unchanged). README: Customer requests bullet updated; new classes come from the design file (no app additions except the sprite icons and, if needed, `.shop-search--grow`). `./vendor/bin/pint --dirty`; `npm run build`.
Check: `./vendor/bin/pint --test --dirty` clean; build succeeds.

### 9. (Rev 2) Search hides empty groups and says when nothing matches
Files: `resources/js/shop/requests-board.js`, `resources/views/shop/partials/requests-staff.blade.php`, `tests/Feature/Shop/ShopRequestsTest.php`
Why: rows hide on search but their group headings and server counts stay, so "Due today 3" can sit over one row, and a miss shows two bare headings.
What: in the module add `groupMatches(el)` → `this.q` is read first (so Alpine re-evaluates on every keystroke), then `Array.from(el.querySelectorAll('[data-text]')).some(r => this.matches(r.dataset.text))`; and `anyMatch()` → the same over `this.$root`. Each group `<section>` gets `x-show="groupMatches($el)"`; the heading count stays the server count (the number of rows in the view, which is still true) but add, after `<small>`, nothing else. Add a `shop-empty` "No matches" block (`search` icon, text "Nothing matches your search.") with `x-show="q.trim() !== '' && ! anyMatch()"` inside the board scope, after the groups.
Check: node exercise with a fake `$root` (three `[data-text]` nodes in two sections): `q = 'zzz'` → `anyMatch()` false and both `groupMatches()` false; `q = 'oat'` → the section holding the oat row true, the other false. `staff_board_shows_actions_and_form` also asserts `x-show="groupMatches($el)"` and "Nothing matches your search." are in the markup.

### 10. (Rev 2) More menu: open one at a time, and open upward near the bottom
Files: `resources/js/shop/requests-board.js`, `resources/views/shop/partials/request-row.blade.php`, `resources/css/shop.css` (`APP ADDITIONS`)
Why: the walkthrough confirmed the plan's risk: the last row's menu opens past the fold, with "Cancel request" the item off screen; two menus can be open at once.
What: app rule `.shop-more.is-up > .shop-menu { top: auto; bottom: calc(100% + 8px); }`. In the module add `menuToggled(details)`: if `details.open`, close every other `details.shop-more[open]` inside `this.$root`, then `const r = details.querySelector('.shop-menu').getBoundingClientRect(); details.classList.toggle('is-up', r.bottom > window.innerHeight && r.height < details.getBoundingClientRect().top)`; if not open, remove `is-up`. On the row: `<details class="shop-more" x-on:toggle="menuToggled($el)">`. (`toggle` fires on `<details>` after `open` changes, so the rect is measurable.) Also close open menus on `keydown.escape.window` in the board scope.
Check: node exercise with a stubbed `details` (open true, menu rect bottom 900 vs innerHeight 800, details top 500) → class `is-up` added; a second stubbed details already open gets `open = false`. `grep -c "is-up" resources/css/shop.css` → 1 below the marker; the design block `cmp` still identical.

## Verification

1. `php artisan route:list --name=customer-requests` → unchanged.
2. `php artisan test --filter="Shop|CustomerRequest"` → green.
3. `php artisan test` → 17 failed, the identical set.
4. Design block `cmp` → identical (with the **new** design css); sprite copies identical; contract greps clean; `grep -c "route(\|fetch(" resources/js/shop/requests-board.js` → 0.
5. `git diff app/Http/Controllers/CustomerRequestController.php` → only `index()`; `git diff app/Services/CustomerRequestService.php` → `boardCards` replaced by `boardRows`, `board()` and every write method untouched.
6. `./vendor/bin/pint --test --dirty` clean; `npm run build` succeeds.
7. Manual, dev app, owner-authorised (the implementer may drive the browser): as an employee at 1024 px wide: rows show date block · main · steps · actions on one line; at phone width the steps wrap under and the actions under those; the seg switches views and the active one is highlighted; typing in search hides non-matching rows instantly; tapping "Mark ordered" reloads with the step lit and the button now "Put aside"; the ⋯ menu opens on tap and closes on the next tap; "Undo last step" steps back; "Cancel request" cancels and the row is found under Done with "Reopen"; "New request" opens the sheet from the right (from the bottom on a phone), Escape and the backdrop close it, "Add request" submits and the toast shows; a failed submit reopens the sheet with the values kept; the guest tablet view is unchanged.

## Risks

- **Native `<details>` menus** stay open until tapped again and more than one can be open; the design accepts this (no JS). A menu near the bottom of the viewport may open off-screen; the stylesheet positions it below the button. Watch for it on the phone; if it is a problem, a follow-up can flip it upward.
- **Inline `style` in the design** (search width, facts columns): the contract test may reject `style=`; the plan's fallback is a one-line app addition.
- **"Show closed" assertion** is the only existing test changed; everything else keeps its strings, including "Overdue 1d" via the screen-reader span.
- **Body scroll behind the sheet** is not locked (no rule for it); acceptable for now.
- **Counts** are computed on every request (two small counts); fine.

## Review

### Revision 1 (2026-09-25, Planner)

Read `implemented.md` to the end and the diffs of the controller, model, service, stylesheet additions, both new partials, the board view, `requests-board.js` and both test files. Reran `php artisan test`: 17 failed / 560 passed, the identical pre-existing set.

**Steps 1–8: pass.** Design block identical to the new design css, sprites identical with the two added icons, lifecycle table implemented as written, one primary button with the right tone, menus gated per status, the seg as links with `aria-current`, the sheet with the design's field order, guest board unchanged, controller reduced to one `index()` hunk, the double board query gone. "Overdue 1d" survives as screen-reader text. Seventeen of eighteen customer-request assertions unchanged.

**Deviations.** 1 ("Show closed" → "Done"): **accepted**, planned. 2 (staff board as its own partial): **accepted.** 3 (`.shop-search--grow` app rule instead of inline style): **accepted**, tidier. 4 (`role="group"` instead of `radiogroup` for links): **accepted**, correct.

**Notes for Planner.**
- PHP-side sort in `boardRows()`: **deferred**, volumes are tiny; revisit if the board ever slows.
- Search leaves orphaned headings: **fixed now, step 9.**
- Native `<details>` menu opens off-screen at the bottom and several can be open: **fixed now, step 10.**
- No body-scroll lock behind the sheet: **deferred.**
- `request-card.blade.php` still takes staff-only arguments: **next cycle**, with the show/edit port.
- Nothing committed: correct.

**Manual walkthrough:** run by the implementer against the dev app with a throwaway request, data restored. Everything on the checklist observed except Escape/backdrop dismissal and the guest view.

**Verdict:** READY, Revision 2. Steps 9–10 only; steps 1–8 stand.

### Revision 2 (2026-09-26, Planner)

Read `implemented.md` to the end and the diff of `requests-board.js`, both staff partials, the stylesheet addition and the test. Reran `php artisan test`: 17 failed / 560 passed, the identical set. Design block still byte-identical; sprites identical.

**Steps 9–10: pass.** Groups hide when none of their rows match, a "Nothing matches your search." state appears on a miss, `groupMatches()` reads `q` first so Alpine re-evaluates it, menus close each other, and a menu that would run off the bottom flips upward only when there is room above.

**Deviations:** none. The two additions (clearing `is-up` on close; leaving a menu downward when it would not fit above either) are correct.

**Notes for Planner.**
- One layout read per menu open: fine.
- Group counts describe the view, not the filter: **accepted as is**; the confusing case is gone.
- Escape closes menus and the sheet: harmless.
- Manual check of the two fixes was blocked by an expired browser session (the implementer rightly did not sign in). **Owner to confirm on the tablet**: a search with no hits shows the message with no headings, and the last row's ⋯ menu opens upward with "Cancel request" visible.
- Nothing committed: correct.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-13/`.
