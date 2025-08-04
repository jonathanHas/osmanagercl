<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IndependentScrapingService
{
    private Client $client;

    private array $config;

    private ?string $sessionCookie = null;

    public function __construct()
    {
        $this->config = [
            'base_uri' => config('services.independent.base_uri', 'https://www.independenthealthfoods.ie'),
            'username' => config('services.independent.username'),
            'password' => config('services.independent.password'),
            'timeout' => config('services.independent.timeout', 30),
            'rate_limit_delay' => config('services.independent.rate_limit_delay', 2),
            'cache_ttl' => config('services.independent.cache_ttl', 3600),
        ];

        $this->client = new Client([
            'base_uri' => $this->config['base_uri'],
            'timeout' => $this->config['timeout'],
            'cookies' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ],
        ]);
    }

    public function getProductData(string $productCode): ?array
    {
        $cacheKey = "independent_product_{$productCode}";

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () use ($productCode) {
            return $this->scrapeProductData($productCode);
        });
    }

    private function scrapeProductData(string $productCode): ?array
    {
        try {
            // Independent Health Foods may not require authentication for product search
            // But we'll implement it in case it's needed for detailed product info
            if ($this->config['username'] && $this->config['password']) {
                if (! $this->ensureAuthenticated()) {
                    Log::warning('Independent scraping failed: Authentication failed');

                    return null;
                }
            }

            sleep($this->config['rate_limit_delay']);

            // Use the search URL from configuration
            $searchUrl = "/search?q={$productCode}";
            $response = $this->client->get($searchUrl, [
                'headers' => [
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::warning('Independent scraping failed: Invalid response status', [
                    'status' => $response->getStatusCode(),
                    'product_code' => $productCode,
                ]);

                return null;
            }

            $html = (string) $response->getBody();

            return $this->parseProductData($html, $productCode);

        } catch (GuzzleException $e) {
            Log::error('Independent scraping failed: HTTP error', [
                'error' => $e->getMessage(),
                'product_code' => $productCode,
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Independent scraping failed: Unexpected error', [
                'error' => $e->getMessage(),
                'product_code' => $productCode,
            ]);

            return null;
        }
    }

    private function ensureAuthenticated(): bool
    {
        if ($this->sessionCookie) {
            return true;
        }

        try {
            // Get login page - adjust URL based on Independent's actual login structure
            $loginPage = $this->client->get('/account/login');
            $loginHtml = (string) $loginPage->getBody();

            // Extract CSRF token if present
            $csrfToken = $this->extractCsrfToken($loginHtml);

            // Prepare form data - adjust field names based on Independent's login form
            $formData = [
                'email' => $this->config['username'],
                'password' => $this->config['password'],
            ];

            // Add CSRF token if found
            if ($csrfToken) {
                $formData['_token'] = $csrfToken;
            }

            $loginResponse = $this->client->post('/account/login', [
                'form_params' => $formData,
                'allow_redirects' => false,
            ]);

            // Check for successful login
            $statusCode = $loginResponse->getStatusCode();
            if ($statusCode === 302 || $statusCode === 200) {
                $this->sessionCookie = 'authenticated';
                Log::info('Independent authentication successful', ['status' => $statusCode]);

                return true;
            }

            Log::warning('Independent login failed: Invalid credentials or form structure', [
                'status_code' => $statusCode,
                'response_preview' => substr((string) $loginResponse->getBody(), 0, 500),
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('Independent authentication failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function extractCsrfToken(string $html): ?string
    {
        if (preg_match('/name=["\']_token["\'][^>]*value=["\']([^"\']+)["\']/', $html, $matches)) {
            return $matches[1];
        }

        if (preg_match('/name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']/', $html, $matches)) {
            return $matches[1];
        }

        if (preg_match('/<meta name=["\']csrf-token["\'] content=["\']([^"\']+)["\']/', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function parseProductData(string $html, string $productCode): ?array
    {
        $data = [
            'product_code' => $productCode,
            'scraped_at' => now()->toISOString(),
            'description' => null,
            'brand' => null,
            'size' => null,
            'unit_price' => null,
            'case_price' => null,
            'rsp' => null,
            'barcode' => null,
            'image_url' => null,
            'attributes' => [],
        ];

        // Extract product name/description
        // Look for product titles in search results
        if (preg_match('/<h[1-6][^>]*class="[^"]*product-title[^"]*"[^>]*>([^<]+)</', $html, $matches)) {
            $data['description'] = trim($matches[1]);
        }
        // Alternative pattern for product names
        elseif (preg_match('/<a[^>]*class="[^"]*product-link[^"]*"[^>]*title="([^"]+)"/', $html, $matches)) {
            $data['description'] = trim($matches[1]);
        }

        // Extract price information
        // Look for price patterns in euros (Independent is Irish)
        if (preg_match('/€\s*([0-9]+[.,]\d{2})/', $html, $matches)) {
            $data['case_price'] = str_replace(',', '.', $matches[1]);
        }

        // Extract product attributes similar to CSV format
        if ($data['description']) {
            // Extract organic indicator
            if (preg_match('/\b(organic|org)\b/i', $data['description'])) {
                $data['attributes']['organic'] = true;
            }

            // Extract size information
            if (preg_match('/(\d+(?:\.\d+)?)\s*(ml|g|kg|l|litre)/i', $data['description'], $matches)) {
                $data['size'] = $matches[1].$matches[2];
            }

            // Extract pack size
            if (preg_match('/(\d+)\s*x\s*(\d+(?:\.\d+)?)\s*(ml|g|kg|l)/i', $data['description'], $matches)) {
                $data['attributes']['pack_quantity'] = (int) $matches[1];
                $data['attributes']['unit_size'] = $matches[2].$matches[3];
            }
        }

        // Extract barcode if available
        $data['barcode'] = $this->extractBarcode($html);

        // Extract product image URL
        $data['image_url'] = $this->extractImageUrl($html);

        // Check if we found useful data
        $hasData = ! is_null($data['description']) ||
                   ! is_null($data['case_price']) ||
                   ! is_null($data['barcode']);

        if (! $hasData) {
            Log::warning('Independent scraping: No product data found', [
                'product_code' => $productCode,
                'html_length' => strlen($html),
            ]);

            return null;
        }

        Log::info('Independent scraping successful', [
            'product_code' => $productCode,
            'data_found' => array_filter($data, fn ($v) => ! is_null($v)),
        ]);

        return $data;
    }

    private function extractBarcode(string $html): ?string
    {
        // Look for barcode/EAN patterns in the HTML
        if (preg_match('/(?:barcode|ean|gtin)[\s:]*(\d{8,13})/i', $html, $matches)) {
            return $matches[1];
        }

        // Look for 13-digit numbers that could be EANs
        if (preg_match('/\b(\d{13})\b/', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractImageUrl(string $html): ?string
    {
        // Look for product images
        if (preg_match('/<img[^>]*class="[^"]*product-image[^"]*"[^>]*src="([^"]+)"/', $html, $matches)) {
            $imageUrl = $matches[1];
            // Convert relative URLs to absolute
            if (str_starts_with($imageUrl, '/')) {
                $imageUrl = $this->config['base_uri'].$imageUrl;
            }

            return $imageUrl;
        }

        return null;
    }

    public function testConnection(): array
    {
        try {
            $response = $this->client->get('/');
            $authResult = ['success' => true];

            // Test authentication if credentials are configured
            if ($this->config['username'] && $this->config['password']) {
                $authResult = $this->testAuthentication();
            }

            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'response_time' => $this->measureResponseTime(),
                'authenticated' => $authResult['success'],
                'auth_debug' => $authResult,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'authenticated' => false,
                'auth_debug' => ['error' => $e->getMessage()],
            ];
        }
    }

    private function testAuthentication(): array
    {
        try {
            $loginPage = $this->client->get('/account/login');
            $loginHtml = (string) $loginPage->getBody();

            $debug = [
                'step_1_login_page' => [
                    'url' => '/account/login',
                    'status' => $loginPage->getStatusCode(),
                    'has_form' => str_contains($loginHtml, '<form'),
                    'has_email_field' => str_contains($loginHtml, 'name="email"'),
                    'html_length' => strlen($loginHtml),
                ],
            ];

            $csrfToken = $this->extractCsrfToken($loginHtml);
            $debug['step_2_csrf'] = [
                'token_found' => ! is_null($csrfToken),
                'token_preview' => $csrfToken ? substr($csrfToken, 0, 10).'...' : null,
            ];

            return [
                'success' => true, // For now, just test page access
                'debug' => $debug,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'debug' => [],
            ];
        }
    }

    private function measureResponseTime(): float
    {
        $start = microtime(true);
        try {
            $this->client->get('/');
        } catch (Exception) {
            // Ignore errors for timing test
        }

        return round((microtime(true) - $start) * 1000, 2);
    }

    public function clearCache(?string $productCode = null): void
    {
        if ($productCode) {
            Cache::forget("independent_product_{$productCode}");
        } else {
            Cache::flush();
        }
    }

    public function getProductDataForDeliveryItem(\App\Models\DeliveryItem $item): ?array
    {
        if (! $item->supplier_code) {
            Log::info('IndependentScrapingService: No supplier code for delivery item', [
                'delivery_item_id' => $item->id,
            ]);

            return null;
        }

        Log::info('IndependentScrapingService: Getting product data for delivery item', [
            'delivery_item_id' => $item->id,
            'supplier_code' => $item->supplier_code,
        ]);

        return $this->getProductData($item->supplier_code);
    }

    public function queueProductScraping(
        string $productCode,
        ?string $callbackUrl = null,
        ?array $callbackData = null
    ): void {
        \App\Jobs\ScrapeIndependentProductDataJob::dispatch($productCode, $callbackUrl, $callbackData);

        Log::info('Independent product scraping job queued', [
            'product_code' => $productCode,
            'has_callback' => ! is_null($callbackUrl),
        ]);
    }
}
