<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\OSAccounts\OSInvoice;
use App\Models\VatReturn;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportOSAccountsVatReturns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osaccounts:import-vat-returns 
                            {--dry-run : Preview the import without making changes}
                            {--force : Import even if VAT returns already exist}
                            {--chunk=100 : Number of periods to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import historical VAT returns from OSAccounts INVOICES Assigned column';

    private $stats = [
        'total_periods' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'invoices_assigned' => 0,
    ];

    private $periodMapping = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting OSAccounts VAT returns import...');

        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        $chunkSize = (int) $this->option('chunk');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        try {
            // Get all unique Assigned values (VAT periods)
            $this->info('📊 Analyzing VAT periods from OSAccounts...');
            $uniquePeriods = $this->getUniquePeriods();

            $this->stats['total_periods'] = count($uniquePeriods);
            $this->info("📅 Found {$this->stats['total_periods']} unique VAT periods");

            if ($this->stats['total_periods'] === 0) {
                $this->warn('⚠️  No VAT periods found to import');

                return 0;
            }

            // Process each period
            $bar = $this->output->createProgressBar($this->stats['total_periods']);
            $bar->start();

            foreach ($uniquePeriods as $periodString) {
                $this->processPeriod($periodString, $isDryRun, $force);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            // Display summary
            $this->displaySummary();

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Import failed: '.$e->getMessage());
            Log::error('VAT returns import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Get all unique VAT period strings from OSAccounts
     */
    private function getUniquePeriods()
    {
        return OSInvoice::whereNotNull('Assigned')
            ->where('Assigned', '!=', '')
            ->distinct()
            ->pluck('Assigned')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Process a single VAT period
     */
    private function processPeriod($periodString, $isDryRun, $force)
    {
        try {
            // Parse the period string to get date range
            $dateRange = $this->parsePeriodString($periodString);
            if (! $dateRange) {
                $this->warn("⚠️  Could not parse period: {$periodString}");
                $this->stats['errors']++;

                return;
            }

            $periodStart = $dateRange['start'];
            $periodEnd = $dateRange['end'];
            $periodCode = $periodEnd->format('Y-m');

            // Check if VAT return already exists
            $existingReturn = VatReturn::where('return_period', $periodCode)->first();
            if ($existingReturn && ! $force) {
                $this->stats['skipped']++;

                return;
            }

            if ($isDryRun) {
                $this->info("🔍 Would process period: {$periodString} ({$periodCode})");
                $this->stats['created']++;

                return;
            }

            DB::beginTransaction();
            try {
                // Create or update VAT return
                $vatReturn = $existingReturn ?: new VatReturn;

                // Get all invoices for this period from OSAccounts
                $osInvoices = OSInvoice::where('Assigned', $periodString)
                    ->with('details')
                    ->get();

                // Calculate totals
                $totals = $this->calculatePeriodTotals($osInvoices);

                // Update VAT return
                $vatReturn->fill([
                    'return_period' => $periodCode,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'status' => 'submitted', // Historical imports are already submitted
                    'is_historical' => true,
                    'total_net' => $totals['total_net'],
                    'total_vat' => $totals['total_vat'],
                    'total_gross' => $totals['total_gross'],
                    'zero_net' => $totals['zero_net'],
                    'zero_vat' => $totals['zero_vat'],
                    'second_reduced_net' => $totals['second_reduced_net'],
                    'second_reduced_vat' => $totals['second_reduced_vat'],
                    'reduced_net' => $totals['reduced_net'],
                    'reduced_vat' => $totals['reduced_vat'],
                    'standard_net' => $totals['standard_net'],
                    'standard_vat' => $totals['standard_vat'],
                    'notes' => "Imported from OSAccounts: {$periodString}",
                    'submitted_date' => $periodEnd, // Use period end as submission date
                    'reference_number' => "OSA-{$periodCode}",
                ]);
                $vatReturn->save();

                // Update Laravel invoices with vat_return_id
                $updatedCount = $this->assignInvoicesToReturn($vatReturn, $osInvoices);
                $this->stats['invoices_assigned'] += $updatedCount;

                if ($existingReturn) {
                    $this->stats['updated']++;
                } else {
                    $this->stats['created']++;
                }

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error processing period {$periodString}: ".$e->getMessage());
            $this->stats['errors']++;
        }
    }

    /**
     * Parse period string like "Jan - Feb 2016" to date range
     */
    private function parsePeriodString($periodString)
    {
        // Common patterns:
        // "VAT Jan Feb 2024" or "Jan Feb 2024"
        // "VAT MAR APR 2017"
        // "VAT May June 2019"
        // "Jan - Feb 2016"
        // And various other formats

        $periodString = trim($periodString);

        // Remove "VAT" prefix if present (case insensitive)
        $periodString = preg_replace('/^VAT\s+/i', '', $periodString);
        $periodString = trim($periodString);

        // Skip invalid entries
        if (empty($periodString) || strtoupper($periodString) === 'ASSIGNED') {
            return null;
        }

        // Try to match two-month pattern like "Jan Feb 2024" or "MAR APR 2017" (no dash)
        if (preg_match('/^(\w+)\s+(\w+)\s+(\d{4})$/i', $periodString, $matches)) {
            $startMonth = $matches[1];
            $endMonth = $matches[2];
            $year = $matches[3];

            try {
                $start = Carbon::parse("1 {$startMonth} {$year}")->startOfMonth();
                $end = Carbon::parse("1 {$endMonth} {$year}")->endOfMonth();

                return [
                    'start' => $start,
                    'end' => $end,
                ];
            } catch (\Exception $e) {
                // Could not parse months
            }
        }

        // Try to match two-month pattern with dash like "Jan - Feb 2016"
        if (preg_match('/^(\w+)\s*-\s*(\w+)\s+(\d{4})$/i', $periodString, $matches)) {
            $startMonth = $matches[1];
            $endMonth = $matches[2];
            $year = $matches[3];

            try {
                $start = Carbon::parse("1 {$startMonth} {$year}")->startOfMonth();
                $end = Carbon::parse("1 {$endMonth} {$year}")->endOfMonth();

                return [
                    'start' => $start,
                    'end' => $end,
                ];
            } catch (\Exception $e) {
                // Could not parse months
            }
        }

        // Try to match patterns without year like "Jan Feb" (assume current year)
        if (preg_match('/^(\w+)\s+(\w+)$/i', $periodString, $matches)) {
            $startMonth = $matches[1];
            $endMonth = $matches[2];
            // Assume current year for missing year
            $year = date('Y');

            try {
                $start = Carbon::parse("1 {$startMonth} {$year}")->startOfMonth();
                $end = Carbon::parse("1 {$endMonth} {$year}")->endOfMonth();

                return [
                    'start' => $start,
                    'end' => $end,
                ];
            } catch (\Exception $e) {
                // Could not parse months
            }
        }

        // Try to match quarter pattern like "Q1 2019"
        if (preg_match('/^Q(\d)\s+(\d{4})$/i', $periodString, $matches)) {
            $quarter = (int) $matches[1];
            $year = $matches[2];

            $start = Carbon::create($year, ($quarter - 1) * 3 + 1, 1)->startOfMonth();
            $end = $start->copy()->addMonths(2)->endOfMonth();

            return [
                'start' => $start,
                'end' => $end,
            ];
        }

        // Try to match YYYY-MM pattern
        if (preg_match('/^(\d{4})-(\d{2})$/', $periodString, $matches)) {
            $year = $matches[1];
            $month = $matches[2];

            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            return [
                'start' => $start,
                'end' => $end,
            ];
        }

        // Try single month year pattern like "January 2020"
        if (preg_match('/^(\w+)\s+(\d{4})$/i', $periodString, $matches)) {
            $month = $matches[1];
            $year = $matches[2];

            try {
                $start = Carbon::parse("1 {$month} {$year}")->startOfMonth();
                $end = $start->copy()->endOfMonth();

                return [
                    'start' => $start,
                    'end' => $end,
                ];
            } catch (\Exception $e) {
                // Could not parse month
            }
        }

        // Could not parse
        return null;
    }

    /**
     * Calculate VAT totals for a period
     */
    private function calculatePeriodTotals($osInvoices)
    {
        $totals = [
            'total_net' => 0,
            'total_vat' => 0,
            'total_gross' => 0,
            'zero_net' => 0,
            'zero_vat' => 0,
            'second_reduced_net' => 0,
            'second_reduced_vat' => 0,
            'reduced_net' => 0,
            'reduced_vat' => 0,
            'standard_net' => 0,
            'standard_vat' => 0,
        ];

        foreach ($osInvoices as $invoice) {
            $breakdown = $invoice->vat_breakdown;

            $totals['zero_net'] += $breakdown['zero_net'];
            $totals['zero_vat'] += $breakdown['zero_vat'];
            $totals['second_reduced_net'] += $breakdown['second_reduced_net'];
            $totals['second_reduced_vat'] += $breakdown['second_reduced_vat'];
            $totals['reduced_net'] += $breakdown['reduced_net'];
            $totals['reduced_vat'] += $breakdown['reduced_vat'];
            $totals['standard_net'] += $breakdown['standard_net'];
            $totals['standard_vat'] += $breakdown['standard_vat'];
        }

        $totals['total_net'] = $totals['zero_net'] + $totals['second_reduced_net'] +
                                $totals['reduced_net'] + $totals['standard_net'];
        $totals['total_vat'] = $totals['zero_vat'] + $totals['second_reduced_vat'] +
                                $totals['reduced_vat'] + $totals['standard_vat'];
        $totals['total_gross'] = $totals['total_net'] + $totals['total_vat'];

        return $totals;
    }

    /**
     * Assign Laravel invoices to the VAT return
     */
    private function assignInvoicesToReturn($vatReturn, $osInvoices)
    {
        $osInvoiceIds = $osInvoices->pluck('ID')->toArray();

        // Update Laravel invoices that match these OSAccounts IDs
        return Invoice::whereIn('external_osaccounts_id', $osInvoiceIds)
            ->update(['vat_return_id' => $vatReturn->id]);
    }

    /**
     * Display import summary
     */
    private function displaySummary()
    {
        $this->newLine();
        $this->info('📊 Import Summary:');
        $this->info("   Total periods found: {$this->stats['total_periods']}");
        $this->info("   ✅ Created: {$this->stats['created']}");
        $this->info("   🔄 Updated: {$this->stats['updated']}");
        $this->info("   ⏭️  Skipped: {$this->stats['skipped']}");
        $this->info("   📎 Invoices assigned: {$this->stats['invoices_assigned']}");

        if ($this->stats['errors'] > 0) {
            $this->warn("   ❌ Errors: {$this->stats['errors']}");
        }
    }
}
