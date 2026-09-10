# Udea Pallet Volumes

## Overview

Udea publishes, for every product, the amount of pallet space it occupies. We capture that data
and use it to show a **running pallet-fill total on the order review page**, so an order can be
built to fill whole pallets rather than discovering the shortfall after it is placed.

The catch that shapes the whole design: **Udea renders this data only on the logged-in basket
page** (`/orders/cart`), and only for products currently in the basket. Product detail pages,
search listings and the site JS carry none of it, and there is no API. Verified 2026-09-09
against `/products/product/{slug}`, `/search/?qry={code}` and every script the basket loads.

The figures are **per-product constants**, independent of ordered quantity, so a single pass
over a basket containing the range captures them permanently. It only needs repeating when the
product range changes.

## How Udea calculates it

From `https://www.udea.nl/js/orders/order.js:1556`:

```js
sve        = parseFloat(productPalletDataElement.attr("data-sve")),
volume     = parseFloat(productPalletDataElement.attr("data-volume")),
totalVolume     = sve * volume * qty,
volumePercentage = (totalVolume / totalPalletVolume) * 100;
return Math.round(volumePercentage * 100) / 100;   // rounded PER LINE, then summed
```

with

```js
totalPalletVolume = numEuroPallets * 250 + numBlockPallets * 360;
```

Two details matter and are easy to get wrong:

1. **`sve` is a multiplier, not a case count.** It is the number of consumer units per *order
   unit*. Omitting it understates every line — a basket totalling 470.66 comes out as 141.04.
2. **Each line's percentage is rounded to 2dp before summing.** Summing raw volumes and
   rounding once gives 188.26% where Udea's page shows 188.24%.

### Where the data sits in the markup

Each basket row carries a hidden span, and the capacities appear once per page:

```html
<span class="hidden product-pallet-data" data-sve="10" data-volume="0.65" data-volume-percent="0"></span>

<span class="hidden" id="pallet-data"
      data-volume-euro-pallet="250"
      data-volume-block-pallet="360"></span>
```

`data-volume-percent` is always `0` in the served HTML — Udea fills it in with JavaScript when
the Pallet Calculator tab is opened, so it cannot be read server-side.

## Architecture

### Components

- **`App\Services\UdeaPalletVolumeService`** — logs in, fetches the basket, parses it, upserts.
  Also the long-term read API (`volumesForCodes`, `summarise`, `capacityFor`,
  `palletFractionFor`). Deliberately a **sibling of `UdeaScrapingService`**, not an extension:
  that class is large, reachable from live order pages and covered only by ageing tests, while
  this one does a single job and only ever issues GETs.
- **`App\Console\Commands\SyncUdeaPalletVolumes`** — `udea:sync-pallet-volumes`. The engine.
- **`App\Http\Controllers\UdeaPalletVolumeController`** — admin page + streamed sync.
- **`App\Models\UdeaProductCard`** — storage, plus `palletVolumeFor()` / `hasPalletData()`.
- **`resources/views/components/udea-pallet-summary.blade.php`** — the order-page running total.

### Database schema

Pallet data lives on the existing `udea_product_cards` table (app `mysql` connection, unique on
`supplier_code`), added by
`database/migrations/2026_09_09_000100_add_pallet_volume_to_udea_product_cards_table.php`:

```sql
pallet_sve         DECIMAL(10,4) NULL,  -- data-sve
pallet_unit_volume DECIMAL(10,4) NULL,  -- data-volume
pallet_scraped_at  TIMESTAMP     NULL   -- separate from scraped_at, on purpose
```

**Why here and not elsewhere:**

- **Not on `PRODUCTS`** — Udea is one supplier of several, and this is supplier-specific
  logistics data, not a product attribute.
- **Not a new generic `supplier_pallet_volumes` table** — no other supplier publishes volumes,
  and if one ever does its units and semantics will not match Udea's 250/360 model. It would
  cost a join on every order page to buy speculative generality.
- **On `udea_product_cards`** — it already *is* "durable cache of scraped per-product Udea
  facts", is a strict 1:1 on `supplier_code`, and `supplier_code` is the join key back to
  `supplier_link.SupplierCode`.

### Data flow

1. Operator empties the Udea basket and bulk-uploads the product range through Udea's own Excel
   importer (XLS/XLSX/CSV/TSV, two columns: `Product SKU / EAN Number`, `Amount`).
2. Operator presses **Sync from basket** on the admin page, or runs the artisan command.
3. Service authenticates, GETs `/orders/cart`, parses each row.
4. `updateOrCreate` by `supplier_code` writes the three pallet columns.
5. The order review page reads them and shows a live pallet-fill total.

## Configuration

Pallet capacities are cart-level constants, identical for every product, so they live in
`config/suppliers.php` rather than in a per-product column:

```php
'external_links' => ['udea' => [
    'pallet' => ['euro' => 250, 'block' => 360],
]],
```

The sync **asserts the values on the live page match config** and warns on drift rather than
silently using stale numbers.

Credentials reuse the existing Udea settings (`config/services.php`):

```env
UDEA_BASE_URI=https://www.udea.nl
UDEA_USERNAME=...
UDEA_PASSWORD=...
```

## Usage

### Refreshing the data

**System Tools → Udea Pallet Volumes** (admin only). The page shows current coverage, when it
was last synced, and the workflow:

1. Empty the Udea basket — back up the live order first if one is pending.
2. Upload the product list through Udea's Excel importer, quantity 1 each.
3. Press **Sync from basket**.
4. Empty the basket again and restore the real order.

The page is **read-only against Udea** — it never adds, changes or removes anything there.

Large ranges can be done in batches; each sync is additive and idempotent.

### On the order review page

For Udea orders (supplier IDs 5, 44, 85) `/orders/{order}` shows a bar at the top with `−`/`+`
steppers for **Europallets** and **Blockpallets**, a fill bar and a live percentage that
recalculates as quantities change. Green → amber past 90% → red over 100%. The pallet selection
persists per-browser in `localStorage`.

Lines with a quantity but no pallet data are counted and reported, so an understated total is
always visible as such rather than silently wrong.

**`/orders/create` deliberately has no total** — it is the generation form (supplier + dates)
and has no line items yet.

### Developer usage

```php
$service = app(App\Services\UdeaPalletVolumeService::class);

// Volume per order unit, keyed by supplier code
$volumes = $service->volumesForCodes(['5004482', '45006']);   // ['5004482' => 6.5, ...]

// Total + fill percentage for a set of lines, using Udea's per-line rounding
$summary = $service->summarise(
    [['volume_per_unit' => 6.5, 'quantity' => 2]],
    $service->capacityFor(euroPallets: 1, blockPallets: 0)    // 250.0
);
// ['volume' => 13.0, 'percent' => 5.2, 'capacity' => 250.0, 'counted' => 1]

// Single product
$card = App\Models\UdeaProductCard::where('supplier_code', '5004482')->first();
$card->palletVolumeFor(3);   // sve * unit_volume * 3
```

### CLI

```bash
php artisan udea:sync-pallet-volumes --dry-run   # report only, writes nothing
php artisan udea:sync-pallet-volumes
```

### Routes

- `GET  /tools/udea-pallet-volumes`      → `tools.udea-pallet-volumes`      (`role:admin`)
- `POST /tools/udea-pallet-volumes/sync` → `tools.udea-pallet-volumes.sync` (`role:admin`)

## Integration points

- The order quantity multiplied is the value in the `.qty-input` field — **cases for case
  products, units otherwise** — matching what `OrderService::exportToCsv()` sends to Udea.
- `review-table.blade.php` and `review-table-christmas.blade.php` emit
  `data-pallet-volume="{{ sve * unit_volume }}"` on each quantity input; the summary component
  sums `value × data-pallet-volume` across them.
- Shares `udea_product_cards` with the price-tier cache written by `udea:refresh-tiers`
  (see [Supplier Integration](./supplier-integration.md)).

## Testing

- `tests/Unit/UdeaPalletVolumeServiceTest.php` (16 tests)
- `tests/Feature/UdeaPalletVolumeSyncTest.php` (8 tests)
- `tests/Fixtures/udea-cart-298-lines.json` — golden master

The **golden master** is a real 298-line basket captured 2026-09-09 (719 units, €5,675.09).
Udea's own page displayed 188.24% of a Europallet and 130.80% of a blockpallet for it, and the
tests assert those exact figures. It pins the per-line rounding semantics against any future
refactor and is price-independent, so it detects quantity drift that money totals would mask.

```
sum(sve * unit_volume * qty)          = 470.6612
per-line rounded, 1 Europallet  (250) = 188.24 %
per-line rounded, 1 blockpallet (360) = 130.80 %
```

The other high-value test asserts `0.22` and `2.5` survive parsing *and* the database round
trip — it catches both an integer column and a `decimal:` cast returning strings.

## Troubleshooting

#### "No products with pallet data found in the basket"
The basket is empty, or the upload has not completed. Load `/orders/cart` on Udea and confirm
products are listed.

#### "Udea basket page did not come back logged in"
Check `UDEA_USERNAME` / `UDEA_PASSWORD`. Udea answers a bad login with a 200, not an error, so
the service checks for account markers in the returned HTML.

#### The sync reports products as "Updated" that have never been synced
**This is expected and not a bug.** The counter reports whether the *row* in
`udea_product_cards` already existed, not whether pallet data was already present. The price
scraper (`udea:refresh-tiers`) creates rows keyed on the same `supplier_code`, so a product it
has seen before is an update even though its pallet columns were empty. Confirm with
`UdeaProductCard::whereNotNull('pallet_scraped_at')->count()` — if it rose by the full number of
products found, every one received pallet data.

#### The order page total looks too low
Check the amber warning under the bar. Products with no pallet data contribute nothing, so
coverage gaps understate the total. Sync again with those products in the basket.

#### Page times out or runs out of memory
The sync must not run in-request. A basket holding a full range is ~15MB of HTML, while PHP-FPM
here is capped at `max_execution_time = 30` and `memory_limit = 128M`. The controller therefore
shells out to the artisan command and streams the output, so the work happens under CLI PHP
where those limits are lifted. If a very large sync is ever cut off, check the web server's
`fastcgi_read_timeout` rather than PHP's limits.

## Known limitations

- **`sve` is not always our `case_units`.** They agreed for 252 of 260 products checked;
  weight-priced goods differ (e.g. `45006` Kipfilet has `sve` 0.22). Worth spot-checking an
  order against Udea's own calculator once coverage is complete.
- **Some products genuinely have `data-volume` of `0`** and contribute nothing to the total.
- **The A2 and Grid order layouts have no pallet bar.** They carry their own quantity markup
  rather than sharing `review-table.blade.php`, so they would need the same two edits.
- Only Udea publishes this data; the feature is Udea-only by nature.

## Related documentation

- [Supplier Integration](./supplier-integration.md)
- [Order Manager](./order-manager.md)
- [POS Integration](./pos-integration.md)
