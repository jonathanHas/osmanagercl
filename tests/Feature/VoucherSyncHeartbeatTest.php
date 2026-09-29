<?php

namespace Tests\Feature;

use App\Services\VoucherSyncHeartbeat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * When the till check last ran, and how it went (vouchers cycle 2).
 */
class VoucherSyncHeartbeatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-29 12:00:00');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function heartbeat(): VoucherSyncHeartbeat
    {
        return app(VoucherSyncHeartbeat::class);
    }

    public function test_a_recorded_run_reads_back(): void
    {
        $this->heartbeat()->record(VoucherSyncHeartbeat::SOURCE_COMMAND, true, ['tickets' => 2, 'errors' => 0]);

        $read = $this->heartbeat()->read();

        $this->assertSame(now()->toIso8601String(), $read['last']['at']);
        $this->assertTrue($read['last']['ok']);
        $this->assertSame('command', $read['last']['source']);
        $this->assertSame(['tickets' => 2, 'errors' => 0], $read['last']['counts']);
        $this->assertNull($read['last']['error']);
        $this->assertSame(now()->toIso8601String(), $read['last_ok_at']);
        $this->assertNull($read['last_scheduled_at']);
    }

    public function test_a_failed_run_does_not_move_last_ok(): void
    {
        $this->heartbeat()->record(VoucherSyncHeartbeat::SOURCE_COMMAND, true, []);
        $okAt = now()->toIso8601String();

        Carbon::setTestNow(now()->addMinute());
        $this->heartbeat()->record(VoucherSyncHeartbeat::SOURCE_COMMAND, false, null, 'Connection refused');

        $read = $this->heartbeat()->read();
        $this->assertFalse($read['last']['ok']);
        $this->assertSame('Connection refused', $read['last']['error']);
        $this->assertSame($okAt, $read['last_ok_at']);
    }

    public function test_only_scheduled_runs_move_last_scheduled_even_when_they_fail(): void
    {
        $this->heartbeat()->record(VoucherSyncHeartbeat::SOURCE_LOOKUP, true, []);
        $this->heartbeat()->record(VoucherSyncHeartbeat::SOURCE_ACTIVITY, true, []);
        $this->heartbeat()->record(VoucherSyncHeartbeat::SOURCE_COMMAND, true, []);
        $this->assertNull($this->heartbeat()->read()['last_scheduled_at']);

        $this->heartbeat()->record(VoucherSyncHeartbeat::SOURCE_SCHEDULE, false, null, 'Till down');

        $this->assertSame(now()->toIso8601String(), $this->heartbeat()->read()['last_scheduled_at']);
    }

    public function test_the_scheduler_is_stale_until_it_runs_and_again_after_the_threshold(): void
    {
        $this->assertTrue($this->heartbeat()->read()['scheduler_stale']);

        $this->heartbeat()->record(VoucherSyncHeartbeat::SOURCE_SCHEDULE, true, []);
        $this->assertFalse($this->heartbeat()->read()['scheduler_stale']);

        Carbon::setTestNow(now()->addSeconds(config('vouchers.activity.scheduler_stale_seconds') - 1));
        $this->assertFalse($this->heartbeat()->read()['scheduler_stale']);

        Carbon::setTestNow(now()->addSeconds(2));
        $this->assertTrue($this->heartbeat()->read()['scheduler_stale']);
    }

    public function test_the_error_is_cut_to_300_characters(): void
    {
        $this->heartbeat()->record(VoucherSyncHeartbeat::SOURCE_COMMAND, false, null, str_repeat('x', 1000));

        $this->assertLessThanOrEqual(303, mb_strlen($this->heartbeat()->read()['last']['error'])); // 300 + "..."
    }

    public function test_a_broken_cache_never_throws(): void
    {
        Log::spy();
        Cache::shouldReceive('forever')->andThrow(new \RuntimeException('cache down'));
        Cache::shouldReceive('get')->andThrow(new \RuntimeException('cache down'));

        $this->heartbeat()->record(VoucherSyncHeartbeat::SOURCE_SCHEDULE, true, []);

        $this->assertSame(
            ['last' => null, 'last_ok_at' => null, 'last_scheduled_at' => null, 'scheduler_stale' => true],
            $this->heartbeat()->read()
        );
        Log::shouldHaveReceived('warning')->twice();
    }
}
