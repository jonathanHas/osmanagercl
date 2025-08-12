<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupDuplicateAttachments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attachments:cleanup-duplicates 
                            {--dry-run : Preview what would be cleaned up}
                            {--fix-permissions : Also fix file permissions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate invoice attachments and fix file permissions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $fixPermissions = $this->option('fix-permissions');
        
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('🧹 Starting duplicate attachment cleanup...');

        $stats = [
            'invoices_checked' => 0,
            'duplicates_found' => 0,
            'duplicates_removed' => 0,
            'permissions_fixed' => 0,
            'errors' => 0,
        ];

        // Process invoices with attachments
        Invoice::whereHas('attachments')
            ->chunk(100, function($invoices) use (&$stats, $isDryRun, $fixPermissions) {
                foreach ($invoices as $invoice) {
                    $stats['invoices_checked']++;
                    
                    $attachments = $invoice->attachments()
                        ->orderBy('created_at', 'asc')
                        ->get();
                    
                    if ($attachments->count() <= 1) {
                        continue;
                    }

                    // Group attachments by file hash
                    $attachmentsByHash = [];
                    foreach ($attachments as $attachment) {
                        $hash = $attachment->file_hash;
                        if (!isset($attachmentsByHash[$hash])) {
                            $attachmentsByHash[$hash] = [];
                        }
                        $attachmentsByHash[$hash][] = $attachment;
                    }

                    // Process duplicates
                    foreach ($attachmentsByHash as $hash => $duplicateGroup) {
                        if (count($duplicateGroup) > 1) {
                            $this->line("Invoice #{$invoice->invoice_number}: Found " . count($duplicateGroup) . " copies of same file");
                            
                            // Keep the first one (oldest), remove the rest
                            $toKeep = array_shift($duplicateGroup);
                            
                            // Fix permissions on the kept file
                            if ($fixPermissions && !$isDryRun) {
                                $fullPath = Storage::disk('private')->path($toKeep->file_path);
                                if (file_exists($fullPath)) {
                                    @chmod($fullPath, 0664);
                                    $stats['permissions_fixed']++;
                                }
                            }
                            
                            // Remove duplicates
                            foreach ($duplicateGroup as $duplicate) {
                                $stats['duplicates_found']++;
                                
                                if (!$isDryRun) {
                                    try {
                                        // Delete the file
                                        Storage::disk('private')->delete($duplicate->file_path);
                                        
                                        // Delete the database record
                                        $duplicate->delete();
                                        
                                        $stats['duplicates_removed']++;
                                        $this->info("  ✅ Removed duplicate: {$duplicate->original_filename}");
                                    } catch (\Exception $e) {
                                        $stats['errors']++;
                                        $this->error("  ❌ Error removing duplicate: " . $e->getMessage());
                                    }
                                } else {
                                    $this->line("  Would remove: {$duplicate->original_filename}");
                                }
                            }
                        } else {
                            // No duplicates, but fix permissions if requested
                            if ($fixPermissions && !$isDryRun) {
                                $attachment = $duplicateGroup[0];
                                $fullPath = Storage::disk('private')->path($attachment->file_path);
                                if (file_exists($fullPath)) {
                                    $currentPerms = substr(sprintf('%o', fileperms($fullPath)), -4);
                                    if ($currentPerms !== '0664') {
                                        @chmod($fullPath, 0664);
                                        $stats['permissions_fixed']++;
                                    }
                                }
                            }
                        }
                    }
                }
            });

        // Display results
        $this->newLine();
        $this->info('📊 Cleanup Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Invoices Checked', $stats['invoices_checked']],
                ['Duplicates Found', $stats['duplicates_found']],
                [$isDryRun ? 'Would Remove' : 'Duplicates Removed', $stats['duplicates_removed']],
                ['Permissions Fixed', $stats['permissions_fixed']],
                ['Errors', $stats['errors']],
            ]
        );

        if (!$isDryRun && $stats['duplicates_removed'] > 0) {
            $this->info('🎉 Cleanup completed successfully!');
        }

        return 0;
    }
}