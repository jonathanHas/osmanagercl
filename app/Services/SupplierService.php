<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SupplierImageCache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class SupplierService
{
    /**
     * The supplier configuration.
     */
    protected array $config;

    /**
     * Create a new supplier service instance.
     */
    public function __construct()
    {
        $this->config = Config::get('suppliers.external_links', []);
    }

    /**
     * Check if a supplier has external integration enabled.
     */
    public function hasExternalIntegration(int|string|null $supplierId): bool
    {
        if (is_string($supplierId)) {
            $supplierId = trim($supplierId);
        }

        if ($supplierId === null || $supplierId === '') {
            return false;
        }

        $normalizedId = filter_var($supplierId, FILTER_VALIDATE_INT);

        if ($normalizedId === false || $normalizedId === 0) {
            return false;
        }

        $supplierId = (int) $normalizedId;

        foreach ($this->config as $supplier => $settings) {
            if ($settings['enabled'] && in_array($supplierId, $settings['supplier_ids'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the external image URL for a product.
     */
    public function getExternalImageUrl(Product $product): ?string
    {
        try {
            if (! $product->supplier) {
                return null;
            }

            $supplierId = (int) $product->supplier->SupplierID;
            $config = $this->getSupplierConfig($supplierId);

            if (! $config || ! $config['enabled'] || empty($config['image_url'])) {
                return null;
            }

            // For suppliers using {SUPPLIER_CODE}, check cache first
            if (str_contains($config['image_url'], '{SUPPLIER_CODE}')) {
                if (! $product->supplierLink || ! $product->supplierLink->SupplierCode) {
                    return null;
                }

                $supplierCode = $product->supplierLink->SupplierCode;

                // Check cache
                $cached = SupplierImageCache::where('supplier_code', $supplierCode)
                    ->where('supplier_id', $supplierId)
                    ->first();

                if ($cached) {
                    return $cached->not_found ? null : $cached->image_url;
                }

                // No cache - return template-based URL (browser fallback chain will try variants)
                $code = preg_replace('/[^a-zA-Z0-9_-]/', '', $supplierCode);
                $imageUrl = str_replace('{SUPPLIER_CODE}', $code, $config['image_url']);
            } else {
                // Use barcode (e.g., Udea)
                if (! $product->CODE) {
                    return null;
                }

                return $this->getExternalImageUrlByBarcode($supplierId, $product->CODE);
            }

            if (! $this->isValidImageUrl($imageUrl)) {
                \Log::warning('Generated image URL does not appear to be a valid image URL: '.$imageUrl);

                return null;
            }

            return $imageUrl;
        } catch (\Exception $e) {
            \Log::error('Error generating external image URL: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Get fallback image URLs for a product (tried if primary URL fails).
     *
     * @return string[]
     */
    public function getExternalImageFallbacks(Product $product): array
    {
        try {
            if (! $product->supplier) {
                return [];
            }

            $supplierId = (int) $product->supplier->SupplierID;
            $config = $this->getSupplierConfig($supplierId);

            if (! $config || ! $config['enabled'] || empty($config['image_url_fallbacks'])) {
                return [];
            }

            // Determine the code to substitute
            if (str_contains($config['image_url'], '{SUPPLIER_CODE}')) {
                if (! $product->supplierLink || ! $product->supplierLink->SupplierCode) {
                    return [];
                }
                $code = preg_replace('/[^a-zA-Z0-9_-]/', '', $product->supplierLink->SupplierCode);
                $placeholder = '{SUPPLIER_CODE}';
            } else {
                if (! $product->CODE) {
                    return [];
                }
                $code = preg_replace('/[^a-zA-Z0-9_-]/', '', $product->CODE);
                $placeholder = '{CODE}';
            }

            $urls = [];
            foreach ($config['image_url_fallbacks'] as $template) {
                $url = str_replace($placeholder, $code, $template);
                if ($this->isValidImageUrl($url)) {
                    $urls[] = $url;
                }
            }

            return $urls;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get the external image URL using supplier ID and barcode directly.
     */
    public function getExternalImageUrlByBarcode(int $supplierId, ?string $barcode): ?string
    {
        try {
            if (! $barcode) {
                return null;
            }

            $config = $this->getSupplierConfig($supplierId);

            if (! $config || ! $config['enabled']) {
                return null;
            }

            // Sanitize the barcode to prevent URL injection
            $code = preg_replace('/[^a-zA-Z0-9_-]/', '', $barcode);

            // Replace {CODE} with the actual barcode
            $imageUrl = str_replace('{CODE}', $code, $config['image_url']);

            // Validate that this looks like an image URL
            if (! $this->isValidImageUrl($imageUrl)) {
                \Log::warning('Generated image URL does not appear to be a valid image URL: '.$imageUrl);

                return null;
            }

            return $imageUrl;
        } catch (\Exception $e) {
            // Log error but don't expose it to users
            \Log::error('Error generating external image URL by barcode: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Get the external image URL using supplier ID and supplier code directly.
     * Used for items that have a supplier code but no product record (e.g., legacy deliveries, new products).
     */
    public function getExternalImageUrlBySupplierCode(int $supplierId, ?string $supplierCode): ?string
    {
        try {
            if (! $supplierCode) {
                return null;
            }

            $config = $this->getSupplierConfig($supplierId);

            if (! $config || ! $config['enabled'] || empty($config['image_url'])) {
                return null;
            }

            $code = preg_replace('/[^a-zA-Z0-9_-]/', '', $supplierCode);

            // Check cache first
            $cached = SupplierImageCache::where('supplier_code', $supplierCode)
                ->where('supplier_id', $supplierId)
                ->first();

            if ($cached) {
                return $cached->not_found ? null : $cached->image_url;
            }

            if (str_contains($config['image_url'], '{SUPPLIER_CODE}')) {
                // Supplier uses supplier code directly in image URLs (e.g., Independent)
                $imageUrl = str_replace('{SUPPLIER_CODE}', $code, $config['image_url']);
            } else {
                // Supplier uses barcode in image URLs (e.g., Udea) — look up barcode from supplier_link
                $barcode = \Illuminate\Support\Facades\DB::connection('pos')
                    ->table('supplier_link')
                    ->where('SupplierID', $supplierId)
                    ->where('SupplierCode', $supplierCode)
                    ->value('Barcode');

                // If not found under exact supplier ID, try all IDs for this supplier group
                if (! $barcode) {
                    $allIds = $config['supplier_ids'] ?? [];
                    $barcode = \Illuminate\Support\Facades\DB::connection('pos')
                        ->table('supplier_link')
                        ->whereIn('SupplierID', $allIds)
                        ->where('SupplierCode', $supplierCode)
                        ->value('Barcode');
                }

                if (! $barcode) {
                    return null;
                }

                return $this->getExternalImageUrlByBarcode($supplierId, $barcode);
            }

            if (! $this->isValidImageUrl($imageUrl)) {
                return null;
            }

            return $imageUrl;
        } catch (\Exception $e) {
            \Log::error('Error generating external image URL by supplier code: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Get the supplier website link for a product.
     */
    public function getSupplierWebsiteLink(Product $product): ?string
    {
        try {
            if (! $product->supplier || ! $product->supplierLink) {
                return null;
            }

            $supplierId = (int) $product->supplier->SupplierID;
            $config = $this->getSupplierConfig($supplierId);

            if (! $config || ! $config['enabled'] || ! $product->supplierLink->SupplierCode) {
                return null;
            }

            // URL encode the supplier code to handle special characters
            $supplierCode = urlencode($product->supplierLink->SupplierCode);

            // Replace {SUPPLIER_CODE} with the actual supplier code
            return str_replace('{SUPPLIER_CODE}', $supplierCode, $config['website_search']);
        } catch (\Exception $e) {
            // Log error but don't expose it to users
            \Log::error('Error generating supplier website link: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Resolve and cache the image URL for a supplier code by scraping the supplier's website.
     */
    public function resolveAndCacheImageUrl(string $supplierCode, int $supplierId): ?string
    {
        // Check cache first (including not_found)
        $cached = SupplierImageCache::where('supplier_code', $supplierCode)
            ->where('supplier_id', $supplierId)
            ->first();

        if ($cached) {
            return $cached->not_found ? null : $cached->image_url;
        }

        $config = $this->getSupplierConfig($supplierId);
        if (! $config || ! $config['enabled']) {
            return null;
        }

        $code = preg_replace('/[^a-zA-Z0-9_-]/', '', $supplierCode);
        $imageUrl = null;

        // Step 1: Try URL patterns with HEAD requests
        $templates = [$config['image_url'] ?? null, ...($config['image_url_fallbacks'] ?? [])];
        $placeholder = str_contains($config['image_url'] ?? '', '{SUPPLIER_CODE}') ? '{SUPPLIER_CODE}' : '{CODE}';

        foreach (array_filter($templates) as $template) {
            $url = str_replace($placeholder, $code, $template);
            try {
                $response = Http::timeout(5)->head($url);
                if ($response->successful()) {
                    $imageUrl = $url;
                    break;
                }
            } catch (\Exception $e) {
                // Continue to next
            }
        }

        // Step 2: If no pattern matched, scrape the search page
        if (! $imageUrl && ! empty($config['website_search'])) {
            $imageUrl = $this->scrapeImageFromSearch($code, $config);
        }

        // Cache the result
        SupplierImageCache::updateOrCreate(
            ['supplier_code' => $supplierCode, 'supplier_id' => $supplierId],
            [
                'image_url' => $imageUrl,
                'not_found' => $imageUrl === null,
            ]
        );

        return $imageUrl;
    }

    /**
     * Scrape the image URL from the supplier's search page.
     */
    protected function scrapeImageFromSearch(string $code, array $config): ?string
    {
        try {
            $searchUrl = str_replace('{SUPPLIER_CODE}', urlencode($code), $config['website_search']);
            $response = Http::timeout(10)->get($searchUrl);

            if (! $response->successful()) {
                return null;
            }

            // Decode HTML entities so &amp; becomes & in URLs
            $html = html_entity_decode($response->body(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $domain = parse_url($config['website_search'], PHP_URL_HOST);
            $imageExtensions = ['webp', 'png', 'jpg', 'jpeg'];

            // Strategy 1: Find srcset containing the supplier code
            if (preg_match('/srcset="([^"]*'.preg_quote($code, '/').'[^"]*)"/', $html, $matches)) {
                $url = $this->extractBestUrlFromSrcset($matches[1], $domain, $imageExtensions);
                if ($url) {
                    return $url;
                }
            }

            // Strategy 2: Find any product image srcset on the page (for custom filenames)
            // Skip logos and site assets by filtering out known non-product patterns
            if (preg_match_all('/srcset="((?:\/\/|https?:\/\/)'.preg_quote($domain, '/').'\/cdn\/shop\/(?:files|products)\/[^"]+)"/', $html, $allSrcsets)) {
                foreach ($allSrcsets[1] as $srcset) {
                    if (preg_match('/logo|icon|badge|banner/i', $srcset)) {
                        continue;
                    }
                    $url = $this->extractBestUrlFromSrcset($srcset, $domain, $imageExtensions);
                    if ($url) {
                        return $url;
                    }
                }
            }

            // Strategy 3: Find img src pointing to product images (not JS/CSS/logos)
            if ($domain && preg_match_all('/src="((?:https?:)?\/\/'.preg_quote($domain, '/').'\/cdn\/shop\/(?:files|products)\/[^"]+)"/', $html, $allSrcs)) {
                foreach ($allSrcs[1] as $src) {
                    if (preg_match('/logo|icon|badge|banner/i', $src)) {
                        continue;
                    }
                    // Only accept image file extensions
                    $pathWithoutQuery = parse_url($src, PHP_URL_PATH) ?? '';
                    $ext = strtolower(pathinfo($pathWithoutQuery, PATHINFO_EXTENSION));
                    if (in_array($ext, $imageExtensions)) {
                        $url = str_starts_with($src, 'http') ? $src : 'https:'.$src;
                        // Normalize to width=533 for consistency
                        $url = preg_replace('/width=\d+/', 'width=533', $url);

                        return $url;
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            \Log::warning('Failed to scrape image from search page: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Extract the best image URL from a srcset string, preferring width=533.
     */
    protected function extractBestUrlFromSrcset(string $srcset, ?string $domain, array $imageExtensions): ?string
    {
        // Parse srcset into individual URLs
        preg_match_all('/(?:https?:)?\/\/[^\s,]+/', $srcset, $urls);

        if (empty($urls[0])) {
            return null;
        }

        $bestUrl = null;
        $bestWidth = 0;

        foreach ($urls[0] as $url) {
            // Only accept image file extensions
            $pathWithoutQuery = parse_url($url, PHP_URL_PATH) ?? '';
            $ext = strtolower(pathinfo($pathWithoutQuery, PATHINFO_EXTENSION));
            if (! in_array($ext, $imageExtensions)) {
                continue;
            }

            // Extract width parameter
            $width = 0;
            if (preg_match('/width=(\d+)/', $url, $wMatch)) {
                $width = (int) $wMatch[1];
            }

            // Prefer width closest to 533 (but at least 300)
            if ($width >= 300 && $width <= 600 && ($bestUrl === null || abs($width - 533) < abs($bestWidth - 533))) {
                $bestUrl = $url;
                $bestWidth = $width;
            } elseif ($bestUrl === null && $width > 0) {
                $bestUrl = $url;
                $bestWidth = $width;
            }
        }

        if ($bestUrl) {
            return str_starts_with($bestUrl, 'http') ? $bestUrl : 'https:'.$bestUrl;
        }

        return null;
    }

    /**
     * Get configuration for a specific supplier ID.
     */
    protected function getSupplierConfig(int $supplierId): ?array
    {
        foreach ($this->config as $supplier => $settings) {
            if (in_array($supplierId, $settings['supplier_ids'])) {
                return $settings;
            }
        }

        return null;
    }

    /**
     * Check if a supplier uses {SUPPLIER_CODE} in its image URL template (vs {CODE}/barcode).
     */
    public function usesSupplierCodeImages(int $supplierId): bool
    {
        $config = $this->getSupplierConfig($supplierId);

        return $config && ! empty($config['image_url']) && str_contains($config['image_url'], '{SUPPLIER_CODE}');
    }

    /**
     * Get the display name for a supplier.
     */
    public function getSupplierDisplayName(int $supplierId): ?string
    {
        $config = $this->getSupplierConfig($supplierId);

        return $config ? $config['display_name'] : null;
    }

    /**
     * Check if a product is from Udea (convenience method).
     */
    public function isUdeaProduct(Product $product): bool
    {
        if (! $product->supplier) {
            return false;
        }

        $supplierId = (int) $product->supplier->SupplierID;
        $udeaConfig = $this->config['udea'] ?? null;

        return $udeaConfig && in_array($supplierId, $udeaConfig['supplier_ids']);
    }

    /**
     * Validate that a URL appears to be a valid image URL.
     */
    protected function isValidImageUrl(string $url): bool
    {
        // Check if URL is well-formed
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check if URL ends with common image extensions
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));

        return isset($pathInfo['extension']) &&
               in_array(strtolower($pathInfo['extension']), $imageExtensions);
    }
}
