<?php

namespace App\Console\Commands;

use App\Jobs\MonitorCoffeeOrdersJob;
use Illuminate\Console\Command;

class KdsContinuousMonitor extends Command
{
    protected $signature = 'kds:continuous-monitor {--interval=2 : Check interval in seconds}';
    protected $description = 'Continuously monitor for new coffee orders with minimal delay';

    public function handle(): int
    {
        $interval = (int) $this->option('interval');
        $this->info("Starting continuous KDS monitoring (checking every {$interval} seconds)...");
        $this->info("Press Ctrl+C to stop");
        
        while (true) {
            try {
                // Dispatch the monitoring job synchronously for immediate processing
                MonitorCoffeeOrdersJob::dispatchSync();
                $this->line('✓ Checked at ' . now()->format('H:i:s'));
            } catch (\Exception $e) {
                $this->error('Error: ' . $e->getMessage());
            }
            
            sleep($interval);
        }
        
        return Command::SUCCESS;
    }
}