<?php

namespace App\Services\BookStack;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The only class that talks HTTP to BookStack, where the shop's procedures
 * (SOPs) are written. Read-only: it sends GET requests and nothing else.
 *
 * Failed API calls throw; callers (ScreenHelp) catch. The API token is only
 * ever placed in the Authorization header, never in a URL or a message.
 */
class BookStackClient
{
    private const PAGE_SIZE = 100;

    private const MAX_SEARCH_PAGES = 10;

    public function isConfigured(): bool
    {
        $config = config('services.bookstack');

        return filled($config['url'] ?? null)
            && filled($config['token_id'] ?? null)
            && filled($config['token_secret'] ?? null);
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('services.bookstack.url'), '/');
    }

    /**
     * Every page carrying a screen tag.
     *
     * @return array<int, array{id: int, title: string, url: string, screens: string[]}>
     */
    public function taggedPages(): array
    {
        $tag = (string) config('services.bookstack.screen_tag', 'screen');
        $pages = [];

        for ($n = 1; $n <= self::MAX_SEARCH_PAGES; $n++) {
            $hits = $this->api()->get($this->baseUrl().'/api/search', [
                'query' => "[{$tag}] {type:page}",
                'count' => self::PAGE_SIZE,
                'page' => $n,
            ])->throw()->json('data') ?? [];

            foreach ($hits as $hit) {
                if (($hit['type'] ?? null) !== 'page' || ! isset($hit['id'])) {
                    continue;
                }

                // Some instances do not return tags with search hits; read the page.
                $tags = array_key_exists('tags', $hit)
                    ? ($hit['tags'] ?? [])
                    : ($this->fetchPage((int) $hit['id'])['tags'] ?? []);

                $screens = $this->screenValues($tags, $tag);
                if ($screens === []) {
                    continue;
                }

                $pages[] = [
                    'id' => (int) $hit['id'],
                    'title' => (string) ($hit['name'] ?? ''),
                    'url' => (string) ($hit['url'] ?? ''),
                    'screens' => $screens,
                ];
            }

            if (count($hits) < self::PAGE_SIZE) {
                break;
            }
        }

        return $pages;
    }

    /**
     * @return array{id: int, title: string, html: string, updated_at: ?string}
     */
    public function page(int $id): array
    {
        $page = $this->fetchPage($id);

        return [
            'id' => (int) ($page['id'] ?? $id),
            'title' => (string) ($page['name'] ?? ''),
            'html' => (string) ($page['html'] ?? ''),
            'updated_at' => $page['updated_at'] ?? null,
        ];
    }

    /**
     * An uploaded image, fetched without the API token.
     *
     * @return array{body: string, type: string}|null
     */
    public function image(string $path): ?array
    {
        $response = $this->http()->get($this->baseUrl().'/'.ltrim($path, '/'));

        if (! $response->successful()) {
            return null;
        }

        return [
            'body' => $response->body(),
            'type' => (string) $response->header('Content-Type'),
        ];
    }

    private function fetchPage(int $id): array
    {
        return $this->api()->get($this->baseUrl().'/api/pages/'.$id)->throw()->json() ?? [];
    }

    /**
     * @return string[]
     */
    private function screenValues(array $tags, string $tag): array
    {
        $values = [];
        foreach ($tags as $t) {
            if (strcasecmp((string) ($t['name'] ?? ''), $tag) !== 0) {
                continue;
            }
            $value = trim((string) ($t['value'] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    private function http(): PendingRequest
    {
        return Http::connectTimeout(2)
            ->timeout((int) config('services.bookstack.timeout', 3));
    }

    private function api(): PendingRequest
    {
        $config = config('services.bookstack');

        return $this->http()
            ->acceptJson()
            ->withHeaders(['Authorization' => 'Token '.$config['token_id'].':'.$config['token_secret']]);
    }
}
