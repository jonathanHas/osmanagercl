# Finance Manager Documentation

Complete documentation for the unified financial management system in OSManager.

## 📚 Documentation Index

1. **[Overview](./overview.md)** - System architecture and components
2. **[Dashboard](./dashboard.md)** - Financial dashboard features and metrics
3. **[Database Schema](./database-schema.md)** - Table structures and relationships
4. **[API Reference](./api-reference.md)** - Endpoints and data formats
5. **[Integration Guide](./integration-guide.md)** - Connecting financial modules
6. **[Reports](./reports.md)** - Financial reporting capabilities
7. **[Troubleshooting](./troubleshooting.md)** - Common issues and solutions

## 🚀 Quick Start

### Access the Financial Dashboard
Navigate to: `/management/financial/dashboard`
- Available to: Admin and Manager roles
- Menu location: 💰 Financial Dashboard

### Key Features
- Real-time sales metrics
- Cash position tracking
- Payment method analysis
- Variance detection
- Trend visualization
- Actionable alerts
- VAT return management and alerts

## 📊 Core Modules

### 1. Receipts Management
- POS transaction analysis
- Payment type filtering
- Export capabilities
- See: [Till Review System](../management/receipts.md)

### 2. Cash Reconciliation
- Physical cash counting
- Variance tracking
- Float management
- See: [Cash Reconciliation](../features/cash-reconciliation.md)

### 3. VAT Returns Management
- Outstanding periods alerts
- Current period tracking
- Deadline countdown
- Return history and export
- See: [VAT Dashboard](../features/vat-dashboard.md)

### 4. Invoices & Suppliers
- Invoice tracking
- Supplier payments
- VAT management
- See: [OSAccounts Integration](../features/osaccounts-integration.md)

## 🔧 Technical Details

### Controllers
- `FinancialDashboardController` - Main dashboard logic
- `VatDashboardController` - VAT return dashboard
- `CashReconciliationController` - Cash management
- `TillReviewController` - Receipt analysis

### Models
- `VatReturn` - VAT return periods and calculations
- `CashReconciliation` - Daily reconciliation records
- `CashReconciliationPayment` - Supplier payments
- `Receipt` (POS) - Transaction data
- `Payment` (POS) - Payment details

### Database Connections
- Main database: Application data
- POS database: Transaction data (read-only)

## 📈 Performance

The financial system uses optimized queries following patterns from the [Sales Data Import Plan](../features/sales-data-import-plan.md):
- Pre-aggregated metrics
- Efficient joins
- Caching strategies
- Background processing

## 🔐 Security

### Permissions
- `financial.dashboard.view` - View dashboard
- `cash_reconciliation.view` - View reconciliations
- `cash_reconciliation.create` - Create reconciliations
- `cash_reconciliation.export` - Export data

### Role Requirements
- **Admin**: Full access to all financial features
- **Manager**: Access to dashboard and reconciliation
- **Employee**: Limited access based on permissions

## 📝 Related Documentation

- [User Roles & Permissions](../features/user-roles-permissions.md)
- [Performance Optimization Guide](../development/performance-optimization-guide.md)
- [Architecture Overview](../architecture/overview.md)