# VAT Returns Management System

## Overview

The VAT Returns Management System provides comprehensive functionality for creating, managing, and submitting Irish VAT returns. The system integrates with both purchase invoices and POS sales data to automatically calculate all Revenue Online Service (ROS) fields, including VAT on sales, VAT on purchases, and Intra-EU trade figures.

## Key Features

### 🇮🇪 **Revenue Online Service (ROS) Compliance**
- **Complete ROS Fields**: Automatically calculates T1, T2, T3, T4, E1, E2 values
- **Irish VAT Periods**: Supports bi-monthly periods (Jan-Feb, Mar-Apr, etc.)
- **Accurate Calculations**: Includes all VAT including voucher sales VAT
- **EU Trade Tracking**: Automatic INTRASTAT reporting for EU suppliers

### 📊 **Automated VAT Calculations**
- **T1 - VAT on Sales**: Calculated from POS data including all payment types
- **T2 - VAT on Purchases**: Calculated from invoice data by VAT rate
- **T3/T4 - Net Payable/Repayable**: Automatic calculation with visual indicators
- **E1 - Goods to EU**: Currently zero (no exports)
- **E2 - Goods from EU**: Automatic calculation from EU supplier invoices

### 🚀 **Enhanced User Experience**
- **Auto-Selection**: All period invoices selected by default
- **Smart Period Detection**: Bi-monthly period calculation from any selected date
- **Live Validation**: Real-time selection counting and validation
- **Comprehensive Exports**: Automatic CSV download with all ROS data

### 📈 **Performance Optimization**
- **Dual Data Sources**: Uses pre-aggregated data when available (100x+ faster)
- **Real-time Fallback**: Falls back to real-time POS queries if needed
- **Optimized Queries**: Efficient cross-database queries with proper indexing
- **Visual Indicators**: Shows data source (Optimized vs Real-time)

## System Architecture

### Bi-Monthly VAT Periods

Irish VAT returns use bi-monthly periods:
- **Jan-Feb** (Due March 19th)
- **Mar-Apr** (Due May 19th)  
- **May-Jun** (Due July 19th)
- **Jul-Aug** (Due September 19th)
- **Sep-Oct** (Due November 19th)
- **Nov-Dec** (Due January 19th)

### EU Supplier Tracking

The system automatically tracks EU suppliers for INTRASTAT reporting:
- **Automatic Detection**: Suppliers marked with `is_eu_supplier = true`
- **Country Codes**: Stored in `country_code` field (FR, NL, etc.)
- **Visual Indicators**: EU supplier badges in invoice lists
- **INTRASTAT Calculation**: Automatic E2 field calculation

## Creating VAT Returns

### 1. Access VAT Returns
Navigate to **Management → VAT Returns** or use outstanding period links from the VAT Dashboard.

### 2. Select VAT Period
- Select any date within the desired VAT period
- System automatically determines the correct bi-monthly period
- Period dates are displayed: "May-Jun 2025"
- Due date is calculated and shown

### 3. Load VAT Data
Click **"Load VAT Data"** to calculate:
- Complete ROS VAT Return Summary
- Sales VAT breakdown by rate
- Purchase VAT breakdown by rate
- EU supplier invoice totals
- Net payable/repayable amounts

### 4. Review Invoice Selection
- **All invoices auto-selected** by default
- Selection counter shows "X of Y invoices selected"
- Visual EU supplier badges for relevant invoices
- Select/deselect buttons for fine-tuning

### 5. Download Preview (Testing)
Click **"Download CSV Preview"** to test the export without creating a return:
- Comprehensive CSV with all ROS fields
- Sales and purchase VAT breakdowns
- Detailed invoice data
- EU supplier INTRASTAT information

### 6. Create VAT Return
Click **"Create VAT Return"** to:
- Create the official VAT return record
- Assign selected invoices to the return
- Automatically download comprehensive CSV
- Redirect to VAT return details page

## ROS VAT Return Fields

### T1 - VAT on Sales
- **Source**: POS sales data (all payment types)
- **Includes**: Voucher sales VAT (must be paid immediately)
- **Excludes**: Internal transfers, kitchen/coffee departments
- **Data Source**: Optimized pre-aggregated data when available

### T2 - VAT on Purchases  
- **Source**: Selected invoice data
- **Breakdown**: 0%, 9%, 13.5%, 23% VAT rates
- **Real-time**: Calculated from actual invoice assignments
- **Validation**: Only unassigned invoices included

### T3/T4 - Net Payable/Repayable
- **Calculation**: T1 - T2 = Net position
- **T3**: Positive amount (VAT owed to Revenue)
- **T4**: Negative amount (VAT refund due)
- **Visual**: Color-coded (Red = payable, Green = repayable)

### E1 - Goods to Other EU Countries
- **Current Value**: €0.00 (no exports)
- **Future**: Can be configured if exports begin

### E2 - Goods from Other EU Countries
- **Source**: EU supplier invoices (net amount)
- **Suppliers**: Dynamis (France), Udea (Netherlands)
- **Automatic**: Calculated from invoice data
- **INTRASTAT**: Required for EU trade reporting

## CSV Export Features

### Comprehensive Export Contents

The automated CSV export includes:

1. **Report Header**
   - VAT period and dates
   - Generation timestamp
   - Data source indicator

2. **ROS VAT Return Summary**
   - All T1, T2, T3, T4, E1, E2 fields
   - Ready for ROS submission

3. **Sales VAT Breakdown**
   - By VAT rate (0%, 13.5%, 23%)
   - Net, VAT, and gross amounts
   - Data source indication

4. **Purchase VAT Breakdown**
   - By VAT rate with totals
   - Matches T2 calculation

5. **EU Suppliers Section**
   - Supplier names and countries
   - Net amounts for INTRASTAT
   - Matches E2 calculation

6. **Detailed Invoice Data**
   - All invoice details by VAT rate
   - EU supplier flags
   - Complete audit trail

### Export Triggers

- **Preview Export**: Manual download from create page
- **Automatic Export**: Downloads when VAT return is created
- **Manual Export**: Available from VAT return details page

## Access Control

VAT Returns are protected by role-based access:
- **Access Roles**: Admin and Manager only
- **Middleware**: `role:admin,manager`
- **Navigation**: Conditionally displayed based on permissions

## Database Structure

### Core Tables
- `vat_returns`: Main VAT return records
- `invoices`: Purchase invoice data
- `accounting_suppliers`: Supplier information with EU flags
- `sales_accounting_daily`: Pre-aggregated sales data

### EU Supplier Fields
```sql
-- Added to accounting_suppliers table
country_code VARCHAR(2) -- 'FR', 'NL', etc.
is_eu_supplier BOOLEAN DEFAULT FALSE
```

### VAT Return Fields
```sql
-- Core vat_returns table fields
return_period VARCHAR(100) -- 'May-Jun 2025'
period_start DATE
period_end DATE
status ENUM('draft', 'finalized', 'submitted')
-- VAT totals calculated from assigned invoices
```

## Performance Optimization

### Dual Data Architecture
1. **Optimized Path**: Uses `sales_accounting_daily` pre-aggregated data
2. **Real-time Path**: Direct POS database queries as fallback
3. **Automatic Selection**: System chooses best available data source
4. **Performance Gain**: 100-1000x faster with optimized data

### Query Optimization
- Indexed EU supplier queries
- Efficient cross-database joins
- Cached VAT calculations
- Optimized date range queries

## Integration Points

### VAT Dashboard Integration
- Outstanding periods link to VAT return creation
- Real-time updates when returns are created
- Historical tracking of all submissions

### Sales Accounting Integration  
- Shared VAT calculation logic
- Consistent figures between reports
- Same data source optimization

### Invoice Management Integration
- Automatic invoice assignment
- Unassigned invoice tracking
- Cross-database supplier matching

## Troubleshooting

### Common Issues

**VAT Figures Don't Match**
- Check if using same data source (optimized vs real-time)
- Verify date ranges match exactly
- Ensure all payment types included correctly

**Missing EU Suppliers**
- Verify `is_eu_supplier` flag is set
- Check `country_code` field is populated
- Run supplier sync if needed

**Auto-Selection Not Working**
- Check JavaScript console for errors
- Verify invoices exist for selected period
- Ensure page loaded completely

## Future Enhancements

Planned improvements:
- Direct ROS submission integration
- Automated VAT return scheduling  
- Enhanced EU supplier management
- Bulk period processing
- Advanced validation rules
- Custom period configurations

## Related Documentation

- [VAT Dashboard System](./vat-dashboard.md)
- [Sales Accounting Report](./sales-accounting-report.md)
- [OSAccounts Integration](./osaccounts-integration.md)
- [Performance Optimization Guide](../development/performance-optimization-guide.md)
- [Invoice Management](./invoice-management.md)

## Technical Implementation

### Controllers
- `VatReturnController`: Main VAT return management
- `VatDashboardController`: Dashboard integration

### Key Methods
- `create()`: VAT return creation with all calculations
- `exportPreview()`: Test CSV export functionality
- `export()`: Comprehensive CSV export
- `getSalesVatData()`: Dual-source sales data retrieval

### Routes
```php
Route::prefix('vat-returns')->group(function () {
    Route::get('/create', 'VatReturnController@create');
    Route::post('/', 'VatReturnController@store');
    Route::post('/export-preview', 'VatReturnController@exportPreview');
    Route::get('/{vatReturn}/export', 'VatReturnController@export');
});
```

---

*This documentation reflects the complete VAT Returns system as of August 2025, including all ROS compliance features, EU supplier tracking, and performance optimizations.*