# Customer requests — plan / implement track

Customer Requests work runs here, separate from the Shop view updates in
`docs/shop_new/`, orders in `docs/order_clean/`, deposits in `docs/deposit/`
and vouchers in `docs/vouchers/`. Same protocol, same file roles: see
[`planimp.md`](./planimp.md). The Planner keeps this file current; a fresh
session reads it before touching request code.

Every request screen is a **Shop view** (board, detail, edit all render in
`<x-shop-layout>` since Shop mode cycle 14), so the Shop rules in
`docs/shop_new/README.md` apply to every change here as well as the request
rules below. The feature doc is `docs/features/customer-requests.md`.

## Where things stand (2026-10-07)

| | |
|---|---|
| Current task | **Cycle 1** — case size on the New request sheet, order by the case or by the unit (`plan.md`, READY 2026-10-07; from the owner's `todo.txt`). Track opened 2026-10-07. Earlier request work ran as Shop mode cycles 12, 13, 13b, 14, 14b, 14c, 22 (`docs/planImp/archive/`) and the sticky "New request" bar (`docs/shop_new/archive/2026-10-02-requests-sticky-bar/`) |
| HEAD | `7334f05f` on `feature/modularization-phase1` |
| Working tree | clean apart from `docs/shop_new/ToDo.txt` (an unrelated stock-check note) and this folder. The Implementer records the baseline `git status --short` all the same |
| Test baseline | `php artisan test --filter=CustomerRequest` → 28 passed, 211 assertions (2026-10-07). Full suite not rerun; the order track recorded 15 failed / 1023 passed on 2026-10-06, the failures being the known unrelated set (`UdeaScrapingServiceTest` ×7, `CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2, `TestScraperControllerTest` ×1) |

## Where the request code is

| What | Where |
|---|---|
| Models | `app/Models/CustomerRequest.php` (`refreshClosedState()`), `CustomerRequestItem.php` (`ALLOWED_TRANSITIONS`, cross-connection `product()`), `CustomerRequestItemStatusLog.php` (table `customer_request_status_logs`) |
| Service | `app/Services/CustomerRequestService.php`: `create()` :43, `update()` :73, `changeItemStatus()` :114, `cancel()` :160, `board()` :182 (guest cards), `boardRows()` :223 (staff rows), `imageUrlsForRequestLines()` :312, `dashboardCounts()` :350, `awaitingArrivalByBarcode()` :365, `awaitingArrivalPayload()` :389 |
| Controller | `app/Http/Controllers/CustomerRequestController.php` (thin; `create` redirects to the board sheet; `photo` serves the public 112 px thumbnail) |
| Validation | `app/Http/Requests/CustomerRequestRequest.php` (store + update; checks `items.*.id` belong to the request), `UpdateCustomerRequestItemStatusRequest.php` |
| Routes | `routes/web.php`: public `customer-requests.index` :63 and `customer-requests.photo` :68 (outside `auth`); the rest in the `customer-requests` prefix group :1269 behind `permission:customer-requests.manage` |
| Views | `resources/views/shop/requests.blade.php` (board), `request-show.blade.php`, `request-edit.blade.php`, partials `shop/partials/request-row` (staff row), `request-card` (guest card), `request-form` (New request sheet). The `resources/views/customer-requests/` folder named in the feature doc **no longer exists** |
| JS | `resources/js/shop/requests.js` (board search + sheet), `request-edit.js`; shared `product-typeahead.js`, `product-images.js`, `mix.js` |
| Consumers elsewhere | `DashboardController` (banner via `dashboardCounts()`), `Shop/ShopHomeController` (Home tile count), `DeliveryLegacyController` (`match()` badge + scan prompt via `awaitingArrivalByBarcode()` / `awaitingArrivalPayload()`), `resources/views/delivery-legacy/partials/customer-request-badge.blade.php` |
| Permission | `customer-requests.manage` (employee, manager, admin), created by migration `2026_09_26_120000_add_customer_requests_and_voucher_permissions.php` |
| Tests | `tests/Feature/CustomerRequestTest.php`, `CustomerRequestDashboardTest.php`, `CustomerRequestDeliveryFlagTest.php`, `tests/Unit/CustomerRequestItemTransitionsTest.php`; Shop contract tests in `tests/Feature/Shop/` |
| Design | `docs/design/shop-mode/` (the requests screens), `docs/features/shop-mode.md` |

## Rules every change must respect

1. **All status writes go through `CustomerRequestService::changeItemStatus()`.**
   It enforces `ALLOWED_TRANSITIONS`, writes the status log and recomputes
   `closed_at`. Never set `status` on an item directly, from a controller or
   a tinker fix.
2. **The board stays public.** `customer-requests.index` and
   `customer-requests.photo` are registered outside the `auth` group by owner
   decision (the shop-floor tablet shows the board all day, no login). Guests
   see no phone numbers and render no form. Every other route stays inside
   `auth` + `permission:customer-requests.manage` so a guest is redirected to
   login, not shown a 403.
3. **The photo route stays narrow.** 112 px JPEG only, only for codes on a
   request line that is open or closed within 30 days, 404 for anything else,
   throttled. It must not become a general product-image endpoint.
4. **`product_code` is the POS barcode and is not a foreign key.** Display
   `product_name` (the snapshot taken at pick time); never eager-load
   `CustomerRequestItem::product()` in a list. The POS database is read-only.
5. **Writes record who did them.** `created_by` / `updated_by` / `closed_by`
   and the log's `user_id` come from the signed-in user; that is why writes
   need a login.
6. **Plain forms to the existing endpoints.** Board actions are HTML POST /
   PATCH forms with redirect-and-flash (JSON only when `Accept:
   application/json`, used by the delivery scanner). No confirm dialogs: the
   backwards transitions ("Undo last step", "Reopen") are the safety net.
7. **Edit never changes a status**, and a line that has moved past `pending`
   cannot be removed on the edit screen; the board does status changes.
8. **Status strings are a contract.** `pending`, `ordered`, `put_aside`,
   `collected`, `not_available`, `cancelled`; the delivery lookup, dashboard
   counts, `boardRows()` views and `refreshClosedState()` all key on them.
9. Shop screens: all of `docs/shop_new/README.md` (view contract, verbatim
   design block, PIN route allow-list in `config/shop.php`, Alpine traps,
   sticky bars, no floating popups over identical rows).
10. **New permissions ship as a migration**, never only in the seeder;
    production was never seeded.
11. Project rules from `CLAUDE.md`: Eloquent models, thin controllers,
    services, `product-typeahead.js` on `GET /api/products/search` for product
    search, tests, `./vendor/bin/pint`.
12. **No commit, push or deploy** unless the plan says so. The owner commits.
    The Planner never edits application code.
13. Every response ends with `mpg123 /home/jon/Music/notification.mp3`.

## Facts that are easy to get wrong

- The feature doc's Architecture section still describes the pre-Shop views
  (`customer-requests/index`, `_form`, `show`) and a `create` page; today
  `create` redirects to the board, where the New request **sheet** takes one
  line, and `show` / `edit` are Shop screens. Read the "Staff board v2" and
  "Shop mode board" sections at the end of that doc first.
- Guests and staff get different boards: `board()` feeds the guest cards
  (one card per line, everything outstanding including put-aside), while
  `boardRows($view)` feeds staff rows with `?show=open|aside|done`
  (`?closed=1` is an alias of `done`). A line collected or cancelled stays
  visible for a day (guest) or 30 days (`done` view) so a mis-tap can be undone.
- Guest refresh is a 5-minute `<meta http-equiv="refresh">` from
  `<x-shop-layout>`'s `guestRefresh` prop, rendered only when signed out;
  signed-in users get the stale-session check instead so nobody is reloaded
  mid-edit.
- The status log table is `customer_request_status_logs` (short name so the
  foreign-key name fits MySQL's 64 characters), not `..._item_status_logs`.
- Board search filters rows already on the page (`data-text` + `x-show`); it
  does not fetch.
- The put-aside flag exists only on the legacy delivery match / scan screen
  that the Shop deliveries flow is built on. The Laravel-native
  `/deliveries/{id}/scan` shows nothing (hook point `DeliveryController::formatDeliveryItem()`).
- Thumbnails share the `fv-thumbs/` folder on the `local` disk with the
  fruit-and-veg ones; keys hash code + blob so they cannot collide.
- Dev data: the board, dashboard banner and delivery flag only show rows whose
  lines are open, so a quiet dev database renders an empty board; seed a
  request through `CustomerRequestService::create()` in tinker before a
  browser check.

## Open items (owner chooses)

Carried from `docs/shop_new/README.md` and the feature doc; none started.

- **New request sheet should find destocked products** (greyed, still
  selectable), from the owner's `docs/planImp/needed.txt`.
- If the sticky "New request" bar still feels slow at the counter, a second
  way in (header button, Home shortcut).
- No automatic status change on scan or on completing a delivery; staff
  confirm with the button. Marking on scan would go in
  `DeliveryLegacyController::incrementScanQuantity()`.
- The native delivery scan screen has no put-aside flag (see above).
- No email or push reminder; the board, dashboard and delivery screen are
  the reminders.
- Tidy: Find product still carries its own copy of the typeahead with the
  hover peek; the feature doc's Architecture section is stale.

## Folder layout

- `planimp.md` — the protocol
- `README.md` — this file; the Planner updates "Where things stand" when a task is accepted
- `plan.md` — Planner-owned
- `implemented.md` — Implementer-owned report for the current task
- `findings/` — things found after a task is accepted
- `archive/YYYY-MM-DD-<slug>/` — accepted tasks
- `parked/` — plans put aside before implementation
