<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UdeaScrapingService
{
    private Client $client;

    private array $config;

    private ?string $sessionCookie = null;

    public function __construct()
    {
        $this->config = [
            'base_uri' => config('services.udea.base_uri', 'https://www.udea.nl'),
            'username' => config('services.udea.username'),
            'password' => config('services.udea.password'),
            'timeout' => config('services.udea.timeout', 30),
            'rate_limit_delay' => config('services.udea.rate_limit_delay', 2),
            'cache_ttl' => config('services.udea.cache_ttl', 3600),
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
        $cacheKey = "udea_product_{$productCode}";

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () use ($productCode) {
            return $this->scrapeProductData($productCode);
        });
    }

    private function scrapeProductData(string $productCode): ?array
    {
        try {
            if (! $this->ensureAuthenticated()) {
                Log::warning('Udea scraping failed: Authentication failed');

                return null;
            }

            sleep($this->config['rate_limit_delay']);

            // Use the correct search URL format with English language preference
            $searchUrl = "/search/?qry={$productCode}";
            $response = $this->client->get($searchUrl, [
                'headers' => [
                    'Accept-Language' => 'en-US,en;q=0.9,nl;q=0.1',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::warning('Udea scraping failed: Invalid response status', [
                    'status' => $response->getStatusCode(),
                    'product_code' => $productCode,
                ]);

                return null;
            }

            $html = (string) $response->getBody();

            // Detect language version for debugging
            $isDutch = strpos($html, '/producten/product/') !== false;
            $isEnglish = strpos($html, '/products/product/') !== false;

            Log::info('Udea language detection', [
                'product_code' => $productCode,
                'dutch_detected' => $isDutch,
                'english_detected' => $isEnglish,
                'search_url' => $searchUrl,
            ]);

            return $this->parseProductData($html, $productCode);

        } catch (GuzzleException $e) {
            Log::error('Udea scraping failed: HTTP error', [
                'error' => $e->getMessage(),
                'product_code' => $productCode,
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Udea scraping failed: Unexpected error', [
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
            // Use the correct login URL
            $loginPage = $this->client->get('/users');
            $loginHtml = (string) $loginPage->getBody();

            // Udea doesn't seem to use CSRF tokens, but let's try to extract one anyway
            $csrfToken = $this->extractCsrfToken($loginHtml);

            // Prepare form data with correct field names
            $formData = [
                'email' => $this->config['username'], // Udea uses 'email' field
                'password' => $this->config['password'],
                'remember-me' => '1', // Stay logged in
            ];

            // Add CSRF token if found
            if ($csrfToken) {
                $formData['_token'] = $csrfToken;
            }

            $loginResponse = $this->client->post('/users/login', [
                'form_params' => $formData,
                'allow_redirects' => false,
            ]);

            // Check for successful login (could be 302 redirect or 200 with success indication)
            $statusCode = $loginResponse->getStatusCode();
            if ($statusCode === 302 || $statusCode === 200) {
                $this->sessionCookie = 'authenticated';
                Log::info('Udea authentication successful', ['status' => $statusCode]);

                return true;
            }

            Log::warning('Udea login failed: Invalid credentials or form structure', [
                'status_code' => $statusCode,
                'response_preview' => substr((string) $loginResponse->getBody(), 0, 500),
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('Udea authentication failed', ['error' => $e->getMessage()]);

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
            'case_price' => null,
            'unit_price' => null,
            'units_per_case' => null,
            'description' => null,
            'brand' => null,
            'size' => null,
            'original_price' => null,
            'discount_price' => null,
            'is_discounted' => false,
            'customer_price' => null,
            'barcode' => null,
        ];

        // NEW: Try to extract full product name using the .volume div structure first
        $fullProductName = $this->extractFullProductName($html);
        if ($fullProductName) {
            $data['description'] = $fullProductName;
            Log::info('Udea product name extraction - NEW method successful', [
                'product_code' => $productCode,
                'full_product_name' => $fullProductName,
                'method' => 'volume_div_extraction',
            ]);
        } else {
            // FALLBACK: Use legacy extraction logic if new method fails
            Log::info('Udea product name extraction - falling back to legacy method', [
                'product_code' => $productCode,
            ]);

            // Pattern 1: Look for product name in h3 with prod-title class and span with title attribute
            if (preg_match('/<h3[^>]*class="[^"]*prod-title[^"]*"[^>]*>.*?<span[^>]*title="([^"]*)"[^>]*>([^<]+)</', $html, $matches)) {
                $data['description'] = trim($matches[1]); // Use title attribute first as it's more complete
                if (empty($data['description'])) {
                    $data['description'] = trim($matches[2]); // Fallback to span content
                }
            }
            // Pattern 2: Look for product name directly in prod-title
            elseif (preg_match('/<h3[^>]*class="[^"]*prod-title[^"]*"[^>]*>([^<]+)</', $html, $matches)) {
                $data['description'] = trim($matches[1]);
            }
            // Pattern 3: Look for "Agar-agar" or similar product names (hyphenated words)
            elseif (preg_match('/>\s*([A-Za-z]+-[A-Za-z]+)\s*</', $html, $matches)) {
                $data['description'] = trim($matches[1]);
            }
            // Pattern 4: Look for product names in title attributes more broadly
            elseif (preg_match('/title="([^"]+)"[^>]*>([^<]+)</', $html, $matches)) {
                // If title is more descriptive than content, use title
                $title = trim($matches[1]);
                $content = trim($matches[2]);
                $data['description'] = strlen($title) > strlen($content) ? $title : $content;
            }
            // Pattern 5: Search for "Agar" anywhere in the HTML and extract surrounding context
            elseif (preg_match('/([A-Za-z-]*[Aa]gar[A-Za-z-]*)/', $html, $matches)) {
                $data['description'] = trim($matches[1]);
            }

            // If no description found yet but we know agar is in the HTML, try more patterns
            if (empty($data['description']) && str_contains(strtolower($html), 'agar')) {
                // Look for agar-agar specifically anywhere in the HTML
                if (preg_match('/(agar-agar)/i', $html, $matches)) {
                    $data['description'] = 'Agar-agar';
                }
                // Look for any word containing agar
                elseif (preg_match('/\b(\w*agar\w*)\b/i', $html, $matches)) {
                    $data['description'] = $matches[1];
                }
            }

            // Log debug info for legacy product name extraction
            Log::info('Udea product name extraction debug - legacy method', [
                'product_code' => $productCode,
                'description_found' => $data['description'] ?? 'NONE',
                'html_contains_agar' => str_contains(strtolower($html), 'agar'),
                'html_contains_agar_agar' => str_contains(strtolower($html), 'agar-agar'),
            ]);
        }

        // Extract brand from .volume-sub-title
        if (preg_match('/<div[^>]*class="[^"]*volume-sub-title[^"]*"[^>]*>\s*([^<\s]+)/', $html, $matches)) {
            $data['brand'] = trim($matches[1]);
        }

        // Extract size (like "30 gram")
        if (preg_match('/>\s*(\d+\s*(?:gram|ml|liter|kg|stuks?))\s*</', $html, $matches)) {
            $data['size'] = trim($matches[1]);
        }

        // Extract units per case (like "x 15")
        if (preg_match('/x\s+(\d+)/', $html, $matches)) {
            $data['units_per_case'] = intval($matches[1]);
        }

        // Extract prices - check for discounts first
        $hasDiscount = false;

        // Look for discount structure: text-striked original price and price-discounted
        if (preg_match('/<span class="text-striked">\s*([0-9]+[.,]\d{2})\s*<\/span>.*?<span class="price-discounted">([0-9]+[.,]\d{2})<\/span>/s', $html, $discountMatches)) {
            $data['original_price'] = $discountMatches[1];
            $data['discount_price'] = $discountMatches[2];
            $data['case_price'] = $discountMatches[2]; // Use discount price as current case price
            $data['is_discounted'] = true;
            $hasDiscount = true;

            Log::info('Udea discount detected', [
                'product_code' => $productCode,
                'original_price' => $data['original_price'],
                'discount_price' => $data['discount_price'],
            ]);
        }

        // If no discount found, use regular price extraction
        if (! $hasDiscount) {
            preg_match_all('/(\d+,\d{2})/', $html, $priceMatches);

            // Log all found prices for debugging
            Log::info('Udea price extraction debug', [
                'product_code' => $productCode,
                'all_prices_found' => $priceMatches[1] ?? [],
            ]);

            if (! empty($priceMatches[1])) {
                $prices = array_unique($priceMatches[1]);

                // Filter out obviously wrong prices
                $validPrices = array_filter($prices, function ($price) {
                    $numeric = floatval(str_replace(',', '.', $price));

                    return $numeric > 0.01 && $numeric < 100;
                });

                if (count($validPrices) >= 1) {
                    $sortedPrices = $validPrices;
                    usort($sortedPrices, function ($a, $b) {
                        $numA = floatval(str_replace(',', '.', $a));
                        $numB = floatval(str_replace(',', '.', $b));

                        return $numB <=> $numA; // Sort descending (largest first)
                    });

                    // Use the largest reasonable price as case price
                    // Don't assume smallest price is unit price (could be from navigation/footer)
                    $data['case_price'] = $sortedPrices[0];

                    // Calculate unit price from case price if we can determine units per case
                    if (isset($data['units_per_case']) && $data['units_per_case'] > 0) {
                        $casePrice = floatval(str_replace(',', '.', $data['case_price']));
                        $data['unit_price'] = number_format($casePrice / $data['units_per_case'], 2, ',', '');
                    }
                }

                // Special handling for specific price patterns
                foreach ($prices as $price) {
                    if (preg_match('/^3[0-9],\d{2}$/', $price)) {
                        $data['case_price'] = $price;
                        break;
                    }
                }
            }
        }

        // Check if we found useful data
        $hasData = ! is_null($data['case_price']) ||
                   ! is_null($data['unit_price']) ||
                   ! is_null($data['description']) ||
                   ! is_null($data['units_per_case']);

        if (! $hasData) {
            Log::warning('Udea scraping: No product data found', [
                'product_code' => $productCode,
                'html_length' => strlen($html),
                'prices_found' => $priceMatches[1] ?? [],
            ]);

            return null;
        }

        // Try to extract customer price from product detail page
        $data['customer_price'] = $this->extractCustomerPrice($html);

        // Try to extract barcode from product detail page
        $barcodeData = $this->extractBarcodeFromDetail($html);
        if ($barcodeData) {
            $data['barcode'] = $barcodeData;
        }

        Log::info('Udea scraping successful', [
            'product_code' => $productCode,
            'data_found' => array_filter($data, fn ($v) => ! is_null($v)),
        ]);

        return $data;
    }

    /**
     * Extract barcode from product detail page
     */
    private function extractBarcodeFromDetail(string $searchHtml): ?string
    {
        try {
            // Look for the first product detail link
            if (strpos($searchHtml, 'id="productsLists"') !== false) {
                $productsListPos = strpos($searchHtml, 'id="productsLists"');
                $searchFromPos = substr($searchHtml, $productsListPos);

                // Look for detail link
                if (preg_match('/<a[^>]*href="(https:\/\/www\.udea\.nl\/product(?:en|s)\/product\/[^"]+)"[^>]*class="[^"]*detail-image[^"]*"/', $searchFromPos, $matches)) {
                    $detailUrl = $matches[1];

                    // Fetch the product detail page
                    $response = $this->client->get($detailUrl);
                    $detailHtml = (string) $response->getBody();

                    // Extract EAN/barcode - Try multiple patterns

                    // Pattern 1: HTML table format - <td class="wt-semi">EAN</td><td>8711521021925</td>
                    if (preg_match('/<td[^>]*class="[^"]*wt-semi[^"]*"[^>]*>(?:EAN|Barcode|GTIN)<\/td>\s*<td[^>]*>(\d{8,13})<\/td>/i', $detailHtml, $barcodeMatches)) {
                        $barcode = $barcodeMatches[1];

                        Log::info('Extracted barcode from table format', [
                            'barcode' => $barcode,
                            'detail_url' => $detailUrl,
                            'pattern' => 'html_table',
                        ]);

                        return $barcode;
                    }

                    // Pattern 2: Alternative table format - <td>EAN</td><td>8711521021925</td> (without class)
                    if (preg_match('/<td[^>]*>(?:EAN|Barcode|GTIN)<\/td>\s*<td[^>]*>(\d{8,13})<\/td>/i', $detailHtml, $barcodeMatches)) {
                        $barcode = $barcodeMatches[1];

                        Log::info('Extracted barcode from simple table format', [
                            'barcode' => $barcode,
                            'detail_url' => $detailUrl,
                            'pattern' => 'simple_table',
                        ]);

                        return $barcode;
                    }

                    // Pattern 3: Original format - "EAN: 8718976017046" or similar
                    if (preg_match('/(?:EAN|Barcode):\s*(\d{8,13})/', $detailHtml, $barcodeMatches)) {
                        $barcode = $barcodeMatches[1];

                        Log::info('Extracted barcode from colon format', [
                            'barcode' => $barcode,
                            'detail_url' => $detailUrl,
                            'pattern' => 'colon_separated',
                        ]);

                        return $barcode;
                    }

                    // Alternative pattern for EAN in different format
                    if (preg_match('/\b(\d{13})\b/', $detailHtml, $barcodeMatches)) {
                        // Validate it's likely an EAN-13
                        $potentialEan = $barcodeMatches[1];
                        if (strlen($potentialEan) == 13 && substr($potentialEan, 0, 2) == '87') {
                            Log::info('Extracted EAN-13', [
                                'barcode' => $potentialEan,
                                'detail_url' => $detailUrl,
                            ]);

                            return $potentialEan;
                        }
                    }
                }
            }

            return null;

        } catch (Exception $e) {
            Log::error('Failed to extract barcode', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract customer price from product detail page
     */
    private function extractCustomerPrice(string $searchHtml): ?string
    {
        try {
            // Look for the first product detail link after productsLists div
            // The link appears after the productsLists div in the HTML structure
            if (strpos($searchHtml, 'id="productsLists"') !== false) {
                // Find the position of productsLists
                $productsListPos = strpos($searchHtml, 'id="productsLists"');

                // Search for the detail link starting from productsLists position
                $searchFromPos = substr($searchHtml, $productsListPos);

                // Look for the first product detail link (both Dutch and English versions)
                if (preg_match('/<a[^>]*href="(https:\/\/www\.udea\.nl\/product(?:en|s)\/product\/[^"]+)"[^>]*class="[^"]*detail-image[^"]*"/', $searchFromPos, $matches)) {
                    $detailUrl = $matches[1];

                    Log::info('Found product detail URL', [
                        'detail_url' => $detailUrl,
                    ]);

                    // Fetch the product detail page
                    $response = $this->client->get($detailUrl);
                    $detailHtml = (string) $response->getBody();

                    // Extract customer price from Product margin section
                    // Pattern: "Customer price: 2,89" (English) or "Consumentenprijs: 2,89" (Dutch)
                    if (preg_match('/(?:Customer price|Consumentenprijs):\s*([0-9]+,\d{2})/', $detailHtml, $priceMatches)) {
                        $customerPrice = $priceMatches[1];

                        Log::info('Extracted customer price', [
                            'customer_price' => $customerPrice,
                            'detail_url' => $detailUrl,
                        ]);

                        return $customerPrice;
                    }

                    Log::warning('Customer price not found on detail page', [
                        'detail_url' => $detailUrl,
                    ]);
                } else {
                    Log::warning('Product detail URL not found after productsLists marker');
                }
            } else {
                Log::warning('productsLists marker not found in search results');
            }

            return null;

        } catch (Exception $e) {
            Log::error('Failed to extract customer price', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function testConnection(): array
    {
        try {
            $response = $this->client->get('/');
            $authResult = $this->testAuthentication();

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
            // Step 1: Get login page (correct URL)
            $loginPage = $this->client->get('/users');
            $loginHtml = (string) $loginPage->getBody();

            $debug = [
                'step_1_login_page' => [
                    'url' => '/users',
                    'status' => $loginPage->getStatusCode(),
                    'has_form' => str_contains($loginHtml, '<form'),
                    'has_email_field' => str_contains($loginHtml, 'name="email"'),
                    'html_length' => strlen($loginHtml),
                ],
            ];

            // Step 2: Extract CSRF token (might not exist)
            $csrfToken = $this->extractCsrfToken($loginHtml);
            $debug['step_2_csrf'] = [
                'token_found' => ! is_null($csrfToken),
                'token_preview' => $csrfToken ? substr($csrfToken, 0, 10).'...' : null,
                'note' => 'Udea may not use CSRF tokens',
            ];

            // Step 3: Attempt login with correct field names
            $loginData = [
                'email' => $this->config['username'], // Use 'email' field
                'password' => $this->config['password'],
                'remember-me' => '1',
            ];

            // Add CSRF token if found
            if ($csrfToken) {
                $loginData['_token'] = $csrfToken;
            }

            $debug['step_3_login_attempt'] = [
                'url' => '/users/login',
                'email' => $this->config['username'] ? 'SET' : 'NOT_SET',
                'password' => $this->config['password'] ? 'SET' : 'NOT_SET',
                'form_data_keys' => array_keys($loginData),
            ];

            $loginResponse = $this->client->post('/users/login', [
                'form_params' => $loginData,
                'allow_redirects' => false,
            ]);

            $responseBody = (string) $loginResponse->getBody();
            $debug['step_4_login_response'] = [
                'status_code' => $loginResponse->getStatusCode(),
                'has_location_header' => $loginResponse->hasHeader('Location'),
                'location' => $loginResponse->getHeader('Location')[0] ?? null,
                'response_size' => strlen($responseBody),
                'response_preview' => substr($responseBody, 0, 300),
                'contains_error' => str_contains(strtolower($responseBody), 'error') || str_contains(strtolower($responseBody), 'invalid'),
            ];

            // Step 4: Check if login was successful
            $statusCode = $loginResponse->getStatusCode();
            $isSuccess = ($statusCode === 302) ||
                        ($statusCode === 200 && ! str_contains(strtolower($responseBody), 'error'));

            if ($isSuccess) {
                $this->sessionCookie = 'authenticated';
            }

            return [
                'success' => $isSuccess,
                'debug' => $debug,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'debug' => $debug ?? [],
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
            Cache::forget("udea_product_{$productCode}");
        } else {
            Cache::flush();
        }
    }

    public function getProductDataForDeliveryItem(\App\Models\DeliveryItem $item): ?array
    {
        if (! $item->supplier_code) {
            Log::info('UdeaScrapingService: No supplier code for delivery item', [
                'delivery_item_id' => $item->id,
            ]);

            return null;
        }

        Log::info('UdeaScrapingService: Getting product data for delivery item', [
            'delivery_item_id' => $item->id,
            'supplier_code' => $item->supplier_code,
        ]);

        // Use existing getProductData method
        return $this->getProductData($item->supplier_code);
    }

    /**
     * Extract full product name from UDEA detail page HTML using prod-display-col-top structure
     * Format: "Brand ProductName Size" (e.g. "Ice Cream Factory Almond Choc 120 ml")
     */
    private function extractFullProductName(string $html): ?string
    {
        // First, try to get the detail page if we have search results
        $detailHtml = $this->getDetailPageHtml($html);
        if (! $detailHtml) {
            Log::info('UDEA name extraction: could not get detail page HTML');
            return null;
        }

        $brand = null;
        $productName = null;
        $size = null;

        // Extract product name from h2.prod-title (simple and reliable)
        if (preg_match('/<h2[^>]*class="[^"]*prod-title[^"]*"[^>]*>\s*([^<]+?)\s*<\/h2>/is', $detailHtml, $nameMatches)) {
            $productName = trim($nameMatches[1]);
            Log::info('UDEA name extraction: product name found from h2.prod-title', [
                'product_name' => $productName,
            ]);
        } else {
            Log::info('UDEA name extraction: product name not found in h2.prod-title');
        }

        // Extract brand from detail-subtitle strong tag (clean and direct)
        if (preg_match('/<div[^>]*class="[^"]*detail-subtitle[^"]*"[^>]*>.*?<strong>\s*([^<]+?)\s*<\/strong>/is', $detailHtml, $brandMatches)) {
            $brand = trim($brandMatches[1]);
            Log::info('UDEA name extraction: brand found from detail-subtitle', [
                'brand' => $brand,
            ]);
        } else {
            Log::info('UDEA name extraction: brand not found in detail-subtitle');
        }

        // Extract size from span.prod-qty (straightforward pattern)
        if (preg_match('/<span[^>]*class="[^"]*prod-qty[^"]*"[^>]*>\s*([^<]+?)\s*<\/span>/is', $detailHtml, $sizeMatches)) {
            $size = trim($sizeMatches[1]);
            Log::info('UDEA name extraction: size found from span.prod-qty', [
                'size' => $size,
            ]);
        } else {
            Log::info('UDEA name extraction: size not found in span.prod-qty');
        }

        // Construct full name from available components
        $components = array_filter([$brand, $productName, $size], function ($value) {
            return ! empty($value);
        });

        if (empty($components)) {
            Log::info('UDEA name extraction: no components found');
            return null;
        }

        $fullName = implode(' ', $components);

        Log::info('UDEA full product name extracted - SUCCESS (detail page method)', [
            'brand' => $brand,
            'product_name' => $productName,
            'size' => $size,
            'full_name' => $fullName,
            'components_count' => count($components),
        ]);

        return $fullName;
    }

    /**
     * Get detail page HTML from search results or use provided HTML if already detail page
     */
    private function getDetailPageHtml(string $searchHtml): ?string
    {
        // Check if this is already a detail page (contains prod-display-col-top)
        if (strpos($searchHtml, 'prod-display-col-top') !== false) {
            Log::info('UDEA name extraction: already on detail page');
            return $searchHtml;
        }

        // Try to extract detail URL from search results and fetch detail page
        try {
            if (strpos($searchHtml, 'id="productsLists"') !== false) {
                $productsListPos = strpos($searchHtml, 'id="productsLists"');
                $searchFromPos = substr($searchHtml, $productsListPos);

                // Look for detail link
                if (preg_match('/<a[^>]*href="(https:\/\/www\.udea\.nl\/product(?:en|s)\/product\/[^"]+)"[^>]*class="[^"]*detail-image[^"]*"/', $searchFromPos, $matches)) {
                    $detailUrl = $matches[1];
                    
                    Log::info('UDEA name extraction: fetching detail page', [
                        'detail_url' => $detailUrl,
                    ]);

                    // Fetch the detail page
                    $response = $this->client->get($detailUrl);
                    $detailHtml = (string) $response->getBody();

                    return $detailHtml;
                }
            }
        } catch (Exception $e) {
            Log::error('UDEA name extraction: failed to fetch detail page', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function queueProductScraping(
        string $productCode,
        ?string $callbackUrl = null,
        ?array $callbackData = null
    ): void {
        \App\Jobs\ScrapeProductDataJob::dispatch($productCode, $callbackUrl, $callbackData);

        Log::info('Product scraping job queued', [
            'product_code' => $productCode,
            'has_callback' => ! is_null($callbackUrl),
        ]);
    }
}
