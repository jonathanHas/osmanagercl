# Shop mode cycle 33 — Delivery scan: prompt without scrolling, Summary out of the way — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-28

## Baseline
HEAD: 3fbfcc44

Cycle 32's code changes are still uncommitted, so the tree started dirty with
them. Nothing reverted.
```
D  .delivery-specialist-agent-recommendation.md.kate-swp
D  JFolder_temp/.questions.txt.kate-swp
 M app/Http/Controllers/DeliveryLegacyController.php
 M docs/features/shop-mode.md
D  docs/jons_docs/.todo.md.kate-swp
R  docs/planImp/implemented.md -> docs/planImp/archive/2026-09-28-shop-mode-cycle-31/implemented.md
RM docs/planImp/plan.md -> docs/planImp/archive/2026-09-28-shop-mode-cycle-31/plan.md
D  docs/planImp/plan-wedge-focus.md
 M resources/js/shop/scan-input.js
 M resources/views/components/shop/scan-input.blade.php
 M tests/Feature/Shop/ShopDeliveryTest.php
 M tests/Feature/Shop/ShopStockScanTest.php
?? docs/planImp/archive/2026-09-28-shop-mode-cycle-32/
?? docs/planImp/plan.md
```

**Test baseline measured: `15 failed, 804 passed (3429 assertions)`** — matching
the plan's Context.

## Steps

### 1. Prompt above the scan field — done
Changed: `resources/views/shop/delivery-scan.blade.php`,
`resources/js/shop/delivery-scan.js`.

The prompt `section` is now the first child of the left `shop-stack`, before
the completed/scan-input block, with a comment giving the measured reason.
Contents unchanged. `onScan()` calls
`this.$nextTick(() => this.$refs.prompt?.scrollIntoView({ block: 'nearest' }))`
after building `pending` and before `announceDone()` — the same call the
correction card makes.

**Check, measured at a true 390 × 844 viewport** (see Verification 3 for how):
```
cameraBlockHeight: 257      promptTop: 89
addButtonBottom: 693        viewportHeight: 844
fitsWithoutScrolling: true  pageScrollY: 0
```
And the before/after, taken by moving the prompt back below the field in the
live DOM and re-measuring the same button:
```
old order: "Add 1 unit" bottom = 1038 px   → 194 px below the fold
new order: "Add 1 unit" bottom =  693 px
```

### 2. Summary out of the sticky bar — done
Changed: `resources/views/shop/delivery-scan.blade.php`.

`div.shop-actions` is now `div.shop-actions shop-actions--static` after the
list, keeping the primary-styled button. A second Summary link sits in the
Items header, inside a new `div.shop-inline` with the sort button.

**The header link is icon + count only, with the word in `shop-sr-only` — a
deviation from the plan's markup, driven by measurement. See Deviations 1.**

### 3. Correction card placement — done (left where it is)
No change, which is the plan's default. Measured at 390 px with the camera
block open: the card is **332 px** tall in an 844 px viewport, and tapping a row
puts "Done" at **827 px** — on screen. The 17 px margin looks tight but is not
fragile: `scrollIntoView({ block: 'nearest' })` scrolls the *minimum* needed, so
the card is positioned low by construction, and there is 512 px of slack before
a taller card could not fit at all.

### 4. Troubleshooting note — done
Changed: `docs/development/quick-start-guide.md`, under `### Common Issues`.

Entry for `file_put_contents(storage/framework/views/…): Permission denied`:
what it is (views the web server compiled are owned by `www-data` mode 644, so
the CLI runner cannot recompile them), when it bites (right after a browser
check, and the trace points into Laravel so it looks like a framework bug), the
workaround (`php artisan view:clear`) and the owner's one-time `setfacl` fix
for both `storage/framework/views` and `bootstrap/cache`.

### 5. One sentence in `scan-input.js` — done
Changed: `resources/js/shop/scan-input.js` header comment. Names the 1 s burst
window in `capture()` and the 1500 ms `lastAt` push in
`restartCameraIfWanted()` (3.5 s total with `detected()`'s 2 s), says they
guard the same failure from two directions, and says to tune them together. No
code change.

### 6. Tests, build, tidy — done
Changed: `tests/Feature/Shop/ShopDeliveryTest.php`.

`test_the_quantity_prompt_comes_before_the_scan_field` — `strpos` of
`x-ref="prompt"` < `strpos` of `shop-scan__input` in the rendered HTML, with a
failure message naming the consequence, plus a source assertion for the
`scrollIntoView` call.

`test_summary_is_in_the_header_and_the_bottom_bar_is_not_sticky` — exactly two
summary hrefs and two `shop-btn__count`s; the first href falls between
`shop-group-title` and `class="shop-list"`, so it really is in the header; the
sticky `<div class="shop-actions">` is gone and the static variant is present.

```
npm run build   → shop-BSTjG56S.js 37.68 kB │ gzip 10.46 kB   ✓ built in 7.01s
php artisan view:clear                                  → cleared
./vendor/bin/pint tests/Feature/Shop/ShopDeliveryTest.php → PASS, 1 file
```

## Deviations

1. **The header Summary link is icon + count, not icon + the word "Summary".**
   The plan's markup produced a header **160 px tall on three lines** at 390 px
   — title, then the sort button, then the Summary link, each on its own row.
   That is worse than the ~80 px sticky bar the step removes, so I measured the
   alternatives in the live phone layout before choosing:

   | variant | lines | header height |
   |---|---|---|
   | plan as written | 3 | 160 px |
   | drop the Summary icon | 3 | 160 px |
   | **sort keeps its label; Summary = icon + count (chosen)** | **2** | **92 px** |
   | sort icon-only; Summary keeps icon + word | 2 | 92 px |
   | sort icon-only *and* Summary icon-only | 2 | 56 px |

   The plan's own fallback was the fourth row — "shorten the sort label to an
   icon-only ghost button". I chose the third instead: it costs the same 92 px
   but keeps the sort label, which an earlier cycle chose deliberately ("the
   label names the order in force; tapping it flips") and which an icon cannot
   convey. What is lost is the visible word "Summary" in the header, where an
   icon, a live issue count and a `title="Summary"` remain — and the
   fully-labelled Summary button still sits at the end of the list. The word is
   in `shop-sr-only`, so screen readers and the existing `assertSee('Summary')`
   both still get it.

   I did not take the 56 px variant because it gives up both labels to save a
   further 36 px on one screen.

2. **Both Summary links were kept.** The plan allowed dropping the bottom one
   if it duplicated awkwardly. At 390 px it does not: the header one is a
   compact glanceable count at the top, the bottom one is a full primary button
   exactly where someone who has worked down the list finishes. They read as
   different affordances rather than a repeat.

Nothing under **Out of scope** was touched: the camera block still shows while
the prompt is open, and neither the summary page nor the design copy changed.

## Verification

1. **`php artisan test`** → `Tests: 15 failed, 806 passed (3441 assertions)`.
   The same 15 pre-existing failures, unchanged in name and count. Passing went
   804 → 806: the two new tests. **No new failures.**

2. **Contract.** `ShopViewContractTest` green (inside the 54 below);
   design-block `cmp` prints nothing; `git diff --stat resources/css/shop.css`
   is empty — no CSS change at all, as the Constraints require.
   `php artisan test --filter='ShopDeliveryTest|ShopViewContractTest'` →
   `54 passed (413 assertions)`.

3. **Browser at 390 × 844.** A note on method, because it matters for how much
   the numbers are worth: `resize_window` did **not** change the page viewport
   on this machine — the window resized but `innerWidth` stayed 826. Rather
   than report measurements from the wrong width, I rendered the page in a
   same-origin iframe sized exactly 390 × 844. Media queries, `innerWidth` and
   `getBoundingClientRect` all resolve against the iframe's own viewport, so
   the layout is the real mobile one: `splitColumns` came back as a single
   `354px` column, confirming the phone breakpoint was active.

   - **Prompt fully visible without scrolling.** `addButtonBottom: 693` against
     `viewportHeight: 844`, `pageScrollY: 0`. Before the change, same
     measurement: **1038 px, i.e. 194 px below the fold**.
   - **Scan from the bottom of the list.** Scrolled to the end (`scrollY: 258`),
     then scanned: the page moved to `scrollY: 89` and the prompt was fully
     visible (`promptTop: 0`, `addButtonBottom: 604`). The `scrollIntoView`
     does its job.
   - **Correction card.** Tapping a row: `cardTop: 512`, `doneButtonBottom: 827`
     against 844 — "Done" on screen. Card height 332 px, so 512 px of slack.
   - **Items header at 390 px.** Two lines, 92 px: "Items 3" on the first,
     "New first" and the Summary icon + count on the second. `overflowsWidth:
     false`. Screenshot taken.
   - **Bottom bar.** One `.shop-actions` element, class
     `shop-actions shop-actions--static`, computed `position: static`, text
     "Summary 3". No sticky bar remains.

4. **Browser at 1280 × 800** (same iframe, resized):
   ```
   splitColumns: "582.469px 529.531px"
   prompt: left 65,  right 647
   list:   left 671, right 1201     promptLeftOfList: true
   headerHeight: 56 (one line)      sortLabel: "New first"
   ```
   Two columns, prompt left, list right, nothing overlapping, and the header
   fits on one line with the sort label showing.

   Console across the whole run: **no errors or exceptions**.

5. **Dev data** — restored, see below.

## Files changed

```
 M docs/development/quick-start-guide.md
 M resources/js/shop/delivery-scan.js
 M resources/js/shop/scan-input.js          (header comment only)
 M resources/views/shop/delivery-scan.blade.php
 M tests/Feature/Shop/ShopDeliveryTest.php
?? docs/planImp/implemented.md              (this file)
```
Plus `public/build/*` from `npm run build`. Everything else dirty in the tree
is cycle 32's and is listed under Baseline.

No commits, no deploys.

## Dev state

The browser check scans through real endpoints. Cleaned up, verified after:
```
delivery scan rows: 0        (three rows from the checks, deleted)
devices remaining: 1         (the pre-existing revoked "Dev browser check")
label queue: 0
stock adjustments today: 0
```
Session `4149c0a2-…` is back to empty, where it started. The seeded "Cycle 33
browser check" device was deleted, the cookie cleared, the tab closed. katelyn
(id 3) still has PIN `2580`. No config file was modified.

## Notes for Planner

1. **There is no "hide on narrow" utility, and that is what forced Deviation 1.**
   `shop.css` has `.shop-touch-only` (shown only when `.is-touch`) and
   `.shop-sr-only`, but nothing that varies by viewport width. With one, the
   header could show "Summary" on the till PC — where there is room — and drop
   to icon + count on a phone, and I would not have had to choose. A single
   line under APP ADDITIONS would do it, e.g.
   `@media (max-width: 640px) { .shop-wide-only { display: none !important; } }`.
   The Constraints forbade a CSS change this cycle, so I did not add it. If you
   want the word back on desktop, that is the cheap way.

2. **`resize_window` does not resize the page viewport on this machine**, which
   is worth knowing before writing another "check it at 390 px" step. The
   iframe technique in Verification 3 is reliable and cheap — same origin, real
   media queries, real geometry — and I would suggest naming it in the plan
   next time rather than leaving each cycle to discover it.

3. **The idle lock fired in the middle of the desktop measurement** and signed
   the session out, which sent the iframe to `/login` and cost a re-auth. Not a
   bug — it is cycle 26 working — but any future cycle whose browser check
   involves long pauses on a trusted device will hit it. Either lower
   `idle_lock_minutes` awareness into the plan's check steps, or untrust the
   device for measurement work.

4. **The 17 px clearance under the correction card's "Done"** is safe today for
   the reason given in step 3, but it is the tightest thing on the screen. If a
   future change makes that card taller than ~840 px it will stop fitting
   entirely and `scrollIntoView` will not save it. Not worth acting on now;
   worth remembering if anyone adds a field to it.
