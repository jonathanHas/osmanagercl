# Shop mode cycle 22 — Guests see product pictures on the public requests board — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: d5aae2b9 — cycle 21 is committed; the tree is clean apart from this cycle.

Test baseline: 15 failed / 653 passed (2797 assertions).

## Pre-flight

- The board route really is public: `customer-requests.index` carries only `web`,
  no `auth`. So a new sibling route needs its own narrowing, which is the whole
  design of step 1.
- `CustomerRequest` has `closed_at` with `open()`/`closed()` scopes, so the
  "current request line" rule is expressible without a new column.
- **The plan's SQLite question, answered:** `SELECT md5('x')` on SQLite throws
  `no such function: md5`. Confirmed by running it. So the version cannot be
  computed in SQL portably.
- `FruitVegController::productImage()`'s versioned-header block is as described and
  is what I mirror, including the same `Cache-Control` on the 304.

**One decision the plan leaves me with a cost I am not willing to pay.** It says to
compute the version by plucking the blobs and hashing in PHP "for portability, the
blobs are few on a board". On dev the F&V photos run to 1 MB each (measured in
cycle 17d), and this would pull every board product's photo into PHP on every board
render — including the guest board, which auto-refreshes. Production's `pos`
connection is MySQL, which does have `MD5()`, so the hash is computed in SQL there
and only falls back to PHP where the driver cannot (tests, SQLite). Recorded under
Deviations.

## Steps

### 1. A public thumbnail route for request lines — done

Changed: `app/Http/Controllers/CustomerRequestController.php` (`photo()` plus a
private `codeIsOnACurrentRequest()`, and two constants), `routes/web.php`.

```
$ php artisan route:list --name=customer-requests.photo -v
GET|HEAD customer-requests/photo/{code} › CustomerRequestController@photo
    ⇂ web
    ⇂ Illuminate\Routing\Middleware\ThrottleRequests:120,1
```
Public, throttled, no `auth`. The authorisation is the 404 rule, which is why it
sits in its own named method with the window in a constant rather than inline.

One judgement the plan does not state: when `jpeg()` returns null — a blob the
encoder cannot read — the F&V route falls back to the full image. **This route must
not**, because it is public. It 404s instead, and the code says why.

### 2. The board resolves POS photos to the public route — done

Changed: `app/Services/ProductSearch/ProductSearchService.php` (an optional
`?callable $posPhotoUrl` second argument), `app/Services/CustomerRequestService.php`
(`imageUrlsForRequestLines()` and a private `photoVersions()`),
`app/Http/Controllers/CustomerRequestController.php` (three call sites moved to the
request service; it no longer needs `ProductSearchService` at all).

```
$ php artisan test --filter=ProductSearchImageUrlsTest
✓ a pos photo uses the callback when one is given
✓ the callback does not touch supplier pictures
✓ without a callback the behaviour is unchanged
  ... plus the 11 from cycle 21
Tests:    14 passed (24 assertions)
```
The middle one records the callback's contract by counting what it was called with:
supplier pictures already load for guests, so the callback must never see them. The
third exists because the search API passes nothing and must keep getting
`products.image`.

### 3. Docs — done

`docs/features/customer-requests.md` — the "guests see supplier pictures only"
limitation is replaced by the new route, its three narrowings and the version
scheme. `docs/design/shop-mode/README.md` and `docs/features/product-search.md`
record which resolver a page should use and the optional callback.

The one remaining `placeholder` in the customer-requests doc is about sourcing
lines, not a guest limitation.

### 4. Tests — done

Changed: `tests/Feature/Shop/ShopRequestsTest.php` — the fixture's photo is now a
real GD-generated PNG (the route 404s on a blob it cannot encode, so `'fake-jpeg-bytes'`
would have made every test pass for the wrong reason), a second product with no
photo, `Storage::fake('local')` so the thumbnail cache never touches the real disk,
and six tests for the route.

```
$ php artisan test --filter=ShopRequestsTest
✓ a guest can load a request line photo
✓ the photo route caches hard only with a matching version
✓ the photo route refuses a product that is not on a current request
✓ the photo route refuses a product with no photo
✓ the photo route refuses an unknown code
✓ the photo route is public and throttled but never serves the full photo
  ... 20 in all
Tests:    20 passed (165 assertions)
```
The last one asserts the route's own middleware — public, throttled — and that the
body is a 112 px JPEG and **not** the stored blob, because that is the property the
whole design rests on. The "not on a current request" test walks the window:
no request → 404, closed 40 days ago → 404, closed 5 days ago → 200, reopened → 200.

### 5. Format, build — done. `pint --test --dirty` → PASS; `npm run build` → built.

## Verification

**1. Route** — above: public, `throttle:120,1`.

**2. Tests**
```
$ php artisan test --filter="Shop|CustomerRequest|ProductSearch"
Tests:    267 passed (1325 assertions)
$ php artisan test
Tests:    15 failed, 662 passed (2832 assertions)
```
The identical set. 662 = 653 + 9.

**3. Diff scope** — `CustomerRequestController` (`photo()`, the helper, the call
sites), `CustomerRequestService` (the two new methods), `ProductSearchService` (the
optional parameter only). Design block `cmp` IDENTICAL; no stylesheet change.

**4. Manual — done with `curl`, no cookies at all**, which is a truer guest than a
private window because there is no session to fall back on.

The guest board now emits the new route where it emitted `products.image`:
```
4 × https://cdn.ekoplaza.nl/…            (supplier, unchanged)
1 × http://osmanager.local/customer-requests/photo/2015?v=a35e32be
```
and fetching that as a guest:
```
HTTP/1.1 200 OK
Cache-Control: immutable, max-age=604800, public
Content-Type: image/jpeg
ETag: "7c1b5232b8ea012088cee3fd6b045587"
image: 112x112 image/jpeg 3538 bytes
```
**The boundary, probed as a guest:**
```
000000000101      404   a product with a photo that nobody has asked for
2015              200   a product on a current request line
9999999999999     404   unknown code
../../etc/passwd  404   rejected by the route constraint
products.image    302 → /login   the office route is untouched
```

(Only one till photo is on the dev board now rather than cycle 21's two: the second
belonged to that cycle's throwaway request, which I deleted.)

**The browser extension disconnected** partway through, so the visual pass is
missing. I did not chase it: every claim above is from `curl` against the running
app, the staff path is pinned by `staff_board_shows_actions_and_form` asserting the
new URL, and nothing here is a layout change.

## Deviations

**One, and it is a cost the plan accepts that I do not.** The plan says to compute
the `?v=` versions by plucking the photo blobs and hashing them in PHP "for
portability, the blobs are few on a board". Product photos on dev run to a megabyte
(measured in cycle 17d), and the guest board auto-refreshes, so that would pull
every board product's photo into PHP on a timer.

`photoVersions()` therefore hashes in SQL — `MD5(IMAGE)` — when the connection's
driver is MySQL, which production's POS is, and falls back to the PHP path
otherwise. The plan's caution about SQLite was right and I confirmed it
(`no such function: md5`); tests take the fallback branch. Both branches are covered
by the tests above, since the assertions compare against a PHP-computed version.

## Files changed

```
 M app/Http/Controllers/CustomerRequestController.php
 M app/Services/CustomerRequestService.php
 M app/Services/ProductSearch/ProductSearchService.php
 M routes/web.php
 M tests/Feature/Shop/ShopRequestsTest.php
 M tests/Feature/ProductSearchImageUrlsTest.php
 M docs/features/customer-requests.md
 M docs/features/product-search.md
 M docs/design/shop-mode/README.md
```
The tree was clean at the start of this cycle apart from an editor swap file in
`docs/jons_docs/`, which is not mine. No dev data was created or changed.

**Not committed, not pushed, not deployed.**

## Notes for Planner

1. **What this route exposes, stated plainly for the record.** Anyone on the
   internet who can reach the board can fetch a 112 px JPEG of any product a
   customer has currently asked for. The board already shows those product names
   publicly, so the picture adds no new fact about what has been requested — it is
   the same information in another form. It reveals nothing about *who* asked. If
   that trade is ever reconsidered, the lever is the 404 rule in
   `codeIsOnACurrentRequest()`, which is deliberately one small method.

2. **The 30-day window is duplicated.** `CustomerRequestController::PHOTO_WINDOW_DAYS`
   and the "Done" view's 30 days in `CustomerRequestService::boardRows()` are the
   same number written twice. They should not drift — a photo served for a line the
   board no longer shows is a small leak, and one no longer served for a line the
   board does show is a placeholder. Worth a shared constant when someone is next in
   that file.

3. **The thumbnail cache is now written by a public, unauthenticated route.** A
   first request for each product encodes and writes a file; the throttle caps that
   at 120 a minute, and the keys are hashes of a code and a blob, so nothing can be
   made to write outside `fv-thumbs/`. Worth knowing that the folder can now grow
   from guest traffic, and that cycle 17g's prune covers it — the prune's "current
   photo" set is F&V products plus Jon's, which may **not** include a product that
   is only on a customer request. If a requested product is outside both sets, its
   thumbnail becomes an orphan the prune will delete, and the next guest request
   simply re-creates it. Harmless, but it means the prune and this route will churn
   a few files against each other. Worth adding request-line products to
   `ProductThumbnailService::currentPhotoProducts()` in a future tidy.
