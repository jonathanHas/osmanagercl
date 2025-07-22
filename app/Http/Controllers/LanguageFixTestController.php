<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

class LanguageFixTestController extends Controller
{
    public function testUrlConversion(Request $request)
    {
        $productCode = $request->get('product_code', '115');
        
        $results = [
            'product_code' => $productCode,
            'timestamp' => now()->toISOString(),
            'original_method' => null,
            'url_conversion_method' => null,
            'comparison' => [],
        ];

        try {
            $client = new Client([
                'base_uri' => 'https://www.udea.nl',
                'timeout' => 30,
                'cookies' => true,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ],
            ]);

            // Authenticate
            $loginPage = $client->get('/users');
            $loginResponse = $client->post('/users/login', [
                'form_params' => [
                    'email' => config('services.udea.username'),
                    'password' => config('services.udea.password'),
                    'remember-me' => '1',
                ],
                'allow_redirects' => false,
            ]);

            // Step 1: Get search results (same as current method)
            $searchResponse = $client->get("/search/?qry={$productCode}", [
                'headers' => ['Accept-Language' => 'en-US,en;q=0.9,nl;q=0.1'],
            ]);

            if ($searchResponse->getStatusCode() === 200) {
                $searchHtml = (string) $searchResponse->getBody();
                
                // Find detail URL (will be Dutch)
                if (strpos($searchHtml, 'id="productsLists"') !== false) {
                    $productsListPos = strpos($searchHtml, 'id="productsLists"');
                    $searchFromPos = substr($searchHtml, $productsListPos);
                    
                    if (preg_match('/<a[^>]*href="(https:\/\/www\.udea\.nl\/product(?:en|s)\/product\/[^"]+)"[^>]*class="[^"]*detail-image[^"]*"/', $searchFromPos, $matches)) {
                        $originalDetailUrl = $matches[1];
                        
                        // Test Original Method (Dutch URL)
                        $results['original_method'] = $this->testDetailPage($client, $originalDetailUrl, 'Original Dutch URL');
                        
                        // Test URL Conversion Method
                        if (strpos($originalDetailUrl, '/producten/product/') !== false) {
                            $englishDetailUrl = str_replace('/producten/product/', '/products/product/', $originalDetailUrl);
                            $results['url_conversion_method'] = $this->testDetailPage($client, $englishDetailUrl, 'Converted English URL', [
                                'Accept-Language' => 'en-US,en;q=1.0',
                                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                            ]);
                        }
                        
                        // Compare results
                        if ($results['original_method'] && $results['url_conversion_method']) {
                            $results['comparison'] = [
                                'original_name' => $results['original_method']['full_description'],
                                'converted_name' => $results['url_conversion_method']['full_description'],
                                'names_different' => $results['original_method']['full_description'] !== $results['url_conversion_method']['full_description'],
                                'original_language_indicators' => $this->detectLanguageIndicators($results['original_method']['html_sample']),
                                'converted_language_indicators' => $this->detectLanguageIndicators($results['url_conversion_method']['html_sample']),
                            ];
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return view('tests.language-fix-test', compact('results'));
    }

    private function testDetailPage($client, $url, $description, $additionalHeaders = [])
    {
        try {
            $headers = array_merge([
                'Accept-Language' => 'en-US,en;q=0.9,nl;q=0.1',
            ], $additionalHeaders);
            
            $response = $client->get($url, ['headers' => $headers]);
            
            if ($response->getStatusCode() === 200) {
                $html = (string) $response->getBody();
                
                $result = [
                    'description' => $description,
                    'url' => $url,
                    'success' => true,
                    'headers_used' => $headers,
                    'html_sample' => substr($html, 0, 2000), // First 2000 chars for analysis
                    'product_name' => null,
                    'brand' => null,
                    'size' => null,
                    'full_description' => null,
                ];

                // Extract product info
                if (preg_match('/<h2[^>]*class="[^"]*prod-title[^"]*"[^>]*>\s*([^<]+?)\s*<\/h2>/is', $html, $matches)) {
                    $result['product_name'] = trim($matches[1]);
                }

                if (preg_match('/<div[^>]*class="[^"]*detail-subtitle[^"]*"[^>]*>.*?<strong>\s*([^<]+?)\s*<\/strong>/is', $html, $matches)) {
                    $result['brand'] = trim($matches[1]);
                }

                if (preg_match('/<span[^>]*class="[^"]*prod-qty[^"]*"[^>]*>\s*([^<]+?)\s*<\/span>/is', $html, $matches)) {
                    $result['size'] = trim($matches[1]);
                }

                // Construct full description
                $components = array_filter([$result['brand'], $result['product_name'], $result['size']]);
                if (!empty($components)) {
                    $result['full_description'] = implode(' ', $components);
                }

                return $result;
            }
        } catch (\Exception $e) {
            return [
                'description' => $description,
                'url' => $url,
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        return null;
    }

    private function detectLanguageIndicators($html)
    {
        return [
            'has_dutch_keywords' => preg_match_all('/\b(producten|artikel|prijs|beschrijving|grootte)\b/i', $html),
            'has_english_keywords' => preg_match_all('/\b(products|item|price|description|size)\b/i', $html),
            'html_lang_attribute' => preg_match('/html[^>]*lang=["\']([^"\']+)["\']/', $html, $matches) ? $matches[1] : 'not found',
            'contains_customer_price_english' => strpos($html, 'Customer price:') !== false,
            'contains_customer_price_dutch' => strpos($html, 'Consumentenprijs:') !== false,
        ];
    }
}