<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FixAttachmentPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attachments:fix-permissions 
                            {--dry-run : Preview what would be fixed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix file ownership and permissions for invoice attachments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - Showing current status only');
        }

        $this->info('🔧 Checking invoice attachment permissions...');

        $basePath = storage_path('app/private/invoices');

        if (! is_dir($basePath)) {
            $this->error('Invoice storage directory does not exist: '.$basePath);

            return 1;
        }

        $stats = [
            'total_files' => 0,
            'wrong_owner' => 0,
            'wrong_permissions' => 0,
            'would_fix' => 0,
            'fixed' => 0,
            'errors' => 0,
        ];

        // Get current user info
        $currentUser = posix_getpwuid(posix_geteuid());
        $webUser = 'www-data';

        $this->line("Current user: {$currentUser['name']}");
        $this->line("Web server user: {$webUser}");
        $this->newLine();

        // Check if we're running as root or www-data
        $canChangeOwnership = ($currentUser['name'] === 'root' || $currentUser['name'] === $webUser);

        if (! $canChangeOwnership && ! $isDryRun) {
            $this->warn('⚠️  Not running as root or www-data. Can only fix permissions, not ownership.');
            $this->warn('   For full fix, run: sudo -u www-data php artisan attachments:fix-permissions');
            $this->newLine();
        }

        // Process all files
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $path => $file) {
            if ($file->isFile()) {
                $stats['total_files']++;

                // Check ownership
                $owner = posix_getpwuid($file->getOwner());
                $group = posix_getgrgid($file->getGroup());
                $ownerName = $owner['name'] ?? 'unknown';
                $groupName = $group['name'] ?? 'unknown';

                $needsOwnershipFix = ($ownerName !== $webUser || $groupName !== $webUser);

                // Check permissions
                $perms = substr(sprintf('%o', $file->getPerms()), -4);
                $needsPermissionFix = ($perms !== '0664');

                if ($needsOwnershipFix) {
                    $stats['wrong_owner']++;
                }

                if ($needsPermissionFix) {
                    $stats['wrong_permissions']++;
                }

                if ($needsOwnershipFix || $needsPermissionFix) {
                    $stats['would_fix']++;

                    if (! $isDryRun) {
                        try {
                            // Fix permissions (we can always do this)
                            if ($needsPermissionFix) {
                                @chmod($path, 0664);
                            }

                            // Fix ownership (only if we have permission)
                            if ($needsOwnershipFix && $canChangeOwnership) {
                                $webUserInfo = posix_getpwnam($webUser);
                                if ($webUserInfo) {
                                    @chown($path, $webUserInfo['uid']);
                                    @chgrp($path, $webUserInfo['gid']);
                                }
                            }

                            $stats['fixed']++;

                            if ($stats['fixed'] % 10 === 0) {
                                $this->line("Fixed {$stats['fixed']} files...");
                            }

                        } catch (\Exception $e) {
                            $stats['errors']++;
                            $this->error("Error fixing {$path}: ".$e->getMessage());
                        }
                    } else {
                        // In dry-run mode, show problematic files
                        if ($stats['would_fix'] <= 10) {
                            $this->line(sprintf(
                                '  %s [Owner: %s:%s, Perms: %s]',
                                basename($path),
                                $ownerName,
                                $groupName,
                                $perms
                            ));
                        } elseif ($stats['would_fix'] === 11) {
                            $this->line('  ... and more');
                        }
                    }
                }
            }
        }

        // Display results
        $this->newLine();
        $this->info('📊 Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Files', $stats['total_files']],
                ['Files with Wrong Owner', $stats['wrong_owner']],
                ['Files with Wrong Permissions', $stats['wrong_permissions']],
                [$isDryRun ? 'Would Fix' : 'Fixed', $isDryRun ? $stats['would_fix'] : $stats['fixed']],
                ['Errors', $stats['errors']],
            ]
        );

        if (! $isDryRun && $stats['fixed'] > 0) {
            $this->info('✅ Permissions fixed successfully!');

            if (! $canChangeOwnership && $stats['wrong_owner'] > 0) {
                $this->newLine();
                $this->warn('⚠️  Some files still have wrong ownership.');
                $this->warn('   To fix ownership, run:');
                $this->line('   sudo chown -R www-data:www-data '.$basePath);
            }
        }

        if ($isDryRun && $stats['would_fix'] > 0) {
            $this->newLine();
            $this->comment('To fix these issues, run without --dry-run');
            $this->comment('For best results: sudo -u www-data php artisan attachments:fix-permissions');
        }

        return 0;
    }
}
