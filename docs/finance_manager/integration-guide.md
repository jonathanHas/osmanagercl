# Financial System Integration Guide

## Overview

This guide covers how to integrate the various financial modules and connect with external systems.

## Internal Module Integration

### 1. Receipts → Cash Reconciliation

The receipts system automatically feeds into cash reconciliation:

```php
// In CashReconciliationController
$posTotal = DB::connection('pos')
    ->table('RECEIPTS as r')
    ->join('PAYMENTS as p', 'r.ID', '=', 'p.RECEIPT')
    ->whereDate('r.DATENEW', $date)
    ->whereIn('p.PAYMENT', ['cash', 'cashrefund'])
    ->sum('p.TOTAL');

// Compare with physical count
$variance = $physicalCount - $posTotal;
```

**Integration Points:**
- POS cash totals auto-populate in reconciliation
- Variance calculated automatically
- Historical data preserved for audit

### 2. Cash Reconciliation → Financial Dashboard

Dashboard pulls reconciliation data for cash position:

```php
// In FinancialDashboardController
$cashPosition = $this->getCashPosition($date);
// Uses latest reconciliation + sales since - payments since
```

**Data Flow:**
1. Last reconciliation provides base float
2. Add cash sales since reconciliation
3. Subtract supplier payments
4. Calculate expected position

### 3. Invoices → Supplier Payments

Link invoices to cash payments from till:

```php
// Recording payment against invoice
$payment = CashReconciliationPayment::create([
    'cash_reconciliation_id' => $reconciliation->id,
    'supplier_name' => $invoice->supplier->name,
    'amount' => $amount,
    'invoice_id' => $invoice->id // Future enhancement
]);

// Update invoice status
$invoice->update([
    'paid_amount' => $invoice->paid_amount + $amount,
    'status' => $invoice->paid_amount >= $invoice->total ? 'paid' : 'partial'
]);
```

### 4. Products → Financial Metrics

Product costs feed into margin calculations:

```php
// Calculate product margins
$margin = ($product->PRICESELL - $product->PRICEBUY) / $product->PRICESELL * 100;

// Aggregate for dashboard
$totalMargin = DB::table('TICKETLINES')
    ->join('PRODUCTS', 'TICKETLINES.PRODUCT', '=', 'PRODUCTS.ID')
    ->selectRaw('SUM((PRODUCTS.PRICESELL - PRODUCTS.PRICEBUY) * TICKETLINES.UNITS) as margin')
    ->whereDate('created_at', $date)
    ->first();
```

## External System Integration

### POS System (uniCenta)

**Connection Configuration:**
```env
POS_DB_CONNECTION=mysql
POS_DB_HOST=localhost
POS_DB_PORT=3306
POS_DB_DATABASE=unicentaopos
POS_DB_USERNAME=pos_user
POS_DB_PASSWORD=secure_password
```

**Key Integration Tables:**
- RECEIPTS - Transaction headers
- PAYMENTS - Payment details
- TICKETS - Line items
- CLOSEDCASH - Till sessions
- PRODUCTS - Product master

**Best Practices:**
1. Always use read-only connection
2. Cache frequently accessed data
3. Use database views for complex queries
4. Implement retry logic for connection issues

### Banking Integration (Future)

**Planned Features:**
```php
// Bank statement import
class BankStatementImporter
{
    public function import($file)
    {
        $transactions = $this->parseFile($file);
        
        foreach ($transactions as $transaction) {
            $this->matchToInvoice($transaction);
            $this->recordInLedger($transaction);
        }
    }
    
    private function matchToInvoice($transaction)
    {
        // Match by amount and reference
        $invoice = Invoice::where('total', $transaction->amount)
            ->where('reference', 'LIKE', "%{$transaction->reference}%")
            ->first();
            
        if ($invoice) {
            $invoice->markPaid($transaction->date);
        }
    }
}
```

### Accounting Software Export

**QuickBooks Integration:**
```php
class QuickBooksExporter
{
    public function exportDailySales($date)
    {
        $sales = $this->getDailySales($date);
        
        return [
            'journal_entry' => [
                'date' => $date,
                'entries' => [
                    [
                        'account' => 'Cash',
                        'debit' => $sales->cash_total,
                    ],
                    [
                        'account' => 'Card Receivables',
                        'debit' => $sales->card_total,
                    ],
                    [
                        'account' => 'Sales Revenue',
                        'credit' => $sales->net_sales,
                    ],
                    [
                        'account' => 'VAT Payable',
                        'credit' => $sales->vat_total,
                    ]
                ]
            ]
        ];
    }
}
```

**Sage Integration:**
```php
class SageExporter
{
    public function exportInvoices($period)
    {
        $invoices = Invoice::whereBetween('invoice_date', $period)->get();
        
        $csv = "Type,Account,Date,Reference,Description,Net,VAT,Gross\n";
        
        foreach ($invoices as $invoice) {
            $csv .= sprintf(
                "PI,%s,%s,%s,%s,%.2f,%.2f,%.2f\n",
                $invoice->supplier->account_code,
                $invoice->invoice_date,
                $invoice->invoice_number,
                $invoice->description,
                $invoice->subtotal,
                $invoice->vat_amount,
                $invoice->total
            );
        }
        
        return $csv;
    }
}
```

## API Integration

### RESTful API Setup

**Creating API Routes:**
```php
// routes/api.php
Route::prefix('v1')->middleware('auth:api')->group(function () {
    Route::get('/financial/dashboard', [ApiFinancialController::class, 'dashboard']);
    Route::get('/financial/sales/{date}', [ApiFinancialController::class, 'dailySales']);
    Route::post('/financial/reconciliation', [ApiFinancialController::class, 'createReconciliation']);
});
```

**API Controller:**
```php
class ApiFinancialController extends Controller
{
    public function dashboard(Request $request)
    {
        $date = $request->input('date', today());
        
        return response()->json([
            'date' => $date,
            'metrics' => $this->getMetrics($date),
            'alerts' => $this->getAlerts($date)
        ]);
    }
}
```

### Webhook Implementation

**Sending Webhooks:**
```php
class WebhookService
{
    public function sendReconciliationAlert($reconciliation)
    {
        if (abs($reconciliation->variance) > 50) {
            $this->send('reconciliation.variance', [
                'id' => $reconciliation->id,
                'variance' => $reconciliation->variance,
                'date' => $reconciliation->date
            ]);
        }
    }
    
    private function send($event, $data)
    {
        $webhooks = Webhook::where('event', $event)->active()->get();
        
        foreach ($webhooks as $webhook) {
            dispatch(new SendWebhook($webhook->url, $event, $data));
        }
    }
}
```

## Database Synchronization

### Real-time Sync Strategy

```php
// Using database triggers for real-time updates
class RealtimeSyncService
{
    public function setupTriggers()
    {
        // POS database trigger
        DB::connection('pos')->unprepared('
            CREATE TRIGGER after_payment_insert
            AFTER INSERT ON PAYMENTS
            FOR EACH ROW
            BEGIN
                INSERT INTO sync_queue (table_name, record_id, action)
                VALUES ("PAYMENTS", NEW.ID, "INSERT");
            END
        ');
    }
    
    public function processSyncQueue()
    {
        $items = DB::table('sync_queue')->where('processed', false)->get();
        
        foreach ($items as $item) {
            $this->syncRecord($item);
            $item->update(['processed' => true]);
        }
    }
}
```

### Batch Processing

```php
// Nightly batch processing
class BatchProcessor
{
    public function processDaily()
    {
        // Import sales data
        $this->importSalesData();
        
        // Calculate summaries
        $this->calculateDailySummaries();
        
        // Generate reports
        $this->generateReports();
        
        // Send notifications
        $this->sendDailyDigest();
    }
    
    private function importSalesData()
    {
        $yesterday = Carbon::yesterday();
        
        $sales = DB::connection('pos')
            ->table('RECEIPTS')
            ->whereDate('DATENEW', $yesterday)
            ->get();
            
        foreach ($sales->chunk(100) as $chunk) {
            SalesImport::insert($chunk->toArray());
        }
    }
}
```

## Event-Driven Architecture

### Financial Events

```php
// app/Events/ReconciliationCompleted.php
class ReconciliationCompleted
{
    public function __construct(
        public CashReconciliation $reconciliation
    ) {}
}

// app/Listeners/UpdateFinancialDashboard.php
class UpdateFinancialDashboard
{
    public function handle(ReconciliationCompleted $event)
    {
        Cache::forget("dashboard.{$event->reconciliation->date}");
        
        // Recalculate metrics
        $metrics = app(FinancialService::class)
            ->calculateMetrics($event->reconciliation->date);
            
        // Cache new metrics
        Cache::put(
            "dashboard.{$event->reconciliation->date}", 
            $metrics, 
            now()->addMinutes(5)
        );
    }
}
```

### Event Registration

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    ReconciliationCompleted::class => [
        UpdateFinancialDashboard::class,
        SendVarianceAlert::class,
        UpdateFloatCalculation::class,
    ],
    InvoicePaid::class => [
        UpdateSupplierBalance::class,
        RecordPaymentInLedger::class,
    ],
];
```

## Testing Integration

### Integration Tests

```php
class FinancialIntegrationTest extends TestCase
{
    public function test_reconciliation_updates_dashboard()
    {
        // Create reconciliation
        $reconciliation = CashReconciliation::factory()->create([
            'date' => today(),
            'variance' => 25.00
        ]);
        
        // Check dashboard reflects change
        $response = $this->get('/management/financial/dashboard');
        
        $response->assertJson([
            'cashPosition' => [
                'last_counted' => today()->format('Y-m-d')
            ],
            'todayMetrics' => [
                'variance' => 25.00
            ]
        ]);
    }
    
    public function test_pos_sync_calculates_correctly()
    {
        // Insert test POS data
        DB::connection('pos')->table('PAYMENTS')->insert([
            'ID' => 'TEST001',
            'RECEIPT' => 'RCP001',
            'PAYMENT' => 'cash',
            'TOTAL' => 100.00
        ]);
        
        // Run sync
        $service = new PosSyncService();
        $service->syncDate(today());
        
        // Verify calculation
        $metrics = DailyMetric::whereDate('date', today())->first();
        $this->assertEquals(100.00, $metrics->cash_total);
    }
}
```

## Monitoring & Logging

### Integration Health Checks

```php
class IntegrationHealthCheck
{
    public function check(): array
    {
        return [
            'pos_connection' => $this->checkPosConnection(),
            'sync_lag' => $this->checkSyncLag(),
            'webhook_queue' => $this->checkWebhookQueue(),
            'api_response_time' => $this->checkApiResponseTime(),
        ];
    }
    
    private function checkPosConnection(): bool
    {
        try {
            DB::connection('pos')->getPdo();
            return true;
        } catch (\Exception $e) {
            Log::error('POS connection failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    private function checkSyncLag(): int
    {
        $latest = DB::connection('pos')
            ->table('RECEIPTS')
            ->max('DATENEW');
            
        return Carbon::parse($latest)->diffInMinutes(now());
    }
}
```

### Audit Logging

```php
class FinancialAuditLog
{
    public static function log($action, $data)
    {
        DB::table('financial_audit_log')->insert([
            'user_id' => auth()->id(),
            'action' => $action,
            'data' => json_encode($data),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now()
        ]);
    }
}

// Usage
FinancialAuditLog::log('reconciliation.created', [
    'id' => $reconciliation->id,
    'variance' => $reconciliation->variance
]);
```

## Troubleshooting Integration Issues

### Common Problems

1. **POS Connection Timeout**
   - Check network connectivity
   - Verify database credentials
   - Increase connection timeout

2. **Data Sync Delays**
   - Check queue workers are running
   - Verify cron jobs are configured
   - Monitor database performance

3. **Calculation Mismatches**
   - Verify timezone settings
   - Check for duplicate transactions
   - Validate rounding rules

4. **API Rate Limiting**
   - Implement exponential backoff
   - Use webhook subscriptions
   - Cache frequently accessed data

## Best Practices

1. **Always use transactions** for financial operations
2. **Implement idempotency** for payment processing
3. **Log all financial changes** for audit trail
4. **Use decimal types** for monetary values
5. **Test with production-like data**
6. **Monitor integration health** continuously
7. **Document all custom integrations**
8. **Version your API endpoints**
9. **Implement proper error handling**
10. **Use queues for heavy processing**