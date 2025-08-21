<?php

namespace App\Console\Commands;

use App\Models\AmazonInvoicePending;
use App\Models\InvoiceBulkUpload;
use App\Models\InvoiceUploadFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateAmazonPendingInvoices extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'invoices:migrate-amazon-pending 
                            {--dry-run : Show what would be migrated without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate Amazon pending invoices from the old system to the unified bulk-upload system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('Migrating Amazon pending invoices to unified bulk-upload system...');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get all pending Amazon invoices from the old system
        $pendingInvoices = AmazonInvoicePending::where('status', 'pending')
            ->with(['uploadFile', 'user'])
            ->get();

        if ($pendingInvoices->isEmpty()) {
            $this->info('No pending Amazon invoices found to migrate.');
            return 0;
        }

        $this->info("Found {$pendingInvoices->count()} Amazon pending invoices to migrate:");

        foreach ($pendingInvoices as $pending) {
            $this->line("- ID: {$pending->id}, File: {$pending->uploadFile->original_filename}, User: {$pending->user->name}");
        }

        if (!$isDryRun) {
            if (!$this->confirm('Proceed with migration?')) {
                $this->info('Migration cancelled.');
                return 0;
            }
        }

        $migratedCount = 0;
        $errorCount = 0;

        foreach ($pendingInvoices as $pending) {
            try {
                if (!$isDryRun) {
                    DB::transaction(function () use ($pending) {
                        // Update the InvoiceUploadFile status to amazon_pending
                        $uploadFile = $pending->uploadFile;
                        $uploadFile->update([
                            'status' => 'amazon_pending',
                            'parsed_invoice_date' => $pending->invoice_date,
                            'parsed_invoice_number' => $pending->invoice_number,
                            'parsed_total_amount' => $pending->gbp_amount,
                            'parsed_data' => array_merge($uploadFile->parsed_data ?? [], [
                                'migrated_from_amazon_pending' => true,
                                'original_amazon_pending_id' => $pending->id,
                                'gbp_amount' => $pending->gbp_amount,
                            ]),
                        ]);

                        // Archive the old pending record (don't delete for history)
                        // Just update notes, keep existing status to avoid enum issues
                        $pending->update([
                            'notes' => ($pending->notes ? $pending->notes . "\n\n" : '') . 
                                      'Migrated to unified bulk-upload system on ' . now()->format('Y-m-d H:i:s'),
                        ]);

                        Log::info('Migrated Amazon pending invoice to bulk-upload system', [
                            'amazon_pending_id' => $pending->id,
                            'upload_file_id' => $uploadFile->id,
                            'batch_id' => $uploadFile->bulk_upload_id,
                        ]);
                    });
                }

                $this->info("✓ Migrated: {$pending->uploadFile->original_filename} (Pending ID: {$pending->id})");
                $migratedCount++;
                
            } catch (\Exception $e) {
                $this->error("✗ Failed to migrate: {$pending->uploadFile->original_filename} - {$e->getMessage()}");
                $errorCount++;
                
                if (!$isDryRun) {
                    Log::error('Failed to migrate Amazon pending invoice', [
                        'amazon_pending_id' => $pending->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }

        if ($isDryRun) {
            $this->info("\nDRY RUN COMPLETE:");
            $this->info("Would migrate: {$migratedCount} invoices");
            if ($errorCount > 0) {
                $this->error("Would fail: {$errorCount} invoices");
            }
        } else {
            $this->info("\nMIGRATION COMPLETE:");
            $this->info("Successfully migrated: {$migratedCount} invoices");
            
            if ($errorCount > 0) {
                $this->error("Failed to migrate: {$errorCount} invoices");
                return 1;
            }
            
            $this->info('All Amazon pending invoices have been migrated to the unified bulk-upload system!');
            $this->info('You can now access them at: /invoices/bulk-upload/amazon-pending');
        }

        return 0;
    }
}