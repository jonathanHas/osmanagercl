<?php

namespace Tests\Feature;

use Spatie\Backup\Config\Config;
use Tests\TestCase;

/**
 * Guards the nightly backup wiring (docs/development/backups.md). None of
 * these run a real backup; they check the configuration the scheduler will
 * use on production, which is the part that silently rots.
 */
class BackupConfigurationTest extends TestCase
{
    public function test_backup_config_is_valid_with_no_notification_email(): void
    {
        // spatie/laravel-backup validates config at boot. An invalid recipient
        // throws from every artisan command, including schedule:run, so the
        // empty-by-default case must produce a valid (empty) recipient list.
        config(['backup.notifications.mail.to' => []]);

        $config = Config::fromArray(config('backup'));

        $this->assertSame([], $config->notifications->mail->to);
        $this->assertSame(['mysql'], config('backup.backup.source.databases'));
        $this->assertSame(['backups'], config('backup.backup.destination.disks'));
    }

    public function test_backups_disk_lives_outside_the_project(): void
    {
        $root = rtrim(config('filesystems.disks.backups.root'), '/');

        $this->assertNotEmpty($root);
        $this->assertStringStartsNotWith(base_path(), $root.'/');
    }

    public function test_reproducible_and_transient_paths_are_excluded(): void
    {
        $excluded = config('backup.backup.source.files.exclude');

        foreach (['vendor', 'node_modules', '.git', 'scripts/invoice-parser/venv'] as $path) {
            $this->assertContains(base_path($path), $excluded, "$path should not be archived");
        }

        foreach (['logs', 'framework/cache', 'framework/sessions', 'framework/views', 'app/backup-temp'] as $path) {
            $this->assertContains(storage_path($path), $excluded, "storage/$path should not be archived");
        }

        $this->assertContains(base_path(), config('backup.backup.source.files.include'));
    }

    public function test_mysql_connection_dumps_in_a_single_transaction(): void
    {
        $dump = config('database.connections.mysql.dump');

        $this->assertTrue($dump['useSingleTransaction']);
        $this->assertNotEmpty($dump['dumpBinaryPath']);
    }
}
