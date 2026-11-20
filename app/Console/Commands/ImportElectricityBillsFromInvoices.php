<?php

namespace App\Console\Commands;

use App\Models\ElectricityBill;
use App\Models\Invoice;
use App\Services\Electricity\BillTextExtractor;
use App\Services\Electricity\ElectricityBillImportService;
use Illuminate\Console\Command;

class ImportElectricityBillsFromInvoices extends Command
{
    protected $signature = 'electricity:import-from-invoices
        {--supplier= : accounting supplier id (default: every supplier of type "utility")}
        {--from= : only invoices dated on or after this date (YYYY-MM-DD)}
        {--dry-run : list what would be read without saving}';

    protected $description = 'Load electricity bills from the PDFs already attached to accounts invoices';

    public function handle(ElectricityBillImportService $importer, BillTextExtractor $extractor): int
    {
        $supplier = $this->option('supplier') ? (int) $this->option('supplier') : null;

        if ($this->option('dry-run')) {
            $rows = Invoice::query()
                ->with('attachments')
                ->whereHas('attachments', fn ($q) => $q->where('mime_type', 'application/pdf'))
                ->when($supplier,
                    fn ($q) => $q->where('supplier_id', $supplier),
                    fn ($q) => $q->whereHas('supplier', fn ($s) => $s->where('supplier_type', 'utility')))
                ->when($this->option('from'), fn ($q, $from) => $q->whereDate('invoice_date', '>=', $from))
                ->orderBy('invoice_date')
                ->get()
                ->map(function (Invoice $invoice) use ($importer, $extractor) {
                    $label = "#{$invoice->id} {$invoice->supplier_name} ".$invoice->invoice_date?->format('d/m/Y');
                    $attachment = $invoice->attachments->filter(fn ($a) => $a->isPdf())->sortByDesc('is_primary')->first();
                    if (! $attachment || ! $attachment->exists()) {
                        return [$label, 'PDF not on disk'];
                    }
                    $text = $extractor->text($attachment->full_storage_path);
                    $parser = $importer->parserFor($text);

                    return [$label, $parser
                        ? $parser->provider().' parser'
                        : ($importer->looksLikeElectricityBill($text) ? 'AI (no parser for this layout)' : 'not an electricity bill')];
                });

            $this->table(['Invoice', 'Would be read by'], $rows->all());

            return self::SUCCESS;
        }

        $outcome = $importer->importFromInvoices($supplier, $this->option('from'));

        $this->table(['Invoice', 'Result', 'Detail'], $outcome['rows']);
        $this->info(collect($outcome['counts'])->map(fn ($n, $k) => "$k: $n")->implode(', '));
        $this->info('Confirmed: '.ElectricityBill::confirmed()->count()
            .', needs review: '.ElectricityBill::where('status', ElectricityBill::STATUS_NEEDS_REVIEW)->count());

        return self::SUCCESS;
    }
}
