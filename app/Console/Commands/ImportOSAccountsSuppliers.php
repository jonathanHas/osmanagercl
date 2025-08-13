<?php

namespace App\Console\Commands;

use App\Models\AccountingSupplier;
use App\Models\OSAccounts\OSSupplierType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log;

class ImportOSAccountsSuppliers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osaccounts:import-suppliers 
                            {--dry-run : Preview the import without making changes}
                            {--force : Import even if suppliers already exist}
                            {--chunk=50 : Number of suppliers to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import suppliers from OSAccounts EXPENSES_JOINED table (includes both POS and OSAccounts suppliers)';

    private $stats = [
        'total' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'pos_suppliers' => 0,
        'osaccounts_only' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting OSAccounts suppliers import...');

        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        $chunkSize = (int) $this->option('chunk');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        try {
            // Test OSAccounts connection
            $this->info('🔌 Testing OSAccounts database connection...');
            $osSupplierCount = DBFacade::connection('osaccounts')->table('EXPENSES_JOINED')->count();
            $this->info("✅ Found {$osSupplierCount} suppliers in EXPENSES_JOINED (POS + OSAccounts)");

            // Get supplier type mappings
            $this->info('📋 Loading supplier type mappings...');
            $typeMap = $this->getSupplierTypeMap();
            $this->info('✅ Loaded '.count($typeMap).' supplier type mappings');

            // Check existing suppliers if not forcing
            if (! $force) {
                $existingCount = AccountingSupplier::where('external_osaccounts_id', '!=', null)->count();
                if ($existingCount > 0) {
                    $this->error("❌ Found {$existingCount} existing OSAccounts suppliers. Use --force to override.");

                    return 1;
                }
            }

            // Process suppliers in chunks
            $this->info("📦 Processing suppliers in chunks of {$chunkSize}...");

            $bar = $this->output->createProgressBar($osSupplierCount);
            $bar->start();

            DBFacade::connection('osaccounts')
                ->table('EXPENSES_JOINED')
                ->orderBy('ID')
                ->chunk($chunkSize, function ($suppliers) use ($isDryRun, $typeMap, $bar) {
                    $this->processSupplierChunk($suppliers, $isDryRun, $typeMap);
                    $bar->advance($suppliers->count());
                });

            $bar->finish();
            $this->newLine();

            // Display results
            $this->displayResults($isDryRun);

            if (! $isDryRun && $this->stats['created'] > 0) {
                $this->info('🎉 Import completed successfully!');
                Log::info('OSAccounts suppliers import completed', $this->stats);
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Import failed: '.$e->getMessage());
            Log::error('OSAccounts suppliers import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Process a chunk of suppliers
     */
    private function processSupplierChunk($suppliers, $isDryRun, $typeMap)
    {
        foreach ($suppliers as $osSupplier) {
            $this->stats['total']++;

            try {
                // Determine if this is a POS supplier (numeric ID) or OSAccounts-only supplier (UUID)
                $isPosSupplier = is_numeric($osSupplier->ID);

                // Check if supplier already exists (by external ID or name)
                $query = AccountingSupplier::where('name', $osSupplier->Name);
                if ($isPosSupplier) {
                    $query->orWhere('external_pos_id', $osSupplier->ID);
                } else {
                    $query->orWhere('external_osaccounts_id', $osSupplier->ID);
                }
                $existing = $query->first();

                if ($existing && ! $this->option('force')) {
                    $this->stats['skipped']++;

                    continue;
                }

                // Map supplier type
                $supplierType = $typeMap[$osSupplier->Supplier_Type_ID] ?? 'other';

                // Generate unique code
                $code = $this->generateSupplierCode($osSupplier->Name, $osSupplier->ID);

                $supplierData = [
                    'code' => $code,
                    'name' => $osSupplier->Name,
                    'supplier_type' => $supplierType,
                    'status' => 'active', // All imported suppliers start as active
                    'is_active' => true,
                    'is_osaccounts_linked' => true,
                    'created_by' => 1, // System user
                    'updated_by' => 1,
                ];

                // Set the appropriate external ID based on whether it's a POS or OSAccounts supplier
                if ($isPosSupplier) {
                    // Numeric ID = POS supplier
                    $supplierData['external_pos_id'] = $osSupplier->ID;
                    $supplierData['external_osaccounts_id'] = $osSupplier->ID; // Also set OSAccounts ID for mapping
                    $supplierData['is_pos_linked'] = true;
                    $this->stats['pos_suppliers']++;
                } else {
                    // UUID = OSAccounts-only supplier
                    $supplierData['external_osaccounts_id'] = $osSupplier->ID;
                    $supplierData['external_pos_id'] = null;
                    $supplierData['is_pos_linked'] = false;
                    $this->stats['osaccounts_only']++;
                }

                if (! $isDryRun) {
                    if ($existing) {
                        $existing->update($supplierData);
                        $this->stats['updated']++;
                    } else {
                        AccountingSupplier::create($supplierData);
                        $this->stats['created']++;
                    }
                } else {
                    $this->stats['created']++; // Count as would-be-created for dry run
                }

            } catch (\Exception $e) {
                $this->stats['errors']++;
                Log::error('Error processing OSAccounts supplier', [
                    'supplier_id' => $osSupplier->ID,
                    'supplier_name' => $osSupplier->Name,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get supplier type mappings
     */
    private function getSupplierTypeMap()
    {
        $map = [];

        try {
            $types = OSSupplierType::all();
            foreach ($types as $type) {
                $map[$type->Supplier_Type_ID] = $type->toLaravelSupplierType();
            }
        } catch (\Exception $e) {
            $this->warn('⚠️  Could not load supplier types, using default mapping');
            // Fallback - assume all are 'other'
            $map = [];
        }

        return $map;
    }

    /**
     * Generate unique supplier code
     */
    private function generateSupplierCode($name, $osId)
    {
        // Create code from name (first 3-4 letters + numbers)
        $nameCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4));

        // Add part of OSAccounts ID for uniqueness
        $idSuffix = substr(str_replace('-', '', $osId), -4);

        $code = $nameCode.$idSuffix;

        // Ensure uniqueness
        $counter = 1;
        $originalCode = $code;
        while (AccountingSupplier::where('code', $code)->exists()) {
            $code = $originalCode.$counter;
            $counter++;
        }

        return $code;
    }

    /**
     * Display import results
     */
    private function displayResults($isDryRun)
    {
        $this->newLine();
        $this->info('📊 Import Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $this->stats['total']],
                [$isDryRun ? 'Would Create' : 'Created', $this->stats['created']],
                [$isDryRun ? 'Would Update' : 'Updated', $this->stats['updated']],
                ['Skipped', $this->stats['skipped']],
                ['Errors', $this->stats['errors']],
                ['POS Suppliers', $this->stats['pos_suppliers']],
                ['OSAccounts-Only', $this->stats['osaccounts_only']],
            ]
        );
    }
}
