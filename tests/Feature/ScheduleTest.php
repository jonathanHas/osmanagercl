<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * What the scheduler actually runs.
 *
 * This exists because four data imports stopped running and nobody noticed. They
 * were declared in `app/Console/Kernel.php`, which this application's
 * `bootstrap/app.php` never binds, so `schedule()` was never called — the code
 * looked right in the only place anyone would think to look. The kernel is gone
 * and the entries live in `routes/console.php`; this test is what stops the same
 * thing happening quietly again.
 */
class ScheduleTest extends TestCase
{
    /**
     * Command and cron expression for everything currently scheduled.
     *
     * `$event->command` is the full shell line — PHP binary, artisan path, then the
     * command — so the head is stripped and only the artisan command compared.
     *
     * @return array<string, string> command => cron expression
     */
    private function scheduled(): array
    {
        $out = [];

        foreach (app(Schedule::class)->events() as $event) {
            $command = preg_replace("/^.*?'?artisan'?\s+/", '', (string) $event->command);
            $out[str_replace("'", '', $command)] = $event->expression;
        }

        return $out;
    }

    public function test_the_expected_jobs_are_scheduled_at_the_expected_times(): void
    {
        $expected = [
            // Restored in cycle 18 from the dead kernel.
            'sales:import-daily --last-week' => '0 5 * * 0',
            'sales:import-daily --yesterday' => '0 6 * * *',
            'sales-accounting:import --days=7' => '10 6 * * *',
            'pos:populate-daily-summaries --last-days=7' => '15 6 * * *',
            // Already in routes/console.php.
            'sales:import-daily --today' => '0 20 * * *',
            'suppliers:send-daily-sales' => '15 20 * * *',
            'fruit-veg:prune-thumbnails' => '30 5 * * 0',
            'customers:send-statements' => '0 7 1 * *',
        ];

        $actual = $this->scheduled();

        foreach ($expected as $command => $expression) {
            $this->assertArrayHasKey($command, $actual, "{$command} is not scheduled");
            $this->assertSame($expression, $actual[$command], "{$command} runs at the wrong time");
        }
    }

    public function test_nothing_unexpected_is_scheduled(): void
    {
        // A new entry is fine, but it should be added here deliberately rather than
        // appearing by accident — the point of this file is that the schedule is
        // something someone has looked at.
        $this->assertCount(8, $this->scheduled());
    }

    public function test_the_kds_monitor_is_not_scheduled(): void
    {
        foreach (array_keys($this->scheduled()) as $command) {
            $this->assertStringNotContainsString('kds:monitor', $command);
        }
    }

    public function test_every_scheduled_command_exists(): void
    {
        $known = array_keys(\Illuminate\Support\Facades\Artisan::all());

        foreach (array_keys($this->scheduled()) as $command) {
            $name = explode(' ', $command)[0];
            $this->assertContains($name, $known, "{$name} is scheduled but is not a registered command");
        }
    }

    public function test_every_scheduled_command_guards_against_overlapping(): void
    {
        // Each of these reads the POS database or sends mail; two at once is at best
        // wasted work. The kernel set this on every entry and the restored ones keep it.
        foreach (app(Schedule::class)->events() as $event) {
            $this->assertNotEmpty(
                $event->withoutOverlapping,
                $event->command.' should use withoutOverlapping()'
            );
            $this->assertTrue($event->onOneServer, $event->command.' should use onOneServer()');
        }
    }
}
