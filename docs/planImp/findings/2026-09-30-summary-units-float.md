# Finding: "Units to add" shows a floating-point tail on the Shop delivery summary

**Seen:** production, Udea session `0aa9a0eb…` (uploaded 2026-09-28 15:03), Shop summary page: "Units to add 1360.4110000000003".

**Cause (verified on production, read-only):** the session has 223 scanned rows summing to exactly 1360.411 — 1346 whole units plus six weighed lines in kg (1.94, 3.74, 1.9, 2.28, 0.111, 4.44 = 14.411). `resources/js/shop/delivery-summary.js` `get unitsToAdd()` sums `r.scanned` with `reduce` and renders the raw double, so binary rounding shows through. The data is correct; only the display is wrong.

**Not a completion risk:** `DeliveryLegacyController::completeDelivery()` increments each product's `STOCKCURRENT.UNITS` by that row's own `scanned` figure (`->increment('UNITS', $item->scanned)` per row); no total is written anywhere.

**Fix (next cycle):** format the total the way the scan page formats stock — whole numbers plain, otherwise up to 3 dp trimmed (`stockText()` in `delivery-scan.js` already does this; share it or copy it). Better still, since the rows do not know their unit: the `items` JSON could carry `weighed` (POS `PRODUCTS.ISSCALE` or the category) so the summary can say "1346 units · 14.411 kg". The rounding is the minimum; the split is the nicer version.
