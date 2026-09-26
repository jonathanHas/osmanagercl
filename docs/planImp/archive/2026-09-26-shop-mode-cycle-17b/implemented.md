# Shop mode cycle 17b — Harvest: recent picks first, the rest by search — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: 030e3e14

Pre-existing dirty files: cycles 15, 17 and the parallel delivery-row cycle are all
still uncommitted in this tree (their plans and reports are archived under
`docs/planImp/archive/`). The files this cycle touches —
`resources/js/shop/fv-harvest.js`, `resources/views/shop/fv-harvest.blade.php`,
`tests/Feature/Shop/ShopFruitVegTest.php` — are all cycle 17's, still untracked or
modified, so this cycle's diff is not separable from cycle 17's by `git diff` alone.
Files changed at the end lists exactly what I altered.

```
$ git status --short | wc -l
31
```

Test baseline, from cycle 17: 15 failed / 594 passed. No new tests this cycle, so
the pass count should not move.

## Pre-flight

Both of the plan's factual claims hold:
```
$ grep -n "get choices" -A 5 resources/js/shop/fv-harvest.js
    get choices() {
        const all = [...this.rows, ...this.available];
        const q = this.query.trim().toLowerCase();
        return q === '' ? all : all.filter((p) => p.name.toLowerCase().includes(q));
    },

$ php artisan tinker --execute='echo config("suppliers.jon");'
2
```
and the office page's own empty state reads "Nothing harvested recently. Use the
search above to add products." (`resources/views/fruit-veg/harvest.blade.php:88`),
which is what this cycle is matching.

## Steps

### 1. Choices: recent by default, everything by search — done

Changed: `resources/js/shop/fv-harvest.js` — `get choices()` rewritten, `get
searching()` and `get hasRecent()` added. Nothing else.

Check output:
```
$ node --check resources/js/shop/fv-harvest.js   → parses

$ node scratchpad/harvest17b.mjs
ok   empty query shows only recent: ["S1"]
ok   searching false: false              ok   hasRecent true: true
ok   query 'm' searches everything: ["M1"]
ok   searching true: true
ok   a query matching nothing gives nothing: []
ok   query 's' spans rows and available: ["S1","M1","R1"]
ok   whitespace-only query is not a search: false
ok   whitespace-only query shows recent: ["S1"]
ok   fresh: no choices: []                ok   fresh: hasRecent false: false
ok   fresh: search still reaches the range: ["M1"]
ok   posted: {"code":"M1","amount":"1.00","unit":"kg"}
ok   cleared search shows it as a recent pick: ["S1","M1"]
ok   today lists it: ["M1"]               ok   selection survived: "M1"
```

Two cases beyond the plan's list, both worth having:

- **A whitespace-only query is not a search.** `searching` trims, so a stray space
  does not empty the screen or flip the hint. The plan's `choices` change trims too,
  but the new getter is what the view keys its hint and its two empty states off, so
  they had to agree.
- **The last case is the one that matters in use**: pick a product from a search,
  log it, clear the search — it is now a recent tile with its total. That is the
  plan's claim that no special handling is needed, actually exercised rather than
  reasoned about.

My first run reported a failure on "a query matching nothing"; the fixture was at
fault, not the code — I had used `x`, and "Salad mix" contains one. Corrected the
query rather than the module.

### 2. The screen says what the list is — done

Changed: `resources/views/shop/fv-harvest.blade.php`,
`tests/Feature/Shop/ShopFruitVegTest.php`.

The hint line, the two empty states and the new placeholder are as the plan
specifies. `employee_can_open_both_screens` gained four assertions — the plan asks
for two, and I added the search empty state's title and the new placeholder so the
whole of this cycle's visible change is pinned, not half of it.

Check output:
```
$ php artisan test --filter="ShopFruitVegTest|ShopViewContractTest"
Tests:    29 passed (285 assertions)
```

### 3. Docs, build, format — done

Changed: `docs/features/fruit-veg-system.md`, `docs/design/shop-mode/README.md` —
one paragraph each, saying the Shop harvest screen lists recent picks by default,
searches Jon's whole range, and promotes a searched product to a recent pick as
soon as it is logged.

Check output:
```
$ npm run build   → ✓ built in 9.28s   (shop-DNu_EkuL.js)
$ ./vendor/bin/pint --test --dirty     → PASS 10 files
```

## Verification

**1. Tests**
```
$ php artisan test --filter=Shop
Tests:    192 passed (845 assertions)

$ php artisan test
Tests:    15 failed, 594 passed (2468 assertions)
```
15 failed, the identical set (Udea ×7, CashReconciliation ×3, FruitVegLabelPrinting
×2, Product ×2, TestScraper ×1). **Passed is 594, unchanged**, as the plan
predicted — this cycle adds assertions, not tests (2464 → 2468).

**2. Contract**
```
$ grep -c "route(" resources/js/shop/fv-harvest.js     → 0
$ grep -rn "<script\|<style" resources/views/shop/     → 0
$ design block cmp                                      → IDENTICAL
```
The plan forbids a stylesheet change and this cycle made none. `git diff` on
`resources/css/shop.css` is **not** empty, but every line in it belongs to the
parallel delivery-row session (`.shop-row--wrap`, `.shop-row__controls`); I read the
diff to confirm rather than assuming.

**3. Manual walkthrough — done, on the dev app, with real taps.**

Dev is in exactly the state this cycle is about: 111 of Jon's products, **none
harvested in the last 30 days**.

- **Opening Harvest** now shows no tiles at all instead of 111: the heading, the
  hint "Recent picks · search to add anything else", the search box reading "Search
  all your produce", and the carrot empty state "Nothing harvested recently /
  Search your produce above to log the first pick."
- **Typing "rose"** flipped the hint to "All of your produce matching the search",
  the empty state disappeared, and Rosemary appeared.
- **Tapped Rosemary → 1 → Log** → toast "Logged 1 kg Rosemary · 1 kg today", and
  `hasRecent` became true while the search was still active.
- **Clearing the search** is the point of the cycle, and it does what it should:
  the hint returns to "Recent picks", and the list is **one tile** — "Rosemary ·
  per kg · 1 kg today" — with the Today row "Rosemary · 13:30 · jonathanE · 1 kg".
  A product logged from a search becomes a recent pick with no special handling.
- **Typing "zzqq"** gave the *other* empty state — the search icon, "No produce
  matches", "Try a shorter search." — and not "Nothing harvested recently", even
  though there is now a recent pick. The two states discriminate correctly.
- Console: one message, Alpine's startup log.

I used "zzqq" rather than the plan's "x" because "Salad mix" contains an x; the
same thing tripped my node exercise first time round.

**Dev data put back.** One harvest row for Rosemary and one
`harvest_product_units` row, both created by this walkthrough, both deleted.
`Harvest::count()` is 197 with none today, `WasteLog::count()` 0, unit prefs 10 —
as before.

## Deviations

None.

## Files changed

Mine, this cycle:
```
resources/js/shop/fv-harvest.js            choices() rewritten; searching(), hasRecent() added
resources/views/shop/fv-harvest.blade.php  hint line, two empty states, new placeholder
tests/Feature/Shop/ShopFruitVegTest.php    4 assertions added to employee_can_open_both_screens
docs/features/fruit-veg-system.md          one paragraph
docs/design/shop-mode/README.md            one sentence
```
All five files were already dirty or untracked from cycle 17, so `git status` does
not separate the two cycles; the list above is exact.

Still in the tree and **not mine**: cycles 15 and 17, and the parallel delivery-row
session's `resources/css/shop.css`,
`resources/views/shop/delivery-scan.blade.php`,
`tests/Feature/Shop/ShopDeliveryTest.php` and `docs/planImp/planimp.md`.

`public/build` is gitignored; the deploy must run the build.

**Not committed, not pushed, not deployed.**

## Notes for Planner

1. **Cycle 17's harvest-accumulates-across-units defect is still open.** Logging
   5.2 kg and then 2 units of the same product gives one row reading "7.2 unit".
   It is `HarvestController::saveRow()`'s behaviour, shared with the office page,
   and out of scope in both cycles. Repeating it here so it does not fall through
   the gap between two accepted cycles.

2. **The waste screen still lists its whole on-till range** — 98 tiles — because
   that range *is* the working set for waste, unlike harvest. I have not touched
   it and I do not think it needs the same treatment, but the two screens now
   behave differently in a way a reader of the code might take for an oversight.
   The comment on `choices()` says why.

3. **Nothing on this screen is keyboard-reachable for the till PC.** The harvest
   and waste screens are the first Shop screens with no scan input, so nothing
   holds focus and there is no Enter path — everything is a tap. That is right for
   the counter tablet and probably fine, since Jon logs harvest from the yard, but
   it is the first screen where the till PC has no route through.
