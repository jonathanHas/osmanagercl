# Order Comparison & Difference Orders

**Status:** ✅ Implemented (comparison July 2026, difference orders September 2026)
**Module:** Order Management
**Related Files:** `OrderController::compare()` / `storeDifference()`, `OrderService::differenceItems()`, `resources/views/orders/compare.blade.php`

---

## Overview

Two orders for the same supplier can be placed side by side to see what one holds that the other
does not, and the gap between them can then be turned into a new draft order in one click.

The workflow it exists for is **trimming an oversized order**: generate a large order, generate a
smaller one against a shorter coverage window, compare the two to see what the smaller one does
without, then place that shortfall as a separate order — next week, or with a second supplier run.

---

## Business Problem

Order generation produces a single answer for a single coverage window. In practice a buyer often
wants two: "what would I order for two weeks?" against "what would I order for one?" — then a way
to act on the gap without transcribing dozens of products by hand.

Before this, the comparison could be seen but not used. Acting on it meant reading a table on one
screen and re-keying products into the search-add box on another.

---

## Using It

### Comparing two orders

1. Go to `/orders`.
2. Tick the checkbox on **two** orders (first column of the table).
3. A bar appears at the foot of the page — click **Compare**.

The selection is capped at two and evicts oldest-first, so ticking a third replaces the first.

### The comparison page

`/orders/compare?a=<id>&b=<id>` splits the two sessions into four sections:

| Section | Contents |
|---|---|
| **Only in Order A** | Products A orders that B does not |
| **Only in Order B** | Products B orders that A does not |
| **In both, quantity changed** | Ordered in both at different quantities, with the delta and a flag when the case size differs between sessions |
| **Identical in both orders** | Collapsed by default — noise against the "what changed?" question |

Each row shows the product name, supplier code, a sales sparkline, current stock and the ordered
quantity (in cases where the product is case-ordered).

Two warning banners appear when relevant: **different suppliers**, and **different sales history
windows** (each session snapshots its sales history over whatever `sales_history_weeks` was set to
at generation, so the two sets of sparklines can span different periods).

### Ordering the difference

The **Order the difference** card sits directly under the two order summaries and offers both
directions:

```
Order A − Order B                                    37 products
What order #300 orders over and above order #301 — 112 units, €254.80.

Delivery date  [ 2026-08-17 ]
[ Create draft order ]
```

Submitting creates a new draft order session and redirects to it.

---

## How the Quantities Are Derived

### The "ordered" predicate

Throughout the comparison, **"ordered" means `final_quantity > 0`**.

A session carries a candidate row for *every* product the supplier stocks, most of them at
quantity zero — session #1 holds 1,504 rows against 353 actual orders. Treating a row's existence
as an order would bury the comparison in noise, and would also make a product look "already
covered" when the other order merely considered it.

The predicate lives in exactly one place, `OrderService::orderedItemsByProduct()`, shared by the
comparison and the difference builder.

### The shortfall

For each product the source order (`from`) orders:

```
shortfall = from.final_quantity − to.final_quantity
```

with `to.final_quantity` treated as 0 when the other order does not order the product at all — so
only-in-A products come across in full. A shortfall below `0.001` produces no line; that is the
same epsilon `compare()` uses to split *changed* from *identical*, and that `OrderItem::wasAdjusted()`
uses.

### Case rounding

Case products round the shortfall **up to whole cases**, as everywhere else in `OrderService`:

```php
$finalCases    = ceil($shortfall / $caseUnits);
$finalQuantity = $finalCases * $caseUnits;
```

This can exceed the raw shortfall. A 25-unit gap on a 12-pack becomes **3 cases (36 units)** —
which is the honest answer, because part of a case cannot be bought. Capping it would write a
non-case-aligned `final_quantity` into a brand-new order and break the invariant every other
write path maintains. The raw figure is preserved in `context_data.derived_from.raw_shortfall`.

Unit products (`case_units = 1`) keep the raw shortfall unrounded, matching the unit branch of
`updateOrderItemQuantity()`.

### When the two orders disagree on case size

`case_units` is a **per-session snapshot** and can drift between generations; units are the source
of truth. So the delta is computed in units and rounded with the **source** order's case size.

Worked example: `from` orders 24 units at 12/case, `to` orders 10 units at 6/case. The gap is 14
units, which against a 12-pack is 2 cases — **24 units**, the whole of the source quantity. For
coarse case sizes a difference order can approach the size of the order it came from. The card's
unit total reflects the rounded figure, so what the button promises is what gets created.

---

## What the New Order Inherits

### Session

| Field | Value |
|---|---|
| `user_id` | The person clicking, not the source order's creator |
| `supplier_id`, `sales_history_weeks` | Copied from the source order |
| `order_date` | Set on the form (see below) |
| `coverage_days` | Copied |
| `coverage_ends_on` | **Re-anchored** to the new delivery date, as `duplicate()` does — the source order's window may already have closed |
| `coverage_overrides` | **Dropped** — per-category override dates are anchored to the source order's delivery date and are stale against the new one |
| `christmas_comparison_enabled` | **Forced false** — `show()` diverts to the Christmas review whenever it is set, and that page frames items against a Christmas window, the wrong lens for a shortfall order |
| `status` | `draft` |
| `notes` | Provenance string, rendered in the order header |

**The delivery date is set on the form** because `OrderController::update()` accepts only `notes` —
an order's date cannot be changed after creation. The field defaults to the source order's date.

### Items

Metadata is **copied, not recalculated**:

| Field | Value |
|---|---|
| `product_id`, `unit_cost`, `case_units`, `review_priority` | Copied from the source item |
| `context_data` | Copied verbatim, plus a `derived_from` key |
| `final_quantity` / `final_cases` | The rounded shortfall |
| `suggested_quantity` / `suggested_cases` | Equal to the final values, so rows do not read as already adjusted |
| `auto_approved` | **`false`** — nobody has approved the new quantity |
| `added_via_search` | **`false`** — these rows are the order's own content, not ad-hoc search additions |
| `adjustment_reason` | `null` |

Copying `context_data` is the cheapest correct option: it carries `case_units` / `is_case_product`
(which drive the case/unit split in the review table), `weekly_sales` (the sparklines),
`current_stock`, `avg_weekly_sales` and `supplier_code` (Udea pallet volumes). The values are a
snapshot from when the source order was generated — which is exactly the provenance wanted, since
that is what the buyer was looking at when the shortfall arose.

Provenance added per row:

```json
"derived_from": {
    "type": "order_difference",
    "from_quantity": 24.0,
    "to_quantity": 10.0,
    "raw_shortfall": 14.0
}
```

---

## Guards

| Condition | Behaviour |
|---|---|
| Different suppliers | The card renders an explanation instead of the forms, and the POST is refused server-side. An `OrderSession` belongs to exactly one supplier, so a cross-supplier difference has no valid `supplier_id`. |
| No shortfall in that direction | That side of the card says so instead of offering a button; a direct POST is refused with a flash error. |
| Same order on both sides | Rejected by validation (`different:to`). |
| Not signed in | Redirected to login — orders routes carry the bare `auth` middleware. |

Neither order needs to be editable: differencing a completed order is legitimate and reads nothing
mutable.

---

## Technical Notes

### Preview and write share one code path

`compare()` calls `OrderService::differenceItemsFor()` for both directions and passes the counts to
the view. Deriving them instead from the already-computed *Only in A* / *changed* collections would
be free, but would give the **raw** unit delta while the create action writes **case-rounded**
quantities — the button would promise 112 units and the resulting order would show 120. Running
both through the same code keeps them honest.

The two extra calls are pure in-memory work over collections `compare()` has already eager-loaded;
they add no queries.

### The write path avoids the POS connection

`storeDifference()` loads its sessions with the items relation constrained to
`final_quantity > 0` (using the existing index on that column) and does **not** eager-load
`items.product` the way `compare()` must. Sessions run to 1,500+ rows each carrying a multi-KB
`context_data` blob, and nothing in the write path needs product data.

The whole write is wrapped in `DB::transaction()`.

### `total_items` reads differently

`OrderSession::updateTotals()` sets `total_items = items()->count()`, which on a generated order
counts every zero-quantity candidate row. A difference order contains **only** real lines, so its
Items column on `/orders` shows the true product count — it will look strikingly smaller than its
neighbours (e.g. 37 against 1,400). This is correct, not a bug.

### Eligibility filters are bypassed — deliberately

Generation only considers products that have a supplier link, a stocking record, and sales in the
last six months. Copying rows sidesteps all three, so a difference order can contain a product a
fresh generation would now exclude. That is the intent: it re-orders what the source order actually
ordered.

---

## Known Limitations

- **Regenerating a difference order destroys the difference.** Touching the category coverage
  controls calls `OrderService::regenerateOrderSession()`, which deletes all items and rebuilds from
  live suggestions. Nothing in the schema marks an order as derived, so this cannot be blocked
  without a migration; the `notes` string warns about it. If it becomes a real problem the fix is a
  `created_from` column and an early return in `regenerateOrderSession()`.
- **The delivery date cannot be changed afterwards** — `update()` accepts only `notes`. Hence the
  date field on the form.
- **`old('order_date')` repopulates both cards** after a validation failure, since both forms use
  the same field name. Harmless — only one form submits.

---

## Testing

| File | Covers |
|---|---|
| `tests/Unit/OrderDifferenceTest.php` | The arithmetic, against unsaved models — no database, no POS scaffolding. Full-quantity carryover, shortfall, equal/greater coverage, the epsilon, case rounding, the deliberate overshoot, disagreeing case sizes, missing case size, unadjusted/unapproved flags, context provenance. |
| `tests/Feature/OrderDifferenceCreationTest.php` | The HTTP round-trip: field mapping, re-anchored coverage, zero-quantity rows ignored on both sides, and all four guards. |

The unit test runs against unsaved models deliberately rather than merely for convenience: the
`decimal:3` / `decimal:2` casts return **strings** on an unsaved model exactly as they do on a
persisted one, so the string-vs-float hazard is exercised for real.

The feature test does **not** follow the redirect — rendering `orders.show` would pull in the
`PRODUCTS` table and the whole supplier eager-load chain on the POS connection.

---

## Related Features

- [Order Generation](./order-generation.md) — how the orders being compared are produced
- [Christmas Comparison](./christmas-comparison.md) — a different kind of comparison, against historical seasonal sales

---

## Changelog

- **2026-09-09** — Difference orders: both directions, case-aware rounding, copied item metadata,
  supplier and empty-difference guards. Flash messages and `notes` now render on the orders pages
  (neither did before, so redirect messages here — and `duplicate()`'s — arrived silently).
- **2026-07-15** — Order comparison page: four-way split, sparklines, supplier and sales-window
  warnings, selection UI on `/orders`.
