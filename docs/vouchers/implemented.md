# Till-driven gift voucher redemption (cycle 28) — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-27

## Baseline
HEAD: 4e7373ea
Pre-existing dirty files (cycle 27 work, not mine):
```
 M app/Http/Controllers/ProfileController.php
 M config/shop.php
 M docs/FEATURES_INDEX.md
 M docs/features/shop-mode.md
 M resources/views/layouts/admin.blade.php
 M resources/views/profile/edit.blade.php
 M routes/web.php
 M tests/Feature/Shop/ConfinePinSessionTest.php
 M tests/Feature/UserPinManagementTest.php
?? app/Http/Controllers/ShopDeviceAdminController.php
?? docs/planImp/archive/2026-09-27-shop-mode-cycle-27/
?? docs/planImp/plan.md
?? resources/views/profile/partials/update-pin-form.blade.php
?? resources/views/shop-devices/
?? tests/Feature/ProfilePinTest.php
?? tests/Feature/ShopDeviceAdminTest.php
```
Note: `docs/FEATURES_INDEX.md`, `resources/views/layouts/admin.blade.php` and `routes/web.php` were already dirty; my edits to them are additive voucher hunks only.

## Steps
### 1. Config — done
Changed: `config/vouchers.php` (new; did not exist)
Check output:
```
$ php artisan tinker --execute="echo config('vouchers.pos_category_name');"
Gift Voucher Redemption
```

### 2. Migrations — done
Changed: `database/migrations/2026_09_28_100000_create_voucher_till_redemptions_table.php`, `2026_09_28_100001_add_source_to_voucher_transactions_table.php`, `2026_09_28_100002_add_pos_product_id_to_vouchers_table.php` (all new)
Check output:
```
  2026_09_28_100000_create_voucher_till_redemptions_table ...... 582.96ms DONE
  2026_09_28_100001_add_source_to_voucher_transactions_table .... 50.38ms DONE
  2026_09_28_100002_add_pos_product_id_to_vouchers_table ....... 126.27ms DONE
bool(true) bool(true) bool(true)
```

### 3. Models — done
Changed: `app/Models/VoucherTillRedemption.php` (new), `app/Models/VoucherTransaction.php`, `app/Models/Voucher.php`
Check output:
```
till
```

### 4. POS product service — done
Changed: `app/Services/VoucherPosProductService.php` (new)
Added a small `lastAction()` accessor (created|linked|renamed|unchanged|failed) so `syncMany()` and the backfill command can report what happened without changing `sync()`'s `?string` return.
Check output (dev POS copy, 127.0.0.1:3307):
```
sync(Voucher 1) → "922add2e-8ea0-4df4-9b17-45bcd1f512ad", lastAction "created"
PRODUCTS: NAME "Gift Voucher GVLH4AU7ASAT [bal €20.00]", CODE/REFERENCE GVLH4AU7ASAT,
          CATEGORY 482fba08-… , TAXCAT 000, PRICESELL 0, ISSERVICE 1
CATEGORIES: 482fba08-fe6c-4b12-a6b6-256d7c118f44 "Gift Voucher Redemption", PARENTID 033, CATSHOWNAME 0
PRODUCTS_CAT rows for GV products: 0
```

### 5. Backfill command — done
Changed: `app/Console/Commands/SyncVoucherPosProducts.php` (new)
Returns FAILURE when any voucher failed to sync.
Check output (dry run lists one voucher, not two: step 4's check had already created voucher 1's product):
```
$ php artisan vouchers:sync-pos-products --dry-run
| GVVUF2SUACUM | active | 20.00   | would create |
1 voucher(s) would be synced (dry run, nothing written).
$ php artisan vouchers:sync-pos-products
| GVVUF2SUACUM | active | 20.00   | created |
1 created, 0 linked, 0 renamed, 0 unchanged, 0 failed.
$ php artisan vouchers:sync-pos-products
0 created, 0 linked, 0 renamed, 0 unchanged, 0 failed.
$ php artisan vouchers:sync-pos-products --all
| GVLH4AU7ASAT | active | 20.00   | unchanged |
| GVVUF2SUACUM | active | 20.00   | unchanged |
0 created, 0 linked, 0 renamed, 2 unchanged, 0 failed.
```

### 6. Till sync service — done
Changed: `app/Services/VoucherTillSyncService.php` (new); also `database/migrations/2026_09_28_100000_…` (see Deviations: `voucher_tender`/`shortfall` widened to decimal(16,2); rolled back batch 96 and re-migrated, then `vouchers:sync-pos-products` re-linked both vouchers via the CODE self-heal: `0 created, 2 linked`).
Implementation notes:
- An already-recorded line (overlap window / retry) is skipped but its `amount_deducted` is still taken off the shared tender pool, so a ticket half-recorded before a failure splits the tender the same way on retry.
- Non-applied rows get a short explanatory `note` (e.g. `Voucher was deactivated (balance €20.00).`, `Voucher scanned but not paid with the Voucher tender.`, `No voucher with this code in the app.`) so the exceptions page explains itself; `markReviewed` appends to it.
- Refund rows still resolve and link `voucher_id` (for the exceptions page link) but never touch the balance.
Check: covered by step 11's `VoucherTillSyncServiceTest` (results below).

### 7. Sync command and schedule — done
Changed: `app/Console/Commands/SyncVoucherTillRedemptions.php` (new), `routes/console.php`, `tests/Feature/ScheduleTest.php`
Check output:
```
$ php artisan schedule:list | grep voucher
  *  *  * * *  php artisan vouchers:sync-till .. Next Due: 46 seconds from now
$ php artisan test --filter=ScheduleTest
  Tests:    5 passed (55 assertions)
$ php artisan vouchers:sync-till
| tickets | applied | partial | no_tender | inactive | unknown | refund | skipped |
| 0       | 0       | 0       | 0         | 0        | 0       | 0      | 0       |
```

### 8. Controller: hooks, history, exceptions — done
Changed: `app/Http/Controllers/VoucherController.php`, `routes/web.php`
- `index()` now takes `Request` to check `vouchers.manage` for `exceptionCount`.
- POS sync after activate/deduct/changeStatus goes through one private `syncPosProduct(string $code)` (reloads by code, then `VoucherPosProductService::sync`, which never throws).
- `markReviewed` appends the note to any existing note with ` · `, capped at 500 chars.
Check output:
```
$ php artisan route:list --name=vouchers | grep -i exception
  GET|HEAD   vouchers/exceptions vouchers.exceptions › VoucherController@exce…
  POST       vouchers/exceptions/{redemption}/reviewed vouchers.exceptions.re…
$ php artisan test --filter=ShopVouchersTest
  Tests:    10 passed (39 assertions)
```

### 9. Office views — done
Changed: `resources/views/vouchers/index.blade.php`, `resources/views/vouchers/transactions.blade.php`, `resources/views/vouchers/exceptions.blade.php` (new; did not exist), `resources/views/layouts/admin.blade.php`, `resources/views/vouchers/print.blade.php` (see Deviations)
- index: `history`/`manualOpen` state; history list under the balance (ACTIVE) and under "€0.00 remaining" (EXHAUSTED); "Manual deduct" button reveals the old input/"Use full balance"/Deduct; a one-line hint "Redeem at the till: scan this voucher, then pay with the Voucher tender."; after a manual deduct the history is re-read quietly (`refreshHistory()`); header link "Till exceptions (N)" for managers when N > 0. No Blade-clashing `@` shorthands.
- transactions: purple "till" badge next to the type; By = `Till #N`.
- exceptions: dark table (list.blade.php styling), pills as specified, "Show reviewed"/"Hide reviewed" toggle, inline "Mark reviewed" form with optional note, empty state, pagination.
Check: rendering covered by `VoucherTillExceptionsTest` (step 11); browser check in Verification 8.

### 10. Shop view — done
Changed: `resources/views/shop/vouchers.blade.php`, `resources/js/shop/vouchers.js`
- `manualOpen` state; ghost "Manual deduct" button at the bottom of the left column (`mode === 'active' && ! manualOpen`); numpad column, "Use full balance" and actions row show on `(mode === 'active' && manualOpen) || activating`, so activation is unchanged. `who(t)` helper in the history meta. `manualOpen` resets on scan and after a successful deduct. No `route()` calls added. `npm run build` ran clean.
Check output:
```
$ php artisan test --filter='ShopVouchersTest|ConfinePinSessionTest'
  Tests:    18 passed (120 assertions)
```

### 11. Tests — done
Changed: `tests/Concerns/CreatesVoucherPosTables.php` (new), `tests/Feature/VoucherPosProductServiceTest.php` (new), `tests/Feature/VoucherTillSyncServiceTest.php` (new), `tests/Feature/VoucherTillExceptionsTest.php` (new), `tests/Feature/Shop/ShopVouchersTest.php` — all four new files checked first; none existed.
- Trait also has `posGoods()` for the non-voucher line; TAXES seeded with `000` (0) and `001` (0.23).
- Extra cases beyond the plan list: status-driven names, two vouchers both fully applied, `--since` override, ordinary sales ignored, `syncIfDue` throttle + off switch + POS outage, generate warning when POS is down, manual deduct renames the product, employee cannot mark reviewed, header link hidden at 0.
- `ShopVouchersTest::test_lookup_includes_history_and_issued_at`: the till row is newest, so the existing assertions moved from `history.0/1` to `history.1/2`; the existing manual deduct row got `created_at = now()->subDay()` so `latest()` ordering is deterministic against the new till row (same-second rows would tie). No assertion was removed.
- One fix while writing: the reviewed-row test first failed because the un-followed redirect's flash ("Till #430025 marked reviewed.") rendered on the next GET; the test now spends the flash first.
Check output:
```
$ php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession'
  Tests:    60 passed (339 assertions)
```

### 12. Docs — done
Changed: `docs/features/voucher-management.md` (new "Till redemption (uniCenta)" section: cashier steps, why a product line, the POS product, backfill command, the sync, exception statuses table, manual fallback, follow-ups; plus lifecycle step 4, data model, till/shop screen descriptions, lookup `source`/`ticket_number`, routes table, key files), `docs/FEATURES_INDEX.md` (voucher bullets), `CLAUDE.md` (voucher line), `docs/finance_manager/database-schema.md` (PAYMENTS: `NOTES` not `VOUCHER`, TRANSID/TENDERED explained, live payment types incl. `paperin`).
Check output:
```
$ grep -n "sync-till" docs/features/voucher-management.md
71:`App\Services\VoucherTillSyncService`, run by `php artisan vouchers:sync-till [--since=ISO]` **every minute** …
```

## Deviations

1. **`voucher_tender` and `shortfall` are `decimal(16,2)`, not `decimal(14,2)`.** The plan's own Risk item says the live 5,390,746,000,620 `paperin` row should land as `partial` with a huge shortfall, but `decimal(14,2)` holds at most 12 integer digits (999,999,999,999.99); that value has 13, so on MySQL strict mode the insert would fail and the ticket would be logged and retried forever instead of showing up on the exceptions page. Confirmed on the dev POS copy: `TOTAL = 5390746000620` (column type `double`). Rolled back batch 96 (no data yet) and re-migrated.
2. **`resources/views/vouchers/print.blade.php`** gained a 3-line `session('warning')` banner. Step 8 flashes the "could not be created on the till" warning onto the redirect to the print page, but neither that page nor the admin layout renders flashes, so the warning would have been invisible.
3. **`VoucherPosProductService::lastAction()`** added (see step 4) so `syncMany()` can count `created` and the backfill command can print an action per row; `sync()` still returns `?string` as specified.
4. **Explanatory `note` on non-applied redemption rows** (`inactive`, `no_tender`, `unknown`); the plan left `note` for the reviewer only. `markReviewed` appends the reviewer's note after ` · `.
5. **`ShopVouchersTest` history test**: existing manual deduct row back-dated one day so the new till row is deterministically newest; indices shifted by one. No assertion removed.

## Verification

1. `./vendor/bin/pint --test <my 19 PHP files>` → `PASS … 19 files`. (Ran on my files only, not `--dirty`, because `--dirty` would also reformat the pre-existing cycle 27 dirty files.)
2. `php artisan migrate` → `INFO  Nothing to migrate.` (the three migrations ran earlier, see step 2/6).
3. `php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession|RoutePermissions'` → `Tests:    117 passed (397 assertions)`.
4. `php artisan test` → `Tests:    15 failed, 797 passed (3384 assertions)`. Failing classes: UdeaScrapingServiceTest ×7, CashReconciliationTest ×3, FruitVegLabelPrintingTest ×2, ProductTest ×2, TestScraperControllerTest ×1 — identical to the baseline; no new failures.
5. `php artisan schedule:list` → `*  *  * * *  php artisan vouchers:sync-till .. Next Due: 46 seconds from now`.
6. `php artisan vouchers:sync-pos-products` on the dev POS: both vouchers have products (created in steps 4/5, re-linked after the migration re-run). `PRODUCTS WHERE CODE LIKE 'GV%'`:
   ```
   Gift Voucher GVLH4AU7ASAT [bal €20.00] | GVLH4AU7ASAT | 482fba08-… | PRICESELL 0 | ISSERVICE 1
   Gift Voucher GVVUF2SUACUM [bal €20.00] | GVVUF2SUACUM | 482fba08-… | PRICESELL 0 | ISSERVICE 1
   PRODUCTS_CAT rows for them: 0
   ```
   No `[not active]` rows because the only two dev vouchers are active (naming of other statuses is covered by `VoucherPosProductServiceTest::test_names_follow_the_status`).
7. Simulated till sales on the dev POS (script: scratchpad `simsale.php`; goods line "Belvoir Strawberry & Raspberry Cordial 500ml" €10 + tax `002`, GV line, `paperin` payment, `DATENEW = now()`):
   - Ticket 999901 (`19c3580b-85bd-4731-942c-281fa8de5b38`), GVLH4AU7ASAT, tender 5.00 → `vouchers:sync-till`: `tickets 1 | applied 1`; redemption `applied`, ticket_total 12.30, voucher 20 → 15; POS NAME `Gift Voucher GVLH4AU7ASAT [bal €15.00]`. `POST /vouchers/lookup` (from the browser session) → `current_balance 15`, `history[0] = {label "Redeemed at till", user "Till #999901", source "till", ticket_number 999901}`.
   - Ticket 999902 (`929813ad-9d7a-41c3-8197-95cf6b5fad26`), GVVUF2SUACUM (balance 20), tender 25.00 → `tickets 2 | partial 1 | skipped 1` (999901 re-read in the overlap window and skipped); redemption `partial`, deducted 20.00, shortfall 5.00; voucher exhausted; POS NAME `[€0.00 used up]`; transaction note `Till #999902 · short €5.00`.
   - **Not done in the browser: viewing `/vouchers/exceptions` and marking the row reviewed.** The Chrome session is a shop PIN session, and cycle 27's `ConfinePinSession` sends every office page to `/confirm-password`; I do not type account passwords. Rendering, the review POST, and the `?all=1` toggle are covered by `VoucherTillExceptionsTest` (8 tests).
   - Cleanup: POS rows deleted for both ticket ids (PAYMENTS 2, TICKETLINES 4, TICKETS 2, RECEIPTS 2). Local dev data restored to baseline: redemptions 1–2 and voucher_transactions 7, 8, 9 (7/8 till, 9 the browser's manual €1) deleted, vouchers 1 and 2 reset to €20.00 active, `vouchers:sync-pos-products --all` → `2 renamed` back to `[bal €20.00]`. Left in place on the dev POS: the "Gift Voucher Redemption" category and the two GV products (the backfill's intended state).
8. Browser, `/shop/vouchers` as employee "katelyn" (PIN session): scanned GVLH4AU7ASAT → €15.00 Active, history "Redeemed at till · 27 Sept · Till #999901 −€5.00", "Issued · Jonathan +€20.00", numpad hidden, "Manual deduct" visible. Tapped Manual deduct → numpad, "Use full balance" and Deduct visible, toggle hidden. 1 → Deduct → toast "Deducted €1.00 · €14.00 left", new top row "Redeemed · katelyn −€1.00", numpad closed again. Console: only "Alpine.js started…", no errors. (The first scan did not register: the tool typed before the input had focus; the retry did.) **Office `/vouchers` not checked in the browser** for the same `/confirm-password` reason; its Blade renders in `VoucherTillExceptionsTest` (header link counts), the Alpine changes are unexercised in a browser.

## Files changed

Mine (cycle 28):
```
 M CLAUDE.md
 M app/Http/Controllers/VoucherController.php
 M app/Models/Voucher.php
 M app/Models/VoucherTransaction.php
 M docs/FEATURES_INDEX.md                         (was already dirty from cycle 27; voucher hunk only)
 M docs/features/voucher-management.md
 M docs/finance_manager/database-schema.md
 M resources/js/shop/vouchers.js
 M resources/views/layouts/admin.blade.php        (was already dirty; "Till exceptions" link only)
 M resources/views/shop/vouchers.blade.php
 M resources/views/vouchers/index.blade.php
 M resources/views/vouchers/print.blade.php
 M resources/views/vouchers/transactions.blade.php
 M routes/console.php
 M routes/web.php                                  (was already dirty; two voucher routes only)
 M tests/Feature/ScheduleTest.php
 M tests/Feature/Shop/ShopVouchersTest.php
?? app/Console/Commands/SyncVoucherPosProducts.php
?? app/Console/Commands/SyncVoucherTillRedemptions.php
?? app/Models/VoucherTillRedemption.php
?? app/Services/VoucherPosProductService.php
?? app/Services/VoucherTillSyncService.php
?? config/vouchers.php
?? database/migrations/2026_09_28_100000_create_voucher_till_redemptions_table.php
?? database/migrations/2026_09_28_100001_add_source_to_voucher_transactions_table.php
?? database/migrations/2026_09_28_100002_add_pos_product_id_to_vouchers_table.php
?? docs/planImp/implemented.md
?? resources/views/vouchers/exceptions.blade.php
?? tests/Concerns/CreatesVoucherPosTables.php
?? tests/Feature/VoucherPosProductServiceTest.php
?? tests/Feature/VoucherTillExceptionsTest.php
?? tests/Feature/VoucherTillSyncServiceTest.php
```
Pre-existing (cycle 27, untouched by me): `ProfileController.php`, `config/shop.php`, `docs/features/shop-mode.md`, `profile/edit.blade.php`, `ConfinePinSessionTest.php`, `UserPinManagementTest.php`, `ShopDeviceAdminController.php`, `docs/planImp/archive/2026-09-27-shop-mode-cycle-27/`, `docs/planImp/plan.md`, `update-pin-form.blade.php`, `shop-devices/`, `ProfilePinTest.php`, `ShopDeviceAdminTest.php`.
Not committed.

## Notes for Planner

1. **Office pages can't be browser-checked from a shop PIN session.** Every cycle that touches an office screen will hit `/confirm-password` in the owner's Chrome. Either the owner does the office checks (`/vouchers` scan + Manual deduct, `/vouchers/exceptions` + Mark reviewed), or a plan provides a local test login the Implementer can use.
2. **A leftover tender is not recorded when the last voucher line isn't applied.** Rule 5 sets `partial`/`shortfall` only when the *last* line is applied. On a two-voucher ticket where the first line is applied and the second is `inactive`/`no_tender`, any unused tender shows only as the second row's status, and no row carries a shortfall. Rare, and the row still lands on the exceptions page. A plan could put the leftover pool on the last row whatever its status.
3. **Watermark and batch limit.** If more than 50 voucher tickets fall inside the 15-minute overlap, every run re-reads the same first 50 (all skipped), so the sync never moves forward. Unrealistic for this shop's volume, but a later refinement could order by `DATENEW` and skip tickets that already have rows in SQL.
4. **The sync is only as current as the scheduler.** `schedule:run` must be in production cron (the plan's Risk). Nothing here checks that.
5. Out-of-scope items seen, untouched: `TillTransactionRepository::formatReceipt` first-payment-only; cash-rec `voucher_used`; GV products in product search / `SalesImportService`. All listed as follow-ups in `voucher-management.md`.
6. The sidebar "Till exceptions" link has no count badge; the count appears only in the `/vouchers` header. Possible later polish.
7. **Real-till test, 2026-09-28** (after this report was written). The owner rang two sales on a uniCenta till in VirtualBox against the dev POS: #430813 `applied` (€20.00 → €8.81) and #430814 `partial` (€8.81 → €0.00, shortfall €8.18). Both behaved as planned. Three findings (numeric-only till keypad, the till accepting any Voucher tender, a one-hour clock offset on the dev till) are in `docs/vouchers/findings/2026-09-28-till-testing.md`. `GVVUF2SUACUM`'s till product now has the dev-only numeric `CODE 2990000000019`.
8. **This cycle moved.** At the owner's request, `plan.md` and `implemented.md` moved from `docs/planImp/` to `docs/vouchers/` (their own plan/implement track, same protocol in `docs/vouchers/planimp.md`). No content of `plan.md` was changed. Helper scripts for till testing are in `docs/vouchers/scripts/`.
