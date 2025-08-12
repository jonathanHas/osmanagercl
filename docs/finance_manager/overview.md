# Financial System Overview

## Architecture

The financial management system integrates multiple data sources to provide comprehensive business insights.

```
┌─────────────────────────────────────────────────────────────┐
│                    Financial Dashboard                       │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Receipts   │  │     Cash     │  │   Invoices   │     │
│  │  Management  │  │Reconciliation│  │  & Suppliers │     │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘     │
│         │                  │                  │              │
│  ┌──────▼──────────────────▼──────────────────▼───────┐    │
│  │            Unified Data Layer                       │    │
│  └──────┬──────────────────┬──────────────────┬───────┘    │
│         │                  │                  │              │
│  ┌──────▼───────┐  ┌──────▼───────┐  ┌──────▼───────┐     │
│  │ POS Database │  │ Main Database│  │   OSAccounts │     │
│  │  (Read-only) │  │              │  │   (Legacy)   │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

## Data Flow

### 1. Daily Operations Flow
```
Morning Setup
    ↓
Sales Transactions (POS)
    ↓
Real-time Dashboard Updates
    ↓
Supplier Payments Recording
    ↓
End-of-Day Cash Count
    ↓
Reconciliation & Variance Analysis
    ↓
Daily Reports Generation
```

### 2. Financial Data Pipeline
```
POS Receipts → Payment Processing → Dashboard Metrics
                        ↓
                Cash Reconciliation
                        ↓
                 Variance Analysis
                        ↓
                  Float Calculation
                        ↓
                 Next Day Setup
```

## Component Integration

### POS Integration
- **Tables Used**: RECEIPTS, PAYMENTS, CLOSEDCASH
- **Connection**: Read-only secondary database
- **Update Frequency**: Real-time queries
- **Key Joins**: RECEIPTS.ID = PAYMENTS.RECEIPT

### Cash Reconciliation
- **Tables**: cash_reconciliations, cash_reconciliation_payments
- **Features**: 
  - Physical count by denomination
  - Automatic variance calculation
  - Float management
  - Supplier payment tracking

### Invoice Management
- **Tables**: invoices, invoice_lines, suppliers
- **Integration**: OSAccounts legacy system
- **Features**:
  - VAT tracking
  - Payment matching
  - Attachment support

## Key Metrics Tracked

### Daily Metrics
- Total sales (gross and net)
- Payment method breakdown
- Transaction count and average
- Cash position
- Variance from expected

### Weekly/Monthly Metrics
- Sales trends
- Growth percentages
- Cash flow patterns
- Outstanding reconciliations
- Supplier payment totals

## Performance Optimizations

### Query Optimization
```sql
-- Optimized daily sales query
SELECT 
    COUNT(DISTINCT r.ID) as transactions,
    SUM(CASE WHEN p.TOTAL >= 0 THEN p.TOTAL ELSE 0 END) as sales,
    SUM(CASE WHEN p.PAYMENT = 'cash' THEN p.TOTAL ELSE 0 END) as cash
FROM RECEIPTS r
JOIN PAYMENTS p ON r.ID = p.RECEIPT
WHERE DATE(r.DATENEW) = ?
```

### Indexing Strategy
- Date-based indexes on transaction tables
- Payment type indexes for filtering
- Composite indexes for common joins

### Caching Approach
- Dashboard metrics cached for 5 minutes
- Historical data cached indefinitely
- Real-time data for current day only

## Security Considerations

### Data Access
- Role-based access control
- Audit logging on all financial operations
- Read-only access to POS database
- Encrypted sensitive data

### Compliance
- VAT calculation accuracy
- Audit trail maintenance
- Data retention policies
- Export restrictions

## Integration Points

### External Systems
1. **POS System (uniCenta)**
   - Transaction data
   - Payment information
   - Till management

2. **Banking (Future)**
   - Statement imports
   - Payment matching
   - Bank reconciliation

3. **Accounting Software (Future)**
   - Export capabilities
   - Journal entries
   - P&L generation

### Internal Modules
1. **User Management**
   - Role verification
   - Permission checks
   - Audit logging

2. **Product Management**
   - Cost tracking
   - Margin analysis
   - Pricing updates

3. **Supplier Management**
   - Payment tracking
   - Invoice matching
   - Spend analysis

## Development Guidelines

### Adding New Financial Features
1. Extend `FinancialDashboardController`
2. Update dashboard view components
3. Add appropriate permissions
4. Document in this guide
5. Add tests for calculations

### Database Modifications
1. Create migrations for new tables
2. Update models with relationships
3. Add indexes for performance
4. Document schema changes

### API Development
1. Follow RESTful conventions
2. Implement proper validation
3. Return consistent formats
4. Include error handling
5. Document endpoints