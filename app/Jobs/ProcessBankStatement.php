<?php

namespace App\Jobs;

use App\Services\BankStatementService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessBankStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;

    protected $originalFilename;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, string $originalFilename)
    {
        $this->filePath = $filePath;
        $this->originalFilename = $originalFilename;
    }

    /**
     * Execute the job.
     */
    public function handle(BankStatementService $bankStatementService): void
    {
        $cacheKey = "bank_statement_processing_{$this->originalFilename}_".str_replace(['/', ' ', '.'], '_', $this->originalFilename);

        try {
            // Set processing status
            Cache::put($cacheKey, ['status' => 'processing', 'message' => 'Processing file...'], 300); // 5 minutes

            $result = $bankStatementService->processCsv(Storage::path($this->filePath), $this->originalFilename);

            // Set success status
            Cache::put($cacheKey, [
                'status' => 'completed',
                'message' => "Successfully imported {$result['imported']} transactions".($result['skipped'] > 0 ? ", skipped {$result['skipped']} duplicates" : ''),
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
            ], 3600); // 1 hour

            Log::info("Successfully processed bank statement: {$this->originalFilename}", [
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
            ]);

        } catch (Exception $e) {
            // Set error status
            Cache::put($cacheKey, [
                'status' => 'failed',
                'message' => 'Processing failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 3600); // 1 hour

            Log::error("Failed to process bank statement {$this->originalFilename}: ".$e->getMessage());

            // Re-throw to mark job as failed
            throw $e;
        } finally {
            // Clean up the uploaded file
            Storage::delete($this->filePath);
        }
    }
}
