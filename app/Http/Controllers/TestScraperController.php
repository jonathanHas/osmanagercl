<?php

namespace App\Http\Controllers;

use App\Services\UdeaScrapingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class TestScraperController extends Controller
{
    private UdeaScrapingService $scrapingService;

    public function __construct(UdeaScrapingService $scrapingService)
    {
        $this->scrapingService = $scrapingService;
    }

    public function guzzleLogin(Request $request): View
    {
        $productCode = $request->get('product_code', '5014415');
        $data = $this->scrapingService->getProductData($productCode);
        
        return view('tests.guzzle', [
            'product_code' => $productCode,
            'data' => $data,
            'success' => !is_null($data),
        ]);
    }

    public function clientFetch(): View
    {
        return view('tests.client', [
            'product_code' => '5014415',
        ]);
    }

    public function proxyProductData(Request $request): JsonResponse
    {
        $request->validate([
            'product_code' => 'required|string|max:50',
        ]);

        $productCode = $request->input('product_code');
        $data = $this->scrapingService->getProductData($productCode);

        if ($data) {
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Product data could not be retrieved',
        ], 404);
    }

    public function testConnection(): JsonResponse
    {
        $result = $this->scrapingService->testConnection();
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function clearCache(Request $request): JsonResponse
    {
        $productCode = $request->input('product_code');
        
        $this->scrapingService->clearCache($productCode);
        
        return response()->json([
            'success' => true,
            'message' => $productCode 
                ? "Cache cleared for product: {$productCode}" 
                : 'All cache cleared',
        ]);
    }

    public function dashboard(): View
    {
        $connectionTest = $this->scrapingService->testConnection();
        
        return view('tests.dashboard', [
            'connection_status' => $connectionTest,
        ]);
    }

    public function queueScraping(Request $request): JsonResponse
    {
        $request->validate([
            'product_code' => 'required|string|max:50',
            'callback_url' => 'nullable|url',
            'callback_data' => 'nullable|array',
        ]);

        $this->scrapingService->queueProductScraping(
            $request->input('product_code'),
            $request->input('callback_url'),
            $request->input('callback_data')
        );

        return response()->json([
            'success' => true,
            'message' => 'Product scraping job queued successfully',
        ]);
    }

    public function testApiRoute(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'API route is working',
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function testUdeaConnection(): JsonResponse
    {
        try {
            // Test multiple ways to make the request
            $results = [];
            
            // Method 1: Basic Guzzle
            try {
                $client = new \GuzzleHttp\Client([
                    'timeout' => 10,
                    'verify' => false,
                    'allow_redirects' => false,
                    'base_uri' => null, // Force no base URI
                ]);

                $response = $client->get('https://www.udea.nl');
                $results['guzzle_basic'] = [
                    'success' => true,
                    'status_code' => $response->getStatusCode(),
                    'headers' => array_keys($response->getHeaders()),
                    'body_preview' => substr((string) $response->getBody(), 0, 200),
                ];
            } catch (\Exception $e) {
                $results['guzzle_basic'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }

            // Method 2: cURL directly
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://www.udea.nl');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; TestBot)');
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($error) {
                    $results['curl_direct'] = [
                        'success' => false,
                        'error' => $error,
                    ];
                } else {
                    $results['curl_direct'] = [
                        'success' => true,
                        'status_code' => $httpCode,
                        'body_preview' => substr($response, 0, 200),
                    ];
                }
            } catch (\Exception $e) {
                $results['curl_direct'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }

            // Method 3: Check current environment
            $results['environment'] = [
                'app_url' => config('app.url'),
                'app_env' => config('app.env'),
                'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
                'http_host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
            ];
            
            return response()->json([
                'success' => true,
                'results' => $results,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
        }
    }

    public function findLoginUrl(): JsonResponse
    {
        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 10,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ]
            ]);

            // Get the homepage
            $response = $client->get('https://www.udea.nl');
            $html = (string) $response->getBody();

            // Look for login links and forms
            $results = [
                'status_code' => $response->getStatusCode(),
                'html_length' => strlen($html),
            ];

            // Find login-related links
            preg_match_all('/<a[^>]*href=["\']([^"\']*login[^"\']*)["\'][^>]*>(.*?)<\/a>/i', $html, $loginLinks);
            preg_match_all('/<a[^>]*href=["\']([^"\']*inlog[^"\']*)["\'][^>]*>(.*?)<\/a>/i', $html, $inlogLinks);
            preg_match_all('/<a[^>]*href=["\']([^"\']*sign[^"\']*)["\'][^>]*>(.*?)<\/a>/i', $html, $signLinks);

            $results['login_links'] = array_combine($loginLinks[1] ?? [], $loginLinks[2] ?? []);
            $results['inlog_links'] = array_combine($inlogLinks[1] ?? [], $inlogLinks[2] ?? []);
            $results['sign_links'] = array_combine($signLinks[1] ?? [], $signLinks[2] ?? []);

            // Look for forms
            preg_match_all('/<form[^>]*>(.*?)<\/form>/is', $html, $forms);
            $results['forms_found'] = count($forms[0]);

            // Look for common auth-related text
            $authKeywords = ['login', 'inloggen', 'sign in', 'aanmelden', 'account'];
            $foundKeywords = [];
            foreach ($authKeywords as $keyword) {
                if (stripos($html, $keyword) !== false) {
                    $foundKeywords[] = $keyword;
                }
            }
            $results['auth_keywords'] = $foundKeywords;

            // Get a larger preview of the HTML
            $results['html_preview'] = substr($html, 0, 2000);

            return response()->json([
                'success' => true,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
        }
    }

    public function debugSearchRaw(): JsonResponse
    {
        try {
            $productCode = '5014415';
            
            // Create a fresh scraping service and manually get the search page
            $client = new \GuzzleHttp\Client([
                'base_uri' => 'https://www.udea.nl',
                'timeout' => 30,
                'cookies' => true,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ]
            ]);

            // Login first
            $loginPage = $client->get('/users');
            $loginResponse = $client->post('/users/login', [
                'form_params' => [
                    'email' => config('services.udea.username'),
                    'password' => config('services.udea.password'),
                    'remember-me' => '1',
                ],
                'allow_redirects' => false
            ]);

            // Then search
            $searchResponse = $client->get("/search/?qry={$productCode}");
            $html = (string) $searchResponse->getBody();
            
            // Look for specific patterns that might contain product data
            $priceMatches = [];
            $productMatches = [];
            $caseMatches = [];
            
            preg_match_all('/price[^>]*>([^<]+)</i', $html, $priceMatches);
            preg_match_all('/(\d+[.,]\d+).*(?:euro|€)/i', $html, $euroMatches);
            preg_match_all('/case[^>]*>([^<]+)</i', $html, $caseMatches);
            preg_match_all('/(\d+)\s*(?:units?|pieces?|stuks?)/i', $html, $unitMatches);
            
            // Also look for the product code in the HTML
            $productCodeFound = str_contains($html, '5014415');
            
            return response()->json([
                'success' => true,
                'login_status' => $loginResponse->getStatusCode(),
                'search_status' => $searchResponse->getStatusCode(),
                'html_length' => strlen($html),
                'html_preview' => substr($html, 0, 3000),
                'product_code_found' => $productCodeFound,
                'extracted_patterns' => [
                    'price_elements' => array_slice($priceMatches[1] ?? [], 0, 5),
                    'euro_amounts' => array_slice($euroMatches[1] ?? [], 0, 5),
                    'case_elements' => array_slice($caseMatches[1] ?? [], 0, 5),
                    'unit_amounts' => array_slice($unitMatches[1] ?? [], 0, 5),
                ],
                'contains_product_info' => [
                    'price' => str_contains($html, 'price'),
                    'product' => str_contains($html, 'product'),
                    'euro' => str_contains($html, '€'),
                    'case' => str_contains($html, 'case'),
                    'units' => str_contains($html, 'units'),
                    'stuks' => str_contains($html, 'stuks'), // Dutch for units
                    'pieces' => str_contains($html, 'pieces'),
                ],
                'html_sections' => [
                    'search_results' => $this->extractHtmlSection($html, 'search', 500),
                    'product_info' => $this->extractHtmlSection($html, 'product', 500),
                    'price_info' => $this->extractHtmlSection($html, 'price', 500),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
        }
    }

    private function extractHtmlSection(string $html, string $keyword, int $maxLength = 500): string
    {
        $pos = stripos($html, $keyword);
        if ($pos === false) {
            return "Keyword '{$keyword}' not found";
        }
        
        $start = max(0, $pos - 200);
        $section = substr($html, $start, $maxLength);
        
        return $section;
    }

    public function debugSearch(): JsonResponse
    {
        try {
            $productCode = '5014415';
            $data = $this->scrapingService->getProductData($productCode);
            
            return response()->json([
                'success' => true,
                'product_code' => $productCode,
                'scraped_data' => $data,
                'data_found' => !is_null($data),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
        }
    }

    public function debugLoginPage(): JsonResponse
    {
        try {
            $config = config('services.udea');
            
            $client = new \GuzzleHttp\Client([
                'base_uri' => $config['base_uri'] ?? 'https://www.udea.nl',
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                ]
            ]);

            $response = $client->get('/login');
            $html = (string) $response->getBody();

            // Look for form fields
            preg_match_all('/<input[^>]*name=["\']([^"\']+)["\'][^>]*>/i', $html, $inputMatches);
            preg_match_all('/<form[^>]*>/i', $html, $formMatches);

            return response()->json([
                'success' => true,
                'config_check' => [
                    'base_uri' => $config['base_uri'] ?? 'NOT_SET',
                    'username' => $config['username'] ?? 'NOT_SET',
                    'password_length' => $config['password'] ? strlen($config['password']) : 0,
                    'timeout' => $config['timeout'] ?? 'NOT_SET',
                ],
                'status_code' => $response->getStatusCode(),
                'html_length' => strlen($html),
                'html_preview' => substr($html, 0, 1500) . '...',
                'forms_found' => count($formMatches[0]),
                'input_fields' => $inputMatches[1] ?? [],
                'csrf_patterns' => [
                    '_token' => (bool) preg_match('/name=["\']_token["\']/', $html),
                    'csrf_token' => (bool) preg_match('/name=["\']csrf_token["\']/', $html),
                    'csrf_meta' => (bool) preg_match('/<meta name=["\']csrf-token["\']/', $html),
                ],
                'response_headers' => array_map(function($header) {
                    return implode(', ', $header);
                }, $response->getHeaders()),
            ]);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            return response()->json([
                'success' => false,
                'error_type' => 'Server Error (5xx)',
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : 'unknown',
                'error' => $e->getMessage(),
                'response_body' => $e->getResponse() ? substr((string) $e->getResponse()->getBody(), 0, 1000) : 'No response body',
                'config_check' => [
                    'base_uri' => config('services.udea.base_uri') ?? 'NOT_SET',
                    'username' => config('services.udea.username') ?? 'NOT_SET',
                    'password_length' => config('services.udea.password') ? strlen(config('services.udea.password')) : 0,
                ],
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return response()->json([
                'success' => false,
                'error_type' => 'Client Error (4xx)',
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : 'unknown',
                'error' => $e->getMessage(),
                'response_body' => $e->getResponse() ? substr((string) $e->getResponse()->getBody(), 0, 1000) : 'No response body',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error_type' => 'General Exception',
                'error' => $e->getMessage(),
                'config_check' => [
                    'base_uri' => config('services.udea.base_uri') ?? 'NOT_SET',
                    'username' => config('services.udea.username') ?? 'NOT_SET',
                    'password_length' => config('services.udea.password') ? strlen(config('services.udea.password')) : 0,
                ],
            ]);
        }
    }
}