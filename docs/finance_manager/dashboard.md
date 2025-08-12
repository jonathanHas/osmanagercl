# Financial Dashboard Documentation

## Overview

The Financial Dashboard provides a comprehensive view of daily business financial health at `/management/financial/dashboard`.

## Dashboard Components

### 1. Header Controls
- **Date Selector**: View any historical date's metrics
- **Today Button**: Quick return to current date
- **Auto-refresh**: Updates every 5 minutes

### 2. Alert System
Displays color-coded alerts for:
- 🔴 **Danger** (Red): Critical issues requiring immediate attention
- 🟡 **Warning** (Yellow): Issues needing review
- 🔵 **Info** (Blue): Informational notices

Common alerts:
- "Cash not reconciled for X days"
- "Large cash variance detected: €XX.XX"
- "Sales below €500 threshold"

### 3. Primary KPI Cards

#### Today's Sales
```
┌─────────────────────────┐
│ Today's Sales           │
│ €1,234.56      ↑12.3%   │
│ 145 transactions        │
└─────────────────────────┘
```
- **Main Value**: Net sales (sales - refunds)
- **Percentage**: Change from yesterday
- **Sub-metric**: Transaction count

#### Cash Position
```
┌─────────────────────────┐
│ Cash Position           │
│ €2,345.67  2d unreconciled│
│ Float: €500.00          │
└─────────────────────────┘
```
- **Main Value**: Expected cash on hand
- **Warning**: Days since last count
- **Sub-metric**: Current float amount

#### Week to Date
```
┌─────────────────────────┐
│ Week to Date            │
│ €8,765.43               │
│ Avg: €1,252.20/day      │
└─────────────────────────┘
```
- **Main Value**: Total weekly sales
- **Sub-metric**: Daily average

#### Month to Date
```
┌─────────────────────────┐
│ Month to Date           │
│ €34,567.89    ↑8.5%     │
│ vs €31,852.10 last month│
└─────────────────────────┘
```
- **Main Value**: Total monthly sales
- **Percentage**: Growth vs last month
- **Sub-metric**: Last month comparison

### 4. Payment Methods Breakdown

Visual representation of payment types:
```
Cash    ████████████░░░░  €650.00  (52%)
Card    ██████░░░░░░░░░░  €475.00  (38%)
Account ██░░░░░░░░░░░░░░  €125.00  (10%)
```

Includes:
- Progress bars showing proportions
- Actual amounts
- Percentage of total
- Average transaction value

### 5. Cash Flow Summary

Three-tier cash flow visualization:
```
┌─────────────────────────────────┐
│ Cash In (Sales)      +€650.00   │
├─────────────────────────────────┤
│ Cash Out (Suppliers) -€150.00   │
├─────────────────────────────────┤
│ Net Cash             +€500.00   │
├─────────────────────────────────┤
│ Cash Variance        +€12.50    │ (if reconciled)
└─────────────────────────────────┘
```

Color coding:
- Green: Positive/Income
- Red: Negative/Expenses
- Blue: Net position
- Yellow/Green: Variance status

### 6. Trend Charts

#### 7-Day Sales Trend
Bar chart showing daily sales for the past week:
```
€1500 │     ██
€1000 │ ██  ██  ██
€500  │ ██  ██  ██  ██
€0    └─────────────────
      M  T  W  T  F  S  S
```

#### 7-Day Cash Flow
Positive/negative cash flow visualization:
```
+€500 │     ██      ██
   0  ├─────────────────
-€500 │ ██      ██
      M  T  W  T  F  S  S
```

### 7. Action Items Section

#### Pending Tasks
Shows unreconciled days count with direct action links:
```
┌──────────────────────────┐
│ Pending Tasks         3  │
├──────────────────────────┤
│ 3 days need reconciliation│
│ Click to reconcile →     │
└──────────────────────────┘
```

#### Outstanding
Invoice and payment tracking:
```
┌──────────────────────────┐
│ Outstanding           5  │
├──────────────────────────┤
│ €2,345.00 outstanding   │
│ Oldest: 15 days         │
└──────────────────────────┘
```

#### Quick Actions
One-click access buttons:
- **Count Cash**: Opens reconciliation form
- **View Receipts**: Goes to till review
- **Export Report**: Generates CSV export

## Data Sources

### Real-time Data
- Current day transactions
- Live cash position
- Active alerts

### Cached Data
- Historical metrics (5-minute cache)
- Trend calculations
- Summary statistics

### Database Queries

#### Daily Metrics Query
```php
DB::connection('pos')
    ->table('RECEIPTS as r')
    ->join('PAYMENTS as p', 'r.ID', '=', 'p.RECEIPT')
    ->whereDate('r.DATENEW', $date)
    ->selectRaw('...')
```

#### Cash Position Calculation
```php
$lastFloat + $salesSinceCount - $paymentsSinceCount
```

## User Interactions

### Date Navigation
- Click date input to select any date
- "Today" button for quick reset
- URL parameter: `?date=YYYY-MM-DD`

### Alert Actions
- Click alert to view details
- Direct links to resolution pages
- Dismissible after action taken

### Filtering
- Click payment type cards to filter
- Hover for detailed tooltips
- Click trends for daily detail

## Performance Metrics

### Load Times
- Initial load: < 1 second
- Date change: < 500ms
- Refresh: < 300ms

### Query Optimization
- Indexed date columns
- Efficient JOIN operations
- Aggregate calculations in database

## Customization

### Thresholds
Configurable in controller:
```php
// Low sales threshold
if ($todayMetrics['net_sales'] < 500) // Adjust value

// Large variance threshold  
if (abs($lastReconciliation->variance) > 50) // Adjust value

// Reconciliation warning days
if ($daysSince > 1) // Adjust days
```

### Display Options
- Currency format
- Date format
- Color schemes (dark/light mode)

## Mobile Responsiveness

The dashboard adapts to screen sizes:
- **Desktop**: Full 4-column layout
- **Tablet**: 2-column layout
- **Mobile**: Single column stack

## Export Capabilities

### Available Formats
- CSV export of daily metrics
- PDF reports (coming soon)
- Excel downloads (coming soon)

### Export Contents
- Transaction summary
- Payment breakdown
- Cash flow analysis
- Variance reports
- Trend data

## Troubleshooting

### Common Issues

#### No Data Showing
- Check date has transactions
- Verify POS connection
- Check user permissions

#### Incorrect Calculations
- Verify reconciliation completed
- Check for refunds/voids
- Validate payment types

#### Slow Loading
- Clear application cache
- Check database indexes
- Review query performance

## Future Enhancements

### Planned Features
- Real-time updates via WebSocket
- Predictive analytics
- Budget vs actual tracking
- Multi-location support
- Custom KPI configuration
- Email/SMS alerts
- API access for external tools