<?php

namespace App\Jobs;

use App\Models\InvoiceUploadFile;
use App\Services\InvoiceParsingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ParseInvoiceFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * The invoice file to parse
     *
     * @var InvoiceUploadFile
     */
    protected $file;

    /**
     * Create a new job instance.
     */
    public function __construct(InvoiceUploadFile $file)
    {
        $this->file = $file;

        // Set the queue if configured
        $queueName = config('invoices.parsing.queue_name');
        if ($queueName) {
            $this->onQueue($queueName);
        }
    }

    /**
     * Execute the job.
     */
    public function handle(InvoiceParsingService $parser): void
    {
        try {
            Log::info('Starting invoice parsing job', [
                'file_id' => $this->file->id,
                'filename' => $this->file->original_filename,
                'batch_id' => $this->file->bulk_upload_id,
            ]);

            // Mark file as being parsed
            $this->file->markAsParsing();

            // Parse the file
            $result = $parser->parseFile($this->file);

            // Process the output
            $parser->processParserOutput($this->file, $result);

            // Update batch statistics
            $this->updateBatchStatistics();

            Log::info('Invoice parsing job completed', [
                'file_id' => $this->file->id,
                'success' => $result['success'] ?? false,
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice parsing job failed', [
                'file_id' => $this->file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark file as failed
            $this->file->markAsFailed($e->getMessage());

            // Update batch statistics
            $this->updateBatchStatistics();

            // Re-throw to trigger retry logic
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Invoice parsing job permanently failed', [
            'file_id' => $this->file->id,
            'error' => $exception->getMessage(),
        ]);

        // Mark file as failed after all retries exhausted
        $this->file->markAsFailed('Job failed after '.$this->tries.' attempts: '.$exception->getMessage());

        // Update batch statistics
        $this->updateBatchStatistics();
    }

    /**
     * Update the batch statistics
     */
    protected function updateBatchStatistics(): void
    {
        try {
            // Refresh the file to get latest status
            $this->file->refresh();

            // Get the batch
            $batch = $this->file->bulkUpload;

            if ($batch) {
                $batch->updateStatistics();

                // Check if all files in batch are processed
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

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        // Exponential backoff: 10 seconds, 30 seconds, 90 seconds
        return [10, 30, 90];
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'invoice-parsing',
            'file:'.$this->file->id,
            'batch:'.$this->file->bulk_upload_id,
        ];
    }
}
