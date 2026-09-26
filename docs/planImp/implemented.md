# Shop mode cycle 19 — Harvest: print labels as you log — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: 9a41cc3d, 26 dirty paths — cycles 17f, 17g and 18, all mine, accepted and
archived but uncommitted. Files changed at the end separates them.

Test baseline: 15 failed / 634 passed (2707 assertions).

## Pre-flight

Every piece the plan relies on exists:

- `zebra-labels.print` is `POST labels/zebra/manage/{zebraLabel}/print` behind
  `PermissionMiddleware:labels.print` — which employees hold, so the Shop screen can
  reach it.
- `ZebraLabelController::print()` clamps copies 1–99, rewrites `^PQ` rather than
  repeating the ZPL, and returns the three messages the plan quotes with 200/500.
- `HarvestController::labelPayload()` returns `{ id, name, width_mm, height_mm }`,
  falling back from the ZPL's own dimensions to the stored ones.
- Design classes `shop-inline`, `shop-btn--secondary`, `shop-iconbtn--ghost`,
  `shop-stepper`, `shop-card--flat`, `shop-between`, `shop-subtitle` and the icons
  `printer`, `alert`, `x` are all present. No `APP ADDITIONS` expected.
- `DeliveryTranslatedLabelPrintingTest::fakePrinter()` binds a `ZebraPrintService`
  with `usingRunner()` that captures the ZPL from the temp file named in the `lp`
  command — the pattern to copy so no test touches a real printer.

**I will not print on the real printer.** The plan puts that with the owner and I
have kept to it; everything below is tests, a node exercise, and browser checks that
stop short of sending a job.

## Steps

### 1. Rows carry the label — done

Changed: `app/Http/Controllers/HarvestController.php` — `'label'` on each item of
`rows` and of `available` in `rows()`. Two lines plus a comment; `dataFor()` already
had the payload.

### 2. Behaviour: the offer and the print — done

Changed: `resources/js/shop/fv-harvest.js` — `print` state, `printUrlTemplate` and
`sizeText` getters, `offerPrint()`, `reprint()`, `dismissPrint()`, `clampCopies()`,
`bumpCopies()`, `sendPrint()`, and the hook in `log()`.

`log()` needed one extra line beyond the plan: it reads `this.amount` through a
getter derived from `typed`, and `typed` is cleared on success before the offer
would be made, so the amount is captured into a local first. Without it the copies
default would always have been 1.

```
$ node scratchpad/print19.mjs
ok   offer appears after a log: true
ok   copies default from the amount (4.2 -> 4): 4
ok   product on the card: "Salad mix"
ok   sizeText: "112.6 × 75.1 mm"
ok   bumpCopies clamps low: 1            ok   bumpCopies clamps high: 99
ok   posted to the templated url: "/labels/zebra/manage/7/print"
ok   posted copies: {"copies":4}
     toast: "Print job sent (4 copies)"
ok   card closes on success: null
ok   card stays open on failure: true    ok   result shown on the card: "Print failed"
ok   not stuck sending: false
ok   retry closes the card: null
ok   network failure keeps the card: true
ok   network failure message: "Could not reach the printer"
ok   no label: no offer: null
ok   reprint copies from logged: 3       ok   dismiss clears: null
ok   reprint on a row with no label does nothing: null
ok   count maps one to one: 7
$ grep -c "route(" resources/js/shop/fv-harvest.js → 0
```
Three cases beyond the plan's list, all about not getting stuck: a retry after a
failure succeeds and closes the card; `sending` is reset in a `finally` so a failed
print never leaves the button disabled forever; and `reprint()` on a row without a
label is a no-op rather than opening an unusable card.

### 3. The screen — done

Changed: `resources/views/shop/fv-harvest.blade.php` — the `data-print-url-template`
attribute, the print card above the unit/numpad card, and the printer button on
Today rows.

```
$ php artisan test --filter=ShopViewContractTest → 19 passed
$ rendered page contains: data-print-url-template, "Print labels",
  "Check the printer has", aria-label="Print labels", __ID__
```

### 4. A print test through the real endpoint — done

Changed: `tests/Feature/Shop/ShopFruitVegTest.php` — a `label()` helper, label
assertions on the rows test, and two print tests.

```
$ php artisan test --filter=ShopFruitVegTest
✓ harvest print uses the zebra endpoint
✓ printing needs the labels permission
  ... plus 12
Tests:    14 passed (119 assertions)
```
The print test binds a `ZebraPrintService` whose runner captures the ZPL from the
temp file, so nothing reaches a printer, and asserts `^PQ3` — which pins the
behaviour the Shop screen depends on: three copies is one job asking for three, not
the ZPL sent three times.

### 5. Docs, format, build — done

`docs/features/fruit-veg-system.md` and `docs/design/shop-mode/README.md`; the
README records the URL-template pattern, which is how a per-record route reaches a
Shop module without a route helper in JavaScript.
`./vendor/bin/pint --test --dirty` → PASS; `npm run build` → built.

## Verification

**1. Tests**
```
$ php artisan test --filter="Shop|FruitVeg"
Tests:    2 failed, 217 passed          ← the 2 are FruitVegLabelPrintingTest, known
$ php artisan test
Tests:    15 failed, 636 passed (2721 assertions)
```
The identical set. 636 = 634 + 2.

**2. Contract**
```
route( in fv-harvest.js        → 0
<script|<style in shop views   → 0
design block cmp               → IDENTICAL   (no stylesheet change)
```

**3. `git diff app/Http/Controllers/HarvestController.php`** — five hunks, of which
**two are mine**, both inside `rows()`. The other three are cycle 17f's
`saveRow()`/`imageUrl()` work, still uncommitted in this tree. I read the diff
rather than reporting the file as "rows() only".

**4. Manual, dev app — the screen exercised, and deliberately stopped short of
printing.** The plan reserves the real print for the owner and I have kept to that:
no job was sent to the printer at any point.

Dev has three of Jon's products with active labels. The label payload arrives:
`Rocket Mossfield 100g → { Rocket_Mossfield, 112.6 × 75.1 mm }`.

There was already a harvest row for today — "Mixed Salad 100g, 1 unit", logged by
`jonathanE` at 15:49, after my last cycle's cleanup. **Not mine, and left alone.**
It was also the ideal subject, because tapping a Today row's printer button opens
the card without creating any data:

```
tap the printer icon on the Today row →
  print = { label: { id 18, "Mixed Salad", 112.6 × 75.1 }, product: "Mixed Salad 100g",
            copies: 1, sending: false, result: null }
  card: "Print labels" ×, "Mixed Salad 100g · Mixed Salad",
        "Check the printer has 112.6 × 75.1 mm labels loaded.",
        COPIES stepper at 1, "Skip" and "Print 1 label"
+ twice  → copies 3, button "Print 3 labels"     (plural)
− three  → copies 1, button "Print 1 label"      (singular, clamped at 1)
Skip     → print null, card hidden, the Today row unchanged at 1
```
Console clean throughout.

The two things only the owner can check are the printer actually producing the
labels, and the "Couldn't confirm the print job" path with the printer off. Both are
in the plan's manual list for them.

## Deviations

None. One addition the plan does not mention but the code needed: capturing the
amount into a local in `log()` before `typed` is cleared, described in step 2.

## Files changed

Mine, this cycle:
```
 M app/Http/Controllers/HarvestController.php     two lines in rows()
 M resources/js/shop/fv-harvest.js                the print offer
 M resources/views/shop/fv-harvest.blade.php      the card and the row button
 M tests/Feature/Shop/ShopFruitVegTest.php        label + print tests
 M docs/features/fruit-veg-system.md
 M docs/design/shop-mode/README.md
```
Those six files also carry cycles 17f and 19's work in some cases; cycles 17f, 17g
and 18 remain uncommitted in this tree alongside them.

**Not committed, not pushed, not deployed. Nothing was printed.**

## Notes for Planner

1. **Only 3 of Jon's 111 products have an active label on dev.** The offer is
   therefore rare in practice — most harvest logs will show no card at all, which
   is correct but means the feature will look like it is not working until someone
   checks which products have labels. Worth telling the owner which three they are
   (`Spinach_Mossfield`, `Mixed Salad`, `Rocket_Mossfield`) so the first test is
   done on one that can work.

2. **The copies default rounds a weight to a count of labels**, which is the
   office's rule and is right for punnets and bags, but 0.4 kg rounds to **zero**
   and is then clamped to 1. That is the sensible floor, and it is worth knowing
   that a small weight always offers one label rather than none.

3. **A print failure is reported twice** — a toast and a line on the card. That is
   deliberate: the toast fades, and the card has to explain why it is still open.
   If the Planner would rather have one, the card line is the one to keep.

4. **The office harvest page and the Shop screen now offer the same print through
   the same endpoint but with different copy-count UIs** (a number input there, a
   stepper here). No behavioural difference; noting it so the two are not assumed
   to share code.
