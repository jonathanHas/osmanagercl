<?php

namespace App\Console\Commands;

use App\Jobs\MonitorCoffeeOrdersJob;
use Illuminate\Console\Command;

class MonitorCoffeeOrders extends Command
{
    protected $signature = 'kds:monitor-coffee';

    protected $description = 'Monitor and import new coffee orders from POS to KDS';

    public function handle(): int
    {
        $this->info('Checking for new coffee orders...');

        MonitorCoffeeOrdersJob::dispatch();

        $this->info('Coffee order monitoring job dispatched.');

        return Command::SUCCESS;
    }
}
