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
- **`<x-board-layout>`** (`app/View/Components/BoardLayout.php`, `resources/views/layouts/board.blade.php`): a full-width layout with no sidebar that renders for guests. `<x-admin-layout>` calls `auth()->user()->can()` unguarded in its sidebar and cannot render logged out, which is why the board has its own layout. Guests get a 5-minute `<meta http-equiv="refresh">`; signed-in users get the same stale-session check as the admin layout instead, so nobody is reloaded mid-edit.
- **Views** (`resources/views/customer-requests/`): `index` (the board), `_form` (shared by `create` / `edit`, Alpine.js with dynamic lines and a product typeahead), `show` (detail + status history), and partials for the request card, status pill and status buttons.
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
    quantity            DECIMAL(10,2) NOT NULL DEFAULT 1,
    notes               VARCHAR(500) NULL,
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
| `not_available` | Could not be sourced | no |
| `cancelled` | Customer no longer wants it | no |

Allowed moves (`CustomerRequestItem::ALLOWED_TRANSITIONS`): `pending → ordered | put_aside | not_available | cancelled`; `ordered → put_aside | not_available | cancelled | pending`; `put_aside → collected | ordered | cancelled`; `collected → put_aside`; `not_available → pending`; `cancelled → pending`. The backwards moves exist to undo a mis-tap on the tablet. Anything else throws a `DomainException`, which the controller turns into a flash error or a 422 JSON response.

A request's `closed_at` is recomputed by `CustomerRequest::refreshClosedState()` after every line change: it closes when no line is `pending` / `ordered` / `put_aside` and reopens when a line is added or moved back. **All status writes must go through `CustomerRequestService::changeItemStatus()`** so this and the log stay correct.

### Data Flow
1. Staff member signs in and opens **New request** from the board or the sidebar.
2. Types the customer's name and phone, a wanted-by date, then scans or searches products (typeahead against POS via `Product::search()`), or adds a free-text "source a new product" line.
3. `CustomerRequestService::create()` stores the header with `created_by`, snapshots the POS product name into each linked line, and writes a `null → pending` log per line.
4. The board (`/customer-requests`) shows it under **Open requests**, or **Due today / overdue** once `wanted_on` arrives. The dashboard banner counts it from that day.
5. When the delivery is checked in on `/delivery-legacy/match`, any matched barcode on a `pending` / `ordered` line shows a pink **Put aside for &lt;customer&gt; × qty** badge and the summary card lists them. Scanning the barcode shows the same prompt in the scanner panel with a **Mark put aside** button (calls the JSON status endpoint).
6. Staff tap **Put aside**, then **Collected** when the customer comes in. The request closes itself once every line is terminal and drops off the board (still visible under "Show closed (last 30 days)" for staff, and on its detail page with the full status history).

## Configuration

No environment variables. One permission, seeded by `RolesAndPermissionsSeeder`:

| Permission | Granted to |
|---|---|
| `customer-requests.manage` | employee, manager (inherited), admin (implicit) |

**Deploy step**: run `php artisan migrate && php artisan db:seed --class=RolesAndPermissionsSeeder`. The seeder is idempotent. Without it every staff write returns 403.

## Usage

### User Perspective

**Shop-floor tablet (no login)** — open `/customer-requests`. The page refreshes itself every 5 minutes. Due / overdue requests are at the top with a red or amber badge. Nothing can be changed without signing in; the header has a **Staff sign in** link.

**Taking a request** — sign in, **New request**, fill in the customer and date, scan or search for products (Enter adds the highlighted match, so a barcode scanner adds a product in one go), or **+ Source a new product** for something we don't stock. Save.

**Progressing a request** — on the board each line shows buttons for its allowed next statuses (Ordered, Put aside, Collected, Not available, Cancel). **Cancel request** on the card cancels every open line. **Edit** changes details and lines but never a status; lines that have moved past pending cannot be removed there.

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
| GET | `/customer-requests/api/products/search?q=` | `customer-requests.api.products.search` | permission |

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
- `tests/Feature/CustomerRequestDeliveryFlagTest.php` — the match page badge and summary card, and the scan-increment JSON carrying `customerRequests` (builds every POS table the legacy raw queries touch in sqlite).
- `tests/Unit/CustomerRequestItemTransitionsTest.php` — the transition matrix.

```bash
php artisan test --filter=CustomerRequest
```

## Security Considerations

- The board is readable by anyone who can reach the host, including customer names and phone numbers. This was an explicit product decision for the shop-floor tablet; the page carries `noindex`. Restrict at the web server if the app is ever exposed beyond the shop network.
- Guests never render a form, so there is no CSRF surface on the public page. Staff on a shared tablet are bounced to login by the stale-session check when their session expires.
- Line ids on an update are checked against the request being edited, so one request's lines cannot be edited through another.

## Known Issues & Limitations

- No automatic status change on scan or on completing a delivery — staff confirm the item is physically set aside with the button. Marking on scan could be added to `incrementScanQuantity()` if it proves too fiddly in practice.
- The Laravel-native delivery scan screen (`/deliveries/{id}/scan`) does not show the flag; only the legacy match screen that shop staff use does. The hook point there would be `DeliveryController::formatDeliveryItem()`.
- No email or push reminder; the board, the dashboard and the delivery screen are the reminders.

## Changelog

- **2026-09-10**: Initial release.
