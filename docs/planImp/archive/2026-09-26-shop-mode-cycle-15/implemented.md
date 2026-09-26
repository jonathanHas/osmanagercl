# Shop mode cycle 15 — Vouchers (screen 11) — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: 030e3e14 ("shop update fixed camera")

Pre-existing dirty files:
```
 D docs/planImp/parked/2026-09-26-shop-mode-cycle-15-vouchers/plan.md
?? docs/planImp/plan.md
```
Cycle 16's camera fix is committed at HEAD, as is the post-cycle-14c `pickResult`
fix, so the tree is otherwise clean — nothing in this cycle's diff will be someone
else's work.

Design block baseline:
```
$ head -529 resources/css/shop.css | cmp - docs/design/shop-mode/shop.css && echo IDENTICAL
IDENTICAL
```

Test baseline: 17 failed / 568 passed, the known set (cycle 16's report).

## Pre-flight: the plan's claims, checked

Everything the plan names exists and behaves as described.

`VoucherController` — `lookup()` returns exactly the five keys listed;
`deduct()` and `activate()` match the plan's contracts, including the 422
`current_balance` on an over-deduction. The five refusal messages are verbatim.

Every design class the plan uses is already in `shop.css`, so no `APP ADDITIONS`
are needed:
```
shop-bignum 2   shop-bignum__cur 1   shop-bignum--bad 1   shop-pill--ok 2
shop-pill--warn 2   shop-pill--bad 2   shop-pill--muted 1   shop-empty 2
shop-card--flat 1   shop-btn--ghost 2  shop-split 2   shop-stack--tight 1
shop-numpad 1   shop-key--fn 1   shop-row__qty 2
```
Icons `gift`, `alert`, `backspace` are all in `public/images/shop-icons.svg`.

`ShopHomeTest` asserts no route for the vouchers tile, so repointing it is safe.

Two things the plan does not mention that change how step 1 must be written:

1. **`Voucher::transactions()` already applies `->latest()`** (`app/Models/Voucher.php:49`).
   So the plan's `transactions()->with('user')->latest()` would order twice, and
   more importantly `issued_at` — "the *earliest* `issue` transaction" — cannot be
   had by adding `oldest()` to that relation, because a second `orderBy` appends
   rather than replaces. It needs `reorder()`, or its own query. I use its own
   query, which says what it means.
2. **`amount` and `balance_after` are cast `decimal:2`**, which Eloquent returns as
   *strings*. They must be cast to float on the way into the JSON or the client's
   arithmetic gets string concatenation. The plan asks for a float `amount`
   anyway; I do the same for `balance_after`.

There is **no `VoucherFactory`** (`database/factories/` has none), so the tests
build vouchers with `Voucher::create()`, which the plan allows.

## Steps

### 1. Lookup returns the history — done

Changed: `app/Http/Controllers/VoucherController.php` (additive: two new keys in
`lookup()`, and two private helpers), `tests/Feature/Shop/ShopVouchersTest.php (new)`.

`lookup()` now also returns `issued_at` and `history`. The existing five keys are
untouched, so the office till screen reads exactly what it read before.

Check output:
```
$ php artisan test --filter=ShopVouchersTest  (the endpoint tests; the screen tests
                                               come in step 2)
✓ lookup includes history and issued at
✓ lookup reports an unknown code
✓ history names the office when no user is recorded
✓ deduct and refusals through the existing endpoint
✓ employee may not activate
Tests:    5 passed (21 assertions)
```

Two notes on how this differs in detail from the plan's wording, neither a change
in behaviour:

- `issued_at` is its own query rather than `transactions()->…`, because that
  relation is declared `->latest()` and a second `orderBy` appends instead of
  replacing, so "earliest issue" could not be expressed on it without `reorder()`.
  A voucher that has been deactivated and reactivated has more than one `issue`
  row, which is why "earliest" matters at all.
- `history` drops the plan's `->latest()` for the same reason — the relation is
  already newest-first — and casts `balance_after` to float alongside `amount`,
  because `decimal:2` hands back strings.

One thing worth knowing for the client: `json_encode` writes a whole float as `50`,
so `history[n].amount` arrives as an integer when the amount has no fractional
part. That is the same value in JavaScript, so nothing needs to handle it, but the
test says `assertEquals` rather than `assertSame` there and explains why, so the
next person does not "fix" it.

### 2. Route, controller, tile — done

Changed: `app/Http/Controllers/Shop/VouchersController.php (new)`, `routes/web.php`,
`config/shop.php` (the `vouchers` tile now points at `shop.vouchers`).

Check output:
```
$ php artisan route:list --name=shop.vouchers -v
GET|HEAD  shop/vouchers . shop.vouchers › Shop\VouchersController@index
    ⇂ Illuminate\Auth\Middleware\Authenticate
    ⇂ App\Http\Middleware\PermissionMiddleware:vouchers.redeem

$ php artisan test --filter=ShopHomeTest
Tests:    12 passed (37 assertions)
```
`ShopHomeTest` needed no change: it asserts no route for the vouchers tile. The new
`home_tile_links_to_the_shop_screen` in `ShopVouchersTest` covers the repoint, and
also asserts the old office URL is no longer linked from Home.

### 3. Behaviour: `vouchers.js` — done

Changed: `resources/js/shop/vouchers.js (new)`, `resources/js/shop.js` (registers
`shopVouchers`).

Check output:
```
$ node --check resources/js/shop/vouchers.js   → parses
$ grep -c "route(" resources/js/shop/vouchers.js
0
```

Node exercise against the real module with `$root` and `fetch` stubbed — every
line the plan asks for, and the branches it does not:
```
ok   balance: 42.5                       ok   statusPill: {"tone":"ok","text":"Active"}
ok   typed: "12.80"                      ok   remaining: 29.7
ok   canDeduct: true                     ok   deductLabel: "Deduct €12.80"
ok   second point ignored: "12.80"       ok   third decimal ignored: "12.80"
ok   backspace: "12.8"                   ok   leading point becomes 0.: "0."
ok   over balance blocks deduct: false   ok   useFull: "42.50"
ok   useFull still deductible: true
ok   deduct posted amount: {"code":"GV23456789AB","amount":"12.80"}
     toast     : {"tone":"ok","text":"Deducted €12.80 · €29.70 left"}
ok   typed cleared: ""                   ok   refresh ran: 2
ok   balance refreshed: 29.7
ok   employee needsManager: true         ok   employee canActivate: false
ok   employee pill: {"tone":"warn","text":"Not active"}
ok   manager activating: true            ok   manager canActivate: true
ok   manager displayLabel: "Starting balance €"
ok   manager activateLabel: "Activate with €50.00"
ok   signed(-7.5): "−€7.50"              ok   signed(70): "+€70.00"
ok   exhausted pill: {"tone":"muted","text":"Exhausted"}   canDeduct false
ok   deactivated pill: {"tone":"bad","text":"Deactivated"} canDeduct false
     issuedText: Issued 12 Mar 2026 · GV23456789AB
```

**The 422 path needed a better test than the plan's.** Written as the plan implies
— look up a €35 voucher, type €100, deduct — nothing happens at all, because
`canDeduct` is already false and `deduct()` returns early. That is correct
behaviour, but it tests nothing. The refusal is only reachable when another till
spends the voucher between this lookup and this deduct, so that is what I
simulated: lookup says €100, the user types €80, the server answers 422 with the
true balance.
```
ok   client thinks it can deduct: true
     refusal   : {"tone":"bad","text":"Amount exceeds the remaining balance of €35.00."}
ok   balance corrected from 422: 35
ok   typed kept so the user can edit it: "80.00"
ok   now blocked: false
```
The screen corrects itself from the server's answer and the button goes dead
without the user having to rescan.

**One cosmetic difference from the design, deliberate.** The mock reads "2 Sep";
`toLocaleDateString('en-IE', { month: 'short' })` renders September as "Sept",
which is what modern ICU gives for Irish and British English and what a user here
would expect. Every other month matches the mock exactly ("12 Mar 2026",
"18 Jun"). I left the locale alone rather than hand-rolling a month table to save
one character in one month — say if you would rather have the mock's spelling.

### 4. The screen — done

Changed: `resources/views/shop/vouchers.blade.php (new)`.

Check output:
```
$ php artisan test --filter=ShopViewContractTest
Tests:    16 passed (198 assertions)      (14 screens before, 15 now — it picked
                                           the new one up on its own)
```

Two shapes differ slightly from the plan's wording, both so the design's own rules
keep working:

- The right-hand column is a `shop-stack` holding the numpad card and the "Use
  full balance" button, rather than the button living inside the card. The design
  puts `shop-btn--ghost` at stack level, and a button inside `shop-card` inherits
  the card's padding rules.
- The activate note and the activate button are wrapped in `@if ($canActivate)`
  as well as `x-show`, so an employee's HTML contains neither. The plan asks for
  this on the button; I did the same for the note, which mentions activation.

### 5. Tests — done

Changed: `tests/Feature/Shop/ShopVouchersTest.php`.

Ten tests rather than the plan's six. The four extra ones cover what the plan's
Risks worry about but its test list does not reach: an unknown code returns
exactly `{found: false}` and nothing else; a transaction with no user reads
"Office" rather than blank; a guest is redirected; and an employee POSTing
`vouchers.activate` directly is refused **and** no voucher row is created — the
plan says "the server already refuses them", and that is now asserted rather than
assumed.

Check output:
```
$ php artisan test --filter="ShopVouchersTest|ShopHomeTest"
   PASS  Tests\Feature\Shop\ShopVouchersTest
  ✓ employee can open the vouchers screen        ✓ manager gets the activate url
  ✓ barista is forbidden                         ✓ guest is sent to login
  ✓ home tile links to the shop screen           ✓ lookup includes history and issued at
  ✓ lookup reports an unknown code               ✓ history names the office when no user is recorded
  ✓ deduct and refusals through the existing endpoint
  ✓ employee may not activate
Tests:    22 passed (76 assertions)
```

### 6. Docs, README, format, build — done

Changed: `docs/features/voucher-management.md` (a "Shop mode screen
(`/shop/vouchers`)" section, the `shop.vouchers` row in the routes table, and the
new controller under Key files), `docs/design/shop-mode/README.md` (a Vouchers
bullet, including why the design's "valid until" is dropped).

Check output:
```
$ ./vendor/bin/pint --test --dirty
PASS   5 files

$ npm run build
public/build/assets/shop-BYs9Z6KG.css   43.61 kB │ gzip: 7.65 kB
public/build/assets/shop-C3ZfzFBB.js    26.75 kB │ gzip: 8.05 kB
✓ built in 9.34s
```

## Verification

**1. Routes**
```
$ php artisan route:list --name=shop.vouchers -v
GET|HEAD  shop/vouchers . shop.vouchers › Shop\VouchersController@index
    ⇂ App\Http\Middleware\PermissionMiddleware:vouchers.redeem

$ php artisan route:list --name=vouchers
Showing [13] routes      (12 before; the one new route is shop.vouchers, and the
                          12 office routes are unchanged)
```

**2. `php artisan test --filter="Shop|Voucher"`**
```
Tests:    179 passed (754 assertions)
```
The contract test went from 14 screens to 15, picking the new one up by itself.

**3. `php artisan test`**
```
Tests:    17 failed, 579 passed (2378 assertions)
```
17 failed, the identical known set (Udea ×7, CashReconciliation ×3, WasteLog ×2,
Product ×2, FruitVegLabelPrinting ×2, TestScraper ×1).

Passed is 579, not the plan's predicted 574. The difference is mine and benign:
the plan budgeted 6 tests and I wrote 10, and `ShopViewContractTest` gains a data
set per screen, so 568 + 10 + 1 = 579.

**4. Nothing existing was changed**
```
$ git diff --stat app/Http/Controllers/VoucherController.php
 1 file changed, 54 insertions(+)        ← insertions only, no deletions

$ git diff --stat resources/views/vouchers/
(empty — the office till screen is untouched)
```

**5. Contract**
```
$ grep -c "route(" resources/js/shop/vouchers.js        → 0
$ grep -rn "<script\|<style" resources/views/shop/ | wc -l → 0
$ head -529 resources/css/shop.css | cmp - docs/design/shop-mode/shop.css → IDENTICAL
```
No `APP ADDITIONS` were needed: every class the screen uses was already in the
design block.

**6. `pint --test --dirty` clean; `npm run build` succeeds.** Both above.

**7. Manual walkthrough — done, on the dev app, in the browser.**

Per cycle 16's note that dev can do more than the plans assumed. To avoid touching
the two real dev vouchers I created a throwaway one, `GVTESTCYC152`, walked the
whole flow on it, and **deleted it and its three transactions afterwards**; the dev
data is exactly as I found it (`GVLH4AU7ASAT` and `GVVUF2SUACUM`, both active at
€20.00, one transaction each). I actioned every control rather than checking that
it rendered.

- **Inactive voucher, as an employee** (the signed-in dev user `jonathanE` is an
  employee): balance card with a `Not active` pill, "Voucher not active. Please ask
  a manager.", and no number pad. `data-activate-url` is `""` in the DOM, so the
  activate markup is genuinely absent rather than merely hidden.
- **Active voucher**: €20.00 with a green `Active` pill, "Issued 21 Sept 2026 ·
  GVTESTCYC152", and one history row "Issued · 21 Sept · Office · +€20.00".
- **The pad**: tapped 7 · . · 5 · 0 → display "7.50", "Remaining after: €12.50",
  and the sticky button went live reading "Deduct €7.50".
- **Deduct**: balance became €12.50, a "Redeemed · 26 Sept · jonathanE · −€7.50"
  row appeared at the top of the history, and the pad cleared with the button back
  to a disabled "Deduct €0.00".
- **Use full balance** → "12.50", "Remaining after: €0.00" → **Deduct**: the pill
  became a muted `Exhausted`, the balance €0.00, "Nothing left on this voucher."
  appeared, the pad and the action bar disappeared, and the toast read
  "Deducted €12.50 · €0.00 left".
- **Unknown code** as an employee: the balance card disappears entirely and only
  "Voucher not active. Please ask a manager." remains.
- Console through all of it: one message, Alpine's own startup log. No errors.

**What the walkthrough did not cover.** The manager path (activate a scanned code)
needs a signed-in manager, and the only manager account on dev is `test`, whose
password I do not have and would not use. So activation is covered by the node
exercise and by two feature tests (`manager_gets_the_activate_url`,
`employee_may_not_activate`) but not by hand. The camera was not exercised either;
cycle 16's fix is what it is, and this screen uses the same component unchanged.

## Deviations

None in behaviour. Three points where I did more or differently than the letter of
the plan, all recorded in the steps above: `issued_at`/`history` are built with
their own query rather than on the `transactions()` relation (which is already
`->latest()`, so "earliest" was not expressible on it); the right-hand column is a
`shop-stack` holding the card and the ghost button rather than the button inside
the card; and the test file has 10 tests rather than 6.

## Files changed

Mine, this cycle:
```
 M app/Http/Controllers/VoucherController.php
 M config/shop.php
 M docs/design/shop-mode/README.md
 M docs/features/voucher-management.md
 M resources/js/shop.js
 M routes/web.php
?? app/Http/Controllers/Shop/VouchersController.php
?? resources/js/shop/vouchers.js
?? resources/views/shop/vouchers.blade.php
?? tests/Feature/Shop/ShopVouchersTest.php
```

**Not mine — a second session was working in this tree at the same time.** These
appeared while I was writing this report and belong to a parallel "delivery row"
cycle:
```
 M docs/planImp/planimp.md
 M resources/css/shop.css                     (3 rules appended under APP ADDITIONS:
                                               .shop-row--wrap, .shop-row__controls)
 M resources/views/shop/delivery-scan.blade.php
 M tests/Feature/Shop/ShopDeliveryTest.php
 D docs/planImp/parked/2026-09-26-shop-mode-cycle-15-vouchers/plan.md   (this cycle
                                               being unparked, by whoever unparked it)
?? docs/planImp/implemented-delivery-row.md
?? docs/planImp/plan-delivery-row.md
?? docs/planImp/plan-delivery-row-imp.md
```
Please read this cycle's diff as the first list only. **This cycle needed no CSS
change at all** — every class the vouchers screen uses was already in the design
block — so any `shop.css` diff you see is theirs. I re-ran the design-block `cmp`
after their change landed and it is still byte-identical, and I rebuilt so the
stylesheet on disk includes both cycles' work.

`public/build` is gitignored, so the built assets do not appear above and the
deploy must run the build. After the rebuild they are `shop-C3ZfzFBB.js` and
`shop-Cx0NKNHm.css` (the CSS hash changed from `shop-BYs9Z6KG` quoted in step 6
purely because their three rules landed between my two builds).

**Not committed, not pushed, not deployed**, per Constraints.

## Notes for Planner

1. **The plan's 422 test would not have tested anything.** As written — look up a
   €35 voucher, type €100, press Deduct — `canDeduct` is already false and
   `deduct()` returns before it posts. The refusal is only reachable when the
   balance changes between lookup and deduct, which is exactly the double-spend
   case the feature doc cares about, so that is what I simulated instead. Worth
   carrying into future plans: a test for a server refusal has to get past the
   client's own guard first, or it silently tests nothing.

2. **One cosmetic difference from the mock, my decision, easily reversed.**
   `toLocaleDateString('en-IE', { month: 'short' })` renders September as "Sept",
   where the mock says "Sep". Every other month matches. I kept the locale rather
   than hand-rolling a month table; say if the mock's spelling should win.

3. **`AuthenticationTest` failed once in a full-suite run and has not failed
   since.** It passes in isolation and passed in the two subsequent full runs, and
   nothing in this cycle touches auth, so I have recorded the suite result from a
   clean run. It looks like a shared rate-limiter or cache leaking between runs. I
   did not chase it; flagging it because "the identical 17" is the baseline every
   cycle checks against, and an intermittent 18th will confuse whoever hits it.

4. **`vouchers.deduct` and `vouchers.activate` had no tests at all before this
   cycle**, and now have coverage only incidentally, through a Shop test file. The
   office till screen depends on the same endpoints and its own behaviour is still
   untested. Not in scope here; worth its own small cycle.

5. **The dev estate has one manager account (`test`) and eleven employees.**
   Anything in a future plan that asks for a by-hand manager check will hit the
   same wall I did. Either the plan should say which account to use, or it should
   accept that manager paths are verified by test only.
