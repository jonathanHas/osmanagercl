<?php

namespace App\Console\Commands;

use App\Services\Deposits\DepositPosService;
use Illuminate\Console\Command;

/**
 * Bring the deposit products and the deposit.* product properties on the till
 * in line with product_deposits, or (--check) report the drift.
 */
class DepositsSyncPos extends Command
{
    protected $signature = 'deposits:sync-pos
                            {--dry-run : List what would change without writing}
                            {--check : Report drift between the app and the till, read-only}';

    protected $description = 'Write bottle-deposit properties to till products';

    public function handle(DepositPosService $service): int
    {
        if ($this->option('check')) {
            return $this->check($service);
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $service->syncAll($dryRun);

        $this->table(['barcode', 'product', 'outcome'], array_map(
            fn ($c) => [$c['code'], $c['name'], ($dryRun ? 'would be ' : '').$c['outcome']],
            $result['changes']
        ));

        $t = $result['totals'];
        $this->info(sprintf(
            '%s%d written, %d cleared, %d stray cleared, %d unchanged, %d refused, %d missing from the till.',
            $dryRun ? 'Dry run, nothing written: ' : '',
            $t['written'], $t['cleared'], $t['stray_cleared'], $t['unchanged'], $t['refused'], $t['missing']
        ));

        return ($t['refused'] + $t['missing']) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function check(DepositPosService $service): int
    {
        $report = $service->check();

        $this->line('Confirmed products:');
        $this->table(['barcode', 'product', 'expected id', 'till id', 'expected price', 'till price', 'ok'], array_map(
            fn ($r) => [$r['code'], $r['name'], $r['expected_id'] ?? '—', $r['actual_id'] ?? '—', $r['expected_price'] ?? '—', $r['actual_price'] ?? '—', $r['ok'] ? 'yes' : 'NO'],
            $report['rows']
        ));

        $this->line('Tiers charged to customers:');
        $this->table(['code', 'price', 'deposit product', 'refund product', 'on till'], array_map(
            fn ($t) => [$t['code'], $t['price'], $t['pos_product_id'] ?? '—', $t['pos_refund_product_id'] ?? '—', $t['on_till'] ? 'yes' : 'NO'],
            $report['tiers']
        ));

        if ($report['strays'] !== []) {
            $this->warn('Till products with deposit properties and no confirmed row:');
            $this->table(['barcode', 'product', 'deposit.id'], array_map(
                fn ($s) => [$s['code'], $s['name'], $s['deposit_id']],
                $report['strays']
            ));
        }

        $tiersMissing = count(array_filter($report['tiers'], fn ($t) => ! $t['on_till']));
        $this->info(sprintf(
            '%d in sync, %d drifted, %d stray(s), %d tier(s) without till products.',
            $report['in_sync'], $report['drifted'], count($report['strays']), $tiersMissing
        ));

        return ($report['drifted'] + count($report['strays']) + $tiersMissing) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
