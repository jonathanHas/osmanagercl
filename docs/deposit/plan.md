# Cycle 3 — Go live: till install command, runbook, warnings on the delivery page, staff procedure

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-10-04

## Goal

Everything the shop needs to switch bottle deposits on for real, without
anyone editing the production POS by hand: an artisan command that installs
(checks, and rolls back) the two till scripts and the `Ticket.Buttons` events
on whatever POS database the app points at; a written go-live runbook the
owner follows on production; the parser's deposit reconciliation warnings
kept on the delivery page instead of vanishing with the flash; the
add-a-product card on `/deposits` showing what was picked (the owner's
`todo.txt`: "the search bar for adding products currently doesn't take
barcodes"); and a draft staff procedure for BookStack. The float
reconciliation report is cycle 4.

## Context

### Who does what on production

- Only the owner commits and deploys. Deploys rsync the repo to
  `/var/www/html/osmanager` on `lilThink2` with
  `scripts/deployment/deploy/deploy-streamlined3.sh` (excludes `.env`,
  `tests`, `storage/app/*`, `node_modules`, the parser venv; `docs/` and
  `resources/` **are** deployed), then `composer install`, `npm run build`,
  `php artisan migrate --force`, caches. Artisan commands that write run as
  `www-data` (`sudo -u www-data php artisan …`). Neither Planner nor
  Implementer runs anything against production (`CLAUDE.md`, `planimp.md`).
- Production tills: `OrgStore` and `Till 2` (hostnames in `CLOSEDCASH`).
  uniCenta reads `Ticket.Buttons` and caches resources at start-up, so both
  tills restart after the install.
- The app's `pos` connection on production is the live uniCenta database; the
  voucher sync already writes products there, so the credentials allow it.

### What exists from cycles 1 and 2 (dev)

- Till scripts, byte-exact: `docs/deposit/scripts/script.Deposit.AddLine.bsh`
  (md5 `a4dea667ba39d37025f6a9b5d03330b6`) and
  `docs/deposit/scripts/script.Deposit.Change.bsh` (`0706d00f4aff6b8b6275a3a80eb550ba`).
  Installed on dev as `RESOURCES` rows of the same names (`RESTYPE 0`,
  `CONTENT` = file bytes). `RESOURCES` has `ID` (uuid, PK), `NAME` (unique
  index `RESOURCES_NAME_INX`), `RESTYPE`, `CONTENT` (blob).
- `Ticket.Buttons` wiring: two lines inserted directly after the one existing
  line `        <event key="ticket.close" code="Ticket.Close"/>` (eight
  spaces of indent):

  ```xml
          <event key="ticket.addline" code="script.Deposit.AddLine"/>
          <event key="ticket.change" code="script.Deposit.Change"/>
  ```

  The dev backup row `Ticket.Buttons.pre-deposit` holds the pre-change
  content (md5 `6310ac80cf46cbcf7f5e2c90e2f87625`); dev POS is a production
  snapshot of 2026-08-14, so production's `Ticket.Buttons` is expected to be
  identical, but the command must verify the anchor, not assume it.
- `docs/deposit/scripts/deposit-spike-setup.php` / `deposit-spike-rollback.php`
  did this for dev with a hard-coded dev-connection assertion; they are the
  reference for the command's logic (backup row first; refuse when already
  wired but no backup; anchor must occur exactly once; idempotent upserts).
- App side: `DepositPosService` (`syncAll`, `check`), `DepositEvidenceService`,
  commands `deposits:backfill-delivery-items`, `deposits:refresh-suggestions`,
  `deposits:sync-pos [--dry-run|--check]`; screen `/deposits`
  (`DepositController`, `resources/views/deposits/index.blade.php`); test
  trait `tests/Concerns/CreatesDepositPosTables.php` (sqlite POS tables; no
  `RESOURCES` yet). Command tests follow
  `tests/Feature/RetireFixedVoucherProductsTest.php` (`$this->artisan(...)`,
  `expectsOutputToContain`, `assertExitCode`).
- Parser: `delivery_udea.py` puts `barrels.line_units` (`{code: units}`) and
  reconciliation warnings (`Deposit code 313: product lines total 54 units,
  barrels section says 60` / `… not in the barrels section`) into the result.
  `DeliveryController::storePdf()` (around lines 609–650) reads
  `$warnings = $this->deliveryParsingService->getWarnings($result)` and
  `$barrels = $result['data']['barrels']`, keeps only `count($warnings)` in
  the success flash, flashes `import_summary`, and persists
  `unparsed_lines`. `Delivery.import_data` is a JSON `array` cast, set once
  in `importFromPdfData()`. `resources/views/deliveries/show.blade.php` has
  the permanent "Barrels/Deposits" collapsible (from line ~544, Alpine
  `x-data="{ expanded: false }"`; note it uses `@click`, which is legacy on
  that page and not to be copied: write `x-on:click`).
- Add card on `/deposits` (view lines ~267–281): `<x-product-search
  mode="picker" name="product_id" :sync-url="false" …>` + tier select + Add.
  The picker is the right component and its endpoint does match full
  barcodes (`ProductSearchService`: a run of 4+ digits matches `CODE`;
  verified with `8711521947614`, stocked or not). On `select()` the picker
  sets the hidden `product_id`, **clears its own text box**, and dispatches
  `product-search:selected` with the product (`id`, `name`, `code`…); the
  card listens to nothing, so after a scan or a pick the box is empty and
  nothing says what was chosen. That is the most likely cause of the owner's
  note. `StoreProductDepositRequest` requires `product_id`
  (`exists:App\Models\Product,ID`) and answers "Pick a product first."
  when it is empty.
- Sales reports: deposit and refund lines are ordinary till lines in
  category `Bottle Deposits`, `TAXCAT 000`. They will appear in sales
  analytics as zero-VAT sales (refunds negative). Nothing in this cycle
  changes that; the runbook says so.

## Constraints

- No production action by either session. The command is written and tested
  on dev and in sqlite; the owner runs it on production following the runbook.
- The till scripts are **not changed**: the command installs the files
  byte-for-byte (md5s above). `docs/deposit/scripts/` stays the source of
  truth for the spike history; the deployable copies live under
  `resources/pos/deposit/` (step 2) and must be identical.
- The command writes only `RESOURCES` (three rows: two scripts, the
  `Ticket.Buttons` content, plus the backup row). Nothing else on the POS.
- It refuses to wire `Ticket.Buttons` unless the anchor line occurs exactly
  once, and never overwrites an existing backup row.
- Against a non-dev POS (anything but `127.0.0.1:3307/unicenta2016`) it
  prints the target and asks for confirmation; `--force` skips the prompt
  for scripted use. `--check` and `--rollback` never need confirmation on dev
  but `--rollback` asks on non-dev too.
- The delivery page addition must not break imports: persisting the
  reconciliation is wrapped so a failure only logs.
- Eloquent where a model exists; the query builder on `pos` for `RESOURCES`
  (no model; recorded exception, as for `CATEGORIES`/`PRODUCTS_CAT`).
- `./vendor/bin/pint` on changed PHP. No commits.

## Out of scope

- The deposit float / reconciliation report (charged vs refunded vs Udea
  credits): cycle 4.
- Changing the till scripts' logic, the attribute format, or the sync rules.
- Writing into BookStack (the owner publishes the procedure); the SOP help
  button for `/deposits`.
- Switching tier 10046 on, or confirming the 52 suggestions (owner, on the
  screen).
- The ten pre-2026-01-27 garbled lines.
- Any change to sales analytics for the deposit category.

## Steps

### 1. Baseline
Files: none.
What: `git rev-parse --short HEAD`, `git status --short` (cycles 1–2 are
uncommitted; list them as pre-existing), `php artisan test` failure list
(expect the known 15), Python suite count (205).
Check: in `implemented.md`.

### 2. Deployable copies of the till scripts
Files: `resources/pos/deposit/script.Deposit.AddLine.bsh` (new),
`resources/pos/deposit/script.Deposit.Change.bsh` (new),
`resources/pos/deposit/README.md` (new),
`docs/deposit/scripts/deposit-spike-setup.php` (path only).
What: copy the two `.bsh` files from `docs/deposit/scripts/` unchanged; the
README says what they are, that the app installs them with
`deposits:install-till`, and that `docs/deposit/` holds the history and the
test matrix. Point the spike setup script's part 5 at the new location so the
two copies cannot drift (`$dir` for the `.bsh` reads becomes
`resource_path('pos/deposit')`).
Check: `md5sum` of both new files equals `a4dea667…` / `0706d00f…`;
`php -l` on the spike script.

### 3. `deposits:install-till` command
Files: `app/Console/Commands/DepositsInstallTill.php` (new),
`app/Services/Deposits/DepositTillInstaller.php` (new),
`tests/Concerns/CreatesDepositPosTables.php` (add `RESOURCES`: `ID` string
PK, `NAME` string unique, `RESTYPE` integer default 0, `CONTENT` text
nullable),
`tests/Feature/DepositTillInstallerTest.php` (new).
What (service):
- `target(): array` → `host`, `port`, `database`, `is_dev` (the dev
  signature `127.0.0.1` / `3307` / `unicenta2016`).
- `status(): array` → per script: `present`, `matches_file` (md5 of
  `CONTENT` vs the resource file); `wired` (both event lines present in
  `Ticket.Buttons`); `anchor_count`; `backup_present`; `installed` (= both
  scripts match and wired).
- `install(): array` → in order: (a) if `Ticket.Buttons` lacks both event
  lines and no backup row exists, insert `Ticket.Buttons.pre-deposit`
  (`RESTYPE 0`, current content) and write the same bytes to
  `storage/app/private/deposit/Ticket.Buttons.<Ymd-His>.pre-deposit.xml`;
  if already wired and no backup row: throw (`Ticket.Buttons is already
  wired but no backup row exists`); (b) upsert both script rows from the
  resource files (`created` / `updated` / `unchanged` per script); (c) if not
  wired: require the anchor exactly once, insert the two lines after it,
  update `CONTENT`; `wired` / `already wired`. Return the actions.
- `rollback(): array` → restore `Ticket.Buttons` from the backup row (throw
  if missing), delete the two script rows, keep the backup row. Return the
  actions.
What (command): `deposits:install-till {--check} {--rollback} {--force}`.
Always prints the target (`POS: host:port/database (dev|NOT dev)`) first.
`--check`: table of the status, exit 0 when `installed`, else 1. Default:
when not dev and not `--force`, `confirm('Install the deposit till scripts
on this POS?')`, abort with exit 1 on no; run `install()`, print actions,
then the status table, exit 0 only if `installed`. `--rollback`: same
confirmation rule, run `rollback()`, print, exit 0. Errors are printed as
`error()` with exit 1, never a stack trace.
Tests (sqlite POS via the trait, `Ticket.Buttons` seeded with a small XML
that contains the anchor once): install creates backup + 2 scripts + wires,
md5s match the resource files, exit 0; a second install reports `unchanged`
/ `already wired`, no second backup; `--check` exits 1 before and 0 after;
`--rollback` restores the original bytes and removes the scripts, backup row
kept; an XML without the anchor → error exit 1 and nothing written; an XML
already wired with no backup → error. The `is_dev` branch: in tests the
connection is sqlite so `is_dev` is false; test that without `--force` the
command asks and `No` aborts (`expectsConfirmation(...,'no')`,
`assertExitCode(1)`), and `--force` proceeds.
Check: `php artisan test --filter=DepositTillInstallerTest` green. On dev
(already installed by the spike): `php artisan deposits:install-till
--check` → installed, exit 0; `php artisan deposits:install-till` → every
action `unchanged` / `already wired`, exit 0. Then the round trip:
`--rollback` (dev: no prompt) → `--check` exits 1 → install → `--check`
exits 0 → `Ticket.Buttons` md5 is `e202b0e0878fda86aaf847f221626409` again
(the cycle 1 wired content) and the backup row still `6310ac80…`. Paste all.
**Tell the owner in `implemented.md`** that the dev till must be restarted
once after this round trip and the juice scanned once more.

### 4. Deposit reconciliation kept on the delivery
Files: `app/Services/DeliveryService.php`,
`app/Http/Controllers/DeliveryController.php`,
`resources/views/deliveries/show.blade.php`,
`tests/Feature/DeliveryDepositReconciliationTest.php` (new).
What: `DeliveryService::recordDepositReconciliation(Delivery $delivery, array
$barrels, array $warnings): void` merges into `import_data`:

```php
'deposit_reconciliation' => [
    'line_units' => $barrels['line_units'] ?? [],          // {code: units on product lines}
    'section'    => [code => qty, …] from $barrels['items'],
    'warnings'   => array_values(array_filter($warnings, fn ($w) => str_starts_with($w, 'Deposit code'))),
    'checked_at' => now()->toDateTimeString(),
],
```

and saves (only when `line_units` or `section` is non-empty). `storePdf()`
calls it right after `storeBarrelItems` (inside the existing Udea branch is
fine), wrapped in `try/catch (\Throwable)` + `Log::warning`. On
`deliveries/show`, inside the permanent Barrels/Deposits block (and also
when `$delivery->barrels` is empty but `import_data.deposit_reconciliation`
exists), add a small table "Deposit codes on product lines": code, units on
lines, units in barrels section, status (`matches` / `differs by n` /
`not in section`), and the warnings as a red list when any. Use `x-on:`,
not `@click`.
Test: a `Delivery` with `import_data.deposit_reconciliation` set renders the
table and a warning (authenticated user with `deliveries.view`; if `show()`
needs POS tables, use `CreatesDepositPosTables`); the service method merges
without dropping other `import_data` keys and skips when both arrays are
empty.
Check: test green; on dev, the method run by hand on delivery 132 with the
parsed JSON from step 2 of cycle 2 (`line_units` `{313: 54, …}`, section
from `DeliveryBarrel` rows) shows the table on `/deliveries/132` with every
row `matches`.

### 5. The add-a-product card shows the pick
Files: `resources/views/deposits/index.blade.php`,
`tests/Feature/DepositScreenTest.php`.
What: wrap the form in `x-data="{ picked: null }"` with
`x-on:product-search:selected="picked = $event.detail"` and
`x-on:product-search:cleared="picked = null"`. Under the picker show
`<p x-show="picked" x-cloak>Selected: <span x-text="picked?.name"></span>
(<span x-text="picked?.code"></span>)</p>` and a hint when nothing is picked:
"Scan or type a barcode, or type part of the name, then choose a result;
Enter selects the top match." Disable the Add button until `picked`
(`x-bind:disabled="!picked"`). Keep the hidden `product_id` as is (the
picker owns it). Nothing server-side changes. Optional nicety if cheap: pass
`autofocus` only when the Products card has no rows.
Test: the index page contains the hint text and the selected-product
markup (a string assertion is enough; Alpine does not run in tests).
Check: test green; browser check on dev as an admin: scan or type
`8711521947614` + Enter in the card → "Selected: Luna e Terra Apple-mango
juice 200ml (8711521947614)" appears and Add becomes active; Add on an
already-mapped product re-confirms it (existing behaviour). Record whether
typing the barcode without Enter and clicking the dropdown result also
works. Any console error is a failure.

### 6. Runbook and staff procedure
Files: `docs/features/barrel-deposit-tracking.md`,
`docs/deposit/sop-bottle-deposits.md` (new).
What: in the feature doc add "Going live on production" with this order,
each line saying who (owner) and where (production, as `www-data`):

1. Commit and deploy as usual; `php artisan migrate --force` (four
   migrations from cycle 2).
2. `php artisan deposits:backfill-delivery-items --dry-run`, then without
   `--dry-run`; `php artisan deposits:refresh-suggestions`.
3. `/deposits`: switch on tiers 313, 315, 9936 (10046 optional); confirm the
   products (per row or "Confirm all suggested"), reject any that are wrong.
4. `php artisan deposits:sync-pos --check`, then `php artisan
   deposits:sync-pos` (writes the attributes; the tills ignore them until the
   scripts exist, so this is safe to do first).
5. `php artisan deposits:install-till --check` (expect "not installed"),
   then `php artisan deposits:install-till` (confirms the production host),
   then `--check` again → exit 0.
6. Restart uniCenta on `OrgStore` and `Till 2`.
7. On one till: scan a 313 product → deposit line under it; print the
   receipt; in the catalogue open **Bottle Deposits** → tap "Bottle deposit
   refund 0.25" → −0.25 line; pay out a refund-only ticket in cash.
8. Publish the staff procedure (step below) in BookStack; tell staff.
9. Rollback at any time: `php artisan deposits:install-till --rollback`,
   restart both tills (deposit lines stop; attributes may stay).

Then the notes: every Udea docket import runs `syncAll()`, which clears
`deposit.*` properties added by hand on the till for products without a
confirmed row; deposit and refund lines appear in sales analytics as
zero-VAT sales in category "Bottle Deposits"; a tier price change creates a
new `DEP-<cents>` pair and keeps the old refund button.
The SOP draft (`docs/deposit/sop-bottle-deposits.md`), written for staff,
short, for the owner to paste into BookStack: what the deposit is and which
products carry it (glass bottles and jars from Udea; the till adds it by
itself, "Bottle deposit 0.25 / 0.70 / 0.10" under the item); selling: nothing
to do, the deposit line is on the receipt; returns: Bottle Deposits category
→ tap the refund button once per bottle of that size; a return with no
purchase is paid out in cash; what to accept (clean, intact Udea bottles and
jars of those types, deposit of the size printed on our shelf); if a deposit
line looks wrong: remove the product line, not the deposit (it comes back by
itself); who to ask.
Check: the doc renders (markdown lint not needed); the SOP file exists and
is under a page in length.

### 7. Format and tidy
Files: changed PHP.
What: `./vendor/bin/pint` on the changed PHP files; delete nothing.
Check: `pint --test` clean on them.

## Verification

1. `php artisan test --filter="DepositTillInstaller|DeliveryDepositReconciliation|DepositScreen"` → green.
2. `php artisan test` → the known 15 failures only.
3. Python suite → 205 passed (untouched).
4. Dev round trip of `deposits:install-till` (`--check` → `--rollback` → `--check` → install → `--check`) with the md5s in step 3; dev till restarted by the owner and the juice scanned once: deposit line present.
5. `/deliveries/132` shows the deposit reconciliation table.
6. `/deposits` add card: browser check per step 5 recorded with the exact text seen.
7. `pint --test` clean; `git status --short` shows only cycle 1–3 files plus the known unrelated ones.

## Risks

- **Production `Ticket.Buttons` differs from dev's.** The anchor check makes
  the command refuse rather than guess; the owner then sends the production
  content (via `--check`, which prints `anchor_count`) and a follow-up wires
  it by hand with the Implementer's help.
- **Till restart forgotten.** The runbook says it twice; `--check` cannot see
  whether a till has restarted, so step 7 of the runbook is the proof.
- **Scripts install before attributes exist** is harmless (no product has
  the property → the scripts do nothing); the reverse order is harmless too.
- **Backup row name clash on production** (`Ticket.Buttons.pre-deposit`
  already there from an earlier manual attempt): the command keeps it and
  will not overwrite; `--check` shows `backup_present`.

## Review

Planner, 2026-10-05. Read `implemented.md` to the end; read the installer
service, the command, the reconciliation recorder and its call site, the
delivery page diff, the add card, the runbook, the SOP draft and the
resources README; re-ran the suites and the installer check on dev.

### Criteria

| Criterion | Result |
|---|---|
| Deployable script copies, byte-identical | Pass. `a4dea667…` / `0706d00f…` in both `resources/pos/deposit/` and `docs/deposit/scripts/`; the spike script now reads from `resource_path('pos/deposit')` |
| `deposits:install-till` | Pass. Prints the target first; `--check` read-only with exit code; confirmation on a non-dev POS unless `--force`; all refusals checked before any write (better than the plan's order); backup row never overwritten; scripts upserted by bytes; wiring after the single anchor; rollback keeps the backup. 10 tests. Dev round trip restored the exact cycle 1 content (`Ticket.Buttons` `e202b0e0…`, backup `6310ac80…`), re-checked live by the Planner: "Installed.", exit 0 |
| Reconciliation on the delivery | Pass. `recordDepositReconciliation()` merges without dropping keys and skips empty data; called for every supplier inside `try/catch` after the deposit hook; delivery 132 shows four matching codes; warnings are visible without expanding (accepted deviation); `@click` → `x-on:click`; 4 tests |
| Add card shows the pick | Pass. `picked` state, "Selected: name (code)", hint, Add disabled until a pick, `?.` on the nullable; browser check by the Implementer: typed barcode + Enter and a dropdown click both select, Add re-confirms; no console errors |
| Runbook and SOP | Pass. Nine ordered steps with who/where, the anchor stop condition, the three notes; SOP under a page |
| Suites, pint, scope | Pass. 57 deposit tests; full suite 15 baseline failures / 999 passed; Python 205; pint clean; only cycle 1–3 files changed plus the known unrelated ones |
| Dev till restart + juice scan after the round trip | **Owner, pending**; the resource bytes are identical, so this is a formality |

### Deviations

All four **accepted**. 3 (every refusal before the first write) is the right
order and the plan's was not. 1 (section from the parse, not from the
duplicated `DeliveryBarrel` rows of delivery 132) is what a real import does.

### Notes for Planner

1. Delivery 132's barrels stored twice (two imports of one order):
   **deferred to cycle 4**, which must de-duplicate or group by order before
   summing barrels per period, and should make `storeBarrelItems` idempotent
   per order.
2. The picker keeps the last pick while a new search is typed: **deferred**,
   small change in `<x-product-search>` (clear `selectedId` on input), for a
   side plan or cycle 4.
3. The tier select does not follow an already-mapped product: **deferred to
   cycle 4** (preselect the row's tier, or warn before re-tiering).
4. Backfilling reconciliation for old deliveries: **rejected**, not needed.

### For the owner

- The SOP says "The deposit amount is the one on our shelf label for that
  item": shelf labels do **not** show deposits (out of scope so far). Change
  that sentence before publishing (e.g. "the amount the till shows for that
  item", or the table above it).
- Restart the dev till once and scan the juice (`8711521947614`): closes the
  round-trip check.
- Then the runbook in `docs/features/barrel-deposit-tracking.md`, "Going live
  on production", is yours to run.

**ACCEPTED.** Archive: `mkdir -p docs/deposit/archive/2026-10-05-cycle-3-go-live`
and move `plan.md` and `implemented.md` there.
