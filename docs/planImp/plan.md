# Plan: Shop mode cycle 23 — Delivery scan v2 (Screen 05 v2)

**Status:** READY
**Planner:** Fable 5.1
**Date:** 2026-09-26

## Goal

Rebuild the Shop delivery scan page (`/shop/deliveries/scan`) to the updated Claude Design "Screen 05 Delivery scan v2": a two-line top bar (supplier over session id and date), a compact inline scan field with the camera/keyboard toggles inside it, a plain status block instead of the Progress card, a ghost sort toggle, and rows that are tappable buttons showing name, code, **current stock**, `scanned / expected` and a pill only where there is something to say. The two-step scan prompt and the quantity corrections stay; corrections move from a per-row stepper to a card that opens when a row is tapped, because the design's rows are buttons and a button cannot hold buttons.

Screen 06 (Delivery summary) is unchanged in the design apart from linking to this screen. Nothing on the summary page changes in this cycle.

## Context (verified 2026-09-26)

- Design copies (Planner-updated today, both editable only by the Planner):
  - `docs/design/shop-mode/screen-05-delivery-scan-v2.html` — the new screen. It shows both states (no invoice lines / invoice loaded) one after the other; the app renders one or the other.
  - `docs/design/shop-mode/shop.css` — now 553 lines. Diff against the previous copy: `.shop-topbar__title` line-height 1.15 → 1.35, and 24 added lines (`.shop-topbar__titles`, `.shop-topbar__sub`, `.shop-scan--inline` rules, `.shop .shop-scan__input` reset, `.shop-status`, `.shop-notice`, `.shop-item`, `.shop-row__stock`).
- `resources/css/shop.css` (575 lines): design block = lines 1–530 (the *old* design file), then `/* === APP ADDITIONS START === */` at line 531 to `END` at 575. The design block is currently out of date; the contract check `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css` fails at byte 8908.
- App additions include `.shop-row--wrap` and `.shop-row__controls` (lines ~559–564), used only by `resources/views/shop/delivery-scan.blade.php` and pinned by `tests/Feature/Shop/ShopDeliveryTest.php` lines 187–188. Also `.shop-scan__mount` pinned-absolute rules (keep; the camera mount must stay pinned in the inline variant).
- `public/images/shop-icons.svg`: symbols include `sort`, `alert`, `pencil`, `minus`, `plus`, `check`, `x`, `list-checks`, `more`, `phone`. There is **no `info`** symbol; the design's notice uses an info circle.
- `resources/views/components/shop/topbar.blade.php`: props `title`, `back`, `guestSafe`; renders `<h1 class="shop-topbar__title">` directly after the back button. `app/View/Components/ShopLayout.php` props `title`, `back`, `guestSafe`, `bare`, `guestRefresh`; `resources/views/layouts/shop.blade.php` line 30 passes `:title :back :guest-safe` to the topbar and line 14 uses `$title` in `<title>`.
- `resources/views/components/shop/scan-input.blade.php`: props `placeholder`, `hint`, `camera`; markup = `.shop-scan` root (`x-data="shopScanInput()"`), `label.shop-scan__field` (icon, sr-only, input), `div.shop-scan__tools` (camera + keyboard buttons, `shop-touch-only`, `:aria-pressed`), `p.shop-scan__hint`, `p.shop-scan__msg`, `div.shop-scan__camera` (mount + reticle + label). `resources/js/shop/scan-input.js` needs no change.
- `resources/views/shop/delivery-scan.blade.php` (current): layout title `supplier · id8`, back = `route('shop.deliveries')`; root `main.shop-page x-data="shopDeliveryScan()"` with `data-items-url`, `data-scan-url`, `data-update-url`, `data-del-id`, `data-supplier-id`; `x-shop.scan-input` with `@scan-empty="pending && commit()"`; prompt `section.shop-card x-show="pending" x-ref="prompt"` (facts: so far / on invoice / in stock, case pill, stepper `bump(±1)`, Add button `commit()`, cancel `cancelPending()`); Progress card; error empty state; right column with `shop-seg` sort radios, no-invoice `shop-meta`, rows `div.shop-row.shop-row--wrap` with `shop-row__controls` (aside qty + pill, `shop-qty` stepper `x-show="editing === row.barcode"`, pencil `edit(row)`), empty state; actions bar with Deliveries + Summary links; toasts.
- `resources/js/shop/delivery-scan.js`: state `session, rows, progress{total,checked,issues}, sort ('new'|'scanned'), recent[], latest, pending, busy, editing, toast, flag, error`; getters `hasInvoice`, `percent`, `unitsToAdd`, `addLabel`, `sorted`; methods `load, onScan(code), bump, commit, cancelPending, reportRow, edit(row) (toggles editing), adjust(row, delta) (PATCHes an absolute quantity then reloads), post, qtyLabel, expectedLabel(row), pill(row) (returns OK/Short/Over/Unexpected/Not scanned), showToast, announceDone/Error`.
- `app/Http/Controllers/DeliveryLegacyController::items()` (~line 880): builds rows from `getMatchedItems()` (invoice lines, fields incl. `Barcode, supCode, dbProductName/prodName, myOrder, invoiceCaseUnits, scanned, productID, UNITS`) and `getScannedNotOnInvoice()` (fields incl. `Barcode, SupplierCode, NAME, scanned, productID, UNITS`). **Both queries already select `STOCKCURRENT.UNITS`**, but the JSON rows carry only `barcode, code, name, expected, scanned, stockable, status`. Response: `session{id,supplier,date,completed}`, `rows`, `progress{total,checked,issues}`.
- `app/Http/Controllers/Shop/DeliveryController::scan()` passes `['session' => …]` with `id, supplierId, supplier, date (dateUpload), completed`.
- Tests: `tests/Feature/Shop/ShopDeliveryTest.php` — `test_items_endpoint_classifies_the_session` (line 139, seeds STOCKCURRENT at ~line 71 and asserts `stockable`), scan-page assertions at lines 177–188 (`shop-scan__input`, `New first`, deliveries href, `class="shop-row shop-row--wrap"`, `class="shop-row__controls"`), 205–206 (completed session hides the input), 233–237 (prompt: `Quantity to add`, `commit()`, `cancelPending()`, `bump(1)`, `bump(-1)`), 297–303 (Summary href/text, `@scan-empty="pending && commit()"`, no `keydown.enter.window`). Contract test `tests/Feature/Shop/ShopViewContractTest.php` (components/classes/CSS byte check).
- Baseline: `php artisan test` → 15 failed / 662 passed (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2, TestScraper ×1). Those 15 are pre-existing and unrelated.

## Constraints

- No commits, no deploys. Do not edit `docs/design/shop-mode/**` (Planner-owned).
- Shop view contract: views use only `x-shop.*` components and `shop-*` classes; behaviour in `resources/js/shop/*.js`; URLs via `data-*`; no Alpine `@`-shorthands that are Blade directives; `x-show` still evaluates bindings (use `?.` or `x-if`).
- The design block of `resources/css/shop.css` must be byte-identical to `docs/design/shop-mode/shop.css`; app rules only inside the APP ADDITIONS block.
- Other pages using `x-shop.scan-input` (stock scan, find product, labels, vouchers) keep the current (non-inline) markup unchanged. Other pages using `x-shop.topbar` keep the current single-title markup unchanged.
- The two-step scan (prompt then Add) and the case-scan handling stay exactly as they are.

## Out of scope

- Product pictures on delivery rows (later cycle).
- Any change to the summary page or a "note for the office" (no backend for it).
- Applying the inline scan variant to other screens.
- The PIN / switch-user screens.

## Steps

### 1. Refresh the design block of `resources/css/shop.css`
Replace lines 1–530 (everything before `/* === APP ADDITIONS START ===`) with the full contents of `docs/design/shop-mode/shop.css`, keeping the APP ADDITIONS block exactly as it is for now (step 6 trims it).
**Check:** `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css` prints nothing; `grep -c "APP ADDITIONS START" resources/css/shop.css` → 1.

### 2. Add an `info` icon to the sprite
Append to `public/images/shop-icons.svg`, beside the app-added `more` and `phone` symbols, a symbol `id="info"` with the design's paths: `<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>` (same `viewBox`/stroke attributes as the neighbours).
**Check:** `grep -c 'id="info"' public/images/shop-icons.svg` → 1; `<x-shop.icon name="info" size="sm" />` renders.

### 3. Topbar subtitle
- `app/View/Components/ShopLayout.php`: add `public ?string $subtitle = null` (doc comment: second line under the title, e.g. a session id and date).
- `resources/views/layouts/shop.blade.php` line 30: pass `:subtitle="$subtitle"`.
- `resources/views/components/shop/topbar.blade.php`: add prop `'subtitle' => null`. In the `@else` branch (back button present), when `$subtitle` is set render
  ```html
  <div class="shop-topbar__titles"><h1 class="shop-topbar__title">{{ $title }}</h1><span class="shop-topbar__sub shop-code">{{ $subtitle }}</span></div>
  ```
  otherwise the existing bare `<h1>` (unchanged markup so other pages' tests still pass).
**Check:** a page with `subtitle` shows both lines; `grep -rn "shop-topbar__title" tests/` still green after the suite.

### 4. Inline scan-input variant
`resources/views/components/shop/scan-input.blade.php`: add prop `'inline' => false`. When `inline`:
- root class `shop-scan shop-scan--inline` (same `x-data`, `:class`, and window listeners as now);
- the camera and keyboard buttons (same attributes, `shop-touch-only`, `:aria-pressed`, handlers) move **inside** `label.shop-scan__field`, after the input, and the `div.shop-scan__tools` wrapper is not rendered;
- no `p.shop-scan__hint` (the design's inline field has none);
- keep `p.shop-scan__msg` and `div.shop-scan__camera` (mount, reticle, label) exactly as they are — they are `grid-column: 1 / -1` and the inline grid has one column, so they fill the width; the app-added `.shop-scan__mount` pinned rules still apply.
When not `inline`, the output is byte-for-byte what it is today.
**Check:** render the delivery scan page with `is-touch` forced (DevTools device toolbar or `document.getElementById('shop-root').classList.add('is-touch')`): the two toggles sit inside the field at 52 px; tapping the camera toggle opens the camera beneath the field with the reticle visible and `aria-pressed="true"` tinting the button; typing a code + Enter still dispatches `scan`.

### 5. Stock in the items JSON
`DeliveryLegacyController::items()`: add `'stock' => $item->UNITS === null ? null : (float) $item->UNITS` to both the invoice-line rows and the scanned-not-on-invoice rows. No query change (both already select `STOCKCURRENT.UNITS`).
**Check:** extend `test_items_endpoint_classifies_the_session` in `ShopDeliveryTest`: the row whose STOCKCURRENT is seeded returns that figure as `stock`; the unresolved-product row (`4260009912200`, `stockable` false) returns `stock` null.

### 6. Rebuild `resources/views/shop/delivery-scan.blade.php` to Screen 05 v2
Keep the root `main.shop-page` and all `data-*` attributes, the `x-shop.scan-input` `@scan` / `@scan-empty="pending && commit()"` wiring, the prompt card (unchanged, including `x-ref="prompt"`, `Quantity to add`, `bump(±1)`, `commit()`, `cancelPending()`), the completed-session branch (no scan input, "This delivery is completed"), the error empty state and the toasts. Change:

- **Layout:** `<x-shop-layout :title="$session['supplier']" :subtitle="$subtitle" :back="route('shop.deliveries')">` where the subtitle is built in the view's `@php` (or in `DeliveryController::scan()` as `$session['when']`) as `{id} · {date}` with the date rendered `today` when `Carbon::parse($session['date'])->isToday()`, otherwise `D j M` (e.g. `d1-20931 · today`, `d1-20931 · Thu 24 Sep`). The `<title>` keeps the supplier only.
- **Scan field:** `<x-shop.scan-input inline placeholder="Scan item" … />`.
- **Status block** replaces the Progress card, directly under the scan field in the left `shop-stack`:
  ```html
  <p class="shop-notice" x-show="! hasInvoice" x-cloak><x-shop.icon name="info" size="sm" />No invoice lines, so quantities can't be checked.</p>
  <div class="shop-status" x-show="hasInvoice" x-cloak>
      <div class="shop-progress-meta"><span><strong x-text="progress.checked"></strong> of <span x-text="progress.total"></span> lines checked</span><span x-text="progress.issues + (progress.issues === 1 ? ' issue' : ' issues')"></span></div>
      <div class="shop-progress" :class="{ 'is-done': …, 'is-issue': … }" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percent"><span class="shop-progress__bar" :style="'width:' + percent + '%'"></span></div>
      <span class="shop-pill shop-pill--sage" x-show="flag" x-cloak x-text="flag"></span>
  </div>
  ```
  (`shop-progress`, not `--lg`; the put-aside `flag` pill stays, inside the status block.) Drop the old "No invoice lines are loaded…" `shop-meta` and the Progress `h2`.
- **Correction card** (new, left column, after the status block, `x-show="editing && ! pending"` with `x-ref="correct"`): `shop-card` with a `shop-between` header (`h2.shop-subtitle` "Correct quantity", ghost `x` icon button `@click="editing = null"` aria-label "Close"), the product name and code (`shop-meta`, from the `editingRow` getter, `?.`-guarded), the stock line when known, a `shop-stepper` (`adjust(editingRow, -1)` / `adjust(editingRow, 1)`, `:disabled="busy"`, `output.shop-stepper__value x-text="editingRow?.scanned ?? 0"`) and a `shop-btn--primary shop-btn--block` "Done" button (`@click="editing = null"`). Each tap still saves immediately, as today.
- **Items header:** `div.shop-between` with `<h2 class="shop-group-title">Items <small x-text="rows.length"></small></h2>` and `<button class="shop-btn shop-btn--ghost" type="button" @click="toggleSort()"><x-shop.icon name="sort" size="sm" /><span x-text="sort === 'new' ? 'New first' : 'Scanned first'"></span></button>` — the label names the order in force; tapping flips it. (The literal `New first` must remain in the rendered HTML: the test asserts it.)
- **Rows:** inside `template x-for="row in sorted" :key="row.barcode"`:
  ```html
  <button class="shop-row shop-item" type="button"
          :class="{ 'is-latest': row.barcode === latest, 'is-off': row.status === 'not_scanned' }"
          :aria-pressed="editing === row.barcode" @click="edit(row)">
      <span class="shop-row__main">
          <span class="shop-row__title" x-text="row.name"></span>
          <span class="shop-row__meta"><span class="shop-code" x-text="row.code || row.barcode"></span><span class="shop-row__stock" x-show="row.stock !== null" x-text="'Stock ' + stockText(row)"></span></span>
      </span>
      <span class="shop-row__aside">
          <span class="shop-row__qty"><span x-text="row.scanned ?? 0"></span><small x-show="hasInvoice" x-text="expectedLabel(row)"></small></span>
          <span class="shop-pill" x-show="pill(row)" :class="pill(row)?.tone" x-text="pill(row)?.text"></span>
      </span>
  </button>
  ```
  Title above meta (the design's order; today's view has them reversed). `expectedLabel` keeps its ` / N` / ` / —` text. No per-row stepper or pencil: the row itself opens the correction card.
- **Empty state** (`x-show="! rows.length"`): title "Nothing scanned yet", text "Scan the first item." (with no invoice, rows only exist once something is scanned; the notice above already explains the missing invoice).
- **Actions bar:** only the Summary link (`shop-btn--primary shop-btn--lg`, `shop-btn__count` with `progress.issues`, shown when > 0). The "Deliveries" secondary button goes; the top-bar back button covers it (test line 184 checks the deliveries href, which the back button provides).
- Remove `.shop-row--wrap` / `.shop-row__controls` and their comment from the APP ADDITIONS block of `resources/css/shop.css` (grep first: `grep -rn "shop-row--wrap\|shop-row__controls" resources/ tests/` must list only this view and its test before you delete).
**Check:** contract test green; view renders both invoice states (use the two seeded sessions in `ShopDeliveryTest`, or seed one supplier with no `delivery` lines).

### 7. `resources/js/shop/delivery-scan.js`
- `toggleSort()` → `this.sort = this.sort === 'new' ? 'scanned' : 'new'`.
- `get editingRow()` → `this.rows.find((r) => r.barcode === this.editing) ?? null`.
- `edit(row)`: unchanged toggle semantics, plus scroll `this.$refs.correct` into view (`{ block: 'nearest' }`) after `$nextTick` when opening, mirroring what the prompt does.
- `onScan()`: set `this.editing = null` before the lookup, so a scan closes an open correction card.
- `pill(row)`: return `null` for `'ok'` (OK rows carry no pill in the design); when `! this.hasInvoice` return `null` for `'unexpected'` too (state A shows plain counts). Keep Short / Over / Unexpected / Not scanned as they are.
- `stockText(row)` → whole numbers without decimals, otherwise up to 3 dp trimmed (STOCKCURRENT.UNITS is decimal). Bare `row.stock` must not be printed as `4.0`.
- Remove `qtyLabel()` if nothing else uses it (grep).
- Update the file header comment: rows are buttons opening a correction card; sort is a toggle.
**Check:** `npm run build` clean; in the browser (see Verification) each behaviour is exercised, not just rendered.

### 8. Tests — `tests/Feature/Shop/ShopDeliveryTest.php`
- Replace the assertions at lines 187–188 with: `class="shop-row shop-item"`, `shop-row__stock`, `shop-scan--inline`, `shop-topbar__sub`, `shop-notice`, `shop-status`, `toggleSort()`, `Correct quantity`, `adjust(editingRow, 1)`; assert `shop-row--wrap` and `shop-row__controls` are gone (`assertDontSee`, `false`).
- Keep the existing `New first`, prompt, `@scan-empty`, Summary and completed-session assertions.
- Add the subtitle assertion: the scan page shows `<span class="shop-topbar__sub shop-code">d-1 · ` (the fixture's session id) and, for a session dated today, `today`.
- Step 5's `stock` assertions.
- A topbar test (in `ShopHomeTest` or a small new `ShopTopbarTest`): a page without a subtitle still renders `<h1 class="shop-topbar__title">` directly, not inside `shop-topbar__titles`.
**Check:** `php artisan test --filter=Shop` green; full suite = baseline 15 failures only.

### 9. Format and tidy
`./vendor/bin/pint --dirty`; `npm run build`; `php artisan view:clear`.

## Verification (report every item with what you saw)

1. `php artisan test` → the same 15 pre-existing failures, nothing new. Paste the summary line.
2. Contract check: the `cmp` command from Constraints prints nothing.
3. Browser, in the Shop (`/shop/deliveries` → an open session with invoice lines), at desktop width and at 390 px:
   - Top bar shows the supplier on line 1 and `id · today` (or the date) on line 2 without wrapping.
   - Type a real barcode from that invoice + Enter → prompt opens → Add → row jumps to the top with `is-latest`, shows `Stock N`, `scanned / expected`, no pill when it matches, `Short` pill when under.
   - Tap a row → correction card opens (scrolled into view on the phone width); `+` and `−` each save (network tab shows the PATCH) and the row updates; Done closes it; scanning while it is open closes it.
   - Tap the sort button: label flips between `New first` and `Scanned first` and the list reorders.
   - Summary button shows the issue count badge.
   - Force `is-touch`: toggles sit inside the field; camera opens under it with the reticle; keyboard toggle changes `inputmode`.
4. A session for a supplier with **no** invoice lines: notice paragraph shown, no progress bar, rows show counts only (no `/ —`, no pill); with nothing scanned yet, the "Nothing scanned yet" empty state.
5. Completed session: no scan field, "This delivery is completed" still shown.
6. Stock scan, Find product, Labels and Vouchers pages: scan field unchanged (hint line still present, toggles outside the field).

## Risks

- **Buttons inside a `<label>`:** the design nests the toggles in `label.shop-scan__field`. Clicking a button that is a descendant of a label does not activate the label (buttons are interactive content), so the input is not focused/blurred by the click itself. If the keyboard toggle misbehaves on Android, wrap the input alone in the label and keep the buttons as siblings inside a `div.shop-scan__field`, which the CSS treats identically.
- **`button.shop-row`:** the design resets `font: inherit; color: inherit` for buttons under `.shop`, and `.shop-item` sets `width:100%; text-align:left`. If the row still shows a UA border or background, that is a design gap: add the one-line fix under APP ADDITIONS and say so in the report; do not edit the design block.
- **Stock figure and `ONLY_FULL_GROUP_BY`:** `UNITS` is already selected by both queries, so nothing changes for MySQL. Only the JSON shape grows.
- **The phone squeeze** reported earlier for delivery rows (the `shop-row--wrap` fix) should no longer need the app rules: `.shop-item` is a two-column grid. Check at 390 px before deleting the old rules; if the aside still squeezes, keep a minimal rule under APP ADDITIONS and report it.
- **Sort label semantics:** the label shows the order in force. If in testing this reads as the action instead, say so in the report rather than changing it; the owner decides.
