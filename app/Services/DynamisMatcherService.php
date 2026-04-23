<?php

namespace App\Services;

use App\Models\DynamisProductLink;
use App\Models\Product;
use App\Models\ProductsCat;
use Illuminate\Support\Collection;

/**
 * Matches parsed Dynamis items to till-visible F&V products, in priority order:
 *   1. Persisted link in dynamis_product_links (auto-confirmed from a prior import).
 *   2. Token-overlap fuzzy match against till-visible Product.NAME (threshold 0.75).
 *
 * AI is NOT invoked here — the controller exposes a separate "Suggest with AI"
 * button that calls DynamisAiSuggesterService on the remaining unmatched items.
 *
 * Input items come from DynamisXlsxParserService::parse() (items[] array).
 */
class DynamisMatcherService
{
    private const FV_CATEGORIES = ['SUB1', 'SUB2', 'SUB3'];

    private const AUTO_MATCH_THRESHOLD = 0.75;

    private const SUGGESTION_LIMIT = 3;

    /**
     * Tokens that add no discriminating signal for F&V names.
     */
    private const STOPWORDS = [
        'bio', 'organic', 'fairtrade', 'kg', 'g', 'gr', 'gm', 'lb', 'oz',
        'pot', 'pots', 'potted', 'bunch', 'bunched', 'pack', 'packs', 'packed',
        'piece', 'pieces', 'pc', 'pcs', 'each', 'loose', 'prepacked',
        'the', 'and', 'of', 'in', 'by', 'a', 'an', 'small', 'large', 'medium',
        'mini', 'maxi', 'baby', 'jumbo',
    ];

    /**
     * Annotate each parsed item with match data.
     *
     * Returns the same items plus match_status / product_id / product_code /
     * product_name / confidence / suggestions[].
     */
    public function matchItems(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $candidates = $this->loadTillProducts();
        $candidateIndex = $this->indexCandidates($candidates);

        $codes = array_values(array_filter(array_map(fn ($i) => $i['code'] ?? null, $items)));
        $savedLinks = DynamisProductLink::whereIn('dynamis_code', $codes)
            ->get()
            ->keyBy('dynamis_code');

        $visibleIds = $candidateIndex['by_id']->keys()->all();
        $results = [];

        foreach ($items as $item) {
            $result = $item;
            $code = $item['code'] ?? '';
            $description = $item['product'] ?? '';

            $link = $savedLinks->get($code);

            if ($link && $candidateIndex['by_id']->has($link->pos_product_id)) {
                $product = $candidateIndex['by_id'][$link->pos_product_id];
                $result['match_status'] = 'matched_saved';
                $result['product_id'] = (string) $product->ID;
                $result['product_code'] = (string) $product->CODE;
                $result['product_name'] = (string) ($product->NAME ?? $product->DISPLAY);
                $result['confidence'] = $link->confidence !== null ? (float) $link->confidence : 1.0;
                $result['suggestions'] = [];
                $results[] = $result;

                continue;
            }

            $scored = $this->scoreCandidates($description, $candidateIndex['normalized']);
            $top = array_slice($scored, 0, self::SUGGESTION_LIMIT);
            $best = $scored[0] ?? null;

            if ($best && $best['score'] >= self::AUTO_MATCH_THRESHOLD) {
                $product = $candidateIndex['by_id'][$best['id']];
                $result['match_status'] = 'matched_fuzzy';
                $result['product_id'] = (string) $product->ID;
                $result['product_code'] = (string) $product->CODE;
                $result['product_name'] = (string) ($product->NAME ?? $product->DISPLAY);
                $result['confidence'] = round($best['score'], 3);
            } else {
                $result['match_status'] = 'unmatched';
                $result['product_id'] = null;
                $result['product_code'] = null;
                $result['product_name'] = null;
                $result['confidence'] = $best ? round($best['score'], 3) : 0.0;
            }

            $result['suggestions'] = array_map(function ($row) use ($candidateIndex) {
                $p = $candidateIndex['by_id'][$row['id']];

                return [
                    'product_id' => (string) $p->ID,
                    'product_code' => (string) $p->CODE,
                    'product_name' => (string) ($p->NAME ?? $p->DISPLAY),
                    'score' => round($row['score'], 3),
                ];
            }, $top);

            $results[] = $result;
        }

        return $results;
    }

    /**
     * All till-visible F&V products (joining PRODUCTS_CAT and PRODUCTS).
     */
    public function loadTillProducts(): Collection
    {
        $visibleIds = ProductsCat::pluck('PRODUCT')->all();

        if (empty($visibleIds)) {
            return collect();
        }

        return Product::whereIn('CATEGORY', self::FV_CATEGORIES)
            ->whereIn('ID', $visibleIds)
            ->get(['ID', 'CODE', 'NAME', 'DISPLAY', 'CATEGORY']);
    }

    /**
     * Precompute normalized token sets for every candidate product.
     */
    private function indexCandidates(Collection $products): array
    {
        $byId = $products->keyBy('ID');
        $normalized = [];

        foreach ($products as $p) {
            $normalized[] = [
                'id' => (string) $p->ID,
                'tokens' => $this->normalize((string) ($p->NAME ?? '')),
            ];
        }

        return [
            'by_id' => $byId,
            'normalized' => $normalized,
        ];
    }

    /**
     * Rank candidates by token overlap against the supplier description.
     * Returns [{id, score}, ...] sorted desc.
     */
    private function scoreCandidates(string $description, array $normalizedCandidates): array
    {
        $aTokens = $this->normalize($description);

        if (empty($aTokens)) {
            return [];
        }

        $scored = [];

        foreach ($normalizedCandidates as $cand) {
            $bTokens = $cand['tokens'];
            if (empty($bTokens)) {
                continue;
            }

            $intersection = count(array_intersect($aTokens, $bTokens));
            if ($intersection === 0) {
                continue;
            }

            $union = count(array_unique(array_merge($aTokens, $bTokens)));
            $jaccard = $union > 0 ? $intersection / $union : 0.0;

            $firstA = $aTokens[0];
            $headBoost = in_array($firstA, $bTokens, true) ? 0.15 : 0.0;

            $score = min(1.0, $jaccard + $headBoost);

            $scored[] = [
                'id' => $cand['id'],
                'score' => $score,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $scored;
    }

    /**
     * Uppercase → strip non-letters → split → drop stopwords → singularize.
     */
    public function normalize(string $text): array
    {
        $text = strtoupper($text);
        $text = preg_replace('/<[^>]+>/', ' ', $text);
        $text = preg_replace('/[^A-Z\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', trim($text));

        if ($text === '') {
            return [];
        }

        $stop = array_map('strtoupper', self::STOPWORDS);
        $out = [];

        foreach (explode(' ', $text) as $tok) {
            if (strlen($tok) < 2) {
                continue;
            }
            if (in_array($tok, $stop, true)) {
                continue;
            }
            $out[] = $this->singularize($tok);
        }

        return array_values(array_unique($out));
    }

    private function singularize(string $token): string
    {
        if (strlen($token) > 3 && substr($token, -3) === 'IES') {
            return substr($token, 0, -3).'Y';
        }
        if (strlen($token) > 2 && substr($token, -1) === 'S' && substr($token, -2) !== 'SS') {
            return substr($token, 0, -1);
        }

        return $token;
    }
}
