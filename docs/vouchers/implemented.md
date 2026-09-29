# Selling a voucher at the till activates it (vouchers cycle 3) — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-29

## Baseline
HEAD: 88447f40
Pre-existing dirty files (planning files, not mine):
```
 M docs/vouchers/README.md
 D docs/vouchers/implemented.md
 M docs/vouchers/plan.md
?? docs/vouchers/archive/2026-09-29-cycle-2-activity-screen/
```
`php artisan test` before any change:
```
   FAIL  Tests\Unit\UdeaScrapingServiceTest
   FAIL  Tests\Feature\CashReconciliationTest
   FAIL  Tests\Feature\FruitVegLabelPrintingTest
   FAIL  Tests\Feature\ProductTest
   FAIL  Tests\Feature\TestScraperControllerTest
  Tests:    15 failed, 835 passed (3577 assertions)
```

## Steps

### 0. Baseline — done
Matches the expected `15 failed, 835 passed`.

### 1. Migrations — done
Changed: `database/migrations/2026_09_30_100000_add_face_value_to_vouchers_table.php`, `2026_09_30_100001_add_sale_amount_to_voucher_till_redemptions_table.php` (both new; neither existed)
Check output:
```
  2026_09_30_100000_add_face_value_to_vouchers_table ............ 79.67ms DONE
  2026_09_30_100001_add_sale_amount_to_voucher_till_redemptions_table  75.11ms DONE
bool(true) bool(true)
```

### 2. Models and config — done
Changed: `app/Models/Voucher.php` (`face_value` fillable + cast, `isForSale()`), `app/Models/VoucherTillRedemption.php` (`STATUS_ACTIVATED`, `STATUS_SALE_FLAGGED`, `sale_flagged` in `EXCEPTION_STATUSES`, `sale_amount` fillable + cast), `config/vouchers.php` (`pos_balance_text.for_sale`, `max_face_value`)
Check output:
```
$ php artisan tinker --execute="$v=new App\Models\Voucher(['status'=>'inactive','face_value'=>20]); var_dump($v->isForSale());"
bool(true)
```

### 3. Till product carries the price — done
Changed: `app/Services/VoucherPosProductService.php` (`productPrice()`, for-sale name, create at price, name-or-price drift updates both columns, reported as `renamed`), `app/Console/Commands/SyncVoucherPosProducts.php` (`value` column)
Check output:
```
$ php artisan vouchers:sync-pos-products --all
| code         | status | value | balance | action    |
| GVLH4AU7ASAT | active | —     | 20.00   | unchanged |
| GVVUF2SUACUM | active | —     | 9.00    | unchanged |
0 created, 0 linked, 0 renamed, 2 unchanged, 0 failed.
```

### 4. Generate with a value — done
Changed: `app/Http/Controllers/VoucherController.php` (`amount` validation, `face_value` on create; `print()` passes `face_value`), `resources/views/vouchers/generate.blade.php` (value input, new help text incl. "keep batches of different values apart"), `resources/views/vouchers/print.blade.php` (value under the code on screen; ZPL unchanged)
`tests/Feature/VoucherPosProductServiceTest.php`: the two generate tests now post `amount` (named in the plan); **also** `test_generating_vouchers_creates_their_products` asserted three `[not active]` names, which are now `[for sale €20.00]` (see Deviations).
Check output: `php artisan test --filter=VoucherPosProductServiceTest` → `11 passed (41 assertions)`. Step 4's own check (3 products priced 20, validation error without amount) is in step 8's tests.

### 5. The till check learns about sales — done
Changed: `app/Services/VoucherTillSyncService.php`
- New private `voucherGroups()`: reads `LINE, PRODUCT, UNITS, PRICE, CODE, tx.RATE` (left join `TAXES`), groups per product in order of first LINE, sums `units` and `sale_total = Σ UNITS × PRICE × (1 + RATE)`, rounded to 2 dp; `isSale = |sale_total| > 0.005`.
- `syncTicket()` now loops over the groups; sale groups go to the new `applySale()`, redemption groups to the unchanged `applyLine()`. `isLast` is "this product is the last *redemption* group".
- `applySale()` rules in the plan's order (refund, unknown, not inactive, Free tender, units ≠ 1, value mismatch, activate), every row with `sale_amount`, `amount_deducted 0`, `shortfall 0`; activation writes the redemption row first, then the voucher, then an `issue`/`till` transaction with note `Till #N`, then links it.
- After the loop, every voucher deducted from or activated gets `posProducts->sync()` (price 0, `[bal €X]` for an activated one).
- `COUNT_KEYS`: `activated`, `sale_flagged` inserted after `refund`.
Check output:
```
$ php artisan test --filter=VoucherTillSyncServiceTest
  Tests:    23 passed (108 assertions)
```

### 6. Screens say what happened — done
Changed: `app/Http/Controllers/VoucherController.php` (`lookup()` + `face_value`, `for_sale`; `history()` till `issue` → "Sold at till"), `app/Services/VoucherActivityService.php` (till `issue` → "Sold at till"; `sale_flagged` → "Sale not activated"; `sale_amount` on both mappers), `resources/views/vouchers/exceptions.blade.php` (red "Sale not activated" pill, "Sale" column after "Voucher tender", empty-state colspan 10 → 11), `resources/views/vouchers/activity.blade.php` ("charged €X" second line behind `x-if`; `replace` → `replaceAll` so `sale_flagged` reads "sale flagged"), `resources/views/vouchers/list.blade.php` and `transactions.blade.php` ("€20.00 for sale" yellow pill when `initial_value` is NULL and `face_value` is set).
Check output:
```
$ node --check <activity inline script>   → NODE_OK
$ php artisan test --filter='VoucherActivityTest|VoucherTillExceptionsTest|ShopVouchersTest'
  Tests:    37 passed (174 assertions)
```

### 7. Lookup screens for an unsold voucher — done
Changed: `resources/views/vouchers/index.blade.php`, `resources/views/shop/vouchers.blade.php`, `resources/js/shop/vouchers.js`
- Office: `faceValue`/`forSale` state from the lookup (reset in `reset()`); managers get a yellow "Value €20.00. Normally sold at the till…" line and the starting balance pre-filled; employees get "Not sold yet (€20.00). Sell it at the till: scan the label as an item." in place of "Please ask a manager." (both texts are in the markup, toggled by `x-show`). A new `money2()` helper formats the value (the existing `money()` is the signed history formatter).
- Shop: `forSale`/`faceValue` getters; the for-sale employee card; `needsManager && ! forSale`; the manager hint split into the original text (`activating && ! forSale`) and the for-sale text; `onScan()` pre-fills `typed` with the face value for a manager on a for-sale voucher. Only `shop-*` classes, no new `route()`.
Check output:
```
$ node --check resources/js/shop/vouchers.js && echo JS_OK   → JS_OK
$ node --check <index inline script>                          → NODE_OK
$ npm run build                                               → ✓ built in 9.84s
$ php artisan test --filter='ShopVouchersTest|ShopViewContractTest|ConfinePinSessionTest'
  Tests:    43 passed (369 assertions)
```

### 8. Tests — done
Changed (additions only, except where named): `tests/Feature/VoucherPosProductServiceTest.php` (+7), `tests/Feature/VoucherTillSyncServiceTest.php` (+13), `tests/Feature/VoucherTillExceptionsTest.php` (+1), `tests/Feature/VoucherActivityTest.php` (+2), `tests/Feature/Shop/ShopVouchersTest.php` (+4, and the class now also uses `CreatesVoucherPosTables` for the manual-activation test)
- Changed assertions: the two generate tests post `amount` (named in the plan) and the `[not active]` name count became `[for sale €20.00]` (Deviations 1).
- Beyond the plan's list: `amount` with 3 decimals and above `max_face_value` rejected; the print page shows the value; active/valueless vouchers report `for_sale false` / `face_value null`.
- Test fix while writing: "after activation the label redeems normally" first failed because my `ticket()` helper priced the voucher line at `face_value` (which stays 20 after activation); on the till the product's price is 0 by then. The test now reads the product's current price for that line. The code was right.
Check output:
```
$ php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession|ShopViewContract'
  Tests:    141 passed (837 assertions)
```

### 9. Dev helper and docs — done
Changed: `docs/vouchers/scripts/simsale.php` (`SALE=1`, `PRICE`, `UNITS`, `PAY`; without `SALE` the same lines and payments as before; header documents both), `docs/features/voucher-management.md` (Overview lifecycle steps 1-4 rewritten; `inactive` status row; `face_value`/`sale_amount` in Data model; new `## Selling a voucher at the till` with cashier steps, "never use the quantity key", keep batches apart, the flag table, the one-minute window, manual activation, products 6012-6014, finance unchanged; `activated`/`sale_flagged` in the exception table; generate-form paragraph; POS product price; key files incl. new migrations and the dev scripts), `docs/FEATURES_INDEX.md` ("Sold at the Till" bullet), `CLAUDE.md` (voucher line).
Check output:
```
$ grep -c "sale_flagged\|face_value" docs/features/voucher-management.md
10
$ php -l docs/vouchers/scripts/simsale.php
No syntax errors detected
```

## Deviations

1. **One more changed assertion than the plan named.** `VoucherPosProductServiceTest::test_generating_vouchers_creates_their_products` asserted three products named `%[not active]`. With the now-required `amount`, generated vouchers are for sale and named `[for sale €20.00]`, so that assertion now counts `%[for sale €20.00]` (commented in the test). The plan only named the extra `amount` parameter.
2. **`ShopVouchersTest` uses `CreatesVoucherPosTables`** (trait added to the class) for the plan's "manager activating a for-sale voucher by hand leaves the product at price 0" test, which needs POS tables. No existing test in the class changed.
3. **Office `index.blade.php` gained a `money2()` helper** (plain `€0.00` formatting). The existing `money()` is the signed history formatter ("+€20.00", "—" for zero) and would read wrongly in "Value €20.00".
4. **`simsale.php` was run through Pint** (single-quote fix) because it failed `pint --test`; not strictly application code.
5. The Shop manager-hint change keeps the original sentence behind `activating && ! forSale` and shows the for-sale sentence behind `activating && forSale` (the plan says "the hint reads …"; both are in the markup, as the plan requires for the no-value case).

## Verification

1. `./vendor/bin/pint --test` on the 16 PHP files I changed or created → `PASS … 16 files` (after the `simsale.php` fix above).
2. `php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession|ShopViewContract'` → `Tests:    141 passed (837 assertions)`.
3. `php artisan test` → `Tests:    15 failed, 862 passed (3696 assertions)`. Failing classes: UdeaScrapingServiceTest, CashReconciliationTest, FruitVegLabelPrintingTest, ProductTest, TestScraperControllerTest — identical to step 0; 835 + 27 new = 862.
4. `node --check resources/js/shop/vouchers.js`, and the extracted inline scripts of `vouchers/index.blade.php` and `vouchers/activity.blade.php` → clean (`ALL_NODE_OK`). `npm run build` → `✓ built in 9.84s`.
5. Dev run (tinker + `docs/vouchers/scripts/simsale.php`):
   - Created vouchers id 5 `GVDEVSALE001` and id 6 `GVDEVSALE002`, €20 for sale. POS products `e60041b7-c2b9-460e-96d1-9b20d542652d` and `32482827-55c6-4e55-ab61-06b842b7cfca`: `Gift Voucher GVDEVSALE00x [for sale €20.00] | PRICESELL 20`.
   - `SALE=1 VOUCHER=GVDEVSALE001 TICKET=999910` (ticket `c86d13ad-…`, magcard 32.30) → `vouchers:sync-till`: `tickets 1 | activated 1`. Voucher `active 20.00`; product `[bal €20.00] | PRICESELL 0`; `payload(7, null)`: `Sold at till · Till #999910 · 20 · charged 20`.
   - Redemption `TENDER=5 TICKET=999911` (ticket `812e76f2-…`) → `tickets 2 | applied 1 | skipped 1`; `GVDEVSALE001: active 15.00 | [bal €15.00] | PRICESELL 0`.
   - `SALE=1 UNITS=2 PAY=cash VOUCHER=GVDEVSALE002 TICKET=999912` (ticket `cb471912-…`) → `tickets 3 | sale_flagged 1 | skipped 2`; `GVDEVSALE002: inactive 0.00 | [for sale €20.00] | PRICESELL 20`; redemption note `Quantity 2 on one voucher (charged €40.00). Not activated: each voucher is scanned itself.`
   - Cleanup: POS PAYMENTS 3, TICKETLINES 6, TICKETS 3, RECEIPTS 3 deleted for the three ticket ids; the two POS products deleted; local redemptions 8, 9, 10, voucher_transactions 2, vouchers 5 and 6 (force-deleted). Left: the 2 original dev vouchers, no `GVDEV%` products, no till tickets ≥ 999900. The heartbeat keys moved (last run `command`).
6. **Owner, on the dev till and in the browser** (not done by me: office pages need a password from the PIN session, and the till is yours): the plan's Verification 6 checklist. With no cron on dev, run `php artisan vouchers:sync-till` after each till sale.

## Files changed

Mine (vouchers cycle 3):
```
?? database/migrations/2026_09_30_100000_add_face_value_to_vouchers_table.php
?? database/migrations/2026_09_30_100001_add_sale_amount_to_voucher_till_redemptions_table.php
 M CLAUDE.md
 M app/Console/Commands/SyncVoucherPosProducts.php
 M app/Http/Controllers/VoucherController.php
 M app/Models/Voucher.php
 M app/Models/VoucherTillRedemption.php
 M app/Services/VoucherActivityService.php
 M app/Services/VoucherPosProductService.php
 M app/Services/VoucherTillSyncService.php
 M config/vouchers.php
 M docs/FEATURES_INDEX.md
 M docs/features/voucher-management.md
 M docs/vouchers/implemented.md          (was " D": the cycle 2 report was archived; this is the new report)
 M docs/vouchers/scripts/simsale.php
 M resources/js/shop/vouchers.js
 M resources/views/shop/vouchers.blade.php
 M resources/views/vouchers/activity.blade.php
 M resources/views/vouchers/exceptions.blade.php
 M resources/views/vouchers/generate.blade.php
 M resources/views/vouchers/index.blade.php
 M resources/views/vouchers/list.blade.php
 M resources/views/vouchers/print.blade.php
 M resources/views/vouchers/transactions.blade.php
 M tests/Feature/Shop/ShopVouchersTest.php
 M tests/Feature/VoucherActivityTest.php
 M tests/Feature/VoucherPosProductServiceTest.php
 M tests/Feature/VoucherTillExceptionsTest.php
 M tests/Feature/VoucherTillSyncServiceTest.php
```
Baseline files (`docs/vouchers/README.md`, `plan.md`, the cycle 2 archive folder) untouched. No Shop-track files other than the two the plan allows. Not committed.

## Notes for Planner

1. **`face_value` stays set after activation.** The plan did not say whether to clear it; I kept it (it records what the voucher was sold for, and `isForSale()` is false once active). Side effect worth knowing: `reset_voucher.php` or a manager setting a used voucher back to `inactive` would make it "for sale" again at its old value. Nothing in the app sets a voucher back to inactive today.
2. **Deactivate/reactivate of an unsold voucher.** `changeStatus()` only deactivates `active` vouchers, so a for-sale voucher cannot be deactivated (e.g. a lost unsold label). Its till product keeps charging. A future "void unsold voucher" action may be wanted.
3. **A sale line and a redemption line of the same voucher on one ticket** (scan to sell, then scan again to spend it at once) are one product, so they group into one line with `units 2` and a charge → `sale_flagged` (quantity). Correct by the owner's rule, but the note says "Quantity 2", which may confuse; the cashier really did a sell-then-redeem. Worth a line in cashier training.
4. **The sale's charge includes tax**: `sale_total` uses the line's `TAXID` rate. Voucher products are `TAXCAT 000` so this is 0% today; if a till user ever changed the tax on the line, the charge would differ from the value and be flagged, which is the intended safety.
5. **Old vouchers** (no `face_value`) behave exactly as before: `[not active]`, price 0, manual activation. The 37 production vouchers without a till product (parked finding) are all of this kind.
6. **Label wording**: the till line for an unsold voucher reads `Gift Voucher GV… [for sale €20.00]` from `config('vouchers.pos_balance_text.for_sale')`; the receipt prints it the same way. The owner can reword it in config.
