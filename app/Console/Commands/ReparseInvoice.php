<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceVatLine;
use App\Services\InvoiceParsingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReparseInvoice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:reparse
                            {invoice? : Invoice number (BU-2026-001216) or numeric invoice id}
                            {--supplier= : Re-parse every invoice for this supplier id instead}
                            {--from= : Only invoices dated on or after this date (Y-m-d)}
                            {--to= : Only invoices dated on or before this date (Y-m-d)}
                            {--apply : Write the re-parsed figures back to the invoice}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-run the invoice parser against a stored invoice attachment and show (or apply) the differences';

    /**
     * The VAT columns compared and written back, keyed by parser breakdown key.
     */
    private const VAT_COLUMNS = [
        'vat_23' => ['net' => 'standard_net', 'vat' => 'standard_vat', 'category' => 'STANDARD', 'rate' => 0.23],
        'vat_13_5' => ['net' => 'reduced_net', 'vat' => 'reduced_vat', 'category' => 'REDUCED', 'rate' => 0.135],
        'vat_9' => ['net' => 'second_reduced_net', 'vat' => 'second_reduced_vat', 'category' => 'SECOND_REDUCED', 'rate' => 0.09],
        'vat_0' => ['net' => 'zero_net', 'vat' => 'zero_vat', 'category' => 'ZERO', 'rate' => 0.0],
    ];

    public function handle(InvoiceParsingService $parsingService): int
    {
        $invoices = $this->resolveInvoices();

        if ($invoices->isEmpty()) {
            $this->error('No matching invoice found.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->warn('Dry run — no changes will be written. Pass --apply to write.');
            $this->newLine();
        }

        $changed = 0;
        $skipped = 0;

        foreach ($invoices as $invoice) {
            $result = $this->reparse($invoice, $parsingService);

            if ($result === null) {
                $skipped++;

                continue;
            }

            if ($result) {
                $changed++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%d of %d invoice(s) differ from the parser%s. %d skipped.',
            $changed,
            $invoices->count(),
            $this->option('apply') ? ' and were updated' : '',
            $skipped
        ));

        return self::SUCCESS;
    }

    /**
     * Resolve the invoices to work on from the command arguments.
     */
    private function resolveInvoices()
    {
        $supplierId = $this->option('supplier');

        if ($supplierId) {
            $query = Invoice::where('supplier_id', $supplierId);

            if ($from = $this->option('from')) {
                $query->where('invoice_date', '>=', $from);
            }

            if ($to = $this->option('to')) {
                $query->where('invoice_date', '<=', $to);
            }

            return $query->orderBy('invoice_date')->get();
        }

        $identifier = $this->argument('invoice');

        if (! $identifier) {
            $this->error('Provide an invoice number/id, or --supplier=<id>.');

            return collect();
        }

        // An invoice number match wins; a bare number only falls back to the id when
        // no invoice carries that number (some invoice_numbers are plain digits).
        $byNumber = Invoice::where('invoice_number', $identifier)->get();

        if ($byNumber->isNotEmpty() || ! is_numeric($identifier)) {
            return $byNumber;
        }

        return Invoice::where('id', $identifier)->get();
    }

    /**
     * Re-parse one invoice. Returns null when skipped, true when it differs.
     */
    private function reparse(Invoice $invoice, InvoiceParsingService $parsingService): ?bool
    {
        $this->line(str_repeat('─', 72));
        $this->info("{$invoice->invoice_number} — {$invoice->supplier_name} — {$invoice->invoice_date->toDateString()}");

        $attachment = $invoice->attachments()->where('is_primary', true)->first()
            ?? $invoice->attachments()->first();

        if (! $attachment || ! $attachment->exists()) {
            $this->warn('  No stored attachment — skipped.');

            return null;
        }

        $response = $parsingService->testParser($attachment->full_storage_path);

        if (! ($response['success'] ?? false) || empty($response['result']['data'])) {
            $this->error('  Parser failed: '.($response['error'] ?? 'no data returned'));

            return null;
        }

        $data = $response['result']['data'];
        $breakdown = $data['vat_breakdown'] ?? [];
        $parsed = $this->buildTotals($breakdown);

        foreach ($response['result']['warnings'] ?? [] as $warning) {
            $this->warn('  Warning: '.$warning);
        }

        $rows = [];
        $differs = false;

        foreach ($this->comparisonColumns() as $column => $label) {
            $before = round((float) $invoice->{$column}, 2);
            $after = round($parsed[$column], 2);
            $delta = round($after - $before, 2);

            if (abs($delta) >= 0.01) {
                $differs = true;
            }

            $rows[] = [
                $label,
                number_format($before, 2),
                number_format($after, 2),
                $delta == 0.0 ? '' : sprintf('%+.2f', $delta),
            ];
        }

        $this->table(['Field', 'Stored', 'Parsed', 'Δ'], $rows);

        if (! $differs) {
            $this->line('  <fg=green>No change.</>');

            return false;
        }

        if (! $this->option('apply')) {
            return true;
        }

        if ($reason = $this->writeBlockedReason($invoice)) {
            $this->error('  Not written: '.$reason);

            return null;
        }

        $this->applyTotals($invoice, $parsed, $breakdown);
        $this->line('  <fg=green>Updated.</>');

        return true;
    }

    /**
     * Turn a parser VAT breakdown into the invoice's total columns.
     */
    private function buildTotals(array $breakdown): array
    {
        $totals = [];
        $subtotal = 0.0;
        $vatAmount = 0.0;

        foreach (self::VAT_COLUMNS as $key => $columns) {
            $net = (float) ($breakdown[$key]['net'] ?? 0);
            // Zero-rated lines never carry VAT, whatever the parser reports
            $vat = $columns['category'] === 'ZERO' ? 0.0 : (float) ($breakdown[$key]['vat'] ?? 0);

            $totals[$columns['net']] = $net;
            $totals[$columns['vat']] = $vat;
            $subtotal += $net;
            $vatAmount += $vat;
        }

        $totals['subtotal'] = round($subtotal, 2);
        $totals['vat_amount'] = round($vatAmount, 2);
        $totals['total_amount'] = round($subtotal + $vatAmount, 2);

        return $totals;
    }

    /**
     * The invoice columns shown in the before/after table, in display order.
     */
    private function comparisonColumns(): array
    {
        return [
            'subtotal' => 'Subtotal',
            'vat_amount' => 'VAT',
            'total_amount' => 'Total',
            'standard_net' => '23% net',
            'standard_vat' => '23% VAT',
            'reduced_net' => '13.5% net',
            'reduced_vat' => '13.5% VAT',
            'second_reduced_net' => '9% net',
            'second_reduced_vat' => '9% VAT',
            'zero_net' => '0% net',
        ];
    }

    /**
     * Why this invoice must not be rewritten, or null when it is safe to write.
     */
    private function writeBlockedReason(Invoice $invoice): ?string
    {
        if ($invoice->vat_return_id) {
            return "already included in VAT return #{$invoice->vat_return_id}";
        }

        $finalized = DB::table('vat_returns')
            ->where('status', 'finalized')
            ->where('period_start', '<=', $invoice->invoice_date->toDateString())
            ->where('period_end', '>=', $invoice->invoice_date->toDateString())
            ->first();

        if ($finalized) {
            return "invoice date falls inside finalized VAT return #{$finalized->id} "
                ."({$finalized->period_start} to {$finalized->period_end})";
        }

        return null;
    }

    /**
     * Write the re-parsed totals and rebuild the invoice's VAT lines.
     */
    private function applyTotals(Invoice $invoice, array $totals, array $breakdown): void
    {
        DB::transaction(function () use ($invoice, $totals, $breakdown) {
            $invoice->fill($totals);
            $invoice->updated_by = auth()->id() ?: $invoice->updated_by;
            $invoice->save();

            $invoice->vatLines()->delete();

            $lineNumber = 1;

            foreach (self::VAT_COLUMNS as $key => $columns) {
                $net = round((float) ($breakdown[$key]['net'] ?? 0), 2);

                if ($net == 0.0) {
                    continue;
                }

                $line = InvoiceVatLine::create([
                    'invoice_id' => $invoice->id,
                    'vat_category' => $columns['category'],
                    'net_amount' => $net,
                    'line_number' => $lineNumber++,
                    'created_by' => $invoice->updated_by,
                ]);

                // The model derives vat_amount as net * rate; the invoice's own VAT is
                // rounded per line and can differ by a cent, so restate it.
                $statedVat = round((float) $totals[$columns['vat']], 2);

                if ((float) $line->vat_amount !== $statedVat) {
                    $line->vat_amount = $statedVat;
                    $line->gross_amount = round($net + $statedVat, 2);
                    $line->save();
                }
            }
        });
    }
}
