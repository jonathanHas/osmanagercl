<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

class LanguageFlagTestController extends Controller
{
    public function testLanguageFlag(Request $request)
    {
        $productCode = $request->get('product_code', '115');
        
        $results = [
            'product_code' => $productCode,
            'timestamp' => now()->toISOString(),
            'homepage_analysis' => null,
            'flag_analysis' => null,
            'language_switch_test' => null,
            'search_after_switch' => null,
        ];

        try {
            $client = new Client([
                'base_uri' => 'https://www.udea.nl',
                'timeout' => 30,
                'cookies' => true, // Important: maintain cookies
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                ],
            ]);

            // Step 1: Analyze homepage to find the British flag
            $results['homepage_analysis'] = $this->analyzeHomepage($client);
            
            // Step 2: Analyze flag functionality 
            $results['flag_analysis'] = $this->analyzeFlagElement($results['homepage_analysis']['html'] ?? '');
            
            // Step 3: Attempt to replicate flag click
            $results['language_switch_test'] = $this->testLanguageSwitch($client, $results['flag_analysis']);
            
            // Step 4: Search after language switch
            if ($results['language_switch_test']['success'] ?? false) {
                $results['search_after_switch'] = $this->searchAfterLanguageSwitch($client, $productCode);
            }

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return view('tests.language-flag-test', compact('results'));
    }

    private function analyzeHomepage($client)
    {
        $result = [
            'success' => false,
            'html' => '',
            'flag_elements' => [],
            'language_elements' => [],
            'scripts' => [],
            'cookies_before' => [],
        ];

        try {
            $response = $client->get('/');
            
            if ($response->getStatusCode() === 200) {
                $html = (string) $response->getBody();
                $result['success'] = true;
                $result['html'] = $html;
                
                // Look for British flag or language switcher elements
                $this->findFlagElements($html, $result);
                
                // Look for language-related JavaScript
                $this->findLanguageScripts($html, $result);
                
                // Look for existing language cookies
                $result['cookies_before'] = $this->extractCookies($response);
            }
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function findFlagElements($html, &$result)
    {
        // Look for flag images, language switchers, etc.
        $patterns = [
            'british_flag' => '/<[^>]*(?:flag|british|uk|gb|english)[^>]*>/i',
            'language_links' => '/<a[^>]*(?:language|lang|english|en)[^>]*>.*?<\/a>/i',
            'flag_images' => '/<img[^>]*(?:flag|british|uk|gb)[^>]*>/i',
            'language_buttons' => '/<button[^>]*(?:language|lang|english)[^>]*>.*?<\/button>/i',
            'data_lang_attributes' => '/data-lang[^=]*=[^>]*>/i',
            'onclick_language' => '/onclick[^=]*=[^>]*(?:language|lang|english)[^>]*/i',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $result['flag_elements'][$type] = array_slice($matches[0], 0, 5); // Limit to first 5 matches
            }
        }

        // Look for specific common patterns
        if (preg_match_all('/<[^>]*class="[^"]*flag[^"]*"[^>]*>/i', $html, $matches)) {
            $result['flag_elements']['flag_classes'] = array_slice($matches[0], 0, 5);
        }

        // Look for language selector dropdowns
        if (preg_match_all('/<select[^>]*(?:language|lang)[^>]*>.*?<\/select>/is', $html, $matches)) {
            $result['flag_elements']['language_selectors'] = array_slice($matches[0], 0, 3);
        }
    }

    private function findLanguageScripts($html, &$result)
    {
        // Extract JavaScript that might handle language switching
        if (preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $script) {
                if (stripos($script, 'language') !== false || 
                    stripos($script, 'lang') !== false || 
                    stripos($script, 'english') !== false ||
                    stripos($script, 'cookie') !== false) {
                    $result['scripts'][] = substr($script, 0, 500) . (strlen($script) > 500 ? '...' : '');
                }
            }
        }
    }

    private function extractCookies($response)
    {
        $cookies = [];
        foreach ($response->getHeaders() as $name => $values) {
            if (strtolower($name) === 'set-cookie') {
                foreach ($values as $cookie) {
                    $cookies[] = $cookie;
                }
            }
        }
        return $cookies;
    }

    private function analyzeFlagElement($html)
    {
        $result = [
            'flag_found' => false,
            'ajax_endpoints' => [],
            'cookie_operations' => [],
            'javascript_handlers' => [],
        ];

        // Look for AJAX endpoints that might handle language switching
        if (preg_match_all('/(?:url|ajax|fetch)\s*:\s*[\'"]([^\'"]*(?:language|lang|english|switch)[^\'"]*)[\'"]/', $html, $matches)) {
            $result['ajax_endpoints'] = array_unique($matches[1]);
        }

        // Look for cookie setting operations
        if (preg_match_all('/document\.cookie\s*=\s*[\'"]([^\'"]*(?:language|lang|english)[^\'"]*)[\'"]/', $html, $matches)) {
            $result['cookie_operations'] = array_unique($matches[1]);
        }

        // Look for JavaScript function calls related to language
        if (preg_match_all('/(?:setLanguage|switchLanguage|changeLang)\s*\([^)]*\)/', $html, $matches)) {
            $result['javascript_handlers'] = array_unique($matches[0]);
        }

        $result['flag_found'] = !empty($result['ajax_endpoints']) || 
                               !empty($result['cookie_operations']) || 
                               !empty($result['javascript_handlers']);

        return $result;
    }

    private function testLanguageSwitch($client, $flagAnalysis)
    {
        $result = [
            'success' => false,
            'methods_tested' => [],
        ];

        // Method 1: Try common language cookies
        $commonCookies = [
            'language=en',
            'lang=en', 
            'locale=en',
            'site_language=english',
            'udea_language=en',
            'user_language=en',
        ];

        foreach ($commonCookies as $cookieValue) {
            $method = [
                'method' => "Cookie: {$cookieValue}",
                'success' => false,
            ];

            try {
                $response = $client->get('/', [
                    'headers' => [
                        'Cookie' => $cookieValue,
                    ],
                ]);

                if ($response->getStatusCode() === 200) {
                    $html = (string) $response->getBody();
                    $method['success'] = true;
                    $method['language_detected'] = $this->detectLanguageInContent($html);
                    $method['contains_english_indicators'] = $this->hasEnglishIndicators($html);
                }

            } catch (\Exception $e) {
                $method['error'] = $e->getMessage();
            }

            $result['methods_tested'][] = $method;
            
            if ($method['success'] && ($method['contains_english_indicators'] ?? false)) {
                $result['success'] = true;
                $result['successful_method'] = $cookieValue;
                break;
            }
        }

        // Method 2: Try AJAX endpoints found in analysis
        foreach ($flagAnalysis['ajax_endpoints'] ?? [] as $endpoint) {
            $method = [
                'method' => "AJAX: {$endpoint}",
                'success' => false,
            ];

            try {
                $response = $client->post($endpoint, [
                    'json' => ['language' => 'en'],
                    'headers' => [
                        'Accept' => 'application/json, text/javascript, */*; q=0.01',
                        'X-Requested-With' => 'XMLHttpRequest',
                    ],
                ]);

                $method['success'] = in_array($response->getStatusCode(), [200, 204]);
                $method['response_code'] = $response->getStatusCode();
                $method['response_body'] = substr((string) $response->getBody(), 0, 200);

            } catch (\Exception $e) {
                $method['error'] = $e->getMessage();
            }

            $result['methods_tested'][] = $method;
        }

        return $result;
    }

    private function searchAfterLanguageSwitch($client, $productCode)
    {
        $result = [
            'success' => false,
            'language_detected' => 'unknown',
            'product_links' => [],
            'product_links_english' => 0,
            'product_links_dutch' => 0,
        ];

        try {
            // Authenticate first
            $loginPage = $client->get('/users');
            $loginResponse = $client->post('/users/login', [
                'form_params' => [
                    'email' => config('services.udea.username'),
                    'password' => config('services.udea.password'),
                    'remember-me' => '1',
                ],
                'allow_redirects' => false,
            ]);

            // Wait for rate limiting
            sleep(2);

            // Search with maintained language session
            $searchResponse = $client->get("/search/?qry={$productCode}");

            if ($searchResponse->getStatusCode() === 200) {
                $html = (string) $searchResponse->getBody();
                $result['success'] = true;
                $result['language_detected'] = $this->detectLanguageInContent($html);
                $result['html_sample'] = substr($html, 0, 2000);

                // Extract product links
                if (strpos($html, 'id="productsLists"') !== false) {
                    $productsListPos = strpos($html, 'id="productsLists"');
                    $searchFromPos = substr($html, $productsListPos);
                    
                    if (preg_match_all('/<a[^>]*href="(https:\/\/www\.udea\.nl\/product(?:en|s)\/product\/[^"]+)"/', $searchFromPos, $matches)) {
                        $result['product_links'] = array_unique($matches[1]);
                        
                        foreach ($result['product_links'] as $link) {
                            if (strpos($link, '/products/product/') !== false) {
                                $result['product_links_english']++;
                            } elseif (strpos($link, '/producten/product/') !== false) {
                                $result['product_links_dutch']++;
                            }
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function detectLanguageInContent($html)
    {
        $englishWords = ['products', 'product', 'search', 'results', 'price', 'description'];
        $dutchWords = ['producten', 'product', 'zoeken', 'resultaten', 'prijs', 'beschrijving'];
        
        $englishCount = 0;
        $dutchCount = 0;
        
        foreach ($englishWords as $word) {
            $englishCount += substr_count(strtolower($html), $word);
        }
        
        foreach ($dutchWords as $word) {
            $dutchCount += substr_count(strtolower($html), $word);
        }
        
        if ($englishCount > $dutchCount * 1.5) return 'english';
        if ($dutchCount > $englishCount * 1.5) return 'dutch';
        return 'mixed';
    }

    private function hasEnglishIndicators($html)
    {
        $indicators = [
            'Customer price:',
            'products/product/',
            'Add to cart',
            'Product description',
            'English',
        ];
        
        foreach ($indicators as $indicator) {
            if (stripos($html, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
}