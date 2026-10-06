# Cycle 1 — Automatic bottle-deposit line on the test till (spike)

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-10-03

## Goal

Prove, on the dev POS database and the VirtualBox test till, that scanning a
product the app has marked as deposit-bearing adds a "Bottle deposit" line to
the ticket automatically, that the deposit follows the product when its
quantity changes or it is removed, and that a bottle return can be refunded
with a negative-price product. No cashier knowledge is needed: the owner's
decision (2026-10-03) is the automatic approach, deposits passed on at cost,
zero-rated. This cycle touches only the dev POS database. Everything the app
will later do (cycle 2) is done here by hand so the till behaviour is known
before any Laravel code is written.

## Context

### The test till

- VirtualBox VM "Arch Shop" (guest hostname `archlinux`, NAT, no port
  forwarding). It runs uniCenta oPOS **3.91.3** from the shared folder
  `/home/jon/VirtualBox VMs/vb_arch_share/unicenta/` (`unicentaopos.jar`,
  `lib/bsh-2.0b5.jar`, `start.sh`). The jar is the exact binary; the Planner
  decompiled it to establish the facts below.
- The till uses the **dev POS database**: schema `unicenta2016` in the
  `mysql57` Docker container on `127.0.0.1:3307` (the VM reaches it as
  `10.0.2.2:3307`). The app's `pos` connection points at the same database
  (`.env`: `POS_DB_HOST=127.0.0.1`, `POS_DB_PORT=3307`). It is a production
  snapshot from 2026-08-14 plus test tickets (last on 2026-09-28, the voucher
  test). The owner restarts the till inside the VM; nobody can drive its
  screen from this machine.
- The till reads `Ticket.Buttons` once at start-up and caches resources, so
  **restart the till after every change to `RESOURCES`**.

### How uniCenta 3.91.3 runs event scripts (from the decompiled jar)

`JPanelTicket.addTicketLine(TicketLineInfo oLine)`:

```java
if (executeEventAndRefresh("ticket.addline", new ScriptArg("line", oLine)) == null) {
    m_oTicket.addLine(oLine); m_ticketlines.addTicketLine(oLine);
    ... visorTicketLine(oLine); printPartialTotals(); stateToZero();
    executeEvent(m_oTicket, m_oTicketExt, "ticket.change");      // NOT refreshed
}
```

- The script sees `line` (the `TicketLineInfo` about to be added), `ticket`
  (`TicketInfo`), `sales` (a `ScriptObject` with `getSelectedIndex()` /
  `setSelectedIndex(int)`), `taxes`, `taxeslogic` (`TaxesLogic`, with
  `getTaxInfo(String taxcatId, CustomerInfoExt customer)`), `user`,
  `dbURL`/`dbUser`/`dbPassword`, `hostname`, `taxesinc`.
- `executeEventAndRefresh` evaluates the script, then calls `refreshTicket()`
  (which rebuilds the on-screen lines from `ticket`, calls
  `printPartialTotals()` and `stateToZero()`), then restores the selected
  index from `sales`. **Only then** is the return value tested: `null` lets
  the till add `line` itself; anything else means "handled" and the till adds
  nothing more.
- A script that throws shows a "cannot execute" dialog and returns a non-null
  `MessageInf`, which on `ticket.addline` **cancels the scan**. Every script
  in this plan is wrapped in `try/catch` and never blocks a sale.
- `paintTicketLine` (quantity edits) fires `ticket.setline` then
  `ticket.change`; `removeTicketLine` fires `ticket.removeline` then
  `ticket.change`. Both `ticket.change` calls are refreshed. The one after a
  plain add is not, which is why the deposit line must be added inside the
  `ticket.addline` script, not in `ticket.change`.
- Events are declared in the `Ticket.Buttons` resource as
  `<event key="ticket.addline" code="<resource name>"/>`; one resource per
  key (`JPanelButtons.events` is a map). The current `Ticket.Buttons` on dev
  declares only `ticket.close` → `Ticket.Close`; the other event lines are
  commented examples.
- `TicketLineInfo(String productid, String productname, String taxcategory,
  double units, double price, TaxInfo tax)` builds a line that is saved to
  `TICKETLINES` with `PRODUCT = productid`. Line properties come from the
  product's `ATTRIBUTES` (cloned onto the line in
  `addTicketLine(ProductInfoExt, …)`) and are saved in
  `TICKETLINES.ATTRIBUTES`. `DataLogicSales` writes a stock movement for every
  line unless `line.isProductService()` (property `product.service` = `true`).
- `PRODUCTS.ATTRIBUTES` is a `java.util.Properties` XML blob read with
  `Properties.loadFromXML` (`ImageUtils.readProperties`). No product on dev
  has one today (count 0). Format:

  ```xml
  <?xml version="1.0" encoding="UTF-8" standalone="no"?>
  <!DOCTYPE properties SYSTEM "http://java.sun.com/dtd/properties.dtd">
  <properties>
  <comment>osmanager deposit</comment>
  <entry key="deposit.id">00000000-0000-0000-0000-000000000000</entry>
  <entry key="deposit.name">Bottle deposit 0.25</entry>
  <entry key="deposit.price">0.25</entry>
  </properties>
  ```
- The till never merges repeated scans: each scan appends a new line.
- `TicketInfo`: `getLinesCount()`, `getLine(i)`, `addLine(l)`,
  `insertLine(i, l)`, `removeLine(i)`, `getCustomer()`.
- The bundled `event.change` template proves that scripts may insert and
  remove lines on these events in this version.

### Existing patterns on this till

- Cup deposit: `4076` "2GoCup Cup" (+2.00) and `4078` "Cup Discount" (−0.40),
  category `081`, TAXCAT `000`. Negative-price products are proven here (143
  "Cup Discount" lines in 90 days). Payment of a negative total goes through
  `JPaymentSelect`'s refund creators (`cashrefund`, `paperout`, …); whether
  the cashier flow is acceptable is a test item below.
- Tax categories: `000` Tax aZero (0%), `001` 13.5%, `002` 23%, `003` 9%.
- The app writes POS products through Eloquent (`App\Models\Product`,
  connection `pos`) in `app/Services/VoucherPosProductService.php`: `create`
  with `ID` uuid, `CODE`, `REFERENCE`, `NAME`, `CATEGORY`, `TAXCAT`,
  `PRICESELL`, `PRICEBUY`, then `forceFill(['ISSERVICE' => 1])->save()`.
  Copy that shape. `CATEGORIES`, `PRODUCTS_CAT` and `RESOURCES` have no
  Eloquent model; the query builder on `DB::connection('pos')` is acceptable
  for them in this dev-only spike (recorded exception to the "models only"
  rule; cycle 2 adds models where the app needs them).

### Products to test with

| Till CODE | Name on till | Udea code | Deposit |
|---|---|---|---|
| `8711521947614` | Luna e Terra Apple-mango juice 200ml | 94761 | 313 → 0.25 |
| `8714728001004` | Ommelanden Buttermilk 1l | 45751 | 315 → 0.70 |

Both are plain unit products (not scale, not variable price).

## Constraints

- **Dev POS database only.** Before the first write, assert
  `DB::connection('pos')->getConfig('host') === '127.0.0.1'` and
  `getConfig('port') == 3307` and `getConfig('database') === 'unicenta2016'`.
  Nothing in this cycle is run against production.
- Tables that may be written: `PRODUCTS` (new rows; `ATTRIBUTES` of the two
  test products only), `CATEGORIES` (one new row), `PRODUCTS_CAT` (rows for
  the new products), `RESOURCES` (new rows; the `Ticket.Buttons` edit). No
  other POS table. The Laravel database is not touched.
- Every write goes through a re-runnable script kept in
  `docs/deposit/scripts/` so the owner can reapply it and so cycle 2 can read
  exactly what was done. A matching rollback script exists before the first
  write is made.
- Scripts on the till must never block a sale: `try/catch` around everything,
  `return null` on any failure, and no database access from the scripts (all
  data comes from the line's properties).
- Deposit is charged at cost and zero-rated (TAXCAT `000`), per the owner.
- No application code, migrations, or parser changes. No commits.

## Out of scope

- Any Laravel code (models, services, screens, parsers, migrations). Cycle 2.
- Production tills and the production database.
- Marking the other 51 deposit products; only the two test products above.
- Receipt template wording changes, shelf labels, reporting, reconciliation.
- The `ticket.setline` and `ticket.removeline` events themselves (only
  `ticket.addline` and `ticket.change` are wired).

## Steps

### 1. Baseline and safety checks
Files: none (report only).
What: record `git rev-parse --short HEAD`, `git status --short`. Run the
connection assertion from Constraints. Record the current `RESOURCES` count,
`PRODUCTS` count, the `md5(CONTENT)` of `Ticket.Buttons`, and confirm
`SELECT COUNT(*) FROM PRODUCTS WHERE LENGTH(ATTRIBUTES) > 0` is 0.
Check: the three config values match; counts pasted into `implemented.md`.

### 2. Back up `Ticket.Buttons`
Files: `docs/deposit/scripts/Ticket.Buttons.2026-10-03.orig.xml` (new).
What: write the current `CONTENT` of resource `Ticket.Buttons` to that file
byte-for-byte, and insert a `RESOURCES` row `Ticket.Buttons.pre-deposit`
(`ID` uuid, `RESTYPE` 0, same `CONTENT`).
Check: `md5sum` of the file equals the `md5(CONTENT)` from step 1; the backup
row exists with the same md5.

### 3. Rollback script first
Files: `docs/deposit/scripts/deposit-spike-rollback.php` (new).
What: a PHP script run with
`php artisan tinker --execute="require 'docs/deposit/scripts/deposit-spike-rollback.php';"`
that: restores `Ticket.Buttons.CONTENT` from the `Ticket.Buttons.pre-deposit`
row; deletes resources `script.Deposit.AddLine` and `script.Deposit.Change`;
sets `ATTRIBUTES = NULL` on the two test products; leaves the deposit
products, category and backup row in place (harmless, and cycle 2 reuses
them). It runs the same connection assertion first and prints what it did.
Check: `php -l` passes. Do not run it yet.

### 4. Category and deposit products
Files: `docs/deposit/scripts/deposit-spike-setup.php` (new; steps 4–6 live in
this one idempotent script, each part skipping what already exists).
What:
- `CATEGORIES` row: `ID` uuid, `NAME` `Bottle Deposits`, `PARENTID` NULL.
  Run `SHOW COLUMNS FROM CATEGORIES` first and fill any NOT NULL column.
- Six products via `App\Models\Product::create` + `forceFill(['ISSERVICE'=>1])`,
  `CATEGORY` = the new category, `TAXCAT` `000`, `PRICEBUY` 0,
  `REFERENCE` = `CODE`:

  | CODE | NAME | PRICESELL |
  |---|---|---|
  | `DEP-025` | Bottle deposit 0.25 | 0.25 |
  | `DEP-070` | Bottle deposit 0.70 | 0.70 |
  | `DEP-010` | Bottle deposit 0.10 | 0.10 |
  | `DEP-025-RET` | Bottle deposit refund 0.25 | −0.25 |
  | `DEP-070-RET` | Bottle deposit refund 0.70 | −0.70 |
  | `DEP-010-RET` | Bottle deposit refund 0.10 | −0.10 |

  Plain ASCII names (no € sign) so the receipt printer cannot mangle them.
- `PRODUCTS_CAT` rows (`PRODUCT`, `CATORDER` 1..3) for the **three refund
  products only**, so they appear as catalogue buttons for the cashier; the
  charge products are never tapped by hand and stay out of the catalogue.
Check: `Product::whereIn('CODE', [...six...])->count()` is 6 with the right
`PRICESELL`, `TAXCAT`, `ISSERVICE`; three `PRODUCTS_CAT` rows; the category
row exists. Paste the six product `ID`s into `implemented.md` (cycle 2 needs
them).

### 5. Mark the two test products
Files: `docs/deposit/scripts/deposit-spike-setup.php`.
What: set `PRODUCTS.ATTRIBUTES` for `8711521947614` to the XML in Context
with `deposit.id` = the `ID` of `DEP-025`, `deposit.name` `Bottle deposit
0.25`, `deposit.price` `0.25`; and for `8714728001004` with `DEP-070` /
`Bottle deposit 0.70` / `0.70`. Write the exact XML shown (DOCTYPE included,
UTF-8, no BOM).
Check: read both back; `simplexml_load_string` parses and yields the three
entries with the expected values.

### 6. Install the two till scripts
Files: `docs/deposit/scripts/script.Deposit.AddLine.bsh` (new),
`docs/deposit/scripts/script.Deposit.Change.bsh` (new),
`docs/deposit/scripts/deposit-spike-setup.php`.
What: save the two scripts below to the files, then insert them as
`RESOURCES` rows (`NAME` as given, `RESTYPE` 0, `CONTENT` = file bytes);
re-running the setup script updates `CONTENT` in place.

`script.Deposit.AddLine` (event `ticket.addline`):

```java
// script.Deposit.AddLine — uniCenta oPOS 3.91.3, event ticket.addline.
// `line` is the TicketLineInfo about to be added. Return null to let the till
// add it; return anything else to say "handled" (the till then adds nothing).
// A product the app marked with deposit.* properties gets its deposit line
// directly under it. Never throws out of the script: a thrown exception would
// cancel the scan.
import com.openbravo.pos.ticket.TicketLineInfo;

String depId = null;
TicketLineInfo dep = null;
try {
    depId = line.getProperty("deposit.id");
    if (depId == null || line.getProperty("deposit.for") != null || line.isProductCom()) {
        return null;
    }
    double price = Double.parseDouble(line.getProperty("deposit.price"));
    String name = line.getProperty("deposit.name", "Bottle deposit");
    dep = new TicketLineInfo(depId, name, "000", line.getMultiply(), price,
                             taxeslogic.getTaxInfo("000", ticket.getCustomer()));
    dep.setProperty("deposit.for", line.getProductID());
    dep.setProperty("product.service", "true");   // no stock movement for the deposit line
} catch (Exception e) {
    return null;                                   // fall back to a plain add
}
// Nothing below can throw: both adds or neither.
ticket.addLine(line);
ticket.addLine(dep);
sales.setSelectedIndex(ticket.getLinesCount() - 2);
return "deposit";
```

`script.Deposit.Change` (event `ticket.change`; fires after a quantity edit or
a line removal, and the till repaints afterwards):

```java
// script.Deposit.Change — uniCenta oPOS 3.91.3, event ticket.change.
// Rule: a deposit line is valid only when the line directly above it is a
// product carrying deposit.id equal to the deposit line's product, and then
// its quantity equals that product's quantity. Orphans are removed; a marked
// product with no deposit line under it gets one back.
import com.openbravo.pos.ticket.TicketLineInfo;

try {
    // Pass 1: deposit lines — drop orphans, sync quantities (walk backwards: removals keep indexes valid)
    for (int i = ticket.getLinesCount() - 1; i >= 0; i--) {
        TicketLineInfo l = ticket.getLine(i);
        if (l.getProperty("deposit.for") == null) continue;
        TicketLineInfo above = (i > 0) ? ticket.getLine(i - 1) : null;
        boolean ok = above != null
                  && above.getProperty("deposit.for") == null
                  && l.getProductID() != null
                  && l.getProductID().equals(above.getProperty("deposit.id"));
        if (!ok) { ticket.removeLine(i); continue; }
        if (Math.abs(l.getMultiply() - above.getMultiply()) > 0.0001) {
            l.setMultiply(above.getMultiply());
        }
    }
    // Pass 2: marked products with no deposit line directly under them
    for (int i = ticket.getLinesCount() - 1; i >= 0; i--) {
        TicketLineInfo p = ticket.getLine(i);
        String depId = p.getProperty("deposit.id");
        if (depId == null || p.getProperty("deposit.for") != null) continue;
        boolean has = (i + 1 < ticket.getLinesCount())
                   && ticket.getLine(i + 1).getProperty("deposit.for") != null
                   && depId.equals(ticket.getLine(i + 1).getProductID());
        if (has) continue;
        TicketLineInfo dep = new TicketLineInfo(depId, p.getProperty("deposit.name", "Bottle deposit"), "000",
                p.getMultiply(), Double.parseDouble(p.getProperty("deposit.price")),
                taxeslogic.getTaxInfo("000", ticket.getCustomer()));
        dep.setProperty("deposit.for", p.getProductID());
        dep.setProperty("product.service", "true");
        ticket.insertLine(i + 1, dep);
    }
} catch (Exception e) {
    // never block the till
}
return null;
```

The Implementer owns the final text; keep the two invariants (never throw,
`ticket.addline` returns non-null only after both lines are added).

Check: parse both files with the till's own BeanShell:
`java -cp "/home/jon/VirtualBox VMs/vb_arch_share/unicenta/lib/bsh-2.0b5.jar" bsh.Parser docs/deposit/scripts/script.Deposit.AddLine.bsh`
(and the other). Silence and exit 0 mean it parsed; a `ParseException`
stack trace on stderr is a failure. (The Planner ran this on the two scripts
as written above on 2026-10-03: both parsed.) Then read both `RESOURCES` rows back
and compare md5 with the files.

### 7. Wire the events in `Ticket.Buttons`
Files: `docs/deposit/scripts/deposit-spike-setup.php`,
`docs/deposit/scripts/Ticket.Buttons.2026-10-03.deposit.xml` (new; the result).
What: take the backed-up content and insert, directly after the existing line
`<event key="ticket.close" code="Ticket.Close"/>`:

```xml
        <event key="ticket.addline" code="script.Deposit.AddLine"/>
        <event key="ticket.change" code="script.Deposit.Change"/>
```

Write the result to the `.deposit.xml` file and to `RESOURCES.CONTENT` of
`Ticket.Buttons`. Do not touch any other line (the commented examples stay).
Check: `xmllint --noout` on the new file passes; `diff` against the `.orig.xml`
shows exactly the two added lines; `md5(CONTENT)` of `Ticket.Buttons` equals
the new file's md5.

### 8. Hand-over for the till test
Files: `implemented.md`.
What: write a "Till test" section the owner can fill in, with the matrix from
Verification 5 as a table (step, expected, observed). State plainly that the
till must be restarted in the VM first. Then set `Status: DONE` (the database
work is done; the till test is the owner's and is reported in that section).
Check: the section exists and lists every row of the matrix.

## Verification

1. `php -l` on both PHP scripts → no syntax errors.
2. Run `deposit-spike-setup.php` twice → second run reports everything
   "already present" and changes nothing (idempotent).
3. `bsh.Parser` on both `.bsh` files → no `ParseException`.
4. Database read-backs: six products, three `PRODUCTS_CAT` rows, two
   `ATTRIBUTES` blobs that parse, two script resources matching the files,
   `Ticket.Buttons` matching the `.deposit.xml` file, the backup row intact.
5. **Owner's till test** in the VM (restart the till first; use a cash drawer
   sale and void or pay each ticket):

   | # | Action | Expected |
   |---|---|---|
   | a | Scan `8711521947614` (juice) | Two lines: the juice, then "Bottle deposit 0.25" ×1 directly under it; total includes 0.25 |
   | b | Scan the juice again | A second juice line followed by its own deposit line (four lines) |
   | c | Scan `8714728001004` (buttermilk) | Buttermilk line, then "Bottle deposit 0.70" under it |
   | d | Scan any product without a deposit (e.g. a tea) | One line, nothing added |
   | e | Select a juice line, press + (quantity 2) | Its deposit line becomes ×2 after the repaint |
   | f | Select a juice line and delete it | Its deposit line disappears with it |
   | g | Select a deposit line and delete it | It comes back (deposit is mandatory) |
   | h | Pay the ticket by cash; print the receipt | Receipt shows the deposit lines under their products; `TICKETLINES` has rows with `PRODUCT` = the `DEP-*` id and `ATTRIBUTES` containing `deposit.for`; no `STOCKDIARY` row for the deposit product |
   | i | New ticket: tap "Bottle deposit refund 0.25" from the Bottle Deposits category, alone | A −0.25 line; the payment screen offers a cash refund and the ticket closes |
   | j | New ticket: juice + "Bottle deposit refund 0.25" | Total = juice + 0.25 − 0.25 |
   | k | Type a quantity (e.g. `3` then `x`) and scan the juice | Juice ×3 and deposit ×3 |
   | l | After all of the above, scan products on a till restart with no deposit property | Unchanged behaviour; no dialogs |

   Any "cannot execute" dialog is a failure: note the exact message.
6. After the owner's test, `php artisan test --filter=Voucher` → still green
   (nothing in the Laravel database changed; this guards against an
   accidental write through the wrong connection).

## Risks

- **A script error cancels scans.** Both scripts are wrapped; the AddLine
  script does its adds last. If the till still shows a dialog on scan, run the
  rollback script and restart the till; the till is back to normal.
- **Order of lines.** The design puts the deposit directly under its product.
  If `ticket.addline` returning non-null skips something the shop relies on
  (`visorTicketLine` is a customer display the shop does not have), fall back
  to adding only the deposit line in the script and returning `null`; the
  deposit then appears above the product. Record which variant the test used.
- **Quantity edits.** Whether + / − on this till goes through
  `paintTicketLine` is confirmed only by test e. If the deposit does not
  follow, `ticket.setline` can be wired to the same Change script in a bumped
  revision.
- **Negative totals.** Test i decides whether standalone returns work
  through the payment screen or must be rung with a sale. Either outcome is
  fine for cycle 2; it needs to be known.
- **Shared database.** The app's dev tests that touch the `pos` connection
  read these new rows. They are inert (service products in a new category).

## Review

Planner, 2026-10-03, after reading `implemented.md` to the end, the six files
under `docs/deposit/scripts/`, and re-running every check against the dev POS
database.

### Criteria

| Criterion | Result |
|---|---|
| Connection assertion before any write | Pass. Both scripts refuse unless host 127.0.0.1, port 3307, database `unicenta2016`; re-checked live |
| Step 2 backup | Pass. `Ticket.Buttons.pre-deposit` row and `.orig.xml` both md5 `6310ac80…`, the pre-change value |
| Step 3 rollback script | Pass. Lints; restores from the backup row, deletes the two scripts, clears the two `ATTRIBUTES`; not run (correct) |
| Step 4 category and six products | Pass. Six `DEP-*` rows, TAXCAT `000`, ISSERVICE 1, ISCOM 0, ISSCALE 0, right prices; `PRODUCTS_CAT` has exactly the three refund products; category row present |
| Step 5 attributes | Pass. Both blobs parse (PHP and, per the report, Java `loadFromXML`), 352 bytes, no BOM, right ids and prices |
| Step 6 scripts | Pass. Resources match files (md5 `a4dea667…`, `0706d00f…`); diff against the plan text is three em dashes in comments only; both parse with `bsh-2.0b5.jar` |
| Step 7 wiring | Pass. `diff orig deposit` is exactly the two `<event>` lines; `xmllint` ok; live `Ticket.Buttons` md5 `e202b0e0…` equals the file |
| Verification 2 idempotency | Pass (report shows the second run; the setup script's logic confirms it) |
| Verification 5 till test | Pass on the owner's word for every row; rows h and i confirmed independently from the database: ticket `a85aea3c` has juice / deposit / juice / deposit / buttermilk / deposit with `deposit.for` on each deposit line, TAXID `000`, and ticket `8dcef97c` is three refund lines paid `cash -1.05`. `STOCKDIARY` has no row for any `DEP-*` product (and two for the juice, as it should) |
| Verification 6 | Pass. `php artisan test --filter=Voucher`: 143 passed, re-run by the Planner |
| No application code, no commit | Pass. `git status` shows only the untracked `docs/deposit/` |

### Deviations

1. Backup folded into the setup script as part 0: **accepted**. It satisfies
   the Constraints better than the plan's wording did, and the guard (refuse
   when already wired with no backup) is a good addition.
2. Em dashes replaced in the three comments: **accepted**. Pure-ASCII
   resources remove a charset question on the till.
3. Verification 6 run before the till test as well: **accepted**.

### Notes for Planner

1. AddLine's non-null return skips the till's own `ticket.change` after a
   scan: **accepted as a recorded fact**, no action. Written into
   `README.md` so anything later wired to `ticket.change` knows it does not
   see scans.
2. Change does not re-price an open ticket's deposit lines: **rejected for
   now**. A deposit price changes rarely and never mid-ticket in practice;
   cycle 2 updates the product's `deposit.price` and the deposit product's
   `PRICESELL` together, which is the right place.
3. Rollback leaves the refund buttons in the catalogue: **deferred**. Cycle 2
   decides where the refund buttons live for real; until then they are
   harmless on the test till.
4. Scale and variable-price products: **fixed in cycle 2**. The mapping must
   refuse `ISSCALE = 1` or `ISVPRICE = 1` products (no Udea deposit product
   is weighed; a weight as a deposit quantity would be wrong).

### Facts learned for cycle 2

- Deposit lines sit directly under their product and carry `deposit.for`
  (the product's POS id); the product line carries `deposit.id` in
  `TICKETLINES.ATTRIBUTES`. Reconciliation can therefore pair lines exactly.
- Quantity + / − goes through `ticket.setline` → `ticket.change`; the deposit
  follows (test e). Deleting the deposit line brings it back (test g).
- A ticket of refund lines alone closes with a negative cash payment
  (test i), so standalone bottle returns need no sale on the ticket.
- Receipt printing needed no template change (test h).

### State left on the test till

Everything stays installed on the dev POS database (products, category,
attributes on the two test products, scripts, wiring, backup row) so cycle 2
can build on it. `docs/deposit/scripts/` stays in place as the source of
truth for the till scripts and the setup / rollback; it is not archived with
the cycle.
