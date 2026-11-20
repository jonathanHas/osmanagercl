<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Electricity\ElectricityBillImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * After an accounts invoice from a utility supplier is created, read its PDF
 * into the Electricity section. Never allowed to disturb invoice creation.
 */
class ImportElectricityBill implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public Invoice $invoice) {}

    public function handle(ElectricityBillImportService $importer): void
    {
        $attachment = $this->invoice->attachments()->get()
            ->filter(fn ($a) => $a->isPdf() && $a->exists())
            ->sortByDesc('is_primary')
            ->first();

        if (! $attachment) {
            return;
        }

        try {
            $outcome = $importer->importFromPdf($attachment->full_storage_path, $this->invoice, $attachment->original_filename);
            Log::info('Electricity bill import from invoice', [
                'invoice_id' => $this->invoice->id,
                'result' => $outcome['result'],
                'bill_id' => $outcome['bill']?->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Electricity bill import from invoice failed', [
                'invoice_id' => $this->invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
