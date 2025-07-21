<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Config;

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
    public function hasExternalIntegration(?int $supplierId): bool
    {
        if (! $supplierId) {
            return false;
        }

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
            if (! $product->supplier || ! $product->CODE) {
                return null;
            }

            $supplierId = (int) $product->supplier->SupplierID;
            return $this->getExternalImageUrlByBarcode($supplierId, $product->CODE);
        } catch (\Exception $e) {
            // Log error but don't expose it to users
            \Log::error('Error generating external image URL: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Get the external image URL using supplier ID and barcode directly.
     */
    public function getExternalImageUrlByBarcode(int $supplierId, ?string $barcode): ?string
    {
        try {
            if (!$barcode) {
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
            if (!$this->isValidImageUrl($imageUrl)) {
                \Log::warning('Generated image URL does not appear to be a valid image URL: ' . $imageUrl);
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
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Check if URL ends with common image extensions
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));
        
        return isset($pathInfo['extension']) && 
               in_array(strtolower($pathInfo['extension']), $imageExtensions);
    }
}
