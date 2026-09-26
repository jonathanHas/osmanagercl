<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Restored from app/Console/Kernel.php (cycle 18): that kernel is not bound by
// bootstrap/app.php, so its schedule() was never called and these four jobs had
// silently stopped running. Commands, options, times and overlap settings are
// exactly as they were declared there.
//
// `kds:monitor` was deliberately NOT moved. The KDS page detects orders itself
// (SSE plus a 2 s poll), MonitorCoffeeOrdersJob is documented as a legacy queue
// job kept for backward compatibility, and the last time it ran unattended it
// left ~3.9M stale queue rows (known-issues).

// Import last 7 days every Sunday to catch any missed data
Schedule::command('sales:import-daily --last-week')
    ->weekly()
    ->sundays()
    ->at('05:00')
    ->onOneServer()
    ->withoutOverlapping(60);

// Import yesterday's sales data every morning at 6 AM
Schedule::command('sales:import-daily --yesterday')
    ->dailyAt('06:00')
    ->onOneServer()
    ->withoutOverlapping(30);

// Import sales accounting data (payment type + VAT rate breakdown) for reports
Schedule::command('sales-accounting:import --days=7')
    ->dailyAt('06:10')
    ->onOneServer()
    ->withoutOverlapping(30);

// Populate POS daily summaries for financial dashboard (runs after sales import)
Schedule::command('pos:populate-daily-summaries --last-days=7')
    ->dailyAt('06:15')
    ->onOneServer()
    ->withoutOverlapping(30);

// Import today's sales (same-day, partial) so opted-in suppliers can be emailed
// their sales the same evening, then send the emails 15 minutes later.
Schedule::command('sales:import-daily --today')
    ->dailyAt('20:00')
    ->onOneServer()
    ->withoutOverlapping(30);

Schedule::command('suppliers:send-daily-sales')
    ->dailyAt('20:15')
    ->onOneServer()
    ->withoutOverlapping(30);

// Tidy cached F&V thumbnails: ProductThumbnailService clears a product's older
// files whenever it writes a new one, so this only matters for a product whose
// photo never changes again. Needs to run as the web server user to be able to
// delete from the cache folder; it says so plainly when it cannot.
//
// This is in routes/console.php, not app/Console/Kernel.php, because bootstrap/app.php
// registers `commands: routes/console.php` and binds no console kernel — the
// schedule() method in Kernel.php is never called. See the cycle 17g report.
Schedule::command('fruit-veg:prune-thumbnails')
    ->weekly()
    ->sundays()
    ->at('05:30')
    ->onOneServer()
    ->withoutOverlapping(60);

// Month-end customer statements: opted-in customers carrying a balance.
Schedule::command('customers:send-statements')
    ->monthlyOn(1, '07:00')
    ->onOneServer()
    ->withoutOverlapping(30);
