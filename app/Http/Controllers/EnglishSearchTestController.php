<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

class EnglishSearchTestController extends Controller
{
    public function testEnglishSearch(Request $request)
    {
        $productCode = $request->get('product_code', '115');

        $results = [
            'product_code' => $productCode,
            'timestamp' => now()->toISOString(),
            'tests' => [],
        ];

        $client = new Client([
            'base_uri' => 'https://www.udea.nl',
            'timeout' => 30,
            'cookies' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
        ]);

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

            // Test 1: Check if there's a language switcher/cookie we can set
            $results['tests']['1_homepage_english'] = $this->testHomepageLanguage($client);

            // Test 2: Try different English URL structures
            $results['tests']['2_english_urls'] = $this->testEnglishUrls($client, $productCode);

            // Test 3: Try setting language cookies/sessions first
            $results['tests']['3_language_cookies'] = $this->testLanguageCookies($client, $productCode);

            // Test 4: Try different language parameters
            $results['tests']['4_language_params'] = $this->testLanguageParams($client, $productCode);

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return view('tests.english-search-test', compact('results'));
    }

    private function testHomepageLanguage($client)
    {
        $result = ['description' => 'Check homepage language options'];

        try {
            $response = $client->get('/');
            $html = (string) $response->getBody();

            $result['success'] = true;
            $result['language_detected'] = $this->detectLanguage($html);

            // Look for language switcher links
            $languageSwitchers = [];
            if (preg_match_all('/<a[^>]*href="([^"]*)"[^>]*[^>]*(english|en|language|taal)[^<]*<\/a>/i', $html, $matches)) {
                $languageSwitchers = array_combine($matches[1], $matches[0]);
            }
            $result['language_switchers'] = $languageSwitchers;

            // Look for /en/ links
            if (preg_match_all('/href="([^"]*\/en\/[^"]*)"/', $html, $matches)) {
                $result['en_links'] = array_unique($matches[1]);
            }

            $result['html_sample'] = substr($html, 0, 1000);

        } catch (\Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function testEnglishUrls($client, $productCode)
    {
        $urlsToTest = [
            "/en/search?qry={$productCode}",
            "/search/en/?qry={$productCode}",
            "/search/?qry={$productCode}&lang=en",
            "/search/?qry={$productCode}&language=en",
            "/search/?qry={$productCode}&locale=en",
            "/en-us/search/?qry={$productCode}",
            "/search/?qry={$productCode}&l=en",
        ];

        $results = [];

        foreach ($urlsToTest as $url) {
            $result = [
                'url' => $url,
                'success' => false,
                'status_code' => null,
                'language_detected' => 'unknown',
                'product_links' => [],
                'error' => null,
            ];

            try {
                $response = $client->get($url, [
                    'headers' => [
                        'Accept-Language' => 'en-US,en;q=1.0',
                    ],
                ]);

                $result['status_code'] = $response->getStatusCode();
                $result['success'] = $response->getStatusCode() === 200;

                if ($result['success']) {
                    $html = (string) $response->getBody();
                    $result['language_detected'] = $this->detectLanguage($html);

                    // Extract product links
                    if (preg_match_all('/<a[^>]*href="(https:\/\/www\.udea\.nl\/products?\/product\/[^"]+)"/', $html, $matches)) {
                        $result['product_links'] = array_unique($matches[1]);
                    }
                }

            } catch (\Exception $e) {
                $result['error'] = $e->getMessage();
            }

            $results[] = $result;
        }

        return $results;
    }

    private function testLanguageCookies($client, $productCode)
    {
        $cookiesToTest = [
            ['name' => 'language', 'value' => 'en'],
            ['name' => 'lang', 'value' => 'en'],
            ['name' => 'locale', 'value' => 'en'],
            ['name' => 'udea_language', 'value' => 'en'],
            ['name' => 'site_language', 'value' => 'english'],
        ];

        $results = [];

        foreach ($cookiesToTest as $cookie) {
            $result = [
                'cookie' => $cookie,
                'success' => false,
                'language_detected' => 'unknown',
                'product_links' => [],
            ];

            try {
                // Set cookie and then search
                $response = $client->get("/search/?qry={$productCode}", [
                    'headers' => [
                        'Accept-Language' => 'en-US,en;q=1.0',
                        'Cookie' => "{$cookie['name']}={$cookie['value']}",
                    ],
                ]);

                $result['success'] = $response->getStatusCode() === 200;

                if ($result['success']) {
                    $html = (string) $response->getBody();
                    $result['language_detected'] = $this->detectLanguage($html);

                    if (preg_match_all('/<a[^>]*href="(https:\/\/www\.udea\.nl\/products?\/product\/[^"]+)"/', $html, $matches)) {
                        $result['product_links'] = array_unique($matches[1]);
                    }
                }

            } catch (\Exception $e) {
                $result['error'] = $e->getMessage();
            }

            $results[] = $result;
        }

        return $results;
    }

    private function testLanguageParams($client, $productCode)
    {
        // Try visiting English pages first to set session language
        $setupUrls = [
            '/en',
            '/en/',
            '/?lang=en',
            '/?language=en',
        ];

        $results = [];

        foreach ($setupUrls as $setupUrl) {
            $result = [
                'setup_url' => $setupUrl,
                'success' => false,
                'search_language' => 'unknown',
                'product_links' => [],
            ];

            try {
                // Visit setup URL first
                $setupResponse = $client->get($setupUrl, [
                    'headers' => ['Accept-Language' => 'en-US,en;q=1.0'],
                ]);

                if ($setupResponse->getStatusCode() === 200) {
                    // Then try search
                    $searchResponse = $client->get("/search/?qry={$productCode}", [
                        'headers' => ['Accept-Language' => 'en-US,en;q=1.0'],
                    ]);

                    $result['success'] = $searchResponse->getStatusCode() === 200;

                    if ($result['success']) {
                        $html = (string) $searchResponse->getBody();
                        $result['search_language'] = $this->detectLanguage($html);

                        if (preg_match_all('/<a[^>]*href="(https:\/\/www\.udea\.nl\/products?\/product\/[^"]+)"/', $html, $matches)) {
                            $result['product_links'] = array_unique($matches[1]);
                        }
                    }
                }

            } catch (\Exception $e) {
                $result['error'] = $e->getMessage();
            }

            $results[] = $result;
        }

        return $results;
    }

    private function detectLanguage($html)
    {
        $isDutch = strpos($html, '/producten/product/') !== false;
        $isEnglish = strpos($html, '/products/product/') !== false;

        if ($isEnglish && ! $isDutch) {
            return 'english';
        }
        if ($isDutch && ! $isEnglish) {
            return 'dutch';
        }
        if ($isDutch && $isEnglish) {
            return 'mixed';
        }

        return 'unknown';
    }
}
