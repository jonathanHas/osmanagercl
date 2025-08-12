<?php

namespace App\Console\Commands;

use App\Jobs\CacheTillTransactions;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CacheDailyTillTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'till:cache-daily {--date= : The date to cache (Y-m-d format)} {--clear : Clear existing cache before caching}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cache till transactions for today or a specific date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateStr = $this->option('date') ?? now()->format('Y-m-d');
        $clearExisting = $this->option('clear');

        try {
            $date = Carbon::parse($dateStr);
        } catch (\Exception $e) {
            $this->error('Invalid date format. Please use Y-m-d format.');

            return 1;
        }

        $this->info("Dispatching cache job for {$date->format('Y-m-d')}...");

        CacheTillTransactions::dispatch($date, $clearExisting);

        $this->info('Cache job dispatched successfully!');

        return 0;
    }
}
