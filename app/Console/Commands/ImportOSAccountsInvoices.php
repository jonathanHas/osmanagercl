<?php

namespace App\Console\Commands;

use App\Models\AccountingSupplier;
use App\Models\Invoice;
use App\Models\OSAccounts\OSInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportOSAccountsInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osaccounts:import-invoices 
                            {--dry-run : Preview the import without making changes}
                            {--force : Import even if invoices already exist}
                            {--chunk=100 : Number of invoices to process at once}
                            {--date-from= : Import invoices from this date (YYYY-MM-DD)}
                            {--date-to= : Import invoices to this date (YYYY-MM-DD)}
                            {--limit= : Limit the number of invoices to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import invoices from OSAccounts INVOICES and INVOICE_DETAIL tables to Laravel invoices table';

    private $stats = [
        'total' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'unmapped_suppliers' => 0,
    ];

    private $supplierMap = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting OSAccounts invoices import...');

        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        $chunkSize = (int) $this->option('chunk');
        $dateFrom = $this->option('date-from');
        $dateTo = $this->option('date-to');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        try {
            // Build supplier mapping
            $this->info('🔗 Building supplier mapping...');
            $this->buildSupplierMap();
            $this->info('✅ Mapped '.count($this->supplierMap).' suppliers');

            // Build invoice query
            $query = OSInvoice::with(['supplier', 'details']);

            // Apply date filters
            if ($dateFrom) {
                $query->where('InvoiceDate', '>=', $dateFrom);
                $this->info("📅 Filtering from date: {$dateFrom}");
            }

            if ($dateTo) {
                $query->where('InvoiceDate', '<=', $dateTo);
                $this->info("📅 Filtering to date: {$dateTo}");
            }

            // Apply limit
            if ($limit) {
                $query->limit($limit);
                $this->info("📊 Limiting to {$limit} invoices");
            }

            $invoiceCount = $query->count();
            $this->info("💰 Found {$invoiceCount} invoices to process");

            if ($invoiceCount === 0) {
                $this->warn('⚠️  No invoices found to import');

                return 0;
            }

            // Note: Individual invoice checking happens in processInvoiceChunk()
            // Existing invoices will be skipped unless --force is used to update them
            if (! $force) {
                $existingCount = Invoice::whereNotNull('external_osaccounts_id')
                    ->when($dateFrom, fn ($q) => $q->where('invoice_date', '>=', $dateFrom))
                    ->when($dateTo, fn ($q) => $q->where('invoice_date', '<=', $dateTo))
                    ->count();

                if ($existingCount > 0) {
                    $this->info("ℹ️  Found {$existingCount} existing OSAccounts invoices in this date range - these will be skipped.");
                }
            }

            // Process invoices in chunks
            $this->info("📦 Processing invoices in chunks of {$chunkSize}...");

            $bar = $this->output->createProgressBar($invoiceCount);
            $bar->start();

            $query->chunk($chunkSize, function ($invoices) use ($isDryRun, $bar) {
                $this->processInvoiceChunk($invoices, $isDryRun);
                $bar->advance($invoices->count());
            });

            $bar->finish();
            $this->newLine();

            // Display results
            $this->displayResults($isDryRun);

            if (! $isDryRun && $this->stats['created'] > 0) {
                $this->info('🎉 Import completed successfully!');
                Log::info('OSAccounts invoices import completed', $this->stats);
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Import failed: '.$e->getMessage());
            Log::error('OSAccounts invoices import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Build supplier mapping from OSAccounts to Laravel
     */
    private function buildSupplierMap()
    {
        $suppliers = AccountingSupplier::where('is_osaccounts_linked', true)
            ->whereNotNull('external_osaccounts_id')
            ->get();

        foreach ($suppliers as $supplier) {
            $this->supplierMap[$supplier->external_osaccounts_id] = $supplier->id;
        }
    }

    /**
     * Process a chunk of invoices
     */
    private function processInvoiceChunk($invoices, $isDryRun)
    {
        foreach ($invoices as $osInvoice) {
            $this->stats['total']++;

            try {
                // Check if invoice already exists
                $existing = Invoice::where('external_osaccounts_id', $osInvoice->ID)->first();

                if ($existing && ! $this->option('force')) {
                    $this->stats['skipped']++;

                    continue;
                }

                // Map supplier
                $supplierId = $this->supplierMap[$osInvoice->SupplierID] ?? null;
                if (! $supplierId) {
                    $this->stats['unmapped_suppliers']++;
                    Log::warning('Unmapped supplier in OSAccounts invoice', [
                        'invoice_id' => $osInvoice->ID,
                        'invoice_num' => $osInvoice->InvoiceNum,
                        'supplier_id' => $osInvoice->SupplierID,
                        'supplier_name' => $osInvoice->supplier?->Name,
                    ]);

                    continue;
                }

                // Get the Laravel supplier to get the correct name
                $laravelSupplier = AccountingSupplier::find($supplierId);
                $supplierName = $laravelSupplier ? $laravelSupplier->name : 'Unknown Supplier';

                // Get VAT breakdown
                $vatBreakdown = $osInvoice->vat_breakdown;

                // Calculate totals
                $subtotal = $osInvoice->subtotal;
                $vatAmount = $osInvoice->vat_amount;
                $totalAmount = $subtotal + $vatAmount;

                $invoiceData = [
                    'invoice_number' => (string) $osInvoice->InvoiceNum,
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplierName,
                    'invoice_date' => $osInvoice->InvoiceDate,
                    'due_date' => $osInvoice->PaidDate ?? $osInvoice->InvoiceDate?->addDays(30),
                    'subtotal' => $subtotal,
                    'vat_amount' => $vatAmount,
                    'total_amount' => $totalAmount,

                    // VAT breakdown
                    'standard_net' => $vatBreakdown['standard_net'],
                    'standard_vat' => $vatBreakdown['standard_vat'],
                    'reduced_net' => $vatBreakdown['reduced_net'],
                    'reduced_vat' => $vatBreakdown['reduced_vat'],
                    'second_reduced_net' => $vatBreakdown['second_reduced_net'],
                    'second_reduced_vat' => $vatBreakdown['second_reduced_vat'],
                    'zero_net' => $vatBreakdown['zero_net'],
                    'zero_vat' => $vatBreakdown['zero_vat'],

                    // Payment status and dates
                    'payment_status' => $osInvoice->payment_status,
                    'payment_date' => $osInvoice->PaidDate,

                    // Additional fields
                    'notes' => $osInvoice->Assigned ? "Period: {$osInvoice->Assigned}" : null,
                    'expense_category' => 'imported', // Default category for imported invoices

                    // External reference
                    'external_osaccounts_id' => $osInvoice->ID,

                    // Audit fields
                    'created_by' => 1, // System user
                    'updated_by' => 1,
                ];

                if (! $isDryRun) {
                    if ($existing) {
                        $existing->update($invoiceData);
                        $this->stats['updated']++;
                    } else {
                        Invoice::create($invoiceData);
                        $this->stats['created']++;
                    }
                } else {
                    $this->stats['created']++; // Count as would-be-created for dry run
                }

            } catch (\Exception $e) {
                $this->stats['errors']++;
                Log::error('Error processing OSAccounts invoice', [
                    'invoice_id' => $osInvoice->ID,
                    'invoice_num' => $osInvoice->InvoiceNum,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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
                ['Unmapped Suppliers', $this->stats['unmapped_suppliers']],
                ['Errors', $this->stats['errors']],
            ]
        );

        if ($this->stats['unmapped_suppliers'] > 0) {
            $this->warn("⚠️  {$this->stats['unmapped_suppliers']} invoices skipped due to unmapped suppliers");
            $this->info('Check logs for details on unmapped suppliers');
        }
    }
}
