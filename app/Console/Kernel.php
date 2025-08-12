<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Import yesterday's sales data every morning at 6 AM
        $schedule->command('sales:import-daily --yesterday')
            ->dailyAt('06:00')
            ->onOneServer()
            ->withoutOverlapping(30); // 30 minute overlap protection

        // Import last 7 days every Sunday to catch any missed data
        $schedule->command('sales:import-daily --last-week')
            ->weekly()
            ->sundays()
            ->at('05:00')
            ->onOneServer()
            ->withoutOverlapping(60);

        // Monitor for new coffee orders every 10 seconds
        // This ensures orders are detected even when no one has the KDS page open
        $schedule->command('kds:monitor')
            ->everyTenSeconds()
            ->onOneServer()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
