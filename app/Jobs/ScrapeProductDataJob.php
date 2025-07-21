<?php

namespace App\Jobs;

use App\Services\UdeaScrapingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeProductDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private string $productCode,
        private ?string $callbackUrl = null,
        private ?array $callbackData = null
    ) {}

    public function handle(UdeaScrapingService $scrapingService): void
    {
        Log::info('Starting product data scraping job', [
            'product_code' => $this->productCode,
            'attempt' => $this->attempts(),
        ]);

        try {
            $data = $scrapingService->getProductData($this->productCode);

            if ($data) {
                Log::info('Product data scraping completed successfully', [
                    'product_code' => $this->productCode,
                    'data_fields' => array_keys($data),
                ]);

                if ($this->callbackUrl) {
                    $this->sendCallback($data, true);
                }
            } else {
                Log::warning('Product data scraping returned no data', [
                    'product_code' => $this->productCode,
                ]);

                if ($this->callbackUrl) {
                    $this->sendCallback(null, false, 'No product data found');
                }
            }
        } catch (\Exception $e) {
            Log::error('Product data scraping job failed', [
                'product_code' => $this->productCode,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->callbackUrl && $this->attempts() >= $this->tries) {
                $this->sendCallback(null, false, $e->getMessage());
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Product data scraping job failed permanently', [
            'product_code' => $this->productCode,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        if ($this->callbackUrl) {
            $this->sendCallback(null, false, $exception->getMessage());
        }
    }

    private function sendCallback(?array $data, bool $success, ?string $error = null): void
    {
        try {
            $payload = [
                'product_code' => $this->productCode,
                'success' => $success,
                'data' => $data,
                'error' => $error,
                'callback_data' => $this->callbackData,
                'completed_at' => now()->toISOString(),
            ];

            $client = new \GuzzleHttp\Client(['timeout' => 10]);

            $response = $client->post($this->callbackUrl, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'UdeaScrapingService/1.0',
                ],
            ]);

            Log::info('Callback sent successfully', [
                'product_code' => $this->productCode,
                'callback_url' => $this->callbackUrl,
                'status_code' => $response->getStatusCode(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send callback', [
                'product_code' => $this->productCode,
                'callback_url' => $this->callbackUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }

    public function tags(): array
    {
        return ['udea-scraping', "product:{$this->productCode}"];
    }
}
