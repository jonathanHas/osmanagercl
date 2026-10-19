# Finding — deposit reconciliation is empty on multi-PDF imports

Date: 2026-10-19
Found during: deliveries side plan `price-check` (Implementer, step 9; confirmed by the deliveries Planner).
Status: open, for the deposit Planner.

## What happened

Dev delivery 168 (four Udea PDFs, orders 4804899 / 4804901 / 4804902 / 4804914)
has `import_data.deposit_reconciliation = {"line_units": [], "section": {…11 codes…},
"warnings": [], …}` although order 4804899 alone parses with
`Deposit code 313: product lines total 24 units, barrels section says 36`.
The Barrels/Deposits block on `/deliveries/168` therefore shows no mismatch and
no "Deposit codes on product lines" table. Delivery 166 (same files, before the
side plan) had the same empty reconciliation, so this predates it.

## Why (verified in code)

Two gaps in the multi-file path, `DeliveryParsingService::parseMultipleDeliveryPdfs()`:

1. It merges `barrels.items` per file but never carries `barrels.line_units`
   (`grep line_units app/Services/DeliveryParsingService.php` finds nothing), so
   `DeliveryService::recordDepositReconciliation()` receives `line_units = []`.
2. It prefixes every warning with `[filename] `, and
   `recordDepositReconciliation()` keeps only warnings that
   `str_starts_with($w, 'Deposit code')`, so none survive.

Single-file imports (`parseDeliveryPdf()`) are unaffected: no prefix, and the
parser's `barrels.line_units` passes straight through.

## Options

- In `parseMultipleDeliveryPdfs()`, sum `line_units` per code across files into
  the merged `barrels`, and in `recordDepositReconciliation()` match the
  warning with `preg_match('/^(\[[^\]]*\]\s*)?Deposit code/', $w)` (the
  delivery page already uses that pattern since the price-check plan).
- Or reconcile per file and store a list; more work, and the page shows one table.

## Recommendation

The first option: two small changes, one test on `parseMultipleDeliveryPdfs()`
with two fake parser results, one on `recordDepositReconciliation()` with a
prefixed warning. Then re-check `/deliveries/168` on dev: `1 deposit mismatch`
and the 313 row `24` vs `36`.

## What it does not affect

The deposit evidence service (`afterDeliveryImport()` reads the per-line
`barrel_code` on `delivery_items`, which is stored correctly: 168 has the codes).
Until fixed, the deliveries price-check plan shows deposit warnings in the
delivery page's Parser warnings block whenever the deposit block has none.
