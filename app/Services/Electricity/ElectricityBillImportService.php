<?php

namespace App\Services\Electricity;

use App\Models\ElectricityBill;
use App\Models\ElectricityMeter;
use App\Models\ElectricitySupplyPoint;
use App\Models\Invoice;
use App\Services\Electricity\Contracts\ElectricityBillParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a bill PDF into an ElectricityBill: provider parser first, AI when no
 * parser knows the layout, the same checks either way. A parser result that
 * passes every blocking check is confirmed; everything else waits for review.
 */
class ElectricityBillImportService
{
    /** @var array<int, class-string<ElectricityBillParser>> */
    public const PARSERS = [
        FlogasBillParser::class,
    ];

    public function __construct(
        private BillTextExtractor $extractor,
        private ElectricityBillValidator $validator,
        private ElectricityBillAiReader $aiReader,
    ) {}

    /**
     * @param  bool  $keepFile  copy the PDF into electricity/ on the private disk (uploads);
     *                          false when the PDF already lives on an accounts invoice
     * @return array{result: string, bill: ?ElectricityBill, message: string}
     *                                                                        result: imported | updated | duplicate | unsupported
     */
    public function importFromPdf(string $path, ?Invoice $invoice = null, ?string $originalFilename = null, bool $keepFile = false): array
    {
        $hash = hash_file('sha256', $path);

        if ($existing = ElectricityBill::where('file_hash', $hash)->first()) {
            $this->linkInvoice($existing, $invoice);

            return ['result' => 'duplicate', 'bill' => $existing, 'message' => 'This file is already loaded.'];
        }

        $fullText = $this->extractor->text($path);
        $parser = $this->parserFor($fullText);

        if ($parser) {
            $cropped = $parser->cropWidth() ? $this->extractor->text($path, $parser->cropWidth()) : null;
            $data = $parser->parse($fullText, $cropped);
            $source = 'parser';
        } elseif (! $this->looksLikeElectricityBill($fullText)) {
            return ['result' => 'unsupported', 'bill' => null, 'message' => 'Not an electricity bill (no MPRN found).'];
        } elseif ($this->aiReader->isAvailable()) {
            $data = $this->aiReader->read($path);
            $source = 'ai';
        } else {
            return ['result' => 'unsupported', 'bill' => null,
                'message' => 'No parser knows this provider\'s layout and AI reading is not set up (AI Diagnostics → Electricity Bill Reading).'];
        }

        $existing = $this->findExisting($data);
        if ($existing && $existing->isConfirmed()) {
            $this->linkInvoice($existing, $invoice);

            return ['result' => 'duplicate', 'bill' => $existing,
                'message' => "Bill {$existing->bill_number} is already loaded and confirmed."];
        }

        $filePath = null;
        if ($keepFile) {
            $year = substr($data['issue_date'] ?? now()->toDateString(), 0, 4);
            $filePath = "electricity/{$year}/{$hash}.pdf";
            Storage::disk('private')->put($filePath, file_get_contents($path));

            // Same shrinking as invoice uploads; the hash stays that of the file as uploaded.
            try {
                app(\App\Services\PdfCompressionService::class)->compress(Storage::disk('private')->path($filePath));
            } catch (\Throwable $e) {
                // the uncompressed copy is fine
            }
        }

        $bill = $this->store($data, $source, $existing, [
            'invoice_id' => $invoice?->id ?? $existing?->invoice_id,
            'original_filename' => $originalFilename ?? basename($path),
            'file_path' => $filePath ?? $existing?->file_path,
            'file_hash' => $hash,
        ]);

        return ['result' => $existing ? 'updated' : 'imported', 'bill' => $bill, 'message' => ''];
    }

    /**
     * Load every utility-supplier invoice PDF already in the accounts.
     *
     * @return array{counts: array<string, int>, rows: array<int, array{0: string, 1: string, 2: string}>}
     */
    public function importFromInvoices(?int $supplierId = null, ?string $from = null): array
    {
        $invoices = Invoice::query()
            ->with(['attachments', 'supplier'])
            ->whereHas('attachments', fn ($q) => $q->where('mime_type', 'application/pdf'))
            ->when($supplierId,
                fn ($q) => $q->where('supplier_id', $supplierId),
                fn ($q) => $q->whereHas('supplier', fn ($s) => $s->where('supplier_type', 'utility')))
            ->when($from, fn ($q) => $q->whereDate('invoice_date', '>=', $from))
            ->orderBy('invoice_date')
            ->get();

        $counts = ['imported' => 0, 'updated' => 0, 'duplicate' => 0, 'unsupported' => 0, 'missing' => 0, 'failed' => 0];
        $rows = [];

        foreach ($invoices as $invoice) {
            $label = "#{$invoice->id} {$invoice->supplier_name} ".$invoice->invoice_date?->format('d/m/Y');
            $attachment = $invoice->attachments->filter(fn ($a) => $a->isPdf())->sortByDesc('is_primary')->first();

            if (! $attachment || ! $attachment->exists()) {
                $counts['missing']++;
                $rows[] = [$label, 'PDF not on disk', ''];

                continue;
            }

            try {
                $outcome = $this->importFromPdf($attachment->full_storage_path, $invoice, $attachment->original_filename);
                $counts[$outcome['result']]++;
                $bill = $outcome['bill'];
                $rows[] = [$label, $outcome['result'], $bill ? "{$bill->bill_number} {$bill->status}" : $outcome['message']];
            } catch (\Throwable $e) {
                $counts['failed']++;
                $rows[] = [$label, 'failed', $e->getMessage()];
            }
        }

        $this->revalidateAll();

        return ['counts' => $counts, 'rows' => $rows];
    }

    /** Continuity checks depend on the neighbouring bills, which may have arrived out of order. */
    public function revalidateAll(): void
    {
        ElectricityBill::orderBy('issue_date')->get()->each(fn ($bill) => $this->revalidate($bill));
    }

    /** Read the bill's PDF again with AI and replace what is stored. Always lands in review. */
    public function rereadWithAi(ElectricityBill $bill): ElectricityBill
    {
        $path = $bill->pdfPath();
        if (! $path) {
            throw new \RuntimeException('The PDF for this bill is no longer on disk.');
        }

        return $this->store($this->aiReader->read($path), 'ai', $bill);
    }

    public function parserFor(string $fullText): ?ElectricityBillParser
    {
        foreach (self::PARSERS as $class) {
            $parser = app($class);
            if ($parser->supports($fullText)) {
                return $parser;
            }
        }

        return null;
    }

    public function looksLikeElectricityBill(string $text): bool
    {
        return preg_match('/\bMPRN\b/i', $text) === 1;
    }

    /**
     * Write a neutral bill array to the database (replacing the bill's lines
     * when it exists), run the checks and set the status.
     */
    public function store(array $data, string $source, ?ElectricityBill $bill = null, array $extra = []): ElectricityBill
    {
        return DB::transaction(function () use ($data, $source, $bill, $extra) {
            $point = null;
            if (! empty($data['mprn'])) {
                $point = ElectricitySupplyPoint::firstOrCreate(['mprn' => $data['mprn']]);
                $point->fill(array_filter([
                    'mic_kva' => $data['mic_kva'] ?? null,
                    'dg_group' => $data['dg_group'] ?? null,
                ], fn ($v) => $v !== null))->save();
            }

            $bill ??= new ElectricityBill;
            $bill->fill($extra + [
                'electricity_supply_point_id' => $point?->id,
                'provider' => $data['provider'] ?? 'Unknown',
                'account_number' => $data['account_number'] ?? null,
                'bill_number' => $data['bill_number'] ?? null,
                'document_type' => ($data['document_type'] ?? 'invoice') === 'credit_note' ? 'credit_note' : 'invoice',
                'issue_date' => $data['issue_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'tariff' => $data['tariff'] ?? null,
                'net_total' => $data['net_total'] ?? null,
                'vat_total' => $data['vat_total'] ?? null,
                'export_credit_total' => $data['export_credit_total'] ?? 0,
                'gross_total' => $data['gross_total'] ?? null,
                'import_kwh' => $this->validator->billedKwh($data['charges'] ?? []),
                'export_kwh' => abs(array_sum(array_map(
                    fn ($c) => ($c['category'] ?? null) === 'export_credit' ? (float) ($c['quantity'] ?? 0) : 0,
                    $data['charges'] ?? []
                ))),
                'source' => $source,
                'warnings' => $data['warnings'] ?? [],
            ]);
            $bill->save();

            $bill->charges()->delete();
            foreach ($data['charges'] ?? [] as $charge) {
                $bill->charges()->create([
                    'category' => $charge['category'] ?? 'other',
                    'band' => $charge['band'] ?? null,
                    'description' => mb_substr((string) ($charge['description'] ?? ''), 0, 255),
                    'quantity' => $charge['quantity'] ?? null,
                    'unit' => $charge['unit'] ?? null,
                    'rate' => $charge['rate'] ?? null,
                    'period_start' => $charge['period_start'] ?? null,
                    'period_end' => $charge['period_end'] ?? null,
                    'net_amount' => $charge['net_amount'] ?? 0,
                    'vat_rate' => $charge['vat_rate'] ?? null,
                ]);
            }

            $bill->readings()->delete();
            foreach ($data['readings'] ?? [] as $reading) {
                if (! $point || empty($reading['meter_serial']) || empty($reading['period_start']) || empty($reading['period_end'])) {
                    continue; // reported by the "vocabulary" check
                }
                $meter = ElectricityMeter::firstOrCreate([
                    'electricity_supply_point_id' => $point->id,
                    'serial' => $reading['meter_serial'],
                ]);
                $bill->readings()->create([
                    'electricity_meter_id' => $meter->id,
                    'band' => $reading['band'] ?? '24h',
                    'period_start' => $reading['period_start'],
                    'period_end' => $reading['period_end'],
                    'previous_reading' => $reading['previous_reading'] ?? 0,
                    'previous_type' => $reading['previous_type'] ?? 'estimate',
                    'present_reading' => $reading['present_reading'] ?? 0,
                    'present_type' => $reading['present_type'] ?? 'estimate',
                    'multiplier' => $reading['multiplier'] ?? 1,
                    'kwh' => $reading['kwh'] ?? 0,
                    'is_reversal' => (bool) ($reading['is_reversal'] ?? false),
                ]);
            }

            $checks = $this->validator->validate($data, $this->context($bill));
            $bill->checks = $checks;

            // Only an untouched parser result that passes everything confirms itself.
            if ($source === 'parser' && $this->validator->passes($checks)) {
                $bill->status = ElectricityBill::STATUS_CONFIRMED;
                $bill->confirmed_at = now();
                $bill->confirmed_by = null;
            } else {
                $bill->status = ElectricityBill::STATUS_NEEDS_REVIEW;
                $bill->confirmed_at = null;
                $bill->confirmed_by = null;
            }
            $bill->save();

            return $bill->fresh(['charges', 'readings.meter']);
        });
    }

    /** Re-run the checks on what is stored (continuity depends on the other bills on file). */
    public function revalidate(ElectricityBill $bill): ElectricityBill
    {
        $bill->checks = $this->validator->validate($this->toArray($bill), $this->context($bill));
        $bill->save();

        return $bill;
    }

    /** The neutral bill array for a stored bill. */
    public function toArray(ElectricityBill $bill): array
    {
        $bill->loadMissing(['charges', 'readings.meter', 'supplyPoint']);
        $date = fn ($d) => $d?->toDateString();

        return [
            'provider' => $bill->provider,
            'mprn' => $bill->supplyPoint?->mprn,
            'account_number' => $bill->account_number,
            'bill_number' => $bill->bill_number,
            'document_type' => $bill->document_type,
            'issue_date' => $date($bill->issue_date),
            'due_date' => $date($bill->due_date),
            'period_start' => $date($bill->period_start),
            'period_end' => $date($bill->period_end),
            'tariff' => $bill->tariff,
            'mic_kva' => $bill->supplyPoint?->mic_kva,
            'dg_group' => $bill->supplyPoint?->dg_group,
            'net_total' => $bill->net_total,
            'vat_total' => $bill->vat_total,
            'export_credit_total' => $bill->export_credit_total,
            'gross_total' => $bill->gross_total,
            'charges' => $bill->charges->map(fn ($c) => [
                'category' => $c->category,
                'band' => $c->band,
                'description' => $c->description,
                'quantity' => $c->quantity,
                'unit' => $c->unit,
                'rate' => $c->rate,
                'period_start' => $date($c->period_start),
                'period_end' => $date($c->period_end),
                'net_amount' => $c->net_amount,
                'vat_rate' => $c->vat_rate,
            ])->all(),
            'readings' => $bill->readings->map(fn ($r) => [
                'meter_serial' => $r->meter->serial,
                'band' => $r->band,
                'period_start' => $date($r->period_start),
                'period_end' => $date($r->period_end),
                'previous_reading' => $r->previous_reading,
                'previous_type' => $r->previous_type,
                'present_reading' => $r->present_reading,
                'present_type' => $r->present_type,
                'multiplier' => $r->multiplier,
                'kwh' => $r->kwh,
                'is_reversal' => $r->is_reversal,
            ])->all(),
            'warnings' => $bill->warnings ?? [],
        ];
    }

    /** What the continuity checks compare against: the bills issued before this one. */
    private function context(ElectricityBill $bill): array
    {
        $context = ['invoice_total' => $bill->invoice ? (float) $bill->invoice->total_amount : null];

        if (! $bill->electricity_supply_point_id || ! $bill->issue_date) {
            return $context;
        }

        $earlier = ElectricityBill::where('electricity_supply_point_id', $bill->electricity_supply_point_id)
            ->where('id', '!=', $bill->id)
            ->where('document_type', 'invoice')
            ->where('issue_date', '<', $bill->issue_date->toDateString())
            ->with('readings.meter')
            ->orderByDesc('period_end')->orderByDesc('issue_date')
            ->limit(6)
            ->get();

        $previous = $earlier->first(fn ($b) => $b->period_end
            && (! $bill->period_end || $b->period_end->lt($bill->period_end)));
        $context['previous_period_end'] = $previous?->period_end?->toDateString();

        $last = [];
        if ($previous) {
            foreach ($previous->readings->where('is_reversal', false)->sortBy('period_end') as $reading) {
                $last[$reading->meter->serial] = $reading->present_reading;
            }
        }
        $context['last_readings'] = $last;

        return $context;
    }

    private function findExisting(array $data): ?ElectricityBill
    {
        if (empty($data['bill_number'])) {
            return null;
        }

        return ElectricityBill::where('provider', $data['provider'] ?? 'Unknown')
            ->where('bill_number', $data['bill_number'])
            ->where('document_type', ($data['document_type'] ?? 'invoice') === 'credit_note' ? 'credit_note' : 'invoice')
            ->first();
    }

    private function linkInvoice(ElectricityBill $bill, ?Invoice $invoice): void
    {
        if ($invoice && ! $bill->invoice_id) {
            $bill->invoice_id = $invoice->id;
            $bill->save();
            $this->revalidate($bill->fresh());
        }
    }
}
