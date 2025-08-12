<?php

namespace App\Console\Commands;

use App\Models\KdsOrder;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;

class ResetKdsCutoff extends Command
{
    protected $signature = 'kds:reset-cutoff {--hours=2 : How many hours back to reset the cutoff}';

    protected $description = 'Reset KDS cutoff time to allow re-importing recent orders';

    public function handle(): int
    {
        $hours = $this->option('hours');

        // Get current last order time
        $lastOrderTime = KdsOrder::max('order_time');
        if ($lastOrderTime) {
            $this->info("Current last KDS order time: {$lastOrderTime}");
        } else {
            $this->info('No KDS orders found');
        }

        // Create a dummy order with a recent time to reset the cutoff
        $resetTime = Carbon::now()->subHours($hours);

        $this->info("Resetting cutoff to: {$resetTime->toDateTimeString()}");

        if ($this->confirm('This will create a temporary KDS order to reset the cutoff time. Continue?')) {
            // Create a temporary order that will be immediately deleted
            $tempOrder = KdsOrder::create([
                'ticket_id' => 'RESET_'.time(),
                'ticket_number' => 999999,
                'person' => 'SYSTEM',
                'status' => 'completed',
                'order_time' => $resetTime,
                'completed_at' => $resetTime->copy()->addSecond(),
            ]);

            $this->info("Created temporary order with ID: {$tempOrder->id}");

            // Now delete it
            $tempOrder->delete();
            $this->info('Temporary order deleted');

            // Clear the last_clear_time if it exists
            DB::table('kds_settings')->where('key', 'last_clear_time')->delete();
            $this->info('Cleared last_clear_time setting');

            $this->info("✓ Cutoff has been reset. The next monitoring job will check for orders from {$hours} hours ago.");
            $this->info("Run 'php artisan kds:monitor' to immediately check for new orders.");

            return Command::SUCCESS;
        }

        $this->info('Operation cancelled');

        return Command::SUCCESS;
    }
}
