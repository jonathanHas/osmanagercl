# Cycle 4 — Evidence on this machine, and a price review on `/deposits`

Status: ACCEPTED
Revision: 5
Planner: Fable 5.1
Date: 2026-10-17

## Goal

Two things. First, `/deposits` on this dev machine shows no suggested or
confirmed products: the database was restored from production on 2026-10-15
and production has never run the evidence commands, so the Implementer runs
them here (the same steps the owner will run on production from the runbook).
Second, the owner suspects that some bottled products had their shelf price
raised in the past to cover the unreturned bottle, and wants to review each
deposit product's price before the till starts adding the deposit on top. The
`/deposits` products table gains the facts needed for that judgement (current
price, latest Udea cost and markup, the last lasting price step and the cost
move around it, Udea's advice price) and two actions per row: **Keep** the
price, or **Lower by the deposit** (writes the new price to the till through
the same recipe as the product page). The decision is recorded on the row so
the review can be finished over several sessions. The deposit float report
moves to cycle 5.

## Context

### State of this machine (Planner, 2026-10-17)

- App database `osmanager` restored from the production Spatie backup of
  2026-10-15; POS snapshot `unicenta2016` (mysql57 container, 127.0.0.1:3307)
  from a production dump, receipts to 2026-10-14.
- Deposit migrations are applied (production ran them on deploy). Counts:
  `deposit_sightings` 9 (one post-deploy docket import), `product_deposits` 0,
  `delivery_items.barrel_code` set on 9 rows, `barrel_codes` 28 rows, all
  five Udea deposit tiers with `charge_customer = 0` and no `pos_product_id`.
- The POS has **no** `DEP-*` products, no `script.Deposit.*` resources, no
  deposit events in `Ticket.Buttons`, no `deposit.*` attributes. The cycle 1–3
  till install was never run on production (and the old dev POS is gone).
- `php artisan deposits:backfill-delivery-items --dry-run` today: 756 lines
  over 64 deliveries (313: 537, 315: 125, 322: 6, 9936: 12, 10046: 76).
  Code `322` ("Fles groot statiegeld NL", €0.25, `barrel_codes.id` 27) is new
  since cycle 3; it is listed on the tiers card like the others.
- `DepositEvidenceService::refreshSuggestions()` creates a row **only for
  tiers with `charge_customer` on** (`tier_off` counter). So the tiers must be
  switched on before suggestions appear. Switching a tier on
  (`DepositController::updateTier`) also creates the tier's till products on
  the dev POS through `DepositPosService::ensureTierProducts()`: that is the
  sanctioned write path (README rule 5) and the dev POS is a snapshot.
- Tiers to switch on here, as the runbook says: 313, 315, 9936. Leave 10046
  and 322 off (owner's call later).

### Where the price facts live

- **Current price**: `PRODUCTS.PRICESELL` is **net** of VAT.
  `Product::getVatRate()` reads the `tax()` hasOneThrough
  (`TAXCATEGORIES` → `TAXES`, ordered by `RATEORDER`), `getGrossPrice()` =
  net × (1 + rate). Example: "Pineapple juice Your Organic N 200ml" net
  2.0325, TAXCAT 002 (23%), gross 2.50. Dairy is TAXCAT 000 (0%).
  `Product::tax()` can be eager loaded with `->with('tax')` provided `TAXCAT`
  is in the select.
- **Cost**: `delivery_items.unit_cost` on Udea lines (`deliveries.supplier_id`
  in `config('suppliers.external_links.udea.supplier_ids')` = 5, 44, 85),
  dated by `deliveries.delivery_date`. `PRODUCTS.PRICEBUY` is the till's copy
  and lags (lime juice: PRICEBUY 1.31, latest docket 1.36). Use the docket
  cost, fall back to PRICEBUY when a product has no Udea line with
  `unit_cost > 0`.
- **Udea advice price**: `delivery_items.sale_price` is Udea's suggested
  consumer price as printed on the docket. Present (> 0) on 302 of the 697
  Udea lines of the 57 deposit products; 50 of 57 products have at least one.
  Only dockets from 2026-04-16 carry it; older lines hold NULL or 0.0000.
- **Price history**: the till keeps none. `sales_daily_summary` (Laravel DB,
  `App\Models\SalesDailySummary`, one row per product per selling day from
  2024-01-01 to 2026-10-14, indexed on `product_id, sale_date`) has
  `avg_price` = `total_revenue / total_units`, **net** of VAT
  (`SalesImportService` sums `TICKETLINES.PRICE`). A day's average dips when
  a line was discounted and comes back the next day (Laitois yogurts show
  2.89 → 2.82 → 2.89 inside a week), so a real price change is a new value
  that **holds**. Example of what the owner is looking for: "Your Organic
  Nature Lime juice 200ml" sold at net 1.99 every month from 2024-01 to
  2025-11, then 2.70 from 2025-12-27 to today (+0.71), while its Udea cost went
  1.31 → 1.36 (+0.05). Over the 57 products, 38 show at least one lasting step
  since 2025-01.
- **Writing a price**: `ProductController::updatePrice()` (route
  `products.update-price`) converts a gross input to net with the product's
  rate (`$vatRate > 0 ? $gross / (1 + $vatRate) : $gross`), updates
  `PRICESELL`, then `LabelLog::logPriceUpdate($product->CODE)` so the label
  area lists the product under "Price changed" (`LabelQueueService`). The
  deposit action must do the same three things; it does not call the
  controller. `veg_price_history` is F&V only: not used here.
- No price-change log exists for general products (`product_activity_logs`
  holds only added/removed-from-till rows), which is why the daily summary is
  the history source.

### The screen and its tests

- `DepositController::index()` builds `$tiers`, `$rows` (with `barrelCode`),
  `$products` (`Product::whereIn('ID', …)->get(['ID','NAME','CODE'])` keyed by
  ID), `$details` from `DepositEvidenceService::evidenceDetails()`,
  `$statuses`, `$suggestedCount`. View `resources/views/deposits/index.blade.php`:
  tiers card, products table (Product, Barcode, Udea code, Tier, Evidence,
  Status, Till, Actions), add card. Routes in `routes/web.php` lines 715–723
  inside the `permission:deliveries.manage` group; names `deposits.*`.
- `ProductDeposit` has `decide()` for status; fillable/casts listed in the
  model; `product_deposits` columns: id, product_id (unique), product_code,
  barrel_code_id, status, source, sightings_units, sightings_count,
  conflicting_units, last_seen_on, confirmed_by, confirmed_at, pos_synced_at,
  pos_sync_error, notes, timestamps.
- Tests: `tests/Feature/DepositScreenTest.php` (11 tests; helpers `manager()`,
  `tier()`, `suggestion()`, `attributes()`), `DepositEvidenceServiceTest`,
  `DepositPosServiceTest`, `DeliveryDepositReconciliationTest`,
  `DepositTillInstallerTest`. All use `Tests\Concerns\CreatesDepositPosTables`,
  which points the `pos` connection at an in-memory SQLite and creates
  `PRODUCTS` (has `PRICESELL`, `PRICEBUY`, `TAXCAT`), `CATEGORIES`,
  `PRODUCTS_CAT`, `supplier_link`, `suppliers`, `RESOURCES`. It creates **no**
  `TAXCATEGORIES` / `TAXES`, so `getVatRate()` would throw in a test today.
  `posProduct()` defaults `TAXCAT` to `'001'`, `PRICESELL` 2.
- `DeliveryDepositReconciliationTest::delivery()` shows the minimum to create
  a `Delivery` (`delivery_number`, `supplier_id`, `delivery_date`, `status`,
  `import_data`). `DeliveryItem` fillable: check the model before seeding
  (`delivery_id`, `supplier_code`, `description`, `unit_cost`, `sale_price`,
  `product_id`, `ordered_quantity` are the ones needed).
- Latest deposit migrations: `2026_10_03_00000{1..4}_*`; latest in repo
  `2026_10_07_100000_add_unit_to_customer_request_items.php`.
- Feature doc: `docs/features/barrel-deposit-tracking.md`, section
  "Customer Bottle Deposits (2026-10)" with sub-sections Data, Services,
  Commands, Screen, At the till, Deposit codes on the delivery page; then
  "Going live on production".

## Constraints

- POS database read-only except through the existing paths (README rule 1).
  This cycle's only POS writes are: `ensureTierProducts()` via the tiers
  switch (step 1), and `PRICESELL` of one product per **Lower** click, through
  the same recipe as `ProductController::updatePrice()`.
- The app never guesses a deposit from a product's name or size (README
  rule 4). The price review shows facts; it does **not** label a product
  "deposit included". The owner decides.
- Eloquent models only; business logic in a service; tests for every change
  (`CLAUDE.md`). Alpine: `x-on:` never `@click`; `?.` behind `x-show`.
- Do not change `ProductController`, `SalesImportService`, the parsers, the
  till scripts or `resources/pos/deposit/`.
- Do not confirm, reject or re-tier any product on `/deposits`: that is the
  owner's decision. Suggested rows stay suggested.
- Production: nothing runs there. Step 1 is dev only; the owner repeats it on
  production from the runbook.
- No commit, no deploy.

## Out of scope

- The deposit float report (charged vs refunded vs Udea charges and credits);
  de-duplicating delivery 132's doubled `delivery_barrels`; idempotent
  `storeBarrelItems`. → cycle 5.
- `<x-product-search>` keeping its last pick while typing; tier select not
  following an already-mapped product. → side plan or cycle 5.
- Switching on tiers 10046 and 322, confirming products, installing the till
  scripts (`deposits:install-till`), `deposits:sync-pos`. Owner / runbook.
- Printing or queueing shelf labels beyond the `LabelLog` row the price write
  already creates. Any change to label templates.
- A general product price-history feature. The step detection here is for
  this table only.
- Showing the review on the Shop view or the product page.

## Steps

### 1. Populate the evidence on this machine
Files: none (commands and the browser).
What, in order:
1. `php artisan deposits:backfill-delivery-items --dry-run` and record the
   table; then without `--dry-run`.
2. `php artisan deposits:refresh-suggestions`: expect `0 created` and a
   non-zero "on a tier not charged to customers" count (tiers are all off).
3. In the browser, on `/deposits`, switch on **313, 315 and 9936** (one
   PATCH each). Each creates its `DEP-<cents>` / `DEP-<cents>-RET` pair and
   the "Bottle Deposits" category on the dev POS and reruns the refresh.
4. `php artisan deposits:refresh-suggestions` once more and record the table
   and the "Udea codes with no till product" line.
Check: `ProductDeposit::suggested()->count()` ≈ 55–60 and
`ProductDeposit::confirmed()->count()` = 0; `/deposits` lists them; the three
tiers show their till product codes; `DepositSighting::count()` > 600.
Paste the counts.

### 2. Migration: price review columns
Files: `database/migrations/2026_10_17_000001_add_price_review_to_product_deposits.php` (new)
What: add to `product_deposits`: `price_review` string(16) nullable (values
`kept` | `lowered`), `price_review_from` decimal(10,4) nullable (gross price
before a lowering), `price_reviewed_at` timestamp nullable,
`price_reviewed_by` foreignId nullable → `users`, nullOnDelete. `down()`
drops the four (drop the foreign key first).
Check: `php artisan migrate` clean;
`Schema::hasColumns('product_deposits', ['price_review','price_review_from','price_reviewed_at','price_reviewed_by'])`
true; `php artisan migrate:rollback --step=1` then `migrate` both clean.

### 3. Model
Files: `app/Models/ProductDeposit.php`
What: constants `PRICE_KEPT = 'kept'`, `PRICE_LOWERED = 'lowered'`,
`PRICE_REVIEWS = [...]`; add the four columns to `$fillable`; casts
`price_review_from` decimal:4, `price_reviewed_at` datetime; relation
`priceReviewedBy(): BelongsTo` to `User`; method
`reviewPrice(?string $outcome, ?User $by, ?float $from = null): self` that
sets the four columns (null outcome clears all four), not saved, mirroring
`decide()`; `isPriceReviewed(): bool`.
Check: `php artisan tinker --execute='(new App\Models\ProductDeposit)->reviewPrice("kept", null)->price_review'` → `kept`.

### 4. `DepositPriceReviewService`
Files: `app/Services/Deposits/DepositPriceReviewService.php` (new)
What: two public methods.

`forRows(Collection $rows, Collection $products): array` keyed by
`product_id`, where `$products` is the controller's keyed collection (must
now carry `PRICESELL`, `PRICEBUY`, `TAXCAT` and eager-loaded `tax`). Returns,
per product (nulls where unknown):
- `net`, `gross`, `vat_rate` from the product;
- `cost` and `cost_on`: `unit_cost` and `deliveries.delivery_date` of the
  latest Udea line (`unit_cost > 0`, supplier ids from
  `DepositEvidenceService::udeaSupplierIds()`, order by delivery_date desc,
  id desc); fallback `cost` = `PRICEBUY` with `cost_on` null and
  `cost_source` = `till` (else `docket`);
- `markup_pct` = `(net − cost) / cost × 100`, null when cost is 0/null;
- `udea_rrp`: `sale_price` of the latest Udea line with `sale_price > 0`;
- `step`: the most recent **lasting** price step, or null. Algorithm over the
  product's `SalesDailySummary` rows with `total_units > 0`, `sale_date ≥
  2024-01-01`, ordered by date: `p = round(avg_price, 2)`. `established` =
  first `p`. For each later row: if `|p − established| ≥ 0.05` **and** the
  next three rows (selling days) all have `|p_next − p| < 0.02`, record
  `['on' => date, 'from' => established, 'to' => p]` and set `established =
  p`; otherwise skip. Return the last recorded step. A change in the final
  three selling days therefore does not show yet (say so in the column's
  title attribute);
- `cost_before_step`: when `step` exists, `unit_cost` of the latest Udea line
  dated **before** `step.on` (null if none).
Two queries for the whole table, not per row: one on `delivery_items` joined
to `deliveries` for all product ids (take what is needed in PHP), one on
`sales_daily_summary` for all product ids ordered by `product_id, sale_date`
(group in PHP). Around 60 products and ~150 summary rows each; no POS query
beyond the products already loaded.

`lowerByDeposit(ProductDeposit $row, User $by): array{ok: bool, message: string}`:
load the product (`Product::find`) and tier (`$row->barrelCode`); refuse with
a message when the product is missing, the tier is null, or
`round(gross − unit_price, 2) <= 0`. Otherwise `newGross = round(gross −
unit_price, 2)`, `newNet = rate > 0 ? newGross / (1 + rate) : newGross`
(same formula as `ProductController::updatePrice()`, no further rounding),
`Product::whereKey($id)->update(['PRICESELL' => $newNet])`,
`LabelLog::logPriceUpdate($product->CODE)`,
`$row->reviewPrice(ProductDeposit::PRICE_LOWERED, $by, $gross)->save()`.
Message: `"<name>: €2.70 → €2.45 on the till (net 2.7000 → 2.4500); label queued."`
Check: covered by step 8's tests; `php artisan test --filter=DepositPriceReviewServiceTest` passes.

### 5. Controller and route
Files: `app/Http/Controllers/DepositController.php`, `routes/web.php`
What:
- Inject `DepositPriceReviewService`. In `index()`: load products with
  `->with('tax')->get(['ID','NAME','CODE','PRICESELL','PRICEBUY','TAXCAT'])`;
  add `'prices' => $this->prices->forRows($rows, $products)`,
  `'sort' => $sort`, `'reviewedCount' => $rows->whereNotNull('price_review')->count()`.
  `$sort = $request->query('sort')` in `['status','markup','change']`, default
  `status`. For `markup`: sort `$rows` by `prices[product_id]['markup_pct']`
  descending, nulls last; for `change`: by `step.on` descending, nulls last.
  Default keeps the existing order.
- New method `priceReview(Request $request, ProductDeposit $productDeposit)`:
  validate `action` in `keep|lower|reset`. `keep` →
  `reviewPrice(PRICE_KEPT, $user)->save()`, flash
  `"<code>: price kept at €x.xx."`. `lower` → service; on `ok` flash success,
  else `back()->withErrors([...])`. `reset` → `reviewPrice(null, null)->save()`,
  flash `"<code>: review cleared (price unchanged)."`.
- Route inside the existing group, after line 721:
  `Route::patch('/deposits/products/{productDeposit}/price', [DepositController::class, 'priceReview'])->name('deposits.products.price');`
Check: `php artisan route:list --name=deposits` shows nine routes including
`deposits.products.price`.

### 6. View
Files: `resources/views/deposits/index.blade.php`
What, in the products card:
- Header: after the status select, a `sort` select in the same GET form
  (`Order: by status / highest markup / latest price change`), submitting on
  change like the status select; keep both values across submits. A small
  line under the heading: `"Price review: {{ $reviewedCount }} of {{ $rows->count() }} rows decided"`.
- Table: after **Evidence**, three new columns.
  **Price**: gross in bold (`€2.70`), below in `text-xs`: `net 2.7000 ·
  cost 1.36 (docket 2026-10-05) · markup 99%`; when cost is from the till say
  `cost 1.31 (till)`; a third line `Udea advice €2.59` when known.
  **Last price change**: `1.99 → 2.70 on 2025-12-27` and below `cost then 1.31`
  (from `cost_before_step`) when known; `—` when no lasting step since 2024;
  `title` attribute explaining the rule ("net prices from daily sales; a
  change counts once it holds for three selling days").
  **Price review**: when `price_review` is null, two forms side by side:
  **Keep** (grey) and **Lower by €0.25** (amber; the tier's amount; disabled
  with a title when `gross − unit_price ≤ 0`). The Lower form confirms first:
  `x-data x-on:submit="if (! confirm('Lower <name> from €2.70 to €2.45 on the till?')) $event.preventDefault()"`.
  When reviewed: a pill `kept` (grey) or `lowered from €2.70` (amber), the
  date and user below in `text-xs`, and a small **Reset** form.
- Flash and error blocks already exist; nothing else moves.
Check: `/deposits` renders without errors; each new column shows values for
the lime juice row (gross 2.70, step 1.99 → 2.70 on 2025-12-27, cost then
1.31, Udea advice 2.59); sorting by markup reorders; the console is clean.

### 7. Test helpers
Files: `tests/Concerns/CreatesDepositPosTables.php`
What: create `TAXCATEGORIES` (`ID` string PK, `NAME`) and `TAXES` (`ID`
string PK, `NAME`, `CATEGORY` string, `RATE` decimal(10,6), `RATEORDER`
integer nullable) on the in-memory POS; add
`posTaxRate(string $taxcat, float $rate): void` inserting one category and
one tax (`ID` = `$taxcat`, `CATEGORY` = `$taxcat`). Existing tests keep
passing (they never load `tax`).
Check: `php artisan test --filter=Deposit` → all existing pass.

### 8. Tests
Files: `tests/Feature/DepositPriceReviewServiceTest.php` (new, check first),
`tests/Feature/DepositScreenTest.php`
What:
Service test (`CreatesDepositPosTables`, `RefreshDatabase`), seeding
`Delivery` rows as `DeliveryDepositReconciliationTest::delivery()` does,
`DeliveryItem::create` lines, `SalesDailySummary::create` rows:
- picks the latest docket cost and `cost_on`, ignores lines with
  `unit_cost = 0`, falls back to `PRICEBUY` with `cost_source = till`;
- picks the latest `sale_price > 0` as `udea_rrp`, ignoring 0 and null;
- markup: net 2.70, cost 1.36 → `markup_pct` ≈ 98.5; cost null → null;
- step detection: a run of 1.99 then 2.70 held for ≥ 4 days → step
  `1.99 → 2.70` on the first 2.70 day; a one-day dip 2.89 → 2.82 → 2.89 → no
  step; a change on the last two days → no step; `cost_before_step` is the
  cost dated before the step, not the latest;
- `lowerByDeposit`: product net 2.0325 at 23% (gross 2.50), tier 0.25 →
  `PRICESELL` becomes 2.25 / 1.23 (assert to 4 dp), a `label_logs` row with
  `event_type = price_update` and the product's `CODE`, row has
  `price_review = lowered`, `price_review_from = 2.50`, `price_reviewed_by`
  the user; a 0% product net 2.70 → 2.45; refused (no write, `ok` false) when
  gross − deposit ≤ 0 or the product is missing.
Screen test additions:
- the page shows the three new columns and the review counter;
- `PATCH deposits.products.price` with `action=keep` sets `kept` and does not
  touch `PRICESELL`; `lower` lowers and flashes success; `reset` clears the
  four columns and leaves the lowered price; an invalid action is a 422.
Check: `php artisan test --filter=Deposit` → all pass (51 today on this
machine, shown as `DEPR` because PHP 8.5 deprecates the `PDO::MYSQL_ATTR_*`
constants; that is not a failure) plus the new ones.

### 9. Docs
Files: `docs/features/barrel-deposit-tracking.md`
What: in "Customer Bottle Deposits (2026-10)" → "Screen": the three columns,
the sort, the Keep / Lower / Reset actions and what Lower writes (PRICESELL
via the product-page recipe, `LabelLog` price_update); "Data": the four new
columns; "Routes": `deposits.products.price`; "Services":
`DepositPriceReviewService` with the step rule. In "Going live on
production" add, after step 3, a step **3b. Review prices** pointing at the
column and the Lower button, and note that a lowered price lands in the label
area under "Price changed".
Check: the four places updated; no other file.

## Verification

1. `php artisan test --filter=Deposit` → all pass; paste the summary line.
2. `php artisan test` → no new failures against the baseline (record the
   baseline count from a run **before** your changes, after step 1).
3. `./vendor/bin/pint --test` → clean on changed files.
4. `php artisan route:list --name=deposits` → 9 routes.
5. Browser, logged in as a manager, `/deposits`:
   a. ≈57 suggested rows; tiers 313 / 315 / 9936 On with till codes.
   b. Lime juice row (`8711521947614` or search the name): Price `€2.70`,
      cost 1.36, Udea advice 2.59; Last price change `1.99 → 2.70 on
      2025-12-27`, cost then 1.31.
   c. Sort by highest markup: order changes; sort by latest price change: the
      row with the newest step is first.
   d. Click **Keep** on one row → pill `kept`, counter +1. Click **Reset** →
      buttons return.
   e. Click **Lower by €0.25** on the lime juice row, accept the confirm →
      flash `€2.70 → €2.45`; `Product::find(id)->PRICESELL` = 2.45; a
      `label_logs` row; pill `lowered from €2.70`. Then on `/products/{id}/edit`
      set the price back to 2.70 and click **Reset** on the row, so dev ends as
      it started. Console clean throughout.
6. `git status --short`: only the files named above plus the untouched
   pre-existing state.

## Risks

- **`tax` eager load**: `Product::tax()` is a hasOneThrough with an
  `orderBy`; `with('tax')` on a `whereIn` of ~60 products is one extra query.
  If a product's TAXCAT has no `TAXES` row the rate is 0 (existing behaviour).
- **Step rule false negatives**: products sold rarely (a few units a month)
  take weeks to accumulate three selling days, so a recent change hides. The
  title text explains it; do not loosen the rule to compensate.
- **Daily average noise**: a day mixing full-price and discounted lines gives
  an average between the two; the 0.02 hold tolerance and the 0.05 step
  threshold are deliberate. Record in the report if a real-data row looks
  wrong.
- **Lowering writes the live POS on production** once deployed. The confirm
  dialog and the `price_review_from` record are the safety net; there is no
  undo button by design (fix on the product page, then Reset).
- **Udea advice price and VAT**: the docket's `sale_price` is a Dutch
  consumer price; it is a reference, not a target. Label it "Udea advice".
- **Step 1 on dev writes the dev POS** (three product pairs and a category).
  Expected; the owner will do the same on production.

## Review

Planner, 2026-10-17. Read `implemented.md` to the end; read the service,
controller, model, migration, view, test helper, both test files and the doc
diff; re-ran the deposit suites, pint and `route:list`; re-ran the price facts
on the 57 dev rows and cross-checked the step rule against the till's own
ticket lines.

### Criteria

| Criterion | Result |
|---|---|
| Step 1 evidence on dev | Pass. 756 lines / 64 deliveries backfilled, 765 sightings, 57 suggested, 0 confirmed, tiers 313 / 315 / 9936 on with `DEP-025`, `DEP-070`, `DEP-010` pairs on the dev POS. Unmatched Udea code `48719` noted |
| Migration + model | Pass. Four columns, FK nullOnDelete, rollback clean; `reviewPrice()` / `isPriceReviewed()` mirror `decide()` |
| Service `forRows` | Pass on structure: two queries, grouped in PHP, 0.19 s for 57 rows; cost / advice / markup correct on real rows. **Step rule: see Notes 1 and 4** |
| Service `lowerByDeposit` | Pass. Gross rounded to cents, net by the product-page formula, `PRICESELL` written, `LabelLog` price_update, review columns set; refuses missing product and non-positive result |
| Controller + route | Pass. 9 routes; `priceReview` validates `keep|lower|reset`; sort by markup / change with nulls last; products loaded with `tax` |
| View | Pass on read-through. `x-on:`, `@js` for the confirm text, `?.`-free (no `x-show` over nullables), colspan 11 |
| Tests | Pass. 67 deposit tests green (shown `DEPR` for PHP 8.5 PDO constants); full suite 16 failures, identical set before and after; pint clean |
| Docs | Pass, with Deviation 2 |
| Browser check (Verification 5) | **Not done**: the Implementer session had no browser. Kernel-rendered checks and the controller calls stand in; the console was not watched. **Owner**: click through 5c–5e once (below) |

### Deviations

All five **accepted**. 1: the HTTP kernel render and direct controller calls
exercise the same code; the gap (CSRF, console) is covered by the owner's
click-through. 2: one Routes block is better than a stray sub-section. 3: a
form PATCH redirects on validation, so JSON is the right way to assert 422.
4: rounding the gross to cents before subtracting is what the shelf price is;
it is also what the plan's own test expected. 5: the files that existed before
the baseline run were unused by existing code.

### Notes for Planner

1. **Fewer steps than measured (29 vs 38)**: the Implementer was right to
   look. The root cause is the plan's source, not the rule. `sales_daily_summary.avg_price`
   mixes discounted lines (staff / member discounts of 5 % and 10 %) with
   full-price lines, so noisy sellers never hold three days within 2 cents
   (Wisselwaar Basmati: 5.20, 4.94, 5.07, 4.72 … → no step) and some products
   are sold discounted almost every time (Landpark Carbonated Lemon: 1.35 =
   1.4228 × 0.95 on all but two days). The till records the discount on the
   line itself: `TICKETLINES.ATTRIBUTES` carries
   `<entry key="product.name">… - 5%</entry>` on a discounted line and the
   plain name on a full-price line. Querying the till's lines and dropping
   discounted ones gives a clean series. **Fixed now**: step 10 (Revision 2)
   below.
2. **Barcode in 5b**: plan error; `8711521947614` is the Apple-mango 200ml
   on this POS. Noted, no action.
3. **"four deposit migrations"** in the runbook: **fixed now**, step 11.
4. **Nothing newer than June**: true under both sources; the rule is not
   hiding anything on this data. But a change made **after the last sale** is
   invisible to any sales-based rule: Ommelanden Buttermilk 1l is on the till
   at net 2.50 while every sale since 2026-06-10 was at 2.80 — someone has
   already lowered it. **Fixed now** in step 10 with a "changed since last
   sale" fact.
5. Dev `label_logs` rows: accepted, no action.
6. Keep on a product missing from the till: **rejected**, harmless and the
   row still says "not on till".

### For the owner

- On `/deposits` in a browser, once Revision 2 is in: sort by markup and by
  latest price change; Keep then Reset a row; Lower the lime juice
  (`8711521925056`), accept the confirm, check the flash and the row, put the
  price back on the product page, Reset. Watch the console.
- The dev POS now has the three `DEP-*` pairs and the "Bottle Deposits"
  category, and the lime juice carries two `price_update` label-log rows.

**Revision 2** — steps 10 and 11 below. Status back to READY. The Implementer
reports in the same `implemented.md` under a new `## Revision 2` heading.

## Steps (Revision 2)

### 10. Price history from the till's own lines
Files: `app/Services/Deposits/DepositPriceReviewService.php`,
`tests/Concerns/CreatesDepositPosTables.php`,
`tests/Feature/DepositPriceReviewServiceTest.php`,
`resources/views/deposits/index.blade.php`,
`docs/features/barrel-deposit-tracking.md`

Why: see Review note 1. The daily summary averages discounted and full-price
lines together; the till's lines say which is which.

What:
- Replace the `SalesDailySummary` query in `forRows()` with one query on the
  `pos` connection. There is no Eloquent model for `TICKETLINES`, `TICKETS`
  or `RECEIPTS`; use the query builder with a comment saying so, as
  `DepositPosService` does for `CATEGORIES`. Read-only. Shape, measured at
  0.28 s for the 57 dev products (the `REGEXP` version took 1.3 s; do not use
  it):
  ```php
  DB::connection('pos')->table('TICKETLINES as tl')
      ->join('TICKETS as t', 't.ID', '=', 'tl.TICKET')
      ->join('RECEIPTS as r', 'r.ID', '=', 't.ID')
      ->whereIn('tl.PRODUCT', $ids)
      ->where('r.DATENEW', '>=', self::HISTORY_FROM)
      ->where('tl.UNITS', '>', 0)
      ->selectRaw(
          'tl.PRODUCT as product_id, DATE(r.DATENEW) as sale_date, ROUND(tl.PRICE, 2) as price, '
          .'COUNT(*) as lines, SUM(CASE WHEN tl.ATTRIBUTES LIKE ? ESCAPE ? THEN 1 ELSE 0 END) as discounted',
          ['% - _%!%</entry>%', '!']
      )
      ->groupBy('product_id', 'sale_date', 'price')
      ->orderBy('product_id')->orderBy('sale_date')
      ->get();
  ```
  The `LIKE` matches a `product.name` entry ending in ` - 5%` / ` - 10%` etc.
  (the literal `%` escaped with `!`, which works on MySQL and SQLite alike;
  `ProductSearchService` uses the same escape character). On dev it flags 616
  of 6,157 lines, the same set as the regex.
- Daily full price per product and day: among the groups with
  `lines − discounted > 0`, the price with the most full-price lines, ties to
  the higher price. A day with only discounted lines is skipped. Feed that
  series (objects with `sale_date`, `avg_price` as today, or rename the key
  and adjust `lastLastingStep()`) to the unchanged `lastLastingStep()`.
- New facts: `last_sold` = `['on' => date, 'net' => price]` of the last
  full-price day, or null; `changed_since_sale` = true when `last_sold` exists
  and `|net − last_sold.net| ≥ 0.05`.
- View, "Last price change" column: when `changed_since_sale`, add a line in
  amber: `now 2.50, sold at 2.80 until 2026-07-11 (no sale since)`. The
  column `title` becomes "Full-price till lines (discounted lines ignored);
  a change counts once it holds for three selling days".
- `sortRowsBy` for `change`: unchanged (by `step.on`).
- Test helper: add `RECEIPTS` (`ID` string PK, `MONEY` string nullable,
  `DATENEW` datetime), `TICKETS` (`ID` string PK, `TICKETTYPE` int default 0,
  `TICKETID` int, `PERSON` string nullable, `CUSTOMER` string nullable,
  `STATUS` int default 0), `TICKETLINES` (`TICKET` string, `LINE` int,
  `PRODUCT` string nullable, `ATTRIBUTESETINSTANCE_ID` string nullable,
  `UNITS` decimal, `PRICE` decimal(10,4), `TAXID` string, `ATTRIBUTES` text
  nullable; primary `(TICKET, LINE)`), and
  `posSale(string $productId, string $date, float $price, int $units = 1, bool $discounted = false, string $name = 'Lime juice'): void`
  inserting one receipt, one ticket and one line whose `ATTRIBUTES` holds
  `<entry key="product.name">{$name} - 5%</entry>` when discounted and
  `<entry key="product.name">{$name}</entry>` otherwise, wrapped in the
  Java-properties XML as the till writes it.
- Tests: rewrite the three step tests on `posSale()` (same expectations);
  drop the `SalesDailySummary` seeding; add: (a) discounted lines are
  ignored — full price 1.42 on two days among twenty 1.35 discounted days → no
  step, `last_sold.net` 1.42; (b) a day with only discounted lines is skipped;
  (c) `changed_since_sale`: sales at 2.80, `PRICESELL` 2.50 → true with
  `last_sold` = the last 2.80 day; sales at 2.70 and `PRICESELL` 2.70 → false;
  (d) no sales → `step` and `last_sold` null, flag false.
- Docs: the Services paragraph and the Screen column text say "full-price
  till lines since 2024-01-01, discounted lines ignored" instead of the daily
  summary.
Check: `php artisan test --filter=Deposit` all pass; on dev,
`forRows()` for the 57 rows runs under 0.5 s and gives: lime juice step
`1.99 → 2.70 on 2025-12-27`; Landpark Carbonated Lemon no step,
`last_sold.net` 1.42; Ommelanden Buttermilk `changed_since_sale` true (now
2.50, sold at 2.80 until 2026-07-11); Wisselwaar Basmati no step; 25 rows
with a step. Paste the figures.

### 11. Runbook count
Files: `docs/features/barrel-deposit-tracking.md`
What: "Going live on production" step 1: "the four deposit migrations" →
"the five deposit migrations".
Check: `grep -n "deposit migrations" docs/features/barrel-deposit-tracking.md`.

## Verification (Revision 2)

1. `php artisan test --filter=Deposit` → all pass; paste the summary.
2. `./vendor/bin/pint --test` on the changed files → clean.
3. The step 10 dev figures, pasted.
4. `git status --short` unchanged in shape from Revision 1 (no new files
   beyond those already listed).

## Revision 3 — the owner's redesign (Claude Design option "2a")

Planner, 2026-10-17, after Revision 2 was written and before it was started.
The owner designed a new layout for the products table in Claude Design
(project "Incomplete request", file `Bottle Deposits Review.dc.html`, option
**2a**, which builds on 1c and 1a). The Planner read the design through the
design MCP; the mock-up's script shows the intended behaviour. This revision
replaces step 6's view and the view part of step 10, and adds the backend the
design needs. **Implement in this order: 10, 12, 13, 14, 15, 16, 11.**
Revision 2's Review and step 10 text above still stand except where this
section says otherwise.

### What 2a is

A single page, top to bottom:

1. **Deposit codes strip**: one cell per Udea deposit code (code and price in
   bold, description, a checkbox). In our terms: the tiers card, one cell per
   tier, checkbox = **Charge customers** (the design's "Create in till" label is
   the same switch; keep our wording), with the till product codes under it
   once created and the confirmed count.
2. **Review band**: kicker "Supplier · Udea", heading "Price review",
   "n of m decided"; a **Delivery charge %** stepper (−, number, %, +); a
   **Group by** segmented control (Tier / Brand / None); a **Sort** select
   (Brand / name A–Z; Markup, lowest first; Sales volume, highest first);
   **Confirm all suggested (n)**.
3. **Grid**, eight columns: Product (name; barcode · Udea code; tier select) ·
   Sales (evidence, last date, "also 322 (36 units)") · Price (gross, large) ·
   Markup now (percentage against **landed cost** = Udea cost × (1 +
   delivery %), a thin bar, "landed x · net y") · Udea (advice price) ·
   **New price** (segmented Keep / −€0.25, plus a € input for a custom price;
   a custom value tints the cell) · **New markup** (recomputed live as the
   choice or the typed price changes: percentage, "+n pts", bar, "€x profit /
   unit") · Decision (Confirm ✓ and Reject ✗ icon buttons while suggested; a
   status tag and **Undo** once decided; decided rows at 55 % opacity).
   Rows sit under **group headers** (e.g. "Tier 313 · €0.25 deposit · 12
   products" with a "Confirm n" button).
4. A footer line: "Markup = net price ÷ (Udea cost × (1 + delivery)) − 1. Net is
   the price ex VAT. New net uses each product's own price-to-net ratio."

The design's key change of meaning: **the price choice and the deposit
decision become one step.** The owner picks Keep / −deposit / a custom price,
then **Confirm** applies both: the row becomes confirmed and the chosen price
is written to the till. **Undo** takes the row back to suggested.

### Planner decisions (the design leaves these open)

- **Styling**: take the layout, hierarchy and controls from 2a; render them
  with the app's existing Tailwind palette inside `x-admin-layout` (indigo
  accent, the app's radii and fonts). Do **not** import the Modernist
  stylesheet or Archivo: one red zero-radius page inside an indigo office
  would look broken. Keep what gives 2a its clarity: 2px rules between
  sections, 11px uppercase tracked column headers, `tabular-nums` on every
  number, thin markup bars, the 55 % opacity on decided rows.
- **Default choice is Keep**, not the mock's "lower". Bulk confirms (header
  "Confirm all suggested" and the group "Confirm n") confirm the deposit
  mapping only and **never touch prices**; label them "(prices unchanged)" in
  the title attribute. Only a row's own Confirm applies that row's choice.
- **Undo** reverts the price too when the row's confirm changed it (we hold
  the gross before in `price_review_from`): status back to suggested, price
  restored through the same recipe (so a label-log row is written), review
  columns cleared. The current Reset action (clear review only) stays on the
  backend for the tests but is not shown.
- **Last price change** is not a column in 2a, but it is what Revision 2
  pays for. Show it as the small text under the gross price in the Price
  cell (the step line and the amber "changed since last sale" line). Nothing
  else from Revision 2 changes.
- **Sales column**: the mock labels delivery evidence "Sales". We can do
  better now that step 10 reads the till's lines: line 1 "sold n in 12 mo",
  line 2 "last sale yyyy-mm-dd", line 3 the delivery evidence ("Udea 38
  units · 5 dockets, last 2026-10-05"), line 4 the alias in accent. Sort
  "Sales volume" uses the sold count.
- **Status filter** (dropped in 2a, present in 1a) stays, as a segmented
  control in the review band: Suggested n / Confirmed n / Rejected n / All.
  Without it rejected rows could never be seen.
- **Till sync** moves to the page header: "Till synced <ago>" (latest
  `pos_synced_at` over all rows, or "never") with the Sync till now and
  Recompute suggestions buttons. A row's `pos_sync_error` shows as a red tag
  in its Decision cell.
- **Delivery charge %** is a shop-wide setting, default 15, stored in the
  existing `app_settings` table (key/value, used today through the query
  builder by three controllers). It gets a small Eloquent model.
- **Brand** = the first word of the till name (as the mock does). Crude but
  cheap; the owner can group by tier instead.
- **Custom price** is written as typed (gross), through the same recipe as
  Lower; recorded as `price_review = 'set'` with the previous gross in
  `price_review_from`.
- The **Add a product** card is unchanged and stays at the bottom.
- No Livewire, no new JS build step: Alpine (3.x, already loaded) does the
  live markup maths per row.

### 12. Delivery charge setting
Files: `app/Models/AppSetting.php` (new, check first), `app/Http/Controllers/DepositController.php`, `routes/web.php`
What:
- `AppSetting`: table `app_settings`, `$primaryKey = 'key'`, string,
  `$incrementing = false`, fillable `key`, `value`; statics
  `get(string $key, mixed $default = null): mixed` and
  `set(string $key, mixed $value): void` (updateOrCreate). Values are stored
  as strings; callers cast.
- Key `deposits.delivery_pct`, default `15`. Controller reads it as float in
  `index()` and passes `deliveryPct`.
- New method `updateSettings(Request)`: validate `delivery_pct` numeric
  0–100; `AppSetting::set(...)`; `back()` with flash
  `"Delivery charge set to 16%."`. Route in the group:
  `Route::patch('/deposits/settings', [DepositController::class, 'updateSettings'])->name('deposits.settings');`
Check: tinker `App\Models\AppSetting::set('deposits.delivery_pct', 16); App\Models\AppSetting::get('deposits.delivery_pct')` → `"16"`; route listed (10 routes now).

### 13. Price choice on confirm, custom price, undo
Files: `app/Http/Requests/UpdateProductDepositRequest.php`,
`app/Http/Controllers/DepositController.php`,
`app/Services/Deposits/DepositPriceReviewService.php`,
`app/Models/ProductDeposit.php`
What:
- Model: `PRICE_SET = 'set'`; `PRICE_REVIEWS` gains it.
- Service: refactor the write into a private `writeGross(Product $product, float $newGross): array{net: float}` (gross→net by the product's rate, `PRICESELL` update, `LabelLog::logPriceUpdate`), used by `lowerByDeposit()` and by two new methods:
  - `setGrossPrice(ProductDeposit $row, User $by, float $newGross): array{ok, message}`: refuses when the product is missing or `$newGross <= 0`; writes; `reviewPrice(PRICE_SET, $by, $grossBefore)`; message `"<name>: €2.70 → €2.49 on the till (net …); label queued."`.
  - `undo(ProductDeposit $row): array{ok, message}`: if `price_review` is `lowered` or `set` and `price_review_from` is set and the product exists, write `price_review_from` back as the gross (label-log row) and say so; then `reviewPrice(null, null)` and `decide(STATUS_SUGGESTED, null)`; save; `DepositPosService::syncProduct()` (clears the deposit attributes, as Reject does). Message `"<code>: back to suggested; price restored to €2.70."` or `"… ; price unchanged."`.
- Request: add `price_action` nullable `in:keep,lower,custom`, `new_price` `required_if:price_action,custom`, numeric, `gt:0`, `max:9999.99`; `price_action` is only honoured when `status` is `confirmed`.
- Controller `updateProduct()`: after the existing status/tier handling and the till sync, when `status === confirmed` and `price_action` present: `keep` → `reviewPrice(PRICE_KEPT, user)->save()`; `lower` → `lowerByDeposit`; `custom` → `setGrossPrice`. On a refused price write the deposit confirmation still stands; flash the service message as an error (`withErrors(['price_review' => …])`) in addition to the success flash.
- Controller `priceReview()`: add action `undo` → service `undo()`; flash its message.
- Controller `confirmAll()`: accept optional `barrel_code_id` (exists) and `brand` (string, max 40); filter the suggested rows (brand = first word of the till name, compared case-insensitively; products loaded in one query). Prices untouched. Flash unchanged in shape.
Check: covered by step 16; `php artisan test --filter=Deposit` green.

### 14. Groups, sort, filter, sales facts
Files: `app/Services/Deposits/DepositPriceReviewService.php`, `app/Http/Controllers/DepositController.php`
What:
- Step 10's ticket-line query also selects `SUM(tl.UNITS) as units`. New facts per product: `sold_12m` (units over the last 365 days, discounted lines included), `last_sale_on` (latest date of any line, or null).
- `forRows(Collection $rows, Collection $products, float $deliveryPct)`: new facts `landed` = cost × (1 + pct/100) (null without cost), `markup_pct` now against **landed** (not bare cost), `profit` = net − landed, `vat_ratio` = gross / net (1 when net is 0).
- Controller `index()`: query params `status` (as today), `sort` ∈ `name|markup|sales` (default `name`; `markup` ascending = lowest first, nulls last; `sales` by `sold_12m` descending, nulls last), `group` ∈ `tier|brand|none` (default `tier`). Build `$groups`: list of `['key', 'title', 'sub', 'rows', 'open' (suggested count), 'confirm' => ['barrel_code_id' => …] or ['brand' => …]]`. Tier title "Tier 313", sub "€0.25 deposit · 12 products"; brand title the word, sub "n products"; `none` → one group with `show = false`. Sort rows inside each group. Drop the old `sort` values `status|change` and `sortRowsBy`'s change branch.
- Pass to the view: `groups`, `group`, `sort`, `deliveryPct`, `lastSyncedAt` (max `pos_synced_at`), `counts` for the filter (suggested / confirmed / rejected totals, not filtered), `decidedCount` (rows shown with status ≠ suggested), `reviewedCount` as before.
Check: `/deposits?group=brand&sort=markup` renders groups in brand order with the lowest markup first; `?group=none` renders one flat list.

### 15. View rewrite to 2a
Files: `resources/views/deposits/index.blade.php`
What (Tailwind, `x-admin-layout`, Alpine):
- **Header slot**: title "Bottle deposits", link "← Barrel codes"; right side
  "Till synced {{ $lastSyncedAt?->diffForHumans() ?? 'never' }}", **Sync till now**, **Recompute suggestions** (existing forms).
- **Deposit codes strip**: `grid grid-cols-2 md:grid-cols-5` of tier cells; each: code (mono, bold) and `€0.25` on one line, description, the **Charge customers** checkbox (existing auto-submit form, `x-on:change`), then till codes `DEP-025 / DEP-025-RET` or "not in till yet", and "n confirmed".
- **Review band**: left block (kicker, "Price review", "{{ $decidedCount }} of {{ $rows->count() }} decided · {{ $reviewedCount }} prices reviewed"); **Delivery charge**: one form PATCH `deposits.settings` with a `−` submit button (`name="delivery_pct" value="{{ $deliveryPct - 1 }}"`), a number input (`name="delivery_pct"`, step 1, min 0, max 100, `x-on:change="$el.form.submit()"`), "%", a `+` submit button; **Status**, **Group by** as segmented radio groups and **Sort** as a select, all in one GET form (`status`, `group`, `sort`), each `x-on:change="$el.form.submit()"`; **Confirm all suggested ({{ $suggestedCount }})** (existing form, `title="Prices unchanged"`).
- **Grid**: `overflow-x-auto`, inner `min-w-[1200px]`, header row and data rows `grid grid-cols-[minmax(0,2fr)_120px_90px_150px_80px_250px_150px_110px]`, 11px uppercase tracked headers, 2px bottom rule; group header rows (title bold 18px, sub, spacer, "Confirm {{ open }}" form → `deposits.products.confirm-all` with the group's hidden filter, disabled with "All decided" when none) shown unless `group=none`.
- **Row** = one `<div x-data="…">` holding `{ choice: 'keep', custom: '', gross, dep, vatRatio, landed }` (numbers from `$prices`, via `@js`), `class="… {{ $row->status !== 'suggested' ? 'opacity-55' : '' }}"`. Cells:
  1. Product: name → `products.edit`; `barcode · Udea 92505` (mono 11px); "Deposit tier" + the existing tier select form.
  2. Sales: `sold {{ sold_12m }} in 12 mo`; `last sale {{ last_sale_on }}`; `Udea {{ sightings_units }} units · {{ sightings_count }} dockets, last {{ last_seen_on }}` (or "added by hand"); alias line(s) in accent from `$details`.
  3. Price: gross bold 20px; small: the Revision 2 step line `1.99 → 2.70 on 2025-12-27` and, when `changed_since_sale`, the amber `now 2.50, sold at 2.80 until …` line; `—` when nothing.
  4. Markup now: `Markup` … `{{ markup_pct }}%` (red when negative), bar `width: min(100, max(0, pct/1.5))%`, `landed 1.56 · net 2.70`. `—` without cost.
  5. Udea: `€2.59` or `—`.
  6. New price (suggested rows only): segmented radios bound with `x-model="choice"` values `keep` / `lower` (label `−€0.25`); € input `x-model="custom"` `x-on:input="choice = 'custom'"`, cell `:class` tint when `choice === 'custom'`. Decided rows: the recorded review as text (`kept` · `lowered from €2.70` · `set from €2.70`, date · user) or `—`.
  7. New markup: Alpine-computed from `newGross = choice === 'keep' ? gross : choice === 'lower' ? gross - dep : parseFloat(String(custom).replace(',', '.')) || 0`, `newNet = newGross / vatRatio`, `newMark = Math.round((newNet / landed - 1) * 100)`, delta `newMark − markupNow` as `+n pts` / `−n pts` / `no change`, bar, `€x.xx profit / unit`. Server-render the Keep values as the initial text so the page is right before Alpine runs and in tests. `—` when landed is null (`x-if`).
  8. Decision: suggested → **Confirm** (icon ✓, `title="Confirm"`; form PATCH `deposits.products.update` with `status=confirmed`, hidden `price_action` `:value="choice"`, hidden `new_price` `:value="choice === 'custom' ? custom : ''"`; `x-on:submit` asks `confirm()` only when `choice !== 'keep'`, text built with `@js` and the computed new price) and **Reject** (icon ✗, `status=rejected`). Decided → status tag (`Confirmed` green / `Rejected` grey), red `till: <error>` tag when `pos_sync_error`, **Undo** (form PATCH `deposits.products.price`, `action=undo`, `x-on:submit` confirm when the row has `price_review_from`: "Undo also restores the price to €2.70. Continue?").
- Footer line with the markup formula, then the unchanged **Add a product** card.
- Rules: `x-on:` never `@`; `?.` on nullables behind `x-show`; all user text through `{{ }}` or `@js`.
Check: `/deposits` renders; the lime juice row shows gross €2.70, Udea €2.59, sold count, step line; choosing `−€0.25` changes New markup live and the Confirm dialog quotes €2.45; typing `2.49` tints the cell and quotes €2.49; Undo on a confirmed-and-lowered row restores €2.70. Console clean (owner, in a browser).

### 16. Tests and docs for Revision 3
Files: `tests/Feature/DepositScreenTest.php`, `tests/Feature/DepositPriceReviewServiceTest.php`, `tests/Concerns/CreatesDepositPosTables.php`, `docs/features/barrel-deposit-tracking.md`
What:
- Helper trait: create `app_settings` only if the Laravel test schema lacks it (it is a normal migration, so it exists under `RefreshDatabase`; just note it).
- Screen test, update the assertions that name old wording: `'Deposit tiers'` → `'Deposit codes'`; `'54 units in 12 lines, last 2026-07-15'` → `'54 units · 12 dockets, last 2026-07-15'`; `'Price review: 0 of 1 rows decided'` → `'0 of 1 decided'`; drop `'Last price change'` and the `<option value="markup" selected>` assertions; keep `'Confirm all suggested (1)'`, `'Add a product'`, the picker assertions, `'Statiegeld 7'`, `'Lower by €0.25'` becomes `'−€0.25'`.
- Screen test, add: group headers (`Tier 313`, `€0.25 deposit · 1 product`); `?group=brand` shows the brand; `?sort=markup` orders lowest first (two rows, assertSeeInOrder); confirm with `price_action=lower` → status confirmed **and** `PRICESELL` lowered, review `lowered`; `price_action=custom&new_price=2.49` → `set`, `PRICESELL` 2.49 (0 % product); `status=rejected` with `price_action=lower` → price untouched; `confirm-all` with `barrel_code_id` confirms only that tier and leaves prices; `undo` on a lowered row → suggested, price back to 2.70, review columns null, a second label-log row; `PATCH deposits.settings delivery_pct=20` persists and the page shows `landed` recomputed; `delivery_pct=150` → validation error.
- Service test, add: `landed` and `markup_pct` with 15 % (cost 1.36 → landed 1.564, net 2.70 → 72.6 %); `sold_12m` counts discounted lines too and excludes a sale older than 365 days; `last_sale_on`; `setGrossPrice`; `undo` restores and clears.
- Docs: rewrite the Screen sub-section for 2a (strip, review band, grid, the one-step confirm, Undo, bulk confirms never change prices, delivery setting); Data: `price_review` values `kept | lowered | set`, `app_settings` key `deposits.delivery_pct`; Routes: `deposits.settings`; Services: landed markup. Runbook 3b: "pick Keep, −deposit or a price, then Confirm".
Check: `php artisan test --filter=Deposit` green; pint clean.

## Verification (Revision 3, replaces Revision 2's)

1. `php artisan test --filter=Deposit` → all pass; paste the summary.
2. `php artisan test` → no new failures against the 16-failure baseline.
3. `./vendor/bin/pint --test` on changed files → clean.
4. `php artisan route:list --name=deposits` → 10 routes.
5. Step 10's dev figures (lime juice step, Landpark no step, Buttermilk changed-since-sale, 25 rows with a step) plus `sold_12m` for the lime juice.
6. `/deposits` rendered as a manager: groups by tier, Delivery charge 15 %, the strip with 5 codes and 3 on; `?group=brand&sort=markup` and `?group=none&sort=sales` render; one real confirm with `−€0.25` on the lime juice then Undo, ending with `PRICESELL` 2.70 and the row suggested (two more label-log rows on dev are fine).
7. `git status --short` lists only the files named in steps 2–16.

## Risks (Revision 3)

- **One-step confirm writes the till**: a mis-click on Confirm with `−€0.25`
  selected changes a live price. The confirm dialog fires for every non-Keep
  choice and Undo restores from `price_review_from`. Default Keep keeps bulk
  actions harmless.
- **Alpine state per row** on 57 rows is fine; make sure the hidden inputs
  are inside the Confirm form and bound with `x-bind:value`, not `x-model`.
- **Brand grouping** by first word misfiles "Your Organic Nature" under
  "Your". Accepted.
- **Landed markup** changes every markup figure shown so far (cost × 1.15):
  say so in the footer line and the docs so nobody compares with the old
  numbers.
- **Width**: eight fixed columns need ~1200px; the grid scrolls horizontally
  below that. The office screens are wider.

## Review (Revision 3)

Planner, 2026-10-17. Read `implemented.md` to the end; read the service,
controller, request, model, `AppSetting`, routes, the whole view, the test
helper, both test files and the doc diff; re-ran the deposit suites, pint and
`route:list`; the owner sent a screenshot of the live page.

### Criteria

| Criterion | Result |
|---|---|
| Step 10 till-line history | Pass. `tillLineGroups()` with the escaped `LIKE` (616 / 6,157 on dev, 0.21 s), full-price days, `last_sold`, `changed_since_sale`; dev figures match the Planner's prototype (lime juice step, Landpark none, Buttermilk flagged, 25 rows with a step) |
| 12 delivery setting | Pass. `AppSetting` model, key, default 15, route, validation 0–100 |
| 13 one-step confirm, custom, undo | Pass. `writeGross()` shared; `setGrossPrice()`, `undo()` restores from `price_review_from` and clears the till attributes; the confirmation stands when a price write is refused; `confirmAll` by tier or brand never touches prices |
| 14 groups, sort, filter, sales facts | Pass. Tier / brand / none; name / markup asc / sales desc with nulls last; `sold_12m`, `last_sale_on`, `landed`, `profit`, `vat_ratio` |
| 15 view | Pass on behaviour (headless Chrome: live markup, confirm texts, real confirm with −€0.25 then Undo, console clean). **Fail on density**: see Revision 4 |
| 16 tests, docs | Pass. 92 deposit tests; full suite the same 16 baseline failures; docs rewritten for 2a |
| 11 runbook count | Pass |

### Deviations

All eight **accepted**. R3-1 (`lines` is reserved in MySQL) was the plan's
error. R3-3 (three forms for −/number/+) is right: one form would post two
`delivery_pct` values. R3-4 (wider container) was needed and is part of why
the Product column now has slack. R3-7 (Remove, "manual", "cost then", the
explanatory paragraph) are correct carry-overs.

### Notes for Planner

1. Ommelanden Low fat yoghurt 1L on the till at 3.95, last sold at 3.30 on
   2026-05-11: **for the owner** to look at; the page shows it in amber.
2. Undo after a till sync error: **accepted**, no change.
3. Brand confirm re-derives the brand: **accepted**, harmless.
4. A row price-reviewed under Revision 1 while still suggested: **rejected**
   for now, no such row exists on dev or production.
5. Headless Chrome and the session-minting scripts: **fixed now**, step 18
   keeps them in the repo for later cycles.

### For the owner

- The page looks different from the design in two ways. The colours, font
  and rounded corners are the Planner's decision (app styling instead of the
  Modernist stylesheet), recorded under Revision 3. The tall rows are **not**
  planned: the Planner put more text (sales, evidence, the price step) into
  the design's narrow columns. Revision 4 fixes the density. Say if you want
  the Modernist look instead; that would be a styling-only revision.
- Ommelanden Low fat yoghurt 1L (note 1).

**Revision 4** — steps 17, 18 and 19 (order: 19, 17, 18; 19 and 17 touch the same view). Status READY. Report under a new
`## Revision 4` heading in `implemented.md`.

### 17. Row density
Files: `resources/views/deposits/index.blade.php`, `tests/Feature/DepositScreenTest.php`
Why: in the owner's screenshot at ~1600px a row is about 110px tall; 2a's
rows are about 60px. The Product column (`minmax(0,2fr)`) has ~400px of
empty space while Sales (120px) and Price (90px) wrap three times.
What:
- Column template → `minmax(0,1.4fr) 160px 125px 150px 70px 240px 150px 100px`
  (same `gap-x-4`, `min-w-[1300px]` stays).
- **Sales** cell, at most three lines, each `whitespace-nowrap`:
  1. `sold 51 in 12 mo · last 2026-10-10` (or `no till sales`);
  2. `Udea 53 units · 13 dockets` with `title="last docket 2026-09-14"`
     (or `added by hand`);
  3. the alias line, when any.
- **Price** cell: the gross, then **one** small line `3.78 → 4.02 · 2025-11-29`
  (`whitespace-nowrap`, the `cost then 2.39` moves into that line's `title`
  together with the existing rule text), then the amber changed-since-sale
  line only when it applies, shortened to `now 2.50, was 2.80 (no sale since
  2026-07-11)`.
- **Product** cell unchanged (three lines already).
- Screen test: `'54 units · 12 dockets, last 2026-07-15'` →
  `'54 units · 12 dockets'` plus `assertSee('last docket 2026-07-15')`; add
  an assertion for the `sold … · last …` line in the price-columns test by
  seeding one `posSale()`.
Check: in headless Chrome at 1600×1000, logged in, measure every row's
`getBoundingClientRect().height` on `/deposits`: the tallest row with a step
line, an alias and a changed-since-sale line is **≤ 84px** and a plain row
≤ 72px; paste the max, min and the lime-juice row height. Save a screenshot
of the first tier group to the session scratchpad and name the path.

### 18. Keep the browser-check scripts
Files: `docs/deposit/scripts/browser/` (new): `boot.php`, `mint.php`,
`cdp.mjs`, `flow.mjs`, `pct.mjs` copied from the Revision 3 session
scratchpad, plus `README.md` (new) saying: dev only, never on production;
what each script does; the one-line invocation that renders `/deposits` as
user 1 and prints console errors; that Chrome is `/usr/bin/google-chrome` on
this machine. Strip anything machine-specific that is not needed (absolute
scratchpad paths → relative to the repo).
Check: the README's invocation runs from the repo root on this machine and
prints `console errors/warnings: []` for `/deposits`.

## Verification (Revision 4)

1. `php artisan test --filter=Deposit` → all pass; paste the summary.
2. `./vendor/bin/pint --test` on changed PHP files → clean.
3. Step 17's row-height figures and screenshot path.
4. Step 18's invocation output.
5. `git status --short`: Revision 3's list plus `docs/deposit/scripts/browser/`, `resources/css/modernist.css`, `vite.config.js`, `resources/views/layouts/admin.blade.php`.
6. Step 19's computed-style figures and screenshot path.

### 19. The Modernist look on `/deposits` (owner, 2026-10-17: "easier to read than our existing")
Files: `resources/css/modernist.css` (new), `vite.config.js`,
`resources/views/layouts/admin.blade.php` (one line),
`resources/views/deposits/index.blade.php`, `docs/features/barrel-deposit-tracking.md`
Why: the Planner chose the app's Tailwind styling in Revision 3; the owner
prefers the design's own system. Reverses that decision for this page only.
What:
- **Stylesheet.** Copy the design system's `styles.css` (project
  "Incomplete request", `_ds/modernist-…/styles.css`; the Planner has read
  it, the Implementer fetches it through the design MCP or asks the owner
  for the file) into `resources/css/modernist.css`, **scoped**: every rule
  prefixed with `.ds-modernist` (the `body` rules become `.ds-modernist`
  itself: background `--color-bg`, colour, Archivo, 15px / 1.55; the global
  `:focus`, `:focus-visible` and `::selection` rules scoped too, so nothing
  leaks into the sidebar). The `:root` token block stays global (custom
  properties only, harmless). Keep the Archivo `@import` at the top. Add a
  page section at the end, `.ds-modernist .dr-*` classes, transcribed from
  the 2a mock-up's inline styles so the Blade carries classes, not styles:
  `.dr-strip` (5-column grid, 2px rules top and bottom, 1px between cells),
  `.dr-band` (the review band), `.dr-kicker` (11px uppercase .1em
  accent-700), `.dr-head` (grid header: 11px uppercase .08em neutral-700,
  2px rule), `.dr-group` (18px/800 heading font, surface fill, 2px rule),
  `.dr-row` (13px/1.4, 1px rule, 1px cell dividers; `.is-decided` at 55 %
  opacity), `.dr-price` (20px/800 heading font), `.dr-bar` and `.dr-bar > i`
  (4px track and fill), `.dr-custom` (accent-100 fill on a typed price),
  `.dr-muted` (neutral-700), `.dr-accent` (accent-700), `.dr-amber`
  (accent-700 as the design has no amber; keep the meaning by wording).
  Column template per step 17. Dark mode: the page keeps its own light
  ground inside `.ds-modernist`; it does not follow the shell's dark theme.
- **Loading.** Add `'resources/css/modernist.css'` to the Vite inputs. Add
  `@stack('styles')` to `admin.blade.php` right after the `@vite([...])`
  line (the layout has `@stack('scripts')` already, no styles stack). The
  view does `@push('styles') @vite('resources/css/modernist.css') @endpush`.
  `npm run build` locally; production builds on deploy.
- **View.** Wrap everything inside the layout slot in `<div class="ds-modernist">`.
  Replace the Tailwind component classes with the system's: `.btn` with
  `.btn-primary` (Confirm all, ✓ Confirm as `.btn-icon` 32px with the Lucide
  check path from the mock), `.btn-secondary` (Sync, Recompute, group
  Confirm, ✗ Reject as `.btn-icon` with the Lucide x path, −/+), `.btn-ghost`
  (Undo, Remove); `.seg` + `.seg-opt` with the native radios for Status,
  Group by and Keep / −€0.25; `.input` for the Sort select, the tier select,
  the delivery number and the € price input; `.field > label` for the band's
  labels; `.tag .tag-accent` Confirmed, `.tag-neutral` Rejected and kept,
  `.tag-outline` for the till-error; the strip's checkbox with
  `accent-color: var(--color-accent)`. Sizes and spacing as the mock
  (14px cell padding, 16px row inset, 12px small print at 11px). The Sync /
  Recompute buttons and "Till synced …" move from the layout header slot
  into a `.nav` bar at the top of the container (as 1a), with the
  "Bottle deposits" `.nav-brand`; the layout header keeps only the title and
  the Barrel codes link. The Add a product card stays Tailwind inside the
  ground (the picker component is shared); give it a 2px top rule.
  Drop the `dark:` classes the system now replaces. Alpine bindings
  unchanged. Step 17's widths and line budget apply.
- Docs: Screen paragraph: the page uses the Modernist design system from the
  owner's design project, scoped to `.ds-modernist`, light ground always.
Check: in headless Chrome at 1600×1000: `getComputedStyle` of a `.dr-price`
gives `font-family` starting `Archivo` and `document.fonts.check('800 20px Archivo')`
true; the Confirm all button's background is `rgb(236, 48, 19)` and
`border-radius` `0px`; the sidebar's own font is unchanged (compute on a
sidebar link); console clean; row heights per step 17; screenshot of the
strip, band and first group saved to the scratchpad and named. Deposit
tests green (strings unchanged apart from step 17's).

## Review (Revision 4)

Planner, 2026-10-17. Read `implemented.md` to the end (including the fix
after DONE); read `modernist.css` in full, the rewritten view, the layout
and Vite diffs, the browser scripts and their README; re-ran the deposit
suites, pint, `measure.mjs` and `clicks.mjs`; took my own viewport
screenshots of the grid (the Implementer's clip screenshots are blank below
the band because the admin shell scrolls `<main>`, not the document, so the
earlier `r4-*.png` show only the strip and band).

### Criteria

| Criterion | Result |
|---|---|
| 19 Modernist look | Pass. Every rule scoped under `.ds-modernist` (141 prefixed selectors, only `:root` and the font `@import` global); `@stack('styles')` one line in the layout; Vite input; Archivo / `rgb(236, 48, 19)` / 0px measured; sidebar still Figtree; the grid is a faithful 2a: 2px rules, 1px cell dividers, 20px/800 prices, bars, segmented Keep / −€0.25, € input tint, ✓ ✗ icon buttons, dimmed decided rows |
| 17 density | Pass. Rows 69–76px (plain max 69, with extra lines 76), no clipped one-liners, widths measured from the text rather than guessed (Deviation R4-1 accepted) |
| 18 scripts | Pass. `boot.php` refuses outside `local`; throwaway Chrome profiles; relative paths; README with a table; `lib.mjs`, `measure.mjs`, `clicks.mjs` extra and welcome |
| Fix after DONE (hidden radios scrolling the shell) | Pass. Root cause right (`position: absolute` inputs with no positioned ancestor inside a scrolling `<main>`); fix is the correct one line; `clicks.mjs` with real pointer events proves it; Keep / −deposit now clear a typed price |
| Tests, pint | Pass. 92 deposit tests, pint clean |

### Deviations

All five **accepted**. R4-1 and R4-2 are better than the plan's numbers; R4-3
is what the mock does; R4-4 is reuse; R4-5 is the only alert the system has.

### Notes for Planner

1. **25 codes in the strip**: **fixed now**, step 20. The query is cycle 2's
   and was always 25; in a five-column strip it is 550px of crates and
   pallets above the review.
2. **`@stack('styles')` wakes a dormant push on `/fruit-veg`** (a 0.3 s
   entry animation and card transition): **accepted**; that is what the
   author of that page intended. For the owner to glance at.
3. **Confirm all wraps** under the band: **fixed now**, step 21.
4. Long names cut with a tooltip: **accepted**.
5. Forms plugin overrides: **accepted**; noted in the README for the next
   Modernist page.

### For the owner

- Run `/deposits` once yourself in Chrome after Revision 5 and press the
  New-price buttons again; the jump you saw is fixed and tested with real
  pointer events, but you reported it, so you should see it gone.
- `/fruit-veg` now plays its intended card animation (note 2).
- The working tree carries the whole of cycle 4 uncommitted (18 files plus
  the archive move). Once Revision 5 is accepted it is time to commit; that
  is the parked question from this morning.

**Revision 5** — steps 20 and 21, then ACCEPTED if clean. Report under
`## Revision 5` in `implemented.md`.

### 20. Deposit codes strip shows the codes that matter
Files: `app/Http/Controllers/DepositController.php`, `resources/views/deposits/index.blade.php`, `tests/Feature/DepositScreenTest.php`, `docs/features/barrel-deposit-tracking.md`
What:
- Controller `index()`: keep `$tiers` as it is (the till-product lookups and
  `chargeTiers` need every tier). Add `$seenCodes` = distinct
  `DepositSighting::barrel_code` (strings). `$stripTiers` = `$tiers` filtered
  to `charge_customer` **or** `supplier_code` in `$seenCodes`, unless
  `?codes=all`, then all. Pass `stripTiers`, `codesAll` (bool), and
  `$tiers->count()`.
- View: the strip iterates `$stripTiers`. In the strip heading, after the
  explanatory sentence: a link `Show all {{ n }} codes` (to the current URL
  with `codes=all`, keeping `status`, `group`, `sort`) or, when showing all,
  `Show deposit codes only` (same URL without `codes`). Use `request()->fullUrlWithQuery([...])`.
- Test: a tier with no sighting and `charge_customer` off (e.g. "Europallet"
  €15.00) is absent by default and present with `codes=all`; a tier with a
  sighting (seed one `DepositSighting`) is present by default even when off.
  Update `test_page_renders_the_three_cards` if its "Statiegeld 7" tier needs
  a sighting to show (it is `charge_customer` true, so it should not).
- Docs: one sentence under Screen.
Check: on dev the strip shows five cells (313, 315, 322, 9936, 10046) on one
row by default and 25 with `?codes=all`; tests green.

### 21. Confirm all moves into the bar
Files: `resources/views/deposits/index.blade.php`
What: move the **Confirm all suggested (n)** form from the band to the
`.nav` bar, after Recompute suggestions, as `btn-primary` (the bar's only
primary). The band keeps the kicker block, the stepper, Status, Group by and
Sort.
Check: `measure.mjs` or a one-off `ev` of `.dr-band` height at 1600×1000 →
one line of controls (≤ 96px); a viewport screenshot of bar + strip + band +
first rows, taken with `Page.captureScreenshot` **without a clip** after
`document.querySelector('.dr-strip').scrollIntoView()` (clips beyond the
viewport come back blank in this shell); name the path.

## Verification (Revision 5)

1. `php artisan test --filter=Deposit` → all pass; paste the summary.
2. `./vendor/bin/pint --test` on changed PHP → clean.
3. `node docs/deposit/scripts/browser/cdp.mjs /deposits` → console clean;
   `clicks.mjs` → unchanged results.
4. Step 20's strip counts and step 21's band height and screenshot path.
5. `git status --short`: no new files beyond Revision 4's list.

## Review (Revision 5)

Planner, 2026-10-17. Read the Revision 5 report and the diff; re-ran the
deposit suites (93, green), pint, `cdp.mjs`; measured the band (96px, one
line) and the strip (5 cells) myself and took a viewport screenshot: strip,
band, header and Tier 313 rows exactly as 2a lays them out.

| Criterion | Result |
|---|---|
| 20 strip filter | Pass. 5 cells by default (313, 315, 322, 9936, 10046), 25 with `?codes=all`, links keep the other query params; test covers off-with-sighting, off-without, and the link |
| 21 Confirm all in the bar | Pass. Band 96px on one line; the bar's only primary |
| Deviations R5-1, R5-2 | Accepted (2px of padding; the measure script following the button) |

**ACCEPTED.** Archive:
`mkdir -p docs/deposit/archive/2026-10-17-cycle-4-price-review` and move
`plan.md` and `implemented.md` there. The side plan `plan-browser-mcp.md`
(the Playwright MCP for the Implementer) runs next; cycle 5 is the float
report.

Then **commit** (the owner): everything in `git status --short` belongs to
cycle 4 except nothing; suggested message
"Deposits: price review on /deposits (cycle 4) — evidence, landed markup,
one-step confirm, Modernist layout, browser checks".
