<?php

namespace App\Console\Commands;

use App\Services\Deposits\DepositEvidenceService;
use Illuminate\Console\Command;

/**
 * Split the Udea deposit (barrel) code off delivery lines imported before the
 * parser captured it, and record the deposit sightings.
 */
class DepositsBackfillDeliveryItems extends Command
{
    protected $signature = 'deposits:backfill-delivery-items
                            {--dry-run : Count what would change without writing}';

    protected $description = 'Capture the bottle-deposit code on existing Udea delivery lines';

    public function handle(DepositEvidenceService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $service->backfillDeliveryItems($dryRun);

        $this->table(['deposit code', 'lines'], collect($result['codes'])->map(fn ($n, $code) => [$code, $n])->values()->all());

        $this->info(sprintf(
            '%d line(s) over %d deliver%s%s.',
            $result['rows'],
            $result['deliveries'],
            $result['deliveries'] === 1 ? 'y' : 'ies',
            $dryRun ? ' would be updated (dry run, nothing written)' : sprintf(' updated, %d sighting(s) written', $result['sightings'])
        ));

        return self::SUCCESS;
    }
}
