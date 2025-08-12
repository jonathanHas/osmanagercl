<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\VatReturn;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportHistoricalVatReturns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vat-returns:import-historical 
                            {--dry-run : Preview the import without making changes}
                            {--force : Recreate VAT returns even if they exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import historical VAT returns from invoice notes and link invoices';

    private $stats = [
        'periods_found' => 0,
        'returns_created' => 0,
        'returns_updated' => 0,
        'invoices_linked' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting historical VAT returns import...');

        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        try {
            // Step 1: Extract unique VAT periods from invoice notes
            $this->info('📊 Extracting VAT periods from invoice notes...');
            $periods = $this->extractVatPeriods();

            if (empty($periods)) {
                $this->warn('⚠️  No VAT periods found in invoice notes');

                return 0;
            }

            $this->info('✅ Found '.count($periods).' unique VAT periods');

            // Step 2: Create VAT returns for each period
            $this->info('📝 Creating VAT returns...');
            $bar = $this->output->createProgressBar(count($periods));
            $bar->start();

            foreach ($periods as $periodData) {
                $this->processVatPeriod($periodData, $isDryRun, $force);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            // Step 3: Display results
            $this->displayResults($isDryRun);

            if (! $isDryRun && $this->stats['returns_created'] > 0) {
                $this->info('🎉 Import completed successfully!');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Import failed: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Extract unique VAT periods from invoice notes
     */
    private function extractVatPeriods(): array
    {
        // Get all unique periods from notes field
        $invoicesWithPeriods = Invoice::whereNotNull('notes')
            ->where('notes', 'LIKE', 'Period: %')
            ->select('notes', DB::raw('COUNT(*) as invoice_count'))
            ->groupBy('notes')
            ->get();

        $periods = [];

        foreach ($invoicesWithPeriods as $record) {
            // Extract period from "Period: VAT Jan Feb 2024" format
            $period = str_replace('Period: ', '', $record->notes);
            $period = trim($period);

            if ($period) {
                $periods[] = [
                    'original_text' => $period,
                    'invoice_count' => $record->invoice_count,
                    'parsed_data' => $this->parsePeriod($period),
                ];
                $this->stats['periods_found']++;
            }
        }

        // Sort by period dates
        usort($periods, function ($a, $b) {
            $dateA = $a['parsed_data']['period_end'] ?? '1900-01-01';
            $dateB = $b['parsed_data']['period_end'] ?? '1900-01-01';

            return strcmp($dateA, $dateB);
        });

        return $periods;
    }

    /**
     * Parse period text to extract dates
     */
    private function parsePeriod(string $periodText): array
    {
        // Remove "VAT" prefix if present
        $cleaned = preg_replace('/^VAT\s+/i', '', $periodText);
        $cleaned = trim($cleaned);

        // Common patterns:
        // "Jan Feb 2024" or "Jan Feb 2024"
        // "Mar April 2024" or "Mar Apr 2024"
        // "May June 2024" or "May Jun 2024"
        // "VAT Nov Dec 2024"

        // Extract year (should be last 4 digits)
        preg_match('/\d{4}$/', $cleaned, $yearMatch);
        $year = $yearMatch[0] ?? date('Y');

        // Extract months
        $monthsText = preg_replace('/\s*\d{4}$/', '', $cleaned);
        $monthsText = trim($monthsText);

        // Parse month names
        $months = $this->parseMonths($monthsText, $year);

        return [
            'period_start' => $months['start'] ?? null,
            'period_end' => $months['end'] ?? null,
            'return_period' => $this->formatReturnPeriod($months, $year),
            'original_text' => $periodText,
        ];
    }

    /**
     * Parse month names and return start/end dates
     */
    private function parseMonths(string $monthsText, string $year): array
    {
        $monthMap = [
            'jan' => '01', 'january' => '01',
            'feb' => '02', 'february' => '02',
            'mar' => '03', 'march' => '03',
            'apr' => '04', 'april' => '04',
            'may' => '05',
            'jun' => '06', 'june' => '06',
            'jul' => '07', 'july' => '07',
            'aug' => '08', 'august' => '08',
            'sep' => '09', 'september' => '09', 'sept' => '09',
            'oct' => '10', 'october' => '10',
            'nov' => '11', 'november' => '11',
            'dec' => '12', 'december' => '12',
        ];

        // Split months (handle various separators)
        $parts = preg_split('/[\s,]+/', strtolower($monthsText));
        $months = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if (isset($monthMap[$part])) {
                $months[] = $monthMap[$part];
            }
        }

        if (empty($months)) {
            return [];
        }

        // Determine start and end dates
        $startMonth = min($months);
        $endMonth = max($months);

        // Create Carbon dates
        $startDate = Carbon::createFromFormat('Y-m-d', "$year-$startMonth-01");
        $endDate = Carbon::createFromFormat('Y-m-d', "$year-$endMonth-01")->endOfMonth();

        return [
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d'),
        ];
    }

    /**
     * Format return period for storage
     */
    private function formatReturnPeriod(array $months, string $year): string
    {
        if (empty($months['start'])) {
            return $year;
        }

        // Extract month numbers
        $startMonth = date('m', strtotime($months['start']));
        $endMonth = date('m', strtotime($months['end']));

        // Check if it's a quarter
        $quarters = [
            '01-03' => 'Q1',
            '04-06' => 'Q2',
            '07-09' => 'Q3',
            '10-12' => 'Q4',
        ];

        foreach ($quarters as $range => $quarter) {
            [$qStart, $qEnd] = explode('-', $range);
            if ($startMonth == $qStart && $endMonth == $qEnd) {
                return "$year-$quarter";
            }
        }

        // Check if bi-monthly
        if ($endMonth - $startMonth == 1) {
            return "$year-".str_pad($startMonth, 2, '0', STR_PAD_LEFT).'-'.str_pad($endMonth, 2, '0', STR_PAD_LEFT);
        }

        // Single month
        if ($startMonth == $endMonth) {
            return "$year-".str_pad($startMonth, 2, '0', STR_PAD_LEFT);
        }

        // Default format
        return "$year-$startMonth-$endMonth";
    }

    /**
     * Process a single VAT period
     */
    private function processVatPeriod(array $periodData, bool $isDryRun, bool $force): void
    {
        $parsed = $periodData['parsed_data'];

        if (! $parsed['period_start'] || ! $parsed['period_end']) {
            $this->stats['errors']++;
            $this->warn('⚠️  Could not parse dates for period: '.$periodData['original_text']);

            return;
        }

        // Check if VAT return already exists
        $existing = VatReturn::where('return_period', $parsed['return_period'])->first();

        if ($existing && ! $force) {
            // Just link the invoices
            if (! $isDryRun) {
                $this->linkInvoicesToReturn($existing, $periodData['original_text']);
            }

            return;
        }

        if (! $isDryRun) {
            DB::beginTransaction();
            try {
                // Create or update VAT return
                $vatReturn = $existing ?: new VatReturn;

                $vatReturn->fill([
                    'return_period' => $parsed['return_period'],
                    'period_start' => $parsed['period_start'],
                    'period_end' => $parsed['period_end'],
                    'status' => 'finalized',
                    'is_historical' => true,
                    'notes' => 'Imported from OSAccounts: '.$periodData['original_text'],
                    'created_by' => 1, // System user
                ]);

                $vatReturn->save();

                if ($existing) {
                    $this->stats['returns_updated']++;
                } else {
                    $this->stats['returns_created']++;
                }

                // Link invoices to this VAT return
                $linkedCount = $this->linkInvoicesToReturn($vatReturn, $periodData['original_text']);

                // Calculate totals
                $vatReturn->calculateTotals();

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                $this->stats['errors']++;
                $this->error("Error processing period {$periodData['original_text']}: ".$e->getMessage());
            }
        } else {
            // Dry run - just count
            $this->stats['returns_created']++;
            $invoiceCount = Invoice::where('notes', 'Period: '.$periodData['original_text'])->count();
            $this->stats['invoices_linked'] += $invoiceCount;
        }
    }

    /**
     * Link invoices to a VAT return based on the period in notes
     */
    private function linkInvoicesToReturn(VatReturn $vatReturn, string $originalPeriodText): int
    {
        $count = Invoice::where('notes', 'Period: '.$originalPeriodText)
            ->whereNull('vat_return_id')
            ->update(['vat_return_id' => $vatReturn->id]);

        $this->stats['invoices_linked'] += $count;

        return $count;
    }

    /**
     * Display import results
     */
    private function displayResults(bool $isDryRun): void
    {
        $this->newLine();
        $this->info('📊 Import Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['VAT Periods Found', $this->stats['periods_found']],
                [$isDryRun ? 'Would Create Returns' : 'Returns Created', $this->stats['returns_created']],
                [$isDryRun ? 'Would Update Returns' : 'Returns Updated', $this->stats['returns_updated']],
                [$isDryRun ? 'Would Link Invoices' : 'Invoices Linked', $this->stats['invoices_linked']],
                ['Errors', $this->stats['errors']],
            ]
        );

        if (! $isDryRun) {
            // Show summary of unassigned invoices
            $unassigned = Invoice::whereNull('vat_return_id')->count();
            $this->info("📌 Remaining unassigned invoices: $unassigned");
        }
    }
}
