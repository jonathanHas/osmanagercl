# Kitchen supplier orders + standing order

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-16

## Goal

From `/kitchen/products`, a kitchen user clicks **Create Order**, picks a
supplier (default: the one with most kitchen products — Udea), sees that
supplier's kitchen products with images, case size and the last three orders,
types a number of **cases** beside each, adds optional notes, and presses
**Confirm Order**. Confirming logs the order and lands on an order page with
**Download CSV** (`Quantity, Supplier Code, Product Name, Case Size`). A
**Kitchen Orders** history page lists past orders per supplier with re-download.
A **Standing Order** page stores a weekly case quantity per product; those
quantities are pre-filled on the create page every time it opens (nothing is
sent or logged automatically).

## Context

Decisions the user made in the planning interview (do not re-open):

- One order per supplier. Quantity is **cases**; `supplier_link.CaseUnits`
  already matches the supplier's case size and single-unit items have
  CaseUnits = 1 (treat null/0 as 1).
- Standing order = pre-fill only. No scheduler, no auto-logging, no email.
- History column = last 3 orders (qty + date) per product, for the selected
  supplier only.
- Confirm first, then download. No CSV of an unlogged draft.
- Table rows with large thumbnails (Udea order-review feel), sticky footer.
- Access = same as rest of Kitchen: routes are auth-only, sidebar links inside
  the existing `hasAnyRole(['admin','manager'])` block. No new permission.

What the codebase already gives us:

- **Kitchen products**: `app/Models/KitchenProduct.php` (fillable
  `product_id`, `notes`; `product()` belongsTo `Product` on `ID`;
  **`$connection = 'mysql'` hard-coded** — see Gotchas). Migration
  `database/migrations/2026_01_20_114028_create_kitchen_products_table.php`.
  Controller `app/Http/Controllers/KitchenProductController.php`; view
  `resources/views/kitchen/products/index.blade.php` (header slot lines 6–14
  holds the page links). Live data: 102 kitchen products — Udea 74,
  Independent 19, Udea Veg 4, Misc 1, Mossfield 1, 3 with no supplier link,
  1 whose POS product row is missing, 5 with empty `CaseUnits`, 3 with empty
  `SupplierCode`.
- **POS models** (connection `pos`, read-only): `app/Models/Product.php`
  (table `PRODUCTS`, PK `ID` string; relations `supplierLink()` hasOne,
  `supplierLinks()` hasMany on `Barcode`↔`CODE`, `supplier()` hasOneThrough,
  `stockCurrent()`; `hasImage()` reads the `IMAGE` blob);
  `app/Models/SupplierLink.php` (table `supplier_link`: `ID, Barcode,
  SupplierCode, SupplierID, CaseUnits, stocked, OuterCode, Cost`);
  `app/Models/Supplier.php` (table `suppliers`, PK `SupplierID` **string**,
  name column `Supplier`).
- **Images**: component `resources/views/components/product-image.blade.php`.
  Props `product, supplierService, size (xs–xl), fit, hover, fallback`. When
  `supplierService` is null it falls back to `$product->image_url` (line ~78,
  `isset($product->image_url)`), so the service can pre-resolve one URL per
  product. Blob-first idiom to copy:
  `app/Services/StockCheckReviewService.php:337` — `route('products.image',
  $product->ID)` when the product has an image, else
  `SupplierService::getExternalImageUrl($product)`
  (`app/Services/SupplierService.php:58`; needs `$product->supplier` and
  `$product->supplierLink` loaded; CDN images exist for Udea ids 5/44/85 by
  barcode and Independent id 37 by supplier code — `config/suppliers.php`).
  Cheap has-image flag without loading blobs:
  `app/Repositories/ProductRepository.php:23-24` —
  `->select('PRODUCTS.*')->addSelect(DB::raw('(CASE WHEN IMAGE IS NOT NULL AND LENGTH(IMAGE) > 0 THEN 1 ELSE 0 END) as has_image'))`.
- **Qty row UI to copy**: `resources/views/fruit-veg/partials/order-table.blade.php`
  lines 24–140 (`qty-decrease`/`qty-increase` buttons, `qty-input`, plain JS);
  product cell with `<x-product-image size="lg" fit="contain" :hover="true">`
  in `resources/views/orders/partials/review-table.blade.php` ~562–590.
- **CSV**: `app/Http/Controllers/OrderController.php:954 export()` returns a
  string response with `Content-Type`/`Content-Disposition` headers;
  `app/Http/Controllers/Management/CashLodgementController.php:286` shows
  `fputcsv`. Use `fputcsv` on `php://temp` and return a string response.
- **Routes**: `routes/web.php` lines 456–508, `Route::prefix('kitchen')`
  inside the `Route::middleware('auth')` group. Anything new must be
  registered **before** the `/{recipe}` wildcard (~line 500);
  `tests/Feature/KitchenWholesalePageTest.php` shows the route-order test.
  Controller imports at lines 23–26.
- **Layout**: every kitchen view is `<x-admin-layout>` with
  `<x-slot name="header">`. The admin layout renders **no flash block** —
  each page prints `session('success')`/`session('error')` itself (see
  `kitchen/products/index.blade.php` lines 20–24). `@stack('scripts')` exists
  at `resources/views/layouts/admin.blade.php:854`; kitchen views use
  `@push('scripts')`. Sidebar KITCHEN SECTION is `admin.blade.php` lines
  300–346 (Recipes / Ingredient Profiles / Kitchen Products / Wholesale).
- **Form requests**: pattern `app/Http/Requests/StoreWholesalePriceRequest.php`.
- **Tests**: `phpunit.xml` uses sqlite `:memory:` for both default and `pos`
  connections (separate DBs). POS tables are hand-created with
  `Schema::connection('pos')->create(...)` in `setUp` and dropped in
  `tearDown`; tests skip when `pdo_sqlite` is missing; `RefreshDatabase`;
  `actingAs(User::factory()->create())`. Examples:
  `tests/Feature/KitchenWholesalePricingTest.php` (widest POS setup),
  `tests/Feature/KitchenIngredientProfileStoreTest.php`. For models pinned to
  `mysql` use the trait `tests/Concerns/AliasesMysqlConnection.php`
  (`$this->aliasMysqlConnectionToTestDatabase()` after `parent::setUp()`),
  as `tests/Feature/LabelTranslationSaveTest.php` does.
- **Existing order system** (`order_sessions`, `order_items`,
  `app/Services/OrderService.php`) is the shop's sales-driven ordering with
  review priorities and statuses. It is **not** reused here.
- **Docs**: `docs/features/kitchen-products.md` (headings Overview / Features /
  Database Schema / API Endpoints / Files / Usage Workflow / Related
  Documentation); `docs/FEATURES_INDEX.md` `## Kitchen Management` (~line
  255) uses `### Title (NEW! YYYY-MM-DD)`; `CHANGELOG.md` `## [Unreleased]` →
  `### Added` entries: emoji title, `(date)`, sub-bullets, closing
  `**Modified**:` path list with `(new)` markers.

## Constraints

- New Laravel-side tables only. **Do not** write to the POS database and do
  not reuse `order_sessions` / `order_items` / `OrderService`.
- New models use the **default** connection (no `$connection` property), like
  `app/Models/OrderSession.php`.
- Order lines are **server-side snapshots**: supplier code, product name and
  case size are copied from POS data at confirm time so CSV re-downloads never
  change when POS data changes. Never trust posted names/codes.
- Quantities are integer cases ≥ 0; only lines with qty > 0 are stored.
- CSV header row must be exactly `Quantity,Supplier Code,Product Name,Case Size`.
- Routes are auth-only (enclosing group). Sidebar links go inside the existing
  admin/manager block. No permission migration.
- Do not commit, push or deploy. Leave `docs/planImp/` untracked as is.
- Run `./vendor/bin/pint` on every new/changed PHP file.

## Out of scope

- Scheduled/automatic ordering, emailing CSVs, supplier API submission.
- Ordering products that are not on the kitchen products list, or products
  with no supplier link.
- Editing or deleting a confirmed order; draft orders; order status workflow.
- Prices/costs on the order or CSV.
- Changing `product-image.blade.php`, `SupplierService`, `KitchenProduct`, or
  any existing order-management code.
- Reworking the kitchen products index beyond adding header links.
- Mobile-specific layouts beyond what Tailwind responsive classes give for free.

## Steps

### 1. Migrations
Files (new):
- `database/migrations/2026_09_16_100000_create_kitchen_orders_table.php`
- `database/migrations/2026_09_16_100001_create_kitchen_order_items_table.php`
- `database/migrations/2026_09_16_100002_create_kitchen_standing_order_items_table.php`

What:

`kitchen_orders`: `id`; `user_id` unsignedBigInteger nullable, index (no FK —
same as `order_sessions`; deleting a user must not delete history);
`supplier_id` string(20) index (POS `SupplierID` is a string);
`supplier_name` string(100) snapshot; `notes` text nullable; `total_cases`
unsignedInteger default 0; `line_count` unsignedInteger default 0;
timestamps. Composite index `(supplier_id, created_at)`. No `ordered_at` —
confirm is single-step, `created_at` is the order moment.

`kitchen_order_items`: `id`; `kitchen_order_id` foreignId constrained to
`kitchen_orders` cascadeOnDelete; `product_id` string(36) index (POS UUID);
`supplier_code` string(50) nullable; `product_name` string(255); `case_units`
unsignedInteger default 1; `quantity` unsignedInteger (cases); timestamps.
Unique `(kitchen_order_id, product_id)`.

`kitchen_standing_order_items`: `id`; `product_id` string(36) **unique** (keyed
by POS product id, not `kitchen_products.id`, because the kitchen toggle
deletes/recreates `kitchen_products` rows and standing quantities must survive
a remove/re-add); `quantity` unsignedInteger; `updated_by` unsignedBigInteger
nullable; timestamps. No `supplier_id` — supplier is derived from the product's
current link at pre-fill time.

Check: `php artisan migrate` runs clean; in tinker
`Schema::hasTable('kitchen_orders') && Schema::hasTable('kitchen_order_items') && Schema::hasTable('kitchen_standing_order_items')` is true;
`php artisan migrate:rollback --step=3` then `php artisan migrate` both clean.

### 2. Models
Files (new): `app/Models/KitchenOrder.php`, `app/Models/KitchenOrderItem.php`,
`app/Models/KitchenStandingOrderItem.php`.

What:
- `KitchenOrder`: fillable `user_id, supplier_id, supplier_name, notes,
  total_cases, line_count`; casts ints; `user()` belongsTo `User`;
  `supplier()` belongsTo `Supplier::class, 'supplier_id', 'SupplierID'`
  (cross-connection belongsTo, same as `OrderSession::supplier()`);
  `items()` hasMany `KitchenOrderItem`; `csvFilename(): string` →
  `kitchen-order-{Str::slug(supplier_name)}-{created_at->format('Y-m-d')}-{id}.csv`.
- `KitchenOrderItem`: fillable `kitchen_order_id, product_id, supplier_code,
  product_name, case_units, quantity`; casts `case_units`/`quantity` int;
  `order()` belongsTo `KitchenOrder`; `product()` belongsTo `Product`
  (`'product_id', 'ID'`).
- `KitchenStandingOrderItem`: fillable `product_id, quantity, updated_by`;
  cast `quantity` int; `product()` belongsTo `Product`.
- None of the three declares `$connection`.

Check: tinker `KitchenOrder::create(['supplier_id'=>'5','supplier_name'=>'Udea'])`
then `->items()->create([...])` works and `->csvFilename()` returns
`kitchen-order-udea-2026-09-16-1.csv`-shaped string; delete the test rows
afterwards (`KitchenOrder::find($id)->delete()`; cascade removes the item).

### 3. Service
File (new): `app/Services/KitchenOrderService.php`. Constructor injects
`SupplierService`.

Methods (signatures are binding; internals are yours):

- `supplierOptions(): Collection` — distinct suppliers among kitchen products
  with counts, sorted by count desc, each `['id' => string, 'name' => string,
  'count' => int]`. Three queries: `KitchenProduct::pluck('product_id')`,
  `Product::whereIn('ID', …)->with('supplierLinks')`,
  `Supplier::whereIn('SupplierID', …)`. A product counts under every supplier
  it has a link to.
- `defaultSupplierId(): ?string` — first of `supplierOptions()` (Udea on live
  data, no hard-coded id).
- `productsForSupplier(string $supplierId): Collection` — rows for the create
  page, sorted by product `NAME`. Load kitchen product ids →
  `Product::whereIn('ID', …)->select('PRODUCTS.*')->addSelect(<has_image raw>)
  ->with(['supplierLinks', 'stockCurrent'])`; keep products having a link with
  `(string) SupplierID === $supplierId`; skip products whose POS row is
  missing; for each: `$link = $product->supplierLinks->firstWhere('SupplierID', $supplierId)`,
  `$product->setRelation('supplierLink', $link)`,
  `$product->setRelation('supplier', $supplier)` (from one `Supplier` lookup),
  then `$product->image_url = $product->has_image ? route('products.image',
  $product->ID) : $this->supplierService->getExternalImageUrl($product)`.
  Return items shaped `['product' => Product, 'kitchen_product' =>
  KitchenProduct, 'supplier_code' => ?string, 'case_units' => int (max(1,
  (int) CaseUnits)), 'stock' => float]`.
- `lastOrdersByProduct(string $supplierId, array $productIds, int $limit = 3): array`
  — **one query**: `KitchenOrderItem` joined to `kitchen_orders`, where
  `kitchen_orders.supplier_id = $supplierId` and `product_id in (...)`,
  ordered by `kitchen_orders.created_at desc`, selecting `product_id, quantity,
  kitchen_orders.created_at as ordered_at`; group in PHP, `take($limit)`.
  Returns `[product_id => [['quantity' => int, 'date' => Carbon], …]]`.
- `standingQuantities(): array` — `KitchenStandingOrderItem::pluck('quantity', 'product_id')->all()`.
- `createOrder(string $supplierId, array $qtyByProductId, ?string $notes, User $user): KitchenOrder`
  — rebuild rows via `productsForSupplier($supplierId)`; for each row take
  `(int) ($qtyByProductId[$product->ID] ?? 0)`, keep `> 0`; ignore posted ids
  not in the list; if no lines throw `InvalidArgumentException`; in
  `DB::transaction` create the order (`supplier_name` from the `Supplier`
  row, `user_id`, `notes`) and items with snapshots
  (`supplier_code`, `product_name = NAME`, `case_units`, `quantity`), then
  set `total_cases`/`line_count`. Return the order with `items` loaded.
- `csv(KitchenOrder $order): string` — `fopen('php://temp', 'r+')`,
  `fputcsv` header `['Quantity', 'Supplier Code', 'Product Name', 'Case Size']`,
  then one row per item with `quantity > 0` ordered by `product_name`
  (`[quantity, supplier_code ?? '', product_name, case_units]`), rewind,
  `stream_get_contents`. (`fputcsv` only quotes when needed, so the header
  line is exactly `Quantity,Supplier Code,Product Name,Case Size`.)
- `saveStandingOrder(array $qtyByProductId, User $user): void` — transaction;
  for each posted id: qty `> 0` → `updateOrCreate(['product_id' => $id],
  ['quantity' => $qty, 'updated_by' => $user->id])`; qty 0/blank → delete
  the row if present. Ids not posted are untouched.
- `allProductsGroupedBySupplier(): Collection` — for the standing page: every
  kitchen product with `product, supplier_code, case_units, image_url,
  orderable`, grouped by supplier name (first link's supplier); groups
  `"No supplier link"` and `"Missing POS product"` last with `orderable =>
  false`. Same eager loads and image resolution as `productsForSupplier`.

Check: tinker —
`app(App\Services\KitchenOrderService::class)->supplierOptions()` shows Udea
first with count 74; `->productsForSupplier('5')->count()` is 74 and every
row has `case_units >= 1`; at least one row has an `image_url` starting
`https://cdn.ekoplaza.nl/`; `DB::connection('pos')->enableQueryLog()` around
the call shows no per-product queries (≤ 4 POS queries total).

### 4. Form requests + controller
Files (new): `app/Http/Requests/StoreKitchenOrderRequest.php`,
`app/Http/Requests/UpdateKitchenStandingOrderRequest.php`,
`app/Http/Controllers/KitchenOrderController.php`.

Rules:
- Store: `supplier_id => required|string|max:20`, `notes =>
  nullable|string|max:2000`, `qty => nullable|array`, `qty.* =>
  nullable|integer|min:0|max:999`.
- Standing: `qty => nullable|array`, `qty.* => nullable|integer|min:0|max:999`.
- `authorize()` returns true (auth middleware gates access).

Controller (constructor injects `KitchenOrderService`; keep each action thin):

| action | verb + path | route name |
|---|---|---|
| `index(Request)` | GET `/kitchen/orders` | `kitchen.orders.index` |
| `create(Request)` | GET `/kitchen/orders/create` | `kitchen.orders.create` |
| `store(StoreKitchenOrderRequest)` | POST `/kitchen/orders` | `kitchen.orders.store` |
| `show(KitchenOrder $kitchenOrder)` | GET `/kitchen/orders/{kitchenOrder}` | `kitchen.orders.show` |
| `csv(KitchenOrder $kitchenOrder)` | GET `/kitchen/orders/{kitchenOrder}/csv` | `kitchen.orders.csv` |
| `standing()` | GET `/kitchen/standing-order` | `kitchen.standing-order.edit` |
| `updateStanding(UpdateKitchenStandingOrderRequest)` | PUT `/kitchen/standing-order` | `kitchen.standing-order.update` |

Behaviour:
- `index`: `?supplier=` filter; `KitchenOrder::with('user')->latest()`,
  filtered by `supplier_id` when given, `paginate(25)->withQueryString()`;
  pass `supplierOptions()` and the selected id.
- `create`: `$supplierId = $request->input('supplier') ?: defaultSupplierId()`.
  If the id is not in `supplierOptions()`, redirect to `create` with the
  default and `error` flash. Pass `rows`, `lastOrders`
  (`lastOrdersByProduct($supplierId, ids)`), `standing`
  (`standingQuantities()`), `suppliers`, `selectedSupplier` (the option
  array), `supplierService` is **not** needed (image URLs are pre-resolved).
- `store`: call `createOrder`; catch `InvalidArgumentException` →
  `redirect()->route('kitchen.orders.create', ['supplier' => $supplierId])
  ->withInput()->with('error', 'Enter a quantity for at least one product.')`;
  success → `redirect()->route('kitchen.orders.show', $order)->with('success',
  'Order logged. Download the CSV below.')`.
- `show`: `load(['items', 'user'])`.
- `csv`: `response($service->csv($order))->header('Content-Type', 'text/csv;
  charset=UTF-8')->header('Content-Disposition', 'attachment;
  filename="'.$order->csvFilename().'"')` — string response (not
  `streamDownload`) so tests can read the body.
- `standing`: pass `groups` (`allProductsGroupedBySupplier()`) and
  `standing` (`standingQuantities()`).
- `updateStanding`: `saveStandingOrder(...)`; `redirect()->route('kitchen.standing-order.edit')->with('success', 'Standing order saved.')`.

Check: `php artisan route:list --path=kitchen/orders` and
`--path=kitchen/standing-order` show the 7 routes with the names above (after
step 5).

### 5. Routes
File: `routes/web.php`.

What: add `use App\Http\Controllers\KitchenOrderController;` next to the other
Kitchen imports (lines 23–26). Inside the kitchen group, **immediately after
the `wholesale` prefix block and before the AJAX endpoints** (i.e. well before
the `/{recipe}` wildcard), add:

```php
// Kitchen supplier orders + standing order (must be before {recipe} wildcard)
Route::prefix('orders')->name('orders.')->group(function () {
    Route::get('/', [KitchenOrderController::class, 'index'])->name('index');
    Route::get('/create', [KitchenOrderController::class, 'create'])->name('create');
    Route::post('/', [KitchenOrderController::class, 'store'])->name('store');
    Route::get('/{kitchenOrder}', [KitchenOrderController::class, 'show'])->name('show');
    Route::get('/{kitchenOrder}/csv', [KitchenOrderController::class, 'csv'])->name('csv');
});
Route::get('/standing-order', [KitchenOrderController::class, 'standing'])->name('standing-order.edit');
Route::put('/standing-order', [KitchenOrderController::class, 'updateStanding'])->name('standing-order.update');
```

Check: `php artisan route:list --path=kitchen` lists the new routes;
`tests/Feature/KitchenOrderRoutesTest.php` (step 8) passes.

### 6. Views
Files (new):
- `resources/views/kitchen/orders/create.blade.php`
- `resources/views/kitchen/orders/partials/product-row.blade.php`
- `resources/views/kitchen/orders/partials/qty-scripts.blade.php` (the
  shared +/- / totals / clear-all JS, included via `@pushOnce('scripts')` by
  the two pages that need it)
- `resources/views/kitchen/orders/show.blade.php`
- `resources/views/kitchen/orders/index.blade.php`
- `resources/views/kitchen/orders/standing.blade.php`

All use `<x-admin-layout>` + `<x-slot name="header">`, print
`session('success')` / `session('error')` at the top of the body (copy the
green box from `kitchen/products/index.blade.php` lines 20–24; use a red
variant for `error`), and use the same card/table Tailwind classes as the
kitchen products page.

**create.blade.php**
- Header: "Create Kitchen Order"; right side: `Order History` →
  `kitchen.orders.index`, `Standing Order` → `kitchen.standing-order.edit`,
  `← Back to Kitchen Products` → `kitchen.products.index`.
- Supplier picker card: small GET form to `kitchen.orders.create` with
  `<select name="supplier" onchange="this.form.submit()">` listing options as
  "Udea (74)"; `<noscript>` submit button.
- Order form: `POST` to `kitchen.orders.store`, `@csrf`, hidden `supplier_id`.
  Table columns: Image | Product | Supplier code | Case size | Last 3 orders |
  Qty (cases). One `@include('kitchen.orders.partials.product-row')` per row.
- `product-row.blade.php` (used by create; standing page passes
  `showHistory=false`):
  - `<x-product-image :product="$row['product']" :supplierService="null"
    size="xl" fit="contain" :hover="true" />` (URL comes from
    `$product->image_url`; grey fallback icon when none).
  - Name (bold), barcode + shop stock small grey; amber badge "no supplier
    code" when the code is empty.
  - Supplier code in `font-mono`.
  - Case size: `"{n} units/case"` or `"single"` when 1.
  - Last 3: up to three chips "12 × 03 Sep" newest first, else "—".
  - Qty cell copied from the fruit-veg partial: `qty-decrease` / `qty-increase`
    buttons with `data-target="qty-{{ $product->ID }}"`,
    `<input type="number" class="qty-input" id="qty-{{ $product->ID }}"
    name="qty[{{ $product->ID }}]" min="0" max="999" step="1"
    value="{{ old('qty.'.$product->ID, $standing[$product->ID] ?? 0) }}">`.
    Small indigo badge "standing" beside the input when
    `($standing[$product->ID] ?? 0) > 0`. Rows with qty > 0 get a
    `bg-indigo-50` class toggled by JS.
- Notes: `<textarea name="notes" maxlength="2000">` above the footer.
- Sticky footer (`sticky bottom-0 bg-white border-t shadow-lg`): "`<span
  id="total-lines">` products · `<span id="total-cases">` cases",
  `Clear all` (`type="button"`, sets every `.qty-input` to 0 and recalcs),
  `Confirm Order` submit button disabled while total cases is 0.
- Group products by category? **No** — flat list sorted by name (keeps the
  74-row page scannable with the browser find).

**qty-scripts.blade.php**: plain JS, no Alpine. Event delegation on
`.qty-decrease` / `.qty-increase` (clamp at 0), `input` on `.qty-input`,
`recalcTotals()` (count inputs > 0, sum values, toggle row highlight and the
submit button), `Clear all`. Run `recalcTotals()` once on load so pre-filled
standing values populate the totals.

**show.blade.php**: header "Kitchen Order #{id} — {supplier_name}" with
primary `Download CSV` → `kitchen.orders.csv` and `Back to Kitchen Orders`.
Body: flash, meta card (date/time, ordered by user name, line count, total
cases, notes), table with exactly the CSV columns (Qty, Supplier code,
Product, Case size), and a secondary link `Order again from {supplier}` →
`kitchen.orders.create?supplier=`.

**index.blade.php**: header "Kitchen Orders" + primary `Create Order`.
Supplier filter GET select (auto-submit) + Clear link. Table: #, Date,
Supplier, Lines, Cases, By, Notes (truncated 60 chars), Actions (`View`,
`CSV`). `{{ $orders->links() }}`. Empty state with a Create link.

**standing.blade.php**: header "Standing Weekly Order" + links to Create Order
/ Order History. Info box: "These quantities are pre-filled every time you
create an order. Nothing is sent or logged automatically." `<form method="POST">`
with `@method('PUT')` to `kitchen.standing-order.update`. One section per
supplier group (heading with count) using the row partial with
`showHistory=false` and `size="lg"` thumbnails; non-orderable groups rendered
greyed with no input. Sticky footer: "N products with a standing quantity"
(`#total-lines`), `Save Standing Order` submit (always enabled — saving all
zeros is a valid way to clear).

Edits to existing views:
- `resources/views/kitchen/products/index.blade.php` header slot (lines 6–14):
  add a primary orange button `Create Order` → `kitchen.orders.create` as the
  first item, and a text link `Order History` → `kitchen.orders.index`.
- `resources/views/layouts/admin.blade.php` KITCHEN SECTION: after the
  "Kitchen Products" anchor add `Kitchen Orders` (`kitchen.orders.index`,
  active on `request()->routeIs('kitchen.orders.*')`) and `Standing Order`
  (`kitchen.standing-order.edit`, active on
  `routeIs('kitchen.standing-order.*')`), same markup as the siblings.

Check: logged in as an admin, `/kitchen/products` shows the Create Order
button; `/kitchen/orders/create` loads with Udea selected, 74 rows, images
visible for most rows, totals `0 products · 0 cases`, Confirm disabled; typing
`2` in one row enables Confirm and shows `1 products · 2 cases`; switching the
select to Independent reloads with 19 rows; `/kitchen/standing-order` saves a
quantity, and `/kitchen/orders/create` then shows it pre-filled with the
"standing" badge and non-zero totals on load; Confirm logs an order, the show
page's Download CSV returns a file whose first line is
`Quantity,Supplier Code,Product Name,Case Size`; `/kitchen/orders` lists it;
after a second order the create page's Last-3 column shows both.

### 7. Documentation
Files: `docs/features/kitchen-orders.md` (new), `docs/features/kitchen-products.md`,
`docs/FEATURES_INDEX.md`, `CHANGELOG.md`.

What:
- `kitchen-orders.md`: same headings as `kitchen-products.md`. Cover: per-
  supplier create page, quantities are cases, standing order = pre-fill only,
  snapshots on order items, CSV columns, route table, the three tables, files
  list, usage workflow, related docs (link to `kitchen-products.md`).
- `kitchen-products.md`: one "Create Order" bullet under Features and a link
  under Related Documentation.
- `FEATURES_INDEX.md`: under `## Kitchen Management`, add above the Wholesale
  entry: `### Kitchen Supplier Orders (NEW! 2026-09-16)` with bullets and
  `📖 [Kitchen Orders Documentation](./features/kitchen-orders.md)`.
- `CHANGELOG.md`: first entry under `## [Unreleased]` → `### Added`:
  `**🧾 Kitchen orders can be built, logged and exported per supplier**
  (2026-09-16)` with sub-bullets (what/why; cases not units; standing order is
  pre-fill only; snapshots keep CSVs stable; tests added) and a
  `**Modified**:` list of every file in this plan with `(new)` markers.

Check: the four files render as Markdown; every path listed in the changelog
entry exists.

### 8. Tests
Files (new, `tests/Feature/`):
`KitchenOrderRoutesTest.php`, `KitchenOrderCreatePageTest.php`,
`KitchenOrderStoreTest.php`, `KitchenOrderCsvTest.php`,
`KitchenStandingOrderTest.php`, `KitchenOrderHistoryTest.php`; plus a trait
`tests/Concerns/CreatesKitchenOrderPosTables.php` (new) with `createPosTables()`
/ `dropPosTables()` creating on `Schema::connection('pos')`:
`PRODUCTS` (`ID` string pk, `NAME`, `CODE`, `REFERENCE` nullable, `CATEGORY`
nullable, `IMAGE` binary nullable — required by the `LENGTH(IMAGE)` raw
select), `supplier_link` (`ID` autoinc, `Barcode`, `SupplierCode` nullable,
`SupplierID`, `CaseUnits` nullable int, `stocked` bool default 0, `OuterCode`
nullable, `Cost` nullable), `suppliers` (`SupplierID` string pk, `Supplier`),
`STOCKCURRENT` (`LOCATION`, `PRODUCT`, `ATTRIBUTESETINSTANCE_ID` nullable,
`UNITS` decimal). Every DB test: skip without `pdo_sqlite`, `RefreshDatabase`,
`AliasesMysqlConnection` with `aliasMysqlConnectionToTestDatabase()` after
`parent::setUp()` (needed because `KitchenProduct` pins `mysql`), drop POS
tables in `tearDown`.

Seed helper for the page tests: suppliers `5 => Udea`, `37 => Independent`;
products A (Udea, CaseUnits 6, code `U-A`), B (Udea, CaseUnits null, code
null), C (Independent, CaseUnits 12); `KitchenProduct::create` for all three.

| file | cases |
|---|---|
| Routes | `/kitchen/orders`, `/kitchen/orders/create`, `/kitchen/standing-order` match `kitchen.orders.index` / `kitchen.orders.create` / `kitchen.standing-order.edit` (not `kitchen.show`), pattern of `KitchenWholesalePageTest`; unauthenticated GET redirects to login; unauthenticated POST/PUT JSON → 401. No DB. |
| Create page | with standing qty 4 for A: `GET create?supplier=5` is 200, sees A and B, does not see C, sees `name="qty[A]"` with `value="4"` and the text `standing`, B shows `single`, B shows "no supplier code"; `GET create` with no param selects supplier 5 (2 products > 1); `GET create?supplier=999` redirects to create. |
| Store | `qty[A]=3, qty[B]=0, qty[C]=2` → one `kitchen_orders` row (`supplier_id` '5', `supplier_name` 'Udea', `total_cases` 3, `line_count` 1, `user_id` set) and one item (`product_id` A, `supplier_code` 'U-A', `product_name`, `case_units` 6, `quantity` 3); redirect to show. All-zero → redirect to create with `error`, zero rows. `qty[A]=-1` → validation error on `qty.A`. |
| CSV | build `KitchenOrder` + 3 items directly (one with `product_name` containing a comma, one inserted with `quantity` 0); GET csv → 200, `Content-Type` starts `text/csv`, `Content-Disposition` contains `attachment; filename="kitchen-order-udea-`; body line 1 exactly `Quantity,Supplier Code,Product Name,Case Size`; comma name is quoted; zero-qty line absent; lines sorted by name. No POS tables. |
| Standing | `PUT qty[A]=4, qty[B]=2` → 2 rows; `PUT qty[A]=6, qty[B]=0` → A is 6, B deleted, C untouched; `GET standing-order` shows headings `Udea` and `Independent` and `value="6"` for A. |
| History | 4 orders for supplier 5 with distinct `created_at` + 1 for supplier 37, all containing A; `lastOrdersByProduct('5', [A])[A]` has 3 entries, newest first, qty/date correct, supplier 37 excluded; `GET orders?supplier=5` shows 4 rows and not the 37 one. Service-level, no POS tables. |

Check: `php artisan test --filter=KitchenOrder` and
`php artisan test --filter=KitchenStandingOrder` → all pass;
`php artisan test --filter=Kitchen` → no regressions.

### 9. Format
What: `./vendor/bin/pint` on all new/changed PHP files.
Check: pint reports no changes on a second run.

## Verification

Run in order at the end:

1. `php artisan migrate:status` → the three `2026_09_16_*` migrations are `Ran`.
2. `php artisan route:list --path=kitchen` → 7 new routes, all listed before
   `kitchen/{recipe}`.
3. `php artisan test --filter=Kitchen` → all pass (existing + new).
4. `php artisan test` → no new failures versus baseline (record any
   pre-existing failures in `implemented.md`).
5. `./vendor/bin/pint --test` → clean.
6. Manual browser check as an admin user, following the step-6 Check list
   (create → confirm → CSV → history → standing → pre-fill). Paste the first
   two lines of a downloaded CSV into `implemented.md`.
7. Query-count check: in tinker, `DB::connection('pos')->enableQueryLog();
   app(App\Services\KitchenOrderService::class)->productsForSupplier('5');
   count(DB::connection('pos')->getQueryLog())` ≤ 4.

## Risks

- **`KitchenProduct` mysql pin in tests** — any test that creates kitchen
  products without `AliasesMysqlConnection` will hit the real dev MySQL
  database. Use the trait; do not add a second sqlite `mysql` connection.
- **Duplicate `supplier_link` rows per barcode** — `supplierLink()` hasOne is
  non-deterministic. Always pick the link with the selected `SupplierID` from
  `supplierLinks` and `setRelation` before image resolution.
- **`has_image` is a select-time column**, not an accessor; without the raw
  `addSelect` it is null and every product falls back to the CDN (Udea /
  Independent still work; others lose their POS image).
- **Supplier id typing** — POS `SupplierID` is a string; `config/suppliers.php`
  lists ints. Store and compare as strings; `SupplierService` casts to int
  internally.
- **Data edge cases** — missing POS product (skip on create, grey on
  standing), no supplier link (never orderable), empty `SupplierCode` (orderable,
  blank in CSV, badge on row), empty `CaseUnits` (normalise to 1 in the
  service, never in the view).
- **Sticky footer + `hover` image previews** — the preview teleports to
  `body` with `z-[99999]`, so it will float above the footer; acceptable.
- **`old()` after a failed store** — the redirect must carry
  `?supplier=` (the create page reads it) plus `withInput()` so typed
  quantities survive.

## Review

Reviewed 2026-09-16 against `implemented.md`, `git status`/`git diff`, and by
rereading every new file. Verification commands rerun by the Planner.

### Criteria

| # | Criterion | Result |
|---|-----------|--------|
| 1 | Three migrations, default connection, columns/indexes as specified; `migrate:status` shows all three `Ran` | PASS |
| 2 | Three models, no `$connection`, relations + `csvFilename()` | PASS |
| 3 | `KitchenOrderService` — all 9 method signatures as specified; Udea default (74), `productsForSupplier('5')` = 74 rows with 4 POS queries; `case_units >= 1`; link pinned via `supplierLinks->first(SupplierID)` + `setRelation` before image resolution; snapshots built server-side; CSV header byte-exact | PASS |
| 4 | Form requests + thin controller; all-zero → redirect to `create?supplier=` with `withInput()` + error; CSV is a string response with `text/csv; charset=UTF-8` + attachment filename | PASS |
| 5 | Routes registered after the `wholesale` block, before `{recipe}`; router match confirms `/kitchen/orders`, `/create`, `/standing-order`, `/{id}/csv` resolve to the new names | PASS |
| 6 | Views: create (supplier picker, xl thumbnails via pre-resolved `image_url`, last-3 chips, +/- steppers, standing badge, notes, sticky footer with Confirm disabled at 0), show, index (filter + pagination + CSV link), standing (grouped, non-orderable groups greyed), header links on `/kitchen/products`, two sidebar entries inside the admin/manager block | PASS (server-rendered output verified through the HTTP kernel by the Implementer; JS see note below) |
| 7 | Docs: `kitchen-orders.md` (new, same heading set), `kitchen-products.md`, `FEATURES_INDEX.md`, `CHANGELOG.md` entry with full `**Modified**` list | PASS |
| 8 | Tests: 22 new cases across 6 files + `CreatesKitchenOrderPosTables` trait; `php artisan test --filter=Kitchen` → 94 passed (298 assertions) rerun by Planner | PASS |
| 9 | `./vendor/bin/pint --test` on all 18 new/changed PHP files → PASS, rerun by Planner | PASS |
| — | Constraints: no POS writes, no reuse of `order_sessions`, nothing committed, `docs/planImp/` untouched | PASS |

### Deviations — all accepted

1. Custom `csvLine()` instead of `fputcsv`: correct call. The plan was wrong
   that `fputcsv` only quotes when needed — it quotes any field containing a
   space, which would have broken the exact-header constraint. The replacement
   is RFC 4180 quoting and is covered by `KitchenOrderCsvTest`.
2. Browser check replaced by a kernel-driven run: acceptable given no Chrome
   extension in that session. The Planner's session had no extension either.
3. Empty-state render instead of a redirect loop when no supplier exists: correct.
4. `AliasesMysqlConnection` on the history test: correct (index page calls
   `supplierOptions()` → `KitchenProduct`).

### Not verified by either session

The quantity JavaScript (`qty-scripts.blade.php`) was reviewed by eye only:
event delegation on `.qty-decrease/.qty-increase`, `input`/`change` recalc,
clamp 0–999, `Clear all`, `recalcTotals()` on load, Confirm disabled at 0.
The script is pushed to `@stack('scripts')` at `admin.blade.php:870` (inside
`<body>`), so the `DOMContentLoaded` listener is registered in time. A
standing quantity saved through the UI by user #5 at 14:16 shows the standing
page form round-trips. **User to confirm in a browser** on
`/kitchen/orders/create`: totals change as you type and press +/−, Confirm
enables above 0 cases, Clear all resets, pre-filled standing rows give
non-zero totals on load.

### Follow-ups (not blocking; new plan if wanted)

- `KitchenOrderService::loadProducts()` docblock says "without pulling IMAGE
  blobs" but selects `PRODUCTS.*`, so the blobs are transferred (fast enough
  on 74 rows). Select explicit columns and fix the comment.
- Independent products cost one `supplier_image_cache` query each (19 on live
  data) inside `SupplierService::getExternalImageUrl()` — out of scope here;
  a `whereIn` prefetch in `SupplierService` would remove it.
- `createOrder()` calls `productsForSupplier()`, which resolves image URLs it
  never uses; a lighter internal loader would avoid that on confirm.
- `KitchenStandingOrderItem::updatedBy()` is unused; harmless.

Status set to **ACCEPTED**. Nothing committed. User: archive with
`mkdir -p docs/planImp/archive/2026-09-16-kitchen-orders && mv docs/planImp/plan.md docs/planImp/implemented.md docs/planImp/archive/2026-09-16-kitchen-orders/`
