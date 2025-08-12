# Financial Reports Documentation

## Available Reports

### 1. Daily Cash Report

**Purpose:** End-of-day cash position and reconciliation summary.

**Contents:**
- Opening float
- Cash sales
- Cash refunds
- Supplier payments
- Physical count
- Variance analysis
- Closing float

**Format Example:**
```
DAILY CASH REPORT - 2025-08-12
================================
Terminal: Till 1
Cashier: John Doe

OPENING POSITION
Notes Float:        €200.00
Coins Float:        €50.00
Total Opening:      €250.00

CASH TRANSACTIONS
Sales:              €650.00
Refunds:           -€25.00
Net Cash In:        €625.00

CASH PAYMENTS
Supplier A:        -€150.00
Supplier B:        -€75.00
Total Out:         -€225.00

EXPECTED CASH:      €650.00

PHYSICAL COUNT
€50 notes (10):     €500.00
€20 notes (5):      €100.00
€10 notes (3):      €30.00
€5 notes (2):       €10.00
€2 coins (5):       €10.00
€1 coins (3):       €3.00
50c coins (4):      €2.00
20c coins (5):      €1.00
10c coins (8):      €0.80
TOTAL COUNTED:      €656.80

VARIANCE:           +€6.80

CLOSING POSITION
Notes for Float:    €200.00
Coins for Float:    €50.00
To Bank:           €406.80
```

### 2. Sales Summary Report

**Purpose:** Comprehensive sales analysis by payment type and category.

**Key Metrics:**
- Total transactions
- Payment method breakdown
- Category performance
- Hourly distribution
- Average transaction value

**SQL Query:**
```sql
SELECT 
    DATE(r.DATENEW) as date,
    COUNT(DISTINCT r.ID) as transactions,
    p.PAYMENT as payment_type,
    SUM(p.TOTAL) as total,
    AVG(p.TOTAL) as avg_transaction
FROM RECEIPTS r
JOIN PAYMENTS p ON r.ID = p.RECEIPT
WHERE DATE(r.DATENEW) = ?
GROUP BY DATE(r.DATENEW), p.PAYMENT
```

### 3. Weekly Performance Report

**Contents:**
- Daily sales comparison
- Week-over-week growth
- Best/worst performing days
- Payment trends
- Cash flow summary

**Visualization:**
```
WEEKLY PERFORMANCE (Aug 5-11, 2025)
====================================

Daily Sales:
Monday:     ████████████ €1,200
Tuesday:    ██████████   €1,000  
Wednesday:  ███████████  €1,100
Thursday:   █████████████ €1,300
Friday:     ████████████████ €1,600
Saturday:   ██████████████ €1,400
Sunday:     ████████     €800

Total:      €8,400 (↑12% vs last week)
Daily Avg:  €1,200
Best Day:   Friday (€1,600)
Worst Day:  Sunday (€800)
```

### 4. Monthly Financial Summary

**Sections:**
1. **Revenue Analysis**
   - Gross sales
   - Returns and refunds
   - Net revenue
   - VAT collected

2. **Payment Analysis**
   - Cash vs card ratio
   - Account sales
   - Payment trends

3. **Reconciliation Summary**
   - Days reconciled
   - Total variance
   - Average daily variance

4. **Supplier Payments**
   - Total paid from till
   - By supplier breakdown
   - Payment frequency

### 5. VAT Report

**Purpose:** Tax compliance and filing preparation.

**Contents:**
```
VAT REPORT - August 2025
========================

SALES VAT
Standard Rate (23%):
  Net Sales:        €10,000.00
  VAT Collected:    €2,300.00

Reduced Rate (13.5%):
  Net Sales:        €5,000.00
  VAT Collected:    €675.00

Zero Rate (0%):
  Net Sales:        €2,000.00
  VAT Collected:    €0.00

TOTAL VAT COLLECTED: €2,975.00

PURCHASE VAT
Invoices Processed:  45
VAT Paid:           €1,850.00

NET VAT POSITION:    €1,125.00
```

### 6. Variance Analysis Report

**Purpose:** Identify and analyze cash discrepancies.

**Contents:**
- Daily variances chart
- Variance by cashier
- Variance by terminal
- Pattern analysis
- Recommendations

**Example Output:**
```
VARIANCE ANALYSIS - August 2025
================================

Summary Statistics:
- Total Variance: +€45.50
- Average Daily: +€1.47
- Days Over: 18
- Days Under: 12
- Days Exact: 1

Largest Variances:
1. Aug 15: +€25.00 (Investigation: Refund not processed)
2. Aug 22: -€18.50 (Investigation: Counting error)
3. Aug 8:  +€15.00 (Investigation: Tips included)

By Terminal:
Till 1: +€30.00 (20 days)
Till 2: +€15.50 (11 days)

Recommendations:
- Additional training for evening shift
- Review refund procedures
- Implement dual count verification
```

## Report Generation

### Manual Generation

**Via Dashboard:**
1. Navigate to Financial Dashboard
2. Select date range
3. Click "Export Report"
4. Choose format (CSV, PDF)
5. Download file

**Via Command Line:**
```bash
# Generate daily cash report
php artisan report:daily-cash --date=2025-08-12

# Generate monthly summary
php artisan report:monthly --month=2025-08

# Email report
php artisan report:daily-cash --date=2025-08-12 --email=manager@example.com
```

### Scheduled Reports

**Configuration in `app/Console/Kernel.php`:**
```php
protected function schedule(Schedule $schedule)
{
    // Daily cash report at 11 PM
    $schedule->command('report:daily-cash')
        ->dailyAt('23:00')
        ->emailOutputTo('manager@example.com');
    
    // Weekly summary every Monday
    $schedule->command('report:weekly')
        ->weeklyOn(1, '8:00');
    
    // Monthly report on 1st of month
    $schedule->command('report:monthly')
        ->monthlyOn(1, '09:00');
}
```

### Custom Reports

**Creating a Custom Report:**
```php
class CustomFinancialReport
{
    public function generate($parameters)
    {
        $data = $this->fetchData($parameters);
        $formatted = $this->formatData($data);
        
        return view('reports.custom', compact('formatted'));
    }
    
    private function fetchData($parameters)
    {
        return DB::table('cash_reconciliations')
            ->whereBetween('date', $parameters['date_range'])
            ->where('till_id', $parameters['till'])
            ->get();
    }
    
    private function formatData($data)
    {
        // Format for display/export
        return $data->map(function ($item) {
            return [
                'date' => Carbon::parse($item->date)->format('d/m/Y'),
                'variance' => number_format($item->variance, 2),
                'status' => $item->variance == 0 ? 'Balanced' : 'Variance'
            ];
        });
    }
}
```

## Export Formats

### CSV Export

**Structure:**
```csv
Date,Transactions,Cash,Card,Debt,Total,Variance
2025-08-12,145,650.00,475.00,125.00,1250.00,12.50
2025-08-13,132,580.00,520.00,100.00,1200.00,-5.00
```

**Generation Code:**
```php
public function exportCsv($data)
{
    $csv = "Date,Transactions,Cash,Card,Debt,Total,Variance\n";
    
    foreach ($data as $row) {
        $csv .= sprintf(
            "%s,%d,%.2f,%.2f,%.2f,%.2f,%.2f\n",
            $row->date,
            $row->transactions,
            $row->cash,
            $row->card,
            $row->debt,
            $row->total,
            $row->variance
        );
    }
    
    return response($csv)
        ->header('Content-Type', 'text/csv')
        ->header('Content-Disposition', 'attachment; filename="report.csv"');
}
```

### PDF Export

**Using Laravel DomPDF:**
```php
use Barryvdh\DomPDF\Facade\Pdf;

public function exportPdf($data)
{
    $pdf = Pdf::loadView('reports.financial', compact('data'));
    
    return $pdf->download('financial-report.pdf');
}
```

**Template Example:**
```blade
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Financial Report - {{ $date }}</h1>
    
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Sales</th>
                <th>Variance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
            <tr>
                <td>{{ $row->date }}</td>
                <td>€{{ number_format($row->sales, 2) }}</td>
                <td>€{{ number_format($row->variance, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
```

### Excel Export

**Using Laravel Excel:**
```php
use Maatwebsite\Excel\Facades\Excel;

class FinancialReportExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return CashReconciliation::all();
    }
    
    public function headings(): array
    {
        return [
            'Date',
            'Till',
            'Cash Counted',
            'POS Total',
            'Variance'
        ];
    }
}

// Usage
return Excel::download(new FinancialReportExport, 'report.xlsx');
```

## Report Templates

### Email Template

```blade
@component('mail::message')
# Daily Financial Report - {{ $date }}

## Summary
- **Total Sales:** €{{ number_format($sales, 2) }}
- **Transactions:** {{ $transactions }}
- **Cash Variance:** €{{ number_format($variance, 2) }}

## Payment Breakdown
@component('mail::table')
| Type | Amount | Percentage |
|:-----|-------:|----------:|
| Cash | €{{ number_format($cash, 2) }} | {{ $cashPercent }}% |
| Card | €{{ number_format($card, 2) }} | {{ $cardPercent }}% |
| Account | €{{ number_format($debt, 2) }} | {{ $debtPercent }}% |
@endcomponent

@if($variance != 0)
## Action Required
Cash variance of €{{ number_format(abs($variance), 2) }} detected.
Please review the reconciliation.

@component('mail::button', ['url' => $reconciliationUrl])
View Reconciliation
@endcomponent
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
```

## Report Permissions

### Access Control

```php
// Middleware for report access
Route::middleware(['auth', 'permission:reports.view'])->group(function () {
    Route::get('/reports/daily', [ReportController::class, 'daily']);
    Route::get('/reports/export', [ReportController::class, 'export'])
        ->middleware('permission:reports.export');
});
```

### Permission Levels

| Role | View | Export | Schedule | Custom |
|------|------|--------|----------|--------|
| Admin | ✓ | ✓ | ✓ | ✓ |
| Manager | ✓ | ✓ | ✓ | ✗ |
| Employee | ✓ | ✗ | ✗ | ✗ |

## Performance Optimization

### Query Optimization

```php
// Use query builder for large datasets
DB::table('receipts')
    ->select(DB::raw('DATE(DATENEW) as date'), DB::raw('COUNT(*) as count'))
    ->whereBetween('DATENEW', [$start, $end])
    ->groupBy(DB::raw('DATE(DATENEW)'))
    ->chunk(1000, function ($receipts) {
        // Process chunk
    });
```

### Caching Reports

```php
public function getDailyReport($date)
{
    return Cache::remember("report.daily.{$date}", 3600, function () use ($date) {
        return $this->generateDailyReport($date);
    });
}
```

### Background Generation

```php
// Queue large reports
class GenerateMonthlyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    
    public function handle()
    {
        $report = app(ReportService::class)->generateMonthly();
        
        Storage::put("reports/monthly-{$this->month}.pdf", $report);
        
        Mail::to($this->recipient)->send(new MonthlyReportReady($this->month));
    }
}
```

## Audit Trail

### Report Access Logging

```php
class ReportAccessLog
{
    public static function log($report, $format)
    {
        DB::table('report_access_log')->insert([
            'user_id' => auth()->id(),
            'report_type' => $report,
            'format' => $format,
            'parameters' => json_encode(request()->all()),
            'ip_address' => request()->ip(),
            'created_at' => now()
        ]);
    }
}
```

## Future Enhancements

1. **Interactive Dashboards** - Real-time drill-down capabilities
2. **Predictive Analytics** - Forecast future performance
3. **Comparative Analysis** - Year-over-year comparisons
4. **Custom Report Builder** - Drag-and-drop interface
5. **Mobile Reports** - Optimized for mobile devices
6. **API Access** - Programmatic report generation
7. **Multi-location Consolidation** - Combined reports for chains