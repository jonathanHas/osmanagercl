# Admin tools for existing and test vouchers (vouchers cycle 4) — implementation

Status: DONE
Plan revision: 2
Implementer: Opus
Date: 2026-09-29

## Baseline
HEAD: 65477718
Pre-existing dirty files (planning files, not mine):
```
 M docs/vouchers/README.md
 D docs/vouchers/implemented.md
 M docs/vouchers/plan.md
?? docs/vouchers/archive/2026-09-29-cycle-3-sale-activates/
```
`php artisan test` before any change:
```
   FAIL  Tests\Unit\UdeaScrapingServiceTest
   FAIL  Tests\Feature\CashReconciliationTest
   FAIL  Tests\Feature\FruitVegLabelPrintingTest
   FAIL  Tests\Feature\ProductTest
   FAIL  Tests\Feature\TestScraperControllerTest
  Tests:    15 failed, 862 passed (3696 assertions)
```

## Steps

### 0. Baseline — done
Matches the expected `15 failed, 862 passed`.

### 1. Config and transaction types — done
Changed: `config/vouchers.php` (`admin_tools`, `legacy_product_codes`, `pos_balance_text.deleted`), `app/Models/VoucherTransaction.php` (`TYPE_DELETE`, `TYPE_RESTORE`, `TYPE_FOR_SALE`)
Check output:
```
$ php artisan tinker --execute="var_dump(config('vouchers.admin_tools'));"
bool(true)
```

### 2. Till product of a deleted voucher — done
Changed: `app/Services/VoucherPosProductService.php` (`productName()`: trashed → `[deleted]`, before `isForSale()`; `productPrice()`: trashed → 0.0)
Check: step 7's tests.

### 3. Admin service — done
Changed: `app/Services/VoucherAdminService.php` (new; did not exist)
- One private `each()` runs every action per voucher in its own `DB::transaction` with `Voucher::withTrashed()->whereKey($id)->lockForUpdate()->first()`, collects skips as `{code, reason}`, and syncs the till product after each commit. An id that does not exist is skipped as `#<id> not found`; an exception inside one voucher is logged and skipped as `error`, the rest carry on.
- Reasons exactly as the plan: `deleted`, `not active`, `not deactivated`, `active: deactivate it first`, `not deleted`, `not active or deactivated`, `no balance`, `has been spent from`, `balance differs from its initial value`; plus `already deleted` for delete on a trashed voucher (the plan says "deleting twice skips" without naming the reason).
- `changeStatus()` untouched.
Check: step 7's `VoucherAdminServiceTest`.

### 4. Deleted vouchers elsewhere in the app — done
Changed: `app/Services/VoucherTillSyncService.php` (`resolveVoucher()` and both locks `withTrashed()`; trashed handled right after the lock: `inactive` "Voucher was deleted." / `sale_flagged` "Charged €X but the voucher was deleted. Nothing was activated."), `app/Http/Controllers/VoucherController.php` (`lookup()` `withTrashed()` → `{found:false, deleted:true}`; `activate()` 422 for a deleted code; `history()` labels and negative `for_sale` amount), `app/Services/VoucherActivityService.php` (labels/kinds `for_sale`, `delete`, `restore`; negative `for_sale`; `whereHas('voucher')` on transactions and on `deduct`/`issue` totals; till rows `voucher_id IS NULL OR whereHas`), `resources/views/vouchers/transactions.blade.php` (badges and `−€X` for `for_sale`)
Check output:
```
$ php artisan test --filter='VoucherTillSyncServiceTest|VoucherActivityTest'
  ⨯ a deleted vouchers rows still show without a link
  Tests:    1 failed, 55 passed
```
The one failure is the test the plan names for change in step 7 (done there).

### 5. Controller and routes — done
Changed: `app/Http/Controllers/VoucherAdminToolsController.php` (new; did not exist), `routes/web.php` (import + five routes inside the existing `role:admin` group, above `vouchers/{voucher}/deactivate`), `app/Http/Controllers/VoucherController.php` (`list()`: `per_page` 25/50/100/200 else 25; `status=deleted` → `onlyTrashed()` only for an admin with the switch on, otherwise ignored (the normal list, not "no rows"); passes `adminTools`, `showDeleted`, `perPage`)
- Every action goes through one private `run()`: `abort_unless(config('vouchers.admin_tools'), 404)`, the validation from the plan, the service call, and a `status` flash ("Deactivated 36 vouchers. Skipped 1: GV… (not active)."; at most 10 skipped codes then "and N more"). Make for sale reads "Made for sale: N vouchers. …".
Check output:
```
$ php artisan route:list --name=vouchers.bulk
  POST vouchers/bulk/deactivate · delete · for-sale · reactivate · restore   (5 routes)
```
404-when-off is in step 7's tests.

### 6. List page — done
Changed: `resources/views/vouchers/list.blade.php`, `resources/views/vouchers/partials/admin-tools.blade.php` (new; did not exist)
- List page, all behind `@if ($adminTools)`: green `status` flash; "Deleted" status option; "N per page" select; checkbox column (`name="ids[]"`, `form="voucher-bulk"`, `data-status`, `data-balance`) with a header `data-bulk-all` checkbox; the `@include`. The Deleted view shows a "Deleted" pill in the Status cell and "Deleted <date>" in place of Edit / Log / Print; empty state "No deleted vouchers.".
- Partial: the `voucher-bulk` form (CSRF, a hidden `note` bound to the Alpine state, a visible note textarea), "N selected · balance €X", buttons Make for sale / Deactivate selected / Reactivate selected / Delete selected (or Restore selected on the Deleted view), disabled at zero; an in-page dialog with the plan's wording (make for sale names count and total; delete names count, what deleting hides, that it can be restored, and keeps Confirm disabled until the reason has ≥ 3 characters). The `voucherBulk()` component and its document `change` listener (select-all and recount) live in the partial.
- The action buttons open the dialog (`type="button"`); Confirm sets the form's `action` to the chosen route and submits. (The plan suggested `formaction` submit buttons; a submit button would bypass the dialog, so the form action is set in JS instead. Same routes, same fields.)
Check output:
```
node --check on the extracted inline scripts: ok admin-tools_0, ok list_0, ok list_1
$ php artisan view:cache  → all views compile (then view:clear)
```

### 7. Lookup screens and tests — done
Changed: `resources/views/vouchers/index.blade.php` (a `mode === 'deleted'` block "Voucher deleted / This voucher was deleted. It cannot be used." and `data.deleted` checked before the activate branch, so managers get no activation form either), `resources/views/shop/vouchers.blade.php` (flat `deleted` card), `resources/js/shop/vouchers.js` (`mode = data.deleted ? 'deleted' : 'unknown'`; `activating`/`needsManager` only fire for `unknown`/`inactive`, so both are false), `tests/Feature/VoucherAdminServiceTest.php` (new, 14 tests), `tests/Feature/VoucherAdminToolsTest.php` (new, 9 tests), `tests/Feature/VoucherTillSyncServiceTest.php` (+3), `tests/Feature/VoucherActivityTest.php` (named change + 4), `tests/Feature/VoucherPosProductServiceTest.php` (+1), `tests/Feature/Shop/ShopVouchersTest.php` (+2). Both new test files were checked first; neither existed.
- Named change: `test_a_deleted_vouchers_rows_still_show_without_a_link` → `test_a_deleted_vouchers_rows_are_hidden` (asserts zero events).
- One test fix while writing: the Deleted-view test first asserted the page does not contain "Make for sale", but those words are also in the partial's dialog script; it now asserts there is no `ask('forSale')` button handler.
- Make-for-sale "whole path" test: convert → till sale at the price → `activated`, balance 20, price 0 → €7 redemption → 13.
Check output:
```
$ php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession|ShopViewContract'
  Tests:    174 passed (1028 assertions)
$ npm run build → built; node --check resources/js/shop/vouchers.js → ok; index inline script → ok
```

### 8. Retire the three fixed voucher products — done
Changed: `app/Console/Commands/RetireFixedVoucherProducts.php` (new; did not exist), `tests/Feature/RetireFixedVoucherProductsTest.php` (new; did not exist, 6 tests)
- Retire per product in one POS transaction: `ProductsCat::removeProduct()`, ` (retired)` suffix (not doubled), `CODE`/`REFERENCE` → `RET` + code (via `Product::whereKey()->update()`). `already retired` / `not found` reported. `--restore` reverses (bare code, suffix removed, `ProductsCat::addProduct()` only when there is no button; restore also reports `not retired` for a product that was never retired). Aborts with FAILURE and a message when the switch is off.
- One test fix while writing: the test's fixture array used `'6012' => …` keys, which PHP turns into integers; the assertions now cast to string. The command was right.
Check output:
```
$ php artisan test --filter=RetireFixedVoucherProductsTest
  Tests:    6 passed (78 assertions)
$ php artisan vouchers:retire-fixed-products --dry-run      (dev POS copy; dry run only, not run for real)
| code | name            | till button | action                 |
| 6012 | Voucher 50 Euro | yes         | would retire → RET6012 |
| 6013 | Voucher 10 Euro | yes         | would retire → RET6013 |
| 6014 | Voucher 20 Euro | yes         | would retire → RET6014 |
Dry run: nothing was written.
Tills load their buttons at start-up: restart uniCenta on each till to see the change.
```

### 9. Docs — done
Changed: `docs/features/voucher-management.md` (new `## Admin changeover tools`: the switch, the bulk actions table with which statuses each applies to and the skip reasons, the two-step rule for active vouchers, delete is reversible and what it hides, undoing make for sale, the retire command incl. `--dry-run`/`--restore`/restart the tills, the owner's 5-step production checklist, and how to remove the tools and what to keep; *deleted* row in `## Statuses`; routes; key files; a pointer from the single-voucher admin section), `docs/FEATURES_INDEX.md` ("Admin Changeover Tools" line).
Check output:
```
$ grep -n "VOUCHER_ADMIN_TOOLS" docs/features/voucher-management.md
240: … **`VOUCHER_ADMIN_TOOLS`** (`config('vouchers.admin_tools')`, default `true`) …
306: | `vouchers.bulk.for-sale` / … | admin, and `VOUCHER_ADMIN_TOOLS` on |
```

## Deviations

1. **The bulk buttons open the dialog and the form is submitted from JS**, instead of each button being a submit button with its own `formaction` (step 6). A submit button would send the form without the in-page confirmation the plan also asks for. The dialog's Confirm sets the form's `action` to the chosen route and submits the same form (same fields, same routes, CSRF included).
2. **A delete of an already-deleted voucher is skipped as `already deleted`.** The plan says "deleting twice skips" without naming the reason; the other actions use `deleted` for a trashed voucher.
3. **An id that does not exist** is skipped as `#<id> (not found)`, and an unexpected exception for one voucher as `#<id> (error)` (logged). The plan says one bad id must not stop the others without naming the reason.
4. **`--restore` reports `not retired`** for a fixed product whose bare code still exists. The plan named only `already retired` and `not found`.
5. **Flash wording for make for sale** is "Made for sale: N vouchers." (the other four read "Deactivated N vouchers." etc.).

## Verification

1. `./vendor/bin/pint --test` on the 17 PHP files I changed or created → `PASS … 17 files`.
2. `php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession|ShopViewContract'` → `Tests:    180 passed (1106 assertions)` (includes `RetireFixedVoucherProductsTest`, matched by "Voucher").
3. `php artisan test` → `Tests:    15 failed, 901 passed (3965 assertions)`. Failing classes: UdeaScrapingServiceTest, CashReconciliationTest, FruitVegLabelPrintingTest, ProductTest, TestScraperControllerTest — identical to step 0; 862 + 39 new = 901.
4. `php artisan route:list --name=vouchers.bulk -v` → five POST routes (`deactivate`, `delete`, `for-sale`, `reactivate`, `restore`), each with `Authenticate`, `PermissionMiddleware:vouchers.manage`, `RoleMiddleware:admin`.
5. `node --check`: `resources/js/shop/vouchers.js` ok; the inline scripts of `vouchers/list.blade.php` (2 blocks), `vouchers/partials/admin-tools.blade.php` and `vouchers/index.blade.php` ok. `npm run build` → built.
6. Dev run through the service (tinker script in the scratchpad; admin user id 1 "Admin"):
```
created ids 8 (GVDEVADM0001 active €10), 9 (GVDEVADM0002 for sale €5), 10 (GVDEVADM0003 deactivated €7)
deactivate: {"done":1,"skipped":[{"code":"GVDEVADM0002","reason":"not active"},{"code":"GVDEVADM0003","reason":"not active"}]}
delete:     {"done":3,"skipped":[]}
  GVDEVADM0001/2/3: Gift Voucher … [deleted] @ 0
activity events for GVDEVADM*: 0
restore A:  {"done":1,"skipped":[]}
  GVDEVADM0001: status deactivated, balance 10.00, product … [deactivated] @ 0
created id 11 (GVDEVADM0004 active €10)
for sale D: {"done":1,"skipped":[]}
  GVDEVADM0004: status inactive, face 10.00, balance 0.00, product … [for sale €10.00] @ 10
SALE=1 VOUCHER=GVDEVADM0004 TICKET=999920 (ticket 789f9c73-1959-4ad0-be60-3a1af9d1a067) → vouchers:sync-till: tickets 1 | activated 1
  GVDEVADM0004: active balance 10.00 | [bal €10.00]
```
   All three deleted in the delete step (the plan said "the first now deletes"; the second is inactive and the third deactivated, and both are deletable too).
   Cleanup: POS PAYMENTS 1, TICKETLINES 2, TICKETS 1, RECEIPTS 1 for the ticket; the 4 POS products of vouchers 8-11; 1 redemption; 10 voucher_transactions; vouchers 8-11 force-deleted. Left: vouchers 1, 2 and 7 (`GVCHPPB99BFL`, created 2026-09-29 14:08 before my run); no `GVDEVADM%` products; the three fixed products untouched (codes, names and buttons re-checked afterwards). Voucher 1 `GVLH4AU7ASAT` is `deactivated`; this run did not do that.
6b. `php artisan vouchers:retire-fixed-products --dry-run` on dev → three rows (`6012 | Voucher 50 Euro | yes | would retire → RET6012`, 6013, 6014), "Dry run: nothing was written." **Not run for real on dev.**
7. **Browser check: owner, as an admin, on dev.** I did not do it: office pages need a password from the PIN session. The checklist is the plan's Verification 7.

**Incident while writing this report:** my first attempt to append these sections used an unquoted shell heredoc, so bash tried to run the backtick-quoted text as commands. The report was not written by that attempt. The command-like fragments were read-only or dry-run (tests, `route:list`, `pint --test`, `node --check`, `npm run build`, the retire `--dry-run`). Afterwards I re-checked the three fixed products (codes, names, buttons), the voucher count, the POS test tickets and `git status` against the end-of-run snapshot: nothing had changed.

## Files changed

Mine (vouchers cycle 4):
```
?? app/Console/Commands/RetireFixedVoucherProducts.php
?? app/Http/Controllers/VoucherAdminToolsController.php
?? app/Services/VoucherAdminService.php
 M app/Http/Controllers/VoucherController.php
 M app/Models/VoucherTransaction.php
 M app/Services/VoucherActivityService.php
 M app/Services/VoucherPosProductService.php
 M app/Services/VoucherTillSyncService.php
 M config/vouchers.php
 M docs/FEATURES_INDEX.md
 M docs/features/voucher-management.md
 M docs/vouchers/implemented.md
 M resources/js/shop/vouchers.js
 M resources/views/shop/vouchers.blade.php
 M resources/views/vouchers/index.blade.php
 M resources/views/vouchers/list.blade.php
 M resources/views/vouchers/transactions.blade.php
 M routes/web.php
 M tests/Feature/Shop/ShopVouchersTest.php
 M tests/Feature/VoucherActivityTest.php
 M tests/Feature/VoucherPosProductServiceTest.php
 M tests/Feature/VoucherTillSyncServiceTest.php
?? resources/views/vouchers/partials/
?? tests/Feature/RetireFixedVoucherProductsTest.php
?? tests/Feature/VoucherAdminServiceTest.php
?? tests/Feature/VoucherAdminToolsTest.php
```
`docs/vouchers/implemented.md` was " D" at baseline (the cycle 3 report had been archived); this is the new report. Baseline files (`docs/vouchers/README.md`, `plan.md`, the cycle 3 archive folder) untouched. No Shop-track files other than the two the plan allows. Not committed.

## Notes for Planner

1. **Deleted vouchers and `/vouchers/{voucher}/transactions`**: route model binding does not include trashed models, so a deleted voucher's log page is a 404. The Deleted view shows no Log link, so nothing links there; if admins want a deleted voucher's history, that route needs `withTrashed()`.
2. **"Select all" selects the current page only** (as planned). With 200 per page that covers production's 42 vouchers.
3. **The single-voucher Edit modal** still sits beside the bulk bar and knows only deactivate/reactivate. It is hidden on the Deleted view.
4. **Make for sale keeps the old `issue` row**; the history reads Issued → Made for sale (−value) → Sold at till. "Issued today" is unaffected by old issues.
5. **A restored exhausted voucher** goes back to `[€0.00 used up]` (the name follows the status). Only for-sale and deactivated restores are in the plan's tests; the exhausted case is covered by the name rules.
6. **Dev data**: voucher 1 `GVLH4AU7ASAT` is deactivated and voucher 7 `GVCHPPB99BFL` exists (for sale €5); both predate this run.
