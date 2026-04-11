<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AiSettingsService
{
    /**
     * Get an AI setting for a feature, falling back to config then default.
     *
     * Reads from app_settings table key "ai.{feature}.{key}".
     * Falls back to config('invoices.ai_parsing.{key}') for invoice_parsing,
     * or config('gemini.{key}') for label_translation, then to $default.
     */
    public static function get(string $feature, string $key, mixed $default = null): mixed
    {
        // Try DB first
        $dbKey = "ai.{$feature}.{$key}";
        $row = DB::table('app_settings')->where('key', $dbKey)->first();

        if ($row && $row->value !== null && $row->value !== '') {
            return $row->value;
        }

        // Fall back to config
        $configValue = match ($feature) {
            'invoice_parsing' => config("invoices.ai_parsing.{$key}"),
            'label_translation' => match ($key) {
                'model' => config('invoices.ai_parsing.model', 'gemini-2.5-flash'),
                'provider' => config('invoices.ai_parsing.provider', 'gemini'),
                default => config("invoices.ai_parsing.{$key}"),
            },
            default => null,
        };

        return $configValue ?? $default;
    }

    /**
     * Save an AI setting for a feature to the database.
     */
    public static function set(string $feature, string $key, ?string $value): void
    {
        $dbKey = "ai.{$feature}.{$key}";

        DB::table('app_settings')->updateOrInsert(
            ['key' => $dbKey],
            ['value' => $value, 'updated_at' => now()]
        );
    }

    /**
     * Get all settings for a feature as an array.
     * Merges DB settings over config defaults.
     */
    public static function getAllForFeature(string $feature): array
    {
        $defaults = self::getDefaults($feature);
        $result = [];

        foreach ($defaults as $key => $default) {
            $result[$key] = self::get($feature, $key, $default);
        }

        // API key always from env, never DB
        $result['api_key'] = self::getApiKey($feature);

        return $result;
    }

    /**
     * Get the API key for a feature (always from .env, never DB).
     */
    public static function getApiKey(string $feature): ?string
    {
        $provider = self::get($feature, 'provider', 'mistral');

        return match (true) {
            str_starts_with($provider, 'gemini') => config('gemini.api_key') ?: env('GEMINI_API_KEY'),
            str_starts_with($provider, 'mistral') => env('MISTRAL_API_KEY'),
            $provider === 'openai' => env('OPENAI_API_KEY'),
            default => env('MISTRAL_API_KEY'),
        };
    }

    /**
     * Get default settings per feature.
     */
    public static function getDefaults(string $feature): array
    {
        return match ($feature) {
            'invoice_parsing' => [
                'provider' => 'mistral-ocr',
                'model' => 'mistral-small-latest',
                'ocr_chat_model' => 'mistral-small-latest',
                'base_url' => 'https://api.mistral.ai/v1',
                'timeout' => '120',
            ],
            'label_translation' => [
                'provider' => 'gemini',
                'model' => 'gemini-2.5-flash',
                'base_url' => 'https://api.mistral.ai/v1',
                'timeout' => '120',
            ],
            default => [],
        };
    }

    /**
     * List available providers with descriptions.
     */
    public static function getAvailableProviders(): array
    {
        return [
            'gemini' => [
                'label' => 'Google Gemini',
                'description' => 'Uses Gemini PHP package. Requires GEMINI_API_KEY.',
                'models' => ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash'],
            ],
            'mistral' => [
                'label' => 'Mistral Vision',
                'description' => 'Chat completions with vision (pixtral models). Requires MISTRAL_API_KEY.',
                'models' => ['mistral-small-latest', 'pixtral-large-latest', 'mistral-medium-latest'],
            ],
            'mistral-ocr' => [
                'label' => 'Mistral OCR',
                'description' => 'Two-step: OCR extracts text, then chat model structures it. Best for handwritten/paper documents.',
                'models' => ['mistral-small-latest', 'mistral-medium-latest'],
            ],
            'openai' => [
                'label' => 'OpenAI',
                'description' => 'OpenAI-compatible chat completions with vision. Requires OPENAI_API_KEY.',
                'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'],
            ],
        ];
    }

    /**
     * List available features.
     */
    public static function getFeatures(): array
    {
        return [
            'invoice_parsing' => 'Invoice Parsing (Camera Capture)',
            'label_translation' => 'Label Translation',
        ];
    }
}
