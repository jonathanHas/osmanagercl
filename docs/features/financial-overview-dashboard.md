# Financial Overview Dashboard - Implementation Plan

## Executive Summary

A comprehensive financial management dashboard that unifies all disparate financial data sources (POS sales, bank transactions, invoices, VAT, cash reconciliations) into a single, actionable overview with drill-down capabilities and discrepancy detection.

## Problem Statement

Currently, financial data is scattered across multiple systems:
- POS sales data in uniCenta database
- Bank transactions imported via CSV
- Invoices tracked separately
- VAT returns calculated periodically
- Cash reconciliations done daily

This fragmentation makes it difficult to:
- Get a real-time view of business financial health
- Identify discrepancies between systems
- Track cash flow accurately
- Categorize and analyze expenses properly

## Proposed Solution

### 1. Enhanced Financial Overview Dashboard (`/management/financial/overview`)

#### Sales & Revenue Section
- **Daily POS Sales Breakdown**
  - Cash, card, debt, free sales with refunds netted out
  - Compare to previous day/week/month with growth indicators
  - Visual trends chart (7-day, 30-day views)
  - Drill-down to individual transactions

- **Revenue Analytics**
  - Sales by time of day patterns
  - Product category performance
  - VAT breakdown by rate
  - Seasonal trends analysis

#### Expense Analysis Section
- **Categorized Expense Tracking**
  - Stock/Inventory purchases
  - Wages & Salaries
  - Utilities (electricity, water, gas, internet)
  - Rent & Property costs
  - Insurance premiums
  - Bank fees & charges
  - Repairs & Maintenance
  - Marketing & Advertising
  - Professional fees
  - Other operating expenses

- **Expense Analytics**
  - Monthly/weekly/daily breakdowns
  - Year-over-year comparisons
  - Unusual spending pattern alerts
  - Supplier spending rankings

#### Cash Flow & Lodgements
- **POS to Bank Reconciliation**
  - Expected cash from POS vs actual bank lodgements
  - Card settlement tracking and timing
  - Discrepancy highlighting with reasons
  - Lodgement pattern analysis (same-day, next-day, delayed)

- **Cash Flow Visualization**
  - Daily cash in vs cash out
  - Running balance tracking
  - Forecast based on patterns
  - Alert for low cash periods

#### Bank Reconciliation Status
- **Transaction Matching Overview**
  - Count of unmatched transactions
  - Recent successful reconciliations
  - Learning system confidence metrics
  - Quick-action buttons for bulk reconciliation

### 2. Lodgement Reconciliation Module (`/management/lodgement-reconciliation`)

#### Daily Lodgement Tracking
```sql
CREATE TABLE lodgement_tracking (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    pos_date DATE NOT NULL,
    pos_cash_total DECIMAL(10,2) NOT NULL,
    pos_card_total DECIMAL(10,2) NOT NULL,
    expected_lodgement_date DATE,
    actual_lodgement_date DATE NULL,
    bank_transaction_id UUID NULL,
    lodged_amount DECIMAL(10,2) NULL,
    variance DECIMAL(10,2) NULL,
    status ENUM('pending', 'matched', 'partial', 'missing', 'excess'),
    notes TEXT NULL,
    reconciled_by INT NULL,
    reconciled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_pos_date (pos_date),
    INDEX idx_status (status),
    FOREIGN KEY (bank_transaction_id) REFERENCES bank_transactions(id)
);
```

#### Features
- **Automatic Matching**
  - Match POS daily totals to bank deposits
  - Handle multi-day lodgements
  - Support split lodgements
  - Track card processing delays

- **Discrepancy Detection**
  - Missing lodgements alert
  - Amount variances highlighting
  - Timing pattern analysis
  - Automatic notification system

### 3. Expense Category Management Enhancement

#### Category Migration Strategy
- Analyze existing 9,000+ "imported" invoices
- Auto-categorize based on supplier patterns
- Machine learning for category suggestions
- Bulk update capabilities

#### Smart Auto-Categorization
```php
class ExpenseCategorizer
{
    public function categorizeInvoice(Invoice $invoice): string
    {
        // Check supplier default category
        if ($supplier = $invoice->supplier) {
            if ($supplier->default_expense_category) {
                return $supplier->default_expense_category;
            }
        }
        
        // Use learning rules
        $rule = ReconciliationRule::where('supplier_id', $invoice->supplier_id)
            ->where('confidence_score', '>=', 70)
            ->first();
            
        if ($rule && $rule->expense_category) {
            return $rule->expense_category;
        }
        
        // Pattern matching on description
        return $this->matchDescriptionPatterns($invoice->description);
    }
}
```

### 4. Integrated P&L Statement (`/management/profit-loss`)

#### Real-time P&L Calculation
- **Revenue Section**
  - Gross sales from POS
  - Less: Returns and refunds
  - Less: Discounts and vouchers
  - Net Revenue

- **Cost of Goods Sold**
  - Opening stock
  - Plus: Purchases
  - Less: Closing stock
  - COGS total

- **Operating Expenses**
  - Wages & salaries
  - Rent & utilities
  - Insurance
  - Marketing
  - Other operating costs

- **Net Profit Calculation**
  - Gross Profit (Revenue - COGS)
  - Operating Profit (Gross - Operating Expenses)
  - Net Profit (after tax)

### 5. Cash Position Dashboard (`/management/cash-position`)

#### Current Cash Status
- Physical cash from last count
- Expected cash based on sales since
- Bank account balances (when integrated)
- Outstanding payments due
- Available working capital

#### Cash Flow Forecast
```php
class CashForecastService
{
    public function forecast($days = 30)
    {
        $forecast = [];
        $currentBalance = $this->getCurrentCashPosition();
        
        for ($i = 1; $i <= $days; $i++) {
            $date = now()->addDays($i);
            
            // Expected income (based on average sales)
            $expectedSales = $this->getAverageDailySales($date->dayOfWeek);
            
            // Expected expenses
            $scheduledPayments = $this->getScheduledPayments($date);
            $recurringCosts = $this->getRecurringCosts($date);
            
            $dayForecast = [
                'date' => $date,
                'opening_balance' => $currentBalance,
                'expected_income' => $expectedSales,
                'expected_expenses' => $scheduledPayments + $recurringCosts,
                'closing_balance' => $currentBalance + $expectedSales - $scheduledPayments - $recurringCosts
            ];
            
            $forecast[] = $dayForecast;
            $currentBalance = $dayForecast['closing_balance'];
        }
        
        return $forecast;
    }
}
```

## Implementation Architecture

### Database Schema Changes

#### 1. Enhanced Invoice Categories
```sql
ALTER TABLE invoices 
ADD COLUMN category_confidence INT DEFAULT 0,
ADD COLUMN auto_categorized BOOLEAN DEFAULT FALSE,
ADD INDEX idx_expense_category (expense_category);

-- Category lookup table
CREATE TABLE expense_categories (
    code VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    parent_code VARCHAR(50) NULL,
    vat_treatment VARCHAR(20) DEFAULT 'standard',
    is_cogs BOOLEAN DEFAULT FALSE,
    is_operating BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 100
);
```

#### 2. Financial Metrics Cache
```sql
CREATE TABLE financial_metrics_cache (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    metric_date DATE NOT NULL,
    metric_type VARCHAR(50) NOT NULL,
    metric_value JSON NOT NULL,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_date_type (metric_date, metric_type),
    INDEX idx_date (metric_date)
);
```

### Service Layer Architecture

```php
// Financial Overview Service
class FinancialOverviewService
{
    public function getDashboardData($date)
    {
        return [
            'sales' => $this->salesService->getDailyMetrics($date),
            'expenses' => $this->expenseService->getCategorizedExpenses($date),
            'cashFlow' => $this->cashFlowService->getDailyCashFlow($date),
            'lodgements' => $this->lodgementService->getReconciliationStatus($date),
            'pl' => $this->plService->getProfitLoss($date),
            'alerts' => $this->alertService->getFinancialAlerts($date)
        ];
    }
}

// Lodgement Reconciliation Service
class LodgementReconciliationService
{
    public function reconcileDailyLodgements($date)
    {
        $posTotal = $this->getPOSDayTotal($date);
        $expectedLodgementDate = $this->calculateExpectedLodgementDate($date);
        $bankLodgements = $this->findBankLodgements($expectedLodgementDate, $posTotal);
        
        return $this->createOrUpdateTracking($date, $posTotal, $bankLodgements);
    }
}
```

## User Interface Design

### Dashboard Layout
```
┌─────────────────────────────────────────────────────────────┐
│                    Financial Overview Dashboard              │
├─────────────────────────────────────────────────────────────┤
│  Date Selector: [2025-09-08] [Today] [Yesterday] [Week]     │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Today's Sales│  │ Today's Costs│  │ Net Position │      │
│  │   €5,432     │  │   €2,156     │  │   €3,276    │      │
│  │   ↑ 12%      │  │   ↓ 5%       │  │   ↑ 23%     │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│                                                               │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Cash Expected│  │ Cash Lodged  │  │  Variance    │      │
│  │   €1,532     │  │   €1,498     │  │   -€34      │      │
│  │  3 days ago  │  │  Yesterday   │  │   2.2%      │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│                                                               │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              7-Day Sales Trend                       │    │
│  │     [Chart showing daily sales for last 7 days]     │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                               │
│  ┌─────────────────────────────────────────────────────┐    │
│  │          Expense Breakdown (This Month)             │    │
│  │  Stock:        €45,234  ████████████████ 65%       │    │
│  │  Wages:        €12,456  ████ 18%                   │    │
│  │  Utilities:     €3,234  ██ 5%                      │    │
│  │  Rent:          €2,500  █ 4%                       │    │
│  │  Other:         €5,567  ██ 8%                      │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                               │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              Action Items                           │    │
│  │  ⚠️ 3 days of lodgements pending reconciliation     │    │
│  │  ⚠️ 15 unmatched bank transactions                  │    │
│  │  ⚠️ Cash variance >€50 on Aug 5                     │    │
│  │  ℹ️ VAT return due in 12 days                       │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

## Performance Optimization

### Caching Strategy
- Cache daily metrics for completed days
- Real-time calculation for current day only
- Background job for overnight recalculation
- Redis for sub-second response times

### Query Optimization
- Pre-aggregate POS data hourly
- Use materialized views for complex joins
- Implement query result caching
- Optimize indexes for common queries

## Security & Access Control

### Role-Based Access
- **Admin**: Full access to all financial data
- **Manager**: View access, limited edit capabilities
- **Employee**: No access to financial overview
- **Accountant**: Read-only access with export capabilities

### Audit Trail
- Log all financial data access
- Track manual adjustments
- Record reconciliation actions
- Maintain change history

## Implementation Timeline

### Phase 1: Foundation (Week 1)
- Create lodgement_tracking table
- Build expense categorization service
- Implement basic dashboard controller
- Create initial dashboard view

### Phase 2: Reconciliation (Week 2)
- Implement lodgement matching logic
- Build discrepancy detection
- Create reconciliation interface
- Add manual adjustment capabilities

### Phase 3: Analytics (Week 3)
- Implement P&L calculation
- Build cash flow forecasting
- Create trend analysis
- Add comparison features

### Phase 4: Integration (Week 4)
- Connect all data sources
- Implement caching layer
- Add export functionality
- Create alert system

### Phase 5: Polish (Week 5)
- Performance optimization
- Mobile responsive design
- User training materials
- Documentation completion

## Success Metrics

### Key Performance Indicators
- Dashboard load time < 2 seconds
- Lodgement matching accuracy > 95%
- Expense categorization accuracy > 90%
- User adoption rate > 80%
- Time savings: 2+ hours per week

### Business Benefits
- Real-time financial visibility
- Faster discrepancy detection
- Improved cash flow management
- Better expense control
- Simplified VAT compliance
- Enhanced decision making

## Future Enhancements

### Phase 2 Features
- Bank API integration for real-time balances
- Predictive analytics for sales forecasting
- Automated supplier payment scheduling
- Budget vs actual tracking
- Multi-location support
- Custom report builder

### Integration Opportunities
- Accounting software sync (Xero, QuickBooks)
- Banking APIs for live data
- Payment processor integration
- Inventory management connection
- Timekeeper for automated wage imports

## Conclusion

This comprehensive Financial Overview Dashboard will transform financial management by:
1. Unifying all financial data sources
2. Providing real-time visibility
3. Automating reconciliation tasks
4. Detecting discrepancies immediately
5. Enabling data-driven decisions

The phased implementation approach ensures quick wins while building toward a complete solution that will save hours of manual work and provide unprecedented financial clarity.