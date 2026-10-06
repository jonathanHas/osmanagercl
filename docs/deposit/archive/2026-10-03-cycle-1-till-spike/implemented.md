# Cycle 1 — Automatic bottle-deposit line on the test till (spike) — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-10-03

The database side is done, and the owner ran the till test (Verification 5)
on 2026-10-03: every row passed. The database evidence is in the "Till test"
section below.

## Baseline
HEAD: 36207bbf
Pre-existing dirty files:
```
?? docs/deposit/
```
(The whole `docs/deposit/` folder was untracked before this cycle: `README.md`,
`planimp.md`, `plan.md` are the Planner's. Mine are `implemented.md` and
everything under `docs/deposit/scripts/`.)

## Steps
### 1. Baseline and safety checks — done
Check output:
```
["127.0.0.1","3307","unicenta2016"]        host / port / database
RESOURCES=71
PRODUCTS=10653
TB md5=6310ac80cf46cbcf7f5e2c90e2f87625     md5(CONTENT) of Ticket.Buttons
ATTR>0=0                                    PRODUCTS with ATTRIBUTES
existing deposit resources / categories / DEP-* products: none
```
`SHOW COLUMNS FROM CATEGORIES`: NOT NULL columns are `ID`, `NAME` (unique),
`CATSHOWNAME` (default 1). Test products: `8711521947614` ID
`627ebc3b-93a1-11ea-bad5-10c37b4d894e`, `8714728001004` ID
`c6095864-abf9-11ef-bdaf-10c37b4d894e`; both ISSCALE 0, ISVPRICE 0.

### 2. Back up `Ticket.Buttons` — done
Changed: `docs/deposit/scripts/Ticket.Buttons.2026-10-03.orig.xml` (new);
POS `RESOURCES` row `Ticket.Buttons.pre-deposit` (RESTYPE 0).
Check output:
```
backup row inserted
backup md5=6310ac80cf46cbcf7f5e2c90e2f87625
6310ac80cf46cbcf7f5e2c90e2f87625  docs/deposit/scripts/Ticket.Buttons.2026-10-03.orig.xml
```
File and row both equal the step 1 md5. The file is plain ASCII, LF endings.
(Done first with a one-off scratch script; the same logic now lives as part 0
of the setup script, see Deviations.)

### 3. Rollback script first — done (not run)
Changed: `docs/deposit/scripts/deposit-spike-rollback.php` (new). Asserts the
dev connection, restores `Ticket.Buttons` from the backup row, deletes the two
script resources, sets `ATTRIBUTES = NULL` on the two test products, prints
what it did. Leaves products, category, `PRODUCTS_CAT` rows and the backup row.
Check output:
```
No syntax errors detected in docs/deposit/scripts/deposit-spike-rollback.php
```

### 4. Category and deposit products — done
Changed: `docs/deposit/scripts/deposit-spike-setup.php` (new), parts 1–3.
Category `Bottle Deposits` (`PARENTID` NULL, `CATSHOWNAME` 1). Products via
`Product::create` + `forceFill(['ISSERVICE' => 1])->save()`, as in
`VoucherPosProductService`.
Check output (read-back):
```
count=6
DEP-010 | Bottle deposit 0.10 | 0.1000 | TAXCAT 000 | ISSERVICE true | CAT c8936170-…
DEP-010-RET | Bottle deposit refund 0.10 | -0.1000 | TAXCAT 000 | ISSERVICE true | CAT c8936170-…
DEP-025 | Bottle deposit 0.25 | 0.2500 | TAXCAT 000 | ISSERVICE true | CAT c8936170-…
DEP-025-RET | Bottle deposit refund 0.25 | -0.2500 | TAXCAT 000 | ISSERVICE true | CAT c8936170-…
DEP-070 | Bottle deposit 0.70 | 0.7000 | TAXCAT 000 | ISSERVICE true | CAT c8936170-…
DEP-070-RET | Bottle deposit refund 0.70 | -0.7000 | TAXCAT 000 | ISSERVICE true | CAT c8936170-…
PRODUCTS_CAT=[{"PRODUCT":"38fe8fc8-…","CATORDER":1},{"PRODUCT":"d2ce3418-…","CATORDER":2},{"PRODUCT":"d3cb7179-…","CATORDER":3}]
category={"ID":"c8936170-3320-47e9-af5e-bf059c43b93a","NAME":"Bottle Deposits","PARENTID":null,…,"CATSHOWNAME":1}
```

IDs for cycle 2:

| What | CODE | ID |
|---|---|---|
| Category `Bottle Deposits` | — | `c8936170-3320-47e9-af5e-bf059c43b93a` |
| Bottle deposit 0.25 | `DEP-025` | `80184d44-d2ca-4a05-b243-3e5166612849` |
| Bottle deposit 0.70 | `DEP-070` | `efc616db-9852-448b-992e-744482c48d40` |
| Bottle deposit 0.10 | `DEP-010` | `c6f9016e-1a76-4977-ab75-1ac230126f78` |
| Bottle deposit refund 0.25 | `DEP-025-RET` | `38fe8fc8-3c2d-4ca1-a75e-02c07cf81a71` |
| Bottle deposit refund 0.70 | `DEP-070-RET` | `d2ce3418-0a49-43ff-a70f-7de3f8b6fd22` |
| Bottle deposit refund 0.10 | `DEP-010-RET` | `d3cb7179-dbc8-4771-b3ab-cdc0eea7b524` |

### 5. Mark the two test products — done
Changed: setup script part 4. Exact XML from plan Context (DOCTYPE, UTF-8, no
BOM, LF).
Check output (`simplexml_load_string` read-back):
```
8711521947614 {"deposit.id":"80184d44-d2ca-4a05-b243-3e5166612849","deposit.name":"Bottle deposit 0.25","deposit.price":"0.25"}
8714728001004 {"deposit.id":"efc616db-9852-448b-992e-744482c48d40","deposit.name":"Bottle deposit 0.70","deposit.price":"0.70"}
```
Extra check: the stored blob for the juice, loaded with Java's own
`Properties.loadFromXML` (JDK 17, offline):
```
{deposit.id=80184d44-d2ca-4a05-b243-3e5166612849, deposit.name=Bottle deposit 0.25, deposit.price=0.25}
```

### 6. Install the two till scripts — done
Changed: `docs/deposit/scripts/script.Deposit.AddLine.bsh` (new),
`docs/deposit/scripts/script.Deposit.Change.bsh` (new), setup script part 5.
Script text is the plan's, extracted verbatim from `plan.md`, with one change:
the em dash in each header comment replaced by `-` so the resources are pure
ASCII (no charset question when the till decodes the blob). Before writing
them I confirmed the methods the scripts call exist in the 3.91.3 class files
(`getProperty`, `setProperty`, `isProductCom`, `getMultiply`, `setMultiply`,
`getProductID` on `TicketLineInfo`; `addLine`, `insertLine`, `removeLine`,
`getLine`, `getLinesCount`, `getCustomer` on `TicketInfo`; `getTaxInfo` on
`TaxesLogic`) by name only (`javap` is not installed, so overloads such as the
two-argument `getProperty` were not checked; the Planner's decompile covers
them).
Check output:
```
java -cp …/lib/bsh-2.0b5.jar bsh.Parser docs/deposit/scripts/script.Deposit.AddLine.bsh   → exit 0, no output
java -cp …/lib/bsh-2.0b5.jar bsh.Parser docs/deposit/scripts/script.Deposit.Change.bsh    → exit 0, no output
negative control (`int x = ;`) → "Encountered ";" at line 1, column 9.", exit 1
script.Deposit.AddLine md5=a4dea667ba39d37025f6a9b5d03330b6   file a4dea667ba39d37025f6a9b5d03330b6
script.Deposit.Change  md5=0706d00f4aff6b8b6275a3a80eb550ba   file 0706d00f4aff6b8b6275a3a80eb550ba
```

### 7. Wire the events in `Ticket.Buttons` — done
Changed: setup script part 6, `docs/deposit/scripts/Ticket.Buttons.2026-10-03.deposit.xml`
(new). Built from the backup row (not the live resource), so re-runs are stable.
Check output:
```
$ xmllint --noout Ticket.Buttons.2026-10-03.deposit.xml   → ok
$ diff Ticket.Buttons.2026-10-03.orig.xml Ticket.Buttons.2026-10-03.deposit.xml
98a99,100
>         <event key="ticket.addline" code="script.Deposit.AddLine"/>
>         <event key="ticket.change" code="script.Deposit.Change"/>
Ticket.Buttons md5=e202b0e0878fda86aaf847f221626409   file e202b0e0878fda86aaf847f221626409
```

### 8. Hand-over for the till test — done
The "Till test" section below.

## Till test (owner)

**Restart the till in the VM first** (it reads `Ticket.Buttons` and the
scripts only at start-up). Use a cash sale; void or pay each ticket. Any
"cannot execute" dialog is a failure: note the exact message. If scanning
breaks, run the rollback and restart the till:
`php artisan tinker --execute="require 'docs/deposit/scripts/deposit-spike-rollback.php';"`

Variant under test: the plan's default (AddLine adds both lines and returns
"handled"; the deposit sits directly under its product).

| # | Action | Expected | Observed |
|---|---|---|---|
| a | Scan `8711521947614` (juice) | Two lines: the juice, then "Bottle deposit 0.25" ×1 directly under it; total includes 0.25 | Pass (owner) |
| b | Scan the juice again | A second juice line followed by its own deposit line (four lines) | Pass (owner) |
| c | Scan `8714728001004` (buttermilk) | Buttermilk line, then "Bottle deposit 0.70" under it | Pass (owner) |
| d | Scan any product without a deposit (e.g. a tea) | One line, nothing added | Pass (owner) |
| e | Select a juice line, press + (quantity 2) | Its deposit line becomes ×2 after the repaint | Pass (owner) |
| f | Select a juice line and delete it | Its deposit line disappears with it | Pass (owner) |
| g | Select a deposit line and delete it | It comes back (deposit is mandatory) | Pass (owner) |
| h | Pay the ticket by cash; print the receipt | Receipt shows the deposit lines under their products; `TICKETLINES` has rows with `PRODUCT` = the `DEP-*` id and `ATTRIBUTES` containing `deposit.for`; no `STOCKDIARY` row for the deposit product | Pass (owner) |
| i | New ticket: tap "Bottle deposit refund 0.25" from the Bottle Deposits category, alone | A −0.25 line; the payment screen offers a cash refund and the ticket closes | Pass (owner) |
| j | New ticket: juice + "Bottle deposit refund 0.25" | Total = juice + 0.25 − 0.25 | Pass (owner) |
| k | Type a quantity (e.g. `3` then `x`) and scan the juice | Juice ×3 and deposit ×3 | Pass (owner) |
| l | After all of the above, scan products on a till restart with no deposit property | Unchanged behaviour; no dialogs | Pass (owner) |

Owner's report (2026-10-03): "I've run the tests - all look good on my side".
Every row passed by the owner's observation. The tickets that were saved
(the others were voided, so they left nothing in the database) confirm h and i:

```
ticket a85aea3c (13:12:25, paid by magcard 7.40):
  L0 juice x1 @1.5041 | L1 DEP-025 x1 @0.25 | L2 juice x1 @1.5041 | L3 DEP-025 x1 @0.25
  L4 buttermilk x1 @2.5 | L5 DEP-070 x1 @0.7
  deposit lines: TAXID 000, ATTRIBUTES deposit.for = 627ebc3b-… (juice) / c6095864-… (buttermilk)
ticket 8dcef97c (13:14:03, standalone refund, paid cash -1.05):
  L0 DEP-025-RET x1 @-0.25 | L1 DEP-070-RET x1 @-0.7 | L2 DEP-010-RET x1 @-0.1
STOCKDIARY rows for any DEP-* product: 0
```

So: each deposit line sits directly under its product and records which
product it belongs to; the deposit lines create no stock movement; a ticket
with only refund lines closes with a negative cash payment. Row h was paid by
card, not cash as the plan said; that doesn't change the check.

## Deviations

1. **Backup folded into the setup script.** Step 2 names no script; the
   Constraints require every write to go through a re-runnable script in
   `docs/deposit/scripts/`. The backup was first made by a one-off scratch
   script (output in step 2), and the same logic is now part 0 of
   `deposit-spike-setup.php` (skips when the row exists; refuses if
   `Ticket.Buttons` is already wired but no backup row exists; checks the
   `.orig.xml` file matches the row). Same result, re-runnable.
2. **Em dash removed from the two `.bsh` header comments** (step 6), for an
   all-ASCII resource. Comment-only; the code is the plan's text unchanged.
3. **Verification 6 ran now as well** (before the owner's till test), as a
   guard right after the writes. It should be run again after the till test
   as the plan says.

## Verification

1. `php -l` on both PHP scripts → `No syntax errors detected` (both).
2. Setup run twice. Run 1 created everything (category, six products, three
   catalogue rows, two attributes, two scripts, wiring; backup already present
   from step 2). Run 2:
   ```
   backup row            already present, md5 6310ac80cf46cbcf7f5e2c90e2f87625
   backup file           already present, matches the row
   category              already present c8936170-3320-47e9-af5e-bf059c43b93a
   product DEP-025 … DEP-010-RET   already present (same six ids)
   catalogue DEP-025-RET / DEP-070-RET / DEP-010-RET   already present
   attributes 8711521947614 / 8714728001004   already present
   script.Deposit.AddLine / script.Deposit.Change   already present (same md5s)
   Ticket.Buttons        already wired, md5 e202b0e0878fda86aaf847f221626409
   ```
   Idempotent. (After these runs I only widened the label column of the
   output, `str_pad` 22 → 26; no logic change.)
3. `bsh.Parser` on both `.bsh` files → exit 0, no `ParseException` (step 6).
4. Database read-backs → six products, three `PRODUCTS_CAT` rows, two parsing
   `ATTRIBUTES` blobs, both script resources match their files, `Ticket.Buttons`
   matches `.deposit.xml`, backup row intact at the original md5 (steps 4–7).
   Counts now: `RESOURCES=74` (71 + backup + 2 scripts), `PRODUCTS=10659`
   (+6), `ATTR>0=2`.
5. Owner's till test → **all rows pass** (owner, 2026-10-03); database evidence for h and i in the Till test section.
6. `php artisan test --filter=Voucher` → `Tests: 143 passed (731 assertions)`
   before the till test, and again after it: `Tests: 143 passed (731 assertions)`.

## Files changed
```
?? docs/deposit/        (pre-existing untracked folder)
```
Inside it, mine: `implemented.md`, `scripts/deposit-spike-setup.php`,
`scripts/deposit-spike-rollback.php`, `scripts/script.Deposit.AddLine.bsh`,
`scripts/script.Deposit.Change.bsh`, `scripts/Ticket.Buttons.2026-10-03.orig.xml`,
`scripts/Ticket.Buttons.2026-10-03.deposit.xml`. No application code. No
commit. POS writes: dev database only, tables `RESOURCES`, `CATEGORIES`,
`PRODUCTS`, `PRODUCTS_CAT` as listed above.

## Notes for Planner

1. **AddLine skips the till's `ticket.change` after a scan.** When the script
   returns non-null, `addTicketLine` skips its own `executeEvent(…
   "ticket.change")` as well as `visorTicketLine`/`printPartialTotals`. The
   refresh inside `executeEventAndRefresh` covers the totals. Harmless today
   (Change would find nothing to fix), but worth knowing if anything else is
   ever wired to `ticket.change`.
2. **The `Change` script's pass 1 does not re-price.** If the owner changes a
   product's `deposit.price` while a ticket is open, existing deposit lines
   keep the old price. Fine for the spike; cycle 2 might sync price too.
3. **Rollback leaves the refund buttons in the catalogue** (by design, per
   step 3). If the owner wants the till exactly as before, the three
   `PRODUCTS_CAT` rows would need removing too.
4. **Deposit on a scale or variable-price product** is untested; both test
   products are plain units. `line.getMultiply()` for a weighed item would be
   a weight, giving a fractional deposit quantity. Cycle 2 should refuse to
   mark scale products or decide what a deposit means for them.
