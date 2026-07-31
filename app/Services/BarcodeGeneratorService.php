<?php

namespace App\Services;

use App\Models\Product;

/**
 * Generates internal product barcodes (PRODUCTS.CODE).
 *
 * Barcode uniqueness is a global invariant across PRODUCTS.CODE, so every
 * caller has to go through this one implementation - two copies would drift
 * and the failure mode is a duplicate code that breaks scanning at the till.
 */
class BarcodeGeneratorService
{
    /**
     * Get the next available barcode for a category, falling back to a generic
     * code when the category has no configured range.
     */
    public function nextForCategory(?string $categoryId): string
    {
        if (! $categoryId) {
            return $this->nextGeneric();
        }

        return $this->nextForConfiguredCategory($categoryId) ?? $this->nextGeneric();
    }

    /**
     * Get the next available barcode for a specific category.
     * Uses configuration-driven approach to support multiple categories with different patterns.
     * Checks ALL products across ALL categories since barcodes are globally unique.
     */
    public function nextForConfiguredCategory(string $categoryId): ?string
    {
        $config = config('barcode_patterns.categories.'.$categoryId);

        // Return null if category is not configured for barcode suggestions
        if (! $config) {
            return null;
        }

        $settings = config('barcode_patterns.settings');

        // Get existing codes for this category within internal code range
        $categoryCodes = Product::where('CATEGORY', $categoryId)
            ->pluck('CODE')
            ->filter(fn ($code) => is_numeric($code) && (int) $code <= $settings['max_internal_code'])
            ->map(fn ($code) => (int) $code)
            ->sort()
            ->values()
            ->toArray();

        // If no codes exist, start at the beginning of the first range
        if (empty($categoryCodes)) {
            $firstRange = $config['ranges'][0];

            return (string) $firstRange[0];
        }

        $min = min($categoryCodes);
        $max = max($categoryCodes);

        if ($config['priority'] === 'fill_gaps') {
            // Fill gaps in existing range first
            for ($i = $min; $i <= $max; $i++) {
                if (! in_array($i, $categoryCodes) && $this->isCodeInRange($i, $config['ranges']) && ! Product::where('CODE', (string) $i)->exists()) {
                    return (string) $i;
                }
            }
        }

        // No gaps found or priority is increment - find next available after highest
        $searchStart = $max + 1;
        $searchLimit = $searchStart + $settings['max_search_range'];

        for ($i = $searchStart; $i <= $searchLimit; $i++) {
            if ($this->isCodeInRange($i, $config['ranges']) && ! Product::where('CODE', (string) $i)->exists()) {
                return (string) $i;
            }
        }

        // Fallback: return next increment (might be outside configured ranges)
        return (string) ($max + 1);
    }

    /**
     * Find the next available numeric code for categories that have no
     * configured range. Codes start at the generic_start setting (kept above
     * every configured range) and are globally unique across PRODUCTS.CODE.
     */
    public function nextGeneric(): string
    {
        $settings = config('barcode_patterns.settings');
        $start = $settings['generic_start'];
        $max = $settings['max_internal_code'];

        // Existing numeric codes at/above the generic start, as a lookup set.
        $used = Product::pluck('CODE')
            ->filter(fn ($code) => is_numeric($code) && (int) $code >= $start && (int) $code <= $max)
            ->map(fn ($code) => (int) $code)
            ->flip();

        // First unused code at/after the generic start.
        for ($i = $start; $i <= $max; $i++) {
            if (! $used->has($i)) {
                return (string) $i;
            }
        }

        // Fallback (band exhausted): one past the start.
        return (string) $start;
    }

    /**
     * Check if a code falls within any of the configured ranges for a category.
     */
    public function isCodeInRange(int $code, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($code >= $range[0] && $code <= $range[1]) {
                return true;
            }
        }

        return false;
    }
}
