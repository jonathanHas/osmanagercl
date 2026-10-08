# Cycle 1 — Link an unknown outer barcode from the Shop scan screen — implementation

Status: BLOCKED
Plan revision: 2
Implementer: Fable 5.1 (this session was started as the Implementer; the protocol names Opus)
Date: 2026-10-08 (revision 1 report earlier the same day; revision 2 below)

**Where it stands in one line:** revision 2 steps 8–11 are done and every
automated check passes (55 delivery tests, 324 Shop tests, the design CSS
block verbatim, pint clean on the changed files, build clean; full suite in
Verification 4). The one thing not done is the **browser check in step 12**:
the Claude-in-Chrome extension was still not connected ("Browser extension
is not connected"), so, as step 12 says to do in that case, the seven
observations are written below as a checklist for the owner and the status is
BLOCKED on that alone. The same observations were driven through the real
`delivery-scan.js` in a Node harness (under step 12). No POS data was written.

---

## Revision 1 (accepted in the Planner's review; kept for the record)

### Baseline
HEAD: f29c5369
Pre-existing dirty files:
```
?? docs/deliveries/
```

### Steps 1–7 — done (step 7's browser check was run by the owner)
Steps 1–6 as reported in revision 1 and reviewed by the Planner: PIN
allow-list line in `config/shop.php`; Not found card in
`delivery-scan.blade.php`; `unknown` state, routing and `linkOuter()` in
`delivery-scan.js` (`announceSaved` count 4); camera test 3 → 4 and six new
tests (53 passed); controller docblock; feature doc section. Deviations 1–4 of
revision 1 were accepted. The owner's browser check found the issue revision 2
fixes: after tapping Link, a scan of the next delivery item linked it
silently.

---

## Revision 2

### Baseline (revision 2)
HEAD: f29c5369 (unchanged; nothing committed)
Dirty files before revision 2 began: the six files of revision 1 plus
`?? docs/deliveries/`.

### 8. Confirm before linking — done
Changed: `resources/js/shop/delivery-scan.js`
- `unknown` is now `{ code, linking, candidate, error }` (state comment
  updated; `lookup()` sets `candidate: null`). Getter `candidateProduct`
  beside `pendingProduct`.
- `linkOuter()` replaced by `findUnit(code)`, `confirmLink()`,
  `rejectCandidate()` and `backToLinking(message)`, as specified. `findUnit`
  posts `scanUrl` with quantity 0 and never `outerUrl`; same-code, no
  product, `scanType === 'case'` and a network failure each go to
  `backToLinking(...)` with the plan's messages; a found unit sets
  `candidate = { code: data.product.barcode, product: data.product }`, calls
  `announceDone()` (no resume) and scrolls the card into view. `confirmLink`
  is the only `this.post(this.outerUrl` in the file; on failure
  `backToLinking(data?.message || 'Could not link, try again')`.
  `backToLinking` writes the state when the card is still open and then
  `announceSaved()` unconditionally. `dismissUnknown()` resumes the camera
  only when a candidate was showing.
- `onScan()` / `pickResult()` route to `findUnit()` while linking.
  `startLink()` also clears `candidate`.
- Header comment updated: the next scan shows the product it found and a tap
  links it; a scan alone never saves.
Check output:
```
$ grep -c 'this.announceSaved();' resources/js/shop/delivery-scan.js
5
$ grep -c 'this.post(this.outerUrl' resources/js/shop/delivery-scan.js
1
$ npm run build
✓ built in 7.97s
```

### 9. The card in three states, and the scan field coloured while linking — done
Changed: `resources/views/shop/delivery-scan.blade.php`, `resources/css/shop.css`
- Section gets `:class="{ 'shop-card--linking': unknown?.linking }"`; title
  expression as the plan; code line kept.
- Not found: sentence + primary **Link as outer barcode**. Waiting: the amber
  sentence ("… not the next delivery item. Nothing is counted until you
  confirm." + " Or find it by name." when `$canSearch`) and a ghost block
  **Cancel linking** (`x` icon, `dismissUnknown()`). Candidate: `shop-inline`
  row with `<x-shop.product-thumb expr="candidateProduct" />`, name, code;
  the "becomes the case barcode of this product." line; primary **Yes, link
  it** (`check` icon, `confirmLink()`, `:disabled="busy"`); ghost **Not this
  one** (`rejectCandidate()`). Error notice unchanged, any state.
- `<x-shop.scan-input …/>` wrapped in
  `<div class="shop-contents" :class="{ 'is-linking': unknown?.linking }">`.
- CSS: the three rules from the plan inside APP ADDITIONS, directly before the
  `.shop .shop-contents` rule's comment. Only `x-shop.*` components and
  `shop-*` classes; `?.` on every read of `unknown` and `candidateProduct`.
Check output:
```
$ head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css | cmp - docs/design/shop-mode/shop.css; echo "cmp exit $?"
cmp exit 0          (nothing printed: design block verbatim)
$ php artisan test tests/Feature/Shop/ShopViewContractTest.php tests/Feature/Shop/ConfinePinSessionTest.php tests/Feature/Shop/ShopDeliveryTest.php
  Tests:    90 passed (680 assertions)      (35 + 55)
```

### 10. Tests for revision 2 — done
Changed: `tests/Feature/Shop/ShopDeliveryTest.php`
- Camera test: 4 → 5, comment lists the five restarts as the plan words them.
- `test_scan_screen_offers_to_link_an_unknown_outer_barcode`: the seven new
  `assertSee`s, `this.post(this.outerUrl` exactly once and after
  `confirmLink()`, with the plan's message.
- New `test_linking_waits_for_a_tap` (the segment from `async findUnit(` to
  `async confirmLink(` contains no save) and
  `test_an_outer_code_cannot_be_linked_as_a_unit_barcode` (404, p1's outer
  unchanged).
Check output:
```
$ php artisan test tests/Feature/Shop/ShopDeliveryTest.php
  Tests:    55 passed
```
(in the 90-test run above; the file alone is 55.)

### 11. Feature doc for the confirmation — done
Changed: `docs/features/shop-mode.md` — the sentence replaced as the plan
words it; the rest of the section kept.
Check output:
```
$ grep -c "Yes, link it" docs/features/shop-mode.md
1
```

### 12. Format, build, browser check — partly done (browser check not run)
- Pint on changed PHP files: `PASS 4 files`. `npm run build`: clean.
- **Browser check: not run.** `tabs_context_mcp` → "Browser extension is not
  connected" (one attempt this revision, three in revision 1).
- **Node harness** (`scratchpad/harness2.mjs`, not in the repo): the real
  module with `$root`, `$refs`, `$nextTick`, `window.dispatchEvent` and
  `fetch` stubbed; scan answers scripted from the controller shapes; the
  "server" records `save-outer-barcode` writes. Real output, trimmed
  (`amber` = `unknown?.linking`, the class binding; `saved` = outer → unit
  writes so far):
  ```
  1.  type 15000000000021            events: scroll:unknown, shop-scan-error(...), shop-scan-saved
                                      title "Not found", amber false, saved {}
  2.  tap Link as outer barcode      events: shop-scan-done        title "Link outer barcode", amber true
  3a. type 8711521093489 (another supplier-5 product, the "next item")
                                      events: shop-scan-done, scroll:unknown   <- no resume: candidate shown
                                      title "Link to this product?", candidate {8711521093489, "Ekoplaza Paper carrier bag small"}, saved {}
  3b. tap Not this one               events: shop-scan-saved       title "Link outer barcode", candidate null, error null, saved {}
  4.  type 4019886650205 (existing supplier-5 outer)
                                      events: shop-scan-saved
                                      error "4019886650205 is already the case barcode of <name>. Scan the barcode on one item."  still waiting
  4b. type 15000000000021 again       error "That is the outer barcode again. ..."   still waiting, no request sent
  4c. type a code no product has      error "No product has barcode 0000000000000. ..." still waiting
  5a. type 3263670237917              events: shop-scan-done, scroll:unknown   title "Link to this product?", candidate "Phare d'Eckmuhl Mackerel Fillets in Olive Oil"
  5b. tap Yes, link it               events: scroll:prompt, shop-scan-done     <- no resume while the prompt is open
                                      toast "ok: Linked to Phare d'Eckmuhl ...", card closed, pending {case, caseUnits 12, "Add 1 case · 12 units"}
                                      saved {15000000000021: 3263670237917}
  5c. cancel the prompt              events: shop-scan-done, shop-scan-saved
  6.  type 15000000000021 again       events: scroll:prompt, shop-scan-done     case prompt straight away
  7a. unknown 15000000000038, Link, type 3263670237917   candidate shown, saved unchanged
  7b. tap × (Dismiss)                events: shop-scan-done, shop-scan-saved   card closed, saved still only 15000000000021
  8.  Cancel linking while waiting    events: shop-scan-done (camera already running)   card closed
  9a. pick by name while waiting      candidate shown (pickResult → findUnit)
  9b. Yes, link it after a pick       toast, case prompt; saved now has 15000000000038 too
  requests: every POST /outer is preceded by a POST /scan {quantity: 0} for the unit and a tap;
            4, 4b, 4c, 3a→3b and 7 produced no POST /outer.
  ```
  Not covered: the rendered amber (CSS) and the `x-show` wiring beyond the
  rendering test, focus handling in `scan-input.js`, the real camera.
- **Owner's checklist (step 12, dev host, signed in as `test`)**, session
  `4149c0a2-ae65-49ec-9c00-8da5a8124cef`, supplier 5. Chosen link: ID 490,
  barcode `3263670237917`, "Phare d'Eckmuhl Mackerel Fillets in Olive Oil",
  CaseUnits 12, `OuterCode` null. A different supplier-5 product for 3:
  `8711521093489` (link 3759, "Ekoplaza Paper carrier bag small"). An existing
  supplier-5 outer code for 4: `4019886650205`.
  1. [ ] Type `15000000000021`, Enter → Not found card, plain colours.
  2. [ ] Tap **Link as outer barcode** → card and scan field amber, **Cancel
         linking** visible, title "Link outer barcode".
  3. [ ] Type `8711521093489`, Enter → "Link to this product?" with that
         product's name and picture; tap **Not this one** → amber waiting
         state again; `OuterCode` still null on links 490 and 3759.
  4. [ ] Type `4019886650205`, Enter → "… is already the case barcode of …",
         still waiting.
  5. [ ] Type `3263670237917`, Enter → "Link to this product?" with its name;
         tap **Yes, link it** → toast "Linked to Phare d'Eckmuhl …", card
         closes, prompt "Outer · Case of 12", button "Add 1 case · 12 units".
         Cancel the prompt.
  6. [ ] Type `15000000000021` again, Enter → case prompt straight away.
         Cancel it.
  7. [ ] Type `15000000000038`, Enter, tap Link, type `3263670237917`, Enter →
         candidate shown; tap × (Dismiss) → card closes; no link saved for
         `15000000000038`.
  Then put the dev data back:
  `php artisan tinker --execute="DB::connection('pos')->table('supplier_link')->where('ID', 490)->update(['OuterCode' => null]);"`
  and confirm the session's `deliveriesScanItems` count is still 0. Console
  clean of errors throughout.
- **Dev state** (read-only this session; the harness mocked `fetch`):
  ```
  link 490 OuterCode: NULL
  session 4149c0a2-… deliveriesScanItems: 0
  supplier-5 links with OuterCode like '150000000000%': none
  ```
  So the owner's revision 1 trial on dev left nothing behind; nothing to put
  back.

## Deviations
1. **Step 12 browser check not run** — extension not connected; checklist
   written and status BLOCKED on that alone, as the plan directs. The Node
   harness is extra evidence, not a substitute the plan asked for.
2. **`findUnit()` on Dismiss mid-flight returns quietly** as the plan says. Note
   that in this one case the camera stays paused (the detection paused it,
   the card had no candidate so `dismissUnknown()` did not resume it, and
   `findUnit` returns before `backToLinking`). Not changed: the plan is
   explicit about the quiet return; see Notes.
3. **Button order in the candidate state**: the error notice is rendered
   before the buttons (one notice element, any state), so in the candidate
   state a refusal message would appear above **Yes, link it** rather than
   between the buttons. The plan did not fix an order.
4. No other deviations. Steps 1–7 were not redone.

## Verification (revision 2)
1. `./vendor/bin/pint --test $(git diff --name-only -- '*.php')` → `PASS 4 files`.
2. `php artisan test tests/Feature/Shop/ShopDeliveryTest.php` → **55 passed**.
3. `php artisan test tests/Feature/Shop` → **324 passed** (1641 assertions).
4. `php artisan test` → **15 failed, 1038 passed** (4762 assertions). The 15 by
   class: `Tests\Unit\UdeaScrapingServiceTest` ×7,
   `Tests\Feature\CashReconciliationTest` ×3,
   `Tests\Feature\FruitVegLabelPrintingTest` ×2, `Tests\Feature\ProductTest` ×2,
   `Tests\Feature\TestScraperControllerTest` ×1 — the baseline set, nothing else.
5. Design CSS block verbatim: `cmp` printed nothing, exit 0.
6. `npm run build` → `✓ built in 7.97s`.
7. Browser check → **not run**; owner's checklist under step 12.

## Files changed
```
 M app/Http/Controllers/Shop/DeliveryController.php   (rev 1)
 M config/shop.php                                    (rev 1)
 M docs/features/shop-mode.md                         (rev 1 + 2)
 M resources/css/shop.css                             (rev 2)
 M resources/js/shop/delivery-scan.js                 (rev 1 + 2)
 M resources/views/shop/delivery-scan.blade.php       (rev 1 + 2)
 M tests/Feature/Shop/ShopDeliveryTest.php            (rev 1 + 2)
?? docs/deliveries/                                   (pre-existing)
```
`git diff --stat`: 7 files changed, 499 insertions(+), 10 deletions(-).
Nothing committed.

## Notes for Planner
- **Camera after Dismiss during an in-flight unit lookup** (Deviation 2): on
  a phone, tapping × in the ~200 ms between a camera detection and the scan
  answer leaves the camera paused until the person taps the camera button.
  Rare and recoverable. If wanted, `dismissUnknown()` could resume when
  `busy` as well as when a candidate is showing; that would make the
  `announceSaved` count 5 still (same call) but the condition
  `!! this.unknown?.candidate || this.busy`. Not done: plan says quiet return.
- **Camera while a candidate is showing** stays paused by design, so a person
  who scans instead of tapping Yes/Not this one gets nothing from the camera;
  a hand scanner or typing still reaches `findUnit()` and replaces the
  candidate (the lookup runs again). Matches the plan; mentioned so the
  browser check does not read it as a bug.
- **Harness scripting slip, not a defect**: a first run of scenario 8 scanned
  the already-linked `15000000000021`, which opened a case prompt instead of
  a card, and the next scan then committed that prompt ("Added", quantity 1
  to the mocked server). That is the pre-existing "a scan over a prompt
  confirms it" rule. Re-run with an unlinked code, scenario 8 behaved as
  reported above.
- **The existing supplier-5 outer code** `4019886650205` was found with a
  tinker query (`SupplierID 5, OuterCode not null`); its product name is
  whatever the scan endpoint returns on dev — the harness used a placeholder.
- README "Where things stand" still says 47 delivery tests / 1030 passed;
  after this cycle it is 55 / 1038.
