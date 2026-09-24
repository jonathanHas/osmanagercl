# Shop mode cycle 7b — hover preview follow-ups — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-24

## Baseline
HEAD: 47ee616f
Pre-existing dirty files (cycles 3–7, accepted and archived but uncommitted — not mine):
```
 M config/shop.php
 M docs/design/shop-mode/README.md
 D docs/planImp/implemented.md
 M docs/planImp/plan.md
 M resources/css/shop.css
 M resources/js/shop.js
 M routes/web.php
 M tests/Feature/Shop/ShopHomeTest.php
?? app/Http/Controllers/Shop/FindProductController.php
?? app/Http/Controllers/Shop/StockScanController.php
?? docs/planImp/archive/2026-09-23-scraping-queue-cleanup/
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-3/
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-4/
?? docs/planImp/archive/2026-09-23-shop-mode-cycle-5/
?? docs/planImp/archive/2026-09-24-shop-mode-cycle-6/
?? docs/planImp/archive/2026-09-24-shop-mode-cycle-7/
?? resources/js/shop/
?? resources/views/components/shop/scan-input.blade.php
?? resources/views/shop/find-product.blade.php
?? resources/views/shop/stock-scan.blade.php
?? tests/Feature/Shop/ShopFindProductTest.php
?? tests/Feature/Shop/ShopStockScanTest.php
```

## Pre-flight

The plan's Risks section assumes Alpine 3 for `destroy()`. Confirmed:
```
$ node -p "require('./package.json').devDependencies.alpinejs"   → ^3.4.2
$ node -p "require('./node_modules/alpinejs/package.json').version" → 3.14.9
```

## Steps
### 1. Conservative panel height — done
Changed: `resources/js/shop/find-product.js` (`PEEK_H` 300 → 320, comment rewritten with the measurement)
Check output:
```
$ node -e "... d.peekAt({id:'a', image_url:'u'}, el at {left:100,right:148,top:700}) ..."
160 472
```
`800 − 320 − 8 = 472`, exactly as the plan predicts. The x coordinate is unchanged.

### 2. Removable scroll listener — done
Changed: `resources/js/shop/find-product.js` (`onScroll` state, named handler in `init()`, new `destroy()`)
Check output — the plan's own check:
```
$ node -e "... stub add/removeEventListener; d.init(); d.destroy() ..."
2 scroll true
```
Two calls, on `scroll`, and the same function reference added and removed.

Two extra checks the plan did not specify:
```
stored handler clears peek        -> null
remove calls after two destroys   -> 1 (expect 1)
onScroll after destroy            -> null
```
So the stored reference really is the `unpeek` handler, and `destroy()` is
idempotent — a second call cannot fire a stray `removeEventListener`.

### 3. Build and format — done
Check output:
```
$ npm run build
✓ built in 9.29s

$ ./vendor/bin/pint --dirty
  PASS   7 files
```
No PHP changed this cycle; Pint ran anyway, as the plan asked, and is clean.
```
$ grep -c "PEEK_H = 320" resources/js/shop/find-product.js      → 1
$ grep -c "removeEventListener" resources/js/shop/find-product.js → 1
```

## Deviations

None. Both edits were exactly as specified.

One clarification on the plan's step 2 check: its stub included
`d.readHistory = () => []`, which this module does not have (that is
`stock-scan.js`). Harmless — assigning an unused property changes nothing — so I
ran the check as written rather than altering it, and it passed.

## Verification

1. `php artisan test --filter=Shop` → **pass**, `Tests: 104 passed (254 assertions)`.
   Unchanged from cycle 7, which is correct: no test asserts the numeric clamp.

2. `php artisan test` → **pass against baseline**: `Tests: 17 failed, 504 passed (1874 assertions)`.
   Identical to cycle 7's totals — same 17 pre-existing failures, no new passes,
   because this cycle adds no tests.

3. `git diff --stat` → the tracked diff does **not** list
   `resources/js/shop/find-product.js`, because `resources/js/shop/` is still
   untracked (`??`) from cycle 3; an untracked file cannot appear in a diff. The
   file it lists are all pre-existing dirt from cycles 3–7, plus one thing that is
   not mine — see the note below. **pass, with that caveat.**

4. `grep -n "PEEK_H\|removeEventListener\|destroy()" resources/js/shop/find-product.js`:
```
21:const PEEK_H = 320;
39:    // Held so destroy() can remove the same reference init() added.
57:    destroy() {
59:            window.removeEventListener('scroll', this.onScroll);
252:            window.innerHeight - PEEK_H - PEEK_EDGE,
```
   All three edits present. **pass**

5. Manual on the till PC — **not run by me.** No browser session, so I have not
   seen a preview pinned at the bottom of a real window. The arithmetic is now
   conservative by ~16 px against a measured ~304 px panel, so it cannot overhang;
   what I cannot confirm is that the measurement itself matches the rendered panel
   (font metrics for the caption line are the uncertain part).

## Files changed

My change this cycle is **one file**: `resources/js/shop/find-product.js`
(`PEEK_H`, `onScroll`, `init()`, new `destroy()`). Nothing else.

`docs/planImp/implemented.md` is mine as always. Everything else in
`git status --short` is pre-existing dirt from cycles 3–7, with one exception:

**`docs/planImp/planimp.md` was modified during this session and is not mine.**
It was clean in my baseline (recorded above) and now carries +10 lines. The change
is entirely within the Planner's own section — an instruction to read
`implemented.md` to the end and to give every Deviation and every Note an explicit
decision in `## Review`, citing cycle 7 as the reason. I have not touched the file
and have no need to; flagging it only so the reviewer does not mistake it for part
of this cycle's diff.

## Notes for Planner

- **Verification 3 cannot work as written, this cycle or any other**, while
  `resources/js/shop/` stays untracked. `git diff --stat` only reports tracked
  files, so the one file this cycle changed is invisible to it. `git status
  --short` plus a `grep` on the file (Verification 4) is what actually proves the
  change; or `git add -N resources/js/shop/` would make the directory diffable
  without committing it. Not a problem with the work, but the check gives false
  comfort.
- **Cycles 3–7 are still uncommitted.** Seven cycles of accepted work now sit in
  one dirty tree, and every report has to distinguish "mine" from "pre-existing"
  by hand. That distinction is getting less reliable each cycle, and it is the
  main thing standing between a reviewer and a clean read of any single cycle's
  diff. A commit point is a decision for you and the owner, not me — the plans
  have all said "do not commit" — but it is worth deciding soon.
- **`PEEK_H` is still a constant, not a measurement.** Out of scope here and
  rightly so. If the caption ever wraps to two lines (a long product name with
  `white-space: nowrap` will not, but a future change might), 320 becomes short
  again. The durable fix is measuring `offsetHeight` after the first open.
- **Nothing committed, pushed or deployed**, per the plan's Constraints.
