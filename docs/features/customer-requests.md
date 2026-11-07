# Customer Requests

## Overview

Customers regularly ask staff to hold or pre-order something we stock, or to source a product we don't carry. These used to be logged in a spreadsheet, where nothing reminded anyone to put the item aside when the delivery arrived or on the day the customer wanted it, and nothing recorded who took the request.

Customer Requests moves that spreadsheet into the app:

- A **public board** at `/customer-requests` that renders without a login, so the shop-floor tablet can show it all day. Due today / overdue requests sit at the top.
- **Every write needs a login** and the `customer-requests.manage` permission, so the user who took the request and every status change is recorded.
- A request is one customer plus a wanted-by date and **one or more lines**. Each line is either a stocked POS product (picked by barcode scan or typeahead) or a free-text item to source, and carries its own status.
- Reminders in three places: the board, a dashboard banner counting requests due and items put aside awaiting collection, and a **"Put aside for …" flag on the legacy delivery match / scan screen** when a delivered barcode is on an open request.

## Architecture

### Components
- **`CustomerRequestController`** (`app/Http/Controllers/`): thin; the public `index` plus staff-only create / edit / show / cancel / line status / product search.
- **`CustomerRequestService`** (`app/Services/`): all business logic — creating and syncing lines, the status lifecycle (`changeItemStatus`), the board grouping, dashboard counts, the barcode lookup used by delivery screens, and the POS product search.
- **`CustomerRequestRequest`** / **`UpdateCustomerRequestItemStatusRequest`** (`app/Http/Requests/`): validation. The first also checks that every `items.*.id` on an update belongs to the request being edited.
- **Models**: `CustomerRequest`, `CustomerRequestItem`, `CustomerRequestItemStatusLog`.
- **`<x-shop-layout>`** (`app/View/Components/ShopLayout.php`, `resources/views/layouts/shop.blade.php`): every customer-request page renders in the Shop shell since cycle 14; `BoardLayout` is gone. It renders for guests, and its `guestRefresh` prop gives a signed-out viewer a 5-minute `<meta http-equiv="refresh">` while signed-in users keep the stale-session check instead, so nobody is reloaded mid-edit.
- **Views** (Shop screens in `resources/views/shop/`): `requests` (the board, staff rows and guest cards), `request-show` (detail + status history), `request-edit` (header and lines, Alpine `resources/js/shop/request-edit.js`), and partials `shop/partials/request-row` (staff row), `request-card` (guest card) and `request-form` (the New request sheet, Alpine `resources/js/shop/requests.js`). `create` redirects to the board with the sheet open.
- **Delivery hook**: `resources/views/delivery-legacy/partials/customer-request-badge.blade.php`, included after every product name on the match page, plus a summary card and a scanner prompt with a one-tap **Mark put aside** button.

### Database Schema

All three tables live on the app (Laravel) database. `product_code` is the POS `PRODUCTS.CODE` (barcode) and is deliberately **not** a foreign key — it crosses databases.

```sql
CREATE TABLE customer_requests (
    id              BIGINT PRIMARY KEY,
    customer_name   VARCHAR(120) NOT NULL,          -- free text, not linked to the accounts `customers` table
    customer_phone  VARCHAR(40) NULL,
    wanted_on       DATE NULL,                      -- "due today" from this date
    notes           TEXT NULL,
    closed_at       TIMESTAMP NULL,                 -- set when no line is still open
    created_by      BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    updated_by      BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    closed_by       BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at, updated_at
);

CREATE TABLE customer_request_items (
    id                  BIGINT PRIMARY KEY,
    customer_request_id BIGINT NOT NULL REFERENCES customer_requests(id) ON DELETE CASCADE,
    product_code        VARCHAR(64) NULL,           -- POS barcode; NULL for a "source this" line
    product_name        VARCHAR(255) NULL,          -- snapshot of the POS name at pick time
    description         VARCHAR(255) NOT NULL,      -- product name on pick, or the free text
    quantity            DECIMAL(10,2) NOT NULL DEFAULT 1,   -- how many of `unit`
    unit                VARCHAR(8) NOT NULL DEFAULT 'unit', -- 'unit' | 'case'
    case_units          SMALLINT UNSIGNED NULL,     -- supplier case size snapshotted when taken by the case
    notes               VARCHAR(500) NULL,
    hold_for_collection TINYINT(1) NOT NULL DEFAULT 1,  -- 1: put aside for the customer; 0: just get it in, mark done
    arrived_at          TIMESTAMP NULL,                 -- stamped by the Receiving scan endpoints (cycle 3)
    arrived_quantity    DECIMAL(10,3) NULL,             -- the scan session's running total for the barcode
    arrived_session_id  VARCHAR(64) NULL,               -- POS deliveriesScan.ID, no FK
    position            INT UNSIGNED NOT NULL DEFAULT 0,
    status              VARCHAR(20) NOT NULL DEFAULT 'pending',
    status_changed_at   TIMESTAMP NULL,
    status_changed_by   BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at, updated_at,
    INDEX (status), INDEX (status, product_code)    -- delivery lookup by barcode
);

CREATE TABLE customer_request_status_logs (        -- append-only audit trail (short name: the FK name must fit MySQL's 64-char limit)
    id                       BIGINT PRIMARY KEY,
    customer_request_item_id BIGINT NOT NULL REFERENCES customer_request_items(id) ON DELETE CASCADE,
    from_status              VARCHAR(20) NULL,
    to_status                VARCHAR(20) NOT NULL,
    user_id                  BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    note                     VARCHAR(255) NULL,
    created_at               TIMESTAMP NOT NULL
);
```

### Status lifecycle

| Status | Meaning | Open? |
|---|---|---|
| `pending` | Taken, nothing done yet | yes |
| `ordered` | On a supplier order | yes |
| `put_aside` | Arrived and physically set aside | yes (awaiting collection) |
| `collected` | Customer has it | no |
| `done` | Finished without a collection: got in and put on the shelf, or simply dealt with (requests cycle 2) | no |
| `not_available` | Could not be sourced | no |
| `cancelled` | Customer no longer wants it | no |

Allowed moves (`CustomerRequestItem::ALLOWED_TRANSITIONS`): `pending → ordered | put_aside | done | not_available | cancelled`; `ordered → put_aside | done | not_available | cancelled | pending`; `put_aside → collected | ordered | cancelled`; `collected → put_aside`; `done → ordered | pending`; `not_available → pending`; `cancelled → pending`. The backwards moves exist to undo a mis-tap on the tablet. Anything else throws a `DomainException`, which the controller turns into a flash error or a 422 JSON response.

`done` is allowed from any pending or ordered line, held or not, so a request taken as "put aside" that turns out to need no holding is still cleared in one tap (the row's **Done, nothing to put aside** menu item). Something already put aside is *collected*, never *done*.

A request's `closed_at` is recomputed by `CustomerRequest::refreshClosedState()` after every line change: it closes when no line is `pending` / `ordered` / `put_aside` and reopens when a line is added or moved back. **All status writes must go through `CustomerRequestService::changeItemStatus()`** so this and the log stay correct.

### Data Flow
1. Staff member signs in and opens **New request** from the board or the sidebar.
2. Types the customer's name and phone, a wanted-by date, then scans or searches products (typeahead against POS via `Product::search()`), or adds a free-text "source a new product" line.
3. `CustomerRequestService::create()` stores the header with `created_by`, snapshots the POS product name into each linked line, and writes a `null → pending` log per line.
4. The board (`/customer-requests`) shows it under **Open requests**, or **Due today / overdue** once `wanted_on` arrives. The dashboard banner counts it from that day.
5. When the delivery is checked in on `/delivery-legacy/match`, any matched barcode on a `pending` / `ordered` line shows a pink **Put aside for &lt;customer&gt; × qty** badge and the summary card lists them. Scanning the barcode shows the same prompt in the scanner panel with a **Mark put aside** button (calls the JSON status endpoint).
6. Staff tap **Put aside**, then **Collected** when the customer comes in. The request closes itself once every line is terminal and drops off the board (still visible under "Show closed (last 30 days)" for staff, and on its detail page with the full status history).

## Configuration

No environment variables. One permission:

| Permission | Granted to |
|---|---|
| `customer-requests.manage` | employee, manager (inherited), admin (implicit) |

**Deploy step**: none beyond `php artisan migrate --force`. The permission is created and granted by migration `2026_09_26_120000_add_customer_requests_and_voucher_permissions.php`. It is also in `RolesAndPermissionsSeeder`, but the seeder is for **fresh installs only** — it must never be run against the live database, which holds permissions the seeder does not list. Until that migration existed this section said to run the seeder on deploy, nobody did, and the permission was simply absent on production, so the Home tile was invisible to everyone (2026-09-26).

## Product pictures

Every pre-order line shows the product's picture — on the staff board, the public
guest board, the detail page's item list, and the edit page's seeded lines. Sourcing
lines are free text with no product behind them and show nothing, not even a
placeholder, so their layout is unchanged.

The picture is resolved by `ProductSearchService::imageUrlsByCode()`, the same rules
the product search uses (POS photo first, supplier CDN second), so a line shows what
the staff member saw when they picked the product. One batched lookup per board.

**Guests see every picture** (cycle 22). Supplier CDN URLs always worked for
anyone; a till photo is served by the board's own public route,
`customer-requests.photo` — `GET /customer-requests/photo/{code}` — rather than by
`products.image`, which stays behind `auth` + `products.view`.

That route is public but deliberately narrow:

- it returns a **112 px JPEG** and never the stored photo;
- only for a product code that appears on a request line whose request is open or
  was closed in the last 30 days — the same lines the board shows. Anything else,
  including a product with no photo or a blob that cannot be decoded, is **404**, so
  it cannot be used as a general product-image endpoint;
- it is throttled to 120 requests a minute.

The thumbnails are cached by `ProductThumbnailService` on the `local` disk, sharing
the `fv-thumbs/` folder with the fruit-and-veg ones (keys hash the code and the
blob, so they cannot collide). `?v=` is the first 8 hex of the photo's md5; when it
matches, the response is `Cache-Control: public, max-age=604800, immutable`, so a
board that is already open re-renders with no image requests. A wrong version is
served short-lived, so a stale link can never pin an old picture.

## By the case or by the unit

A pre-order line can be taken **by the case** or **by the unit** (requests cycle 1,
2026-10-07). When staff pick a product on the New request sheet, the picked row
shows what the app knows about it — `Case of 6 · 7 in stock · €6.25` (or `Sold
singly`) — and, when the supplier sells it in cases, two cards: **Units** and
**Case**. Choosing Case changes the Quantity label to Cases. The edit screen has the
same choice per product line.

- **Where the size comes from**: `supplier_link.CaseUnits` on the POS (read only),
  the same figure supplier orders use. Case is only offered when it is **more than
  1**; the product search API's `case_units`, the service's snapshot
  (`CustomerRequestService::caseUnitsByCode()`) and the edit-screen seeds all apply
  that rule.
- **Snapshot**: the server stores `case_units` when the line is saved by the case;
  a posted `case_units` is ignored. Editing a case line keeps the stored size even
  if the supplier's case size has changed since; switching a line to units clears it.
  A sourcing line is always by the unit.
- **`quantity` keeps meaning "how many of the chosen unit"**: "2 cases of 6" is
  `quantity 2, unit case, case_units 6`, not 12.
- **Wording** (`CustomerRequestItem::quantityLabel()`, the only place it lives):
  units → the bare number (`3`, `1.5`); cases → `1 case of 6`, `2 cases of 6`, or
  `2 cases` when the size is unknown. The staff row, guest card, detail page, the
  delivery match page badge, summary card and scanner prompt, and the Shop delivery
  scan flag all use it; the delivery JSON carries `unit`, `case_units` and
  `quantity_label` next to `quantity`.

## Put aside, or just get it in

Not every request is held for the customer. Many are a customer asking us to get
something in: it arrives, goes on the shelf, and there is nothing to put aside and
nobody to wait for. Until requests cycle 2 (2026-11-07) those lines had to be walked
through Put aside → Collected to get them off the board.

Each line now carries **`hold_for_collection`**:

- **Put aside** (`1`, the default, and what every line taken before the choice
  existed reads as): the lifecycle is unchanged — Ordered → Put aside → Collected,
  the delivery screens say **Put aside for …**, the dashboard counts it once put aside.
- **Just get it in** (`0`): the strip is Ordered → Done. The row's primary button is
  **Done** from pending or ordered (Mark ordered moves to the more menu); the row
  carries a *Just get it in* pill; the delivery badge reads **Requested by …** and the
  scanner prompt's button is **Mark done** (it PATCHes `done` instead of `put_aside`);
  the Shop delivery scan flag says *Requested by* for these and *Put aside for* for
  held lines.

The choice is made on the **New request** sheet (a two-card choice under the item,
"When it arrives": *Put aside — hold it for them to collect* / *Just get it in — onto
the shelf, mark done*) and can be changed per line on the edit screen. The server
reads `items.*.hold_for_collection` (`boolean`); a payload without it leaves a new
line held and an edited line as it was, so the JSON callers and older forms behave
as before. Helpers: `CustomerRequestItem::isHeldForCollection()`,
`lifecycleSteps()` (the strip, in order) and `handlingLabel()`. Factory state:
`justGetItIn()`.

**When the request was taken** is shown next to the due date since the same cycle:
*Taken Mon 3 Nov by Jon* under the row's meta on the staff board (the date block is
the due date), *Taken Mon 3 Nov* on the guest card, *taken …* in the delivery badge's
tooltip, and `taken_on` in the scan-increment JSON next to `wanted_on`. It is the
request's `created_at`; nothing new is stored.

## Arrivals from Receiving (requests cycle 3)

The board can tell when a requested product has come in. Receiving writes every
scanned barcode to the POS `deliveriesScanItems` table, which is indexed by
session only (125k rows on production), so the board never searches it. Instead
the two scan endpoints stamp the request lines at scan time:

- `DeliveryLegacyController::incrementScanQuantity()` (the Shop scan screen and
  the office scanner) and `updateScannedQuantity()` (typed corrections) call
  `CustomerRequestService::recordArrival($barcode, $sessionTotal, $sessionId)`
  after writing the scan row. Every **pending or ordered** line for that barcode
  gets `arrived_at` (now), `arrived_quantity` (the session's running total for the
  barcode) and `arrived_session_id` (POS `deliveriesScan.ID`, no FK). A
  correction back to zero clears the stamp this session made.
- `CustomerRequestItem::hasArrived()` is true only while the line is still
  pending or ordered; once it is put aside or done the stamp stays as history and
  the detail page shows *Arrived Tue 5 Nov 14:02 · 6 scanned in Receiving*, with an
  **Open the delivery** link when the Receiving session still exists
  (`ReceivingService::session()`; a merged or deleted session, or a POS that is
  down, just means no link).
- **Board**: an arrived line carries a green *Arrived Tue 5 Nov · 6 scanned* pill
  and sorts first in its group. Its next step becomes the prominent button: **Put
  aside** for a held line (even from pending; Mark ordered moves to the menu) and
  **Done** for a just-get-it-in line. The guest card shows *Arrived Tue 5 Nov*.
- **Dashboard** banner: `dashboardCounts()['arrived']` adds "N items have arrived
  and are not yet put aside", and an arrival alone is enough to show the banner.
  The scan-increment JSON carries `arrived_at` and `arrived_quantity`.
- No status is changed automatically: staff still confirm the item is physically
  set aside (or done) with the button.

**Deploy step**: after `migrate`, run `php artisan customer-requests:backfill-arrivals`
once. It stamps open pending / ordered lines from scans made after the line reached
its current status (one query on the POS scan table for those barcodes) and skips
lines already stamped, so it is safe to re-run. Factory columns default to null.

## Usage

### User Perspective

**Shop-floor tablet (no login)** — open `/customer-requests`. The page refreshes itself every 5 minutes. Due / overdue requests are at the top with a red or amber badge. Nothing can be changed without signing in; the header has a **Staff sign in** link.

**Taking a request** — sign in, **New request**, fill in the customer and date, scan or search for products (Enter adds the highlighted match, so a barcode scanner adds a product in one go), or **+ Source a new product** for something we don't stock. Save.

**Progressing a request** — on the board each line shows buttons for its allowed next statuses (Ordered, Put aside, Collected, Not available, Cancel). A line taken as *Just get it in* shows **Done** instead of Put aside / Collected; a held line that needs no holding after all has **Done, nothing to put aside** in its more menu. **Cancel request** on the card cancels every open line. **Edit** changes details and lines (including the put-aside choice) but never a status; lines that have moved past pending cannot be removed there.

**On a delivery** — on `/delivery-legacy/match` look for the pink **Put aside for …** badges and the "Put aside for customer requests" card above the filters. In the scanner, a scanned product on an open request shows a pink prompt with **Mark put aside**.

### Developer Perspective

#### Routes

| Method | Path | Name | Auth |
|---|---|---|---|
| GET | `/customer-requests` | `customer-requests.index` | **none** (public board) |
| GET | `/customer-requests/create` | `customer-requests.create` | permission |
| POST | `/customer-requests` | `customer-requests.store` | permission |
| GET | `/customer-requests/{customerRequest}` | `customer-requests.show` | permission |
| GET | `/customer-requests/{customerRequest}/edit` | `customer-requests.edit` | permission |
| PUT | `/customer-requests/{customerRequest}` | `customer-requests.update` | permission |
| POST | `/customer-requests/{customerRequest}/cancel` | `customer-requests.cancel` | permission |
| PATCH | `/customer-requests/items/{item}/status` | `customer-requests.items.status` | permission; JSON when `Accept: application/json` |

The public route is registered **outside** the `Route::middleware('auth')` group in `routes/web.php`; everything else is inside it under `permission:customer-requests.manage`, so guests hitting a write URL are redirected to login rather than getting a bare 403.

#### Code Examples
```php
$service = app(\App\Services\CustomerRequestService::class);

// Take a request: one stocked line (name snapshotted from POS) and one to source
$request = $service->create(
    ['customer_name' => 'Jane Doe', 'customer_phone' => '087 123 4567', 'wanted_on' => '2026-09-14'],
    [
        ['product_code' => '5000000000017', 'quantity' => 2],
        ['description' => 'Gluten-free sourdough', 'quantity' => 1, 'notes' => 'Seeded if possible'],
    ],
    $user
);

// Progress a line (throws DomainException on an illegal move)
$service->changeItemStatus($request->items->first(), 'ordered', $user, 'Udea order #123');

// What a delivery screen needs: open lines keyed by barcode
$byBarcode = $service->awaitingArrivalByBarcode(['5000000000017', '5000000000024']);
```

### Integration Points
- **Legacy delivery match screen** (`DeliveryLegacyController::match()` and `incrementScanQuantity()`): a separate Eloquent lookup on the app database keyed by barcode. The raw POS queries are untouched — the two databases cannot be joined.
- **Dashboard** (`/dashboard` closure in `routes/web.php`, `resources/views/dashboard.blade.php`): `dashboardCounts()` feeds an indigo banner modelled on the Amazon pending one.
- **POS products**: `CustomerRequestItem::product()` is a cross-connection `belongsTo` on `CODE`. Never eager-load it in lists; `product_name` is the snapshot to display.
- **Sidebar**: the Customer section now shows to anyone with either `customer-invoices.manage` or `customer-requests.manage`; the invoice links stay behind the invoice permission.

## Testing

- `tests/Feature/CustomerRequestTest.php` — public board (guest vs staff), auth and permission gates, create with snapshot and log, validation and `old()` repopulation, update sync rules, the status lifecycle and auto-close/reopen, cancel, product search, the barcode lookup, the show page.
- `tests/Feature/CustomerRequestDashboardTest.php` — banner hidden at zero, correct counts otherwise.
- `tests/Feature/CustomerRequestDeliveryFlagTest.php` — the match page badge and summary card, the scan-increment JSON carrying `customerRequests`, arrival stamping and clearing by the scan endpoints, and the backfill command (builds every POS table the legacy raw queries touch in sqlite).
- `tests/Unit/CustomerRequestItemTransitionsTest.php` — the transition matrix, `done`, and the held / just-get-it-in lifecycle steps.
- `tests/Feature/Shop/ShopRequestsTest.php` — the Shop board, the just-get-it-in row, the Done menu item on a held row, the taken date, the sheet's and the edit screen's put-aside choice.

```bash
php artisan test --filter=CustomerRequest
```

## Security Considerations

- The board is readable by anyone who can reach the host, including customer names and phone numbers. This was an explicit product decision for the shop-floor tablet; the page carries `noindex`. Restrict at the web server if the app is ever exposed beyond the shop network.
- Guests never render a form, so there is no CSRF surface on the public page. Staff on a shared tablet are bounced to login by the stale-session check when their session expires.
- Line ids on an update are checked against the request being edited, so one request's lines cannot be edited through another.

## Known Issues & Limitations

- **Alpine names unnamed radios after their `x-model` expression.** Radios in different `x-for` rows bound to the same expression (`item.unit`, `item.hold_for_collection`) therefore form one browser radio group, and only one row's choice shows as selected (found in requests cycle 2; the Units/Case cards on the edit screen had it). Give such radios a per-row `:name`. The sheet's single-line radios are unaffected.

- No automatic status change on scan or on completing a delivery — staff confirm the item is physically set aside with the button. Since cycle 3 the scan does stamp the line as arrived (board pill, one-tap Put aside); auto-moving it to `put_aside` would be a one-line addition in `recordArrival()`'s callers if that proves wanted.
- Merging two Receiving sessions moves the scan rows but not `arrived_session_id`, so the detail page's "Open the delivery" link disappears for lines stamped by the source session. The arrival itself is unaffected.
- The Laravel-native delivery scan screen (`/deliveries/{id}/scan`) does not show the flag; only the legacy match screen that shop staff use does. The hook point there would be `DeliveryController::formatDeliveryItem()`.
- No email or push reminder; the board, the dashboard and the delivery screen are the reminders.

## Changelog

- **2026-09-10**: Initial release.
- **2026-10-07**: Lines can be taken by the case or by the unit; the New request sheet shows case size, stock and price for the picked product (requests cycle 1).
- **2026-11-07** (later): arrivals from Receiving — scan endpoints stamp open lines, Arrived pill and one-tap next step on the board, dashboard count, `customer-requests:backfill-arrivals` (requests cycle 3). Also fixed the board order: `boardRows()` passed per-item key closures to `sortBy([...])`, which Laravel treats as two-item comparators, so rows were never sorted by due date; one composite key (`boardSortKey()`) now orders arrived first, then due date, undated last.
- **2026-11-07**: "Put aside" or "Just get it in" per line (`hold_for_collection`), the `done` status with a one-tap Done on the board, and the taken date shown next to the due date (requests cycle 2).

## Staff board v2 (cycle 13)

The staff view is a list of request **rows**, not cards:

- **Views** via `?show=`: `open` (pending or ordered, the default), `aside`
  (put aside), `done` (collected / not available / cancelled in the last 30 days,
  newest first). `?closed=1` is kept as an alias of `show=done`. Guests are always
  given the `board` view — everything still outstanding, put-aside lines included
  — on the unchanged cycle 12 cards.
- **A row** is a date block (Today / Late / the date, or "No date"), the item and
  customer, a three-step lifecycle strip (Ordered → Put aside → Collected), one
  primary next-step button and a `<details>` "more" menu (Edit, Put aside now,
  Undo last step, Not available, Cancel request). Finished lines get `is-done`;
  not-available and cancelled lines strike the strip through with `is-stopped`.
- Every action is still a plain form to the existing endpoints. There is no
  confirm dialog: "Undo last step" and "Reopen" are the safety net, which is what
  the backwards transitions in `ALLOWED_TRANSITIONS` are for.
- **Search** filters the rows already on screen (`data-text` + `x-show`); it does
  not fetch. **New request** lives in the sticky bottom bar and opens a sheet
  (a side sheet, a bottom sheet on phones) holding the cycle 12 form.
- `CustomerRequestService::boardRows($view)` replaces `boardCards()` and returns
  `due` / `open` / `done` / `counts`.

## Shop mode board (cycle 12)

The board at `/customer-requests` renders through the Shop mode shell
(`resources/views/shop/requests.blade.php` plus `shop/partials/request-card` and
`shop/partials/request-form`). The URL, route name, public access and every write
endpoint are unchanged.

- **One card per line**, not per request: `CustomerRequestService::boardCards()`
  flattens `board()` into `['item' => …, 'request' => …]` entries. A line
  collected or cancelled in the last day stays on the board so a mis-tap can be
  undone there.
- **Guests** get the read-only board, a 5-minute meta refresh (`ShopLayout`'s
  `guestRefresh` prop, rendered only when signed out) and no phone numbers.
- **Staff** with `customer-requests.manage` additionally get the status buttons —
  the same PATCH forms as the office partial, same labels — a pencil link to the
  office edit page, and the "New request" card.
- **The New request card takes one line**, either a pre-order (a stocked product
  found via `api.products.search`) or a sourcing request (free text). It is a
  plain HTML POST to `customer-requests.store`, so the redirect-and-flash contract
  is untouched; the only JavaScript is the typeahead in
  `resources/js/shop/requests.js`.
- `show` and `edit` are Shop screens too since cycle 14:
  `resources/views/shop/request-show.blade.php` (header facts, the lines with
  status pills, and the full status history) and
  `resources/views/shop/request-edit.blade.php` (customer, phone, due date and
  notes; rename lines, change quantities and line notes; add a stocked product by
  typing or scanning, or a free-text line to source; remove a line that has not
  moved past pending). Statuses are never changed from the edit screen — the
  board does that. Behaviour is `resources/js/shop/request-edit.js`.
- The product thumbnail and the typeahead are shared:
  `<x-shop.product-thumb>`, `resources/js/shop/product-images.js` and
  `resources/js/shop/product-typeahead.js`, used by both the New request sheet
  and the edit screen. (Find product still has its own copy, which carries the
  hover peek; a later tidy.)
