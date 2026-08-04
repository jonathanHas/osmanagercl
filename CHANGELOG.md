# Changelog

All notable changes to OSManager CL will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **🍳 Wholesale kitchen products are no longer put on the till when created** (2026-08-01)
  - Pricing a recipe for wholesale created its POS product and immediately added it to `PRODUCTS_CAT`, so a full-batch line appeared on the till alongside retail items and could be rung up by mistake
  - A wholesale batch is sold off-till by invoice, so the product is now created hidden. Till visibility can still be turned on per product the usual way if a batch genuinely needs to be sellable at the counter
  - Existing wholesale products are unaffected — this only changes what happens at creation. Re-pricing an existing product never touched visibility
  - **Modified**: `app/Services/KitchenWholesaleService.php`

### Fixed

- **📦 "Undo Complete & Reopen" could silently remove the wrong stock** (2026-08-04)
  - Completing a legacy delivery writes **no audit trail** — no `STOCKDIARY` row, no `stock_adjustments` row, no model events. It only increments `STOCKCURRENT.UNITS` and sets `deliveriesScan.status = 1`. So `undoComplete()` had to *recompute* what to remove by re-running `getMatchedItems()` / `getScannedNotOnInvoice()`, on the assumption that this reproduces the original amounts exactly
  - That assumption held only for `deliveriesScanItems`. Those queries **also read the POS `delivery` table, a global scratch table with no delivery-id column** that `DeliveryController::syncToLegacy()` truncates and repopulates on *every* legacy sync. And because `getMatchedItems()` groups by `supCode` with each row carrying the same `scanned` value, **one barcode reachable from two supplier codes produces two rows and so a doubled quantity**. Sync a different invoice between completing and undoing, and the undo removes a different amount than completion added — with no warning and no way to notice afterwards
  - Reproduced on the dev POS snapshot against a real completed delivery: adding a second supplier code for an already-scanned barcode made the undo plan to remove **12 units where the scan record says 6** (22 → 28 units overall)
  - New `calculateUndoPreview()` compares what the undo is about to decrement against `deliveriesScanItems`, which is scoped to the `delID` and is never touched by completion and is therefore the ground truth. The completed-delivery banner now shows products to revert, units to remove, and stock before/after, plus a per-product breakdown of any disagreement
  - **When the amounts disagree the button is removed, not disabled**, and `undoComplete()` refuses server-side as well — the route is a plain POST and a rendered preview can be stale by the time it is submitted. It **blocks rather than auto-corrects**: on a mismatch there is no way to tell whether completion added the planned figure or the scanned one, so picking either silently could leave stock quietly wrong
  - Products that would be driven negative are flagged as a warning but do not block — that is expected when the goods genuinely were never received
  - The preview reuses the controller's own item queries rather than reimplementing them, so it cannot drift from the action it checks. It is computed only for completed sessions
  - **Modified**: `app/Http/Controllers/DeliveryLegacyController.php`, `resources/views/delivery-legacy/match.blade.php`

- **🥬 `/fruit-veg/manage` silently truncated its product list on slower machines** (2026-08-01)
  - On an older, slower shop PC the list rendered alphabetically and simply **stopped part-way** — it reached "Raspberries" and ended, with no error and no end-of-list cue. The page has no footer, so a partial list was indistinguishable from a complete one, and staff were making availability and pricing decisions against a range that was quietly missing items. A faster PC loaded the same page fully
  - The truncation was **client-side**. The whole product set is inlined as JSON and Alpine builds every row in the browser; a short server response would have produced malformed JSON and *zero* rows, so a partial list meant the render loop ran out of time or memory part-way and left behind what it had already inserted. Where it died depended on the machine, which is why it looked machine-specific
  - **Every product was rendered twice.** The mobile card block is wrapped in `md:hidden`, which is **CSS-only** — Alpine still instantiated the entire mobile subtree for every product on a desktop browser. Both loops are now gated on a `matchMedia('(min-width: 768px)')` flag, so only one builds DOM
  - **Each row fetched the country, class and unit dropdown lists itself** — three `x-init` fetches per row, doubled by the mobile loop, for lists that are global, static and identical everywhere. Measured in headless Chrome: **534 XHRs on the default view and 4,008 on "All"**, against a browser limit of 6 concurrent connections per host, with each request a full authenticated Laravel boot including a database session write. The three lookups are now sent once with the page and inherited through Alpine's scope chain — **0 XHRs**
  - **The list is now capped and always reports its true total.** `getProductsWithVisibility()` already accepted `$limit`/`$offset` but `manage()` passed neither; it now passes `MANAGE_PRODUCT_LIMIT` (500) alongside a new `countProductsWithVisibility()`. The bottom of the table shows either *"Showing all N products — end of list"* or an amber *"Showing the first 500 of N"* banner. **This is the change that would have made the original bug self-evident**, and it guards against any future truncation
  - Measured before → after at desktop width: default view **534 → 0** lookup XHRs, 896 → 451 Alpine components; "All" view **4,008 → 0** XHRs, 105,994 → 58,955 DOM elements, 6,686 → 2,506 Alpine components
  - **Fixed alongside**, all in the same file: `clearFilters()` reset `availabilityFilter` to `'all'`, pulling the entire 668-product catalogue including everything off the till — it now resets to the page default `'available'`. `restoreFilters()` compared against `'all'` while the default is `'available'`, so once `localStorage.fruitVegManageFilters` existed **every page load rendered the full list twice**; it now refetches only when the restored filters differ from what the server rendered. `productMatchesFilters()` compared category IDs against `'001'`/`'002'`/`'126'` while the server uses `SUB1`/`SUB2`/`SUB3`, so a product toggled on via quick-search never reappeared while a category filter was active
  - **N+1 removed**: `getGrossPrice()` resolves the `tax` `hasOneThrough` for every row and it was never eager-loaded — one extra POS query per product. Page load went from **210 to 44 queries**
  - Note that filters live in `localStorage`, not the URL or session — they are per browser profile, so two people on the same login can still see different filter states
  - **Modified**: `app/Http/Controllers/FruitVegController.php`, `app/Services/TillVisibilityService.php`, `resources/views/fruit-veg/manage.blade.php`

### Added

- **💰 Wholesale pricing for kitchen recipes** (2026-07-31)
  - Recipes could only be costed against the retail price of their linked POS product. Selling a **full batch** to a wholesale buyer meant creating the product by hand in uniCenta, with no cost basis, no margin review and no link back to the recipe
  - New page at `/kitchen/wholesale`, reached from a **Wholesale** button on `/kitchen` and a sidebar entry. Lists every recipe with its full-batch cost; entering a price creates or updates a POS product named `"<recipe name> Wholesale"`
  - **One wholesale unit is one full batch** — the cost basis is `calculateRecipeCost()['total_cost']` (ingredients + labour + electricity + packaging for the whole yield), *not* `cost_per_portion`. Pricing a batch against a per-portion cost would undercost it by the portion count, so a test pins `PRICEBUY == total_cost`
  - Prices are entered **including VAT**; `PRODUCTS.PRICESELL` is stored ex-VAT, so every price crosses through the same `inc / (1 + rate)` conversion the products page uses. Ex-VAT price and margin update live in the browser with no round trip
  - **Category and VAT are inherited** from the recipe's linked retail product. Recipes with no linked product are still pricable — the row shows category and VAT dropdowns, and the save is rejected until both are chosen. VAT never silently defaults to 0%, which would store a 23% item's gross price as its net price
  - **Re-pricing updates the existing product rather than duplicating it.** If the link column is ever lost, a name lookup adopts the orphaned product instead of creating a second one; a link pointing at a product deleted in uniCenta clears itself and recreates
  - A price **below batch cost saves with a warning, not an error** — a loss-leader wholesale price is a legitimate decision, and blocking it would just push the work into uniCenta
  - Review aids per row: a target-margin helper (page-wide default plus per-row override) that fills the price hitting that margin, portions and cost-per-portion beside the batch cost, an expandable ingredients/labour/electricity/packaging breakdown, and a **Cost changed** badge when the current batch cost has drifted more than 1% from the cost the price was set against
  - The wholesale product is **not renamed** when the recipe is renamed — `PRODUCTS.NAME` is unique and till button layouts reference it. The row shows the actual POS product name so the drift is visible
  - **Added**: `app/Services/KitchenWholesaleService.php`, `app/Http/Controllers/KitchenWholesaleController.php`, `app/Http/Requests/StoreWholesalePriceRequest.php`, `resources/views/kitchen/wholesale.blade.php`, `database/migrations/2026_07_31_120000_add_wholesale_to_kitchen_recipes_table.php`, `tests/Unit/KitchenWholesaleMarginTest.php`, `tests/Feature/KitchenWholesalePricingTest.php`, `tests/Feature/KitchenWholesalePageTest.php`
  - **Modified**: `app/Models/KitchenRecipe.php`, `app/Repositories/KitchenRepository.php`, `routes/web.php`, `config/kitchen.php`, `resources/views/kitchen/index.blade.php`, `resources/views/layouts/admin.blade.php`

- **🔢 Barcode generation extracted into a service** (2026-07-31)
  - Behaviour-preserving refactor. The next-available-barcode logic was private to `ProductController`, so the wholesale pricing page had no way to reuse it. Barcode uniqueness is a global invariant across `PRODUCTS.CODE` — a second copy would drift, and the failure mode is a duplicate code that breaks scanning at the till
  - Bodies moved **verbatim** into `BarcodeGeneratorService`; the three private `ProductController` methods remain as one-line delegations so no call site changed. `nextForCategory()` folds in the generic-band fallback that `suggestBarcode()` spelled out
  - **Added**: `app/Services/BarcodeGeneratorService.php`, `tests/Feature/BarcodeGeneratorServiceTest.php`
  - **Modified**: `app/Http/Controllers/ProductController.php`

### Changed

- **🍳 Batch-scaled recipes now save the scaling you previewed** (2026-07-31)
  - `saveScaledRecipe()` copied `prep_time` and `cook_time` **verbatim** and recorded the labour/electricity factors only in a notes string. Because recipe cost is derived from the times, the saved recipe recomputed at 1x and **contradicted the preview the decision was based on** — a 3x Mincemeat batch previewed €16.13 of labour and then opened showing €8.25
  - The factors are now applied to the times, which is what actually drives cost: **labour factor scales prep time, electricity factor scales cook time**. The saved recipe recomputes to exactly the previewed figures
  - The preview was rebuilt to match: instead of multiplying the *cost* figures, it derives prep/cook times from the factors and recomputes labour and electricity with the same formulas the server uses. `calculateRecipeCost()` now returns `prep_time`, `cook_time`, `labour_rate`, `electricity_rate`, `cooking_power` and `cook_supervision_factor` so the calculator has the inputs to do this
  - The comparison table gained **Prep time** and **Cook time** rows, and each factor input shows its effect inline (*"Prep 30 → 60 min"*), so the scaling is visible before saving. Notes now record the actual times rather than bare factors
  - Note the factors interact as the cost model does: cook time drives electricity in full **and** a slice of labour via the supervision factor, so raising the electricity factor nudges labour up slightly. That is the real relationship, not an artefact
  - **Modified**: `app/Http/Controllers/KitchenController.php`, `app/Services/KitchenCostingService.php`, `resources/views/kitchen/edit.blade.php`, `docs/features/kitchen-recipe-costing.md`

- **📉 Recipe cost history now records the component breakdown** (2026-07-31)
  - `kitchen_recipe_cost_history` stored only `total_cost`, `cost_per_portion`, `sell_price` and `margin_percentage`, so a snapshot showed *what* a recipe cost but never *why* — there was no way to see whether a rise came from ingredients, labour or energy, and no way to chart labour against energy over time
  - Adds `ingredient_cost`, `labour_cost`, `labour_minutes`, `electricity_cost` and `packaging_cost`. `getCostTrends()` returns the matching series alongside the existing totals
  - **Columns are nullable and existing rows are left alone** — a historical snapshot cannot be broken down after the fact, so a null means "not captured" rather than zero. `KitchenRecipeCostHistory::hasBreakdown()` distinguishes the two; charts should render legacy rows as gaps, not as zeroes. `overhead_cost` (labour + electricity) returns null for those rows rather than a misleading €0.00
  - **Added**: `database/migrations/2026_07_31_090000_add_cost_breakdown_to_kitchen_recipe_cost_history_table.php`, `tests/Feature/KitchenRecipeScalingTest.php` (covers both this and the scaling fix)
  - **Modified**: `app/Models/KitchenRecipeCostHistory.php`, `app/Services/KitchenCostingService.php`, `docs/features/kitchen-recipe-costing.md`

- **👨‍🍳 Kitchen recipe labour no longer charges cook time at the full hourly rate** (2026-07-31)
  - Labour was `(prep_time + cook_time) / 60 × rate`, so the *same* cook time was billed twice — once as a chef's time and again as electricity. A 45 minute oven bake was charged as 45 minutes of direct labour even though nobody stands over the oven, overstating the cost of every cooked recipe and understating its margin
  - Cook time now contributes labour at a **10% supervision factor**: `(prep_time + cook_time × 0.10) / 60 × rate`. A 40 minute cook becomes 4 minutes of labour. **Electricity is unchanged** and still charges the full cook time, which is correct — the oven really is drawing power for all of it
  - Configurable globally via `KITCHEN_COOK_SUPERVISION_FACTOR` (`config/kitchen.php`), alongside the existing labour, electricity and cooking-power rates. Raise it if your recipes are typically hands-on stovetop work rather than oven bakes
  - **Recipe costs are computed on every page load and nothing is stored**, so every recipe picks this up immediately — labour costs fall and margins rise on anything with cook time. Prep-only recipes are unaffected. The worked Apple Pie example in the docs moves from €4.45/portion at 31.5% margin to €3.19/portion at 50.9%
  - The Cost Summary rows previously labelled themselves `Labour (75 min)` using prep + cook; they now show the chargeable minutes with a subtext explaining the split (*"30 min prep + 10% of 45 min cook"*). `total_time` and `formatted_total_time` are untouched — they still mean "how long this recipe takes to make"
  - **Modified**: `config/kitchen.php`, `app/Models/KitchenRecipe.php` (new `labour_minutes` accessor + `getCookSupervisionFactor()`), `app/Services/KitchenCostingService.php` (now returns `labour_minutes` in the costs array consumed by `kitchen.api.costs`), `resources/views/kitchen/edit.blade.php`, `resources/views/kitchen/show.blade.php`, `docs/features/kitchen-recipe-costing.md`
  - **Added**: `tests/Unit/KitchenCostingServiceTest.php` — the labour and electricity calculations previously had no test coverage at all

- **🥬 F&V products now open the full product edit form** (2026-07-29)
  - Clicking a product name on `/fruit-veg/manage` opened a separate, thinner edit page that offered only **display name, country of origin and price**, each saved by its own AJAX call. The "Create Product" links at the top of the same page have always gone to the **generic** product form (`products.create?category=SUB1|SUB2|SUB3`) — so creating a product gave you far more than editing one
  - Product names now link to `/products/{ID}/edit?from=fruit-veg`, the existing full mirror of that create form. F&V lines gain **cost price, VAT category, supplier + supplier code + units per case, outer barcode, live margin breakdown, till button preview, inline stock editing, min-stock override, alternate barcodes** and the supplier-link duplicate check — none of which the old page had
  - **New "Fruit & Veg Details" card** on the edit form, rendered only for `SUB1`/`SUB2`/`SUB3`: **Country of Origin, Class and Unit** selects plus the read-only **price history** table. Class and Unit were previously editable *only* from the manage grid, never on the product's own page
  - **The single-form save replicates every side effect the per-field endpoints had**, which is the part that had to be right: a changed price writes a `veg_price_history` row (old/new **gross**, `changed_by`) and queues `price_change`; changed display name, country, class or unit each queue their own reason on `veg_print_queue`. Without this, F&V price sync and label reprints would have gone quiet with nothing to show for it. The POS write happens first and a bookkeeping failure is logged rather than thrown — losing a queue entry beats rolling back a saved product
  - `/fruit-veg/product/{CODE}` is **kept as a 302** to the new page so bookmarks and the F&V orders review table still resolve. The back button reads "Back to F&V Manage"
  - **Note**: `veg_print_queue` holds one row per product, so when several fields change in one save only the last reason is recorded. The product is still queued, which is what drives the reprint
  - **Modified**: `app/Http/Controllers/ProductController.php` (new `syncFruitVegDetails()`), `app/Http/Controllers/FruitVegController.php`, `app/Http/Requests/UpdateProductRequest.php`, `app/Models/VegDetails.php`, `resources/views/products/edit.blade.php`, `resources/views/fruit-veg/manage.blade.php`, `routes/web.php`
  - **Removed**: `resources/views/fruit-veg/product-edit.blade.php` and the now-unused `FruitVegController::salesData()` + its route. `fruit-veg.product-image` and `product.update-image` are retained (still used by manage, availability, index, sales, waste, order-table, and covered by `FruitVegProductImageTest`)

### Added

- **📊 Highest Value Stock Lines review, with in-place quantity correction** (2026-07-28)
  - A third review panel on the live valuation listing the **top 50 lines by stock value**, excluding anything already shown as a cost anomaly (those are inflated by a wrong *cost*, not a wrong *quantity*, and have their own panel)
  - **Days of cover** is the diagnostic: units on hand divided by the rate the product actually sold over the last 90 days. It separates a large holding of a fast seller from a quantity that cannot be right — *Clipper Everyday Tea* at €635 has 124 days of cover and is fine, while *Bio D Fabric Conditioner 1L* has **771 days** and *Montezumas Choc Buttons* holds €166 having **not sold at all**. Over 365 days is flagged red, over 180 amber, never-sold red
  - **Correct the quantity inline**: type the right number of units (or hit *set to 0*) and save. Shows the resulting stock value before committing, applies via AJAX with no reload, and updates the headline Total Stock Value live from the exact server-returned delta — the same pattern as the cost panel
  - **Reuses the existing `stock_adjustments` audit table** (already used by Stock Review) with a `valuation_high_value` source, recording old stock, new stock and the difference. No new table. Unlike a cost price, a stock figure has no other source to recover it from, so the record is the only way back
  - **Atomic across both databases**: the audit row is written first inside a transaction, then `STOCKCURRENT.UNITS` — a failed POS write rolls the audit back, a failed audit leaves the POS untouched
  - **Lazy-loaded**: the panel fetches its rows only when first opened, so the ~400 ms sales-velocity lookup never lands on page load. The live view stays at ~250 ms
  - **Velocity window is anchored to the most recent stock movement rather than to today**, so the figure stays meaningful against a database snapshot that has stopped receiving sales (as the dev copy has). On a live POS the two are the same day
  - ⚡ Two query costs found and avoided while building this: filtering `MAX(DATENEW)` by `REASON` turns a 1 ms index lookup into a **~600 ms full scan** (`STOCKDIARY` indexes `DATENEW` but not `REASON`), and joining sales history for all products at once costs ~2 s against ~0.4 s for a two-step lookup restricted to the 50 candidates
  - **New**: `getHighValueLines()`, `adjustStockQuantity()`, `fetchSalesVelocity()` in `StockValuationService`; `highValue()` and `adjustStock()` controller actions; routes `management.stock-valuation.high-value` and `.adjust-stock`
  - **Modified**: `app/Services/StockValuationService.php`, `app/Http/Controllers/Management/StockValuationController.php`, `resources/views/management/stock-valuation/live.blade.php`, `routes/web.php`
  - ⚠️ Correcting a quantity fixes the valuation but not the cause — a line showing years of cover usually means stock is not being decremented correctly at the till, which will drift again

### Fixed

- **🐞 Setting origin/class/unit on one F&V product could silently change three others** (2026-07-29)
  - Found while consolidating the three near-identical `vegDetails` create blocks in `FruitVegController` into `VegDetails::upsertForProduct()`
  - `vegDetails.ID` is a `varchar(36)` holding UUIDs, but new rows were given `(int) VegDetails::max('ID') + 1`. `MAX()` over UUID strings returns something like `ffdefbcc-bc78-11eb-…`, which casts to **0** — so every row the app ever created was assigned the literal ID **`1`**. Four rows currently share it: products `000000000212`, `2305`, `2306` and `2307`
  - Because `ID` is the model's primary key, `$detail->update([...])` issued `WHERE ID = '1'` — **writing to all four rows at once**. Setting the country on `2305` also changed it on `2306`, `2307` and `000000000212`. There is no PK constraint on the column (only `UNIQUE(product)`), so nothing ever errored
  - **Two fixes**: new rows get a real `Str::uuid()`, matching uniCenta's own convention for the table; and updates are keyed on **`product`** (the table's only unique index) rather than on `ID`, so the existing colliding rows are safe without a data migration
  - ⚠️ **The four existing rows still share `ID = '1'`** on both dev and production. Nothing in the app now writes by that key, but a manual `UPDATE ... WHERE ID` against `vegDetails` would still hit all four
  - **Modified**: `app/Models/VegDetails.php`, `app/Http/Controllers/FruitVegController.php` (`updateCountry`, `updateUnit`, `updateClass`)

- **⚡ Cost corrections apply without a page reload, and the page loads 4x faster** (2026-07-28)
  - Each cost correction previously did a **full page reload** — ~1,000 ms of server time and a **460 KB** payload, re-running the entire 2,626-product valuation to change one number. Working through the 19 anomaly lines meant 19 reloads, each losing your place in the list
  - **Now AJAX**: the row posts via `fetch` and updates in place. Round trip is **58 ms / 387 bytes** — roughly **17x faster and 1,200x smaller** than the reload it replaces. The row turns green, keeps its position so progress stays visible, and the input and button disable; failures show inline on the row and stay retryable
  - **Headline figures update live** from an exact server-returned delta (`units × (new_cost − old_cost)`) rather than a recalculation — the Total Stock Value card, the anomaly count and the anomaly subtotal all move as you work. Verified against a full recalculation across three consecutive adjustments (including one deliberately under-divided so it stays an anomaly): total, count and subtotal all agree to the cent
  - 🐞 **Fixed a page-load regression introduced with the divisor suggestions**: the `supplier_link` join added to `getCostAnomalies()` was joining across the whole `PRODUCTS × STOCKCURRENT` set to decorate 19 rows, costing **748 ms of the ~1,000 ms page load**. Resolving the anomaly rows first and then looking up their barcodes takes **19 ms** — the same fix already recorded for `SupplierRepository::getSupplierProducts()`. Full page load drops from **~1,000 ms to ~265 ms**, identical data
  - 🐞 **Fixed a 1-cent drift** found while verifying the live totals: `getCostAnomalies()` summed *unrounded* line values while the client applies cent-rounded deltas, so the anomaly subtotal diverged after an adjustment. Line values are now rounded per line throughout, matching `calculateLiveValuation()`. (This shifts the reported negative-stock notional by 2c, to −€142,914.94)
  - **JSON contract** follows the house `expectsJson()` style: 200 with the delta payload, **422** with `errors` for a bad divisor, **404** for an unknown product, and the redirect-with-flash path retained for non-JSON posts. Confirmed through the full HTTP stack including CSRF; on every failure path `PRICEBUY` is untouched and no audit row is written
  - ⚠️ **The panel now requires JavaScript** — it already did in practice, since the divisor preview and controls render inside `<template x-if>`, which produces nothing without Alpine
  - **Modified**: `app/Services/StockValuationService.php`, `app/Http/Controllers/Management/StockValuationController.php`, `resources/views/management/stock-valuation/live.blade.php`

- **📦 Stock Valuation now reports a usable figure and is reachable from the menu** (2026-07-28)
  - The stock valuation feature (`/management/stock-valuation`, added 2026-01-02) had **never been usable**: it had **no navigation link anywhere** — only reachable by typing the URL — and it **totalled to a negative number** (−€86,074.74 live; the sole saved snapshot read −€99,842)
  - **Root cause**: the valuation summed *every* `STOCKCURRENT` row including negative stock. 365 lines carry permanently negative stock (`co Cappuccino` −5,066.7 units, `co Americano` −4,381.1, `Latte` −2,717, plus quiche, loose eggs, loose fruit & veg) — made-to-order and loose lines that are sold at the till but never booked in. Their −€142,914.94 swamped the +€56,840.22 of real stock
  - **Fix**: stock is now floored at zero — a product must be **net-positive across all locations** to be counted (`SUM(UNITS)` grouped per product, `HAVING SUM(UNITS) > 0`). Live valuation now reports **€56,840.22**
  - **Excluded lines are reported, not hidden**: a new amber *"Excluded — Negative Stock"* panel on the live view lists the worst offenders with counts and notional value, so the headline figure is never read without its caveat. Mirrored as an `EXCLUDED - NEGATIVE STOCK` block in the CSV export
  - **New "Cost Price Anomalies" panel**: flags the 19 stocked products whose `PRICEBUY` exceeds `PRICESELL` (€4,713.62 of the total) — typically a case cost entered against a unit price, mostly bulk Refills lines. These *are* included in the total and may inflate it, so they are surfaced for correction rather than silently absorbed
  - **Exact reconciliation**: line values are rounded at source so category subtotals and the grand total are exact sums of the printed detail — header total, category summary and 2,626 detail lines all tie to €56,840.22, and the figure survives `finalize()` (previously the per-category `decimal(12,2)` rounding left the finalized total 2c adrift from its own detail)
  - **Performance**: `calculateLiveValuation()` replaced a full-catalogue `Product::with(['stockCurrent','category'])->get()` + PHP aggregation with a single aggregate query on the `pos` connection (~260ms). Snapshot items now batch-insert in chunks of 500 instead of ~2,626 individual `create()` calls
  - **Audit trail**: new nullable `diagnostics` JSON column on `stock_valuation_snapshots` records what was excluded *at the time the valuation was taken*, so a finalized snapshot stays defensible after the underlying data moves on
  - **CSV export hardened**: switched from hand-rolled `sprintf('"%s",…')` quoting to `fputcsv`, which previously produced a broken file for any product or category name containing a `"`
  - **Navigation**: *Stock Valuation* added to the **Stock** sidebar section, gated `@if(auth()->user()->hasAnyRole(['admin','manager']))` to match the route's `role:admin,manager` middleware (the section itself only excludes baristas)
  - **Basis note for accounts**: stock at cost, **ex-VAT**, at current `PRICEBUY` — a replacement-cost basis, not FIFO or lower-of-cost-and-NRV. Fine for management accounts; confirm with your accountant before using a finalized snapshot in statutory year-end figures. `supplier_link.Cost` was evaluated as an alternative basis and rejected — it covers only 94 stocked products and moves the total by ~€49
  - **New**: migration `2026_07_28_100000_add_diagnostics_to_stock_valuation_snapshots_table.php`
  - **Modified**: `app/Services/StockValuationService.php`, `app/Models/StockValuationSnapshot.php`, `resources/views/management/stock-valuation/live.blade.php`, `resources/views/layouts/admin.blade.php`
  - ⚠️ **Underlying data issue remains**: those 365 negative lines mean stock movement genuinely isn't tracked for those products. Excluding them makes the valuation correct, but booking them in properly (or marking them non-stock) is the real fix

- **🔧 Fix bulk/case cost prices directly from the anomaly panel** (2026-07-28)
  - Each line in the **Cost Price Anomalies** panel now has a **"divide cost by"** box and an **Update cost** button, for the common case where a bulk-container or case cost has been entered against a per-unit sell price (e.g. *TruEco Laundry Detergent Bulk 20L* at €57.35 cost vs €4.01 sell — the cost is for the 20L drum, the sell is per litre)
  - **Live preview before committing**: typing a divisor immediately shows the resulting per-unit cost, the **resulting margin**, and the effect on that line's stock value (`€1,319.05 → €65.95`). Margins above 60% are flagged amber (*"unusually high, check the divisor"*) and negative margins red, so a wrong divisor is visible before it is applied
  - **Suggested divisors**: a click-to-apply chip drawn from `supplier_link.CaseUnits` (where above 1) or the pack size parsed from the product name (`20L`, `10L`, `5L`, `30Bags`, `x12`). Covers **12 of the 19** current anomalies. Deliberately **never auto-applied** — both sources are unreliable (`CaseUnits` is 0 or 1 for several bulk lines), so a human confirms every change. `ml` sizes are intentionally not parsed: a 400ml bottle sold as one bottle needs no divisor
  - **Full audit trail**: new `cost_price_adjustments` table records product, **old and new cost**, sell price, divisor, source and user. The POS keeps no price history of its own, so this is the only route back from a mistaken adjustment
  - **Atomic across two databases**: the audit row (Laravel connection) is written *first* inside a transaction, then `PRICEBUY` (POS connection). A failed POS write rolls the audit back; a failed audit leaves the POS untouched — a cost price can never change without a record of what it was. Verified in both directions
  - **Bounded input**: divisor validated `0.0001–10000` at the controller *and* in the service, with a floor on the resulting cost. (The service-level bounds were added after testing found a divisor of 1,000,000 wrote `PRICEBUY = 0.0001` and *then* failed the audit insert — leaving a corrupted price with no record. Fixed by the transaction ordering above plus `MAX_DIVISOR`/`MIN_COST`)
  - Confirmation dialog names the product and both prices; the panel stays open after an update so the next line can be worked straight away; the live view now renders flash messages (it previously had none, so redirects were silent)
  - **New**: `app/Models/CostPriceAdjustment.php`, migration `2026_07_28_110000_create_cost_price_adjustments_table.php`, route `management.stock-valuation.adjust-cost`
  - **Modified**: `app/Services/StockValuationService.php` (`suggestDivisor()`, `adjustCostPrice()`), `app/Http/Controllers/Management/StockValuationController.php` (`adjustCost()`), `resources/views/management/stock-valuation/live.blade.php`, `routes/web.php`
  - ⚠️ **Check stock units too**: dividing the cost assumes `STOCKCURRENT.UNITS` is already counted in the *selling* unit (litres, not drums). Where that isn't true the stock quantity needs correcting as well — this tool only fixes the cost side

### Added

- **📧 Daily supplier sales email** (2026-07-24)
  - Opt-in feature that emails a supplier a **same-day report of how their products sold**, each evening. A per-supplier **"Email this supplier a same-day sales report"** checkbox on the supplier edit page (`/suppliers/{id}/edit`) turns it on; requires a valid email address and a POS link
  - **Report content**: an HTML table listing every product the supplier sold **so far this calendar month** — the day's sellers first (with that day's units + sales), then the rest of the month's products (0 for the day) — each with running context (this-week, this-month, 12-month average units, trend arrow), followed by day totals and a matching **CSV attachment**. The summary tiles and totals still reflect the report **date** only; the email is still skipped entirely on days the supplier sold nothing
  - **Same-day timing**: `sales:import-daily --today` runs at **20:00** to import the day's partial sales, then `suppliers:send-daily-sales` runs at **20:15** to build and **send synchronously** (`sendNow`) each opted-in supplier's email — **no queue worker required**. Suppliers with no sales that day are skipped. The existing 06:00 `--yesterday` import remains the overnight backfill
  - **Data reuse**: resolves supplier products via POS `supplier_link` (Barcode = `sales_daily_summary.product_code`) and reuses `SalesRepository::getBulkProductSalesStatistics()` for the running context — no new aggregation tables
  - **New**: `App\Services\SupplierSalesReportService`, `App\Mail\SupplierDailySalesMail` + `resources/views/emails/supplier-daily-sales.blade.php`, `App\Console\Commands\SendSupplierSalesEmails` (`suppliers:send-daily-sales {--date=} {--supplier=} {--dry-run}`), migration adding `send_daily_sales_email` to `accounting_suppliers`, `--today` option on `sales:import-daily`
  - **Modified**: `app/Models/AccountingSupplier.php` (fillable/cast + `scopeReceivesDailySalesEmail`), `app/Http/Controllers/AccountingSuppliersController.php` (validation), `resources/views/suppliers/edit.blade.php` (checkbox), `routes/console.php` (schedule)
  - **Preview page** (`/suppliers/daily-sales-preview`, linked from the Suppliers toolbar): lists every opted-in supplier with their products/units/sales for a chosen date and a **"Preview email"** link that renders the exact HTML email in-browser (**nothing is sent**), plus a **CSV** download matching the email attachment. Includes a **"Refresh sales for this date"** button (runs the idempotent import so today's partial figures show before the 20:00 job). The supplier edit page also gains a **"Preview this email →"** link next to the checkbox
  - **"Send" button on the preview page**: sends a supplier's report for the selected date immediately (synchronous `sendNow`, so SMTP errors surface as an on-screen flash) — for testing real delivery. Guarded by a JS confirm showing the recipient address. Same synchronous path the 20:15 job uses
  - **Per-supplier "attach CSV" toggle**: a checkbox on the supplier edit page controls whether the CSV of the figures is attached to that supplier's email. Unticked → the email carries the HTML summary only, no attachment (and the "a CSV is attached" line is dropped). Defaults to **on**. New `attach_sales_csv` column on `accounting_suppliers`; the preview list flags affected suppliers with a **"no CSV"** badge
  - **Per-supplier "include sales values" toggle**: a second checkbox on the supplier edit page controls whether the email + CSV show monetary (€) figures. Unticked → the supplier sees **units and trends only** (no per-product Sales, no Sales-ex-VAT tile, no revenue total, no Revenue CSV column); units, counts, and week/month/12-month context are always shown. Defaults to **on** (no change for existing suppliers). New `include_sales_values` column on `accounting_suppliers`; the preview list flags suppliers with a **"€ hidden from supplier"** badge
  - ⚠️ **Requires a real mail transport**: `.env` is currently `MAIL_MAILER=log` (nothing is delivered). Configure SMTP/SES/etc. before go-live
  - 🐞 **Also fixed**: the app's Laravel-12 schedule is read from `routes/console.php`, not the legacy (unwired) `app/Console/Kernel.php` — the new jobs are registered where they actually run

- **🔢 On-demand product code auto-generate on Create Product** (2026-07-18)
  - Added an **"Auto-generate code"** link below the Product Code/Barcode field on `/products/create`. Previously the auto-barcode suggestion only ran server-side when the page was opened with `?category=<id>`; now it works for the normal flow after picking a category from the dropdown
  - **No category selected** → prompts the user to select a category first (and focuses the select)
  - **Configured category** (the 8 in `config/barcode_patterns.php`) → reuses the existing per-category range logic (`getNextAvailableBarcodeForCategory`)
  - **Any other category** → generates the first free code at/after a generic start (`9000`, above every configured 1000–7999 range) so it never collides with the configured bands
  - The generated code is written into the field and immediately re-validated by the existing duplicate check
  - **New**: `POST /api/products/suggest-barcode` (`ProductController@suggestBarcode` + `getNextGenericBarcode` helper), `generic_start` setting in `config/barcode_patterns.php`
  - **Modified**: `resources/views/products/create.blade.php` (button + JS), `routes/web.php`

- **🖼️ Udea product images on order pages + case/single-unit pricing test** (2026-07-01)
  - The order review table (`/orders/{id}`) now shows the Udea **product image** next to each line item, reusing the existing `x-product-image` component (barcode → Ekoplaza CDN) with hover/tap preview and graceful fallback. Applied to all line-item sections in `resources/views/orders/partials/review-table.blade.php`
  - **New feasibility test page** (`/tools/udea-case-test/{order}`) that scrapes each Udea line item's webshop card and shows the parsed **buy tiers** — units-per-case per the site, single-unit availability and price, and per-unit case price — next to our stored `CaseUnits`, with a raw-HTML toggle for validating the parser. Verified against the live card for product 1118 (case 6 @ €9,12 / €1,52 unit, single @ €1,60)
  - **Only single-unit products (case units = 1) are scraped** — the only ones where a case-buy option is worth discovering (`?all=1` also lists case products, image-only)
  - **Progressive loading**: the page renders instantly and each row fills in real time via a background batch endpoint (`POST /tools/udea-case-test-scrape`), with per-row spinners and a progress bar — no more minute-long blocking loads on large orders
  - **Durable cache**: parsed tier data is written through to a new `udea_product_cards` table keyed by supplier code (30-day staleness), so a product scraped once returns in ~2ms instead of ~4s and stays cached across orders (replaces the previous 1-hour app cache). Raw card HTML is kept only ephemerally for the debug toggle
  - **Manual refresh**: a per-row ↻ button re-scrapes one product, the "Bypass cache" toggle re-scrapes a whole order, and `php artisan udea:refresh-tiers [--order=] [--force] [--limit=]` bulk-populates/refreshes the cache on demand
  - **Modified**: `app/Services/UdeaScrapingService.php` (new `extractPurchaseTiers()` heuristic + `debugProductCard()` with DB write-through, exposing `case_qty` / `single_unit_available` / `single_unit_price` / `per_unit_case_price` / `purchase_tiers`), `routes/web.php`
  - **Order-page integration**: single-unit line items on `/orders/{id}` now show a compact **“📦 Case ×N · €x/u (save y%)”** badge under the product code when Udea offers that product by the case (read instantly from the durable cache). Uncached single-unit products render an empty slot that a **background warmer** fills in live (and persists), so the page never blocks. New partial `resources/views/orders/partials/udea-case-badge.blade.php`
  - **New**: `app/Http/Controllers/UdeaCaseTestController.php`, `resources/views/tools/udea-case-test.blade.php`, `resources/views/orders/partials/udea-case-badge.blade.php`, `app/Models/UdeaProductCard.php`, `app/Console/Commands/RefreshUdeaTiers.php`, migration `2026_07_01_000000_create_udea_product_cards_table.php`

- **🎟️ Gift Voucher Management** (2026-06-25)
  - New **Voucher Management** system: gift vouchers with a unique, randomised, non-sequential CODE-128 barcode, a server-tracked balance, and a full transaction log — scannable and redeemable at the till to prevent forgery and double-spending
  - **Lifecycle / statuses**: `inactive` (generated/printed, not sold) → `active` (issued with a balance) → `exhausted` (spent); plus `deactivated` (admin-disabled, balance preserved). Codes are app-generated (`GV` + 10 chars from an unambiguous charset, CSPRNG via `Voucher::generateUniqueCode()`)
  - **Till screen** (`/vouchers`): minimal tablet/phone UI that **reuses the shared camera scanner** (`resources/js/barcode-scanner.js`, same as `/stocking`) with a photo-decode fallback and manual entry. Scan → activate (enter starting balance) / show balance + deduct / deactivated / exhausted
  - **Double-spend protection**: `deduct()` and `activate()` use `DB::transaction` + `lockForUpdate()` row locking, re-validating the balance under the lock; status flips to `exhausted` at €0
  - **Full audit log**: separate `voucher_transactions` table records every `issue` / `deduct` / `deactivate` / `activate` with amount, balance-after, optional note, user and timestamp (viewable per voucher)
  - **Zebra label printing**: generate N vouchers → preview page (rendered via the in-browser `ZplPreview` emulator) → **Print to Zebra** on the small label (56×30mm). `Voucher::toZplLabel()` builds the CODE-128 ZPL; printing reuses the existing CUPS `lp` raw-print path and `config('services.zebra.*')`
  - **Admin deactivate/reactivate**: admin-only Edit modal on the All Vouchers page to disable/re-enable a voucher with an optional reason; deactivated vouchers keep their balance and are blocked from redemption
  - **Tiered access**: `vouchers.redeem` (employees + managers + admins — scan & deduct only), `vouchers.manage` (managers/admins — activate, generate, print, list), and deactivate/reactivate (`role:admin`). Enforced by route middleware and in the UI; employees get a cut-down till screen that can't activate/generate
  - **New**: `app/Http/Controllers/VoucherController.php`, `app/Models/Voucher.php`, `app/Models/VoucherTransaction.php`, migrations `2026_06_24_120000_create_vouchers_table.php` / `..._120001_create_voucher_transactions_table.php` / `2026_06_25_120000_add_note_to_voucher_transactions_table.php`, `resources/views/vouchers/{index,list,generate,print,transactions}.blade.php`
  - **Modified**: `routes/web.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `resources/views/layouts/admin.blade.php`
  - 📖 [Voucher Management Documentation](./docs/features/voucher-management.md)

- **📄 IIHF (Independent) Goods Return Sheet Generator** (2026-06-16)
  - On the delivery match page (`/delivery-legacy/match`), Independent (IIHF, supplier 37) deliveries can now generate a **pre-filled "Goods Return Record" PDF** for short/missing items — the PDF counterpart to the Udea deviation report
  - Reuses the same desktop-only checkbox selection on the **Qty Mismatches** and **Missing – Not Scanned** tables; adds a **Generate Returns Sheet** button
  - Because the official form is a **flat PDF with no fillable fields**, our data is **overlaid** onto the official form via FPDI/FPDF. The form is converted once with Ghostscript to PDF 1.4 and stored at `public/downloads/iihf-goods-return-template.pdf`
  - Fills per line: Invoice No., Product Code, Description, Quantity, Value (€), VAT (%), and an **X on reason code A (Not delivered)**. Also auto-fills the header/footer: Account Name (Mossfield Organic Store Ltd), IIHF Account No. (20128), Customer Contact Name (Jonathan Haslam) and Date (delivery date)
  - Values are **re-queried server-side** (checkboxes only carry `source:barcode`). `getOnInvoiceNotScanned()` now also selects `TAXES.RATE` so VAT can be filled for missing items
  - **New deps**: `setasign/fpdi`, `setasign/fpdf`. **New route**: `POST delivery-legacy/goods-return-sheet`
  - **New**: `app/Services/IihfGoodsReturnPdfService.php`. **Modified**: `app/Http/Controllers/DeliveryLegacyController.php`, `resources/views/delivery-legacy/match.blade.php`, `routes/web.php`
  - 📖 [IIHF Goods Return Sheet Documentation](./docs/features/iihf-goods-return-sheet.md)

- **📄 Udea Deviation Report Generator** (2026-06-16)
  - On the delivery match page (`/delivery-legacy/match`), Udea deliveries can now generate a **pre-filled Udea deviation report** `.xlsx` for short/missing items, instead of hand-typing the blank template
  - Adds a checkbox column to the **Qty Mismatches** and **Missing – Not Scanned** desktop tables (Udea-only, hidden on mobile) plus a **Generate from Selected** button beside the existing blank-template download
  - Fills the template from row 9: delivery date, order number, article (supplier) code, product name, amount, and deviation = `"Not recieved (Partially)"`. Amount = expected qty for missing items, shortfall (`abs(expected − scanned)`) for mismatches
  - Values are **re-queried server-side** from the same POS queries the page uses (checkboxes only carry `source:barcode`), so the report can't be tampered with. The template (`public/downloads/deviation-report-template-2025.xlsx`) is loaded/re-saved with PhpSpreadsheet so its dropdowns, styling and baked-in customer number are preserved
  - **New route**: `POST delivery-legacy/deviation-report`
  - **Modified**: `app/Http/Controllers/DeliveryLegacyController.php` (`deviationReport()`), `resources/views/delivery-legacy/match.blade.php`, `routes/web.php`
  - 📖 [Udea Deviation Report Documentation](./docs/features/udea-deviation-report.md)

- **🗑️ Fruit & Veg Waste Log** (2026-06-06)
  - New **Log Waste** page under the F&V module (`/fruit-veg/waste`) for recording spoiled/discarded stock, built from a Claude Design handoff (List layout)
  - Default list shows all **till-visible** F&V products (`TillVisibilityService`, `PRODUCTS_CAT` × `SUB1/SUB2/SUB3`) with product image, name, code/category/origin/class meta, current price and an **On till** badge; a debounced **search-all bar** covers the full F&V range via `searchAllProductsWithVisibility()` (results badged **Full range**)
  - **Instant save**: typing an amount upserts that day's row via AJAX (`POST /fruit-veg/waste/entry`, 500ms debounce, per-row saving/saved/failed indicator); a cleared/zero amount deletes the row. `UNIQUE (waste_date, product_code)` keeps one editable row per product per day
  - **Per-row kg/units switching** via a quiet dropdown on the unit label inside the amount field (deliberately low-prominence so it can't be hit by mistake — replaced an always-visible segmented toggle); +/− steppers in units mode. Default unit chain = today's entry → last-used unit for the product → the product's veg unit. `unit_price`/`value` are snapshotted at save time so historical totals stay stable; value is null (—) when logged in a non-priced unit
  - **Live totals bar**: items logged, total kg (+ units), and **est. value lost** (accent `#c2410c`), plus a **Waste report** link
  - Date picker (max today) to edit past days; history page (`/fruit-veg/waste/history`) groups entries by date with counts, per-unit totals, est. value, per-line delete and "Edit this day" links
  - **Mobile responsive**: price/value columns collapse into the product cell below `md`/`lg`, meta condenses to code below `sm`, amount control wraps, numeric keypad via `inputmode="decimal"`, sticky totals bar
  - Records-only — no POS stock changes
  - **New**: `app/Http/Controllers/WasteController.php`, `app/Models/WasteLog.php`, migration `2026_06_06_000001_create_fv_waste_logs_table.php`, `resources/views/fruit-veg/waste.blade.php`, `resources/views/fruit-veg/waste-history.blade.php`, `tests/Feature/WasteLogTest.php`
  - **Modified**: `routes/web.php`, `resources/views/fruit-veg/index.blade.php`

- **🌱 Fruit & Veg Harvest Log** (2026-05-26)
  - New **Log Harvest** page under the F&V module (`/fruit-veg/harvest`) for recording quantities of own-farm produce harvested per day
  - Product list is scoped to supplier **Jon** (own farm, POS `SupplierID = 2`) via the existing `SupplierRepository::getSupplierProducts()`; supplier id referenced from new `config('suppliers.jon')` key
  - **Combo entry**: products harvested in the last 30 days (and anything already logged for the chosen date) appear as quantity inputs ready to fill/edit; remaining Jon products are added via an Alpine search dropdown. All rows post as a single `items[product_code]` map
  - **Per-product unit** (`kg` or `unit`): each row has a kg/unit toggle, and the choice is remembered per product (`harvest_product_units` table) so future harvest logs default to the correct measure (e.g. Rocket 100g → bags/unit, Spinach → kg). Unset products default to `kg`. History totals are grouped by unit rather than summed together
  - Records-only — no POS stock changes or delivery records. A `UNIQUE (harvest_date, product_code)` constraint makes re-saving a date an in-place edit; a cleared/zero quantity deletes that row. `product_name`/`unit` are snapshotted so history survives POS renames/deletions
  - History page (`/fruit-veg/harvest/history`) groups past harvests by date (counts + per-unit totals) with per-line delete and edit links
  - **New**: `app/Http/Controllers/HarvestController.php`, `app/Models/Harvest.php`, `app/Models/HarvestProductUnit.php`, migrations `2026_05_26_145633_create_harvests_table.php` + `2026_05_26_152805_create_harvest_product_units_table.php`, `resources/views/fruit-veg/harvest.blade.php`, `resources/views/fruit-veg/harvest-history.blade.php`
  - **Modified**: `routes/web.php`, `config/suppliers.php`, `resources/views/fruit-veg/index.blade.php`

- **🥬 Dynamis Fruit & Veg Delivery Import (XLSX)** (2026-04-22)
  - New **XLSX (Dynamis)** tab on `/deliveries/create` accepts Dynamis `Historique(NN).xlsx` delivery files
  - Parsed in PHP via PhpSpreadsheet (no Python round-trip). Columns A-L mapped to SKU / description / unit cost / unit type (K/C/P) / line total; the `DIV0010` "MISCELLANEOUS TRANSPORT" row is routed to `freight_charge`
  - Kg rows stored as boxes with `weight_per_unit` (mirrors the Independent weight-based flow). C/P rows use piece count
  - `SupplierLink` is **not** used for matching — F&V SKUs are matched by name. Priority: saved link → fuzzy Jaccard on normalised tokens (threshold 0.75) → unmatched with top-3 suggestions
  - New `dynamis_product_links` table persists confirmed matches so subsequent imports auto-match (`matched_saved`)
  - **Suggest with AI** button runs a bulk AI pass (Mistral / Gemini configurable via new `dynamis_matcher` feature key on `AiSettingsService`) over still-unmatched items
  - Matching scoped to **till-visible** F&V products (`PRODUCTS_CAT` × `CATEGORY IN (SUB1, SUB2, SUB3)`). Unmatched items are dropped unless the user picks one — no new products are created automatically
  - `DeliveryService::importFromPdfData()` now honours a preset `product_id` on incoming items (used by this flow; PDF path unchanged)
  - New routes: `POST /deliveries/parse-xlsx`, `POST /deliveries/ai-suggest-xlsx`, `POST /deliveries/store-xlsx`
  - **New**: `app/Services/DynamisXlsxParserService.php`, `app/Services/DynamisMatcherService.php`, `app/Services/DynamisAiSuggesterService.php`, `app/Models/DynamisProductLink.php`, `resources/views/deliveries/partials/xlsx-tab.blade.php`, `tests/Unit/DynamisXlsxParserServiceTest.php`, `tests/Unit/DynamisMatcherServiceTest.php`, migration `2026_04_22_154911_create_dynamis_product_links_table.php`
  - **Modified**: `app/Http/Controllers/DeliveryController.php`, `app/Services/DeliveryService.php`, `app/Services/AiSettingsService.php`, `routes/web.php`, `resources/views/deliveries/create.blade.php`
  - 📖 [Dynamis Delivery Import Documentation](./docs/features/dynamis-delivery-import.md)

- **🧾 Outstanding Report: Pop-out Invoice Viewer** (2026-04-17)
  - "View Invoice" links on `/suppliers/outstanding-report` now open the invoice's primary attachment in a standalone pop-out window (centered, ~1100x900, resizable) instead of navigating to `/invoices/{id}`
  - Uses the existing `invoices.attachments.viewer-minimal` route — chrome-free PDF/image viewer, same clean pattern as the bulk-upload file viewer
  - Each click opens its own window (unique `Date.now()` window name) so multiple invoices can be compared side-by-side
  - Falls back to `invoices.show` (same-tab) for invoices that have no attachment
  - Eager-loads `attachments` on both the outstanding query and the previous-payments query to avoid N+1
  - **Modified**: `app/Http/Controllers/SupplierOutstandingController.php`, `resources/views/suppliers/outstanding-report.blade.php`

- **📷 Camera Barcode Scanning: Stocking + Product Edit Alternate Barcode** (2026-04-17)
  - **Stocking (`/stocking`)**: Green camera toggle button added next to the barcode input. Tapping opens a live camera viewport using `html5-qrcode`; a detected barcode triggers the existing lookup flow (beep, vibrate, 2s duplicate cooldown, GS1-128 → GTIN-14 parsing)
  - **Persistent camera mode on stocking**: Mirrors the delivery-legacy pattern — once enabled, the camera auto-restarts after a successful **Update Stock** or **Add to Labels**, so the user can scan → adjust → scan continuously without pressing the button again. Pressing the red (active) camera button manually clears the flag and keeps it off
  - **Product Edit alternate-barcode panel**: The "Add alternate barcode for this product" section now has a green camera button that fills the `new_barcode` input from a scan. Camera auto-stops when the collapsible section is closed
  - Requires HTTPS for live camera (browser restriction)
  - **Modified**: `resources/views/stocking/index.blade.php`, `resources/views/products/edit.blade.php`

- **🔒 AI Diagnostics: Admin-only access + provider badge on AI pages** (2026-04-17)
  - `/tools/ai-diagnostics` routes now require `role:admin` middleware; sidebar entry hidden from non-admins
  - New `<x-ai-provider-badge>` component: small pill near the page title on `bulk-upload`, `bulk-upload-preview`, and `labels/translate`, showing which provider (Mistral OCR / Gemini / etc.) is currently configured for that feature
  - Tooltip on hover shows provider + model; non-clickable (users don't link into the diagnostics page)
  - **Modified**: `routes/web.php`, `resources/views/layouts/admin.blade.php`, `resources/views/invoices/bulk-upload.blade.php`, `resources/views/invoices/bulk-upload-preview.blade.php`, `resources/views/labels/translate.blade.php`
  - **New**: `resources/views/components/ai-provider-badge.blade.php`

- **✨ Invoice Bulk Upload: AI Fallback for Failed / Review Parses** (2026-04-17)
  - New "Send to AI" button on bulk-upload preview for files that Python parsers fail on or route to `review` with low confidence
  - Routes the file through the same AI pipeline used by Camera Capture (`InvoiceGeminiParsingService` + `ParseInvoiceCameraImage` job)
  - **PDF support**: Mistral OCR's native `document_url` endpoint is used for PDF invoices (no Ghostscript dependency); non-OCR providers return a clear error pointing to `/tools/ai-diagnostics`
  - **New AI feature key**: `invoice_ai_fallback` -- selectable independently of Camera Capture at `/tools/ai-diagnostics`, defaults to the Camera Capture provider when unset
  - Button visible on `failed` files and on `review` files (except duplicates, which keep their "Delete Duplicate" action); present in both the card and table preview layouts
  - New route: `POST /invoices/bulk-upload/{batchId}/file/{fileId}/send-to-ai`
  - **Modified**: `app/Services/AiSettingsService.php`, `app/Services/InvoiceGeminiParsingService.php`, `app/Jobs/ParseInvoiceCameraImage.php`, `app/Http/Controllers/InvoiceBulkUploadController.php`, `routes/web.php`, `resources/views/invoices/bulk-upload-preview.blade.php`

- **📦 Product Page Consolidation: Show Merged into Edit** (2026-04-16)
  - Product show page (`/products/{id}`) now redirects to edit page (`/products/{id}/edit`)
  - **Quick Stats Bar** added to edit page: Stock Level with inline edit, Stocking Status toggle (AJAX), Min Stock Override (Alpine.js editor, admin/manager), VAT Rate badge
  - **Sales History** collapsible section on edit page with Chart.js chart, time period buttons (4m/6m/12m/YTD), stats cards, monthly table, and detailed sales modal
  - **Requeue Label** and **Print Label** buttons added to edit page header
  - **Kitchen toggle** button added to edit page for marking products as kitchen products
  - **Supplier product images** from Udea, Udea Frozen, and Independent displayed on edit page and create page (uses `x-product-image` component with cached image resolution)
  - All `route('products.show')` links updated to `route('products.edit')` across codebase (deliveries, orders, labels, stocking logs, destock review, supplier code lookup)
  - Stocking management moved from form checkbox to AJAX toggle in stats bar
  - Cancel button now links to products index instead of removed show page
  - **Modified**: `app/Http/Controllers/ProductController.php`, `resources/views/products/edit.blade.php`, `resources/views/products/create.blade.php`, `resources/views/deliveries/show.blade.php`, `resources/views/products/index.blade.php`, `resources/views/labels/index.blade.php`, `resources/views/destock-review/suggestions.blade.php`, `resources/views/supplier-code-lookup/index.blade.php`, `resources/views/stocking/logs.blade.php`, `resources/views/orders/grid-view.blade.php`, `tests/Feature/ProductTest.php`

- **🍳 Kitchen Toggle on Deliveries Page** (2026-04-16)
  - "Kitchen" toggle button added to delivery show page for matched products (mobile card view and desktop table view)
  - Uses existing `/kitchen/products/toggle` AJAX endpoint
  - Orange highlight when product is flagged, gray when not
  - **Modified**: `resources/views/deliveries/show.blade.php`

- **📦 Delivery: Invoice Number Extraction for Independent Supplier** (2026-04-15)
  - Independent (IIH) delivery parser now extracts invoice numbers (e.g., "Invoice No: IN466447") from PDF content
  - Each PDF's invoice number is stored as the order number on delivery items and documents
  - Displayed as badge on delivery items, matching existing Udea order number feature
  - Uses proven regex pattern from IIH RTD invoice parser
  - **Modified**: `scripts/invoice-parser/parsers/delivery_independent.py`

- **💰 Cash Lodgements: Unreconciled Days, Till Close Date & Workflow Fixes** (2026-04-14)
  - **Auto-Creation of Reconciliations**: Page load auto-creates `CashReconciliation` records for POS till closes missing one, importing legacy denomination data so they appear in Pending Bags immediately
  - **Needs Cash Reconciliation Section**: New section showing days where the till was closed but no denomination data exists, with links to cash-reconciliation page
  - **Till Closed Date Column**: Lodgements table now shows the POS till close date alongside the lodgement date, sorted by till closed date descending
  - **Available-to-Lodge Fix**: Removed double-deduction of supplier payments — payments are already taken out before the end-of-day cash count, so the formula is now `Total Cash - Float`
  - **Select All Fix**: Verified bags select-all checkbox now works correctly using Alpine.js getter/setter pattern
  - **Null Denomination Handling**: Empty denomination fields on bag verification treated as 0 instead of throwing integrity constraint error
  - **View Recon Links**: Each pending bag now has a "View Recon" link opening cash reconciliation in a new tab
  - **Hover Highlighting**: Pending bags rows highlight on hover for easier identification
  - **Modified**: `app/Http/Controllers/Management/CashLodgementController.php`, `app/Models/CashReconciliation.php`, `resources/views/management/cash-lodgements/index.blade.php`, `resources/views/management/cash-reconciliation/index.blade.php`

- **AI Integration: Multi-Provider Camera Invoice Capture** (2026-04-11)
  - Phone camera tab on bulk upload page for photographing paper invoices
  - AI-powered extraction of supplier, invoice number, date, total, VAT breakdown, and line items
  - Multi-provider support: Google Gemini, Mistral Vision, Mistral OCR, OpenAI-compatible APIs
  - Per-feature AI configuration -- invoice parsing and label translation can use different providers
  - Admin UI for switching providers/models from System Tools > AI Diagnostics (no server access needed)
  - Database-backed settings (`app_settings` table) with `.env` fallback; API keys always in `.env`
  - Queue-based processing: users snap multiple photos without waiting for AI results
  - 4-layer supplier fuzzy matching (exact, substring, cleaned suffixes, word-based with Levenshtein)
  - Date validation warnings for dates > 2 months old, wrong year, or future dates
  - VAT safety: only assigns rates explicitly shown on invoice, warns when not specified
  - Prominent warning display on preview page with red highlighting for date issues
  - Connection diagnostics page with text, vision, and OCR test endpoints
  - **New**: `app/Services/AiSettingsService.php`, `app/Services/InvoiceGeminiParsingService.php`, `app/Jobs/ParseInvoiceCameraImage.php`, `app/Http/Controllers/AiDiagnosticsController.php`, `resources/views/tools/ai-diagnostics.blade.php`, `docs/features/ai-integration.md`
  - **Modified**: `app/Http/Controllers/InvoiceBulkUploadController.php`, `app/Http/Controllers/LabelTranslationController.php`, `app/Http/Controllers/LabelAreaController.php`, `resources/views/invoices/bulk-upload.blade.php`, `resources/views/invoices/bulk-upload-preview.blade.php`, `resources/views/layouts/admin.blade.php`, `config/invoices.php`, `routes/web.php`
  - **Migration**: `add_parsing_source_to_invoice_upload_files_table`

- **Delivery Legacy: Mobile-Friendly Card Layouts** (2026-04-01)
  - `/delivery-legacy` index: Recent Scan Sessions now display as compact cards on mobile with touch-friendly Select/View Match buttons, merge checkboxes, and inline supplier editing — no horizontal scrolling required
  - `/delivery-legacy/match`: All 7 table sections (Pending, Critical, Warnings, Verified, OOS, Extra, Missing) now show mobile card layouts below `md` breakpoint with color-coded left borders, product images, stock levels, and inline quantity editing
  - Pending section moved to first position so users can immediately check for unscanned items at end of delivery
  - Product images enlarged in mobile cards (`size="lg"`, 64px) with tap-to-enlarge overlay featuring close button — no more accidentally tapping links behind the preview
  - Desktop table views completely unchanged
  - **Modified**: `resources/views/delivery-legacy/index.blade.php`, `resources/views/delivery-legacy/match.blade.php`, `resources/views/components/product-image.blade.php`

### Fixed

- **Legacy delivery match: missing Udea product images on production** (2026-06-16)
  - On `delivery-legacy/match` for Udea (supplier 5), many product images were missing on production while displaying correctly on dev; the newer `deliveries/{id}` page showed all images correctly on both
  - Root cause: the legacy page builds synthetic product objects and resolved Udea images via `getExternalImageUrlBySupplierCode()`, which re-derives the barcode from POS with `supplier_link...->value('Barcode')`. With duplicate/stale `supplier_link` rows on the live POS, `value()` returned an imageless barcode, so the CDN URL 404'd. The new page uses the product's own barcode directly and was unaffected. Not reproducible on dev (separate POS snapshot)
  - Fix: for barcode-keyed suppliers (Udea), `product-image.blade.php` now builds the image URL from the barcode it already has (matching the new page), before falling back to the supplier-code path. `{SUPPLIER_CODE}` suppliers (Independent) are unchanged
  - Hardened `getExternalImageUrlByBarcode()` to guard an empty `image_url` (e.g. Natural Medicine), preventing a PHP 8.2 `str_replace(null)` deprecation
  - **Modified**: `resources/views/components/product-image.blade.php`, `app/Services/SupplierService.php`

- **AI Diagnostics: Mistral / OpenAI "No API Key" on production with cached config** (2026-04-17)
  - Switching any feature to Mistral or Mistral OCR on production surfaced "No API key configured for this provider" even though `MISTRAL_API_KEY` was present in `.env`; Gemini always worked
  - Root cause: `AiSettingsService::getApiKey()` read the Mistral/OpenAI key via `env()` directly. Once `php artisan config:cache` runs (standard on production deploy), Laravel unsets Dotenv variables and `env()` returns `null` outside `config/*.php` files. Gemini survived because its lookup went through `config('gemini.api_key')` first
  - Fix: Mistral, OpenAI, and default branches now read `config('invoices.ai_parsing.api_key')` first, falling back to `env()` — mirroring Gemini's pattern and surviving `config:cache`
  - No `.env` or config file changes required; `config/invoices.php` already exposed `MISTRAL_API_KEY` via the `ai_parsing.api_key` key
  - **Modified**: `app/Services/AiSettingsService.php`

- **Label Translation: Smarter layout with no text overlap** (2026-03-28)
  - Replaced naive character-count line estimation with word-wrap simulation plus 10% safety margin, matching ZPL's actual word-boundary wrapping behaviour
  - Removed hardcoded `^FB` maxLines caps (nutrition was 3, storage/address were 2) — sections now use actual estimated lines, eliminating the mismatch between rendered lines and Y-position advance that caused overlap
  - Downsized product name from `nameFont` (40pt/30pt) to `bodyFont` (28pt/20pt), capped at 1 line, freeing vertical space for ingredients/nutrition/storage
  - Auto-fit scaling now sees true content height and correctly reduces font when needed
  - Added EU-compliant "Per 100g/100ml" requirement to Gemini nutrition extraction prompt
  - **Modified**: `app/Services/ZplGeneratorService.php`, `app/Http/Controllers/LabelTranslationController.php`, `app/Http/Controllers/LabelAreaController.php`

- **F&V Manage: Print queue icon updates instantly** (2026-03-25)
  - "Queued"/"On List" badges and amber row highlight now appear immediately after editing a product (price, country, unit, class, display, or toggling availability on), without requiring a page refresh

### Added

- **Organic Trust Report Enhancements** (2026-03-23)
  - Product Type and Certification Body fields per organic supplier (inline editable dropdowns)
  - Customizable dropdown options via "Manage Dropdown Options" settings panel (stored in `app_settings`)
  - Per-supplier POS sales revenue (ex. VAT) via `supplier_link` → `sales_daily_summary` cross-database query
  - Suppliers with sales but no invoices (e.g., Coffee) now included in report
  - Organic Sales by Category breakdown table (category name, units sold, revenue)
  - All amounts now shown ex. VAT (invoices use `total_amount - vat_amount`, sales already net)
  - Summary stats expanded: Total Sales, Organic Sales cards added
  - Two submission-ready CSV exports replacing previous generic exports:
    - "Bought In Organic Products" — Supplier Name, Product Type, Certification Body, Total Amount (ex. VAT)
    - "Sales of Organic Products" — Category, Units Sold, Sales Revenue (ex. VAT)
  - **New Migration**: `2026_03_23_000000_add_organic_fields_to_accounting_suppliers_table` — adds `organic_product_type` and `organic_certification_body` columns
  - **Modified**: `OrganicTrustReportController.php`, `organic-trust-report.blade.php`, `AccountingSupplier.php`, `routes/web.php`

- **Destock Review & Audit System** (2026-03-22)
  - Audit logging for all destock/restock actions — records who, when, and from where (order review, product page, etc.)
  - New Destock Review page (`/destock-review`) with two tabs:
    - **Audit Log**: Filterable history of all destock/restock actions by date, action type, and user
    - **Restock Suggestions**: Identifies destocked products that still have sales activity, suggesting they may need restocking
  - Restock Suggestions features:
    - Configurable sales period (7 days to 1 year) and minimum units threshold
    - Supplier filter and supplier website links (Udea, Independent) for quick product lookup
    - Product images from supplier CDNs with hover preview
    - Exclude Fruit & Vegetables toggle (on by default, as F&V orders don't use stocking)
    - Sort by units sold, revenue, days with sales, or last sale date
    - Search by product name or barcode
    - Sales chart modal for viewing detailed sales history per product
    - One-click restock button to add products back to stock management
    - View product link for each suggestion
  - Sidebar link under STOCK section with warning triangle icon
  - **New**: `app/Models/DestockAudit.php`, `app/Http/Controllers/DestockReviewController.php`, `resources/views/destock-review/index.blade.php`, `resources/views/destock-review/suggestions.blade.php`
  - **Modified**: `app/Http/Controllers/ProductController.php`, `resources/views/orders/partials/review-table.blade.php`, `resources/views/orders/partials/review-table-christmas.blade.php`, `resources/views/products/show.blade.php`, `routes/web.php`, `resources/views/layouts/admin.blade.php`
  - **Migration**: `create_destock_audits_table`

- **Stock Check Review** (2026-03-21)
  - New page for reviewing physical stock checks by category, replacing old PHP `stock_by_category_REVIEW2.php`
  - Products grouped by status: items needing attention (unchecked with stock) shown first
  - Color-coded rows: red (unchecked with stock), yellow (negative stock), green (zero/safe), white (verified)
  - Summary dashboard with progress bar, checked count, at-risk value
  - Product images from database, Udea CDN, and Independent CDN with click-to-enlarge lightbox
  - Fullscreen scanner modal with barcode input, phone camera scanning (html5-qrcode), and optional stock update
  - "Set to Zero" with confirmation modal — bulk zeros unchecked products with full audit trail
  - Set-to-zero history modal combining old POS `catSetZero` records and new Laravel audit records
  - Sales history toggle (lazy-loaded, last 5 months per product)
  - Mobile-optimized: collapsible controls, card layout, bottom-sheet modals, touch-friendly buttons
  - **New**: `app/Models/StockLastChecked.php`, `app/Models/StockZeroAudit.php`, `app/Services/StockCheckReviewService.php`, `app/Http/Controllers/StockCheckReviewController.php`, `resources/views/stock-review/index.blade.php`, `resources/views/stock-review/audit-log.blade.php`
  - **Modified**: `app/Models/Product.php`, `app/Repositories/ProductRepository.php`, `routes/web.php`, `resources/views/layouts/admin.blade.php`
  - **Migration**: `create_stock_zero_audits_table`

### Fixed

- **Delivery "Invoiced" column showing ordered quantity instead of delivered quantity** (2026-03-19)
  - INVOICED column now displays `invoice_delivered_quantity` (what the supplier delivered) instead of `ordered_quantity` (what was ordered)
  - Shows "Ordered: X" sub-detail when ordered differs from delivered for at-a-glance discrepancy visibility
  - Added orange row highlighting and "Partial" count badge for partial deliveries (ordered > delivered > 0)
  - Fixed `Received` column diff to compare against invoice delivered quantity, not ordered
  - Fixed `DeliveryItem` model computed attributes (`quantity_difference`, `value_difference`, `completion_percentage`, `unit_tax_amount`) to use `invoice_delivered_quantity` instead of `ordered_quantity`
  - **Modified**: `resources/views/deliveries/show.blade.php`, `app/Models/DeliveryItem.php`

- **F&V manage page showing stale prices from price history instead of live POS prices** (2026-03-19)
  - Manage page now always displays the live POS price (source of truth) instead of preferring `veg_price_history`
  - Added amber warning banner showing count of products with price mismatches and link to price-sync page
  - Added inline mismatch indicator on each affected product showing the stale history price
  - Added quick "Sync" button per product and "Sync All to POS Price" bulk action to resolve mismatches without leaving the page
  - Editing a price inline automatically clears the mismatch flag
  - **Modified**: `app/Http/Controllers/FruitVegController.php`, `resources/views/fruit-veg/manage.blade.php`

- **Label preview not loading on production** (2026-03-18)
  - ZPL preview WASM renderer (9MB) failed to load within the 2-second timeout over VPN/slow connections
  - Increased `getZplRenderer()` polling timeout from 2s to 15s (150 × 100ms)
  - **Modified**: `resources/views/labels/translate.blade.php`

- **Ingredients/nutrition text overlap on translated labels** (2026-03-18)
  - Long ingredients text overflowed its `^FB` line allocation, rendering on top of the nutrition field below
  - Removed hardcoded line cap from `estimateLines()` for ingredients — now uses actual lines needed
  - Corrected character width ratio from 0.6 to 0.5 to match Zebra default font rendering
  - Auto-fit scaling still reduces font size if total content exceeds label height
  - **Modified**: `app/Services/ZplGeneratorService.php`

### Added

- **Phone camera barcode scanning on delivery show page** (2026-03-19)
  - New "Scan Barcode" button on delivery items that are new products without a barcode
  - Opens camera scanner modal (html5-qrcode) to scan and save barcode to the delivery item via AJAX
  - Barcode is saved without creating a product — user can complete "Add to POS" later on desktop
  - Manual barcode entry fallback in the modal
  - **Route**: `PATCH /deliveries/{delivery}/items/{item}/barcode`
  - **Modified**: `routes/web.php`, `app/Http/Controllers/DeliveryController.php`, `resources/views/deliveries/show.blade.php`

- **F&V manage page print queue visual indicators** (2026-03-18)
  - Products already on the label print list now show an amber row highlight and "On List" badge with checkmark icon
  - "Add to Labels" button shows a + icon; queued products show amber "On List" button with checkmark
  - Fixed `in_print_queue` not being set on initial page load (only worked after search/filter)
  - Both desktop table rows and mobile cards highlight when queued
  - **Modified**: `resources/views/fruit-veg/manage.blade.php` (row highlighting, button icons)
  - **Modified**: `app/Http/Controllers/FruitVegController.php` (`manage()` now sets `in_print_queue`)

- **Label size & text scale controls on print step** (2026-03-18)
  - When scanning an existing product and going directly to print, users can now adjust label size and font scale
  - Same controls as the review/edit step: label size buttons and A-/A+ text size slider
  - Changes regenerate the ZPL and update the preview before printing
  - **Modified**: `resources/views/labels/translate.blade.php`

- **ZPL Code viewer on label translate page** (2026-03-18)
  - Collapsible dropdown showing raw ZPL code on the review step of `/labels/translate?edit=`
  - Useful for debugging label layout issues
  - **Modified**: `resources/views/labels/translate.blade.php`

- **Photo upload failing for multiple images on mobile** (2026-03-11)
  - Phone photos (3-5MB) exceeded PHP's `upload_max_filesize` (2M), causing silent upload failures
  - Added client-side image resize in browser before upload (max 1600px, JPEG 85% quality)
  - Photos now compress to ~200-500KB, well within PHP limits and faster to upload on mobile
  - Lowered server-side validation from 10MB to 5MB per image (resized images are under 1MB)
  - **Modified**: `resources/views/labels/translate.blade.php` (canvas resize in `addFileToPhotos()`)
  - **Modified**: `app/Http/Controllers/LabelTranslationController.php` (validation limit)

### Added

- **Image Upload on Product Create Page** (2026-03-16)
  - File picker with client-side preview on the create product form
  - Uses same Intervention Image resize (128x128) as the edit page
  - Optional — image failure does not block product creation
  - **Modified**: `resources/views/products/create.blade.php` (multipart form, image card, preview JS)
  - **Modified**: `app/Http/Controllers/ProductController.php` (`store()` image handling)
  - **Modified**: `app/Http/Requests/StoreProductRequest.php` (image validation rule)

- **Organic Trust Supplier Report** (2026-03-14)
  - New report at `/suppliers/organic-trust-report` for Organic Trust annual return (Field 13: Bought In Organic Ingredients/Products)
  - Date range selection shows all product suppliers with invoice spend totals (incl. VAT)
  - Toggle switches to mark/unmark suppliers as organic (persists across reports via `is_organic` flag)
  - Summary stats: total suppliers, total spend, organic supplier count, organic spend
  - Dual CSV export: "Export All" for review, "Export Organic Only" for the return form
  - Navigation button added to suppliers index page
  - **New**: `app/Http/Controllers/OrganicTrustReportController.php`, `resources/views/suppliers/organic-trust-report.blade.php`
  - **Modified**: `app/Models/AccountingSupplier.php` (added `is_organic`), `routes/web.php`, `resources/views/suppliers/index.blade.php`
  - **Migration**: `add_is_organic_to_accounting_suppliers_table`

- **Standalone zebra labels visible on main labels page** (2026-03-14)
  - Labels without a barcode/product link now appear in a "Standalone Labels" section on `/labels/zebra`
  - Previously these were only visible on the manage page (`/labels/zebra/manage`)
  - Users can print standalone labels directly from the main page like any other label
  - **Modified**: `app/Http/Controllers/LabelAreaController.php`, `resources/views/labels/zebra.blade.php`

- **Zebra Label Storage & Printing** (2026-03-12)
  - Upload ZebraDesigner .prn/.zpl exports and store in database for direct printing
  - Auto-extract barcode and product link from ZPL content
  - Editable label fields — modify product name, price, weight, origin, class, etc. before printing
  - ZPL hex decode/encode for display (e.g., `\15` → `€` for Euro symbol)
  - Print quantity parsed from ZPL `^PQ` command, modifiable per-print
  - Live ZPL preview via zpl-renderer-js (note: `~DG` embedded graphics may not render perfectly in preview)
  - Reuses existing CUPS printer infrastructure (Zebra GX430t via `lp` command)
  - **New Files**: `app/Models/ZebraLabel.php`, `app/Http/Controllers/ZebraLabelController.php`, `resources/views/zebra-labels/` (index, create, show), `database/migrations/2026_03_12_000000_create_zebra_labels_table.php`
  - **Modified**: `routes/web.php`

- **Test Pages Hub & Documentation** (2026-03-12)
  - Central test page index at `/tests/hub` linking to all dev/test pages
  - Test registry documentation at `docs/test.md` listing all test routes with cleanup instructions
  - **New Files**: `resources/views/tests/hub.blade.php`, `docs/test.md`
  - **Modified**: `routes/web.php`

- **Natural Medicine Company delivery parser** (2026-03-10)
  - New Python PDF parser for The Natural Medicine Company invoices
  - Extracts all product line items: stock code, description, RRP, quantity, trade price, discount, total, VAT rate
  - Handles multi-line product descriptions that wrap across PDF lines
  - Handles multi-page invoices with repeated headers
  - Validates line totals (qty × trade price) and cross-checks against invoice SUB-TOTAL
  - Supports all Irish VAT rates (0%, 13.5%, 23%)
  - Auto-detected from PDF text ("The Natural Medicine Company" / "naturalmedicine.ie")
  - **New File**: `scripts/invoice-parser/parsers/delivery_natural_medicine.py`
  - **Modified**: `scripts/invoice-parser/delivery_parser_laravel.py` (supplier detection + dispatch)

- **Barcode scanner test page with live camera scanning** (2026-03-10)
  - Live camera barcode scanner at `/labels/barcode-scan-test` using `html5-qrcode` library
  - Supports EAN-13, EAN-8, UPC-A, UPC-E, Code-128 barcode formats
  - Real-time camera feed scans barcodes continuously (requires HTTPS)
  - Auto-lookup against POS product database showing name, code, and price
  - Manual barcode input fallback for typed/pasted codes
  - Scan history list tracks all lookups in current session
  - Audio beep feedback on successful barcode detection
  - **New Package**: `html5-qrcode` for browser-based barcode scanning
  - **New Files**: `resources/js/barcode-scanner.js`, `resources/views/labels/barcode-scan-test.blade.php`
  - **HTTPS Setup**: Self-signed certificate configuration for Apache to enable camera API on mobile

- **AI-powered label translation system** (2026-03-09)
  - Snap photos of foreign-language product labels using phone camera (Chrome on Android)
  - Google Gemini 2.5 Flash translates label text to English and generates ZPL II printer code
  - 14 EU allergens highlighted in CAPS for HSE compliance
  - Direct printing to networked Zebra GX430t thermal printer via CUPS/IPP
  - Label size: 50mm x 76mm at 300dpi with product name, ingredients, nutrition, and storage info
  - Uploaded images auto-resized to 1200px before API call to reduce latency
  - Gallery of previously uploaded label images on capture page
  - Printer debug panel with connectivity and configuration tests
  - **New Package**: `google-gemini-php/laravel` for Gemini API integration
  - **Files Modified**: `LabelAreaController.php`, `routes/web.php`, `config/services.php`, `config/gemini.php`
  - **New Views**: `labels/camera-test.blade.php`, `labels/review.blade.php`

- **Category filter for delivery legacy match page** (2026-03-07)
  - "Categories" checkbox next to "Show Details" displays product category names under each item
  - When enabled, a dropdown appears with all unique categories from the delivery
  - Multi-select checkboxes to filter items by category with "All" / "None" quick buttons
  - Filtering applies across all sections (critical, warnings, verified, OOS, pending, extra items)
  - Items with no category are always shown
  - **Files Modified**: `app/Http/Controllers/DeliveryLegacyController.php`, `resources/views/delivery-legacy/match.blade.php`

- **Client-side image compression for invoice bulk upload** (2026-03-06)
  - Images (JPG, PNG) are now automatically compressed in the browser before upload using Canvas API
  - Scales images down to max 2000px on longest side, JPEG quality 0.7
  - Solves issue where large phone photos (3MB+) exceeded PHP's 2MB `upload_max_filesize` limit
  - Shows compression progress ("Compressing...") and result ("3.3MB → 250KB") in the file list
  - Upload button disabled while compression is in progress
  - Non-image files (PDF, DOC, XLS, etc.) pass through unchanged
  - No new dependencies — uses native browser Canvas API
  - **File Modified**: `resources/views/invoices/bulk-upload.blade.php`

### Fixed

- **Ardú Bakery invoice parser date extraction** (2026-03-06)
  - Updated date regex to handle `20 Feb 2026` text-month format (was only matching `dd/mm/yy`)
  - Old `dd/mm/yy` format kept as fallback for backwards compatibility
  - **File Modified**: `scripts/invoice-parser/parsers/ardu.py`

- **Bulk upload preview: invoice date display and edit form pre-population** (2026-03-06)
  - Added parsed invoice date to the summary line alongside total and supplier
  - Fixed date input in edit form: Carbon date now formatted as `Y-m-d` for HTML date picker (was outputting datetime string the browser ignored)
  - Fixed supplier dropdown pre-selection: added JS initializer to ensure dropdown reflects parsed supplier reliably
  - **File Modified**: `resources/views/invoices/bulk-upload-preview.blade.php`

### Added

- **Manual Resolution of Unparsed Delivery Lines** (2026-02-28)
  - When PDF parser can't parse a line, it's now persisted to the delivery (survives page refresh)
  - Interactive inline form on delivery show page to manually create delivery items from unparsed lines
  - Auto-lookup by supplier code: pre-fills description, unit cost, VAT rate, units/case, and barcode from product database
  - Fallback lookup chain: exact supplier match → any supplier → past delivery items
  - Barcode-based product matching prevents false "New Product" flags
  - Manually added items tracked in parsing discrepancy section with updated totals and remaining difference
  - "Dismiss" option to remove unparsed lines without creating items
  - **Files Created**: `add_unparsed_lines_to_deliveries_table` migration, `add_manually_added_total_to_deliveries_table` migration
  - **Files Modified**: `DeliveryController.php`, `DeliveryService.php`, `Delivery.php`, `deliveries/show.blade.php`, `web.php`, `api.php`

### Fixed

- **"Undefined array key filename" on single-file delivery upload** (2026-02-28)
  - Single-file PDF uploads now include `filename` key in unmatched lines session data, matching multi-file behaviour
  - **File Modified**: `DeliveryController.php`

- **Wages Management & P&L Integration** (2026-02-28)
  - New `/management/wages` page to import payroll "Gross to Net Total By Week Number" XLS/XLSX exports
  - Auto-detects column layout across different file format years (2025 vs 2026 column shifts)
  - Upserts weekly entries (year + week number) so re-importing updates existing data
  - Year filter tabs, totals row, individual and bulk year delete
  - P&L page automatically includes wages (Gross Pay + Employer PRSI) in cost breakdown and profit calculation
  - Previous period comparison now includes wages in cost totals
  - **Files Created**: `WageController.php`, `WageImportService.php`, `WageEntry.php`, `wages/index.blade.php`, `create_wage_entries_table` migration
  - **Files Modified**: `ProfitLossController.php`, `profit-loss/index.blade.php`, `web.php`, `admin.blade.php`

- **Delivery Legacy: Merge Sessions & Change Supplier** (2026-02-24)
  - Merge two pending scan sessions: overlapping barcodes have quantities summed, unique items are moved
  - Source session is deleted after merge; target session's supplier is preserved
  - Change supplier on any pending session via inline edit icon with dropdown
  - Checkboxes on pending session rows with "Merge Selected Sessions" button (appears when exactly 2 selected)
  - Modal dialog to choose which session to keep, with different-supplier warning
  - Completed sessions cannot be merged or have supplier changed
  - **Files Modified**: `DeliveryLegacyController.php`, `delivery-legacy/index.blade.php`, `web.php`

- **RTD: Unfreeze Mode for correcting frozen invoices** (2026-02-23)
  - New "Unfreeze Mode" toggle in the RTD settings cog panel
  - Per-row "Unfreeze" button on frozen invoices resets them to "needs_computation" for recomputation
  - "Unfreeze All Visible" bulk button unfreezes all frozen invoices on the current page
  - Safety: blocks unfreezing invoices in submitted RTD submissions
  - **Files Modified**: `RtdController.php`, `rtd/index.blade.php`, `web.php`

- **VAT on Purchases report page** (2026-02-23)
  - New report under Revenue sidebar showing purchase invoice VAT broken down by rate (0%, 9%, 13.5%, 23%)
  - Splits invoices into Retail (T1), Non-Retail (T2), and Unclassified based on supplier RTD classification
  - Summary cards, VAT rate breakdown table, and collapsible invoice detail list
  - Date range selector defaulting to current month
  - **Files Created**: `VatPurchasesController.php`, `management/vat-purchases/index.blade.php`

- **Suppliers: Inline RTD Classification dropdown on index page** (2026-02-22)
  - New color-coded dropdown in the suppliers table for quickly assigning RTD classification (Simple/Parser/Service/N/A)
  - AJAX-powered inline editing matching the existing VAT Treatment pattern
  - **Files Modified**: `index.blade.php`, `AccountingSuppliersController.php`, `web.php`

### Changed

- **Suppliers: Removed redundant "Default Purchase Use" field** (2026-02-22)
  - The `default_purchase_use` field (resale/overhead/mixed) was never used in any business logic — RTD Classification fully supersedes it
  - Removed from supplier create, edit, and index pages, controller validation, and model
  - Dropped the column and its index via migration
  - **Files Modified**: `AccountingSupplier.php`, `AccountingSuppliersController.php`, `edit.blade.php`, `create.blade.php`, `index.blade.php`

### Fixed

- **Delivery Parser: "Nett" skip term matching "Nettle" product names** (2026-02-19)
  - IIH parser skipped all products containing "Nettle" (e.g., Heath and Heather Nettle, Urtekram Nettle Shampoo) because the skip term `"Nett"` matched as a substring of `"Nettle"`
  - Caused €23.91 discrepancy on Invoice(54).pdf (2 items × €18.05 + €5.86 silently dropped)
  - Fix: replaced plain substring match with regex word-boundary `\bNett\b` so footer "Nett" lines are still skipped but "Nettle" product names are not
  - Added post-parse cross-validation: compares item codes found in raw PDF text against parsed output, warns on any missing codes
  - **File Modified**: `scripts/invoice-parser/parsers/delivery_independent.py`

- **Delivery Discrepancy: Per-document feedback for multi-PDF deliveries** (2026-02-19)
  - When multiple PDFs are uploaded, the discrepancy warning now shows which specific document has the mismatch
  - Stores per-file parsing metadata (stated vs calculated totals, match status) in `DeliveryDocument.parsing_metadata`
  - Single-document deliveries show the document filename; multi-document deliveries show a per-document breakdown with checkmark/X indicators
  - **Files Modified**: `app/Http/Controllers/DeliveryController.php`, `resources/views/deliveries/show.blade.php`

- **VAT Return: Paperin adjustment not applied to 0% rate row in Sales VAT Breakdown** (2026-02-19)
  - The 0.0% row showed unadjusted net/gross figures (e.g., €138,399.44 instead of €136,322.34) while the TOTAL row was correct
  - Root cause: SQLite returns `decimal(8,4)` values as strings like `"0.0000"`, but `keyBy` checked for key `"0"` — the key mismatch silently skipped the paperin deduction for the 0% row
  - Fix: normalize `keyBy` with `(string) (float)` cast to ensure consistent keys; also broadened `show()` recalculation to detect and fix already-stored returns with stale by_rate data
  - **File Modified**: `app/Http/Controllers/Management/VatReturnController.php`

- **RTD Submission: Paperin deduction missing from Tier 1 sales figures** (2026-02-17)
  - `VatReturn.sales_vat_data['by_rate']` stores 0% net **before** paperin deduction — the `paperin_adjustment` key records the amount but `by_rate` is never adjusted
  - Tier 1 (persisted VAT data) was trusting `by_rate` figures blindly, inflating 0% by the paperin gross (e.g., €2,077.10)
  - Now deducts paperin from 0% for all Tier 1 returns: uses stored `paperin_adjustment` value when present, queries `sales_accounting_daily` for older returns without it
  - Debug page also updated to show paperin source (stored vs queried) for each Tier 1 VAT return
  - **Files Modified**: `app/Models/RtdSubmission.php`, `app/Http/Controllers/RtdSubmissionController.php`

- **RTD Submission: Paperin adjustment missing from Tier 2/3 sales figures** (2026-02-16)
  - Paperin (gift voucher redemption) gross amount was not being deducted from 0% sales in Tier 2 (sales_accounting_daily fallback) and Tier 3 (no VAT returns) code paths
  - Caused D1 (0% Home) to be overstated by the paperin gross total (e.g., €921.61)
  - Now correctly deducts paperin gross from 0% net in all code paths, matching `VatReturnController::getSalesVatData()` logic
  - **File Modified**: `app/Models/RtdSubmission.php`

### Added

- **RTD Section 1 (Sales) Populated from VAT3 Returns** (2026-02-13)
  - Section 1 "Goods and/or Services" now shows actual net sales by VAT rate, aggregated from VAT3 returns for the submission period
  - Maps VAT rates to ROS boxes: D1 (0% Home), BC5 (9%), AC5 (13.5%), P1 (Std Rate 23%), Z1 (Total)
  - Primary source: `VatReturn.sales_vat_data` snapshots; fallback to `sales_accounting_daily` for historical returns
  - Sales data persisted in `totals_snapshot` for audit consistency
  - CSV export includes real sales figures
  - **Files Modified**: `RtdSubmission` model, `RtdSubmissionController`, `report.blade.php`

### Changed

- **RTD Submission Report: Aligned to ROS Layout** (2026-02-13)
  - Restructured report to mirror Revenue Online Service (ROS) RTD form exactly — same section order, headings, box codes, and VAT rate sequence
  - 4 ROS sections: Section 1 (Sales), Section 2 (Acquisitions from EU/Non-EU), Section 3 (Goods for Resale), Section 4 (Other Deductible)
  - Added placeholder rows for untracked VAT rates (Exempt, 4.8%, FlatFarm) shown as 0.00
  - Combined EU and Non-EU acquisitions into single Section 2 with correct ROS box codes (E4, D2, C6, BC6, AC6, B6, P2, Z2)
  - Fixed T2 total box code from Z4 to Z5 per ROS specification
  - Added Postponed Accounting rows (PA2 in Section 2, PA4 in Section 4)
  - CSV export updated to match new structure
  - **Files Modified**: `report.blade.php`, `RtdSubmissionController`

### Added

- **RTD Submission: Import Newly Frozen Invoices** (2026-02-13)
  - Draft submissions can now import invoices frozen after the submission was created
  - Collapsible panel on submission detail page shows count of available frozen invoices
  - Table with checkboxes, Select All, and "Add Selected" button for batch import
  - AJAX-powered import with automatic totals recalculation
  - **Files Modified**: `RtdSubmissionController` (new `addInvoices()` method, updated `show()`), `routes/web.php`, `submissions/show.blade.php`

- **RTD Submission Tracking** (2026-02-10)
  - New submission management page to track which invoices were filed with Revenue as part of RTD submissions
  - Create submissions with flexible date ranges, selecting from frozen invoices not yet in any submission
  - VAT breakdown snapshot (T1 goods by rate, T2 service by rate, excluded totals) saved at submission time
  - Mark submissions as filed with Revenue date and reference number
  - Remove invoices from draft submissions; next submission automatically shows previously missed invoices
  - **Files Created**: `RtdSubmission` model, `RtdSubmissionController`, 3 Blade views, 2 migrations

### Fixed

- **Sales Accounting Import - POS Query Performance** (2026-02-16)
  - **Bug**: Importing accounting data took ~36 seconds per day (~72s for 2 days) due to full table scans
  - **Root Cause**: `DATE_FORMAT(DATENEW, '%Y %m %d') = ?` wraps the indexed column in a function, preventing MySQL from using the index on `DATENEW`
  - **Fix**: Replaced with range comparisons `DATENEW >= ? AND DATENEW < ?` using day start/end timestamps in both `importMainSalesData()` and `importStockTransferData()`
  - **Expected Result**: Import time drops from ~36s to <2s per day
  - **File Modified**: `app/Services/SalesAccountingImportService.php`

- **Udea Parser - Credit Note / Negative Total Support** (2026-02-12)
  - **Bug**: Udea credit notes (e.g., returned crates) have negative totals like `Total including vat EUR -3873,20`, but the parser regex `[\d.,]+` didn't match the negative sign, causing total to be `None` and all amounts to be 0
  - **Fix**: Changed total regex to `-?[\d.,]+` to allow negative amounts; detect credit notes from negative total and set `is_credit_note: true`
  - **File Modified**: `scripts/invoice-parser/parsers/udea.py`

- **Invoice Deletion - VAT Return Protection** (2026-02-12)
  - **Bug**: Deleting an invoice assigned to a finalized/submitted VAT return silently succeeded, leaving VAT return totals stale and incorrect
  - **Fix**: Added three-tier protection:
    - **Block**: Invoices with non-zero VAT on finalized/submitted/paid returns cannot be deleted (button disabled with explanation)
    - **Warn**: Invoices with zero VAT on finalized returns show confirmation warning, user can override
    - **Draft**: Invoices on draft returns are unlinked and totals recalculated automatically
  - **Files Modified**: `app/Http/Controllers/InvoiceController.php` (`destroy()` method), `resources/views/invoices/show.blade.php` (delete button)

- **IIH Parser - Non-Standard VAT Rate Handling** (2026-02-10)
  - **Bug**: IIH invoice #9588 showed 164.74 reconciliation difference — 164.75 in taxable goods missing from RTD
  - **Root Cause**: IIH VAT summary regex only matched exact rates (`0.00|9.00|13.50|23.00`); invoice used rate `22.50` which was silently skipped
  - **Fix**: Changed regex to accept any numeric rate with 2 decimal places, anchored to line start; bucket mapping uses wider tolerance (rates >=20% → 23% bucket)
  - **File Modified**: `scripts/invoice-parser/parsers/invoice_iih_rtd.py` (`_extract_vat_summary()` method)

- **VAT Returns - Sales VAT Breakdown Missing Rates** (2026-02-10)
  - **Bug**: Sales VAT Breakdown on VAT return create page only showed one rate (0.0%) instead of all 4 rates (0%, 9%, 13.5%, 23%)
  - **Root Cause**: PHP truncates float array keys to integers — `keyBy('vat_rate')` caused rates 0, 0.09, 0.135, 0.23 to all collapse to integer key `0`, with each overwriting the last
  - **Impact**: Only the 23% rate data survived but displayed as "0.0%"; totals were correct but per-rate breakdown was wrong
  - **Fix**: Cast vat_rate to string before using as key: `keyBy(fn ($item) => (string) $item->vat_rate)`
  - **File Modified**: `app/Http/Controllers/Management/VatReturnController.php` (`getSalesVatData()` method, both optimized and real-time paths)

- **Invoice Total Parsing - Number Format Detection** (2026-02-05)
  - **Bug**: Invoice stated total was parsing `1,978.38` as `1.98` instead of `1978.38`
  - **Root Cause**: `_clean_number_string()` in Independent parser assumed European format when both comma and period present
  - **Fix**: Now detects format by checking which separator comes last (period last = UK/US, comma last = European)
  - **File Modified**: `scripts/invoice-parser/parsers/delivery_independent.py` (lines 158-184)

### Added

- **✅ RTD Non-Retail Classification for Fallback Entries** (2026-02-10)
  - **Non-Retail Flag**: New `is_non_retail` boolean on RTD VAT fallback entries (default: false)
  - **Correct VAT Routing**: Non-retail items (cleaning supplies, office equipment) go to `excluded.service_overhead` instead of `goods_for_resale`, preventing T1 inflation
  - **Minimal UX Impact**: Checkbox in bulk assign bar (unchecked by default — zero extra clicks for the common case)
  - **Fallback Index**: Non-retail entries show yellow "Non-retail" badge; edit modal includes checkbox
  - **RTD Display**: Excluded section shows "Non-retail" line (yellow) for parser-based suppliers, "Service/Overhead" for service suppliers
  - **JS Detail Row**: Recompute AJAX response now correctly includes `service_overhead` in excluded total and renders Non-retail line
  - **Files Created**:
    - `database/migrations/2026_02_10_120000_add_is_non_retail_to_rtd_vat_fallbacks_table.php`
  - **Files Modified**:
    - `app/Models/RtdVatFallback.php` - Added `findFallback()` returning `[vat_rate, is_non_retail]`, `findVatRate()` kept as wrapper
    - `app/Services/RtdResolutionService.php` - Non-retail routing to `excluded.service_overhead`, integrity check includes non-retail total
    - `app/Http/Controllers/RtdFallbackController.php` - `is_non_retail` validation in `bulkAssign()` and `update()`
    - `resources/views/rtd-fallbacks/unresolved.blade.php` - Non-retail checkbox, yellow badge for non-retail assigned items
    - `resources/views/rtd-fallbacks/index.blade.php` - Non-retail badge, edit modal checkbox
    - `resources/views/rtd/index.blade.php` - JS `excludedTotal` includes `service_overhead`, Non-retail line in Excluded section

- **✅ RTD Force Reparse Mode** (2026-02-04)
  - **Force Reparse Toggle**: Settings dropdown with toggle to enable force reparse mode
  - **Reparse Any Invoice**: When enabled, shows "Reparse" button on all invoices with PDFs
  - **Bypass Checks**: Force reparse bypasses `canReparseForRtd()` validation
  - **Fresh Parse**: Deletes existing parsed data and re-parses with latest RTD parser
  - **Visual Indicator**: Orange banner shows when force reparse mode is active
  - **State Persistence**: Mode setting saved to localStorage across sessions
  - **Files Modified**:
    - `app/Http/Controllers/RtdController.php` - Added `forceParse()` method
    - `routes/web.php` - Added force-parse route
    - `resources/views/rtd/index.blade.php` - Added settings dropdown, toggle, reparse buttons

- **✅ Independent Irish Health Foods (IIH) RTD Support** (2026-02-04)
  - **New IIH Parser**: `invoice_iih_rtd.py` extracts VAT summary and DRS totals from IIH invoices
  - **DRS Exclusion**: Deposit Return Scheme amounts automatically excluded from 0% goods for resale
  - **VAT Summary Approach**: Uses invoice's built-in VAT categorization (0%, 13.5%, 23%) instead of article code resolution
  - **Three-Supplier RTD**: Dashboard now supports Udea, Dynamis, and Independent suppliers
  - **DRS in Excluded Section**: DRS amounts shown alongside Freight and Deposits in reconciliation
  - **Files Created**:
    - `scripts/invoice-parser/parsers/invoice_iih_rtd.py` - IIH RTD parser with VAT summary extraction
  - **Files Modified**:
    - `app/Models/Invoice.php` - Added `isIndependentSupplier()`, updated `hasRtdParser()`, `canReparseForRtd()`
    - `app/Services/RtdResolutionService.php` - Added `computeRtdFromVatSummary()` for IIH, DRS support
    - `app/Http/Controllers/RtdController.php` - Added Independent supplier detection in all queries
    - `resources/views/rtd/index.blade.php` - Added DRS row in excluded section
    - `resources/views/rtd/year-report.blade.php` - Added DRS to excluded totals display

- **✅ RTD Detail Row Reconciliation Layout** (2026-02-04)
  - **4-Column Layout**: Reorganized expandable detail row into Goods for Resale, Excluded, Unresolved, and Reconciliation columns
  - **Reconciliation Summary**: New column showing how totals add up to match invoice total
    - Shows `+ Goods`, `+ Excluded`, `+ Unresolved` = `Calculated Total`
    - Compares against Invoice Total with balance indicator
  - **Visual Balance Indicator**: Green border when balanced (difference < €0.50), yellow border when discrepancy exists
  - **Improved Styling**: Each column in a card with colored headers and icons
  - **Files Modified**:
    - `app/Http/Controllers/RtdController.php` - Added `invoice_total` to JSON response
    - `resources/views/rtd/index.blade.php` - New 4-column layout with reconciliation, updated JS

- **✅ RTD Year Report Color Improvements** (2026-02-04)
  - **Improved Readability**: "Not Frozen" warning section now uses orange theme with better contrast
  - **Better Text Colors**: Changed from hard-to-read yellow-200 to gray-200/gray-300
  - **Status Badges**: Proper pill styling with background colors for Computed/Pending status
  - **Table Styling**: Added row separators and darker background for table area
  - **Files Modified**:
    - `resources/views/rtd/year-report.blade.php` - Updated color scheme for warning section

- **✅ RTD Page UX Improvements** (2026-02-04)
  - **AJAX Actions**: Parse, Compute, Freeze actions now use AJAX instead of page redirects
  - **Scroll Preservation**: Page scroll position maintained during all RTD operations
  - **Loading Indicators**: Per-row loading spinners show processing status with action text
  - **In-Place Updates**: Row status, issues count, and action buttons update without page reload
  - **Detail Row Updates**: Expandable detail section updates automatically after actions
  - **PDF View Button**: New "PDF" button opens invoice attachment in popup viewer
  - **Flash Messages**: Success/error messages appear inline and auto-dismiss after 5 seconds
  - **Files Modified**:
    - `app/Http/Controllers/RtdController.php` - Added JSON responses via `rtdResponse()` helper
    - `resources/views/rtd/index.blade.php` - AJAX buttons, loading states, JS update functions

- **✅ Invoice Missing File Detection & Upload** (2026-02-04)
  - **Missing File Indicator**: Red badge on `/invoices` list showing count of missing attachments
  - **Missing Files Modal**: Popup showing missing filenames with upload inputs per file
  - **Copy Filename Button**: Quick copy-to-clipboard with visual feedback (checkmark confirmation)
  - **Clipboard Fallback**: Works on non-HTTPS environments using execCommand fallback
  - **File Upload Validation**: Validates replacement files against invoice data using parser
    - Compares total_amount, invoice_number, supplier_name, invoice_date
    - Shows validation mismatches with option to confirm or cancel
  - **AJAX Upload**: Files upload without page reload, modal updates on success
  - **Files Modified**:
    - `app/Models/Invoice.php` - Added `hasMissingAttachments()`, `getMissingAttachmentCountAttribute()`
    - `app/Http/Controllers/InvoiceController.php` - Eager load attachments
    - `app/Http/Controllers/InvoiceAttachmentController.php` - Added `getMissing()`, `replace()` methods
    - `resources/views/invoices/index.blade.php` - Missing badge, modal, JS functions
    - `routes/web.php` - Added `missing` and `replace` routes

- **✅ RTD Missing PDF Detection** (2026-02-04)
  - **New Status**: Added `pdf_missing` status for invoices with orphaned attachment records
  - **Visual Indicator**: Gray "PDF Missing" badge on RTD dashboard for affected invoices
  - **Smart Detection**: System now checks actual file existence on disk, not just database records
  - **Stat Card**: Conditional stat card appears when missing PDFs are detected
  - **Files Modified**:
    - `app/Models/Invoice.php` - Added `hasPdfOnDisk()` method, updated `canReparseForRtd()`
    - `app/Http/Controllers/RtdController.php` - Added `pdf_missing` status detection
    - `resources/views/rtd/index.blade.php` - Added badge, action text, and stat card

- **✅ Dynamis RTD Invoice Parser** (2026-02-04)
  - **New Parser**: `invoice_dynamis_rtd.py` for line-item extraction from Dynamis invoices
  - **Two Invoice Types**: Automatically detects F&V (RUNGIS) vs Grocery (MAG) from `Ent:` field
  - **EAN Barcode Resolution**: Grocery invoices use 13-digit EAN codes for direct product lookup
  - **Generated Article Codes**: F&V invoices generate `DYN-PRODUCT-COUNTRY` codes for fallback resolution
  - **Multi-Supplier RTD**: Dashboard now supports both Udea and Dynamis suppliers
  - **Files Created**:
    - `scripts/invoice-parser/parsers/invoice_dynamis_rtd.py` - Dynamis RTD parser
  - **Files Modified**:
    - `app/Http/Controllers/RtdController.php` - Added Dynamis supplier detection and routing
    - `app/Services/RtdResolutionService.php` - Added EAN barcode lookup, Dynamis file support
    - `app/Models/Invoice.php` - Added `isDynamisSupplier()`, `hasRtdParser()` methods

- **✅ Delivery Parsing Totals Verification** (2026-01-29)
  - **Invoice Total Extraction**: Python parsers now extract stated totals from PDF footers
    - UDEA: Extracts "Total to deliver", "Total barrels delivered", "Total including/excluding vat"
    - Independent: Extracts "Gross Total", "Subtotal", "Nett" totals
  - **Totals Comparison**: Compares calculated sum of parsed items against PDF-stated totals
    - €0.50 tolerance for rounding differences
    - Flags mismatches with detailed warnings
  - **Preview UI Enhancement**: New "Totals Verification" section in delivery upload preview
    - Shows PDF-stated vs parsed values in comparison table
    - Green checkmarks for matches, red X for mismatches
    - Warning message highlighting potential missing items
  - **Persistent Discrepancy Tracking**: Database storage for audit trail
    - New fields: `invoice_stated_total`, `calculated_total`, `total_discrepancy`, `has_discrepancy`
    - `parsing_metadata` JSON field on `delivery_documents` table
  - **Show Page Warning Banner**: Red warning banner displays on delivery detail page when discrepancy detected
  - **Files Modified**:
    - `scripts/invoice-parser/parsers/delivery_udea.py` - Added `_extract_invoice_totals()` method
    - `scripts/invoice-parser/parsers/delivery_independent.py` - Added `_extract_invoice_totals()` method
    - `app/Services/DeliveryParsingService.php` - Passes through totals verification data
    - `app/Services/DeliveryService.php` - Stores discrepancy data in database
    - `app/Http/Controllers/DeliveryController.php` - Passes totals to service
    - `app/Models/Delivery.php` - Added discrepancy fields to fillable/casts
    - `app/Models/DeliveryDocument.php` - Added `parsing_metadata` field
    - `resources/views/deliveries/create.blade.php` - Totals verification UI section
    - `resources/views/deliveries/show.blade.php` - Discrepancy warning banner
  - **Files Created**:
    - `database/migrations/2026_01_29_160146_add_totals_verification_to_deliveries_table.php`

- **📊 Udea Invoice Parser** (2026-01-28)
  - **Invoice Header Extraction**: Parses invoice number, date, total excl VAT, VAT amount, zero-VAT confirmation
  - **Product Line Parsing**: Extracts article codes, descriptions, quantities, unit prices, and line totals
  - **Line Classification**: Automatic categorization by Gb.rek account code:
    - 30302 = AGF (Fruit & Vegetables)
    - 30322 = DKW (Dry goods)
    - 30342 = Drogmetica
    - 30362 = Non-food
    - 30862 = Transport/Freight
    - 34120 = Barrels/Deposits
  - **Barrel/Deposit Extraction**: Detects "Barrels delivered" section with codes, quantities, values
  - **Freight/Costs Detection**: Extracts transport charges from "Costs" section
  - **Validation**: Reconciles sum of line totals against invoice total (€0.50 tolerance)
  - **Problem Line Reporting**: Reports unparseable lines with reasons (no Gb.rek, truncated, etc.)
  - **PDF Corruption Handling**: Fixes common text extraction issues:
    - `Bio-Dynamis3c0h302` → `Bio-Dynamisch 30302`
    - `1kilogramOnions` → `1kilogram Onions`
    - Scrambled Gb.rek codes recovered from corrupted text
  - **~96% Accuracy**: Captures 248 of ~263 product lines on sample invoices
  - **Web Interface**: "Parse Udea" button on `/invoices/bulk-upload/preview` page
  - **Debug Modal**: Shows header, validation, lines, barrels, costs, warnings, and problem lines
  - **Files Created**:
    - `scripts/invoice-parser/parsers/invoice_udea.py` - Python parser with pdfplumber
    - `docs/features/udea-invoice-parser.md` - Feature documentation
  - **Files Modified**:
    - `app/Http/Controllers/InvoiceBulkUploadController.php` - Added `parseUdeaInvoice()` method
    - `routes/web.php` - Added `parse-udea` route
    - `resources/views/invoices/bulk-upload-preview.blade.php` - Added Parse Udea button and results modal

- **🏷️ Supplier VAT Classification Fields** (2026-01-28)
  - **New Fields**: `vat_treatment` and `default_purchase_use` enums on AccountingSupplier model
  - **VAT Treatment Options**: irish_vat, eu_goods_zero_rated, eu_reverse_charge_services, postponed_import, outside_scope_or_exempt
  - **Purchase Use Options**: resale, overhead, mixed
  - **Auto-Default Logic**: VAT treatment auto-set based on country_code (IE→irish_vat, EU→eu_goods_zero_rated, etc.)
  - **Inline Editing**: Update VAT fields directly on `/suppliers` index page via AJAX
  - **VAT Filter**: Filter suppliers by VAT treatment on index page
  - **Statistics Display**: Shows counts of suppliers by VAT classification
  - **Files Created**:
    - `database/migrations/2026_01_28_135442_add_vat_classification_to_accounting_suppliers_table.php`
  - **Files Modified**:
    - `app/Models/AccountingSupplier.php` - Added fields, constants, helper methods, boot() auto-default
    - `app/Http/Controllers/AccountingSuppliersController.php` - Added updateVatClassification(), validation
    - `routes/web.php` - Added `suppliers.update-vat-classification` route
    - `resources/views/suppliers/index.blade.php` - VAT column, inline dropdowns, AJAX, filter, stats
    - `resources/views/suppliers/edit.blade.php` - VAT Classification section
    - `resources/views/suppliers/create.blade.php` - VAT Classification section

- **📄 Delivery Document Storage & Viewing** (2026-01-27)
  - **Permanent Document Storage**: PDF and CSV files uploaded during delivery creation are now stored permanently
  - **Document Viewer**: View delivery documents at `/delivery-documents/{id}/viewer` with clean minimal interface
  - **Popup Window**: Documents open in separate popup window (900x700) without navigation elements
  - **Collapsible Section**: Documents section on delivery detail page is collapsible (collapsed by default)
  - **Legacy Integration**: Document links synced to legacy delivery pages via cache mechanism
  - **Multiple Documents**: Support for multiple documents per delivery (PDF invoices, CSV imports)
  - **File Management**: Automatic cleanup when delivery/document is deleted
  - **Files Created**:
    - `database/migrations/2026_01_27_143710_create_delivery_documents_table.php`
    - `app/Models/DeliveryDocument.php`
    - `app/Http/Controllers/DeliveryDocumentController.php`
    - `resources/views/deliveries/document-viewer.blade.php`
    - `resources/views/deliveries/document-viewer-minimal.blade.php`
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryController.php` - Document saving in store/storePdf
    - `app/Models/Delivery.php` - Added documents() relationship
    - `resources/views/deliveries/show.blade.php` - Collapsible documents section
    - `resources/views/delivery-legacy/index.blade.php` - Synced documents display
    - `resources/views/delivery-legacy/match.blade.php` - Invoice documents section

- **🔄 Create Legacy Scan Session** (2026-01-27)
  - **New Session Creation**: Create new delivery scan sessions directly from `/delivery-legacy` page
  - **Supplier Selection**: Select supplier when creating new session
  - **UUID-Based IDs**: Sessions created with UUID identifiers
  - **Direct Redirect**: After creation, redirects to match page for the new session
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryLegacyController.php` - Added createSession() method
    - `routes/web.php` - Added `delivery-legacy.create-session` route
    - `resources/views/delivery-legacy/index.blade.php` - Added create session form

### Changed

- **📊 Stock Input Precision Enhancement** (2026-01-27)
  - **2 Decimal Places**: Stock input fields now accept 2 decimal places (was 1)
  - **Arrow Key Behavior**: Up/down arrow keys increment by 1 (not 0.01) for quick adjustments
  - **Display Update**: Stock values display with 2 decimal places for consistency
  - **Pages Updated**: Products index (`/products`) and product detail (`/products/{uuid}`)
  - **Use Case**: Allows precise stock entries like 12.75 units for weighted/measured items
  - **Files Modified**:
    - `resources/views/products/index.blade.php` - Inline stock editing
    - `resources/views/products/show.blade.php` - Stock edit form

### Added

- **⚖️ Weight-Based Product Support for Udea Deliveries** (2026-01-27)
  - **Automatic Detection**: Parser detects weight-based products (e.g., meat sold by kg) from PDF invoices
  - **Weight Fields**: New database fields `is_weight_based`, `weight_per_unit`, `weight_unit`, `total_weight`
  - **Correct Price Validation**: Weight-based products validate as `total_weight × price` (not qty × price)
  - **INVOICED Column**: Shows total weight (e.g., 0.921 kg) instead of quantity for weight-based items
  - **Delivery Legacy Sync**: Syncs `total_weight` to legacy `myOrder` field for weight-based products
  - **Visual Indicators**: Purple badges show weight breakdown (e.g., "0.307 kg × 3 = 0.921 kg")
  - **Decimal Input**: All scanned quantity fields accept decimal values (step="0.001")
  - **Files Created**:
    - `database/migrations/2026_01_27_101241_add_weight_fields_to_delivery_items_table.php`
  - **Files Modified**:
    - `app/Models/DeliveryItem.php` - Added weight fields to fillable and casts
    - `scripts/invoice-parser/parsers/delivery_udea.py` - Weight detection and extraction
    - `app/Services/DeliveryParsingService.php` - Pass weight data through conversion
    - `app/Services/DeliveryService.php` - Store weight fields on import
    - `app/Http/Controllers/DeliveryController.php` - Sync weight to legacy
    - `resources/views/deliveries/show.blade.php` - Display weight badges
    - `resources/views/delivery-legacy/match.blade.php` - Weight display and decimal inputs

- **🖼️ Product Images in Delivery Legacy Pages** (2026-01-26)
  - **Image Thumbnails**: Product images now display in the left column of all tables on `/delivery-legacy/match`
  - **Hover Preview**: Large image preview on hover using fixed positioning (displays over table headers/footers)
  - **Smart Positioning**: Preview automatically appears below thumbnail, or above if near viewport bottom
  - **Alpine.js Teleport**: Uses `x-teleport="body"` to render preview outside overflow containers
  - **Barcode Column**: Added always-visible barcode column to "Pending - Not Yet Scanned" section
  - **Supplier Integration**: Uses existing `SupplierService` for external image URLs (UDEA CDN)
  - **Tables Updated**: Critical Issues, Warnings, Verified, OOS, Pending, Extra Items, Missing Items
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryLegacyController.php` - Injected SupplierService
    - `resources/views/delivery-legacy/match.blade.php` - Added image columns to all tables
    - `resources/views/components/product-image.blade.php` - Enhanced hover with fixed positioning

- **✅ Barcode Exists Highlighting in Deliveries** (2026-01-26)
  - **Auto-Detection**: When refreshing a barcode from supplier website, system checks if barcode already exists in POS products
  - **Green Highlighting**: Existing barcodes display with green background, checkmark icon in separate circle
  - **Product Link**: Clickable link opens existing product page in new tab for verification
  - **Persistent Display**: Highlighting persists through auto-refresh polling (every 10 seconds)
  - **Tooltip**: Hover shows product name for quick identification
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryController.php` - Added Product lookup in `refreshBarcode()` and AJAX response
    - `resources/views/deliveries/show.blade.php` - Updated `refreshBarcode()` and `updateBarcodeCell()` JS functions

- **📦 Barrel Deposit Tracking System** (2026-01-26)
  - **Automatic Extraction**: Barrel deposits (crates, bottles, pallets) parsed from Udea delivery PDFs
  - **Reference Database**: `barrel_codes` table auto-populated from imports with supplier linkage
  - **Per-Delivery Tracking**: `delivery_barrels` table records each barrel item per delivery
  - **Custom Naming**: Add your own names to barrel codes for easier identification
  - **Image Support**: Upload photos (100x100 resized) for visual identification
  - **Collapsible Display**: Barrel section on delivery show page collapsed by default with Show/Hide toggle
  - **Management Page**: `/barrel-codes` - browse, filter by supplier/status, search, and edit barrel codes
  - **Parser Boundary Fix**: Barrels section correctly stops at "Costs" to exclude freight charges
  - **Files Created**:
    - `app/Models/BarrelCode.php` - Barrel code reference model
    - `app/Models/DeliveryBarrel.php` - Delivery barrel line item model
    - `app/Http/Controllers/BarrelCodeController.php` - CRUD with image handling
    - `resources/views/barrel-codes/index.blade.php` - List page with filters
    - `resources/views/barrel-codes/edit.blade.php` - Edit form with image upload
    - `docs/features/barrel-deposit-tracking.md` - Feature documentation
  - **Files Modified**:
    - `scripts/invoice-parser/parsers/delivery_udea.py` - Barrel section extraction
    - `scripts/invoice-parser/delivery_parser_laravel.py` - Include barrels in response
    - `app/Services/DeliveryService.php` - storeBarrelItems() method
    - `app/Http/Controllers/DeliveryController.php` - storePdf() integration
    - `resources/views/deliveries/show.blade.php` - Collapsible barrel display
    - `resources/views/deliveries/index.blade.php` - Barrel Codes button

- **📦 Delivery Legacy - Out of Stock (OOS) Handling** (2026-01-24)
  - **OOS Items at Bottom**: OOS items (ordered but not delivered) now sync to legacy at the bottom of the list
  - **Expected: 0 for OOS**: OOS items display "Expected: 0" instead of their ordered quantity for clearer verification
  - **Correct Case Units**: OOS items retain correct invoice case units (not hardcoded to 1)
  - **Separate OOS Section**: New "Out of Stock" section on delivery-legacy match page with orange styling
  - **OOS Excluded from Missing**: OOS items no longer appear in "Missing Items" section (HAVING clause filter)
  - **OOS Excluded from Verified**: OOS items no longer incorrectly appear in "Verified Items"
  - **Visual Progress**: OOS count displayed in progress bar area
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryController.php` - Two-pass sync with OOS detection
    - `app/Http/Controllers/DeliveryLegacyController.php` - HAVING clause to exclude OOS from Missing Items
    - `resources/views/delivery-legacy/match.blade.php` - OOS section and filter updates

- **🔍 Auto Supplier Detection on PDF Upload** (2026-01-24)
  - **Automatic Detection**: System identifies supplier from PDF content when uploading deliveries
  - **Supported Suppliers**: Independent Irish Health Foods, UDEA, Mossfield
  - **Visual Feedback**: Shows "Detecting supplier..." status while parsing
  - **Smart Mapping**: Converts detected supplier name to correct supplier ID
  - **Graceful Fallback**: If supplier cannot be detected, user can select manually
  - **Route**: `POST /deliveries/detect-supplier`
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryController.php` - Added `detectSupplier()` and `mapSupplierNameToId()`
    - `resources/views/deliveries/create.blade.php` - Added auto-detection UI and JavaScript
    - `routes/web.php` - Added `deliveries.detect-supplier` route

- **🔘 Clickable Case Unit Mismatch Badges** (2026-01-24)
  - **Quick Update**: Case mismatch badges (e.g., "Case: 1 → 12") are now clickable buttons
  - **One-Click Fix**: Clicking updates DB case units to match invoice case units
  - **Auto Reload**: Page reloads after update to reflect corrected expected quantities
  - **Hover Feedback**: Title tooltip shows what value will be applied
  - **Available In**: Critical Issues and Warnings sections
  - **Files Modified**:
    - `resources/views/delivery-legacy/match.blade.php` - Converted case badges to clickable buttons

### Fixed

- **🧾 Tax Rate Calculation for PDF Imports** (2026-01-24)
  - **Root Cause**: `importFromPdfData()` saved tax_amount but didn't calculate tax_rate or normalized_tax_rate
  - **Fix**: Added tax rate calculation: `(tax / lineTotal) * 100` with Irish VAT normalization
  - **Impact**: Delivery items now have correct tax_rate and normalized_tax_rate values
  - **Files Modified**:
    - `app/Services/DeliveryService.php` - Added tax rate calculation in `importFromPdfData()`

- **⌨️ Label Scanner Keyboard Toggle** (2026-01-24)
  - **Keyboard Toggle Button**: Added toggle button beside barcode input in scan-to-label modal
  - **Mobile Keyboard Control**: Click to show/hide virtual keyboard for manual barcode entry
  - **Visual State Feedback**: Blue styling when enabled, gray when disabled (matches stocking page pattern)
  - **Dark Mode Support**: Full dark mode styling for the toggle button
  - **Focus Preservation**: Automatically refocuses input after toggling keyboard state
  - **Files Modified**:
    - `resources/views/labels/index.blade.php` - Added keyboard toggle button and `keyboardEnabled` Alpine state

- **📄 UDEA PDF Delivery Parsing** (2026-01-23)
  - **Automatic Supplier Detection**: Parses "UDEA B.V." or "WWW.UDEA.NL" from PDF text
  - **European Number Formatting**: Converts 1.234,56 → 1234.56 automatically
  - **Three-Tier Regex Matching**: NORMAL → QUANTITY_SKU → FALLBACK patterns for robust parsing
  - **Weight-Based Products**: Handles kilogram, gram, and SKU-based quantities
  - **Price Validation**: Qty × Price × SKU verification with configurable tolerance
  - **High Confidence**: Achieves 99-100% confidence on standard UDEA invoices
  - **Files Created**:
    - `scripts/invoice-parser/parsers/delivery_udea.py` - UDEA-specific parser
  - **Files Modified**:
    - `scripts/invoice-parser/delivery_parser_laravel.py` - Added UDEA detection and import
    - `resources/views/deliveries/create.blade.php` - Added UDEA to supported suppliers list
  - **Test Results**: 3 UDEA PDFs with 257 combined items, €3,938.47 total

- **📦 Multi-PDF Delivery Upload** (2026-01-23)
  - **Multiple File Selection**: Upload multiple PDFs at once to create single delivery
  - **Per-File Status**: Preview shows success/failure and item count for each file
  - **Item Merging**: All items from all PDFs combined into single delivery
  - **Total Aggregation**: Values summed across all files with combined statistics
  - **Confidence Scoring**: Weighted average confidence across parsed files
  - **Files Processed Summary**: Visual breakdown of each file's contribution
  - **Backward Compatible**: Single file uploads continue to work as before
  - **Files Modified**:
    - `app/Services/DeliveryParsingService.php` - Added `parseMultipleDeliveryPdfs()` method
    - `app/Http/Controllers/DeliveryController.php` - Updated `parsePdf()` and `storePdf()` for multi-file
    - `resources/views/deliveries/create.blade.php` - Multi-file UI with `multiple` attribute
  - **Documentation**: See [Delivery System Documentation](./docs/features/delivery-system.md#multi-pdf-upload-support)

- **⚡ Product Detail Page Performance Optimization** (2026-01-22)
  - **Lazy-Loaded Sales Data**: Sales history section now loads via AJAX after page render
  - **Optimized Database Queries**: Combined 4 separate queries into 1 using SQL CASE statements
    - Before: 1 EXISTS check + 3 SUM queries = 4 database round trips
    - After: Single query with CASE statements = 1 database round trip (75% reduction)
  - **Detailed Sales History Modal**: Added "Detailed Sales History" button that opens the full interactive sales chart modal (same as products listing)
    - Weekly view with expand/contract date range
    - Click on week to drill down to daily view
    - Click on day to see individual transactions
  - **Instant Page Load**: Product detail pages now render immediately without waiting for sales data
  - **Files Modified**:
    - `app/Repositories/SalesRepository.php` - Optimized `getProductSalesStatistics()` method
    - `app/Http/Controllers/ProductController.php` - Removed synchronous sales loading from `show()`
    - `resources/views/products/show.blade.php` - Added lazy loading and sales chart modal
  - **Performance Pattern**: Follows the proven optimization pattern from [Sales Data Import Plan](./docs/features/sales-data-import-plan.md)

- **📦 Stocking Scanner** (2026-01-22)
  - **Mobile-First Store Room Scanner**: Dedicated page at `/stocking` for checking stock levels in the store room
  - **Stock Level Display**: Scan product barcode to see current stock count prominently displayed
  - **Stock Adjustment**: Adjust stock levels directly from the scanner with +/- buttons
  - **Add to Label Queue**: Quick button to add scanned products to the label print queue
  - **Audit Trail**: All stock adjustments logged with user, timestamp, old/new values
  - **Keyboard Toggle**: Button to show/hide mobile keyboard (scanner mode vs manual entry)
  - **Scan History**: Recent scans stored in localStorage for quick reference
  - **Admin Stock Logs Page**: View all stock adjustments at `/stocking/logs` (admin only)
    - Filter by barcode, user, and date range
    - Color-coded changes (green for increases, red for decreases)
    - Links to product detail pages
  - **Sidebar Navigation**: Links under "Stock" section (Stocking, Labels & Printing)
  - **Database Schema**: New `stock_adjustments` table for audit trail
  - **Files Created**:
    - `app/Http/Controllers/StockingController.php`
    - `app/Models/StockAdjustment.php`
    - `database/migrations/2026_01_22_105722_create_stock_adjustments_table.php`
    - `resources/views/stocking/index.blade.php`
    - `resources/views/stocking/logs.blade.php`
  - **Files Modified**:
    - `routes/web.php` - Added stocking routes
    - `resources/views/layouts/admin.blade.php` - Added Stock section and Stock Logs link
  - **Documentation**: See [Stocking Documentation](./docs/features/stocking.md)

- **🍳 Kitchen Products Management System** (2026-01-21)
  - **Kitchen Products List**: Dedicated page at `/kitchen/products` for managing products that regularly go to the kitchen
  - **Quick Flag from Orders**: "Kitchen" toggle button on Orders review page (`/orders/`) to quickly add/remove products from kitchen list
  - **Product Search & Add**: Search and add products directly from kitchen products page
    - Search by product name, barcode (CODE), or supplier code
    - Results show product name, barcode, supplier code, and supplier name
    - One-click add with success feedback and page reload
  - **Supplier Code Quick Copy**: Click supplier code to copy to clipboard with visual feedback
    - Fallback method for non-HTTPS environments using execCommand
    - "Copied!" confirmation with checkmark icon
  - **Shop Stock Display**: Real-time stock levels from POS `STOCKCURRENT` table
    - Color-coded: green for in-stock, red for negative stock
  - **Kitchen Stock Placeholder**: Column ready for future kitchen inventory tracking
  - **Ingredient Profile Integration**: Direct links to create or edit ingredient profiles for costing
  - **Filtering Options**:
    - Supplier dropdown filter
    - Group by category toggle with collapsible category sections
    - Text search for product name/code
  - **Statistics Dashboard**: Cards showing total kitchen products and profile coverage
  - **Sidebar Navigation**: Quick access link in admin sidebar
  - **Database Schema**: New `kitchen_products` table with `product_id` (UUID) and notes field
  - **Files Created**:
    - `database/migrations/2026_01_20_114028_create_kitchen_products_table.php`
    - `app/Models/KitchenProduct.php`
    - `app/Http/Controllers/KitchenProductController.php`
    - `resources/views/kitchen/products/index.blade.php`
    - `resources/views/kitchen/products/partials/product-row.blade.php`
  - **Files Modified**:
    - `routes/web.php` - Added kitchen products routes
    - `resources/views/orders/partials/review-table.blade.php` - Added Kitchen toggle button
    - `resources/views/layouts/admin.blade.php` - Added sidebar link
  - **Documentation**: See [Kitchen Products Documentation](./docs/features/kitchen-products.md)

- **📊 Delivery Legacy - Stock Update Verification System** (2026-01-21)
  - **Stock Update Preview**: Blue card shows what will happen before clicking complete:
    - Products to update count
    - Total units to add
    - Current stock total (for affected products only)
    - Expected stock after update
  - **Update Results Banner**: Enhanced completion banner shows actual results:
    - Products actually updated
    - Units actually added
    - Products skipped (no STOCKCURRENT record)
  - **Extra Items Stock Column**: Added Stock column to Extra Items section showing current stock
  - **Extra Items Processing**: Fixed bug where Extra section items weren't included in stock updates
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryLegacyController.php` - Added `calculateStockPreview()`, result tracking, STOCKCURRENT join for Extra Items
    - `resources/views/delivery-legacy/match.blade.php` - Added preview card, enhanced completion banner, Stock column in Extra Items

- **✅ Delivery Legacy - Update Stock & Completion** (2026-01-20)
  - **Update Stock Button**: "Update Stock & Complete" button in header to finalize delivery verification
  - **Stock Updates**: Increments `STOCKCURRENT.UNITS` for all products with scanned quantities
  - **Completion Status**: Sets `deliveriesScan.status` to 1 (integer) to mark delivery as finalized
  - **Read-Only Mode**: After completion, all edit functionality is disabled to preserve record
  - **Completion Banner**: Green banner shows "Delivery Complete - Stock has been updated"
  - **Visual Indicators**: Pencil icons and arrow buttons hidden when completed
  - **Transaction Safety**: Stock updates and status change wrapped in database transaction
  - **Confirmation Dialog**: Warns user before completing (action cannot be undone)
  - **Files Modified**:
    - `routes/web.php` - Added `delivery-legacy.complete` route
    - `app/Http/Controllers/DeliveryLegacyController.php` - Added `completeDelivery()`, `isCompleted` check
    - `resources/views/delivery-legacy/match.blade.php` - Added button, banner, `canEdit` pattern for read-only

- **📝 Delivery Legacy - Delivered Column for Pending Items** (2026-01-20)
  - **Delivered Column**: New column in "Pending - Not Yet Scanned" section for entering quantities
  - **Arrow Auto-Fill**: Click → button to copy expected quantity to delivered field instantly
  - **Manual Entry**: Click delivered field directly for custom quantity entry
  - **Decimal Support**: Accepts up to 3 decimal places for weight-based items (step="0.001")
  - **Workflow Integration**: Saved items move to Verified or Critical sections automatically
  - **Use Case**: Allows quantity entry for non-scannable items (no barcode, bulk items, etc.)
  - **Files Modified**:
    - `resources/views/delivery-legacy/match.blade.php` - Added Delivered column with arrow button and editable input

- **🔧 Delivery Legacy - Case Quantity Mismatch Improvements** (2026-01-20)
  - **Issue Column in Critical Issues**: Now shows "Case: X → Y" badge when case units mismatch alongside quantity issues
  - **Inline-Editable DB Case**: Click DB Case column to edit `supplier_link.CaseUnits` directly
  - **Consistent Editing Pattern**: Same UX as scanned quantity editing (click, edit, save/cancel)
  - **Auto Recalculation**: Page reloads after case update to reflect new expected quantities
  - **Available Everywhere**: Case editing works in Critical Issues, Warnings, and Verified sections
  - **Files Created/Modified**:
    - `routes/web.php` - Added `delivery-legacy.update-case-units` route
    - `app/Http/Controllers/DeliveryLegacyController.php` - Added `updateCaseUnits()` method
    - `resources/views/delivery-legacy/match.blade.php` - Added Issue column, editable DB Case, JS handler

- **📦 Category Products Stock Display & Editing** (2026-01-20)
  - **Show Stock Toggle**: New checkbox in filters to display/hide stock column
  - **Editable Stock Values**: Click-to-edit inline stock editing on category products page
  - **Adaptive Decimal Display**: Smart formatting shows decimals for liquid products, whole numbers for regular items
  - **Real-time Updates**: Uses existing `/products/{id}/update-stock` endpoint for instant saves
  - **Color-Coded Display**: Green for in-stock (>0), gray for out-of-stock (0)
  - **Global Component Reference**: Follows delivery-legacy pattern for reliable nested component access
  - **Files Modified**:
    - `app/Http/Controllers/CategoriesController.php` - Added `stockCurrent` eager loading and `current_stock` to responses
    - `resources/views/categories/products.blade.php` - Added stock toggle, editable column, and `updateStock()` method

### Fixed

- **🔗 Delivery Legacy Match Page - Product Link Fixes** (2026-01-19)
  - **Correct Product URLs**: Product links now use UUID (`productID`) instead of barcode, fixing broken links
  - **Edit Page Navigation**: Links now go to `/products/{uuid}/edit` instead of show page for direct editing
  - **New Tab Opening**: All product links open in new tabs (`target="_blank"`) so users can easily return to delivery
  - **Case Unit Values Display**: "Case units changed" badge now shows actual values (e.g., "Case: 6 → 5")
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryLegacyController.php` - Added `PRODUCTS.ID as productID` to SQL queries
    - `resources/views/delivery-legacy/match.blade.php` - Updated links to use productID with target="_blank"

### Added

- **🔄 Delivery Sync to Legacy Feature** (2026-01-19)
  - **Sync Button**: New "Sync to Legacy" button on delivery detail pages
  - **One-Click Sync**: Copies delivery items from Laravel to POS `delivery` table for legacy comparison
  - **Invoice Matching**: Enables comparison with scanned items via `/delivery-legacy/match`
  - **Confirmation Dialog**: Warns user that existing legacy data will be replaced
  - **Transaction Safety**: Uses database transaction for safe data transfer
  - **Files Created**:
    - `app/Models/LegacyDelivery.php` - Eloquent model for POS delivery table
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryController.php` - Added `syncToLegacy()` method
    - `routes/web.php` - Added `deliveries.sync-legacy` route
    - `resources/views/deliveries/show.blade.php` - Added sync button

- **☕ Internal Customer Sales Tracking on Order Charts** (2026-01-19)
  - **Coffee & Kitchen Tracking**: Order review charts now display internal department transfers (Coffee, Kitchen) alongside regular sales
  - **Visual Distinction**: Purple solid line for Coffee (☕), orange dashed line for Kitchen (🍳), blue for total sales
  - **Interactive Tooltips**: Hover shows contextual info with emoji indicators
  - **Optimized Performance**: Single database query fetches both Coffee and Kitchen data via `getBulkInternalCustomerWeeklySales()`
  - **Modal Support**: Expanded sales history modal also shows Coffee/Kitchen lines
  - **Universal**: Works for all suppliers (Udea, Independent, Mossfield, etc.)
  - **Files Modified**:
    - `app/Repositories/SalesRepository.php` - Added `getBulkInternalCustomerWeeklySales()` method
    - `app/Services/OrderService.php` - Pre-fetches coffee/kitchen data, adds to context_data
    - `app/Http/Controllers/ProductController.php` - Updated API to include coffee/kitchen
    - `resources/views/orders/partials/review-table.blade.php` - Added chart datasets and tooltips

- **📦 Delivery Legacy Page Redesign** (2026-01-18)
  - **Financial Dashboard**: 6-card overview showing Invoice Total, Scanned Total, Discrepancy, Missing Value, Extra Value, and Margin Alerts
  - **Progress Bar**: Visual verification progress with verified/total item counts
  - **Quick Filters**: Alpine.js-powered buttons for All Items, Problems Only, and Verified Only views
  - **Issues-First Layout**: Collapsible sections prioritized by severity (Critical, Warnings, Verified, Pending, Extra, Missing)
  - **Simplified Tables**: Default view shows Product, Expected, Scanned, Diff, Stock columns
  - **Detailed View Toggle**: "Show Details" checkbox reveals VAT, Barcode, Cost, Sell, Margin columns
  - **Stock Column Always Visible**: Moved from details toggle to always-on for easier verification
  - **Admin Layout Integration**: Added sidebar navigation matching rest of application
  - **Files Modified**:
    - `app/Http/Controllers/DeliveryLegacyController.php` - Added `calculateFinancials()` method
    - `resources/views/delivery-legacy/match.blade.php` - Complete redesign with Alpine.js interactivity
    - `resources/views/delivery-legacy/index.blade.php` - Changed to admin layout

- **👁️ Category Visibility Management** (2026-01-14)
  - **Products Page Toggle**: New "Show hidden categories" checkbox to include all categories in the dropdown filter
  - **Hidden Category Indicator**: Categories marked as hidden show "(hidden)" suffix in the dropdown
  - **Categories Page Visibility Stats**: Header now displays visible/hidden category counts
  - **Visual Visibility Toggle**: Eye icon button on each category card to toggle visibility
  - **Instant AJAX Updates**: Toggle visibility without page reload using Alpine.js
  - **POS Integration**: Changes `CATSHOWNAME` field in POS database to control dropdown visibility
  - **Files Modified**:
    - `app/Repositories/ProductRepository.php` - Added `showHidden` parameter to `getAllCategoriesWithProducts()`
    - `app/Http/Controllers/ProductController.php` - Added `showHiddenCategories` handling
    - `app/Http/Controllers/CategoriesController.php` - Added `toggleCategoryVisibility()` method
    - `resources/views/products/index.blade.php` - Added checkbox filter and hidden indicator
    - `resources/views/categories/index.blade.php` - Added visibility stats and toggle buttons
    - `routes/web.php` - Added `categories.category-visibility.toggle` route

### Fixed

- **🖼️ Product Image Quality Improvement** (2026-01-13)
  - **Increased Resolution**: Product image uploads now resize to 128x128 pixels (was 64x64)
  - **Sharper Display**: Images now match the UI display size exactly, eliminating blurriness
  - **Files Modified**:
    - `app/Http/Controllers/ProductController.php` - Updated resize dimensions
    - `tests/Feature/FruitVegProductImageTest.php` - Updated test assertions

### Added

- **💰 Supplier Payments Report** (2026-01-12)
  - **Payments Listing**: View all supplier invoice payments within a date range
  - **Sorting Options**: Sort by date (newest/oldest) or supplier name (A-Z/Z-A)
  - **Group by Supplier**: Collapsible sections with Expand All/Collapse All toggle
  - **Summary Statistics**: Total payments, count, and breakdown by payment method
  - **Date Format**: UK format (dd/mm/yyyy) for payment and invoice dates
  - **Invoice Date Column**: Shows invoice date alongside payment date for reference
  - **CSV Export**: Download filtered payments with current sort order
  - **Navigation**: Green "Payments" button added to suppliers index
  - **Routes**: `/suppliers/payments` and `/suppliers/payments/export`
  - **Files Created/Modified**:
    - `app/Http/Controllers/SupplierPaymentsController.php` - New controller
    - `resources/views/suppliers/payments.blade.php` - New view with grouped/flat modes
    - `resources/views/suppliers/index.blade.php` - Added navigation link

- **🥬 F&V Order Generation System** (2026-01-09)
  - **Supplier-Agnostic Ordering**: Generate orders for all F&V products regardless of supplier
  - **Sales-Based Suggestions**: Order quantities calculated from historical sales data
  - **Category Groupings**: Products organized by Fruits (SUB1), Vegetables (SUB2), and Barcoded (SUB3)
  - **Configurable Parameters**:
    - Sales period selection (start/end date)
    - Coverage days (how many days the order should cover)
  - **Weekly Sales Analytics**:
    - Weekly average calculation
    - Peak weekly sales tracking
    - Mini line charts with average line indicator (dashed)
  - **Interactive Review Interface**:
    - Matches existing order system layout
    - Editable suggested quantities with +/- buttons
    - Client-side sorting by sales or name
    - Product images and origin country display
  - **Routes**: `/fruit-veg/orders` (form) and POST for results
  - **Quick Access**: "Generate Order" button added to F&V dashboard
  - **Files Created**:
    - `resources/views/fruit-veg/orders.blade.php` - Order generation form
    - `resources/views/fruit-veg/orders-review.blade.php` - Results display
    - `resources/views/fruit-veg/partials/order-table.blade.php` - Category table partial

- **💳 Card Transaction Reconciliation System** (2026-01-07)
  - **myPOS XLS Import**: Upload card transaction exports for reconciliation against POS records
  - **Intelligent Matching Algorithm**: Confidence-based matching using amount (0-50 pts), time (0-40 pts), and card type (0-10 pts)
  - **Discrepancy Detection**: Automatically identifies declined, mismatched, and orphan transactions
  - **Auto-Match Orphans**: Batch matching with configurable criteria and preview mode
    - Adjustable time window (15 min to 2 hours)
    - Minimum confidence threshold (70-90%)
    - Exact amount only option
    - Card/cash payment filtering
  - **Preview Before Matching**: Review all proposed matches with payment method details (Card/Cash) before confirming
  - **Manual Matching**: Find nearby POS payments for unmatched transactions
  - **Configurable Settings**: User-defined time windows and auto-match thresholds
  - **Batch Management**: Upload history, reprocess, delete, and export batches
  - **CSV Export**: Download reconciliation results with full transaction details
  - **Database Schema**: Two new tables (`card_transactions`, `card_reconciliation_settings`)
  - **Files Created**:
    - `app/Services/MyPosXlsParserService.php` - myPOS XLS parser
    - `app/Services/CardReconciliationService.php` - Matching logic
    - `app/Models/CardTransaction.php` - Card transaction model
    - `app/Models/CardReconciliationSetting.php` - User settings model
    - `app/Jobs/ProcessCardTransactions.php` - File processing job
    - `app/Http/Controllers/Financials/CardReconciliationController.php`
    - `resources/views/financials/card-reconciliation/` - Views
  - **Documentation**: See [Card Transaction Reconciliation Guide](./docs/features/card-reconciliation.md)

- **🍳 Kitchen Recipe Scaling & Packaging** (2025-12-10)
  - **Batch Scaling Calculator**: Analyze cost efficiencies when producing larger batches
    - Recipe multiplier (2x, 3x, 5x, 10x, or custom)
    - Independent labour factor (e.g., 2x batch might only need 1.5x labour)
    - Independent electricity factor (e.g., same oven time for larger batch)
    - Smart default factors based on batch size
    - Real-time comparison table showing original vs scaled costs
    - Per-portion savings percentage calculation
    - Save scaled version as new recipe with one click
  - **Packaging Cost Support**: Per-portion packaging costs for containers, lids, labels
    - New field in Rate Overrides section
    - Automatically included in total cost and cost-per-portion calculations
    - Packaging costs scale with portions in batch scaling calculator
    - Preserved when saving scaled recipes
  - **Database**: Added `packaging_cost_per_portion` column to `kitchen_recipes` table
  - **API**: New endpoint `POST /kitchen/{recipe}/scale` for saving scaled recipes

- **📦 Order Page Enhancements** (2025-12-09)
  - **Supplier Website Links**: Added "View →" links to Udea and Independent Health Foods product pages directly from order review
    - Links appear next to supplier code in product rows
    - Opens supplier website in new tab with product search
    - Works on both regular and Christmas review pages
  - **Destock/Restock Toggle**: Quick stock management control from order review pages
    - Red "Destock" button to remove products from stock management
    - Green "Restock" button to add products back
    - Confirmation dialog with clear messaging before action
    - Visual state toggle without page reload
    - Prevents products from appearing in future orders when destocked
  - **Sales Chart Modal for Christmas Review**: Extended sales history popup now available on Christmas review page
    - Click any chart to open expandable sales history modal
    - Navigate sales history with +/- 1 month and +/- 2 months controls
    - Statistics bar showing total sales, peak week, average, and active weeks
    - Consistent experience with regular order review page

- **🍳 Kitchen Recipe Costing System** (2025-12-08)
  - **Recipe Management**: Create, edit, and manage recipes with ingredients linked to POS products
  - **Ingredient Profiles**: Define ingredient costing with purchase units, recipe units, and density conversions
  - **Labour Cost Calculation**: Automatic labour cost from prep + cook time with configurable hourly rate
  - **Electricity Cost Calculation**: Automatic electricity cost from cook time with configurable kW and rate
  - **Per-Recipe Overrides**: Override global labour rate, electricity rate, and cooking power per recipe
  - **Cost Breakdown Display**: Detailed cost breakdown showing ingredients, labour, electricity, and total
  - **Margin Analysis**: Profit margin calculation with color-coded status (excellent/good/low/critical)
  - **Cost History Tracking**: Record cost snapshots over time for trend analysis
  - **Delivery Markup Support**: Apply delivery markup to imported products (Udea, Dynamis suppliers)
  - **Unit Conversions**: Smart weight↔volume conversions using density factors
  - **Global Config Defaults**: `config/kitchen.php` for system-wide rate defaults via environment variables
  - **Database Schema**:
    - `kitchen_recipes` table with override fields for rates
    - `kitchen_recipe_ingredients` table with unit conversions
    - `kitchen_ingredient_profiles` table for reusable ingredient costing
    - `kitchen_recipe_cost_history` table for cost tracking over time
  - **Files**:
    - `app/Models/KitchenRecipe.php` - Recipe model with rate helpers
    - `app/Services/KitchenCostingService.php` - Cost calculation service
    - `app/Http/Controllers/KitchenController.php` - Recipe management
    - `resources/views/kitchen/` - Recipe management views

- **🎄 Christmas Comparison Feature** (2025-12-01)
  - **Seasonal Order Planning**: Compare recent sales with historical Christmas period sales when generating orders
    - Flexible date range selection (e.g., Dec 10-26) with custom start/end dates
    - Multi-year comparison: Select 1-2 previous years (2024, 2023)
    - Max mode: Automatically uses higher of regular or Christmas-based suggestions
    - Opt-in design: Feature enabled via toggle, doesn't affect normal ordering
  - **Dual-Window Comparison Display**: Side-by-side stats showing recent vs Christmas data
    - Recent sales column: 8-week (or custom) average and suggested quantity
    - Christmas sales column: Historical Christmas average and suggested quantity
    - Green checkmark indicates which suggestion was selected (higher)
    - Delta indicator shows difference if >5 units
  - **Enhanced Visual Timeline Charts**: Extended Chart.js graphs with multiple datasets
    - Timeline: Recent weeks | Current/After stock | Christmas 2024 | Christmas 2023
    - Distinct colors: Blue (recent), Purple (2024), Pink (2023)
    - Interactive legend to toggle datasets
    - Hover tooltips display quantities for all data points
    - Larger graphs: 640x220px (2x previous size) for better visibility
  - **December Banner Prompt**: Auto-suggestion when creating December orders
    - Promotional banner with one-click enable
    - Dismissible without enabling feature
  - **Technical Implementation**:
    - New files: `show-christmas.blade.php`, `review-table-christmas.blade.php`
    - Enhanced: `OrderService`, `SalesRepository`, `OrderController`
    - Zero-risk deployment: Separate Christmas review files, original pages untouched
    - JSON storage: No new database tables, uses `christmas_window_config` JSON column
  - **Documentation**: See [Christmas Comparison Feature Guide](./docs/features/order-management/christmas-comparison.md)

- **📊 Graph Size Expansion** (2025-12-01)
  - **Larger Charts in Christmas Review**: Doubled graph dimensions for better visibility
    - Column width: 320px → 640px
    - Chart height: 110px → 220px
    - Row height: 180px → 360px
    - Better utilization of available white space
    - Desktop-optimized fixed dimensions
  - **Improved Data Visibility**: Easier to see patterns in extended timeline with Christmas data
    - Multiple datasets more clearly distinguishable
    - Legend and tooltip interactions more accessible
    - Better for analyzing seasonal trends

### Changed

- **📚 Documentation Refactoring** (2025-11-03)
  - **CLAUDE.md Cleanup**: Reduced from 646 lines to 229 lines (65% reduction)
    - Removed detailed feature descriptions (moved to Features Index)
    - Removed detailed known issues (moved to Known Issues document)
    - Removed detailed development commands (moved to Quick Start Guide)
    - Removed AI assistant guidelines (moved to AI Assistant Guide)
    - Now serves as concise entry point with links to detailed documentation
  - **New Documentation Files**:
    - `docs/FEATURES_INDEX.md` - Complete feature catalog organized by category
    - `docs/development/ai-assistant-guide.md` - Comprehensive guidelines for AI assistants
    - `docs/development/known-issues.md` - Detailed known issues and solutions
    - `docs/development/quick-start-guide.md` - Complete development setup and commands
  - **Improved Organization**: Better separation of concerns with focused, maintainable documents
  - **Enhanced Navigation**: Clear links between related documentation files
  - **Better Maintainability**: Easier to update specific sections without editing large files

### Added

- **📦 Minimum Stock Level Override System** (2025-11-03)
  - **User-Controlled Stock Levels**: Admin and Manager users can now set custom minimum stock levels for individual products
    - Override system uses absolute units (e.g., 50 units) for clear, direct control
    - Smart calculation: System uses whichever is higher - calculated minimum or user override
    - Preserves existing ordering intelligence while giving power users precise control
  - **Product Detail Page Integration**: Inline editing interface on product pages
    - Yellow badge displays current override value when set
    - Click-to-edit functionality with save/cancel/remove options
    - Real-time AJAX updates without page reload
    - Visual feedback during save operations
    - Only visible to Admin and Manager roles
  - **Order Calculation Integration**: Seamlessly integrated into order suggestion system
    - OrderService automatically applies override when calculating order quantities
    - Context data includes both calculated minimum and override value for transparency
    - Indicates when override is active in order context information
  - **Order Review Table Display**: Min stock override shown in stock levels section
    - Orange badge displays override value for products with custom minimums
    - Appears between current/after stock and coverage information
    - Visible across all order table sections (Cheese, Refrigerated, Case, Unit products)
  - **Order Review Table Editing**: Inline editing of min stock directly from orders page (Admin/Manager only)
    - Click pencil icon in orange badge to edit min stock override
    - Compact inline editor with number input, save, and cancel buttons
    - Enter to save, Escape to cancel editing
    - **Dynamic Real-time Updates** (2025-11-04): Changes apply instantly without page reload
      - Order quantity automatically recalculates based on new minimum stock level
      - "After Order" stock value updates immediately
      - Orange dotted line on chart moves to new minimum stock level
      - Green ring and "✓ Saved!" indicator provide immediate visual feedback
      - All updates happen seamlessly in <1 second
    - "Set Min Stock" button appears for products without override set
    - Non-admin users see display-only badge
  - **Sales Graph Visualization**: Orange dotted line shows minimum stock level
    - Horizontal line overlays monthly sales bars when override is set (product detail page)
    - Legend automatically displays when min stock override is active
    - Tooltip shows "Min Stock: X units" when hovering over line
    - Clear visual reference for stock planning and analysis
  - **Order Table Mini Charts**: Orange dotted line appears on weekly sales charts
    - Mini charts in order review table show min stock override as orange dotted line
    - Consistent visualization across product detail and order review pages
    - Tooltip displays "Min Stock Override · X units" when hovering
    - Automatically scales chart to include override level
  - **Database Schema**: New `min_stock_override` column in `product_order_settings` table
    - Nullable decimal field (10,2) for flexible precision
    - Automatically created/updated with product order settings
    - Persists across all order sessions
  - **Permission-Based Access**: Restricted to Admin and Manager roles only
    - Authorization checks in controller and view
    - Clear error messages for unauthorized access attempts
  - **API Support**: RESTful endpoint for updating min stock overrides
    - Route: `PATCH /products/{id}/min-stock-override`
    - Supports both setting and removing overrides
    - JSON responses for AJAX requests
    - Comprehensive validation (numeric, min: 0, max: 999,999.99)
    - **Enhanced Response** (2025-11-04): Returns recalculated order data for instant UI updates
      - Includes new suggested quantity after min stock change
      - Returns updated "after order" stock level
      - Provides complete context data for seamless dynamic updates

- **🔍 Real-time Product Duplicate Detection** (2025-11-01)
  - **Barcode Duplicate Detection**: Instant validation when creating products
    - Real-time AJAX validation with 500ms debounce for optimal performance
    - Warning appears before user fills out entire form, saving time
    - Shows conflicting product name, supplier, and direct link to edit existing product
    - "Edit Existing Product" button for quick navigation to conflicting product
    - "Use Different Barcode" button to clear field and try again
    - Full-width warning placement for maximum visibility
  - **Supplier Link Duplicate Detection with Override**: Smart duplicate handling for supplier codes
    - Real-time validation when entering supplier codes on create/edit forms
    - Warning modal shows conflicting product details before submission
    - User-controlled override with confirmation modal for intentional duplicates
    - Automatic conflict resolution: removes old link, assigns code to new product
    - Complete audit trail logging all override actions with metadata
    - Transaction-safe operations with rollback on failure
    - Visual feedback with yellow warning colors and clear conflict information
  - **Enhanced User Experience**: Comprehensive duplicate prevention system
    - Prevents accidental duplicate product creation
    - Allows intentional supplier code reassignment with proper warnings
    - Direct navigation to conflicting products for quick resolution
    - Session-based authentication for AJAX endpoints
    - CSRF protection on all validation requests

- **📄 DOC/XLS Invoice Attachment Viewing** (2025-09-03)
  - **Universal Document Viewing**: DOC, DOCX, XLS, XLSX files now viewable directly in browser
  - **On-Demand PDF Conversion**: LibreOffice headless conversion transforms documents to PDF for browser compatibility
  - **Seamless User Experience**: Click document icon to view any supported file type without download
  - **Intelligent Caching**: Converted PDFs cached for instant subsequent views (2-3 seconds first time, instant after)
  - **Permission-Safe Architecture**: Temporary directory strategy eliminates web server permission conflicts
  - **Visual File Type Indicators**: Enhanced icons show DOC (blue), XLS (green), PDF (red) with conversion status
  - **Robust Error Handling**: Graceful fallback to download if LibreOffice conversion fails
  - **Automatic Cleanup**: Converted files removed when original attachments deleted
  - **Database Schema**: Added `converted_pdf_path` and `converted_at` columns to track conversions
  - **Production Ready**: Full deployment support with proper environment variable management
  - **System Requirement**: LibreOffice must be installed (`sudo apt-get install libreoffice`)

- **🔧 F&V Image Upload Cache Fix** (2025-09-02)
  - **Root Cause Resolution**: Fixed issue where uploaded images appeared successful but didn't show updated images
  - **Cache-Busting Implementation**: Added server timestamp parameters to force browser cache refresh
  - **Dynamic Cache Control**: Images cached for 24 hours normally, 5 minutes when cache-busting parameter present
  - **Transaction Safety**: Added proper POS database transaction management matching price update patterns
  - **Content-Type Detection**: Automatic MIME type detection (PNG, JPEG, GIF, WebP) from binary image data
  - **Enhanced Error Handling**: Comprehensive logging and rollback on upload failures with debugging information
  - **Immediate Visibility**: Uploaded images now appear instantly without requiring browser refresh or cache clear
  - **Robust Architecture**: Uses explicit `DB::connection('pos')->beginTransaction()` for transaction integrity

- **📎 Clickable Invoice Attachment Icons** (2025-09-02)
  - **One-Click Viewing**: Click attachment icons in invoice table to instantly view documents
  - **New Window Display**: Opens attachments in dedicated window (1200x800) without navigation disruption
  - **Smart Selection**: Automatically prioritizes primary attachment, falls back to first available
  - **Visual Feedback**: Hover effects and tooltips indicate clickability and file count
  - **Error Handling**: Graceful handling of missing attachments with user-friendly messages
  - **Event Management**: Click handlers prevent interference with existing table row links
  - **Quick Access**: No need to navigate to invoice detail page to view attachments

- **📊 Outstanding Invoices Report System** (2025-09-02)
  - **Date-Based Reporting**: Select any date to see invoices outstanding at that time
  - **Supplier Grouping**: Automatic organization by supplier with individual tables
  - **Smart Outstanding Logic**: Uses `payment_status` field to accurately determine outstanding invoices
  - **Comprehensive Calculations**: Per-supplier totals and overall outstanding amounts
  - **Summary Statistics**: Cards showing supplier count, invoice count, total amounts, unpaid count
  - **CSV Export**: Download complete report with all supplier groupings and totals
  - **Year-End Reporting**: Perfect for management accounts and financial reporting at any date
  - **Access Points**: Available at `/suppliers/outstanding-report` or via button on suppliers page
  - **Payment Status Integration**: Properly excludes cancelled invoices and handles payment dates

- **📊 Invoice CSV Export System** (2025-09-02)
  - **Comprehensive Export**: Export button on invoices page with complete statistics and invoice data
  - **Filter Preservation**: CSV respects all active filters (supplier, status, dates, search terms)
  - **Statistics Cards Data**: Includes Total Unpaid, Overdue, This Month, Last Month summaries
  - **Filtered Results Summary**: Shows breakdown of filtered results when filters are applied
  - **Professional Format**: Structured CSV with header info, statistics sections, and detailed invoice table
  - **Smart Filename**: Auto-generated filename format `invoices_YYYY-MM-DD.csv`
  - **Complete Data Export**: All invoice fields including payment details, due dates, notes
  - **One-Click Export**: Green "Export CSV" button preserves current view state

- **🔄 Enhanced Invoice Payment Date Sorting** (2025-09-02)
  - **Smart Column Sorting**: "Status / Paid On" column now toggles between payment status and payment date sorting
  - **Payment Date Priority**: Click to sort by payment date (most recent payments first)
  - **NULL Value Handling**: Proper ordering with paid invoices first, unpaid invoices at end
  - **Direction Toggle**: Second click reverses payment date order (oldest to newest)
  - **Visual Feedback**: Arrow indicators show current sort field and direction
  - **Maintained Layout**: Single column design preserves compact table layout

- **🔧 F&V Price Sync Management System** (2025-08-28)
  - **Web-based Price Sync Tool**: New management interface at `/fruit-veg/price-sync`
  - **Cross-Database Discrepancy Detection**: Identifies products where POS and Laravel price history don't match
  - **Bidirectional Synchronization**: Choose sync direction (History→POS or POS→History)
  - **Statistics Dashboard**: Real-time overview of total F&V products, sync status, and discrepancy counts
  - **Individual & Bulk Operations**: Sync single products or multiple products simultaneously
  - **Professional Interface**: Sortable tables, loading indicators, success/error notifications
  - **Production Ready**: Eliminates need for terminal access to identify price issues
  - **Transaction Safety**: Proper cross-database transaction management with error handling
  - **Audit Trail Preservation**: Maintains complete price change history during sync operations

### Fixed

- **🔧 Product Duplicate Detection Showing "Unknown" Supplier** (2025-11-01)
  - **Root Cause**: Code accessing wrong supplier model field name (`NAME` instead of `Supplier`)
  - **Symptoms**: Real-time duplicate warnings displayed "Supplier: Unknown" or "Supplier: No supplier"
  - **Solution**: Fixed all 4 instances in ProductController (lines 912, 1126, 1301, 1357)
  - **Impact**: Duplicate detection now shows correct supplier names (e.g., "Infinity", "Natural Medicine")
  - **Discovery Method**: Used tinker to inspect Supplier model schema
  - **Testing**: Verified with both barcode and supplier link duplicate detection

- **🚨 F&V Price Updates Not Appearing on POS Till** (2025-08-28) - **CRITICAL BUG FIX**
  - **Cross-Database Transaction Issue**: Laravel `DB::transaction()` only applied to default connection
  - **Root Cause**: POS database updates were running but not committing properly due to transaction scope
  - **Solution**: Implemented separate transaction management for each database connection
  - **Database Connection Verification**: Added diagnostic tools to detect port mismatches (3306 vs 3307)
  - **Result**: Price changes now synchronize correctly between Laravel app and POS till system

- **📎 OSAccounts Attachment Import**: Major improvements to attachment import system (2025-08-12)
  - **Smart Path Resolution**: Handles various path formats from OSAccounts
    - Detects when InvoicePath already contains full filename
    - Decodes HTML entities (e.g., `&amp;` to `&`)
    - Multiple fallback strategies for finding files
    - Handles timestamp suffixes in paths
  - **Production-Ready Permissions**: Automatic permission management
    - Files created with `664` permissions (group-readable)
    - Automatic `www-data` group ownership
    - Directories use setgid bit for group inheritance
    - No sudo required in production
  - **Duplicate Prevention**: SHA-256 hash-based duplicate detection
    - Prevents re-import of identical files
    - New cleanup command `attachments:cleanup-duplicates`
    - Removed 42 duplicate attachments from initial imports
  - **Success Rate**: Improved from 23% to 98.4% (183 of 186 files)
  - **New Commands**:
    - `attachments:cleanup-duplicates` - Remove duplicates and fix permissions
    - `attachments:fix-permissions` - Fix file ownership and permissions
  - **Web Interface**: Fixed file access issues through proper group permissions

### Added

- **📊 VAT Dashboard System**: Comprehensive VAT return management dashboard (2025-08-12)
  - **Outstanding Periods Alert**: Automatic detection of overdue VAT periods
  - **Current Period Tracking**: Real-time display of current period status
  - **Next Deadline Tracker**: Visual countdown with urgency indicators
  - **Unsubmitted Invoices Summary**: Monthly breakdown of unassigned invoices
  - **Recent Submissions**: Quick view of last 6 VAT returns
  - **Yearly Statistics**: Side-by-side comparison of annual VAT metrics
  - **Complete History View**: Paginated archive with filtering by year and status
  - **Direct Links**: Quick access to create returns with pre-filled dates
  - **Role-based Access**: Protected for Admin and Manager roles only

### Changed

- **☕ KDS Clear All Orders Fix**: Improved reliability of clearing all orders (2025-08-11)
  - Changed from deleting orders to marking them as completed
  - Prevents orders from reappearing after clearing
  - Simplified implementation without complex tracking
  - Orders remain in database for audit trail
  - Automatic cleanup after 24 hours
  - Updated UI button text to "Complete All Orders"

### Added

- **💰 Cash Reconciliation System**: Comprehensive end-of-day cash management (2025-08-11)
  - **Physical Cash Counting**: Count by denomination (€50 notes to 10c coins)
  - **Legacy Data Import**: Seamlessly imports existing data from PHP system
    - Converts stored totals to denomination counts (€400 → 8 × €50 notes)
    - Imports supplier payments from `payeePayments` table
    - Imports daily notes from `dayNotes` table
  - **Variance Tracking**: Automatic calculation against POS totals
  - **Float Management**: Automatic carry-over from previous day
  - **Supplier Payments**: Track up to 4 cash payments to suppliers
  - **Multi-Till Support**: Manage all terminals from one interface
  - **Real-time Calculations**: Dynamic totals with Alpine.js
  - **Export to CSV**: Generate reports for accounting
  - **Audit Trail**: Complete tracking of who created/modified reconciliations
  - **Role-Based Access**: Manager and Admin only permissions
  - **Database Structure**: 3 new tables for reconciliations, payments, and notes
  - **Repository Pattern**: Clean separation of business logic
  - **Modern UI**: Responsive design with color-coded variance indicators

- **🔐 User Roles & Permissions System**: Complete RBAC implementation (2025-08-08)
  - **Three-tier Role System**: Admin, Manager, and Employee roles
  - **30+ Granular Permissions**: Organized by modules (Products, Sales, Delivery, etc.)
  - **Database Structure**: Four new tables for roles, permissions, and relationships
  - **Middleware Protection**: `role` and `permission` middleware for route protection
  - **HasPermissions Trait**: Comprehensive permission checking methods
  - **Flexible Authorization**: Works in controllers, views, and middleware
  - **Default Permissions**:
    - Admin: Full system access
    - Manager: Sales reports, analytics, product management
    - Employee: Basic operational tasks
  - **Test Interface**: Role testing page at `/roles-test`
  - **Seeder System**: Automated setup of roles and permissions
  - **User Management Integration**: 
    - Role selection in user create/edit forms
    - Security warnings for role changes
    - Prevention of self-demotion
    - Protection of last admin user
    - Role column in user list with badges
  - **Profile Role Display**:
    - Comprehensive role information section in user profile
    - Role badges with color coding and icons
    - Permission count and access summary
    - Key permissions display
    - Help text for requesting additional access
  - **Blade Integration**: Permission checks in views
  - **Security Features**: Admin override, role hierarchy, audit support
  - **Specialized Agent**: Custom Claude Code agent for role system development

- **📝 Barcode Editing Feature**: Ability to edit product barcodes with comprehensive safety measures (2025-08-07)
  - **Edit Interface**: Inline barcode editing directly from product detail page
  - **Safety Warnings**: Clear warnings about affected records before changes
  - **Confirmation Required**: Checkbox confirmation to prevent accidental changes
  - **Transaction Safety**: All updates wrapped in database transaction
  - **Automatic Updates**: Updates all dependent records:
    - Supplier link records
    - Stocking records (handles primary key change)
    - Label logs with audit trail
    - Product metadata
    - Veg details
  - **Audit Trail**: Creates special 'barcode_change' event in label_logs
  - **Validation**: Ensures new barcode is unique across products
  - **Error Handling**: Comprehensive error messages and rollback on failure
  - **Visual Design**: Yellow warning colors for high visibility
  - **Metadata Storage**: Stores old and new barcode in JSON metadata field

- **🛠️ Product Creation Form Improvements**: Enhanced functionality and fixes (2025-08-07)
  - **UDEA Button Fix**: "View on UDEA Website" button now only shows for UDEA suppliers (IDs: 5, 44, 85)
  - **Independent Support**: Added Independent supplier website links and image preview
  - **Pricing Breakdown Fix**: Initial pricing breakdown now displays correctly on page load
  - **Tax Rates Integration**: Proper tax rates loaded from database for accurate calculations
  - **Till Visibility Default**: "Show on Till" checkbox now unchecked by default (most products don't need till visibility)
  - **Dynamic Supplier Links**: Links update based on selected supplier type
  - **Improved Validation**: Better handling of supplier-specific features

- **🖼️ Independent Health Foods Product Images**: Full integration with Independent supplier (2025-08-07)
  - **Automatic Image Display**: Product images appear when supplier code is entered
  - **Smart Path Detection**: Automatically tries multiple CDN paths (`/cdn/shop/files/` and `/cdn/shop/products/`)
  - **Format Flexibility**: Supports both `.webp` and `.jpg` image formats
  - **Click-to-View Modal**: Full-size image viewer with zoom capabilities
  - **Test Page**: Dedicated testing interface at `/products/independent-test`
  - **Dynamic Loading**: Images update in real-time as supplier codes change
  - **Visual Feedback**: Hover effects and "click to view" indicators
  - **Fallback System**: Gracefully handles missing images
  - **Website Integration**: Direct links to Independent's product search
  - **Error Handling**: Console logging for debugging image load issues

- **🚨 Product Health Dashboard**: Auto-loading dashboard with critical product insights (2025-01-06)
  - **Good Sellers Gone Silent**: Identifies high performers with no recent sales
  - **Slow Movers**: Products with lowest sales velocity over 60 days
  - **Stagnant Stock**: Products with zero sales in last 30 days
  - **Inventory Alerts**: High-velocity products needing stock attention
  - **Auto-Loading**: Dashboard loads immediately on page view
  - **Stock Levels**: Current stock displayed for all dashboard products
  - **Product Links**: Click any product name to navigate to edit page
  - **Parallel Loading**: All tabs fetch data simultaneously for speed
  - **Visual Design**: Color-coded cards by severity (red, orange, yellow, blue)
  - **Empty States**: Positive feedback when no issues found
  - **Performance**: Sub-second load times with pre-aggregated data

- **📊 Categories Sales Analytics Enhancements**: Major improvements to sales analytics interface (2025-01-06)
  - **Fixed Daily Sales Chart**: Resolved chart initialization preventing graph display
  - **Enhanced Tooltips**: Added day of week to chart tooltips (e.g., "Monday, 1 Mar 2025")
  - **Expandable Product Details**: Dropdown arrows show individual product daily sales
  - **Product Mini Charts**: Each expanded product shows revenue/units trend chart
  - **Column Sorting**: Click headers to sort by Product, Units, Revenue, or Avg Price
  - **Sort Indicators**: Visual arrows show current sort column and direction
  - **Table Structure Fix**: Corrected alignment issues with expandable rows
  - **Data Type Handling**: Fixed formatCurrency() errors with proper float parsing
  - **Loading States**: Separate states for loading, empty, and data display
  - **Performance**: Lazy loading of expanded product data for efficiency

- **📂 Universal Categories Management System**: Complete category management for all product types (2025-08-05)
  - **Universal Interface**: Single system works with any product category
  - **Category Index**: Grid view with product counts, visibility stats, and progress bars
  - **Category Dashboard**: Quick actions, featured products, subcategory navigation
  - **Product Management**: Inline editing of prices and display names per category
  - **Sales Analytics**: Pre-aggregated data with charts and top products per category
  - **Till Visibility**: Toggle products on/off POS per category
  - **Search & Filter**: Find categories and products quickly
  - **Breadcrumb Navigation**: Clear path through category hierarchies
  - **Performance Optimized**: Sub-second response times using OptimizedSalesRepository
  - **Generic Repository Methods**: New category-agnostic methods for any category analysis
  - **Backward Compatible**: Existing Coffee and F&V modules continue to work
  - **Routes**: Complete `/categories` routing structure with all CRUD operations
  - **Navigation**: New "Categories" menu item in sidebar

- **🏷️ Product Display Name Management**: Universal display name editing across all products (2025-08-05)
  - **Inline Editing**: Click-to-edit display names on all product detail pages
  - **HTML Support**: Support for `<br>` tags and HTML formatting in display names
  - **Consistent UX**: Same editing pattern as fruit-veg module for unified experience
  - **AJAX Updates**: Real-time saving with loading states and success feedback
  - **Label Integration**: Display names automatically used in label generation
  - **Cross-Module**: Works for all product categories, not just F&V products
  - **API Endpoint**: New `PATCH /products/{id}/display` endpoint with JSON responses

- **☕ Coffee Module Enhancements**: Advanced product management features (2025-08-04)
  - **Inline Price Editing**: Click-to-edit pricing with VAT calculations
  - **Display Name Management**: Set custom display names for till buttons
  - **Clickable Product Names**: Navigate to product detail pages with context
  - **Context-Aware Navigation**: Smart back button text based on referrer
  - **Till Visibility Toggle**: Fixed invisible toggle switches using Alpine.js patterns
  - **Alpine.js Directive Fix**: Resolved Blade/Alpine.js `@error` directive conflicts

- **☕ Coffee Fresh Module**: New category-specific sales analytics module (2025-08-04)
  - **Complete Implementation**: Full coffee sales tracking and analytics dashboard
  - **Category Support**: Covers both "Coffee Hot" (080) and "Coffee Cold" (081) categories
  - **Sales Analytics**: Comprehensive sales dashboard with charts and individual product breakdowns
  - **Product Management**: Till visibility toggles and product listing
  - **Individual Product Charts**: Expandable rows with Chart.js visualizations per product
  - **Pattern Template**: Establishes simplified pattern for future category modules (Lunch, Cakes, etc.)
  - **Performance**: Uses OptimizedSalesRepository for instant sub-20ms queries

- **🎯 Enhanced F&V Sales Dashboard Navigation**: Advanced date range controls for sales analytics
  - **Week/Month Navigation**: Dedicated arrow buttons for intuitive week and month increments
  - **Quick Period Selector**: Pre-configured periods (Today, This Week, Last Month, Latest Data)
  - **Smart Date Defaults**: Automatically detects latest sales data period (June 18 - July 17, 2025)
  - **Manual Date Inputs**: Compact date selectors for precise range control
  - **Period Information Display**: Shows current range with duration (1 week, 30 days, etc.)
  - **Mobile Responsive Design**: Compact controls optimized for all screen sizes

- **📊 Daily Sales Chart Integration**: Interactive Chart.js visualization for F&V sales trends
  - **Dual-axis display**: Revenue (€) on left axis, Units Sold on right axis
  - **Real-time chart updates**: Chart correctly updates when navigating date ranges
  - **Smooth animations**: Professional chart transitions with data changes
  - **Currency formatting**: Proper Euro (€) display in tooltips and axis labels
  - **Loading states**: Visual indicators during data fetching
  - **Empty data handling**: Graceful "No Data" placeholders
  - **Error recovery**: Automatic chart recreation on update failures

- **🔧 Enhanced Sales Data API**: Improved backend support for sales analytics
  - **Smart date detection**: getSalesData() automatically uses most recent 30-day period with data
  - **Daily sales endpoint**: New getProductDailySales() method for individual product breakdowns
  - **Optimized data flow**: Proper integration with OptimizedSalesRepository
  - **Enhanced logging**: Comprehensive debugging information for troubleshooting

### Fixed

- **🔧 Alpine.js Template Tag Error in Coffee Sales**: Fixed "can't access property 'after', A is undefined" error (2025-08-04)
  - **Root Cause**: Invalid `x-show` directive on `<template>` tags causing Alpine.js DOM manipulation failure
  - **Solution**: Removed `<template x-show="...">` wrapper - template tags cannot use runtime directives
  - **Impact**: Coffee sales table now displays product data correctly with pagination and search
  - **Documentation**: Added troubleshooting guide entry and updated CLAUDE.md with prevention tips

- **🔧 Critical F&V Sales Table Rendering**: Fixed Product Sales Details table not displaying data
  - **Alpine.js template structure**: Resolved nested template issues preventing x-for loop rendering
  - **Table initialization**: Added missing x-init directive to trigger data loading on page load
  - **Data flow debugging**: Enhanced logging to track API responses and data processing

- **📊 Chart Recursion Error Resolution**: Fixed "too much recursion" error in daily sales chart
  - **Non-reactive chart storage**: Moved Chart.js instance outside Alpine.js reactive scope
  - **Update optimization**: Prevented infinite loops caused by Alpine reactivity watching chart internals
  - **Error recovery**: Improved chart recreation logic for failed updates

- **Label Preview Layout Improvements**: Enhanced 4x9 grid label display for better readability
  - Fixed € symbol clipping by restructuring layout from 2 rows to 3 rows
  - Moved barcode number to dedicated bottom row for improved legibility (7pt from 5.5pt)
  - Increased barcode and price horizontal space allocation (48% each from 42%/52%)
  - Larger barcode visual height (18px from 10px) for better scanning
- **Product Name Display Optimization**: Smarter text sizing for better space utilization  
  - Implemented 5-tier responsive font sizing (extra-short to extra-long)
  - Fixed character counting with mb_strlen() for proper UTF-8 support
  - Changed hyphenation from auto to manual to prevent awkward breaks
  - Added letter-spacing adjustments for long text
  - Increased line-clamp for extra-long text (5 lines) to show more content

### Added

- **🚀 Full Store Sales Data Import System**: Revolutionary performance improvement for complete store analytics
  - **Lightning-fast queries**: 100x+ performance improvement (sub-20ms vs 30+ second queries)
  - **Pre-aggregated sales tables**: `sales_daily_summary` and `sales_monthly_summary` with optimized indexes
  - **Complete store coverage**: Imports ALL product categories (not just F&V) with UUID category support
  - **Automated data synchronization**: Daily imports from POS database with scheduling
  - **Historical data processing**: Chunked imports for large datasets with progress tracking
  - **Console commands**: Complete CLI suite for sales data management
    - `sales:import-daily` - Daily sales import with flexible date options
    - `sales:import-historical` - Bulk historical data processing
    - `sales:import-monthly` - Monthly summary generation
    - `sales:test-repository` - Performance testing utilities
  - **OptimizedSalesRepository**: New repository with sub-second analytics queries for full store
    - Full store sales statistics in 17ms (vs 5-10 seconds previously)
    - Daily sales charts in 1.2ms (vs 15+ seconds previously)
    - Top products analysis across all categories in 1.3ms (vs 10+ seconds previously)
    - Category performance for all 60+ categories in 1.3ms (vs 20+ seconds previously)
    - Backward-compatible F&V methods maintained for existing integrations
  - **Import logging and monitoring**: Complete audit trail with `sales_import_log` table
  - **Memory-efficient processing**: Chunked processing for large datasets
  - **Automated scheduling**: Production-ready cron scheduling with overlap protection
  - **Extended database schema**: VARCHAR(50) category_id support for UUID-based categories
  - **🔍 Full Store Data Validation & Comparison System**: Comprehensive validation interface for data integrity
    - **Real-time validation**: Compare imported data against original POS database for all categories
    - **100% accuracy detection**: Identify perfect matches, variances, and discrepancies across full store
    - **Multi-view analysis**: Overview, daily, category, and detailed product-level comparisons for all categories
    - **Performance metrics**: Sub-second validation of entire months of full store data
    - **Interactive web interface**: Tabbed validation dashboard with real-time results for all categories
    - **CSV export**: Export detailed validation results for analysis
    - **Status indicators**: Excellent/Good/Needs Attention classification system
    - **63+ category validation**: Validates all product categories including F&V, beverages, dairy, and more
- **🚀 Fruit & Veg Sales Analytics Optimization**: Revolutionary performance improvement for F&V sales dashboard
  - **Integrated OptimizedSalesRepository**: Replaced slow cross-database queries with blazing-fast pre-aggregated data
  - **Unprecedented Speed Gains**: 100x+ performance improvement across all F&V sales operations
    - F&V Sales Stats: 5-10 seconds → **14ms** (357x faster)
    - Daily Sales Charts: 15+ seconds → **1ms** (13,513x faster) 
    - Top Products Analysis: 10+ seconds → **1ms** (7,117x faster)
    - Full Sales Data: 30+ seconds → **2ms** (18,071x faster)
  - **Sub-Second Response Times**: Complete F&V analytics dashboard loads in under 30ms
  - **Enhanced User Experience**: From unusable timeouts to instant, responsive analytics
  - **100% Data Accuracy**: Leverages validated pre-aggregated sales data
  - **Smart Search**: Ultra-fast product search across F&V sales data
  - **Performance Monitoring**: Real-time performance metrics in API responses
  - **Backward Compatibility**: All existing F&V functionality maintained while dramatically faster
- **Enhanced Label Printing System**: Comprehensive improvements to label design and functionality
  - **New 4x9 Grid Label Template**: Efficient 36 labels per A4 sheet (47.5×30.8mm each)
    - Optimized layout with product name, barcode, and price positioning
    - Intelligent price font sizing (26pt) for clear readability
    - Fixed CSS syntax errors that prevented proper font size rendering
    - Enhanced CSS specificity to override parent constraints
    - Automatic text sizing for product names within available space
    - Improved barcode positioning and sizing for better scanner recognition
  - **Enhanced Label Template System**: Multiple templates with configurable dimensions
  - **Improved Print Templates**: Consistent styling between preview and print modes
  - **Debug Features**: Comprehensive CSS debugging and troubleshooting capabilities
  - **Layout Optimization**: Flexible height management and overflow handling
  - Removed label borders for cleaner appearance when cutting
  - Enhanced padding (4mm) and margins (2mm) for easier label cutting
  - Smart unit display: shows "each" instead of "per ea" for per-unit items
  - Left-aligned product names for better readability
  - Print-optimized CSS to hide navigation buttons during printing
  - Professional borderless design for retail use
- **Enhanced Product Price Editor**: Complete redesign of product price editing interface
  - Dual input modes: gross price (inc VAT) and net price (ex VAT) with toggle switching
  - Real-time pricing breakdown showing cost, net price, VAT amount, gross price, and profit margins
  - Color-coded margin analysis (red <10%, yellow 10-20%, green >20%)  
  - Modal dialog interface replacing inline form for better UX
  - Price change preview before submission
  - Visual consistency with product creation form
  - Improved validation and error handling
  - Automatic VAT conversion using tax category rates
- **Enhanced Product Search & Filtering**: Improved supplier filtering on products page
  - Dynamic supplier dropdown that appears instantly when "Show suppliers" is checked
  - No form submission required to populate dropdown options
  - Suppliers always loaded for immediate availability
  - Automatic dropdown reset when checkbox is unchecked
  - Better performance with efficient loading strategy
- **VAT Handling Improvements**: Fixed product creation and editing to properly handle VAT calculations
  - Product creation now correctly converts VAT-inclusive prices to VAT-exclusive for database storage
  - Enhanced price update methods support both gross and net price inputs
  - Consistent VAT calculation throughout product management workflows
- **Product Detail Management System**: Complete unit and class editing functionality for fruit-veg products
  - Unit editing with inline dropdown (kilogram, each, bunch, punnet, bag)
  - Quality class assignment (Extra, I, II, III) with inline editing  
  - Self-contained database migrations for countries, units, and classes
  - Normalized veg_details table with proper foreign key relationships
  - API endpoints for unit/class CRUD operations (/fruit-veg/units, /fruit-veg/classes)
  - Alpine.js event dispatch system for clean component communication
- Combined management interface (/fruit-veg/manage) unifying availability and price management
- Activity tracking system with product_activity_logs table for audit trail without modifying POS database
- "Recently Added to Till" section on main dashboard with real-time updates
- Progressive loading with "Load More" functionality for better performance
- Database-level filtering for availability status to improve query efficiency
- Real-time dashboard updates when products are added/removed via search
- Till visibility management system replacing legacy veg_availability approach
- Integration with POS database PRODUCTS_CAT table for real-time till synchronization
- TillVisibilityService for centralized till management across product categories
- ProductsCat model for POS database integration
- Quick search component (till-visibility-search) for rapid product visibility updates
- Till visibility search bar on F&V main dashboard for instant access
- Reusable Blade components for consistent till visibility UI
- Migration script to populate PRODUCTS_CAT from veg_availability data
- Foundation for extending till visibility to Coffee, Lunch, and Cakes categories

### Added
- Comprehensive documentation restructuring with new organization system
- CONTRIBUTING.md with coding standards and development guidelines
- Project-focused README.md replacing Laravel boilerplate
- Label system documentation with complete feature overview
- Enhanced label re-queuing functionality with "Add Back to Products Needing Labels"
- Dynamic print/preview forms that use current product state instead of cached data
- Real-time label queue management without requiring full page navigation
- Featured "Available This Week" section on fruit-veg main page with clickable product cards
- Comprehensive fruit-veg product edit interface with tabbed layout (Alpine.js workaround)
- Image upload functionality for fruit-veg products with binary database storage
- Live HTML preview for display name editing with proper entity conversion
- Price history tracking and display in fruit-veg product edit interface
- Sales statistics placeholder interface for future POS integration
- Enhanced fruit-veg product image serving with cache optimization and fallback handling

### Fixed
- Price update functionality in manage screen failing due to Alpine.js `$root` scope issues (now uses self-contained savePrice method)
- Price editing UX improved with explicit save/cancel buttons instead of auto-save on blur
- Price update restrictions preventing updates to hidden products in manage screen (now allows all updates in manage, restricts only in prices page)
- N+1 query performance issues in manage screen by implementing batch loading of price records
- Availability filter not working in manage screen due to post-pagination filtering (now applied at database level)
- Delivery scanning syntax errors in Blade templates
- Division by zero in progress bar calculations
- Null date handling in delivery views
- API data consistency between scan and quantity endpoints
- Label system caching issues where re-queued products didn't appear in print/preview until navigation
- Products not disappearing from "Products Needing Labels" after printing due to incorrect requeue vs print event logic
- JavaScript errors when "Products Needing Labels" section is empty (null reference exceptions)
- Label layout order changed from price-name-barcode to name-price-barcode as requested
- ParseError in fruit-veg/availability.blade.php caused by Alpine.js @error directive conflicting with Blade compilation
- **Daily Sales Overview Chart Issues**: Fixed major Chart.js errors and date range synchronization problems
  - Chart.js "can't access property 'save', t is null" error resolved with smart chart recreation logic
  - Daily Sales Overview now properly responds to date range changes (June data shows when June selected)
  - Implemented intelligent chart destruction/recreation only when data actually changes
  - Added 100ms delay between chart destroy and create operations to prevent Canvas context issues
  - Comprehensive Chart.js error handling with user-friendly error messages
  - Fixed currency display to show Euro (€) throughout all chart labels and statistics
  - Added fallback system using live POS queries when aggregated sales data unavailable
  - Enhanced quick date buttons to use data-aware date calculations (show periods with actual sales)
  - Improved debugging with comprehensive console logging for troubleshooting chart issues
- Template literal and route generation issues in JavaScript sections of Blade templates
- Blade compilation errors due to unescaped Alpine.js event handlers
- HTML entity display issues in fruit-veg product names (display names now render <br> tags properly)
- SQL ordering errors when querying POS database tables without 'updated_at' column
- Tab component slot access compatibility issues with Laravel's slot system (documented with Alpine.js workaround)
- Products removed from till reappearing in "Recently Added" section after page refresh
- **Sales Data Validation System Issues**: Fixed multiple validation accuracy and interface problems
  - **Key matching bug**: Fixed Carbon date formatting in validation service causing 0% accuracy
  - **Daily summary grouping**: Corrected DATE() function usage and keyBy operations for proper aggregation
  - **Tab loading restrictions**: Removed dependency on overview validation for other tabs to function
  - **AJAX endpoint failures**: Fixed Daily, Category, and Detailed comparison tabs not loading data
  - **Test data cleanup**: Removed 120 synthetic test records (€12,186.17) leaving only real POS data
  - **Data integrity verification**: Achieved 100% validation accuracy with clean imported data

### Changed
- Optimized TillVisibilityService to apply filters at database query level instead of post-processing
- Enhanced manage screen performance with progressive loading and optimized queries
- Replaced "Currently Visible on Till" section with dynamic "Recently Added to Till" on main dashboard
- Refactored DeliveryController to use consistent data formatting
- Moved complex PHP logic from Blade templates to controllers
- Replaced session-based print queue with event-based re-queuing system
- Improved getProductsNeedingLabels() algorithm to properly handle timestamp-based event comparison
- Enhanced JavaScript form handling to collect current product IDs dynamically
- Updated label system UI terminology from "Add to Queue" to "Add Back to Products Needing Labels"
- Strengthened notification requirements in CLAUDE.md to ensure consistent user alerts
- Enhanced fruit-veg product display to use regular product names in headers instead of display names
- Updated all F&V views to use "till visibility" terminology instead of "availability"
- Modified FruitVegController to use TillVisibilityService instead of direct DB queries
- Replaced veg_availability table references with PRODUCTS_CAT integration
- Enhanced pricing system to track history independently of till visibility
- Improved statistics to show "visible on till" counts instead of "available" counts
- Improved fruit-veg controller methods with new routes for product editing and image management
- Updated fruit-veg main page to feature available products with responsive grid layout

### Technical Improvements
- Added EVENT_REQUEUE_LABEL to LabelLog model with database migration
- Implemented proper null checks and conditional initialization in JavaScript
- Optimized label event tracking with timestamp-aware logic
- Enhanced error handling and user feedback in label operations
- Implemented binary image storage for fruit-veg products in POS database IMAGE field
- Enhanced troubleshooting documentation with comprehensive tab component slot access analysis
- Added working Alpine.js alternatives for problematic Laravel Blade components
- Improved fruit-veg image serving with proper cache headers and transparent PNG fallbacks
- Enhanced AJAX form submissions for real-time fruit-veg product updates without page refresh

## [0.3.0] - 2024-01-20

### Added
- Delivery verification system with CSV import and barcode scanning
- Real-time mobile-optimized scanning interface
- Discrepancy tracking and reporting for deliveries
- Product creation from unmatched delivery items
- Supplier image integration with hover previews
- Export functionality for delivery discrepancies

### Changed
- Enhanced product image support for new products without existing models
- Improved barcode extraction with multiple pattern support

## [0.2.0] - 2024-01-15

### Added
- Advanced pricing system with VAT-inclusive calculations
- 4-decimal precision storage for accurate VAT preservation
- Live supplier price comparison with Udea
- Quick action buttons for competitive pricing strategies
- Transport cost analysis (15% calculation)
- Customer price extraction from supplier pages

### Changed
- Consolidated pricing interface in product management
- Enhanced supplier integration with live data

## [0.1.0] - 2024-01-10

### Added
- Initial Laravel 12 application setup
- uniCenta POS database integration
- Product catalog with real-time stock levels
- Supplier management and cost tracking
- Admin dashboard with sidebar navigation
- Username/email authentication with Laravel Breeze
- Product search and filtering capabilities
- Supplier external integration for images and links

### Security
- Secure authentication system with email verification
- Role-based access control foundation

## Development Guidelines

When making changes:
1. Update this changelog in the Unreleased section
2. Follow the categories: Added, Changed, Deprecated, Removed, Fixed, Security
3. Reference issue numbers where applicable
4. Move Unreleased items to a new version section when releasing

[Unreleased]: https://github.com/yourusername/osmanagercl/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/yourusername/osmanagercl/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/yourusername/osmanagercl/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/yourusername/osmanagercl/releases/tag/v0.1.0