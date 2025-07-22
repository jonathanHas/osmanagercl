<?php

namespace App\Http\Controllers;

use App\Services\UdeaScrapingService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class AuthenticationTestController extends Controller
{
    public function testAuthentication(Request $request)
    {
        $productCode = $request->get('product_code', '115');
        
        $results = [
            'product_code' => $productCode,
            'timestamp' => now()->toISOString(),
            'working_service_test' => null,
            'manual_auth_test' => null,
            'comparison' => null,
        ];

        try {
            // Test 1: Use the working UdeaScrapingService (known to work)
            $results['working_service_test'] = $this->testWorkingService($productCode);
            
            // Test 2: Manual authentication and search (like our previous tests)
            $results['manual_auth_test'] = $this->testManualAuth($productCode);
            
            // Test 3: Compare the two approaches
            $results['comparison'] = $this->compareResults($results['working_service_test'], $results['manual_auth_test']);

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return view('tests.authentication-test', compact('results'));
    }

    private function testWorkingService($productCode)
    {
        $result = [
            'method' => 'Working UdeaScrapingService',
            'success' => false,
            'data' => null,
            'product_links' => [],
            'language_detected' => 'unknown',
        ];

        try {
            $service = new UdeaScrapingService();
            $data = $service->getProductData($productCode);
            
            $result['success'] = !is_null($data);
            $result['data'] = $data;
            
            if ($data) {
                $result['product_name'] = $data['description'] ?? 'N/A';
                $result['brand'] = $data['brand'] ?? 'N/A';
                $result['size'] = $data['size'] ?? 'N/A';
            }
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function testManualAuth($productCode)
    {
        $result = [
            'method' => 'Manual Authentication + Search',
            'success' => false,
            'search_html_sample' => '',
            'product_links' => [],
            'language_detected' => 'unknown',
            'authentication_status' => 'unknown',
        ];

        try {
            $client = new Client([
                'base_uri' => 'https://www.udea.nl',
                'timeout' => 30,
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

            // Step 1: Get login page
            $loginPage = $client->get('/users');
            $loginHtml = (string) $loginPage->getBody();
            
            // Step 2: Attempt login
            $loginResponse = $client->post('/users/login', [
                'form_params' => [
                    'email' => config('services.udea.username'),
                    'password' => config('services.udea.password'),
                    'remember-me' => '1',
                ],
                'allow_redirects' => false,
            ]);

            $result['authentication_status'] = $loginResponse->getStatusCode();
            
            // Step 3: Search with rate limiting
            sleep(2); // Rate limiting like the working service
            
            $searchResponse = $client->get("/search/?qry={$productCode}", [
                'headers' => [
                    'Accept-Language' => 'en-US,en;q=0.9,nl;q=0.1',
                ],
            ]);

            if ($searchResponse->getStatusCode() === 200) {
                $searchHtml = (string) $searchResponse->getBody();
                $result['search_html_sample'] = substr($searchHtml, 0, 3000);
                $result['success'] = true;
                
                // Detect language
                $result['language_detected'] = $this->detectLanguage($searchHtml);
                
                // Look for product links after authentication
                if (strpos($searchHtml, 'id="productsLists"') !== false) {
                    $productsListPos = strpos($searchHtml, 'id="productsLists"');
                    $searchFromPos = substr($searchHtml, $productsListPos);
                    
                    if (preg_match_all('/<a[^>]*href="(https:\/\/www\.udea\.nl\/product(?:en|s)\/product\/[^"]+)"[^>]*class="[^"]*detail-image[^"]*"/', $searchFromPos, $matches)) {
                        $result['product_links'] = array_unique($matches[1]);
                    }
                }
                
                // Check if we're getting the expected content
                $result['contains_products_list'] = strpos($searchHtml, 'id="productsLists"') !== false;
                $result['contains_product_code'] = strpos($searchHtml, $productCode) !== false;
                $result['html_title'] = '';
                if (preg_match('/<title>([^<]+)<\/title>/', $searchHtml, $matches)) {
                    $result['html_title'] = trim($matches[1]);
                }
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function compareResults($workingTest, $manualTest)
    {
        return [
            'working_service_succeeds' => $workingTest['success'] ?? false,
            'manual_auth_succeeds' => $manualTest['success'] ?? false,
            'working_service_finds_product' => isset($workingTest['data']) && !is_null($workingTest['data']),
            'manual_auth_finds_links' => isset($manualTest['product_links']) && count($manualTest['product_links']) > 0,
            'authentication_difference' => ($workingTest['success'] ?? false) !== ($manualTest['success'] ?? false),
            'possible_issue' => $this->identifyPossibleIssue($workingTest, $manualTest),
        ];
    }

    private function identifyPossibleIssue($workingTest, $manualTest)
    {
        if (($workingTest['success'] ?? false) && !($manualTest['success'] ?? false)) {
            return 'Manual authentication method is failing - the working service uses different authentication approach';
        }
        
        if (($workingTest['success'] ?? false) && ($manualTest['success'] ?? false)) {
            if (isset($workingTest['data']) && count($manualTest['product_links'] ?? []) === 0) {
                return 'Authentication works but search results differ - possible session/cookie issue';
            }
        }
        
        if (!($workingTest['success'] ?? false) && !($manualTest['success'] ?? false)) {
            return 'Both methods failing - possible UDEA service issue or configuration problem';
        }
        
        return 'Results are consistent between methods';
    }

    private function detectLanguage($html)
    {
        $isDutch = strpos($html, '/producten/product/') !== false;
        $isEnglish = strpos($html, '/products/product/') !== false;
        
        if ($isEnglish && !$isDutch) return 'english';
        if ($isDutch && !$isEnglish) return 'dutch';
        if ($isDutch && $isEnglish) return 'mixed';
        
        return 'unknown';
    }
}