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
        if (!$supplierId) {
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
        if (!$product->supplier || !$product->CODE) {
            return null;
        }

        $supplierId = (int) $product->supplier->SupplierID;
        $config = $this->getSupplierConfig($supplierId);

        if (!$config || !$config['enabled']) {
            return null;
        }

        // Replace {CODE} with the actual product code
        return str_replace('{CODE}', $product->CODE, $config['image_url']);
    }

    /**
     * Get the supplier website link for a product.
     */
    public function getSupplierWebsiteLink(Product $product): ?string
    {
        if (!$product->supplier || !$product->supplierLink) {
            return null;
        }

        $supplierId = (int) $product->supplier->SupplierID;
        $config = $this->getSupplierConfig($supplierId);

        if (!$config || !$config['enabled'] || !$product->supplierLink->SupplierCode) {
            return null;
        }

        // Replace {SUPPLIER_CODE} with the actual supplier code
        return str_replace('{SUPPLIER_CODE}', $product->supplierLink->SupplierCode, $config['website_search']);
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
        if (!$product->supplier) {
            return false;
        }

        $supplierId = (int) $product->supplier->SupplierID;
        $udeaConfig = $this->config['udea'] ?? null;

        return $udeaConfig && in_array($supplierId, $udeaConfig['supplier_ids']);
    }
}