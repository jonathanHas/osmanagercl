<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixOSAccountsAssignedInvoices extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'osaccounts:fix-assigned-invoices 
                            {--dry-run : Preview the updates without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Fix OSAccounts invoices with ASSIGNED status by assigning them to dummy VAT period VAT Nov Dec 1900';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Fixing OSAccounts ASSIGNED invoices...');

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        try {
            // Get all invoices with ASSIGNED status
            $invoices = DB::connection('osaccounts')
                ->table('INVOICES')
                ->where('Assigned', 'ASSIGNED')
                ->select('ID', 'InvoiceNum', 'InvoiceDate')
                ->orderBy('InvoiceDate')
                ->get();

            $this->info("📋 Found {$invoices->count()} invoices with ASSIGNED status");

            if ($invoices->count() === 0) {
                $this->info('✅ No invoices need fixing');

                return 0;
            }

            // Show what will be updated
            $this->info('📝 Invoices to be updated:');
            foreach ($invoices as $invoice) {
                $this->line("   - Invoice {$invoice->InvoiceNum} ({$invoice->InvoiceDate}) -> VAT Nov Dec 1900");
            }

            if ($isDryRun) {
                $this->info('🔍 Would update '.$invoices->count().' invoices');

                return 0;
            }

            // Confirm before proceeding
            if (! $this->confirm('Do you want to proceed with updating these invoices?')) {
                $this->warn('❌ Operation cancelled');

                return 0;
            }

            // Update all invoices
            $updated = DB::connection('osaccounts')
                ->table('INVOICES')
                ->where('Assigned', 'ASSIGNED')
                ->update(['Assigned' => 'VAT Nov Dec 1900']);

            $this->info("✅ Successfully updated {$updated} invoices");

            // Show next steps
            $this->newLine();
            $this->info('📋 Next steps:');
            $this->info('   1. Run: php artisan osaccounts:import-vat-returns --force');
            $this->info('   2. Run: php artisan osaccounts:import-invoices --update-existing');
            $this->info('   This will sync the changes to the Laravel database');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error fixing ASSIGNED invoices: '.$e->getMessage());

            return 1;
        }
    }
}
