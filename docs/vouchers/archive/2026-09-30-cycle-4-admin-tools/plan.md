# Admin tools for existing and test vouchers (vouchers cycle 4)

Status: ACCEPTED
Revision: 2
Planner: Fable 5.1
Date: 2026-09-29

## Goal

An admin can bring the vouchers that predate the sell-at-the-till process into it, from the voucher list page: tick the printed labels that were activated by hand but never sold and **make them for sale at their value**, so they sell through the till like new ones; deactivate or reactivate many vouchers in one go; and delete vouchers that were only ever used for testing so they disappear from the list, the activity log and the totals. A deleted voucher can be restored. One command takes the three old fixed voucher products off the till. These are changeover tools: admins only, behind one switch, and built so they can be removed later without touching the rest.

Revision 2 (2026-09-29, before implementation started) adds the "make for sale" action and the retire command, both at the owner's request.

## Context (verified 2026-09-29)

**Production today (read-only check, 15:32):**

| Status | Vouchers | Balance | With a value | With a till product |
|---|---|---|---|---|
| active | 37 | €860.00 | 0 | 2 |
| deactivated | 4 | €42.24 | 2 | 2 |
| inactive | 1 | €0.00 | 1 | 1 |

- The 37 active vouchers were activated by hand (39 manual `issue` rows, €10 × 21, €20 × 5, €50 × 11) before the new process. The owner says one of them has really been sold; the other 36 are printed labels carrying a balance nobody paid for.
- All 37 are untouched: balance equals the initial value and none has a `deduct` row (checked 2026-09-29).
- The three fixed products `6012` "Voucher 50 Euro", `6013` "Voucher 10 Euro", `6014` "Voucher 20 Euro" (category `033`) each have a till button (`PRODUCTS_CAT` row, `CATORDER` NULL). One was sold on 2026-09-29 at 14:14, two lines since 1 September.
- The five others are test vouchers: two from June, three from 29 September (two of those were sold and redeemed at the live till for €1 and €2, so their till products have ticket lines).
- Nothing is soft-deleted yet.

**What exists:**
- `app/Models/Voucher.php` uses `SoftDeletes`; nothing in the app deletes a voucher. `vouchers.code` is unique **including** soft-deleted rows, and `generateUniqueCode()` already checks `withTrashed()`.
- Admin status change, one voucher at a time: `VoucherController::deactivate()` / `reactivate()` → private `changeStatus()` (l.309-355 before cycle 3's shifts; find it by name): `lockForUpdate`, only `active → deactivated` and `deactivated → active`, one `voucher_transactions` row (`deactivate` / `activate`, amount 0, optional note, user), then `syncPosProduct()`. Routes `vouchers.deactivate` / `vouchers.reactivate` sit in `Route::middleware('role:admin')` inside the `permission:vouchers.manage` group (`routes/web.php`, just after `vouchers/{voucher}/transactions`). `role` is `App\Http\Middleware\RoleMiddleware`.
- `resources/views/vouchers/list.blade.php`: `@php($isAdmin = auth()->user()->hasRole('admin'))`; filter form (`q`, `status`); table Code / Status / Initial / Balance / Created by / Created / actions; an admin "Edit" modal per voucher driven by the inline `voucherAdmin()` Alpine component (posts JSON to the two routes, then reloads); a no-op `voucherAdmin()` for non-admins. `VoucherController::list()` paginates 25.
- `app/Services/VoucherPosProductService.php`: `productName()` picks the text from `config('vouchers.pos_balance_text')[status]` (`for_sale` when `isForSale()`); `productPrice()`; `sync()` creates the product when missing and corrects name and price. So **deactivating a voucher that has no till product creates one**, named `[deactivated]`, price 0.
- `app/Services/VoucherTillSyncService.php`: `resolveVoucher()` uses `Voucher::where(...)` (soft-deleted vouchers are invisible to it); `applyLine()` and `applySale()` lock with `Voucher::whereKey(...)->lockForUpdate()->first()`.
- `app/Http/Controllers/VoucherController.php`: `lookup()` returns `['found' => false]` for an unknown code, and the screens then offer a manager the activation form; `activate()` creates an unknown code on the fly.
- `app/Services/VoucherActivityService.php`: `events()` loads vouchers `withTrashed()` and shows their rows without a link; `totals()` sums transactions whatever their voucher's state. `tests/Feature/VoucherActivityTest.php::test_a_deleted_vouchers_rows_still_show_without_a_link` pins that behaviour.
- `voucher_transactions.type` is `string(20)`; types today `issue|deduct|deactivate|activate`. `voucher_till_redemptions.voucher_id` is `nullOnDelete` (hard delete only).
- The POS `TICKETLINES.PRODUCT` has a foreign key to `PRODUCTS`, so a till product that was ever sold or scanned on a ticket cannot be deleted.
- Test helpers: `userWith($role, $permissions, $name)` in `tests/Feature/VoucherTillExceptionsTest.php`; `tests/Concerns/CreatesVoucherPosTables.php`.
- Suite baseline: 15 failed, 862 passed.

- `app/Models/ProductsCat.php` (till buttons): `isProductVisible()`, `addProduct($productId, ?int $order)`, `removeProduct($productId)`. The office product page already toggles till visibility one product at a time (`products.toggle-till-visibility`).

**Owner's request (2026-09-29):** tools to deactivate the existing barcodes, to activate the one that was sold, and to delete barcodes used for testing; admin only; may be dropped once everyone knows the system. **Added the same day:** turn the unsold hand-activated labels into for-sale vouchers at their value, and retire the three old fixed products and take them off the till.

**Planner decisions (record, do not re-open):**
1. **Delete is a soft delete and can be undone.** Money records are never destroyed. "Deleted" means hidden everywhere and unusable; an admin can restore it.
2. **An active voucher cannot be deleted.** It must be deactivated first. Two deliberate steps stand between an admin and wiping a customer's balance.
3. **Delete needs a reason**; deactivate and reactivate take an optional note, as the single-voucher actions do today.
4. **A deleted voucher's till product is kept**, renamed `[deleted]` at price 0. It cannot always be removed (foreign key), and a label that still scans should say what it is.
5. **A deleted voucher's code stays reserved.** It cannot be activated or generated again.
6. **Deleted vouchers leave the activity log and the totals.** That is the point of deleting test data. Their till exceptions are marked reviewed at the same moment.
7. "Activate the one sold barcode" is met by leaving it unticked (it stays active), or by reactivating it after a bulk deactivation. No new kind of activation is added.
8. **Make for sale is only for a voucher nobody has used**: status `active` or `deactivated`, a balance above zero equal to its initial value, and no `deduct` row ever. Anything else is skipped with its reason. The value it is sold for is the balance it carried.
9. **Make for sale is undone by hand**: a manager activates the voucher on `/vouchers` (the value is pre-filled). No "undo" action is added.
10. **Retiring a fixed product** removes its till button, renames it with ` (retired)` and changes its barcode to `RET` + the old code, so keying `6012` on the till finds nothing. The product row stays, because past sales refer to it. The command can put all three back.

## Constraints

- Admin only: every new route is inside `role:admin` within the `vouchers.manage` group. Managers keep exactly what they have.
- One switch: `config('vouchers.admin_tools')` (env `VOUCHER_ADMIN_TOOLS`, default `true`). When false the routes answer 404 and the list page shows none of the new controls.
- Removable: the new behaviour lives in one new service, one new controller and one Blade partial. Existing methods (`changeStatus()`, the Edit modal) are not refactored.
- Voucher money rules do not change. No balance is altered by any of these tools; status and deletion only. Every action writes one `voucher_transactions` row per voucher with the admin as `user_id`.
- The till sync, the redemption and sale rules, and finance code keep their behaviour, except the two named changes for deleted vouchers (step 4).
- POS writes stay limited to `PRODUCTS` rows of voucher products (name, price, insert when missing), plus, **in the retire command only**, the three fixed products named in `config('vouchers.legacy_product_codes')`: their `PRODUCTS_CAT` row, `NAME`, `CODE` and `REFERENCE`. Never the ticket tables. No voucher product ever gets a `PRODUCTS_CAT` row.
- POS downtime must not fail an action: the local change commits, the till product is corrected by the next sync of that voucher or by `vouchers:sync-pos-products --all`.
- From the Shop tree only `resources/views/shop/vouchers.blade.php` and `resources/js/shop/vouchers.js` may change; `shop-*` classes only; no new `route()` call there.
- Do not commit, push or deploy. **Nothing runs against production**: the owner uses the tools there after deploying.

## Out of scope

- Hard delete, purge of deleted vouchers, editing a balance, changing a voucher's value by hand.
- An "undo" for make for sale (decision 9). Deleting the fixed products' rows.
- A bulk action for managers or employees; any Shop mode screen for these tools.
- Running the parked production backfill (`findings/2026-09-29-production-backfill-not-run.md`). Note only: make for sale and bulk deactivation both create a missing till product as a side effect, which settles that finding for the vouchers they touch.

## Steps

### 0. Baseline
What: record `git rev-parse --short HEAD`, `git status --short` and the `php artisan test` summary in `implemented.md`. Expected about `15 failed, 862 passed`.
Check: summary line and failing class names pasted.

### 1. Config and transaction types
Files: `config/vouchers.php`, `app/Models/VoucherTransaction.php`
What:
- `config/vouchers.php`: `'admin_tools' => (bool) env('VOUCHER_ADMIN_TOOLS', true),` with a comment saying these are changeover tools; in `pos_balance_text` add `'deleted' => 'deleted'`.
- `config/vouchers.php` also gets `'legacy_product_codes' => ['6012', '6013', '6014'],` with a comment naming the three products.
- `VoucherTransaction`: `TYPE_DELETE = 'delete'`, `TYPE_RESTORE = 'restore'` and `TYPE_FOR_SALE = 'for_sale'` (a hand-activated voucher returned to unsold).
Check: `php artisan tinker --execute="var_dump(config('vouchers.admin_tools'));"` → `bool(true)`.

### 2. Till product of a deleted voucher
Files: `app/Services/VoucherPosProductService.php`
What: in `productName()`, when `$voucher->trashed()` the text is `config('vouchers.pos_balance_text.deleted')`, checked before `isForSale()`: `Gift Voucher GV7KQFM2RA9T [deleted]`. In `productPrice()`, a trashed voucher is `0.0`. Nothing else changes.
Check: step 7's tests.

### 3. Admin service
Files: `app/Services/VoucherAdminService.php` (new)
What: constructor takes `VoucherPosProductService`. Five public methods, each taking `array $ids`, `?string $note`, `User $admin` and returning `['done' => int, 'skipped' => array<int, array{code: string, reason: string}>]`. Each voucher is handled in its own `DB::transaction` with `Voucher::withTrashed()->whereKey($id)->lockForUpdate()->first()`; after the transaction commits, `posProducts->sync()` for that voucher (it never throws). One voucher failing or being skipped never stops the rest.
- `deactivate()`: status must be `active` → `deactivated`; transaction `deactivate`, amount 0, `balance_after` = current balance, note, `user_id`. Otherwise skipped with reason `not active`. A trashed voucher is skipped with `deleted`.
- `reactivate()`: `deactivated` → `active`; transaction `activate`. Otherwise skipped `not deactivated` / `deleted`.
- `delete()`: allowed when the status is `inactive`, `deactivated` or `exhausted` and the voucher is not already trashed. An `active` voucher is skipped with `active: deactivate it first`. Writes the transaction `delete` (amount 0, `balance_after` = current balance, the reason, `user_id`), then `$voucher->delete()`. Then marks the voucher's till rows reviewed: `VoucherTillRedemption::where('voucher_id', $id)->whereNull('reviewed_at')->update(['reviewed_at' => now(), 'reviewed_by' => $admin->id])`.
- `restore()`: only a trashed voucher; `$voucher->restore()`, transaction `restore`, status and balance as they were. Otherwise skipped `not deleted`.
- `makeForSale()`: under the lock, the voucher must be not trashed, status `active` or `deactivated`, `current_balance > 0`, `current_balance` equal to `initial_value` (2 dp), and have no transaction of type `deduct`. Skipped reasons: `deleted`, `not active or deactivated`, `no balance`, `has been spent from`, `balance differs from its initial value`. Effect: `face_value = current_balance`, `current_balance = 0`, `initial_value = NULL`, `status = inactive`. Transaction `for_sale`, `amount` = the value, `balance_after` 0, note, `user_id`. After commit the till product becomes `[for sale €X]` at price X (created when missing).
- The same rules and wording as `changeStatus()` for the two status changes; do not call or change `changeStatus()`.
Check: step 7's `VoucherAdminServiceTest`.

### 4. Deleted vouchers elsewhere in the app
Files: `app/Services/VoucherTillSyncService.php`, `app/Http/Controllers/VoucherController.php`, `app/Services/VoucherActivityService.php`
What:
- **Till sync.** `resolveVoucher()` looks up `withTrashed()` (both by `pos_product_id` and by `code`). `applyLine()` and `applySale()` lock with `Voucher::withTrashed()->whereKey(...)->lockForUpdate()->first()`. Immediately after the lock, a trashed voucher is handled before any other rule (the refund rule still comes first): in `applyLine()` → status `inactive`, note `Voucher was deleted.`, nothing deducted; in `applySale()` → status `sale_flagged`, note `Charged €X but the voucher was deleted. Nothing was activated.`
- **Lookup.** `lookup()` searches `withTrashed()`; for a trashed voucher it returns `['found' => false, 'deleted' => true]` and nothing else about it.
- **Activate.** `activate()`: before creating an unknown code, if a trashed voucher has that code return 422 `['success' => false, 'message' => 'This voucher was deleted. It cannot be activated.']`.
- **Labels for the new types.** `VoucherController::history()` and `VoucherActivityService::fromTransaction()`: `for_sale` → label `Made for sale`, amount **negative** (the balance went down by that value), kind `for_sale`; `delete` → `Deleted`; `restore` → `Restored` (both amount 0). `resources/views/vouchers/activity.blade.php` `pill()` needs no change (they fall to the grey `status` pill). `resources/views/vouchers/transactions.blade.php`: badge text for the three types, and the amount cell shows `−€X` for `for_sale`.
- **Activity.** `events()`: leave out transactions and till rows whose voucher is trashed (`whereHas('voucher')` for transactions; for till rows, `where(fn ($q) => $q->whereNull('voucher_id')->orWhereHas('voucher'))` so `unknown` rows with no voucher still show). `totals()`: `redeemed_today*` and `issued_today` count only transactions whose voucher is not trashed. The `withTrashed()` eager load and the `voucher_url` null branch may stay; they are now unreachable for trashed vouchers.
Check: `php artisan test --filter='VoucherTillSyncServiceTest|VoucherActivityTest'` → all pass after the one named change in step 7.

### 5. Controller and routes
Files: `app/Http/Controllers/VoucherAdminToolsController.php` (new), `routes/web.php`, `app/Http/Controllers/VoucherController.php`
What:
- `VoucherAdminToolsController`, constructor takes `VoucherAdminService`. Its constructor (or a first line in each action) aborts with 404 when `config('vouchers.admin_tools')` is false. Five actions, each validating `ids` (`required|array|min:1|max:200`, `ids.* integer`) and `note`:
  - `deactivate` and `reactivate`: `note` `nullable|string|max:500`.
  - `delete`: `note` `required|string|min:3|max:500`.
  - `restore`: `note` `nullable|string|max:500`.
  - `forSale`: `note` `nullable|string|max:500`.
  Each calls the service and redirects back with a `status` flash such as `Deactivated 36 vouchers. Skipped 1: GVXXXXXXXXXX (not active).` (list at most 10 skipped codes, then "and N more").
- Routes inside the existing `role:admin` group, **above** `vouchers/{voucher}/deactivate`:
```php
Route::post('vouchers/bulk/deactivate', [VoucherAdminToolsController::class, 'deactivate'])->name('vouchers.bulk.deactivate');
Route::post('vouchers/bulk/reactivate', [VoucherAdminToolsController::class, 'reactivate'])->name('vouchers.bulk.reactivate');
Route::post('vouchers/bulk/delete', [VoucherAdminToolsController::class, 'delete'])->name('vouchers.bulk.delete');
Route::post('vouchers/bulk/restore', [VoucherAdminToolsController::class, 'restore'])->name('vouchers.bulk.restore');
Route::post('vouchers/bulk/for-sale', [VoucherAdminToolsController::class, 'forSale'])->name('vouchers.bulk.for-sale');
```
- `VoucherController::list()`: `per_page` from the query, one of `25, 50, 100, 200` (default 25); a `status` value of `deleted` lists `onlyTrashed()` and is honoured **only** for an admin with the switch on (anyone else gets the normal list). Pass `adminTools` = admin and switch on.
Check: `php artisan route:list --name=vouchers.bulk` lists five routes; with `VOUCHER_ADMIN_TOOLS=false` in a test they answer 404.

### 6. List page
Files: `resources/views/vouchers/list.blade.php`, `resources/views/vouchers/partials/admin-tools.blade.php` (new)
What: everything new is wrapped in `@if ($adminTools)`; with the switch off or for a manager the page renders as it does today.
- Filter form: a "Per page" select (25/50/100/200) and, for admins, a "Deleted" option in the status select.
- A `status` flash banner at the top (green), which the page does not show today.
- Table: a first column of checkboxes (`name="ids[]"`, `form="voucher-bulk"`, value the voucher id) and a "select all on this page" checkbox in the header.
- The partial holds one form `id="voucher-bulk"` (`method="POST"`, `@csrf`) with a note textarea and the action buttons, each a submit button with its own `formaction`: "Make for sale", "Deactivate selected", "Reactivate selected", "Delete selected"; on the Deleted view only "Restore selected". The bar shows "N selected" and is disabled at zero.
- Each row's checkbox carries `data-status` and `data-balance`, so the bar can show the count and the total balance of the selection without a request.
- Confirmation: Deactivate and Reactivate ask once in an in-page dialog ("Deactivate 36 vouchers?"). Make for sale asks: "36 vouchers worth €810.00 go back to unsold. Their balances become their sale value, and each activates again when it is sold at the till. Leave out any voucher a customer already holds." Delete opens a dialog that names the count, says deleted vouchers leave the list, the activity log and the totals and can be restored from the Deleted view, and keeps its confirm button disabled until the reason has at least 3 characters. Use an Alpine dialog like the existing Edit modal, not the browser's `confirm()`.
- The Deleted view shows the same columns, a "Deleted" pill, the delete date, and no Edit / Log / Print links.
- Alpine: a second, separate component for the bulk bar (`voucherBulk()`), defined inside the partial, so removing the partial removes all of it. Respect the Alpine rules in `planimp.md` (`x-on:` for anything that is also a Blade directive; `?.` behind `x-show`).
Check: step 7's rendering tests; `node --check` of the page's inline scripts, extracted as in cycles 2 and 3.

### 7. Lookup screens and tests
Files: `resources/views/vouchers/index.blade.php`, `resources/views/shop/vouchers.blade.php`, `resources/js/shop/vouchers.js`, `tests/Feature/VoucherAdminServiceTest.php` (new), `tests/Feature/VoucherAdminToolsTest.php` (new), `tests/Feature/VoucherTillSyncServiceTest.php`, `tests/Feature/VoucherActivityTest.php`, `tests/Feature/VoucherPosProductServiceTest.php`, `tests/Feature/Shop/ShopVouchersTest.php`
What:
- Screens: when the lookup answers `deleted: true`, both screens show "This voucher was deleted. It cannot be used." and offer no activation, to managers as well. Office: a new `mode === 'deleted'` block. Shop: a `deleted` mode with a flat card; `needsManager` and `activating` must be false in that mode. `npm run build`.
- `VoucherAdminServiceTest` (POS tables via `CreatesVoucherPosTables`): bulk deactivate changes only active vouchers and reports the rest as skipped with reasons; each writes one transaction with the admin and the note; a voucher without a till product gets one named `[deactivated]`; reactivate; delete refuses an active voucher, deletes an inactive, a deactivated and an exhausted one, writes the `delete` row, renames the product `[deleted]` at price 0 (also for a for-sale voucher that was priced), marks its unreviewed till rows reviewed; restore brings status, balance and product name back (a for-sale voucher is priced again); deleting twice skips; a POS outage (drop `PRODUCTS`) still completes the local change; one bad id does not stop the others.
- `VoucherAdminServiceTest` also covers make for sale: an untouched active €10 voucher becomes inactive, `face_value 10.00`, balance 0, `initial_value` NULL, one `for_sale` row with amount 10 and the admin, till product `[for sale €10.00]` at price 10 (created when it had none); a deactivated untouched voucher converts too; a voucher with a deduct, a zero balance, an exhausted status, an already for-sale voucher and a deleted voucher are each skipped with the right reason; **the whole path**: convert, then a till sale at that price activates it through `VoucherTillSyncService` with the same value, and a later redemption works.
- `VoucherAdminToolsTest`: manager → 403 on all five routes, employee → 403, guest → login; admin succeeds and the flash names done and skipped; `ids` empty → validation error; delete without a reason → validation error; more than 200 ids → validation error; switch off → 404 and the list page contains none of `voucher-bulk`, "Make for sale", "Deactivate selected", "Deleted"; list page for an admin has the checkboxes and the bar, for a manager it does not; `per_page=100` is honoured and `per_page=7` falls back to 25; `status=deleted` lists trashed vouchers for an admin and is ignored for a manager.
- `VoucherTillSyncServiceTest` (add): a deleted voucher's label scanned with a Voucher tender → `inactive` "Voucher was deleted.", balance untouched; a deleted for-sale voucher sold at the till → `sale_flagged`, not activated; a restored voucher redeems normally.
- `VoucherActivityTest` (add): a `for_sale` row shows as `Made for sale` with a negative amount and lowers "Outstanding balance".
- `VoucherActivityTest`: **changed assertion, named here:** `test_a_deleted_vouchers_rows_still_show_without_a_link` becomes `test_a_deleted_vouchers_rows_are_hidden` and asserts zero events. Add: totals exclude a deleted voucher's issue and deduct of today; an `unknown` till row with no voucher still shows; restoring the voucher brings its rows back.
- `VoucherPosProductServiceTest` (add): name and price of a trashed voucher.
- `ShopVouchersTest` (add): lookup of a deleted code returns `found false, deleted true`; activating a deleted code as a manager → 422 with the message; the shop page contains the deleted wording.
Check: `php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession|ShopViewContract'` → all pass.

### 8. Retire the three fixed voucher products
Files: `app/Console/Commands/RetireFixedVoucherProducts.php` (new), `tests/Feature/RetireFixedVoucherProductsTest.php` (new)
What: `vouchers:retire-fixed-products {--dry-run} {--restore}`. It works on the products whose `CODE` is in `config('vouchers.legacy_product_codes')`, or, for `--restore`, whose `CODE` is `RET` + one of those codes. It aborts with a clear message when `config('vouchers.admin_tools')` is false.
- Retire, per product, inside one POS transaction: `ProductsCat::removeProduct($id)` (no error when there is no button); `NAME` gains the suffix ` (retired)` unless it already ends with it; `CODE` and `REFERENCE` become `RET` + the old code (`RET6012`). A product that is already retired is reported `already retired` and left alone. A code that matches no product is reported `not found`.
- `--restore` reverses all three for each retired product: `CODE`/`REFERENCE` back to the bare code, the suffix removed, `ProductsCat::addProduct($id)` when it has no button.
- `--dry-run` prints what would change and writes nothing.
- Output: a table `code | name | till button | action`, then one line reminding that tills show their buttons from start-up, so **restart uniCenta on each till** to see the change.
- Uses the `Product` and `ProductsCat` models. It never touches a voucher product, a ticket table or any other product.
- Test (POS tables from `CreatesVoucherPosTables`, which already has `PRODUCTS` and `PRODUCTS_CAT`; add the three products and their buttons in the test): dry run changes nothing; retire removes the three buttons, renames and re-codes; a second run reports `already retired`; `--restore` puts name, code and button back; an unrelated product and its button are untouched; with the switch off the command fails and changes nothing.
Check: `php artisan test --filter=RetireFixedVoucherProductsTest` passes. On dev: `php artisan vouchers:retire-fixed-products --dry-run` lists the three products from the dev POS copy; do **not** run it for real on dev unless you restore afterwards, and say which you did.

### 9. Docs
Files: `docs/features/voucher-management.md`, `docs/FEATURES_INDEX.md`
What: a new `## Admin changeover tools` section: what each action does and to which statuses, that delete is reversible and what it hides, the two-step rule for active vouchers, the switch `VOUCHER_ADMIN_TOOLS`, and **how to remove the tools later** (delete the controller, the service, the partial, the five routes, the retire command, the config keys; what to keep: `withTrashed()` handling in the sync and lookup if any voucher has been deleted). Describe make for sale (which vouchers qualify, what it changes, how to undo it by manual activation) and the retire command (what it changes, `--dry-run`, `--restore`, restart the tills). Add a "Changeover on production" checklist for the owner:
  1. Find the one voucher that was sold and keep its code to hand.
  2. `/vouchers/list`, status Active, 200 per page: tick all, **untick the sold one**, Make for sale. The banner should report 36 done.
  3. Filter to the test vouchers (Deactivated, then Inactive): Delete selected, with a reason. Destroy the physical test labels.
  4. On the server, as the web user: `php artisan vouchers:retire-fixed-products --dry-run`, then without `--dry-run`. Restart uniCenta on each till.
  5. `/vouchers/activity`: the health banner should be green, and "Outstanding balance" should be the sold voucher's balance only. Update `## Statuses`, `## Routes`, `## Key files`. One line in `FEATURES_INDEX.md`.
Check: `grep -n "VOUCHER_ADMIN_TOOLS" docs/features/voucher-management.md` finds it.

## Verification (report every item with what you saw)

1. `./vendor/bin/pint --test` on the PHP files you changed → PASS.
2. `php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession|ShopViewContract'` → all pass.
3. `php artisan test` → no new failures against step 0. Paste the summary and failing class names.
4. `php artisan route:list --name=vouchers.bulk` → five routes, each showing the `role:admin` middleware (`-v`).
5. `node --check` on `resources/js/shop/vouchers.js` and the extracted inline scripts of `vouchers/list.blade.php` and `vouchers/index.blade.php` → clean. `npm run build` → built.
6. Dev, through tinker and the service (record ids; restore the dev data afterwards): create three vouchers (one active €10, one for sale €5, one deactivated); bulk deactivate all three → 1 done, 2 skipped with reasons; delete all three → the first now deletes (it is deactivated), and its POS product reads `[deleted]` at price 0; `VoucherActivityService::payload(7, null)` shows none of them; restore one → it is back with its status and product name. Then make for sale: an untouched active €10 voucher → inactive, `[for sale €10.00]` at price 10; `SALE=1 … simsale.php` on it and `vouchers:sync-till` → `activated 1`, balance €10.00.
6b. `php artisan vouchers:retire-fixed-products --dry-run` on dev → three rows, nothing written.
7. **Browser check: owner, signed in as an admin, on dev.** `/vouchers/list?per_page=200&status=active`: tick all, untick one, Deactivate selected with a note; the banner reports the count; the unticked voucher is still active. Filter Deactivated, tick a test voucher, Delete selected: the confirm button stays disabled until a reason is typed; afterwards the voucher is gone from the list and from `/vouchers/activity`. Status "Deleted": the voucher is there; Restore selected brings it back. Scan the deleted code on `/vouchers`: "This voucher was deleted." Tick two untouched active vouchers, Make for sale: the dialog names the count and the total; afterwards both read "€X for sale" and scanning one on `/vouchers` shows "Not sold yet". Console free of errors.

## Risks

- **The changeover on production creates about 35 till products**, one for each hand-activated voucher that has none: `[for sale €X]` at its price after make for sale, or `[deactivated]` at price 0 after a deactivation. That is wanted, and it settles the parked backfill finding for those vouchers.
- **The one sold voucher.** The tools cannot know which of the 37 it is. The owner must identify it and leave it unticked. If it is made for sale by mistake, the customer's label would ring up as an item to pay for; a manager fixes that by activating it by hand on `/vouchers` with its value.
- **Labels of different values look the same** (€10, €20 and €50 among the 36). After make for sale the till shows each one's price when scanned; that is the only check, as for new vouchers.
- **Retiring changes three real products.** Their names and barcodes change and their buttons go. Past sales keep pointing at the same product rows, so reports are unaffected except that the name now ends ` (retired)`. `--restore` undoes it. Tills keep showing the old buttons until uniCenta is restarted.
- **Deleting hides history.** A deleted voucher's issue and redemption rows leave the activity log and today's totals. Restore brings them back. Finance reports are not affected: they read the till, not this log.
- **A deleted label still scans at the till** as a €0.00 line reading `[deleted]`. A voucher tender taken against it deducts nothing and appears on the exceptions page. Destroy the physical labels.
- **Selection is per page.** "Select all" ticks the vouchers on the page shown; with 200 per page that covers production's 42 vouchers in one go.
- **Removal later.** Once any voucher has been deleted, the `withTrashed()` handling in the sync and the lookup must stay even if the tools are removed; the docs step records this.

## Review

Reviewed 2026-09-30 against `implemented.md` (read to the end, five deviations, six notes and the heredoc incident) and the committed diff `65477718..9f796b28`. The owner committed the cycle before review. Production was not touched.

**Criteria**
- Step 0 baseline — pass (15 failed, 862 passed).
- Step 1 config and types — pass.
- Step 2 deleted voucher's till product — pass: `[deleted]`, price 0, checked before `isForSale()`.
- Step 3 admin service — pass. Five actions, each voucher in its own transaction under `withTrashed()->lockForUpdate()`, skip reasons as specified, one audit row per voucher with the admin, till product synced after each commit. Make for sale checks status, balance, no deduct ever, balance equal to the initial value, then sets `face_value`, zeroes the balance and clears `initial_value`.
- Step 4 deleted vouchers elsewhere — pass. Sync resolves and locks `withTrashed()` and refuses a deleted voucher right after the lock (`inactive` / `sale_flagged` with the agreed notes); lookup answers `found false, deleted true`; activate refuses a deleted code; the activity log and totals leave deleted vouchers out while `unknown` rows still show.
- Step 5 controller and routes — pass. Five routes in `role:admin` (verified with `route:list -v`), 404 when the switch is off, validation as specified, flash with done and skipped.
- Step 6 list page — pass with Deviation 1. Checkboxes joined to the form by `form=`, per-page select, Deleted view, in-page dialogs, delete Confirm disabled until a reason of 3 characters.
- Step 7 lookup screens and tests — pass. Both screens have a deleted state; 39 tests added; the one named assertion changed.
- Step 8 retire command — pass. Per product in one POS transaction: button removed, name suffixed, code and reference re-coded; `--restore` reverses; `--dry-run` writes nothing; refuses when the switch is off. Dry run on the dev copy shows the three products; nothing was written (re-checked by the Planner).
- Step 9 docs — pass. The owner's five-step production checklist is in the feature doc.

**Verification (rerun by the Planner)**
1. `pint --test` on the 17 changed PHP files → PASS.
2. `php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession|ShopViewContract'` → 181 passed (1111 assertions).
3. `php artisan test` → 15 failed, 921 passed (4080 assertions); the 15 are the baseline classes. The extra passing tests over the implementer's 901 come from unrelated uncommitted work in the tree (help pages), not from this cycle.
4. `route:list --name=vouchers.bulk -v` → five routes, each with `RoleMiddleware:admin`.
5. `node --check` on the shop module and the four extracted inline scripts (list ×2, partial, index) → clean.
6. Dev run: the implementer's evidence; the Planner re-checked afterwards that the three fixed products still have their codes, names and buttons, no `GVDEV%` products remain and no simulated tickets remain. The heredoc incident left no trace.
6b. Dry run of the retire command → three rows, nothing written.
7. Browser check: owner (below).

**Deviations** — all five accepted.
1. Buttons open the dialog and JS submits the form: the plan's `formaction` submit buttons would have bypassed the confirmation the plan also asked for. The Planner's inconsistency.
2. `already deleted` as the reason for deleting twice: fine.
3. `#<id> (not found)` and `#<id> (error)`: fine, and the error is logged.
4. `--restore` reports `not retired`: fine.
5. "Made for sale: N vouchers.": fine.

**Notes for Planner**
1. A deleted voucher's log page is a 404 — deferred; nothing links to it. If admins ever need it, add `withTrashed()` to that route's binding.
2. Select all is per page — accepted as planned; 200 per page covers production.
3. The single-voucher Edit modal stays beside the bar — accepted; it is the pre-existing tool and is removed with the rest later if wanted.
4. Make for sale keeps the old `issue` row — accepted; the history reads Issued → Made for sale → Sold at till, which is the truth.
5. A restored exhausted voucher reads `[€0.00 used up]` — accepted; the name follows the status.
6. Dev data noted — accepted.
7. **The heredoc incident**: the implementer's report-writing command executed text as shell. Everything it could have run was read-only or a dry run, the implementer re-checked the tree and the till data, and the Planner re-checked the same. Lesson for `planimp.md`: write reports with the file tools, never with an unquoted shell heredoc.

**Owner actions**
- Browser check as an admin on dev (plan Verification 7), then deploy.
- The production changeover, in this order (also in `docs/features/voucher-management.md` under "Changeover on production"): identify the one sold voucher; Active, 200 per page, tick all, untick it, Make for sale (expect 36); delete the five test vouchers with a reason and destroy their labels; on the server as the web user `php artisan vouchers:retire-fixed-products --dry-run` then for real; restart uniCenta on each till; check `/vouchers/activity`.

**Archive**: done by the Planner on 2026-09-30, `docs/vouchers/archive/2026-09-30-cycle-4-admin-tools/`.
