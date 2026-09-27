# Plan: Shop mode cycle 25 — Delivery tidy-ups

**Status:** ACCEPTED
**Planner:** Fable 5.1
**Date:** 2026-09-27

## Goal

Close the small items left open by cycles 23 and 24 on the delivery scan page, and stop the weekly thumbnail prune from throwing away pictures the Shop is using. Five items, all small; no new screens, no layout change.

1. The scan prompt's "In stock" fact prints a raw float (`1.5200000000000011`); format it the way the rows and the correction card do.
2. The correction card stays open with blank content when its row disappears after a reload (an unexpected row corrected down to 0 is deleted server-side). Close it.
3. `delivery-scan.js`: two getters landed between the `sorted` doc comment and `get sorted()`; put the comment back on its getter.
4. `delivery-scan.blade.php`: the correction card's `shop-inline` wrapper is not re-indented.
5. The prune keep-set (`ProductThumbnailService::currentPhotoProducts()`) is F&V plus Jon's produce, so every Sunday it deletes the thumbnails for delivery rows and request lines, which are then re-encoded on the next view. Add products on recent deliveries and on current request lines, and share the 30-day "recent" constant the customer-requests code already uses in three places.

## Context (verified 2026-09-27)

- `resources/js/shop/delivery-scan.js`: `stockText(row)` at line 379 reads `row?.stock`; callers in the view: rows (`stockText(row)`), correction card (`stockText(editingRow)`). The prompt's fact is `x-text="pending?.scannedSoFar"` / `x-text="pending?.product.currentStock ?? '—'"` (`delivery-scan.blade.php` ~line 45–52); `currentStock` comes from `scan-increment` (`STOCKCURRENT.UNITS as currentStock`, a decimal). `load()` at line 155 sets `session`, `rows`, `progress`, `error`. `get editingRow()` at line 127 (`rows.find(...) ?? null`). The `sorted` doc comment ("New first" puts the items just scanned at the top…) is at lines 112–116, immediately followed by the `pendingProduct` and `editingRow` getters (117–129), then `get sorted()` at 131.
- `resources/views/shop/delivery-scan.blade.php` lines 102–110: `<div class="shop-inline">` wraps the thumb and a `shop-stack--tight` whose lines are still at the outer indent.
- `DeliveryLegacyController::updateQuantity()` (~line 1270): deletes the `deliveriesScanItems` rows for the barcode and re-inserts only when `quantity > 0`; `items()` lists scanned-not-on-invoice rows from `deliveriesScanItems`, so a 0 correction removes an unexpected row from the JSON.
- `ProductThumbnailService::currentPhotoProducts()` (static, line 176): `Product::whereNotNull('IMAGE')` in F&V categories (`TillVisibilityService::CATEGORY_MAPPINGS['fruit_veg']`) or with a `SupplierLink` for `config('suppliers.jon')`. Called by `PruneFruitVegThumbnails` (weekly, `fruit-veg:prune-thumbnails`, Sunday 05:30 — keep the signature; `ScheduleTest` pins it) and `FruitVegController::pruneThumbnails()` (manage-page button). `prune()` needs each product's `IMAGE` to compute the expected filenames.
- Request lines: `CustomerRequestItem` (`product_code` = POS CODE, `request()` belongs-to, `scopeForBarcodes`); `CustomerRequest::scopeOpen()` = `closed_at` null. `CustomerRequestController::PHOTO_WINDOW_DAYS = 30` (private, line 28) used by `codeIsOnACurrentRequest()` (line 126: open, or `closed_at >= now-30d`). `CustomerRequestService` hard-codes `now()->subDays(30)` at lines 188 and 231 (the Done view / counts).
- Delivery sessions: POS `deliveriesScan` (`ID`, `supID`, `dateUpload` datetime string e.g. `2026-09-26 20:05:28`, `status`), `deliveriesScanItems` (`delID`, `barcode`, `quantity`). Dev has 3 sessions in the last 30 days.
- Tests: `tests/Feature/FruitVegProductImageTest.php` runs the manage-page prune end to end (line ~390) with a POS fixture (`PRODUCTS` with `IMAGE`, `PRODUCTS_CAT`, `CATEGORIES`, `units`, `class`, `vegDetails`, `supplier_link` — **no `deliveriesScan`/`deliveriesScanItems`**). `tests/Unit/ProductThumbnailServiceTest.php` tests `prune()` with hand-built products. `tests/Concerns/CreatesLegacyDeliveryPosTables.php` has the shape of the two delivery tables. `tests/Feature/Shop/ShopRequestsTest.php` covers the photo gate.
- Baseline: 15 failed / 670 passed (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2, TestScraper ×1). Cycle 24 is accepted but uncommitted; do not revert anything in the tree.

## Constraints

- No commits, no deploys. Do not edit `docs/design/shop-mode/**`; no change to `resources/css/shop.css` is expected.
- Shop view contract as before.
- `fruit-veg:prune-thumbnails` keeps its signature and schedule.
- Item 2 must not add a server round trip; it is a client-side check after `load()`.

## Out of scope

- Making `isTouch` in `scan-input.js` reactive (harmless outside DevTools).
- Marking sessions with invoice lines on the Deliveries list (a feature, next cycle if wanted).
- Sort label wording.

## Steps

### 1. Stock formatting on the prompt
`delivery-scan.js`: change `stockText(row)` to `stockText(value)` taking the number (or null) directly; same rounding rules; doc comment updated. Callers: rows `stockText(row.stock)`, correction card `stockText(editingRow?.stock)`, and the prompt fact becomes `x-text="pending?.product.currentStock == null ? '—' : stockText(pending.product.currentStock)"`.
**Check:** scan a weighed product on dev (the report named `5412533420791`, stock 1.52): the prompt shows `1.52`; a whole number shows without `.0`; a product with no stock record shows `—`.

### 2. Close the correction card when its row is gone
`load()`: after `this.rows = data.rows`, add `if (this.editing !== null && this.editingRow === null) { this.editing = null; }` with a one-line comment (a 0 correction deletes an unexpected row). 
**Check:** in the browser, on an unexpected row (not on the invoice) with scanned 1, tap the row and press `−`: the row disappears and the card closes. Put the scan back afterwards (scan the code and Add 1) so dev data is unchanged; say so in the report.

### 3. Doc comment placement
Move the `pendingProduct` and `editingRow` getters (with their own comments) above the `sorted` doc comment, so that comment sits directly on `get sorted()`.
**Check:** `sed -n` the region in the report; `npm run build` clean.

### 4. Indentation
Re-indent the `shop-stack--tight` block inside the `shop-inline` wrapper by one level. No markup change.
**Check:** `git diff` shows whitespace-only changes for that block; `ShopDeliveryTest` green.

### 5. Prune keep-set and the shared window
- `CustomerRequestService`: add `public const RECENT_DAYS = 30;` (doc: the window the Done view, its counts and the public photo route all use). Replace the two `subDays(30)` with it. `CustomerRequestController`: delete `PHOTO_WINDOW_DAYS`, use `CustomerRequestService::RECENT_DAYS`.
- `ProductThumbnailService::currentPhotoProducts()`: extend the `where` closure with two more `orWhereIn('CODE', …)` sets, each computed first and skipped when empty:
  - request-line codes: `CustomerRequestItem::query()->whereNotNull('product_code')->whereHas('request', fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', now()->subDays(CustomerRequestService::RECENT_DAYS)))->pluck('product_code')->unique()->all()`;
  - recent-delivery barcodes: `DB::connection('pos')->table('deliveriesScanItems')->join('deliveriesScan', 'deliveriesScan.ID', '=', 'deliveriesScanItems.delID')->where('deliveriesScan.dateUpload', '>=', now()->subDays(CustomerRequestService::RECENT_DAYS)->format('Y-m-d H:i:s'))->distinct()->pluck('deliveriesScanItems.barcode')->all()`. (Use the same constant; if you prefer a thumbnail-service constant, add `public const KEEP_DAYS = 30` there and reference it from both — either is fine, but one constant, not two literals.)
  - Update the method's doc comment and the command's description/class comment ("product thumbnails the Shop and the office use", not only F&V). Signature unchanged.
- Tests:
  - `FruitVegProductImageTest` fixture: add `deliveriesScan` and `deliveriesScanItems` (copy from the delivery trait). Add `test_the_prune_keeps_thumbnails_for_recent_deliveries_and_request_lines`: three non-F&V products with photos — one scanned on a session dated yesterday, one on a session dated 40 days ago, one on an open request line (`CustomerRequest` + `CustomerRequestItem` factories or direct creates). Assert `currentPhotoProducts()` contains the first and third and not the second; then run the prune (button or service) with those thumbnails on disk and assert the files for the first and third remain.
  - `ShopRequestsTest`: unchanged and green (the constant move).
**Check:** `php artisan test --filter='FruitVegProductImageTest|ProductThumbnailServiceTest|ShopRequestsTest|ScheduleTest'` green.

### 6. Format and tidy
`./vendor/bin/pint --dirty`; `npm run build`; `php artisan view:clear`.

## Verification (report every item with what you saw)

1. `php artisan test` summary line; the same 15 pre-existing failures.
2. Design-block `cmp` prints nothing (no CSS change).
3. Browser: item 1 (prompt figure), item 2 (card closes, scan restored), the rows and correction card still show `Stock N` as before.
4. On dev, run the manage-page "Tidy thumbnail cache" button (or `sudo -u www-data php artisan fruit-veg:prune-thumbnails`) and confirm the thumbnail file for the delivery product with a photo (`5412533420791`, generated by cycle 24's browser check) is **kept**: list `storage/app/fv-thumbs` before and after, or read the kept/deleted counts and check the file is still there.
5. `php artisan schedule:list` still shows `fruit-veg:prune-thumbnails` at `30 5 * * 0`.

## Risks

- **`currentPhotoProducts()` loads blobs into PHP** (prune needs `IMAGE`). Adding recent-delivery products adds at most a few dozen blobs on a real week; report the count and the peak memory (`memory_get_peak_usage()` in tinker around the call) so we know the headroom.
- **POS `dateUpload` is a string column** in the legacy table; compare with a formatted string, not a Carbon instance, so the SQLite fixture and MySQL behave the same.
- **`orWhereIn` with an empty array** is fine in Laravel, but keep the "skip when empty" pattern the method already uses for `$jonCodes`.
