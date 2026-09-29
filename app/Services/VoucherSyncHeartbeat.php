<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * When the till check (VoucherTillSyncService::sync) last ran, and how it went.
 *
 * The scheduled command's output is discarded, so without this a stopped
 * scheduler or an unreachable till database looks exactly like a quiet day.
 *
 * Three separate cache keys, one per fact, so recording never needs a
 * read-modify-write. `last-scheduled` is kept apart from `last` on purpose:
 * the activity page runs the check itself while it is open, so `last` is
 * always fresh there and only `last-scheduled` can show a dead scheduler.
 *
 * Neither method ever throws: a heartbeat failure must not fail a sync, a
 * lookup or the activity feed.
 */
class VoucherSyncHeartbeat
{
    const SOURCE_SCHEDULE = 'schedule';

    const SOURCE_COMMAND = 'command';

    const SOURCE_LOOKUP = 'lookup';

    const SOURCE_ACTIVITY = 'activity';

    const KEY_LAST = 'vouchers:till-sync:last';

    const KEY_LAST_OK = 'vouchers:till-sync:last-ok';

    const KEY_LAST_SCHEDULED = 'vouchers:till-sync:last-scheduled';

    /**
     * @param  array<string, int>|null  $counts
     */
    public function record(string $source, bool $ok, ?array $counts = null, ?string $error = null): void
    {
        try {
            $at = now()->toIso8601String();

            Cache::forever(self::KEY_LAST, [
                'at' => $at,
                'ok' => $ok,
                'source' => $source,
                'counts' => $counts,
                'error' => $error !== null ? Str::limit($error, 300) : null,
            ]);

            if ($ok) {
                Cache::forever(self::KEY_LAST_OK, $at);
            }

            // Written whether or not the run succeeded: it proves the scheduler is alive.
            if ($source === self::SOURCE_SCHEDULE) {
                Cache::forever(self::KEY_LAST_SCHEDULED, $at);
            }
        } catch (\Throwable $e) {
            Log::warning('Voucher till sync heartbeat could not be recorded', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{last: array<string, mixed>|null, last_ok_at: ?string, last_scheduled_at: ?string, scheduler_stale: bool}
     */
    public function read(): array
    {
        try {
            $lastScheduled = Cache::get(self::KEY_LAST_SCHEDULED);
            $stale = $lastScheduled === null
                || Carbon::parse($lastScheduled)->lt(now()->subSeconds((int) config('vouchers.activity.scheduler_stale_seconds')));

            return [
                'last' => Cache::get(self::KEY_LAST),
                'last_ok_at' => Cache::get(self::KEY_LAST_OK),
                'last_scheduled_at' => $lastScheduled,
                'scheduler_stale' => $stale,
            ];
        } catch (\Throwable $e) {
            Log::warning('Voucher till sync heartbeat could not be read', ['error' => $e->getMessage()]);

            return ['last' => null, 'last_ok_at' => null, 'last_scheduled_at' => null, 'scheduler_stale' => true];
        }
    }
}
