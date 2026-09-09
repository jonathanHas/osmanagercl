<?php

namespace App\Console\Commands;

use App\Models\UdeaProductCard;
use App\Services\UdeaPalletVolumeService;
use Illuminate\Console\Command;

/**
 * Reads pallet volume figures for every product currently in the Udea basket and
 * stores them against udea_product_cards.
 *
 * Udea only renders this data for products in the basket, so the workflow is:
 * empty the basket, bulk-upload the product range through Udea's own Excel importer,
 * then run this. The figures are per-product constants, so it only needs repeating
 * when the range changes.
 */
class SyncUdeaPalletVolumes extends Command
{
    protected $signature = 'udea:sync-pallet-volumes
        {--dry-run : Report what would be written without touching the database}';

    protected $description = 'Read pallet volume data from the Udea basket into udea_product_cards';

    public function handle(UdeaPalletVolumeService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run - nothing will be written.');
        }

        $this->info('Fetching the Udea basket... (a full range can be a very large page)');

        try {
            $result = $service->sync($dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['found'] === 0) {
            $this->error('No products with pallet data found in the basket. Is it empty?');

            return self::FAILURE;
        }

        if ($result['capacity_warning']) {
            $this->warn('Pallet capacity drift: '.$result['capacity_warning']);
            $this->warn('Update config/suppliers.php (external_links.udea.pallet).');
        }

        $this->newLine();
        $this->info("Products found in basket : {$result['found']}");
        $this->info(($dryRun ? 'Would create' : 'Created').str_repeat(' ', $dryRun ? 13 : 15).": {$result['created']}");
        $this->info(($dryRun ? 'Would update' : 'Updated').str_repeat(' ', $dryRun ? 13 : 15).": {$result['updated']}");

        if ($result['skipped'] > 0) {
            $this->warn("Skipped (no product code): {$result['skipped']}");
        }

        foreach ($result['errors'] as $error) {
            $this->error('  '.$error);
        }

        $capacities = $result['capacities'];
        $euro = $capacities['euro'] ?: 250;
        $block = $capacities['block'] ?: 360;

        $this->newLine();
        $this->line('Basket pallet volume: '.$result['total_volume']);
        $this->line(sprintf('  = %.2f%% of a Europallet (%s)', $result['total_volume'] / $euro * 100, $euro));
        $this->line(sprintf('  = %.2f%% of a blockpallet (%s)', $result['total_volume'] / $block * 100, $block));

        if (! $dryRun) {
            $this->newLine();
            $this->info('Total products with pallet data: '.UdeaProductCard::whereNotNull('pallet_scraped_at')->count());
        }

        return self::SUCCESS;
    }
}
