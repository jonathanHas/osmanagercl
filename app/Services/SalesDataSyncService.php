<?php

namespace App\Services;

use App\Models\SalesDailySummary;
use App\Models\SalesImportLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SalesDataSyncService
{
    public function __construct(protected SalesImportService $salesImportService) {}

    /**
     * Ensure the sales_daily_summary table has up-to-date data before order generation.
     *
     * @return SalesImportLog|null Returns the import log when an import runs, null when no work was required.
     */
    public function ensureDailySummariesAreFresh(int $salesHistoryWeeks = 8): ?SalesImportLog
    {
        $salesHistoryWeeks = max(1, min(26, $salesHistoryWeeks));
        $targetEndDate = Carbon::yesterday();

        $latestSummaryDate = SalesDailySummary::max('sale_date');

        if ($latestSummaryDate !== null) {
            $latestSummaryDate = Carbon::parse($latestSummaryDate);

            // Already up to date
            if ($latestSummaryDate->greaterThanOrEqualTo($targetEndDate)) {
                return null;
            }

            $startDate = $latestSummaryDate->copy()->addDay();
        } else {
            // Fall back to enough history for the upcoming order window
            $startDate = Carbon::now()->copy()->subWeeks($salesHistoryWeeks)->startOfWeek();
        }

        if ($startDate->greaterThan($targetEndDate)) {
            return null;
        }

        $log = $this->salesImportService->importDailySales($startDate->copy(), $targetEndDate->copy());

        Log::info('Automatic sales import ran before order generation', [
            'start_date' => $startDate->toDateString(),
            'end_date' => $targetEndDate->toDateString(),
            'records_processed' => $log->records_processed,
        ]);

        return $log;
    }
}
