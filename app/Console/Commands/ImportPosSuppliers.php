<?php

namespace App\Console\Commands;

use App\Models\AccountingSupplier;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportPosSuppliers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:import-pos 
                            {--dry-run : Preview the import without making changes}
                            {--force : Import even if suppliers already exist}
                            {--chunk=50 : Number of suppliers to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import suppliers from POS database into unified supplier management system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $chunkSize = (int) $this->option('chunk');

        $this->info('🚀 Starting POS Supplier Import');
        $this->newLine();

        try {
            // Check if we can connect to POS database
            $this->checkPosConnection();

            // Get existing POS-linked suppliers if not forcing
            $existingCount = 0;
            if (! $force) {
                $existingCount = AccountingSupplier::where('is_pos_linked', true)->count();
                if ($existingCount > 0) {
                    $this->warn("Found {$existingCount} existing POS-linked suppliers.");
                    if (! $this->confirm('Continue anyway? (existing suppliers will be skipped)')) {
                        return self::FAILURE;
                    }
                }
            }

            // Get POS suppliers
            $this->info('📡 Fetching suppliers from POS database...');
            $posSuppliers = Supplier::orderBy('SupplierID')->get();

            if ($posSuppliers->isEmpty()) {
                $this->warn('No suppliers found in POS database.');

                return self::SUCCESS;
            }

            $this->info("Found {$posSuppliers->count()} suppliers in POS database");
            $this->newLine();

            if ($dryRun) {
                $this->warn('🔍 DRY RUN MODE - No changes will be made');
                $this->newLine();
            }

            // Process suppliers in chunks
            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            $bar = $this->output->createProgressBar($posSuppliers->count());
            $bar->start();

            foreach ($posSuppliers->chunk($chunkSize) as $chunk) {
                foreach ($chunk as $posSupplier) {
                    try {
                        $result = $this->importSupplier($posSupplier, $dryRun, $force);

                        if ($result === 'processed') {
                            $processedCount++;
                        } elseif ($result === 'skipped') {
                            $skippedCount++;
                        }
                    } catch (Throwable $e) {
                        $errorCount++;
                        Log::error('Failed to import POS supplier', [
                            'supplier_id' => $posSupplier->SupplierID,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $bar->advance();
                }
            }

            $bar->finish();
            $this->newLine(2);

            // Display results
            $this->displayResults($processedCount, $skippedCount, $errorCount, $dryRun);

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());
            Log::error('POS supplier import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Check if we can connect to the POS database.
     */
    private function checkPosConnection(): void
    {
        try {
            DB::connection('pos')->getPdo();
            $this->info('✅ POS database connection successful');
        } catch (Throwable $e) {
            throw new \Exception('Cannot connect to POS database: '.$e->getMessage());
        }
    }

    /**
     * Import a single supplier from POS to Laravel.
     */
    private function importSupplier(Supplier $posSupplier, bool $dryRun, bool $force): string
    {
        // Check if supplier already exists
        $existing = AccountingSupplier::where('external_pos_id', $posSupplier->SupplierID)->first();

        if ($existing && ! $force) {
            return 'skipped';
        }

        // Prepare supplier data
        $supplierData = [
            'code' => $this->generateSupplierCode($posSupplier->SupplierID, $posSupplier->Supplier),
            'name' => $this->cleanString($posSupplier->Supplier) ?: 'Unnamed Supplier',
            'external_pos_id' => $posSupplier->SupplierID,
            'is_pos_linked' => true,
            'supplier_type' => $this->determineSupplierType($posSupplier),
            'address' => $this->cleanString($posSupplier->Address),
            'phone' => $this->cleanString($posSupplier->Phone),
            'email' => $this->cleanEmail($posSupplier->Email),
            'status' => 'active',
            'is_active' => true,
            'integration_type' => 'pos',
            'notes' => $this->buildNotesFromPosData($posSupplier),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($dryRun) {
            // In dry run, just show what would be imported
            $this->line("Would import: {$supplierData['name']} (POS ID: {$posSupplier->SupplierID})");

            return 'processed';
        }

        // Create or update supplier
        if ($existing && $force) {
            $existing->update($supplierData);
            $action = 'updated';
        } else {
            AccountingSupplier::create($supplierData);
            $action = 'created';
        }

        return 'processed';
    }

    /**
     * Generate a unique supplier code.
     */
    private function generateSupplierCode(string $posId, ?string $name): string
    {
        $baseCode = 'POS-'.str_pad($posId, 3, '0', STR_PAD_LEFT);

        // If name is available, try to incorporate it
        if ($name) {
            $nameCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3));
            if ($nameCode) {
                $baseCode = $nameCode.'-'.$posId;
            }
        }

        // Ensure uniqueness
        $code = $baseCode;
        $counter = 1;
        while (AccountingSupplier::where('code', $code)->exists()) {
            $code = $baseCode.'-'.$counter;
            $counter++;
        }

        return $code;
    }

    /**
     * Determine supplier type based on available data.
     */
    private function determineSupplierType(Supplier $posSupplier): string
    {
        // Based on existing supplier integrations config, we can categorize known suppliers
        $knownProductSuppliers = [5, 44, 85, 37]; // Udea variants and Independent

        if (in_array((int) $posSupplier->SupplierID, $knownProductSuppliers)) {
            return 'product';
        }

        // Default to 'other' for unknown suppliers
        return 'other';
    }

    /**
     * Build notes from POS data.
     */
    private function buildNotesFromPosData(Supplier $posSupplier): ?string
    {
        $notes = [];

        if ($posSupplier->PostCode) {
            $notes[] = "Post Code: {$posSupplier->PostCode}";
        }

        if ($posSupplier->Country) {
            $notes[] = "Country: {$posSupplier->Country}";
        }

        if ($posSupplier->Products) {
            $notes[] = "Products: {$posSupplier->Products}";
        }

        if ($posSupplier->Supplier_Type_ID) {
            $notes[] = "POS Supplier Type ID: {$posSupplier->Supplier_Type_ID}";
        }

        $notes[] = 'Imported from POS database on '.now()->format('Y-m-d H:i:s');

        return implode("\n", $notes);
    }

    /**
     * Clean and validate string data.
     */
    private function cleanString(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $cleaned = trim($value);

        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * Clean and validate email.
     */
    private function cleanEmail(?string $email): ?string
    {
        $cleaned = $this->cleanString($email);

        if (! $cleaned) {
            return null;
        }

        return filter_var($cleaned, FILTER_VALIDATE_EMAIL) ? $cleaned : null;
    }

    /**
     * Display import results.
     */
    private function displayResults(int $processed, int $skipped, int $errors, bool $dryRun): void
    {
        if ($dryRun) {
            $this->info('🔍 Dry Run Results:');
        } else {
            $this->info('✅ Import Complete!');
        }

        $this->table(['Metric', 'Count'], [
            ['Processed', $processed],
            ['Skipped', $skipped],
            ['Errors', $errors],
            ['Total', $processed + $skipped + $errors],
        ]);

        if ($errors > 0) {
            $this->warn("⚠️  {$errors} errors occurred. Check the application logs for details.");
        }

        if (! $dryRun && $processed > 0) {
            $this->info('🎉 Suppliers have been imported into the unified management system!');
            $this->info('💡 You can now manage these suppliers through the Laravel application.');
        }
    }
}
