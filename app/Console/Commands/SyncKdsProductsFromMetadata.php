<?php

namespace App\Console\Commands;

use App\Models\CoffeeProductMetadata;
use App\Models\KdsProduct;
use Illuminate\Console\Command;

class SyncKdsProductsFromMetadata extends Command
{
    protected $signature = 'kds:sync-products
                            {--dry-run : Report what would change without writing anything}';

    protected $description = 'Put every product with coffee metadata on the KDS allow-list (kds_products), reactivating any that were switched off';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = ['created' => 0, 'reactivated' => 0, 'unchanged' => 0, 'not_in_pos' => 0];

        $metadata = CoffeeProductMetadata::orderBy('type')->orderBy('product_name')->get();
        $this->info(($dryRun ? '[dry run] ' : '')."Checking {$metadata->count()} metadata rows against the KDS allow-list...");

        foreach ($metadata as $row) {
            if ($dryRun) {
                $action = $this->predict($row->product_id);
            } else {
                $action = KdsProduct::ensureListed($row->product_id)['action'];
            }

            $counts[$action]++;

            if ($action !== 'unchanged') {
                $this->line(sprintf('  %-12s %s (%s)', $action, $row->product_name, $row->product_id));
            }
        }

        $this->newLine();
        $this->table(
            ['Created', 'Reactivated', 'Already listed', 'Not in POS (skipped)'],
            [[$counts['created'], $counts['reactivated'], $counts['unchanged'], $counts['not_in_pos']]]
        );

        if ($dryRun && ($counts['created'] + $counts['reactivated']) > 0) {
            $this->comment('Run again without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Same decision as KdsProduct::ensureListed(), without writing.
     */
    private function predict(string $productId): string
    {
        $existing = KdsProduct::where('product_id', $productId)->first();

        if ($existing) {
            return $existing->is_active ? 'unchanged' : 'reactivated';
        }

        $inPos = \Illuminate\Support\Facades\DB::connection('pos')
            ->table('PRODUCTS')
            ->where('ID', $productId)
            ->exists();

        return $inPos ? 'created' : 'not_in_pos';
    }
}
