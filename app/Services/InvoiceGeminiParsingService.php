<?php

namespace App\Services;

use App\Models\AccountingSupplier;
use App\Models\InvoiceUploadFile;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InvoiceGeminiParsingService
{
    /**
     * Parse an invoice image using the configured AI provider.
     *
     * Returns data in the same format as the Python parser output
     * so it can be fed into InvoiceParsingService::processParserOutput().
     */
    public function parseImage(InvoiceUploadFile $file): array
    {
        $imageData = $this->prepareImage($file);

        if (! $imageData) {
            return [
                'success' => false,
                'errors' => [['message' => 'Could not read or process the invoice image.']],
            ];
        }

        $knownSuppliers = $this->getKnownSupplierNames();
        $provider = AiSettingsService::get('invoice_parsing', 'provider', 'mistral-ocr');

        try {
            $text = match ($provider) {
                'gemini' => $this->callGemini($imageData, $knownSuppliers),
                'mistral' => $this->callMistralVision($imageData, $knownSuppliers),
                'mistral-ocr' => $this->callMistralOcr($imageData, $knownSuppliers),
                'openai' => $this->callOpenAiCompatible($imageData, $knownSuppliers),
                default => throw new \Exception("Unknown AI provider: {$provider}"),
            };

            // Strip markdown code fencing if the model wraps it
            $text = preg_replace('/^```(?:json)?\s*\n?/m', '', $text);
            $text = preg_replace('/\n?```\s*$/m', '', $text);
            $text = trim($text);

            $data = json_decode($text, true);

            if (! $data) {
                Log::warning('AI returned invalid JSON for invoice', [
                    'file_id' => $file->id,
                    'provider' => $provider,
                    'raw_response' => substr($text, 0, 500),
                ]);

                return [
                    'success' => false,
                    'errors' => [['message' => 'AI returned invalid data. Raw: '.substr($text, 0, 200)]],
                ];
            }

            return $this->formatOutput($data, $knownSuppliers, $provider);

        } catch (\Exception $e) {
            Log::error('AI invoice parsing failed', [
                'file_id' => $file->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ─── Provider Methods ────────────────────────────────────────────

    /**
     * Google Gemini via the google-gemini-php/laravel package.
     */
    protected function callGemini(string $imageBase64, array $knownSuppliers): string
    {
        $model = AiSettingsService::get('invoice_parsing', 'model', 'gemini-2.5-flash');

        $contents = [
            $this->buildPrompt($knownSuppliers),
            new Blob(mimeType: MimeType::IMAGE_JPEG, data: $imageBase64),
        ];

        $result = Gemini::generativeModel(model: $model)->generateContent($contents);

        return $result->text();
    }

    /**
     * Mistral vision via chat/completions (pixtral models).
     */
    protected function callMistralVision(string $imageBase64, array $knownSuppliers): string
    {
        return $this->callChatCompletions($imageBase64, $knownSuppliers);
    }

    /**
     * Mistral OCR: two-step -- OCR extracts text, then chat model structures it.
     */
    protected function callMistralOcr(string $imageBase64, array $knownSuppliers): string
    {
        $apiKey = AiSettingsService::getApiKey('invoice_parsing');
        $baseUrl = AiSettingsService::get('invoice_parsing', 'base_url', 'https://api.mistral.ai/v1');
        $timeout = (int) AiSettingsService::get('invoice_parsing', 'timeout', 120);

        // Step 1: OCR -- extract text from image
        $ocrResponse = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])->timeout($timeout)->post($baseUrl.'/ocr', [
            'model' => 'mistral-ocr-latest',
            'document' => [
                'type' => 'image_url',
                'image_url' => 'data:image/jpeg;base64,'.$imageBase64,
            ],
        ]);

        if (! $ocrResponse->successful()) {
            $error = $ocrResponse->json('error.message') ?? $ocrResponse->json('message') ?? $ocrResponse->body();
            throw new \Exception('Mistral OCR error ('.$ocrResponse->status().'): '.$error);
        }

        // Extract markdown text from OCR response pages
        $pages = $ocrResponse->json('pages', []);
        $ocrText = collect($pages)->pluck('markdown')->implode("\n\n");

        if (empty(trim($ocrText))) {
            throw new \Exception('Mistral OCR returned empty text -- image may be unreadable');
        }

        Log::info('Mistral OCR text extracted', [
            'pages' => count($pages),
            'text_length' => strlen($ocrText),
        ]);

        // Step 2: Send extracted text to chat model for structured JSON extraction
        $chatModel = AiSettingsService::get('invoice_parsing', 'ocr_chat_model', 'mistral-small-latest');

        $chatResponse = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])->timeout($timeout)->post($baseUrl.'/chat/completions', [
            'model' => $chatModel,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $this->buildPrompt($knownSuppliers)
                        ."\n\n--- INVOICE TEXT (extracted via OCR) ---\n\n"
                        .$ocrText,
                ],
            ],
            'max_tokens' => 4096,
            'temperature' => 0.1,
        ]);

        if (! $chatResponse->successful()) {
            $error = $chatResponse->json('error.message') ?? $chatResponse->body();
            throw new \Exception('Mistral chat error ('.$chatResponse->status().'): '.$error);
        }

        return $chatResponse->json('choices.0.message.content', '');
    }

    /**
     * OpenAI-compatible chat/completions with vision (works for Mistral, OpenAI, etc).
     */
    protected function callOpenAiCompatible(string $imageBase64, array $knownSuppliers): string
    {
        return $this->callChatCompletions($imageBase64, $knownSuppliers);
    }

    /**
     * Shared: OpenAI-compatible chat/completions with vision.
     */
    protected function callChatCompletions(string $imageBase64, array $knownSuppliers): string
    {
        $apiKey = AiSettingsService::getApiKey('invoice_parsing');
        $model = AiSettingsService::get('invoice_parsing', 'model', 'mistral-small-latest');
        $baseUrl = AiSettingsService::get('invoice_parsing', 'base_url', 'https://api.mistral.ai/v1');
        $timeout = (int) AiSettingsService::get('invoice_parsing', 'timeout', 120);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])->timeout($timeout)->post($baseUrl.'/chat/completions', [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $this->buildPrompt($knownSuppliers)],
                        [
                            'type' => 'image_url',
                            'image_url' => ['url' => 'data:image/jpeg;base64,'.$imageBase64],
                        ],
                    ],
                ],
            ],
            'max_tokens' => 4096,
            'temperature' => 0.1,
        ]);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? $response->body();
            throw new \Exception('AI API error ('.$response->status().'): '.$error);
        }

        return $response->json('choices.0.message.content', '');
    }

    // ─── Image Processing ────────────────────────────────────────────

    protected function prepareImage(InvoiceUploadFile $file): ?string
    {
        $fullPath = $file->temp_file_path;

        if (! $fullPath || ! file_exists($fullPath)) {
            return null;
        }

        $img = @imagecreatefromjpeg($fullPath) ?: @imagecreatefrompng($fullPath);

        if (! $img) {
            return null;
        }

        $origW = imagesx($img);
        $origH = imagesy($img);
        $maxDim = (int) AiSettingsService::get('invoice_parsing', 'max_image_dimension', 1200);

        if (max($origW, $origH) > $maxDim) {
            $scale = $maxDim / max($origW, $origH);
            $newW = (int) ($origW * $scale);
            $newH = (int) ($origH * $scale);
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($img);
            $img = $resized;
        }

        $quality = (int) AiSettingsService::get('invoice_parsing', 'jpeg_quality', 80);
        ob_start();
        imagejpeg($img, null, $quality);
        $encoded = base64_encode(ob_get_clean());
        imagedestroy($img);

        return $encoded;
    }

    // ─── Supplier & Prompt ───────────────────────────────────────────

    protected function getKnownSupplierNames(): array
    {
        return AccountingSupplier::where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
    }

    protected function buildPrompt(array $knownSuppliers): string
    {
        $supplierList = implode(', ', array_map(fn ($s) => '"'.$s.'"', $knownSuppliers));

        return 'You are an invoice data extraction system. Extract all data from this invoice into JSON.'
            ."\n\n"
            .'IMPORTANT: Only extract information physically visible on the invoice. Do not guess or fabricate any values.'
            ."\n\n"
            .'SUPPLIER MATCHING: You MUST match the supplier to one of these known suppliers: ['.$supplierList.'].'
            .' Use the exact name from this list. If the invoice shows a name that is a variation of a known supplier '
            .'(e.g. "Coolfin Organic Bakery" matches "Coolfin", "Imbibe Coffee Roasters" matches "Imbibe"), '
            .'use the known supplier name. If no known supplier matches, set supplier_name to the name as it appears '
            .'on the invoice and add a warning: "Supplier not found in database: [name on invoice]".'
            ."\n\n"
            .'Return a JSON object with these exact keys:'
            ."\n\n"
            .'{'
            ."\n".'  "supplier_name": "The supplier/vendor company name",'
            ."\n".'  "invoice_number": "The invoice reference number",'
            ."\n".'  "invoice_date": "YYYY-MM-DD format",'
            ."\n".'  "total_amount": 123.45,'
            ."\n".'  "is_credit_note": false,'
            ."\n".'  "is_tax_free": false,'
            ."\n".'  "vat_breakdown": {'
            ."\n".'    "vat_23": {"net": 0.00, "vat": 0.00},'
            ."\n".'    "vat_13_5": {"net": 0.00, "vat": 0.00},'
            ."\n".'    "vat_9": {"net": 0.00, "vat": 0.00},'
            ."\n".'    "vat_0": {"net": 0.00, "vat": 0.00}'
            ."\n".'  },'
            ."\n".'  "line_items": ['
            ."\n".'    {'
            ."\n".'      "description": "Item description",'
            ."\n".'      "quantity": 1,'
            ."\n".'      "unit_price": 10.00,'
            ."\n".'      "total": 10.00,'
            ."\n".'      "vat_rate": 23'
            ."\n".'    }'
            ."\n".'  ],'
            ."\n".'  "warnings": []'
            ."\n".'}'
            ."\n\n"
            .'RULES:'
            ."\n".'- This is an Irish business. VAT rates are 23% (standard), 13.5% (reduced), 9% (second reduced), and 0% (zero/exempt).'
            ."\n".'- Assign each line item and net amount to the correct VAT rate bracket ONLY if the VAT rate is explicitly shown on the invoice.'
            ."\n".'- VAT GUESSING: If the invoice does NOT explicitly state the VAT rate for an item or total, do NOT assume 23%. '
            .'Instead put the amount under vat_0 and add a warning: "VAT rate not shown on invoice -- assigned to 0% pending review". '
            .'Only assign to a specific VAT rate (23%, 13.5%, 9%) when the invoice clearly states it.'
            ."\n".'- If the invoice shows a negative total or is labeled "credit note", set is_credit_note to true.'
            ."\n".'- If no VAT is charged at all, set is_tax_free to true and put the full amount under vat_0.'
            ."\n".'- For the total_amount field, use the gross total (including VAT).'
            ."\n".'- All monetary values must be numbers, not strings.'
            ."\n".'- If a field cannot be determined from the image, set it to null.'
            ."\n".'- Add any concerns about image quality, partial visibility, or ambiguous data to the warnings array.'
            ."\n".'- DATES: Pay special attention to handwritten dates. If the date is hard to read, written over other text, '
            .'smudged, or ambiguous (e.g. could be 04 or 01), add a warning like "Date may be incorrect: [reason]". '
            .'If you cannot read the date at all, set invoice_date to null and add a warning.'
            ."\n".'- Return ONLY the JSON object, no markdown fencing or explanation.';
    }

    // ─── Post-Processing ─────────────────────────────────────────────

    protected function matchSupplier(?string $aiName, array $knownSuppliers): array
    {
        if (! $aiName) {
            return [null, null];
        }

        $aiLower = strtolower(trim($aiName));

        // 1. Exact match (case-insensitive)
        foreach ($knownSuppliers as $known) {
            if (strtolower($known) === $aiLower) {
                return [$known, $aiName];
            }
        }

        // 2. Substring containment -- prefer longest match
        $bestMatch = null;
        $bestLen = 0;

        foreach ($knownSuppliers as $known) {
            $knownLower = strtolower($known);
            if (str_contains($aiLower, $knownLower) || str_contains($knownLower, $aiLower)) {
                if (strlen($known) > $bestLen) {
                    $bestMatch = $known;
                    $bestLen = strlen($known);
                }
            }
        }

        if ($bestMatch) {
            return [$bestMatch, $aiName];
        }

        // 3. Clean suffixes/prefixes and retry substring match
        $cleanName = preg_replace('/\s+(ltd|limited|inc|corp|plc|co\.|company|b\.?v\.?|gmbh)\.?$/i', '', $aiName);
        // Also strip leading initials like "W.K." or "J.P."
        $cleanName = preg_replace('/^([A-Z]\.?\s*)+/i', '', $cleanName);
        // Strip & and common connectors
        $cleanName = str_replace(['&', ' and '], ' ', $cleanName);
        $cleanLower = strtolower(trim($cleanName));

        if ($cleanLower !== $aiLower) {
            foreach ($knownSuppliers as $known) {
                $knownLower = strtolower($known);
                if (str_contains($cleanLower, $knownLower) || str_contains($knownLower, $cleanLower)) {
                    if (strlen($known) > $bestLen) {
                        $bestMatch = $known;
                        $bestLen = strlen($known);
                    }
                }
            }
            if ($bestMatch) {
                return [$bestMatch, $aiName];
            }
        }

        // 4. Word-based matching: extract significant words and compare
        //    e.g. "W.K. Fayle Hardware & Home Furnishings Ltd" -> ["fayle", "hardware", "home", "furnishings"]
        //    vs "Fayles Hardware" -> ["fayles", "hardware"]
        $aiWords = $this->extractSignificantWords($aiName);

        $bestWordMatch = null;
        $bestWordScore = 0;

        foreach ($knownSuppliers as $known) {
            $knownWords = $this->extractSignificantWords($known);
            if (empty($knownWords)) {
                continue;
            }

            $matchingWords = 0;
            foreach ($knownWords as $kw) {
                foreach ($aiWords as $aw) {
                    // Allow close matches (Fayle/Fayles, Menton/Mentons)
                    if ($kw === $aw || str_starts_with($kw, $aw) || str_starts_with($aw, $kw)) {
                        $matchingWords++;
                        break;
                    }
                    // Levenshtein for typos (max distance 2 for words >= 4 chars)
                    if (strlen($kw) >= 4 && strlen($aw) >= 4 && levenshtein($kw, $aw) <= 2) {
                        $matchingWords++;
                        break;
                    }
                }
            }

            // Score = fraction of known-supplier words that matched
            $score = $matchingWords / count($knownWords);

            // Require at least 50% word match and at least 1 word
            if ($score > $bestWordScore && $matchingWords >= 1 && $score >= 0.5) {
                $bestWordScore = $score;
                $bestWordMatch = $known;
            }
        }

        return [$bestWordMatch, $aiName];
    }

    /**
     * Extract significant words from a name, filtering out noise.
     */
    protected function extractSignificantWords(string $name): array
    {
        $name = strtolower($name);
        // Remove punctuation except hyphens
        $name = preg_replace('/[^\w\s-]/', ' ', $name);
        $words = preg_split('/\s+/', trim($name));

        // Filter out noise words and short tokens
        $stopWords = ['ltd', 'limited', 'inc', 'corp', 'plc', 'co', 'company', 'gmbh', 'bv',
            'the', 'and', 'of', 'for', 'in', 'at', 'a', 'an'];

        return array_values(array_filter($words, function ($w) use ($stopWords) {
            return strlen($w) >= 3 && ! in_array($w, $stopWords);
        }));
    }

    protected function formatOutput(array $data, array $knownSuppliers, string $provider): array
    {
        $warnings = $data['warnings'] ?? [];

        // Validate parsed date
        $invoiceDate = $data['invoice_date'] ?? null;
        if ($invoiceDate) {
            try {
                $parsed = \Carbon\Carbon::parse($invoiceDate);
                $now = \Carbon\Carbon::now();
                $monthsDiff = abs($parsed->diffInMonths($now));

                if ($monthsDiff > 2) {
                    $warnings[] = 'Date '.$invoiceDate.' is more than 2 months from today -- please verify this is correct.';
                }
                if ($parsed->year < $now->year - 1) {
                    $warnings[] = 'Date year '.$parsed->year.' looks incorrect -- possibly a misread handwritten date.';
                }
                if ($parsed->isAfter($now->copy()->addDays(7))) {
                    $warnings[] = 'Date '.$invoiceDate.' is in the future -- please verify.';
                }
            } catch (\Exception $e) {
                $warnings[] = 'Could not validate date: '.$invoiceDate;
            }
        }

        // Post-process supplier matching
        $supplierName = $data['supplier_name'] ?? null;
        [$matchedSupplier, $originalName] = $this->matchSupplier($supplierName, $knownSuppliers);

        if ($supplierName && ! $matchedSupplier) {
            $warnings[] = 'Supplier not found in database: '.$supplierName;
        } elseif ($matchedSupplier && $matchedSupplier !== $supplierName) {
            Log::info('AI supplier name matched to known supplier', [
                'ai_name' => $supplierName,
                'matched_name' => $matchedSupplier,
            ]);
        }

        $finalSupplierName = $matchedSupplier ?? $supplierName;

        // Confidence heuristic
        $confidence = 0.75;
        $criticalFields = ['supplier_name', 'invoice_number', 'invoice_date', 'total_amount'];
        $presentCount = 0;
        foreach ($criticalFields as $field) {
            if (! empty($data[$field])) {
                $presentCount++;
            }
        }

        $confidence = $presentCount === count($criticalFields) ? 0.80 : $confidence - ((count($criticalFields) - $presentCount) * 0.10);

        if ($supplierName && ! $matchedSupplier) {
            $confidence = min($confidence, 0.60);
        }
        if (! empty($warnings)) {
            $confidence -= 0.05;
        }
        $confidence = max(0.10, min(1.0, $confidence));

        $model = AiSettingsService::get('invoice_parsing', 'model', 'unknown');

        return [
            'success' => true,
            'data' => [
                'supplier_name' => $finalSupplierName,
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'total_amount' => $data['total_amount'] ?? null,
                'is_credit_note' => $data['is_credit_note'] ?? false,
                'is_tax_free' => $data['is_tax_free'] ?? false,
                'vat_breakdown' => $data['vat_breakdown'] ?? null,
                'line_items' => $data['line_items'] ?? [],
                'warnings' => $warnings,
            ],
            'confidence' => $confidence,
            'warnings' => $warnings,
            'metadata' => [
                'parsing_source' => $provider,
                'model' => $model,
            ],
        ];
    }
}
