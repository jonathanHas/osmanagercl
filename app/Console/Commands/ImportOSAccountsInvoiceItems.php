<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\InvoiceVatLine;
use App\Models\OSAccounts\OSInvoice;
use App\Models\OSAccounts\OSInvoiceDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportOSAccountsInvoiceItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osaccounts:import-invoice-vat-lines 
                            {--dry-run : Preview the import without making changes}
                            {--force : Import even if VAT lines already exist}
                            {--invoice= : Import VAT lines for specific invoice number only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import VAT lines from OSAccounts INVOICE_DETAIL table grouped by VAT rate';

    private $stats = [
        'invoices_processed' => 0,
        'vat_lines_imported' => 0,
        'vat_lines_skipped' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔗 Starting OSAccounts invoice VAT lines import...');
        
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        $specificInvoice = $this->option('invoice');
        
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        try {
            // Get Laravel invoices that were imported from OSAccounts
            $query = Invoice::whereNotNull('external_osaccounts_id');
            
            if ($specificInvoice) {
                $query->where('invoice_number', $specificInvoice);
            }
            
            // If not forcing, exclude invoices that already have VAT lines
            if (!$force) {
                $query->doesntHave('vatLines');
            }
            
            $invoices = $query->with('vatLines')->get();
            
            $this->info("📋 Found {$invoices->count()} invoices to process");
            
            if ($invoices->count() === 0) {
                $this->warn('⚠️  No invoices found to process');
                return 0;
            }

            $bar = $this->output->createProgressBar($invoices->count());
            $bar->start();

            foreach ($invoices as $invoice) {
                $this->processInvoice($invoice, $isDryRun, $force);
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine();
            
            // Display results
            $this->displayResults($isDryRun);
            
            if (!$isDryRun && $this->stats['vat_lines_imported'] > 0) {
                $this->info('🎉 Import completed successfully!');
                $this->info('💡 You can now edit invoices with detailed VAT lines');
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Import failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Process a single invoice for VAT lines import.
     */
    private function processInvoice(Invoice $invoice, bool $isDryRun, bool $force): void
    {
        $this->stats['invoices_processed']++;
        
        try {
            // Skip if invoice already has VAT lines and not forcing
            if (!$force && $invoice->vatLines->count() > 0) {
                $this->stats['vat_lines_skipped']++;
                return;
            }
            
            // Find corresponding OSAccounts invoice
            $osInvoice = OSInvoice::find($invoice->external_osaccounts_id);
            if (!$osInvoice) {
                Log::warning('OSAccounts invoice not found', [
                    'invoice_id' => $invoice->id,
                    'external_id' => $invoice->external_osaccounts_id
                ]);
                $this->stats['errors']++;
                return;
            }
            
            // Get invoice details from OSAccounts
            $osDetails = OSInvoiceDetail::where('InvoiceID', $osInvoice->ID)->get();
            
            if ($osDetails->count() === 0) {
                // Create VAT lines from invoice totals if no details exist
                if (!$isDryRun) {
                    $this->createVatLinesFromTotals($invoice);
                }
                $this->stats['vat_lines_imported']++;
                return;
            }
            
            // Clear existing VAT lines if forcing
            if ($force && !$isDryRun) {
                $invoice->vatLines()->delete();
            }
            
            // Group details by VAT rate and create VAT lines
            if (!$isDryRun) {
                $vatLineCount = $this->createVatLinesFromDetails($invoice, $osDetails);
                $this->stats['vat_lines_imported'] += $vatLineCount;
            } else {
                // For dry run, estimate VAT lines that would be created
                $vatGroups = $this->groupDetailsByVat($osDetails);
                $this->stats['vat_lines_imported'] += count($vatGroups);
            }
            
        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error('Error processing invoice for VAT lines import', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create VAT lines from invoice totals when no detail records exist.
     */
    private function createVatLinesFromTotals(Invoice $invoice): void
    {
        // Use the existing VAT breakdown from the invoice
        $vatBreakdown = $invoice->getVatBreakdown();
        
        if (empty($vatBreakdown)) {
            // Fallback: create single VAT line from totals
            $vatCategory = $this->determineVatCategory($invoice->subtotal, $invoice->vat_amount);
            
            InvoiceVatLine::create([
                'invoice_id' => $invoice->id,
                'vat_category' => $vatCategory,
                'net_amount' => $invoice->subtotal,
                'line_number' => 1,
                'created_by' => 1, // System user
            ]);
        } else {
            // Create VAT lines from existing breakdown
            $lineNumber = 1;
            foreach ($vatBreakdown as $breakdown) {
                if ($breakdown['net_amount'] > 0) {
                    InvoiceVatLine::create([
                        'invoice_id' => $invoice->id,
                        'vat_category' => $breakdown['code'],
                        'net_amount' => $breakdown['net_amount'],
                        'line_number' => $lineNumber++,
                        'created_by' => 1, // System user
                    ]);
                }
            }
        }
    }

    /**
     * Create VAT lines from OSAccounts detail records grouped by VAT rate.
     */
    private function createVatLinesFromDetails(Invoice $invoice, $osDetails): int
    {
        $vatGroups = $this->groupDetailsByVat($osDetails);
        $lineNumber = 1;
        
        foreach ($vatGroups as $vatCategory => $group) {
            InvoiceVatLine::create([
                'invoice_id' => $invoice->id,
                'vat_category' => $vatCategory,
                'net_amount' => $group['total_net'],
                'line_number' => $lineNumber++,
                'created_by' => 1, // System user
            ]);
        }
        
        return count($vatGroups);
    }

    /**
     * Group OSAccounts details by VAT category and sum amounts.
     */
    private function groupDetailsByVat($osDetails): array
    {
        $vatGroups = [];
        
        foreach ($osDetails as $detail) {
            $netAmount = (float) $detail->Amount; // OSAccounts stores net amounts
            $vatInfo = $this->getVatInfoFromDetail($detail, $netAmount);
            $vatCategory = $vatInfo['vat_category'];
            
            if (!isset($vatGroups[$vatCategory])) {
                $vatGroups[$vatCategory] = [
                    'total_gross' => 0,
                    'total_net' => 0,
                    'total_vat' => 0,
                    'vat_rate' => $vatInfo['vat_rate'],
                    'count' => 0,
                ];
            }
            
            $vatGroups[$vatCategory]['total_gross'] += $vatInfo['gross_amount'];
            $vatGroups[$vatCategory]['total_net'] += $vatInfo['net_amount'];
            $vatGroups[$vatCategory]['total_vat'] += $vatInfo['vat_amount'];
            $vatGroups[$vatCategory]['count']++;
        }
        
        return $vatGroups;
    }

    /**
     * Get VAT information from OSAccounts detail.
     */
    private function getVatInfoFromDetail(OSInvoiceDetail $detail, float $netAmount): array
    {
        // Map OSAccounts VAT IDs to our system
        $vatMapping = [
            '000' => ['category' => 'ZERO', 'rate' => 0.0],
            '001' => ['category' => 'REDUCED', 'rate' => 0.135],
            '002' => ['category' => 'STANDARD', 'rate' => 0.23],
            '003' => ['category' => 'SECOND_REDUCED', 'rate' => 0.09],
        ];
        
        $vatId = $detail->VatID ?? '002'; // Default to standard rate
        $vatInfo = $vatMapping[$vatId] ?? $vatMapping['002'];
        
        // OSAccounts stores net amounts (ex-VAT), calculate gross
        $vatRate = $vatInfo['rate'];
        $vatAmount = $netAmount * $vatRate;
        $grossAmount = $netAmount + $vatAmount;
        
        return [
            'vat_category' => $vatInfo['category'],
            'vat_rate' => $vatRate,
            'net_amount' => round($netAmount, 2),
            'vat_amount' => round($vatAmount, 2),
            'gross_amount' => round($grossAmount, 2),
        ];
    }

    /**
     * Determine VAT category from amounts.
     */
    private function determineVatCategory(float $netAmount, float $vatAmount): string
    {
        if ($netAmount <= 0) return 'ZERO';
        
        $vatRate = $vatAmount / $netAmount;
        
        if ($vatRate >= 0.22 && $vatRate <= 0.24) return 'STANDARD';      // ~23%
        if ($vatRate >= 0.12 && $vatRate <= 0.14) return 'REDUCED';       // ~13.5%
        if ($vatRate >= 0.08 && $vatRate <= 0.10) return 'SECOND_REDUCED'; // ~9%
        if ($vatRate < 0.01) return 'ZERO';                               // ~0%
        
        return 'STANDARD'; // Default
    }

    /**
     * Display import results.
     */
    private function displayResults(bool $isDryRun): void
    {
        $this->newLine();
        $this->info('📊 Import Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Invoices Processed', $this->stats['invoices_processed']],
                [$isDryRun ? 'Would Import VAT Lines' : 'VAT Lines Imported', $this->stats['vat_lines_imported']],
                ['VAT Lines Skipped', $this->stats['vat_lines_skipped']],
                ['Errors', $this->stats['errors']],
            ]
        );
        
        if ($this->stats['errors'] > 0) {
            $this->warn("⚠️  {$this->stats['errors']} errors occurred during import");
            $this->comment('💡 Check logs for details');
        }
    }
}