# KDS Coffee Orders Not Detecting - Fix Instructions

## Problem
The KDS system stopped detecting new coffee orders because the last processed order time got stuck at an old timestamp (17:20:48 or earlier). This prevents the monitoring job from looking for newer orders.

## Solution Deployed
1. **Added max lookback window** - The monitoring job now has a 2-hour maximum lookback to prevent getting stuck
2. **Added diagnostic command** - `php artisan kds:diagnose` to troubleshoot issues
3. **Improved logging** - Better debug output to identify issues

## To Fix on Production

### Option 1: Quick Fix (Recommended)
```bash
# SSH to production server
cd /var/www/html/osmanagercl

# Clear old stuck orders (older than 1 hour)
php artisan tinker
>>> App\Models\KdsOrder::where('order_time', '<', Carbon\Carbon::now()->subHours(1))->delete();
>>> exit

# The system will automatically start detecting new orders
```

### Option 2: Full Reset
```bash
# Clear ALL KDS orders and start fresh
php artisan tinker
>>> App\Models\KdsOrder::truncate();
>>> DB::table('kds_settings')->truncate();
>>> exit
```

## Verify Fix is Working

1. **Check diagnostics:**
```bash
php artisan kds:diagnose --minutes=120
```

2. **Monitor logs:**
```bash
tail -f storage/logs/laravel.log | grep MonitorCoffeeOrdersJob
```

3. **Check the KDS page:**
- Visit `/kds` 
- Create a coffee order in uniCenta
- Should appear within 10-30 seconds

## Prevention
The code now includes a 2-hour maximum lookback window, so this issue should not recur. If the system is down for more than 2 hours, only the last 2 hours of orders will be imported when it restarts.

## Debug Commands Available
- `php artisan kds:diagnose` - Check system status
- `php artisan kds:monitor` - Manually trigger order check
- `php artisan kds:test-orders 5` - Create 5 test orders (dev only)