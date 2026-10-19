# Finding — parser warnings on Udea order 4804899, and `x-collapse` on the delivery show page

Date: 2026-10-19
Found during: side plan `plan-dev-parser-venv.md` (dev venv rebuild), step 4 and step 6.
Status: items 1 and 3 folded into `docs/deliveries/plan-price-check.md`
(Planner, 2026-10-19 evening); item 2 stays open for the deposit track. Move this
file into that side plan's archive folder on acceptance.

## What happened

Parsing `~/Downloads/Order_4804899.pdf` (Udea, 147 lines, grand total 3,238.27,
`totals_match: True`) succeeded with two parser warnings:

1. `Price mismatch for 5004102: 1×0.49×401.0=196.49, actual=19.60`
2. `Deposit code 313: product lines total 24 units, barrels section says 36`

The import itself is fine (delivery 165 on dev). Both warnings are about the
content of this order, not the environment.

Separately, the delivery show page (`/deliveries/165`) logs two Alpine warnings
in the browser console: `x-collapse` is used on two elements without the
Collapse plugin loaded.

## Follow-up (Planner, 2026-10-19 evening)

Item 1 confirmed: the raw line is `5004102 1 1 4012,50gram …`; pdfplumber glued
the case size 40 to the content 12,50 gram and the fallback regex read 401 +
2,50. The warning was correct; the item still imported as 401 units at €0.0489
because nothing after the preview keeps `price_valid`. See the plan for the
repair rule and the evidence sweep over all stored Udea dockets.

## Why (first look, unverified)

1. Line `5004102` looks like a weight- or volume-priced line where the parser
   reads `401.0` as a quantity multiplier. The stated line total (19.60) is
   40 × 0.49, so a figure of 401 or 40.1 is being split or scaled wrongly by
   `parsers/delivery_udea.py`. Needs the PDF line in front of someone.
2. The 313 count compares per-line deposit codes against the "Barrels
   delivered" section. The deposit track already knows pdfplumber garbles the
   letters before the code on some lines (deposit README, cycle 2 rev 3 added
   a fallback scoped to the barrels section). A 12-unit gap on one order is
   worth checking against that fallback: either lines are missed, or the
   barrels section counts packaging that is not on product lines.
3. `x-collapse` needs `@alpinejs/collapse` registered; without it the
   directive is ignored, so the sections render open and do not animate.

## Options

- Treat 1 and 2 as a small parser cycle: reproduce from the PDF, add a fixture
  line to `scripts/invoice-parser/tests/test_delivery_udea.py`, fix.
- Treat 2 inside the deposit track (cycle 5 or later), since the count feeds
  `import_data.deposit_reconciliation` shown on the delivery page.
- Treat 3 as a one-line fix in `resources/js/app.js` (register the plugin) or
  swap the two `x-collapse` uses for `x-show`, in any office-page cycle.

## Recommendation

Fold 1 and 2 into the next deliveries cycle (after reviewing cycle 3) with the
PDF as the fixture; let the deposit Planner see item 2 first. Item 3 goes with
whichever cycle next touches `resources/views/deliveries/show.blade.php`.

## What it does not affect

The dev venv fix, the import of this order, the 147 items or the totals.
