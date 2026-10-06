<?php

namespace App\Console\Commands;

use App\Services\Deposits\DepositTillInstaller;
use Illuminate\Console\Command;

/**
 * Install, check or roll back the bottle-deposit till scripts and their
 * Ticket.Buttons events on the POS the app points at. Restart every till
 * afterwards. Runbook: docs/features/barrel-deposit-tracking.md.
 */
class DepositsInstallTill extends Command
{
    protected $signature = 'deposits:install-till
                            {--check : Report whether the scripts are installed, read-only}
                            {--rollback : Restore Ticket.Buttons from the backup and remove the scripts}
                            {--force : Do not ask for confirmation on a non-dev POS}';

    protected $description = 'Install the bottle-deposit scripts on the uniCenta till';

    public function handle(DepositTillInstaller $installer): int
    {
        try {
            $target = $installer->target();
            $this->line(sprintf(
                'POS: %s:%s/%s (%s)',
                $target['host'], $target['port'], $target['database'], $target['is_dev'] ? 'dev' : 'NOT dev'
            ));

            if ($this->option('check')) {
                return $this->printStatus($installer) ? self::SUCCESS : self::FAILURE;
            }

            $rollback = (bool) $this->option('rollback');
            $question = $rollback
                ? 'Roll back the deposit till scripts on this POS?'
                : 'Install the deposit till scripts on this POS?';

            if (! $target['is_dev'] && ! $this->option('force') && ! $this->confirm($question)) {
                $this->warn('Aborted, nothing was written.');

                return self::FAILURE;
            }

            foreach ($rollback ? $installer->rollback() : $installer->install() as $action) {
                $this->line('  '.$action);
            }

            $installed = $this->printStatus($installer);
            $this->info('Restart uniCenta on every till for this to take effect.');

            if ($rollback) {
                return self::SUCCESS;
            }

            return $installed ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function printStatus(DepositTillInstaller $installer): bool
    {
        $status = $installer->status();
        $yes = fn (bool $b) => $b ? 'yes' : 'no';

        $rows = [];
        foreach ($status['scripts'] as $name => $script) {
            $rows[] = [$name, $yes($script['present']), $yes($script['matches_file'])];
        }
        $this->table(['script', 'present', 'matches file'], $rows);

        $this->table(['check', 'value'], [
            ['Ticket.Buttons wired', $yes($status['wired'])],
            ['anchor (ticket.close line) count', $status['anchor_count']],
            ['backup row present', $yes($status['backup_present'])],
            ['installed', $yes($status['installed'])],
        ]);

        $status['installed']
            ? $this->info('Installed.')
            : $this->warn('Not installed.');

        return $status['installed'];
    }
}
