<?php

namespace App\Console\Commands;

use App\Models\KitchenIngredientProfile;
use Illuminate\Console\Command;

class RecalculateKitchenProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kitchen:recalculate-profiles {--dry-run : Show what would change without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh cost_per_base_unit on kitchen ingredient profiles from current product cost prices';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No data will be modified');
        }

        $profiles = KitchenIngredientProfile::whereNotNull('pos_product_id')
            ->with('product')
            ->get();

        $this->info("📊 Checking {$profiles->count()} product-linked profiles...");

        $changed = [];

        foreach ($profiles as $profile) {
            $old = (float) $profile->cost_per_base_unit;
            $new = $profile->calculateCostPerBaseUnit();

            // decimal:6 cast - anything below that rounds away to no visible change
            if (round($old, 6) === round($new, 6)) {
                continue;
            }

            if (! $isDryRun) {
                $profile->recalculateCost();
            }

            $changed[] = [
                $profile->id,
                mb_strimwidth($profile->name, 0, 40, '…'),
                number_format($old, 6),
                number_format($new, 6),
                sprintf('%+.6f', $new - $old),
            ];
        }

        if (empty($changed)) {
            $this->info('✅ All profiles are already up to date.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Old', 'New', 'Change'], $changed);

        $verb = $isDryRun ? 'would be updated' : 'updated';
        $this->info('✅ '.count($changed)." profile(s) {$verb}.");

        return self::SUCCESS;
    }
}
