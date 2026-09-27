# Shop mode cycle 23 — Delivery scan v2 (Screen 05 v2) — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: d5aae2b9. Cycle 22's work is accepted and archived but still uncommitted, so
the tree carries it alongside this cycle. Files changed at the end separates them.

Test baseline: 15 failed / 662 passed.

## Pre-flight

The plan's two factual claims about the design, both checked:

```
$ wc -l docs/design/shop-mode/shop.css resources/css/shop.css
553 docs/design/shop-mode/shop.css
575 resources/css/shop.css

$ head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - …
differ: byte 8908, line 152
```
and the diff against the committed design file is exactly what the plan describes:
`.shop-topbar__title` line-height 1.15 → 1.35, plus 24 new lines
(`.shop-topbar__titles`, `.shop-topbar__sub`, four `.shop-scan--inline` rules, the
`.shop .shop-scan__input` reset, `.shop-status`, `.shop-notice`, `.shop-item`,
`.shop-row__stock`).

`docs/design/shop-mode/screen-05-delivery-scan-v2.html` exists and is Planner-owned;
I read it but did not touch it.

## Steps

### 1. Refresh the design block — done

Replaced everything before `APP ADDITIONS START` with the current design file.
```
$ head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css
(nothing)
$ grep -c "APP ADDITIONS START" resources/css/shop.css
1
```

### 2. `info` icon — done

```
$ grep -c 'id="info"' public/images/shop-icons.svg          → 1
$ Blade::render('<x-shop.icon name="info" size="sm" />')    → …#info
```

### 3. Topbar subtitle — done

`ShopLayout` gained `?string $subtitle`, the layout passes it, the topbar renders
`shop-topbar__titles` **only when it is set**; otherwise the markup is the bare
`<h1>` it always was.
```
no subtitle:  <h1 class="shop-topbar__title">Sup</h1>
with:         <div class="shop-topbar__titles"><h1 …>Sup</h1><span class="shop-topbar__sub shop-code">d1 · today</span></div>
```

### 4. Inline scan-input variant — done

`inline` prop. **I proved the non-inline output is unchanged rather than assuming
it**: rendered the component, stashed my changes, rendered again on the original,
and diffed. The only differences are leading whitespace from the new Blade
directives; normalised for whitespace the two are identical, and
`ShopStockScanTest|ShopFindProductTest|ShopVouchersTest` (31 tests) pass untouched.

### 5. Stock in the items JSON — done

`'stock'` on both row builders; no query change, both already selected
`STOCKCURRENT.UNITS`.

### 6. The view rebuilt to Screen 05 v2 — done

Subtitle, inline field, notice/status block, correction card, ghost sort toggle,
button rows with stock, the new empty state, Summary-only action bar.

**I checked before deleting the old app CSS**, as the plan requires:
```
$ grep -rn "shop-row--wrap\|shop-row__controls" resources/ tests/ app/
resources/css/shop.css:585-587   (the rules)
tests/…/ShopDeliveryTest.php:187,188,193,194   (the assertions)
```
Nothing else, so both rules are gone. The plan's risk about the phone squeeze does
not materialise — measured below.

### 7. `delivery-scan.js` — done

`toggleSort()`, `editingRow`, `edit()` scrolls `$refs.correct` into view, `onScan()`
clears `editing`, `pill()` returns null for `ok` and for `unexpected` without an
invoice, `stockText()` replaces `qtyLabel()` (grepped: nothing else used it — the
`$qtyLabel` hits in `orders/compare.blade.php` are an unrelated PHP closure).

### 8 & 9. Tests, format, build — done

Replaced the two `shop-row--wrap` assertions with nine for v2 plus two
`assertDontSee`, added a subtitle test, the `stock` assertions, and a topbar
regression test in `ShopHomeTest` proving a page without a subtitle still renders a
bare `<h1>`.

## Verification

**1. `php artisan test`**
```
Tests:    15 failed, 664 passed (2851 assertions)
```
The identical 15 (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2,
Product ×2, TestScraper ×1). 664 = 662 + 2 new tests.

**2. Contract** — the `cmp` prints nothing (above); no `<script>`/`<style>` in shop
views.

**3. Browser, the Shop, at desktop width and at the narrowest this browser allows.**

*State B — a session with 164 invoice lines* (`supplierID=37`; none of the sessions
the list offers has invoice lines, so I had to find the supplier that does):
```
hasInvoice true, rows 164, progress {total 164, checked 0, issues 0}
status block visible, notice hidden, bar width "0%"
rows: "All About Kombucha Ginger & Lemon Can 330ml b" · 49037A · Stock 16 · 0 / 10 · "Not scanned"
      expectedLabel "/ 1", "/ 6", "/ 5"
```
**`stockText()` earns its place here.** One real row has
`stock: 1.5200000000000011`; it renders `1.52`. A whole `8` renders `8`, not `8.0`.

*State A — a session with no invoice lines:* the notice paragraph, no progress bar,
one row showing a plain count with **no pill** (correct — nothing is "unexpected"
without an invoice), `Items 1`, the ghost `New first` toggle. A second such session
with nothing scanned shows the new **"Nothing scanned yet / Scan the first item."**
empty state.

*Interactions, all by real clicks:*
- Sort toggle: `New first` → `Scanned first` → `New first`, `sort` following.
- Tapping a row (`<button>`, `aria-pressed`) opens **Correct quantity** with the
  name, code, `Stock 53` and the stepper at 27.
- `−` then `+` each hit the server and the row updated 27 → 26 → 27, so the figure
  is exactly where it started.
- `Done` closes the card (`editing` null, card hidden).

*Top bar:* supplier on line 1, `9e5c52db · Sat 20 Jun` on line 2, not truncated.

*Phone width:* the browser would not go below a **500 px** CSS viewport however I
resized it, so I could not test at 390. At 500: nothing truncated, rows are
80 px two-column grids (main 384 px, aside 36 px), the title fits. **The old
`shop-row--wrap` rules are not needed** — `.shop-item`'s grid handles it.

*`is-touch`:* `shop-scan shop-scan--inline`, both toggles inside the field at
**52×52**, no `shop-scan__tools` wrapper, no hint line, the camera box present and
`.shop-scan__mount` still `position: absolute` (cycle 16's fix survives). The
keyboard toggle flips `aria-pressed` and `inputmode` between `none` and `text`.

**4 & 5.** State A covered above. A completed session still hides the scan input and
shows "This delivery is completed" — pinned by `test_completed_session_hides_the_scan_input`,
which passes.

**6. Other screens unchanged**, checked in the browser:
```
stock-scan: classes "shop-scan", inline false, tools outside, 0 toggles in field,
            hint "Ready — scanner listening"
labels:     same, and no shop-topbar__titles
```

*Console:* two messages, both Alpine's startup log.

*Dev data:* the one quantity I moved is back at 27; nothing else was written.

## Deviations

**One, from a measurement.** The plan's subtitle is `{id} · {date}`. A real session
id is a 36-character UUID, and at the narrowest width I could reach the subtitle was
truncated to **97 px of 396 px** — the date, the useful half, was cut off entirely.
I truncate the id to 8 characters, which is what the previous single-line title did
(`substr($session['id'], 0, 8)`) and what the design's own example shows
(`d1-20931`). After the change: `9e5c52db · Sat 20 Jun`, 156 px, not truncated.
The plan's test assertion still holds — the fixture's id is `d-1`.

## Risks the plan named, and what actually happened

- **Buttons inside a `<label>`:** no misbehaviour observed; the toggles work and the
  fallback the plan offers was not needed.
- **`button.shop-row`:** no UA border or background. The design's button reset plus
  `.shop-item` is enough; **no APP ADDITIONS rule was needed**.
- **The phone squeeze:** did not recur; the old rules are deleted.
- **Sort label semantics:** in use the label reads as the order in force, which is
  what the design intends. I am not confident it is unambiguous — see Notes.

## Files changed

```
 M app/Http/Controllers/DeliveryLegacyController.php   'stock' on both row builders
 M app/View/Components/ShopLayout.php                  $subtitle
 M public/images/shop-icons.svg                        the info symbol
 M resources/css/shop.css                              design block refreshed; 3 app rules removed
 M resources/js/shop/delivery-scan.js
 M resources/views/components/shop/scan-input.blade.php   the inline variant
 M resources/views/components/shop/topbar.blade.php
 M resources/views/layouts/shop.blade.php
 M resources/views/shop/delivery-scan.blade.php        rebuilt to v2
 M tests/Feature/Shop/ShopDeliveryTest.php
 M tests/Feature/Shop/ShopHomeTest.php
```
Cycle 22's files are also still dirty in this tree, uncommitted. I did not touch
`docs/design/shop-mode/**`.

**Not committed, not pushed, not deployed.**

## Notes for Planner

1. **No delivery session on dev has invoice lines through the list.** Every session
   the Deliveries page offers came back `hasInvoice: false`; I reached state B only
   by pointing the URL at supplier 37, which is where the 206 `delivery` rows
   belong. So the owner testing this by tapping through the list will only ever see
   state A and may conclude the progress bar is missing. Worth knowing before they
   look, and worth asking whether the list should show which sessions have an
   invoice loaded.

2. **The sort label is genuinely ambiguous and I am reporting rather than changing
   it, as the plan asks.** "New first" beside a sort icon reads equally as "this is
   the order" and "tap for this order". The radio group it replaced could not be
   misread. If the owner hesitates over it, the cheap fix is a label that cannot be
   an instruction — e.g. `Sorted: new first` — rather than reverting to the segmented
   control.

3. **`inputmode` does not refresh when `is-touch` is added after load.** `isTouch`
   reads the DOM rather than reactive state, so Alpine's binding only re-evaluates
   when something reactive changes. On a real device the class is present at page
   load so the first render is correct; it only shows up when forcing touch in
   DevTools, as I did. Pre-existing, not introduced here, and harmless — but it will
   confuse the next person who tests touch this way.

4. **I could not get a CSS viewport below 500 px** in this browser however I resized
   the window, so the 390 px check in the plan's verification is unmet. Everything
   holds at 500 px with room to spare (aside 36 px of 500), so I do not expect a
   problem, but it is untested at true phone width.
