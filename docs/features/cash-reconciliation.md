# Cash Reconciliation System

## Overview

The Cash Reconciliation System provides comprehensive end-of-day cash management functionality, allowing managers to reconcile physical cash counts against POS system totals, track supplier payments, and maintain accurate float records.

## Features

### Core Functionality
- **Physical Cash Counting**: Count cash by denomination (€50 notes down to 10c coins)
- **Float Management**: Track note and coin floats carried between days
- **Variance Calculation**: Automatic comparison between counted cash and POS totals
- **Supplier Payment Tracking**: Record cash payments made to suppliers from till
- **Multi-Till Support**: Manage reconciliations across all terminals
- **Legacy Data Import**: Seamlessly imports existing data from the legacy PHP system

### Additional Features
- **Real-time Calculations**: Dynamic totals update as you enter counts
- **Previous Float Carry-Over**: Automatically retrieves previous day's float
- **Daily Notes**: Add comments and notes for each reconciliation
- **Export to CSV**: Export reconciliation data for reporting
- **Audit Trail**: Complete tracking of who created/modified reconciliations
- **Historical View**: Quick access to recent reconciliation history

## Technical Implementation

### Database Structure

#### Tables
1. **cash_reconciliations** - Main reconciliation records
   - Links to POS CLOSEDCASH via `closed_cash_id`
   - Stores denomination counts (not totals)
   - Tracks floats, variances, and metadata

2. **cash_reconciliation_payments** - Supplier payment records
   - Links to suppliers in POS database
   - Supports multiple payments per reconciliation
   - Ordered by sequence

3. **cash_reconciliation_notes** - Daily notes/comments
   - Text notes for each reconciliation
   - Tracks who created each note

### Models
- `App\Models\CashReconciliation` - Main reconciliation model with calculation methods
- `App\Models\CashReconciliationPayment` - Payment records
- `App\Models\CashReconciliationNote` - Notes/comments

### Repository Pattern
- `App\Repositories\CashReconciliationRepository` - Business logic layer
  - Handles legacy data import
  - Calculates POS totals
  - Manages float carry-over
  - Provides export functionality

### Controller
- `App\Http\Controllers\Management\CashReconciliationController`
  - REST endpoints for CRUD operations
  - AJAX endpoints for dynamic data
  - CSV export functionality

## Legacy System Integration

### Data Import from POS Database

The system automatically imports existing reconciliation data from the legacy PHP system:

#### Tables Imported
1. **money** - Cash count data
   - Stores TOTAL VALUES (not counts)
   - System converts: €400 in cash50 → 8 × €50 notes
   
2. **payeePayments** - Supplier payments
   - Imported with supplier relationships
   - Maintains payment sequence

3. **dayNotes** - Daily reconciliation notes
   - Imported as-is into new system

### Conversion Logic
```php
// Legacy stores totals, we store counts
'cash_50' => $legacyMoney->cash50 ? intval($legacyMoney->cash50 / 50) : 0,
'cash_20' => $legacyMoney->cash20 ? intval($legacyMoney->cash20 / 20) : 0,
// ... etc
```

## User Interface

### Main Features
- **Date/Till Selector**: Choose date and till to reconcile
- **POS Summary Cards**: Display POS totals and variance
- **Cash Counting Grid**: Organized by notes and coins
- **Float Management Section**: Note and coin float entry
- **Supplier Payments**: Up to 4 payment entries with supplier dropdown
- **Notes Section**: Free-text area for daily notes
- **Summary Panel**: Real-time calculations and variance display

### Visual Indicators
- **Green**: Positive variance (over)
- **Red**: Negative variance (short)
- **Gray**: Neutral/no variance

## Permissions

### Role-Based Access
- **Admin**: Full access to all features
- **Manager**: Full access to all features
- **Employee**: No access (view-only for till receipts)

### Permission Keys
- `cash_reconciliation.view` - View reconciliation interface
- `cash_reconciliation.create` - Create/edit reconciliations
- `cash_reconciliation.export` - Export to CSV

## Calculations

### Total Cash Formula
```
Total Cash = (€50 × count) + (€20 × count) + ... + (10c × count)
```

### Day's Cash Taking
```
Day's Cash = Total Counted + Cash Back - Previous Float - Money Added
```

### Variance
```
Variance = Day's Cash Taking - POS Cash Total
```

## Usage Workflow

1. **Select Till and Date**: Choose the till and date to reconcile
2. **System Loads Data**: 
   - Imports any existing legacy data
   - Calculates POS totals
   - Retrieves previous float
3. **Enter Cash Counts**: Input the number of each denomination
4. **Record Payments**: Add any supplier payments made
5. **Add Notes**: Optional daily notes
6. **Save Reconciliation**: System calculates variance and stores data

## API Endpoints

### Main Routes
- `GET /cash-reconciliation` - Main interface
- `POST /cash-reconciliation/store` - Save reconciliation
- `GET /cash-reconciliation/previous-float` - Get previous day's float
- `GET /cash-reconciliation/reconciliation` - Get reconciliation data via AJAX
- `GET /cash-reconciliation/export` - Export to CSV

## Troubleshooting

### Common Issues

#### "No closed cash record found"
- **Cause**: Till was not closed on the selected date
- **Solution**: Ensure till was properly closed in POS system

#### High Variance
- **Cause**: Incorrect float or missing payments
- **Solution**: Check previous day's float and supplier payments

#### Missing Legacy Data
- **Cause**: No entry in legacy `money` table
- **Solution**: Data will be created fresh; enter counts manually

## Related Systems

- [Till Review System](./till-review.md) - View receipts and transactions
- [POS Integration](./pos-integration.md) - Connection to uniCenta POS
- [User Roles & Permissions](./user-roles-permissions.md) - Access control

## Migration from Legacy System

The system seamlessly migrates from the legacy PHP cash reconciliation:

1. **Automatic Import**: First access imports existing data
2. **Data Preservation**: Once imported, data stays in new system
3. **Backward Compatible**: Reads from legacy tables if present
4. **No Data Loss**: All counts, payments, and notes preserved

### Legacy Table Mapping
| Legacy Table | Legacy Column | New Table | New Column | Conversion |
|-------------|---------------|-----------|------------|------------|
| money | cash50 | cash_reconciliations | cash_50 | Total ÷ 50 |
| money | cash20 | cash_reconciliations | cash_20 | Total ÷ 20 |
| payeePayments | payeeID | cash_reconciliation_payments | supplier_id | Direct |
| dayNotes | message | cash_reconciliation_notes | message | Direct |

## Performance Considerations

- **Caching**: Till list cached for 1 hour
- **Lazy Loading**: Payments and notes loaded on demand
- **Indexed Queries**: Date and till_id indexed for fast lookups
- **Optimized Calculations**: Totals calculated in PHP, not database

## Security

- **Role-based Access**: Only managers and admins can access
- **Audit Trail**: All actions logged with user and timestamp
- **Data Validation**: Comprehensive input validation
- **CSRF Protection**: All forms protected against CSRF attacks

## Future Enhancements

Potential improvements for future versions:
- Dashboard widgets for variance trends
- Email alerts for large variances
- Mobile app for cash counting
- Automated bank reconciliation
- Multi-currency support
- Barcode scanning for sealed cash bags