<?php

namespace App\Console\Commands;

use App\Models\CashReconciliation;
use App\Services\Cash\TillCountService;
use Illuminate\Console\Command;

/**
 * Repair stored variances (till count rewrite, 2026-11-07).
 *
 * The old page froze each row's variance at creation against whichever "previous"
 * row happened to exist at the time, and the importer used a different formula
 * again. This re-resolves the previous float from the previous counted session of
 * the same till and recomputes every variance with the one formula in
 * TillCountService::variance().
 */
class RecalculateCashVariances extends Command
{
    protected $signature = 'cash:recalculate-variances
                            {--from= : Only rows dated on or after this date (Y-m-d)}
                            {--till= : Only this till id}
                            {--dry-run : Report what would change without saving}';

    protected $description = 'Re-resolve previous floats and recompute cash reconciliation variances';

    public function handle(TillCountService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = CashReconciliation::counted()->with('payments')->orderBy('till_id')->orderBy('date');
        if ($this->option('from')) {
            $query->whereDate('date', '>=', $this->option('from'));
        }
        if ($this->option('till')) {
            $query->where('till_id', (int) $this->option('till'));
        }

        $rows = $query->get();
        $this->info(($dryRun ? 'DRY RUN: ' : '')."checking {$rows->count()} counted reconciliations");

        $changed = [];
        $absBefore = [];
        $absAfter = [];

        foreach ($rows as $row) {
            $result = $service->recalculate($row, persist: ! $dryRun);
            $absBefore[] = abs($result['before']);
            $absAfter[] = abs($result['after']);

            if (abs($result['before'] - $result['after']) >= 0.005) {
                $changed[] = [
                    $row->date->toDateString(),
                    $row->till_name,
                    number_format($result['previous_before'] ?? 0, 2),
                    number_format($result['previous_after'], 2),
                    number_format($result['before'], 2),
                    number_format($result['after'], 2),
                ];
            }
        }

        if ($changed !== []) {
            $this->table(['Date', 'Till', 'Prev float (was)', 'Prev float (now)', 'Variance (was)', 'Variance (now)'], $changed);
        }

        $this->line(sprintf(
            '%d of %d rows change. Median |variance| %.2f -> %.2f; rows over 20: %d -> %d.',
            count($changed),
            $rows->count(),
            self::median($absBefore),
            self::median($absAfter),
            count(array_filter($absBefore, fn ($v) => $v > 20)),
            count(array_filter($absAfter, fn ($v) => $v > 20)),
        ));

        if ($dryRun) {
            $this->warn('Nothing saved. Run without --dry-run to apply.');
        } else {
            $this->info('Saved.');
        }

        return self::SUCCESS;
    }

    /** @param  array<int, float>  $values */
    private static function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);

        return $values[intdiv(count($values), 2)];
    }
}
