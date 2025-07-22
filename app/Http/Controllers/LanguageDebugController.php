<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

class LanguageDebugController extends Controller
{
    public function testLanguageControl(Request $request)
    {
        $productCode = $request->get('product_code', '115');

        $debugInfo = [
            'product_code' => $productCode,
            'timestamp' => now()->toISOString(),
            'tests' => [],
        ];

        try {
            // Test 1: Default request (current working method)
            $debugInfo['tests']['1_default'] = $this->testWithSettings($productCode, [
                'url' => "/search/?qry={$productCode}",
                'headers' => [
                    'Accept-Language' => 'en-US,en;q=0.9,nl;q=0.1',
                ],
                'description' => 'Current working method',
            ]);

            // Test 2: Try explicit English URL
            $debugInfo['tests']['2_english_url'] = $this->testWithSettings($productCode, [
                'url' => "/en/search/?qry={$productCode}",
                'headers' => [
                    'Accept-Language' => 'en-US,en;q=1.0',
                ],
                'description' => 'Explicit English URL with strong preference',
            ]);

            // Test 3: Try strong English headers only
            $debugInfo['tests']['3_strong_headers'] = $this->testWithSettings($productCode, [
                'url' => "/search/?qry={$productCode}",
                'headers' => [
                    'Accept-Language' => 'en-US,en;q=1.0',
                ],
                'description' => 'Default URL with very strong English preference',
            ]);

            // Test 4: Try with additional English indicators
            $debugInfo['tests']['4_full_english'] = $this->testWithSettings($productCode, [
                'url' => "/search/?qry={$productCode}",
                'headers' => [
                    'Accept-Language' => 'en-US,en;q=1.0',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Charset' => 'utf-8',
                ],
                'description' => 'Default URL with comprehensive English headers',
            ]);

        } catch (\Exception $e) {
            $debugInfo['error'] = $e->getMessage();
        }

        return view('tests.language-debug', compact('debugInfo'));
    }

    private function testWithSettings($productCode, $settings)
    {
        $client = new Client([
            'base_uri' => 'https://www.udea.nl',
            'timeout' => 30,
            'cookies' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            ],
        ]);

        $result = [
            'description' => $settings['description'],
            'url' => $settings['url'],
            'headers' => $settings['headers'],
            'success' => false,
            'response_code' => null,
            'language_detected' => 'unknown',
            'detail_url' => null,
            'detail_language' => 'unknown',
            'product_name' => null,
            'brand' => null,
            'size' => null,
            'full_description' => null,
        ];

        try {
            // Step 1: Try to authenticate (simplified)
            $loginPage = $client->get('/users');
            $loginResponse = $client->post('/users/login', [
                'form_params' => [
                    'email' => config('services.udea.username'),
                    'password' => config('services.udea.password'),
                    'remember-me' => '1',
                ],
                'allow_redirects' => false,
            ]);

            // Step 2: Search with specified settings
            $response = $client->get($settings['url'], [
                'headers' => $settings['headers'],
            ]);

            $result['success'] = $response->getStatusCode() === 200;
            $result['response_code'] = $response->getStatusCode();

            if ($result['success']) {
                $html = (string) $response->getBody();

                // Detect language from search results
                $result['language_detected'] = $this->detectLanguage($html);

                // Try to find detail URL
                if (strpos($html, 'id="productsLists"') !== false) {
                    $productsListPos = strpos($html, 'id="productsLists"');
                    $searchFromPos = substr($html, $productsListPos);

                    if (preg_match('/<a[^>]*href="(https:\/\/www\.udea\.nl\/product(?:en|s)\/product\/[^"]+)"[^>]*class="[^"]*detail-image[^"]*"/', $searchFromPos, $matches)) {
                        $result['detail_url'] = $matches[1];
                        $result['detail_language'] = strpos($matches[1], '/producten/product/') !== false ? 'dutch' : 'english';

                        // Fetch detail page and extract product info
                        $detailResponse = $client->get($matches[1], [
                            'headers' => $settings['headers'],
                        ]);

                        if ($detailResponse->getStatusCode() === 200) {
                            $detailHtml = (string) $detailResponse->getBody();
                            $this->extractProductInfo($detailHtml, $result);
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function detectLanguage($html)
    {
        $isDutch = strpos($html, '/producten/product/') !== false;
        $isEnglish = strpos($html, '/products/product/') !== false;

        if ($isDutch && ! $isEnglish) {
            return 'dutch';
        }
        if ($isEnglish && ! $isDutch) {
            return 'english';
        }
        if ($isDutch && $isEnglish) {
            return 'mixed';
        }

        // Look for language indicators in HTML
        if (strpos($html, 'lang="nl"') !== false) {
            return 'dutch';
        }
        if (strpos($html, 'lang="en"') !== false) {
            return 'english';
        }

        return 'unknown';
    }

    private function extractProductInfo($html, &$result)
    {
        // Extract product name from h2.prod-title
        if (preg_match('/<h2[^>]*class="[^"]*prod-title[^"]*"[^>]*>\s*([^<]+?)\s*<\/h2>/is', $html, $matches)) {
            $result['product_name'] = trim($matches[1]);
        }

        // Extract brand from detail-subtitle strong tag
        if (preg_match('/<div[^>]*class="[^"]*detail-subtitle[^"]*"[^>]*>.*?<strong>\s*([^<]+?)\s*<\/strong>/is', $html, $matches)) {
            $result['brand'] = trim($matches[1]);
        }

        // Extract size from span.prod-qty
        if (preg_match('/<span[^>]*class="[^"]*prod-qty[^"]*"[^>]*>\s*([^<]+?)\s*<\/span>/is', $html, $matches)) {
            $result['size'] = trim($matches[1]);
        }

        // Construct full description
        $components = array_filter([$result['brand'], $result['product_name'], $result['size']]);
        if (! empty($components)) {
            $result['full_description'] = implode(' ', $components);
        }
    }
}
