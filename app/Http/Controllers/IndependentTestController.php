<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class IndependentTestController extends Controller
{
    /**
     * Display the Independent supplier test page.
     */
    public function index(Request $request): View
    {
        $supplierCode = $request->query('supplier_code', '');
        $results = [];
        
        if ($supplierCode) {
            $results = $this->testSupplierCode($supplierCode);
        }
        
        return view('products.independent-test', compact('supplierCode', 'results'));
    }
    
    /**
     * Test various data retrieval methods for a supplier code.
     */
    private function testSupplierCode(string $supplierCode): array
    {
        $results = [
            'supplier_code' => $supplierCode,
            'images' => [],
            'search_data' => null,
            'errors' => [],
            'timings' => [],
        ];
        
        // Test image URLs
        $startTime = microtime(true);
        $results['images'] = $this->testImages($supplierCode);
        $results['timings']['images'] = round((microtime(true) - $startTime) * 1000, 2) . 'ms';
        
        // Test search page scraping
        $startTime = microtime(true);
        $results['search_data'] = $this->testSearchPage($supplierCode);
        $results['timings']['search'] = round((microtime(true) - $startTime) * 1000, 2) . 'ms';
        
        return $results;
    }
    
    /**
     * Test image availability from Independent CDN.
     */
    private function testImages(string $supplierCode): array
    {
        $images = [];
        
        // Try different base URLs and formats
        $paths = [
            ['base' => 'https://iihealthfoods.com/cdn/shop/files/', 'format' => 'webp'],
            ['base' => 'https://iihealthfoods.com/cdn/shop/products/', 'format' => 'jpg'],
            ['base' => 'https://iihealthfoods.com/cdn/shop/files/', 'format' => 'jpg'],
        ];
        
        // Test different image variations
        for ($i = 1; $i <= 3; $i++) {
            $found = false;
            
            foreach ($paths as $path) {
                if ($found) break;
                
                $imageName = "{$supplierCode}_{$i}.{$path['format']}";
                $sizes = [165, 360, 533, 720];
                
                foreach ($sizes as $width) {
                    $url = "{$path['base']}{$imageName}?width={$width}";
                    
                    try {
                        $response = Http::timeout(5)->head($url);
                        
                        if ($response->successful()) {
                            $images[] = [
                                'url' => $url,
                                'variation' => $i,
                                'width' => $width,
                                'path' => $path['base'],
                                'format' => $path['format'],
                                'status' => 'available',
                                'content_type' => $response->header('Content-Type'),
                                'size' => $response->header('Content-Length'),
                            ];
                            $found = true;
                            break; // If this size works, skip other sizes
                        }
                    } catch (\Exception $e) {
                        // Image not found or error, continue to next
                    }
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Test scraping data from Independent search page.
     */
    private function testSearchPage(string $supplierCode): array
    {
        $searchUrl = "https://iihealthfoods.com/search?q={$supplierCode}";
        $data = [
            'url' => $searchUrl,
            'product_found' => false,
            'product_name' => null,
            'product_url' => null,
            'price' => null,
            'raw_html' => null,
            'error' => null,
        ];
        
        try {
            $response = Http::timeout(10)->get($searchUrl);
            
            if ($response->successful()) {
                $html = $response->body();
                $data['raw_html'] = substr($html, 0, 5000); // First 5000 chars for analysis
                
                // Parse HTML to extract product information
                // Using simple regex for now, could use a proper HTML parser later
                
                // Look for product title/name
                if (preg_match('/<h3[^>]*class="[^"]*card__heading[^"]*"[^>]*>.*?<a[^>]*>(.*?)<\/a>/is', $html, $matches)) {
                    $data['product_found'] = true;
                    $data['product_name'] = strip_tags(trim($matches[1]));
                }
                
                // Look for product URL
                if (preg_match('/<a[^>]*href="(\/products\/[^"]+)"[^>]*class="[^"]*full-unstyled-link[^"]*"/i', $html, $matches)) {
                    $data['product_url'] = 'https://iihealthfoods.com' . $matches[1];
                }
                
                // Look for price
                if (preg_match('/<span[^>]*class="[^"]*price-item[^"]*"[^>]*>([€£]\s*[\d,]+\.?\d*)/i', $html, $matches)) {
                    $data['price'] = trim($matches[1]);
                }
                
                // Check if no results found
                if (stripos($html, 'No results found') !== false || stripos($html, '0 results') !== false) {
                    $data['product_found'] = false;
                    $data['error'] = 'No products found for this supplier code';
                }
            } else {
                $data['error'] = "HTTP {$response->status()} error";
            }
        } catch (\Exception $e) {
            $data['error'] = $e->getMessage();
        }
        
        return $data;
    }
}