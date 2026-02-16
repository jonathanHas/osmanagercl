# Fix Missing Paperin Adjustment in RTD Submission Sales Figures

> **STATUS: IMPLEMENTED** (2026-02-16) — Both Tier 2 and Tier 3 fixes applied in `app/Models/RtdSubmission.php`.

## Problem
The RTD report at `/rtd/submissions/4/report` shows D1 (0% Home) as **136,281.85** instead of the correct **135,360.24**. The difference is exactly **921.61** — the paperin (gift voucher redemption) gross amount that should be deducted from 0% sales to prevent double-counting revenue.

## Root Cause
**File:** `app/Models/RtdSubmission.php`, method `aggregateSalesFromVatReturns()` (lines 72-182)

The paperin adjustment is correctly implemented in `VatReturnController::getSalesVatData()` (lines 183-205) but is missing/wrong in two of three code paths in the RTD aggregation:

| Tier | When Used | Paperin Handling | Status |
|------|-----------|-----------------|--------|
| **1** (lines 93-117) | VAT return has `sales_vat_data` | Trusts persisted data (should already be adjusted) | OK if data is fresh |
| **2** (lines 118-140) | VAT return exists but lacks `sales_vat_data` | **None — paperin included raw** | **BUG** |
| **3** (lines 143-164) | No VAT returns for period | Filters `payment_type != 'paperin'` (removes net per rate, not gross from 0%) | **WRONG** |

### Correct paperin logic (from `VatReturnController::getSalesVatData()`)
1. Include ALL payment types in the base sales query
2. Calculate paperin gross total: `SUM(gross_amount) WHERE payment_type = 'paperin'`
3. Deduct that gross total from the **0% net** figure only

This is correct because gift vouchers are sold at 0% VAT — when redeemed (paperin), the full gross face value must be backed out of 0% sales.

## Changes Required

### Fix Tier 2 (lines 118-140) in `app/Models/RtdSubmission.php`

Add paperin gross deduction after the existing `foreach` loop. Replace lines 118-140 with:

```php
} else {
    // Fallback: query sales_accounting_daily for this period
    $usedFallback = true;
    $periodSales = DB::table('sales_accounting_daily')
        ->select(
            'vat_rate',
            DB::raw('SUM(net_amount) as total_net')
        )
        ->whereBetween('sale_date', [
            $vatReturn->period_start->format('Y-m-d'),
            $vatReturn->period_end->format('Y-m-d'),
        ])
        ->groupBy('vat_rate')
        ->get();

    foreach ($periodSales as $row) {
        $key = $rateToKey[(string) $row->vat_rate] ?? null;

        if ($key !== null) {
            $sales[$key] += (float) $row->total_net;
        }
    }

    // Deduct paperin (gift voucher redemption) gross from 0% to prevent double-counting.
    // Vouchers are sold at 0% VAT; when redeemed, the full gross amount of items
    // purchased must be backed out of 0% sales.
    $paperinGross = (float) DB::table('sales_accounting_daily')
        ->where('payment_type', 'paperin')
        ->whereBetween('sale_date', [
            $vatReturn->period_start->format('Y-m-d'),
            $vatReturn->period_end->format('Y-m-d'),
        ])
        ->sum('gross_amount');

    if ($paperinGross > 0) {
        $sales['0'] -= $paperinGross;
    }
}
```

### Fix Tier 3 (lines 143-164) in `app/Models/RtdSubmission.php`

Replace the `WHERE payment_type != 'paperin'` filter with the proper gross-from-0% deduction. Replace lines 143-164 with:

```php
// If no VAT returns found, fall back to sales_accounting_daily directly
if ($vatReturns->isEmpty()) {
    $directSales = DB::table('sales_accounting_daily')
        ->select('vat_rate', DB::raw('SUM(net_amount) as total_net'))
        ->whereBetween('sale_date', [
            $this->period_start->format('Y-m-d'),
            $this->period_end->format('Y-m-d'),
        ])
        ->groupBy('vat_rate')
        ->get();

    if ($directSales->isNotEmpty()) {
        foreach ($directSales as $row) {
            $key = $rateToKey[(string) $row->vat_rate] ?? null;
            if ($key !== null) {
                $sales[$key] += (float) $row->total_net;
            }
        }

        // Deduct paperin (gift voucher redemption) gross from 0% to prevent double-counting.
        $paperinGross = (float) DB::table('sales_accounting_daily')
            ->where('payment_type', 'paperin')
            ->whereBetween('sale_date', [
                $this->period_start->format('Y-m-d'),
                $this->period_end->format('Y-m-d'),
            ])
            ->sum('gross_amount');

        if ($paperinGross > 0) {
            $sales['0'] -= $paperinGross;
        }

        $usedFallback = true;
    }
}
```

## Behavior Change Note for Tier 3

The old Tier 3 filtered out paperin rows entirely (removing net amounts from ALL rates). The new approach keeps paperin items at 13.5%/23% in the totals but deducts the full gross from 0% only. This is the accounting-correct behavior matching `VatReturnController`. Net effect for the example period:

- 0% goes **down** by an additional 304.87
- 13.5% goes **up** by 148.68
- 23% goes **up** by 110.67
- Overall sales total goes **down** by 45.52

Tier 3 only fires when there are NO VAT returns for the period, which is rare.

## Verification

1. Apply both code changes
2. Recalculate submission 4 (hit "Recalculate" on the show page or via tinker)
3. Check `/rtd/submissions/4/report` — D1 0% Home should show **135,360.24**
4. If still wrong after recalculation, the issue is in **Tier 1** (stale `sales_vat_data` on the VatReturn records that was saved before paperin code existed). In that case, refresh the VAT return data for the affected periods.

## Related Files
- `app/Models/RtdSubmission.php` — `aggregateSalesFromVatReturns()` (lines 72-182)
- `app/Http/Controllers/Management/VatReturnController.php` — `getSalesVatData()` (lines 160-269, reference implementation)
- `app/Http/Controllers/RtdSubmissionController.php` — `report()` (lines 150-218, display logic)
