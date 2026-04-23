# Dynamis Fruit & Veg Delivery Import

**Status**: Shipped (initial release 2026-04-22)
**Supplier**: Dynamis (POS `SupplierID = 56`) — primary fruit & veg supplier.
**Entry point**: `/deliveries/create` → **XLSX (Dynamis)** tab.

## Why this exists

Fruit & veg cost updates were being done by hand through `/fruit-veg/manage`.
Dynamis sends a per-delivery XLSX (`Historique(NN).xlsx`) with SKU, description,
unit cost, unit type and line total. Parsing this file and matching the lines
to till products removes the manual step and gives a delivery record in the
same place as other suppliers.

## What it does

1. User uploads the Dynamis XLSX at `/deliveries/create` (XLSX tab).
2. The file is parsed in PHP (PhpSpreadsheet). Product lines are separated from
   the `DIV0010` "MISCELLANEOUS TRANSPORT" freight row.
3. Each line is matched against till-visible F&V products (categories `SUB1`,
   `SUB2`, `SUB3` present in `PRODUCTS_CAT`) using, in order:
   - a persisted row in `dynamis_product_links` (saved from a prior import);
   - a fuzzy name score (Jaccard over normalised tokens, threshold 0.75);
   - otherwise left unmatched with top-3 suggestions.
4. For unmatched items the user can either pick manually from a dropdown
   (seeded with fuzzy suggestions) or click **Suggest with AI** to call the AI
   system (Mistral/Gemini, configurable per feature).
5. Confirmed matches are saved to `dynamis_product_links`. Next time the same
   SKU appears it auto-matches as `matched_saved`.
6. Submit creates a `Delivery` (supplier_id = 56) via the existing
   `DeliveryService::importFromPdfData()` path. The freight total is written
   to `deliveries.freight_charge`.

## Architectural decisions

- **SupplierLink is not used.** Dynamis F&V SKUs have no entries in
  `supplier_link`, so matching is name-based with a dedicated
  `dynamis_product_links` table. Over a handful of imports this table becomes
  the primary source of matches.
- **No Python.** The file is simple XLSX — PhpSpreadsheet (already installed)
  is sufficient. No subprocess.
- **No new products created.** Unmatched items are dropped unless the user
  picks a till product for them. This is deliberately conservative and matches
  how the F&V till catalogue is curated manually.
- **Till visibility is required.** Candidate products come exclusively from
  the join of `PRODUCTS_CAT` × `CATEGORY IN (SUB1, SUB2, SUB3)`. A hidden
  product cannot be picked.
- **Kg rows**: `Total_Ordered_Units = boxes` (col F), with
  `weight_per_unit = net_kg / boxes`. This mirrors the Independent
  weight-based flow so downstream scan / cost code is consistent.
- **Retail price update is out of scope.** `/fruit-veg/manage` still owns RSP.
  This feature ends at creating the delivery with the correct `unit_cost`.

## Dynamis XLSX layout

Single sheet, row 1 = header. Columns A–L:

| Col | Meaning | Notes |
|-----|---------|-------|
| A | BL | Delivery reference (used as `order_number`) |
| B | Code Article | Supplier SKU (e.g. `POM0481`) — key for matching |
| C | Code douanier | Not used |
| D | Provenance | Country of origin |
| E | Article | English description with BIO marker |
| F | Colis | Box count (used as unit count for kg rows) |
| G | Pièces | Piece count (used for C / P unit rows) |
| H | Poids Brut | Gross weight |
| I | Poids Net | Net weight (used for kg-row `total_weight`) |
| J | Prix | Unit cost (EUR) |
| K | Unité | `K` = kilo, `C` = count, `P` = pot |
| L | Total | Line cost |

Freight marker: column B = `DIV0010` **or** column E matches
`/MISCELLANEOUS TRANSPORT/i`. Such rows are routed to
`data.costs.items` so `freight_charge` is populated.

No VAT column — fruit & veg is 0% VAT in Ireland. Tax is set to 0.

## File map

**New**
- `app/Services/DynamisXlsxParserService.php` — XLSX → structured items /
  freight / totals.
- `app/Services/DynamisMatcherService.php` — saved-link + fuzzy matching.
  Exposes `matchItems()`, `loadTillProducts()`, and `normalize()`.
- `app/Services/DynamisAiSuggesterService.php` — bulk AI suggestion pass
  using `AiSettingsService::get('dynamis_matcher', …)`. Chunks at 40 items.
- `app/Models/DynamisProductLink.php` — persisted supplier-code → product map.
- `database/migrations/2026_04_22_154911_create_dynamis_product_links_table.php`.
- `resources/views/deliveries/partials/xlsx-tab.blade.php` — Alpine tab with
  parse preview, matched / unmatched / freight sections, AI button.
- `tests/Unit/DynamisXlsxParserServiceTest.php`,
  `tests/Unit/DynamisMatcherServiceTest.php`,
  `tests/Fixtures/dynamis_historique_sample.xlsx`.

**Changed**
- `app/Http/Controllers/DeliveryController.php` — `parseXlsx`, `aiSuggestXlsx`,
  `storeXlsx` actions; new services injected; `'dynamis' => 56` added to
  `mapSupplierNameToId()`.
- `app/Services/DeliveryService.php` — `importFromPdfData()` now honours a
  preset `product_id` on incoming items (required by the Dynamis flow because
  matching happens Laravel-side, not via `SupplierLink`).
- `app/Services/AiSettingsService.php` — registered the `dynamis_matcher`
  feature key. Falls through to `invoice_parsing` when unset.
- `routes/web.php` — `deliveries.parse-xlsx`, `deliveries.ai-suggest-xlsx`,
  `deliveries.store-xlsx`.
- `resources/views/deliveries/create.blade.php` — third tab added; partial
  included.

## Matching details

Normalisation (`DynamisMatcherService::normalize()`):

- uppercase;
- strip HTML tags (the POS `DISPLAY` field is HTML-wrapped);
- drop non-letters, tokenise on whitespace;
- drop tokens shorter than 2 chars;
- drop stopwords (`BIO`, `ORGANIC`, `KG`, `G`, `POT`, `POTS`, `POTTED`,
  `BUNCH`, `BUNCHED`, `PIECE`, `PIECES`, `LOOSE`, `SMALL`, `LARGE`,
  `BABY`, `MINI`, …);
- singularise by stripping trailing `S` (with `IES → Y` guard).

Scoring = Jaccard `|A∩B| / |A∪B|` with a `+0.15` boost when the first token of
the supplier description appears anywhere in the candidate's tokens
(emphasises the head noun — "apple", "avocado" — while still demanding
overall overlap). Threshold **0.75** for `matched_fuzzy`; below that the item
surfaces as `unmatched` with the top 3 candidates as suggestions.

## AI provider config

- Feature key: `dynamis_matcher`.
- Default provider: `mistral` (chat-completions), model
  `mistral-small-latest`.
- Configurable at runtime through `AiSettingsService::set()` (same mechanism
  the AI diagnostics page uses). Falls through to `invoice_parsing` when
  unset.
- The suggester sends **one** chat request per 40 unmatched items, asks for
  JSON-only output, and ignores any match whose `product_id` isn't in the
  candidate set. Failures are logged and treated as "no suggestions" — the
  user can still pick manually.

## Verification

1. `php artisan migrate` — creates `dynamis_product_links`.
2. `php vendor/phpunit/phpunit/phpunit tests/Unit/DynamisXlsxParserServiceTest.php tests/Unit/DynamisMatcherServiceTest.php`.
3. Tinker sanity:
   ```bash
   php artisan tinker --execute='print_r(array_slice(
     app(App\Services\DynamisXlsxParserService::class)
       ->parse("/var/www/html/osmanagercl/JFolder_temp/Historique(65).xlsx")
       ["data"]["items"], 0, 3));'
   ```
4. Browser: `/deliveries/create` → XLSX tab → upload the sample file →
   **Parse & Preview** → confirm matched items, pick till products for a few
   unmatched, optionally press **Suggest with AI** → **Import**.
5. Re-upload the same file — previously confirmed items now render as
   `matched_saved` (green badge).

## Known follow-ups

- **Retail price propagation** — the delivery currently updates `unit_cost`
  only. If we want imports to feed into `veg_price_history` / prompt an RSP
  update, wire a hook on delivery completion.
- **Non-till products** — currently hidden from the UI. If the till catalogue
  needs a "create this product from the delivery line" escape hatch, surface
  a separate section on the preview.
- **Bulk edit of saved links** — there's no admin view for
  `dynamis_product_links` yet. If mismatches creep in, only the next import
  (with the user overriding the suggestion) can overwrite a row.
- **Supplier detection** — the tab is hard-wired to supplier 56. If Dynamis
  ever ships PDF invoices alongside XLSX, add `dynamis` to
  `DeliveryParsingService` / Python dispatcher.
