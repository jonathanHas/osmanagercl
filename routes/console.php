<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
