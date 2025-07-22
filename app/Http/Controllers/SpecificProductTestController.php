<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

class SpecificProductTestController extends Controller
{
    public function testSpecificProduct(Request $request)
    {
        $productCode = $request->get('product_code', '6001223');

        $results = [
            'product_code' => $productCode,
            'timestamp' => now()->toISOString(),
            'baseline_test' => null,
            'session_experiments' => [],
            'comparison' => null,
        ];

        try {
            $client = new Client([
                'base_uri' => 'https://www.udea.nl',
                'timeout' => 30,
                'cookies' => true,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                ],
            ]);

            // Test 1: Baseline (current method)
            $results['baseline_test'] = $this->testCurrentMethod($client, $productCode);

            // Test 2: Various session setup methods
            $results['session_experiments'] = $this->testSessionMethods($client, $productCode);

            // Test 3: Compare results
            $results['comparison'] = $this->compareResults($results['baseline_test'], $results['session_experiments']);

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return view('tests.specific-product-test', compact('results'));
    }

    private function testCurrentMethod($client, $productCode)
    {
        $result = [
            'method' => 'Current working method',
            'success' => false,
            'product_links' => [],
            'product_details' => null,
        ];

        try {
            // Authenticate
            $this->authenticate($client);

            // Search
            sleep(2);
            $searchResponse = $client->get("/search/?qry={$productCode}", [
                'headers' => ['Accept-Language' => 'en-US,en;q=0.9,nl;q=0.1'],
            ]);

            if ($searchResponse->getStatusCode() === 200) {
                $html = (string) $searchResponse->getBody();
                $result['success'] = true;
                $result['search_html_sample'] = substr($html, 0, 2000);

                // Extract product links
                $result['product_links'] = $this->extractProductLinks($html);

                // If we found a link, get product details
                if (! empty($result['product_links'])) {
                    $result['product_details'] = $this->getProductDetails($client, $result['product_links'][0]);
                }
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function testSessionMethods($client, $productCode)
    {
        $methods = [
            // Method 1: Set language cookie first
            [
                'name' => 'Language cookie before auth',
                'setup' => function ($client) {
                    $client->get('/', ['headers' => ['Cookie' => 'language=en']]);
                },
            ],

            // Method 2: Try localStorage simulation with cookie
            [
                'name' => 'LocalStorage lang=EN + cookie',
                'setup' => function ($client) {
                    $client->get('/', ['headers' => ['Cookie' => 'language=en; lang=EN']]);
                },
            ],

            // Method 3: Try multiple language indicators
            [
                'name' => 'Multiple language headers',
                'setup' => function ($client) {
                    $client->get('/', [
                        'headers' => [
                            'Cookie' => 'language=en; locale=en; site_lang=en',
                            'Accept-Language' => 'en-US,en;q=1.0,nl;q=0.1',
                        ],
                    ]);
                },
            ],

            // Method 4: Visit homepage with strong English preference first
            [
                'name' => 'Strong English preference setup',
                'setup' => function ($client) {
                    $client->get('/', [
                        'headers' => [
                            'Accept-Language' => 'en-US,en;q=1.0',
                            'Accept-Charset' => 'utf-8',
                            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        ],
                    ]);
                    $client->get('/', ['headers' => ['Cookie' => 'language=en']]);
                },
            ],

            // Method 5: Try session with specific English parameters
            [
                'name' => 'English session parameters',
                'setup' => function ($client) {
                    // Visit with language parameter
                    $client->get('/?lang=en&language=english');
                    // Set cookie
                    $client->get('/', ['headers' => ['Cookie' => 'language=en; currentSiteLang=en']]);
                },
            ],

            // Method 6: Try different cookie combinations from JavaScript analysis
            [
                'name' => 'JavaScript-based session',
                'setup' => function ($client) {
                    // Based on the JS we found: currentSiteLang and currentDialogLanguage
                    $client->get('/', [
                        'headers' => [
                            'Cookie' => 'language=en; currentSiteLang=en; currentDialogLanguage=en',
                        ],
                    ]);
                },
            ],
        ];

        $results = [];

        foreach ($methods as $methodConfig) {
            $result = [
                'method_name' => $methodConfig['name'],
                'success' => false,
                'product_links' => [],
                'english_links_found' => 0,
                'product_details' => null,
            ];

            try {
                // Create fresh client for each test
                $testClient = new Client([
                    'base_uri' => 'https://www.udea.nl',
                    'timeout' => 30,
                    'cookies' => true,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    ],
                ]);

                // Setup language preference
                $methodConfig['setup']($testClient);

                // Authenticate
                $this->authenticate($testClient);

                // Search with setup language preference
                sleep(2);
                $searchResponse = $testClient->get("/search/?qry={$productCode}");

                if ($searchResponse->getStatusCode() === 200) {
                    $html = (string) $searchResponse->getBody();
                    $result['success'] = true;
                    $result['search_html_sample'] = substr($html, 0, 1500);

                    // Extract product links
                    $result['product_links'] = $this->extractProductLinks($html);

                    // Count English vs Dutch links
                    foreach ($result['product_links'] as $link) {
                        if (strpos($link, '/products/product/') !== false) {
                            $result['english_links_found']++;
                        }
                    }

                    // If we found English links, get details from the first one
                    if ($result['english_links_found'] > 0) {
                        $englishLink = null;
                        foreach ($result['product_links'] as $link) {
                            if (strpos($link, '/products/product/') !== false) {
                                $englishLink = $link;
                                break;
                            }
                        }
                        if ($englishLink) {
                            $result['product_details'] = $this->getProductDetails($testClient, $englishLink);
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

    private function authenticate($client)
    {
        $loginPage = $client->get('/users');

        return $client->post('/users/login', [
            'form_params' => [
                'email' => config('services.udea.username'),
                'password' => config('services.udea.password'),
                'remember-me' => '1',
            ],
            'allow_redirects' => false,
        ]);
    }

    private function extractProductLinks($html)
    {
        $links = [];
        if (strpos($html, 'id="productsLists"') !== false) {
            $productsListPos = strpos($html, 'id="productsLists"');
            $searchFromPos = substr($html, $productsListPos);

            if (preg_match_all('/<a[^>]*href="(https:\/\/www\.udea\.nl\/product(?:en|s)\/product\/[^"]+)"/', $searchFromPos, $matches)) {
                $links = array_unique($matches[1]);
            }
        }

        return $links;
    }

    private function getProductDetails($client, $detailUrl)
    {
        $details = [
            'url' => $detailUrl,
            'is_english' => strpos($detailUrl, '/products/product/') !== false,
            'success' => false,
            'product_name' => null,
            'brand' => null,
            'size' => null,
            'full_description' => null,
        ];

        try {
            $response = $client->get($detailUrl);

            if ($response->getStatusCode() === 200) {
                $html = (string) $response->getBody();
                $details['success'] = true;
                $details['html_sample'] = substr($html, 0, 2000);

                // Extract product information using the same patterns as main service
                if (preg_match('/<h2[^>]*class="[^"]*prod-title[^"]*"[^>]*>\s*([^<]+?)\s*<\/h2>/is', $html, $matches)) {
                    $details['product_name'] = trim($matches[1]);
                }

                if (preg_match('/<div[^>]*class="[^"]*detail-subtitle[^"]*"[^>]*>.*?<strong>\s*([^<]+?)\s*<\/strong>/is', $html, $matches)) {
                    $details['brand'] = trim($matches[1]);
                }

                if (preg_match('/<span[^>]*class="[^"]*prod-qty[^"]*"[^>]*>\s*([^<]+?)\s*<\/span>/is', $html, $matches)) {
                    $details['size'] = trim($matches[1]);
                }

                // Construct full description
                $components = array_filter([$details['brand'], $details['product_name'], $details['size']]);
                if (! empty($components)) {
                    $details['full_description'] = implode(' ', $components);
                }
            }

        } catch (\Exception $e) {
            $details['error'] = $e->getMessage();
        }

        return $details;
    }

    private function compareResults($baseline, $experiments)
    {
        $comparison = [
            'baseline_success' => $baseline['success'] ?? false,
            'baseline_links_count' => count($baseline['product_links'] ?? []),
            'baseline_english_links' => 0,
            'successful_experiments' => [],
            'best_method' => null,
        ];

        // Count English links in baseline
        foreach ($baseline['product_links'] ?? [] as $link) {
            if (strpos($link, '/products/product/') !== false) {
                $comparison['baseline_english_links']++;
            }
        }

        // Find successful experiments
        foreach ($experiments as $experiment) {
            if (($experiment['english_links_found'] ?? 0) > 0) {
                $comparison['successful_experiments'][] = $experiment['method_name'];

                if (! $comparison['best_method'] ||
                    ($experiment['english_links_found'] ?? 0) > ($comparison['best_method']['english_links'] ?? 0)) {
                    $comparison['best_method'] = [
                        'name' => $experiment['method_name'],
                        'english_links' => $experiment['english_links_found'] ?? 0,
                        'product_details' => $experiment['product_details'],
                    ];
                }
            }
        }

        return $comparison;
    }
}
