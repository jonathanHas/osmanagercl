# Sales Accounting Report System

## Overview

The Sales Accounting Report system provides VAT-compliant sales analysis with proper separation of customer revenue, internal stock transfers, and gift voucher adjustments. The system generates comprehensive reports for accounting and tax purposes, with optimized performance using pre-aggregated data where available.

## Key Features

### ✅ **Accurate Financial Reporting**
- **Customer Revenue Calculation**: Properly excludes voucher sales from revenue calculations
- **VAT Compliance**: Accurate VAT breakdown by rates for tax returns
- **Stock Transfer Separation**: Internal movements excluded from revenue calculations
- **Gift Voucher Handling**: Paperin/paperin adjust system prevents double-counting

### ✅ **Dynamic Interface**
- **Smart Column Display**: Only shows VAT rate columns with actual data
- **Collapsible Sections**: Stock transfers can be expanded/collapsed to focus on main sales
- **Responsive Design**: Works on desktop and mobile devices
- **Clean Visual Design**: Modern gradient styling with hover effects

### ✅ **Comprehensive CSV Export**
- **Complete Data Structure**: Matches website table format exactly
- **Detailed Information**: Date range, VAT breakdown, and summary metrics
- **Professional Format**: Structured sections for easy accounting review
- **Total VAT for Returns**: Key metric clearly identified for tax purposes

### ✅ **Performance Optimization**
- **Dual Data Sources**: Uses pre-aggregated data when available, falls back to real-time
- **100x+ Performance**: Leverages optimized sales repository patterns
- **Smart Caching**: Reduces database load while maintaining accuracy
- **Fast Response Times**: Sub-second page loads for most date ranges

## Business Logic

### Revenue vs Stock Transfers

The system properly separates two types of transactions:

1. **Customer Sales (Revenue)**
   - Cash, card, debt, and free sales to actual customers
   - Included in revenue calculations
   - Subject to VAT reporting
   - Displayed in green-themed Customer Sales section

2. **Internal Stock Transfers**  
   - Kitchen and Coffee "customer" transactions
   - Used for moving stock between departments
   - Excluded from revenue calculations
   - Displayed in blue-themed collapsible section

### Gift Voucher System

**Paperin/Paperin Adjust Logic:**
- `paperin`: Gift voucher redemptions (shows as sales but aren't revenue)
- `paperin adjust*`: Negative adjustment to prevent double-counting
- Applied to 0% VAT rate to maintain VAT compliance
- Net effect: Voucher redemptions don't inflate revenue figures

### VAT Calculations

**Dynamic VAT Rates:**
- Only displays columns for VAT rates with actual data
- 0% rates show only Net column (no VAT column)
- Non-zero rates show both Net and VAT columns
- Maintains proper table alignment and readability

**VAT Breakdown:**
- Excludes voucher sales from VAT calculations
- Provides rate-by-rate breakdown for tax returns
- Calculates total VAT owed on actual sales only

## Technical Implementation

### Database Structure

**Pre-aggregated Tables:**
- `sales_accounting_daily`: Daily sales totals by payment type and VAT rate
- `stock_transfer_daily`: Daily transfer totals by department and VAT rate

**Key Fields:**
- `sale_date/transfer_date`: Date of transactions
- `payment_type/department`: Transaction categorization
- `vat_rate`: VAT rate applied (stored as decimal: 0.09, 0.135, 0.23)
- `net_amount`, `vat_amount`, `gross_amount`: Financial totals
- `transaction_count`: Number of individual transactions

### Controller Architecture

**`SalesAccountingReportController`:**
- `index()`: Main report display with date range handling
- `generateSalesAccountingReport()`: Dual-source data generation
- `getAggregatedReport()`: Fast pre-aggregated data queries  
- `getRealTimeReport()`: Fallback to live POS data
- `enhanceReportData()`: Adds active rates and summary metrics
- `exportCsv()`: Comprehensive CSV export with structured format

**Key Methods:**
- `hasAggregatedData()`: Checks if optimized data exists for date range
- `calculateSummaryMetrics()`: Computes revenue excluding vouchers
- `formatReportData()`: Structures data for template display

### Template Features

**Dynamic Columns (`all_active_rates`):**
```php
@foreach($data['all_active_rates'] as $rate)
    <th>{{ number_format($rate * 100, 1) }}% Net</th>
    @if($rate != 0)
        <th>{{ number_format($rate * 100, 1) }}% VAT</th>
    @endif
@endforeach
```

**Collapsible Transfers (Alpine.js):**
```html
<div x-data="{ transfersExpanded: false }">
    <button @click="transfersExpanded = !transfersExpanded">
        <span x-text="transfersExpanded ? 'Collapse' : 'Expand'"></span>
    </button>
    <div x-show="transfersExpanded" x-transition>
        <!-- Transfers table -->
    </div>
</div>
```

## CSV Export Structure

The enhanced CSV export provides a comprehensive, structured format:

### 1. Report Header
- Title and date range covered
- Generation timestamp  
- Data source information (Optimized vs Real-time)

### 2. Summary Metrics
- Customer Revenue (excludes vouchers): €X,XXX.XX
- Total VAT Owed: €XXX.XX  
- Stock Transfers (if any): €XXX.XX
- Voucher Sales (if any): €XXX.XX

### 3. VAT Breakdown
- 9.0% VAT: €XXX.XX
- 13.5% VAT: €XXX.XX  
- 23.0% VAT: €XXX.XX
- (Only shows rates with actual amounts)

### 4. Customer Sales Table
Exact replica of website table:
- Payment type rows (cash, magcard, debt, free)
- Dynamic VAT columns (only active rates)
- Paperin adjust row (if applicable)
- Total Sales summary row

### 5. Stock Transfers Table (if applicable)
- Department rows (Kitchen, Coffee)
- Same column structure as sales
- Total Transfers summary row

### 6. Final Summary
- Total Customer Revenue
- Total Stock Transfers  
- **Total VAT for Returns** (key for tax purposes)

## Usage Guide

### Accessing the Report

**Navigation:** Management → Sales Accounting Reports

**Permissions Required:**
- Role: Admin or Manager (`role:admin,manager` middleware)
- Users with Employee role cannot access this feature

### Generating Reports

1. **Select Date Range:**
   - Start Date: Beginning of reporting period
   - End Date: End of reporting period
   - Default: Current month to today

2. **Generate Report:**
   - Click "Generate Report" button
   - System automatically chooses fastest data source
   - Displays data source information (Optimized vs Real-time)

3. **Export Options:**
   - Click "Export CSV" for comprehensive data export
   - File includes all tables and summary information
   - Filename format: `sales_accounting_YYYY-MM-DD_to_YYYY-MM-DD.csv`

### Understanding the Display

**Customer Sales Section:**
- Green styling indicates revenue-generating transactions
- Shows breakdown by payment type and VAT rate
- Includes paperin adjust row to correct voucher double-counting

**Stock Transfers Section:**  
- Blue styling indicates internal movements
- Collapsible to keep focus on main sales data
- Click "Expand" to see departmental breakdown

**Summary Cards (Bottom):**
- Total Revenue: Customer sales excluding voucher adjustments
- Stock Transfers: Internal movements (if any)
- VAT for Returns: Total VAT owed on actual sales

## Performance Optimization

### Data Source Strategy

**Primary (Optimized):**
- Uses `sales_accounting_daily` and `stock_transfer_daily` tables
- Pre-aggregated data provides 100x+ faster queries
- Updated via `sales-accounting:import` command
- Indicated as "Optimized (Fast)" in interface

**Fallback (Real-time):**  
- Queries live POS database when aggregated data unavailable
- Direct queries to `TICKETLINES`, `RECEIPTS`, `PAYMENTS` tables
- Slower but always current
- Indicated as "Real-time (May be slower)" in interface

### Import Command

**Daily Import:**
```bash
php artisan sales-accounting:import --days=7
```

**Date Range Import:**
```bash
php artisan sales-accounting:import --start-date=2023-01-01 --end-date=2023-12-31
```

**Force Re-import:**
```bash
php artisan sales-accounting:import --force --start-date=2023-01-01
```

### Caching Strategy
- Controller uses string VAT rate keys to avoid PHP 8.3 float-to-int conversion
- Template lookups optimized for performance
- Summary metrics calculated once and reused

## Security & Access Control

### Role-Based Access
- **Route Protection:** `middleware(['role:admin,manager'])`
- **Admin Users:** Full access to all features
- **Manager Users:** Full access to all features  
- **Employee Users:** No access (403 Forbidden)

### Data Security
- **Read-Only Access:** Report system only queries data, never modifies
- **Audit Logging:** All access logged through Laravel's built-in logging
- **Input Validation:** Date ranges validated before processing
- **SQL Injection Protection:** All queries use Laravel's query builder

## Error Handling

### Common Issues

**No Data Found:**
- Displays friendly message when no transactions in date range
- Suggests checking date range or running import command

**Performance Timeouts:**
- Automatically falls back to real-time data if aggregated queries fail
- Shows data source information to user

**Invalid Dates:**
- Validates date range before processing
- Shows user-friendly error messages

### Troubleshooting

**Slow Performance:**
1. Check if aggregated data exists for date range
2. Run import command to create optimized data:
   ```bash
   php artisan sales-accounting:import --start-date=YYYY-MM-DD --end-date=YYYY-MM-DD
   ```
3. Monitor data source indicator in interface

**Incorrect Totals:**
1. Verify paperin adjust calculations
2. Check VAT rate mappings in POS system
3. Compare aggregated vs real-time data
4. Re-run import with `--force` flag if needed

**CSV Export Issues:**
1. Ensure adequate PHP memory for large date ranges
2. Check file permissions for download directory
3. Verify all summary calculations in export

## Integration Points

### POS Database Connection
- **Connection:** `pos` database connection in Laravel
- **Key Tables:** `TICKETLINES`, `RECEIPTS`, `PAYMENTS`, `CUSTOMERS`, `TAXES`  
- **Read-Only:** System never modifies POS data
- **Cross-Database Queries:** Optimized for performance

### Laravel Application Integration
- **Routes:** Defined in `routes/web.php` under management prefix
- **Middleware:** Uses existing RBAC system for access control
- **Navigation:** Integrated into admin sidebar menu
- **Assets:** Uses existing Tailwind CSS and Alpine.js

### Data Flow
```
POS Database → Import Command → Aggregated Tables → Report Controller → Blade Template
     ↓                                                        ↑
Real-time Fallback ←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←
```

## Future Enhancements

### Planned Features (Pending)
- **Mobile Responsive Improvements:** Enhanced mobile layout
- **Excel/PDF Export:** Additional export formats
- **Date Range Presets:** Quick selection (This Month, Last Month, etc.)
- **Interactive Controls:** Toggle controls for data filtering

### Potential Integrations
- **VAT Return System:** Direct integration with VAT dashboard
- **Management Dashboard:** Summary metrics on main dashboard
- **Automated Reports:** Scheduled email reports for management

## Testing Strategy

### Unit Tests
- VAT calculation accuracy
- Revenue vs transfer separation  
- Gift voucher adjustment logic
- CSV export formatting

### Integration Tests  
- POS database connectivity
- Aggregated vs real-time data consistency
- Export functionality
- Permission enforcement

### Performance Tests
- Large date range handling
- Memory usage for CSV exports
- Response time benchmarks
- Database query optimization

## Maintenance

### Regular Tasks
- **Monthly Import:** Ensure aggregated data is current
- **Performance Monitoring:** Check query response times
- **Data Validation:** Spot-check totals against POS reports
- **Cache Management:** Clear compiled views after updates

### Updates Required
- **VAT Rate Changes:** Update rate mappings if government changes rates
- **POS Schema Changes:** Adapt queries if POS system updated
- **Laravel Updates:** Ensure compatibility with framework updates

---

## Related Documentation

- **[VAT Dashboard](./vat-dashboard.md)** - VAT return management system
- **[Sales Data Import Plan](./sales-data-import-plan.md)** - 100x performance optimization patterns  
- **[Management Accounting System](./management-accounting-system.md)** - Overall financial system plan
- **[User Roles & Permissions](./user-roles-permissions.md)** - Access control system

## Support

For technical support or feature requests related to the Sales Accounting Report system, refer to the main application documentation or contact the development team.