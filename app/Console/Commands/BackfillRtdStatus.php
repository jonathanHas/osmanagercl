<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class BackfillRtdStatus extends Command
{
    protected $signature = 'rtd:backfill-status {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Backfill rtd_status for all RTD-eligible invoices based on their current state';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN - no changes will be made.');
        }

        $query = Invoice::where(function ($q) {
            $q->whereHas('supplier', function ($sq) {
                $sq->where('rtd_classification', '!=', 'not_applicable')
                    ->whereNotNull('rtd_classification');
            })
                ->orWhere('supplier_name', 'like', '%udea%')
                ->orWhereHas('supplier', function ($sq) {
                    $sq->where('name', 'like', '%udea%');
                })
                ->orWhere('supplier_name', 'like', '%dynamis%')
                ->orWhereHas('supplier', function ($sq) {
                    $sq->where('name', 'like', '%dynamis%');
                })
                ->orWhere('supplier_name', 'like', '%independent%')
                ->orWhereHas('supplier', function ($sq) {
                    $sq->where('name', 'like', '%independent%');
                });
        });

        $total = $query->count();
        $this->info("Found {$total} RTD-eligible invoices to process.");

        $updated = 0;
        $statusCounts = [];

        $query->with(['supplier', 'attachments', 'uploadFiles'])
            ->chunkById(100, function ($invoices) use ($dryRun, &$updated, &$statusCounts) {
                foreach ($invoices as $invoice) {
                    $newStatus = $this->computeStatus($invoice);

                    if (! isset($statusCounts[$newStatus])) {
                        $statusCounts[$newStatus] = 0;
                    }
                    $statusCounts[$newStatus]++;

                    if ($invoice->rtd_status !== $newStatus) {
                        if (! $dryRun) {
                            $invoice->update(['rtd_status' => $newStatus]);
                        }
                        $updated++;
                    }
                }

                $this->output->write('.');
            });

        $this->newLine();
        $this->info("Done! {$updated} invoices updated out of {$total} total.");
        $this->newLine();

        $this->table(['Status', 'Count'], collect($statusCounts)->map(fn ($count, $status) => [$status, $count])->values()->toArray());

        return Command::SUCCESS;
    }

    private function computeStatus(Invoice $invoice): string
    {
        // Frozen takes priority
        if ($invoice->rtd_status === 'frozen') {
            return 'frozen';
        }

        // Has RTD breakdown data
        if (! empty($invoice->rtd_breakdown)) {
            $unresolvedCount = $invoice->rtd_breakdown['unresolved']['count'] ?? 0;

            return $unresolvedCount > 0 ? 'has_issues' : 'computed';
        }

        // Has parsed line data (can compute)
        if ($invoice->canComputeRtd()) {
            return 'needs_computation';
        }

        // Has PDF on disk (can parse)
        if ($invoice->canReparseForRtd()) {
            return 'needs_parsing';
        }

        // Has PDF record but file missing
        $hasPdfRecord = $invoice->attachments->where('mime_type', 'application/pdf')->isNotEmpty();
        if ($hasPdfRecord && ! $invoice->hasPdfOnDisk()) {
            return 'pdf_missing';
        }

        // Default
        return 'needs_parsing';
    }
}
