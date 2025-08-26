<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class RecalculateInvoiceVatBreakdown extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:recalculate-vat-breakdown 
                            {--invoice-id= : Specific invoice ID to recalculate}
                            {--supplier-id= : Recalculate all invoices for a specific supplier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate VAT breakdown fields for invoices from their VAT lines';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting VAT breakdown recalculation...');
        
        $query = Invoice::with('vatLines');
        
        // Apply filters if provided
        if ($invoiceId = $this->option('invoice-id')) {
            $query->where('id', $invoiceId);
        }
        
        if ($supplierId = $this->option('supplier-id')) {
            $query->where('supplier_id', $supplierId);
        }
        
        $invoices = $query->get();
        $count = $invoices->count();
        
        if ($count === 0) {
            $this->warn('No invoices found to process.');
            return 0;
        }
        
        $this->info("Processing {$count} invoice(s)...");
        
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        
        $updated = 0;
        
        foreach ($invoices as $invoice) {
            // Store old values for comparison
            $oldValues = [
                'standard_net' => $invoice->standard_net,
                'standard_vat' => $invoice->standard_vat,
                'reduced_net' => $invoice->reduced_net,
                'reduced_vat' => $invoice->reduced_vat,
                'second_reduced_net' => $invoice->second_reduced_net,
                'second_reduced_vat' => $invoice->second_reduced_vat,
                'zero_net' => $invoice->zero_net,
                'zero_vat' => $invoice->zero_vat,
            ];
            
            // Recalculate totals (this now includes VAT breakdown)
            $invoice->calculateTotals();
            
            // Check if anything changed
            $changed = false;
            foreach ($oldValues as $field => $oldValue) {
                if (abs($invoice->$field - $oldValue) > 0.001) {
                    $changed = true;
                    break;
                }
            }
            
            if ($changed) {
                $updated++;
                $this->line('');
                $this->info("Updated invoice #{$invoice->invoice_number} (ID: {$invoice->id}):");
                
                if (abs($oldValues['standard_net'] - $invoice->standard_net) > 0.001) {
                    $this->line("  Standard Net: €" . number_format($oldValues['standard_net'], 2) . " → €" . number_format($invoice->standard_net, 2));
                }
                if (abs($oldValues['standard_vat'] - $invoice->standard_vat) > 0.001) {
                    $this->line("  Standard VAT: €" . number_format($oldValues['standard_vat'], 2) . " → €" . number_format($invoice->standard_vat, 2));
                }
                if (abs($oldValues['reduced_net'] - $invoice->reduced_net) > 0.001) {
                    $this->line("  Reduced Net: €" . number_format($oldValues['reduced_net'], 2) . " → €" . number_format($invoice->reduced_net, 2));
                }
                if (abs($oldValues['reduced_vat'] - $invoice->reduced_vat) > 0.001) {
                    $this->line("  Reduced VAT: €" . number_format($oldValues['reduced_vat'], 2) . " → €" . number_format($invoice->reduced_vat, 2));
                }
                if (abs($oldValues['second_reduced_net'] - $invoice->second_reduced_net) > 0.001) {
                    $this->line("  Second Reduced Net: €" . number_format($oldValues['second_reduced_net'], 2) . " → €" . number_format($invoice->second_reduced_net, 2));
                }
                if (abs($oldValues['second_reduced_vat'] - $invoice->second_reduced_vat) > 0.001) {
                    $this->line("  Second Reduced VAT: €" . number_format($oldValues['second_reduced_vat'], 2) . " → €" . number_format($invoice->second_reduced_vat, 2));
                }
                if (abs($oldValues['zero_net'] - $invoice->zero_net) > 0.001) {
                    $this->line("  Zero Net: €" . number_format($oldValues['zero_net'], 2) . " → €" . number_format($invoice->zero_net, 2));
                }
                if (abs($oldValues['zero_vat'] - $invoice->zero_vat) > 0.001) {
                    $this->line("  Zero VAT: €" . number_format($oldValues['zero_vat'], 2) . " → €" . number_format($invoice->zero_vat, 2));
                }
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        
        $this->line('');
        $this->line('');
        $this->info("Recalculation complete! Updated {$updated} out of {$count} invoice(s).");
        
        return 0;
    }
}