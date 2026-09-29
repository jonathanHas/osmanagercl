# Selling a voucher at the till activates it (vouchers cycle 3)

Status: READY
Revision: 1
Planner: Fable 5.1
Date: 2026-09-29

## Goal

A voucher is created with a value. Its label is scanned at the till like any product, the till charges that value, and the sale itself activates the voucher with that amount: no manager step, no separate "Voucher 20 Euro" product. From then on the same label scans as the €0.00 redemption line that cycle 1 built. Anything unusual about the sale (quantity above 1, the Free tender, a voucher sold twice, a refund) activates nothing and lands on the manager exceptions page.

## Context (verified 2026-09-29)

**How vouchers are sold today.** The cashier rings a fixed till product ("Voucher 10 Euro" `6013`, "Voucher 20 Euro" `6014`, "Voucher 50 Euro" `6012`; category `033`, `TAXCAT 000`, till buttons in `PRODUCTS_CAT`) and a manager then activates a printed GV label by hand on `/vouchers`. Last 12 months in the POS copy: 196 sale lines, about €7,100; paid by card (102 tickets) or cash (51); **30 of the 196 lines had quantity above 1** (up to 9); no till price edits; no refund of a voucher ever. A `free` payment is never mixed with another payment on a receipt.

**What cycles 1 and 2 built (all committed or accepted; archives under `docs/vouchers/archive/`):**
- `app/Models/Voucher.php`: fillable `code, pos_product_id, initial_value, current_balance, status, created_by`. `initial_value` is set only at activation and is shown as "Initial" in `vouchers/list.blade.php` l.39/58 and `vouchers/transactions.blade.php` l.19-20. There is no field for an unsold voucher's value.
- `app/Services/VoucherPosProductService.php`: `productName()` l.60 builds the NAME from `config('vouchers.pos_name_format')` and `pos_balance_text[status]`; `sync()` l.77 creates the product with `PRICESELL 0` (l.102) and afterwards only renames (l.110-112). It never changes a price. `lastAction()` reports `created|linked|renamed|unchanged|failed`.
- `app/Services/VoucherTillSyncService.php`: `run()` l.91 finds tickets with a product in the voucher category, `TICKETTYPE IN (0,1)`; `syncTicket()` l.151 reads the voucher lines with `select('tl.LINE','tl.PRODUCT','p.CODE')` l.161 and `->unique('PRODUCT')` l.163 (so **price and units are not read today**), sums `paperin` l.172, and calls `applyLine()` l.271 per voucher in LINE order with a shared `$pool`; `isLast` (l.193, 214) decides `partial`. `applyLine()` order: refund l.279 → unknown l.285 → not active l.298 → no tender l.307 → deduct l.316. After the loop, vouchers with `deducted > 0` get `posProducts->sync()`. `COUNT_KEYS` ends `…,'refund','skipped','errors'`.
- `app/Models/VoucherTillRedemption.php`: statuses `applied|partial|no_tender|inactive|unknown|refund`; `EXCEPTION_STATUSES` is all but `applied`. Unique `(pos_ticket_id, pos_product_id)`. Despite its name the table is "one row per voucher per till ticket"; this cycle adds sale rows to it and does not rename it.
- `app/Http/Controllers/VoucherController.php`: `lookup()` l.71, `history()` l.123 (issue → "Issued"; till rows get `user` "Till #N"), `activate()` l.159 (route `vouchers.activate`, under `permission:vouchers.manage`, creates unknown codes on the fly, then `syncPosProduct()`), `generate()` l.365 (validates `count` only; creates vouchers, then `posProducts->syncMany()`), `print()` l.399.
- `app/Services/VoucherActivityService.php`: `fromTransaction()` maps `issue` → kind `issue`, label "Issued"; `fromRedemption()` labels the four no-transaction statuses.
- Views: `resources/views/vouchers/generate.blade.php` (one `count` input), `print.blade.php` (label previews with the code under each), `index.blade.php` (ACTIVATE block l.81-118: managers get a starting-balance input, employees get "Voucher not active … Please ask a manager."; script sets `mode = 'activate'` and `startingBalance = ''` at l.378-381), `exceptions.blade.php`, `activity.blade.php`. Shop: `resources/views/shop/vouchers.blade.php` l.30-47 and `resources/js/shop/vouchers.js` (`needsManager` l.85, `activating` l.76, `typed` reset in `onScan()` l.131).
- **Manual activation is already managers and admins only**: `vouchers.activate` is in the `vouchers.manage` group and `vouchers.manage` is granted to manager and up (migration `2026_09_26_120000_add_customer_requests_and_voucher_permissions.php`). `ShopVouchersTest` already asserts an employee gets 403. No permission work is needed; the owner's decision 6 is met by keeping this.
- Tests: `tests/Concerns/CreatesVoucherPosTables.php` (`posSale($ticketNo, $lines, $payments, $at, $type)` takes `price`, `units`, `tax` per line; `TAXES` seeded `000` = 0 and `001` = 0.23). Current counts: `VoucherTillSyncServiceTest` 23, `VoucherPosProductServiceTest` 11, `VoucherTillExceptionsTest` 8, `VoucherActivityTest` 18, `ShopVouchersTest` 11.
- Dev helpers: `docs/vouchers/scripts/simsale.php` inserts a goods line, the voucher's line at `PRICE 0`, and a `paperin` payment; `numeric_barcode.php` gives a voucher product a numeric barcode for the till's numeric keypad; `reset_voucher.php`.

**Till and finance facts:**
- uniCenta reads a scanned product from the database on every scan, so a changed `PRICESELL` or `NAME` applies to the next scan. `PRICESELL` is ex-VAT; `TAXCAT 000` maps to `TAXES.ID 000`, `RATE 0`, so price equals the amount charged.
- Finance counts a voucher as 0% VAT revenue when sold and deducts `paperin` when redeemed (`VatReturnController` l.183-275, `SalesAccountingReportController` l.328-345, `ProfitLossController` l.128-135, `RtdSubmission`). None of that code refers to products `6012-6014` or category `033`. A voucher's own product sold at `TAXCAT 000` is therefore treated exactly as a voucher sale is today.
- Between the sale and the next till check (at most about a minute on production) the product still carries its price.

**Owner decisions (2026-09-29), do not re-open:**
1. Quantity above 1 on a voucher sale: activate nothing, flag for a manager.
2. A voucher sold again when it is already active (including a second scan before the price has dropped to zero): flag, add nothing.
3. A refund ticket carrying a voucher sale: flag; the manager deactivates the voucher by hand.
4. A voucher sale on a receipt paid with the Free tender: do not activate, flag.
5. Products `6012-6014` stay as they are for now. Not part of this cycle.
6. Manual activation stays, for managers and admins only (already the case).
7. The printed label does not show the amount for now.

**Planner decisions (record, do not re-open):**
- The amount charged must equal the voucher's value. A different amount (a price edited at the till) activates nothing and is flagged, like decision 1.
- Codes stay app-generated (`GV` + 10 characters). "Creates a voucher code and amount" means generating vouchers with a value.
- One value per batch: the generate form takes a count and one amount.

## Constraints

- Redemption behaviour from cycle 1 must not change: deduct = the receipt's summed `paperin`, LINE order, deduct to zero and `partial`, `no_tender`, `inactive`, `unknown`, `refund`, idempotency by the unique pair, heartbeat from cycle 2. Every existing test in the five files above keeps passing **unchanged**, except where this plan names a changed assertion.
- Money rules: 2 dp, one `voucher_transactions` row per balance change, `lockForUpdate` on the voucher, the redemption row inserted before money moves.
- Finance treatment must not change: voucher products stay `TAXCAT 000`; nothing in the finance controllers is touched.
- POS writes stay limited to `PRODUCTS` (insert; `NAME` and now `PRICESELL` on voucher products only) and the one `CATEGORIES` row. Never `PRODUCTS_CAT`. Never `TICKETS`, `RECEIPTS`, `TICKETLINES`, `PAYMENTS` (the dev simulator script excepted, on dev only).
- POS downtime must never fail generation, activation, deduction, lookup or the feed.
- JSON shapes are additive: `vouchers.lookup` gains keys, loses none.
- The ZPL label is unchanged (decision 7). Permissions unchanged. Products `6012-6014` untouched.
- From the Shop tree only `resources/views/shop/vouchers.blade.php` and `resources/js/shop/vouchers.js` may change, with `shop-*` classes only and no new `route()` call. The Shop mode track has other uncommitted work in the same working tree; leave it alone.
- Do not commit, push or deploy. Nothing runs against production.

## Out of scope

- Retiring or hiding products `6012-6014` (decision 5).
- Printing the amount on the label (decision 7).
- Topping up an active voucher at the till. Reversing a refund automatically.
- Renaming the POS category "Gift Voucher Redemption" or the table `voucher_till_redemptions`.
- The parked production backfill (`docs/vouchers/findings/2026-09-29-production-backfill-not-run.md`).
- A uniCenta-side check on over-tender; cash reconciliation; `TillTransactionRepository`.

## Steps

### 0. Baseline
What: record `git rev-parse --short HEAD`, `git status --short` and the `php artisan test` summary in `implemented.md` before any change. Expected about `15 failed, 835 passed`.
Check: the summary line and failing class names are pasted.

### 1. Migrations
Files: `database/migrations/2026_09_30_100000_add_face_value_to_vouchers_table.php` (new), `database/migrations/2026_09_30_100001_add_sale_amount_to_voucher_till_redemptions_table.php` (new)
What:
- `vouchers.face_value`: `decimal(10,2)` nullable, after `pos_product_id`. The value an unsold voucher is sold for. NULL for every existing voucher.
- `voucher_till_redemptions.sale_amount`: `decimal(12,2)` nullable, after `ticket_total`. What the till charged for the voucher's own line(s) on that ticket; NULL for redemption rows.
Check: `php artisan migrate` clean; `php artisan tinker --execute="var_dump(Schema::hasColumn('vouchers','face_value'), Schema::hasColumn('voucher_till_redemptions','sale_amount'));"` → two `true`.

### 2. Models and config
Files: `app/Models/Voucher.php`, `app/Models/VoucherTillRedemption.php`, `config/vouchers.php`
What:
- `Voucher`: `face_value` in fillable, cast `decimal:2`; `isForSale(): bool` = status `inactive` and `face_value > 0`.
- `VoucherTillRedemption`: `STATUS_ACTIVATED = 'activated'` (the sale activated the voucher; not an exception) and `STATUS_SALE_FLAGGED = 'sale_flagged'` (a sale that activated nothing). Add `sale_flagged` to `EXCEPTION_STATUSES`. `sale_amount` in fillable, cast `decimal:2`.
- `config/vouchers.php`: `'max_face_value' => 1000,` and in `pos_balance_text` a new entry `'for_sale' => 'for sale €%s'`.
Check: `php artisan tinker --execute="\$v=new App\Models\Voucher(['status'=>'inactive','face_value'=>20]); var_dump(\$v->isForSale());"` → `true`.

### 3. Till product carries the price
Files: `app/Services/VoucherPosProductService.php`, `app/Console/Commands/SyncVoucherPosProducts.php`
What:
- `productPrice(Voucher $v): float`: `round((float) $v->face_value, 2)` when `$v->isForSale()`, else `0.0`.
- `productName()`: when `isForSale()`, the balance text is `sprintf(config('vouchers.pos_balance_text.for_sale'), number_format(face_value, 2))`, giving `Gift Voucher GV7KQFM2RA9T [for sale €20.00]`. Every other status is unchanged (a valueless inactive voucher still reads `[not active]`).
- `sync()`: create with `'PRICESELL' => productPrice`. After the create/link step, compare both: if `NAME` differs **or** `round((float) $product->PRICESELL, 2)` differs from `productPrice`, update both columns in one `Product::whereKey(...)->update([...])`. Report either change as `renamed` (no new action name, so the backfill command's totals keep working).
- `SyncVoucherPosProducts`: add a `value` column to the printed table (face value or `—`). Behaviour otherwise unchanged; `--all` now also corrects prices.
Check: covered by step 8; on dev `php artisan vouchers:sync-pos-products --all` reports the two existing vouchers `unchanged` (they have no face value, price stays 0).

### 4. Generate with a value
Files: `app/Http/Controllers/VoucherController.php`, `resources/views/vouchers/generate.blade.php`, `resources/views/vouchers/print.blade.php`
What:
- `generate()`: validate `count` as now plus `amount` → `['required', 'numeric', 'gt:0', 'max:'.config('vouchers.max_face_value'), 'decimal:0,2']`. Each voucher is created with `face_value = round(amount, 2)`, `current_balance = 0`, `status = inactive`, `initial_value` left NULL. The rest of the method (POS `syncMany`, warning flash, redirect) is unchanged.
- `generate.blade.php`: a second input "Value of each voucher (€)", `name="amount"`, `step="0.01"`, `min="0.01"`, required, `old('amount')`. Replace the help text: each voucher starts unsold; selling it at the till (scan the label as an item) activates it with this value.
- `print()` adds `'face_value' => $v->face_value !== null ? (float) $v->face_value : null` to each label; `print.blade.php` shows it on screen under the code (`€20.00`, or nothing). The ZPL is not changed.
Check: as a manager in a test, `POST vouchers/generate` with `count=3, amount=20` creates three vouchers with `face_value 20.00` and three POS products priced 20 named `[for sale €20.00]`; without `amount` → validation error.

### 5. The till check learns about sales
Files: `app/Services/VoucherTillSyncService.php`
What:
- **Read price and units.** In `syncTicket()` select `tl.LINE, tl.PRODUCT, tl.UNITS, tl.PRICE, p.CODE` and `tx.RATE` (`leftJoin('TAXES as tx', 'tx.ID', '=', 'tl.TAXID')`), ordered by LINE. Replace `->unique('PRODUCT')` with a group by `PRODUCT` that keeps: the lowest `LINE`, `CODE`, `units` = sum of `UNITS`, `sale_total` = `round(sum(UNITS * PRICE * (1 + RATE ?? 0)), 2)`. Keep the groups in order of their lowest LINE.
- **Two kinds of group.** A group is a *sale* when `abs(sale_total) > 0.005`, otherwise a *redemption*. Redemption groups go through `applyLine()` exactly as now; `isLast` is computed over redemption groups only, so a sale on the same ticket cannot steal or create a `partial`.
- **Free tender.** Once per ticket: does the receipt have a `PAYMENTS` row with `PAYMENT = 'free'`?
- **Sale groups** go to a new `applySale()`, inside the same per-line `DB::transaction`, redemption row inserted first. Every row it writes carries `sale_amount = abs(sale_total)`, `amount_deducted 0`, `shortfall 0`. Rules in this order:
  1. `TICKETTYPE = 1` → status `refund`, note `Refund of a voucher sale (€X). Deactivate the voucher if it was handed back.` Voucher untouched. (A refund of a redemption line keeps today's `refund` row with no note.)
  2. No voucher for the product → `unknown`, note as today.
  3. Lock the voucher (`lockForUpdate`). Status is not `inactive` → `sale_flagged`, note `Charged €X but the voucher was already <status> (balance €Y). Nothing was added.` (decision 2)
  4. The receipt has a Free payment → `sale_flagged`, note `Paid with the Free tender. Not activated.` (decision 4)
  5. `units` is not exactly 1 → `sale_flagged`, note `Quantity <n> on one voucher (charged €X). Not activated: each voucher is scanned itself.` (decision 1)
  6. `face_value` is NULL/0, or differs from `abs(sale_total)` by more than 0.005 → `sale_flagged`, note `Charged €X but the voucher's value is €Y. Not activated.`
  7. Otherwise **activate**: `initial_value = current_balance = face_value`, `status = active`; create the transaction `type issue`, `source till`, `amount = face_value`, `balance_after = face_value`, `user_id null`, `note 'Till #<TICKETID>'`; redemption row status `activated` with `voucher_transaction_id`.
- **After the loop**, call `posProducts->sync()` for every voucher that was deducted from **or activated**. For an activated voucher that sets the price to 0 and the name to `[bal €X]`.
- `COUNT_KEYS`: insert `'activated', 'sale_flagged'` after `'refund'`.
- The pre-check for an already recorded pair, the 23000 catch, the per-ticket catch with `errors`, the watermark and the heartbeat are unchanged.
Check: `php artisan test --filter=VoucherTillSyncServiceTest` → the existing 23 pass unchanged.

### 6. Screens say what happened
Files: `app/Http/Controllers/VoucherController.php`, `app/Services/VoucherActivityService.php`, `resources/views/vouchers/exceptions.blade.php`, `resources/views/vouchers/activity.blade.php`, `resources/views/vouchers/list.blade.php`, `resources/views/vouchers/transactions.blade.php`
What:
- `lookup()` adds `'face_value' => float|null` and `'for_sale' => $voucher->isForSale()`. `history()`: an `issue` row with `source = till` gets label `Sold at till` (its `user` is already `Till #N`).
- `VoucherActivityService::fromTransaction()`: `issue` + till → kind `issue`, label `Sold at till`. `fromRedemption()`: `sale_flagged` → label `Sale not activated`; add `'sale_amount'` (float or null) to **both** mappers (from the till redemption when there is one).
- `exceptions.blade.php`: pill for `sale_flagged` (red, "Sale not activated"); a "Sale" column after "Voucher tender" showing `sale_amount` or `—`.
- `activity.blade.php`: `pill()` handles `sale_flagged` through the existing `exception` branch (no change needed if the status text reads well: it shows `sale flagged`; replace `_` globally, the current `.replace('_', ' ')` only replaces the first); show `sale_amount` as a second line under the label when present (`charged €X`), behind an `x-if`.
- `list.blade.php` l.58 and `transactions.blade.php` l.19-20: when `initial_value` is NULL and `face_value` is not, show `€20.00 for sale` in the yellow pill colours instead of `—`.
Check: step 8's tests; `node --check` of the activity view's inline script as in cycle 2.

### 7. Lookup screens for an unsold voucher
Files: `resources/views/vouchers/index.blade.php`, `resources/views/shop/vouchers.blade.php`, `resources/js/shop/vouchers.js`
What:
- Office `index.blade.php`: keep `faceValue` and `forSale` from the lookup in the Alpine state. In the ACTIVATE block: managers see, when `forSale`, a line "Value €20.00. Normally sold at the till: scan the label as an item and it activates itself." and the starting-balance input **pre-filled** with the face value (l.380 sets `startingBalance` to the face value instead of `''`); employees see, when `forSale`, "Not sold yet (€20.00). Sell it at the till: scan the label as an item." in place of "Please ask a manager." For a voucher without a value, both keep today's wording exactly.
- Shop `vouchers.blade.php` / `vouchers.js`: getters `forSale` and `faceValue` from `voucher`; a new flat card shown when `forSale && ! activateUrl`: "Not sold yet (€20.00). Sell it at the till: scan the label as an item."; the existing `needsManager` card gains `&& ! forSale`. For a manager (`activating`) on a `forSale` voucher, `onScan()` sets `typed` to the face value (`faceValue.toFixed(2)`) instead of `''`, and the hint reads "Not sold yet. Normally sold at the till; to activate by hand, confirm the value." Keep "Voucher not active. Please ask a manager." and "Not yet active. Enter the starting balance and activate." in the markup for the no-value case. Respect the Alpine rules in `planimp.md`. `npm run build`.
Check: `php artisan test --filter='ShopVouchersTest|ShopViewContractTest|ConfinePinSessionTest'` passes; `node --check resources/js/shop/vouchers.js`.

### 8. Tests
Files: `tests/Feature/VoucherTillSyncServiceTest.php`, `tests/Feature/VoucherPosProductServiceTest.php`, `tests/Feature/VoucherTillExceptionsTest.php`, `tests/Feature/VoucherActivityTest.php`, `tests/Feature/Shop/ShopVouchersTest.php`
What (add; change no existing assertion unless named here):
- `VoucherPosProductServiceTest`: a for-sale voucher's product is created priced at its value and named `[for sale €20.00]`; activating it (status active, balance 20) and syncing sets price 0 and `[bal €20.00]`; a valueless inactive voucher stays price 0 `[not active]`; a price drifted on the till side is corrected by `sync()`; generate with `count` + `amount` (step 4's check); generate without `amount` fails validation. **Changed assertion:** `test_generating_vouchers_creates_their_products` and `test_generating_warns_when_the_pos_is_down` must now post an `amount`; say so in Deviations if anything else had to change.
- `VoucherTillSyncServiceTest`, with a helper `forSale(float $value, string $code)` creating an inactive voucher with a face value and its product:
  - sold for its value, paid by card → `activated`; voucher active with `initial_value` and balance = value; transaction `issue`/`till`/`Till #N`, `user_id` null; redemption `sale_amount` = value; product price 0 and `[bal €20.00]`.
  - quantity 2 on one line → `sale_flagged`, voucher still inactive, product still priced; two separate lines of the same voucher → the same.
  - paid with `free` → `sale_flagged`.
  - line price edited (value 20, charged 15) → `sale_flagged`.
  - sold when already active → `sale_flagged`, balance unchanged; sold when deactivated → `sale_flagged`.
  - refund ticket (`type 1`, units −1, price 20) → `refund` with the sale note, voucher untouched.
  - unknown product sold at a price → `unknown` with `sale_amount`.
  - one ticket: voucher A sold (20, card 20 + the rest) and voucher B redeemed with `paperin` 10 → A `activated`, B `applied` for 10; and a ticket with a sale plus one redemption whose tender exceeds its balance → the redemption is `partial` (a sale group does not count as the last redemption line).
  - a voucher bought with a voucher: A sold for 20, paid entirely by `paperin` 20 with B's redemption line on the ticket → A `activated`, B `applied` 20.
  - second run changes nothing; after activation and reprice a later €0.00 scan with `paperin` redeems normally.
  - counts contain `activated` and `sale_flagged`.
- `VoucherTillExceptionsTest`: a `sale_flagged` row shows on the page with its pill, note and Sale amount; `activated` rows do not.
- `VoucherActivityTest`: a till activation appears as `Sold at till` with positive amount and `who` `Till #N`; a `sale_flagged` row appears once as `Sale not activated` with `sale_amount`; "Issued today" includes the till activation.
- `ShopVouchersTest`: lookup of a for-sale voucher returns `face_value` and `for_sale = true`; the shop page contains both the for-sale wording and the original "ask a manager" wording; an employee still gets 403 on `vouchers.activate`; a manager activating a for-sale voucher by hand leaves the product at price 0.
Check: `php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession|ShopViewContract'` → all pass.

### 9. Dev helper and docs
Files: `docs/vouchers/scripts/simsale.php`, `docs/features/voucher-management.md`, `docs/FEATURES_INDEX.md`, `CLAUDE.md`
What:
- `simsale.php`: new optional env `SALE=1` (the voucher line is inserted at `PRICE` = the voucher's face value, or `PRICE=<n>` when given, `UNITS=<n>` default 1, and the payment is `PAY=<type>` default `magcard` for the ticket total instead of `paperin`). Without `SALE` it behaves exactly as now. Document the new options in its header.
- `voucher-management.md`: rewrite `## Overview` steps 1-4 for the new lifecycle; new `## Selling a voucher at the till` before `## Till redemption (uniCenta)`: cashier steps (scan the label as an item, the till charges its value, take payment as normal, **scan each voucher itself, never use quantity**), what activates and what is flagged (a table of the `sale_flagged` reasons and `refund`), the one-minute window, manual activation as the manager fallback, that labels of different values look the same so batches must be kept apart, and that products `6012-6014` still exist. Update `## Statuses`, `## Data model` (`face_value`, `sale_amount`, the two new statuses), the exception table, the generate section and `## Key files`.
- `FEATURES_INDEX.md` and the `CLAUDE.md` voucher line: vouchers are generated with a value and activated by their sale at the till.
Check: `grep -n "sale_flagged\|face_value" docs/features/voucher-management.md` finds both.

## Verification (report every item with what you saw)

1. `./vendor/bin/pint --test` on the PHP files you changed (not `--dirty`) → PASS.
2. `php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession|ShopViewContract'` → all pass.
3. `php artisan test` → no new failures against step 0. Paste the summary and failing class names.
4. `node --check` on `resources/js/shop/vouchers.js` and on the extracted inline scripts of `vouchers/index.blade.php` and `vouchers/activity.blade.php` → clean. `npm run build` → built.
5. Dev, by tinker and the scripts (record every id; clean up afterwards):
   - Create one for-sale voucher of €20 (`Voucher::create` + `VoucherPosProductService::sync`, or through the generate form in a test). POS product: `PRICESELL 20`, NAME `[for sale €20.00]`.
   - `SALE=1 VOUCHER=<code> TICKET=999910 … simsale.php`, then `php artisan vouchers:sync-till` → `activated 1`. Voucher active €20.00; POS product `PRICESELL 0`, NAME `[bal €20.00]`; `payload(7, null)` shows "Sold at till · Till #999910".
   - A redemption on the same voucher (`TENDER=5`, no `SALE`) → `applied 1`, balance €15.00.
   - A second for-sale voucher sold with `UNITS=2` → `sale_flagged 1`, voucher still inactive, product still priced.
   - Delete the simulated POS rows and the test vouchers' POS products; delete the test vouchers and their local rows, or say exactly what was left.
6. **Owner, on the dev till (VirtualBox uniCenta) and in the browser as a manager:** generate one €10 voucher; give it a numeric barcode with `numeric_barcode.php` if keying by hand; scan it at the till: a €10.00 line named `[for sale €10.00]`; pay by cash; run `php artisan vouchers:sync-till` (dev has no cron); `/vouchers/activity` shows "Sold at till"; scan the label again at the till: a €0.00 line `[bal €10.00]`; redeem €4 with the Voucher tender; balance €6.00. Then sell a second voucher with quantity 2 and confirm it appears on `/vouchers/exceptions` as "Sale not activated".

## Risks

- **Quantity habit.** 30 of the last 196 voucher sale lines used a quantity above 1. With this process each voucher is scanned itself; a cashier who scans one label and keys ×3 charges €60 and activates nothing. The exceptions page catches it, but the customer has left with three dead vouchers. Cashier training matters more than any code here; the docs step says so.
- **Labels look identical** whatever their value (decision 7). Batches of different values must be kept apart physically; the till line shows the price at scan, which is the only check.
- **The one-minute window.** Until the next till check the sold voucher's product still carries its price. A redemption attempted in that minute would charge the value again and be flagged. On dev there is no cron, so the window lasts until someone runs the command or looks a voucher up.
- **Price drift.** If a product's price is changed by hand in uniCenta, `vouchers:sync-pos-products --all` or the next sync of that voucher puts it back; until then a sale at the wrong price is flagged, not activated.
- **Sales reports by category** will show voucher sales under the POS category "Gift Voucher Redemption". The name is misleading for sales; renaming it is out of scope and noted as a follow-up.
- **Old and new side by side.** While `6012-6014` exist, a cashier can still sell "Voucher 20 Euro" and hand over a label that was never scanned; that voucher stays unsold until a manager activates it by hand. Expected during the changeover.
- **Existing vouchers** have no face value: inactive ones stay `[not active]` at price 0 and need manual activation, as today.

## Review

(Planner fills this in after reading implemented.md and the diff.)
