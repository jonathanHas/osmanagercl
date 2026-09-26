# Shop mode cycle 15 — Vouchers (screen 11)

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26 (unparked 2026-09-26 after the camera fix, cycle 16)

## Goal

Staff redeem gift vouchers from Shop mode: scan the voucher, see its balance and status, tap an amount on the number pad, see what will be left, and deduct. The history of the voucher (issued, redeemed, by whom, when) sits under the balance, as the design shows. Managers can also activate a scanned voucher with a starting balance, as on the office till screen. The existing endpoints do all the work; one of them gains an extra field.

## Context

Baseline: cycles 14, 14b and 14c accepted and archived; may still be uncommitted. Record `git status --short` first.

**Design.** `docs/design/shop-mode/screen-11-vouchers.html`: `shop-page` → `shop-split`; left `shop-stack`: the scan field (placeholder "Scan voucher", hint "Voucher found"), a `shop-card` with `shop-between` (`shop-label` "Balance", `shop-pill shop-pill--ok` "Active"), `shop-bignum` with `shop-bignum__cur` "€" then "42.50", `shop-meta` "Issued 12 Mar 2026 · valid until 12 Mar 2029"; then `shop-label` "History" and a `shop-list` of `shop-row`s (title "Redeemed"/"Issued", meta "2 Sep · Maya", aside `shop-row__qty` "−€7.50" / "+€70.00"). Right `shop-card`: `shop-numpad` with `shop-numpad__display` (`shop-label` "Deduct €", value "12.80"), keys 1–9, `shop-key--fn` ".", "0", `shop-key--fn` backspace icon; `shop-meta` "Remaining after: <strong>€29.70</strong>". Sticky `shop-actions`: primary "Deduct €12.80". The app has no expiry date, so the meta line shows "Issued {date} · {code}" instead of "valid until".

**Feature** (`docs/features/voucher-management.md`): statuses `inactive` / `active` / `exhausted` / `deactivated`; `vouchers.redeem` (employees) covers `vouchers.index`, `vouchers.lookup`, `vouchers.deduct`; `vouchers.manage` covers `vouchers.activate` and the rest; codes are `GV` + 10 chars, CODE-128. `VoucherController`:
- `lookup()` (POST `{ code }`) → `{ found: false }` or `{ found: true, code, status, initial_value, current_balance }`.
- `deduct()` (POST `{ code, amount }`) → 200 `{ success: true, status, new_balance }` or 422 `{ success: false, message[, current_balance] }`; row-locked; messages "Voucher not found." / "Voucher has not been activated." / "Voucher has been deactivated." / "Voucher is exhausted." / "Amount exceeds the remaining balance of €X.".
- `activate()` (POST `{ code, starting_balance }`, manage) → 200 `{ success, status, current_balance }` or 422 `{ success: false, message }`; creates an unknown code on the fly.
- Models: `Voucher` (`code`, `initial_value`, `current_balance` decimal:2, `status`, `created_by`, `transactions()`, `creator()`), `VoucherTransaction` (`type` issue/deduct/deactivate/activate, `amount`, `balance_after`, `note`, `user_id`, `user()`).
- Office till screen `resources/views/vouchers/index.blade.php`: modes idle/activate/active/exhausted/deactivated; employees scanning an unknown or inactive code see "Voucher not active — This voucher hasn't been activated. Please ask a manager."; managers get a starting-balance form; active shows balance, a deduct field, "Use full balance". It stays as it is.
- Dev DB: 2 vouchers, both active, 2 transactions. No voucher tests exist.

**Shop pieces.** `x-shop.scan-input` (emits `scan {code}`; its parser strips a `]C1` CODE-128 symbology prefix and otherwise passes alphanumeric text through, so a voucher scan arrives as the bare `GV…` code; listens for `shop-scan-done` / `shop-scan-error`), the numpad markup on `resources/views/shop/stock-scan.blade.php` lines 45–56 (`shop-numpad`, `shop-numpad__display`, `shop-key`, `shop-key--fn`, backspace icon) with its `key(k)` handler pattern in `stock-scan.js`, the toast block, the `shop-bignum` rule (`--shop-fs-num`), `shop-pill` tones, `shop-empty`. Home tile `vouchers` in `config/shop.php` → `vouchers.index`.

## Constraints

- Do not commit, push or deploy.
- `deduct()` and `activate()` are unchanged. `lookup()` changes **additively** only (new keys; existing keys untouched) so the office screen keeps working.
- Employees never see an activate control or the activate URL; the server already refuses them.
- Amounts are sent as decimal strings with two places; the server rounds and re-checks under lock, so the client's "Remaining after" is a preview, never the truth.
- Contract rules; design block byte-identical; app rules only under `APP ADDITIONS` (none expected).

## Out of scope

- Generating, printing, listing, deactivating vouchers (office, manager/admin).
- A physical-keyboard path for amounts (the scan input owns keyboard focus on the till PC; amounts are tapped).

## Steps

### 1. Lookup returns the history
Files: `app/Http/Controllers/VoucherController.php`, `tests/Feature/Shop/ShopVouchersTest.php (new, started here)`
What: in `lookup()`, when found, add `issued_at` (ISO string of the earliest `issue` transaction's `created_at`, or null) and `history`: the last 20 transactions newest first, each `{ type, label, amount, balance_after, user, at }` where `label` = Issued / Redeemed / Deactivated / Reactivated by type, `amount` is signed as a float (issue positive, deduct negative, others 0), `user` = the user's name or "Office" when null, `at` ISO. Load with `transactions()->with('user')->latest()->limit(20)`.
Check: test `lookup_includes_history_and_issued_at`: seed a voucher (factory or `Voucher::create`) with an `issue` transaction (+50, user A) and a `deduct` (−7.50, user B) → employee POST lookup → `found` true, `history[0].label` "Redeemed", `history[0].amount` −7.5, `history[0].user` B's name, `history[1].label` "Issued", `issued_at` not null, and the pre-existing keys unchanged.

### 2. Route, controller, tile
Files: `app/Http/Controllers/Shop/VouchersController.php (new)`, `routes/web.php`, `config/shop.php`
What: `VouchersController@index` → `view('shop.vouchers', ['canActivate' => $request->user()->hasPermission('vouchers.manage')])`; route in the `shop` group `GET /vouchers` → `shop.vouchers`, `permission:vouchers.redeem`. Tile `vouchers` → `'route' => 'shop.vouchers'`.
Check: `php artisan route:list --name=shop.vouchers` → `vouchers.redeem`; `ShopHomeTest` green (a tile assertion may need the new route; say so).

### 3. Behaviour: `vouchers.js`
Files: `resources/js/shop/vouchers.js (new)`, `resources/js/shop.js`
What: `Alpine.data('shopVouchers')` reading `lookupUrl`, `deductUrl`, `activateUrl` (empty when not allowed) from `data-*`; csrf from the meta tag. State: `code = ''`, `voucher = null` (the lookup payload), `mode = 'idle'` ('idle' | 'active' | 'inactive' | 'deactivated' | 'exhausted' | 'unknown'), `typed = ''`, `busy = false`, `toast = null`.
- `onScan(code)`: `busy`; POST `lookupUrl` `{ code }`; not found → `voucher = null`, `mode = 'unknown'`; found → `voucher = data`, `mode = data.status` (inactive/active/exhausted/deactivated); `typed = ''`; `code = data.code ?? code`; dispatch `shop-scan-done` for any successful lookup (found or not; the screen explains the state), `shop-scan-error` with "Could not look up voucher" on a network error.
- Getters: `balance` (`Number(voucher?.current_balance ?? 0)`), `amount` (`typed === '' ? 0 : Number(typed)`), `remaining` (`Math.max(0, balance − amount)`), `canDeduct` (`mode === 'active' && amount > 0 && amount <= balance + 0.0001`), `canActivate` (`!! activateUrl && (mode === 'unknown' || mode === 'inactive') && amount > 0`), `needsManager` (`! activateUrl && (mode === 'unknown' || mode === 'inactive')`), `displayLabel` ('Starting balance €' when activating, else 'Deduct €'), `deductLabel` (`'Deduct €' + amount.toFixed(2)`), `activateLabel`, `statusPill` (`{ tone, text }`: active → ok "Active"; inactive/unknown → warn "Not active"; deactivated → bad "Deactivated"; exhausted → muted "Exhausted"), `issuedText` ("Issued {d M Y} · {code}" or the code alone), `history` (`voucher?.history ?? []`).
- `key(k)`: `'backspace'` → drop the last char; `'.'` → append only if none yet and length < 6; digit → append if the result keeps ≤ 2 decimals and ≤ 7 chars total; never a leading `.` (prefix `0`).
- `useFull()`: `typed = balance.toFixed(2)`.
- `deduct()`: guard `canDeduct`; `busy`; POST `deductUrl` `{ code, amount: amount.toFixed(2) }`; success → `voucher.current_balance = data.new_balance`, `voucher.status = data.status`, `mode = data.status`, `typed = ''`, toast ok `Deducted €X · €Y left` (Y = new balance), then re-run the lookup silently to refresh `history`; 422 → toast bad `data.message`, and if `data.current_balance` is present update the balance; network → toast bad "Could not deduct".
- `activate()`: guard `canActivate`; POST `activateUrl` `{ code, starting_balance: amount.toFixed(2) }`; success → re-run lookup (so history and status come from the server), toast ok `Activated with €X`; 422 → toast bad `data.message`.
- `format(n)`: `'€' + Number(n).toFixed(2)`; `signed(n)`: `(n < 0 ? '−' : '+') + format(Math.abs(n))` using the typographic minus as the design does; `when(iso)`: `d M` from the date. Toast helpers as elsewhere.
Check: `node --check`; node exercise with stubbed `$root`/`fetch`: lookup active 42.50 → `balance` 42.5, `statusPill.text` "Active"; `key('1')`,`key('2')`,`key('.')`,`key('8')`,`key('0')` → `typed` "12.80", `remaining` 29.7, `canDeduct` true; `key('.')` again ignored; `key('5')` ignored (third decimal); `typed = '50'` → `canDeduct` false; `useFull()` → "42.50", `canDeduct` true; deduct stub → `{ success: true, status: 'active', new_balance: 29.7 }` → toast text "Deducted €12.80 · €29.70 left", `typed` ""; employee with empty `activateUrl` after `mode = 'unknown'` → `needsManager` true, `canActivate` false; `signed(-7.5)` → "−€7.50"; `grep -c "route(" resources/js/shop/vouchers.js` → 0.

### 4. The screen
Files: `resources/views/shop/vouchers.blade.php (new)`
What: `<x-shop-layout title="Vouchers" :back="route('shop.home')">`, `<main class="shop-page" x-data="shopVouchers()" data-lookup-url="{{ route('vouchers.lookup') }}" data-deduct-url="{{ route('vouchers.deduct') }}" data-activate-url="{{ $canActivate ? route('vouchers.activate') : '' }}" @scan="onScan($event.detail.code)">` then `shop-split`:
- Left `shop-stack`: `<x-shop.scan-input placeholder="Scan voucher" hint="Ready — scan a voucher" />`.
  - Idle: `shop-empty` (`gift` icon, "Scan a voucher", "Its balance and history will show here.") `x-show="mode === 'idle'"`.
  - Balance card `x-show="voucher"` `x-cloak`: `shop-between` of `shop-label` "Balance" and `<span class="shop-pill" :class="'shop-pill--' + statusPill.tone" x-text="statusPill.text">`; `<span class="shop-bignum" :class="{ 'shop-bignum--bad': mode === 'deactivated' }"><span class="shop-bignum__cur">€</span><span x-text="balance.toFixed(2)"></span></span>`; `shop-meta` `x-text="issuedText"`.
  - State notes as `shop-card shop-card--flat` with an `alert` icon and `shop-meta`: `needsManager` → "Voucher not active. Please ask a manager."; `mode === 'deactivated'` → "This voucher has been deactivated."; `mode === 'exhausted'` → "Nothing left on this voucher."; `canActivate || (activateUrl && (mode === 'unknown' || mode === 'inactive'))` → "Not yet active. Enter the starting balance and activate." (use a getter `activating` for that last condition).
  - History `x-show="history.length"`: `shop-label` "History", `shop-list` with `<template x-for="t in history" :key="t.at + t.type">` → `shop-row` (title `t.label`, meta `when(t.at) + ' · ' + t.user`, aside `shop-row__qty` `x-text="t.amount ? signed(t.amount) : ''"`).
- Right `section.shop-card` `x-show="mode === 'active' || activating"` `x-cloak`: numpad as on Stock scan with the display `<span class="shop-label" x-text="displayLabel"></span><span x-text="typed || '0'"></span>`, keys 1–9, `shop-key--fn` "." (`key('.')`), "0", backspace; `<p class="shop-meta" x-show="mode === 'active'">Remaining after: <strong x-text="format(remaining)"></strong></p>`; a `shop-btn shop-btn--ghost` "Use full balance" `x-show="mode === 'active'"` `@click="useFull()"`.
- `shop-actions` `x-show="mode === 'active' || activating"`: when active `<button class="shop-btn shop-btn--primary shop-btn--lg" type="button" :disabled="! canDeduct || busy" @click="deduct()" x-text="deductLabel">`; when activating `<button … :disabled="! canActivate || busy" @click="activate()" x-text="activateLabel">` (only rendered when `$canActivate`, so employees never get the markup).
- Toast region as elsewhere.
Check: `php artisan test --filter=ShopViewContractTest` green (contract test picks the new screen up).

### 5. Tests
Files: `tests/Feature/Shop/ShopVouchersTest.php`
What (default DB only; no POS tables needed; users via the usual `userWith()` helper):
- `employee_can_open_the_vouchers_screen`: 200, `data-shell="shop"`, the scan input, `data-lookup-url`, `data-deduct-url`, `data-activate-url=""`, no "Activate", "Use full balance" present in markup.
- `manager_gets_the_activate_url`: `data-activate-url="` + `route('vouchers.activate')` and the word "Activate".
- `barista_is_forbidden`: 403.
- `lookup_includes_history_and_issued_at` (step 1).
- `deduct_and_refusals_through_the_existing_endpoint`: employee deducts 7.50 from a 42.50 voucher → 200, `new_balance` 35; deducts 100 → 422 with the "exceeds" message and `current_balance` 35; a deactivated voucher → 422 "deactivated".
- `home_tile_links_to_the_shop_screen`: `/shop` contains `route('shop.vouchers')`.
Check: `php artisan test --filter="ShopVouchersTest|ShopHomeTest"` green (6 tests).

### 6. Docs, README, format, build
Files: `docs/features/voucher-management.md`, `docs/design/shop-mode/README.md`, all touched
What: feature doc: a "Shop mode" paragraph (screen at `/shop/vouchers`, same endpoints, `lookup` now also returns `issued_at` and `history`); README bullet for Vouchers. `./vendor/bin/pint --dirty`; `npm run build`.
Check: `./vendor/bin/pint --test --dirty` clean; build succeeds.

## Verification

1. `php artisan route:list --name=shop.vouchers` → present, `vouchers.redeem`; `--name=vouchers` unchanged otherwise.
2. `php artisan test --filter="Shop|Voucher"` → green; the contract test lists the new screen.
3. `php artisan test` → 17 failed, the identical set; passed = 568 + 6.
4. `git diff app/Http/Controllers/VoucherController.php` → only `lookup()`, additive; `git diff --stat resources/views/vouchers/` → empty.
5. Contract greps; `grep -c "route(" resources/js/shop/vouchers.js` → 0; design block `cmp` identical.
6. `./vendor/bin/pint --test --dirty` clean; `npm run build` succeeds.
7. Manual, dev app (the camera works on dev once the browser is allowed to use it, per cycle 16; exercise every action, not just the render: tap the keys, tap Deduct, tap Use full balance), on one of the two active dev vouchers or a fresh one a manager activates for the purpose: scan (or type the code and Enter) → balance, Active pill, issued line, history; tap 1 2 . 8 0 → display "12.80", "Remaining after" correct, button reads "Deduct €12.80"; Deduct → toast, balance and history update, pad clears; tap an amount above the balance → button disabled; "Use full balance" → Deduct → pill "Exhausted", note shown, pad hidden; as an employee scan an unknown code → "Voucher not active. Please ask a manager." and no pad; as a manager the same scan → pad with "Starting balance €", Activate works; on the till PC the USB scanner's `]C1` prefix is stripped and the code resolves.

## Risks

- **Float display**: all money passes through `toFixed(2)`; the server rounds and re-validates under lock, so a client rounding quirk can only produce a 422, never an over-deduction.
- **Scanner prefixes**: CODE-128 scanners may emit `]C1`; the parser strips it. Some emit nothing; either way the bare code reaches lookup. If a scanner emits lowercase, lookup fails (codes are uppercase); the office screen has the same behaviour, so nothing new.
- **Employees and unknown codes**: the office shows "ask a manager"; the Shop screen does the same and never renders the activate markup, and the server enforces `vouchers.manage` regardless.
- **History size**: capped at 20; a voucher rarely has more than a handful.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end and the diffs of `VoucherController`, `config/shop.php`, `routes/web.php`, `shop.js`, the new Shop controller, `vouchers.js`, the view and the ten tests. Reran `php artisan test`: 17 failed / 579 passed, the identical pre-existing set (568 + 10 tests + 1 contract data set). Design block byte-identical; no app rules added; the office till view untouched; `lookup()` gained only additions.

**Steps 1–6: pass.** The screen follows the design; the pad, remaining-after preview, full-balance shortcut, refusal handling and manager activation all behave as specified, and the implementer walked the whole employee flow on a throwaway voucher it then deleted.

**Deviations.** `issued_at`/`history` built with their own queries because the relation is already ordered newest-first: **accepted, correct**. Ghost button at stack level rather than inside the card: **accepted**. Ten tests instead of six: **accepted**.

**Notes for Planner.**
1. The plan's 422 test would have tested nothing because the client guard runs first; the implementer simulated the real double-spend window instead: **accepted, and the lesson kept**: a server-refusal test must get past the client's own guard.
2. "Sept" for September from the Irish locale: **accepted**; consistent with the platform, one character off the mock.
3. `AuthenticationTest` flaked once in a full run: **noted**; a shared rate limiter is the likely cause; watch for an 18th failure.
4. The office deduct/activate endpoints had no tests before this cycle: **deferred**, a small housekeeping cycle.
5. Only one manager account on dev: **owner to check the activate path by hand** as a manager (scan an unknown or printed-but-inactive code, enter a starting balance, activate).
6. Second session in the tree: read correctly as the side fix; no conflict.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-15/`.
