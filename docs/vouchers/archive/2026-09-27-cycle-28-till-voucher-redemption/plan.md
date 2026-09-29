# Till-driven gift voucher redemption (cycle 28)

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-27

## Goal

A cashier redeems a gift voucher at the uniCenta till in the normal sale, with no second screen: ring up the goods, scan the voucher label (it adds a €0.00 line whose name shows the voucher's remaining balance), then pay with the till's own Voucher tender. Within a minute, or on the next voucher scan in the app, the app deducts the amount tendered from that voucher and records the till ticket number in its history. Anything odd (tender larger than the balance, voucher scanned but paid by cash, inactive voucher, refund) lands on a manager exceptions page. The manual deduct screens stay as a fallback but lead with balance and history.

## Context

**Why the till's Voucher tender cannot do this alone.** uniCenta records a Voucher payment as `PAYMENTS.PAYMENT = 'paperin'` with the amount in `TOTAL` (`TENDERED` is always 0). `TRANSID` is a random 12-digit id the till generates for every payment type (card rows carry the same shape; no value ever repeats), so nothing on the payment says which voucher was used. Voucher identity therefore arrives via a scanned product line.

**Vouchers today** (Laravel only, no POS link):
- `app/Models/Voucher.php`: statuses `inactive|active|exhausted|deactivated`; code `GV` + 10 chars from `23456789ABCDEFGHJKLMNPQRSTUVWXYZ`; `generateUniqueCode()`; `toZplLabel()` prints CODE-128; fillable `code, initial_value, current_balance, status, created_by`; SoftDeletes; no factory (tests use `Voucher::create`).
- `app/Models/VoucherTransaction.php`: types `issue|deduct|deactivate|activate`; fillable `voucher_id,type,amount,balance_after,note,user_id`; `voucher()`, `user()`.
- `app/Http/Controllers/VoucherController.php`: `index()` l.24, `list()` l.32, `transactions()` l.52, `lookup()` l.62-84 (returns `found,code,status,initial_value,current_balance,issued_at,history`), `history()` l.111-136 (maps transactions; `user` falls back to `'Office'`), `activate()` l.141-182 (creates unknown codes on the fly, `lockForUpdate`, `issue` row), `deduct()` l.187-246 (`lockForUpdate`, refuses over-balance, sets `exhausted` at 0), `changeStatus()` l.283-322, `generate()` l.335-355 (1-200 inactive vouchers inside `DB::transaction`, then redirect to print), `print()`/`printZebra()` l.360-405.
- Routes `routes/web.php` l.1204-1223: `vouchers.index|lookup|deduct` under `permission:vouchers.redeem`; `vouchers.activate|generate|generate.store|print|print.send|list|transactions` under `permission:vouchers.manage`; `deactivate|reactivate` admin only. Shop route l.114 `shop.vouchers` → `app/Http/Controllers/Shop/VouchersController.php`.
- Views: office `resources/views/vouchers/index.blade.php` (inline Alpine `voucherTill()`; ACTIVE block l.117-153 shows balance + deduct input; EXHAUSTED block l.167-175; no history rendered today), `vouchers/list.blade.php` (dark table styling to copy), `vouchers/transactions.blade.php`, `vouchers/generate.blade.php`, `vouchers/print.blade.php`. Shop `resources/views/shop/vouchers.blade.php` + `resources/js/shop/vouchers.js` (history rows print `when(t.at) + ' · ' + t.user`; numpad + deduct in the right column; "Use full balance" button asserted by `tests/Feature/Shop/ShopVouchersTest.php`).
- Admin sidebar `resources/views/layouts/admin.blade.php` l.282-318: voucher links, `vouchers.manage` block at l.302.
- Docs: `docs/features/voucher-management.md`, `docs/FEATURES_INDEX.md` (~l.587), `CLAUDE.md` voucher line.

**POS side (connection `pos`, `config/database.php` l.125-142, writable; the app already creates products):**
- `app/Models/Product.php`: table `PRODUCTS`, string PK `ID`, fillable `ID,NAME,CODE,REFERENCE,CATEGORY,TAXCAT,PRICESELL,PRICEBUY,DISPLAY,IMAGE`; `ISSERVICE` cast boolean but not fillable (use `forceFill`). NOT NULL without default: `ID, REFERENCE, CODE, NAME, CATEGORY, TAXCAT`. `NAME`, `CODE`, `REFERENCE` are each UNIQUE. Hidden-product pattern to copy: `app/Services/KitchenWholesaleService.php` l.192-245 (creates a product and deliberately does not insert into `PRODUCTS_CAT`, so no till button).
- `app/Models/Category.php`: table `CATEGORIES`, string PK, fillable lacks `ID` → insert the category row with `DB::connection('pos')->table('CATEGORIES')->insert()`. Columns used: `ID, NAME (unique), PARENTID, CATSHOWNAME`. Voucher-sale products already exist: `6012 Voucher 50 Euro`, `6013 Voucher 10 Euro`, `6014 Voucher 20 Euro`, all `CATEGORY '033'` ("Seasonal (Parent)") and `TAXCAT '000'` ("Tax aZero"). No voucher/gift category exists yet.
- POS models `app/Models/POS/`: `Ticket` (TICKETS; `TICKETID` int, `TICKETTYPE` 0 sale / 1 refund; `receipt()`, `ticketLines()`), `TicketLine` (TICKETLINES; `TICKET, LINE, PRODUCT, UNITS, PRICE, TAXID`), `Receipt` (RECEIPTS; `DATENEW`; `payments()`), `Payment` (PAYMENTS; `ID, RECEIPT, PAYMENT, TOTAL, TRANSID, RETURNMSG, NOTES, TENDERED, CARDNAME`).
- Poller pattern to copy: `app/Http/Controllers/KdsRealtimeController.php` l.20-110 (watermark from the local table's max time, capped look-back, `DB::connection('pos')->table('TICKETS as t')->join(RECEIPTS/TICKETLINES/PRODUCTS)->where('r.DATENEW','>',…)->where('t.TICKETTYPE',0)->distinct()->limit()`, dedupe by local `ticket_id`, second query for lines).
- uniCenta looks a scanned product up in the DB on every scan (not cached like the catalog buttons), so a `NAME` change is visible on the very next scan.
- Live data facts: 993 `paperin` rows in history; one has `TOTAL = 5,390,746,000,620` (a barcode typed into the amount field) — the exceptions page must make such a row obvious. Dev POS is a copy at `127.0.0.1:3307` (data to 2026-08-14) with no till attached.

**Scheduling and tests:**
- Schedules live in `routes/console.php` only; `tests/Feature/ScheduleTest.php` pins every entry (each needs `withoutOverlapping()` + `onOneServer()`) and asserts the count at l.68 (`assertCount(8, …)`). Production must run `schedule:run` (check with `php artisan schedule:list`); this dev box has no cron.
- Tests fake the POS with an in-memory sqlite `pos` connection (`phpunit.xml` `POS_DB_CONNECTION=sqlite`, `:memory:`) and build tables with the schema builder: see `tests/Concerns/CreatesLegacyDeliveryPosTables.php`, `CreatesKitchenOrderPosTables.php`, `CreatesProductSearchPosTables.php`. `lockForUpdate()` is a no-op on sqlite. Store `DATENEW` as `Y-m-d H:i:s` strings so comparisons work.
- `tests/Feature/Shop/ConfinePinSessionTest.php` asserts every `route()` named in `resources/views/shop/**`, `resources/js/shop/**`, the shop components and layout is on `config('shop.pin_session_routes')`. This plan adds no new `route()` call to those files, so the allow-list is untouched.
- Suite baseline (2026-09-26): 15 failed (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2, TestScraper ×1), all pre-existing.

**Owner decisions (2026-09-27), do not re-open:**
1. Amount deducted = sum of `paperin` payments on the receipt. Voucher line but no voucher tender → nothing deducted, exception `no_tender`.
2. Tender exceeds balance → deduct to zero (voucher `exhausted`), exception `partial` with the shortfall.
3. Redemption only. Activation stays manual.
4. Manual deduct stays as fallback; both screens lead with balance + history; managers get an exceptions page.
5. The POS product's `NAME` carries the current balance so the cashier sees it on the till when the voucher is scanned, before choosing the tender amount.

## Constraints

- Voucher money rules stay as in `deduct()`: round to 2 dp, never go below zero, `exhausted` exactly when the balance reaches 0, every balance change writes one `voucher_transactions` row with `balance_after`.
- Existing JSON shapes are additive only: `lookup()` keeps every current key; `history` rows keep `type,label,amount,balance_after,user,at` and gain `source,ticket_number`.
- Never write to `TICKETS`, `RECEIPTS`, `TICKETLINES`, `PAYMENTS`. POS writes are limited to `PRODUCTS` (insert, and `NAME` updates on voucher products only) and one `CATEGORIES` row. Never insert into `PRODUCTS_CAT` (no till button).
- POS downtime must never fail voucher generation, activation, deduction or lookup: every POS write and the lookup-time sync are wrapped, logged, and degrade silently.
- Finance treats `paperin` as gift-voucher redemption (excluded from revenue in VAT/P&L/sales accounting); nothing here changes that.
- Permissions: reuse `vouchers.redeem` and `vouchers.manage`; no new permission, no seeder change.
- Do not commit, push or deploy.

## Out of scope

- Auto-activating vouchers at the till (a "Voucher 50 Euro" line plus a blank GV label). Possible later cycle.
- Reversing a refund ticket's voucher deduction (recorded as `refund`, manager re-credits by hand).
- Auto-filling cash reconciliation `voucher_used` from `paperin`; fixing `TillTransactionRepository::formatReceipt` reading only the first payment; excluding the voucher category from product search or `SalesImportService`. Note them in `Notes for Planner` if seen, do not touch.
- Any change to label printing, ZPL, or the shop scan-input component.
- KDS code.

## Steps

### 1. Config
Files: `config/vouchers.php` (new)
What: return array with `pos_category_name` (`env('VOUCHER_POS_CATEGORY', 'Gift Voucher Redemption')`), `pos_category_parent_id` (`env('VOUCHER_POS_CATEGORY_PARENT', '033')`), `pos_taxcat` (`'000'`), `pos_name_format` (default `'Gift Voucher %s [%s]'` — first `%s` is the code, second is the balance text), `pos_balance_text` (map: `active` → `'bal €%s'`, `exhausted` → `'€0.00 used up'`, `inactive` → `'not active'`, `deactivated` → `'deactivated'`), and `sync` = `['lookback_hours' => 24, 'overlap_minutes' => 15, 'batch' => 50, 'on_lookup' => true, 'lookup_throttle_seconds' => 5]`.
Check: `php artisan tinker --execute="echo config('vouchers.pos_category_name');"` prints `Gift Voucher Redemption`.

### 2. Migrations
Files: `database/migrations/2026_09_28_100000_create_voucher_till_redemptions_table.php` (new), `2026_09_28_100001_add_source_to_voucher_transactions_table.php` (new), `2026_09_28_100002_add_pos_product_id_to_vouchers_table.php` (new)
What:
- `voucher_till_redemptions`: `id; pos_ticket_id string(64); pos_product_id string(64) nullable; voucher_code string(20) nullable; ticket_number integer; ticket_type unsignedTinyInteger default 0; sold_at dateTime index; voucher_tender decimal(14,2) default 0; ticket_total decimal(12,2) nullable; amount_deducted decimal(10,2) default 0; shortfall decimal(14,2) default 0; status string(20) index; voucher_id foreignId nullable constrained nullOnDelete; voucher_transaction_id foreignId nullable constrained('voucher_transactions') nullOnDelete; note string(500) nullable; reviewed_at timestamp nullable; reviewed_by foreignId nullable constrained('users') nullOnDelete; timestamps; unique(['pos_ticket_id','pos_product_id']); index(['status','reviewed_at'])`.
- `voucher_transactions`: `source string(10) default 'manual'` after `type`.
- `vouchers`: `pos_product_id string(64) nullable unique` after `code`.
Check: `php artisan migrate` clean; `php artisan tinker --execute="var_dump(Schema::hasTable('voucher_till_redemptions'), Schema::hasColumn('voucher_transactions','source'), Schema::hasColumn('vouchers','pos_product_id'));"` → three `true`.

### 3. Models
Files: `app/Models/VoucherTillRedemption.php` (new), `app/Models/VoucherTransaction.php`, `app/Models/Voucher.php`
What:
- `VoucherTillRedemption`: constants `STATUS_APPLIED='applied'`, `STATUS_PARTIAL='partial'`, `STATUS_NO_TENDER='no_tender'`, `STATUS_INACTIVE='inactive'`, `STATUS_UNKNOWN='unknown'`, `STATUS_REFUND='refund'`, `EXCEPTION_STATUSES` = all but applied; fillable for every column above; casts `sold_at` datetime, `reviewed_at` datetime, money columns `decimal:2`; relations `voucher()`, `transaction()` (belongsTo `VoucherTransaction`, `voucher_transaction_id`), `reviewer()` (belongsTo `User`, `reviewed_by`); scopes `scopeExceptions()` (`whereIn('status', EXCEPTION_STATUSES)`), `scopeUnreviewed()` (`whereNull('reviewed_at')`).
- `VoucherTransaction`: `SOURCE_MANUAL='manual'`, `SOURCE_TILL='till'`; add `source` to fillable; `tillRedemption(): HasOne` on `voucher_transaction_id`.
- `Voucher`: add `pos_product_id` to fillable; `tillRedemptions(): HasMany`.
Check: `php artisan tinker --execute="\$v=App\Models\Voucher::create(['code'=>'GVTESTTEST01','current_balance'=>0,'status'=>'inactive']); \$t=\$v->transactions()->create(['type'=>'deduct','source'=>'till','amount'=>1,'balance_after'=>0]); echo \$t->fresh()->source; \$v->forceDelete();"` prints `till`.

### 4. POS product service
Files: `app/Services/VoucherPosProductService.php` (new)
What: class with
- `categoryId(): string` — memoised; `DB::connection('pos')->table('CATEGORIES')->where('NAME', config name)->value('ID')`; when missing insert `['ID' => (string) Str::uuid(), 'NAME' => …, 'PARENTID' => config parent, 'CATSHOWNAME' => 0]` and return the new id.
- `productName(Voucher $v): string` — `sprintf(config('vouchers.pos_name_format'), $v->code, $balanceText)` where `$balanceText` comes from `config('vouchers.pos_balance_text')[$v->status]`, with the active text taking `number_format($v->current_balance, 2)`. Example active: `Gift Voucher GV7KQFM2RA9T [bal €42.50]`; exhausted: `Gift Voucher GV7KQFM2RA9T [€0.00 used up]`; inactive: `… [not active]`.
- `sync(Voucher $v): ?string` — ensure the product exists and its `NAME` is current. Order: (a) if `pos_product_id` set and `Product::find()` exists, use it; (b) else `Product::where('CODE', $v->code)->first()` (self-heal after a half-failed create; link it); (c) else `Product::create(['ID' => (string) Str::uuid(), 'NAME' => productName, 'CODE' => code, 'REFERENCE' => code, 'CATEGORY' => categoryId(), 'TAXCAT' => config taxcat, 'PRICESELL' => 0, 'PRICEBUY' => 0])` then `forceFill(['ISSERVICE' => 1])->save()` (no stock movements). Then if the product's `NAME` differs from `productName`, `Product::whereKey($id)->update(['NAME' => …])`. Save `pos_product_id` on the voucher via `forceFill(...)->saveQuietly()` when it changed. The whole method is in `try { … } catch (\Throwable $e) { Log::warning('Voucher POS product sync failed', ['code' => …, 'error' => $e->getMessage()]); return null; }`. Never insert into `PRODUCTS_CAT`, never create `STOCKCURRENT`/metadata rows.
- `syncMany(iterable $vouchers): array{synced:int, created:int, failed:int}`.
Check: covered by step 11's `VoucherPosProductServiceTest`; also `php artisan tinker --execute="app(App\Services\VoucherPosProductService::class)->sync(App\Models\Voucher::first());"` against the dev POS copy creates a `PRODUCTS` row whose `NAME` starts with `Gift Voucher GV` (then leave it; the backfill in step 5 is idempotent).

### 5. Backfill command
Files: `app/Console/Commands/SyncVoucherPosProducts.php` (new)
What: signature `vouchers:sync-pos-products {--dry-run} {--all : also re-sync vouchers that already have a pos_product_id}`; default set `Voucher::whereNull('pos_product_id')` in every status (a printed-but-unsold label must scan, or the till says "product not found"); `--all` = every voucher (renames to the current balance). Print a table `code | status | balance | action` and the totals. `--dry-run` lists without writing.
Check: `php artisan vouchers:sync-pos-products --dry-run` lists the two active vouchers; `php artisan vouchers:sync-pos-products` creates them; a second run reports 0 created.

### 6. Till sync service
Files: `app/Services/VoucherTillSyncService.php` (new)
What: constructor takes `VoucherPosProductService`. Public `sync(?Carbon $since = null): array` returning `['tickets'=>n,'applied'=>n,'partial'=>n,'no_tender'=>n,'inactive'=>n,'unknown'=>n,'refund'=>n,'skipped'=>n]`, and `syncIfDue(): ?array` = `if (! config('vouchers.sync.on_lookup')) return null; if (! Cache::add('vouchers:till-sync', 1, config throttle seconds)) return null; try { return $this->sync(); } catch (\Throwable $e) { Log::warning(...); return null; }`.
- Watermark: `VoucherTillRedemption::max('sold_at')` minus `overlap_minutes`, floored at `now() - lookback_hours`; empty table → `now() - lookback_hours`. `$since` overrides.
- Candidate tickets (builder copied from `KdsRealtimeController` l.53-68): `TICKETS t join RECEIPTS r on r.ID=t.ID join TICKETLINES tl on tl.TICKET=t.ID join PRODUCTS p on p.ID=tl.PRODUCT where r.DATENEW > :since and p.CATEGORY = :voucherCategory and t.TICKETTYPE in (0,1)`, `select t.ID, t.TICKETID, t.TICKETTYPE, r.DATENEW` distinct, `orderBy r.DATENEW, t.TICKETID`, `limit batch`. The category filter (not a `whereIn` of product ids) keeps the query fixed-size as vouchers accumulate and also catches GV products with no local voucher row (`unknown`).
- Per ticket: voucher lines = `TICKETLINES tl join PRODUCTS p … where tl.TICKET = ? and p.CATEGORY = ? order by tl.LINE`, then group by `PRODUCT` keeping the lowest `LINE` (a double scan collapses to one; `UNITS` ignored); `voucher_tender = round(SUM(TOTAL) from PAYMENTS where RECEIPT = ? and PAYMENT = 'paperin', 2)`; `ticket_total = round(SUM(tl.PRICE * tl.UNITS * (1 + tx.RATE)) from TICKETLINES tl join TAXES tx on tx.ID = tl.TAXID where tl.TICKET = ?, 2)` (same formula as `app/Repositories/TillTransactionRepository.php` `formatReceipt`).
- Rules, in `LINE` order, sharing one `$pool = voucher_tender`:
  1. `TICKETTYPE = 1` → status `refund`, `amount_deducted 0`, voucher untouched.
  2. Resolve the voucher by `pos_product_id`, else by `code = p.CODE` (and repair `pos_product_id`). None → `unknown`, `voucher_code = p.CODE`.
  3. Voucher status not `active`, or balance ≤ 0 → `inactive`, nothing deducted, pool untouched.
  4. `$pool <= 0` → `no_tender`.
  5. `$deduct = min(balance, $pool)`; `$pool -= $deduct`; balance −= deduct; `exhausted` when it reaches 0; status `applied`. If this is the last voucher line on the ticket and `$pool > 0` → status `partial`, `shortfall = $pool`. (A single-voucher ticket reduces exactly to owner decision 2.)
- Each line: skip cheaply if `VoucherTillRedemption::where(pos_ticket_id, pos_product_id)->exists()`. Otherwise `DB::transaction` on the default connection: `Voucher::whereKey(id)->lockForUpdate()->first()` (re-read balance under the lock), insert the redemption row first (the unique index is the idempotency backstop; catch `QueryException` with `errorInfo[0] === '23000'` → `skipped++`), update the voucher, create `VoucherTransaction(['type' => deduct, 'source' => till, 'amount' => deduct, 'balance_after' => …, 'user_id' => null, 'note' => "Till #{TICKETID}" (+ " · short €X.XX" for partial)])` only when `deduct > 0`, set `voucher_transaction_id`. After the transaction commits, call `VoucherPosProductService::sync($voucher)` for any line that changed the voucher (renames the POS product to the new balance). Any other throwable inside a ticket: `Log::error`, skip the ticket (the watermark only advances through stored `sold_at`, so it is retried next run).
Check: covered by step 11's `VoucherTillSyncServiceTest`.

### 7. Sync command and schedule
Files: `app/Console/Commands/SyncVoucherTillRedemptions.php` (new), `routes/console.php`, `tests/Feature/ScheduleTest.php`
What: `vouchers:sync-till {--since= : ISO datetime overriding the watermark}` prints the counts table, returns `FAILURE` on exception. Schedule: `Schedule::command('vouchers:sync-till')->everyMinute()->onOneServer()->withoutOverlapping(5);` with a comment saying the lookup hook (step 8) also runs it. Update `ScheduleTest`: add the entry with expression `* * * * *` and bump `assertCount(8, …)` to 9.
Check: `php artisan schedule:list` shows `vouchers:sync-till` every minute; `php artisan test --filter=ScheduleTest` passes; `php artisan vouchers:sync-till` on dev prints a table with all zeros.

### 8. Controller: hooks, history, exceptions
Files: `app/Http/Controllers/VoucherController.php`, `routes/web.php`
What:
- Constructor: inject `VoucherPosProductService $posProducts` and `VoucherTillSyncService $tillSync` alongside `ZebraPrintService`.
- `lookup()`: first statement `$this->tillSync->syncIfDue();`.
- `history()`: `->with(['user', 'tillRedemption'])`; for `TYPE_DEDUCT` the label is `'Redeemed at till'` when `source === 'till'`, else `'Redeemed'`; `user` is `'Till #'.($t->tillRedemption?->ticket_number ?? '?')` for till rows, else `$t->user?->name ?? 'Office'`; add `'source' => $t->source` and `'ticket_number' => $t->tillRedemption?->ticket_number`.
- `activate()`: after the transaction returns `success`, `$this->posProducts->sync($voucher)` (reload the voucher by code first, since the closure's instance is out of scope). `deduct()` and `changeStatus()`: same call after success (renames the POS product to the new balance/status). `generate()`: after `DB::transaction` commits, `$summary = $this->posProducts->syncMany(Voucher::whereIn('id', $ids)->get())`; if `failed > 0`, `->with('warning', "{$failed} voucher products could not be created on the till; run vouchers:sync-pos-products")` on the redirect.
- `index()`: pass `exceptionCount` = `VoucherTillRedemption::exceptions()->unreviewed()->count()` when the user can `vouchers.manage`, else 0.
- `transactions()`: eager-load `transactions.tillRedemption` too.
- New `exceptions(Request)`: `VoucherTillRedemption::with(['voucher', 'reviewer'])->exceptions()`, `unreviewed()` unless `?all=1`, `latest('sold_at')->paginate(50)->withQueryString()` → view `vouchers.exceptions`.
- New `markReviewed(Request, VoucherTillRedemption $redemption)`: validate `note nullable|string|max:500`; set `reviewed_at = now()`, `reviewed_by = Auth::id()`, append note; redirect back with `status` flash.
- Routes inside the `vouchers.manage` group, placed before `vouchers/{voucher}/transactions`: `Route::get('vouchers/exceptions', …)->name('vouchers.exceptions')` and `Route::post('vouchers/exceptions/{redemption}/reviewed', …)->name('vouchers.exceptions.reviewed')`.
Check: `php artisan route:list --name=vouchers` shows the two new routes; `php artisan test --filter=ShopVouchersTest` still passes at this point.

### 9. Office views
Files: `resources/views/vouchers/index.blade.php`, `resources/views/vouchers/transactions.blade.php`, `resources/views/vouchers/exceptions.blade.php` (new), `resources/views/layouts/admin.blade.php`
What:
- `index.blade.php`: add `history: []` to the Alpine state and set it from `data.history` in `lookup()`; render a compact history list (label · date · user, signed amount, `balance_after`) under the balance in the ACTIVE block (l.117-153) and in the EXHAUSTED block (l.167-175) so staff can see the till redemption that emptied it. Wrap the deduct input, "Use full balance" and Deduct button in a collapsed "Manual deduct" section (`x-show="manualOpen"` with a toggle button; default closed). When `$canManage` and `$exceptionCount > 0`, show a "Till exceptions (N)" link to `vouchers.exceptions` in the page header. Respect the Alpine rules in `planimp.md` (no `@`-shorthands that are Blade directives; `?.` behind `x-show`).
- `transactions.blade.php`: the "By" cell shows `Till #N` when `source === 'till'` (from `tillRedemption`), and a small "till" badge next to the type.
- `exceptions.blade.php` (new, copy the table styling from `vouchers/list.blade.php`): columns When (`sold_at`), Ticket #, Voucher (link to `vouchers.transactions` when `voucher_id`, else the `voucher_code` snapshot), Status pill (partial red, no_tender amber, inactive amber, unknown grey, refund blue), Voucher tender, Deducted, Shortfall, Ticket total, Note, Reviewed (name + time, or a "Mark reviewed" inline form with an optional note). A toggle link "Show reviewed" (`?all=1`). Empty state text. Pagination links.
- `admin.blade.php` inside the `vouchers.manage` block (l.302-318): add a "Till exceptions" link to `vouchers.exceptions`, active when `request()->routeIs('vouchers.exceptions')`.
Check: as a manager, `GET /vouchers/exceptions` renders (empty state); `GET /vouchers` shows the header link only when there are unreviewed exceptions; scanning an active code on `/vouchers` shows the history list and the collapsed manual deduct.

### 10. Shop view
Files: `resources/views/shop/vouchers.blade.php`, `resources/js/shop/vouchers.js`
What: put the numpad column and the deduct action behind a `manualOpen` flag (a `shop-btn--ghost` "Manual deduct" button in the left column, visible when `mode === 'active'`; the numpad column and the Deduct button gain `&& manualOpen` on their `x-show`). Activation (`activating`) is unaffected. Keep the "Use full balance" button in markup via `x-show` (the test asserts its text). History meta already prints `t.user`, so till rows read "Till #430025" with no template change; add a `who(t)` helper returning `t.source === 'till' ? 'Till #' + t.ticket_number : t.user` and use it in the meta line. Reset `manualOpen = false` in `onScan()`. Add no `route()` calls to these files.
Check: `php artisan test --filter=ShopVouchersTest` and `--filter=ConfinePinSessionTest` pass; on `/shop/vouchers`, an active voucher shows balance + history first and the numpad only after tapping "Manual deduct".

### 11. Tests
Files: `tests/Concerns/CreatesVoucherPosTables.php` (new), `tests/Feature/VoucherPosProductServiceTest.php` (new), `tests/Feature/VoucherTillSyncServiceTest.php` (new), `tests/Feature/VoucherTillExceptionsTest.php` (new), `tests/Feature/Shop/ShopVouchersTest.php`
What:
- Trait (pattern: `CreatesLegacyDeliveryPosTables`): sqlite `pos` tables `PRODUCTS` (ID pk, NAME unique, CODE unique, REFERENCE, CATEGORY, TAXCAT, PRICEBUY, PRICESELL, DISPLAY, ISSERVICE), `CATEGORIES` (ID, NAME unique, PARENTID, CATSHOWNAME), `PRODUCTS_CAT` (PRODUCT, CATORDER), `TICKETS` (ID, TICKETID, TICKETTYPE, PERSON, CUSTOMER, STATUS), `RECEIPTS` (ID, MONEY, DATENEW string), `TICKETLINES` (TICKET, LINE, PRODUCT, UNITS, PRICE, TAXID, ATTRIBUTES), `TAXES` (ID, RATE), `PAYMENTS` (ID, RECEIPT, PAYMENT, TOTAL, TENDERED, TRANSID, NOTES, CARDNAME); helper `posSale(int $ticketNo, array $lines, array $payments, Carbon $at, int $type = 0): string` returning the ticket id.
- `VoucherPosProductServiceTest`: category created once as a child of the configured parent with `CATSHOWNAME 0`; product fields (NAME format, CODE/REFERENCE = code, CATEGORY, TAXCAT `000`, PRICESELL 0, ISSERVICE 1); no `PRODUCTS_CAT` row; `pos_product_id` saved; idempotent; CODE self-heal when `pos_product_id` is null but the product exists; rename on balance change (`sync` twice with different balances → NAME updated); POS failure (drop `PRODUCTS` first) returns null, logs, voucher untouched; `POST vouchers.generate.store count=3` → 3 products; `activate` of an unknown code → product created with `[bal €25.00]`.
- `VoucherTillSyncServiceTest`: applied (50 − 30 → 20; transaction `source=till`, `user_id` null, note `Till #430025`; POS NAME renamed to `[bal €20.00]`); partial (20 vs 30 → 0, `exhausted`, shortfall 10, NAME `[€0.00 used up]`); no_tender (GV line + cash only); inactive / deactivated / exhausted → `inactive`, no change; unknown product in the voucher category; code self-heal; idempotent on a second run and inside the overlap window; double scan → one row; two vouchers on one ticket split in LINE order with `partial` on the last; refund ticket → `refund`; watermark ignores tickets older than `max(sold_at) − overlap`; empty table uses `lookback_hours`; rounding (e.g. tender 10.005).
- `VoucherTillExceptionsTest`: employee `GET /vouchers/exceptions` → 403; manager → 200 with ticket #, status and amounts; `POST …/reviewed` sets `reviewed_at`/`reviewed_by`; reviewed rows hidden unless `?all=1`; `/vouchers` header count for managers; `artisan vouchers:sync-till` exits 0 and prints the table.
- `ShopVouchersTest`: in the history test add a till `deduct` transaction plus its redemption row and assert `history.0.user === 'Till #430025'`, `history.0.source === 'till'`, `history.0.label === 'Redeemed at till'`; in `setUp` add `Config::set('vouchers.sync.on_lookup', false)`.
Check: `php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession'` → all pass.

### 12. Docs
Files: `docs/features/voucher-management.md`, `docs/FEATURES_INDEX.md`, `CLAUDE.md`, `docs/finance_manager/database-schema.md`
What:
- `voucher-management.md`: new "Till redemption" section: cashier steps (ring up goods → scan the GV label, a €0.00 line "Gift Voucher GV… [bal €42.50]" appears, read the balance → Pay → Voucher tab → enter the amount to take from the voucher (at most the balance shown) → finish; the app deducts within a minute or on the next voucher scan), what each exception status means and where to review them, that manual deduct is the fallback, the POS product (hidden category, `ISSERVICE`, NAME carries the balance), `vouchers:sync-pos-products` and `vouchers:sync-till`, the new table/columns, the two new routes, and follow-ups (cash-rec `voucher_used`, `TillTransactionRepository` first-payment bug, auto-activation).
- `FEATURES_INDEX.md` (~l.587) and `CLAUDE.md` voucher line: mention till redemption via the uniCenta Voucher tender, auto-synced, with a manager exceptions list.
- `database-schema.md` l.138-162: `PAYMENTS` has `NOTES` (there is no `VOUCHER` column); real payment types seen in the live table: cash, magcard, free, debt, paperin (gift voucher redemption), debtpaid, cheque, cashrefund, magcardrefund, bank, cashout.
Check: files updated; `grep -n "sync-till" docs/features/voucher-management.md` finds the command.

## Verification

1. `./vendor/bin/pint --dirty` → no changes needed.
2. `php artisan migrate:fresh --seed` on a scratch sqlite is NOT required; run `php artisan migrate` → clean.
3. `php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession|RoutePermissions'` → all pass.
4. `php artisan test` → no new failures versus the 15-failure baseline (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2, TestScraper ×1). Paste the summary line.
5. `php artisan schedule:list` → includes `* * * * *  php artisan vouchers:sync-till`.
6. `php artisan vouchers:sync-pos-products` against the dev POS copy → creates one product per voucher; `SELECT NAME, CODE, CATEGORY, PRICESELL, ISSERVICE FROM PRODUCTS WHERE CODE LIKE 'GV%'` (tinker) shows names ending `[bal €…]` / `[not active]`, `PRICESELL 0`, `ISSERVICE 1`, and no rows in `PRODUCTS_CAT` for them.
7. Simulated till sale on dev (tinker inserts into TICKETS/RECEIPTS/TICKETLINES/PAYMENTS with `DATENEW = now()`: one goods line, one GV line, one `paperin` payment of 5.00 against an active voucher): `php artisan vouchers:sync-till` → `applied 1`; `POST /vouchers/lookup` for that code shows the reduced balance and a history row `Redeemed at till · Till #<n>`; the POS product NAME shows the new balance. Repeat with a tender above the balance → `partial` row visible on `/vouchers/exceptions`; mark it reviewed. Clean the simulated rows up afterwards (record the ids in `implemented.md`).
8. Browser: `/vouchers` (office) and `/shop/vouchers` as an employee — scan an active code, see balance + history, open "Manual deduct", deduct €1, see the new row; check the console for errors.

## Risks

- The till prints product names on the customer receipt, so the receipt will show the balance *before* this sale (e.g. `[bal €42.50]` on a sale that used €20). The wording lives in `config('vouchers.pos_name_format')` / `pos_balance_text`; the owner can change it without code.
- The name is only as fresh as the last sync (≤ 1 minute, or the last app lookup). Two uses of one voucher within a minute at two tills can show a stale balance; the exceptions page catches the over-tender.
- Garbage tender (a real 5.39-trillion `paperin` exists in history): becomes `partial` with a huge shortfall; `ticket_total` on the row makes it obvious.
- Voucher scanned, paid by cash/card → `no_tender`, balance untouched; cashier training is the control.
- `PRODUCTS.NAME` is unique: the code is inside every name, so renames cannot collide. A rename while the POS is unreachable is skipped and caught up on the next sync or `vouchers:sync-pos-products --all`.
- GV products will appear in the app's product search and as 0-value lines in `SalesImportService`; harmless, follow-up if noisy.
- Lookup latency when the POS host is unreachable: throttled to one attempt per 5 s and caught, but a TCP connect timeout can still add seconds; `vouchers.sync.on_lookup=false` is the off switch.
- Production must run `schedule:run`; otherwise only the lookup hook syncs.
- Refund tickets are recorded, not reversed.

## Review

Reviewed 2026-09-27 (code and diff) and re-reviewed 2026-09-28 after the real-till test, notes 7-8 and the move to `docs/vouchers/`. The review body written on the 27th was lost in the move, so it is restated here in full. `implemented.md` read to the end (8 notes); every verification command rerun by the Planner on the 27th; the POS state re-checked on the 28th.

**Criteria**
- Step 1 config — pass. `config('vouchers.pos_category_name')` = `Gift Voucher Redemption`.
- Step 2 migrations — pass (three tables/columns exist; `migrate` clean).
- Step 3 models — pass. Constants, fillables, casts, relations and scopes as specified.
- Step 4 POS product service — pass. Create / link-by-code / rename paths, `ISSERVICE 1`, no `PRODUCTS_CAT`, failures logged and swallowed. Dev POS: category `482fba08-…` under `033` with `CATSHOWNAME 0`, GV products `PRICESELL 0`, `ISSERVICE 1`, zero `PRODUCTS_CAT` rows.
- Step 5 backfill command — pass (idempotent; `--all` renames).
- Step 6 till sync — pass. Watermark, category-filtered candidate query, LINE-ordered pool split, all six statuses, row-before-money inside `DB::transaction` with `lockForUpdate`, SQLSTATE 23000 → skipped, POS rename after commit. **Proven on a real uniCenta till on 2026-09-28:** #430813 `applied` (€20.00 → €8.81, till name `[bal €8.81]`), #430814 `partial` (€8.81 → €0.00, shortfall €8.18, `[€0.00 used up]`), the overlap re-read skipped #430813 with no double deduction, and the tender was stored as `paperin`.
- Step 7 command + schedule — pass. `schedule:list` shows `* * * * * vouchers:sync-till`; `ScheduleTest` pins 9 entries.
- Step 8 controller/routes — pass. `syncIfDue()` first in `lookup()`; history gains `source`/`ticket_number` and "Redeemed at till · Till #N"; POS rename after activate/deduct/status change; generate warns on POS failure; `exceptions`/`markReviewed` under `vouchers.manage`, placed before `{voucher}/transactions`.
- Step 9 office views — pass by rendering test and a `node --check` of the inline Alpine script (syntax clean, no Blade-clashing `@` shorthands). Alpine behaviour not exercised in a browser (owner action below).
- Step 10 shop view — pass. Numpad and actions behind `manualOpen`; activation unchanged; "Use full balance" kept in markup; no new `route()` calls (`ConfinePinSessionTest` green). Browser-checked by the implementer as a PIN user, console clean.
- Step 11 tests — pass. 36 new tests across three files plus the trait; `ShopVouchersTest` extended without removing assertions.
- Step 12 docs — pass. Feature doc has the cashier steps, exception table, follow-ups; schema doc corrected (`NOTES`, no `VOUCHER`, real payment types).

**Verification (rerun by the Planner, 2026-09-27)**
1. `pint --test` on the 19 changed PHP files → PASS.
2. `php artisan migrate` → nothing to migrate.
3. `php artisan test --filter='Voucher|ShopVouchers|Schedule|ConfinePinSession|RoutePermissions'` → 117 passed (397 assertions).
4. `php artisan test` → 15 failed, 797 passed; the 15 are the baseline classes (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2, Product ×2, TestScraper ×1). No new failures.
5. `php artisan schedule:list` → `vouchers:sync-till` every minute.
6. Dev POS products/category as above.
7. Simulated sales (implementer, cleaned up) and then two real till sales (2026-09-28, left in place: tickets #430813/#430814, redemptions 3 and 4, #430814 unreviewed).
8. Browser: shop screen done; office screens outstanding (owner).

**Deviations** — all five accepted.
1. `decimal(16,2)` for `voucher_tender`/`shortfall`: correct catch; the plan's own risk (a 13-digit `paperin` amount in live data) would not have fitted `decimal(14,2)` and the ticket would have retried forever.
2. Warning banner on `vouchers/print.blade.php`: without it the generate warning was invisible.
3. `lastAction()` on the product service: small, keeps `sync()`'s contract.
4. Explanatory `note` on non-applied rows, reviewer note appended: makes the exceptions page self-explaining.
5. `ShopVouchersTest` indices shifted, manual row back-dated: no assertion removed, ordering now deterministic.

**Notes for Planner**
1. Office pages unreachable from a PIN Chrome session — accepted as a protocol fact; the owner does the office checks (below). Future office-screen plans will say so up front.
2. Leftover tender not recorded when the last voucher line is not `applied` — deferred: only on a multi-voucher ticket whose last line is inactive/no_tender, and the row still reaches the exceptions page. Tidy candidate: put the remaining pool on the last row whatever its status.
3. Batch limit vs overlap window (more than 50 voucher tickets in 15 minutes would stall the watermark) — deferred: orders of magnitude above this shop's volume. Candidate: exclude already-recorded ticket ids in SQL.
4. Sync depends on the production scheduler — known (plan Risks); owner to confirm `schedule:run` is in production cron.
5. Out-of-scope items noted in the feature doc — deferred as planned.
6. Sidebar link without a count badge — deferred; the `/vouchers` header carries the count.
7. Real-till test — accepted; it is the strongest evidence in this cycle and is recorded under step 6 above.
8. The move to `docs/vouchers/` — accepted; archive path below updated accordingly. Nothing in the plan's content changed.

**Findings (`findings/2026-09-28-till-testing.md`)**
1. *Till keypad is numeric only.* Decision: **option 1, no code change.** The till already sells the letter-coded product `xxx999` (no till button) 2,195 times in 2026, so letters reach the barcode field from a keyboard or wedge scanner; a scanned CODE-128 GV label should work the same way. Owner to confirm with one printed label and the real scanner. If that fails, a follow-up cycle implements option 2 (numeric `CODE` on the POS product, label prints both). The dev-only numeric `CODE 2990000000019` on `GVVUF2SUACUM` stays on the dev POS copy; `numeric_barcode.php` must never run against production, and `vouchers:sync-pos-products --all` does not revert it (the sync only renames).
2. *Till accepts any Voucher tender.* Decision: **owner decision; the plan's decision 2 stands for now** (deduct to zero, `partial` on the exceptions page). Revisit only if over-tenders appear in practice; option 2 (a uniCenta-side check) would be its own research cycle and option 3 would change the finance treatment of `paperin`, which the Constraints forbid without the owner.
3. *Dev till writes UTC.* **Resolved, nothing to do.** Production data in the POS copy shows the same 08:00–18:00 sales shape in July (UTC+1) and January (UTC), so the live till writes Irish local time; the hour offset is the VirtualBox VM only (`@@system_time_zone = UTC`).

The finding file stays in `findings/` until the scanner check decides finding 1; it then moves into this cycle's archive folder (or a follow-up cycle's).

**Owner actions before relying on it in the shop**
- Deploy, `php artisan migrate --force`, `php artisan vouchers:sync-pos-products`, confirm `php artisan schedule:list` on production shows `vouchers:sync-till` and that cron runs `schedule:run`.
- Scan one printed GV label with the real scanner at the real till: the €0.00 line with `[bal €…]` must appear (finding 1).
- Browser check of the office screens as a manager: `/vouchers` scan an active code → balance, history, "Manual deduct" reveals the input, deduct €1 adds a row; `/vouchers/exceptions` shows #430814 and "Mark reviewed" works.

**Archive**: `mkdir -p docs/vouchers/archive/2026-09-27-cycle-28-till-voucher-redemption` and move `plan.md` and `implemented.md` there. The finding file follows once finding 1 is closed.
