<?php

namespace App\Console\Commands;

use App\Services\CustomerRequestService;
use Illuminate\Console\Command;

/**
 * One-off after deploying requests cycle 3: stamp the open request lines whose
 * product was already scanned in Receiving before the scan endpoints started
 * recording arrivals. Safe to re-run; it only touches lines with no arrival.
 */
class BackfillCustomerRequestArrivals extends Command
{
    protected $signature = 'customer-requests:backfill-arrivals';

    protected $description = 'Stamp open customer request lines with arrivals found in past Receiving scans';

    public function handle(CustomerRequestService $service): int
    {
        $stamped = $service->backfillArrivals();

        $this->info("{$stamped} request line(s) stamped as arrived.");

        return self::SUCCESS;
    }
}
