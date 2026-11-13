# Dynamis Invoice Pricing

**Status**: Shipped 2026-11-11
**Who**: managers and admins only (`role:manager,admin`)
**Entry point**: Delivery Dockets → New → **Dynamis invoice (pricing)** tab, or the
**Open pricing page** banner on a Dynamis docket.

## What it does

Dynamis (POS `SupplierID = 56`) sends a PDF invoice (`FC…`) each week. Uploading it:

1. creates a **delivery docket** with every invoice line (costs, quantities, the PDF filed
   against it), and
2. opens a **pricing page** where each fruit & veg line gets a guide RRP and is priced for
   the till per kg or each.

It replaces the owner's `dynamis_invoice.py` → spreadsheet routine. The older XLSX tab
([Dynamis Delivery Import](./dynamis-delivery-import.md)) still works and is unchanged.

## The formula

```
guide RRP per invoice unit = net unit price × transport × markup      (× (1 + VAT) if the till product has VAT)
box price                  = guide × quantity ÷ packages
per kg                     = box price ÷ kg in a box
each                       = box price ÷ count in a box
```

- Transport defaults to **1.2** and markup to **1.5** (`app_settings` keys
  `dynamis.transport_multiplier`, `dynamis.markup`). Both can be changed for one invoice at
  the top of the page (stored in `deliveries.import_data.dynamis`); **Make these the
  default** also updates the settings.
- The page shows the invoice's actual transport (`Shipping Fees ÷ Gross Total Excl.`) beside
  the figure in use. Some weeks no delivery charge is on the invoice.
- No rounding: the suggestion is to the cent and the manager types the shelf price.
- The formula lives in `DynamisPricingService::guidePrice()` / `suggestedPrice()` and is
  mirrored in the page's Alpine code so it recalculates as a count is typed. Change both.

## Line options

| Invoice unit | Per kg | Each |
|---|---|---|
| `KG` (apples, kiwis) | the guide | box weight × guide ÷ **count typed in** |
| `COL` box (avocados, cucumbers, lettuce) | box ÷ **kg in a box** (read from "1KG" / "ABOUT 4.5KG", or typed) | box ÷ **count** |
| `PCE` piece (bunches, punnets) | needs kg per box typed | the guide |

The count for a box line is prefilled when the description gives it:

- `12 PIECES`, `6 PIECES`, `10 BUNCHES` → used as is.
- `SIZE 22` → prefilled but flagged "check the box" (it may not be the count). A range
  (`SIZE 3/4`, `14-21`, `150/180`) and any `SIZE` on a per-kg line are ignored.
- Nothing on the invoice (Bacon avocado) → the box is highlighted until someone counts.

Each line can also be **skipped** (till price left alone) and un-skipped.

The default choice is: what was used last time for that Dynamis code → how the till sells
the matched product (`vegDetails.unitId`) → the invoice unit. A warning shows when the choice
disagrees with the till unit.

## Matching lines to till products

- **Saved link** (`dynamis_product_links`, keyed by Dynamis code): matched on the docket
  straight away. The link also remembers `pricing_mode`, `units_per_box` and `kg_per_box`.
- **Barcoded lines** (an EAN under the item): matched by till barcode. They are listed in
  their own section with cost and guide RRP but are **not priced here**; use the docket's
  normal cost/price tools (they carry VAT and are not fruit & veg).
- **No link yet**: a name-based guess is shown as "Looks like …" and does nothing until a
  manager clicks **Yes, use this** or picks another product with the standard product
  search. A wrong guess never reaches the docket.
- **Till visibility**: every linked or suggested product carries an **On till** / **Not on
  till** badge (`PRODUCTS_CAT`, read live), and the picker marks each result the same way
  (`show-till` on `x-product-search`). Guesses prefer products showing on the till
  (`DynamisPricingService::nameGuesses`): a hidden product is suggested only when nothing
  on the till matches at all, or when its name is a near-exact match (score ≥ 0.9) and the
  till's best match is not. Ties go to the visible product. Guesses for lines not yet
  linked are recomputed each time the page opens (`refreshGuesses`), so they follow what
  is on the till that day. A linked product that is hidden has a **Show on till**
  button (`showOnTill` → `TillVisibilityService::setVisibility`, the same switch and
  activity log as Fruit & Veg > Manage). It is not offered for an unconfirmed guess, and
  the page never hides a product.
- No till product → the guide is shown but the line cannot be applied. New products are
  still created in Fruit & Veg. See **New product from a line** below. (AI suggestions were left out on
  purpose; add them if they turn out to be needed.)

## New product from a line

A line with no till product has a **New product** button (beside "Choose till product" /
"Choose another"). The form is prefilled from the line and creates a fruit & veg till
product in one step.

| Field | Starts as |
|---|---|
| Name | the description without class / size / weight / country, in title case (`newProductDefaults`) |
| Till button text | empty (the till then shows the name); stored in `PRODUCTS.DISPLAY` |
| Category | must be chosen: Fruit (`SUB1`) or Vegetables (`SUB2`) |
| Country of origin | the invoice origin |
| Class | the class in the description ("CLASS II"), else Class I |
| Sold | the line's Per kg / Each choice and box count / weight (editable in the form) |
| Price | the guide price; cost inc. delivery shown beside it |
| Picture for the till | optional; scaled down to 128 px and stored in `PRODUCTS.IMAGE` |
| Show on the till | ticked |

- The code is the next free one in the fruit & veg range (`BarcodeGeneratorService`).
  VAT is zero (`TAXCAT 000`, as every F&V product). **Cost price** (`PRICEBUY`) is the
  invoice cost for one kg / one piece as sold, **without delivery**.
- The form lists fruit & veg already in the product list with a similar name (most are
  hidden from the till) with **Use this**, so an old product is re-used rather than
  duplicated. A name that already exists is refused.
- On save: `FruitVegProductService::create` (product, `vegDetails`, stock rows, label
  log, metadata, label queue with reason `new_product`, till button), then the line is
  linked (remembered for this Dynamis code) and marked priced.
- `FruitVegProductService` repeats the shared steps of `ProductController@store` rather
  than replacing them; that page is unchanged. Keep the two in step if either changes.

## The new price box

- It starts on the **price already on the till** for the linked product, so nothing
  changes unless someone decides it should. (`priceSource` on each line in the page's JS:
  `till` → `guide` → `typed`.)
- **Use guide €x.xx** puts the guide price in the box; it then follows the count, weight,
  transport and markup as they are edited. **keep €x.xx** goes back to the till price.
  Typing a price overrides both.
- Before a product is confirmed the box shows the suggested product's till price; with
  no product and no suggestion it is empty until **Use guide** is clicked or a price is
  typed. Either way the line cannot be applied until a till product is chosen.
- **Tick all with a new price** ticks only linked lines whose box differs from the till.
  Applying an unchanged price writes nothing to the till; it just marks the line done and
  remembers per kg / each and the count.

## Cost and margin

Under the price box each line shows **cost €x.xx /kg (or each) inc. delivery · margin
n%**: invoice cost × transport for one kg or one piece as sold (`landedCost` in the
page's JS), and the share of the price before VAT left after it. It follows the price
box, the count / weight and the transport setting live, and turns red with "below cost"
when the price no longer covers it. "Delivery" here is the transport multiplier (the
estimate used for the guide), not the actual charge on the invoice. Display only; nothing
is stored.

## Country of origin

- The invoice's origin column is a two-letter code (`FR`, `ES`). `countries.code` holds
  the ISO code for each country the shop lists (filled by migration
  `2026_11_11_000002_fill_country_codes`; Corsica has none), so a code resolves to a
  country (`DynamisPricingService::originCountry`).
- Each linked line shows the till product's country (`vegDetails.countryCode`, the one
  printed on the label) against the invoice's: "Origin Spain" when they agree, or
  "Till says Ireland, invoice says France" with a **Change to France** button.
- The country changes **only on that click** (`useInvoiceCountry`: `vegDetails` upsert +
  label queue with reason `country_updated`, as Fruit & Veg > Manage does). Apply never
  touches it.
- One till product on two lines with different origins (broccoli from FR and IT): each
  line shows its own comparison plus "Also from Italy on this invoice"; whoever prices
  decides which country the label carries. Changing it on one line updates what the
  other line shows.
- An origin code that is not in the country list is shown as such and has no button;
  add the country (with its code) to `countries` first.

## Apply

**Apply** (one line, or **Apply ticked**) calls `FruitVegPriceService::setPrice()`, the same
path as Fruit & Veg → Manage: POS `PRICESELL`, a `veg_price_history` row, and the label
print queue. It then records the applied price on the line and saves the mode and count on
the link for next week. Nothing else on the docket changes; costs are still updated from
the docket page.

## Parser

`DynamisInvoicePdfParserService` runs `pdftotext -layout` (poppler-utils, already required
by the invoice parser) and reads the current English-label layout. It checks that the lines
add up to *Gross Total Excl.*; a mismatch is shown as a warning on the page and usually
means Dynamis changed their layout (adjust `ITEM_RE`). The same invoice number is never
imported twice: a second upload opens the existing pricing page.

## File map

- `app/Http/Controllers/DynamisPricingController.php`
- `app/Services/DynamisPricingService.php` – formula, docket creation, link / apply / skip
- `app/Services/DynamisInvoicePdfParserService.php`
- `app/Services/FruitVegPriceService.php` – shared till price write (also used by `FruitVegController::updatePrice`)
- `app/Services/DeliveryDocumentService.php` – files the PDF (extracted from `DeliveryController`)
- `app/Models/DynamisPricingLine.php`, migration `2026_11_11_000001_create_dynamis_pricing_lines_table`
- `resources/views/deliveries/dynamis-pricing.blade.php`
- Tests: `tests/Feature/DynamisPricingTest.php`, `tests/Unit/DynamisInvoicePdfParserServiceTest.php`,
  `tests/Unit/DynamisPricingFormulaTest.php`, fixture `tests/Fixtures/dynamis_invoice_FC10006631.txt`

## Deploying

`php artisan migrate` (one new table, three nullable columns on `dynamis_product_links`).
`pdftotext` must be on the server's PATH (`poppler-utils`).
