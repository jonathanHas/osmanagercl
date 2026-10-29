<?php

namespace Tests\Unit;

use App\Services\AiSettingsService;
use App\Services\ClaudeAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed on this machine.');
        }

        parent::setUp();

        config([
            'services.anthropic.api_key' => 'anthropic-key',
            'services.openai.api_key' => 'openai-key',
            'invoices.ai_parsing.api_key' => 'mistral-key',
        ]);
    }

    public function test_each_provider_reads_its_own_api_key(): void
    {
        foreach (['anthropic' => 'anthropic-key', 'openai' => 'openai-key', 'mistral-ocr' => 'mistral-key'] as $provider => $key) {
            AiSettingsService::set('invoice_parsing', 'provider', $provider);

            $this->assertSame($key, AiSettingsService::getApiKey('invoice_parsing'), $provider);
        }
    }

    public function test_anthropic_is_an_available_provider(): void
    {
        $providers = AiSettingsService::getAvailableProviders();

        $this->assertArrayHasKey('anthropic', $providers);
        $this->assertContains(ClaudeAiService::DEFAULT_MODEL, $providers['anthropic']['models']);
    }

    public function test_claude_refuses_to_call_without_a_key(): void
    {
        if (env('ANTHROPIC_API_KEY')) {
            $this->markTestSkipped('ANTHROPIC_API_KEY is set in this environment.');
        }

        config(['services.anthropic.api_key' => null]);
        AiSettingsService::set('label_translation', 'provider', 'anthropic');

        $this->expectExceptionMessage('ANTHROPIC_API_KEY is not set');

        (new ClaudeAiService)->complete('label_translation', 'Say OK');
    }
}
