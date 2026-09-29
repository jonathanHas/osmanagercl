<?php

namespace App\Console\Commands;

use App\Services\VoucherSyncHeartbeat;
use App\Services\VoucherTillSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Deduct gift vouchers redeemed at the uniCenta till (Voucher tender + scanned
 * voucher label). Scheduled every minute; the voucher lookup screens also run
 * the same sync, throttled.
 */
class SyncVoucherTillRedemptions extends Command
{
    protected $signature = 'vouchers:sync-till
                            {--since= : ISO datetime overriding the watermark}
                            {--scheduled : Set by the scheduler, so the activity screen can tell its runs from a manual one}';

    protected $description = 'Apply gift voucher redemptions taken at the uniCenta till';

    public function handle(VoucherTillSyncService $service): int
    {
        try {
            $since = $this->option('since') ? Carbon::parse($this->option('since')) : null;
            $source = $this->option('scheduled') ? VoucherSyncHeartbeat::SOURCE_SCHEDULE : VoucherSyncHeartbeat::SOURCE_COMMAND;
            $counts = $service->sync($since, $source);
        } catch (\Throwable $e) {
            $this->error('Voucher till sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(array_keys($counts), [array_values($counts)]);

        return self::SUCCESS;
    }
}
