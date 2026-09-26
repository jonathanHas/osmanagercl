# Shop mode cycle 19 — Harvest: print labels as you log

Status: READY
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

The office harvest page offers to print Zebra labels right after a harvest entry is saved, defaulting the copy count to the amount just logged, and has a per-row print button for reprints. The Shop harvest screen lost this when cycle 17 dropped the label payload (Zebra printing was parked). Restore it in Shop style: after a successful log for a product that has an active Zebra label, a card offers "Print N labels" with a stepper, sends the print, and reports the printer's answer; each Today row with a label gets a print button for a reprint. Products without a label behave exactly as now.

## Context

- **Office behaviour** (`resources/views/fruit-veg/harvest.blade.php`): after `saveRow()` succeeds, `if (row.label) this.openPrintModal(row, amount)`; the modal shows product, label name, a "check the loaded labels" note with the label size, a copies input defaulting to `Math.min(99, Math.max(1, Math.round(amount)))`, Print/Cancel; `sendPrint()` POSTs `/labels/zebra/manage/{id}/print` with `{ copies }` and shows `data.message` (or `Failed: …`), closing 1.5 s after success. `printRow(row)` reopens it with the typed amount or 1.
- **Endpoint**: `ZebraLabelController::print(Request, ZebraLabel)` (`zebra-labels.print`, `permission:labels.print`, which employees hold): `copies` clamped 1–99, optional `fields`; returns `{ success, message, output }`, 200 or 500; messages "Print job sent (N copies)" / "Couldn't confirm the print job — the printer may not have responded." / "Print failed". The printer call can take up to 15 s.
- **Label payload**: `HarvestController::labelPayload(?ZebraLabel)` → `{ id, name, width_mm, height_mm }` or null; `dataFor()` already attaches it to each `productLookup` entry and to `rows` (`'label' => …`) but `rows()` (the Shop JSON) omits it for both `rows` and `available` ("Label payloads are dropped, as the plan says"). `available` items come from `productLookup`, which has the label too.
- **Shop harvest** (`resources/js/shop/fv-harvest.js`, `fv-harvest.blade.php`): `log()` posts, and on success toasts `Logged … · N unit today` and calls `load()`; `today` rows render name, time, user, quantity; a card on the right holds the unit switch and numpad; `shop-actions` has the log button. Design pieces available: `shop-card`, `shop-stepper` (delivery-scan pattern), `shop-pill--warn`, `shop-btn--secondary/--primary`, `shop-iconbtn` with the `printer` icon, toasts.
- **Vouchers/Zebra plan (parked)** describe the same print contract; nothing from it is needed here beyond the endpoint.
- Tests: `ShopFruitVegTest` (12; harvest fixture uses a POS `PRODUCTS` table and `supplier_link`), `DeliveryTranslatedLabelPrintingTest` shows how to bind a fake `ZebraPrintService` (`usingRunner`) so a print test never shells out. `ZebraLabel` lives on the default DB (`zebra_labels`, migrated).
- Suite baseline: 15 failed / 634 passed.

## Constraints

- Do not commit, push or deploy.
- The print goes through the existing `zebra-labels.print` endpoint unchanged; no ZPL handling in Shop code.
- The offer appears only when the logged product has an active label; it never blocks the log (the entry is already saved before the offer).
- Contract rules; design block byte-identical; app rules only if needed (none expected).

## Out of scope

- Price/country field overrides (the parked Zebra cycle).
- Printing from the waste screen (waste has no label flow in the office either).
- Changing which products have labels.

## Steps

### 1. Rows carry the label
Files: `app/Http/Controllers/HarvestController.php`
What: in `rows()`, include `'label' => $row['label']` on each of `rows` and `'label' => $p['label']` on each of `available` (the same `{ id, name, width_mm, height_mm }` or null the office gets). Nothing else changes.
Check: `ShopFruitVegTest::harvest_rows_lists_recent_and_available_jon_products` extended: seed an active `ZebraLabel` with `product_code` `2002` (and `zpl_content` with a `^PW`/`^LL` pair or explicit `label_width_mm`/`label_height_mm`) → that row's `label.id` and `label.name` match; the product without one has `label` null.

### 2. Behaviour: the offer and the print
Files: `resources/js/shop/fv-harvest.js`
What: state `print = null` (`{ label, product, copies, sending, result }`). In `log()` after a successful save, before `load()`: if `this.selected.label` → `this.offerPrint(this.selected, this.amount)`. `offerPrint(p, amount)`: `print = { label: p.label, product: p.name, copies: Math.min(99, Math.max(1, Math.round(amount))), sending: false, result: null }`. `bumpCopies(n)` clamps 1–99. `sizeText` getter → `${label.width_mm ?? '?'} × ${label.height_mm ?? '?'} mm`. `sendPrint()`: `sending = true`; POST `this.$root.dataset.printUrlTemplate.replace('__ID__', print.label.id)` with `{ copies }` (the URL template comes from Blade; no route helper in JS); on `data.success` → toast ok `data.message`, `print = null`; else toast bad `data.message || 'Print failed'` and keep the card open with `result = data.message` so the user can retry; network → toast bad "Could not reach the printer", keep open. `dismissPrint()` → `print = null`. `reprint(r)` (from a Today row) → `offerPrint(r, r.logged)`.
Check: node exercise with stubbed fetch: log 4.2 kg of a product with a label → `print.copies` 4, `sizeText` "112.6 × 75.1 mm"; `bumpCopies(-10)` → 1; `bumpCopies(200)` → 99; `sendPrint()` posts `{ copies }` to `/labels/zebra/manage/7/print` (template replaced) → success toast and `print` null; a 500 `{ success: false, message: 'Print failed' }` → `print` still set, `result` "Print failed"; a product without a label → `print` stays null after log; `reprint({ label, name, logged: 3 })` → copies 3; `grep -c "route(" resources/js/shop/fv-harvest.js` → 0.

### 3. The screen
Files: `resources/views/shop/fv-harvest.blade.php`
What: `<main …>` gains `data-print-url-template="{{ route('zebra-labels.print', ['zebraLabel' => '__ID__']) }}"`. Add a print card in the right-hand column above the unit/numpad card: `<section class="shop-card" x-show="print" x-cloak>` with `shop-between` (`h2.shop-subtitle` "Print labels", ghost × `@click="dismissPrint()"`), `shop-meta` `x-text="print?.product + ' · ' + print?.label.name"`, a `shop-card shop-card--flat` note with the `alert` icon: "Check the printer has <strong x-text="sizeText"></strong> labels loaded.", a `shop-label` "Copies" with a `shop-stepper` (`bumpCopies(-1)`, value `print?.copies`, `bumpCopies(1)`), `shop-meta` `x-show="print?.result"` `x-text="print?.result"` for a failure, and two buttons in a `shop-inline`: `shop-btn shop-btn--secondary` "Skip" (`dismissPrint()`) and `shop-btn shop-btn--primary` with the `printer` icon and `x-text="print?.sending ? 'Sending…' : 'Print ' + print?.copies + (print?.copies === 1 ? ' label' : ' labels')"` (`:disabled="print?.sending"`, `@click="sendPrint()"`). Today rows: after the quantity, `<button class="shop-iconbtn shop-iconbtn--ghost" type="button" x-show="r.label" aria-label="Print labels" @click="reprint(r)"><x-shop.icon name="printer" /></button>`. While `print` is set the log button in `shop-actions` stays as it is (the entry is already saved).
Check: `php artisan test --filter="ShopFruitVegTest|ShopViewContractTest"` green; `employee_can_open_both_screens` also asserts `data-print-url-template`, "Print labels", "Check the printer has" and `aria-label="Print labels"`.

### 4. A print test through the real endpoint
Files: `tests/Feature/Shop/ShopFruitVegTest.php`
What: `harvest_print_uses_the_zebra_endpoint`: bind a fake `ZebraPrintService` as `DeliveryTranslatedLabelPrintingTest` does (capture the ZPL written to the temp file); seed an active label for `2002` with ZPL `^XA^FT30,60^A0N,28,28^FDSalad mix^FS^PQ1^XZ`; employee POSTs `route('zebra-labels.print', $label)` with `{ copies: 3 }` → 200 `success` true, message "Print job sent (3 copies)", captured ZPL contains `^PQ3`; a barista → 403. (This pins the contract the Shop screen relies on; the Shop code itself is covered by the node exercise and the markup assertions.)
Check: `php artisan test --filter=ShopFruitVegTest` green.

### 5. Docs, format, build
Files: `docs/features/fruit-veg-system.md`, `docs/design/shop-mode/README.md`, all touched
What: feature doc: the Shop harvest print offer (after a log, and from a Today row), copies default, the endpoint. README: the harvest print card and the URL-template pattern for a per-record route. `./vendor/bin/pint --dirty`; `npm run build`.
Check: `./vendor/bin/pint --test --dirty` clean; build succeeds.

## Verification

1. `php artisan test --filter="Shop|FruitVeg"` → green; `php artisan test` → 15 failed, the identical set; passed = 634 + new tests.
2. Contract greps; `grep -c "route(" resources/js/shop/fv-harvest.js` → 0; design block `cmp` identical.
3. `git diff app/Http/Controllers/HarvestController.php` → `rows()` only.
4. Manual, on production after deploy with the Zebra printer on, or on dev with the printer reachable (the implementer must not print on the real printer unasked; the owner does this part): log 4 kg of a product that has a label → the print card offers 4 copies with the label size; adjust to 2; Print → the printer produces 2, the toast says so, the card closes; log a product without a label → no card; tap the printer icon on a Today row → the card with the logged amount as copies; Skip closes it; with the printer off → "Couldn't confirm the print job…" stays on the card and Print can be retried.

## Risks

- **Printer wait**: the endpoint can block up to 15 s; the button shows "Sending…" and is disabled meanwhile, as the office does.
- **Copies default from a kg amount**: 4.2 kg → 4 labels, the office's rounding rule; a units amount maps one to one.
- **`rows` payload grows** by a small object per product; negligible.
