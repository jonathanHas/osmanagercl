<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\OSAccounts\OSInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecalculateOSAccountsInvoiceVAT extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osaccounts:recalculate-vat 
                            {--dry-run : Preview the changes without updating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate VAT for existing OSAccounts invoices with corrected ex-VAT logic';

    private $stats = [
        'total' => 0,
        'updated' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Recalculating VAT for OSAccounts invoices...');

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        try {
            // Find invoices that were imported from OSAccounts
            $recentInvoices = Invoice::whereNotNull('external_osaccounts_id')->get();

            $this->info("🔍 Found {$recentInvoices->count()} recent invoices to recalculate");

            if ($recentInvoices->count() === 0) {
                $this->warn('⚠️  No recent invoices found to recalculate');

                return 0;
            }

            $bar = $this->output->createProgressBar($recentInvoices->count());
            $bar->start();

            foreach ($recentInvoices as $invoice) {
                $this->stats['total']++;

                try {
                    // Find matching OSAccounts invoice by external ID
                    $osInvoice = OSInvoice::with(['details', 'supplier'])
                        ->where('ID', $invoice->external_osaccounts_id)
                        ->first();

                    if (! $osInvoice) {
                        continue; // Skip if can't find matching OSAccounts invoice
                    }

                    // Get corrected VAT breakdown using the fixed calculation
                    $vatBreakdown = $osInvoice->vat_breakdown;

                    // Calculate new totals
                    $subtotal = $osInvoice->subtotal;
                    $vatAmount = $osInvoice->vat_amount;
                    $totalAmount = $subtotal + $vatAmount;

                    if (! $isDryRun) {
                        $invoice->update([
                            'subtotal' => $subtotal,
                            'vat_amount' => $vatAmount,
                            'total_amount' => $totalAmount,
                            'standard_net' => $vatBreakdown['standard_net'],
                            'standard_vat' => $vatBreakdown['standard_vat'],
                            'reduced_net' => $vatBreakdown['reduced_net'],
                            'reduced_vat' => $vatBreakdown['reduced_vat'],
                            'second_reduced_net' => $vatBreakdown['second_reduced_net'],
                            'second_reduced_vat' => $vatBreakdown['second_reduced_vat'],
                            'zero_net' => $vatBreakdown['zero_net'],
                            'zero_vat' => $vatBreakdown['zero_vat'],
                            'payment_status' => $osInvoice->payment_status, // Update payment status with corrected logic
                            'external_osaccounts_id' => $osInvoice->ID, // Also add the missing external ID
                        ]);
                    }

                    $this->stats['updated']++;

                } catch (\Exception $e) {
                    $this->stats['errors']++;
                    Log::error('Error recalculating invoice VAT', [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'error' => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            // Display results
            $this->displayResults($isDryRun);

            if (! $isDryRun && $this->stats['updated'] > 0) {
                $this->info('🎉 VAT recalculation completed successfully!');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ VAT recalculation failed: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Display recalculation results
     */
    private function displayResults($isDryRun)
    {
        $this->newLine();
        $this->info('📊 Recalculation Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $this->stats['total']],
                [$isDryRun ? 'Would Update' : 'Updated', $this->stats['updated']],
                ['Errors', $this->stats['errors']],
            ]
        );

        if ($this->stats['updated'] > 0 && ! $isDryRun) {
            $this->info('✅ Invoices now have correct ex-VAT + VAT = total calculations');
        }
    }
}
