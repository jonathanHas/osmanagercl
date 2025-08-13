<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceAttachment;
use App\Models\OSAccounts\OSInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportOSAccountsAttachments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osaccounts:import-attachments 
                            {--dry-run : Preview the import without making changes}
                            {--base-path= : Base path where OSAccounts files are stored}
                            {--force : Import even if attachments already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import existing invoice file attachments from OSAccounts system';

    private $stats = [
        'total' => 0,
        'imported' => 0,
        'skipped' => 0,
        'errors' => 0,
        'not_found' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔗 Starting OSAccounts attachments import...');

        $isDryRun = $this->option('dry-run');
        $basePath = $this->option('base-path') ?? '/path/to/osaccounts/files'; // You'll need to update this
        $force = $this->option('force');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        if (! is_dir($basePath)) {
            $this->error("❌ Base path does not exist: {$basePath}");
            $this->comment('💡 Use --base-path option to specify the correct path to OSAccounts files');

            return 1;
        }

        try {
            // First get all Laravel invoice OSAccounts IDs
            $importedOSAccountsIds = Invoice::whereNotNull('external_osaccounts_id')
                ->pluck('external_osaccounts_id')
                ->toArray();

            if (empty($importedOSAccountsIds)) {
                $this->warn('⚠️  No imported OSAccounts invoices found in Laravel');

                return 0;
            }

            // Find OSAccounts invoices with file attachments that have been imported to Laravel
            $osInvoicesWithFiles = OSInvoice::whereNotNull('InvoicePath')
                ->whereNotNull('Filename')
                ->whereIn('ID', $importedOSAccountsIds)
                ->get();

            $this->info("📁 Found {$osInvoicesWithFiles->count()} OSAccounts invoices with files");

            if ($osInvoicesWithFiles->count() === 0) {
                $this->warn('⚠️  No OSAccounts invoices with files found');

                return 0;
            }

            $bar = $this->output->createProgressBar($osInvoicesWithFiles->count());
            $bar->start();

            foreach ($osInvoicesWithFiles as $osInvoice) {
                $this->stats['total']++;

                try {
                    // Find the corresponding Laravel invoice
                    $invoice = Invoice::where('external_osaccounts_id', $osInvoice->ID)->first();
                    if (! $invoice) {
                        $this->stats['skipped']++;
                        $bar->advance();

                        continue;
                    }

                    // Check if attachment already exists (unless forcing)
                    if (! $force && $invoice->attachments()->where('external_osaccounts_path', $osInvoice->InvoicePath)->exists()) {
                        $this->stats['skipped']++;
                        $bar->advance();

                        continue;
                    }

                    // Try to find the file with multiple fallback strategies
                    $filePath = $this->findFileWithFallbacks($basePath, $osInvoice->InvoicePath, $osInvoice->Filename);

                    if (! $filePath) {
                        $this->stats['not_found']++;
                        $primaryPath = $this->buildFilePath($basePath, $osInvoice->InvoicePath, $osInvoice->Filename);
                        Log::warning('OSAccounts attachment file not found after trying fallbacks', [
                            'invoice_id' => $osInvoice->ID,
                            'primary_path_tried' => $primaryPath,
                            'invoice_path' => $osInvoice->InvoicePath,
                            'filename' => $osInvoice->Filename,
                        ]);
                        $bar->advance();

                        continue;
                    }

                    if (! $isDryRun) {
                        $this->importAttachment($invoice, $filePath, $osInvoice);
                    }

                    $this->stats['imported']++;

                } catch (\Exception $e) {
                    $this->stats['errors']++;
                    Log::error('Error importing OSAccounts attachment', [
                        'invoice_id' => $osInvoice->ID,
                        'error' => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            // Display results
            $this->displayResults($isDryRun);

            if (! $isDryRun && $this->stats['imported'] > 0) {
                $this->info('🎉 Import completed successfully!');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Import failed: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Build the full file path from OSAccounts data.
     */
    private function buildFilePath(string $basePath, ?string $invoicePath, string $filename): string
    {
        if (empty($invoicePath)) {
            // If no path, file is directly in base directory
            return $basePath.'/'.$filename;
        }

        // Decode HTML entities (e.g., &amp; to &)
        $invoicePath = html_entity_decode($invoicePath, ENT_QUOTES | ENT_HTML5);
        $filename = html_entity_decode($filename, ENT_QUOTES | ENT_HTML5);

        // Normalize path separators
        $invoicePath = str_replace('\\', '/', $invoicePath);
        $invoicePath = trim($invoicePath, '/');

        // Check if the invoice path already contains the filename
        // OSAccounts sometimes stores "Supplier/filename.pdf" in InvoicePath
        if (str_ends_with($invoicePath, $filename)) {
            // Path already includes filename, use as-is
            return $basePath.'/'.$invoicePath;
        }

        // Check if InvoicePath ends with .pdf (full file path)
        if (str_ends_with(strtolower($invoicePath), '.pdf') ||
            str_ends_with(strtolower($invoicePath), '.xls') ||
            str_ends_with(strtolower($invoicePath), '.xlsx')) {
            // InvoicePath is the complete file path
            return $basePath.'/'.$invoicePath;
        }

        // Path is just the directory, append filename
        return $basePath.'/'.$invoicePath.'/'.$filename;
    }

    /**
     * Try multiple path variations to find the file.
     */
    private function findFileWithFallbacks(string $basePath, ?string $invoicePath, string $filename): ?string
    {
        // First try the standard path
        $primaryPath = $this->buildFilePath($basePath, $invoicePath, $filename);
        if (File::exists($primaryPath)) {
            return $primaryPath;
        }

        // If invoice path is empty, no fallbacks needed
        if (empty($invoicePath)) {
            return null;
        }

        // Decode HTML entities
        $invoicePath = html_entity_decode($invoicePath, ENT_QUOTES | ENT_HTML5);
        $filename = html_entity_decode($filename, ENT_QUOTES | ENT_HTML5);

        // Try path without timestamp suffix (e.g., remove _20250807_081150 from path)
        if (preg_match('/^(.+?)(_\d{8}_\d{6})\.(pdf|xls|xlsx)$/i', $invoicePath, $matches)) {
            $pathWithoutTimestamp = $matches[1].'.'.$matches[3];
            $fallbackPath = $basePath.'/'.$pathWithoutTimestamp;
            if (File::exists($fallbackPath)) {
                return $fallbackPath;
            }

            // Also try with just the directory part (remove timestamp and extension)
            $dirOnly = dirname($invoicePath);
            if ($dirOnly !== '.') {
                $fallbackPath = $basePath.'/'.$dirOnly.'/'.$filename;
                if (File::exists($fallbackPath)) {
                    return $fallbackPath;
                }
            }
        }

        // Try without HTML entities in the actual filesystem
        // Sometimes the filesystem has & but database has &amp;
        $decodedPath = str_replace('&amp;', '&', $invoicePath);
        if ($decodedPath !== $invoicePath) {
            $fallbackPath = $basePath.'/'.$decodedPath;
            if (File::exists($fallbackPath)) {
                return $fallbackPath;
            }

            // Try with filename appended
            if (! str_ends_with($decodedPath, $filename)) {
                $fallbackPath = $basePath.'/'.$decodedPath.'/'.$filename;
                if (File::exists($fallbackPath)) {
                    return $fallbackPath;
                }
            }
        }

        return null;
    }

    /**
     * Import a single attachment file.
     */
    private function importAttachment(Invoice $invoice, string $filePath, OSInvoice $osInvoice): void
    {
        $originalFilename = $osInvoice->Filename;
        $storedFilename = InvoiceAttachment::generateStoredFilename($originalFilename);
        $storagePath = InvoiceAttachment::generateFilePath($invoice->id, $storedFilename);

        // Get file information
        $mimeType = File::mimeType($filePath) ?? 'application/octet-stream';
        $fileSize = File::size($filePath);
        $fileHash = hash_file('sha256', $filePath);

        // Check if this exact file already exists (by hash) to prevent duplicates
        $existingAttachment = $invoice->attachments()
            ->where('file_hash', $fileHash)
            ->first();

        if ($existingAttachment) {
            Log::info('Skipping duplicate attachment (same file hash already exists)', [
                'invoice_id' => $invoice->id,
                'original_filename' => $originalFilename,
                'existing_attachment_id' => $existingAttachment->id,
            ]);

            return;
        }

        // Copy file to Laravel's private storage
        $fileContents = File::get($filePath);

        // Ensure the directory exists
        $directory = dirname($storagePath);
        Storage::disk('private')->makeDirectory($directory);

        // Store the file using Laravel Storage
        Storage::disk('private')->put($storagePath, $fileContents);

        // Get the full path for permissions fix
        $fullPath = Storage::disk('private')->path($storagePath);

        // PRODUCTION-SAFE: Set permissions that allow group access
        // This works regardless of who runs the command (cron, artisan, web)
        if (file_exists($fullPath)) {
            // Set file to be readable/writable by owner and group
            @chmod($fullPath, 0664);

            // Try to set the group to www-data if we have permission
            // This will work if:
            // 1. We're running as root (unlikely)
            // 2. We're running as a member of www-data group and the parent dir has setgid
            // 3. We're running as www-data user
            $wwwDataGroup = posix_getgrnam('www-data');
            if ($wwwDataGroup && isset($wwwDataGroup['gid'])) {
                @chgrp($fullPath, $wwwDataGroup['gid']);
            }

            // Also ensure parent directories have proper permissions and setgid bit
            $parentDir = dirname($fullPath);
            if (is_dir($parentDir)) {
                @chmod($parentDir, 02775); // rwxrwsr-x - setgid ensures new files inherit group

                // Try to set group on directory too
                if ($wwwDataGroup && isset($wwwDataGroup['gid'])) {
                    @chgrp($parentDir, $wwwDataGroup['gid']);
                }
            }
        }

        // Create attachment record
        InvoiceAttachment::create([
            'invoice_id' => $invoice->id,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'file_path' => $storagePath,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_hash' => $fileHash,
            'description' => 'Imported from OSAccounts',
            'attachment_type' => 'invoice_scan',
            'is_primary' => ! $invoice->hasAttachments(), // First attachment becomes primary
            'uploaded_by' => 1, // System user
            'external_osaccounts_path' => $osInvoice->InvoicePath,
        ]);

        Log::info('Successfully imported attachment', [
            'invoice_id' => $invoice->id,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'storage_path' => $storagePath,
            'full_path' => $fullPath,
            'file_exists' => file_exists($fullPath),
            'file_size' => $fileSize,
        ]);
    }

    /**
     * Display import results.
     */
    private function displayResults($isDryRun)
    {
        $this->newLine();
        $this->info('📊 Import Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total OSAccounts Files Found', $this->stats['total']],
                [$isDryRun ? 'Would Import' : 'Imported', $this->stats['imported']],
                ['Skipped (Already Exist)', $this->stats['skipped']],
                ['Files Not Found', $this->stats['not_found']],
                ['Errors', $this->stats['errors']],
            ]
        );

        if ($this->stats['not_found'] > 0) {
            $this->warn("⚠️  {$this->stats['not_found']} files not found on disk");
            $this->comment('💡 Check the --base-path option and file permissions');
        }

        if ($this->stats['errors'] > 0) {
            $this->warn("⚠️  {$this->stats['errors']} errors occurred during import");
            $this->comment('💡 Check logs for details');
        }
    }
}
