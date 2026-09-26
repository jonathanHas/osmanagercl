# Shop mode cycle 14 — Request detail and edit in Shop mode; retire the board layout

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

The last two customer-request pages leave the old board layout. "Details" (`customer-requests.show`) becomes a Shop screen: the request's header facts, its lines with status pills, and the status history. "Edit" (`customer-requests.edit`) becomes a Shop screen with the same look as the New request sheet, extended to several lines: change the customer, phone, due date and notes; rename lines, change quantities and line notes; add a stocked product by typing or scanning (with thumbnails) or a free-text line to source; remove a line that has not moved past pending. Statuses are never changed here. With those ported, `BoardLayout`, `layouts/board.blade.php`, the office `_form` and the three office partials are deleted. On the way: the thumbnail pattern becomes one Blade component and one shared script module (cycle 12's note), the guest card partial loses its staff-only arguments, and the board's actions panel gains a "Details" link so the history is reachable from Shop mode.

## Context

Baseline: cycles 12, 13 and 13b accepted and archived; may still be uncommitted. Record `git status --short` first.

**Pages being replaced** (`resources/views/customer-requests/`): `show.blade.php` (header: name, "Request #id · taken by X on D j M Y H:i · closed …", Edit and Board links; flash `status`, error `status`; the office request card; "Status history" table of every line's `statusLogs` sorted by time then id: When (D j M H:i), Item (description), Change (from → to labels), By (user name or 'unknown'), Note), `edit.blade.php` (heading, "Line statuses are changed from the board; editing here only changes the details.", includes `_form`), `_form.blade.php` (Alpine `customerRequestForm({items, statusLabels})`: `addProduct(p)` merges a duplicate stocked line by adding 1 to quantity, `addBlankItem()`, `unlink(idx)`, `removeItem(idx)`; hidden `items[i][id|product_code|product_name]`, inputs `items[i][description]` (required), `[quantity]` (number, min 0.01), `[notes]`; remove only when `!item.id || item.status === 'pending'`, otherwise a status badge; submit blocked with `alert()` when no lines). Partials `request-card`, `status-buttons`, `status-pill` are used only by `show`.

**Controller** (`CustomerRequestController`): `show()` loads `items.statusLogs.user`, `items.statusChanger`, `creator`, `updater`, `closer` and returns `customer-requests.show`; `edit()` loads `items`, returns `customer-requests.edit` with `seedItems` (old input wins, else the model's lines as `{id, product_code, product_name, description, quantity (float), notes, status}`); `update()` (`CustomerRequestRequest`, PUT) → `CustomerRequestService::update()` syncs lines by id (foreign id → DomainException → back with `errors.items`), deletes lines not sent, never touches status; redirects to the board with `status` flash. Validation: `items` 1–50; `items.*.id` must belong to the request (`withValidator`); `description` required unless `product_code`; quantity numeric > 0 ≤ 9999; messages name "Line :position".

**Tests to keep green** (`CustomerRequestTest`): `test_update_syncs_lines_without_touching_status` (edit page shows "Keep me", "Delete me"; PUT payload with `items[]`), `test_update_rejects_a_line_from_another_request` (`from(edit)`, `assertSessionHasErrors(['items.0.id'])`), `test_show_page_lists_status_history` (sees "Status history", the note "Udea order #123", the user's name). `ShopRequestsTest` (8) covers the board.

**Shop pieces.** `resources/views/shop/partials/request-form.blade.php` (the sheet form; typeahead with thumbnails; `x-data="shopRequestForm(@js($seed))"`), `resources/js/shop/requests.js` (`kind`, `query`, `results`, `picked`, `description`, `searching`, `failed`, `search()`, `pick(p)`, `pickFirst()`, `unpick()`, `hasImage(p)`, `imageFailed(p)`; URL from `data-search-url`), `resources/js/shop/find-product.js` (same `hasImage`/`imageFailed` plus hover peek: **leave it alone this cycle**), `request-row.blade.php` (actions panel: Edit link first), `request-card.blade.php` (guest-only now, still takes `$canManage`, `$statusTone`, `$actionLabels`), `requests.blade.php` (guest branch passes those). Components: `x-shop.icon` (ids include `pencil`, `list-checks`, `history`, `x`, `plus`, `check`, `search`, `scan`, `package`, `phone`, `more`), `x-shop.scan-input`, `x-shop.tile`, `x-shop.topbar`. The contract test scans `resources/views/shop/**` (components under `resources/views/components/shop/` are not scanned; they may hold Alpine expressions). Stylesheet: design block + `APP ADDITIONS` (`.shop-thumb*`, `.shop-peek*`, `.shop-search--grow`, `.shop-req__more*`); `textarea.shop-input` exists in the design.

**No design screen exists** for detail or edit; both are composed from the components above, keeping the New request sheet's field order and look.

## Constraints

- Do not commit, push or deploy.
- Routes, permissions, `update()`, `CustomerRequestRequest` and the service are unchanged. `show()` and `edit()` change only their view names.
- Statuses are never changed from the edit screen (existing rule; the board does that).
- Keep the strings the existing tests assert on (listed above).
- Contract rules; design block byte-identical; app rules only under `APP ADDITIONS`.
- `x-product-search` (the office component) is not used in Shop views and is not touched.

## Out of scope

- Reworking the Find product screen's thumbnails to the new component (it has the hover peek; a later tidy).
- Changing what `update()` allows (e.g. removing a non-pending line).
- Guest board changes.

## Steps

### 1. Shared thumbnail: component and script module
Files: `resources/views/components/shop/product-thumb.blade.php (new)`, `resources/js/shop/product-images.js (new)`, `resources/js/shop/requests.js`, `resources/views/shop/partials/request-form.blade.php`, `docs/design/shop-mode/README.md`
What: the component takes `@props(['expr' => 'p'])` and renders exactly the pair the form uses today, with the expression substituted: `<img class="shop-thumb" x-show="hasImage({{ $expr }})" :src="{{ $expr }}.image_url" :alt="{{ $expr }}.name" loading="lazy" decoding="async" x-on:error="imageFailed({{ $expr }})"><span class="shop-row__lead" x-show="! hasImage({{ $expr }})"><x-shop.icon name="package" /></span>`. The module exports `productImages()` returning `{ failed: {}, hasImage(p) { return !! p?.image_url && ! this.failed[p.id]; }, imageFailed(p) { this.failed = { ...this.failed, [p.id]: true }; } }`. `requests.js` spreads `...productImages()` instead of its own copies, and `pick(p)` carries `id` (`{ id: p.id, code, name, image_url }`; the seed pick gets `id: null`). The form partial uses `<x-shop.product-thumb />` in the result rows and `<x-shop.product-thumb expr="picked" />` in the picked row (guard: `x-show="picked && hasImage(picked)"` becomes the component's `hasImage(picked)`; `hasImage` is null-safe above). README: add the component to the "Component API in the app" section.
Check: `php artisan test --filter="ShopRequestsTest|ShopViewContractTest"` green (the two thumbnail assertions in `staff_board_shows_actions_and_form` still hold because the component emits the same attributes); `grep -c "hasImage(p) {" resources/js/shop/requests.js` → 0.

### 2. Shared typeahead module
Files: `resources/js/shop/product-typeahead.js (new)`, `resources/js/shop/requests.js`
What: move `query`, `results`, `searching`, `search()` (reads `this.$root.dataset.searchUrl`, `LIMIT` 8) and `pickFirst()` into `productTypeahead()` returning those members plus `pickResult(p)` → `this.results = []; this.query = ''; this.onPick(p);`. `pickFirst()` calls `pickResult`. `requests.js` spreads `...productTypeahead()` and defines `onPick(p) { this.picked = {...}; }`; `unpick()` stays. Behaviour identical (the node exercise from cycle 12 Rev 2 still passes: scanned code + Enter with empty results → search → pick).
Check: rerun that node exercise against `requests.js`; `node --check` on all three modules; `grep -c "route(" resources/js/shop/product-typeahead.js` → 0.

### 3. Controller view names, and "Details" on the board
Files: `app/Http/Controllers/CustomerRequestController.php`, `resources/views/shop/partials/request-row.blade.php`
What: `show()` returns `view('shop.request-show', …)`, `edit()` returns `view('shop.request-edit', …)`; nothing else. In the row's actions panel, before Edit: `<a class="shop-menu__item" href="{{ route('customer-requests.show', $request) }}"><x-shop.icon name="list-checks" />Details</a>`.
Check: `git diff app/Http/Controllers/CustomerRequestController.php` shows two view-name lines only.

### 4. Detail screen
Files: `resources/views/shop/request-show.blade.php (new)`
What: `<x-shop-layout title="Request" :back="route('customer-requests.index')">`, `<main class="shop-page shop-page--narrow">`:
- Flash/error toasts as on the board (`session('status')`, `$errors->first('status')`).
- `section.shop-card`: `h2.shop-title` customer name; `shop-meta` "Taken by {creator name or unknown} · {created_at D j M H:i}" and, when closed, " · Closed {closed_at D j M H:i}{ by closer}"; `shop-inline` of pills: due pill as on the board (`shop-pill--bad` "Overdue Nd" / `--warn` "Due today" / `--muted` date / nothing), request status (`shop-pill--ok` "Open" / `--muted` "Closed"); phone line with the `phone` icon when present; notes as `shop-meta` when present.
- `h2.shop-subtitle` "Items" + `shop-list` of `div.shop-row` per line: `shop-row__main` (title `label()`, meta: qty · barcode when linked · "Pre-order"/"Sourcing" · line notes), `shop-row__aside` status pill (tones as the board: ordered sage, put aside ok, collected ok, not available bad, cancelled muted, pending muted "Pending").
- `h2.shop-subtitle` "Status history" + `shop-list` of rows built from the same `$logs` computation as the office page (flatMap `statusLogs`, sort by `created_at` then id): title = `{from label} → {to label}` (or just the to-label when no from), meta = `{D j M H:i} · {item description} · {user name or unknown}` and, when a note exists, a second meta line with the note. Empty → `shop-empty` "No status changes recorded."
- `shop-actions`: `<a class="shop-btn shop-btn--secondary shop-btn--lg" href="{{ route('customer-requests.index') }}">Back to board</a>` and `<a class="shop-btn shop-btn--primary shop-btn--lg" href="{{ route('customer-requests.edit', $customerRequest) }}"><x-shop.icon name="pencil" />Edit</a>`.
Check: `php artisan test --filter="CustomerRequestTest::test_show_page_lists_status_history|ShopViewContractTest"` green.

### 5. Edit behaviour: `request-edit.js`
Files: `resources/js/shop/request-edit.js (new)`, `resources/js/shop.js`
What: `Alpine.data('shopRequestEdit', (seed) => ({ ...productImages(), ...productTypeahead(), items: (seed ?? []).map(withKey), tooFew: false, onPick(p) {…}, addBlank() {…}, unlink(i) {…}, remove(i) {…}, canRemove(item) { return ! item.id || ! item.status || item.status === 'pending'; }, submitGuard(e) { if (this.items.length === 0) { e.preventDefault(); this.tooFew = true; } } }))` where `withKey` mirrors the office `_form` (id, product_code, product_name, description, quantity, notes, status, `_key`), `onPick(p)` merges a duplicate stocked line the same way (`+1` quantity when an existing line has the same `product_code` and is new or pending), else pushes `{ product_code: p.code, product_name: p.name, description: p.name, quantity: 1, image_url: p.image_url, id_product: p.id }` (keep a `product` object `{ id, image_url }` on the line for the thumbnail: `line.product = { id: p.id, image_url: p.image_url ?? null }`, and seeds get `product: null`). `addBlank()` pushes a free-text line and focuses its description via `$nextTick` and a `data-line-idx` attribute. Register in `shop.js`.
Check: node exercise: seed two lines (one with `id` and status `ordered`, one pending); `canRemove` false/true respectively; `onPick({id:'p1', code:'5000000000017', name:'Oat drink'})` twice → one line with quantity 2; `addBlank()` → three lines; `remove(2)` → two; `submitGuard` on an empty list sets `tooFew` and prevents default (stub event). `grep -c "route(\|alert(" resources/js/shop/request-edit.js` → 0.

### 6. Edit screen
Files: `resources/views/shop/request-edit.blade.php (new)`
What: `<x-shop-layout title="Edit request" :back="route('customer-requests.show', $customerRequest)">`, `<main class="shop-page shop-page--narrow">`. `@php` the same `$value`/`$wantedOn` helpers as the office form (old input wins wholesale). Error summary: when `$errors->any()`, a `shop-card shop-card--flat` listing `$errors->all()` as `shop-meta` lines with an `alert` icon.
`<form id="edit-request-form" method="POST" action="{{ route('customer-requests.update', $customerRequest) }}" class="shop-stack" x-data="shopRequestEdit(@js($seedItems))" data-search-url="{{ route('api.products.search') }}" @submit="submitGuard($event)">` with `@csrf @method('PUT')`:
- `section.shop-card`: `h2.shop-subtitle` "Customer"; fields Customer (`customer_name`, required), Phone (`customer_phone`, label "Phone · not shown on the public board"), Due (`wanted_on`, date), Notes (`<textarea class="shop-input" name="notes" rows="2" maxlength="2000">`), each with the `shop-pill--bad` error under it as on the sheet form.
- `section.shop-card`: `h2.shop-subtitle` "Items"; `shop-meta` "Line statuses are changed from the board; editing here only changes the details."; the lines as `shop-list` with `<template x-for="(item, idx) in items" :key="item._key">` → `div.shop-row` containing hidden inputs `items[idx][id|product_code|product_name]` (`:name` with template strings as the office form), a `<x-shop.product-thumb expr="item.product" />` guarded `x-show="item.product"` (free-text lines show the `shop-row__lead` with a `sprout` icon), `shop-row__main` with `shop-field`s: Description (`items[idx][description]`, `x-model="item.description"`, required, maxlength 255, `:data-line-idx="idx"`), a `shop-facts--2` of Quantity (`items[idx][quantity]`, `inputmode="decimal"`, `x-model="item.quantity"`) and Line note (`items[idx][notes]`, `x-model="item.notes"`, maxlength 500), a `shop-inline` of: `shop-code` barcode when `item.product_code`, "Unlink product" `shop-btn shop-btn--ghost` when `item.product_code`, status pill `<span class="shop-pill shop-pill--muted" x-show="item.id && item.status && item.status !== 'pending'" x-text="…label…">` (pass `\App\Models\CustomerRequestItem::LABELS` as `@js` into a `data-status-labels` attribute or into the data factory's second argument; choose the factory argument), and the remove ghost × `x-show="canRemove(item)"`.
  Below the list: the typeahead (`shop-search` with `scan` icon, `x-model="query"`, `@input.debounce.250ms="search()"`, `@keydown.enter.prevent="pickFirst()"`, placeholder "Type a name or scan a barcode", `enterkeyhint="search"`) and the results `shop-list` of `button.shop-row` with `<x-shop.product-thumb />`, name, code; then `<button class="shop-btn shop-btn--secondary" type="button" @click="addBlank()"><x-shop.icon name="plus" />Add a product to source</button>`. `shop-pill--bad` "Add at least one item before saving." `x-show="tooFew"`.
- `shop-actions`: `<a class="shop-btn shop-btn--secondary shop-btn--lg" href="{{ route('customer-requests.show', $customerRequest) }}">Cancel</a>` and `<button class="shop-btn shop-btn--primary shop-btn--lg" type="submit"><x-shop.icon name="check" />Save changes</button>`.
Check: `php artisan test --filter="CustomerRequestTest|ShopViewContractTest"` green ("Keep me"/"Delete me" appear in the seed JSON, which `assertSee` finds; if Blade escapes the JSON so the strings do not match, render each line's description also as the input's initial `value` attribute).

### 7. Retire the board layout and the office partials; slim the guest card
Files: delete `app/View/Components/BoardLayout.php`, `resources/views/layouts/board.blade.php`, `resources/views/customer-requests/show.blade.php`, `edit.blade.php`, `_form.blade.php`, `partials/request-card.blade.php`, `partials/status-buttons.blade.php`, `partials/status-pill.blade.php` (the `customer-requests/` folder is then empty: remove it); edit `resources/views/shop/partials/request-card.blade.php`, `resources/views/shop/requests.blade.php`
What: the guest card drops its `$canManage`, `$statusTone`, `$actionLabels` parameters and the branches they gated (phone, notes, edit link, action forms); it keeps `$item`, `$request`, `$qty` and the status pill with a local tone map. The board's guest branch passes only those three; the `$statusTone`/`$actionLabels` maps at the top of `requests.blade.php` go.
Check: `grep -rn "board-layout\|BoardLayout\|layouts.board\|customer-requests\.\(partials\|_form\|show\|edit\)" app/ resources/ tests/ config/` → nothing; `php artisan view:cache` succeeds (catches a missing include); `php artisan test --filter="Shop|CustomerRequest"` green.

### 8. Tests
Files: `tests/Feature/Shop/ShopRequestsTest.php`
What: add
- `detail_page_is_shop_styled_and_lists_lines_and_history`: request with two lines, one moved to ordered with a note; employee GET show → 200, `data-shell="shop"`, customer name, both labels, "Ordered" pill, "Status history", "Pending → Ordered", the note, the user's name, `route('customer-requests.edit', …)`, `shop-page--narrow`.
- `edit_page_seeds_lines_and_posts_to_update`: employee GET edit → 200, `x-data="shopRequestEdit(`, `data-search-url`, `name="_method" value="PUT"`, "Save changes", "Line statuses are changed from the board", both line descriptions; then PUT with a renamed line and a new line → redirect to the board, the lines match (this duplicates the office test's semantics deliberately, from the Shop page).
- `board_panel_links_to_details`: staff board contains `route('customer-requests.show', $request)`.
- `guest_card_never_renders_staff_arguments`: guest board → no phone, no edit route (already asserted in `guest_board_is_shop_styled_and_read_only`; extend that test instead of a new one).
- `board_layout_is_gone`: `$this->assertFileDoesNotExist(resource_path('views/layouts/board.blade.php'))` and `assertFalse(class_exists(\App\View\Components\BoardLayout::class))`.
Check: `php artisan test --filter="ShopRequestsTest|CustomerRequestTest"` green.

### 9. Docs, README, format, build
Files: `docs/features/customer-requests.md`, `docs/FEATURES_INDEX.md`, `docs/design/shop-mode/README.md`, all touched
What: feature doc: remove the `BoardLayout` bullet, describe the three Shop views (`shop/requests`, `shop/request-show`, `shop/request-edit`) and partials, the shared typeahead/thumbnail modules; FEATURES_INDEX line ~610 ("Own layout: x-board-layout") → "Shop mode shell". README: `x-shop.product-thumb`, `product-images.js`, `product-typeahead.js`. `./vendor/bin/pint --dirty`; `npm run build`.
Check: `./vendor/bin/pint --test --dirty` clean; build succeeds; `grep -rn "board-layout\|BoardLayout" docs/ --include=*.md | grep -v planImp` → nothing.

## Verification

1. `php artisan route:list --name=customer-requests` → unchanged.
2. `php artisan test --filter="Shop|CustomerRequest"` → green; the contract test lists the two new screens.
3. `php artisan test` → 17 failed, the identical set; passed = 560 + new tests.
4. `git diff app/Http/Controllers/CustomerRequestController.php` → two view-name lines; `git status --short` shows the eight deletions.
5. Contract greps; `grep -c "route(\|fetch(\|alert(" resources/js/shop/request-edit.js` → 0 (fetch lives in the typeahead module); design block `cmp` identical; `php artisan view:cache` succeeds then `php artisan view:clear`.
6. `./vendor/bin/pint --test --dirty` clean; `npm run build` succeeds.
7. Manual, dev app, owner-authorised, on a throwaway request created for the purpose and deleted afterwards: Details from the board's ⋯ panel shows the header, lines and history in Shop style; Edit opens with the lines seeded; scan a barcode into the item box → a stocked line appears with its thumbnail; scan the same again → quantity 2; add a free-text line; try to remove the ordered line → no × on it; remove the free-text line; Save → board with the toast, Details shows the changes and no status changed; Cancel returns to Details; a validation error (empty customer) re-renders the edit page with the summary and the lines kept; the guest board unchanged.

## Risks

- **Old-input seeding on the edit page**: `seedItems()` already prefers `old('items')`, and header fields use the office `$value` helper; port both verbatim so a bounced edit loses nothing.
- **`assertSee('Keep me')` against JSON**: `@js()` HTML-escapes nothing inside the string for plain words, so it matches; the plan's fallback (a `value` attribute) is there if not.
- **Deleting `layouts/board.blade.php`** breaks nothing else: the grep in step 7 proves it before the delete; `view:cache` proves it after.
- **The Find product screen keeps its own thumbnail code** until its own tidy; two implementations coexist for one more cycle by choice.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end and the diffs of the controller, the three new script modules, the sheet-form script, the thumbnail component, both new screens, the four changed partials, `shop.js` and the tests. Reran `php artisan test`: 17 failed / 566 passed, the identical pre-existing set. Checked the current deploy script: it runs `composer install --optimize-autoloader`, so the classmap is rebuilt on deploy and the deleted `BoardLayout` cannot fatal there.

**Steps 1–9: pass.** The controller diff is two view names. Eight office files and the board layout are gone and nothing references them; `view:cache` proves it. All eighteen office customer-request tests pass against the Shop screens. The shared thumbnail component and the two shared modules are used by both the sheet form and the edit screen; the sheet form's behaviour is unchanged (the scanner exercise still passes).

**Deviations.** 1 (`.js` extensions on module imports): **accepted**, needed for the node checks and harmless to Vite. 2 (`searchUrl` as a method, not a getter): **accepted, and the important catch**: spreading an object evaluates its getters at construction, before Alpine attaches `$root`; the README now records the constraint. 3 (no merge into a line past pending): **accepted**, the correct reading. 4 (`composer dump-autoload` locally, and `class_exists(…, false)`): **accepted**; the file-existence assertion is the real check.

**Notes for Planner.**
- Getter-in-a-spread constraint: recorded; nothing more to do.
- Find product keeps its own thumbnail code: **deferred**, as planned.
- Deploy autoloader: **checked**, covered by the deploy script.
- No confirmation on removing a line in the editor: **accepted**; removal only takes effect on Save, as in the office form.
- **Found in the diff:** seeded lines carry no product object, so a stocked line already on the request shows the `sprout` ("to source") placeholder on the edit screen. Cosmetic but misleading; **first step of the next cycle**: show `package` when `item.product_code` and `sprout` otherwise, and have `seedItems()` include `image_url` from the POS product when cheap.
- Nothing committed: correct.

**Manual walkthrough:** not run (writes on a throwaway request). Owner to try: Details from the board's ⋯ panel, Edit, scan a barcode twice to see the quantity merge, an ordered line with no ×, Save, Cancel.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-14/`.
