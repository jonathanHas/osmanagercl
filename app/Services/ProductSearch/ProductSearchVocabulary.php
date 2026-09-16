<?php

namespace App\Services\ProductSearch;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * "Did you mean" support for product search.
 *
 * The vocabulary is every distinct word (≥3 chars) across all product names,
 * built from a single pluck of PRODUCTS.NAME and cached for an hour. A typo'd
 * search token is corrected to the closest vocabulary word by Levenshtein
 * distance; the search service only asks for corrections when the exact
 * tokenised query returned nothing.
 */
class ProductSearchVocabulary
{
    public const CACHE_KEY = 'product-search:vocab';

    /** @var array<string, int>|null */
    private ?array $words = null;

    /**
     * Distinct words across product names, keyed by word with frequency as value.
     *
     * @return array<string, int>
     */
    public function words(): array
    {
        return $this->words ??= Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
            $words = [];

            foreach (Product::query()->select('NAME')->pluck('NAME') as $name) {
                foreach (preg_split('/[^a-z0-9]+/', strtolower((string) $name)) as $word) {
                    if (strlen($word) < 3) {
                        continue;
                    }
                    $words[$word] = ($words[$word] ?? 0) + 1;
                }
            }

            return $words;
        });
    }

    /**
     * Return the closest vocabulary word for a token, or null when the token
     * is too short, numeric (barcodes are never "corrected"), already a
     * substring of a real word, or has no close-enough neighbour.
     */
    public function correct(string $token): ?string
    {
        $token = strtolower(trim($token));
        $length = strlen($token);

        if ($length < 4 || ctype_digit($token)) {
            return null;
        }

        $words = $this->words();

        if (isset($words[$token])) {
            return null;
        }

        // A token that is the start/middle of a real word is a partial word, not a typo.
        foreach ($words as $word => $frequency) {
            if (str_contains($word, $token)) {
                return null;
            }
        }

        $maxDistance = $length <= 6 ? 1 : 2;
        $best = null;
        $bestDistance = PHP_INT_MAX;
        $bestFrequency = 0;

        foreach ($words as $word => $frequency) {
            if (abs(strlen($word) - $length) > 2) {
                continue;
            }

            $distance = $this->distance($token, $word);

            if ($distance > $maxDistance) {
                continue;
            }

            if ($distance < $bestDistance || ($distance === $bestDistance && $frequency > $bestFrequency)) {
                $best = $word;
                $bestDistance = $distance;
                $bestFrequency = $frequency;
            }
        }

        return $best;
    }

    /**
     * Levenshtein distance, with an adjacent transposition ("friut" → "fruit")
     * counted as a single edit rather than two.
     */
    protected function distance(string $a, string $b): int
    {
        $distance = levenshtein($a, $b);

        if ($distance === 2 && strlen($a) === strlen($b)) {
            $diff = array_keys(array_diff_assoc(str_split($a), str_split($b)));

            if (count($diff) === 2 && $diff[1] === $diff[0] + 1
                && $a[$diff[0]] === $b[$diff[1]] && $a[$diff[1]] === $b[$diff[0]]) {
                return 1;
            }
        }

        return $distance;
    }

    /**
     * Drop the cached vocabulary (after a product is created or renamed).
     */
    public function forget(): void
    {
        $this->words = null;
        Cache::forget(self::CACHE_KEY);
    }
}
