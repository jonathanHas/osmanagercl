<?php

namespace Tests\Unit;

use App\Services\ZebraPrintService;
use Tests\TestCase;

class ZebraPrintServiceTest extends TestCase
{
    private function service(?string &$captured, string $response = ''): ZebraPrintService
    {
        $service = new ZebraPrintService('printer.test', '631', 'TEST-PRINTER', 9);

        return $service->usingRunner(function (string $command) use (&$captured, $response) {
            $captured = $command;

            return $response;
        });
    }

    public function test_send_raw_builds_a_timeout_guarded_escaped_command(): void
    {
        $captured = null;
        $this->service($captured, 'request id is TEST-PRINTER-3 (1 file(s))')
            ->sendRaw('^XA^FDx^FS^XZ');

        $this->assertStringStartsWith('timeout 9 lp ', $captured);
        $this->assertStringContainsString("-h 'printer.test:631/version=1.1'", $captured);
        $this->assertStringContainsString("-d 'TEST-PRINTER'", $captured);
        $this->assertStringContainsString('-o raw ', $captured);
    }

    /** The host must come from config, not the IP that used to be hardcoded. */
    public function test_uses_the_configured_host_not_a_hardcoded_one(): void
    {
        $captured = null;
        $this->service($captured, 'request id is X-1')->sendRaw('^XA^XZ');

        $this->assertStringNotContainsString('10.42.1.71', $captured);
    }

    public function test_parses_the_cups_job_id(): void
    {
        $captured = null;
        $result = $this->service($captured, 'request id is ZTC-GX430t-42 (1 file(s))')
            ->sendRaw('^XA^XZ');

        $this->assertTrue($result->success);
        $this->assertSame('ZTC-GX430t-42', $result->jobId);
        $this->assertFalse($result->timedOut);
        $this->assertFalse($result->definitivelyFailed());
    }

    /** Empty output means `timeout` killed lp — the job may still have been accepted. */
    public function test_empty_output_is_treated_as_a_timeout_not_a_failure(): void
    {
        $captured = null;
        $result = $this->service($captured, '')->sendRaw('^XA^XZ');

        $this->assertFalse($result->success);
        $this->assertTrue($result->timedOut);
        $this->assertFalse($result->definitivelyFailed());
    }

    public function test_an_error_message_is_a_definitive_failure(): void
    {
        $captured = null;
        $result = $this->service($captured, 'lp: Destination "TEST-PRINTER" does not exist.')
            ->sendRaw('^XA^XZ');

        $this->assertFalse($result->success);
        $this->assertFalse($result->timedOut);
        $this->assertTrue($result->definitivelyFailed());
    }

    public function test_queue_parses_lpstat_rows(): void
    {
        $captured = null;
        $output = "ZTC-GX430t-41  jon  1024  Tue 09 Sep 2026 10:15:00 IST\n"
            .'ZTC-GX430t-42  jon  2048  Tue 09 Sep 2026 10:16:00 IST';

        $queue = $this->service($captured, $output)->queue();

        $this->assertStringContainsString("lpstat -h 'printer.test:631/version=1.1' -o", $captured);
        $this->assertTrue($queue['success']);
        $this->assertCount(2, $queue['jobs']);
        $this->assertSame('ZTC-GX430t-41', $queue['jobs'][0]['job_id']);
        $this->assertSame('jon', $queue['jobs'][0]['user']);
        $this->assertSame('Tue 09 Sep 2026 10:15:00 IST', $queue['jobs'][0]['submitted_at']);
    }

    public function test_queue_reports_an_lpstat_error(): void
    {
        $captured = null;
        $queue = $this->service($captured, 'lpstat: Error - unable to connect')->queue();

        $this->assertFalse($queue['success']);
        $this->assertSame([], $queue['jobs']);
    }

    public function test_cancel_all_targets_the_printer(): void
    {
        $captured = null;
        $result = $this->service($captured, '')->cancelAll();

        $this->assertStringStartsWith('timeout 9 cancel ', $captured);
        $this->assertStringContainsString("-a 'TEST-PRINTER'", $captured);
        $this->assertTrue($result['success']);
    }

    public function test_cancel_targets_one_job(): void
    {
        $captured = null;
        $this->service($captured, '')->cancel('ZTC-GX430t-42');

        $this->assertStringContainsString("'ZTC-GX430t-42'", $captured);
    }
}
