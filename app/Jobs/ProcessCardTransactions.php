<?php

namespace App\Jobs;

use App\Models\CardTransaction;
use App\Services\CardReconciliationService;
use App\Services\MyPosXlsParserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessCardTransactions implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    private string $filePath;

    private string $batchId;

    private string $originalFilename;

    public function __construct(string $filePath, string $batchId, string $originalFilename)
    {
        $this->filePath = $filePath;
        $this->batchId = $batchId;
        $this->originalFilename = $originalFilename;
    }

    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function handle(MyPosXlsParserService $parser, CardReconciliationService $reconciler): void
    {
        $cacheKey = "card_transactions_processing_{$this->batchId}";

        try {
            $this->updateStatus($cacheKey, 'parsing', 'Parsing XLS file...');

            $fullPath = Storage::path($this->filePath);

            // Validate file
            $errors = $parser->validateFile($fullPath);
            if (! empty($errors)) {
                $this->updateStatus($cacheKey, 'failed', 'Validation failed: '.implode(', ', $errors));
                Log::error('Card transaction file validation failed', ['batch_id' => $this->batchId, 'errors' => $errors]);

                return;
            }

            // Parse transactions
            $transactions = $parser->parse($fullPath);

            if ($transactions->isEmpty()) {
                $this->updateStatus($cacheKey, 'failed', 'No transactions found in file');
                Log::warning('No transactions found in card transaction file', ['batch_id' => $this->batchId]);

                return;
            }

            $this->updateStatus($cacheKey, 'importing', "Importing {$transactions->count()} transactions...");

            // Import transactions
            $imported = 0;
            $skipped = 0;

            foreach ($transactions as $txData) {
                // Check for duplicate transaction reference
                $exists = CardTransaction::where('transaction_reference', $txData['transaction_reference'])->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                CardTransaction::create(array_merge($txData, [
                    'upload_batch_id' => $this->batchId,
                    'source_filename' => $this->originalFilename,
                    'reconciliation_status' => 'pending',
                ]));

                $imported++;
            }

            $this->updateStatus($cacheKey, 'reconciling', "Reconciling {$imported} transactions...");

            // Run reconciliation
            $stats = $reconciler->reconcileBatch($this->batchId);

            $this->updateStatus($cacheKey, 'completed', 'Processing complete', [
                'imported' => $imported,
                'skipped' => $skipped,
                'stats' => $stats,
            ]);

            Log::info('Card transactions processed successfully', [
                'batch_id' => $this->batchId,
                'imported' => $imported,
                'skipped' => $skipped,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            $this->updateStatus($cacheKey, 'failed', 'Processing failed: '.$e->getMessage());
            Log::error('Card transaction processing failed', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            // Cleanup temp file
            if (Storage::exists($this->filePath)) {
                Storage::delete($this->filePath);
            }
        }
    }

    private function updateStatus(string $cacheKey, string $status, string $message, array $data = []): void
    {
        Cache::put($cacheKey, array_merge([
            'status' => $status,
            'message' => $message,
            'batch_id' => $this->batchId,
            'updated_at' => now()->toIso8601String(),
        ], $data), 3600); // Cache for 1 hour
    }
}
