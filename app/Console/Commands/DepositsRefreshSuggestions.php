<?php

namespace App\Console\Commands;

use App\Models\ProductDeposit;
use App\Services\Deposits\DepositEvidenceService;
use Illuminate\Console\Command;

/**
 * Rebuild the product deposit suggestions and their evidence counts from the
 * recorded sightings. Confirmed and rejected rows keep their status and tier.
 */
class DepositsRefreshSuggestions extends Command
{
    protected $signature = 'deposits:refresh-suggestions';

    protected $description = 'Recompute bottle-deposit suggestions from supplier evidence';

    public function handle(DepositEvidenceService $service): int
    {
        $result = $service->refreshSuggestions();

        $this->table(['status', 'rows'], ProductDeposit::query()
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($r) => [$r->status, $r->n])
            ->all());

        $this->info(sprintf(
            '%d created, %d updated, %d unchanged, %d product(s) on a tier not charged to customers.',
            $result['created'], $result['updated'], $result['unchanged'], $result['tier_off']
        ));

        if ($result['unmatched'] !== []) {
            $this->warn('Udea codes with no till product (add by hand on /deposits if needed): '.implode(', ', $result['unmatched']));
        }

        return self::SUCCESS;
    }
}
