<?php

/*
|--------------------------------------------------------------------------
| spatie/laravel-backup
|--------------------------------------------------------------------------
|
| Nightly backup of the application database and files, run on production by
| the Laravel scheduler (routes/console.php) and written to the `backups`
| disk (config/filesystems.php). See docs/development/backups.md.
|
| Only the `mysql` connection is dumped. On production the POS (uniCenta) and
| OSAccounts databases live on other machines and are not this app's data to
| back up — the POS database is the live till.
|
*/

$include = [base_path()];

// Supplier invoice attachments can live outside the project tree
// (OSACCOUNTS_FILE_PATH). Back them up when that directory exists.
$osaccountsFiles = env('OSACCOUNTS_FILE_PATH');
if ($osaccountsFiles && is_dir($osaccountsFiles) && ! str_starts_with(rtrim($osaccountsFiles, '/').'/', base_path().'/')) {
    $include[] = $osaccountsFiles;
}

return [

    'backup' => [

        'name' => env('BACKUP_NAME', 'osmanager'),

        'source' => [

            'files' => [

                'include' => $include,

                // Everything here is reproducible (composer/npm/git/pip) or
                // transient. storage/logs alone is ~1 GB on production.
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    base_path('.git'),
                    base_path('tests'),
                    base_path('.phpunit.cache'),
                    base_path('bootstrap/cache'),
                    base_path('scripts/invoice-parser/venv'),
                    base_path('scripts/invoice-parser/__pycache__'),
                    base_path('.pytest_cache'),
                    base_path('JFolder_temp'),
                    // The package matches exclusions against realpath(), so the
                    // venv's bin/python* symlinks (→ /usr/bin/python3) escape
                    // the venv rule above and add ~24 MB of interpreter per
                    // archive. Excluding their target closes that gap.
                    '/usr/bin',
                    storage_path('framework/cache'),
                    storage_path('framework/sessions'),
                    storage_path('framework/views'),
                    storage_path('framework/testing'),
                    storage_path('logs'),
                    storage_path('pail'),
                    storage_path('app/backup-temp'),
                    // Derived PDF→image conversions; rebuilt on demand.
                    storage_path('app/private/temp/conversions'),
                ],

                'follow_links' => false,

                'ignore_unreadable_directories' => true,

                // Paths inside the archive are relative to the project root.
                'relative_path' => base_path(),
            ],

            'databases' => [
                'mysql',
            ],
        ],

        'database_dump_compressor' => Spatie\DbDumper\Compressors\GzipCompressor::class,

        'database_dump_file_timestamp_format' => 'Y-m-d-H-i-s',

        'database_dump_filename_base' => 'database',

        'database_dump_file_extension' => '',

        'destination' => [

            'compression_method' => ZipArchive::CM_DEFAULT,

            'compression_level' => 9,

            'filename_prefix' => 'osmanager-',

            // The `backups` disk in config/filesystems.php: BACKUP_PATH, which
            // must be OUTSIDE the project (default /var/backups/osmanager).
            'disks' => [
                'backups',
            ],
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        // The archive contains .env. Set BACKUP_ARCHIVE_PASSWORD to encrypt it
        // (AES-256). Leave empty for a plain zip — simpler to restore from.
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => 'default',

        'tries' => 1,

        'retry_delay' => 0,
    ],

    /*
    | Only problems are mailed. Successful runs are recorded in
    | storage/logs/backup.log by the scheduler, and `backup:monitor` mails if a
    | recent healthy archive is missing — so silence means "working", and a
    | broken mailer still shows up as a missing healthy-backup line in the log.
    */
    'notifications' => [

        'notifications' => [
            Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
            Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => [],
            Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => [],
            Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            // Comma-separated list. Empty = no backup mail is sent (problems
            // still reach backup.log). NOTE: the package validates this at
            // boot, so an invalid address here breaks EVERY artisan command,
            // including schedule:run — hence the filtering rather than a
            // raw env() string.
            'to' => array_values(array_filter(array_map('trim', explode(',', (string) env('BACKUP_NOTIFICATION_EMAIL', ''))))),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'OS Manager Backups'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],
    ],

    'monitor_backups' => [
        [
            'name' => env('BACKUP_NAME', 'osmanager'),
            'disks' => ['backups'],
            'health_checks' => [
                Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => (int) env('BACKUP_MAX_STORAGE_MB', 30000),
            ],
        ],
    ],

    /*
    | Retention. Production archives are roughly 0.5–1 GB each (invoice and
    | delivery PDFs do not compress), so the storage ceiling is the real limit:
    | the oldest archives go first once BACKUP_MAX_STORAGE_MB is exceeded.
    */
    'cleanup' => [
        'strategy' => Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 30,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 6,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => (int) env('BACKUP_MAX_STORAGE_MB', 30000),
        ],

        'tries' => 1,

        'retry_delay' => 0,
    ],
];
