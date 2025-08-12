# Financial System Troubleshooting Guide

## Common Issues & Solutions

### Dashboard Issues

#### Problem: Dashboard shows no data
**Symptoms:**
- Empty KPI cards
- No transactions displayed
- Zero values everywhere

**Solutions:**
1. **Check date selection:**
   ```php
   // Verify date has transactions
   $count = DB::connection('pos')
       ->table('RECEIPTS')
       ->whereDate('DATENEW', $date)
       ->count();
   ```

2. **Verify POS connection:**
   ```bash
   php artisan tinker
   >>> DB::connection('pos')->getPdo();
   ```

3. **Check user permissions:**
   ```php
   >>> auth()->user()->hasRole(['admin', 'manager'])
   >>> auth()->user()->can('financial.dashboard.view')
   ```

#### Problem: Incorrect calculations
**Symptoms:**
- Totals don't match POS
- Variance calculations wrong
- Missing transactions

**Solutions:**
1. **Check timezone settings:**
   ```php
   // config/app.php
   'timezone' => 'Europe/Dublin',
   
   // Verify in tinker
   >>> Carbon::now()->timezone
   ```

2. **Look for duplicate transactions:**
   ```sql
   SELECT ID, COUNT(*) as count
   FROM RECEIPTS
   GROUP BY ID
   HAVING count > 1;
   ```

3. **Verify payment type mappings:**
   ```php
   // Check actual payment types in database
   DB::connection('pos')
       ->table('PAYMENTS')
       ->distinct()
       ->pluck('PAYMENT');
   ```

### Cash Reconciliation Issues

#### Problem: Cannot save reconciliation
**Error:** "SQLSTATE[23000]: Integrity constraint violation"

**Solutions:**
1. **Check for existing reconciliation:**
   ```php
   $exists = CashReconciliation::where('date', $date)
       ->where('till_id', $tillId)
       ->exists();
   ```

2. **Verify UUID generation:**
   ```php
   // Model should use HasUuids trait
   use Illuminate\Database\Eloquent\Concerns\HasUuids;
   ```

3. **Check required fields:**
   ```php
   $validator = Validator::make($request->all(), [
       'date' => 'required|date',
       'till_id' => 'required',
       // ... other fields
   ]);
   ```

#### Problem: Variance always shows zero
**Symptoms:**
- Physical count matches POS exactly (unlikely)
- Variance calculation not working

**Solutions:**
1. **Verify calculation formula:**
   ```php
   $counted = $this->calculateTotalCash();
   $posTotal = $this->pos_cash_total;
   $variance = $counted - $posTotal;
   ```

2. **Check denomination calculations:**
   ```php
   // Test calculation
   $total = 
       ($cash_50 * 50) +
       ($cash_20 * 20) +
       ($cash_10 * 10) +
       ($cash_5 * 5) +
       ($cash_2 * 2) +
       ($cash_1 * 1) +
       ($cash_50c * 0.50) +
       ($cash_20c * 0.20) +
       ($cash_10c * 0.10);
   ```

### POS Integration Issues

#### Problem: Cannot connect to POS database
**Error:** "SQLSTATE[HY000] [2002] Connection refused"

**Solutions:**
1. **Check database configuration:**
   ```env
   POS_DB_CONNECTION=mysql
   POS_DB_HOST=localhost
   POS_DB_PORT=3306
   POS_DB_DATABASE=unicentaopos
   POS_DB_USERNAME=pos_user
   POS_DB_PASSWORD=secure_password
   ```

2. **Test connection manually:**
   ```bash
   mysql -h localhost -P 3306 -u pos_user -p unicentaopos
   ```

3. **Verify firewall rules:**
   ```bash
   sudo ufw status
   telnet localhost 3306
   ```

4. **Check MySQL is running:**
   ```bash
   sudo systemctl status mysql
   sudo systemctl restart mysql
   ```

#### Problem: Slow POS queries
**Symptoms:**
- Dashboard takes >5 seconds to load
- Timeout errors
- High database CPU usage

**Solutions:**
1. **Add missing indexes:**
   ```sql
   -- On POS database
   CREATE INDEX idx_receipts_datenew ON RECEIPTS(DATENEW);
   CREATE INDEX idx_payments_receipt ON PAYMENTS(RECEIPT);
   CREATE INDEX idx_payments_payment ON PAYMENTS(PAYMENT);
   ```

2. **Optimize queries:**
   ```php
   // Bad - N+1 problem
   foreach ($receipts as $receipt) {
       $payments = Payment::where('RECEIPT', $receipt->ID)->get();
   }
   
   // Good - Eager loading
   $receipts = Receipt::with('payments')->get();
   ```

3. **Implement caching:**
   ```php
   $metrics = Cache::remember("metrics.{$date}", 300, function () use ($date) {
       return $this->calculateMetrics($date);
   });
   ```

### Invoice & Supplier Issues

#### Problem: Invoices not linking to suppliers
**Symptoms:**
- Supplier name shows as null
- Cannot create invoices
- Supplier dropdown empty

**Solutions:**
1. **Check supplier data:**
   ```php
   >>> Supplier::count()
   >>> DB::table('suppliers')->count()
   ```

2. **Verify relationships:**
   ```php
   // Invoice model
   public function supplier()
   {
       return $this->belongsTo(Supplier::class);
   }
   ```

3. **Import suppliers from POS:**
   ```bash
   php artisan suppliers:sync
   ```

### Report Generation Issues

#### Problem: Export fails or times out
**Symptoms:**
- Browser timeout
- Incomplete CSV files
- Memory exhausted errors

**Solutions:**
1. **Increase memory limit:**
   ```php
   // In controller or command
   ini_set('memory_limit', '512M');
   set_time_limit(300);
   ```

2. **Use chunking for large datasets:**
   ```php
   DB::table('receipts')
       ->orderBy('id')
       ->chunk(1000, function ($receipts) use ($csv) {
           foreach ($receipts as $receipt) {
               fputcsv($csv, $receipt->toArray());
           }
       });
   ```

3. **Queue large reports:**
   ```php
   dispatch(new GenerateLargeReport($parameters));
   ```

#### Problem: PDF generation errors
**Error:** "DOMDocument::loadHTML(): Warning"

**Solutions:**
1. **Fix HTML encoding:**
   ```php
   $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
   ```

2. **Suppress warnings:**
   ```php
   libxml_use_internal_errors(true);
   $pdf->loadHTML($html);
   libxml_clear_errors();
   ```

### Permission & Access Issues

#### Problem: Users cannot access financial features
**Symptoms:**
- 403 Forbidden errors
- Menu items not visible
- Redirect to home page

**Solutions:**
1. **Check role assignment:**
   ```php
   >>> $user = User::find($id);
   >>> $user->roles->pluck('name');
   >>> $user->hasRole('manager');
   ```

2. **Verify permissions:**
   ```php
   >>> $user->getAllPermissions()->pluck('name');
   >>> $user->can('financial.dashboard.view');
   ```

3. **Clear permission cache:**
   ```bash
   php artisan permission:cache:reset
   php artisan cache:clear
   ```

### Data Integrity Issues

#### Problem: Duplicate transactions appearing
**Symptoms:**
- Same receipt shown multiple times
- Inflated sales totals
- Incorrect transaction counts

**Solutions:**
1. **Check for duplicate imports:**
   ```sql
   SELECT RECEIPT, COUNT(*) as count
   FROM till_review_cache
   GROUP BY RECEIPT
   HAVING count > 1;
   ```

2. **Add unique constraints:**
   ```php
   // Migration
   $table->unique(['date', 'receipt_id']);
   ```

3. **Clean duplicate data:**
   ```sql
   DELETE t1 FROM transactions t1
   INNER JOIN transactions t2
   WHERE t1.id > t2.id
   AND t1.receipt_id = t2.receipt_id;
   ```

## Diagnostic Commands

### Health Check Script
```bash
#!/bin/bash
# financial_health_check.sh

echo "Checking Financial System Health..."

# Check database connections
php artisan db:test

# Check POS connection
php artisan pos:test

# Verify cache
php artisan cache:test

# Check queue workers
php artisan queue:status

# Test calculations
php artisan financial:validate --date=today
```

### Laravel Commands

```bash
# Clear all caches
php artisan optimize:clear

# Check route registration
php artisan route:list | grep financial

# Test specific service
php artisan tinker
>>> app(FinancialDashboardController::class)->getDailyMetrics(today());

# Validate data integrity
php artisan financial:audit --date=2025-08-12
```

## Performance Monitoring

### Slow Query Log

```php
// AppServiceProvider.php
DB::listen(function ($query) {
    if ($query->time > 1000) { // queries longer than 1 second
        Log::warning('Slow query detected', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time
        ]);
    }
});
```

### Dashboard Performance Metrics

```php
class PerformanceMonitor
{
    public function track($operation)
    {
        $start = microtime(true);
        
        $result = $operation();
        
        $duration = microtime(true) - $start;
        
        Log::info("Performance: {$operation->name}", [
            'duration' => $duration,
            'memory' => memory_get_peak_usage(true)
        ]);
        
        return $result;
    }
}
```

## Error Logging

### Custom Error Handler

```php
// app/Exceptions/Handler.php
public function report(Throwable $exception)
{
    if ($exception instanceof FinancialException) {
        Log::critical('Financial system error', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'user' => auth()->user()?->email,
            'url' => request()->fullUrl(),
            'data' => request()->all()
        ]);
        
        // Send alert
        Mail::to('admin@example.com')->send(new FinancialErrorAlert($exception));
    }
    
    parent::report($exception);
}
```

## Recovery Procedures

### Corrupted Cache

```bash
# Clear all cache
redis-cli FLUSHALL
php artisan cache:clear

# Rebuild cache
php artisan financial:rebuild-cache
```

### Missing Transactions

```php
// Reimport missing date
$importer = new TransactionImporter();
$importer->importDate('2025-08-12');

// Validate import
$validator = new TransactionValidator();
$validator->validateDate('2025-08-12');
```

### Database Recovery

```bash
# Backup current state
mysqldump osmanager > backup_$(date +%Y%m%d).sql

# Restore from backup
mysql osmanager < backup_20250812.sql

# Verify integrity
php artisan db:verify
```

## Support Contacts

### Internal Support
- **Technical Issues**: tech@osmanager.local
- **Financial Queries**: finance@osmanager.local
- **Emergency**: +353 1 234 5678

### External Support
- **POS System**: support@unicenta.com
- **Laravel Framework**: https://laracasts.com/discuss
- **Database**: https://dev.mysql.com/doc/

## Useful Resources

- [Laravel Debugging Guide](https://laravel.com/docs/debugging)
- [MySQL Performance Tuning](https://dev.mysql.com/doc/refman/8.0/en/optimization.html)
- [POS Integration Manual](../features/pos-integration.md)
- [System Architecture](./overview.md)