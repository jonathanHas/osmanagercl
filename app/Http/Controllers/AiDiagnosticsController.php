<?php

namespace App\Http\Controllers;

use App\Services\AiSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiDiagnosticsController extends Controller
{
    public function index()
    {
        $features = AiSettingsService::getFeatures();
        $providers = AiSettingsService::getAvailableProviders();
        $featureConfigs = [];

        foreach (array_keys($features) as $feature) {
            $settings = AiSettingsService::getAllForFeature($feature);
            $apiKey = $settings['api_key'] ?? '';
            $featureConfigs[$feature] = [
                'provider' => $settings['provider'] ?? 'not set',
                'model' => $settings['model'] ?? 'not set',
                'ocr_chat_model' => $settings['ocr_chat_model'] ?? '',
                'base_url' => $settings['base_url'] ?? '',
                'timeout' => $settings['timeout'] ?? 120,
                'api_key_set' => ! empty($apiKey),
                'api_key_masked' => $apiKey ? substr($apiKey, 0, 4).'...'.substr($apiKey, -4) : 'not set',
            ];
        }

        $recentErrors = $this->getRecentAiErrors();

        return view('tools.ai-diagnostics', [
            'features' => $features,
            'providers' => $providers,
            'featureConfigs' => $featureConfigs,
            'recentErrors' => $recentErrors,
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $request->validate([
            'feature' => 'required|string|in:'.implode(',', array_keys(AiSettingsService::getFeatures())),
            'provider' => 'required|string|in:'.implode(',', array_keys(AiSettingsService::getAvailableProviders())),
            'model' => 'required|string|max:100',
            'ocr_chat_model' => 'nullable|string|max:100',
            'base_url' => 'nullable|url|max:255',
            'timeout' => 'nullable|integer|min:10|max:300',
        ]);

        $feature = $request->input('feature');

        AiSettingsService::set($feature, 'provider', $request->input('provider'));
        AiSettingsService::set($feature, 'model', $request->input('model'));

        if ($request->filled('ocr_chat_model')) {
            AiSettingsService::set($feature, 'ocr_chat_model', $request->input('ocr_chat_model'));
        }
        if ($request->filled('base_url')) {
            AiSettingsService::set($feature, 'base_url', $request->input('base_url'));
        }
        if ($request->filled('timeout')) {
            AiSettingsService::set($feature, 'timeout', $request->input('timeout'));
        }

        // Restart queue workers so they pick up new settings
        Artisan::call('queue:restart');

        Log::info('AI settings updated', [
            'feature' => $feature,
            'provider' => $request->input('provider'),
            'model' => $request->input('model'),
            'changed_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Settings saved. Queue workers have been signalled to restart.',
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $testType = $request->input('test_type', 'text');
        $feature = $request->input('feature', 'invoice_parsing');
        $config = AiSettingsService::getAllForFeature($feature);

        if (empty($config['api_key'])) {
            return response()->json([
                'success' => false,
                'error' => 'No API key configured for this provider. Check your .env file.',
                'duration_ms' => 0,
            ]);
        }

        $startTime = microtime(true);

        try {
            $result = match ($testType) {
                'vision' => $this->testVision($config),
                'ocr' => $this->testOcr($config),
                default => $this->testText($config),
            };

            $duration = round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'success' => true,
                'duration_ms' => $duration,
                'model' => $config['model'] ?? 'unknown',
                'response' => $result['response'],
                'usage' => $result['usage'] ?? null,
                'status_code' => $result['status_code'],
            ]);

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
        }
    }

    protected function testText(array $config): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$config['api_key'],
            'Content-Type' => 'application/json',
        ])->timeout((int) ($config['timeout'] ?? 30))->post(($config['base_url'] ?? 'https://api.mistral.ai/v1').'/chat/completions', [
            'model' => $config['model'] ?? 'mistral-small-latest',
            'messages' => [
                ['role' => 'user', 'content' => 'Reply with exactly: "AI connection OK". Nothing else.'],
            ],
            'max_tokens' => 20,
            'temperature' => 0,
        ]);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? $response->json('message') ?? $response->body();
            throw new \Exception('API error ('.$response->status().'): '.$error);
        }

        return [
            'response' => $response->json('choices.0.message.content', ''),
            'usage' => $response->json('usage'),
            'status_code' => $response->status(),
        ];
    }

    protected function testVision(array $config): array
    {
        $imageBase64 = $this->createTestImage();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$config['api_key'],
            'Content-Type' => 'application/json',
        ])->timeout((int) ($config['timeout'] ?? 60))->post(($config['base_url'] ?? 'https://api.mistral.ai/v1').'/chat/completions', [
            'model' => $config['model'] ?? 'mistral-small-latest',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'What text do you see in this image? Reply briefly.'],
                        ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,'.$imageBase64]],
                    ],
                ],
            ],
            'max_tokens' => 100,
            'temperature' => 0,
        ]);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? $response->json('message') ?? $response->body();
            throw new \Exception('Vision API error ('.$response->status().'): '.$error);
        }

        return [
            'response' => $response->json('choices.0.message.content', ''),
            'usage' => $response->json('usage'),
            'status_code' => $response->status(),
        ];
    }

    protected function testOcr(array $config): array
    {
        $imageBase64 = $this->createTestImage();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.($config['api_key'] ?? ''),
            'Content-Type' => 'application/json',
        ])->timeout((int) ($config['timeout'] ?? 60))->post(($config['base_url'] ?? 'https://api.mistral.ai/v1').'/ocr', [
            'model' => 'mistral-ocr-latest',
            'document' => [
                'type' => 'image_url',
                'image_url' => 'data:image/jpeg;base64,'.$imageBase64,
            ],
        ]);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? $response->json('message') ?? $response->body();
            throw new \Exception('OCR API error ('.$response->status().'): '.$error);
        }

        $pages = $response->json('pages', []);
        $ocrText = collect($pages)->pluck('markdown')->implode("\n");

        return [
            'response' => $ocrText ?: '(no text extracted)',
            'usage' => $response->json('usage'),
            'status_code' => $response->status(),
        ];
    }

    protected function createTestImage(): string
    {
        $img = imagecreatetruecolor(200, 100);
        $bg = imagecolorallocate($img, 255, 255, 255);
        $text = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, 199, 99, $bg);
        imagestring($img, 5, 10, 10, 'INVOICE #12345', $text);
        imagestring($img, 5, 10, 35, 'Date: 2026-01-15', $text);
        imagestring($img, 5, 10, 60, 'Total: EUR 99.99', $text);

        ob_start();
        imagejpeg($img, null, 90);
        $imageBase64 = base64_encode(ob_get_clean());
        imagedestroy($img);

        return $imageBase64;
    }

    protected function getRecentAiErrors(): array
    {
        $logFile = storage_path('logs/laravel.log');
        if (! file_exists($logFile)) {
            return [];
        }

        $lines = [];
        $fp = fopen($logFile, 'r');
        if (! $fp) {
            return [];
        }

        fseek($fp, 0, SEEK_END);
        $pos = ftell($fp);
        $lineCount = 0;
        $buffer = '';

        while ($pos > 0 && $lineCount < 500) {
            $pos--;
            fseek($fp, $pos);
            $char = fgetc($fp);
            if ($char === "\n" && $buffer !== '') {
                $lines[] = $buffer;
                $buffer = '';
                $lineCount++;
            } else {
                $buffer = $char.$buffer;
            }
        }
        if ($buffer !== '') {
            $lines[] = $buffer;
        }
        fclose($fp);

        $errors = [];
        foreach ($lines as $line) {
            if (preg_match('/\[([\d-]+ [\d:]+)\].*(?:Mistral|Gemini|AI.*pars|invoice.*pars|camera.*fail|Bearer token|OCR)/i', $line, $matches)) {
                $errors[] = [
                    'timestamp' => $matches[1],
                    'message' => trim(substr($line, 0, 300)),
                ];
                if (count($errors) >= 20) {
                    break;
                }
            }
        }

        return $errors;
    }
}
