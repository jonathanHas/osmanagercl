# Product Search (`x-product-search`)

One Blade component, backed by one JSON endpoint, for every page that needs to find a
product. It replaces the per-page typeaheads that each had their own controller method
and JSON shape.

| Piece | Location |
|---|---|
| Component | `resources/views/components/product-search.blade.php` |
| Endpoint | `GET /api/products/search` → `App\Http\Controllers\Api\ProductSearchController` (auth) |
| Service | `App\Services\ProductSearch\ProductSearchService` (+ `ProductSearchCriteria`, `ProductSearchVocabulary`) |
| Test page | `/products/search-test` (both modes, debug panel with `took_ms`) |
| Tests | `tests/Feature/ProductSearchApiTest.php`, `tests/Unit/ProductSearchVocabularyTest.php`, `tests/Concerns/CreatesProductSearchPosTables.php` |
| First consumer | `/products` (`ProductController@index`, `resources/views/products/index.blade.php`) |

## Using the component

```blade
{{-- Picker: choose one product, the page reacts --}}
<div x-data="{ product: null }" x-on:product-search:selected="product = $event.detail">
    <x-product-search mode="picker" name="product_id" placeholder="Scan or type a product…" />
    <p x-text="product ? product.name : ''"></p>
</div>

{{-- List: full results table with your own actions per row --}}
<x-product-search mode="list" :initial="$initial" :camera="true">
    <x-slot:row-actions>
        <a :href="product.edit_url">Edit</a>
    </x-slot:row-actions>
</x-product-search>
```

### Props

| Prop | Default | Notes |
|---|---|---|
| `mode` | `picker` | `picker` (dropdown) or `list` (results table with pagination) |
| `url` | `route('api.products.search')` | Override only to point at a compatible endpoint |
| `stocked` | `true` | Initial state of the stocked filter |
| `show-stocked-toggle` | `true` | Renders the "Include unstocked" checkbox |
| `supplier-id`, `category-id` | `null` | Hidden fixed filters sent with every request |
| `exclude-ids` | `[]` | Product IDs never returned (e.g. already on an order) |
| `per-page` | 10 picker / 20 list | Max 50 |
| `placeholder` | generic text | |
| `min-length` | `1` | Characters before a picker search runs |
| `debounce` | `250` | ms after the last keystroke |
| `autofocus` | `false` | |
| `camera` | `false` | Adds the camera button and pushes `resources/js/barcode-scanner.js` once |
| `initial` | `null` | List mode: a server-rendered first response (`ProductSearchService::search()` array) so the first paint needs no fetch |
| `sync-url` | `true` | List mode: mirror `q`, `stocked=0`, `page` into the address bar with `history.replaceState` |
| `name` | `null` | Picker: name of a hidden input that receives the selected product ID |

### Slots

- `row-actions` (list mode) — rendered inside the `x-for` template, so Alpine expressions on `product` work (`:href="product.edit_url"`, `x-on:click="doThing(product.id)"`). Nested `x-data` inside the slot can read `product` too.
- `empty` — text for the "No products found" state.

### Events (bubble from the component root)

| Event | `detail` | When |
|---|---|---|
| `product-search:selected` | the product item (see JSON below) | picker row chosen (click, Enter, scanner) |
| `product-search:cleared` | — | the × button / Escape cleared the input |
| `product-search:results` | `{ data, meta, url }` | every successful search (both modes) |

### Keyboard / scanner behaviour

- ↑ / ↓ move the highlight, Enter selects, Escape closes (picker) or clears (list).
- Enter with no dropdown open runs the search immediately and, in picker mode, commits the
  top match. A USB barcode scanner sends the code followed by Enter, so a scan selects the
  product in one go.
- The camera button opens a modal driven by `window.BarcodeScanner`; a decoded barcode is
  put in the input and searched (picker mode also selects the top match). GS1 `01` AIs are
  unwrapped to the GTIN-14.
- Responses are guarded by a request counter: a slow earlier response never overwrites a
  newer one.

## Endpoint

`GET /api/products/search` (inside the `auth` group; unauthenticated JSON requests get 401).

| Param | Type | Default |
|---|---|---|
| `q` | string ≤100 | `""` (browse listing ordered by name) |
| `stocked` | `0/1/true/false` | `1` |
| `supplier_id`, `category_id` | string | — |
| `exclude` | comma-separated product IDs (≤200) | — |
| `page` | int ≥1 | 1 |
| `per_page` | int 1–50 | 20 |

Response (this shape is canonical; do not add per-page variants):

```json
{
  "data": [
    {
      "id": "98f8a46e-…", "code": "8721325594341", "reference": "8721325594341",
      "name": "Chocolatemakers forest fruit milk chocolate 100 gram", "display": null,
      "category_id": "…", "category_name": "Chocolate",
      "price_sell": 5.08, "price_with_vat": 6.25, "vat_rate": 0.23, "vat_label": "23.0%",
      "vat_badge_class": "bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100",
      "stock_units": 7, "stock_location": null, "has_stock_record": true,
      "is_stocked": true, "is_service": false, "has_image": false,
      "image_url": "https://cdn.ekoplaza.nl/ekoplaza/producten/small/8721325594341.jpg",
      "supplier": { "id": "5", "name": "Udea", "code": "6001397", "website_url": "https://www.udea.nl/search/?qry=6001397" },
      "edit_url": "/products/98f8a46e-…/edit", "match_rank": 3
    }
  ],
  "meta": {
    "query": "chocolatemakers fruit", "corrected_query": null,
    "total": 1, "page": 1, "per_page": 20, "last_page": 1,
    "stocked": true, "took_ms": 44.6
  }
}
```

`supplier` is `null` without a `supplier_link` row. `image_url` is the POS blob route when
the product has an image, else the supplier CDN URL (Udea by barcode; Independent by supplier
code via `supplier_image_cache`, template URL when uncached), else `null`. `match_rank` is
`null` for the browse listing.

## Matching and ranking

1. **Barcode mode** — whole query is ≥6 digits: `CODE = q OR REFERENCE = q OR CODE LIKE 'q%' OR CODE IN (barcodes whose supplier code = q)`.
2. **Token mode** — otherwise the query is lower-cased, split on whitespace, stripped to
   `[a-z0-9%.-]`, de-duplicated, max 6 tokens. Every token must match (`AND`), each against
   `NAME`, `CODE`, `REFERENCE` (`LIKE '%t%'`) or the barcodes whose `supplier_link.SupplierCode`
   contains it (`OR CODE IN (...)`, only for tokens ≥3 chars that match fewer than 500 links).
   `%`, `_` and `!` are escaped (`ESCAPE '!'`, which works on MySQL and SQLite).
3. **Ranking** (`match_rank`, then `NAME`): 0 exact code/reference · 1 name starts with the
   phrase · 2 name contains the phrase · 3 every token starts a word in the name · 4 anything else.
4. **Typo fallback** — if the tokenised search returns nothing, each token is corrected by
   `ProductSearchVocabulary::correct()` and the search reruns once; `meta.corrected_query`
   carries the corrected string and the UI shows "Showing results for …".

### Typo-correction rules (`ProductSearchVocabulary`)

- Vocabulary = every distinct word (≥3 chars, `[a-z0-9]`) across all `PRODUCTS.NAME`, with
  frequencies; built from one `pluck('NAME')` and cached for an hour under
  `product-search:vocab`. `ProductController@store` / `@update` call `forget()`.
- Tokens shorter than 4 chars or all digits are never corrected.
- A token that is a substring of any vocabulary word is a partial word, not a typo → no correction.
- Candidates must be within ±2 chars in length. Accepted distance: ≤1 for 4–6 char tokens,
  ≤2 for longer; an adjacent transposition (`friut`→`fruit`) counts as 1. Ties go to the
  more frequent word.

## Performance rules (measured on the live POS DB — read before touching the query)

`PRODUCTS.CODE` (`utf8_general_ci`) and `stocking.Barcode` / `supplier_link.Barcode`
(`latin1_swedish_ci`) have different collations, so a correlated comparison between them
cannot use an index. See the
[known-issues entry](../development/known-issues.md#pos-collation-mismatch-never-where-exists-against-stocking--supplier_link)
for the timing table (JOIN 30–40 ms vs `WHERE EXISTS` 20 s).

- Stocked filter is `join('stocking', 'stocking.Barcode', '=', 'PRODUCTS.CODE')`. Never `Product::stocked()`.
- Supplier filter and supplier-code matching pre-pluck barcodes from `supplier_link` and use `whereIn`.
- `supplier_link`, `suppliers`, `STOCKCURRENT`, `TAXES`, `CATEGORIES` are eager-loaded on the
  page of ≤50 results only, never joined into the main query.
- Explicit `select()` list plus a `CASE` expression for `has_image`; never `PRODUCTS.*` (the
  `IMAGE` mediumblob would be read for every row).
- The page is fetched before the `COUNT(*)`; when page 1 comes back short the total is
  already known and the count (~30 ms) is skipped.
- Image URLs are resolved in one batch per page (`supplier_image_cache` `whereIn`), never
  per row, and `resolveAndCacheImageUrl()` is never called from a search.

Typical server times: exact/barcode 35–50 ms, multi-word 45–95 ms, typo path 80–100 ms,
single character "a" ~130 ms, browse listing ~70 ms.

## Migrating a page to `x-product-search`

Eight pages still have their own typeaheads (customer requests migrated). For each:

1. Replace the page's search input + dropdown markup with `<x-product-search mode="picker" …>`.
2. Listen for `product-search:selected` on a parent element and map `$event.detail` (the
   JSON item above) onto whatever the page did with its old row (`code`, `name`, `price_sell`,
   `price_with_vat`, `supplier`, `stock_units` are all there).
3. Pass fixed filters as props (`:stocked="false"`, `:supplier-id`, `:exclude-ids`) instead
   of building a query string.
4. Delete the page's controller search method and route once nothing else calls it, and
   drop its `@push('scripts')` search code.
5. Check the picker still works with a barcode scanner (Enter commits the top match).

Call sites (view → current endpoint):

| View | Current endpoint |
|---|---|
| `resources/views/orders/partials/review-table.blade.php` | `orders.product-search` (`OrderController@searchProducts`) |
| `resources/views/customer-invoices/create.blade.php` | customer-invoice product search |
| `resources/views/kitchen/create.blade.php` | `kitchen.api.products.search` (`KitchenController@searchProducts`) |
| `resources/views/kitchen/edit.blade.php` | `kitchen.api.products.search` |
| kitchen ingredient profiles | `kitchen.api.profiles.products.search` (`KitchenIngredientProfileController@searchProducts`) |
| `resources/views/kds/products.blade.php` | `kds.products.search` (`KdsProductController@searchPos`) |
| fruit-veg manage / quick search | `fruit-veg.search`, `fruit-veg.quick-search` (`FruitVegController`) |
| `resources/views/fruit-veg/waste.blade.php` | `fruit-veg.waste.search` (`WasteController@search`) |

Out of scope until those migrations: `Product::scopeSearch()`, `Product::scopeStocked()` and
`ProductRepository::searchProducts()` stay as they are because those pages still call them.
