# Cycle 1 — Case size on the New request sheet, order by the case or by the unit

Status: READY
Revision: 1
Planner: Fable 5.1
Date: 2026-10-07

## Goal

When staff pick a stocked product on the New request sheet (and on the edit
screen), the sheet tells them what it knows about the product — the case size,
the stock on hand and the shelf price — and lets them take the request **by the
case** or **by the unit**. A request line then remembers which it was and how
big the case was, so the board, the detail page and the delivery put-aside flag
read "2 cases of 6", not "12", and the person putting the item aside knows to
keep the case unopened. Owner's words (`todo.txt`): "some info for the user to be
returned such as the case size of an item with the option to order a case then
for the customer or individual units".

## Context

- **Case size lives on the POS `supplier_link` row**: `App\Models\SupplierLink`
  (`pos` connection, table `supplier_link`, `CaseUnits` integer cast,
  `Barcode` = `PRODUCTS.CODE`). `Product::supplierLink()` is a `hasOne` on it.
  This is the figure orders use (`OrderItem::case_units` comes from
  `$supplierLink->CaseUnits ?? 1`, `DeliveryService.php:652`). Dev data: 10,276
  links, 5,537 with `CaseUnits > 1`, 789 null or 0. The POS database is
  read-only; we only read `CaseUnits`.
- **The product search endpoint already loads the link.**
  `App\Services\ProductSearch\ProductSearchService::hydrate()` (:373) has
  `$link = $product->supplierLink` per row and builds the `supplier` sub-array
  from it (:416). The response shape is canonical (`docs/features/product-search.md`,
  "do not add per-page variants"); adding one top-level key is fine, changing
  the `supplier` sub-array is not: `ProductSearchApiTest::test_response_carries_supplier_price_and_stock_details`
  (:192) `assertSame`s that sub-array exactly. The fixture
  `tests/Concerns/CreatesProductSearchPosTables.php:152` inserts `supplier_link`
  rows without `CaseUnits` (column is nullable, :49).
- The response already carries `stock_units` (float), `price_with_vat`
  (float, 2 dp) and `is_service`; the sheet shows none of it today.
- **Request lines**: `customer_request_items` (`database/migrations/2026_09_10_100001_…`)
  has `quantity decimal(10,2)`, no unit. `CustomerRequestItem` casts
  `quantity => decimal:2` (arrives as a string). Factory
  `database/factories/CustomerRequestItemFactory.php` with `forProduct()`.
- **Where quantity is rendered today** (every one is a bare number via
  `rtrim(rtrim(number_format(…)))`):
  `resources/views/shop/partials/request-row.blade.php:23,94` (staff row),
  `request-card.blade.php:24` (guest card, via `$qty` closure passed from
  `requests.blade.php:1`), `request-show.blade.php:85` (`$qty` defined in
  that view), `delivery-legacy/partials/customer-request-badge.blade.php:16`,
  `delivery-legacy/match.blade.php:376` (scanner prompt, Alpine `cr.quantity`)
  and `:709` (summary card), `resources/js/shop/delivery-scan.js:391` (Shop
  scan flag text, names only).
- **The write path**: `CustomerRequestRequest` (`ITEM_KEYS` whitelist :13,
  rules :31) → `CustomerRequestService::create()` / `update()` →
  `snapshotProductNames()` (:468, one POS query for all lines) →
  `lineFields()` (:425) → `createItem()`. `update()` fills existing lines with
  `lineFields()` and never touches status.
- **Seeds**: `CustomerRequestController::seedItems()` (:226) builds the Alpine
  seed for a bounced submission (`old('items')`) and for the edit screen, with
  a batched image lookup; `seedProduct()` gives `{code, image_url}`.
  `request-edit.js` `withKey()` (:21) copies the seed fields it knows and drops
  the rest.
- **New request sheet**: `resources/views/shop/partials/request-form.blade.php`
  (one line, `kind` pre-order/sourcing segmented control, picked row with
  `x-shop.product-thumb`, hidden `items[0][…]` inputs, quantity text input),
  Alpine `resources/js/shop/requests.js` (`onPick()` keeps
  `{id, code, name, image_url}`). Edit screen:
  `resources/views/shop/request-edit.blade.php` (:73 `x-for` over `items`,
  hidden inputs :76–78, quantity :99) and `request-edit.js`.
- **Shop CSS available in the verbatim design block** (no app additions
  needed): `.shop-choices` / `.shop-choice` radio cards with a `<small>`
  caption (`resources/css/shop.css:371–376`), `.shop-seg`, `.shop-facts--2`,
  `.shop-row__meta`, `.shop-code`, `.shop-meta`. `ShopViewContractTest` lets
  through any `shop-*` class.
- `resources/js/shop/quantity.js` exports `quantityText()` (whole numbers
  without ".0", weights to 3 dp) for showing `stock_units`.
- The delivery JSON (`awaitingArrivalPayload()` :389) is consumed by
  `match.blade.php` and `delivery-scan.js`; `CustomerRequestDeliveryFlagTest:182`
  asserts `customerRequests.0.quantity` is 2 (keep it).
- Tests: `tests/Feature/CustomerRequestTest.php` (helper `payload()`, POS
  `PRODUCTS` built in sqlite; **check whether it creates `supplier_link`** —
  `CustomerRequestDeliveryFlagTest:83,116` does, with `CaseUnits` 6 on the oat
  milk, copy that), `CustomerRequestDeliveryFlagTest`, `ProductSearchApiTest`,
  `tests/Unit/CustomerRequestItemTransitionsTest.php`.

## Decisions (owner not asked; recorded here)

1. **`quantity` keeps meaning "how many of the chosen unit".** "2 cases of 6"
   is stored as `quantity 2, unit case, case_units 6`, not as 12 units. Every
   screen that reads quantity today keeps working, and the person putting the
   item aside sees what the customer actually asked for.
2. **`case_units` is a snapshot taken on the server** from `supplier_link`
   when the line is saved by the case, like `product_name`. A posted
   `case_units` is ignored. Supplier case sizes change; the request must
   show the size that was agreed.
3. A line can be "by the case" with no known size (`case_units` null) only
   if the product has no link; the sheet never offers Case in that case, but
   the service tolerates it and the label reads "2 cases".
4. Wording: units → the bare number, as today. Cases → "1 case of 6",
   "2 cases of 6", "2 cases" when the size is unknown.

## Constraints

- The POS database is read-only; `supplier_link` is only read.
- All status writes stay in `CustomerRequestService::changeItemStatus()`;
  this cycle does not touch statuses.
- Public board and photo route unchanged; guests still see no form.
- The `GET /api/products/search` shape gains one top-level key, `case_units`
  (int or null). Nothing else in the shape changes. Update the JSON example in
  `docs/features/product-search.md`.
- Shop rules (`docs/shop_new/README.md`): view contract (`shop-*` classes and
  `x-shop.*` only under `resources/views/shop/`), no edits to the verbatim
  design block of `shop.css`, Alpine traps (`?.` behind `x-show`, no `@error`
  shorthand), no new routes so the PIN allow-list is untouched.
- Old rows: `unit` defaults to `unit`, `case_units` null; they render exactly
  as before.
- Do not commit, push or deploy. The owner commits.

## Out of scope

- Converting a case line into a supplier order, or any link to the order
  system.
- Automatic status changes on delivery scan.
- The native `/deliveries/{id}/scan` screen (still no flag).
- Udea product-card `units_per_case` as a second source of case size.
- Reworking the sheet to take several lines, or multi-line entry on the sheet.
- Guest board wording beyond the same quantity label.
- Any change to the office legacy match page other than the two quantity
  strings named in step 8.

## Steps

### 1. Migration: `unit` and `case_units` on request lines
Files: `database/migrations/2026_10_07_100000_add_unit_to_customer_request_items.php` (new)
What: add `string('unit', 8)->default('unit')` after `quantity` and
`unsignedSmallInteger('case_units')->nullable()` after it; `down()` drops
both. A comment on each: `unit` is `unit` | `case`; `case_units` is the
supplier case size snapshotted when the line was taken by the case.
Check: `php artisan migrate` runs clean;
`php artisan tinker --execute='var_dump(Schema::hasColumns("customer_request_items", ["unit","case_units"]));'`
prints `bool(true)`.

### 2. Model and factory
Files: `app/Models/CustomerRequestItem.php`, `database/factories/CustomerRequestItemFactory.php`
What:
- Constants `UNIT_UNIT = 'unit'`, `UNIT_CASE = 'case'`, `UNITS = [...]`.
- Add `unit`, `case_units` to `$fillable`; cast `case_units => 'integer'`.
- `isByTheCase(): bool` (`unit === UNIT_CASE`).
- `quantityLabel(): string` — the one place the wording lives:
  units → the bare number as the views format it today (move the
  `rtrim(rtrim(number_format((float) $q, 2, '.', ''), '0'), '.')` expression
  into a small private helper or a static `formatQuantity()` so the views can
  drop their copies); case → `"{n} case of {case_units}"` / `"{n} cases of {case_units}"`,
  or `"{n} case"` / `"{n} cases"` when `case_units` is null. Singular when
  the number equals 1.
- Factory: `'unit' => 'unit', 'case_units' => null` in `definition()`; add
  state `byTheCase(int $caseUnits = 6)` setting `unit` and `case_units`.
Check: `php artisan tinker --execute='$i = new App\Models\CustomerRequestItem(["quantity"=>2,"unit"=>"case","case_units"=>6]); echo $i->quantityLabel(), "|", (new App\Models\CustomerRequestItem(["quantity"=>1,"unit"=>"case"]))->quantityLabel(), "|", (new App\Models\CustomerRequestItem(["quantity"=>1.5,"unit"=>"unit"]))->quantityLabel();'`
prints `2 cases of 6|1 case|1.5`.

### 3. Unit tests for the label
Files: `tests/Unit/CustomerRequestItemQuantityLabelTest.php` (new; look first)
What: the three cases above plus `"2 cases"` (null size) and `"4"` for four
units. Plain model instances, no database.
Check: `php artisan test --filter=CustomerRequestItemQuantityLabelTest` → pass.

### 4. Validation accepts `unit`
Files: `app/Http/Requests/CustomerRequestRequest.php`
What: add `'unit'` to `ITEM_KEYS` (not `case_units`: the server decides it);
rule `'items.*.unit' => ['nullable', 'in:unit,case']`; message
`'items.*.unit.in' => 'Line :position has an invalid unit.'`.
Check: covered by step 6 tests.

### 5. Service snapshots the case size
Files: `app/Services/CustomerRequestService.php`
What:
- `lineFields()` returns `'unit' => ($item['unit'] ?? 'unit') === 'case' && $code !== null ? 'case' : 'unit'`
  (a sourcing line is always by the unit) and
  `'case_units' => $item['case_units'] ?? null` — where `case_units` is only
  ever set by the step below, never from the client.
- New private `snapshotCaseUnits(array $items): array`, called in both
  `create()` and `update()` right after `snapshotProductNames()`: for every
  line with a `product_code` and `unit === 'case'`, one query
  `SupplierLink::whereIn('Barcode', $codes)->pluck('CaseUnits', 'Barcode')`,
  and set `case_units` to that value when it is `> 1`, else null. Lines by
  the unit get `case_units = null`.
- `update()`: an existing line that was already `case` with a stored
  `case_units` keeps its stored value (do not re-snapshot on every edit);
  only a line switching to `case`, or a `case` line whose stored value is
  null, is looked up. Make `snapshotCaseUnits()` take the existing lines
  (keyed by id) so it can tell; or do the "keep" in `update()` before
  calling `lineFields()`. Either is fine; say which in `implemented.md`.
- New public `caseUnitsByCode(array $codes): array` (code → int|null, same
  rule `> 1`) for the controller seeds (step 7). `snapshotCaseUnits()` can
  use it.
- `awaitingArrivalPayload()`: add `'unit' => $item->unit`,
  `'case_units' => $item->case_units`, `'quantity_label' => $item->quantityLabel()`.
  Keep `quantity` as it is.
Check: step 6 tests.

### 6. Feature tests for the write path and the delivery payload
Files: `tests/Feature/CustomerRequestTest.php`, `tests/Feature/CustomerRequestDeliveryFlagTest.php`
What: if `CustomerRequestTest`'s POS setup has no `supplier_link` table,
create it the way `CustomerRequestDeliveryFlagTest:83` does and insert a link
for `5000000000017` with `CaseUnits` 6. Then:
- create with `['product_code' => '5000000000017', 'quantity' => 2, 'unit' => 'case']`
  → line has `unit case`, `case_units 6`; a second line
  `['product_code' => '5000000000017', 'quantity' => 3]` (no unit) → `unit`,
  `case_units` null; a sourcing line sent with `unit => 'case'` is stored as
  `unit`.
- `unit => 'box'` → 422 / session error on `items.0.unit`.
- update: switching an existing unit line to `case` snapshots 6; editing a
  case line's quantity keeps its stored `case_units` even if the fixture's
  `CaseUnits` is changed to 12 before the update.
- delivery flag test: make the oat-milk line `byTheCase(6)` with quantity 2
  and assert `customerRequests.0.quantity_label` is `2 cases of 6` and the
  match page shows that text; keep the existing `quantity` assertion.
Check: `php artisan test --filter=CustomerRequest` → all pass (baseline 28 +
the new ones).

### 7. Search API carries `case_units`
Files: `app/Services/ProductSearch/ProductSearchService.php`, `tests/Concerns/CreatesProductSearchPosTables.php`, `tests/Feature/ProductSearchApiTest.php`, `docs/features/product-search.md`
What: in `hydrate()` add top-level `'case_units' => $link && (int) $link->CaseUnits > 1 ? (int) $link->CaseUnits : null`
next to `supplier`. Fixture: give the `8721325594341` link `'CaseUnits' => 6`
and leave the others without. Test: in
`test_response_carries_supplier_price_and_stock_details` assert
`$item['case_units'] === 6` and `$unlinked['case_units'] === null`. Doc: add
`"case_units": 6` to the JSON example and one line under the endpoint
explaining it (supplier case size, null when unknown or 1).
Check: `php artisan test --filter=ProductSearchApiTest` → pass;
`curl -s -b <cookie>` not needed: the Shop sheet check in step 9 covers it.

### 8. Views and the delivery strings use the label
Files: `resources/views/shop/partials/request-row.blade.php`, `request-card.blade.php`, `resources/views/shop/requests.blade.php`, `resources/views/shop/request-show.blade.php`, `resources/views/delivery-legacy/partials/customer-request-badge.blade.php`, `resources/views/delivery-legacy/match.blade.php` (:376 and :709 only), `resources/js/shop/delivery-scan.js` (:391 only)
What: replace each bare-number quantity with `$item->quantityLabel()` (or
`$line->quantityLabel()`); drop the `$qty` / `$qtyOf` closures and the
`$qty` partial parameter once nothing uses them. In `match.blade.php:376`
show `cr.quantity_label` instead of `cr.quantity`; at `:709` use the label.
In `delivery-scan.js:391` the flag becomes
`'Put aside for ' + data.customerRequests.map((c) => c.customer_name + ' (' + c.quantity_label + ')').join(', ')`.
Check: `php artisan test --filter=CustomerRequest` still passes (the board
test that looks for a quantity, if any, is updated to the label);
`php artisan test --filter=ShopViewContractTest` passes.

### 9. New request sheet: product facts and the Units / Case choice
Files: `resources/views/shop/partials/request-form.blade.php`, `resources/js/shop/requests.js`, `app/Http/Controllers/CustomerRequestController.php`
What:
- `onPick(p)` keeps `case_units: p.case_units ?? null`,
  `stock_units: p.stock_units ?? null`, `price_with_vat: p.price_with_vat ?? null`
  on `picked`. Add `unit: 'unit'` state, reset to `'unit'` in `unpick()`.
  A getter `unitValue` → `kind === 'preorder' && picked?.case_units ? unit : 'unit'`
  for a hidden `items[0][unit]` input.
- Picked row: under the code, a second `shop-row__meta` line built from the
  facts, each part only when known: `Case of 6` (or `Sold singly` when
  `case_units` is null), `7 in stock` (via `quantityText` from
  `./quantity.js`), `€6.25` (`Number(x).toFixed(2)`). Join with ` · `.
- Below the picked row, shown only when `picked?.case_units`
  (`x-show`, with `?.` everywhere, per the Alpine trap), a
  `<div class="shop-choices" role="radiogroup" aria-label="Order by">` with
  two `<label class="shop-choice">` radio cards bound to `unit`:
  **Units** (`<small>` "single items") and **Case** (`<small>` bound to
  `` `${picked?.case_units} per case` ``). Radios are `x-model="unit"` with
  no `name` so they do not post; the hidden input does.
- The Quantity label reads `Quantity` for units and `Cases` when
  `unit === 'case'` (`x-text`).
- Seeds: `seedItems()` adds `'unit' => $i['unit'] ?? 'unit'` (old input) /
  `$i->unit` (model), and `seedProduct()` gains `'case_units' => $caseUnits[$code] ?? null`
  from one `caseUnitsByCode()` call per seed set, so a bounced submission by
  the case re-opens with Case still selected and the size shown.
  `requests.js` reads `seed.unit` and `seed.product?.case_units` into the
  initial state (the picked seed object gets `case_units` from
  `seed.product`).
Check (browser, signed in, `/customer-requests?new=1`): pick a product with
a case size (e.g. search `oat` and take one whose row says "Case of N") —
the facts line shows, the Units / Case cards appear, choosing Case changes
the label to Cases; save with 2 → the board row reads "2 cases of N"; pick a
product without a case size → no cards, save → bare number. Submit with an
empty customer name while Case is selected → the sheet re-opens with Case
still selected and the size shown. Watch the console for errors on each
click.

### 10. Edit screen: the same choice per product line
Files: `resources/views/shop/request-edit.blade.php`, `resources/js/shop/request-edit.js`
What: `withKey()` carries `unit: line.unit ?? 'unit'` and
`case_units: line.case_units ?? line.product?.case_units ?? null`; `onPick()`
sets `case_units: p.case_units ?? null` on a new line and `product` as
today. Per line add a hidden `items[idx][unit]` (`:value="item.product_code && item.case_units ? item.unit : 'unit'"`)
and, inside the `shop-facts--2` block or just under it, the same
`shop-choices` pair shown only when `item.product_code && item.case_units`,
captions as in step 9, radios `x-model="item.unit"` without `name`. The
Quantity label flips to `Cases` the same way. `unlink()` sets `unit = 'unit'`
and `case_units = null`.
Check (browser): edit the request saved in step 9 — the case line opens
with Case selected and "N per case"; switch it to Units, save → board shows
the bare number and `case_units` is null in the database
(`php artisan tinker --execute='echo App\Models\CustomerRequestItem::latest("id")->first()->case_units;'`
prints nothing); switch back to Case, save → "2 cases of N" again.

### 11. Docs
Files: `docs/features/customer-requests.md`, `docs/requests/README.md` is the Planner's — leave it
What: schema block gains the two columns; "Status lifecycle" untouched; add
a short "By the case or by the unit" section after "Product pictures":
where the size comes from, that it is a snapshot, the label wording, and
that the sheet only offers Case when the supplier link says more than one.
Changelog line dated 2026-10-07. Also fix the stale Architecture bullet that
still names `resources/views/customer-requests/` — point it at the Shop
views (board `shop/requests`, `request-show`, `request-edit`, partials).
Check: the doc's route table and JSON stay accurate; `grep -n "customer-requests/" docs/features/customer-requests.md`
shows no reference to the removed views folder.

### 12. Format and build
Files: none new
What: `./vendor/bin/pint --dirty`; `npm run build` so the Shop JS changes
are in the manifest for the browser checks.
Check: pint reports no changes left; build completes without warnings
about the shop entry.

## Verification

Run in order and record the output:

1. `php artisan migrate:status | grep add_unit_to_customer_request_items` → `Ran`.
2. `php artisan test --filter=CustomerRequest` → all pass (28 baseline + new).
3. `php artisan test --filter=ProductSearchApiTest` → pass.
4. `php artisan test --filter=Shop` → pass (view contract and PIN drift unchanged).
5. `php artisan test` → the only failures are the known unrelated set
   (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3,
   `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2,
   `TestScraperControllerTest` ×1); report the exact count.
6. Browser checks from steps 9 and 10, with the console open; note anything
   printed.
7. `git status --short` with pre-existing dirty files marked.

## Risks

- **`CaseUnits` of 1 or 0 is common** (789 null/0 in dev, plus many 1s). The
  rule "offer Case only when `> 1`" is applied in three places (API, service
  snapshot, seeds); keep them identical or the sheet and the stored line
  disagree.
- **`x-show` evaluates bindings on hidden elements.** The Case caption
  reads `picked?.case_units`; a bare `picked.case_units` throws when nothing
  is picked. Same on the edit screen per line.
- **Posting `case_units` from the form must not work.** It is deliberately
  not in `ITEM_KEYS`; the test in step 6 posting `case_units => 99` with
  `unit => 'case'` should still store 6.
- **`quantity` arrives as a string** (`decimal:2` cast); compare with
  `(float)` in `quantityLabel()` for the singular check.
- The `products.search` test fixture gives only one link a `CaseUnits`; if
  another test asserts a whole item array with `assertSame`, it gains the
  new key — search `ProductSearchApiTest` for `assertSame(['id'` before
  assuming.
- Vite: the Shop sheet is served from the built manifest; forgetting
  `npm run build` makes the browser check look like a regression.

## Review

(Planner fills this in after reading implemented.md and the diff.)
