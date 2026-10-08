# Cycle 3 — Correct quantities from the delivery summary

Status: READY
Revision: 1
Planner: Fable 5.1
Date: 2026-10-08

## Goal

On the Shop scan screen a row can be tapped to open the correction card
(cycle 2 made it instant). The summary screen (`/shop/deliveries/summary`)
lists exactly the rows that need attention, short, over, unexpected and not
scanned, but they are plain rows: nothing can be fixed from the place where
mistakes are found. After this cycle a discrepancy row on the summary opens
the same correction card, with the same local stepper, typed value and
debounced save, and the totals and the list update when the save lands. The
card is one shared module and one shared Blade partial, so the two screens
cannot drift. A completed delivery stays read-only on both screens. From the
owner's request, 2026-10-08.

## Context

- **Summary screen**: `app/Http/Controllers/Shop/DeliveryController::summary()`
  passes `$session` (`id`, `supplierId`, `supplier`, `date`, `completed`);
  view `resources/views/shop/delivery-summary.blade.php` (narrow page,
  `shop-page--narrow`; the discrepancy list is `<div class="shop-row">`
  elements in an `x-for` under the "Discrepancies" heading; the complete
  confirmation card `x-ref="confirm"`; a sticky `.shop-actions` bar); JS
  `resources/js/shop/delivery-summary.js` (`mix(productImages(), {...})`,
  `load()` from `delivery-legacy.items`, derived getters `totals`,
  `discrepancies`, `canComplete`, no toast, no `busy`). The root carries
  `data-items-url`, `data-del-id`, `data-supplier-id` only.
- **Scan screen's card** (committed in cycle 2, `4d023aa8`):
  `resources/views/shop/delivery-scan.blade.php` :221–259, the section
  `x-ref="correct"` with `x-show="editing && ! pending"`; JS in
  `resources/js/shop/delivery-scan.js`: state `editing`, `editValue`,
  `editTyped`, `flushTimer`, `flushPending`, `busy`, `toast`, `toastTimer`;
  `init()` :86–108 (the `editing` watch that flushes the row just left, the
  `pagehide` keepalive PATCH); getters `editingRow` :222, `updateUrl`,
  `delId`, `supplierId`, `csrf`; `load()` :273 closes the card when its row
  was deleted; methods `edit()` :772, `adjust()` :794, `flushNow()` :812,
  `typeCorrection()` :848, `setCorrection()` :861, `quantityBody()` :879,
  `saveQuantity()` :894, `requestInit()` :918, `post()` :931, `stockText()`,
  `focusField()`, `showToast()` (`TOAST_MS` 3000). The toast markup is the
  `.shop-toasts` block at the end of the scan view.
- **Composition**: `resources/js/shop/mix.js` copies property descriptors,
  so a shared part may carry getters; later parts override earlier ones.
  Parts have no `init` of their own: the page's `init()` calls into them.
  Modules are registered in `resources/js/shop.js` (`Alpine.data(...)`).
- **Rows as buttons**: the scan list uses
  `<button class="shop-row shop-item shop-item--pic" :aria-pressed=…>`
  (`.shop-item` gives a button the grid, full width and left-aligned text;
  `.shop-item--pic` the picture column, an app addition). The summary's rows
  have the same thumb / main / aside shape.
- **Rules**: Shop rule 6 (no popups over neighbouring rows: the card is a
  section in flow, above the list, scrolled into view, as on the scan
  screen); view contract (`x-shop.*`, `shop-*`, partials live under
  `resources/views/shop/partials/`); PIN allow-list already covers
  `delivery-legacy.update-quantity`; permission is `deliveries.process` on
  both screens. A completed session: the scan screen hides the scan field
  and says corrections are made on the office page; the summary must not
  offer the card either (`completeDelivery()` has already added stock).
- **Tests pinning the scan JS by source** (`tests/Feature/Shop/ShopDeliveryTest.php`,
  helper `scanJs()` :905): `test_the_correction_card_has_one_primary_button_while_typing`
  (:936, the `setCorrection()` body), `test_the_shop_page_can_skip_financials_on_a_correction`
  (:1269, `financials: false` once), `test_the_correction_card_steps_locally_and_saves_once`
  (:1275, `flushNow`, the 400 ms timer, `keepalive`, `adjust()` body),
  `test_a_scan_flushes_a_pending_correction_first` (:1298, stays on the scan
  JS: `lookup()` is page code). The camera test (:880) and the prompt-order
  test (:218) read the scan JS for strings that do not move. Summary tests:
  `test_summary_screen_renders` :651, `test_the_summary_page_shows_pictures`
  :388, `test_completed_summary_shows_the_completed_notice` :858,
  `test_summary_is_forbidden_for_a_barista` :666. 59 pass today.
- **Fixture**: `d-1` has leeks short (6 of 8), oat drink ok, the unknown
  `4260009912200` unexpected ×3; the summary lists two discrepancies.

## Constraints

- One implementation of the card: a shared JS part and a shared Blade
  partial used by both screens. No copy of the stepper logic in
  `delivery-summary.js`.
- The scan screen's behaviour is unchanged: every existing test passes with
  at most the `scanJs()` → `correctionJs()` retargeting named in step 5.
- No new endpoint, no route or config change.
- A completed session gets no correction card and no row buttons on the
  summary.
- Do not commit, push or deploy. The owner commits.

## Out of scope

- Correcting from the office match page or the `/deliveries` flow.
- Adding items from the summary (scan or find by name stay on the scan
  screen). A "not scanned" row can be corrected to a quantity, which is a
  scan by another route and is wanted.
- Changing what the summary lists (only discrepancies), its totals, or the
  completion flow.
- The lost-tap edge cases noted in `README.md` open items.

## Steps

### 1. The shared card logic
Files: `resources/js/shop/delivery-correction.js` (new; check it does not
exist first), `resources/js/shop/delivery-scan.js`
What: create the part and move into it, verbatim apart from the header
comment, everything in the scan JS that the card needs: state `editing`,
`editValue`, `editTyped`, `flushTimer`, `flushPending`, `busy`, `toast`,
`toastTimer`; `TOAST_MS`; getters `editingRow`, `updateUrl`, `delId`,
`supplierId`, `csrf`; methods `edit()`, `adjust()`, `flushNow()`,
`typeCorrection()`, `setCorrection()`, `quantityBody()`, `saveQuantity()`,
`requestInit()`, `post()`, `stockText()`, `focusField()`, `showToast()`;
plus two new ones:
- `initCorrection()`: the `editing` watch and the `pagehide` listener,
  moved out of the scan page's `init()` unchanged.
- `closeCardIfRowGone()`: the two lines from the scan page's `load()`
  (`if (this.editing !== null && this.editingRow === null) this.editing = null;`).
The part expects the page to provide `rows` and `load()`, and a
`$refs.correct` on the card; say so in the header comment, with why the
part exists (cycle 3: the summary gets the same card, one implementation).
Export `default () => ({...})` like the other parts. Then in
`delivery-scan.js`: import it, add it to `mix(productImages(),
productTypeahead(), deliveryCorrection(), {...})` **before** the page
object; delete the moved members from the page object; `init()` becomes
`this.initCorrection(); this.load();`; `load()` calls
`this.closeCardIfRowGone()`. `lookup()` keeps its `await this.flushNow()`.
Nothing else in the scan page changes.
Check: `npm run build` succeeds;
`grep -c "async flushNow(" resources/js/shop/delivery-scan.js` → 0 and the
same in `delivery-correction.js` → 1; `php artisan test
tests/Feature/Shop/ShopDeliveryTest.php` passes except the four source tests
retargeted in step 5 (run it, note which fail, fix in step 5).

### 2. The shared card markup
Files: `resources/views/shop/partials/delivery-correction.blade.php` (new;
check first), `resources/views/shop/delivery-scan.blade.php`
What: move the `x-ref="correct"` section (:221–259 including its leading
comment) into the partial. The section's `x-show` becomes
`x-show="{{ $show ?? 'editing' }}"`. In the scan view replace it with
`@include('shop.partials.delivery-correction', ['show' => 'editing && ! pending'])`.
Keep the input id `delivery-edit-qty` (one card per page).
Check: `php artisan test tests/Feature/Shop/ShopViewContractTest.php
tests/Feature/Shop/ShopDeliveryTest.php` — the scan-page rendering tests
still see the same markup (`Correct quantity`, `adjust(editingRow, 1)`,
`stockText(editValue)`, the Done button's `:class`).

### 3. The summary opens the card
Files: `resources/views/shop/delivery-summary.blade.php`, `resources/js/shop/delivery-summary.js`
What:
- JS: import `deliveryCorrection`, compose
  `mix(productImages(), deliveryCorrection(), {...})`; `init()` becomes
  `this.initCorrection(); this.load();`; `load()` calls
  `this.closeCardIfRowGone()` after assigning `rows`. Nothing else: the
  totals and the list are getters over `rows`, so a save's reload updates
  them. Header comment: a discrepancy row opens the shared correction card
  (cycle 3), on an open session only.
- View: add `data-update-url="{{ route('delivery-legacy.update-quantity') }}"`
  to `<main>`. In the Discrepancies section, directly after the `<h2>` and
  before the list, `@include('shop.partials.delivery-correction')` wrapped
  in `@unless ($session['completed']) … @endunless`. The row: when
  `! $session['completed']`, render
  `<button class="shop-row shop-item shop-item--pic" type="button"
  :aria-pressed="editing === row.barcode" @click="edit(row)">` with the
  same children as today (thumb, main, aside), using `<span>`s inside the
  button as the scan rows do (a `div` inside a `button` is invalid HTML);
  when completed, keep today's `<div class="shop-row">`. Use one `@if` /
  `@else` around the two row variants inside the `x-for` template rather
  than a dynamic tag.
- Add the toast block (copy of the scan view's `.shop-toasts` div, with its
  comment) at the end of `<main>`, before `</main>`, so "Could not save"
  can show.
- A short comment above the include: the card sits above the list and is
  scrolled into view by `edit()`; a corrected row that now matches leaves
  the list while the card stays open on it, which is the confirmation.
Check: `php artisan test tests/Feature/Shop/ShopViewContractTest.php
tests/Feature/Shop/ConfinePinSessionTest.php` pass; `npm run build`.

### 4. Tests for the summary
Files: `tests/Feature/Shop/ShopDeliveryTest.php`
What: under `// --- deliveries cycle 3: corrections from the summary ---`:
1. `test_summary_rows_open_the_correction_card`: GET the summary as
   `employee()` → sees `data-update-url="…update-quantity…"`,
   `@click="edit(row)"`, `:aria-pressed="editing === row.barcode"`,
   `x-ref="correct"`, `Correct quantity`, `x-text="stockText(editValue)"`,
   `shop-toasts`; and `x-ref="correct"` comes before the first
   `x-for="row in discrepancies"` (card above the list).
2. `test_completed_summary_offers_no_correction`: set `d-1` status 1 → the
   page has no `edit(row)`, no `x-ref="correct"`, still shows
   `This delivery is completed`.
3. `test_both_delivery_screens_share_the_correction_card`: both JS files
   contain `from './delivery-correction.js'`; the scan JS contains no
   `async flushNow(`; both views contain
   `@include('shop.partials.delivery-correction'`; and the partial file
   contains `x-ref="correct"` exactly once.
4. `test_a_summary_correction_changes_the_totals`: as `employee()`, PATCH
   `update-quantity` for `5000000000024` to 8 with `financials: false`, then
   GET `items` → the leeks row `status 'ok'` and `progress.issues` 1 (only
   the unexpected row left). (Server-side proof that the summary's reload
   reflects a correction; the summary derives its totals from this JSON.)
Check: `ShopDeliveryTest` → 63 passed.

### 5. Retarget the moved source assertions
Files: `tests/Feature/Shop/ShopDeliveryTest.php`
What: add `private function correctionJs(): string` returning
`file_get_contents(resource_path('js/shop/delivery-correction.js'))`. Point
at it the assertions whose strings moved: the `setCorrection()` body in
`test_the_correction_card_has_one_primary_button_while_typing`; the
`financials: false` count in `test_the_shop_page_can_skip_financials_on_a_correction`;
the `flushNow`, 400 ms, `keepalive` and `adjust()` body assertions in
`test_the_correction_card_steps_locally_and_saves_once`. Leave
`test_a_scan_flushes_a_pending_correction_first`, the camera test and the
prompt-order test on `scanJs()`. Add to the steps-locally test one line:
the scan JS does not contain `setTimeout(() => this.flushNow(), 400)` (so a
copy cannot creep back).
Check: `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` → 63 passed.

### 6. Docs
Files: `docs/features/shop-mode.md`, `docs/shop_new/README.md`
What: in "Deliveries: correcting a quantity" add a paragraph: the same card
opens from a discrepancy row on the summary, where mistakes are found; a
corrected row that now matches leaves the list while the card stays open;
not on a completed delivery; the card is `delivery-correction.js` +
`shop/partials/delivery-correction.blade.php`, shared by both screens. In
`docs/shop_new/README.md` "Where the code is", add `delivery-correction.js`
to the shared Alpine modules list (one phrase).
Check: `grep -c "delivery-correction" docs/features/shop-mode.md docs/shop_new/README.md` → 1 each or more.

### 7. Format, build, browser check
Files: none new.
What: `./vendor/bin/pint --test` on changed PHP files; `npm run build`.
Browser on dev as `test` (if the extension is connected and the profile is
signed in; otherwise write the checklist and set BLOCKED on that alone):
open the summary of an open dev session with a discrepancy
(`/shop/deliveries/summary?delID=9e5c52db-9b1b-4641-95e3-4e5b33cd8a24&supplierID=13`
had Coolfin Raw Honey at 27 in cycle 2; note the figure on screen first).
1. Tap the row → the card opens above the list showing its number;
   `aria-pressed` on the row.
2. Tap + three times → the number climbs at once; one PATCH and one GET
   `items` about 400 ms later; the totals and the row's pill update.
3. Tap the number, type the original figure, Set → saved; the list and
   totals are back to what they were.
4. Tap Done → the card closes.
5. Open the summary of a completed session → rows are not buttons, no card.
Put the figure back if it differs (tinker read to confirm) and list it under
"Dev state".
Check: observations recorded; console clean.

## Verification

1. `./vendor/bin/pint --test $(git diff --name-only -- '*.php')` → PASS.
2. `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` → 63 passed.
3. `php artisan test tests/Feature/Shop` → all pass.
4. `php artisan test` → 15 failed (the baseline set), 1046 passed, nothing else.
5. `npm run build` succeeds.
6. The browser check in step 7.

## Risks

- **Moving code breaks the scan screen.** The move is verbatim and the
  existing 59 tests plus the retargeted four are the guard; the camera test
  and the scan-flush test still read the scan JS. Run the delivery tests
  after step 1 and again after step 5.
- **`mix()` order**: the shared part must come before the page object so a
  page can still override (nothing does today); `busy`, `toast` and the
  getters move out of the scan page, so a leftover duplicate there would
  silently win. Delete, do not comment out.
- **`$refs.correct` on the summary** is inside the Discrepancies section;
  `edit()` scrolls it into view. If the include is placed outside `<main
  x-data>` the ref is lost: it must be inside the Alpine root.
- **A row corrected to match** disappears from the list with the card still
  open: intended, documented in step 6.
- **Completed sessions**: the Blade guard uses `$session['completed']`, the
  same server fact the scan view uses, so the card can never render for one.

## Review

(Planner fills this in after reading implemented.md and the diff.)
