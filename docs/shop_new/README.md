# Shop view updates — working notes

General updates to the new Shop view (`/shop`, the touch-first employee
interface) run in this folder. The protocol is [`planimp.md`](./planimp.md).
This file is what a fresh session needs before touching Shop code: where
things are, the rules a change must not break, and what is open.

History: the Shop view was built in 33 numbered cycles under `docs/planImp/`
(archive in `docs/planImp/archive/`, last handover
`docs/planImp/handover-2026-09-30.md`). That folder is the record of how each
screen came to be; look there when a piece of behaviour seems odd before
changing it. Feature documentation: `docs/features/shop-mode.md`.

## Where things stand (2026-10-02)

| | |
|---|---|
| Current task | none. Accepted 2026-10-02 and uncommitted: delivery follow-ups (`archive/2026-10-02-delivery-follow-ups/`), product-search failure message (`archive/2026-10-02-search-failure-message/`), sticky "New request" / Print bars + "No products match" (`archive/2026-10-02-requests-sticky-bar/`). Committed: find-by-name, `9247c79e`. The owner's `ToDo.txt` item is done |
| HEAD | `9247c79e` on `feature/modularization-phase1` (track opened at `9f796b28`) |
| Working tree | three accepted tasks uncommitted (Shop delivery, typeahead, requests and labels views, `shop.css`, their tests, `docs/features/shop-mode.md`, `docs/shop_new/`) |
| Test baseline | 15 failed / 942 passed (see "Test baseline" below) |

## Where the code is

| What | Where |
|---|---|
| Screens | `resources/views/shop/*.blade.php` (+ `partials/`) |
| Components | `resources/views/components/shop/` (`topbar`, `scan-input`, `tile`, `icon`, `product-thumb`, `photo`, `help-button`), layout `<x-shop-layout>` |
| Alpine modules | `resources/js/shop/*.js` (shared parts composed with `mix()` from `mix.js`; `scan-input.js`, `product-images.js`, `product-typeahead.js` are shared) |
| Styles | `resources/css/shop.css` |
| Icons | `public/images/shop-icons.svg` |
| Controllers | `app/Http/Controllers/Shop/` (thin; most screens call existing office JSON endpoints) |
| Routes | `routes/web.php`: public `shop.switch*`, `shop.lock`, `shop.locked`; authenticated `shop.*` group |
| Home tiles, idle lock, PIN settings, PIN route allow-list | `config/shop.php` |
| Tests | `tests/Feature/Shop/` |
| Design reference | `docs/design/shop-mode/` (Claude Design project `9402165e-9955-461d-8ff4-3e934897b600`) |

Screens today: Home, Stock scan, Find product, Deliveries (list, scan,
summary; built on the **legacy** `delivery-legacy.*` endpoints, which is the
flow employees use), Print labels, Customer requests (board, detail, edit; the
public `/customer-requests` route), Vouchers, F&V waste and harvest, PIN
switch / lock / locked, trust device, Help (BookStack procedures). The coffee
KDS is deliberately outside the Shop view.

## Rules every change must respect

1. **View contract.** Views under `resources/views/shop/**` use only
   `x-shop.*` components and `shop-*` classes
   (`tests/Feature/Shop/ShopViewContractTest.php`).
2. **Design block is verbatim.** The first part of `resources/css/shop.css` is
   byte-identical to `docs/design/shop-mode/shop.css`. App rules go only
   between `/* === APP ADDITIONS START === */` and `END`. Check:
   `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css`
   (identical on 2026-10-02). Everything is scoped under `.shop`, tokens are
   `--shop-*`, light theme only, `is-touch` on the root for coarse pointers.
3. **PIN sessions are confined.** A new endpoint called from a Shop screen
   must be covered by `config('shop.pin_session_routes')`, or PIN users get a
   403 on the shop floor. The drift test in
   `tests/Feature/Shop/ConfinePinSessionTest.php` catches a route named in a
   Shop view that is not allow-listed.
4. **New permissions ship as a migration**, never only in the seeder
   (production was never seeded; never seed over production).
5. **Alpine traps.** Never use an Alpine `@` shorthand that is also a Blade
   directive (`@error`, `@class`; write `x-on:error`). `x-show` still
   evaluates the element's other bindings, so nullable data behind it needs
   `?.` or an `x-if` template. Compose shared parts with `mix()`, not object
   spread (spread flattens getters). A handler on an element that an `x-if`
   can remove loses `$root` (and every `$root.dataset` getter) once its own
   action removes that element, so prefer `x-show` with a null-safe
   getter/setter there. `x-show` reveals an element after `$nextTick` has run,
   so focusing something just shown needs a frame
   (`$nextTick(() => requestAnimationFrame(…))`, see `focusField()` in
   `delivery-scan.js`).
6. **Lists of identical rows**: never float a popup with one-tap actions over
   neighbouring rows; expand in flow instead (cycle 13b).
7. **Sticky action bars.** A sticky `.shop-actions` must be laid out as a
   child of the page's tall container (`<main>`, or a form wrapping the whole
   page). A wrapper that exists only for an Alpine scope or a form takes
   `class="shop-contents"` (`display: contents`), or the bar sits at the end
   of the page instead of the bottom of the screen (found on Customer requests
   and Print labels, 2026-10-02).
7a. **Project rules** from `CLAUDE.md`: Eloquent models, thin controllers,
   services for business logic, `<x-product-search>` / `GET /api/products/search`
   for product search (Shop uses `product-typeahead.js` on that endpoint),
   tests for new behaviour, `./vendor/bin/pint`.
8. **No commit, push or deploy** unless the plan says so. The owner commits.
   The Planner never edits application code.
9. Every response ends with `mpg123 /home/jon/Music/notification.mp3`.

## Scanning: facts that are easy to break

- `scan-input.js` owns camera and hand-scanner input for every Shop screen.
  A screen dispatches `shop-scan-saved` when it has finished with a scan
  (saved, cancelled, not found), never while a prompt is open; the camera is
  paused on detection (frozen frame, "Got it") and resumes on that event. A
  failed save keeps the camera paused.
- Timing constants: 1500 ms `lastAt` push in `restartCameraIfWanted()`
  (raise this one if a phone re-prompts the same item), 2000 ms in
  `detected()`.
- Hand-scanner keystrokes are captured at window level (`capture()`): a burst
  goes to the scan field, a lone Enter goes to the focused button. It assumes
  one scan field per page.
- Focusing a field (`focusField()` in `delivery-scan.js`, `focus()` in
  `scan-input.js`) waits on `requestAnimationFrame`, which never fires in a
  hidden or backgrounded tab. In browser automation, bring the tab to the
  front or click into fields by hand. If a device ever fails to focus,
  `setTimeout(…, 0)` after `$nextTick` is the fallback.
- html5-qrcode 2.3.8 version-tied points are listed in
  `docs/features/shop-mode.md` under "Camera scanning".

## Checking a change

- Tests: `php artisan test` for the baseline, `php artisan test tests/Feature/Shop`
  while working. Any page reaching `ProductSearchService` needs the POS
  `PRODUCTS` fixture with the full `SELECT_COLUMNS` (incl. PRICEBUY,
  ISSERVICE, DISPLAY, IMAGE).
- Markup is checked with a rendering test, not `php artisan view:cache`.
- Browser: dev host is `osmanager.local`, plain HTTP, so the **live camera
  cannot run on dev**; camera behaviour is checked with spies and by the
  owner on production (`https://lilthink2/`). A browser check exercises the
  action (click, submit, watch the console), not just the render.
- Phone width: `resize_window` does not change the viewport on this machine;
  use a same-origin iframe sized 390 × 844.
- The idle lock (5 min) fires on a trusted device during long checks.
- The automation tab is hidden (`document.hidden` is true), so `requestAnimationFrame`
  and `x-show` wait on timers throttled to about a second; a timing check that waits on
  rAF can hang for tens of seconds. Time a change with a few microtask turns
  (`await Promise.resolve()` ×5) plus a forced layout (`document.body.offsetHeight`), and
  read component state instead of waiting for `x-show` (order_clean cycle 1, 2026-10-06).
- On `/shop/labels` `total` is a getter (`rows.length`); to fake a queue in a
  browser check, set `rows`.
- Browser automation here runs in a hidden tab and degrades over a long
  session (screenshots time out, clicks and keys stop arriving, `x-show`
  reveals lag by seconds). Prefer reading component state over screenshots,
  open a fresh tab for each pass, and expect to fall back to DOM events late
  in a pass; say in the report which actions were real input.
- Dev accounts: `katelyn` (id 3, employee) has PIN `2580`; `test` is the one
  manager account. Dev data written during a check is put back and listed
  under "Dev state" in the report.
- If a page 500s after a view change with a permission error on compiled
  views: `php artisan view:clear` (the www-data trap, quick-start guide).
- Production is read-only from here:
  `ssh -n -o BatchMode=yes jon@lilThink2 'cd /var/www/html/osmanager && php artisan tinker --execute=…'`.

## Test baseline

The Planner takes it fresh when writing each plan (other tracks add tests).

2026-10-02 (after the sticky-bar task), `php artisan test`: **15 failed, 942 passed** (about 57 s). The 15
are pre-existing and unrelated to the Shop view: `UdeaScrapingServiceTest` ×7,
`CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2,
`TestScraperControllerTest` ×1. A task is clean when the same 15 fail and
nothing else does.

## Open items inherited from `docs/planImp/`

Not started; the owner chooses.

- Owner to choose: on wide screens, when a page does not scroll, a sticky
  action bar sits 8 px above the bottom edge (design block: 32 px page padding
  against a 24 px bar margin). Every screen with a bar; not new.
- If the sticky "New request" bar still feels slow at the counter, a second
  way in (header button, Home shortcut) would be a new task.
- Left from the find-by-name task, owner to choose: the by-name list sits
  above Items on a phone; the supplier list is stocked products only; the
  list does not reopen after leaving the page; supplier code vs product code
  in the two lists.
- Delivery summary could split units and kg; needs a reliable weighed flag
  (the cheese is not `ISSCALE`). The raw-float display itself is fixed.
- **New request page should find destocked products** (greyed, still
  selectable), from the owner's `docs/planImp/needed.txt`.
- **Scanning feels slower than the legacy screen** `/delivery-legacy/match`
  (same file). Cycles 29–33 addressed parts of this; whether it still holds
  on production is unconfirmed.
- Zebra labels in Shop mode: plan parked at
  `docs/planImp/parked/2026-09-25-shop-mode-cycle-11-zebra/plan.md`.
- F&V availability and labels screens (design screens 12 and 15): unplanned.
- Deliveries list does not show which sessions have invoice lines loaded.
- Thumbnail prune keep-set misses invoice lines never scanned.
- Shop devices page: re-trusting leaves a new row each time; log has no
  filtering; revoke does not sign the current user out.
- Delivery summary "Note for the office" from the design has no backend.
- Optional `shop-wide-only` utility so the delivery header Summary link shows
  its word on wide screens.

## Folder layout

- `planimp.md` — the protocol
- `README.md` — this file; the Planner updates "Where things stand" when a task is accepted
- `plan.md` — Planner-owned
- `implemented.md` — Implementer-owned report for the current task
- `findings/` — things found after a task is accepted
- `archive/YYYY-MM-DD-<slug>/` — accepted tasks
- `parked/` — plans put aside before implementation
