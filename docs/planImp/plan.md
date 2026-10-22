# Per-line pallet percentage on Udea order review

Status: READY
Revision: 1
Planner: Fable 5.1
Date: 2026-10-21

## Goal

On a Udea order's review page (`/orders/{order}`, and the Christmas review) the
operator can see the running pallet-fill total but not how much of the pallet each
product line takes. Show, under each line's quantity control, that line's share of
the selected pallets at its current quantity, and what one more order unit (case or
single) adds. The figures use Udea's own per-line maths, so the line figures add up
to the total in the bar. While there, fix the bar not updating when quantity is
changed with the `−`/`+` or case-snap buttons.

## Context

- Feature doc: `docs/features/udea-pallet-volumes.md`. Udea's rule (their
  `order.js:1556`): `linePct = round(sve * volume * qty / capacity * 100, 2)`,
  rounded **per line**, then summed; `capacity = euro * 250 + block * 360`.
- Per-unit data: `UdeaPalletVolumeService::volumesForCodes()`
  (`app/Services/UdeaPalletVolumeService.php:220`) returns `sve * volume` per
  supplier code. The review tables already call it and put the value on every
  quantity input as `data-pallet-volume` (`review-table.blade.php:777`,
  `review-table-christmas.blade.php:726`, and the three copies of each).
- The bar is `resources/views/components/udea-pallet-summary.blade.php`. Its
  Alpine component `udeaPalletCalc` (line 104) owns `euro`, `block`, `capacity`,
  and `recalc()` (line 137) already iterates every `.qty-input`, computes each
  line's rounded percent at line 156 and then throws it away. **Capacity exists
  only inside that component**: no store, no global, no event. The cleanest design
  is therefore for `recalc()` itself to write each line's figure into a
  placeholder in the row, found by id. That keeps one source of truth for the
  rounding and means the line figures always sum to the bar's total.
- `review-table.blade.php` has **four byte-identical copies** of the quantity cell
  (cheese, refrigerated, case products, unit products): lines 754–806, 1245–1297,
  1648–1700, 2051–2103 (verified with `diff`). The "Order: N units (x cases)" line
  is at 783–786 (and 1274–1277, 1677–1680, 2080–2083). The row `@php` defines
  `$item`, `$supplierCode` (trimmed), `$isCaseProduct`, `$quantityLabel`
  (`'cases'`/`'units'`), and `$udeaPalletVolumes` is the partial-level map
  (line 84). `review-table-christmas.blade.php` mirrors this: "Order:" lines at
  733, 1264, 1707, 2150; its pallet map is built around lines 55–60.
- The row ids already follow `something-{{ $item->id }}` (`units-label-`,
  `total-cost-`, `qty-input-`), and the table's JS finds them with
  `document.getElementById`. Copy that convention.
- **Existing gap.** The bar listens for `input` and `change` on `.qty-input`
  (summary lines 126–132), but the `−`/`+` handlers
  (`review-table.blade.php:3171`, `:3190`), the case-snap buttons (`:3256`), and
  `min-stock-editor.blade.php:133–137` set `input.value` directly. No
  `dispatchEvent` exists anywhere in these partials, so the bar does not move on
  those clicks today (the comment at summary lines 124–125 believes it does).
  Nothing else listens for `change` on `.qty-input` (only the priority `<select>`
  has a `change` listener, `:3132`), so dispatching a bubbling `change` event from
  those handlers reaches the bar without re-triggering the table's own `input`
  listener (`:3149`) and its debounced save.
- `$isUdeaOrder` is computed in `show.blade.php:56–58` and
  `show-christmas.blade.php` (same block, lines 53–58) but is **not** passed to the
  review-table partials (`show.blade.php:135–141`). The partial looks up pallet
  volumes for any supplier, so a non-Udea order whose codes happened to match would
  already get `data-pallet-volume`. The new per-line text must be gated on the Udea
  flag, or every line of a Sonett order would say "No pallet data".
- Dev data: 1,761 `udea_product_cards` rows carry pallet data (13 of them with
  volume 0). Order **349** (supplier 5, draft, 62 items, 39 with a quantity) is a
  small Udea order for browser checks; rendered today it shows 60 of 62 inputs with
  `data-pallet-volume`, bar 19.71 % of one Europallet, 38 lines counted, 1 line
  with a quantity and no data, console clean. Order 353 (1,353 items) is the
  heavy case.
- Browser tooling: `docs/deposit/scripts/browser/lib.mjs` (`open()`, `b.go()`,
  `b.ev()`, `b.errors`, `b.close()`), README in that folder. Run from the repo
  root; dev only.
- Test scaffolding for rendering an order page with POS tables:
  `tests/Feature/Shop/ShopOrderReviewTest.php` (`AliasesMysqlConnection` +
  `CreatesProductSearchPosTables`, seeds `suppliers`, `PRODUCTS`, `supplier_link`,
  `stocking` on the `pos` connection; passes today, 15 tests). No test currently
  renders the office `/orders/{order}` page. `UdeaProductCard::create([...])` with
  `pallet_sve`, `pallet_unit_volume`, `pallet_scraped_at` is used in
  `tests/Feature/UdeaPalletVolumeSyncTest.php:52`.

## Constraints

- Udea's maths must be reproduced exactly: per-line rounding to 2 dp **before**
  summing (doc, "How Udea calculates it"). The per-line figure shown is the same
  rounded value that is summed, so the lines add up to the bar.
- The quantity multiplied is the `.qty-input` value (cases for case products,
  singles otherwise), matching `OrderService::exportToCsv()`; do not multiply by
  case units again.
- No new server round trips: the per-line figure is computed in the browser from
  data already on the page. No new routes, migrations, or model changes.
- The per-line text appears only on Udea orders (supplier ids from
  `config('suppliers.external_links.udea.supplier_ids')`), the same gate as the bar.
- Do not change how the table's own `input` listener saves quantities; the new
  event must not cause a second save.
- Use only Tailwind classes already present in the file being edited, or run
  `npm run build` and say so (`public/build` is gitignored; production builds on
  deploy).
- Do not commit, push, or deploy.

## Out of scope

- The A2, A2-dense, A2-christmas and grid layouts (`show-layout-a2*.blade.php`,
  `grid-view.blade.php`): they have no pallet bar at all (doc, Known limitations).
- Shop mode order review (`resources/views/shop/order-review.blade.php`).
- Changing how `total-cost-{id}` updates (server response only) or any other
  per-line figure.
- Server-side rendering of an initial percentage (capacity depends on the
  operator's `localStorage` pallet selection; the browser fills it on load).
- Any change to `UdeaPalletVolumeService`, the sync command or the admin page.
- Flagging "large" lines with colour thresholds.

## Steps

### 1. Pass the Udea flag into the review-table partials
Files: `resources/views/orders/show.blade.php`,
`resources/views/orders/show-christmas.blade.php`
What: add `'isUdeaOrder' => $isUdeaOrder` to the `@include('orders.partials.review-table', [...])`
(line 135) and the `review-table-christmas` include (line 120). The only other
caller is the mockup page `resources/views/orders/mockup-vico-live.blade.php:46`
(route `orders.mockup.vico-live`, a Vico order): leave it alone, it keeps the
default (false).
Check: `grep -n "isUdeaOrder" resources/views/orders/show.blade.php resources/views/orders/show-christmas.blade.php`
shows the flag computed and passed in each file.

### 2. Per-line placeholder in `review-table.blade.php`
Files: `resources/views/orders/partials/review-table.blade.php`
What: in the partial-level `@php` (near line 84) add
`$showPalletLine = (bool) ($isUdeaOrder ?? false);`. Then, in **each of the four**
quantity cells, directly after the `<div class="text-xs text-gray-500 text-center">
Order: … </div>` block (lines 783–786 and its three copies) and before the
`@if($caseUnits == 1 && $supplierCode)` case-snap block, add:

```blade
@if($showPalletLine)
    @php $palletPerUnit = $supplierCode ? ($udeaPalletVolumes[(string) $supplierCode] ?? null) : null; @endphp
    <div id="pallet-line-{{ $item->id }}" class="text-xs text-center text-gray-500"
         data-pallet-line="{{ $item->id }}">
        @if($palletPerUnit === null)
            <span class="text-amber-600">No pallet data</span>
        @else
            <span id="pallet-line-pct-{{ $item->id }}" class="font-semibold text-gray-700"></span>
            <span id="pallet-unit-pct-{{ $item->id }}" class="text-gray-400"></span>
        @endif
    </div>
@endif
```

The two spans start empty; the bar's `recalc()` fills them (step 4). A product with
pallet data and volume 0 is "data present" (it will show 0.00 %), matching the bar.
Keep the four copies identical. `text-amber-600` is not used in this file but is
already in the compiled CSS (the summary component uses it, lines 86 and 90, and
Tailwind scans every Blade file), so no `npm run build` is needed for it.
Check: `grep -c 'id="pallet-line-{{ $item->id }}"' resources/views/orders/partials/review-table.blade.php`
→ `4`. `diff` of the four quantity-cell ranges still reports no differences
(re-derive the line ranges after editing).

### 3. Same placeholder in `review-table-christmas.blade.php`
Files: `resources/views/orders/partials/review-table-christmas.blade.php`
What: the same `$showPalletLine` line in its partial-level `@php` (the pallet map
is `$udeaPalletVolumes` at line 59) and the same block immediately after each of
the four "Order: …" divs (733, 1264, 1707, 2150), before the
`@if(abs($finalUnits - $suggestedUnits) > 0.001)` "Adjusted from" block. This file
has no case-snap block. `$supplierCode` is trimmed in row scope at lines 389–391.
Check: `grep -c 'id="pallet-line-{{ $item->id }}"' resources/views/orders/partials/review-table-christmas.blade.php`
→ `4`.

### 4. `recalc()` writes each line's figure
Files: `resources/views/components/udea-pallet-summary.blade.php`
What: inside the `forEach` in `recalc()` (lines 142–158), reorder the body so
that **every** input with a finite `data-pallet-volume` gets its placeholders
written, including lines with quantity 0. Exact order per input:

1. Parse `qty = parseFloat(input.value)` and `perUnit = parseFloat(input.dataset.palletVolume)`
   (today `perUnit` is parsed after the qty early-return; move it up).
2. If `Number.isFinite(perUnit)`, write the two spans (rules below).
3. Then the existing logic, unchanged: `qty` not finite or `<= 0` → `return`;
   `perUnit` not finite → `uncounted++; return`; add to `volume`, `counted`,
   `pctSum`.

Span rules, with `isCase = input.dataset.isCaseProduct === '1'` and
`suffix = isCase ? '/case' : '/unit'`:

- `capacity <= 0`: line span `—`, unit span empty.
- Otherwise compute once:
  `linePct = Math.round(perUnit * qty / capacity * 100 * 100) / 100` (the **same**
  number added to `pctSum` in step 3, so reuse the variable there) and
  `u = perUnit / capacity * 100`,
  `unitText = (u > 0 && u < 0.005) ? '<0.01%' : u.toFixed(2) + '%'`.
  - `qty > 0`: line span `linePct.toFixed(2) + '%'`; unit span `' · ' + unitText + suffix`.
  - `qty <= 0` (or not finite): line span empty; unit span `unitText + suffix`.

Find the spans with `document.getElementById('pallet-line-pct-' + input.dataset.itemId)`
and `'pallet-unit-pct-' + …`; skip silently when either is missing (non-Udea
pages, or an input with data but no placeholder). Only assign `textContent` when
the new text differs, so a keystroke on a 1,353-line order does not rewrite
every node. Update the component's header comment to say the component also
writes the per-line figures. Keep the `localStorage` write and the `counted` /
`uncounted` logic as they are.
Check: `node docs/deposit/scripts/browser/cdp.mjs /orders/349` ends with
`console errors/warnings: []`, and the script in Verification step 2 shows every
line's figure and a sum equal to the bar.

### 5. Programmatic quantity changes tell the bar
Files: `resources/views/orders/partials/review-table.blade.php`,
`resources/views/orders/partials/review-table-christmas.blade.php`,
`resources/views/orders/partials/min-stock-editor.blade.php`
What: add `input.dispatchEvent(new Event('change', { bubbles: true }));` as the
**last statement** of each click-handler body that assigns a quantity input's
`.value` directly: review-table decrease handler (assignment at `:3171`), increase
(`:3190`), case-snap button (`:3256`; the handler ends with `highlight();
applyCaseMatch(input);`, the dispatch goes after those); christmas decrease
(`:3245`) and increase (`:3263`); min-stock-editor as the last statement inside
`if (qtyInputEl) { ... }` (`:134–138`, variable `qtyInputEl`). Nothing downstream
depends on ordering; the bar only reads `input.value`. Do not dispatch `input`
(that would run the table's own `input` listener and save twice). The only other
`change` listeners on the page are the priority `<select>` (`:3132`) and the sort
`<select>`'s inline `onchange`, neither on a quantity input, and no ancestor has an
Alpine `x-on:change`. Fix the summary's comment at lines 124–125 to say the table
dispatches `change` for programmatic changes.
Check: `grep -n "new Event('change'" ` across the three files → 6 hits. Browser:
Verification step 3 shows the bar and the line figure move after a `+` click.

### 6. Feature test for the markup
Files: `tests/Feature/OrderReviewPalletLineTest.php` (new; check it does not
exist first)
What: model it on `tests/Feature/Shop/ShopOrderReviewTest.php` (`RefreshDatabase`,
`AliasesMysqlConnection`, `CreatesProductSearchPosTables`; seed `suppliers`,
`PRODUCTS`, `supplier_link` with `SupplierID` `'5'`, `stocking`). Build an
`OrderSession` with `supplier_id => '5'` and two `OrderItem`s whose
`context_data` carries `supplier_code` (`'5004482'` and `'5009999'`) and
`case_units`; create `UdeaProductCard` for `'5004482'` with `pallet_sve => 10`,
`pallet_unit_volume => 0.65`, `pallet_scraped_at => now()` and none for the other.
Both items need `suggested_quantity` and `final_quantity` above zero (e.g. 6):
the partial hides rows with no quantity unless `?show_all=1`
(`review-table.blade.php:45–54`). Both `product_id`s must exist in `PRODUCTS`:
the row `@php` dereferences `$product->ID` and `$product->stocking` unguarded.
Log in as an admin (`User::factory()->withRole('admin')`, as in
`OrderDifferenceCreationTest`); the route sits behind `permission:orders.manage`,
which the admin role passes without seeded permissions. Traced at planning time,
the page reads only `PRODUCTS`, `supplier_link`, `suppliers`, `stocking` on the
POS side, all created by the concern. Tests:

1. `GET /orders/{id}` for the Udea order is 200 and contains
   `data-pallet-volume="6.5"`, `id="pallet-line-pct-{itemA}"`, and, for item B,
   `id="pallet-line-{itemB}"` followed by `No pallet data`.
2. The same order with `supplier_id => 'S1'` (a non-Udea supplier seeded in
   `suppliers`) renders 200 with **no** `pallet-line-` string anywhere.

If the office page needs POS tables the concern does not create, add them in
this test's own `setUp` (not in the shared concern) and record it in Deviations.
If after adding at most two tables the page still cannot render in a test, drop
step 6, say so in Deviations, and the browser checks in Verification are the
acceptance evidence for the markup.
Check: `php artisan test --filter=OrderReviewPalletLineTest` → all pass.

### 7. Documentation
Files: `docs/features/udea-pallet-volumes.md`
What: in "On the order review page", add a paragraph: each line shows its share
of the selected pallets at its current quantity and the share one order unit adds,
written by the bar's `recalc()` into `pallet-line-pct-{id}` / `pallet-unit-pct-{id}`;
lines without data say "No pallet data". In "Integration points", note that
programmatic quantity changes dispatch a `change` event on the input so the bar
and line figures follow the `−`/`+`, case-snap and min-stock buttons. Add the
per-line display to the A2/Grid "Known limitations" bullet (they have neither).
Check: the three passages read correctly in the rendered Markdown.

## Verification

Run from the repo root, in order.

1. `./vendor/bin/pint --test` → clean (or run `./vendor/bin/pint` on the test file).
2. Per-line figures and their sum, on dev order 349 (read-only):

```bash
node --input-type=module -e '
import { open, sleep } from "./docs/deposit/scripts/browser/lib.mjs";
const b = await open(); await b.go("/orders/349"); await sleep(1500);
const out = await b.ev(`(() => {
  const bar = document.querySelector("[x-data^=\\"udeaPalletCalc\\"]");
  const a = Alpine.$data(bar);
  const rows = [...document.querySelectorAll(".qty-input")].map(i => ({
    id: i.dataset.itemId, qty: i.value, vol: i.dataset.palletVolume,
    line: document.getElementById("pallet-line-pct-" + i.dataset.itemId)?.textContent,
    unit: document.getElementById("pallet-unit-pct-" + i.dataset.itemId)?.textContent,
    none: document.getElementById("pallet-line-" + i.dataset.itemId)?.textContent.includes("No pallet data") }));
  const sum = rows.reduce((s, r) => s + (parseFloat(r.line) || 0), 0);
  const expectBad = rows.filter(r => r.vol !== undefined && parseFloat(r.qty) > 0 &&
    Math.abs((Math.round(parseFloat(r.vol) * parseFloat(r.qty) / a.capacity * 10000) / 100) - parseFloat(r.line)) > 0.001);
  return { capacity: a.capacity, barPct: a.percent, sumOfLines: sum, rows: rows.length,
    withLine: rows.filter(r => r.line).length, noData: rows.filter(r => r.none).length,
    unitOnly: rows.filter(r => !r.line && r.unit).length, mismatches: expectBad, sample: rows.filter(r => r.line).slice(0, 3) };
})()`);
console.log(JSON.stringify(out, null, 1)); console.log("console:", JSON.stringify(b.errors)); b.close();'
```

   Expected: `noData` = 2 (the two products without data), `withLine` = 38,
   `unitOnly` = 22 (60 inputs with data minus 38 with a quantity),
   `|sumOfLines − barPct| < 0.01`, `mismatches` = `[]`, console `[]`. Sample
   texts look like `1.23%` and ` · 0.41%/case` or `0.41%/unit`.
3. Buttons move the bar and the line (writes dev order 349, then restores it):
   in the same style, click the pallet-bar Europallet `+` (every line figure and
   the bar halve-ish; `a.capacity` becomes 500), click it `−` again; then on the
   first row with a line figure, record `line` and `a.percent`, click that row's
   `.qty-increase`, wait 300 ms, confirm both changed and `a.percent` rose by the
   line's new minus old figure (tolerance 0.005), then click `.qty-decrease` once
   and confirm both return to the recorded values. Console stays `[]`. (Chrome
   starts with an empty profile each run, so the bar begins at 1 Europallet.)
4. `php artisan test --filter=OrderReviewPalletLineTest` → pass (unless dropped
   under step 6's rule).
5. `php artisan test --filter="UdeaPalletVolume|ShopOrderReview|OrderDifference"`
   → all pass (ignore the PHP 8.5 `PDO::MYSQL_ATTR_SSL_CA` deprecation lines).
6. `php artisan test` → the same 16 pre-existing failures as the baseline (the
   usual 15 plus `BackupConfigurationTest`); list any new failure.
7. Heavy page: `node docs/deposit/scripts/browser/cdp.mjs /orders/353` → console
   `[]` and the page still responds (no multi-second freeze on a keystroke; if a
   single `input` event takes over 100 ms in DevTools timing, say so).

## Risks

- **Four copies per file.** A placeholder added to three of the four quantity
  cells shows the figure only in some sections. The `grep -c … → 4` checks exist
  for this.
- **Double save.** Dispatching `input` instead of `change` would run the table's
  own listener and save the quantity twice. Use `change`.
- **Non-Udea orders.** Without the step 1 flag every line of every order shows
  "No pallet data". Test 2 in step 6 guards this.
- **Large orders.** `recalc()` already walks every input on each keystroke; the
  per-line write adds a `getElementById` pair and a text compare per line. Only
  assign when the text changes. Verification step 7 is the check.
- **Rendering `/orders/{order}` in a test** may need POS tables the concern lacks;
  the fallback in step 6 bounds the effort.
- Alpine note from `planimp.md`: nothing here uses `x-show` on nullable bindings
  or an `@` shorthand that is also a Blade directive; the new markup is plain
  Blade with ids, filled by the existing component.

## Review

(Planner fills this in after reading implemented.md and the diff.)
