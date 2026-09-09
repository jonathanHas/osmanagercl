# Christmas Comparison Feature

**Status:** ✅ Implemented (December 2025)
**Module:** Order Management
**Related Files:** Order generation, Sales analytics

---

## Overview

The Christmas Comparison feature allows users to compare recent sales data with historical Christmas period sales when generating orders. This helps ensure adequate stock levels during peak seasonal demand by showing side-by-side comparisons and automatically using the higher of regular or Christmas-based suggestions.

## Business Problem

During the Christmas season, many products experience significant sales increases compared to regular periods. Using only recent sales history (e.g., 8-week average) can lead to understocking during the holiday rush, resulting in:
- Lost sales opportunities
- Customer dissatisfaction
- Emergency reordering at higher costs

The Christmas Comparison feature solves this by incorporating historical Christmas sales patterns into order calculations.

---

## Key Features

### 1. Flexible Date Range Selection

- **User-Selectable Period:** Choose custom start/end dates for the Christmas comparison window (default: Dec 10-26)
- **Multiple Orders:** Create different orders for different phases (early shopping, peak week, last-minute rush)
- **Multi-Year Comparison:** Select 1 or 2 previous years to compare (e.g., 2024 and 2023)

### 2. Dual-Window Comparison

- **Recent Sales:** Standard 8-week (or custom) recent sales average
- **Christmas Sales:** Historical Christmas period sales from selected years
- **Side-by-Side Display:** Visual comparison showing both recommendations
- **Automatic Selection:** System automatically uses the higher quantity (max mode)

### 3. Visual Timeline Charts

- **Extended Timeline:** Charts show recent sales weeks + current/after stock + Christmas historical weeks
- **Multiple Datasets:** Distinct lines for recent sales, Christmas 2024, Christmas 2023
- **Interactive Legend:** Toggle datasets on/off
- **Hover Tooltips:** Show exact quantities for all data points

### 4. December Banner Prompt

- **Auto-Detection:** When creating an order with December delivery, shows promotional banner
- **One-Click Enable:** Quick toggle to enable Christmas comparison mode
- **Dismissible:** Can be closed without enabling

### 5. Comparison Stats Panel

For each product, displays:
- **Recent column:** Weekly average and suggested quantity from recent sales
- **Christmas column:** Weekly average and suggested quantity from historical Christmas periods
- **Green checkmark:** Indicates which suggestion was selected (higher)
- **Delta indicator:** Shows difference between suggestions if >5 units
- **Visual styling:** Orange for increase, green for decrease

---

## How It Works

### Order Creation Flow

1. **Enable Feature:** User creates new order and enables "Christmas comparison" toggle
2. **Configure Window:** Select date range (e.g., Dec 10-26) and years to compare (e.g., 2024, 2023)
3. **Generate Order:** System calculates both regular and Christmas suggestions for each product
4. **Review Page:** Navigate to Christmas review page showing enhanced graphs and comparison data
5. **Adjust & Complete:** User can override quantities as normal, then complete order

### Calculation Logic

```
For each product:
  1. Calculate regular suggestion:
     - Fetch weekly sales for past N weeks (default 8)
     - Average weekly sales = sum(weeks) / count(weeks)
     - Regular suggestion = (avg_weekly × coverage_weeks) - current_stock

  2. Calculate Christmas suggestion:
     - Fetch sales for selected date range in each selected year
     - For each year: weekly_avg = total_sales / (days / 7)
     - Christmas suggestion = (avg_christmas_weekly × coverage_weeks) - current_stock

  3. Apply max mode:
     - Selected quantity = max(regular_suggestion, christmas_suggestion)
     - Store both values in context_data for display

  4. Continue with normal order processing:
     - Apply safety factors
     - Round to case quantities
     - Apply adjustments
```

### Data Storage

All Christmas configuration is stored in JSON fields (no new tables required):

**`order_sessions.christmas_window_config`:**
```json
{
  "enabled": true,
  "comparison_years": [2024, 2023],
  "date_range": {
    "start": "2024-12-10",
    "end": "2024-12-26"
  },
  "calculation_mode": "max"
}
```

**`order_items.context_data['christmas_comparison']`:**
```json
{
  "enabled": true,
  "years": [2024, 2023],
  "date_range": {"start": "2024-12-10", "end": "2024-12-26"},
  "christmas_windows": [
    {
      "year": 2024,
      "date_range": "Dec 10 - Dec 26",
      "weekly_breakdown": [
        {"label": "W1", "units": 45, "date_range": "Dec 10-16"},
        {"label": "W2", "units": 52, "date_range": "Dec 17-23"}
      ],
      "total_units": 97,
      "weekly_average": 48.5,
      "days": 17
    }
  ],
  "stats": {
    "regular_weekly_avg": 11.1,
    "christmas_weekly_avg": 12.4,
    "regular_suggested": 24,
    "christmas_suggested": 26,
    "selected_quantity": 26,
    "selected_mode": "christmas",
    "delta": 2
  }
}
```

---

## User Interface

### Order Creation Form

**Location:** `/orders/create`

**Christmas Comparison Section:**
- Checkbox toggle: "🎄 Show Christmas comparison data"
- Collapsible settings panel with:
  - Date range pickers (start/end)
  - Year checkboxes (2024, 2023)
  - Info box explaining max mode behavior

**December Banner (conditional):**
- Appears when order delivery date is in December
- Gradient purple/pink background with Christmas tree emoji
- "Enable Christmas Mode" button
- Dismissible close button

### Christmas Review Page

**Location:** `/orders/{id}` (when `christmas_comparison_enabled = true`)

**Files:**
- `show-christmas.blade.php` - Main review page (duplicate of show.blade.php)
- `partials/review-table-christmas.blade.php` - Enhanced table partial

**Key UI Elements:**

1. **Summary Cards:** Same as regular review (total items, requires review, standard, safe counts)

2. **Comparison Stats Panel** (per product):
   - Two-column layout: Recent vs Christmas
   - Weekly averages displayed prominently
   - Suggested quantities with green checkmark on selected
   - Delta indicator for significant differences

3. **Enhanced Chart:**
   - **Width:** 640px (2x larger than regular)
   - **Height:** 220px (2x larger than regular)
   - **Timeline:** Recent weeks | Current/After | Christmas 2024 | Christmas 2023
   - **Legend:** Enabled (shows all datasets)
   - **Tooltips:** Display quantities for all datasets on hover
   - **Colors:** Blue (recent), Purple (2024), Pink (2023)

---

## Technical Implementation

### Files Created

1. **`/resources/views/orders/partials/review-table-christmas.blade.php`**
   - Duplicate of `review-table.blade.php` with Christmas enhancements
   - Adds comparison stats panel before "Suggested" display
   - Enhanced Chart.js with extended timeline and multiple datasets
   - Larger graph dimensions (640x220px vs 320x110px)

2. **`/resources/views/orders/show-christmas.blade.php`**
   - Duplicate of `show.blade.php`
   - Includes `review-table-christmas` partial instead of `review-table`
   - Used only when `christmas_comparison_enabled = true`

3. **`/database/migrations/xxxx_add_christmas_comparison_to_order_sessions.php`**
   - Adds `christmas_comparison_enabled` BOOLEAN column
   - Adds `christmas_window_config` JSON column

### Files Modified

1. **`/app/Services/OrderService.php`**
   - Enhanced `generateOrderSuggestions()` to pass Christmas options
   - Enhanced `buildOrderItemsForSession()` to pass Christmas options
   - Enhanced `calculateProductSuggestion()` with Christmas comparison logic
   - Added max mode calculation (selects higher quantity)

2. **`/app/Repositories/SalesRepository.php`**
   - Added `getChristmasWindowComparison()` method
   - Fetches sales data for custom date ranges across multiple years
   - Groups daily sales into weekly breakdowns for visualization

3. **`/app/Http/Controllers/OrderController.php`**
   - Modified `store()` to save Christmas configuration
   - Added `showChristmasReview()` method for Christmas review page
   - Added routing logic: Christmas orders → Christmas review page

4. **`/app/Models/OrderSession.php`**
   - Added `christmas_window_config` to `$casts` for JSON handling

5. **`/resources/views/orders/create.blade.php`**
   - Added Christmas comparison toggle section
   - Added date range pickers and year checkboxes
   - Added December banner (conditional on delivery date)
   - Fixed default years to [current_year - 1, current_year - 2]

### Key Methods

**SalesRepository::getChristmasWindowComparison()**
```php
public function getChristmasWindowComparison(
    string $productId,
    array $years,          // [2024, 2023]
    Carbon $startDate,     // e.g., Dec 10
    Carbon $endDate        // e.g., Dec 26
): array
```

Returns array of windows with weekly breakdowns, totals, and averages for each year.

**OrderService::calculateProductSuggestion()** (enhanced)
- Now accepts `christmas_comparison_enabled` and `christmas_window_config` options
- Calculates both regular and Christmas suggestions
- Applies max mode (selects higher quantity)
- Stores full comparison data in `context_data['christmas_comparison']`

---

## Performance Considerations

### Current Performance (December 2025)

**✅ Bulk Fetching Implemented:**
The performance optimizations have been applied. Christmas comparison queries are now bulk-fetched for all products at once.

| Order Size | Previous Time | Current Time | Improvement |
|------------|---------------|--------------|-------------|
| Large (1,400+ products) with Christmas | 149+ seconds (timeout) | **~22 seconds** | **6.6x faster** |

### Query Impact (After Optimization)

**Without Christmas Comparison:**
- ~10-15 bulk queries total for all products

**With Christmas Comparison:**
- ~10-15 bulk queries + 1 bulk query per selected year
- Total: ~12-17 queries (not per-product!)

**Key Methods:**
- `getBulkChristmasWindowComparison(array $productIds, array $years, $startDate, $endDate)`
- Returns weekly breakdown data for chart visualization
- Uses `sales_daily_summary` table for fast aggregation
- Falls back to STOCKDIARY if summary data unavailable

### Optimization Implemented

1. **✅ Bulk Fetching:** Uses `whereIn('product_id', $productIds)` to fetch all Christmas data in 1-2 queries
2. **✅ Pre-aggregated Tables:** Uses `sales_daily_summary` instead of raw STOCKDIARY
3. **✅ Collection groupBy():** Data grouped once upfront, then accessed via O(1) hashmap lookups
4. **✅ Weekly Breakdown Included:** Chart visualization data pre-fetched with totals

---

## Testing

### Test Scenarios

1. **Normal Order (No Christmas):**
   - Create order without enabling Christmas toggle
   - Should route to regular review page
   - No Christmas data in context_data

2. **Christmas Order - Single Year:**
   - Enable Christmas comparison
   - Select only 2024
   - Verify calculation uses 2024 data
   - Verify chart shows only one Christmas dataset

3. **Christmas Order - Multiple Years:**
   - Enable Christmas comparison
   - Select 2024 and 2023
   - Verify calculation averages both years
   - Verify chart shows both datasets

4. **Edge Cases:**
   - Product with no Christmas sales data → Should fall back to regular calculation
   - New product (no history) → Should show regular calculation only
   - Christmas suggestion lower than regular → Should use regular (max mode)

5. **UI/UX:**
   - December banner appears for December orders
   - Christmas toggle shows/hides settings panel
   - Charts display at 640x220px (2x larger)
   - Tooltips show quantities on hover
   - Legend toggles datasets correctly

### Manual Testing

1. Navigate to `/orders/create`
2. Select December delivery date → December banner should appear
3. Enable Christmas comparison toggle
4. Select date range: Dec 10 to Dec 26
5. Select years: 2024 and 2023
6. Generate order
7. Review page should show:
   - Larger graphs (640x220px)
   - Comparison stats panels
   - Extended timeline with Christmas data
   - Tooltips on hover
   - Legend with 3+ datasets

---

## Safety & Risk Mitigation

### Zero-Risk Deployment Strategy

**Separate Files Approach:**
- Created `show-christmas.blade.php` and `review-table-christmas.blade.php` as duplicates
- Original `show.blade.php` and `review-table.blade.php` remain **untouched**
- Christmas orders use Christmas files, regular orders use original files
- No risk to existing order workflow

**Opt-In Design:**
- Feature is off by default
- Must explicitly enable Christmas comparison toggle
- Normal ordering remains unchanged

**Graceful Fallback:**
- If Christmas data missing → Falls back to regular calculation
- If Christmas suggestion lower → Uses regular (max mode ensures never under-ordering)
- If feature disabled mid-order → Can still view/complete order normally

### Rollback Plan

If issues arise:
1. Disable Christmas toggle in create form (comment out section)
2. Remove route to Christmas review page
3. System reverts to normal workflow
4. No data loss (Christmas data stored in JSON, doesn't affect regular fields)

---

## Known Limitations

1. **Desktop Only:** Graph sizing optimized for desktop (1366px+ screens)
2. **No Responsive Graphs:** Charts use fixed dimensions (640x220px)
3. **Max Mode Only:** Currently only supports "use higher quantity" mode (no weighted blending)
4. **Manual Year Selection:** Users must manually select which years to compare
5. **Date Range Manual:** No "last year same period" auto-detection

---

## Future Enhancements

### Potential Improvements

1. **Responsive Graphs:** Add breakpoints for tablet/mobile viewing
2. **Smart Year Selection:** Auto-select previous N years
3. **Date Range Presets:** Quick buttons for "Full December", "Peak Week", "Last Minute"
4. **Calculation Modes:** Add weighted blending option (e.g., 70% Christmas, 30% recent)
5. **Confidence Scoring:** Show confidence levels based on data consistency
6. **Pattern Analysis:** Auto-detect products with significant Christmas uplift
7. **Admin Dashboard:** View Christmas patterns across all products
8. **Export Comparison:** Export Christmas comparison data to CSV
9. **Other Seasonal Periods:** Extend to Easter, summer holidays, etc.

---

## Related Features

- [Order Generation](./order-generation.md) - Core order generation system
- [Sales Analytics](../sales-accounting-report.md) - Sales data and reporting
- Product Order Settings - Per-product configuration

---

## Changelog

### December 2025 - Performance Optimization

**Performance Improvements:**
- Bulk pre-fetching for all Christmas data (eliminates N+1 queries)
- Added `getBulkChristmasWindowComparison()` method for batch processing
- Weekly breakdown data now included in bulk fetch for chart visualization
- Collection groupBy() optimization for O(1) hashmap lookups
- Large orders (1,400+ products) now complete in ~22 seconds (previously 149+ seconds or timeout)
- Fixed chart visualization issue where Christmas graph lines weren't rendering

**Files Modified:**
- `app/Repositories/SalesRepository.php` - Added bulk Christmas comparison method with weekly breakdown
- `app/Services/OrderService.php` - Integrated bulk pre-fetching in order generation loop

### December 2025 - Initial Implementation

**Added:**
- Christmas comparison toggle in order creation form
- Flexible date range selection (start/end dates)
- Multi-year comparison (select 2+ years)
- Dual-window calculation (recent vs Christmas)
- Max mode (automatically use higher quantity)
- Enhanced review page with comparison stats panels
- Extended timeline charts with multiple datasets
- Larger graph dimensions (2x size: 640x220px)
- December banner with auto-suggestion
- Tooltip support for all datasets

**Technical:**
- New migration: `christmas_comparison_enabled`, `christmas_window_config` columns
- New files: `show-christmas.blade.php`, `review-table-christmas.blade.php`
- Enhanced: `OrderService`, `SalesRepository`, `OrderController`
- Zero-risk deployment: Separate Christmas files, original files untouched

---

## December 2025 Enhancements

### Sales Chart Modal (2025-12-09)
The Christmas review page now includes the same expandable sales chart modal as the regular order review:
- **Click to Expand**: Click any inline chart to open a full-sized sales history modal
- **Date Range Controls**: Navigate sales history with +/- 1 month and +/- 2 months buttons
- **Statistics Bar**: Shows total sales, peak week, average weekly sales, and active weeks
- **Range Limits**: View from 4 weeks to 2 years of sales history

### Supplier Website Links (2025-12-09)
Direct links to view products on supplier websites:
- **"View →" Link**: Appears next to supplier code for Udea and Independent Health Foods products
- **Quick Access**: Opens supplier's product search page in new tab
- **Both Pages**: Available on regular and Christmas review pages

### Destock/Restock Toggle (2025-12-09)
Quick stock management controls added to order review:
- **Destock Button**: Red button to remove product from stock management
- **Restock Button**: Green button to add product back (after destocking)
- **Confirmation Dialog**: Warning message before action with explanation
- **Visual Toggle**: Button changes color and label based on current state

---

## Support

For questions or issues with the Christmas Comparison feature:

1. Check [Known Issues](../../development/known-issues.md)
2. Review [Troubleshooting Guide](../../troubleshooting/index.md)
3. See [Order Generation Docs](./order-generation.md) for general order system
4. Contact system administrator

---

**Last Updated:** December 2025
**Feature Status:** ✅ Production Ready
**Maintained By:** Development Team
