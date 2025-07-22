<?php

namespace App\Jobs;

use App\Models\DeliveryItem;
use App\Services\UdeaScrapingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetrieveBarcodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public DeliveryItem $deliveryItem
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(UdeaScrapingService $udeaService): void
    {
        try {
            Log::info("Retrieving barcode for supplier code: {$this->deliveryItem->supplier_code}");

            $productData = $udeaService->getProductData($this->deliveryItem->supplier_code);

            if ($productData && isset($productData['barcode'])) {
                $this->deliveryItem->update(['barcode' => $productData['barcode']]);

                Log::info("Successfully retrieved barcode: {$productData['barcode']} for supplier code: {$this->deliveryItem->supplier_code}");
            } else {
                Log::warning("No barcode data returned for supplier code: {$this->deliveryItem->supplier_code}");

                // Mark as failed barcode retrieval after all attempts
                if ($this->attempts() >= $this->tries) {
                    $this->deliveryItem->update([
                        'barcode_retrieval_failed' => true,
                        'barcode_retrieval_error' => 'No barcode data found after '.$this->tries.' attempts',
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve barcode for supplier code: {$this->deliveryItem->supplier_code}", [
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // If this is the final attempt, mark as failed
            if ($this->attempts() >= $this->tries) {
                $this->deliveryItem->update([
                    'barcode_retrieval_failed' => true,
                    'barcode_retrieval_error' => $e->getMessage(),
                ]);
            }

            // Re-throw the exception to trigger retry
            throw $e;
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300]; // Wait 30s, 2min, 5min between retries
    }
}
