<?php

namespace App\Jobs;

use App\Models\InvoiceUploadFile;
use App\Services\InvoiceGeminiParsingService;
use App\Services\InvoiceParsingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ParseInvoiceCameraImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 180;

    protected $file;

    protected string $featureKey;

    public function __construct(InvoiceUploadFile $file, string $featureKey = 'invoice_parsing')
    {
        $this->file = $file;
        $this->featureKey = $featureKey;

        $queueName = config('invoices.parsing.queue_name');
        if ($queueName) {
            $this->onQueue($queueName);
        }
    }

    public function handle(InvoiceGeminiParsingService $geminiParser, InvoiceParsingService $parser): void
    {
        try {
            Log::info('Starting Gemini invoice parsing job', [
                'file_id' => $this->file->id,
                'filename' => $this->file->original_filename,
                'batch_id' => $this->file->bulk_upload_id,
                'feature_key' => $this->featureKey,
            ]);

            // Bail if the user stopped this file before the job started
            $this->file->refresh();
            if (! in_array($this->file->status, ['uploaded', 'failed'])) {
                Log::info('Gemini invoice parsing job skipped; file no longer queued', [
                    'file_id' => $this->file->id,
                    'status' => $this->file->status,
                ]);

                return;
            }

            $this->file->markAsParsing();

            $result = $geminiParser->parseImage($this->file, $this->featureKey);

            // Discard the result if the user stopped this file mid-parse
            $this->file->refresh();
            if ($this->file->status !== 'parsing') {
                Log::info('Gemini invoice parse result discarded; file no longer parsing', [
                    'file_id' => $this->file->id,
                    'status' => $this->file->status,
                ]);

                return;
            }

            $parser->processParserOutput($this->file, $result);

            $this->updateBatchStatistics();

            Log::info('Gemini invoice parsing job completed', [
                'file_id' => $this->file->id,
                'success' => $result['success'] ?? false,
            ]);

        } catch (\Exception $e) {
            Log::error('Gemini invoice parsing job failed', [
                'file_id' => $this->file->id,
                'error' => $e->getMessage(),
            ]);

            $this->file->markAsFailed($e->getMessage());
            $this->updateBatchStatistics();

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Gemini invoice parsing job permanently failed', [
            'file_id' => $this->file->id,
            'error' => $exception->getMessage(),
        ]);

        $this->file->markAsFailed('Job failed after '.$this->tries.' attempts: '.$exception->getMessage());
        $this->updateBatchStatistics();
    }

    protected function updateBatchStatistics(): void
    {
        try {
            $this->file->refresh();

            $batch = $this->file->bulkUpload;

            if ($batch) {
                $batch->updateStatistics();

                $pendingCount = $batch->files()
                    ->whereIn('status', ['pending', 'uploading', 'uploaded', 'parsing'])
                    ->count();

                if ($pendingCount === 0) {
                    Log::info('All files in batch processed', [
                        'batch_id' => $batch->batch_id,
                        'total_files' => $batch->total_files,
                        'successful' => $batch->successful_files,
                        'failed' => $batch->failed_files,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to update batch statistics', [
                'file_id' => $this->file->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function backoff(): array
    {
        return [15, 45, 120];
    }

    public function tags(): array
    {
        return [
            'invoice-parsing',
            'source:camera',
            'file:'.$this->file->id,
            'batch:'.$this->file->bulk_upload_id,
        ];
    }
}
