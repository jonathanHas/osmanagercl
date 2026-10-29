<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\Base64ImageSource;
use Anthropic\Messages\Base64PDFSource;
use Anthropic\Messages\DocumentBlockParam;
use Anthropic\Messages\ImageBlockParam;
use Anthropic\Messages\StopReason;
use Anthropic\Messages\TextBlockParam;

/**
 * Claude (Anthropic) provider for the per-feature AI settings.
 *
 * Model and timeout come from AiSettingsService for the given feature, the key
 * from ANTHROPIC_API_KEY via config('services.anthropic.api_key').
 */
class ClaudeAiService
{
    public const DEFAULT_MODEL = 'claude-opus-5-5';

    /**
     * Send a prompt with optional attachments and return the text of the reply.
     *
     * @param  array<int, array{data: string, mime: string}>  $attachments  base64 data; 'application/pdf' or an image type
     */
    public function complete(string $feature, string $prompt, array $attachments = [], int $maxTokens = 16000): string
    {
        $apiKey = AiSettingsService::getApiKey($feature);
        if (! $apiKey) {
            throw new \Exception('ANTHROPIC_API_KEY is not set in .env');
        }

        $model = (string) AiSettingsService::get($feature, 'model', self::DEFAULT_MODEL);
        $timeout = (float) AiSettingsService::get($feature, 'timeout', 120);

        // Attachments go before the text so the instructions refer to them.
        $content = [];
        foreach ($attachments as $attachment) {
            $content[] = $attachment['mime'] === 'application/pdf'
                ? DocumentBlockParam::with(source: Base64PDFSource::with(data: $attachment['data']))
                : ImageBlockParam::with(source: Base64ImageSource::with(data: $attachment['data'], mediaType: $attachment['mime']));
        }
        $content[] = TextBlockParam::with(text: $prompt);

        $client = new Client(apiKey: $apiKey, requestOptions: ['timeout' => $timeout]);

        try {
            $message = $client->messages->create(
                model: $model,
                maxTokens: $maxTokens,
                messages: [['role' => 'user', 'content' => $content]],
            );
        } catch (APIStatusException $e) {
            $error = is_array($e->body) ? ($e->body['error']['message'] ?? json_encode($e->body)) : (string) $e->body;
            throw new \Exception('Claude API error ('.$e->status.'): '.$error, 0, $e);
        }

        if ($message->stopReason === StopReason::REFUSAL->value) {
            throw new \Exception('Claude declined this request.');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        if ($message->stopReason === StopReason::MAX_TOKENS->value) {
            throw new \Exception('Claude reply was cut off at the token limit.');
        }

        return $text;
    }
}
