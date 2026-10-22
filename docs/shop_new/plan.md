# Labels in Shop mode — Shelf labels and Zebra labels tabs

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-10-21

## Goal

Give "Print labels" in Shop mode both halves of the job, inside Shop mode. Today
`/shop/labels` holds only the A4 shelf-label queue, and its two tiles ("Zebra
labels", "Printed recently") drop the user into the office layout. After this
cycle the Home tile opens a Labels section with two tabs: **Shelf labels** (the
queue as now, plus a collapsed "Printed today" list with "Back to queue" per
row and Undo on Remove / Clear) and **Zebra labels** (scan or search a saved
Zebra label or a translated label, see whether its price or country still
matches the till, choose copies, print to the counter Zebra with the till's
value for that print only, and see the printer spool, with "Cancel all" for
managers). The office pages keep every job that makes or maintains labels.

The design is the owner's Claude Design revision of 2026-10-21, saved as
`docs/design/shop-mode/screen-07-shelf-labels.html` and
`docs/design/shop-mode/screen-07b-zebra-labels.html`. Read both first: the
`<script type="text/x-dc">` at the end of each is the behaviour spec (states,
sample data, what every action does) and is what the steps below describe in
app terms. The brief behind the design is `docs/shop_new/design-brief-labels.md`.

## Context

**Baseline.** HEAD `58842899` on `feature/modularization-phase1`. Dirty before
this cycle: `docs/deliveries/README.md` (M), `docs/deliveries/implemented-glued-price.md`
and `plan-glued-price.md` (D), `docs/deliveries/archive/2026-10-20-glued-price/` (??),
`docs/shop_new/README.md` (M), `docs/shop_new/design-brief-labels.md` (??),
`docs/design/shop-mode/screen-07-shelf-labels.html` and `screen-07b-zebra-labels.html` (??).
`php artisan test` (2026-10-21): **16 failed**, all pre-existing and unrelated:
`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3,
`FruitVegLabelPrintingTest` ×2, `ProductTest` ×2, `TestScraperControllerTest` ×1,
`BackupConfigurationTest` ×1. Clean means the same 16 and nothing else.

**Shelf labels today.** `resources/views/shop/labels.blade.php` +
`resources/js/shop/labels.js` (`Alpine.data('shopLabels')`) +
`app/Http/Controllers/Shop/LabelsController.php`. Queue JSON `labels.queue`
(`LabelAreaController::queue()`): `{ rows: [{ id, code, name, price, event,
since, reason }], counts, labels_per_sheet }`; `id` is the POS product ID. Add
= POST `labels.scan` `{ barcode }`; remove = POST `labels.dismiss` `{ barcode }`
(records `label_dismiss`); clear = POST `labels.dismiss-all` (`labels.manage`).
Print = plain form POST to `labels.print-a4` in a new tab; `printA4()` logs a
`label_print` per product at POST time, so the queue and the printed list are
right as soon as the page reloads (`@submit="setTimeout(() => load(), 1500)"`).
The queue is a derivation over `LabelLog` in `app/Services/LabelQueueService.php`
(`needingLabels()`, `countsByEventType()`): a product needs a label when its
latest `new_product` / `price_update` / `barcode_change` / `requeue_label`
event is newer than its last `label_print` or `label_dismiss`.

**Requeue and restore.** `LabelAreaController::requeueProduct()` (`labels.requeue`,
POST, `labels.print`) takes `product_id` (POS ID), logs `requeue_label` for the
product's CODE, returns `{ success: true, message }` (400 without an id, 404
unknown). `restoreBatch()` (`labels.restore-batch`, POST, **labels.manage**)
validates `timestamp` (required string, only echoed in the message) and
`product_ids[]` (strings), logs a requeue per found product, returns
`{ success, message, restored_count }`. A requeue after a print or a dismiss
puts the product back on the queue, which is exactly "Back to queue" and the
two Undo actions.

**Printed history.** `shelfLabels()` loads `LabelLog::with('product')
->eventType(LabelLog::EVENT_LABEL_PRINT)->where('created_at', '>=', now()->subDays(7))
->orderBy('created_at', 'desc')->limit(200)->get()` and groups it with the
private `groupLabelPrintsByTime()` (five-minute windows keyed `Y-m-d H:i:s`,
each `['timestamp', 'display_time', 'products' => LabelLog[], 'count']`,
newest first). `LabelLog::product()` is `belongsTo(Product::class, 'barcode', 'CODE')`
and can be null (product deleted). Dev: 253 prints in 30 days, batches of 1–43
(median 30), on about one day in four.

**Zebra labels.** Everything in the parked plan
`docs/planImp/parked/2026-09-25-shop-mode-cycle-11-zebra/plan.md` § Context
still holds and is not repeated here: `App\Models\ZebraLabel` and its static
helpers (`extractTextFields`, `findPriceField`, `findCountryField`,
`setZplQuantity`, `replaceTextFields`), the office page `LabelAreaController::zebra()`
(the non-translations branch runs from line 80 `$zebraLabelsQuery = …` to the
end of the `foreach` at line 163, building `$zebraLabels` (product on the till
per `TillVisibilityService::isVisibleOnTill()`), `$otherLabels` and
`$standAloneLabels`, each item `['id','name','product_name','product_code',
'width_mm','height_mm','default_copies','mismatches','fields']`; **mismatches
are computed for every product-linked label, on the till or not**), the print
endpoint `ZebraLabelController::print()` (`zebra-labels.print`, POST
`labels/zebra/manage/{zebraLabel}/print`, `labels.print`; `copies` 1–99,
optional `fields` index→text applied for this print only; `{ success, message,
output }`, 200/500), the spool endpoints `labels.printer-queue` (GET,
`labels.print`; `{ success, host, printer, jobs[], count, output }`, up to 15 s)
and `labels.printer-cancel` (POST, **labels.manage**; `{ all: true }` or
`{ job_id }`), and the faked printer for tests
(`tests/Feature/DeliveryTranslatedLabelPrintingTest.php` lines 89–100).
`ZebraPrintService::sendRaw()` returns `success: false` with "No output from lp
(timed out or printer unreachable)" when the host is down: a print to an
unreachable printer **fails**, nothing is held. Dev: 17 active labels, 14
product-linked (all bagged produce and the shop's own chutney), 3 standalone.

**Translated labels.** `App\Models\ProductTranslation` (default DB; fillable
`product_id, product_code, label_data (json), label_size, font_scale,
original_photos, zpl_content, auto_print (bool), created_by`;
`product()` = `belongsTo(Product::class, 'product_code', 'CODE')`;
`latestPerProductCode(array $codes)` returns the newest per code).
`label_data['product_name']` is the name the office shows. `label_size` is a
key of `config('label-sizes')`: `large` = 75 × 50 mm, `small` = 56 × 31 mm
(`widthMm` / `heightMm`). Print = `LabelTranslationController::print()`
(`labels.translate.print`, POST `labels/translate/{translation}/print`,
`labels.print`): `copies` 1–99, `setZplQuantity`, `sendRaw`, same JSON as the
Zebra print; 422 when the row has no ZPL. These auto-print on delivery scans
already (`DeliveryLegacyController::printableTranslations()`), which the Shop
delivery screen uses. Dev: 301 rows, 199 with auto-print.

**Permissions.** `labels.print` = every shop-floor user; `labels.manage` =
Admin, Jessika, Jonathan, test. PIN sessions are confined to
`config('shop.pin_session_routes')`; `shop.*` is already there, so the new
`shop.zebra` route is covered, but every `labels.*` endpoint a Shop view names
must be listed (drift test
`ConfinePinSessionTest::test_every_route_a_shop_view_names_is_on_the_allow_list`
greps `route('…')` in `resources/views/shop/**`).

**Shop parts to copy.** Tab nav: `resources/views/shop/partials/fv-nav.blade.php`
(`shop-seg`, `shop-seg__opt`, `aria-current` tight against the quote).
Print card with stepper: `resources/views/shop/fv-harvest.blade.php` lines
73–105 and `resources/js/shop/fv-harvest.js` (per-record URL as a
`data-print-url-template` with `__ID__`; here the list JSON carries each
item's `print_url` instead, as the parked plan chose). Toasts, scan handling,
`post()` helper, `announceDone()` / `announceError()`: `labels.js`. Rows that
are buttons: `find-product.blade.php` line 85. `shop-switch` markup with the
app's `shop-switch__label` span. Sprite ids used: `tag`, `barcode`, `printer`,
`search`, `x`, `check`, `alert`, `info`, `minus`, `plus`, `chevron-right`.
`shop-seg--block`, `shop-page--narrow`, `shop-card--flat`, `shop-field`,
`shop-field__label`, `shop-btn--block`, `shop-notice`, `shop-hint`,
`shop-inline`, `shop-row__lead`, `shop-row__chev`, `shop-empty`, `shop-stepper`,
`shop-pill--ok|warn|muted` all exist in `resources/css/shop.css`.

**Alpine facts** (README rule 5): no `@error` / `@class` shorthands; `x-show`
still evaluates bindings, so nullable data behind it uses `?.` or an `x-if`;
compose with `mix()`; a handler on an element an `x-if` removes loses `$root`,
so the print card and the toast use `x-show`; focusing something just shown
waits a frame.

## Constraints

- Do not commit, push or deploy. The owner commits.
- Office pages are unchanged in behaviour: `zebra()` and `shelfLabels()` only
  delegate to the new service methods (same arrays, same keys, same order);
  `ZebraLabelController`, `LabelTranslationController`, `resources/views/labels/*`
  untouched.
- Employees never edit a saved label or a translation; overrides are per print
  through the existing `fields` parameter. "Fix" stays on the office page.
- Spool cancel is `labels.manage` (server-side already); the Shop button shows
  only to those users. Clear queue and its Undo are `labels.manage`.
- Shop view contract (`x-shop.*` components, `shop-*` classes, no `<style>` or
  `<script>` in views), design block byte-identical, URLs via `data-*` or the
  JSON (never `route()` in JS), new endpoints on the PIN allow-list.
- Printing a shelf sheet stays a form POST to `labels.print-a4` in a new tab.
- The queue has one entry per product; rows show ×1 (the design's ×n is only
  for Printed today items printed twice in one window).

## Out of scope

- Office tidy-up (developer-tool links, the two history pages), the Zebra
  label CRUD, the translate wizard, auto-print toggles, label templates.
- F&V labels (design screen 15) and the F&V tab nav.
- Per-job spool cancel; printer status beyond reachable / job count.
- Showing the *reason* (price changed, new product) on Printed today items:
  the `label_print` row does not carry it. Items show price and ×n only.
- Translated-label search on the server: the list JSON carries all of them
  (about 300 rows without ZPL, ~30 kB) and the module filters client-side.

## Steps

### 1. Labels tab nav partial
Files: `resources/views/shop/partials/labels-nav.blade.php` (new)
What: copy `fv-nav.blade.php`. `<nav class="shop-seg shop-seg--block" aria-label="Label type">`
with two `<a class="shop-seg__opt">`: `route('shop.labels')` with
`<x-shop.icon name="tag" size="sm" />` "Shelf labels", and `route('shop.zebra')`
with `<x-shop.icon name="barcode" size="sm" />` "Zebra labels"; `@if ($active === …) aria-current="page"@endif`
tight against the quote as in the F&V partial. Keep the comment explaining why.
Check: rendered by step 2 and step 6; `grep -c 'aria-current="page"' ` on each
page's HTML → 1.

### 2. Shelf tab: view and controller
Files: `resources/views/shop/labels.blade.php`, `app/Http/Controllers/Shop/LabelsController.php`
What, following `screen-07-shelf-labels.html` top to bottom:
- `<main class="shop-page shop-page--narrow" x-data="shopLabels()" …>` gains
  `data-printed-url="{{ route('labels.printed-today') }}"`,
  `data-requeue-url="{{ route('labels.requeue') }}"`,
  `data-restore-url="{{ $canClear ? route('labels.restore-batch') : '' }}"`.
  Keep `data-queue-url`, `data-scan-url`, `data-dismiss-url`, `data-clear-url`.
- `@include('shop.partials.labels-nav', ['active' => 'shelf'])` first, then the
  scan input (unchanged), then the queue section (unchanged markup; the ×1 stays).
- Remove the `shop-tiles--hub` nav and `$zebraUrl` from the controller.
- Empty state text: "All caught up" / "Every product has its shelf label. Scan a
  product to add one."
- **Printed today** after the queue section, `<section class="shop-list" aria-label="Printed today" x-show="batches.length" x-cloak>`:
  - header row `<button class="shop-row" type="button" :aria-expanded="historyOpen" @click="historyOpen = ! historyOpen">`
    with `shop-row__lead` (`printer` icon), `shop-row__main` (title "Printed today",
    meta `historyMeta`), and `<x-shop.icon name="chevron-right" class="shop-row__chev" />`
    with `:class="historyOpen && 'is-open'"` (if the icon component cannot take
    a class, wrap the icon in `<span class="shop-row__chev" :class=…>`; the
    rotation rule targets `.shop-row__chev.is-open`).
  - `<template x-if="historyOpen">` → `<template x-for="b in batches" :key="b.key">`:
    batch row `<button class="shop-row shop-row--batch" type="button" :aria-expanded="openBatch === b.key" @click="openBatch = openBatch === b.key ? null : b.key">`
    (title `b.time + ' · ' + plural(b.count, 'label')`, meta `plural(b.items.length, 'product')`, chevron);
    then `<template x-for="it in b.items" :key="it.key">` shown when
    `openBatch === b.key`: `<div class="shop-row shop-row--nested">` with
    title `it.name`, meta `it.meta`, and on the right either
    `<button class="shop-btn shop-btn--secondary shop-row__action" type="button" :disabled="busy" @click="requeue(b, it)">Back to queue</button>`
    (when `it.id && ! inQueue(it)`) or `<span class="shop-pill shop-pill--ok">In queue</span>`
    (when `inQueue(it)`); items with no product (`it.id === null`) show neither.
  - A batch row that is an `x-if` target: the handlers only touch component
    state (`openBatch`), never `$root`, so `x-if` is safe here; the nested item
    buttons call `requeue()`, which reads URLs through getters on `this`, not
    `$root` inside the removed element — confirm with the node exercise in step 3.
- The sticky `shop-actions` form stays as it is (Clear queue for `$canClear`,
  Print); `@submit` also reloads Printed today: `setTimeout(() => { load(); loadPrinted(); }, 1500)`.
- Toast: add `<button class="shop-btn shop-btn--ghost" type="button" x-show="toast?.undo" @click="undo()">Undo</button>`
  inside `.shop-toast` after the text.
Check: `php artisan test --filter=ShopViewContractTest` green;
`grep -c "labels.zebra\b" resources/views/shop/labels.blade.php` → 0;
`grep -c "labels-nav" resources/views/shop/labels.blade.php` → 1.

### 3. Shelf tab: module
Files: `resources/js/shop/labels.js`
What:
- `TOAST_MS = 4000`. State adds `batches = []`, `historyOpen = false`,
  `openBatch = null`, `day = 'today'`; getters `printedUrl`, `requeueUrl`,
  `restoreUrl` from `$root.dataset`.
- `init()`: `load()` and `loadPrinted()` (not awaited together).
- `loadPrinted()`: GET `printedUrl` → `batches = data.batches`, `day = data.day`;
  error → `batches = []` (the history is a convenience; never block the queue).
- `historyMeta` getter: `plural(batches.length, 'batch', 'batches') + ' · ' + plural(sum of b.count, 'label')`,
  prefixed "Yesterday · " when `day === 'yesterday'`.
- `inQueue(it)`: `rows.some(r => r.id === it.id)`.
- `requeue(b, it)`: `busy = true`; POST `requeueUrl` `{ product_id: it.id }`;
  on `success` → `await load()`, toast ok `it.name + ' is back in the queue'`;
  else toast bad `data.message || data.error || 'Could not re-queue'`.
- `dismiss(row)`: as now, but the ok toast carries an undo:
  `showToast('ok', 'Removed: ' + row.name, () => this.restore([row]))`.
- `clear()`: keep `ids` before posting; on success toast
  `showToast('ok', data.message || 'Queue cleared', ids.length && this.restoreUrl ? () => this.restoreBatch(ids) : null)`.
- `restore([row])`: POST `requeueUrl` `{ product_id: row.id }` then `load()`.
  `restoreBatch(ids)`: POST `restoreUrl` `{ timestamp: new Date().toISOString(), product_ids: ids }` then `load()`.
  Both toast bad on failure.
- `showToast(tone, text, undo = null)` stores `{ tone, text, undo }`; `undo()`
  clears the timer, hides the toast and runs the callback.
- `plural(n, one, many = one + 's')` helper.
Check: `node --check resources/js/shop/labels.js`; a node exercise with stubbed
`$root.dataset` and `fetch` prints: after `load()` + `loadPrinted()` with two
batches → `historyMeta` '2 batches · 5 labels'; `inQueue({id:'p1'})` true when
`rows` holds p1; `requeue()` posts `{product_id}` to the requeue URL and
reloads; `dismiss()` then `undo()` posts `{product_id}`; `clear()` then
`undo()` posts `{timestamp, product_ids}` to the restore URL;
`grep -c "route(" resources/js/shop/labels.js` → 0.

### 4. Printed-today JSON
Files: `app/Services/LabelQueueService.php`, `app/Http/Controllers/LabelAreaController.php`, `routes/web.php`
What:
- Move `groupLabelPrintsByTime()` into the service as
  `public function groupPrintsByWindow(Collection $prints): array` (body
  verbatim). Add `public function recentPrints(Carbon $since, int $limit = 200): Collection`
  with the query from `shelfLabels()` (with `product`, `label_print`, `>= $since`,
  newest first, limit). `shelfLabels()` becomes
  `$recentLabelPrints = $this->labelQueue->recentPrints(now()->subDays(7));
  $groupedLabelPrints = $this->labelQueue->groupPrintsByWindow($recentLabelPrints);`
  and the private method is deleted.
- `LabelAreaController::printedToday(): JsonResponse`: `$prints = recentPrints(today())`;
  if empty, `$prints = recentPrints(today()->subDay())` and `day = 'yesterday'`
  (when that is empty too, `day = 'today'` with no batches). Group, then map
  each window to `{ key: <group key>, time: timestamp->format('H:i'), at: ISO8601,
  count: <log rows in the window>, items: [...] }` where items are the window's
  rows collapsed per barcode in first-seen order:
  `{ key: barcode, id: product?->ID, code: barcode, name: product?->NAME ?? 'Product not found',
  price: product?->formatted_price_with_vat, copies: <rows for that barcode>,
  meta: price + (copies > 1 ? ' · ×' + copies : '') }` (`meta` computed server-side
  so the view has one string; `price` null → meta is just the ×n or ''). Return
  `{ day, batches }`.
- Route next to `labels.queue`: `Route::get('/labels/printed-today', [LabelAreaController::class, 'printedToday'])->name('labels.printed-today')->middleware('permission:labels.print');`
Check: `php artisan route:list --name=labels.printed-today` → `labels.print`;
`GET /labels/shelf-labels` as admin through the kernel → 200 before and after,
and `grep -c "groupLabelPrintsByTime" app/Http/Controllers/LabelAreaController.php` → 0;
step 9 pins the JSON.

### 5. Zebra list service and JSON
Files: `app/Services/ZebraLabelListService.php` (new), `app/Http/Controllers/LabelAreaController.php`, `routes/web.php`
What: parked plan steps 1–2, with the translated group added.
- `ZebraLabelListService::grouped(?string $search = null): array` returns
  `['onTill' => [...], 'other' => [...], 'standalone' => [...]]`, the
  non-translations branch of `zebra()` moved verbatim (including the
  `$countryNames` lookup and `TillVisibilityService`, injected). `zebra()`'s
  `else` branch becomes the three assignments from `$groups`. Inject the service
  into `LabelAreaController`'s constructor.
- `ZebraLabelListService::translated(): array`: every translation, newest per
  `product_code` (`ProductTranslation::orderByDesc('created_at')->orderByDesc('id')->get()->groupBy('product_code')->map->first()`;
  skip rows with empty `zpl_content`), joined to POS products in one
  `Product::whereIn('CODE', …)->get()->keyBy('CODE')` for the till name. Each:
  `['id', 'name' => label_data['product_name'] ?? product?->NAME ?? product_code,
  'product_code', 'label_size', 'width_mm' => config("label-sizes.$size.widthMm"),
  'height_mm' => …heightMm, 'auto_print' => (bool)]`, sorted by name.
- `LabelAreaController::zebraList(): JsonResponse` → `{ labels: [...] }`, one
  flat array in the order onTill, other, standalone, translated. Zebra items:
  `{ kind: 'zebra', group: 'till'|'other'|'standalone', id, name: product_name ?? name,
  label: name (null for standalone, where name is the title), product_code,
  width_mm, height_mm, default_copies (int, null → 1), mismatches (as computed or null),
  print_url: route('zebra-labels.print', $id) }`; `fields` dropped. Translated
  items: `{ kind: 'translated', group: 'translated', id, name, label: null,
  product_code, width_mm, height_mm, default_copies: 1, mismatches: null,
  auto_print, print_url: route('labels.translate.print', $id) }`. ZPL never
  appears in the JSON.
- Route next to `labels.zebra`:
  `Route::get('/labels/zebra/list', [LabelAreaController::class, 'zebraList'])->name('labels.zebra-list')->middleware('permission:labels.print');`
  (declare it **before** any `labels/zebra/{…}` route so "list" is not captured).
Check: `php artisan route:list --name=labels.zebra-list` → `labels.print`;
`GET /labels/zebra` as admin → 200 before and after, and the page still lists
the 17 dev labels; step 9 pins the JSON.

### 6. Zebra tab: route, controller, view
Files: `app/Http/Controllers/Shop/ZebraController.php` (new), `routes/web.php`, `resources/views/shop/zebra.blade.php` (new)
What:
- Route in the `shop` group after `labels`: `Route::get('/labels/zebra', [ZebraController::class, 'index'])->middleware('permission:labels.print')->name('zebra');`
  → `shop.zebra`. Controller: `view('shop.zebra', ['canCancel' => $request->user()->hasPermission('labels.manage')])`.
- View, `<x-shop-layout title="Print labels" :back="route('shop.home')">`, then
  `<main class="shop-page shop-page--narrow" x-data="shopZebra()" data-list-url="{{ route('labels.zebra-list') }}" data-queue-url="{{ route('labels.printer-queue') }}" data-cancel-url="{{ $canCancel ? route('labels.printer-cancel') : '' }}" @scan="onScan($event.detail.code)">`,
  following `screen-07b-zebra-labels.html` top to bottom:
  1. `@include('shop.partials.labels-nav', ['active' => 'zebra'])`.
  2. Printer line `<div class="shop-printer" :class="printerClass" role="status"><span x-text="printerText"></span>` and, inside it when `$canCancel`,
     `<button class="shop-btn shop-btn--ghost" type="button" x-show="printer?.ok && printer?.count > 0" x-cloak :disabled="busy" @click="cancelAll()"><x-shop.icon name="x" size="sm" />Cancel all</button>`.
  3. `<x-shop.scan-input placeholder="Scan a product to print its label" hint="Ready — scan a product, or search below" />`.
  4. `<div class="shop-search"><x-shop.icon name="search" /><input class="shop-input" type="search" placeholder="Search labels" aria-label="Search labels" x-model="query" autocomplete="off"></div>`.
  5. Print card `<section class="shop-card" aria-label="Print label" x-show="selected" x-cloak>`:
     header `<div class="shop-between shop-between--top">` with
     `<div class="shop-stack shop-stack--tight"><h2 class="shop-subtitle" x-text="selected?.name"></h2><span class="shop-row__meta" x-text="selectedMeta"></span></div>`
     and the ghost × `@click="close()"`; `<div class="shop-inline" x-show="selected?.auto_print" x-cloak><span class="shop-pill shop-pill--muted">Auto-prints on delivery</span></div>`;
     price block `<div class="shop-card shop-card--flat" x-show="selected?.mismatches?.price" x-cloak>`:
     `<div class="shop-between"><span class="shop-row__title" x-text="priceText"></span><span class="shop-pill shop-pill--warn">Price differs</span></div>`,
     `<label class="shop-switch"><input type="checkbox" x-model="useTillPrice"><span class="shop-switch__track"></span><span class="shop-switch__text"></span><span class="shop-switch__label">Print with till price</span></label>`,
     `<p class="shop-hint">For this print only. The saved label is fixed in the office.</p>`;
     the same block for country (`useTillCountry`, "Print with till country");
     `<p class="shop-notice"><x-shop.icon name="info" size="sm" /><span x-text="stockText"></span></p>`;
     `<p class="shop-notice" x-show="printer && ! printer.ok" x-cloak><x-shop.icon name="alert" size="sm" />Printer not reachable. Printing will fail until it is back.</p>`;
     `<div class="shop-field"><span class="shop-field__label">Copies</span>` + the harvest stepper (`bump(-1)` / `bump(1)`, `x-text="copies"`) `</div>`;
     `<button class="shop-btn shop-btn--primary shop-btn--lg shop-btn--block" type="button" :disabled="busy" @click="print()"><x-shop.icon name="printer" /><span x-text="printLabel"></span></button>`.
  6. Groups: `<template x-for="g in groups" :key="g.key">` → `<section class="shop-stack shop-stack--tight" x-show="g.rows.length">`
     with `<div class="shop-between"><h2 class="shop-subtitle" x-text="g.title"></h2><span class="shop-meta" x-text="g.count"></span></div>`,
     `<p class="shop-hint" x-show="g.hint" x-text="g.hint"></p>`, and
     `<div class="shop-list"><template x-for="r in g.rows" :key="r.kind + r.id"><button class="shop-row" type="button" :class="{ 'is-latest': isSelected(r) }" @click="open(r)"><span class="shop-row__main"><span class="shop-row__title" x-text="r.name"></span><span class="shop-row__meta" x-text="rowMeta(r)"></span></span><span class="shop-pill" :class="'shop-pill--' + pill(r).tone" x-show="pill(r)" x-text="pill(r)?.text"></span></button></template></div>`.
     Group hints: Other products "Not on the till right now."; Standalone labels
     "Not linked to a product, so a scan won't find them. Search or tap."
  7. Collapsed translated group, shown when there is no query:
     `<section class="shop-stack shop-stack--tight" x-show="! query.trim() && translatedCount" x-cloak>`
     header "Translated labels" / meta `translatedCount`, and one static
     `shop-row` (lead `search` icon, title "Scan or search to find one", meta
     `translatedCount + ' translated labels'`).
  8. `shop-empty` "No labels match" / "Try another name or code, or scan the product."
     (`barcode` icon) when `! loading && query.trim() && shown === 0 && ! error`;
     the error block as on the Shelf tab; the toast block as on the Shelf tab
     (no Undo here).
Check: `php artisan route:list --name=shop.zebra` present;
`php artisan test --filter=ShopViewContractTest` green (one more screen);
`grep -c "labels-nav" resources/views/shop/zebra.blade.php` → 1.

### 7. Zebra tab: module
Files: `resources/js/shop/zebra.js` (new), `resources/js/shop.js`
What: `Alpine.data('shopZebra', zebra)` registered in `shop.js`. Reads
`listUrl`, `queueUrl`, `cancelUrl` from `$root.dataset`; csrf from the meta tag;
`post()` / `showToast()` / `announceDone()` / `announceError()` as in `labels.js`
(compose with `mix()` if a shared part exists; otherwise copy).
State: `labels = []`, `query = ''`, `selected = null`, `copies = 1`,
`useTillPrice = true`, `useTillCountry = true`, `busy = false`, `loading = true`,
`error = null`, `printer = null` (`{ ok, count }`), `toast = null`.
- `init()`: `load()` then `loadPrinter()`, not awaited together (the spool call
  can take 15 s and must not delay the list).
- `load()`: GET `listUrl` → `labels`; error → `error = 'Could not load labels'`.
- `matches(l)`: query trimmed, lower-cased; true when empty or contained in
  `name`, `label` or `product_code` (null-safe).
- `groups` getter: `[{ key:'till', title:'On the till', hint:'' }, { key:'other', title:'Other products', hint:'Not on the till right now.' }, { key:'standalone', title:'Standalone labels', hint:'Not linked to a product, so a scan won’t find them. Search or tap.' }]`
  each with `rows = labels.filter(l => l.group === key && matches(l))` and
  `count = String(rows.length)`; when the query is non-empty, append
  `{ key:'translated', title:'Translated labels', hint:'', rows: translated matches, count: rows.length + ' of ' + translatedCount }`.
  `translatedCount` getter = `labels.filter(l => l.group === 'translated').length`;
  `shown` = sum of the groups' row counts.
- `onScan(code)`: first label with `product_code === code` (array order puts
  Zebra labels before translations) → `open(label)` + `shop-scan-done`; none →
  `shop-scan-error` "No Zebra label for this product" and `close()`.
- `open(l)`: `selected = l`, `copies = l.default_copies || 1`,
  `useTillPrice = useTillCountry = true`, scroll to top
  (`window.scrollTo({ top: 0, behavior: 'smooth' })` in a try).
  `close()`: `selected = null`. `isSelected(r)`: same `kind` and `id`.
- `bump(n)`: clamp 1–99. `printLabel` getter: `'Print ' + copies + (copies === 1 ? ' label' : ' labels')`.
- `rowMeta(r)`: size text `w × h mm` (one decimal where the value has one, as
  the JSON gives it); standalone → `'Label only · ' + size`; translated →
  `'Translated · ' + size + ' · ' + code`; else `[label, size, code].join(' · ')`.
  `selectedMeta` = `rowMeta(selected)` (null-safe).
- `pill(r)`: standalone → `{ tone:'muted', text:'No product' }`; translated →
  `auto_print ? { tone:'muted', text:'Auto-print' } : null`; price mismatch →
  `{ tone:'warn', text:'Price differs' }`; country → `'Country differs'`; both →
  `'Price + country differ'`; else `{ tone:'ok', text:'Matches till' }`.
- `priceText` / `countryText`: `'Label says €' + label_value + ', till says €' + db_value`
  (country without the € sign). `stockText`: `'Check the printer has ' + Math.round(w) + ' × ' + Math.round(h) + ' mm labels loaded'`.
- `overrides()`: `{}`; if `selected.mismatches?.price && useTillPrice` →
  `[price.field_index] = price.new_field`; same for country. Zebra items only.
- `print()`: `busy = true`; body `{ copies }` plus `fields: overrides()` when
  non-empty and `kind === 'zebra'`; POST `selected.print_url`; 2xx + `success`
  → toast ok `data.message`, `close()`, `loadPrinter()`, refocus the scan field
  (`shop-scan-saved`); otherwise toast bad `data.message || 'Print failed'`;
  `busy = false`.
- `loadPrinter()`: GET `queueUrl` → `printer = { ok: data.success, count: data.count }`;
  error → `{ ok: false, count: 0 }`.
- `printerClass`: `printer === null` → '' ; `! ok` → 'is-off'; `count === 0` → 'is-ok'; else 'is-busy'.
  `printerText`: 'Counter Zebra · checking printer…' / '… printer not reachable' /
  '… printer queue empty' / `'… ' + count + (count === 1 ? ' job' : ' jobs') + ' waiting'`.
- `cancelAll()`: only when `cancelUrl`; POST `{ all: true }` → toast
  `data.message`; then `loadPrinter()`.
Check: `node --check`; a node exercise with stubbed `$root.dataset` and `fetch`
prints: three Zebra + one translated label → groups till 1 / other 1 /
standalone 1 and the translated group absent; `query = 'hall'` → translated
group `1 of 1`, `shown` 1; `onScan('000000000102')` opens the kale label with
`copies` 2; `open(priceMismatch)` then `overrides()` → `{ "1": "\\152.79" }`,
`useTillPrice = false` → `{}`; a translated `print()` posts `{ copies }` only;
`bump(-5)` → 1, `bump(200)` → 99; `printerText` for `{ok:true,count:2}` →
'Counter Zebra · 2 jobs waiting'; `grep -c "route(" resources/js/shop/zebra.js` → 0.

### 8. CSS additions and the PIN allow-list
Files: `resources/css/shop.css`, `config/shop.php`
What: between the `APP ADDITIONS START` / `END` markers add, under a
`/* Labels (07 Shelf labels, 07b Zebra labels) */` comment, these rules from
the design project verbatim:
```css
.shop-printer { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; min-height: var(--shop-tap); padding: 0 4px; font-size: var(--shop-fs-sm); font-weight: var(--shop-fw-bold); color: var(--shop-ink-2); }
.shop-printer::before { content: ""; flex: none; width: 10px; height: 10px; border-radius: 50%; background: var(--shop-line-strong); }
.shop-printer.is-ok::before { background: var(--shop-ok); }
.shop-printer.is-busy::before { background: var(--shop-warn); }
.shop-printer.is-off::before { background: var(--shop-muted); border-radius: 2px; }
.shop-printer > .shop-btn { margin-left: auto; }
.shop-row__chev { transition: transform var(--shop-dur) var(--shop-ease); }
.shop-row__chev.is-open { transform: rotate(90deg); }
.shop-row--batch { min-height: 64px; }
.shop-row--nested { padding-left: calc(var(--shop-space-4) + 20px); background: var(--shop-bg); }
.shop-row__action { flex: none; padding: 0 18px; }
@media (max-width: 419px) { .shop-row__action { padding: 0 14px; font-size: var(--shop-fs-sm); } }
.shop-between--top { flex-wrap: nowrap; align-items: flex-start; }
```
and replace the existing `.shop-switch__label { font-weight: var(--shop-fw-bold); }`
(line 710) with the design's
`.shop-switch__label { font-size: var(--shop-fs-sm); font-weight: var(--shop-fw-bold); color: var(--shop-ink); }`.
Everything stays scoped under `.shop` by the file's existing nesting; if the
additions block is not nested, prefix each selector with `.shop `.
In `config('shop.pin_session_routes')` add, next to the `labels.*` entries:
`labels.printed-today`, `labels.requeue`, `labels.restore-batch`,
`labels.zebra-list`, `labels.printer-queue`, `labels.printer-cancel`,
`labels.translate.print` (with a one-line comment: Shop labels tabs; the
manage-gated ones keep their own permission).
Check: `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css` → identical;
`npm run build` succeeds; `php artisan test --filter=ConfinePinSessionTest` green.

### 9. Tests
Files: `tests/Feature/Shop/ShopLabelsTest.php`, `tests/Feature/Shop/ShopZebraTest.php` (new; look first, extend if it exists)
What:
- `ShopLabelsTest`: `test_employee_can_open_the_labels_screen` now asserts the
  nav (`href="…shop.labels" aria-current="page"` and `href="…shop.zebra"`),
  `data-printed-url`, `data-requeue-url`, `data-restore-url=""` for an
  employee, and **not** `route('labels.zebra')`. `test_manager_sees_clear_queue`
  also asserts `data-restore-url="…labels.restore-batch"`. New:
  `test_printed_today_groups_prints_in_five_minute_windows` (three `label_print`
  rows today: p1 at 14:02 and 14:04, p2 at 14:03 → one batch, `count` 3,
  items p1 `copies` 2 then p2; a row at 09:00 → a second, older batch; a print
  for an unknown barcode → `id` null, name "Product not found");
  `test_printed_today_falls_back_to_yesterday` (`day` 'yesterday' when today
  has none); `test_requeue_puts_a_printed_product_back_on_the_queue` (print,
  then POST `labels.requeue` → queue lists it);
  `test_restore_batch_is_for_managers` (employee 403, manager 200 and both
  products back on the queue); `test_printed_today_is_forbidden_for_baristas`.
- `ShopZebraTest`: the parked plan's step 7 fixture and tests, renamed to the
  real data where it helps (Curly Kale 200g on `000000000102`, PRICESELL
  2.2683 at 23 % → gross 2.79, ZPL price `\152.49`, `default_copies` 2, on the
  till; Rocket Mossfield on `000000000329` with a matching price, not on the
  till; "parsnip" standalone), plus: a `ProductTranslation` for a third product
  (`label_data.product_name` "Dynamis Halloumi 250 g", `label_size` 'large',
  `auto_print` true, a short ZPL with `^PQ1`). Tests:
  `employee_can_open_the_zebra_screen` (200, nav, `data-list-url`, scan input,
  "Copies", no "Cancel all", `data-cancel-url=""`); `manager_sees_cancel_all`;
  `barista_is_forbidden` (screen and `labels.zebra-list`);
  `list_groups_labels_reports_mismatches_and_includes_translations` (four
  items in order; kale `group` 'till', `mismatches.price.db_value` '2.79',
  `new_field` '\152.79', `print_url` = `route('zebra-labels.print', $kale)`;
  rocket 'other', `mismatches` null; parsnip 'standalone', `product_code` null;
  halloumi `kind` 'translated', `width_mm` 75, `auto_print` true, `print_url` =
  `route('labels.translate.print', $t)`; no item has `zpl_content`);
  `office_page_and_list_agree`; `print_with_till_price_overrides_this_print_only`;
  `print_without_override_sends_the_saved_label`;
  `translated_print_sends_the_translation_with_copies` (POST `labels.translate.print`
  `{ copies: 3 }` → captured ZPL has `^PQ3`); `office_shelf_page_still_renders`
  (manager GET `labels.shelf-labels` 200 with a printed product's name).
Check: `php artisan test --filter="ShopLabelsTest|ShopZebraTest|ShopViewContractTest|ConfinePinSessionTest"` green.

### 10. Docs, format, build
Files: `docs/design/shop-mode/README.md`, `docs/features/shop-mode.md`, `docs/shop_new/README.md`, all touched
What: README bullet for Print labels: the two tabs, Printed today from
`labels.printed-today` (today's `label_print` rows in five-minute windows,
yesterday when today is empty), Back to queue and the two Undos via
`labels.requeue` / `labels.restore-batch`, the Zebra tab from
`labels.zebra-list` (three Zebra groups plus translations, mismatches,
per-item `print_url`), per-print `fields` overrides that never edit the saved
label, the spool via `labels.printer-queue` after render, "Cancel all" for
`labels.manage`. `docs/features/shop-mode.md`: a short "Print labels" subsection
under "The shell" beside the deliveries ones, same facts. `docs/shop_new/README.md`:
"Screens today" line gains Zebra labels; nothing else (the Planner updates
"Where things stand" on acceptance). `./vendor/bin/pint --dirty`; `npm run build`.
Check: `./vendor/bin/pint --test --dirty` clean; build succeeds.

## Verification

1. `php artisan route:list --name=shop.zebra`, `--name=labels.zebra-list`,
   `--name=labels.printed-today` → present, `labels.print`.
2. `php artisan test tests/Feature/Shop` → green; the contract test covers one
   more screen than before.
3. `php artisan test` → **16 failed**, the identical set.
4. `git diff --stat app/Http/Controllers/ZebraLabelController.php app/Http/Controllers/LabelTranslationController.php resources/views/labels/` → empty;
   `git diff app/Http/Controllers/LabelAreaController.php` shows only the two
   delegations, `zebraList()` and `printedToday()`.
5. `grep -c "route(" resources/js/shop/labels.js resources/js/shop/zebra.js` → 0 0;
   design-block `cmp` → identical; `cmp public/images/shop-icons.svg docs/design/shop-mode/shop-icons.svg` unchanged.
6. `./vendor/bin/pint --test --dirty` clean; `npm run build` succeeds.
7. Browser on dev (`osmanager.local`, hidden-tab caveats in the README; prefer
   reading component state), as `katelyn` (PIN 2580) and as `test`:
   Shelf tab → queue renders, Printed today collapsed with today's batches
   (fake a print first: POST `labels.print-a4` for one product), expand → batch
   → item → Back to queue → the product reappears in the queue and the row
   shows "In queue"; Remove → toast with Undo → Undo puts it back; as `test`,
   Clear queue → Undo restores all; the sticky bar sits at the bottom of the
   screen at 390 px. Zebra tab → printer line settles (dev prints to
   `127.0.0.1`, so expect "printer not reachable" and the card's notice);
   search filters; a translated group appears only with a query; tap a row →
   card at the top with the right meta and pill; copies clamp; Print → the
   toast shows the server message; scan `000000000102` (type it and press
   Enter in the scan field) → the kale card with copies preset.
   Console clean on both tabs.
8. Owner, on production with the printer on: Zebra tab → scan a bagged
   produce label → print 1 copy with the till price → the label prints and the
   office label is unchanged; a translated label prints from search; "Cancel
   all" clears a paused spool as a manager.

## Risks

- **Printer calls block a PHP worker** for up to 15 s each; the list and the
  spool are separate requests so the screen renders when the printer is down.
- **`replaceTextFields` indexes** are positional over fields with a font; the
  JSON carries the index computed from the same ZPL, so the override lands on
  the right field unless a manager edits the label between list and print.
- **Printed today item `copies`** collapses rows per barcode in a window; a
  product printed in two different windows appears in both batches, as the
  office shows it.
- **Undo after Clear** re-queues every cleared product with a `requeue_label`
  event each, which the office history also reflects; acceptable, same as
  "Restore All" there.
- **`ProductTranslation` rows without a product** on the POS still list (name
  from `label_data`), and their print works; only the till name is missing.
- **`x-if` around the batch rows** removes the chevron state when the history
  collapses; `openBatch` is kept in component state so reopening restores it.

## Review

Reviewed 2026-10-21 by the Planner against `implemented.md` (read to the end)
and the working-tree diff. Every verification command was rerun here.

### Criteria

| # | Criterion | Result |
|---|-----------|--------|
| 1 | `labels-nav` partial, `shop-seg shop-seg--block`, `tag` / `barcode`, `aria-current` tight | pass (partial read; asserted in both screen tests) |
| 2 | Shelf tab: nav, no hub tiles, no `$zebraUrl`, three `data-*` URLs, Printed today, Undo in toast | pass (diff read; `labels.zebra` gone from the view) |
| 3 | `labels.js`: `TOAST_MS` 4000, `loadPrinted`, `historyMeta`, `inQueue`, `requeue`, undo on Remove and Clear | pass; `grep -c "route("` → 0 |
| 4 | `LabelQueueService::recentPrints()` + `groupPrintsByWindow()` verbatim; `shelfLabels()` delegates; `printedToday()` JSON; route on `labels.print` | pass (grouping body is byte-for-byte the old private method; office page byte-identical through the kernel per the report) |
| 5 | `ZebraLabelListService::grouped()` verbatim from `zebra()`, `translated()`; `zebraList()` flat list with `kind`/`group`/`print_url`, no ZPL; route before `labels/zebra/{…}` | pass (service read; `fields` and `zpl_content` never in the JSON, asserted) |
| 6 | `shop.zebra` route on `labels.print`, `ZebraController`, `zebra.blade.php` with the design's zones in order | pass (view read: printer line, scan, search, card with switches / notices / stepper / block Print, groups, collapsed Translated, empty, error, toasts) |
| 7 | `zebra.js` behaviour spec | pass (module read against the design's `x-dc` spec: groups, scan, open/close, bump clamp 1–99, pills, overrides, print with `fields`, printer line states, cancel all); `route(` → 0 |
| 8 | 13 new CSS rules + `.shop-switch__label` replaced, inside the APP ADDITIONS block; seven allow-list entries | pass; design block `cmp` → identical; `ConfinePinSessionTest` green |
| 9 | `ShopLabelsTest` extended, `ShopZebraTest` new with real-data fixture and faked printer | pass; `tests/Feature/Shop` → 352 tests, 0 failed |
| 10 | Docs, `pint --test --dirty`, `npm run build` | pass (PASS 9 files; built) |
| V3 | Full suite = the identical 16 | pass: `Tests: 1074 deprecated, 16 failed, 43 passed`; `UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2, `TestScraperControllerTest` ×1, `BackupConfigurationTest` ×1 |
| V4 | Office controllers and views untouched beyond delegation | pass (`LabelAreaController` hunks are the two delegations, `zebraList()`, `printedToday()`, the constructor and a pint import removal) |
| V7 | Browser pass on dev | done by the Implementer with real clicks on both tabs; two real bugs found and fixed (Deviations 1–3) |
| V8 | Owner print on production | **outstanding**, owner's step after deploy (see below) |

### Deviations

1. `x-show="!! toast?.undo"` — **accepted**. Alpine calls a function-valued
   expression; the plan's binding was wrong. Fact recorded in `README.md`
   rule 5 and `planimp.md`.
2. Undo callbacks take the component (`(c) => c.restore([row])`) — **accepted**.
   The plan's closure over `this` lived in the x-for row that Remove deletes.
   Recorded with 1.
3. Same-second tie-break in `LabelQueueService::candidates()` — **accepted**.
   `created_at` is to the second and ids are autoincrement, so on an exact tie
   the higher id *is* the later event; the old `<=` silently swallowed a
   requeue tapped within a second of a dismissal, which is exactly the Undo
   case. Strictly earlier or later timestamps behave as before, the query
   count is unchanged (`test_queue_derivation_uses_a_fixed_number_of_queries`
   passes), and the full suite shows no office regression. The new test pins
   it.
4. `display: contents` wrappers in Printed today and the extra separator rule
   — **accepted**. Alpine templates need one root; the rule sits in the
   additions block, prefixed and commented.
5. `x-bind:class` on `<x-shop.icon>` instead of a wrapping span — **accepted**;
   the icon component spreads `$attributes` onto the svg.
6. `Math.round` → "113 × 75 mm" — **accepted**; the design sample was
   illustrative and the real label is 112.6 mm.
7. Translated list sorted by name; newest-without-ZPL skipped — **accepted**;
   that is `ProductTranslation::latestPerProductCode()`'s rule and the test
   pins it.
8. Yesterday fallback reads from yesterday's midnight (spanning today) —
   **accepted**; only reached when today is empty, commented in code.

### Notes for Planner

- Deviation 3 decision: accepted, above.
- Two Alpine facts: **fixed now** in `docs/shop_new/README.md` rule 5 and
  `planimp.md` (Planner edits, docs only).
- `.shop-switch__label` now the design's rule, so "Show not ordered" on Order
  review turns from muted to ink — **accepted**, no change: it is the design
  system's rule and the label is a control, not meta. Owner: glance at Order
  review on the next browser pass.
- Dev `127.0.0.1` answers `lpstat` but `lp` fails — **noted, no change**; the
  plan's "expect not reachable on dev" was this Planner's guess about one
  machine, and the Implementer exercised both the "queue empty" line and the
  failed-print toast anyway.
- No mismatching Zebra label on dev — **deferred to V8** (owner, production):
  scan a bagged produce label whose price has changed, print with the till
  price, check the office label is unchanged.
- 297 of 301 translations listed — informational, correct per Deviation 7.
- Unknown-product print items have a blank meta line — **accepted**; there is
  nothing to show and the name already says "Product not found".
- Rows put back show reason "Re-queued" — **accepted**, as the plan said.

### Decision

**ACCEPTED.** Nothing to redo. Owner: run V8 on production after deploy; if
the Zebra print or Cancel all misbehaves there, write it up as
`docs/shop_new/findings/YYYY-MM-DD-zebra-production.md` for the next cycle.
Then archive: `mkdir -p docs/shop_new/archive/2026-10-21-labels` and move
`plan.md` and `implemented.md` there.
