<?php

namespace App\Jobs;

use App\Services\CustomerStatementService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Bulk statement run, triggered from the debtors page. Wraps the same service
 * method the artisan command uses so the two can't drift.
 */
class SendCustomerStatementsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?string $asOf = null) {}

    public function handle(CustomerStatementService $statements): void
    {
        $asOf = $this->asOf ? Carbon::parse($this->asOf) : Carbon::today();

        $result = $statements->sendStatements($asOf);

        Log::info('Customer statement run finished', [
            'as_of' => $asOf->toDateString(),
            'sent' => $result['sent'],
            'skipped' => $result['skipped'],
            'failed' => $result['failed'],
        ]);
    }
}
