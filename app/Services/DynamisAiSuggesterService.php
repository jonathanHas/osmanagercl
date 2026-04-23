<?php

namespace App\Services;

use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bulk AI pass for Dynamis items that DynamisMatcherService couldn't auto-match.
 *
 * Usage: pass in the still-unmatched annotated items plus the same till-product
 * collection the matcher was built against. Returns the items with AI-suggested
 * product_id / product_name / confidence attached (match_status = 'matched_ai').
 *
 * Provider/model/API key resolved via AiSettingsService::get('dynamis_matcher', …),
 * which falls back to invoice_parsing when unset — see AiSettingsService::get().
 */
class DynamisAiSuggesterService
{
    private const FEATURE_KEY = 'dynamis_matcher';

    /**
     * Reasonable upper bound to keep prompts within token limits. If more
     * unmatched items arrive, they are chunked across multiple calls.
     */
    private const ITEMS_PER_CALL = 40;

    public function suggest(array $unmatchedItems, Collection $candidates): array
    {
        if (empty($unmatchedItems) || $candidates->isEmpty()) {
            return $unmatchedItems;
        }

        $candidateIndex = $candidates->keyBy('ID');

        $chunks = array_chunk($unmatchedItems, self::ITEMS_PER_CALL);
        $merged = [];

        foreach ($chunks as $chunk) {
            try {
                $suggestions = $this->callAi($chunk, $candidates);
            } catch (\Throwable $e) {
                Log::warning('Dynamis AI suggester failed', [
                    'error' => $e->getMessage(),
                    'chunk_size' => count($chunk),
                ]);
                $suggestions = [];
            }

            foreach ($chunk as $item) {
                $code = $item['code'] ?? '';
                $suggestion = $suggestions[$code] ?? null;

                if ($suggestion && $candidateIndex->has($suggestion['product_id'])) {
                    $product = $candidateIndex[$suggestion['product_id']];
                    $item['match_status'] = 'matched_ai';
                    $item['product_id'] = (string) $product->ID;
                    $item['product_code'] = (string) $product->CODE;
                    $item['product_name'] = (string) ($product->NAME ?? $product->DISPLAY);
                    $item['confidence'] = (float) $suggestion['confidence'];
                    $item['ai_model'] = $this->modelLabel();
                }

                $merged[] = $item;
            }
        }

        return $merged;
    }

    /**
     * Indexed by dynamis_code → ['product_id' => ..., 'confidence' => ...].
     */
    private function callAi(array $items, Collection $candidates): array
    {
        $provider = (string) AiSettingsService::get(self::FEATURE_KEY, 'provider', 'mistral');
        $prompt = $this->buildPrompt($items, $candidates);

        $raw = match (true) {
            str_starts_with($provider, 'gemini') => $this->callGemini($prompt),
            default => $this->callChatCompletions($prompt),
        };

        return $this->parseResponse($raw);
    }

    private function callGemini(string $prompt): string
    {
        $model = (string) AiSettingsService::get(self::FEATURE_KEY, 'model', 'gemini-2.5-flash');
        $result = Gemini::generativeModel(model: $model)->generateContent($prompt);

        return $result->text();
    }

    private function callChatCompletions(string $prompt): string
    {
        $apiKey = AiSettingsService::getApiKey(self::FEATURE_KEY);
        $model = (string) AiSettingsService::get(self::FEATURE_KEY, 'model', 'mistral-small-latest');
        $baseUrl = (string) AiSettingsService::get(self::FEATURE_KEY, 'base_url', 'https://api.mistral.ai/v1');
        $timeout = (int) AiSettingsService::get(self::FEATURE_KEY, 'timeout', 60);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])->timeout($timeout)->post($baseUrl.'/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 2048,
            'temperature' => 0.1,
        ]);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException('AI API error ('.$response->status().'): '.$error);
        }

        return (string) $response->json('choices.0.message.content', '');
    }

    private function buildPrompt(array $items, Collection $candidates): string
    {
        $supplierLines = [];
        foreach ($items as $item) {
            $supplierLines[] = sprintf(
                '  - %s  ||  %s',
                $item['code'] ?? '',
                $item['product'] ?? ''
            );
        }

        $tillLines = [];
        foreach ($candidates as $p) {
            $tillLines[] = sprintf(
                '  - %s  ||  %s',
                (string) $p->ID,
                (string) ($p->NAME ?? $p->DISPLAY ?? '')
            );
        }

        return <<<PROMPT
You are matching supplier delivery lines (from Dynamis, a French fruit-and-veg wholesaler) to products in a retail till catalogue.

Rules:
- Only match when the supplier line and till product clearly refer to the same variety/type. Matching "Apple Jazz" to "Apples Elstar" is WRONG — different varieties.
- "BIO" / "organic" markers can be ignored; match on the produce type and variety.
- If no good match exists, return null.
- Confidence is a number 0.0–1.0.

SUPPLIER LINES (format: supplier_code || description):
{$this->joinLines($supplierLines)}

TILL PRODUCTS (format: product_id || name):
{$this->joinLines($tillLines)}

Return ONLY a JSON array, no prose. One object per supplier line, using the supplier_code as the key:
[
  {"supplier_code": "POM0481", "product_id": "1070", "confidence": 0.92},
  {"supplier_code": "POM1440", "product_id": null, "confidence": 0.0}
]
PROMPT;
    }

    private function joinLines(array $lines): string
    {
        return implode("\n", $lines);
    }

    /**
     * @return array<string, array{product_id: string, confidence: float}>
     */
    private function parseResponse(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        if (preg_match('/```(?:json)?\s*(.*?)```/s', $raw, $m)) {
            $raw = trim($m[1]);
        }

        if (preg_match('/\[.*\]/s', $raw, $m)) {
            $raw = $m[0];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            Log::warning('Dynamis AI suggester: non-array response', ['raw' => $raw]);

            return [];
        }

        $out = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = $row['supplier_code'] ?? null;
            $pid = $row['product_id'] ?? null;
            if (! $code || ! $pid) {
                continue;
            }

            $out[(string) $code] = [
                'product_id' => (string) $pid,
                'confidence' => (float) ($row['confidence'] ?? 0.0),
            ];
        }

        return $out;
    }

    private function modelLabel(): string
    {
        $provider = (string) AiSettingsService::get(self::FEATURE_KEY, 'provider', 'mistral');
        $model = (string) AiSettingsService::get(self::FEATURE_KEY, 'model', 'mistral-small-latest');

        return $provider.'/'.$model;
    }
}
