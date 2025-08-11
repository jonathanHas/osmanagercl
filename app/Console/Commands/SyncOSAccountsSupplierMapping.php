<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AccountingSupplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncOSAccountsSupplierMapping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osaccounts:sync-supplier-mapping 
                            {--dry-run : Preview the sync without making changes}
                            {--detailed : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync OSAccounts supplier IDs with Laravel suppliers based on POS IDs';

    private $stats = [
        'total' => 0,
        'updated' => 0,
        'already_mapped' => 0,
        'not_found' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔗 Starting OSAccounts supplier mapping sync...');
        
        $isDryRun = $this->option('dry-run');
        $verbose = $this->option('detailed');
        
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        try {
            // Get all suppliers with POS IDs
            $suppliersWithPosId = AccountingSupplier::whereNotNull('external_pos_id')
                ->get();
            
            $this->info("📋 Found {$suppliersWithPosId->count()} suppliers with POS IDs");

            $bar = $this->output->createProgressBar($suppliersWithPosId->count());
            $bar->start();

            foreach ($suppliersWithPosId as $supplier) {
                $this->stats['total']++;
                
                // Check if OSAccounts ID is already set and matches POS ID
                if ($supplier->external_osaccounts_id == $supplier->external_pos_id) {
                    $this->stats['already_mapped']++;
                    if ($verbose) {
                        $this->line("\n✓ {$supplier->name} already mapped correctly");
                    }
                } else {
                    // Check if this POS ID exists in OSAccounts EXPENSES_JOINED
                    $osSupplier = DB::connection('osaccounts')
                        ->table('EXPENSES_JOINED')
                        ->where('ID', $supplier->external_pos_id)
                        ->first();
                    
                    if ($osSupplier) {
                        if (!$isDryRun) {
                            $supplier->external_osaccounts_id = $supplier->external_pos_id;
                            $supplier->is_osaccounts_linked = 1;
                            $supplier->save();
                        }
                        
                        $this->stats['updated']++;
                        
                        if ($verbose) {
                            $this->line("\n📝 {$supplier->name}: Set OSAccounts ID to {$supplier->external_pos_id}");
                        }
                    } else {
                        $this->stats['not_found']++;
                        if ($verbose) {
                            $this->line("\n⚠️  {$supplier->name}: POS ID {$supplier->external_pos_id} not found in OSAccounts");
                        }
                    }
                }
                
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine();
            
            // Also check for UUID-based suppliers
            $this->info("\n🔍 Checking UUID-based suppliers...");
            
            $uuidSuppliers = AccountingSupplier::where('external_osaccounts_id', 'LIKE', '%-%-%-%-%')
                ->whereNull('external_pos_id')
                ->get();
                
            if ($uuidSuppliers->count() > 0) {
                $this->info("Found {$uuidSuppliers->count()} UUID-based suppliers (already correctly mapped)");
                $this->stats['already_mapped'] += $uuidSuppliers->count();
            }
            
            // Display results
            $this->displayResults($isDryRun);
            
            if (!$isDryRun && $this->stats['updated'] > 0) {
                $this->info('🎉 Supplier mapping sync completed successfully!');
                $this->comment('💡 You can now run osaccounts:import-invoices with proper supplier mapping');
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display sync results.
     */
    private function displayResults($isDryRun)
    {
        $this->newLine();
        $this->info('📊 Sync Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Suppliers Checked', $this->stats['total']],
                [$isDryRun ? 'Would Update' : 'Updated', $this->stats['updated']],
                ['Already Correctly Mapped', $this->stats['already_mapped']],
                ['Not Found in OSAccounts', $this->stats['not_found']],
            ]
        );
        
        if ($this->stats['not_found'] > 0) {
            $this->warn("⚠️  {$this->stats['not_found']} suppliers with POS IDs not found in OSAccounts");
            $this->comment('These suppliers may be POS-only and not used for invoicing');
        }
    }
}