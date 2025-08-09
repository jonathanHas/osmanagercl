<?php

namespace App\Jobs;

use App\Repositories\TillTransactionRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CacheTillTransactions implements ShouldQueue
{
    use Queueable;

    protected Carbon $date;
    protected bool $clearExisting;

    /**
     * Create a new job instance.
     */
    public function __construct(Carbon $date, bool $clearExisting = false)
    {
        $this->date = $date;
        $this->clearExisting = $clearExisting;
    }

    /**
     * Execute the job.
     */
    public function handle(TillTransactionRepository $repository): void
    {
        try {
            Log::info('Starting till transaction cache job', [
                'date' => $this->date->format('Y-m-d'),
                'clear_existing' => $this->clearExisting,
            ]);

            // Clear existing cache if requested
            if ($this->clearExisting) {
                $repository->clearCache($this->date);
            }

            // Fetch and cache transactions
            $transactions = $repository->getTransactionsForDate($this->date);
            
            // Generate daily summary
            $summary = $repository->getDailySummary($this->date);

            Log::info('Till transaction cache job completed', [
                'date' => $this->date->format('Y-m-d'),
                'transaction_count' => $transactions->count(),
                'summary_total' => $summary->total_sales,
            ]);
        } catch (\Exception $e) {
            Log::error('Till transaction cache job failed', [
                'date' => $this->date->format('Y-m-d'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
}