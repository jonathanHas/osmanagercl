# VAT Dashboard Documentation

## Overview

The VAT Dashboard provides a comprehensive view of VAT return management, including outstanding periods that require submission, recent submissions history, and unsubmitted invoice summaries. The dashboard automatically identifies periods that are past their due dates and provides direct links to create returns with pre-filled dates.

## Features

### 1. Outstanding Periods Alert
- **Automatic Detection**: Identifies VAT periods past their due date (15 days after period end)
- **Direct Links**: Each outstanding period links directly to VAT return creation with pre-filled dates
- **Overdue Tracking**: Shows how many days overdue each period is
- **Invoice Count**: Displays the number of unassigned invoices for each period

### 2. Current Period Information
- **Real-time Status**: Shows the current VAT period in progress
- **Date Range**: Displays start and end dates of the current period
- **Days Remaining**: Countdown to the period end
- **Invoice Tracking**: Number of unassigned invoices in the current period
- **Status Indicator**: Shows if a return has already been filed for the current period

### 3. Next Deadline Tracker
- **Automatic Calculation**: Shows the next VAT submission deadline (period end + 15 days)
- **Visual Status**: Color-coded urgency indicators:
  - **Red (Urgent)**: Less than 7 days remaining
  - **Yellow (Due Soon)**: 7-14 days remaining
  - **Green (On Track)**: More than 14 days remaining
- **Human-readable Format**: Shows deadline in "X days from now" format

### 4. Unsubmitted Invoices Summary
- **Total Metrics**: Count, amount, and VAT totals for all unassigned invoices
- **Date Range**: Shows earliest and latest unassigned invoice dates
- **Monthly Breakdown**: Table showing invoice counts and VAT totals by month
- **Quick Access**: Direct link to view all unassigned invoices

### 5. Recent Submissions
- **Latest Returns**: Shows the 6 most recent VAT returns
- **Status Display**: Draft, Finalized, or Submitted status badges
- **Historical Indicator**: Identifies returns imported from legacy system
- **Key Information**: Period, invoice count, VAT total, submission date
- **Quick Actions**: Direct links to view each return

### 6. Yearly Statistics
- **Annual Comparison**: Shows current year and previous year statistics
- **Key Metrics**:
  - Number of returns filed
  - Total net amount
  - Total VAT amount
  - Total gross amount
- **Visual Layout**: Side-by-side comparison cards

### 7. History View
- **Complete Archive**: Paginated list of all VAT returns
- **Filtering Options**:
  - Filter by year
  - Filter by status (Draft, Finalized, Submitted)
- **Detailed Information**: 
  - Period dates
  - Invoice counts
  - Net, VAT, and gross amounts
  - Creation and submission dates
  - User who created/finalized
- **Export Access**: Direct links to export finalized returns
- **Page Statistics**: Summary totals for displayed returns

## Access Control

The VAT Dashboard is protected by role-based access control:
- **Access Roles**: Admin and Manager only
- **Middleware**: `role:admin,manager`
- **Navigation**: Conditionally displayed based on user role

## Routes

```php
// VAT Dashboard routes
Route::prefix('management/vat-dashboard')->name('management.vat-dashboard.')->group(function () {
    Route::get('/', [VatDashboardController::class, 'index'])->name('index');
    Route::get('/history', [VatDashboardController::class, 'history'])->name('history');
});
```

## Period Configuration

The system uses standard Irish bi-monthly VAT periods:
- **Jan-Feb**: January 1 - February 28/29
- **Mar-Apr**: March 1 - April 30
- **May-Jun**: May 1 - June 30
- **Jul-Aug**: July 1 - August 31
- **Sep-Oct**: September 1 - October 31
- **Nov-Dec**: November 1 - December 31

Each period has a 15-day grace period for submission after the period end date.

## Technical Implementation

### Controller: `VatDashboardController`

Key methods:
- `index()`: Main dashboard view with all summary data
- `history()`: Complete paginated history with filtering
- `getOutstandingPeriods()`: Identifies periods requiring submission
- `getCurrentPeriodInfo()`: Calculates current period details
- `getNextDeadline()`: Determines next submission deadline
- `getUnsubmittedInvoicesSummary()`: Aggregates unassigned invoice data
- `getYearlyStatistics()`: Calculates annual comparison metrics

### Views

- `management/vat-dashboard/index.blade.php`: Main dashboard view
- `management/vat-dashboard/history.blade.php`: Complete history view

### Database Queries

The dashboard uses optimized queries with eager loading and aggregation:
- Invoice counts and sums use database-level aggregation
- Recent submissions use relationship counting
- Monthly breakdowns use GROUP BY for efficiency

## Usage

1. **Access Dashboard**: Navigate to "VAT Dashboard" in the admin sidebar
2. **Review Alerts**: Check for any outstanding periods shown in red
3. **Create Returns**: Click on outstanding period links to create returns
4. **Monitor Progress**: Use current period info to track ongoing collections
5. **View History**: Click "View History" for complete archive
6. **Export Data**: Use export links on finalized returns for CSV downloads

## Future Enhancements

Planned features for future releases:
- VAT on sales tracking and reconciliation
- Automated email reminders for upcoming deadlines
- Graphical charts for VAT trends
- Integration with Revenue Online Service (ROS)
- Bulk operations for multiple periods
- Custom period configurations for special cases

## Related Documentation

- [VAT Returns Management](./vat-returns.md)
- [Invoice Management](./invoice-management.md)
- [OSAccounts Integration](./osaccounts-integration.md)
- [Cash Reconciliation](./cash-reconciliation.md)