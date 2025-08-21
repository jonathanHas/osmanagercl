<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceUploadFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FixMissingInvoiceAttachments extends Command
{
    protected $signature = 'invoices:fix-attachments {--invoice-ids=* : Specific invoice IDs to fix}';

    protected $description = 'Fix missing invoice attachments for bulk uploaded invoices';

    public function handle()
    {
        $invoiceIds = $this->option('invoice-ids');

        if (empty($invoiceIds)) {
            // Find invoices without attachments that have upload files
            $query = Invoice::whereDoesntHave('attachments')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('invoice_upload_files')
                        ->whereColumn('invoices.id', 'invoice_upload_files.invoice_id')
                        ->whereNotNull('invoice_upload_files.temp_path');
                });
        } else {
            $query = Invoice::whereIn('id', $invoiceIds);
        }

        $invoices = $query->with(['uploadFiles' => function ($query) {
            $query->whereNotNull('temp_path');
        }])->get();

        if ($invoices->isEmpty()) {
            $this->info('No invoices found that need attachment fixes.');

            return;
        }

        $this->info("Found {$invoices->count()} invoices that need attachment fixes:");

        $fixed = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            foreach ($invoice->uploadFiles as $uploadFile) {
                $this->line("Processing Invoice #{$invoice->invoice_number} (ID: {$invoice->id})...");

                try {
                    if ($this->createAttachmentForInvoice($invoice, $uploadFile)) {
                        $this->info('  ✅ Attachment created successfully');
                        $fixed++;
                    } else {
                        $this->error('  ❌ Failed to create attachment');
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $this->error('  ❌ Error: '.$e->getMessage());
                    $failed++;
                }
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->info("  Fixed: {$fixed}");
        if ($failed > 0) {
            $this->error("  Failed: {$failed}");
        }
    }

    private function createAttachmentForInvoice(Invoice $invoice, InvoiceUploadFile $file): bool
    {
        // Check if temp file exists
        $tempFilePath = Storage::disk('local')->path($file->temp_path);
        if (! file_exists($tempFilePath)) {
            $this->error("  Temp file not found: {$file->temp_path}");

            return false;
        }

        // Create permanent path
        $year = $invoice->invoice_date ? $invoice->invoice_date->format('Y') : now()->format('Y');
        $month = $invoice->invoice_date ? $invoice->invoice_date->format('m') : now()->format('m');
        $filename = \Str::uuid().'.'.pathinfo($file->original_filename, PATHINFO_EXTENSION);
        $permanentPath = "invoices/{$year}/{$month}/{$invoice->id}/{$filename}";

        try {
            // Copy file to permanent location
            $tempFileContent = file_get_contents($tempFilePath);
            Storage::disk('local')->put($permanentPath, $tempFileContent);

            // Create attachment record
            $attachment = $invoice->attachments()->create([
                'original_filename' => $file->original_filename,
                'stored_filename' => $filename,
                'file_path' => $permanentPath,
                'file_size' => $file->file_size,
                'mime_type' => $file->mime_type,
                'file_hash' => $file->file_hash,
                'attachment_type' => 'invoice_scan',
                'is_primary' => true,
                'uploaded_by' => $invoice->created_by,
                'uploaded_at' => $file->uploaded_at ?? now(),
            ]);

            Log::info('Missing invoice attachment created', [
                'invoice_id' => $invoice->id,
                'attachment_id' => $attachment->id,
                'file_path' => $permanentPath,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create missing invoice attachment', [
                'invoice_id' => $invoice->id,
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
