<?php

namespace App\Console\Commands;

use App\Models\Voucher;
use App\Services\VoucherPosProductService;
use Illuminate\Console\Command;

/**
 * Backfill (or refresh) the hidden uniCenta product behind each gift voucher.
 *
 * Every status is included by default: a printed-but-unsold label must still
 * scan at the till, or uniCenta says "product not found".
 */
class SyncVoucherPosProducts extends Command
{
    protected $signature = 'vouchers:sync-pos-products
                            {--dry-run : List what would be synced without writing}
                            {--all : Also re-sync vouchers that already have a pos_product_id}';

    protected $description = 'Create or rename the hidden POS product for each gift voucher';

    public function handle(VoucherPosProductService $service): int
    {
        $query = Voucher::query()->orderBy('id');
        if (! $this->option('all')) {
            $query->whereNull('pos_product_id');
        }

        $vouchers = $query->get();
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $totals = ['created' => 0, 'linked' => 0, 'renamed' => 0, 'unchanged' => 0, 'failed' => 0];

        foreach ($vouchers as $voucher) {
            if ($dryRun) {
                $action = $voucher->pos_product_id ? 'would re-sync' : 'would create';
            } else {
                $service->sync($voucher);
                $action = $service->lastAction() ?? 'failed';
                $totals[$action] = ($totals[$action] ?? 0) + 1;
            }

            $rows[] = [$voucher->code, $voucher->status, number_format((float) $voucher->current_balance, 2), $action];
        }

        $this->table(['code', 'status', 'balance', 'action'], $rows);

        if ($dryRun) {
            $this->info(count($rows).' voucher(s) would be synced (dry run, nothing written).');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d created, %d linked, %d renamed, %d unchanged, %d failed.',
            $totals['created'], $totals['linked'], $totals['renamed'], $totals['unchanged'], $totals['failed']
        ));

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
