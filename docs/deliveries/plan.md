# Cycle 1 — Link an unknown outer barcode from the Shop scan screen

Status: READY
Revision: 2
Planner: Fable 5.1
Date: 2026-10-08 (revision 2: 2026-10-08, after the owner's browser check)

## Goal

On the office match page (`/delivery-legacy/match?delID=…`) a scan that
matches no product offers "Assign as outer barcode →": the person scans the
unit barcode on one item from the case, the outer code is saved on the
supplier link, and the outer code is re-scanned so the delivery is counted
by the case. The Shop scan screen (`/shop/deliveries/scan?delID=…`) only says
"Product not found" and moves on, so the case cannot be counted and the
outer code is never learned. This cycle brings the same flow to the Shop
screen: a **Not found** card with **Link as outer barcode**, then one more
scan (or a pick from Find by name) to link it, then the case prompt opens.
No new endpoint: the Shop page posts the legacy `save-outer-barcode` route
the office page already uses. From the owner's `todo.txt`, 2026-10-08.

## Context

What the Implementer needs, all verified against the code on 2026-10-08:

- **The legacy flow** is in `resources/views/delivery-legacy/match.blade.php`:
  the unknown result and its button at :318–329, the assign step markup at
  :262–311, the JS `startOuterAssign()` / `cancelOuterAssign()` /
  `submitOuterAssign()` at :3083–3192, and the camera handler at :3270 that
  routes a scan to the assign step while `scanner.step === 'assign-outer'`.
  On success it waits 1.5 s, then re-runs the outer code through
  `lookupBarcode()` so the quantity prompt opens as a case. On failure the
  message is shown and the step stays open. Read it once; do not copy its
  markup, which is office Tailwind, not Shop.
- **The endpoint**: `DeliveryLegacyController::saveOuterBarcode()` at
  `app/Http/Controllers/DeliveryLegacyController.php:1493`, route
  `delivery-legacy.save-outer-barcode` (`routes/web.php:735`, POST, inside the
  `permission:deliveries.process` group that every other Shop delivery
  endpoint is in). Body `{ unitBarcode, supplierID, outerCode }`, all strings.
  It normalises a GS1-128 `outerCode` to the GTIN-14 in AI (01)
  (`parseGS1OuterCode()` :1553), 404s with `{success:false, message:'Product
  not found for this supplier.'}` when the unit barcode has no `supplier_link`
  row for that supplier, 409s with `{success:false, message:'This outer
  barcode is already assigned to: NAME (CODE)'}` when another product of the
  same supplier has that outer code, else updates `supplier_link.OuterCode`
  and returns `{success:true, productName, unitBarcode}`. It is used as is;
  nothing server-side changes in this cycle.
- **How a scan is resolved**: `incrementScanQuantity()` :1326 looks the code
  up as a unit barcode (`PRODUCTS.CODE`), then as `supplier_link.OuterCode`
  for the supplier; an outer match returns `scanType: 'case'`,
  `caseUnits: max(1, CaseUnits)` and `product.barcode` = the unit barcode.
  With `quantity: 0` nothing is written. An unknown code returns
  `{success:true, product:null}`; that is the case the new card handles.
  (`success:false` is a validation failure and keeps today's behaviour.)
- **The Shop page**: `resources/views/shop/delivery-scan.blade.php` and
  `resources/js/shop/delivery-scan.js`. Endpoint URLs reach the JS as
  `data-*` attributes on `<main>` (`itemsUrl`, `scanUrl`, `updateUrl`
  getters read `this.$root.dataset`). `onScan(code)` → `lookup(code, false)`;
  `pickResult(p)` (Find by name) → `lookup(p.code, true)`. The not-found
  branch of `lookup()` calls `announceError()` then `announceSaved()` (the
  camera resumes). The prompt card is the first section in the left column
  (`x-ref="prompt"`), above the scan field, because on a phone the camera
  block pushes anything under the field off screen (cycle 33); the new card
  goes in the same place, after the prompt.
- **One scan field per page** (`scan-input.js` `capture()`): the unit barcode
  for the link is taken from the same field, routed by page state, whether it
  comes from the camera, a hand scanner, or typing. No second input.
- **Camera rule**: `shop-scan-saved` resumes a paused camera and is dispatched
  only when the page has finished with a scan, never while a prompt is open.
  `tests/Feature/Shop/ShopDeliveryTest.php::test_the_delivery_scan_page_reopens_the_camera_but_not_while_the_prompt_is_open`
  (:880) asserts exactly 3 occurrences of `this.announceSaved();` and that
  none sits between `this.pending = {` and the next `this.announceDone();`.
  This cycle adds one occurrence (a failed link keeps the card open, so the
  camera must come back), so the count becomes 4 and the comment lists it.
- **PIN sessions**: `config/shop.php` `pin_session_routes` (:59) lists
  `delivery-legacy.items`, `scan-increment`, `update-quantity`, `complete`,
  `create-session`, not `save-outer-barcode`. The drift test in
  `tests/Feature/Shop/ConfinePinSessionTest.php` (:140–172) greps every
  `route('…')` in `resources/views/shop/**` and fails on a name not
  allow-listed, so step 1 must land with step 2.
- **Test fixture**: `tests/Concerns/CreatesLegacyDeliveryPosTables.php`.
  Supplier `999` "Hof Linde"; p1 "Oat drink 1 L" `5000000000017`
  (`CaseUnits` 6, `OuterCode` `15000000000014`); p2 "Leeks" `5000000000024`
  (`CaseUnits` 1, no outer); session `d-1`; scan `4260009912200` × 3 has no
  product and no supplier link. `ShopDeliveryTest` has `employee()`
  (`deliveries.process` only), `userWith()`, `scanUrl()`, `scanJs()`.
  47 tests pass today.
- **Design**: no unknown-barcode state is drawn in
  `docs/design/shop-mode/screen-05-delivery-scan-v2.html`. The card below is
  built from the prompt card's own parts (`shop-card`, `shop-between`,
  `shop-subtitle`, `shop-code`, `shop-meta`, `shop-notice`,
  `shop-btn--primary shop-btn--block`, `shop-iconbtn--ghost`, icons `x`,
  `alert`, `package`), all present in the design CSS block. No CSS change.
- **Dev host**: `osmanager.local` has no camera (plain HTTP); typing a code
  into the scan field and pressing Enter fires the same `scan` event. JS is
  served from the git-ignored `public/build` (`npm run build`). Open dev
  sessions and candidate products are in `README.md` "Checking a change".

## Constraints

- Shop rules in `docs/shop_new/README.md` (view contract, design CSS
  verbatim, PIN allow-list, Alpine traps, `mix()`), and the delivery rules in
  this folder's `README.md`.
- `supplier_link.OuterCode` is written only through
  `delivery-legacy.save-outer-barcode`. No new write path, no server change.
- The office match page keeps working unchanged: nothing in
  `DeliveryLegacyController` or `match.blade.php` is edited.
- A code that resolves (unit or outer) behaves exactly as today. The card
  appears only for `success:true, product:null`.
- The camera is never resumed while a quantity prompt is open.
- Do not commit, push or deploy. The owner commits.

## Out of scope

- Editing `CaseUnits` from the Shop screen (a newly linked outer on a link
  whose `CaseUnits` is 0 or 1 opens a "Case of 1" prompt; the office page has
  `updateCaseUnits()`; owner to decide later, noted in `README.md`).
- Changing or clearing an outer code that is already linked (office page).
- Any change to the legacy match page, the `/deliveries` flow, or the design
  files under `docs/design/`.
- A confirmation step before linking: the legacy page has none, and the
  card's Dismiss button is the way out.

## Steps

### 1. Allow the endpoint for PIN sessions
Files: `config/shop.php`
What: in `pin_session_routes`, add `'delivery-legacy.save-outer-barcode',`
after `'delivery-legacy.create-session',`.
Check: `php artisan tinker --execute="var_dump(in_array('delivery-legacy.save-outer-barcode', config('shop.pin_session_routes')));"`
prints `bool(true)`.

### 2. The Not found card on the scan screen
Files: `resources/views/shop/delivery-scan.blade.php`
What:
- On `<main>`, add `data-outer-url="{{ route('delivery-legacy.save-outer-barcode') }}"`
  after `data-update-url`.
- Insert this section directly after the prompt `</section>` (the one with
  `x-ref="prompt"`) and before the `@if ($session['completed'])` block:

```blade
{{-- A code no product has, and the step that links it to a product as its
     outer (case) barcode, as the office match page offers. Above the scan
     field for the same reason the prompt is: on a phone the camera block
     pushes anything under the field off screen. --}}
<section class="shop-card" x-show="unknown" x-cloak x-ref="unknown">
    <div class="shop-between">
        <div class="shop-stack shop-stack--tight">
            <h2 class="shop-subtitle" x-text="unknown?.linking ? 'Link outer barcode' : 'Not found'"></h2>
            <span class="shop-row__meta shop-code" x-text="unknown?.code"></span>
        </div>
        <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Dismiss" @click="dismissUnknown()">
            <x-shop.icon name="x" />
        </button>
    </div>

    <p class="shop-meta" x-show="! unknown?.linking">
        No {{ $session['supplier'] ?? 'supplier' }} product has this barcode. If it is the barcode on a case, link it to the product inside.
    </p>
    <p class="shop-meta" x-show="unknown?.linking" x-cloak>
        Now scan the barcode on one item from the case{{ $canSearch ? ', or find it by name' : '' }}.
    </p>

    <p class="shop-notice" x-show="unknown?.error" x-cloak>
        <x-shop.icon name="alert" size="sm" /><span x-text="unknown?.error"></span>
    </p>

    <button class="shop-btn shop-btn--primary shop-btn--block" type="button" x-show="! unknown?.linking" :disabled="busy" @click="startLink()">
        <x-shop.icon name="package" />Link as outer barcode
    </button>
</section>
```

Only `x-shop.*` components and `shop-*` classes (view contract). `?.`
everywhere `unknown` is read: `x-show` hides the section but still
evaluates the bindings inside it (Shop rule 5).
Check: `php artisan test tests/Feature/Shop/ShopViewContractTest.php tests/Feature/Shop/ConfinePinSessionTest.php`
passes, and `GET` of the scan page as an employee contains
`data-outer-url="…/delivery-legacy/save-outer-barcode"` and
`Link as outer barcode` (the new test in step 4 pins both).

### 3. Page state and the link flow
Files: `resources/js/shop/delivery-scan.js`
What:
- State: add `unknown: null,` with a comment: an unknown code as
  `{ code, linking, error }`; `linking` is true once "Link as outer barcode"
  was tapped, and then the next code scanned (or product picked by name) is
  the unit barcode to link it to.
- Getter `outerUrl` reading `this.$root.dataset.outerUrl`, beside `updateUrl`.
- `onScan(code)`: when `this.unknown?.linking`, return `this.linkOuter(code)`;
  otherwise `this.lookup(code, false)` as now.
- `pickResult(p)`: when `this.unknown?.linking`, return `this.linkOuter(p.code)`;
  otherwise as now.
- `lookup()`: at the top, after the dropped/commit handling and beside
  `this.editing = null`, set `this.unknown = null` (a scan is a new subject).
  In the not-found branch, only when `data.success && ! data.product`, set
  `this.unknown = { code, linking: false, error: null }` and
  `this.$nextTick(() => this.$refs.unknown?.scrollIntoView({ block: 'nearest' }))`
  before the existing `announceError()` / `announceSaved()` calls, which stay
  (the camera comes back so the next scan can be the unit barcode). A
  `! data.success` answer keeps today's behaviour with no card.
- New methods, next to `cancelPending()`:

```js
/** "Link as outer barcode": the next code is the unit barcode. */
startLink() {
    if (! this.unknown) {
        return;
    }

    this.unknown.linking = true;
    this.unknown.error = null;
    this.editing = null;
    // Clears "Product not found" under the field and hands focus back to it,
    // so a hand scanner or a typed unit barcode lands there.
    this.announceDone();
},

dismissUnknown() {
    this.unknown = null;
    this.announceDone();
},

/**
 * Link the unknown code to the product whose unit barcode was just scanned,
 * through the office page's own endpoint. On success the outer code now
 * resolves, so it is looked up again and opens a case prompt — the scan the
 * person made a moment ago, completed. The card stays open on a failure,
 * with the server's message, for another try.
 */
async linkOuter(code) {
    const outer = this.unknown?.code;

    if (! outer || this.busy) {
        return;
    }

    let failure = null;

    if (code === outer) {
        failure = 'That is the outer barcode again. Scan the barcode on one item from the case.';
    } else {
        this.busy = true;
        this.unknown.error = null;

        try {
            const data = await this.post(this.outerUrl, {
                unitBarcode: code,
                supplierID: this.supplierId,
                outerCode: outer,
            });

            if (! data.success) {
                failure = data.message || 'Could not link, try again';
            }
        } catch (e) {
            failure = 'Could not link, try again';
        } finally {
            this.busy = false;
        }
    }

    if (failure !== null) {
        this.unknown.error = failure;
        // The card stays open for another scan, so give the camera back.
        this.announceSaved();

        return;
    }

    this.showToast('ok', `Linked to ${data.productName}`);
    this.unknown = null;

    if (this.manual) {
        this.resetManual();
    }

    await this.lookup(outer, false);
},
```

  (`data` must be declared outside the `try` for the toast; the Implementer
  owns the final shape.) `lookup(outer)` opens the prompt itself and does not
  resume the camera, as the camera rule requires.
- Update the file's header comment: one sentence under the scan paragraph
  saying an unknown code opens a Not found card that can link it as an outer
  barcode through `delivery-legacy.save-outer-barcode`, after which the code
  is looked up again and opens a case prompt.
Check: `grep -c 'this.announceSaved();' resources/js/shop/delivery-scan.js`
prints `4`; `npm run build` succeeds.

### 4. Tests
Files: `tests/Feature/Shop/ShopDeliveryTest.php`
What:
- In `test_the_delivery_scan_page_reopens_the_camera_but_not_while_the_prompt_is_open`
  change the expected count from 3 to 4 and the comment to list the four:
  a committed add, a cancelled prompt, a code that is not in the delivery, a
  failed outer-barcode link (the card stays open for another try). The second
  assertion (no `announceSaved` between the prompt being built and its
  `announceDone()`) stays as it is.
- Add, under a `// --- deliveries cycle 1: link an outer barcode from the scan screen ---`
  marker, these tests:
  1. `test_scan_screen_offers_to_link_an_unknown_outer_barcode`: GET the scan
     page as `employee()`; assert it contains
     `data-outer-url="`.e(route('delivery-legacy.save-outer-barcode')).`"`,
     `Link as outer barcode`, `startLink()`, `dismissUnknown()`, and that
     `x-ref="unknown"` comes before `shop-scan__input` (same `strpos` shape as
     `test_the_quantity_prompt_comes_before_the_scan_field`). Also assert
     `scanJs()` contains `this.post(this.outerUrl` and `outer barcode again`.
  2. `test_linking_an_outer_barcode_makes_the_next_scan_a_case`: set p2's
     `CaseUnits` to 4 in `supplier_link`; as `employee()` `postJson` the
     save-outer-barcode route with `unitBarcode '5000000000024'`,
     `supplierID '999'`, `outerCode '15000000000021'` → `assertOk`, json
     `success true`, `productName 'Leeks'`, `unitBarcode '5000000000024'`;
     `supplier_link.OuterCode` for that row is now `15000000000021`. Then
     `postJson` scan-increment with `barcode '15000000000021'`, `quantity 0`
     → `product.name 'Leeks'`, `product.barcode '5000000000024'`,
     `scanType 'case'`, `caseUnits 4`; `deliveriesScanItems` count is still 3
     (the lookup recorded nothing).
  3. `test_an_outer_barcode_already_on_another_product_is_refused`:
     `outerCode '15000000000014'` (p1's) for unit `5000000000024` → status
     409, `success false`, message contains `Oat drink 1 L`; p2's `OuterCode`
     is still null and p1's unchanged.
  4. `test_a_unit_barcode_the_supplier_does_not_have_is_refused`:
     `unitBarcode '4260009912200'` → status 404, `success false`.
  5. `test_a_gs1_outer_code_is_stored_as_its_gtin`: `outerCode`
     `"]C10115000000000021\x1D10LOT42"` (PHP double quotes) for unit
     `5000000000024` → `assertOk`, stored `OuterCode` is `15000000000021`.
  6. `test_a_barista_cannot_link_an_outer_barcode`: `userWith('barista',
     ['kds.access'])` posting the route → `assertForbidden`.
Check: `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` → 53 passed.

### 5. The Shop controller's docblock
Files: `app/Http/Controllers/Shop/DeliveryController.php`
What: the class docblock says "Everything else about a delivery — financials,
case units, outer barcodes, translations, completion — stays on the office
match page." Remove "outer barcodes" from that list and add a sentence: the
scan screen can link an unknown code as a product's outer barcode through
the office page's `saveOuterBarcode()` (deliveries cycle 1, 2026-10-08).
Check: `./vendor/bin/pint --test app/Http/Controllers/Shop/DeliveryController.php` clean.

### 6. Feature documentation
Files: `docs/features/shop-mode.md`
What: after the section "Deliveries: items without a barcode" (ends before
"### Orders: list and order review", :147) add
"### Deliveries: unknown barcodes and outer codes": a scan no product has
opens a **Not found** card with the code; **Link as outer barcode** makes
the next scan (or a pick from Find by name) the unit barcode of the product
inside the case; the link is saved on `supplier_link.OuterCode` through the
office page's `delivery-legacy.save-outer-barcode` (GS1-128 codes are stored
as their GTIN-14; an outer code already on another product of the supplier,
or a unit barcode the supplier does not carry, is refused with the message on
the card); on success the outer code is looked up again and opens a case
prompt. Mention that case units are not editable from the Shop screen and
that a link with `CaseUnits` 0 or 1 gives a "Case of 1" prompt. Keep it to
two short paragraphs in the style of the surrounding sections.
Check: the heading appears once in the file (`grep -c "unknown barcodes and outer codes" docs/features/shop-mode.md` → 1).

### 7. Format, build, browser check
Files: none new.
What:
- `./vendor/bin/pint` on the changed PHP files; `npm run build`.
- Browser, dev host, signed in as `test`: open
  `/shop/deliveries/scan?delID=4149c0a2-ae65-49ec-9c00-8da5a8124cef&supplierID=5`.
  Pick a product to link with
  `php artisan tinker --execute="print_r(DB::connection('pos')->table('supplier_link')->join('PRODUCTS','PRODUCTS.CODE','=','supplier_link.Barcode')->where('SupplierID',5)->where('stocked',1)->where('CaseUnits','>',1)->whereNull('OuterCode')->limit(3)->get(['supplier_link.ID','Barcode','NAME','CaseUnits'])->all());"`
  and note its `ID` and `Barcode`.
  1. Type `15000000000021` into the scan field, Enter: the Not found card
     shows that code; nothing is added to the list.
  2. Tap **Link as outer barcode**: the title becomes "Link outer barcode".
  3. Type `15000000000021` again, Enter: the card shows "That is the outer
     barcode again…" and stays open.
  4. Type a barcode of a product from another supplier (any `PRODUCTS.CODE`
     with no `supplier_link` row for supplier 5), Enter: the card shows
     "Product not found for this supplier." and stays open.
  5. Type the chosen product's `Barcode`, Enter: toast "Linked to <name>",
     the card closes, the prompt opens with "Outer · Case of N" and the
     button reads "Add 1 case · N units". **Cancel the prompt** (the × on the
     prompt); the list is unchanged.
  6. Type `15000000000021` once more, Enter: the case prompt opens straight
     away. Cancel it.
  Watch the console throughout; record what was real input.
- Put the dev data back:
  `php artisan tinker --execute="DB::connection('pos')->table('supplier_link')->where('ID', <id>)->update(['OuterCode' => null]);"`
  and confirm `deliveriesScanItems` for that session has the same row count
  as before the check. List both under "Dev state" in the report.
Check: each numbered observation recorded in `implemented.md`, with the
console clean of errors.

## Verification

1. `./vendor/bin/pint --test` → no files need formatting.
2. `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` → 53 passed.
3. `php artisan test tests/Feature/Shop` → all pass (includes the PIN drift
   test and the view contract).
4. `php artisan test` → **15 failed, 1036 passed**: the same 15 as the
   baseline in `README.md` (`UdeaScrapingServiceTest` ×7,
   `CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2,
   `ProductTest` ×2, `TestScraperControllerTest` ×1) and nothing else.
5. `npm run build` → succeeds.
6. The browser check in step 7, with dev data put back.

## Risks

- **A wrong second scan links the wrong product.** Someone taps Link and
  then scans the next delivery item instead of the unit inside the case.
  The legacy page has the same exposure; the card's title and text, and the
  Dismiss button, are the guard. A wrong link is corrected on the office
  page (out of scope here). If the owner finds this happens, a confirm step
  showing the product before saving is a small follow-up.
- **Camera re-reads the outer code while linking.** The resumed camera
  ignores the last code for about 3.5 s; if the case is still under the lens
  after that, the "outer barcode again" message shows and the camera resumes
  again. Harmless, and it tells the person what to do. Cannot be checked on
  dev; the owner sees it on production.
- **"Case of 1".** `supplier_link.CaseUnits` is 0 or 1 on many links
  (Mossfield's are 0). The endpoint uses `max(1, CaseUnits)`, so the prompt
  reads "Case of 1" and Add adds 1 unit. Noted as an open item in
  `README.md`; not changed here.
- **The `announceSaved` count test** fails if the Implementer adds or
  removes a resume; the count in step 4 is 4, and the comment must match.
- **PIN drift test** fails if step 1 is skipped or misspelled.

## Review of revision 1 (2026-10-08)

Read `implemented.md` to the end and the full `git diff` of the six files;
reran `php artisan test tests/Feature/Shop` (322 passed), pint on the changed
PHP files (clean) and the `announceSaved` count (4).

**Steps**

| Step | Result |
|---|---|
| 1 PIN allow-list | pass: one line in `config/shop.php`, drift test passes |
| 2 Card markup | pass: as the snippet, `?.` on every read, contract test passes, card before the scan field |
| 3 JS flow | pass: `unknown` state, `onScan` / `pickResult` routing, `lookup()` clears and sets the card only on `success:true`, `linkOuter()` with `data` outside the `try`; header comment updated |
| 4 Tests | pass: count 3 → 4 with the comment, six new tests as named, 53 pass |
| 5 Docblock | pass |
| 6 Feature doc | pass: accurate and in the style of the neighbours |
| 7 Browser check | **done by the owner**, not the Implementer (the Chrome extension was not connected in that session). The Node harness evidence is accepted for the client flow; the owner's run is the browser check, and it found the issue revision 2 fixes |

**Deviations**: 1 (harness instead of browser) accepted, see step 7. 2 (guard
`this.unknown` before writing the failure) accepted: correct, the plan's
snippet would throw after Dismiss. 3 (route in the header list) accepted.
4 (Implementer ran on Fable) accepted; no effect.

**Notes for Planner**: repo-wide `pint --test` is not clean at baseline (two
committed files outside this cycle) → fixed now: Verification 1 reads "on the
changed files". Harness observation on the camera re-reading the case →
noted, matches the plan's Risk. `lookup()` clearing the card on any scan →
intended. `pickResult()` while linking → fine as is. A JS test runner →
deferred: the project has none and adding one is an owner decision; listed in
`README.md` open items.

**Owner's finding (browser check, 2026-10-08)**: after tapping "Link as outer
barcode", the screen looks the same as ordinary scanning. Someone who tapped
it by accident, or forgot they had, scans the next delivery item and links it
as the unit of that outer code without noticing. Revision 1 saves on that
single scan, so the mistake is silent and can only be undone on the office
page. Correct, and not covered by the plan's Risks beyond "the card is the
guard". Revision 2 below adds a confirmation naming the product and makes the
linking state look unlike scanning. Status back to READY; steps 1–7 are not
redone.

## Revision 2 — steps

### 8. Confirm before linking
Files: `resources/js/shop/delivery-scan.js`
What: a scan while linking no longer saves. It looks the code up, shows the
product it found, and saves only on a tap.
- `unknown` becomes `{ code, linking, candidate, error }`; `candidate` is
  null or `{ code, product }` where `product` is the scan endpoint's product
  object (name, barcode, image_url, …) for the unit barcode just scanned.
  Update the state comment. Add getter `candidateProduct` returning
  `this.unknown?.candidate?.product ?? null` (the thumb component cannot take
  an optional chain, see `pendingProduct`).
- Replace `linkOuter(code)` with three methods and a helper:
  - `findUnit(code)`: the scan half. Guards `! outer || this.busy`. Same-code
    → `backToLinking('That is the outer barcode again. Scan the barcode on one item from the case.')`.
    Otherwise `busy`, POST `this.scanUrl` with `{ delID, barcode: code, quantity: 0, supplierID }`
    (records nothing). If the card was dismissed meanwhile (`! this.unknown`)
    return quietly. `! data.success || ! data.product` →
    `backToLinking(`No product has barcode ${code}. Scan the barcode on one item from the case.`)`.
    `data.scanType === 'case'` →
    `backToLinking(`${code} is already the case barcode of ${data.product.name}. Scan the barcode on one item.`)`.
    Else set `this.unknown.candidate = { code: data.product.barcode, product: data.product }`,
    `this.unknown.error = null`, `announceDone()`, scroll `$refs.unknown` into
    view. **No `announceSaved()` here**: the item is still under the lens, the
    same rule as the prompt. Network failure →
    `backToLinking('Could not look that up, try again')`.
  - `confirmLink()`: needs `this.unknown?.candidate` and not `busy`. POST
    `this.outerUrl` with `{ unitBarcode: candidate.code, supplierID, outerCode }`.
    Success → toast `Linked to ${data.productName}`, `unknown = null`,
    `resetManual()` if `manual`, `await this.lookup(outer, false)` (opens the
    case prompt, no resume). Failure or network error →
    `backToLinking(data?.message || 'Could not link, try again')`.
  - `rejectCandidate()` ("Not this one") → `backToLinking(null)`.
  - `backToLinking(message)`: if `this.unknown`: `candidate = null`,
    `linking = true`, `error = message`; then `this.announceSaved();` — the
    one camera resume of the link flow, so the person can scan again.
- `onScan()` and `pickResult()` route to `findUnit()` while
  `this.unknown?.linking` (they routed to `linkOuter()`).
- `dismissUnknown()`: `const paused = !! this.unknown?.candidate;` then
  `unknown = null`, `announceDone()`, and `if (paused) { this.announceSaved(); }`
  with a comment: the camera was left paused when the candidate was shown.
- Header comment: the next scan shows the product it found and a tap links it.
Check: `grep -c 'this.announceSaved();' resources/js/shop/delivery-scan.js` → `5`;
`grep -c 'this.post(this.outerUrl' resources/js/shop/delivery-scan.js` → `1`;
`npm run build` succeeds.

### 9. The card in three states, and the scan field coloured while linking
Files: `resources/views/shop/delivery-scan.blade.php`, `resources/css/shop.css`
(APP ADDITIONS block only)
What: replace the card's body so each state is unmistakable:
- On the `<section>`: `:class="{ 'shop-card--linking': unknown?.linking }"`.
- Title: `! unknown?.linking` → "Not found"; linking without a candidate →
  "Link outer barcode"; with a candidate → "Link to this product?".
  `x-text="! unknown?.linking ? 'Not found' : (unknown?.candidate ? 'Link to this product?' : 'Link outer barcode')"`.
  The code line under it stays.
- **Not found** (as now): the sentence and the primary **Link as outer
  barcode** button.
- **Waiting** (`unknown?.linking && ! unknown?.candidate`): `shop-meta` text
  "Scan the barcode on one item from the case — not the next delivery item.
  Nothing is counted until you confirm.{{ $canSearch ? ' Or find it by name.' : '' }}"
  and a block ghost button **Cancel linking** (`x-shop.icon name="x" size="sm"`)
  → `dismissUnknown()`.
- **Candidate** (`unknown?.candidate`): a `shop-inline` row with
  `<x-shop.product-thumb expr="candidateProduct" />`, the product name
  (`shop-row__title`, `x-text="candidateProduct?.name"`) and its code
  (`shop-row__meta shop-code`, `x-text="candidateProduct?.barcode"`); a
  `shop-meta` line `x-text="unknown ? unknown.code + ' becomes the case barcode of this product.' : ''"`;
  a primary block button **Yes, link it** (`x-shop.icon name="check"`) →
  `confirmLink()`, `:disabled="busy"`; a ghost block button **Not this one** →
  `rejectCandidate()`.
- The error notice stays, shown in any state when `unknown?.error`.
- Wrap the `<x-shop.scan-input … />` line in
  `<div class="shop-contents" :class="{ 'is-linking': unknown?.linking }">…</div>`
  (`shop-contents` is `display: contents`, Shop rule 7, so layout is
  unchanged; the class only scopes the CSS below).
- `resources/css/shop.css`, inside the APP ADDITIONS block, before the
  `shop-contents` rule:

```css
/* The delivery scan screen while an unknown code waits for its unit barcode
   (deliveries cycle 1 rev 2): the card and the scan field turn amber so a scan
   cannot be mistaken for counting the next item. */
.shop-card--linking { background: var(--shop-warn-soft); box-shadow: inset 0 0 0 3px var(--shop-warn); }
.is-linking .shop-scan__field { border-color: var(--shop-warn); background: var(--shop-warn-soft); }
.is-linking .shop-scan__field > .shop-ico { color: var(--shop-warn-ink); }
```

Check: the design block is still verbatim:
`head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css`
prints nothing; `php artisan test tests/Feature/Shop/ShopViewContractTest.php tests/Feature/Shop/ConfinePinSessionTest.php` passes.

### 10. Tests for revision 2
Files: `tests/Feature/Shop/ShopDeliveryTest.php`
What:
- Camera test: count 4 → 5; comment: a committed add, a cancelled prompt, a
  code that is not in the delivery, a return to waiting for the unit barcode
  (`backToLinking`: a refused or failed link, a lookup that found nothing, or
  Not this one), and Dismiss while a candidate was showing.
- `test_scan_screen_offers_to_link_an_unknown_outer_barcode`: also assert the
  page contains `confirmLink()`, `rejectCandidate()`, `Yes, link it`,
  `Not this one`, `Cancel linking`, `shop-card--linking`, `is-linking`; and
  that `this.post(this.outerUrl` occurs exactly once in the JS and after
  `confirmLink()`'s definition (`strpos($js, 'confirmLink()') < strpos($js, 'this.post(this.outerUrl')`),
  with a message saying a scan alone must never save a link.
- New `test_linking_waits_for_a_tap`: assert the JS contains
  `backToLinking(null)` and `candidate = { code: data.product.barcode`, and
  that within `findUnit(` (from its definition to the next `    },\n` at the
  method indent, or simply from `findUnit(` to `confirmLink(`) the string
  `this.post(this.outerUrl` does not occur.
- New `test_an_outer_code_cannot_be_linked_as_a_unit_barcode`: POST the
  save route with `unitBarcode '15000000000014'` (p1's outer code; no
  `supplier_link.Barcode` equals it), `outerCode '15000000000021'` → 404,
  `success false`, and p1's `OuterCode` unchanged. (The client refuses this
  earlier with the "already the case barcode of" message; this pins the server
  backstop.)
Check: `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` → 55 passed.

### 11. Feature doc for the confirmation
Files: `docs/features/shop-mode.md`
What: in "Deliveries: unknown barcodes and outer codes", replace "Tapping it
makes the next scan, or a pick from Find by name, the unit barcode of the
product inside the case." with: tapping it turns the card and the scan field
amber and asks for the barcode on one item from the case (or a pick from Find
by name); the product found is shown with its picture and **Yes, link it** /
**Not this one**, and nothing is saved until Yes is tapped (a scan of a code
that is already a case barcode, or that no product has, is refused with a
message and the card keeps waiting); **Cancel linking** or Dismiss leaves
without saving. Keep the rest.
Check: `grep -c "Yes, link it" docs/features/shop-mode.md` → 1.

### 12. Format, build, browser check
Files: none new.
What: `./vendor/bin/pint` on changed PHP files; `npm run build`. Browser
check as step 7's setup (session `4149c0a2-…`, supplier 5, a stocked link
with `CaseUnits > 1` and no `OuterCode`; if the Chrome extension is still not
connected, write the six observations as a checklist in `implemented.md` for
the owner and set `BLOCKED` on that alone):
  1. Type `15000000000021`, Enter → Not found card, plain colours.
  2. Tap **Link as outer barcode** → card and scan field amber, "Cancel
     linking" visible, title "Link outer barcode".
  3. Type the barcode of a *different* supplier-5 product (the mistaken
     "next delivery item"), Enter → "Link to this product?" with that
     product's name and picture; tap **Not this one** → back to the amber
     waiting state, nothing saved (`OuterCode` still null for both links).
  4. Type an existing outer code of supplier 5 (any non-null
     `supplier_link.OuterCode` where `SupplierID` is 5), Enter → message
     "… is already the case barcode of …", still waiting.
  5. Type the chosen product's barcode, Enter → "Link to this product?" with
     its name; tap **Yes, link it** → toast "Linked to <name>", card closes,
     prompt opens "Outer · Case of N", button "Add 1 case · N units". Cancel
     the prompt.
  6. Type `15000000000021` again → case prompt straight away. Cancel it.
  7. Type an unknown code `15000000000038`, Link, type the chosen product's
     barcode → candidate shown; tap the × (Dismiss) → card closes, nothing
     saved for `15000000000038`.
  Then put the dev data back (`OuterCode` null on the chosen link) and list
  it under "Dev state". Also list, without changing them, any supplier-5
  links whose `OuterCode` starts with `150000000000` — the owner's own trial
  of revision 1 on dev may have linked one; the owner decides.
Check: each observation recorded, console clean.

## Verification (revision 2)

1. `./vendor/bin/pint --test $(git diff --name-only -- '*.php')` → PASS on the changed files.
2. `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` → 55 passed.
3. `php artisan test tests/Feature/Shop` → all pass.
4. `php artisan test` → 15 failed (the baseline set), 1038 passed, nothing else failing.
5. The design CSS block is verbatim (`cmp` in step 9 prints nothing).
6. `npm run build` succeeds.
7. The browser check in step 12, with dev data put back.

## Review of revision 2

(Planner fills this in after reading implemented.md and the diff.)
