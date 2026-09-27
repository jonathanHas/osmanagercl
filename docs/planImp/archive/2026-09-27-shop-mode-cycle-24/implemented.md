# Shop mode cycle 24 — Pictures on delivery rows — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: ea9db126. Test baseline: 15 failed / 664 passed.

## Pre-flight — the plan's main risk, measured before building on it

The plan warns that `versions()` hashes every photo product on every `items()` call
and says to add a 10-minute cache if the delta exceeds ~250 ms. I measured first, on
the real 164-line session (`supplierID=37`), so the decision is made from figures
rather than from caution.

**`items()` before any change**, three runs:
```
run 1: 29.9 ms   (cold)
run 2:  7.5 ms
run 3:  7.1 ms   rows=164
```

**What hashing would actually cost on that session:**
```
164 codes, of which just 1 has a POS photo — 69 kB of blobs in total
MD5() in SQL: 2.7 / 2.7 / 2.5 ms
```
Delivery products mostly have no till photo, so the realistic cost is ~2.5 ms.

**And the worst case I could construct** — 164 products that *all* have photos:
```
7 MB of blobs
MD5() in SQL:              20.5 / 19.3 / 17.5 ms
PHP fallback (pulls blobs): 31.8 ms
```

So even an all-photos delivery costs about **19 ms**, an order of magnitude under
the plan's threshold. **No cache is added**, and the report keeps the figures so the
decision can be revisited if the data changes. The plan's "100+ MB hashed per call"
does not occur: `MD5()` runs in the database and the blobs never cross the wire.

## Steps

### 1. Share the thumbnail plumbing — done

`ProductThumbnailService::versions()` now holds what was
`CustomerRequestService::photoVersions()`; the request service injects the
thumbnail service and calls it. `app/Http/Controllers/Concerns/ServesProductThumbnails.php (new)`
holds the response half of a thumbnail route — encode, refuse rather than fall back,
versioned cache-control, ETag/304 — and `CustomerRequestController::photo()` keeps
its gate and product lookup and calls the trait.

```
$ php artisan test --filter=ShopRequestsTest
Tests:    20 passed (165 assertions)
```
Green with **no assertion changed**, which is the check that the move was a move.

### 2. Shop photo route — done

`app/Http/Controllers/Shop/ProductPhotoController.php (new)`,
`app/Services/Shop/ProductImageUrls.php (new)`, and the route inside the Shop auth
group but **not** under a task permission — a screen that shows the product's name
may show its picture.
```
GET|HEAD shop/products/{code}/photo › Shop\ProductPhotoController@show
    ⇂ web
    ⇂ Illuminate\Auth\Middleware\Authenticate
```

### 3. `image_url` in the legacy JSON — done

One batched lookup in `items()` after both row loops; `scanIncrement()` resolves the
single product. Timing is in Verification 3.

### 4. Front end — done

`key(p)` → `p?.id ?? p?.barcode ?? p?.code ?? null`; both delivery modules composed
with `mix(productImages(), …)`; thumbs on rows, prompt, correction card and summary
rows; `.shop-item--pic` under APP ADDITIONS.

**I took the plan's fallback for the prompt deliberately rather than after a
failure.** `x-shop.product-thumb` puts `expr` inside `imageFailed(...)` as well as
`:src`, and `imageFailed(pending?.product)` is an optional chain in an argument
position Alpine evaluates as an assignment target. A `pendingProduct` getter avoids
the question entirely, so the view uses `expr="pendingProduct"`.

`shop-inline` in the design block already does `align-items: center`, so the
correction card needed no new rule — checked at `shop.css:141`.

### 5 & 6. Fixture and tests — done

Added `DISPLAY`, `ISSERVICE`, `IMAGE` to the delivery trait's `PRODUCTS`, gave `p1`
a real GD-generated PNG, and added seven tests.

Two things the fixture taught me:
- **A batch insert needs the same columns in every row.** Giving `p1` an `IMAGE`
  and leaving `p2` without one fails with "all VALUES must have the same number of
  terms"; `p2` gets an explicit null.
- **`supplierID` is validated as a string** by `incrementScanQuantity`, so the new
  scan test passes `'999'`. The same trap cost me a tinker call during pre-flight.

## Verification

**1. `php artisan test`**
```
Tests:    15 failed, 670 passed (2878 assertions)
```
The identical 15 (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting ×2,
Product ×2, TestScraper ×1). 670 = 664 + 6.

**A regression on the way, and the plan's context is where it came from.** The first
full run was **17** failed: `CustomerRequestDeliveryFlagTest` ×2, with
`no such column: IMAGE`. The plan says "only `ShopDeliveryTest` uses the trait",
which is true — but that test builds its **own** POS fixture and drives
`delivery-legacy.scan-increment`, which now resolves a picture. Three columns added
there too and it is back to 15. Worth noting that "who uses the fixture" and "who
calls the endpoint" are different questions.

**2. Contract**
```
design block cmp                                → IDENTICAL
<script|<style in shop views                    → 0
route( in delivery-scan.js / delivery-summary.js → 0, 0
ShopViewContractTest                             → 19 passed
```

**3. Timing — the plan's gate, measured before and after.**
```
items() for the 164-line session (supplierID=37), three runs each
before:  29.9 / 7.5 / 7.1 ms
after:   47.9 / 30.6 / 30.8 ms          delta ≈ +23 ms
```
Breaking the added time down:
```
versions()          2.3 ms     ← the hashing the plan worried about
imageUrlsByCode()  16.9 ms     ← the product query + supplier-cache batch
byCode() total     20.4 ms
```
**No cache added.** The delta is a tenth of the plan's 250 ms threshold, and the
risk was aimed at the wrong component: hashing is 2.3 ms because `MD5()` runs in the
database and the blobs never reach PHP. Even a constructed worst case — 164 products
that *all* have photos, 7 MB of blobs — hashes in **19 ms** (31.8 ms via the PHP
fallback). The plan's "100+ MB hashed per call" does not occur.

**4. Browser, the 164-line scan page.**
```
164 rows: 1 Shop thumbnail, 162 supplier CDN, 1 with nothing
img elements 164, broken 0, failed keys 0, placeholders shown 1
```
That breakdown matches the database exactly (one product with a till photo).

The Shop thumbnail: **4,203 bytes** against a **69,040-byte** stored blob — 16×
smaller — served `200 image/jpeg`, `immutable, max-age=604800, public`. In the page
it renders at natural 112×112, displayed 48×48.

- **Prompt:** scanning `5412533420791` opened the two-step card with the product's
  picture at its head.
- **Correction card:** tapping that row showed the picture beside the name, code and
  `Stock 1.52`.
- **Layout:** the three-column row does not squeeze the quantity; `Not scanned`
  pills and `0 / N` sit where they did.

**A CDN miss falls back, and I proved it rather than waiting for one.** I pointed a
row's `image_url` at an unresolvable host:
```
failed keys: ["7640166798880"]   ← keyed by barcode, the key() change
img hidden: true, placeholder shown: true
other rows unaffected
```

**5. Summary page:** three discrepancy rows — two CDN pictures loaded, the third has
no picture and shows the `package` placeholder with its `img` hidden.

**6. Customer requests:** the board still renders its pictures, one CDN and one
through `customer-requests/photo`. Its gate is intact, checked as a guest with no
cookies:
```
customer-requests/photo/2015           → 200   (on a current request line)
customer-requests/photo/000000000101   → 404   (has a photo, not requested)
customer-requests/photo/5412533420791  → 404   (the delivery product)
shop/products/5412533420791/photo      → 302 → /login   (the new route is not public)
```

**7. F&V waste:** 98 products, 95 with pictures, still resolving to
`/fruit-veg/product-image/…`, 0 failures — the `key()` change left it alone.

*Console:* clean on every page visited.

*Dev data:* nothing written. The scan prompt was cancelled, not committed.

## Deviations

None. Two choices the plan offers and I took the second of: `expr="pendingProduct"`
over `expr="pending?.product"` (reason in step 4), and `shop-inline` for the
correction card, which the design already centres.

## Files changed

```
 M app/Http/Controllers/CustomerRequestController.php      photo() now calls the trait
 M app/Http/Controllers/DeliveryLegacyController.php       image_url on items + scan
 M app/Services/CustomerRequestService.php                 delegates versions()
 M app/Services/ProductThumbnailService.php                versions()
 M public/…                                               (none)
 M resources/css/shop.css                                  .shop-item--pic
 M resources/js/shop/delivery-scan.js
 M resources/js/shop/delivery-summary.js
 M resources/js/shop/product-images.js                     key() adds barcode
 M resources/views/shop/delivery-scan.blade.php
 M resources/views/shop/delivery-summary.blade.php
 M routes/web.php
 M tests/Concerns/CreatesLegacyDeliveryPosTables.php
 M tests/Feature/CustomerRequestDeliveryFlagTest.php        fixture columns
 M tests/Feature/Shop/ShopDeliveryTest.php
?? app/Http/Controllers/Concerns/ServesProductThumbnails.php
?? app/Http/Controllers/Shop/ProductPhotoController.php
?? app/Services/Shop/ProductImageUrls.php
```

**Not committed, not pushed, not deployed.**

## Notes for Planner

1. **The scan prompt shows a raw float where every other place is formatted.** Its
   "In stock" fact reads **1.5200000000000011**; the correction card immediately
   below reads `Stock 1.52`, because rows go through `stockText()` and the prompt's
   `pending.product.currentStock` does not. It predates this cycle and the plan puts
   layout changes out of scope, so I left it — but it is now side by side with a
   correctly formatted figure and looks like a fault in the new work. A one-line fix
   whenever that card is next open.

2. **I nearly reported a bug that did not exist, and the reason is worth recording.**
   My first browser measurement said 57 rows pointed at the Shop photo route while
   only one product had a photo — which would have meant 56 wasted 404s. The cause
   was my own classifier: the supplier CDN serves from
   `iihealthfoods.com/cdn/shop/products/…`, which contains the substring I was
   matching on. Classifying by origin gives 1/162/1, matching the database. Anything
   that later distinguishes "our" image URLs from suppliers' should compare origins,
   not paths.

3. **Only 1 of 164 products on a real delivery has a till photo**, and 162 have a
   supplier picture. So this cycle's visible benefit on dev comes almost entirely
   from the CDN, and the Shop thumbnail route earns its keep on a handful of rows —
   though on a delivery of own-brand or F&V stock the proportions would reverse.
   Worth knowing before judging the feature by what dev shows.

4. **The weekly prune still deletes delivery thumbnails.** `currentPhotoProducts()`
   is F&V plus Jon's produce, so a delivery product's thumbnail is removed each
   Sunday and re-encoded on the next view — one encode per product per week, which
   is what the plan accepts. It also means the prune and this route churn a few
   files against each other, as cycle 22's note said of the requests route. Adding
   "products on a recent delivery" to the prune's set would settle both, and is the
   same one-method change.
