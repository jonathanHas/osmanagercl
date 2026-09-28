# Till testing of cycle 28 (voucher redemption at the uniCenta till)

Date: 2026-09-28
Found by: Implementer (Opus), with the owner driving a real uniCenta till
Affects: cycle 28 (`docs/vouchers/plan.md`, `docs/vouchers/implemented.md`), not yet reviewed by the Planner

## Setup

- The owner's uniCenta runs in VirtualBox against the dev POS MySQL (`127.0.0.1:3307`, the same `pos` connection the app uses).
- No cron on the dev box, so `php artisan vouchers:sync-till` was run by hand after each sale.
- Voucher `GVVUF2SUACUM` (active, €20.00). Its till product was given the numeric barcode `2990000000019` (finding 1) using `docs/vouchers/scripts/numeric_barcode.php`.

## Results

| Test | Till ticket | Voucher tender | Sync output | Outcome |
|---|---|---|---|---|
| Voucher covers the sale | #430813 | €11.19 (whole sale) | `tickets 1, applied 1` | €20.00 → €8.81 active; history `Till #430813`; till name `[bal €8.81]` |
| Tender above balance | #430814 | €16.99 (whole sale) | `tickets 2, partial 1, skipped 1` | €8.81 → €0.00 exhausted; shortfall €8.18; note `Till #430814 · short €8.18`; till name `[€0.00 used up]`; 1 unreviewed exception |

- The €0.00 voucher line appeared on the till with the balance in its name, as designed.
- The second run re-read #430813 inside the overlap window and skipped it: no double deduction.
- The Voucher tender was stored as `paperin`, as the plan's Context said.
- Not yet checked in a browser: `/vouchers/exceptions` showing #430814 and "Mark reviewed" (needs a manager login; the Chrome session is a PIN session, and `ConfinePinSession` sends office pages to `/confirm-password`). Also not yet checked: scanning the numeric code again to see the `[€0.00 used up]` line on the till screen.

## Finding 1 — the till's on-screen keypad is numeric only

**What happened:** the owner could not key `GVVUF2SUACUM` on the till: the on-screen keypad has no letters.

**Why:** voucher codes are `GV` + 10 characters from `23456789ABCDEFGHJKLMNPQRSTUVWXYZ`, printed as CODE-128. A USB/Bluetooth scanner acts as a keyboard and types letters into the barcode field, so a scanned label *should* work, but this has not been tried at the real till with a real printed label.

**Workaround used:** the voucher's POS product `CODE` was changed to a numeric EAN-13 (`2990000000019`, in-store prefix 29, valid check digit), with `REFERENCE` still the GV code. The sync matches by `PRODUCTS.ID`, so it still worked.

**Options:**
1. Do nothing in code: confirm a real scanner reads a printed GV label into the till's barcode field. Staff key nothing by hand.
2. A numeric fallback on the product only: every voucher product gets a numeric `CODE` (e.g. `29` + 10 digits + check digit), and the label prints both the GV code and that number (e.g. the barcode encodes the number, the human-readable line shows both). The sync is unaffected. Changes: `VoucherPosProductService::sync()` (CODE), `Voucher::toZplLabel()`, the lookup screens must accept either code.
3. Numeric voucher codes throughout: change `Voucher::CODE_PREFIX`/`CODE_CHARSET`/`generateUniqueCode()` to 13-digit EAN with a check digit. Existing GV vouchers keep working by product id. Largest change; also affects the app's own lookup and labels.

**Recommendation:** try option 1 first (one printed label, one scanner, the real till). If it fails, or if hand-keying from a damaged label matters, option 2.

## Finding 2 — the till accepts any Voucher tender, whatever the balance

**What happened:** the till took €16.99 of Voucher tender against a voucher with €8.81 left. The app deducted €8.81 and flagged €8.18 as a shortfall (`partial`), as designed (owner decision 2). In a real sale that €8.18 is goods given away until a manager chases it.

**Why:** uniCenta has no idea of voucher balances; the product-name balance is information for the cashier, not a control.

**Options:**
1. Training + the exceptions page (current design).
2. A uniCenta script/event on the Voucher payment that reads the balance (e.g. from the voucher product's NAME, or a small lookup table) and refuses an over-tender. Needs research into uniCenta's scripting hooks; a separate cycle.
3. Make the voucher line carry value (e.g. a negative-price line for the voucher amount) instead of using the tender. Changes the finance treatment of `paperin`; the plan's Constraints say that must not change, so this needs the owner.

**Recommendation:** owner decision. Option 1 is live now; option 2 only if over-tenders show up on the exceptions page in practice.

## Finding 3 — the dev till writes times one hour behind the app

**What happened:** `now()` in the app was 13:52 (Europe/Dublin) while the POS MySQL `NOW()` was 12:52 and the till wrote `DATENEW 12:53:49` for a sale made at about 13:53 local. `sold_at` is stored as the raw `DATENEW`, and the model casts it in the app timezone, so the exceptions page would show the sale an hour early.

**Why:** the VirtualBox till/MySQL run on UTC (`time_zone = SYSTEM`); the app is Europe/Dublin.

**Effect:** display only, on this dev setup. The watermark compares `sold_at` with `DATENEW` (both till time), so matching is unaffected; the 24 h look-back floor is off by an hour, which does not matter.

**Recommendation:** check production: if the live till and POS MySQL write Irish local time (likely, as the KDS uses the same pattern), there is nothing to do. If not, it is a KDS-wide question, not a voucher one.

## Dev data left in place

- `GVVUF2SUACUM`: **exhausted, €0.00**; till product `CODE 2990000000019`, `REFERENCE GVVUF2SUACUM`, NAME `Gift Voucher GVVUF2SUACUM [€0.00 used up]`. Reset with `docs/vouchers/scripts/reset_voucher.php`.
- `GVLH4AU7ASAT`: active €20.00; till product `CODE GVLH4AU7ASAT` (not numeric yet).
- `voucher_till_redemptions`: id 3 (#430813 applied), id 4 (#430814 partial, **unreviewed**).
- Tickets #430813 and #430814 are real till sales in the dev POS; leave them (deleting them would not undo the local deductions).
