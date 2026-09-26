# Shop mode cycle 21 — Product pictures on the customer requests screens — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: 58a48360 — cycle 20 is committed; the tree is clean apart from this cycle.

Test baseline: 15 failed / 640 passed (2762 assertions).

## Pre-flight

**The plan's third risk, checked first because it decides whether the feature works
for staff at all:**
```
$ php artisan route:list --name=products.image -v
GET|HEAD products/{id}/image › ProductController@image
    ⇂ Illuminate\Auth\Middleware\Authenticate
    ⇂ App\Http\Middleware\PermissionMiddleware:products.view

$ employee has products.view → true
```
So signed-in staff see POS photos on every screen. Guests do not — the route
redirects to login and the `<img>` errors onto the placeholder, which is the
behaviour the plan puts in Out of scope and which the component has to handle
cleanly.

The resolver is as described: `SELECT_COLUMNS` never includes `IMAGE`, `has_image`
is a raw CASE expression at line 155, and `imageUrl()` goes POS photo → supplier
CDN by barcode → cached/template supplier code → null.

## Steps

### 1. `imageUrlsByCode()` on the search service — done

Changed: `app/Services/ProductSearch/ProductSearchService.php` — a
`HAS_IMAGE_EXPRESSION` constant now shared by `runQuery()` and the new public
`imageUrlsByCode()`, which delegates to the existing private `loadSupplierImageCache()`
and `imageUrl()`. `imageUrl()`'s rules are untouched, so the search API is unaffected.

Changed: `tests/Feature/ProductSearchImageUrlsTest.php (new)` — 11 tests.
```
✓ a product with a pos photo gets the image route
✓ a product with no photo and no supplier gets null
✓ an empty blob is not a photo
✓ a supplier with a barcode template gets the cdn url
✓ a supplier that keys images by supplier code uses the template
✓ a disabled integration gets null
✓ a pos photo wins over a supplier picture
✓ an unknown code is absent rather than null
✓ an empty list runs no query
✓ duplicate codes are queried once
✓ the blob never leaves the database
Tests:    11 passed (19 assertions)
```
Four go beyond the plan's list and are the ones that pin the *rules* rather than the
plumbing: an empty blob is not a photo (the expression checks `LENGTH > 0`, not just
`NOT NULL`), a disabled integration yields null, a POS photo wins over a supplier
picture, and — the constraint the whole design rests on — **the query never selects
`PRODUCTS.*` or `IMAGE`**, asserted against the query log, because `IMAGE` is a
mediumblob and selecting it would pull every photo into PHP.

### 2. Board rows and the detail page carry the URL — done

Changed: `app/Services/CustomerRequestService.php` (a constructor taking
`ProductSearchService`; `rowsFrom()` enriches every row),
`app/Http/Controllers/CustomerRequestController.php` (`show()` passes `images`,
`seedItems()` attaches a `product` object through a new `seedProduct()` helper).

The enrichment went into `rowsFrom()` rather than `boardRows()` because it is the
single funnel both branches pass through, and only one branch runs — so it is
exactly one extra query per board however the board is filtered.

`seedProduct()` keys the object by `code`, not `id`: a request line stores a product
code, and `productImages()` falls back from `id` to `code` (cycle 17c), so the
Alpine thumbnail resolves it unchanged.

### 3. A server-side photo component and the three views — done

Changed: `resources/views/components/shop/photo.blade.php (new)`, the staff row,
the guest card, the detail page, and the two includes that pass `image` down.

**The plan's third risk, resolved first:** `products.image` is behind `auth` +
`permission:products.view`, and employees hold `products.view`. So staff see POS
photos everywhere; guests do not, and the component's error handler is what puts
the placeholder there. Recorded in the component's own docblock and in the docs.

Sourcing lines render neither photo nor placeholder, so their layout is byte-for-byte
what it was.

### 4. Tests — done

Changed: `tests/Feature/Shop/ShopRequestsTest.php`.

The fixture needed more than the plan's `IMAGE` column: it was also missing
`PRICEBUY`, `ISSERVICE` and `DISPLAY`, all of which are in `SELECT_COLUMNS`. Four
tests failed with `no such column: PRODUCTS.DISPLAY` until all four were added.

```
$ php artisan test --filter=ShopRequestsTest
✓ a sourcing line has no picture and no placeholder
✓ detail page shows the picture for a pre order line
✓ guest board is shop styled and read only        (+ photo assertions)
✓ staff board shows actions and form              (+ photo assertions)
✓ edit page seeds lines and posts to update       (+ seeded product assertion)
  ... 14 in all
Tests:    14 passed (135 assertions)
```

Two assertions I had to get right rather than merely green:

- **`assertDontSee('class="shop-thumb"')` for a sourcing line was wrong.** The New
  request sheet on the same page renders the Alpine thumbnail regardless, so that
  assertion could never have passed and, had I weakened it instead, would have
  proved nothing. The test now asserts on `shop-photo` — the server component's own
  wrapper — and then seeds a pre-order line and asserts the board *does* show one,
  so it cannot pass just because nothing ever emits it.
- **The edit-page seed goes through `@js()`**, i.e. `Illuminate\Support\Js::from()`,
  which hex-escapes quotes as `"` rather than `&quot;`. The expected fragment
  is built with the same helper instead of hand-written JSON.

### 5. Docs, format, build — done

`docs/features/customer-requests.md` (a "Product pictures" section including the
guest limitation), `docs/design/shop-mode/README.md` (`x-shop.photo` beside
`x-shop.product-thumb` and when to use which), `docs/features/product-search.md`
(the new public method). `pint --test --dirty` → PASS; `npm run build` → built.

## Verification

**1. Tests**
```
$ php artisan test --filter="Shop|CustomerRequest|ProductSearch"
Tests:    258 passed (1290 assertions)
$ php artisan test
Tests:    15 failed, 653 passed (2797 assertions)
```
The identical set. 653 = 640 + 13 (11 resolver tests + 2 new request tests).

**2. Diff scope**
```
 app/Http/Controllers/CustomerRequestController.php | 41 ++    show(), seedItems(), seedProduct()
 app/Services/CustomerRequestService.php            | 26 ++    constructor, rowsFrom()
 app/Services/ProductSearch/ProductSearchService.php| 48 ++    the constant and the new method
```
Nothing else in `app/`.

**3. Contract** — no `<script>`/`<style>` in shop views; design block `cmp`
IDENTICAL; no stylesheet change was needed.

**4. Manual, dev app, on a throwaway request with one pre-order line and one
sourcing line, deleted afterwards.**

*Staff board* — zoomed in to read it:
```
"ZZ sourcing line, no product"  no photo wrapper at all, title flush left as before
"Watered Down"                  the product's picture beside the title, 128×128 natural
```
*Detail page*: the pre-order line shows the picture, the sourcing line has no
wrapper.

*Edit page* — this is the cycle 14 gap, now closed:
```
items[0].product = { code: "4156", image_url: "http://…/products/001f8843…/image" }
items[1].product = null
1 thumbnail with a src, loaded
```
The seeded line shows its picture without the user re-picking the product.

*Guest board*, requested with no cookies at all rather than by signing anyone out:
```
6 shop-photo wrappers, 6 with x-on:error="ok = false"
srcs: 4 × https://cdn.ekoplaza.nl…   2 × http://osmanager.local/products/…/image
GET /products/{id}/image as a guest → 302 to /login
```
Exactly the documented behaviour: four of the six guest pictures are supplier CDN
URLs and load for anyone; the two POS photos redirect to login, error, and fall back
to the placeholder.

Twice the browser reported an image as not loaded when it had simply not been
fetched yet — the hidden-tab lazy-load artifact from cycle 17d. Both figures above
were taken after forcing a render.

*Dev data*: the throwaway request and its two lines deleted; `CustomerRequest::count()`
back to 8.

## Deviations

None. Two things the plan under-specified, both recorded in their steps: the POS
fixture needed `PRICEBUY`, `ISSERVICE` and `DISPLAY` as well as `IMAGE`, and the
enrichment sits in `rowsFrom()` rather than `boardRows()`.

## Files changed

```
 M app/Http/Controllers/CustomerRequestController.php
 M app/Services/CustomerRequestService.php
 M app/Services/ProductSearch/ProductSearchService.php
 M resources/views/shop/partials/request-row.blade.php
 M resources/views/shop/partials/request-card.blade.php
 M resources/views/shop/partials/requests-staff.blade.php
 M resources/views/shop/requests.blade.php
 M resources/views/shop/request-show.blade.php
 M tests/Feature/Shop/ShopRequestsTest.php
 M docs/features/customer-requests.md
 M docs/features/product-search.md
 M docs/design/shop-mode/README.md
?? resources/views/components/shop/photo.blade.php
?? tests/Feature/ProductSearchImageUrlsTest.php
```
The tree was clean at the start of this cycle apart from an editor swap file in
`docs/jons_docs/`, which is not mine.

**Not committed, not pushed, not deployed.**

## Notes for Planner

1. **Guests get placeholders for POS-photo products**, which is stated as out of
   scope but is now visible on the public board: two of six pictures on dev. The
   cheapest fix, if it is wanted, is not to open `products.image` to guests but to
   let the *board* serve the picture — the same `?w=`/`?v=` thumbnail route the F&V
   screens use (cycles 17d/17e) could take a guest-readable variant, since a
   112 px thumbnail of a product on a public board is not sensitive. That is a
   decision, not an implementation detail.

2. **`imageUrlsByCode()` now has three plausible callers that do not use it yet**:
   deliveries, order review and the label queue all hold product codes and show
   names without pictures. Nothing needs it, but it is the reason the method was
   made public rather than left private to the requests flow.

3. **The staff row wraps its title in a `shop-inline` only for pre-order lines**,
   so the two line types now have slightly different DOM. They look identical when
   there is no picture, but anything that later styles `.shop-req__title` by
   position in its parent will need to know.

4. **`ShopRequestsTest`'s POS fixture is now a full `SELECT_COLUMNS` table.** Any
   future test that exercises a page reaching `ProductSearchService` will need the
   same; the four columns I added are easy to miss because the failure is a raw
   `no such column` from deep inside a view render.
