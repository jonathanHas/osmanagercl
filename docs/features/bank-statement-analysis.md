# Bank Statement Analysis System

The Bank Statement Analysis system provides comprehensive reconciliation between POS sales data and bank lodgements, enabling accurate financial tracking and variance identification.

## Overview

This system compares daily POS sales against bank deposits to:
- Identify unmatched transactions requiring attention
- Calculate variances between sales and lodgements
- Track lodgement patterns and timing
- Generate reconciliation reports for accounting

## Key Features

### Daily Reconciliation Grid
- Side-by-side comparison of POS sales vs bank lodgements
- Color-coded status indicators (matched, missing, variance)
- Day-of-week analysis for pattern identification
- Real-time variance calculations

### Automatic Pattern Detection
- **Exact Matching**: Finds bank lodgements that exactly match POS totals
- **Weekend Combining**: Detects combined Friday-Sunday deposits on Monday
- **Card Settlement Timing**: Identifies typical card payment settlement delays
- **Confidence Scoring**: Rates automatic matches by likelihood

### Manual Matching Interface
- Link specific POS days to bank transactions
- Support for partial matches and split deposits
- Match type categorization (exact, combined, split, partial)
- Audit trail with user attribution and notes

### Performance Optimization
- **Cached Daily Summaries**: POS data pre-aggregated for instant access
- **Smart Refresh**: Only recalculates when needed
- **Batch Processing**: Efficient period analysis

## Database Schema

### POS Daily Summaries Table
```sql
pos_daily_summaries
├── sale_date (date, unique)
├── cash_sales (decimal)
├── cash_refunds (decimal)
├── card_sales (decimal)
├── card_refunds (decimal)
├── debt_sales (decimal)
├── free_sales (decimal)
├── total_transactions (integer)
└── timestamps
```

### Matching Records Table
```sql
pos_bank_matches
├── pos_date (date)
├── bank_transaction_id (uuid)
├── matched_amount (decimal)
├── match_type (enum: exact, partial, combined, split)
├── confidence_score (integer)
├── notes (text)
├── matched_by (user_id)
├── matched_at (timestamp)
└── timestamps
```

## Usage

### Accessing the Analysis
Navigate to **Management → Bank Statements → Analysis** or visit `/management/bank-statements/analysis`

### Setting Date Range
- Default: Last 30 days
- Custom range: Use date picker inputs
- Quick selection buttons: Current Month, Previous Month, Last 3 Months, This Year
- Recommended: Review monthly periods for accounting alignment

### Understanding Status Codes
- **✅ Matched**: POS and bank amounts align within €0.01
- **⚠️ Matched with Variance**: Small differences (< €50 or < 5%)
- **❌ Missing**: No corresponding bank lodgement found
- **🔍 Large Variance**: Significant amount differences requiring investigation
- **⏳ Pending**: Recent sales, lodgement expected within 3 business days
- **💤 No Sales**: No POS activity on this date

### Manual Matching Process
1. **Identify Unmatched Items**: Review "Unmatched POS Days" and "Unmatched Bank Transactions"
2. **Create Match**: Click match button to link POS day with bank transaction
3. **Select Match Type**: Choose appropriate category
4. **Add Notes**: Document reasoning for audit trail
5. **Confirm**: Save match for future reference

## Data Sources

### POS Database Integration
Connects to uniCenta POS system:
- **Tables**: RECEIPTS, PAYMENTS
- **Payment Types**: cash, cashrefund, magcard, magcardrefund, debt, free, bank, paperin
- **Real-time**: Direct queries with caching layer

### Bank Transaction Integration
Uses existing bank_transactions table:
- **Credit Amounts**: Only positive lodgements considered
- **Date Range**: Searches 3-5 days after POS date for matches
- **Description Matching**: Pattern analysis for transaction categorization

## Reconciliation Logic

### Automatic Matching Algorithm
1. **Exact Match**: Find bank transaction within 5 days matching POS total exactly
2. **Weekend Pattern**: Combine Friday-Sunday sales, match to Monday lodgement
3. **Card Settlement**: Account for 1-3 day delays in card payment deposits
4. **Confidence Scoring**: Rate matches from 0-100 based on amount accuracy and timing

### Variance Calculation
```php
$variance = $bankAmount - $posAmount;
$variancePercent = $posAmount > 0 ? ($variance / $posAmount) * 100 : 0;
$isSignificant = abs($variance) > 50 || abs($variancePercent) > 5;
```

### Status Determination
- **Recent sales** (< 3 days): Status = "pending"
- **Exact match** (< €0.01 difference): Status = "matched"
- **Small variance** (< €50): Status = "matched_with_variance"
- **Large difference**: Status = "large_variance"
- **No lodgement found**: Status = "missing"

## Export Functionality

### CSV Export Format
- Date, Day of Week
- POS Cash, POS Card, POS Total
- Bank Lodged Amount
- Variance Amount and Percentage
- Reconciliation Status
- Notes and Suggestions

### Report Generation
Exports respect current filters and date range for targeted analysis.

## Performance Features

### Caching Strategy
- **Daily Summaries**: POS data cached in database table
- **Analysis Results**: Complex calculations cached for 1 hour
- **Smart Refresh**: Individual date recalculation on demand

### Database Optimization
- **Indexed Lookups**: Efficient date-based queries
- **Batch Processing**: Process multiple days in single query
- **Connection Pooling**: Optimized cross-database queries

## Administrative Functions

### Data Management
- **Refresh POS Data**: Recalculate specific date summaries
- **Clear Matches**: Remove incorrect manual matches
- **Bulk Operations**: Process multiple periods simultaneously

### Audit Trail
All manual matches tracked with:
- User who created the match
- Timestamp of creation
- Notes and reasoning
- Match confidence and type

## Integration Points

### Financial Overview Dashboard
Provides validated data for:
- Daily cash flow analysis
- Monthly reconciliation summaries
- Variance trend reporting
- Outstanding item tracking

### VAT Returns
Supports VAT calculations by:
- Confirming lodged amounts match sales
- Identifying timing differences
- Providing audit trail for Revenue

### Cash Reconciliation
Cross-references with:
- Till cash counts
- Supplier payments from till
- Float management
- Daily variance reports

## Troubleshooting

### Common Issues

**No POS Data Appearing**
- Check POS database connection in `.env`
- Verify date format (YYYY-MM-DD)
- Ensure RECEIPTS/PAYMENTS tables accessible

**Bank Matches Not Found**
- Confirm bank_transactions table has data for period
- Check credit_amount > 0 filter
- Verify transaction_date format

**Performance Slow**
- Check pos_daily_summaries table populated
- Clear old cache entries
- Verify database indexes on date columns

**Quick Date Selection Off by One Day** (Fixed 2025-09-11)
- Issue was timezone conversion in JavaScript formatDate function
- toISOString() converted local dates to UTC, causing day shifts
- Fixed by using local date formatting without timezone conversion

### Data Validation
Regular checks recommended:
- Compare POS totals to till reports
- Verify bank transaction imports complete
- Cross-check manual matches for accuracy

## Future Enhancements

### Planned Features
- **AI Pattern Recognition**: Machine learning for better automatic matching
- **Multi-Account Support**: Handle multiple bank accounts
- **Mobile Interface**: Tablet-optimized matching interface
- **Integration APIs**: Connect with accounting systems

### Enhancement Requests
Submit enhancement requests through the standard development process with:
- Use case description
- Expected benefit
- Priority level
- Resource requirements

---

**Access Level**: Admin, Manager  
**Last Updated**: 2025-09-11  
**Version**: 1.1