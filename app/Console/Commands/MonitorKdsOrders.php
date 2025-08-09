<?php

namespace App\Console\Commands;

use App\Jobs\MonitorCoffeeOrdersJob;
use Illuminate\Console\Command;

class MonitorKdsOrders extends Command
{
    protected $signature = 'kds:monitor';
    protected $description = 'Dispatch job to monitor for new coffee orders';

    public function handle(): int
    {
        MonitorCoffeeOrdersJob::dispatch();
        
        $this->info('Coffee order monitoring job dispatched');
        
        return Command::SUCCESS;
    }
}