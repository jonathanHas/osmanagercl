# Cycle 1 — Case size on the New request sheet, order by the case or by the unit — implementation

Status: IN PROGRESS
Plan revision: 1
Implementer: Opus
Date: 2026-10-07

## Baseline
HEAD: 7334f05f
Pre-existing dirty files:
```
 M docs/shop_new/ToDo.txt
?? docs/requests/
```

## Steps

### 1. Migration — done
Changed: `database/migrations/2026_10_07_100000_add_unit_to_customer_request_items.php` (new)
Check output:
```
2026_10_07_100000_add_unit_to_customer_request_items ......... 130.92ms DONE
bool(true)
```

### 2. Model and factory — done
Changed: `app/Models/CustomerRequestItem.php` (UNIT_* constants, fillable, `case_units` cast,
`isByTheCase()`, `quantityLabel()`, static `formatQuantity()`), `database/factories/CustomerRequestItemFactory.php`
(`unit`/`case_units` defaults, `byTheCase()` state)
Check output:
```
2 cases of 6|1 case|1.5
```

### 3. Unit tests for the label — done
Changed: `tests/Unit/CustomerRequestItemQuantityLabelTest.php` (new; did not exist)
Check output:
```
Tests:    3 passed (6 assertions)
```

### 4. Validation accepts `unit` — done
Changed: `app/Http/Requests/CustomerRequestRequest.php` (`unit` in ITEM_KEYS, rule, message)

### 5. Service snapshots the case size — done
Changed: `app/Services/CustomerRequestService.php`
- `lineFields()` sets `unit` / `case_units` via `isCaseLine()` (case only with a product code).
- `snapshotCaseUnits($items, ?$existing)` runs after `snapshotProductNames()` in `create()` and
  `update()`. It always overwrites any client `case_units`. **Keep rule chosen:** `snapshotCaseUnits()`
  takes the request's existing lines keyed by id; a line keeps its stored size when it is already
  `case`, has a non-null size **and the same product_code** (a relinked line re-snapshots).
- `caseUnitsByCode()` public, `> 1` rule, one `SupplierLink` query.
- `awaitingArrivalPayload()` gains `unit`, `case_units`, `quantity_label`.

### 6. Feature tests — done
Changed: `tests/Feature/CustomerRequestTest.php` (POS `supplier_link` gains nullable `CaseUnits`, a link for
`5000000000017` with 6; tests `test_a_line_by_the_case_snapshots_the_supplier_case_size` (includes posted
`case_units => 99` → stored 6, sourcing line sent as case → unit, product without link → `1 case`),
`test_an_unknown_unit_is_rejected`, `test_update_snapshots_the_case_size_once_and_keeps_it` (switch to case → 6;
CaseUnits changed to 12, edit quantity → still 6; back to unit → null)),
`tests/Feature/CustomerRequestDeliveryFlagTest.php` (new test `test_a_line_by_the_case_reads_as_cases_on_the_delivery_screens`;
existing `quantity` assertion kept untouched).
Check output (before step 8; the two failures are the view assertions step 8 makes pass):
```
⨯ a line by the case reads as cases on the delivery screens   ➜ assertSee('2 cases of 6') on match page
⨯ a line by the case snapshots the supplier case size         ➜ board assertSee
Tests:    2 failed, 33 passed (249 assertions)
```
Note: `validated()` returns the lines reordered (keys present per line differ, so Laravel's wildcard
merge puts the sourced line last). The test looks lines up by content rather than position. Pre-existing
behaviour, not changed — see Notes for Planner.

### 7. Search API carries `case_units` — done
Changed: `app/Services/ProductSearch/ProductSearchService.php` (top-level `case_units`, `> 1` rule),
`tests/Concerns/CreatesProductSearchPosTables.php` (`8721325594341` link `CaseUnits` 6; the other three rows get
`'CaseUnits' => null` explicitly because a batch `insert()` needs the same keys on every row — same value as
leaving them out, the column is nullable with no default), `tests/Feature/ProductSearchApiTest.php` (asserts 6 / null),
`docs/features/product-search.md` (JSON example + one sentence).
Check output:
```
php artisan test --filter=ProductSearchApiTest
Tests:    14 passed (99 assertions)
```

### 8. Views and delivery strings use the label — done
Changed: `shop/partials/request-row.blade.php` (`$qty` removed), `shop/partials/request-card.blade.php`
(`$qty` param removed, docblock updated), `shop/requests.blade.php` (`$qtyOf` and the `'qty'` include param removed),
`shop/request-show.blade.php` (`$qty` closure removed), `delivery-legacy/partials/customer-request-badge.blade.php`,
`delivery-legacy/match.blade.php` (scanner `cr.quantity_label`; summary card label), `resources/js/shop/delivery-scan.js`
(flag text with `(quantity_label)`).
The board-render assertion moved from `CustomerRequestTest` to `CustomerRequestDeliveryFlagTest`: `CustomerRequestTest`'s
POS fixture has no `PRODUCTS.IMAGE`, so rendering the board with a product line 500s in
`ProductThumbnailService::versions()`. The delivery-flag fixture is complete, so the new test there also checks the
staff board row (`Jane Doe &middot; 2 cases of 6`) and the show page.
Check output:
```
php artisan test --filter=CustomerRequest
Tests:    35 passed (257 assertions)
php artisan test --filter=ShopViewContractTest
Tests:    27 passed (297 assertions)
```

### 9. New request sheet — code done; browser check pending (see Verification 6)
Changed: `resources/views/shop/partials/request-form.blade.php` (facts `shop-row__meta` line via `pickedFacts`;
`shop-choices` Units / Case cards behind `x-show="picked?.case_units"`, radios without `name`; hidden
`items[0][unit]` bound to `unitValue`; Quantity label `x-text` → Cases), `resources/js/shop/requests.js`
(`onPick()` keeps `case_units`, `stock_units`, `price_with_vat`, resets `unit`; `unpick()` resets `unit`; seed
reads `seed.unit` and `seed.product?.case_units`; getters `unitValue`, `pickedFacts` using `quantityText`),
`app/Http/Controllers/CustomerRequestController.php` (`seedItems()` adds `unit` (old input and model) and
`case_units` (model); one `caseUnitsByCode()` call per seed set; `seedProduct()` gains `case_units`).
The Quantity label keys on `unitValue` (not raw `unit`) so it cannot read "Cases" on a Sourcing line or a
product with no case size.

### 10. Edit screen — code done; browser check pending
Changed: `resources/views/shop/request-edit.blade.php` (hidden `items[idx][unit]` via `unitValue(item)`;
`shop-choices` pair above the `shop-facts--2` block, `x-show="item.product_code && item.case_units"`; Quantity
label flips to Cases), `resources/js/shop/request-edit.js` (`withKey()` carries `unit` and
`case_units: line.case_units ?? line.product?.case_units ?? null`; `onPick()` sets `case_units`; `unlink()`
resets both; new `unitValue(item)` method).

### 11. Docs — done
Changed: `docs/features/customer-requests.md` (schema gains `unit`, `case_units`; new section
"By the case or by the unit" after "Product pictures"; changelog 2026-10-07; stale Views bullet now names the
Shop views).
Check output:
```
grep -n "views/customer-requests" docs/features/customer-requests.md   → (nothing)
grep -n "customer-requests/" → only route URLs (/customer-requests/photo/{code}, the route table)
```

### 12. Format and build — done
Check output:
```
./vendor/bin/pint --dirty   → PASS ... 12 files (then clean)
npm run build               → ✓ built in 10.79s; shop-DSXQ1-wV.js 50.57 kB.
  The only warning is the pre-existing "chunks larger than 500 kB" for zpl-preview / barcode-scanner, not the shop entry.
```
