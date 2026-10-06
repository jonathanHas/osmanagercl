<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesDepositPosTables;
use Tests\TestCase;

/**
 * deposits:install-till (deposit cycle 3). The sqlite POS is "not dev", so
 * every writing run needs --force or a confirmation.
 */
class DepositTillInstallerTest extends TestCase
{
    use CreatesDepositPosTables, RefreshDatabase;

    private const BUTTONS = "<configuration>\n"
        ."    <!-- SET Show Change in Cash Sale (Default=ENABLED -->\n"
        ."        <event key=\"ticket.close\" code=\"Ticket.Close\"/>\n"
        ."\n"
        ."        <!-- <event key=\"ticket.addline\" code=\"event.addline\"/> -->\n"
        ."</configuration>\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDepositPosTables();
        Storage::fake('local');
    }

    private function seedButtons(string $content = self::BUTTONS): void
    {
        DB::connection('pos')->table('RESOURCES')->insert([
            'ID' => 'tb', 'NAME' => 'Ticket.Buttons', 'RESTYPE' => 0, 'CONTENT' => $content,
        ]);
    }

    private function resource(string $name): ?string
    {
        return DB::connection('pos')->table('RESOURCES')->where('NAME', $name)->value('CONTENT');
    }

    private function file(string $name): string
    {
        return file_get_contents(resource_path("pos/deposit/$name.bsh"));
    }

    public function test_install_creates_backup_scripts_and_wires_the_events(): void
    {
        $this->seedButtons();

        $this->artisan('deposits:install-till', ['--force' => true])
            ->expectsOutputToContain('(NOT dev)')
            ->expectsOutputToContain('backup: created Ticket.Buttons.pre-deposit')
            ->expectsOutputToContain('script.Deposit.AddLine: created')
            ->expectsOutputToContain('script.Deposit.Change: created')
            ->expectsOutputToContain('Ticket.Buttons: wired')
            ->expectsOutputToContain('Installed.')
            ->assertExitCode(0);

        $this->assertSame(self::BUTTONS, $this->resource('Ticket.Buttons.pre-deposit'));
        $this->assertSame(md5($this->file('script.Deposit.AddLine')), md5($this->resource('script.Deposit.AddLine')));
        $this->assertSame(md5($this->file('script.Deposit.Change')), md5($this->resource('script.Deposit.Change')));
        $this->assertSame(
            str_replace(
                "        <event key=\"ticket.close\" code=\"Ticket.Close\"/>\n",
                "        <event key=\"ticket.close\" code=\"Ticket.Close\"/>\n"
                ."        <event key=\"ticket.addline\" code=\"script.Deposit.AddLine\"/>\n"
                ."        <event key=\"ticket.change\" code=\"script.Deposit.Change\"/>\n",
                self::BUTTONS
            ),
            $this->resource('Ticket.Buttons')
        );

        $files = Storage::disk('local')->files('deposit');
        $this->assertCount(1, $files);
        $this->assertSame(self::BUTTONS, Storage::disk('local')->get($files[0]));
    }

    public function test_a_second_install_changes_nothing(): void
    {
        $this->seedButtons();
        $this->artisan('deposits:install-till', ['--force' => true])->assertExitCode(0);
        $wired = $this->resource('Ticket.Buttons');

        $this->artisan('deposits:install-till', ['--force' => true])
            ->expectsOutputToContain('backup: already present (kept)')
            ->expectsOutputToContain('script.Deposit.AddLine: unchanged')
            ->expectsOutputToContain('script.Deposit.Change: unchanged')
            ->expectsOutputToContain('Ticket.Buttons: already wired')
            ->assertExitCode(0);

        $this->assertSame($wired, $this->resource('Ticket.Buttons'));
        $this->assertSame(1, DB::connection('pos')->table('RESOURCES')->where('NAME', 'Ticket.Buttons.pre-deposit')->count());
        $this->assertCount(1, Storage::disk('local')->files('deposit'));
    }

    public function test_a_changed_script_is_updated(): void
    {
        $this->seedButtons();
        $this->artisan('deposits:install-till', ['--force' => true]);
        DB::connection('pos')->table('RESOURCES')->where('NAME', 'script.Deposit.Change')->update(['CONTENT' => 'old']);

        $this->artisan('deposits:install-till', ['--check' => true])->assertExitCode(1);
        $this->artisan('deposits:install-till', ['--force' => true])
            ->expectsOutputToContain('script.Deposit.Change: updated')
            ->assertExitCode(0);

        $this->assertSame($this->file('script.Deposit.Change'), $this->resource('script.Deposit.Change'));
    }

    public function test_check_exits_1_before_and_0_after(): void
    {
        $this->seedButtons();

        $this->artisan('deposits:install-till', ['--check' => true])
            ->expectsOutputToContain('Not installed.')
            ->assertExitCode(1);
        $this->assertNull($this->resource('Ticket.Buttons.pre-deposit'));

        $this->artisan('deposits:install-till', ['--force' => true]);

        $this->artisan('deposits:install-till', ['--check' => true])
            ->expectsOutputToContain('Installed.')
            ->assertExitCode(0);
    }

    public function test_rollback_restores_the_original_and_keeps_the_backup(): void
    {
        $this->seedButtons();
        $this->artisan('deposits:install-till', ['--force' => true]);

        $this->artisan('deposits:install-till', ['--rollback' => true, '--force' => true])
            ->expectsOutputToContain('Ticket.Buttons: restored from Ticket.Buttons.pre-deposit')
            ->expectsOutputToContain('script.Deposit.AddLine: deleted')
            ->expectsOutputToContain('backup: kept')
            ->assertExitCode(0);

        $this->assertSame(self::BUTTONS, $this->resource('Ticket.Buttons'));
        $this->assertNull($this->resource('script.Deposit.AddLine'));
        $this->assertNull($this->resource('script.Deposit.Change'));
        $this->assertSame(self::BUTTONS, $this->resource('Ticket.Buttons.pre-deposit'));

        // And back again.
        $this->artisan('deposits:install-till', ['--force' => true])->assertExitCode(0);
        $this->artisan('deposits:install-till', ['--check' => true])->assertExitCode(0);
    }

    public function test_rollback_without_backup_is_an_error(): void
    {
        $this->seedButtons();

        $this->artisan('deposits:install-till', ['--rollback' => true, '--force' => true])
            ->expectsOutputToContain('No Ticket.Buttons.pre-deposit backup row')
            ->assertExitCode(1);

        $this->assertSame(self::BUTTONS, $this->resource('Ticket.Buttons'));
    }

    public function test_missing_anchor_writes_nothing(): void
    {
        $this->seedButtons("<configuration>\n</configuration>\n");

        $this->artisan('deposits:install-till', ['--force' => true])
            ->expectsOutputToContain('occurs 0 times')
            ->assertExitCode(1);

        $this->assertSame(1, DB::connection('pos')->table('RESOURCES')->count());
        $this->assertSame([], Storage::disk('local')->files('deposit'));
    }

    public function test_duplicate_anchor_writes_nothing(): void
    {
        $line = "        <event key=\"ticket.close\" code=\"Ticket.Close\"/>\n";
        $this->seedButtons("<configuration>\n$line$line</configuration>\n");

        $this->artisan('deposits:install-till', ['--force' => true])
            ->expectsOutputToContain('occurs 2 times')
            ->assertExitCode(1);

        $this->assertSame(1, DB::connection('pos')->table('RESOURCES')->count());
    }

    public function test_already_wired_without_backup_is_an_error(): void
    {
        $this->seedButtons(str_replace(
            "code=\"Ticket.Close\"/>\n",
            "code=\"Ticket.Close\"/>\n        <event key=\"ticket.addline\" code=\"script.Deposit.AddLine\"/>\n        <event key=\"ticket.change\" code=\"script.Deposit.Change\"/>\n",
            self::BUTTONS
        ));

        $this->artisan('deposits:install-till', ['--force' => true])
            ->expectsOutputToContain('already wired but no backup row exists')
            ->assertExitCode(1);

        $this->assertSame(1, DB::connection('pos')->table('RESOURCES')->count());
    }

    public function test_a_non_dev_pos_asks_and_no_aborts(): void
    {
        $this->seedButtons();

        $this->artisan('deposits:install-till')
            ->expectsConfirmation('Install the deposit till scripts on this POS?', 'no')
            ->expectsOutputToContain('Aborted, nothing was written.')
            ->assertExitCode(1);
        $this->assertSame(1, DB::connection('pos')->table('RESOURCES')->count());

        $this->artisan('deposits:install-till')
            ->expectsConfirmation('Install the deposit till scripts on this POS?', 'yes')
            ->assertExitCode(0);
        $this->assertNotNull($this->resource('script.Deposit.AddLine'));

        $this->artisan('deposits:install-till', ['--rollback' => true])
            ->expectsConfirmation('Roll back the deposit till scripts on this POS?', 'no')
            ->assertExitCode(1);
        $this->assertNotNull($this->resource('script.Deposit.AddLine'));
    }
}
